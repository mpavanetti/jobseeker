<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/MlComputeDriver.php';
require_once APPPATH.'libraries/MlWorkspace.php';

/**
 * Life cycle of one ML run: materialise the job source, size the run to the
 * engine host, start a single container on the selected runtime image, and
 * advance it on each browser / Jenkins poll until it finishes - capturing the
 * console log and exit code and tearing the container down.
 *
 * Metrics, artifacts, model versions and output datasets are NOT collected here:
 * the jobseeker_ml SDK inside the container streams them to MlRuntime::ingest /
 * ::dataset over HTTP while the job runs. This class only owns execution.
 */
class MlRunOrchestrator
{
    const TERMINAL = array('SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT');
    const LOG_TAIL_MAX = 80000;

    /** @var object MlCatalog_model */
    private $catalog;
    /** @var object MlRun_model */
    private $runs;
    /** @var MlComputeDriver */
    private $driver;

    private $repositoryRoot;
    private $bindSource;
    private $runtimeUrl;
    private $runToken;
    /** @var MlWorkspace */
    private $workspace;

    public function __construct($config = array())
    {
        $this->catalog = isset($config['catalog']) ? $config['catalog'] : NULL;
        $this->runs = isset($config['runs']) ? $config['runs'] : NULL;
        $this->driver = isset($config['driver']) && $config['driver'] instanceof MlComputeDriver
            ? $config['driver'] : MlComputeDriverFactory::make($config);
        $this->workspace = isset($config['workspace']) && $config['workspace'] instanceof MlWorkspace
            ? $config['workspace'] : new MlWorkspace();

        $this->repositoryRoot = rtrim((string) (getenv('JOBSEEKER_ML_REPOSITORY_ROOT')
            ?: (rtrim(FCPATH, '/\\').'/repository/ml')), '/');
        $this->bindSource = rtrim((string) (getenv('JOBSEEKER_ML_BIND_SOURCE')
            ?: $this->repositoryRoot), '/');
        $this->runtimeUrl = trim((string) (getenv('JOBSEEKER_ML_RUNTIME_URL')
            ?: 'http://nginx:8080/machine-learning/runtime'));
        $this->runToken = trim((string) getenv('JOBSEEKER_ML_API_TOKEN'));
    }

    public function driverName()
    {
        return $this->driver->name();
    }

    public function engineHealthy()
    {
        return $this->driver->healthy();
    }

    public function capacity()
    {
        return array(
            'host' => $this->driver->capacitySnapshot(),
            'active_runs' => $this->runs ? $this->runs->activeRunCount() : 0,
            'max_concurrent' => $this->maxConcurrentRuns(),
            'driver' => $this->driver->name(),
            'engine_healthy' => $this->driver->healthy(),
        );
    }

    private function maxConcurrentRuns()
    {
        return max(1, min(20, (int) (getenv('JOBSEEKER_ML_MAX_CONCURRENT_RUNS') ?: 3)));
    }

    // --- start ------------------------------------------------------

    /**
     * @param object $job     ml_job row
     * @param object $runtime ml_runtime row
     * @param array  $context triggered_by, trigger_source, jenkins_build_number, params (array)
     * @return array{ok:bool, run:object|null, message:string}
     */
    public function start($job, $runtime, array $context)
    {
        if (! $job || ! $runtime) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'Job and runtime are required.');
        }
        $this->reapStaleRuns();

        if ($this->runs->activeRunCount() >= $this->maxConcurrentRuns()) {
            return array('ok' => FALSE, 'run' => NULL,
                'message' => 'Too many ML runs are already in flight ('.$this->maxConcurrentRuns().' max).');
        }
        if (! $this->driver->healthy()) {
            return array('ok' => FALSE, 'run' => NULL,
                'message' => 'The compute engine ('.$this->driver->name().') is not reachable.');
        }

        // Materialise the workspace and get (build if needed) the per-job image.
        $plan = $this->prepareImage($job, $runtime);
        if (empty($plan['ok'])) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => $plan['message']);
        }
        $image = $plan['ref'];

        $cpu = max(0.25, (float) $job->cpu_limit);
        $memMb = max(256, (int) $job->memory_limit_mb);
        $host = $this->driver->capacitySnapshot();
        if (! empty($host['available']) && ($memMb > $host['freeMemoryMb'] || $cpu > $host['freeCpus'])) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => sprintf(
                'Not enough headroom on the compute host: this run needs %d MB / %s vCPU but only %d MB / %s vCPU are free.',
                $memMb, rtrim(rtrim(number_format($cpu, 2), '0'), '.'),
                (int) $host['freeMemoryMb'], rtrim(rtrim(number_format($host['freeCpus'], 2), '0'), '.')
            ));
        }

        $params = isset($context['params']) && is_array($context['params']) ? $context['params']
            : $this->decodeMap($job->params_json);

        $runId = $this->runs->createRun(array(
            'job_id' => (int) $job->id,
            'experiment_id' => $job->experiment_id ? (int) $job->experiment_id : NULL,
            'name' => $job->name,
            'environment' => $job->environment,
            'run_type' => $job->run_type,
            'trigger_source' => isset($context['trigger_source']) ? (string) $context['trigger_source'] : 'manual',
            'triggered_by' => isset($context['triggered_by']) ? (string) $context['triggered_by'] : 'system',
            'runtime_key' => $job->runtime_key,
            'image_ref' => $image,
            'driver' => $this->driver->name(),
            'params_json' => $params ? json_encode($params, JSON_UNESCAPED_SLASHES) : NULL,
            'cpu_limit' => $cpu,
            'memory_limit_mb' => $memMb,
            'jenkins_build_number' => isset($context['jenkins_build_number']) ? (int) $context['jenkins_build_number'] : NULL,
        ));
        $run = $this->runs->getRun($runId);

        // Lineage: bound input dataset versions -> this run.
        foreach ($this->decodeMap($job->dataset_bindings_json) as $role => $binding) {
            if (is_array($binding) && ! empty($binding['dataset_version_id'])) {
                $this->runs->addEdge('dataset_version', (int) $binding['dataset_version_id'], 'run', $runId, 'input:'.$role);
            }
        }

        if (! empty($plan['build_log'])) {
            $this->runs->updateRun($runId, array('log_tail' => "== image build ==\n".$plan['build_log']."\n== run ==\n"));
        }

        try {
            $entry = $job->entrypoint ?: 'main.py';
            $args = $this->splitArgs($job->application_args);
            if (! empty($plan['baked'])) {
                $command = array_merge(array('python', '-u', $entry), $args);
                $workdir = '/app';
                $binds = array();
            } else {
                $command = array_merge(array('python', '-u', '/workspace/'.$entry), $args);
                $workdir = '/workspace';
                $binds = array($this->workspace->bindDir($job->job_key).':/workspace:ro');
            }
            $handle = $this->driver->startRun(array(
                'run_key' => $run->run_key,
                'run_token' => $this->runToken,
                'job_name' => $job->job_key,
                'environment' => $job->environment,
                'image' => $image,
                'command' => $command,
                'workdir' => $workdir,
                'cpu_limit' => $cpu,
                'memory_limit_mb' => $memMb,
                'env' => $this->runEnv($job, $params),
                'binds' => $binds,
                'runtime_url' => $this->runtimeUrl,
            ));

            $this->runs->updateRun($runId, array(
                'status' => 'RUNNING',
                'container_id' => isset($handle['container_id']) ? $handle['container_id'] : NULL,
                'started_at' => date('Y-m-d H:i:s'),
                'heartbeat_at' => date('Y-m-d H:i:s'),
            ));
            return array('ok' => TRUE, 'run' => $this->runs->getRun($runId),
                'message' => $plan['message'] ?: 'Run container started.');
        } catch (Exception $e) {
            $this->driver->teardown($this->handle($this->runs->getRun($runId)));
            $this->finish($runId, 'FAILED', NULL, '', 'Could not start the run: '.$e->getMessage());
            return array('ok' => FALSE, 'run' => $this->runs->getRun($runId),
                'message' => 'Run failed to start: '.$e->getMessage());
        }
    }

    // --- advance ------------------------------------------------------

    public function advance($run)
    {
        if (! $run || in_array($run->status, self::TERMINAL, TRUE)) {
            return $run;
        }
        $handle = $this->handle($run);
        $this->runs->updateRun($run->id, array('heartbeat_at' => date('Y-m-d H:i:s')));

        $timeout = $this->timeoutSeconds($run);
        $startedTs = $run->started_at ? strtotime($run->started_at.' UTC') : strtotime($run->queued_at.' UTC');
        if ($startedTs && time() - $startedTs > $timeout) {
            $this->finish($run->id, 'TIMED_OUT', NULL, $this->driver->fetchLogs($handle),
                'Run exceeded '.$timeout.'s and was stopped.');
            $this->driver->teardown($handle);
            return $this->runs->getRun($run->id);
        }

        $poll = $this->driver->pollRun($handle);
        if ($poll['phase'] === MlComputeDriver::PHASE_RUNNING || $poll['phase'] === MlComputeDriver::PHASE_PROVISIONING) {
            $this->runs->updateRun($run->id, array('status' => 'RUNNING'));
            return $this->runs->getRun($run->id);
        }
        if ($poll['phase'] === MlComputeDriver::PHASE_SUCCEEDED || $poll['phase'] === MlComputeDriver::PHASE_FAILED) {
            $status = $poll['phase'] === MlComputeDriver::PHASE_SUCCEEDED ? 'SUCCEEDED' : 'FAILED';
            $this->finish($run->id, $status, $poll['exit_code'], $this->driver->fetchLogs($handle),
                $status === 'FAILED' ? (string) $poll['detail'] : '');
            $this->driver->teardown($handle);
            return $this->runs->getRun($run->id);
        }
        // UNKNOWN: give the engine a grace period before declaring the run lost.
        if ($startedTs && time() - $startedTs > 120) {
            $this->finish($run->id, 'FAILED', NULL, $this->driver->fetchLogs($handle),
                'Lost track of the run container: '.$poll['detail']);
            $this->driver->teardown($handle);
        }
        return $this->runs->getRun($run->id);
    }

    public function cancel($run, $actor)
    {
        if (! $run || in_array($run->status, self::TERMINAL, TRUE)) {
            return $run;
        }
        $handle = $this->handle($run);
        $logs = $this->driver->fetchLogs($handle);
        $this->finish($run->id, 'CANCELLED', NULL, $logs, 'Cancelled by '.$actor.'.');
        $this->driver->teardown($handle);
        return $this->runs->getRun($run->id);
    }

    public function liveLogs($run)
    {
        if (! $run) {
            return '';
        }
        if (in_array($run->status, self::TERMINAL, TRUE)) {
            return (string) $run->log_tail;
        }
        return $this->driver->fetchLogs($this->handle($run));
    }

    public function runStats($run)
    {
        if (! $run || in_array($run->status, self::TERMINAL, TRUE)) {
            return array('engine_healthy' => TRUE, 'phase' => $run ? (string) $run->status : 'UNKNOWN',
                'terminal' => TRUE, 'container' => new stdClass());
        }
        return array(
            'engine_healthy' => $this->driver->healthy(),
            'phase' => (string) $run->status,
            'terminal' => FALSE,
            'container' => $this->driver->stats($this->handle($run)),
        );
    }

    public function reapStaleRuns()
    {
        foreach ($this->runs->staleActiveRuns(600) as $run) {
            $this->driver->teardown($this->handle($run));
            $this->finish($run->id, 'FAILED', NULL, (string) $run->log_tail,
                'No status poll for over 10 minutes; the run was reaped.');
        }
    }

    // --- internals ------------------------------------------------------

    private function timeoutSeconds($run)
    {
        if ((string) $run->trigger_source === 'preview') {
            return max(60, min(900, (int) (getenv('JOBSEEKER_ML_PREVIEW_TIMEOUT_SECONDS') ?: 300)));
        }
        $job = $run->job_id ? $this->catalog->getJob((int) $run->job_id) : NULL;
        $configured = $job ? (int) $job->timeout_seconds : 0;
        return max(120, min(43200, $configured ?: (int) (getenv('JOBSEEKER_ML_RUN_TIMEOUT_SECONDS') ?: 3600)));
    }

    private function runtimeImageRef($runtime)
    {
        $repo = trim((string) $runtime->image_repository);
        $tag = trim((string) $runtime->image_tag);
        return $tag === '' ? $repo : $repo.':'.$tag;
    }

    private function jobImageTag($job)
    {
        $key = preg_replace('/[^a-z0-9._-]+/', '-', strtolower((string) $job->job_key));
        return 'jobseeker/ml-job/'.trim($key, '-').':'.max(1, (int) $job->version);
    }

    /**
     * Ensure the workspace is on disk and return the image to run:
     *   - the per-job baked image when built and current (no bind mount);
     *   - otherwise build it now; on build failure fall back to the runtime
     *     image with a bind mount so a Test run still works.
     *
     * @return array{ok:bool, ref:string, baked:bool, build_log:string, message:string}
     */
    private function prepareImage($job, $runtime)
    {
        $dir = $this->workspace->dir($job->job_key);
        if (! is_dir($dir) || ! is_file($dir.'/main.py')) {
            $this->workspace->sync($job, $runtime);
        }
        $hash = $this->workspace->hash($job->job_key);
        $runtimeRef = $this->runtimeImageRef($runtime);

        // Runtime base image must exist / be pullable.
        $base = $this->driver->ensureImage($runtimeRef);
        if (empty($base['ok'])) {
            return array('ok' => FALSE, 'ref' => $runtimeRef, 'baked' => FALSE, 'build_log' => '',
                'message' => $base['message']);
        }

        $tag = $this->jobImageTag($job);
        $current = $job->image_state === 'ready'
            && (string) $job->workspace_hash === (string) $hash
            && (string) $job->image_tag === $tag;
        if ($current) {
            $present = $this->driver->ensureImage($tag);
            if (! empty($present['ok'])) {
                return array('ok' => TRUE, 'ref' => $tag, 'baked' => TRUE, 'build_log' => '',
                    'message' => 'Run container started (baked image).');
            }
        }

        $build = $this->rebuildImage($job, $runtime, $tag, $hash);
        if (! empty($build['ok'])) {
            return array('ok' => TRUE, 'ref' => $tag, 'baked' => TRUE,
                'build_log' => (string) $build['log'], 'message' => 'Image built; run container started.');
        }
        return array('ok' => TRUE, 'ref' => $runtimeRef, 'baked' => FALSE,
            'build_log' => (string) $build['log']."\nImage build failed: ".$build['message']
                ."\nFalling back to the runtime image with a bind mount.",
            'message' => 'Image build failed; ran on the runtime image (see console).');
    }

    /**
     * Build the per-job image now and persist the job's image_* state.
     * @return array{ok:bool, tag:string, log:string, message:string}
     */
    public function rebuildImage($job, $runtime, $tag = NULL, $hash = NULL)
    {
        if (! is_dir($this->workspace->dir($job->job_key)) || ! is_file($this->workspace->dir($job->job_key).'/main.py')) {
            $this->workspace->sync($job, $runtime);
        }
        $tag = $tag ?: $this->jobImageTag($job);
        $hash = $hash !== NULL ? $hash : $this->workspace->hash($job->job_key);
        $this->catalog->saveJob(array('image_state' => 'building', 'updated_at' => date('Y-m-d H:i:s')), (int) $job->id);
        $result = $this->driver->buildJobImage(array(
            'tar' => $this->workspace->tar($job->job_key),
            'tag' => $tag,
            'dockerfile' => 'Dockerfile',
        ));
        $this->catalog->saveJob(array(
            'image_tag' => $tag,
            'image_state' => ! empty($result['ok']) ? 'ready' : 'failed',
            'image_digest' => ! empty($result['image_id']) ? substr((string) $result['image_id'], 0, 120) : NULL,
            'image_built_at' => ! empty($result['ok']) ? date('Y-m-d H:i:s') : NULL,
            'image_build_log' => substr((string) (isset($result['log']) ? $result['log'] : '')
                .(empty($result['ok']) ? "\n".$result['message'] : ''), -20000),
            'workspace_hash' => $hash,
            'updated_at' => date('Y-m-d H:i:s'),
        ), (int) $job->id);
        return array('ok' => ! empty($result['ok']), 'tag' => $tag,
            'log' => (string) (isset($result['log']) ? $result['log'] : ''),
            'message' => (string) $result['message']);
    }

    public function workspace()
    {
        return $this->workspace;
    }

    private function runEnv($job, $params)
    {
        $env = $this->decodeMap($job->env_json);
        $env['JOBSEEKER_ML_JOB_KEY'] = (string) $job->job_key;
        $env['JOBSEEKER_ML_RUN_TYPE'] = (string) $job->run_type;
        if ($params) {
            $env['JOBSEEKER_ML_PARAMS'] = json_encode($params, JSON_UNESCAPED_SLASHES);
        }
        $bindings = $this->decodeMap($job->dataset_bindings_json);
        if ($bindings) {
            $env['JOBSEEKER_ML_DATASETS'] = json_encode($bindings, JSON_UNESCAPED_SLASHES);
        }
        return $env;
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
        foreach ($matches as $m) {
            $args[] = ($m[1] !== '' || $m[0] === '""') ? $m[1] : $m[2];
        }
        return $args;
    }

    private function handle($run)
    {
        return array(
            'run_key' => $run ? (string) $run->run_key : '',
            'container_id' => $run ? (string) $run->container_id : '',
        );
    }

    private function finish($runId, $status, $exitCode, $logs, $error)
    {
        $run = $this->runs->getRun($runId);
        $latest = $this->runs->latestMetrics($runId);
        $this->runs->updateRun($runId, array(
            'status' => $status,
            'exit_code' => $exitCode === NULL ? NULL : (int) $exitCode,
            'log_tail' => $this->clampLogs($logs),
            'error_message' => $error === '' ? NULL : substr((string) $error, 0, 2000),
            'metrics_summary_json' => $latest ? json_encode($latest, JSON_UNESCAPED_SLASHES) : NULL,
            'completed_at' => date('Y-m-d H:i:s'),
        ));
        if ($run && $run->experiment_id) {
            // keep experiments fresh for the "recent activity" widgets
            $this->catalog->saveExperiment(array('updated_at' => date('Y-m-d H:i:s')), (int) $run->experiment_id);
        }
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
