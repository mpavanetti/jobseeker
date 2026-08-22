<style>
  .executor-monitor-layout {
    width: 100%;
  }

  .executor-monitor-toolbar {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: space-between;
    margin-bottom: 14px;
  }

  .executor-monitor-scope {
    color: #64748b;
    font-size: 13px;
  }

  .executor-monitor-card {
    border: 1px solid #dbe3eb;
    box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
  }

  .executor-monitor-summary .info-box {
    min-height: 78px;
  }

  .executor-monitor-summary .info-box-icon {
    height: 78px;
    line-height: 78px;
  }

  .executor-monitor-summary .info-box-content {
    padding-top: 14px;
  }

  .executor-monitor-table th,
  .executor-monitor-table td {
    vertical-align: middle !important;
  }

  .executor-monitor-empty {
    color: #64748b;
    padding: 18px;
    text-align: center;
  }

  .executor-monitor-note {
    border-left: 4px solid var(--jobseeker-env-navbar-hover, #3c8dbc);
  }

  .executor-monitor-label {
    display: inline-block;
    min-width: 58px;
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>
      Jenkins Executor Monitor
      <small>Live workers, queue, and environment slot capacity.</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Job Management</a></li>
      <li class="active">Executor Monitor</li>
    </ol>
  </section>

  <section class="content executor-monitor-layout">
    <div class="executor-monitor-toolbar">
      <div>
        <button type="button" class="btn btn-primary" id="refreshExecutorMonitor"><i class="fa fa-refresh"></i> Refresh</button>
        <label class="switch" style="margin-left: 12px; padding-top: 3px; vertical-align: middle;">
          <input type="checkbox" id="executorMonitorAutoRefresh" checked>
          <span class="slider round"></span>
        </label>
        <b style="margin-left: 8px; font-size: 13px;">Auto Refresh</b>
      </div>
      <div class="executor-monitor-scope">
        Scope: <span class="label label-primary" id="executorMonitorScope">Loading</span>
        <span id="executorMonitorUpdated"></span>
      </div>
    </div>

    <div class="row executor-monitor-summary">
      <div class="col-lg-3 col-sm-6 col-xs-12">
        <div class="info-box">
          <span class="info-box-icon bg-aqua"><i class="fa fa-sliders"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Environment Slots</span>
            <span class="info-box-number" id="executorMonitorSlots">--</span>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6 col-xs-12">
        <div class="info-box">
          <span class="info-box-icon bg-green"><i class="fa fa-server"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Jenkins Executors</span>
            <span class="info-box-number" id="executorMonitorExecutors">--</span>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6 col-xs-12">
        <div class="info-box">
          <span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Queued Builds</span>
            <span class="info-box-number" id="executorMonitorQueue">--</span>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6 col-xs-12">
        <div class="info-box">
          <span class="info-box-icon bg-red"><i class="fa fa-plug"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Offline Nodes</span>
            <span class="info-box-number" id="executorMonitorOffline">--</span>
          </div>
        </div>
      </div>
    </div>

    <div class="callout callout-info executor-monitor-note">
      <h4><i class="fa fa-info-circle"></i> Parallelism model</h4>
      <p>Jenkins executors are the worker slots that run builds. JobSeeker applies environment slot limits before triggering builds, so DEV capacity and PROD capacity are tracked separately even when the current Jenkins controller still has one shared executor pool.</p>
    </div>

    <div class="row">
      <div class="col-md-6 col-xs-12">
        <div class="box box-primary executor-monitor-card">
          <div class="box-header with-border">
            <h3 class="box-title"><b>Environment Slot Usage</b></h3>
          </div>
          <div class="box-body table-responsive no-padding">
            <table class="table table-striped executor-monitor-table">
              <thead>
                <tr>
                  <th>Environment</th>
                  <th>Running</th>
                  <th>Queued</th>
                  <th>Used</th>
                  <th>Limit</th>
                  <th>Available</th>
                </tr>
              </thead>
              <tbody id="executorMonitorSlotRows">
                <tr><td colspan="6" class="executor-monitor-empty">Loading slot usage...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-6 col-xs-12">
        <div class="box box-primary executor-monitor-card">
          <div class="box-header with-border">
            <h3 class="box-title"><b>Build Queue</b></h3>
          </div>
          <div class="box-body table-responsive no-padding">
            <table class="table table-striped executor-monitor-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Job</th>
                  <th>Environment</th>
                  <th>Reason</th>
                </tr>
              </thead>
              <tbody id="executorMonitorQueueRows">
                <tr><td colspan="4" class="executor-monitor-empty">Loading queue...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-xs-12">
        <div class="box box-primary executor-monitor-card">
          <div class="box-header with-border">
            <h3 class="box-title"><b>Jenkins Executor Details</b></h3>
          </div>
          <div class="box-body table-responsive no-padding">
            <table class="table table-striped executor-monitor-table">
              <thead>
                <tr>
                  <th>Node</th>
                  <th>Executor</th>
                  <th>Status</th>
                  <th>Environment</th>
                  <th>Build</th>
                </tr>
              </thead>
              <tbody id="executorMonitorExecutorRows">
                <tr><td colspan="5" class="executor-monitor-empty">Loading executors...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script type="text/javascript">
  (function() {
    var refreshTimer = null;

    function appUrl(path) {
      var appBase = typeof baseURL !== 'undefined' ? baseURL : '';
      return appBase.replace(/\/+$/, '/') + path.replace(/^\/+/, '');
    }

    function escapeHtml(value) {
      return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function number(value) {
      var parsed = parseInt(value, 10);
      return isNaN(parsed) ? 0 : parsed;
    }

    function selectedEnvironment() {
      var value = $('#globalEnvironmentSelector').val() || 'all';

      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
        return window.JobSeekerGlobalEnvironment.normalize(value);
      }

      return String(value || 'all').toUpperCase();
    }

    function scopedUrl(path) {
      var environment = selectedEnvironment();
      var url = appUrl(path);

      if (! environment || environment === 'all') {
        return url;
      }

      return url + (url.indexOf('?') === -1 ? '?' : '&') + 'environment=' + encodeURIComponent(environment);
    }

    function environmentLabel(environment) {
      environment = environment || 'Unknown';
      return '<span class="label label-primary executor-monitor-label">' + escapeHtml(environment) + '</span>';
    }

    function renderRows(selector, rows, emptyMessage, colspan) {
      $(selector).html(rows.length ? rows.join('') : '<tr><td colspan="' + colspan + '" class="executor-monitor-empty">' + escapeHtml(emptyMessage) + '</td></tr>');
    }

    function slotRows(environments) {
      var rows = [];

      $.each(environments || {}, function(environment, row) {
        rows.push('<tr>' +
          '<td>' + environmentLabel(environment) + '</td>' +
          '<td>' + number(row.running) + '</td>' +
          '<td>' + number(row.queued) + '</td>' +
          '<td>' + number(row.active) + '</td>' +
          '<td>' + (number(row.limit) > 0 ? number(row.limit) : 'Unlimited') + '</td>' +
          '<td>' + (row.available === null ? 'Unlimited' : number(row.available)) + '</td>' +
        '</tr>');
      });

      return rows;
    }

    function executorRows(executors) {
      return $.map(executors || [], function(executor) {
        var status = executor.offline ? '<span class="label label-danger">Offline</span>' : (executor.idle ? '<span class="label label-success">Idle</span>' : '<span class="label label-warning">Busy</span>');
        var build = executor.build ? escapeHtml(executor.build) : '<span class="text-muted">No active build</span>';

        return '<tr>' +
          '<td>' + escapeHtml(executor.node || 'Jenkins node') + '</td>' +
          '<td>#' + number(executor.executor) + '</td>' +
          '<td>' + status + '</td>' +
          '<td>' + (executor.environment ? environmentLabel(executor.environment) : '<span class="label label-default">Idle</span>') + '</td>' +
          '<td>' + build + '</td>' +
        '</tr>';
      });
    }

    function queueRows(queue) {
      return $.map(queue || [], function(item) {
        return '<tr>' +
          '<td>' + number(item.id) + '</td>' +
          '<td>' + escapeHtml(item.job || 'Unknown job') + '</td>' +
          '<td>' + (item.environment ? environmentLabel(item.environment) : '<span class="label label-default">Unknown</span>') + '</td>' +
          '<td>' + escapeHtml(item.why || 'Waiting for executor') + '</td>' +
        '</tr>';
      });
    }

    function loadExecutorMonitor() {
      $('#refreshExecutorMonitor').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Refreshing');

      $.getJSON(scopedUrl('jenkins/executorMonitor')).done(function(payload) {
        var environments = payload.environments || {};
        var selected = selectedEnvironment();
        var slots = selected && selected !== 'all' ? environments[selected] || {running: 0, queued: 0, active: 0, limit: payload.defaultLimit || 1, available: payload.defaultLimit || 1} : null;
        var slotActive = 0;
        var slotLimit = 0;
        var queued = 0;

        if (slots) {
          slotActive = number(slots.active);
          slotLimit = number(slots.limit);
          queued = number(slots.queued);
        } else {
          $.each(environments, function(environment, row) {
            slotActive += number(row.active);
            queued += number(row.queued);
            if (number(row.limit) > 0) {
              slotLimit += number(row.limit);
            }
          });
        }

        $('#executorMonitorScope').text(selected && selected !== 'all' ? selected : 'All environments');
        $('#executorMonitorUpdated').text('Updated ' + moment().format('h:mm:ss a'));
        $('#executorMonitorSlots').text(slotLimit > 0 ? slotActive + ' / ' + slotLimit : slotActive + ' / unlimited');
        $('#executorMonitorExecutors').text(number(payload.global && payload.global.busyExecutors) + ' / ' + number(payload.global && payload.global.totalExecutors));
        $('#executorMonitorQueue').text(queued);
        $('#executorMonitorOffline').text(number(payload.global && payload.global.offlineNodes));

        renderRows('#executorMonitorSlotRows', slotRows(environments), 'No environment slot data is available.', 6);
        renderRows('#executorMonitorQueueRows', queueRows(payload.queue), 'The Jenkins queue is empty.', 4);
        renderRows('#executorMonitorExecutorRows', executorRows(payload.executors), 'No Jenkins executors were returned.', 5);
      }).fail(function(xhr) {
        var message = xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to load Jenkins executor monitor.';
        $('#executorMonitorUpdated').text(message);
        toastr.error(message, 'Jenkins Executor Monitor');
      }).always(function() {
        $('#refreshExecutorMonitor').prop('disabled', false).html('<i class="fa fa-refresh"></i> Refresh');
      });
    }

    function syncAutoRefresh() {
      if (refreshTimer) {
        window.clearInterval(refreshTimer);
        refreshTimer = null;
      }

      if ($('#executorMonitorAutoRefresh').is(':checked')) {
        refreshTimer = window.setInterval(loadExecutorMonitor, 15000);
      }
    }

    $(document).ready(function() {
      loadExecutorMonitor();
      syncAutoRefresh();
      $('#refreshExecutorMonitor').on('click', loadExecutorMonitor);
      $('#executorMonitorAutoRefresh').on('change', syncAutoRefresh);
      $(document).on('jobseeker:environment-change', loadExecutorMonitor);
    });
  })();
</script>