<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/visualization.css?v=2">
<div class="content-wrapper viz-page viz-sources-page">
  <section class="content">
    <div class="viz-shell">
      <div class="viz-viewer-bar">
        <div class="viz-viewer-title"><a class="viz-btn viz-btn-light" href="<?php echo base_url(); ?>Visualization/studio"><i class="fa fa-arrow-left"></i></a><div><span class="viz-eyebrow viz-eyebrow-dark">Insight Studio</span><h1>Governed data sources</h1><p>Connect a read-only database, approve a table and fields, then publish it to the Studio library.</p></div></div>
        <a class="viz-btn viz-btn-light" href="<?php echo base_url(); ?>Visualization"><i class="fa fa-compass"></i> Analytics hub</a>
      </div>

      <div class="viz-source-security"><i class="fa fa-shield"></i><div><strong>Safe by default</strong><span>Passwords are encrypted at rest. The browser never receives credentials, table names, or raw SQL, and Studio runs generated aggregate SELECT queries only.</span></div><div class="viz-source-security-points"><span><i class="fa fa-check"></i> Dedicated read-only account</span><span><i class="fa fa-check"></i> TLS recommended</span><span><i class="fa fa-check"></i> 5-second connection timeout</span></div></div>

      <div id="vizSourceNotice" class="alert" style="display:none"></div>
      <div class="viz-source-layout">
        <div class="viz-source-main">
          <section class="viz-panel">
            <div class="viz-panel-head"><div><h2><span class="viz-step">1</span> Connect a database</h2><p>MySQL, MariaDB and PostgreSQL are supported. The connection is tested before it is stored.</p></div><span class="viz-pill"><i class="fa fa-lock"></i> encrypted</span></div>
            <div class="viz-panel-body">
              <form id="vizConnectionForm" class="viz-form">
                <input type="hidden" name="id" value="0">
                <div class="row">
                  <div class="col-md-6 form-group"><label for="vizConnectionName">Connection name</label><input id="vizConnectionName" class="form-control" name="name" maxlength="120" placeholder="Warehouse · production" required></div>
                  <div class="col-md-3 form-group"><label for="vizDriver">Database</label><select id="vizDriver" class="form-control" name="driver"><option value="mysql">MySQL / MariaDB</option><option value="pgsql">PostgreSQL</option></select></div>
                  <div class="col-md-3 form-group"><label for="vizSslMode">TLS mode</label><select id="vizSslMode" class="form-control" name="ssl_mode"><option value="required">Required</option><option value="preferred">Preferred</option><option value="disabled">Disabled</option></select></div>
                </div>
                <div class="row">
                  <div class="col-md-5 form-group"><label for="vizHost">Host</label><input id="vizHost" class="form-control" name="host" maxlength="255" placeholder="analytics-db.internal" required></div>
                  <div class="col-md-2 form-group"><label for="vizPort">Port</label><input id="vizPort" class="form-control" name="port" type="number" min="1" max="65535" value="3306" required></div>
                  <div class="col-md-5 form-group"><label for="vizDatabase">Database</label><input id="vizDatabase" class="form-control" name="database_name" maxlength="128" placeholder="analytics" required></div>
                </div>
                <div class="row">
                  <div class="col-md-6 form-group"><label for="vizUsername">Read-only username</label><input id="vizUsername" class="form-control" name="username" maxlength="128" autocomplete="off" required></div>
                  <div class="col-md-6 form-group"><label for="vizPassword">Password</label><input id="vizPassword" class="form-control" name="password" type="password" maxlength="1000" autocomplete="new-password" required></div>
                </div>
                <input type="hidden" name="is_active" value="1">
                <div class="viz-form-actions"><button id="vizSaveConnection" class="viz-btn viz-btn-blue" type="submit"><i class="fa fa-plug"></i> Test &amp; save connection</button><span class="viz-form-note">Use a database account with SELECT access only to the approved schemas.</span></div>
              </form>
            </div>
          </section>

          <section class="viz-panel viz-source-builder">
            <div class="viz-panel-head"><div><h2><span class="viz-step">2</span> Publish a curated dataset</h2><p>Select only the dimensions and numeric measures analysts should see.</p></div><span class="viz-pill viz-pill-neutral"><i class="fa fa-eye-slash"></i> no raw SQL</span></div>
            <div class="viz-panel-body">
              <?php if(empty($connections)) { ?>
                <div id="vizDatasetEmpty" class="viz-empty viz-empty-compact"><i class="fa fa-database"></i><strong>Save a database connection first</strong><p>The dataset builder will discover its schemas and tables here.</p></div>
              <?php } ?>
              <form id="vizDatasetForm" class="viz-form"<?php echo empty($connections) ? ' style="display:none"' : ''; ?>>
                <input type="hidden" name="id" value="0">
                <input id="vizDatasetSchema" type="hidden" name="table_schema">
                <input id="vizDatasetTableName" type="hidden" name="table_name">
                <div class="row">
                  <div class="col-md-6 form-group"><label for="vizDatasetConnection">Connection</label><select id="vizDatasetConnection" class="form-control" name="connection_id"><option value="">Choose a connection…</option><?php foreach($connections as $connection) { if((int) $connection->is_active === 1) { ?><option value="<?php echo (int) $connection->id; ?>"><?php echo html_escape($connection->name); ?> · <?php echo strtoupper(html_escape($connection->driver)); ?></option><?php } } ?></select></div>
                  <div class="col-md-6 form-group"><label for="vizDatasetTable">Table</label><select id="vizDatasetTable" class="form-control" disabled><option value="">Choose a connection first…</option></select></div>
                </div>
                <div class="row">
                  <div class="col-md-5 form-group"><label for="vizDatasetName">Dataset name</label><input id="vizDatasetName" class="form-control" name="name" maxlength="120" placeholder="Applications funnel" required></div>
                  <div class="col-md-7 form-group"><label for="vizDatasetDescription">Description</label><input id="vizDatasetDescription" class="form-control" name="description" maxlength="500" placeholder="What analysts can learn from this dataset"></div>
                </div>
                <div id="vizColumnWorkbench" class="viz-column-workbench" style="display:none">
                  <div class="viz-column-head"><div><strong>Approved fields</strong><span>Dimensions group records; measures calculate totals and averages.</span></div><span id="vizColumnCount" class="viz-pill viz-pill-neutral"></span></div>
                  <div class="viz-column-grid"><div><label class="viz-column-label"><i class="fa fa-font"></i> Dimensions</label><div id="vizDimensionColumns" class="viz-column-list"></div></div><div><label class="viz-column-label"><i class="fa fa-hashtag"></i> Numeric measures</label><div id="vizMeasureColumns" class="viz-column-list"></div></div></div>
                  <div class="row viz-filter-mapping">
                    <div class="col-md-6 form-group"><label for="vizTimeColumn">Time filter column <span>optional</span></label><select id="vizTimeColumn" class="form-control" name="time_column"><option value="">No global time filter</option></select></div>
                    <div class="col-md-6 form-group"><label for="vizEnvironmentColumn">Environment filter column <span>optional</span></label><select id="vizEnvironmentColumn" class="form-control" name="environment_column"><option value="">No environment filter</option></select></div>
                  </div>
                </div>
                <div class="viz-form-actions"><button id="vizSaveDataset" class="viz-btn viz-btn-blue" type="submit" disabled><i class="fa fa-cloud-upload"></i> Publish to Insight Studio</button><span class="viz-form-note">A safe row-count measure is always included automatically.</span></div>
              </form>
            </div>
          </section>
        </div>

        <aside class="viz-source-side">
          <section class="viz-panel">
            <div class="viz-panel-head"><div><h3>Connections</h3><p><?php echo number_format(count($connections)); ?> configured</p></div></div>
            <div class="viz-source-card-list">
              <?php if(!empty($connections)) { foreach($connections as $connection) { ?>
                <article class="viz-source-card" data-connection-id="<?php echo (int) $connection->id; ?>"><div class="viz-source-card-icon"><i class="fa fa-database"></i></div><div class="viz-source-card-copy"><strong><?php echo html_escape($connection->name); ?></strong><span><?php echo html_escape($connection->username); ?>@<?php echo html_escape($connection->host); ?>:<?php echo (int) $connection->port; ?></span><small><?php echo strtoupper(html_escape($connection->driver)); ?> · <?php echo html_escape($connection->database_name); ?> · <?php echo (int) $connection->dataset_count; ?> datasets</small></div><button class="viz-source-delete viz-delete-connection" type="button" title="Delete connection"><i class="fa fa-trash"></i></button></article>
              <?php } } else { ?><div class="viz-empty viz-empty-compact"><i class="fa fa-plug"></i><strong>No connections yet</strong></div><?php } ?>
            </div>
          </section>
          <section class="viz-panel">
            <div class="viz-panel-head"><div><h3>Published datasets</h3><p><?php echo number_format(count($connectedDatasets)); ?> in the Studio library</p></div></div>
            <div class="viz-source-card-list">
              <?php if(!empty($connectedDatasets)) { foreach($connectedDatasets as $dataset) { ?>
                <article class="viz-source-card" data-dataset-id="<?php echo (int) $dataset->id; ?>"><div class="viz-source-card-icon viz-source-card-icon-mint"><i class="fa fa-table"></i></div><div class="viz-source-card-copy"><strong><?php echo html_escape($dataset->name); ?></strong><span><?php echo html_escape($dataset->connection_name); ?></span><small><?php echo html_escape($dataset->table_schema.'.'.$dataset->table_name); ?></small></div><button class="viz-source-delete viz-delete-dataset" type="button" title="Remove dataset"><i class="fa fa-trash"></i></button></article>
              <?php } } else { ?><div class="viz-empty viz-empty-compact"><i class="fa fa-table"></i><strong>No connected datasets</strong><p>Your built-in Jobseeker datasets remain available.</p></div><?php } ?>
            </div>
          </section>
        </aside>
      </div>
    </div>
  </section>
</div>
<script>
window.JobSeekerVisualizationSources = <?php echo json_encode(array('endpoints' => array(
  'saveConnection' => base_url().'Visualization/saveConnection',
  'deleteConnection' => base_url().'Visualization/deleteConnection',
  'tables' => base_url().'Visualization/connectionTables/',
  'columns' => base_url().'Visualization/connectionColumns/',
  'saveDataset' => base_url().'Visualization/saveDataset',
  'deleteDataset' => base_url().'Visualization/deleteDataset'
))); ?>;
</script>
<script src="<?php echo base_url(); ?>assets/js/visualization-datasources.js?v=1"></script>
