<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=2">
<div class="content-wrapper">
  <section class="content-header"><h1>ML Monitoring <small>drift &amp; serving health</small></h1></section>
  <section class="content">
    <div class="ml-tiles">
      <div class="ml-tile <?php echo (int) $openAlertCount > 0 ? 'ml-bad' : 'ml-good'; ?>"><div class="ml-tile-label">Open alerts</div><div class="ml-tile-value"><?php echo (int) $openAlertCount; ?></div></div>
      <div class="ml-tile"><div class="ml-tile-label">Monitors</div><div class="ml-tile-value"><?php echo count($monitors); ?></div></div>
    </div>

    <div class="row">
      <div class="col-md-7">
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Monitor status</h3></div>
          <div class="box-body"><div class="ml-status-grid" id="statusGrid"><span class="ml-muted">Loading…</span></div></div>
        </div>
      </div>
      <div class="col-md-5">
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Worst-drifted features</h3></div>
          <div class="box-body no-padding"><table class="table" id="worstFeatures"><tbody><tr><td class="ml-muted">Loading…</td></tr></tbody></table></div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-4">
        <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">New monitor</h3></div>
          <div class="box-body"><form id="monitorForm">
            <input type="hidden" name="id" value="">
            <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
            <div class="ml-form-grid">
              <div class="form-group"><label>Environment</label><select class="form-control" name="environment"><option value="ALL">ALL</option>
                <?php foreach ($environments as $e): ?><option><?php echo html_escape($e); ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>Model</label><select class="form-control" name="model_id">
                <?php foreach ($models as $m): ?><option value="<?php echo (int) $m->id; ?>"><?php echo html_escape($m->name); ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>Track stage</label><select class="form-control" name="track_stage"><option>production</option><option>staging</option></select></div>
              <div class="form-group"><label>Baseline dataset</label><select class="form-control" name="baseline_dataset_id">
                <option value="">—</option>
                <?php foreach ($datasets as $d): ?><option value="<?php echo (int) $d->id; ?>"><?php echo html_escape($d->name); ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>Comparison dataset</label><select class="form-control" name="comparison_dataset_id">
                <option value="">same as baseline</option>
                <?php foreach ($datasets as $d): ?><option value="<?php echo (int) $d->id; ?>"><?php echo html_escape($d->name); ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>PSI warn</label><input type="number" step="0.01" class="form-control" name="psi_warning" value="0.1"></div>
              <div class="form-group"><label>PSI critical</label><input type="number" step="0.01" class="form-control" name="psi_critical" value="0.25"></div>
              <div class="form-group"><label>Accuracy floor</label><input type="number" step="0.01" class="form-control" name="accuracy_floor"></div>
              <div class="form-group"><label>Min predictions</label><input type="number" class="form-control" name="min_prediction_volume"></div>
              <div class="form-group"><label>Schedule (cron)</label><input class="form-control" name="schedule_cron" placeholder="H */6 * * *"></div>
              <div class="form-group"><label>Notify email</label><input class="form-control" name="notify_email"></div>
            </div>
            <button type="submit" class="btn btn-primary">Create monitor</button>
          </form></div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Monitors</h3></div>
          <div class="box-body no-padding"><table class="table table-hover">
            <thead><tr><th>Name</th><th>Model</th><th>Status</th><th>Last run</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($monitors as $mo): ?>
              <tr>
                <td><a href="<?php echo base_url(); ?>machine-learning/monitoring/detail/<?php echo (int) $mo->id; ?>"><?php echo html_escape($mo->name); ?></a></td>
                <td class="ml-muted"><?php echo html_escape($mo->model_name); ?></td>
                <td><span class="ml-status <?php echo html_escape($mo->status); ?>"><?php echo html_escape($mo->status); ?></span></td>
                <td class="ml-muted ml-nowrap"><?php echo html_escape($mo->last_run_at ?: '—'); ?></td>
                <td><button class="btn btn-xs btn-success js-run" data-id="<?php echo (int) $mo->id; ?>">Evaluate</button></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($monitors)): ?><tr><td colspan="5" class="ml-muted">No monitors.</td></tr><?php endif; ?>
            </tbody>
          </table></div>
        </div>

        <div class="box box-danger"><div class="box-header with-border"><h3 class="box-title">Alerts</h3></div>
          <div class="box-body no-padding"><table class="table">
            <thead><tr><th>Sev</th><th>Title</th><th>Observed</th><th>State</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($alerts as $a): ?>
              <tr>
                <td><span class="ml-status <?php echo html_escape($a->severity); ?>"><?php echo html_escape(strtoupper($a->severity)); ?></span></td>
                <td><?php echo html_escape($a->title); ?><div class="ml-muted" style="font-size:11px"><?php echo html_escape($a->detail); ?></div></td>
                <td class="ml-mono"><?php echo html_escape($a->observed_value); ?> / <?php echo html_escape($a->threshold_value); ?></td>
                <td><span class="ml-status <?php echo $a->state === 'open' ? 'FAILED' : 'SUCCEEDED'; ?>"><?php echo html_escape($a->state); ?></span></td>
                <td><?php if ($a->state === 'open'): ?><button class="btn btn-xs btn-default js-ack" data-id="<?php echo (int) $a->id; ?>">Ack</button><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($alerts)): ?><tr><td colspan="5" class="ml-muted">No alerts.</td></tr><?php endif; ?>
            </tbody>
          </table></div>
        </div>
      </div>
    </div>
  </section>
</div>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=2"></script>
<script>
jQuery(function ($) {
  var base = MlCommon.base + 'machine-learning/monitoring/';

  MlCommon.get(base + 'overview').done(function (r) {
    if (!r || !r.ok) { return; }
    $('#statusGrid').html((r.grid || []).map(function (m) {
      return '<a class="ml-status-cell ' + m.status + '" href="' + MlCommon.base + 'machine-learning/monitoring/detail/' + m.id + '">' +
        '<h5>' + MlCommon.escape(m.name) + '</h5><div class="ml-muted">' + MlCommon.escape(m.model || '') + '</div>' +
        '<div class="ml-muted">' + (m.last_run_at ? MlCommon.ago(m.last_run_at) : 'never run') + '</div></a>';
    }).join('') || '<span class="ml-muted">No monitors.</span>');
    $('#worstFeatures tbody').html((r.worst || []).map(function (w) {
      return '<tr><td>' + MlCommon.escape(w.feature) + '<div class="ml-muted" style="font-size:11px">' + MlCommon.escape(w.monitor) + '</div></td>' +
        '<td class="ml-mono ml-nowrap"><span class="ml-status ' + (w.psi >= 0.25 ? 'critical' : (w.psi >= 0.1 ? 'warning' : 'ok')) + '">PSI ' + MlCommon.fmt(w.psi) + '</span></td></tr>';
    }).join('') || '<tr><td class="ml-muted">No drift recorded.</td></tr>');
  });
  $('#monitorForm').on('submit', function (e) {
    e.preventDefault();
    MlCommon.post(base + 'save', $(this).serialize()).done(function (r) {
      if (r.ok) { location.reload(); } else { MlCommon.toast('error', r.message); }
    });
  });
  $('.js-run').on('click', function () {
    var $b = $(this).prop('disabled', true).text('Evaluating…');
    MlCommon.post(base + 'run', { id: $b.data('id') }).done(function (r) {
      MlCommon.toast(r.ok ? 'success' : 'error', r.message);
      if (r.ok) { location.reload(); } else { $b.prop('disabled', false).text('Evaluate'); }
    });
  });
  $('.js-ack').on('click', function () {
    MlCommon.post(base + 'alert/ack', { id: $(this).data('id') }).done(function () { location.reload(); });
  });
});
</script>
