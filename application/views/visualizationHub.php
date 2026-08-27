<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/visualization.css?v=1">
<?php
$reportCount = !empty($reports) ? count($reports) : 0;
$dashboardCount = !empty($dashboards) ? count($dashboards) : 0;
$datasetCount = !empty($datasets) ? count($datasets) : 0;
$providerLabels = array('pbi' => 'Power BI', 'tbl' => 'Tableau', 'tblPublic' => 'Tableau Public', 'qlikSense' => 'Qlik Sense', 'qlikView' => 'QlikView', 'superset' => 'Superset', 'metabase' => 'Metabase', 'grafana' => 'Grafana', 'looker' => 'Looker', 'microstrategy' => 'MicroStrategy', 'custom' => 'Connected report');
?>
<div class="content-wrapper viz-page">
  <section class="content">
    <div class="viz-shell">
      <div class="viz-hero animated fadeIn">
        <div class="viz-hero-copy">
          <span class="viz-eyebrow">Analytics workspace</span>
          <h1>Turn operations into a clear story.</h1>
          <p>Build live dashboards from governed Jobseeker data, or bring trusted analytics from the tools your team already uses.</p>
        </div>
        <div class="viz-hero-actions">
          <a class="viz-btn viz-btn-primary" href="<?php echo base_url(); ?>Visualization/studio"><i class="fa fa-magic"></i> Open Insight Studio</a>
          <?php if($role == ROLE_ADMIN || $role == ROLE_MANAGER) { ?><a class="viz-btn viz-btn-ghost" href="<?php echo base_url(); ?>Visualization/dataSources"><i class="fa fa-database"></i> Connect data</a><?php } ?>
          <?php if($role == ROLE_ADMIN || $role == ROLE_MANAGER) { ?><a class="viz-btn viz-btn-ghost" href="<?php echo base_url(); ?>Visualization/config"><i class="fa fa-link"></i> Connect a report</a><?php } ?>
        </div>
      </div>
      <div class="viz-stat-row">
        <div class="viz-stat"><span class="viz-stat-icon"><i class="fa fa-th-large"></i></span><div><strong><?php echo number_format($dashboardCount); ?></strong><span>available dashboards</span></div></div>
        <div class="viz-stat"><span class="viz-stat-icon"><i class="fa fa-database"></i></span><div><strong><?php echo number_format($datasetCount); ?></strong><span>governed live datasets</span></div></div>
        <div class="viz-stat"><span class="viz-stat-icon"><i class="fa fa-external-link-square"></i></span><div><strong><?php echo number_format($reportCount); ?></strong><span>connected reports</span></div></div>
      </div>
      <section class="viz-section">
        <div class="viz-section-heading"><div><h2>Your dashboards</h2><p>Editable canvases made inside Jobseeker.</p></div><a class="viz-card-link" href="<?php echo base_url(); ?>Visualization/studio">Create dashboard <i class="fa fa-arrow-right"></i></a></div>
        <div class="viz-card-grid">
          <?php if(!empty($dashboards)) { foreach(array_slice($dashboards, 0, 6) as $dashboard) { ?>
            <a class="viz-card" href="<?php echo base_url(); ?>Visualization/studio?dashboard=<?php echo (int) $dashboard->id; ?>">
              <div class="viz-card-top"><span class="viz-card-icon"><i class="fa fa-area-chart"></i></span><span class="viz-pill <?php echo (int) $dashboard->is_shared === 1 ? '' : 'viz-pill-neutral'; ?>"><i class="fa <?php echo (int) $dashboard->is_shared === 1 ? 'fa-users' : 'fa-lock'; ?>"></i> <?php echo (int) $dashboard->is_shared === 1 ? 'Shared' : 'Private'; ?></span></div>
              <h3><?php echo html_escape($dashboard->name); ?></h3><p><?php echo html_escape($dashboard->description !== '' ? $dashboard->description : 'A live Jobseeker dashboard ready to explore.'); ?></p>
              <div class="viz-card-meta"><span>By <?php echo html_escape($dashboard->owner); ?></span><span class="viz-card-link">Open <i class="fa fa-arrow-right"></i></span></div>
            </a>
          <?php } } else { ?>
            <a class="viz-card" href="<?php echo base_url(); ?>Visualization/studio"><div class="viz-card-top"><span class="viz-card-icon"><i class="fa fa-plus"></i></span><span class="viz-pill">2 minute setup</span></div><h3>Build your first dashboard</h3><p>Drag TMF fields onto a canvas and choose the visual that tells the story best.</p><div class="viz-card-meta"><span>No SQL required</span><span class="viz-card-link">Start building <i class="fa fa-arrow-right"></i></span></div></a>
          <?php } ?>
        </div>
      </section>
      <section class="viz-section">
        <div class="viz-section-heading"><div><h2>Connected analytics</h2><p>Reports hosted by trusted BI platforms and presented in a governed frame.</p></div><?php if($role == ROLE_ADMIN || $role == ROLE_MANAGER) { ?><a class="viz-card-link" href="<?php echo base_url(); ?>Visualization/config">Manage connections <i class="fa fa-cog"></i></a><?php } ?></div>
        <div class="viz-card-grid">
          <?php if(!empty($reports)) { foreach($reports as $report) { $provider = isset($providerLabels[$report->type]) ? $providerLabels[$report->type] : $report->type; ?>
            <a class="viz-card" href="<?php echo base_url(); ?>Visualization/view/<?php echo rawurlencode($report->report); ?>"><div class="viz-card-top"><span class="viz-card-icon"><i class="fa fa-line-chart"></i></span><span class="viz-pill viz-pill-neutral"><?php echo html_escape($provider); ?></span></div><h3><?php echo html_escape($report->report); ?></h3><p>Open this report without leaving the Jobseeker analytics workspace.</p><div class="viz-card-meta"><span><span class="viz-live-dot"></span>Connected</span><span class="viz-card-link">View report <i class="fa fa-arrow-right"></i></span></div></a>
          <?php } } else { ?>
            <div class="viz-card"><div class="viz-card-top"><span class="viz-card-icon"><i class="fa fa-plug"></i></span><span class="viz-pill viz-pill-neutral">Optional</span></div><h3>No external reports connected</h3><p>Admins can add HTTPS embeds from Power BI, Superset, Metabase, Grafana and more.</p><div class="viz-card-meta"><span>Your native studio is ready</span></div></div>
          <?php } ?>
        </div>
      </section>
      <div class="viz-safety"><i class="fa fa-shield"></i><div><h3>Governed by design</h3><p>Dashboards use named datasets and server-side aggregations. Browser clients never receive database credentials or arbitrary SQL access.</p></div><span class="viz-safety-point"><i class="fa fa-check"></i> Allowlisted fields</span><span class="viz-safety-point"><i class="fa fa-check"></i> Encrypted connections</span><span class="viz-safety-point"><i class="fa fa-check"></i> Sandboxed embeds</span></div>
    </div>
  </section>
</div>
