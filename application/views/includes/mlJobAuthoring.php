<?php
/**
 * Reusable ML job authoring form (v2). Workspace-backed: a real multi-file
 * Python project with an "Open in Editor" (OpenVSCode) button, a per-job image
 * build, a dataset picker that writes typed accessors into the code, and a Test
 * run. Rendered by mlJobs.php with server data, and by the Create Job screen
 * bootstrapped over AJAX. Driven by MlJobAuthoring.mount() in ml-job-authoring.js.
 *
 * Optional caller vars: $mlAuthoringId (DOM id, default "mlJobAuthoring").
 */
$rootId = isset($mlAuthoringId) ? $mlAuthoringId : 'mlJobAuthoring';
?>
<div id="<?php echo html_escape($rootId); ?>" class="ml-job-authoring">
  <form class="ml-authoring-form">
    <input type="hidden" name="id" value="">
    <input type="hidden" name="sample_key" value="">
    <input type="hidden" name="dataset_bindings_json" value="">

    <div class="ml-sample-gallery js-samples" style="margin-bottom:14px"></div>

    <div class="ml-form-grid">
      <div class="form-group"><label>Name</label><input type="text" class="form-control" name="name" required></div>
      <div class="form-group"><label>Job key</label><input type="text" class="form-control" name="job_key" placeholder="auto from name"></div>
      <div class="form-group"><label>Environment</label><select class="form-control" name="environment" required></select></div>
      <div class="form-group"><label>Runtime (base image)</label><select class="form-control" name="runtime_key" required></select></div>
      <div class="form-group"><label>Group</label><input type="text" class="form-control" name="group_name" value="General"></div>
      <div class="form-group"><label>Experiment</label><input type="text" class="form-control" name="experiment_name" placeholder="new or existing"></div>
      <div class="form-group"><label>Entry point</label><input type="text" class="form-control ml-mono" name="entrypoint" value="main.py"></div>
      <div class="form-group"><label>Dependencies</label>
        <select class="form-control" name="dependency_mode">
          <option value="requirements">requirements.txt</option>
          <option value="pyproject">pyproject.toml</option>
          <option value="none">none (runtime image only)</option>
        </select></div>
      <div class="form-group"><label>vCPU limit</label><input type="number" step="0.25" min="0.25" class="form-control" name="cpu_limit" value="1"></div>
      <div class="form-group"><label>Memory limit (MB)</label><input type="number" step="128" min="256" class="form-control" name="memory_limit_mb" value="2048"></div>
      <div class="form-group"><label>Timeout (s)</label><input type="number" step="60" min="120" class="form-control" name="timeout_seconds" value="3600"></div>
      <div class="form-group"><label>Schedule (cron)</label><input type="text" class="form-control ml-mono" name="schedule_cron" placeholder="H 2 * * *"></div>
    </div>

    <div class="form-group">
      <label>Detected job type</label>
      <div>
        <span class="ml-badge js-detected-type unknown">unclassified</span>
        <span class="ml-muted js-detected-note" style="margin-left:8px"></span>
        <label style="margin-left:12px;font-weight:normal">Override:
          <select class="form-control input-sm js-run-type-override" style="display:inline-block;width:auto">
            <option value="">auto</option><option value="train">train</option><option value="batch_infer">batch_infer</option>
            <option value="evaluate">evaluate</option><option value="preprocess">preprocess</option><option value="tune">tune</option>
          </select>
        </label>
      </div>
      <div class="ml-confidence-bar"><span class="js-confidence" style="width:0%"></span></div>
    </div>

    <div class="row">
      <div class="col-md-8">
        <label>Workspace</label>
        <div class="ml-editor">
          <div class="ml-editor-tabs js-tabs"></div>
          <div class="ml-editor-body">
            <textarea class="js-code" spellcheck="false" wrap="off"></textarea>
          </div>
          <div class="ml-editor-bar">
            <button type="button" class="btn btn-xs btn-default js-open-editor"><i class="fa fa-external-link"></i> Open in Editor</button>
            <button type="button" class="btn btn-xs btn-default js-build"><i class="fa fa-cube"></i> Build image</button>
            <span class="ml-image-pill none js-image-pill">image: none</span>
            <span class="ml-muted js-editor-note" style="margin-left:auto"></span>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <label>Datasets</label>
        <div class="ml-bound js-bound" style="margin-bottom:8px"><span class="ml-muted">Nothing bound.</span></div>
        <div class="ml-ds-picker js-ds-picker" style="max-height:220px;overflow:auto"></div>
        <label style="margin-top:12px">Params (JSON)</label>
        <textarea class="form-control ml-mono" name="params_json" rows="4" placeholder='{"n_estimators": 300}'></textarea>
        <label style="margin-top:8px">Container env (JSON)</label>
        <textarea class="form-control ml-mono" name="env_json" rows="3" placeholder='{"OMP_NUM_THREADS": "2"}'></textarea>
        <label style="margin-top:8px">Application args</label>
        <input type="text" class="form-control ml-mono" name="application_args" placeholder="--mode train">
      </div>
    </div>

    <div style="margin-top:14px">
      <button type="submit" class="btn btn-primary js-save"><i class="fa fa-save"></i> Save job</button>
      <button type="button" class="btn btn-success js-save-run"><i class="fa fa-play"></i> Save &amp; Test run</button>
      <span class="ml-muted js-save-status" style="margin-left:10px"></span>
    </div>
  </form>

  <div class="js-run-panel" style="display:none;margin-top:16px">
    <h4>Console</h4>
    <div class="js-run-console"></div>
  </div>
</div>
