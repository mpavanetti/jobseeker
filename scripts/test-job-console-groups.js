const assert = require('assert');
const fs = require('fs');
const consoleGroups = require('../assets/js/job-console-groups');

const consoleCss = fs.readFileSync('assets/dist/css/job-console-groups.css', 'utf8');
const header = fs.readFileSync('application/views/includes/header.php', 'utf8');
for (const kind of ['docker-execution', 'python-tests', 'shell', 'hop-execution', 'task', 'dag']) {
  assert(consoleCss.includes('.job-console-section-' + kind), kind + ' needs an explicit console style');
}
assert(header.includes('job-console-groups.css?v=7'));
assert(header.includes('job-console-groups.js?v=10'));

const dockerLog = [
  'Started by user jobseeker',
  'Running as SYSTEM',
  'Building in workspace /var/jenkins_home/workspace/sample-UAT',
  '+ export JOBSEEKER_PYTHON_RUNTIME=docker',
  '+ echo Preparing Python Docker build context...',
  'Preparing Python Docker build context...',
  '+ JOBSEEKER_DOCKERFILE=/tmp/context/Dockerfile',
  '+ DOCKER_BUILDKIT=1 docker build -t sample .',
  '#1 [internal] load build definition from Dockerfile',
  '#1 DONE 0.0s',
  '+ docker run --rm sample sh -lc set -e',
  'mkdir -p /tmp/jobseeker-context',
  'PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet runtime-sdk',
  '[JobSeeker] Python tests',
  '============================= test session starts ==============================',
  'collected 1 item',
  'tests/test_smoke.py .',
  '============================== 1 passed in 0.02s ===============================',
  '[JobSeeker] Python execution',
  'python -u "$JOBSEEKER_ENTRYPOINT" "$@" sh UAT',
  'Processing row 1/2',
  'Processing row 2/2',
  '[JobSeeker] Cleanup',
  '+ docker run --rm --user 0 --entrypoint cat -v jobseeker-email-sample-1:/jobseeker-email:ro sample /jobseeker-email/jobseeker-email-metrics.properties',
  '+ rm -f /var/jenkins_home/workspace/sample/jobseeker-email-metrics.properties.tmp',
  '+ docker run --rm --user 0 --entrypoint sh -v jobseeker-assets-sample-1:/jobseeker-repository sample -c rm -f /jobseeker-repository/data-assets/manifest.json; tar -C /jobseeker-repository -cf - data-assets',
  '+ tar -C /php/repository -xf -',
  '+ [ 0 -ne 0 ]',
  '+ jobseeker_python_docker_cleanup',
  '+ docker image rm sample',
  'Finished: SUCCESS'
].join('\n');

const parsed = consoleGroups.parse(dockerLog);
assert.strictEqual(consoleGroups.parse('first\r\nsecond\r\n').raw, 'first\r\nsecond\r\n');
assert.deepStrictEqual(parsed.sections.map((section) => section.kind), [
  'jenkins',
  'docker-build',
  'docker-runtime',
  'python-tests',
  'python',
  'cleanup',
  'result'
]);
assert(parsed.sections.find((section) => section.kind === 'python').text.includes('Processing row 2/2'));
assert(!parsed.sections.find((section) => section.kind === 'python').text.includes('jobseeker-email-metrics.properties'));
assert(parsed.sections.find((section) => section.kind === 'cleanup').text.includes('data-assets/manifest.json'));
assert(parsed.sections.find((section) => section.kind === 'python-tests').text.includes('1 passed'));
assert(parsed.sections.find((section) => section.kind === 'docker-runtime').text.includes('pip install'));
assert.strictEqual(parsed.sections.find((section) => section.kind === 'result').hasError, false);

const failed = consoleGroups.parse([
  'Started by user jobseeker',
  '+ python3 -u main.py',
  'Traceback (most recent call last):',
  'RuntimeError: broken',
  'Finished: FAILURE'
].join('\n'));
assert.strictEqual(failed.sections.find((section) => section.kind === 'python').hasError, true);
assert.strictEqual(failed.sections.find((section) => section.kind === 'result').hasError, true);

const exceptionOnly = consoleGroups.parse('+ python3 -u main.py\nRuntimeError: broken');
assert.strictEqual(exceptionOnly.sections.find((section) => section.kind === 'python').hasError, true);

const failedTests = consoleGroups.parse([
  '[JobSeeker] Python tests',
  'tests/test_failure.py F',
  'FAILED tests/test_failure.py::test_failure - AssertionError',
  'Finished: FAILURE'
].join('\n'));
assert.strictEqual(failedTests.sections.find((section) => section.kind === 'python-tests').hasError, true);
assert.strictEqual(failedTests.sections.some((section) => section.kind === 'python'), false);

const localEnvironment = consoleGroups.parse([
  'Started by user jobseeker',
  'Creating Python virtual environment...',
  'Installing Python dependencies from requirements.txt',
  '+ /tmp/.venv/bin/python -u main.py',
  'done',
  'Finished: SUCCESS'
].join('\n'));
assert.deepStrictEqual(localEnvironment.sections.map((section) => section.kind), [
  'jenkins',
  'python-environment',
  'python',
  'result'
]);

const abortedEmail = consoleGroups.parse([
  'Started by user jobseeker',
  '+ python3 -u main.py',
  'Processing row 1/5',
  'Build was aborted',
  'Aborted by jobseeker',
  'Email was triggered for: Aborted',
  'Sending email for trigger: Aborted',
  '[JobSeeker Email] From: JobSeeker <jobseeker@local.test>',
  '[JobSeeker Email] To: operator@example.com',
  '[JobSeeker Email] Subject: [ABORTED] sample #42',
  'Sending email to: operator@example.com',
  '[JobSeeker Email] Delivery completed.',
  'Finished: ABORTED'
].join('\n'));
assert.deepStrictEqual(abortedEmail.sections.map((section) => section.kind), [
  'jenkins',
  'python',
  'result',
  'email',
  'result'
]);
const emailSection = abortedEmail.sections.find((section) => section.kind === 'email');
assert.strictEqual(emailSection.title, 'Email notification');
assert(emailSection.text.includes('From: JobSeeker <jobseeker@local.test>'));
assert(emailSection.text.includes('To: operator@example.com'));
assert(emailSection.text.includes('Subject: [ABORTED] sample #42'));
assert(!abortedEmail.sections.find((section) => section.kind === 'python').text.includes('Sending email'));
assert.strictEqual(abortedEmail.sections.filter((section) => section.kind === 'result').some((section) => section.hasError), false,
  'a user-aborted build should not be presented as an application error');

const legacyDockerCleanup = consoleGroups.parse([
  '[JobSeeker] Python execution',
  'python -u "$JOBSEEKER_ENTRYPOINT" "$@" sh DEV',
  'Completed',
  '+ docker run --rm --user 0 --entrypoint cat -v jobseeker-email-2-1:/jobseeker-email:ro jobseeker-python-custom:2-1 /jobseeker-email/jobseeker-email-metrics.properties',
  '+ rm -f /var/jenkins_home/workspace/2/jobseeker-email-metrics.properties.tmp',
  '+ docker run --rm --user 0 --entrypoint sh -v jobseeker-assets-2-1:/jobseeker-repository jobseeker-python-custom:2-1 -c rm -f /jobseeker-repository/data-assets/manifest.json; tar -C /jobseeker-repository -cf - data-assets',
  '+ tar -C /php/repository -xf -',
  '+ [ 0 -ne 0 ]',
  'Finished: SUCCESS'
].join('\n'));
assert.deepStrictEqual(legacyDockerCleanup.sections.map((section) => section.kind), ['python', 'cleanup', 'result']);
assert(legacyDockerCleanup.sections.find((section) => section.kind === 'cleanup').text.includes('[ 0 -ne 0 ]'));

const explicitRuntimeMarkers = consoleGroups.parse([
  'Started by user jobseeker',
  '[JobSeeker] Git source checkout',
  'Cloning into /tmp/source',
  '[JobSeeker] Docker image build',
  '#1 DONE 0.1s',
  '[JobSeeker] Docker container execution',
  'container output',
  '[JobSeeker] Cleanup',
  'removed build context',
  'Finished: SUCCESS'
].join('\n'));
assert.deepStrictEqual(explicitRuntimeMarkers.sections.map((section) => section.kind), [
  'jenkins',
  'source',
  'docker-build',
  'docker-execution',
  'cleanup',
  'result'
]);
assert(explicitRuntimeMarkers.sections.find((section) => section.kind === 'source').text.includes('Cloning into'));
assert(explicitRuntimeMarkers.sections.find((section) => section.kind === 'docker-execution').text.includes('container output'));

const explicitShellMarker = consoleGroups.parse([
  '[JobSeeker] Shell execution',
  'first shell line',
  'second shell line',
  'Finished: SUCCESS'
].join('\n'));
assert.deepStrictEqual(explicitShellMarker.sections.map((section) => section.kind), ['shell', 'result']);
assert(explicitShellMarker.sections[0].text.includes('second shell line'));

const prebuiltDocker = consoleGroups.parse([
  'Started by user jobseeker',
  '[JobSeeker] Docker runtime setup',
  'Preparing Python Docker build context...',
  'Using prebuilt image python:3.13-slim',
  '[JobSeeker] Docker container execution',
  'container output',
  '[JobSeeker] Cleanup',
  'removed runtime volumes',
  'Finished: SUCCESS'
].join('\n'));
assert.deepStrictEqual(prebuiltDocker.sections.map((section) => section.kind), [
  'jenkins',
  'docker-runtime',
  'docker-execution',
  'cleanup',
  'result'
]);
assert.strictEqual(prebuiltDocker.sections.some((section) => section.kind === 'docker-build'), false,
  'using a prebuilt image must not be presented as an image build');

const hopJenkinsLog = consoleGroups.parse([
  'Started by user jobseeker',
  '[JobSeeker] Apache Hop execution (container)',
  '[JobSeeker] Apache Hop container run',
  '[JobSeeker] project=orders file=pipelines/main.hpl environment=DEV run-config=local',
  '[JobSeeker] Context variables: Custom',
  '[JobSeeker] Data Asset variables: JOBSEEKER_ASSET_ORDERS',
  '2026/09/04 22:01:13 - main - Execution started for pipeline [main]',
  '2026/09/04 22:01:13 - main - Finished processing (I=0, O=0, R=1, W=1, U=0, E=0)',
  '[JobSeeker] Completed pipelines/main.hpl in 0.5s (read 1, written 1, errors 0)',
  'Finished: SUCCESS'
].join('\n'));
assert.deepStrictEqual(hopJenkinsLog.sections.map((section) => section.kind), [
  'jenkins',
  'hop-execution',
  'hop-step',
  'hop-execution',
  'result'
]);
assert.strictEqual(hopJenkinsLog.sections.find((section) => section.kind === 'hop-execution').hasError, false);

const interleavedHopLog = consoleGroups.parse([
  '[JobSeeker] Apache Hop execution (container)',
  '2026/09/04 22:01:13 - extract customers.0 - reading page 1',
  '2026/09/04 22:01:13 - validate rows.0 - validating batch 1',
  '2026/09/04 22:01:14 - extract customers.1 - reading page 2',
  '2026/09/04 22:01:14 - validate rows.0 - Finished processing (I=0, O=0, R=2, W=2, U=0, E=0)',
  '2026/09/04 22:01:14 - extract customers.0 - Finished processing (I=2, O=0, R=0, W=2, U=0, E=0)',
  '[JobSeeker] Completed pipelines/customers.hpl in 0.5s (read 2, written 2, errors 0)',
  'Finished: SUCCESS'
].join('\n'));
const hopSteps = interleavedHopLog.sections.filter((section) => section.kind === 'hop-step');
assert.deepStrictEqual(hopSteps.map((section) => section.owner), ['extract customers', 'validate rows'],
  'Hop copy suffixes must map back to the transform names used by the canvas');
assert.strictEqual(hopSteps[0].lineCount, 3,
  'interleaved output and multiple copies must be regrouped into one complete transform section');
assert(hopSteps[0].text.includes('extract customers.1'));
assert(!hopSteps[0].text.includes('[JobSeeker] Completed'),
  'a JobSeeker phase marker must not be swallowed as a transform continuation');

const hyphenatedHopLog = consoleGroups.parse([
  '[JobSeeker] Apache Hop execution (container)',
  '2026/09/04 22:01:13 - extract-customers.0 - read 2 rows',
  'Finished: SUCCESS'
].join('\n'));
assert.strictEqual(hyphenatedHopLog.sections.find((section) => section.kind === 'hop-step').owner, 'extract-customers',
  'hyphens in a transform name must not prevent it from matching its canvas node');

const workflowHopLog = consoleGroups.parse([
  '[JobSeeker] Apache Hop execution (container)',
  '2026/09/04 22:01:13 - nightly - Starting action [load warehouse]',
  '2026/09/04 22:01:14 - nightly - Finished action [load warehouse] (result=[true])',
  'Finished: SUCCESS'
].join('\n'));
assert.strictEqual(workflowHopLog.sections.find((section) => section.kind === 'hop-step').owner, 'load warehouse',
  'workflow boundary messages must map to the action name used by the canvas');

const successfulBusinessStatuses = consoleGroups.parse([
  '[JobSeeker] Python execution',
  'status = error',
  '{"status":"failed","error":null}',
  '0 failed, 12 passed',
  'failure notifications disabled',
  'error handling mode = continue',
  'Finished: SUCCESS'
].join('\n'));
assert.strictEqual(successfulBusinessStatuses.sections.some((section) => section.hasError), false,
  'business values and zero-failure summaries must not paint a successful build red');

const successfulHopInventory = [
  'Hop Server result: OK',
  'Pipeline executed successfully',
  "2026/09/04 22:01:13 - tmf-inventory - Execution started for pipeline [tmf-inventory]",
  '2026/09/04 22:01:13 - write inventory to log.0 - status = ready',
  '2026/09/04 22:01:13 - write inventory to log.0 - status = error',
  '2026/09/04 22:01:13 - write inventory to log.0 - executions = 6',
  '2026/09/04 22:01:13 - write inventory to log.0 - Finished processing (I=0, O=0, R=3, W=3, U=0, E=0)',
  '[JobSeeker] Completed pipelines/tmf-inventory.hpl in 0.5s (read 3, written 3, errors 0)',
  'Finished: SUCCESS'
].join('\n');
const successfulHopConsole = consoleGroups.parse(successfulHopInventory);
const activeHopNode = consoleGroups.hopNodeState(successfulHopInventory.split('\n').slice(0, 4).join('\n'), {kind: 'pipeline'});
assert.strictEqual(activeHopNode.nodes['write inventory to log'].status, 'Running',
  'a transform should light up when its first log line arrives, before final counters');
const finishedHopNode = consoleGroups.hopNodeState(successfulHopInventory, {kind: 'pipeline'});
assert.strictEqual(finishedHopNode.nodes['write inventory to log'].status, 'Finished');
assert.strictEqual(finishedHopNode.nodes['write inventory to log'].written, 3);
const failedHopNode = consoleGroups.hopNodeState([
  '2026/09/04 22:01:13 - read inventory.0 - ERROR: Source unavailable',
  '2026/09/04 22:01:14 - read inventory.0 - Finished processing (I=0, O=0, R=0, W=0, U=0, E=1)'
].join('\n'), {kind: 'pipeline'});
assert.strictEqual(failedHopNode.nodes['read inventory'].status, 'Failed');
assert.strictEqual(failedHopNode.nodes['read inventory'].errors, 1,
  'the same error in a log line and final counters must only count once');
assert.strictEqual(successfulHopConsole.sections.some((section) => section.hasError), false,
  'a Hop data row whose value is error must not mark a successful Jenkins section as failed');
const successfulHopLog = consoleGroups.parseHop(successfulHopInventory, {name: 'tmf-inventory'});
assert.strictEqual(successfulHopLog.sections.some((section) => section.hasError), false,
  'the grouped Hop log must apply the same structured error rule');

const failedHopLog = consoleGroups.parseHop([
  '2026/09/04 22:03:39 - tmf-inventory - Execution started for pipeline [tmf-inventory]',
  '2026/09/04 22:03:40 - read TMF status counts.0 - ERROR: Unable to connect to database',
  '2026/09/04 22:03:40 - read TMF status counts.0 - Finished processing (I=0, O=0, R=0, W=0, U=0, E=0)'
].join('\n'), {name: 'tmf-inventory'});
assert.strictEqual(failedHopLog.sections.find((section) => section.title === 'read TMF status counts.0').hasError, true,
  'an actual Hop ERROR record must still be highlighted');

// A licence name is not a failure. The JDBC driver installer prints "GPLv2 with
// Universal FOSS Exception", which used to paint a successful build red.
const licenceLog = [
  'Started by user jobseeker',
  '+ hop driver install mysql --accept-license',
  "  license  : GPLv2 with Universal FOSS Exception (category X)",
  '  into     : /opt/hop/lib/jdbc',
  'Installed 1 jar(s):',
  'Finished: SUCCESS'
].join('\n');
assert(
  !consoleGroups.parse(licenceLog).sections.some((section) => section.hasError),
  'an ordinary use of the word Exception must not flag a section as failed'
);
// A real exception still does, with or without a trailing colon.
assert(consoleGroups.parse('java.lang.NullPointerException').sections[0].hasError);
assert(consoleGroups.parse('org.apache.hop.core.exception.HopXmlException: bad').sections[0].hasError);
assert(consoleGroups.parse('2026-09-21 12:00:00 ERROR worker connection failed').sections[0].hasError);

// --- Task DAG sections -------------------------------------------------------
//
// A job that declares tasks prints one marker per task, and the runtime tags
// every line a task writes with that task's id. Both matter: tasks run
// concurrently, so without per-line ownership one task's traceback lands under
// another task's heading and paints a task that succeeded red.
const dagLog = [
  'Started by user jobseeker',
  '[JobSeeker] Python execution',
  '[JobSeeker DAG] start | 4 task(s) | run nightly-DEV-42 | max parallel 4',
  '[JobSeeker Task] extract | RUNNING | attempt 1/1',
  '[extract] pulled 1200 rows',
  '[JobSeeker Task] extract | SUCCESS | attempt 1/1 | 1.204s',
  '[JobSeeker Task] enrich | RUNNING | attempt 1/2',
  '[JobSeeker Task] validate | RUNNING | attempt 1/1',
  '[enrich] Traceback (most recent call last):',
  '[validate] 1180 of 1200 rows passed validation',
  '[enrich] ValueError: bad row',
  '[JobSeeker Task] enrich | RETRY | attempt 1/2 | 0.100s | ValueError: bad row',
  '[JobSeeker Task] validate | SUCCESS | attempt 1/1 | 0.300s',
  '[JobSeeker Task] enrich | RUNNING | attempt 2/2',
  '[JobSeeker Task] enrich | FAILURE | attempt 2/2 | 0.120s | ValueError: bad row',
  '[JobSeeker Task] publish | UPSTREAM_FAILED | upstream failed',
  '[JobSeeker DAG] finish | FAILURE | 2 succeeded, 2 failed, 0 skipped | 2.100s',
  '[JobSeeker] Cleanup',
  'Finished: FAILURE'
].join('\n');

const dagSections = consoleGroups.parse(dagLog).sections;
const taskSections = dagSections.filter((section) => section.kind === 'task');
assert.strictEqual(taskSections.length, 4,
  'interleaved output must still produce exactly one section per task');
assert.deepStrictEqual(taskSections.map((section) => section.title),
  ['Task extract', 'Task enrich', 'Task validate', 'Task publish'],
  'each task section must be titled with its task id, in first-seen order');

const byTask = {};
taskSections.forEach((section) => { byTask[section.taskId] = section; });

assert(byTask.extract.text.indexOf('[extract] pulled 1200 rows') !== -1,
  "a task's own stdout must stay under that task's heading");
assert.strictEqual(byTask.extract.hasError, false, 'a task that succeeded must not be flagged');

assert(byTask.enrich.text.indexOf('ValueError: bad row') !== -1,
  "a task's traceback must stay under that task's heading");
assert.strictEqual(byTask.enrich.hasError, true, 'a task that failed must be flagged');
assert(byTask.enrich.text.indexOf('attempt 2/2') !== -1,
  'every attempt of one task belongs to the same section');

// This is the regression that per-line attribution exists to prevent.
assert.strictEqual(byTask.validate.hasError, false,
  "a concurrent task's traceback must never flag the task that was running beside it");
assert(byTask.validate.text.indexOf('Traceback') === -1,
  "one task's output must never leak into another task's section");
assert(byTask.validate.text.indexOf('1180 of 1200 rows passed validation') !== -1,
  'a task interleaved with another must keep its own output');

const dagFrames = dagSections.filter((section) => section.kind === 'dag');
assert.strictEqual(dagFrames.length, 2, 'the run start and the run outcome are their own sections');
assert.strictEqual(dagFrames[1].hasError, true, 'a failed run outcome must be flagged');
assert(dagSections.some((section) => section.kind === 'cleanup'),
  'an explicit JobSeeker section heading must still end the task sections');
assert.deepStrictEqual(dagSections.map((section) => section.kind),
  ['jenkins', 'python', 'dag', 'task', 'task', 'task', 'task', 'dag', 'cleanup', 'result'],
  'regrouping the tasks must not move the surrounding sections');
assert.strictEqual(new Set(dagSections.map((section) => section.id)).size, dagSections.length,
  'section ids must stay unique after regrouping');

// A bracketed prefix is only a task tag once a marker has introduced that task.
const lookalike = consoleGroups.parse([
  'Started by user jobseeker',
  '[worker] starting up',
  'Finished: SUCCESS'
].join('\n')).sections;
assert(!lookalike.some((section) => section.kind === 'task'),
  'an ordinary bracketed log prefix must not be mistaken for a task tag');

// A job that declares no tasks must be grouped exactly as before.
const plainPython = consoleGroups.parse([
  'Started by user jobseeker',
  '[JobSeeker] Python execution',
  'hello from a single script',
  'Finished: SUCCESS'
].join('\n')).sections;
assert(!plainPython.some((section) => section.kind === 'task' || section.kind === 'dag'),
  'a single-script job must not grow task sections');

// The DOM behavior is deliberately small enough to verify without a browser:
// focusing opens the matching group, remembers that choice for live redraws,
// scrolls it into view, and applies the highlight class.
let focusedScrolled = false;
let focusedSummary = false;
const focusedClasses = new Set();
const focusedSection = {
  open: false,
  offsetWidth: 100,
  getAttribute(name) {
    return {
      'data-console-owner': 'extract customers',
      'data-console-section-id': 'hop-step-1'
    }[name] || '';
  },
  scrollIntoView() { focusedScrolled = true; },
  querySelector(selector) {
    return selector === '.job-console-summary' ? {focus() { focusedSummary = true; }} : null;
  },
  classList: {
    add(name) { focusedClasses.add(name); },
    remove(name) { focusedClasses.delete(name); }
  }
};
const focusedHost = {
  querySelectorAll() { return [focusedSection]; }
};
assert.strictEqual(consoleGroups.focusSection(focusedHost, 'Extract Customers.0'), focusedSection,
  'the focus helper must tolerate Hop copy suffixes and case differences');
assert.strictEqual(focusedSection.open, true);
assert.strictEqual(focusedScrolled, true);
assert.strictEqual(focusedSummary, true, 'the focused section heading must receive keyboard focus');
assert.strictEqual(focusedClasses.has('job-console-section-focus'), true);
assert.strictEqual(focusedHost.__jobSeekerConsoleState.openById['hop-step-1'], true,
  'a focused section must stay open across live console redraws');
assert.strictEqual(focusedHost.__jobSeekerConsoleState.focusedOwner, 'extract customers',
  'live redraws must retain the focused owner while the focus animation is active');

console.log('Job console grouping tests passed.');
