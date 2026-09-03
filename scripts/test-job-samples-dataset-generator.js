const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const samples = read('application/config/job_samples.php');
const jobView = read('application/views/jobCreation.php');
const jobController = read('application/controllers/JobCreation.php');
const generatorController = read('application/controllers/DatasetGenerator.php');
const generatorModel = read('application/models/DatasetGenerator_model.php');
const generatorView = read('application/views/datasetGenerator.php');
const routes = read('application/config/routes.php');
const header = read('application/views/includes/header.php');
const schema = read('db_setup.sql');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

const shellSamples = (samples.match(/'family'\s*=>\s*'shell'/g) || []).length;
const pythonSamples = (samples.match(/'family'\s*=>\s*'python'/g) || []).length;
assert(shellSamples >= 6, 'The catalog must keep at least six shell examples.');
assert(pythonSamples >= 8, 'The catalog must keep at least eight Python/JobSeeker examples.');
assert(samples.includes("'complexity' => 'simple'") && samples.includes("'complexity' => 'intermediate'") && samples.includes("'complexity' => 'advanced'"), 'Samples must span all supported complexity levels.');
assert((samples.match(/'integrations'\s*=>\s*array\(/g) || []).length === shellSamples + pythonSamples, 'Every sample must declare the platform integrations it demonstrates.');
assert(samples.includes("'files' => array(") && samples.includes('tests/test_rules.py') && samples.includes('tests/test_aggregate.py'), 'The Python catalog must include multi-file tested workspaces.');
assert(samples.includes('from jobseeker import JobSeeker') && samples.includes('tmf.progress'), 'Python templates must exercise the JobSeeker TMF SDK.');
assert(samples.includes('tmf.asset(') && samples.includes('tmf.connector('), 'Python templates must cover governed assets and scoped connectors.');
assert(samples.includes("'id' => 'python-platform-pipeline'") && samples.includes('js.get_context("pipeline_batch_size"') && samples.includes('js.email_metrics('), 'The catalog must include a combined end-to-end platform integration sample.');
assert(/jobseeker-connector\s+(?:test|exec)\s+"\$connector_key"/.test(samples) && samples.includes('jobseeker-asset "$asset_key"'), 'Shell templates must cover the connector and Data Asset runtime helpers together.');
assert(samples.includes("'id' => 'shell-db-connector'") && samples.includes("'id' => 'python-db-connector-inspect'") && samples.includes("'id' => 'python-db-warehouse-etl'"), 'The catalog must include simple and ETL samples for the built-in database connector.');
assert((samples.match(/jobseeker-mariadb/g) || []).length >= 3, 'Database samples must default to the built-in jobseeker-mariadb connector.');
assert((samples.match(/'database'/g) || []).length >= 4, 'Database-connector samples must declare the database integration tag.');
assert(!/set -Eeuo pipefail/.test(samples) && !/done < <\(/.test(samples), 'Shell samples must stay POSIX sh (dash) compatible: no pipefail flag, no process substitution.');

assert(jobController.includes("require APPPATH . 'config/job_samples.php'"), 'JobCreation must load the server-side sample catalog.');
assert(jobView.includes('id="jobSampleModal"') && jobView.includes('function applyJobSample(sample)'), 'Job Creation must expose and apply the sample library.');
assert(jobView.includes('jobSampleFamily') && jobView.includes('jobSampleComplexity') && jobView.includes('jobSampleIntegration') && jobView.includes('jobSampleSearch'), 'The sample library must filter by runtime, complexity, platform integration, and search text.');
assert(jobView.includes('jobSampleIntegrationLabels') && jobView.includes('sample.integrations || []'), 'Sample cards must make their platform integrations visible.');
assert(jobView.includes("database: 'Database'") && jobView.includes('<option value="database">'), 'The sample library must expose the database-connector integration filter.');
assert(jobView.includes("loadPythonInlineFilesPayload({files: sample.files || []"), 'Python samples must be able to load full workspaces.');
assert(jobView.includes("if (sample.docker_image)"), 'Samples must be able to select a Docker image that provides their declared runtime tools.');

// Loading a sample must not discard the runtime the operator already picked.
assert(!/\$\('#pythonRuntimeMode'\)\.val\(sample\.runtime === 'docker' \? 'docker' : 'local'\)/.test(jobView), 'Loading a sample must not reset an operator-selected Docker runtime back to the Jenkins agent.');
assert(/var sampleRequiresDocker = sample\.runtime === 'docker' \|\| !! sample\.use_dockerfile;/.test(jobView), 'Sample loading must decide the runtime from what the sample content actually requires.');
assert(/if \(sampleRequiresDocker\) \{\s*\$\('#pythonRuntimeMode'\)\.val\('docker'\);\s*\}/.test(jobView), 'A Docker-only sample must still pin the Docker runtime.');
assert(/keepSelectedDockerRuntime && pythonWorkspaceAllowsInlineCode\(\)/.test(jobView), 'A preserved Docker runtime must keep the Dockerfile-backed build behind the Dockerfile tab and Open in VS Code.');
assert(/if \(\$\('#pythonUseDockerfile'\)\.is\(':checked'\)\) \{\s*ensurePythonPyprojectText\(\);\s*ensurePythonDockerfileText\(\);/.test(jobView), 'Dockerfile and pyproject text must be seeded from the resolved checkbox state, not only from the sample payload.');

// Only samples whose content needs Docker may declare it; the rest stay runtime-neutral.
samples.split(/\n    array\(/).forEach(block => {
  const id = (block.match(/'id'\s*=>\s*'([^']+)'/) || [])[1];
  if (!id || !/'family'\s*=>\s*'python'/.test(block)) return;
  if (/'runtime'\s*=>\s*'docker'/.test(block)) {
    assert(/'use_dockerfile'\s*=>\s*TRUE/.test(block) && /'docker_image'\s*=>/.test(block), `Sample ${id} pins the Docker runtime, so it must declare the Dockerfile build and image it needs.`);
  }
});
assert((samples.match(/'docker_image'\s*=>\s*'python:3\.13-alpine'/g) || []).length >= 3, 'Shell samples that require Python tooling must select a compatible Docker image.');

assert(generatorController.includes('return $this->role == ROLE_ADMIN;'), 'Dataset generation must be restricted to administrators.');
assert(generatorController.includes("$this->input->method(TRUE) !== 'POST'"), 'Dataset mutations must reject non-POST requests.');
assert(generatorModel.includes('const TMF_CHUNK_SIZE = 500;') && generatorModel.includes("insertChunk('tmf'"), 'Large TMF datasets must be inserted in bounded batches.');
assert(generatorModel.includes('database_seconds') && generatorModel.includes('tmf_rows_per_second'), 'Generated batches must retain performance measurements.');
assert(generatorView.includes('get_csrf_token_name') && generatorView.includes('delete-generated-dataset'), 'Admin create/remove forms must carry CSRF tokens and explicit cleanup controls.');
assert(routes.includes("$route['dataset-generator/create']") && routes.includes("$route['dataset-generator/delete']"), 'Dataset generator routes must be explicit.');
assert(header.includes('dataset-generator') && schema.includes('CREATE TABLE IF NOT EXISTS `generated_datasets`'), 'The admin navigation and base schema must include the generator registry.');

console.log(`Job sample and dataset generator checks passed (${shellSamples} shell, ${pythonSamples} Python samples).`);
