<link href="<?php echo base_url(); ?>assets/dist/css/compute.css?v=2" rel="stylesheet" type="text/css">
<div class="content-wrapper compute-page">
  <section class="content-header">
    <h1>ML Runtimes <small>Miniconda-based runtime images</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-home"></i> Home</a></li>
      <li>Data Engineering</li>
      <li class="active">ML Runtimes</li>
    </ol>
  </section>

  <section class="content">
    <div class="compute-toolbar">
      <span class="compute-toolbar-env">Runtime catalogue</span>
      <div class="compute-toolbar-actions">
        <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>data-engineering/ml-jobs"><i class="fa fa-flask"></i> ML Jobs</a>
        <button class="btn btn-primary btn-sm" type="button" id="runtimeNew"><i class="fa fa-plus"></i> New runtime</button>
      </div>
    </div>
    <div class="compute-alert is-info"><i class="fa fa-info-circle"></i> Build images with <code>scripts/build-compute-runtimes.sh</code> (or <code>docker compose --profile runtimes up --build</code>). Conda-only stacks: keep <strong>Conda based</strong> on.</div>
    <div class="compute-card">
      <header><h2>Runtimes <span class="compute-chip"><?php echo count($runtimes); ?></span></h2></header>
      <div class="compute-card-body is-flush">
        <table class="compute-table">
          <thead><tr><th>Runtime</th><th>Image</th><th>Libraries</th><th>State</th><th></th></tr></thead>
          <tbody>
          <?php if (empty($runtimes)) { ?>
            <tr><td colspan="5" class="compute-empty">No runtimes defined.</td></tr>
          <?php } foreach ($runtimes as $runtime) { ?>
            <tr data-runtime='<?php echo html_escape(json_encode($runtime, JSON_UNESCAPED_SLASHES)); ?>'>
              <td>
                <span class="compute-name"><?php echo html_escape($runtime->display_name); ?></span>
                <span class="compute-sub"><?php echo html_escape($runtime->runtime_key); ?><?php echo $runtime->conda_based ? ' &middot; conda' : ''; ?><?php echo $runtime->is_default ? ' &middot; default' : ''; ?></span>
              </td>
              <td><code><?php echo html_escape($runtime->image_repository.':'.$runtime->image_tag); ?></code></td>
              <td><span class="compute-sub" style="margin:0"><?php echo html_escape($runtime->library_summary); ?></span></td>
              <td><span class="compute-chip <?php echo $runtime->is_active ? '' : 'is-muted'; ?>"><?php echo $runtime->is_active ? 'active' : 'inactive'; ?></span></td>
              <td class="compute-row-actions">
                <button class="btn btn-xs btn-default runtime-edit" type="button"><i class="fa fa-pencil"></i></button>
                <button class="btn btn-xs btn-danger runtime-delete" type="button"><i class="fa fa-trash"></i></button>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>

<div id="runtimeModalRoot" hidden></div>

<script>
(function () {
  var BASE = <?php echo json_encode(base_url()); ?>;
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
  }); }
  var CSRF = window.jobseekerCsrf || { name: '', hash: '' };
  function el(html) { var t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }
  function postForm(path, body) {
    if (CSRF.name) { body.set(CSRF.name, CSRF.hash); }
    return fetch(BASE + path, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }

  function openModal(runtime) {
    var r = runtime || {};
    var isEdit = !!r.id;
    var root = document.getElementById('runtimeModalRoot');
    root.hidden = false;
    root.innerHTML = '';
    var backdrop = el('<div class="compute-modal-backdrop"></div>');
    var modal = el(
      '<div class="compute-modal" role="dialog" aria-modal="true">' +
        '<header><h3>' + (isEdit ? 'Edit runtime' : 'New ML runtime') + '</h3>' +
        '<button type="button" class="btn btn-xs btn-default" data-close>&times;</button></header>' +
        '<form class="compute-modal-body"><div class="compute-form-grid">' +
          '<div><label>Display name</label><input class="form-control input-sm" name="display_name" required value="' + esc(r.display_name || '') + '"></div>' +
          '<div><label>Sort order</label><input class="form-control input-sm" type="number" min="1" max="999" name="sort_order" value="' + (r.sort_order || 100) + '"></div>' +
          '<div><label>Image repository</label><input class="form-control input-sm" name="image_repository" required value="' + esc(r.image_repository || 'jobseeker/ml-runtime') + '"></div>' +
          '<div><label>Image tag</label><input class="form-control input-sm" name="image_tag" required value="' + esc(r.image_tag || '') + '"></div>' +
          '<div class="full"><label>Base image</label><input class="form-control input-sm" name="base_image" value="' + esc(r.base_image || 'continuumio/miniconda3') + '"></div>' +
          '<div class="full"><label>Library summary</label><input class="form-control input-sm" name="library_summary" value="' + esc(r.library_summary || '') + '"></div>' +
          '<div class="full"><label>Description</label><input class="form-control input-sm" name="description" value="' + esc(r.description || '') + '"></div>' +
          '<div><label><input type="checkbox" name="conda_based_cb" ' + (r.conda_based == 0 ? '' : 'checked') + '> Conda based</label></div>' +
          '<div><label><input type="checkbox" name="is_default_cb" ' + (r.is_default == 1 ? 'checked' : '') + '> Default</label></div>' +
          '<div><label><input type="checkbox" name="is_active_cb" ' + (r.is_active == 0 ? '' : 'checked') + '> Active</label></div>' +
        '</div><p style="color:#c0392b" data-error hidden></p>' +
        '<footer style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">' +
          '<button type="button" class="btn btn-default btn-sm" data-close>Cancel</button>' +
          '<button type="submit" class="btn btn-primary btn-sm">' + (isEdit ? 'Save changes' : 'Create runtime') + '</button>' +
        '</footer></form>' +
      '</div>'
    );
    function close() { root.hidden = true; root.innerHTML = ''; }
    backdrop.addEventListener('click', close);
    modal.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', close); });
    modal.querySelector('form').addEventListener('submit', function (event) {
      event.preventDefault();
      var form = event.target;
      var body = new URLSearchParams();
      body.set('id', r.id || 0);
      body.set('runtime_key', r.runtime_key || '');
      ['display_name', 'sort_order', 'image_repository', 'image_tag', 'base_image', 'library_summary', 'description'].forEach(function (field) {
        body.set(field, form.elements[field].value);
      });
      body.set('conda_based', form.elements.conda_based_cb.checked ? 1 : 0);
      body.set('is_default', form.elements.is_default_cb.checked ? 1 : 0);
      body.set('is_active', form.elements.is_active_cb.checked ? 1 : 0);
      var errBox = form.querySelector('[data-error]');
      errBox.hidden = true;
      postForm('data-engineering/ml-runtimes/save', body)
        .then(function (payload) {
          if (payload.ok) { window.location.reload(); return; }
          errBox.textContent = payload.message || 'Save failed.';
          errBox.hidden = false;
        });
    });
    root.appendChild(backdrop);
    root.appendChild(modal);
  }

  document.getElementById('runtimeNew').addEventListener('click', function () { openModal(null); });
  document.querySelectorAll('tr[data-runtime]').forEach(function (row) {
    var runtime = JSON.parse(row.getAttribute('data-runtime'));
    row.querySelector('.runtime-edit').addEventListener('click', function () { openModal(runtime); });
    row.querySelector('.runtime-delete').addEventListener('click', function () {
      if (!window.confirm('Delete runtime "' + runtime.display_name + '"?')) { return; }
      var body = new URLSearchParams(); body.set('id', runtime.id);
      postForm('data-engineering/ml-runtimes/delete', body)
        .then(function (payload) { if (payload.ok) { window.location.reload(); } else { window.alert(payload.message || 'Delete failed.'); } });
    });
  });
})();
</script>
