<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/ComputeDriver.php';

/**
 * Drives the life cycle of one ML job run: a single ephemeral container on the
 * selected Miniconda-based runtime image, no network. Same poll-driven model as
 * SparkClusterOrchestrator - `start()` launches the container, the browser polls
 * `advance()`, and completion captures logs / exit code and removes it.
 */
class MlJobOrchestrator
{
    const TERMINAL = array('SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT');
    const LOG_TAIL_MAX = 60000;

    /** @var object MlCompute_model */
    private $model;

    /** @var ComputeDriver */
    private $driver;

    /** @var string */
    private $repositoryRoot;

    /** @var string */
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
        $this->bindSource = rtrim((string) (isset($config['ml_bind_source']) && $config['ml_bind_source'] !== ''
            ? $config['ml_bind_source']
            : (getenv('JOBSEEKER_ML_BIND_SOURCE') ?: '/jobseeker/ml')), '/');
    }

    public function driverName()
    {
        return $this->driver->name();
    }

    public function engineHealthy()
    {
        return $this->driver->healthy();
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
    public function start($job, $runtime, $triggeredBy)
    {
        if (! $job || ! $runtime) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'Job and runtime are required.');
        }
        if ($this->model->activeMlRunCount() >= $this->maxConcurrentRuns()) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'Too many ML runs are already in flight ('.$this->maxConcurrentRuns().' max).');
        }
        if (! $this->driver->healthy()) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'The compute engine ('.$this->driver->name().') is not reachable.');
        }

        $image = $this->imageReference($runtime);
        $imageCheck = $this->driver->ensureImage($image);
        if (empty($imageCheck['ok'])) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => $imageCheck['message']);
        }

        $runId = $this->model->createMlRun($job->id, $job->environment, $triggeredBy);
        $run = $this->model->getMlRun($runId);
        if (! $run) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'Could not record the run.');
        }

        try {
            $entryPoint = $this->resolveEntryPoint($job);
            $handle = $this->driver->runMlJob(array(
                'run_key' => $run->run_key,
                'job_name' => $job->name,
                'environment' => $job->environment,
                'image' => $image,
                'entry_point' => $entryPoint,
                'application_args' => $this->splitArgs($job->application_args),
                'cpu_limit' => (float) $job->cpu_limit,
                'memory_limit_mb' => (int) $job->memory_limit_mb,
                'env' => $this->decodeMap($job->env_json),
                'bind_source' => $this->bindSource,
            ));

            $this->model->updateMlRun($runId, array(
                'container_id' => isset($handle['container_id']) ? $handle['container_id'] : NULL,
                'status' => 'RUNNING',
            ));
            return array('ok' => TRUE, 'run' => $this->model->getMlRun($runId), 'message' => 'Job container started.');
        } catch (Exception $exception) {
            $run = $this->model->getMlRun($runId);
            if ($run) {
                $this->driver->teardownMl($this->handleFromRun($run));
            }
            $this->model->updateMlRun($runId, array(
                'status' => 'FAILED',
                'error_message' => substr($exception->getMessage(), 0, 2000),
                'completed_at' => date('Y-m-d H:i:s'),
            ));
            return array('ok' => FALSE, 'run' => $this->model->getMlRun($runId), 'message' => 'Run failed: '.$exception->getMessage());
        }
    }

    public function advance($run)
    {
        if (! $run || in_array($run->status, self::TERMINAL, TRUE)) {
            return $run;
        }
        $handle = $this->handleFromRun($run);

        if (time() - strtotime($run->started_at.' UTC') > $this->runTimeoutSeconds()) {
            $this->finish($run->id, 'TIMED_OUT', NULL, $this->driver->fetchMlLogs($handle), 'Run exceeded '.$this->runTimeoutSeconds().'s and was stopped.');
            $this->driver->teardownMl($handle);
            return $this->model->getMlRun($run->id);
        }

        $poll = $this->driver->pollMlRun($handle);
        if ($poll['phase'] === 'RUNNING') {
            $this->model->updateMlRun($run->id, array('status' => 'RUNNING'));
            return $this->model->getMlRun($run->id);
        }
        if ($poll['phase'] === 'SUCCEEDED' || $poll['phase'] === 'FAILED') {
            $this->finish($run->id, $poll['phase'], $poll['exit_code'], $this->driver->fetchMlLogs($handle), $poll['phase'] === 'FAILED' ? $poll['detail'] : '');
            $this->driver->teardownMl($handle);
            return $this->model->getMlRun($run->id);
        }
        if (time() - strtotime($run->started_at.' UTC') > 90) {
            $this->finish($run->id, 'FAILED', NULL, $this->driver->fetchMlLogs($handle), 'Lost track of the job container: '.$poll['detail']);
            $this->driver->teardownMl($handle);
        }
        return $this->model->getMlRun($run->id);
    }

    public function cancel($run, $triggeredBy)
    {
        if (! $run || in_array($run->status, self::TERMINAL, TRUE)) {
            return $run;
        }
        $handle = $this->handleFromRun($run);
        $logs = $this->driver->fetchMlLogs($handle);
        $this->finish($run->id, 'CANCELLED', NULL, $logs, 'Cancelled by '.$triggeredBy.'.');
        $this->driver->teardownMl($handle);
        return $this->model->getMlRun($run->id);
    }

    public function liveLogs($run)
    {
        if (! $run) {
            return '';
        }
        if (in_array($run->status, self::TERMINAL, TRUE)) {
            return (string) $run->log_tail;
        }
        return $this->driver->fetchMlLogs($this->handleFromRun($run));
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
            $dir = $this->repositoryRoot.'/ml/inline/'.$key;
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
        preg_match_all('/"([^"]*)"|(\S+)/', $raw, $matches, PREG_SET_ORDER);
        $args = array();
        foreach ($matches as $match) {
            $args[] = ($match[1] !== '' || $match[0] === '""') ? $match[1] : $match[2];
        }
        return $args;
    }

    private function handleFromRun($run)
    {
        return array(
            'run_key' => (string) $run->run_key,
            'container_id' => (string) $run->container_id,
        );
    }

    private function finish($runId, $status, $exitCode, $logs, $error)
    {
        $this->model->updateMlRun($runId, array(
            'status' => $status,
            'exit_code' => $exitCode === NULL ? NULL : (int) $exitCode,
            'log_tail' => $this->clampLogs($logs),
            'error_message' => $error === '' ? NULL : substr((string) $error, 0, 2000),
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
