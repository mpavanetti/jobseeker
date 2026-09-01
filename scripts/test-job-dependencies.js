const assert = require('assert');
const fs = require('fs');

const scanner = fs.readFileSync('application/libraries/DependencyScanner.php', 'utf8');
const model = fs.readFileSync('application/models/JobDependency_model.php', 'utf8');
const jobCreation = fs.readFileSync('application/controllers/JobCreation.php', 'utf8');
const baseController = fs.readFileSync('application/libraries/BaseController.php', 'utf8');
const jobView = fs.readFileSync('application/controllers/JobView.php', 'utf8');
const jobExecution = fs.readFileSync('application/controllers/JobExecution.php', 'utf8');
const routes = fs.readFileSync('application/config/routes.php', 'utf8');
const clientJs = fs.readFileSync('assets/js/job-dependencies.js', 'utf8');
const creationView = fs.readFileSync('application/views/jobCreation.php', 'utf8');
const viewView = fs.readFileSync('application/views/jobView.php', 'utf8');
const executionView = fs.readFileSync('application/views/jobExecution.php', 'utf8');

// --- Scanner ---
assert(scanner.includes('class DependencyScanner'));
assert(scanner.includes('function scan(') && scanner.includes('function sourcesForJob('));
['CONNECTOR_CALL', 'CONNECTOR_CLI', 'CONNECTOR_ENV', 'ASSET_CALL', 'ASSET_URI'].forEach((c) => {
  assert(scanner.includes("const " + c + " = "), 'DependencyScanner must define ' + c);
});
assert(scanner.includes("'.git', '.venv'"), 'transient directories must be skipped when reading job source');

// --- Model ---
assert(model.includes('class JobDependency_model'));
assert(model.includes('CREATE TABLE IF NOT EXISTS `job_dependencies`'));
['function resolve(', 'function replaceForJob(', 'function recordTestResult(', 'function listForJob(', 'function summaryForJobs('].forEach((m) => {
  assert(model.includes(m), 'JobDependency_model must define ' + m);
});
assert(model.includes("'missing'") && model.includes("'out_of_scope'") && model.includes("'inactive'"), 'light status values must exist');
assert(model.includes('UNIQUE KEY `job_dependencies_row`'));

// --- Endpoints + routes ---
assert(jobCreation.includes('use JenkinsRunnerTrait;'), 'JobCreation must reuse the shared worker test');
assert(jobCreation.includes('public function scanDependencies()'));
assert(jobCreation.includes('public function testDependencies()'));
assert(jobCreation.includes('function persistJobDependencies('));
assert(jobCreation.includes('$this->persistJobDependencies('), 'the map must be persisted on job save');
assert(jobCreation.includes('runConnectorConnectionTest('), 'testDependencies must run the real worker handshake');
assert(baseController.includes('function jobDependencyMap('), 'BaseController must expose a shared dependency map for read-only views');
assert(jobView.includes('public function dependencies()') && jobView.includes('jobDependencyMap('));
assert(jobExecution.includes('public function dependencies()') && jobExecution.includes('jobDependencyMap('));
['jobCreation/scanDependencies', 'jobCreation/testDependencies', 'jobView/dependencies', 'jobExecution/dependencies'].forEach((r) => {
  assert(routes.includes("$route['" + r + "']"), 'route missing: ' + r);
});

// --- Client + views ---
assert(clientJs.includes('window.JobSeekerJobDependencies'));
['render', 'renderChip', 'summaryText', 'scan', 'test', 'load'].forEach((fn) => {
  assert(clientJs.includes(fn + ':'), 'job-dependencies.js must export ' + fn);
});
assert(clientJs.includes("'dbSettings?edit='") && clientJs.includes("'data-assets'"), 'chips must link to the catalog pages');

assert(creationView.includes('assets/js/job-dependencies.js'));
assert(creationView.includes('id="jobDependencyPanel"') && creationView.includes('id="jobDependencyTest"'));
assert(creationView.includes('JobSeekerJobDependencies') && /deps\.scan\(/.test(creationView) && /deps\.test\(/.test(creationView));
assert(creationView.includes('window.jobseekerSavedJobs'), 'the post-create auto test needs the saved job names');

assert(viewView.includes('assets/js/job-dependencies.js'));
assert(viewView.includes("JobSeekerJobDependencies.load('JobView'"));
assert(viewView.includes('Connectors &amp; datasets'));

assert(executionView.includes('assets/js/job-dependencies.js'));
assert(executionView.includes("JobSeekerJobDependencies.load('JobExecution'"));

console.log('Job dependency mapping tests passed.');
