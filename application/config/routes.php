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

# Data Engineering : Spark job clusters and ML runtimes

$route['data-engineering/spark-clusters'] = "SparkClusters/index";
$route['data-engineering/spark-clusters/list'] = "SparkClusters/listClusters";
$route['data-engineering/spark-clusters/runtimes'] = "SparkClusters/runtimes";
$route['data-engineering/spark-clusters/get/(:num)'] = "SparkClusters/get/$1";
$route['data-engineering/spark-clusters/save'] = "SparkClusters/save";
$route['data-engineering/spark-clusters/delete'] = "SparkClusters/delete";

$route['data-engineering/spark-jobs'] = "SparkJobs/index";
$route['data-engineering/spark-jobs/list'] = "SparkJobs/listJobs";
$route['data-engineering/spark-jobs/samples'] = "SparkJobs/samples";
$route['data-engineering/spark-jobs/get/(:num)'] = "SparkJobs/get/$1";
$route['data-engineering/spark-jobs/save'] = "SparkJobs/save";
$route['data-engineering/spark-jobs/delete'] = "SparkJobs/delete";
$route['data-engineering/spark-jobs/run'] = "SparkJobs/run";
$route['data-engineering/spark-jobs/status/(:num)'] = "SparkJobs/status/$1";
$route['data-engineering/spark-jobs/logs/(:num)'] = "SparkJobs/logs/$1";
$route['data-engineering/spark-jobs/monitor/(:num)'] = "SparkJobs/monitor/$1";
$route['data-engineering/spark-jobs/capacity'] = "SparkJobs/capacity";
$route['data-engineering/spark-jobs/develop'] = "SparkJobs/develop";
$route['data-engineering/spark-jobs/cancel'] = "SparkJobs/cancel";

$route['data-engineering/ml-runtimes'] = "MlRuntimes/index";
$route['data-engineering/ml-runtimes/list'] = "MlRuntimes/listRuntimes";
$route['data-engineering/ml-runtimes/get/(:num)'] = "MlRuntimes/get/$1";
$route['data-engineering/ml-runtimes/save'] = "MlRuntimes/save";
$route['data-engineering/ml-runtimes/delete'] = "MlRuntimes/delete";

$route['data-engineering/ml-jobs'] = "MlJobs/index";
$route['data-engineering/ml-jobs/list'] = "MlJobs/listJobs";
$route['data-engineering/ml-jobs/samples'] = "MlJobs/samples";
$route['data-engineering/ml-jobs/get/(:num)'] = "MlJobs/get/$1";
$route['data-engineering/ml-jobs/save'] = "MlJobs/save";
$route['data-engineering/ml-jobs/delete'] = "MlJobs/delete";
$route['data-engineering/ml-jobs/run'] = "MlJobs/run";
$route['data-engineering/ml-jobs/status/(:num)'] = "MlJobs/status/$1";
$route['data-engineering/ml-jobs/logs/(:num)'] = "MlJobs/logs/$1";
$route['data-engineering/ml-jobs/monitor/(:num)'] = "MlJobs/monitor/$1";
$route['data-engineering/ml-jobs/capacity'] = "MlJobs/capacity";
$route['data-engineering/ml-jobs/develop'] = "MlJobs/develop";
$route['data-engineering/ml-jobs/cancel'] = "MlJobs/cancel";




/* End of file routes.php */
/* Location: ./application/config/routes.php */
