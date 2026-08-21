 <?php // print_r($jobs) ?>
 <?php $canManageJobs = isset($canManageJobs) ? (bool) $canManageJobs : false; ?>
 <!-- <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse');
  });
</script> -->

<style type="text/css">
  /* The switch - the box around the slider */
.switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 34px;
}

/* Hide default HTML checkbox */
.switch input {
  opacity: 0;
  width: 0;
  height: 5;
}

/* The slider */
.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  -webkit-transition: .4s;
  transition: .4s;
}

.slider:before {
  position: absolute;
  content: "";
  height: 26px;
  width: 26px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  -webkit-transition: .4s;
  transition: .4s;
}

input:checked + .slider {
  background-color: #2196F3;
}

input:focus + .slider {
  box-shadow: 0 0 1px #2196F3;
}

input:checked + .slider:before {
  -webkit-transform: translateX(26px);
  -ms-transform: translateX(26px);
  transform: translateX(26px);
}

/* Rounded sliders */
.slider.round {
  border-radius: 34px;
}

.slider.round:before {
  border-radius: 50%;
}

pre { 
    white-space: pre-wrap; 
    word-break: break-word;
    max-width: 750px;
}

.monitor-summary .info-box {
  min-height: 72px;
}

.monitor-summary .info-box-icon {
  height: 72px;
  line-height: 72px;
}

.monitor-summary .info-box-content {
  padding-top: 10px;
}

.monitor-pill {
  display: inline-block;
  border-radius: 3px;
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
  padding: 5px 7px;
  white-space: nowrap;
}

.monitor-pill-success { background: #00a65a; color: #fff; }
.monitor-pill-danger { background: #dd4b39; color: #fff; }
.monitor-pill-warning { background: #f39c12; color: #fff; }
.monitor-pill-info { background: #00c0ef; color: #fff; }
.monitor-pill-muted { background: #d2d6de; color: #444; }

.monitor-progress {
  min-width: 130px;
}

.monitor-progress .progress {
  margin-bottom: 4px;
}

.monitor-filter-panel {
  margin-bottom: 10px;
}

.monitor-filter-panel .btn {
  margin-bottom: 5px;
}

.monitor-filter-panel .badge {
  margin-left: 6px;
}

.schedule-cell small,
.next-run-cell small {
  display: block;
  color: #777;
  max-width: 180px;
  white-space: normal;
}

</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1>
     <b>Job Build List</b>
     <small>Quick access to your jobs.</small>
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
 <?php if($canManageJobs) {  ?>
  <div class="row">
    <div class="col-xs-12 text-left">
      <div class="form-group">
        <a class="btn btn-primary" href="<?php echo base_url(); ?>jobCreation"><i class="fa fa-plus"></i> Add New Job</a>
        <a id="load" class="btn btn-success" href="#" style="margin-left: 10px;"><i class="fa fa-refresh"></i> Load Data</a>
         <label class="switch" style="margin-left: 15px; padding-top: 3px;">
          <input type="checkbox" name="refresh" id="refresh" value="1" checked>
          <span class="slider round"></span>
        </label> <b style="margin-left: 10px; font-size: 15px;">Enable Auto Refresh</b>
      </div>

      
    </div>
  </div> 
<?php } ?>

<div class="row monitor-summary" style="margin-top: 5px;">
  <div class="col-sm-6 col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-aqua"><i class="fa fa-list"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Jobs</span>
        <span class="info-box-number" id="monitor-total-jobs">0</span>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-green"><i class="fa fa-play"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Running Now</span>
        <span class="info-box-number" id="monitor-running-jobs">0</span>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Queued</span>
        <span class="info-box-number" id="monitor-queued-jobs">0</span>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-red"><i class="fa fa-exclamation-triangle"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Needs Attention</span>
        <span class="info-box-number" id="monitor-attention-jobs">0</span>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-xs-12">
    <p class="text-muted" id="monitor-last-refresh">Monitoring data has not been loaded yet.</p>
  </div>
</div>

<div class="row monitor-filter-panel">
  <div class="col-xs-12">
    <div class="btn-group" role="group" aria-label="Job monitor quick filters">
      <button type="button" class="btn btn-default monitor-filter active" data-filter="all">All <span class="badge" id="monitor-filter-all">0</span></button>
      <button type="button" class="btn btn-default monitor-filter" data-filter="running">Running <span class="badge" id="monitor-filter-running">0</span></button>
      <button type="button" class="btn btn-default monitor-filter" data-filter="queued">Queued <span class="badge" id="monitor-filter-queued">0</span></button>
      <button type="button" class="btn btn-default monitor-filter" data-filter="attention">Needs Attention <span class="badge" id="monitor-filter-attention">0</span></button>
      <button type="button" class="btn btn-default monitor-filter" data-filter="scheduled">Scheduled <span class="badge" id="monitor-filter-scheduled">0</span></button>
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
    <div id="box2" class="box box-primary collapsed-box">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Available Jobs</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <div class="table-responsive">
        <table id="listTable" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>Health</th>
              <th>State</th>
              <th>Job Name</th>
              <th>Schedule</th>
              <th>Next Run</th>
              <th>Current Run</th>
              <th>Queue</th>
              <th>Last Result</th>
              <th>Last Build Time</th>
              <th>Last Build Duration</th>
              <th>Last Build Number</th>
              <th>Description</th>
              <th>Trigger Job</th>
              <th>Last Build Output</th>
              <th>Abort Job</th>
              <th>Inspect Job</th>
              <th>Delete Job</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
              <th>Health</th>
              <th>State</th>
              <th>Job Name</th>
              <th>Schedule</th>
              <th>Next Run</th>
              <th>Current Run</th>
              <th>Queue</th>
              <th>Last Result</th>
              <th>Last Build Time</th>
              <th>Last Build Duration</th>
              <th>Last Build Number</th>
              <th>Description</th>
              <th>Trigger Job</th>
              <th>Last Build Output</th>
              <th>Abort Job</th>
              <th>Inspect Job</th>
              <th>Delete Job</th>
            </tr>
        </tfoot> 
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

<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box3" class="box box-danger collapsed-box">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Last Failed Job Builds</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <table id="listFailedTable" class="table table-bordered table-striped">
          <thead>
            <tr>
            <th>Job Name</th>
            <th>Result</th>
            <th>Last Build Number</th>
            <th>Last Failure Time</th>
            <th>Job Duration</th>
            <th>Job Url</th>
            <th>Queue Id</th>
            <th>Building</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
            <th>Job Name</th>
            <th>Result</th>
            <th>Last Build Number</th>
            <th>Last Failure Time</th>
            <th>Job Duration</th>
            <th>Job Url</th>
            <th>Queue Id</th>
            <th>Building</th>
          </tr>
        </tfoot>
      </table>
    </div>
    <!-- /.box-body -->
  </div>
  <!-- /.box -->
</div>
<!-- /.col -->
</div>
<!-- /.row -->


<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box4" class="box box-success collapsed-box">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Last Success Job Builds</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <table id="listSuccessTable" class="table table-bordered table-striped">
          <thead>
            <tr>
            <th>Job Name</th>
            <th>Result</th>
            <th>Last Build Number</th>
            <th>Last Success Time</th>
            <th>Job Duration</th>
            <th>Job Url</th>
            <th>Queue Id</th>
            <th>Building</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
            <th>Job Name</th>
            <th>Result</th>
            <th>Last Build Number</th>
            <th>Last Success Time</th>
            <th>Job Duration</th>
            <th>Job Url</th>
            <th>Queue Id</th>
            <th>Building</th>
          </tr>
        </tfoot>
      </table>
    </div>
    <!-- /.box-body -->
  </div>
  <!-- /.box -->
</div>
<!-- /.col -->
</div>
<!-- /.row -->
</div>
<!-- /.container -->

</section>
<!-- /.content -->
</div>
<!-- /.content-wrapper -->

<script type="text/javascript">

  var jobListRefreshTimer = null;
  var jobListRefreshIntervalMs = 5000;
  var jobListLoadGeneration = 0;
  var jobMonitorFilter = 'all';
  var jobMonitorFilterRegistered = false;
  var jobDataTablesErrorModeConfigured = false;
  var jobListLoadInProgress = false;
  var jobScheduleCache = {};
  var jobScheduleRequests = {};
  var canManageJobs = <?php echo $canManageJobs ? 'true' : 'false'; ?>;
  var deleteRepositoriesUrl = <?php echo json_encode(base_url() . 'DeleteJob/deleteRepositories'); ?>;
  var jobViewUrl = <?php echo json_encode(base_url() . 'jobView?job='); ?>;

  function jenkinsJobPath(jobName) {
    return String(jobName == null ? '' : jobName).split('/').map(function(segment) {
      return 'job/' + encodeURIComponent(segment);
    }).join('/');
  }

  function deleteJenkinsJob(jobName, jenkinsUrl) {
    return $.ajax({
      url: jenkinsUrl + jenkinsJobPath(jobName) + '/doDelete',
      method: 'POST'
    });
  }

  function deleteJobRepositories(jobNames) {
    return $.ajax({
      url: deleteRepositoriesUrl,
      method: 'POST',
      dataType: 'json',
      data: {jobs: jobNames}
    });
  }

  function reportRepositoryDeleteResults(data) {
    var results = data && data.results ? data.results : [];
    var deletedCount = results.filter(function(result) { return result.exist; }).length;
    var invalidCount = results.filter(function(result) { return result.error; }).length;

    if (deletedCount > 0) {
      toastr.success(deletedCount + ' repository folder(s) deleted.', 'Repositories Deleted');
    } else if (results.length > 0) {
      toastr.warning('No matching repository folders were found.', 'No Repository Found');
    }

    if (invalidCount > 0) {
      toastr.error(invalidCount + ' repository selection(s) were invalid.', 'Repository Delete Warning');
    }
  }

  function jobsFromJenkinsResponse(json) {
    return json && Array.isArray(json.jobs) ? json.jobs : [];
  }

  function jobsWithBuild(json, buildKey) {
    return jobsFromJenkinsResponse(json).filter(function(job) {
      return job && job[buildKey];
    });
  }

  function latestBuildValue(row, field) {
    if (! row || ! Array.isArray(row.builds) || row.builds.length === 0 || ! row.builds[0]) {
      return '';
    }

    var value = row.builds[0][field];
    return value == null ? '' : value;
  }

  function nestedBuildValue(row, buildKey, field) {
    if (! row || ! row[buildKey]) {
      return '';
    }

    var value = row[buildKey][field];
    return value == null ? '' : value;
  }

  function renderText(data) {
    return data == null ? '' : data;
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

  function lastBuild(row) {
    if (row && row.lastBuild) {
      return row.lastBuild;
    }

    if (row && Array.isArray(row.builds) && row.builds.length > 0 && row.builds[0]) {
      return row.builds[0];
    }

    return null;
  }

  function lastBuildField(row, field) {
    var build = lastBuild(row);
    if (! build) {
      return '';
    }

    var value = build[field];
    return value == null ? '' : value;
  }

  function normalizedJobColor(color) {
    return String(color == null ? '' : color).replace('_anime', '');
  }

  function isJobRunning(row) {
    var build = lastBuild(row);
    return !!(build && build.building === true) || /_anime$/.test(String(row && row.color ? row.color : ''));
  }

  function isJobAttention(row) {
    var result = lastBuildField(row, 'result');
    var color = normalizedJobColor(row && row.color);
    return result === 'FAILURE' || result === 'ABORTED' || color === 'red' || row && row.buildable === false;
  }

  function isJobScheduled(row) {
    var cached = jobScheduleCache[scheduleCacheKey(row)];
    return !!(cached && ! cached.loading && ! cached.error && cached.spec && cached.spec !== 'No cron trigger');
  }

  function rowMatchesMonitorFilter(row, filter) {
    if (filter === 'running') {
      return isJobRunning(row);
    }

    if (filter === 'queued') {
      return !!(row && row.inQueue === true);
    }

    if (filter === 'attention') {
      return isJobAttention(row);
    }

    if (filter === 'scheduled') {
      return isJobScheduled(row);
    }

    return true;
  }

  function updateMonitorFilterCounts(jobs) {
    $('#monitor-filter-all').text(jobs.length);
    $('#monitor-filter-running').text(jobs.filter(isJobRunning).length);
    $('#monitor-filter-queued').text(jobs.filter(function(job) { return job && job.inQueue === true; }).length);
    $('#monitor-filter-attention').text(jobs.filter(isJobAttention).length);
    $('#monitor-filter-scheduled').text(jobs.filter(isJobScheduled).length);
  }

  function updateMonitorSummary(jobs) {
    var running = jobs.filter(isJobRunning).length;
    var queued = jobs.filter(function(job) { return job && job.inQueue === true; }).length;
    var attention = jobs.filter(isJobAttention).length;

    $('#monitor-total-jobs').text(jobs.length);
    $('#monitor-running-jobs').text(running);
    $('#monitor-queued-jobs').text(queued);
    $('#monitor-attention-jobs').text(attention);
    updateMonitorFilterCounts(jobs);
    $('#monitor-last-refresh').text('Last refreshed ' + moment().format('MMMM Do YYYY, h:mm:ss a'));
  }

  function monitorPill(label, kind) {
    return '<span class="monitor-pill monitor-pill-' + kind + '">' + escapeHtml(label) + '</span>';
  }

  function renderHealth(row) {
    var color = normalizedJobColor(row && row.color);

    if (isJobRunning(row)) {
      return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/loading.gif">';
    }

    if (color === 'aborted' || color === 'red') {
      return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/bad.png">';
    }

    if (color === 'blue') {
      return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/good.png">';
    }

    if (color === 'notbuilt') {
      return '<b>Never Built</b>';
    }

    return renderText(color);
  }

  function renderState(row) {
    if (row && row.buildable === false) {
      return monitorPill('Disabled', 'muted');
    }

    if (isJobRunning(row)) {
      return monitorPill('Running', 'info');
    }

    if (row && row.inQueue === true) {
      return monitorPill('Queued', 'warning');
    }

    var result = lastBuildField(row, 'result');
    if (result === 'SUCCESS') {
      return monitorPill('Healthy', 'success');
    }

    if (result === 'FAILURE' || result === 'ABORTED') {
      return monitorPill(result, 'danger');
    }

    return monitorPill('Idle', 'muted');
  }

  function renderCurrentRun(row) {
    if (! isJobRunning(row)) {
      return '<span class="text-muted">Idle</span>';
    }

    var buildNumber = lastBuildField(row, 'number');
    var estimatedDuration = parseInt(lastBuildField(row, 'estimatedDuration'), 10);
    var timestamp = parseInt(lastBuildField(row, 'timestamp'), 10);
    var percent = 0;

    if (estimatedDuration > 0 && timestamp > 0) {
      percent = Math.max(0, Math.min(100, Math.floor(((Date.now() - timestamp) / estimatedDuration) * 100)));
    }

    return '<div class="monitor-progress"><b>#' + escapeHtml(buildNumber || '?') + '</b><div class="progress progress-xs active"><div class="progress-bar progress-bar-info progress-bar-striped" style="width: ' + percent + '%"></div></div><small>' + percent + '% estimated</small></div>';
  }

  function renderQueue(row) {
    if (row && row.inQueue === true) {
      var why = row.queueItem && row.queueItem.why ? row.queueItem.why : 'Waiting for executor';
      return monitorPill('Queued', 'warning') + '<br><small>' + escapeHtml(why) + '</small>';
    }

    return '<span class="text-muted">No</span>';
  }

  function scheduleCacheKey(row) {
    return row && (row.fullName || row.name) ? row.fullName || row.name : '';
  }

  function firstCronLine(spec) {
    return String(spec || '').split(/\r?\n/).map(function(line) {
      return line.trim();
    }).filter(function(line) {
      return line !== '' && line.charAt(0) !== '#';
    })[0] || '';
  }

  function summarizeCronSpec(spec) {
    if (! spec) {
      return 'Manual only';
    }

    if (spec.charAt(0) === '@') {
      return spec;
    }

    var parts = spec.split(/\s+/);
    if (parts.length !== 5) {
      return 'Custom cron';
    }

    var minute = parts[0];
    var hour = parts[1];
    var dayOfMonth = parts[2];
    var month = parts[3];
    var dayOfWeek = parts[4];
    var everyMinute = minute === '*' && hour === '*' && dayOfMonth === '*' && month === '*' && dayOfWeek === '*';
    var minuteStep = minute.match(/^(?:\*|H)\/(\d+)$/);

    if (everyMinute) {
      return 'Every minute';
    }

    if (minuteStep && hour === '*' && dayOfMonth === '*' && month === '*' && dayOfWeek === '*') {
      return 'Every ' + minuteStep[1] + ' minutes';
    }

    if (/^\d+$/.test(minute) && hour === '*' && dayOfMonth === '*' && month === '*' && dayOfWeek === '*') {
      return 'Hourly at :' + ('0' + minute).slice(-2);
    }

    if (/^\d+$/.test(minute) && /^\d+$/.test(hour) && dayOfMonth === '*' && month === '*' && dayOfWeek === '*') {
      return 'Daily at ' + ('0' + hour).slice(-2) + ':' + ('0' + minute).slice(-2);
    }

    return spec.indexOf('H') !== -1 ? 'Hashed cron' : 'Cron schedule';
  }

  function cronTokenMatches(value, token, min, max) {
    token = token.replace(/H/g, '*').replace(/\?/g, '*');

    if (token === '*') {
      return true;
    }

    var stepParts = token.split('/');
    var rangePart = stepParts[0];
    var step = stepParts.length > 1 ? parseInt(stepParts[1], 10) : 1;

    if (! step || step < 1) {
      return false;
    }

    var start = min;
    var end = max;

    if (rangePart !== '*') {
      if (rangePart.indexOf('-') !== -1) {
        var range = rangePart.split('-');
        start = parseInt(range[0], 10);
        end = parseInt(range[1], 10);
      } else {
        start = parseInt(rangePart, 10);
        end = start;
      }
    }

    if (Number.isNaN(start) || Number.isNaN(end) || value < start || value > end) {
      return false;
    }

    return ((value - start) % step) === 0;
  }

  function cronFieldMatches(value, field, min, max) {
    return String(field || '*').split(',').some(function(token) {
      return cronTokenMatches(value, token.trim(), min, max);
    });
  }

  function cronDayOfWeekMatches(date, field) {
    var day = date.getDay();
    return cronFieldMatches(day, field, 0, 7) || (day === 0 && cronFieldMatches(7, field, 0, 7));
  }

  function estimateTaggedNextRun(tag) {
    var next = new Date();
    next.setSeconds(0, 0);

    if (tag === '@hourly') {
      next.setHours(next.getHours() + 1, 0, 0, 0);
    } else if (tag === '@daily' || tag === '@midnight') {
      next.setDate(next.getDate() + 1);
      next.setHours(0, 0, 0, 0);
    } else if (tag === '@weekly') {
      next.setDate(next.getDate() + (7 - next.getDay()));
      next.setHours(0, 0, 0, 0);
    } else if (tag === '@monthly') {
      next = new Date(next.getFullYear(), next.getMonth() + 1, 1, 0, 0, 0, 0);
    } else if (tag === '@yearly' || tag === '@annually') {
      next = new Date(next.getFullYear() + 1, 0, 1, 0, 0, 0, 0);
    } else {
      return 'Scheduled by Jenkins';
    }

    return moment(next).format('MMMM Do YYYY, h:mm a');
  }

  function estimateNextRun(spec) {
    if (! spec) {
      return 'Not scheduled';
    }

    if (spec.charAt(0) === '@') {
      return estimateTaggedNextRun(spec);
    }

    var parts = spec.split(/\s+/);
    if (parts.length !== 5) {
      return 'Next run unavailable';
    }

    var candidate = new Date(Date.now() + 60000);
    candidate.setSeconds(0, 0);

    for (var index = 0; index < 60 * 24 * 32; index++) {
      if (
        cronFieldMatches(candidate.getMinutes(), parts[0], 0, 59) &&
        cronFieldMatches(candidate.getHours(), parts[1], 0, 23) &&
        cronFieldMatches(candidate.getDate(), parts[2], 1, 31) &&
        cronFieldMatches(candidate.getMonth() + 1, parts[3], 1, 12) &&
        cronDayOfWeekMatches(candidate, parts[4])
      ) {
        return moment(candidate).format('MMMM Do YYYY, h:mm a');
      }

      candidate = new Date(candidate.getTime() + 60000);
    }

    return 'Next run unavailable';
  }

  function scheduleInfoFromConfig(xmlText) {
    try {
      var xml = typeof xmlText === 'string' ? $.parseXML(xmlText) : xmlText;
      var spec = firstCronLine($(xml).find('triggers spec').first().text());

      return {
        summary: summarizeCronSpec(spec),
        spec: spec || 'No cron trigger',
        nextRunText: estimateNextRun(spec),
        error: false
      };
    } catch (error) {
      return {
        summary: 'Schedule unavailable',
        spec: '',
        nextRunText: 'Unavailable',
        error: true
      };
    }
  }

  function renderSchedule(row) {
    var key = scheduleCacheKey(row);
    var cached = jobScheduleCache[key];

    if (row && row.buildable === false) {
      return monitorPill('Disabled', 'muted');
    }

    if (! cached || cached.loading) {
      return '<span class="text-muted">Loading...</span>';
    }

    if (cached.error) {
      return '<span class="text-red">Unavailable</span>';
    }

    return '<span class="schedule-cell"><b>' + escapeHtml(cached.summary) + '</b><small>' + escapeHtml(cached.spec) + '</small></span>';
  }

  function renderNextRun(row) {
    var key = scheduleCacheKey(row);
    var cached = jobScheduleCache[key];

    if (row && row.buildable === false) {
      return '<span class="text-muted">Paused</span>';
    }

    if (! cached || cached.loading) {
      return '<span class="text-muted">Loading...</span>';
    }

    if (cached.error) {
      return '<span class="text-red">Unavailable</span>';
    }

    return '<span class="next-run-cell">' + escapeHtml(cached.nextRunText) + '</span>';
  }

  function abortScheduleRequests() {
    $.each(jobScheduleRequests, function(key, request) {
      if (request && request.readyState !== 4) {
        request.abort();
      }
    });

    jobScheduleRequests = {};

    $.each(jobScheduleCache, function(key, value) {
      if (value && value.loading) {
        delete jobScheduleCache[key];
      }
    });
  }

  function hydrateJobSchedules(table, jenkinsUrl, headers, generation) {
    table.rows({page: 'current'}).every(function() {
      var row = this.data();
      var key = scheduleCacheKey(row);

      if (! key || jobScheduleCache[key]) {
        return;
      }

      jobScheduleCache[key] = {loading: true};

      jobScheduleRequests[key] = $.ajax({
        contentType: 'application/text',
        url: jenkinsUrl + jenkinsJobPath(key) + '/config.xml',
        method: 'GET',
        headers: headers
      }).done(function(xmlText) {
        if (generation !== jobListLoadGeneration || ! $.fn.DataTable.isDataTable('#listTable')) {
          return;
        }

        jobScheduleCache[key] = scheduleInfoFromConfig(xmlText);
        updateMonitorFilterCounts(table.rows().data().toArray());
        table.rows().invalidate('data').draw(false);
      }).fail(function(request, statusText) {
        if (statusText === 'abort' || generation !== jobListLoadGeneration || ! $.fn.DataTable.isDataTable('#listTable')) {
          return;
        }

        jobScheduleCache[key] = {
          summary: 'Schedule unavailable',
          spec: '',
          nextRunText: 'Unavailable',
          error: true
        };
        updateMonitorFilterCounts(table.rows().data().toArray());
        table.rows().invalidate('data').draw(false);
      }).always(function() {
        delete jobScheduleRequests[key];
      });
    });
  }

  function ensureMonitorFilterRegistered() {
    if (jobMonitorFilterRegistered || ! $.fn.dataTable || ! $.fn.dataTable.ext) {
      return;
    }

    $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex, rowData) {
      if (! settings.nTable || settings.nTable.id !== 'listTable') {
        return true;
      }

      var row = rowData || (settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex]._aData : null);
      return rowMatchesMonitorFilter(row, jobMonitorFilter);
    });

    jobMonitorFilterRegistered = true;
  }

  function ensureDataTablesErrorModeConfigured() {
    if (jobDataTablesErrorModeConfigured || ! $.fn.dataTable || ! $.fn.dataTable.ext) {
      return;
    }

    $.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
      var tableId = settings && settings.nTable ? settings.nTable.id : 'table';
      console.warn('DataTables warning for ' + tableId + ': ' + message);
      toastr.warning('Could not refresh ' + tableId + '. Please try Load Data again if the table looks stale.', 'Table Refresh Warning');
    };

    jobDataTablesErrorModeConfigured = true;
  }

  function renderBuildTime(data, emptyText) {
    return data != null && data !== '' ? moment(parseInt(data, 10)).format('MMMM Do YYYY, h:mm:ss a') : emptyText;
  }

  function renderDuration(data, emptyText) {
    return data != null && data !== '' ? moment(parseInt(data, 10)).utc().format('HH [Hours, ] mm [Minutes, ] ss [Seconds, ] SSS [Miliseconds.]') : emptyText;
  }

  function dataTableHasPendingAjax(table) {
    var settings = table.settings()[0];
    return !!(settings && settings.jqXHR && settings.jqXHR.readyState !== 4);
  }

  function destroyDataTable(selector) {
    if ($.fn.DataTable.isDataTable(selector)) {
      var table = $(selector).DataTable();
      var settings = table.settings()[0];

      if (settings && settings.jqXHR && settings.jqXHR.readyState !== 4) {
        settings.jqXHR.abort();
      }

      table.clear().destroy();
    }
  }

  function reloadJobTables() {
    ['#listTable', '#listSuccessTable', '#listFailedTable'].forEach(function(selector) {
      if ($.fn.DataTable.isDataTable(selector)) {
        var table = $(selector).DataTable();

        if (! dataTableHasPendingAjax(table)) {
          table.ajax.reload(null, false);
        }
      }
    });
  }

  function clearJobListRefresh() {
    if (jobListRefreshTimer !== null) {
      clearInterval(jobListRefreshTimer);
      jobListRefreshTimer = null;
    }
  }

  function startJobListRefresh() {
    clearJobListRefresh();

    jobListRefreshTimer = setInterval(function(){
      if($('#refresh').is(":checked")){
        reloadJobTables();
      } else {
        clearJobListRefresh();
      }
    }, jobListRefreshIntervalMs);
  }

  $('#refresh').change(function(){
    if($(this).is(":checked")){
      if ($.fn.DataTable.isDataTable('#listTable')) {
        startJobListRefresh();
      } else {
        loadJobListData();
      }
    } else {
      clearJobListRefresh();
    }
  });

  $('.monitor-filter').click(function(){
    jobMonitorFilter = $(this).data('filter') || 'all';
    $('.monitor-filter').removeClass('active');
    $(this).addClass('active');

    if ($.fn.DataTable.isDataTable('#listTable')) {
      ensureMonitorFilterRegistered();
      $('#listTable').DataTable().draw(false);
    }
  });

  function loadJobListData(options) {
    options = options || {};

    if (jobListLoadInProgress) {
      return;
    }

    if ($.fn.DataTable.isDataTable('#listTable')) {
      if (! options.silent) {
        toastr.info('Refreshing data from server...', 'Query Data');
      }
      reloadJobTables();
      if ($('#refresh').is(':checked')) {
        startJobListRefresh();
      }
      return;
    }

    jobListLoadInProgress = true;
    
        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '';
        var jenkins_token = '';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

        if (! options.silent) {
          toastr.info('Fetching data from server...', 'Query Data');
        }
        $(".overlay").show();

      
        jobListLoadGeneration++;
        var currentGeneration = jobListLoadGeneration;
        abortScheduleRequests();
        ensureMonitorFilterRegistered();
        ensureDataTablesErrorModeConfigured();
        destroyDataTable('#listTable');
        var jobListHeaders = {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)};
        var listTable = $('#listTable').DataTable({
          "lengthMenu": [3,5,10,15,20,100,200,500,1000],
          "pageLength": 20,
          "order": [[ 2, "asc" ]],
          "scrollX": true,
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,fullName,displayName,color,description,buildable,inQueue,nextBuildNumber,queueItem[id,why],lastBuild[number,id,result,timestamp,duration,estimatedDuration,building,url,queueId,displayName],lastCompletedBuild[number,result,timestamp,duration,url,queueId,building,displayName],lastFailedBuild[displayName,result,timestamp,duration,url,queueId,building],lastStableBuild[displayName,result,timestamp,duration,url,queueId,building]]',
            "type": 'GET',
            "headers": jobListHeaders,
            "dataSrc": function(json) {
              var jobs = jobsFromJenkinsResponse(json);
              updateMonitorSummary(jobs);
              return jobs;
            }
          },
          "columns": [
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderHealth(row); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderState(row); }},
          {"data": "name", "defaultContent": "", "render": function(data, type, row){ return escapeHtml(row.fullName || data || ''); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderSchedule(row); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderNextRun(row); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderCurrentRun(row); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderQueue(row); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            var result = lastBuildField(row, 'result');
            if(result === 'SUCCESS') { return '<b style="color: green;">' + escapeHtml(result) + '</b>'; }
            if(result) { return '<b style="color: red;">' + escapeHtml(result) + '</b>'; }
            return '<span class="text-muted">Never Built</span>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderBuildTime(lastBuildField(row, 'timestamp'), '<b>Never Built</b>'); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderDuration(lastBuildField(row, 'duration'), '<b>Never Built</b>'); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return escapeHtml(lastBuildField(row, 'number')); }},
          {"data": "description", "defaultContent": "", "render": function(data){ return escapeHtml(data); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            var jobName = row.fullName || row.name || '';
            return '<button class="btn btn-sm btn-primary run" href="#" value="'+ escapeAttribute(jobName) +'" title="Click to trigger this job build">Build</button>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            var jobName = row.fullName || row.name || '';
            var buildNumber = lastBuildField(row, 'number');
            var result = lastBuildField(row, 'result');
            var timestamp = lastBuildField(row, 'timestamp');
            var date = renderBuildTime(timestamp, '');
            return '<button class="btn btn-sm btn-info log" href="#" value="'+ escapeAttribute(jobName) +'" data-build="'+ escapeAttribute(buildNumber) +'" data-result="'+ escapeAttribute(result) +'" data-time="'+ escapeAttribute(date) +'" title="Click to check this job console output">Check</button>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            if (! isJobRunning(row)) {
              return '';
            }

            var jobName = row.fullName || row.name || '';
            return '<button class="btn btn-sm btn-danger abort" href="#" value="'+ escapeAttribute(jobName) +'" title="Click to cancel this job execution">Abort</button>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            var jobName = row.fullName || row.name || '';
            return '<a class="btn btn-sm btn-default" href="' + jobViewUrl + encodeURIComponent(jobName) + '" title="Inspect this Jenkins job"><i class="fa fa-eye"></i> Inspect</a>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            if (! canManageJobs) {
              return '';
            }

            var jobName = row.fullName || row.name || '';
            return '<button class="btn btn-sm btn-danger delete-job" href="#" value="'+ escapeAttribute(jobName) +'" data-running="'+ (isJobRunning(row) ? '1' : '0') +'" title="Delete this Jenkins job">Delete</button>';
          }}
          ],
          "drawCallback": function() {
            hydrateJobSchedules(this.api(), jenkins_url, jobListHeaders, currentGeneration);
          }
       });
      $('#box2').boxWidget('expand');

        destroyDataTable('#listFailedTable');
        $('#listFailedTable').DataTable({
          "lengthMenu": [3,5,10,15,20,100,200,500,1000],
          "pageLength": 20,
          "order": [[ 3, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,lastFailedBuild[displayName,result,timestamp,duration,url,queueId,building]{0,1}]',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": function(json){ return jobsWithBuild(json, 'lastFailedBuild'); }
          },
          "columns": [
          {"data": "name", "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastFailedBuild', 'result'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastFailedBuild', 'displayName'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastFailedBuild', 'timestamp'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastFailedBuild', 'duration'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastFailedBuild', 'url'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastFailedBuild', 'queueId'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastFailedBuild', 'building'); }, "defaultContent": ""},
          ],
          columnDefs:[{targets:0, render:function(data){
            return renderText(data);
          }},{targets:1, render:function(data){
            if(data != null){if(data == 'SUCCESS') { return '<b style="color: green;">' + data + '</b>'} else {return '<b style="color: red;">' + data + '</b>'}} else {return ''}
          }},{targets:2, render:function(data){
            return renderText(data);
          }},{targets:3, render:function(data){
            return renderBuildTime(data, '');
          }},{targets:4, render:function(data){
            return renderDuration(data, '');
          }},{targets:5, render:function(data){
            return renderText(data);
          }},{targets:6, render:function(data){
            return renderText(data);
          }},{targets:7, render:function(data){
            return renderText(data);
          }}]
       });
        $('#box3').boxWidget('expand');

        destroyDataTable('#listSuccessTable');
        $('#listSuccessTable').DataTable({
          "lengthMenu": [3,5,10,15,20,100,200,500,1000],
          "pageLength": 20,
          "order": [[ 3, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,lastStableBuild[displayName,result,timestamp,duration,url,queueId,building]{0,1}]',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": function(json){ return jobsWithBuild(json, 'lastStableBuild'); }
          },
          "columns": [
          {"data": "name", "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastStableBuild', 'result'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastStableBuild', 'displayName'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastStableBuild', 'timestamp'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastStableBuild', 'duration'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastStableBuild', 'url'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastStableBuild', 'queueId'); }, "defaultContent": ""},
          {"data": function(row){ return nestedBuildValue(row, 'lastStableBuild', 'building'); }, "defaultContent": ""},

          ],
          columnDefs:[{targets:0, render:function(data){
            return renderText(data);
          }},{targets:1, render:function(data){
            if(data != null){if(data == 'SUCCESS') { return '<b style="color: green;">' + data + '</b>'} else {return '<b style="color: red;">' + data + '</b>'}} else {return ''}
          }},{targets:2, render:function(data){
            return renderText(data);
          }},{targets:3, render:function(data){
            return renderBuildTime(data, '');
          }},{targets:4, render:function(data){
            return renderDuration(data, '');
          }},{targets:5, render:function(data){
            return renderText(data);
          }},{targets:6, render:function(data){
            return renderText(data);
          }},{targets:7, render:function(data){
            return renderText(data);
          }}]
       });
        $('#box4').boxWidget('expand');

       $(".overlay").hide();
       jobListLoadInProgress = false;

        if ($('#refresh').is(':checked')) {
          startJobListRefresh();
        }

      }

      $('#load').click(function(event){
        event.preventDefault();
        loadJobListData();
      });


 $("#listTable").on('click','.abort',function(){

       var jenkins_url = '<?php echo $jenkins_url; ?>',
           jenkins_username = '',
           jenkins_token = '',
           jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

      var job=$(this).val();
         

            alertify.confirm('Job <b style="color:red;">'+ escapeHtml(job) +'</b> Abort Request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p>Are you sure you want to Abort the job <b>'+ escapeHtml(job) +' ?</b></p></div></div></div>',
      function(){ 

         $.ajax({
                    url: jenkins_url + jenkinsJobPath(job) + '/lastBuild/stop',
                    method: 'POST',
                    headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
                    beforeSend: function() {
                      toastr.warning("Your Stop Request has been sent to server.", "Request Sent")
                  }
                  }).done(function(data) {
                toastr.warning("Your Abort Request has been sent to server.", "Request Sent")
                 setTimeout(function(){
                reloadJobTables();
             }, 1000);
                $('.overlay').hide();
              })
    },
      function(){
        alertify.error('Operation Aborted')
    }
  );

});

 $("#listTable").on('click','.delete-job',function(){

       if (! canManageJobs) {
         toastr.error('You do not have permission to delete jobs.', 'Access Denied');
         return;
       }

       var button = $(this),
           jenkins_url = '<?php echo $jenkins_url; ?>',
           job = button.val(),
           isRunning = button.data('running') == 1;

       var runningWarning = isRunning ? '<p><b>This job appears to be running. Jenkins may reject deletion until the build is stopped.</b></p>' : '';

       alertify.confirm('Job <b style="color:red;">'+ escapeHtml(job) +'</b> Delete Request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p>Are you sure you want to delete the job <b>'+ escapeHtml(job) +'</b> permanently?</p>' + runningWarning + '<div class="checkbox text-left"><label><input type="checkbox" id="deleteBuildListRepository" checked> Also delete assigned job repository files.</label></div></div></div></div>',
      function(){
        var deleteRepository = $('#deleteBuildListRepository').is(':checked');
        $('.overlay').show();

        deleteJenkinsJob(job, jenkins_url).done(function() {
          toastr.success('Jenkins job deleted successfully.', 'Job Deleted');
          delete jobScheduleCache[job];

          if (! deleteRepository) {
            reloadJobTables();
            $('.overlay').hide();
            return;
          }

          deleteJobRepositories([job]).done(function(data) {
            reportRepositoryDeleteResults(data);
          }).fail(function() {
            console.error(arguments);
            toastr.error('The Jenkins job was deleted, but repository cleanup failed.', 'Repository Delete Failed');
          }).always(function() {
            reloadJobTables();
            $('.overlay').hide();
          });
        }).fail(function() {
          console.error(arguments);
          toastr.error('The Jenkins job could not be deleted.', 'Delete Failed');
          $('.overlay').hide();
        });
    }, 
      function(){ 
        alertify.error('Operation Aborted')
    }
  );
    
});

 $("#listTable").on('click','.run',function(){

       var jenkins_url = '<?php echo $jenkins_url; ?>',
           jenkins_username = '',
           jenkins_token = '',
           jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

      var job=$(this).val();
         

            alertify.confirm('Job <b style="color:red;">'+ escapeHtml(job) +'</b> Trigger Request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p>Are you sure you want to trigger the job <b>'+ escapeHtml(job) +' ?</b></p></div></div></div>',
      function(){ 

         $.ajax({
         url: jenkins_url + jenkinsJobPath(job) +'/build',
          method: 'POST',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {
            $('.overlay').show();
        }
        }).done(function(data) {
            toastr.warning("Your Execution Request has been sent to server.", "Request Sent")
             setTimeout(function(){
            reloadJobTables();
         }, 1000);
            $('.overlay').hide();
          })
    }, 
      function(){ 
        alertify.error('Operation Aborted')
    }
  );
    
});


$("#listTable").on('click','.log',function(){

        // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>',
          jenkins_username = '',
          jenkins_token = '',
            jenkins_authorization = '<?php echo $jenkins_authorization; ?>';


         // get the current row Id, job name and instance id
         var button = $(this),
           name = button.val(),
           result = button.data('result') || '',
           buildNumber = button.data('build') || '',
           date = button.data('time') || '';

        if(buildNumber == '' || buildNumber == null){
          var output = 'Your requested job ' + name + ' has not been executed yet. Please, try again later.';
          $("#addLog").append('<div class="destroy"><table class="table table-bordered"><tbody><tr><th width="10px">Header</th><th>Task</th></tr><tr><td>Execution Date</td><td>'+ escapeHtml(date) +'</td></tr><tr><td>Job Name</td><td>'+ escapeHtml(name) +' <b>['+ escapeHtml(buildNumber) +']</b> </td></tr><tr><td>Status</td><td>'+ escapeHtml(result) +'</td></tr><tr><td>Console Log</td><td><pre>'+ escapeHtml(output) +'</pre></td></tr></tbody></table></div>');
          $('#modal-default').modal('show');
          return;
        }

        $.ajax({
            contentType: "application/text",
          url: jenkins_url + jenkinsJobPath(name) +'/'+ encodeURIComponent(buildNumber) +'/consoleText',
            method: 'GET',
            headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            beforeSend: function() {
             $(".overlay").show();
             $(".destroy").remove();

            },
            error: function() {
            toastr.error("Error during console log query.", "Query Data Error");
            },
          success: function(output) {
              $("#addLog").append('<div class="destroy"><table class="table table-bordered"><tbody><tr><th width="10px">Header</th><th>Task</th></tr><tr><td>Execution Date</td><td>'+ escapeHtml(date) +'</td></tr><tr><td>Job Name</td><td>'+ escapeHtml(name) +' <b>['+ escapeHtml(buildNumber) +']</b> </td></tr><tr><td>Status</td><td>'+ escapeHtml(result) +'</td></tr><tr><td>Console Log</td><td><pre>'+ escapeHtml(output) +'</pre></td></tr></tbody></table></div>');
              $('#modal-default').modal('show');
            },
            complete: function(data) {
                dateRequest = data;
                $(".overlay").hide();
            }

         });

    });

  $(function() {
    $('#refresh').prop('checked', true);
    setTimeout(function() {
      loadJobListData({silent: true});
    }, 0);
  });
</script>