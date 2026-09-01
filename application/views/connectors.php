<?php
$isEditing = ! empty($editing);
$referenceValues = $isEditing && isset($editing->secret_reference_values) ? $editing->secret_reference_values : array();
$value = function($field, $default = '') use ($editing, $isEditing) {
    return $isEditing && isset($editing->$field) ? $editing->$field : $default;
};
$globalEnvironment = isset($selectedEnvironment) ? $selectedEnvironment : 'ALL';
$connectorEnvironment = $isEditing ? $value('environment', $globalEnvironment) : $globalEnvironment;
$environmentQuery = rawurlencode($globalEnvironment);
$selectedType = $value('db_type', 'mysql');
$selectedAuthType = $value('auth_type', 'username_password');
$selectedBackend = $value('secret_backend', 'local');
$mappingLines = function($mappings) {
  $lines = array();
  foreach (is_array($mappings) ? $mappings : array() as $name => $source) {
    $lines[] = $name.'='.$source;
  }
  return implode("\n", $lines);
};
$environmentMappings = isset($referenceValues['variables']) ? $referenceValues['variables'] : array();
if (empty($environmentMappings)) {
  if (! empty($referenceValues['username_env'])) $environmentMappings['username'] = $referenceValues['username_env'];
  if (! empty($referenceValues['password_env'])) $environmentMappings['password'] = $referenceValues['password_env'];
}
$azureMappings = isset($referenceValues['secrets']) ? $referenceValues['secrets'] : array();
if (empty($azureMappings)) {
  if (! empty($referenceValues['username_secret'])) $azureMappings['username'] = $referenceValues['username_secret'];
  if (! empty($referenceValues['password_secret'])) $azureMappings['password'] = $referenceValues['password_secret'];
}
$awsMappings = isset($referenceValues['fields']) ? $referenceValues['fields'] : array();
if (empty($awsMappings)) {
  if (! empty($referenceValues['username_field'])) $awsMappings['username'] = $referenceValues['username_field'];
  if (! empty($referenceValues['password_field'])) $awsMappings['password'] = $referenceValues['password_field'];
}
$selectedAzureAuth = isset($referenceValues['auth_mode']) ? $referenceValues['auth_mode'] : 'default';
$selectedAwsAuth = isset($referenceValues['auth_mode']) ? $referenceValues['auth_mode'] : 'default';
?>
<style>
.connector-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
.connector-toolbar h3 { float:none !important; margin:0; font-size:18px; }
.connector-toolbar .btn { margin-left:auto; }
.connector-form-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
.connector-form-grid .span-2 { grid-column:span 2; }
.connector-form-grid .span-4 { grid-column:span 4; }
.connector-secret-panel { border-left:3px solid #3c8dbc; background:#f8fafc; padding:14px; margin-top:14px; }
.connector-secret-panel h4 { margin:0 0 12px; font-size:15px; }
.connector-scope { color:#52606d; white-space:nowrap; }
.connector-key { font-family:Menlo,Consolas,monospace; font-weight:600; }
.connector-endpoint { word-break:break-word; }
.connector-status { display:inline-flex; align-items:center; gap:6px; font-weight:600; }
.connector-status-dot { width:8px; height:8px; border-radius:50%; background:#9aa5b1; }
.connector-status.active .connector-status-dot { background:#2e8540; }
.connector-actions { white-space:nowrap; }
@media (max-width: 991px) { .connector-form-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .connector-form-grid .span-4 { grid-column:span 2; } }
@media (max-width: 600px) { .connector-toolbar { align-items:flex-start; flex-direction:column; } .connector-form-grid { grid-template-columns:1fr; } .connector-form-grid .span-2,.connector-form-grid .span-4 { grid-column:span 1; } }
</style>
<div class="content-wrapper">
  <section class="content-header">
    <h1>Connectors <small>ETL connection catalog</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
      <li>Extract, Transform, Load</li>
      <li class="active">Connectors</li>
    </ol>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if ($this->session->flashdata('error')) { ?>
        <div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><?php echo html_escape($this->session->flashdata('error')); ?></div>
      <?php } ?>
      <?php if ($this->session->flashdata('success')) { ?>
        <div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><?php echo html_escape($this->session->flashdata('success')); ?></div>
      <?php } ?>

      <?php if ($showForm) { ?>
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><?php echo $isEditing ? 'Edit connector' : 'New connector'; ?></h3>
          <div class="box-tools"><a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>dbSettings?environment=<?php echo $environmentQuery; ?>"><i class="fa fa-times"></i> Cancel</a></div>
        </div>
        <?php echo form_open('dbSettings/'.($isEditing ? 'UpdateDbSettings' : 'InsertDbSettings').'?environment='.$environmentQuery, array('id' => 'connectorForm')); ?>
          <?php if ($isEditing) { ?><input type="hidden" name="id" value="<?php echo (int) $editing->id; ?>"><?php } ?>
          <input type="hidden" name="environment" value="<?php echo html_escape($connectorEnvironment); ?>">
          <div class="box-body">
            <div class="connector-form-grid">
              <div class="form-group">
                <label for="connector_key">Connector key</label>
                <input class="form-control" id="connector_key" name="connector_key" maxlength="128" pattern="[a-z0-9\-]+" value="<?php echo html_escape($value('connector_key')); ?>" required>
              </div>
              <div class="form-group">
                <label>Environment</label>
                <p class="form-control-static"><span class="label label-primary"><?php echo html_escape($connectorEnvironment); ?></span></p>
              </div>
              <div class="form-group">
                <label for="job_name">Job scope</label>
                <input class="form-control" id="job_name" name="job_name" maxlength="200" value="<?php echo html_escape($value('job_name', '*')); ?>" required>
              </div>
              <div class="form-group">
                <label for="db_type">Connector type</label>
                <select class="form-control" id="db_type" name="db_type" required>
                  <?php foreach ($connectorTypes as $type => $label) { ?>
                    <option value="<?php echo html_escape($type); ?>"<?php echo $selectedType === $type ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="form-group">
                <label for="auth_type">Target authentication</label>
                <select class="form-control" id="auth_type" name="auth_type" required>
                  <?php foreach ($authenticationTypes as $type => $label) { ?>
                    <option value="<?php echo html_escape($type); ?>"<?php echo $selectedAuthType === $type ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="form-group span-2">
                <label for="address">Endpoint / host</label>
                <input class="form-control" id="address" name="address" maxlength="255" value="<?php echo html_escape($value('address')); ?>">
              </div>
              <div class="form-group">
                <label for="port">Port</label>
                <input class="form-control" type="number" id="port" name="port" min="0" max="65535" value="<?php echo html_escape($value('port', '3306')); ?>">
              </div>
              <div class="form-group">
                <label for="schema">Database / resource</label>
                <input class="form-control" id="schema" name="schema" maxlength="200" value="<?php echo html_escape($value('schema')); ?>">
              </div>
              <div class="form-group span-2 oracle-field" data-oracle-type="oracle_service">
                <label for="oracle_ServiceName">Oracle service name</label>
                <input class="form-control" id="oracle_ServiceName" name="oracle_ServiceName" maxlength="200" value="<?php echo html_escape($value('oracle_ServiceName')); ?>">
              </div>
              <div class="form-group span-2 oracle-field" data-oracle-type="oracle_sid">
                <label for="oracle_sid">Oracle SID</label>
                <input class="form-control" id="oracle_sid" name="oracle_sid" maxlength="200" value="<?php echo html_escape($value('oracle_sid')); ?>">
              </div>
              <div class="form-group span-2">
                <label for="additional_parameters">Connection parameters</label>
                <input class="form-control" id="additional_parameters" name="additional_parameters" maxlength="1000" value="<?php echo html_escape($value('additional_parameters')); ?>">
              </div>
              <div class="form-group">
                <label for="secret_backend">Secret source</label>
                <select class="form-control" id="secret_backend" name="secret_backend" required>
                  <?php foreach ($secretBackends as $backend => $label) { ?>
                    <option value="<?php echo html_escape($backend); ?>"<?php echo $selectedBackend === $backend ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="form-group">
                <label for="is_active">Status</label>
                <select class="form-control" id="is_active" name="is_active">
                  <option value="1"<?php echo (int) $value('is_active', 1) === 1 ? ' selected' : ''; ?>>Active</option>
                  <option value="0"<?php echo (int) $value('is_active', 1) === 0 ? ' selected' : ''; ?>>Inactive</option>
                </select>
              </div>
              <div class="form-group span-4">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" maxlength="2000" rows="2"><?php echo html_escape($value('description')); ?></textarea>
              </div>
            </div>

            <div class="connector-secret-panel secret-fields" data-secret-backend="local">
              <h4>Encrypted local values</h4>
              <div class="connector-form-grid">
                <div class="form-group span-2"><label for="login">Username</label><input class="form-control" id="login" name="login" maxlength="500" autocomplete="off"></div>
                <div class="form-group span-2"><label for="password">Password</label><input class="form-control" type="password" id="password" name="password" maxlength="2000" autocomplete="new-password"></div>
                <div class="form-group span-4"><label for="local_secret_fields">Additional secret fields</label><textarea class="form-control" id="local_secret_fields" name="local_secret_fields" rows="4" spellcheck="false" placeholder="api_key=...&#10;sas_token=...&#10;connection_string=..."></textarea></div>
                <?php if ($isEditing && $editing->secret_backend === 'local') { ?>
                  <div class="form-group span-4"><label><input type="checkbox" name="clear_local_secrets" value="1"> Clear all stored local values</label><p class="help-block">Use this when the selected authentication type does not require a saved credential. Otherwise, blank fields preserve the current encrypted values.</p></div>
                <?php } ?>
              </div>
            </div>

            <div class="connector-secret-panel secret-fields" data-secret-backend="environment">
              <h4>Worker environment mappings</h4>
              <div class="connector-form-grid">
                <div class="form-group span-4"><label for="environment_mappings">Secret field to environment variable</label><textarea class="form-control backend-required" id="environment_mappings" name="environment_mappings" rows="5" spellcheck="false" placeholder="username=WAREHOUSE_USER&#10;password=WAREHOUSE_PASSWORD&#10;sas_token=AZURE_STORAGE_SAS_TOKEN"><?php echo html_escape($mappingLines($environmentMappings)); ?></textarea></div>
              </div>
            </div>

            <div class="connector-secret-panel secret-fields" data-secret-backend="azure_key_vault">
              <h4>Azure Key Vault references</h4>
              <div class="connector-form-grid">
                <div class="form-group span-2"><label for="vault_url">Vault URL</label><input class="form-control backend-required" type="url" id="vault_url" name="vault_url" placeholder="https://example.vault.azure.net" value="<?php echo html_escape(isset($referenceValues['vault_url']) ? $referenceValues['vault_url'] : ''); ?>"></div>
                <div class="form-group"><label for="azure_auth_mode">Vault authentication</label><select class="form-control backend-required" id="azure_auth_mode" name="azure_auth_mode"><option value="default"<?php echo $selectedAzureAuth === 'default' ? ' selected' : ''; ?>>Default credential chain</option><option value="managed_identity"<?php echo $selectedAzureAuth === 'managed_identity' ? ' selected' : ''; ?>>Managed identity</option><option value="workload_identity"<?php echo $selectedAzureAuth === 'workload_identity' ? ' selected' : ''; ?>>Workload identity</option><option value="environment"<?php echo $selectedAzureAuth === 'environment' ? ' selected' : ''; ?>>Service principal environment</option></select></div>
                <div class="form-group span-2"><label for="managed_identity_client_id">Managed identity client ID</label><input class="form-control" id="managed_identity_client_id" name="managed_identity_client_id" value="<?php echo html_escape(isset($referenceValues['managed_identity_client_id']) ? $referenceValues['managed_identity_client_id'] : ''); ?>"></div>
                <div class="form-group span-4"><label for="azure_secret_mappings">Connector field to Key Vault secret</label><textarea class="form-control backend-required" id="azure_secret_mappings" name="azure_secret_mappings" rows="5" spellcheck="false" placeholder="username=warehouse-user&#10;password=warehouse-password&#10;api_key=vendor-api-key"><?php echo html_escape($mappingLines($azureMappings)); ?></textarea></div>
              </div>
            </div>

            <div class="connector-secret-panel secret-fields" data-secret-backend="aws_secrets_manager">
              <h4>AWS Secrets Manager reference</h4>
              <div class="connector-form-grid">
                <div class="form-group"><label for="aws_region">AWS region</label><input class="form-control backend-required" id="aws_region" name="aws_region" placeholder="us-east-1" value="<?php echo html_escape(isset($referenceValues['region']) ? $referenceValues['region'] : ''); ?>"></div>
                <div class="form-group span-2"><label for="aws_secret_id">Secret ID or ARN</label><input class="form-control backend-required" id="aws_secret_id" name="aws_secret_id" maxlength="512" value="<?php echo html_escape(isset($referenceValues['secret_id']) ? $referenceValues['secret_id'] : ''); ?>"></div>
                <div class="form-group"><label for="aws_auth_mode">AWS authentication</label><select class="form-control backend-required" id="aws_auth_mode" name="aws_auth_mode"><option value="default"<?php echo $selectedAwsAuth === 'default' ? ' selected' : ''; ?>>Default credential chain</option><option value="iam_role"<?php echo $selectedAwsAuth === 'iam_role' ? ' selected' : ''; ?>>IAM role</option><option value="web_identity"<?php echo $selectedAwsAuth === 'web_identity' ? ' selected' : ''; ?>>Web identity</option><option value="environment"<?php echo $selectedAwsAuth === 'environment' ? ' selected' : ''; ?>>Access key environment</option><option value="profile"<?php echo $selectedAwsAuth === 'profile' ? ' selected' : ''; ?>>Named profile</option></select></div>
                <div class="form-group span-2 aws-profile-field"><label for="aws_profile_name">Profile name</label><input class="form-control" id="aws_profile_name" name="aws_profile_name" value="<?php echo html_escape(isset($referenceValues['profile_name']) ? $referenceValues['profile_name'] : ''); ?>"></div>
                <div class="form-group span-4"><label for="aws_field_mappings">Connector field to secret JSON field</label><textarea class="form-control backend-required" id="aws_field_mappings" name="aws_field_mappings" rows="5" spellcheck="false" placeholder="username=credentials.username&#10;password=credentials.password&#10;api_key=vendor.api_key"><?php echo html_escape($mappingLines($awsMappings)); ?></textarea></div>
              </div>
            </div>
          </div>
          <div class="box-footer"><button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Save connector</button></div>
        <?php echo form_close(); ?>
      </div>
      <?php } ?>

      <div class="box box-primary">
        <div class="box-header with-border connector-toolbar">
          <h3 class="box-title">Connector catalog</h3>
          <a class="btn btn-primary btn-sm" href="<?php echo base_url(); ?>dbSettings?create=1&amp;environment=<?php echo $environmentQuery; ?>"><i class="fa fa-plus"></i> New connector</a>
        </div>
        <div class="box-body table-responsive">
          <table id="connectorTable" class="table table-bordered table-striped">
            <thead><tr><th>Key</th><th>Scope</th><th>Type</th><th>Endpoint</th><th>Secret source</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($settings as $record) {
              $isBuiltin = ($record->owner === 'system' && $record->connector_key === 'jobseeker-mariadb');
            ?>
              <tr data-connector-key="<?php echo html_escape($record->connector_key); ?>">
                <td class="connector-key"><?php echo html_escape($record->connector_key); ?><?php echo $isBuiltin ? ' <span class="label label-info" title="Seeded by JobSeeker; editable">Built-in</span>' : ''; ?></td>
                <td class="connector-scope"><?php echo html_escape($record->environment); ?> / <?php echo $record->job_name === '*' ? 'shared' : html_escape($record->job_name); ?></td>
                <td><?php echo html_escape(isset($connectorTypes[$record->db_type]) ? $connectorTypes[$record->db_type] : $record->db_type); ?><br><small><?php echo html_escape(isset($authenticationTypes[$record->auth_type]) ? $authenticationTypes[$record->auth_type] : $record->auth_type); ?></small></td>
                <td class="connector-endpoint"><?php echo html_escape($record->address); ?>:<?php echo html_escape($record->port); ?><br><small><?php echo html_escape($record->schema); ?></small></td>
                <td><?php echo html_escape(isset($secretBackends[$record->secret_backend]) ? $secretBackends[$record->secret_backend] : $record->secret_backend); ?></td>
                <td><span class="connector-status <?php echo (int) $record->is_active === 1 ? 'active' : ''; ?>"><span class="connector-status-dot"></span><?php echo (int) $record->is_active === 1 ? 'Active' : 'Inactive'; ?></span></td>
                <td><?php echo html_escape($record->updated_at ?: $record->creation_date); ?></td>
                <td class="connector-actions"><button class="btn btn-default btn-xs connectorHelp" type="button" data-key="<?php echo html_escape($record->connector_key); ?>" data-type="<?php echo html_escape($record->db_type); ?>" data-auth="<?php echo html_escape($record->auth_type); ?>" data-backend="<?php echo html_escape($record->secret_backend); ?>" title="Usage"><i class="fa fa-code"></i></button> <button class="btn btn-info btn-xs testConnector" type="button" data-id="<?php echo (int) $record->id; ?>" data-key="<?php echo html_escape($record->connector_key); ?>" title="Run a live connection test on a Jenkins worker"><i class="fa fa-plug"></i> Test</button> <a class="btn btn-warning btn-xs" href="<?php echo base_url().'dbSettings?edit='.(int) $record->id.'&amp;environment='.$environmentQuery; ?>" title="Edit"><i class="fa fa-pencil"></i></a> <button class="btn btn-danger btn-xs deleteConnector" type="button" data-id="<?php echo (int) $record->id; ?>" title="Delete"><i class="fa fa-trash"></i></button></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="connectorHelpModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button><h4 class="modal-title">Use connector <code id="connectorHelpKey"></code></h4></div>
      <div class="modal-body">
        <div class="row">
          <div class="col-sm-4"><strong>Type</strong><p id="connectorHelpType"></p></div>
          <div class="col-sm-4"><strong>Target authentication</strong><p id="connectorHelpAuth"></p></div>
          <div class="col-sm-4"><strong>Secret source</strong><p id="connectorHelpBackend"></p></div>
        </div>
        <p id="connectorHelpBackendNote" class="alert alert-info"></p>
        <ul class="nav nav-tabs" role="tablist">
          <li class="active"><a href="#connectorPythonUsage" data-toggle="tab">Python</a></li>
          <li><a href="#connectorShellUsage" data-toggle="tab">Shell</a></li>
          <li><a href="#connectorTalendUsage" data-toggle="tab">Talend</a></li>
          <li><a href="#connectorFileUsage" data-toggle="tab">Files</a></li>
        </ul>
        <div class="tab-content" style="padding-top:12px">
          <div class="tab-pane active" id="connectorPythonUsage"><pre id="connectorPythonCode"></pre></div>
          <div class="tab-pane" id="connectorShellUsage"><pre id="connectorShellCode"></pre></div>
          <div class="tab-pane" id="connectorTalendUsage"><pre id="connectorTalendCode"></pre></div>
          <div class="tab-pane" id="connectorFileUsage"><pre id="connectorFileCode"></pre></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<div class="modal fade" id="connectorTestModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button><h4 class="modal-title">Connection test &mdash; <code id="connectorTestKey"></code></h4></div>
      <div class="modal-body">
        <div id="connectorTestRunning" class="text-muted"><i class="fa fa-circle-o-notch fa-spin"></i> Running a live handshake on a Jenkins worker. This can take a few seconds&hellip;</div>
        <div id="connectorTestOutcome" style="display:none">
          <p><span id="connectorTestBadge" class="label"></span> <span id="connectorTestMessage"></span></p>
          <table class="table table-condensed">
            <tbody>
              <tr><th style="width:40%">Result</th><td id="connectorTestStatus"></td></tr>
              <tr><th>Latency</th><td id="connectorTestLatency"></td></tr>
              <tr><th>Server</th><td id="connectorTestServer"></td></tr>
              <tr><th>Test environment</th><td id="connectorTestEnv"></td></tr>
            </tbody>
          </table>
          <ul id="connectorTestChecks" class="list-unstyled"></ul>
          <pre id="connectorTestConsole" style="display:none;max-height:220px;overflow:auto"></pre>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>
<script>
(function($) {
  function refreshSecretFields() {
    var backend = $('#secret_backend').val();
    $('.secret-fields').each(function() {
      var active = $(this).data('secret-backend') === backend;
      $(this).toggle(active).find('.backend-required').prop('required', active);
    });
    refreshAwsProfile();
  }
  function refreshConnectorType(updatePort) {
    var type = $('#db_type').val();
    $('.oracle-field').each(function() {
      $(this).toggle($(this).data('oracle-type') === type);
    });
    var endpointOptional = $.inArray(type, ['aws_s3', 'azure_blob', 'azure_data_lake', 'gcs', 'generic_secret']) !== -1;
    $('#address').prop('required', ! endpointOptional);
    if (updatePort) {
      var defaults = {mysql:3306, pgsql:5432, sqlserver:1433, oracle_service:1521, oracle_sid:1521, mongodb:27017, redis:6379, snowflake:443, databricks:443, kafka:9092, rabbitmq:5672, elasticsearch:9200, sftp:22, http_api:443};
      var knownPorts = ['0','22','443','1433','1521','3306','5432','5672','6379','9092','9200','27017'];
      var current = String($('#port').val() || '0');
      if ($.inArray(current, knownPorts) !== -1) {
        $('#port').val(defaults[type] || 0);
      }
    }
  }
  function refreshAwsProfile() {
    var active = $('#secret_backend').val() === 'aws_secrets_manager' && $('#aws_auth_mode').val() === 'profile';
    $('.aws-profile-field').toggle(active).find('input').prop('required', active);
  }
  function syncGlobalEnvironment() {
    if (! window.JobSeekerGlobalEnvironment) { return true; }
    var selected = window.JobSeekerGlobalEnvironment.selected();
    var server = <?php echo json_encode(strtolower($globalEnvironment)); ?>;
    if (selected === '__UNKNOWN__') { selected = 'all'; }
    if (String(selected).toLowerCase() === server) { return true; }
    var url = new URL(window.location.href);
    if (selected === 'all') { url.searchParams.delete('environment'); }
    else { url.searchParams.set('environment', selected); }
    window.location.replace(url.toString());
    return false;
  }
  $(function() {
    if (! syncGlobalEnvironment()) { return; }
    $('#secret_backend').on('change', refreshSecretFields);
    $('#db_type').on('change', function() { refreshConnectorType(true); });
    $('#aws_auth_mode').on('change', refreshAwsProfile);
    refreshSecretFields();
    refreshConnectorType(false);
    if ($.fn.DataTable) { $('#connectorTable').DataTable({order:[[0,'asc']], pageLength:25}); }
  });
  function renderConnectorTest(response) {
    var ok = !!response.ok;
    $('#connectorTestRunning').hide();
    $('#connectorTestOutcome').show();
    $('#connectorTestBadge').attr('class', 'label ' + (ok ? 'label-success' : (response.status === 'driver_missing' ? 'label-warning' : 'label-danger'))).text(ok ? 'Passed' : 'Failed');
    $('#connectorTestMessage').text(response.message || '');
    $('#connectorTestStatus').text(response.status || (ok ? 'passed' : 'error'));
    $('#connectorTestLatency').text(response.latencyMs != null ? response.latencyMs + ' ms' : '—');
    $('#connectorTestServer').text(response.serverVersion || '—');
    $('#connectorTestEnv').text(response.testEnvironment || '—');
    var checks = $('#connectorTestChecks').empty();
    $.each(response.checks || [], function(_, check) {
      checks.append($('<li>').html('<i class="fa ' + (check.ok ? 'fa-check text-success' : 'fa-times text-danger') + '"></i> <strong>' + $('<span>').text(check.name).html() + '</strong> — ' + $('<span>').text(check.detail || '').html()));
    });
    if (response.consoleTail) {
      $('#connectorTestConsole').text(response.consoleTail).show();
    } else {
      $('#connectorTestConsole').hide();
    }
  }
  $(document).on('click', '.testConnector', function() {
    var button = $(this);
    button.prop('disabled', true).find('i').removeClass('fa-plug').addClass('fa-spinner fa-spin');
    $('#connectorTestKey').text(String(button.data('key') || ''));
    $('#connectorTestRunning').show();
    $('#connectorTestOutcome').hide();
    $('#connectorTestModal').modal('show');
    $.ajax({type:'POST', dataType:'json', url:baseURL + 'dbSettings/testConnector?environment=' + encodeURIComponent(<?php echo json_encode($globalEnvironment); ?>), data:{id:button.data('id'), mode:'live'}}).done(function(response) {
      renderConnectorTest(response);
    }).fail(function(xhr) {
      renderConnectorTest(xhr.responseJSON || {ok:false, status:'error', message:'Connector test failed.'});
    }).always(function() {
      button.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-plug');
    });
  });
  $(document).on('click', '.connectorHelp', function() {
    var button = $(this);
    var key = String(button.data('key'));
    var type = String(button.data('type'));
    var auth = String(button.data('auth'));
    var backend = String(button.data('backend'));
    var backendNotes = {
      local: 'Values are decrypted only during build materialization. Leave all local value fields blank while editing to preserve the saved credential.',
      environment: 'Each connector field is read from the mapped Jenkins worker environment variable when the build starts.',
      azure_key_vault: 'The Jenkins worker uses the selected Azure credential mode, then reads each mapped Key Vault secret.',
      aws_secrets_manager: 'The Jenkins worker uses the selected AWS credential mode, then reads mapped fields from the secret JSON object.'
    };
    $('#connectorHelpKey').text(key);
    $('#connectorHelpType').text(type);
    $('#connectorHelpAuth').text(auth);
    $('#connectorHelpBackend').text(backend);
    $('#connectorHelpBackendNote').text(backendNotes[backend] || 'The Jenkins worker resolves this secret source when the build starts.');
    $('#connectorPythonCode').text('from jobseeker import JobSeeker\n\nwith JobSeeker() as js:\n    connector = js.connector("' + key + '")\n    host = connector.host\n    token = connector.value("token", required=True)\n    # Pass connector values to the driver or client owned by this job.');
    $('#connectorShellCode').text('"$JOBSEEKER_CONNECTOR_HELPER" exec ' + key + ' -- ./run-etl.sh\n\n# run-etl.sh receives JOBSEEKER_CONNECTOR_HOST,\n# JOBSEEKER_CONNECTOR_PORT, and mapped JOBSEEKER_CONNECTOR_* values.');
    $('#connectorTalendCode').text('"$JOBSEEKER_CONNECTOR_HELPER" exec ' + key + ' -- ./run-talend.sh\n\n# run-talend.sh\n./MyJob_run.sh \\\n  --context_param host="$JOBSEEKER_CONNECTOR_HOST" \\\n  --context_param username="$JOBSEEKER_CONNECTOR_USERNAME" \\\n  --context_param password="$JOBSEEKER_CONNECTOR_PASSWORD"');
    $('#connectorFileCode').text('$JOBSEEKER_CONNECTORS_DIR/' + key + '/host\n$JOBSEEKER_CONNECTORS_DIR/' + key + '/username\n$JOBSEEKER_CONNECTORS_DIR/' + key + '/token\n\n# Files are 0600 locally and read-only inside Docker jobs.');
    $('#connectorHelpModal').modal('show');
  });
  $(document).on('click', '.deleteConnector', function() {
    var button = $(this);
    alertify.confirm('Delete connector', 'Delete this connector from the ETL catalog?', function() {
      $.ajax({type:'POST', dataType:'json', url:baseURL + 'dbSettings/deleteSetting?environment=' + encodeURIComponent(<?php echo json_encode($globalEnvironment); ?>), data:{userId:button.data('id')}}).done(function(response) {
        if (response.status === true) { button.closest('tr').remove(); alertify.success('Connector deleted.'); }
        else { alertify.error('Connector could not be deleted.'); }
      }).fail(function() { alertify.error('Connector could not be deleted.'); });
    }, function() {});
  });
}(jQuery));
</script>
