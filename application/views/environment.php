<?php
$environmentRows = !empty($list) ? $list : array();
$inactiveEnvironments = max(0, (int) $environments - (int) $activeEnvironments);
$standaloneDeployment = isset($deployment_mode) && $deployment_mode === 'standalone';
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/context-details.css?v=3">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/settings-details.css?v=1">

<div class="content-wrapper context-page settings-page">
  <section class="content-header">
    <h1>
      <i class="fa fa-globe"></i> Context Settings <b>Environment Details</b>
      <small>Manage runtime environment scopes</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>"><i class="fa fa-dashboard"></i> Home</a></li>
      <li>Context Settings</li>
      <li class="active">Environment Details</li>
    </ol>
  </section>

  <section class="content">
    <div class="context-shell">
      <div class="context-page-heading">
        <div><h2>Runtime environments</h2><p>Define the deployment stages used to scope contexts, jobs, and promotions.</p></div>
      </div>

      <div class="context-metrics settings-metrics">
        <div class="info-box context-metric">
          <span class="info-box-icon bg-aqua context-metric-icon"><i class="fa fa-globe"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Available environments</span><span class="info-box-number context-metric-value" id="settingsVisibleCount"><?php echo (int) $environments; ?></span></div>
        </div>
        <div class="info-box context-metric">
          <span class="info-box-icon bg-green context-metric-icon"><i class="fa fa-check"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Active environments</span><span class="info-box-number context-metric-value" id="settingsActiveCount"><?php echo (int) $activeEnvironments; ?></span></div>
        </div>
        <div class="info-box context-metric">
          <span class="info-box-icon bg-yellow context-metric-icon"><i class="fa fa-pause"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Inactive environments</span><span class="info-box-number context-metric-value"><?php echo (int) $inactiveEnvironments; ?></span></div>
        </div>
      </div>

      <?php
        $this->load->helper('form');
        $error = $this->session->flashdata('error');
        $success = $this->session->flashdata('success');
      ?>
      <?php if ($error) { ?><div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><?php echo html_escape($error); ?></div><?php } ?>
      <?php if ($success) { ?><div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><?php echo html_escape($success); ?></div><?php } ?>
      <?php echo validation_errors('<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>', '</div>'); ?>
	  <?php if ($standaloneDeployment) { ?><div class="alert alert-info"><i class="fa fa-lock"></i> This installation is fixed to <b><?php echo html_escape($standalone_environment); ?></b>. Change the deployment configuration to rename or replace its environment.</div><?php } ?>

	  <?php if (! $standaloneDeployment) { ?>
      <div class="box box-primary context-card animated fadeIn">
        <div class="box-header with-border context-card-header">
          <div class="context-card-title"><span class="context-card-title-icon"><i class="fa fa-plus"></i></span><div><h3>Add an environment</h3><p>Create a deployment stage for jobs and context variables.</p></div></div>
        </div>
        <form action="<?php echo base_url(); ?>Context/addEnvironment" method="POST" id="environmentCreateForm">
          <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
          <div class="box-body context-card-body">
            <div class="context-form-grid">
              <div class="context-field settings-field-name">
                <label for="name">Environment name <span class="text-danger">*</span></label>
                <input id="name" type="text" name="name" class="form-control" placeholder="e.g. QA" maxlength="100" autocomplete="off" required>
                <span class="context-help">Use a short, recognizable deployment-stage name.</span>
              </div>
              <div class="context-field settings-field-wide">
                <label for="description">Description</label>
                <input id="description" type="text" name="description" class="form-control" placeholder="Purpose, ownership, or deployment notes" maxlength="2000" autocomplete="off">
              </div>
              <div class="context-field settings-field-status">
                <label for="active">Status</label>
                <select id="active" class="form-control" name="active"><option value="1">Active</option><option value="0">Inactive</option></select>
              </div>
            </div>
          </div>
          <div class="box-footer context-form-footer">
            <span class="context-form-note"><i class="fa fa-info-circle"></i> Environment names must be unique.</span>
            <div class="context-form-actions"><button type="reset" class="btn btn-default">Clear</button><button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Create environment</button></div>
          </div>
        </form>
      </div>
	  <?php } ?>

      <div class="box box-primary context-card">
        <div class="box-header with-border context-card-header">
          <div class="context-card-title"><span class="context-card-title-icon"><i class="fa fa-list"></i></span><div><h3>Available environments</h3><p>Search and manage configured runtime stages.</p></div></div>
          <label class="context-empty-filter">Rows
            <select id="settingsPageLength" class="form-control input-sm" style="display:inline-block; width:auto; margin-left:6px;"><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option></select>
          </label>
        </div>

        <div class="context-toolbar settings-toolbar" aria-label="Environment filters">
          <div class="context-filter context-filter-search"><label for="settingsSearch">Search environments</label><div class="context-search-wrap"><i class="fa fa-search"></i><input id="settingsSearch" type="search" class="form-control" placeholder="Environment name, description, or date"></div></div>
          <div class="context-filter"><label for="settingsStatusFilter">Status</label><select id="settingsStatusFilter" class="form-control"><option value="">Any status</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
          <button id="settingsResetFilters" type="button" class="btn btn-default context-filter-reset"><i class="fa fa-undo"></i> Reset</button>
        </div>

        <div class="context-table-wrap">
          <table id="settingsDetailsTable" class="table table-hover context-table">
			<thead><tr><th>Environment</th><th>Description</th><th>Status</th><th>Updated</th><?php if ($role != 1 && ! $standaloneDeployment) { ?><th class="settings-actions-column">Actions</th><?php } ?></tr></thead>
            <tbody>
              <?php foreach ($environmentRows as $record) {
                $createdOn = !empty($record->CreatedOn) ? date('Y-m-d H:i', strtotime($record->CreatedOn)) : '';
                $modifiedOn = !empty($record->ModifiedOn) ? date('Y-m-d H:i', strtotime($record->ModifiedOn)) : '';
                $updatedOn = $modifiedOn !== '' ? $modifiedOn : $createdOn;
              ?>
                <tr>
                  <td><span class="settings-name"><?php echo html_escape($record->Environment); ?></span><span class="context-row-meta">#<?php echo (int) $record->Id; ?> &middot; Created <?php echo js_time($record->CreatedOn, array('format' => 'Y-m-d H:i', 'empty' => '')); ?></span></td>
                  <td><?php echo trim((string) $record->Description) !== '' ? html_escape($record->Description) : '<span class="settings-empty-value">No description</span>'; ?></td>
                  <td><span class="context-badge <?php echo (int) $record->IsActive === 1 ? 'context-badge-active' : 'context-badge-inactive'; ?>"><?php echo (int) $record->IsActive === 1 ? 'Active' : 'Inactive'; ?></span></td>
                  <td data-order="<?php echo html_escape($updatedOn); ?>"><?php echo js_time(!empty($record->ModifiedOn) ? $record->ModifiedOn : $record->CreatedOn, array('format' => 'Y-m-d H:i', 'empty' => '')); ?><?php if ($modifiedOn === '') { ?><span class="context-row-meta">Created</span><?php } ?></td>
				  <?php if ($role != 1 && ! $standaloneDeployment) { ?><td class="settings-actions"><a class="btn btn-sm btn-default" href="<?php echo base_url().'Context/editEnvironment/'.(int) $record->Id; ?>" title="Edit <?php echo html_escape($record->Environment); ?>"><i class="fa fa-pencil"></i></a><button type="button" class="btn btn-sm btn-danger delete-setting" data-setting-id="<?php echo (int) $record->Id; ?>" data-setting-name="<?php echo html_escape($record->Environment); ?>" title="Delete <?php echo html_escape($record->Environment); ?>"><i class="fa fa-trash"></i></button></td><?php } ?>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<script>window.settingsDetailsConfig = {type: 'environment', deleteUrl: <?php echo json_encode(base_url().'Context/deleteEnvironment'); ?>};</script>
<script src="<?php echo base_url(); ?>assets/js/settings-details.js?v=1"></script>
