<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/ComputeEngineClient.php';

/**
 * Compute driver contract for the JobSeeker Data Engineering plane.
 *
 * A driver turns a provider-neutral spec (produced by SparkClusterOrchestrator /
 * MlJobOrchestrator from the `spark_*` / `ml_*` tables) into real infrastructure
 * and reports back on it. `DockerComputeDriver` below targets the in-stack
 * Docker-in-Docker engine today; `KubernetesComputeDriver` is the seam for the
 * planned move to Kubernetes and keeps the same method set.
 *
 * A "handle" is a plain associative array the orchestrator persists between HTTP
 * polls (network name/id, container ids). Drivers must treat every teardown as
 * idempotent - the orchestrator calls it on the happy path, on failure, and
 * from a reaper.
 */
abstract class ComputeDriver
{
    const LABEL_KIND        = 'com.jobseeker.kind';
    const LABEL_TYPE        = 'com.jobseeker.compute.type';
    const LABEL_RUN         = 'com.jobseeker.compute.run';
    const LABEL_ROLE        = 'com.jobseeker.compute.role';
    const LABEL_JOB         = 'com.jobseeker.job.name';
    const LABEL_ENVIRONMENT = 'com.jobseeker.environment';
    const LABEL_MANAGED     = 'com.jobseeker.managed';

    /** @return string short driver id, e.g. "docker" */
    abstract public function name();

    /** @return bool whether the backing engine is reachable */
    abstract public function healthy();

    /** @return bool TRUE when the runtime image is already present on the engine */
    abstract public function imageAvailable($imageReference);

    /**
     * Pull a public base image on demand. jobseeker/* images are never pulled -
     * they must be built with scripts/build-compute-runtimes.sh.
     *
     * @return array{ok:bool, message:string}
     */
    abstract public function ensureImage($imageReference);

    // --- Spark job clusters -------------------------------------------------

    /**
     * Create the per-run network and start the master + workers.
     *
     * @param array $spec run_key, job_name, environment, image, worker_count,
     *                    worker_cores, worker_memory_mb, spark_conf, env, bind_source
     * @return array handle
     * @throws RuntimeException on any provisioning failure (nothing left running)
     */
    abstract public function provisionSparkCluster(array $spec);

    /**
     * Start the driver container that runs spark-submit against the cluster.
     *
     * @param array $spec entry_point, application_args (array), driver_cores,
     *                    driver_memory_mb, spark_conf, env, bind_source
     * @return array handle merged with driver_id
     * @throws RuntimeException
     */
    abstract public function submitSparkJob(array $handle, array $spec);

    /**
     * @return array{phase:string, exit_code:int|null, running:int, detail:string}
     *               phase is one of PROVISIONING|RUNNING|SUCCEEDED|FAILED|UNKNOWN
     */
    abstract public function pollSparkRun(array $handle);

    /** @return string plain-text log tail (driver container, falling back to master) */
    abstract public function fetchSparkLogs(array $handle, $tailLines = 400);

    /** Remove driver, workers, master and the per-run network. Idempotent. */
    abstract public function teardownSpark(array $handle);

    // --- Machine Learning single-container jobs ---------------------------

    /**
     * @param array $spec run_key, job_name, environment, image, entry_point,
     *                    application_args (array), cpu_limit, memory_limit_mb,
     *                    env, bind_source
     * @return array handle with container_id
     * @throws RuntimeException
     */
    abstract public function runMlJob(array $spec);

    /** @return array{phase:string, exit_code:int|null, running:int, detail:string} */
    abstract public function pollMlRun(array $handle);

    abstract public function fetchMlLogs(array $handle, $tailLines = 400);

    /** Remove the job container. Idempotent. */
    abstract public function teardownMl(array $handle);

    // --- shared helpers --------------------------------------------------

    protected function baseLabels($type, $spec)
    {
        return array(
            self::LABEL_KIND        => 'compute',
            self::LABEL_TYPE        => $type,
            self::LABEL_MANAGED     => 'true',
            self::LABEL_RUN         => (string) $spec['run_key'],
            self::LABEL_JOB         => isset($spec['job_name']) ? (string) $spec['job_name'] : '',
            self::LABEL_ENVIRONMENT => isset($spec['environment']) ? (string) $spec['environment'] : '',
        );
    }

    protected function assertEntryPoint($entryPoint)
    {
        $entryPoint = ltrim(trim((string) $entryPoint), '/');
        if ($entryPoint === ''
            || strpos($entryPoint, '..') !== FALSE
            || ! preg_match('#^[A-Za-z0-9._/-]+\.py$#', $entryPoint)) {
            throw new RuntimeException('Invalid job entry point: '.$entryPoint);
        }
        return $entryPoint;
    }

    protected function assertImage($imageReference)
    {
        $imageReference = trim((string) $imageReference);
        if ($imageReference === '' || ! preg_match('#^[a-z0-9]([a-z0-9._/-]*[a-z0-9])?(:[A-Za-z0-9._-]+)?$#', $imageReference)) {
            throw new RuntimeException('Invalid runtime image reference: '.$imageReference);
        }
        return $imageReference;
    }

    /** @return array<int,string> env entries as KEY=VALUE, junk keys dropped */
    protected function envList($env)
    {
        $out = array();
        foreach ((is_array($env) ? $env : array()) as $key => $value) {
            $key = trim((string) $key);
            if ($key === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }
            $out[] = $key.'='.str_replace(array("\r", "\n"), '', (string) $value);
        }
        return $out;
    }

    /** @return array<int,string> spark-submit --conf flags from a k=>v map */
    protected function sparkConfFlags($conf)
    {
        $flags = array();
        foreach ((is_array($conf) ? $conf : array()) as $key => $value) {
            $key = trim((string) $key);
            if ($key === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $key)) {
                continue;
            }
            $flags[] = '--conf';
            $flags[] = $key.'='.str_replace(array("\r", "\n"), ' ', (string) $value);
        }
        return $flags;
    }

    /** @return array<int,string> sanitised positional application arguments */
    protected function argList($args)
    {
        $out = array();
        foreach ((is_array($args) ? $args : array()) as $arg) {
            $arg = str_replace(array("\r", "\n", "\0"), '', (string) $arg);
            if ($arg !== '') {
                $out[] = $arg;
            }
        }
        return $out;
    }
}

/**
 * Resolve the configured compute driver. Selection is JOBSEEKER_COMPUTE_DRIVER
 * (default "docker"); an unknown value falls back to docker so a typo never
 * takes the feature down.
 */
class ComputeDriverFactory
{
    public static function make($config = array())
    {
        $requested = strtolower(trim((string) getenv('JOBSEEKER_COMPUTE_DRIVER')));
        if ($requested === 'kubernetes' || $requested === 'k8s') {
            require_once APPPATH.'libraries/KubernetesComputeDriver.php';
            return new KubernetesComputeDriver($config);
        }
        return new DockerComputeDriver($config);
    }
}

/**
 * Docker-in-Docker implementation. Every container and network it creates
 * carries the com.jobseeker.compute.* labels so the Docker Monitor screen and
 * the run reaper can find them.
 */
class DockerComputeDriver extends ComputeDriver
{
    /** @var ComputeEngineClient */
    private $engine;

    public function __construct($config = array())
    {
        $this->engine = isset($config['engine']) && $config['engine'] instanceof ComputeEngineClient
            ? $config['engine']
            : new ComputeEngineClient($config);
    }

    public function name()
    {
        return 'docker';
    }

    public function healthy()
    {
        return $this->engine->ping();
    }

    public function imageAvailable($imageReference)
    {
        return $this->engine->imageExists($this->assertImage($imageReference));
    }

    public function ensureImage($imageReference)
    {
        $imageReference = $this->assertImage($imageReference);
        if ($this->engine->imageExists($imageReference)) {
            return array('ok' => TRUE, 'message' => 'image present');
        }
        if (strpos($imageReference, 'jobseeker/') === 0) {
            return array('ok' => FALSE, 'message' => 'Runtime image "'.$imageReference.'" is not built on the engine. Run scripts/build-compute-runtimes.sh (or: docker compose --profile runtimes up --build).');
        }
        $parts = explode(':', $imageReference, 2);
        return $this->engine->pullImage($parts[0], isset($parts[1]) ? $parts[1] : 'latest');
    }

    public function provisionSparkCluster(array $spec)
    {
        $runKey = $this->assertRunKey($spec['run_key']);
        $image = $this->assertImage($spec['image']);
        $workerCount = max(1, min(32, (int) $spec['worker_count']));
        $workerCores = max(1, min(32, (int) $spec['worker_cores']));
        $workerMemMb = max(512, min(131072, (int) $spec['worker_memory_mb']));
        $labels = $this->baseLabels('spark', $spec);

        $networkName = 'js-'.$runKey;
        $networkId = $this->engine->createNetwork($networkName, $labels + array(self::LABEL_ROLE => 'network'));
        if ($networkId === '') {
            throw new RuntimeException('Spark cluster network failed: '.($this->engine->lastError() ?: 'unknown error'));
        }

        $handle = array(
            'run_key' => $runKey,
            'network' => $networkName,
            'network_id' => $networkId,
            'image' => $image,
            'master_id' => '',
            'worker_ids' => array(),
            'driver_id' => '',
            'bind_source' => $this->assertBindSource($spec, 'spark'),
        );

        try {
            $masterCmd = array(
                '/opt/spark/bin/spark-class', 'org.apache.spark.deploy.master.Master',
                '--host', 'master', '--port', '7077', '--webui-port', '8080',
            );
            $handle['master_id'] = $this->createComputeContainer(
                'js-'.$runKey.'-master', $image, $masterCmd,
                $this->envList($spec['env'] ?? array()),
                $labels + array(self::LABEL_ROLE => 'master'),
                $networkName, 'master', 1.0, 1024, NULL
            );

            for ($i = 1; $i <= $workerCount; $i++) {
                $workerCmd = array(
                    '/opt/spark/bin/spark-class', 'org.apache.spark.deploy.worker.Worker',
                    'spark://master:7077',
                    '--cores', (string) $workerCores,
                    '--memory', $workerMemMb.'m',
                    '--webui-port', '8081',
                );
                $handle['worker_ids'][] = $this->createComputeContainer(
                    'js-'.$runKey.'-worker-'.$i, $image, $workerCmd,
                    $this->envList($spec['env'] ?? array()),
                    $labels + array(self::LABEL_ROLE => 'worker'),
                    $networkName, 'worker-'.$i, $workerCores, $workerMemMb, NULL
                );
            }
        } catch (RuntimeException $exception) {
            $this->teardownSpark($handle);
            throw $exception;
        }

        return $handle;
    }

    public function submitSparkJob(array $handle, array $spec)
    {
        $runKey = $this->assertRunKey($handle['run_key']);
        $entryPoint = $this->assertEntryPoint($spec['entry_point']);
        $image = $this->assertImage($handle['image']);
        $driverCores = max(1, min(16, (int) ($spec['driver_cores'] ?? 1)));
        $driverMemMb = max(512, min(65536, (int) ($spec['driver_memory_mb'] ?? 1024)));
        $labels = $this->baseLabels('spark', $spec + array('run_key' => $runKey));

        $cmd = array_merge(
            array('/opt/spark/bin/spark-submit', '--master', 'spark://master:7077', '--deploy-mode', 'client'),
            $this->sparkConfFlags($spec['spark_conf'] ?? array()),
            array('/workspace/'.$entryPoint),
            $this->argList($spec['application_args'] ?? array())
        );

        $driverId = $this->createComputeContainer(
            'js-'.$runKey.'-driver', $image, $cmd,
            $this->envList($spec['env'] ?? array()),
            $labels + array(self::LABEL_ROLE => 'driver'),
            $handle['network'], 'driver', $driverCores, $driverMemMb,
            $handle['bind_source'].':/workspace:ro'
        );

        $handle['driver_id'] = $driverId;
        return $handle;
    }

    public function pollSparkRun(array $handle)
    {
        $workers = is_array($handle['worker_ids'] ?? NULL) ? $handle['worker_ids'] : array();
        $runningWorkers = 0;
        foreach ($workers as $workerId) {
            $state = $this->engine->inspectContainer($workerId);
            if ($state['running']) {
                $runningWorkers++;
            }
        }

        if (empty($handle['driver_id'])) {
            return array('phase' => 'PROVISIONING', 'exit_code' => NULL, 'running' => $runningWorkers, 'detail' => $runningWorkers.'/'.count($workers).' workers up');
        }

        $driver = $this->engine->inspectContainer($handle['driver_id']);
        if (! $driver['found']) {
            return array('phase' => 'UNKNOWN', 'exit_code' => NULL, 'running' => $runningWorkers, 'detail' => 'driver container missing');
        }
        if ($driver['running']) {
            return array('phase' => 'RUNNING', 'exit_code' => NULL, 'running' => $runningWorkers, 'detail' => 'spark-submit running');
        }

        $exit = $driver['exitCode'];
        $phase = ($exit === 0) ? 'SUCCEEDED' : 'FAILED';
        $detail = ($exit === 0) ? 'driver exited 0' : 'driver exited '.($exit === NULL ? '?' : $exit).($driver['oomKilled'] ? ' (OOM)' : '');
        return array('phase' => $phase, 'exit_code' => $exit, 'running' => $runningWorkers, 'detail' => $detail);
    }

    public function fetchSparkLogs(array $handle, $tailLines = 400)
    {
        if (! empty($handle['driver_id'])) {
            $logs = $this->engine->containerLogs($handle['driver_id'], $tailLines);
            if (trim($logs) !== '') {
                return $logs;
            }
        }
        if (! empty($handle['master_id'])) {
            return "[master]\n".$this->engine->containerLogs($handle['master_id'], $tailLines);
        }
        return '';
    }

    public function teardownSpark(array $handle)
    {
        foreach (array_merge(
            array($handle['driver_id'] ?? ''),
            is_array($handle['worker_ids'] ?? NULL) ? $handle['worker_ids'] : array(),
            array($handle['master_id'] ?? '')
        ) as $containerId) {
            if ($containerId !== '') {
                $this->engine->removeContainer($containerId, TRUE);
            }
        }
        // Label sweep catches anything the fast path missed (e.g. the master on
        // a poll-driven teardown, or an orphan from a half-failed provision).
        $this->sweepRun($handle);
        if (! empty($handle['network'])) {
            $this->engine->removeNetwork($handle['network']);
        }
    }

    public function runMlJob(array $spec)
    {
        $runKey = $this->assertRunKey($spec['run_key']);
        $image = $this->assertImage($spec['image']);
        $entryPoint = $this->assertEntryPoint($spec['entry_point']);
        $cpu = max(0.25, min(16.0, (float) ($spec['cpu_limit'] ?? 1.0)));
        $memMb = max(256, min(131072, (int) ($spec['memory_limit_mb'] ?? 2048)));
        $labels = $this->baseLabels('ml', $spec);
        $bindSource = $this->assertBindSource($spec, 'ml');

        $cmd = array_merge(
            array('python', '/workspace/'.$entryPoint),
            $this->argList($spec['application_args'] ?? array())
        );

        $containerId = $this->createComputeContainer(
            'js-'.$runKey.'-ml', $image, $cmd,
            $this->envList($spec['env'] ?? array()),
            $labels + array(self::LABEL_ROLE => 'job'),
            'none', NULL, $cpu, $memMb, $bindSource.':/workspace:ro'
        );

        return array('run_key' => $runKey, 'container_id' => $containerId, 'image' => $image);
    }

    public function pollMlRun(array $handle)
    {
        $state = $this->engine->inspectContainer($handle['container_id'] ?? '');
        if (! $state['found']) {
            return array('phase' => 'UNKNOWN', 'exit_code' => NULL, 'running' => 0, 'detail' => 'container missing');
        }
        if ($state['running']) {
            return array('phase' => 'RUNNING', 'exit_code' => NULL, 'running' => 1, 'detail' => 'job running');
        }
        $exit = $state['exitCode'];
        return array(
            'phase' => ($exit === 0) ? 'SUCCEEDED' : 'FAILED',
            'exit_code' => $exit,
            'running' => 0,
            'detail' => ($exit === 0) ? 'exited 0' : 'exited '.($exit === NULL ? '?' : $exit).($state['oomKilled'] ? ' (OOM)' : ''),
        );
    }

    public function fetchMlLogs(array $handle, $tailLines = 400)
    {
        return $this->engine->containerLogs($handle['container_id'] ?? '', $tailLines);
    }

    public function teardownMl(array $handle)
    {
        if (! empty($handle['container_id'])) {
            $this->engine->removeContainer($handle['container_id'], TRUE);
        }
        $this->sweepRun($handle);
    }

    /** Remove every container still carrying this run's label. Best effort. */
    private function sweepRun(array $handle)
    {
        $runKey = isset($handle['run_key']) ? strtolower(trim((string) $handle['run_key'])) : '';
        if (! preg_match('/^[a-f0-9]{8,32}$/', $runKey)) {
            return;
        }
        foreach ($this->engine->listByLabel(self::LABEL_RUN, $runKey) as $container) {
            if (isset($container['Id'])) {
                $this->engine->removeContainer((string) $container['Id'], TRUE);
            }
        }
    }

    // --- internals -----------------------------------------------------

    private function assertRunKey($runKey)
    {
        $runKey = strtolower(trim((string) $runKey));
        if (! preg_match('/^[a-f0-9]{8,32}$/', $runKey)) {
            throw new RuntimeException('Invalid compute run key.');
        }
        return $runKey;
    }

    private function assertBindSource(array $spec, $kind)
    {
        $source = isset($spec['bind_source']) ? trim((string) $spec['bind_source']) : '';
        $default = $kind === 'spark' ? '/jobseeker/spark' : '/jobseeker/ml';
        if ($source === '') {
            return $default;
        }
        if ($source[0] !== '/' || strpos($source, '..') !== FALSE || ! preg_match('#^/[A-Za-z0-9._/-]+$#', $source)) {
            throw new RuntimeException('Invalid bind source path.');
        }
        return $source;
    }

    /**
     * @param string      $name       unique container name
     * @param string      $image      image reference (already validated)
     * @param array       $cmd        argv (no shell)
     * @param array       $env        KEY=VALUE list
     * @param array       $labels     label map
     * @param string      $network    network name, or 'none'
     * @param string|null $alias      network alias, or null
     * @param float       $cores      cpu limit
     * @param int         $memoryMb   memory limit (MiB)
     * @param string|null $bind       "src:dst:ro" bind, or null
     * @return string container id
     * @throws RuntimeException
     */
    private function createComputeContainer($name, $image, array $cmd, array $env, array $labels, $network, $alias, $cores, $memoryMb, $bind)
    {
        $hostConfig = array(
            'NetworkMode' => $network,
            'RestartPolicy' => array('Name' => 'no'),
            'AutoRemove' => FALSE,
            'Memory' => (int) round($memoryMb * 1024 * 1024),
            'NanoCpus' => (int) round($cores * 1e9),
            'PidsLimit' => 4096,
        );
        if ($bind !== NULL) {
            $hostConfig['Binds'] = array($bind);
        }

        $spec = array(
            'Image' => $image,
            'Cmd' => array_values($cmd),
            'Env' => array_values($env),
            'Labels' => (object) $labels,
            'Tty' => TRUE,
            'HostConfig' => $hostConfig,
        );
        if ($network !== 'none' && $alias !== NULL) {
            $spec['NetworkingConfig'] = array('EndpointsConfig' => array(
                $network => array('Aliases' => array($alias)),
            ));
        }

        $id = $this->engine->createContainer($name, $spec);
        if ($id === '') {
            throw new RuntimeException('Container '.$name.' create failed: '.($this->engine->lastError() ?: 'unknown error'));
        }
        if (! $this->engine->startContainer($id)) {
            $this->engine->removeContainer($id, TRUE);
            throw new RuntimeException('Container '.$name.' failed to start: '.($this->engine->lastError() ?: 'unknown error'));
        }
        return $id;
    }
}
