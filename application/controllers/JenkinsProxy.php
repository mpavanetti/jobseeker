<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class JenkinsProxy extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
    }

    public function proxy()
    {
        $method = $this->input->method(TRUE);

        if (! in_array($method, array('GET', 'HEAD', 'OPTIONS'), TRUE)) {
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

        if (! in_array($method, array('GET', 'HEAD', 'OPTIONS'), TRUE)) {
            $slotCheck = $this->checkJenkinsEnvironmentSlotsForBuildRequest($path, $this->input->raw_input_stream);
            if (! $slotCheck['ok']) {
                $this->output
                    ->set_status_header(isset($slotCheck['status']) ? (int) $slotCheck['status'] : 429)
                    ->set_content_type('text/plain')
                    ->set_output($slotCheck['message']);
                return;
            }

            $routingCheck = $this->ensureJenkinsEnvironmentAgentAssignmentForBuildRequest($path, $this->input->raw_input_stream);
            if (! $routingCheck['ok']) {
                $this->output
                    ->set_status_header(isset($routingCheck['status']) ? (int) $routingCheck['status'] : 502)
                    ->set_content_type('text/plain')
                    ->set_output($routingCheck['message']);
                return;
            }
        }

        $response = $this->requestJenkins($method, $path, $this->input->raw_input_stream, $contentType);

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
        $status = $this->jenkinsEnvironmentSlotStatus($this->input->get('environment'));
        $this->includeConfiguredContextEnvironments($status);

        $this->output
            ->set_status_header(isset($status['status']) ? (int) $status['status'] : 200)
            ->set_content_type('application/json')
            ->set_output(json_encode($status));
    }

    public function executorMonitor()
    {
        $status = $this->jenkinsExecutorMonitorStatus($this->input->get('environment'));
        $this->includeConfiguredContextEnvironments($status);

        $this->output
            ->set_status_header(isset($status['status']) ? (int) $status['status'] : 200)
            ->set_content_type('application/json')
            ->set_output(json_encode($status));
    }

    public function agentSetupHelper()
    {
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

    private function includeConfiguredContextEnvironments(&$status)
    {
        if (! isset($status['ok']) || ! $status['ok'] || ! isset($status['environments']) || ! is_array($status['environments'])) {
            return;
        }

        $this->load->model('Context_model', 'contextModel');
        $environments = $this->contextModel->listEnvironments();

        foreach (is_array($environments) ? $environments : array() as $record) {
            $environmentName = isset($record->Environment) ? $record->Environment : '';
            $environment = $this->normalizeJobSeekerEnvironment($environmentName);

            if ($environment === '' || $environment === '0' || $environment === 'ALL' || $environment === 'UNKNOWN') {
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
                    'busyAgentExecutors' => 0,
                    'availableAgentExecutors' => 0,
                    'offlineAgentNodes' => 0
                );
            } else {
                foreach (array('agentNodes', 'onlineAgentNodes', 'agentExecutors', 'busyAgentExecutors', 'availableAgentExecutors', 'offlineAgentNodes') as $field) {
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