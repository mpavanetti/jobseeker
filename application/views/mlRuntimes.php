<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=1">
<div class="content-wrapper">
  <section class="content-header"><h1>ML Runtimes <small>container images jobs run on</small></h1></section>
  <section class="content">
    <div class="callout callout-info" style="font-size:13px">
      Images are built by <code>scripts/build-ml-runtimes.sh</code> on the compute engine. Rows here only
      catalogue them for the job authoring dropdown.
    </div>
    <div class="row">
      <div class="col-md-7">
        <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Runtimes</h3></div>
          <div class="box-body no-padding">
            <table class="table table-hover">
              <thead><tr><th>Runtime</th><th>Image</th><th>Libraries</th><th>Default</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($runtimes as $r): ?>
                <tr data-runtime='<?php echo html_escape(json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>
                  <td><strong><?php echo html_escape($r->display_name); ?></strong><div class="ml-muted" style="font-size:11px"><?php echo html_escape($r->runtime_key); ?> · <?php echo html_escape($r->kind); ?></div></td>
                  <td class="ml-mono" style="font-size:11px"><?php echo html_escape($r->image_repository.':'.$r->image_tag); ?></td>
                  <td class="ml-muted" style="font-size:11px"><?php echo html_escape($r->library_summary); ?></td>
                  <td><?php echo $r->is_default ? '<i class="fa fa-check text-green"></i>' : ''; ?><?php echo $r->is_active ? '' : ' <span class="ml-muted">(inactive)</span>'; ?></td>
                  <td><button class="btn btn-xs btn-default js-edit">Edit</button></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-5">
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Runtime</h3></div>
          <div class="box-body">
            <form id="runtimeForm">
              <input type="hidden" name="id" value="">
              <div class="form-group"><label>Display name</label><input class="form-control" name="display_name" required></div>
              <div class="form-group"><label>Runtime key</label><input class="form-control" name="runtime_key" placeholder="auto"></div>
              <div class="ml-form-grid">
                <div class="form-group"><label>Image repository</label><input class="form-control" name="image_repository" value="jobseeker/ml-runtime" required></div>
                <div class="form-group"><label>Image tag</label><input class="form-control" name="image_tag" required></div>
                <div class="form-group"><label>Kind</label><select class="form-control" name="kind"><option value="cpu">cpu</option><option value="gpu">gpu</option></select></div>
                <div class="form-group"><label>Base image</label><input class="form-control" name="base_image" value="continuumio/miniconda3"></div>
                <div class="form-group"><label>Default vCPU</label><input type="number" step="0.25" class="form-control" name="default_cpu_limit" value="1"></div>
                <div class="form-group"><label>Default MB</label><input type="number" step="128" class="form-control" name="default_memory_mb" value="2048"></div>
              </div>
              <div class="form-group"><label>Library summary</label><input class="form-control" name="library_summary"></div>
              <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
              <label style="font-weight:normal"><input type="checkbox" name="is_default" value="1"> Default runtime</label>
              <label style="font-weight:normal;margin-left:16px"><input type="checkbox" name="is_active" value="1" checked> Active</label>
              <div style="margin-top:12px">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-default js-reset">New</button>
                <button type="button" class="btn btn-link text-red js-delete" style="display:none">Delete</button>
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
  var base = MlCommon.base + 'machine-learning/runtimes/';
  var $form = $('#runtimeForm');
  function reset() { $form[0].reset(); $form.find('[name=id]').val(''); $('.js-delete').hide(); }
  $('.js-reset').on('click', reset);
  $('.js-edit').on('click', function () {
    var r = $(this).closest('tr').data('runtime');
    Object.keys(r).forEach(function (k) {
      var $f = $form.find('[name=' + k + ']');
      if (!$f.length) { return; }
      if ($f.attr('type') === 'checkbox') { $f.prop('checked', !!Number(r[k])); } else { $f.val(r[k]); }
    });
    $('.js-delete').show();
  });
  $form.on('submit', function (e) {
    e.preventDefault();
    MlCommon.post(base + 'save', $form.serialize()).done(function (res) {
      if (res.ok) { MlCommon.toast('success', res.message); location.reload(); }
      else { MlCommon.toast('error', res.message); }
    });
  });
  $('.js-delete').on('click', function () {
    if (!confirm('Delete this runtime?')) { return; }
    MlCommon.post(base + 'delete', { id: $form.find('[name=id]').val() }).done(function (res) {
      if (res.ok) { location.reload(); } else { MlCommon.toast('error', res.message); }
    });
  });
});
</script>
