const fs = require('fs');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

const header = fs.readFileSync('application/views/includes/header.php', 'utf8');
const deleteView = fs.readFileSync('application/views/deleteJob.php', 'utf8');
const jobList = fs.readFileSync('application/views/jobList.php', 'utf8');
const deleteController = fs.readFileSync('application/controllers/DeleteJob.php', 'utf8');
const jenkinsProxy = fs.readFileSync('application/controllers/JenkinsProxy.php', 'utf8');
const tmfController = fs.readFileSync('application/controllers/Tmf.php', 'utf8');
const contextScript = fs.readFileSync('assets/js/context-details.js', 'utf8');
const assetScript = fs.readFileSync('assets/js/data-assets.js', 'utf8');

assert(header.includes("'jobseeker.global.environment.user.' + preferenceUserId"), 'Global environment localStorage must be scoped by user.');
assert(header.includes("'jobseeker_global_environment_user_' + preferenceUserId"), 'Global environment cookies must be scoped by user.');
assert(!deleteView.includes("'/doDelete'"), 'Delete Job must not delete Jenkins jobs directly from the browser.');
assert(!jobList.includes("'/doDelete'"), 'Job List must not delete Jenkins jobs directly from the browser.');
assert(deleteController.includes('jobMatchesRequestedEnvironment'), 'Backend deletion must verify the requested environment.');
assert(deleteController.includes("$this->jenkinsEnvironmentFromJobConfig($jobName)"), 'Backend deletion must inspect Jenkins configuration.');
assert(jenkinsProxy.includes("preg_match('#(?:^|/)doDelete"), 'The generic Jenkins proxy must not bypass scoped backend deletion.');
assert(tmfController.includes("$environment = array($globalEnvironment)"), 'TMF form filters must not broaden the global backend scope.');
assert(tmfController.includes('$this->jobSeekerEnvironmentPreference()'), 'TMF must use the current user environment preference when the URL has no filter.');
assert(contextScript.includes("window.location.href = baseUrl + '?environment='"), 'Context environment changes must reload backend-filtered rows.');
assert(assetScript.includes("window.location.href = baseUrl + '?environment='"), 'Data Asset environment changes must reload backend-filtered rows.');

console.log('Platform isolation and backend filtering tests passed.');
