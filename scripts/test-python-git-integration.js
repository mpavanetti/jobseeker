const assert = require('assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const childProcess = require('child_process');

const root = path.resolve(__dirname, '..');
const helper = path.join(root, 'docker/jenkins/jobseeker-git');
const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'jobseeker-git-test-'));

function write(file, value, mode = 0o600) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, value, { mode });
}

try {
  const connector = path.join(temporaryRoot, 'connector');
  const fakeBin = path.join(temporaryRoot, 'bin');
  const capture = path.join(temporaryRoot, 'capture');
  write(path.join(connector, 'host'), 'github.example.test\n');
  write(path.join(connector, 'auth_type'), 'token\n');
  write(path.join(connector, 'token'), 'private-token-value');
  write(path.join(fakeBin, 'git'), [
    '#!/bin/sh',
    'set -eu',
    'printf "%s\\n" "$@" > "$JOBSEEKER_TEST_CAPTURE/arguments"',
    'printf "%s" "$GIT_ASKPASS" > "$JOBSEEKER_TEST_CAPTURE/askpass-path"',
    '"$GIT_ASKPASS" "Username for repository" > "$JOBSEEKER_TEST_CAPTURE/username"',
    '"$GIT_ASKPASS" "Password for repository" > "$JOBSEEKER_TEST_CAPTURE/password"',
    ''
  ].join('\n'), 0o700);
  fs.mkdirSync(capture);

  const environment = {
    ...process.env,
    PATH: `${fakeBin}:${process.env.PATH}`,
    JOBSEEKER_TEST_CAPTURE: capture
  };
  const result = childProcess.spawnSync(helper, [
    'clone', '--connector-dir', connector, '--branch', 'main', '--',
    'https://github.example.test/acme/private.git', path.join(temporaryRoot, 'checkout')
  ], { env: environment, encoding: 'utf8' });
  assert.strictEqual(result.status, 0, result.stderr);
  assert.strictEqual(fs.readFileSync(path.join(capture, 'username'), 'utf8'), 'x-access-token');
  assert.strictEqual(fs.readFileSync(path.join(capture, 'password'), 'utf8'), 'private-token-value');
  const argumentsText = fs.readFileSync(path.join(capture, 'arguments'), 'utf8');
  assert(argumentsText.includes('https://github.example.test/acme/private.git'));
  assert(!argumentsText.includes('private-token-value'));
  assert(!result.stdout.includes('private-token-value'));
  assert(!result.stderr.includes('private-token-value'));
  assert(!fs.existsSync(fs.readFileSync(path.join(capture, 'askpass-path'), 'utf8')));

  const wrongHost = childProcess.spawnSync(helper, [
    'ls-remote', '--connector-dir', connector, '--',
    'https://gitlab.example.test/acme/private.git'
  ], { env: environment, encoding: 'utf8' });
  assert.strictEqual(wrongHost.status, 78);
  assert(wrongHost.stderr.includes('scoped to github.example.test'));

  const wrongTransport = childProcess.spawnSync(helper, [
    'ls-remote', '--connector-dir', connector, '--',
    'git@github.example.test:acme/private.git'
  ], { env: environment, encoding: 'utf8' });
  assert.strictEqual(wrongTransport.status, 78);
  assert(wrongTransport.stderr.includes('require an HTTP(S) repository URL'));

  const controller = fs.readFileSync(path.join(root, 'application/controllers/concerns/JobCreationExecutionTrait.php'), 'utf8');
  const form = fs.readFileSync(path.join(root, 'application/views/jobCreation.php'), 'utf8');
  assert(controller.includes('jobseeker-git clone --connector-dir'));
  assert(controller.includes('JOBSEEKER_GIT_CREDENTIAL_KEY'));
  assert(form.includes('name="pythonGitCredentialKey"'));
  assert(form.includes('shellExportValue(command, \'JOBSEEKER_GIT_CREDENTIAL_KEY\')'));

  console.log('Private Python Git integration tests passed.');
} finally {
  fs.rmSync(temporaryRoot, { recursive: true, force: true });
}
