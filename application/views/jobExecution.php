<style>
  .execution-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .execution-help {
    color: #777;
    margin-top: 8px;
  }

  .execution-job-list {
    border: 1px solid #d2d6de;
    border-radius: 4px;
    max-height: 310px;
    min-height: 260px;
    overflow: auto;
  }

  .execution-job-option {
    border-bottom: 1px solid #f4f4f4;
    cursor: pointer;
    display: block;
    font-weight: normal;
    margin: 0;
    padding: 9px 10px;
  }

  .execution-job-option:hover {
    background: #f9fafc;
  }

  .execution-job-option input {
    margin-right: 8px;
  }

  .execution-job-option.disabled {
    color: #999;
    cursor: not-allowed;
  }

  .execution-job-name {
    display: inline-block;
    max-width: calc(100% - 85px);
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
    white-space: nowrap;
  }

  .execution-job-option .label {
    min-width: 54px;
  }

  .execution-summary .info-box {
    min-height: 72px;
    margin-bottom: 12px;
  }

  .execution-summary .info-box-icon {
    height: 72px;
    line-height: 72px;
  }

  .execution-summary .info-box-content {
    padding-top: 12px;
  }

  .execution-summary .info-box-text {
    white-space: nowrap;
  }

  .execution-table-wrapper {
    overflow-x: auto;
  }

  .execution-table td,
  .execution-table th {
    vertical-align: middle !important;
  }

  .execution-progress {
    min-width: 130px;
  }

  .execution-progress .progress {
    margin-bottom: 3px;
  }

  .execution-tabs > li > a {
    max-width: 240px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .execution-pane-header {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
  }

  .execution-meta {
    display: grid;
    grid-template-columns: repeat(4, minmax(140px, 1fr));
    gap: 10px;
    margin-bottom: 12px;
  }

  .execution-meta-item {
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    background: #fafafa;
    padding: 8px 10px;
  }

  .execution-meta-item span {
    color: #777;
    display: block;
    font-size: 12px;
    text-transform: uppercase;
  }

  .execution-meta-item strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .execution-console {
    background: #111827;
    border: 0;
    border-radius: 4px;
    color: #d1d5db;
    font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
    font-size: 12px;
    line-height: 1.45;
    max-height: 460px;
    min-height: 260px;
    overflow: auto;
    padding: 14px;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .execution-empty {
    border: 1px dashed #d2d6de;
    color: #777;
    padding: 36px 20px;
    text-align: center;
  }

  @media (max-width: 991px) {
    .execution-meta {
      grid-template-columns: repeat(2, minmax(140px, 1fr));
    }
  }

  @media (min-width: 1200px) {
    .execution-summary-column .execution-summary > div {
      width: 100%;
    }

    .execution-summary-column .info-box {
      margin-bottom: 10px;
    }

    .execution-summary-column .info-box-number {
      font-size: 24px;
    }
  }

  @media (max-width: 600px) {
    .execution-meta {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>
      Job Execution
      <small>Run Jobs</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Extract, Transform, Load</a></li>
      <li class="active">Job Execution</li>
    </ol>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="row" style="margin-top: 10px;">
        <div class="col-lg-3 col-md-4 col-xs-12">
          <div class="box box-primary">
            <div class="box-header with-border">
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
              </div>
              <h3 class="box-title"><b>Job Selector</b></h3>
            </div>
            <div class="box-body" style="padding: 20px;">
              <div class="form-group">
                <label for="jobFilter">Filter jobs</label>
                <input type="text" id="jobFilter" class="form-control" placeholder="Type a job name">
              </div>
              <div class="form-group">
                <label>Available jobs</label>
                <div id="jobSelectorList" class="execution-job-list">
                  <div class="text-muted text-center" style="padding: 24px;">Loading Jenkins jobs...</div>
                </div>
                <p class="execution-help"><span id="selectedJobCount">0</span> job(s) selected.</p>
              </div>
              <div class="execution-toolbar">
                <button type="button" class="btn btn-primary" id="triggerSelected">
                  <i class="fa fa-play"></i> Trigger Selected
                </button>
                <button type="button" class="btn btn-default" id="selectVisibleJobs">
                  <i class="fa fa-check-square-o"></i> Select Visible
                </button>
                <button type="button" class="btn btn-default" id="clearSelectedJobs">
                  <i class="fa fa-square-o"></i> Clear
                </button>
                <button type="button" class="btn btn-default" id="reloadJobs">
                  <i class="fa fa-refresh"></i> Reload Jobs
                </button>
              </div>
            </div>
            <div class="overlay" id="jobSelectorOverlay" style="display:none;">
              <i class="fa fa-refresh fa-spin"></i>
            </div>
          </div>
        </div>

        <div class="col-lg-7 col-md-8 col-xs-12">
          <div class="box box-primary">
            <div class="box-header with-border">
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
              </div>
              <h3 class="box-title"><b>Build Execution Info</b></h3>
            </div>
            <div class="box-body">
              <div class="execution-toolbar" style="margin-bottom: 10px;">
                <button type="button" class="btn btn-danger btn-sm" id="stopActive" disabled>
                  <i class="fa fa-stop"></i> Stop Active Builds
                </button>
                <button type="button" class="btn btn-default btn-sm" id="clearFinished" disabled>
                  <i class="fa fa-trash"></i> Clear Finished
                </button>
                <label class="checkbox-inline">
                  <input type="checkbox" id="autoScrollConsole" checked> Auto-scroll console
                </label>
              </div>
              <div class="execution-table-wrapper">
                <table class="table table-striped table-hover execution-table">
                  <thead>
                    <tr>
                      <th>Job</th>
                      <th>Build</th>
                      <th>Status</th>
                      <th>Queue</th>
                      <th>Started</th>
                      <th>Duration</th>
                      <th>Console</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody id="executionRows">
                    <tr id="executionNoRows"><td colspan="8" class="text-muted text-center">No executions started from this page yet.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-2 col-md-12 col-xs-12 execution-summary-column">
          <div class="row execution-summary">
            <div class="col-sm-6 col-md-3 col-lg-12">
              <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-list"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Executions</span>
                  <span class="info-box-number" id="executionTotal">0</span>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-3 col-lg-12">
              <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Queued</span>
                  <span class="info-box-number" id="executionQueued">0</span>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-3 col-lg-12">
              <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-play"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Running</span>
                  <span class="info-box-number" id="executionRunning">0</span>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-3 col-lg-12">
              <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fa-flag-checkered"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Finished</span>
                  <span class="info-box-number" id="executionFinished">0</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header with-border">
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
              </div>
              <h3 class="box-title"><b>Live Console Output</b></h3>
            </div>
            <div class="box-body">
              <div id="executionEmpty" class="execution-empty">
                <i class="fa fa-terminal fa-3x"></i>
                <h4>No live executions yet</h4>
                <p>Select one or more Jenkins jobs and trigger them to open live console tabs.</p>
              </div>
              <ul class="nav nav-tabs execution-tabs" id="executionTabs" role="tablist"></ul>
              <div class="tab-content" id="executionTabContent" style="padding-top: 15px;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script type="text/javascript">
  $(document).ready(function() {
    var jenkinsUrl = window.jobseekerJenkinsUrl || <?php echo json_encode(isset($jenkins_url) ? $jenkins_url : ''); ?>;
    var jobsByName = {};
    var visibleJobs = [];
    var selectedJobNames = {};
    var executions = {};
    var executionOrder = [];
    var executionCounter = 0;
    var buildPollDelay = 2000;
    var queuePollDelay = 2000;
    var consolePollDelay = 1200;

    if (jenkinsUrl && jenkinsUrl.charAt(jenkinsUrl.length - 1) !== '/') {
      jenkinsUrl += '/';
    }

    function escapeHtml(value) {
      return String(value == null ? '' : value).replace(/[&<>'"]/g, function(character) {
        return {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          "'": '&#039;',
          '"': '&quot;'
        }[character];
      });
    }

    function escapeAttribute(value) {
      return escapeHtml(value);
    }

    function jenkinsJobPath(jobName) {
      return 'job/' + String(jobName || '').split('/').map(function(segment) {
        return encodeURIComponent(segment);
      }).join('/job/');
    }

    function jenkinsRequest(path, method) {
      return $.ajax({
        url: jenkinsUrl + path,
        method: method || 'GET',
        cache: false
      });
    }

    function responseMessage(xhr, fallback) {
      if (xhr && xhr.responseText) {
        return xhr.responseText;
      }

      if (xhr && xhr.statusText) {
        return xhr.statusText;
      }

      return fallback || 'Request failed.';
    }

    function queueIdFromLocation(location) {
      var match = String(location || '').match(/\/queue\/item\/(\d+)\/?/);
      return match ? match[1] : '';
    }

    function buildLabel(run) {
      return run.buildNumber ? '#' + run.buildNumber : (run.expectedBuildNumber ? '#' + run.expectedBuildNumber + ' expected' : 'Pending');
    }

    function formatTime(timestamp) {
      timestamp = parseInt(timestamp, 10);
      if (! timestamp) {
        return 'Waiting';
      }

      if (typeof moment === 'function') {
        return moment(timestamp).format('YYYY-MM-DD HH:mm:ss');
      }

      return new Date(timestamp).toLocaleString();
    }

    function formatDuration(milliseconds) {
      milliseconds = parseInt(milliseconds, 10);
      if (isNaN(milliseconds) || milliseconds < 0) {
        return 'Pending';
      }

      var totalSeconds = Math.floor(milliseconds / 1000);
      var hours = Math.floor(totalSeconds / 3600);
      var minutes = Math.floor((totalSeconds % 3600) / 60);
      var seconds = totalSeconds % 60;

      return [hours, minutes, seconds].map(function(part) {
        return ('0' + part).slice(-2);
      }).join(':');
    }

    function isTerminal(run) {
      return run.finished || $.inArray(run.status, ['SUCCESS', 'FAILURE', 'ABORTED', 'NOT_BUILT', 'Trigger Failed', 'Cancelled', 'Error']) !== -1;
    }

    function isRunning(run) {
      return run.status === 'Running' || run.building === true;
    }

    function isQueued(run) {
      return run.status === 'Queued' || run.status === 'Waiting for Executor';
    }

    function statusLabel(run) {
      if (run.status === 'SUCCESS') {
        return '<span class="label label-success">Success</span>';
      }

      if (run.status === 'FAILURE' || run.status === 'ABORTED' || run.status === 'Trigger Failed' || run.status === 'Error') {
        return '<span class="label label-danger">' + escapeHtml(run.status) + '</span>';
      }

      if (run.status === 'Stopping' || run.status === 'Cancel Requested') {
        return '<span class="label label-warning">' + escapeHtml(run.status) + '</span>';
      }

      if (run.status === 'Cancelled') {
        return '<span class="label label-default">Cancelled</span>';
      }

      if (isRunning(run)) {
        return '<span class="label label-info">Running</span>';
      }

      if (isQueued(run)) {
        return '<span class="label label-warning">Queued</span>';
      }

      return '<span class="label label-default">' + escapeHtml(run.status || 'Pending') + '</span>';
    }

    function progressPercent(run) {
      if (run.finished) {
        return 100;
      }

      if (isQueued(run)) {
        return 10;
      }

      if (! isRunning(run)) {
        return 5;
      }

      var estimatedDuration = parseInt(run.estimatedDuration, 10);
      var timestamp = parseInt(run.timestamp, 10);

      if (estimatedDuration > 0 && timestamp > 0) {
        return Math.max(15, Math.min(95, Math.floor(((Date.now() - timestamp) / estimatedDuration) * 100)));
      }

      return 35;
    }

    function progressBar(run) {
      var percent = progressPercent(run);
      var className = run.finished ? 'progress-bar-success' : (isQueued(run) ? 'progress-bar-warning' : 'progress-bar-info progress-bar-striped');

      if (run.status === 'FAILURE' || run.status === 'ABORTED' || run.status === 'Trigger Failed' || run.status === 'Error') {
        className = 'progress-bar-danger';
      } else if (run.status === 'Cancelled') {
        className = 'progress-bar-warning';
      }

      return '<div class="execution-progress"><div class="progress progress-xs active"><div class="progress-bar ' + className + '" style="width: ' + percent + '%"></div></div><small>' + percent + '%</small></div>';
    }

    function shortJobName(jobName) {
      var parts = String(jobName || '').split('/');
      return parts[parts.length - 1] || jobName;
    }

    function buildJobOptionText(job) {
      var name = job.fullName || job.name || '';
      var state = 'idle';

      if (job.buildable === false) {
        state = 'disabled';
      } else if (job.inQueue === true) {
        state = 'queued';
      } else if (job.color && /_anime$/.test(String(job.color))) {
        state = 'running';
      } else if (job.lastBuild && job.lastBuild.result) {
        state = String(job.lastBuild.result).toLowerCase();
      } else if (job.color) {
        state = String(job.color).replace('_anime', '');
      }

      return name + ' [' + state + ']';
    }

    function jobStateLabel(job) {
      if (job.buildable === false) {
        return '<span class="label label-default pull-right">Disabled</span>';
      }

      if (job.inQueue === true) {
        return '<span class="label label-warning pull-right">Queued</span>';
      }

      if (job.color && /_anime$/.test(String(job.color))) {
        return '<span class="label label-info pull-right">Running</span>';
      }

      if (job.lastBuild && job.lastBuild.result === 'SUCCESS') {
        return '<span class="label label-success pull-right">Success</span>';
      }

      if (job.lastBuild && (job.lastBuild.result === 'FAILURE' || job.lastBuild.result === 'ABORTED')) {
        return '<span class="label label-danger pull-right">' + escapeHtml(job.lastBuild.result) + '</span>';
      }

      return '<span class="label label-default pull-right">Idle</span>';
    }

    function updateSelectedJobCount() {
      $('#selectedJobCount').text(Object.keys(selectedJobNames).length);
    }

    function selectedJobList() {
      return Object.keys(selectedJobNames).filter(function(jobName) {
        return jobsByName[jobName] && jobsByName[jobName].buildable !== false;
      });
    }

    function renderJobOptions(filter) {
      var normalizedFilter = String(filter || '').toLowerCase();
      var list = $('#jobSelectorList');
      var html = '';

      list.empty();

      $.each(visibleJobs, function(index, job) {
        var name = job.fullName || job.name || '';
        if (normalizedFilter && name.toLowerCase().indexOf(normalizedFilter) === -1) {
          return;
        }

        html += '<label class="execution-job-option' + (job.buildable === false ? ' disabled' : '') + '" title="' + escapeAttribute(buildJobOptionText(job)) + '">' +
          '<input type="checkbox" class="execution-job-check" value="' + escapeAttribute(name) + '" ' + (selectedJobNames[name] ? 'checked ' : '') + (job.buildable === false ? 'disabled ' : '') + '>' +
          '<span class="execution-job-name">' + escapeHtml(name) + '</span>' +
          jobStateLabel(job) +
        '</label>';
      });

      list.html(html || '<div class="text-muted text-center" style="padding: 24px;">No Jenkins jobs match this filter.</div>');
      updateSelectedJobCount();
    }

    function loadJobs() {
      if (! jenkinsUrl) {
        toastr.error('Jenkins URL is not configured.', 'Jenkins');
        return;
      }

      $('#jobSelectorOverlay').show();
      jenkinsRequest('api/json?tree=jobs[name,fullName,displayName,color,buildable,inQueue,nextBuildNumber,lastBuild[number,result,building]]')
        .done(function(data) {
          var jobs = Array.isArray(data.jobs) ? data.jobs : [];
          jobsByName = {};
          visibleJobs = jobs.sort(function(left, right) {
            var leftName = left.fullName || left.name || '';
            var rightName = right.fullName || right.name || '';
            return leftName.localeCompare(rightName);
          });

          $.each(visibleJobs, function(index, job) {
            var name = job.fullName || job.name || '';
            jobsByName[name] = job;
          });

          $.each(Object.keys(selectedJobNames), function(index, jobName) {
            if (! jobsByName[jobName] || jobsByName[jobName].buildable === false) {
              delete selectedJobNames[jobName];
            }
          });

          renderJobOptions($('#jobFilter').val());
        })
        .fail(function(xhr) {
          toastr.error(responseMessage(xhr, 'Unable to load Jenkins jobs.'), 'Jenkins');
        })
        .always(function() {
          $('#jobSelectorOverlay').hide();
        });
    }

    function createExecution(job) {
      executionCounter += 1;

      var jobName = job.fullName || job.name || '';
      var run = {
        id: 'execution_' + Date.now() + '_' + executionCounter,
        jobName: jobName,
        jobPath: jenkinsJobPath(jobName),
        expectedBuildNumber: job.nextBuildNumber || '',
        queueId: '',
        queueWhy: '',
        lastQueueMessage: '',
        buildNumber: '',
        displayName: shortJobName(jobName),
        status: 'Preparing',
        result: '',
        building: false,
        timestamp: '',
        duration: '',
        estimatedDuration: '',
        builtOn: '',
        url: '',
        description: '',
        consoleStart: 0,
        consoleBytes: 0,
        consoleComplete: false,
        hasConsoleText: false,
        finished: false,
        timers: {},
        infoRequestInFlight: false,
        consoleRequestInFlight: false
      };

      executions[run.id] = run;
      executionOrder.unshift(run.id);
      createExecutionTab(run);
      updateExecutionUI(run);
      updateExecutionSummary();

      return run;
    }

    function createExecutionTab(run) {
      var activeClass = executionOrder.length === 1 ? 'active' : '';
      $('#executionEmpty').hide();

      $('#executionTabs').append(
        '<li role="presentation" class="' + activeClass + '" id="tab-' + run.id + '">' +
          '<a href="#pane-' + run.id + '" aria-controls="pane-' + run.id + '" role="tab" data-toggle="tab" title="' + escapeAttribute(run.jobName) + '">' +
            '<i class="fa fa-terminal"></i> <span class="tab-title">' + escapeHtml(shortJobName(run.jobName)) + '</span>' +
          '</a>' +
        '</li>'
      );

      $('#executionTabContent').append(
        '<div role="tabpanel" class="tab-pane ' + activeClass + '" id="pane-' + run.id + '">' +
          '<div class="execution-pane-header">' +
            '<div>' +
              '<h4 style="margin-top: 0; margin-bottom: 4px;"><span class="run-job-name"></span></h4>' +
              '<span class="run-status"></span> <span class="text-muted run-queue"></span>' +
            '</div>' +
            '<div>' +
              '<button type="button" class="btn btn-danger btn-sm execution-abort" data-execution-id="' + run.id + '"><i class="fa fa-stop"></i> Stop</button>' +
              ' <button type="button" class="btn btn-default btn-sm execution-remove" data-execution-id="' + run.id + '"><i class="fa fa-times"></i> Close</button>' +
            '</div>' +
          '</div>' +
          '<div class="execution-meta">' +
            '<div class="execution-meta-item"><span>Build</span><strong class="run-build"></strong></div>' +
            '<div class="execution-meta-item"><span>Started</span><strong class="run-started"></strong></div>' +
            '<div class="execution-meta-item"><span>Duration</span><strong class="run-duration"></strong></div>' +
            '<div class="execution-meta-item"><span>Console</span><strong class="run-console-size"></strong></div>' +
          '</div>' +
          '<pre class="execution-console" id="console-' + run.id + '">Waiting for Jenkins to start this build...</pre>' +
        '</div>'
      );

      $('#executionTabs a[href="#pane-' + run.id + '"]').tab('show');
    }

    function updateExecutionUI(run) {
      var pane = $('#pane-' + run.id);
      pane.find('.run-job-name').text(run.jobName);
      pane.find('.run-status').html(statusLabel(run));
      pane.find('.run-queue').text(run.queueWhy ? run.queueWhy : (run.queueId ? 'Queue #' + run.queueId : ''));
      pane.find('.run-build').text(buildLabel(run));
      pane.find('.run-started').text(formatTime(run.timestamp));
      pane.find('.run-duration').text(run.finished ? formatDuration(run.duration) : formatDuration(run.timestamp ? Date.now() - parseInt(run.timestamp, 10) : 0));
      pane.find('.run-console-size').text(run.consoleBytes + ' bytes');
      pane.find('.execution-abort').prop('disabled', isTerminal(run));

      $('#tab-' + run.id + ' a').attr('title', run.jobName + ' - ' + (run.status || 'Pending'));
      renderExecutionRows();
      updateExecutionSummary();
    }

    function renderExecutionRows() {
      var rows = '';

      $.each(executionOrder, function(index, id) {
        var run = executions[id];
        if (! run) {
          return;
        }

        rows += '<tr>' +
          '<td><a href="#pane-' + run.id + '" data-toggle="tab" class="execution-row-link" data-tab-id="' + run.id + '">' + escapeHtml(run.jobName) + '</a></td>' +
          '<td>' + escapeHtml(buildLabel(run)) + '</td>' +
          '<td>' + statusLabel(run) + '</td>' +
          '<td>' + escapeHtml(run.queueWhy || (run.queueId ? 'Queue #' + run.queueId : 'None')) + '</td>' +
          '<td>' + escapeHtml(formatTime(run.timestamp)) + '</td>' +
          '<td>' + escapeHtml(run.finished ? formatDuration(run.duration) : formatDuration(run.timestamp ? Date.now() - parseInt(run.timestamp, 10) : 0)) + '</td>' +
          '<td>' + progressBar(run) + '<small>' + escapeHtml(run.consoleBytes + ' bytes') + '</small></td>' +
          '<td>' + (isTerminal(run) ? '<span class="text-muted">Done</span>' : '<button type="button" class="btn btn-xs btn-danger execution-abort" data-execution-id="' + run.id + '">Stop</button>') + '</td>' +
        '</tr>';
      });

      if (rows === '') {
        rows = '<tr id="executionNoRows"><td colspan="8" class="text-muted text-center">No executions started from this page yet.</td></tr>';
      }

      $('#executionRows').html(rows);
    }

    function updateExecutionSummary() {
      var total = executionOrder.length;
      var queued = 0;
      var running = 0;
      var finished = 0;

      $.each(executionOrder, function(index, id) {
        var run = executions[id];
        if (! run) {
          return;
        }

        if (isQueued(run)) {
          queued += 1;
        }

        if (isRunning(run)) {
          running += 1;
        }

        if (isTerminal(run)) {
          finished += 1;
        }
      });

      $('#executionTotal').text(total);
      $('#executionQueued').text(queued);
      $('#executionRunning').text(running);
      $('#executionFinished').text(finished);
      $('#stopActive').prop('disabled', running + queued === 0);
      $('#clearFinished').prop('disabled', finished === 0);
    }

    function clearRunTimer(run, key) {
      if (run.timers[key]) {
        clearTimeout(run.timers[key]);
        run.timers[key] = null;
      }
    }

    function scheduleRunTimer(run, key, callback, delay) {
      clearRunTimer(run, key);
      run.timers[key] = setTimeout(function() {
        run.timers[key] = null;
        callback();
      }, delay);
    }

    function appendConsole(run, text) {
      if (! text) {
        return;
      }

      var consoleElement = $('#console-' + run.id);

      if (! run.hasConsoleText) {
        consoleElement.text('');
        run.hasConsoleText = true;
      }

      consoleElement.append(document.createTextNode(text));

      if ($('#autoScrollConsole').is(':checked')) {
        consoleElement.scrollTop(consoleElement[0].scrollHeight);
      }
    }

    function markExecutionError(run, message) {
      run.status = 'Error';
      run.result = 'ERROR';
      run.finished = true;
      appendConsole(run, '\n[JobSeeker] ' + message + '\n');
      updateExecutionUI(run);
    }

    function appendQueueMessage(run, message) {
      if (! message || run.lastQueueMessage === message) {
        return;
      }

      run.lastQueueMessage = message;
      appendConsole(run, '[JobSeeker] ' + message + '\n');
    }

    function triggerExecution(run) {
      run.status = 'Sending';
      updateExecutionUI(run);

      jenkinsRequest(run.jobPath + '/build', 'POST')
        .done(function(data, textStatus, xhr) {
          var location = xhr.getResponseHeader('X-JobSeeker-Jenkins-Location') || xhr.getResponseHeader('Location') || '';
          run.queueId = queueIdFromLocation(location);
          run.status = 'Queued';
          run.queueWhy = run.queueId ? 'Queue #' + run.queueId : 'Waiting for Jenkins queue item';
          appendConsole(run, '[JobSeeker] Trigger request sent for ' + run.jobName + '.\n');
          appendQueueMessage(run, run.queueWhy);
          updateExecutionUI(run);
          discoverBuild(run);
        })
        .fail(function(xhr) {
          run.status = 'Trigger Failed';
          run.result = 'FAILURE';
          run.finished = true;
          appendConsole(run, '[JobSeeker] Trigger failed: ' + responseMessage(xhr, 'Unable to trigger this job.') + '\n');
          updateExecutionUI(run);
        });
    }

    function discoverBuild(run) {
      if (run.finished || run.buildNumber) {
        return;
      }

      if (run.queueId) {
        pollQueueItem(run);
        return;
      }

      pollExpectedBuild(run);
    }

    function pollQueueItem(run) {
      if (run.finished || run.buildNumber) {
        return;
      }

      jenkinsRequest('queue/item/' + encodeURIComponent(run.queueId) + '/api/json?tree=id,why,cancelled,executable[number,url]')
        .done(function(data) {
          if (data.cancelled === true) {
            run.status = 'Cancelled';
            run.finished = true;
            run.queueWhy = 'Queue item was cancelled';
            appendConsole(run, '[JobSeeker] Queue item was cancelled before the build started.\n');
            updateExecutionUI(run);
            return;
          }

          if (data.executable && data.executable.number) {
            startBuildPolling(run, data.executable.number);
            return;
          }

          run.status = 'Queued';
          run.queueWhy = data.why || 'Waiting for executor';
          appendQueueMessage(run, run.queueWhy);
          updateExecutionUI(run);
          scheduleRunTimer(run, 'queue', function() { pollQueueItem(run); }, queuePollDelay);
        })
        .fail(function() {
          pollExpectedBuild(run);
        });
    }

    function pollExpectedBuild(run) {
      if (run.finished || run.buildNumber) {
        return;
      }

      if (run.expectedBuildNumber) {
        jenkinsRequest(run.jobPath + '/' + encodeURIComponent(run.expectedBuildNumber) + '/api/json?tree=number,building,result')
          .done(function(data) {
            startBuildPolling(run, data.number || run.expectedBuildNumber);
          })
          .fail(function() {
            pollJobForBuild(run);
          });
        return;
      }

      pollJobForBuild(run);
    }

    function pollJobForBuild(run) {
      if (run.finished || run.buildNumber) {
        return;
      }

      jenkinsRequest(run.jobPath + '/api/json?tree=queueItem[id,why],lastBuild[number,building,result],nextBuildNumber')
        .done(function(data) {
          if (data.queueItem && data.queueItem.id) {
            run.queueId = data.queueItem.id;
            run.queueWhy = data.queueItem.why || 'Waiting for executor';
            run.status = 'Queued';
            appendQueueMessage(run, run.queueWhy);
            updateExecutionUI(run);
            scheduleRunTimer(run, 'queue', function() { pollQueueItem(run); }, queuePollDelay);
            return;
          }

          if (data.lastBuild && data.lastBuild.number) {
            if (! run.expectedBuildNumber || parseInt(data.lastBuild.number, 10) >= parseInt(run.expectedBuildNumber, 10)) {
              startBuildPolling(run, data.lastBuild.number);
              return;
            }
          }

          run.status = 'Queued';
          run.queueWhy = 'Waiting for build number';
          appendQueueMessage(run, run.queueWhy);
          updateExecutionUI(run);
          scheduleRunTimer(run, 'queue', function() { pollJobForBuild(run); }, queuePollDelay);
        })
        .fail(function(xhr) {
          markExecutionError(run, responseMessage(xhr, 'Unable to locate the queued build.'));
        });
    }

    function startBuildPolling(run, buildNumber) {
      run.buildNumber = buildNumber;
      run.status = 'Running';
      run.queueWhy = '';
      appendConsole(run, '[JobSeeker] Build #' + buildNumber + ' started.\n');
      updateExecutionUI(run);
      pollBuildInfo(run);
      pollConsole(run);
    }

    function pollBuildInfo(run) {
      if (! run.buildNumber || run.infoRequestInFlight) {
        return;
      }

      run.infoRequestInFlight = true;

      jenkinsRequest(run.jobPath + '/' + encodeURIComponent(run.buildNumber) + '/api/json?tree=building,result,number,id,queueId,url,displayName,fullDisplayName,timestamp,duration,estimatedDuration,description,builtOn')
        .done(function(data) {
          run.building = data.building === true;
          run.result = data.result || '';
          run.timestamp = data.timestamp || run.timestamp;
          run.duration = data.duration || run.duration;
          run.estimatedDuration = data.estimatedDuration || run.estimatedDuration;
          run.url = data.url || run.url;
          run.description = data.description || run.description;
          run.builtOn = data.builtOn || run.builtOn;
          run.displayName = data.fullDisplayName || data.displayName || run.displayName;

          if (run.building) {
            run.status = 'Running';
            scheduleRunTimer(run, 'buildInfo', function() { pollBuildInfo(run); }, buildPollDelay);
          } else {
            run.status = run.result || 'SUCCESS';
            run.finished = true;
            clearRunTimer(run, 'buildInfo');
            pollConsole(run);
            loadJobs();

            if (run.result === 'SUCCESS') {
              toastr.success(run.jobName + ' finished successfully.', 'Build Finished');
            } else {
              toastr.error(run.jobName + ' finished with status ' + run.status + '.', 'Build Finished');
            }
          }

          updateExecutionUI(run);
        })
        .fail(function(xhr) {
          markExecutionError(run, responseMessage(xhr, 'Unable to read build information.'));
        })
        .always(function() {
          run.infoRequestInFlight = false;
        });
    }

    function pollConsole(run) {
      if (! run.buildNumber || run.consoleRequestInFlight || run.consoleComplete) {
        return;
      }

      run.consoleRequestInFlight = true;

      jenkinsRequest(run.jobPath + '/' + encodeURIComponent(run.buildNumber) + '/logText/progressiveText?start=' + encodeURIComponent(run.consoleStart))
        .done(function(data, textStatus, xhr) {
          var text = data || '';
          var nextSize = parseInt(xhr.getResponseHeader('X-Text-Size'), 10);
          var hasMoreData = xhr.getResponseHeader('X-More-Data') === 'true';

          appendConsole(run, text);

          if (! isNaN(nextSize)) {
            run.consoleStart = nextSize;
          } else {
            run.consoleStart += text.length;
          }

          run.consoleBytes = run.consoleStart;
          run.consoleComplete = run.finished && ! hasMoreData;
          updateExecutionUI(run);

          if (! run.consoleComplete) {
            scheduleRunTimer(run, 'console', function() { pollConsole(run); }, consolePollDelay);
          }
        })
        .fail(function(xhr) {
          if (! run.finished) {
            scheduleRunTimer(run, 'console', function() { pollConsole(run); }, consolePollDelay);
            return;
          }

          appendConsole(run, '\n[JobSeeker] Unable to finish reading console output: ' + responseMessage(xhr, 'Console request failed.') + '\n');
          run.consoleComplete = true;
          updateExecutionUI(run);
        })
        .always(function() {
          run.consoleRequestInFlight = false;
        });
    }

    function hasActiveExecutionForJob(jobName) {
      return executionOrder.some(function(id) {
        var run = executions[id];
        return run && run.jobName === jobName && ! isTerminal(run);
      });
    }

    function stopExecution(run) {
      if (! run || isTerminal(run)) {
        return;
      }

      run.status = run.buildNumber ? 'Stopping' : 'Cancel Requested';
      updateExecutionUI(run);

      if (run.buildNumber) {
        jenkinsRequest(run.jobPath + '/' + encodeURIComponent(run.buildNumber) + '/stop', 'POST')
          .done(function() {
            appendConsole(run, '\n[JobSeeker] Stop request sent.\n');
            toastr.warning('Stop request sent for ' + run.jobName + '.', 'Build Stop');
            scheduleRunTimer(run, 'buildInfo', function() { pollBuildInfo(run); }, 1000);
          })
          .fail(function(xhr) {
            toastr.error(responseMessage(xhr, 'Unable to stop this build.'), 'Build Stop');
          });
        return;
      }

      if (run.queueId) {
        jenkinsRequest('queue/cancelItem?id=' + encodeURIComponent(run.queueId), 'POST')
          .done(function() {
            run.status = 'Cancelled';
            run.finished = true;
            appendConsole(run, '\n[JobSeeker] Queue item cancelled.\n');
            updateExecutionUI(run);
            toastr.warning('Queue item cancelled for ' + run.jobName + '.', 'Build Stop');
          })
          .fail(function(xhr) {
            toastr.error(responseMessage(xhr, 'Unable to cancel this queue item.'), 'Build Stop');
          });
      }
    }

    function removeExecution(run) {
      if (! run) {
        return;
      }

      $.each(run.timers, function(key) {
        clearRunTimer(run, key);
      });

      delete executions[run.id];
      executionOrder = executionOrder.filter(function(id) {
        return id !== run.id;
      });

      var wasActive = $('#tab-' + run.id).hasClass('active');
      $('#tab-' + run.id).remove();
      $('#pane-' + run.id).remove();

      if (wasActive && executionOrder.length > 0) {
        $('#executionTabs a[href="#pane-' + executionOrder[0] + '"]').tab('show');
      }

      if (executionOrder.length === 0) {
        $('#executionEmpty').show();
      }

      renderExecutionRows();
      updateExecutionSummary();
    }

    $('#jobFilter').on('keyup', function() {
      renderJobOptions($(this).val());
    });

    $(document).on('change', '.execution-job-check', function() {
      if (this.checked) {
        selectedJobNames[this.value] = true;
      } else {
        delete selectedJobNames[this.value];
      }

      updateSelectedJobCount();
    });

    $('#selectVisibleJobs').on('click', function() {
      $('.execution-job-check:enabled').each(function() {
        this.checked = true;
        selectedJobNames[this.value] = true;
      });

      updateSelectedJobCount();
    });

    $('#clearSelectedJobs').on('click', function() {
      selectedJobNames = {};
      $('.execution-job-check').prop('checked', false);
      updateSelectedJobCount();
    });

    $('#reloadJobs').on('click', function() {
      loadJobs();
    });

    $('#triggerSelected').on('click', function() {
      var selectedJobs = selectedJobList();
      var started = 0;

      if (selectedJobs.length === 0) {
        toastr.error('Select one or more jobs to execute.', 'Job Execution');
        return;
      }

      $.each(selectedJobs, function(index, jobName) {
        var job = jobsByName[jobName];

        if (! job) {
          toastr.error(jobName + ' is no longer available.', 'Job Execution');
          return;
        }

        if (job.buildable === false) {
          toastr.error(jobName + ' is disabled in Jenkins.', 'Job Execution');
          return;
        }

        if (hasActiveExecutionForJob(jobName)) {
          toastr.warning(jobName + ' already has an active execution tab.', 'Job Execution');
          return;
        }

        started += 1;
        delete selectedJobNames[jobName];
        triggerExecution(createExecution(job));
      });

      if (started > 0) {
        toastr.info(started + ' execution request(s) sent to Jenkins.', 'Job Execution');
        renderJobOptions($('#jobFilter').val());
        loadJobs();
      }
    });

    $('#stopActive').on('click', function() {
      $.each(executionOrder, function(index, id) {
        var run = executions[id];
        if (run && ! isTerminal(run)) {
          stopExecution(run);
        }
      });
    });

    $('#clearFinished').on('click', function() {
      $.each(executionOrder.slice(), function(index, id) {
        var run = executions[id];
        if (run && isTerminal(run)) {
          removeExecution(run);
        }
      });
    });

    $(document).on('click', '.execution-abort', function() {
      stopExecution(executions[$(this).data('execution-id')]);
    });

    $(document).on('click', '.execution-remove', function() {
      removeExecution(executions[$(this).data('execution-id')]);
    });

    $(document).on('click', '.execution-row-link', function() {
      $('#executionTabs a[href="#pane-' + $(this).data('tab-id') + '"]').tab('show');
    });

    loadJobs();
  });
</script>