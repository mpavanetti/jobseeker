<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/context-details.css?v=3">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/settings-details.css?v=1">

<div class="content-wrapper context-page settings-page">
  <section class="content-header">
    <h1><i class="fa fa-folder-open"></i> Context Settings <b>Edit Project</b><small>Update a project scope</small></h1>
    <ol class="breadcrumb"><li><a href="<?php echo base_url(); ?>"><i class="fa fa-dashboard"></i> Home</a></li><li><a href="<?php echo base_url(); ?>Context/projectDetails">Project Details</a></li><li class="active">Edit</li></ol>
  </section>

  <section class="content">
    <div class="context-shell">
      <div class="context-page-heading"><div><h2>Edit <?php echo html_escape($project->ProjectName); ?></h2><p>Changes apply wherever this project is used as a context scope.</p></div></div>

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
          <div class="context-card-title"><span class="context-card-title-icon"><i class="fa fa-pencil"></i></span><div><h3>Project configuration</h3><p>Review the scope before saving.</p></div></div>
          <a href="<?php echo base_url(); ?>Context/projectDetails" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to projects</a>
        </div>
        <form action="<?php echo base_url(); ?>Context/editProjectUpdate" method="POST" id="projectEditForm">
          <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
          <input type="hidden" name="Id" value="<?php echo (int) $project->Id; ?>">
          <div class="box-body context-card-body">
            <div class="settings-edit-meta">
              <div><strong>Project ID</strong>#<?php echo (int) $project->Id; ?></div>
              <div><strong>Created</strong><?php echo js_time($project->CreatedOn, array('format' => 'Y-m-d H:i', 'empty' => 'Unknown')); ?></div>
              <div><strong>Last modified</strong><?php echo js_time($project->ModifiedOn, array('format' => 'Y-m-d H:i', 'empty' => 'Never')); ?></div>
            </div>
            <div class="context-form-grid">
              <div class="context-field settings-field-name"><label for="name">Project name <span class="text-danger">*</span></label><input id="name" type="text" name="name" value="<?php echo html_escape($project->ProjectName); ?>" class="form-control" maxlength="1000" autocomplete="off" required><span class="context-help">The name must remain unique.</span></div>
              <div class="context-field settings-field-wide"><label for="gitpath">Git path</label><input id="gitpath" type="text" name="gitpath" value="<?php echo html_escape($project->GitPath); ?>" class="form-control" placeholder="https://github.com/organization/repository.git" maxlength="2000" autocomplete="off"><span class="context-help">Optional source repository or local Git path.</span></div>
              <div class="context-field settings-field-status"><label for="active">Status</label><select id="active" class="form-control" name="active"><option value="1" <?php echo (int) $project->IsActive === 1 ? 'selected' : ''; ?>>Active</option><option value="0" <?php echo (int) $project->IsActive === 0 ? 'selected' : ''; ?>>Inactive</option></select></div>
            </div>
          </div>
          <div class="box-footer context-form-footer"><span class="context-form-note"><i class="fa fa-info-circle"></i> Existing contexts keep their project association.</span><div class="context-form-actions"><a href="<?php echo base_url(); ?>Context/projectDetails" class="btn btn-default">Cancel</a><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save changes</button></div></div>
        </form>
      </div>
    </div>
  </section>
</div>
