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

	protected function checkJenkinsEnvironmentSlots($jobName, $environment) {
		$environment = $this->normalizeJobSeekerEnvironment($environment);

		if ($environment === '' || $environment === '0' || $environment === 'ALL' || $environment === 'UNKNOWN') {
			return array('ok' => TRUE, 'environment' => $environment, 'limit' => 0, 'running' => 0, 'queued' => 0, 'active' => 0, 'message' => 'No concrete runtime environment was selected.');
		}

		$limit = $this->jenkinsEnvironmentSlotLimit($environment);
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

	protected function jenkinsExecutorMonitorStatus($environment = '') {
		$slotStatus = $this->jenkinsEnvironmentSlotStatus($environment);
		if (! $slotStatus['ok']) {
			return $slotStatus;
		}

		$computerResponse = $this->requestJenkins('GET', 'computer/api/json?tree=computer[displayName,offline,temporarilyOffline,numExecutors,executors[number,idle,currentExecutable[number,url,fullDisplayName,building,result,actions[parameters[name,value]]]]]');
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
		$queueItems = array();
		$global = array('totalExecutors' => 0, 'busyExecutors' => 0, 'idleExecutors' => 0, 'offlineNodes' => 0);

		foreach (isset($computerPayload->computer) && is_array($computerPayload->computer) ? $computerPayload->computer : array() as $node) {
			$nodeName = isset($node->displayName) && $node->displayName !== '' ? (string) $node->displayName : 'Jenkins node';
			$isOffline = isset($node->offline) && $node->offline === TRUE;
			$nodeExecutors = isset($node->numExecutors) ? (int) $node->numExecutors : (isset($node->executors) && is_array($node->executors) ? count($node->executors) : 0);

			$global['totalExecutors'] += $nodeExecutors;
			if ($isOffline) {
				$global['offlineNodes'] += 1;
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

				if ($isIdle) {
					$global['idleExecutors'] += 1;
				} else {
					$global['busyExecutors'] += 1;
				}

				$executors[] = array(
					'node' => $nodeName,
					'executor' => isset($executor->number) ? (int) $executor->number : (int) $executorIndex,
					'offline' => $isOffline,
					'idle' => $isIdle,
					'job' => $jobName,
					'build' => $executable && isset($executable->fullDisplayName) ? (string) $executable->fullDisplayName : '',
					'url' => $executable && isset($executable->url) ? (string) $executable->url : '',
					'environment' => $this->normalizeJobSeekerEnvironment($executorEnvironment)
				);
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
		$slotStatus['executors'] = $executors;
		$slotStatus['queue'] = $queueItems;

		return $slotStatus;
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

	private function jenkinsEnvironmentFromJobConfig($jobName) {
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

	private function jenkinsDirectChildText($parent, $tagName) {
		foreach ($parent->childNodes as $child) {
			if ($child->nodeType === XML_ELEMENT_NODE && $child->tagName === $tagName) {
				return $child->textContent;
			}
		}

		return '';
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
			redirect ( 'login' );
		} else {
			$this->role = $this->session->userdata ( 'role' );
			$this->vendorId = $this->session->userdata ( 'userId' );
			$this->name = $this->session->userdata ( 'name' );
			$this->roleText = $this->session->userdata ( 'roleText' );
			$this->lastLogin = $this->session->userdata ( 'lastLogin' );
			
			$this->global ['name'] = $this->name;
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
			$this->global ['jenkins_authorization'] = $jsonToArray->jenkins->authorization;
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
		if ($this->role != ROLE_MANAGER) {
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