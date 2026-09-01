<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Minimal Docker Engine API client for the JobSeeker compute plane.
 *
 * The PHP-FPM image ships without ext-curl and CI_Loader's HTTP helpers are
 * GET/POST-only, so this talks to the engine over plain stream contexts. It is
 * deliberately small: only the verbs the Spark / ML orchestrators need
 * (networks, containers, images, logs) and no global state, so it can be unit
 * tested outside CodeIgniter.
 *
 * Target engine defaults to the in-stack Docker-in-Docker daemon
 * (`tcp://docker-runtime:2375`, exposed to PHP as JOBSEEKER_DOCKER_RUNTIME_URL),
 * the same engine the existing job runtime and Docker Monitor screen use.
 */
class ComputeEngineClient
{
    /** @var string */
    private $baseUrl;

    /** @var int */
    private $timeout;

    /** @var string */
    private $lastError = '';

    public function __construct($config = array())
    {
        $base = isset($config['base_url']) && trim((string) $config['base_url']) !== ''
            ? trim((string) $config['base_url'])
            : (trim((string) getenv('JOBSEEKER_DOCKER_RUNTIME_URL')) ?: 'http://docker-runtime:2375');
        $this->baseUrl = rtrim($base, '/');
        $this->timeout = isset($config['timeout']) ? max(2, min(30, (int) $config['timeout'])) : 10;
    }

    public function baseUrl()
    {
        return $this->baseUrl;
    }

    public function lastError()
    {
        return $this->lastError;
    }

    /**
     * Perform one Engine API call.
     *
     * @param string     $method  GET|POST|DELETE
     * @param string     $path    e.g. /containers/create
     * @param array|null $body    JSON-encoded when provided (null = no body)
     * @param int|null   $timeout override for slow calls (image pulls)
     * @return array{status:int, body:string, json:mixed}
     */
    public function request($method, $path, $body = NULL, $timeout = NULL)
    {
        $this->lastError = '';
        $method = strtoupper(trim((string) $method));
        $path = '/'.ltrim((string) $path, '/');

        if (! in_array($method, array('GET', 'POST', 'DELETE'), TRUE)
            || ! preg_match('#^https?://[a-z0-9._-]+(?::[0-9]{1,5})?$#i', $this->baseUrl)
            || strpos($path, '..') !== FALSE
            || preg_match('/[\r\n]/', $path)) {
            $this->lastError = 'Rejected malformed engine request.';
            return array('status' => 400, 'body' => '', 'json' => NULL);
        }

        $headers = "Accept: application/json\r\nConnection: close";
        $payload = '';
        if ($body !== NULL) {
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES);
            $headers .= "\r\nContent-Type: application/json\r\nContent-Length: ".strlen($payload);
        } elseif ($method === 'POST') {
            // Docker expects a body on POST routes that take no parameters.
            $payload = '';
            $headers .= "\r\nContent-Type: application/json\r\nContent-Length: 0";
        }

        $context = stream_context_create(array('http' => array(
            'method' => $method,
            'header' => $headers,
            'content' => $payload,
            'ignore_errors' => TRUE,
            'timeout' => $timeout === NULL ? $this->timeout : max(2, min(600, (int) $timeout)),
        )));

        $responseHeaders = array();
        $response = @file_get_contents($this->baseUrl.$path, FALSE, $context);
        if (isset($http_response_header) && is_array($http_response_header)) {
            $responseHeaders = $http_response_header;
        }

        $status = 0;
        foreach ($responseHeaders as $line) {
            if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
        }

        if ($response === FALSE && $status === 0) {
            $this->lastError = 'Engine unreachable at '.$this->baseUrl.'.';
            return array('status' => 502, 'body' => '', 'json' => NULL);
        }

        $decoded = NULL;
        if ($response !== FALSE && $response !== '') {
            $decoded = json_decode($response, TRUE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = NULL;
            }
        }

        if ($status >= 400 && is_array($decoded) && isset($decoded['message'])) {
            $this->lastError = trim(strip_tags((string) $decoded['message']));
        }

        return array('status' => $status ?: 200, 'body' => $response === FALSE ? '' : $response, 'json' => $decoded);
    }

    public function ping()
    {
        $result = $this->request('GET', '/_ping', NULL, 4);
        return $result['status'] >= 200 && $result['status'] < 400;
    }

    /**
     * @return bool TRUE when the image is present on the engine.
     */
    public function imageExists($reference)
    {
        $result = $this->request('GET', '/images/'.rawurlencode((string) $reference).'/json', NULL, 6);
        return $result['status'] === 200;
    }

    /**
     * Pull an image (blocking). Used only for non-jobseeker base images.
     *
     * @return array{ok:bool, message:string}
     */
    public function pullImage($repository, $tag = 'latest')
    {
        $query = '/images/create?fromImage='.rawurlencode((string) $repository).'&tag='.rawurlencode((string) $tag);
        $result = $this->request('POST', $query, NULL, 600);
        $ok = $result['status'] >= 200 && $result['status'] < 300 && strpos((string) $result['body'], '"errorDetail"') === FALSE;
        return array('ok' => $ok, 'message' => $ok ? 'pulled '.$repository.':'.$tag : ($this->lastError ?: 'image pull failed'));
    }

    /**
     * @return string network id, or '' on failure ($this->lastError set).
     */
    public function createNetwork($name, array $labels = array())
    {
        $result = $this->request('POST', '/networks/create', array(
            'Name' => (string) $name,
            'Driver' => 'bridge',
            'CheckDuplicate' => TRUE,
            'Attachable' => TRUE,
            'Labels' => $this->stringMap($labels),
        ), 15);
        if ($result['status'] >= 200 && $result['status'] < 300 && isset($result['json']['Id'])) {
            return (string) $result['json']['Id'];
        }
        if ($this->lastError === '') {
            $this->lastError = 'Could not create network '.$name.' (HTTP '.$result['status'].').';
        }
        return '';
    }

    public function removeNetwork($idOrName)
    {
        $result = $this->request('DELETE', '/networks/'.rawurlencode((string) $idOrName), NULL, 15);
        return $result['status'] === 204 || $result['status'] === 404;
    }

    /**
     * Create a container from a fully-formed spec.
     *
     * @param string $name  container name (must be unique on the engine)
     * @param array  $spec  Engine API create payload (Image, Cmd, Env, Labels, HostConfig, ...)
     * @return string container id, or '' on failure.
     */
    public function createContainer($name, array $spec)
    {
        $result = $this->request('POST', '/containers/create?name='.rawurlencode((string) $name), $spec, 20);
        if ($result['status'] >= 200 && $result['status'] < 300 && isset($result['json']['Id'])) {
            return (string) $result['json']['Id'];
        }
        if ($this->lastError === '') {
            $this->lastError = 'Could not create container '.$name.' (HTTP '.$result['status'].').';
        }
        return '';
    }

    public function startContainer($id)
    {
        $result = $this->request('POST', '/containers/'.rawurlencode((string) $id).'/start', NULL, 20);
        return $result['status'] === 204 || $result['status'] === 304;
    }

    public function stopContainer($id, $secondsBeforeKill = 3)
    {
        $result = $this->request('POST', '/containers/'.rawurlencode((string) $id).'/stop?t='.max(0, (int) $secondsBeforeKill), NULL, 20);
        return $result['status'] === 204 || $result['status'] === 304 || $result['status'] === 404;
    }

    public function removeContainer($id, $force = TRUE)
    {
        $query = '/containers/'.rawurlencode((string) $id).'?v=1'.($force ? '&force=1' : '');
        $result = $this->request('DELETE', $query, NULL, 20);
        return $result['status'] === 204 || $result['status'] === 404;
    }

    /**
     * @return array{found:bool, running:bool, status:string, exitCode:int|null, error:string, oomKilled:bool, startedAt:string, finishedAt:string}
     */
    public function inspectContainer($id)
    {
        $result = $this->request('GET', '/containers/'.rawurlencode((string) $id).'/json', NULL, 8);
        if ($result['status'] !== 200 || ! is_array($result['json'])) {
            return array(
                'found' => FALSE, 'running' => FALSE, 'status' => 'unknown',
                'exitCode' => NULL, 'error' => $this->lastError, 'oomKilled' => FALSE,
                'startedAt' => '', 'finishedAt' => '',
            );
        }
        $state = isset($result['json']['State']) && is_array($result['json']['State']) ? $result['json']['State'] : array();
        $running = ! empty($state['Running']);
        return array(
            'found' => TRUE,
            'running' => $running,
            'status' => isset($state['Status']) ? strtolower((string) $state['Status']) : 'unknown',
            'exitCode' => $running ? NULL : (isset($state['ExitCode']) ? (int) $state['ExitCode'] : NULL),
            'error' => isset($state['Error']) ? trim((string) $state['Error']) : '',
            'oomKilled' => ! empty($state['OOMKilled']),
            'startedAt' => isset($state['StartedAt']) ? (string) $state['StartedAt'] : '',
            'finishedAt' => isset($state['FinishedAt']) ? (string) $state['FinishedAt'] : '',
        );
    }

    /**
     * Fetch a plain-text log tail. Containers are created with Tty=true so the
     * stream carries no 8-byte multiplex frame headers.
     */
    public function containerLogs($id, $tailLines = 400)
    {
        $tail = max(1, min(5000, (int) $tailLines));
        $result = $this->request('GET', '/containers/'.rawurlencode((string) $id).'/logs?stdout=1&stderr=1&tail='.$tail, NULL, 12);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            return '';
        }
        return $this->stripControlFrames((string) $result['body']);
    }

    /**
     * List containers carrying a given label (optionally a specific value).
     *
     * @return array<int,array> raw Engine container summaries
     */
    public function listByLabel($label, $value = NULL)
    {
        $filterLabel = $value === NULL ? (string) $label : $label.'='.$value;
        $filters = json_encode(array('label' => array($filterLabel)));
        $result = $this->request('GET', '/containers/json?all=1&filters='.rawurlencode($filters), NULL, 8);
        return $result['status'] === 200 && is_array($result['json']) ? $result['json'] : array();
    }

    private function stringMap(array $map)
    {
        $out = array();
        foreach ($map as $key => $value) {
            $out[(string) $key] = (string) $value;
        }
        return (object) $out;
    }

    /**
     * Best-effort removal of Docker's multiplex frame headers should a container
     * ever be created without a TTY. Each frame is an 8-byte header
     * (stream byte, 3 nulls, uint32 big-endian length) followed by the payload.
     */
    private function stripControlFrames($raw)
    {
        if ($raw === '' || preg_match('//u', $raw)) {
            // Valid UTF-8 with no NULs -> already plain text (TTY mode).
            if (strpos($raw, "\x00") === FALSE) {
                return $raw;
            }
        }
        $out = '';
        $offset = 0;
        $length = strlen($raw);
        while ($offset + 8 <= $length) {
            $type = ord($raw[$offset]);
            $size = unpack('N', substr($raw, $offset + 4, 4))[1];
            if (($type < 1 || $type > 2) || $size < 0 || $offset + 8 + $size > $length) {
                return $raw; // Not framed the way we expected; return as-is.
            }
            $out .= substr($raw, $offset + 8, $size);
            $offset += 8 + $size;
        }
        return $out !== '' ? $out : $raw;
    }
}
