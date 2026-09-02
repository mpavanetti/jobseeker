<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/MlEngineClient.php';

/**
 * Provider-neutral compute contract for ML runs. An ML run is a single
 * ephemeral container executing `python <entry>` on a runtime image, with the
 * jobseeker_ml SDK mounted read-only so the job streams metrics / artifacts /
 * model versions back to the app while it runs.
 *
 * `DockerMlComputeDriver` targets the in-stack Docker-in-Docker engine and is
 * the local / small-deployment backend. `KubernetesMlComputeDriver` is the seam
 * for the production path (one K8s Job per run) and keeps the same method set;
 * it fails loudly until its body is implemented.
 *
 * A "handle" is a plain array the orchestrator persists between polls
 * (container id, run key). Teardown must be idempotent.
 *
 * @method string name()
 * @method bool   healthy()
 * @method array  capacitySnapshot()
 * @method array  ensureImage(string $ref)
 * @method array  startRun(array $spec)      returns handle
 * @method array  pollRun(array $handle)     {phase, exit_code, detail}
 * @method string fetchLogs(array $handle, int $tailLines = 600)
 * @method array  stats(array $handle)       {cpu_pct, memory_mb, memory_limit_mb}
 * @method void   teardown(array $handle)
 */
abstract class MlComputeDriver
{
    const LABEL_MANAGED = 'com.jobseeker.ml.managed';
    const LABEL_RUN     = 'com.jobseeker.ml.run';
    const LABEL_JOB     = 'com.jobseeker.ml.job';
    const LABEL_ENV     = 'com.jobseeker.ml.environment';

    const PHASE_PROVISIONING = 'PROVISIONING';
    const PHASE_RUNNING      = 'RUNNING';
    const PHASE_SUCCEEDED    = 'SUCCEEDED';
    const PHASE_FAILED       = 'FAILED';
    const PHASE_UNKNOWN      = 'UNKNOWN';

    abstract public function name();
    abstract public function healthy();
    abstract public function capacitySnapshot();
    abstract public function ensureImage($imageReference);

    /**
     * Build a per-job image from a workspace tar.
     * @param array $spec tar (string), tag (string), dockerfile (string)
     * @return array{ok:bool, image_id:string, log:string, message:string}
     */
    abstract public function buildJobImage(array $spec);

    abstract public function startRun(array $spec);
    abstract public function pollRun(array $handle);
    abstract public function fetchLogs(array $handle, $tailLines = 600);
    abstract public function stats(array $handle);
    abstract public function teardown(array $handle);
}

/**
 * Factory. Selects the driver from JOBSEEKER_ML_COMPUTE_DRIVER (docker|kubernetes),
 * defaulting to docker.
 */
class MlComputeDriverFactory
{
    public static function make($config = array())
    {
        $name = strtolower(trim((string) (isset($config['driver']) ? $config['driver']
            : (getenv('JOBSEEKER_ML_COMPUTE_DRIVER') ?: 'docker'))));
        if ($name === 'kubernetes' || $name === 'k8s') {
            return new KubernetesMlComputeDriver($config);
        }
        return new DockerMlComputeDriver($config);
    }
}

class DockerMlComputeDriver extends MlComputeDriver
{
    /** @var MlEngineClient */
    private $engine;

    /** @var string network the run container joins so the SDK can reach the app */
    private $network;

    /** @var int MB kept free on the host so the box never fully commits */
    private $headroomMb;

    public function __construct($config = array())
    {
        $this->engine = isset($config['engine']) && $config['engine'] instanceof MlEngineClient
            ? $config['engine'] : new MlEngineClient($config);
        $this->network = trim((string) (getenv('JOBSEEKER_ML_RUN_NETWORK') ?: ''));
        $this->headroomMb = max(256, (int) (getenv('JOBSEEKER_ML_HOST_HEADROOM_MB') ?: 1024));
    }

    public function name()
    {
        return 'docker';
    }

    public function healthy()
    {
        return $this->engine->ping();
    }

    public function capacitySnapshot()
    {
        $info = $this->engine->info();
        if (empty($info) || ! isset($info['NCPU'], $info['MemTotal'])) {
            return array('available' => FALSE, 'cpus' => 0.0, 'memoryMb' => 0,
                'usedCpus' => 0.0, 'usedMemoryMb' => 0, 'freeCpus' => 0.0, 'freeMemoryMb' => 0);
        }
        $cpus = (float) $info['NCPU'];
        $memMb = (int) round($info['MemTotal'] / 1048576);

        $usedCpus = 0.0;
        $usedMem = 0;
        $list = $this->engine->request('GET', '/containers/json?filters='.rawurlencode(json_encode(array(
            'label' => array(self::LABEL_MANAGED.'=1'),
        ))));
        if (is_array($list['json'])) {
            foreach ($list['json'] as $container) {
                $detail = $this->engine->request('GET', '/containers/'.rawurlencode($container['Id']).'/json');
                if (! is_array($detail['json'])) {
                    continue;
                }
                $hostConfig = isset($detail['json']['HostConfig']) ? $detail['json']['HostConfig'] : array();
                $usedCpus += isset($hostConfig['NanoCpus']) && $hostConfig['NanoCpus'] > 0
                    ? $hostConfig['NanoCpus'] / 1e9 : 0.0;
                $usedMem += isset($hostConfig['Memory']) && $hostConfig['Memory'] > 0
                    ? (int) round($hostConfig['Memory'] / 1048576) : 0;
            }
        }
        $freeCpus = max(0.0, $cpus - $usedCpus - 0.5);
        $freeMem = max(0, $memMb - $usedMem - $this->headroomMb);
        return array(
            'available' => TRUE,
            'cpus' => $cpus, 'memoryMb' => $memMb,
            'usedCpus' => round($usedCpus, 2), 'usedMemoryMb' => $usedMem,
            'freeCpus' => round($freeCpus, 2), 'freeMemoryMb' => $freeMem,
            'headroomMb' => $this->headroomMb,
        );
    }

    public function ensureImage($imageReference)
    {
        $imageReference = trim((string) $imageReference);
        if ($imageReference === '') {
            return array('ok' => FALSE, 'message' => 'No image reference.');
        }
        $inspect = $this->engine->request('GET', '/images/'.rawurlencode($imageReference).'/json');
        if ($inspect['status'] === 200) {
            return array('ok' => TRUE, 'message' => 'Image present.');
        }
        // jobseeker/* images are built locally by scripts/build-ml-runtimes.sh.
        if (strpos($imageReference, 'jobseeker/') === 0) {
            return array('ok' => FALSE, 'message' => 'Runtime image '.$imageReference
                .' is not built on the engine. Run scripts/build-ml-runtimes.sh.');
        }
        $pull = $this->engine->request('POST', '/images/create?fromImage='.rawurlencode($imageReference), NULL, 600);
        if ($pull['status'] >= 200 && $pull['status'] < 300) {
            return array('ok' => TRUE, 'message' => 'Image pulled.');
        }
        return array('ok' => FALSE, 'message' => 'Could not pull '.$imageReference.' ('.$pull['status'].').');
    }

    public function buildJobImage(array $spec)
    {
        if (empty($spec['tar']) || empty($spec['tag'])) {
            return array('ok' => FALSE, 'image_id' => '', 'log' => '', 'message' => 'tar and tag are required.');
        }
        return $this->engine->buildImage(
            (string) $spec['tar'],
            (string) $spec['tag'],
            isset($spec['dockerfile']) ? (string) $spec['dockerfile'] : 'Dockerfile'
        );
    }

    public function startRun(array $spec)
    {
        $runKey = (string) $spec['run_key'];
        $env = array();
        foreach ((isset($spec['env']) && is_array($spec['env']) ? $spec['env'] : array()) as $k => $v) {
            $env[] = $k.'='.$v;
        }
        // Rewrite the callback host to a numeric address so a DinD child
        // container (which does not share the compose DNS) can still reach it.
        $callback = (string) (isset($spec['runtime_url']) ? $spec['runtime_url'] : '');
        if ($callback !== '' && preg_match('#^(https?)://([a-z0-9._-]+)(:\d+)?(/.*)?$#i', $callback, $m)) {
            $ip = @gethostbyname($m[2]);
            if ($ip && $ip !== $m[2]) {
                $callback = $m[1].'://'.$ip.(isset($m[3]) ? $m[3] : '').(isset($m[4]) ? $m[4] : '');
            }
        }
        $env[] = 'JOBSEEKER_ML_API='.$callback;
        $env[] = 'JOBSEEKER_ML_RUN_KEY='.$runKey;
        $env[] = 'JOBSEEKER_ML_RUN_TOKEN='.(string) (isset($spec['run_token']) ? $spec['run_token'] : '');
        $env[] = 'JOBSEEKER_ML_ENVIRONMENT='.(string) (isset($spec['environment']) ? $spec['environment'] : '');
        $env[] = 'PYTHONUNBUFFERED=1';
        $env[] = 'MPLBACKEND=Agg';

        $nanoCpus = (int) round(max(0.25, (float) $spec['cpu_limit']) * 1e9);
        $memoryBytes = max(256, (int) $spec['memory_limit_mb']) * 1048576;

        $body = array(
            'Image' => (string) $spec['image'],
            // Clear any image ENTRYPOINT: the orchestrator always sends a full
            // argv (python -u <entry> ...), so Cmd is authoritative.
            'Entrypoint' => array(),
            'Cmd' => array_values((array) $spec['command']),
            'Env' => $env,
            'WorkingDir' => isset($spec['workdir']) ? (string) $spec['workdir'] : '/workspace',
            'Labels' => array(
                self::LABEL_MANAGED => '1',
                self::LABEL_RUN => $runKey,
                self::LABEL_JOB => (string) (isset($spec['job_name']) ? $spec['job_name'] : ''),
                self::LABEL_ENV => (string) (isset($spec['environment']) ? $spec['environment'] : ''),
            ),
            'HostConfig' => array(
                'NanoCpus' => $nanoCpus,
                'Memory' => $memoryBytes,
                'MemorySwap' => $memoryBytes,
                'AutoRemove' => FALSE,
                'Binds' => array_values((array) (isset($spec['binds']) ? $spec['binds'] : array())),
                'PidsLimit' => 512,
                'SecurityOpt' => array('no-new-privileges'),
            ),
        );
        if ($this->network !== '') {
            $body['HostConfig']['NetworkMode'] = $this->network;
        }

        $name = 'jsml-'.$runKey;
        $create = $this->engine->request('POST', '/containers/create?name='.rawurlencode($name), $body, 30);
        if ($create['status'] !== 201 || ! isset($create['json']['Id'])) {
            throw new RuntimeException('Container create failed ('.$create['status'].'): '
                .substr((string) $create['body'], 0, 400));
        }
        $containerId = (string) $create['json']['Id'];

        $start = $this->engine->request('POST', '/containers/'.rawurlencode($containerId).'/start', NULL, 30);
        if ($start['status'] !== 204 && $start['status'] !== 304) {
            $this->engine->request('DELETE', '/containers/'.rawurlencode($containerId).'?force=1&v=1');
            throw new RuntimeException('Container start failed ('.$start['status'].'): '
                .substr((string) $start['body'], 0, 400));
        }
        return array('run_key' => $runKey, 'container_id' => $containerId, 'driver' => 'docker');
    }

    public function pollRun(array $handle)
    {
        $id = (string) (isset($handle['container_id']) ? $handle['container_id'] : '');
        if ($id === '') {
            return array('phase' => self::PHASE_UNKNOWN, 'exit_code' => NULL, 'detail' => 'No container id.');
        }
        $res = $this->engine->request('GET', '/containers/'.rawurlencode($id).'/json');
        if ($res['status'] === 404) {
            return array('phase' => self::PHASE_UNKNOWN, 'exit_code' => NULL, 'detail' => 'Container gone.');
        }
        if ($res['status'] !== 200 || ! is_array($res['json'])) {
            return array('phase' => self::PHASE_UNKNOWN, 'exit_code' => NULL, 'detail' => 'Engine poll failed.');
        }
        $state = isset($res['json']['State']) ? $res['json']['State'] : array();
        $status = isset($state['Status']) ? $state['Status'] : 'unknown';
        if (in_array($status, array('created', 'restarting'), TRUE)) {
            return array('phase' => self::PHASE_PROVISIONING, 'exit_code' => NULL, 'detail' => $status);
        }
        if ($status === 'running' || $status === 'paused') {
            return array('phase' => self::PHASE_RUNNING, 'exit_code' => NULL, 'detail' => $status);
        }
        if ($status === 'exited' || $status === 'dead') {
            $code = isset($state['ExitCode']) ? (int) $state['ExitCode'] : NULL;
            return array(
                'phase' => $code === 0 ? self::PHASE_SUCCEEDED : self::PHASE_FAILED,
                'exit_code' => $code,
                'detail' => isset($state['Error']) && $state['Error'] !== '' ? $state['Error'] : ('exit '.$code),
            );
        }
        return array('phase' => self::PHASE_UNKNOWN, 'exit_code' => NULL, 'detail' => $status);
    }

    public function fetchLogs(array $handle, $tailLines = 600)
    {
        $id = (string) (isset($handle['container_id']) ? $handle['container_id'] : '');
        if ($id === '') {
            return '';
        }
        $res = $this->engine->request(
            'GET',
            '/containers/'.rawurlencode($id).'/logs?stdout=1&stderr=1&timestamps=0&tail='.max(1, min(5000, (int) $tailLines)),
            NULL, 15, TRUE
        );
        return $this->demuxDockerStream((string) $res['body']);
    }

    public function stats(array $handle)
    {
        $id = (string) (isset($handle['container_id']) ? $handle['container_id'] : '');
        if ($id === '') {
            return array('cpu_pct' => 0.0, 'memory_mb' => 0.0, 'memory_limit_mb' => 0.0);
        }
        $res = $this->engine->request('GET', '/containers/'.rawurlencode($id).'/stats?stream=0', NULL, 12);
        $s = is_array($res['json']) ? $res['json'] : array();
        $cpuPct = 0.0;
        if (isset($s['cpu_stats']['cpu_usage']['total_usage'], $s['precpu_stats']['cpu_usage']['total_usage'],
            $s['cpu_stats']['system_cpu_usage'], $s['precpu_stats']['system_cpu_usage'])) {
            $cpuDelta = $s['cpu_stats']['cpu_usage']['total_usage'] - $s['precpu_stats']['cpu_usage']['total_usage'];
            $sysDelta = $s['cpu_stats']['system_cpu_usage'] - $s['precpu_stats']['system_cpu_usage'];
            $cores = isset($s['cpu_stats']['online_cpus']) ? (int) $s['cpu_stats']['online_cpus'] : 1;
            if ($sysDelta > 0 && $cpuDelta > 0) {
                $cpuPct = ($cpuDelta / $sysDelta) * max(1, $cores) * 100.0;
            }
        }
        $memMb = isset($s['memory_stats']['usage']) ? $s['memory_stats']['usage'] / 1048576 : 0.0;
        $memLimitMb = isset($s['memory_stats']['limit']) ? $s['memory_stats']['limit'] / 1048576 : 0.0;
        return array(
            'cpu_pct' => round($cpuPct, 1),
            'memory_mb' => round($memMb, 1),
            'memory_limit_mb' => round($memLimitMb, 1),
        );
    }

    public function teardown(array $handle)
    {
        $id = (string) (isset($handle['container_id']) ? $handle['container_id'] : '');
        if ($id === '') {
            return;
        }
        $this->engine->request('DELETE', '/containers/'.rawurlencode($id).'?force=1&v=1');
    }

    /**
     * Docker multiplexes stdout/stderr with an 8-byte header per frame when no
     * TTY is allocated. Strip the frame headers so the console text is clean.
     */
    private function demuxDockerStream($raw)
    {
        if ($raw === '' || strlen($raw) < 8) {
            return $raw;
        }
        $out = '';
        $offset = 0;
        $length = strlen($raw);
        while ($offset + 8 <= $length) {
            $header = substr($raw, $offset, 8);
            $streamType = ord($header[0]);
            $frameLen = unpack('N', substr($header, 4, 4))[1];
            if ($streamType > 2 || $frameLen < 0 || $offset + 8 + $frameLen > $length) {
                // Not a framed stream (or truncated) - return remaining as-is.
                return $out.substr($raw, $offset);
            }
            $out .= substr($raw, $offset + 8, $frameLen);
            $offset += 8 + $frameLen;
        }
        return $out;
    }
}

/**
 * Kubernetes backend seam: one K8s Job per run, pod mounts the job source and
 * the SDK, requests/limits from cpu_limit / memory_limit_mb, logs via
 * GET .../pods/<pod>/log, teardown via delete with propagationPolicy=Background.
 * Not implemented yet - fails loudly so a misconfigured deployment is obvious.
 */
class KubernetesMlComputeDriver extends MlComputeDriver
{
    public function __construct($config = array()) {}

    private function notReady()
    {
        return new RuntimeException(
            'KubernetesMlComputeDriver is a stub. Set JOBSEEKER_ML_COMPUTE_DRIVER=docker '
            .'or implement the K8s Job path (see doc/jobseeker/MachineLearning/architecture.md).'
        );
    }

    public function name() { return 'kubernetes'; }
    public function healthy() { return FALSE; }
    public function capacitySnapshot()
    {
        return array('available' => FALSE, 'cpus' => 0.0, 'memoryMb' => 0,
            'usedCpus' => 0.0, 'usedMemoryMb' => 0, 'freeCpus' => 0.0, 'freeMemoryMb' => 0);
    }
    public function ensureImage($imageReference)
    {
        return array('ok' => FALSE, 'message' => 'Kubernetes driver not implemented.');
    }
    public function buildJobImage(array $spec)
    {
        // Production path: an in-cluster kaniko / BuildKit Job that builds from
        // the workspace and pushes to the cluster registry.
        return array('ok' => FALSE, 'image_id' => '', 'log' => '',
            'message' => 'KubernetesMlComputeDriver image build is a stub (kaniko Job).');
    }
    public function startRun(array $spec) { throw $this->notReady(); }
    public function pollRun(array $handle) { return array('phase' => self::PHASE_UNKNOWN, 'exit_code' => NULL, 'detail' => 'k8s stub'); }
    public function fetchLogs(array $handle, $tailLines = 600) { return ''; }
    public function stats(array $handle) { return array('cpu_pct' => 0.0, 'memory_mb' => 0.0, 'memory_limit_mb' => 0.0); }
    public function teardown(array $handle) {}
}
