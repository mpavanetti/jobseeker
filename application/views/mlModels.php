<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=1">
<div class="content-wrapper">
  <section class="content-header"><h1>ML Models <small>registry</small></h1></section>
  <section class="content">
    <div class="ml-tiles">
      <?php foreach (array('production', 'staging', 'none', 'archived') as $st): ?>
        <div class="ml-tile"><div class="ml-tile-label"><?php echo $st; ?></div><div class="ml-tile-value"><?php echo (int) (isset($stageCounts[$st]) ? $stageCounts[$st] : 0); ?></div></div>
      <?php endforeach; ?>
    </div>

    <div class="row">
      <div class="col-md-4">
        <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">New model</h3></div>
          <div class="box-body"><form id="modelForm">
            <input type="hidden" name="id" value="">
            <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
            <div class="form-group"><label>Key</label><input class="form-control" name="model_key" placeholder="auto"></div>
            <div class="ml-form-grid">
              <div class="form-group"><label>Environment</label><select class="form-control" name="environment">
                <option value="ALL">ALL</option>
                <?php foreach ($environments as $e): ?><option><?php echo html_escape($e); ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>Task</label><select class="form-control" name="task">
                <?php foreach (array('classification', 'regression', 'forecasting', 'clustering', 'ranking', 'nlp', 'vision', 'other') as $t): ?><option><?php echo $t; ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>Primary metric</label><input class="form-control" name="primary_metric" placeholder="accuracy"></div>
              <div class="form-group"><label>Goal</label><select class="form-control" name="metric_goal"><option value="max">max</option><option value="min">min</option></select></div>
            </div>
            <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
            <button type="submit" class="btn btn-primary">Create model</button>
          </form></div>
        </div>
      </div>
      <div class="col-md-8">
        <?php foreach ($models as $m): $model = $m['model']; ?>
          <div class="box box-default">
            <div class="box-header with-border">
              <h3 class="box-title"><?php echo html_escape($model->name); ?>
                <span class="ml-muted">· <?php echo html_escape($model->task); ?> · <?php echo html_escape($model->environment); ?></span></h3>
              <div class="box-tools">
                <?php if ($m['production']): ?><span class="ml-status production">production: v<?php echo (int) $m['production']->version; ?></span><?php endif; ?>
              </div>
            </div>
            <div class="box-body no-padding"><table class="table">
              <thead><tr><th>Version</th><th>Stage</th><th>Metrics</th><th>Created</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($m['versions'] as $v): ?>
                <tr>
                  <td><a href="<?php echo base_url(); ?>machine-learning/models/version/<?php echo (int) $v->id; ?>">v<?php echo (int) $v->version; ?></a></td>
                  <td><span class="ml-status <?php echo html_escape($v->stage); ?>"><?php echo html_escape($v->stage); ?></span></td>
                  <td class="ml-mono" style="font-size:11px"><?php echo html_escape($v->metrics_json); ?></td>
                  <td class="ml-muted ml-nowrap"><?php echo html_escape($v->created_at); ?></td>
                  <td>
                    <select class="input-sm js-stage" data-vid="<?php echo (int) $v->id; ?>">
                      <?php foreach (array('none', 'staging', 'production', 'archived') as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo $v->stage === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($m['versions'])): ?><tr><td colspan="5" class="ml-muted">No versions — produced by training runs.</td></tr><?php endif; ?>
              </tbody>
            </table></div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($models)): ?><div class="callout callout-info">No models yet. A training run that calls <code>ml.log_model(..., register=True)</code> creates one automatically.</div><?php endif; ?>
      </div>
    </div>
  </section>
</div>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=1"></script>
<script>
jQuery(function ($) {
  $('#modelForm').on('submit', function (e) {
    e.preventDefault();
    MlCommon.post(MlCommon.base + 'machine-learning/models/save', $(this).serialize()).done(function (r) {
      if (r.ok) { location.reload(); } else { MlCommon.toast('error', r.message); }
    });
  });
  $('.js-stage').on('change', function () {
    var vid = $(this).data('vid'), stage = this.value;
    var reason = stage === 'production' ? (prompt('Reason for promoting to production', '') || '') : '';
    MlCommon.post(MlCommon.base + 'machine-learning/models/transition', { version_id: vid, stage: stage, reason: reason }).done(function (r) {
      MlCommon.toast(r.ok ? 'success' : 'error', r.message);
      if (r.ok) { location.reload(); }
    });
  });
});
</script>
