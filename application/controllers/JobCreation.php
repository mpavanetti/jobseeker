<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class JobCreation extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
     //   $this->load->model('files_model');
        $this->isLoggedIn();   
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : Job Creation';
      $data = array('job_creation_dates' => $this->readJobCreationDates());
        
      $this->loadViews("jobCreation", $this->global, $data, NULL);

    }

    private function canManageJobs() {
      return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function jenkinsJobPath($jobName) {
      $segments = explode('/', trim((string) $jobName, '/'));
      $path = array();

      foreach ($segments as $segment) {
        if ($segment !== '') {
          $path[] = 'job/' . rawurlencode($segment);
        }
      }

      return implode('/', $path);
    }

    private function isSuccessfulJenkinsStatus($status) {
      return in_array((int) $status, array(200, 201, 302, 303), TRUE);
    }

    private function saveGeneratedJenkinsJob($jobName, $xml) {
      $jobPath = $this->jenkinsJobPath($jobName);
      $jobResponse = $this->requestJenkins('GET', $jobPath . '/api/json');

      if ((int) $jobResponse['status'] === 200) {
        $saveResponse = $this->requestJenkins('POST', $jobPath . '/config.xml', $xml, 'text/xml');

        return array(
          'ok' => $this->isSuccessfulJenkinsStatus($saveResponse['status']),
          'updated' => TRUE,
          'status' => $saveResponse['status']
        );
      }

      if ((int) $jobResponse['status'] === 404) {
        $saveResponse = $this->requestJenkins('POST', 'createItem?name=' . rawurlencode($jobName), $xml, 'text/xml');

        return array(
          'ok' => $this->isSuccessfulJenkinsStatus($saveResponse['status']),
          'updated' => FALSE,
          'status' => $saveResponse['status']
        );
      }

      return array(
        'ok' => FALSE,
        'updated' => FALSE,
        'status' => $jobResponse['status']
      );
    }

    private function cleanSubmittedJobNameList($jobNames) {
      if ($jobNames === NULL || $jobNames === '') {
        return array('ok' => TRUE, 'message' => '', 'names' => array());
      }

      if (! is_array($jobNames)) {
        $jobNames = preg_split('/[\r\n,;]+/', (string) $jobNames);
      }

      $cleanNames = array();
      $seen = array();

      foreach ((array) $jobNames as $jobName) {
        $jobName = trim((string) $jobName);
        if ($jobName === '') {
          continue;
        }

        $clean = $this->cleanSubmittedJobName($jobName);
        if (! $clean['ok']) {
          return array('ok' => FALSE, 'message' => $clean['message'], 'names' => array());
        }

        if (! isset($seen[$clean['name']])) {
          $seen[$clean['name']] = TRUE;
          $cleanNames[] = $clean['name'];
        }
      }

      return array('ok' => TRUE, 'message' => '', 'names' => $cleanNames);
    }

    private function firstDirectChildElement($parent, $tagName) {
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

    private function createBuildTriggerThreshold($dom, $optionsRadios) {
      $isFailureThreshold = (string) $optionsRadios === '2';
      $threshold = $dom->createElement('threshold');

      $this->appendTextElement($dom, $threshold, 'name', $isFailureThreshold ? 'FAILURE' : 'SUCCESS');
      $this->appendTextElement($dom, $threshold, 'ordinal', $isFailureThreshold ? '2' : '0');
      $this->appendTextElement($dom, $threshold, 'color', $isFailureThreshold ? 'RED' : 'BLUE');
      $this->appendTextElement($dom, $threshold, 'completeBuild', 'true');

      return $threshold;
    }

    private function createRuntimeEnvironmentProperties($dom, $environment) {
      $properties = $dom->createElement('properties');
      $parametersProperty = $dom->createElement('hudson.model.ParametersDefinitionProperty');
      $parameterDefinitions = $dom->createElement('parameterDefinitions');
      $environmentParameter = $dom->createElement('hudson.model.StringParameterDefinition');

      $this->appendTextElement($dom, $environmentParameter, 'name', 'ENVIRONMENT');
      $this->appendTextElement($dom, $environmentParameter, 'description', 'Runtime environment managed by JobSeeker.');
      $this->appendTextElement($dom, $environmentParameter, 'defaultValue', $environment);
      $this->appendTextElement($dom, $environmentParameter, 'trim', 'true');

      $parameterDefinitions->appendChild($environmentParameter);
      $parametersProperty->appendChild($parameterDefinitions);
      $properties->appendChild($parametersProperty);

      return $properties;
    }

    private function appendJenkinsEnvironmentAgentAssignment($dom, $root, $environment) {
      $agentLabel = $this->jenkinsEnvironmentAgentLabel($environment);
      if ($agentLabel === '') {
        return;
      }

      $this->appendTextElement($dom, $root, 'assignedNode', $agentLabel);
      $this->appendTextElement($dom, $root, 'canRoam', 'false');
    }

    private function createShellCommandJobXml($description, $command, $environment) {
      $dom = new DOMDocument();
      $dom->encoding = 'UTF-8';
      $dom->xmlVersion = '1.1';
      $dom->formatOutput = true;

      $root = $dom->createElement('project');
      $this->appendTextElement($dom, $root, 'description', $description);
      $root->appendChild($this->createRuntimeEnvironmentProperties($dom, $environment));
      $this->appendJenkinsEnvironmentAgentAssignment($dom, $root, $environment);

      $builders = $dom->createElement('builders');
      $shell = $dom->createElement('hudson.tasks.Shell');
      $this->appendTextElement($dom, $shell, 'command', $command);
      $builders->appendChild($shell);
      $root->appendChild($builders);

      $root->appendChild($dom->createElement('publishers'));

      $buildWrappers = $dom->createElement('buildWrappers');
      $timestamper = $dom->createElement('hudson.plugins.timestamper.TimestamperBuildWrapper');
      $timestamper->setAttributeNode(new DOMAttr('plugin', 'timestamper@1.10'));
      $buildWrappers->appendChild($timestamper);
      $root->appendChild($buildWrappers);

      $dom->appendChild($root);

      return $dom->saveXML();
    }

    private function jsonJobCreationResponse($payload, $status = 200) {
      $this->output
        ->set_status_header((int) $status)
        ->set_content_type('application/json', 'utf-8')
        ->set_output(json_encode($payload));
    }

    private function jenkinsQueueIdFromHeaders($headers) {
      foreach (is_array($headers) ? $headers : array() as $header) {
        if (stripos($header, 'Location:') !== 0) {
          continue;
        }

        if (preg_match('#/queue/item/(\d+)/?#', $header, $matches)) {
          return $matches[1];
        }
      }

      return '';
    }

    private function waitForJenkinsBuildResult($jobName, $queueId, $timeoutSeconds) {
      $jobPath = $this->jenkinsJobPath($jobName);
      $deadline = microtime(TRUE) + max(5, (int) $timeoutSeconds);
      $buildNumber = '';
      $queueWhy = '';

      while (microtime(TRUE) <= $deadline) {
        if ($queueId !== '' && $buildNumber === '') {
          $queueResponse = $this->requestJenkins('GET', 'queue/item/' . rawurlencode($queueId) . '/api/json?tree=id,why,cancelled,executable[number,url]');
          if ((int) $queueResponse['status'] === 200) {
            $queuePayload = json_decode($queueResponse['body']);
            if (is_object($queuePayload)) {
              if (isset($queuePayload->cancelled) && $queuePayload->cancelled) {
                return array('ok' => FALSE, 'status' => 'CANCELLED', 'buildNumber' => '', 'queueWhy' => 'Cancelled in Jenkins.');
              }

              if (isset($queuePayload->why)) {
                $queueWhy = (string) $queuePayload->why;
              }

              if (isset($queuePayload->executable) && isset($queuePayload->executable->number)) {
                $buildNumber = (string) $queuePayload->executable->number;
              }
            }
          }
        }

        if ($buildNumber === '') {
          $jobResponse = $this->requestJenkins('GET', $jobPath . '/api/json?tree=queueItem[id,why],lastBuild[number,building,result]');
          if ((int) $jobResponse['status'] === 200) {
            $jobPayload = json_decode($jobResponse['body']);
            if (is_object($jobPayload)) {
              if (isset($jobPayload->queueItem) && isset($jobPayload->queueItem->why)) {
                $queueWhy = (string) $jobPayload->queueItem->why;
              }

              if (isset($jobPayload->lastBuild) && isset($jobPayload->lastBuild->number)) {
                $buildNumber = (string) $jobPayload->lastBuild->number;
              }
            }
          }
        }

        if ($buildNumber !== '') {
          $buildResponse = $this->requestJenkins('GET', $jobPath . '/' . rawurlencode($buildNumber) . '/api/json?tree=number,building,result,duration,timestamp,url');
          if ((int) $buildResponse['status'] === 200) {
            $buildPayload = json_decode($buildResponse['body']);
            if (is_object($buildPayload) && isset($buildPayload->building) && $buildPayload->building !== TRUE) {
              $result = isset($buildPayload->result) && $buildPayload->result !== NULL ? (string) $buildPayload->result : 'UNKNOWN';
              return array('ok' => $result === 'SUCCESS', 'status' => $result, 'buildNumber' => $buildNumber, 'queueWhy' => $queueWhy);
            }
          }
        }

        usleep(1000000);
      }

      return array('ok' => FALSE, 'status' => 'TIMEOUT', 'buildNumber' => $buildNumber, 'queueWhy' => $queueWhy);
    }

    private function jenkinsBuildConsoleText($jobName, $buildNumber) {
      if ($buildNumber === '') {
        return '';
      }

      $response = $this->requestJenkins('GET', $this->jenkinsJobPath($jobName) . '/' . rawurlencode($buildNumber) . '/consoleText');
      if ((int) $response['status'] !== 200) {
        return 'Unable to read Jenkins console output. HTTP '.$response['status'].'.';
      }

      return strlen($response['body']) > 120000 ? substr($response['body'], -120000) : $response['body'];
    }

    private function availableJobsTree($depth) {
      $fields = '_class,name,fullName,displayName,url,color,description,buildable,inQueue,nextBuildNumber,queueItem[id,why],healthReport[description,score],property[parameterDefinitions[name,defaultParameterValue[value]]],lastBuild[number,id,result,timestamp,duration,estimatedDuration,building,builtOn,url,queueId,displayName,actions[parameters[name,value]]],lastCompletedBuild[number,id,result,timestamp,duration,estimatedDuration,building,builtOn,url,queueId,displayName],lastFailedBuild[number,id,result,timestamp,duration,estimatedDuration,building,builtOn,url,queueId,displayName],lastStableBuild[number,id,result,timestamp,duration,estimatedDuration,building,builtOn,url,queueId,displayName]';

      if ($depth <= 0) {
        return 'jobs['.$fields.']';
      }

      return 'jobs['.$fields.','.$this->availableJobsTree($depth - 1).']';
    }

    private function isRunnableAvailableJob($job) {
      return isset($job->buildable) || isset($job->color) || isset($job->lastBuild) || isset($job->lastCompletedBuild) || isset($job->lastFailedBuild) || isset($job->lastStableBuild) || isset($job->nextBuildNumber);
    }

    private function collectAvailableJobs($jobs, &$availableJobs, $environmentFilter = '') {
      foreach (is_array($jobs) ? $jobs : array() as $job) {
        $jobName = isset($job->fullName) && $job->fullName !== '' ? $job->fullName : (isset($job->name) ? $job->name : '');

        if ($jobName !== '' && strpos((string) $jobName, '__jobseeker_') !== 0 && $this->isRunnableAvailableJob($job)) {
          $environment = $this->jenkinsEnvironmentFromJobData($job, $jobName);

          if ($environmentFilter === '' || $environmentFilter === 'ALL' || $environment === $environmentFilter) {
            $job->environment = $environment;
            $job->jobseekerEnvironment = $environment;
            $job->environmentSource = $environment === '' ? 'Not detected' : 'Jenkins metadata';

            if (isset($job->jobs)) {
              unset($job->jobs);
            }

            $availableJobs[] = $job;
          }
        }

        if (isset($job->jobs) && is_array($job->jobs)) {
          $this->collectAvailableJobs($job->jobs, $availableJobs, $environmentFilter);
        }
      }
    }

    private function wrapPreviewShellCommand($command, $timeoutSeconds) {
      $timeoutSeconds = max(5, (int) $timeoutSeconds);
      $wrappedCommand = escapeshellarg($command);

      return 'if command -v timeout >/dev/null 2>&1; then timeout '.$timeoutSeconds.'s sh -lc '.$wrappedCommand.'; else sh -lc '.$wrappedCommand.'; fi';
    }

    public function runInlinePythonPreview() {
      if (! $this->canManageJobs()) {
        $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
        return;
      }

      @set_time_limit(180);

      $environment = trim((string) $this->input->post('environment'));
      if ($environment === '' || $environment === '0' || strtoupper($environment) === 'ALL') {
        $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Select a concrete runtime environment before running the inline Python preview.'), 400);
        return;
      }

      $this->load->model('Context_model');
      if ((int) $this->Context_model->validateEnvironment($environment) === 0) {
        $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'The selected runtime environment is not configured in Context Settings.'), 400);
        return;
      }

      $entryPointRaw = $this->input->post('pythonEntryPoint');
      $entryPoint = trim((string) $entryPointRaw) === '' ? 'main.py' : $this->cleanPythonEntryPoint($entryPointRaw, TRUE);
      if ($entryPoint === FALSE) {
        $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Invalid inline Python entry file.'), 400);
        return;
      }

      $sourceCode = str_replace(array("\r\n", "\r"), "\n", (string) $this->input->post('pythonInlineCode'));
      if (trim($sourceCode) === '' || strlen($sourceCode) > 50000 || strpos($sourceCode, "\0") !== FALSE) {
        $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Enter valid inline Python code before running the preview.'), 400);
        return;
      }

      $requirementsText = $this->cleanPythonRequirementsText($this->input->post('pythonRequirementsText'));
      if ($requirementsText === FALSE) {
        $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Your Python requirements.txt content is too large or contains invalid characters.'), 400);
        return;
      }

      $inlineFiles = $this->cleanPythonInlineFilesJson($this->input->post('pythonInlineFilesJson'), $entryPoint);
      if ($inlineFiles === FALSE) {
        $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Your inline Python workspace contains invalid file paths or too much content.'), 400);
        return;
      }

      $pythonExecutable = $this->cleanPythonExecutable($this->input->post('pythonVersion'));
      if ($pythonExecutable === FALSE) {
        $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Please select a valid Python version.'), 400);
        return;
      }

      try {
        $token = substr(bin2hex(random_bytes(5)), 0, 10);
      } catch (Exception $exception) {
        $token = substr(str_replace('.', '', uniqid('', TRUE)), -10);
      }

      $previewJobName = '__jobseeker_py_preview_' . date('YmdHis') . '_' . $token;
      $jenkinsHome = $this->global['jenkins_home'];
      $repositoryRoot = ($jenkinsHome === '' || $jenkinsHome === NULL) ? FCPATH.'repository' : rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository';
      $pythonExecution = NULL;
      $jobSaved = FALSE;
      $buildNumber = '';
      $previewTimeoutSeconds = 45;

      try {
        if (! $this->ensurePythonSharedLibrary($repositoryRoot)) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Unable to prepare the JobSeeker Python SDK for Jenkins.'), 500);
          return;
        }

        $pythonExecution = $this->resolveInlinePythonExecution($repositoryRoot, $previewJobName, $entryPoint, $sourceCode, $requirementsText, NULL, $inlineFiles);
        if ($pythonExecution === FALSE) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'JobSeeker could not prepare the inline Python preview workspace.'), 400);
          return;
        }

        $slotCheck = $this->checkJenkinsEnvironmentSlots($previewJobName, $environment);
        if (! $slotCheck['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => $slotCheck['message']), isset($slotCheck['status']) ? (int) $slotCheck['status'] : 429);
          return;
        }

        $command = "export JOBSEEKER_PREVIEW=1\nexport JOBSEEKER_PREVIEW_MAX_ROWS=5\n" . $this->buildPythonExecutionCommand($pythonExecution, $repositoryRoot, $this->pythonEnvironmentArgument($environment, 1), array(
          'mode' => 'local',
          'pythonExecutable' => $pythonExecutable,
          'requirementsText' => $requirementsText,
          'dockerfileText' => ''
        ));
        $command = $this->wrapPreviewShellCommand($command, $previewTimeoutSeconds);

        $xml = $this->createShellCommandJobXml('Temporary JobSeeker inline Python preview. This job is deleted automatically after the run.', $command, $environment);
        $saveResult = $this->saveGeneratedJenkinsJob($previewJobName, $xml);
        if (! $saveResult['ok']) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Unable to create the temporary Jenkins preview job. HTTP '.$saveResult['status'].'.'), 502);
          return;
        }

        $jobSaved = TRUE;
        $triggerBody = http_build_query(array('ENVIRONMENT' => $environment));
        $triggerResponse = $this->requestJenkins('POST', $this->jenkinsJobPath($previewJobName) . '/buildWithParameters', $triggerBody, 'application/x-www-form-urlencoded');

        if (! $this->isSuccessfulJenkinsStatus($triggerResponse['status'])) {
          $this->jsonJobCreationResponse(array('ok' => FALSE, 'message' => 'Unable to start the temporary Jenkins preview job. HTTP '.$triggerResponse['status'].'.'), 502);
          return;
        }

        $queueId = $this->jenkinsQueueIdFromHeaders(isset($triggerResponse['headers']) ? $triggerResponse['headers'] : array());
          $wait = $this->waitForJenkinsBuildResult($previewJobName, $queueId, $previewTimeoutSeconds + 20);
        $buildNumber = isset($wait['buildNumber']) ? $wait['buildNumber'] : '';
        $output = $this->jenkinsBuildConsoleText($previewJobName, $buildNumber);

        if ($wait['status'] === 'TIMEOUT' && $buildNumber !== '') {
          $this->requestJenkins('POST', $this->jenkinsJobPath($previewJobName) . '/' . rawurlencode($buildNumber) . '/stop');
        }

        $message = $wait['ok'] ? 'Inline Python preview succeeded with Jenkins Python.' : 'Inline Python preview finished with status '.$wait['status'].'.';
        if ($wait['status'] === 'TIMEOUT') {
          $message = 'Inline Python preview timed out after '.$previewTimeoutSeconds.' seconds.';
        }

        $this->jsonJobCreationResponse(array(
          'ok' => $wait['ok'],
          'status' => $wait['status'],
          'message' => $message,
          'output' => $output,
          'environment' => $environment,
          'runtime' => 'Jenkins Agent '.$pythonExecutable,
          'queueWhy' => isset($wait['queueWhy']) ? $wait['queueWhy'] : ''
        ));
      } finally {
        if ($jobSaved) {
          $this->requestJenkins('POST', $this->jenkinsJobPath($previewJobName) . '/doDelete');
        }

        if (is_array($pythonExecution) && isset($pythonExecution['sourceDirectory']) && is_dir($pythonExecution['sourceDirectory'])) {
          $this->removeUploadDirectory($pythonExecution['sourceDirectory']);
        }
      }
    }

    private function appendDownstreamJobToExistingJob($sourceJobName, $targetJobName, $optionsRadios) {
      $sourceJobPath = $this->jenkinsJobPath($sourceJobName);
      $jobResponse = $this->requestJenkins('GET', $sourceJobPath . '/config.xml');

      if ((int) $jobResponse['status'] !== 200) {
        return array('ok' => FALSE, 'updated' => FALSE, 'status' => $jobResponse['status']);
      }

      $dom = new DOMDocument();
      $previousErrors = libxml_use_internal_errors(TRUE);
      $loaded = $dom->loadXML($jobResponse['body']);
      libxml_clear_errors();
      libxml_use_internal_errors($previousErrors);

      if (! $loaded || ! $dom->documentElement) {
        return array('ok' => FALSE, 'updated' => FALSE, 'status' => 422);
      }

      $root = $dom->documentElement;
      $publishers = $this->firstDirectChildElement($root, 'publishers');

      if ($publishers === NULL) {
        $publishers = $dom->createElement('publishers');
        $buildWrappers = $this->firstDirectChildElement($root, 'buildWrappers');

        if ($buildWrappers !== NULL) {
          $root->insertBefore($publishers, $buildWrappers);
        } else {
          $root->appendChild($publishers);
        }
      }

      $buildTrigger = $this->firstDirectChildElement($publishers, 'hudson.tasks.BuildTrigger');
      $isNewTrigger = FALSE;

      if ($buildTrigger === NULL) {
        $buildTrigger = $dom->createElement('hudson.tasks.BuildTrigger');
        $publishers->appendChild($buildTrigger);
        $isNewTrigger = TRUE;
      }

      $childProjects = $this->firstDirectChildElement($buildTrigger, 'childProjects');
      if ($childProjects === NULL) {
        $childProjects = $dom->createElement('childProjects');
        $buildTrigger->insertBefore($childProjects, $buildTrigger->firstChild);
      }

      $projects = array();
      foreach (explode(',', $childProjects->textContent) as $projectName) {
        $projectName = trim($projectName);
        if ($projectName !== '' && ! in_array($projectName, $projects, TRUE)) {
          $projects[] = $projectName;
        }
      }

      if (in_array($targetJobName, $projects, TRUE)) {
        return array('ok' => TRUE, 'updated' => FALSE, 'status' => 200);
      }

      $projects[] = $targetJobName;
      while ($childProjects->firstChild) {
        $childProjects->removeChild($childProjects->firstChild);
      }
      $childProjects->appendChild($dom->createTextNode(implode(', ', $projects)));

      if ($isNewTrigger || $this->firstDirectChildElement($buildTrigger, 'threshold') === NULL) {
        $buildTrigger->appendChild($this->createBuildTriggerThreshold($dom, $optionsRadios));
      }

      $saveResponse = $this->requestJenkins('POST', $sourceJobPath . '/config.xml', $dom->saveXML(), 'text/xml');

      return array(
        'ok' => $this->isSuccessfulJenkinsStatus($saveResponse['status']),
        'updated' => TRUE,
        'status' => $saveResponse['status']
      );
    }

    private function jobCreationDatesPath() {
      return APPPATH . 'cache/job_creation_dates.json';
    }

    private function readJobCreationDates() {
      $path = $this->jobCreationDatesPath();

      if (! is_readable($path)) {
        return array();
      }

      $json = file_get_contents($path);
      $dates = json_decode($json, TRUE);

      if (! is_array($dates)) {
        return array();
      }

      $cleanDates = array();
      foreach ($dates as $jobName => $createdAt) {
        if (is_string($jobName) && is_string($createdAt) && $jobName !== '' && $createdAt !== '') {
          $cleanDates[$jobName] = $createdAt;
        }
      }

      return $cleanDates;
    }

    private function recordJobCreationDate($jobName, $createdAt) {
      $dates = $this->readJobCreationDates();
      $dates[$jobName] = $createdAt;

      return file_put_contents($this->jobCreationDatesPath(), json_encode($dates, JSON_PRETTY_PRINT), LOCK_EX) !== FALSE;
    }

    private function generateJobName() {
      $names = array('milo', 'luna', 'piper', 'nova', 'ruby', 'jasper', 'olive', 'cosmo');
      $traits = array('sunny', 'maple', 'pixel', 'river', 'coco', 'sage', 'mango', 'ember');

      try {
        $token = dechex(random_int(4096, 65535));
      } catch (Exception $exception) {
        $token = substr(uniqid('', TRUE), -4);
      }

      return $names[array_rand($names)].'-'.$traits[array_rand($traits)].'-'.$token;
    }

    private function cleanSubmittedJobName($jobName) {
      $jobName = trim((string) $jobName);

      if ($jobName === '') {
        return array('ok' => FALSE, 'message' => 'Job name cannot be empty.');
      }

      if (strlen($jobName) > 50) {
        return array('ok' => FALSE, 'message' => 'Job name "'.$jobName.'" is longer than 50 characters.');
      }

      if (preg_match('/\s/', $jobName) || preg_match('/[^A-Za-z0-9._\/-]/', $jobName) || strpos($jobName, '..') !== FALSE || trim($jobName, '/') !== $jobName || strpos($jobName, '//') !== FALSE) {
        return array('ok' => FALSE, 'message' => 'Job name "'.$jobName.'" is invalid. Use letters, numbers, dot, dash, underscore, and folder separators only.');
      }

      return array('ok' => TRUE, 'name' => $jobName);
    }

    private function submittedJobNames($primaryJobName, $additionalJobNames) {
      $candidates = array();

      if (trim((string) $primaryJobName) !== '') {
        $candidates[] = $primaryJobName;
      }

      foreach (preg_split('/[\r\n,;]+/', (string) $additionalJobNames) as $jobName) {
        if (trim($jobName) !== '') {
          $candidates[] = $jobName;
        }
      }

      if (empty($candidates)) {
        $candidates[] = $this->generateJobName();
      }

      $jobNames = array();
      $seen = array();

      foreach ($candidates as $candidate) {
        $clean = $this->cleanSubmittedJobName($candidate);
        if (! $clean['ok']) {
          return array('ok' => FALSE, 'message' => $clean['message'], 'names' => array());
        }

        if (! isset($seen[$clean['name']])) {
          $seen[$clean['name']] = TRUE;
          $jobNames[] = $clean['name'];
        }
      }

      return array('ok' => TRUE, 'message' => '', 'names' => $jobNames);
    }

    public function do_upload($val,$job_name) {

      header('Content-Type: text/html; charset=utf-8');

      if (! $this->canManageJobs()) {
        $this->output->set_status_header(403);
        echo 'Access denied.';
        return;
      }

      $this->global['pageTitle'] = 'Job Seeker : Upload';
      $jenkins_home = $this->global['jenkins_home'];

      $safeScriptType = $this->safePathSegment(rawurldecode($val));
      $safeJobName = $this->safePathSegment(rawurldecode($job_name));

      if ($safeScriptType === FALSE || $safeJobName === FALSE) {
        $this->output->set_status_header(400);
        echo 'Invalid upload destination.';
        return;
      }

     $ds = DIRECTORY_SEPARATOR;

      // Check if jenkins home variable exist
     if($jenkins_home === '' || $jenkins_home === null){
      $storeFolder = '../../repository/'.$safeScriptType.'/jobs/';
      $targetPath = dirname( __FILE__ ) . $ds. $storeFolder . $ds; 

     } else {

      $storeFolder = rtrim($jenkins_home, '/\\').'/repository/'.$safeScriptType.'/jobs/';
      $targetPath = $storeFolder;
      
     }

      $allowedExtensions = $safeScriptType === 'python' ? array('zip', 'py') : ($safeScriptType === 'bash' ? array('zip', 'sh') : array('zip'));
      $upload = $this->getUploadedFile('file', $allowedExtensions, 104857600);
      if (! $upload['ok']) {
        $this->output->set_status_header(400);
        echo $upload['message'];
        return;
      }

      $targetJobPath = rtrim($targetPath, '/\\') . $ds . $safeJobName;

      if ($safeScriptType === 'python' && $upload['extension'] === 'py') {
        if (! $this->ensureDirectory($targetJobPath)) {
          $this->output->set_status_header(500);
          echo 'Unable to create upload directory.';
          return;
        }

        $targetFile = $targetJobPath . $ds . $upload['safe_name'];
        if (! move_uploaded_file($upload['tmp_name'], $targetFile)) {
          $this->output->set_status_header(500);
          echo 'Unable to store uploaded file.';
          return;
        }

        echo 'Python file uploaded.';
        return;
      }

      if ($safeScriptType === 'bash' && $upload['extension'] === 'sh') {
        if (! $this->ensureDirectory($targetJobPath)) {
          $this->output->set_status_header(500);
          echo 'Unable to create upload directory.';
          return;
        }

        $targetFile = $targetJobPath . $ds . $upload['safe_name'];
        if (! move_uploaded_file($upload['tmp_name'], $targetFile)) {
          $this->output->set_status_header(500);
          echo 'Unable to store uploaded file.';
          return;
        }

        echo 'Bash script uploaded.';
        return;
      }

      if (! $this->ensureDirectory($targetPath)) {
        $this->output->set_status_header(500);
        echo 'Unable to create upload directory.';
        return;
      }

      $targetFile = $targetPath . uniqid('job_', TRUE) . '.zip';
      if (! move_uploaded_file($upload['tmp_name'], $targetFile)) {
        $this->output->set_status_header(500);
        echo 'Unable to store uploaded file.';
        return;
      }

      $destinationExisted = is_dir($targetJobPath);
      $extractResult = $this->extractZipSafely($targetFile, $targetJobPath);
      @unlink($targetFile);

      if (! $extractResult['ok']) {
        if (! $destinationExisted && is_dir($targetJobPath)) {
          $this->removeUploadDirectory($targetJobPath);
        }
        $this->output->set_status_header(400);
        echo $extractResult['message'];
        return;
      }

      echo 'File uploaded and extracted.';

      }

      private function cronFieldString($values, $min, $max) {
        if (! is_array($values)) {
          $values = array($values);
        }

        $cleanValues = array();
        foreach ($values as $value) {
          $value = trim((string) $value);

          if ($value === '') {
            continue;
          }

          if ($value === '*') {
            $cleanValues[] = '*';
            continue;
          }

          if (! ctype_digit($value)) {
            return FALSE;
          }

          $number = (int) $value;
          if ($number < $min || $number > $max) {
            return FALSE;
          }

          $cleanValues[] = (string) $number;
        }

        $cleanValues = array_values(array_unique($cleanValues));
        if (empty($cleanValues)) {
          return FALSE;
        }

        if (in_array('*', $cleanValues, TRUE) && count($cleanValues) > 1) {
          $cleanValues = array_values(array_diff($cleanValues, array('*')));
        }

        return implode(',', $cleanValues);
      }

      private function cronMinuteStepString($value) {
        $value = trim((string) $value);

        if ($value === '*') {
          return '*';
        }

        if (! ctype_digit($value)) {
          return FALSE;
        }

        $number = (int) $value;
        if ($number < 1 || $number > 59) {
          return FALSE;
        }

        return 'H/'.$number;
      }

      private function cleanCustomCronExpression($value) {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        if ($value === '' || strlen($value) > 120) {
          return FALSE;
        }

        $allowedTags = array('@hourly', '@daily', '@weekly', '@monthly', '@annually', '@yearly', '@midnight');
        if (in_array($value, $allowedTags, TRUE)) {
          return $value;
        }

        $parts = preg_split('/\s+/', $value);
        if (count($parts) !== 5) {
          return FALSE;
        }

        foreach ($parts as $part) {
          if ($part === '' || ! preg_match('/^[A-Za-z0-9*?,\/#LWH()\-]+$/', $part)) {
            return FALSE;
          }
        }

        return implode(' ', $parts);
      }

      private function removeUploadDirectory($path) {
        foreach (scandir($path) as $item) {
          if ($item === '.' || $item === '..') {
            continue;
          }

          $itemPath = $path . DIRECTORY_SEPARATOR . $item;
          if (is_dir($itemPath) && ! is_link($itemPath)) {
            $this->removeUploadDirectory($itemPath);
          } else {
            @unlink($itemPath);
          }
        }

        @rmdir($path);
      }

      private function copyDirectory($sourcePath, $targetPath) {
        if (! is_dir($sourcePath) || ! $this->ensureDirectory($targetPath)) {
          return FALSE;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
          $name = $item->getFilename();
          if ($name === '__pycache__' || substr($name, -4) === '.pyc') {
            continue;
          }

          $relativePath = $iterator->getSubPathName();
          $targetItem = rtrim($targetPath, '/\\').DIRECTORY_SEPARATOR.$relativePath;

          if ($item->isDir()) {
            if (! $this->ensureDirectory($targetItem)) {
              return FALSE;
            }
          } else if (! copy($item->getPathname(), $targetItem)) {
            return FALSE;
          }
        }

        return TRUE;
      }

      private function ensurePythonSharedLibrary($repositoryRoot) {
        $sourceDirectory = APPPATH.'third_party/python/jobseeker_sdk';
        $targetRoot = rtrim($repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'python'.DIRECTORY_SEPARATOR.'lib';
        $targetDirectory = $targetRoot.DIRECTORY_SEPARATOR.'jobseeker-sdk';
        $legacyTargetFile = $targetRoot.DIRECTORY_SEPARATOR.'jobseeker.py';

        if (! is_dir($sourceDirectory) || ! $this->ensureDirectory($targetRoot)) {
          return FALSE;
        }

        if (is_file($legacyTargetFile)) {
          @unlink($legacyTargetFile);
        }

        if (is_dir($targetDirectory)) {
          $this->removeUploadDirectory($targetDirectory);
        }

        return $this->copyDirectory($sourceDirectory, $targetDirectory);
      }

      private function selectedPythonSourceMode($sourceMode) {
        return in_array($sourceMode, array('upload', 'path', 'git', 'inline'), TRUE) ? $sourceMode : 'upload';
      }

      private function cleanPythonEntryPoint($entryPoint, $required = FALSE) {
        $entryPoint = trim((string) $entryPoint);

        if ($entryPoint === '') {
          return $required ? FALSE : '';
        }

        $safeEntryPoint = $this->safeRelativePath($entryPoint);
        if ($safeEntryPoint === FALSE || strtolower(pathinfo($safeEntryPoint, PATHINFO_EXTENSION)) !== 'py') {
          return FALSE;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $safeEntryPoint);
      }

      private function cleanPythonRequirementsText($requirementsText) {
        $requirementsText = str_replace(array("\r\n", "\r"), "\n", (string) $requirementsText);

        if (strlen($requirementsText) > 20000 || strpos($requirementsText, "\0") !== FALSE) {
          return FALSE;
        }

        return $requirementsText;
      }

      private function cleanPythonDockerfileText($dockerfileText) {
        $dockerfileText = str_replace(array("\r\n", "\r"), "\n", (string) $dockerfileText);

        if (strlen($dockerfileText) > 50000 || strpos($dockerfileText, "\0") !== FALSE) {
          return FALSE;
        }

        return $dockerfileText;
      }

      private function normalizeInlinePythonWorkspacePath($path, $requirePythonFile = FALSE) {
        $safePath = $this->safeRelativePath($path);
        if ($safePath === FALSE) {
          return FALSE;
        }

        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $safePath);
        $lowerPath = strtolower($relativePath);

        if ($lowerPath === 'requirements.txt' || $lowerPath === 'dockerfile') {
          return FALSE;
        }

        if (strpos($lowerPath, '__pycache__/') !== FALSE || strpos($lowerPath, '.jobseeker-python-libs/') !== FALSE) {
          return FALSE;
        }

        if ($requirePythonFile && strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'py') {
          return FALSE;
        }

        return $relativePath;
      }

      private function cleanPythonInlineFilesJson($inlineFilesJson, $entryPoint) {
        $inlineFilesJson = (string) $inlineFilesJson;
        if (trim($inlineFilesJson) === '') {
          return array('files' => array(), 'directories' => array());
        }

        if (strlen($inlineFilesJson) > 250000 || strpos($inlineFilesJson, "\0") !== FALSE) {
          return FALSE;
        }

        $payload = json_decode($inlineFilesJson, TRUE);
        if (! is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
          return FALSE;
        }

        $entryPoint = trim((string) $entryPoint) === '' ? 'main.py' : $entryPoint;
        $entryPoint = $this->normalizeInlinePythonWorkspacePath($entryPoint, TRUE);
        if ($entryPoint === FALSE) {
          return FALSE;
        }

        $files = array();
        $directories = array();
        $totalBytes = 0;
        $entries = 0;

        if (isset($payload['directories']) && is_array($payload['directories'])) {
          foreach ($payload['directories'] as $directory) {
            $path = is_array($directory) && isset($directory['path']) ? $directory['path'] : $directory;
            $path = $this->normalizeInlinePythonWorkspacePath($path, FALSE);
            if ($path === FALSE || strtolower($path) === strtolower($entryPoint)) {
              return FALSE;
            }

            $directories[$path] = $path;
            $entries++;
          }
        }

        if (isset($payload['files']) && is_array($payload['files'])) {
          foreach ($payload['files'] as $file) {
            if (! is_array($file) || ! array_key_exists('path', $file) || ! array_key_exists('content', $file)) {
              return FALSE;
            }

            $path = $this->normalizeInlinePythonWorkspacePath($file['path'], TRUE);
            if ($path === FALSE || strtolower($path) === strtolower($entryPoint)) {
              return FALSE;
            }

            $content = str_replace(array("\r\n", "\r"), "\n", (string) $file['content']);
            $contentLength = strlen($content);
            if ($contentLength > 50000 || strpos($content, "\0") !== FALSE) {
              return FALSE;
            }

            $totalBytes += $contentLength;
            if ($totalBytes > 200000) {
              return FALSE;
            }

            $files[$path] = $content;
            $entries++;
          }
        }

        if ($entries > 80) {
          return FALSE;
        }

        ksort($files);
        ksort($directories);

        return array('files' => $files, 'directories' => array_values($directories));
      }

      private function selectedPythonRuntimeMode($runtimeMode) {
        return $runtimeMode === 'docker' ? 'docker' : 'local';
      }

      private function selectedLinuxRuntimeMode($runtimeMode) {
        return $runtimeMode === 'docker' ? 'docker' : 'local';
      }

      private function cleanPythonExecutable($pythonVersion) {
        $pythonVersion = trim((string) $pythonVersion);

        if ($pythonVersion === '') {
          return 'python3';
        }

        if (preg_match('/^python3(?:\.[0-9]{1,2})?$/', $pythonVersion)) {
          return 'python3';
        }

        if (preg_match('/^3(?:\.[0-9]{1,2})?$/', $pythonVersion)) {
          return 'python3';
        }

        return FALSE;
      }

      private function cleanDockerImage($dockerImage, $defaultImage) {
        $dockerImage = trim((string) $dockerImage);

        if ($dockerImage === '') {
          return $defaultImage;
        }

        if (strlen($dockerImage) > 200 || preg_match('/[\s\x00-\x1F\x7F]/', $dockerImage) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/:@-]*$/', $dockerImage)) {
          return FALSE;
        }

        return $dockerImage;
      }

      private function cleanPythonDockerImage($dockerImage, $pythonExecutable) {
        $version = preg_replace('/^python/', '', $pythonExecutable);
        return $this->cleanDockerImage($dockerImage, 'python:'.($version === '' ? '3' : $version).'-slim');
      }

      private function cleanLinuxDockerImage($dockerImage, $scriptType = '') {
        return $this->cleanDockerImage($dockerImage, $scriptType === 'talend' ? 'eclipse-temurin:17-jre-alpine' : 'alpine:3.20');
      }

      private function repositoryRealPath($repositoryRoot) {
        $repositoryRealPath = realpath($repositoryRoot);
        return $repositoryRealPath === FALSE ? FALSE : rtrim($repositoryRealPath, DIRECTORY_SEPARATOR);
      }

      private function resolveRepositoryPath($path, $repositoryRoot) {
        $path = trim((string) $path);
        if ($path === '') {
          return FALSE;
        }

        $repositoryRealPath = $this->repositoryRealPath($repositoryRoot);
        if ($repositoryRealPath === FALSE) {
          return FALSE;
        }

        if (strpos($path, '/php/repository/') === 0 || $path === '/php/repository') {
          $candidatePath = $path;
        } else {
          $path = trim(str_replace('\\', '/', $path), '/');
          if (strpos($path, 'repository/') === 0) {
            $path = substr($path, strlen('repository/'));
          }

          $safePath = $this->safeRelativePath($path);
          if ($safePath === FALSE) {
            return FALSE;
          }

          $candidatePath = $repositoryRealPath.DIRECTORY_SEPARATOR.$safePath;
        }

        $resolvedPath = realpath($candidatePath);
        if ($resolvedPath === FALSE || ! $this->pathWithinBase($resolvedPath, $repositoryRealPath)) {
          return FALSE;
        }

        return $resolvedPath;
      }

      private function resolvePythonFile($sourceDirectory, $entryPoint) {
        $scriptPath = realpath(rtrim($sourceDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entryPoint));
        if ($scriptPath === FALSE || ! is_file($scriptPath) || strtolower(pathinfo($scriptPath, PATHINFO_EXTENSION)) !== 'py' || ! $this->pathWithinBase($scriptPath, $sourceDirectory)) {
          return FALSE;
        }

        return $scriptPath;
      }

      private function shouldSkipUploadedPythonPath($path) {
        $name = basename($path);

        return $name === '__MACOSX' || $name === '__pycache__' || $name === '.git' || $name === '.jobseeker-python-libs' || strpos($name, '.') === 0;
      }

      private function collectUploadedPythonFiles($directory, $baseDirectory, &$files) {
        if (count($files) >= 500) {
          return;
        }

        $items = @scandir($directory);
        if (! is_array($items)) {
          return;
        }

        foreach ($items as $item) {
          if ($item === '.' || $item === '..') {
            continue;
          }

          $path = $directory.DIRECTORY_SEPARATOR.$item;
          if ($this->shouldSkipUploadedPythonPath($path)) {
            continue;
          }

          if (is_dir($path) && ! is_link($path)) {
            $this->collectUploadedPythonFiles($path, $baseDirectory, $files);
          } else if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'py') {
            $realPath = realpath($path);
            if ($realPath !== FALSE && $this->pathWithinBase($realPath, $baseDirectory)) {
              $files[] = $realPath;
            }
          }
        }
      }

      private function resolveUploadedPythonFile($jobDirectory, $entryPoint) {
        if ($entryPoint !== '') {
          $scriptPath = $this->resolvePythonFile($jobDirectory, $entryPoint);
          if ($scriptPath !== FALSE) {
            return $scriptPath;
          }

          if (strpos($entryPoint, '/') !== FALSE) {
            return FALSE;
          }
        }

        $files = array();
        $this->collectUploadedPythonFiles($jobDirectory, $jobDirectory, $files);

        if ($entryPoint !== '') {
          $matches = array_values(array_filter($files, function($file) use ($entryPoint) {
            return basename($file) === $entryPoint;
          }));

          return count($matches) === 1 ? $matches[0] : FALSE;
        }

        $rootFiles = glob($jobDirectory.DIRECTORY_SEPARATOR.'*.py');
        if (is_array($rootFiles) && count($rootFiles) === 1) {
          return realpath($rootFiles[0]);
        }

        $mainFiles = array_values(array_filter($files, function($file) {
          return basename($file) === 'main.py';
        }));

        if (count($mainFiles) === 1) {
          return $mainFiles[0];
        }

        return count($files) === 1 ? $files[0] : FALSE;
      }

      private function resolveUploadedPythonExecution($repositoryRoot, $jobName, $entryPoint) {
        $jobDirectory = $this->resolveRepositoryPath('python/jobs/'.$jobName, $repositoryRoot);
        if ($jobDirectory === FALSE || ! is_dir($jobDirectory)) {
          return FALSE;
        }

        $scriptPath = $this->resolveUploadedPythonFile($jobDirectory, $entryPoint);

        if ($scriptPath === FALSE || ! is_file($scriptPath)) {
          return FALSE;
        }

        return array(
          'mode' => 'local',
          'sourceDirectory' => $jobDirectory,
          'scriptPath' => $scriptPath
        );
      }

      private function collectInlinePythonWorkspaceFiles($directory, $baseDirectory, $entryPoint, &$files, &$directories, $includeContent = FALSE) {
        $items = @scandir($directory);
        if (! is_array($items)) {
          return;
        }

        foreach ($items as $item) {
          if ($item === '.' || $item === '..') {
            continue;
          }

          $path = $directory.DIRECTORY_SEPARATOR.$item;
          if ($this->shouldSkipUploadedPythonPath($path)) {
            continue;
          }

          $realPath = realpath($path);
          if ($realPath === FALSE || ! $this->pathWithinBase($realPath, $baseDirectory)) {
            continue;
          }

          $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($realPath, strlen(rtrim($baseDirectory, DIRECTORY_SEPARATOR)) + 1));
          if (is_dir($realPath) && ! is_link($realPath)) {
            $this->collectInlinePythonWorkspaceFiles($realPath, $baseDirectory, $entryPoint, $files, $directories, $includeContent);
            $directories[] = $relativePath;
          } else if (is_file($realPath) && strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) === 'py' && strtolower($relativePath) !== strtolower($entryPoint)) {
            if ($includeContent) {
              if (filesize($realPath) > 50000) {
                continue;
              }

              $files[] = array('path' => $relativePath, 'content' => file_get_contents($realPath));
            } else {
              $files[] = $realPath;
            }
          }
        }
      }

      private function syncInlinePythonFiles($jobDirectory, $entryPoint, $inlineFiles) {
        if (! is_array($inlineFiles) || ! isset($inlineFiles['files']) || ! isset($inlineFiles['directories'])) {
          return FALSE;
        }

        if (! $this->ensureDirectory($jobDirectory)) {
          return FALSE;
        }

        $existingFiles = array();
        $existingDirectories = array();
        $this->collectInlinePythonWorkspaceFiles($jobDirectory, $jobDirectory, $entryPoint, $existingFiles, $existingDirectories, FALSE);

        foreach ($existingFiles as $existingFile) {
          @unlink($existingFile);
        }

        usort($existingDirectories, function($left, $right) {
          return strlen($right) - strlen($left);
        });

        foreach ($existingDirectories as $existingDirectory) {
          @rmdir($jobDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $existingDirectory));
        }

        foreach ($inlineFiles['directories'] as $directory) {
          $targetDirectory = $jobDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);
          if (! $this->pathWithinBase($targetDirectory, $jobDirectory) || ! $this->ensureDirectory($targetDirectory)) {
            return FALSE;
          }
        }

        foreach ($inlineFiles['files'] as $path => $content) {
          $targetFile = $jobDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
          if (! $this->pathWithinBase($targetFile, $jobDirectory) || ! $this->ensureDirectory(dirname($targetFile)) || file_put_contents($targetFile, $content, LOCK_EX) === FALSE) {
            return FALSE;
          }
        }

        return TRUE;
      }

      private function resolveInlinePythonExecution($repositoryRoot, $jobName, $entryPoint, $sourceCode, $requirementsText = NULL, $dockerfileText = NULL, $inlineFiles = NULL) {
        $entryPoint = trim((string) $entryPoint) === '' ? 'main.py' : $this->cleanPythonEntryPoint($entryPoint, TRUE);
        if ($entryPoint === FALSE) {
          return FALSE;
        }

        $relativeJobDirectory = $this->safeRelativePath('python/inline/'.$jobName);
        if ($relativeJobDirectory === FALSE) {
          return FALSE;
        }

        $jobDirectory = rtrim($repositoryRoot, '/\\').DIRECTORY_SEPARATOR.$relativeJobDirectory;
        $scriptPath = $jobDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entryPoint);

        if (! $this->pathWithinBase($scriptPath, $jobDirectory)) {
          return FALSE;
        }

        $sourceCode = str_replace(array("\r\n", "\r"), "\n", (string) $sourceCode);
        if ($requirementsText !== NULL) {
          $requirementsText = $this->cleanPythonRequirementsText($requirementsText);
          if ($requirementsText === FALSE) {
            return FALSE;
          }
        }

        if (trim($sourceCode) !== '') {
          if (strlen($sourceCode) > 50000 || ! $this->ensureDirectory(dirname($scriptPath)) || file_put_contents($scriptPath, $sourceCode, LOCK_EX) === FALSE) {
            return FALSE;
          }
        } else if (! is_file($scriptPath)) {
          return FALSE;
        }

        $requirementsPath = $jobDirectory.DIRECTORY_SEPARATOR.'requirements.txt';
        if ($requirementsText !== NULL) {
          if (trim($requirementsText) !== '') {
            if (! $this->ensureDirectory($jobDirectory) || file_put_contents($requirementsPath, $requirementsText, LOCK_EX) === FALSE) {
              return FALSE;
            }
          } else if (is_file($requirementsPath)) {
            @unlink($requirementsPath);
          }
        }

        $dockerfilePath = $jobDirectory.DIRECTORY_SEPARATOR.'Dockerfile';
        if ($dockerfileText !== NULL) {
          $dockerfileText = $this->cleanPythonDockerfileText($dockerfileText);
          if ($dockerfileText === FALSE) {
            return FALSE;
          }

          if (trim($dockerfileText) !== '') {
            if (! $this->ensureDirectory($jobDirectory) || file_put_contents($dockerfilePath, $dockerfileText, LOCK_EX) === FALSE) {
              return FALSE;
            }
          } else if (is_file($dockerfilePath)) {
            @unlink($dockerfilePath);
          }
        }

        if ($inlineFiles !== NULL && ! $this->syncInlinePythonFiles($jobDirectory, $entryPoint, $inlineFiles)) {
          return FALSE;
        }

        return array(
          'mode' => 'local',
          'sourceDirectory' => $jobDirectory,
          'scriptPath' => $scriptPath,
          'requirementsPath' => $requirementsPath,
          'dockerfilePath' => $dockerfilePath
        );
      }

      private function resolvePathPythonExecution($repositoryRoot, $sourcePath, $entryPoint) {
        $resolvedSourcePath = $this->resolveRepositoryPath($sourcePath, $repositoryRoot);
        if ($resolvedSourcePath === FALSE) {
          return FALSE;
        }

        if (is_file($resolvedSourcePath)) {
          $sourceDirectory = dirname($resolvedSourcePath);
          $scriptPath = $resolvedSourcePath;
        } else if (is_dir($resolvedSourcePath) && $entryPoint !== '') {
          $sourceDirectory = $resolvedSourcePath;
          $scriptPath = $this->resolvePythonFile($sourceDirectory, $entryPoint);
        } else {
          return FALSE;
        }

        if ($scriptPath === FALSE || strtolower(pathinfo($scriptPath, PATHINFO_EXTENSION)) !== 'py') {
          return FALSE;
        }

        return array(
          'mode' => 'local',
          'sourceDirectory' => $sourceDirectory,
          'scriptPath' => $scriptPath
        );
      }

      private function cleanPythonRepositoryUrl($repositoryUrl) {
        $repositoryUrl = trim((string) $repositoryUrl);
        if ($repositoryUrl === '' || strlen($repositoryUrl) > 1000 || preg_match('/[\x00-\x1F\x7F]/', $repositoryUrl)) {
          return FALSE;
        }

        if (filter_var($repositoryUrl, FILTER_VALIDATE_URL) !== FALSE) {
          return $repositoryUrl;
        }

        if (preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9._\-]+:[A-Za-z0-9._\-\/]+(?:\.git)?$/', $repositoryUrl)) {
          return $repositoryUrl;
        }

        return FALSE;
      }

      private function cleanPythonRepositoryBranch($branch) {
        $branch = trim((string) $branch);
        if ($branch === '') {
          return '';
        }

        return preg_match('/^[A-Za-z0-9._\/-]+$/', $branch) ? $branch : FALSE;
      }

      private function resolveGitPythonExecution($repositoryUrl, $branch, $entryPoint) {
        $repositoryUrl = $this->cleanPythonRepositoryUrl($repositoryUrl);
        $branch = $this->cleanPythonRepositoryBranch($branch);
        $entryPoint = $this->cleanPythonEntryPoint($entryPoint, TRUE);

        if ($repositoryUrl === FALSE || $branch === FALSE || $entryPoint === FALSE) {
          return FALSE;
        }

        return array(
          'mode' => 'git',
          'repositoryUrl' => $repositoryUrl,
          'branch' => $branch,
          'entryPoint' => $entryPoint
        );
      }

      public function inlinePythonSource() {
        if (! $this->canManageJobs()) {
          $this->output->set_status_header(403);
          echo 'Access denied.';
          return;
        }

        $cleanJobName = $this->cleanSubmittedJobName($this->input->get('job_name'));
        if (! $cleanJobName['ok']) {
          $this->output->set_status_header(400);
          echo $cleanJobName['message'];
          return;
        }

        $entryPoint = $this->input->get('entry_point');
        $cleanEntryPoint = trim((string) $entryPoint) === '' ? 'main.py' : $this->cleanPythonEntryPoint($entryPoint, TRUE);
        if ($cleanEntryPoint === FALSE) {
          $this->output->set_status_header(400);
          echo 'Invalid inline Python entry file.';
          return;
        }

        $jenkinsHome = $this->global['jenkins_home'];
        $repositoryRoot = ($jenkinsHome === '' || $jenkinsHome === NULL) ? FCPATH.'repository' : rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository';
        $pythonExecution = $this->resolveInlinePythonExecution($repositoryRoot, $cleanJobName['name'], $cleanEntryPoint, '');

        if ($pythonExecution === FALSE || ! is_readable($pythonExecution['scriptPath'])) {
          $this->output->set_status_header(404);
          echo 'Inline Python source was not found.';
          return;
        }

        $this->output->set_content_type('text/plain', 'utf-8');
        echo file_get_contents($pythonExecution['scriptPath']);
      }

      public function inlinePythonRequirements() {
        if (! $this->canManageJobs()) {
          $this->output->set_status_header(403);
          echo 'Access denied.';
          return;
        }

        $cleanJobName = $this->cleanSubmittedJobName($this->input->get('job_name'));
        if (! $cleanJobName['ok']) {
          $this->output->set_status_header(400);
          echo $cleanJobName['message'];
          return;
        }

        $entryPoint = $this->input->get('entry_point');
        $cleanEntryPoint = trim((string) $entryPoint) === '' ? 'main.py' : $this->cleanPythonEntryPoint($entryPoint, TRUE);
        if ($cleanEntryPoint === FALSE) {
          $this->output->set_status_header(400);
          echo 'Invalid inline Python entry file.';
          return;
        }

        $jenkinsHome = $this->global['jenkins_home'];
        $repositoryRoot = ($jenkinsHome === '' || $jenkinsHome === NULL) ? FCPATH.'repository' : rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository';
        $pythonExecution = $this->resolveInlinePythonExecution($repositoryRoot, $cleanJobName['name'], $cleanEntryPoint, '');

        if ($pythonExecution === FALSE) {
          $this->output->set_status_header(404);
          echo 'Inline Python source was not found.';
          return;
        }

        $this->output->set_content_type('text/plain', 'utf-8');
        echo is_readable($pythonExecution['requirementsPath']) ? file_get_contents($pythonExecution['requirementsPath']) : '';
      }

      public function inlinePythonDockerfile() {
        if (! $this->canManageJobs()) {
          $this->output->set_status_header(403);
          echo 'Access denied.';
          return;
        }

        $cleanJobName = $this->cleanSubmittedJobName($this->input->get('job_name'));
        if (! $cleanJobName['ok']) {
          $this->output->set_status_header(400);
          echo $cleanJobName['message'];
          return;
        }

        $entryPoint = $this->input->get('entry_point');
        $jenkinsHome = $this->global['jenkins_home'];
        $repositoryRoot = ($jenkinsHome === '' || $jenkinsHome === NULL) ? FCPATH.'repository' : rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository';
        $pythonExecution = $this->resolveInlinePythonExecution($repositoryRoot, $cleanJobName['name'], $entryPoint, '');

        if ($pythonExecution === FALSE) {
          $this->output->set_status_header(404);
          echo 'Inline Python source was not found.';
          return;
        }

        $this->output->set_content_type('text/plain', 'utf-8');
        echo is_readable($pythonExecution['dockerfilePath']) ? file_get_contents($pythonExecution['dockerfilePath']) : '';
      }

      public function inlinePythonFiles() {
        if (! $this->canManageJobs()) {
          $this->output->set_status_header(403);
          echo 'Access denied.';
          return;
        }

        $cleanJobName = $this->cleanSubmittedJobName($this->input->get('job_name'));
        if (! $cleanJobName['ok']) {
          $this->output->set_status_header(400);
          echo $cleanJobName['message'];
          return;
        }

        $entryPoint = $this->input->get('entry_point');
        $cleanEntryPoint = trim((string) $entryPoint) === '' ? 'main.py' : $this->cleanPythonEntryPoint($entryPoint, TRUE);
        if ($cleanEntryPoint === FALSE) {
          $this->output->set_status_header(400);
          echo 'Invalid inline Python entry file.';
          return;
        }

        $jenkinsHome = $this->global['jenkins_home'];
        $repositoryRoot = ($jenkinsHome === '' || $jenkinsHome === NULL) ? FCPATH.'repository' : rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository';
        $pythonExecution = $this->resolveInlinePythonExecution($repositoryRoot, $cleanJobName['name'], $cleanEntryPoint, '');

        if ($pythonExecution === FALSE) {
          $this->output->set_status_header(404);
          echo 'Inline Python source was not found.';
          return;
        }

        $files = array();
        $directories = array();
        $this->collectInlinePythonWorkspaceFiles($pythonExecution['sourceDirectory'], $pythonExecution['sourceDirectory'], $cleanEntryPoint, $files, $directories, TRUE);

        sort($directories);
        usort($files, function($left, $right) {
          return strcmp($left['path'], $right['path']);
        });

        $this->output->set_content_type('application/json', 'utf-8');
        echo json_encode(array('files' => $files, 'directories' => $directories));
      }

      public function availableJobs() {
        if (! $this->canManageJobs()) {
          $this->output->set_status_header(403);
          $this->output->set_content_type('application/json');
          echo json_encode(array('jobs' => array(), 'error' => 'Access denied.'));
          return;
        }

        $environmentFilter = $this->normalizeJobSeekerEnvironment($this->input->get('environment'));
        $response = $this->requestJenkins('GET', 'api/json?tree='.$this->availableJobsTree(3));
        $this->output->set_status_header((int) $response['status']);
        $this->output->set_content_type('application/json');

        if ((int) $response['status'] !== 200) {
          echo json_encode(array('jobs' => array(), 'error' => $response['body']));
          return;
        }

        $payload = json_decode($response['body']);
        if (! is_object($payload) || ! isset($payload->jobs) || ! is_array($payload->jobs)) {
          $this->output->set_status_header(502);
          echo json_encode(array('jobs' => array(), 'error' => 'Jenkins returned an invalid jobs payload.'));
          return;
        }

        $availableJobs = array();
        $this->collectAvailableJobs($payload->jobs, $availableJobs, $environmentFilter);
        $payload->jobs = $availableJobs;

        echo json_encode($payload);
      }

      private function pythonEnvironmentArgument($environment, $checkEnvironment) {
        return ($environment != '0' && $checkEnvironment == 1) ? escapeshellarg($environment) : '';
      }

      private function shellArgumentString($arguments) {
        $escapedArguments = array();

        foreach ($arguments as $argument) {
          $escapedArguments[] = escapeshellarg($argument);
        }

        return implode(' ', $escapedArguments);
      }

      private function buildLinuxCommandExecutionCommand($commandText, $runtimeOptions = array()) {
        $commandText = str_replace(array("\r\n", "\r"), "\n", (string) $commandText);
        $runtimeMode = isset($runtimeOptions['mode']) ? $runtimeOptions['mode'] : 'local';
        $dockerImage = isset($runtimeOptions['dockerImage']) ? $runtimeOptions['dockerImage'] : 'alpine:3.20';

        if ($runtimeMode !== 'docker') {
          return $commandText;
        }

        $lines = array('set -e');
        $lines[] = 'export JOBSEEKER_LINUX_RUNTIME=\'docker\'';
        $lines[] = 'export JOBSEEKER_DOCKER_IMAGE='.escapeshellarg($dockerImage);
        $lines[] = 'export JOBSEEKER_LINUX_COMMAND_B64='.escapeshellarg(base64_encode($commandText));
        $lines[] = 'command -v docker >/dev/null || { echo "Docker runtime selected, but docker is not available on this Jenkins agent."; exit 127; }';
        $lines[] = 'docker run --rm -i \\';
        $lines[] = '  --network host \\';
        $lines[] = '  -e "JOBSEEKER_LINUX_COMMAND_B64=$JOBSEEKER_LINUX_COMMAND_B64" \\';
        $lines[] = '  "$JOBSEEKER_DOCKER_IMAGE" \\';
        $lines[] = '  sh -lc \'printf "%s" "$JOBSEEKER_LINUX_COMMAND_B64" | base64 -d | sh\'';

        return implode("\n", $lines);
      }

      private function buildShellScriptExecutionCommand($execution, $runtimeOptions = array()) {
        $arguments = isset($execution['arguments']) ? $execution['arguments'] : array();
        $argumentString = $this->shellArgumentString($arguments);
        $runtimeMode = isset($runtimeOptions['mode']) ? $runtimeOptions['mode'] : 'local';
        $dockerImage = isset($runtimeOptions['dockerImage']) ? $runtimeOptions['dockerImage'] : ($execution['scriptType'] === 'talend' ? 'eclipse-temurin:17-jre-alpine' : 'alpine:3.20');

        if ($runtimeMode !== 'docker') {
          return 'sh '.escapeshellarg($execution['scriptPath']).($argumentString !== '' ? ' '.$argumentString : '');
        }

        $dockerScript = implode("\n", array(
          'set -e',
          'mkdir -p /tmp/jobseeker-context',
          'tar -C /tmp/jobseeker-context -xf -',
          'cd /tmp/jobseeker-context/source',
          'if [ -n "${JAVA_HOME:-}" ] && [ -d "$JAVA_HOME/bin" ]; then export PATH="$JAVA_HOME/bin:$PATH"; fi',
          'if [ -d /opt/java/openjdk/bin ]; then export PATH="/opt/java/openjdk/bin:$PATH"; fi',
          'sh "$JOBSEEKER_ENTRYPOINT" "$@"'
        ));

        $lines = array('set -e');
        $lines[] = 'export JOBSEEKER_LINUX_RUNTIME=\'docker\'';
        $lines[] = 'export JOBSEEKER_LINUX_SCRIPT_TYPE='.escapeshellarg($execution['scriptType']);
        $lines[] = 'export JOBSEEKER_SOURCE_DIR='.escapeshellarg($execution['sourceDirectory']);
        $lines[] = 'export JOBSEEKER_SCRIPT_PATH='.escapeshellarg($execution['scriptPath']);
        $lines[] = 'export JOBSEEKER_DOCKER_IMAGE='.escapeshellarg($dockerImage);
        $lines[] = 'command -v docker >/dev/null || { echo "Docker runtime selected, but docker is not available on this Jenkins agent."; exit 127; }';
        $lines[] = 'JOBSEEKER_DOCKER_ENTRYPOINT="${JOBSEEKER_SCRIPT_PATH#$JOBSEEKER_SOURCE_DIR/}"';
        $lines[] = 'if [ "$JOBSEEKER_DOCKER_ENTRYPOINT" = "$JOBSEEKER_SCRIPT_PATH" ]; then JOBSEEKER_DOCKER_ENTRYPOINT="$(basename "$JOBSEEKER_SCRIPT_PATH")"; fi';
        $lines[] = 'JOBSEEKER_DOCKER_CONTEXT="$WORKSPACE/jobseeker-linux-docker-context"';
        $lines[] = 'rm -rf "$JOBSEEKER_DOCKER_CONTEXT"';
        $lines[] = 'mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/source"';
        $lines[] = 'cp -R "$JOBSEEKER_SOURCE_DIR/." "$JOBSEEKER_DOCKER_CONTEXT/source/"';
        $lines[] = 'tar -C "$JOBSEEKER_DOCKER_CONTEXT" -cf - . | docker run --rm -i \\';
        $lines[] = '  --network host \\';
        $lines[] = '  -e "JOBSEEKER_ENTRYPOINT=$JOBSEEKER_DOCKER_ENTRYPOINT" \\';
        $lines[] = '  "$JOBSEEKER_DOCKER_IMAGE" \\';
        $lines[] = '  sh -lc '.escapeshellarg($dockerScript).' sh'.($argumentString !== '' ? ' '.$argumentString : '');

        return implode("\n", $lines);
      }

      private function buildPythonExecutionCommand($execution, $repositoryRoot, $environmentArgument, $runtimeOptions = array()) {
        $pythonLibraryPath = rtrim($repositoryRoot, '/\\').'/python/lib';
        $runtimeMode = isset($runtimeOptions['mode']) ? $runtimeOptions['mode'] : 'local';
        $pythonExecutable = isset($runtimeOptions['pythonExecutable']) ? $runtimeOptions['pythonExecutable'] : 'python3';
        $dockerImage = isset($runtimeOptions['dockerImage']) ? $runtimeOptions['dockerImage'] : 'python:3-slim';
        $requirementsText = isset($runtimeOptions['requirementsText']) ? (string) $runtimeOptions['requirementsText'] : '';
        $dockerfileText = isset($runtimeOptions['dockerfileText']) ? (string) $runtimeOptions['dockerfileText'] : '';
        $lines = array('set -e');

        if ($execution['mode'] === 'git') {
          $cloneCommand = 'git clone --depth 1';
          if ($execution['branch'] !== '') {
            $cloneCommand .= ' --branch '.escapeshellarg($execution['branch']);
          }
          $cloneCommand .= ' '.escapeshellarg($execution['repositoryUrl']).' "$WORKSPACE/jobseeker-python-source"';

          $lines[] = 'rm -rf "$WORKSPACE/jobseeker-python-source"';
          $lines[] = $cloneCommand;
          $lines[] = 'export JOBSEEKER_SOURCE_DIR="$WORKSPACE/jobseeker-python-source"';
          $lines[] = 'export JOBSEEKER_ENTRYPOINT='.escapeshellarg($execution['entryPoint']);
          $lines[] = 'export JOBSEEKER_SCRIPT_PATH="$JOBSEEKER_SOURCE_DIR/$JOBSEEKER_ENTRYPOINT"';
        } else {
          $lines[] = 'export JOBSEEKER_SOURCE_DIR='.escapeshellarg($execution['sourceDirectory']);
          $lines[] = 'export JOBSEEKER_SCRIPT_PATH='.escapeshellarg($execution['scriptPath']);
        }

        $lines[] = 'export JOBSEEKER_PYTHON_LIB='.escapeshellarg($pythonLibraryPath);
        $lines[] = 'export JOBSEEKER_PYTHON_SDK="$JOBSEEKER_PYTHON_LIB/jobseeker-sdk"';
        $lines[] = 'export JOBSEEKER_RUNTIME_LIBS="$WORKSPACE/.jobseeker-runtime-libs"';
        $lines[] = 'export JOBSEEKER_PYTHON_RUNTIME='.escapeshellarg($runtimeMode);
        $lines[] = 'export JOBSEEKER_PYTHON='.escapeshellarg($pythonExecutable);
        $lines[] = 'export JOBSEEKER_SCRIPT_DIR="$(dirname "$JOBSEEKER_SCRIPT_PATH")"';
        $lines[] = 'cd "$JOBSEEKER_SOURCE_DIR"';
        $lines[] = 'rm -rf "$JOBSEEKER_RUNTIME_LIBS"';
        $lines[] = '"$JOBSEEKER_PYTHON" -m pip install --quiet --disable-pip-version-check --target "$JOBSEEKER_RUNTIME_LIBS" "$JOBSEEKER_PYTHON_SDK"';

        if ($runtimeMode === 'docker') {
          $dockerScript = implode("\n", array(
            'set -e',
            'mkdir -p /tmp/jobseeker-context',
            'tar -C /tmp/jobseeker-context -xf -',
            'cd /tmp/jobseeker-context/source',
            'JOBSEEKER_SCRIPT_DIR="$(dirname "$JOBSEEKER_ENTRYPOINT")"',
            'rm -rf /tmp/jobseeker-runtime-libs',
            'PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet --disable-pip-version-check --target /tmp/jobseeker-runtime-libs /tmp/jobseeker-context/jobseeker-sdk',
            'JOBSEEKER_REQUIREMENTS=""',
            'JOBSEEKER_USER_LIBS=""',
            'if [ -f "/tmp/jobseeker-context/source/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="/tmp/jobseeker-context/source/requirements.txt"; fi',
            'if [ -f "/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR/requirements.txt"; fi',
            'if [ -n "$JOBSEEKER_REQUIREMENTS" ]; then',
            '  rm -rf /tmp/jobseeker-python-libs',
            '  PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet --disable-pip-version-check --target /tmp/jobseeker-python-libs -r "$JOBSEEKER_REQUIREMENTS"',
            '  JOBSEEKER_USER_LIBS="/tmp/jobseeker-python-libs"',
            'fi',
            'if [ -n "$JOBSEEKER_USER_LIBS" ]; then export PYTHONPATH="/tmp/jobseeker-runtime-libs:$JOBSEEKER_USER_LIBS:/tmp/jobseeker-context/source:/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"; else export PYTHONPATH="/tmp/jobseeker-runtime-libs:/tmp/jobseeker-context/source:/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"; fi',
            'python "$JOBSEEKER_ENTRYPOINT" "$@"'
          ));

          $lines[] = 'export JOBSEEKER_DOCKER_IMAGE='.escapeshellarg($dockerImage);
          if (trim($requirementsText) !== '') {
            $lines[] = 'export JOBSEEKER_PYTHON_REQUIREMENTS_B64='.escapeshellarg(base64_encode($requirementsText));
          }
          if (trim($dockerfileText) !== '') {
            $lines[] = 'export JOBSEEKER_PYTHON_DOCKERFILE_B64='.escapeshellarg(base64_encode($dockerfileText));
          }
          $lines[] = 'command -v docker >/dev/null || { echo "Docker runtime selected, but docker is not available on this Jenkins agent."; exit 127; }';
          $lines[] = 'JOBSEEKER_DOCKER_ENTRYPOINT="${JOBSEEKER_SCRIPT_PATH#$JOBSEEKER_SOURCE_DIR/}"';
          $lines[] = 'JOBSEEKER_DOCKER_CONTEXT="$WORKSPACE/jobseeker-python-docker-context"';
          $lines[] = 'rm -rf "$JOBSEEKER_DOCKER_CONTEXT"';
          $lines[] = 'mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/source" "$JOBSEEKER_DOCKER_CONTEXT/jobseeker-sdk"';
          $lines[] = 'cp -R "$JOBSEEKER_SOURCE_DIR/." "$JOBSEEKER_DOCKER_CONTEXT/source/"';
          $lines[] = 'cp -R "$JOBSEEKER_PYTHON_SDK/." "$JOBSEEKER_DOCKER_CONTEXT/jobseeker-sdk/"';
          $lines[] = 'JOBSEEKER_DOCKER_SCRIPT_DIR="$(dirname "$JOBSEEKER_DOCKER_ENTRYPOINT")"';
          $lines[] = 'if [ -n "${JOBSEEKER_PYTHON_REQUIREMENTS_B64:-}" ]; then mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR"; printf "%s" "$JOBSEEKER_PYTHON_REQUIREMENTS_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt"; fi';
          $lines[] = 'if [ -n "${JOBSEEKER_PYTHON_DOCKERFILE_B64:-}" ]; then printf "%s" "$JOBSEEKER_PYTHON_DOCKERFILE_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile"; fi';
          $lines[] = 'JOBSEEKER_DOCKERFILE=""';
          $lines[] = 'if [ -f "$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile" ]; then JOBSEEKER_DOCKERFILE="$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile"; fi';
          $lines[] = 'if [ -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/Dockerfile" ]; then JOBSEEKER_DOCKERFILE="$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/Dockerfile"; fi';
          $lines[] = 'JOBSEEKER_DOCKER_RUN_IMAGE="$JOBSEEKER_DOCKER_IMAGE"';
          $lines[] = 'if [ -n "$JOBSEEKER_DOCKERFILE" ]; then JOBSEEKER_DOCKER_TAG="$(printf "%s" "${JOB_NAME:-job}-${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"; if [ -z "$JOBSEEKER_DOCKER_TAG" ]; then JOBSEEKER_DOCKER_TAG="manual"; fi; JOBSEEKER_DOCKER_RUN_IMAGE="jobseeker-python-custom:$JOBSEEKER_DOCKER_TAG"; JOBSEEKER_DOCKER_BUILD_CONTEXT="$(dirname "$JOBSEEKER_DOCKERFILE")"; docker build --network host --pull -t "$JOBSEEKER_DOCKER_RUN_IMAGE" -f "$JOBSEEKER_DOCKERFILE" "$JOBSEEKER_DOCKER_BUILD_CONTEXT"; fi';
          $lines[] = 'tar -C "$JOBSEEKER_DOCKER_CONTEXT" -cf - . | docker run --rm -i \\';
          $lines[] = '  --network host \\';
          $lines[] = '  -e "JOBSEEKER_ENTRYPOINT=$JOBSEEKER_DOCKER_ENTRYPOINT" \\';
          $lines[] = '  -e JOB_NAME -e BUILD_NUMBER -e BUILD_ID \\';
          $lines[] = '  -e JOBSEEKER_DB_HOST -e JOBSEEKER_DB_PORT -e JOBSEEKER_DB_USER -e JOBSEEKER_DB_PASSWORD -e JOBSEEKER_DB_NAME \\';
          $lines[] = '  "$JOBSEEKER_DOCKER_RUN_IMAGE" \\';
          $lines[] = '  sh -lc '.escapeshellarg($dockerScript).' sh'.($environmentArgument !== '' ? ' '.$environmentArgument : '');
        } else {
          $lines[] = 'JOBSEEKER_REQUIREMENTS=""';
          $lines[] = 'JOBSEEKER_USER_LIBS=""';
          $lines[] = 'if [ -f "$JOBSEEKER_SOURCE_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="$JOBSEEKER_SOURCE_DIR/requirements.txt"; fi';
          $lines[] = 'if [ -f "$JOBSEEKER_SCRIPT_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="$JOBSEEKER_SCRIPT_DIR/requirements.txt"; fi';
          $lines[] = 'if [ -n "$JOBSEEKER_REQUIREMENTS" ]; then';
          $lines[] = '  rm -rf "$JOBSEEKER_SOURCE_DIR/.jobseeker-python-libs"';
          $lines[] = '  "$JOBSEEKER_PYTHON" -m pip install --quiet --disable-pip-version-check --target "$JOBSEEKER_SOURCE_DIR/.jobseeker-python-libs" -r "$JOBSEEKER_REQUIREMENTS"';
          $lines[] = '  JOBSEEKER_USER_LIBS="$JOBSEEKER_SOURCE_DIR/.jobseeker-python-libs"';
          $lines[] = 'fi';
          $lines[] = 'if [ -n "$JOBSEEKER_USER_LIBS" ]; then export PYTHONPATH="$JOBSEEKER_RUNTIME_LIBS:$JOBSEEKER_USER_LIBS:$JOBSEEKER_SOURCE_DIR:$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"; else export PYTHONPATH="$JOBSEEKER_RUNTIME_LIBS:$JOBSEEKER_SOURCE_DIR:$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"; fi';
          $lines[] = '"$JOBSEEKER_PYTHON" "$JOBSEEKER_SCRIPT_PATH"'.($environmentArgument !== '' ? ' '.$environmentArgument : '');
        }

        return implode("\n", $lines);
      }


    public function send() {

      if(! $this->canManageJobs())
        {
            $this->loadThis();
        }
        else
        {
            header('Content-Type: text/html; charset=utf-8');

            $this->load->library('form_validation');

            // Basic inputs
            $this->form_validation->set_rules('job_name','Job Name','trim|max_length[50]');
            $this->form_validation->set_rules('job_names','Bulk Job Names','trim|max_length[5000]');
            $this->form_validation->set_rules('description','Description','trim|max_length[5000]');

      
            // Abort Build
            $this->form_validation->set_rules('timeoutMinutes','Time Out in Minutes','trim|max_length[50]');
            $this->form_validation->set_rules('timeoutSeconds','Time Out in Seconds','trim|max_length[50]');
            $this->form_validation->set_rules('customCronExpression','Custom Cron Expression','trim|max_length[120]');

            // Enable Email Notification
             $this->form_validation->set_rules('recipients','Recipients','trim|max_length[1000]');


            if($this->form_validation->run() == FALSE)
            {
                $this->index();
            }
            else
            {
                // Basic Inputs
                $job_name = trim((string) $this->security->xss_clean($this->input->post('job_name')));
                $job_names = $this->security->xss_clean($this->input->post('job_names'));
                $submittedJobs = $this->submittedJobNames($job_name, $job_names);

                if (! $submittedJobs['ok']) {
                  $this->session->set_flashdata('error', $submittedJobs['message']);
                  redirect('JobCreation');
                }

                $jobNames = $submittedJobs['names'];
                $job_name = $jobNames[0];
                $description = trim((string) $this->security->xss_clean($this->input->post('description')));
                $triggerAfterSave = $this->security->xss_clean($this->input->post('trigger_after_save')) == '1' ? '1' : '0';
                $submittedUpstreamJobs = $this->cleanSubmittedJobNameList($this->input->post('upstreamJobList'));

                if (! $submittedUpstreamJobs['ok']) {
                  $this->session->set_flashdata('error', $submittedUpstreamJobs['message']);
                  redirect('JobCreation');
                }

                $upstreamJobNames = $submittedUpstreamJobs['names'];

                // Timestamp Checkbox
                $timestamp = $this->security->xss_clean($this->input->post('timestamp'));


                // Trigger Build Periodically Option 
                 $checkBuild = $this->security->xss_clean($this->input->post('checkBuild'));
                 $action = $this->security->xss_clean($this->input->post('action'));

                // Single Build Options
                $singleMinute = $this->input->post('singleMinute');
                $singleHour = $this->input->post('singleHour');
                $singleDayOfMonth = $this->input->post('singleDayOfMonth');
                $singleMonth = $this->input->post('singleMonth');
                $singleDayOfWeek = $this->input->post('singleDayOfWeek');
                $customCronExpression = $this->security->xss_clean($this->input->post('customCronExpression'));

                // Execute a Windows Command Option

                // Start Windows File Upload 
                $winCommand = $this->input->post('winCommand');
                $executionStrategy = $this->input->post('executionStrategy');
                $scriptType = $this->input->post('scriptType');
                $windowsCommandLine = $this->input->post('windowsCommandLine');

                //Environment
                $environment = trim((string) $this->input->post('environment'));
                $checkEnvironment = $this->security->xss_clean($this->input->post('checkEnvironment'));

                if ($environment === '' || $environment === '0') {
                  $this->session->set_flashdata('error', 'Please select the runtime environment for this Jenkins job. Existing jobs without an environment will still be shown as Unknown.');
                  redirect('JobCreation');
                }

                $this->load->model('Context_model');
                if ((int) $this->Context_model->validateEnvironment($environment) === 0) {
                  $this->session->set_flashdata('error', 'The selected runtime environment is not configured in Context Settings.');
                  redirect('JobCreation');
                }

                $checkEnvironment = 1;

                if($executionStrategy == 'script'){
                  if($scriptType == 'talend'){
                          $filelist = glob("repository/".$scriptType."/jobs/".$job_name."/*");
                          $file = glob($filelist[0].'/*.bat');

                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = realpath($file[0]).' --context='.$environment;  
                          } else {
                            $filePath = realpath($file[0]);
                          }
                          
                          // // echo 'WINDOWS - TALEND File Path: <b>'.$filePath.'</b>';
                          // // echo '<hr><br>';
                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // // echo "The file $filePath does not exists";
                            }
                          }
                  } else if ($scriptType == 'batch') {
                        $filelist = glob("repository/".$scriptType."/jobs/".$job_name."/*.bat");
                          $file = glob($filelist[0]);

                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = realpath($file[0]).' --context='.$environment;  
                          } else {
                            $filePath = realpath($file[0]);
                          }
                          // // echo 'WINDOWS - BATCH File Path: <b>'.$filePath.'</b>';
                          // // echo '<hr><br>';
                          // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // // echo "The file $filePath does not exists";
                            }
                          } 
                           
                  } else if ($scriptType == 'python') {
                        $filelist = glob("repository/".$scriptType."/jobs/".$job_name."/*.py");
                          $file = glob($filelist[0]);

                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = realpath($file[0]).' '.$environment;  
                          } else {
                            $filePath = realpath($file[0]);
                          }
                          // // echo 'WINDOWS - PYTHON File Path: <b>'.$filePath.'</b>';
                          // // echo '<hr><br>';

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // // echo "The file $filePath does not exists";
                            }
                          }
                  }
                } else if ($executionStrategy == 'command'){

                  $filePath = $windowsCommandLine;
                  
                }
                // END Windows File Upload

                // Start Linux File Upload
                $jenkins_home = $this->global['jenkins_home'];
                $linuxCommand = $this->input->post('linuxCommand');
                $linuxExecutionStrategy = $this->input->post('linuxExecutionStrategy');
                $linuxScriptType = $this->input->post('linuxScriptType');
                $linuxCommandLine = $this->input->post('linuxCommandLine');
                $pythonSourceMode = $this->selectedPythonSourceMode($this->input->post('pythonSourceMode'));
                $pythonSourcePath = $this->input->post('pythonSourcePath');
                $pythonRepositoryUrl = $this->input->post('pythonRepositoryUrl');
                $pythonRepositoryBranch = $this->input->post('pythonRepositoryBranch');
                $pythonEntryPointRaw = $this->input->post('pythonEntryPoint');
                $pythonInlineCode = $this->input->post('pythonInlineCode');
                $pythonRequirementsText = $this->cleanPythonRequirementsText($this->input->post('pythonRequirementsText'));
                $pythonDockerfileText = $this->cleanPythonDockerfileText($this->input->post('pythonDockerfileText'));
                $pythonInlineFiles = NULL;
                $pythonRuntimeMode = $this->selectedPythonRuntimeMode($this->input->post('pythonRuntimeMode'));
                $linuxRuntimeMode = $this->selectedLinuxRuntimeMode($this->input->post('pythonRuntimeMode'));
                $pythonExecutable = $this->cleanPythonExecutable($this->input->post('pythonVersion'));
                $pythonEntryPoint = $this->cleanPythonEntryPoint($pythonEntryPointRaw, FALSE);
                $postedDockerImage = $this->input->post('pythonDockerImage');
                $pythonExecution = NULL;
                $linuxScriptExecution = NULL;
                $linuxUsesPythonRuntime = ($linuxExecutionStrategy == 'python_inline' || ($linuxExecutionStrategy == 'script' && ($linuxScriptType == 'python' || $linuxScriptType == 'python_inline')));

                if ($linuxExecutionStrategy == 'python_inline' || $linuxScriptType == 'python_inline') {
                  $pythonSourceMode = 'inline';
                }

                $usesInlinePythonSource = $pythonSourceMode === 'inline' || $linuxExecutionStrategy == 'python_inline' || $linuxScriptType == 'python_inline';

                if ($pythonRequirementsText === FALSE) {
                  $this->session->set_flashdata('error', 'Your Python requirements.txt content is too large or contains invalid characters.');
                  redirect('JobCreation');
                }

                if ($pythonDockerfileText === FALSE) {
                  $this->session->set_flashdata('error', 'Your Python Dockerfile content is too large or contains invalid characters.');
                  redirect('JobCreation');
                }

                if ($pythonExecutable === FALSE) {
                  $this->session->set_flashdata('error', 'Please select a valid Python version.');
                  redirect('JobCreation');
                }

                $pythonDockerImage = $this->cleanPythonDockerImage($postedDockerImage, $pythonExecutable);
                if ($pythonRuntimeMode === 'docker' && $pythonDockerImage === FALSE && $linuxUsesPythonRuntime) {
                  $this->session->set_flashdata('error', 'Please select a valid Python Docker image.');
                  redirect('JobCreation');
                }

                if ($pythonDockerImage === FALSE) {
                  $pythonDockerImage = $this->cleanPythonDockerImage('', $pythonExecutable);
                }

                $pythonRuntimeOptions = array(
                  'mode' => $pythonRuntimeMode,
                  'pythonExecutable' => $pythonExecutable,
                  'dockerImage' => $pythonDockerImage,
                  'requirementsText' => $usesInlinePythonSource ? $pythonRequirementsText : '',
                  'dockerfileText' => ($usesInlinePythonSource && $pythonRuntimeMode === 'docker') ? $pythonDockerfileText : ''
                );

                $linuxDockerImage = $this->cleanLinuxDockerImage($postedDockerImage, $linuxExecutionStrategy == 'script' ? $linuxScriptType : '');
                if ($linuxRuntimeMode === 'docker' && $linuxDockerImage === FALSE && ! $linuxUsesPythonRuntime) {
                  $this->session->set_flashdata('error', 'Please select a valid Linux Docker image.');
                  redirect('JobCreation');
                }

                $linuxRuntimeOptions = array(
                  'mode' => $linuxRuntimeMode,
                  'dockerImage' => $linuxDockerImage
                );

                if ($pythonEntryPoint === FALSE) {
                  $this->session->set_flashdata('error', 'You missed to select a valid Python entry file.');
                  redirect('JobCreation');
                }

                $pythonDockerfileTextForInlineSave = $pythonRuntimeMode === 'docker' ? $pythonDockerfileText : NULL;

                if ($usesInlinePythonSource) {
                  $pythonInlineFiles = $this->cleanPythonInlineFilesJson($this->input->post('pythonInlineFilesJson'), $pythonEntryPoint ?: 'main.py');
                  if ($pythonInlineFiles === FALSE) {
                    $this->session->set_flashdata('error', 'Your inline Python workspace contains invalid file paths or too much content. Use .py files inside the job folder.');
                    redirect('JobCreation');
                  }
                }

                    // Check if jenkins home variable exist
                     if($jenkins_home != ''){
                       $storeFolder = $jenkins_home.'/repository/';
                       } else {
                          $storeFolder = 'repository/';
                      }

                    if($linuxExecutionStrategy == 'script'){

                  if($linuxScriptType == 'talend'){

                          $filelist = glob($storeFolder.$linuxScriptType."/jobs/".$job_name."/*");
                          $file = glob($filelist[0].'/*.sh');
                          $scriptPath = realpath($file[0]);
                          $sourceDirectory = realpath($filelist[0]);
                          $scriptArguments = array();

                          if ($scriptPath === FALSE || $sourceDirectory === FALSE || ! is_file($scriptPath)) {
                            $this->session->set_flashdata('error', 'Your file was not uploaded to the server or no executable file was found inside the zip archive.');
                            redirect('JobCreation');
                          }
                          
                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = $scriptPath.' --context='.$environment;
                              $scriptArguments[] = '--context='.$environment;
                          } else {
                            $filePath = $scriptPath;
                          }

                          $linuxScriptExecution = array(
                            'scriptType' => 'talend',
                            'sourceDirectory' => $sourceDirectory,
                            'scriptPath' => $scriptPath,
                            'arguments' => $scriptArguments
                          );

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // // echo "The file $filePath does not exists";
                            }
                          }

                          // // echo 'LINUX - TALEND File Path: <b>'.$filePath.'</b>';
                          // // echo '<hr><br>';
                  } else if ($linuxScriptType == 'bash') {
                        $filelist = glob($storeFolder.$linuxScriptType."/jobs/".$job_name."/*.sh");
                          $file = glob($filelist[0]);
                          $scriptPath = realpath($file[0]);
                          $sourceDirectory = realpath($storeFolder.$linuxScriptType."/jobs/".$job_name);
                          $scriptArguments = array();

                          if ($scriptPath === FALSE || ! is_file($scriptPath)) {
                            $this->session->set_flashdata('error', 'Your file was not uploaded to the server or no executable file was found inside the zip archive.');
                            redirect('JobCreation');
                          }

                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = $scriptPath.' -context "'.$environment.'"';
                              $scriptArguments[] = '-context';
                              $scriptArguments[] = $environment;
                          } else {
                            $filePath = $scriptPath;
                          }

                          $linuxScriptExecution = array(
                            'scriptType' => 'bash',
                            'sourceDirectory' => $sourceDirectory === FALSE ? dirname($scriptPath) : $sourceDirectory,
                            'scriptPath' => $scriptPath,
                            'arguments' => $scriptArguments
                          );

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // echo "The file $filePath does not exists";
                            }
                          }

                         
                          // echo 'LINUX - BASH File Path: <b>'.$filePath.'</b>';
                          // echo '<hr><br>';
                    } else if ($linuxScriptType == 'python' || $linuxScriptType == 'python_inline') {
                          $repositoryRoot = rtrim($storeFolder, '/\\');

                          if ($pythonSourceMode === 'path') {
                            $pythonExecution = $this->resolvePathPythonExecution($repositoryRoot, $pythonSourcePath, $pythonEntryPoint);
                          } else if ($pythonSourceMode === 'git') {
                            $pythonExecution = $this->resolveGitPythonExecution($pythonRepositoryUrl, $pythonRepositoryBranch, $pythonEntryPointRaw);
                          } else if ($pythonSourceMode === 'inline') {
                            $pythonExecution = $this->resolveInlinePythonExecution($repositoryRoot, $job_name, $pythonEntryPointRaw, $pythonInlineCode, $pythonRequirementsText, $pythonDockerfileTextForInlineSave, $pythonInlineFiles);
                          } else {
                            $pythonExecution = $this->resolveUploadedPythonExecution($repositoryRoot, $job_name, $pythonEntryPoint);
                          }

                          if ($pythonExecution === FALSE) {
                           $this->session->set_flashdata('error', 'JobSeeker could not resolve the Python source. Check the upload, repository path, Git URL, and entry file. For ZIP uploads with a top-level folder, use an entry file like pyjob/main.py, or a unique filename such as main.py.');
                           redirect('JobCreation');
                          }
                  }
                } else if ($linuxExecutionStrategy == 'python_inline'){

                  $repositoryRoot = rtrim($storeFolder, '/\\');
                  $pythonExecution = $this->resolveInlinePythonExecution($repositoryRoot, $job_name, $pythonEntryPointRaw, $pythonInlineCode, $pythonRequirementsText, $pythonDockerfileTextForInlineSave, $pythonInlineFiles);

                  if ($pythonExecution === FALSE) {
                   $this->session->set_flashdata('error', 'JobSeeker could not resolve the inline Python source. Check the entry file and code.');
                   redirect('JobCreation');
                  }
                } else if ($linuxExecutionStrategy == 'command'){

                  $filePath = $linuxCommandLine;  
                  
                }

                // END Linux File Upload
          
                 // Repetitive Build Options
                $repetitiveMinute = $this->security->xss_clean($this->input->post('repetitiveMinute'));
                $repetitiveHour = $this->security->xss_clean($this->input->post('repetitiveHour'));
                $repetitiveDayOfMonth = $this->security->xss_clean($this->input->post('repetitiveDayOfMonth'));
                $repetitiveMonth = $this->security->xss_clean($this->input->post('repetitiveMonth'));
                $repetitiveDayOfWeek = $this->security->xss_clean($this->input->post('repetitiveDayOfWeek'));

                // Tag Build Option
                $tag = $this->security->xss_clean($this->input->post('tag'));

                // Abort the build Checkbox
                $abort = $this->security->xss_clean($this->input->post('abort'));
                $timeoutStrategy = $this->security->xss_clean($this->input->post('timeoutStrategy'));
                $timeoutMinutes = $this->security->xss_clean($this->input->post('timeoutMinutes'));
                $timeoutSeconds = $this->security->xss_clean($this->input->post('timeoutSeconds'));

                if ($abort == 1) {
                  if ($timeoutStrategy == 'absolute') {
                    if (! ctype_digit((string) $timeoutMinutes) || (int) $timeoutMinutes < 1) {
                      $this->session->set_flashdata('error', 'You missed to select a valid timeout in minutes for the abort option.');
                      redirect('JobCreation');
                    }
                  } else {
                    $timeoutStrategy = 'noActivity';
                    if (! ctype_digit((string) $timeoutSeconds) || (int) $timeoutSeconds < 60) {
                      $this->session->set_flashdata('error', 'You missed to select a valid timeout in seconds for the abort option.');
                      redirect('JobCreation');
                    }
                  }
                }
            
                // Execute another job section 
                $runJobCheck = $this->security->xss_clean($this->input->post('runJobCheck'));
                $jobList = $this->security->xss_clean($this->input->post('jobList'));
                $optionsRadios = $this->security->xss_clean($this->input->post('optionsRadios'));

                // Enable Email Notification
                $emailCheck = $this->security->xss_clean($this->input->post('emailCheck'));
                $recipients = $this->security->xss_clean($this->input->post('recipients'));

                // Enable Editable Email Notification
                $editableEmailCheck = $this->security->xss_clean($this->input->post('editableEmailCheck'));
                $onSuccess = $this->security->xss_clean($this->input->post('onSuccess'));
                $attSuccess = $this->security->xss_clean($this->input->post('attSuccess'));
                $onFailure = $this->security->xss_clean($this->input->post('onFailure'));
                $attFailure = $this->security->xss_clean($this->input->post('attFailure'));
                $onAbort = $this->security->xss_clean($this->input->post('onAbort'));
                $attAbort = $this->security->xss_clean($this->input->post('attAbort'));

                // Check if some field is missing from editable email notification
                if($editableEmailCheck == 1){
                  if($onSuccess == "0" && $onFailure == "0" && $onAbort == "0"){
                    $this->session->set_flashdata('error', 'You missed to select one field value for Editable email notification.');
                    redirect('JobCreation');
                  }
                }

               
                // Array to String Conversion Section
                $singleMinuteString = $this->cronFieldString($singleMinute, 0, 59);
                $singleHourString = $this->cronFieldString($singleHour, 0, 23);
                $singleDayOfMonthString = $this->cronFieldString($singleDayOfMonth, 1, 31);
                $singleMonthString = $this->cronFieldString($singleMonth, 1, 12);
                $singleDayOfWeekString = $this->cronFieldString($singleDayOfWeek, 1, 7);

                $repetitiveMinuteString = $this->cronMinuteStepString($repetitiveMinute);
                $repetitiveHourString = $this->cronFieldString($repetitiveHour, 0, 23);
                $repetitiveDayOfMonthString = $this->cronFieldString($repetitiveDayOfMonth, 1, 31);
                $repetitiveMonthString = $this->cronFieldString($repetitiveMonth, 1, 12);
                $repetitiveDayOfWeekString = $this->cronFieldString($repetitiveDayOfWeek, 1, 7);
                $customCronString = $this->cleanCustomCronExpression($customCronExpression);

                if($checkBuild == 1){
                  if($action == "single" && ($singleMinuteString === FALSE || $singleHourString === FALSE || $singleDayOfMonthString === FALSE || $singleMonthString === FALSE || $singleDayOfWeekString === FALSE)){
                    $this->session->set_flashdata('error', 'You missed to select valid values for Single Execution scheduling.');
                    redirect('JobCreation');
                  } else if($action == "repetitive" && ($repetitiveMinuteString === FALSE || $repetitiveHourString === FALSE || $repetitiveDayOfMonthString === FALSE || $repetitiveMonthString === FALSE || $repetitiveDayOfWeekString === FALSE)){
                    $this->session->set_flashdata('error', 'You missed to select valid values for Repetitive Execution scheduling.');
                    redirect('JobCreation');
                  } else if($action == "tags" && ! in_array($tag, array('@hourly', '@daily', '@weekly', '@monthly', '@annually', '@yearly', '@midnight'), TRUE)){
                    $this->session->set_flashdata('error', 'You missed to select a valid Execution Tag option.');
                    redirect('JobCreation');
                  } else if($action == "cron" && $customCronString === FALSE){
                    $this->session->set_flashdata('error', 'You missed to provide a valid Jenkins cron expression. Use 5 fields such as H 2 * * 1-5.');
                    redirect('JobCreation');
                  } else if($action != "single" && $action != "repetitive" && $action != "tags" && $action != "cron"){
                    $this->session->set_flashdata('error', 'You missed to select one field value for Build Periodically function');
                    redirect('JobCreation');
                  }
                }

                if ($jobList != null) {
                  $jobListString = rtrim(implode(', ', $jobList), ',');
                }
                // Array to String Conversion Section

                // XMl Creation Node Section

                $dom = new DOMDocument();

                $dom->encoding = 'UTF-8';

                $dom->xmlVersion = '1.1';

                $dom->formatOutput = true;

                $xml_file_name = 'config.xml';

                $root = $dom->createElement('project');

                $node_description = $dom->createElement('description', $description);

                $root->appendChild($node_description);
                $root->appendChild($this->createRuntimeEnvironmentProperties($dom, $environment));
                $this->appendJenkinsEnvironmentAgentAssignment($dom, $root, $environment);

                // Create Trigger Elements
                if($checkBuild == 1){ // If Build Periodically Build is selected then

                  // If Single Build option is selected then
                    if($action == "single") {
                      $triggers = $dom->createElement('triggers');
                      $hudson_triggers = $dom->createElement('hudson.triggers.TimerTrigger');
                      $spec = $dom->createElement('spec', $singleMinuteString.' '.$singleHourString.' '.$singleDayOfMonthString.' '.$singleMonthString.' '.$singleDayOfWeekString);
                      $hudson_triggers->appendChild($spec);  
                      $triggers->appendChild($hudson_triggers);    
                      $root->appendChild($triggers);
                  }

                  // If Repetitive Build option is selected then
                  if($action == "repetitive") {
                     $triggers = $dom->createElement('triggers');
                      $hudson_triggers = $dom->createElement('hudson.triggers.TimerTrigger');
                      $spec = $dom->createElement('spec', $repetitiveMinuteString.' '.$repetitiveHourString.' '.$repetitiveDayOfMonthString.' '.$repetitiveMonthString.' '.$repetitiveDayOfWeekString);
                      $hudson_triggers->appendChild($spec);  
                      $triggers->appendChild($hudson_triggers);    
                      $root->appendChild($triggers);
                  }

                  // If Single Tags option is selected then
                  if($action == "tags") {
                     $triggers = $dom->createElement('triggers');
                      $hudson_triggers = $dom->createElement('hudson.triggers.TimerTrigger');
                      $spec = $dom->createElement('spec', $tag);
                      $hudson_triggers->appendChild($spec);  
                      $triggers->appendChild($hudson_triggers);    
                      $root->appendChild($triggers);
                  }

                  if($action == "cron") {
                     $triggers = $dom->createElement('triggers');
                      $hudson_triggers = $dom->createElement('hudson.triggers.TimerTrigger');
                      $spec = $dom->createElement('spec', $customCronString);
                      $hudson_triggers->appendChild($spec);
                      $triggers->appendChild($hudson_triggers);
                      $root->appendChild($triggers);
                  }
                }

                // Create builders Elements
                $builders = $dom->createElement('builders');

                // Windows Script Execution
                if($winCommand == 1){ // Check if the windows command checkbox is marked
                  if($executionStrategy == 'script' && $scriptType != "0" || $executionStrategy == 'command'){

                    $hudson_task_BatchFile = $dom->createElement('hudson.tasks.BatchFile');
                    $this->appendTextElement($dom, $hudson_task_BatchFile, 'command', $filePath);
                    $builders->appendChild($hudson_task_BatchFile);
                  }
                }  

                // Linux Script Execution
                if($linuxCommand == 1) {
                  if($linuxExecutionStrategy == 'script' && $linuxScriptType != "0"){

                    $hudson_task_BashFile = $dom->createElement('hudson.tasks.Shell');
                    if($linuxScriptType == 'python' || $linuxScriptType == 'python_inline'){
                      $repositoryRoot = rtrim($storeFolder, '/\\');
                      if (! $this->ensurePythonSharedLibrary($repositoryRoot)) {
                        $this->session->set_flashdata('error', 'Unable to prepare the shared Python jobseeker helper.');
                        redirect('JobCreation');
                      }
                      $this->appendTextElement($dom, $hudson_task_BashFile, 'command', $this->buildPythonExecutionCommand($pythonExecution, $repositoryRoot, $this->pythonEnvironmentArgument($environment, $checkEnvironment), $pythonRuntimeOptions));
                    } else {
                      $this->appendTextElement($dom, $hudson_task_BashFile, 'command', $this->buildShellScriptExecutionCommand($linuxScriptExecution, $linuxRuntimeOptions));
                    }

                    $builders->appendChild($hudson_task_BashFile);
                    
                  } else if($linuxExecutionStrategy == 'python_inline') {

                    $hudson_task_BashFile = $dom->createElement('hudson.tasks.Shell');
                    $repositoryRoot = rtrim($storeFolder, '/\\');
                    if (! $this->ensurePythonSharedLibrary($repositoryRoot)) {
                      $this->session->set_flashdata('error', 'Unable to prepare the shared Python jobseeker helper.');
                      redirect('JobCreation');
                    }
                    $this->appendTextElement($dom, $hudson_task_BashFile, 'command', $this->buildPythonExecutionCommand($pythonExecution, $repositoryRoot, $this->pythonEnvironmentArgument($environment, $checkEnvironment), $pythonRuntimeOptions));
                    $builders->appendChild($hudson_task_BashFile);

                  } else if($linuxExecutionStrategy == 'command') {

                    $hudson_task_BashFile = $dom->createElement('hudson.tasks.Shell');
                    $this->appendTextElement($dom, $hudson_task_BashFile, 'command', $this->buildLinuxCommandExecutionCommand($filePath, $linuxRuntimeOptions));
                    $builders->appendChild($hudson_task_BashFile);

                  }
                }

                // Append Builders to root node
                $root->appendChild($builders);

                 // Create Publishers Elements
                 $publishers = $dom->createElement('publishers');





                 $hudson_ExtendedMailer = NULL;
                 $configuredTriggers = NULL;

                 // Editable Email Notification
                 if($editableEmailCheck == 1){ // if enable editable email notification is marked

               
                  $hudson_ExtendedMailer = $dom->createElement('hudson.plugins.emailext.ExtendedEmailPublisher');
                  $attr_hudson_ExtendedMailer = new DOMAttr('plugin', 'email-ext@2.68');
                  $hudson_ExtendedMailer->setAttributeNode($attr_hudson_ExtendedMailer);
                  $publishers->appendChild($hudson_ExtendedMailer);
                
                  $configuredTriggers = $dom->createElement('configuredTriggers');
                  $hudson_ExtendedMailer->appendChild($configuredTriggers);

                  // On Success
                  if($onSuccess != "0") { // if On success template is selected

                  // Load EmailSettings Model in order to fech the email template
                  $this->load->model('emailSettings_model','model');
                  $listMailTemplates = $this->model->fetchName($onSuccess); 

                  $successTrigger = $dom->createElement('hudson.plugins.emailext.plugins.trigger.SuccessTrigger');
                  $configuredTriggers->appendChild($successTrigger);

                  $email = $dom->createElement('email');
                  $successTrigger->appendChild($email);

                  $this->appendTextElement($dom, $email, 'recipientList', $this->emailTemplateRecipientList($listMailTemplates[0]));

                  $this->appendTextElement($dom, $email, 'subject', $this->failureEmailSubject($listMailTemplates[0]->subject, $environment));

                  $this->appendTextElement($dom, $email, 'body', $this->failureEmailBodyWithEnvironment($listMailTemplates[0]->msg, $environment));

                  $recipientProviders = $dom->createElement('recipientProviders');
                  $email->appendChild($recipientProviders);

                  $recipientProvidersPlugin = $dom->createElement('hudson.plugins.emailext.plugins.recipients.DevelopersRecipientProvider');
                  $recipientProviders->appendChild($recipientProvidersPlugin);

                  $attachments = $dom->createElement('attachmentsPattern', '');
                  $email->appendChild($attachments);

                  $attachBuildLog = $dom->createElement('attachBuildLog', $attSuccess);
                  $email->appendChild($attachBuildLog);

                  $compressBuildLog = $dom->createElement('compressBuildLog', 'false');
                  $email->appendChild($compressBuildLog);

                  $replyTo = $dom->createElement('replyTo', '$PROJECT_DEFAULT_REPLYTO');
                  $email->appendChild($replyTo);

                  $contentType = $dom->createElement('contentType', 'both');
                  $email->appendChild($contentType);

                  $this->appendTextElement($dom, $hudson_ExtendedMailer, 'from', $listMailTemplates[0]->from);

                  }


                  // On Failure
                  if($onFailure != "0") { // if On success template is selected

                  // Load EmailSettings Model in order to fech the email template
                  $this->load->model('emailSettings_model','model');
                  $listMailTemplates = $this->model->fetchName($onFailure); 

                  $successTrigger = $dom->createElement('hudson.plugins.emailext.plugins.trigger.FailureTrigger');
                  $configuredTriggers->appendChild($successTrigger);

                  $email = $dom->createElement('email');
                  $successTrigger->appendChild($email);

                  $this->appendTextElement($dom, $email, 'recipientList', $this->emailTemplateRecipientList($listMailTemplates[0]));

                  $this->appendTextElement($dom, $email, 'subject', $listMailTemplates[0]->subject);

                  $this->appendTextElement($dom, $email, 'body', $listMailTemplates[0]->msg);

                  $recipientProviders = $dom->createElement('recipientProviders');
                  $email->appendChild($recipientProviders);

                  $recipientProvidersPlugin = $dom->createElement('hudson.plugins.emailext.plugins.recipients.DevelopersRecipientProvider');
                  $recipientProviders->appendChild($recipientProvidersPlugin);

                  $attachments = $dom->createElement('attachmentsPattern', '');
                  $email->appendChild($attachments);

                  $attachBuildLog = $dom->createElement('attachBuildLog', $attFailure);
                  $email->appendChild($attachBuildLog);

                  $compressBuildLog = $dom->createElement('compressBuildLog', 'false');
                  $email->appendChild($compressBuildLog);

                  $replyTo = $dom->createElement('replyTo', '$PROJECT_DEFAULT_REPLYTO');
                  $email->appendChild($replyTo);

                  $contentType = $dom->createElement('contentType', 'both');
                  $email->appendChild($contentType);

                  $this->appendTextElement($dom, $hudson_ExtendedMailer, 'from', $listMailTemplates[0]->from);

                  }

                   // On Abort
                  if($onAbort != "0") { // if On success template is selected

                  // Load EmailSettings Model in order to fech the email template
                  $this->load->model('emailSettings_model','model');
                  $listMailTemplates = $this->model->fetchName($onAbort);

                  $successTrigger = $dom->createElement('hudson.plugins.emailext.plugins.trigger.AbortedTrigger');
                  $configuredTriggers->appendChild($successTrigger);

                  $email = $dom->createElement('email');
                  $successTrigger->appendChild($email);

                  $this->appendTextElement($dom, $email, 'recipientList', $this->emailTemplateRecipientList($listMailTemplates[0]));

                  $this->appendTextElement($dom, $email, 'subject', $listMailTemplates[0]->subject);

                  $this->appendTextElement($dom, $email, 'body', $listMailTemplates[0]->msg);

                  $recipientProviders = $dom->createElement('recipientProviders');
                  $email->appendChild($recipientProviders);

                  $recipientProvidersPlugin = $dom->createElement('hudson.plugins.emailext.plugins.recipients.DevelopersRecipientProvider');
                  $recipientProviders->appendChild($recipientProvidersPlugin);

                  $attachments = $dom->createElement('attachmentsPattern', '');
                  $email->appendChild($attachments);

                  $attachBuildLog = $dom->createElement('attachBuildLog', $attAbort);
                  $email->appendChild($attachBuildLog);

                  $compressBuildLog = $dom->createElement('compressBuildLog', 'false');
                  $email->appendChild($compressBuildLog);

                  $replyTo = $dom->createElement('replyTo', '$PROJECT_DEFAULT_REPLYTO');
                  $email->appendChild($replyTo);

                  $contentType = $dom->createElement('contentType', 'both');
                  $email->appendChild($contentType);

                  $this->appendTextElement($dom, $hudson_ExtendedMailer, 'from', $listMailTemplates[0]->from);

                  }

                 }


                 // Email Notification
                 if ($emailCheck == 1) { // if email notification checkbox is marked then
                    if ($recipients != '') {

                      if($hudson_ExtendedMailer === NULL || $configuredTriggers === NULL) {
                        $emailExtPublisher = $this->createExtendedEmailPublisher($dom, $publishers);
                        $hudson_ExtendedMailer = $emailExtPublisher['publisher'];
                        $configuredTriggers = $emailExtPublisher['configuredTriggers'];
                      }

                      $this->appendDefaultFailureEmailTrigger($dom, $configuredTriggers, $recipients, $environment);
                    
                    }
                  }

                if($runJobCheck == 1){ // if Run Job Checkbox is marked then   
                  if ($jobList != null){
                    $BuildTrigger = $dom->createElement('hudson.tasks.BuildTrigger');
                    $publishers->appendChild($BuildTrigger);

                    $childProjects = $dom->createElement('childProjects', $jobListString );
                    $BuildTrigger->appendChild($childProjects);

                    $BuildTrigger->appendChild($this->createBuildTriggerThreshold($dom, $optionsRadios));
                  }
                }

                // Append Builders to root node
                $root->appendChild($publishers);

                // Create buildWrappers Elements
                $buildWrappers = $dom->createElement('buildWrappers');

                // If option to add timestamp is enabled then
                if($timestamp == 1) {
                $hudson_plugins_timestamper = $dom->createElement('hudson.plugins.timestamper.TimestamperBuildWrapper');
                $attr_hudson_timestamper = new DOMAttr('plugin', 'timestamper@1.10');
                $hudson_plugins_timestamper->setAttributeNode($attr_hudson_timestamper);
                $buildWrappers->appendChild($hudson_plugins_timestamper);
                }


                // Abort Build if Stucks Option if enabled then
                if ($abort == 1){
                 $hudson_plugins_timeout = $dom->createElement('hudson.plugins.build__timeout.BuildTimeoutWrapper');
                 $attr_hudson_plugins_timeout = new DOMAttr('plugin', 'build-timeout@1.19');
                 $hudson_plugins_timeout->setAttributeNode($attr_hudson_plugins_timeout);

                 if ($timeoutStrategy == 'absolute') { // if absolute then

                 $strategy = $dom->createElement('strategy');
                 $attr_stategy = new DOMAttr('class', 'hudson.plugins.build_timeout.impl.AbsoluteTimeOutStrategy');
                 $strategy->setAttributeNode($attr_stategy);

                 $timeoutMinutes_node = $dom->createElement('timeoutMinutes', $timeoutMinutes);
                 $strategy->appendChild($timeoutMinutes_node);
                 $hudson_plugins_timeout->appendChild($strategy);
                } else { // if not absolute then

                 $strategy = $dom->createElement('strategy');
                 $attr_stategy = new DOMAttr('class', 'hudson.plugins.build_timeout.impl.NoActivityTimeOutStrategy');
                 $strategy->setAttributeNode($attr_stategy);
                 $timeoutSeconds_node = $dom->createElement('timeoutSecondsString', $timeoutSeconds);
                 $strategy->appendChild($timeoutSeconds_node);
                 $hudson_plugins_timeout->appendChild($strategy);

                }

                 $operationList = $dom->createElement('operationList');
                 $hudson_plugins_abort = $dom->createElement('hudson.plugins.build__timeout.operations.AbortOperation');
                 $operationList->appendChild($hudson_plugins_abort);
                 $hudson_plugins_timeout->appendChild($operationList);
                 $buildWrappers->appendChild($hudson_plugins_timeout);
                }
                // End Abort Build if Stucks Option

                $root->appendChild($buildWrappers);

                // Append document to root node
                $dom->appendChild($root);
                // Save XML file
                $xmlPath = '/php/data/'.$xml_file_name;
                if ($dom->save($xmlPath) === FALSE) {
                  $this->session->set_flashdata('error', 'Unable to prepare the Jenkins job configuration.');
                  redirect('JobCreation');
                }

                $xmlContent = file_get_contents($xmlPath);
                if ($xmlContent === FALSE) {
                  $this->session->set_flashdata('error', 'Unable to read the generated Jenkins job configuration.');
                  redirect('JobCreation');
                }

                $savedJobNames = array();
                $savedJobCreationDates = array();
                $createdCount = 0;
                $updatedCount = 0;
                $triggeredCount = 0;
                $upstreamWireCount = 0;
                $saveFailures = array();
                $triggerFailures = array();
                $upstreamWireFailures = array();

                foreach ($jobNames as $targetJobName) {
                  $saveResult = $this->saveGeneratedJenkinsJob($targetJobName, $xmlContent);

                  if (! $saveResult['ok']) {
                    $saveFailures[] = $targetJobName.' (HTTP '.$saveResult['status'].')';
                    continue;
                  }

                  $savedJobNames[] = $targetJobName;

                  if ($saveResult['updated']) {
                    $updatedCount += 1;
                  } else {
                    $createdCount += 1;
                    $createdAt = date('c');
                    $savedJobCreationDates[$targetJobName] = $createdAt;
                    $this->recordJobCreationDate($targetJobName, $createdAt);
                  }

                  if ($triggerAfterSave === '1') {
                    $slotCheck = $this->checkJenkinsEnvironmentSlots($targetJobName, $environment);
                    if (! $slotCheck['ok']) {
                      $triggerFailures[] = $targetJobName.' ('.$slotCheck['message'].')';
                      continue;
                    }

                    $routingCheck = $this->ensureJenkinsJobEnvironmentAgentAssignment($targetJobName, $environment);
                    if (! $routingCheck['ok']) {
                      $triggerFailures[] = $targetJobName.' ('.$routingCheck['message'].')';
                      continue;
                    }

                    $triggerBody = http_build_query(array('ENVIRONMENT' => $environment));
                    $triggerResponse = $this->requestJenkins('POST', $this->jenkinsJobPath($targetJobName) . '/buildWithParameters', $triggerBody, 'application/x-www-form-urlencoded');

                    if (! $this->isSuccessfulJenkinsStatus($triggerResponse['status']) && in_array((int) $triggerResponse['status'], array(400, 404), TRUE)) {
                      $triggerResponse = $this->requestJenkins('POST', $this->jenkinsJobPath($targetJobName) . '/build');
                    }

                    if ($this->isSuccessfulJenkinsStatus($triggerResponse['status'])) {
                      $triggeredCount += 1;
                    } else {
                      $triggerFailures[] = $targetJobName.' (HTTP '.$triggerResponse['status'].')';
                    }
                  }
                }

                if (empty($savedJobNames)) {
                  $this->session->set_flashdata('error', 'No Jenkins jobs were saved. Failed jobs: '.implode(', ', $saveFailures).'.');
                  redirect('JobCreation');
                }

                foreach ($upstreamJobNames as $upstreamJobName) {
                  foreach ($savedJobNames as $targetJobName) {
                    if ($upstreamJobName === $targetJobName) {
                      continue;
                    }

                    $wireResult = $this->appendDownstreamJobToExistingJob($upstreamJobName, $targetJobName, $optionsRadios);

                    if ($wireResult['ok']) {
                      if ($wireResult['updated']) {
                        $upstreamWireCount += 1;
                      }
                    } else {
                      $upstreamWireFailures[] = $upstreamJobName.' -> '.$targetJobName.' (HTTP '.$wireResult['status'].')';
                    }
                  }
                }

                $successMessage = count($savedJobNames).' job(s) saved: '.$createdCount.' created, '.$updatedCount.' updated.';

                if ($upstreamWireCount > 0) {
                  $successMessage .= ' '.$upstreamWireCount.' upstream link(s) updated.';
                }

                if ($triggerAfterSave === '1') {
                  $successMessage .= ' '.$triggeredCount.' trigger request(s) sent.';
                }

                $warnings = array();
                if (! empty($saveFailures)) {
                  $warnings[] = 'Some jobs failed to save: '.implode(', ', $saveFailures).'.';
                }
                if (! empty($triggerFailures)) {
                  $warnings[] = 'Some saved jobs failed to trigger: '.implode(', ', $triggerFailures).'.';
                }
                if (! empty($upstreamWireFailures)) {
                  $warnings[] = 'Some upstream links failed to save: '.implode(', ', $upstreamWireFailures).'.';
                }

                if (! empty($warnings)) {
                  $this->session->set_flashdata('error', implode(' ', $warnings));
                }

                 $this->session->set_flashdata('success', $successMessage);
                 $this->session->set_flashdata('saved_job_name', $savedJobNames[0]);
                 $this->session->set_flashdata('saved_job_names', $savedJobNames);
                 $this->session->set_flashdata('saved_job_creation_dates', $savedJobCreationDates);
                 if (isset($savedJobCreationDates[$savedJobNames[0]])) {
                   $this->session->set_flashdata('saved_job_created_at', $savedJobCreationDates[$savedJobNames[0]]);
                 }

                redirect('JobCreation');

            }
        }
    }

    private function createExtendedEmailPublisher($dom, $publishers) {
      $publisher = $dom->createElement('hudson.plugins.emailext.ExtendedEmailPublisher');
      $attrPublisher = new DOMAttr('plugin', 'email-ext@2.68');
      $publisher->setAttributeNode($attrPublisher);
      $publishers->appendChild($publisher);

      $configuredTriggers = $dom->createElement('configuredTriggers');
      $publisher->appendChild($configuredTriggers);

      return array('publisher' => $publisher, 'configuredTriggers' => $configuredTriggers);
    }

    private function appendTextElement($dom, $parent, $name, $value) {
      $element = $dom->createElement($name);
      $element->appendChild($dom->createTextNode((string) $value));
      $parent->appendChild($element);

      return $element;
    }

    private function emailTemplateRecipientList($template) {
      $recipients = trim((string) $template->to);
      $cc = trim((string) $template->cc);

      if($cc !== '') {
        foreach(explode(',', $cc) as $ccRecipient) {
          $ccRecipient = trim($ccRecipient);
          if($ccRecipient !== '') {
            $recipients .= ($recipients === '' ? '' : ', ') . 'cc:' . $ccRecipient;
          }
        }
      }

      return $recipients;
    }

    private function appendDefaultFailureEmailTrigger($dom, $configuredTriggers, $recipients, $environment = '') {
      $failureTrigger = $dom->createElement('hudson.plugins.emailext.plugins.trigger.FailureTrigger');
      $configuredTriggers->appendChild($failureTrigger);

      $email = $dom->createElement('email');
      $failureTrigger->appendChild($email);

      $recipientList = $dom->createElement('recipientList', $recipients);
      $email->appendChild($recipientList);

      $subject = $dom->createElement('subject', $this->failureEmailSubject('[${BUILD_STATUS}] ${PROJECT_NAME} #${BUILD_NUMBER}', $environment));
      $email->appendChild($subject);

      $body = $dom->createElement('body');
      $body->appendChild($dom->createCDATASection($this->defaultFailureEmailBody($environment)));
      $email->appendChild($body);

      $recipientProviders = $dom->createElement('recipientProviders');
      $email->appendChild($recipientProviders);

      $recipientProvidersPlugin = $dom->createElement('hudson.plugins.emailext.plugins.recipients.DevelopersRecipientProvider');
      $recipientProviders->appendChild($recipientProvidersPlugin);

      $attachments = $dom->createElement('attachmentsPattern', '');
      $email->appendChild($attachments);

      $attachBuildLog = $dom->createElement('attachBuildLog', 'false');
      $email->appendChild($attachBuildLog);

      $compressBuildLog = $dom->createElement('compressBuildLog', 'false');
      $email->appendChild($compressBuildLog);

      $replyTo = $dom->createElement('replyTo', '$PROJECT_DEFAULT_REPLYTO');
      $email->appendChild($replyTo);

      $contentType = $dom->createElement('contentType', 'text/html');
      $email->appendChild($contentType);
    }

    private function environmentEmailPalette($environment) {
      switch ($this->normalizeJobSeekerEnvironment($environment)) {
        case 'DEV':
          return array('start' => '#0f4c81', 'end' => '#2563eb', 'text' => '#dbeafe');
        case 'QA':
          return array('start' => '#047857', 'end' => '#14b8a6', 'text' => '#ccfbf1');
        case 'UAT':
          return array('start' => '#7c3aed', 'end' => '#0ea5e9', 'text' => '#e0f2fe');
        case 'PREPROD':
        case 'HML':
          return array('start' => '#b45309', 'end' => '#f59e0b', 'text' => '#fff7ed');
        case 'PROD':
          return array('start' => '#7f1d1d', 'end' => '#dc2626', 'text' => '#fee2e2');
        case 'LOCAL':
          return array('start' => '#334155', 'end' => '#64748b', 'text' => '#e2e8f0');
        default:
          return array('start' => '#4A00E0', 'end' => '#8E2DE2', 'text' => '#ede9fe');
      }
    }

    private function emailEnvironmentLabel($environment) {
      $environment = $this->normalizeJobSeekerEnvironment($environment);
      return $environment === '' || $environment === '0' || $environment === 'ALL' ? 'Runtime Environment' : $environment;
    }

    private function failureEmailEnvironmentHeader($environment) {
      $palette = $this->environmentEmailPalette($environment);
      $environmentLabel = htmlspecialchars($this->emailEnvironmentLabel($environment), ENT_QUOTES, 'UTF-8');

      return '<div style="background:'.$palette['start'].'; background:linear-gradient(to right, '.$palette['start'].', '.$palette['end'].'); color:#ffffff; padding:20px 24px;">'
        .'<p style="margin:0 0 6px; font-size:12px; letter-spacing:.04em; text-transform:uppercase; color:'.$palette['text'].';">${BUILD_STATUS} - '.$environmentLabel.'</p>'
        .'<h1 style="margin:0; font-size:23px; line-height:1.3;">'.$environmentLabel.' - ${PROJECT_NAME} #${BUILD_NUMBER} failed</h1>'
        .'<p style="margin:8px 0 0; font-size:14px; line-height:1.4; color:'.$palette['text'].';">${CAUSE}</p>'
        .'</div>';
    }

    private function failureEmailSubject($subject, $environment) {
      $environmentLabel = $this->emailEnvironmentLabel($environment);
      return '['.$environmentLabel.'] '.trim((string) $subject);
    }

    private function failureEmailBodyWithEnvironment($body, $environment) {
      $banner = $this->failureEmailEnvironmentHeader($environment);
      $body = (string) $body;

      if (preg_match('/<body\b[^>]*>/i', $body)) {
        return preg_replace_callback('/<body\b[^>]*>/i', function($matches) use ($banner) {
          return $matches[0].$banner;
        }, $body, 1);
      }

      return $banner.$body;
    }

    private function defaultFailureEmailBody($environment = '') {
      return str_replace(
        array('@@JOBSEEKER_ENVIRONMENT_EMAIL_HEADER@@', '@@JOBSEEKER_EMAIL_ENVIRONMENT@@'),
        array($this->failureEmailEnvironmentHeader($environment), htmlspecialchars($this->emailEnvironmentLabel($environment), ENT_QUOTES, 'UTF-8')),
        <<<'HTML'
<html>
  <body style="margin:0; padding:0; background:#f3f4f6; color:#17202a; font-family:Arial, Helvetica, sans-serif;">
    <div style="max-width:780px; margin:0 auto; padding:24px;">
      <div style="background:#ffffff; border:1px solid #d8dee9; border-radius:6px; overflow:hidden;">
        @@JOBSEEKER_ENVIRONMENT_EMAIL_HEADER@@
        <div style="padding:24px;">
          <p style="margin:0 0 18px; font-size:15px; line-height:1.55;">Jenkins marked this JobSeeker build as failed. Start with the highlighted error excerpt, then open the console log if the surrounding context is needed.</p>
          <p style="margin:0 0 20px;">
            <a href="${BUILD_URL}" style="display:inline-block; margin:0 8px 8px 0; padding:9px 13px; background:#1f2937; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px;">Open build</a>
            <a href="${BUILD_URL}console" style="display:inline-block; margin:0 8px 8px 0; padding:9px 13px; background:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px;">Console log</a>
            <a href="${BUILD_URL}consoleText" style="display:inline-block; margin:0 8px 8px 0; padding:9px 13px; background:#475569; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px;">Raw log</a>
            <a href="${PROJECT_URL}" style="display:inline-block; margin:0 0 8px 0; padding:9px 13px; background:#e5e7eb; color:#111827; text-decoration:none; border-radius:4px; font-size:13px;">Job page</a>
          </p>
          <table style="width:100%; border-collapse:collapse; margin:0 0 20px; font-size:14px;">
            <tr><th align="left" style="width:150px; padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Job</th><td style="padding:8px; border:1px solid #d8dee9;">${PROJECT_NAME}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Environment</th><td style="padding:8px; border:1px solid #d8dee9; font-weight:bold;">@@JOBSEEKER_EMAIL_ENVIRONMENT@@</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build</th><td style="padding:8px; border:1px solid #d8dee9;">#${BUILD_NUMBER}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Status</th><td style="padding:8px; border:1px solid #d8dee9; color:#991b1b; font-weight:bold;">${BUILD_STATUS}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build ID</th><td style="padding:8px; border:1px solid #d8dee9;">${ENV,var="BUILD_ID"}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build tag</th><td style="padding:8px; border:1px solid #d8dee9;">${ENV,var="BUILD_TAG"}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Node</th><td style="padding:8px; border:1px solid #d8dee9;">${ENV,var="NODE_NAME"} / executor ${ENV,var="EXECUTOR_NUMBER"}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Workspace</th><td style="padding:8px; border:1px solid #d8dee9; word-break:break-all;">${ENV,var="WORKSPACE"}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Cause</th><td style="padding:8px; border:1px solid #d8dee9;">${CAUSE}</td></tr>
            <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build URL</th><td style="padding:8px; border:1px solid #d8dee9; word-break:break-all;"><a href="${BUILD_URL}" style="color:#2563eb;">${BUILD_URL}</a></td></tr>
          </table>
          <h2 style="margin:20px 0 8px; font-size:16px;">Error Focus</h2>
          <pre style="white-space:pre-wrap; word-break:break-word; background:#111827; color:#e5e7eb; padding:14px; border-radius:4px; font-size:12px; line-height:1.45;">${BUILD_LOG_REGEX, regex="(?i)(traceback|[a-z_][a-z0-9_]*(error|exception):|error|exception|fatal|command not found|no such file|permission denied|returned non-zero exit status|script returned exit code|build step .* marked build as failure)", linesBefore=5, linesAfter=0, maxTailMatches=6, maxLineLength=360, showTruncatedLines=false, escapeHtml=true, matchedLineHtmlStyle="color:#fecaca; font-weight:bold;", defaultValue="No explicit error lines were detected in the captured console output."}</pre>
          <h2 style="margin:20px 0 8px; font-size:16px;">Recent Console Output</h2>
          <pre style="white-space:pre-wrap; word-break:break-word; background:#0f172a; color:#e5e7eb; padding:14px; border-radius:4px; font-size:12px; line-height:1.45;">${BUILD_LOG, maxLines=160, maxLineLength=500, escapeHtml=true}</pre>
        </div>
      </div>
    </div>
  </body>
</html>
HTML
  );
    }

    public function readXML() {

        header("Content-Type: text/xml");
        $content = file_get_contents("xml/config.xml");
        // // echo $content;

    }

}