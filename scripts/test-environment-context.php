<?php
/**
 * Behavioural tests for EnvironmentContext: Context values a deployment
 * supplies through `JOBSEEKER_CONTEXT_*`, which are readable everywhere a
 * stored Context value is but cannot be edited from the UI.
 */
define('JOBSEEKER_ENVIRONMENT_CONTEXT_TEST', TRUE);
require dirname(__DIR__).'/application/libraries/EnvironmentContext.php';

$checks = 0;

function env_assert($condition, $message)
{
    global $checks;
    if (! $condition) {
        fwrite(STDERR, 'FAIL: '.$message."\n");
        exit(1);
    }
    $checks++;
}

/** Feeds a fixed environment in place of getenv(). */
class FakeEnvironmentContext extends EnvironmentContext
{
    public $environment = array();

    protected function candidates()
    {
        return $this->environment;
    }
}

// --- 1. Only the context prefix is exposed ----------------------------------
$context = new FakeEnvironmentContext();
$context->environment = array(
    'JOBSEEKER_CONTEXT_BATCH_SIZE' => '500',
    'JOBSEEKER_CONTEXT_WAREHOUSE_SCHEMA' => 'analytics',
    'JOBSEEKER_CONTEXT_DATABASE_PASSWORD' => 'must-not-leak',
    'JOBSEEKER_CONTEXT_API_TOKEN' => 'must-not-leak-either',
    // None of these may ever be shown or exported.
    'JOBSEEKER_DB_PASSWORD' => 'hunter2',
    'JOBSEEKER_JENKINS_TOKEN' => 'secret-token',
    'JOBSEEKER_HOP_ENABLED' => 'true',
    'AWS_SECRET_ACCESS_KEY' => 'nope',
    'PATH' => '/usr/bin',
);

$values = $context->values();
env_assert($values === array('batch_size' => '500', 'warehouse_schema' => 'analytics'),
    'Only JOBSEEKER_CONTEXT_* may be read: '.json_encode($values));
env_assert(! isset($values['db_password']) && ! isset($values['jenkins_token']),
    'A deployment credential must never become a context value.');
env_assert(! $context->has('hop_enabled'),
    'Platform configuration must not be mistaken for a context value.');
env_assert(! $context->has('database_password') && ! $context->has('api_token'),
    'Credential-shaped context keys must be routed to Connectors instead of displayed.');

// --- 2. Keys and variable names round-trip ----------------------------------
env_assert($context->value('batch_size') === '500', 'A value must be readable by its key.');
env_assert($context->value('BATCH_SIZE') === '500', 'Key lookup must not care about case.');
env_assert($context->value('missing', 'fallback') === 'fallback', 'A missing key returns the default.');
env_assert($context->variableName('batch_size') === 'JOBSEEKER_CONTEXT_BATCH_SIZE',
    'The page must be able to tell an operator which variable to set.');
env_assert($context->variableName('quality.threshold') === 'JOBSEEKER_CONTEXT_QUALITY_THRESHOLD',
    'Dots and dashes in a key map to underscores in the variable.');

// --- 3. Rubbish is refused rather than rendered ------------------------------
$context = new FakeEnvironmentContext();
$context->environment = array(
    'JOBSEEKER_CONTEXT_' => 'no key at all',
    'JOBSEEKER_CONTEXT_9BAD' => 'starts with a digit',
    'JOBSEEKER_CONTEXT_HAS SPACE' => 'space in the key',
    'JOBSEEKER_CONTEXT_CONTROL' => "line\nbreak",
    'JOBSEEKER_CONTEXT_GOOD' => 'kept',
);
env_assert($context->values() === array('good' => 'kept'),
    'Only well-formed keys and values survive: '.json_encode($context->values()));

$context = new FakeEnvironmentContext();
$context->environment = array('JOBSEEKER_CONTEXT_LONG' => str_repeat('x', 5000));
env_assert(strlen($context->value('long')) === EnvironmentContext::MAX_VALUE_LENGTH,
    'An oversized value is truncated rather than rendered whole.');

// --- 4. Page rows ------------------------------------------------------------
$context = new FakeEnvironmentContext();
$context->environment = array(
    'JOBSEEKER_CONTEXT_BATCH_SIZE' => '500',
    'JOBSEEKER_CONTEXT_REGION' => 'eu-west-1',
);
$rows = $context->rows();
env_assert(count($rows) === 2, 'Every exposed value becomes a row.');
env_assert($rows[0]->ContextKey === 'batch_size' && $rows[1]->ContextKey === 'region',
    'Rows are ordered by key: '.$rows[0]->ContextKey.', '.$rows[1]->ContextKey);
env_assert($rows[0]->readOnly === TRUE, 'An environment row must be marked read-only.');
env_assert($rows[0]->source === 'environment', 'An environment row must say where it came from.');
env_assert($rows[0]->Environment === 'ALL',
    'A deployment value is not scoped to one runtime environment.');
env_assert((int) $rows[0]->isEncrypted === 0 && (int) $rows[0]->IsActive === 1,
    'An environment row is a plain, active value.');
env_assert(strpos($rows[0]->Description, 'JOBSEEKER_CONTEXT_BATCH_SIZE') !== FALSE,
    'The row must name the variable that supplies it.');
env_assert($rows[0]->shadowed === FALSE, 'Nothing shadows it when no stored value exists.');

// A stored Context value for the same key is what a job resolves, so the row
// has to say the environment value is not the effective one.
$rows = $context->rows(array('BATCH_SIZE'));
env_assert($rows[0]->shadowed === TRUE, 'A stored value of the same key shadows the environment one.');
env_assert($rows[1]->shadowed === FALSE, 'Only the matching key is shadowed.');

// --- 5. Reaching a job's runtime ---------------------------------------------
$lines = $context->exportLines();
env_assert(count($lines) === 2, 'Every value is exported into a job.');
env_assert(in_array("export JOBSEEKER_CONTEXT_BATCH_SIZE='500'", $lines, TRUE),
    'The export must use the variable name: '.json_encode($lines));
env_assert($context->variableNames() === array('JOBSEEKER_CONTEXT_BATCH_SIZE', 'JOBSEEKER_CONTEXT_REGION'),
    'The container needs the names forwarded to it.');

$context = new FakeEnvironmentContext();
$context->environment = array('JOBSEEKER_CONTEXT_TRICKY' => "it's a value; rm -rf /");
$lines = $context->exportLines();
env_assert(count($lines) === 1, 'A hostile value still exports as one line.');
env_assert(strpos($lines[0], "'it'\\''s a value; rm -rf /'") !== FALSE,
    'A value must be shell-escaped, not interpolated: '.$lines[0]);

// --- 6. An empty environment is not an error ---------------------------------
$context = new FakeEnvironmentContext();
$context->environment = array();
env_assert($context->values() === array() && $context->rows() === array() && $context->exportLines() === array(),
    'A deployment that supplies nothing produces nothing.');

echo "Environment context checks passed ({$checks} assertions).\n";
