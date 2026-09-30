const assert = require('assert');
const fs = require('fs');

const controller = fs.readFileSync('application/controllers/DataAssets.php', 'utf8');
const model = fs.readFileSync('application/models/DataAssets_model.php', 'utf8');
const connectorModel = fs.readFileSync('application/models/DbSettings_model.php', 'utf8');
const runtime = fs.readFileSync('application/controllers/ConnectorRuntime.php', 'utf8');
const client = fs.readFileSync('application/libraries/DataAssetSource.php', 'utf8');
const view = fs.readFileSync('application/views/dataAssets.php', 'utf8');
const editor = fs.readFileSync('assets/js/data-assets.js', 'utf8');
const jobs = fs.readFileSync('assets/js/job-dependencies.js', 'utf8');
const sdk = fs.readFileSync('application/third_party/python/jobseeker_sdk/src/jobseeker/__init__.py', 'utf8');
const engine = fs.readFileSync('application/third_party/python/jobseeker_sdk/src/jobseeker/sources.py', 'utf8');
const service = fs.readFileSync('application/third_party/python/jobseeker_sdk/src/jobseeker/preview_server.py', 'utf8');
const compose = fs.readFileSync('docker-compose.yml', 'utf8');

['source_type', 'connector_key', 'source_config_json'].forEach((column) => {
  assert(model.includes(column), 'Data Asset schema must include ' + column);
});
assert(controller.includes("'schema_version' => 2"), 'the source-aware manifest must use schema version 2');
assert(!controller.includes("'secret_encrypted' =>"), 'the Data Asset manifest must never include encrypted connector secrets');

// Each source declares the formats and Connection types that make sense for it.
['upload', 'url', 'google_sheet', 'database_table', 'object_storage', 'document_collection'].forEach((type) => {
  assert(controller.includes("'" + type + "' => array("), 'source type missing from the catalog: ' + type);
  assert(engine.includes('"' + type + '"'), 'the Python engine must read ' + type + ' sources');
});
assert(/'database_table' => array\([\s\S]*?'formats' => array\('table' => [\s\S]*?'connection' => 'required'/.test(controller),
  'a database table has one shape (rows as CSV) and needs a Connection');
assert(controller.includes("count($source['formats']) === 1"), 'single-shape sources must ignore a posted file format');
assert(controller.includes("in_array((string) $connector->db_type, $source['connectors'], TRUE)"), 'a Connection must match its source');
['sqlserver', 'oracle_service', 'snowflake', 'databricks', 'aws_s3', 'azure_blob', 'gcs', 'sftp', 'mongodb', 'elasticsearch'].forEach((type) => {
  assert(controller.includes("'" + type + "'"), 'the catalog must accept ' + type + ' Connections');
});

// Previews run in the Python service with one Connection's runtime payload.
assert(controller.includes('runtimePayload((array) $row)') && controller.includes("'granted-preview'"),
  'a preview sends only the asset Connection and audits that access');
assert(runtime.includes('$this->connectors->runtimePayload($row)'), 'workers and previews must share one connector payload');
assert(connectorModel.includes('public function runtimePayload($row)'), 'the connector payload builder belongs to the model');
assert(client.includes("getenv('JOBSEEKER_DATA_PREVIEW_URL')") && client.includes("'Authorization: Bearer '"),
  'the web tier must authenticate to the Data Preview service');
assert(!client.includes('new PDO(') && !client.includes('CURLOPT_RESOLVE'), 'PHP must not keep a second source reader');
assert(service.includes('hmac.compare_digest') && service.includes('public_only=True'),
  'the service must check its token and refuse private destinations for public sources');
assert(/data-preview:[\s\S]*?\.\/repository:\/php\/repository:ro/.test(compose), 'the preview service reads uploads read-only');

// The engine guards what the web tier cannot see.
assert(engine.includes('.is_global') && engine.includes('class _PublicHTTPSConnection'),
  'public sources must connect only to validated public addresses');
assert(engine.includes('_CredentialScopedRedirect(credentials.keys())'), 'Connection credentials must stay on their host');
assert(engine.includes('def table_reference(') && engine.includes('SELECT TOP (%d) * FROM %s') && engine.includes('WHERE ROWNUM <= %d'),
  'table sources run only a validated, dialect-bounded SELECT');
assert(engine.includes('_display_url(url)'), 'messages must not print URL query strings');

// Jobs materialize every source through the same engine.
assert(sdk.includes('return sources.materialize(self, self._connector(), target)'), 'jobs must materialize through the engine');
assert(sdk.includes('def connector_from_payload(') && sdk.includes('def data_asset_from_item('), 'the service builds assets and Connections with SDK helpers');

['assetSourceType', 'assetConnector', 'assetSourceUrl', 'assetSpreadsheetId', 'assetTableName', 'assetObjectPath', 'assetCollectionName'].forEach((id) => {
  assert(view.includes('id="' + id + '"'), 'Data Asset editor field missing: ' + id);
});
assert(editor.includes('window.JobSeekerDataAssets.sources') && editor.includes("$format.prop('disabled', keys.length < 2)"),
  'the editor must offer only the formats a source can deliver');

assert(jobs.includes('jd-preview-asset'), 'job screens must expose the Data Asset preview popup');
assert(jobs.includes('jd-toggle-inline-assets'), 'job execution must expose inline Data Asset samples');
assert(jobs.includes("'&job=' + encodeURIComponent(job)"), 'job screens must preview with the job scope');

console.log('Connected Data Asset source checks passed.');
