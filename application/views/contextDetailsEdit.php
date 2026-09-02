<?php $context = $list[0]; ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/context-details.css?v=3">

<div class="content-wrapper context-page">
  <section class="content-header">
    <h1>
      <i class="fa fa-sliders"></i> Context Settings <b>Edit Context</b>
      <small>Update an environment-specific runtime variable</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="<?php echo base_url(); ?>Context/contextDetails">Context Details</a></li>
      <li class="active">Edit</li>
    </ol>
  </section>

  <section class="content">
    <div class="context-shell">
      <div class="context-page-heading">
        <div>
          <h2>Edit <?php echo html_escape($context->ContextKey); ?></h2>
          <p>Changes will be available to jobs using this project and environment.</p>
        </div>
        <span class="context-scope-pill"><i class="fa fa-globe"></i> <?php echo html_escape($context->Environment); ?></span>
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
            <span class="context-card-title-icon"><i class="fa fa-pencil"></i></span>
            <div>
              <h3>Context configuration</h3>
              <p>Review the scope carefully before saving.</p>
            </div>
          </div>
          <a href="<?php echo base_url(); ?>Context/contextDetails" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to contexts</a>
        </div>

        <form action="<?php echo base_url(); ?>Context/editContextUpdate?environment=<?php echo rawurlencode(isset($selectedEnvironment) ? $selectedEnvironment : 'ALL'); ?>" method="POST" id="contextEditForm">
          <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
          <input type="hidden" name="ContextId" value="<?php echo (int) $context->Id; ?>">
          <div class="box-body context-card-body">
            <div class="context-edit-meta">
              <div><strong>Context ID</strong>#<?php echo (int) $context->Id; ?></div>
              <div><strong>Created</strong><?php echo js_time($context->CreatedOn, array('format' => 'Y-m-d H:i', 'empty' => 'Unknown')); ?></div>
              <div><strong>Created by</strong><?php echo !empty($context->CreatedBy) ? html_escape($context->CreatedBy) : 'Unknown'; ?></div>
              <div><strong>Last modified</strong><?php echo js_time($context->ModifiedOn, array('format' => 'Y-m-d H:i', 'empty' => 'Never')); ?></div>
            </div>

            <div class="context-form-grid">
              <div class="context-field context-field-key">
                <label for="contextKey">Context key <span class="text-danger">*</span></label>
                <input id="contextKey" type="text" name="contextKey" value="<?php echo html_escape($context->ContextKey); ?>" class="form-control" placeholder="e.g. API_BASE_URL" maxlength="1000" autocomplete="off" required>
                <span class="context-help">The key must remain unique within its project and environment.</span>
              </div>
              <div class="context-field">
                <label for="projectName">Project <span class="text-danger">*</span></label>
                <select id="projectName" class="form-control" name="projectName" required>
                  <?php foreach ((array) $listProjects as $project) { ?>
                    <option value="<?php echo html_escape($project->ProjectName); ?>" <?php echo $project->ProjectName == $context->ProjectName ? 'selected' : ''; ?>><?php echo html_escape($project->ProjectName); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="context-field">
                <label for="environmentName">Environment <span class="text-danger">*</span></label>
                <select id="environmentName" class="form-control" name="environmentName" required>
                  <?php foreach ((array) $listEnvironments as $environment) { ?>
                    <option value="<?php echo html_escape($environment->Environment); ?>" <?php echo $environment->Environment == $context->Environment ? 'selected' : ''; ?>><?php echo html_escape($environment->Environment); ?></option>
                  <?php } ?>
                </select>
              </div>

              <div class="context-field context-field-value">
                <label for="contextValue">Context value <span class="text-danger">*</span></label>
                <div class="input-group context-value-group">
                  <input id="contextValue" type="text" name="contextValue" value="<?php echo html_escape($context->ContextValue); ?>" class="form-control" placeholder="Enter the runtime value" maxlength="1000" autocomplete="new-password" required>
                  <span class="input-group-btn">
                    <button id="toggleContextValue" class="btn btn-default" type="button" aria-pressed="false"><i class="fa fa-eye"></i> Show</button>
                  </span>
                </div>
                <span class="context-help">Encrypted values start hidden and remain masked in the context list.</span>
              </div>
              <div class="context-field context-field-description">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" placeholder="Where is this value used?" maxlength="2000"><?php echo html_escape($context->Description); ?></textarea>
              </div>

              <div class="context-field">
                <label for="active">Availability</label>
                <select id="active" class="form-control" name="active">
                  <option value="1" <?php echo (int) $context->IsActive === 1 ? 'selected' : ''; ?>>Active</option>
                  <option value="0" <?php echo (int) $context->IsActive === 0 ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <span class="context-help">Inactive contexts remain stored but are unavailable to jobs.</span>
              </div>
              <div class="context-field">
                <label for="encrypted">Value protection</label>
                <select id="encrypted" class="form-control" name="encrypted">
                  <option value="0" <?php echo (int) $context->isEncrypted === 0 ? 'selected' : ''; ?>>Standard value</option>
                  <option value="1" <?php echo (int) $context->isEncrypted === 1 ? 'selected' : ''; ?>>Encrypted secret</option>
                </select>
              </div>
            </div>

          </div>
          <div class="box-footer context-form-footer">
            <span class="context-form-note"><i class="fa fa-shield"></i> Moving a key to another scope can affect running jobs.</span>
            <div class="context-form-actions">
              <a href="<?php echo base_url(); ?>Context/contextDetails" class="btn btn-default">Cancel</a>
              <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save changes</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

<script src="<?php echo base_url(); ?>assets/js/context-details.js?v=2"></script>
