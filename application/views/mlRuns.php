<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=1">
<div class="content-wrapper">
  <section class="content-header"><h1>Experiments &amp; Runs</h1></section>
  <section class="content">

    <div class="box box-solid">
      <div class="box-body">
        <form method="get" class="form-inline">
          <select name="experiment_id" class="form-control input-sm">
            <option value="">All experiments</option>
            <?php foreach ($experiments as $e): ?>
              <option value="<?php echo (int) $e->id; ?>" <?php echo isset($filters['experiment_id']) && $filters['experiment_id'] == $e->id ? 'selected' : ''; ?>><?php echo html_escape($e->name); ?></option>
            <?php endforeach; ?>
          </select>
          <select name="run_type" class="form-control input-sm">
            <option value="">Any type</option>
            <?php foreach (array('train', 'batch_infer', 'evaluate', 'preprocess', 'tune') as $t): ?>
              <option value="<?php echo $t; ?>" <?php echo isset($filters['run_type']) && $filters['run_type'] === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
            <?php endforeach; ?>
          </select>
          <select name="status" class="form-control input-sm">
            <option value="">Any status</option>
            <?php foreach (array('RUNNING', 'SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT', 'QUEUED') as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo isset($filters['status']) && $filters['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-default">Filter</button>
          <?php if (! empty($filters['experiment_id'])): ?>
            <button type="button" class="btn btn-sm btn-primary" id="compareBtn" data-exp="<?php echo (int) $filters['experiment_id']; ?>">Compare runs</button>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div id="compareGrid" style="display:none" class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Experiment comparison</h3></div><div class="box-body table-responsive"></div></div>

    <div class="box box-default">
      <div class="box-body no-padding table-responsive">
        <table class="table table-hover">
          <thead><tr><th>Run</th><th>Type</th><th>Status</th><th>Started</th><th>Duration</th><th>Metrics</th></tr></thead>
          <tbody>
          <?php foreach ($runs as $run): ?>
            <?php
            $dur = ($run->started_at && $run->completed_at) ? (strtotime($run->completed_at) - strtotime($run->started_at)) : null;
            ?>
            <tr>
              <td><a href="<?php echo base_url(); ?>machine-learning/runs/detail/<?php echo (int) $run->id; ?>"><?php echo html_escape($run->name ?: substr($run->run_key, 0, 8)); ?></a></td>
              <td><span class="ml-badge <?php echo html_escape($run->run_type); ?>"><?php echo html_escape($run->run_type); ?></span></td>
              <td><span class="ml-status <?php echo html_escape($run->status); ?>"><?php echo html_escape($run->status); ?></span></td>
              <td class="ml-muted ml-nowrap"><?php echo html_escape($run->started_at ?: $run->queued_at); ?></td>
              <td class="ml-muted"><?php echo $dur === null ? '—' : ($dur < 60 ? $dur.'s' : round($dur / 60, 1).'m'); ?></td>
              <td class="ml-mono" style="font-size:11px"><?php echo html_escape($run->metrics_summary_json); ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($runs)): ?><tr><td colspan="6" class="ml-muted">No runs match.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=1"></script>
<script>
jQuery(function ($) {
  $('#compareBtn').on('click', function () {
    var exp = $(this).data('exp');
    var $grid = $('#compareGrid').show().find('.box-body').html('<p class="ml-muted">Loading…</p>');
    MlCommon.get(MlCommon.base + 'machine-learning/runs/compare/' + exp).done(function (r) {
      if (!r || !r.ok) { $grid.html('<p class="ml-muted">No data.</p>'); return; }
      var cols = r.metric_keys || [];
      var html = '<table class="table table-bordered table-condensed"><thead><tr><th>Run</th><th>Status</th>';
      cols.forEach(function (c) { html += '<th>' + MlCommon.escape(c) + '</th>'; });
      html += '</tr></thead><tbody>';
      (r.runs || []).forEach(function (run) {
        html += '<tr><td><a href="' + MlCommon.base + 'machine-learning/runs/detail/' + run.id + '">' + MlCommon.escape(run.name || run.run_key.slice(0, 8)) + '</a></td>';
        html += '<td><span class="ml-status ' + run.status + '">' + run.status + '</span></td>';
        cols.forEach(function (c) { html += '<td class="ml-mono">' + (run.metrics && run.metrics[c] != null ? MlCommon.fmt(run.metrics[c]) : '—') + '</td>'; });
        html += '</tr>';
      });
      $grid.html(html + '</tbody></table>');
    });
  });
});
</script>
