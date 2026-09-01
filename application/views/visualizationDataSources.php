<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/visualization.css?v=2">
<div class="content-wrapper viz-page viz-sources-page">
  <section class="content">
    <div class="viz-shell">
      <div class="viz-viewer-bar">
        <div class="viz-viewer-title"><a class="viz-btn viz-btn-light" href="<?php echo base_url(); ?>Visualization/studio"><i class="fa fa-arrow-left"></i></a><div><span class="viz-eyebrow viz-eyebrow-dark">Insight Studio</span><h1>Governed data sources</h1><p>Pick a governed connector from the JobSeeker catalog, approve a table and fields, then publish it to the Studio library.</p></div></div>
        <a class="viz-btn viz-btn-light" href="<?php echo base_url(); ?>Visualization"><i class="fa fa-compass"></i> Analytics hub</a>
      </div>

      <div class="viz-source-security"><i class="fa fa-shield"></i><div><strong>Safe by default</strong><span>Connections live in the shared ETL connector catalog, encrypted at rest. The browser never receives credentials, table names, or raw SQL, and Studio runs generated aggregate SELECT queries only.</span></div><div class="viz-source-security-points"><span><i class="fa fa-check"></i> One connection registry</span><span><i class="fa fa-check"></i> Dedicated read-only account</span><span><i class="fa fa-check"></i> 5-second connection timeout</span></div></div>

      <div id="vizSourceNotice" class="alert" style="display:none"></div>
      <div class="viz-source-layout">
        <div class="viz-source-main">
          <section class="viz-panel">
            <div class="viz-panel-head"><div><h2><span class="viz-step">1</span> Choose a governed connector</h2><p>Insight Studio uses MySQL, MariaDB and PostgreSQL connectors from the JobSeeker connector catalog whose credentials are stored locally.</p></div><a class="viz-btn viz-btn-light" href="<?php echo html_escape($manageConnectorsUrl); ?>"><i class="fa fa-plug"></i> Manage connectors</a></div>
            <div class="viz-panel-body">
              <?php if(empty($connections)) { ?>
                <div class="viz-empty viz-empty-compact"><i class="fa fa-database"></i><strong>No usable connectors yet</strong><p>Add a MySQL/MariaDB or PostgreSQL connector with a locally-stored secret on the <a href="<?php echo html_escape($manageConnectorsUrl); ?>">Connectors</a> page. The built-in <code>jobseeker-mariadb</code> connector is available out of the box.</p></div>
              <?php } else { ?>
                <table class="table viz-connector-table">
                  <thead><tr><th>Connector</th><th>Engine</th><th>Endpoint</th><th>Datasets</th></tr></thead>
                  <tbody>
                    <?php foreach($connections as $connection) { ?>
                      <tr>
                        <td><strong><?php echo html_escape($connection->name); ?></strong><?php echo (int) $connection->is_active === 1 ? '' : ' <span class="label label-default">inactive</span>'; ?></td>
                        <td><?php echo strtoupper(html_escape($connection->driver)); ?></td>
                        <td class="viz-connector-endpoint"><?php echo html_escape($connection->host); ?>:<?php echo (int) $connection->port; ?> / <?php echo html_escape($connection->database_name); ?></td>
                        <td><?php echo (int) $connection->dataset_count; ?></td>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
                <p class="viz-form-note">Create, edit and live-test these connectors on the <a href="<?php echo html_escape($manageConnectorsUrl); ?>">Connectors</a> page.</p>
              <?php } ?>
            </div>
          </section>

          <section class="viz-panel viz-source-builder">
            <div class="viz-panel-head"><div><h2><span class="viz-step">2</span> Publish a curated dataset</h2><p>Select only the dimensions and numeric measures analysts should see.</p></div><span class="viz-pill viz-pill-neutral"><i class="fa fa-eye-slash"></i> no raw SQL</span></div>
            <div class="viz-panel-body">
              <?php if(empty($connections)) { ?>
                <div id="vizDatasetEmpty" class="viz-empty viz-empty-compact"><i class="fa fa-database"></i><strong>Add a usable connector first</strong><p>The dataset builder will discover its schemas and tables here.</p></div>
              <?php } ?>
              <form id="vizDatasetForm" class="viz-form"<?php echo empty($connections) ? ' style="display:none"' : ''; ?>>
                <input type="hidden" name="id" value="0">
                <input id="vizDatasetSchema" type="hidden" name="table_schema">
                <input id="vizDatasetTableName" type="hidden" name="table_name">
                <div class="row">
                  <div class="col-md-6 form-group"><label for="vizDatasetConnection">Connector</label><select id="vizDatasetConnection" class="form-control" name="connection_id"><option value="">Choose a connector…</option><?php foreach($connections as $connection) { if((int) $connection->is_active === 1) { ?><option value="<?php echo (int) $connection->id; ?>"><?php echo html_escape($connection->name); ?> · <?php echo strtoupper(html_escape($connection->driver)); ?></option><?php } } ?></select></div>
                  <div class="col-md-6 form-group"><label for="vizDatasetTable">Table</label><select id="vizDatasetTable" class="form-control" disabled><option value="">Choose a connector first…</option></select></div>
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
            <div class="viz-panel-head"><div><h3>Catalog connectors</h3><p><?php echo number_format(count($connections)); ?> usable for Studio</p></div></div>
            <div class="viz-source-card-list">
              <?php if(!empty($connections)) { foreach($connections as $connection) { ?>
                <article class="viz-source-card"><div class="viz-source-card-icon"><i class="fa fa-database"></i></div><div class="viz-source-card-copy"><strong><?php echo html_escape($connection->name); ?></strong><span><?php echo html_escape($connection->host); ?>:<?php echo (int) $connection->port; ?></span><small><?php echo strtoupper(html_escape($connection->driver)); ?> · <?php echo html_escape($connection->database_name); ?> · <?php echo (int) $connection->dataset_count; ?> datasets</small></div></article>
              <?php } } else { ?><div class="viz-empty viz-empty-compact"><i class="fa fa-plug"></i><strong>No connectors yet</strong></div><?php } ?>
            </div>
            <div class="viz-panel-body"><a class="viz-btn viz-btn-light viz-btn-block" href="<?php echo html_escape($manageConnectorsUrl); ?>"><i class="fa fa-external-link"></i> Open Connectors</a></div>
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
  'tables' => base_url().'Visualization/connectionTables/',
  'columns' => base_url().'Visualization/connectionColumns/',
  'saveDataset' => base_url().'Visualization/saveDataset',
  'deleteDataset' => base_url().'Visualization/deleteDataset'
))); ?>;
</script>
<script src="<?php echo base_url(); ?>assets/js/visualization-datasources.js?v=2"></script>
