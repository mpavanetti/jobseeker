<?php
$dashboardEnvironment = isset($selectedEnvironment) ? (string) $selectedEnvironment : 'all';
$dashboardEnvironmentLabel = $dashboardEnvironment !== '' && strtolower($dashboardEnvironment) !== 'all' ? strtoupper($dashboardEnvironment) : 'All environments';
$dashboardEnvironmentQuery = $dashboardEnvironment !== '' && strtolower($dashboardEnvironment) !== 'all' ? '?environment='.rawurlencode($dashboardEnvironment) : '';
$dashboardCanViewExecutors = isset($role) && ($role == ROLE_ADMIN || $role == ROLE_MANAGER);
$dashboardConfig = array(
  'environment' => $dashboardEnvironment,
  'environmentLabel' => $dashboardEnvironmentLabel,
  'overviewUrl' => base_url('dashboard/overview'),
  'jenkinsMetricsUrl' => base_url('jenkins/dashboardMetrics'),
  'tmfUrl' => base_url('tmf'),
  'dataAssetsUrl' => base_url('data-assets'),
  'executorMonitorUrl' => base_url('jobExecution/executors')
);
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.css">
<style>
  .dashboard-overview .box { border-radius: 3px; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
  .dashboard-overview .box-header .box-title { font-size: 17px; font-weight: 600; }
  .dashboard-overview .box-header .box-subtitle { color: #777; display: block; font-size: 12px; margin: 5px 0 0 25px; }
  .dashboard-scope { align-items: center; display: inline-flex; gap: 7px; margin-top: 6px; }
  .dashboard-scope .label { font-size: 11px; padding: 4px 7px; }
  .dashboard-updated { color: #777; font-size: 12px; margin-top: 8px; }
  .dashboard-loading { padding: 70px 20px; text-align: center; }
  .dashboard-loading i { color: #3c8dbc; font-size: 34px; }
  .dashboard-loading p { color: #777; margin-top: 12px; }
  .dashboard-kpis .small-box { min-height: 132px; }
  .dashboard-kpis .small-box .inner h3 { font-size: 32px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .dashboard-kpis .small-box .inner p { font-weight: 600; }
  .dashboard-kpis .small-box .metric-detail { display: block; font-size: 12px; font-weight: 400; min-height: 18px; opacity: .88; }
  .dashboard-kpis .small-box .icon { font-size: 74px; top: 4px; }
  .dashboard-jenkins-body { align-items: center; display: flex; gap: 24px; justify-content: space-between; }
  .dashboard-jenkins-copy { flex: 1 1 260px; min-width: 220px; }
  .dashboard-jenkins-copy h4 { color: #3c8dbc; font-weight: 600; margin: 0 0 5px; }
  .dashboard-jenkins-copy p { color: #666; margin: 0; }
  .dashboard-jenkins-grid { display: grid; flex: 1 1 640px; gap: 12px; grid-template-columns: repeat(4, minmax(120px, 1fr)); }
  .dashboard-live-stat { background: #f7f9fb; border: 1px solid #dce4ea; border-radius: 3px; min-height: 76px; padding: 11px 13px; }
  .dashboard-live-stat strong { color: #34495e; display: block; font-size: 23px; line-height: 1.15; }
  .dashboard-live-stat span { color: #777; display: block; font-size: 11px; letter-spacing: .02em; margin-top: 3px; text-transform: uppercase; }
  .dashboard-live-stat .progress { background: #e5e9ed; height: 4px; margin: 8px 0 0; }
  .dashboard-live-stat.is-warning { border-color: #f39c12; }
  .dashboard-live-stat.is-danger { border-color: #dd4b39; }
  .dashboard-period-controls .btn { min-width: 50px; }
  .dashboard-chart-wrap { height: 315px; position: relative; }
  .dashboard-chart-wrap.dashboard-chart-small { height: 270px; }
  .dashboard-empty { align-items: center; background: #f9fbfc; border: 1px dashed #cbd6de; color: #777; display: none; flex-direction: column; justify-content: center; min-height: 220px; padding: 30px; text-align: center; }
  .dashboard-empty i { color: #9aaab5; font-size: 34px; margin-bottom: 9px; }
  .dashboard-empty strong { color: #455a64; display: block; margin-bottom: 4px; }
  .dashboard-compare .description-block { margin: 8px 0; }
  .dashboard-compare .description-header { font-size: 20px; }
  .dashboard-compare .description-text { color: #777; display: block; font-size: 11px; margin-top: 4px; }
  .dashboard-table { margin-bottom: 0; table-layout: fixed; width: 100%; }
  .dashboard-table th { background: #f7f9fb; color: #555; font-size: 11px; letter-spacing: .02em; text-transform: uppercase; }
  .dashboard-table th, .dashboard-table td { overflow: hidden; text-overflow: ellipsis; vertical-align: middle !important; white-space: nowrap; }
  .dashboard-table .dashboard-primary-cell { color: #34495e; font-weight: 600; }
  .dashboard-table .dashboard-secondary-cell { color: #777; display: block; font-size: 11px; font-weight: 400; margin-top: 2px; }
  .dashboard-status-label { display: inline-block; min-width: 67px; text-align: center; text-transform: capitalize; }
  .dashboard-platform-summary { border-bottom: 1px solid #f0f0f0; margin-bottom: 15px; padding-bottom: 12px; }
  .dashboard-platform-stat { border-right: 1px solid #eee; min-height: 58px; padding: 3px 12px; }
  .dashboard-platform-summary > div:last-child .dashboard-platform-stat { border-right: 0; }
  .dashboard-platform-stat strong { color: #34495e; display: block; font-size: 23px; }
  .dashboard-platform-stat span { color: #777; font-size: 11px; text-transform: uppercase; }
  .dashboard-platform-note { background: #f7fbfd; border-left: 3px solid #3c8dbc; color: #607d8b; font-size: 12px; margin: 0 0 15px; padding: 9px 12px; }
  .dashboard-health-note { color: #777; font-size: 12px; margin: 8px 0 0; }
  .dashboard-error { display: none; }
  .dashboard-refresh-spin { animation: dashboard-spin 1s linear infinite; }
  @keyframes dashboard-spin { to { transform: rotate(360deg); } }
  @media (max-width: 991px) {
    .dashboard-jenkins-body { align-items: stretch; flex-direction: column; }
    .dashboard-jenkins-grid { grid-template-columns: repeat(2, minmax(120px, 1fr)); width: 100%; }
  }
  @media (max-width: 767px) {
    .dashboard-overview .content-header h1 small { display: block; margin: 5px 0 0; }
    .dashboard-kpis .small-box .inner h3 { font-size: 27px; }
    .dashboard-jenkins-grid { grid-template-columns: 1fr 1fr; }
    .dashboard-chart-wrap { height: 270px; }
    .dashboard-platform-stat { border-bottom: 1px solid #eee; border-right: 0; margin-bottom: 8px; padding-bottom: 8px; }
    .dashboard-table { table-layout: auto; }
  }
</style>

<div class="content-wrapper dashboard-overview">
  <section class="content-header">
    <h1><i class="fa fa-dashboard"></i> Operations Dashboard <small>Execution health, capacity and data workload signals</small></h1>
    <div class="dashboard-scope">
      <span class="text-muted">Viewing</span><span class="label label-primary"><?php echo html_escape($dashboardEnvironmentLabel); ?></span>
      <span class="text-muted">Use the environment selector above to change scope.</span>
    </div>
  </section>

  <section class="content">
    <div id="dashboardError" class="callout callout-danger dashboard-error"><h4><i class="fa fa-exclamation-circle"></i> Dashboard metrics could not be loaded</h4><p id="dashboardErrorMessage">Refresh the page or check the application logs.</p></div>
    <div id="dashboardLoading" class="box box-primary dashboard-loading"><i class="fa fa-circle-o-notch dashboard-refresh-spin"></i><p>Building a fresh operational snapshot for <?php echo html_escape($dashboardEnvironmentLabel); ?>...</p></div>

    <div id="dashboardContent" style="display:none;">
      <div class="row dashboard-kpis">
        <div class="col-lg-3 col-sm-6 col-xs-12"><div class="small-box bg-aqua"><div class="inner"><h3 id="dashboardActive">0</h3><p>Active executions</p><span id="dashboardActiveDetail" class="metric-detail">TMF running state</span></div><div class="icon"><i class="fa fa-refresh"></i></div><a href="<?php echo base_url('tmf/fetchDataStatus/running').$dashboardEnvironmentQuery; ?>" class="small-box-footer">Inspect running <i class="fa fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-sm-6 col-xs-12"><div class="small-box bg-green"><div class="inner"><h3 id="dashboardReadyRate">--</h3><p>Ready rate · 30 days</p><span id="dashboardReadyDetail" class="metric-detail">Completed executions assessed</span></div><div class="icon"><i class="fa fa-check-circle"></i></div><a href="<?php echo base_url('tmf/fetchDataStatus/ready').$dashboardEnvironmentQuery; ?>" class="small-box-footer">Inspect ready <i class="fa fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-sm-6 col-xs-12"><div class="small-box bg-yellow"><div class="inner"><h3 id="dashboardAttention">0</h3><p>Needs attention · 30 days</p><span id="dashboardAttentionDetail" class="metric-detail">Warnings and errors</span></div><div class="icon"><i class="fa fa-warning"></i></div><a href="<?php echo base_url('tmf').$dashboardEnvironmentQuery; ?>" class="small-box-footer">Open transaction monitor <i class="fa fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-sm-6 col-xs-12"><div class="small-box bg-purple"><div class="inner"><h3 id="dashboardRecords">0</h3><p>Records processed · 30 days</p><span id="dashboardRecordsDetail" class="metric-detail">Across all reported executions</span></div><div class="icon"><i class="fa fa-database"></i></div><span class="small-box-footer" id="dashboardHistoryDetail">Telemetry history</span></div></div>
      </div>

      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-server text-blue"></i> Jenkins Capacity</h3><div class="box-tools pull-right"><?php if ($dashboardCanViewExecutors) { ?><a class="btn btn-box-tool" href="<?php echo base_url('jobExecution/executors'); ?>"><i class="fa fa-external-link"></i> Executor monitor</a><?php } ?><button id="dashboardRefresh" type="button" class="btn btn-box-tool" title="Refresh dashboard"><i class="fa fa-refresh"></i></button></div></div>
        <div class="box-body dashboard-jenkins-body">
          <div class="dashboard-jenkins-copy"><h4 id="dashboardJenkinsHeadline">Loading live Jenkins state...</h4><p id="dashboardJenkinsDetail">Executor, queue and agent metrics refresh every 30 seconds.</p><div id="dashboardUpdated" class="dashboard-updated"></div></div>
          <div class="dashboard-jenkins-grid">
            <div id="dashboardExecutorStat" class="dashboard-live-stat"><strong id="dashboardExecutors">--</strong><span>Busy / total executors</span><div class="progress"><div id="dashboardExecutorBar" class="progress-bar progress-bar-primary" style="width:0%"></div></div></div>
            <div id="dashboardQueueStat" class="dashboard-live-stat"><strong id="dashboardQueue">--</strong><span>Queued builds</span></div>
            <div id="dashboardAgentStat" class="dashboard-live-stat"><strong id="dashboardAgents">--</strong><span>Online / total agents</span></div>
            <div id="dashboardSlotStat" class="dashboard-live-stat"><strong id="dashboardSlots">--</strong><span>Active / trigger limit</span></div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-8 col-xs-12">
          <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-line-chart text-blue"></i> Execution Health Trend</h3><span class="box-subtitle">One comparable timeline replaces the overlapping 30/90/180 growth cards.</span><div class="box-tools pull-right dashboard-period-controls"><div class="btn-group" data-toggle="buttons"><label class="btn btn-default btn-xs active"><input type="radio" name="dashboardTrendDays" value="30" checked>30d</label><label class="btn btn-default btn-xs"><input type="radio" name="dashboardTrendDays" value="90">90d</label><label class="btn btn-default btn-xs"><input type="radio" name="dashboardTrendDays" value="180">180d</label></div></div></div>
            <div class="box-body"><div id="dashboardTrendWrap" class="dashboard-chart-wrap"><canvas id="dashboardTrendChart"></canvas></div><div id="dashboardTrendEmpty" class="dashboard-empty"><i class="fa fa-line-chart"></i><strong>No executions in this period</strong><span>Choose a longer range or run a job.</span></div><p class="dashboard-health-note"><i class="fa fa-info-circle"></i> Ready, warning, error and cancelled are terminal TMF outcomes. Running is shown as a live KPI instead of a historical success series.</p></div>
            <div class="box-footer dashboard-compare"><div class="row">
              <div class="col-sm-3 col-xs-6"><div class="description-block border-right"><span id="dashboardExecutionChange" class="description-percentage">--</span><h5 id="dashboardExecutionCurrent" class="description-header">0</h5><span class="description-text">Executions · current 30d</span></div></div>
              <div class="col-sm-3 col-xs-6"><div class="description-block border-right"><span id="dashboardRateChange" class="description-percentage">--</span><h5 id="dashboardRateCurrent" class="description-header">--</h5><span class="description-text">Ready rate · current 30d</span></div></div>
              <div class="col-sm-3 col-xs-6"><div class="description-block border-right"><span id="dashboardAttentionChange" class="description-percentage">--</span><h5 id="dashboardAttentionCurrent" class="description-header">0</h5><span class="description-text">Warning + error · current 30d</span></div></div>
              <div class="col-sm-3 col-xs-6"><div class="description-block"><span id="dashboardDurationChange" class="description-percentage">--</span><h5 id="dashboardDurationCurrent" class="description-header">--</h5><span class="description-text">Average runtime · current 30d</span></div></div>
            </div></div>
          </div>
        </div>
        <div class="col-lg-4 col-xs-12"><div class="box box-primary"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-pie-chart text-blue"></i> TMF State Mix · 30 Days</h3></div><div class="box-body"><div id="dashboardStatusWrap" class="dashboard-chart-wrap dashboard-chart-small"><canvas id="dashboardStatusChart"></canvas></div><div id="dashboardStatusEmpty" class="dashboard-empty"><i class="fa fa-pie-chart"></i><strong>No states yet</strong><span>Status distribution will appear after executions report TMF state.</span></div></div></div></div>
      </div>

      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-globe text-blue"></i> Environment Execution Summary</h3><span class="box-subtitle">Comparable 30-day execution, quality, volume and runtime signals.</span></div>
        <div class="box-body table-responsive no-padding"><table class="table table-hover dashboard-table"><thead><tr><th>Environment</th><th>Executions</th><th>Ready rate</th><th>Attention</th><th>Running</th><th>Records</th><th>Avg runtime</th><th>Last activity</th></tr></thead><tbody id="dashboardEnvironmentRows"></tbody></table><div id="dashboardEnvironmentEmpty" class="dashboard-empty"><i class="fa fa-globe"></i><strong>No environment activity in the last 30 days</strong></div></div>
      </div>

      <div class="row">
        <div class="col-lg-6 col-xs-12"><div class="box box-primary"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-clock-o text-blue"></i> Recent Executions</h3></div><div class="box-body table-responsive no-padding"><table class="table table-hover dashboard-table"><thead><tr><th style="width:31%">Job</th><th>Environment</th><th>Status</th><th>Records</th><th>Runtime</th><th>Activity</th></tr></thead><tbody id="dashboardRecentRows"></tbody></table><div id="dashboardRecentEmpty" class="dashboard-empty"><i class="fa fa-clock-o"></i><strong>No recent executions</strong></div></div></div></div>
        <div class="col-lg-6 col-xs-12"><div class="box box-primary"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-tasks text-blue"></i> Most Active Pipelines · 30 Days</h3></div><div class="box-body table-responsive no-padding"><table class="table table-hover dashboard-table"><thead><tr><th style="width:34%">Pipeline</th><th>Runs</th><th>Ready rate</th><th>Attention</th><th>Records</th><th>Avg runtime</th></tr></thead><tbody id="dashboardPipelineRows"></tbody></table><div id="dashboardPipelineEmpty" class="dashboard-empty"><i class="fa fa-tasks"></i><strong>No pipeline activity in this period</strong></div></div></div></div>
      </div>

      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-cubes text-blue"></i> Data Platform Workloads</h3><span class="box-subtitle">Execution subjects and governed data assets across modern and traditional data architectures.</span><div class="box-tools pull-right"><a href="<?php echo base_url('data-assets'); ?>" class="btn btn-box-tool"><i class="fa fa-external-link"></i> Data asset catalog</a></div></div>
        <div class="box-body">
          <p class="dashboard-platform-note"><i class="fa fa-info-circle"></i> Workloads are inferred from TMF job, dimension and task metadata. Categories include ingestion and landing zones, transformation, warehouses, lakes and lakehouses, semantic serving, quality/governance, and ML feature pipelines. They represent observed executions—not discovered physical tables.</p>
          <div class="row dashboard-platform-summary"><div class="col-sm-3 col-xs-6"><div class="dashboard-platform-stat"><strong id="dashboardWorkloadExecutions">0</strong><span>Observed runs · 180d</span></div></div><div class="col-sm-3 col-xs-6"><div class="dashboard-platform-stat"><strong id="dashboardAssetActive">0</strong><span>Active catalog assets</span></div></div><div class="col-sm-3 col-xs-6"><div class="dashboard-platform-stat"><strong id="dashboardAssetInputs">0</strong><span>Input / reference assets</span></div></div><div class="col-sm-3 col-xs-6"><div class="dashboard-platform-stat"><strong id="dashboardAssetOutputs">0</strong><span>Output contracts</span></div></div></div>
          <div class="row"><div class="col-lg-8 col-xs-12"><h5 class="text-center"><b>Execution subjects · last 180 days</b></h5><div id="dashboardWorkloadWrap" class="dashboard-chart-wrap"><canvas id="dashboardWorkloadChart"></canvas></div><div id="dashboardWorkloadEmpty" class="dashboard-empty"><i class="fa fa-cubes"></i><strong>No classifiable workloads yet</strong></div></div><div class="col-lg-4 col-xs-12"><h5 class="text-center"><b>Active assets by format</b></h5><div id="dashboardAssetWrap" class="dashboard-chart-wrap"><canvas id="dashboardAssetChart"></canvas></div><div id="dashboardAssetEmpty" class="dashboard-empty"><i class="fa fa-file-o"></i><strong>No active assets in this scope</strong><span>Publish an input or output contract to populate this chart.</span></div></div></div>
        </div>
      </div>
    </div>
  </section>
</div>

<script src="<?php echo base_url(); ?>assets/bower_components/moment/moment.min.js"></script>
<script src="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.js"></script>
<script>window.jobseekerDashboardConfig = <?php echo json_encode($dashboardConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
<script src="<?php echo base_url(); ?>assets/js/dashboard.js?v=42"></script>
