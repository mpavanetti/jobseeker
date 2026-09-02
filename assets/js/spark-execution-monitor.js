/* Job Execution enrichment: for any execution tab whose Jenkins job is a Spark
 * job, show a live cluster monitor (topology, per-node CPU/mem/net, Spark master
 * state) above the console. Fully self-contained - polls
 * data-engineering/spark-jobs/monitor-by-build and paints #spark-<runId>. */
(function ($) {
  'use strict';
  if (!$) { return; }

  var cfg = window.jobseekerSparkExecMonitor || {};
  var BASE = cfg.baseUrl || (window.baseURL || '/');
  var POLL_MS = 3000;
  var paneState = {}; // paneId -> { isSpark: bool|undefined, done: bool }

  function esc(s) { return $('<i>').text(s == null ? '' : String(s)).html(); }
  function fmtBytes(n) {
    n = +n || 0; var u = ['B', 'KB', 'MB', 'GB']; var i = 0;
    while (n >= 1024 && i < 3) { n /= 1024; i++; }
    return n.toFixed(i ? 1 : 0) + ' ' + u[i];
  }
  function num(v, d) { v = Number(v); return isFinite(v) ? v : (d || 0); }

  function paneBuild($pane) {
    var t = $.trim($pane.find('.run-build').first().text());
    var m = /(\d+)/.exec(t);
    return m ? m[1] : '';
  }

  function roleLabel(role) {
    return { master: 'label-primary', worker: 'label-default', driver: 'label-info' }[role] || 'label-default';
  }

  function render($panel, payload) {
    var run = payload.run_id ? payload : null;
    var containers = (payload.containers) || [];
    var agg = payload.aggregate || {};
    var spark = payload.spark || null;
    var status = payload.status || (payload.phase || '');
    var terminal = !!payload.terminal;

    var head =
      '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">' +
        '<strong><i class="fa fa-fire"></i> Spark cluster</strong>' +
        '<span class="label ' + (status === 'SUCCEEDED' ? 'label-success' : (status === 'RUNNING' || status === 'PROVISIONING' ? 'label-primary' : (status ? 'label-warning' : 'label-default'))) + '">' + esc(status || 'unknown') + '</span>' +
        (payload.run_key ? '<span class="text-muted" style="font-size:11px">run ' + esc(payload.run_key) + '</span>' : '') +
      '</div>';

    var sparkHtml = spark ?
      '<div class="callout callout-info" style="margin:0 0 8px;padding:8px 12px">' +
        '<b>Master</b> ' + esc(spark.status || 'ALIVE') +
        ' &nbsp;·&nbsp; workers <b>' + num(spark.aliveworkers) + '</b>/' + num(spark.workers) +
        ' &nbsp;·&nbsp; cores <b>' + num(spark.coresused) + '</b>/' + num(spark.cores) +
        ' &nbsp;·&nbsp; memory <b>' + num(spark.memoryused) + '</b>/' + num(spark.memory) + ' MB' +
        ' &nbsp;·&nbsp; active apps <b>' + num(spark.activeapps) + '</b>' +
      '</div>' : '';

    var rows = containers.map(function (c) {
      var cpu = num(c.cpuPercent).toFixed(1) + '%';
      var mem = c.memoryLimitBytes ? (fmtBytes(c.memoryBytes) + ' / ' + fmtBytes(c.memoryLimitBytes) + ' (' + num(c.memoryPercent).toFixed(0) + '%)') : fmtBytes(c.memoryBytes);
      return '<tr>' +
        '<td><span class="label ' + roleLabel(c.role) + '">' + esc(c.role) + '</span> ' + esc(c.name) + '</td>' +
        '<td>' + esc(c.state) + (c.exitCode != null ? ' (exit ' + c.exitCode + ')' : '') + '</td>' +
        '<td>' + cpu + '</td>' +
        '<td>' + mem + '</td>' +
        '<td class="text-muted" style="font-size:11px">&darr;' + fmtBytes(c.networkRxBytes) + ' &uarr;' + fmtBytes(c.networkTxBytes) + ' · ' + num(c.pids) + ' pid</td>' +
      '</tr>';
    }).join('');

    var table = containers.length ?
      '<table class="table table-condensed" style="margin-bottom:0">' +
        '<thead><tr><th>Node</th><th>State</th><th>CPU</th><th>Memory</th><th>I/O</th></tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
        '<tfoot><tr><th>Aggregate</th><th>' + num(agg.running) + '/' + num(agg.total) + '</th>' +
          '<th>' + num(agg.cpuPercent).toFixed(1) + '%</th><th>' + fmtBytes(agg.memoryBytes) + '</th><th></th></tr></tfoot>' +
      '</table>' :
      '<p class="text-muted" style="margin:0">' + (terminal ? 'Cluster released.' : 'Provisioning cluster…') + '</p>';

    $panel.html(
      '<div style="border:1px solid #e4e8ec;border-radius:4px;padding:10px 12px;margin-bottom:10px;background:#fbfcfd">' +
        head + sparkHtml + table +
      '</div>'
    ).show();
  }

  function poll() {
    $('#executionTabContent .tab-pane').each(function () {
      var $pane = $(this);
      var paneId = $pane.attr('id');
      if (!paneId) { return; }
      var st = paneState[paneId] || (paneState[paneId] = {});
      if (st.isSpark === false || st.done) { return; }

      var jobName = $pane.attr('data-job') || '';
      var build = paneBuild($pane);
      if (!jobName || !build) { return; }

      $.getJSON(BASE + 'data-engineering/spark-jobs/monitor-by-build', { job: jobName, build: build })
        .done(function (r) {
          if (!r || !r.ok) { return; }
          if (!r.isSpark) { st.isSpark = false; return; }
          st.isSpark = true;
          var $panel = $('#spark-' + paneId.replace('pane-', ''));
          if (!$panel.length) { return; }
          if (!r.run_id && !r.run) { $panel.html('<p class="text-muted" style="margin:0"><i class="fa fa-fire"></i> Spark job — cluster not started yet.</p>').show(); return; }
          render($panel, r);
          if (r.terminal) { st.done = true; }
        });
    });
  }

  $(function () {
    if (!$('#executionTabContent').length) { return; }
    setInterval(poll, POLL_MS);
    setTimeout(poll, 1200);
  });
})(window.jQuery);
