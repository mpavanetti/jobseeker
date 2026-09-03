<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class Context extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
      parent::__construct();
      $this->load->helper('url','form');
      $this->load->model('Context_model','model');
      $this->load->library('session');
      $this->isLoggedIn();   
    }

    private function selectedContextEnvironment()
    {
	  if ($this->jobSeekerIsStandaloneDeployment()) {
		return $this->jobSeekerStandaloneEnvironment();
	  }
      $environment = trim((string) $this->input->get('environment', TRUE));
      if ($environment === '') {
        $environment = $this->jobSeekerEnvironmentPreference();
      }
      $environment = $this->normalizeJobSeekerEnvironment($environment);
      return $environment === '' || $environment === '*' ? 'ALL' : $environment;
    }

    private function contextRowsMatchSelectedEnvironment($rows, $environment = NULL)
    {
      $environment = $environment === NULL ? $this->selectedContextEnvironment() : $this->normalizeJobSeekerEnvironment($environment);
      if ($environment === 'ALL') {
        return ! empty($rows);
      }
      if (empty($rows)) {
        return FALSE;
      }

      return $this->normalizeJobSeekerEnvironment($rows[0]->Environment) === $environment;
    }


    public function projectDetails() {

     if($this->isManager() == TRUE)
     {
      $this->loadThis();
    }
    else
    {

      $this->global['pageTitle'] = 'Job Seeker : Project Config';
      $user = $this->global['name'];

      $data["list"] = $this->model->listProjects();
      $data["projects"] = $this->model->listAvailableProjects();
      $data["activeprojects"] = $this->model->listActiveProjects();
      $data["role"] = $this->isManager();

      $this->loadViews("projectDetails", $this->global, $data, NULL);
    }
  }

  public function environment() {

   if($this->isManager() == TRUE)
   {
    $this->loadThis();
  }
  else
  {

    $this->global['pageTitle'] = 'Job Seeker : Environment Config';
    $user = $this->global['name'];

	$environmentRows = $this->jobSeekerFilterEnvironmentRows($this->model->listEnvironments());
    $data["list"] = $environmentRows;
    $data["environments"] = count($environmentRows);
    $data["activeEnvironments"] = count(array_filter($environmentRows, function($row) { return (int) $row->IsActive === 1; }));
    $data["role"] = $this->isManager();

    $this->loadViews("environment", $this->global, $data, NULL);
  }
}

public function fetchEnvironments() {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson["data"] = $this->jobSeekerFilterEnvironmentRows($this->model->listEnvironments());
         echo json_encode($listJobsJson, JSON_PRETTY_PRINT);

     }

public function contextDetails() {

   if($this->isManager() == TRUE)
   {
    $this->loadThis();
  }
  else
  {

    $this->global['pageTitle'] = 'Job Seeker : ContextDetails Config';
    $user = $this->global['name'];
    $selectedEnvironment = $this->selectedContextEnvironment();

    $data["user"] = $user;
    $data["list"] = $this->model->listContexts($selectedEnvironment);
	$data["comparisonList"] = $this->model->listContexts('ALL');
	if ($this->jobSeekerIsStandaloneDeployment()) {
	  $data["comparisonList"] = $this->model->listContexts($selectedEnvironment);
	}
    $data["listProjects"] = $this->model->listProjects();
	$data["comparisonEnvironments"] = $this->jobSeekerFilterEnvironmentRows($this->model->listEnvironments());
    $data["listEnvironments"] = $data["comparisonEnvironments"];
    if ($selectedEnvironment !== 'ALL') {
      $data["listEnvironments"] = array_values(array_filter($data["listEnvironments"], function($row) use ($selectedEnvironment) {
        return $this->normalizeJobSeekerEnvironment($row->Environment) === $selectedEnvironment;
      }));
    }
    $data["contexts"] = $this->model->listAvailableContexts($selectedEnvironment);
    $data["activeContexts"] = $this->model->listActiveContexts($selectedEnvironment);
    $data["selectedEnvironment"] = $selectedEnvironment;
    $this->global['selectedEnvironment'] = $selectedEnvironment;
    $data["role"] = $this->isManager();

    $this->loadViews("contextDetails", $this->global, $data, NULL);
  }
}

public function promotion() {

  if ($this->jobSeekerIsStandaloneDeployment()) {
    $this->session->set_flashdata('error', 'Cross-environment promotion is managed between standalone deployments.');
    redirect('Context/contextDetails');
    return;
  }

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {
    $this->global['pageTitle'] = 'Job Seeker : Environment Deployment';
    $data["listEnvironments"] = $this->model->listEnvironments();
    $data["listProjects"] = $this->model->listProjects();
    $selectedEnvironment = $this->selectedPromotionEnvironment();
    $jenkinsJobs = $this->listPromotionJenkinsJobs();
    $data["jenkinsJobs"] = $this->filterPromotionWorkloads($this->annotatePromotionWorkloads($jenkinsJobs['jobs']), $selectedEnvironment);
    $data["jenkinsError"] = $jenkinsJobs['error'];
    $data["jenkinsStatus"] = $jenkinsJobs['status'];
    $data["promotionHistory"] = $this->listPromotionHistory();
    $data["selectedEnvironment"] = $selectedEnvironment;
    $this->global['selectedEnvironment'] = $selectedEnvironment;
    $data["role"] = $this->isManager();

    $this->loadViews("contextPromotion", $this->global, $data, NULL);
  }
}

public function promotionJobs() {
	if ($this->jobSeekerIsStandaloneDeployment()) {
	  $this->output->set_status_header(409)->set_content_type('application/json')->set_output(json_encode(array('jobs' => array(), 'error' => 'Cross-environment promotion is disabled in standalone mode.')));
	  return;
	}
  if($this->isManager() == TRUE)
  {
    $this->output->set_status_header(403)->set_content_type('application/json')->set_output(json_encode(array('jobs' => array(), 'error' => 'Access denied.')));
    return;
  }

  $environment = $this->selectedPromotionEnvironment();
  $jenkinsJobs = $this->listPromotionJenkinsJobs();
  $jobs = $this->filterPromotionWorkloads($this->annotatePromotionWorkloads($jenkinsJobs['jobs']), $environment);
  $this->output
    ->set_status_header((int) $jenkinsJobs['status'])
    ->set_header('Cache-Control: no-store, max-age=0')
    ->set_content_type('application/json')
    ->set_output(json_encode(array('jobs' => $jobs, 'environment' => $environment, 'error' => $jenkinsJobs['error'])));
}

private function selectedPromotionEnvironment() {
  $environment = trim((string) $this->input->get('environment', TRUE));
  if ($environment === '') {
    $environment = $this->jobSeekerEnvironmentPreference();
  }
  $environment = $this->normalizeJobSeekerEnvironment($environment);
  return $environment === '' || $environment === '*' ? 'ALL' : $environment;
}

private function filterPromotionWorkloads($jobs, $environment) {
  $environment = $this->normalizeJobSeekerEnvironment($environment);
  if ($environment === '' || $environment === 'ALL') {
    return array_values($jobs);
  }

  return array_values(array_filter($jobs, function($job) use ($environment) {
    $jobEnvironment = ! empty($job['pipelineEnvironment']) ? $job['pipelineEnvironment'] : (isset($job['environment']) ? $job['environment'] : '');
    return $this->normalizeJobSeekerEnvironment($jobEnvironment) === $environment;
  }));
}

public function promoteContext() {

  $this->session->set_flashdata('error', 'Context variable deployment was replaced by workload deployment. Select a source job or pipeline and target environment below.');
  redirect('Context/promotion');
}

public function previewJobPromotion() {

  header('Content-type:application/json;charset=utf-8');
	if ($this->jobSeekerIsStandaloneDeployment()) {
	  $this->output->set_status_header(409);
	  echo json_encode(array('ok' => FALSE, 'message' => 'Cross-environment promotion is disabled in standalone mode.'));
	  return;
	}

  if($this->isManager() == TRUE)
  {
    echo json_encode(array('ok' => FALSE, 'message' => 'Access denied.'));
    return;
  }

  $input = $this->readJobPromotionInput(FALSE);
  if (! $input['ok']) {
    echo json_encode($input);
    return;
  }

  echo json_encode($this->buildJobPromotionResult($input, TRUE), JSON_PRETTY_PRINT);
}

public function promoteJob() {
	if ($this->jobSeekerIsStandaloneDeployment()) {
	  $this->output->set_status_header(409)->set_content_type('application/json')->set_output(json_encode(array('ok' => FALSE, 'message' => 'Cross-environment promotion is disabled in standalone mode.')));
	  return;
	}

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {
    $this->load->library('form_validation');

    $this->form_validation->set_rules('sourceJob','Source Job','required|max_length[255]');
    $this->form_validation->set_rules('targetJobName','Target Job Name','trim|max_length[255]');
    $this->form_validation->set_rules('sourceEnvironment','Source Environment','required|integer');
    $this->form_validation->set_rules('targetEnvironment','Target Environment','required|integer');
    $this->form_validation->set_rules('promotionProject','Context Project','trim|integer');

    if($this->form_validation->run() == FALSE)
    {
      $this->promotion();
    }
    else
    {
      $input = $this->readJobPromotionInput(TRUE);

      if (! $input['ok']) {
        $this->session->set_flashdata('error', $input['message']);
        redirect('Context/promotion');
      }

      $result = $this->buildJobPromotionResult($input, FALSE);
      $this->recordPromotionHistory($input, $result);

      if (! $result['ok']) {
        $this->session->set_flashdata('error', $result['message']);
      } else {
        $action = $result['target_exists'] ? 'updated' : 'created';
        $contextSummary = !empty($result['context_promotion']['enabled']) ? ' Contexts: '.$result['context_promotion']['result']['created'].' created, '.$result['context_promotion']['result']['updated'].' updated, '.$result['context_promotion']['result']['skipped'].' skipped.' : '';
        $rollbackSummary = !empty($result['rollback_id']) ? ' Rollback checkpoint: '.$result['rollback_id'].'.' : '';
        $this->session->set_flashdata('success', 'Deployed '.$result['job_count'].' Jenkins job(s) from '.$input['source_environment']->Environment.' to '.$input['target_environment']->Environment.'. Root target '.$input['target_job'].' '.$action.'. Command updates: '.$result['command_updates'].', parameter updates: '.$result['parameter_updates'].', artifact folders copied: '.count($result['artifacts']['copied']).'.'.$contextSummary.$rollbackSummary);
        if (!empty($result['rollback_id'])) {
          $this->session->set_flashdata('rollback_id', $result['rollback_id']);
        }
      }

      redirect('Context/promotion');
    }
  }
}

public function rollbackJobPromotion() {
	if ($this->jobSeekerIsStandaloneDeployment()) {
	  $this->output->set_status_header(409)->set_content_type('application/json')->set_output(json_encode(array('ok' => FALSE, 'message' => 'Cross-environment promotion is disabled in standalone mode.')));
	  return;
	}

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {
    $rollbackId = $this->security->xss_clean($this->input->post('rollbackId'));
    $result = $this->performPromotionRollback($rollbackId);
    $this->updatePromotionHistoryRollback($rollbackId, $result);

    if (! $result['ok']) {
      $this->session->set_flashdata('error', $result['message']);
    } else {
      $this->session->set_flashdata('success', $result['message']);
    }

    redirect('Context/promotion');
  }
}

private function listPromotionJenkinsJobs() {
  $fields = '_class,name,fullName,color,buildable,lastBuild[number,result,timestamp],property[parameterDefinitions[name,defaultParameterValue[value]]]';
  $tree = 'jobs['.$fields.',jobs['.$fields.',jobs['.$fields.']]]';
  $response = $this->requestJenkins('GET', 'api/json?tree='.rawurlencode($tree));

  if ((int) $response['status'] !== 200) {
    return array('jobs' => array(), 'error' => 'Jenkins jobs could not be loaded. HTTP '.$response['status'].'.', 'status' => $response['status']);
  }

  $payload = json_decode($response['body']);
  if (! is_object($payload) || ! isset($payload->jobs) || ! is_array($payload->jobs)) {
    return array('jobs' => array(), 'error' => 'Jenkins returned an unexpected job list payload.', 'status' => 502);
  }

  $jobs = $this->flattenPromotionJenkinsJobs($payload->jobs);
  usort($jobs, function($left, $right) {
    return strcasecmp($left['fullName'], $right['fullName']);
  });

  return array('jobs' => $jobs, 'error' => '', 'status' => 200);
}

private function annotatePromotionWorkloads($jobs) {
  $this->load->model('Pipeline_model', 'promotionPipelines');
  $pipelinesByJob = array();
  foreach ($this->promotionPipelines->listPipelines() as $pipeline) {
    if (!empty($pipeline->jenkins_job_name)) {
      $pipelinesByJob[$pipeline->jenkins_job_name] = $pipeline;
    }
  }

  foreach ($jobs as &$job) {
    $job['workloadType'] = 'job';
    $job['displayName'] = $job['fullName'];
    $job['pipelineId'] = 0;
    $job['pipelineKey'] = '';
    $job['pipelineVersion'] = 0;
    $job['pipelineEnvironment'] = '';
    $job['pipelineNodeCount'] = 0;
    $job['pipelineSchedule'] = '';

    if (!isset($pipelinesByJob[$job['fullName']])) {
      continue;
    }

    $pipeline = $pipelinesByJob[$job['fullName']];
    $graph = json_decode((string) $pipeline->graph_json, TRUE);
    $job['workloadType'] = 'pipeline';
    $job['displayName'] = $pipeline->name;
    $job['pipelineId'] = (int) $pipeline->id;
    $job['pipelineKey'] = $pipeline->pipeline_key;
    $job['pipelineVersion'] = (int) $pipeline->version;
    $job['pipelineEnvironment'] = $pipeline->environment;
    $job['pipelineNodeCount'] = isset($graph['nodes']) && is_array($graph['nodes']) ? count($graph['nodes']) : 0;
    $job['pipelineSchedule'] = !empty($pipeline->schedule_enabled) ? (string) $pipeline->schedule_cron : '';
  }
  unset($job);

  usort($jobs, function($left, $right) {
    if ($left['workloadType'] !== $right['workloadType']) {
      return $left['workloadType'] === 'pipeline' ? -1 : 1;
    }
    return strcasecmp($left['displayName'], $right['displayName']);
  });
  return $jobs;
}

private function flattenPromotionJenkinsJobs($jobs) {
  $flatJobs = array();

  foreach ((array) $jobs as $job) {
    if (! is_object($job)) {
      continue;
    }

    $jobClass = isset($job->_class) ? (string) $job->_class : '';
    $isFolder = stripos($jobClass, 'Folder') !== FALSE;

    if (! empty($job->fullName) && ! $isFolder) {
    $flatJobs[] = array(
        'name' => isset($job->name) ? (string) $job->name : (string) $job->fullName,
        'fullName' => (string) $job->fullName,
        'color' => isset($job->color) ? (string) $job->color : '',
      'buildable' => isset($job->buildable) ? (bool) $job->buildable : TRUE,
      'lastBuild' => isset($job->lastBuild) ? $job->lastBuild : NULL,
      'environment' => $this->jenkinsEnvironmentFromJobData($job, (string) $job->fullName)
    );
    }

    if (isset($job->jobs) && is_array($job->jobs)) {
      $flatJobs = array_merge($flatJobs, $this->flattenPromotionJenkinsJobs($job->jobs));
    }
  }

  return $flatJobs;
}

private function readJobPromotionInput($targetRequired) {
  $sourceJob = $this->security->xss_clean($this->input->post('sourceJob'));
  $targetJob = $this->security->xss_clean($this->input->post('targetJobName'));
  $sourceEnvironmentId = (int) $this->security->xss_clean($this->input->post('sourceEnvironment'));
  $targetEnvironmentId = (int) $this->security->xss_clean($this->input->post('targetEnvironment'));
  $overwrite = $this->input->post('overwriteExisting') == '1';
  $includeDependencies = $this->input->post('includeDependencies') == '1';
  $promoteContexts = $this->input->post('promoteContexts') == '1';
  $contextProjectId = (int) $this->security->xss_clean($this->input->post('promotionProject'));
  $overwriteContexts = $this->input->post('overwriteContexts') == '1';
  $createRollback = $this->input->post('createRollback') !== '0';

  $sourceJobClean = $this->cleanPromotionJobName($sourceJob);
  if (! $sourceJobClean['ok']) {
    return array('ok' => FALSE, 'message' => $sourceJobClean['message']);
  }

  if ($sourceEnvironmentId <= 0 || $targetEnvironmentId <= 0) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Select a source environment and a target environment.');
  }

  if ($sourceEnvironmentId == $targetEnvironmentId) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Source and target environments must be different.');
  }

  $sourceEnvironment = $this->model->getEnvironment($sourceEnvironmentId);
  $targetEnvironment = $this->model->getEnvironment($targetEnvironmentId);

  if (empty($sourceEnvironment) || empty($targetEnvironment)) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Environment was not found.');
  }

  $contextProject = NULL;
  if ($promoteContexts) {
    if ($contextProjectId <= 0) {
      return array('ok' => FALSE, 'message' => 'Deployment failed. Select a context project when context deployment is enabled.');
    }

    $contextProject = $this->model->getProject($contextProjectId);
    if (empty($contextProject)) {
      return array('ok' => FALSE, 'message' => 'Deployment failed. Context project was not found.');
    }
  }

  $targetJob = trim((string) $targetJob);
  if ($targetJob === '') {
    $targetJob = $this->suggestPromotedJobName($sourceJobClean['name'], $sourceEnvironment->Environment, $targetEnvironment->Environment);
  }

  $targetJobClean = $this->cleanPromotionJobName($targetJob);
  if (! $targetJobClean['ok']) {
    return array('ok' => FALSE, 'message' => $targetJobClean['message']);
  }

  if ($sourceJobClean['name'] === $targetJobClean['name']) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Target job must be a separate Jenkins job so the source environment remains unchanged.');
  }

  return array(
    'ok' => TRUE,
    'source_job' => $sourceJobClean['name'],
    'target_job' => $targetJobClean['name'],
    'source_environment' => $sourceEnvironment,
    'target_environment' => $targetEnvironment,
    'overwrite' => $overwrite,
    'include_dependencies' => $includeDependencies,
    'promote_contexts' => $promoteContexts,
    'context_project_id' => $contextProjectId,
    'context_project' => $contextProject,
    'overwrite_contexts' => $overwriteContexts,
    'create_rollback' => $createRollback,
    'user' => isset($this->global['name']) ? $this->global['name'] : ''
  );
}

private function buildJobPromotionResult($input, $previewOnly) {
  $plan = $this->buildJobPromotionPlan($input);
  if (! $plan['ok']) {
    return $plan;
  }

  $preparedJobs = array();
  $totals = array('command_updates' => 0, 'parameter_updates' => 0, 'artifact_path_updates' => 0, 'downstream_updates' => 0);
  $allArtifacts = array('planned' => array(), 'copied' => array(), 'skipped' => array(), 'errors' => array());
  $commandPreviews = array();

  foreach ($plan['jobs'] as $index => $jobInput) {
    $prepared = $this->prepareSingleJobPromotion($jobInput, $index === 0);
    if (! $prepared['ok']) {
      return $prepared;
    }

    $preparedJobs[] = $prepared;
    $totals['command_updates'] += $prepared['command_updates'];
    $totals['parameter_updates'] += $prepared['parameter_updates'];
    $totals['artifact_path_updates'] += $prepared['artifact_path_updates'];
    $totals['downstream_updates'] += $prepared['downstream_updates'];
    $allArtifacts['planned'] = array_merge($allArtifacts['planned'], $prepared['artifacts']['planned']);
    $allArtifacts['skipped'] = array_merge($allArtifacts['skipped'], $prepared['artifacts']['skipped']);

    foreach ($prepared['command_previews'] as $commandPreview) {
      if (count($commandPreviews) >= 6) {
        break;
      }
      $commandPreview['job'] = $prepared['source_job'];
      $commandPreviews[] = $commandPreview;
    }
  }

  $contextPlan = $this->prepareContextPromotionPlan($input);
  if (! $contextPlan['ok']) {
    return $contextPlan;
  }

  if ($previewOnly) {
    return $this->promotionResultPayload(TRUE, 'Deployment preview is ready.', $input, $preparedJobs, $totals, $allArtifacts, $commandPreviews, $contextPlan, '');
  }

  $rollbackId = '';
  if (! empty($input['create_rollback'])) {
    $rollback = $this->createPromotionRollback($input, $preparedJobs, $contextPlan);
    if (! $rollback['ok']) {
      return $rollback;
    }
    $rollbackId = $rollback['id'];
  }

  foreach ($preparedJobs as $prepared) {
    $artifactCopy = $this->copyPromotionArtifacts($prepared['source_job'], $prepared['target_job'], $input['overwrite'], FALSE);
    if (! empty($artifactCopy['errors'])) {
      return $this->promotionFailureWithAutomaticRollback('Deployment failed while copying artifacts for '.$prepared['source_job'].': '.implode(' ', $artifactCopy['errors']).'.', $rollbackId);
    }

    $allArtifacts['copied'] = array_merge($allArtifacts['copied'], $artifactCopy['copied']);
    $allArtifacts['skipped'] = array_merge($allArtifacts['skipped'], $artifactCopy['skipped']);

    $save = $this->savePreparedPromotionJob($prepared);
    if (! $save['ok']) {
      return $this->promotionFailureWithAutomaticRollback('Deployment failed. Jenkins refused to save '.$prepared['target_job'].'. HTTP '.$save['status'].'.', $rollbackId);
    }
  }

  if (! empty($contextPlan['enabled'])) {
    $contextPlan['result'] = $this->model->promoteContexts($input['context_project_id'], (int) $input['source_environment']->Id, (int) $input['target_environment']->Id, $input['user'], $input['overwrite_contexts']);
  }

  return $this->promotionResultPayload(TRUE, 'Deployment completed.', $input, $preparedJobs, $totals, $allArtifacts, $commandPreviews, $contextPlan, $rollbackId);
}

private function promotionFailureWithAutomaticRollback($message, $rollbackId) {
  if ($rollbackId === '') {
    return array('ok' => FALSE, 'message' => $message, 'rollback_id' => '');
  }

  $rollback = $this->performPromotionRollback($rollbackId);
  if (! empty($rollback['ok'])) {
    return array('ok' => FALSE, 'message' => $message.' The deployment checkpoint was rolled back automatically.', 'rollback_id' => $rollbackId, 'rolled_back' => TRUE);
  }

  return array('ok' => FALSE, 'message' => $message.' Automatic rollback also reported an error: '.$rollback['message'].' Checkpoint: '.$rollbackId.'.', 'rollback_id' => $rollbackId, 'rolled_back' => FALSE);
}

private function prepareSingleJobPromotion($input, $requireEnvironmentBinding) {
  $sourcePath = $this->jenkinsJobPath($input['source_job']);
  $sourceResponse = $this->requestJenkins('GET', $sourcePath . '/config.xml');

  if ((int) $sourceResponse['status'] !== 200) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Source Jenkins job '.$input['source_job'].' config could not be loaded. HTTP '.$sourceResponse['status'].'.');
  }

  $detectedEnvironment = $this->detectPromotionEnvironment($sourceResponse['body'], $input['source_job']);
  if ($requireEnvironmentBinding && ! empty($detectedEnvironment['environment']) && ! $this->promotionEnvironmentsEquivalent($detectedEnvironment['environment'], $input['source_environment']->Environment)) {
    return array('ok' => FALSE, 'message' => 'Deployment stopped. Source Jenkins job '.$input['source_job'].' appears to run in '.$detectedEnvironment['environment'].' from '.$detectedEnvironment['source'].', not '.$input['source_environment']->Environment.'. Select the detected source environment before deploying.');
  }

  $jobNameMap = isset($input['job_name_map']) ? $input['job_name_map'] : array();
  $transform = $this->transformPromotedJenkinsConfig($sourceResponse['body'], $input['source_environment']->Environment, $input['target_environment']->Environment, $input['source_job'], $input['target_job'], $jobNameMap);
  if (! $transform['ok']) {
    return $transform;
  }

  if ($requireEnvironmentBinding && ($transform['command_updates'] + $transform['parameter_updates']) < 1) {
    return array('ok' => FALSE, 'message' => 'Deployment stopped. JobSeeker could not find '.$input['source_environment']->Environment.' in a Jenkins environment parameter, --context argument, environment assignment, or Python environment argument inside '.$input['source_job'].'.');
  }

  $targetPath = $this->jenkinsJobPath($input['target_job']);
  $targetResponse = $this->requestJenkins('GET', $targetPath . '/api/json');
  $targetExists = (int) $targetResponse['status'] === 200;

  if (! $targetExists && (int) $targetResponse['status'] !== 404) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Target Jenkins job '.$input['target_job'].' state could not be checked. HTTP '.$targetResponse['status'].'.');
  }

  if ($targetExists && ! $input['overwrite']) {
    return array('ok' => FALSE, 'message' => 'Deployment stopped. Target Jenkins job '.$input['target_job'].' already exists. Enable overwrite to update it.', 'target_exists' => TRUE);
  }

  $artifacts = $this->copyPromotionArtifacts($input['source_job'], $input['target_job'], $input['overwrite'], TRUE);
  if (! empty($artifacts['errors'])) {
    return array('ok' => FALSE, 'message' => 'Deployment failed while preparing artifacts for '.$input['source_job'].': '.implode(' ', $artifacts['errors']).'.', 'artifacts' => $artifacts);
  }

  return array(
    'ok' => TRUE,
    'source_job' => $input['source_job'],
    'target_job' => $input['target_job'],
    'target_exists' => $targetExists,
    'detected_environment' => $detectedEnvironment,
    'xml' => $transform['xml'],
    'command_updates' => $transform['command_updates'],
    'parameter_updates' => $transform['parameter_updates'],
    'artifact_path_updates' => $transform['artifact_path_updates'],
    'downstream_updates' => $transform['downstream_updates'],
    'command_previews' => $transform['command_previews'],
    'artifacts' => $artifacts
  );
}

private function buildJobPromotionPlan($input) {
  $jobs = array();
  $jobNameMap = array();
  $seenSources = array();
  $seenTargets = array();
  $queue = array(array('source_job' => $input['source_job'], 'target_job' => $input['target_job']));
  $maxJobs = 50;

  while (! empty($queue)) {
    if (count($jobs) >= $maxJobs) {
      return array('ok' => FALSE, 'message' => 'Deployment stopped. Dependency graph is larger than '.$maxJobs.' jobs.');
    }

    $current = array_shift($queue);
    $sourceJob = $current['source_job'];
    $targetJob = $current['target_job'];

    if (isset($seenSources[$sourceJob])) {
      continue;
    }

    if (isset($seenTargets[$targetJob]) && $seenTargets[$targetJob] !== $sourceJob) {
      return array('ok' => FALSE, 'message' => 'Deployment stopped. Multiple source jobs map to target job '.$targetJob.'. Choose clearer environment naming or deploy the dependency separately.');
    }

    $seenSources[$sourceJob] = TRUE;
    $seenTargets[$targetJob] = $sourceJob;
    $jobNameMap[$sourceJob] = $targetJob;
    $jobs[] = array_merge($input, array('source_job' => $sourceJob, 'target_job' => $targetJob));

    if (empty($input['include_dependencies'])) {
      continue;
    }

    $sourceResponse = $this->requestJenkins('GET', $this->jenkinsJobPath($sourceJob) . '/config.xml');
    if ((int) $sourceResponse['status'] !== 200) {
      return array('ok' => FALSE, 'message' => 'Deployment failed. Dependency scan could not load '.$sourceJob.'. HTTP '.$sourceResponse['status'].'.');
    }

    foreach ($this->extractDownstreamJobNames($sourceResponse['body']) as $downstreamJob) {
      $clean = $this->cleanPromotionJobName($downstreamJob);
      if (! $clean['ok']) {
        return array('ok' => FALSE, 'message' => 'Deployment stopped. Downstream job name '.$downstreamJob.' is not safe to deploy.');
      }

      if (isset($seenSources[$clean['name']])) {
        continue;
      }

      $queue[] = array(
        'source_job' => $clean['name'],
        'target_job' => $this->suggestPromotedJobName($clean['name'], $input['source_environment']->Environment, $input['target_environment']->Environment)
      );
    }
  }

  foreach ($jobs as $index => $jobInput) {
    $jobs[$index]['job_name_map'] = $jobNameMap;
  }

  return array('ok' => TRUE, 'jobs' => $jobs, 'job_name_map' => $jobNameMap);
}

private function extractDownstreamJobNames($xml) {
  $dom = new DOMDocument();
  $previousErrors = libxml_use_internal_errors(TRUE);
  $loaded = $dom->loadXML($xml);
  libxml_clear_errors();
  libxml_use_internal_errors($previousErrors);

  if (! $loaded || ! $dom->documentElement) {
    return array();
  }

  $downstreamJobs = array();
  foreach ($dom->getElementsByTagName('childProjects') as $childProjectsNode) {
    foreach (explode(',', $childProjectsNode->nodeValue) as $jobName) {
      $jobName = trim($jobName);
      if ($jobName !== '' && ! in_array($jobName, $downstreamJobs, TRUE)) {
        $downstreamJobs[] = $jobName;
      }
    }
  }

  return $downstreamJobs;
}

private function prepareContextPromotionPlan($input) {
  if (empty($input['promote_contexts'])) {
    return array('ok' => TRUE, 'enabled' => FALSE, 'total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'snapshot' => array('total' => 0, 'rows' => array()), 'result' => array('total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0));
  }

  $snapshot = $this->model->snapshotContextsForPromotion($input['context_project_id'], (int) $input['source_environment']->Id, (int) $input['target_environment']->Id);
  $created = 0;
  $updated = 0;
  $skipped = 0;

  foreach ($snapshot['rows'] as $row) {
    if (!empty($row['existed'])) {
      if (!empty($input['overwrite_contexts'])) {
        $updated++;
      } else {
        $skipped++;
      }
    } else {
      $created++;
    }
  }

  return array(
    'ok' => TRUE,
    'enabled' => TRUE,
    'project_id' => $input['context_project_id'],
    'project_name' => !empty($input['context_project']) ? $input['context_project']->ProjectName : '',
    'total' => $snapshot['total'],
    'created' => $created,
    'updated' => $updated,
    'skipped' => $skipped,
    'snapshot' => $snapshot,
    'result' => array('total' => $snapshot['total'], 'created' => $created, 'updated' => $updated, 'skipped' => $skipped)
  );
}

private function savePreparedPromotionJob($prepared) {
  if ($prepared['target_exists']) {
    $saveResponse = $this->requestJenkins('POST', $this->jenkinsJobPath($prepared['target_job']) . '/config.xml', $prepared['xml'], 'text/xml');
  } else {
    $saveResponse = $this->requestJenkins('POST', $this->jenkinsCreateItemPath($prepared['target_job']), $prepared['xml'], 'text/xml');
  }

  return array('ok' => $this->isSuccessfulJenkinsStatus($saveResponse['status']), 'status' => $saveResponse['status']);
}

private function promotionResultPayload($ok, $message, $input, $preparedJobs, $totals, $artifacts, $commandPreviews, $contextPlan, $rollbackId) {
  $jobs = array();
  foreach ($preparedJobs as $prepared) {
    $jobs[] = array(
      'source_job' => $prepared['source_job'],
      'target_job' => $prepared['target_job'],
      'target_exists' => $prepared['target_exists'],
      'detected_environment' => isset($prepared['detected_environment']) ? $prepared['detected_environment'] : array('environment' => '', 'source' => 'Not detected'),
      'command_updates' => $prepared['command_updates'],
      'parameter_updates' => $prepared['parameter_updates'],
      'artifact_path_updates' => $prepared['artifact_path_updates'],
      'downstream_updates' => $prepared['downstream_updates'],
      'artifact_count' => count($prepared['artifacts']['planned'])
    );
  }

  return array(
    'ok' => $ok,
    'message' => $message,
    'target_exists' => !empty($preparedJobs) ? $preparedJobs[0]['target_exists'] : FALSE,
    'target_job' => $input['target_job'],
    'source_environment' => $input['source_environment']->Environment,
    'target_environment' => $input['target_environment']->Environment,
    'job_count' => count($preparedJobs),
    'dependency_count' => max(0, count($preparedJobs) - 1),
    'jobs' => $jobs,
    'command_updates' => $totals['command_updates'],
    'parameter_updates' => $totals['parameter_updates'],
    'artifact_path_updates' => $totals['artifact_path_updates'],
    'downstream_updates' => $totals['downstream_updates'],
    'command_previews' => $commandPreviews,
    'artifacts' => $artifacts,
    'context_promotion' => array(
      'enabled' => !empty($contextPlan['enabled']),
      'project_name' => isset($contextPlan['project_name']) ? $contextPlan['project_name'] : '',
      'total' => isset($contextPlan['total']) ? $contextPlan['total'] : 0,
      'created' => isset($contextPlan['created']) ? $contextPlan['created'] : 0,
      'updated' => isset($contextPlan['updated']) ? $contextPlan['updated'] : 0,
      'skipped' => isset($contextPlan['skipped']) ? $contextPlan['skipped'] : 0,
      'result' => isset($contextPlan['result']) ? $contextPlan['result'] : array('total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0)
    ),
    'rollback_enabled' => !empty($input['create_rollback']),
    'rollback_id' => $rollbackId
  );
}

private function createPromotionRollback($input, $preparedJobs, $contextPlan) {
  $root = $this->promotionRollbackRoot();
  if ($root === FALSE) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Rollback folder could not be prepared.');
  }

  $rollbackId = date('YmdHis').'-'.substr(sha1(uniqid('', TRUE)), 0, 10);
  $rollbackDirectory = $root.DIRECTORY_SEPARATOR.$rollbackId;
  if (! $this->ensureDirectory($rollbackDirectory)) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Rollback checkpoint folder could not be created.');
  }

  $bundle = array(
    'id' => $rollbackId,
    'created_at' => date('c'),
    'created_by' => $input['user'],
    'source_environment_id' => (int) $input['source_environment']->Id,
    'source_environment' => $input['source_environment']->Environment,
    'target_environment_id' => (int) $input['target_environment']->Id,
    'target_environment' => $input['target_environment']->Environment,
    'source_job' => $input['source_job'],
    'target_job' => $input['target_job'],
    'parameters' => array(
      'overwrite_existing' => !empty($input['overwrite']),
      'include_dependencies' => !empty($input['include_dependencies']),
      'promote_contexts' => !empty($input['promote_contexts']),
      'context_project_id' => isset($input['context_project_id']) ? (int) $input['context_project_id'] : 0,
      'context_project' => !empty($input['context_project']) && isset($input['context_project']->ProjectName) ? $input['context_project']->ProjectName : '',
      'overwrite_contexts' => !empty($input['overwrite_contexts'])
    ),
    'jobs' => array(),
    'artifacts' => array(),
    'contexts' => array('enabled' => !empty($contextPlan['enabled']), 'snapshot' => !empty($contextPlan['enabled']) ? $contextPlan['snapshot'] : array('total' => 0, 'rows' => array()))
  );

  foreach ($preparedJobs as $prepared) {
    $jobSnapshot = array(
      'source_job' => $prepared['source_job'],
      'target_job' => $prepared['target_job'],
      'existed' => $prepared['target_exists'],
      'command_updates' => $prepared['command_updates'],
      'parameter_updates' => $prepared['parameter_updates'],
      'artifact_path_updates' => $prepared['artifact_path_updates'],
      'downstream_updates' => $prepared['downstream_updates'],
      'config_xml' => ''
    );

    if ($prepared['target_exists']) {
      $configResponse = $this->requestJenkins('GET', $this->jenkinsJobPath($prepared['target_job']) . '/config.xml');
      if ((int) $configResponse['status'] !== 200) {
        return array('ok' => FALSE, 'message' => 'Deployment failed. Existing target job '.$prepared['target_job'].' could not be backed up. HTTP '.$configResponse['status'].'.');
      }
      $jobSnapshot['config_xml'] = $configResponse['body'];
    }

    $bundle['jobs'][] = $jobSnapshot;

    foreach ($prepared['artifacts']['planned'] as $artifact) {
      $targetPath = $this->promotionArtifactAbsolutePath($artifact['target']);
      if ($targetPath === FALSE) {
        return array('ok' => FALSE, 'message' => 'Deployment failed. Artifact rollback path is invalid for '.$artifact['target'].'.');
      }

      $artifactSnapshot = array('label' => $artifact['label'], 'source' => isset($artifact['source']) ? $artifact['source'] : '', 'target' => $artifact['target'], 'existed' => is_dir($targetPath), 'backup_path' => '');
      if ($artifactSnapshot['existed']) {
        $backupRelativePath = 'artifacts/'.count($bundle['artifacts']);
        $backupPath = $rollbackDirectory.DIRECTORY_SEPARATOR.$backupRelativePath;
        if (! $this->copyDirectoryTree($targetPath, $backupPath, TRUE)) {
          return array('ok' => FALSE, 'message' => 'Deployment failed. Artifact folder '.$artifact['target'].' could not be backed up for rollback.');
        }
        $artifactSnapshot['backup_path'] = $backupRelativePath;
      }

      $bundle['artifacts'][] = $artifactSnapshot;
    }
  }

  $filePath = $this->promotionRollbackFilePath($rollbackId);
  if ($filePath === FALSE || file_put_contents($filePath, json_encode($bundle, JSON_PRETTY_PRINT), LOCK_EX) === FALSE) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Rollback checkpoint could not be written.');
  }

  return array('ok' => TRUE, 'id' => $rollbackId);
}

private function performPromotionRollback($rollbackId) {
  $filePath = $this->promotionRollbackFilePath($rollbackId);
  if ($filePath === FALSE || ! is_readable($filePath)) {
    return array('ok' => FALSE, 'message' => 'Rollback checkpoint was not found.');
  }

  $bundle = json_decode(file_get_contents($filePath), TRUE);
  if (! is_array($bundle) || empty($bundle['id']) || empty($bundle['jobs']) || ! is_array($bundle['jobs'])) {
    return array('ok' => FALSE, 'message' => 'Rollback checkpoint is invalid.');
  }

  $errors = array();
  $restoredJobs = 0;
  $deletedJobs = 0;
  $restoredArtifacts = 0;
  $deletedArtifacts = 0;

  foreach (array_reverse($bundle['jobs']) as $jobSnapshot) {
    $targetJob = isset($jobSnapshot['target_job']) ? $jobSnapshot['target_job'] : '';
    if ($targetJob === '') {
      continue;
    }

    if (! empty($jobSnapshot['existed'])) {
      $response = $this->requestJenkins('POST', $this->jenkinsJobPath($targetJob) . '/config.xml', isset($jobSnapshot['config_xml']) ? $jobSnapshot['config_xml'] : '', 'text/xml');
      if (! $this->isSuccessfulJenkinsStatus($response['status'])) {
        $errors[] = 'Could not restore job '.$targetJob.' (HTTP '.$response['status'].').';
        continue;
      }
      $restoredJobs++;
    } else {
      $response = $this->requestJenkins('POST', $this->jenkinsJobPath($targetJob) . '/doDelete');
      if (! $this->isSuccessfulJenkinsStatus($response['status']) && (int) $response['status'] !== 404) {
        $errors[] = 'Could not delete deployed job '.$targetJob.' (HTTP '.$response['status'].').';
        continue;
      }
      $deletedJobs++;
    }
  }

  if (! empty($bundle['artifacts']) && is_array($bundle['artifacts'])) {
    foreach (array_reverse($bundle['artifacts']) as $artifactSnapshot) {
      $targetPath = $this->promotionArtifactAbsolutePath(isset($artifactSnapshot['target']) ? $artifactSnapshot['target'] : '');
      if ($targetPath === FALSE) {
        $errors[] = 'Could not resolve artifact path for rollback.';
        continue;
      }

      if (is_dir($targetPath) && ! $this->removeDirectoryTree($targetPath)) {
        $errors[] = 'Could not clear artifact folder '.$artifactSnapshot['target'].'.';
        continue;
      }

      if (! empty($artifactSnapshot['existed'])) {
        $backupPath = dirname($filePath).DIRECTORY_SEPARATOR.$bundle['id'].DIRECTORY_SEPARATOR.(isset($artifactSnapshot['backup_path']) ? $artifactSnapshot['backup_path'] : '');
        if (! is_dir($backupPath) || ! $this->copyDirectoryTree($backupPath, $targetPath)) {
          $errors[] = 'Could not restore artifact folder '.$artifactSnapshot['target'].'.';
          continue;
        }
        $restoredArtifacts++;
      } else {
        $deletedArtifacts++;
      }
    }
  }

  $contextResult = array('restored' => 0, 'deleted' => 0, 'skipped' => 0);
  if (! empty($bundle['contexts']['enabled']) && isset($bundle['contexts']['snapshot'])) {
    $contextResult = $this->model->rollbackContextsFromSnapshot($bundle['contexts']['snapshot']);
  }

  if (! empty($errors)) {
    return array('ok' => FALSE, 'message' => 'Rollback completed with errors: '.implode(' ', $errors));
  }

  $rollbackDirectory = dirname($filePath).DIRECTORY_SEPARATOR.$bundle['id'];
  if (is_dir($rollbackDirectory)) {
    $this->removeDirectoryTree($rollbackDirectory);
  }
  @rename($filePath, $filePath.'.rolledback.'.date('YmdHis'));
  return array('ok' => TRUE, 'message' => 'Rollback completed. Jobs restored: '.$restoredJobs.', jobs deleted: '.$deletedJobs.', artifact folders restored: '.$restoredArtifacts.', artifact folders deleted: '.$deletedArtifacts.', contexts restored: '.$contextResult['restored'].', contexts deleted: '.$contextResult['deleted'].'.');
}

private function recordPromotionHistory($input, $result) {
  $root = $this->promotionHistoryRoot();
  if ($root === FALSE) {
    log_message('error', 'Promotion history folder could not be prepared.');
    return FALSE;
  }

  $historyId = date('YmdHis').'-'.substr(sha1(uniqid('', TRUE)), 0, 10);
  $rollbackId = isset($result['rollback_id']) ? (string) $result['rollback_id'] : '';
  $status = !empty($result['ok']) ? 'completed' : (!empty($result['rolled_back']) ? 'rolled_back' : 'failed');
  $record = array(
    'id' => $historyId,
    'created_at' => date('c'),
    'created_by' => isset($input['user']) ? $input['user'] : '',
    'status' => $status,
    'message' => isset($result['message']) ? $result['message'] : '',
    'source_environment' => isset($input['source_environment']->Environment) ? $input['source_environment']->Environment : '',
    'target_environment' => isset($input['target_environment']->Environment) ? $input['target_environment']->Environment : '',
    'source_job' => isset($input['source_job']) ? $input['source_job'] : '',
    'target_job' => isset($input['target_job']) ? $input['target_job'] : '',
    'rollback_id' => $rollbackId,
    'rollback_at' => !empty($result['rolled_back']) ? date('c') : '',
    'rollback_by' => !empty($result['rolled_back']) && isset($this->global['name']) ? $this->global['name'] : '',
    'rollback_message' => !empty($result['rolled_back']) && isset($result['message']) ? $result['message'] : '',
    'parameters' => array(
      'overwrite_existing' => !empty($input['overwrite']),
      'include_dependencies' => !empty($input['include_dependencies']),
      'promote_contexts' => !empty($input['promote_contexts']),
      'context_project' => !empty($input['context_project']) && isset($input['context_project']->ProjectName) ? $input['context_project']->ProjectName : '',
      'overwrite_contexts' => !empty($input['overwrite_contexts']),
      'create_rollback' => !empty($input['create_rollback'])
    ),
    'metrics' => array(
      'job_count' => isset($result['job_count']) ? (int) $result['job_count'] : 0,
      'dependency_count' => isset($result['dependency_count']) ? (int) $result['dependency_count'] : 0,
      'command_updates' => isset($result['command_updates']) ? (int) $result['command_updates'] : 0,
      'parameter_updates' => isset($result['parameter_updates']) ? (int) $result['parameter_updates'] : 0,
      'artifact_path_updates' => isset($result['artifact_path_updates']) ? (int) $result['artifact_path_updates'] : 0,
      'downstream_updates' => isset($result['downstream_updates']) ? (int) $result['downstream_updates'] : 0
    ),
    'jobs' => isset($result['jobs']) && is_array($result['jobs']) ? $result['jobs'] : array(),
    'artifacts' => $this->promotionHistoryArtifactSummary(isset($result['artifacts']) ? $result['artifacts'] : array()),
    'contexts' => isset($result['context_promotion']) && is_array($result['context_promotion']) ? $result['context_promotion'] : array()
  );

  return file_put_contents($root.DIRECTORY_SEPARATOR.$historyId.'.json', json_encode($record, JSON_PRETTY_PRINT), LOCK_EX) !== FALSE;
}

private function promotionHistoryArtifactSummary($artifacts) {
  $summary = array('planned' => array(), 'copied' => array(), 'skipped' => array(), 'errors' => array());
  foreach ($summary as $group => $items) {
    if (empty($artifacts[$group]) || ! is_array($artifacts[$group])) {
      continue;
    }

    foreach ($artifacts[$group] as $artifact) {
      if (is_array($artifact)) {
        $summary[$group][] = array(
          'label' => isset($artifact['label']) ? $artifact['label'] : '',
          'source' => isset($artifact['source']) ? $artifact['source'] : '',
          'target' => isset($artifact['target']) ? $artifact['target'] : '',
          'reason' => isset($artifact['reason']) ? $artifact['reason'] : ''
        );
      } else {
        $summary[$group][] = (string) $artifact;
      }
    }
  }
  return $summary;
}

private function listPromotionHistory() {
  $records = array();
  $rollbackIds = array();
  $root = $this->promotionHistoryRoot();

  if ($root !== FALSE) {
    foreach ((array) glob($root.DIRECTORY_SEPARATOR.'*.json') as $filePath) {
      $record = json_decode(file_get_contents($filePath), TRUE);
      if (! is_array($record) || empty($record['id'])) {
        continue;
      }
      $record['rollback_available'] = !empty($record['rollback_id']) && is_readable($this->promotionRollbackFilePath($record['rollback_id']));
      $records[] = $record;
      if (!empty($record['rollback_id'])) {
        $rollbackIds[$record['rollback_id']] = TRUE;
      }
    }
  }

  $rollbackRoot = $this->promotionRollbackRoot();
  if ($rollbackRoot !== FALSE) {
    foreach ((array) glob($rollbackRoot.DIRECTORY_SEPARATOR.'*.json*') as $filePath) {
      $bundle = json_decode(file_get_contents($filePath), TRUE);
      if (! is_array($bundle) || empty($bundle['id']) || isset($rollbackIds[$bundle['id']])) {
        continue;
      }
      $jobs = array();
      foreach (isset($bundle['jobs']) && is_array($bundle['jobs']) ? $bundle['jobs'] : array() as $job) {
        $jobs[] = array('source_job' => isset($job['source_job']) ? $job['source_job'] : '', 'target_job' => isset($job['target_job']) ? $job['target_job'] : '');
      }
      $rolledBack = strpos(basename($filePath), '.rolledback.') !== FALSE;
      $records[] = array(
        'id' => 'checkpoint-'.$bundle['id'],
        'created_at' => isset($bundle['created_at']) ? $bundle['created_at'] : '',
        'created_by' => isset($bundle['created_by']) ? $bundle['created_by'] : '',
        'status' => $rolledBack ? 'rolled_back' : 'checkpoint',
        'message' => 'Imported from an existing rollback checkpoint.',
        'source_environment' => isset($bundle['source_environment']) ? $bundle['source_environment'] : '',
        'target_environment' => isset($bundle['target_environment']) ? $bundle['target_environment'] : '',
        'source_job' => isset($bundle['source_job']) ? $bundle['source_job'] : '',
        'target_job' => isset($bundle['target_job']) ? $bundle['target_job'] : (!empty($jobs[0]['target_job']) ? $jobs[0]['target_job'] : ''),
        'rollback_id' => $bundle['id'],
        'rollback_available' => !$rolledBack && substr($filePath, -5) === '.json',
        'parameters' => isset($bundle['parameters']) ? $bundle['parameters'] : array(),
        'metrics' => array('job_count' => count($jobs), 'dependency_count' => max(0, count($jobs) - 1)),
        'jobs' => $jobs,
        'artifacts' => array('planned' => isset($bundle['artifacts']) ? $bundle['artifacts'] : array(), 'copied' => array(), 'skipped' => array(), 'errors' => array()),
        'contexts' => isset($bundle['contexts']) ? array('enabled' => !empty($bundle['contexts']['enabled']), 'total' => isset($bundle['contexts']['snapshot']['total']) ? (int) $bundle['contexts']['snapshot']['total'] : 0) : array()
      );
    }
  }

  usort($records, function($left, $right) {
    return strcmp(isset($right['created_at']) ? $right['created_at'] : '', isset($left['created_at']) ? $left['created_at'] : '');
  });
  return array_slice($records, 0, 100);
}

private function updatePromotionHistoryRollback($rollbackId, $result) {
  $root = $this->promotionHistoryRoot();
  if ($root === FALSE || $rollbackId === '') {
    return;
  }

  foreach ((array) glob($root.DIRECTORY_SEPARATOR.'*.json') as $filePath) {
    $record = json_decode(file_get_contents($filePath), TRUE);
    if (! is_array($record) || !isset($record['rollback_id']) || $record['rollback_id'] !== $rollbackId) {
      continue;
    }
    $record['status'] = !empty($result['ok']) ? 'rolled_back' : 'rollback_failed';
    $record['rollback_at'] = date('c');
    $record['rollback_by'] = isset($this->global['name']) ? $this->global['name'] : '';
    $record['rollback_message'] = isset($result['message']) ? $result['message'] : '';
    file_put_contents($filePath, json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);
  }
}

private function promotionHistoryRoot() {
  $root = APPPATH.'cache'.DIRECTORY_SEPARATOR.'promotion_history';
  return $this->ensureDirectory($root) ? $root : FALSE;
}

private function promotionRollbackRoot() {
  $root = APPPATH.'cache'.DIRECTORY_SEPARATOR.'promotion_rollbacks';

  if (! $this->ensureDirectory($root)) {
    return FALSE;
  }

  return $root;
}

private function promotionRollbackFilePath($rollbackId) {
  $rollbackId = trim((string) $rollbackId);
  if ($rollbackId === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $rollbackId)) {
    return FALSE;
  }

  $root = $this->promotionRollbackRoot();
  if ($root === FALSE) {
    return FALSE;
  }

  return $root.DIRECTORY_SEPARATOR.$rollbackId.'.json';
}

private function promotionArtifactAbsolutePath($relativePath) {
  $repositoryRoot = realpath($this->promotionRepositoryRoot());
  if ($repositoryRoot === FALSE) {
    return FALSE;
  }

  $safeRelativePath = $this->safeRelativePath($relativePath);
  if ($safeRelativePath === FALSE) {
    return FALSE;
  }

  $path = $repositoryRoot.DIRECTORY_SEPARATOR.$safeRelativePath;
  return $this->pathWithinBase($path, $repositoryRoot) ? $path : FALSE;
}

private function transformPromotedJenkinsConfig($xml, $sourceEnvironment, $targetEnvironment, $sourceJobName, $targetJobName, $jobNameMap = array()) {
  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = FALSE;
  $dom->formatOutput = TRUE;
  $previousErrors = libxml_use_internal_errors(TRUE);
  $loaded = $dom->loadXML($xml);
  libxml_clear_errors();
  libxml_use_internal_errors($previousErrors);

  if (! $loaded || ! $dom->documentElement) {
    return array('ok' => FALSE, 'message' => 'Deployment failed. Source Jenkins config.xml is not valid XML.');
  }

  $xpath = new DOMXPath($dom);
  $commandNodes = $xpath->query('//hudson.tasks.Shell/command | //hudson.tasks.BatchFile/command');
  $commandUpdates = 0;
  $artifactPathUpdates = 0;
  $commandPreviews = array();

  foreach ($commandNodes as $commandNode) {
    $before = $commandNode->nodeValue;
    $rewrite = $this->rewritePromotionCommand($before, $sourceEnvironment, $targetEnvironment, $sourceJobName, $targetJobName);

    if ($rewrite['text'] !== $before) {
      while ($commandNode->firstChild) {
        $commandNode->removeChild($commandNode->firstChild);
      }

      $commandNode->appendChild($dom->createTextNode($rewrite['text']));
      $commandUpdates += $rewrite['environment_updates'];
      $artifactPathUpdates += $rewrite['artifact_path_updates'];

      if (count($commandPreviews) < 3) {
        $commandPreviews[] = array(
          'builder' => $commandNode->parentNode ? $commandNode->parentNode->nodeName : 'command',
          'before' => $this->shortPromotionCommandPreview($before),
          'after' => $this->shortPromotionCommandPreview($rewrite['text'])
        );
      }
    }
  }

  $parameterUpdates = $this->rewriteEnvironmentParameterDefaults($dom, $sourceEnvironment, $targetEnvironment);
  $downstreamUpdates = $this->rewritePromotionDownstreamJobs($dom, $sourceEnvironment, $targetEnvironment, $jobNameMap);
  $agentAssignmentUpdates = $this->rewritePromotionAgentAssignment($dom, $sourceEnvironment, $targetEnvironment);

  return array(
    'ok' => TRUE,
    'xml' => $dom->saveXML(),
    'command_updates' => $commandUpdates,
    'parameter_updates' => $parameterUpdates,
    'artifact_path_updates' => $artifactPathUpdates,
    'downstream_updates' => $downstreamUpdates,
    'agent_assignment_updates' => $agentAssignmentUpdates,
    'command_previews' => $commandPreviews
  );
}

private function detectPromotionEnvironment($xml, $fallbackJobName) {
  $detected = array('environment' => '', 'source' => 'Not detected');
  $dom = new DOMDocument();
  $previousErrors = libxml_use_internal_errors(TRUE);
  $loaded = $dom->loadXML($xml);
  libxml_clear_errors();
  libxml_use_internal_errors($previousErrors);

  if ($loaded && $dom->documentElement) {
    $parameterEnvironment = $this->detectPromotionEnvironmentFromParameters($dom);
    if ($parameterEnvironment !== '') {
      return array('environment' => $parameterEnvironment, 'source' => 'Jenkins parameter');
    }

    foreach ($dom->getElementsByTagName('command') as $commandNode) {
      $commandEnvironment = $this->detectPromotionEnvironmentFromCommand($commandNode->nodeValue);
      if ($commandEnvironment !== '') {
        return array('environment' => $commandEnvironment, 'source' => 'Jenkins command');
      }
    }
  }

  $nameEnvironment = $this->detectPromotionEnvironmentFromName($fallbackJobName);
  if ($nameEnvironment !== '') {
    return array('environment' => $nameEnvironment, 'source' => 'Job name');
  }

  return $detected;
}

private function detectPromotionEnvironmentFromParameters($dom) {
  $parameterNames = array('environment', 'env', 'context', 'job_context', 'jobseeker_environment', 'target_environment', 'context_environment');

  foreach ($dom->getElementsByTagName('*') as $element) {
    if (substr($element->tagName, -19) !== 'ParameterDefinition') {
      continue;
    }

    $nameNode = $this->directChildElement($element, 'name');
    if (! $nameNode) {
      continue;
    }

    if (! in_array(strtolower(trim($nameNode->nodeValue)), $parameterNames, TRUE)) {
      continue;
    }

    $defaultNode = $this->directChildElement($element, 'defaultValue');
    if (! $defaultNode) {
      $defaultNode = $this->directChildElement($element, 'value');
    }

    if ($defaultNode) {
      $environment = $this->normalizePromotionEnvironmentName($defaultNode->nodeValue);
      if ($environment !== '') {
        return $environment;
      }
    }
  }

  return '';
}

private function detectPromotionEnvironmentFromCommand($text) {
  $valuePattern = '(?:"([^"\r\n]+)"|\'([^\'\r\n]+)\'|([^\s;]+))';
  $patterns = array(
    '/(?:^|[\s"\'])--context["\']?(?:=|\s+)'.$valuePattern.'/i',
    '/(?:^|[\s"\'])-context["\']?\s+'.$valuePattern.'/i',
    '/(?:^|[\s"\'])--environment["\']?(?:=|\s+)'.$valuePattern.'/i',
    '/(?:^|[\s;])(?:export\s+)?(?:ENVIRONMENT|JOBSEEKER_ENVIRONMENT|JOB_CONTEXT|CONTEXT)\s*=\s*'.$valuePattern.'/i'
  );

  foreach ($patterns as $pattern) {
    if (preg_match($pattern, (string) $text, $matches)) {
      for ($index = 1; $index < count($matches); $index++) {
        $environment = $this->normalizePromotionEnvironmentName($matches[$index]);
        if ($environment !== '') {
          return $environment;
        }
      }
    }
  }

  foreach (preg_split('/(\r\n|\n|\r)/', (string) $text) as $line) {
    if (! $this->looksLikePythonExecutionLine($line)) {
      continue;
    }

    $environment = $this->detectPromotionEnvironmentFromName($line);
    if ($environment !== '') {
      return $environment;
    }
  }

  return '';
}

private function detectPromotionEnvironmentFromName($text) {
  $knownEnvironments = array('LOCAL', 'DEV', 'QA', 'QAS', 'UAT', 'PREPROD', 'PROD', 'STAGE', 'STAGING', 'TEST', 'HML');
  $source = strtoupper((string) $text);

  foreach ($knownEnvironments as $environment) {
    if (preg_match('/(^|[^A-Z0-9])'.preg_quote($environment, '/').'($|[^A-Z0-9])/', $source)) {
      return $this->normalizePromotionEnvironmentName($environment);
    }
  }

  return '';
}

private function normalizePromotionEnvironmentName($environment) {
  $environment = strtoupper(trim((string) $environment, " \t\n\r\0\x0B'\"`,;"));
  $aliases = array(
    'HOMOLOG' => 'HML',
    'HOMOLOGATION' => 'HML',
    'QAS' => 'QA',
    'PRD' => 'PROD',
    'PRODUCTION' => 'PROD'
  );

  return isset($aliases[$environment]) ? $aliases[$environment] : $environment;
}

private function promotionEnvironmentsEquivalent($left, $right) {
  return $this->normalizePromotionEnvironmentName($left) === $this->normalizePromotionEnvironmentName($right);
}

private function rewritePromotionCommand($commandText, $sourceEnvironment, $targetEnvironment, $sourceJobName, $targetJobName) {
  $updated = (string) $commandText;
  $environmentUpdates = 0;
  $artifactPathUpdates = 0;

  $updated = $this->rewriteContextArguments($updated, $sourceEnvironment, $targetEnvironment, $environmentUpdates);
  $updated = $this->rewriteEnvironmentAssignments($updated, $sourceEnvironment, $targetEnvironment, $environmentUpdates);
  $updated = $this->rewritePythonEnvironmentArguments($updated, $sourceEnvironment, $targetEnvironment, $environmentUpdates);
  $updated = $this->rewritePromotedArtifactPaths($updated, $sourceJobName, $targetJobName, $artifactPathUpdates);
  $updated = $this->normalizePromotedInlinePythonDockerCommand($updated);

  return array('text' => $updated, 'environment_updates' => $environmentUpdates, 'artifact_path_updates' => $artifactPathUpdates);
}

private function normalizePromotedInlinePythonDockerCommand($commandText) {
  if (strpos($commandText, 'JOBSEEKER_DOCKER_CONTEXT/source') === FALSE) {
    return $commandText;
  }

  $legacyRequirements = 'if [ -n "${JOBSEEKER_PYTHON_REQUIREMENTS_B64:-}" ]; then mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR"; printf "%s" "$JOBSEEKER_PYTHON_REQUIREMENTS_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt"; fi';
  $fallbackRequirements = 'if [ -n "${JOBSEEKER_PYTHON_REQUIREMENTS_B64:-}" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/requirements.txt" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/pyproject.toml" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/pyproject.toml" ]; then mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR"; printf "%s" "$JOBSEEKER_PYTHON_REQUIREMENTS_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt"; fi';
  $legacyPyproject = array(
    'if [ -n "${JOBSEEKER_PYPROJECT_B64:-}" ]; then printf "%s" "$JOBSEEKER_PYPROJECT_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/pyproject.toml"; rm -f "$JOBSEEKER_DOCKER_CONTEXT/source/requirements.txt" "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt"; fi',
    'if [ -n "${JOBSEEKER_PYPROJECT_B64:-}" ]; then printf "%s" "$JOBSEEKER_PYPROJECT_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/pyproject.toml"; rm -f "$JOBSEEKER_DOCKER_CONTEXT/source/requirements.txt" "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt" "$JOBSEEKER_DOCKER_CONTEXT/source/poetry.lock" "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/poetry.lock"; fi'
  );
  $fallbackPyproject = 'if [ -n "${JOBSEEKER_PYPROJECT_B64:-}" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/pyproject.toml" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/pyproject.toml" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/requirements.txt" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt" ]; then printf "%s" "$JOBSEEKER_PYPROJECT_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/pyproject.toml"; fi';
  $legacyDockerfile = 'if [ -n "${JOBSEEKER_PYTHON_DOCKERFILE_B64:-}" ]; then printf "%s" "$JOBSEEKER_PYTHON_DOCKERFILE_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile"; fi';
  $fallbackDockerfile = 'if [ -n "${JOBSEEKER_PYTHON_DOCKERFILE_B64:-}" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/Dockerfile" ]; then printf "%s" "$JOBSEEKER_PYTHON_DOCKERFILE_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile"; fi';

  $updated = str_replace($legacyRequirements, $fallbackRequirements, $commandText);
  $updated = str_replace($legacyPyproject, $fallbackPyproject, $updated);
  return str_replace($legacyDockerfile, $fallbackDockerfile, $updated);
}

private function rewriteContextArguments($text, $sourceEnvironment, $targetEnvironment, &$updateCount) {
  $assignmentPattern = '/(?<![A-Za-z0-9_-])(["\']?)(--?context)(\s*=\s*)'.preg_quote($sourceEnvironment, '/').'\1(?![A-Za-z0-9_.-])/';
  $separateArgumentPattern = '/(?<![A-Za-z0-9_-])(["\']?)(--?context)\1(\s+)(["\']?)'.preg_quote($sourceEnvironment, '/').'\4(?![A-Za-z0-9_.-])/';

  $text = preg_replace_callback($assignmentPattern, function($matches) use ($targetEnvironment, &$updateCount) {
    $updateCount++;
    return $matches[1].$matches[2].$matches[3].$targetEnvironment.$matches[1];
  }, $text);

  return preg_replace_callback($separateArgumentPattern, function($matches) use ($targetEnvironment, &$updateCount) {
    $updateCount++;
    return $matches[1].$matches[2].$matches[1].$matches[3].$matches[4].$targetEnvironment.$matches[4];
  }, $text);
}

private function rewriteEnvironmentAssignments($text, $sourceEnvironment, $targetEnvironment, &$updateCount) {
  $names = 'JOBSEEKER_ENVIRONMENT|JOBSEEKER_CONTEXT|CONTEXT_ENVIRONMENT|TARGET_ENVIRONMENT|ENVIRONMENT|CONTEXT';
  $pattern = '/^(\s*(?:export\s+)?(?:'.$names.')\s*=\s*)(["\']?)'.preg_quote($sourceEnvironment, '/').'\2(\s*)$/m';

  return preg_replace_callback($pattern, function($matches) use ($targetEnvironment, &$updateCount) {
    $updateCount++;
    return $matches[1].$matches[2].$targetEnvironment.$matches[2].$matches[3];
  }, $text);
}

private function rewritePythonEnvironmentArguments($text, $sourceEnvironment, $targetEnvironment, &$updateCount) {
  $parts = preg_split('/(\r\n|\n|\r)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
  $pattern = '/(^|\s)(["\']?)'.preg_quote($sourceEnvironment, '/').'\2(\s*)$/';

  for ($index = 0; $index < count($parts); $index += 2) {
    $line = $parts[$index];

    if (! $this->looksLikePythonExecutionLine($line)) {
      continue;
    }

    $parts[$index] = preg_replace_callback($pattern, function($matches) use ($targetEnvironment, &$updateCount) {
      $updateCount++;
      return $matches[1].$matches[2].$targetEnvironment.$matches[2].$matches[3];
    }, $line, 1);
  }

  return implode('', $parts);
}

private function looksLikePythonExecutionLine($line) {
  $trimmed = ltrim((string) $line);

  if (stripos($trimmed, 'export ') === 0) {
    return FALSE;
  }

  return strpos($line, 'JOBSEEKER_PYTHON') !== FALSE
    || strpos($line, 'JOBSEEKER_ENTRYPOINT') !== FALSE
    || preg_match('/(^|\s)python[0-9.]*\s/i', $line)
    || preg_match('/\.py(["\']?)(\s|$)/i', $line);
}

private function rewritePromotedArtifactPaths($text, $sourceJobName, $targetJobName, &$updateCount) {
  if ($sourceJobName === $targetJobName) {
    return $text;
  }

  $sourceJobPath = trim(str_replace('\\', '/', $sourceJobName), '/');
  $targetJobPath = trim(str_replace('\\', '/', $targetJobName), '/');
  $pathPairs = array(
    array('repository/talend/jobs/'.$sourceJobPath, 'repository/talend/jobs/'.$targetJobPath),
    array('repository/bash/jobs/'.$sourceJobPath, 'repository/bash/jobs/'.$targetJobPath),
    array('repository/batch/jobs/'.$sourceJobPath, 'repository/batch/jobs/'.$targetJobPath),
    array('repository/python/jobs/'.$sourceJobPath, 'repository/python/jobs/'.$targetJobPath),
    array('repository/python/inline/'.$sourceJobPath, 'repository/python/inline/'.$targetJobPath)
  );

  foreach ($pathPairs as $pathPair) {
    $matches = substr_count($text, $pathPair[0]);
    if ($matches > 0) {
      $text = str_replace($pathPair[0], $pathPair[1], $text);
      $updateCount += $matches;
    }
  }

  return $text;
}

private function rewriteEnvironmentParameterDefaults($dom, $sourceEnvironment, $targetEnvironment) {
  $updates = 0;
  $parameterNames = array('environment', 'env', 'context', 'job_environment', 'target_environment', 'context_environment');

  foreach ($dom->getElementsByTagName('defaultValue') as $defaultValueNode) {
    $parent = $defaultValueNode->parentNode;
    $nameNode = $this->directChildElement($parent, 'name');

    if (! $nameNode) {
      continue;
    }

    $parameterName = strtolower(trim($nameNode->nodeValue));
    if (! in_array($parameterName, $parameterNames, TRUE)) {
      continue;
    }

    if (trim($defaultValueNode->nodeValue) === $sourceEnvironment) {
      while ($defaultValueNode->firstChild) {
        $defaultValueNode->removeChild($defaultValueNode->firstChild);
      }
      $defaultValueNode->appendChild($dom->createTextNode($targetEnvironment));
      $updates++;
    }
  }

  return $updates;
}

private function rewritePromotionDownstreamJobs($dom, $sourceEnvironment, $targetEnvironment, $jobNameMap = array()) {
  $updates = 0;

  foreach ($dom->getElementsByTagName('childProjects') as $childProjectsNode) {
    $original = $childProjectsNode->nodeValue;
    $promotedProjects = array();

    foreach (explode(',', $original) as $jobName) {
      $trimmedJobName = trim($jobName);
      if ($trimmedJobName === '') {
        continue;
      }

      $promotedJobName = isset($jobNameMap[$trimmedJobName]) ? $jobNameMap[$trimmedJobName] : $this->replaceEnvironmentTokenInJobName($trimmedJobName, $sourceEnvironment, $targetEnvironment);
      if ($promotedJobName !== $trimmedJobName) {
        $updates++;
      }
      $promotedProjects[] = $promotedJobName;
    }

    $promotedValue = implode(', ', $promotedProjects);
    if ($promotedValue !== $original) {
      while ($childProjectsNode->firstChild) {
        $childProjectsNode->removeChild($childProjectsNode->firstChild);
      }
      $childProjectsNode->appendChild($dom->createTextNode($promotedValue));
    }
  }

  return $updates;
}

private function rewritePromotionAgentAssignment($dom, $sourceEnvironment, $targetEnvironment) {
  if (! $dom->documentElement) {
    return 0;
  }

  $targetCapacity = $this->jenkinsOnlineEnvironmentAgentCapacity($targetEnvironment);
  $targetAgentLabel = $targetCapacity['label'];
  $agentLabel = (int) $targetCapacity['executors'] > 0 ? $targetAgentLabel : '';
  $sourceAgentLabel = $this->jenkinsEnvironmentAgentLabel($sourceEnvironment);
  $updates = 0;
  $root = $dom->documentElement;
  $assignedNode = $this->directChildElement($root, 'assignedNode');
  $canRoam = $this->directChildElement($root, 'canRoam');

  if ($agentLabel === '') {
    // No online target agent: make the job plainly controller-routable and
    // never leave it canRoam=false with an empty assignedNode (that ties a
    // freestyle job to the "built-in" node and yields the confusing
    // "doesn't have label 'built-in'" queue reason).
    $currentLabel = $assignedNode ? trim((string) $assignedNode->nodeValue) : '';
    $isJobSeekerPin = $currentLabel !== '' && (
      in_array($currentLabel, array($sourceAgentLabel, $targetAgentLabel), TRUE)
      || preg_match('/^jobseeker-env-[a-z0-9_-]+$/i', $currentLabel)
    );
    if ($assignedNode && ($currentLabel === '' || $isJobSeekerPin)) {
      while ($assignedNode->firstChild) {
        $assignedNode->removeChild($assignedNode->firstChild);
      }
      $currentLabel = '';
      $updates++;
    }

    if ($currentLabel === '' && $canRoam && $canRoam->nodeValue !== 'true') {
      while ($canRoam->firstChild) {
        $canRoam->removeChild($canRoam->firstChild);
      }
      $canRoam->appendChild($dom->createTextNode('true'));
      $updates++;
    }
    return $updates;
  }

  if (! $assignedNode) {
    $assignedNode = $dom->createElement('assignedNode');
    $root->appendChild($assignedNode);
    $updates++;
  }

  if ($assignedNode->nodeValue !== $agentLabel) {
    while ($assignedNode->firstChild) {
      $assignedNode->removeChild($assignedNode->firstChild);
    }
    $assignedNode->appendChild($dom->createTextNode($agentLabel));
    $updates++;
  }

  if (! $canRoam) {
    $canRoam = $dom->createElement('canRoam');
    $root->appendChild($canRoam);
    $updates++;
  }

  if ($canRoam->nodeValue !== 'false') {
    while ($canRoam->firstChild) {
      $canRoam->removeChild($canRoam->firstChild);
    }
    $canRoam->appendChild($dom->createTextNode('false'));
    $updates++;
  }

  return $updates;
}

private function directChildElement($parent, $tagName) {
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

private function copyPromotionArtifacts($sourceJobName, $targetJobName, $overwrite, $previewOnly) {
  $result = array('copied' => array(), 'planned' => array(), 'skipped' => array(), 'errors' => array());

  if ($sourceJobName === $targetJobName) {
    return $result;
  }

  $repositoryRoot = $this->promotionRepositoryRoot();
  $realRepositoryRoot = realpath($repositoryRoot);
  if ($realRepositoryRoot === FALSE || ! is_dir($realRepositoryRoot)) {
    $result['skipped'][] = 'Repository folder was not found on this server.';
    return $result;
  }

  $locations = array(
    array('label' => 'Talend package', 'relative' => 'talend/jobs/'.$sourceJobName, 'target' => 'talend/jobs/'.$targetJobName),
    array('label' => 'Bash package', 'relative' => 'bash/jobs/'.$sourceJobName, 'target' => 'bash/jobs/'.$targetJobName),
    array('label' => 'Batch package', 'relative' => 'batch/jobs/'.$sourceJobName, 'target' => 'batch/jobs/'.$targetJobName),
    array('label' => 'Python package', 'relative' => 'python/jobs/'.$sourceJobName, 'target' => 'python/jobs/'.$targetJobName),
    array('label' => 'Inline Python workspace', 'relative' => 'python/inline/'.$sourceJobName, 'target' => 'python/inline/'.$targetJobName)
  );

  $candidates = array();

  foreach ($locations as $location) {
    $sourceRelative = $this->safeRelativePath($location['relative']);
    $targetRelative = $this->safeRelativePath($location['target']);

    if ($sourceRelative === FALSE || $targetRelative === FALSE) {
      $result['errors'][] = $location['label'].' uses an unsafe repository path.';
      continue;
    }

    $sourcePath = $realRepositoryRoot.DIRECTORY_SEPARATOR.$sourceRelative;
    $targetPath = $realRepositoryRoot.DIRECTORY_SEPARATOR.$targetRelative;

    if (! $this->pathWithinBase($sourcePath, $realRepositoryRoot) || ! $this->pathWithinBase($targetPath, $realRepositoryRoot)) {
      $result['errors'][] = $location['label'].' resolved outside the repository folder.';
      continue;
    }

    if (! is_dir($sourcePath)) {
      continue;
    }

    if (is_link($sourcePath)) {
      $result['errors'][] = $location['label'].' source folder cannot be a symbolic link.';
      continue;
    }

    $realSourcePath = realpath($sourcePath);
    if ($realSourcePath === FALSE || ! $this->pathWithinBase($realSourcePath, $realRepositoryRoot)) {
      $result['errors'][] = $location['label'].' source folder resolved outside the repository folder.';
      continue;
    }

    $resolvedTargetPath = $this->promotionPathWithResolvedAncestor($targetPath);
    if ($resolvedTargetPath === FALSE || ! $this->pathWithinBase($resolvedTargetPath, $realRepositoryRoot)) {
      $result['errors'][] = $location['label'].' target folder resolved outside the repository folder.';
      continue;
    }

    if ($this->promotionPathsOverlap($sourcePath, $targetPath) || $this->promotionPathsOverlap($realSourcePath, $resolvedTargetPath)) {
      $result['errors'][] = $location['label'].' source and target folders cannot contain one another.';
      continue;
    }

    if (is_link($targetPath)) {
      $result['errors'][] = $location['label'].' target folder cannot be a symbolic link.';
      continue;
    }

    if (file_exists($targetPath) && ! is_dir($targetPath)) {
      $result['errors'][] = $location['label'].' target path exists and is not a folder.';
      continue;
    }

    if (is_dir($targetPath)) {
      $realTargetPath = realpath($targetPath);
      if ($realTargetPath === FALSE || ! $this->pathWithinBase($realTargetPath, $realRepositoryRoot)) {
        $result['errors'][] = $location['label'].' target folder resolved outside the repository folder.';
        continue;
      }

      if ($this->promotionPathsOverlap($realSourcePath, $realTargetPath)) {
        $result['errors'][] = $location['label'].' source and target folders cannot contain one another.';
        continue;
      }
    }

    $targetPath = $resolvedTargetPath;

    $entry = array('label' => $location['label'], 'source' => $location['relative'], 'target' => $location['target']);

    if (is_dir($targetPath) && ! $overwrite) {
      $result['errors'][] = $location['label'].' already exists for the target job. Enable overwrite to replace it.';
      continue;
    }

    if ($previewOnly) {
      $result['planned'][] = $entry;
      continue;
    }

    $candidates[] = array(
      'entry' => $entry,
      'source_path' => $realSourcePath,
      'target_path' => $targetPath,
      'target_existed' => is_dir($targetPath)
    );
  }

  if ($previewOnly || ! empty($result['errors']) || empty($candidates)) {
    return $result;
  }

  $transactionToken = date('YmdHis').'-'.substr(sha1(uniqid('', TRUE)), 0, 12);
  $prepared = array();

  foreach ($candidates as $index => $candidate) {
    $targetParent = dirname($candidate['target_path']);
    $targetBaseName = basename($candidate['target_path']);
    $stagePath = $targetParent.DIRECTORY_SEPARATOR.'.'.$targetBaseName.'.promotion-stage-'.$transactionToken.'-'.$index;
    $backupPath = $targetParent.DIRECTORY_SEPARATOR.'.'.$targetBaseName.'.promotion-backup-'.$transactionToken.'-'.$index;

    $preparedItem = $candidate;
    $preparedItem['stage_path'] = $stagePath;
    $preparedItem['backup_path'] = $backupPath;
    $preparedItem['target_moved'] = FALSE;
    $preparedItem['promoted'] = FALSE;
    $prepared[] = $preparedItem;
    $preparedIndex = count($prepared) - 1;

    if (! $this->pathWithinBase($stagePath, $realRepositoryRoot) || ! $this->pathWithinBase($backupPath, $realRepositoryRoot) || file_exists($stagePath) || is_link($stagePath) || file_exists($backupPath) || is_link($backupPath)) {
      $result['errors'][] = $candidate['entry']['label'].' could not reserve safe temporary promotion folders.';
      break;
    }

    if (! $this->copyDirectoryTree($candidate['source_path'], $stagePath, TRUE, TRUE)) {
      $result['errors'][] = $candidate['entry']['label'].' could not be staged for promotion.';
      break;
    }

    if ($candidate['target_existed'] && ! $this->copyPromotionEnvironmentFiles($candidate['target_path'], $stagePath)) {
      $result['errors'][] = $candidate['entry']['label'].' target environment files could not be preserved.';
      break;
    }

    $prepared[$preparedIndex]['staged'] = TRUE;
  }

  if (! empty($result['errors'])) {
    $cleanupErrors = $this->restorePromotionArtifactTransaction($prepared);
    if (! empty($cleanupErrors)) {
      $result['errors'] = array_merge($result['errors'], $cleanupErrors);
    }
    return $result;
  }

  foreach ($prepared as $index => $preparedItem) {
    clearstatcache(TRUE, $preparedItem['target_path']);
    $targetExistsNow = is_dir($preparedItem['target_path']);

    if (is_link($preparedItem['target_path']) || (file_exists($preparedItem['target_path']) && ! $targetExistsNow) || $targetExistsNow !== $preparedItem['target_existed']) {
      $result['errors'][] = $preparedItem['entry']['label'].' target folder changed while the promotion was being prepared.';
      break;
    }

    if ($targetExistsNow) {
      if (! rename($preparedItem['target_path'], $preparedItem['backup_path'])) {
        $result['errors'][] = $preparedItem['entry']['label'].' existing target folder could not be moved to a promotion backup.';
        break;
      }
      $prepared[$index]['target_moved'] = TRUE;
    }

    if (! rename($preparedItem['stage_path'], $preparedItem['target_path'])) {
      $result['errors'][] = $preparedItem['entry']['label'].' staged folder could not replace the target folder.';
      break;
    }

    $prepared[$index]['promoted'] = TRUE;
  }

  if (! empty($result['errors'])) {
    $restoreErrors = $this->restorePromotionArtifactTransaction($prepared);
    if (! empty($restoreErrors)) {
      $result['errors'] = array_merge($result['errors'], $restoreErrors);
    }
    return $result;
  }

  foreach ($prepared as $preparedItem) {
    $result['copied'][] = $preparedItem['entry'];

    if (is_dir($preparedItem['backup_path']) && ! $this->removeDirectoryTree($preparedItem['backup_path'])) {
      $result['skipped'][] = $preparedItem['entry']['label'].' promotion backup could not be removed automatically.';
    }
  }

  return $result;
}

private function promotionPathsOverlap($firstPath, $secondPath) {
  $firstPath = rtrim(str_replace('\\', '/', (string) $firstPath), '/');
  $secondPath = rtrim(str_replace('\\', '/', (string) $secondPath), '/');

  if ($firstPath === '' || $secondPath === '') {
    return FALSE;
  }

  return $firstPath === $secondPath || strpos($firstPath.'/', $secondPath.'/') === 0 || strpos($secondPath.'/', $firstPath.'/') === 0;
}

private function promotionPathWithResolvedAncestor($path) {
  $candidate = rtrim((string) $path, '/\\');
  $suffix = array();

  while ($candidate !== '' && ! file_exists($candidate) && ! is_link($candidate)) {
    $parent = dirname($candidate);
    if ($parent === $candidate) {
      return FALSE;
    }
    array_unshift($suffix, basename($candidate));
    $candidate = $parent;
  }

  $realCandidate = realpath($candidate);
  if ($realCandidate === FALSE) {
    return FALSE;
  }

  foreach ($suffix as $segment) {
    $realCandidate .= DIRECTORY_SEPARATOR.$segment;
  }

  return $realCandidate;
}

private function isPromotionEnvironmentFilePath($relativePath) {
  $baseName = strtolower(basename(str_replace('\\', '/', (string) $relativePath)));
  return ($baseName === '.env' || strpos($baseName, '.env.') === 0) && $baseName !== '.env.example';
}

private function copyPromotionEnvironmentFiles($sourcePath, $targetPath) {
  if (! is_dir($sourcePath) || is_link($sourcePath) || ! is_dir($targetPath) || is_link($targetPath)) {
    return FALSE;
  }

  $sourcePath = rtrim($sourcePath, DIRECTORY_SEPARATOR);
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

  foreach ($iterator as $item) {
    $relativePath = substr($item->getPathname(), strlen($sourcePath) + 1);
    if (! $this->isPromotionEnvironmentFilePath($relativePath)) {
      continue;
    }

    if ($item->isLink() || ! $item->isFile()) {
      return FALSE;
    }

    $targetItemPath = $targetPath.DIRECTORY_SEPARATOR.$relativePath;
    if (! $this->ensureDirectory(dirname($targetItemPath)) || ! copy($item->getPathname(), $targetItemPath)) {
      return FALSE;
    }

    $permissions = fileperms($item->getPathname());
    if ($permissions !== FALSE && ! chmod($targetItemPath, $permissions & 0777)) {
      return FALSE;
    }
  }

  return TRUE;
}

private function restorePromotionArtifactTransaction($prepared) {
  $errors = array();

  foreach (array_reverse($prepared, TRUE) as $preparedItem) {
    if (! empty($preparedItem['promoted']) && is_dir($preparedItem['target_path']) && ! $this->removeDirectoryTree($preparedItem['target_path'])) {
      $errors[] = $preparedItem['entry']['label'].' promoted target could not be cleared while restoring the previous state.';
    }

    if (! empty($preparedItem['target_moved']) && is_dir($preparedItem['backup_path'])) {
      if (file_exists($preparedItem['target_path']) || is_link($preparedItem['target_path']) || ! rename($preparedItem['backup_path'], $preparedItem['target_path'])) {
        $errors[] = $preparedItem['entry']['label'].' promotion backup remains at '.$preparedItem['backup_path'].' because it could not be restored automatically.';
      }
    }

    if (is_dir($preparedItem['stage_path']) && ! $this->removeDirectoryTree($preparedItem['stage_path'])) {
      $errors[] = $preparedItem['entry']['label'].' staging folder could not be removed automatically.';
    }
  }

  return $errors;
}

private function promotionRepositoryRoot() {
  $jenkinsHome = isset($this->global['jenkins_home']) ? trim((string) $this->global['jenkins_home']) : '';

  if ($jenkinsHome !== '') {
    return rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository';
  }

  return FCPATH.'repository';
}

private function isTransientPromotionArtifactPath($relativePath) {
  $relativePath = trim(str_replace('\\', '/', (string) $relativePath), '/');
  if ($relativePath === '') {
    return FALSE;
  }

  $transientDirectories = array(
    '.git',
    '.venv',
    'venv',
    '.tox',
    '.nox',
    '.uv-cache',
    '.jobseeker-wheels',
    '.jobseeker-python-libs',
    '__pycache__',
    '.mypy_cache',
    '.ruff_cache',
    '.pytest_cache',
    'htmlcov',
    'build',
    'dist'
  );

  foreach (explode('/', strtolower($relativePath)) as $segment) {
    if (in_array($segment, $transientDirectories, TRUE) || preg_match('/\\.egg-info$/i', $segment) === 1) {
      return TRUE;
    }
  }

  $baseName = strtolower(basename($relativePath));
  if ($baseName === '.coverage' || strpos($baseName, '.coverage.') === 0 || $baseName === 'coverage.xml') {
    return TRUE;
  }

  return preg_match('/\\.py[co]$/i', $baseName) === 1;
}

private function copyDirectoryTree($sourcePath, $targetPath, $excludeTransientPythonFiles = FALSE, $excludeEnvironmentFiles = FALSE) {
  if (! is_dir($sourcePath) || is_link($sourcePath) || is_link($targetPath) || (file_exists($targetPath) && ! is_dir($targetPath)) || $this->promotionPathsOverlap($sourcePath, $targetPath)) {
    return FALSE;
  }

  if (! $this->ensureDirectory($targetPath)) {
    return FALSE;
  }

  $sourcePath = rtrim($sourcePath, DIRECTORY_SEPARATOR);
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

  foreach ($iterator as $item) {
    $relativePath = substr($item->getPathname(), strlen($sourcePath) + 1);
    if ($excludeTransientPythonFiles && $this->isTransientPromotionArtifactPath($relativePath)) {
      continue;
    }

    if ($excludeEnvironmentFiles && $this->isPromotionEnvironmentFilePath($relativePath)) {
      continue;
    }

    if ($item->isLink()) {
      return FALSE;
    }

    $targetItemPath = $targetPath.DIRECTORY_SEPARATOR.$relativePath;

    if ($item->isDir()) {
      if (! $this->ensureDirectory($targetItemPath)) {
        return FALSE;
      }
    } elseif ($item->isFile()) {
      if (! $this->ensureDirectory(dirname($targetItemPath)) || ! copy($item->getPathname(), $targetItemPath)) {
        return FALSE;
      }
      $permissions = $item->getPerms();
      if ($permissions !== FALSE && ! chmod($targetItemPath, $permissions & 0777)) {
        return FALSE;
      }
    } else {
      return FALSE;
    }
  }

  return TRUE;
}

private function removeDirectoryTree($path) {
  if (! is_dir($path)) {
    return TRUE;
  }

  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

  foreach ($iterator as $item) {
    if ($item->isDir() && ! $item->isLink()) {
      if (! rmdir($item->getPathname())) {
        return FALSE;
      }
    } else {
      if (! unlink($item->getPathname())) {
        return FALSE;
      }
    }
  }

  return rmdir($path);
}

private function cleanPromotionJobName($jobName) {
  $jobName = trim((string) $jobName);

  if ($jobName === '') {
    return array('ok' => FALSE, 'message' => 'Job name cannot be empty.');
  }

  if (strlen($jobName) > 255) {
    return array('ok' => FALSE, 'message' => 'Job name is longer than 255 characters.');
  }

  if (preg_match('/\s/', $jobName) || preg_match('/[^A-Za-z0-9._\/-]/', $jobName) || strpos($jobName, '..') !== FALSE || trim($jobName, '/') !== $jobName || strpos($jobName, '//') !== FALSE) {
    return array('ok' => FALSE, 'message' => 'Job name "'.$jobName.'" is invalid. Use letters, numbers, dot, dash, underscore, and folder separators only.');
  }

  return array('ok' => TRUE, 'name' => $jobName);
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

private function jenkinsCreateItemPath($jobName) {
  $segments = explode('/', trim((string) $jobName, '/'));
  $name = array_pop($segments);
  $parentJob = implode('/', $segments);
  $parentPath = $parentJob !== '' ? $this->jenkinsJobPath($parentJob).'/' : '';

  return $parentPath.'createItem?name='.rawurlencode($name);
}

private function isSuccessfulJenkinsStatus($status) {
  return in_array((int) $status, array(200, 201, 302, 303), TRUE);
}

private function replaceEnvironmentTokenInJobName($jobName, $sourceEnvironment, $targetEnvironment) {
  $pattern = '/(^|[._\/-])'.preg_quote($sourceEnvironment, '/').'($|[._\/-])/i';

  return preg_replace_callback($pattern, function($matches) use ($targetEnvironment) {
    return $matches[1].$targetEnvironment.$matches[2];
  }, $jobName, 1);
}

private function suggestPromotedJobName($sourceJobName, $sourceEnvironment, $targetEnvironment) {
  $suggested = $this->replaceEnvironmentTokenInJobName($sourceJobName, $sourceEnvironment, $targetEnvironment);

  if ($suggested !== $sourceJobName) {
    return $suggested;
  }

  $targetToken = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim((string) $targetEnvironment));
  $targetToken = trim($targetToken, '.-_');

  if ($targetToken === '') {
    $targetToken = 'deployed';
  }

  return $sourceJobName.'-'.$targetToken;
}

private function shortPromotionCommandPreview($commandText) {
  $lines = preg_split('/\r\n|\n|\r/', trim((string) $commandText));
  $previewLines = array();

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') {
      continue;
    }
    $previewLines[] = strlen($line) > 180 ? substr($line, 0, 177).'...' : $line;
    if (count($previewLines) >= 8) {
      break;
    }
  }

  return implode("\n", $previewLines);
}

public function addProject() {

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {

    $this->load->library('form_validation');

    $this->form_validation->set_rules('name','Project Name','trim|required|max_length[1000]');
    $this->form_validation->set_rules('active','Active Project','required|max_length[1]');
    $this->form_validation->set_rules('gitpath','Git Path','trim|max_length[2000]');

    if($this->form_validation->run() == FALSE)
    {
      $this->projectDetails();
    }
    else
    {

      $name = $this->security->xss_clean($this->input->post('name'));
      $active = $this->security->xss_clean($this->input->post('active'));
      $gitpath = $this->security->xss_clean($this->input->post('gitpath'));

      if ($name == null || $active == null) {
       $this->session->set_flashdata('error', 'Project Setup failed ! You must type a project name');
       redirect('Context/projectDetails');
     }

                // Check if the data is alredy on table
     $validateSetting = $this->model->validateProject($name);

     $Info = array(
      'ProjectName'=>$name, 
      'IsActive'=>$active, 
      'GitPath' => $gitpath,
      'CreatedOn'=>date('Y-m-d H:i:s')
    );

     if($validateSetting > 0){

      $this->session->set_flashdata('error', 'This row seems already created, please try changing the project name.');
    } else {

      $result = $this->model->insertProject($Info);

      if($result > 0)
      {
        $this->session->set_flashdata('success', 'New Project has successfully created and now is available to be used.');
      }
      else
      {
        $this->session->set_flashdata('error', 'Project creation failed !');
      }

    }

    redirect('Context/projectDetails');

  }

}

}


public function addEnvironment() {

  if ($this->jobSeekerIsStandaloneDeployment()) {
    $this->session->set_flashdata('error', 'Environment definitions are fixed by standalone deployment configuration.');
    redirect('Context/environment');
    return;
  }

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {

    $this->load->library('form_validation');

    $this->form_validation->set_rules('name','Environment Name','trim|required|max_length[100]');
    $this->form_validation->set_rules('active','Active Environment','required|max_length[1]');
    $this->form_validation->set_rules('description','Description','trim|max_length[2000]');

    if($this->form_validation->run() == FALSE)
    {
      $this->environment();
    }
    else
    {

      $name = $this->security->xss_clean($this->input->post('name'));
      $active = $this->security->xss_clean($this->input->post('active'));
      $description = $this->security->xss_clean($this->input->post('description'));

      if ($name == null || $active == null) {
       $this->session->set_flashdata('error', 'Environment Setup failed ! You must type an environment name');
       redirect('Context/environment');
     }

                // Check if the data is alredy on table
     $validateSetting = $this->model->validateEnvironment($name);

     $Info = array(
      'Environment'=>$name, 
      'IsActive'=>$active, 
      'Description' => $description,
      'CreatedOn'=>date('Y-m-d H:i:s')
    );

     if($validateSetting > 0){

      $this->session->set_flashdata('error', 'This row seems already created, please try changing the environment name.');
    } else {

      $result = $this->model->insertEnvironment($Info);

      if($result > 0)
      {
        $this->session->set_flashdata('success', 'New Environment has successfully created and now is available to be used.');
      }
      else
      {
        $this->session->set_flashdata('error', 'Environment creation failed !');
      }

    }

    redirect('Context/environment');

  }

}

}

public function addContext() {

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {
    $user = $this->global['name'];
    $this->load->library('form_validation');

    $this->form_validation->set_rules('contextValue','Context Value','required|max_length[1000]');
    $this->form_validation->set_rules('contextKey','Context Key','trim|required|max_length[1000]');
    $this->form_validation->set_rules('active','Active Context','required|max_length[1]');
    $this->form_validation->set_rules('encrypted','Encrypted Context','required|max_length[1]');
    $this->form_validation->set_rules('projectName','Project Name','required|max_length[255]');
    $this->form_validation->set_rules('environmentName','Environment Name','required|max_length[255]');
    $this->form_validation->set_rules('description','Description','trim|max_length[2000]');

    if($this->form_validation->run() == FALSE)
    {
      $this->contextDetails();
    }
    else
    {

      $contextValue = $this->security->xss_clean($this->input->post('contextValue'));
      $contextKey = $this->security->xss_clean($this->input->post('contextKey'));
      $active = $this->security->xss_clean($this->input->post('active'));
      $encrypted = $this->security->xss_clean($this->input->post('encrypted'));
      $projectName = $this->security->xss_clean($this->input->post('projectName'));
      $environmentName = $this->security->xss_clean($this->input->post('environmentName'));
      $description = $this->security->xss_clean($this->input->post('description'));
      $selectedEnvironment = $this->selectedContextEnvironment();

      if ($contextKey == null || $contextValue == null || $projectName == null || $environmentName == null) {
       $this->session->set_flashdata('error', 'Context Creation failed ! You must type a context key, value, project and environment.');
       redirect('Context/contextDetails');
     }

     if ($selectedEnvironment !== 'ALL' && $this->normalizeJobSeekerEnvironment($environmentName) !== $selectedEnvironment) {
       $this->session->set_flashdata('error', 'The target context environment is outside the current backend scope.');
       redirect('Context/contextDetails?environment='.rawurlencode($selectedEnvironment));
     }

     // Check if the data is alredy on table
     $validateSetting = $this->model->validateContext($contextKey,$projectName,$environmentName);
     $projectIdReturn = $this->model->getProjectId($projectName);
     $environmentIdReturn = $this->model->getEnvironmentId($environmentName);

     if (empty($projectIdReturn) || empty($environmentIdReturn)) {
       $this->session->set_flashdata('error', 'The selected project or environment is no longer available. Please choose another scope.');
       redirect('Context/contextDetails');
     }

     $projectId = $projectIdReturn[0]->id;
     $environmentId = $environmentIdReturn[0]->id;


     $Info = array(
      'ContextKey'=>$contextKey, 
      'ContextValue'=>$contextValue, 
      'IsActive' => $active,
      'IsEncrypted' => $encrypted,
      'CreatedBy' => $user,
      'EnvironmentFK' => $environmentId,
      'ProjectDetailsFK' => $projectId,
      'Description' => $description,
      'CreatedOn'=>date('Y-m-d H:i:s')
    );

     if($validateSetting > 0){

      $this->session->set_flashdata('error', 'This row seems already created, please try changing the context key, project and environment name.');
    } else {

      $result = $this->model->insertContext($Info);

      if($result > 0)
      {
        $this->session->set_flashdata('success', 'New Context has successfully created and now is available to be used.');
      }
      else
      {
        $this->session->set_flashdata('error', 'Context creation failed !');
      }

    }

    redirect('Context/contextDetails');

  }

}

}

  public function deleteProject() {

    if($this->isManager() == TRUE)
    {
      echo(json_encode(array('status'=>'access')));
    }
    else
    {
      if($this->input->method(TRUE) !== 'POST') {
        $this->output->set_status_header(405);
        echo(json_encode(array('status'=>FALSE, 'message'=>'Delete requests must use POST.')));
        return;
      }

      $id = $this->input->post('userId');

      $result = $this->model->deleteProject($id);

      if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
      else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
    }
  }

  public function deleteEnvironment() {

    if ($this->jobSeekerIsStandaloneDeployment()) {
      $this->output->set_status_header(409);
      echo(json_encode(array('status'=>FALSE, 'message'=>'Environment definitions are fixed by standalone deployment configuration.')));
      return;
    }

    if($this->isManager() == TRUE)
    {
      echo(json_encode(array('status'=>'access')));
    }
    else
    {
      if($this->input->method(TRUE) !== 'POST') {
        $this->output->set_status_header(405);
        echo(json_encode(array('status'=>FALSE, 'message'=>'Delete requests must use POST.')));
        return;
      }

      $id = $this->input->post('userId');

      $result = $this->model->deleteEnvironment($id);

      if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
      else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
    }
  }

  public function deleteContext() {

    if($this->isManager() == TRUE)
    {
      echo(json_encode(array('status'=>'access')));
    }
    else
    {
      if($this->input->method(TRUE) !== 'POST') {
        $this->output->set_status_header(405);
        echo(json_encode(array('status'=>FALSE, 'message'=>'Delete requests must use POST.')));
        return;
      }

      $id = $this->input->post('userId');
      $requestedEnvironment = $this->selectedContextEnvironment();
      if ($requestedEnvironment !== 'ALL') {
        $contextRows = $this->model->listContextId($id);
        if (! $this->contextRowsMatchSelectedEnvironment($contextRows, $requestedEnvironment)) {
          $this->output->set_status_header(409);
          echo(json_encode(array('status'=>FALSE, 'message'=>'The context does not belong to the selected environment.')));
          return;
        }
      }

      $result = $this->model->deleteContext($id);

      if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
      else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
    }
  }


     /**
     * Edit Input Component 
     */
     function editProject($id = NULL)
     {
      if($this->isManager() == TRUE )
      {
        $this->loadThis();
      }
      else
      {
        if($id == null)
        {
          redirect('Context/projectDetails');
        }


        $data['project'] = $this->model->getProject($id);

        if (empty($data['project'])) {
          $this->session->set_flashdata('error', 'The requested project could not be found.');
          redirect('Context/projectDetails');
        }

        $this->global['pageTitle'] = 'Job Seeker : Edit Data';

        $this->loadViews("projectDetailsEdit", $this->global, $data, NULL);
      }
    }

    /**
     * Edit Input Component 
     */
     function editEnvironment($id = NULL)
     {
	  if ($this->jobSeekerIsStandaloneDeployment()) {
		$this->session->set_flashdata('error', 'Environment definitions are fixed by standalone deployment configuration.');
		redirect('Context/environment');
		return;
	  }
      if($this->isManager() == TRUE )
      {
        $this->loadThis();
      }
      else
      {
        if($id == null)
        {
          redirect('Context/environment');
        }


        $data['environment'] = $this->model->getEnvironment($id);

        if (empty($data['environment'])) {
          $this->session->set_flashdata('error', 'The requested environment could not be found.');
          redirect('Context/environment');
        }

        $this->global['pageTitle'] = 'Job Seeker : Edit Data';

        $this->loadViews("environmentEdit", $this->global, $data, NULL);
      }
    }

    /**
     * Edit Input Component 
     */
     function editContext($id = NULL)
     {
      if($this->isManager() == TRUE )
      {
        $this->loadThis();
      }
      else
      {
        if($id == null)
        {
          redirect('Context/contextDetails');
        }


        $data["list"] = $this->model->listContextId($id);

        $selectedEnvironment = $this->selectedContextEnvironment();
        if (! $this->contextRowsMatchSelectedEnvironment($data["list"], $selectedEnvironment)) {
          $this->session->set_flashdata('error', 'The requested context variable could not be found.');
          redirect('Context/contextDetails');
        }

        $data["listProjects"] = $this->model->listProjects();
        $data["listEnvironments"] = $this->model->listEnvironments();
        if ($selectedEnvironment !== 'ALL') {
          $data["listEnvironments"] = array_values(array_filter($data["listEnvironments"], function($row) use ($selectedEnvironment) {
            return $this->normalizeJobSeekerEnvironment($row->Environment) === $selectedEnvironment;
          }));
        }
        $data["contexts"] = $this->model->listAvailableContexts($selectedEnvironment);
        $data["activeContexts"] = $this->model->listActiveContexts($selectedEnvironment);
        $data["selectedEnvironment"] = $selectedEnvironment;
        $this->global['selectedEnvironment'] = $selectedEnvironment;

        $this->global['pageTitle'] = 'Job Seeker : Edit Data';

        $this->loadViews("contextDetailsEdit", $this->global, $data, NULL);
      }
    }

    public function editProjectUpdate() {

      if($this->isManager() == TRUE)
      {
        $this->loadThis();
      }
      else
      {

        $this->load->library('form_validation');

        $Id = (int) $this->security->xss_clean($this->input->post('Id'));

        $this->form_validation->set_rules('name','Project Name','trim|required|max_length[1000]');
        $this->form_validation->set_rules('Id','Project Id','required|integer');
        $this->form_validation->set_rules('active','Active Project','required|max_length[1]');
        $this->form_validation->set_rules('gitpath','Git Path','trim|max_length[2000]');

        if($this->form_validation->run() == FALSE)
        {
          if ($Id > 0) {
            $this->editProject($Id);
          } else {
            $this->session->set_flashdata('error', 'The project update request did not include a valid project ID.');
            redirect('Context/projectDetails');
          }
        }
        else
        {
          $name = $this->security->xss_clean($this->input->post('name'));
          $active = $this->security->xss_clean($this->input->post('active'));
          $gitpath = $this->security->xss_clean($this->input->post('gitpath'));

          if ($name == null || $active == null) {
           $this->session->set_flashdata('error', 'Project Setup failed ! You must type a project name');
           redirect('Context/projectDetails');
         }

         if ($this->model->validateProjectExcept($name, $Id) > 0) {
           $this->session->set_flashdata('error', 'A project with this name already exists.');
           redirect('Context/editProject/'.$Id);
         }

         $Info = array(
          'ProjectName'=>$name, 
          'IsActive'=>$active, 
          'GitPath' => $gitpath,
          'ModifiedOn'=>date('Y-m-d H:i:s')
        );

         $result = $this->model->updateProjectSetting($Info,$Id);

         if($result == True)
         {
          $this->session->set_flashdata('success', 'The project was updated successfully.');
        }
        else
        {
          $this->session->set_flashdata('error', 'Project update failed !');
        }

        redirect('Context/projectDetails');

      }

    }

  }

  public function editEnvironmentUpdate() {

  if ($this->jobSeekerIsStandaloneDeployment()) {
    $this->session->set_flashdata('error', 'Environment definitions are fixed by standalone deployment configuration.');
    redirect('Context/environment');
    return;
  }

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {

    $this->load->library('form_validation');

    $Id = (int) $this->security->xss_clean($this->input->post('Id'));

    $this->form_validation->set_rules('Id','Environment Id','required|integer');
    $this->form_validation->set_rules('name','Environment Name','trim|required|max_length[100]');
    $this->form_validation->set_rules('active','Active Environment','required|max_length[1]');
    $this->form_validation->set_rules('description','Description','trim|max_length[2000]');

    if($this->form_validation->run() == FALSE)
    {
      if ($Id > 0) {
        $this->editEnvironment($Id);
      } else {
        $this->session->set_flashdata('error', 'The environment update request did not include a valid environment ID.');
        redirect('Context/environment');
      }
    }
    else
    {
      $name = $this->security->xss_clean($this->input->post('name'));
      $active = $this->security->xss_clean($this->input->post('active'));
      $description = $this->security->xss_clean($this->input->post('description'));

     if ($name == null || $active == null || $Id == null) {
       $this->session->set_flashdata('error', 'Environment Setup failed ! You must type an environment name,id and active status');
       redirect('Context/environment');
     }

     if ($this->model->validateEnvironmentExcept($name, $Id) > 0) {
       $this->session->set_flashdata('error', 'An environment with this name already exists.');
       redirect('Context/editEnvironment/'.$Id);
     }

     $Info = array(
      'Environment'=>$name, 
      'IsActive'=>$active, 
      'Description' => $description,
      'ModifiedOn'=>date('Y-m-d H:i:s')
    );

      $result = $this->model->updateEnvironment($Info,$Id);

      if($result > 0)
      {
        $this->session->set_flashdata('success', 'The environment was updated successfully.');
      }
      else
      {
        $this->session->set_flashdata('error', 'Environment updated failed !');
      }

    redirect('Context/environment');

  }

}

}

public function editContextUpdate() {

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {
    $user = $this->global['name'];
    $this->load->library('form_validation');

    $Id = (int) $this->security->xss_clean($this->input->post('ContextId'));
    $selectedEnvironment = $this->selectedContextEnvironment();
    if (! $this->contextRowsMatchSelectedEnvironment($this->model->listContextId($Id), $selectedEnvironment)) {
      $this->session->set_flashdata('error', 'The requested context variable is outside the current environment.');
      redirect('Context/contextDetails');
    }

    $this->form_validation->set_rules('contextValue','Context Value','required|max_length[1000]');
    $this->form_validation->set_rules('contextKey','Context Key','trim|required|max_length[1000]');
    $this->form_validation->set_rules('ContextId','Context Id','required|integer');
    $this->form_validation->set_rules('active','Active Context','required|max_length[1]');
    $this->form_validation->set_rules('encrypted','Encrypted Context','required|max_length[1]');
    $this->form_validation->set_rules('projectName','Project Name','required|max_length[255]');
    $this->form_validation->set_rules('environmentName','Environment Name','required|max_length[255]');
    $this->form_validation->set_rules('description','Description','trim|max_length[2000]');

    if($this->form_validation->run() == FALSE)
    {
      if ($Id > 0) {
        $this->editContext($Id);
      } else {
        $this->session->set_flashdata('error', 'The context update request did not include a valid context ID.');
        redirect('Context/contextDetails');
      }
    }
    else
    {

      $contextValue = $this->security->xss_clean($this->input->post('contextValue'));
      $contextKey = $this->security->xss_clean($this->input->post('contextKey'));
      $active = $this->security->xss_clean($this->input->post('active'));
      $encrypted = $this->security->xss_clean($this->input->post('encrypted'));
      $projectName = $this->security->xss_clean($this->input->post('projectName'));
      $environmentName = $this->security->xss_clean($this->input->post('environmentName'));
      $description = $this->security->xss_clean($this->input->post('description'));

     if ($contextKey == null || $contextValue == null || $projectName == null || $environmentName == null) {
       $this->session->set_flashdata('error', 'Context Creation failed ! You must type a context key, value, project and environment.');
       redirect('Context/contextDetails');
     }

     if ($selectedEnvironment !== 'ALL' && $this->normalizeJobSeekerEnvironment($environmentName) !== $selectedEnvironment) {
       $this->session->set_flashdata('error', 'The target context environment is outside the current backend scope.');
       redirect('Context/editContext/'.$Id);
     }

     // Check if the data is alredy on table
     $projectIdReturn = $this->model->getProjectId($projectName);
     $environmentIdReturn = $this->model->getEnvironmentId($environmentName);

     if (empty($projectIdReturn) || empty($environmentIdReturn)) {
       $this->session->set_flashdata('error', 'The selected project or environment is no longer available. Please choose another scope.');
       redirect('Context/editContext/'.$Id);
     }

     if ($this->model->validateContextExcept($contextKey, $projectName, $environmentName, $Id) > 0) {
       $this->session->set_flashdata('error', 'A context with this key already exists in the selected project and environment.');
       redirect('Context/editContext/'.$Id);
     }

     $projectId = $projectIdReturn[0]->id;
     $environmentId = $environmentIdReturn[0]->id;


     $Info = array(
      'ContextKey'=>$contextKey, 
      'ContextValue'=>$contextValue, 
      'IsActive' => $active,
      'IsEncrypted' => $encrypted,      
      'EnvironmentFK' => $environmentId,
      'ProjectDetailsFK' => $projectId,
      'Description' => $description,
      'ModifiedBy' => $user,
      'ModifiedOn'=>date('Y-m-d H:i:s')
    );

      $result = $this->model->updatedContext($Info,$Id);

      if($result > 0)
      {
        $this->session->set_flashdata('success', 'The context variable was updated successfully.');
      }
      else
      {
        $this->session->set_flashdata('error', 'Context update failed !');
      }

    

    redirect('Context/contextDetails');

  }

}

}



}

?>
