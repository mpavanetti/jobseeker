/* Spark job authoring panel on the Create Job screen (#runSparkJob).
 * Saves a PySpark job straight from this panel: it becomes a spark_jobs row plus
 * a real Jenkins job (see SparkJenkinsTrait) that provisions an ephemeral Spark
 * cluster on trigger. jQuery + the global CSRF ajax prefilter are assumed. */
(function ($) {
  'use strict';

  var cfg = window.jobseekerSparkAuthoring || {};
  var BASE = cfg.baseUrl || (window.baseURL || '/');
  var runtimesByKey = {};
  var openVsCodeEnabled = false;
  var loadedOptionsEnv = null;
  var allClusters = [];

  var STARTER = [
    'from connect import get_spark', '', '',
    'def main() -> None:',
    '    spark = get_spark("jobseeker-job")',
    '    try:',
    '        print("rows:", spark.range(1000).count())',
    '    finally:',
    '        spark.stop()', '', '',
    'if __name__ == "__main__":',
    '    main()', ''
  ].join('\n');

  function currentMode() { return $('input[name="sparkJobMode"]:checked').val() || 'batch'; }

  function $id(id) { return document.getElementById(id); }
  function val(id) { var el = $id(id); return el ? el.value : ''; }
  function trimVal(id) { return $.trim(val(id)); }

  function alertBox(kind, html) {
    var $a = $('#sparkJobAlert');
    if (!html) { $a.hide(); return; }
    $a.attr('class', 'alert alert-' + kind).html(html).show();
  }

  function currentSource() {
    return $('input[name="sparkJobSource"]:checked').val() || 'inline';
  }

  // The Spark panel has no environment picker of its own: it always follows the
  // page-wide environment selector (#environment). Keep the hidden field and the
  // read-only label in sync with it.
  function pageEnv() { return $.trim($('#environment').val() || ''); }
  function syncSparkEnv() {
    var env = pageEnv();
    $('#sparkJobEnvironment').val(env);
    $('#sparkJobEnvironmentLabel').text(env || '—');
  }

  function syncSourceUi() {
    var inline = currentSource() === 'inline';
    $('#sparkJobInlineWrap').toggle(inline);
    $('#sparkJobRepoWrap').toggle(!inline);
    var editing = trimVal('sparkJobId') !== '';
    $('#sparkJobVsCodeBtn').toggle(inline && openVsCodeEnabled && editing);
  }

  function updateJenkinsNamePreview() {
    var name = trimVal('sparkJobName').replace(/[^A-Za-z0-9._/-]+/g, '-').replace(/^[-/]+|[-/]+$/g, '');
    var env = (trimVal('sparkJobEnvironment') || 'ENV').toUpperCase().replace(/[^A-Za-z0-9]+/g, '');
    $('#sparkJenkinsNamePreview').text((name || 'name') + (env ? '-' + env : ''));
  }

  function fillSelect($sel, items, valueKey, labelFn, placeholder) {
    var cur = $sel.val();
    $sel.empty().append($('<option>').val('').text(placeholder));
    items.forEach(function (it) {
      $sel.append($('<option>').val(String(it[valueKey])).text(labelFn(it)));
    });
    if (cur) { $sel.val(cur); }
  }

  function refreshRuntimeLabel() {
    var $c = $('#sparkJobCluster');
    var rk = $c.find('option:selected').data('runtime') || '';
    var rt = runtimesByKey[rk];
    $('#sparkJobRuntimeLabel').text(rt ? (rt.display_name + ' (' + rt.image_repository + ':' + rt.image_tag + ')') : '—');
  }

  function renderClusters() {
    var $c = $('#sparkJobCluster');
    var keep = $c.val();
    var mode = currentMode();
    var list = allClusters.filter(function (cl) {
      return mode === 'interactive' ? cl.lifecycle === 'persistent' : true;
    });
    $c.empty().append($('<option>').val('').text(
      list.length ? '-- select compute --'
        : (mode === 'interactive' ? 'No All-Purpose clusters — create one under Compute'
                                  : 'No clusters in this environment — create one')));
    list.forEach(function (cl) {
      var tag = cl.lifecycle === 'persistent' ? ' [All-Purpose]' : ' [Job]';
      $('<option>').val(String(cl.id))
        .text(cl.name + tag + '  ·  ' + cl.worker_cores + ' vCPU / ' + cl.worker_memory_mb + ' MB workers')
        .attr('data-runtime', cl.runtime_key).appendTo($c);
    });
    if (keep) { $c.val(keep); }
    refreshRuntimeLabel();
  }

  function loadOptions(force) {
    var env = trimVal('sparkJobEnvironment');
    if (!force && env === loadedOptionsEnv) { return $.Deferred().resolve().promise(); }
    return $.getJSON(BASE + 'jobCreation/sparkJobOptions', { environment: env }).done(function (r) {
      if (!r || !r.ok) { return; }
      loadedOptionsEnv = env;
      openVsCodeEnabled = !!r.openVsCodeEnabled;
      runtimesByKey = {};
      (r.runtimes || []).forEach(function (rt) { runtimesByKey[rt.runtime_key] = rt; });
      allClusters = r.clusters || [];
      renderClusters();
      syncSourceUi();
    });
  }

  function loadSourcePreview(id) {
    var $pre = $('#sparkJobSourcePreview');
    if (!id) { $pre.text('Save the job, then Open in VS Code to author it.'); $id('sparkJobInlineCode').value = ''; return; }
    $.getJSON(BASE + 'data-engineering/spark-jobs/source/' + id).done(function (r) {
      if (r && r.ok) {
        $pre.text(r.source || STARTER);
        $id('sparkJobInlineCode').value = r.source || STARTER;
      }
    });
  }

  function collect() {
    return {
      id: trimVal('sparkJobId'),
      name: trimVal('sparkJobName'),
      environment: pageEnv(),
      group_name: trimVal('sparkJobGroup') || 'General',
      cluster_id: trimVal('sparkJobCluster'),
      mode: currentMode(),
      source_type: currentSource(),
      entry_point: trimVal('sparkJobEntryPoint'),
      inline_code: currentSource() === 'inline' ? ($.trim(val('sparkJobInlineCode')) ? val('sparkJobInlineCode') : STARTER) : '',
      application_args: trimVal('sparkJobArgs'),
      spark_submit_conf_json: $.trim(val('sparkJobConf')),
      workers: trimVal('sparkJobWorkers'),
      is_active: $id('sparkJobActive').checked ? 1 : 0
    };
  }

  function applyJob(job, runs) {
    $id('sparkJobId').value = job.id;
    $id('sparkJobName').value = job.name || '';
    $id('sparkJobGroup').value = job.group_name || 'General';
    $id('sparkJobArgs').value = job.application_args || '';
    $id('sparkJobConf').value = job.spark_submit_conf_json || '';
    $id('sparkJobWorkers').value = job.workers || '';
    $id('sparkJobActive').checked = String(job.is_active) !== '0';
    $('input[name="sparkJobSource"][value="' + (job.source_type === 'repository' ? 'repository' : 'inline') + '"]').prop('checked', true);
    $('input[name="sparkJobMode"][value="' + (job.mode === 'interactive' ? 'interactive' : 'batch') + '"]').prop('checked', true);
    if (job.source_type === 'inline' && job.inline_code) { $id('sparkJobInlineCode').value = job.inline_code; }
    if (job.entry_point) { $id('sparkJobEntryPoint').value = job.entry_point; }
    loadSourcePreview(job.id);
    // Point the page-wide selector at the job's environment so the whole screen
    // (and this panel) follow it.
    var $pageEnv = $('#environment');
    if ($pageEnv.length && job.environment && $pageEnv.find('option[value="' + job.environment + '"]').length) {
      $pageEnv.val(job.environment);
    }
    syncSparkEnv();
    $('#sparkJobDeleteBtn').show();
    loadOptions(true).done(function () { renderClusters(); $('#sparkJobCluster').val(String(job.cluster_id)); refreshRuntimeLabel(); syncSourceUi(); });
    renderRuns(runs || []);
    updateJenkinsNamePreview();
  }

  function renderRuns(runs) {
    var $box = $('#sparkJobRunsBox'), $body = $('#sparkJobRunsBody');
    if (!runs.length) { $box.hide(); return; }
    $body.empty();
    runs.forEach(function (r) {
      $('<tr>').append(
        $('<td>').append($('<code>').text(r.run_key || ('#' + r.id))),
        $('<td>').html('<span class="label label-' + statusClass(r.status) + '">' + r.status + '</span>'),
        $('<td>').text(r.worker_count || 0),
        $('<td>').text(r.started_at || ''),
        $('<td>').text(r.jenkins_build_number ? ('#' + r.jenkins_build_number) : '')
      ).appendTo($body);
    });
    $box.show();
  }

  function statusClass(s) {
    return ({ SUCCEEDED: 'success', RUNNING: 'primary', PROVISIONING: 'info', QUEUED: 'default',
      FAILED: 'danger', TIMED_OUT: 'danger', CANCELLED: 'warning' })[s] || 'default';
  }

  function reset() {
    $id('sparkJobId').value = '';
    $id('sparkJobName').value = '';
    $id('sparkJobArgs').value = '';
    $id('sparkJobConf').value = '';
    $id('sparkJobWorkers').value = '';
    $id('sparkJobActive').checked = true;
    $('input[name="sparkJobSource"][value="inline"]').prop('checked', true);
    $('input[name="sparkJobMode"][value="batch"]').prop('checked', true);
    $('#sparkJobDeleteBtn').hide();
    $('#sparkJobRunsBox').hide();
    loadSourcePreview('');
    renderClusters();
    alertBox(null);
    syncSourceUi();
    updateJenkinsNamePreview();
  }

  function triggerJenkins(jobName, environment) {
    var csrf = window.jobseekerCsrf || {};
    var path = 'job/' + String(jobName).split('/').map(encodeURIComponent).join('/job/') +
      '/buildWithParameters?ENVIRONMENT=' + encodeURIComponent(environment || 'DEV');
    return $.ajax({
      url: BASE + 'jenkins/proxy?path=' + encodeURIComponent(path) + '&' + encodeURIComponent(csrf.name || 'csrf_test_name') + '=' + encodeURIComponent(csrf.hash || ''),
      method: 'POST'
    });
  }

  function save(opts) {
    opts = opts || {};
    var $btn = $('#send, #saveAndTrigger').prop('disabled', true);
    alertBox('info', 'Saving…');
    $.ajax({ url: BASE + 'jobCreation/saveSparkJob', method: 'POST', dataType: 'json', data: collect() })
      .done(function (r) {
        if (!r || !r.ok) { alertBox('danger', (r && r.message) || 'Save failed.'); return; }
        $id('sparkJobId').value = r.id;
        $('#sparkJobDeleteBtn').show();
        syncSourceUi();
        loadSourcePreview(r.id);
        loadRuns(r.id);
        var jname = $('<i>').text(r.jenkins_job_name).html();
        if (opts.thenTrigger) {
          triggerJenkins(r.jenkins_job_name, trimVal('sparkJobEnvironment'))
            .done(function () {
              alertBox('success', r.message + ' Build queued for <code>' + jname + '</code> — watch it in <a href="' + BASE + 'jobExecution?job=' + encodeURIComponent(r.jenkins_job_name) + '&environment=' + encodeURIComponent(trimVal('sparkJobEnvironment')) + '">Job Execution</a>.');
            })
            .fail(function () {
              alertBox('warning', r.message + ' Jenkins job <code>' + jname + '</code> was saved, but the build could not be queued — trigger it from Job Execution.');
            });
        } else {
          alertBox('success', r.message + ' Jenkins job <code>' + jname + '</code>. Trigger it from <a href="' + BASE + 'jobExecution?job=' + encodeURIComponent(r.jenkins_job_name) + '&environment=' + encodeURIComponent(trimVal('sparkJobEnvironment')) + '">Job Execution</a>.');
        }
      })
      .fail(function (xhr) {
        var m = 'Save failed.';
        try { m = JSON.parse(xhr.responseText).message || m; } catch (e) {}
        alertBox('danger', m);
      })
      .always(function () { $btn.prop('disabled', false); });
  }

  function loadRuns(id) {
    if (!id) { return; }
    $.getJSON(BASE + 'jobCreation/sparkJob/' + id).done(function (r) {
      if (r && r.ok) { renderRuns(r.recentRuns || []); }
    });
  }

  function openVsCode() {
    var id = trimVal('sparkJobId');
    if (!id) { alertBox('warning', 'Save the job first, then open it in VS Code.'); return; }
    var $btn = $('#sparkJobVsCodeBtn').prop('disabled', true);
    $.ajax({ url: BASE + 'data-engineering/spark-jobs/develop', method: 'POST', dataType: 'json',
      data: { id: id, inline_code: val('sparkJobInlineCode') } })
      .done(function (r) {
        if (r && r.ok && r.url) {
          window.open(r.url, '_blank', 'noopener');
          loadSourcePreview(id);
          if (r.persistent && r.jupyterVsCodeUrl) {
            alertBox('info', 'Workspace opened. In VS Code, attach the notebook kernel to the cluster Jupyter server: <code>' + $('<i>').text(r.jupyterVsCodeUrl).html() + '</code> (also in the workspace README).');
          } else {
            alertBox('info', 'Workspace opened. Edit <code>job.py</code> / <code>notebook.ipynb</code> there, then Save the job.');
          }
        } else { alertBox('danger', (r && r.message) || 'Could not open VS Code.'); }
      })
      .fail(function () { alertBox('danger', 'Could not open VS Code.'); })
      .always(function () { $btn.prop('disabled', false); });
  }

  function del() {
    var id = trimVal('sparkJobId');
    if (!id || !window.confirm('Delete this Spark job and its Jenkins job?')) { return; }
    $.ajax({ url: BASE + 'jobCreation/deleteSparkJob', method: 'POST', dataType: 'json', data: { id: id } })
      .done(function (r) {
        if (r && r.ok) { reset(); alertBox('success', 'Spark job deleted.'); }
        else { alertBox('danger', (r && r.message) || 'Delete failed.'); }
      });
  }

  $(function () {
    if (!$id('runSparkJob')) { return; }

    $('#environment').on('change', function () {
      syncSparkEnv();
      loadOptions(true).done(refreshRuntimeLabel);
      updateJenkinsNamePreview();
    });
    $('#sparkJobCluster').on('change', refreshRuntimeLabel);
    $('#sparkJobName').on('input', updateJenkinsNamePreview);
    $('input[name="sparkJobSource"]').on('change', syncSourceUi);
    $('input[name="sparkJobMode"]').on('change', renderClusters);
    $('#sparkJobVsCodeBtn').on('click', openVsCode);
    $('#sparkJobDeleteBtn').on('click', del);

    // Load dropdown data the first time the Spark panel is shown.
    var loadedOnce = false;
    function maybeLoad() {
      if (loadedOnce || $('#runSparkJob').is(':hidden')) { return; }
      loadedOnce = true;
      syncSparkEnv();
      loadOptions(true);
    }
    // A job is exactly one of Linux / Python / ETL / Spark. Turning Spark on
    // turns the other execution families off (the view does the reverse).
    $('#sparkJob').on('change', function () {
      if (this.checked) {
        ['#linuxCommand', '#winCommand'].forEach(function (sel) {
          var $x = $(sel);
          if ($x.length && $x.is(':checked')) { $x.prop('checked', false).trigger('change'); }
        });
        $('.job-execution-option').removeClass('active');
      }
      setTimeout(maybeLoad, 0);
    });
    $(document).on('click', '.job-config-chip, .job-option-card', function () { setTimeout(maybeLoad, 50); });
    maybeLoad();

    // Deep-link: /jobCreation?sparkJob=<id> opens the panel on that job.
    var m = /[?&]sparkJob=(\d+)/.exec(window.location.search);
    if (m) {
      $('#sparkJob').prop('checked', true).trigger('change');
      $.getJSON(BASE + 'jobCreation/sparkJob/' + m[1]).done(function (r) {
        if (r && r.ok) { applyJob(r.job, r.recentRuns); }
      });
    }

    // When Spark is the chosen execution type, the main "Create Job" / "Create
    // And Trigger" buttons drive the Spark save (the platform send() handler has
    // no Spark path).
    var lastSubmitId = '';
    $('#send, #saveAndTrigger').on('click', function () { lastSubmitId = this.id; });
    $('#InsertDbSettings').on('submit', function (e) {
      if (!$('#sparkJob').is(':checked')) { return; }
      e.preventDefault();
      e.stopImmediatePropagation();
      save({ thenTrigger: lastSubmitId === 'saveAndTrigger' });
    });

    syncSparkEnv();
    syncSourceUi();
    updateJenkinsNamePreview();
  });
})(jQuery);
