const fs = require('fs');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

const header = fs.readFileSync('application/views/includes/header.php', 'utf8');
const baseController = fs.readFileSync('application/libraries/BaseController.php', 'utf8');
const jobCreationController = fs.readFileSync('application/controllers/JobCreation.php', 'utf8');
const dashboardController = fs.readFileSync('application/controllers/Dashboard.php', 'utf8');
const deleteView = fs.readFileSync('application/views/deleteJob.php', 'utf8');
const jobList = fs.readFileSync('application/views/jobList.php', 'utf8');
const fullJobList = fs.readFileSync('application/views/fullJobList.php', 'utf8');
const jobCreation = fs.readFileSync('application/views/jobCreation.php', 'utf8');
const jobExecution = fs.readFileSync('application/views/jobExecution.php', 'utf8');
const jobView = fs.readFileSync('application/views/jobView.php', 'utf8');
const executorView = fs.readFileSync('application/views/jenkinsExecutors.php', 'utf8');
const promotionView = fs.readFileSync('application/views/contextPromotion.php', 'utf8');
const deleteController = fs.readFileSync('application/controllers/DeleteJob.php', 'utf8');
const jenkinsProxy = fs.readFileSync('application/controllers/JenkinsProxy.php', 'utf8');
const tmfController = fs.readFileSync('application/controllers/Tmf.php', 'utf8');
const contextController = fs.readFileSync('application/controllers/Context.php', 'utf8');
const contextModel = fs.readFileSync('application/models/Context_model.php', 'utf8');
const dataAssetsController = fs.readFileSync('application/controllers/DataAssets.php', 'utf8');
const dataAssetsModel = fs.readFileSync('application/models/DataAssets_model.php', 'utf8');
const connectorController = fs.readFileSync('application/controllers/DbSettings.php', 'utf8');
const connectorModel = fs.readFileSync('application/models/DbSettings_model.php', 'utf8');
const pipelineModel = fs.readFileSync('application/models/Pipeline_model.php', 'utf8');
const visualizationModel = fs.readFileSync('application/models/Visualization_model.php', 'utf8');
const contextScript = fs.readFileSync('assets/js/context-details.js', 'utf8');
const assetScript = fs.readFileSync('assets/js/data-assets.js', 'utf8');
const runtimeConfig = fs.readFileSync('application/config/config.json', 'utf8');
const compose = fs.readFileSync('docker-compose.yml', 'utf8');

assert(header.includes("'jobseeker.global.environment.user.' + preferenceUserId"), 'Global environment localStorage must be scoped by user.');
assert(header.includes("'jobseeker_global_environment_user_' + preferenceUserId"), 'Global environment cookies must be scoped by user.');
assert(jobCreationController.includes('$this->collectAvailableJobs($payload->jobs, $availableJobs, $environmentFilter)'), 'Available Jenkins jobs must be filtered in the backend.');
assert(jobCreationController.includes('$this->jobSeekerEnvironmentPreference()'), 'Available jobs must default to the current user environment preference.');
[jobList, fullJobList, jobCreation, jobExecution, jobView, deleteView].forEach((view, index) => {
  assert(view.includes('availableJobs'), 'Jenkins job view #' + (index + 1) + ' must use the backend available-jobs endpoint.');
  assert(/environment/.test(view), 'Jenkins job view #' + (index + 1) + ' must send an environment scope.');
});
assert(!jobExecution.includes('jobMatchesEnvironmentFilter'), 'Job Execution must not re-filter environments in JavaScript.');
assert(!jobView.includes('jobMatchesEnvironmentFilter'), 'Job View must not re-filter environments in JavaScript.');
assert(!deleteView.includes("'/doDelete'"), 'Delete Job must not delete Jenkins jobs directly from the browser.');
assert(!jobList.includes("'/doDelete'"), 'Job List must not delete Jenkins jobs directly from the browser.');
assert(deleteController.includes('jobMatchesRequestedEnvironment'), 'Backend deletion must verify the requested environment.');
assert(deleteController.includes("$this->jenkinsEnvironmentFromJobConfig($jobName)"), 'Backend deletion must inspect Jenkins configuration.');
assert(jenkinsProxy.includes("preg_match('#(?:^|/)doDelete"), 'The generic Jenkins proxy must not bypass scoped backend deletion.');
assert(jenkinsProxy.includes('$status = $this->jenkinsExecutorMonitorStatus($requestedEnvironment)'), 'Executor monitoring must request backend environment scoping.');
assert(jenkinsProxy.includes('$this->includeConfiguredContextEnvironments($status, $requestedEnvironment)'), 'Configured executor placeholders must preserve the requested backend scope.');
assert(baseController.includes('scopeJenkinsExecutorMonitorStatus($slotStatus, $environment)'), 'Executor rows must be scoped before the backend response is returned.');
assert(baseController.includes('$this->checkJenkinsEnvironmentSlotsForBuildRequest($path, $body)'), 'JobSeeker build requests must enforce environment slot limits.');
assert(baseController.includes('jenkinsOnlineEnvironmentAgentCapacity($environment)'), 'Agent routing must inspect matching online environment capacity.');
assert(!executorView.includes('scopedRows') && !executorView.includes('scopedEnvironments'), 'Executor monitoring must not filter environment rows in JavaScript.');
assert(tmfController.includes("$environment = array($globalEnvironment)"), 'TMF form filters must not broaden the global backend scope.');
assert(tmfController.includes('$this->jobSeekerEnvironmentPreference()'), 'TMF must use the current user environment preference when the URL has no filter.');
assert(dashboardController.includes('$this->jobSeekerEnvironmentPreference()'), 'Dashboard queries must default to the current user environment preference.');
assert(contextController.includes('listContexts($selectedEnvironment)'), 'Context Details must query its environment in the backend.');
assert(contextController.includes("The target context environment is outside the current backend scope."), 'Context creation and updates must reject environments outside the backend scope.');
assert(contextController.includes("'api/json?tree='.rawurlencode($tree)"), 'Environment Deployment must encode its nested Jenkins tree query.');
assert(contextController.includes("$tree = 'jobs['.$fields.',jobs['.$fields.',jobs['.$fields.']]]';"), 'Environment Deployment must keep a balanced three-level Jenkins tree expression.');
assert(contextController.includes('public function promotionJobs()'), 'Environment Deployment must load source workloads through a backend-filtered endpoint.');
assert(!promotionView.includes('sourceJobMatchesEnvironment'), 'Environment Deployment must not filter source workloads in JavaScript.');
assert(contextModel.includes('applyEnvironmentFilter($environment)'), 'Context model queries must apply the environment filter.');
assert(contextModel.includes("$this->db->select('cd.IsActive')"), 'Context active counts must qualify the joined IsActive column.');
assert(dataAssetsController.includes('assetMatchesSelectedEnvironment'), 'Data Asset row actions must enforce their backend environment scope.');
assert(dataAssetsModel.includes("array_merge($this->environmentFilterValues($environment), array('ALL'))"), 'Data Asset lists must include only the backend environment and shared fallback.');
assert(connectorController.includes('connectorMatchesSelectedEnvironment'), 'Connector row actions must enforce their backend environment scope.');
assert(connectorModel.includes("array_merge($this->environmentFilterValues($environment), array('ALL'))"), 'Connector lists must be filtered in the backend.');
assert(pipelineModel.includes("where_in('environment', $this->environmentFilterValues($environment))"), 'Pipeline lists must be filtered in the backend.');
assert(visualizationModel.includes('applyStudioEnvironmentFilter'), 'Insight Studio queries must apply environment filters in the backend.');
assert(contextScript.includes("window.location.href = baseUrl + '?environment='"), 'Context environment changes must reload backend-filtered rows.');
assert(assetScript.includes("window.location.href = baseUrl + '?environment='"), 'Data Asset environment changes must reload backend-filtered rows.');
assert(jobCreationController.includes('$this->jenkinsOnlineEnvironmentAgentCapacity($environment)'), 'New jobs must target only online matching environment agents.');
assert(JSON.parse(runtimeConfig).jenkins.environment_agents_enabled === true, 'Environment-agent routing must default to enabled in runtime config.');
assert(compose.includes('JOBSEEKER_JENKINS_ENVIRONMENT_AGENTS_ENABLED:-true'), 'Compose must enable environment-agent routing by default.');

console.log('Platform isolation and backend filtering tests passed.');
