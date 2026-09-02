<?php
$env = isset($selectedEnvironment) ? $selectedEnvironment : 'ALL';
$envQuery = rawurlencode($env);
$jobs = isset($jobs) ? $jobs : array();
$runs = isset($runs) ? $runs : array();
$allPurpose = isset($allPurpose) ? $allPurpose : array();
$cap = isset($capacity['host']) ? $capacity['host'] : array();
$terminal = array('SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT');
function spark_state_class($s) {
    $m = array('SUCCEEDED' => 'is-succeeded', 'RUNNING' => 'is-running', 'PROVISIONING' => 'is-provisioning',
        'QUEUED' => 'is-queued', 'FAILED' => 'is-failed', 'TIMED_OUT' => 'is-failed', 'CANCELLED' => 'is-cancelled');
    return isset($m[$s]) ? $m[$s] : 'is-cancelled';
}
function spark_dur($s) { $s = max(0, (int) $s); $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60); return $h ? ($h.'h '.$m.'m') : ($m ? ($m.'m '.($s % 60).'s') : ($s.'s')); }
?>
<link href="<?php echo base_url(); ?>assets/dist/css/compute.css?v=5" rel="stylesheet" type="text/css">
<div class="content-wrapper compute-page">
  <section class="content-header">
    <h1>Spark Activity <small>compute &amp; runs across every Spark job</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-home"></i> Home</a></li>
      <li>Data Engineering</li>
      <li class="active">Spark Activity</li>
    </ol>
  </section>

  <section class="content">
    <div class="compute-toolbar">
      <span class="compute-toolbar-env">Environment <strong><?php echo html_escape($env); ?></strong>
        &nbsp;·&nbsp; engine <strong class="<?php echo $engineHealthy ? 'compute-capacity-ok' : 'compute-capacity-warn'; ?>"><?php echo $engineHealthy ? 'reachable' : 'unreachable'; ?></strong>
        &nbsp;·&nbsp; <span id="capacityGauge">capacity …</span>
      </span>
      <div class="compute-toolbar-actions">
        <label class="compute-toolbar-check" style="font-size:12px;font-weight:600;color:#475467"><input type="checkbox" id="sparkAutoRefresh" checked> Auto-refresh</label>
        <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>data-engineering/spark-clusters?environment=<?php echo $envQuery; ?>"><i class="fa fa-server"></i> Compute</a>
        <a class="btn btn-primary btn-sm" href="<?php echo base_url(); ?>jobCreation#runSparkJob"><i class="fa fa-plus"></i> New Spark job</a>
      </div>
    </div>

    <?php if ($env === 'ALL') { ?>
      <div class="compute-alert is-warn"><i class="fa fa-filter"></i> Pick a global environment (top bar) to scope this view.</div>
    <?php } ?>

    <div class="compute-card" style="margin-bottom:16px">
      <header><h2><i class="fa fa-server"></i> All-Purpose compute</h2>
        <a class="compute-sub" style="margin:0" href="<?php echo base_url(); ?>data-engineering/spark-clusters?environment=<?php echo $envQuery; ?>">Manage &rarr;</a>
      </header>
      <div class="compute-card-body">
        <div id="allPurposeStrip" class="compute-ap-strip">
          <?php if (empty($allPurpose)) { ?>
            <p class="compute-sub" style="margin:0">No All-Purpose clusters in <?php echo html_escape($env); ?>. Create one on the Compute screen to develop notebooks interactively.</p>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="compute-grid">
      <div class="compute-card">
        <header><h2><i class="fa fa-fire"></i> Spark jobs <span class="compute-chip"><?php echo count($jobs); ?></span></h2></header>
        <div class="compute-card-body is-flush">
          <div class="compute-table-wrap">
            <table class="compute-table">
              <thead><tr><th>Name</th><th>Mode</th><th>Cluster</th><th></th></tr></thead>
              <tbody>
              <?php if (empty($jobs)) { ?>
                <tr><td colspan="4" class="compute-empty">No Spark jobs yet. <a href="<?php echo base_url(); ?>jobCreation#runSparkJob">Create one &rarr;</a></td></tr>
              <?php } foreach ($jobs as $j) {
                  $editUrl = base_url().'jobCreation?sparkJob='.(int) $j->id.'#runSparkJob';
                  $watchUrl = $j->jenkins_job_name
                      ? base_url().'jobExecution?job='.rawurlencode($j->jenkins_job_name).'&environment='.rawurlencode($j->environment)
                      : '';
              ?>
                <tr>
                  <td>
                    <a href="<?php echo $editUrl; ?>"><span class="compute-name"><?php echo html_escape($j->name); ?></span></a>
                    <span class="compute-sub"><?php echo html_escape($j->environment); ?><?php echo $j->is_active ? '' : ' · inactive'; ?></span>
                  </td>
                  <td><span class="compute-chip"><?php echo html_escape(isset($j->mode) && $j->mode === 'interactive' ? 'Interactive' : 'Batch'); ?></span></td>
                  <td><span class="compute-sub" style="margin:0"><?php echo html_escape(isset($j->cluster_name) ? $j->cluster_name : '—'); ?></span></td>
                  <td class="compute-row-actions">
                    <a class="btn btn-xs btn-default" href="<?php echo $editUrl; ?>" title="Edit in Create Job"><i class="fa fa-pencil"></i></a>
                    <?php if ($watchUrl) { ?><a class="btn btn-xs btn-default" href="<?php echo $watchUrl; ?>" title="Watch in Job Execution"><i class="fa fa-play"></i></a><?php } ?>
                  </td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="compute-card">
        <header><h2><i class="fa fa-history"></i> Recent runs</h2>
          <span class="compute-sub" id="sparkRunsUpdated" style="margin:0"></span>
        </header>
        <div class="compute-card-body is-flush">
          <div class="compute-table-wrap">
            <table class="compute-table">
              <thead><tr><th>Run</th><th>Job</th><th>Mode</th><th>Status</th><th>Duration</th><th>Started</th><th>By</th><th></th></tr></thead>
              <tbody id="sparkRunsBody">
              <?php if (empty($runs)) { ?>
                <tr><td colspan="8" class="compute-empty">No runs recorded.</td></tr>
              <?php } foreach ($runs as $r) {
                  $isTerminal = in_array($r->status, $terminal, TRUE);
                  $watchUrl = $r->jenkins_job_name
                      ? base_url().'jobExecution?job='.rawurlencode($r->jenkins_job_name).'&environment='.rawurlencode($r->environment).($r->jenkins_build_number ? '&build='.(int) $r->jenkins_build_number : '')
                      : '';
                  $started = $r->started_at ? strtotime($r->started_at.' UTC') : 0;
                  $dur = $started ? (($r->completed_at ? strtotime($r->completed_at.' UTC') : time()) - $started) : 0;
              ?>
                <tr class="spark-run-row" data-run-id="<?php echo (int) $r->id; ?>" data-terminal="<?php echo $isTerminal ? '1' : '0'; ?>">
                  <td><code><?php echo html_escape($r->run_key); ?></code></td>
                  <td><?php echo html_escape($r->job_name ? $r->job_name : ('#'.$r->job_id)); ?><span class="compute-sub" style="margin:0"><?php echo html_escape($r->environment); ?><?php echo isset($r->cluster_name) && $r->cluster_name ? ' · '.html_escape($r->cluster_name) : ''; ?></span></td>
                  <td><span class="compute-chip"><?php echo isset($r->mode) && $r->mode === 'interactive' ? 'Interactive' : 'Batch'; ?></span></td>
                  <td><span class="compute-status <?php echo spark_state_class($r->status); ?>"><?php echo html_escape($r->status); ?></span><?php echo ($r->exit_code !== NULL && (int) $r->exit_code !== 0) ? ' <span class="compute-sub" style="margin:0">exit '.(int) $r->exit_code.'</span>' : ''; ?></td>
                  <td><?php echo spark_dur($dur); ?></td>
                  <td class="compute-sub" style="margin:0"><?php echo html_escape($r->started_at); ?></td>
                  <td class="compute-sub" style="margin:0"><?php echo html_escape($r->triggered_by); ?></td>
                  <td class="compute-row-actions">
                    <button type="button" class="btn btn-xs btn-default spark-run-open"><i class="fa fa-terminal"></i></button>
                    <?php if ($watchUrl) { ?><a class="btn btn-xs btn-default" href="<?php echo $watchUrl; ?>" title="Open in Job Execution"><i class="fa fa-external-link"></i></a><?php } ?>
                  </td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
(function ($) {
  'use strict';
  var BASE = <?php echo json_encode(base_url()); ?>;
  var ENV_QUERY = <?php echo json_encode($envQuery); ?>;
  var TERMINAL = ['SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT'];
  var STATE_CLASS = { SUCCEEDED: 'is-succeeded', RUNNING: 'is-running', PROVISIONING: 'is-provisioning', QUEUED: 'is-queued',
    FAILED: 'is-failed', TIMED_OUT: 'is-failed', CANCELLED: 'is-cancelled', STOPPED: 'is-stopped' };
  var CSRF = window.jobseekerCsrf || { name: '', hash: '' };

  function esc(s) { return $('<i>').text(s == null ? '' : String(s)).html(); }
  function fmtDur(s) { s = Math.max(0, s | 0); var h = s / 3600 | 0, m = (s % 3600) / 60 | 0; return h ? (h + 'h ' + m + 'm') : (m ? (m + 'm ' + (s % 60) + 's') : (s + 's')); }
  function postForm(path, data) {
    var body = new URLSearchParams(data || {});
    if (CSRF.name) { body.set(CSRF.name, CSRF.hash); }
    return fetch(BASE + path, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }

  var openRunId = null, runTimer = null, listTimer = null;

  function renderAllPurpose(list) {
    var $s = $('#allPurposeStrip');
    if (!list || !list.length) { return; }
    $s.empty();
    list.forEach(function (c) {
      var st = c.status || 'STOPPED';
      var links = c.links || {};
      var linksHtml = (st === 'RUNNING' && c.links)
        ? '<div class="compute-sub" style="margin:6px 0 0"><a href="' + esc(links.jupyterLab) + '" target="_blank" rel="noopener">JupyterLab &#8599;</a> · <a href="' + esc(links.sparkUi) + '" target="_blank" rel="noopener">Spark UI &#8599;</a></div>'
        : '';
      $s.append(
        '<div class="compute-ap-card">' +
          '<div class="compute-ap-head"><b>' + esc(c.name) + '</b>' +
            '<span class="compute-status ' + (STATE_CLASS[st] || 'is-stopped') + '">' + esc(st) + '</span></div>' +
          '<div class="compute-sub" style="margin:4px 0 0">workers ' + (c.workerRunning || 0) + ' / ' + (c.workerTarget || 0) +
            (c.uptimeSeconds ? ' · up ' + fmtDur(c.uptimeSeconds) : '') + '</div>' +
          linksHtml +
          '<div style="margin-top:8px"><button type="button" class="btn btn-xs btn-default ap-notebook" data-id="' + c.id + '"><i class="fa fa-book"></i> Open notebook</button></div>' +
        '</div>'
      );
    });
  }

  function renderRuns(runs) {
    var $b = $('#sparkRunsBody');
    if (!runs.length) { $b.html('<tr><td colspan="8" class="compute-empty">No runs recorded.</td></tr>'); return; }
    // Preserve an open detail drawer across the 5s rebuild.
    var wasOpen = openRunId;
    if (runTimer) { clearInterval(runTimer); runTimer = null; }
    openRunId = null;
    $b.find('tr.spark-run-detail').remove();
    $b.empty();
    runs.forEach(function (r) {
      var watchUrl = r.jenkins_job_name
        ? BASE + 'jobExecution?job=' + encodeURIComponent(r.jenkins_job_name) + '&environment=' + encodeURIComponent(r.environment) + (r.jenkins_build_number ? '&build=' + r.jenkins_build_number : '')
        : '';
      var $tr = $('<tr class="spark-run-row">').attr('data-run-id', r.id).attr('data-terminal', r.terminal ? '1' : '0');
      $tr.append('<td><code>' + esc(r.run_key) + '</code></td>');
      $tr.append('<td>' + esc(r.job_name || ('#' + r.id)) + '<span class="compute-sub" style="margin:0">' + esc(r.environment) + (r.cluster_name ? ' · ' + esc(r.cluster_name) : '') + '</span></td>');
      $tr.append('<td><span class="compute-chip">' + (r.mode === 'interactive' ? 'Interactive' : 'Batch') + '</span></td>');
      $tr.append('<td><span class="compute-status ' + (STATE_CLASS[r.status] || 'is-cancelled') + '">' + esc(r.status) + '</span>' +
        (r.exit_code != null && r.exit_code !== 0 ? ' <span class="compute-sub" style="margin:0">exit ' + r.exit_code + '</span>' : '') + '</td>');
      $tr.append('<td>' + fmtDur(r.duration_seconds || 0) + '</td>');
      $tr.append('<td class="compute-sub" style="margin:0">' + esc(r.started_at) + '</td>');
      $tr.append('<td class="compute-sub" style="margin:0">' + esc(r.triggered_by) + '</td>');
      var actions = '<button type="button" class="btn btn-xs btn-default spark-run-open"><i class="fa fa-terminal"></i></button> ';
      if (watchUrl) { actions += '<a class="btn btn-xs btn-default" href="' + watchUrl + '"><i class="fa fa-external-link"></i></a>'; }
      $tr.append('<td class="compute-row-actions">' + actions + '</td>');
      $b.append($tr);
    });
    if (wasOpen != null && $b.find('tr.spark-run-row[data-run-id="' + wasOpen + '"]').length) { openRun(wasOpen); }
  }

  function refreshList() {
    $.getJSON(BASE + 'data-engineering/spark-jobs/activity?environment=' + ENV_QUERY).done(function (r) {
      if (!r || !r.ok) { return; }
      renderRuns(r.runs || []);
      renderAllPurpose(r.allPurpose || []);
      var cap = r.capacity && r.capacity.host;
      if (cap && cap.available) {
        $('#capacityGauge').text('free ' + (Math.round(cap.freeCpus * 10) / 10) + ' vCPU / ' + cap.freeMemoryMb + ' MB of ' + Math.round(cap.cpus) + ' vCPU / ' + cap.memoryMb + ' MB');
      } else { $('#capacityGauge').text('capacity: n/a'); }
      $('#sparkRunsUpdated').text('updated ' + new Date().toLocaleTimeString());
      if (openRunId != null) {
        var still = (r.runs || []).some(function (x) { return x.id === openRunId && !x.terminal; });
        if (!still && runTimer) { clearInterval(runTimer); runTimer = null; }
      }
    });
  }

  // ---- run detail drawer (inline) -----------------------------------
  function closeRun() {
    if (runTimer) { clearInterval(runTimer); runTimer = null; }
    $('#sparkRunsBody tr.spark-run-detail').remove();
    openRunId = null;
  }
  function openRun(id) {
    if (openRunId === id) { closeRun(); return; }
    closeRun();
    var $row = $('#sparkRunsBody tr.spark-run-row[data-run-id="' + id + '"]');
    if (!$row.length) { return; }
    var terminal = $row.attr('data-terminal') === '1';
    var $d = $('<tr class="spark-run-detail"><td colspan="8">' +
      '<div class="compute-run-drawer"><header><span class="compute-run-meta">run ' + id + '</span>' +
      (terminal ? '' : '<button type="button" class="btn btn-xs btn-danger spark-run-cancel">Cancel run</button>') +
      '<button type="button" class="btn btn-xs btn-default spark-run-close">close</button></header>' +
      '<pre class="compute-run-log">loading…</pre></div></td></tr>');
    $row.after($d);
    $d.find('.spark-run-close').on('click', closeRun);
    $d.find('.spark-run-cancel').on('click', function () {
      if (!window.confirm('Cancel run ' + id + '?')) { return; }
      postForm('data-engineering/spark-jobs/cancel', { id: id }).then(function () { tickRun(id); refreshList(); });
    });
    openRunId = id;
    tickRun(id);
    if (!terminal) { runTimer = setInterval(function () { tickRun(id); }, 3000); }
  }
  function tickRun(id) {
    $.getJSON(BASE + 'data-engineering/spark-jobs/logs/' + id).done(function (r) {
      var $log = $('#sparkRunsBody tr.spark-run-detail .compute-run-log');
      if (!$log.length || !r) { return; }
      $log.text(r.logs || '(no output yet)');
      if (r.terminal && runTimer) { clearInterval(runTimer); runTimer = null; }
    });
  }

  $(document).on('click', '.spark-run-open', function (e) { e.stopPropagation(); openRun(+$(this).closest('tr').data('run-id')); });
  $(document).on('click', 'tr.spark-run-row td:not(.compute-row-actions)', function () { openRun(+$(this).closest('tr').data('run-id')); });
  $(document).on('click', '.ap-notebook', function () {
    var btn = this; btn.disabled = true;
    postForm('data-engineering/spark-clusters/notebook', { id: $(this).data('id') }).then(function (p) {
      btn.disabled = false;
      if (p.ok && p.url) { window.open(p.url, '_blank', 'noopener'); } else { window.alert(p.message || 'Could not open the notebook workspace.'); }
    }).catch(function () { btn.disabled = false; window.alert('Network error.'); });
  });

  function setAutoRefresh(on) {
    if (listTimer) { clearInterval(listTimer); listTimer = null; }
    if (on) { listTimer = setInterval(refreshList, 5000); }
  }
  $('#sparkAutoRefresh').on('change', function () { setAutoRefresh(this.checked); });
  window.addEventListener('beforeunload', closeRun);

  refreshList();
  setAutoRefresh(true);
})(jQuery);
</script>
