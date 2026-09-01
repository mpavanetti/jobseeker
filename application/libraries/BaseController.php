<?php defined ( 'BASEPATH' ) or exit ( 'No direct script access allowed' ); 

/**
 * Class : BaseController
 * Base Class to control over all the classes
 * @author : Matheus Pavanetti
 * @version : 1.1
 * @since : 2019
 */
class BaseController extends CI_Controller {
	protected $role = '';
	protected $vendorId = '';
	protected $name = '';
	protected $roleText = '';
	protected $global = array ();
	protected $lastLogin = '';
	private $jenkinsOnlineEnvironmentAgentCapacityCache = array();

	protected function getRuntimeConfig() {
		if (! is_readable(JOBSEEKER_CONFIG_PATH)) {
			log_message('error', 'Runtime config file is not readable: ' . JOBSEEKER_CONFIG_PATH);
			show_error('Application configuration is unavailable.', 500);
		}

		$configJson = file_get_contents(JOBSEEKER_CONFIG_PATH);
		$config = json_decode($configJson);

		if (! is_object($config) || empty($config->jenkins) || empty($config->setup)) {
			log_message('error', 'Runtime config file is invalid: ' . JOBSEEKER_CONFIG_PATH);
			show_error('Application configuration is invalid.', 500);
		}

		return $config;
	}

	protected function requestJenkins($method, $path, $body = '', $contentType = NULL) {
		if ($path === NULL || $path === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path) || strpos($path, '..') !== FALSE) {
			return array('status' => 400, 'content_type' => 'text/plain', 'body' => 'Invalid Jenkins path.', 'headers' => array());
		}

		$config = $this->getRuntimeConfig();

		if (empty($config->jenkins->enabled)) {
			return array('status' => 503, 'content_type' => 'text/plain', 'body' => 'Jenkins integration is disabled.', 'headers' => array());
		}

		$jenkinsUrl = getenv('JOBSEEKER_JENKINS_INTERNAL_URL') ?: $config->jenkins->url;
		$requestUrl = rtrim($jenkinsUrl, '/') . '/' . ltrim($path, '/');
		$jenkinsUsername = getenv('JOBSEEKER_JENKINS_USER') ?: $config->jenkins->username;
		$jenkinsToken = getenv('JOBSEEKER_JENKINS_TOKEN') ?: $config->jenkins->token;
		$authorizationHeader = 'Authorization: Basic ' . base64_encode($jenkinsUsername . ':' . $jenkinsToken);
		$headers = array($authorizationHeader);

		if (! empty($contentType)) {
			$headers[] = 'Content-Type: ' . $contentType;
		}

		$method = strtoupper($method);

		if ($method !== 'GET' && $method !== 'HEAD') {
			$crumbContext = stream_context_create(array(
				'http' => array(
					'method' => 'GET',
					'header' => $authorizationHeader,
					'ignore_errors' => TRUE,
					'timeout' => 10
				)
			));
			$crumbResponse = file_get_contents(rtrim($jenkinsUrl, '/') . '/crumbIssuer/api/json', FALSE, $crumbContext);
			$crumbHeaders = isset($http_response_header) ? $http_response_header : array();
			$crumb = json_decode($crumbResponse);

			if (is_object($crumb) && ! empty($crumb->crumbRequestField) && ! empty($crumb->crumb)) {
				$headers[] = $crumb->crumbRequestField . ': ' . $crumb->crumb;
				$cookies = array();

				foreach ($crumbHeaders as $header) {
					if (stripos($header, 'Set-Cookie:') === 0) {
						$cookie = explode(';', trim(substr($header, strlen('Set-Cookie:'))), 2);
						$cookies[] = $cookie[0];
					}
				}

				if (! empty($cookies)) {
					$headers[] = 'Cookie: ' . implode('; ', $cookies);
				}
			}
		}

		$options = array(
			'http' => array(
				'method' => $method,
				'header' => implode("\r\n", $headers),
				'ignore_errors' => TRUE,
				'timeout' => 30
			)
		);

		if ($method !== 'GET' && $method !== 'HEAD') {
			$options['http']['content'] = $body;
		}

		$response = file_get_contents($requestUrl, FALSE, stream_context_create($options));
		$responseHeaders = isset($http_response_header) ? $http_response_header : array();
		$statusCode = 502;
		$responseContentType = 'text/plain';

		if (! empty($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches)) {
			$statusCode = (int) $matches[1];
		}

		foreach ($responseHeaders as $header) {
			if (stripos($header, 'Content-Type:') === 0) {
				$responseContentType = trim(substr($header, strlen('Content-Type:')));
				break;
			}
		}

		if ($response === FALSE) {
			return array('status' => 502, 'content_type' => 'text/plain', 'body' => 'Unable to reach Jenkins.', 'headers' => $responseHeaders);
		}

		return array('status' => $statusCode, 'content_type' => $responseContentType, 'body' => $response, 'headers' => $responseHeaders);
	}

	protected function requestInternalHttp($baseUrl, $method, $path, $body = '', $timeout = 3) {
		$baseUrl = rtrim(trim((string) $baseUrl), '/');
		$method = strtoupper(trim((string) $method));
		$path = '/'.ltrim((string) $path, '/');

		if (! preg_match('#^https?://[a-z0-9._-]+(?::[0-9]{1,5})?$#i', $baseUrl)
			|| ! in_array($method, array('GET', 'POST'), TRUE)
			|| strpos($path, '..') !== FALSE
			|| preg_match('/[\r\n]/', $path)) {
			return array('status' => 400, 'body' => '', 'headers' => array());
		}

		$options = array(
			'http' => array(
				'method' => $method,
				'header' => "Accept: application/json\r\nConnection: close",
				'ignore_errors' => TRUE,
				'timeout' => max(1, min(15, (int) $timeout))
			)
		);

		if ($method === 'POST') {
			$options['http']['header'] .= "\r\nContent-Type: application/json";
			$options['http']['content'] = (string) $body;
		}

		$response = @file_get_contents($baseUrl.$path, FALSE, stream_context_create($options));
		$responseHeaders = isset($http_response_header) ? $http_response_header : array();
		$statusCode = $response === FALSE ? 502 : 200;

		if (! empty($responseHeaders[0]) && preg_match('/\s(\d{3})(?:\s|$)/', $responseHeaders[0], $matches)) {
			$statusCode = (int) $matches[1];
		}

		return array(
			'status' => $statusCode,
			'body' => $response === FALSE ? '' : $response,
			'headers' => $responseHeaders
		);
	}

	protected function normalizeJobSeekerEnvironment($environment) {
		$value = strtoupper(trim((string) $environment));
		if ($value === '') {
			return '';
		}

		$value = preg_replace('/\s+/', '_', $value);
		$aliases = array(
			'QAS' => 'QA',
			'PRD' => 'PROD',
			'PRODUCTION' => 'PROD',
			'HOMOLOG' => 'HML',
			'HOMOLOGATION' => 'HML'
		);

		return isset($aliases[$value]) ? $aliases[$value] : $value;
	}

	/**
	 * Browser preferences must not leak between users sharing a browser. Keep
	 * the user identifier in the cookie name so server-rendered views resolve
	 * the same environment as the user-scoped localStorage key.
	 */
	protected function jobSeekerUserPreferenceCookieName($preference) {
		$userId = preg_replace('/[^0-9]/', '', (string) $this->vendorId);
		if ($userId === '') {
			$userId = 'anonymous';
		}

		return 'jobseeker_'.preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $preference)).'_user_'.$userId;
	}

	protected function jobSeekerEnvironmentPreference() {
		return trim((string) $this->input->cookie($this->jobSeekerUserPreferenceCookieName('global_environment'), TRUE));
	}

	protected function checkJenkinsEnvironmentSlots($jobName, $environment) {
		$environment = $this->normalizeJobSeekerEnvironment($environment);

		if ($environment === '' || $environment === '0' || $environment === 'ALL' || $environment === 'UNKNOWN') {
			return array('ok' => TRUE, 'environment' => $environment, 'limit' => 0, 'running' => 0, 'queued' => 0, 'active' => 0, 'message' => 'No concrete runtime environment was selected.');
		}

		$configuredLimit = $this->jenkinsEnvironmentSlotLimit($environment);
		$agentCapacity = $this->jenkinsOnlineEnvironmentAgentCapacity($environment);
		$limit = $configuredLimit < 1 ? 0 : $configuredLimit + (int) $agentCapacity['executors'];
		if ($limit < 1) {
			return array('ok' => TRUE, 'environment' => $environment, 'limit' => $limit, 'running' => 0, 'queued' => 0, 'active' => 0, 'message' => 'Environment slot limit is disabled.');
		}

		$usage = $this->jenkinsEnvironmentSlotUsage();
		if (! $usage['ok']) {
			return array('ok' => FALSE, 'status' => 503, 'environment' => $environment, 'limit' => $limit, 'running' => 0, 'queued' => 0, 'active' => 0, 'message' => $usage['message']);
		}

		$environmentUsage = isset($usage['environments'][$environment]) ? $usage['environments'][$environment] : array('running' => 0, 'queued' => 0, 'active' => 0);
		$active = (int) $environmentUsage['active'];

		if ($active >= $limit) {
			return array(
				'ok' => FALSE,
				'status' => 429,
				'environment' => $environment,
				'limit' => $limit,
				'running' => (int) $environmentUsage['running'],
				'queued' => (int) $environmentUsage['queued'],
				'active' => $active,
				'message' => $environment.' environment slots are full ('.$active.'/'.$limit.' active: '.(int) $environmentUsage['running'].' running, '.(int) $environmentUsage['queued'].' queued). Wait for a '.$environment.' job to finish before starting another one.'
			);
		}

		return array(
			'ok' => TRUE,
			'status' => 200,
			'environment' => $environment,
			'limit' => $limit,
			'running' => (int) $environmentUsage['running'],
			'queued' => (int) $environmentUsage['queued'],
			'active' => $active,
			'message' => $environment.' environment slots available ('.$active.'/'.$limit.' active).'
		);
	}

	protected function checkJenkinsEnvironmentSlotsForBuildRequest($path, $body = '') {
		$jobName = $this->jenkinsJobNameFromBuildPath($path);
		if ($jobName === FALSE) {
			return array('ok' => TRUE, 'message' => 'Not a Jenkins build request.');
		}

		$environment = $this->jenkinsEnvironmentFromBuildRequest($jobName, $path, $body);

		return $this->checkJenkinsEnvironmentSlots($jobName, $environment);
	}

	protected function requestJenkinsBuild($path, $body = '', $contentType = NULL) {
		$jobName = $this->jenkinsJobNameFromBuildPath($path);
		if ($jobName === FALSE) {
			return NULL;
		}

		$slotCheck = $this->checkJenkinsEnvironmentSlotsForBuildRequest($path, $body);
		if (! $slotCheck['ok']) {
			return array(
				'status' => isset($slotCheck['status']) ? (int) $slotCheck['status'] : 429,
				'content_type' => 'text/plain',
				'body' => $slotCheck['message'],
				'headers' => array()
			);
		}

		$routingCheck = $this->ensureJenkinsEnvironmentAgentAssignmentForBuildRequest($path, $body);
		if (! $routingCheck['ok']) {
			return array(
				'status' => isset($routingCheck['status']) ? (int) $routingCheck['status'] : 502,
				'content_type' => 'text/plain',
				'body' => $routingCheck['message'],
				'headers' => array()
			);
		}

		return $this->requestJenkins('POST', $path, $body, $contentType);
	}

	protected function ensureJenkinsEnvironmentAgentAssignmentForBuildRequest($path, $body = '') {
		$jobName = $this->jenkinsJobNameFromBuildPath($path);
		if ($jobName === FALSE) {
			return array('ok' => TRUE, 'updated' => FALSE, 'message' => 'Not a Jenkins build request.');
		}

		$environment = $this->jenkinsEnvironmentFromBuildRequest($jobName, $path, $body);
		return $this->ensureJenkinsJobEnvironmentAgentAssignment($jobName, $environment);
	}

	protected function ensureJenkinsJobEnvironmentAgentAssignment($jobName, $environment) {
		$jobName = trim((string) $jobName);
		$environment = $this->normalizeJobSeekerEnvironment($environment);
		$agentCapacity = $this->jenkinsOnlineEnvironmentAgentCapacity($environment);
		$configuredAgentLabel = $agentCapacity['label'];
		$agentLabel = (int) $agentCapacity['executors'] > 0 ? $configuredAgentLabel : '';

		if ($jobName === '') {
			return array('ok' => TRUE, 'updated' => FALSE, 'environment' => $environment, 'agentLabel' => $agentLabel, 'message' => 'No Jenkins job was selected.');
		}

		$jobPath = $this->jenkinsEncodedJobPath($jobName);
		$response = $this->requestJenkins('GET', $jobPath.'/config.xml');

		if ((int) $response['status'] !== 200) {
			return array('ok' => FALSE, 'updated' => FALSE, 'status' => (int) $response['status'], 'message' => 'Unable to read Jenkins job configuration before build. HTTP '.$response['status'].'.');
		}

		$dom = new DOMDocument();
		$previousErrors = libxml_use_internal_errors(TRUE);
		$loaded = $dom->loadXML($response['body']);
		libxml_clear_errors();
		libxml_use_internal_errors($previousErrors);

		if (! $loaded || ! $dom->documentElement) {
			return array('ok' => FALSE, 'updated' => FALSE, 'status' => 422, 'message' => 'Jenkins job configuration is not valid XML.');
		}

		$updates = 0;
		$routingUpdated = FALSE;
		$commandUpdates = 0;
		$root = $dom->documentElement;

		if ($agentLabel !== '') {
			$assignedNode = $this->jenkinsDirectChildElement($root, 'assignedNode');
			$canRoam = $this->jenkinsDirectChildElement($root, 'canRoam');

			if (! $assignedNode) {
				$assignedNode = $dom->createElement('assignedNode');
				$root->appendChild($assignedNode);
				$updates++;
				$routingUpdated = TRUE;
			}

			if ($assignedNode->nodeValue !== $agentLabel) {
				while ($assignedNode->firstChild) {
					$assignedNode->removeChild($assignedNode->firstChild);
				}
				$assignedNode->appendChild($dom->createTextNode($agentLabel));
				$updates++;
				$routingUpdated = TRUE;
			}

			if (! $canRoam) {
				$canRoam = $dom->createElement('canRoam');
				$root->appendChild($canRoam);
				$updates++;
				$routingUpdated = TRUE;
			}

			if ($canRoam->nodeValue !== 'false') {
				while ($canRoam->firstChild) {
					$canRoam->removeChild($canRoam->firstChild);
				}
				$canRoam->appendChild($dom->createTextNode('false'));
				$updates++;
				$routingUpdated = TRUE;
			}
		} else if ($configuredAgentLabel !== '') {
			$assignedNode = $this->jenkinsDirectChildElement($root, 'assignedNode');
			$canRoam = $this->jenkinsDirectChildElement($root, 'canRoam');

			if ($assignedNode && trim((string) $assignedNode->nodeValue) === $configuredAgentLabel) {
				while ($assignedNode->firstChild) {
					$assignedNode->removeChild($assignedNode->firstChild);
				}
				$updates++;
				$routingUpdated = TRUE;
			}

			if ($routingUpdated && $canRoam && $canRoam->nodeValue !== 'true') {
				while ($canRoam->firstChild) {
					$canRoam->removeChild($canRoam->firstChild);
				}
				$canRoam->appendChild($dom->createTextNode('true'));
				$updates++;
			}
		}

		$commandUpdates = $this->ensureJenkinsPythonShellCommandsAreUnbuffered($dom);
		$updates += $commandUpdates;

		if ($updates === 0) {
			$message = $agentLabel !== '' ? 'Jenkins job already targets the online environment agent.' : 'No matching environment agent is online; the Jenkins job can use the controller.';
			return array('ok' => TRUE, 'updated' => FALSE, 'environment' => $environment, 'agentLabel' => $agentLabel, 'message' => $message);
		}

		$saveResponse = $this->requestJenkins('POST', $jobPath.'/config.xml', $dom->saveXML(), 'text/xml');
		$ok = in_array((int) $saveResponse['status'], array(200, 201, 302, 303), TRUE);
		$messageParts = array();

		if ($routingUpdated) {
			$messageParts[] = $agentLabel !== '' ? 'Jenkins job routed to '.$agentLabel.'.' : 'The environment agent is offline; Jenkins job routing returned to the controller.';
		}

		if ($commandUpdates > 0) {
			$messageParts[] = 'Jenkins Python command updated for live output.';
		}

		if (empty($messageParts)) {
			$messageParts[] = 'Jenkins job configuration updated before build.';
		}

		return array(
			'ok' => $ok,
			'updated' => $ok,
			'status' => (int) $saveResponse['status'],
			'environment' => $environment,
			'agentLabel' => $agentLabel,
			'message' => $ok ? implode(' ', $messageParts) : 'Unable to update Jenkins job configuration before build. HTTP '.$saveResponse['status'].'.'
		);
	}

	private function ensureJenkinsPythonShellCommandsAreUnbuffered($dom) {
		$updates = 0;

		foreach ($dom->getElementsByTagName('command') as $commandNode) {
			$command = $commandNode->textContent;
			$updatedCommand = $this->unbufferJenkinsPythonShellCommand($command);

			if ($updatedCommand === $command) {
				continue;
			}

			while ($commandNode->firstChild) {
				$commandNode->removeChild($commandNode->firstChild);
			}
			$commandNode->appendChild($dom->createTextNode($updatedCommand));
			$updates++;
		}

		return $updates;
	}

	private function unbufferJenkinsPythonShellCommand($command) {
		$command = (string) $command;

		if (strpos($command, 'JOBSEEKER_SCRIPT_PATH') === FALSE && strpos($command, 'JOBSEEKER_ENTRYPOINT') === FALSE) {
			return $command;
		}

		$updated = $command;

		if (strpos($updated, 'PYTHONUNBUFFERED') === FALSE) {
			$updated = $this->addPythonUnbufferedExport($updated);
		}

		$updated = str_replace('python3 "$JOBSEEKER_SCRIPT_PATH"', 'python3 -u "$JOBSEEKER_SCRIPT_PATH"', $updated);
		$updated = str_replace('"$JOBSEEKER_PYTHON" "$JOBSEEKER_SCRIPT_PATH"', '"$JOBSEEKER_PYTHON" -u "$JOBSEEKER_SCRIPT_PATH"', $updated);
		$updated = str_replace('python "$JOBSEEKER_ENTRYPOINT" "$@"', 'python -u "$JOBSEEKER_ENTRYPOINT" "$@"', $updated);
		$updated = $this->addPythonUnbufferedDockerEnv($updated);

		return $updated;
	}

	private function addPythonUnbufferedExport($command) {
		$count = 0;
		$updated = preg_replace('/(^export JOBSEEKER_PYTHON=.*$)/m', "$1\nexport PYTHONUNBUFFERED=1", $command, 1, $count);

		if ($count > 0) {
			return $updated;
		}

		$updated = preg_replace('/(^set -e$)/m', "$1\nexport PYTHONUNBUFFERED=1", $command, 1, $count);

		if ($count > 0) {
			return $updated;
		}

		return "export PYTHONUNBUFFERED=1\n".$command;
	}

	private function addPythonUnbufferedDockerEnv($command) {
		if (strpos($command, '-e PYTHONUNBUFFERED') !== FALSE || strpos($command, 'JOBSEEKER_DOCKER_ENTRYPOINT') === FALSE) {
			return $command;
		}

		$lines = preg_split('/\r\n|\r|\n/', $command);
		$updated = array();
		$inserted = FALSE;

		foreach ($lines as $line) {
			$updated[] = $line;

			if (! $inserted && strpos($line, '-e "JOBSEEKER_ENTRYPOINT=$JOBSEEKER_DOCKER_ENTRYPOINT"') !== FALSE) {
				preg_match('/^(\s*)/', $line, $matches);
				$indent = isset($matches[1]) ? $matches[1] : '';
				$continuation = substr(rtrim($line), -1) === '\\' ? ' \\' : '';
				$updated[] = $indent.'-e PYTHONUNBUFFERED'.$continuation;
				$inserted = TRUE;
			}
		}

		return $inserted ? implode("\n", $updated) : $command;
	}

	protected function jenkinsEnvironmentSlotStatus($environment = '') {
		$usage = $this->jenkinsEnvironmentSlotUsage();
		$limits = array();
		$configuredLimits = $this->jenkinsEnvironmentSlotLimits();
		$defaultLimit = isset($configuredLimits['DEFAULT']) ? (int) $configuredLimits['DEFAULT'] : 1;
		$requestedEnvironment = $this->normalizeJobSeekerEnvironment($environment);

		if (! $usage['ok']) {
			return $usage;
		}

		foreach ($configuredLimits as $key => $limit) {
			if ($key !== 'DEFAULT' && ! isset($usage['environments'][$key])) {
				$usage['environments'][$key] = array('running' => 0, 'queued' => 0, 'active' => 0);
			}
		}

		foreach ($usage['environments'] as $key => $row) {
			$limits[$key] = isset($configuredLimits[$key]) ? (int) $configuredLimits[$key] : $defaultLimit;
		}

		if ($requestedEnvironment !== '' && $requestedEnvironment !== 'ALL' && $requestedEnvironment !== 'UNKNOWN') {
			if (! isset($usage['environments'][$requestedEnvironment])) {
				$usage['environments'][$requestedEnvironment] = array('running' => 0, 'queued' => 0, 'active' => 0);
			}
			$limits[$requestedEnvironment] = isset($configuredLimits[$requestedEnvironment]) ? (int) $configuredLimits[$requestedEnvironment] : $defaultLimit;
		}

		foreach ($limits as $key => $limit) {
			if (! isset($usage['environments'][$key])) {
				$usage['environments'][$key] = array('running' => 0, 'queued' => 0, 'active' => 0);
			}
			$usage['environments'][$key]['limit'] = $limit;
			$usage['environments'][$key]['available'] = $limit < 1 ? NULL : max(0, $limit - (int) $usage['environments'][$key]['active']);
		}

		ksort($usage['environments']);
		$usage['defaultLimit'] = $defaultLimit;
		$usage['environmentAgentsEnabled'] = $this->jenkinsEnvironmentAgentsEnabled();
		$usage['environmentAgentLabels'] = $this->jenkinsEnvironmentAgentLabels();
		$usage['ok'] = TRUE;
		return $usage;
	}

	protected function jenkinsEnvironmentSlotLimit($environment) {
		$environment = $this->normalizeJobSeekerEnvironment($environment);
		$limits = $this->jenkinsEnvironmentSlotLimits();

		if (isset($limits[$environment])) {
			return (int) $limits[$environment];
		}

		return isset($limits['DEFAULT']) ? (int) $limits['DEFAULT'] : 1;
	}

	private function jenkinsEnvironmentSlotLimits() {
		$defaultLimit = 1;
		$defaultOverride = getenv('JOBSEEKER_JENKINS_DEFAULT_ENVIRONMENT_SLOTS');

		if ($defaultOverride !== FALSE && preg_match('/^\d+$/', (string) $defaultOverride)) {
			$defaultLimit = (int) $defaultOverride;
		}

		$limits = array('DEFAULT' => $defaultLimit);
		$config = $this->getRuntimeConfig();

		if (isset($config->jenkins->environment_slots)) {
			$this->mergeJenkinsEnvironmentSlotConfig($limits, $config->jenkins->environment_slots);
		}

		if (isset($config->jenkins->environmentSlots)) {
			$this->mergeJenkinsEnvironmentSlotConfig($limits, $config->jenkins->environmentSlots);
		}

		$envConfig = getenv('JOBSEEKER_JENKINS_ENVIRONMENT_SLOTS');
		if ($envConfig !== FALSE && trim($envConfig) !== '') {
			$this->mergeJenkinsEnvironmentSlotConfig($limits, $envConfig);
		}

		return $limits;
	}

	protected function jenkinsEnvironmentAgentLabel($environment) {
		$environment = $this->normalizeJobSeekerEnvironment($environment);
		if ($environment === '' || $environment === '0' || $environment === 'ALL' || $environment === 'UNKNOWN' || ! $this->jenkinsEnvironmentAgentsEnabled()) {
			return '';
		}

		$labels = $this->jenkinsEnvironmentAgentLabels();
		return isset($labels[$environment]) && trim((string) $labels[$environment]) !== '' ? trim((string) $labels[$environment]) : 'jobseeker-env-'.strtolower($environment);
	}

	protected function jenkinsOnlineEnvironmentAgentCapacity($environment) {
		$environment = $this->normalizeJobSeekerEnvironment($environment);
		$agentLabel = $this->jenkinsEnvironmentAgentLabel($environment);
		if ($agentLabel === '') {
			return array('label' => '', 'nodes' => 0, 'executors' => 0);
		}

		if (isset($this->jenkinsOnlineEnvironmentAgentCapacityCache[$environment])) {
			return $this->jenkinsOnlineEnvironmentAgentCapacityCache[$environment];
		}

		$capacity = array('label' => $agentLabel, 'nodes' => 0, 'executors' => 0);
		$response = $this->requestJenkins('GET', 'computer/api/json?tree=computer[offline,numExecutors,assignedLabels[name]]');
		if ((int) $response['status'] === 200) {
			$payload = json_decode($response['body']);
			foreach (is_object($payload) && isset($payload->computer) && is_array($payload->computer) ? $payload->computer : array() as $node) {
				if (! is_object($node) || ! empty($node->offline) || ! in_array($agentLabel, $this->jenkinsNodeLabelNames($node), TRUE)) {
					continue;
				}
				$capacity['nodes']++;
				$capacity['executors'] += isset($node->numExecutors) ? max(0, (int) $node->numExecutors) : 0;
			}
		} else {
			log_message('error', 'Unable to inspect Jenkins agents for '.$environment.'. HTTP '.$response['status'].'.');
		}

		$this->jenkinsOnlineEnvironmentAgentCapacityCache[$environment] = $capacity;
		return $capacity;
	}

	private function jenkinsEnvironmentAgentsEnabled() {
		$envValue = getenv('JOBSEEKER_JENKINS_ENVIRONMENT_AGENTS_ENABLED');
		if ($envValue !== FALSE) {
			return $this->jenkinsBooleanValue($envValue);
		}

		$config = $this->getRuntimeConfig();
		if (isset($config->jenkins->environment_agents_enabled)) {
			return $this->jenkinsBooleanValue($config->jenkins->environment_agents_enabled);
		}

		if (isset($config->jenkins->environmentAgentsEnabled)) {
			return $this->jenkinsBooleanValue($config->jenkins->environmentAgentsEnabled);
		}

		return FALSE;
	}

	private function jenkinsBooleanValue($value) {
		if (is_bool($value)) {
			return $value;
		}

		return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on', 'enabled'), TRUE);
	}

	private function jenkinsEnvironmentAgentLabels() {
		$labels = array(
			'DEV' => 'jobseeker-env-dev',
			'QA' => 'jobseeker-env-qa',
			'UAT' => 'jobseeker-env-uat',
			'PROD' => 'jobseeker-env-prod'
		);
		$config = $this->getRuntimeConfig();

		if (isset($config->jenkins->environment_agent_labels)) {
			$this->mergeJenkinsEnvironmentAgentLabelConfig($labels, $config->jenkins->environment_agent_labels);
		}

		if (isset($config->jenkins->environmentAgentLabels)) {
			$this->mergeJenkinsEnvironmentAgentLabelConfig($labels, $config->jenkins->environmentAgentLabels);
		}

		$envConfig = getenv('JOBSEEKER_JENKINS_ENVIRONMENT_AGENT_LABELS');
		if ($envConfig !== FALSE && trim($envConfig) !== '') {
			$this->mergeJenkinsEnvironmentAgentLabelConfig($labels, $envConfig);
		}

		return $labels;
	}

	private function mergeJenkinsEnvironmentAgentLabelConfig(&$labels, $config) {
		if (is_string($config)) {
			$trimmed = trim($config);
			if ($trimmed === '') {
				return;
			}

			if ($trimmed[0] === '{') {
				$decoded = json_decode($trimmed, TRUE);
				if (is_array($decoded)) {
					$this->mergeJenkinsEnvironmentAgentLabelConfig($labels, $decoded);
				}
				return;
			}

			foreach (preg_split('/[,;]+/', $trimmed) as $pair) {
				$parts = explode('=', $pair, 2);
				if (count($parts) === 2) {
					$this->setJenkinsEnvironmentAgentLabel($labels, $parts[0], $parts[1]);
				}
			}
			return;
		}

		if (is_object($config)) {
			$config = get_object_vars($config);
		}

		if (! is_array($config)) {
			return;
		}

		foreach ($config as $environment => $label) {
			$this->setJenkinsEnvironmentAgentLabel($labels, $environment, $label);
		}
	}

	private function setJenkinsEnvironmentAgentLabel(&$labels, $environment, $label) {
		$environment = $this->normalizeJobSeekerEnvironment($environment);
		$label = trim((string) $label);

		if ($environment === '' || $environment === '0' || $environment === 'ALL' || $environment === 'UNKNOWN' || $label === '') {
			return;
		}

		$labels[$environment] = $label;
	}

	private function jenkinsNodeLabelNames($node) {
		$labels = array();

		foreach (isset($node->assignedLabels) && is_array($node->assignedLabels) ? $node->assignedLabels : array() as $label) {
			if (isset($label->name) && trim((string) $label->name) !== '') {
				$labels[] = trim((string) $label->name);
			}
		}

		return array_values(array_unique($labels));
	}

	private function jenkinsEnvironmentFromNodeLabels($labels, $nodeName = '') {
		$configuredLabels = $this->jenkinsEnvironmentAgentLabels();

		foreach ($configuredLabels as $environment => $configuredLabel) {
			foreach ($labels as $label) {
				if ($label === $configuredLabel) {
					return $environment;
				}
			}
		}

		$environment = $this->detectEnvironmentFromJenkinsJobName(implode(' ', $labels).' '.$nodeName);
		return $environment !== '' ? $environment : '';
	}

	protected function jenkinsExecutorMonitorStatus($environment = '') {
		$slotStatus = $this->jenkinsEnvironmentSlotStatus($environment);
		if (! $slotStatus['ok']) {
			return $slotStatus;
		}

		$computerResponse = $this->requestJenkins('GET', 'computer/api/json?tree=computer[displayName,offline,temporarilyOffline,numExecutors,assignedLabels[name],executors[number,idle,currentExecutable[number,url,fullDisplayName,building,result,builtOn,actions[parameters[name,value]]]]]');
		if ((int) $computerResponse['status'] !== 200) {
			$slotStatus['ok'] = FALSE;
			$slotStatus['status'] = (int) $computerResponse['status'];
			$slotStatus['message'] = 'Unable to inspect Jenkins executors. HTTP '.$computerResponse['status'].'.';
			return $slotStatus;
		}

		$queueResponse = $this->requestJenkins('GET', 'queue/api/json?tree=items[id,why,cancelled,params,task[name,fullName],actions[parameters[name,value]]]');
		if ((int) $queueResponse['status'] !== 200) {
			$slotStatus['ok'] = FALSE;
			$slotStatus['status'] = (int) $queueResponse['status'];
			$slotStatus['message'] = 'Unable to inspect the Jenkins queue. HTTP '.$queueResponse['status'].'.';
			return $slotStatus;
		}

		$computerPayload = json_decode($computerResponse['body']);
		$queuePayload = json_decode($queueResponse['body']);
		if (! is_object($computerPayload) || ! is_object($queuePayload)) {
			$slotStatus['ok'] = FALSE;
			$slotStatus['status'] = 502;
			$slotStatus['message'] = 'Jenkins returned an invalid executor monitor payload.';
			return $slotStatus;
		}

		$executors = array();
		$nodes = array();
		$agentCapacity = array();
		$queueItems = array();
		$global = array('totalExecutors' => 0, 'busyExecutors' => 0, 'idleExecutors' => 0, 'offlineNodes' => 0, 'onlineNodes' => 0, 'agentNodes' => 0, 'onlineAgentNodes' => 0);

		foreach (isset($computerPayload->computer) && is_array($computerPayload->computer) ? $computerPayload->computer : array() as $node) {
			$nodeName = isset($node->displayName) && $node->displayName !== '' ? (string) $node->displayName : 'Jenkins node';
			$isOffline = isset($node->offline) && $node->offline === TRUE;
			$isTemporarilyOffline = isset($node->temporarilyOffline) && $node->temporarilyOffline === TRUE;
			$nodeLabels = $this->jenkinsNodeLabelNames($node);
			$nodeEnvironment = $this->jenkinsEnvironmentFromNodeLabels($nodeLabels, $nodeName);
			$nodeExecutors = isset($node->numExecutors) ? (int) $node->numExecutors : (isset($node->executors) && is_array($node->executors) ? count($node->executors) : 0);
			$nodeBusyExecutors = 0;
			$nodeIdleExecutors = 0;
			$isControllerNode = in_array($nodeName, array('Built-In Node', 'Jenkins node', 'master'), TRUE);

			if ($isOffline) {
				$global['offlineNodes'] += 1;
			} else {
				$global['onlineNodes'] += 1;
				$global['totalExecutors'] += $nodeExecutors;
			}

			if (! $isControllerNode) {
				$global['agentNodes'] += 1;
				if (! $isOffline) {
					$global['onlineAgentNodes'] += 1;
				}
			}

			foreach (isset($node->executors) && is_array($node->executors) ? $node->executors : array() as $executorIndex => $executor) {
				$isIdle = ! isset($executor->idle) || $executor->idle === TRUE;
				$executable = isset($executor->currentExecutable) && is_object($executor->currentExecutable) ? $executor->currentExecutable : NULL;
				$jobName = $this->jenkinsJobNameFromExecutable($executable);
				$executorEnvironment = $executable ? $this->jenkinsParameterValueFromActions($executable, 'ENVIRONMENT') : '';
				if ($executorEnvironment === '' && $jobName !== '') {
					$executorEnvironment = $this->jenkinsEnvironmentFromJobConfig($jobName);
				}
				if ($executorEnvironment === '') {
					$executorEnvironment = $this->detectEnvironmentFromJenkinsJobName($jobName);
				}
				if ($executorEnvironment === '') {
					$executorEnvironment = $nodeEnvironment;
				}

				if (! $isOffline) {
					if ($isIdle) {
						$global['idleExecutors'] += 1;
						$nodeIdleExecutors += 1;
					} else {
						$global['busyExecutors'] += 1;
						$nodeBusyExecutors += 1;
					}
				}

				$executors[] = array(
					'node' => $nodeName,
					'controller' => $isControllerNode,
					'executor' => isset($executor->number) ? (int) $executor->number : (int) $executorIndex,
					'offline' => $isOffline,
					'temporarilyOffline' => $isTemporarilyOffline,
					'idle' => $isIdle,
					'job' => $jobName,
					'build' => $executable && isset($executable->fullDisplayName) ? (string) $executable->fullDisplayName : '',
					'url' => $executable && isset($executable->url) ? (string) $executable->url : '',
					'environment' => $this->normalizeJobSeekerEnvironment($executorEnvironment),
					'labels' => $nodeLabels
				);
			}

			if ($nodeEnvironment !== '') {
				if (! isset($agentCapacity[$nodeEnvironment])) {
					$agentCapacity[$nodeEnvironment] = array('agentNodes' => 0, 'onlineAgentNodes' => 0, 'agentExecutors' => 0, 'onlineAgentExecutors' => 0, 'busyAgentExecutors' => 0, 'availableAgentExecutors' => 0, 'offlineAgentNodes' => 0);
				}

				$agentCapacity[$nodeEnvironment]['agentNodes'] += 1;
				$agentCapacity[$nodeEnvironment]['agentExecutors'] += $nodeExecutors;
				$agentCapacity[$nodeEnvironment]['busyAgentExecutors'] += $nodeBusyExecutors;

				if ($isOffline) {
					$agentCapacity[$nodeEnvironment]['offlineAgentNodes'] += 1;
				} else {
					$agentCapacity[$nodeEnvironment]['onlineAgentNodes'] += 1;
					$agentCapacity[$nodeEnvironment]['onlineAgentExecutors'] += $nodeExecutors;
					$agentCapacity[$nodeEnvironment]['availableAgentExecutors'] += $nodeIdleExecutors;
				}
			}

			$nodes[] = array(
				'node' => $nodeName,
				'controller' => $isControllerNode,
				'environment' => $nodeEnvironment,
				'labels' => $nodeLabels,
				'offline' => $isOffline,
				'temporarilyOffline' => $isTemporarilyOffline,
				'executors' => $nodeExecutors,
				'busyExecutors' => $nodeBusyExecutors,
				'idleExecutors' => $isOffline ? 0 : $nodeIdleExecutors,
				'availableExecutors' => $isOffline ? 0 : $nodeIdleExecutors
			);
		}

		foreach ($agentCapacity as $environmentName => $capacity) {
			if (! isset($slotStatus['environments'][$environmentName])) {
				$slotStatus['environments'][$environmentName] = array('running' => 0, 'queued' => 0, 'active' => 0, 'limit' => 0, 'available' => NULL);
			}

			$slotStatus['environments'][$environmentName] = array_merge($slotStatus['environments'][$environmentName], $capacity);
		}

		foreach ($slotStatus['environments'] as $environmentName => $row) {
			foreach (array('agentNodes', 'onlineAgentNodes', 'agentExecutors', 'onlineAgentExecutors', 'busyAgentExecutors', 'availableAgentExecutors', 'offlineAgentNodes') as $field) {
				if (! isset($slotStatus['environments'][$environmentName][$field])) {
					$slotStatus['environments'][$environmentName][$field] = 0;
				}
			}

			$configuredLimit = isset($row['limit']) ? (int) $row['limit'] : 0;
			$slotStatus['environments'][$environmentName]['configuredLimit'] = $configuredLimit;
			if (! empty($slotStatus['environmentAgentsEnabled']) && $configuredLimit > 0) {
				$effectiveLimit = $configuredLimit + (int) $slotStatus['environments'][$environmentName]['onlineAgentExecutors'];
				$slotStatus['environments'][$environmentName]['limit'] = $effectiveLimit;
				$slotStatus['environments'][$environmentName]['available'] = max(0, $effectiveLimit - (int) $row['active']);
			}
		}

		foreach (isset($queuePayload->items) && is_array($queuePayload->items) ? $queuePayload->items : array() as $item) {
			if (isset($item->cancelled) && $item->cancelled) {
				continue;
			}

			$taskName = '';
			if (isset($item->task) && isset($item->task->fullName)) {
				$taskName = (string) $item->task->fullName;
			} else if (isset($item->task) && isset($item->task->name)) {
				$taskName = (string) $item->task->name;
			}

			$queueItems[] = array(
				'id' => isset($item->id) ? (int) $item->id : 0,
				'job' => $taskName,
				'environment' => $this->normalizeJobSeekerEnvironment($this->jenkinsEnvironmentFromQueueItem($item, array())),
				'why' => isset($item->why) ? (string) $item->why : ''
			);
		}

		$slotStatus['status'] = 200;
		$slotStatus['message'] = 'Jenkins executor monitor loaded.';
		$slotStatus['global'] = $global;
		$slotStatus['nodes'] = $nodes;
		$slotStatus['executors'] = $executors;
		$slotStatus['queue'] = $queueItems;
		$this->scopeJenkinsExecutorMonitorStatus($slotStatus, $environment);

		return $slotStatus;
	}

	private function jenkinsMonitorEnvironmentMatches($value, $requestedEnvironment) {
		$value = $this->normalizeJobSeekerEnvironment($value);
		$requestedEnvironment = $this->normalizeJobSeekerEnvironment($requestedEnvironment);

		if ($requestedEnvironment === '__UNKNOWN__' || $requestedEnvironment === 'UNKNOWN') {
			return $value === '' || $value === '__UNKNOWN__' || $value === 'UNKNOWN';
		}

		return $value === $requestedEnvironment;
	}

	private function scopeJenkinsExecutorMonitorStatus(&$status, $environment) {
		$requestedEnvironment = $this->normalizeJobSeekerEnvironment($environment);
		if ($requestedEnvironment === '' || $requestedEnvironment === 'ALL') {
			return;
		}

		$environmentKey = $requestedEnvironment;
		$environmentRows = isset($status['environments']) && is_array($status['environments']) ? $status['environments'] : array();
		$environmentRow = isset($environmentRows[$environmentKey]) ? $environmentRows[$environmentKey] : array();
		$controllerHasScopedBuild = FALSE;
		foreach (isset($status['executors']) && is_array($status['executors']) ? $status['executors'] : array() as $executor) {
			if (! empty($executor['controller']) && empty($executor['idle']) && $this->jenkinsMonitorEnvironmentMatches(isset($executor['environment']) ? $executor['environment'] : '', $requestedEnvironment)) {
				$controllerHasScopedBuild = TRUE;
				break;
			}
		}
		$includeControllerCapacity = $requestedEnvironment === 'LOCAL'
			|| empty($status['environmentAgentsEnabled'])
			|| (isset($environmentRow['onlineAgentExecutors']) ? (int) $environmentRow['onlineAgentExecutors'] : 0) < 1
			|| $controllerHasScopedBuild;
		$status['environments'] = array(
			$environmentKey => $environmentRow ? $environmentRow : array(
				'running' => 0,
				'queued' => 0,
				'active' => 0,
				'limit' => isset($status['defaultLimit']) ? (int) $status['defaultLimit'] : 1,
				'available' => isset($status['defaultLimit']) ? (int) $status['defaultLimit'] : 1
			)
		);

		foreach (array('nodes', 'executors', 'queue') as $collection) {
			$rows = isset($status[$collection]) && is_array($status[$collection]) ? $status[$collection] : array();
			$status[$collection] = array_values(array_filter($rows, function($row) use ($requestedEnvironment, $includeControllerCapacity) {
				if (! is_array($row)) {
					return FALSE;
				}

				if ($includeControllerCapacity && ! empty($row['controller'])) {
					return TRUE;
				}

				return $this->jenkinsMonitorEnvironmentMatches(isset($row['environment']) ? $row['environment'] : '', $requestedEnvironment);
			}));
		}

		$global = array('totalExecutors' => 0, 'busyExecutors' => 0, 'idleExecutors' => 0, 'offlineNodes' => 0, 'onlineNodes' => 0, 'agentNodes' => 0, 'onlineAgentNodes' => 0);
		foreach ($status['nodes'] as $node) {
			$offline = ! empty($node['offline']);
			$global[$offline ? 'offlineNodes' : 'onlineNodes']++;
			$isControllerNode = ! empty($node['controller']);
			if (! $isControllerNode) {
				$global['agentNodes']++;
			}
			if (! $offline) {
				if (! $isControllerNode) {
					$global['onlineAgentNodes']++;
				}
				$global['totalExecutors'] += isset($node['executors']) ? (int) $node['executors'] : 0;
			}
		}
		foreach ($status['executors'] as $executor) {
			if (! empty($executor['offline'])) {
				continue;
			}
			if (! empty($executor['idle'])) {
				$global['idleExecutors']++;
			} else {
				$global['busyExecutors']++;
			}
		}
		$status['global'] = $global;

		if (isset($status['environmentAgentLabels']) && is_array($status['environmentAgentLabels'])) {
			$status['environmentAgentLabels'] = isset($status['environmentAgentLabels'][$environmentKey])
				? array($environmentKey => $status['environmentAgentLabels'][$environmentKey])
				: array();
		}
	}

	private function jenkinsJobNameFromExecutable($executable) {
		if (! is_object($executable)) {
			return '';
		}

		if (isset($executable->url)) {
			$path = parse_url((string) $executable->url, PHP_URL_PATH);
			$segments = explode('/', trim((string) $path, '/'));
			$jobSegments = array();

			for ($index = 0; $index < count($segments); $index++) {
				if ($segments[$index] === 'job' && isset($segments[$index + 1])) {
					$jobSegments[] = rawurldecode($segments[$index + 1]);
				}
			}

			if (! empty($jobSegments)) {
				return implode('/', $jobSegments);
			}
		}

		if (isset($executable->fullDisplayName) && preg_match('/^(.*?) #\d+$/', (string) $executable->fullDisplayName, $matches)) {
			return str_replace(' » ', '/', $matches[1]);
		}

		return '';
	}

	private function mergeJenkinsEnvironmentSlotConfig(&$limits, $config) {
		if (is_string($config)) {
			$trimmed = trim($config);
			if ($trimmed === '') {
				return;
			}

			if ($trimmed[0] === '{') {
				$decoded = json_decode($trimmed, TRUE);
				if (is_array($decoded)) {
					$this->mergeJenkinsEnvironmentSlotConfig($limits, $decoded);
				}
				return;
			}

			foreach (preg_split('/[,;]+/', $trimmed) as $pair) {
				$parts = explode('=', $pair, 2);
				if (count($parts) !== 2) {
					continue;
				}

				$this->setJenkinsEnvironmentSlotLimit($limits, $parts[0], $parts[1]);
			}
			return;
		}

		if (is_object($config)) {
			$config = get_object_vars($config);
		}

		if (! is_array($config)) {
			return;
		}

		foreach ($config as $environment => $limit) {
			$this->setJenkinsEnvironmentSlotLimit($limits, $environment, $limit);
		}
	}

	private function setJenkinsEnvironmentSlotLimit(&$limits, $environment, $limit) {
		$environment = strtoupper(trim((string) $environment));
		$key = in_array($environment, array('*', 'ALL', 'DEFAULT'), TRUE) ? 'DEFAULT' : $this->normalizeJobSeekerEnvironment($environment);

		if ($key === '' || ! preg_match('/^\d+$/', trim((string) $limit))) {
			return;
		}

		$limits[$key] = (int) $limit;
	}

	private function jenkinsEnvironmentSlotUsage() {
		$usage = array('ok' => FALSE, 'status' => 0, 'message' => '', 'environments' => array());
		$jobDefaults = array();
		$jobsResponse = $this->requestJenkins('GET', 'api/json?tree='.$this->jenkinsEnvironmentSlotJobTree(3));

		if ((int) $jobsResponse['status'] !== 200) {
			$usage['status'] = (int) $jobsResponse['status'];
			$usage['message'] = 'Unable to inspect Jenkins executors before starting the build. HTTP '.$jobsResponse['status'].'.';
			return $usage;
		}

		$jobsPayload = json_decode($jobsResponse['body']);
		if (! is_object($jobsPayload)) {
			$usage['status'] = 502;
			$usage['message'] = 'Jenkins returned an invalid executor payload.';
			return $usage;
		}

		$this->collectJenkinsEnvironmentSlotJobs(isset($jobsPayload->jobs) ? $jobsPayload->jobs : array(), $usage['environments'], $jobDefaults);

		$queueResponse = $this->requestJenkins('GET', 'queue/api/json?tree=items[id,why,cancelled,params,task[name,fullName],actions[parameters[name,value]]]');
		if ((int) $queueResponse['status'] !== 200) {
			$usage['status'] = (int) $queueResponse['status'];
			$usage['message'] = 'Unable to inspect the Jenkins queue before starting the build. HTTP '.$queueResponse['status'].'.';
			return $usage;
		}

		$queuePayload = json_decode($queueResponse['body']);
		if (! is_object($queuePayload)) {
			$usage['status'] = 502;
			$usage['message'] = 'Jenkins returned an invalid queue payload.';
			return $usage;
		}

		foreach (isset($queuePayload->items) && is_array($queuePayload->items) ? $queuePayload->items : array() as $item) {
			if (isset($item->cancelled) && $item->cancelled) {
				continue;
			}

			$environment = $this->jenkinsEnvironmentFromQueueItem($item, $jobDefaults);
			$this->recordJenkinsEnvironmentSlotUse($usage['environments'], $environment, 'queued');
		}

		$usage['ok'] = TRUE;
		$usage['status'] = 200;
		$usage['message'] = 'Jenkins environment slot usage loaded.';

		return $usage;
	}

	private function jenkinsEnvironmentSlotJobTree($depth) {
		$fields = '_class,name,fullName,color,property[parameterDefinitions[name,defaultParameterValue[value]]],builds[number,building,actions[parameters[name,value]]]{0,20}';

		if ($depth <= 0) {
			return 'jobs['.$fields.']';
		}

		return 'jobs['.$fields.','.$this->jenkinsEnvironmentSlotJobTree($depth - 1).']';
	}

	private function collectJenkinsEnvironmentSlotJobs($jobs, &$environments, &$jobDefaults) {
		foreach (is_array($jobs) ? $jobs : array() as $job) {
			$jobName = isset($job->fullName) && $job->fullName !== '' ? $job->fullName : (isset($job->name) ? $job->name : '');
			$defaultEnvironment = $this->jenkinsEnvironmentFromJobData($job, $jobName);

			if ($jobName !== '' && $defaultEnvironment !== '') {
				$jobDefaults[$jobName] = $defaultEnvironment;
			}

			foreach (isset($job->builds) && is_array($job->builds) ? $job->builds : array() as $build) {
				if (! isset($build->building) || $build->building !== TRUE) {
					continue;
				}

				$environment = $this->jenkinsParameterValueFromActions($build, 'ENVIRONMENT');
				if ($environment === '') {
					$environment = $defaultEnvironment;
				}

				$this->recordJenkinsEnvironmentSlotUse($environments, $environment, 'running');
			}

			if (isset($job->jobs) && is_array($job->jobs)) {
				$this->collectJenkinsEnvironmentSlotJobs($job->jobs, $environments, $jobDefaults);
			}
		}
	}

	protected function jenkinsEnvironmentFromJobData($job, $fallbackJobName = '') {
		$environment = $this->jenkinsEnvironmentFromJobProperty($job);

		if ($environment === '') {
			$environment = $this->detectEnvironmentFromJenkinsJobName($fallbackJobName);
		}

		return $this->normalizeJobSeekerEnvironment($environment);
	}

	private function recordJenkinsEnvironmentSlotUse(&$environments, $environment, $kind) {
		$environment = $this->normalizeJobSeekerEnvironment($environment);
		if ($environment === '' || $environment === '0' || $environment === 'ALL' || $environment === 'UNKNOWN') {
			return;
		}

		if (! isset($environments[$environment])) {
			$environments[$environment] = array('running' => 0, 'queued' => 0, 'active' => 0);
		}

		if ($kind === 'running') {
			$environments[$environment]['running'] += 1;
		} else if ($kind === 'queued') {
			$environments[$environment]['queued'] += 1;
		}

		$environments[$environment]['active'] = (int) $environments[$environment]['running'] + (int) $environments[$environment]['queued'];
	}

	protected function jenkinsParameterValueFromActions($holder, $parameterName) {
		if (! isset($holder->actions) || ! is_array($holder->actions)) {
			return '';
		}

		foreach ($holder->actions as $action) {
			if (! isset($action->parameters) || ! is_array($action->parameters)) {
				continue;
			}

			foreach ($action->parameters as $parameter) {
				if (isset($parameter->name) && $parameter->name === $parameterName && isset($parameter->value)) {
					return (string) $parameter->value;
				}
			}
		}

		return '';
	}

	protected function jenkinsEnvironmentFromJobProperty($job) {
		if (! isset($job->property) || ! is_array($job->property)) {
			return '';
		}

		foreach ($job->property as $property) {
			if (! isset($property->parameterDefinitions) || ! is_array($property->parameterDefinitions)) {
				continue;
			}

			foreach ($property->parameterDefinitions as $definition) {
				if (! isset($definition->name) || $definition->name !== 'ENVIRONMENT') {
					continue;
				}

				if (isset($definition->defaultParameterValue) && isset($definition->defaultParameterValue->value)) {
					return (string) $definition->defaultParameterValue->value;
				}
			}
		}

		return '';
	}

	private function jenkinsEnvironmentFromQueueItem($item, $jobDefaults) {
		$environment = $this->jenkinsParameterValueFromActions($item, 'ENVIRONMENT');

		if ($environment === '' && isset($item->params) && preg_match('/(?:^|\s)ENVIRONMENT=([^\s]+)/', (string) $item->params, $matches)) {
			$environment = $matches[1];
		}

		if ($environment !== '') {
			return $environment;
		}

		$jobName = '';
		if (isset($item->task) && isset($item->task->fullName)) {
			$jobName = $item->task->fullName;
		} else if (isset($item->task) && isset($item->task->name)) {
			$jobName = $item->task->name;
		}

		if ($jobName !== '' && isset($jobDefaults[$jobName])) {
			return $jobDefaults[$jobName];
		}

		return $this->detectEnvironmentFromJenkinsJobName($jobName);
	}

	protected function detectEnvironmentFromJenkinsJobName($jobName) {
		if (preg_match('/(?:^|[\/_\-. ])(DEV|QA|QAS|UAT|PREPROD|HML|HOMOLOG|HOMOLOGATION|PROD|PRD|PRODUCTION)(?:$|[\/_\-. ])/i', (string) $jobName, $matches)) {
			return $this->normalizeJobSeekerEnvironment($matches[1]);
		}

		return '';
	}

	private function jenkinsJobNameFromBuildPath($path) {
		$path = trim((string) $path);
		$pathOnly = explode('?', $path, 2);
		$segments = explode('/', trim($pathOnly[0], '/'));

		if (count($segments) < 3) {
			return FALSE;
		}

		$action = array_pop($segments);
		if ($action !== 'build' && $action !== 'buildWithParameters') {
			return FALSE;
		}

		$jobSegments = array();
		for ($index = 0; $index < count($segments); $index += 2) {
			if ($segments[$index] !== 'job' || ! isset($segments[$index + 1])) {
				return FALSE;
			}

			$jobSegments[] = rawurldecode($segments[$index + 1]);
		}

		return empty($jobSegments) ? FALSE : implode('/', $jobSegments);
	}

	private function jenkinsEnvironmentFromBuildRequest($jobName, $path, $body) {
		$params = array();
		$query = parse_url($path, PHP_URL_QUERY);

		if ($query !== NULL && $query !== FALSE && $query !== '') {
			parse_str($query, $params);
		}

		$bodyParams = array();
		if (trim((string) $body) !== '') {
			parse_str((string) $body, $bodyParams);
		}

		if (isset($bodyParams['ENVIRONMENT'])) {
			return $bodyParams['ENVIRONMENT'];
		}

		if (isset($params['ENVIRONMENT'])) {
			return $params['ENVIRONMENT'];
		}

		$environment = $this->jenkinsEnvironmentFromJobConfig($jobName);
		return $environment !== '' ? $environment : $this->detectEnvironmentFromJenkinsJobName($jobName);
	}

	protected function jenkinsEnvironmentFromJobConfig($jobName) {
		$response = $this->requestJenkins('GET', $this->jenkinsEncodedJobPath($jobName).'/config.xml');
		if ((int) $response['status'] !== 200) {
			return '';
		}

		$dom = new DOMDocument();
		$previousErrors = libxml_use_internal_errors(TRUE);
		$loaded = $dom->loadXML($response['body']);
		libxml_clear_errors();
		libxml_use_internal_errors($previousErrors);

		if (! $loaded) {
			return '';
		}

		foreach ($dom->getElementsByTagName('hudson.model.StringParameterDefinition') as $definition) {
			$name = $this->jenkinsDirectChildText($definition, 'name');
			if ($name === 'ENVIRONMENT') {
				return $this->jenkinsDirectChildText($definition, 'defaultValue');
			}
		}

		return '';
	}

	private function jenkinsDirectChildElement($parent, $tagName) {
		foreach ($parent->childNodes as $child) {
			if ($child->nodeType === XML_ELEMENT_NODE && $child->tagName === $tagName) {
				return $child;
			}
		}

		return NULL;
	}

	private function jenkinsDirectChildText($parent, $tagName) {
		$child = $this->jenkinsDirectChildElement($parent, $tagName);

		return $child ? $child->textContent : '';
	}

	private function jenkinsEncodedJobPath($jobName) {
		$segments = explode('/', trim((string) $jobName, '/'));
		$path = array();

		foreach ($segments as $segment) {
			if ($segment !== '') {
				$path[] = 'job/' . rawurlencode($segment);
			}
		}

		return implode('/', $path);
	}

	protected function repositoryRootPath() {
		$jenkinsHome = isset($this->global['jenkins_home']) ? trim((string) $this->global['jenkins_home']) : '';
		return $jenkinsHome === '' ? FCPATH.'repository' : rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository';
	}

	/**
	 * Connector / data-asset dependency map for a saved job. Returns stored rows
	 * when the job was scanned at creation; otherwise falls back to a live scan
	 * of the Jenkins command plus the job's repository source (not persisted).
	 */
	protected function jobDependencyMap($jobName, $environment) {
		$jobName = trim((string) $jobName);
		$environment = $this->normalizeJobSeekerEnvironment((string) $environment);
		$environment = $environment === '' ? 'ALL' : $environment;
		if ($jobName === '') {
			return array('connectors' => array(), 'datasets' => array(), 'stored' => FALSE);
		}

		$this->load->model('JobDependency_model', 'jobDependencyModel');
		$stored = $this->jobDependencyModel->listForJob($jobName, $environment);
		if (! empty($stored['connectors']) || ! empty($stored['datasets'])) {
			$stored['stored'] = TRUE;
			return $stored;
		}

		$this->load->library('DependencyScanner');
		$sources = array();
		$configResponse = $this->requestJenkins('GET', $this->jenkinsEncodedJobPath($jobName).'/config.xml');
		if ((int) $configResponse['status'] === 200 && preg_match_all('#<command>(.*?)</command>#s', (string) $configResponse['body'], $commandMatches)) {
			foreach ($commandMatches[1] as $command) {
				$sources[] = array('text' => html_entity_decode($command, ENT_QUOTES | ENT_XML1, 'UTF-8'), 'from' => 'command');
			}
		}
		$repositoryRoot = $this->repositoryRootPath();
		foreach (array('python/inline', 'python/jobs', 'bash/jobs', 'batch/jobs', 'talend/jobs') as $location) {
			$relative = $this->safeRelativePath($location.'/'.$jobName);
			if ($relative === FALSE) {
				continue;
			}
			$directory = rtrim($repositoryRoot, '/\\').DIRECTORY_SEPARATOR.$relative;
			if (is_dir($directory)) {
				foreach ($this->dependencyscanner->sourcesForJob('', $directory) as $repoSource) {
					$sources[] = $repoSource;
				}
			}
		}

		$scan = $this->dependencyscanner->scan($sources);
		$resolved = $this->jobDependencyModel->resolve(
			$this->dependencyscanner->keys($scan, 'connectors'),
			$this->dependencyscanner->keys($scan, 'datasets'),
			$environment,
			$jobName
		);
		$resolved['stored'] = FALSE;
		return $resolved;
	}

	protected function getUploadedFile($field, $allowedExtensions = array(), $maxBytes = 104857600) {
		if (empty($_FILES[$field]) || ! is_array($_FILES[$field])) {
			return array('ok' => FALSE, 'message' => 'No file was uploaded.');
		}

		$file = $_FILES[$field];

		if (! isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
			return array('ok' => FALSE, 'message' => $this->uploadErrorMessage(isset($file['error']) ? $file['error'] : NULL));
		}

		if ($maxBytes > 0 && (int) $file['size'] > $maxBytes) {
			return array('ok' => FALSE, 'message' => 'Uploaded file exceeds the maximum allowed size.');
		}

		$originalName = isset($file['name']) ? $file['name'] : '';
		if ($originalName === '' || preg_match('#[\\/]#', $originalName)) {
			return array('ok' => FALSE, 'message' => 'Uploaded file name is invalid.');
		}

		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$allowed = array();
		foreach ((array) $allowedExtensions as $allowedExtension) {
			$allowedExtension = strtolower(ltrim(trim($allowedExtension), '.'));
			if ($allowedExtension !== '') {
				$allowed[] = $allowedExtension;
			}
		}

		if (! empty($allowed) && ! in_array($extension, $allowed, TRUE)) {
			return array('ok' => FALSE, 'message' => 'Uploaded file type is not allowed.');
		}

		$safeName = $this->safeUploadFileName($originalName);
		if ($safeName === FALSE) {
			return array('ok' => FALSE, 'message' => 'Uploaded file name is invalid.');
		}

		if (! is_uploaded_file($file['tmp_name'])) {
			return array('ok' => FALSE, 'message' => 'Uploaded file is invalid.');
		}

		return array(
			'ok' => TRUE,
			'tmp_name' => $file['tmp_name'],
			'original_name' => $originalName,
			'safe_name' => $safeName,
			'extension' => $extension,
			'size' => (int) $file['size']
		);
	}

	protected function safeUploadFileName($name) {
		$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
		$safeName = trim($safeName, '._');

		return $safeName === '' ? FALSE : $safeName;
	}

	protected function safePathSegment($segment) {
		$segment = trim((string) $segment);

		if ($segment === '' || $segment === '.' || $segment === '..' || strpos($segment, "\0") !== FALSE || preg_match('#[\\/]#', $segment)) {
			return FALSE;
		}

		return preg_match('/^[A-Za-z0-9._ -]+$/', $segment) ? $segment : FALSE;
	}

	protected function safeRelativePath($path) {
		$path = trim(str_replace('\\', '/', (string) $path), '/');
		if ($path === '' || strpos($path, "\0") !== FALSE) {
			return FALSE;
		}

		$safeSegments = array();
		foreach (explode('/', $path) as $segment) {
			$safeSegment = $this->safePathSegment($segment);
			if ($safeSegment === FALSE) {
				return FALSE;
			}
			$safeSegments[] = $safeSegment;
		}

		return implode(DIRECTORY_SEPARATOR, $safeSegments);
	}

	protected function ensureDirectory($path) {
		return is_dir($path) || mkdir($path, 0755, TRUE) || is_dir($path);
	}

	protected function pathWithinBase($path, $base) {
		$base = rtrim(str_replace('\\', '/', $base), '/') . '/';
		$path = rtrim(str_replace('\\', '/', $path), '/') . '/';

		return strpos($path, $base) === 0;
	}

	protected function extractZipSafely($zipFile, $destination) {
		if (! $this->ensureDirectory($destination)) {
			return array('ok' => FALSE, 'message' => 'Unable to create extraction directory.');
		}

		$zip = new ZipArchive;
		if ($zip->open($zipFile) !== TRUE) {
			return array('ok' => FALSE, 'message' => 'Uploaded file is not a valid ZIP archive.');
		}

		$realDestination = realpath($destination);
		if ($realDestination === FALSE) {
			$zip->close();
			return array('ok' => FALSE, 'message' => 'Extraction directory is invalid.');
		}

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entryName = $zip->getNameIndex($i);
			$normalizedName = str_replace('\\', '/', $entryName);

			if ($normalizedName === '' || strpos($normalizedName, "\0") !== FALSE || $normalizedName[0] === '/' || preg_match('#(^|/)\.\.($|/)#', $normalizedName)) {
				$zip->close();
				return array('ok' => FALSE, 'message' => 'ZIP archive contains an unsafe path.');
			}

			$entryTarget = $realDestination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedName);
			$targetDirectory = substr($normalizedName, -1) === '/' ? rtrim($entryTarget, DIRECTORY_SEPARATOR) : dirname($entryTarget);

			if (! $this->pathWithinBase($targetDirectory, $realDestination)) {
				$zip->close();
				return array('ok' => FALSE, 'message' => 'ZIP archive contains an unsafe path.');
			}
		}

		if (! $zip->extractTo($realDestination)) {
			$zip->close();
			return array('ok' => FALSE, 'message' => 'Unable to extract ZIP archive.');
		}

		$zip->close();
		return array('ok' => TRUE, 'message' => 'ZIP archive extracted.');
	}

	protected function uploadErrorMessage($errorCode) {
		switch ($errorCode) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return 'Uploaded file exceeds the maximum allowed size.';
			case UPLOAD_ERR_PARTIAL:
				return 'Uploaded file was only partially received.';
			case UPLOAD_ERR_NO_FILE:
				return 'No file was uploaded.';
			default:
				return 'Uploaded file could not be processed.';
		}
	}

	/**
	 * Takes mixed data and optionally a status code, then creates the response
	 *
	 * @access public
	 * @param array|NULL $data
	 *        	Data to output to the user
	 *        	running the script; otherwise, exit
	 */
	public function response($data = NULL) {
		$this->output->set_status_header ( 200 )->set_content_type ( 'application/json', 'utf-8' )->set_output ( json_encode ( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )->_display ();
		exit ();
	}

	/**
	 * This function used to check the user is logged in or not
	 */
	function isLoggedIn() {
		$isLoggedIn = $this->session->userdata ( 'isLoggedIn' );
		
		if (! isset ( $isLoggedIn ) || $isLoggedIn != TRUE) {
			if ($this->input->is_ajax_request()) {
				$this->output
					->set_status_header(401)
					->set_content_type('application/json', 'utf-8')
					->set_output(json_encode(array('status' => FALSE, 'error' => 'Session expired. Please log in again.')))
					->_display();
				exit;
			}

			redirect ( 'login' );
		} else {
			$this->role = $this->session->userdata ( 'role' );
			$this->vendorId = $this->session->userdata ( 'userId' );
			$this->name = $this->session->userdata ( 'name' );
			$this->roleText = $this->session->userdata ( 'roleText' );
			$this->lastLogin = $this->session->userdata ( 'lastLogin' );
			
			$this->global ['name'] = $this->name;
			$this->global ['user_id'] = $this->vendorId;
			$this->global ['role'] = $this->role;
			$this->global ['role_text'] = $this->roleText;
			$this->global ['last_login'] = $this->lastLogin;

			// load json config file
			$jsonToArray = $this->getRuntimeConfig();

			// Load reports with user permision
			$this->load->model('Visualization_model');
			$this->global ['allowedReports'] =$this->Visualization_model->allowedUser($this->name);

			// Set global var to be used on Controllers
			$this->global ['jenkins_enabled'] = $jsonToArray->jenkins->enabled;
			$this->global ['jenkins_url'] = $jsonToArray->jenkins->url;
			$this->global ['jenkins_username'] = '';
			$this->global ['jenkins_token'] = '';
			// The Jenkins credential is never exposed to the browser. All Jenkins
			// traffic goes through the authenticated server-side proxy, which
			// builds its own Authorization header from the runtime config.
			$this->global ['jenkins_home'] = $jsonToArray->jenkins->jenkins_home;


			// Set global var to detect OS Version
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			    $os = "windows";
			} else {
			    $os = "linux";
			}
			$this->global ['os'] = $os;

		}
	}
	
	/**
	 * This function is used to check the access
	 */
	function isAdmin() {
		if ($this->role != ROLE_ADMIN) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * This function is used to check the access
	 */
	function isManager() {
		// Historical callers treat TRUE as "access denied". Both documented
		// job-management roles must therefore return FALSE here.
		if ($this->role != ROLE_ADMIN && $this->role != ROLE_MANAGER) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * This function is used to check the access
	 */
	function isTicketter() {
		if ($this->role != ROLE_ADMIN || $this->role != ROLE_MANAGER) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * This function is used to load the set of views
	 */
	function loadThis() {
		$this->global ['pageTitle'] = 'Job Seeker : Access Denied';
		
		$this->load->view ( 'includes/header', $this->global );
		$this->load->view ( 'access' );
		$this->load->view ( 'includes/footer' );
	}

		
	
	/**
	 * This function is used to logged out user from system
	 */
	function logout() {
		$this->session->sess_destroy ();
		
		redirect ( 'login' );
	}

	/**
     * This function used to load views
     * @param {string} $viewName : This is view name
     * @param {mixed} $headerInfo : This is array of header information
     * @param {mixed} $pageInfo : This is array of page information
     * @param {mixed} $footerInfo : This is array of footer information
     * @return {null} $result : null
     */
    function loadViews($viewName = "", $headerInfo = NULL, $pageInfo = NULL, $footerInfo = NULL){

        $this->load->view('includes/header', $headerInfo);
        $this->load->view($viewName, $pageInfo);
        $this->load->view('includes/footer', $footerInfo);
    }

    function loadViewsSetup($viewName = "", $headerInfo = NULL, $pageInfo = NULL, $footerInfo = NULL){

        $this->load->view('includes/setupHeader', $headerInfo);
        $this->load->view($viewName, $pageInfo);
        $this->load->view('includes/setupFooter', $footerInfo);
    }
	
	/**
	 * This function used provide the pagination resources
	 * @param {string} $link : This is page link
	 * @param {number} $count : This is page count
	 * @param {number} $perPage : This is records per page limit
	 * @return {mixed} $result : This is array of records and pagination data
	 */
	function paginationCompress($link, $count, $perPage = 10, $segment = SEGMENT) {
		$this->load->library ( 'pagination' );

		$config ['base_url'] = base_url () . $link;
		$config ['total_rows'] = $count;
		$config ['uri_segment'] = $segment;
		$config ['per_page'] = $perPage;
		$config ['num_links'] = 5;
		$config ['full_tag_open'] = '<nav><ul class="pagination">';
		$config ['full_tag_close'] = '</ul></nav>';
		$config ['first_tag_open'] = '<li class="arrow">';
		$config ['first_link'] = 'First';
		$config ['first_tag_close'] = '</li>';
		$config ['prev_link'] = 'Previous';
		$config ['prev_tag_open'] = '<li class="arrow">';
		$config ['prev_tag_close'] = '</li>';
		$config ['next_link'] = 'Next';
		$config ['next_tag_open'] = '<li class="arrow">';
		$config ['next_tag_close'] = '</li>';
		$config ['cur_tag_open'] = '<li class="active"><a href="#">';
		$config ['cur_tag_close'] = '</a></li>';
		$config ['num_tag_open'] = '<li>';
		$config ['num_tag_close'] = '</li>';
		$config ['last_tag_open'] = '<li class="arrow">';
		$config ['last_link'] = 'Last';
		$config ['last_tag_close'] = '</li>';
	
		$this->pagination->initialize ( $config );
		$page = $config ['per_page'];
		$segment = $this->uri->segment ( $segment );
	
		return array (
				"page" => $page,
				"segment" => $segment
		);
	}
}
