<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=1">
<div class="content-wrapper">
  <section class="content-header">
    <h1><?php echo html_escape($model->name); ?> <small>v<?php echo (int) $version->version; ?>
      <span class="ml-status <?php echo html_escape($version->stage); ?>"><?php echo html_escape($version->stage); ?></span></small></h1>
  </section>
  <section class="content">
    <div class="row">
      <div class="col-md-8">
        <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Metrics</h3></div>
          <div class="box-body">
            <table class="table table-condensed">
              <?php foreach ($metrics as $k => $v): ?><tr><td><?php echo html_escape($k); ?></td><td class="ml-mono"><?php echo html_escape(is_scalar($v) ? $v : json_encode($v)); ?></td></tr><?php endforeach; ?>
              <?php if (empty($metrics)): ?><tr><td class="ml-muted">No metrics snapshot.</td></tr><?php endif; ?>
            </table>
          </div>
        </div>
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Signature</h3></div>
          <div class="box-body ml-mono" style="font-size:12px"><pre style="white-space:pre-wrap;border:0;background:transparent"><?php echo html_escape(json_encode($signature, JSON_PRETTY_PRINT)); ?></pre></div>
        </div>
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Parameters</h3></div>
          <div class="box-body ml-mono" style="font-size:12px">
            <?php foreach ($params as $k => $v): ?><div><?php echo html_escape($k); ?> = <?php echo html_escape(is_scalar($v) ? $v : json_encode($v)); ?></div><?php endforeach; ?>
            <?php if (empty($params)): ?><span class="ml-muted">none</span><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="box box-solid"><div class="box-body">
          <p><strong>Framework:</strong> <?php echo html_escape($version->framework ?: '—'); ?></p>
          <p><strong>Source run:</strong> <?php echo $run ? '<a href="'.base_url().'machine-learning/runs/detail/'.(int) $run->id.'">'.html_escape(substr($run->run_key, 0, 8)).'</a>' : '—'; ?></p>
          <p><strong>Training data:</strong> <?php echo $trainingDatasetVersion ? html_escape('dataset #'.$trainingDatasetVersion->dataset_id.' v'.$trainingDatasetVersion->version) : '—'; ?></p>
          <p><strong>Created:</strong> <?php echo html_escape($version->created_at); ?> by <?php echo html_escape($version->created_by); ?></p>
          <form id="stageForm">
            <label>Stage</label>
            <select class="form-control" name="stage">
              <?php foreach (array('none', 'staging', 'production', 'archived') as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $version->stage === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
              <?php endforeach; ?>
            </select>
            <input class="form-control" name="reason" placeholder="reason" style="margin-top:6px">
            <button class="btn btn-primary btn-sm" style="margin-top:6px">Apply</button>
          </form>
        </div></div>
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Stage history</h3></div>
          <div class="box-body">
            <?php foreach ($stageHistory as $ev): ?>
              <div style="font-size:12px"><span class="ml-muted"><?php echo html_escape($ev->created_at); ?></span>
                — <?php echo html_escape($ev->from_stage); ?> → <strong><?php echo html_escape($ev->to_stage); ?></strong>
                <span class="ml-muted"><?php echo html_escape($ev->actor); ?></span>
                <?php if ($ev->reason): ?><div class="ml-muted"><?php echo html_escape($ev->reason); ?></div><?php endif; ?></div>
            <?php endforeach; ?>
            <?php if (empty($stageHistory)): ?><span class="ml-muted">No transitions.</span><?php endif; ?>
          </div>
        </div>
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Notes</h3></div>
          <div class="box-body">
            <textarea id="notes" class="form-control" rows="3"><?php echo html_escape($version->notes); ?></textarea>
            <button class="btn btn-default btn-sm" id="saveNotes" style="margin-top:6px">Save notes</button>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=1"></script>
<script>
jQuery(function ($) {
  var vid = <?php echo (int) $version->id; ?>;
  $('#stageForm').on('submit', function (e) {
    e.preventDefault();
    MlCommon.post(MlCommon.base + 'machine-learning/models/transition',
      { version_id: vid, stage: $('[name=stage]').val(), reason: $('[name=reason]').val() }).done(function (r) {
      MlCommon.toast(r.ok ? 'success' : 'error', r.message);
      if (r.ok) { location.reload(); }
    });
  });
  $('#saveNotes').on('click', function () {
    MlCommon.post(MlCommon.base + 'machine-learning/models/notes', { version_id: vid, notes: $('#notes').val() })
      .done(function (r) { MlCommon.toast(r.ok ? 'success' : 'error', r.message); });
  });
});
</script>
