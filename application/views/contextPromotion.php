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
$promotionHistory = !empty($promotionHistory) && is_array($promotionHistory) ? $promotionHistory : array();
$rollbackId = $this->session->flashdata('rollback_id');
$pipelineWorkloadCount = 0;
$jobWorkloadCount = 0;
foreach($promotionJobs as $workload) {
  if (isset($workload['workloadType']) && $workload['workloadType'] === 'pipeline') {
    $pipelineWorkloadCount++;
  } else {
    $jobWorkloadCount++;
  }
}
?>
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

  .promotion-tabs {
    margin-top: 15px;
  }

  .promotion-history-panel {
    padding-top: 18px;
  }

  .promotion-history-detail {
    color: #52606d;
    font-size: 12px;
    line-height: 1.55;
    min-width: 260px;
  }

  .promotion-history-detail details {
    margin-top: 6px;
  }

  .promotion-history-detail summary {
    color: #3c8dbc;
    cursor: pointer;
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
      <i class="fa fa-level-up"></i> Environment Deployment
      <small>Deploy jobs and pipelines between runtime environments</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Extract, Transform, Load</a></li>
      <li><a href="#">Context Settings</a></li>
      <li><a href="#">Environment Deployment</a></li>
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
            <button type="submit" class="btn btn-xs btn-info pull-right" onclick="return confirm('Rollback this environment deployment?');"><i class="fa fa-undo"></i> Rollback Deployment</button>
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

      <ul class="nav nav-tabs promotion-tabs" role="tablist">
        <li class="active"><a href="#promotionWorkspace" data-toggle="tab"><i class="fa fa-level-up"></i> Deploy</a></li>
        <li><a href="#promotionHistory" data-toggle="tab"><i class="fa fa-history"></i> History <span class="badge"><?php echo count($promotionHistory); ?></span></a></li>
      </ul>
      <div class="tab-content">
      <div class="tab-pane active" id="promotionWorkspace">
      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
          <div class="promotion-summary-card">
            <span class="promotion-summary-label">Deployable Workloads</span>
            <span class="promotion-summary-value"><?php echo number_format(count($promotionJobs)); ?></span>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
          <div class="promotion-summary-card">
            <span class="promotion-summary-label">Managed Pipelines</span>
            <span class="promotion-summary-value"><?php echo number_format($pipelineWorkloadCount); ?></span>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
          <div class="promotion-summary-card">
            <span class="promotion-summary-label">Jenkins Jobs</span>
            <span class="promotion-summary-value"><?php echo number_format($jobWorkloadCount); ?></span>
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
          <h3 class="promotion-section-title"><i class="fa fa-code-fork"></i> Deploy Workload</h3>
          <form action="<?php echo base_url() ?>Context/promoteJob" method="POST" id="jobPromotionForm">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>" />
            <div class="form-group">
              <label>Source Workload</label>
              <select id="sourceJob" class="form-control" name="sourceJob" required>
                <option value="">Select job or pipeline</option>
                <?php foreach($promotionJobs as $job) { ?>
                <option value="<?php echo html_escape($job['fullName']); ?>" data-color="<?php echo html_escape($job['color']); ?>" data-buildable="<?php echo $job['buildable'] ? '1' : '0'; ?>" data-workload-type="<?php echo html_escape($job['workloadType']); ?>" data-environment="<?php echo html_escape(isset($job['environment']) ? $job['environment'] : ''); ?>" data-pipeline-id="<?php echo (int) $job['pipelineId']; ?>" data-pipeline-key="<?php echo html_escape($job['pipelineKey']); ?>" data-pipeline-version="<?php echo (int) $job['pipelineVersion']; ?>" data-pipeline-environment="<?php echo html_escape($job['pipelineEnvironment']); ?>" data-pipeline-node-count="<?php echo (int) $job['pipelineNodeCount']; ?>" data-pipeline-schedule="<?php echo html_escape($job['pipelineSchedule']); ?>"><?php echo $job['workloadType'] === 'pipeline' ? '[Pipeline] '.html_escape($job['displayName']).' (v'.(int) $job['pipelineVersion'].')' : '[Job] '.html_escape($job['displayName']); ?></option>
                <?php } ?>
              </select>
              <small id="sourceJobEnvironmentHint" class="promotion-source-environment"><i class="fa fa-globe"></i> Environment: <span class="label label-default">Select a job</span></small>
            </div>

            <div class="promotion-flow">
              <div class="form-group">
                <label>Deploy From</label>
                <select id="sourceEnvironment" class="form-control" name="sourceEnvironment" required>
                  <option value="">Source environment</option>
                  <?php foreach($environmentOptions as $env) { ?>
                  <option value="<?php echo (int) $env['id']; ?>" data-name="<?php echo html_escape($env['name']); ?>"><?php echo html_escape($env['name']); ?><?php echo $env['active'] == 1 ? '' : ' (inactive)'; ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="promotion-flow-icon"><i class="fa fa-long-arrow-right"></i></div>
              <div class="form-group">
                <label>Deploy To</label>
                <select id="targetEnvironment" class="form-control" name="targetEnvironment" required>
                  <option value="">Target environment</option>
                  <?php foreach($environmentOptions as $env) { ?>
                  <option value="<?php echo (int) $env['id']; ?>" data-name="<?php echo html_escape($env['name']); ?>"><?php echo html_escape($env['name']); ?><?php echo $env['active'] == 1 ? '' : ' (inactive)'; ?></option>
                  <?php } ?>
                </select>
              </div>
            </div>

            <div class="form-group" id="targetJobGroup">
              <label>Target Jenkins Job</label>
              <input type="text" id="targetJobName" name="targetJobName" class="form-control" maxlength="255" placeholder="Target job name" autocomplete="off">
            </div>

            <div class="promotion-option-panel" id="jobDeploymentOptions">
              <div class="checkbox">
                <label><input type="checkbox" id="includeDependencies" name="includeDependencies" value="1" checked> Include downstream Jenkins dependencies</label>
              </div>
              <div class="checkbox">
                <label><input type="checkbox" id="promoteContexts" name="promoteContexts" value="1"> Deploy context keys for a project</label>
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
                <label><input type="checkbox" id="overwriteExisting" name="overwriteExisting" value="1"> <span id="overwriteExistingLabel">Overwrite existing target job and artifacts</span></label>
              </div>
              <button type="submit" class="btn btn-primary" disabled><i class="fa fa-level-up"></i> Deploy Workload</button>
            </div>
          </form>
        </div>

        <div class="promotion-preview-panel">
          <h3 class="promotion-section-title"><i class="fa fa-search"></i> Deployment Preview</h3>
          <div class="promotion-preview-status" id="jobPromotionPreview">Select a source workload and environments to inspect the deployment.</div>
          <div class="promotion-preview-kpis" id="jobPromotionKpis" style="display: none;"></div>
          <div id="jobPromotionDetails"></div>
        </div>
      </div>

      <div class="promotion-inventory-card box box-primary">
        <div class="box-header">
          <h3 class="box-title"><b>Deployable Workloads</b></h3>
        </div>
        <div class="box-body table-responsive">
          <table id="promotionInventoryTable" class="table table-bordered table-striped" style="width: 100%;">
            <thead>
              <tr>
                <th>Workload</th>
                <th>Type</th>
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
                $lastBuildAtSeconds = 0;
                if (!empty($job['lastBuild']) && isset($job['lastBuild']->number)) {
                  $lastBuild = '#'.(int) $job['lastBuild']->number;
                  if (isset($job['lastBuild']->result) && $job['lastBuild']->result !== NULL) {
                    $lastBuild .= ' '.(string) $job['lastBuild']->result;
                  }
                  if (isset($job['lastBuild']->timestamp) && (int) $job['lastBuild']->timestamp > 0) {
                    $lastBuildAtSeconds = (int) floor(((int) $job['lastBuild']->timestamp) / 1000);
                  }
                }
              ?>
              <tr data-promotion-job="<?php echo html_escape($job['fullName']); ?>">
                <td><b><?php echo html_escape($job['displayName']); ?></b><?php if($job['workloadType'] === 'pipeline') { ?><small class="text-muted" style="display:block;"><?php echo html_escape($job['fullName']); ?></small><?php } ?></td>
                <td><span class="label label-<?php echo $job['workloadType'] === 'pipeline' ? 'info' : 'default'; ?>"><?php echo $job['workloadType'] === 'pipeline' ? 'Pipeline' : 'Job'; ?></span></td>
                <td><span class="promotion-inventory-environment"><span class="label label-default">Detecting</span></span></td>
                <td><span class="label label-<?php echo $statusClass; ?>"><?php echo html_escape($color !== '' ? $color : 'unknown'); ?></span></td>
                <td><?php echo $job['buildable'] ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>'; ?></td>
                <td><?php echo html_escape($lastBuild); ?><?php if ($lastBuildAtSeconds > 0) { echo ' at '.js_time($lastBuildAtSeconds, array('format' => 'Y-m-d H:i')); } ?></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
      </div>

      <div class="tab-pane promotion-history-panel" id="promotionHistory">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title"><b>Deployment History</b></h3>
          </div>
          <div class="box-body table-responsive">
            <table id="promotionHistoryTable" class="table table-bordered table-striped" style="width: 100%;">
              <thead><tr><th>Created</th><th>User</th><th>Route</th><th>Jobs</th><th>Parameters and Artifacts</th><th>Status</th><th>Rollback</th></tr></thead>
              <tbody>
              <?php foreach($promotionHistory as $history) { ?>
                <?php
                  $historyStatus = isset($history['status']) ? $history['status'] : 'unknown';
                  $historyStatusClass = $historyStatus === 'completed' ? 'success' : ($historyStatus === 'rolled_back' ? 'info' : ($historyStatus === 'failed' || $historyStatus === 'rollback_failed' ? 'danger' : 'warning'));
                  $historyJobs = isset($history['jobs']) && is_array($history['jobs']) ? $history['jobs'] : array();
                  $historyParameters = isset($history['parameters']) && is_array($history['parameters']) ? $history['parameters'] : array();
                  $historyArtifacts = isset($history['artifacts']) && is_array($history['artifacts']) ? $history['artifacts'] : array();
                  $artifactCount = 0;
                  foreach(array('copied', 'planned') as $artifactGroup) {
                    if (!empty($historyArtifacts[$artifactGroup]) && is_array($historyArtifacts[$artifactGroup])) {
                      $artifactCount = max($artifactCount, count($historyArtifacts[$artifactGroup]));
                    }
                  }
                ?>
                <tr>
                  <td data-order="<?php echo html_escape(isset($history['created_at']) ? $history['created_at'] : ''); ?>"><?php echo js_time(isset($history['created_at']) ? $history['created_at'] : null, array('empty' => 'Unknown')); ?></td>
                  <td><?php echo html_escape(isset($history['created_by']) && $history['created_by'] !== '' ? $history['created_by'] : 'Unknown'); ?></td>
                  <td><span class="label label-default"><?php echo html_escape(isset($history['source_environment']) ? $history['source_environment'] : ''); ?></span> <i class="fa fa-long-arrow-right"></i> <span class="label label-primary"><?php echo html_escape(isset($history['target_environment']) ? $history['target_environment'] : ''); ?></span></td>
                  <td class="promotion-history-detail">
                    <b><?php echo html_escape(isset($history['source_job']) ? $history['source_job'] : ''); ?></b><br>
                    <i class="fa fa-long-arrow-right"></i> <?php echo html_escape(isset($history['target_job']) ? $history['target_job'] : ''); ?>
                    <?php if(count($historyJobs) > 1) { ?><details><summary><?php echo count($historyJobs); ?> deployed jobs</summary><?php foreach($historyJobs as $job) { ?><div><?php echo html_escape(isset($job['source_job']) ? $job['source_job'] : ''); ?> &rarr; <?php echo html_escape(isset($job['target_job']) ? $job['target_job'] : ''); ?></div><?php } ?></details><?php } ?>
                  </td>
                  <td class="promotion-history-detail">
                    <div>Dependencies: <b><?php echo !empty($historyParameters['include_dependencies']) ? 'Yes' : 'No'; ?></b>; overwrite: <b><?php echo !empty($historyParameters['overwrite_existing']) ? 'Yes' : 'No'; ?></b></div>
                    <div>Contexts: <b><?php echo !empty($historyParameters['promote_contexts']) ? 'Yes' : 'No'; ?></b><?php echo !empty($historyParameters['context_project']) ? ' ('.html_escape($historyParameters['context_project']).')' : ''; ?>; artifacts: <b><?php echo (int) $artifactCount; ?></b></div>
                    <?php if($artifactCount > 0) { ?><details><summary>Artifact paths</summary><?php $artifactRows = !empty($historyArtifacts['copied']) ? $historyArtifacts['copied'] : $historyArtifacts['planned']; foreach($artifactRows as $artifact) { ?><div><?php echo html_escape(is_array($artifact) ? ((isset($artifact['label']) ? $artifact['label'].': ' : '').(isset($artifact['source']) ? $artifact['source'].' -> ' : '').(isset($artifact['target']) ? $artifact['target'] : '')) : $artifact); ?></div><?php } ?></details><?php } ?>
                  </td>
                  <td><span class="label label-<?php echo $historyStatusClass; ?>"><?php echo html_escape(ucwords(str_replace('_', ' ', $historyStatus))); ?></span><?php if(!empty($history['message'])) { ?><div class="promotion-history-detail" style="margin-top: 5px;"><?php echo html_escape($history['message']); ?></div><?php } ?></td>
                  <td>
                    <?php if(!empty($history['rollback_available'])) { ?>
                    <form action="<?php echo base_url() ?>Context/rollbackJobPromotion" method="POST">
                      <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>" />
                      <input type="hidden" name="rollbackId" value="<?php echo html_escape($history['rollback_id']); ?>" />
                      <button type="submit" class="btn btn-warning btn-xs" onclick="return confirm('Rollback checkpoint <?php echo html_escape($history['rollback_id']); ?>?');"><i class="fa fa-undo"></i> Rollback</button>
                    </form>
                    <?php } else { ?>
                    <span class="text-muted"><?php echo $historyStatus === 'rolled_back' ? 'Rolled back' : 'No checkpoint'; ?></span>
                    <?php } ?>
                  </td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
            <?php if(empty($promotionHistory)) { ?><p class="text-muted text-center" style="padding: 24px;">No deployments have been recorded yet.</p><?php } ?>
          </div>
        </div>
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
    var sourceJobsRequest = null;
    var promotionJobsUrl = <?php echo json_encode(base_url().'Context/promotionJobs'); ?>;
    var sourceJobOptions = [];
    var sourceJobEnvironmentRequests = {};
    var sourceJobEnvironmentInfo = {};
    var jobSchedulesUrl = <?php echo json_encode(base_url().'jobCreation/jobSchedules'); ?>;
    var jobEnvironmentIndexRequest = null;
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
      var workload = {
        value: $(this).val(),
        text: $(this).text(),
        color: $(this).data('color'),
        buildable: $(this).data('buildable'),
        workloadType: $(this).data('workload-type') || 'job',
        environment: $(this).data('environment') || '',
        pipelineId: Number($(this).data('pipeline-id') || 0),
        pipelineKey: $(this).data('pipeline-key') || '',
        pipelineVersion: Number($(this).data('pipeline-version') || 0),
        pipelineEnvironment: $(this).data('pipeline-environment') || '',
        pipelineNodeCount: Number($(this).data('pipeline-node-count') || 0),
        pipelineSchedule: $(this).data('pipeline-schedule') || ''
      };
      sourceJobOptions.push(workload);
      var workloadEnvironment = workload.pipelineEnvironment || workload.environment;
      if (workloadEnvironment) {
        sourceJobEnvironmentInfo[workload.value] = {environment: workloadEnvironment, source: workload.pipelineEnvironment ? 'Pipeline record' : 'Jenkins metadata', unknown: false};
      }
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

    function renderSourceJobOptions() {
      var currentValue = $('#sourceJob').val() || '';
      var keepCurrent = currentValue !== '' && sourceJobOptions.some(function(job) { return job.value === currentValue; });

      $('#sourceJob').empty().append($('<option>', {value: '', text: 'Select job or pipeline'}));
      $.each(sourceJobOptions, function(index, job) {
        $('#sourceJob').append($('<option>', {
          value: job.value,
          text: job.text
        }).attr({
          'data-color': job.color,
          'data-buildable': job.buildable,
          'data-workload-type': job.workloadType,
          'data-environment': job.environment,
          'data-pipeline-id': job.pipelineId,
          'data-pipeline-key': job.pipelineKey,
          'data-pipeline-version': job.pipelineVersion,
          'data-pipeline-environment': job.pipelineEnvironment,
          'data-pipeline-node-count': job.pipelineNodeCount,
          'data-pipeline-schedule': job.pipelineSchedule
        }));
      });

      $('#sourceJob').val(keepCurrent ? currentValue : '').trigger('change.select2');
      if (! keepCurrent) {
        setDetectedSourceEnvironment({environment: 'Unknown', source: 'Not detected', unknown: true}, false);
      }
    }

    function loadSourceJobs() {
      if (sourceJobsRequest && sourceJobsRequest.readyState !== 4) {
        sourceJobsRequest.abort();
      }
      var environment = activeSourceEnvironmentFilter() || 'ALL';
      sourceJobsRequest = $.ajax({
        url: promotionJobsUrl,
        method: 'GET',
        dataType: 'json',
        cache: false,
        data: {environment: environment}
      }).done(function(response) {
        sourceJobOptions = $.map(response && response.jobs ? response.jobs : [], function(job) {
          var workloadEnvironment = job.pipelineEnvironment || job.environment || '';
          if (workloadEnvironment) {
            sourceJobEnvironmentInfo[job.fullName] = {environment: workloadEnvironment, source: job.pipelineEnvironment ? 'Pipeline record' : 'Jenkins metadata', unknown: false};
          }
          return {
            value: job.fullName,
            text: job.workloadType === 'pipeline' ? '[Pipeline] ' + job.displayName + ' (v' + Number(job.pipelineVersion || 0) + ')' : '[Job] ' + job.displayName,
            color: job.color || '',
            buildable: job.buildable ? 1 : 0,
            workloadType: job.workloadType || 'job',
            environment: job.environment || '',
            pipelineId: Number(job.pipelineId || 0),
            pipelineKey: job.pipelineKey || '',
            pipelineVersion: Number(job.pipelineVersion || 0),
            pipelineEnvironment: job.pipelineEnvironment || '',
            pipelineNodeCount: Number(job.pipelineNodeCount || 0),
            pipelineSchedule: job.pipelineSchedule || ''
          };
        });
        renderSourceJobOptions();
        hydrateSourceJobEnvironments();
        schedulePreview();
      }).fail(function(xhr, textStatus) {
        if (textStatus !== 'abort') {
          toastr.error('Source workloads could not be loaded for ' + environment + '.', 'Environment Filter');
        }
      });
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

      if (($('#sourceJob option:selected').data('workload-type') || 'job') === 'pipeline') {
        setDetectedSourceEnvironment(sourceJobEnvironment(sourceJob), true);
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

    // Every promotion-history row's environment, from one request.
    //
    // This table used to pull config.xml straight from Jenkins for each row, with
    // no caching and no de-duplication, so a history of sixty rows meant sixty
    // proxied round trips every time the page opened. The server resolves the
    // same parameter declaration while listing the jobs.
    function jobEnvironmentIndex() {
      if (! jobEnvironmentIndexRequest) {
        jobEnvironmentIndexRequest = $.ajax({url: jobSchedulesUrl, method: 'GET', dataType: 'json'})
          .then(function(payload) {
            return payload && payload.schedules ? payload.schedules : {};
          }, function() {
            return {};
          });
      }

      return jobEnvironmentIndexRequest;
    }

    function hydrateInventoryEnvironments() {
      var rows = [];

      $('tr[data-promotion-job]').each(function() {
        var $row = $(this);
        var jobName = $row.data('promotion-job') || '';

        if (! jobName) {
          return;
        }

        setInventoryEnvironment($row, environmentHelper.detectFromJob({name: jobName, fullName: jobName}));
        rows.push({row: $row, jobName: jobName});
      });

      if (! rows.length) {
        return;
      }

      jobEnvironmentIndex().done(function(index) {
        var pending = {};

        $.each(rows, function(idx, entry) {
          var indexed = index[entry.jobName];

          // Only a parameter-declared environment is as authoritative here as
          // reading config.xml; anything else still reads its own config.
          if (indexed && indexed.environmentFromParameter) {
            setInventoryEnvironment(entry.row, {
              environment: environmentHelper.normalize(indexed.environment),
              source: 'Jenkins parameter',
              unknown: false
            });
            return;
          }

          // The same job can appear on several history rows; read it once.
          if (! pending[entry.jobName]) {
            pending[entry.jobName] = [];
          }
          pending[entry.jobName].push(entry.row);
        });

        $.each(pending, function(jobName, targets) {
          $.ajax({
            url: jenkinsBaseUrl() + jenkinsJobPath(jobName) + '/config.xml',
            method: 'GET',
            dataType: 'text'
          }).done(function(xmlText) {
            var info = environmentHelper.detectFromConfig(xmlText || '', jobName);
            $.each(targets, function(idx, $row) {
              setInventoryEnvironment($row, info);
            });
          });
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
      if (($('#sourceJob option:selected').data('workload-type') || 'job') === 'pipeline') {
        $('#targetJobName').val('');
      } else if (!targetWasEdited) {
        $('#targetJobName').val(suggestTargetJobName());
      }
    }

    function selectedWorkload() {
      var $option = $('#sourceJob option:selected');
      return {
        type: $option.data('workload-type') || 'job',
        pipelineId: Number($option.data('pipeline-id') || 0),
        version: Number($option.data('pipeline-version') || 0),
        nodeCount: Number($option.data('pipeline-node-count') || 0),
        schedule: $option.data('pipeline-schedule') || '',
        label: $.trim($option.text().replace(/^\[(Job|Pipeline)\]\s*/, '').replace(/\s+\(v\d+\)$/, '')),
        jenkinsJob: $option.val() || ''
      };
    }

    function syncWorkloadControls() {
      var pipeline = selectedWorkload().type === 'pipeline';
      $('#targetJobGroup, #jobDeploymentOptions').toggle(!pipeline);
      $('#overwriteExistingLabel').text(pipeline ? 'Overwrite existing target pipeline' : 'Overwrite existing target job and artifacts');
      $submitButton.html('<i class="fa fa-level-up"></i> ' + (pipeline ? 'Deploy Pipeline' : 'Deploy Job'));
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

      $preview.html('<span class="label label-success">Ready</span> <strong>' + htmlEscape(response.target_job) + '</strong> passed deployment checks. ' + htmlEscape(targetState) + '. Rollback is ' + (response.rollback_enabled ? 'enabled' : 'disabled') + '.');

      $kpis.html(
        kpi('Jobs', response.job_count || 0) +
        kpi('Dependencies', response.dependency_count || 0) +
        kpi('Context Keys', context.enabled ? (context.total || 0) : 0) +
        kpi('Config Updates', (response.command_updates || 0) + (response.parameter_updates || 0) + (response.downstream_updates || 0) + (response.artifact_path_updates || 0)) +
        kpi('Artifact Folders', artifactCount)
      ).show();

      if (response.jobs && response.jobs.length) {
        jobTable = '<div class="table-responsive promotion-preview-table"><table class="table table-condensed table-bordered"><thead><tr><th>Source Job</th><th>Target Job</th><th>Action</th><th>Detected</th><th>Deployment</th><th>Config Changes</th><th>Artifacts</th></tr></thead><tbody>';
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

    function renderPipelinePreview(workload) {
      var targetEnvironment = selectedEnvironmentName('#targetEnvironment');
      $preview.html('<span class="label label-success">Ready</span> <strong>' + htmlEscape(workload.label) + '</strong> will be deployed as a managed pipeline in ' + htmlEscape(targetEnvironment) + '.');
      $kpis.html(
        kpi('Type', 'Pipeline') +
        kpi('Version', 'v' + workload.version) +
        kpi('Jobs', workload.nodeCount) +
        kpi('Schedule', workload.schedule || 'Manual')
      ).show();
      $details.html('<ul class="promotion-preview-list">' +
        '<li><span class="label label-info">pipeline</span> ' + htmlEscape(workload.jenkinsJob) + '</li>' +
        '<li><span class="label label-primary">route</span> ' + htmlEscape(selectedEnvironmentName('#sourceEnvironment')) + ' to ' + htmlEscape(targetEnvironment) + '</li>' +
        '<li><span class="label label-default">graph</span> Target jobs and environment mappings are validated before deployment.</li>' +
      '</ul>');
      $submitButton.prop('disabled', false);
    }

    function requestPreview() {
      var sourceJob = $('#sourceJob').val();
      var sourceEnvironment = $('#sourceEnvironment').val();
      var targetEnvironment = $('#targetEnvironment').val();
      var targetJobName = $.trim($('#targetJobName').val());
      var promoteContexts = $('#promoteContexts').is(':checked');
      var promotionProject = $('#promotionProject').val();
      var workload = selectedWorkload();

      if (!sourceJob || !sourceEnvironment || !targetEnvironment) {
        setPreviewIdle('Select a source workload and environments to inspect the deployment.');
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

      if (workload.type === 'pipeline') {
        renderPipelinePreview(workload);
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
        setPreviewIdle('<span class="label label-warning">Waiting</span> Select a context project or turn context deployment off.');
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
          setPreviewIdle('<span class="label label-danger">Blocked</span> ' + htmlEscape(response && response.message ? response.message : 'Deployment preview failed.'));
        }
      }).fail(function(xhr, status) {
        if (status === 'abort') {
          return;
        }
        setPreviewIdle('<span class="label label-danger">Error</span> Deployment preview request failed.');
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
      syncWorkloadControls();
      detectSourceJobEnvironment();
    });

    $('#sourceEnvironment, #targetEnvironment, #overwriteExisting, #includeDependencies, #promoteContexts, #promotionProject, #overwriteContexts, #createRollback').on('change', function() {
      syncContextControls();
      if (this.id === 'sourceEnvironment') {
        loadSourceJobs();
      }
      schedulePreview();
    });

    $(document).on('jobseeker:environment-change', function() {
      applyGlobalSourceEnvironment();
      loadSourceJobs();
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

      var workload = selectedWorkload();
      if (workload.type === 'pipeline') {
        event.preventDefault();
        if (!confirm('Deploy this pipeline to the target environment?')) {
          return false;
        }
        $submitButton.prop('disabled', true);
        $.ajax({
          url: '<?php echo base_url(); ?>pipelines/deploy?environment=' + encodeURIComponent(selectedEnvironmentName('#sourceEnvironment')),
          method: 'POST',
          dataType: 'json',
          data: {
            id: workload.pipelineId,
            target_environment: selectedEnvironmentName('#targetEnvironment'),
            overwrite: $('#overwriteExisting').is(':checked') ? '1' : '0'
          }
        }).done(function(response) {
          window.location.href = '<?php echo base_url(); ?>pipelines?id=' + encodeURIComponent(response.id) + '&environment=' + encodeURIComponent(response.environment);
        }).fail(function(xhr) {
          var response = xhr.responseJSON || {};
          setPreviewIdle('<span class="label label-danger">Blocked</span> ' + htmlEscape(response.message || 'Pipeline deployment failed.'));
          $submitButton.prop('disabled', false);
        });
        return false;
      }

      return confirm('Deploy this job package to the target environment?');
    });

    applyGlobalSourceEnvironment();
    renderSourceJobOptions();
    hydrateSourceJobEnvironments();
    syncContextControls();
    syncWorkloadControls();
    hydrateInventoryEnvironments();
    if ($.fn.dataTable && ! $.fn.dataTable.isDataTable('#promotionInventoryTable')) {
      var promotionInventoryTable = $('#promotionInventoryTable').DataTable({
        scrollX: true,
        autoWidth: false,
        order: [[2, 'desc']],
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
    if ($.fn.dataTable && ! $.fn.dataTable.isDataTable('#promotionHistoryTable')) {
      $('#promotionHistoryTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 20,
        autoWidth: false,
        columnDefs: [
          { orderable: false, targets: [4, 6] }
        ]
      });
    }
    $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
      if ($.fn.dataTable) {
        $.fn.dataTable.tables({visible: true, api: true}).columns.adjust();
      }
    });
    schedulePreview();
  });
</script>
