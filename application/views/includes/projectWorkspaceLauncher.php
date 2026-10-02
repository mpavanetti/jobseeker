<?php
/**
 * The sidebar's VS Code launcher: pick (or create) a project and open your
 * own workspace of it. See ProjectWorkspaceTrait for what opening does.
 */
$vscodeMark = '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path fill="currentColor" d="M23.15 2.587 18.21.21a1.494 1.494 0 0 0-1.705.29l-9.46 8.63-4.12-3.128a.999.999 0 0 0-1.276.057L.327 7.261A1 1 0 0 0 .326 8.74L3.899 12 .326 15.26a1 1 0 0 0 .001 1.479L1.65 17.94a.999.999 0 0 0 1.276.057l4.12-3.128 9.46 8.63a1.492 1.492 0 0 0 1.704.29l4.942-2.377A1.5 1.5 0 0 0 24 20.06V3.939a1.5 1.5 0 0 0-.85-1.352zm-5.146 14.861L10.826 12l7.178-5.448v10.896z"/></svg>';
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/project-workspace.css?v=5">
<div class="modal fade project-launcher" id="projectWorkspaceLauncher" tabindex="-1" role="dialog" aria-labelledby="projectWorkspaceLauncherTitle">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="projectWorkspaceLauncherTitle"><span class="project-launcher-mark"><?php echo $vscodeMark; ?></span> Open a project in VS Code</h4>
        <p class="project-launcher-subtitle">Develop first, schedule later. Each job is a folder under <code>jobs/</code>; when one is ready, Job Creation turns it into a job.</p>
      </div>
      <div class="modal-body">
        <div class="project-launcher-columns">
          <div class="project-launcher-projects">
            <div class="project-launcher-toolbar">
              <label class="project-launcher-search"><i class="fa fa-search" aria-hidden="true"></i><span class="sr-only">Find a project</span><input type="search" id="projectLauncherSearch" placeholder="Find a project" autocomplete="off"></label>
              <button type="button" class="btn btn-default btn-sm" id="projectLauncherNew"><i class="fa fa-plus"></i> New</button>
            </div>
            <div id="projectLauncherList" class="project-launcher-list" role="listbox" aria-label="Projects"><div class="project-launcher-empty"><i class="fa fa-spinner fa-spin"></i> Loading projects...</div></div>
          </div>
          <div class="project-launcher-pane">
            <form id="projectLauncherCreate" class="project-launcher-create" hidden>
              <h5>New project</h5>
              <div class="form-group">
                <label for="projectLauncherName">Name</label>
                <input type="text" class="form-control" id="projectLauncherName" maxlength="255" autocomplete="off" placeholder="e.g. Customer Analytics" required>
              </div>
              <fieldset class="form-group">
                <legend>Type</legend>
                <div class="project-launcher-types" id="projectLauncherTypes"></div>
              </fieldset>
              <div class="form-group">
                <label for="projectLauncherRepository">Git repository <small class="text-muted">optional, recommended</small></label>
                <input type="text" class="form-control" id="projectLauncherRepository" maxlength="1000" autocomplete="off" spellcheck="false" placeholder="https://github.com/org/customer-analytics.git">
                <p class="help-block">With Git, everyone gets a working copy and branch of their own. Without it, the project is one folder everyone edits.</p>
              </div>
              <div class="project-launcher-create-actions">
                <button type="button" class="btn btn-default btn-sm" id="projectLauncherCreateCancel">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="projectLauncherCreateSubmit"><i class="fa fa-plus"></i> Create project</button>
              </div>
            </form>
            <div id="projectLauncherDetail" class="project-launcher-detail">
              <div class="project-launcher-placeholder"><i class="fa fa-folder-open-o"></i><p>Choose a project, or create one.</p></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <span class="project-launcher-environment">Branches for <strong id="projectLauncherEnvironment">DEV</strong></span>
        <a class="btn btn-default" id="projectLauncherCreateJob" href="<?php echo base_url(); ?>JobCreation" hidden><i class="fa fa-plus-square"></i> Create a job</a>
        <a class="btn btn-default" id="projectLauncherHop" href="<?php echo base_url(); ?>hop" hidden><i class="fa fa-random"></i> Open Apache Hop</a>
        <button type="button" class="btn project-launcher-open" id="projectLauncherOpen" disabled><span class="project-launcher-mark"><?php echo $vscodeMark; ?></span> Open in VS Code</button>
      </div>
    </div>
  </div>
</div>
<script>
  window.jobseekerProjectLauncher = <?php echo json_encode(array(
    'listUrl' => base_url().'jobCreation/projectWorkspaces',
    'openUrl' => base_url().'jobCreation/projectWorkspaceOpen',
    'createUrl' => base_url().'jobCreation/projectWorkspaceCreateProject',
    'statusUrl' => base_url().'jobCreation/inlinePythonExternalStatus',
    'jobCreationUrl' => base_url().'JobCreation',
    'projectDetailsUrl' => base_url().'Context/editProject/',
    'gitProfileUrl' => base_url().'profile/git',
    'runtimeUrl' => base_url().'jobCreation/projectWorkspaceRuntime',
    'runtimeSaveUrl' => base_url().'jobCreation/projectWorkspaceRuntimeSave',
    'runtimeStopUrl' => base_url().'jobCreation/projectWorkspaceRuntimeStop',
    'devcontainerStarterUrl' => base_url().'jobCreation/projectWorkspaceDevcontainerStarter',
    'runtimeRebuildUrl' => base_url().'jobCreation/projectWorkspaceRuntimeRebuild',
    'runtimesUrl' => base_url().'workspace-runtimes'
  )); ?>;
</script>
<script src="<?php echo base_url(); ?>assets/js/project-workspace.js?v=10"></script>
