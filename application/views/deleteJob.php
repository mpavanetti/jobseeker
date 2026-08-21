<style type="text/css">
  .checkbox input {
    transform: scale(1.5);
  }

  .delete-actions .btn {
    margin-right: 6px;
    margin-bottom: 6px;
  }

  .delete-job-select {
    min-height: 220px;
  }

  .delete-selection-help {
    display: block;
    margin-top: 6px;
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
  <div class="container">
    <div class="row" style="margin-top: 15px;">
       <div class="col-lg-6 col-md-6 col-xs-12">
         <div class="box box-primary">
            <div class="overlay" style="display:none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
            <div class="box-header">
              <b>Delete Jenkins Jobs</b>
            </div>
            <form role="form" id="delJob">
             <div class="box-body">
               <div class="col-lg-12 col-md-12 col-xs-12">
                     <div class="form-group">
                  <label for="deleteJobSelect">Select jobs to delete</label>
                  <select class="form-control selector delete-job-select" id="deleteJobSelect" multiple>
                        </select>
                  <small class="text-muted delete-selection-help"><span id="deleteJobCount">0</span> job(s) selected. Hold Ctrl/Cmd to select multiple jobs.</small>
                    </div>
                </div>
              <div class="col-lg-12 col-md-12 col-xs-12">
                 <div class="form-group">
                    <label for="job_name">Delete Job Repository</label>
                    <div class="checkbox">
                        <label for="deleteRepository">
                    <input type="checkbox" name="deleteRepoCheck" id="deleteRepoCheck" value="1"> Also delete assigned job repositories and files.
                      </label>
                  </div>
              </div>
          </div>
      </div>
          <div class="box-footer delete-actions">
           <button type="button" id="selectAllJobs" class="btn btn-default"><i class="fa fa-check-square-o"></i> Select All</button>
           <button type="button" id="clearSelectedJobs" class="btn btn-default"><i class="fa fa-square-o"></i> Clear</button>
           <button type="button" id="reloadDeleteJobs" class="btn btn-info"><i class="fa fa-refresh"></i> Reload</button>
           <button type="button" id="deleteJob" class="btn btn-danger"><i class="fa fa-trash"></i> Delete Selected Jobs</button>
     </div>
 </form> 
</div>
</div>
<div class="col-lg-6 col-md-6 col-xs-12">
 <div class="box box-primary">
    <div class="overlay" style="display:none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
    <div class="box-header">
      <b>Delete Job Repositories Only</b>
    </div>
    <form role="form" id="delRepository">
     <div class="box-body">
         <div class="col-lg-12 col-md-12 col-xs-12">
             <div class="form-group">
          <label for="deleteRepoSelect">Select repositories to delete</label>
          <select class="form-control selector delete-job-select" id="deleteRepoSelect" multiple>
                </select>
          <small class="text-muted delete-selection-help"><span id="deleteRepoCount">0</span> repository selection(s). This does not delete Jenkins jobs.</small>
            </div>
        </div>
</div>
  <div class="box-footer delete-actions">
   <button type="button" id="selectAllRepos" class="btn btn-default"><i class="fa fa-check-square-o"></i> Select All</button>
   <button type="button" id="clearSelectedRepos" class="btn btn-default"><i class="fa fa-square-o"></i> Clear</button>
   <button type="button" id="delRepoBtn" class="btn btn-danger"><i class="fa fa-trash"></i> Delete Selected Repositories</button>
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
      $('#deleteJob, #delRepoBtn, #reloadDeleteJobs, #selectAllJobs, #clearSelectedJobs, #selectAllRepos, #clearSelectedRepos')
        .prop('disabled', isBusy)
        .toggleClass('disabled', isBusy);
    }

    function selectedValues(selector) {
      return ($(selector).val() || []).filter(function(value) {
        return value !== null && value !== '' && value !== '0';
      });
    }

    function updateSelectedCounts() {
      $('#deleteJobCount').text(selectedValues('#deleteJobSelect').length);
      $('#deleteRepoCount').text(selectedValues('#deleteRepoSelect').length);
    }

    function optionListHtml(jobNames) {
      var maxVisibleJobs = 10;
      var visibleJobs = jobNames.slice(0, maxVisibleJobs).map(function(jobName) {
        return '<li>' + escapeHtml(jobName) + '</li>';
      }).join('');
      var hiddenCount = jobNames.length - maxVisibleJobs;

      if (hiddenCount > 0) {
        visibleJobs += '<li>and ' + hiddenCount + ' more...</li>';
      }

      return '<ul class="text-left">' + visibleJobs + '</ul>';
    }

    function populateSelectors(jobs) {
      var names = (jobs || []).map(function(job) {
        return job.fullName || job.name || '';
      }).filter(function(name, index, allNames) {
        return name !== '' && allNames.indexOf(name) === index;
      }).sort();

      $('.selector').empty();
      $.each(names, function(index, name) {
        $('.selector').append($('<option>', {
          value: name,
          text: name
        }));
      });

      updateSelectedCounts();
    }

    function removeDeletedOptions(jobNames) {
      $.each(jobNames, function(index, jobName) {
        $('.selector option').filter(function() {
          return this.value === jobName;
        }).remove();
      });

      updateSelectedCounts();
    }

    function loadDeleteOptions() {
      setDeleteBusy(true);
      $.ajax({
        url: jenkins_url + 'api/json?tree=jobs[name,fullName]&pretty=true',
        method: 'GET'
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

    $('#reloadDeleteJobs').click(loadDeleteOptions);

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