<?php
$contextRows = !empty($list) ? $list : array();
$environmentNames = array();
$projectNames = array();
$encryptedContexts = 0;

foreach ($contextRows as $contextRecord) {
  $environmentName = trim((string) $contextRecord->Environment);
  $projectName = trim((string) $contextRecord->ProjectName);
  if ($environmentName !== '') {
    $environmentNames[$environmentName] = TRUE;
  }
  if ($projectName !== '') {
    $projectNames[$projectName] = TRUE;
  }
  if ((int) $contextRecord->isEncrypted === 1) {
    $encryptedContexts++;
  }
}

$environmentNames = array_keys($environmentNames);
$projectNames = array_keys($projectNames);
sort($environmentNames, SORT_NATURAL | SORT_FLAG_CASE);
sort($projectNames, SORT_NATURAL | SORT_FLAG_CASE);

$filterEnvironmentNames = array();
foreach ((array) $listEnvironments as $environmentRecord) {
  $configuredEnvironmentName = trim((string) $environmentRecord->Environment);
  if ($configuredEnvironmentName !== '') {
    $filterEnvironmentNames[$configuredEnvironmentName] = TRUE;
  }
}
$filterEnvironmentNames = array_keys($filterEnvironmentNames);
sort($filterEnvironmentNames, SORT_NATURAL | SORT_FLAG_CASE);
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/context-details.css?v=6">

<div class="content-wrapper context-page">
  <section class="content-header">
    <h1>
      <i class="fa fa-sliders"></i> Context Settings <b>Context Details</b>
      <small>Manage environment-specific runtime variables</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>"><i class="fa fa-dashboard"></i> Home</a></li>
      <li>Context Settings</li>
      <li class="active">Context Details</li>
    </ol>
  </section>

  <section class="content">
    <div class="context-shell">
      <div class="context-page-heading">
        <div>
          <h2>Runtime context library</h2>
          <p>Create, scope, and protect the values used by your jobs at runtime.</p>
        </div>
        <span class="context-scope-pill"><i class="fa fa-globe"></i> <span id="contextScopeLabel">All environments</span></span>
      </div>

      <div class="context-metrics">
        <div class="info-box context-metric">
          <span class="info-box-icon bg-aqua context-metric-icon"><i class="fa fa-database"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Available contexts</span><span class="info-box-number context-metric-value" id="contextVisibleCount"><?php echo (int) $contexts; ?></span></div>
        </div>
        <div class="info-box context-metric">
          <span class="info-box-icon bg-green context-metric-icon"><i class="fa fa-check"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Active</span><span class="info-box-number context-metric-value" id="contextActiveCount"><?php echo (int) $activeContexts; ?></span></div>
        </div>
        <div class="info-box context-metric">
          <span class="info-box-icon bg-yellow context-metric-icon"><i class="fa fa-lock"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Encrypted</span><span class="info-box-number context-metric-value" id="contextEncryptedCount"><?php echo (int) $encryptedContexts; ?></span></div>
        </div>
        <div class="info-box context-metric">
          <span class="info-box-icon bg-purple context-metric-icon"><i class="fa fa-globe"></i></span>
          <div class="info-box-content"><span class="info-box-text context-metric-label">Environments</span><span class="info-box-number context-metric-value" id="contextEnvironmentCount"><?php echo count($environmentNames); ?></span></div>
        </div>
      </div>

      <?php
        $this->load->helper('form');
        $error = $this->session->flashdata('error');
        $success = $this->session->flashdata('success');
      ?>
      <?php if ($error) { ?>
        <div class="alert alert-danger alert-dismissable">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
          <?php echo html_escape($error); ?>
        </div>
      <?php } ?>
      <?php if ($success) { ?>
        <div class="alert alert-success alert-dismissable">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
          <?php echo html_escape($success); ?>
        </div>
      <?php } ?>
      <?php echo validation_errors('<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>', '</div>'); ?>

      <div class="box box-primary context-card animated fadeIn">
        <div class="box-header with-border context-card-header">
          <div class="context-card-title">
            <span class="context-card-title-icon"><i class="fa fa-plus"></i></span>
            <div>
              <h3>Add a context variable</h3>
              <p>Keys are unique within a project and environment.</p>
            </div>
          </div>
        </div>
        <form action="<?php echo base_url(); ?>Context/addContext?environment=<?php echo rawurlencode($selectedEnvironment); ?>" method="POST" id="contextCreateForm">
          <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
          <div class="box-body context-card-body">
            <div class="context-form-grid">
              <div class="context-field context-field-key">
                <label for="contextKey">Context key <span class="text-danger">*</span></label>
                <input id="contextKey" type="text" name="contextKey" class="form-control" placeholder="e.g. API_BASE_URL" maxlength="1000" autocomplete="off" required>
                <span class="context-help">Use a stable, descriptive name that jobs can reference.</span>
              </div>
              <div class="context-field">
                <label for="projectName">Project <span class="text-danger">*</span></label>
                <select id="projectName" class="form-control" name="projectName" required>
                  <?php foreach ((array) $listProjects as $project) { ?>
                    <option value="<?php echo html_escape($project->ProjectName); ?>"><?php echo html_escape($project->ProjectName); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="context-field">
                <label for="environmentName">Environment <span class="text-danger">*</span></label>
                <select id="environmentName" class="form-control" name="environmentName" required>
                  <?php foreach ((array) $listEnvironments as $environment) { ?>
                    <option value="<?php echo html_escape($environment->Environment); ?>"><?php echo html_escape($environment->Environment); ?></option>
                  <?php } ?>
                </select>
              </div>

              <div class="context-field context-field-value">
                <label for="contextValue">Context value <span class="text-danger">*</span></label>
                <div class="input-group context-value-group">
                  <input id="contextValue" type="text" name="contextValue" class="form-control" placeholder="Enter the runtime value" maxlength="1000" autocomplete="new-password" required>
                  <span class="input-group-btn">
                    <button id="toggleContextValue" class="btn btn-default" type="button" aria-pressed="false"><i class="fa fa-eye"></i> Show</button>
                  </span>
                </div>
                <span class="context-help">Encrypted values are masked everywhere in the context list.</span>
              </div>
              <div class="context-field context-field-description">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" placeholder="Where is this value used?" maxlength="2000"></textarea>
              </div>

              <div class="context-field">
                <label for="active">Availability</label>
                <select id="active" class="form-control" name="active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
              <div class="context-field">
                <label for="encrypted">Value protection</label>
                <select id="encrypted" class="form-control" name="encrypted">
                  <option value="0">Standard value</option>
                  <option value="1">Encrypted secret</option>
                </select>
              </div>
            </div>

          </div>
          <div class="box-footer context-form-footer">
            <span class="context-form-note"><i class="fa fa-info-circle"></i> Required fields are marked with an asterisk.</span>
            <div class="context-form-actions">
              <button type="reset" class="btn btn-default">Clear</button>
              <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Create context</button>
            </div>
          </div>
        </form>
      </div>

      <div class="box box-primary context-card">
        <div class="box-header with-border context-card-header">
          <div class="context-card-title">
            <span class="context-card-title-icon"><i class="fa fa-list"></i></span>
            <div>
              <h3>Available contexts</h3>
              <p>Search by key or value, then narrow the list using exact filters.</p>
            </div>
          </div>
          <label class="context-empty-filter">Rows
            <select id="contextPageLength" class="form-control input-sm" style="display:inline-block; width:auto; margin-left:6px;">
              <option value="10">10</option>
              <option value="20" selected>20</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </label>
        </div>

        <div class="context-toolbar" aria-label="Context filters">
          <div class="context-filter context-filter-search">
            <label for="contextSearch">Search contexts</label>
            <div class="context-search-wrap">
              <i class="fa fa-search"></i>
              <input id="contextSearch" type="search" class="form-control" placeholder="Key, value, description, or owner">
            </div>
          </div>
          <div class="context-filter">
            <label for="contextEnvironmentFilter">Environment</label>
            <select id="contextEnvironmentFilter" class="form-control">
              <option value="all">All environments</option>
              <?php foreach ($filterEnvironmentNames as $environmentName) { ?>
                <option value="<?php echo html_escape($environmentName); ?>"><?php echo html_escape($environmentName); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="context-filter">
            <label for="contextProjectFilter">Project</label>
            <select id="contextProjectFilter" class="form-control">
              <option value="">All projects</option>
              <?php foreach ($projectNames as $projectName) { ?>
                <option value="<?php echo html_escape($projectName); ?>"><?php echo html_escape($projectName); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="context-filter">
            <label for="contextStatusFilter">Status</label>
            <select id="contextStatusFilter" class="form-control">
              <option value="">Any status</option>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
          <div class="context-filter">
            <label for="contextEncryptionFilter">Protection</label>
            <select id="contextEncryptionFilter" class="form-control">
              <option value="">Any type</option>
              <option value="Encrypted">Encrypted</option>
              <option value="Standard">Standard</option>
            </select>
          </div>
          <button id="contextResetFilters" type="button" class="btn btn-default context-filter-reset"><i class="fa fa-undo"></i> Reset</button>
        </div>

        <div class="context-table-wrap">
          <table id="contextsTable" class="table table-hover context-table">
            <thead>
              <tr>
                <th>Context</th>
                <th>Value</th>
                <th>Environment</th>
                <th>Project</th>
                <th>Status</th>
                <th>Protection</th>
                <th>Updated</th>
                <th>Owner</th>
                <?php if ($role != 1) { ?><th class="context-actions-column">Actions</th><?php } ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($contextRows as $record) {
                $createdOn = !empty($record->CreatedOn) ? date('Y-m-d H:i', strtotime($record->CreatedOn)) : '';
                $modifiedOn = !empty($record->ModifiedOn) ? date('Y-m-d H:i', strtotime($record->ModifiedOn)) : '';
                $updatedOn = $modifiedOn !== '' ? $modifiedOn : $createdOn;
                $owner = !empty($record->ModifiedBy) ? $record->ModifiedBy : $record->CreatedBy;
              ?>
                <tr>
                  <td>
                    <span class="context-key"><?php echo html_escape($record->ContextKey); ?></span>
                    <span class="context-row-meta">#<?php echo (int) $record->Id; ?> &middot; Created <?php echo html_escape($createdOn); ?></span>
                    <?php if (trim((string) $record->Description) !== '') { ?><span class="context-description" title="<?php echo html_escape($record->Description); ?>"><?php echo html_escape($record->Description); ?></span><?php } ?>
                  </td>
                  <td>
                    <?php if ((int) $record->isEncrypted === 1) { ?>
                      <span class="context-secret-value" aria-label="Encrypted value">********</span>
                    <?php } else { ?>
                      <code class="context-value-preview" title="<?php echo html_escape($record->ContextValue); ?>"><?php echo html_escape($record->ContextValue); ?></code>
                    <?php } ?>
                  </td>
                  <td><span class="context-badge context-badge-environment"><?php echo html_escape($record->Environment); ?></span></td>
                  <td><?php echo html_escape($record->ProjectName); ?></td>
                  <td data-order="<?php echo (int) $record->IsActive; ?>"><span class="context-badge <?php echo (int) $record->IsActive === 1 ? 'context-badge-active' : 'context-badge-inactive'; ?>"><?php echo (int) $record->IsActive === 1 ? 'Active' : 'Inactive'; ?></span></td>
                  <td><?php if ((int) $record->isEncrypted === 1) { ?><span class="context-badge context-badge-secret"><i class="fa fa-lock"></i> Encrypted</span><?php } else { ?><span class="context-row-meta">Standard</span><?php } ?></td>
                  <td data-order="<?php echo html_escape($updatedOn); ?>"><?php echo html_escape($updatedOn); ?><?php if ($modifiedOn === '') { ?><span class="context-row-meta">Created</span><?php } ?></td>
                  <td><?php echo html_escape($owner); ?><?php if (!empty($record->ModifiedBy)) { ?><span class="context-row-meta">Last editor</span><?php } ?></td>
                  <?php if ($role != 1) { ?>
                    <td class="text-nowrap">
                      <a class="btn btn-sm btn-default" href="<?php echo base_url().'Context/editContext/'.(int) $record->Id.'?environment='.rawurlencode($selectedEnvironment); ?>" title="Edit <?php echo html_escape($record->ContextKey); ?>"><i class="fa fa-pencil"></i></a>
                      <button class="btn btn-sm btn-danger delete-context" type="button" data-context-id="<?php echo (int) $record->Id; ?>" data-context-key="<?php echo html_escape($record->ContextKey); ?>" title="Delete <?php echo html_escape($record->ContextKey); ?>"><i class="fa fa-trash"></i></button>
                    </td>
                  <?php } ?>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
window.contextDetailsConfig = {
  deleteUrl: <?php echo json_encode(base_url().'Context/deleteContext?environment='.rawurlencode($selectedEnvironment)); ?>,
  baseUrl: <?php echo json_encode(base_url().'Context/contextDetails'); ?>,
  selectedEnvironment: <?php echo json_encode(isset($selectedEnvironment) ? $selectedEnvironment : 'ALL'); ?>
};
</script>
<script src="<?php echo base_url(); ?>assets/js/context-details.js?v=4"></script>
