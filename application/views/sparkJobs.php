<?php
$env = isset($selectedEnvironment) ? $selectedEnvironment : 'ALL';
$envQuery = rawurlencode($env);
$selectedJob = isset($job) ? $job : NULL;
$clusterById = array();
foreach ($clusters as $cluster) {
    $clusterById[$cluster->id] = $cluster;
}
?>
<link href="<?php echo base_url(); ?>assets/dist/css/compute.css?v=1" rel="stylesheet" type="text/css">
<div class="content-wrapper compute-page">
  <section class="content-header">
    <div class="compute-toolbar">
      <h1>Spark Jobs <small>PySpark on ephemeral job clusters</small></h1>
      <div class="compute-toolbar-actions">
        <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>data-engineering/spark-clusters?environment=<?php echo $envQuery; ?>"><i class="fa fa-server"></i> Clusters</a>
        <select class="form-control input-sm" id="sampleSelect" style="width:190px"<?php echo ($env === 'ALL' || empty($clusters)) ? ' disabled' : ''; ?>>
          <option value="">Create from sample&hellip;</option>
          <?php foreach ($samples as $sample) { ?>
            <option value="<?php echo html_escape($sample['key']); ?>"><?php echo html_escape($sample['name']); ?></option>
          <?php } ?>
        </select>
        <button class="btn btn-primary btn-sm" type="button" id="jobNew"<?php echo ($env === 'ALL' || empty($clusters)) ? ' disabled' : ''; ?>><i class="fa fa-plus"></i> New job</button>
      </div>
    </div>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-home"></i> Home</a></li>
      <li>Data Engineering</li>
      <li class="active">Spark Jobs</li>
    </ol>
  </section>

  <section class="content">
    <?php if ($env === 'ALL') { ?>
      <div class="compute-alert is-warn"><i class="fa fa-filter"></i> Pick a global environment (top bar) to manage Spark jobs.</div>
    <?php } elseif (empty($clusters)) { ?>
      <div class="compute-alert is-warn"><i class="fa fa-server"></i> Create a <a href="<?php echo base_url(); ?>data-engineering/spark-clusters?environment=<?php echo $envQuery; ?>">Spark cluster</a> for <strong><?php echo html_escape($env); ?></strong> first.</div>
    <?php } ?>
    <?php if (! $engineHealthy) { ?>
      <div class="compute-alert is-warn"><i class="fa fa-plug"></i> The compute engine (<code><?php echo html_escape($driverName); ?></code>) is not reachable &mdash; runs will fail until it is back.</div>
    <?php } ?>

    <div class="compute-grid compute-grid-split">
      <div class="compute-card">
        <header><h2>Jobs <span class="compute-chip"><?php echo count($jobs); ?></span></h2></header>
        <div class="compute-card-body is-flush">
          <table class="compute-table">
            <thead><tr><th>Name</th><th>Cluster</th><th>Source</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($jobs)) { ?>
              <tr><td colspan="4" class="compute-empty">No Spark jobs in <strong><?php echo html_escape($env); ?></strong> yet.</td></tr>
            <?php } foreach ($jobs as $row) { ?>
              <tr<?php echo ($selectedJob && $selectedJob->id == $row->id) ? ' style="background:#f1f6ff"' : ''; ?>>
                <td>
                  <a class="compute-name" href="<?php echo base_url(); ?>data-engineering/spark-jobs?environment=<?php echo $envQuery; ?>&id=<?php echo (int) $row->id; ?>"><?php echo html_escape($row->name); ?></a>
                  <span class="compute-sub"><?php echo html_escape($row->group_name); ?><?php echo $row->is_active ? '' : ' &middot; inactive'; ?></span>
                </td>
                <td><span class="compute-chip"><?php echo html_escape(isset($row->cluster_name) ? $row->cluster_name : ('#'.$row->cluster_id)); ?></span></td>
                <td><span class="compute-sub" style="margin:0"><?php echo html_escape($row->source_type === 'inline' ? 'inline' : $row->entry_point); ?></span></td>
                <td class="compute-row-actions">
                  <button class="btn btn-xs btn-success job-run" type="button" data-id="<?php echo (int) $row->id; ?>"<?php echo (! $engineHealthy || ! $row->is_active) ? ' disabled' : ''; ?>><i class="fa fa-play"></i> Run</button>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="compute-card">
        <header>
          <h2><?php echo $selectedJob ? html_escape($selectedJob->name) : 'Run activity'; ?></h2>
          <?php if ($selectedJob) { ?><span class="compute-chip"><?php echo html_escape($selectedJob->environment); ?></span><?php } ?>
        </header>
        <div class="compute-card-body">
          <?php if ($selectedJob) {
              $selectedCluster = isset($clusterById[$selectedJob->cluster_id]) ? $clusterById[$selectedJob->cluster_id] : NULL;
          ?>
            <dl class="compute-kv">
              <dt>Cluster</dt><dd><?php echo html_escape($selectedCluster ? $selectedCluster->name : ('#'.$selectedJob->cluster_id)); ?></dd>
              <dt>Runtime</dt><dd><?php echo html_escape($selectedCluster ? $selectedCluster->runtime_key : '&mdash;'); ?></dd>
              <dt>Source</dt><dd><?php echo html_escape($selectedJob->source_type === 'inline' ? 'inline code' : $selectedJob->entry_point); ?></dd>
              <dt>Args</dt><dd><?php echo html_escape($selectedJob->application_args ?: '&mdash;'); ?></dd>
            </dl>
            <div style="margin:12px 0">
              <button class="btn btn-success btn-sm job-run" type="button" data-id="<?php echo (int) $selectedJob->id; ?>"<?php echo $engineHealthy ? '' : ' disabled'; ?>><i class="fa fa-play"></i> Run job</button>
              <button class="btn btn-default btn-sm" type="button" id="jobEdit"><i class="fa fa-pencil"></i> Edit</button>
              <button class="btn btn-danger btn-sm" type="button" id="jobDelete"><i class="fa fa-trash"></i></button>
            </div>
            <div id="runDrawer" hidden></div>
            <table class="compute-table" style="margin-top:10px">
              <thead><tr><th>Run</th><th>Status</th><th>Workers</th><th>Started</th></tr></thead>
              <tbody id="recentRuns">
              <?php if (empty($recentRuns)) { ?>
                <tr><td colspan="4" class="compute-empty">No runs yet.</td></tr>
              <?php } foreach ($recentRuns as $run) { ?>
                <tr data-run-id="<?php echo (int) $run->id; ?>">
                  <td><code><?php echo html_escape($run->run_key); ?></code></td>
                  <td><span class="compute-status is-<?php echo strtolower($run->status); ?>"><?php echo html_escape($run->status); ?></span></td>
                  <td><?php echo (int) $run->worker_count; ?></td>
                  <td><span class="compute-sub" style="margin:0"><?php echo html_escape($run->started_at); ?></span></td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
          <?php } else { ?>
            <p class="compute-empty">Select a job to see its run history, or start one with <strong>Run</strong>.</p>
          <?php } ?>
        </div>
      </div>
    </div>
  </section>
</div>

<div id="jobModalRoot" hidden></div>

<script>
(function () {
  var BASE = <?php echo json_encode(base_url()); ?>;
  var ENV = <?php echo json_encode($env); ?>;
  var CLUSTERS = <?php echo json_encode(array_map(function ($c) {
      return array('id' => (int) $c->id, 'name' => $c->name, 'runtime_key' => $c->runtime_key);
  }, $clusters)); ?>;
  var SAMPLES = <?php echo json_encode($samples); ?>;
  var SELECTED_JOB = <?php echo json_encode($selectedJob ? array(
      'id' => (int) $selectedJob->id, 'name' => $selectedJob->name, 'job_key' => $selectedJob->job_key,
      'group_name' => $selectedJob->group_name, 'description' => $selectedJob->description,
      'cluster_id' => (int) $selectedJob->cluster_id, 'source_type' => $selectedJob->source_type,
      'entry_point' => $selectedJob->entry_point, 'application_args' => $selectedJob->application_args,
      'inline_code' => $selectedJob->inline_code, 'spark_submit_conf_json' => $selectedJob->spark_submit_conf_json,
      'is_active' => (int) $selectedJob->is_active,
  ) : null); ?>;
  var TERMINAL = ['SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT'];

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
  }); }
  function el(html) { var t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }
  function clusterOptions(selected) {
    return CLUSTERS.map(function (c) {
      return '<option value="' + c.id + '"' + (c.id === selected ? ' selected' : '') + '>' + esc(c.name) + '</option>';
    }).join('');
  }
  var CSRF = window.jobseekerCsrf || { name: '', hash: '' };
  function post(path, params) {
    var body = new URLSearchParams();
    Object.keys(params).forEach(function (k) { body.set(k, params[k]); });
    if (CSRF.name) { body.set(CSRF.name, CSRF.hash); }
    return fetch(BASE + path, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }

  // --- run drawer + polling ------------------------------------------
  var poller = null;
  function stopPolling() { if (poller) { window.clearInterval(poller); poller = null; } }

  function renderDrawer(run) {
    var drawer = document.getElementById('runDrawer');
    if (!drawer) { return; }
    drawer.hidden = false;
    var canCancel = TERMINAL.indexOf(run.status) === -1;
    drawer.innerHTML =
      '<div class="compute-run-drawer">' +
        '<header>' +
          '<div><span class="compute-status is-' + esc(run.status.toLowerCase()) + '">' + esc(run.status) + '</span> ' +
          '<span class="compute-run-meta">run ' + esc(run.run_key) + (run.exit_code != null ? ' &middot; exit ' + run.exit_code : '') + '</span></div>' +
          (canCancel ? '<button type="button" class="btn btn-xs btn-danger" id="runCancel">Cancel</button>' : '') +
        '</header>' +
        (run.error_message ? '<div style="padding:8px 14px;color:#fca5a5;font-size:12px">' + esc(run.error_message) + '</div>' : '') +
        '<pre class="compute-run-log" id="runLog">waiting for logs&hellip;</pre>' +
      '</div>';
    var cancelBtn = document.getElementById('runCancel');
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        cancelBtn.disabled = true;
        post('data-engineering/spark-jobs/cancel', { id: run.id }).then(function (payload) {
          if (payload.run) { renderDrawer(payload.run); }
        });
      });
    }
  }

  function refreshLog(runId) {
    fetch(BASE + 'data-engineering/spark-jobs/logs/' + runId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (payload) {
        var log = document.getElementById('runLog');
        if (log && payload.ok) { log.textContent = payload.logs || '(no output yet)'; log.scrollTop = log.scrollHeight; }
      });
  }

  function track(run) {
    stopPolling();
    renderDrawer(run);
    refreshLog(run.id);
    if (TERMINAL.indexOf(run.status) !== -1) { return; }
    poller = window.setInterval(function () {
      fetch(BASE + 'data-engineering/spark-jobs/status/' + run.id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
          if (!payload.ok) { return; }
          renderDrawer(payload.run);
          refreshLog(run.id);
          if (payload.run.terminal) { stopPolling(); }
        });
    }, 2500);
  }

  document.querySelectorAll('.job-run').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      post('data-engineering/spark-jobs/run', { id: btn.getAttribute('data-id') }).then(function (payload) {
        btn.disabled = false;
        if (!payload.ok || !payload.run) { window.alert(payload.message || 'Run failed to start.'); return; }
        if (String(payload.run.id) && document.getElementById('runDrawer')) {
          track(payload.run);
        } else {
          window.location = BASE + 'data-engineering/spark-jobs?environment=' + encodeURIComponent(ENV) + '&id=' + btn.getAttribute('data-id');
        }
      });
    });
  });

  // --- job create / edit modal ------------------------------------
  function openModal(job) {
    var j = job || {};
    var isEdit = !!j.id;
    var root = document.getElementById('jobModalRoot');
    root.hidden = false;
    root.innerHTML = '';
    var backdrop = el('<div class="compute-modal-backdrop"></div>');
    var sourceType = j.source_type || 'repository';
    var modal = el(
      '<div class="compute-modal" role="dialog" aria-modal="true">' +
        '<header><h3>' + (isEdit ? 'Edit Spark job' : 'New Spark job') + '</h3>' +
        '<button type="button" class="btn btn-xs btn-default" data-close>&times;</button></header>' +
        '<form class="compute-modal-body"><div class="compute-form-grid">' +
          '<div><label>Name</label><input class="form-control input-sm" name="name" required value="' + esc(j.name || '') + '"></div>' +
          '<div><label>Group</label><input class="form-control input-sm" name="group_name" value="' + esc(j.group_name || 'General') + '"></div>' +
          '<div class="full"><label>Description</label><input class="form-control input-sm" name="description" maxlength="2000" value="' + esc(j.description || '') + '"></div>' +
          '<div><label>Cluster</label><select class="form-control input-sm" name="cluster_id">' + clusterOptions(j.cluster_id) + '</select></div>' +
          '<div><label>Source</label><select class="form-control input-sm" name="source_type">' +
            '<option value="repository"' + (sourceType === 'repository' ? ' selected' : '') + '>Repository file</option>' +
            '<option value="inline"' + (sourceType === 'inline' ? ' selected' : '') + '>Inline code</option></select></div>' +
          '<div class="full" data-when="repository"><label>Entry point</label><input class="form-control input-sm" name="entry_point" placeholder="jobs/pi/main.py" value="' + esc(j.entry_point || '') + '"><div class="help">Path under repository/spark, e.g. <code>jobs/pi/main.py</code>.</div></div>' +
          '<div class="full" data-when="inline"><label>Inline PySpark</label><textarea class="form-control code" name="inline_code">' + esc(j.inline_code || 'from pyspark.sql import SparkSession\n\nspark = SparkSession.builder.appName("job").getOrCreate()\nprint(spark.range(5).count())\nspark.stop()\n') + '</textarea></div>' +
          '<div class="full"><label>Application args</label><input class="form-control input-sm" name="application_args" value="' + esc(j.application_args || '') + '"></div>' +
          '<div class="full"><label>spark-submit conf (JSON object, optional)</label><textarea class="form-control" name="spark_submit_conf_json">' + esc(j.spark_submit_conf_json || '') + '</textarea></div>' +
          '<div><label><input type="checkbox" name="is_active_cb" ' + (j.is_active == 0 ? '' : 'checked') + '> Active</label></div>' +
        '</div><p style="color:#c0392b" data-error hidden></p>' +
        '<footer style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">' +
          '<button type="button" class="btn btn-default btn-sm" data-close>Cancel</button>' +
          '<button type="submit" class="btn btn-primary btn-sm">' + (isEdit ? 'Save changes' : 'Create job') + '</button>' +
        '</footer></form>' +
      '</div>'
    );
    function close() { root.hidden = true; root.innerHTML = ''; }
    function syncSource() {
      var mode = modal.querySelector('[name="source_type"]').value;
      modal.querySelectorAll('[data-when]').forEach(function (node) { node.hidden = node.getAttribute('data-when') !== mode; });
    }
    backdrop.addEventListener('click', close);
    modal.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', close); });
    modal.querySelector('[name="source_type"]').addEventListener('change', syncSource);
    modal.querySelector('form').addEventListener('submit', function (event) {
      event.preventDefault();
      var form = event.target;
      var errBox = form.querySelector('[data-error]');
      errBox.hidden = true;
      post('data-engineering/spark-jobs/save', {
        id: j.id || 0, environment: ENV, job_key: j.job_key || '',
        name: form.elements.name.value, group_name: form.elements.group_name.value,
        description: form.elements.description.value, cluster_id: form.elements.cluster_id.value,
        source_type: form.elements.source_type.value, entry_point: form.elements.entry_point.value,
        inline_code: form.elements.inline_code.value, application_args: form.elements.application_args.value,
        spark_submit_conf_json: form.elements.spark_submit_conf_json.value,
        is_active: form.elements.is_active_cb.checked ? 1 : 0
      }).then(function (payload) {
        if (payload.ok) {
          window.location = BASE + 'data-engineering/spark-jobs?environment=' + encodeURIComponent(ENV) + '&id=' + payload.id;
          return;
        }
        errBox.textContent = payload.message || 'Save failed.';
        errBox.hidden = false;
      });
    });
    root.appendChild(backdrop);
    root.appendChild(modal);
    syncSource();
  }

  var newBtn = document.getElementById('jobNew');
  if (newBtn) { newBtn.addEventListener('click', function () { openModal(null); }); }
  var editBtn = document.getElementById('jobEdit');
  if (editBtn && SELECTED_JOB) { editBtn.addEventListener('click', function () { openModal(SELECTED_JOB); }); }
  var deleteBtn = document.getElementById('jobDelete');
  if (deleteBtn && SELECTED_JOB) {
    deleteBtn.addEventListener('click', function () {
      if (!window.confirm('Delete job "' + SELECTED_JOB.name + '"?')) { return; }
      post('data-engineering/spark-jobs/delete', { id: SELECTED_JOB.id }).then(function (payload) {
        if (payload.ok) { window.location = BASE + 'data-engineering/spark-jobs?environment=' + encodeURIComponent(ENV); }
        else { window.alert(payload.message || 'Delete failed.'); }
      });
    });
  }

  var sampleSelect = document.getElementById('sampleSelect');
  if (sampleSelect) {
    sampleSelect.addEventListener('change', function () {
      var sample = SAMPLES.filter(function (s) { return s.key === sampleSelect.value; })[0];
      sampleSelect.value = '';
      if (!sample) { return; }
      openModal({ name: sample.name, entry_point: sample.entry_point, application_args: sample.application_args,
                  description: sample.description, source_type: 'repository',
                  cluster_id: CLUSTERS.length ? CLUSTERS[0].id : 0, is_active: 1 });
    });
  }
})();
</script>
