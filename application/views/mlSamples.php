<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=1">
<div class="content-wrapper">
  <section class="content-header"><h1>ML Samples <small>customisable job templates</small></h1></section>
  <section class="content">
    <div class="row">
      <div class="col-md-5">
        <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Gallery</h3>
          <div class="box-tools"><button class="btn btn-xs btn-primary js-new">New sample</button></div></div>
          <div class="box-body no-padding"><table class="table table-hover">
            <thead><tr><th>Name</th><th>Category</th><th>Type</th><th>Built-in</th></tr></thead>
            <tbody>
            <?php foreach ($samples as $s): ?>
              <tr data-sample='<?php echo html_escape(json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG)); ?>' style="cursor:pointer">
                <td><?php echo html_escape($s->name); ?><div class="ml-muted" style="font-size:11px"><?php echo html_escape($s->sample_key); ?></div></td>
                <td><?php echo html_escape($s->category); ?></td>
                <td><span class="ml-badge <?php echo html_escape($s->run_type); ?>"><?php echo html_escape($s->run_type); ?></span></td>
                <td><?php echo $s->is_builtin ? '<i class="fa fa-lock text-muted"></i>' : ''; ?><?php echo $s->is_active ? '' : ' <span class="ml-muted">(off)</span>'; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      </div>
      <div class="col-md-7">
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Sample</h3></div>
          <div class="box-body">
            <form id="sampleForm">
              <input type="hidden" name="id" value="">
              <div class="ml-form-grid">
                <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
                <div class="form-group"><label>Sample key</label><input class="form-control" name="sample_key" placeholder="auto"></div>
                <div class="form-group"><label>Category</label><select class="form-control" name="category">
                  <?php foreach ($categories as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Run type</label><select class="form-control" name="run_type">
                  <?php foreach ($runTypes as $t): ?><option value="<?php echo $t; ?>"><?php echo $t; ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Runtime</label><select class="form-control" name="runtime_key">
                  <?php foreach ($runtimes as $r): ?><option value="<?php echo html_escape($r->runtime_key); ?>"><?php echo html_escape($r->display_name); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Tags</label><input class="form-control" name="tags"></div>
              </div>
              <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
              <div class="form-group"><label>Code (main.py)</label><textarea class="form-control ml-code-editor" name="code" spellcheck="false"></textarea></div>
              <div class="ml-form-grid">
                <div class="form-group"><label>Params schema (JSON)</label><textarea class="form-control ml-mono" name="params_schema_json" rows="3"></textarea></div>
                <div class="form-group"><label>Dataset roles (JSON array)</label><textarea class="form-control ml-mono" name="dataset_roles_json" rows="3"></textarea></div>
              </div>
              <label style="font-weight:normal"><input type="checkbox" name="is_active" value="1" checked> Active</label>
              <div style="margin-top:12px">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-default js-new">New</button>
                <button type="button" class="btn btn-link text-red js-delete" style="display:none">Delete</button>
                <span class="ml-muted js-builtin-note" style="margin-left:8px"></span>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=1"></script>
<script>
jQuery(function ($) {
  var base = MlCommon.base + 'machine-learning/samples/';
  var $form = $('#sampleForm');
  function reset() { $form[0].reset(); $form.find('[name=id]').val(''); $('.js-delete').hide(); $('.js-builtin-note').text(''); }
  $('.js-new').on('click', reset);
  $('tr[data-sample]').on('click', function () {
    var s = $(this).data('sample');
    Object.keys(s).forEach(function (k) {
      var $f = $form.find('[name=' + k + ']');
      if (!$f.length) { return; }
      if ($f.attr('type') === 'checkbox') { $f.prop('checked', !!Number(s[k])); } else { $f.val(s[k]); }
    });
    $('.js-delete').toggle(!Number(s.is_builtin));
    $('.js-builtin-note').text(Number(s.is_builtin) ? 'Built-in sample — edits stay until the next repo sync.' : '');
  });
  $form.on('submit', function (e) {
    e.preventDefault();
    MlCommon.post(base + 'save', $form.serialize()).done(function (res) {
      if (res.ok) { MlCommon.toast('success', res.message); location.reload(); }
      else { MlCommon.toast('error', res.message); }
    });
  });
  $('.js-delete').on('click', function () {
    if (!confirm('Delete this sample?')) { return; }
    MlCommon.post(base + 'delete', { id: $form.find('[name=id]').val() }).done(function (res) {
      if (res.ok) { location.reload(); } else { MlCommon.toast('error', res.message); }
    });
  });
});
</script>
