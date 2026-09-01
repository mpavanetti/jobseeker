<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class Pipelines extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pipeline_model', 'pipelines');
        $this->load->library('WorkflowCompiler', NULL, 'compiler');
        $this->load->library('JenkinsCronSchedule', NULL, 'cronSchedule');
        $this->isLoggedIn();
    }

    private function canManagePipelines()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function jsonResponse($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function requireManagerPost()
    {
        if (! $this->canManagePipelines()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return FALSE;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
            return FALSE;
        }
        return TRUE;
    }

    private function activeEnvironments()
    {
        if (! $this->db->table_exists('environment')) {
            return array();
        }
        $rows = $this->db->select('Environment')->from('environment')->where('IsActive', 1)->get()->result();
        return array_values(array_unique(array_map(function($row) {
            return $this->normalizeJobSeekerEnvironment($row->Environment);
        }, $rows)));
    }

    private function selectedEnvironment()
    {
        $value = trim((string) $this->input->get('environment', TRUE));
        if ($value === '') {
            $value = $this->jobSeekerEnvironmentPreference();
        }
        $environment = $this->normalizeJobSeekerEnvironment($value);
        if ($environment === '' || $environment === '*' || $environment === 'ALL') {
            return 'ALL';
        }
        return in_array($environment, $this->activeEnvironments(), TRUE) ? $environment : 'ALL';
    }

    private function normalizePipelineKey($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim(substr($value, 0, 128), '-');
    }

    private function jenkinsJobPath($jobName)
    {
        $path = array();
        foreach (explode('/', trim((string) $jobName, '/')) as $segment) {
            if ($segment !== '') {
                $path[] = 'job/'.rawurlencode($segment);
            }
        }
        return implode('/', $path);
    }

    private function jenkinsCreateItemPath($jobName)
    {
        $segments = explode('/', trim((string) $jobName, '/'));
        $name = array_pop($segments);
        $parent = implode('/', $segments);
        return ($parent !== '' ? $this->jenkinsJobPath($parent).'/' : '').'createItem?name='.rawurlencode($name);
    }

    private function successfulJenkinsStatus($status)
    {
        return in_array((int) $status, array(200, 201, 302, 303), TRUE);
    }

    private function availableJobsTree($depth)
    {
        $fields = '_class,name,fullName,displayName,color,buildable,property[parameterDefinitions[name,defaultParameterValue[value]]]';
        return $depth <= 0 ? 'jobs['.$fields.']' : 'jobs['.$fields.','.$this->availableJobsTree($depth - 1).']';
    }

    private function collectAvailableJobs($jobs, &$result, $environment)
    {
        foreach (is_array($jobs) ? $jobs : array() as $job) {
            $fullName = isset($job->fullName) && $job->fullName !== '' ? (string) $job->fullName : (isset($job->name) ? (string) $job->name : '');
            $isRunnable = isset($job->buildable) || isset($job->color);
            if ($fullName !== '' && $isRunnable && strpos($fullName, 'jobseeker-pipeline-') !== 0 && strpos($fullName, '__jobseeker_') !== 0) {
                $jobEnvironment = $this->jenkinsEnvironmentFromJobData($job, $fullName);
                if ($environment === 'ALL' || $jobEnvironment === $environment) {
                    $result[] = array(
                        'name' => $fullName,
                        'label' => isset($job->displayName) && $job->displayName !== '' ? (string) $job->displayName : $fullName,
                        'environment' => $jobEnvironment,
                        'color' => isset($job->color) ? (string) $job->color : ''
                    );
                }
            }
            if (isset($job->jobs) && is_array($job->jobs)) {
                $this->collectAvailableJobs($job->jobs, $result, $environment);
            }
        }
    }

    private function availableJobs($environment)
    {
        $response = $this->requestJenkins('GET', 'api/json?tree='.$this->availableJobsTree(3));
        if ((int) $response['status'] !== 200) {
            return array('ok' => FALSE, 'status' => (int) $response['status'], 'jobs' => array());
        }
        $payload = json_decode($response['body']);
        if (! is_object($payload) || ! isset($payload->jobs)) {
            return array('ok' => FALSE, 'status' => 502, 'jobs' => array());
        }
        $jobs = array();
        $this->collectAvailableJobs($payload->jobs, $jobs, $environment);
        usort($jobs, function($left, $right) { return strcasecmp($left['name'], $right['name']); });
        return array('ok' => TRUE, 'status' => 200, 'jobs' => $jobs);
    }

    private function graphPayload($pipeline)
    {
        if (! $pipeline) {
            return array('nodes' => array(), 'edges' => array());
        }
        $graph = json_decode((string) $pipeline->graph_json, TRUE);
        return is_array($graph) ? $graph : array('nodes' => array(), 'edges' => array());
    }

    private function observedBuildTrigger($build)
    {
        foreach (isset($build['actions']) && is_array($build['actions']) ? $build['actions'] : array() as $action) {
            foreach (isset($action['causes']) && is_array($action['causes']) ? $action['causes'] : array() as $cause) {
                $class = isset($cause['_class']) ? (string) $cause['_class'] : '';
                if (stripos($class, 'TimerTriggerCause') !== FALSE) {
                    return 'Jenkins schedule';
                }
                if (! empty($cause['userName'])) {
                    return (string) $cause['userName'];
                }
                if (! empty($cause['shortDescription'])) {
                    return substr((string) $cause['shortDescription'], 0, 200);
                }
            }
        }
        return 'Jenkins';
    }

    private function syncObservedRuns($pipeline)
    {
        if (! $pipeline || empty($pipeline->jenkins_job_name)) {
            return;
        }
        $tree = 'builds[number,result,building,timestamp,duration,actions[causes[_class,shortDescription,userName]]]{0,10}';
        $response = $this->requestJenkins('GET', $this->jenkinsJobPath($pipeline->jenkins_job_name).'/api/json?tree='.$tree);
        if ((int) $response['status'] !== 200) {
            return;
        }
        $payload = json_decode($response['body'], TRUE);
        $builds = isset($payload['builds']) && is_array($payload['builds']) ? array_reverse($payload['builds']) : array();
        foreach ($builds as $build) {
            $buildNumber = isset($build['number']) ? (int) $build['number'] : 0;
            if ($buildNumber <= 0 || $this->pipelines->getRunByBuild($pipeline->id, $buildNumber)) {
                continue;
            }
            $startedTimestamp = isset($build['timestamp']) ? (int) floor($build['timestamp'] / 1000) : time();
            $duration = isset($build['duration']) ? (int) $build['duration'] : 0;
            $status = ! empty($build['building']) ? 'RUNNING' : strtoupper(isset($build['result']) ? (string) $build['result'] : 'UNKNOWN');
            $completedAt = $status === 'RUNNING' ? NULL : date('Y-m-d H:i:s', $startedTimestamp + (int) floor($duration / 1000));
            $this->pipelines->createObservedRun(
                $pipeline->id,
                $buildNumber,
                $status,
                $pipeline->environment,
                $this->observedBuildTrigger($build),
                date('Y-m-d H:i:s', $startedTimestamp),
                $completedAt
            );
        }
    }

    public function index()
    {
        if (! $this->canManagePipelines()) {
            $this->loadThis();
            return;
        }
        $environment = $this->selectedEnvironment();
        $pipelineId = (int) $this->input->get('id');
        $pipeline = $pipelineId > 0 ? $this->pipelines->getPipeline($pipelineId) : NULL;
        if ($pipeline && $environment !== 'ALL' && $pipeline->environment !== $environment) {
            $pipeline = NULL;
        }
        $this->syncObservedRuns($pipeline);
        $jobs = $environment === 'ALL' ? array('ok' => TRUE, 'jobs' => array()) : $this->availableJobs($environment);
        $data = array(
            'selectedEnvironment' => $environment,
            'environments' => $this->activeEnvironments(),
            'pipelineList' => $this->pipelines->listPipelines($environment),
            'pipeline' => $pipeline,
            'graph' => $this->graphPayload($pipeline),
            'jobs' => $jobs['jobs'],
            'jobsAvailable' => $jobs['ok'],
            'recentRuns' => $pipeline ? $this->pipelines->recentRuns($pipeline->id, 10) : array()
        );
        $this->global['pageTitle'] = 'Job Seeker : Pipelines';
        $this->loadViews('pipelines', $this->global, $data, NULL);
    }

    public function jobs()
    {
        if (! $this->canManagePipelines()) {
            $this->jsonResponse(array('ok' => FALSE, 'jobs' => array()), 403);
            return;
        }
        $environment = $this->selectedEnvironment();
        if ($environment === 'ALL') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Select a global environment first.', 'jobs' => array()), 422);
            return;
        }
        $result = $this->availableJobs($environment);
        $this->jsonResponse($result, $result['ok'] ? 200 : $result['status']);
    }

    public function validateGraph()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $nodes = json_decode((string) $this->input->post('nodes_json'), TRUE);
        $edges = json_decode((string) $this->input->post('edges_json'), TRUE);
        $result = $this->compiler->validateGraph($nodes, $edges);
        $this->jsonResponse($result, $result['ok'] ? 200 : 422);
    }

    private function pipelineJobName($id, $key)
    {
        return substr('__jobseeker_pipeline_'.(int) $id.'_'.str_replace('-', '_', $key), 0, 240);
    }

    private function suggestedDeploymentJobName($jobName, $sourceEnvironment, $targetEnvironment)
    {
        $pattern = '/(^|[._\/-])'.preg_quote($sourceEnvironment, '/').'($|[._\/-])/i';
        $suggested = preg_replace_callback($pattern, function($matches) use ($targetEnvironment) {
            return $matches[1].$targetEnvironment.$matches[2];
        }, $jobName, 1);
        if ($suggested !== $jobName) {
            return $suggested;
        }
        $targetToken = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $targetEnvironment), '.-_');
        return $jobName.'-'.($targetToken === '' ? 'deployed' : $targetToken);
    }

    private function deploymentGraph($graph, $sourceEnvironment, $targetEnvironment, $targetJobs)
    {
        $availableNames = array_fill_keys(array_map(function($job) { return $job['name']; }, $targetJobs), TRUE);
        $mappings = array();
        foreach ($graph['nodes'] as &$node) {
            $sourceJob = $node['job'];
            $suggestedJob = $this->suggestedDeploymentJobName($sourceJob, $sourceEnvironment, $targetEnvironment);
            $targetJob = isset($availableNames[$suggestedJob]) ? $suggestedJob : (isset($availableNames[$sourceJob]) ? $sourceJob : $suggestedJob);
            $targetExists = isset($availableNames[$targetJob]);
            if (! isset($node['label']) || $node['label'] === '' || $node['label'] === $sourceJob) {
                $node['label'] = $targetJob;
            }
            $node['job'] = $targetJob;
            $mappings[] = array('nodeId' => $node['id'], 'sourceJob' => $sourceJob, 'targetJob' => $targetJob, 'targetExists' => $targetExists);
        }
        unset($node);
        return array('ok' => TRUE, 'graph' => $graph, 'mappings' => $mappings);
    }

    private function prepareDeploymentJobs($mappings, $sourceEnvironment, $targetEnvironment, $overwrite)
    {
        $jobNameMap = array();
        foreach ($mappings as $mapping) {
            $jobNameMap[$mapping['sourceJob']] = $mapping['targetJob'];
        }

        $prepared = array();
        $seen = array();
        foreach ($mappings as $mapping) {
            $key = $mapping['sourceJob']."\0".$mapping['targetJob'];
            if (isset($seen[$key]) || (! $overwrite && $mapping['targetExists'])) {
                continue;
            }
            $seen[$key] = TRUE;

            $sourceResponse = $this->requestJenkins('GET', $this->jenkinsJobPath($mapping['sourceJob']).'/config.xml');
            if ((int) $sourceResponse['status'] !== 200) {
                return array('ok' => FALSE, 'message' => 'Source Jenkins job '.$mapping['sourceJob'].' could not be loaded (HTTP '.$sourceResponse['status'].').');
            }
            $transform = $this->transformDeploymentJobXml($sourceResponse['body'], $sourceEnvironment, $targetEnvironment, $mapping['sourceJob'], $mapping['targetJob'], $jobNameMap);
            if (! $transform['ok']) {
                return $transform;
            }
            $prepared[] = array(
                'sourceJob' => $mapping['sourceJob'],
                'targetJob' => $mapping['targetJob'],
                'targetExists' => $mapping['targetExists'],
                'xml' => $transform['xml']
            );
        }
        return array('ok' => TRUE, 'jobs' => $prepared);
    }

    private function transformDeploymentJobXml($xml, $sourceEnvironment, $targetEnvironment, $sourceJob, $targetJob, $jobNameMap)
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = FALSE;
        $dom->formatOutput = TRUE;
        $previousErrors = libxml_use_internal_errors(TRUE);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        if (! $loaded || ! $dom->documentElement) {
            return array('ok' => FALSE, 'message' => 'Source Jenkins job '.$sourceJob.' has invalid config.xml.');
        }

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//hudson.tasks.Shell/command | //hudson.tasks.BatchFile/command') as $commandNode) {
            $updated = $this->rewriteDeploymentCommand($commandNode->nodeValue, $sourceEnvironment, $targetEnvironment, $sourceJob, $targetJob);
            if ($updated !== $commandNode->nodeValue) {
                while ($commandNode->firstChild) {
                    $commandNode->removeChild($commandNode->firstChild);
                }
                $commandNode->appendChild($dom->createTextNode($updated));
            }
        }

        $parameterNames = array('environment', 'env', 'context', 'job_environment', 'jobseeker_environment', 'target_environment', 'context_environment');
        foreach ($dom->getElementsByTagName('defaultValue') as $defaultValueNode) {
            $nameNode = $this->directDeploymentChild($defaultValueNode->parentNode, 'name');
            if ($nameNode && in_array(strtolower(trim($nameNode->nodeValue)), $parameterNames, TRUE) && strcasecmp(trim($defaultValueNode->nodeValue), $sourceEnvironment) === 0) {
                while ($defaultValueNode->firstChild) {
                    $defaultValueNode->removeChild($defaultValueNode->firstChild);
                }
                $defaultValueNode->appendChild($dom->createTextNode($targetEnvironment));
            }
        }

        foreach ($dom->getElementsByTagName('childProjects') as $childProjectsNode) {
            $projects = array();
            foreach (explode(',', $childProjectsNode->nodeValue) as $project) {
                $project = trim($project);
                if ($project !== '') {
                    $projects[] = isset($jobNameMap[$project]) ? $jobNameMap[$project] : $this->replaceDeploymentEnvironmentToken($project, $sourceEnvironment, $targetEnvironment);
                }
            }
            while ($childProjectsNode->firstChild) {
                $childProjectsNode->removeChild($childProjectsNode->firstChild);
            }
            $childProjectsNode->appendChild($dom->createTextNode(implode(', ', $projects)));
        }

        $this->rewriteDeploymentAgent($dom, $sourceEnvironment, $targetEnvironment);
        return array('ok' => TRUE, 'xml' => $dom->saveXML());
    }

    private function replaceDeploymentEnvironmentToken($jobName, $sourceEnvironment, $targetEnvironment)
    {
        $pattern = '/(^|[._\/-])'.preg_quote($sourceEnvironment, '/').'($|[._\/-])/i';
        return preg_replace_callback($pattern, function($matches) use ($targetEnvironment) {
            return $matches[1].$targetEnvironment.$matches[2];
        }, $jobName, 1);
    }

    private function rewriteDeploymentAgent($dom, $sourceEnvironment, $targetEnvironment)
    {
        $assignedNode = $this->directDeploymentChild($dom->documentElement, 'assignedNode');
        if (! $assignedNode) {
            return;
        }
        $sourceLabel = $this->jenkinsEnvironmentAgentLabel($sourceEnvironment);
        if ($sourceLabel === '' || trim($assignedNode->nodeValue) !== $sourceLabel) {
            return;
        }
        $targetLabel = $this->jenkinsEnvironmentAgentLabel($targetEnvironment);
        while ($assignedNode->firstChild) {
            $assignedNode->removeChild($assignedNode->firstChild);
        }
        if ($targetLabel !== '') {
            $assignedNode->appendChild($dom->createTextNode($targetLabel));
        }
        $canRoam = $this->directDeploymentChild($dom->documentElement, 'canRoam');
        if ($canRoam) {
            while ($canRoam->firstChild) {
                $canRoam->removeChild($canRoam->firstChild);
            }
            $canRoam->appendChild($dom->createTextNode($targetLabel === '' ? 'true' : 'false'));
        }
    }

    private function rewriteDeploymentCommand($text, $sourceEnvironment, $targetEnvironment, $sourceJob, $targetJob)
    {
        $source = preg_quote($sourceEnvironment, '/');
        $updated = preg_replace('/(?<![A-Za-z0-9_-])(["\']?)(--?(?:context|environment))(\s*=\s*)'.$source.'\1(?![A-Za-z0-9_.-])/i', '$1$2$3'.$targetEnvironment.'$1', (string) $text);
        $updated = preg_replace('/(?<![A-Za-z0-9_-])(["\']?)(--?(?:context|environment))\1(\s+)(["\']?)'.$source.'\4(?![A-Za-z0-9_.-])/i', '$1$2$1$3$4'.$targetEnvironment.'$4', $updated);
        $assignmentNames = 'JOBSEEKER_ENVIRONMENT|JOBSEEKER_CONTEXT|CONTEXT_ENVIRONMENT|TARGET_ENVIRONMENT|ENVIRONMENT|CONTEXT';
        $updated = preg_replace('/^(\s*(?:export\s+)?(?:'.$assignmentNames.')\s*=\s*)(["\']?)'.$source.'\2(\s*)$/mi', '$1$2'.$targetEnvironment.'$2$3', $updated);

        $parts = preg_split('/(\r\n|\n|\r)/', $updated, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($index = 0; $index < count($parts); $index += 2) {
            if (strpos($parts[$index], 'JOBSEEKER_PYTHON') !== FALSE || strpos($parts[$index], 'JOBSEEKER_ENTRYPOINT') !== FALSE || preg_match('/(^|\s)python[0-9.]*\s/i', $parts[$index]) || preg_match('/\.py(["\']?)(\s|$)/i', $parts[$index])) {
                $parts[$index] = preg_replace('/(^|\s)(["\']?)'.$source.'\2(\s*)$/i', '$1$2'.$targetEnvironment.'$2$3', $parts[$index], 1);
            }
        }
        $updated = implode('', $parts);

        // Legacy inline Python jobs baked the environment in as a positional argument
        // to the entrypoint (docker:  ... "$@"' sh 'DEV' || ...   local:  "$JOBSEEKER_SCRIPT_PATH" 'DEV').
        // Jobs created after the "$JOBSEEKER_ENVIRONMENT" change need no rewrite here.
        $updated = preg_replace('/("\$@"\x27\s+sh\s+)([\x27"]?)'.$source.'\2(?=\s*(?:\|\||\r|\n|$))/', '${1}${2}'.$targetEnvironment.'${2}', $updated);
        $updated = preg_replace('/("\$JOBSEEKER_SCRIPT_PATH"\s+)([\x27"]?)'.$source.'\2(?=\s*(?:\r|\n|$))/', '${1}${2}'.$targetEnvironment.'${2}', $updated);

        $sourcePath = trim(str_replace('\\', '/', $sourceJob), '/');
        $targetPath = trim(str_replace('\\', '/', $targetJob), '/');
        foreach (array('talend/jobs/', 'bash/jobs/', 'batch/jobs/', 'python/jobs/', 'python/inline/') as $location) {
            $updated = str_replace('repository/'.$location.$sourcePath, 'repository/'.$location.$targetPath, $updated);
        }
        return $updated;
    }

    private function directDeploymentChild($parent, $tagName)
    {
        if (! $parent) {
            return NULL;
        }
        foreach ($parent->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->tagName === $tagName) {
                return $child;
            }
        }
        return NULL;
    }

    private function deploymentRepositoryRoot()
    {
        $jenkinsHome = isset($this->global['jenkins_home']) ? trim((string) $this->global['jenkins_home']) : '';
        return $jenkinsHome !== '' ? rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository' : FCPATH.'repository';
    }

    private function isTransientDeploymentPath($relativePath)
    {
        $relativePath = trim(str_replace('\\', '/', (string) $relativePath), '/');
        if ($relativePath === '') {
            return FALSE;
        }

        // Local build caches only - never a directory that could hold authored job
        // deliverables (e.g. a shell job's own build/ or dist/ folder).
        $transientDirectories = array(
            '.git', '.venv', 'venv', '.vscode', '.uv-cache', '.jobseeker-wheels', '.jobseeker-python-libs',
            '__pycache__', '.mypy_cache', '.ruff_cache', '.pytest_cache', 'htmlcov', 'node_modules'
        );
        foreach (explode('/', strtolower($relativePath)) as $segment) {
            if (in_array($segment, $transientDirectories, TRUE) || preg_match('/\.egg-info$/i', $segment) === 1) {
                return TRUE;
            }
        }

        $baseName = strtolower(basename($relativePath));
        if ($baseName === '.coverage' || $baseName === 'coverage.xml') {
            return TRUE;
        }
        if (($baseName === '.env' || strpos($baseName, '.env.') === 0) && $baseName !== '.env.example') {
            return TRUE;
        }
        return preg_match('/\.py[co]$/i', $baseName) === 1;
    }

    private function copyDeploymentDirectory($sourcePath, $targetPath)
    {
        if (! $this->ensureDirectory($targetPath)) {
            return FALSE;
        }
        $sourcePath = rtrim($sourcePath, DIRECTORY_SEPARATOR);
        $directory = new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($directory, function($current) use ($sourcePath) {
            $relativePath = substr($current->getPathname(), strlen($sourcePath) + 1);
            return ! $current->isLink() && ! $this->isTransientDeploymentPath($relativePath);
        });
        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                continue;
            }
            $relativePath = substr($item->getPathname(), strlen($sourcePath) + 1);
            $targetItem = $targetPath.DIRECTORY_SEPARATOR.$relativePath;
            if ($item->isDir()) {
                if (! $this->ensureDirectory($targetItem)) {
                    return FALSE;
                }
            } elseif (! $item->isFile() || ! $this->ensureDirectory(dirname($targetItem)) || ! copy($item->getPathname(), $targetItem)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    private function copyDeploymentEnvironmentFiles($sourcePath, $targetPath)
    {
        if (! is_dir($sourcePath) || ! is_dir($targetPath) || is_link($sourcePath) || is_link($targetPath)) {
            return FALSE;
        }
        $sourcePath = rtrim($sourcePath, DIRECTORY_SEPARATOR);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($sourcePath) + 1);
            $baseName = strtolower(basename(str_replace('\\', '/', $relativePath)));
            if (($baseName !== '.env' && strpos($baseName, '.env.') !== 0) || $baseName === '.env.example') {
                continue;
            }
            $targetItem = $targetPath.DIRECTORY_SEPARATOR.$relativePath;
            if ($item->isLink() || ! $item->isFile() || ! $this->ensureDirectory(dirname($targetItem)) || ! copy($item->getPathname(), $targetItem)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    private function removeDeploymentDirectory($path)
    {
        if (! is_dir($path)) {
            return TRUE;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($iterator as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                if (! rmdir($item->getPathname())) {
                    return FALSE;
                }
            } elseif (! unlink($item->getPathname())) {
                return FALSE;
            }
        }
        return rmdir($path);
    }

    private function copyDeploymentArtifacts($sourceJob, $targetJob, $overwrite)
    {
        $root = realpath($this->deploymentRepositoryRoot());
        if ($root === FALSE || $sourceJob === $targetJob) {
            return array('ok' => TRUE, 'copied' => 0);
        }
        $copied = 0;
        foreach (array('talend/jobs', 'bash/jobs', 'batch/jobs', 'python/jobs', 'python/inline') as $location) {
            $sourceRelative = $this->safeRelativePath($location.'/'.$sourceJob);
            $targetRelative = $this->safeRelativePath($location.'/'.$targetJob);
            if ($sourceRelative === FALSE || $targetRelative === FALSE) {
                return array('ok' => FALSE, 'message' => 'A pipeline job resolved to an unsafe repository path.');
            }
            $sourcePath = $root.DIRECTORY_SEPARATOR.$sourceRelative;
            $targetPath = $root.DIRECTORY_SEPARATOR.$targetRelative;
            if (! is_dir($sourcePath)) {
                continue;
            }
            if (is_link($sourcePath) || is_link($targetPath) || ! $this->pathWithinBase($sourcePath, $root) || ! $this->pathWithinBase($targetPath, $root)) {
                return array('ok' => FALSE, 'message' => 'A pipeline job repository path is not safe to deploy.');
            }
            if (is_dir($targetPath) && ! $overwrite) {
                return array('ok' => FALSE, 'message' => 'Repository payload for '.$targetJob.' already exists. Enable overwrite to replace it.');
            }
            $token = '.pipeline-deploy-'.substr(sha1(uniqid('', TRUE)), 0, 12);
            $stagePath = dirname($targetPath).DIRECTORY_SEPARATOR.$token.'-stage';
            $backupPath = dirname($targetPath).DIRECTORY_SEPARATOR.$token.'-backup';
            if (! $this->ensureDirectory(dirname($targetPath)) || ! $this->copyDeploymentDirectory($sourcePath, $stagePath)) {
                $this->removeDeploymentDirectory($stagePath);
                return array('ok' => FALSE, 'message' => 'Repository payload for '.$sourceJob.' could not be staged.');
            }
            $hadTarget = is_dir($targetPath);
            if ($hadTarget && ! $this->copyDeploymentEnvironmentFiles($targetPath, $stagePath)) {
                $this->removeDeploymentDirectory($stagePath);
                return array('ok' => FALSE, 'message' => 'Target environment files for '.$targetJob.' could not be preserved.');
            }
            if ($hadTarget && ! rename($targetPath, $backupPath)) {
                $this->removeDeploymentDirectory($stagePath);
                return array('ok' => FALSE, 'message' => 'Existing repository payload for '.$targetJob.' could not be backed up.');
            }
            if (! rename($stagePath, $targetPath)) {
                if ($hadTarget) {
                    rename($backupPath, $targetPath);
                }
                $this->removeDeploymentDirectory($stagePath);
                return array('ok' => FALSE, 'message' => 'Repository payload for '.$targetJob.' could not be installed.');
            }
            if ($hadTarget) {
                $this->removeDeploymentDirectory($backupPath);
            }
            $copied++;
        }
        return array('ok' => TRUE, 'copied' => $copied);
    }

    private function deployPreparedJobs($prepared, $overwrite)
    {
        $deployed = 0;
        $artifactFolders = 0;
        foreach ($prepared as $job) {
            $artifacts = $this->copyDeploymentArtifacts($job['sourceJob'], $job['targetJob'], $overwrite);
            if (! $artifacts['ok']) {
                return $artifacts;
            }
            $artifactFolders += $artifacts['copied'];
            $targetPath = $this->jenkinsJobPath($job['targetJob']);
            $response = $job['targetExists']
                ? $this->requestJenkins('POST', $targetPath.'/config.xml', $job['xml'], 'text/xml')
                : $this->requestJenkins('POST', $this->jenkinsCreateItemPath($job['targetJob']), $job['xml'], 'text/xml');
            if (! $this->successfulJenkinsStatus($response['status'])) {
                return array('ok' => FALSE, 'message' => 'Jenkins refused pipeline job '.$job['targetJob'].' (HTTP '.$response['status'].').');
            }
            $deployed++;
        }
        return array('ok' => TRUE, 'deployed' => $deployed, 'artifactFolders' => $artifactFolders);
    }

    /**
     * Validate a pipeline schedule expression with the same offline Jenkins-cron
     * grammar Job Creation uses (JenkinsCronSchedule), so pipelines reject the
     * Quartz-only tokens Jenkins' TimerTrigger would refuse and give a specific
     * reason rather than a generic "invalid schedule".
     *
     * @return array{ok:bool,spec:string,error:string,warnings:string[]}
     */
    private function validatePipelineCron($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return array('ok' => FALSE, 'spec' => '', 'error' => 'Enter a Jenkins cron schedule such as "H 2 * * 1-5".', 'warnings' => array());
        }
        if (strlen($value) > 120) {
            return array('ok' => FALSE, 'spec' => '', 'error' => 'The cron expression is too long (120 characters maximum).', 'warnings' => array());
        }

        $check = $this->cronSchedule->validateSpec($value);
        return array(
            'ok'       => $check['ok'],
            'spec'     => $check['normalized'],
            'error'    => $check['error'],
            'warnings' => $check['warnings'],
        );
    }

    /**
     * Live schedule validation for the pipeline editor: offline grammar check
     * plus Jenkins' own previous / next run times.
     */
    public function validateSchedule()
    {
        if (! $this->canManagePipelines()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return;
        }

        $cron = $this->validatePipelineCron($this->input->post('schedule_cron'));
        $payload = array(
            'ok'       => $cron['ok'],
            'spec'     => $cron['spec'],
            'error'    => $cron['error'],
            'warnings' => $cron['warnings'],
        );

        if ($cron['ok'] && $cron['spec'] !== '' && strpos($cron['spec'], '@') !== 0) {
            $check = $this->requestJenkins('GET', 'descriptorByName/hudson.triggers.TimerTrigger/checkSpec?value=' . rawurlencode($cron['spec']));
            if ((int) $check['status'] === 200 && preg_match('#<div class="(ok|warning|error)">(.*?)</div>#s', (string) $check['body'], $m)) {
                $payload['jenkins'] = array(
                    'level'   => $m[1],
                    'message' => trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                );
                if ($m[1] === 'error') {
                    $payload['ok'] = FALSE;
                    if ($payload['error'] === '') {
                        $payload['error'] = $payload['jenkins']['message'];
                    }
                }
            }
        }

        $this->jsonResponse($payload);
    }

    private function pipelineXml($pipeline, $script)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = TRUE;
        $root = $dom->createElement('flow-definition');
        $root->setAttribute('plugin', 'workflow-job');
        $dom->appendChild($root);
        $root->appendChild($dom->createElement('actions'));
        $description = $dom->createElement('description');
        $description->appendChild($dom->createTextNode('JobSeeker pipeline: '.$pipeline->name.' ['.$pipeline->environment.']'));
        $root->appendChild($description);
        $root->appendChild($dom->createElement('keepDependencies', 'false'));
        $properties = $dom->createElement('properties');
        $parametersProperty = $dom->createElement('hudson.model.ParametersDefinitionProperty');
        $parameterDefinitions = $dom->createElement('parameterDefinitions');
        $environmentParameter = $dom->createElement('hudson.model.StringParameterDefinition');
        $environmentParameter->appendChild($dom->createElement('name', 'ENVIRONMENT'));
        $environmentParameter->appendChild($dom->createElement('description', 'JobSeeker global pipeline environment'));
        $environmentParameter->appendChild($dom->createElement('defaultValue', $pipeline->environment));
        $environmentParameter->appendChild($dom->createElement('trim', 'true'));
        $parameterDefinitions->appendChild($environmentParameter);
        $parametersProperty->appendChild($parameterDefinitions);
        $properties->appendChild($parametersProperty);
        $disableConcurrent = $dom->createElement('org.jenkinsci.plugins.workflow.job.properties.DisableConcurrentBuildsJobProperty');
        $disableConcurrent->appendChild($dom->createElement('abortPrevious', 'false'));
        $properties->appendChild($disableConcurrent);
        $root->appendChild($properties);
        $definition = $dom->createElement('definition');
        $definition->setAttribute('class', 'org.jenkinsci.plugins.workflow.cps.CpsFlowDefinition');
        $definition->setAttribute('plugin', 'workflow-cps');
        $definition->appendChild($dom->createElement('script'))->appendChild($dom->createCDATASection($script));
        $definition->appendChild($dom->createElement('sandbox', 'true'));
        $root->appendChild($definition);
        $triggers = $dom->createElement('triggers');
        if (! empty($pipeline->schedule_enabled) && ! empty($pipeline->schedule_cron)) {
            $timer = $dom->createElement('hudson.triggers.TimerTrigger');
            $spec = $dom->createElement('spec');
            $spec->appendChild($dom->createTextNode($pipeline->schedule_cron));
            $timer->appendChild($spec);
            $triggers->appendChild($timer);
        }
        $root->appendChild($triggers);
        $root->appendChild($dom->createElement('disabled', $pipeline->is_active ? 'false' : 'true'));
        return $dom->saveXML();
    }

    private function syncPipeline($pipeline, $graph)
    {
        $jobName = $pipeline->jenkins_job_name ?: $this->pipelineJobName($pipeline->id, $pipeline->pipeline_key);
        try {
            $script = $this->compiler->compileScript($pipeline->name, $pipeline->environment, $graph['nodes'], $graph['edges']);
        } catch (Exception $exception) {
            $this->pipelines->updateSync($pipeline->id, $jobName, 'failed', $exception->getMessage());
            return array('ok' => FALSE, 'status' => 422, 'message' => $exception->getMessage(), 'jobName' => $jobName);
        }
        $xml = $this->pipelineXml($pipeline, $script);
        $jobPath = $this->jenkinsJobPath($jobName);
        $exists = $this->requestJenkins('GET', $jobPath.'/api/json');
        if ((int) $exists['status'] === 200) {
            $response = $this->requestJenkins('POST', $jobPath.'/config.xml', $xml, 'text/xml');
        } else if ((int) $exists['status'] === 404) {
            $response = $this->requestJenkins('POST', 'createItem?name='.rawurlencode($jobName), $xml, 'text/xml');
        } else {
            $response = $exists;
        }
        $ok = $this->successfulJenkinsStatus($response['status']);
        $message = $ok ? 'Pipeline synchronized with Jenkins.' : 'Jenkins rejected the pipeline definition (HTTP '.$response['status'].').';
        $this->pipelines->updateSync($pipeline->id, $jobName, $ok ? 'synced' : 'failed', $ok ? '' : $message);
        return array('ok' => $ok, 'status' => $ok ? 200 : 502, 'message' => $message, 'jobName' => $jobName);
    }

    public function save()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $environment = $this->selectedEnvironment();
        if ($environment === 'ALL') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Select a global environment before saving a pipeline.'), 422);
            return;
        }
        $id = (int) $this->input->post('id');
        $existing = $id > 0 ? $this->pipelines->getPipeline($id) : NULL;
        if ($id > 0 && (! $existing || $existing->environment !== $environment)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Pipeline not found in the selected environment.'), 404);
            return;
        }
        $name = trim((string) $this->input->post('name'));
        $key = $this->normalizePipelineKey($this->input->post('pipeline_key'));
        $groupName = trim((string) $this->input->post('group_name'));
        $description = trim((string) $this->input->post('description'));
        $scheduleEnabled = $this->input->post('schedule_enabled') === '1';
        $scheduleCronResult = $scheduleEnabled ? $this->validatePipelineCron($this->input->post('schedule_cron')) : NULL;
        $scheduleCron = $scheduleEnabled && $scheduleCronResult['ok'] ? $scheduleCronResult['spec'] : ($scheduleEnabled ? FALSE : NULL);
        if ($name === '' || strlen($name) > 200 || preg_match('/[\x00-\x1F\x7F]/', $name)
            || $key === '' || $groupName === '' || strlen($groupName) > 128 || preg_match('/[\x00-\x1F\x7F]/', $groupName)
            || strlen($description) > 2000 || strpos($description, "\0") !== FALSE) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Check the pipeline name, key, group, and description.'), 422);
            return;
        }
        if ($scheduleEnabled && $scheduleCron === FALSE) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Schedule: '.$scheduleCronResult['error']), 422);
            return;
        }
        if ($this->pipelines->scopeExists($key, $environment, $id)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'That pipeline key already exists in this environment.'), 409);
            return;
        }
        $nodes = json_decode((string) $this->input->post('nodes_json'), TRUE);
        $edges = json_decode((string) $this->input->post('edges_json'), TRUE);
        $validation = $this->compiler->validateGraph($nodes, $edges);
        if (! $validation['ok']) {
            $this->jsonResponse($validation, 422);
            return;
        }
        $available = $this->availableJobs($environment);
        if (! $available['ok']) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Jenkins jobs could not be validated.'), 502);
            return;
        }
        $availableNames = array_fill_keys(array_map(function($job) { return $job['name']; }, $available['jobs']), TRUE);
        foreach ($validation['nodes'] as $node) {
            if (! isset($availableNames[$node['job']])) {
                $this->jsonResponse(array('ok' => FALSE, 'message' => 'Jenkins job is unavailable in '.$environment.': '.$node['job']), 422);
                return;
            }
        }
        $now = date('Y-m-d H:i:s');
        $graph = array('nodes' => $validation['nodes'], 'edges' => $validation['edges']);
        $data = array(
            'pipeline_key' => $key,
            'name' => $name,
            'group_name' => $groupName,
            'description' => $description,
            'environment' => $environment,
            'graph_json' => json_encode($graph, JSON_UNESCAPED_SLASHES),
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'schedule_enabled' => $scheduleEnabled ? 1 : 0,
            'schedule_cron' => $scheduleEnabled ? $scheduleCron : NULL,
            'version' => $existing ? (int) $existing->version + 1 : 1,
            'updated_at' => $now,
            'owner' => $this->name
        );
        if (! $existing) {
            $data['created_at'] = $now;
        }
        $savedId = $this->pipelines->savePipeline($data, $id);
        if ($savedId <= 0) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Pipeline could not be saved.'), 500);
            return;
        }
        $pipeline = $this->pipelines->getPipeline($savedId);
        $sync = $this->syncPipeline($pipeline, $graph);
        $this->jsonResponse(array(
            'ok' => $sync['ok'],
            'id' => $savedId,
            'pipeline' => $pipeline,
            'layers' => $validation['layers'],
            'jenkinsJobName' => $sync['jobName'],
            'message' => $sync['message']
        ), $sync['status']);
    }

    public function deploy()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $sourceEnvironment = $this->selectedEnvironment();
        $source = $this->pipelines->getPipeline((int) $this->input->post('id'));
        $targetEnvironment = $this->normalizeJobSeekerEnvironment($this->input->post('target_environment'));
        if (! $source || $sourceEnvironment === 'ALL' || $source->environment !== $sourceEnvironment) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Source pipeline not found in the selected environment.'), 404);
            return;
        }
        if ($targetEnvironment === '' || $targetEnvironment === 'ALL' || ! in_array($targetEnvironment, $this->activeEnvironments(), TRUE)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Select an active target environment.'), 422);
            return;
        }
        if ($targetEnvironment === $sourceEnvironment) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Source and target environments must be different.'), 422);
            return;
        }
        $targetJobs = $this->availableJobs($targetEnvironment);
        if (! $targetJobs['ok']) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Target environment jobs could not be loaded from Jenkins.'), 502);
            return;
        }
        $deployment = $this->deploymentGraph($this->graphPayload($source), $sourceEnvironment, $targetEnvironment, $targetJobs['jobs']);
        $overwrite = $this->input->post('overwrite') === '1';
        $preparedJobs = $this->prepareDeploymentJobs($deployment['mappings'], $sourceEnvironment, $targetEnvironment, $overwrite);
        if (! $preparedJobs['ok']) {
            $this->jsonResponse($preparedJobs, 422);
            return;
        }
        $validation = $this->compiler->validateGraph($deployment['graph']['nodes'], $deployment['graph']['edges']);
        if (! $validation['ok']) {
            $this->jsonResponse($validation, 422);
            return;
        }
        $target = $this->pipelines->getPipelineByScope($source->pipeline_key, $targetEnvironment);
        if ($target && ! $overwrite) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'This pipeline already exists in '.$targetEnvironment.'. Enable overwrite to deploy a new version.', 'targetId' => (int) $target->id), 409);
            return;
        }
        $jobDeployment = $this->deployPreparedJobs($preparedJobs['jobs'], $overwrite);
        if (! $jobDeployment['ok']) {
            $this->jsonResponse($jobDeployment, 502);
            return;
        }
        $now = date('Y-m-d H:i:s');
        $graph = array('nodes' => $validation['nodes'], 'edges' => $validation['edges']);
        $data = array(
            'pipeline_key' => $source->pipeline_key,
            'name' => $source->name,
            'group_name' => $source->group_name,
            'description' => $source->description,
            'environment' => $targetEnvironment,
            'graph_json' => json_encode($graph, JSON_UNESCAPED_SLASHES),
            'is_active' => (int) $source->is_active,
            'schedule_enabled' => (int) $source->schedule_enabled,
            'schedule_cron' => $source->schedule_cron,
            'version' => $target ? (int) $target->version + 1 : 1,
            'updated_at' => $now,
            'owner' => $this->name
        );
        if (! $target) {
            $data['created_at'] = $now;
        }
        $targetId = $this->pipelines->savePipeline($data, $target ? $target->id : 0);
        if ($targetId <= 0) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Target pipeline could not be saved.'), 500);
            return;
        }
        $target = $this->pipelines->getPipeline($targetId);
        $sync = $this->syncPipeline($target, $graph);
        $this->jsonResponse(array(
            'ok' => $sync['ok'],
            'id' => $targetId,
            'environment' => $targetEnvironment,
            'version' => (int) $target->version,
            'mappings' => $deployment['mappings'],
            'deployedJobs' => $jobDeployment['deployed'],
            'artifactFolders' => $jobDeployment['artifactFolders'],
            'message' => $sync['ok'] ? 'Pipeline and '.$jobDeployment['deployed'].' job(s) deployed to '.$targetEnvironment.'.' : $sync['message']
        ), $sync['status']);
    }

    private function queueIdFromHeaders($headers)
    {
        foreach (is_array($headers) ? $headers : array() as $header) {
            if (preg_match('#^Location:.*?/queue/item/(\d+)/?#i', trim($header), $matches)) {
                return (int) $matches[1];
            }
        }
        return NULL;
    }

    public function run()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $pipeline = $this->pipelines->getPipeline((int) $this->input->post('id'));
        $environment = $this->selectedEnvironment();
        if (! $pipeline || ! $pipeline->is_active || $environment === 'ALL' || $pipeline->environment !== $environment) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Active pipeline not found in the selected environment.'), 404);
            return;
        }
        $graph = $this->graphPayload($pipeline);
        $sync = $this->syncPipeline($pipeline, $graph);
        if (! $sync['ok']) {
            $this->jsonResponse($sync, $sync['status']);
            return;
        }
        $jobPath = $this->jenkinsJobPath($sync['jobName']);
        $jobInfoResponse = $this->requestJenkins('GET', $jobPath.'/api/json?tree=nextBuildNumber');
        $jobInfo = (int) $jobInfoResponse['status'] === 200 ? json_decode($jobInfoResponse['body'], TRUE) : array();
        $expectedBuildNumber = isset($jobInfo['nextBuildNumber']) ? (int) $jobInfo['nextBuildNumber'] : NULL;
        $body = http_build_query(array('ENVIRONMENT' => $pipeline->environment), '', '&', PHP_QUERY_RFC3986);
        $response = $this->requestJenkins('POST', $jobPath.'/buildWithParameters', $body, 'application/x-www-form-urlencoded');
        if (! $this->successfulJenkinsStatus($response['status'])) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Jenkins could not queue the pipeline (HTTP '.$response['status'].').'), 502);
            return;
        }
        $queueId = $this->queueIdFromHeaders($response['headers']);
        $runId = $this->pipelines->createRun($pipeline->id, $pipeline->environment, $this->name, $queueId);
        if ($expectedBuildNumber !== NULL) {
            $this->pipelines->updateRun($runId, array('jenkins_build_number' => $expectedBuildNumber));
        }
        $this->jsonResponse(array('ok' => TRUE, 'runId' => $runId, 'queueId' => $queueId, 'message' => 'Pipeline queued.'));
    }

    private function parseNodeStates($console)
    {
        $states = array();
        if (preg_match_all('/^JOBSEEKER_PIPELINE_NODE\|([^|]+)\|([^|]+)\|([^|]+)\|([^\r\n]*)/m', (string) $console, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $states[$match[1]] = array(
                    'nodeId' => $match[1],
                    'job' => $match[2],
                    'status' => strtoupper($match[3]),
                    'buildNumber' => trim($match[4]) === '' ? NULL : (int) $match[4]
                );
            }
        }
        return array_values($states);
    }

    public function status($runId)
    {
        if (! $this->canManagePipelines()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return;
        }
        $run = $this->pipelines->getRun((int) $runId);
        $pipeline = $run ? $this->pipelines->getPipeline($run->pipeline_id) : NULL;
        $environment = $this->selectedEnvironment();
        if (! $run || ! $pipeline || ($environment !== 'ALL' && $pipeline->environment !== $environment)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Pipeline run not found.'), 404);
            return;
        }
        $buildNumber = $run->jenkins_build_number ? (int) $run->jenkins_build_number : NULL;
        $queueStatus = '';
        if ($buildNumber === NULL && $run->jenkins_queue_id) {
            $queueResponse = $this->requestJenkins('GET', 'queue/item/'.(int) $run->jenkins_queue_id.'/api/json');
            if ((int) $queueResponse['status'] === 200) {
                $queue = json_decode($queueResponse['body'], TRUE);
                if (isset($queue['executable']['number'])) {
                    $buildNumber = (int) $queue['executable']['number'];
                    $this->pipelines->updateRun($run->id, array('jenkins_build_number' => $buildNumber, 'status' => 'RUNNING'));
                } else if (! empty($queue['cancelled'])) {
                    $queueStatus = 'ABORTED';
                } else {
                    $queueStatus = 'QUEUED';
                }
            } else if ((int) $queueResponse['status'] === 404) {
                $queueStatus = $run->status;
            }
        }
        if ($buildNumber === NULL) {
            if ($queueStatus === 'ABORTED') {
                $this->pipelines->updateRun($run->id, array('status' => 'ABORTED', 'completed_at' => date('Y-m-d H:i:s')));
            }
            $this->jsonResponse(array('ok' => TRUE, 'runId' => (int) $run->id, 'status' => $queueStatus ?: 'QUEUED', 'nodes' => array(), 'buildNumber' => NULL));
            return;
        }
        $jobPath = $this->jenkinsJobPath($pipeline->jenkins_job_name);
        $buildResponse = $this->requestJenkins('GET', $jobPath.'/'.$buildNumber.'/api/json');
        if ((int) $buildResponse['status'] === 404 && $run->status === 'QUEUED') {
            $this->jsonResponse(array('ok' => TRUE, 'runId' => (int) $run->id, 'status' => 'QUEUED', 'nodes' => array(), 'buildNumber' => $buildNumber));
            return;
        }
        if ((int) $buildResponse['status'] !== 200) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Unable to read the Jenkins pipeline build.'), 502);
            return;
        }
        $build = json_decode($buildResponse['body'], TRUE);
        $status = ! empty($build['building']) ? 'RUNNING' : strtoupper(isset($build['result']) ? (string) $build['result'] : 'UNKNOWN');
        $consoleResponse = $this->requestJenkins('GET', $jobPath.'/'.$buildNumber.'/consoleText');
        $console = (int) $consoleResponse['status'] === 200 ? $consoleResponse['body'] : '';
        $update = array('jenkins_build_number' => $buildNumber, 'status' => $status);
        if ($status !== 'RUNNING' && $status !== 'QUEUED') {
            $update['completed_at'] = date('Y-m-d H:i:s');
        }
        $this->pipelines->updateRun($run->id, $update);
        $this->jsonResponse(array(
            'ok' => TRUE,
            'runId' => (int) $run->id,
            'status' => $status,
            'nodes' => $this->parseNodeStates($console),
            'buildNumber' => $buildNumber,
            'duration' => isset($build['duration']) ? (int) $build['duration'] : 0,
            'timestamp' => isset($build['timestamp']) ? (int) $build['timestamp'] : 0
        ));
    }

    public function stop()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $run = $this->pipelines->getRun((int) $this->input->post('run_id'));
        $pipeline = $run ? $this->pipelines->getPipeline($run->pipeline_id) : NULL;
        $environment = $this->selectedEnvironment();
        if (! $run || ! $pipeline || $environment === 'ALL' || $pipeline->environment !== $environment) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Pipeline run not found.'), 404);
            return;
        }
        if ($run->status === 'QUEUED' && $run->jenkins_queue_id) {
            $response = $this->requestJenkins('POST', 'queue/cancelItem?id='.(int) $run->jenkins_queue_id);
        } else if ($run->jenkins_build_number) {
            $response = $this->requestJenkins('POST', $this->jenkinsJobPath($pipeline->jenkins_job_name).'/'.(int) $run->jenkins_build_number.'/stop');
        } else if ($run->jenkins_queue_id) {
            $response = $this->requestJenkins('POST', 'queue/cancelItem?id='.(int) $run->jenkins_queue_id);
        } else {
            $response = array('status' => 409);
        }
        $ok = $this->successfulJenkinsStatus($response['status']);
        if ($ok) {
            $this->pipelines->updateRun($run->id, array('status' => 'ABORTED', 'completed_at' => date('Y-m-d H:i:s')));
        }
        $this->jsonResponse(array('ok' => $ok, 'message' => $ok ? 'Pipeline stop requested.' : 'Pipeline could not be stopped.'), $ok ? 200 : 409);
    }

    public function delete()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $pipeline = $this->pipelines->getPipeline((int) $this->input->post('id'));
        $environment = $this->selectedEnvironment();
        if (! $pipeline || $environment === 'ALL' || $pipeline->environment !== $environment) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Pipeline not found.'), 404);
            return;
        }
        if ($pipeline->jenkins_job_name) {
            $response = $this->requestJenkins('POST', $this->jenkinsJobPath($pipeline->jenkins_job_name).'/doDelete');
            if (! $this->successfulJenkinsStatus($response['status']) && (int) $response['status'] !== 404) {
                $this->jsonResponse(array('ok' => FALSE, 'message' => 'Jenkins pipeline could not be deleted.'), 502);
                return;
            }
        }
        $deleted = $this->pipelines->deletePipeline($pipeline->id);
        $this->jsonResponse(array('ok' => $deleted > 0, 'message' => $deleted > 0 ? 'Pipeline deleted.' : 'Pipeline could not be deleted.'), $deleted > 0 ? 200 : 500);
    }
}

?>
