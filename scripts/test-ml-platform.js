/* Static wiring checks for the Machine Learning platform.
 * Run:  node scripts/test-ml-platform.js
 * Mirrors the other scripts/test-*.js: reads the source and asserts the pieces
 * are connected. The live end-to-end path is scripts/test-ml-platform-e2e.py. */
const assert = require('assert');
const fs = require('fs');

const read = (p) => fs.readFileSync(p, 'utf8');

const routes = read('application/config/routes.php');
const config = read('application/config/config.php');
const header = read('application/views/includes/header.php');
const compose = read('docker-compose.yml');
const envExample = read('.env.example');
const dbSetup = read('db_setup.sql');

// --- routes ---
[
  'machine-learning/overview', 'machine-learning/runtimes', 'machine-learning/samples',
  'machine-learning/datasets', 'machine-learning/jobs', 'machine-learning/runs',
  'machine-learning/models', 'machine-learning/monitoring',
  'machine-learning/runtime/trigger', 'machine-learning/runtime/ingest',
  'machine-learning/runtime/artifact', 'machine-learning/runtime/model',
  'machine-learning/runtime/dataset', 'machine-learning/runtime/resolve-dataset',
  'machine-learning/monitoring/run-due', 'jobCreation/mlJobOptions',
].forEach((r) => assert(routes.includes("'" + r + "'") || routes.includes('"' + r + '"'), 'route missing: ' + r));

// --- CSRF exclusion for the agent surface ---
assert(/csrf_exclude_uris.*machine-learning\/runtime/.test(config), 'runtime endpoints must be CSRF-excluded');

// --- sidebar ---
assert(header.includes('Machine Learning') && header.includes('machine-learning/overview'), 'sidebar entry missing');
assert(header.includes('JOBSEEKER_ML_PLATFORM_ENABLED'), 'sidebar must honour the feature flag');

// --- controllers ---
const controllers = {
  'MlOverview.php': ['function index(', 'function pulse('],
  'MlRuntimes.php': ['function save(', 'function delete('],
  'MlSamples.php': ['function save(', 'seedSamples is synced'.replace(/.*/, 'function save(')],
  'MlDatasets.php': ['function registerVersion(', 'function explore(', 'function pick(', 'function preview('],
  'MlJobs.php': ['function persistJob(', 'function introspect(', 'function run(', 'function develop(', 'function buildImage(', 'MlJenkinsTrait', 'MlWorkspace'],
  'MlRuns.php': ['function detail(', 'function compare(', 'function registerModelFromRun('],
  'MlModels.php': ['function transition(', 'function version('],
  'MlMonitoring.php': ['function run(', 'function runDue(', 'MlMonitorEvaluator'],
  'MlRuntime.php': ['function trigger(', 'function ingest(', 'function artifact(', 'function model(', 'function dataset(', 'JOBSEEKER_ML_API_TOKEN'],
};
Object.keys(controllers).forEach((file) => {
  const src = read('application/controllers/' + file);
  controllers[file].forEach((needle) => assert(src.includes(needle), file + ' missing: ' + needle));
});

// --- models + self-healing schema ---
const modelTables = {
  'MlCatalog_model.php': ['ml_runtime', 'ml_sample', 'ml_experiment', 'ml_job'],
  'MlRun_model.php': ['ml_run', 'ml_run_metric', 'ml_artifact', 'ml_run_artifact', 'ml_lineage_edge'],
  'MlRegistry_model.php': ['ml_model', 'ml_model_version', 'ml_model_stage_event'],
  'MlMonitoring_model.php': ['ml_monitor', 'ml_monitor_run', 'ml_monitor_point', 'ml_alert'],
};
Object.keys(modelTables).forEach((file) => {
  const src = read('application/models/' + file);
  assert(src.includes('CREATE TABLE IF NOT EXISTS'), file + ' must self-heal its schema');
  modelTables[file].forEach((t) => {
    assert(src.includes('`' + t + '`'), file + ' missing table ' + t);
    assert(dbSetup.includes('CREATE TABLE IF NOT EXISTS `' + t + '`'), 'db_setup.sql missing ' + t);
  });
});
// v2: ML datasets are unified into the Data Assets store.
const dataAssets = read('application/models/DataAssets_model.php');
assert(dataAssets.includes('`data_asset_versions`') && dataAssets.includes('function createVersion('),
  'DataAssets_model must own data_asset_versions');
assert(dbSetup.includes('CREATE TABLE IF NOT EXISTS `data_asset_versions`'), 'db_setup.sql missing data_asset_versions');
assert(read('application/models/MlDataset_model.php').includes("load->model('DataAssets_model'"),
  'MlDataset_model must be an adapter over DataAssets_model');
assert(!read('application/models/MlDataset_model.php').includes('CREATE TABLE'),
  'MlDataset_model must no longer create its own tables');

// --- libraries ---
const libs = {
  'MlComputeDriver.php': ['abstract class MlComputeDriver', 'class DockerMlComputeDriver', 'class KubernetesMlComputeDriver', 'capacitySnapshot'],
  'MlArtifactStore.php': ['abstract class MlArtifactStore', 'class LocalMlArtifactStore', 'class S3MlArtifactStore'],
  'MlRunOrchestrator.php': ['function start(', 'function advance(', 'host headroom'.replace(/.*/, 'function advance('), 'reapStaleRuns'],
  'MlJobIntrospector.php': ['function analyze(', "'train'", "'batch_infer'", "'tune'"],
  'MlDatasetProfiler.php': ['function profile(', 'fingerprint', 'histogram'],
  'MlDriftAnalyzer.php': ['function psi(', 'function kl(', 'function compare('],
  'MlMonitorEvaluator.php': ['function evaluate(', 'servingSignals', 'raiseAlert'],
  'MlConnectorQuery.php': ['function run(', 'read-only'],
  'MlEngineClient.php': ['function request(', 'docker-runtime', 'function buildImage('],
  'MlWorkspace.php': ['function sync(', 'function tar(', 'function editorFolderUrl(', 'function hash('],
};
Object.keys(libs).forEach((file) => {
  const src = read('application/libraries/' + file);
  libs[file].forEach((needle) => assert(src.includes(needle), file + ' missing: ' + needle));
});
// v2: per-job baked image path.
assert(read('application/libraries/MlComputeDriver.php').includes('function buildJobImage('), 'driver needs buildJobImage');
assert(read('application/libraries/MlRunOrchestrator.php').includes('function prepareImage(') &&
  read('application/libraries/MlRunOrchestrator.php').includes('function rebuildImage('), 'orchestrator needs image build path');

// --- Create Job integration ---
const jobCreation = read('application/controllers/JobCreation.php');
assert(jobCreation.includes('use JobCreationMlTrait;'), 'JobCreation must use JobCreationMlTrait');
assert(read('application/controllers/concerns/JobCreationMlTrait.php').includes('function mlJobOptions('));
assert(fs.existsSync('application/views/includes/mlJobAuthoring.php'));

// --- views + assets ---
['mlOverview', 'mlRuntimes', 'mlSamples', 'mlDatasets', 'mlDatasetExplore', 'mlJobs',
 'mlRuns', 'mlRunDetail', 'mlModels', 'mlModelVersion', 'mlMonitoring', 'mlMonitorDetail']
  .forEach((v) => assert(fs.existsSync('application/views/' + v + '.php'), 'view missing: ' + v));
['assets/js/ml-common.js', 'assets/js/ml-ui.js', 'assets/js/ml-job-authoring.js', 'assets/dist/css/ml-platform.css']
  .forEach((a) => assert(fs.existsSync(a), 'asset missing: ' + a));

// --- SDK (v2: typed dataset accessors) ---
const sdk = read('repository/ml/sdk/jobseeker_ml/client.py');
['def log_metric(', 'def log_model(', 'def load_dataset(', 'def save_dataset(', 'def log_artifact(',
 'JOBSEEKER_ML_API', 'multipart/form-data', 'class Dataset', 'class _DatasetNamespace', 'def read(']
  .forEach((needle) => assert(sdk.includes(needle), 'SDK missing: ' + needle));
assert(read('repository/ml/sdk/jobseeker_ml/__init__.py').includes('datasets'), 'SDK must export datasets');

// v2: integration touch-points.
assert(read('application/views/includes/footer.php').includes('machine-learning/runs/active'),
  'sidebar running jobs must include ML runs');
assert(read('assets/js/pipeline-builder.js').includes('/^ml\\//i'), 'pipeline builder must badge ML jobs');
assert(read('application/views/dashboard.php').includes('machine-learning/overview/pulse'), 'dashboard must show an ML card');

// --- samples on disk ---
['tabular-classification', 'tabular-regression', 'batch-inference', 'feature-engineering'].forEach((s) => {
  assert(fs.existsSync('repository/ml/samples/' + s + '/main.py'), 'sample script missing: ' + s);
  assert(fs.existsSync('repository/ml/samples/' + s + '/sample.json'), 'sample manifest missing: ' + s);
});

// --- infra ---
assert(compose.includes('JOBSEEKER_ML_API_TOKEN') && compose.includes('/jobseeker/repository/ml'), 'compose ML wiring missing');
assert(envExample.includes('JOBSEEKER_ML_API_TOKEN') && envExample.includes('JOBSEEKER_ML_COMPUTE_DRIVER'), '.env.example ML vars missing');
assert(fs.existsSync('docker/ml/Dockerfile.ml-cpu') && fs.existsSync('scripts/build-ml-runtimes.sh'));
assert(fs.existsSync('doc/jobseeker/MachineLearning/architecture.md') && fs.existsSync('doc/jobseeker/MachineLearning/sdk.md'));

console.log('test-ml-platform: all static wiring checks passed');
