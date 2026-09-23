'use strict';

// Wiring for Context values supplied by the deployment through
// JOBSEEKER_CONTEXT_* in .env. The library itself is covered behaviourally by
// scripts/test-environment-context.php; this covers the places that have to
// agree with it: the page, the generated job command, the SDK resolver, and the
// compose file that actually puts .env inside the container.

const assert = require('assert');
const fs = require('fs');

const read = (p) => fs.readFileSync(p, 'utf8');
let checks = 0;
function ok(label, condition) {
  assert(condition, label);
  checks++;
}

// --- 1. Only the context prefix is ever exposed ------------------------------
const library = read('application/libraries/EnvironmentContext.php');
ok('the library exists', library.indexOf('class EnvironmentContext') !== -1);
ok('one prefix, declared once', library.indexOf("const PREFIX = 'JOBSEEKER_CONTEXT_'") !== -1);
ok('the prefix is what filters the environment', /strpos\(\$name, self::PREFIX\) !== 0/.test(library));
ok('the reason is written down, not just the rule',
  library.indexOf('JOBSEEKER_DB_PASSWORD') !== -1 && library.indexOf('Connector') !== -1);
ok('keys are validated before they reach a page', library.indexOf('KEY_PATTERN') !== -1);
ok('values are single line', /preg_match\('\/\[\\x00-\\x1F\\x7F\]\/', \$value\)/.test(library));
ok('values are bounded', library.indexOf('MAX_VALUE_LENGTH') !== -1 && library.indexOf('MAX_VALUES') !== -1);
ok('exports are shell-escaped', /escapeshellarg\(\$value\)/.test(library));
ok('credential-shaped context keys are refused', library.indexOf('SENSITIVE_KEY_PATTERN') !== -1 &&
  library.indexOf('$this->isSensitiveKey($key)') !== -1);

// --- 2. The page shows them, read only ---------------------------------------
const controller = read('application/controllers/Context.php');
ok('the page is given the environment values', controller.indexOf("$data[\"environmentContexts\"]") !== -1);
ok('it tells the library which keys are already stored',
  /rows\(\$storedKeys\)/.test(controller));

const view = read('application/views/contextDetails.php');
ok('the rows join the table', view.indexOf('$environmentContextRows') !== -1);
ok('a row knows it is read only', view.indexOf('$isEnvironmentRow = ! empty($record->readOnly)') !== -1);
ok('an environment row gets no edit link and no delete button',
  /if \(\$isEnvironmentRow\) \{ \?>\s*<span class="context-row-meta">Set in <code>\.env<\/code><\/span>/.test(view));
ok('a stored value of the same key is flagged as the one that wins',
  view.indexOf('Overridden') !== -1 && view.indexOf('$record->shadowed') !== -1);
ok('the row says which variable supplies it', view.indexOf('$record->variableName') !== -1);
ok('the protection column says read only', view.indexOf('context-badge-readonly') !== -1);

const css = read('assets/dist/css/context-details.css');
ok('the read-only badge is styled', css.indexOf('.context-badge-readonly') !== -1);
ok('the row is distinguishable', css.indexOf('.context-row-environment') !== -1);
ok('the stylesheet is cache-busted past the previous version',
  /context-details\.css\?v=([7-9]|\d{2,})/.test(view));

// --- 3. They reach a job ------------------------------------------------------
const trait = read('application/controllers/concerns/JobCreationExecutionTrait.php');
ok('the generator can read them', trait.indexOf('function environmentContextLines()') !== -1);
ok('it says why they are baked in rather than inherited',
  trait.indexOf('does not inherit the app') !== -1);
['buildLinuxCommandExecutionCommand', 'buildShellScriptExecutionCommand', 'buildPythonExecutionCommand', 'buildHopExecutionCommand']
  .forEach((builder) => {
    const start = trait.indexOf('function ' + builder + '(');
    ok(builder + ' exists', start > 0);
  });
ok('every runtime that merges the data-asset lines also merges the context lines',
  trait.split('dataAssetsRuntimeLines(').length - 1 === trait.split('environmentContextLines()').length - 1 + 1 ||
  (trait.match(/environmentContextLines\(\)/g) || []).length >= 4);
ok('a containerised job receives the same names', trait.indexOf('function dockerContextEnvLines()') !== -1);
ok('and they are forwarded on the docker run', (trait.match(/dockerContextEnvLines\(\)/g) || []).length >= 4);

// --- 4. The SDK resolves them, behind the database ---------------------------
const sdk = read('application/third_party/python/jobseeker_sdk/src/jobseeker/__init__.py');
ok('the SDK knows the prefix', sdk.indexOf('CONTEXT_ENVIRONMENT_PREFIX = "JOBSEEKER_CONTEXT_"') !== -1);
ok('it maps a key to a variable name', sdk.indexOf('def context_environment_variable(') !== -1);
ok('it reads one from the environment', sdk.indexOf('def context_from_environment(') !== -1);
ok('the SDK also refuses credential-shaped context keys', sdk.indexOf('CONTEXT_SENSITIVE_KEY') !== -1 &&
  /CONTEXT_SENSITIVE_KEY\.search/.test(sdk));
ok('the database is asked first',
  /value = self\.transport\.get_context\(payload\)[\s\S]{0,400}value = context_from_environment\(key\)/.test(sdk));
ok('an unreachable database still lets the environment answer',
  /transport_error = error[\s\S]{0,120}value = None/.test(sdk));
ok('but an outage is not hidden behind a default when nothing answers',
  sdk.indexOf('Context lookup failed for') !== -1);
ok('the precedence is written down', sdk.indexOf("deliberate override") !== -1);
ok('both helpers are public', sdk.indexOf('"context_environment_variable"') !== -1 &&
  sdk.indexOf('"context_from_environment"') !== -1);

// --- 5. The deployment actually supplies them --------------------------------
const compose = read('docker-compose.yml');
ok('compose puts .env inside the php container', /env_file:[\s\S]{0,400}path: \.\/\.env\b/.test(compose));
ok('and says why the list below cannot cover it', compose.indexOf('JOBSEEKER_CONTEXT_') !== -1);

const envExample = read('.env.example');
ok('the convention is documented where an operator will set it',
  envExample.indexOf('JOBSEEKER_CONTEXT_<KEY>') !== -1);
ok('the example shows the key mapping', envExample.indexOf('JOBSEEKER_CONTEXT_BATCH_SIZE') !== -1);
ok('it says they are read only and why', envExample.indexOf('read-only in the UI') !== -1);
ok('it warns that secrets belong in a connector', envExample.indexOf('Connector') !== -1);
ok('it documents that credential-shaped keys are ignored', envExample.indexOf('password, secret, token') !== -1);
ok('it states the override rule', envExample.indexOf('overrides the one from here') !== -1);
console.log('Context environment checks passed (' + checks + ' assertions).');
