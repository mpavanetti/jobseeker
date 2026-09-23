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

// 1. The setup wizard is gone for good. It rewrote application/config/config.json
// with the Jenkins credentials, opened server-side database connections to any
// host the form supplied, and enumerated Jenkins - and nothing linked to it.
// Runtime configuration now comes from .env and the Kubernetes ConfigMap.
for (const path of [
  'application/controllers/Setup.php',
  'application/views/setup.php',
  'application/views/setupDatabase.php',
  'application/views/setupEnv.php',
  'application/views/setupJenkins.php',
  'application/views/includes/setupHeader.php',
  'application/views/includes/setupFooter.php'
]) {
  ok(path + ' must stay deleted', !fs.existsSync(path));
}
ok('no route may resolve to a Setup controller',
  !/=>?\s*["'][Ss]etup\//.test(read('application/config/routes.php')));
ok('CI core Controller no longer fetches config.json over HTTP',
  !read('system/core/Controller.php').includes('config.json'));

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

// 6. Every escapeHtml()-style helper must escape quotes, not just angle brackets.
//    jQuery's .text()/.html() round-trip leaves " and ' untouched, so a helper
//    built on it lets an attacker-controlled value (for example the <name>
//    inside an uploaded Apache Hop .hwf/.hpl, surfaced as execution.name) break
//    out of an HTML attribute such as data-hop-name="..." and inject a handler.
const escapeHelperFiles = [
  'application/views/hop.php',
  'application/views/jenkinsExecutors.php',
  'application/views/contextPromotion.php',
  'application/views/jobCreation.php',
  'application/views/tmf.php',
  'application/views/emailSettings.php',
  'assets/js/visualization-datasources.js',
  'assets/js/job-dependencies.js',
  'assets/js/visualization-studio.js',
  'assets/js/context-details.js',
  'assets/js/job-inspect-modal.js',
  'assets/js/job-environment.js',
  'assets/js/dashboard.js'
];
// A `return $('<div>').text(...).html()` that is not followed by quote escaping.
const unsafeRoundTrip = /return \$\('<div>'\)\.text\([^;]*?\)\.html\(\)\s*;/;
for (const file of escapeHelperFiles) {
  ok(file + ' has no quote-unsafe escape helper', !unsafeRoundTrip.test(read(file)));
}

// 7. profile.php renders stored user fields into HTML attributes. value="" goes
//    through set_value() (which escapes), but placeholder="" and the hidden
//    userId did not, so a stored name/mobile/email could break out of the
//    attribute and inject a handler on the victim's own profile page.
const profile = read('application/views/profile.php');
for (const field of ['$name', '$userId', '$mobile', '$email']) {
  ok('profile.php escapes ' + field + ' in attribute output',
    !new RegExp('="<\\?php echo \\' + field + '; \\?>"').test(profile));
}

// 8. Cookie and header hardening that CodeIgniter 3.1.10 cannot express itself.
//    CI sets its cookies through the positional setcookie() and
//    session_set_cookie_params() signatures, neither of which takes a SameSite
//    argument, so the session cookie gets it from the PHP ini instead. Browsers
//    treat an unset SameSite as Lax, but only after a grace period on top-level
//    POSTs - declaring it closes that window.
const phpIni = read('docker/php/security.ini');
ok('the session cookie declares SameSite', /^\s*session\.cookie_samesite\s*=\s*Lax\s*$/m.test(phpIni));
ok('PHP does not advertise its version', /^\s*expose_php\s*=\s*Off\s*$/m.test(phpIni));
ok('the php image installs the hardening ini',
  read('docker/php_image').includes('COPY docker/php/security.ini /usr/local/etc/php/conf.d/'));

// 9. The Jenkins credential must not be reachable from the tracked runtime
//    config. scripts/test-jenkins-runtime-config.js covers the full contract;
//    this is the security-relevant half, asserted here so a security run alone
//    still catches it.
ok('config.json carries no Jenkins credential',
  !/"(username|token)"\s*:/.test(read('application/config/config.json')));
ok('BaseController reads the Jenkins credential from the environment only',
  !/\$config->jenkins->(username|token)/.test(read('application/libraries/BaseController.php')));

// 10. Uploads: a .php is refused whatever the caller's allowlist says.
//     getUploadedFile() only consults an allowlist when one is supplied, and
//     Upload::do_upload() builds its list from a database column - a row with no
//     usable extension in it left every type permitted.
const uploads = read('application/libraries/BaseController.php');
ok('an unconditional web-executable deny list exists',
  /private static \$webExecutableUploadExtensions = array\(/.test(uploads));
ok('the deny list is applied regardless of the caller allowlist',
  /in_array\(\$extension, self::\$webExecutableUploadExtensions, TRUE\)/.test(uploads));
const denyList = (uploads.match(/\$webExecutableUploadExtensions = array\(([\s\S]*?)\);/) || [])[1] || '';
for (const ext of ['php', 'phtml', 'phar', 'htaccess']) {
  ok('uploads refuse .' + ext, new RegExp("'" + ext + "'").test(denyList));
}
// JobSeeker runs operator-authored Python and shell, so these are legitimate
// uploads (JobCreation allows py for python jobs and sh for bash jobs). The
// deny list must never grow to cover them or job creation breaks.
for (const ext of ['py', 'sh', 'zip', 'hpl', 'hwf', 'csv']) {
  ok('uploads still accept .' + ext, !new RegExp("'" + ext + "'").test(denyList));
}

// 11. Nothing under repository/ is served. Roughly a dozen call sites fall back
//     to FCPATH.'repository' - inside the document root - when jenkins_home is
//     unset, which would put uploads where nginx can reach them.
const nginxConf = read('nginx/default.conf');
ok('nginx refuses to serve repository/',
  /location ~ \^\/\(application\|system\|repository\)\/ \{/.test(nginxConf));
const denyBlock = nginxConf.indexOf('location ~ ^/(application|system|repository)/');
const phpBlock = nginxConf.indexOf('location ~* \\.php$ {');
ok('the deny block is matched before the php handler',
  denyBlock !== -1 && phpBlock !== -1 && denyBlock < phpBlock);

// 12. The application environment fails closed. Defaulting to 'development'
//     turns on error_reporting(-1) and display_errors, so a deployment that
//     forgot CI_ENV served warnings and stack traces to visitors.
ok('ENVIRONMENT defaults to production when CI_ENV is unset',
  /define\('ENVIRONMENT', isset\(\$_SERVER\['CI_ENV'\]\) \? \$_SERVER\['CI_ENV'\] : 'production'\);/.test(read('index.php')));

console.log('Security hardening regression checks passed (' + checks + ' assertions).');
