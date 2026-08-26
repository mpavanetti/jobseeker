<!-- <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse');
  });
</script>-->
<style>
pre {
  white-space: pre-wrap;
  word-break: break-word;
}

.full-job-filter-box .box-body {
  padding-bottom: 10px;
}

.full-job-filter-actions {
  align-items: stretch;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding-top: 25px;
}

.full-job-filter-actions .btn {
  flex: 1 1 110px;
}

.full-job-filter-note {
  color: #777;
  margin-bottom: 0;
}

.full-job-environment-line {
  margin-top: 6px;
}

.full-job-environment-line .label,
.full-job-environment-cell .label {
  display: inline-block;
}

.full-job-summary {
  margin-top: 10px;
}

.full-job-summary .info-box {
  min-height: 72px;
  margin-bottom: 12px;
}

.full-job-summary .info-box-icon {
  height: 72px;
  line-height: 72px;
}

.full-job-summary .info-box-content {
  padding-top: 10px;
}

.full-job-table-wrapper {
  overflow-x: auto;
}

#fetch th,
#fetch td {
  vertical-align: middle !important;
}

#fetch th {
  white-space: nowrap;
}

.full-job-build-cell strong,
.full-job-build-cell small {
  display: block;
}

.full-job-build-cell small {
  color: #777;
  margin-top: 3px;
  max-width: 210px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.full-job-log-preview {
  max-width: 260px;
  white-space: normal;
}

.full-job-log-preview mark {
  background: #fff2a8;
  padding: 0 2px;
}

#addLog pre {
  max-height: 560px;
  overflow: auto;
}

.full-job-actions {
  display: inline-flex;
  flex-wrap: nowrap;
  white-space: nowrap;
}

.full-job-actions > .btn {
  float: none;
}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1>
     <b>Full Job Build List</b>
     <small>Quick access to your jobs build logs.</small>
   </h1>
   <ol class="breadcrumb">
    <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
    <li><a href="#">Job Management</a></li>
    <li class="active">Job List</li>
  </ol>
</section>

<!-- Main content -->
<section class="content">
<div class="container">
  <div class="box box-primary full-job-filter-box animated fadeIn" style="margin-top: 25px;">
    <div class="box-header with-border">
      <h3 class="box-title"><b>Build Filters</b></h3>
    </div>
    <div class="box-body">
      <form id="searchList" autocomplete="off">
        <div class="row">
          <div class="col-lg-3 col-md-4 col-sm-6 col-xs-12 form-group">
            <label for="job_name">Job Name</label>
            <select class="form-control" name="job_name" id="job_name">
              <option value="">Loading Jenkins jobs...</option>
            </select>
          </div>
          <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12 form-group">
            <label for="resultFilter">Result</label>
            <select class="form-control" id="resultFilter" name="resultFilter">
              <option value="all">All results</option>
              <option value="SUCCESS">Success</option>
              <option value="FAILURE">Failure</option>
              <option value="ABORTED">Aborted</option>
              <option value="UNSTABLE">Unstable</option>
              <option value="RUNNING">Running</option>
              <option value="NO_RESULT">No result</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
          <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12 form-group">
            <label for="dateFrom">From date/time</label>
            <input type="datetime-local" class="form-control" id="dateFrom" name="dateFrom" step="60">
          </div>
          <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12 form-group">
            <label for="dateTo">To date/time</label>
            <input type="datetime-local" class="form-control" id="dateTo" name="dateTo" step="60">
          </div>
          <div class="col-lg-3 col-md-4 col-sm-6 col-xs-12 form-group">
            <label for="logText">Console contains</label>
            <input type="text" class="form-control" id="logText" name="logText" placeholder="Text inside build logs">
          </div>
        </div>
        <div class="row">
          <div class="col-lg-2 col-md-3 col-sm-6 col-xs-12 form-group">
            <label for="buildFrom">Build # from</label>
            <input type="number" class="form-control" id="buildFrom" name="buildFrom" min="1" autocomplete="off">
          </div>
          <div class="col-lg-2 col-md-3 col-sm-6 col-xs-12 form-group">
            <label for="buildTo">Build # to</label>
            <input type="number" class="form-control" id="buildTo" name="buildTo" min="1" autocomplete="off">
          </div>
          <div class="col-lg-2 col-md-3 col-sm-6 col-xs-12 form-group">
            <label for="rowLimit">Fetch latest builds</label>
            <input type="number" class="form-control" id="rowLimit" name="rowLimit" min="1" max="500" value="100" autocomplete="off">
          </div>
          <div class="col-lg-2 col-md-3 col-sm-6 col-xs-12 form-group">
            <label>&nbsp;</label>
            <div class="checkbox" style="margin-top: 4px;">
              <label><input type="checkbox" id="caseSensitiveLog" name="caseSensitiveLog"> Case-sensitive log text</label>
            </div>
          </div>
          <div class="col-lg-4 col-md-12 col-sm-12 col-xs-12 form-group">
            <div class="full-job-filter-actions">
              <button id="search" type="submit" class="btn btn-primary"><i class="fa fa-search" aria-hidden="true"></i> Search</button>
              <button id="reload" type="button" class="btn btn-default"><i class="fa fa-refresh" aria-hidden="true"></i> Refresh</button>
              <button id="resetFilters" type="button" class="btn btn-default"><i class="fa fa-eraser" aria-hidden="true"></i> Reset</button>
            </div>
          </div>
        </div>
      </form>
      <p class="full-job-filter-note" id="fullJobFilterStatus">Select a Jenkins job, then search builds. Console text filtering checks the logs from the fetched builds.</p>
      <p class="full-job-filter-note full-job-environment-line" id="selectedJobEnvironment"><i class="fa fa-globe"></i> Environment: <span id="selectedJobEnvironmentLabel"><span class="label label-default">Unknown</span></span></p>
    </div>
  </div>

  <div class="row full-job-summary">
    <div class="col-sm-6 col-md-3">
      <div class="info-box">
        <span class="info-box-icon bg-aqua"><i class="fa fa-download"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Fetched</span>
          <span class="info-box-number" id="summaryFetched">0</span>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="info-box">
        <span class="info-box-icon bg-yellow"><i class="fa fa-filter"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Matching</span>
          <span class="info-box-number" id="summaryMatching">0</span>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="info-box">
        <span class="info-box-icon bg-green"><i class="fa fa-check"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Success</span>
          <span class="info-box-number" id="summarySuccess">0</span>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="info-box">
        <span class="info-box-icon bg-red"><i class="fa fa-exclamation-triangle"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Problems</span>
          <span class="info-box-number" id="summaryProblems">0</span>
        </div>
      </div>
    </div>
  </div>


<div class="modal fade" id="modal-default" style="display: none;">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span></button>
          <h4 class="modal-title">Job Build Console Log</h4>
        </div>
        <div class="modal-body" id="addLog">
          
       </div>
       <div class="modal-footer">
        <button type="button" class="btn btn-primary pull-left" data-dismiss="modal">Close</button>
      </div>
    </div>
    <!-- /.modal-content -->
  </div>
  <!-- /.modal-dialog -->
</div>

<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box" class="box box box-primary collapsed-box">
      <div class="overlay" style="display:none;">
        <i class="fa fa-refresh fa-spin"></i>
      </div>
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Available Jobs</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <div class="full-job-table-wrapper">
        <table id="fetch" class="table table-bordered table-striped" style="width: 100%;">
          <thead>
            <tr>
              <th>Build</th>
              <th>Environment</th>
              <th>Worker</th>
              <th>Result</th>
              <th>Started</th>
              <th>Duration</th>
              <th>Queue</th>
              <th>Console Match</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
      </table>
      </div>
    </div>
    <!-- /.box-body -->
  </div>
  <!-- /.box -->
</div>
<!-- /.col -->
</div>
<!-- /.row -->
</div>
</section>
<!-- /.content -->
</div>
<!-- /.content-wrapper -->

<script type="text/javascript">
  $(document).ready(function(){
    $('#box').boxWidget('collapse');
  });
</script>

<script type="text/javascript">
  $(document).ready(function() {
    var jenkins_url = '<?php echo $jenkins_url; ?>';
    var jenkins_username = '';
    var jenkins_token = '';
    var availableJobsUrl = <?php echo json_encode(base_url() . 'jobCreation/availableJobs'); ?>;
    var jobEnvironmentFilter = window.jobseekerDashboardEnvironment || 'all';
    var logCache = {};
    var currentLogQuery = '';
    var jobMetadata = {};
    var selectedJobEnvironmentInfo = {environment: 'Unknown', source: 'Not detected', unknown: true};
    var environmentHelper = window.JobSeekerEnvironment || {
      detectFromConfig: function(xmlText, jobName) { return this.detectFromJob({name: jobName}); },
      detectFromJob: function() { return {environment: 'Unknown', source: 'Not detected', unknown: true}; },
      label: function() { return '<span class="label label-default">Unknown</span>'; },
      text: function(info) { return info && info.environment ? info.environment : 'Unknown'; }
    };

    loadJobs();

    function fullJobEnvironmentRequestValue() {
      var value = jobEnvironmentFilter || 'all';

      if (String(value).toLowerCase() === 'all') {
        return 'all';
      }

      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
        return window.JobSeekerGlobalEnvironment.normalize(value);
      }

      if (window.JobSeekerEnvironment && window.JobSeekerEnvironment.normalize) {
        return window.JobSeekerEnvironment.normalize(value) || value;
      }

      return String(value || '').toUpperCase();
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
      return escapeHtml(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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

    function jenkinsBuildUrl(build) {
      var baseUrl = browserJenkinsBaseUrl(jenkins_url);

      if (! build || ! build.jobName || build.number == null || build.number === '') {
        return '';
      }

      if (baseUrl.charAt(baseUrl.length - 1) !== '/') {
        baseUrl += '/';
      }

      return baseUrl + jenkinsJobPath(build.jobName) + '/' + encodeURIComponent(build.number) + '/';
    }

    function buildsFromJenkinsResponse(json) {
      return json && Array.isArray(json.builds) ? json.builds : [];
    }

    function destroyDataTable(selector) {
      if ($.fn.DataTable.isDataTable(selector)) {
        $(selector).DataTable().clear().destroy();
      }
    }

    function setBusy(isBusy, message) {
      $('.overlay').toggle(isBusy);
      $('#search, #reload, #resetFilters').prop('disabled', isBusy);
      if (message) {
        $('#fullJobFilterStatus').text(message);
      }
    }

    function updateSummary(fetchedBuilds, matchingBuilds) {
      fetchedBuilds = fetchedBuilds || [];
      matchingBuilds = matchingBuilds || [];

      $('#summaryFetched').text(fetchedBuilds.length);
      $('#summaryMatching').text(matchingBuilds.length);
      $('#summarySuccess').text(matchingBuilds.filter(function(build) { return build.result === 'SUCCESS' && build.building !== true; }).length);
      $('#summaryProblems').text(matchingBuilds.filter(function(build) {
        return $.inArray(build.result, ['FAILURE', 'ABORTED', 'UNSTABLE', 'NOT_BUILT']) !== -1;
      }).length);
    }

    function renderBuildTime(data) {
      var timestamp = parseInt(data, 10);
      return timestamp ? moment(timestamp).format('YYYY-MM-DD HH:mm:ss') : '';
    }

    function renderDuration(data) {
      var duration = parseInt(data, 10);
      return ! isNaN(duration) ? moment(duration).utc().format('HH [h] mm [m] ss [s]') : '';
    }

    function renderResult(build) {
      if (build.building === true) {
        return '<span class="label label-info">Running</span>';
      }

      if (build.result === 'SUCCESS') {
        return '<span class="label label-success">SUCCESS</span>';
      }

      if ($.inArray(build.result, ['FAILURE', 'ABORTED', 'UNSTABLE', 'NOT_BUILT']) !== -1) {
        return '<span class="label label-danger">' + escapeHtml(build.result) + '</span>';
      }

      return build.result ? '<span class="label label-warning">' + escapeHtml(build.result) + '</span>' : '<span class="text-muted">No result</span>';
    }

    function renderBoolean(value) {
      return value === true || value === 'true' ? '<span class="label label-info">Yes</span>' : '<span class="label label-default">No</span>';
    }

    function renderBuildIdentity(build) {
      return '<div class="full-job-build-cell"><strong>#' + renderText(build.number) + '</strong><small title="' + escapeAttribute(build.jobName) + '">' + renderText(build.jobName) + '</small></div>';
    }

    function renderEnvironment(build) {
      var info = build && build.environmentInfo ? build.environmentInfo : selectedJobEnvironmentInfo;
      return '<span class="full-job-environment-cell">' + environmentHelper.label(info) + '</span>';
    }

    function renderWorkerNode(build) {
      var builtOn = $.trim(String(build && build.builtOn != null ? build.builtOn : ''));
      return builtOn ? renderText(builtOn) : '<span class="text-muted">Controller</span>';
    }

    function setSelectedJobEnvironment(info) {
      selectedJobEnvironmentInfo = info || {environment: 'Unknown', source: 'Not detected', unknown: true};
      $('#selectedJobEnvironmentLabel').html(environmentHelper.label(selectedJobEnvironmentInfo) + ' <small>' + escapeHtml(selectedJobEnvironmentInfo.source || 'Not detected') + '</small>');
    }

    function loadSelectedJobEnvironment(jobName) {
      var fallback = environmentHelper.detectFromJob(jobMetadata[jobName] || {name: jobName, fullName: jobName});
      setSelectedJobEnvironment(fallback);

      if (! jobName) {
        return $.Deferred().resolve(fallback).promise();
      }

      return $.ajax({
        contentType: 'application/text',
        url: jenkins_url + jenkinsJobPath(jobName) + '/config.xml',
        method: 'GET',
        headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
      }).then(function(xmlText) {
        var info = environmentHelper.detectFromConfig(xmlText || '', jobName);
        setSelectedJobEnvironment(info);
        return info;
      }, function() {
        return fallback;
      });
    }

    function renderQueue(build) {
      var queueId = build.queueId == null || build.queueId === '' ? '<span class="text-muted">None</span>' : renderText(build.queueId);
      var state = build.building === true ? '<span class="label label-info">Running</span>' : '<span class="label label-default">Finished</span>';

      return queueId + '<br>' + state;
    }

    function renderText(value) {
      return escapeHtml(value == null ? '' : value);
    }

    function renderLogPreview(build) {
      if (! currentLogQuery) {
        return '<span class="text-muted">No text filter</span>';
      }

      if (build.consolePreview) {
        return '<div class="full-job-log-preview">' + build.consolePreview + '</div>';
      }

      if (build.consoleError) {
        return '<span class="text-warning">Console unavailable</span>';
      }

      return '<span class="text-muted">No match preview</span>';
    }

    function renderActions(build) {
      var jenkinsUrl = jenkinsBuildUrl(build);
      var jenkinsButton = jenkinsUrl ? '<a class="btn btn-sm btn-default" target="_blank" rel="noopener" href="' + escapeAttribute(jenkinsUrl) + '"><i class="fa fa-external-link"></i> Jenkins</a>' : '';

      return '<div class="btn-group btn-group-xs full-job-actions"><button type="button" class="btn btn-info log" data-job="' + escapeAttribute(build.jobName) + '" data-build="' + escapeAttribute(build.number) + '" data-result="' + escapeAttribute(build.result || '') + '" data-date="' + escapeAttribute(renderBuildTime(build.timestamp)) + '" data-environment="' + escapeAttribute(environmentHelper.text(build.environmentInfo)) + '"><i class="fa fa-terminal"></i> Logs</button>' + jenkinsButton + '</div>';
    }

    function renderBuildTable(builds) {
      destroyDataTable('#fetch');
      $('#box').boxWidget('expand');
      var buildTable = $('#fetch').DataTable({
        data: builds,
        lengthMenu: [5,10,20,50,100,200,500],
        pageLength: 10,
        order: [[4, 'desc']],
        scrollX: true,
        language: {
          emptyTable: 'Search builds to populate this report.'
        },
        columns: [
          {data: null, defaultContent: '', render: function(data, type, row) { return type === 'sort' ? parseInt(row.number, 10) || 0 : renderBuildIdentity(row); }},
          {data: null, defaultContent: '', render: function(data, type, row) { return type === 'sort' || type === 'type' ? environmentHelper.text(row.environmentInfo) : renderEnvironment(row); }},
          {data: null, defaultContent: '', render: function(data, type, row) { return type === 'sort' || type === 'type' ? String(row.builtOn || '') : renderWorkerNode(row); }},
          {data: null, defaultContent: '', render: function(data, type, row) { return renderResult(row); }},
          {data: 'timestamp', defaultContent: '', render: function(data, type) { return type === 'sort' || type === 'type' ? parseInt(data, 10) || 0 : renderBuildTime(data); }},
          {data: 'duration', defaultContent: '', render: function(data, type) { return type === 'sort' || type === 'type' ? parseInt(data, 10) || 0 : renderDuration(data); }},
          {data: null, defaultContent: '', render: function(data, type, row) { return type === 'sort' ? parseInt(row.queueId, 10) || 0 : renderQueue(row); }},
          {data: null, defaultContent: '', orderable: false, render: function(data, type, row) { return renderLogPreview(row); }},
          {data: null, defaultContent: '', orderable: false, searchable: false, render: function(data, type, row) { return renderActions(row); }}
        ]
      });

      setTimeout(function() {
        buildTable.columns.adjust();
      }, 0);
    }

    function loadJobs() {
      setBusy(true, 'Loading Jenkins jobs...');

      $.ajax({
        url: availableJobsUrl,
        method: 'GET',
        data: {environment: fullJobEnvironmentRequestValue()}
      }).done(function(data) {
        var jobs = data && Array.isArray(data.jobs) ? data.jobs : [];
        var options = '<option value="">Select a Jenkins job</option>';
        jobMetadata = {};

        jobs.sort(function(left, right) {
          return String(left.fullName || left.name || '').localeCompare(String(right.fullName || right.name || ''));
        });

        $.each(jobs, function(index, item) {
          var name = item.fullName || item.name || '';
          if (name !== '') {
            jobMetadata[name] = item;
            options += '<option value="' + escapeAttribute(name) + '">' + escapeHtml(name) + '</option>';
          }
        });

        $('#job_name').html(options);
        $('#fullJobFilterStatus').text(jobs.length ? 'Select a Jenkins job, then search builds.' : 'No Jenkins jobs were returned.');
      }).fail(function(xhr) {
        toastr.error(responseMessage(xhr, 'Unable to load Jenkins jobs.'), 'Job Query Failed');
        $('#job_name').html('<option value="">Unable to load Jenkins jobs</option>');
      }).always(function() {
        setBusy(false);
      });
    }

    $(document).on('jobseeker:environment-change', function(event, environment) {
      jobEnvironmentFilter = environment || 'all';
      loadJobs();
    });

    function responseMessage(xhr, fallback) {
      return xhr && xhr.responseText ? xhr.responseText : fallback;
    }

    function dateTimeValue(value) {
      if (! value) {
        return null;
      }

      var timestamp = new Date(value).getTime();
      return isNaN(timestamp) ? null : timestamp;
    }

    function readFilters() {
      var rowLimit = parseInt($('#rowLimit').val(), 10);
      var buildFrom = parseInt($('#buildFrom').val(), 10);
      var buildTo = parseInt($('#buildTo').val(), 10);

      if (isNaN(rowLimit)) {
        rowLimit = 100;
      }

      rowLimit = Math.max(1, Math.min(rowLimit, 500));
      $('#rowLimit').val(rowLimit);

      return {
        jobName: $('#job_name').val() || '',
        result: $('#resultFilter').val() || 'all',
        dateFrom: $('#dateFrom').val() || '',
        dateTo: $('#dateTo').val() || '',
        fromTime: dateTimeValue($('#dateFrom').val()),
        toTime: dateTimeValue($('#dateTo').val()),
        buildFrom: isNaN(buildFrom) ? null : buildFrom,
        buildTo: isNaN(buildTo) ? null : buildTo,
        rowLimit: rowLimit,
        logText: $.trim($('#logText').val() || ''),
        caseSensitive: $('#caseSensitiveLog').is(':checked')
      };
    }

    function validateFilters(filters) {
      if (filters.jobName === '') {
        toastr.error('Please select a Jenkins job.', 'Job Name Required');
        return false;
      }

      if (filters.fromTime && filters.toTime && filters.fromTime > filters.toTime) {
        toastr.error('From date must be before To date.', 'Date Range Error');
        return false;
      }

      if (filters.buildFrom && filters.buildTo && filters.buildFrom > filters.buildTo) {
        toastr.error('Build # from must be less than Build # to.', 'Build Range Error');
        return false;
      }

      return true;
    }

    function fetchBuilds(filters) {
      return $.ajax({
        url: jenkins_url + jenkinsJobPath(filters.jobName) + '/api/json?tree=builds[number,fullDisplayName,result,timestamp,duration,builtOn,url,queueId,building]{0,' + filters.rowLimit + '}',
        method: 'GET',
        headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
      }).then(function(data) {
        return $.map(buildsFromJenkinsResponse(data), function(build) {
          build.jobName = filters.jobName;
          build.environmentInfo = selectedJobEnvironmentInfo;
          build.number = parseInt(build.number, 10) || build.number;
          return build;
        });
      });
    }

    function resultMatches(build, resultFilter) {
      var result = build.result || '';

      if (resultFilter === 'all') {
        return true;
      }

      if (resultFilter === 'RUNNING') {
        return build.building === true;
      }

      if (resultFilter === 'NO_RESULT') {
        return build.building !== true && result === '';
      }

      if (resultFilter === 'OTHER') {
        return build.building !== true && result !== '' && $.inArray(result, ['SUCCESS', 'FAILURE', 'ABORTED', 'UNSTABLE', 'NOT_BUILT']) === -1;
      }

      return result === resultFilter;
    }

    function applyBasicFilters(builds, filters) {
      return builds.filter(function(build) {
        var number = parseInt(build.number, 10);
        var timestamp = parseInt(build.timestamp, 10);

        if (! resultMatches(build, filters.result)) {
          return false;
        }

        if (filters.fromTime && (! timestamp || timestamp < filters.fromTime)) {
          return false;
        }

        if (filters.toTime && (! timestamp || timestamp > filters.toTime)) {
          return false;
        }

        if (filters.buildFrom && (! number || number < filters.buildFrom)) {
          return false;
        }

        if (filters.buildTo && (! number || number > filters.buildTo)) {
          return false;
        }

        return true;
      });
    }

    function consoleCacheKey(jobName, buildNumber) {
      return jobName + '#' + buildNumber;
    }

    function fetchConsole(jobName, buildNumber) {
      var key = consoleCacheKey(jobName, buildNumber);

      if (logCache.hasOwnProperty(key)) {
        return $.Deferred().resolve(logCache[key]).promise();
      }

      return $.ajax({
        contentType: 'application/text',
        url: jenkins_url + jenkinsJobPath(jobName) + '/' + encodeURIComponent(buildNumber) + '/consoleText',
        method: 'GET',
        headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
      }).then(function(output) {
        logCache[key] = output || '';
        return logCache[key];
      });
    }

    function logPreview(output, query, caseSensitive) {
      var haystack = caseSensitive ? output : output.toLowerCase();
      var needle = caseSensitive ? query : query.toLowerCase();
      var index = haystack.indexOf(needle);

      if (index === -1) {
        return '';
      }

      var start = Math.max(0, index - 80);
      var end = Math.min(output.length, index + query.length + 120);
      var before = escapeHtml(output.substring(start, index));
      var match = escapeHtml(output.substring(index, index + query.length));
      var after = escapeHtml(output.substring(index + query.length, end));

      return (start > 0 ? '...' : '') + before + '<mark>' + match + '</mark>' + after + (end < output.length ? '...' : '');
    }

    function filterByConsoleText(jobName, builds, query, caseSensitive) {
      var deferred = $.Deferred();

      if (! query) {
        deferred.resolve(builds);
        return deferred.promise();
      }

      if (builds.length === 0) {
        deferred.resolve([]);
        return deferred.promise();
      }

      var filtered = [];
      var index = 0;
      var active = 0;
      var completed = 0;
      var errors = 0;
      var concurrency = 4;

      function pump() {
        while (active < concurrency && index < builds.length) {
          (function(currentIndex) {
            var build = builds[currentIndex];
            active += 1;
            fetchConsole(jobName, build.number).done(function(output) {
              var preview = logPreview(output || '', query, caseSensitive);
              if (preview) {
                build.consolePreview = preview;
                filtered[currentIndex] = build;
              }
            }).fail(function() {
              build.consoleError = true;
              errors += 1;
            }).always(function() {
              completed += 1;
              active -= 1;
              $('#fullJobFilterStatus').text('Checking console logs... ' + completed + '/' + builds.length);

              if (completed === builds.length) {
                if (errors > 0) {
                  toastr.warning(errors + ' console log request(s) failed while filtering.', 'Console Filter');
                }
                deferred.resolve(filtered.filter(function(build) { return !! build; }));
              } else {
                pump();
              }
            });
          })(index);
          index += 1;
        }
      }

      pump();
      return deferred.promise();
    }

    function runSearch() {
      var filters = readFilters();

      if (! validateFilters(filters)) {
        return;
      }

      currentLogQuery = filters.logText;
      setBusy(true, 'Detecting environment for ' + filters.jobName + '...');

      loadSelectedJobEnvironment(filters.jobName).always(function() {
        setBusy(true, 'Fetching latest ' + filters.rowLimit + ' build(s) for ' + filters.jobName + '...');

        fetchBuilds(filters).done(function(builds) {
          var basicMatches = applyBasicFilters(builds, filters);
          $('#fullJobFilterStatus').text(basicMatches.length + ' build(s) match the non-console filters.');

          filterByConsoleText(filters.jobName, basicMatches, filters.logText, filters.caseSensitive).done(function(filteredBuilds) {
            renderBuildTable(filteredBuilds);
            updateSummary(builds, filteredBuilds);
            $('#fullJobFilterStatus').text('Fetched ' + builds.length + ' build(s) for ' + environmentHelper.text(selectedJobEnvironmentInfo) + '; showing ' + filteredBuilds.length + ' matching build(s).');
          }).always(function() {
            setBusy(false);
          });
        }).fail(function(xhr) {
          toastr.error(responseMessage(xhr, 'Unable to fetch builds for this job.'), 'Build Query Failed');
          setBusy(false);
        });
      });
    }

    function resetFilters() {
      $('#resultFilter').val('all');
      $('#dateFrom, #dateTo, #buildFrom, #buildTo, #logText').val('');
      $('#rowLimit').val(100);
      $('#caseSensitiveLog').prop('checked', false);
      setSelectedJobEnvironment({environment: 'Unknown', source: 'Not detected', unknown: true});
      $('#fullJobFilterStatus').text('Filters reset. Search again to refresh the report.');
    }

    function showLog(jobName, buildNumber, result, date, environment, output) {
      var consoleOutput = output || 'Console output is empty for this build.';
      $('#addLog').html('<div class="destroy"><table class="table table-bordered"><tbody><tr><th width="120px">Header</th><th>Task</th></tr><tr><td>Execution Date</td><td>' + escapeHtml(date || 'Not available') + '</td></tr><tr><td>Job Name</td><td>' + escapeHtml(jobName) + ' <b>[#' + escapeHtml(buildNumber) + ']</b></td></tr><tr><td>Environment</td><td>' + escapeHtml(environment || 'Unknown') + '</td></tr><tr><td>Status</td><td>' + escapeHtml(result || 'No result') + '</td></tr><tr><td>Console Log</td><td><div id="fullJobConsoleLog"></div></td></tr></tbody></table></div>');

      if (window.JobSeekerConsole) {
        window.JobSeekerConsole.setText('#fullJobConsoleLog', consoleOutput, {live: String(result).toUpperCase() === 'RUNNING'});
      } else {
        $('#fullJobConsoleLog').text(consoleOutput);
      }

      $('#modal-default').modal('show');
    }

    $('#searchList').on('submit', function(event) {
      event.preventDefault();
      runSearch();
    });

    $('#reload').click(function() {
      runSearch();
    });

    $('#resetFilters').click(function() {
      resetFilters();
    });

    $('#job_name').on('change', function() {
      var jobName = $(this).val() || '';
      setSelectedJobEnvironment(environmentHelper.detectFromJob({name: jobName, fullName: jobName}));
    });

    $('#fetch').on('click', '.log', function() {
      var button = $(this);
      var jobName = button.data('job') || '';
      var buildNumber = button.data('build') || '';
      var result = button.data('result') || '';
      var date = button.data('date') || '';
      var environment = button.data('environment') || environmentHelper.text(selectedJobEnvironmentInfo);

      if (jobName === '' || buildNumber === '') {
        return;
      }

      button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Logs');
      $('.overlay').show();

      fetchConsole(jobName, buildNumber).done(function(output) {
        showLog(jobName, buildNumber, result, date, environment, output);
      }).fail(function(xhr) {
        toastr.error(responseMessage(xhr, 'Unable to fetch console log.'), 'Log Query Failed');
      }).always(function() {
        $('.overlay').hide();
        button.prop('disabled', false).html('<i class="fa fa-terminal"></i> Logs');
      });
    });
  });
</script>
