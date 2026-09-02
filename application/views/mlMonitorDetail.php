<?php
$overallSeries = isset($series['drift_psi']['__overall__']) ? $series['drift_psi']['__overall__'] : array();
$lastOverall = $overallSeries ? end($overallSeries) : null;
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=2">
<div class="content-wrapper">
  <section class="content-header">
    <h1><?php echo html_escape($monitor->name); ?> <small>monitor · <span class="ml-status <?php echo html_escape($monitor->status); ?>"><?php echo html_escape($monitor->status); ?></span></small></h1>
  </section>
  <section class="content">
    <div class="box box-solid"><div class="box-body">
      <strong>Model:</strong> <?php echo $model ? html_escape($model->name) : '—'; ?> ·
      <strong>Tracks:</strong> <?php echo html_escape($monitor->track_stage); ?> ·
      <strong>Baseline:</strong> <?php echo $baseline ? html_escape('dataset #'.$baseline->dataset_id.' v'.$baseline->version) : '—'; ?> ·
      <strong>Current:</strong> <?php echo $current ? html_escape('v'.$current->version) : '—'; ?> ·
      <strong>Schedule:</strong> <?php echo html_escape($monitor->schedule_cron ?: 'manual'); ?>
      <button class="btn btn-xs btn-success" id="evalNow" style="margin-left:12px">Evaluate now</button>
    </div></div>

    <div class="ml-tiles">
      <div class="ml-tile <?php echo $lastOverall && $lastOverall['value'] >= 0.25 ? 'ml-bad' : ($lastOverall && $lastOverall['value'] >= 0.1 ? 'ml-warn' : 'ml-good'); ?>">
        <div class="ml-tile-label">Overall drift (PSI)</div>
        <div class="ml-tile-value"><?php echo $lastOverall ? number_format($lastOverall['value'], 3) : '—'; ?></div>
      </div>
      <div class="ml-tile"><div class="ml-tile-label">Evaluations</div><div class="ml-tile-value"><?php echo count($runsHistory); ?></div></div>
      <div class="ml-tile"><div class="ml-tile-label">Open alerts</div><div class="ml-tile-value"><?php echo count(array_filter($alerts, function ($a) { return $a->state === 'open'; })); ?></div></div>
    </div>

    <div class="row">
      <div class="col-md-8">
        <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Overall drift over time</h3></div>
          <div class="box-body"><div class="ml-canvas"><canvas id="overallChart"></canvas></div></div>
        </div>
        <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Per-feature drift (PSI over time)</h3></div>
          <div class="box-body"><div id="featureCharts" class="ml-grid-charts"></div></div>
        </div>
        <div class="box box-warning"><div class="box-header with-border"><h3 class="box-title">Baseline vs current distribution</h3></div>
          <div class="box-body"><div id="overlayCharts" class="ml-grid-charts"></div></div>
        </div>
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Serving signals</h3></div>
          <div class="box-body"><div id="servingCharts" class="ml-grid-charts"></div></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="box box-danger"><div class="box-header with-border"><h3 class="box-title">Alert timeline</h3></div>
          <div class="box-body">
            <div class="ml-timeline">
              <?php foreach ($alerts as $a): ?>
                <div class="ml-timeline-item <?php echo $a->severity === 'critical' ? 'critical' : ($a->state !== 'open' ? 'resolved' : ''); ?>">
                  <span class="ml-muted ml-nowrap"><?php echo html_escape($a->created_at); ?></span>
                  <span class="ml-status <?php echo html_escape($a->severity); ?>"><?php echo html_escape(strtoupper($a->severity)); ?></span>
                  <?php echo html_escape($a->title); ?>
                  <div class="ml-muted" style="font-size:11px"><?php echo html_escape($a->detail); ?></div>
                  <?php if ($a->state === 'open'): ?><button class="btn btn-xs btn-default js-ack" data-id="<?php echo (int) $a->id; ?>">Ack</button>
                  <?php else: ?><span class="ml-muted"><?php echo html_escape($a->state); ?></span><?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php if (empty($alerts)): ?><div class="ml-timeline-item">No alerts.</div><?php endif; ?>
            </div>
          </div>
        </div>
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Evaluation history</h3></div>
          <div class="box-body no-padding"><table class="table">
            <thead><tr><th>When</th><th>Status</th><th>Max PSI</th><th>Alerts</th></tr></thead>
            <tbody>
            <?php foreach ($runsHistory as $r): ?>
              <tr><td class="ml-muted ml-nowrap"><?php echo html_escape($r->started_at); ?></td>
                <td><span class="ml-status <?php echo html_escape($r->status); ?>"><?php echo html_escape($r->status); ?></span></td>
                <td class="ml-mono"><?php echo html_escape(round((float) $r->drift_score, 4)); ?></td>
                <td><?php echo (int) $r->alerts_opened; ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($runsHistory)): ?><tr><td colspan="4" class="ml-muted">Not evaluated yet.</td></tr><?php endif; ?>
            </tbody>
          </table></div>
        </div>
      </div>
    </div>
  </section>
</div>
<script id="monSeries" type="application/json"><?php echo json_encode($series, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script id="monBaseline" type="application/json"><?php echo json_encode($baselineFingerprint, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script id="monCurrent" type="application/json"><?php echo json_encode($currentFingerprint, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=2"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-ui.js?v=2"></script>
<script>
jQuery(function ($) {
  var monitorId = <?php echo (int) $monitor->id; ?>;
  var series = j('monSeries'), baseFp = j('monBaseline'), curFp = j('monCurrent');
  function j(id) { try { return JSON.parse(document.getElementById(id).textContent); } catch (e) { return {}; } }
  function pts(arr) { return (arr || []).map(function (p) { return { t: p.ts, v: p.value }; }); }
  function lineChart(canvas, labels, data, color) {
    return MlUi.chart(canvas, 'line', labels, [{ label: '', data: data, borderColor: color || '#3c8dbc', fill: false }], { legend: { display: false } });
  }

  // overall
  var ov = (series.drift_psi && series.drift_psi.__overall__) || [];
  if (ov.length) { lineChart(document.getElementById('overallChart'), ov.map(function (p) { return p.ts.slice(5, 16); }), ov.map(function (p) { return p.value; }), '#dd4b39'); }

  // per-feature small multiples (drift_psi per feature)
  var $fc = $('#featureCharts');
  Object.keys((series.drift_psi) || {}).forEach(function (feature) {
    if (feature === '__overall__') { return; }
    var p = series.drift_psi[feature];
    var box = $('<div class="box box-default" style="margin:0"><div class="box-header with-border"><h3 class="box-title">' +
      MlCommon.escape(feature) + '</h3></div><div class="box-body"><div class="ml-canvas sm"><canvas></canvas></div></div></div>');
    $fc.append(box);
    lineChart(box.find('canvas')[0], p.map(function (x) { return x.ts.slice(5, 16); }), p.map(function (x) { return x.value; }));
  });
  if (!$fc.children().length) { $fc.html('<p class="ml-muted">Run an evaluation to populate feature drift.</p>'); }

  // baseline vs current overlay
  var $ov = $('#overlayCharts');
  Object.keys((baseFp.columns) || {}).forEach(function (f) {
    var b = baseFp.columns[f], c = (curFp.columns || {})[f];
    if (!b || !c) { return; }
    var box = $('<div class="box box-default" style="margin:0"><div class="box-header with-border"><h3 class="box-title">' +
      MlCommon.escape(f) + '</h3></div><div class="box-body"><div class="js-ovl"></div></div></div>');
    $ov.append(box);
    if (b.type === 'numeric' && b.histogram) {
      MlUi.overlayBars(box.find('.js-ovl'), b.histogram.edges || [], b.histogram.counts || [], (c.histogram && c.histogram.counts) || []);
    } else {
      var keys = Object.keys(b.top || {});
      MlUi.overlayBars(box.find('.js-ovl'), keys, keys.map(function (k) { return (b.top || {})[k] || 0; }), keys.map(function (k) { return (c.top || {})[k] || 0; }));
    }
  });
  if (!$ov.children().length) { $ov.html('<p class="ml-muted">No comparable fingerprints yet.</p>'); }

  // serving signals
  var $sv = $('#servingCharts');
  ['prediction_volume', 'output_mean', 'accuracy'].forEach(function (k) {
    var f = (series[k] && series[k].__overall__) || [];
    if (!f.length) { return; }
    var box = $('<div class="box box-default" style="margin:0"><div class="box-header with-border"><h3 class="box-title">' + k + '</h3></div><div class="box-body"><div class="ml-canvas sm"><canvas></canvas></div></div></div>');
    $sv.append(box);
    lineChart(box.find('canvas')[0], f.map(function (x) { return x.ts.slice(5, 16); }), f.map(function (x) { return x.value; }), '#00a65a');
  });
  if (!$sv.children().length) { $sv.html('<p class="ml-muted">No serving signals (needs batch-inference runs of the tracked model).</p>'); }

  $('#evalNow').on('click', function () {
    var $b = $(this).prop('disabled', true).text('Evaluating…');
    MlCommon.post(MlCommon.base + 'machine-learning/monitoring/run', { id: monitorId }).done(function (r) {
      MlCommon.toast(r.ok ? 'success' : 'error', r.message);
      if (r.ok) { location.reload(); } else { $b.prop('disabled', false).text('Evaluate now'); }
    });
  });
  $('.js-ack').on('click', function () {
    MlCommon.post(MlCommon.base + 'machine-learning/monitoring/alert/ack', { id: $(this).data('id') }).done(function () { location.reload(); });
  });
});
</script>
