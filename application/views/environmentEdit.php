<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/context-details.css?v=3">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/settings-details.css?v=1">

<div class="content-wrapper context-page settings-page">
  <section class="content-header">
    <h1><i class="fa fa-globe"></i> Context Settings <b>Edit Environment</b><small>Update a runtime environment scope</small></h1>
    <ol class="breadcrumb"><li><a href="<?php echo base_url(); ?>"><i class="fa fa-dashboard"></i> Home</a></li><li><a href="<?php echo base_url(); ?>Context/environment">Environment Details</a></li><li class="active">Edit</li></ol>
  </section>

  <section class="content">
    <div class="context-shell">
      <div class="context-page-heading"><div><h2>Edit <?php echo html_escape($environment->Environment); ?></h2><p>Changes apply to context, job, and promotion environment selectors.</p></div></div>

      <?php
        $this->load->helper('form');
        $error = $this->session->flashdata('error');
        $success = $this->session->flashdata('success');
      ?>
      <?php if ($error) { ?><div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><?php echo html_escape($error); ?></div><?php } ?>
      <?php if ($success) { ?><div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><?php echo html_escape($success); ?></div><?php } ?>
      <?php echo validation_errors('<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>', '</div>'); ?>

      <div class="box box-primary context-card animated fadeIn">
        <div class="box-header with-border context-card-header">
          <div class="context-card-title"><span class="context-card-title-icon"><i class="fa fa-pencil"></i></span><div><h3>Environment configuration</h3><p>Review the runtime scope before saving.</p></div></div>
          <a href="<?php echo base_url(); ?>Context/environment" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to environments</a>
        </div>
        <form action="<?php echo base_url(); ?>Context/editEnvironmentUpdate" method="POST" id="environmentEditForm">
          <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
          <input type="hidden" name="Id" value="<?php echo (int) $environment->Id; ?>">
          <div class="box-body context-card-body">
            <div class="settings-edit-meta">
              <div><strong>Environment ID</strong>#<?php echo (int) $environment->Id; ?></div>
              <div><strong>Created</strong><?php echo !empty($environment->CreatedOn) ? html_escape(date('Y-m-d H:i', strtotime($environment->CreatedOn))) : 'Unknown'; ?></div>
              <div><strong>Last modified</strong><?php echo !empty($environment->ModifiedOn) ? html_escape(date('Y-m-d H:i', strtotime($environment->ModifiedOn))) : 'Never'; ?></div>
            </div>
            <div class="context-form-grid">
              <div class="context-field settings-field-name"><label for="name">Environment name <span class="text-danger">*</span></label><input id="name" type="text" name="name" value="<?php echo html_escape($environment->Environment); ?>" class="form-control" maxlength="100" autocomplete="off" required><span class="context-help">Use a stable deployment-stage name.</span></div>
              <div class="context-field settings-field-wide"><label for="description">Description</label><input id="description" type="text" name="description" value="<?php echo html_escape($environment->Description); ?>" class="form-control" placeholder="Purpose, ownership, or deployment notes" maxlength="2000" autocomplete="off"></div>
              <div class="context-field settings-field-status"><label for="active">Status</label><select id="active" class="form-control" name="active"><option value="1" <?php echo (int) $environment->IsActive === 1 ? 'selected' : ''; ?>>Active</option><option value="0" <?php echo (int) $environment->IsActive === 0 ? 'selected' : ''; ?>>Inactive</option></select></div>
            </div>
          </div>
          <div class="box-footer context-form-footer"><span class="context-form-note"><i class="fa fa-exclamation-triangle"></i> Renaming an environment can affect jobs and contexts that reference it.</span><div class="context-form-actions"><a href="<?php echo base_url(); ?>Context/environment" class="btn btn-default">Cancel</a><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save changes</button></div></div>
        </form>
      </div>
    </div>
  </section>
</div>
