const assert = require('assert');
const fs = require('fs');

const jobCreation = fs.readFileSync('application/controllers/JobCreation.php', 'utf8');
const controller = fs.readFileSync('application/controllers/ConnectorRuntime.php', 'utf8');
const settingsController = fs.readFileSync('application/controllers/DbSettings.php', 'utf8');
const model = fs.readFileSync('application/models/DbSettings_model.php', 'utf8');
const view = fs.readFileSync('application/views/connectors.php', 'utf8');

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
assert(settingsController.includes("'password' => ''"));
assert(settingsController.includes('selectedGlobalEnvironment'));
assert(settingsController.includes('$existing ? (string) $existing->environment : $this->selectedGlobalEnvironment()'));
assert(settingsController.includes('authenticationTypes'));
assert(settingsController.includes('fieldMappings'));
assert(settingsController.includes('public function testConnector'));
assert(settingsController.includes("'sas_token' => 'Azure SAS token'"));
assert(view.includes('connectorHelpModal'));
assert(view.includes('testConnector'));
assert(view.includes('environment_mappings'));
assert(view.includes('azure_secret_mappings'));
assert(view.includes('aws_field_mappings'));
assert(!view.includes('<select class="form-control" id="environment"'));
assert(model.includes('secret_encrypted'));
assert(!model.includes("select('*')"));

console.log('Job connector runtime tests passed.');
