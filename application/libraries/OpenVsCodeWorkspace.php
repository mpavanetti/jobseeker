<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Thin helper for opening a repository folder in the managed OpenVSCode Server
 * container. The logic mirrors the inline-Python flow in JobCreation
 * (openVsCodeRuntimeState / openVsCodeWorkspacePath / openVsCodeLaunchUrl) but is
 * standalone so the Spark / ML job screens can reuse it without pulling in that
 * 180 KB controller.
 *
 * The editor container (`jobseeker-openvscode`) runs on the application host
 * engine and is controlled through the same read-mostly socket proxy the Docker
 * Monitor screen uses (JOBSEEKER_DOCKER_MONITOR_URL).
 */
class OpenVsCodeWorkspace
{
    /** @var string */
    private $monitorUrl;

    /** @var string absolute repository root inside the PHP container */
    private $repositoryRoot;

    /** @var string workspace root inside the editor container */
    private $workspaceRoot;

    public function __construct($config = array())
    {
        $this->monitorUrl = rtrim((string) (isset($config['monitor_url']) && $config['monitor_url'] !== ''
            ? $config['monitor_url']
            : (getenv('JOBSEEKER_DOCKER_MONITOR_URL') ?: 'http://docker-monitor-proxy:8080')), '/');
        $this->repositoryRoot = rtrim(str_replace('\\', '/', (string) (isset($config['repository_root']) && $config['repository_root'] !== ''
            ? $config['repository_root']
            : (getenv('JOBSEEKER_COMPUTE_REPOSITORY_ROOT') ?: '/php/repository'))), '/');
        $this->workspaceRoot = rtrim(str_replace('\\', '/', (string) (getenv('JOBSEEKER_OPENVSCODE_WORKSPACE') ?: '/home/workspace')), '/');
    }

    public function enabled()
    {
        $value = strtolower(trim((string) getenv('JOBSEEKER_OPENVSCODE_ENABLED')));
        return $value === '' || ! in_array($value, array('0', 'false', 'no', 'off'), TRUE);
    }

    /**
     * Ensure the editor container is running, starting it if stopped.
     *
     * @return array{available:bool, running:bool, started:bool, message:string}
     */
    public function ensureRunning()
    {
        $inspect = $this->httpJson('GET', '/containers/jobseeker-openvscode/json');
        if ($inspect['status'] === 404) {
            return array('available' => FALSE, 'running' => FALSE, 'started' => FALSE,
                'message' => 'OpenVSCode is not installed in this deployment. Recreate the stack with the openvscode profile.');
        }
        if ($inspect['status'] < 200 || $inspect['status'] >= 300) {
            return array('available' => FALSE, 'running' => FALSE, 'started' => FALSE,
                'message' => 'The Docker control service is unavailable, so OpenVSCode could not be checked.');
        }

        $running = is_array($inspect['json']) && ! empty($inspect['json']['State']['Running']);
        if ($running) {
            return array('available' => TRUE, 'running' => TRUE, 'started' => FALSE, 'message' => 'OpenVSCode is ready.');
        }

        $start = $this->httpJson('POST', '/containers/jobseeker-openvscode/start');
        if (! in_array($start['status'], array(204, 304), TRUE)) {
            return array('available' => TRUE, 'running' => FALSE, 'started' => FALSE,
                'message' => 'OpenVSCode is stopped and JobSeeker could not start it.');
        }
        return array('available' => TRUE, 'running' => TRUE, 'started' => TRUE,
            'message' => 'OpenVSCode is starting; give the editor a few seconds to come up.');
    }

    /**
     * Build the editor URL that opens a folder inside the repository.
     *
     * @param string $absoluteRepoPath e.g. /php/repository/spark/inline/my-job
     * @return string editor URL, or '' if the path is outside the repository root
     */
    public function folderUrl($absoluteRepoPath)
    {
        $normalized = rtrim(str_replace('\\', '/', (string) $absoluteRepoPath), '/');
        $prefix = $this->repositoryRoot.'/';
        if (strpos($normalized.'/', $prefix) !== 0) {
            return '';
        }
        $relative = ltrim(substr($normalized, strlen($this->repositoryRoot)), '/');
        $folder = $this->workspaceRoot.'/repository/'.$relative;

        $params = array();
        $token = trim((string) getenv('JOBSEEKER_OPENVSCODE_TOKEN'));
        if ($token !== '') {
            $params['tkn'] = $token;
        }
        $params['folder'] = $folder;
        return $this->publicUrl().'/?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function publicUrl()
    {
        $configured = trim((string) getenv('JOBSEEKER_OPENVSCODE_PUBLIC_URL'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $port = trim((string) getenv('JOBSEEKER_OPENVSCODE_PORT')) ?: '3000';
        $protocol = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        if (! empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwarded = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
            if (in_array($forwarded, array('http', 'https'), TRUE)) {
                $protocol = $forwarded;
            }
        }
        $host = isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : 'localhost';
        if (! empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
        }
        $hostName = (strpos($host, '[') === 0 && strpos($host, ']') !== FALSE)
            ? substr($host, 0, strpos($host, ']') + 1)
            : explode(':', $host, 2)[0];
        if ($hostName === '') {
            $hostName = 'localhost';
        }
        $defaultPort = $protocol === 'https' ? '443' : '80';
        return $protocol.'://'.$hostName.($port === $defaultPort ? '' : ':'.$port);
    }

    private function httpJson($method, $path)
    {
        $context = stream_context_create(array('http' => array(
            'method' => strtoupper($method),
            'header' => "Accept: application/json\r\nContent-Type: application/json\r\nConnection: close",
            'content' => $method === 'POST' ? '{}' : '',
            'ignore_errors' => TRUE,
            'timeout' => 5,
        )));
        $body = @file_get_contents($this->monitorUrl.$path, FALSE, $context);
        $status = 0;
        foreach (isset($http_response_header) && is_array($http_response_header) ? $http_response_header : array() as $line) {
            if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        return array(
            'status' => $status ?: ($body === FALSE ? 502 : 200),
            'json' => $body === FALSE ? NULL : json_decode($body, TRUE),
        );
    }
}
