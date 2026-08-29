<?php
$stats = isset($statistics) && $statistics ? $statistics : (object) array('total' => 0, 'active' => 0, 'inputs' => 0, 'outputs' => 0, 'uploaded' => 0);
$error = $this->session->flashdata('error');
$success = $this->session->flashdata('success');
$assetPayload = array();

foreach ((array) $assets as $asset) {
    $options = json_decode($asset->options_json, TRUE);
    $assetPayload[] = array(
        'id' => (int) $asset->id,
        'key' => $asset->asset_key,
        'name' => $asset->name,
        'direction' => $asset->direction,
        'format' => $asset->format,
        'environment' => $asset->environment,
        'job' => $asset->job_name,
        'file_name' => $asset->file_name,
        'description' => $asset->description,
        'required' => (int) $asset->is_required,
        'active' => (int) $asset->is_active,
        'options' => is_array($options) ? $options : array()
    );
}

function data_asset_size($bytes) {
    if ($bytes === NULL) return 'No file';
    $bytes = (float) $bytes;
    if ($bytes < 1024) return number_format($bytes, 0).' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1).' KB';
    return number_format($bytes / 1048576, 1).' MB';
}

function data_asset_role_label($direction) {
    if ($direction === 'input_output') return 'Input + output';
    return ucfirst($direction);
}

function data_asset_uri($asset) {
    $scope = $asset->job_name === '*' ? 'shared' : rawurlencode(str_replace('/', '~', $asset->job_name));
    return 'jobseeker://'.strtolower($asset->environment).'/'.$scope.'/'.$asset->asset_key;
}
?>

<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/data-assets.css?v=2">

<div class="content-wrapper data-assets-page">
  <section class="content-header">
    <h1><i class="fa fa-cubes"></i> Data Assets <small>Datasets, runtime inputs and generated outputs</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
      <li>Extract Transform Load</li>
      <li class="active">Data Assets</li>
    </ol>
  </section>

  <section class="content">
    <div class="container-fluid data-assets-shell">
      <?php if ($error) { ?>
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
          <i class="icon fa fa-ban"></i> <?php echo html_escape($error); ?>
        </div>
      <?php } ?>
      <?php if ($success) { ?>
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
          <i class="icon fa fa-check"></i> <?php echo html_escape($success); ?>
        </div>
      <?php } ?>

      <div class="row data-assets-stats">
        <div class="col-lg-3 col-sm-6">
          <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-cubes"></i></span>
            <div class="info-box-content"><span class="info-box-text">Registered Assets</span><span class="info-box-number"><?php echo (int) $stats->total; ?></span><small><?php echo (int) $stats->active; ?> active contracts</small></div>
          </div>
        </div>
        <div class="col-lg-3 col-sm-6">
          <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-sign-in"></i></span>
            <div class="info-box-content"><span class="info-box-text">Runtime Inputs</span><span class="info-box-number"><?php echo (int) $stats->inputs; ?></span><small>Seed files and upstream data</small></div>
          </div>
        </div>
        <div class="col-lg-3 col-sm-6">
          <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-sign-out"></i></span>
            <div class="info-box-content"><span class="info-box-text">Declared Outputs</span><span class="info-box-number"><?php echo (int) $stats->outputs; ?></span><small>Stable write destinations</small></div>
          </div>
        </div>
        <div class="col-lg-3 col-sm-6">
          <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-cloud-upload"></i></span>
            <div class="info-box-content"><span class="info-box-text">Versioned Files</span><span class="info-box-number"><?php echo (int) $stats->uploaded; ?></span><small>Checksum-tracked revisions</small></div>
          </div>
        </div>
      </div>

      <div class="callout callout-info data-assets-intro">
        <div class="data-assets-intro-icon"><i class="fa fa-exchange"></i></div>
        <div>
          <h4>One contract, every runtime</h4>
          <p>Register a named asset once, optionally seed it with a file, then resolve the same URI from Python, shell, Talend, or Docker. Environment and job scopes let a shared key point at the correct runtime file without hardcoded paths.</p>
        </div>
        <button id="showAssetForm" type="button" class="btn btn-primary"><i class="fa fa-plus"></i> Register Data Asset</button>
      </div>

      <div id="assetEditor" class="box box-primary data-asset-editor<?php echo validation_errors() ? '' : ' is-collapsed'; ?>">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-sliders"></i> <span id="assetEditorTitle">Register data asset</span></h3>
          <div class="box-tools pull-right">
            <button id="closeAssetEditor" type="button" class="btn btn-box-tool" title="Close"><i class="fa fa-times"></i></button>
          </div>
        </div>
        <?php echo form_open_multipart('data-assets/save?environment='.rawurlencode($initialEnvironment), array('id' => 'dataAssetForm')); ?>
          <input type="hidden" id="assetId" name="asset_id" value="0">
          <div class="box-body">
            <?php echo validation_errors('<div class="alert alert-danger">', '</div>'); ?>
            <div class="row">
              <div class="col-lg-8">
                <div class="data-assets-section-title"><span>1</span><div><strong>Contract identity</strong><small>The stable name jobs use instead of a filesystem path.</small></div></div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group"><label for="assetName">Display name</label><input id="assetName" name="name" class="form-control" maxlength="200" placeholder="Customer reference data" required></div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group"><label for="assetKey">Asset key</label><div class="input-group"><span class="input-group-addon"><i class="fa fa-link"></i></span><input id="assetKey" name="asset_key" class="form-control" maxlength="128" placeholder="customer-reference" pattern="[a-z0-9-]+" required></div><p class="help-block">Used by <code>js.asset("customer-reference")</code>.</p></div>
                  </div>
                </div>

                <div class="form-group">
                  <label>Runtime role</label>
                  <div class="data-role-picker" role="group" aria-label="Data asset role">
                    <label><input type="radio" name="direction" value="input" checked><span><i class="fa fa-sign-in"></i><strong>Input</strong><small>Read-only seed or upstream data</small></span></label>
                    <label><input type="radio" name="direction" value="output"><span><i class="fa fa-sign-out"></i><strong>Output</strong><small>Stable destination created by a job</small></span></label>
                    <label><input type="radio" name="direction" value="input_output"><span><i class="fa fa-exchange"></i><strong>Input + output</strong><small>Shared handoff between jobs</small></span></label>
                  </div>
                </div>

                <div class="data-assets-section-title"><span>2</span><div><strong>Runtime scope</strong><small>Exact environment and job matches win; ALL and Shared are fallbacks.</small></div></div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group"><label for="assetEnvironment">Environment</label><select id="assetEnvironment" name="environment" class="form-control"><option value="ALL">ALL — fallback for every environment</option><?php foreach ($environments as $environment) { $assetEnvironmentName = strtoupper($environment->Environment); ?><option value="<?php echo html_escape($assetEnvironmentName); ?>"><?php echo html_escape($assetEnvironmentName); ?></option><?php } ?></select></div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group"><label for="assetJobName">Job scope</label><input id="assetJobName" name="job_name" class="form-control" maxlength="200" placeholder="* (shared by all jobs)"><p class="help-block">Use <code>*</code> for a reusable catalog asset.</p></div>
                  </div>
                </div>

                <div class="data-assets-section-title"><span>3</span><div><strong>File contract</strong><small>Format options become metadata in the runtime catalog.</small></div></div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group"><label for="assetFormat">Format</label><select id="assetFormat" name="format" class="form-control"><?php foreach ($formats as $key => $format) { ?><option value="<?php echo html_escape($key); ?>"><?php echo html_escape($format['label']); ?></option><?php } ?></select></div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group"><label for="assetFileName">Runtime file name</label><input id="assetFileName" name="file_name" class="form-control" maxlength="255" placeholder="customers.csv"><p class="help-block">Optional when selecting a seed file.</p></div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group"><label for="assetFile">Seed / replacement file</label><input id="assetFile" name="asset_file" type="file" class="form-control"><p class="help-block">Maximum 100 MB. Replacements create a new version.</p></div>
                  </div>
                </div>

                <div id="delimitedOptions" class="well well-sm data-format-options">
                  <div class="row">
                    <div class="col-sm-4"><div class="form-group"><label for="assetDelimiter">Delimiter</label><select id="assetDelimiter" name="delimiter" class="form-control"><option value=",">Comma (,)</option><option value=";">Semicolon (;)</option><option value="|">Pipe (|)</option><option value="\t">Tab</option></select></div></div>
                    <div class="col-sm-4"><div class="form-group"><label for="assetEncoding">Encoding</label><select id="assetEncoding" name="encoding" class="form-control"><option>UTF-8</option><option>UTF-16</option><option>ISO-8859-1</option></select></div></div>
                    <div class="col-sm-4"><div class="form-group"><label>Header row</label><select id="assetHeader" name="has_header" class="form-control"><option value="1">First row contains columns</option><option value="0">No header row</option></select></div></div>
                  </div>
                </div>
                <div id="excelOptions" class="well well-sm data-format-options" style="display:none"><div class="form-group"><label for="assetSheet">Excel sheet</label><input id="assetSheet" name="sheet" class="form-control" maxlength="100" placeholder="First sheet when empty"></div></div>

                <div class="form-group"><label for="assetDescription">Description</label><textarea id="assetDescription" name="description" class="form-control" rows="3" maxlength="2000" placeholder="What this dataset contains, who produces it, and how consumers should use it."></textarea></div>
              </div>

              <div class="col-lg-4">
                <div class="box box-solid data-runtime-preview">
                  <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-code"></i> Runtime contract</h3></div>
                  <div class="box-body">
                    <span class="data-preview-label">Resolved URI</span>
                    <code id="assetUriPreview">jobseeker://all/shared/asset-key</code>
                    <hr>
                    <span class="data-preview-label">Python</span>
                    <pre>asset = js.asset("<span class="asset-key-example">asset-key</span>")
rows = asset.read()</pre>
                    <span class="data-preview-label">Shell</span>
                    <pre>jobseeker-asset "<span class="asset-key-example">asset-key</span>"</pre>
                    <p class="text-muted"><i class="fa fa-info-circle"></i> Shell jobs also receive <code>$JOBSEEKER_REPOSITORY_ROOT</code> and the JSON catalog path.</p>
                  </div>
                </div>
                <div class="data-asset-switches">
                  <input type="hidden" name="is_required" value="0">
                  <label><input id="assetRequired" type="checkbox" name="is_required" value="1" checked> <span><strong>Required at runtime</strong><small>Fail clearly when an input file is missing.</small></span></label>
                  <input type="hidden" name="is_active" value="0">
                  <label><input id="assetActive" type="checkbox" name="is_active" value="1" checked> <span><strong>Publish to catalog</strong><small>Inactive assets remain registered but cannot resolve.</small></span></label>
                </div>
              </div>
            </div>
          </div>
          <div class="box-footer data-asset-form-actions">
            <button id="resetAssetForm" type="button" class="btn btn-default"><i class="fa fa-undo"></i> Reset</button>
            <button type="submit" class="btn btn-primary"><i class="fa fa-cloud-upload"></i> <span id="saveAssetLabel">Publish Data Asset</span></button>
          </div>
        <?php echo form_close(); ?>
      </div>

      <div class="box box-primary data-assets-catalog">
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-list-alt"></i> Runtime Catalog</h3>
          <div class="box-tools pull-right"><span id="assetVisibleCount" class="label label-primary"><?php echo count($assets); ?> shown</span></div>
        </div>
        <div class="box-body data-assets-toolbar">
          <div class="row">
            <div class="col-md-5"><div class="input-group"><span class="input-group-addon"><i class="fa fa-search"></i></span><input id="assetSearch" class="form-control" placeholder="Search key, name, job, path or description"></div></div>
            <div class="col-md-2"><select id="assetEnvironmentFilter" class="form-control"><option value="ALL">All environments</option><?php foreach ($environments as $environment) { ?><option value="<?php echo html_escape(strtoupper($environment->Environment)); ?>"><?php echo html_escape(strtoupper($environment->Environment)); ?> + ALL fallback</option><?php } ?></select></div>
            <div class="col-md-2"><select id="assetDirectionFilter" class="form-control"><option value="">All roles</option><option value="input">Inputs</option><option value="output">Outputs</option><option value="input_output">Input + output</option></select></div>
            <div class="col-md-2"><select id="assetFormatFilter" class="form-control"><option value="">All formats</option><?php foreach ($formats as $key => $format) { ?><option value="<?php echo html_escape($key); ?>"><?php echo html_escape($format['label']); ?></option><?php } ?></select></div>
            <div class="col-md-1"><button id="clearAssetFilters" type="button" class="btn btn-default btn-block" title="Clear filters"><i class="fa fa-eraser"></i></button></div>
          </div>
        </div>
        <div class="table-responsive">
          <table id="dataAssetsTable" class="table table-hover data-assets-table">
            <thead><tr><th>Asset</th><th>Role & format</th><th>Scope</th><th>Runtime file</th><th>Revision</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($assets as $asset) {
                $options = json_decode($asset->options_json, TRUE);
                $isAvailable = (int) $asset->version > 0 || ((string) $asset->direction === 'output');
            ?>
              <tr class="data-asset-row<?php echo (int) $asset->is_active ? '' : ' is-inactive'; ?>" data-id="<?php echo (int) $asset->id; ?>" data-environment="<?php echo html_escape($asset->environment); ?>" data-direction="<?php echo html_escape($asset->direction); ?>" data-format="<?php echo html_escape($asset->format); ?>" data-search="<?php echo html_escape(strtolower($asset->asset_key.' '.$asset->name.' '.$asset->job_name.' '.$asset->storage_path.' '.$asset->description)); ?>">
                <td><div class="data-asset-identity"><span class="data-asset-icon"><i class="fa <?php echo $asset->direction === 'output' ? 'fa-sign-out' : ($asset->direction === 'input_output' ? 'fa-exchange' : 'fa-sign-in'); ?>"></i></span><div><strong><?php echo html_escape($asset->name); ?></strong><code><?php echo html_escape($asset->asset_key); ?></code><small title="<?php echo html_escape(data_asset_uri($asset)); ?>"><?php echo html_escape(data_asset_uri($asset)); ?></small></div></div></td>
                <td><span class="label data-role-label data-role-<?php echo html_escape($asset->direction); ?>"><?php echo html_escape(data_asset_role_label($asset->direction)); ?></span><span class="label label-default data-format-label"><?php echo html_escape(strtoupper($asset->format)); ?></span><?php if (!empty($options['delimiter']) && $asset->format === 'csv') { ?><small class="data-meta-line">Delimiter <?php echo $options['delimiter'] === "\t" ? 'TAB' : html_escape($options['delimiter']); ?> · <?php echo !empty($options['header']) ? 'header' : 'no header'; ?></small><?php } ?></td>
                <td><strong><?php echo html_escape($asset->environment); ?></strong><small><?php echo $asset->job_name === '*' ? '<i class="fa fa-share-alt"></i> Shared by all jobs' : '<i class="fa fa-cube"></i> '.html_escape($asset->job_name); ?></small><?php if (!(int) $asset->is_active) { ?><span class="label label-default">Inactive</span><?php } ?></td>
                <td><code title="<?php echo html_escape($asset->storage_path); ?>"><?php echo html_escape($asset->file_name); ?></code><small><?php echo html_escape($asset->storage_path); ?></small></td>
                <td><strong>v<?php echo (int) $asset->version; ?></strong><small><?php echo html_escape(data_asset_size($asset->file_size)); ?></small><?php if ($asset->checksum) { ?><small title="SHA-256 <?php echo html_escape($asset->checksum); ?>"><i class="fa fa-shield"></i> <?php echo html_escape(substr($asset->checksum, 0, 10)); ?>&hellip;</small><?php } else { ?><small class="text-muted"><?php echo $isAvailable ? 'Write target ready' : 'Awaiting file'; ?></small><?php } ?></td>
                <td class="text-right data-asset-actions"><button type="button" class="btn btn-default btn-sm edit-data-asset" data-id="<?php echo (int) $asset->id; ?>" title="Edit or replace file"><i class="fa fa-pencil"></i></button><?php if ((int) $asset->version > 0) { ?><button type="button" class="btn btn-default btn-sm preview-data-asset" data-url="<?php echo base_url().'data-assets/preview/'.(int) $asset->id.'?environment='.rawurlencode($initialEnvironment); ?>" data-name="<?php echo html_escape($asset->name); ?>" title="Preview current file"><i class="fa fa-eye"></i></button><a class="btn btn-default btn-sm" href="<?php echo base_url().'data-assets/download/'.(int) $asset->id.'?environment='.rawurlencode($initialEnvironment); ?>" title="Download current file"><i class="fa fa-download"></i></a><?php } ?><button type="button" class="btn btn-danger btn-sm delete-data-asset" data-id="<?php echo (int) $asset->id; ?>" data-name="<?php echo html_escape($asset->name); ?>" data-managed="<?php echo empty($asset->legacy_source) ? '1' : '0'; ?>" title="Delete"><i class="fa fa-trash"></i></button></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
          <div id="assetEmptyState" class="data-assets-empty"<?php echo empty($assets) ? '' : ' style="display:none"'; ?>><i class="fa fa-cubes"></i><h4>No data assets match this view</h4><p>Register an input dataset, output destination, or shared handoff contract.</p><button type="button" class="btn btn-primary show-asset-form"><i class="fa fa-plus"></i> Register Data Asset</button></div>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="assetPreviewModal" tabindex="-1" role="dialog" aria-labelledby="assetPreviewTitle">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button><h4 id="assetPreviewTitle" class="modal-title"><i class="fa fa-eye"></i> File preview</h4></div>
    <div class="modal-body">
      <div id="assetPreviewLoading" class="data-asset-preview-loading"><i class="fa fa-refresh fa-spin"></i><span>Reading a bounded sample&hellip;</span></div>
      <div id="assetPreviewError" class="alert alert-warning" style="display:none"></div>
      <div id="assetPreviewContent" style="display:none">
        <p id="assetPreviewMeta" class="data-asset-preview-meta"></p>
        <div id="assetPreviewBody" class="data-asset-preview-body"></div>
      </div>
    </div>
    <div class="modal-footer"><span class="pull-left text-muted"><i class="fa fa-shield"></i> Preview is read-only and size-limited.</span><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>
  </div></div>
</div>

<div class="modal fade" id="deleteAssetModal" tabindex="-1" role="dialog" aria-labelledby="deleteAssetTitle">
  <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
    <?php echo form_open('data-assets/delete?environment='.rawurlencode($initialEnvironment), array('id' => 'deleteAssetForm')); ?>
      <input id="deleteAssetId" type="hidden" name="asset_id">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button><h4 id="deleteAssetTitle" class="modal-title"><i class="fa fa-trash"></i> Delete data asset</h4></div>
      <div class="modal-body"><p>Delete <strong id="deleteAssetName"></strong> from the runtime catalog?</p><label id="deleteAssetFileOption" class="delete-file-option"><input type="checkbox" name="delete_file" value="1"> Also delete its stored file</label><p class="help-block">Deleting the registration immediately removes it from runtime discovery.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Delete Asset</button></div>
    <?php echo form_close(); ?>
  </div></div>
</div>

<script id="dataAssetsPayload" type="application/json"><?php echo json_encode($assetPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script>window.JobSeekerDataAssets = { initialDirection: <?php echo json_encode($initialDirection); ?>, initialEnvironment: <?php echo json_encode($initialEnvironment); ?>, baseUrl: <?php echo json_encode(base_url().'data-assets'); ?> };</script>
<script src="<?php echo base_url(); ?>assets/js/data-assets.js?v=2"></script>
