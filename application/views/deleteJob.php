<style type="text/css">
  .checkbox input {
    transform: scale(1.5);
  }

  .delete-actions {
    display: grid;
    gap: 6px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    padding: 10px;
  }

  .delete-actions::before,
  .delete-actions::after {
    content: none;
    display: none;
  }

  .delete-actions .btn {
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    white-space: nowrap;
  }

  .delete-actions .delete-primary-action {
    grid-column: 1 / -1;
  }

  .delete-job-layout {
    align-items: stretch;
    display: flex;
    flex-wrap: wrap;
  }

  .delete-job-column {
    display: flex;
    margin-bottom: 15px;
  }

  .delete-job-box {
    display: flex;
    flex-direction: column;
    min-height: 480px;
    width: 100%;
  }

  .delete-job-box > form {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
  }

  .delete-job-box .box-body {
    flex: 1 1 auto;
    padding: 20px;
  }

  .delete-job-box .box-footer {
    background: #fff;
    margin-top: auto;
  }

  .delete-job-summary {
    background: #f9fafc;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    margin-bottom: 14px;
    padding: 10px 12px;
  }

  .delete-job-summary strong {
    display: block;
    margin-bottom: 3px;
  }

  .delete-job-select {
    min-height: 300px;
  }

  .delete-selection-help {
    display: block;
    margin-top: 6px;
  }

  .delete-job-environment-summary {
    color: #777;
    display: block;
    margin-top: 8px;
  }

  .delete-job-environment-summary .label {
    display: inline-block;
    margin: 0 4px 4px 0;
  }

  .delete-environment-filter-bar {
    background: #fff;
    border: 1px solid #d2d6de;
    border-radius: 4px;
    margin-top: 15px;
    padding: 12px;
  }

  .delete-environment-filter-bar label {
    margin-right: 8px;
  }

  .delete-environment-filter-bar select {
    display: inline-block;
    max-width: 340px;
  }

  @media (max-width: 991px) {
    .delete-job-layout,
    .delete-job-column,
    .delete-job-box {
      display: block;
      min-height: 0;
    }
  }

  @media (max-width: 479px) {
    .delete-actions {
      grid-template-columns: 1fr;
    }

    .delete-actions .delete-primary-action {
      grid-column: auto;
    }
  }

</style>
<div class="content-wrapper">    
    <section class="content-header">
      <h1>
        Delete Job Panel
        <small>This is a area to delete jobs files and repositories.</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Job Management</a></li>
        <li class="active">Delete Job</li>
    </ol>
</section>
<section class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-xs-12">
        <div class="delete-environment-filter-bar">
          <label for="deleteEnvironmentFilter"><i class="fa fa-globe"></i> Filter by environment</label>
          <select id="deleteEnvironmentFilter" class="form-control input-sm">
            <option value="all">All environments</option>
          </select>
          <label for="deleteStatusFilter"><i class="fa fa-heartbeat"></i> Filter by status</label>
          <select id="deleteStatusFilter" class="form-control input-sm">
            <option value="all">All statuses</option>
            <option value="healthy">Healthy / Success</option>
            <option value="running">Running</option>
            <option value="queued">Queued</option>
            <option value="attention">Needs Attention</option>
            <option value="disabled">Disabled</option>
            <option value="never-built">Never Built</option>
          </select>
          <span class="text-muted" id="deleteEnvironmentFilterStatus">Loading Jenkins jobs...</span>
        </div>
      </div>
    </div>
    <div class="row delete-job-layout" style="margin-top: 15px;">
       <div class="col-lg-6 col-md-6 col-xs-12 delete-job-column">
         <div class="box box-danger delete-job-box">
            <div class="overlay" style="display:none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
            <div class="box-header with-border">
              <h3 class="box-title"><b>Delete Jenkins Jobs</b></h3>
            </div>
            <form role="form" id="delJob">
             <div class="box-body">
              <div class="delete-job-summary">
                <strong><i class="fa fa-server"></i> Jenkins job configuration</strong>
                <span class="text-muted">Optionally remove matching uploaded source folders after the Jenkins job is deleted.</span>
              </div>
              <div class="form-group">
                  <label for="deleteJobSelect">Select jobs to delete</label>
                  <select class="form-control selector delete-job-select" id="deleteJobSelect" multiple>
                        </select>
                  <small class="text-muted delete-selection-help"><span id="deleteJobCount">0</span> job(s) selected. Hold Ctrl/Cmd to select multiple jobs.</small>
                  <small class="delete-job-environment-summary" id="deleteJobEnvironmentSummary"><span class="text-muted">No environments selected.</span></small>
              </div>
              <div class="form-group">
                    <label for="deleteRepoCheck">Repository cleanup</label>
                    <div class="checkbox">
                        <label for="deleteRepository">
                    <input type="checkbox" name="deleteRepoCheck" id="deleteRepoCheck" value="1"> Also delete assigned job repositories and files.
                      </label>
                  </div>
              </div>
      </div>
          <div class="box-footer delete-actions">
           <button type="button" id="selectAllJobs" class="btn btn-default"><i class="fa fa-check-square-o"></i> Select All</button>
           <button type="button" id="clearSelectedJobs" class="btn btn-default"><i class="fa fa-square-o"></i> Clear</button>
           <button type="button" id="reloadDeleteJobs" class="btn btn-info"><i class="fa fa-refresh"></i> Reload</button>
           <button type="button" id="deleteJob" class="btn btn-danger delete-primary-action"><i class="fa fa-trash"></i> Delete Selected Jobs</button>
     </div>
 </form> 
</div>
</div>
<div class="col-lg-6 col-md-6 col-xs-12 delete-job-column">
 <div class="box box-warning delete-job-box">
    <div class="overlay" style="display:none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
    <div class="box-header with-border">
      <h3 class="box-title"><b>Delete Job Repositories Only</b></h3>
    </div>
    <form role="form" id="delRepository">
     <div class="box-body">
          <div class="delete-job-summary">
            <strong><i class="fa fa-folder-open"></i> Uploaded source folders</strong>
            <span class="text-muted">Remove stored job files while leaving the Jenkins job configuration available.</span>
          </div>
          <div class="form-group">
          <label for="deleteRepoSelect">Select repositories to delete</label>
          <select class="form-control selector delete-job-select" id="deleteRepoSelect" multiple>
                </select>
          <small class="text-muted delete-selection-help"><span id="deleteRepoCount">0</span> repository selection(s). This does not delete Jenkins jobs.</small>
              <small class="delete-job-environment-summary" id="deleteRepoEnvironmentSummary"><span class="text-muted">No environments selected.</span></small>
            </div>
</div>
  <div class="box-footer delete-actions">
   <button type="button" id="selectAllRepos" class="btn btn-default"><i class="fa fa-check-square-o"></i> Select All</button>
   <button type="button" id="clearSelectedRepos" class="btn btn-default"><i class="fa fa-square-o"></i> Clear</button>
   <button type="button" id="reloadDeleteRepos" class="btn btn-info"><i class="fa fa-refresh"></i> Reload</button>
   <button type="button" id="delRepoBtn" class="btn btn-danger delete-primary-action"><i class="fa fa-trash"></i> Delete Selected Repositories</button>
</div>
</form> 
</div>
</div>
</div>
</div>
</section>
</div>

<script type="text/javascript">

$(document).ready(function(){

    var jenkins_url = <?php echo json_encode($jenkins_url); ?>;
    var deleteRepositoriesUrl = <?php echo json_encode(base_url() . 'DeleteJob/deleteRepositories'); ?>;
    var availableJobsUrl = <?php echo json_encode(base_url() . 'jobCreation/availableJobs'); ?>;
    var jobsByName = {};
    var allDeleteJobs = [];
    var deleteEnvironmentFilter = window.jobseekerDashboardEnvironment || 'all';
    var deleteStatusFilter = 'all';
    var deleteEnvironmentRequests = {};
    var environmentHelper = window.JobSeekerEnvironment || {
      detectFromJob: function() { return {environment: 'Unknown', source: 'Not detected', unknown: true}; },
      normalize: function(value) { return $.trim(String(value || '')).toUpperCase(); },
      label: function() { return '<span class="label label-default">Unknown</span>'; },
      text: function(info) { return info && info.environment ? info.environment : 'Unknown'; }
    };

    if (jenkins_url && jenkins_url.charAt(jenkins_url.length - 1) !== '/') {
      jenkins_url += '/';
    }

    $("#deleteRepoCheck").prop("checked", true);

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

    function jenkinsJobPath(jobName) {
      return String(jobName == null ? '' : jobName).split('/').map(function(segment) {
        return 'job/' + encodeURIComponent(segment);
      }).join('/');
    }

    function setDeleteBusy(isBusy) {
      $('.overlay').toggle(isBusy);
      $('#deleteJob, #delRepoBtn, #reloadDeleteJobs, #reloadDeleteRepos, #selectAllJobs, #clearSelectedJobs, #selectAllRepos, #clearSelectedRepos')
        .prop('disabled', isBusy)
        .toggleClass('disabled', isBusy);
    }

    function selectedValues(selector) {
      return ($(selector).val() || []).filter(function(value) {
        return value !== null && value !== '' && value !== '0';
      });
    }

    function jobEnvironmentInfo(jobName) {
      var job = jobsByName[jobName] || {name: jobName, fullName: jobName};
      return job.environmentInfo || environmentHelper.detectFromJob(job);
    }

    function environmentTextForJobName(jobName) {
      return environmentHelper.text(jobEnvironmentInfo(jobName));
    }

    function normalizeDeleteEnvironment(value) {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
        return window.JobSeekerGlobalEnvironment.normalize(value);
      }

      return environmentHelper.normalize(value);
    }

    function configuredDeleteEnvironments() {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.configuredEnvironmentNames) {
        return window.JobSeekerGlobalEnvironment.configuredEnvironmentNames();
      }

      return $.map(window.jobseekerGlobalEnvironmentOptions || [], function(value) {
        return normalizeDeleteEnvironment(value);
      });
    }

    function configuredDeleteEnvironmentLabel(environment) {
      var normalized = normalizeDeleteEnvironment(environment);
      var labels = window.jobseekerGlobalEnvironmentOptions || [];

      for (var index = 0; index < labels.length; index++) {
        if (normalizeDeleteEnvironment(labels[index]) === normalized) {
          return labels[index];
        }
      }

      return normalized;
    }

    function isConfiguredDeleteEnvironment(environment) {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.isConfiguredEnvironment) {
        return window.JobSeekerGlobalEnvironment.isConfiguredEnvironment(environment);
      }

      return $.inArray(normalizeDeleteEnvironment(environment), configuredDeleteEnvironments()) !== -1;
    }

    function isAllDeleteEnvironmentFilter(value) {
      return String(value || '').toLowerCase() === 'all';
    }

    function deleteEnvironmentRequestValue() {
      return isAllDeleteEnvironmentFilter(deleteEnvironmentFilter) ? 'all' : normalizeDeleteEnvironment(deleteEnvironmentFilter);
    }

    function normalizedDeleteJobColor(jobName) {
      var job = jobsByName[jobName] || {};
      return String(job.color || '').replace('_anime', '');
    }

    function deleteJobLastResult(jobName) {
      var job = jobsByName[jobName] || {};
      return job.lastBuild && job.lastBuild.result ? String(job.lastBuild.result).toUpperCase() : '';
    }

    function isDeleteJobRunning(jobName) {
      var job = jobsByName[jobName] || {};
      return !!((job.lastBuild && job.lastBuild.building === true) || /_anime$/.test(String(job.color || '')));
    }

    function isDeleteJobHealthy(jobName) {
      var job = jobsByName[jobName] || {};
      return job.buildable !== false && job.inQueue !== true && ! isDeleteJobRunning(jobName) && (deleteJobLastResult(jobName) === 'SUCCESS' || normalizedDeleteJobColor(jobName) === 'blue');
    }

    function isDeleteJobAttention(jobName) {
      var result = deleteJobLastResult(jobName);
      var color = normalizedDeleteJobColor(jobName);
      return $.inArray(result, ['FAILURE', 'ABORTED', 'UNSTABLE']) !== -1 || $.inArray(color, ['red', 'yellow', 'aborted']) !== -1;
    }

    function isDeleteJobNeverBuilt(jobName) {
      var job = jobsByName[jobName] || {};
      return ! job.lastBuild || normalizedDeleteJobColor(jobName) === 'notbuilt';
    }

    function deleteJobStatusText(jobName) {
      var job = jobsByName[jobName] || {};

      if (job.buildable === false) {
        return 'Disabled';
      }

      if (job.inQueue === true) {
        return 'Queued';
      }

      if (isDeleteJobRunning(jobName)) {
        return 'Running';
      }

      if (isDeleteJobHealthy(jobName)) {
        return 'Healthy';
      }

      if (isDeleteJobAttention(jobName)) {
        return deleteJobLastResult(jobName) || 'Needs Attention';
      }

      if (isDeleteJobNeverBuilt(jobName)) {
        return 'Never Built';
      }

      return 'Idle';
    }

    function jobNameMatchesDeleteStatus(jobName) {
      var job = jobsByName[jobName] || {};

      if (deleteStatusFilter === 'healthy') {
        return isDeleteJobHealthy(jobName);
      }

      if (deleteStatusFilter === 'running') {
        return isDeleteJobRunning(jobName);
      }

      if (deleteStatusFilter === 'queued') {
        return job.inQueue === true;
      }

      if (deleteStatusFilter === 'attention') {
        return isDeleteJobAttention(jobName);
      }

      if (deleteStatusFilter === 'disabled') {
        return job.buildable === false;
      }

      if (deleteStatusFilter === 'never-built') {
        return isDeleteJobNeverBuilt(jobName);
      }

      return true;
    }

    function jobNameMatchesDeleteEnvironment(jobName) {
      if (isAllDeleteEnvironmentFilter(deleteEnvironmentFilter)) {
        return true;
      }

      var environment = normalizeDeleteEnvironment(environmentTextForJobName(jobName));

      if (! isConfiguredDeleteEnvironment(environment)) {
        return false;
      }

      return deleteEnvironmentFilter === 'all' || environment === normalizeDeleteEnvironment(deleteEnvironmentFilter);
    }

    function environmentSummaryHtml(jobNames) {
      var counts = {};

      $.each(jobNames || [], function(index, jobName) {
        var environment = environmentHelper.text(jobEnvironmentInfo(jobName));
        counts[environment] = (counts[environment] || 0) + 1;
      });

      return Object.keys(counts).sort().map(function(environment) {
        return environmentHelper.label({environment: environment, source: 'Selected jobs'}) + ' ' + counts[environment];
      }).join(' ');
    }

    function updateSelectedCounts() {
      $('#deleteJobCount').text(selectedValues('#deleteJobSelect').length);
      $('#deleteRepoCount').text(selectedValues('#deleteRepoSelect').length);
      $('#deleteJobEnvironmentSummary').html(environmentSummaryHtml(selectedValues('#deleteJobSelect')) || '<span class="text-muted">No environments selected.</span>');
      $('#deleteRepoEnvironmentSummary').html(environmentSummaryHtml(selectedValues('#deleteRepoSelect')) || '<span class="text-muted">No environments selected.</span>');
    }

    function optionListHtml(jobNames) {
      var maxVisibleJobs = 10;
      var visibleJobs = jobNames.slice(0, maxVisibleJobs).map(function(jobName) {
        return '<li>' + escapeHtml(jobName) + ' ' + environmentHelper.label(jobEnvironmentInfo(jobName)) + '</li>';
      }).join('');
      var hiddenCount = jobNames.length - maxVisibleJobs;

      if (hiddenCount > 0) {
        visibleJobs += '<li>and ' + hiddenCount + ' more...</li>';
      }

      return '<ul class="text-left">' + visibleJobs + '</ul>';
    }

    function renderDeleteEnvironmentFilterOptions() {
      var counts = {};
      var totalJobs = allDeleteJobs.length;

      $.each(allDeleteJobs, function(index, job) {
        var name = job.fullName || job.name || '';
        var environment = normalizeDeleteEnvironment(environmentTextForJobName(name));
        if (isConfiguredDeleteEnvironment(environment)) {
          counts[environment] = (counts[environment] || 0) + 1;
        }
      });

      var currentValue = deleteEnvironmentFilter;
      var options = '<option value="all">All environments (' + totalJobs + ')</option>';
      $.each(configuredDeleteEnvironments().sort(), function(index, environment) {
        options += '<option value="' + escapeHtml(environment) + '">' + escapeHtml(configuredDeleteEnvironmentLabel(environment)) + ' (' + (counts[environment] || 0) + ')</option>';
      });

      $('#deleteEnvironmentFilter').html(options);
      $('#deleteEnvironmentFilter').val(isAllDeleteEnvironmentFilter(currentValue) ? 'all' : (isConfiguredDeleteEnvironment(currentValue) ? normalizeDeleteEnvironment(currentValue) : 'all'));
      deleteEnvironmentFilter = $('#deleteEnvironmentFilter').val() || 'all';
      $('#deleteEnvironmentFilterStatus').text(totalJobs + ' configured-environment Jenkins job(s) loaded.');
    }

    function filteredDeleteJobNames() {
      return allDeleteJobs.map(function(job) {
        return job.fullName || job.name || '';
      }).filter(function(name, index, names) {
        if (name === '' || names.indexOf(name) !== index) {
          return false;
        }

        return jobNameMatchesDeleteEnvironment(name) && jobNameMatchesDeleteStatus(name);
      }).sort();
    }

    function renderDeleteSelectors() {
      var selectedJobs = selectedValues('#deleteJobSelect');
      var selectedRepos = selectedValues('#deleteRepoSelect');
      var names = filteredDeleteJobNames();

      renderDeleteEnvironmentFilterOptions();
      $('.selector').empty();
      $.each(names, function(index, name) {
        var environment = environmentTextForJobName(name);
        var status = deleteJobStatusText(name);
        $('#deleteJobSelect').append($('<option>', {
          value: name,
          text: '[' + environment + '] [' + status + '] ' + name,
          selected: selectedJobs.indexOf(name) !== -1
        }));
        $('#deleteRepoSelect').append($('<option>', {
          value: name,
          text: '[' + environment + '] [' + status + '] ' + name,
          selected: selectedRepos.indexOf(name) !== -1
        }));
      });

      updateSelectedCounts();
    }

    function hydrateDeleteJobEnvironments() {
      $.each(allDeleteJobs, function(index, job) {
        var name = job.fullName || job.name || '';

        if (!name || job.environmentHydrated || deleteEnvironmentRequests[name]) {
          return;
        }

        deleteEnvironmentRequests[name] = $.ajax({
          url: jenkins_url + jenkinsJobPath(name) + '/config.xml',
          method: 'GET',
          dataType: 'text'
        }).done(function(xmlText) {
            var info = environmentHelper.detectFromConfig(xmlText || '', name);
            job.environmentInfo = info && ! info.unknown ? info : environmentHelper.detectFromJob(job);
          job.environmentHydrated = true;
          renderDeleteSelectors();
        }).fail(function() {
          job.environmentInfo = environmentHelper.detectFromJob(job);
          job.environmentHydrated = true;
        }).always(function() {
          delete deleteEnvironmentRequests[name];
        });
      });
    }

    function abortDeleteEnvironmentRequests() {
      $.each(deleteEnvironmentRequests, function(name, request) {
        if (request && request.readyState !== 4) {
          request.abort();
        }
      });

      deleteEnvironmentRequests = {};
    }

    function populateSelectors(jobs) {
      jobsByName = {};
      allDeleteJobs = (jobs || []).map(function(job) {
        var name = job.fullName || job.name || '';

        if (name !== '') {
          job.environmentInfo = environmentHelper.detectFromJob(job);
          jobsByName[name] = job;
        }

        return job;
      }).filter(function(job, index, allJobs) {
        var name = job.fullName || job.name || '';
        return name !== '' && allJobs.findIndex(function(candidate) {
          return (candidate.fullName || candidate.name || '') === name;
        }) === index;
      }).sort(function(left, right) {
        return String(left.fullName || left.name || '').localeCompare(String(right.fullName || right.name || ''));
      });

      renderDeleteSelectors();
      hydrateDeleteJobEnvironments();
    }

    function removeDeletedOptions(jobNames) {
      $.each(jobNames, function(index, jobName) {
        delete jobsByName[jobName];
        $('.selector option').filter(function() {
          return this.value === jobName;
        }).remove();
      });

      allDeleteJobs = allDeleteJobs.filter(function(job) {
        return jobNames.indexOf(job.fullName || job.name || '') === -1;
      });

      renderDeleteSelectors();
    }

    function loadDeleteOptions() {
      abortDeleteEnvironmentRequests();
      setDeleteBusy(true);
      $.ajax({
        url: availableJobsUrl,
        method: 'GET',
        data: {environment: deleteEnvironmentRequestValue()}
      }).done(function(data) {
        populateSelectors(data && data.jobs ? data.jobs : []);
      }).fail(function() {
        console.error(arguments);
        toastr.error('Could not load Jenkins jobs.', 'Load Jobs Failed');
      }).always(function() {
        setDeleteBusy(false);
      });
    }

    function deleteJenkinsJob(jobName) {
      return $.ajax({
        url: jenkins_url + jenkinsJobPath(jobName) + '/doDelete',
        method: 'POST'
      });
    }

    function deleteJenkinsJobs(jobNames) {
      var results = [];
      var chain = $.Deferred().resolve().promise();

      $.each(jobNames, function(index, jobName) {
        chain = chain.then(function() {
          var step = $.Deferred();
          deleteJenkinsJob(jobName).done(function() {
            results.push({job: jobName, deleted: true});
          }).fail(function(request) {
            results.push({
              job: jobName,
              deleted: false,
              error: request && request.responseText ? request.responseText : 'Unable to delete Jenkins job.'
            });
          }).always(function() {
            step.resolve();
          });

          return step.promise();
        });
      });

      return chain.then(function() {
        return results;
      });
    }

    function deleteRepositories(jobNames) {
      return $.ajax({
        url: deleteRepositoriesUrl,
        method: 'POST',
        dataType: 'json',
        data: {jobs: jobNames}
      });
    }

    function reportJenkinsDeleteResults(results) {
      var deletedJobs = results.filter(function(result) { return result.deleted; }).map(function(result) { return result.job; });
      var failedJobs = results.filter(function(result) { return ! result.deleted; });

      if (deletedJobs.length > 0) {
        toastr.success(deletedJobs.length + ' Jenkins job(s) deleted.', 'Jobs Deleted');
        removeDeletedOptions(deletedJobs);
      }

      if (failedJobs.length > 0) {
        toastr.error(failedJobs.length + ' Jenkins job(s) could not be deleted.', 'Delete Failed');
        console.error('Jenkins delete failures', failedJobs);
      }

      return deletedJobs;
    }

    function reportRepositoryDeleteResults(data) {
      var results = data && data.results ? data.results : [];
      var deletedRepositories = results.filter(function(result) { return result.exist; });
      var invalidRepositories = results.filter(function(result) { return result.error; });

      if (deletedRepositories.length > 0) {
        toastr.success(deletedRepositories.length + ' repository folder(s) deleted.', 'Repositories Deleted');
      } else if (results.length > 0) {
        toastr.warning('No matching repository folders were found.', 'No Repository Found');
      }

      if (invalidRepositories.length > 0) {
        toastr.error(invalidRepositories.length + ' repository selection(s) were invalid.', 'Repository Delete Warning');
      }
    }

    $('#deleteJobSelect, #deleteRepoSelect').change(updateSelectedCounts);

    $('#deleteEnvironmentFilter').change(function() {
      var value = $(this).val() || 'all';
      deleteEnvironmentFilter = isAllDeleteEnvironmentFilter(value) ? 'all' : normalizeDeleteEnvironment(value);
      loadDeleteOptions();
    });

    $('#deleteStatusFilter').change(function() {
      deleteStatusFilter = $(this).val() || 'all';
      renderDeleteSelectors();
    });

    $(document).on('jobseeker:environment-change', function(event, environment) {
      deleteEnvironmentFilter = isAllDeleteEnvironmentFilter(environment) ? 'all' : normalizeDeleteEnvironment(environment || 'all');
      loadDeleteOptions();
    });

    $('#selectAllJobs').click(function() {
      $('#deleteJobSelect option').prop('selected', true);
      $('#deleteJobSelect').trigger('change');
    });

    $('#clearSelectedJobs').click(function() {
      $('#deleteJobSelect option').prop('selected', false);
      $('#deleteJobSelect').trigger('change');
    });

    $('#selectAllRepos').click(function() {
      $('#deleteRepoSelect option').prop('selected', true);
      $('#deleteRepoSelect').trigger('change');
    });

    $('#clearSelectedRepos').click(function() {
      $('#deleteRepoSelect option').prop('selected', false);
      $('#deleteRepoSelect').trigger('change');
    });

    $('#reloadDeleteJobs, #reloadDeleteRepos').click(loadDeleteOptions);

    $('#deleteJob').click(function(event){
      event.preventDefault();

      if ($(this).hasClass('disabled')) {
        return;
      }

      var jobs = selectedValues('#deleteJobSelect');
      var deleteRepositoriesAfterJobs = $('#deleteRepoCheck').is(':checked');

      if (jobs.length === 0) {
        toastr.error('Please select at least one job to delete.', 'Select Jobs');
        return;
      }

      var repositoryWarning = deleteRepositoriesAfterJobs ? '<p><b>Repository folders and files will also be deleted.</b></p>' : '';
      alertify.confirm('Delete Job Confirmation Required', '<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Delete ' + jobs.length + ' selected Jenkins job(s) permanently?</b></p>' + repositoryWarning + optionListHtml(jobs) + '</div></div></div>',
        function(){
          setDeleteBusy(true);
          deleteJenkinsJobs(jobs).done(function(results) {
            reportJenkinsDeleteResults(results);

            if (! deleteRepositoriesAfterJobs) {
              setDeleteBusy(false);
              return;
            }

            deleteRepositories(jobs).done(function(data) {
              reportRepositoryDeleteResults(data);
            }).fail(function() {
              console.error(arguments);
              toastr.error('Some repositories could not be deleted.', 'Repository Delete Failed');
            }).always(function() {
              setDeleteBusy(false);
            });
          });
        },
        function(){
          alertify.error('Operation aborted.');
        }
      );
    });

    $('#delRepoBtn').click(function(event){
      event.preventDefault();

      if ($(this).hasClass('disabled')) {
        return;
      }

      var jobs = selectedValues('#deleteRepoSelect');

      if (jobs.length === 0) {
        toastr.error('Please select at least one repository to delete.', 'Select Repositories');
        return;
      }

      alertify.confirm('Delete Repository Confirmation Required', '<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Delete repository files for ' + jobs.length + ' selected job(s)?</b></p><p>This does not delete the Jenkins job configuration.</p>' + optionListHtml(jobs) + '</div></div></div>',
        function(){
          setDeleteBusy(true);
          deleteRepositories(jobs).done(function(data) {
            reportRepositoryDeleteResults(data);
          }).fail(function() {
            console.error(arguments);
            toastr.error('Some repositories could not be deleted.', 'Repository Delete Failed');
          }).always(function() {
            setDeleteBusy(false);
          });
        },
        function(){
          alertify.error('Operation aborted.');
        }
      );
    });

    loadDeleteOptions();

});

</script>
