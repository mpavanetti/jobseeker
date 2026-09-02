<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/

$route['default_controller'] = "login";
$route['404_override'] = 'error_404';
$route['translate_uri_dashes'] = FALSE;


/*********** USER DEFINED ROUTES *******************/

$route['loginMe'] = 'login/loginMe';
$route['dashboard'] = 'Dashboard';
$route['dashboard/overview'] = 'Dashboard/overview';
$route['docker-monitoring'] = 'DockerMonitoring/index';
$route['docker-monitoring/snapshot'] = 'DockerMonitoring/snapshot';
$route['docker-monitoring/storage'] = 'DockerMonitoring/storage';
$route['docker-monitoring/reclaim-cache'] = 'DockerMonitoring/pruneCache';
$route['docker-monitoring/jobs'] = 'DockerMonitoring/jobs';
$route['delete-job/jobs'] = 'DeleteJob/deleteJobs';
$route['jenkins/proxy'] = 'JenkinsProxy/proxy';
$route['jenkins/environmentSlots'] = 'JenkinsProxy/environmentSlots';
$route['jenkins/executorMonitor'] = 'JenkinsProxy/executorMonitor';
$route['jenkins/dashboardMetrics'] = 'JenkinsProxy/dashboardMetrics';
$route['jenkins/runningBuilds'] = 'JenkinsProxy/runningBuilds';
$route['jenkins/agentSetupHelper'] = 'JenkinsProxy/agentSetupHelper';
$route['jobExecution/executors'] = 'JobExecution/executors';
$route['logout'] = 'user/logout';
$route['userListing'] = 'user/userListing';
$route['userListing/(:num)'] = "user/userListing/$1";
$route['addNew'] = "user/addNew";
$route['addNewUser'] = "user/addNewUser";
$route['editOld'] = "user/editOld";
$route['editOld/(:num)'] = "user/editOld/$1";
$route['editUser'] = "user/editUser";
$route['deleteUser'] = "user/deleteUser";
$route['profile'] = "user/profile";
$route['profile/(:any)'] = "user/profile/$1";
$route['profileUpdate'] = "user/profileUpdate";
$route['profileUpdate/(:any)'] = "user/profileUpdate/$1";

$route['loadChangePass'] = "user/loadChangePass";
$route['changePassword'] = "user/changePassword";
$route['changePassword/(:any)'] = "user/changePassword/$1";
$route['pageNotFound'] = "user/pageNotFound";
$route['checkEmailExists'] = "user/checkEmailExists";
$route['login-history'] = "user/loginHistoy";
$route['login-history/(:num)'] = "user/loginHistoy/$1";
$route['login-history/(:num)/(:num)'] = "user/loginHistoy/$1/$2";

$route['forgotPassword'] = "login/forgotPassword";
$route['resetPasswordUser'] = "login/resetPasswordUser";
$route['resetPasswordConfirmUser'] = "login/resetPasswordConfirmUser";
$route['resetPasswordConfirmUser/(:any)'] = "login/resetPasswordConfirmUser/$1";
$route['resetPasswordConfirmUser/(:any)/(:any)'] = "login/resetPasswordConfirmUser/$1/$2";
$route['createPasswordUser'] = "login/createPasswordUser";
$route['setup/testJenkinsApi'] = "Setup/testJenkinsApi";

#Input and Output Settings

$route['data-assets'] = "DataAssets/index";
$route['data-assets/save'] = "DataAssets/save";
$route['data-assets/delete'] = "DataAssets/delete";
$route['data-assets/download/(:num)'] = "DataAssets/download/$1";
$route['data-assets/preview/(:num)'] = "DataAssets/preview/$1";
$route['data-assets/catalog'] = "DataAssets/catalog";
$route['connector-runtime'] = "ConnectorRuntime/index";
$route['dbSettings/testConnector'] = "DbSettings/testConnector";
$route['jobCreation/scanDependencies'] = "JobCreation/scanDependencies";
$route['jobCreation/testDependencies'] = "JobCreation/testDependencies";
$route['jobCreation/validateSchedule'] = "JobCreation/validateSchedule";
$route['jobView/dependencies'] = "JobView/dependencies";
$route['jobExecution/dependencies'] = "JobExecution/dependencies";
$route['pipelines'] = "Pipelines/index";
$route['pipelines/jobs'] = "Pipelines/jobs";
$route['pipelines/validate'] = "Pipelines/validateGraph";
$route['pipelines/validateSchedule'] = "Pipelines/validateSchedule";
$route['pipelines/save'] = "Pipelines/save";
$route['pipelines/deploy'] = "Pipelines/deploy";
$route['pipelines/run'] = "Pipelines/run";
$route['pipelines/status/(:num)'] = "Pipelines/status/$1";
$route['pipelines/stop'] = "Pipelines/stop";
$route['pipelines/delete'] = "Pipelines/delete";
$route['deleteJobInput'] = "JobsTable/delete";
$route['addNewJob'] = "JobsTable/addNewJob";
$route['addNewJobInsert'] = "JobsTable/addNewJobInsert";
$route['addNewJobEdit'] = "JobsTable/editJob";
$route['deleteOutput'] = "JobsTable/deleteOutput";

# Generic Settings

$route['AddSettings'] = "GenericSettings/AddSettings";
$route['InsertGenericSettings'] = "GenericSettings/insertGenericSetting";
$route['DeleteGenericSettings'] = "GenericSettings/deleteGenericSetting";
$route['updateGenericSetting'] = "GenericSettings/updateGenericSetting";




# ---------------------------------------------------------------------------
# Machine Learning platform
# ---------------------------------------------------------------------------

$route['machine-learning'] = 'MlOverview/index';
$route['machine-learning/overview'] = 'MlOverview/index';
$route['machine-learning/overview/pulse'] = 'MlOverview/pulse';

$route['machine-learning/runtimes'] = 'MlRuntimes/index';
$route['machine-learning/runtimes/list'] = 'MlRuntimes/listRuntimes';
$route['machine-learning/runtimes/get/(:num)'] = 'MlRuntimes/get/$1';
$route['machine-learning/runtimes/save'] = 'MlRuntimes/save';
$route['machine-learning/runtimes/delete'] = 'MlRuntimes/delete';

$route['machine-learning/samples'] = 'MlSamples/index';
$route['machine-learning/samples/list'] = 'MlSamples/listSamples';
$route['machine-learning/samples/get/(:num)'] = 'MlSamples/get/$1';
$route['machine-learning/samples/save'] = 'MlSamples/save';
$route['machine-learning/samples/delete'] = 'MlSamples/delete';

$route['machine-learning/datasets'] = 'MlDatasets/index';
$route['machine-learning/datasets/list'] = 'MlDatasets/listDatasets';
$route['machine-learning/datasets/pick'] = 'MlDatasets/pick';
$route['machine-learning/datasets/explore/(:num)'] = 'MlDatasets/explore/$1';
$route['machine-learning/datasets/save'] = 'MlDatasets/save';
$route['machine-learning/datasets/delete'] = 'MlDatasets/delete';
$route['machine-learning/datasets/register-version'] = 'MlDatasets/registerVersion';
$route['machine-learning/datasets/version/(:num)'] = 'MlDatasets/versionProfile/$1';
$route['machine-learning/datasets/preview/(:num)'] = 'MlDatasets/preview/$1';
$route['machine-learning/datasets/compare/(:num)'] = 'MlDatasets/compareVersions/$1';
$route['machine-learning/datasets/download/(:num)'] = 'MlDatasets/download/$1';

$route['machine-learning/jobs'] = 'MlJobs/index';
$route['machine-learning/jobs/list'] = 'MlJobs/listJobs';
$route['machine-learning/jobs/get/(:num)'] = 'MlJobs/get/$1';
$route['machine-learning/jobs/introspect'] = 'MlJobs/introspect';
$route['machine-learning/jobs/save'] = 'MlJobs/save';
$route['machine-learning/jobs/delete'] = 'MlJobs/delete';
$route['machine-learning/jobs/save-as-sample'] = 'MlJobs/saveAsSample';
$route['machine-learning/jobs/develop'] = 'MlJobs/develop';
$route['machine-learning/jobs/workspace/(:num)'] = 'MlJobs/workspaceFile/$1';
$route['machine-learning/jobs/build-image'] = 'MlJobs/buildImage';
$route['machine-learning/jobs/image-status/(:num)'] = 'MlJobs/imageStatus/$1';
$route['machine-learning/jobs/run'] = 'MlJobs/run';
$route['machine-learning/jobs/status/(:num)'] = 'MlJobs/status/$1';
$route['machine-learning/jobs/logs/(:num)'] = 'MlJobs/logs/$1';
$route['machine-learning/jobs/cancel'] = 'MlJobs/cancel';
$route['machine-learning/jobs/capacity'] = 'MlJobs/capacity';

$route['machine-learning/runs'] = 'MlRuns/index';
$route['machine-learning/runs/active'] = 'MlRuns/active';
$route['machine-learning/runs/detail/(:num)'] = 'MlRuns/detail/$1';
$route['machine-learning/runs/status/(:num)'] = 'MlRuns/status/$1';
$route['machine-learning/runs/compare/(:num)'] = 'MlRuns/compare/$1';
$route['machine-learning/runs/register-model'] = 'MlRuns/registerModelFromRun';
$route['machine-learning/experiments/save'] = 'MlRuns/saveExperiment';

$route['machine-learning/models'] = 'MlModels/index';
$route['machine-learning/models/version/(:num)'] = 'MlModels/version/$1';
$route['machine-learning/models/save'] = 'MlModels/saveModel';
$route['machine-learning/models/delete'] = 'MlModels/deleteModel';
$route['machine-learning/models/transition'] = 'MlModels/transition';
$route['machine-learning/models/notes'] = 'MlModels/updateVersionNotes';

$route['machine-learning/monitoring'] = 'MlMonitoring/index';
$route['machine-learning/monitoring/overview'] = 'MlMonitoring/overview';
$route['machine-learning/monitoring/detail/(:num)'] = 'MlMonitoring/detail/$1';
$route['machine-learning/monitoring/save'] = 'MlMonitoring/save';
$route['machine-learning/monitoring/delete'] = 'MlMonitoring/delete';
$route['machine-learning/monitoring/run'] = 'MlMonitoring/run';
$route['machine-learning/monitoring/run-due'] = 'MlMonitoring/runDue';
$route['machine-learning/monitoring/alert/ack'] = 'MlMonitoring/acknowledgeAlert';

# Agent-callable runtime surface (bearer token, CSRF-excluded)
$route['machine-learning/runtime/trigger'] = 'MlRuntime/trigger';
$route['machine-learning/runtime/status/(:any)'] = 'MlRuntime/status/$1';
$route['machine-learning/runtime/logs/(:any)'] = 'MlRuntime/logs/$1';
$route['machine-learning/runtime/cancel'] = 'MlRuntime/cancel';
$route['machine-learning/runtime/heartbeat'] = 'MlRuntime/heartbeat';
$route['machine-learning/runtime/ingest'] = 'MlRuntime/ingest';
$route['machine-learning/runtime/artifact'] = 'MlRuntime/artifact';
$route['machine-learning/runtime/model'] = 'MlRuntime/model';
$route['machine-learning/runtime/dataset'] = 'MlRuntime/dataset';
$route['machine-learning/runtime/resolve-dataset'] = 'MlRuntime/resolveDataset';
$route['machine-learning/runtime/resolve-model'] = 'MlRuntime/resolveModel';
$route['machine-learning/runtime/dataset-download/(:num)'] = 'MlRuntime/datasetDownload/$1';
$route['machine-learning/runtime/model-download/(:num)'] = 'MlRuntime/modelDownload/$1';
$route['machine-learning/runtime/run-due-monitors'] = 'MlRuntime/runDueMonitors';

# Create Job screen integration (authoring form is embedded; it posts to the
# machine-learning/jobs/* endpoints above). This route only bootstraps the tab.
$route['jobCreation/mlJobOptions'] = 'JobCreation/mlJobOptions';

/* End of file routes.php */
/* Location: ./application/config/routes.php */
