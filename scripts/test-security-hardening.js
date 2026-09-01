'use strict';

// Source-level regression guards for the 2026-09 security hardening pass.
// See doc/jobseeker/Security/README.md.

const fs = require('fs');
const assert = require('assert');

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

let checks = 0;
function ok(label, condition) {
  assert(condition, label);
  checks++;
}

// 1. The setup wizard must guard access once the instance is provisioned.
const setup = read('application/controllers/Setup.php');
ok('Setup calls guardSetupAccess in the constructor',
  /function __construct[\s\S]*?guardSetupAccess\s*\(\s*\)/.test(setup));
ok('Setup.guardSetupAccess redirects anonymous callers to login',
  /guardSetupAccess[\s\S]*?redirect\('login'\)/.test(setup));
ok('Setup.guardSetupAccess requires the admin role',
  /guardSetupAccess[\s\S]*?ROLE_ADMIN/.test(setup));
ok('Setup still allows first-run bootstrap before any user exists',
  /table_exists\('tbl_users'\)[\s\S]*?count_all_results\('tbl_users'\)/.test(setup));
ok('Setup no longer reflects the Jenkins authorization blob',
  !setup.includes('jenkins_authorization'));

// 2. The Jenkins credential must never be handed to the browser.
ok('BaseController does not expose jenkins_authorization',
  !read('application/libraries/BaseController.php').includes("'jenkins_authorization'"));
for (const view of ['application/views/jobList.php', 'application/views/jobCreation.php', 'application/views/tmf.php']) {
  ok(view + ' has no jenkins_authorization variable', !read(view).includes('jenkins_authorization'));
}

// 3. Reflected search term is escaped.
ok('users.php escapes the reflected search term',
  read('application/views/users.php').includes('value="<?php echo html_escape($searchText); ?>"'));

// 4. Production secret hygiene is checked.
const config = read('application/config/config.php');
ok('config.php checks for placeholder / short application secrets',
  config.includes('is still set to a shipped placeholder value') &&
  config.includes('JOBSEEKER_CONNECTOR_API_TOKEN'));

// 5. CSRF stays enabled; only the two self-authenticating routes are excluded.
ok('csrf_protection is enabled', /\$config\['csrf_protection'\]\s*=\s*TRUE/.test(config));
ok('csrf_exclude_uris is limited to jenkins/proxy and connector-runtime',
  /csrf_exclude_uris'\]\s*=\s*array\('jenkins\/proxy',\s*'connector-runtime'\)/.test(config));

console.log('Security hardening regression checks passed (' + checks + ' assertions).');
