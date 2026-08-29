<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class JenkinsProxy extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
    }

    private function canViewExecutorMonitoring()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function denyExecutorMonitoring()
    {
        $this->output
            ->set_status_header(403)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(array('ok' => FALSE, 'message' => 'Access denied.')));
    }

    private function requestedEnvironment()
    {
        $environment = trim((string) $this->input->get('environment', TRUE));
        return $environment !== '' ? $environment : $this->jobSeekerEnvironmentPreference();
    }

    public function proxy()
    {
        $method = $this->input->method(TRUE);

        if (! in_array($method, array('GET', 'HEAD', 'OPTIONS'), TRUE)) {
            if ($this->role != ROLE_ADMIN && $this->role != ROLE_MANAGER) {
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('text/plain')
                    ->set_output('Access denied. Jenkins mutations require a job-management role.');
                return;
            }

            $tokenName = $this->security->get_csrf_token_name();
            $token = $this->input->get($tokenName);

            if (empty($token) || ! hash_equals($this->security->get_csrf_hash(), $token)) {
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('text/plain')
                    ->set_output('Invalid CSRF token.');
                return;
            }
        }

        $path = $this->input->get('path');
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : NULL;

        if (! in_array($method, array('GET', 'HEAD', 'OPTIONS'), TRUE) && preg_match('#(?:^|/)doDelete(?:\?.*)?$#i', (string) $path)) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(array(
                    'ok' => FALSE,
                    'message' => 'Jenkins job deletion must use the environment-scoped JobSeeker endpoint.'
                )));
            return;
        }

        $response = NULL;
        if (! in_array($method, array('GET', 'HEAD', 'OPTIONS'), TRUE)) {
            $response = $this->requestJenkinsBuild($path, $this->input->raw_input_stream, $contentType);
        }

        if ($response === NULL) {
            $response = $this->requestJenkins($method, $path, $this->input->raw_input_stream, $contentType);
        }

        if (! in_array($method, array('GET', 'HEAD', 'OPTIONS'), TRUE) && in_array($response['status'], array(301, 302, 303), TRUE)) {
            $response['status'] = 200;
        }

        $this->forwardJenkinsResponseHeaders($response);

        $this->output
            ->set_status_header($response['status'])
            ->set_content_type($response['content_type'])
            ->set_output($response['body']);
    }

    public function environmentSlots()
    {
        $requestedEnvironment = $this->requestedEnvironment();
        $status = $this->jenkinsEnvironmentSlotStatus($requestedEnvironment);
        $this->includeConfiguredContextEnvironments($status, $requestedEnvironment);

        $this->output
            ->set_status_header(isset($status['status']) ? (int) $status['status'] : 200)
            ->set_content_type('application/json')
            ->set_output(json_encode($status));
    }

    public function executorMonitor()
    {
        if (! $this->canViewExecutorMonitoring()) {
            $this->denyExecutorMonitoring();
            return;
        }

        $requestedEnvironment = $this->requestedEnvironment();
        $status = $this->jenkinsExecutorMonitorStatus($requestedEnvironment);
        $this->includeConfiguredContextEnvironments($status, $requestedEnvironment);

        $this->output
            ->set_status_header(isset($status['status']) ? (int) $status['status'] : 200)
            ->set_content_type('application/json')
            ->set_output(json_encode($status));
    }

    public function dashboardMetrics()
    {
        $requestedEnvironment = $this->normalizeJobSeekerEnvironment($this->requestedEnvironment());
        $status = $this->jenkinsExecutorMonitorStatus($requestedEnvironment);
        $this->includeConfiguredContextEnvironments($status, $requestedEnvironment);

        if (! isset($status['ok']) || ! $status['ok']) {
            $this->output
                ->set_status_header(isset($status['status']) ? (int) $status['status'] : 503)
                ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(array(
                    'ok' => FALSE,
                    'message' => isset($status['message']) ? $status['message'] : 'Unable to load Jenkins capacity metrics.'
                )));
            return;
        }

        $global = isset($status['global']) && is_array($status['global']) ? $status['global'] : array();
        $environmentRows = isset($status['environments']) && is_array($status['environments']) ? $status['environments'] : array();
        $slotRows = array();
        if ($requestedEnvironment !== '' && $requestedEnvironment !== 'ALL') {
            $slotRows[] = isset($environmentRows[$requestedEnvironment]) ? $environmentRows[$requestedEnvironment] : array();
        } else {
            $slotRows = array_values($environmentRows);
        }

        $slots = array('active' => 0, 'running' => 0, 'queued' => 0, 'limit' => 0, 'available' => 0);
        foreach ($slotRows as $row) {
            foreach (array('active', 'running', 'queued', 'limit') as $field) {
                $slots[$field] += isset($row[$field]) ? (int) $row[$field] : 0;
            }
            if (isset($row['available']) && $row['available'] !== NULL) {
                $slots['available'] += (int) $row['available'];
            }
        }

        $totalExecutors = isset($global['totalExecutors']) ? (int) $global['totalExecutors'] : 0;
        $busyExecutors = isset($global['busyExecutors']) ? (int) $global['busyExecutors'] : 0;
        $payload = array(
            'ok' => TRUE,
            'generatedAt' => date('c'),
            'scope' => $requestedEnvironment === '' ? 'all' : $requestedEnvironment,
            'executors' => array(
                'total' => $totalExecutors,
                'busy' => $busyExecutors,
                'idle' => isset($global['idleExecutors']) ? (int) $global['idleExecutors'] : max(0, $totalExecutors - $busyExecutors),
                'utilization' => $totalExecutors > 0 ? round(($busyExecutors / $totalExecutors) * 100, 1) : NULL
            ),
            'nodes' => array(
                'online' => isset($global['onlineNodes']) ? (int) $global['onlineNodes'] : 0,
                'offline' => isset($global['offlineNodes']) ? (int) $global['offlineNodes'] : 0,
                'agents' => isset($global['agentNodes']) ? (int) $global['agentNodes'] : 0,
                'onlineAgents' => isset($global['onlineAgentNodes']) ? (int) $global['onlineAgentNodes'] : 0
            ),
            'queueDepth' => isset($status['queue']) && is_array($status['queue']) ? count($status['queue']) : 0,
            'slots' => $slots,
            'environmentAgentsEnabled' => isset($status['environmentAgentsEnabled']) && $status['environmentAgentsEnabled'] === TRUE
        );

        $this->output
            ->set_status_header(200)
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload));
    }

    public function runningBuilds()
    {
        $limit = $this->runningBuildLimit($this->input->get('limit'));
        $requestedEnvironment = $this->requestedEnvironment();
        $status = $this->jenkinsRunningBuildsStatus($requestedEnvironment, $limit);
        $this->includeConfiguredRunningBuildEnvironments($status, $requestedEnvironment);

        $this->output
            ->set_status_header(isset($status['status']) ? (int) $status['status'] : 200)
            ->set_content_type('application/json')
            ->set_output(json_encode($status));
    }

    public function agentSetupHelper()
    {
        if (! $this->canViewExecutorMonitoring()) {
            $this->denyExecutorMonitoring();
            return;
        }

        $status = $this->jenkinsExecutorMonitorStatus('');
        $this->includeConfiguredContextEnvironments($status);

        if (! isset($status['ok']) || ! $status['ok']) {
            $this->output
                ->set_status_header(isset($status['status']) ? (int) $status['status'] : 503)
                ->set_content_type('application/json')
                ->set_output(json_encode($status));
            return;
        }

        $mode = strtolower(trim((string) $this->input->get('mode')));
        if ($mode === 'k8s') {
            $mode = 'kubernetes';
        }
        if (! in_array($mode, array('docker', 'vm', 'kubernetes'), TRUE)) {
            $mode = 'docker';
        }

        $detectedCpu = $this->detectAgentHelperCpuCores();
        $detectedMemory = $this->detectAgentHelperMemoryMb();
        $cpuOverride = $this->positiveAgentHelperInteger($this->input->get('cpu'));
        $memoryOverride = $this->positiveAgentHelperInteger($this->input->get('memoryMb'));
        $cpuCores = $cpuOverride > 0 ? $cpuOverride : $detectedCpu['value'];
        $memoryMb = $memoryOverride > 0 ? $memoryOverride : $detectedMemory['value'];

        $guide = $this->buildAgentSetupGuide($status, $mode, $cpuCores, $cpuOverride > 0 ? 'manual override' : $detectedCpu['source'], $memoryMb, $memoryOverride > 0 ? 'manual override' : $detectedMemory['source']);

        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode($guide));
    }

    private function runningBuildLimit($value)
    {
        $limit = preg_match('/^[1-9][0-9]*$/', (string) $value) ? (int) $value : 5;
        return max(1, min(20, $limit));
    }

    private function jenkinsRunningBuildsStatus($environment = '', $limitPerEnvironment = 5)
    {
        $requestedEnvironment = $this->normalizeJobSeekerEnvironment($environment);
        $response = $this->requestJenkins('GET', 'api/json?tree='.$this->runningBuildJobTree(3));

        if ((int) $response['status'] !== 200) {
            return array(
                'ok' => FALSE,
                'status' => (int) $response['status'],
                'message' => 'Unable to inspect Jenkins running builds. HTTP '.$response['status'].'.',
                'builds' => array(),
                'environments' => array()
            );
        }

        $payload = json_decode($response['body']);
        if (! is_object($payload) || ! isset($payload->jobs) || ! is_array($payload->jobs)) {
            return array(
                'ok' => FALSE,
                'status' => 502,
                'message' => 'Jenkins returned an invalid running builds payload.',
                'builds' => array(),
                'environments' => array()
            );
        }

        $builds = array();
        $seen = array();
        $this->collectRunningBuilds($payload->jobs, $builds, $seen, $requestedEnvironment);
        usort($builds, array($this, 'compareRunningBuilds'));

        $grouped = $this->groupRunningBuilds($builds, $limitPerEnvironment);

        return array(
            'ok' => TRUE,
            'status' => 200,
            'message' => 'Jenkins running builds loaded.',
            'generatedAt' => (int) round(microtime(TRUE) * 1000),
            'limitPerEnvironment' => (int) $limitPerEnvironment,
            'builds' => $grouped['builds'],
            'environments' => $grouped['environments'],
            'totalRunning' => count($builds)
        );
    }

    private function runningBuildJobTree($depth)
    {
        $fields = '_class,name,fullName,displayName,url,color,buildable,inQueue,property[parameterDefinitions[name,defaultParameterValue[value]]],lastBuild[number,id,result,timestamp,duration,estimatedDuration,building,builtOn,url,queueId,displayName,fullDisplayName,actions[parameters[name,value]]],builds[number,id,result,timestamp,duration,estimatedDuration,building,builtOn,url,queueId,displayName,fullDisplayName,actions[parameters[name,value]]]{0,20}';

        if ($depth <= 0) {
            return 'jobs['.$fields.']';
        }

        return 'jobs['.$fields.','.$this->runningBuildJobTree($depth - 1).']';
    }

    private function collectRunningBuilds($jobs, &$builds, &$seen, $requestedEnvironment = '')
    {
        foreach (is_array($jobs) ? $jobs : array() as $job) {
            $jobName = isset($job->fullName) && $job->fullName !== '' ? $job->fullName : (isset($job->name) ? $job->name : '');

            if ($jobName !== '' && strpos((string) $jobName, '__jobseeker_') !== 0) {
                $jobEnvironment = $this->jenkinsEnvironmentFromJobData($job, $jobName);
                $candidateBuilds = isset($job->builds) && is_array($job->builds) ? $job->builds : array();

                if (empty($candidateBuilds) && isset($job->lastBuild) && is_object($job->lastBuild)) {
                    $candidateBuilds[] = $job->lastBuild;
                }

                foreach ($candidateBuilds as $build) {
                    if (! is_object($build) || ! isset($build->building) || $build->building !== TRUE || ! isset($build->number)) {
                        continue;
                    }

                    $record = $this->runningBuildRecord($job, $build, $jobName, $jobEnvironment);
                    if ($record === NULL) {
                        continue;
                    }

                    if ($requestedEnvironment !== '' && $requestedEnvironment !== 'ALL' && $record['environment'] !== $requestedEnvironment) {
                        continue;
                    }

                    $key = $record['jobName'].'#'.$record['buildNumber'];
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = TRUE;
                    $builds[] = $record;
                }
            }

            if (isset($job->jobs) && is_array($job->jobs)) {
                $this->collectRunningBuilds($job->jobs, $builds, $seen, $requestedEnvironment);
            }
        }
    }

    private function runningBuildRecord($job, $build, $jobName, $jobEnvironment)
    {
        $buildNumber = isset($build->number) ? (int) $build->number : 0;
        if ($jobName === '' || $buildNumber < 1) {
            return NULL;
        }

        $environment = $this->jenkinsParameterValueFromActions($build, 'ENVIRONMENT');
        if ($environment === '') {
            $environment = $jobEnvironment;
        }
        if ($environment === '') {
            $environment = $this->detectEnvironmentFromJenkinsJobName($jobName);
        }

        $environment = $this->normalizeJobSeekerEnvironment($environment);
        if ($environment === '' || $environment === '0' || $environment === 'ALL') {
            $environment = 'UNKNOWN';
        }

        $timestamp = isset($build->timestamp) ? (int) $build->timestamp : 0;
        $duration = isset($build->duration) ? (int) $build->duration : 0;
        $elapsedMs = $timestamp > 0 ? max(0, (int) round(microtime(TRUE) * 1000) - $timestamp) : max(0, $duration);
        $displayName = isset($build->displayName) && $build->displayName !== '' ? (string) $build->displayName : '#'.$buildNumber;
        $fullDisplayName = isset($build->fullDisplayName) && $build->fullDisplayName !== '' ? (string) $build->fullDisplayName : $jobName.' '.$displayName;

        return array(
            'job' => $jobName,
            'jobName' => $jobName,
            'buildNumber' => $buildNumber,
            'number' => $buildNumber,
            'displayName' => $displayName,
            'fullDisplayName' => $fullDisplayName,
            'environment' => $environment,
            'environmentSource' => $this->jenkinsParameterValueFromActions($build, 'ENVIRONMENT') !== '' ? 'Jenkins build parameter' : 'Jenkins job metadata',
            'timestamp' => $timestamp,
            'duration' => $duration,
            'estimatedDuration' => isset($build->estimatedDuration) ? (int) $build->estimatedDuration : 0,
            'elapsedMs' => $elapsedMs,
            'builtOn' => isset($build->builtOn) ? (string) $build->builtOn : '',
            'url' => isset($build->url) ? (string) $build->url : '',
            'queueId' => isset($build->queueId) ? (string) $build->queueId : '',
            'result' => isset($build->result) ? (string) $build->result : '',
            'building' => TRUE
        );
    }

    private function compareRunningBuilds($left, $right)
    {
        $leftTimestamp = isset($left['timestamp']) ? (int) $left['timestamp'] : 0;
        $rightTimestamp = isset($right['timestamp']) ? (int) $right['timestamp'] : 0;

        if ($leftTimestamp !== $rightTimestamp) {
            return $leftTimestamp < $rightTimestamp ? 1 : -1;
        }

        return strcmp(isset($left['jobName']) ? $left['jobName'] : '', isset($right['jobName']) ? $right['jobName'] : '');
    }

    private function groupRunningBuilds($builds, $limitPerEnvironment)
    {
        $environments = array();
        $limitedBuilds = array();
        $limitPerEnvironment = max(1, (int) $limitPerEnvironment);

        foreach ($builds as $build) {
            $environment = isset($build['environment']) ? $build['environment'] : 'UNKNOWN';

            if (! isset($environments[$environment])) {
                $environments[$environment] = array('running' => 0, 'builds' => array());
            }

            $environments[$environment]['running'] += 1;

            if (count($environments[$environment]['builds']) < $limitPerEnvironment) {
                $build['rank'] = count($environments[$environment]['builds']) + 1;
                $environments[$environment]['builds'][] = $build;
                $limitedBuilds[] = $build;
            }
        }

        ksort($environments);
        usort($limitedBuilds, array($this, 'compareRunningBuilds'));

        return array('builds' => $limitedBuilds, 'environments' => $environments);
    }

    private function includeConfiguredRunningBuildEnvironments(&$status, $requestedEnvironment = '')
    {
        if (! isset($status['ok']) || ! $status['ok'] || ! isset($status['environments']) || ! is_array($status['environments'])) {
            return;
        }

        $this->load->model('Context_model', 'contextModel');
        $environments = $this->contextModel->listEnvironments();
        $requestedEnvironment = $this->normalizeJobSeekerEnvironment($requestedEnvironment);

        foreach (is_array($environments) ? $environments : array() as $record) {
            $environmentName = isset($record->Environment) ? $record->Environment : '';
            $environment = $this->normalizeJobSeekerEnvironment($environmentName);

            if ($environment === '' || $environment === '0' || $environment === 'ALL' || $environment === 'UNKNOWN') {
                continue;
            }
            if ($requestedEnvironment !== '' && $requestedEnvironment !== 'ALL' && $environment !== $requestedEnvironment) {
                continue;
            }

            if (! isset($status['environments'][$environment])) {
                $status['environments'][$environment] = array('running' => 0, 'builds' => array());
            }
        }

        ksort($status['environments']);
    }

    private function includeConfiguredContextEnvironments(&$status, $requestedEnvironment = '')
    {
        if (! isset($status['ok']) || ! $status['ok'] || ! isset($status['environments']) || ! is_array($status['environments'])) {
            return;
        }

        $this->load->model('Context_model', 'contextModel');
        $environments = $this->contextModel->listEnvironments();
        $requestedEnvironment = $this->normalizeJobSeekerEnvironment($requestedEnvironment);

        foreach (is_array($environments) ? $environments : array() as $record) {
            $environmentName = isset($record->Environment) ? $record->Environment : '';
            $environment = $this->normalizeJobSeekerEnvironment($environmentName);

            if ($environment === '' || $environment === '0' || $environment === 'ALL' || $environment === 'UNKNOWN') {
                continue;
            }
            if ($requestedEnvironment !== '' && $requestedEnvironment !== 'ALL' && $environment !== $requestedEnvironment) {
                continue;
            }

            if (! isset($status['environments'][$environment])) {
                $limit = $this->jenkinsEnvironmentSlotLimit($environment);
                $status['environments'][$environment] = array(
                    'running' => 0,
                    'queued' => 0,
                    'active' => 0,
                    'limit' => $limit,
                    'available' => $limit < 1 ? NULL : $limit,
                    'agentNodes' => 0,
                    'onlineAgentNodes' => 0,
                    'agentExecutors' => 0,
                    'onlineAgentExecutors' => 0,
                    'busyAgentExecutors' => 0,
                    'availableAgentExecutors' => 0,
                    'offlineAgentNodes' => 0
                );
            } else {
                foreach (array('agentNodes', 'onlineAgentNodes', 'agentExecutors', 'onlineAgentExecutors', 'busyAgentExecutors', 'availableAgentExecutors', 'offlineAgentNodes') as $field) {
                    if (! isset($status['environments'][$environment][$field])) {
                        $status['environments'][$environment][$field] = 0;
                    }
                }
            }
        }

        ksort($status['environments']);
    }

    private function buildAgentSetupGuide($status, $mode, $cpuCores, $cpuSource, $memoryMb, $memorySource)
    {
        $cpuCores = max(1, (int) $cpuCores);
        $memoryMb = max(0, (int) $memoryMb);
        $environmentRows = array();
        $environmentNames = $this->agentHelperEnvironmentNames($status);
        $cpuBudget = max(1, $cpuCores - ($cpuCores >= 4 ? 1 : 0));
        $memoryBudget = $memoryMb > 0 ? max(1, (int) floor(max(512, $memoryMb - 1024) / 1024)) : $cpuBudget;
        $buildBudget = max(1, min($cpuBudget, $memoryBudget));
        $controllerExecutors = $mode === 'kubernetes' ? 0 : min(1, $buildBudget);
        $agentBudget = empty($environmentNames) ? 0 : max(0, $buildBudget - $controllerExecutors);
        $slotParts = array();
        $labelParts = array();
        $executorLines = array();
        $knownComposeEnvironments = array('DEV', 'QA', 'UAT', 'PROD');
        $labels = isset($status['environmentAgentLabels']) && is_array($status['environmentAgentLabels']) ? $status['environmentAgentLabels'] : array();
        $routableEnvironmentNames = array();

        foreach ($environmentNames as $environment) {
            if ($mode === 'kubernetes' || isset($labels[$environment]) || in_array($environment, $knownComposeEnvironments, TRUE)) {
                $routableEnvironmentNames[] = $environment;
            }
        }

        $allocations = $this->allocateAgentHelperExecutors($routableEnvironmentNames, $agentBudget);

        foreach ($environmentNames as $environment) {
            $row = isset($status['environments'][$environment]) && is_array($status['environments'][$environment]) ? $status['environments'][$environment] : array();
            $recommendedExecutors = isset($allocations[$environment]) ? (int) $allocations[$environment] : 0;
            $hasConfiguredLabel = isset($labels[$environment]) && $labels[$environment] !== '';
            $label = $hasConfiguredLabel || $mode === 'kubernetes' ? ($hasConfiguredLabel ? $labels[$environment] : 'jobseeker-env-'.strtolower($environment)) : '';
            $service = in_array($environment, $knownComposeEnvironments, TRUE) ? 'jenkins-agent-'.strtolower($environment) : '';
            $recommendedAgents = $mode === 'kubernetes' ? $recommendedExecutors : ($recommendedExecutors > 0 ? 1 : 0);
            $recommendedExecutorsPerAgent = $mode === 'kubernetes' ? 1 : $recommendedExecutors;
            $recommendedSlotLimit = $recommendedExecutors;

            $environmentRows[] = array(
                'environment' => $environment,
                'label' => $label,
                'service' => $service,
                'currentSlotLimit' => isset($row['limit']) ? (int) $row['limit'] : 0,
                'currentAgentNodes' => isset($row['agentNodes']) ? (int) $row['agentNodes'] : 0,
                'onlineAgentNodes' => isset($row['onlineAgentNodes']) ? (int) $row['onlineAgentNodes'] : 0,
                'currentAgentExecutors' => isset($row['agentExecutors']) ? (int) $row['agentExecutors'] : 0,
                'recommendedAgents' => $recommendedAgents,
                'recommendedExecutorsPerAgent' => $recommendedExecutorsPerAgent,
                'recommendedSlotLimit' => $recommendedSlotLimit
            );

            if ($recommendedSlotLimit > 0) {
                $slotParts[] = $environment.'='.$recommendedSlotLimit;
            }
            if ($label !== '') {
                $labelParts[] = $environment.'='.$label;
            }

            if ($mode !== 'kubernetes' && $service !== '' && $recommendedExecutors > 0) {
                $executorLines[] = 'JOBSEEKER_JENKINS_'.$environment.'_AGENT_EXECUTORS='.$recommendedExecutorsPerAgent;
            }
        }

        $envLines = array(
            'JENKINS_NUM_EXECUTORS='.$controllerExecutors,
            'JOBSEEKER_JENKINS_ENVIRONMENT_AGENTS_ENABLED=true',
            'JOBSEEKER_JENKINS_ENVIRONMENT_SLOTS='.implode(',', $slotParts),
            'JOBSEEKER_JENKINS_ENVIRONMENT_AGENT_LABELS='.implode(',', $labelParts)
        );
        $envLines = array_merge($envLines, $executorLines);

        $commands = $mode === 'kubernetes'
            ? array(
                'Configure Jenkins controller executors to '.$controllerExecutors.'.',
                'Create Jenkins Kubernetes pod templates with the labels in JOBSEEKER_JENKINS_ENVIRONMENT_AGENT_LABELS.',
                'Use one executor per pod and set each pod-template instance cap to the recommended slot limit.',
                'Roll the JobSeeker app after setting the environment variables.'
            )
            : array(
                'docker compose up -d --force-recreate php jenkins',
                'docker compose --profile jenkins-agents up -d --build',
                'docker compose --profile jenkins-agents up -d --force-recreate jenkins-agent-dev jenkins-agent-qa jenkins-agent-uat jenkins-agent-prod'
            );

        return array(
            'ok' => TRUE,
            'mode' => $mode,
            'detected' => array(
                'cpuCores' => $cpuCores,
                'cpuSource' => $cpuSource,
                'memoryMb' => $memoryMb,
                'memorySource' => $memorySource
            ),
            'recommendation' => array(
                'buildBudget' => $buildBudget,
                'controllerExecutors' => $controllerExecutors,
                'agentExecutors' => $agentBudget,
                'routingEnabled' => isset($status['environmentAgentsEnabled']) && $status['environmentAgentsEnabled'] === TRUE,
                'currentJenkinsExecutors' => isset($status['global']['totalExecutors']) ? (int) $status['global']['totalExecutors'] : 0
            ),
            'environments' => $environmentRows,
            'env' => $envLines,
            'commands' => $commands,
            'notes' => array(
                'Use this as a CPU-heavy starting point. I/O-heavy jobs can often tolerate more executors than CPU cores.',
                'JobSeeker slots should usually match the runnable agent capacity for that environment.',
                'Existing jobs are now reconciled to the target label when they are triggered through JobSeeker. Direct Jenkins UI/API triggers bypass that safety check.'
            )
        );
    }

    private function agentHelperEnvironmentNames($status)
    {
        $names = array();
        $preferred = array('DEV', 'QA', 'UAT', 'PROD');
        $labels = isset($status['environmentAgentLabels']) && is_array($status['environmentAgentLabels']) ? $status['environmentAgentLabels'] : array();
        $environments = isset($status['environments']) && is_array($status['environments']) ? $status['environments'] : array();

        foreach ($preferred as $environment) {
            if (isset($labels[$environment]) || isset($environments[$environment])) {
                $names[] = $environment;
            }
        }

        foreach (array_keys($labels) as $environment) {
            $environment = $this->normalizeJobSeekerEnvironment($environment);
            if ($environment !== '' && ! in_array($environment, $names, TRUE)) {
                $names[] = $environment;
            }
        }

        foreach (array_keys($environments) as $environment) {
            $environment = $this->normalizeJobSeekerEnvironment($environment);
            if ($environment !== '' && ! in_array($environment, $names, TRUE)) {
                $names[] = $environment;
            }
        }

        return $names;
    }

    private function allocateAgentHelperExecutors($environmentNames, $budget)
    {
        $allocations = array();
        foreach ($environmentNames as $environment) {
            $allocations[$environment] = 0;
        }

        $remaining = max(0, (int) $budget);
        $weights = array('DEV' => 2, 'QA' => 1, 'UAT' => 1, 'PROD' => 1);

        while ($remaining > 0) {
            $progress = FALSE;
            foreach ($environmentNames as $environment) {
                $target = isset($weights[$environment]) ? (int) $weights[$environment] : 1;
                if ($allocations[$environment] >= $target || $remaining < 1) {
                    continue;
                }
                $allocations[$environment] += 1;
                $remaining -= 1;
                $progress = TRUE;
            }

            if (! $progress) {
                break;
            }
        }

        $index = 0;
        while ($remaining > 0 && ! empty($environmentNames)) {
            $environment = $environmentNames[$index % count($environmentNames)];
            $allocations[$environment] += 1;
            $remaining -= 1;
            $index += 1;
        }

        return $allocations;
    }

    private function positiveAgentHelperInteger($value)
    {
        return preg_match('/^[1-9][0-9]*$/', (string) $value) ? (int) $value : 0;
    }

    private function detectAgentHelperCpuCores()
    {
        $cpuMax = @file_get_contents('/sys/fs/cgroup/cpu.max');
        if (is_string($cpuMax) && preg_match('/^(\d+)\s+(\d+)/', trim($cpuMax), $matches)) {
            $quota = (int) $matches[1];
            $period = (int) $matches[2];
            if ($quota > 0 && $period > 0) {
                return array('value' => max(1, (int) ceil($quota / $period)), 'source' => 'cgroup cpu.max');
            }
        }

        $quota = @file_get_contents('/sys/fs/cgroup/cpu/cpu.cfs_quota_us');
        $period = @file_get_contents('/sys/fs/cgroup/cpu/cpu.cfs_period_us');
        if (is_string($quota) && is_string($period) && (int) trim($quota) > 0 && (int) trim($period) > 0) {
            return array('value' => max(1, (int) ceil((int) trim($quota) / (int) trim($period))), 'source' => 'cgroup cpu quota');
        }

        $cpuInfo = @file('/proc/cpuinfo');
        if (is_array($cpuInfo)) {
            $cores = 0;
            foreach ($cpuInfo as $line) {
                if (preg_match('/^processor\s*:/', $line)) {
                    $cores += 1;
                }
            }
            if ($cores > 0) {
                return array('value' => $cores, 'source' => '/proc/cpuinfo');
            }
        }

        return array('value' => 2, 'source' => 'fallback');
    }

    private function detectAgentHelperMemoryMb()
    {
        foreach (array('/sys/fs/cgroup/memory.max', '/sys/fs/cgroup/memory/memory.limit_in_bytes') as $path) {
            $raw = @file_get_contents($path);
            $raw = is_string($raw) ? trim($raw) : '';
            if ($raw !== '' && $raw !== 'max' && preg_match('/^[0-9]+$/', $raw)) {
                $bytes = (int) $raw;
                if ($bytes > 0 && $bytes < 9000000000000000000) {
                    return array('value' => max(1, (int) floor($bytes / 1048576)), 'source' => basename($path));
                }
            }
        }

        $meminfo = @file('/proc/meminfo');
        if (is_array($meminfo)) {
            foreach ($meminfo as $line) {
                if (preg_match('/^MemTotal:\s+(\d+)\s+kB/i', $line, $matches)) {
                    return array('value' => max(1, (int) floor((int) $matches[1] / 1024)), 'source' => '/proc/meminfo');
                }
            }
        }

        return array('value' => 0, 'source' => 'unknown');
    }

    private function forwardJenkinsResponseHeaders($response)
    {
        if (empty($response['headers']) || ! is_array($response['headers'])) {
            return;
        }

        $headersToForward = array('Location', 'X-Text-Size', 'X-More-Data');

        foreach ($response['headers'] as $header) {
            foreach ($headersToForward as $name) {
                if (stripos($header, $name . ':') !== 0) {
                    continue;
                }

                $value = trim(substr($header, strlen($name) + 1));

                if ($value === '' || preg_match('/[\r\n]/', $value)) {
                    continue;
                }

                if ($name === 'Location') {
                    $this->output->set_header('X-JobSeeker-Jenkins-Location: ' . $value, TRUE);
                } else {
                    $this->output->set_header($name . ': ' . $value, TRUE);
                }
            }
        }
    }
}
