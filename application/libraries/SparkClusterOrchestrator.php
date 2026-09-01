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
            : (getenv('JOBSEEKER_SPARK_BIND_SOURCE') ?: '/jobseeker/src/spark')), '/');
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
    public function start($job, $cluster, $runtime, $triggeredBy, $workerOverride = NULL)
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

        $ceiling = min($this->maxWorkers(), max(1, (int) $cluster->max_workers ?: $this->maxWorkers()));
        $requestedWorkers = $workerOverride !== NULL
            ? max(1, min($ceiling, (int) $workerOverride))
            : min($ceiling, max(1, (int) $cluster->min_workers));

        // Size the run to the engine host: never let a cluster reserve more CPU
        // or memory than the host can back. Scale workers down to fit; reject
        // only when even the driver + one worker cannot.
        $admission = $this->admitToHost($cluster, $requestedWorkers);
        if (! $admission['ok']) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => $admission['message']);
        }
        $workerCount = $admission['workers'];
        $capacityNote = $admission['note'];
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
                'master_container_id' => isset($handle['master_id']) ? $handle['master_id'] : NULL,
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
                'worker_cores' => (int) $cluster->worker_cores,
                'worker_memory_mb' => (int) $cluster->worker_memory_mb,
                'spark_conf' => array_merge($sparkConf, $this->decodeMap($job->spark_submit_conf_json)),
                'env' => $env,
            );
            $handle = $this->driver->submitSparkJob($handle, $submitSpec);

            $this->model->updateSparkRun($runId, array(
                'driver_container_id' => isset($handle['driver_id']) ? $handle['driver_id'] : NULL,
                'status' => 'RUNNING',
            ));

            $message = 'Cluster provisioning ('.$workerCount.' worker'.($workerCount === 1 ? '' : 's').'); spark-submit started.';
            if ($capacityNote !== '') {
                $message .= ' '.$capacityNote;
            }
            return array('ok' => TRUE, 'run' => $this->model->getSparkRun($runId), 'message' => $message);
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

    /**
     * Live per-container resource usage for a run's cluster.
     *
     * @return array{engineHealthy:bool, phase:string, terminal:bool, containers:array, aggregate:array}
     */
    public function runStats($run)
    {
        $terminal = ! $run || in_array($run->status, self::TERMINAL, TRUE);
        if ($terminal) {
            return array('engineHealthy' => TRUE, 'phase' => $run ? (string) $run->status : 'UNKNOWN', 'terminal' => TRUE, 'containers' => array(), 'aggregate' => new stdClass());
        }
        $stats = $this->driver->clusterStats($this->handleFromRun($run));
        return array(
            'engineHealthy' => $this->driver->healthy(),
            'phase' => (string) $run->status,
            'terminal' => FALSE,
            'containers' => $stats['containers'],
            'aggregate' => $stats['aggregate'],
        );
    }

    /**
     * Host capacity plus, optionally, what a given cluster spec would consume at
     * its min / max worker counts. Drives the "proportional" UI widget.
     */
    public function capacity($cluster = NULL)
    {
        $snapshot = $this->driver->capacitySnapshot();
        $result = array('host' => $snapshot, 'demand' => NULL);
        if ($cluster) {
            $driverCores = max(1, (int) $cluster->driver_cores);
            $driverMemMb = max(512, (int) $cluster->driver_memory_mb);
            $workerCores = max(1, (int) $cluster->worker_cores);
            $workerMemMb = max(512, (int) $cluster->worker_memory_mb);
            $result['demand'] = array(
                'min' => array(
                    'workers' => max(1, (int) $cluster->min_workers),
                    'cpus' => $driverCores + max(1, (int) $cluster->min_workers) * $workerCores,
                    'memoryMb' => $driverMemMb + max(1, (int) $cluster->min_workers) * $workerMemMb,
                ),
                'max' => array(
                    'workers' => max(1, (int) $cluster->max_workers),
                    'cpus' => $driverCores + max(1, (int) $cluster->max_workers) * $workerCores,
                    'memoryMb' => $driverMemMb + max(1, (int) $cluster->max_workers) * $workerMemMb,
                ),
                'fitsMax' => ! $snapshot['available'] || (
                    ($driverMemMb + max(1, (int) $cluster->max_workers) * $workerMemMb) <= $snapshot['freeMemoryMb']
                    && ($driverCores + max(1, (int) $cluster->max_workers) * $workerCores) <= $snapshot['freeCpus']
                ),
            );
        }
        return $result;
    }

    /**
     * @return array{ok:bool, workers:int, note:string, message:string}
     */
    private function admitToHost($cluster, $requestedWorkers)
    {
        $snapshot = $this->driver->capacitySnapshot();
        if (empty($snapshot['available'])) {
            // Can't measure the host (e.g. Kubernetes driver) - trust the caller.
            return array('ok' => TRUE, 'workers' => $requestedWorkers, 'note' => '', 'message' => '');
        }

        $driverMemMb = max(512, (int) $cluster->driver_memory_mb);
        $driverCores = max(1, (int) $cluster->driver_cores);
        $workerMemMb = max(512, (int) $cluster->worker_memory_mb);
        $workerCores = max(1, (int) $cluster->worker_cores);
        $freeMemMb = (int) $snapshot['freeMemoryMb'];
        $freeCpus = (float) $snapshot['freeCpus'];

        $baseline = $driverMemMb + $workerMemMb;
        $baselineCpus = $driverCores + $workerCores;
        if ($baseline > $freeMemMb || $baselineCpus > $freeCpus) {
            return array('ok' => FALSE, 'workers' => 0, 'note' => '', 'message' => sprintf(
                'Not enough headroom on the compute host for this cluster. Driver + 1 worker need %d MB / %s vCPU; only %d MB / %s vCPU are free. Lower the driver/worker size on the cluster spec.',
                $baseline, rtrim(rtrim(number_format($baselineCpus, 1), '0'), '.'), $freeMemMb, rtrim(rtrim(number_format($freeCpus, 1), '0'), '.')
            ));
        }

        $fit = $requestedWorkers;
        while ($fit > 1 && (($driverMemMb + $fit * $workerMemMb) > $freeMemMb || ($driverCores + $fit * $workerCores) > $freeCpus)) {
            $fit--;
        }
        $note = $fit < $requestedWorkers
            ? sprintf('Scaled from %d to %d workers to stay within host headroom (%d MB / %s vCPU free).', $requestedWorkers, $fit, $freeMemMb, rtrim(rtrim(number_format($freeCpus, 1), '0'), '.'))
            : '';
        return array('ok' => TRUE, 'workers' => $fit, 'note' => $note, 'message' => '');
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
            'master_id' => isset($run->master_container_id) ? (string) $run->master_container_id : '',
            'worker_ids' => json_decode((string) $run->worker_container_ids_json, TRUE) ?: array(),
            'driver_id' => (string) $run->driver_container_id,
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
