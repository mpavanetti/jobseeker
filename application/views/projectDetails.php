<?php
$projectRows = !empty($list) ? $list : array();
$projectsWithRepository = 0;
foreach ($projectRows as $projectRecord) {
  if (trim((string) $projectRecord->GitPath) !== '') {
    $projectsWithRepository++;
  }
}
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/context-details.css?v=3">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/settings-details.css?v=1">

<div class="content-wrapper context-page settings-page">
  <section class="content-header">
    <h1>
      <i class="fa fa-folder-open"></i> Context Settings <b>Project Details</b>
      <small>Manage project scopes and source repositories</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>"><i class="fa fa-dashboard"></i> Home</a></li>
      <li>Context Settings</li>
      <li class="active">Project Details</li>
    </ol>
  </section>

  <section class="content">
    <div class="context-shell">
      <div class="context-page-heading">
        <div>
          <h2>Project scopes</h2>
          <p>Organize context variables by application, pipeline, or team.</p>
        </div>
      </div>

      <div class="context-metrics settings-metrics">
        <div class="info-box context-metric">
          <span class="info-box-icon bg-aqua context-metric-icon"><i class="fa fa-folder-open"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Available projects</span><span class="info-box-number context-metric-value" id="settingsVisibleCount"><?php echo (int) $projects; ?></span></div>
        </div>
        <div class="info-box context-metric">
          <span class="info-box-icon bg-green context-metric-icon"><i class="fa fa-check"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Active projects</span><span class="info-box-number context-metric-value" id="settingsActiveCount"><?php echo (int) $activeprojects; ?></span></div>
        </div>
        <div class="info-box context-metric">
          <span class="info-box-icon bg-purple context-metric-icon"><i class="fa fa-code-fork"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Repositories linked</span><span class="info-box-number context-metric-value"><?php echo (int) $projectsWithRepository; ?></span></div>
        </div>
      </div>

      <?php
        $this->load->helper('form');
        $error = $this->session->flashdata('error');
        $success = $this->session->flashdata('success');
      ?>
      <?php if ($error) { ?>
        <div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><?php echo html_escape($error); ?></div>
      <?php } ?>
      <?php if ($success) { ?>
        <div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><?php echo html_escape($success); ?></div>
      <?php } ?>
      <?php echo validation_errors('<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>', '</div>'); ?>

      <div class="box box-primary context-card animated fadeIn">
        <div class="box-header with-border context-card-header">
          <div class="context-card-title">
            <span class="context-card-title-icon"><i class="fa fa-plus"></i></span>
            <div><h3>Add a project</h3><p>Create a reusable scope for contexts and source configuration.</p></div>
          </div>
        </div>
        <form action="<?php echo base_url(); ?>Context/addProject" method="POST" id="projectCreateForm">
          <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
          <div class="box-body context-card-body">
            <div class="context-form-grid">
              <div class="context-field settings-field-name">
                <label for="name">Project name <span class="text-danger">*</span></label>
                <input id="name" type="text" name="name" class="form-control" placeholder="e.g. Customer Analytics" maxlength="1000" autocomplete="off" required>
                <span class="context-help">This name identifies the project in context selectors.</span>
              </div>
              <div class="context-field settings-field-wide">
                <label for="gitpath">Git path</label>
                <input id="gitpath" type="text" name="gitpath" class="form-control" placeholder="https://github.com/organization/repository.git" maxlength="2000" autocomplete="off">
                <span class="context-help">Optional source repository or local Git path.</span>
              </div>
              <div class="context-field settings-field-status">
                <label for="active">Status</label>
                <select id="active" class="form-control" name="active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
            </div>
          </div>
          <div class="box-footer context-form-footer">
            <span class="context-form-note"><i class="fa fa-info-circle"></i> Project names must be unique.</span>
            <div class="context-form-actions">
              <button type="reset" class="btn btn-default">Clear</button>
              <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Create project</button>
            </div>
          </div>
        </form>
      </div>

      <div class="box box-primary context-card">
        <div class="box-header with-border context-card-header">
          <div class="context-card-title">
            <span class="context-card-title-icon"><i class="fa fa-list"></i></span>
            <div><h3>Available projects</h3><p>Search and manage configured project scopes.</p></div>
          </div>
          <label class="context-empty-filter">Rows
            <select id="settingsPageLength" class="form-control input-sm" style="display:inline-block; width:auto; margin-left:6px;">
              <option value="10">10</option><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
            </select>
          </label>
        </div>

        <div class="context-toolbar settings-toolbar" aria-label="Project filters">
          <div class="context-filter context-filter-search">
            <label for="settingsSearch">Search projects</label>
            <div class="context-search-wrap"><i class="fa fa-search"></i><input id="settingsSearch" type="search" class="form-control" placeholder="Project name, Git path, or date"></div>
          </div>
          <div class="context-filter">
            <label for="settingsStatusFilter">Status</label>
            <select id="settingsStatusFilter" class="form-control"><option value="">Any status</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select>
          </div>
          <button id="settingsResetFilters" type="button" class="btn btn-default context-filter-reset"><i class="fa fa-undo"></i> Reset</button>
        </div>

        <div class="context-table-wrap">
          <table id="settingsDetailsTable" class="table table-hover context-table">
            <thead><tr><th>Project</th><th>Git path</th><th>Status</th><th>Updated</th><?php if ($role != 1) { ?><th class="settings-actions-column">Actions</th><?php } ?></tr></thead>
            <tbody>
              <?php foreach ($projectRows as $record) {
                $createdOn = !empty($record->CreatedOn) ? date('Y-m-d H:i', strtotime($record->CreatedOn)) : '';
                $modifiedOn = !empty($record->ModifiedOn) ? date('Y-m-d H:i', strtotime($record->ModifiedOn)) : '';
                $updatedOn = $modifiedOn !== '' ? $modifiedOn : $createdOn;
              ?>
                <tr>
                  <td><span class="settings-name"><?php echo html_escape($record->ProjectName); ?></span><span class="context-row-meta">#<?php echo (int) $record->Id; ?> &middot; Created <?php echo html_escape($createdOn); ?></span></td>
                  <td><?php if (trim((string) $record->GitPath) !== '') { ?><code class="settings-path" title="<?php echo html_escape($record->GitPath); ?>"><?php echo html_escape($record->GitPath); ?></code><?php } else { ?><span class="settings-empty-value">Not configured</span><?php } ?></td>
                  <td><span class="context-badge <?php echo (int) $record->IsActive === 1 ? 'context-badge-active' : 'context-badge-inactive'; ?>"><?php echo (int) $record->IsActive === 1 ? 'Active' : 'Inactive'; ?></span></td>
                  <td data-order="<?php echo html_escape($updatedOn); ?>"><?php echo html_escape($updatedOn); ?><?php if ($modifiedOn === '') { ?><span class="context-row-meta">Created</span><?php } ?></td>
                  <?php if ($role != 1) { ?><td class="settings-actions"><a class="btn btn-sm btn-default" href="<?php echo base_url().'Context/editProject/'.(int) $record->Id; ?>" title="Edit <?php echo html_escape($record->ProjectName); ?>"><i class="fa fa-pencil"></i></a><button type="button" class="btn btn-sm btn-danger delete-setting" data-setting-id="<?php echo (int) $record->Id; ?>" data-setting-name="<?php echo html_escape($record->ProjectName); ?>" title="Delete <?php echo html_escape($record->ProjectName); ?>"><i class="fa fa-trash"></i></button></td><?php } ?>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<script>window.settingsDetailsConfig = {type: 'project', deleteUrl: <?php echo json_encode(base_url().'Context/deleteProject'); ?>};</script>
<script src="<?php echo base_url(); ?>assets/js/settings-details.js?v=1"></script>
