<?php
$environmentOptions = array();
$projectOptions = array();
$activeEnvironmentCount = 0;

if(!empty($listEnvironments))
{
  foreach($listEnvironments as $environmentRecord)
  {
    $environmentOptions[] = array(
      'id' => (int) $environmentRecord->Id,
      'name' => isset($environmentRecord->Environment) ? (string) $environmentRecord->Environment : '',
      'active' => isset($environmentRecord->IsActive) ? (int) $environmentRecord->IsActive : 0
    );

    if (isset($environmentRecord->IsActive) && (int) $environmentRecord->IsActive == 1) {
      $activeEnvironmentCount++;
    }
  }
}

if(!empty($listProjects))
{
  foreach($listProjects as $projectRecord)
  {
    $projectOptions[] = array(
      'id' => (int) $projectRecord->Id,
      'name' => isset($projectRecord->ProjectName) ? (string) $projectRecord->ProjectName : '',
      'active' => isset($projectRecord->IsActive) ? (int) $projectRecord->IsActive : 0
    );
  }
}

$promotionJobs = !empty($jenkinsJobs) ? $jenkinsJobs : array();
$rollbackId = $this->session->flashdata('rollback_id');
?>
<script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
</script>
<style>
  .job-promotion-page .content {
    padding: 18px;
  }

  .job-promotion-shell {
    max-width: 1620px;
    width: 100%;
  }

  .promotion-summary-card,
  .promotion-workbench,
  .promotion-preview-panel,
  .promotion-inventory-card {
    background: #fff;
    border: 1px solid #d8e0e8;
    border-radius: 6px;
    box-shadow: 0 8px 20px rgba(16, 42, 67, .08);
  }

  .promotion-summary-card {
    min-height: 92px;
    padding: 16px;
  }

  .promotion-summary-label {
    color: #6b7c8f;
    display: block;
    font-size: 12px;
    letter-spacing: .03em;
    text-transform: uppercase;
  }

  .promotion-summary-value {
    color: #102a43;
    display: block;
    font-size: 28px;
    font-weight: 700;
    margin-top: 4px;
  }

  .promotion-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: minmax(360px, 470px) minmax(0, 1fr);
    margin-top: 18px;
  }

  .promotion-workbench,
  .promotion-preview-panel {
    min-height: 430px;
    padding: 18px;
  }

  .promotion-section-title {
    align-items: center;
    color: #102a43;
    display: flex;
    font-size: 18px;
    font-weight: 700;
    gap: 8px;
    margin: 0 0 16px;
  }

  .promotion-workbench label {
    color: #243b53;
    font-size: 12px;
    letter-spacing: .02em;
    text-transform: uppercase;
  }

  .promotion-flow {
    align-items: center;
    display: grid;
    gap: 10px;
    grid-template-columns: minmax(0, 1fr) 38px minmax(0, 1fr);
  }

  .promotion-flow-icon {
    align-items: center;
    background: #e8f4f8;
    border: 1px solid #b8d8e5;
    border-radius: 50%;
    color: #2f80a4;
    display: flex;
    height: 38px;
    justify-content: center;
    margin-top: 21px;
    width: 38px;
  }

  .promotion-option-row {
    align-items: center;
    display: flex;
    gap: 12px;
    justify-content: space-between;
    margin-top: 12px;
  }

  .promotion-option-panel {
    background: #f8fbfd;
    border: 1px solid #e3ebf2;
    border-radius: 4px;
    margin: 14px 0;
    padding: 12px;
  }

  .promotion-option-panel .checkbox {
    margin: 0 0 10px;
  }

  .promotion-preview-status {
    background: #f7fafc;
    border: 1px dashed #bcccdc;
    border-radius: 4px;
    color: #486581;
    min-height: 110px;
    padding: 14px;
  }

  .promotion-preview-status strong {
    color: #102a43;
  }

  .promotion-preview-kpis {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    margin: 14px 0;
  }

  .promotion-preview-kpi {
    background: #f8fbfd;
    border: 1px solid #e3ebf2;
    border-radius: 4px;
    padding: 10px;
  }

  .promotion-preview-kpi span {
    color: #6b7c8f;
    display: block;
    font-size: 11px;
    letter-spacing: .03em;
    text-transform: uppercase;
  }

  .promotion-preview-kpi b {
    color: #102a43;
    display: block;
    font-size: 20px;
    margin-top: 3px;
  }

  .promotion-preview-list {
    list-style: none;
    margin: 10px 0 0;
    padding: 0;
  }

  .promotion-preview-list li {
    border-top: 1px solid #e6edf3;
    color: #486581;
    font-size: 12px;
    padding: 7px 0;
  }

  .promotion-preview-table {
    margin-top: 12px;
  }

  .promotion-source-environment {
    color: #6b7c8f;
    display: block;
    margin-top: 7px;
  }

  .promotion-source-environment .label,
  .promotion-inventory-environment .label {
    display: inline-block;
    margin-right: 4px;
  }

  .promotion-environment-mismatch {
    border-color: #dd4b39;
    box-shadow: 0 0 0 1px rgba(221, 75, 57, .25);
  }

  .promotion-command-preview {
    background: #0f1b2a;
    border-radius: 4px;
    color: #d9e2ec;
    font-family: Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 12px;
    line-height: 1.5;
    margin-top: 12px;
    max-height: 300px;
    overflow: auto;
    padding: 12px;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .promotion-command-preview .preview-heading {
    color: #9fb3c8;
    display: block;
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    font-size: 11px;
    letter-spacing: .04em;
    margin: 10px 0 4px;
    text-transform: uppercase;
  }

  .promotion-inventory-card {
    margin-top: 18px;
  }

  .promotion-inventory-card .box-header {
    border-bottom: 1px solid #edf1f5;
  }

  #promotionInventoryTable,
  #promotionInventoryTable_wrapper,
  #promotionInventoryTable_wrapper .dataTables_scroll,
  #promotionInventoryTable_wrapper .dataTables_scrollHead,
  #promotionInventoryTable_wrapper .dataTables_scrollBody,
  #promotionInventoryTable_wrapper .dataTables_scrollHeadInner,
  #promotionInventoryTable_wrapper .dataTables_scrollHeadInner table {
    width: 100% !important;
  }

  .job-promotion-page table th {
    color: #243b53;
    font-size: 12px;
    letter-spacing: .02em;
    text-transform: uppercase;
    white-space: nowrap;
  }

  @media (max-width: 1100px) {
    .promotion-grid,
    .promotion-preview-kpis {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 767px) {
    .promotion-flow,
    .promotion-option-row {
      display: block;
    }

    .promotion-flow-icon {
      margin: 4px 0 14px;
    }

    .promotion-option-row .btn {
      margin-top: 10px;
      width: 100%;
    }
  }
</style>

<div class="content-wrapper job-promotion-page">
  <section class="content-header">
    <h1>
      <i class="fa fa-level-up"></i> Environment Promotion
      <small>Promote Jenkins jobs between runtime environments</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Extract, Transform, Load</a></li>
      <li><a href="#">Context Settings</a></li>
      <li><a href="#">Environment Promotion</a></li>
    </ol>
  </section>

  <section class="content">
    <div class="container-fluid job-promotion-shell">
      <div class="row">
        <div class="col-md-12">
          <?php
            $this->load->helper('form');
            $error = $this->session->flashdata('error');
            if($error)
            {
          ?>
          <div class="alert alert-danger alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button>
            <?php echo $this->session->flashdata('error'); ?>
          </div>
          <?php } ?>
          <?php
            $success = $this->session->flashdata('success');
            if($success)
            {
          ?>
          <div class="alert alert-success alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button>
            <?php echo $this->session->flashdata('success'); ?>
          </div>
          <?php } ?>
          <?php if(!empty($rollbackId)) { ?>
          <form action="<?php echo base_url() ?>Context/rollbackJobPromotion" method="POST" class="alert alert-info" style="margin-bottom: 15px;">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>" />
            <input type="hidden" name="rollbackId" value="<?php echo html_escape($rollbackId); ?>" />
            <strong>Rollback ready:</strong> restore Jenkins jobs, artifacts, and copied contexts from checkpoint <?php echo html_escape($rollbackId); ?>.
            <button type="submit" class="btn btn-xs btn-info pull-right" onclick="return confirm('Rollback this environment promotion?');"><i class="fa fa-undo"></i> Rollback Promotion</button>
          </form>
          <?php } ?>
          <?php if(!empty($jenkinsError)) { ?>
          <div class="alert alert-warning alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button>
            <?php echo html_escape($jenkinsError); ?>
          </div>
          <?php } ?>
          <?php echo validation_errors('<div class="alert alert-danger alert-dismissable">', ' <button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button></div>'); ?>
        </div>
      </div>

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
          <div class="promotion-summary-card">
            <span class="promotion-summary-label">Jenkins Jobs</span>
            <span class="promotion-summary-value"><?php echo number_format(count($promotionJobs)); ?></span>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
          <div class="promotion-summary-card">
            <span class="promotion-summary-label">Environments</span>
            <span class="promotion-summary-value"><?php echo number_format(count($environmentOptions)); ?></span>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
          <div class="promotion-summary-card">
            <span class="promotion-summary-label">Active Environments</span>
            <span class="promotion-summary-value"><?php echo number_format($activeEnvironmentCount); ?></span>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
          <div class="promotion-summary-card">
            <span class="promotion-summary-label">Context Projects</span>
            <span class="promotion-summary-value"><?php echo number_format(count($projectOptions)); ?></span>
          </div>
        </div>
      </div>

      <div class="promotion-grid animated fadeIn">
        <div class="promotion-workbench">
          <h3 class="promotion-section-title"><i class="fa fa-code-fork"></i> Promote Job</h3>
          <form action="<?php echo base_url() ?>Context/promoteJob" method="POST" id="jobPromotionForm">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>" />
            <div class="form-group">
              <label>Source Jenkins Job</label>
              <select id="sourceJob" class="form-control" name="sourceJob" required>
                <option value="">Select job</option>
                <?php foreach($promotionJobs as $job) { ?>
                <option value="<?php echo html_escape($job['fullName']); ?>" data-color="<?php echo html_escape($job['color']); ?>" data-buildable="<?php echo $job['buildable'] ? '1' : '0'; ?>"><?php echo html_escape($job['fullName']); ?></option>
                <?php } ?>
              </select>
              <small id="sourceJobEnvironmentHint" class="promotion-source-environment"><i class="fa fa-globe"></i> Environment: <span class="label label-default">Select a job</span></small>
            </div>

            <div class="promotion-flow">
              <div class="form-group">
                <label>Promote From</label>
                <select id="sourceEnvironment" class="form-control" name="sourceEnvironment" required>
                  <option value="">Source environment</option>
                  <?php foreach($environmentOptions as $env) { ?>
                  <option value="<?php echo (int) $env['id']; ?>" data-name="<?php echo html_escape($env['name']); ?>"><?php echo html_escape($env['name']); ?><?php echo $env['active'] == 1 ? '' : ' (inactive)'; ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="promotion-flow-icon"><i class="fa fa-long-arrow-right"></i></div>
              <div class="form-group">
                <label>Promote To</label>
                <select id="targetEnvironment" class="form-control" name="targetEnvironment" required>
                  <option value="">Target environment</option>
                  <?php foreach($environmentOptions as $env) { ?>
                  <option value="<?php echo (int) $env['id']; ?>" data-name="<?php echo html_escape($env['name']); ?>"><?php echo html_escape($env['name']); ?><?php echo $env['active'] == 1 ? '' : ' (inactive)'; ?></option>
                  <?php } ?>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Target Jenkins Job</label>
              <input type="text" id="targetJobName" name="targetJobName" class="form-control" maxlength="255" placeholder="Target job name" autocomplete="off">
            </div>

            <div class="promotion-option-panel">
              <div class="checkbox">
                <label><input type="checkbox" id="includeDependencies" name="includeDependencies" value="1" checked> Include downstream Jenkins dependencies</label>
              </div>
              <div class="checkbox">
                <label><input type="checkbox" id="promoteContexts" name="promoteContexts" value="1"> Promote context keys for a project</label>
              </div>
              <div class="form-group" id="contextProjectGroup" style="display: none;">
                <label>Context Project</label>
                <select id="promotionProject" class="form-control" name="promotionProject">
                  <option value="">Select project</option>
                  <?php foreach($projectOptions as $project) { ?>
                  <option value="<?php echo (int) $project['id']; ?>"><?php echo html_escape($project['name']); ?><?php echo $project['active'] == 1 ? '' : ' (inactive)'; ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="checkbox" id="overwriteContextsGroup" style="display: none;">
                <label><input type="checkbox" id="overwriteContexts" name="overwriteContexts" value="1"> Overwrite existing target context keys</label>
              </div>
              <div class="checkbox" style="margin-bottom: 0;">
                <label><input type="checkbox" id="createRollback" name="createRollback" value="1" checked> Create rollback checkpoint before saving</label>
              </div>
            </div>

            <div class="promotion-option-row">
              <div class="checkbox" style="margin: 0;">
                <label><input type="checkbox" id="overwriteExisting" name="overwriteExisting" value="1"> Overwrite existing target job and artifacts</label>
              </div>
              <button type="submit" class="btn btn-primary" disabled><i class="fa fa-level-up"></i> Promote Job</button>
            </div>
          </form>
        </div>

        <div class="promotion-preview-panel">
          <h3 class="promotion-section-title"><i class="fa fa-search"></i> Promotion Preview</h3>
          <div class="promotion-preview-status" id="jobPromotionPreview">Select a source job and environments to inspect the Jenkins config changes.</div>
          <div class="promotion-preview-kpis" id="jobPromotionKpis" style="display: none;"></div>
          <div id="jobPromotionDetails"></div>
        </div>
      </div>

      <div class="promotion-inventory-card box box-primary">
        <div class="box-header">
          <h3 class="box-title"><b>Promotable Jenkins Jobs</b></h3>
        </div>
        <div class="box-body table-responsive">
          <table id="promotionInventoryTable" class="table table-bordered table-striped" style="width: 100%;">
            <thead>
              <tr>
                <th>Job</th>
                <th>Environment</th>
                <th>Status</th>
                <th>Buildable</th>
                <th>Last Build</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($promotionJobs as $job) { ?>
              <?php
                $color = isset($job['color']) ? $job['color'] : '';
                $statusClass = strpos($color, 'blue') === 0 || strpos($color, 'green') === 0 ? 'success' : (strpos($color, 'red') === 0 ? 'danger' : 'default');
                $lastBuild = 'Never';
                if (!empty($job['lastBuild']) && isset($job['lastBuild']->number)) {
                  $lastBuild = '#'.(int) $job['lastBuild']->number;
                  if (isset($job['lastBuild']->result) && $job['lastBuild']->result !== NULL) {
                    $lastBuild .= ' '.(string) $job['lastBuild']->result;
                  }
                  if (isset($job['lastBuild']->timestamp) && (int) $job['lastBuild']->timestamp > 0) {
                    $lastBuild .= ' at '.date('Y-m-d H:i', floor(((int) $job['lastBuild']->timestamp) / 1000));
                  }
                }
              ?>
              <tr data-promotion-job="<?php echo html_escape($job['fullName']); ?>">
                <td><b><?php echo html_escape($job['fullName']); ?></b></td>
                <td><span class="promotion-inventory-environment"><span class="label label-default">Detecting</span></span></td>
                <td><span class="label label-<?php echo $statusClass; ?>"><?php echo html_escape($color !== '' ? $color : 'unknown'); ?></span></td>
                <td><?php echo $job['buildable'] ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>'; ?></td>
                <td><?php echo html_escape($lastBuild); ?></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<script type="text/javascript">
  $(document).ready(function() {
    var previewTimer = null;
    var targetWasEdited = false;
    var pendingPreview = null;
    var $form = $('#jobPromotionForm');
    var $submitButton = $form.find('button[type="submit"]');
    var $preview = $('#jobPromotionPreview');
    var $kpis = $('#jobPromotionKpis');
    var $details = $('#jobPromotionDetails');
    var detectedSourceEnvironment = {environment: 'Unknown', source: 'Not detected', unknown: true};
    var sourceEnvironmentRequest = null;
    var sourceJobOptions = [];
    var sourceJobEnvironmentRequests = {};
    var sourceJobEnvironmentInfo = {};
    var sourceEnvironmentOptionList = [];
    var environmentHelper = window.JobSeekerEnvironment || {
      detectFromConfig: function(xmlText, jobName) { return this.detectFromJob({name: jobName}); },
      detectFromJob: function() { return {environment: 'Unknown', source: 'Not detected', unknown: true}; },
      normalize: function(value) { return $.trim(String(value || '')).toUpperCase(); },
      label: function() { return '<span class="label label-default">Unknown</span>'; },
      text: function(info) { return info && info.environment ? info.environment : 'Unknown'; }
    };
    var environmentOptions = {};

    if ($.fn.select2) {
      $('#sourceJob, #sourceEnvironment, #targetEnvironment, #promotionProject').select2({ width: '100%' });
    }

    $('#sourceJob option[value!=""]').each(function() {
      sourceJobOptions.push({
        value: $(this).val(),
        text: $(this).text(),
        color: $(this).data('color'),
        buildable: $(this).data('buildable')
      });
    });

    $('#sourceEnvironment option[data-name]').each(function() {
      var name = environmentHelper.normalize($(this).data('name'));
      if (name) {
        sourceEnvironmentOptionList.push({
          value: $(this).val(),
          name: $(this).data('name'),
          text: $(this).text()
        });
      }
    });

    $('#sourceEnvironment option[data-name], #targetEnvironment option[data-name]').each(function() {
      var name = environmentHelper.normalize($(this).data('name'));
      if (name) {
        environmentOptions[name] = $(this).val();
      }
    });

    function htmlEscape(value) {
      return $('<div>').text(value == null ? '' : value).html();
    }

    function selectedEnvironmentName(selector) {
      var $option = $(selector).find('option:selected');
      return $option.data('name') || $.trim($option.text().replace(/\s+\(inactive\)$/i, ''));
    }

    function jenkinsBaseUrl() {
      var url = window.jobseekerJenkinsUrl || <?php echo json_encode($jenkins_url); ?> || '';
      return url && url.charAt(url.length - 1) !== '/' ? url + '/' : url;
    }

    function jenkinsJobPath(jobName) {
      return String(jobName == null ? '' : jobName).split('/').map(function(segment) {
        return 'job/' + encodeURIComponent(segment);
      }).join('/');
    }

    function selectedEnvironmentNormalized(selector) {
      return environmentHelper.normalize(selectedEnvironmentName(selector));
    }

    function currentGlobalEnvironment() {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.selected) {
        return window.JobSeekerGlobalEnvironment.selected();
      }

      return environmentHelper.normalize($('#globalEnvironmentSelector').val() || 'all');
    }

    function isConfiguredEnvironment(environment) {
      if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.isConfiguredEnvironment) {
        return window.JobSeekerGlobalEnvironment.isConfiguredEnvironment(environment);
      }

      return !! environmentOptions[environmentHelper.normalize(environment)];
    }

    function activeSourceEnvironmentFilter() {
      var globalEnvironment = currentGlobalEnvironment();
      if (isConfiguredEnvironment(globalEnvironment)) {
        return environmentHelper.normalize(globalEnvironment);
      }

      return selectedEnvironmentNormalized('#sourceEnvironment');
    }

    function sourceJobEnvironment(jobName) {
      return sourceJobEnvironmentInfo[jobName] || environmentHelper.detectFromJob({name: jobName, fullName: jobName});
    }

    function sourceJobMatchesEnvironment(jobName) {
      var environment = environmentHelper.normalize(environmentHelper.text(sourceJobEnvironment(jobName)));
      var sourceFilter = activeSourceEnvironmentFilter();

      if (! isConfiguredEnvironment(environment)) {
        return false;
      }

      return ! sourceFilter || environment === sourceFilter;
    }

    function renderSourceJobOptions() {
      var currentValue = $('#sourceJob').val() || '';
      var keepCurrent = currentValue !== '' && sourceJobMatchesEnvironment(currentValue);

      $('#sourceJob').empty().append($('<option>', {value: '', text: 'Select job'}));
      $.each(sourceJobOptions, function(index, job) {
        if (! sourceJobMatchesEnvironment(job.value)) {
          return;
        }

        $('#sourceJob').append($('<option>', {
          value: job.value,
          text: job.text
        }).attr('data-color', job.color).attr('data-buildable', job.buildable));
      });

      $('#sourceJob').val(keepCurrent ? currentValue : '').trigger('change.select2');
      if (! keepCurrent) {
        setDetectedSourceEnvironment({environment: 'Unknown', source: 'Not detected', unknown: true}, false);
      }
    }

    function applyGlobalSourceEnvironment() {
      var currentValue = $('#sourceEnvironment').val() || '';
      var globalEnvironment = currentGlobalEnvironment();
      var sourceFilter = isConfiguredEnvironment(globalEnvironment) ? environmentHelper.normalize(globalEnvironment) : '';
      var currentStillAvailable = false;

      $('#sourceEnvironment').empty().append($('<option>', {value: '', text: 'Source environment'}));
      $.each(sourceEnvironmentOptionList, function(index, environment) {
        var normalizedName = environmentHelper.normalize(environment.name);
        if (sourceFilter && normalizedName !== sourceFilter) {
          return;
        }

        if (environment.value == currentValue) {
          currentStillAvailable = true;
        }

        $('#sourceEnvironment').append($('<option>', {
          value: environment.value,
          text: environment.text
        }).attr('data-name', environment.name));
      });

      if (sourceFilter && environmentOptions[sourceFilter]) {
        $('#sourceEnvironment').val(environmentOptions[sourceFilter]);
      } else if (currentStillAvailable) {
        $('#sourceEnvironment').val(currentValue);
      } else {
        $('#sourceEnvironment').val('');
      }

      $('#sourceEnvironment').trigger('change.select2');
    }

    function hydrateSourceJobEnvironment(jobName) {
      if (! jobName || sourceJobEnvironmentInfo[jobName] || sourceJobEnvironmentRequests[jobName]) {
        return;
      }

      sourceJobEnvironmentInfo[jobName] = environmentHelper.detectFromJob({name: jobName, fullName: jobName});
      sourceJobEnvironmentRequests[jobName] = $.ajax({
        url: jenkinsBaseUrl() + jenkinsJobPath(jobName) + '/config.xml',
        method: 'GET',
        dataType: 'text'
      }).done(function(xmlText) {
        sourceJobEnvironmentInfo[jobName] = environmentHelper.detectFromConfig(xmlText || '', jobName);
      }).fail(function() {
        sourceJobEnvironmentInfo[jobName] = environmentHelper.detectFromJob({name: jobName, fullName: jobName});
      }).always(function() {
        delete sourceJobEnvironmentRequests[jobName];
        renderSourceJobOptions();
        schedulePreview();
      });
    }

    function hydrateSourceJobEnvironments() {
      $.each(sourceJobOptions, function(index, job) {
        hydrateSourceJobEnvironment(job.value);
      });
    }

    function renderEnvironmentInfo(info) {
      info = info || environmentHelper.detectFromJob({});
      return environmentHelper.label(info) + ' <small>' + htmlEscape(info.source || 'Not detected') + '</small>';
    }

    function adjustPromotionInventoryTable() {
      if ($.fn.dataTable && $.fn.dataTable.isDataTable('#promotionInventoryTable')) {
        $('#promotionInventoryTable').DataTable().columns.adjust();
      }
    }

    function setDetectedSourceEnvironment(info, shouldSelectSource) {
      detectedSourceEnvironment = info || environmentHelper.detectFromJob({});
      $('#sourceJobEnvironmentHint').html('<i class="fa fa-globe"></i> Environment: ' + renderEnvironmentInfo(detectedSourceEnvironment));
      $('#sourceEnvironment').removeClass('promotion-environment-mismatch');

      if (!detectedSourceEnvironment.unknown && shouldSelectSource && ! isConfiguredEnvironment(currentGlobalEnvironment())) {
        var detectedName = environmentHelper.normalize(detectedSourceEnvironment.environment);
        var optionValue = environmentOptions[detectedName];

        if (optionValue) {
          $('#sourceEnvironment').val(optionValue).trigger('change.select2');
        }
      }
    }

    function detectSourceJobEnvironment() {
      var sourceJob = $('#sourceJob').val();

      if (sourceEnvironmentRequest && sourceEnvironmentRequest.readyState !== 4) {
        sourceEnvironmentRequest.abort();
      }

      if (!sourceJob) {
        setDetectedSourceEnvironment({environment: 'Unknown', source: 'Not detected', unknown: true}, false);
        schedulePreview();
        return;
      }

      setDetectedSourceEnvironment(environmentHelper.detectFromJob({name: sourceJob, fullName: sourceJob}), true);
      $('#sourceJobEnvironmentHint').append(' <span class="text-muted">Checking Jenkins config...</span>');

      sourceEnvironmentRequest = $.ajax({
        url: jenkinsBaseUrl() + jenkinsJobPath(sourceJob) + '/config.xml',
        method: 'GET',
        dataType: 'text'
      }).done(function(xmlText) {
        setDetectedSourceEnvironment(environmentHelper.detectFromConfig(xmlText || '', sourceJob), true);
      }).fail(function(xhr, status) {
        if (status !== 'abort') {
          setDetectedSourceEnvironment(environmentHelper.detectFromJob({name: sourceJob, fullName: sourceJob}), true);
        }
      }).always(function() {
        schedulePreview();
      });
    }

    function setInventoryEnvironment($row, info) {
      $row.find('.promotion-inventory-environment').html(renderEnvironmentInfo(info));
      setTimeout(adjustPromotionInventoryTable, 0);
    }

    function hydrateInventoryEnvironments() {
      $('tr[data-promotion-job]').each(function() {
        var $row = $(this);
        var jobName = $row.data('promotion-job') || '';

        if (!jobName) {
          return;
        }

        setInventoryEnvironment($row, environmentHelper.detectFromJob({name: jobName, fullName: jobName}));

        $.ajax({
          url: jenkinsBaseUrl() + jenkinsJobPath(jobName) + '/config.xml',
          method: 'GET',
          dataType: 'text'
        }).done(function(xmlText) {
          setInventoryEnvironment($row, environmentHelper.detectFromConfig(xmlText || '', jobName));
        });
      });
    }

    function sourceEnvironmentMismatchMessage() {
      if (!detectedSourceEnvironment || detectedSourceEnvironment.unknown) {
        return '';
      }

      var selectedSource = selectedEnvironmentNormalized('#sourceEnvironment');
      var detectedSource = environmentHelper.normalize(detectedSourceEnvironment.environment);

      if (!selectedSource || !detectedSource || selectedSource === detectedSource) {
        $('#sourceEnvironment').removeClass('promotion-environment-mismatch');
        return '';
      }

      $('#sourceEnvironment').addClass('promotion-environment-mismatch');
      return 'Selected source environment is ' + selectedEnvironmentName('#sourceEnvironment') + ', but the Jenkins job appears to run in ' + detectedSourceEnvironment.environment + ' from ' + detectedSourceEnvironment.source + '.';
    }

    function escapeRegExp(value) {
      return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function jobNameToken(value) {
      var token = $.trim(String(value || '')).replace(/[^A-Za-z0-9._-]+/g, '-').replace(/^[._-]+|[._-]+$/g, '');
      return token || 'promoted';
    }

    function suggestTargetJobName() {
      var sourceJob = $('#sourceJob').val();
      var sourceEnvironment = selectedEnvironmentName('#sourceEnvironment');
      var targetEnvironment = selectedEnvironmentName('#targetEnvironment');

      if (!sourceJob || !targetEnvironment) {
        return '';
      }

      if (sourceEnvironment) {
        var pattern = new RegExp('(^|[._\\/-])' + escapeRegExp(sourceEnvironment) + '($|[._\\/-])', 'i');
        if (pattern.test(sourceJob)) {
          return sourceJob.replace(pattern, function(match, before, after) {
            return before + targetEnvironment + after;
          });
        }
      }

      return sourceJob + '-' + jobNameToken(targetEnvironment);
    }

    function syncTargetSuggestion() {
      if (!targetWasEdited) {
        $('#targetJobName').val(suggestTargetJobName());
      }
    }

    function setPreviewIdle(message) {
      $submitButton.prop('disabled', true);
      $preview.html(message);
      $kpis.hide().empty();
      $details.empty();
    }

    function kpi(label, value) {
      return '<div class="promotion-preview-kpi"><span>' + htmlEscape(label) + '</span><b>' + htmlEscape(value) + '</b></div>';
    }

    function renderPreview(response) {
      var targetState = response.target_exists ? 'Target job exists' : 'Target job will be created';
      var artifactCount = response.artifacts && response.artifacts.planned ? response.artifacts.planned.length : 0;
      var artifactItems = [];
      var jobItems = [];
      var context = response.context_promotion || {};
      var commandPreview = [];
      var jobTable = '';

      $preview.html('<span class="label label-success">Ready</span> <strong>' + htmlEscape(response.target_job) + '</strong> passed promotion checks. ' + htmlEscape(targetState) + '. Rollback is ' + (response.rollback_enabled ? 'enabled' : 'disabled') + '.');

      $kpis.html(
        kpi('Jobs', response.job_count || 0) +
        kpi('Dependencies', response.dependency_count || 0) +
        kpi('Context Keys', context.enabled ? (context.total || 0) : 0) +
        kpi('Config Updates', (response.command_updates || 0) + (response.parameter_updates || 0) + (response.downstream_updates || 0) + (response.artifact_path_updates || 0)) +
        kpi('Artifact Folders', artifactCount)
      ).show();

      if (response.jobs && response.jobs.length) {
        jobTable = '<div class="table-responsive promotion-preview-table"><table class="table table-condensed table-bordered"><thead><tr><th>Source Job</th><th>Target Job</th><th>Action</th><th>Detected</th><th>Promotion</th><th>Config Changes</th><th>Artifacts</th></tr></thead><tbody>';
        $.each(response.jobs, function(index, job) {
          var detected = job.detected_environment || {environment: '', source: 'Not detected'};
          jobItems.push('<li><span class="label label-' + (job.target_exists ? 'warning' : 'success') + '">' + (job.target_exists ? 'update' : 'create') + '</span> ' + htmlEscape(job.source_job) + ' to ' + htmlEscape(job.target_job) + '</li>');
          jobTable += '<tr>' +
            '<td>' + htmlEscape(job.source_job) + '</td>' +
            '<td>' + htmlEscape(job.target_job) + '</td>' +
            '<td><span class="label label-' + (job.target_exists ? 'warning' : 'success') + '">' + (job.target_exists ? 'Update' : 'Create') + '</span></td>' +
            '<td>' + renderEnvironmentInfo(detected.environment ? detected : {environment: 'Unknown', source: detected.source || 'Not detected', unknown: true}) + '</td>' +
            '<td>' + htmlEscape(response.source_environment || '') + ' to ' + htmlEscape(response.target_environment || '') + '</td>' +
            '<td>' + htmlEscape((job.command_updates || 0) + (job.parameter_updates || 0) + (job.downstream_updates || 0) + (job.artifact_path_updates || 0)) + '</td>' +
            '<td>' + htmlEscape(job.artifact_count || 0) + '</td>' +
          '</tr>';
        });
        jobTable += '</tbody></table></div>';
      }

      if (context.enabled) {
        jobItems.push('<li><span class="label label-primary">contexts</span> ' + htmlEscape(context.project_name || 'Selected project') + ': ' + htmlEscape(context.created || 0) + ' create, ' + htmlEscape(context.updated || 0) + ' update, ' + htmlEscape(context.skipped || 0) + ' skip</li>');
      }

      if (artifactCount > 0) {
        $.each(response.artifacts.planned, function(index, item) {
          artifactItems.push('<li><span class="label label-info">copy</span> ' + htmlEscape(item.label) + ' to ' + htmlEscape(item.target) + '</li>');
        });
      } else {
        artifactItems.push('<li><span class="label label-default">skip</span> No uploaded or inline artifact folder was found for this source job.</li>');
      }

      if (response.command_previews && response.command_previews.length) {
        $.each(response.command_previews, function(index, preview) {
          var title = (preview.job ? preview.job + ' / ' : '') + preview.builder;
          commandPreview.push('<span class="preview-heading">' + htmlEscape(title) + ' before</span>' + htmlEscape(preview.before));
          commandPreview.push('<span class="preview-heading">' + htmlEscape(title) + ' after</span>' + htmlEscape(preview.after));
        });
      }

      $details.html(
        jobTable +
        '<ul class="promotion-preview-list">' + jobItems.concat(artifactItems).join('') + '</ul>' +
        (commandPreview.length ? '<pre class="promotion-command-preview">' + commandPreview.join('\n\n') + '</pre>' : '')
      );

      $submitButton.prop('disabled', false);
    }

    function requestPreview() {
      var sourceJob = $('#sourceJob').val();
      var sourceEnvironment = $('#sourceEnvironment').val();
      var targetEnvironment = $('#targetEnvironment').val();
      var targetJobName = $.trim($('#targetJobName').val());
      var promoteContexts = $('#promoteContexts').is(':checked');
      var promotionProject = $('#promotionProject').val();

      if (!sourceJob || !sourceEnvironment || !targetEnvironment) {
        setPreviewIdle('Select a source job and environments to inspect the Jenkins config changes.');
        return;
      }

      var mismatch = sourceEnvironmentMismatchMessage();
      if (mismatch) {
        setPreviewIdle('<span class="label label-danger">Blocked</span> ' + htmlEscape(mismatch));
        return;
      }

      if (sourceEnvironment === targetEnvironment) {
        setPreviewIdle('<span class="label label-danger">Blocked</span> Source and target environments must be different.');
        return;
      }

      if (!targetJobName) {
        setPreviewIdle('<span class="label label-warning">Waiting</span> Target Jenkins job name is required.');
        return;
      }

      if (sourceJob === targetJobName) {
        setPreviewIdle('<span class="label label-danger">Blocked</span> Target job must be separate from the source job.');
        return;
      }

      if (promoteContexts && !promotionProject) {
        setPreviewIdle('<span class="label label-warning">Waiting</span> Select a context project or turn context promotion off.');
        return;
      }

      if (pendingPreview && pendingPreview.readyState !== 4) {
        pendingPreview.abort();
      }

      $submitButton.prop('disabled', true);
      $preview.html('<span class="label label-info">Checking</span> Reading Jenkins config.xml and detecting environment bindings...');
      $kpis.hide().empty();
      $details.empty();

      pendingPreview = $.ajax({
        url: '<?php echo base_url(); ?>Context/previewJobPromotion',
        method: 'POST',
        dataType: 'json',
        data: {
          sourceJob: sourceJob,
          sourceEnvironment: sourceEnvironment,
          targetEnvironment: targetEnvironment,
          targetJobName: targetJobName,
          overwriteExisting: $('#overwriteExisting').is(':checked') ? '1' : '0',
          includeDependencies: $('#includeDependencies').is(':checked') ? '1' : '0',
          promoteContexts: promoteContexts ? '1' : '0',
          promotionProject: promotionProject,
          overwriteContexts: $('#overwriteContexts').is(':checked') ? '1' : '0',
          createRollback: $('#createRollback').is(':checked') ? '1' : '0'
        }
      }).done(function(response) {
        if (response && response.ok) {
          renderPreview(response);
        } else {
          setPreviewIdle('<span class="label label-danger">Blocked</span> ' + htmlEscape(response && response.message ? response.message : 'Promotion preview failed.'));
        }
      }).fail(function(xhr, status) {
        if (status === 'abort') {
          return;
        }
        setPreviewIdle('<span class="label label-danger">Error</span> Promotion preview request failed.');
      });
    }

    function schedulePreview() {
      syncTargetSuggestion();
      clearTimeout(previewTimer);
      previewTimer = setTimeout(requestPreview, 250);
    }

    $('#targetJobName').on('input', function() {
      targetWasEdited = true;
      schedulePreview();
    });

    function syncContextControls() {
      var enabled = $('#promoteContexts').is(':checked');
      $('#contextProjectGroup, #overwriteContextsGroup').toggle(enabled);
      $('#promotionProject').prop('required', enabled);
    }

    $('#sourceJob').on('change', function() {
      targetWasEdited = false;
      syncContextControls();
      detectSourceJobEnvironment();
    });

    $('#sourceEnvironment, #targetEnvironment, #overwriteExisting, #includeDependencies, #promoteContexts, #promotionProject, #overwriteContexts, #createRollback').on('change', function() {
      syncContextControls();
      if (this.id === 'sourceEnvironment') {
        renderSourceJobOptions();
      }
      schedulePreview();
    });

    $(document).on('jobseeker:environment-change', function() {
      applyGlobalSourceEnvironment();
      renderSourceJobOptions();
      detectSourceJobEnvironment();
      schedulePreview();
    });

    $form.on('submit', function(event) {
      if ($submitButton.prop('disabled')) {
        event.preventDefault();
        return false;
      }

      var mismatch = sourceEnvironmentMismatchMessage();
      if (mismatch) {
        event.preventDefault();
        setPreviewIdle('<span class="label label-danger">Blocked</span> ' + htmlEscape(mismatch));
        return false;
      }

      return confirm('Promote this environment package to the target environment?');
    });

    applyGlobalSourceEnvironment();
    renderSourceJobOptions();
    hydrateSourceJobEnvironments();
    syncContextControls();
    hydrateInventoryEnvironments();
    if ($.fn.dataTable && ! $.fn.dataTable.isDataTable('#promotionInventoryTable')) {
      var promotionInventoryTable = $('#promotionInventoryTable').DataTable({
        scrollX: true,
        autoWidth: false,
        order: [[1, 'desc']],
        lengthMenu: [10, 20, 50, 100, 200, 500],
        columnDefs: [
          { width: 220, targets: 0 }
        ],
        initComplete: function() {
          this.api().columns.adjust();
        }
      });
      setTimeout(function() {
        promotionInventoryTable.columns.adjust();
      }, 0);
    }
    schedulePreview();
  });
</script>
