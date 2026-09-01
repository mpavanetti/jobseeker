<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/ComputeDriver.php';

/**
 * Drives the life cycle of one Spark job run against an ephemeral cluster.
 *
 * Databricks job-cluster model: nothing runs until a job is triggered. `start()`
 * provisions a per-run network + master + workers + driver in a single request
 * (a short burst of Engine API calls) and returns. The browser then polls
 * `advance()` which inspects the driver, and on completion pulls the log tail,
 * records the exit code and tears the whole cluster down.
 *
 * No long-lived PHP request and no server-side loop: every transition is driven
 * by a poll, so the feature stays light.
 */
class SparkClusterOrchestrator
{
    const TERMINAL = array('SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT');
    const LOG_TAIL_MAX = 60000;

    /** @var object SparkCompute_model */
    private $model;

    /** @var ComputeDriver */
    private $driver;

    /** @var string filesystem root the PHP container writes job source into */
    private $repositoryRoot;

    /** @var string path the engine mounts that same source from */
    private $bindSource;

    public function __construct($config = array())
    {
        $this->model = isset($config['model']) ? $config['model'] : NULL;
        $this->driver = isset($config['driver']) && $config['driver'] instanceof ComputeDriver
            ? $config['driver']
            : ComputeDriverFactory::make();
        $this->repositoryRoot = rtrim((string) (isset($config['repository_root']) && $config['repository_root'] !== ''
            ? $config['repository_root']
            : (getenv('JOBSEEKER_COMPUTE_REPOSITORY_ROOT') ?: '/php/repository')), '/');
        $this->bindSource = rtrim((string) (isset($config['spark_bind_source']) && $config['spark_bind_source'] !== ''
            ? $config['spark_bind_source']
            : (getenv('JOBSEEKER_SPARK_BIND_SOURCE') ?: '/jobseeker/spark')), '/');
    }

    public function driverName()
    {
        return $this->driver->name();
    }

    public function engineHealthy()
    {
        return $this->driver->healthy();
    }

    private function maxWorkers()
    {
        return max(1, min(64, (int) (getenv('JOBSEEKER_SPARK_MAX_WORKERS') ?: 8)));
    }

    private function maxConcurrentRuns()
    {
        return max(1, min(20, (int) (getenv('JOBSEEKER_COMPUTE_MAX_CONCURRENT_RUNS') ?: 3)));
    }

    private function runTimeoutSeconds()
    {
        return max(120, min(21600, (int) (getenv('JOBSEEKER_COMPUTE_RUN_TIMEOUT_SECONDS') ?: 1800)));
    }

    /**
     * @return array{ok:bool, run:object|null, message:string}
     */
    public function start($job, $cluster, $runtime, $triggeredBy)
    {
        if (! $job || ! $cluster || ! $runtime) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'Job, cluster and runtime are all required.');
        }
        if ($this->model->activeSparkRunCount() >= $this->maxConcurrentRuns()) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'Too many Spark runs are already in flight ('.$this->maxConcurrentRuns().' max). Try again shortly.');
        }
        if (! $this->driver->healthy()) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'The compute engine ('.$this->driver->name().') is not reachable.');
        }

        $image = $this->imageReference($runtime);
        $imageCheck = $this->driver->ensureImage($image);
        if (empty($imageCheck['ok'])) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => $imageCheck['message']);
        }

        $workerCount = min($this->maxWorkers(), max(1, (int) $cluster->min_workers));
        $runId = $this->model->createSparkRun($job->id, $job->environment, $triggeredBy, $workerCount);
        $run = $this->model->getSparkRun($runId);
        if (! $run) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'Could not record the run.');
        }

        try {
            $entryPoint = $this->resolveEntryPoint($job);
            $this->model->updateSparkRun($runId, array('status' => 'PROVISIONING'));

            $sparkConf = $this->decodeMap($cluster->spark_conf_json);
            $env = $this->decodeMap($cluster->env_json);
            $provisionSpec = array(
                'run_key' => $run->run_key,
                'job_name' => $job->name,
                'environment' => $job->environment,
                'image' => $image,
                'worker_count' => $workerCount,
                'worker_cores' => (int) $cluster->worker_cores,
                'worker_memory_mb' => (int) $cluster->worker_memory_mb,
                'spark_conf' => $sparkConf,
                'env' => $env,
                'bind_source' => $this->bindSource,
            );
            $handle = $this->driver->provisionSparkCluster($provisionSpec);

            $this->model->updateSparkRun($runId, array(
                'cluster_network' => isset($handle['network']) ? $handle['network'] : NULL,
                'worker_container_ids_json' => json_encode(array_values($handle['worker_ids'] ?? array())),
                'worker_count' => count($handle['worker_ids'] ?? array()),
                'provisioned_at' => date('Y-m-d H:i:s'),
            ));

            $submitSpec = array(
                'run_key' => $run->run_key,
                'job_name' => $job->name,
                'environment' => $job->environment,
                'entry_point' => $entryPoint,
                'application_args' => $this->splitArgs($job->application_args),
                'driver_cores' => (int) $cluster->driver_cores,
                'driver_memory_mb' => (int) $cluster->driver_memory_mb,
                'spark_conf' => array_merge($sparkConf, $this->decodeMap($job->spark_submit_conf_json)),
                'env' => $env,
            );
            $handle = $this->driver->submitSparkJob($handle, $submitSpec);

            $this->model->updateSparkRun($runId, array(
                'driver_container_id' => isset($handle['driver_id']) ? $handle['driver_id'] : NULL,
                'status' => 'RUNNING',
            ));

            return array('ok' => TRUE, 'run' => $this->model->getSparkRun($runId), 'message' => 'Cluster provisioning; spark-submit started.');
        } catch (Exception $exception) {
            $this->failAndTeardown($runId, $exception->getMessage());
            return array('ok' => FALSE, 'run' => $this->model->getSparkRun($runId), 'message' => 'Run failed: '.$exception->getMessage());
        }
    }

    /**
     * Advance a non-terminal run by one step and return the fresh row.
     */
    public function advance($run)
    {
        if (! $run || in_array($run->status, self::TERMINAL, TRUE)) {
            return $run;
        }

        $handle = $this->handleFromRun($run);

        $ageSeconds = time() - strtotime($run->started_at.' UTC');
        if ($ageSeconds > $this->runTimeoutSeconds()) {
            $this->finish($run->id, 'TIMED_OUT', NULL, $this->driver->fetchSparkLogs($handle), 'Run exceeded '.$this->runTimeoutSeconds().'s and was stopped.');
            $this->driver->teardownSpark($handle);
            return $this->model->getSparkRun($run->id);
        }

        $poll = $this->driver->pollSparkRun($handle);
        $phase = $poll['phase'];

        if ($phase === 'PROVISIONING' || $phase === 'RUNNING') {
            $this->model->updateSparkRun($run->id, array('status' => $phase === 'RUNNING' ? 'RUNNING' : 'PROVISIONING'));
            return $this->model->getSparkRun($run->id);
        }

        if ($phase === 'SUCCEEDED' || $phase === 'FAILED') {
            $logs = $this->driver->fetchSparkLogs($handle);
            $this->finish($run->id, $phase, $poll['exit_code'], $logs, $phase === 'FAILED' ? $poll['detail'] : '');
            $this->driver->teardownSpark($handle);
            return $this->model->getSparkRun($run->id);
        }

        // UNKNOWN: give the engine a short grace period before declaring failure.
        if ($ageSeconds > 90) {
            $this->finish($run->id, 'FAILED', NULL, $this->driver->fetchSparkLogs($handle), 'Lost track of the driver container: '.$poll['detail']);
            $this->driver->teardownSpark($handle);
        }
        return $this->model->getSparkRun($run->id);
    }

    public function cancel($run, $triggeredBy)
    {
        if (! $run || in_array($run->status, self::TERMINAL, TRUE)) {
            return $run;
        }
        $handle = $this->handleFromRun($run);
        $logs = $this->driver->fetchSparkLogs($handle);
        $this->finish($run->id, 'CANCELLED', NULL, $logs, 'Cancelled by '.$triggeredBy.'.');
        $this->driver->teardownSpark($handle);
        return $this->model->getSparkRun($run->id);
    }

    public function liveLogs($run)
    {
        if (! $run) {
            return '';
        }
        if (in_array($run->status, self::TERMINAL, TRUE)) {
            return (string) $run->log_tail;
        }
        return $this->driver->fetchSparkLogs($this->handleFromRun($run));
    }

    // --- internals ---------------------------------------------------

    private function imageReference($runtime)
    {
        $repository = trim((string) $runtime->image_repository);
        $tag = trim((string) $runtime->image_tag);
        return $tag === '' ? $repository : $repository.':'.$tag;
    }

    private function resolveEntryPoint($job)
    {
        if ((string) $job->source_type === 'inline') {
            $key = preg_replace('/[^a-z0-9._-]+/', '-', strtolower((string) $job->job_key));
            $dir = $this->repositoryRoot.'/spark/inline/'.$key;
            if (! is_dir($dir) && ! @mkdir($dir, 0775, TRUE) && ! is_dir($dir)) {
                throw new RuntimeException('Could not create the inline job workspace.');
            }
            if (@file_put_contents($dir.'/main.py', (string) $job->inline_code) === FALSE) {
                throw new RuntimeException('Could not write the inline job source.');
            }
            return 'inline/'.$key.'/main.py';
        }

        $entry = ltrim(trim((string) $job->entry_point), '/');
        if ($entry === '' || strpos($entry, '..') !== FALSE || ! preg_match('#^(jobs|inline)/[A-Za-z0-9._/-]+\.py$#', $entry)) {
            throw new RuntimeException('Entry point must be a .py file under jobs/ or inline/.');
        }
        return $entry;
    }

    private function decodeMap($json)
    {
        $decoded = json_decode((string) $json, TRUE);
        return is_array($decoded) ? $decoded : array();
    }

    private function splitArgs($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }
        // Whitespace-separated, with simple double-quote grouping.
        preg_match_all('/"([^"]*)"|(\S+)/', $raw, $matches, PREG_SET_ORDER);
        $args = array();
        foreach ($matches as $match) {
            $args[] = $match[1] !== '' || $match[0] === '""' ? $match[1] : $match[2];
        }
        return $args;
    }

    private function handleFromRun($run)
    {
        return array(
            'run_key' => (string) $run->run_key,
            'network' => (string) $run->cluster_network,
            'image' => '',
            'master_id' => '',
            'worker_ids' => json_decode((string) $run->worker_container_ids_json, TRUE) ?: array(),
            'driver_id' => (string) $run->driver_container_id,
            'master_id_hint' => 'js-'.$run->run_key.'-master',
            'bind_source' => $this->bindSource,
        );
    }

    private function finish($runId, $status, $exitCode, $logs, $error)
    {
        $this->model->updateSparkRun($runId, array(
            'status' => $status,
            'exit_code' => $exitCode === NULL ? NULL : (int) $exitCode,
            'log_tail' => $this->clampLogs($logs),
            'error_message' => $error === '' ? NULL : substr((string) $error, 0, 2000),
            'completed_at' => date('Y-m-d H:i:s'),
        ));
    }

    private function failAndTeardown($runId, $message)
    {
        $run = $this->model->getSparkRun($runId);
        if ($run) {
            $this->driver->teardownSpark($this->handleFromRun($run));
        }
        $this->model->updateSparkRun($runId, array(
            'status' => 'FAILED',
            'error_message' => substr((string) $message, 0, 2000),
            'completed_at' => date('Y-m-d H:i:s'),
        ));
    }

    private function clampLogs($logs)
    {
        $logs = (string) $logs;
        if (strlen($logs) <= self::LOG_TAIL_MAX) {
            return $logs;
        }
        return "...[truncated]...\n".substr($logs, -self::LOG_TAIL_MAX);
    }
}
