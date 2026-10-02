<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * A small client for the Docker Engine API of the job runtime
 * (JOBSEEKER_DOCKER_RUNTIME_URL, the docker-runtime service), which builds
 * and runs workspace runtimes. The host's own Docker socket is never used:
 * it stays behind the read-only docker-monitor-proxy.
 */
class DockerEngine
{
    const API_VERSION = 'v1.43';

    private $baseUrl = '';

    public function __construct($params = array())
    {
        $url = isset($params['url']) ? (string) $params['url'] : (string) getenv('JOBSEEKER_DOCKER_RUNTIME_URL');
        $this->baseUrl = rtrim(trim($url), '/');
    }

    public function configured()
    {
        return (bool) preg_match('#^https?://[A-Za-z0-9._-]+(?::[0-9]{1,5})?$#', $this->baseUrl);
    }

    public function baseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param mixed $body array (sent as JSON), string, or NULL
     * @return array status (0 when unreachable), data (decoded JSON or NULL), body
     */
    public function request($method, $path, $body = NULL, $timeout = 10)
    {
        if (! $this->configured() || ! function_exists('curl_init')) {
            return array('status' => 0, 'data' => NULL, 'body' => '');
        }
        $handle = curl_init($this->baseUrl.'/'.self::API_VERSION.'/'.ltrim($path, '/'));
        $headers = array('Accept: application/json');
        curl_setopt_array($handle, array(
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => max(1, (int) $timeout)
        ));
        if ($body !== NULL) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($handle, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES));
        }
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($handle);
        $status = $response === FALSE ? 0 : (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        $response = $response === FALSE ? '' : (string) $response;
        $decoded = json_decode($response, TRUE);
        return array('status' => $status, 'data' => $decoded, 'body' => $response);
    }

    /** The error message of a failed API call. */
    public function message(array $response, $fallback)
    {
        if (is_array($response['data']) && isset($response['data']['message'])) {
            return (string) $response['data']['message'];
        }
        return $response['status'] === 0 ? 'The Docker job runtime is not reachable.' : $fallback;
    }

    public function available()
    {
        return $this->request('GET', '_ping', NULL, 3)['status'] === 200;
    }

    /** @return array|NULL|FALSE the inspect document, NULL when missing, FALSE on error */
    public function inspectContainer($name)
    {
        $response = $this->request('GET', 'containers/'.rawurlencode($name).'/json', NULL, 5);
        if ($response['status'] === 404) {
            return NULL;
        }
        return $response['status'] === 200 && is_array($response['data']) ? $response['data'] : FALSE;
    }

    /** @return array|NULL|FALSE */
    public function inspectImage($reference)
    {
        $response = $this->request('GET', 'images/'.rawurlencode($reference).'/json', NULL, 5);
        if ($response['status'] === 404) {
            return NULL;
        }
        return $response['status'] === 200 && is_array($response['data']) ? $response['data'] : FALSE;
    }

    public function createContainer($name, array $config)
    {
        return $this->request('POST', 'containers/create?'.http_build_query(array('name' => $name)), $config, 15);
    }

    public function startContainer($name)
    {
        return $this->request('POST', 'containers/'.rawurlencode($name).'/start', NULL, 15);
    }

    public function stopContainer($name, $seconds = 10)
    {
        return $this->request('POST', 'containers/'.rawurlencode($name).'/stop?t='.(int) $seconds, NULL, (int) $seconds + 10);
    }

    public function removeContainer($name)
    {
        return $this->request('DELETE', 'containers/'.rawurlencode($name).'?force=1', NULL, 20);
    }

    public function removeVolume($name)
    {
        return $this->request('DELETE', 'volumes/'.rawurlencode($name), NULL, 10);
    }

    /**
     * Runs a command in a throwaway container of $image as root, without a
     * network, and waits for it.
     *
     * @return int|FALSE its exit status
     */
    public function runOnce($image, array $command, array $mounts, array $capabilities, $timeout = 120)
    {
        // A busy engine (a runtime build) can time out a start or a remove,
        // leaving a container behind; the next run clears what is old.
        foreach ((array) $this->containers(array('com.jobseeker.workspace-once' => '1')) as $stale) {
            if (isset($stale['Id'], $stale['Created']) && (int) $stale['Created'] < time() - 600) {
                $this->removeContainer($stale['Id']);
            }
        }
        $name = 'jobseeker-once-'.bin2hex(random_bytes(6));
        $created = $this->createContainer($name, array(
            'Image' => $image,
            'User' => '0:0',
            'Entrypoint' => array_slice($command, 0, 1),
            'Cmd' => array_slice($command, 1),
            'Labels' => array('com.jobseeker.workspace-once' => '1'),
            'HostConfig' => array(
                'NetworkMode' => 'none',
                'Mounts' => $mounts,
                'CapDrop' => array('ALL'),
                'CapAdd' => $capabilities,
                'SecurityOpt' => array('no-new-privileges')
            )
        ));
        if ($created['status'] !== 201) {
            return FALSE;
        }
        $status = FALSE;
        $started = $this->request('POST', 'containers/'.rawurlencode($name).'/start', NULL, 60);
        if (in_array($started['status'], array(204, 304), TRUE)) {
            $waited = $this->request('POST', 'containers/'.rawurlencode($name).'/wait', NULL, $timeout);
            $status = $waited['status'] === 200 && isset($waited['data']['StatusCode']) ? (int) $waited['data']['StatusCode'] : FALSE;
        }
        $this->removeContainer($name);
        return $status;
    }

    /** Containers carrying every label in $labels (name => value). */
    public function containers(array $labels)
    {
        $filters = array('label' => array());
        foreach ($labels as $name => $value) {
            $filters['label'][] = $value === NULL ? $name : $name.'='.$value;
        }
        $response = $this->request('GET', 'containers/json?'.http_build_query(array('all' => 1, 'filters' => json_encode($filters))), NULL, 5);
        return $response['status'] === 200 && is_array($response['data']) ? $response['data'] : FALSE;
    }

    /** The last lines of a container's output, demultiplexed. */
    public function containerLogs($name, $tail = 200)
    {
        $response = $this->request('GET', 'containers/'.rawurlencode($name).'/logs?'.http_build_query(array('stdout' => 1, 'stderr' => 1, 'tail' => (int) $tail, 'timestamps' => 0)), NULL, 5);
        if ($response['status'] !== 200) {
            return '';
        }
        $raw = $response['body'];
        $text = '';
        $offset = 0;
        // Without a TTY every frame has an 8-byte header: stream, 0, 0, 0, size.
        while ($offset + 8 <= strlen($raw) && in_array(ord($raw[$offset]), array(0, 1, 2), TRUE) && substr($raw, $offset + 1, 3) === "\0\0\0") {
            $size = unpack('N', substr($raw, $offset + 4, 4))[1];
            $text .= substr($raw, $offset + 8, $size);
            $offset += 8 + $size;
        }
        // OpenVSCode prints its connection token when it starts; logs are shown
        // to more people than the editor belongs to.
        return preg_replace('/([?&]tkn=)[^\s&"\']+/', '$1[redacted]', $offset === 0 ? $raw : $text);
    }

    /**
     * Runs build steps in the background, one after the other, stopping at the
     * first failure. Each step posts a tar context to the build API and appends
     * the stream to <directory>/build.log; <directory>/exit gets the status.
     *
     * @param array $steps list of array(title, tar (path), query (array))
     */
    public function startBuild(array $steps, $directory)
    {
        if (! $this->configured()) {
            return FALSE;
        }
        $directory = rtrim($directory, '/');
        $lines = array(
            '#!/bin/sh',
            '# Generated by JobSeeker: builds one workspace runtime in the background.',
            'cd '.escapeshellarg($directory).' || exit 1',
            'echo $$ > pid',
            ': > build.log',
            'status=0'
        );
        foreach ($steps as $index => $step) {
            $url = $this->baseUrl.'/'.self::API_VERSION.'/build?'.http_build_query($step['query']);
            $lines[] = 'if [ "$status" -eq 0 ]; then';
            $lines[] = '  printf \'%s\\n\' '.escapeshellarg(json_encode(array('stream' => "\n".'[JobSeeker] '.$step['title']."\n"))).' >> build.log';
            $lines[] = '  : > step.log';
            $lines[] = '  curl -sS -g -N --max-time 5400 -X POST -H "Content-Type: application/x-tar" -T '.escapeshellarg($step['tar']).' '.escapeshellarg($url).' > step.log 2> step.err || status=$?';
            $lines[] = '  cat step.log >> build.log';
            $lines[] = '  if [ -s step.err ]; then printf \'{"errorDetail":{"message":"%s"}}\\n\' "$(tr -d \'"\\\\\' < step.err | tr \'\\n\' \' \')" >> build.log; fi';
            $lines[] = '  if grep -q -e \'"errorDetail"\' -e \'^{"message"\' step.log; then status=1; fi';
            $lines[] = 'fi';
        }
        $lines[] = 'rm -f step.log step.err '.implode(' ', array_map(function($step) { return escapeshellarg(basename($step['tar'])); }, $steps));
        $lines[] = 'echo "$status" > exit.tmp && mv exit.tmp exit';
        $lines[] = '';
        $script = $directory.'/build.sh';
        if (file_put_contents($script, implode("\n", $lines), LOCK_EX) === FALSE) {
            return FALSE;
        }
        // Detached from the request: the intermediate shell exits at once.
        $process = proc_open(array('sh', '-c', 'setsid sh "$0" </dev/null >/dev/null 2>&1 &', $script), array(), $pipes);
        if (! is_resource($process)) {
            return FALSE;
        }
        return proc_close($process) === 0;
    }
}
