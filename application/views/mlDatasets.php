<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=1">
<div class="content-wrapper">
  <section class="content-header"><h1>ML Datasets <small>registry &amp; explorer</small></h1></section>
  <section class="content">
    <div class="ml-tiles">
      <div class="ml-tile"><div class="ml-tile-label">Datasets</div><div class="ml-tile-value"><?php echo (int) (isset($statistics->datasets) ? $statistics->datasets : 0); ?></div></div>
      <div class="ml-tile"><div class="ml-tile-label">Active</div><div class="ml-tile-value"><?php echo (int) (isset($statistics->active) ? $statistics->active : 0); ?></div></div>
    </div>

    <div class="row">
      <div class="col-md-4">
        <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">New dataset</h3></div>
          <div class="box-body">
            <form id="datasetForm">
              <input type="hidden" name="id" value="">
              <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
              <div class="form-group"><label>Key</label><input class="form-control" name="dataset_key" placeholder="auto"></div>
              <div class="ml-form-grid">
                <div class="form-group"><label>Environment</label><select class="form-control" name="environment">
                  <option value="ALL">ALL</option>
                  <?php foreach ($environments as $e): ?><option value="<?php echo html_escape($e); ?>"><?php echo html_escape($e); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Kind</label><select class="form-control" name="kind">
                  <option value="table">table</option><option value="text">text</option><option value="image">image</option><option value="timeseries">timeseries</option></select></div>
                <div class="form-group"><label>Default source</label><select class="form-control" name="default_source_type">
                  <option value="upload">upload</option><option value="connector">connector</option><option value="repository">repository</option><option value="run_output">run_output</option></select></div>
              </div>
              <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
              <button type="submit" class="btn btn-primary">Create dataset</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Datasets</h3></div>
          <div class="box-body no-padding"><table class="table table-hover">
            <thead><tr><th>Name</th><th>Env</th><th>Versions</th><th>Latest rows</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($datasets as $d): ?>
              <tr>
                <td><a href="<?php echo base_url(); ?>machine-learning/datasets/explore/<?php echo (int) $d->id; ?>"><?php echo html_escape($d->name); ?></a>
                  <div class="ml-muted" style="font-size:11px"><?php echo html_escape($d->dataset_key); ?></div></td>
                <td class="ml-muted"><?php echo html_escape($d->environment); ?></td>
                <td><?php echo (int) $d->latest_version; ?></td>
                <td class="ml-muted" data-dsid="<?php echo (int) $d->id; ?>">—</td>
                <td><button class="btn btn-xs btn-success js-add-version" data-id="<?php echo (int) $d->id; ?>" data-key="<?php echo html_escape($d->dataset_key); ?>">+ version</button></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($datasets)): ?><tr><td colspan="5" class="ml-muted">No datasets registered.</td></tr><?php endif; ?>
            </tbody>
          </table></div>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="versionModal"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Register dataset version</h4></div>
  <div class="modal-body">
    <form id="versionForm" enctype="multipart/form-data">
      <input type="hidden" name="dataset_id" value="">
      <div class="form-group"><label>Source</label>
        <select class="form-control" name="source_type" id="srcType">
          <option value="upload">File upload</option>
          <option value="connector">Connector query</option>
          <option value="repository">Repository path</option>
        </select>
      </div>
      <div class="js-src js-src-upload">
        <input type="file" name="file">
        <label style="font-weight:normal;margin-top:6px"><input type="checkbox" name="has_header" value="1" checked> CSV has header row</label>
      </div>
      <div class="js-src js-src-connector" style="display:none">
        <select class="form-control" name="connector_id">
          <?php foreach ($connectors as $c): ?><option value="<?php echo (int) $c->id; ?>"><?php echo html_escape($c->connector_key.' ('.$c->db_type.'/'.$c->environment.')'); ?></option><?php endforeach; ?>
        </select>
        <textarea class="form-control ml-mono" name="sql" rows="4" placeholder="SELECT ... LIMIT 100000" style="margin-top:6px"></textarea>
      </div>
      <div class="js-src js-src-repository" style="display:none">
        <input class="form-control ml-mono" name="repository_path" placeholder="ml/samples/tabular-classification/data/train.csv">
      </div>
      <div class="form-group" style="margin-top:8px"><label>Notes</label><input class="form-control" name="notes"></div>
    </form>
  </div>
  <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Cancel</button>
    <button class="btn btn-primary" id="submitVersion">Register version</button></div>
</div></div></div>

<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=1"></script>
<script>
jQuery(function ($) {
  var base = MlCommon.base + 'machine-learning/datasets/';
  $('#datasetForm').on('submit', function (e) {
    e.preventDefault();
    MlCommon.post(base + 'save', $(this).serialize()).done(function (r) {
      if (r.ok) { location.reload(); } else { MlCommon.toast('error', r.message); }
    });
  });
  $('#srcType').on('change', function () {
    $('.js-src').hide();
    $('.js-src-' + this.value).show();
  });
  $('.js-add-version').on('click', function () {
    $('#versionForm')[0].reset();
    $('#versionForm [name=dataset_id]').val($(this).data('id'));
    $('#srcType').trigger('change');
    $('#versionModal').modal('show');
  });
  $('#submitVersion').on('click', function () {
    var fd = new FormData($('#versionForm')[0]);
    var $btn = $(this).prop('disabled', true).text('Working…');
    MlCommon.postForm(base + 'register-version', fd).done(function (r) {
      if (r.ok) { MlCommon.toast('success', r.message); location.reload(); }
      else { MlCommon.toast('error', r.message); $btn.prop('disabled', false).text('Register version'); }
    }).fail(function (xhr) {
      MlCommon.toast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Failed.');
      $btn.prop('disabled', false).text('Register version');
    });
  });
});
</script>
