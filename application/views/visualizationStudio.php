<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/visualization.css?v=1">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/visualization-studio.css?v=4">
<div class="content-wrapper viz-page studio-page">
  <section class="content">
    <div class="studio-shell">
      <div id="studioRuntimeNotice" class="alert alert-danger" style="display:none"><i class="fa fa-exclamation-triangle"></i> Insight Studio could not start: <span></span> Refresh the page; if this persists, contact an administrator.</div>
      <header class="studio-topbar">
        <a class="studio-btn" href="<?php echo base_url(); ?>Visualization" aria-label="Back to visualization hub"><i class="fa fa-arrow-left"></i></a>
        <div class="studio-title-wrap">
          <input id="studioDashboardName" class="studio-title" maxlength="120" value="Operations pulse" aria-label="Dashboard name">
          <div class="studio-title-meta"><span id="studioOwnership">Unsaved dashboard</span><span id="studioDirty" class="studio-dirty">Unsaved changes</span></div>
        </div>
        <div class="studio-top-actions">
          <button id="studioDataToggle" class="studio-btn studio-mobile-data-toggle" type="button"><i class="fa fa-database"></i><span> Data</span></button>
          <?php if($canManageData) { ?><a class="studio-btn" href="<?php echo base_url(); ?>Visualization/dataSources" title="Manage governed data sources"><i class="fa fa-plug"></i><span> Data sources</span></a><?php } ?>
          <select id="studioDashboardPicker" class="studio-dashboard-picker" aria-label="Open a saved dashboard"><option value="">Saved dashboards…</option><?php foreach($dashboards as $dashboard) { ?><option value="<?php echo (int) $dashboard->id; ?>"><?php echo html_escape($dashboard->name); ?><?php echo (int) $dashboard->owner_id === (int) $currentUserId ? '' : ' · shared'; ?></option><?php } ?></select>
          <button id="studioNewDashboard" class="studio-btn" type="button"><i class="fa fa-plus"></i><span> New</span></button>
          <button id="studioRefresh" class="studio-btn" type="button"><i class="fa fa-refresh"></i><span> Refresh</span></button>
          <button id="studioImport" class="studio-btn studio-btn-icon" type="button" title="Import dashboard JSON" aria-label="Import dashboard JSON"><i class="fa fa-upload"></i></button>
          <input id="studioImportFile" class="studio-import-file" type="file" accept="application/json,.json" hidden tabindex="-1">
          <button id="studioExport" class="studio-btn studio-btn-icon" type="button" title="Export dashboard JSON" aria-label="Export dashboard JSON"><i class="fa fa-download"></i></button>
          <button id="studioPresentation" class="studio-btn studio-btn-icon" type="button" title="Presentation mode" aria-label="Presentation mode"><i class="fa fa-expand"></i></button>
          <label class="studio-team-share" title="Share this dashboard with the team"><input id="studioShareDashboard" type="checkbox"><i class="fa fa-users"></i><span> Team</span></label>
          <button id="studioDeleteDashboard" class="studio-btn studio-btn-danger studio-btn-icon" type="button" title="Delete dashboard" aria-label="Delete dashboard" disabled><i class="fa fa-trash"></i></button>
          <button id="studioSaveDashboard" class="studio-btn studio-btn-primary" type="button"><i class="fa fa-cloud-upload"></i><span> Save dashboard</span></button>
        </div>
      </header>

      <div class="studio-workspace">
        <aside id="studioDataPanel" class="studio-panel studio-sidebar">
          <div class="studio-panel-heading"><button id="studioCloseData" class="studio-icon-btn studio-close-data" type="button" aria-label="Close data library"><i class="fa fa-times"></i></button><div class="studio-panel-title-row"><h2>Data library</h2><span class="studio-panel-badge"><?php echo count($datasets); ?> sources</span></div><p>Governed, read-only operational datasets.</p></div>
          <div class="studio-panel-body">
            <div class="studio-field-search"><i class="fa fa-search"></i><input id="studioFieldSearch" type="search" placeholder="Find datasets or fields…" aria-label="Search datasets and fields"></div>
            <div class="studio-section-label"><span>Datasets</span><i class="fa fa-shield"></i></div>
            <div id="studioDatasets" class="studio-dataset-list"></div>
            <div class="studio-section-label"><span>Dimensions</span><span>drag</span></div>
            <div id="studioDimensions" class="studio-field-list"></div>
            <div class="studio-section-label"><span>Measures</span><span>drag</span></div>
            <div id="studioMeasures" class="studio-field-list"></div>
          </div>
        </aside>

        <main class="studio-panel studio-canvas-panel">
          <div class="studio-compose">
            <div class="studio-compose-top"><div><strong>Visual recipe</strong> <span>Drag fields below, or click a field to assign it.</span></div><div id="studioChartTypes" class="studio-chart-types" aria-label="Chart type"></div></div>
            <div class="studio-recipe">
              <div id="studioRecipeDataset" class="studio-dropzone studio-recipe-dataset"><i class="fa fa-database"></i><div class="studio-dropzone-label"><small>Dataset</small><strong>TMF runs</strong></div></div>
              <div id="studioDimensionDrop" class="studio-dropzone" data-accept="dimension"><i class="fa fa-tag"></i><div class="studio-dropzone-label"><small>Category / time</small><strong>Status</strong></div></div>
              <div id="studioMeasureDrop" class="studio-dropzone" data-accept="measure"><i class="fa fa-hashtag"></i><div class="studio-dropzone-label"><small>Value</small><strong>Runs</strong></div></div>
              <button id="studioAddWidget" class="studio-btn studio-btn-primary" type="button"><i class="fa fa-plus"></i> Add to canvas</button>
            </div>
          </div>
          <div class="studio-canvas-toolbar"><span class="studio-canvas-summary"><strong id="studioWidgetCount">0 visuals</strong><small>Drag cards to reorder</small></span><div class="studio-global-filters"><label><i class="fa fa-calendar"></i><select id="studioGlobalRange" aria-label="Dashboard time window"><option value="7d">7 days</option><option value="30d" selected>30 days</option><option value="90d">90 days</option><option value="180d">180 days</option><option value="all">All time</option></select></label><label><i class="fa fa-server"></i><select id="studioGlobalEnvironment" aria-label="Dashboard environment"><option value="all">All environments</option></select></label><button id="studioApplyGlobalFilters" class="studio-btn" type="button"><i class="fa fa-filter"></i> Apply to all</button></div><div class="studio-canvas-actions"><button id="studioClearCanvas" class="studio-icon-btn" type="button" title="Clear canvas"><i class="fa fa-eraser"></i></button></div></div>
          <div id="studioGrid" class="studio-grid"></div>
          <div id="studioEmptyCanvas" class="studio-empty-canvas"><div><div class="studio-empty-illustration"><span></span><span></span><span></span></div><h3>Your canvas is ready</h3><p>Choose a dataset, drop a dimension and measure into the recipe, then add the visualization.</p><button id="studioUseStarter" class="studio-btn studio-btn-primary" type="button"><i class="fa fa-bolt"></i> Use starter layout</button></div></div>
        </main>

        <aside id="studioInspector" class="studio-panel studio-sidebar studio-inspector">
          <div class="studio-panel-heading"><button id="studioCloseInspector" class="studio-icon-btn studio-close-inspector" type="button" aria-label="Close visual settings"><i class="fa fa-times"></i></button><h3>Visual settings</h3><p>Fine-tune the selected card.</p></div>
          <div id="studioInspectorEmpty" class="studio-inspector-empty"><i class="fa fa-sliders"></i>Select a visual on the canvas to configure it.</div>
          <div id="studioInspectorForm" class="studio-panel-body studio-inspector-form">
            <div class="studio-control"><label for="studioWidgetTitle">Title</label><input id="studioWidgetTitle" class="form-control" maxlength="100"></div>
            <div class="studio-control"><label>Visual</label><div id="studioInspectorCharts" class="studio-segmented"></div></div>
            <div class="studio-control"><label for="studioInspectorDataset">Dataset</label><select id="studioInspectorDataset" class="form-control"></select></div>
            <div class="studio-control"><label for="studioInspectorDimension">Category / time</label><select id="studioInspectorDimension" class="form-control"></select></div>
            <div class="studio-control"><label for="studioInspectorMeasure">Value</label><select id="studioInspectorMeasure" class="form-control"></select></div>
            <div class="studio-control"><label for="studioInspectorRange">Time window</label><select id="studioInspectorRange" class="form-control"><option value="7d">Last 7 days</option><option value="30d">Last 30 days</option><option value="90d">Last 90 days</option><option value="180d">Last 180 days</option><option value="all">All time</option></select></div>
            <div class="studio-control"><label for="studioInspectorEnvironment">Environment</label><select id="studioInspectorEnvironment" class="form-control"><option value="all">All environments</option></select></div>
            <div class="studio-control"><label>Card width</label><div id="studioSizePicker" class="studio-size-picker"><button type="button" data-size="4">S</button><button type="button" data-size="6">M</button><button type="button" data-size="8">L</button><button type="button" data-size="12">Full</button></div></div>
            <div class="studio-control"><label>Palette</label><div id="studioPalettePicker" class="studio-palette-picker"><button type="button" class="studio-palette" data-palette="aurora" title="Aurora"><span></span></button><button type="button" class="studio-palette" data-palette="signal" title="Signal"><span></span></button><button type="button" class="studio-palette" data-palette="ocean" title="Ocean"><span></span></button><button type="button" class="studio-palette" data-palette="mono" title="Monochrome"><span></span></button></div></div>
            <div class="studio-inspector-actions"><button id="studioApplyWidget" class="studio-btn studio-btn-primary" type="button"><i class="fa fa-check"></i> Apply</button><button id="studioRemoveWidget" class="studio-btn studio-btn-danger" type="button" title="Remove visual"><i class="fa fa-trash"></i></button></div>
          </div>
        </aside>
      </div>
    </div>
  </section>
</div>
<script src="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.js"></script>
<script>
window.JobSeekerInsightStudio = <?php echo json_encode(array(
  'datasets' => array_values($datasets),
  'dashboards' => $dashboards,
  'currentUserId' => (int) $currentUserId,
  'currentUserName' => $currentUserName,
  'initialDashboardId' => (int) $this->input->get('dashboard', TRUE),
  'endpoints' => array(
    'query' => base_url().'Visualization/query',
    'dashboard' => base_url().'Visualization/dashboard/',
    'save' => base_url().'Visualization/saveDashboard',
    'delete' => base_url().'Visualization/deleteDashboard'
  )
)); ?>;
</script>
<script src="<?php echo base_url(); ?>assets/js/visualization-studio.js?v=6"></script>
