<?php
/**
 * Workspace runtimes: dev container environments projects develop and run in
 * (controllers/WorkspaceRuntimes.php, doc/jobseeker/Architecture/workspace-runtimes.md).
 * The catalog, a gallery of templates to start from, and the editors deployed
 * from runtimes. A runtime's files are edited in a VS Code-style editor
 * (assets/js/code-editor.js) next to the Dockerfile and devcontainer.json it
 * generates.
 */
$runtimeKinds = isset($runtimeKinds) ? $runtimeKinds : array();
$pythonVersions = isset($pythonVersions) ? $pythonVersions : array();
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/code-editor.css?v=1">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/workspace-runtimes.css?v=1">

<div class="content-wrapper rt-page">
  <section class="content-header">
    <h1>Workspace Runtimes <small>Dev container environments projects develop and run in</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Workspace Runtimes</li>
    </ol>
  </section>

  <section class="content">
    <div class="rt-hero">
      <div class="rt-hero-text">
        <span class="rt-hero-eyebrow"><i class="fa fa-cube"></i> Dev containers</span>
        <h2>One environment, from the editor to the scheduled job</h2>
        <p>A runtime is a dev container: an image with the Python, Conda environment or system libraries a project needs, the VS Code
          extensions and features that go with it, and the ports its apps use. Choose one for a project under <strong>VS Code</strong> in the sidebar,
          just for you or shared by the team. Docker jobs run the same image, and any runtime downloads as a <code>.devcontainer</code>
          for VS Code on your machine or Codespaces.</p>
        <div class="rt-hero-actions">
          <button type="button" class="btn rt-btn-primary" id="runtimeNew"<?php echo empty($runtimesEnabled) ? ' disabled' : ''; ?>><i class="fa fa-plus"></i> New runtime</button>
          <button type="button" class="btn rt-btn-ghost" data-rt-tab="templates"><i class="fa fa-th-large"></i> Browse templates</button>
          <button type="button" class="btn rt-btn-ghost" id="runtimeReclaim" title="Remove runtime images nothing uses any more" hidden><i class="fa fa-eraser"></i> Reclaim images</button>
          <button type="button" class="btn rt-btn-ghost" id="runtimeRefresh" title="Refresh"><i class="fa fa-refresh"></i></button>
        </div>
      </div>
      <div class="rt-hero-stats" aria-live="polite">
        <div class="rt-stat"><span class="rt-stat-value" id="rtStatRuntimes">–</span><span class="rt-stat-label">runtimes</span></div>
        <div class="rt-stat"><span class="rt-stat-value" id="rtStatBuilt">–</span><span class="rt-stat-label">built</span></div>
        <div class="rt-stat"><span class="rt-stat-value" id="rtStatRunning">–</span><span class="rt-stat-label">editors running</span></div>
        <div class="rt-stat"><span class="rt-stat-value" id="rtStatTemplates">–</span><span class="rt-stat-label">templates</span></div>
      </div>
    </div>

    <div id="runtimeEngineAlert" hidden></div>

    <nav class="rt-tabs" role="tablist" aria-label="Runtimes">
      <button type="button" role="tab" class="rt-tab is-active" data-rt-tab="catalog" aria-selected="true"><i class="fa fa-cubes"></i> Catalog <span class="rt-tab-count" id="rtCountCatalog"></span></button>
      <button type="button" role="tab" class="rt-tab" data-rt-tab="templates" aria-selected="false"><i class="fa fa-th-large"></i> Templates <span class="rt-tab-count" id="rtCountTemplates"></span></button>
      <button type="button" role="tab" class="rt-tab" data-rt-tab="deployments" aria-selected="false"><i class="fa fa-desktop"></i> Deployments <span class="rt-tab-count" id="rtCountDeployments"></span></button>
    </nav>

    <section class="rt-panel" data-rt-panel="catalog">
      <div class="rt-grid" id="runtimeCatalog"><div class="rt-empty"><i class="fa fa-spinner fa-spin"></i> Loading runtimes…</div></div>
    </section>

    <section class="rt-panel" data-rt-panel="templates" hidden>
      <div class="rt-filters">
        <div class="rt-chips" id="rtCategoryChips" role="group" aria-label="Categories"></div>
        <label class="rt-search"><i class="fa fa-search" aria-hidden="true"></i><span class="sr-only">Find a template</span><input type="search" id="rtTemplateSearch" placeholder="pandas, kafka, fastapi…" autocomplete="off"></label>
      </div>
      <div class="rt-grid" id="runtimeTemplates"></div>
    </section>

    <section class="rt-panel" data-rt-panel="deployments" hidden>
      <p class="rt-panel-note" id="runtimeIdleNote"></p>
      <div class="rt-table-wrap">
        <table class="table rt-table">
          <thead><tr><th>Project</th><th>For</th><th>Runtime</th><th>State</th><th>Last opened</th><th class="text-right">Actions</th></tr></thead>
          <tbody id="runtimeDeployments"><tr><td colspan="6" class="rt-empty">No editor has been deployed yet.</td></tr></tbody>
        </table>
      </div>
    </section>
  </section>
</div>

<div class="modal fade rt-editor-modal" id="runtimeEditor" tabindex="-1" role="dialog" aria-labelledby="runtimeEditorTitle">
  <div class="modal-dialog" role="document">
    <form class="modal-content" id="runtimeEditorForm" novalidate>
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="runtimeEditorTitle">New runtime</h4>
        <span class="rt-editor-subtitle" id="runtimeEditorSubtitle">A dev container: what it installs on the left, the files it becomes on the right.</span>
      </div>
      <div class="modal-body">
        <input type="hidden" name="original_key" id="runtimeOriginalKey">
        <input type="hidden" name="template" id="runtimeTemplate">
        <input type="hidden" name="system_packages" id="runtimeSystemPackages">
        <input type="hidden" name="python_packages" id="runtimePythonPackages">
        <input type="hidden" name="environment_yml" id="runtimeEnvironment">
        <input type="hidden" name="dockerfile" id="runtimeDockerfile">
        <div class="rt-editor-layout">
          <div class="rt-editor-form">
            <div class="row">
              <div class="col-sm-7 form-group">
                <label for="runtimeName">Name</label>
                <input type="text" class="form-control" id="runtimeName" name="name" maxlength="100" required placeholder="e.g. Geo Python 3.12">
              </div>
              <div class="col-sm-5 form-group">
                <label for="runtimeKey">Key <small class="text-muted">image name</small></label>
                <input type="text" class="form-control rt-mono" id="runtimeKey" name="key" maxlength="40" spellcheck="false" placeholder="from the name">
              </div>
            </div>
            <div class="form-group">
              <label for="runtimeDescription">Description</label>
              <input type="text" class="form-control" id="runtimeDescription" name="description" maxlength="500" placeholder="What projects on it get">
            </div>
            <div class="rt-kinds" id="runtimeKinds" role="radiogroup" aria-label="Kind">
              <?php foreach ($runtimeKinds as $kind => $info) { ?>
              <label class="rt-kind" data-kind="<?php echo html_escape($kind); ?>" title="<?php echo html_escape($info['help']); ?>">
                <input type="radio" name="kind" value="<?php echo html_escape($kind); ?>">
                <i class="fa <?php echo html_escape($info['icon']); ?>"></i> <?php echo html_escape($info['label']); ?>
              </label>
              <?php } ?>
            </div>
            <p class="rt-help" id="runtimeKindHelp"></p>
            <div class="row" data-runtime-kind="python conda">
              <div class="col-sm-4 form-group" data-runtime-kind="python">
                <label for="runtimePythonVersion">Python</label>
                <select class="form-control" id="runtimePythonVersion" name="python_version">
                  <?php foreach ($pythonVersions as $version) { ?>
                  <option value="<?php echo html_escape($version); ?>"><?php echo html_escape($version); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="col-sm-8 form-group">
                <label for="runtimeSystemPackagesInput">System packages <small class="text-muted">apt</small></label>
                <input type="text" class="form-control rt-mono" id="runtimeSystemPackagesInput" spellcheck="false" placeholder="build-essential libpq-dev">
              </div>
            </div>
            <div class="form-group">
              <label for="runtimeExtensions">VS Code extensions <small class="text-muted">from open-vsx.org, one per line</small></label>
              <textarea class="form-control rt-mono" id="runtimeExtensions" name="extensions" rows="2" spellcheck="false" placeholder="ms-toolsai.jupyter&#10;mtxr.sqltools"></textarea>
            </div>
            <div class="form-group">
              <label for="runtimeFeatures">Dev container features <small class="text-muted">as in devcontainer.json</small></label>
              <textarea class="form-control rt-mono" id="runtimeFeatures" name="features" rows="2" spellcheck="false" placeholder='{"ghcr.io/devcontainers/features/node:1": {"version": "lts"}}'></textarea>
            </div>
            <div class="row">
              <div class="col-sm-5 form-group">
                <label for="runtimePorts">Forwarded ports</label>
                <input type="text" class="form-control rt-mono" id="runtimePorts" name="ports" spellcheck="false" placeholder="8000, 8501">
              </div>
              <div class="col-sm-7 form-group">
                <label for="runtimePostCreate">Post-create command</label>
                <input type="text" class="form-control rt-mono" id="runtimePostCreate" name="post_create" spellcheck="false" placeholder="pip install -e .">
              </div>
            </div>
            <div class="form-group">
              <label for="runtimeEnv">Environment <small class="text-muted">NAME=value lines</small></label>
              <textarea class="form-control rt-mono" id="runtimeEnv" name="env" rows="2" spellcheck="false" placeholder="TZ=UTC"></textarea>
            </div>
            <div class="rt-editor-errors" id="runtimeEditorError" role="alert" hidden></div>
          </div>
          <div class="rt-editor-code">
            <div id="runtimeCodeEditor"></div>
            <p class="rt-help rt-code-help" id="runtimeCodeHelp"></p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a class="btn btn-default pull-left" id="runtimeEditorDownload" href="#" hidden><i class="fa fa-download"></i> .devcontainer</a>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-default" id="runtimeEditorSave" data-build="0"><i class="fa fa-save"></i> Save</button>
        <button type="submit" class="btn rt-btn-primary" id="runtimeEditorSaveBuild" data-build="1"><i class="fa fa-gavel"></i> Save &amp; build</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="runtimeReclaimModal" tabindex="-1" role="dialog" aria-labelledby="runtimeReclaimTitle">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="runtimeReclaimTitle">Reclaim runtime images</h4>
        <span class="rt-help">Kept: every catalog runtime's current images, anything a container runs, and runtime images saved jobs name.</span>
      </div>
      <div class="modal-body" id="runtimeReclaimBody"><p class="rt-empty"><i class="fa fa-spinner fa-spin"></i> Looking for unused images…</p></div>
      <div class="modal-footer">
        <span class="rt-help pull-left" id="runtimeReclaimTotal"></span>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="runtimeReclaimConfirm" disabled><i class="fa fa-eraser"></i> Remove selected</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="runtimeLogModal" tabindex="-1" role="dialog" aria-labelledby="runtimeLogTitle">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content rt-log-modal">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="runtimeLogTitle">Build log</h4>
        <span class="rt-help" id="runtimeLogStatus"></span>
      </div>
      <div class="modal-body"><pre class="rt-log" id="runtimeLogText">Loading…</pre></div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<script src="<?php echo base_url(); ?>assets/js/code-editor.js?v=1"></script>
<script>
  window.jobseekerRuntimes = <?php echo json_encode(array(
    'urls' => array(
      'catalog' => base_url().'workspace-runtimes/catalog',
      'save' => base_url().'workspace-runtimes/save',
      'remove' => base_url().'workspace-runtimes/delete',
      'build' => base_url().'workspace-runtimes/build',
      'log' => base_url().'workspace-runtimes/log',
      'preview' => base_url().'workspace-runtimes/preview',
      'devcontainer' => base_url().'workspace-runtimes/devcontainer',
      'deployment' => base_url().'workspace-runtimes/deployment',
      'deploymentLogs' => base_url().'workspace-runtimes/deployment-logs',
      'reclaim' => base_url().'workspace-runtimes/reclaim'
    ),
    'kinds' => $runtimeKinds,
    'enabled' => ! empty($runtimesEnabled)
  )); ?>;
</script>
<script src="<?php echo base_url(); ?>assets/js/workspace-runtimes.js?v=1"></script>
