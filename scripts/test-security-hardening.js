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

// 5. CSRF stays enabled; only self-authenticating routes are excluded.
ok('csrf_protection is enabled', /\$config\['csrf_protection'\]\s*=\s*TRUE/.test(config));
ok('csrf_exclude_uris only lists bearer-token self-authenticating endpoints',
  /csrf_exclude_uris'\]\s*=\s*array\(('jenkins\/proxy',\s*'connector-runtime'|'jenkins\/proxy',\s*'connector-runtime',\s*'spark-runtime\/trigger',\s*'spark-runtime\/cancel\/\[0-9\]\+')\)/.test(config));
ok('every csrf_exclude_uris entry is a jenkins/proxy, connector-runtime or spark-runtime route',
  (config.match(/csrf_exclude_uris'\]\s*=\s*array\(([^)]*)\)/) || [, ''])[1]
    .split(',').map(s => s.trim().replace(/'/g, ''))
    .every(uri => uri === 'jenkins/proxy' || uri === 'connector-runtime' || /^spark-runtime\//.test(uri)));

// 6. Persistent Spark cluster lifecycle endpoints stay behind the session CSRF /
//    manager guard (they are NOT bearer-token endpoints, so must not be excluded).
const sparkClusters = read('application/controllers/SparkClusters.php');
ok('SparkClusters start/stop route through the manager + POST guard',
  /function startCluster\(\)\s*\{[\s\S]*?persistentClusterOr422\(TRUE\)/.test(sparkClusters) &&
  /function stopCluster\(\)\s*\{[\s\S]*?persistentClusterOr422\(TRUE\)/.test(sparkClusters) &&
  /function persistentClusterOr422\([\s\S]*?requireManagerPost/.test(sparkClusters));
ok('spark-clusters lifecycle routes are not CSRF-excluded',
  !/spark-clusters/.test((config.match(/csrf_exclude_uris'\]\s*=\s*array\(([^)]*)\)/) || [, ''])[1]));
ok('the nginx spark-persist proxy is port-range constrained',
  /location\s*~\s*"?\^\/spark-persist\/\(\?<sp_port>18\[0-9\]\{3\}\)/.test(read('nginx/default.conf')));
ok('SparkJobs::develop flushes the posted buffer with a size ceiling',
  /inline_code[\s\S]*?<=\s*200000/.test(read('application/controllers/SparkJobs.php')));

console.log('Security hardening regression checks passed (' + checks + ' assertions).');
