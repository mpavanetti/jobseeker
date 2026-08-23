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

  .executor-monitor-label-list {
    color: #64748b;
    font-size: 12px;
    line-height: 1.4;
    word-break: break-word;
  }

  .executor-setup-helper .form-inline .form-group {
    margin-bottom: 10px;
    margin-right: 10px;
  }

  .executor-setup-summary {
    color: #475569;
    font-size: 13px;
    line-height: 1.45;
    margin-top: 6px;
  }

  .executor-setup-code {
    background: #0f172a;
    border: 0;
    border-radius: 6px;
    color: #dbeafe;
    font-size: 12px;
    min-height: 96px;
    white-space: pre-wrap;
  }

  .executor-setup-notes {
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
    margin-top: 8px;
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
            <span class="info-box-text">JobSeeker Slots</span>
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
            <span class="info-box-text">Worker Agents</span>
            <span class="info-box-number" id="executorMonitorAgents">--</span>
          </div>
        </div>
      </div>
    </div>

    <div class="callout callout-info executor-monitor-note">
      <h4><i class="fa fa-info-circle"></i> Parallelism model</h4>
      <p>JobSeeker slots are trigger limits per environment. Jenkins executors are the actual Jenkins worker capacity across the controller and online agent nodes. Per-environment agent capacity is shown in the Environment Slot Usage and Worker Nodes tables.</p>
      <p id="executorMonitorRoutingDetail">Loading environment-agent routing state...</p>
    </div>

    <div class="box box-primary executor-monitor-card executor-setup-helper">
      <div class="box-header with-border">
        <h3 class="box-title"><b>Agent Setup Helper</b></h3>
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" id="refreshAgentSetupHelper"><i class="fa fa-refresh"></i></button>
        </div>
      </div>
      <div class="box-body">
        <div class="form-inline">
          <div class="form-group">
            <label for="agentSetupMode">Deployment</label>
            <select class="form-control input-sm" id="agentSetupMode">
              <option value="docker">Local Docker / VM</option>
              <option value="kubernetes">Kubernetes</option>
            </select>
          </div>
          <div class="form-group">
            <label for="agentSetupCpu">CPU cores</label>
            <input type="number" min="1" class="form-control input-sm" id="agentSetupCpu" placeholder="Auto">
          </div>
          <div class="form-group">
            <label for="agentSetupMemory">Memory MB</label>
            <input type="number" min="256" class="form-control input-sm" id="agentSetupMemory" placeholder="Auto">
          </div>
          <button type="button" class="btn btn-default btn-sm" id="calculateAgentSetupHelper"><i class="fa fa-calculator"></i> Calculate</button>
        </div>
        <div class="executor-setup-summary" id="agentSetupSummary">Loading setup recommendation...</div>
        <div class="row" style="margin-top: 12px;">
          <div class="col-md-7 col-xs-12">
            <div class="table-responsive no-padding">
              <table class="table table-striped executor-monitor-table">
                <thead>
                  <tr>
                    <th>Environment</th>
                    <th>Label</th>
                    <th>Current</th>
                    <th>Recommended</th>
                  </tr>
                </thead>
                <tbody id="agentSetupRows">
                  <tr><td colspan="4" class="executor-monitor-empty">Loading setup helper...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-md-5 col-xs-12">
            <label>.env values</label>
            <pre class="executor-setup-code" id="agentSetupEnv">Loading...</pre>
            <label>Apply path</label>
            <pre class="executor-setup-code" id="agentSetupCommands">Loading...</pre>
            <div class="executor-setup-notes" id="agentSetupNotes"></div>
          </div>
        </div>
      </div>
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
                  <th>Agent Nodes</th>
                  <th>Agent Executors</th>
                  <th>Agent Available</th>
                </tr>
              </thead>
              <tbody id="executorMonitorSlotRows">
                <tr><td colspan="9" class="executor-monitor-empty">Loading slot usage...</td></tr>
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
            <h3 class="box-title"><b>Worker Nodes</b></h3>
          </div>
          <div class="box-body table-responsive no-padding">
            <table class="table table-striped executor-monitor-table">
              <thead>
                <tr>
                  <th>Node</th>
                  <th>Environment</th>
                  <th>Status</th>
                  <th>Executors</th>
                  <th>Available</th>
                  <th>Labels</th>
                </tr>
              </thead>
              <tbody id="executorMonitorNodeRows">
                <tr><td colspan="6" class="executor-monitor-empty">Loading worker nodes...</td></tr>
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
            <h3 class="box-title"><b>Live Executor Details</b></h3>
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
        value = window.JobSeekerGlobalEnvironment.normalize(value);
        return String(value || '').toLowerCase() === 'all' ? 'all' : value;
      }

      return String(value || 'all').toLowerCase() === 'all' ? 'all' : String(value || 'all').toUpperCase();
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

    function labelList(labels) {
      labels = $.map(labels || [], function(label) { return label ? escapeHtml(label) : null; });
      return labels.length ? '<span class="executor-monitor-label-list">' + labels.join(', ') + '</span>' : '<span class="text-muted">None</span>';
    }

    function scopedRows(rows, selected) {
      if (! selected || selected === 'all') {
        return rows || [];
      }

      return $.grep(rows || [], function(row) {
        return String(row.environment || '').toUpperCase() === selected;
      });
    }

    function scopedEnvironments(environments, selected) {
      if (! selected || selected === 'all') {
        return environments || {};
      }

      var scoped = {};
      scoped[selected] = environments && environments[selected] ? environments[selected] : {};
      return scoped;
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
          '<td>' + number(row.onlineAgentNodes) + ' / ' + number(row.agentNodes) + '</td>' +
          '<td>' + number(row.busyAgentExecutors) + ' / ' + number(row.agentExecutors) + '</td>' +
          '<td>' + number(row.availableAgentExecutors) + '</td>' +
        '</tr>');
      });

      return rows;
    }

    function nodeRows(nodes) {
      return $.map(nodes || [], function(node) {
        var status = node.offline ? '<span class="label label-danger">Offline</span>' : (node.temporarilyOffline ? '<span class="label label-warning">Temporarily offline</span>' : '<span class="label label-success">Online</span>');

        return '<tr>' +
          '<td>' + escapeHtml(node.node || 'Jenkins node') + '</td>' +
          '<td>' + (node.environment ? environmentLabel(node.environment) : '<span class="label label-default">Shared</span>') + '</td>' +
          '<td>' + status + '</td>' +
          '<td>' + number(node.busyExecutors) + ' / ' + number(node.executors) + '</td>' +
          '<td>' + number(node.availableExecutors) + '</td>' +
          '<td>' + labelList(node.labels) + '</td>' +
        '</tr>';
      });
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

    function routingDetail(payload, selected, slots) {
      var enabled = payload && payload.environmentAgentsEnabled === true;
      var labels = payload && payload.environmentAgentLabels ? payload.environmentAgentLabels : {};
      var badge = enabled ? '<span class="label label-success">Agent routing enabled</span>' : '<span class="label label-default">Agent routing disabled</span>';

      if (selected && selected !== 'all') {
        var label = labels[selected] || ('jobseeker-env-' + String(selected).toLowerCase());
        var agents = slots ? number(slots.onlineAgentNodes) + ' / ' + number(slots.agentNodes) : '0 / 0';
        var executors = slots ? number(slots.availableAgentExecutors) + ' / ' + number(slots.agentExecutors) : '0 / 0';
        return badge + ' ' + escapeHtml(selected) + ' routes to <code>' + escapeHtml(label) + '</code>. Online workers: ' + agents + '. Available worker executors: ' + executors + '.';
      }

      return badge + ' All environments are shown. The executor total includes controller executors plus any online agent executors.';
    }

    function setupHelperRows(rows) {
      return $.map(rows || [], function(row) {
        var current = 'slots ' + number(row.currentSlotLimit) + ', agents ' + number(row.onlineAgentNodes) + ' / ' + number(row.currentAgentNodes) + ', executors ' + number(row.currentAgentExecutors);
        var recommended = number(row.recommendedAgents) + ' agent(s), ' + number(row.recommendedExecutorsPerAgent) + ' executor(s)/agent, slots ' + number(row.recommendedSlotLimit);
        if (! row.service && number(row.recommendedSlotLimit) > 0) {
          recommended += '<br><span class="text-muted">Custom worker template needed</span>';
        }
        if (! row.label) {
          recommended += '<br><span class="text-muted">No routing label configured</span>';
        }

        return '<tr>' +
          '<td>' + environmentLabel(row.environment) + '</td>' +
          '<td>' + (row.label ? '<code>' + escapeHtml(row.label) + '</code>' : '<span class="text-muted">Not configured</span>') + '</td>' +
          '<td>' + escapeHtml(current) + '</td>' +
          '<td>' + recommended + '</td>' +
        '</tr>';
      });
    }

    function renderAgentSetupHelper(payload) {
      var detected = payload.detected || {};
      var recommendation = payload.recommendation || {};
      var mode = payload.mode === 'kubernetes' ? 'Kubernetes' : 'Local Docker / VM';
      var routing = recommendation.routingEnabled ? '<span class="label label-success">Routing enabled</span>' : '<span class="label label-default">Routing disabled</span>';
      var summary = routing + ' ' + mode + ': detected ' + number(detected.cpuCores) + ' CPU core(s) from ' + escapeHtml(detected.cpuSource || 'unknown') + ', ' + number(detected.memoryMb) + ' MB memory from ' + escapeHtml(detected.memorySource || 'unknown') + '. Recommended build budget: ' + number(recommendation.buildBudget) + ', controller executors: ' + number(recommendation.controllerExecutors) + ', agent executors: ' + number(recommendation.agentExecutors) + '. Current online Jenkins executors: ' + number(recommendation.currentJenkinsExecutors) + '.';

      $('#agentSetupSummary').html(summary);
      renderRows('#agentSetupRows', setupHelperRows(payload.environments), 'No environment setup recommendation is available.', 4);
      $('#agentSetupEnv').text((payload.env || []).join('\n') || 'No environment variables recommended.');
      $('#agentSetupCommands').text((payload.commands || []).join('\n') || 'No commands recommended.');
      $('#agentSetupNotes').html($.map(payload.notes || [], function(note) {
        return '<div><i class="fa fa-circle-o"></i> ' + escapeHtml(note) + '</div>';
      }).join(''));
    }

    function loadAgentSetupHelper() {
      var params = {mode: $('#agentSetupMode').val() || 'docker'};
      var cpu = parseInt($('#agentSetupCpu').val(), 10);
      var memory = parseInt($('#agentSetupMemory').val(), 10);

      if (! isNaN(cpu) && cpu > 0) {
        params.cpu = cpu;
      }
      if (! isNaN(memory) && memory > 0) {
        params.memoryMb = memory;
      }

      $('#agentSetupSummary').text('Calculating setup recommendation...');
      $.getJSON(appUrl('jenkins/agentSetupHelper'), params).done(renderAgentSetupHelper).fail(function(xhr) {
        var message = xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to calculate agent setup.';
        $('#agentSetupSummary').text(message);
        renderRows('#agentSetupRows', [], message, 4);
        $('#agentSetupEnv').text('');
        $('#agentSetupCommands').text('');
        $('#agentSetupNotes').text('');
        toastr.error(message, 'Agent Setup Helper');
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
        var selected = selectedEnvironment();
        var environments = payload.environments || {};
        var visibleEnvironments = scopedEnvironments(environments, selected);
        var slots = selected && selected !== 'all' ? environments[selected] || {running: 0, queued: 0, active: 0, limit: payload.defaultLimit || 1, available: payload.defaultLimit || 1} : null;
        var visibleExecutors = scopedRows(payload.executors, selected);
        var slotActive = 0;
        var slotLimit = 0;
        var queued = 0;
        var agentNodes = 0;
        var onlineAgentNodes = 0;

        if (slots) {
          slotActive = number(slots.active);
          slotLimit = number(slots.limit);
          queued = number(slots.queued);
          agentNodes = number(slots.agentNodes);
          onlineAgentNodes = number(slots.onlineAgentNodes);
        } else {
          $.each(environments, function(environment, row) {
            slotActive += number(row.active);
            queued += number(row.queued);
            agentNodes += number(row.agentNodes);
            onlineAgentNodes += number(row.onlineAgentNodes);
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
        $('#executorMonitorAgents').text(onlineAgentNodes + ' / ' + agentNodes);
        $('#executorMonitorRoutingDetail').html(routingDetail(payload, selected, slots));

        renderRows('#executorMonitorSlotRows', slotRows(visibleEnvironments), 'No environment slot data is available.', 9);
        renderRows('#executorMonitorQueueRows', queueRows(scopedRows(payload.queue, selected)), 'The Jenkins queue is empty.', 4);
        renderRows('#executorMonitorNodeRows', nodeRows(scopedRows(payload.nodes, selected)), 'No worker nodes were returned for this scope.', 6);
        renderRows('#executorMonitorExecutorRows', executorRows(visibleExecutors), 'No Jenkins executors were returned for this scope.', 5);
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
      loadAgentSetupHelper();
      syncAutoRefresh();
      $('#refreshExecutorMonitor').on('click', loadExecutorMonitor);
      $('#refreshAgentSetupHelper, #calculateAgentSetupHelper').on('click', loadAgentSetupHelper);
      $('#agentSetupMode').on('change', loadAgentSetupHelper);
      $('#executorMonitorAutoRefresh').on('change', syncAutoRefresh);
      $(document).on('jobseeker:environment-change', loadExecutorMonitor);
    });
  })();
</script>