<?php
$counts = isset($statusCounts) ? $statusCounts : array();
$succeeded = isset($counts['SUCCEEDED']) ? (int) $counts['SUCCEEDED'] : 0;
$failed = (isset($counts['FAILED']) ? (int) $counts['FAILED'] : 0) + (isset($counts['TIMED_OUT']) ? (int) $counts['TIMED_OUT'] : 0);
$total30 = array_sum(array_map('intval', $counts));
$successRate = $total30 > 0 ? round($succeeded / $total30 * 100) : null;
$host = isset($capacity['host']) ? $capacity['host'] : array();
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=2">
<div class="content-wrapper">
  <section class="content-header">
    <h1>Machine Learning <small>platform overview</small></h1>
  </section>
  <section class="content">

    <div class="ml-tiles">
      <div class="ml-tile <?php echo empty($capacity['engine_healthy']) ? 'ml-bad' : 'ml-good'; ?>">
        <div class="ml-tile-label">Compute engine</div>
        <div class="ml-tile-value"><?php echo empty($capacity['engine_healthy']) ? 'Down' : 'Ready'; ?></div>
        <div class="ml-tile-sub"><?php echo html_escape($driverName); ?> driver</div>
      </div>
      <div class="ml-tile">
        <div class="ml-tile-label">Host free</div>
        <div class="ml-tile-value"><?php echo isset($host['freeCpus']) ? html_escape($host['freeCpus']) : '—'; ?> vCPU</div>
        <div class="ml-tile-sub"><?php echo isset($host['freeMemoryMb']) ? number_format($host['freeMemoryMb']).' MB free' : 'capacity unknown'; ?></div>
      </div>
      <div class="ml-tile">
        <div class="ml-tile-label">Active runs</div>
        <div class="ml-tile-value" id="mlActiveRuns"><?php echo (int) (isset($capacity['active_runs']) ? $capacity['active_runs'] : 0); ?></div>
        <div class="ml-tile-sub">max <?php echo (int) (isset($capacity['max_concurrent']) ? $capacity['max_concurrent'] : 0); ?> concurrent</div>
      </div>
      <div class="ml-tile <?php echo $successRate !== null && $successRate < 80 ? 'ml-warn' : ''; ?>">
        <div class="ml-tile-label">Run success (30d)</div>
        <div class="ml-tile-value"><?php echo $successRate === null ? '—' : $successRate.'%'; ?></div>
        <div class="ml-tile-sub"><?php echo $succeeded; ?> ok · <?php echo $failed; ?> failed</div>
      </div>
      <div class="ml-tile <?php echo (int) $openAlertCount > 0 ? 'ml-bad' : 'ml-good'; ?>">
        <div class="ml-tile-label">Open alerts</div>
        <div class="ml-tile-value"><?php echo (int) $openAlertCount; ?></div>
        <div class="ml-tile-sub"><?php echo (int) $monitorCount; ?> monitor(s)</div>
      </div>
      <div class="ml-tile">
        <div class="ml-tile-label">Catalogue</div>
        <div class="ml-tile-value"><?php echo (int) $jobCount; ?> jobs</div>
        <div class="ml-tile-sub"><?php echo (int) (isset($datasetStats->datasets) ? $datasetStats->datasets : 0); ?> datasets · <?php echo array_sum(array_map('intval', (array) $modelStages)); ?> model versions</div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="box box-default">
          <div class="box-header with-border"><h3 class="box-title">Run outcomes (last 30 days)</h3>
            <div class="box-tools">
              <a href="<?php echo base_url(); ?>machine-learning/jobs" class="btn btn-xs btn-primary">New job</a>
              <a href="<?php echo base_url(); ?>machine-learning/datasets" class="btn btn-xs btn-default">New dataset</a>
            </div>
          </div>
          <div class="box-body"><div class="ml-canvas sm"><canvas id="outcomeChart"></canvas></div></div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-7">
        <div class="box box-primary">
          <div class="box-header with-border"><h3 class="box-title">Recent runs</h3>
            <div class="box-tools"><a href="<?php echo base_url(); ?>machine-learning/runs" class="btn btn-xs btn-default">All runs</a></div>
          </div>
          <div class="box-body no-padding">
            <table class="table table-hover">
              <thead><tr><th>Run</th><th>Type</th><th>Status</th><th>Started</th></tr></thead>
              <tbody>
              <?php foreach ($recentRuns as $run): ?>
                <tr>
                  <td><a href="<?php echo base_url(); ?>machine-learning/runs/detail/<?php echo (int) $run->id; ?>"><?php echo html_escape($run->name ?: substr($run->run_key, 0, 8)); ?></a></td>
                  <td><span class="ml-badge <?php echo html_escape($run->run_type); ?>"><?php echo html_escape($run->run_type); ?></span></td>
                  <td><span class="ml-status <?php echo html_escape($run->status); ?>"><?php echo html_escape($run->status); ?></span></td>
                  <td class="ml-muted ml-nowrap"><?php echo html_escape($run->started_at ?: $run->queued_at); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($recentRuns)): ?><tr><td colspan="4" class="ml-muted">No runs yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-5">
        <div class="box box-success">
          <div class="box-header with-border"><h3 class="box-title">Production models</h3></div>
          <div class="box-body no-padding">
            <table class="table">
              <tbody>
              <?php foreach ($productionModels as $mv): ?>
                <tr>
                  <td><a href="<?php echo base_url(); ?>machine-learning/models/version/<?php echo (int) $mv->id; ?>"><?php echo html_escape($mv->model_name); ?></a>
                    <span class="ml-muted">v<?php echo (int) $mv->version; ?></span></td>
                  <td class="ml-muted ml-nowrap"><?php echo html_escape($mv->environment); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($productionModels)): ?><tr><td class="ml-muted">Nothing in production yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="box box-danger">
          <div class="box-header with-border"><h3 class="box-title">Open alerts</h3>
            <div class="box-tools"><a href="<?php echo base_url(); ?>machine-learning/monitoring" class="btn btn-xs btn-default">Monitoring</a></div>
          </div>
          <div class="box-body no-padding">
            <table class="table">
              <tbody>
              <?php foreach ($openAlerts as $alert): ?>
                <tr>
                  <td><span class="ml-status <?php echo html_escape($alert->severity); ?>"><?php echo html_escape(strtoupper($alert->severity)); ?></span>
                    <?php echo html_escape($alert->title); ?>
                    <div class="ml-muted" style="font-size:11px"><?php echo html_escape($alert->detail); ?></div></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($openAlerts)): ?><tr><td class="ml-muted">No open alerts.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </section>
</div>
<script id="mlStatusCounts" type="application/json"><?php echo json_encode($statusCounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=2"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-ui.js?v=2"></script>
<script>
jQuery(function ($) {
  var counts = {};
  try { counts = JSON.parse(document.getElementById('mlStatusCounts').textContent); } catch (e) {}
  var order = ['SUCCEEDED', 'FAILED', 'TIMED_OUT', 'CANCELLED', 'RUNNING', 'QUEUED'];
  var palette = { SUCCEEDED: '#00a65a', FAILED: '#dd4b39', TIMED_OUT: '#f39c12', CANCELLED: '#98a0a8', RUNNING: '#3c8dbc', QUEUED: '#c8ced4' };
  var labels = order.filter(function (k) { return counts[k]; });
  MlUi.chart(document.getElementById('outcomeChart'), 'bar', labels,
    [{ label: 'runs', data: labels.map(function (k) { return counts[k]; }), backgroundColor: labels.map(function (k) { return palette[k]; }) }],
    { legend: { display: false } });

  setInterval(function () {
    MlCommon.get(MlCommon.envQuery(MlCommon.base + 'machine-learning/overview/pulse')).done(function (r) {
      if (!r || !r.ok) { return; }
      $('#mlActiveRuns').text((r.activeRuns || []).length);
    });
  }, 15000);
});
</script>
