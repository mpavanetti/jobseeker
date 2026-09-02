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
const driver = read('application/libraries/ComputeDriver.php');
const engine = read('application/libraries/ComputeEngineClient.php');
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
  'data-engineering/spark-jobs/monitor/(:num)',
  'data-engineering/spark-jobs/capacity',
  'data-engineering/spark-jobs/develop',
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
assert(compose.includes('./repository:/jobseeker/src:ro'), 'docker-runtime must mount the repository into the compute engine.');
assert(compose.includes('spark-runtime-builder') && compose.includes('profiles: ["runtimes"]'),
  'A runtimes build profile must exist.');
assert(/FROM apache\/spark:4\.\d/.test(dockerfilePython) && /FROM apache\/spark:4\.\d/.test(dockerfileScala),
  'Spark runtime images must start from Apache Spark 4.x.');

// --- iteration 2: monitoring, capacity, scalable workers, develop, layout ---
const editorLib = read('application/libraries/OpenVsCodeWorkspace.php');
assert(engine.includes('function containerStats'), 'Engine client must expose containerStats (ported from DockerMonitoring).');
assert(engine.includes('function engineCapacity') && engine.includes('function containerReservation'),
  'Engine client must expose host capacity + per-container reservation.');
assert(driver.includes('function clusterStats') && driver.includes('function capacitySnapshot'),
  'Driver must expose clusterStats + capacitySnapshot.');
assert(driver.includes('/dev/tcp/master/7077'), 'Driver must wait for the master before spark-submit (readiness gate).');
assert(driver.includes("'spark.executor.memory'") && driver.includes("'spark.executor.cores'"),
  'Driver must size executors to the worker so the master can place them.');
assert(driver.includes('$workspaceBind'), 'Driver binds the job source on master and every worker, not just the driver.');
assert(orchestrator.includes('admitToHost') && orchestrator.includes('function runStats'),
  'Orchestrator must do host admission control and expose runStats.');
assert(orchestrator.includes('$workerOverride'), 'Spark run must accept a per-run worker override.');
assert(controllerJobs.includes('function monitor(') && controllerJobs.includes('function develop(') && controllerJobs.includes('function capacity('),
  'SparkJobs must expose monitor / develop / capacity endpoints.');
assert(controllerJobs.includes("$this->input->post('workers')"), 'SparkJobs::run must read the workers override.');
assert(editorLib.includes('function folderUrl') && editorLib.includes('function ensureRunning'),
  'OpenVsCodeWorkspace must map a repo folder to an editor URL and manage the container.');
[read('application/views/sparkClusters.php'), read('application/views/sparkJobs.php')].forEach((v) => {
  const head = v.slice(v.indexOf('content-header'), v.indexOf('</section>'));
  assert(!/compute-toolbar/.test(head), 'The toolbar must not live inside AdminLTE .content-header (breadcrumb overlap).');
});

// --- iteration 3: Spark jobs are Jenkins jobs, authored in Create Job ---
const sparkRuntime = read('application/controllers/SparkRuntime.php');
const sparkJenkinsTrait = read('application/controllers/concerns/SparkJenkinsTrait.php');
const jobCreation = read('application/controllers/JobCreation.php');
const config = read('application/config/config.php');

assert(/spark-runtime\/trigger/.test(routes) && /spark-runtime\/status/.test(routes) && /spark-runtime\/logs/.test(routes),
  'Agent-callable spark-runtime routes must be registered.');
assert(/spark-runtime\/trigger/.test(config), 'spark-runtime/trigger must be CSRF-excluded (bearer-token auth).');
assert(sparkRuntime.includes('JOBSEEKER_SPARK_TRIGGER_TOKEN') && sparkRuntime.includes('hash_equals'),
  'SparkRuntime must bearer-token authenticate like ConnectorRuntime.');
assert(sparkRuntime.includes('orchestrator->advance') && /function logs\(/.test(sparkRuntime),
  'SparkRuntime::logs must advance the run so the Jenkins poll loop drives it.');
assert(sparkJenkinsTrait.includes('$JOBSEEKER_SPARK_TRIGGER_URL/trigger') &&
  sparkJenkinsTrait.includes('#!/bin/sh') && sparkJenkinsTrait.includes('trap cleanup INT TERM'),
  'The Jenkins runner must call SparkRuntime /trigger, with a shebang and an abort trap.');
assert(sparkJenkinsTrait.includes('hudson.triggers.TimerTrigger') && sparkJenkinsTrait.includes('ParametersDefinitionProperty'),
  'The generated Spark Jenkins job must support a schedule spec and an ENVIRONMENT parameter.');
assert(jobCreation.includes('use SparkJenkinsTrait') && jobCreation.includes('function saveSparkJob') &&
  jobCreation.includes('function deleteSparkJob') && jobCreation.includes("registerJobInfo"),
  'Create Job must author Spark jobs (save/delete) and register them in job_info.');
assert(model.includes('function reapStaleRuns') === false && orchestrator.includes('function reapStaleRuns'),
  'The orchestrator must reap stale runs (dead Jenkins build / closed tab).');
assert(model.includes('function getJobByJenkinsName') && model.includes('function getRunByBuild'),
  'The model must resolve a Spark job + run from a Jenkins job name and build number.');
assert(controllerJobs.includes('function monitorByBuild') && driver.includes('function sparkMasterState') &&
  engine.includes('function exec('),
  'Job Execution must get a build-scoped monitor with Spark master state (scraped via container exec).');

// --- browser scripts parse -------------------------------------
const clusterScripts = parseInlineScripts('application/views/sparkClusters.php');
const activityScripts = parseInlineScripts('application/views/sparkJobs.php');
assert(clusterScripts.length >= 1 && activityScripts.length >= 1, 'Spark views must ship an inline controller script.');
assert(activityScripts.join('\n').includes('data-engineering/spark-jobs/activity'),
  'The Spark Activity dashboard must poll the activity feed.');
assert(activityScripts.join('\n').includes('data-engineering/spark-jobs/logs/'),
  'The Spark Activity dashboard must stream the driver log in the run drawer.');
new (require('vm').Script)(read('assets/js/spark-job-authoring.js'));
new (require('vm').Script)(read('assets/js/spark-execution-monitor.js'));

// --- iteration 4: Create Job panel cleanup + persistent clusters + notebook ---
const jobCreationView = read('application/views/jobCreation.php');
const sparkAuthoring = read('assets/js/spark-job-authoring.js');
const clustersView = read('application/views/sparkClusters.php');
const nginxConf = read('nginx/default.conf');
const dockerfileNotebook = read('docker/spark/Dockerfile.spark-4.0-python-notebook');

// Panel cleanup: environment follows the page selector, no Schedule field, no
// dedicated Save / New-clear buttons (the left-panel buttons drive the save).
assert(!/id="sparkJobCron"/.test(jobCreationView), 'Spark panel must not carry its own Schedule (cron) field.');
assert(!/id="sparkJobSaveBtn"/.test(jobCreationView) && !/id="sparkJobResetBtn"/.test(jobCreationView),
  'Spark panel must not have its own Save / New-clear buttons.');
assert(/id="sparkJobEnvironmentLabel"/.test(jobCreationView) && !/id="sparkJobEnvironment"[^>]*<option/.test(jobCreationView),
  'Spark panel environment must be a read-only label fed by the page selector.');
assert(!/schedule_cron/.test(sparkAuthoring), 'spark-job-authoring.js must not send schedule_cron.');
assert(/pageEnv\(\)/.test(sparkAuthoring) && /#environment'\)\.on\('change'/.test(sparkAuthoring),
  'spark-job-authoring.js must derive the environment from #environment.');
assert(/data:\s*\{ id: id, inline_code:/.test(sparkAuthoring),
  'Develop in VS Code must flush the editor buffer (inline_code) to the workspace.');
assert(!/'schedule_cron'\s*=>/.test(jobCreation), 'saveSparkJob must not write schedule_cron (Schedule screen owns it).');

// New routes for the persistent-cluster lifecycle.
['data-engineering/spark-clusters/start', 'data-engineering/spark-clusters/stop',
 'data-engineering/spark-clusters/status/(:num)'].forEach((route) => {
  assert(routes.includes("$route['" + route + "']"), 'Missing route: ' + route);
});

// Driver: persistent provisioning, key-scoped teardown, driver-only release, and
// published ports + a JupyterLab container.
['provisionPersistentCluster', 'teardownByKey', 'removeSparkDriver'].forEach((method) => {
  assert(new RegExp('function ' + method + '\\b').test(driver), 'DockerComputeDriver missing ' + method);
  assert(new RegExp('function ' + method + '\\b').test(k8sDriver), 'KubernetesComputeDriver missing ' + method + ' (k8s seam incomplete)');
});
assert(driver.includes('PortBindings') && driver.includes('ExposedPorts'),
  'Driver must publish master / Jupyter ports for persistent clusters.');
assert(driver.includes("'jupyter', 'lab'") && driver.includes("-jupyter"),
  'Persistent provisioning must run a JupyterLab container on the cluster network.');
assert(/ServerApp\.token=/.test(driver), 'The JupyterLab container must be token-protected.');

// Orchestrator: lifecycle + idle reaper + a persistent-backed run frees only its
// driver (never tears the shared cluster down).
['startPersistent', 'stopPersistent', 'persistentStats', 'reapIdlePersistentClusters',
 'startOnPersistent', 'releaseRunContainers'].forEach((fn) => {
  assert(new RegExp('function ' + fn + '\\b').test(orchestrator), 'Orchestrator missing ' + fn);
});
assert(/persistent_cluster_id[\s\S]*removeSparkDriver/.test(orchestrator) || /removeSparkDriver[\s\S]*persistent_cluster_id/.test(orchestrator),
  'A run bound to a persistent cluster must release only its driver.');
assert(orchestrator.includes('JOBSEEKER_SPARK_PERSISTENT_MAX') && orchestrator.includes('JOBSEEKER_SPARK_PERSISTENT_IDLE_MINUTES'),
  'Orchestrator must honour the persistent-cluster limit + idle timeout envs.');
assert(orchestrator.includes("lifecycle === 'persistent'") || orchestrator.includes("lifecycle !== 'persistent'"),
  'Orchestrator must branch on the cluster lifecycle.');

// Model + schema.
assert(model.includes('spark_cluster_instances') && dbSetup.includes('`spark_cluster_instances`'),
  'spark_cluster_instances must exist in the model and db_setup.sql.');
['getClusterInstance', 'upsertClusterInstance', 'idlePersistentInstances', 'runningPersistentInstances', 'touchClusterInstance'].forEach((fn) => {
  assert(new RegExp('function ' + fn + '\\b').test(model), 'Model missing ' + fn);
});
assert(/ADD `lifecycle`/.test(model) && dbSetup.includes('`lifecycle` varchar(16)'),
  'spark_clusters must gain a lifecycle column (job | persistent).');
assert(/ADD `persistent_cluster_id`/.test(model) && dbSetup.includes('`persistent_cluster_id`'),
  'spark_job_runs must record which persistent cluster a run used.');

// Controller: lifecycle endpoints are POST + manager gated; save() takes lifecycle.
assert(/function startCluster\(\)\s*\{[\s\S]*?persistentClusterOr422/.test(controllerClusters) &&
  /function stopCluster\(\)\s*\{[\s\S]*?persistentClusterOr422/.test(controllerClusters),
  'SparkClusters start/stop must go through the manager + POST guard.');
assert(controllerClusters.includes("post('lifecycle')") && controllerClusters.includes("'lifecycle' => \$lifecycle"),
  'SparkClusters::save must persist the lifecycle.');
assert(controllerJobs.includes('function persistentClusterView') && controllerJobs.includes('spark-persist/'),
  'SparkJobs::develop must wire the workspace to a running persistent cluster.');

// View: mode selector + Start/Stop controls.
assert(clustersView.includes('name="lifecycle"') && /cluster-toggle/.test(clustersView),
  'Compute screen must offer a mode selector and a Start/Stop toggle.');
assert(clustersView.includes('spark-clusters/status/'), 'Compute screen must poll persistent cluster status.');

// --- iteration 5: Databricks-style rework (inline monitor, notebook-native jobs) ---
['data-engineering/spark-clusters/overview', 'data-engineering/spark-clusters/restart',
 'data-engineering/spark-clusters/notebook', 'data-engineering/spark-jobs/source/(:num)'].forEach((route) => {
  assert(routes.includes("$route['" + route + "']"), 'Missing route: ' + route);
});

// Compute screen: the live monitor is an inline detail row (never a body-level
// sibling that escapes the AdminLTE content wrapper), driven by ONE timer.
assert(!/id="clusterStatusRoot"/.test(clustersView), 'The detached #clusterStatusRoot monitor panel must be gone.');
assert(/compute-detail-row/.test(clustersView) && /compute-menu/.test(clustersView),
  'Compute screen must render an inline detail row + a row action menu.');
assert(/window\.__sparkMon/.test(clustersView) && /clearInterval\(MON\.timer\)/.test(clustersView),
  'The Compute monitor must use a single, cleared interval (no timer leak).');
assert(read('assets/dist/css/compute.css').includes('.compute-detail-row'),
  'compute.css must style the inline monitor detail row.');

// Two job modes: interactive (All-Purpose) vs batch, notebook-native authoring.
assert(/name="sparkJobMode"/.test(jobCreationView) && /id="sparkJobSourcePreview"/.test(jobCreationView),
  'Create Job Spark panel must have an Interactive/Batch mode and a read-only job.py preview.');
assert(/id="sparkJobInlineCode"[^>]*style="display:none"/.test(jobCreationView),
  'The inline PySpark <textarea> must be a hidden buffer, not a visible editor.');
assert(/spark_jobs`?\s*ADD `mode`/.test(model) || /ADD `mode` varchar/.test(model),
  'spark_jobs must gain a mode column.');
assert(/`mode` varchar\(16\)/.test(dbSetup), 'db_setup.sql must define spark_jobs.mode.');
assert(/mode === 'interactive'[\s\S]{0,200}lifecycle !== 'persistent'/.test(controllerJobs) &&
  /mode === 'interactive'[\s\S]{0,200}lifecycle !== 'persistent'/.test(jobCreation),
  'Interactive jobs must be rejected on a non-All-Purpose cluster (both save paths).');

// Coherent OpenVSCode workspace: SparkWorkspace scaffolds job.py + *.code-workspace
// + notebook.ipynb, and there is no Dockerfile / devcontainer dead end.
const sparkWorkspaceLib = read('application/libraries/SparkWorkspace.php');
assert(/function scaffold\(/.test(sparkWorkspaceLib) && sparkWorkspaceLib.includes('.code-workspace') &&
  sparkWorkspaceLib.includes('notebook.ipynb') && sparkWorkspaceLib.includes('job.py'),
  'SparkWorkspace must scaffold job.py + notebook.ipynb + a .code-workspace.');
assert(!sparkWorkspaceLib.includes("'.devcontainer/") && !/=>\s*"# JobSeeker Spark dev container/.test(sparkWorkspaceLib),
  'The Spark workspace must not write a Dockerfile / devcontainer dead end.');
assert(/workspace->scaffold\(/.test(controllerJobs) && !/\.devcontainer/.test(controllerJobs),
  'SparkJobs::develop must delegate to SparkWorkspace and drop the devcontainer.');
assert(/connect\.py/.test(read('application/libraries/SparkClusterOrchestrator.php')) &&
  /inline\/'\.\$key\.'\/job\.py/.test(read('application/libraries/SparkClusterOrchestrator.php')),
  'The orchestrator must run inline/<key>/job.py and drop a fallback connect.py.');

// Spark Activity: All-Purpose strip + run duration.
assert(/function allPurposeView\(/.test(controllerJobs) && /duration_seconds/.test(controllerJobs),
  'Spark Activity feed must include the All-Purpose strip and per-run duration.');
assert(/allPurposeStrip/.test(activityScripts.join('\n')) && /spark-clusters\/notebook/.test(activityScripts.join('\n')),
  'Spark Activity must render the All-Purpose strip with an Open-notebook action.');

// nginx proxy for the published Jupyter / Spark UI ports.
assert(/location\s*~\s*"?\^\/spark-persist\//.test(nginxConf) && /docker-runtime:\$sp_port/.test(nginxConf) &&
  /18\[0-9\]\{3\}/.test(nginxConf),
  'nginx must proxy /spark-persist/<port>/ to the engine, port-range constrained.');

// Notebook runtime image.
assert(/FROM jobseeker\/spark-runtime:4\.0\.0-python\b/.test(dockerfileNotebook) && /jupyterlab/.test(dockerfileNotebook),
  'The notebook runtime image must layer JupyterLab on the python runtime.');
assert(compose.includes('Dockerfile.spark-4.0-python-notebook') &&
  compose.includes('JOBSEEKER_SPARK_PERSISTENT_MAX') && compose.includes('JOBSEEKER_SPARK_PERSISTENT_PORT_BASE'),
  'docker-compose must build the notebook image and pass the persistent-cluster envs.');

console.log('Spark compute tests passed.');
