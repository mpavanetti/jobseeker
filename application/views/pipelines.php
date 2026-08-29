<?php
$pipelineId = $pipeline ? (int) $pipeline->id : 0;
$environmentQuery = rawurlencode($selectedEnvironment);
$currentRunId = ! empty($recentRuns) ? (int) $recentRuns[0]->id : 0;
$groups = array();
foreach ($pipelineList as $item) {
    $group = trim((string) $item->group_name) ?: 'General';
    if (! isset($groups[$group])) $groups[$group] = array();
    $groups[$group][] = $item;
}
?>
<link href="<?php echo base_url(); ?>assets/dist/css/pipeline-builder.css?v=10" rel="stylesheet" type="text/css">
<div class="content-wrapper">
  <section class="content-header">
    <h1>Pipelines <small>workflow orchestration</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
      <li>Extract, Transform, Load</li>
      <li class="active">Pipelines</li>
    </ol>
  </section>

  <section class="content pipeline-page">
    <?php if ($selectedEnvironment === 'ALL') { ?>
      <div class="alert alert-warning pipeline-global-warning"><i class="fa fa-filter"></i> Select a global environment to create or edit pipelines.</div>
    <?php } ?>
    <?php if (! $jobsAvailable) { ?>
      <div class="alert alert-danger pipeline-global-warning"><i class="fa fa-exclamation-triangle"></i> Jenkins jobs are currently unavailable.</div>
    <?php } ?>

    <div class="pipeline-shell">
      <aside class="pipeline-rail pipeline-rail-left">
        <div class="pipeline-library-action">
          <a class="btn btn-primary btn-sm btn-block" href="<?php echo base_url(); ?>jobCreation"><i class="fa fa-plus"></i> Create job</a>
        </div>
        <div class="pipeline-rail-header"><h3>Created jobs</h3><span class="badge"><?php echo count($jobs); ?></span></div>
        <div class="pipeline-search"><div class="input-group input-group-sm"><span class="input-group-addon"><i class="fa fa-search"></i></span><input class="form-control" id="pipelineJobSearch" aria-label="Search created jobs" placeholder="Search jobs"></div></div>
        <div class="pipeline-job-list" id="pipelineJobList"></div>
        <div class="pipeline-library-footer"><i class="fa fa-hand-pointer-o"></i> Click to add or drag onto the canvas</div>
      </aside>

      <main class="pipeline-main">
        <div class="pipeline-toolbar">
          <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>pipelines?environment=<?php echo $environmentQuery; ?>" title="New pipeline"><i class="fa fa-file-o"></i></a>
          <select class="form-control input-sm pipeline-picker" id="pipelinePicker" aria-label="Saved pipeline">
            <option value="">New pipeline</option>
            <?php foreach ($groups as $groupName => $items) { ?>
              <optgroup label="<?php echo html_escape($groupName); ?>">
                <?php foreach ($items as $item) { ?>
                  <option value="<?php echo (int) $item->id; ?>"<?php echo $pipelineId === (int) $item->id ? ' selected' : ''; ?>><?php echo html_escape($item->name); ?> (v<?php echo (int) $item->version; ?>)</option>
                <?php } ?>
              </optgroup>
            <?php } ?>
          </select>
          <button type="button" class="btn btn-primary btn-sm" id="pipelineSave" title="Save and synchronize"<?php echo $selectedEnvironment === 'ALL' ? ' disabled' : ''; ?>><i class="fa fa-save"></i></button>
          <button type="button" class="btn btn-default btn-sm" id="pipelineValidate" title="Validate DAG"><i class="fa fa-check-circle"></i></button>
          <button type="button" class="btn btn-default btn-sm" id="pipelineAutoLayout" title="Auto layout"><i class="fa fa-sitemap"></i></button>
          <span class="pipeline-toolbar-spacer"></span>
          <span class="pipeline-environment"><i class="fa fa-cube"></i> <?php echo html_escape($selectedEnvironment); ?></span>
          <button type="button" class="btn btn-info btn-sm" id="pipelineDeploy" title="Deploy to environment"<?php echo $pipelineId <= 0 ? ' disabled' : ''; ?>><i class="fa fa-cloud-upload"></i></button>
          <button type="button" class="btn btn-success btn-sm" id="pipelineRun" title="Run pipeline"<?php echo $pipelineId <= 0 || ! $pipeline->is_active ? ' disabled' : ''; ?>><i class="fa fa-play"></i></button>
          <button type="button" class="btn btn-warning btn-sm" id="pipelineStop" title="Stop run" disabled><i class="fa fa-stop"></i></button>
          <button type="button" class="btn btn-danger btn-sm" id="pipelineDelete" title="Delete pipeline"<?php echo $pipelineId <= 0 ? ' disabled' : ''; ?>><i class="fa fa-trash"></i></button>
        </div>

        <div class="pipeline-meta">
          <div class="form-group"><label for="pipelineName">Name</label><input class="form-control input-sm" id="pipelineName" maxlength="200" value="<?php echo html_escape($pipeline ? $pipeline->name : ''); ?>"<?php echo $selectedEnvironment === 'ALL' ? ' disabled' : ''; ?>></div>
          <div class="form-group"><label for="pipelineKey">Key</label><input class="form-control input-sm" id="pipelineKey" maxlength="128" value="<?php echo html_escape($pipeline ? $pipeline->pipeline_key : ''); ?>"<?php echo $selectedEnvironment === 'ALL' ? ' disabled' : ''; ?>></div>
          <div class="form-group"><label for="pipelineGroup">Group</label><input class="form-control input-sm" id="pipelineGroup" maxlength="128" value="<?php echo html_escape($pipeline ? $pipeline->group_name : 'General'); ?>"<?php echo $selectedEnvironment === 'ALL' ? ' disabled' : ''; ?>></div>
          <div class="form-group"><label for="pipelineDescription">Description</label><input class="form-control input-sm" id="pipelineDescription" maxlength="2000" value="<?php echo html_escape($pipeline ? $pipeline->description : ''); ?>"<?php echo $selectedEnvironment === 'ALL' ? ' disabled' : ''; ?>></div>
          <div class="form-group"><label for="pipelineActive">Active</label><div><label style="margin-top:5px"><input type="checkbox" id="pipelineActive"<?php echo ! $pipeline || $pipeline->is_active ? ' checked' : ''; ?><?php echo $selectedEnvironment === 'ALL' ? ' disabled' : ''; ?>> Enabled</label></div></div>
          <div class="form-group pipeline-schedule-group">
            <label for="pipelineScheduleCron">Schedule</label>
            <div class="pipeline-schedule-control">
              <label class="pipeline-schedule-toggle" title="Enable Jenkins schedule"><input type="checkbox" id="pipelineScheduleEnabled"<?php echo $pipeline && $pipeline->schedule_enabled ? ' checked' : ''; ?><?php echo $selectedEnvironment === 'ALL' ? ' disabled' : ''; ?>><i class="fa fa-clock-o"></i></label>
              <input class="form-control input-sm" id="pipelineScheduleCron" maxlength="120" placeholder="H 2 * * *" value="<?php echo html_escape($pipeline && $pipeline->schedule_cron ? $pipeline->schedule_cron : ''); ?>"<?php echo $selectedEnvironment === 'ALL' || ! ($pipeline && $pipeline->schedule_enabled) ? ' disabled' : ''; ?>>
            </div>
          </div>
        </div>

        <div class="pipeline-canvas-scroll">
          <div class="pipeline-canvas" id="pipelineCanvas">
            <svg class="pipeline-edge-layer" id="pipelineEdgeLayer" aria-hidden="true"></svg>
            <div id="pipelineNodeLayer"></div>
          </div>
          <div class="pipeline-canvas-empty" id="pipelineCanvasEmpty">
            <div class="pipeline-canvas-empty-content">
              <span class="pipeline-canvas-empty-icon"><i class="fa fa-sitemap"></i></span>
              <h3>Start with a job</h3>
              <p>Select a created job from the left, or create a new job first.</p>
              <div class="pipeline-canvas-empty-actions">
                <button type="button" class="btn btn-primary btn-sm" id="pipelineEmptyFocusJobs"><i class="fa fa-list"></i> Browse jobs</button>
                <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>jobCreation"><i class="fa fa-plus"></i> Create job</a>
              </div>
            </div>
          </div>
        </div>
      </main>

      <aside class="pipeline-rail pipeline-rail-right">
        <div class="pipeline-rail-header pipeline-inspector-header"><h3>Inspector</h3></div>
        <div class="pipeline-inspector" id="pipelineInspector"></div>
        <div class="pipeline-rail-header pipeline-run-header"><h3>Run</h3></div>
        <div class="pipeline-run-panel">
          <div class="pipeline-run-summary"><span>Latest state</span><span class="pipeline-run-status" id="pipelineRunStatus" data-status="<?php echo ! empty($recentRuns) ? html_escape($recentRuns[0]->status) : 'NOT_RUN'; ?>"><?php echo ! empty($recentRuns) ? html_escape($recentRuns[0]->status) : 'NOT RUN'; ?></span></div>
          <div id="pipelineRunNodes"></div>
          <?php if (! empty($recentRuns)) { ?>
            <h4 style="margin:16px 0 6px;font-size:13px">Recent runs</h4>
            <?php foreach ($recentRuns as $run) { ?>
              <button type="button" class="pipeline-list-item pipeline-run-history" data-run-id="<?php echo (int) $run->id; ?>">
                <span class="pipeline-list-title">Run #<?php echo (int) $run->id; ?></span>
                <span class="pipeline-list-meta"><?php echo html_escape($run->status); ?> &middot; <?php echo html_escape($run->started_at); ?></span>
              </button>
            <?php } ?>
          <?php } ?>
        </div>
      </aside>
    </div>

    <section class="pipeline-execution-monitor" id="pipelineExecutionMonitor">
      <header class="pipeline-monitor-header">
        <div>
          <span class="pipeline-monitor-kicker">Execution monitor</span>
          <h2>Pipeline job status</h2>
        </div>
        <div class="pipeline-monitor-summary" aria-live="polite">
          <span><strong id="pipelineMonitorTotal"><?php echo count($graph['nodes']); ?></strong> jobs</span>
          <span><strong id="pipelineMonitorRunning">0</strong> running</span>
          <span><strong id="pipelineMonitorFinished">0</strong> finished</span>
          <span class="pipeline-run-status" id="pipelineMonitorStatus" data-status="<?php echo ! empty($recentRuns) ? html_escape($recentRuns[0]->status) : 'NOT_RUN'; ?>"><?php echo ! empty($recentRuns) ? html_escape($recentRuns[0]->status) : 'NOT RUN'; ?></span>
        </div>
      </header>
      <div class="pipeline-status-track" id="pipelineStatusTrack"></div>
      <div class="pipeline-console-panel">
        <div class="pipeline-console-toolbar">
          <div class="pipeline-console-identity">
            <i class="fa fa-terminal"></i>
            <div><strong id="pipelineConsoleTitle">Job console</strong><span id="pipelineConsoleMeta">Select a job above to inspect its output.</span></div>
          </div>
          <label class="pipeline-console-autoscroll"><input type="checkbox" id="pipelineConsoleAutoScroll" checked> Auto-scroll</label>
          <button type="button" class="btn btn-default btn-sm" id="pipelineConsoleReload" title="Reload console output" disabled><i class="fa fa-refresh"></i></button>
        </div>
        <div class="pipeline-console-output job-console-host" id="pipelineConsoleOutput"><div class="job-console-empty">Run this pipeline, then select a job to view its console output.</div></div>
      </div>
    </section>
  </section>
</div>

<div class="modal fade" id="pipelineDeployModal" tabindex="-1" role="dialog" aria-labelledby="pipelineDeployTitle">
  <div class="modal-dialog pipeline-deploy-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="pipelineDeployTitle"><i class="fa fa-cloud-upload"></i> Deploy pipeline</h4>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger" id="pipelineDeployError" style="display:none"></div>
        <div class="pipeline-deploy-flow">
          <div class="form-group">
            <label>Source</label>
            <div class="pipeline-deploy-environment"><i class="fa fa-cube"></i> <?php echo html_escape($selectedEnvironment); ?></div>
          </div>
          <i class="fa fa-long-arrow-right pipeline-deploy-arrow" aria-hidden="true"></i>
          <div class="form-group">
            <label for="pipelineDeployTarget">Target</label>
            <select class="form-control" id="pipelineDeployTarget">
              <option value="">Select environment</option>
              <?php foreach ($environments as $deploymentEnvironment) { if ($deploymentEnvironment === $selectedEnvironment) continue; ?>
                <option value="<?php echo html_escape($deploymentEnvironment); ?>"><?php echo html_escape($deploymentEnvironment); ?></option>
              <?php } ?>
            </select>
          </div>
        </div>
        <label class="pipeline-deploy-overwrite"><input type="checkbox" id="pipelineDeployOverwrite"> Overwrite the target pipeline when it already exists</label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-info" id="pipelineDeployConfirm"><i class="fa fa-cloud-upload"></i> Deploy</button>
      </div>
    </div>
  </div>
</div>
<script>
window.JobSeekerPipelineConfig = <?php echo json_encode(array(
  'baseUrl' => base_url(),
  'environment' => $selectedEnvironment,
  'pipeline' => $pipeline,
  'graph' => $graph,
  'jobs' => $jobs,
  'currentRunId' => $currentRunId,
  'urls' => array(
    'validate' => base_url().'pipelines/validate?environment='.$environmentQuery,
    'save' => base_url().'pipelines/save?environment='.$environmentQuery,
    'deploy' => base_url().'pipelines/deploy?environment='.$environmentQuery,
    'run' => base_url().'pipelines/run?environment='.$environmentQuery,
    'status' => base_url().'pipelines/status/{id}?environment='.$environmentQuery,
    'stop' => base_url().'pipelines/stop?environment='.$environmentQuery,
    'delete' => base_url().'pipelines/delete?environment='.$environmentQuery
  )
), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="<?php echo base_url(); ?>assets/js/pipeline-builder.js?v=9"></script>
<script>jQuery(function() { window.JobSeekerPipelineBuilder.initialize(window.JobSeekerPipelineConfig); });</script>
