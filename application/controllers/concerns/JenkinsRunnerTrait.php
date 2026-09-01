<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Shared helpers for running a short-lived, JobSeeker-managed shell job on a
 * Jenkins worker and reading its console output. Used by the inline-Python
 * preview and by the connector connection-test framework so both exercise the
 * real job runtime (drivers, cloud identity, agent routing).
 *
 * Requires the host controller to extend BaseController (for requestJenkins /
 * requestJenkinsBuild) and to provide createRuntimeEnvironmentProperties() OR
 * fall back to the minimal parameter block written here.
 */
trait JenkinsRunnerTrait
{
    protected function jenkinsRunnerJobPath($jobName)
    {
        $segments = array();
        foreach (explode('/', trim((string) $jobName, '/')) as $segment) {
            if ($segment !== '') {
                $segments[] = 'job/'.rawurlencode($segment);
            }
        }
        return implode('/', $segments);
    }

    protected function jenkinsRunnerSuccessful($status)
    {
        return in_array((int) $status, array(200, 201, 302, 303), TRUE);
    }

    /**
     * Build a Freestyle project XML with a single shell step and an ENVIRONMENT
     * string parameter (default $environment). Kept intentionally small.
     */
    protected function jenkinsRunnerShellJobXml($description, $command, $environment)
    {
        $dom = new DOMDocument();
        $dom->encoding = 'UTF-8';
        $dom->xmlVersion = '1.1';
        $dom->formatOutput = TRUE;

        $text = function($name, $value) use ($dom) {
            $element = $dom->createElement($name);
            $element->appendChild($dom->createTextNode((string) $value));
            return $element;
        };

        $project = $dom->createElement('project');
        $project->appendChild($text('description', $description));
        $project->appendChild($text('keepDependencies', 'false'));

        $properties = $dom->createElement('properties');
        $paramsProperty = $dom->createElement('hudson.model.ParametersDefinitionProperty');
        $definitions = $dom->createElement('parameterDefinitions');
        $stringParam = $dom->createElement('hudson.model.StringParameterDefinition');
        $stringParam->appendChild($text('name', 'ENVIRONMENT'));
        $stringParam->appendChild($text('description', 'Runtime environment managed by JobSeeker.'));
        $stringParam->appendChild($text('defaultValue', $environment));
        $stringParam->appendChild($text('trim', 'true'));
        $definitions->appendChild($stringParam);
        $paramsProperty->appendChild($definitions);
        $properties->appendChild($paramsProperty);
        $project->appendChild($properties);

        $scm = $dom->createElement('scm');
        $scm->setAttribute('class', 'hudson.scm.NullSCM');
        $project->appendChild($scm);
        $project->appendChild($text('canRoam', 'true'));
        $project->appendChild($text('disabled', 'false'));
        $project->appendChild($text('concurrentBuild', 'false'));

        $builders = $dom->createElement('builders');
        $shell = $dom->createElement('hudson.tasks.Shell');
        $shell->appendChild($text('command', $command));
        $builders->appendChild($shell);
        $project->appendChild($builders);

        $project->appendChild($dom->createElement('publishers'));
        $project->appendChild($dom->createElement('buildWrappers'));

        $dom->appendChild($project);
        return $dom->saveXML();
    }

    protected function saveDisposableJenkinsJob($jobName, $xml)
    {
        $jobPath = $this->jenkinsRunnerJobPath($jobName);
        $existing = $this->requestJenkins('GET', $jobPath.'/api/json');
        if ((int) $existing['status'] === 200) {
            $response = $this->requestJenkins('POST', $jobPath.'/config.xml', $xml, 'text/xml');
        } else {
            $response = $this->requestJenkins('POST', 'createItem?name='.rawurlencode($jobName), $xml, 'text/xml');
        }
        return array('ok' => $this->jenkinsRunnerSuccessful($response['status']), 'status' => $response['status']);
    }

    protected function deleteDisposableJenkinsJob($jobName)
    {
        $this->requestJenkins('POST', $this->jenkinsRunnerJobPath($jobName).'/doDelete');
    }

    protected function triggerDisposableJenkinsBuild($jobName, $parameters)
    {
        $response = $this->requestJenkinsBuild(
            $this->jenkinsRunnerJobPath($jobName).'/buildWithParameters',
            http_build_query($parameters),
            'application/x-www-form-urlencoded'
        );
        if (! is_array($response)) {
            return array('ok' => FALSE, 'status' => 502, 'queueId' => '');
        }
        $queueId = '';
        foreach (isset($response['headers']) && is_array($response['headers']) ? $response['headers'] : array() as $header) {
            if (preg_match('#/queue/item/(\d+)/?#', $header, $matches)) {
                $queueId = $matches[1];
                break;
            }
        }
        return array(
            'ok' => $this->jenkinsRunnerSuccessful($response['status']),
            'status' => $response['status'],
            'queueId' => $queueId,
            'body' => isset($response['body']) ? $response['body'] : ''
        );
    }

    protected function waitForDisposableBuild($jobName, $queueId, $timeoutSeconds)
    {
        $jobPath = $this->jenkinsRunnerJobPath($jobName);
        $deadline = microtime(TRUE) + max(5, (int) $timeoutSeconds);
        $buildNumber = '';

        while (microtime(TRUE) <= $deadline) {
            if ($queueId !== '' && $buildNumber === '') {
                $queue = $this->requestJenkins('GET', 'queue/item/'.rawurlencode($queueId).'/api/json?tree=cancelled,executable[number]');
                if ((int) $queue['status'] === 200) {
                    $payload = json_decode($queue['body']);
                    if (is_object($payload)) {
                        if (! empty($payload->cancelled)) {
                            return array('status' => 'CANCELLED', 'buildNumber' => '');
                        }
                        if (isset($payload->executable->number)) {
                            $buildNumber = (string) $payload->executable->number;
                        }
                    }
                }
            }
            if ($buildNumber === '') {
                $job = $this->requestJenkins('GET', $jobPath.'/api/json?tree=lastBuild[number,building,result]');
                if ((int) $job['status'] === 200) {
                    $payload = json_decode($job['body']);
                    if (is_object($payload) && isset($payload->lastBuild->number)) {
                        $buildNumber = (string) $payload->lastBuild->number;
                    }
                }
            }
            if ($buildNumber !== '') {
                $build = $this->requestJenkins('GET', $jobPath.'/'.rawurlencode($buildNumber).'/api/json?tree=building,result');
                if ((int) $build['status'] === 200) {
                    $payload = json_decode($build['body']);
                    if (is_object($payload) && isset($payload->building) && $payload->building !== TRUE) {
                        return array(
                            'status' => isset($payload->result) && $payload->result !== NULL ? (string) $payload->result : 'UNKNOWN',
                            'buildNumber' => $buildNumber
                        );
                    }
                }
            }
            usleep(1000000);
        }
        return array('status' => 'TIMEOUT', 'buildNumber' => $buildNumber);
    }

    protected function disposableBuildConsole($jobName, $buildNumber)
    {
        if ($buildNumber === '') {
            return '';
        }
        $response = $this->requestJenkins('GET', $this->jenkinsRunnerJobPath($jobName).'/'.rawurlencode($buildNumber).'/consoleText');
        if ((int) $response['status'] !== 200) {
            return '';
        }
        $body = (string) $response['body'];
        return strlen($body) > 60000 ? substr($body, -60000) : $body;
    }

    /**
     * Wrap a script so it is time-limited on the worker even without `timeout`.
     */
    protected function wrapWorkerShellCommand($command, $timeoutSeconds)
    {
        $timeoutSeconds = max(5, (int) $timeoutSeconds);
        $escaped = escapeshellarg((string) $command);
        return 'if command -v timeout >/dev/null 2>&1; then timeout '.$timeoutSeconds.'s sh -lc '.$escaped.'; else sh -lc '.$escaped.'; fi';
    }

    protected function parseConnTestConsole($console)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $console);
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = trim($lines[$index]);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $decoded = json_decode($line, TRUE);
            if (is_array($decoded) && isset($decoded['status'])) {
                return $decoded;
            }
        }
        return NULL;
    }

    /**
     * Run a real connection handshake for one catalog connector on a Jenkins
     * worker: create a disposable job that materializes the connector exactly
     * like a real build and runs `jobseeker.conntest`, then delete it.
     *
     * @param string $connectorKey normalized connector key
     * @param string $environment  concrete active environment to materialize under
     * @param string $jobScope     job name the connector is scoped to ('*' -> synthetic)
     * @return array normalized result: ok,status,latencyMs,serverVersion,message,checks,connectorType,testEnvironment,buildResult[,consoleTail]
     */
    protected function runConnectorConnectionTest($connectorKey, $environment, $jobScope = '*')
    {
        $jobScope = trim((string) $jobScope);
        if ($jobScope === '' || $jobScope === 'ALL' || $jobScope === '*') {
            $jobScope = 'jobseeker-connection-test';
        }

        $testJobName = '__jobseeker_conn_test_'.substr(bin2hex(random_bytes(6)), 0, 12);
        $script = implode("\n", array(
            'set -e',
            'command -v jobseeker-connector >/dev/null || { echo "jobseeker-connector is not installed on this Jenkins worker."; exit 127; }',
            'command -v python3 >/dev/null || { echo "python3 is not installed on this Jenkins worker."; exit 127; }',
            'JOBSEEKER_CT_DIR="$(mktemp -d 2>/dev/null || echo "/tmp/jobseeker-conntest-$$")"',
            'mkdir -p "$JOBSEEKER_CT_DIR"',
            'trap \'rm -rf "$JOBSEEKER_CT_DIR"\' EXIT',
            'jobseeker-connector materialize --directory "$JOBSEEKER_CT_DIR" --environment "$ENVIRONMENT" --job '.escapeshellarg($jobScope).' >/dev/null',
            'echo "JOBSEEKER_CONNTEST_BEGIN"',
            'python3 -m jobseeker.conntest --directory "$JOBSEEKER_CT_DIR" --key '.escapeshellarg((string) $connectorKey).' --timeout 8 --json',
        ));
        $command = $this->wrapWorkerShellCommand($script, 75);
        $xml = $this->jenkinsRunnerShellJobXml('JobSeeker connector connection test. Created and deleted automatically.', $command, $environment);

        $saved = $this->saveDisposableJenkinsJob($testJobName, $xml);
        if (! $saved['ok']) {
            return array('ok' => FALSE, 'status' => 'error', 'message' => 'Could not create the Jenkins test job (HTTP '.$saved['status'].').', 'buildResult' => 'NOT_CREATED', 'httpStatus' => 502);
        }

        try {
            $triggered = $this->triggerDisposableJenkinsBuild($testJobName, array('ENVIRONMENT' => $environment));
            if (! $triggered['ok']) {
                $message = trim((string) $triggered['body']) !== '' ? trim((string) $triggered['body']) : 'Could not start the Jenkins test job (HTTP '.$triggered['status'].').';
                return array('ok' => FALSE, 'status' => 'error', 'message' => $message, 'buildResult' => 'NOT_STARTED', 'httpStatus' => 502);
            }
            $build = $this->waitForDisposableBuild($testJobName, $triggered['queueId'], 90);
            $console = $this->disposableBuildConsole($testJobName, isset($build['buildNumber']) ? $build['buildNumber'] : '');
        } finally {
            $this->deleteDisposableJenkinsJob($testJobName);
        }

        $buildStatus = isset($build['status']) ? $build['status'] : 'UNKNOWN';
        $result = $this->parseConnTestConsole($console);
        if ($result === NULL) {
            return array(
                'ok' => FALSE,
                'status' => $buildStatus === 'TIMEOUT' ? 'timeout' : 'error',
                'message' => $buildStatus === 'TIMEOUT'
                    ? 'The connection test did not finish within the time limit.'
                    : 'The connection test did not produce a result. See the worker output.',
                'buildResult' => $buildStatus,
                'consoleTail' => trim(substr((string) $console, -1200)),
                'httpStatus' => 422,
            );
        }

        return array(
            'ok' => (bool) $result['ok'],
            'status' => (string) $result['status'],
            'latencyMs' => isset($result['latency_ms']) ? $result['latency_ms'] : NULL,
            'serverVersion' => isset($result['server_version']) ? $result['server_version'] : '',
            'message' => isset($result['message']) ? $result['message'] : '',
            'checks' => isset($result['checks']) ? $result['checks'] : array(),
            'connectorType' => isset($result['type']) ? $result['type'] : '',
            'testEnvironment' => $environment,
            'buildResult' => $buildStatus,
            'httpStatus' => $result['ok'] ? 200 : 422,
        );
    }
}
