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

    private function maxPersistentClusters()
    {
        return max(1, min(10, (int) (getenv('JOBSEEKER_SPARK_PERSISTENT_MAX') ?: 2)));
    }

    private function persistentIdleMinutes()
    {
        return max(5, min(10080, (int) (getenv('JOBSEEKER_SPARK_PERSISTENT_IDLE_MINUTES') ?: 120)));
    }

    /**
     * Deterministic hex key for a persistent cluster's containers / network, so
     * the existing js-<key>-* naming, label sweep and teardown all apply.
     */
    private function persistKeyForId($clusterId)
    {
        return substr(hash('sha256', 'jobseeker-spark-persist-'.(int) $clusterId), 0, 16);
    }

    /** {8888:jupyter, 8080:spark-ui, 7077:master} host ports for a persistent cluster. */
    private function persistentPorts($cluster)
    {
        $base = max(1024, min(60000, (int) (getenv('JOBSEEKER_SPARK_PERSISTENT_PORT_BASE') ?: 18000)));
        $n = ((int) $cluster->id) % 100;
        return array(8888 => $base + $n, 8080 => $base + 100 + $n, 7077 => $base + 200 + $n);
    }

    /** Notebook (JupyterLab) image: explicit override, else the runtime image tag + "-notebook". */
    private function notebookImage($runtime)
    {
        $explicit = trim((string) getenv('JOBSEEKER_SPARK_NOTEBOOK_IMAGE'));
        if ($explicit !== '') {
            return $explicit;
        }
        $base = $this->imageReference($runtime);
        if (preg_match('/-notebook$/', $base)) {
            return $base;
        }
        return strpos($base, ':') === FALSE ? $base.':notebook' : $base.'-notebook';
    }

    private function handleFromInstance($instance)
    {
        return array(
            'run_key' => $this->persistKeyForId((int) $instance->cluster_id),
            'network' => (string) $instance->network,
            'image' => '',
            'master_id' => (string) $instance->master_container_id,
            'worker_ids' => json_decode((string) $instance->worker_container_ids_json, TRUE) ?: array(),
            'jupyter_id' => (string) $instance->jupyter_container_id,
            'driver_id' => '',
            'bind_source' => $this->bindSource,
        );
    }

    /**
     * @return array{ok:bool, run:object|null, message:string}
     */
    public function start($job, $cluster, $runtime, $triggeredBy, $workerOverride = NULL)
    {
        if (! $job || ! $cluster || ! $runtime) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'Job, cluster and runtime are all required.');
        }
        $this->reapStaleRuns();
        $this->reapIdlePersistentClusters();
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

        // A job whose cluster is persistent and currently running submits straight
        // to it - no per-run provisioning, no teardown of the shared cluster.
        $persistentInstance = NULL;
        if ((string) $cluster->lifecycle === 'persistent') {
            $candidate = $this->model->getClusterInstance($cluster->id);
            if ($candidate && $candidate->status === 'RUNNING' && trim((string) $candidate->network) !== '') {
                $persistentInstance = $candidate;
            }
        }
        if ($persistentInstance) {
            return $this->startOnPersistent($job, $cluster, $persistentInstance, $image, $triggeredBy);
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
     * Submit a job run onto an already-running persistent cluster: only a driver
     * container is created (on the cluster's network), and only it is removed
     * when the run ends.
     *
     * @return array{ok:bool, run:object|null, message:string}
     */
    private function startOnPersistent($job, $cluster, $instance, $image, $triggeredBy)
    {
        $workerCount = max(0, (int) $instance->worker_count);
        $runId = $this->model->createSparkRun($job->id, $job->environment, $triggeredBy, $workerCount);
        $run = $this->model->getSparkRun($runId);
        if (! $run) {
            return array('ok' => FALSE, 'run' => NULL, 'message' => 'Could not record the run.');
        }

        try {
            $this->model->updateSparkRun($runId, array('status' => 'PROVISIONING', 'persistent_cluster_id' => (int) $cluster->id));
            $this->model->touchClusterInstance((int) $cluster->id);
            $entryPoint = $this->resolveEntryPoint($job);

            $sparkConf = $this->decodeMap($cluster->spark_conf_json);
            $env = $this->decodeMap($cluster->env_json);

            $handle = $this->handleFromInstance($instance);
            $handle['run_key'] = $run->run_key;
            $handle['image'] = $image;

            $this->model->updateSparkRun($runId, array(
                'cluster_network' => (string) $instance->network,
                'master_container_id' => (string) $instance->master_container_id,
                'worker_container_ids_json' => (string) $instance->worker_container_ids_json,
                'worker_count' => $workerCount,
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

            return array('ok' => TRUE, 'run' => $this->model->getSparkRun($runId),
                'message' => 'Submitting to persistent cluster "'.$cluster->name.'" (no provisioning).');
        } catch (Exception $exception) {
            $this->failAndTeardown($runId, $exception->getMessage());
            return array('ok' => FALSE, 'run' => $this->model->getSparkRun($runId), 'message' => 'Run failed: '.$exception->getMessage());
        }
    }

    /**
     * Start (or report the already-running) persistent cluster: network + master
     * (7077/8080 published) + workers + a JupyterLab container (8888 published).
     *
     * @return array{ok:bool, instance:object|null, message:string}
     */
    public function startPersistent($cluster, $runtime, $triggeredBy)
    {
        if (! $cluster || ! $runtime) {
            return array('ok' => FALSE, 'instance' => NULL, 'message' => 'Cluster and runtime are required.');
        }
        if ((string) $cluster->lifecycle !== 'persistent') {
            return array('ok' => FALSE, 'instance' => NULL, 'message' => 'This cluster is not a persistent cluster.');
        }

        $existing = $this->model->getClusterInstance($cluster->id);
        if ($existing && in_array($existing->status, array('PROVISIONING', 'RUNNING'), TRUE)) {
            return array('ok' => TRUE, 'instance' => $existing, 'message' => 'Persistent cluster is already running.');
        }

        $this->reapIdlePersistentClusters();
        if (count($this->model->runningPersistentInstances()) >= $this->maxPersistentClusters()) {
            return array('ok' => FALSE, 'instance' => NULL,
                'message' => 'Too many persistent clusters are running ('.$this->maxPersistentClusters().' max). Stop one first.');
        }
        if (! $this->driver->healthy()) {
            return array('ok' => FALSE, 'instance' => NULL, 'message' => 'The compute engine ('.$this->driver->name().') is not reachable.');
        }

        $image = $this->imageReference($runtime);
        foreach (array($image, $this->notebookImage($runtime)) as $ref) {
            $check = $this->driver->ensureImage($ref);
            if (empty($check['ok'])) {
                return array('ok' => FALSE, 'instance' => NULL, 'message' => $check['message']);
            }
        }

        $key = $this->persistKeyForId((int) $cluster->id);
        $ports = $this->persistentPorts($cluster);
        $token = bin2hex(random_bytes(8));
        $workerCount = max(1, min($this->maxWorkers(), max(1, (int) $cluster->min_workers)));
        $now = date('Y-m-d H:i:s');

        $this->model->upsertClusterInstance($cluster->id, array(
            'cluster_key' => (string) $cluster->cluster_key,
            'environment' => (string) $cluster->environment,
            'status' => 'PROVISIONING',
            'jupyter_token' => $token,
            'jupyter_port' => $ports[8888],
            'spark_ui_port' => $ports[8080],
            'master_port' => $ports[7077],
            'worker_count' => $workerCount,
            'triggered_by' => substr((string) $triggeredBy, 0, 200),
            'error_message' => NULL,
            'started_at' => $now,
            'last_seen_at' => $now,
            'stopped_at' => NULL,
        ));

        try {
            $handle = $this->driver->provisionPersistentCluster(array(
                'run_key' => $key,
                'job_name' => 'persistent:'.$cluster->cluster_key,
                'environment' => $cluster->environment,
                'image' => $image,
                'worker_count' => $workerCount,
                'worker_cores' => (int) $cluster->worker_cores,
                'worker_memory_mb' => (int) $cluster->worker_memory_mb,
                'spark_conf' => $this->decodeMap($cluster->spark_conf_json),
                'env' => $this->decodeMap($cluster->env_json),
                'bind_source' => $this->bindSource,
                'jupyter_image' => $this->notebookImage($runtime),
                'jupyter_token' => $token,
                'publish_ports' => $ports,
            ));

            $instance = $this->model->upsertClusterInstance($cluster->id, array(
                'status' => 'RUNNING',
                'network' => isset($handle['network']) ? $handle['network'] : ('js-'.$key),
                'master_container_id' => isset($handle['master_id']) ? $handle['master_id'] : NULL,
                'worker_container_ids_json' => json_encode(array_values($handle['worker_ids'] ?? array())),
                'jupyter_container_id' => isset($handle['jupyter_id']) ? $handle['jupyter_id'] : NULL,
                'jupyter_token' => isset($handle['jupyter_token']) ? $handle['jupyter_token'] : $token,
                'worker_count' => count($handle['worker_ids'] ?? array()),
                'last_seen_at' => date('Y-m-d H:i:s'),
            ));
            return array('ok' => TRUE, 'instance' => $instance,
                'message' => 'Persistent cluster started ('.count($handle['worker_ids'] ?? array()).' workers) with JupyterLab.');
        } catch (Exception $exception) {
            $this->driver->teardownByKey($key);
            $this->model->upsertClusterInstance($cluster->id, array(
                'status' => 'FAILED',
                'error_message' => substr($exception->getMessage(), 0, 2000),
                'stopped_at' => date('Y-m-d H:i:s'),
            ));
            return array('ok' => FALSE, 'instance' => $this->model->getClusterInstance($cluster->id),
                'message' => 'Start failed: '.$exception->getMessage());
        }
    }

    /** Tear a persistent cluster down. Idempotent. @return array{ok:bool, instance:object|null, message:string} */
    public function stopPersistent($cluster)
    {
        if (! $cluster) {
            return array('ok' => FALSE, 'instance' => NULL, 'message' => 'Cluster is required.');
        }
        $key = $this->persistKeyForId((int) $cluster->id);
        if ($this->model->getClusterInstance($cluster->id)) {
            $this->model->upsertClusterInstance($cluster->id, array('status' => 'STOPPING'));
        }
        $this->driver->teardownByKey($key);
        $instance = $this->model->upsertClusterInstance($cluster->id, array(
            'status' => 'STOPPED',
            'network' => NULL,
            'master_container_id' => NULL,
            'worker_container_ids_json' => NULL,
            'jupyter_container_id' => NULL,
            'worker_count' => 0,
            'stopped_at' => date('Y-m-d H:i:s'),
        ));
        return array('ok' => TRUE, 'instance' => $instance, 'message' => 'Persistent cluster stopped.');
    }

    /**
     * Live container stats + Spark master state for a persistent cluster.
     *
     * @return array{status:string, running:bool, containers:array, aggregate:mixed, spark:array|null, instance:object|null}
     */
    public function persistentStats($cluster)
    {
        $instance = $cluster ? $this->model->getClusterInstance($cluster->id) : NULL;
        if (! $instance || ! in_array($instance->status, array('PROVISIONING', 'RUNNING', 'STOPPING'), TRUE)) {
            return array(
                'status' => $instance ? (string) $instance->status : 'STOPPED',
                'running' => FALSE, 'containers' => array(), 'aggregate' => new stdClass(),
                'spark' => NULL, 'instance' => $instance,
            );
        }
        $this->model->touchClusterInstance($cluster->id);
        $handle = $this->handleFromInstance($instance);
        $stats = $this->driver->clusterStats($handle);
        return array(
            'status' => (string) $instance->status,
            'running' => TRUE,
            'containers' => $stats['containers'],
            'aggregate' => $stats['aggregate'],
            'spark' => $this->driver->sparkMasterState($handle),
            'instance' => $instance,
        );
    }

    /** Stop persistent clusters idle past their timeout. Cheap; runs on every start(). */
    private function reapIdlePersistentClusters()
    {
        foreach ($this->model->idlePersistentInstances($this->persistentIdleMinutes()) as $row) {
            $this->driver->teardownByKey($this->persistKeyForId((int) $row->cluster_id));
            $this->model->upsertClusterInstance((int) $row->cluster_id, array(
                'status' => 'STOPPED',
                'network' => NULL,
                'master_container_id' => NULL,
                'worker_container_ids_json' => NULL,
                'jupyter_container_id' => NULL,
                'stopped_at' => date('Y-m-d H:i:s'),
                'error_message' => 'Idle timeout reached; the persistent cluster was stopped automatically.',
            ));
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
            $this->releaseRunContainers($run, $handle);
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
            $this->releaseRunContainers($run, $handle);
            return $this->model->getSparkRun($run->id);
        }

        // UNKNOWN: give the engine a short grace period before declaring failure.
        if ($ageSeconds > 90) {
            $this->finish($run->id, 'FAILED', NULL, $this->driver->fetchSparkLogs($handle), 'Lost track of the driver container: '.$poll['detail']);
            $this->releaseRunContainers($run, $handle);
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
        $this->releaseRunContainers($run, $handle);
        return $this->model->getSparkRun($run->id);
    }

    /**
     * Free a finished run's containers. A run that executed against a persistent
     * cluster only owns its driver; a job-cluster run owns the whole cluster.
     */
    private function releaseRunContainers($run, $handle)
    {
        if ($run && $run->persistent_cluster_id !== NULL && (int) $run->persistent_cluster_id > 0) {
            $this->driver->removeSparkDriver($handle);
            $this->model->touchClusterInstance((int) $run->persistent_cluster_id);
            return;
        }
        $this->driver->teardownSpark($handle);
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
        $handle = $this->handleFromRun($run);
        $stats = $this->driver->clusterStats($handle);
        return array(
            'engineHealthy' => $this->driver->healthy(),
            'phase' => (string) $run->status,
            'terminal' => FALSE,
            'containers' => $stats['containers'],
            'aggregate' => $stats['aggregate'],
            'spark' => $this->driver->sparkMasterState($handle),
        );
    }

    /**
     * A run only advances when something polls it (the browser, or a Jenkins
     * runner script). If the poller dies - a killed build, a closed tab - the
     * row is left RUNNING forever and permanently eats a concurrency slot.
     * Sweep anything nobody has touched in 5 minutes: tear its containers down
     * (idempotent) and mark it FAILED. Cheap enough to run on every start().
     */
    private function reapStaleRuns()
    {
        foreach ($this->model->staleActiveRuns(300) as $run) {
            $this->releaseRunContainers($run, $this->handleFromRun($run));
            $this->model->updateSparkRun($run->id, array(
                'status' => 'FAILED',
                'error_message' => 'No status poll was received for over 5 minutes (the Jenkins build or browser tab likely stopped); the run was reaped.',
                'completed_at' => date('Y-m-d H:i:s'),
            ));
        }
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
            if (@file_put_contents($dir.'/job.py', (string) $job->inline_code) === FALSE) {
                throw new RuntimeException('Could not write the inline job source.');
            }
            // Keep inline jobs self-contained: `from connect import get_spark`
            // works even if the workspace was never opened in VS Code. Empty
            // DEFAULT_MASTER lets spark-submit's --master win.
            if (! is_file($dir.'/connect.py')) {
                @file_put_contents($dir.'/connect.py',
                    "\"\"\"SparkSession helper (JobSeeker). Override with SPARK_MASTER_URL.\"\"\"\n\n"
                    ."import os\n\nfrom pyspark.sql import SparkSession\n\nDEFAULT_MASTER = \"\"\n\n\n"
                    ."def get_spark(app_name: str = \"jobseeker-dev\") -> SparkSession:\n"
                    ."    master = os.environ.get(\"SPARK_MASTER_URL\", \"\").strip() or DEFAULT_MASTER\n"
                    ."    builder = SparkSession.builder.appName(app_name)\n"
                    ."    if master:\n        builder = builder.master(master)\n"
                    ."    return builder.getOrCreate()\n");
            }
            return 'inline/'.$key.'/job.py';
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
            $this->releaseRunContainers($run, $this->handleFromRun($run));
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
