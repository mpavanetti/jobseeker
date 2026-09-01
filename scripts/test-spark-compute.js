'use strict';

// Static guard rails for the Spark side of the Data Engineering plane. No live
// Docker engine: this parses the browser scripts and asserts the controller /
// orchestrator / driver keep their safety contracts.

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const read = (rel) => fs.readFileSync(path.join(root, rel), 'utf8');

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function parseInlineScripts(viewRelPath) {
  const view = read(viewRelPath);
  const scripts = [];
  const re = /<script>([\s\S]*?)<\/script>/g;
  let match;
  while ((match = re.exec(view)) !== null) {
    const body = match[1].replace(/<\?php[\s\S]*?\?>/g, '"__php__"');
    new vm.Script(body, { filename: viewRelPath + '#' + scripts.length });
    scripts.push(body);
  }
  return scripts;
}

const routes = read('application/config/routes.php');
const header = read('application/views/includes/header.php');
const controllerClusters = read('application/controllers/SparkClusters.php');
const controllerJobs = read('application/controllers/SparkJobs.php');
const model = read('application/models/SparkCompute_model.php');
const engine = read('application/libraries/ComputeEngineClient.php');
const driver = read('application/libraries/ComputeDriver.php');
const k8sDriver = read('application/libraries/KubernetesComputeDriver.php');
const orchestrator = read('application/libraries/SparkClusterOrchestrator.php');
const compose = read('docker-compose.yml');
const dbSetup = read('db_setup.sql');
const dockerfilePython = read('docker/spark/Dockerfile.spark-4.0-python');
const dockerfileScala = read('docker/spark/Dockerfile.spark-4.0-scala');

// --- routes + navigation -------------------------------------------------
[
  'data-engineering/spark-clusters',
  'data-engineering/spark-clusters/save',
  'data-engineering/spark-clusters/delete',
  'data-engineering/spark-jobs',
  'data-engineering/spark-jobs/run',
  'data-engineering/spark-jobs/status/(:num)',
  'data-engineering/spark-jobs/logs/(:num)',
  'data-engineering/spark-jobs/cancel',
].forEach((route) => {
  assert(routes.includes("$route['" + route + "']"), 'Missing route: ' + route);
});
assert(header.includes('Data Engineering') && header.includes('data-engineering/spark-jobs'),
  'Sidebar must expose the Data Engineering section.');
assert(/\$compute_enabled/.test(header) && header.includes("role == ROLE_ADMIN || \$role == ROLE_MANAGER"),
  'Data Engineering nav must be role-gated and honour the compute toggle.');

// --- controller safety -------------------------------------------------
[controllerClusters, controllerJobs].forEach((source) => {
  assert(source.includes("method(TRUE) !== 'POST'"), 'Mutating endpoints must reject non-POST.');
  assert(source.includes('ROLE_ADMIN') && source.includes('ROLE_MANAGER'), 'Endpoints must be role-gated.');
  assert(source.includes("empty($this->global['compute_enabled'])"), 'Controllers must honour the compute toggle.');
});
assert(controllerJobs.includes('requireManagerPost') && /public function run\(\)\s*\{\s*if \(! \$this->requireManagerPost/.test(controllerJobs),
  'SparkJobs::run must be POST + manager gated.');
assert(controllerJobs.includes('> 200000'), 'Inline PySpark code must have a size ceiling.');
assert(/preg_match\('#\^\(jobs\|inline\)\//.test(controllerJobs), 'Repository entry points must be constrained to jobs/ or inline/.');

// --- no docker CLI shelling from PHP ---------------------------------
[engine, driver, orchestrator, controllerJobs, controllerClusters, model].forEach((source) => {
  assert(!/\b(shell_exec|proc_open|passthru|popen)\s*\(/.test(source), 'PHP compute code must not shell out.');
  assert(!/\bexec\s*\(\s*['"][^'"]*docker/.test(source), 'PHP compute code must not exec the docker CLI.');
});
assert(engine.includes("in_array($method, array('GET', 'POST', 'DELETE')"), 'Engine client speaks only GET/POST/DELETE.');

// --- driver contract + teardown -----------------------------------
assert(driver.includes('abstract class ComputeDriver'), 'ComputeDriver must be abstract.');
[
  'provisionSparkCluster', 'submitSparkJob', 'pollSparkRun', 'fetchSparkLogs', 'teardownSpark',
  'runMlJob', 'pollMlRun', 'fetchMlLogs', 'teardownMl',
].forEach((method) => {
  assert(new RegExp('function ' + method + '\\b').test(driver), 'DockerComputeDriver missing ' + method);
  assert(new RegExp('function ' + method + '\\b').test(k8sDriver), 'KubernetesComputeDriver missing ' + method + ' (k8s seam incomplete)');
});
assert(driver.includes('spark://master:7077'), 'Spark driver must submit against the cluster master.');
assert(driver.includes("'/opt/spark/bin/spark-submit'"), 'Spark run must use spark-submit.');
assert(/sweepRun/.test(driver) && driver.includes('LABEL_RUN'), 'Teardown must sweep every container by run label.');
assert(driver.includes('removeNetwork'), 'Spark teardown must remove the per-run network.');
assert(k8sDriver.includes('not implemented') || k8sDriver.includes('notImplemented'),
  'Kubernetes driver must fail loudly until implemented.');

// --- orchestrator ------------------------------------------------
assert(orchestrator.includes('activeSparkRunCount') && orchestrator.includes('maxConcurrentRuns'),
  'Orchestrator must cap concurrent runs.');
assert(orchestrator.includes('runTimeoutSeconds') && orchestrator.includes("'TIMED_OUT'"),
  'Orchestrator must time runs out.');
assert(orchestrator.includes('teardownSpark'), 'Orchestrator must always tear the cluster down.');
assert(/jobseeker\/spark-runtime/.test(driver) === false, 'Driver must not hardcode a runtime image (comes from the catalogue).');

// --- schema + compose + images ---------------------------------
['spark_runtimes', 'spark_clusters', 'spark_jobs', 'spark_job_runs'].forEach((table) => {
  assert(dbSetup.includes('`' + table + '`') && model.includes('`' + table + '`'),
    'Table ' + table + ' must exist in db_setup.sql and the model.');
});
assert(dbSetup.includes("'spark-4.0-python'") && dbSetup.includes("'jobseeker/spark-runtime'"),
  'db_setup.sql must seed the Spark runtime catalogue.');
assert(compose.includes('./repository/spark:/jobseeker/spark:ro'), 'docker-runtime must mount the Spark job source read-only.');
assert(compose.includes('spark-runtime-builder') && compose.includes('profiles: ["runtimes"]'),
  'A runtimes build profile must exist.');
assert(/FROM apache\/spark:4\.\d/.test(dockerfilePython) && /FROM apache\/spark:4\.\d/.test(dockerfileScala),
  'Spark runtime images must start from Apache Spark 4.x.');

// --- browser scripts parse -------------------------------------
const clusterScripts = parseInlineScripts('application/views/sparkClusters.php');
const jobScripts = parseInlineScripts('application/views/sparkJobs.php');
assert(clusterScripts.length >= 1 && jobScripts.length >= 1, 'Spark views must ship an inline controller script.');
assert(jobScripts.join('\n').includes('data-engineering/spark-jobs/status/'), 'Spark job view must poll run status.');
assert(jobScripts.join('\n').includes('TERMINAL'), 'Spark job view must stop polling on terminal runs.');

console.log('Spark compute tests passed.');
