'use strict';

const assert = require('assert');
const fs = require('fs');
const read = (path) => fs.readFileSync(path, 'utf8');
let checks = 0;
function ok(label, condition) { assert(condition, label); checks++; }

const library = read('application/libraries/EnvironmentConnector.php');
const model = read('application/models/DbSettings_model.php');
const runtime = read('application/controllers/ConnectorRuntime.php');
const controller = read('application/controllers/DbSettings.php');
const view = read('application/views/connectors.php');
const dependencies = read('application/models/JobDependency_model.php');
const creation = read('application/controllers/JobCreation.php');
const hop = read('application/controllers/Hop.php');
const envExample = read('.env.example');

ok('the deployment connector library exists', library.includes('class EnvironmentConnector'));
ok('a type is required so platform connector variables are ignored', library.includes('$type = strtolower') && library.includes('TYPE is deliberately required'));
ok('public rows omit runtime secrets', /if \(\$includeSecrets\) \{[\s\S]{0,100}_secret_values/.test(library));
ok('secret values are kept separate from variable references', library.includes("'secrets' => $secrets"));
ok('the catalog page merges deployment rows', model.includes('environmentConnectorRows(FALSE)'));
ok('runtime resolution merges secret-bearing deployment rows', model.includes('environmentConnectorRows(TRUE)'));
ok('stored rows are considered before deployment rows', model.indexOf("->result_array();\n        foreach ($this->environmentConnectorRows(TRUE)") > 0);
ok('dependency resolution uses the complete connector catalog', dependencies.includes('catalogSettingsForKeys($keys)'));
ok('job creation tests can resolve deployment connectors', creation.includes('connectorCatalog->catalogSetting'));
ok('the authenticated endpoint turns deployment values into an ephemeral local payload',
  /\$backend === 'deployment'[\s\S]{0,500}\$secret\['backend'\] = 'local'/.test(runtime));
ok('the controller supplies a display-only backend label', controller.includes("$secretBackendLabels['deployment']"));
ok('deployment connectors have no edit or delete controls', view.includes('$isEnvironmentConnector') && view.includes('Read only'));
ok('secret values are visibly masked', view.includes('&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;'));
ok('live tests identify virtual connectors by key and scope', view.includes('connector_environment:button.data'));
ok('deployment database connectors can be published to Hop without entering the database',
  hop.includes("array('local', 'deployment')") && hop.includes("$row['_secret_values']"));
ok('the convention is documented', envExample.includes('JOBSEEKER_CONNECTOR_WAREHOUSE_TYPE=mysql'));

console.log('Environment connector wiring checks passed (' + checks + ' assertions).');
