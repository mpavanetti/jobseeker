'use strict';

// Task DAG wiring and renderer checks.
//
// The runtime itself is covered by scripts/test-jobseeker-dag.py and the static
// scanner by scripts/test-task-graph-scanner.php. This file covers the parts in
// between: the storage model, the endpoints, the routes, the client renderer,
// and the places in the views where the panel is actually mounted.

const assert = require('assert');
const fs = require('fs');

const graph = require('../assets/js/job-task-graph.js');
const read = (p) => fs.readFileSync(p, 'utf8');

let checks = 0;
function ok(label, condition) {
  assert(condition, label);
  checks++;
}

// --- 1. Layout ---------------------------------------------------------------

const fanOut = {
  tasks: [
    {id: 'extract', produces: ['raw']},
    {id: 'clean_a', depends_on: ['extract']},
    {id: 'clean_b', depends_on: ['extract']},
    {id: 'publish', depends_on: ['clean_a', 'clean_b']},
    {id: 'alert', depends_on: ['publish'], trigger: 'FAILURE'}
  ],
  edges: [
    {source: 'extract', target: 'clean_a', condition: 'SUCCESS'},
    {source: 'extract', target: 'clean_b', condition: 'SUCCESS'},
    {source: 'clean_a', target: 'publish', condition: 'SUCCESS'},
    {source: 'clean_b', target: 'publish', condition: 'SUCCESS'},
    {source: 'publish', target: 'alert', condition: 'FAILURE'}
  ]
};

const plan = graph.layout(fanOut, {});
ok('layers are derived from the edges',
  JSON.stringify(plan.layers) === JSON.stringify([['extract'], ['clean_a', 'clean_b'], ['publish'], ['alert']]));
ok('every task becomes a node', plan.nodes.length === 5);
ok('every edge becomes a path', plan.edges.length === 5);

const byId = {};
plan.nodes.forEach((node) => { byId[node.id] = node; });
ok('a later layer sits further right', byId.publish.x > byId.extract.x);
ok('siblings in a layer share a column', byId.clean_a.x === byId.clean_b.x);
ok('siblings in a layer do not overlap vertically',
  Math.abs(byId.clean_a.y - byId.clean_b.y) >= graph.NODE_HEIGHT);
ok('the canvas is wide enough for every column', plan.width > byId.alert.x + graph.NODE_WIDTH);
ok('the canvas is tall enough for every row',
  plan.height >= Math.max(...plan.nodes.map((n) => n.y + n.height)));
ok('an edge starts on its source and ends on its target', plan.edges.every((edge) => {
  const source = byId[edge.source];
  const target = byId[edge.target];
  return edge.x1 === source.x + source.width && edge.x2 === target.x;
}));
ok('a failure edge keeps its condition',
  plan.edges.find((e) => e.target === 'alert').condition === 'FAILURE');

// A graph with no run data renders as declared, not as failed.
ok('an un-run task is DECLARED, never PENDING-looking-failed',
  plan.nodes.every((node) => node.status === 'DECLARED'));

// Run state colours the nodes.
const stated = graph.layout(fanOut, {
  extract: {status: 'SUCCESS', durationMs: 1204, attempt: 1},
  clean_a: {status: 'RUNNING', attempt: 1},
  clean_b: {status: 'FAILURE', attempt: 3, message: 'ValueError: bad row'},
  publish: {status: 'UPSTREAM_FAILED'},
  alert: {status: 'SUCCESS', attempt: 1}
});
const statedById = {};
stated.nodes.forEach((node) => { statedById[node.id] = node; });
ok('run status reaches the node', statedById.clean_b.status === 'FAILURE');
ok('the attempt number reaches the node', statedById.clean_b.attempt === 3);
ok('the failure message reaches the node', statedById.clean_b.message === 'ValueError: bad row');

// An empty graph must not throw.
ok('an empty graph lays out to nothing', graph.layout({tasks: [], edges: []}).nodes.length === 0);
ok('a missing graph lays out to nothing', graph.layout(null, null).nodes.length === 0);

// An edge pointing at a task that is not declared must be dropped, not crash.
const dangling = graph.layout(
  {tasks: [{id: 'solo'}], edges: [{source: 'solo', target: 'ghost'}]}, {});
ok('an edge to an undeclared task is dropped', dangling.edges.length === 0);
ok('the declared task still renders', dangling.nodes.length === 1);

// A cycle should never reach the browser, but it must not lose nodes if it does.
const cyclic = graph.layout({
  tasks: [{id: 'a'}, {id: 'b'}],
  edges: [{source: 'a', target: 'b'}, {source: 'b', target: 'a'}]
}, {});
ok('a cyclic graph still shows every node', cyclic.nodes.length === 2);

// --- 2. Duration formatting ---------------------------------------------------

ok('sub-second durations are milliseconds', graph.formatDuration(842) === '842ms');
ok('seconds get one decimal', graph.formatDuration(1204) === '1.2s');
ok('minutes are split out', graph.formatDuration(125000) === '2m 5s');
ok('a missing duration is blank', graph.formatDuration(null) === '');

// --- 3. Summary ----------------------------------------------------------------

ok('the summary counts outcomes',
  graph.summaryText({
    graph: fanOut,
    runState: 'running',
    tasks: [
      {id: 'extract', status: 'SUCCESS'},
      {id: 'clean_a', status: 'RUNNING'},
      {id: 'clean_b', status: 'FAILURE'},
      {id: 'publish', status: 'UPSTREAM_FAILED'}
    ]
  }) === '5 tasks · 4 started · 1 running · 1 succeeded · 2 failed');
ok('a graph with no tasks has no summary', graph.summaryText({graph: {tasks: []}}) === '');
ok('a queued build says nothing has started',
  graph.summaryText({graph: fanOut, runState: 'pending', tasks: []}) === '5 tasks · none started yet');

// --- 3b. A queued build must never wear a previous run's outcome ------------
//
// This is the regression the run states exist for: a build that is queued has
// produced nothing, and showing the last run's green nodes under its header
// reads as though the new build had already finished.
const queuedPlan = graph.layout(fanOut, {}, 'pending');
ok('every task of a queued build is QUEUED',
  queuedPlan.nodes.every((node) => node.status === 'QUEUED'));
ok('no task of a queued build counts as started',
  queuedPlan.nodes.every((node) => node.started === false));

const runningPlan = graph.layout(fanOut, {extract: {status: 'SUCCESS'}}, 'running');
const runningById = {};
runningPlan.nodes.forEach((node) => { runningById[node.id] = node; });
ok('a task that has run keeps its outcome', runningById.extract.status === 'SUCCESS');
ok('a task still waiting its turn is PENDING, not DECLARED',
  runningById.clean_a.status === 'PENDING');
ok('a job that never ran shows declarations',
  graph.layout(fanOut, {}, 'none').nodes.every((node) => node.status === 'DECLARED'));
ok('every run state maps to an unstarted status',
  ['pending', 'running', 'finished', 'none'].every((state) => !!graph.UNSTARTED_STATUS[state]));

const pendingHtml = graph.renderHtml({
  graph: fanOut, job: 'nightly', runState: 'pending', pendingBuild: 6, requestedBuild: 6, runs: [], tasks: []
});
ok('a queued build says so in words', pendingHtml.indexOf('Build #6 is queued') !== -1);
ok('a queued build shows no success colouring', pendingHtml.indexOf('jtg-success') === -1);

const runningHtml = graph.renderHtml({
  graph: fanOut, job: 'nightly', runState: 'running',
  run: {runKey: 'nightly-DEV-7', buildNumber: 7, status: 'RUNNING'}, runKey: 'nightly-DEV-7',
  runs: [], tasks: [{id: 'extract', status: 'RUNNING', attempt: 1}]
});
ok('a running build names itself', runningHtml.indexOf('build #7, still running') !== -1);

// --- 3c. TMF telemetry reaches the graph ------------------------------------
ok('rows read and written are summarised',
  graph.rowsLabel({recordsProcessed: 1180, recordsTotal: 1200}) === '1,180 / 1,200 rows');
ok('a single reported count is enough',
  graph.rowsLabel({recordsProcessed: null, recordsTotal: 500}) === '500 rows');
ok('equal counts are not repeated',
  graph.rowsLabel({recordsProcessed: 42, recordsTotal: 42}) === '42 rows');
ok('a task that reported nothing claims nothing',
  graph.rowsLabel({recordsProcessed: null, recordsTotal: null}) === '');

const rowsHtml = graph.renderHtml({
  graph: fanOut, job: 'nightly', runState: 'finished',
  run: {runKey: 'r', buildNumber: 3, status: 'SUCCESS'}, runKey: 'r', runs: [],
  tasks: [{id: 'extract', status: 'SUCCESS', attempt: 1, recordsProcessed: 1180, recordsTotal: 1200,
           tmfInstanceId: 'abc123', durationMs: 900}]
});
ok('the table grows a Rows column when a task reported counts', rowsHtml.indexOf('<th>Rows</th>') !== -1);
ok('the reported counts are shown', rowsHtml.indexOf('1,180 / 1,200 rows') !== -1);
ok('a graph with no counts keeps the table narrow',
  graph.renderHtml({graph: fanOut, runState: 'none', runs: [], tasks: []}).indexOf('<th>Rows</th>') === -1);

// --- 3d. Selecting a task ----------------------------------------------------
const plan2 = graph.layout(fanOut, {}, 'none');
const selection = graph.selectionFor(plan2, 'clean_a');
ok('a selection names its task', selection.id === 'clean_a');
ok('a selection knows its direct neighbours',
  selection.related.extract === true && selection.related.publish === true);
ok('a selection excludes unrelated tasks', selection.related.alert === undefined);
ok('selecting a task that is not in the graph selects nothing',
  graph.selectionFor(plan2, 'ghost') === null);

const selectedHtml = graph.renderHtml({
  graph: fanOut, job: 'nightly', runState: 'finished',
  run: {runKey: 'r', buildNumber: 3, status: 'SUCCESS'}, runKey: 'r', runs: [],
  tasks: [{id: 'clean_a', status: 'SUCCESS', attempt: 1, durationMs: 400, startedAt: '2026-09-22 09:00:00'}]
}, {selected: 'clean_a'});
ok('a selected task is marked', selectedHtml.indexOf('jtg-selected') !== -1);
ok('its neighbours stay legible', selectedHtml.indexOf('jtg-related') !== -1);
ok('unrelated tasks are dimmed', selectedHtml.indexOf('jtg-dimmed') !== -1);
ok('its edges are highlighted', selectedHtml.indexOf('jtg-edge-active') !== -1);
ok('a detail panel opens', selectedHtml.indexOf('jtg-detail-grid') !== -1);
ok('the detail shows when the task started', selectedHtml.indexOf('2026-09-22 09:00:00') !== -1);
ok('nodes are reachable by keyboard', selectedHtml.indexOf('tabindex="0"') !== -1);
ok('without a selection the panel invites one',
  graph.renderHtml({graph: fanOut, runState: 'none', runs: [], tasks: []}).indexOf('jtg-detail-hint') !== -1);

// --- 3e. Re-run controls -----------------------------------------------------
const failedPayload = {
  graph: fanOut, job: 'nightly', runState: 'finished', runKey: 'nightly-DEV-9', canRun: true,
  run: {runKey: 'nightly-DEV-9', buildNumber: 9, status: 'FAILURE'}, runs: [],
  tasks: [
    {id: 'extract', status: 'SUCCESS', attempt: 1},
    {id: 'clean_a', status: 'FAILURE', attempt: 2},
    {id: 'publish', status: 'UPSTREAM_FAILED', attempt: 1}
  ]
};
ok('the failed tasks are identified',
  JSON.stringify(graph.failedTasks(failedPayload)) === JSON.stringify(['clean_a', 'publish']));

const actionHtml = graph.renderHtml(failedPayload);
ok('a failed run offers a re-run', actionHtml.indexOf('data-action="resume"') !== -1);
ok('the re-run says how many tasks it covers', actionHtml.indexOf('Re-run 2 failed tasks') !== -1);
ok('a whole-job run is always offered', actionHtml.indexOf('data-action="all"') !== -1);
ok('running one task needs a selection first', actionHtml.indexOf('data-action="task"') === -1);
ok('selecting a task offers to run just that one',
  graph.renderHtml(failedPayload, {selected: 'clean_a'}).indexOf('data-action="task"') !== -1);

const readOnly = JSON.parse(JSON.stringify(failedPayload));
readOnly.canRun = false;
ok('a viewer who cannot run jobs is offered no run controls',
  graph.renderHtml(readOnly).indexOf('jtg-action') === -1);
ok('a successful run offers no re-run of failures',
  graph.renderHtml({graph: fanOut, job: 'n', canRun: true, runState: 'finished', runKey: 'r',
    run: {runKey: 'r', buildNumber: 2, status: 'SUCCESS'}, runs: [],
    tasks: [{id: 'extract', status: 'SUCCESS', attempt: 1}]}).indexOf('data-action="resume"') === -1);

// --- 4. Rendering ---------------------------------------------------------------

const html = graph.renderHtml({
  graph: fanOut,
  runs: [{runKey: 'nightly-DEV-42', buildNumber: 42, status: 'FAILURE', startedAt: '2026-09-22 09:00:00'},
         {runKey: 'nightly-DEV-41', buildNumber: 41, status: 'SUCCESS', startedAt: '2026-09-21 09:00:00'}],
  runKey: 'nightly-DEV-42',
  stored: true,
  tasks: [
    {id: 'extract', status: 'SUCCESS', attempt: 1, durationMs: 1204},
    {id: 'clean_b', status: 'FAILURE', attempt: 3, message: 'ValueError: bad row'}
  ]
});
ok('the graph renders an svg', html.indexOf('<svg') !== -1);
ok('every task has a node', fanOut.tasks.every((task) => html.indexOf('data-task="' + task.id + '"') !== -1));
ok('a failed task is marked', html.indexOf('jtg-failure') !== -1);
ok('a table accompanies the graph for screen readers', html.indexOf('<table') !== -1);
ok('the run picker appears when several runs exist', html.indexOf('jtg-run-select') !== -1);
ok('the selected run is preselected', /value="nightly-DEV-42" selected/.test(html));
ok('the run picker can be suppressed',
  graph.renderHtml({graph: fanOut, runs: [{runKey: 'a'}, {runKey: 'b'}], tasks: []}, {runPicker: false})
    .indexOf('jtg-run-select') === -1);

const empty = graph.renderHtml({graph: null, tasks: []});
ok('a job without tasks explains how to declare them', empty.indexOf('@dag.task') !== -1);
ok('a job without tasks renders no canvas', empty.indexOf('<svg') === -1);

// Task ids and messages are operator-supplied text and must be escaped.
const hostile = graph.renderHtml({
  graph: {tasks: [{id: 'evil', description: '<img src=x onerror=alert(1)>'}], edges: []},
  tasks: [{id: 'evil', status: 'FAILURE', message: '</text><script>alert(1)</script>'}]
});
ok('a task description cannot inject markup', hostile.indexOf('<img src=x') === -1);
ok('a task message cannot inject markup', hostile.indexOf('<script>alert(1)</script>') === -1);
ok('escaped text is still shown', hostile.indexOf('&lt;img src=x') !== -1);

// --- 5. Client API surface ------------------------------------------------------

const clientJs = read('assets/js/job-task-graph.js');
['layout', 'render', 'renderHtml', 'summaryText', 'load', 'scan', 'statesFrom'].forEach((fn) => {
  ok('job-task-graph.js exports ' + fn, typeof graph[fn] === 'function');
});
ok('the client reads the tasks endpoint', clientJs.indexOf("controller + '/tasks'") !== -1);
ok('the client can ask for a specific build', /build: buildNumber/.test(clientJs));
ok('the client posts to the scan endpoint', clientJs.indexOf("'jobCreation/scanTasks'") !== -1);

// --- 6. Storage model -----------------------------------------------------------

const model = read('application/models/JobTask_model.php');
ok('the model exists', model.indexOf('class JobTask_model') !== -1);
['job_task_graphs', 'job_task_runs'].forEach((table) => {
  ok('the model can create ' + table, model.indexOf('CREATE TABLE IF NOT EXISTS `' + table + '`') !== -1);
});
['function saveGraph(', 'function graphForJob(', 'function recentRuns(', 'function tasksForRun(',
 'function overview(', 'function summaryForJobs(', 'function deleteForJob(', 'function runKeyForBuild(']
  .forEach((method) => {
    ok('JobTask_model defines ' + method, model.indexOf(method) !== -1);
  });
ok('run rows are unique per attempt', model.indexOf('UNIQUE KEY `job_task_run_attempt` (`run_key`,`task_key`,`attempt`)') !== -1);
ok('one graph row per job and environment', model.indexOf('UNIQUE KEY `job_task_graph_scope` (`job_name`,`environment`)') !== -1);
ok('only the last attempt of a task is shown', /MAX\(attempt\) AS attempt/.test(model));
ok('schema maintenance is not charged to every request',
  model.indexOf('private static $schemaChecked') !== -1 &&
  !/function __construct\(\)\s*\{[^}]*ensureSchema\(\)/.test(model));
ok('a runtime manifest and a static scan are distinguished',
  model.indexOf("array('scan', 'runtime')") !== -1);

// --- 7. Endpoints and routes -----------------------------------------------------

const scanner = read('application/libraries/TaskGraphScanner.php');
ok('the scanner exists', scanner.indexOf('class TaskGraphScanner') !== -1);
ok('the scanner never executes job code', scanner.indexOf('No code is executed') !== -1);
ok('the scanner only trusts names bound to the DAG', scanner.indexOf('function dagNames(') !== -1);
ok('the scanner reads balanced call arguments', scanner.indexOf('function balancedArguments(') !== -1);

const jobCreation = read('application/controllers/JobCreation.php');
ok('Job Creation exposes a live scan', jobCreation.indexOf('public function scanTasks()') !== -1);
ok('Job Creation stores the graph on save', jobCreation.indexOf('$this->persistJobTaskGraph(') !== -1);
ok('a preview never writes run history', jobCreation.indexOf('JOBSEEKER_DAG_STATE=0') !== -1);

const baseController = read('application/libraries/BaseController.php');
ok('the read-only views share one overview helper', baseController.indexOf('function jobTaskOverview(') !== -1);
ok('a job with no stored graph is scanned live', baseController.indexOf('taskgraphscanner') !== -1);

['application/controllers/JobView.php', 'application/controllers/JobExecution.php'].forEach((file) => {
  const source = read(file);
  ok(file + ' exposes tasks()', source.indexOf('public function tasks()') !== -1);
  ok(file + ' uses the shared helper', source.indexOf('$this->jobTaskOverview(') !== -1);
});

const routes = read('application/config/routes.php');
['jobView/tasks', 'jobExecution/tasks', 'jobCreation/scanTasks'].forEach((route) => {
  ok('route exists: ' + route, routes.indexOf("$route['" + route + "']") !== -1);
});

const deleteJob = read('application/controllers/DeleteJob.php');
ok('deleting a job clears its task rows', deleteJob.indexOf('jobTaskModel->deleteForJob(') !== -1);

// --- 8. Runtime wiring ------------------------------------------------------------

const executionTrait = read('application/controllers/concerns/JobCreationExecutionTrait.php');
ok('the generated command exports the DAG environment', executionTrait.indexOf('function dagRuntimeLines()') !== -1);
['JOBSEEKER_DAG_RESUME', 'JOBSEEKER_DAG_TASKS', 'JOBSEEKER_DAG_MAX_PARALLEL', 'JOBSEEKER_DAG_FAIL_FAST', 'JOBSEEKER_DAG_STATE']
  .forEach((name) => {
    ok(name + ' is exported for the job', executionTrait.indexOf("export " + name + "=") !== -1);
    ok(name + ' is forwarded into the container', executionTrait.indexOf('-e ' + name) !== -1);
  });
ok('the python builder includes the DAG environment',
  /dataAssetsRuntimeLines\(\$repositoryRoot\),\s*\$this->connectorRuntimeLines\(\),\s*\$this->dagRuntimeLines\(\)/.test(executionTrait));

// --- 9. Views ----------------------------------------------------------------------

const header = read('application/views/includes/header.php');
ok('the console grouper is cache-busted for the task sections', header.indexOf('job-console-groups.js?v=8') !== -1);
ok('the console stylesheet is cache-busted', header.indexOf('job-console-groups.css?v=5') !== -1);

const consoleCss = read('assets/dist/css/job-console-groups.css');
['task', 'dag'].forEach((kind) => {
  ok('console section style exists for ' + kind, consoleCss.indexOf('.job-console-section-' + kind) !== -1);
});

const taskCss = read('assets/dist/css/job-task-graph.css');
['jtg-success', 'jtg-failure', 'jtg-upstream-failed', 'jtg-running', 'jtg-skipped', 'jtg-declared'].forEach((cls) => {
  ok('task status style exists for ' + cls, taskCss.indexOf('.' + cls) !== -1);
});
ok('the running animation respects reduced motion', taskCss.indexOf('prefers-reduced-motion') !== -1);

const jobView = read('application/views/jobView.php');
ok('Job View loads the client', jobView.indexOf('assets/js/job-task-graph.js') !== -1);
ok('Job View mounts a task panel', jobView.indexOf('job-task-panel') !== -1);
ok('Job View reads its own endpoint', jobView.indexOf("JobSeekerTaskGraph.load('JobView'") !== -1);
ok('Job View can switch runs', jobView.indexOf('.jtg-run-select') !== -1);

const jobExecution = read('application/views/jobExecution.php');
ok('Job Execution loads the client', jobExecution.indexOf('assets/js/job-task-graph.js') !== -1);
ok('Job Execution mounts a task panel', jobExecution.indexOf('execution-task-panel') !== -1);
ok('Job Execution refreshes the graph on its poll ticks',
  /function updateExecutionUI\(run\)[\s\S]{0,2000}refreshTaskGraph\(run, false\)/.test(jobExecution));
ok('Job Execution throttles those refreshes', jobExecution.indexOf('TASK_GRAPH_MIN_INTERVAL_MS') !== -1);
ok('Job Execution guarantees a final refresh after the build ends',
  jobExecution.indexOf('finalFetched') !== -1);
ok('Job Execution asks for its own build', /load\('JobExecution', run\.jobName, environment, '', run\.buildNumber\)/.test(jobExecution));

const jobCreationView = read('application/views/jobCreation.php');
ok('the editor previews the graph', jobCreationView.indexOf('jobTaskPanel') !== -1);
ok('the editor scans on the same debounce as the dependency panel',
  /tasks\.scan\(currentPayload\(\)\)/.test(jobCreationView));
ok('the sample library can filter for task DAGs', jobCreationView.indexOf("task_dag: 'Task DAG'") !== -1);

// --- 10. Schema and samples ----------------------------------------------------------

const schema = read('db_setup.sql');
['job_task_graphs', 'job_task_runs'].forEach((table) => {
  ok('db_setup.sql ships ' + table, schema.indexOf('CREATE TABLE IF NOT EXISTS `' + table + '`') !== -1);
});
ok('run state is separate from TMF telemetry', schema.indexOf('`tmf_instance_id`') !== -1);

const samples = read('application/config/job_samples.php');
ok('a starter task DAG is offered', samples.indexOf("'id' => 'python-task-dag'") !== -1);
ok('an advanced task DAG is offered', samples.indexOf("'id' => 'python-task-dag-assets'") !== -1);
ok('the samples call dag.run()', (samples.match(/dag\.run\(\)/g) || []).length >= 2);
ok('the samples show a FAILURE trigger', samples.indexOf('trigger="FAILURE"') !== -1);
ok('the samples show an ALWAYS trigger', samples.indexOf('trigger="ALWAYS"') !== -1);

// --- 11. The SDK exposes the runtime -----------------------------------------------

const sdk = read('application/third_party/python/jobseeker_sdk/src/jobseeker/__init__.py');
ok('the SDK exports the dag module', /"dag",/.test(sdk));
ok('the dag module is resolved lazily without recursing', sdk.indexOf('importlib.import_module(__name__ + ".dag")') !== -1);

const pyproject = read('application/third_party/python/jobseeker_sdk/pyproject.toml');
ok('the jobseeker-dag CLI is registered', pyproject.indexOf('jobseeker-dag = "jobseeker.dag:dag_cli"') !== -1);

const dagRuntime = read('application/third_party/python/jobseeker_sdk/src/jobseeker/dag.py');
ok('the runtime reuses the pipeline trigger vocabulary',
  /TRIGGERS = \(SUCCESS, FAILURE, ALWAYS\)/.test(dagRuntime));
ok('the runtime writes the same tables the model reads',
  dagRuntime.indexOf('job_task_runs') !== -1 && dagRuntime.indexOf('job_task_graphs') !== -1);
ok('task state never fails a run', dagRuntime.indexOf('telemetry must never fail a run') !== -1);
ok('every line a task writes is attributed to it',
  dagRuntime.indexOf('class _TaskOutputStream') !== -1 && /sys\.stdout, sys\.stderr = tagged_stdout, tagged_stderr/.test(dagRuntime));
ok('the original streams are restored when the run ends',
  /sys\.stdout, sys\.stderr = previous_stdout, previous_stderr/.test(dagRuntime));

const consoleJs = read('assets/js/job-console-groups.js');
ok('the console grouper reads the task tag', consoleJs.indexOf('TASK_OWNED_LINE') !== -1);
ok('a tag is only honoured for a task a marker introduced', consoleJs.indexOf('knownTasks[ownerMatch[1]]') !== -1);
ok('interleaved task output is regrouped into one section per task',
  consoleJs.indexOf('var regrouped = []') !== -1 && consoleJs.indexOf('sections = regrouped;') !== -1);

// --- 12. TMF integration ---------------------------------------------------
//
// A task inside a task DAG is a transaction, so it belongs on the Results page
// like any other. What was missing was the linkage that makes a row readable as
// part of one job run, and the counts coming back into the job run view.

const sdkDag = read('application/third_party/python/jobseeker_sdk/src/jobseeker/dag.py');
ok('a task is tracked unless it opts out', /def tracks\(self, state_enabled: bool\) -> bool/.test(sdkDag));
ok('tracking follows the run by default', sdkDag.indexOf('track: Optional[bool] = None') !== -1);
ok('a tracked task carries its run and task id into TMF',
  /run_key=run_state\.run_key,\s*\n\s*task_key=spec\.id,/.test(sdkDag));
ok('a task can report rows without knowing whether TMF is reachable',
  sdkDag.indexOf('def progress(') !== -1);
ok('tracking can be turned off for a whole run', sdkDag.indexOf('JOBSEEKER_DAG_TMF') !== -1);

ok('the transport writes the linkage when the columns exist',
  sdk.indexOf('def _has_task_columns(') !== -1 && sdk.indexOf('columns += ", run_key, task_key"') !== -1);
ok('an older schema is not an error for the worker',
  sdk.indexOf('an older schema is not an error') !== -1);
ok('TmfTask carries the run linkage', sdk.indexOf('run_key: str = "",') !== -1);

ok('the schema ships the linkage columns',
  schema.indexOf('`run_key` varchar(64)') !== -1 && schema.indexOf('`task_key` varchar(128)') !== -1);
ok('the linkage is indexed', schema.indexOf('KEY `tmf_task_run` (`run_key`,`task_key`)') !== -1);
ok('an existing installation is migrated', model.indexOf('function ensureTmfTaskColumns(') !== -1);
ok('the migration reads the column list in one query',
  /SELECT COLUMN_NAME FROM information_schema\.COLUMNS/.test(model));
ok('task rows carry their TMF counts back to the graph',
  model.indexOf("t.records_total, t.records_processed") !== -1);
ok('a count that was never reported stays unreported',
  model.indexOf('private function recordCount(') !== -1);
ok('a stack without tmf still returns task rows', model.indexOf("table_exists('tmf')") !== -1);

const tmfModel = read('application/models/Tmf_model.php');
ok('the Results page can be scoped to one run', tmfModel.indexOf('function listByRun(') !== -1);
ok('that scope is a no-op on a schema without the columns',
  /if \(\$runKey === '' \|\| ! \$this->hasTaskColumns\(\)\)/.test(tmfModel));

const tmfController = read('application/controllers/Tmf.php');
ok('a run-scoped Results view exists', tmfController.indexOf('public function taskRun(') !== -1);
ok('the run key is validated before it reaches a query',
  /preg_match\('\/\^\[A-Za-z0-9\._-\]\+\$\/', \$runKey\)/.test(tmfController));

const tmfView = read('application/views/tmf.php');
ok('the Results page says when it is scoped to one run', tmfView.indexOf('tmf-task-run-scope') !== -1);
ok('and offers a way back to everything', tmfView.indexOf('Show every transaction') !== -1);

// The results toolbar is shared by the ordinary Results page and the
// run-scoped one added above. The truncation notice explains the "+" in the
// row count beside it, so it lives inside the toolbar; as a sibling alert it
// rendered as a full-width bar under a row that had the button stranded at one
// end and the count at the other.
const toolbar = tmfView.match(/<div class="tmf-results-toolbar">[\s\S]*?\n        <\/div>/);
ok('the results toolbar exists', toolbar !== null);
ok('the truncation notice sits inside the toolbar', toolbar[0].indexOf('tmf-result-limit') !== -1);
ok('it is no longer a full-width alert bar', tmfView.indexOf('<div class="alert alert-info">') === -1);
ok('the toolbar wraps instead of stretching its items apart',
  /\.tmf-results-toolbar \{[^}]*flex-wrap: wrap;/.test(tmfView) &&
  !/\.tmf-results-toolbar \{[^}]*justify-content: space-between;/.test(tmfView));
ok('the row count is pushed right by free space, not by justify-content',
  /\.tmf-refresh-note \{[^}]*margin-left: auto;/.test(tmfView));
ok('the notice takes a row of its own', /\.tmf-result-limit \{[^}]*flex: 1 0 100%;/.test(tmfView));
ok('and the auto margin is dropped once the toolbar stacks',
  /@media \(max-width: 767px\)[\s\S]*\.tmf-refresh-note \{\s*margin-left: 0;/.test(tmfView));
ok('the graph links a task to its transaction', clientJs.indexOf("'tmf/taskRun/'") !== -1);

// --- 13. A queued build shows itself, not the previous run -------------------
ok('naming a build that has written nothing marks the run pending',
  model.indexOf('$pendingBuild = $buildNumber;') !== -1);
ok('and does not fall back to the latest run',
  /if \(\$runKey === '' && \$pendingBuild === 0 && ! empty\(\$runs\)\)/.test(model));
ok('the panel is told what the run is doing', model.indexOf('private function runState(') !== -1);
['pending', 'running', 'finished', 'none'].forEach((state) => {
  ok('the model can report the ' + state + ' run state', model.indexOf("'" + state + "'") !== -1);
});

// --- 14. Running a graph from the panel --------------------------------------
// `jobExecution` above is the view; the endpoint lives in the controller.
const jobExecutionController = read('application/controllers/JobExecution.php');
ok('the re-run endpoint exists', jobExecutionController.indexOf('public function runTasks()') !== -1);
ok('only a manager or admin may re-run',
  /runTasks\(\)[\s\S]{0,400}ROLE_ADMIN && \$this->role != ROLE_MANAGER/.test(jobExecutionController));
ok('re-running is a POST', /runTasks\(\)[\s\S]{0,800}Method not allowed/.test(jobExecutionController));
ok('a resume key must belong to this job', jobExecutionController.indexOf('That run does not belong to this job.') !== -1);
ok('a task id must be one the job declares', jobExecutionController.indexOf('does not declare a task called') !== -1);
ok('the re-run is an ordinary build of the same job', jobExecutionController.indexOf("'/buildWithParameters'") !== -1);
ok('route exists: jobExecution/runTasks', routes.indexOf("$route['jobExecution/runTasks']") !== -1);

ok('a Python job declares the two task parameters',
  jobCreation.indexOf("'name', 'JOBSEEKER_DAG_RESUME'") !== -1 &&
  jobCreation.indexOf("'name', 'JOBSEEKER_DAG_TASKS'") !== -1);
ok('a shell job does not grow them',
  /createRuntimeEnvironmentProperties\(\$dom, \$environment, \$includeTaskParameters = FALSE\)/.test(jobCreation));
ok('the builder only asks for them on a Python job', jobCreation.indexOf('$declaresTasks = ') !== -1);

ok('the client can queue a re-run', clientJs.indexOf("'jobExecution/runTasks'") !== -1);
ok('the panel reports what happened', clientJs.indexOf('onRun') !== -1 && clientJs.indexOf('onRunFailed') !== -1);

// --- 15. A running graph keeps filling in ------------------------------------
ok('Job View follows a run that is still going',
  jobView.indexOf('TASK_GRAPH_LIVE_INTERVAL_MS') !== -1 &&
  /data\.runState === 'running'/.test(jobView));
ok('and stops polling once it finishes', jobView.indexOf('clearTaskGraphTimer') !== -1);
ok('Job View surfaces the outcome of a queued re-run', jobView.indexOf('Queued as build #') !== -1);

console.log('Job task graph checks passed (' + checks + ' assertions).');
