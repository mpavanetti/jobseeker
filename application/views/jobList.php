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
.environment-cell small,
.next-run-cell small {
  display: block;
  color: #777;
  max-width: 180px;
  white-space: normal;
}

.environment-cell .label {
  display: inline-block;
  margin-bottom: 3px;
}
.job-list-layout {
  width: 100%;
}

.job-list-layout table.dataTable {
  width: 100% !important;
}

.monitor-environment-filter {
  display: inline-flex;
  max-width: 320px;
  min-width: 240px;
  vertical-align: middle;
}

.monitor-environment-filter .input-group-addon {
  width: auto;
}

.job-bulk-toolbar {
  align-items: center;
  background: #f8fafc;
  border: 1px solid #dce4ec;
  border-radius: 4px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: space-between;
  margin-bottom: 12px;
  padding: 9px 10px;
}

.job-bulk-actions {
  align-items: center;
  display: inline-flex;
  flex-wrap: wrap;
  gap: 7px;
}

.job-bulk-hint {
  color: #777;
  font-size: 12px;
}

.job-select-cell {
  min-width: 42px;
  text-align: center;
  width: 42px;
}

.job-select-cell input {
  cursor: pointer;
  margin: 0;
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
  <div class="container-fluid job-list-layout">
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
      <button type="button" class="btn btn-default monitor-filter" data-filter="healthy">Healthy <span class="badge" id="monitor-filter-healthy">0</span></button>
      <button type="button" class="btn btn-default monitor-filter" data-filter="running">Running <span class="badge" id="monitor-filter-running">0</span></button>
      <button type="button" class="btn btn-default monitor-filter" data-filter="queued">Queued <span class="badge" id="monitor-filter-queued">0</span></button>
      <button type="button" class="btn btn-default monitor-filter" data-filter="attention">Needs Attention <span class="badge" id="monitor-filter-attention">0</span></button>
      <button type="button" class="btn btn-default monitor-filter" data-filter="scheduled">Scheduled <span class="badge" id="monitor-filter-scheduled">0</span></button>
    </div>
    <div class="input-group input-group-sm monitor-environment-filter">
      <span class="input-group-addon"><i class="fa fa-globe"></i> Environment</span>
      <select id="monitorEnvironmentFilter" class="form-control">
        <option value="all">All environments</option>
      </select>
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
        <div class="job-bulk-toolbar">
          <div class="job-bulk-actions">
            <button type="button" class="btn btn-primary btn-sm" id="triggerSelectedJobs" disabled>
              <i class="fa fa-play"></i> Trigger Selected <span class="badge" id="selectedJobBuildCount">0</span>
            </button>
            <button type="button" class="btn btn-default btn-sm" id="clearSelectedJobs" disabled>
              <i class="fa fa-eraser"></i> Clear Selection
            </button>
          </div>
          <span class="job-bulk-hint">Use the first column to select jobs. Selections persist while paging and filtering.</span>
        </div>
        <div class="table-responsive">
        <table id="listTable" class="table table-bordered table-striped" style="width: 100%;">
          <thead>
            <tr>
              <th class="job-select-cell"><input type="checkbox" class="job-select-visible" aria-label="Select all triggerable jobs on this page" title="Select all triggerable jobs on this page"></th>
              <th>Health</th>
              <th>State</th>
              <th>Job Name</th>
              <th>Environment</th>
              <th>Schedule</th>
              <th>Next Run</th>
              <th>Current Run</th>
              <th>Queue</th>
              <th>Last Result</th>
              <th>Last Build Time</th>
              <th>Last Build Duration</th>
              <th>Last Build Number</th>
              <th>Last Worker</th>
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
              <th class="job-select-cell">Select</th>
              <th>Health</th>
              <th>State</th>
              <th>Job Name</th>
              <th>Environment</th>
              <th>Schedule</th>
              <th>Next Run</th>
              <th>Current Run</th>
              <th>Queue</th>
              <th>Last Result</th>
              <th>Last Build Time</th>
              <th>Last Build Duration</th>
              <th>Last Build Number</th>
              <th>Last Worker</th>
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
        <table id="listFailedTable" class="table table-bordered table-striped" style="width: 100%;">
          <thead>
            <tr>
            <th>Job Name</th>
            <th>Environment</th>
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
            <th>Environment</th>
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
        <table id="listSuccessTable" class="table table-bordered table-striped" style="width: 100%;">
          <thead>
            <tr>
            <th>Job Name</th>
            <th>Environment</th>
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
            <th>Environment</th>
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
<!-- /.container-fluid -->

</section>
<!-- /.content -->
</div>
<!-- /.content-wrapper -->

<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/job-inspect-modal.js?v=1"></script>
<script type="text/javascript">

  var jobListRefreshTimer = null;
  var jobListRefreshIntervalMs = 5000;
  var jobListLoadGeneration = 0;
  var jobScheduleIndexRequest = null;
  var jobMonitorFilter = 'all';
  var jobMonitorFilterRegistered = false;
  var jobDataTablesErrorModeConfigured = false;
  var jobListLoadInProgress = false;
  var jobScheduleCache = {};
  var jobScheduleRequests = {};
  var selectedJobBuilds = {};
  var bulkTriggerInProgress = false;
  var canManageJobs = <?php echo $canManageJobs ? 'true' : 'false'; ?>;
  var jobEnvironmentFilter = window.jobseekerDashboardEnvironment || 'all';
  var deleteJobsUrl = <?php echo json_encode(base_url() . 'delete-job/jobs'); ?>;
  var availableJobsUrl = <?php echo json_encode(base_url() . 'jobCreation/availableJobs'); ?>;
  var jobSchedulesUrl = <?php echo json_encode(base_url() . 'jobCreation/jobSchedules'); ?>;
  var jobExecutionUrl = <?php echo json_encode(base_url() . 'jobExecution'); ?>;
  var environmentHelper = window.JobSeekerEnvironment || {
    detectFromConfig: function(xmlText, jobName) { return this.detectFromJob({name: jobName}); },
    detectFromJob: function(job) { return {environment: 'Unknown', source: 'Not detected', unknown: true}; },
    label: function() { return '<span class="label label-default">Unknown</span>'; },
    text: function(info) { return info && info.environment ? info.environment : 'Unknown'; }
  };

  function jenkinsJobPath(jobName) {
    return String(jobName == null ? '' : jobName).split('/').map(function(segment) {
      return 'job/' + encodeURIComponent(segment);
    }).join('/');
  }

  function deleteJenkinsJob(jobName, environment, deleteRepository) {
    return $.ajax({
      url: deleteJobsUrl,
      method: 'POST',
      dataType: 'json',
      data: {jobs: [jobName], environment: environment, delete_repositories: deleteRepository ? '1' : '0'}
    });
  }

  function hasKnownEnvironment(environment) {
    return environment && environment !== 'Unknown';
  }

  function triggerJenkinsJob(jobName, environment, jenkinsUrl, headers) {
    if (! hasKnownEnvironment(environment)) {
      return $.ajax({
        url: jenkinsUrl + jenkinsJobPath(jobName) + '/build',
        method: 'POST',
        headers: headers || {}
      }).then(null, function(xhr) {
        if (xhr && (xhr.status === 400 || xhr.status === 404)) {
          return $.ajax({
            url: jenkinsUrl + jenkinsJobPath(jobName) + '/buildWithParameters',
            method: 'POST',
            headers: headers || {}
          });
        }

        return $.Deferred().rejectWith(this, arguments).promise();
      });
    }

    return $.ajax({
      url: jenkinsUrl + jenkinsJobPath(jobName) + '/buildWithParameters',
      method: 'POST',
      headers: headers || {},
      data: { ENVIRONMENT: environment }
    }).then(null, function(xhr) {
      if (xhr && (xhr.status === 400 || xhr.status === 404)) {
        return $.ajax({
          url: jenkinsUrl + jenkinsJobPath(jobName) + '/build',
          method: 'POST',
          headers: headers || {}
        });
      }

      return $.Deferred().rejectWith(this, arguments).promise();
    });
  }

  function jobNameForRow(row) {
    return row && (row.fullName || row.name) ? row.fullName || row.name : '';
  }

  function isJobTriggerable(row) {
    return !! row && row.buildable !== false && row.inQueue !== true && ! isJobRunning(row);
  }

  function selectedJobItems() {
    return Object.keys(selectedJobBuilds).sort().map(function(jobName) {
      return selectedJobBuilds[jobName];
    });
  }

  function pruneSelectedJobBuilds(jobs) {
    var available = {};

    $.each(jobs || [], function(index, row) {
      var jobName = jobNameForRow(row);
      if (! jobName) {
        return;
      }

      available[jobName] = row;
      if (selectedJobBuilds[jobName]) {
        if (! isJobTriggerable(row)) {
          delete selectedJobBuilds[jobName];
        } else {
          selectedJobBuilds[jobName].environment = environmentTextForRow(row);
        }
      }
    });

    Object.keys(selectedJobBuilds).forEach(function(jobName) {
      if (! available[jobName]) {
        delete selectedJobBuilds[jobName];
      }
    });
  }

  function syncBulkJobSelectionUi(table) {
    if (table && table.rows) {
      table.rows().data().each(function(row) {
        var jobName = jobNameForRow(row);
        if (! jobName || ! selectedJobBuilds[jobName]) {
          return;
        }

        if (! isJobTriggerable(row)) {
          delete selectedJobBuilds[jobName];
        } else {
          selectedJobBuilds[jobName].environment = environmentTextForRow(row);
        }
      });
    }

    var selectedCount = selectedJobItems().length;
    var rowCheckboxes = $('#listTable_wrapper .job-select-row');
    var eligibleCheckboxes = rowCheckboxes.filter('[data-triggerable="1"]');

    rowCheckboxes.each(function() {
      var checkbox = $(this);
      var jobName = checkbox.data('job') || '';
      checkbox
        .prop('checked', !! selectedJobBuilds[jobName])
        .prop('disabled', bulkTriggerInProgress || checkbox.attr('data-triggerable') !== '1');
    });

    var checkedEligible = eligibleCheckboxes.filter(':checked').length;
    $('.job-select-visible')
      .prop('checked', eligibleCheckboxes.length > 0 && checkedEligible === eligibleCheckboxes.length)
      .prop('indeterminate', checkedEligible > 0 && checkedEligible < eligibleCheckboxes.length)
      .prop('disabled', bulkTriggerInProgress || eligibleCheckboxes.length === 0);

    $('#selectedJobBuildCount').text(selectedCount);
    $('#triggerSelectedJobs').prop('disabled', bulkTriggerInProgress || selectedCount === 0);
    $('#clearSelectedJobs').prop('disabled', bulkTriggerInProgress || selectedCount === 0);
  }

  function clearSelectedJobBuilds() {
    selectedJobBuilds = {};
    syncBulkJobSelectionUi();
  }

  function bulkTriggerFailureMessage(xhr) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
      return xhr.responseJSON.message;
    }
    if (xhr && xhr.responseText) {
      return xhr.responseText;
    }
    return 'Unable to trigger this job.';
  }

  function executeBulkJobTrigger(items) {
    var jenkinsUrl = '<?php echo $jenkins_url; ?>';
    var headers = {'Authorization': 'Basic ' + btoa('' + ':' + '')};
    var nextIndex = 0;
    var active = 0;
    var completed = 0;
    var successes = [];
    var failures = [];
    var concurrency = 3;

    bulkTriggerInProgress = true;
    $('.overlay').show();
    syncBulkJobSelectionUi();

    function finish() {
      bulkTriggerInProgress = false;
      $('.overlay').hide();
      syncBulkJobSelectionUi();

      if (successes.length) {
        toastr.success(successes.length + ' job trigger request(s) sent successfully.', 'Bulk Trigger Complete');
      }
      if (failures.length) {
        toastr.error(failures.length + ' job(s) failed to trigger: ' + failures.map(function(item) { return item.jobName; }).join(', '), 'Bulk Trigger Problems');
      }

      window.setTimeout(reloadJobTables, 1000);
      if (window.JobSeekerRunningJobs && window.JobSeekerRunningJobs.refresh) {
        window.JobSeekerRunningJobs.refresh();
      }
    }

    function updateProgress() {
      $('#triggerSelectedJobs').html('<i class="fa fa-spinner fa-spin"></i> Triggering ' + completed + '/' + items.length);
    }

    function pump() {
      while (active < concurrency && nextIndex < items.length) {
        (function(item) {
          active += 1;
          triggerJenkinsJob(item.jobName, item.environment, jenkinsUrl, headers)
            .done(function() {
              successes.push(item);
              delete selectedJobBuilds[item.jobName];
            })
            .fail(function(xhr) {
              failures.push({jobName: item.jobName, message: bulkTriggerFailureMessage(xhr)});
            })
            .always(function() {
              active -= 1;
              completed += 1;
              updateProgress();

              if (completed === items.length) {
                $('#triggerSelectedJobs').html('<i class="fa fa-play"></i> Trigger Selected <span class="badge" id="selectedJobBuildCount">' + selectedJobItems().length + '</span>');
                finish();
              } else {
                pump();
              }
            });
        })(items[nextIndex]);
        nextIndex += 1;
      }
    }

    updateProgress();
    pump();
  }

  function confirmBulkJobTrigger() {
    var items = selectedJobItems();
    if (! items.length || bulkTriggerInProgress) {
      return;
    }

    var previewLimit = 12;
    var preview = items.slice(0, previewLimit).map(function(item) {
      return '<li><b>' + escapeHtml(item.jobName) + '</b> <span class="text-muted">(' + escapeHtml(item.environment || 'Unknown') + ')</span></li>';
    }).join('');
    if (items.length > previewLimit) {
      preview += '<li>...and ' + (items.length - previewLimit) + ' more</li>';
    }

    alertify.confirm(
      'Trigger ' + items.length + ' Jenkins Jobs',
      '<p>Send build requests for the following jobs?</p><ul style="max-height:260px; overflow:auto; text-align:left;">' + preview + '</ul><p><b>Each job will use its detected environment.</b></p>',
      function() { executeBulkJobTrigger(items); },
      function() { alertify.message('Bulk trigger cancelled.'); }
    );
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

  function liveConsoleUrl(jobName, buildNumber, environment) {
    try {
      var target = new URL(jobExecutionUrl, window.location.href);
      target.searchParams.set('job', jobName || '');
      target.searchParams.set('build', buildNumber || '');
      target.searchParams.set('environment', environment || 'Unknown');
      return target.toString();
    } catch (error) {
      return jobExecutionUrl + '?job=' + encodeURIComponent(jobName || '') + '&build=' + encodeURIComponent(buildNumber || '') + '&environment=' + encodeURIComponent(environment || 'Unknown');
    }
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

  function isJobHealthy(row) {
    var result = lastBuildField(row, 'result');
    var color = normalizedJobColor(row && row.color);
    return row && row.buildable !== false && row.inQueue !== true && ! isJobRunning(row) && (result === 'SUCCESS' || color === 'blue');
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

  function environmentInfoForRow(row) {
    var key = scheduleCacheKey(row);
    var cached = jobScheduleCache[key];

    if (cached && cached.environmentInfo) {
      return cached.environmentInfo;
    }

    return environmentHelper.detectFromJob(row || {});
  }

  function environmentTextForRow(row) {
    return environmentHelper.text(environmentInfoForRow(row));
  }

  function normalizeEnvironmentFilterValue(value) {
    if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
      return window.JobSeekerGlobalEnvironment.normalize(value);
    }

    return String(value || '').toUpperCase();
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

  function renderEnvironment(row) {
    var info = environmentInfoForRow(row);
    return '<span class="environment-cell">' + environmentHelper.label(info) + '<small>' + escapeHtml(info.source || 'Not detected') + '</small></span>';
  }

  function rowMatchesMonitorFilter(row, filter) {
    if (filter === 'running') {
      return isJobRunning(row);
    }

    if (filter === 'healthy') {
      return isJobHealthy(row);
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
    updateEnvironmentFilterOptions(jobs);
    // The availableJobs endpoint already returned the selected environment.
    var environmentJobs = jobs || [];

    $('#monitor-filter-all').text(environmentJobs.length);
    $('#monitor-filter-healthy').text(environmentJobs.filter(isJobHealthy).length);
    $('#monitor-filter-running').text(environmentJobs.filter(isJobRunning).length);
    $('#monitor-filter-queued').text(environmentJobs.filter(function(job) { return job && job.inQueue === true; }).length);
    $('#monitor-filter-attention').text(environmentJobs.filter(isJobAttention).length);
    $('#monitor-filter-scheduled').text(environmentJobs.filter(isJobScheduled).length);
  }

  function updateEnvironmentFilterOptions(jobs) {
    var environments = {};
    var totalJobs = (jobs || []).length;

    $.each(jobs || [], function(index, job) {
      var environment = normalizeEnvironmentFilterValue(environmentTextForRow(job));
      if (isConfiguredEnvironment(environment)) {
        environments[environment] = (environments[environment] || 0) + 1;
      }
    });

    var options = '<option value="all">All environments (' + totalJobs + ')</option>';
    $.each(configuredEnvironmentNames().sort(), function(index, environment) {
      options += '<option value="' + escapeAttribute(environment) + '">' + escapeHtml(configuredEnvironmentLabel(environment)) + ' (' + (environments[environment] || 0) + ')</option>';
    });

    $('#monitorEnvironmentFilter').html(options).val(isAllEnvironmentFilter(jobEnvironmentFilter) ? 'all' : (isConfiguredEnvironment(jobEnvironmentFilter) ? normalizeEnvironmentFilterValue(jobEnvironmentFilter) : 'all'));
    jobEnvironmentFilter = $('#monitorEnvironmentFilter').val() || 'all';
  }

  function updateMonitorSummary(jobs) {
    jobs = jobs || [];
    var environmentJobs = jobs;
    var running = environmentJobs.filter(isJobRunning).length;
    var queued = environmentJobs.filter(function(job) { return job && job.inQueue === true; }).length;
    var attention = environmentJobs.filter(isJobAttention).length;

    $('#monitor-total-jobs').text(environmentJobs.length);
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

  function humanizeQueueWhy(why) {
    why = String(why == null ? '' : why).trim();
    if (! why) { return 'Waiting for Jenkins'; }
    var head = why.split(/;\s*['‘’"]/)[0].trim().replace(/[.;]+$/, '');
    if (/executor slot already in use|waiting for next available executor|all executors|is reserved for jobs with matching label/i.test(why)) {
      return 'Waiting for a free executor';
    }
    if (/in the quiet period/i.test(why)) { return 'Quiet period'; }
    if (/is offline|there are no nodes|no online node/i.test(why)) { return 'No matching worker online (' + head + ')'; }
    return head;
  }

  function renderQueue(row) {
    if (row && row.inQueue === true) {
      var why = row.queueItem && row.queueItem.why ? row.queueItem.why : 'Waiting for executor';
      return monitorPill('Queued', 'warning') + '<br><small>' + escapeHtml(humanizeQueueWhy(why)) + '</small>';
    }

    return '<span class="text-muted">No</span>';
  }

  function renderWorkerNode(value) {
    value = $.trim(String(value == null ? '' : value));
    return value ? escapeHtml(value) : '<span class="text-muted">Controller</span>';
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

  function scheduleInfoFromConfig(xmlText, jobName, fallbackRow) {
    try {
      var xml = typeof xmlText === 'string' ? $.parseXML(xmlText) : xmlText;
      var spec = firstCronLine($(xml).find('triggers spec').first().text());
      var environmentInfo = environmentHelper.detectFromConfig(xmlText, jobName);

      if (! environmentInfo || environmentInfo.unknown) {
        environmentInfo = environmentHelper.detectFromJob(fallbackRow || {name: jobName, fullName: jobName});
      }

      return {
        summary: summarizeCronSpec(spec),
        spec: spec || 'No cron trigger',
        nextRunText: estimateNextRun(spec),
        environmentInfo: environmentInfo,
        error: false
      };
    } catch (error) {
      return {
        summary: 'Schedule unavailable',
        spec: '',
        nextRunText: 'Unavailable',
        environmentInfo: environmentHelper.detectFromJob(fallbackRow || {name: jobName, fullName: jobName}),
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
    // A reload must re-read the shared schedule index too, otherwise a schedule
    // edited elsewhere would stay hidden behind the resolved promise.
    jobScheduleIndexRequest = null;
    availableJobsRequest = null;

    $.each(jobScheduleCache, function(key, value) {
      if (value && value.loading) {
        delete jobScheduleCache[key];
      }
    });
  }

  function hydrateJobSchedules(table, jenkinsUrl, headers, generation) {
    // Wait for the shared index first. Without this the visible page would race
    // ahead and re-request config.xml for rows the index is about to answer.
    jobScheduleIndex().done(function() {
      if (generation === jobListLoadGeneration) {
        hydrateVisibleJobSchedules(table, jenkinsUrl, headers, generation);
      }
    });
  }

  function hydrateVisibleJobSchedules(table, jenkinsUrl, headers, generation) {
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

        jobScheduleCache[key] = scheduleInfoFromConfig(xmlText, key, row);
        updateMonitorSummary(table.rows().data().toArray());
        table.rows().invalidate('data').draw(false);
      }).fail(function(request, statusText) {
        if (statusText === 'abort' || generation !== jobListLoadGeneration || ! $.fn.DataTable.isDataTable('#listTable')) {
          return;
        }

        jobScheduleCache[key] = {
          summary: 'Schedule unavailable',
          spec: '',
          nextRunText: 'Unavailable',
          environmentInfo: environmentHelper.detectFromJob(row),
          error: true
        };
        updateMonitorSummary(table.rows().data().toArray());
        table.rows().invalidate('data').draw(false);
      }).always(function() {
        delete jobScheduleRequests[key];
      });
    });
  }

  function drawEnvironmentFilteredBuildTables() {
    ['#listFailedTable', '#listSuccessTable'].forEach(function(selector) {
      if ($.fn.DataTable.isDataTable(selector)) {
        $(selector).DataTable().rows().invalidate('data').draw(false);
      }
    });
  }

  // One reading of every job's cron trigger, shared by all three tables.
  //
  // Jenkins only keeps a cron trigger in a job's config.xml, so this page used to
  // request config.xml from the browser once per job. On a list of sixty jobs that
  // was sixty proxied round trips per page view, enough to occupy every PHP worker
  // and stall the rest of the application. The server now reads them together and
  // caches the result, so the whole list costs one request.
  // The three tables on this page (all jobs, failed builds, successful builds)
  // render different views of one job list. They used to request it separately,
  // so a single page load fetched the same ~100 KB payload three times, and
  // three times again on every refresh.
  //
  // Share one response between them, but only briefly: the tables are reloaded
  // on a timer, and holding a resolved response any longer than it takes all
  // three to ask for it would freeze the list on whatever it showed first.
  var availableJobsRequest = null;
  var availableJobsRequestKey = '';
  var availableJobsRequestStartedAt = 0;
  var availableJobsShareWindowMs = 1000;

  function loadAvailableJobs() {
    var environment = String(jobEnvironmentRequestValue());
    var now = new Date().getTime();
    var inFlight = !! availableJobsRequest && availableJobsRequest.state() === 'pending';
    var stillShareable = !! availableJobsRequest
      && availableJobsRequestKey === environment
      && (inFlight || (now - availableJobsRequestStartedAt) < availableJobsShareWindowMs);

    if (! stillShareable) {
      availableJobsRequestKey = environment;
      availableJobsRequestStartedAt = now;
      availableJobsRequest = $.ajax({
        url: availableJobsUrl,
        method: 'GET',
        dataType: 'json',
        data: {environment: environment}
      });
    }

    return availableJobsRequest;
  }

  function jobScheduleIndex() {
    if (! jobScheduleIndexRequest) {
      jobScheduleIndexRequest = $.ajax({url: jobSchedulesUrl, method: 'GET', dataType: 'json'})
        .then(function(payload) {
          return payload && payload.schedules ? payload.schedules : {};
        }, function() {
          // Fall back to per-job requests below rather than losing the column.
          return {};
        });
    }

    return jobScheduleIndexRequest;
  }

  function scheduleInfoFromIndexEntry(entry, row) {
    var spec = firstCronLine(entry.spec || '');

    return {
      summary: summarizeCronSpec(spec),
      spec: spec || 'No cron trigger',
      nextRunText: estimateNextRun(spec),
      environmentInfo: {
        environment: environmentHelper.normalize(entry.environment),
        source: 'Jenkins parameter',
        unknown: false
      },
      error: false
    };
  }

  function hydrateJobEnvironmentCache(jobs, jenkinsUrl, headers, generation) {
    jobScheduleIndex().done(function(index) {
      if (generation !== jobListLoadGeneration) {
        return;
      }

      var applied = false;
      $.each(jobs || [], function(idx, row) {
        var key = scheduleCacheKey(row);
        var entry = key ? index[key] : null;

        // Only the server's parameter-derived environment is as authoritative as
        // parsing config.xml here. Anything else still falls through to the
        // per-job request, which can also read an environment out of the command.
        if (! key || ! entry || ! entry.environmentFromParameter || jobScheduleCache[key] || jobScheduleRequests[key]) {
          return;
        }

        jobScheduleCache[key] = scheduleInfoFromIndexEntry(entry, row);
        applied = true;
      });

      if (applied) {
        // Summarize the job list we were handed. Reading it back off the table
        // would race the draw that is still being handed the same rows.
        updateMonitorSummary(jobs || []);

        if ($.fn.DataTable.isDataTable('#listTable')) {
          $('#listTable').DataTable().rows().invalidate('data').draw(false);
          drawEnvironmentFilteredBuildTables();
        }
      }

      hydrateRemainingJobEnvironments(jobs, jenkinsUrl, headers, generation);
    });
  }

  function hydrateRemainingJobEnvironments(jobs, jenkinsUrl, headers, generation) {
    $.each(jobs || [], function(index, row) {
      var key = scheduleCacheKey(row);

      if (! key || jobScheduleCache[key] || jobScheduleRequests[key]) {
        return;
      }

      jobScheduleCache[key] = {loading: true, environmentInfo: environmentHelper.detectFromJob(row)};
      jobScheduleRequests[key] = $.ajax({
        contentType: 'application/text',
        url: jenkinsUrl + jenkinsJobPath(key) + '/config.xml',
        method: 'GET',
        headers: headers
      }).done(function(xmlText) {
        if (generation !== jobListLoadGeneration) {
          return;
        }

        jobScheduleCache[key] = scheduleInfoFromConfig(xmlText, key, row);
        if ($.fn.DataTable.isDataTable('#listTable')) {
          var table = $('#listTable').DataTable();
          updateMonitorSummary(table.rows().data().toArray());
          table.rows().invalidate('data').draw(false);
        }
        drawEnvironmentFilteredBuildTables();
      }).fail(function(request, statusText) {
        if (statusText === 'abort' || generation !== jobListLoadGeneration) {
          return;
        }

        jobScheduleCache[key] = {
          summary: 'Schedule unavailable',
          spec: '',
          nextRunText: 'Unavailable',
          environmentInfo: environmentHelper.detectFromJob(row),
          error: true
        };
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
      if (! settings.nTable || ['listTable', 'listFailedTable', 'listSuccessTable'].indexOf(settings.nTable.id) === -1) {
        return true;
      }

      var row = rowData || (settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex]._aData : null);

      if (settings.nTable.id !== 'listTable') {
        return true;
      }

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
    if (data == null || data === '') {
      return emptyText;
    }
    var ts = parseInt(data, 10);
    if (window.JobSeekerTime) {
      return JobSeekerTime.format(ts);
    }
    return moment(ts).format('MMMM Do YYYY, h:mm:ss a');
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

  $('#monitorEnvironmentFilter').on('change', function() {
    jobEnvironmentFilter = $(this).val() || 'all';
    ensureMonitorFilterRegistered();
    abortScheduleRequests();
    reloadJobTables();
  });

  $(document).on('jobseeker:environment-change', function(event, environment) {
    clearSelectedJobBuilds();
    jobEnvironmentFilter = isAllEnvironmentFilter(environment) ? 'all' : normalizeEnvironmentFilterValue(environment || 'all');
    ensureMonitorFilterRegistered();
    abortScheduleRequests();
    if ($.fn.DataTable.isDataTable('#listTable')) {
      reloadJobTables();
    }
  });

  $('#listTable').on('change', '.job-select-row', function() {
    var checkbox = $(this);
    var jobName = checkbox.data('job') || '';

    if (! jobName || checkbox.attr('data-triggerable') !== '1') {
      checkbox.prop('checked', false);
      return;
    }

    if (checkbox.is(':checked')) {
      selectedJobBuilds[jobName] = {
        jobName: jobName,
        environment: checkbox.data('environment') || 'Unknown'
      };
    } else {
      delete selectedJobBuilds[jobName];
    }

    syncBulkJobSelectionUi();
  });

  $(document).on('change', '.job-select-visible', function() {
    if (! $.fn.DataTable.isDataTable('#listTable') || bulkTriggerInProgress) {
      return;
    }

    var shouldSelect = $(this).is(':checked');
    var table = $('#listTable').DataTable();

    table.rows({page: 'current', search: 'applied'}).data().each(function(row) {
      var jobName = jobNameForRow(row);
      if (! jobName || ! isJobTriggerable(row)) {
        return;
      }

      if (shouldSelect) {
        selectedJobBuilds[jobName] = {
          jobName: jobName,
          environment: environmentTextForRow(row)
        };
      } else {
        delete selectedJobBuilds[jobName];
      }
    });

    syncBulkJobSelectionUi(table);
  });

  $('#triggerSelectedJobs').on('click', confirmBulkJobTrigger);
  $('#clearSelectedJobs').on('click', clearSelectedJobBuilds);

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
          // Most-recently-built jobs first (column 10 = last build time).
          "order": [[ 10, "desc" ]],
          "scrollX": true,
          "ajax": function(request, callback) {
            loadAvailableJobs().done(function(json) {
              // A shared response cannot be aborted per table the way DataTables
              // aborts its own request, so drop anything that outlived its reload.
              if (currentGeneration !== jobListLoadGeneration) {
                return;
              }

              var jobs = jobsFromJenkinsResponse(json);
              pruneSelectedJobBuilds(jobs);
              updateMonitorSummary(jobs);
              hydrateJobEnvironmentCache(jobs, jenkins_url, jobListHeaders, currentGeneration);
              callback({data: jobs});
            }).fail(function() {
              if (currentGeneration === jobListLoadGeneration) {
                callback({data: []});
              }
            });
          },
          "columns": [
          {"data": null, "defaultContent": "", "orderable": false, "searchable": false, "className": "job-select-cell", "render": function(data, type, row){
            if (type !== 'display') {
              return '';
            }

            var jobName = jobNameForRow(row);
            var environment = environmentTextForRow(row);
            var triggerable = isJobTriggerable(row);
            var checked = !! selectedJobBuilds[jobName];
            var title = triggerable ? 'Select ' + jobName + ' for bulk triggering' : 'This job is already running, queued, or disabled';
            return '<input type="checkbox" class="job-select-row" data-job="' + escapeAttribute(jobName) + '" data-environment="' + escapeAttribute(environment) + '" data-triggerable="' + (triggerable ? '1' : '0') + '" aria-label="' + escapeAttribute(title) + '" title="' + escapeAttribute(title) + '"' + (checked ? ' checked' : '') + (triggerable ? '' : ' disabled') + '>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderHealth(row); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderState(row); }},
          {"data": "name", "defaultContent": "", "render": function(data, type, row){ return escapeHtml(row.fullName || data || ''); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return type === 'sort' || type === 'type' ? environmentTextForRow(row) : renderEnvironment(row); }},
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
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            var ts = lastBuildField(row, 'timestamp');
            if (type === 'sort' || type === 'type') { return parseInt(ts, 10) || 0; }
            return renderBuildTime(ts, '<b>Never Built</b>');
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderDuration(lastBuildField(row, 'duration'), '<b>Never Built</b>'); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return escapeHtml(lastBuildField(row, 'number')); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){ return renderWorkerNode(lastBuildField(row, 'builtOn')); }},
          {"data": "description", "defaultContent": "", "render": function(data){ return escapeHtml(data); }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            var jobName = row.fullName || row.name || '';
            var buildNumber = lastBuildField(row, 'number');
            var environment = environmentTextForRow(row);

            if (isJobRunning(row) && buildNumber) {
              return '<a class="btn btn-sm btn-info live-console" href="' + escapeAttribute(liveConsoleUrl(jobName, buildNumber, environment)) + '" title="View the live console for this running build"><i class="fa fa-terminal"></i> Live</a>';
            }

            return '<button class="btn btn-sm btn-primary run" href="#" value="'+ escapeAttribute(jobName) +'" data-environment="' + escapeAttribute(environment) + '" title="Click to trigger this job build">Build</button>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            var jobName = row.fullName || row.name || '';
            var buildNumber = lastBuildField(row, 'number');
            var result = lastBuildField(row, 'result');
            var timestamp = lastBuildField(row, 'timestamp');
            var date = renderBuildTime(timestamp, '');
            return '<button class="btn btn-sm btn-info log" href="#" value="'+ escapeAttribute(jobName) +'" data-build="'+ escapeAttribute(buildNumber) +'" data-result="'+ escapeAttribute(result) +'" data-time="'+ escapeAttribute(date) +'" data-environment="' + escapeAttribute(environmentTextForRow(row)) + '" title="Click to check this job console output">Check</button>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            if (! isJobRunning(row)) {
              return '';
            }

            var jobName = row.fullName || row.name || '';
            return '<button class="btn btn-sm btn-danger abort" href="#" value="'+ escapeAttribute(jobName) +'" data-environment="' + escapeAttribute(environmentTextForRow(row)) + '" title="Click to cancel this job execution">Abort</button>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            var jobName = row.fullName || row.name || '';
            return '<button type="button" class="btn btn-sm btn-default inspectJenkinsJob" data-job="'+ escapeAttribute(jobName) +'" title="Inspect this Jenkins job"><i class="fa fa-eye"></i> Inspect</button>';
          }},
          {"data": null, "defaultContent": "", "render": function(data, type, row){
            if (! canManageJobs) {
              return '';
            }

            var jobName = row.fullName || row.name || '';
            return '<button class="btn btn-sm btn-danger delete-job" href="#" value="'+ escapeAttribute(jobName) +'" data-environment="' + escapeAttribute(environmentTextForRow(row)) + '" data-running="'+ (isJobRunning(row) ? '1' : '0') +'" title="Delete this Jenkins job">Delete</button>';
          }}
          ],
          "drawCallback": function() {
            var table = this.api();
            hydrateJobSchedules(table, jenkins_url, jobListHeaders, currentGeneration);
            syncBulkJobSelectionUi(table);
          }
       });
      $('#box2').boxWidget('expand');

        destroyDataTable('#listFailedTable');
        $('#listFailedTable').DataTable({
          "lengthMenu": [3,5,10,15,20,100,200,500,1000],
          "pageLength": 20,
          "order": [[ 4, "desc" ]],
          "scrollX": true,
          "ajax": function(request, callback) {
            loadAvailableJobs().done(function(json) {
              if (currentGeneration === jobListLoadGeneration) {
                callback({data: jobsWithBuild(json, 'lastFailedBuild')});
              }
            }).fail(function() {
              if (currentGeneration === jobListLoadGeneration) {
                callback({data: []});
              }
            });
          },
          "columns": [
          {"data": "name", "defaultContent": ""},
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
          }},{targets:1, render:function(data, type, row){
            return type === 'sort' || type === 'type' ? environmentTextForRow(row) : environmentHelper.label(environmentInfoForRow(row));
          }},{targets:2, render:function(data){
            if(data != null){if(data == 'SUCCESS') { return '<b style="color: green;">' + data + '</b>'} else {return '<b style="color: red;">' + data + '</b>'}} else {return ''}
          }},{targets:3, render:function(data){
            return renderText(data);
          }},{targets:4, render:function(data){
            return renderBuildTime(data, '');
          }},{targets:5, render:function(data){
            return renderDuration(data, '');
          }},{targets:6, render:function(data){
            return renderText(data);
          }},{targets:7, render:function(data){
            return renderText(data);
          }},{targets:8, render:function(data){
            return renderText(data);
          }}]
       });
        $('#box3').boxWidget('expand');

        destroyDataTable('#listSuccessTable');
        $('#listSuccessTable').DataTable({
          "lengthMenu": [3,5,10,15,20,100,200,500,1000],
          "pageLength": 20,
          "order": [[ 4, "desc" ]],
          "scrollX": true,
          "ajax": function(request, callback) {
            loadAvailableJobs().done(function(json) {
              if (currentGeneration === jobListLoadGeneration) {
                callback({data: jobsWithBuild(json, 'lastStableBuild')});
              }
            }).fail(function() {
              if (currentGeneration === jobListLoadGeneration) {
                callback({data: []});
              }
            });
          },
          "columns": [
          {"data": "name", "defaultContent": ""},
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
          }},{targets:1, render:function(data, type, row){
            return type === 'sort' || type === 'type' ? environmentTextForRow(row) : environmentHelper.label(environmentInfoForRow(row));
          }},{targets:2, render:function(data){
            if(data != null){if(data == 'SUCCESS') { return '<b style="color: green;">' + data + '</b>'} else {return '<b style="color: red;">' + data + '</b>'}} else {return ''}
          }},{targets:3, render:function(data){
            return renderText(data);
          }},{targets:4, render:function(data){
            return renderBuildTime(data, '');
          }},{targets:5, render:function(data){
            return renderDuration(data, '');
          }},{targets:6, render:function(data){
            return renderText(data);
          }},{targets:7, render:function(data){
            return renderText(data);
          }},{targets:8, render:function(data){
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
           jenkins_token = '';

      var job=$(this).val();
  var environment = $(this).data('environment') || 'Unknown';
         

    alertify.confirm('Job <b style="color:red;">'+ escapeHtml(job) +'</b> Abort Request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p>Are you sure you want to Abort the job <b>'+ escapeHtml(job) +' ?</b></p><p>Environment: <b>'+ escapeHtml(environment) +'</b></p></div></div></div>',
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
             environment = button.data('environment') || 'Unknown',
           isRunning = button.data('running') == 1;

       var runningWarning = isRunning ? '<p><b>This job appears to be running. Jenkins may reject deletion until the build is stopped.</b></p>' : '';

           alertify.confirm('Job <b style="color:red;">'+ escapeHtml(job) +'</b> Delete Request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p>Are you sure you want to delete the job <b>'+ escapeHtml(job) +'</b> permanently?</p><p>Environment: <b>'+ escapeHtml(environment) +'</b></p>' + runningWarning + '<div class="checkbox text-left"><label><input type="checkbox" id="deleteBuildListRepository" checked> Also delete assigned job repository files.</label></div></div></div></div>',
      function(){
        var deleteRepository = $('#deleteBuildListRepository').is(':checked');
        $('.overlay').show();

        deleteJenkinsJob(job, environment, deleteRepository).done(function(data) {
          var result = data && data.results && data.results.length ? data.results[0] : null;
          if (! result || ! result.deleted) {
            toastr.error(result && result.error ? result.error : 'The backend did not delete this job.', 'Delete Failed');
            $('.overlay').hide();
            return;
          }
          toastr.success('Jenkins job deleted successfully.', 'Job Deleted');
          delete jobScheduleCache[job];
          if (deleteRepository) {
            reportRepositoryDeleteResults({results: [{exist: !!(result.systems && result.systems.length), systems: result.systems || [], error: result.error || ''}]});
          }
          reloadJobTables();
          $('.overlay').hide();
        }).fail(function(xhr) {
          var message = xhr && xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'The Jenkins job could not be deleted.';
          toastr.error(message, 'Delete Failed');
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
           jenkins_token = '';

      var job=$(this).val();
  var environment = $(this).data('environment') || 'Unknown';
         

    alertify.confirm('Job <b style="color:red;">'+ escapeHtml(job) +'</b> Trigger Request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p>Are you sure you want to trigger the job <b>'+ escapeHtml(job) +' ?</b></p><p>Environment: <b>'+ escapeHtml(environment) +'</b></p></div></div></div>',
      function(){ 

        $('.overlay').show();
        triggerJenkinsJob(job, environment, jenkins_url, {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)})
          .done(function(data) {
            toastr.warning("Your Execution Request has been sent to server.", "Request Sent")
             setTimeout(function(){
            reloadJobTables();
         }, 1000);
            $('.overlay').hide();
          }).fail(function(xhr) {
            var message = xhr && xhr.responseText ? xhr.responseText : 'Unable to trigger this job.';
            toastr.error(message, 'Trigger Failed');
            $('.overlay').hide();
          });
    }, 
      function(){ 
        alertify.error('Operation Aborted')
    }
  );
    
});


function renderJobListConsoleLog(name, buildNumber, result, date, environment, output) {
  var consoleOutput = output || 'Console output is empty for this build.';
  $("#addLog").html('<div class="destroy"><table class="table table-bordered"><tbody><tr><th width="10px">Header</th><th>Task</th></tr><tr><td>Execution Date</td><td>'+ escapeHtml(date) +'</td></tr><tr><td>Job Name</td><td>'+ escapeHtml(name) +' <b>['+ escapeHtml(buildNumber) +']</b> </td></tr><tr><td>Environment</td><td>'+ escapeHtml(environment) +'</td></tr><tr><td>Status</td><td>'+ escapeHtml(result) +'</td></tr><tr><td>Console Log</td><td><div id="jobListConsoleLog"></div></td></tr></tbody></table></div>');

  if (window.JobSeekerConsole) {
    window.JobSeekerConsole.setText('#jobListConsoleLog', consoleOutput, {live: String(result).toUpperCase() === 'RUNNING'});
  } else {
    $('#jobListConsoleLog').text(consoleOutput);
  }

  $('#modal-default').modal('show');
}

$("#listTable").on('click','.log',function(){

        // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>',
          jenkins_username = '',
          jenkins_token = '';


         // get the current row Id, job name and instance id
         var button = $(this),
           name = button.val(),
           result = button.data('result') || '',
           buildNumber = button.data('build') || '',
           date = button.data('time') || '',
           environment = button.data('environment') || 'Unknown';

        if(buildNumber == '' || buildNumber == null){
          var output = 'Your requested job ' + name + ' has not been executed yet. Please, try again later.';
          renderJobListConsoleLog(name, buildNumber, result, date, environment, output);
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
              renderJobListConsoleLog(name, buildNumber, result, date, environment, output);
            },
            complete: function(data) {
                dateRequest = data;
                $(".overlay").hide();
            }

         });

    });

$(document).on('click', '.inspectJenkinsJob', function() {
  JobSeekerJobInspect.open({
    jobName: $(this).data('job') || '',
    button: this
  });
});

  $(function() {
    $('#refresh').prop('checked', true);
    setTimeout(function() {
      loadJobListData({silent: true});
    }, 0);
  });
</script>
