const assert = require('assert');
const fs = require('fs');
const builder = require('../assets/js/pipeline-builder');

const nodes = [
  {id: 'extract', job: 'extract-job'},
  {id: 'load_a', job: 'load-a'},
  {id: 'load_b', job: 'load-b'},
  {id: 'publish', job: 'publish-job'}
];
const edges = [
  {source: 'extract', target: 'load_a', condition: 'SUCCESS'},
  {source: 'extract', target: 'load_b', condition: 'SUCCESS'},
  {source: 'load_a', target: 'publish', condition: 'ALWAYS'},
  {source: 'load_b', target: 'publish', condition: 'FAILURE'}
];

const valid = builder.validateGraph(nodes, edges);
assert.strictEqual(valid.ok, true);
assert.strictEqual(builder.nodeIdForJob('1', 1), 'node-1-1');
assert.strictEqual(builder.nodeIdForJob('folder/2', 2), 'node-folder-2-2');
assert.strictEqual(builder.validateGraph([{id: builder.nodeIdForJob('1', 1), job: '1'}], []).ok, true);
assert.deepStrictEqual(valid.layers, [['extract'], ['load_a', 'load_b'], ['publish']]);
assert.strictEqual(builder.validateGraph(nodes, edges.concat({source: 'publish', target: 'extract', condition: 'ALWAYS'})).ok, false);
assert.strictEqual(builder.validateGraph(nodes, edges.concat(edges[0])).ok, false);
assert.strictEqual(builder.validateGraph([], []).ok, false);

const compiler = fs.readFileSync('application/libraries/WorkflowCompiler.php', 'utf8');
const controller = fs.readFileSync('application/controllers/Pipelines.php', 'utf8');
const contextController = fs.readFileSync('application/controllers/Context.php', 'utf8');
const builderSource = fs.readFileSync('assets/js/pipeline-builder.js', 'utf8');
const pipelineView = fs.readFileSync('application/views/pipelines.php', 'utf8');
const deploymentView = fs.readFileSync('application/views/contextPromotion.php', 'utf8');
assert(compiler.includes('parallel branches'));
assert(compiler.includes('propagate: false'));
assert(compiler.includes('JOBSEEKER_PIPELINE_NODE'));
assert(compiler.includes('waitForStart: true'));
assert(compiler.includes('RUNNING|${downstream.number}'));
assert(compiler.includes('waitForBuild runId: downstream.externalizableId, propagate: false, propagateAbort: true'));
assert(controller.includes('public function run()'));
assert(controller.includes('public function status($runId)'));
assert(controller.includes('queueIdFromHeaders'));
assert(controller.includes("pipeline->environment !== $environment"));
assert(controller.includes("'__jobseeker_pipeline_'"));
assert(controller.includes("api/json?tree=nextBuildNumber"));
assert(controller.includes("jenkins_build_number' => $expectedBuildNumber"));
assert(controller.includes("$environment === 'ALL' || $pipeline->environment !== $environment"));
assert(controller.includes('cleanCronExpression'));
assert(controller.includes('hudson.triggers.TimerTrigger'));
assert(controller.includes("'schedule_enabled' => $scheduleEnabled ? 1 : 0"));
assert(controller.includes('public function deploy()'));
assert(controller.includes('deploymentGraph'));
assert(controller.includes('Deploy the missing target jobs before deploying this pipeline'));
assert(controller.includes("'schedule_cron' => $source->schedule_cron"));
assert(controller.includes('syncObservedRuns'));
assert(controller.includes('TimerTriggerCause'));
assert(controller.includes('createObservedRun'));
assert(contextController.includes('annotatePromotionWorkloads'));
assert(contextController.includes("$job['workloadType'] = 'pipeline'"));
assert(compiler.includes('edge.condition == "SUCCESS"'));
assert(compiler.includes('edge.condition == "FAILURE"'));
assert(compiler.includes('edge.condition == "ALWAYS"'));
assert(builderSource.includes('/logText/progressiveText?start='));
assert(builderSource.includes('pipelineStatusTrack'));
assert(builderSource.includes('nextNodePosition'));
assert(builderSource.includes('function resizeCanvas()'));
assert(builderSource.includes("toggleClass('is-visible', state.nodes.length === 0)"));
assert(builderSource.includes('schedule_enabled'));
assert(builderSource.includes('pipelineScheduleCron'));
assert(builderSource.includes('pipelineDeployConfirm'));
assert(builderSource.includes('target_environment'));
assert(pipelineView.includes('pipelineExecutionMonitor'));
assert(pipelineView.includes('pipelineDeployModal'));
assert(deploymentView.includes('Environment Deployment'));
assert(deploymentView.includes('data-workload-type'));
assert(deploymentView.includes("pipelines/deploy?environment="));
assert(pipelineView.includes('pipelineConsoleOutput'));
assert(pipelineView.includes('pipeline-rail-left'));
assert(pipelineView.includes('pipelineCanvasEmpty'));
assert(pipelineView.includes('jobCreation'));

console.log('Pipeline builder tests passed.');
