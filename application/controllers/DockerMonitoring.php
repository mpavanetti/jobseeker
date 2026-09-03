<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class DockerMonitoring extends BaseController
{
    /**
     * Docker's disk-usage walk is expensive and its result changes slowly, so a
     * short shared reading keeps the panel responsive without going stale.
     */
    const STORAGE_CACHE_KEY = 'docker_monitor_storage';
    const STORAGE_CACHE_TTL = 60;

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
    }

    private function canViewMonitoring()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function jsonResponse($payload, $status = 200)
    {
        $this->output
            ->set_status_header((int) $status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload));
    }

    public function index()
    {
        if (! $this->canViewMonitoring()) {
            redirect('/dashboard');
            return;
        }

        $this->global['pageTitle'] = 'Job Seeker : Docker Monitoring';
        $this->loadViews('dockerMonitoring', $this->global, array(), NULL);
    }

    private function engineRequest($baseUrl, $path, $timeout = 3)
    {
        $response = $this->requestInternalHttp($baseUrl, 'GET', $path, '', $timeout);
        $decoded = json_decode($response['body'], TRUE);

        return array(
            'ok' => $response['status'] >= 200 && $response['status'] < 300 && is_array($decoded),
            'status' => $response['status'],
            'data' => is_array($decoded) ? $decoded : array()
        );
    }

    private function pruneEngineBuildCache($label, $baseUrl)
    {
        // `all=1` removes every unused build-cache record. Docker never removes
        // cache used by an active build through this endpoint, and this fixed
        // path cannot prune containers, images, networks, or volumes.
        $response = $this->requestInternalHttp($baseUrl, 'POST', '/build/prune?all=1', '{}', 15);
        $decoded = json_decode($response['body'], TRUE);
        $ok = $response['status'] >= 200 && $response['status'] < 300 && is_array($decoded);
        $message = '';
        if (! $ok && is_array($decoded) && isset($decoded['message'])) {
            $message = trim(strip_tags((string) $decoded['message']));
        }

        return array(
            'label' => $label,
            'ok' => $ok,
            'status' => (int) $response['status'],
            'spaceReclaimedBytes' => $ok && isset($decoded['SpaceReclaimed']) ? max(0, (int) $decoded['SpaceReclaimed']) : 0,
            'cacheRecordsDeleted' => $ok && isset($decoded['CachesDeleted']) && is_array($decoded['CachesDeleted']) ? count($decoded['CachesDeleted']) : 0,
            'message' => $ok ? '' : ($message !== '' ? $message : 'Docker build-cache cleanup was unavailable.')
        );
    }

    private function calculateContainerStats($stats)
    {
        $cpuStats = isset($stats['cpu_stats']) ? $stats['cpu_stats'] : array();
        $preCpuStats = isset($stats['precpu_stats']) ? $stats['precpu_stats'] : array();
        $cpuTotal = isset($cpuStats['cpu_usage']['total_usage']) ? (float) $cpuStats['cpu_usage']['total_usage'] : 0;
        $preCpuTotal = isset($preCpuStats['cpu_usage']['total_usage']) ? (float) $preCpuStats['cpu_usage']['total_usage'] : 0;
        $systemTotal = isset($cpuStats['system_cpu_usage']) ? (float) $cpuStats['system_cpu_usage'] : 0;
        $preSystemTotal = isset($preCpuStats['system_cpu_usage']) ? (float) $preCpuStats['system_cpu_usage'] : 0;
        $onlineCpus = isset($cpuStats['online_cpus']) ? max(1, (int) $cpuStats['online_cpus']) : 1;
        $cpuDelta = $cpuTotal - $preCpuTotal;
        $systemDelta = $systemTotal - $preSystemTotal;
        $cpuSampleAvailable = $cpuDelta > 0 && $systemDelta > 0 && $preCpuTotal > 0 && $preSystemTotal > 0;
        $cpuPercent = $cpuSampleAvailable ? ($cpuDelta / $systemDelta) * $onlineCpus * 100 : 0;

        $memory = isset($stats['memory_stats']['usage']) ? (float) $stats['memory_stats']['usage'] : 0;
        $cache = isset($stats['memory_stats']['stats']['inactive_file'])
            ? (float) $stats['memory_stats']['stats']['inactive_file']
            : (isset($stats['memory_stats']['stats']['cache']) ? (float) $stats['memory_stats']['stats']['cache'] : 0);
        $memory = max(0, $memory - $cache);
        $memoryLimit = isset($stats['memory_stats']['limit']) ? (float) $stats['memory_stats']['limit'] : 0;
        $networkRx = 0;
        $networkTx = 0;
        foreach (isset($stats['networks']) && is_array($stats['networks']) ? $stats['networks'] : array() as $network) {
            $networkRx += isset($network['rx_bytes']) ? (float) $network['rx_bytes'] : 0;
            $networkTx += isset($network['tx_bytes']) ? (float) $network['tx_bytes'] : 0;
        }

        $blockRead = 0;
        $blockWrite = 0;
        $ioEntries = isset($stats['blkio_stats']['io_service_bytes_recursive']) && is_array($stats['blkio_stats']['io_service_bytes_recursive'])
            ? $stats['blkio_stats']['io_service_bytes_recursive'] : array();
        foreach ($ioEntries as $entry) {
            $operation = strtolower(isset($entry['op']) ? (string) $entry['op'] : '');
            if ($operation === 'read') {
                $blockRead += isset($entry['value']) ? (float) $entry['value'] : 0;
            } elseif ($operation === 'write') {
                $blockWrite += isset($entry['value']) ? (float) $entry['value'] : 0;
            }
        }

        return array(
            'cpuPercent' => round($cpuPercent, 2),
            'cpuSampleAvailable' => $cpuSampleAvailable,
            'cpuTotalUsage' => (int) $cpuTotal,
            'systemCpuUsage' => (int) $systemTotal,
            'onlineCpus' => $onlineCpus,
            'cpuReadAt' => isset($stats['read']) ? (string) $stats['read'] : '',
            'memoryBytes' => (int) $memory,
            'memoryLimitBytes' => (int) $memoryLimit,
            'memoryPercent' => $memoryLimit > 0 ? round(($memory / $memoryLimit) * 100, 2) : 0,
            'networkRxBytes' => (int) $networkRx,
            'networkTxBytes' => (int) $networkTx,
            'blockReadBytes' => (int) $blockRead,
            'blockWriteBytes' => (int) $blockWrite,
            'pids' => isset($stats['pids_stats']['current']) ? (int) $stats['pids_stats']['current'] : 0
        );
    }

    private function containerPorts($container)
    {
        $ports = array();
        foreach (isset($container['Ports']) && is_array($container['Ports']) ? $container['Ports'] : array() as $port) {
            $privatePort = isset($port['PrivatePort']) ? (int) $port['PrivatePort'] : 0;
            if ($privatePort <= 0) {
                continue;
            }
            $protocol = isset($port['Type']) ? strtolower((string) $port['Type']) : 'tcp';
            if (isset($port['PublicPort'])) {
                $hostIp = isset($port['IP']) && trim((string) $port['IP']) !== '' ? (string) $port['IP'] : '0.0.0.0';
                if (strpos($hostIp, ':') !== FALSE && strpos($hostIp, '[') !== 0) {
                    $hostIp = '['.$hostIp.']';
                }
                $ports[] = $hostIp.':'.(int) $port['PublicPort'].' → '.$privatePort.'/'.$protocol;
            } else {
                $ports[] = $privatePort.'/'.$protocol;
            }
        }

        return array_values(array_unique($ports));
    }

    private function sumField($items, $path, $filter = NULL)
    {
        $total = 0;
        foreach (is_array($items) ? $items : array() as $item) {
            if (! is_array($item) || ($filter !== NULL && ! $filter($item))) {
                continue;
            }

            $value = $item;
            foreach ($path as $key) {
                if (! is_array($value) || ! isset($value[$key])) {
                    $value = 0;
                    break;
                }
                $value = $value[$key];
            }
            $total += is_numeric($value) ? (float) $value : 0;
        }

        return (int) $total;
    }

    private function engineStorageSnapshot($label, $baseUrl)
    {
        // /system/df is intentionally kept out of the five-second metrics poll.
        // On hosts with a large BuildKit cache it can take more than a second.
        $response = $this->engineRequest($baseUrl, '/system/df', 8);
        if (! $response['ok']) {
            return array(
                'label' => $label,
                'available' => FALSE,
                'message' => 'Docker storage data unavailable (HTTP '.$response['status'].').'
            );
        }

        $data = $response['data'];
        $images = isset($data['Images']) && is_array($data['Images']) ? $data['Images'] : array();
        $containers = isset($data['Containers']) && is_array($data['Containers']) ? $data['Containers'] : array();
        $volumes = isset($data['Volumes']) && is_array($data['Volumes']) ? $data['Volumes'] : array();
        $buildCache = isset($data['BuildCache']) && is_array($data['BuildCache']) ? $data['BuildCache'] : array();

        return array(
            'label' => $label,
            'available' => TRUE,
            'layersBytes' => isset($data['LayersSize']) ? (int) $data['LayersSize'] : 0,
            'imageCount' => count($images),
            'imageBytes' => $this->sumField($images, array('Size')),
            'containerCount' => count($containers),
            'containerWritableBytes' => $this->sumField($containers, array('SizeRw')),
            'volumeCount' => count($volumes),
            'volumeBytes' => $this->sumField($volumes, array('UsageData', 'Size')),
            'buildCacheCount' => count($buildCache),
            'buildCacheBytes' => $this->sumField($buildCache, array('Size')),
            // Docker's own reclaimable calculation excludes cache records shared
            // with an image, even when the cache record itself is not in use.
            'buildCacheReclaimableBytes' => $this->sumField($buildCache, array('Size'), function($entry) {
                return empty($entry['InUse']) && empty($entry['Shared']);
            })
        );
    }

    private function containerEnvironmentValue($inspect, $keys)
    {
        $wanted = array_fill_keys($keys, TRUE);
        $environment = isset($inspect['Config']['Env']) && is_array($inspect['Config']['Env']) ? $inspect['Config']['Env'] : array();
        foreach ($environment as $entry) {
            $parts = explode('=', (string) $entry, 2);
            if (count($parts) === 2 && isset($wanted[$parts[0]])) {
                return (string) $parts[1];
            }
        }

        return '';
    }

    private function engineSnapshot($label, $baseUrl, $includeStopped = TRUE)
    {
        $infoResponse = $this->engineRequest($baseUrl, '/info', 3);
        $containersResponse = $this->engineRequest($baseUrl, '/containers/json?all='.($includeStopped ? '1' : '0'), 3);
        if (! $infoResponse['ok'] || ! $containersResponse['ok']) {
            return array(
                'label' => $label,
                'available' => FALSE,
                'message' => 'Docker API unavailable (HTTP '.max($infoResponse['status'], $containersResponse['status']).').',
                'containers' => array()
            );
        }

        $info = $infoResponse['data'];
        $containerRecords = $containersResponse['data'];
        $containerLimit = 50;
        $containers = array();
        foreach (array_slice($containerRecords, 0, $containerLimit) as $container) {
            $id = isset($container['Id']) ? strtolower((string) $container['Id']) : '';
            if (! preg_match('/^[a-f0-9]{12,64}$/', $id)) {
                continue;
            }

            $state = isset($container['State']) ? strtolower((string) $container['State']) : 'unknown';
            $inspectResponse = $this->engineRequest($baseUrl, '/containers/'.$id.'/json', 3);
            $inspect = $inspectResponse['ok'] ? $inspectResponse['data'] : array();
            $usage = array(
                'metricsAvailable' => FALSE,
                'cpuPercent' => 0,
                'cpuSampleAvailable' => FALSE,
                'cpuTotalUsage' => 0,
                'systemCpuUsage' => 0,
                'onlineCpus' => 0,
                'cpuReadAt' => '',
                'memoryBytes' => 0,
                'memoryLimitBytes' => 0,
                'memoryPercent' => 0,
                'networkRxBytes' => 0,
                'networkTxBytes' => 0,
                'blockReadBytes' => 0,
                'blockWriteBytes' => 0,
                'pids' => 0
            );
            if ($state === 'running') {
                $statsResponse = $this->engineRequest($baseUrl, '/containers/'.$id.'/stats?stream=false&one-shot=true', 3);
                if ($statsResponse['ok']) {
                    $usage = $this->calculateContainerStats($statsResponse['data']);
                    $usage['metricsAvailable'] = TRUE;
                }
            }

            $names = isset($container['Names']) && is_array($container['Names']) ? $container['Names'] : array();
            $name = ! empty($names) ? ltrim((string) $names[0], '/') : substr($id, 0, 12);
            $labels = isset($container['Labels']) && is_array($container['Labels']) ? $container['Labels'] : array();
            $ipAddresses = array();
            $networks = isset($inspect['NetworkSettings']['Networks']) && is_array($inspect['NetworkSettings']['Networks'])
                ? $inspect['NetworkSettings']['Networks'] : array();
            foreach ($networks as $networkName => $network) {
                $ipAddress = isset($network['IPAddress']) ? trim((string) $network['IPAddress']) : '';
                if ($ipAddress !== '') {
                    $ipAddresses[] = $networkName.': '.$ipAddress;
                }
            }
            $health = isset($inspect['State']['Health']['Status']) ? strtolower((string) $inspect['State']['Health']['Status']) : '';
            $jobName = isset($labels['com.jobseeker.job.name']) ? trim((string) $labels['com.jobseeker.job.name']) : '';
            if ($jobName === '') {
                $jobName = trim($this->containerEnvironmentValue($inspect, array('JOB_NAME', 'JOBSEEKER_JOB_NAME')));
            }
            $buildNumber = isset($labels['com.jobseeker.build.number']) ? trim((string) $labels['com.jobseeker.build.number']) : '';
            if ($buildNumber === '') {
                $buildNumber = trim($this->containerEnvironmentValue($inspect, array('BUILD_NUMBER')));
            }
            $jobEnvironment = isset($labels['com.jobseeker.environment']) ? trim((string) $labels['com.jobseeker.environment']) : '';
            if ($jobEnvironment === '') {
                $jobEnvironment = trim($this->containerEnvironmentValue($inspect, array('JOBSEEKER_ENVIRONMENT', 'ENVIRONMENT')));
            }
            $jobRuntime = isset($labels['com.jobseeker.runtime']) ? trim((string) $labels['com.jobseeker.runtime']) : '';
            $isManagedJob = isset($labels['com.jobseeker.kind']) && strtolower((string) $labels['com.jobseeker.kind']) === 'job';
            $isLegacyJob = $label === 'Job runtime' && $jobName !== '' && preg_match('/^[1-9][0-9]*$/', $buildNumber);
            if ($jobRuntime === '' && ($isManagedJob || $isLegacyJob)) {
                $imageName = isset($container['Image']) ? strtolower((string) $container['Image']) : '';
                $jobRuntime = strpos($imageName, 'python') !== FALSE ? 'python' : 'container';
            }
            $nanoCpus = isset($inspect['HostConfig']['NanoCpus']) ? (float) $inspect['HostConfig']['NanoCpus'] : 0;
            $cpuQuota = isset($inspect['HostConfig']['CpuQuota']) ? (float) $inspect['HostConfig']['CpuQuota'] : 0;
            $cpuPeriod = isset($inspect['HostConfig']['CpuPeriod']) ? (float) $inspect['HostConfig']['CpuPeriod'] : 0;
            $cpuLimitCores = $nanoCpus > 0 ? $nanoCpus / 1000000000 : ($cpuQuota > 0 && $cpuPeriod > 0 ? $cpuQuota / $cpuPeriod : 0);
            $containers[] = array_merge(array(
                'id' => substr($id, 0, 12),
                'name' => $name,
                'image' => isset($container['Image']) ? (string) $container['Image'] : '',
                'state' => $state,
                'status' => isset($container['Status']) ? (string) $container['Status'] : '',
                'created' => isset($container['Created']) ? (int) $container['Created'] : 0,
                'source' => $label,
                'hostname' => isset($inspect['Config']['Hostname']) ? (string) $inspect['Config']['Hostname'] : '',
                'health' => $health,
                'exitCode' => isset($inspect['State']['ExitCode']) ? (int) $inspect['State']['ExitCode'] : 0,
                'oomKilled' => ! empty($inspect['State']['OOMKilled']),
                'stateError' => isset($inspect['State']['Error']) ? trim((string) $inspect['State']['Error']) : '',
                'restartCount' => isset($inspect['RestartCount']) ? (int) $inspect['RestartCount'] : 0,
                'restartPolicy' => isset($inspect['HostConfig']['RestartPolicy']['Name']) ? (string) $inspect['HostConfig']['RestartPolicy']['Name'] : '',
                'loggingDriver' => isset($inspect['HostConfig']['LogConfig']['Type']) ? (string) $inspect['HostConfig']['LogConfig']['Type'] : '',
                'autoRemove' => ! empty($inspect['HostConfig']['AutoRemove']),
                'configuredMemoryLimitBytes' => isset($inspect['HostConfig']['Memory']) ? (int) $inspect['HostConfig']['Memory'] : 0,
                'configuredMemoryReservationBytes' => isset($inspect['HostConfig']['MemoryReservation']) ? (int) $inspect['HostConfig']['MemoryReservation'] : 0,
                'cpuLimitCores' => round($cpuLimitCores, 2),
                'cpuShares' => isset($inspect['HostConfig']['CpuShares']) ? (int) $inspect['HostConfig']['CpuShares'] : 0,
                'networkMode' => isset($inspect['HostConfig']['NetworkMode']) ? (string) $inspect['HostConfig']['NetworkMode'] : '',
                'ipAddresses' => $ipAddresses,
                'ports' => $this->containerPorts($container),
                'composeProject' => isset($labels['com.docker.compose.project']) ? (string) $labels['com.docker.compose.project'] : '',
                'composeService' => isset($labels['com.docker.compose.service']) ? (string) $labels['com.docker.compose.service'] : '',
                'composeOneOff' => isset($labels['com.docker.compose.oneoff']) && strtolower((string) $labels['com.docker.compose.oneoff']) === 'true',
                'isJobContainer' => $isManagedJob || $isLegacyJob,
                'jobName' => $jobName,
                'buildNumber' => preg_match('/^[1-9][0-9]*$/', $buildNumber) ? $buildNumber : '',
                'jobEnvironment' => $jobEnvironment,
                'jobRuntime' => $jobRuntime,
                'command' => isset($container['Command']) ? (string) $container['Command'] : '',
                'mountCount' => isset($inspect['Mounts']) && is_array($inspect['Mounts']) ? count($inspect['Mounts']) : 0,
                'startedAt' => isset($inspect['State']['StartedAt']) ? (string) $inspect['State']['StartedAt'] : '',
                'finishedAt' => isset($inspect['State']['FinishedAt']) ? (string) $inspect['State']['FinishedAt'] : ''
            ), $usage);
        }

        usort($containers, function($left, $right) {
            if ($left['state'] === $right['state']) {
                return strcasecmp($left['name'], $right['name']);
            }
            return $left['state'] === 'running' ? -1 : 1;
        });

        return array(
            'label' => $label,
            'available' => TRUE,
            'serverVersion' => isset($info['ServerVersion']) ? (string) $info['ServerVersion'] : '',
            'engineName' => isset($info['Name']) ? (string) $info['Name'] : '',
            'engineId' => isset($info['ID']) ? (string) $info['ID'] : '',
            'operatingSystem' => isset($info['OperatingSystem']) ? (string) $info['OperatingSystem'] : '',
            'architecture' => isset($info['Architecture']) ? (string) $info['Architecture'] : '',
            'kernelVersion' => isset($info['KernelVersion']) ? (string) $info['KernelVersion'] : '',
            'storageDriver' => isset($info['Driver']) ? (string) $info['Driver'] : '',
            'loggingDriver' => isset($info['LoggingDriver']) ? (string) $info['LoggingDriver'] : '',
            'dockerRootDir' => isset($info['DockerRootDir']) ? (string) $info['DockerRootDir'] : '',
            'cgroupVersion' => isset($info['CgroupVersion']) ? (string) $info['CgroupVersion'] : '',
            'cpus' => isset($info['NCPU']) ? (int) $info['NCPU'] : 0,
            'memoryBytes' => isset($info['MemTotal']) ? (int) $info['MemTotal'] : 0,
            'containersRunning' => isset($info['ContainersRunning']) ? (int) $info['ContainersRunning'] : 0,
            'containersStopped' => isset($info['ContainersStopped']) ? (int) $info['ContainersStopped'] : 0,
            'images' => isset($info['Images']) ? (int) $info['Images'] : 0,
            'containersReturned' => count($containers),
            'containersTotal' => count($containerRecords),
            'containersTruncated' => count($containerRecords) > $containerLimit,
            'containers' => $containers
        );
    }

    private function procSnapshot()
    {
        $cpuTotal = 0;
        $cpuIdle = 0;
        $stat = @file('/proc/stat', FILE_IGNORE_NEW_LINES);
        if (is_array($stat) && ! empty($stat) && preg_match('/^cpu\s+(.+)$/', $stat[0], $matches)) {
            $parts = array_map('floatval', preg_split('/\s+/', trim($matches[1])));
            $cpuTotal = array_sum($parts);
            $cpuIdle = (isset($parts[3]) ? $parts[3] : 0) + (isset($parts[4]) ? $parts[4] : 0);
        }

        $memoryTotal = 0;
        $memoryAvailable = 0;
        $memoryLines = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES);
        foreach (is_array($memoryLines) ? $memoryLines : array() as $line) {
            if (preg_match('/^(MemTotal|MemAvailable):\s+(\d+)\s+kB$/', $line, $matches)) {
                if ($matches[1] === 'MemTotal') {
                    $memoryTotal = (int) $matches[2] * 1024;
                } else {
                    $memoryAvailable = (int) $matches[2] * 1024;
                }
            }
        }

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : array(0, 0, 0);
        $diskPath = is_dir('/php/repository') ? '/php/repository' : FCPATH;
        $diskTotal = @disk_total_space($diskPath);
        $diskFree = @disk_free_space($diskPath);
        $uptimeRaw = @file_get_contents('/proc/uptime');
        $cpuInfo = @file_get_contents('/proc/cpuinfo');
        $cpuCores = $cpuInfo === FALSE ? 0 : preg_match_all('/^processor\s*:/m', $cpuInfo, $unusedMatches);
        $cpuModel = '';
        if ($cpuInfo !== FALSE && preg_match('/^model name\s*:\s*(.+)$/m', $cpuInfo, $cpuModelMatch)) {
            $cpuModel = trim($cpuModelMatch[1]);
        }

        return array(
            'hostname' => php_uname('n'),
            'kernel' => php_uname('s').' '.php_uname('r'),
            'architecture' => php_uname('m'),
            'cpuCores' => (int) $cpuCores,
            'cpuModel' => $cpuModel,
            'load' => array_map(function($value) { return round((float) $value, 2); }, array_slice((array) $load, 0, 3)),
            'cpuTotal' => (int) $cpuTotal,
            'cpuIdle' => (int) $cpuIdle,
            'memoryTotalBytes' => $memoryTotal,
            'memoryAvailableBytes' => $memoryAvailable,
            'memoryUsedBytes' => max(0, $memoryTotal - $memoryAvailable),
            'diskTotalBytes' => $diskTotal === FALSE ? 0 : (int) $diskTotal,
            'diskFreeBytes' => $diskFree === FALSE ? 0 : (int) $diskFree,
            'diskUsedBytes' => ($diskTotal === FALSE || $diskFree === FALSE) ? 0 : max(0, (int) $diskTotal - (int) $diskFree),
            'uptimeSeconds' => $uptimeRaw === FALSE ? 0 : (int) (float) explode(' ', trim($uptimeRaw))[0]
        );
    }

    public function snapshot()
    {
        if (! $this->canViewMonitoring()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return;
        }

        $hostUrl = trim((string) getenv('JOBSEEKER_DOCKER_MONITOR_URL')) ?: 'http://docker-monitor-proxy:8080';
        $runtimeUrl = trim((string) getenv('JOBSEEKER_DOCKER_RUNTIME_URL')) ?: 'http://docker-runtime:2375';
        $engines = array(
            $this->engineSnapshot('Application host', $hostUrl),
            $this->engineSnapshot('Job runtime', $runtimeUrl)
        );

        $this->jsonResponse(array(
            'ok' => TRUE,
            'generatedAt' => gmdate('c'),
            'host' => $this->procSnapshot(),
            'engines' => $engines
        ));
    }

    public function storage()
    {
        if (! $this->canViewMonitoring()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return;
        }

        // Docker computes disk usage by walking every image layer, volume and
        // build-cache record, which takes seconds on a busy engine and blocks a
        // PHP worker for all of it. The number moves slowly, so serve a recent
        // reading and let a caller ask for a fresh one.
        $this->load->driver('cache', array('adapter' => 'file'));
        $fresh = in_array((string) $this->input->get('fresh'), array('1', 'true', 'yes'), TRUE);
        $payload = $fresh ? FALSE : $this->cache->get(self::STORAGE_CACHE_KEY);

        if ($payload === FALSE) {
            $hostUrl = trim((string) getenv('JOBSEEKER_DOCKER_MONITOR_URL')) ?: 'http://docker-monitor-proxy:8080';
            $runtimeUrl = trim((string) getenv('JOBSEEKER_DOCKER_RUNTIME_URL')) ?: 'http://docker-runtime:2375';
            $payload = array(
                'ok' => TRUE,
                'generatedAt' => gmdate('c'),
                'engines' => array(
                    $this->engineStorageSnapshot('Application host', $hostUrl),
                    $this->engineStorageSnapshot('Job runtime', $runtimeUrl)
                )
            );
            // Never pin a failure in place: an engine that was briefly unreachable
            // would otherwise keep reporting "unavailable" long after it recovered.
            $measured = array_filter($payload['engines'], function($engine) {
                return ! empty($engine['available']);
            });

            if (! empty($measured)) {
                $this->cache->save(self::STORAGE_CACHE_KEY, $payload, self::STORAGE_CACHE_TTL);
            }
        }

        $this->jsonResponse($payload);
    }

    public function pruneCache()
    {
        if (! $this->canViewMonitoring()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return;
        }

        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Docker build-cache cleanup requires a POST request.'), 405);
            return;
        }

        $lock = @fopen(sys_get_temp_dir().'/jobseeker-docker-build-cache-prune.lock', 'c');
        if (! $lock || ! @flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                @fclose($lock);
            }
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'A Docker build-cache cleanup is already running.'), 409);
            return;
        }

        try {
            $hostUrl = trim((string) getenv('JOBSEEKER_DOCKER_MONITOR_URL')) ?: 'http://docker-monitor-proxy:8080';
            $runtimeUrl = trim((string) getenv('JOBSEEKER_DOCKER_RUNTIME_URL')) ?: 'http://docker-runtime:2375';
            $engines = array(
                $this->pruneEngineBuildCache('Application host', $hostUrl),
                $this->pruneEngineBuildCache('Job runtime', $runtimeUrl)
            );
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }

        // The whole point of pruning is the freed space, so never report it from
        // a reading taken before the prune ran.
        $this->load->driver('cache', array('adapter' => 'file'));
        $this->cache->delete(self::STORAGE_CACHE_KEY);

        $successful = array_values(array_filter($engines, function($engine) {
            return ! empty($engine['ok']);
        }));
        $reclaimed = array_sum(array_map(function($engine) {
            return isset($engine['spaceReclaimedBytes']) ? (int) $engine['spaceReclaimedBytes'] : 0;
        }, $successful));

        $this->jsonResponse(array(
            'ok' => count($successful) > 0,
            'complete' => count($successful) === count($engines),
            'generatedAt' => gmdate('c'),
            'totalSpaceReclaimedBytes' => $reclaimed,
            'engines' => $engines,
            'message' => count($successful) > 0
                ? 'Unused Docker build cache was reclaimed.'
                : 'Docker build-cache cleanup failed on every engine.'
        ), count($successful) > 0 ? 200 : 502);
    }

    public function jobs()
    {
        $runtimeUrl = trim((string) getenv('JOBSEEKER_DOCKER_RUNTIME_URL')) ?: 'http://docker-runtime:2375';
        $engine = $this->engineSnapshot('Job runtime', $runtimeUrl, FALSE);
        if (! $engine['available']) {
            $this->jsonResponse(array(
                'ok' => FALSE,
                'generatedAt' => gmdate('c'),
                'message' => isset($engine['message']) ? $engine['message'] : 'Job runtime is unavailable.',
                'jobs' => array()
            ), 503);
            return;
        }

        $jobFilter = trim((string) $this->security->xss_clean($this->input->get('job')));
        $buildFilter = trim((string) $this->security->xss_clean($this->input->get('build')));
        if ($buildFilter !== '' && ! preg_match('/^[1-9][0-9]*$/', $buildFilter)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Invalid build number.'), 400);
            return;
        }

        $jobs = array_values(array_filter($engine['containers'], function($container) use ($jobFilter, $buildFilter) {
            if ($container['state'] !== 'running' || empty($container['isJobContainer'])) {
                return FALSE;
            }
            if ($jobFilter !== '' && $container['jobName'] !== $jobFilter) {
                return FALSE;
            }
            return $buildFilter === '' || $container['buildNumber'] === $buildFilter;
        }));

        $this->jsonResponse(array(
            'ok' => TRUE,
            'generatedAt' => gmdate('c'),
            'engine' => array(
                'label' => $engine['label'],
                'serverVersion' => $engine['serverVersion'],
                'engineName' => $engine['engineName']
            ),
            'count' => count($jobs),
            'jobs' => $jobs
        ));
    }
}
