'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

const controller = read('application/libraries/BaseController.php');
const runtime = read('application/controllers/ConnectorRuntime.php');
const header = read('application/views/includes/header.php');
const compose = read('docker-compose.yml');
const standaloneCompose = read('docker-compose.standalone.yml');
const kubernetesBase = read('deploy/kubernetes/base/config.yaml');
const kubernetesStandalone = read('deploy/kubernetes/overlays/standalone/config.yaml');
const standaloneCasc = read('docker/jenkins/casc-kubernetes-standalone.yaml');

assert.match(controller, /JOBSEEKER_DEPLOYMENT_MODE/);
assert.match(controller, /JOBSEEKER_STANDALONE_ENVIRONMENT/);
assert.match(controller, /ensureJenkinsJobEnvironmentAgentAssignment[\s\S]*This standalone deployment only executes/);
assert.match(runtime, /jobSeekerEnvironmentIsAllowed/);
assert.match(header, /jobseekerDeploymentMode[\s\S]*standalone/);
assert.match(header, /standaloneMode \? standaloneEnvironment : 'all'/);
assert.match(header, /jobseekerDeploymentMode !== 'standalone'.*All environments/);

assert.match(compose, /JOBSEEKER_DEPLOYMENT_MODE=\$\{JOBSEEKER_DEPLOYMENT_MODE:-multi\}/);
assert.match(standaloneCompose, /JOBSEEKER_DEPLOYMENT_MODE: standalone/);
assert.match(standaloneCompose, /jobseeker-env-standalone/);
assert.doesNotMatch(standaloneCompose, /jenkins-agent-(?:dev|qa|uat|prod):/);

assert.match(kubernetesBase, /JOBSEEKER_DEPLOYMENT_MODE: multi/);
assert.match(kubernetesStandalone, /JOBSEEKER_DEPLOYMENT_MODE: standalone/);
assert.match(standaloneCasc, /name: "jobseeker-standalone"/);
assert.strictEqual((standaloneCasc.match(/name: "jobseeker-(?:standalone|dev|qa|uat|prod)"/g) || []).length, 1);

console.log('Deployment mode contracts verified.');
