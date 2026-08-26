<style>
  .execution-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .execution-selector-actions {
    align-items: stretch;
  }

  .execution-selector-actions .btn {
    flex: 1 1 calc(50% - 8px);
  }

  .execution-selector-actions #triggerSelected,
  .execution-selector-actions #reloadJobs {
    flex-basis: 100%;
  }

  .execution-main .box {
    margin-bottom: 14px;
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

  .execution-job-created {
    color: #777;
    display: block;
    font-size: 12px;
    margin-left: 24px;
    margin-top: 3px;
  }

  .execution-job-environment {
    color: #777;
    display: block;
    font-size: 12px;
    margin-left: 24px;
    margin-top: 3px;
  }

  .execution-job-environment .label {
    display: inline-block;
    margin-left: 4px;
  }

  .execution-environment-filter {
    margin-bottom: 12px;
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

  .execution-console-toolbar {
    margin-bottom: 20px;
  }

  .execution-console-toolbar .execution-compare-columns {
    min-width: 142px;
    width: auto;
  }

  .execution-console-hint {
    color: #777;
  }

  #executionTabContent.execution-compare-grid {
    display: grid;
    gap: 12px;
  }

  #executionTabContent.execution-compare-grid.compare-columns-auto {
    grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
  }

  #executionTabContent.execution-compare-grid.compare-columns-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  #executionTabContent.execution-compare-grid.compare-columns-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  #executionTabContent.execution-compare-grid > .tab-pane {
    background: #fff;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    display: block !important;
    min-width: 0;
    padding: 12px;
  }

  #executionTabContent.execution-compare-grid .execution-pane-header {
    align-items: flex-start;
  }

  #executionTabContent.execution-compare-grid .execution-meta {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  #executionTabContent.execution-compare-grid .execution-meta-item {
    padding: 6px 8px;
  }

  #executionTabContent.execution-compare-grid .execution-console {
    max-height: 520px;
    min-height: 300px;
  }

  #executionTabContent.execution-compare-grid .execution-pane-focus {
    box-shadow: 0 0 0 2px #3c8dbc;
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
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
    max-height: 560px;
    min-height: 340px;
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
    .execution-sidebar {
      position: sticky;
      top: 15px;
    }

    .execution-main .info-box-number {
      font-size: 24px;
    }
  }

  @media (max-width: 600px) {
    .execution-meta {
      grid-template-columns: 1fr;
    }

    #executionTabContent.execution-compare-grid {
      grid-template-columns: 1fr !important;
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
        <div class="col-lg-3 col-md-4 col-xs-12 execution-sidebar">
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
              <div class="form-group execution-environment-filter">
                <label for="jobEnvironmentFilter">Filter by environment</label>
                <select id="jobEnvironmentFilter" class="form-control">
                  <option value="all">All environments</option>
                </select>
              </div>
              <div class="form-group">
                <label for="jobStatusFilter">Filter by status</label>
                <select id="jobStatusFilter" class="form-control">
                  <option value="all">All statuses</option>
                  <option value="healthy">Healthy / Success</option>
                  <option value="running">Running</option>
                  <option value="queued">Queued</option>
                  <option value="attention">Needs Attention</option>
                  <option value="disabled">Disabled</option>
                  <option value="never-built">Never Built</option>
                </select>
              </div>
              <div class="form-group">
                <label>Available jobs</label>
                <div id="jobSelectorList" class="execution-job-list">
                  <div class="text-muted text-center" style="padding: 24px;">Loading Jenkins jobs...</div>
                </div>
                <p class="execution-help"><span id="selectedJobCount">0</span> job(s) selected.</p>
              </div>
              <div class="execution-toolbar execution-selector-actions">
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

        <div class="col-lg-9 col-md-8 col-xs-12 execution-main">
          <div class="row execution-summary">
            <div class="col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-list"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Executions</span>
                  <span class="info-box-number" id="executionTotal">0</span>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Queued</span>
                  <span class="info-box-number" id="executionQueued">0</span>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-play"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Running</span>
                  <span class="info-box-number" id="executionRunning">0</span>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fa-flag-checkered"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Finished</span>
                  <span class="info-box-number" id="executionFinished">0</span>
                </div>
              </div>
            </div>
          </div>

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
                      <th>Environment</th>
                      <th>Worker</th>
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
                    <tr id="executionNoRows"><td colspan="10" class="text-muted text-center">No executions started from this page yet.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="box box-primary">
            <div class="box-header with-border">
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
              </div>
              <h3 class="box-title"><b>Live Console Output</b></h3>
            </div>
            <div class="box-body">
              <div id="executionEmpty" class="execution-empty" style="margin-bottom: 10px;">
                <i class="fa fa-terminal fa-3x"></i>
                <h4>No live executions yet</h4>
                <p>Select one or more Jenkins jobs and trigger them to open live console tabs.</p>
              </div>
              <div class="execution-toolbar execution-console-toolbar">
                <div class="btn-group btn-group-sm" role="group" aria-label="Console layout">
                  <button type="button" class="btn btn-primary active" id="consoleTabbedView" data-console-view="tabs"><i class="fa fa-list"></i> Tabs</button>
                  <button type="button" class="btn btn-default" id="consoleCompareView" data-console-view="compare"><i class="fa fa-columns"></i> Compare</button>
                </div>
                <select class="form-control input-sm execution-compare-columns" id="consoleCompareColumns" disabled>
                  <option value="auto">Auto columns</option>
                  <option value="2">2 columns</option>
                  <option value="3">3 columns</option>
                </select>
                <button type="button" class="btn btn-info btn-sm" id="viewRunningBuilds">
                  <i class="fa fa-terminal"></i> View Running Jobs
                </button>
                <span class="execution-console-hint" id="consoleViewHint">Showing one live console at a time.</span>
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
    var availableJobsUrl = <?php echo json_encode(base_url() . 'jobCreation/availableJobs'); ?>;
    var runningBuildsUrl = window.jobseekerRunningBuildsUrl || <?php echo json_encode(base_url() . 'jenkins/runningBuilds'); ?>;
    var jobsByName = {};
    var visibleJobs = [];
    var selectedJobNames = {};
    var executions = {};
    var executionOrder = [];
    var executionCounter = 0;
    var jobEnvironmentFilter = window.jobseekerDashboardEnvironment || 'all';
    var jobStatusFilter = 'all';
    var buildPollDelay = 2000;
    var queuePollDelay = 2000;
    var consolePollDelay = 1200;
    var consoleViewMode = 'tabs';
    var triggerSelectedBusy = false;
    var initialResumeBuild = {
      jobName: <?php echo json_encode(isset($resume_job) ? $resume_job : ''); ?>,
      buildNumber: <?php echo json_encode(isset($resume_build) ? $resume_build : ''); ?>,
      environment: <?php echo json_encode(isset($resume_environment) ? $resume_environment : ''); ?>
    };
    var jobCreationDates = <?php echo json_encode(isset($job_creation_dates) && is_array($job_creation_dates) ? $job_creation_dates : array()); ?> || {};
    var jobEnvironmentRequests = {};
    var environmentHelper = window.JobSeekerEnvironment || {
      detectFromConfig: function(xmlText, jobName) { return this.detectFromJob({name: jobName}); },
      detectFromJob: function(job) { return {environment: 'Unknown', source: 'Not detected', unknown: true}; },
      normalize: function(value) { return $.trim(String(value || '')).toUpperCase(); },
      label: function() { return '<span class="label label-default">Unknown</span>'; },
      text: function(info) { return info && info.environment ? info.environment : 'Unknown'; }
    };

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

    function jenkinsRequestWithData(path, method, data) {
      return $.ajax({
        url: jenkinsUrl + path,
        method: method || 'GET',
        cache: false,
        data: data || undefined
      });
    }

    function hasKnownEnvironment(environment) {
      return environment && environment !== 'Unknown';
    }

    function triggerJobBuild(run) {
      if (! hasKnownEnvironment(run.environment)) {
        return jenkinsRequest(run.jobPath + '/build', 'POST')
          .then(null, function(xhr) {
            if (xhr && (xhr.status === 400 || xhr.status === 404)) {
              return jenkinsRequestWithData(run.jobPath + '/buildWithParameters', 'POST');
            }

            return $.Deferred().rejectWith(this, arguments).promise();
          });
      }

      return jenkinsRequestWithData(run.jobPath + '/buildWithParameters', 'POST', { ENVIRONMENT: run.environment })
        .then(null, function(xhr) {
          if (xhr && (xhr.status === 400 || xhr.status === 404)) {
            return jenkinsRequest(run.jobPath + '/build', 'POST');
          }

          return $.Deferred().rejectWith(this, arguments).promise();
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

    function jobCreationTimestamp(jobName) {
      var createdAt = jobCreationDates[jobName] || '';
      var timestamp = Date.parse(createdAt);

      return isNaN(timestamp) ? 0 : timestamp;
    }

    function formatJobCreationDate(jobName) {
      var timestamp = jobCreationTimestamp(jobName);

      if (! timestamp) {
        return 'Created: Not tracked';
      }

      if (typeof moment === 'function') {
        return 'Created: ' + moment(timestamp).format('YYYY-MM-DD HH:mm:ss');
      }

      return 'Created: ' + new Date(timestamp).toLocaleString();
    }

    function environmentInfoForJob(job) {
      if (job && job.environmentInfo) {
        return job.environmentInfo;
      }

      return environmentHelper.detectFromJob(job || {});
    }

    function environmentTextForJob(job) {
      return environmentHelper.text(environmentInfoForJob(job));
    }

    function normalizeEnvironmentFilterValue(value) {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
        return window.JobSeekerGlobalEnvironment.normalize(value);
      }

      return environmentHelper.normalize(value);
    }

    function configuredEnvironmentNames() {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.configuredEnvironmentNames) {
        return window.JobSeekerGlobalEnvironment.configuredEnvironmentNames();
      }

      return $.map(window.jobseekerGlobalEnvironmentOptions || [], function(value) {
        return normalizeEnvironmentFilterValue(value);
      });
    }

    function configuredEnvironmentLabel(environment) {
      var normalized = normalizeEnvironmentFilterValue(environment);
      var labels = window.jobseekerGlobalEnvironmentOptions || [];

      for (var index = 0; index < labels.length; index++) {
        if (normalizeEnvironmentFilterValue(labels[index]) === normalized) {
          return labels[index];
        }
      }

      return normalized;
    }

    function isConfiguredEnvironment(environment) {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.isConfiguredEnvironment) {
        return window.JobSeekerGlobalEnvironment.isConfiguredEnvironment(environment);
      }

      return $.inArray(normalizeEnvironmentFilterValue(environment), configuredEnvironmentNames()) !== -1;
    }

    function isAllEnvironmentFilter(value) {
      return String(value || '').toLowerCase() === 'all';
    }

    function jobEnvironmentRequestValue() {
      return isAllEnvironmentFilter(jobEnvironmentFilter) ? 'all' : normalizeEnvironmentFilterValue(jobEnvironmentFilter);
    }

    function normalizedSelectableJobColor(job) {
      return String(job && job.color ? job.color : '').replace('_anime', '');
    }

    function selectableJobLastResult(job) {
      return job && job.lastBuild && job.lastBuild.result ? String(job.lastBuild.result).toUpperCase() : '';
    }

    function isSelectableJobRunning(job) {
      return !!(job && ((job.lastBuild && job.lastBuild.building === true) || /_anime$/.test(String(job.color || ''))));
    }

    function isSelectableJobHealthy(job) {
      return job && job.buildable !== false && job.inQueue !== true && ! isSelectableJobRunning(job) && (selectableJobLastResult(job) === 'SUCCESS' || normalizedSelectableJobColor(job) === 'blue');
    }

    function isSelectableJobAttention(job) {
      var result = selectableJobLastResult(job);
      var color = normalizedSelectableJobColor(job);
      return $.inArray(result, ['FAILURE', 'ABORTED', 'UNSTABLE']) !== -1 || $.inArray(color, ['red', 'yellow', 'aborted']) !== -1;
    }

    function isSelectableJobNeverBuilt(job) {
      return job && ! job.lastBuild && normalizedSelectableJobColor(job) === 'notbuilt';
    }

    function jobMatchesStatusFilter(job) {
      if (jobStatusFilter === 'healthy') {
        return isSelectableJobHealthy(job);
      }

      if (jobStatusFilter === 'running') {
        return isSelectableJobRunning(job);
      }

      if (jobStatusFilter === 'queued') {
        return job && job.inQueue === true;
      }

      if (jobStatusFilter === 'attention') {
        return isSelectableJobAttention(job);
      }

      if (jobStatusFilter === 'disabled') {
        return job && job.buildable === false;
      }

      if (jobStatusFilter === 'never-built') {
        return isSelectableJobNeverBuilt(job);
      }

      return true;
    }

    function jobMatchesEnvironmentFilter(job) {
      if (isAllEnvironmentFilter(jobEnvironmentFilter)) {
        return true;
      }

      var environment = normalizeEnvironmentFilterValue(environmentTextForJob(job));

      if (! isConfiguredEnvironment(environment)) {
        return false;
      }

      return jobEnvironmentFilter === 'all' || environment === normalizeEnvironmentFilterValue(jobEnvironmentFilter);
    }

    function renderJobEnvironment(job) {
      var info = environmentInfoForJob(job);
      return '<span class="execution-job-environment"><i class="fa fa-globe"></i> Environment ' + environmentHelper.label(info) + '</span>';
    }

    function updateEnvironmentFilterOptions() {
      var counts = {};
      var totalJobs = (visibleJobs || []).length;

      $.each(visibleJobs || [], function(index, job) {
        var environment = normalizeEnvironmentFilterValue(environmentTextForJob(job));
        if (isConfiguredEnvironment(environment)) {
          counts[environment] = (counts[environment] || 0) + 1;
        }
      });

      var currentValue = jobEnvironmentFilter;
      var options = '<option value="all">All environments (' + totalJobs + ')</option>';
      $.each(configuredEnvironmentNames().sort(), function(index, environment) {
        options += '<option value="' + escapeAttribute(environment) + '">' + escapeHtml(configuredEnvironmentLabel(environment)) + ' (' + (counts[environment] || 0) + ')</option>';
      });

      $('#jobEnvironmentFilter').html(options);
      $('#jobEnvironmentFilter').val(isAllEnvironmentFilter(currentValue) ? 'all' : (isConfiguredEnvironment(currentValue) ? normalizeEnvironmentFilterValue(currentValue) : 'all'));
      jobEnvironmentFilter = $('#jobEnvironmentFilter').val() || 'all';
    }

    function hydrateJobEnvironment(job) {
      var jobName = job && (job.fullName || job.name) ? job.fullName || job.name : '';

      if (! jobName || job.environmentHydrated || jobEnvironmentRequests[jobName]) {
        return $.Deferred().resolve(job).promise();
      }

      jobEnvironmentRequests[jobName] = jenkinsRequest(jenkinsJobPath(jobName) + '/config.xml', 'GET')
        .done(function(xmlText) {
          job.environmentInfo = environmentHelper.detectFromConfig(xmlText || '', jobName);
          job.environmentHydrated = true;
          updateEnvironmentFilterOptions();
          renderJobOptions($('#jobFilter').val());
        })
        .fail(function() {
          job.environmentInfo = environmentHelper.detectFromJob(job);
          job.environmentHydrated = true;
        })
        .always(function() {
          delete jobEnvironmentRequests[jobName];
        });

      return jobEnvironmentRequests[jobName];
    }

    function hydrateJobEnvironments() {
      $.each(visibleJobs, function(index, job) {
        hydrateJobEnvironment(job);
      });
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

      return name + ' [' + state + ', environment: ' + environmentTextForJob(job) + ']';
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
      $('#selectedJobCount').text(selectedJobList().length);
      updateTriggerSelectedButton();
    }

    function setTriggerSelectedBusy(isBusy, label) {
      triggerSelectedBusy = isBusy;
      $('#triggerSelected')
        .prop('disabled', isBusy)
        .html(label || '<i class="fa fa-play"></i> Trigger Selected');
    }

    function updateTriggerSelectedButton() {
      if (triggerSelectedBusy) {
        return;
      }

      var selectedJobs = selectedJobList();
      var runningSelected = 0;

      $.each(selectedJobs, function(index, jobName) {
        if (runningBuildFromJob(jobsByName[jobName])) {
          runningSelected += 1;
        }
      });

      if (selectedJobs.length > 0 && runningSelected === selectedJobs.length) {
        $('#triggerSelected').html('<i class="fa fa-terminal"></i> View Live Console');
      } else if (runningSelected > 0) {
        $('#triggerSelected').html('<i class="fa fa-play"></i> Trigger / View Selected');
      } else {
        $('#triggerSelected').html('<i class="fa fa-play"></i> Trigger Selected');
      }
    }

    function selectedJobList() {
      return Object.keys(selectedJobNames).filter(function(jobName) {
        return jobsByName[jobName] && jobsByName[jobName].buildable !== false && jobMatchesEnvironmentFilter(jobsByName[jobName]) && jobMatchesStatusFilter(jobsByName[jobName]);
      });
    }

    function renderJobOptions(filter) {
      var normalizedFilter = String(filter || '').toLowerCase();
      var list = $('#jobSelectorList');
      var html = '';

      list.empty();
      updateEnvironmentFilterOptions();

      $.each(visibleJobs, function(index, job) {
        var name = job.fullName || job.name || '';
        if (normalizedFilter && name.toLowerCase().indexOf(normalizedFilter) === -1) {
          return;
        }

        if (! jobMatchesEnvironmentFilter(job)) {
          return;
        }

        if (! jobMatchesStatusFilter(job)) {
          return;
        }

        html += '<label class="execution-job-option' + (job.buildable === false ? ' disabled' : '') + '" title="' + escapeAttribute(buildJobOptionText(job)) + '">' +
          '<input type="checkbox" class="execution-job-check" value="' + escapeAttribute(name) + '" ' + (selectedJobNames[name] ? 'checked ' : '') + (job.buildable === false ? 'disabled ' : '') + '>' +
          '<span class="execution-job-name">' + escapeHtml(name) + '</span>' +
          jobStateLabel(job) +
          renderJobEnvironment(job) +
          '<span class="execution-job-created"><i class="fa fa-calendar-o"></i> ' + escapeHtml(formatJobCreationDate(name)) + '</span>' +
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
      $.ajax({url: availableJobsUrl, method: 'GET', dataType: 'json', cache: false, data: {environment: jobEnvironmentRequestValue()}})
        .done(function(data) {
          var jobs = Array.isArray(data.jobs) ? data.jobs : [];
          jobsByName = {};
          visibleJobs = jobs.sort(function(left, right) {
            var leftName = left.fullName || left.name || '';
            var rightName = right.fullName || right.name || '';
            var createdDiff = jobCreationTimestamp(rightName) - jobCreationTimestamp(leftName);

            if (createdDiff !== 0) {
              return createdDiff;
            }

            return leftName.localeCompare(rightName);
          });

          $.each(visibleJobs, function(index, job) {
            var name = job.fullName || job.name || '';
            job.environmentInfo = job.environmentInfo || environmentHelper.detectFromJob(job);
            job.environmentHydrated = ! job.environmentInfo.unknown;
            jobsByName[name] = job;
          });

          $.each(Object.keys(selectedJobNames), function(index, jobName) {
            if (! jobsByName[jobName] || jobsByName[jobName].buildable === false) {
              delete selectedJobNames[jobName];
            }
          });

          renderJobOptions($('#jobFilter').val());
          hydrateJobEnvironments();
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
        environment: environmentTextForJob(job),
        environmentInfo: environmentInfoForJob(job),
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

    function environmentInfoFromText(environment, source, fallbackJob) {
      if (fallbackJob && fallbackJob.environmentInfo) {
        return fallbackJob.environmentInfo;
      }

      var normalized = normalizeEnvironmentFilterValue(environment || '');
      var known = normalized && normalized !== 'all' && normalized !== 'UNKNOWN' && normalized !== '__UNKNOWN__';

      return {
        environment: known ? normalized : (environment || 'Unknown'),
        source: source || 'Jenkins runtime metadata',
        unknown: ! known
      };
    }

    function activeExecutionForBuild(jobName, buildNumber) {
      var normalizedBuild = String(buildNumber || '');
      var found = null;

      $.each(executionOrder, function(index, id) {
        var run = executions[id];
        if (! run || run.jobName !== jobName) {
          return;
        }

        if (normalizedBuild === '' || String(run.buildNumber || '') === normalizedBuild) {
          found = run;
          return false;
        }
      });

      return found;
    }

    function runningBuildFromJob(job) {
      if (! job || ! isSelectableJobRunning(job) || ! job.lastBuild || ! job.lastBuild.number) {
        return null;
      }

      var build = $.extend({}, job.lastBuild);
      build.jobName = job.fullName || job.name || '';
      build.job = build.jobName;
      build.buildNumber = build.number;
      build.environment = environmentTextForJob(job);
      build.environmentInfo = environmentInfoForJob(job);
      return build;
    }

    function createExecutionFromBuild(build, fallbackJob) {
      build = build || {};
      var jobName = build.jobName || build.job || (fallbackJob ? fallbackJob.fullName || fallbackJob.name : '');
      var buildNumber = build.buildNumber || build.number || '';

      if (! jobName || ! buildNumber) {
        toastr.error('Unable to identify the running build.', 'Live Console');
        return null;
      }

      var existing = activeExecutionForBuild(jobName, buildNumber);
      if (existing) {
        focusExecutionPane(existing.id);
        return existing;
      }

      executionCounter += 1;

      var environment = build.environment || (fallbackJob ? environmentTextForJob(fallbackJob) : 'Unknown');
      var run = {
        id: 'execution_' + Date.now() + '_' + executionCounter,
        jobName: jobName,
        jobPath: jenkinsJobPath(jobName),
        expectedBuildNumber: buildNumber,
        queueId: build.queueId || '',
        queueWhy: '',
        lastQueueMessage: '',
        buildNumber: buildNumber,
        displayName: build.fullDisplayName || build.displayName || shortJobName(jobName),
        environment: environment,
        environmentInfo: build.environmentInfo || environmentInfoFromText(environment, build.environmentSource || 'Jenkins runtime metadata', fallbackJob),
        status: build.building === false ? (build.result || 'SUCCESS') : 'Running',
        result: build.result || '',
        building: build.building === false ? false : true,
        timestamp: build.timestamp || '',
        duration: build.duration || '',
        estimatedDuration: build.estimatedDuration || '',
        builtOn: build.builtOn || '',
        url: build.url || '',
        description: build.description || '',
        consoleStart: 0,
        consoleBytes: 0,
        consoleComplete: false,
        hasConsoleText: false,
        finished: build.building === false,
        timers: {},
        infoRequestInFlight: false,
        consoleRequestInFlight: false
      };

      executions[run.id] = run;
      executionOrder.unshift(run.id);
      createExecutionTab(run);
      appendConsole(run, '[JobSeeker] Reattached to ' + run.jobName + ' build #' + run.buildNumber + '.\n');
      updateExecutionUI(run);
      pollBuildInfo(run);
      pollConsole(run);

      if (window.JobSeekerRunningJobs && window.JobSeekerRunningJobs.refresh) {
        window.JobSeekerRunningJobs.refresh();
      }

      return run;
    }

    function loadRunningBuildsForCurrentEnvironment() {
      $('#viewRunningBuilds').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading');
      $.getJSON(runningBuildsUrl, {environment: jobEnvironmentRequestValue(), limit: 10})
        .done(function(payload) {
          if (! payload || payload.ok !== true) {
            toastr.error(payload && payload.message ? payload.message : 'Unable to load running jobs.', 'Live Console');
            return;
          }

          var builds = Array.isArray(payload.builds) ? payload.builds : [];
          var opened = 0;

          if (builds.length === 0) {
            toastr.info('No running Jenkins jobs were found for this scope.', 'Live Console');
            return;
          }

          $.each(builds, function(index, build) {
            if (createExecutionFromBuild(build)) {
              opened += 1;
            }
          });

          toastr.info(opened + ' running build console(s) opened.', 'Live Console');
        })
        .fail(function(xhr) {
          toastr.error(responseMessage(xhr, 'Unable to load running jobs.'), 'Live Console');
        })
        .always(function() {
          $('#viewRunningBuilds').prop('disabled', false).html('<i class="fa fa-terminal"></i> View Running Jobs');
        });
    }

    function resumeInitialExecution() {
      if (! initialResumeBuild.jobName || ! initialResumeBuild.buildNumber) {
        return;
      }

      createExecutionFromBuild({
        jobName: initialResumeBuild.jobName,
        job: initialResumeBuild.jobName,
        buildNumber: initialResumeBuild.buildNumber,
        number: initialResumeBuild.buildNumber,
        environment: initialResumeBuild.environment || 'Unknown',
        environmentSource: 'JobSeeker link',
        building: true
      });
      initialResumeBuild = {};
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
            '<div class="execution-meta-item"><span>Environment</span><strong class="run-environment"></strong></div>' +
            '<div class="execution-meta-item"><span>Worker</span><strong class="run-worker"></strong></div>' +
            '<div class="execution-meta-item"><span>Started</span><strong class="run-started"></strong></div>' +
            '<div class="execution-meta-item"><span>Duration</span><strong class="run-duration"></strong></div>' +
            '<div class="execution-meta-item"><span>Console</span><strong class="run-console-size"></strong></div>' +
          '</div>' +
          '<div class="execution-console job-console-host" id="console-' + run.id + '"><div class="job-console-empty">Waiting for Jenkins to start this build...</div></div>' +
        '</div>'
      );

      $('#executionTabs a[href="#pane-' + run.id + '"]').tab('show');
    }

    function updateConsoleViewLayout() {
      var compareMode = consoleViewMode === 'compare';
      var columns = $('#consoleCompareColumns').val() || 'auto';

      $('#consoleTabbedView')
        .toggleClass('btn-primary active', ! compareMode)
        .toggleClass('btn-default', compareMode);
      $('#consoleCompareView')
        .toggleClass('btn-primary active', compareMode)
        .toggleClass('btn-default', ! compareMode);
      $('#consoleCompareColumns').prop('disabled', ! compareMode);
      $('#consoleViewHint').text(compareMode ? 'Showing live consoles side by side.' : 'Showing one live console at a time.');
      $('#executionTabs').toggle(! compareMode);
      $('#executionTabContent')
        .removeClass('execution-compare-grid compare-columns-auto compare-columns-2 compare-columns-3')
        .toggleClass('execution-compare-grid compare-columns-' + columns, compareMode);

      if (! compareMode && executionOrder.length > 0 && $('#executionTabs li.active').length === 0) {
        $('#executionTabs a[href="#pane-' + executionOrder[0] + '"]').tab('show');
      }
    }

    function focusExecutionPane(runId) {
      var pane = $('#pane-' + runId);

      if (consoleViewMode === 'compare') {
        if (pane.length && typeof pane[0].scrollIntoView === 'function') {
          pane[0].scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        pane.addClass('execution-pane-focus');
        setTimeout(function() {
          pane.removeClass('execution-pane-focus');
        }, 1200);
        return;
      }

      $('#executionTabs a[href="#pane-' + runId + '"]').tab('show');
    }

    function updateExecutionUI(run) {
      var pane = $('#pane-' + run.id);
      pane.find('.run-job-name').text(run.jobName);
      pane.find('.run-status').html(statusLabel(run));
      pane.find('.run-queue').text(run.queueWhy ? run.queueWhy : (run.queueId ? 'Queue #' + run.queueId : ''));
      pane.find('.run-build').text(buildLabel(run));
      pane.find('.run-environment').html(environmentHelper.label(run.environmentInfo));
      pane.find('.run-worker').text(workerNodeLabel(run));
      pane.find('.run-started').text(formatTime(run.timestamp));
      pane.find('.run-duration').text(run.finished ? formatDuration(run.duration) : formatDuration(run.timestamp ? Date.now() - parseInt(run.timestamp, 10) : 0));
      pane.find('.run-console-size').text(run.consoleBytes + ' bytes');
      pane.find('.execution-abort').prop('disabled', isTerminal(run));

      $('#tab-' + run.id + ' a').attr('title', run.jobName + ' - ' + run.environment + ' - ' + (run.status || 'Pending'));
      renderExecutionRows();
      updateExecutionSummary();
    }

    function workerNodeLabel(run) {
      if (! run || ! run.buildNumber) {
        return 'Waiting';
      }

      return run.builtOn ? run.builtOn : 'Controller';
    }

    function renderExecutionRows() {
      var rows = '';

      $.each(executionOrder, function(index, id) {
        var run = executions[id];
        if (! run) {
          return;
        }

        rows += '<tr>' +
          '<td><a href="#pane-' + run.id + '" class="execution-row-link" data-tab-id="' + run.id + '">' + escapeHtml(run.jobName) + '</a></td>' +
          '<td>' + environmentHelper.label(run.environmentInfo) + '</td>' +
          '<td>' + escapeHtml(workerNodeLabel(run)) + '</td>' +
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
        rows = '<tr id="executionNoRows"><td colspan="10" class="text-muted text-center">No executions started from this page yet.</td></tr>';
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

      if (window.JobSeekerConsole) {
        window.JobSeekerConsole.appendText(consoleElement, text, {live: ! run.finished});
      } else {
        consoleElement.append(document.createTextNode(text));
      }

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

      triggerJobBuild(run)
        .done(function(data, textStatus, xhr) {
          var location = xhr.getResponseHeader('X-JobSeeker-Jenkins-Location') || xhr.getResponseHeader('Location') || '';
          run.queueId = queueIdFromLocation(location);
          run.status = 'Queued';
          run.queueWhy = run.queueId ? 'Queue #' + run.queueId : 'Waiting for Jenkins queue item';
          appendConsole(run, '[JobSeeker] Environment: ' + run.environment + '.\n');
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

      if (window.JobSeekerRunningJobs && window.JobSeekerRunningJobs.refresh) {
        window.JobSeekerRunningJobs.refresh();
      }
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

            if (window.JobSeekerRunningJobs && window.JobSeekerRunningJobs.refresh) {
              window.JobSeekerRunningJobs.refresh();
            }

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

    $('#jobEnvironmentFilter').on('change', function() {
      var value = $(this).val() || 'all';
      jobEnvironmentFilter = isAllEnvironmentFilter(value) ? 'all' : normalizeEnvironmentFilterValue(value);
      loadJobs();
    });

    $('#jobStatusFilter').on('change', function() {
      jobStatusFilter = $(this).val() || 'all';
      renderJobOptions($('#jobFilter').val());
    });

    $(document).on('jobseeker:environment-change', function(event, environment) {
      jobEnvironmentFilter = isAllEnvironmentFilter(environment) ? 'all' : normalizeEnvironmentFilterValue(environment || 'all');
      loadJobs();
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

    $('#viewRunningBuilds').on('click', function() {
      loadRunningBuildsForCurrentEnvironment();
    });

    $('#triggerSelected').on('click', function() {
      var selectedJobs = selectedJobList();
      var readyJobs = [];
      var resumed = 0;

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

        var runningBuild = runningBuildFromJob(job);
        if (runningBuild) {
          if (createExecutionFromBuild(runningBuild, job)) {
            resumed += 1;
            delete selectedJobNames[jobName];
          }
          return;
        }

        if (isSelectableJobRunning(job)) {
          toastr.warning(jobName + ' is running, but Jenkins did not return a build number yet. Reload jobs in a moment and try again.', 'Job Execution');
          return;
        }

        if (hasActiveExecutionForJob(jobName)) {
          toastr.warning(jobName + ' already has an active execution tab.', 'Job Execution');
          return;
        }

        readyJobs.push(job);
      });

      if (resumed > 0) {
        toastr.info(resumed + ' running build console(s) opened.', 'Live Console');
      }

      if (readyJobs.length > 0) {
        setTriggerSelectedBusy(true, '<i class="fa fa-spinner fa-spin"></i> Preparing');
        $.when.apply($, $.map(readyJobs, function(job) { return hydrateJobEnvironment(job); })).always(function() {
          var started = 0;

          $.each(readyJobs, function(index, job) {
            var jobName = job.fullName || job.name || '';
            started += 1;
            delete selectedJobNames[jobName];
            triggerExecution(createExecution(job));
          });

          toastr.info(started + ' execution request(s) sent to Jenkins.', 'Job Execution');
          renderJobOptions($('#jobFilter').val());
          loadJobs();
          setTriggerSelectedBusy(false);
          updateTriggerSelectedButton();
        });
      } else {
        renderJobOptions($('#jobFilter').val());
        updateTriggerSelectedButton();
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

    $('[data-console-view]').on('click', function() {
      consoleViewMode = $(this).data('console-view') === 'compare' ? 'compare' : 'tabs';
      updateConsoleViewLayout();
    });

    $('#consoleCompareColumns').on('change', function() {
      updateConsoleViewLayout();
    });

    $(document).on('click', '.execution-row-link', function(event) {
      event.preventDefault();
      focusExecutionPane($(this).data('tab-id'));
    });

    loadJobs();
    resumeInitialExecution();
    updateTriggerSelectedButton();
    updateConsoleViewLayout();
  });
</script>
