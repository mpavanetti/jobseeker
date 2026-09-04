/*
 * Apache Hop integration contract tests.
 *
 * These assert the seams that break silently: the upstream Apache Hop entry
 * point variables, the Hop file formats JobSeeker generates, the PHP and Python
 * halves of the project contract agreeing with each other, and the security
 * properties (no credential in a project file, no secret in a process argument).
 *
 * They are static, so they run in the default `npm test` chain. The live
 * execution path is covered by scripts/test-hop-e2e.py, which needs Docker.
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const read = file => fs.readFileSync(file, 'utf8');

const sdk = read('application/third_party/python/jobseeker_sdk/src/jobseeker/hop.py');
const pyproject = read('application/third_party/python/jobseeker_sdk/pyproject.toml');
const library = read('application/libraries/HopProject.php');
const model = read('application/models/Hop_model.php');
const controller = read('application/controllers/Hop.php');
const jobCreation = read('application/controllers/JobCreation.php');
const deleteJob = read('application/controllers/DeleteJob.php');
const executionTrait = read('application/controllers/concerns/JobCreationExecutionTrait.php');
const jobCreationView = read('application/views/jobCreation.php');
const hopView = read('application/views/hop.php');
const routes = read('application/config/routes.php');
const header = read('application/views/includes/header.php');
const compose = read('docker-compose.yml');
const image = read('docker/hop_image');
const entrypoint = read('docker/hop/entrypoint-extension.sh');
const hopServerLibrary = read('application/libraries/HopServer.php');
const hopGraphLibrary = read('application/libraries/HopGraph.php');
const hopCanvas = read('assets/js/hop-canvas.js');
const consoleGroups = read('assets/js/job-console-groups.js');
const jobExecutionView = read('application/views/jobExecution.php');
const jobExecutionController = read('application/controllers/JobExecution.php');
const schema = read('db_setup.sql');
const baseController = read('application/libraries/BaseController.php');

// --- Upstream Apache Hop container contract -------------------------------
// Names taken from docker/resources/load-and-execute.sh in apache/hop 2.19.
[
  'HOP_PROJECT_FOLDER',
  'HOP_PROJECT_NAME',
  'HOP_PROJECT_CONFIG_FILE_NAME',
  'HOP_ENVIRONMENT_NAME',
  'HOP_ENVIRONMENT_CONFIG_FILE_NAME_PATHS',
  'HOP_RUN_CONFIG',
  'HOP_FILE_PATH',
  'HOP_LOG_LEVEL'
].forEach(variable => assert(sdk.includes(variable), 'runner must set ' + variable));
assert(sdk.includes('HOP_RUN_PARAMETERS'), 'runner must pass Hop parameters through the documented variable');
assert(image.includes('HOP_CUSTOM_ENTRYPOINT_EXTENSION_SHELL_FILE_PATH'), 'the image must use the supported entry point hook');
assert(image.includes('FROM apache/hop:${HOP_VERSION}'), 'the JobSeeker image must extend the official Hop image');
assert(!image.includes('--no-deps'), 'the Hop image must include the TMF database transport used by workflow actions');

// --- Engines ---------------------------------------------------------------
// Two engines only. The "agent" engine needed a Hop installation on the
// Jenkins worker, which the platform never provides, so it could only ever
// fail; the container engine covers the same ground with no host dependency.
assert(/ENGINES = \("container", "server"\)/.test(sdk));
assert(sdk.includes('class ContainerEngine') && sdk.includes('class ServerEngine'));
assert(!sdk.includes('class AgentEngine') && !sdk.includes('hop-run.sh'), 'the removed agent engine must not linger');
assert(sdk.includes('DEFAULT_IMAGE = "apache/hop:" + HOP_VERSION'), 'the default run image must be pullable without a private registry');
assert(sdk.includes('"/hop/execWorkflow"') && sdk.includes('"/hop/execPipeline"'), 'server engine must use the Hop Server servlets');
assert(sdk.includes('"/hop/status"'), 'server engine must expose a health probe');
assert(sdk.includes('method="POST"'), 'server engine must POST so credentials never reach a URL or an access log');
assert(library.includes("array('container', 'server')"), 'PHP and Python must offer the same engines');
assert(!jobCreationView.includes('value="agent"'), 'Job Creation must not offer an engine the platform cannot run');

// --- Project contract, both halves ----------------------------------------
assert(sdk.includes('MANIFEST_NAME = ".jobseeker-hop.json"'));
assert(library.includes("const MANIFEST_NAME = '.jobseeker-hop.json'"));
assert(sdk.includes('PROJECT_CONFIG_NAME = "project-config.json"'));
assert(library.includes("const PROJECT_CONFIG_NAME = 'project-config.json'"));
assert(sdk.includes('SCHEMA_VERSION = 1') && library.includes('const SCHEMA_VERSION = 1'));
assert(sdk.includes('DEFAULT_RUN_CONFIG = "local"') && library.includes("const DEFAULT_RUN_CONFIG = 'local'"));
['ensure_project_config', 'ensure_run_configurations', 'locate'].forEach(method =>
  assert(sdk.includes('def ' + method), 'the runner must implement ' + method));
['ensureProjectConfig', 'ensureRunConfigurations', 'locate'].forEach(method =>
  assert(library.includes('function ' + method), 'the PHP library must implement ' + method));

// Both halves must agree on Hop's own file formats.
['metadataBaseFolder', '${PROJECT_HOME}/metadata', 'enforcingExecutionInHome'].forEach(key => {
  assert(sdk.includes(key), 'runner project-config.json must carry ' + key);
  assert(library.includes(key), 'PHP project-config.json must carry ' + key);
});
assert(sdk.includes('engineRunConfiguration') && library.includes('engineRunConfiguration'));
assert(sdk.includes('pipeline-run-configuration') && library.includes('pipeline-run-configuration'));
assert(sdk.includes('workflow-run-configuration') && library.includes('workflow-run-configuration'));
// Hop reads environment config files as {"variables": [{name, value, description}]}.
assert(/"variables": \[dict\(variable\) for variable in variables\]/.test(sdk));

// --- Connectors become Hop database connections ---------------------------
['MYSQL', 'MARIADB', 'POSTGRESQL', 'MSSQLNATIVE', 'ORACLE'].forEach(pluginId =>
  assert(sdk.includes('"' + pluginId + '"'), 'missing Hop database plugin id ' + pluginId));
assert(sdk.includes('def rdbms_metadata'));
assert(sdk.includes('"rdbms": {'), "generated metadata must use Hop's rdbms document shape");
assert(sdk.includes('JOBSEEKER_CONN'), 'connector values must be exposed as namespaced Hop variables');
// A credential must never be written into a project file: the run-scoped
// documents reference variables, and only the Hop Server - whose metadata
// folder is where Hop itself expects credentials - gets resolved values.
assert(sdk.includes('use_variables: bool = True'));
assert(sdk.includes('use_variables=engine_name != "server"'));
assert(sdk.includes('replace=engine_name == "server"'), 'server runs must replace stale scoped connector metadata');
assert(sdk.includes('.execution.lock'), 'shared Hop Server metadata and executions must be serialized');
assert(/class ServerEngine[\s\S]*?def cleanup\([\s\S]*?os\.unlink/.test(sdk),
  'resolved server connector metadata must be deleted after the synchronous run');
assert(sdk.includes('def write_environment_file') && sdk.includes('mode=0o600'));
assert(sdk.includes('def redact('), 'a stack trace must not be able to leak a password into the console');
assert((sdk.match(/redact_variables=variables/g) || []).length >= 1,
  'container subprocess output must be redacted before it reaches Jenkins');
assert(sdk.includes('redact(_describe_web_result(body), variables)'),
  'Hop Server output must be redacted before it reaches Jenkins');
assert(sdk.includes('member.isfile() or member.isdir()'),
  'container output copy-back must ignore archive links and special files');
assert(/if arguments\.command == "metadata":[\s\S]*?finally:[\s\S]*?shutil\.rmtree\(temporary_directory/.test(sdk),
  'metadata previews must remove their materialized connector credentials');

// --- Data Assets, Context and TMF -----------------------------------------
assert(sdk.includes('JOBSEEKER_ASSET'), 'Data Assets must be published as Hop variables');
assert(sdk.includes('DataAssetCatalog') && sdk.includes('materialize_connectors'), 'the runner must reuse the platform catalogs');
assert(sdk.includes('jobseeker.begin(') && sdk.includes('jobseeker.end(') && sdk.includes('jobseeker.error('), 'a Hop run must open and close a TMF instance');
assert(sdk.includes('def parse_hop_counters'), "record counts must come from Hop's own metrics");
// A Hop workflow can report into its own TMF row from a shell action, which is
// only usable if the instance id is the sole thing on stdout.
assert(sdk.includes('"tmf",'), 'the runner must expose a tmf subcommand');
assert(/redirect_stdout\(sys\.stderr\)/.test(sdk), 'tmf begin must keep its narration off stdout');
assert(sdk.includes('JOBSEEKER_TMF_INSTANCE_ID'), 'the run instance id must be published as a Hop variable');
assert(sdk.includes('has_metrics'));
assert(sdk.includes('def referenced_variables') && sdk.includes('def _context_variable_names'),
  'the runner must discover Context variables referenced directly by Hop files');
assert(sdk.includes('if with_tmf or context_names'),
  '--no-tmf must not disable Context variables requested by a Hop project');
assert(sdk.includes('Unavailable in this JobSeeker run scope'),
  'an unresolved platform reference must not survive as a literal ${NAME} value');
// Monitoring must never be able to fail the job it is monitoring, whether
// constructing the shared client or opening the transaction is what fails.
assert(/monitoring must never block execution/.test(sdk));
assert(/Could not open the Transaction Monitoring instance/.test(sdk));

// --- Failures reach Transaction Monitoring with Hop's own words -----------
// Hop reports what went wrong on ordinary log lines. Recording only the exit
// code left TMF saying "Apache Hop reported errors" and nothing else, which is
// exactly when somebody needs the detail.
assert(sdk.includes('def extract_hop_errors'), "TMF must record Hop's own error lines");
assert(sdk.includes('failed = not result.ok or counters["errors"] > 0 or bool(hop_errors)'),
  'Hop ERROR/FATAL log lines must fail a build even when the exit code and counters are clean');
assert(sdk.includes('def _failure_message') && sdk.includes('def _failure_origin'));
assert(sdk.includes('type="Apache Hop"'), 'a Hop failure must not be filed as a Python exception');
assert(/def error\([\s\S]*?type: str = "Python Exception"/.test(read('application/third_party/python/jobseeker_sdk/src/jobseeker/__init__.py')),
  'the SDK error type must be overridable without changing the default for Python jobs');
// A workflow status carries no per-action log text at all, so its whole log
// arrives only as the gzip+base64 <logging_string>.
assert(sdk.includes('def decode_hop_log') && sdk.includes('gzip.decompress'));
assert(hopServerLibrary.includes('gzdecode'), 'the screen must decode the same log format as the runner');
assert(/status = self\._collect_status\(/.test(sdk) && !/if not failed:\s*\n\s*log = self\._collect/.test(sdk),
  'the log must be read back on failure too, which is when it matters');
assert(sdk.includes('_ACTION_ERROR_COUNT'), 'a workflow that fails without row metrics must still count as failed');

// --- Hop Server runs started outside JobSeeker ----------------------------
// The Apache Hop GUI can publish straight to the server. Those runs used to be
// invisible: not on the Hop screen, not in Transaction Monitoring.
assert(controller.includes('function syncServerExecutions'));
assert(controller.includes('recordTmfRun') && controller.includes('recordTmfErrors'));
assert(model.includes('function recordTmfRun') && model.includes('function recordTmfErrors'));
assert(model.includes("'dimension' => (string) (isset($values['dimension']) ? $values['dimension'] : 'hop-server')"),
  'an externally started run must be identifiable in Transaction Monitoring');
assert(hopServerLibrary.includes('function executions') && hopServerLibrary.includes('function errorLines'));
assert(hopView.includes('Hop Server executions'), 'the screen must show what the server ran');
assert(hopView.includes('hop/execution-log') || hopView.includes("executionLogUrl"), 'a run must show its Hop log');
assert(hopView.includes('document.hidden'), 'the screen must stop polling the Hop Server when nobody is looking');
// A run JobSeeker started already has a TMF row from the runner, so the
// reconciler must be able to tell the two apart.
assert(sdk.includes('def _claim_run') && /"hop", "server", "claims"/.test(sdk));
assert(hopServerLibrary.includes('function claims') && hopServerLibrary.includes('CLAIM_MAX_AGE_SECONDS'));
assert(sdk.includes('def _forget_run') && sdk.includes('"/hop/removePipeline"') && sdk.includes('"/hop/removeWorkflow"'),
  'JobSeeker must clean up after its own server runs');
assert(sdk.includes('def _registered_ids'), 'a run must identify its own execution id, not the first of its name');
assert(!sdk.includes('_forget_previous_run'), 'removing runs by name would delete somebody else\'s');
assert(sdk.includes('"PROJECT_HOME": self.translate_path(self.project.root)'),
  'the server engine must set PROJECT_HOME or intra-project references cannot resolve');

// --- Reading a Hop run in JobSeeker ---------------------------------------
// A Hop log is grouped by the transform or action that wrote each line, which
// is how Hop's own UI presents a run; the generic parser would collapse it into
// one wall of text because a Hop log carries no Jenkins markers.
assert(consoleGroups.includes('function parseHop') && consoleGroups.includes("'hop-step'"),
  'the shared console must understand an Apache Hop log');
assert(consoleGroups.includes('parseHop: parseHop'), 'the Hop parser must be exported');
assert(hopView.includes("parser: 'hop'"), 'the Hop log viewer must use the platform console, not a bare <pre>');

// The canvas is rebuilt from the layout Hop already stores in the file, so a
// person can see what a job does without opening a desktop tool.
assert(hopGraphLibrary.includes('function parseFile') && hopGraphLibrary.includes("'xloc'"));
assert(hopGraphLibrary.includes("'unconditional'") && hopGraphLibrary.includes("'failure'"),
  'a workflow hop must record whether it follows success or failure');
assert(hopCanvas.includes('function spreadFactors') && hopCanvas.includes('function separate'),
  'boxes are far wider than Hop\'s 32px icon, so the layout must be spread and then de-overlapped');
assert(controller.includes('public function graph()') && routes.includes("$route['hop/graph']"));
assert(hopView.includes('hop-view-canvas') && hopView.includes('hop-project-canvas'),
  'both a run and a project file must open the canvas');

// A run somebody is happy with has to become a scheduled Jenkins job without
// retyping the project, the file and the engine.
assert(hopView.includes('function useInJobUrl') && hopView.includes('hop_entry='));
assert(jobCreation.includes("hop_entry") && jobCreation.includes("hop_engine"),
  'Job Creation must accept the file and engine a Use in job link carries');
assert(jobCreationView.includes('requestedHopEntry') && jobCreationView.includes('requestedHopEngine'));

// Filters, because both tables grow without bound.
assert(hopView.includes('hopRunSearch') && hopView.includes('hopRunState') && hopView.includes('hopRunSource'));
assert(hopView.includes('hopProjectEnvironment') && hopView.includes('hopProjectHealth'));

// The Apache Hop GUI leaves a new file's internal name as "New workflow", so
// every run of it would otherwise pile up under one meaningless label.
assert(hopServerLibrary.includes('function displayName') && hopServerLibrary.includes('function sourceName'));
assert(schema.includes('`display_name`') && model.includes('`display_name`'));
assert(model.includes("field_exists('display_name'"), 'an existing install must gain the column');

// --- Connections, variables and assets on a shared Hop Server -------------
// A pipeline published from the Apache Hop GUI has no runner in front of it, so
// without a published catalog it fails with "connection not found".
assert(controller.includes('public function publishConnections()'));
assert(routes.includes("$route['hop/publish-connections']"));
assert(hopServerLibrary.includes('function publishCatalog') && hopServerLibrary.includes('function withdrawCatalog'));
assert(hopServerLibrary.includes('function publishSystemVariables'),
  'a GUI-started run resolves ${JOBSEEKER_*} only from Hop system variables');
assert(compose.includes('HOP_CONFIG_FOLDER=${JOBSEEKER_HOP_SERVER_CONFIG_FOLDER'),
  'the Hop Server needs a writable configuration folder for those variables');
assert(entrypoint.includes('jobseeker_seed_config_folder'), 'that folder must be seeded from the image');
assert(controller.includes("runtimeSettings($environment, '*')"),
  'only connectors scoped to every job may reach a shared server');
assert(controller.includes("stays run-scoped"), 'a cloud-held secret must not be copied onto the server volume');
assert(sdk.includes('def _restore_published_catalog'),
  'a Jenkins run borrows the shared metadata folder and must put the published catalog back');

// The Apache Hop GUI publishes by uploading a zip of whatever the designer has
// open, so such a run has no file in the repository and cannot be scheduled
// until the archive is imported.
assert(compose.includes('-Djava.io.tmpdir=${JOBSEEKER_HOP_SERVER_EXPORT_FOLDER'),
  'Hop must write its uploaded exports where JobSeeker can read them');
assert(controller.includes('public function importExecution()'));
assert(routes.includes("$route['hop/import-execution']"));
assert(hopServerLibrary.includes('function exportArchive'));
assert(/function exportArchive[\s\S]*?realpath[\s\S]*?strpos\(\$real, rtrim\(\$folder/.test(hopServerLibrary),
  'an archive outside the export folder must be refused, not read');
assert(controller.includes('__workflow_execution_configuration__.xml'),
  "Hop's own execution configuration must not be imported as project content");
assert(hopView.includes('hop-import-execution'), 'the screen must offer the import');

// A Hop file is the same XML wherever it came from, so it can be taken back
// into the desktop Apache Hop GUI.
assert(controller.includes('public function download()') && routes.includes("$route['hop/download']"));
assert(controller.includes('Content-Disposition: attachment'));
assert(controller.includes('$entry->isLink()'), 'a project archive must not follow links out of the project');
assert(hopView.includes('downloadUrl'), 'the screen must offer the download');

// The Apache Hop image bundles only the permissively licensed JDBC drivers, so
// a connection to anything else needs one installed. What is missing depends on
// the image, so the server decides rather than the app guessing.
assert(hopServerLibrary.includes('function requiredDrivers') && hopServerLibrary.includes('function publishDrivers'));
assert(entrypoint.includes('jobseeker_install_required_drivers') && entrypoint.includes('hop driver list'),
  'the server must detect what its own image is missing');
assert(entrypoint.includes('--accept-license'));
assert(compose.includes('HOP_SHARED_JDBC_FOLDERS'), 'installed drivers must survive a container rebuild');
assert(/HOP_SHARED_JDBC_FOLDERS=[^\n]*\/opt\/hop\/lib\/jdbc/.test(compose),
  "the image's own driver folder must stay on the scan list or its bundled drivers disappear");

// A shared metadata folder that a Jenkins run borrows can be left short, and a
// person would only find out when a Hop GUI pipeline failed.
assert(hopServerLibrary.includes('function ensureCatalogMirrored'));
assert(controller.includes('ensureCatalogMirrored'), 'the screen must repair a drifted catalog');

// An Apache Hop job runs a picture, so its name and execution action open a
// modal with the rows each transform has moved while the server holds the run.
assert(controller.includes("$this->input->get('job', TRUE)") && controller.includes("projectForJob($jobName)"),
  'the canvas must be addressable by Jenkins job');
assert(controller.includes("get('live') === '1'") && hopServerLibrary.includes('function executionNodes'));
assert(jobExecutionController.includes('hop_jobs') && model.includes('function hopJobs'));
assert(jobExecutionView.includes('id="hopCanvasModal"') && jobExecutionView.includes('execution-hop-canvas'));
assert(jobExecutionView.includes('execution-hop-job-link') && jobExecutionView.includes('hopExecutionCanvas'));
assert(!jobExecutionView.includes('id="hopCanvasBox"'), 'the canvas must not occupy a separate execution-page section');
assert(jobExecutionView.includes('hop-canvas.js'));
assert(hopCanvas.includes('options.nodeState'), 'the canvas must be able to draw run state');
assert(jobExecutionView.includes('document.hidden'), 'a hidden tab must not keep polling the Hop Server');

// Apache Hop is the platform's own ETL integration, so it leads the choice.
assert(
  jobCreationView.indexOf('data-linux-etl-choice="hop"') < jobCreationView.indexOf('data-linux-etl-choice="talend"'),
  'Apache Hop must be offered before Talend'
);
// Scoped to the Linux list: the Windows one has a Talend option and no Hop one,
// because this integration only runs Hop on Linux workers.
const linuxScriptTypes = jobCreationView.slice(
  jobCreationView.indexOf('id="linuxScriptType"'),
  jobCreationView.indexOf('</select>', jobCreationView.indexOf('id="linuxScriptType"'))
);
assert(
  linuxScriptTypes.indexOf('<option value="hop">') < linuxScriptTypes.indexOf('<option value="talend">'),
  'Apache Hop must lead the Linux script-type list too'
);

// --- Jenkins remains the scheduler ----------------------------------------
assert(executionTrait.includes('function buildHopExecutionCommand'));
assert(executionTrait.includes('jobseeker-hop run'));
assert(executionTrait.includes('command -v jobseeker-hop >/dev/null'));
assert(executionTrait.includes('export PYTHONUNBUFFERED=1'), 'the console must stream while Hop runs');
assert(executionTrait.includes('--environment "$JOBSEEKER_ENVIRONMENT"'), 'the environment must follow the Jenkins parameter, not be baked in');
assert(executionTrait.includes('--job "$JOBSEEKER_JOB_NAME"'));
assert(executionTrait.includes('escapeshellarg'), 'every generated argument must be escaped');
assert(executionTrait.includes("'  --param '.escapeshellarg($name.'='.$value)"),
  'job parameters must be escaped into the generated shell command');
assert(pyproject.includes('jobseeker-hop = "jobseeker.hop:main"'), 'the runner must be installed as a console script');
// The runner and the container engine only work if every worker that can run a
// Hop job installs the SDK and carries a Docker client.
['docker/jenkins_image', 'docker/jenkins_agent_image'].forEach(dockerfile => {
  const body = read(dockerfile);
  assert(/pip install[^\n]*jobseeker_sdk/.test(body.replace(/\\\n/g, ' ')),
    dockerfile + ' must install the JobSeeker SDK so jobseeker-hop is on PATH');
  assert(body.includes('/usr/local/bin/docker'),
    dockerfile + ' must carry the Docker CLI for the container engine');
});

// --- Job Creation integration ---------------------------------------------
assert(jobCreationView.includes('data-linux-etl-choice="hop"'), 'the Apache Hop card must be selectable');
assert(!jobCreationView.includes('<strong>Apache Hop</strong><span>Coming soon.</span>'));
assert(jobCreationView.includes('<option value="hop">Apache Hop Workflow or Pipeline</option>'));
['hopSourceMode', 'hopSample', 'hopProjectPath', 'hopEntryFile', 'hopEngine', 'hopRunConfig', 'hopLogLevel', 'hopParameters'].forEach(field => {
  assert(jobCreationView.includes('name="' + field + '"'), 'missing form field ' + field);
  assert(jobCreationView.includes("'" + field + "'"), field + ' must be persisted in job drafts');
});
assert(jobCreationView.includes('function linuxExecutionUsesHop'));
assert(jobCreationView.includes('function hydrateHopCommand'), 'editing a Jenkins Hop job must restore the Hop form');
assert(jobCreationView.includes('requestedHopProject'), 'a catalog project must open preselected in Job Creation');
assert(jobCreationView.includes("acceptedFiles = '.zip,.hpl,.hwf'"));
assert(jobCreation.includes("$safeScriptType === 'hop' ? 'projects' : 'jobs'"), 'Hop uploads belong in repository/hop/projects');
assert(jobCreation.includes("array('zip', 'hpl', 'hwf')"));
assert(jobCreation.includes('function resolveHopExecution'));
assert(jobCreation.includes('function normalizeUploadedHopProject'), 'an archive that wraps the project in a folder must still work');
assert(jobCreation.includes("$targetJobPath.'-upload-'"), 'Hop archives must be staged before replacing a working project');
assert(baseController.includes('ZIP archive expands beyond the maximum allowed size'), 'archive extraction must reject zip bombs');
assert(baseController.includes('getExternalAttributesIndex'), 'archive extraction must reject symlinks and special files');
assert(jobCreation.includes('buildHopExecutionCommand($hopExecution'));
assert(jobCreation.includes("'hop/projects'"), 'dependency scanning must see Hop projects');
assert(jobCreation.includes('persistHopProject'));
assert(jobCreation.includes('linkJob('), 'the Jenkins job must be linked to its Hop project');
assert(jobCreation.includes('unlinkJob($targetJobName'), 'changing a Hop job to another runtime must remove stale usage');
assert(deleteJob.includes("'relative_root' => 'hop/projects'"), 'repository cleanup must include job-owned Hop projects');
assert(deleteJob.includes('unlinkJob($jobName'), 'deleting a Jenkins job must remove its Hop usage link');
assert(model.includes("->where('job_name', $jobName)"), 'a Jenkins job must have one current Hop usage link');
assert(jobCreation.includes("$projectEnvironment = 'ALL'"), 'a Hop project shared across environments must remain visible in all of them');
assert(jobCreation.includes('pathWithinBase($projectRoot'), 'a Hop project must not escape the repository');
assert(library.includes('is_link($absolute)'), 'project discovery must never follow uploaded symlinks');
assert(library.includes("$root === '' || is_link($root)"), 'bare project scaffolding must reject a linked root');
assert(controller.includes('if (is_link($path))'), 'project deletion must unlink rather than traverse symlinks');

// --- Screen, routes and navigation ----------------------------------------
assert(routes.includes('$route[\'hop\'] = "Hop/index";'));
assert(routes.includes('$route[\'hop/inspect\'] = "Hop/inspect";'));
assert(routes.includes('$route[\'hop/delete\'] = "Hop/delete";'));
assert(routes.includes('$route[\'hop/executions\'] = "Hop/executions";'));
assert(routes.includes('$route[\'hop/execution-log\'] = "Hop/executionLog";'));
assert(header.includes('<span>Apache Hop</span>'), 'Apache Hop must appear in the ETL sidebar section');
assert(header.includes('$jobseekerHopEnabled'), 'disabling Hop must also hide its navigation');
assert(controller.includes('function serverStatus'));
assert(controller.includes('canManageProjects'), 'destructive actions must be role gated');
assert(controller.includes("method(TRUE) !== 'POST'"));
assert(hopView.includes('content-wrapper hop-page'), 'the screen must stay inside the AdminLTE content wrapper');
assert(hopView.includes('Jenkins schedules, Hop executes'));
assert(hopView.includes('Use in job'), 'catalog projects must publish through the shared Job Creation flow');

// --- Registry --------------------------------------------------------------
['hop_projects', 'hop_project_jobs', 'hop_server_executions'].forEach(table => {
  assert(schema.includes('CREATE TABLE IF NOT EXISTS `' + table + '`'), 'db_setup.sql must create ' + table);
  assert(model.includes('CREATE TABLE IF NOT EXISTS `' + table + '`'), 'the model must create ' + table + ' on existing installs');
});
assert(schema.includes('UNIQUE KEY `hop_projects_key`'));
assert(schema.includes('UNIQUE KEY `hop_project_jobs_scope`'));
assert(schema.includes('UNIQUE KEY `hop_server_executions_id`'),
  'one Hop execution must be one row, so a repeated poll cannot duplicate a TMF entry');

// --- Compose ---------------------------------------------------------------
assert(compose.includes('hop-server:'), 'the optional Hop Server engine must be a service');
assert(/hop-server:[\s\S]*?profiles:\s*\n\s*- hop\b/.test(compose), 'hop-server must be behind a profile');
assert(compose.includes('JOBSEEKER_HOP_ENABLED=${JOBSEEKER_HOP_ENABLED:-true}'),
  'the optional Hop integration must be enabled by default');
assert(!compose.includes('hop-web'), 'Hop Web is not part of this integration');
assert(compose.includes('HOP_SERVER_METADATA_FOLDER=/php/repository/hop/server/metadata'));
assert(compose.includes('JOBSEEKER_HOP_CONNECTOR_REFRESH'));
assert(compose.includes('JOBSEEKER_HOP_CONNECTOR_REFRESH:-false'), 'persistent server credentials must be opt-in');
assert(compose.includes('find /repository/hop -type d -exec chmod 2775'),
  'shared Hop directories must inherit the host group for authoring writes');
assert(compose.includes('/repository/hop/projects/jobseeker'), 'the shared authoring project must be initialized');
assert(compose.includes('\\"project\\": \\"jobseeker\\"'), 'the initialized authoring manifest must match its registered project name');
assert(compose.includes('/repository/hop/projects'), 'the repository initializer must create the Hop folders');
assert(compose.includes('/repository/hop/runs'));
assert(compose.includes('${JOBSEEKER_HOP_SERVER_HOST:-127.0.0.1}'), 'the Hop Server port must default to loopback');
assert(compose.includes('JOBSEEKER_HOP_SERVER_ENVIRONMENT'), 'the app must know which environment a Hop Server run belongs to');
assert(compose.includes('JOBSEEKER_HOP_IMAGE'), 'workers must be told which Hop image to run');
// The app, the Jenkins controller and all four environment agents need it.
assert((compose.match(/JOBSEEKER_HOP_SERVER_URL/g) || []).length >= 6,
  'the controller, the agents and the app must all know the Hop Server URL');

// --- Entry point extension --------------------------------------------------
assert(entrypoint.includes('JOBSEEKER_HOP_METADATA_OVERLAY'));
assert(entrypoint.includes('jobseeker-hop server-catalog'));
assert(!/echo.*PASSWORD/i.test(entrypoint), 'the entry point must never echo a credential');

// --- Sample projects --------------------------------------------------------
const samplesRoot = 'application/third_party/hop/samples';
const samples = fs.readdirSync(samplesRoot).filter(entry => fs.statSync(path.join(samplesRoot, entry)).isDirectory());
assert(samples.length >= 3, 'ship at least three reviewed Hop starter projects');
samples.forEach(sample => {
  const root = path.join(samplesRoot, sample);
  assert(fs.existsSync(path.join(root, 'project-config.json')), sample + ' must be a real Hop project');
  const manifest = JSON.parse(read(path.join(root, '.jobseeker-hop.json')));
  assert.strictEqual(manifest.schema_version, 1);
  assert(manifest.entry_file, sample + ' must declare an entry file');
  assert(manifest.description, sample + ' must explain itself in the sample picker');
  assert(fs.existsSync(path.join(root, manifest.entry_file)), sample + ' entry file must exist: ' + manifest.entry_file);
  assert(fs.existsSync(path.join(root, 'metadata/pipeline-run-configuration/local.json')));

  const files = [];
  const walk = directory => fs.readdirSync(directory).forEach(entry => {
    const full = path.join(directory, entry);
    if (fs.statSync(full).isDirectory()) { walk(full); } else { files.push(full); }
  });
  walk(root);
  files.forEach(file => {
    const body = read(file);
    // A sample must never carry a credential or an absolute developer path.
    assert(!/<password>(?!\s*<)/.test(body), file + ' must not contain a password');
    assert(!/\/home\/|\/Users\//.test(body), file + ' must not contain an absolute developer path');
  });
});
assert(samples.includes('platform-hello'), 'keep the dependency-free smoke test sample');

console.log('Apache Hop integration checks passed (' + samples.length + ' sample project(s)).');
