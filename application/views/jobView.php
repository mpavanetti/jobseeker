<style type="text/css">
  .job-view-sidebar {
    margin-bottom: 14px;
  }

  .job-view-toolbar {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .job-view-selector-actions .btn {
    flex: 1 1 calc(50% - 8px);
  }

  .job-view-selector-actions #loadSelected,
  .job-view-selector-actions #reloadJobs {
    flex-basis: 100%;
  }

  .job-view-job-list {
    border: 1px solid #d2d6de;
    border-radius: 4px;
    max-height: 380px;
    min-height: 280px;
    overflow: auto;
  }

  .job-view-job-option {
    border-bottom: 1px solid #f4f4f4;
    cursor: pointer;
    display: block;
    font-weight: normal;
    margin: 0;
    padding: 9px 10px;
  }

  .job-view-job-option:hover {
    background: #f9fafc;
  }

  .job-view-job-option input {
    margin-right: 8px;
  }

  .job-view-job-name {
    display: inline-block;
    max-width: calc(100% - 90px);
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
    white-space: nowrap;
  }

  .job-view-job-created {
    color: #777;
    display: block;
    font-size: 12px;
    margin-left: 24px;
    margin-top: 3px;
  }

  .job-view-job-environment,
  .job-environment-source {
    color: #777;
    display: block;
    font-size: 12px;
    margin-top: 3px;
  }

  .job-view-job-environment {
    margin-left: 24px;
  }

  .job-view-job-environment .label,
  .job-environment-source .label {
    display: inline-block;
    margin-left: 4px;
  }

  .job-view-environment-filter {
    margin-bottom: 12px;
  }

  .job-view-summary .info-box {
    margin-bottom: 12px;
    min-height: 72px;
  }

  .job-view-summary .info-box-icon {
    height: 72px;
    line-height: 72px;
  }

  .job-view-summary .info-box-content {
    padding-top: 12px;
  }

  .job-view-summary .info-box-text {
    white-space: nowrap;
  }

  .job-view-empty {
    border: 1px dashed #d2d6de;
    color: #777;
    padding: 42px 20px;
    text-align: center;
  }

  .job-compare-wrapper {
    overflow-x: auto;
  }

  .job-compare-table {
    margin-bottom: 0;
  }

  .job-compare-table th:first-child,
  .job-compare-table td:first-child {
    background: #f9fafc;
    min-width: 150px;
    width: 150px;
  }

  .job-compare-table th:not(:first-child),
  .job-compare-table td:not(:first-child) {
    min-width: 240px;
    vertical-align: top !important;
  }

  .job-view-list {
    margin: 0;
    padding-left: 18px;
  }

  .job-view-list li {
    margin-bottom: 4px;
  }

  .job-view-mini-pre,
  .job-command-pre,
  .job-console-pre,
  .job-xml-pre {
    background: #111827;
    border: 0;
    border-radius: 4px;
    color: #d1d5db;
    font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
    font-size: 12px;
    line-height: 1.45;
    margin-bottom: 0;
    overflow: auto;
    padding: 12px;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .job-view-mini-pre {
    max-height: 140px;
  }

  .job-command-pre {
    max-height: 260px;
  }

  .job-console-pre,
  .job-xml-pre {
    max-height: 360px;
  }

  .job-detail-grid {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
  }

  .job-detail-card {
    min-width: 0;
  }

  .job-detail-card .box-title {
    max-width: calc(100% - 210px);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .job-detail-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
  }

  .job-overview-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(4, minmax(120px, 1fr));
    margin-bottom: 14px;
  }

  .job-overview-item {
    background: #fafafa;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 8px 10px;
  }

  .job-overview-item span {
    color: #777;
    display: block;
    font-size: 12px;
    text-transform: uppercase;
  }

  .job-overview-item strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .job-detail-section {
    border-top: 1px solid #f4f4f4;
    margin-top: 12px;
    padding-top: 12px;
  }

  .job-detail-section h4 {
    font-size: 15px;
    font-weight: 700;
    margin: 0 0 8px;
  }

  .job-runtime-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  }

  .job-runtime-item {
    background: #fafafa;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 8px 10px;
    min-width: 0;
  }

  .job-runtime-item span {
    color: #777;
    display: block;
    font-size: 12px;
    text-transform: uppercase;
  }

  .job-runtime-item strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .job-runtime-badges .label {
    display: inline-block;
    margin: 0 4px 4px 0;
  }

  .job-build-table td,
  .job-build-table th,
  .job-small-table td,
  .job-small-table th {
    vertical-align: middle !important;
  }

  .job-view-status-line {
    color: #777;
    margin-top: 8px;
  }

  details.job-xml-details summary {
    cursor: pointer;
    font-weight: 700;
    margin-bottom: 8px;
  }

  @media (min-width: 1200px) {
    .job-view-sidebar {
      position: sticky;
      top: 15px;
    }

    .job-view-summary .info-box-number {
      font-size: 24px;
    }
  }

  @media (max-width: 991px) {
    .job-overview-grid {
      grid-template-columns: repeat(2, minmax(120px, 1fr));
    }
  }

  @media (max-width: 600px) {
    .job-detail-grid,
    .job-overview-grid {
      grid-template-columns: 1fr;
    }

    .job-detail-card .box-title {
      max-width: 100%;
    }

    .job-detail-actions {
      justify-content: flex-start;
      margin-top: 8px;
    }
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>
      View Job
      <small>Explore and compare Jenkins job details.</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Job Management</a></li>
      <li class="active">View Job</li>
    </ol>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="row" style="margin-top: 10px;">
        <div class="col-lg-3 col-md-4 col-xs-12 job-view-sidebar">
          <div class="box box-warning">
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
              <div class="form-group job-view-environment-filter">
                <label for="jobEnvironmentFilter">Filter by environment</label>
                <select id="jobEnvironmentFilter" class="form-control">
                  <option value="all">All environments</option>
                </select>
              </div>
              <div class="form-group">
                <label>Available jobs</label>
                <div id="jobSelectorList" class="job-view-job-list">
                  <div class="text-muted text-center" style="padding: 24px;">Loading Jenkins jobs...</div>
                </div>
                <p class="job-view-status-line"><span id="selectedJobCount">0</span> job(s) selected.</p>
              </div>
              <div class="job-view-toolbar job-view-selector-actions">
                <button type="button" class="btn btn-primary" id="loadSelected">
                  <i class="fa fa-columns"></i> Load Details
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

        <div class="col-lg-9 col-md-8 col-xs-12">
          <div class="row job-view-summary">
            <div class="col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-eye"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Loaded Jobs</span>
                  <span class="info-box-number" id="summaryLoaded">0</span>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-check"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Buildable</span>
                  <span class="info-box-number" id="summaryBuildable">0</span>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Active</span>
                  <span class="info-box-number" id="summaryActive">0</span>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fa-warning"></i></span>
                <div class="info-box-content">
                  <span class="info-box-text">Problems</span>
                  <span class="info-box-number" id="summaryProblems">0</span>
                </div>
              </div>
            </div>
          </div>

          <div class="box box-primary">
            <div class="box-header with-border">
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
              </div>
              <h3 class="box-title"><b>Job Comparison</b></h3>
            </div>
            <div class="box-body">
              <div id="jobViewEmpty" class="job-view-empty">
                <i class="fa fa-columns fa-3x"></i>
                <h4>No job details loaded yet</h4>
                <p>Select one or more Jenkins jobs, then load details to compare configuration, build history, health, and latest console output.</p>
              </div>
              <div id="jobCompareWrapper" class="job-compare-wrapper" style="display:none;"></div>
              <p class="job-view-status-line" id="jobViewStatus">Waiting for a selection.</p>
            </div>
            <div class="overlay" id="jobViewOverlay" style="display:none;">
              <i class="fa fa-refresh fa-spin"></i>
            </div>
          </div>

          <div class="box box-primary">
            <div class="box-header with-border">
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
              </div>
              <h3 class="box-title"><b>Job Details</b></h3>
            </div>
            <div class="box-body">
              <div id="jobDetailsGrid" class="job-detail-grid"></div>
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
    var requestedJobs = initialRequestedJobs();
    var requestedJobsApplied = false;
    var jobEnvironmentFilter = 'all';
    var jobEnvironmentRequests = {};
    var jobCreationDates = <?php echo json_encode(isset($job_creation_dates) && is_array($job_creation_dates) ? $job_creation_dates : array()); ?> || {};
    var environmentHelper = window.JobSeekerEnvironment || {
      detectFromConfig: function(xmlText, jobName) { return this.detectFromJob({name: jobName}); },
      detectFromJob: function() { return {environment: 'Unknown', source: 'Not detected', unknown: true}; },
      detectFromName: function() { return {environment: 'Unknown', source: 'Not detected', unknown: true}; },
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

    function uniqueValues(values) {
      var seen = {};
      var unique = [];

      $.each(values || [], function(index, value) {
        value = $.trim(String(value || ''));
        if (! value || seen[value]) {
          return;
        }

        seen[value] = true;
        unique.push(value);
      });

      return unique;
    }

    function initialRequestedJobs() {
      var params = new URLSearchParams(window.location.search);
      var jobs = [];

      $.each(params.getAll('job'), function(index, value) {
        jobs.push(value);
      });

      $.each(params.getAll('jobs'), function(index, value) {
        jobs = jobs.concat(String(value || '').split(','));
      });

      return uniqueValues(jobs);
    }

    function jenkinsJobPath(jobName) {
      return String(jobName == null ? '' : jobName).split('/').map(function(segment) {
        return 'job/' + encodeURIComponent(segment);
      }).join('/');
    }

    function isLocalHostName(hostname) {
      hostname = String(hostname || '').toLowerCase();
      return hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
    }

    function isInternalJenkinsHost(hostname) {
      hostname = String(hostname || '').toLowerCase();
      return isLocalHostName(hostname) || hostname === 'jenkins';
    }

    function forwardedHostForPort(hostname, port) {
      var codespacesHost = String(hostname || '').match(/^(.*)-\d+(\.(?:app\.github\.dev|preview\.app\.github\.dev|githubpreview\.dev))$/);

      if (codespacesHost) {
        return codespacesHost[1] + '-' + port + codespacesHost[2];
      }

      codespacesHost = String(hostname || '').match(/^(.+?)(\.(?:app\.github\.dev|preview\.app\.github\.dev|githubpreview\.dev))$/);

      if (codespacesHost) {
        return codespacesHost[1] + '-' + port + codespacesHost[2];
      }

      return hostname + ':' + port;
    }

    function browserJenkinsBaseUrl(configuredUrl) {
      var baseUrl = String(configuredUrl || '');

      if (! baseUrl) {
        return '';
      }

      if (baseUrl.charAt(baseUrl.length - 1) !== '/') {
        baseUrl += '/';
      }

      var parser = document.createElement('a');
      parser.href = baseUrl;

      if (isInternalJenkinsHost(parser.hostname) && window.location.hostname && ! isLocalHostName(window.location.hostname)) {
        var jenkinsPort = parser.port || (parser.protocol === 'https:' ? '443' : '80');
        var jenkinsPath = parser.pathname || '/';

        if (jenkinsPath.charAt(jenkinsPath.length - 1) !== '/') {
          jenkinsPath += '/';
        }

        return window.location.protocol + '//' + forwardedHostForPort(window.location.hostname, jenkinsPort) + jenkinsPath;
      }

      return baseUrl;
    }

    function jenkinsJobUrl(jobName) {
      var baseUrl = browserJenkinsBaseUrl(jenkinsUrl);

      if (! baseUrl) {
        return '';
      }

      if (baseUrl.charAt(baseUrl.length - 1) !== '/') {
        baseUrl += '/';
      }

      return baseUrl + jenkinsJobPath(jobName) + '/';
    }

    function jenkinsRequest(path, method, options) {
      options = options || {};

      return $.ajax($.extend({
        url: jenkinsUrl + path,
        method: method || 'GET',
        cache: false
      }, options));
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

    function formatTime(timestamp) {
      timestamp = parseInt(timestamp, 10);
      if (! timestamp) {
        return 'Not available';
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

    function jobMatchesEnvironmentFilter(job) {
      var environment = normalizeEnvironmentFilterValue(environmentTextForJob(job));

      if (! isConfiguredEnvironment(environment)) {
        return false;
      }

      return jobEnvironmentFilter === 'all' || environment === normalizeEnvironmentFilterValue(jobEnvironmentFilter);
    }

    function renderEnvironmentInfo(info) {
      info = info || environmentHelper.detectFromName('');
      return environmentHelper.label(info) + '<small class="job-environment-source">' + escapeHtml(info.source || 'Not detected') + '</small>';
    }

    function renderJobEnvironment(job) {
      return '<span class="job-view-job-environment"><i class="fa fa-globe"></i> Environment ' + environmentHelper.label(environmentInfoForJob(job)) + '</span>';
    }

    function updateEnvironmentFilterOptions() {
      var counts = {};
      var totalJobs = 0;

      $.each(visibleJobs || [], function(index, job) {
        var environment = normalizeEnvironmentFilterValue(environmentTextForJob(job));
        if (isConfiguredEnvironment(environment)) {
          counts[environment] = (counts[environment] || 0) + 1;
          totalJobs += 1;
        }
      });

      var currentValue = jobEnvironmentFilter;
      var options = '<option value="all">All environments (' + totalJobs + ')</option>';
      $.each(configuredEnvironmentNames().sort(), function(index, environment) {
        options += '<option value="' + escapeAttribute(environment) + '">' + escapeHtml(configuredEnvironmentLabel(environment)) + ' (' + (counts[environment] || 0) + ')</option>';
      });

      $('#jobEnvironmentFilter').html(options);
      $('#jobEnvironmentFilter').val(currentValue === 'all' || isConfiguredEnvironment(currentValue) ? normalizeEnvironmentFilterValue(currentValue) : 'all');
      jobEnvironmentFilter = $('#jobEnvironmentFilter').val() || 'all';
    }

    function hydrateJobEnvironment(job) {
      var jobName = job && (job.fullName || job.name) ? job.fullName || job.name : '';

      if (! jobName || job.environmentHydrated || jobEnvironmentRequests[jobName]) {
        return;
      }

      jobEnvironmentRequests[jobName] = jenkinsRequest(jenkinsJobPath(jobName) + '/config.xml', 'GET', {dataType: 'text'})
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
    }

    function hydrateJobEnvironments() {
      $.each(visibleJobs, function(index, job) {
        hydrateJobEnvironment(job);
      });
    }

    function formatDuration(milliseconds) {
      milliseconds = parseInt(milliseconds, 10);
      if (isNaN(milliseconds) || milliseconds < 0) {
        return 'Not available';
      }

      var totalSeconds = Math.floor(milliseconds / 1000);
      var hours = Math.floor(totalSeconds / 3600);
      var minutes = Math.floor((totalSeconds % 3600) / 60);
      var seconds = totalSeconds % 60;

      return [hours, minutes, seconds].map(function(part) {
        return ('0' + part).slice(-2);
      }).join(':');
    }

    function boolLabel(value) {
      return value === true || value === 'true' ? '<span class="label label-info">Yes</span>' : '<span class="label label-default">No</span>';
    }

    function statusText(job) {
      if (! job || job.error) {
        return 'Unavailable';
      }

      if (job.disabled === true || job.buildable === false) {
        return 'Disabled';
      }

      if (job.inQueue === true) {
        return 'Queued';
      }

      if (job.lastBuild && job.lastBuild.building === true) {
        return 'Running';
      }

      if (job.color && /_anime$/.test(String(job.color))) {
        return 'Running';
      }

      if (job.lastBuild && job.lastBuild.result) {
        return job.lastBuild.result;
      }

      if (job.color === 'notbuilt' || ! job.lastBuild) {
        return 'Never Built';
      }

      return String(job.color || 'Idle').replace('_anime', '');
    }

    function statusLabel(value) {
      value = String(value || 'Pending');

      if (value === 'SUCCESS' || value === 'Success') {
        return '<span class="label label-success">Success</span>';
      }

      if ($.inArray(value, ['FAILURE', 'ABORTED', 'UNSTABLE', 'NOT_BUILT', 'Error', 'Unavailable']) !== -1) {
        return '<span class="label label-danger">' + escapeHtml(value) + '</span>';
      }

      if ($.inArray(value, ['Running', 'Queued']) !== -1) {
        return '<span class="label label-info">' + escapeHtml(value) + '</span>';
      }

      if (value === 'Disabled' || value === 'Never Built') {
        return '<span class="label label-default">' + escapeHtml(value) + '</span>';
      }

      return '<span class="label label-warning">' + escapeHtml(value) + '</span>';
    }

    function renderMuted(value) {
      return '<span class="text-muted">' + escapeHtml(value || 'None') + '</span>';
    }

    function renderValue(value) {
      value = $.trim(String(value == null ? '' : value));
      return value ? escapeHtml(value) : renderMuted('None');
    }

    function renderList(values) {
      values = uniqueValues(values || []);

      if (values.length === 0) {
        return renderMuted('None');
      }

      return '<ul class="job-view-list"><li>' + $.map(values, function(value) {
        return escapeHtml(value);
      }).join('</li><li>') + '</li></ul>';
    }

    function renderPre(value, className) {
      value = $.trim(String(value == null ? '' : value));
      return value ? '<pre class="' + className + '">' + escapeHtml(value) + '</pre>' : renderMuted('None');
    }

    function renderLabel(value, styleName) {
      return '<span class="label label-' + escapeAttribute(styleName || 'default') + '">' + escapeHtml(value) + '</span>';
    }

    function renderBuild(build) {
      if (! build || ! build.number) {
        return renderMuted('None');
      }

      var result = build.building === true ? 'Running' : (build.result || 'No result');
      return '<strong>#' + escapeHtml(build.number) + '</strong> ' + statusLabel(result) + '<br><small>' + escapeHtml(formatTime(build.timestamp)) + ' / ' + escapeHtml(formatDuration(build.duration)) + '</small>';
    }

    function metric(label, value) {
      return '<div class="job-overview-item"><span>' + escapeHtml(label) + '</span><strong>' + value + '</strong></div>';
    }

    function nodeText(node) {
      return node && node.textContent != null ? $.trim(node.textContent) : '';
    }

    function childText(node, tagName) {
      if (! node) {
        return '';
      }

      var children = node.getElementsByTagName(tagName);
      return children.length ? nodeText(children[0]) : '';
    }

    function regexEscape(value) {
      return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function shellExportValue(commandText, variableName) {
      var pattern = new RegExp("(?:^|\\n)\\s*(?:export\\s+)?" + regexEscape(variableName) + "=(?:'([^']*)'|\"([^\"]*)\"|([^\\s\\n]+))");
      var match = pattern.exec(commandText || '');

      if (! match) {
        return '';
      }

      return match[1] || match[2] || match[3] || '';
    }

    function decodeBase64Text(value) {
      try {
        return value && window.atob ? window.atob(value) : '';
      } catch (error) {
        return '';
      }
    }

    function labelFromScriptType(scriptType) {
      if (scriptType === 'talend') {
        return 'Talend script';
      }

      if (scriptType === 'bash') {
        return 'Bash script';
      }

      if (scriptType === 'python' || scriptType === 'python_inline') {
        return 'Python script';
      }

      return scriptType ? scriptType + ' script' : 'Linux command';
    }

    function sourceKindFromRuntime(runtime) {
      var sourcePath = runtime.sourceDirectory || runtime.scriptPath || '';
      var normalizedSourcePath = sourcePath.replace(/\\/g, '/');

      if (runtime.commandPreview) {
        return 'Inline command';
      }

      if (/git clone /.test(runtime.rawCommand)) {
        return 'Git repository';
      }

      if (normalizedSourcePath.indexOf('/python/inline/') !== -1) {
        return 'Inline Python';
      }

      if (sourcePath) {
        return 'Uploaded/path source';
      }

      return 'Not detected';
    }

    function displayEntryPointFromRuntime(runtime) {
      var entryPoint = runtime.entryPoint || '';
      var scriptPath = String(runtime.scriptPath || '').replace(/\\/g, '/');
      var sourceDirectory = String(runtime.sourceDirectory || '').replace(/\\/g, '/');

      if (entryPoint && entryPoint.indexOf('$') === -1) {
        return entryPoint;
      }

      if (scriptPath && sourceDirectory && scriptPath.indexOf(sourceDirectory + '/') === 0) {
        return scriptPath.substring(sourceDirectory.length + 1);
      }

      return scriptPath ? scriptPath.split('/').pop() : entryPoint;
    }

    function parseRuntimeConfig(commands) {
      var rawCommand = (commands || []).join('\n\n');
      var runtime = {
        commandPreview: '',
        dockerImage: shellExportValue(rawCommand, 'JOBSEEKER_DOCKER_IMAGE'),
        entryPoint: shellExportValue(rawCommand, 'JOBSEEKER_ENTRYPOINT') || shellExportValue(rawCommand, 'JOBSEEKER_DOCKER_ENTRYPOINT'),
        linuxRuntime: shellExportValue(rawCommand, 'JOBSEEKER_LINUX_RUNTIME'),
        mode: 'Jenkins Agent',
        pythonExecutable: shellExportValue(rawCommand, 'JOBSEEKER_PYTHON'),
        pythonRuntime: shellExportValue(rawCommand, 'JOBSEEKER_PYTHON_RUNTIME'),
        rawCommand: rawCommand,
        scriptPath: shellExportValue(rawCommand, 'JOBSEEKER_SCRIPT_PATH'),
        scriptType: shellExportValue(rawCommand, 'JOBSEEKER_LINUX_SCRIPT_TYPE'),
        sourceDirectory: shellExportValue(rawCommand, 'JOBSEEKER_SOURCE_DIR'),
        sourceKind: 'Not detected',
        type: 'Shell',
        usesDocker: false
      };
      var encodedCommand = shellExportValue(rawCommand, 'JOBSEEKER_LINUX_COMMAND_B64');

      runtime.commandPreview = decodeBase64Text(encodedCommand);
      runtime.usesDocker = runtime.dockerImage !== '' || runtime.pythonRuntime === 'docker' || runtime.linuxRuntime === 'docker' || /docker run/.test(rawCommand);
      runtime.mode = runtime.usesDocker ? 'Docker' : 'Jenkins Agent';

      if (runtime.pythonRuntime || runtime.pythonExecutable || runtime.entryPoint && /\.py$/.test(runtime.entryPoint)) {
        runtime.type = 'Python';
      } else if (runtime.linuxRuntime || runtime.scriptType || runtime.commandPreview) {
        runtime.type = labelFromScriptType(runtime.scriptType);
      }

      runtime.entryPoint = displayEntryPointFromRuntime(runtime);
      runtime.sourceKind = sourceKindFromRuntime(runtime);
      return runtime;
    }

    function renderRuntimeBadges(config) {
      var runtime = config && config.runtime ? config.runtime : parseRuntimeConfig([]);
      var badges = [renderLabel(runtime.mode, runtime.usesDocker ? 'primary' : 'default')];

      if (runtime.type) {
        badges.push(renderLabel(runtime.type, runtime.type === 'Python' ? 'info' : 'default'));
      }

      if (runtime.dockerImage) {
        badges.push(renderLabel(runtime.dockerImage, 'success'));
      }

      return '<span class="job-runtime-badges">' + badges.join(' ') + '</span>';
    }

    function runtimeField(label, value) {
      var content = value || renderMuted('None');
      var plainText = $('<div>').html(content).text();
      return '<div class="job-runtime-item"><span>' + escapeHtml(label) + '</span><strong title="' + escapeAttribute(plainText) + '">' + content + '</strong></div>';
    }

    function renderRuntimeConfig(config) {
      var runtime = config && config.runtime ? config.runtime : parseRuntimeConfig([]);
      var html = '<div class="job-runtime-grid">' +
        runtimeField('Environment', renderEnvironmentInfo(config ? config.environmentInfo : environmentHelper.detectFromName(''))) +
        runtimeField('Runtime', renderRuntimeBadges(config)) +
        runtimeField('Docker Image', runtime.dockerImage ? escapeHtml(runtime.dockerImage) : renderMuted('No Docker image')) +
        runtimeField('Job Type', escapeHtml(runtime.type || 'Shell')) +
        runtimeField('Source', escapeHtml(runtime.sourceKind || 'Not detected')) +
        runtimeField('Entrypoint', runtime.entryPoint ? escapeHtml(runtime.entryPoint) : renderMuted('None')) +
        runtimeField('Python', runtime.pythonExecutable ? escapeHtml(runtime.pythonExecutable) : renderMuted('None')) +
        runtimeField('Script Type', runtime.scriptType ? escapeHtml(labelFromScriptType(runtime.scriptType)) : renderMuted('None')) +
        runtimeField('Source Path', runtime.sourceDirectory || runtime.scriptPath ? escapeHtml(runtime.sourceDirectory || runtime.scriptPath) : renderMuted('None')) +
      '</div>';

      if (runtime.commandPreview) {
        html += '<div class="job-detail-section"><h4>Decoded Linux Command</h4>' + renderPre(runtime.commandPreview, 'job-command-pre') + '</div>';
      }

      return html;
    }

    function parseJobConfig(xmlText, jobName) {
      var config = {
        commands: [],
        environmentInfo: environmentHelper.detectFromName(jobName || ''),
        schedules: [],
        timeouts: [],
        downstream: [],
        downstreamConditions: [],
        mailRecipients: [],
        emailDefaults: {},
        emailTriggers: [],
        parameters: [],
        runtime: parseRuntimeConfig([]),
        scmUrls: [],
        parseError: ''
      };

      if (! xmlText) {
        return config;
      }

      try {
        var xml = $.parseXML(xmlText);
        var allElements = xml.getElementsByTagName('*');

        function firstText(tagName) {
          var nodes = xml.getElementsByTagName(tagName);
          return nodes.length ? nodeText(nodes[0]) : '';
        }

        function allText(tagName) {
          var values = [];
          var nodes = xml.getElementsByTagName(tagName);
          for (var index = 0; index < nodes.length; index += 1) {
            values.push(nodeText(nodes[index]));
          }
          return uniqueValues(values);
        }

        config.commands = allText('command');
        config.environmentInfo = environmentHelper.detectFromConfig(xmlText, jobName || '');
        config.runtime = parseRuntimeConfig(config.commands);
        config.schedules = allText('spec');
        config.downstream = allText('childProjects');
        config.scmUrls = uniqueValues(allText('url').concat(allText('remote')));

        var timeoutSeconds = firstText('timeoutSecondsString');
        var timeoutMinutes = firstText('timeoutMinutes');
        if (timeoutSeconds) {
          config.timeouts.push(timeoutSeconds + ' seconds');
        }
        if (timeoutMinutes) {
          config.timeouts.push(timeoutMinutes + ' minutes');
        }

        var thresholdNodes = xml.getElementsByTagName('threshold');
        for (var thresholdIndex = 0; thresholdIndex < thresholdNodes.length; thresholdIndex += 1) {
          var thresholdName = childText(thresholdNodes[thresholdIndex], 'name');
          if (thresholdName) {
            config.downstreamConditions.push(thresholdName);
          }
        }

        var mailerNodes = xml.getElementsByTagName('hudson.tasks.Mailer');
        for (var mailerIndex = 0; mailerIndex < mailerNodes.length; mailerIndex += 1) {
          config.mailRecipients.push(childText(mailerNodes[mailerIndex], 'recipients'));
        }
        config.mailRecipients = uniqueValues(config.mailRecipients);

        var emailPublisher = xml.getElementsByTagName('hudson.plugins.emailext.ExtendedEmailPublisher')[0];
        if (emailPublisher) {
          config.emailDefaults = {
            recipients: childText(emailPublisher, 'recipientList'),
            from: childText(emailPublisher, 'from'),
            subject: childText(emailPublisher, 'defaultSubject'),
            body: childText(emailPublisher, 'defaultContent')
          };
        }

        for (var elementIndex = 0; elementIndex < allElements.length; elementIndex += 1) {
          var element = allElements[elementIndex];
          var tagName = element.tagName || '';

          if (/ParameterDefinition$/.test(tagName)) {
            var parameterName = childText(element, 'name');
            if (parameterName) {
              config.parameters.push({
                type: tagName.split('.').pop().replace('ParameterDefinition', ''),
                name: parameterName,
                defaultValue: childText(element, 'defaultValue') || childText(element, 'value'),
                description: childText(element, 'description')
              });
            }
          }

          if (/plugins\.trigger\..*Trigger$/.test(tagName)) {
            config.emailTriggers.push({
              name: tagName.split('.').pop().replace('Trigger', ''),
              recipients: childText(element, 'recipientList'),
              subject: childText(element, 'subject'),
              attachBuildLog: childText(element, 'attachBuildLog')
            });
          }
        }
      } catch (error) {
        config.parseError = error && error.message ? error.message : 'Unable to parse config.xml.';
      }

      return config;
    }

    function renderJobStateLabel(job) {
      return statusLabel(statusText(job));
    }

    function renderJobOptions(filter) {
      var normalizedFilter = String(filter || '').toLowerCase();
      var html = '';

      updateEnvironmentFilterOptions();

      $.each(visibleJobs, function(index, job) {
        var name = job.fullName || job.name || '';
        if (normalizedFilter && name.toLowerCase().indexOf(normalizedFilter) === -1) {
          return;
        }

        if (! jobMatchesEnvironmentFilter(job)) {
          return;
        }

        html += '<label class="job-view-job-option" title="' + escapeAttribute(name + ' - environment: ' + environmentTextForJob(job)) + '">' +
          '<input type="checkbox" class="job-view-job-check" value="' + escapeAttribute(name) + '" ' + (selectedJobNames[name] ? 'checked ' : '') + '>' +
          '<span class="job-view-job-name">' + escapeHtml(name) + '</span>' +
          '<span class="pull-right">' + renderJobStateLabel(job) + '</span>' +
          renderJobEnvironment(job) +
          '<span class="job-view-job-created"><i class="fa fa-calendar-o"></i> ' + escapeHtml(formatJobCreationDate(name)) + '</span>' +
        '</label>';
      });

      $('#jobSelectorList').html(html || '<div class="text-muted text-center" style="padding: 24px;">No Jenkins jobs match this filter.</div>');
      updateSelectedJobCount();
    }

    function updateSelectedJobCount() {
      $('#selectedJobCount').text(selectedJobList().length);
    }

    function selectedJobList() {
      var selected = [];
      var included = {};

      $.each(visibleJobs, function(index, job) {
        var name = job.fullName || job.name || '';
        if (selectedJobNames[name] && jobMatchesEnvironmentFilter(job)) {
          selected.push(name);
          included[name] = true;
        }
      });

      $.each(Object.keys(selectedJobNames).sort(), function(index, name) {
        if (! included[name] && jobsByName[name] && jobMatchesEnvironmentFilter(jobsByName[name])) {
          selected.push(name);
        }
      });

      return selected;
    }

    function setSelectorBusy(isBusy) {
      $('#jobSelectorOverlay').toggle(isBusy);
      $('#reloadJobs').prop('disabled', isBusy);
    }

    function setDetailBusy(isBusy, message) {
      $('#jobViewOverlay').toggle(isBusy);
      $('#loadSelected').prop('disabled', isBusy);
      if (message) {
        $('#jobViewStatus').text(message);
      }
    }

    function applyRequestedJobs() {
      if (requestedJobsApplied || requestedJobs.length === 0) {
        return;
      }

      requestedJobsApplied = true;
      $.each(requestedJobs, function(index, jobName) {
        selectedJobNames[jobName] = true;
        if (! jobsByName[jobName]) {
          jobsByName[jobName] = {name: jobName, fullName: jobName, buildable: true, environmentInfo: environmentHelper.detectFromName(jobName)};
          visibleJobs.unshift(jobsByName[jobName]);
        }
      });

      renderJobOptions($('#jobFilter').val());
      loadSelectedJobs();
    }

    function loadJobs() {
      if (! jenkinsUrl) {
        toastr.error('Jenkins URL is not configured.', 'Jenkins');
        return;
      }

      setSelectorBusy(true);
      $('#jobSelectorList').html('<div class="text-muted text-center" style="padding: 24px;">Loading Jenkins jobs...</div>');

      jenkinsRequest('api/json?tree=jobs[name,fullName,displayName,color,description,buildable,inQueue,nextBuildNumber,lastBuild[number,result,building,timestamp,duration],healthReport[description,score]]')
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
            job.environmentInfo = environmentHelper.detectFromJob(job);
            jobsByName[name] = job;
          });

          renderJobOptions($('#jobFilter').val());
          applyRequestedJobs();
          hydrateJobEnvironments();
        })
        .fail(function(xhr) {
          toastr.error(responseMessage(xhr, 'Unable to load Jenkins jobs.'), 'Jenkins');
          $('#jobSelectorList').html('<div class="text-danger text-center" style="padding: 24px;">Unable to load Jenkins jobs.</div>');
        })
        .always(function() {
          setSelectorBusy(false);
        });
    }

    function fetchJobDetails(jobName) {
      var deferred = $.Deferred();
      var jobPath = jenkinsJobPath(jobName);
      var tree = 'name,fullName,displayName,description,url,color,buildable,inQueue,disabled,nextBuildNumber,queueItem[id,why],healthReport[description,score],lastBuild[number,result,timestamp,duration,building,url],lastCompletedBuild[number,result,timestamp,duration,url],lastSuccessfulBuild[number,result,timestamp,duration,url],lastFailedBuild[number,result,timestamp,duration,url],lastStableBuild[number,result,timestamp,duration,url],lastUnstableBuild[number,result,timestamp,duration,url],lastUnsuccessfulBuild[number,result,timestamp,duration,url],builds[number,result,timestamp,duration,building,url,description]{0,5}';

      jenkinsRequest(jobPath + '/api/json?tree=' + tree)
        .done(function(data) {
          var configRequest = jenkinsRequest(jobPath + '/config.xml', 'GET', {dataType: 'text'})
            .then(function(xmlText) {
              return {xmlText: xmlText || '', config: parseJobConfig(xmlText || '', jobName), error: ''};
            }, function(xhr) {
              return {xmlText: '', config: parseJobConfig('', jobName), error: responseMessage(xhr, 'Unable to fetch config.xml.')};
            });

          var consoleRequest = $.Deferred().resolve({consoleText: '', error: ''}).promise();
          if (data.lastBuild && data.lastBuild.number) {
            consoleRequest = jenkinsRequest(jobPath + '/lastBuild/consoleText', 'GET', {dataType: 'text'})
              .then(function(consoleText) {
                return {consoleText: consoleText || '', error: ''};
              }, function(xhr) {
                return {consoleText: '', error: responseMessage(xhr, 'Unable to fetch latest console output.')};
              });
          }

          $.when(configRequest, consoleRequest).done(function(configResult, consoleResult) {
            var fullName = data.fullName || data.name || jobName;
            deferred.resolve({
              name: fullName,
              displayName: data.displayName || fullName,
              description: data.description || '',
              color: data.color || '',
              buildable: data.buildable !== false,
              disabled: data.disabled === true || data.buildable === false,
              inQueue: data.inQueue === true,
              queueWhy: data.queueItem && data.queueItem.why ? data.queueItem.why : '',
              nextBuildNumber: data.nextBuildNumber || '',
              healthReport: Array.isArray(data.healthReport) ? data.healthReport : [],
              lastBuild: data.lastBuild || null,
              lastCompletedBuild: data.lastCompletedBuild || null,
              lastSuccessfulBuild: data.lastSuccessfulBuild || null,
              lastFailedBuild: data.lastFailedBuild || null,
              lastStableBuild: data.lastStableBuild || null,
              lastUnstableBuild: data.lastUnstableBuild || null,
              lastUnsuccessfulBuild: data.lastUnsuccessfulBuild || null,
              builds: Array.isArray(data.builds) ? data.builds : [],
              config: configResult.config || parseJobConfig('', fullName),
              configXml: configResult.xmlText || '',
              configError: configResult.error || '',
              consoleText: consoleResult.consoleText || '',
              consoleError: consoleResult.error || '',
              jenkinsUrl: jenkinsJobUrl(fullName),
              status: statusText(data)
            });
          });
        })
        .fail(function(xhr) {
          deferred.resolve({
            name: jobName,
            displayName: jobName,
            error: responseMessage(xhr, 'Unable to load this Jenkins job.'),
            status: 'Unavailable',
            buildable: false,
            disabled: true,
            inQueue: false,
            healthReport: [],
            builds: [],
            config: parseJobConfig('', jobName),
            configXml: '',
            consoleText: '',
            jenkinsUrl: jenkinsJobUrl(jobName)
          });
        });

      return deferred.promise();
    }

    function loadSelectedJobs() {
      var jobs = selectedJobList();

      if (jobs.length === 0) {
        toastr.error('Select one or more jobs to view.', 'View Job');
        return;
      }

      setDetailBusy(true, 'Loading details for ' + jobs.length + ' job(s)...');
      $('#jobViewEmpty').hide();
      $('#jobCompareWrapper').show().html('<div class="text-muted text-center" style="padding: 24px;">Loading job comparison...</div>');
      $('#jobDetailsGrid').html('');

      var requests = $.map(jobs, function(jobName) {
        return fetchJobDetails(jobName);
      });

      $.when.apply($, requests).done(function() {
        var details = requests.length === 1 ? [arguments[0]] : Array.prototype.slice.call(arguments);
        renderSummary(details);
        renderComparison(details);
        renderDetails(details);
        $('#jobViewStatus').text('Loaded ' + details.length + ' job(s).');
      }).always(function() {
        setDetailBusy(false);
      });
    }

    function renderSummary(details) {
      var loaded = details.length;
      var buildable = 0;
      var active = 0;
      var problems = 0;

      $.each(details, function(index, detail) {
        var state = detail.status || statusText(detail);
        if (detail.buildable && ! detail.disabled && ! detail.error) {
          buildable += 1;
        }
        if ($.inArray(state, ['Running', 'Queued']) !== -1) {
          active += 1;
        }
        if (detail.error || $.inArray(state, ['FAILURE', 'ABORTED', 'UNSTABLE', 'NOT_BUILT', 'Unavailable']) !== -1) {
          problems += 1;
        }
      });

      $('#summaryLoaded').text(loaded);
      $('#summaryBuildable').text(buildable);
      $('#summaryActive').text(active);
      $('#summaryProblems').text(problems);
    }

    function healthText(detail) {
      if (! detail.healthReport || detail.healthReport.length === 0) {
        return renderMuted('None');
      }

      var health = detail.healthReport[0];
      var score = health.score == null ? '' : health.score + '%';
      var description = health.description || '';
      return '<strong>' + escapeHtml(score || 'Reported') + '</strong><br><small>' + escapeHtml(description || 'No description') + '</small>';
    }

    function commandSummary(detail) {
      return detail.config && detail.config.commands.length ? renderPre(detail.config.commands.join('\n\n'), 'job-view-mini-pre') : renderMuted('None');
    }

    function renderComparison(details) {
      if (details.length === 0) {
        $('#jobViewEmpty').show().html(
          '<i class="fa fa-columns fa-3x"></i>' +
          '<h4>No job details loaded yet</h4>' +
          '<p>Select one or more Jenkins jobs, then load details to compare configuration, build history, health, and latest console output.</p>'
        );
        $('#jobCompareWrapper').hide().empty();
        return;
      }

      if (details.length === 1) {
        $('#jobViewEmpty').show().html(
          '<i class="fa fa-columns fa-3x"></i>' +
          '<h4>Select another job to compare</h4>' +
          '<p>Detailed configuration for ' + escapeHtml(details[0].name) + ' is shown below.</p>'
        );
        $('#jobCompareWrapper').hide().empty();
        return;
      }

      var rows = [
        {label: 'Status', render: function(detail) { return statusLabel(detail.status); }},
        {label: 'Environment', render: function(detail) { return renderEnvironmentInfo(detail.config ? detail.config.environmentInfo : environmentHelper.detectFromName(detail.name)); }},
        {label: 'Health', render: healthText},
        {label: 'Buildable', render: function(detail) { return boolLabel(detail.buildable && ! detail.disabled); }},
        {label: 'In Queue', render: function(detail) { return detail.inQueue ? '<span class="label label-warning">Queued</span><br><small>' + escapeHtml(detail.queueWhy || '') + '</small>' : '<span class="label label-default">No</span>'; }},
        {label: 'Runtime', render: function(detail) { return renderRuntimeBadges(detail.config); }},
        {label: 'Docker Image', render: function(detail) { return detail.config && detail.config.runtime && detail.config.runtime.dockerImage ? renderValue(detail.config.runtime.dockerImage) : renderMuted('No Docker image'); }},
        {label: 'Entrypoint', render: function(detail) { return detail.config && detail.config.runtime ? renderValue(detail.config.runtime.entryPoint) : renderMuted('None'); }},
        {label: 'Next Build', render: function(detail) { return renderValue(detail.nextBuildNumber); }},
        {label: 'Last Build', render: function(detail) { return renderBuild(detail.lastBuild); }},
        {label: 'Last Success', render: function(detail) { return renderBuild(detail.lastSuccessfulBuild); }},
        {label: 'Last Failure', render: function(detail) { return renderBuild(detail.lastFailedBuild); }},
        {label: 'Schedule', render: function(detail) { return renderList(detail.config ? detail.config.schedules : []); }},
        {label: 'Command', render: commandSummary},
        {label: 'Downstream', render: function(detail) { return renderList(detail.config ? detail.config.downstream : []); }},
        {label: 'Mail Recipients', render: function(detail) { return renderList(detail.config ? detail.config.mailRecipients.concat([detail.config.emailDefaults.recipients || '']) : []); }},
        {label: 'Parameters', render: function(detail) { return detail.config && detail.config.parameters.length ? renderList($.map(detail.config.parameters, function(parameter) { return parameter.name + ' [' + parameter.type + ']'; })) : renderMuted('None'); }}
      ];

      var html = '<table class="table table-bordered table-condensed job-compare-table"><thead><tr><th>Detail</th>';
      $.each(details, function(index, detail) {
        html += '<th>' + escapeHtml(detail.name) + '<br>' + (detail.jenkinsUrl ? '<a href="' + escapeAttribute(detail.jenkinsUrl) + '" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Jenkins</a>' : '') + '</th>';
      });
      html += '</tr></thead><tbody>';

      $.each(rows, function(rowIndex, row) {
        html += '<tr><td><strong>' + escapeHtml(row.label) + '</strong></td>';
        $.each(details, function(detailIndex, detail) {
          html += '<td>' + (detail.error ? '<span class="text-danger">' + escapeHtml(detail.error) + '</span>' : row.render(detail)) + '</td>';
        });
        html += '</tr>';
      });

      html += '</tbody></table>';
      $('#jobCompareWrapper').show().html(html);
    }

    function renderBuildHistory(builds) {
      if (! builds || builds.length === 0) {
        return renderMuted('No builds yet');
      }

      var html = '<div class="table-responsive"><table class="table table-condensed table-striped job-build-table"><thead><tr><th>Build</th><th>Result</th><th>Started</th><th>Duration</th></tr></thead><tbody>';
      $.each(builds, function(index, build) {
        html += '<tr>' +
          '<td><strong>#' + escapeHtml(build.number || '') + '</strong></td>' +
          '<td>' + statusLabel(build.building === true ? 'Running' : (build.result || 'No result')) + '</td>' +
          '<td>' + escapeHtml(formatTime(build.timestamp)) + '</td>' +
          '<td>' + escapeHtml(formatDuration(build.duration)) + '</td>' +
        '</tr>';
      });
      html += '</tbody></table></div>';
      return html;
    }

    function renderParameters(parameters) {
      if (! parameters || parameters.length === 0) {
        return renderMuted('None');
      }

      var html = '<div class="table-responsive"><table class="table table-condensed table-bordered job-small-table"><thead><tr><th>Name</th><th>Type</th><th>Default</th><th>Description</th></tr></thead><tbody>';
      $.each(parameters, function(index, parameter) {
        html += '<tr>' +
          '<td>' + escapeHtml(parameter.name || '') + '</td>' +
          '<td>' + escapeHtml(parameter.type || '') + '</td>' +
          '<td>' + escapeHtml(parameter.defaultValue || '') + '</td>' +
          '<td>' + escapeHtml(parameter.description || '') + '</td>' +
        '</tr>';
      });
      html += '</tbody></table></div>';
      return html;
    }

    function renderEmailConfig(config) {
      var lines = [];

      if (config.mailRecipients.length) {
        lines.push('Mailer: ' + config.mailRecipients.join(', '));
      }

      if (config.emailDefaults.recipients || config.emailDefaults.subject || config.emailDefaults.from) {
        lines.push('Email-ext recipients: ' + (config.emailDefaults.recipients || 'None'));
        lines.push('Email-ext from: ' + (config.emailDefaults.from || 'None'));
        lines.push('Email-ext subject: ' + (config.emailDefaults.subject || 'None'));
      }

      $.each(config.emailTriggers, function(index, trigger) {
        lines.push(trigger.name + ': ' + (trigger.recipients || 'default recipients') + (trigger.attachBuildLog ? ' / attach log: ' + trigger.attachBuildLog : ''));
      });

      return renderList(lines);
    }

    function renderDetails(details) {
      var html = '';

      $.each(details, function(index, detail) {
        if (detail.error) {
          html += '<div class="box box-danger job-detail-card"><div class="box-header with-border"><h3 class="box-title"><b>' + escapeHtml(detail.name) + '</b></h3></div><div class="box-body"><p class="text-danger">' + escapeHtml(detail.error) + '</p></div></div>';
          return;
        }

        var config = detail.config || parseJobConfig('', detail.name);
        var consoleText = detail.consoleText || '';
        var boxClass = detail.status === 'FAILURE' || detail.status === 'ABORTED' || detail.status === 'UNSTABLE' ? 'box-danger' : (detail.status === 'SUCCESS' ? 'box-success' : 'box-primary');
        var description = detail.description || 'No description.';
        var downstream = config.downstream.slice();

        if (config.downstreamConditions.length) {
          downstream.push('Conditions: ' + config.downstreamConditions.join(', '));
        }

        html += '<div class="box ' + boxClass + ' job-detail-card">' +
          '<div class="box-header with-border">' +
            '<h3 class="box-title"><b>' + escapeHtml(detail.name) + '</b> ' + environmentHelper.label(config.environmentInfo) + '</h3>' +
            '<div class="box-tools pull-right job-detail-actions">' +
              (detail.jenkinsUrl ? '<a class="btn btn-box-tool" href="' + escapeAttribute(detail.jenkinsUrl) + '" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Jenkins</a>' : '') +
              (detail.jenkinsUrl ? '<a class="btn btn-box-tool" href="' + escapeAttribute(detail.jenkinsUrl + 'configure') + '" target="_blank" rel="noopener"><i class="fa fa-cog"></i> Configure</a>' : '') +
            '</div>' +
          '</div>' +
          '<div class="box-body">' +
            '<div class="job-overview-grid">' +
              metric('Status', statusLabel(detail.status)) +
              metric('Environment', renderEnvironmentInfo(config.environmentInfo)) +
              metric('Health', detail.healthReport.length ? escapeHtml((detail.healthReport[0].score == null ? 'Reported' : detail.healthReport[0].score + '%')) : renderMuted('None')) +
              metric('Buildable', boolLabel(detail.buildable && ! detail.disabled)) +
              metric('Queue', detail.inQueue ? '<span class="label label-warning">Queued</span>' : '<span class="label label-default">None</span>') +
              metric('Next Build', renderValue(detail.nextBuildNumber)) +
              metric('Last Build', renderBuild(detail.lastBuild)) +
              metric('Last Success', renderBuild(detail.lastSuccessfulBuild)) +
              metric('Last Failure', renderBuild(detail.lastFailedBuild)) +
            '</div>' +
            '<div class="job-detail-section"><h4>Description</h4><p>' + escapeHtml(description) + '</p>' + healthText(detail) + '</div>' +
            '<div class="job-detail-section"><h4>Execution Runtime</h4>' + renderRuntimeConfig(config) + '</div>' +
            '<div class="job-detail-section"><h4>Build History</h4>' + renderBuildHistory(detail.builds) + '</div>' +
            '<div class="row">' +
              '<div class="col-md-6"><div class="job-detail-section"><h4>Schedule</h4>' + renderList(config.schedules) + '</div></div>' +
              '<div class="col-md-6"><div class="job-detail-section"><h4>Timeouts</h4>' + renderList(config.timeouts) + '</div></div>' +
            '</div>' +
            '<div class="job-detail-section"><h4>Command</h4>' + renderPre(config.commands.join('\n\n'), 'job-command-pre') + '</div>' +
            '<div class="row">' +
              '<div class="col-md-6"><div class="job-detail-section"><h4>Parameters</h4>' + renderParameters(config.parameters) + '</div></div>' +
              '<div class="col-md-6"><div class="job-detail-section"><h4>SCM</h4>' + renderList(config.scmUrls) + '</div></div>' +
            '</div>' +
            '<div class="row">' +
              '<div class="col-md-6"><div class="job-detail-section"><h4>Downstream Jobs</h4>' + renderList(downstream) + '</div></div>' +
              '<div class="col-md-6"><div class="job-detail-section"><h4>Email Notifications</h4>' + renderEmailConfig(config) + '</div></div>' +
            '</div>' +
            '<div class="job-detail-section"><h4>Latest Console Output</h4>' + (detail.consoleError ? '<p class="text-warning">' + escapeHtml(detail.consoleError) + '</p>' : '') + renderPre(consoleText || 'No console output available.', 'job-console-pre') + '</div>' +
            '<div class="job-detail-section">' +
              '<details class="job-xml-details"><summary>config.xml</summary>' + (detail.configError ? '<p class="text-warning">' + escapeHtml(detail.configError) + '</p>' : '') + renderPre(detail.configXml || 'No config.xml available.', 'job-xml-pre') + '</details>' +
            '</div>' +
          '</div>' +
        '</div>';
      });

      $('#jobDetailsGrid').html(html);
    }

    $('#jobFilter').on('keyup', function() {
      renderJobOptions($(this).val());
    });

    $('#jobEnvironmentFilter').on('change', function() {
      jobEnvironmentFilter = normalizeEnvironmentFilterValue($(this).val() || 'all');
      renderJobOptions($('#jobFilter').val());
    });

    $(document).on('change', '.job-view-job-check', function() {
      if (this.checked) {
        selectedJobNames[this.value] = true;
      } else {
        delete selectedJobNames[this.value];
      }

      updateSelectedJobCount();
    });

    $('#selectVisibleJobs').on('click', function() {
      $('.job-view-job-check').each(function() {
        this.checked = true;
        selectedJobNames[this.value] = true;
      });

      updateSelectedJobCount();
    });

    $('#clearSelectedJobs').on('click', function() {
      selectedJobNames = {};
      $('.job-view-job-check').prop('checked', false);
      updateSelectedJobCount();
    });

    $('#reloadJobs').on('click', function() {
      loadJobs();
    });

    $('#loadSelected').on('click', function() {
      loadSelectedJobs();
    });

    loadJobs();
  });
</script>