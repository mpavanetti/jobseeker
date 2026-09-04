const assert = require('assert');
const consoleGroups = require('../assets/js/job-console-groups');

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

console.log('Job console grouping tests passed.');
