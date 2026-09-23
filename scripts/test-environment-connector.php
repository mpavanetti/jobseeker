<?php
define('JOBSEEKER_ENVIRONMENT_CONNECTOR_TEST', TRUE);
require dirname(__DIR__).'/application/libraries/EnvironmentConnector.php';

$checks = 0;
function connector_env_assert($condition, $message)
{
    global $checks;
    if (! $condition) {
        fwrite(STDERR, 'FAIL: '.$message."\n");
        exit(1);
    }
    $checks++;
}

class FakeEnvironmentConnector extends EnvironmentConnector
{
    public $environment = array();
    protected function candidates() { return $this->environment; }
}

$catalog = new FakeEnvironmentConnector();
$catalog->environment = array(
    'JOBSEEKER_CONNECTOR_WAREHOUSE_TYPE' => 'mysql',
    'JOBSEEKER_CONNECTOR_WAREHOUSE_HOST' => 'db.internal',
    'JOBSEEKER_CONNECTOR_WAREHOUSE_DATABASE' => 'analytics',
    'JOBSEEKER_CONNECTOR_WAREHOUSE_USERNAME' => 'etl-user',
    'JOBSEEKER_CONNECTOR_WAREHOUSE_PASSWORD' => 'top-secret',
    'JOBSEEKER_CONNECTOR_WAREHOUSE_ENVIRONMENT' => 'DEV',
    'JOBSEEKER_CONNECTOR_WAREHOUSE_JOB' => '*',
    // Platform variables must never manufacture a connector.
    'JOBSEEKER_CONNECTOR_API_TOKEN' => 'internal-token',
    'JOBSEEKER_CONNECTOR_HELPER' => '/usr/local/bin/jobseeker-connector',
);

$public = $catalog->rows(FALSE);
connector_env_assert(count($public) === 1, 'Only a complete TYPE group becomes a connector.');
connector_env_assert($public[0]['connector_key'] === 'warehouse', 'The variable group becomes the connector key.');
connector_env_assert($public[0]['db_type'] === 'mysql' && $public[0]['port'] === '3306', 'Type defaults are applied.');
connector_env_assert($public[0]['environment'] === 'DEV' && $public[0]['job_name'] === '*', 'Scope is retained.');
connector_env_assert($public[0]['readOnly'] === TRUE && $public[0]['secret_backend'] === 'deployment', 'The row is deployment-owned.');
connector_env_assert($public[0]['secretFields'] === array('username', 'password'), 'Only secret field names reach the UI.');
connector_env_assert(! isset($public[0]['_secret_values']), 'Secret values never reach a UI row.');
connector_env_assert(strpos(json_encode($public[0]), 'top-secret') === FALSE, 'A password cannot leak through the public row.');

$runtime = $catalog->rows(TRUE);
connector_env_assert($runtime[0]['_secret_values']['username'] === 'etl-user', 'The runtime receives the username.');
connector_env_assert($runtime[0]['_secret_values']['password'] === 'top-secret', 'The runtime receives the password.');
connector_env_assert(strpos($runtime[0]['secret_reference'], 'top-secret') === FALSE, 'References contain variable names, not values.');

$catalog = new FakeEnvironmentConnector();
$catalog->environment = array(
    'JOBSEEKER_CONNECTOR_VENDOR_API_TYPE' => 'http_api',
    'JOBSEEKER_CONNECTOR_VENDOR_API_HOST' => 'api.example.test',
    'JOBSEEKER_CONNECTOR_VENDOR_API_TOKEN' => 'abc',
    'JOBSEEKER_CONNECTOR_VENDOR_API_DESCRIPTION' => 'Vendor service',
);
$rows = $catalog->rows(TRUE);
connector_env_assert(count($rows) === 1 && $rows[0]['connector_key'] === 'vendor-api', 'Underscores in names become dashes.');
connector_env_assert($rows[0]['auth_type'] === 'token', 'Authentication is inferred when omitted.');
connector_env_assert($rows[0]['port'] === '443', 'HTTP API gets its conventional port.');

$catalog = new FakeEnvironmentConnector();
$catalog->environment = array(
    'JOBSEEKER_CONNECTOR_BAD_TYPE' => 'made-up',
    'JOBSEEKER_CONNECTOR_BAD_HOST' => 'example.test',
    'JOBSEEKER_CONNECTOR_NOHOST_TYPE' => 'mysql',
    'JOBSEEKER_CONNECTOR_INVALID_TYPE' => 'mysql',
    'JOBSEEKER_CONNECTOR_INVALID_HOST' => "bad\nhost",
);
connector_env_assert($catalog->rows(TRUE) === array(), 'Invalid or incomplete definitions fail closed.');

echo "Environment connector checks passed ({$checks} assertions).\n";
