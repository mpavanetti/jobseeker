<?php
$terminal = in_array($run->status, array('SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT'), TRUE);
$rid = 'r'.(int) $run->id;
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=2">
<div class="content-wrapper">
  <section class="content-header">
    <h1>Run <?php echo html_escape(substr($run->run_key, 0, 12)); ?>
      <small><span class="ml-badge <?php echo html_escape($run->run_type); ?>"><?php echo html_escape($run->run_type); ?></span>
      <span class="ml-status <?php echo html_escape($run->status); ?>" id="runStatus"><?php echo html_escape($run->status); ?></span>
      <?php if ($run->trigger_source === 'preview'): ?><span class="ml-muted">test run</span><?php endif; ?></small></h1>
  </section>
  <section class="content">
    <ul class="nav nav-tabs" role="tablist">
      <li class="active"><a href="#<?php echo $rid; ?>_overview" data-toggle="tab">Overview</a></li>
      <li><a href="#<?php echo $rid; ?>_metrics" data-toggle="tab">Metrics</a></li>
      <li><a href="#<?php echo $rid; ?>_console" data-toggle="tab">Console</a></li>
      <li><a href="#<?php echo $rid; ?>_lineage" data-toggle="tab">Lineage</a></li>
    </ul>
    <div class="tab-content" style="padding-top:14px">

      <div class="tab-pane active" id="<?php echo $rid; ?>_overview">
        <div class="row">
          <div class="col-md-8">
            <?php if ($inputDatasets || $outputDatasets): ?>
            <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Data</h3></div>
              <div class="box-body">
                <?php foreach (array('Inputs' => $inputDatasets, 'Outputs' => $outputDatasets) as $label => $sets): ?>
                  <?php if ($sets): ?><h5 class="ml-muted"><?php echo $label; ?></h5><?php endif; ?>
                  <?php foreach ($sets as $d): ?>
                    <div class="ml-bound-item" style="margin-bottom:8px">
                      <span class="ml-bound-role"><?php echo html_escape($d['role']); ?></span>
                      <a href="<?php echo base_url(); ?>machine-learning/datasets/explore/<?php echo (int) $d['dataset_id']; ?>"><?php echo html_escape($d['name']); ?></a>
                      <span class="ml-muted">v<?php echo (int) $d['version']; ?> ·
                        <?php echo $d['row_count'] === NULL ? '?' : number_format($d['row_count']); ?> rows · <?php echo html_escape($d['format']); ?></span>
                      <a href="#" class="js-preview" data-vid="<?php echo (int) $d['version_id']; ?>" style="float:right">preview</a>
                      <?php if (! empty($d['drift_vs_prev'])): ?>
                        <span class="ml-status <?php echo html_escape($d['drift_vs_prev']['status']); ?>" style="float:right;margin-right:10px">
                          drift vs v<?php echo (int) $d['version'] - 1; ?>: PSI <?php echo html_escape($d['drift_vs_prev']['drift_psi_max']); ?></span>
                      <?php endif; ?>
                      <div style="margin-top:5px"><?php echo count($d['schema']) ? '' : '<span class="ml-muted">no schema</span>'; ?>
                        <?php foreach (array_slice($d['schema'], 0, 12) as $c): ?><span class="ml-chip"><?php echo html_escape($c['name']); ?><small><?php echo html_escape($c['type'] ?? '?'); ?></small></span><?php endforeach; ?>
                      </div>
                      <div class="js-preview-out ml-mono" style="font-size:11px;margin-top:6px"></div>
                    </div>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

            <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Latest metrics</h3></div>
              <div class="box-body" id="latestMetrics"></div>
            </div>

            <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Parameters</h3></div>
              <div class="box-body ml-mono" style="font-size:12px">
                <?php foreach ($params as $k => $v): ?><div><?php echo html_escape($k); ?> = <?php echo html_escape(is_scalar($v) ? $v : json_encode($v)); ?></div><?php endforeach; ?>
                <?php if (empty($params)): ?><span class="ml-muted">none</span><?php endif; ?>
              </div>
            </div>

            <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Artifacts</h3></div>
              <div class="box-body">
                <?php foreach ($artifacts as $a): ?>
                  <div><span class="ml-badge"><?php echo html_escape($a->role); ?></span> <?php echo html_escape($a->path); ?>
                    <span class="ml-muted">(<?php echo number_format($a->size_bytes / 1024, 1); ?> KB)</span></div>
                <?php endforeach; ?>
                <?php if (empty($artifacts)): ?><span class="ml-muted">none</span><?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="box box-solid"><div class="box-body">
              <p><strong>Job:</strong> <?php echo $job ? '<a href="'.base_url().'machine-learning/jobs?id='.(int) $job->id.'">'.html_escape($job->name).'</a>' : '—'; ?></p>
              <p><strong>Experiment:</strong> <?php echo $experiment ? html_escape($experiment->name) : '—'; ?></p>
              <p><strong>Environment:</strong> <?php echo html_escape($run->environment); ?></p>
              <p><strong>Image:</strong> <span class="ml-mono" style="font-size:11px"><?php echo html_escape($run->image_ref); ?></span></p>
              <p><strong>Triggered by:</strong> <?php echo html_escape($run->triggered_by); ?> (<?php echo html_escape($run->trigger_source); ?>)</p>
              <p><strong>Limits:</strong> <?php echo html_escape($run->cpu_limit); ?> vCPU / <?php echo (int) $run->memory_limit_mb; ?> MB</p>
              <p id="resStats" class="ml-muted"></p>
              <?php if (! $terminal): ?><button class="btn btn-xs btn-danger" id="cancelRun">Cancel</button><?php endif; ?>
              <?php if ($run->status === 'SUCCEEDED' && ! $modelVersions): ?>
                <button class="btn btn-xs btn-success" id="registerModel">Register model from run</button>
              <?php endif; ?>
            </div></div>

            <?php if ($modelVersions): ?>
            <div class="box box-success"><div class="box-header with-border"><h3 class="box-title">Produced models</h3></div>
              <div class="box-body">
                <?php foreach ($modelVersions as $mv): ?>
                  <div><a href="<?php echo base_url(); ?>machine-learning/models/version/<?php echo (int) $mv['id']; ?>"><?php echo html_escape($mv['name']); ?> v<?php echo (int) $mv['version']; ?></a>
                    <span class="ml-status <?php echo html_escape($mv['stage']); ?>"><?php echo html_escape($mv['stage']); ?></span></div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

            <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Live hints</h3></div>
              <div class="box-body" id="runHints"><span class="ml-muted">—</span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="tab-pane" id="<?php echo $rid; ?>_metrics">
        <div class="box box-primary"><div class="box-body">
          <div id="metricCharts" class="ml-grid-charts"></div>
        </div></div>
      </div>

      <div class="tab-pane" id="<?php echo $rid; ?>_console">
        <div id="runConsole"></div>
      </div>

      <div class="tab-pane" id="<?php echo $rid; ?>_lineage">
        <div class="box box-default"><div class="box-body">
          <div class="ml-lineage">
            <?php foreach ($lineage['in'] as $e): ?><span class="ml-node <?php echo html_escape($e->src_kind); ?>"><?php echo html_escape($e->src_kind); ?> #<?php echo (int) $e->src_id; ?></span><span class="ml-arrow">→</span><?php endforeach; ?>
            <span class="ml-node run"><strong>this run</strong></span>
            <?php foreach ($lineage['out'] as $e): ?><span class="ml-arrow">→</span><span class="ml-node <?php echo html_escape($e->dst_kind); ?>"><?php echo html_escape($e->dst_kind); ?> #<?php echo (int) $e->dst_id; ?> <small>(<?php echo html_escape($e->role); ?>)</small></span><?php endforeach; ?>
          </div>
        </div></div>
      </div>

    </div>
  </section>
</div>
<script id="metricSeries" type="application/json"><?php echo json_encode($metricSeries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=2"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-ui.js?v=2"></script>
<script>
jQuery(function ($) {
  var runId = <?php echo (int) $run->id; ?>;
  var terminal = <?php echo $terminal ? 'true' : 'false'; ?>;
  var charts = {};
  var console = MlUi.console('#runConsole');
  console.setStatus('<?php echo html_escape($run->status); ?>');
  console.setLog(<?php echo json_encode($logs); ?>);

  function renderMetrics(series) {
    var keys = Object.keys(series || {});
    if (!keys.length) { $('#metricCharts').html('<p class="ml-muted">No metrics logged.</p>'); return; }
    keys.forEach(function (key) {
      var pts = series[key];
      if (!charts[key]) {
        var box = $('<div class="box box-default" style="margin:0"><div class="box-header with-border"><h3 class="box-title">' +
          MlCommon.escape(key) + '</h3></div><div class="box-body"><div class="ml-canvas sm"><canvas></canvas></div></div></div>');
        $('#metricCharts').append(box);
        charts[key] = MlUi.chart(box.find('canvas')[0], 'line',
          pts.map(function (p) { return p.step; }),
          [{ label: key, data: pts.map(function (p) { return p.value; }), borderColor: MlUi.palette[0], fill: false }]);
      } else {
        charts[key].data.labels = pts.map(function (p) { return p.step; });
        charts[key].data.datasets[0].data = pts.map(function (p) { return p.value; });
        charts[key].update();
      }
    });
  }
  function renderLatest(m) {
    $('#latestMetrics').html(Object.keys(m || {}).map(function (k) {
      return '<span class="ml-badge" style="margin-right:6px">' + MlCommon.escape(k) + ' ' + MlCommon.fmt(m[k]) + '</span>';
    }).join('') || '<span class="ml-muted">none</span>');
  }
  try { renderMetrics(JSON.parse(document.getElementById('metricSeries').textContent)); } catch (e) {}

  function refresh() {
    MlCommon.get(MlCommon.base + 'machine-learning/runs/status/' + runId).done(function (r) {
      if (!r || !r.ok) { return; }
      $('#runStatus').attr('class', 'ml-status ' + r.status).text(r.status);
      console.setStatus(r.status);
      console.setLog(r.logs || '');
      console.setMetrics(r.latestMetrics || {});
      renderMetrics(r.metricSeries); renderLatest(r.latestMetrics);
      if (r.stats && r.stats.container) {
        $('#resStats').text('CPU ' + MlCommon.fmt(r.stats.container.cpu_pct, 1) + '% · MEM ' + MlCommon.fmt(r.stats.container.memory_mb, 0) + ' MB');
      }
      $('#runHints').html((r.hints || []).map(function (h) {
        return '<div class="ml-status ' + (h.level === 'warning' ? 'warning' : 'ok') + '">' + MlCommon.escape(h.text) + '</div>';
      }).join('') || '<span class="ml-muted">—</span>');
      if (r.terminal) { clearInterval(timer); }
    });
  }
  var timer = null;
  if (!terminal) { timer = setInterval(refresh, 2500); refresh(); }
  else { renderLatest(<?php echo json_encode($latestMetrics); ?>); }

  $('.js-preview').on('click', function (e) {
    e.preventDefault();
    var $out = $(this).closest('.ml-bound-item').find('.js-preview-out').text('loading…');
    MlCommon.get(MlCommon.base + 'machine-learning/datasets/preview/' + $(this).data('vid') + '?limit=5').done(function (r) {
      if (!r || !r.ok) { $out.text((r && r.message) || 'no preview'); return; }
      $out.html('<table class="table table-condensed table-bordered"><thead><tr>' +
        r.columns.map(function (c) { return '<th>' + MlCommon.escape(c) + '</th>'; }).join('') + '</tr></thead><tbody>' +
        r.rows.map(function (row) { return '<tr>' + row.map(function (v) { return '<td>' + MlCommon.escape(v) + '</td>'; }).join('') + '</tr>'; }).join('') +
        '</tbody></table>');
    });
  });
  $('#cancelRun').on('click', function () {
    MlCommon.post(MlCommon.base + 'machine-learning/jobs/cancel', { id: runId }).done(function () { location.reload(); });
  });
  $('#registerModel').on('click', function () {
    var key = prompt('Model key', '');
    if (key == null) { return; }
    MlCommon.post(MlCommon.base + 'machine-learning/runs/register-model', { run_id: runId, model_key: key }).done(function (r) {
      MlCommon.toast(r.ok ? 'success' : 'error', r.message); if (r.ok) { location.reload(); }
    });
  });
});
</script>
