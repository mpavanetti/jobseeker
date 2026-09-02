<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Minimal Docker Engine API client for the ML compute plane.
 *
 * The PHP-FPM image ships without ext-curl, so this talks to the engine over
 * plain stream contexts. It only implements the verbs the ML run orchestrator
 * needs (ping, info, images, single containers, logs, stats) and holds no global
 * state so it can be exercised outside CodeIgniter.
 *
 * Target engine defaults to the in-stack Docker-in-Docker daemon
 * (`tcp://docker-runtime:2375`, exposed to PHP as JOBSEEKER_DOCKER_RUNTIME_URL) -
 * the same engine the Docker Monitor screen and the Python job runtime use.
 */
class MlEngineClient
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
        $this->timeout = isset($config['timeout']) ? max(2, min(60, (int) $config['timeout'])) : 12;
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
     * @param string     $method GET|POST|DELETE
     * @param string     $path   e.g. /containers/create
     * @param array|null $body   JSON encoded when provided
     * @param int|null   $timeout override for slow calls (image pulls)
     * @param bool       $raw    return the body untouched (log/stat text)
     * @return array{status:int, body:string, json:mixed}
     */
    public function request($method, $path, $body = NULL, $timeout = NULL, $raw = FALSE)
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
            $headers .= "\r\nContent-Type: application/json\r\nContent-Length: 0";
        }

        $context = stream_context_create(array('http' => array(
            'method' => $method,
            'header' => $headers,
            'content' => $payload,
            'ignore_errors' => TRUE,
            'timeout' => $timeout === NULL ? $this->timeout : max(2, min(900, (int) $timeout)),
        )));

        $response = @file_get_contents($this->baseUrl.$path, FALSE, $context);
        $responseHeaders = (isset($http_response_header) && is_array($http_response_header)) ? $http_response_header : array();

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

        if ($raw) {
            return array('status' => $status ?: 200, 'body' => (string) $response, 'json' => NULL);
        }
        $decoded = NULL;
        if ($response !== FALSE && $response !== '') {
            $decoded = json_decode($response, TRUE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = NULL;
            }
        }
        return array('status' => $status ?: 200, 'body' => (string) $response, 'json' => $decoded);
    }

    public function ping()
    {
        $res = $this->request('GET', '/_ping');
        return $res['status'] === 200;
    }

    /**
     * Build an image from an in-memory tar context (the Engine `/build` route).
     *
     * @param string $tarBytes   ustar archive containing at least the Dockerfile
     * @param string $tag         image tag, e.g. jobseeker/ml-job/foo:3
     * @param string $dockerfile  Dockerfile name inside the context
     * @return array{ok:bool, image_id:string, log:string, message:string}
     */
    public function buildImage($tarBytes, $tag, $dockerfile = 'Dockerfile')
    {
        $path = '/build?'.http_build_query(array(
            't' => $tag, 'dockerfile' => $dockerfile, 'rm' => '1', 'forcerm' => '1', 'pull' => '0',
        ), '', '&');

        $context = stream_context_create(array('http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-tar\r\nConnection: close\r\nContent-Length: ".strlen($tarBytes),
            'content' => $tarBytes,
            'ignore_errors' => TRUE,
            'timeout' => max(120, min(1800, (int) (getenv('JOBSEEKER_ML_IMAGE_BUILD_TIMEOUT') ?: 900))),
        )));
        $response = @file_get_contents($this->baseUrl.$path, FALSE, $context);
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                }
            }
        }
        if ($response === FALSE) {
            return array('ok' => FALSE, 'image_id' => '', 'log' => '', 'message' => 'Engine unreachable for build.');
        }

        // The response is newline-delimited JSON: {"stream": "..."} lines plus a
        // final {"aux":{"ID":"sha256:..."}} or {"errorDetail":{"message":"..."}}.
        $log = '';
        $imageId = '';
        $error = '';
        foreach (preg_split('/\r?\n/', (string) $response) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $frame = json_decode($line, TRUE);
            if (! is_array($frame)) {
                $log .= $line."\n";
                continue;
            }
            if (isset($frame['stream'])) {
                $log .= $frame['stream'];
            }
            if (isset($frame['aux']['ID'])) {
                $imageId = (string) $frame['aux']['ID'];
            }
            if (isset($frame['error'])) {
                $error = (string) $frame['error'];
            } elseif (isset($frame['errorDetail']['message'])) {
                $error = (string) $frame['errorDetail']['message'];
            }
        }
        if ($error !== '' || ($status >= 400)) {
            return array('ok' => FALSE, 'image_id' => $imageId,
                'log' => $log, 'message' => $error !== '' ? $error : ('build failed (HTTP '.$status.')'));
        }
        return array('ok' => TRUE, 'image_id' => $imageId, 'log' => $log, 'message' => 'built');
    }

    public function info()
    {
        $res = $this->request('GET', '/info');
        return is_array($res['json']) ? $res['json'] : array();
    }
}
