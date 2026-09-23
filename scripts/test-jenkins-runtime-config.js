'use strict';

// Contract tests for how JobSeeker resolves its Jenkins connection.
//
// The rules these lock in, and why each one exists:
//
//   1. The Jenkins credential is never read from application/config/config.json.
//      That file is tracked in git, so a credential slot in it invites a real
//      token being committed.
//   2. The internal URL is environment-only. It is a container or service name,
//      so a value shipped in a tracked file is wrong for every deployment but
//      the one it was written for - and config.json used to ship
//      "http://localhost:8080/", which from inside the php container resolves
//      to the php container itself.
//   3. Server-side traffic and browser traffic use different roots. The browser
//      cannot reach the internal URL, so anything rendered into a page has to
//      go through jenkinsPublicUrl().
//   4. The public URL always ends in a slash, because every caller concatenates
//      a path that does not begin with one (jenkinsJobPath() returns "job/...").

const fs = require('fs');
const assert = require('assert');

const read = (p) => fs.readFileSync(p, 'utf8');
let checks = 0;
function ok(label, condition) {
  assert(condition, label);
  checks++;
}

const base = read('application/libraries/BaseController.php');
const runtimeConfig = JSON.parse(read('application/config/config.json'));

// --- 1. No credential may live in the tracked runtime config -----------------
for (const file of ['application/config/config.json', 'deploy/kubernetes/base/config.yaml']) {
  const body = read(file);
  ok(file + ' must not carry a Jenkins username', !/"username"\s*:/.test(body));
  ok(file + ' must not carry a Jenkins token', !/"token"\s*:/.test(body));
}
ok('config.json exposes no jenkins.username key', runtimeConfig.jenkins.username === undefined);
ok('config.json exposes no jenkins.token key', runtimeConfig.jenkins.token === undefined);
ok('BaseController never reads a credential from the runtime config',
  !/\$config->jenkins->(username|token)/.test(base));

// --- 2. The internal URL is environment-only ---------------------------------
const connection = base.match(/private function jenkinsConnection\(\)\s*\{[\s\S]*?\n\t\}/);
ok('jenkinsConnection() exists', connection !== null);
const connectionBody = connection[0];
ok('jenkinsConnection reads the internal URL from the environment',
  connectionBody.includes("getenv('JOBSEEKER_JENKINS_INTERNAL_URL')"));
ok('jenkinsConnection does not fall back to the runtime config URL',
  !/\$config->jenkins->url/.test(connectionBody));
ok('jenkinsConnection falls back to the public URL for a single-host install',
  connectionBody.includes('$this->jenkinsPublicUrl()'));
ok('jenkinsConnection builds the credential from the environment only',
  connectionBody.includes("getenv('JOBSEEKER_JENKINS_USER')") &&
  connectionBody.includes("getenv('JOBSEEKER_JENKINS_TOKEN')"));
ok('jenkinsConnection reports a missing credential rather than failing silently',
  /log_message\('error'[^)]*JOBSEEKER_JENKINS_USER/.test(connectionBody));
// Callers append '/' + path themselves, so the root must carry no trailing one.
ok('jenkinsConnection strips the trailing slash from the environment URL',
  connectionBody.includes("rtrim(trim((string) getenv('JOBSEEKER_JENKINS_INTERNAL_URL')), '/')"));
ok('jenkinsConnection strips the trailing slash from the public-URL fallback',
  connectionBody.includes("rtrim($this->jenkinsPublicUrl(), '/')"));

// --- 3. Browser traffic uses the public root ---------------------------------
const publicUrl = base.match(/protected function jenkinsPublicUrl\(\)\s*\{[\s\S]*?\n\t\}/);
ok('jenkinsPublicUrl() exists', publicUrl !== null);
const publicBody = publicUrl[0];
ok('jenkinsPublicUrl prefers JOBSEEKER_JENKINS_PUBLIC_URL',
  publicBody.includes("getenv('JOBSEEKER_JENKINS_PUBLIC_URL')"));
ok('jenkinsPublicUrl falls back to the runtime config URL',
  publicBody.includes('$config->jenkins->url'));
// Every caller does jenkins_url + jenkinsJobPath(name), and jenkinsJobPath()
// returns "job/<name>" with no leading slash, so a root without a trailing
// slash silently produces "http://host:8080job/<name>".
ok('jenkinsPublicUrl always returns a trailing slash',
  /return rtrim\(\$url, '\/'\) \. '\/';/.test(publicBody));
ok('the page global uses the public root, not the raw config value',
  /\$this->global \['jenkins_url'\] = \$this->jenkinsPublicUrl\(\);/.test(base));

// --- 4. The credential still never reaches the browser -----------------------
ok('BaseController sets no jenkins_username page global', !base.includes("'jenkins_username'"));
ok('BaseController sets no jenkins_token page global', !base.includes("'jenkins_token'"));

// --- 5. Dead config shapes stay dead -----------------------------------------
// Nothing ever wrote these camelCase keys; the camelCase names elsewhere in the
// codebase are JSON response fields, which is how they crept in here.
for (const alias of ['environmentSlots', 'environmentAgentsEnabled', 'environmentAgentLabels']) {
  ok('BaseController must not read $config->jenkins->' + alias,
    !new RegExp('\\$config->jenkins->' + alias).test(base));
}
ok('the removed authorization blob must not come back',
  runtimeConfig.jenkins.authorization === undefined && !base.includes('jenkins->authorization'));

// --- 6. The keys the runtime config still owns -------------------------------
// enabled and jenkins_home have no environment equivalent, and the two
// environment defaults are what an install with no environment at all falls
// back to, so all four have to stay readable.
ok('config.json still declares jenkins.enabled', runtimeConfig.jenkins.enabled !== undefined);
ok('config.json still declares jenkins.jenkins_home', runtimeConfig.jenkins.jenkins_home !== undefined);
ok('BaseController still reads the snake_case environment overrides',
  base.includes('$config->jenkins->environment_slots') &&
  base.includes('$config->jenkins->environment_agents_enabled') &&
  base.includes('$config->jenkins->environment_agent_labels'));

// --- 7. The runtime config no longer demands the deleted setup block ---------
ok('getRuntimeConfig does not require a setup block', !/empty\(\$config->setup\)/.test(base));

console.log('Jenkins runtime configuration contract tests passed (' + checks + ' assertions).');
