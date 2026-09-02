<?php
$env = isset($selectedEnvironment) ? $selectedEnvironment : 'ALL';
$envQuery = rawurlencode($env);
$runtimeByKey = array();
foreach ($runtimes as $runtime) {
    $runtimeByKey[$runtime->runtime_key] = $runtime;
}
function spark_cluster_lifecycle_label($v) { return $v === 'persistent' ? 'All-Purpose' : 'Job'; }
?>
<link href="<?php echo base_url(); ?>assets/dist/css/compute.css?v=5" rel="stylesheet" type="text/css">
<div class="content-wrapper compute-page">
  <section class="content-header">
    <h1>Compute <small>Spark clusters — All-Purpose &amp; Job</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-home"></i> Home</a></li>
      <li>Data Engineering</li>
      <li class="active">Compute</li>
    </ol>
  </section>

  <section class="content">
    <div class="compute-toolbar">
      <span class="compute-toolbar-env">Environment <strong><?php echo html_escape($env); ?></strong>
        &nbsp;·&nbsp; <span id="capacityGauge" class="text-muted">capacity …</span>
      </span>
      <div class="compute-toolbar-actions">
        <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>data-engineering/spark-jobs?environment=<?php echo $envQuery; ?>"><i class="fa fa-bolt"></i> Spark Activity</a>
        <button class="btn btn-primary btn-sm" type="button" id="clusterNew"<?php echo $env === 'ALL' ? ' disabled' : ''; ?>><i class="fa fa-plus"></i> Create compute</button>
      </div>
    </div>
    <?php if ($env === 'ALL') { ?>
      <div class="compute-alert is-warn"><i class="fa fa-filter"></i> Pick a global environment (top bar) to create or edit compute.</div>
    <?php } ?>

    <div class="compute-card">
      <header><h2>Clusters <span class="compute-chip"><?php echo count($clusters); ?></span></h2></header>
      <div class="compute-card-body is-flush">
        <div class="compute-table-wrap">
          <table class="compute-table compute-table-clusters">
            <thead><tr>
              <th>Name</th><th>Mode</th><th>State</th><th>Runtime</th><th>Workers</th><th>Uptime</th><th class="compute-row-actions">Actions</th>
            </tr></thead>
            <tbody id="clusterRows">
            <?php if (empty($clusters)) { ?>
              <tr><td colspan="7" class="compute-empty">No compute in <strong><?php echo html_escape($env); ?></strong> yet. <em>Create compute</em> to start.</td></tr>
            <?php } foreach ($clusters as $cluster) {
                $rt = isset($runtimeByKey[$cluster->runtime_key]) ? $runtimeByKey[$cluster->runtime_key] : NULL;
                $persistent = isset($cluster->lifecycle) && $cluster->lifecycle === 'persistent';
            ?>
              <tr class="compute-row" data-cluster='<?php echo html_escape(json_encode($cluster, JSON_UNESCAPED_SLASHES)); ?>' data-cluster-id="<?php echo (int) $cluster->id; ?>" data-persistent="<?php echo $persistent ? '1' : '0'; ?>">
                <td>
                  <span class="compute-name"><?php echo html_escape($cluster->name); ?></span>
                  <span class="compute-sub"><?php echo html_escape($cluster->group_name); ?> &middot; <code><?php echo html_escape($cluster->cluster_key); ?></code><?php echo $cluster->is_active ? '' : ' &middot; inactive'; ?></span>
                  <span class="compute-links" hidden></span>
                </td>
                <td><span class="compute-chip"><?php echo spark_cluster_lifecycle_label($persistent ? 'persistent' : 'job'); ?></span></td>
                <td><span class="compute-status is-stopped cluster-state"><?php echo $persistent ? '…' : 'on run'; ?></span></td>
                <td><span class="compute-sub" style="margin:0"><?php echo html_escape($rt ? $rt->display_name : $cluster->runtime_key); ?></span></td>
                <td class="cluster-workers"><?php echo $persistent ? '0 / '.(int) $cluster->min_workers : (int) $cluster->min_workers; ?></td>
                <td class="cluster-uptime">—</td>
                <td class="compute-row-actions">
                  <div class="compute-actions">
                    <?php if ($persistent) { ?>
                      <button class="btn btn-xs btn-success cluster-toggle" type="button" data-verb="start"><i class="fa fa-play"></i> Start</button>
                    <?php } ?>
                    <div class="compute-menu">
                      <button class="btn btn-xs btn-default compute-menu-btn" type="button" aria-haspopup="true"><i class="fa fa-ellipsis-h"></i></button>
                      <div class="compute-menu-list" hidden>
                        <?php if ($persistent) { ?>
                          <a href="#" data-act="restart"><i class="fa fa-refresh"></i> Restart</a>
                          <a href="#" data-act="monitor"><i class="fa fa-heartbeat"></i> Monitor</a>
                          <a href="#" data-act="notebook"><i class="fa fa-book"></i> Open notebook</a>
                          <a href="#" data-act="jupyter" class="compute-menu-link" hidden><i class="fa fa-external-link"></i> JupyterLab</a>
                          <a href="#" data-act="sparkui" class="compute-menu-link" hidden><i class="fa fa-external-link"></i> Spark UI</a>
                          <div class="compute-menu-sep"></div>
                        <?php } ?>
                        <a href="#" data-act="edit"><i class="fa fa-pencil"></i> Edit</a>
                        <a href="#" data-act="delete" class="is-danger"><i class="fa fa-trash"></i> Delete</a>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<div id="clusterModalRoot" hidden></div>

<script>
(function () {
  'use strict';
  var BASE = <?php echo json_encode(base_url()); ?>;
  var ENV = <?php echo json_encode($env); ?>;
  var RUNTIMES = <?php echo json_encode(array_map(function ($r) {
      return array('key' => $r->runtime_key, 'name' => $r->display_name, 'image' => $r->image_repository.':'.$r->image_tag, 'desc' => (string) $r->description);
  }, $runtimes)); ?>;
  var CSRF = window.jobseekerCsrf || { name: '', hash: '' };

  function el(html) { var t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
  function fmtBytes(n) { n = +n || 0; var u = ['B','KB','MB','GB']; var i = 0; while (n >= 1024 && i < 3) { n /= 1024; i++; } return n.toFixed(i ? 1 : 0) + ' ' + u[i]; }
  function fmtDur(s) { s = Math.max(0, s | 0); var h = s / 3600 | 0, m = (s % 3600) / 60 | 0; return h ? (h + 'h ' + m + 'm') : (m ? (m + 'm') : (s + 's')); }
  function postForm(path, body) {
    if (CSRF.name) { body.set(CSRF.name, CSRF.hash); }
    return fetch(BASE + path, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }
  function getJSON(path) {
    return fetch(BASE + path, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }
  var STATE_CLASS = { RUNNING: 'is-running', PROVISIONING: 'is-provisioning', STOPPING: 'is-provisioning',
    STOPPED: 'is-stopped', FAILED: 'is-failed', JOB: 'is-cancelled' };

  // ---- one monitor, one timer --------------------------------------------
  var MON = { timer: null, id: null, starts: 0, stops: 0 };
  window.__sparkMon = MON;

  function closeDetail() {
    if (MON.timer) { clearInterval(MON.timer); MON.timer = null; MON.stops++; }
    var open = document.querySelector('.compute-detail-row');
    if (open) { open.parentNode.removeChild(open); }
    MON.id = null;
  }
  function openDetail(id, name) {
    if (MON.id === id) { closeDetail(); return; }
    closeDetail();
    var row = document.querySelector('tr.compute-row[data-cluster-id="' + id + '"]');
    if (!row) { return; }
    var tr = el('<tr class="compute-detail-row"><td colspan="7"><div class="compute-detail">' +
      '<div class="compute-detail-head"><strong>' + esc(name) + '</strong> — live monitor ' +
      '<button type="button" class="btn btn-xs btn-default compute-detail-close">&times; close</button></div>' +
      '<div class="compute-detail-body"><p class="compute-sub">Loading…</p></div></div></td></tr>');
    row.parentNode.insertBefore(tr, row.nextSibling);
    tr.querySelector('.compute-detail-close').addEventListener('click', closeDetail);
    MON.id = id;
    tickDetail(id);
    MON.timer = setInterval(function () { tickDetail(id); }, 4000);
    MON.starts++;
  }
  function tickDetail(id) {
    getJSON('data-engineering/spark-clusters/status/' + id).then(function (p) {
      if (!p || !p.ok) { return; }
      applyRow(id, p);
      var box = document.querySelector('.compute-detail-row .compute-detail-body');
      if (!box) { return; }
      var s = p.spark, links = p.links, inst = p.instance || {};
      var rows = (p.containers || []).map(function (c) {
        var cpu = (c.cpuPercent != null ? Number(c.cpuPercent).toFixed(1) : '0.0') + '%';
        var mem = c.memoryLimitBytes ? (fmtBytes(c.memoryBytes) + ' / ' + fmtBytes(c.memoryLimitBytes)) : fmtBytes(c.memoryBytes);
        return '<tr><td><span class="compute-chip">' + esc(c.role) + '</span> ' + esc(c.name) + '</td>' +
          '<td>' + esc(c.state) + (c.exitCode != null ? ' (exit ' + c.exitCode + ')' : '') + '</td>' +
          '<td>' + cpu + '</td><td>' + mem + '</td></tr>';
      }).join('');
      var sparkHtml = s ? ('<p class="compute-sub"><b>Spark master</b> ' + esc(s.status || 'ALIVE') +
        ' · workers ' + (s.aliveworkers != null ? s.aliveworkers : (s.workers || 0)) +
        ' · cores ' + (s.coresused || 0) + '/' + (s.cores || 0) +
        ' · mem ' + (s.memoryused || 0) + '/' + (s.memory || 0) + ' MB' +
        (s.activeapps != null ? (' · apps ' + s.activeapps) : '') + '</p>') : '';
      var linksHtml = links ? ('<p class="compute-sub">' +
        '<a href="' + esc(links.jupyterLab) + '" target="_blank" rel="noopener">JupyterLab &#8599;</a> &nbsp; ' +
        '<a href="' + esc(links.sparkUi) + '" target="_blank" rel="noopener">Spark UI &#8599;</a><br>' +
        'VS Code &rarr; Existing Jupyter Server: <code>' + esc(links.jupyterVsCode) + '</code></p>') : '';
      var errHtml = inst.error_message ? '<p class="compute-help" style="color:#c0392b">' + esc(inst.error_message) + '</p>' : '';
      box.innerHTML = '<div><span class="compute-status ' + (STATE_CLASS[p.clusterStatus] || 'is-stopped') + '">' + esc(p.clusterStatus) + '</span>' +
        (p.uptimeSeconds ? ' <span class="compute-sub">up ' + fmtDur(p.uptimeSeconds) + '</span>' : '') + '</div>' +
        errHtml + sparkHtml + linksHtml +
        (rows ? '<div class="compute-table-wrap"><table class="compute-table"><thead><tr><th>Node</th><th>State</th><th>CPU</th><th>Memory</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
              : '<p class="compute-sub">No containers running.</p>');
    });
  }

  // ---- per-row state ----------------------------------------------------
  function applyRow(id, p) {
    var row = document.querySelector('tr.compute-row[data-cluster-id="' + id + '"]');
    if (!row) { return; }
    var st = p.clusterStatus || 'STOPPED';
    var pill = row.querySelector('.cluster-state');
    if (pill) { pill.className = 'compute-status cluster-state ' + (STATE_CLASS[st] || 'is-stopped'); pill.textContent = st; }
    var wk = row.querySelector('.cluster-workers');
    if (wk && row.getAttribute('data-persistent') === '1') {
      var running = p.workerRunning != null ? p.workerRunning : 0;
      var target = p.workerTarget != null ? p.workerTarget : (parseInt((wk.textContent.split('/')[1] || '0'), 10) || 0);
      wk.textContent = running + ' / ' + target;
    }
    var up = row.querySelector('.cluster-uptime');
    if (up) { up.textContent = p.uptimeSeconds ? fmtDur(p.uptimeSeconds) : '—'; }
    var toggle = row.querySelector('.cluster-toggle');
    if (toggle) {
      var running = st === 'RUNNING' || st === 'PROVISIONING' || st === 'STOPPING';
      toggle.setAttribute('data-verb', running ? 'stop' : 'start');
      toggle.className = 'btn btn-xs cluster-toggle ' + (running ? 'btn-warning' : 'btn-success');
      toggle.innerHTML = running ? '<i class="fa fa-stop"></i> Stop' : '<i class="fa fa-play"></i> Start';
      toggle.disabled = (st === 'PROVISIONING' || st === 'STOPPING');
    }
    var links = p.links;
    var inlineLinks = row.querySelector('.compute-links');
    if (inlineLinks) {
      if (links) {
        inlineLinks.hidden = false;
        inlineLinks.innerHTML = '<a href="' + esc(links.jupyterLab) + '" target="_blank" rel="noopener">JupyterLab &#8599;</a> · ' +
          '<a href="' + esc(links.sparkUi) + '" target="_blank" rel="noopener">Spark UI &#8599;</a>';
      } else { inlineLinks.hidden = true; inlineLinks.innerHTML = ''; }
    }
    row.querySelectorAll('.compute-menu-link').forEach(function (a) {
      var which = a.getAttribute('data-act');
      var href = links ? (which === 'jupyter' ? links.jupyterLab : links.sparkUi) : '';
      if (href) { a.hidden = false; a.href = href; a.target = '_blank'; } else { a.hidden = true; }
    });
  }

  // ---- overview poll (pills for every row) -----------------------------
  function refreshOverview() {
    getJSON('data-engineering/spark-clusters/overview').then(function (p) {
      if (!p || !p.ok) { return; }
      (p.clusters || []).forEach(function (c) { applyRow(c.id, c); });
      var cap = p.capacity && p.capacity.host;
      var g = document.getElementById('capacityGauge');
      if (g && cap && cap.available) {
        g.textContent = 'free ' + Math.round(cap.freeCpus * 10) / 10 + ' vCPU / ' + cap.freeMemoryMb + ' MB of ' +
          Math.round(cap.cpus) + ' vCPU / ' + cap.memoryMb + ' MB';
      } else if (g) { g.textContent = 'capacity: n/a'; }
    });
  }

  // ---- modal ----------------------------------------------------------
  function runtimeOptions(sel) {
    return RUNTIMES.map(function (r) { return '<option value="' + esc(r.key) + '"' + (r.key === sel ? ' selected' : '') + '>' + esc(r.name) + '</option>'; }).join('');
  }
  function catalogueHtml() {
    return RUNTIMES.map(function (r) { return '<li><b>' + esc(r.name) + '</b> — <code>' + esc(r.image) + '</code><br><span class="compute-sub">' + esc(r.desc) + '</span></li>'; }).join('');
  }
  function openModal(cluster) {
    var c = cluster || {};
    var isEdit = !!c.id;
    var root = document.getElementById('clusterModalRoot');
    root.hidden = false; root.innerHTML = '';
    var backdrop = el('<div class="compute-modal-backdrop"></div>');
    var modal = el(
      '<div class="compute-modal" role="dialog" aria-modal="true">' +
        '<header><h3>' + (isEdit ? 'Edit compute' : 'Create compute') + '</h3>' +
        '<button type="button" class="btn btn-xs btn-default" data-close>&times;</button></header>' +
        '<form class="compute-modal-body"><div class="compute-form-grid">' +
          '<div><label>Name</label><input class="form-control input-sm" name="name" required value="' + esc(c.name || '') + '"></div>' +
          '<div><label>Group</label><input class="form-control input-sm" name="group_name" value="' + esc(c.group_name || 'General') + '"></div>' +
          '<div class="full"><label>Description</label><input class="form-control input-sm" name="description" maxlength="2000" value="' + esc(c.description || '') + '"></div>' +
          '<div><label>Mode</label><select class="form-control input-sm" name="lifecycle">' +
            '<option value="persistent"' + (c.lifecycle === 'persistent' ? ' selected' : '') + '>All-Purpose (Start/Stop, notebooks)</option>' +
            '<option value="job"' + (c.lifecycle === 'persistent' ? '' : ' selected') + '>Job (ephemeral, per run)</option>' +
          '</select></div>' +
          '<div><label>Runtime</label><select class="form-control input-sm" name="runtime_key">' + runtimeOptions(c.runtime_key) + '</select></div>' +
          '<div><label>Idle timeout (min)</label><input class="form-control input-sm" type="number" min="1" max="10080" name="idle_timeout_minutes" value="' + (c.idle_timeout_minutes || 120) + '"></div>' +
          '<div><label>Driver vCPU</label><input class="form-control input-sm" type="number" min="1" max="16" name="driver_cores" value="' + (c.driver_cores || 1) + '"></div>' +
          '<div><label>Driver memory (MB)</label><input class="form-control input-sm" type="number" min="512" step="256" name="driver_memory_mb" value="' + (c.driver_memory_mb || 1024) + '"></div>' +
          '<div><label>Worker vCPU</label><input class="form-control input-sm" type="number" min="1" max="32" name="worker_cores" value="' + (c.worker_cores || 1) + '"></div>' +
          '<div><label>Worker memory (MB)</label><input class="form-control input-sm" type="number" min="512" step="256" name="worker_memory_mb" value="' + (c.worker_memory_mb || 1024) + '"></div>' +
          '<div><label>Min workers</label><input class="form-control input-sm" type="number" min="1" max="64" name="min_workers" value="' + (c.min_workers || 1) + '"></div>' +
          '<div><label>Max workers</label><input class="form-control input-sm" type="number" min="1" max="64" name="max_workers" value="' + (c.max_workers || 2) + '"></div>' +
          '<div><label><input type="checkbox" name="autoscale" ' + (c.autoscale == 1 ? 'checked' : '') + '> Autoscale</label></div>' +
          '<div><label><input type="checkbox" name="is_active_cb" ' + (c.is_active == 0 ? '' : 'checked') + '> Active</label></div>' +
          '<div class="full"><label>Spark conf (JSON)</label><textarea class="form-control" name="spark_conf_json">' + esc(c.spark_conf_json || '{}') + '</textarea></div>' +
          '<div class="full"><label>Environment variables (JSON)</label><textarea class="form-control" name="env_json">' + esc(c.env_json || '{}') + '</textarea></div>' +
          '<div class="full"><details class="compute-catalogue"><summary>Runtime catalogue</summary><ul>' + catalogueHtml() + '</ul></details></div>' +
        '</div><p class="compute-help" style="color:#c0392b" data-error hidden></p>' +
        '<footer style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">' +
          '<button type="button" class="btn btn-default btn-sm" data-close>Cancel</button>' +
          '<button type="submit" class="btn btn-primary btn-sm">' + (isEdit ? 'Save changes' : 'Create') + '</button>' +
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
      ['name', 'group_name', 'description', 'runtime_key', 'lifecycle', 'idle_timeout_minutes', 'driver_cores', 'driver_memory_mb',
       'worker_cores', 'worker_memory_mb', 'min_workers', 'max_workers', 'spark_conf_json', 'env_json'].forEach(function (f) {
        body.set(f, form.elements[f].value);
      });
      body.set('autoscale', form.elements.autoscale.checked ? 1 : 0);
      body.set('is_active', form.elements.is_active_cb.checked ? 1 : 0);
      var errBox = form.querySelector('[data-error]');
      errBox.hidden = true;
      postForm('data-engineering/spark-clusters/save', body).then(function (payload) {
        if (payload.ok) { window.location.reload(); return; }
        errBox.textContent = payload.message || 'Save failed.'; errBox.hidden = false;
      }).catch(function () { errBox.textContent = 'Network error.'; errBox.hidden = false; });
    });
    root.appendChild(backdrop); root.appendChild(modal);
  }

  var newBtn = document.getElementById('clusterNew');
  if (newBtn) { newBtn.addEventListener('click', function () { openModal(null); }); }

  // ---- row wiring ----------------------------------------------------
  function lifecycleCall(id, name, path, verb, btn) {
    var original = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }
    var body = new URLSearchParams(); body.set('id', id);
    return postForm(path, body).then(function (payload) {
      if (btn) { btn.disabled = false; btn.innerHTML = original; }
      if (!payload.ok) { window.alert(payload.message || (verb + ' failed.')); }
      applyRow(id, payload);
      if (verb === 'Stop') { if (MON.id === id) { closeDetail(); } }
      else if (MON.id === id) { tickDetail(id); }
      else if (payload.ok) { openDetail(id, name); }
      return payload;
    }).catch(function () { if (btn) { btn.disabled = false; btn.innerHTML = original; } window.alert('Network error.'); });
  }

  document.querySelectorAll('tr.compute-row').forEach(function (row) {
    var cluster = JSON.parse(row.getAttribute('data-cluster'));
    var id = cluster.id, persistent = row.getAttribute('data-persistent') === '1';

    var toggle = row.querySelector('.cluster-toggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var verb = toggle.getAttribute('data-verb');
        if (verb === 'stop' && !window.confirm('Stop "' + cluster.name + '"? Attached notebooks lose their kernel.')) { return; }
        lifecycleCall(id, cluster.name, 'data-engineering/spark-clusters/' + (verb === 'stop' ? 'stop' : 'start'), verb === 'stop' ? 'Stop' : 'Start', toggle);
      });
    }

    var menu = row.querySelector('.compute-menu');
    var menuBtn = row.querySelector('.compute-menu-btn');
    var menuList = row.querySelector('.compute-menu-list');
    menuBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      document.querySelectorAll('.compute-menu-list').forEach(function (l) { if (l !== menuList) l.hidden = true; });
      menuList.hidden = !menuList.hidden;
    });
    menuList.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-act]');
      if (!a) { return; }
      var act = a.getAttribute('data-act');
      if (act === 'jupyter' || act === 'sparkui') { return; } // real hrefs
      e.preventDefault();
      menuList.hidden = true;
      if (act === 'edit') { openModal(cluster); }
      else if (act === 'delete') {
        if (!window.confirm('Delete "' + cluster.name + '"?')) { return; }
        var b = new URLSearchParams(); b.set('id', id);
        postForm('data-engineering/spark-clusters/delete', b).then(function (p) {
          if (p.ok) { window.location.reload(); } else { window.alert(p.message || 'Delete failed.'); }
        });
      }
      else if (act === 'monitor') { openDetail(id, cluster.name); }
      else if (act === 'restart') { lifecycleCall(id, cluster.name, 'data-engineering/spark-clusters/restart', 'Restart', null); }
      else if (act === 'notebook') {
        var b2 = new URLSearchParams(); b2.set('id', id);
        postForm('data-engineering/spark-clusters/notebook', b2).then(function (p) {
          if (p.ok && p.url) { window.open(p.url, '_blank', 'noopener'); }
          else { window.alert(p.message || 'Could not open the notebook workspace.'); }
        });
      }
    });
  });
  document.addEventListener('click', function () { document.querySelectorAll('.compute-menu-list').forEach(function (l) { l.hidden = true; }); });
  window.addEventListener('beforeunload', closeDetail);

  refreshOverview();
  setInterval(refreshOverview, 5000);
})();
</script>
