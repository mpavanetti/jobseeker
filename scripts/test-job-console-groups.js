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

console.log('Job console grouping tests passed.');
