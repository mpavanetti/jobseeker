const assert = require('assert');
const fs = require('fs');
const path = require('path');

const jobCreation = [
	'application/controllers/JobCreation.php',
	...fs.readdirSync('application/controllers/concerns')
		.filter(file => file.startsWith('JobCreation') && file.endsWith('.php'))
		.map(file => path.join('application/controllers/concerns', file))
].map(file => fs.readFileSync(file, 'utf8')).join('\n');
const controller = fs.readFileSync('application/controllers/ConnectorRuntime.php', 'utf8');
const settingsController = fs.readFileSync('application/controllers/DbSettings.php', 'utf8');
const model = fs.readFileSync('application/models/DbSettings_model.php', 'utf8');
const view = fs.readFileSync('application/views/connectors.php', 'utf8');
const runnerTrait = fs.readFileSync('application/controllers/concerns/JenkinsRunnerTrait.php', 'utf8');
const sdk = fs.readFileSync('application/third_party/python/jobseeker_sdk/src/jobseeker/__init__.py', 'utf8');
const conntest = fs.readFileSync('application/third_party/python/jobseeker_sdk/src/jobseeker/conntest.py', 'utf8');
const vizModel = fs.readFileSync('application/models/Visualization_model.php', 'utf8');
const vizController = fs.readFileSync('application/controllers/Visualization.php', 'utf8');

assert(jobCreation.includes('jobseeker-connector materialize'));
assert(jobCreation.includes('unset JOBSEEKER_CONNECTOR_API_URL JOBSEEKER_CONNECTOR_API_TOKEN'));
assert(jobCreation.includes('.source-environment-variables'));
assert(jobCreation.includes('unset "$JOBSEEKER_SECRET_VARIABLE"'));
assert(jobCreation.includes('JOBSEEKER_CONNECTORS_VOLUME:/run/jobseeker-connectors:ro'));
assert(jobCreation.includes('docker volume rm "$JOBSEEKER_CONNECTORS_VOLUME"'));
assert(jobCreation.includes('rm -rf "$JOBSEEKER_CONNECTORS_DIR"'));
assert.strictEqual((jobCreation.match(/JOBSEEKER_CONNECTORS_DIR=\/run\/jobseeker-connectors/g) || []).length, 3);
assert.strictEqual((jobCreation.match(/JOBSEEKER_CONNECTORS_VOLUME:\/run\/jobseeker-connectors:ro/g) || []).length, 3);
assert(!jobCreation.includes('jobseeker-local-connector-token'));

assert(controller.includes("hash_equals($expected"));
assert(controller.includes("set_header('Cache-Control: no-store"));
assert(controller.includes("method(TRUE) !== 'POST'"));
assert(controller.includes("(object) array_map"));
assert(settingsController.includes("'password' => ''"));
assert(settingsController.includes('selectedGlobalEnvironment'));
assert(settingsController.includes('$existing ? (string) $existing->environment : $this->selectedGlobalEnvironment()'));
assert(settingsController.includes('authenticationTypes'));
assert(settingsController.includes('fieldMappings'));
assert(settingsController.includes("post('clear_local_secrets')"));
assert(settingsController.includes('&& ! $clearLocalSecrets'));
assert(settingsController.includes('public function testConnector'));
assert(settingsController.includes("'sas_token' => 'Azure SAS token'"));
assert(view.includes('connectorHelpModal'));
assert(view.includes('testConnector'));
assert(view.includes('environment_mappings'));
assert(view.includes('azure_secret_mappings'));
assert(view.includes('aws_field_mappings'));
assert(view.includes('name="clear_local_secrets"'));
assert(view.includes('pattern="[a-z0-9\\-]+"'));
assert(!view.includes('<select class="form-control" id="environment"'));
assert(model.includes('secret_encrypted'));
assert(!model.includes("select('*')"));

// --- Connection-test framework (SDK) ---
assert(conntest.includes('class ConnectionTestResult'));
assert(conntest.includes('def test_connector('));
assert(/def _test_mysql\(/.test(conntest) && /def _test_pgsql\(/.test(conntest));
assert(conntest.includes('DRIVER_MISSING') && conntest.includes('_tcp_probe'));
assert(conntest.includes('def _sanitize('), 'connection-test messages must be scrubbed of secrets');
assert(sdk.includes('def test(self, timeout'), 'Connector must expose a live .test() method');
assert(sdk.includes('_load_conntest()'));
assert(sdk.includes('"ConnectionTestResult"') && sdk.includes('"test_connector"'), 'conntest helpers must be re-exported');
assert(sdk.includes('commands.add_parser("test"'), 'jobseeker-connector must offer a test subcommand');

// --- Worker-run connector test trigger (PHP) ---
assert(runnerTrait.includes('trait JenkinsRunnerTrait'));
assert(runnerTrait.includes('function saveDisposableJenkinsJob') && runnerTrait.includes('function waitForDisposableBuild'));
assert(runnerTrait.includes('createTextNode'), 'job XML must be built with text nodes, not raw interpolation');
assert(runnerTrait.includes('function runConnectorConnectionTest'), 'the worker connection test must be shared via the trait');
assert(runnerTrait.includes('python3 -m jobseeker.conntest'));
assert(runnerTrait.includes('__jobseeker_conn_test_'));
assert(runnerTrait.includes('deleteDisposableJenkinsJob'), 'the disposable test job must be cleaned up');
assert(settingsController.includes('use JenkinsRunnerTrait;'));
assert(settingsController.includes('function liveConnectorTest') && settingsController.includes('runConnectorConnectionTest('));
assert(settingsController.includes("$mode === 'quick'"), 'the fast TCP probe must remain available');

// --- Built-in + Insight Studio merge (model) ---
assert(model.includes('BUILTIN_MARIADB_KEY') && model.includes("'jobseeker-mariadb'"));
assert(model.includes('function ensureBuiltinConnectors'));
assert(model.includes('function migrateVisualizationConnections'));
assert(model.includes('migrated_connector_id'));
assert(vizModel.includes("from('database_settings connection')") || vizModel.includes("join('database_settings connection'"));
assert(!vizModel.includes("join('visualization_connections connection'"), 'Insight Studio must not join the retired table');
assert(vizModel.includes('hydrateCatalogConnection') && vizModel.includes('password_plain'));
assert(!vizModel.includes('function saveConnection') && !vizModel.includes('function deleteConnection'));
assert(!vizController.includes('function saveConnection') && !vizController.includes('function deleteConnection'));
assert(vizController.includes('manageConnectorsUrl'));

console.log('Job connector runtime tests passed.');
