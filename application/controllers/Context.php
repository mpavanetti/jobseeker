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
      date_default_timezone_set('America/Sao_Paulo');
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

    $data["list"] = $this->model->listEnvironments();
    $data["environments"] = $this->model->listAvailableEnvironments();
    $data["activeEnvironments"] = $this->model->listActiveEnvironments();
    $data["role"] = $this->isManager();

    $this->loadViews("environment", $this->global, $data, NULL);
  }
}

public function fetchEnvironments() {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson["data"] = $this->model->listEnvironments();
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

    $data["user"] = $user;
    $data["list"] = $this->model->listContexts();
    $data["listProjects"] = $this->model->listProjects();
    $data["listEnvironments"] = $this->model->listEnvironments();
    $data["contexts"] = $this->model->listAvailableContexts();
    $data["activeContexts"] = $this->model->listActiveContexts();
    $data["role"] = $this->isManager();

    $this->loadViews("contextDetails", $this->global, $data, NULL);
  }
}

public function promotion() {

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {
    $this->global['pageTitle'] = 'Job Seeker : Environment Promotion';
    $data["listEnvironments"] = $this->model->listEnvironments();
    $data["listProjects"] = $this->model->listProjects();
    $jenkinsJobs = $this->listPromotionJenkinsJobs();
    $data["jenkinsJobs"] = $jenkinsJobs['jobs'];
    $data["jenkinsError"] = $jenkinsJobs['error'];
    $data["jenkinsStatus"] = $jenkinsJobs['status'];
    $data["role"] = $this->isManager();

    $this->loadViews("contextPromotion", $this->global, $data, NULL);
  }
}

public function promoteContext() {

  $this->session->set_flashdata('error', 'Context variable promotion was replaced by Jenkins job promotion. Select a source job and target environment below.');
  redirect('Context/promotion');
}

public function previewJobPromotion() {

  header('Content-type:application/json;charset=utf-8');

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

      if (! $result['ok']) {
        $this->session->set_flashdata('error', $result['message']);
      } else {
        $action = $result['target_exists'] ? 'updated' : 'created';
        $contextSummary = !empty($result['context_promotion']['enabled']) ? ' Contexts: '.$result['context_promotion']['result']['created'].' created, '.$result['context_promotion']['result']['updated'].' updated, '.$result['context_promotion']['result']['skipped'].' skipped.' : '';
        $rollbackSummary = !empty($result['rollback_id']) ? ' Rollback checkpoint: '.$result['rollback_id'].'.' : '';
        $this->session->set_flashdata('success', 'Promoted '.$result['job_count'].' Jenkins job(s) from '.$input['source_environment']->Environment.' to '.$input['target_environment']->Environment.'. Root target '.$input['target_job'].' '.$action.'. Command updates: '.$result['command_updates'].', parameter updates: '.$result['parameter_updates'].', artifact folders copied: '.count($result['artifacts']['copied']).'.'.$contextSummary.$rollbackSummary);
        if (!empty($result['rollback_id'])) {
          $this->session->set_flashdata('rollback_id', $result['rollback_id']);
        }
      }

      redirect('Context/promotion');
    }
  }
}

public function rollbackJobPromotion() {

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {
    $rollbackId = $this->security->xss_clean($this->input->post('rollbackId'));
    $result = $this->performPromotionRollback($rollbackId);

    if (! $result['ok']) {
      $this->session->set_flashdata('error', $result['message']);
    } else {
      $this->session->set_flashdata('success', $result['message']);
    }

    redirect('Context/promotion');
  }
}

private function listPromotionJenkinsJobs() {
  $response = $this->requestJenkins('GET', 'api/json?tree=jobs[_class,name,fullName,color,buildable,lastBuild[number,result,timestamp],jobs[_class,name,fullName,color,buildable,lastBuild[number,result,timestamp],jobs[_class,name,fullName,color,buildable,lastBuild[number,result,timestamp]]]]');

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
        'lastBuild' => isset($job->lastBuild) ? $job->lastBuild : NULL
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
    return array('ok' => FALSE, 'message' => 'Promotion failed. Select a source environment and a target environment.');
  }

  if ($sourceEnvironmentId == $targetEnvironmentId) {
    return array('ok' => FALSE, 'message' => 'Promotion failed. Source and target environments must be different.');
  }

  $sourceEnvironment = $this->model->getEnvironment($sourceEnvironmentId);
  $targetEnvironment = $this->model->getEnvironment($targetEnvironmentId);

  if (empty($sourceEnvironment) || empty($targetEnvironment)) {
    return array('ok' => FALSE, 'message' => 'Promotion failed. Environment was not found.');
  }

  $contextProject = NULL;
  if ($promoteContexts) {
    if ($contextProjectId <= 0) {
      return array('ok' => FALSE, 'message' => 'Promotion failed. Select a context project when context promotion is enabled.');
    }

    $contextProject = $this->model->getProject($contextProjectId);
    if (empty($contextProject)) {
      return array('ok' => FALSE, 'message' => 'Promotion failed. Context project was not found.');
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
    return array('ok' => FALSE, 'message' => 'Promotion failed. Target job must be a separate Jenkins job so the source environment remains unchanged.');
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
    return $this->promotionResultPayload(TRUE, 'Promotion preview is ready.', $input, $preparedJobs, $totals, $allArtifacts, $commandPreviews, $contextPlan, '');
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
      return array('ok' => FALSE, 'message' => 'Promotion failed while copying artifacts for '.$prepared['source_job'].': '.implode(' ', $artifactCopy['errors']).'.', 'rollback_id' => $rollbackId);
    }

    $allArtifacts['copied'] = array_merge($allArtifacts['copied'], $artifactCopy['copied']);
    $allArtifacts['skipped'] = array_merge($allArtifacts['skipped'], $artifactCopy['skipped']);

    $save = $this->savePreparedPromotionJob($prepared);
    if (! $save['ok']) {
      return array('ok' => FALSE, 'message' => 'Promotion failed. Jenkins refused to save '.$prepared['target_job'].'. HTTP '.$save['status'].'.'.($rollbackId !== '' ? ' Rollback checkpoint: '.$rollbackId.'.' : ''), 'rollback_id' => $rollbackId);
    }
  }

  if (! empty($contextPlan['enabled'])) {
    $contextPlan['result'] = $this->model->promoteContexts($input['context_project_id'], (int) $input['source_environment']->Id, (int) $input['target_environment']->Id, $input['user'], $input['overwrite_contexts']);
  }

  return $this->promotionResultPayload(TRUE, 'Promotion completed.', $input, $preparedJobs, $totals, $allArtifacts, $commandPreviews, $contextPlan, $rollbackId);
}

private function prepareSingleJobPromotion($input, $requireEnvironmentBinding) {
  $sourcePath = $this->jenkinsJobPath($input['source_job']);
  $sourceResponse = $this->requestJenkins('GET', $sourcePath . '/config.xml');

  if ((int) $sourceResponse['status'] !== 200) {
    return array('ok' => FALSE, 'message' => 'Promotion failed. Source Jenkins job '.$input['source_job'].' config could not be loaded. HTTP '.$sourceResponse['status'].'.');
  }

  $detectedEnvironment = $this->detectPromotionEnvironment($sourceResponse['body'], $input['source_job']);
  if ($requireEnvironmentBinding && ! empty($detectedEnvironment['environment']) && ! $this->promotionEnvironmentsEquivalent($detectedEnvironment['environment'], $input['source_environment']->Environment)) {
    return array('ok' => FALSE, 'message' => 'Promotion stopped. Source Jenkins job '.$input['source_job'].' appears to run in '.$detectedEnvironment['environment'].' from '.$detectedEnvironment['source'].', not '.$input['source_environment']->Environment.'. Select the detected source environment before promoting.');
  }

  $jobNameMap = isset($input['job_name_map']) ? $input['job_name_map'] : array();
  $transform = $this->transformPromotedJenkinsConfig($sourceResponse['body'], $input['source_environment']->Environment, $input['target_environment']->Environment, $input['source_job'], $input['target_job'], $jobNameMap);
  if (! $transform['ok']) {
    return $transform;
  }

  if ($requireEnvironmentBinding && ($transform['command_updates'] + $transform['parameter_updates']) < 1) {
    return array('ok' => FALSE, 'message' => 'Promotion stopped. JobSeeker could not find '.$input['source_environment']->Environment.' in a Jenkins environment parameter, --context argument, environment assignment, or Python environment argument inside '.$input['source_job'].'.');
  }

  $targetPath = $this->jenkinsJobPath($input['target_job']);
  $targetResponse = $this->requestJenkins('GET', $targetPath . '/api/json');
  $targetExists = (int) $targetResponse['status'] === 200;

  if (! $targetExists && (int) $targetResponse['status'] !== 404) {
    return array('ok' => FALSE, 'message' => 'Promotion failed. Target Jenkins job '.$input['target_job'].' state could not be checked. HTTP '.$targetResponse['status'].'.');
  }

  if ($targetExists && ! $input['overwrite']) {
    return array('ok' => FALSE, 'message' => 'Promotion stopped. Target Jenkins job '.$input['target_job'].' already exists. Enable overwrite to update it.', 'target_exists' => TRUE);
  }

  $artifacts = $this->copyPromotionArtifacts($input['source_job'], $input['target_job'], $input['overwrite'], TRUE);
  if (! empty($artifacts['errors'])) {
    return array('ok' => FALSE, 'message' => 'Promotion failed while preparing artifacts for '.$input['source_job'].': '.implode(' ', $artifacts['errors']).'.', 'artifacts' => $artifacts);
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
      return array('ok' => FALSE, 'message' => 'Promotion stopped. Dependency graph is larger than '.$maxJobs.' jobs.');
    }

    $current = array_shift($queue);
    $sourceJob = $current['source_job'];
    $targetJob = $current['target_job'];

    if (isset($seenSources[$sourceJob])) {
      continue;
    }

    if (isset($seenTargets[$targetJob]) && $seenTargets[$targetJob] !== $sourceJob) {
      return array('ok' => FALSE, 'message' => 'Promotion stopped. Multiple source jobs map to target job '.$targetJob.'. Choose clearer environment naming or promote the dependency separately.');
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
      return array('ok' => FALSE, 'message' => 'Promotion failed. Dependency scan could not load '.$sourceJob.'. HTTP '.$sourceResponse['status'].'.');
    }

    foreach ($this->extractDownstreamJobNames($sourceResponse['body']) as $downstreamJob) {
      $clean = $this->cleanPromotionJobName($downstreamJob);
      if (! $clean['ok']) {
        return array('ok' => FALSE, 'message' => 'Promotion stopped. Downstream job name '.$downstreamJob.' is not safe to promote.');
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
    return array('ok' => FALSE, 'message' => 'Promotion failed. Rollback folder could not be prepared.');
  }

  $rollbackId = date('YmdHis').'-'.substr(sha1(uniqid('', TRUE)), 0, 10);
  $rollbackDirectory = $root.DIRECTORY_SEPARATOR.$rollbackId;
  if (! $this->ensureDirectory($rollbackDirectory)) {
    return array('ok' => FALSE, 'message' => 'Promotion failed. Rollback checkpoint folder could not be created.');
  }

  $bundle = array(
    'id' => $rollbackId,
    'created_at' => date('c'),
    'created_by' => $input['user'],
    'source_environment_id' => (int) $input['source_environment']->Id,
    'source_environment' => $input['source_environment']->Environment,
    'target_environment_id' => (int) $input['target_environment']->Id,
    'target_environment' => $input['target_environment']->Environment,
    'jobs' => array(),
    'artifacts' => array(),
    'contexts' => array('enabled' => !empty($contextPlan['enabled']), 'snapshot' => !empty($contextPlan['enabled']) ? $contextPlan['snapshot'] : array('total' => 0, 'rows' => array()))
  );

  foreach ($preparedJobs as $prepared) {
    $jobSnapshot = array('target_job' => $prepared['target_job'], 'existed' => $prepared['target_exists'], 'config_xml' => '');

    if ($prepared['target_exists']) {
      $configResponse = $this->requestJenkins('GET', $this->jenkinsJobPath($prepared['target_job']) . '/config.xml');
      if ((int) $configResponse['status'] !== 200) {
        return array('ok' => FALSE, 'message' => 'Promotion failed. Existing target job '.$prepared['target_job'].' could not be backed up. HTTP '.$configResponse['status'].'.');
      }
      $jobSnapshot['config_xml'] = $configResponse['body'];
    }

    $bundle['jobs'][] = $jobSnapshot;

    foreach ($prepared['artifacts']['planned'] as $artifact) {
      $targetPath = $this->promotionArtifactAbsolutePath($artifact['target']);
      if ($targetPath === FALSE) {
        return array('ok' => FALSE, 'message' => 'Promotion failed. Artifact rollback path is invalid for '.$artifact['target'].'.');
      }

      $artifactSnapshot = array('label' => $artifact['label'], 'target' => $artifact['target'], 'existed' => is_dir($targetPath), 'backup_path' => '');
      if ($artifactSnapshot['existed']) {
        $backupRelativePath = 'artifacts/'.count($bundle['artifacts']);
        $backupPath = $rollbackDirectory.DIRECTORY_SEPARATOR.$backupRelativePath;
        if (! $this->copyDirectoryTree($targetPath, $backupPath)) {
          return array('ok' => FALSE, 'message' => 'Promotion failed. Artifact folder '.$artifact['target'].' could not be backed up for rollback.');
        }
        $artifactSnapshot['backup_path'] = $backupRelativePath;
      }

      $bundle['artifacts'][] = $artifactSnapshot;
    }
  }

  $filePath = $this->promotionRollbackFilePath($rollbackId);
  if ($filePath === FALSE || file_put_contents($filePath, json_encode($bundle, JSON_PRETTY_PRINT), LOCK_EX) === FALSE) {
    return array('ok' => FALSE, 'message' => 'Promotion failed. Rollback checkpoint could not be written.');
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
        $errors[] = 'Could not delete promoted job '.$targetJob.' (HTTP '.$response['status'].').';
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

  @rename($filePath, $filePath.'.rolledback.'.date('YmdHis'));
  return array('ok' => TRUE, 'message' => 'Rollback completed. Jobs restored: '.$restoredJobs.', jobs deleted: '.$deletedJobs.', artifact folders restored: '.$restoredArtifacts.', artifact folders deleted: '.$deletedArtifacts.', contexts restored: '.$contextResult['restored'].', contexts deleted: '.$contextResult['deleted'].'.');
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
    return array('ok' => FALSE, 'message' => 'Promotion failed. Source Jenkins config.xml is not valid XML.');
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
  $agentAssignmentUpdates = $this->rewritePromotionAgentAssignment($dom, $targetEnvironment);

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

  return array('text' => $updated, 'environment_updates' => $environmentUpdates, 'artifact_path_updates' => $artifactPathUpdates);
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

private function rewritePromotionAgentAssignment($dom, $targetEnvironment) {
  $agentLabel = $this->jenkinsEnvironmentAgentLabel($targetEnvironment);
  if ($agentLabel === '' || ! $dom->documentElement) {
    return 0;
  }

  $updates = 0;
  $root = $dom->documentElement;
  $assignedNode = $this->directChildElement($root, 'assignedNode');
  $canRoam = $this->directChildElement($root, 'canRoam');

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

    $entry = array('label' => $location['label'], 'source' => $location['relative'], 'target' => $location['target']);

    if (is_dir($targetPath) && ! $overwrite) {
      $result['errors'][] = $location['label'].' already exists for the target job. Enable overwrite to replace it.';
      continue;
    }

    if ($previewOnly) {
      $result['planned'][] = $entry;
      continue;
    }

    if (is_dir($targetPath) && ! $this->removeDirectoryTree($targetPath)) {
      $result['errors'][] = $location['label'].' target folder could not be cleared.';
      continue;
    }

    if (! $this->copyDirectoryTree($sourcePath, $targetPath)) {
      $result['errors'][] = $location['label'].' could not be copied.';
      continue;
    }

    $result['copied'][] = $entry;
  }

  return $result;
}

private function promotionRepositoryRoot() {
  $jenkinsHome = isset($this->global['jenkins_home']) ? trim((string) $this->global['jenkins_home']) : '';

  if ($jenkinsHome !== '') {
    return rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository';
  }

  return FCPATH.'repository';
}

private function copyDirectoryTree($sourcePath, $targetPath) {
  if (! $this->ensureDirectory($targetPath)) {
    return FALSE;
  }

  $sourcePath = rtrim($sourcePath, DIRECTORY_SEPARATOR);
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

  foreach ($iterator as $item) {
    if ($item->isLink()) {
      return FALSE;
    }

    $relativePath = substr($item->getPathname(), strlen($sourcePath) + 1);
    $targetItemPath = $targetPath.DIRECTORY_SEPARATOR.$relativePath;

    if ($item->isDir()) {
      if (! $this->ensureDirectory($targetItemPath)) {
        return FALSE;
      }
    } else {
      if (! $this->ensureDirectory(dirname($targetItemPath)) || ! copy($item->getPathname(), $targetItemPath)) {
        return FALSE;
      }
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
    $targetToken = 'promoted';
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

    $this->form_validation->set_rules('name','Project Name','required|max_length[1000]');
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

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {

    $this->load->library('form_validation');

    $this->form_validation->set_rules('name','Environment Name','required|max_length[100]');
    $this->form_validation->set_rules('active','Active Project','required|max_length[1]');
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
    $this->form_validation->set_rules('contextKey','Context Key','required|max_length[1000]');
    $this->form_validation->set_rules('active','Active Context','required|max_length[1]');
    $this->form_validation->set_rules('encrypted','Encrypted Context','required|max_length[1]');
    $this->form_validation->set_rules('projectName','Project Name','required|max_length[255]');
    $this->form_validation->set_rules('environmentName','Environment Name','required|max_length[255]');
    $this->form_validation->set_rules('description','Description','trim|max_length[2000]');

    if($this->form_validation->run() == FALSE)
    {
      $this->projectDetails();
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

     // Check if the data is alredy on table
     $validateSetting = $this->model->validateContext($contextKey,$projectName,$environmentName);
     $projectIdReturn = $this->model->getProjectId($projectName);
     $environmentIdReturn = $this->model->getEnvironmentId($environmentName);
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
      $id = $this->input->post('userId');

      $result = $this->model->deleteProject($id);

      if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
      else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
    }
  }

  public function deleteEnvironment() {

    if($this->isManager() == TRUE)
    {
      echo(json_encode(array('status'=>'access')));
    }
    else
    {
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
      $id = $this->input->post('userId');

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

        $this->global['pageTitle'] = 'Job Seeker : Edit Data';

        $this->loadViews("projectDetailsEdit", $this->global, $data, NULL);
      }
    }

    /**
     * Edit Input Component 
     */
     function editEnvironment($id = NULL)
     {
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
        $data["listProjects"] = $this->model->listProjects();
        $data["listEnvironments"] = $this->model->listEnvironments();
        $data["contexts"] = $this->model->listAvailableContexts();
        $data["activeContexts"] = $this->model->listActiveContexts();

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

        $this->form_validation->set_rules('name','Project Name','required|max_length[1000]');
        $this->form_validation->set_rules('Id','Project Id','required|max_length[11]');
        $this->form_validation->set_rules('active','Active Project','required|max_length[1]');
        $this->form_validation->set_rules('gitpath','Git Path','trim|max_length[2000]');

        if($this->form_validation->run() == FALSE)
        {
          $this->projectDetails();
        }
        else
        {
          $Id = $this->security->xss_clean($this->input->post('Id'));
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
          'ModifiedOn'=>date('Y-m-d H:i:s')
        );

         $result = $this->model->updateProjectSetting($Info,$Id);

         if($result == True)
         {
          $this->session->set_flashdata('success', 'New Project has successfully updated and now is available to be used.');
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

  if($this->isManager() == TRUE)
  {
    $this->loadThis();
  }
  else
  {

    $this->load->library('form_validation');

    $this->form_validation->set_rules('Id','Environment Id','required|max_length[11]');
    $this->form_validation->set_rules('name','Environment Name','required|max_length[100]');
    $this->form_validation->set_rules('active','Active Project','required|max_length[1]');
    $this->form_validation->set_rules('description','Description','trim|max_length[2000]');

    if($this->form_validation->run() == FALSE)
    {
      $this->environment();
    }
    else
    {
      $Id = $this->security->xss_clean($this->input->post('Id'));
      $name = $this->security->xss_clean($this->input->post('name'));
      $active = $this->security->xss_clean($this->input->post('active'));
      $description = $this->security->xss_clean($this->input->post('description'));

      if ($name == null || $active == null || $Id == null) {
       $this->session->set_flashdata('error', 'Environment Setup failed ! You must type an environment name,id and active status');
       redirect('Context/environment');
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
        $this->session->set_flashdata('success', 'New Environment has successfully updated and now is available to be used.');
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

    $this->form_validation->set_rules('contextValue','Context Value','required|max_length[1000]');
    $this->form_validation->set_rules('contextKey','Context Key','required|max_length[1000]');
    $this->form_validation->set_rules('active','Active Context','required|max_length[1]');
    $this->form_validation->set_rules('encrypted','Encrypted Context','required|max_length[1]');
    $this->form_validation->set_rules('projectName','Project Name','required|max_length[255]');
    $this->form_validation->set_rules('environmentName','Environment Name','required|max_length[255]');
    $this->form_validation->set_rules('description','Description','trim|max_length[2000]');

    if($this->form_validation->run() == FALSE)
    {
      $this->projectDetails();
    }
    else
    {

      $Id = $this->security->xss_clean($this->input->post('ContextId'));
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

     // Check if the data is alredy on table
     $projectIdReturn = $this->model->getProjectId($projectName);
     $environmentIdReturn = $this->model->getEnvironmentId($environmentName);
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
        $this->session->set_flashdata('success', 'New Context has successfully updated and now is available to be used.');
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