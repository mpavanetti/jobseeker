'use strict';

// Static guard rails for the Machine Learning side of the Data Engineering plane.

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
const controllerRuntimes = read('application/controllers/MlRuntimes.php');
const controllerJobs = read('application/controllers/MlJobs.php');
const model = read('application/models/MlCompute_model.php');
const orchestrator = read('application/libraries/MlJobOrchestrator.php');
const driver = read('application/libraries/ComputeDriver.php');
const compose = read('docker-compose.yml');
const dbSetup = read('db_setup.sql');
const dockerfileCpu = read('docker/ml/Dockerfile.ml-cpu');
const dockerfileDl = read('docker/ml/Dockerfile.ml-dl-cpu');
const envCpu = read('docker/ml/env-ml-cpu.yml');

// --- routes + navigation -------------------------------------------------
[
  'data-engineering/ml-runtimes',
  'data-engineering/ml-runtimes/save',
  'data-engineering/ml-runtimes/delete',
  'data-engineering/ml-jobs',
  'data-engineering/ml-jobs/run',
  'data-engineering/ml-jobs/status/(:num)',
  'data-engineering/ml-jobs/logs/(:num)',
  'data-engineering/ml-jobs/monitor/(:num)',
  'data-engineering/ml-jobs/capacity',
  'data-engineering/ml-jobs/develop',
  'data-engineering/ml-jobs/cancel',
].forEach((route) => {
  assert(routes.includes("$route['" + route + "']"), 'Missing route: ' + route);
});
assert(header.includes('data-engineering/ml-runtimes') && header.includes('data-engineering/ml-jobs'),
  'Sidebar must expose the ML screens.');

// --- controller safety -------------------------------------------------
[controllerRuntimes, controllerJobs].forEach((source) => {
  assert(source.includes("method(TRUE) !== 'POST'"), 'Mutating endpoints must reject non-POST.');
  assert(source.includes('ROLE_ADMIN') && source.includes('ROLE_MANAGER'), 'Endpoints must be role-gated.');
  assert(source.includes("empty($this->global['compute_enabled'])"), 'Controllers must honour the compute toggle.');
  assert(!/\b(shell_exec|proc_open|passthru|popen)\s*\(/.test(source), 'ML controllers must not shell out.');
});
assert(controllerJobs.includes('> 200000'), 'Inline code must have a size ceiling.');
assert(/preg_match\('#\^\(jobs\|inline\)\//.test(controllerJobs), 'Repository entry points must be constrained to jobs/ or inline/.');

// --- orchestrator + driver -----------------------------------------
assert(orchestrator.includes('activeMlRunCount') && orchestrator.includes('runTimeoutSeconds'),
  'ML orchestrator must cap concurrency and time out.');
assert(orchestrator.includes('teardownMl'), 'ML orchestrator must always remove the job container.');
assert(driver.includes("'python', '/workspace/'"), 'ML jobs must run python /workspace/<entry>.');
assert(driver.includes("'NetworkMode' => $network") && driver.includes("'none'"),
  'ML jobs must be able to run with no network.');
assert(!/\bexec\s*\(\s*['"][^'"]*docker/.test(orchestrator), 'ML orchestrator must not exec the docker CLI.');

// --- schema + compose + images ---------------------------------
['ml_runtimes', 'ml_jobs', 'ml_job_runs'].forEach((table) => {
  assert(dbSetup.includes('`' + table + '`') && model.includes('`' + table + '`'),
    'Table ' + table + ' must exist in db_setup.sql and the model.');
});
assert(dbSetup.includes("'ml-cpu'") && dbSetup.includes("'jobseeker/ml-runtime'"),
  'db_setup.sql must seed the ML runtime catalogue.');
assert(compose.includes('./repository:/jobseeker/src:ro'), 'docker-runtime must mount the repository into the compute engine.');
assert(compose.includes('ml-runtime-builder'), 'A runtimes build profile must build the ML images.');
assert(/FROM continuumio\/miniconda3/.test(dockerfileCpu) && /FROM continuumio\/miniconda3/.test(dockerfileDl),
  'ML runtime images must be Miniconda based.');
assert(/scikit-learn/.test(envCpu) && /conda env update/.test(dockerfileCpu),
  'The ML CPU image must install its conda environment.');

// --- iteration 2: monitoring, develop, layout ---
const driverSrc2 = read('application/libraries/ComputeDriver.php');
const editorLib2 = read('application/libraries/OpenVsCodeWorkspace.php');
assert(driverSrc2.includes('function mlJobStats'), 'Driver must expose mlJobStats for the ML run monitor.');
assert(orchestrator.includes('function runStats') && orchestrator.includes('capacitySnapshot'),
  'ML orchestrator must expose runStats and check host capacity.');
assert(controllerJobs.includes('function monitor(') && controllerJobs.includes('function develop('),
  'MlJobs must expose monitor and develop endpoints.');
assert(controllerJobs.includes("'/ml/inline/'"), 'MlJobs::develop must materialise the workspace under repository/ml/inline.');
assert(editorLib2.includes('JOBSEEKER_OPENVSCODE_TOKEN') && editorLib2.includes("'folder'") && editorLib2.includes('http_build_query'),
  'OpenVsCodeWorkspace must build a tokenised folder URL.');
const mlHead = read('application/views/mlJobs.php');
assert(!/compute-toolbar/.test(mlHead.slice(mlHead.indexOf('content-header'), mlHead.indexOf('</section>'))),
  'The ML Jobs toolbar must not live inside .content-header.');

// --- browser scripts parse -------------------------------------
const runtimeScripts = parseInlineScripts('application/views/mlRuntimes.php');
const jobScripts = parseInlineScripts('application/views/mlJobs.php');
assert(runtimeScripts.length >= 1 && jobScripts.length >= 1, 'ML views must ship an inline controller script.');
assert(jobScripts.join('\n').includes('data-engineering/ml-jobs/status/'), 'ML job view must poll run status.');

console.log('ML compute tests passed.');
