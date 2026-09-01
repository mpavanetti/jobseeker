<?php
$env = isset($selectedEnvironment) ? $selectedEnvironment : 'ALL';
$envQuery = rawurlencode($env);
$runtimeByKey = array();
foreach ($runtimes as $runtime) {
    $runtimeByKey[$runtime->runtime_key] = $runtime;
}
?>
<link href="<?php echo base_url(); ?>assets/dist/css/compute.css?v=1" rel="stylesheet" type="text/css">
<div class="content-wrapper compute-page">
  <section class="content-header">
    <div class="compute-toolbar">
      <h1>Spark Clusters <small>ephemeral job-cluster specifications</small></h1>
      <div class="compute-toolbar-actions">
        <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>data-engineering/spark-jobs?environment=<?php echo $envQuery; ?>"><i class="fa fa-tasks"></i> Spark Jobs</a>
        <button class="btn btn-primary btn-sm" type="button" id="clusterNew"<?php echo $env === 'ALL' ? ' disabled' : ''; ?>><i class="fa fa-plus"></i> New cluster</button>
      </div>
    </div>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-home"></i> Home</a></li>
      <li>Data Engineering</li>
      <li class="active">Spark Clusters</li>
    </ol>
  </section>

  <section class="content">
    <?php if ($env === 'ALL') { ?>
      <div class="compute-alert is-warn"><i class="fa fa-filter"></i> Pick a global environment (top bar) to create or edit clusters.</div>
    <?php } ?>

    <div class="compute-grid compute-grid-split">
      <div class="compute-card">
        <header><h2>Clusters <span class="compute-chip"><?php echo count($clusters); ?></span></h2></header>
        <div class="compute-card-body is-flush">
          <table class="compute-table">
            <thead><tr><th>Name</th><th>Runtime</th><th>Workers</th><th>Resources</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($clusters)) { ?>
              <tr><td colspan="5" class="compute-empty">No clusters yet in <strong><?php echo html_escape($env); ?></strong>.</td></tr>
            <?php } foreach ($clusters as $cluster) {
                $rt = isset($runtimeByKey[$cluster->runtime_key]) ? $runtimeByKey[$cluster->runtime_key] : NULL;
            ?>
              <tr data-cluster='<?php echo html_escape(json_encode($cluster, JSON_UNESCAPED_SLASHES)); ?>'>
                <td>
                  <span class="compute-name"><?php echo html_escape($cluster->name); ?></span>
                  <span class="compute-sub"><?php echo html_escape($cluster->group_name); ?> &middot; <?php echo html_escape($cluster->cluster_key); ?><?php echo $cluster->is_active ? '' : ' &middot; inactive'; ?></span>
                </td>
                <td><span class="compute-chip"><?php echo html_escape($rt ? $rt->display_name : $cluster->runtime_key); ?></span></td>
                <td><?php echo (int) $cluster->min_workers; ?><?php echo $cluster->autoscale ? '&ndash;'.(int) $cluster->max_workers.' (auto)' : ''; ?></td>
                <td>
                  <span class="compute-sub" style="margin:0">driver <?php echo (int) $cluster->driver_cores; ?> vCPU / <?php echo (int) $cluster->driver_memory_mb; ?> MB</span>
                  <span class="compute-sub" style="margin:0">worker <?php echo (int) $cluster->worker_cores; ?> vCPU / <?php echo (int) $cluster->worker_memory_mb; ?> MB</span>
                </td>
                <td class="compute-row-actions">
                  <button class="btn btn-xs btn-default cluster-edit" type="button"><i class="fa fa-pencil"></i></button>
                  <button class="btn btn-xs btn-danger cluster-delete" type="button"><i class="fa fa-trash"></i></button>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="compute-card">
        <header><h2>Runtime catalogue</h2></header>
        <div class="compute-card-body is-flush">
          <table class="compute-table">
            <thead><tr><th>Runtime</th><th>Image</th></tr></thead>
            <tbody>
            <?php foreach ($runtimes as $runtime) { ?>
              <tr>
                <td>
                  <span class="compute-name"><?php echo html_escape($runtime->display_name); ?></span>
                  <span class="compute-sub"><?php echo html_escape($runtime->description); ?></span>
                </td>
                <td><code><?php echo html_escape($runtime->image_repository.':'.$runtime->image_tag); ?></code></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
          <div class="compute-card-body" style="border-top:1px solid #eef1f5">
            <p class="compute-sub" style="margin:0">Build the images once with <code>scripts/build-compute-runtimes.sh</code> (or <code>docker compose --profile runtimes up --build</code>).</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<div id="clusterModalRoot" hidden></div>

<script>
(function () {
  var BASE = <?php echo json_encode(base_url()); ?>;
  var ENV = <?php echo json_encode($env); ?>;
  var RUNTIMES = <?php echo json_encode(array_map(function ($r) {
      return array('key' => $r->runtime_key, 'name' => $r->display_name);
  }, $runtimes)); ?>;

  var CSRF = window.jobseekerCsrf || { name: '', hash: '' };
  function el(html) { var t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
  }); }
  function postForm(path, body) {
    if (CSRF.name) { body.set(CSRF.name, CSRF.hash); }
    return fetch(BASE + path, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }

  function runtimeOptions(selected) {
    return RUNTIMES.map(function (r) {
      return '<option value="' + esc(r.key) + '"' + (r.key === selected ? ' selected' : '') + '>' + esc(r.name) + '</option>';
    }).join('');
  }

  function openModal(cluster) {
    var c = cluster || {};
    var isEdit = !!c.id;
    var root = document.getElementById('clusterModalRoot');
    root.hidden = false;
    root.innerHTML = '';
    var backdrop = el('<div class="compute-modal-backdrop"></div>');
    var modal = el(
      '<div class="compute-modal" role="dialog" aria-modal="true">' +
        '<header><h3>' + (isEdit ? 'Edit cluster' : 'New Spark cluster') + '</h3>' +
        '<button type="button" class="btn btn-xs btn-default" data-close>&times;</button></header>' +
        '<form class="compute-modal-body"><div class="compute-form-grid">' +
          '<div><label>Name</label><input class="form-control input-sm" name="name" required value="' + esc(c.name || '') + '"></div>' +
          '<div><label>Group</label><input class="form-control input-sm" name="group_name" value="' + esc(c.group_name || 'General') + '"></div>' +
          '<div class="full"><label>Description</label><input class="form-control input-sm" name="description" maxlength="2000" value="' + esc(c.description || '') + '"></div>' +
          '<div><label>Runtime</label><select class="form-control input-sm" name="runtime_key">' + runtimeOptions(c.runtime_key) + '</select></div>' +
          '<div><label>Idle timeout (min)</label><input class="form-control input-sm" type="number" min="1" max="240" name="idle_timeout_minutes" value="' + (c.idle_timeout_minutes || 10) + '"></div>' +
          '<div><label>Driver vCPU</label><input class="form-control input-sm" type="number" min="1" max="16" name="driver_cores" value="' + (c.driver_cores || 1) + '"></div>' +
          '<div><label>Driver memory (MB)</label><input class="form-control input-sm" type="number" min="512" step="256" name="driver_memory_mb" value="' + (c.driver_memory_mb || 1024) + '"></div>' +
          '<div><label>Worker vCPU</label><input class="form-control input-sm" type="number" min="1" max="32" name="worker_cores" value="' + (c.worker_cores || 1) + '"></div>' +
          '<div><label>Worker memory (MB)</label><input class="form-control input-sm" type="number" min="512" step="256" name="worker_memory_mb" value="' + (c.worker_memory_mb || 1024) + '"></div>' +
          '<div><label>Min workers</label><input class="form-control input-sm" type="number" min="1" max="64" name="min_workers" value="' + (c.min_workers || 1) + '"></div>' +
          '<div><label>Max workers</label><input class="form-control input-sm" type="number" min="1" max="64" name="max_workers" value="' + (c.max_workers || 2) + '"></div>' +
          '<div><label><input type="checkbox" name="autoscale" ' + (c.autoscale == 1 ? 'checked' : '') + '> Autoscale workers</label></div>' +
          '<div><label><input type="checkbox" name="is_active_cb" ' + (c.is_active == 0 ? '' : 'checked') + '> Active</label></div>' +
          '<div class="full"><label>Spark conf (JSON object)</label><textarea class="form-control" name="spark_conf_json">' + esc(c.spark_conf_json || '{}') + '</textarea></div>' +
          '<div class="full"><label>Environment variables (JSON object)</label><textarea class="form-control" name="env_json">' + esc(c.env_json || '{}') + '</textarea></div>' +
        '</div><p class="compute-help" style="color:#c0392b" data-error hidden></p>' +
        '<footer style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">' +
          '<button type="button" class="btn btn-default btn-sm" data-close>Cancel</button>' +
          '<button type="submit" class="btn btn-primary btn-sm">' + (isEdit ? 'Save changes' : 'Create cluster') + '</button>' +
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
      body.set('id', c.id || 0);
      body.set('environment', ENV);
      body.set('cluster_key', c.cluster_key || '');
      ['name', 'group_name', 'description', 'runtime_key', 'idle_timeout_minutes', 'driver_cores', 'driver_memory_mb',
       'worker_cores', 'worker_memory_mb', 'min_workers', 'max_workers', 'spark_conf_json', 'env_json'].forEach(function (field) {
        body.set(field, form.elements[field].value);
      });
      body.set('autoscale', form.elements.autoscale.checked ? 1 : 0);
      body.set('is_active', form.elements.is_active_cb.checked ? 1 : 0);
      var errBox = form.querySelector('[data-error]');
      errBox.hidden = true;
      postForm('data-engineering/spark-clusters/save', body)
        .then(function (payload) {
          if (payload.ok) { window.location.reload(); return; }
          errBox.textContent = payload.message || 'Save failed.';
          errBox.hidden = false;
        })
        .catch(function () { errBox.textContent = 'Network error.'; errBox.hidden = false; });
    });
    root.appendChild(backdrop);
    root.appendChild(modal);
  }

  var newBtn = document.getElementById('clusterNew');
  if (newBtn) { newBtn.addEventListener('click', function () { openModal(null); }); }

  document.querySelectorAll('tr[data-cluster]').forEach(function (row) {
    var cluster = JSON.parse(row.getAttribute('data-cluster'));
    row.querySelector('.cluster-edit').addEventListener('click', function () { openModal(cluster); });
    row.querySelector('.cluster-delete').addEventListener('click', function () {
      if (!window.confirm('Delete cluster "' + cluster.name + '"?')) { return; }
      var body = new URLSearchParams(); body.set('id', cluster.id);
      postForm('data-engineering/spark-clusters/delete', body)
        .then(function (payload) { if (payload.ok) { window.location.reload(); } else { window.alert(payload.message || 'Delete failed.'); } });
    });
  });
})();
</script>
