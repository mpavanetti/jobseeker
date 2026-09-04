<?php
$projects = isset($projects) && is_array($projects) ? $projects : array();
$executions = isset($executions) && is_array($executions) ? $executions : array();
$server = isset($server) && is_array($server) ? $server : array('reachable' => FALSE, 'url' => '', 'message' => '', 'version' => '', 'environment' => '');
$hopEnabled = isset($hop_enabled) ? (bool) $hop_enabled : TRUE;
$hopVersion = isset($hop_version) ? (string) $hop_version : '';
$canManage = isset($can_manage) ? (bool) $can_manage : FALSE;
$environment = isset($environment) ? (string) $environment : 'ALL';

$totalWorkflows = 0;
$totalPipelines = 0;
$totalJobs = 0;
$brokenProjects = 0;
foreach ($projects as $project) {
    $totalWorkflows += count($project['workflows']);
    $totalPipelines += count($project['pipelines']);
    $totalJobs += count($project['jobs']);
    if (! empty($project['missing']) || empty($project['valid'])) {
        $brokenProjects++;
    }
}

$publishedConnections = isset($published_connections) && is_array($published_connections) ? $published_connections : array();
$publishedVariables = isset($published_variables) && is_array($published_variables) ? $published_variables : array();
$publishedDrivers = isset($published_drivers) && is_array($published_drivers) ? $published_drivers : array();
$installedDrivers = isset($installed_drivers) && is_array($installed_drivers) ? $installed_drivers : array();
$availableConnections = isset($available_connections) && is_array($available_connections) ? $available_connections : array();
$serverEnvironment = isset($server_environment) ? (string) $server_environment : 'DEV';
$publishableConnections = array();
foreach ($availableConnections as $availableConnection) {
    if (! empty($availableConnection['relational']) && $availableConnection['backend'] === 'local') {
        $publishableConnections[] = $availableConnection;
    }
}

$externalRuns = 0;
$failedRuns = 0;
foreach ($executions as $execution) {
    if ($execution['source'] === 'hop-gui') {
        $externalRuns++;
    }
    if ($execution['state'] === 'error') {
        $failedRuns++;
    }
}
?>
<style>
  .hop-page .hop-engine-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 15px; }
  .hop-page .hop-engine-card { flex: 1 1 240px; border: 1px solid #e4e7ea; border-radius: 4px; padding: 12px 14px; background: #fff; }
  .hop-page .hop-engine-card strong { display: block; font-size: 13px; }
  .hop-page .hop-engine-card span { color: #8a9199; font-size: 12px; }
  .hop-page .hop-engine-card .label { margin-top: 6px; display: inline-block; }
  .hop-page .hop-file-list { margin: 0; padding-left: 16px; font-size: 12px; color: #5c6670; max-height: 120px; overflow-y: auto; }
  .hop-page .hop-chip { display: inline-block; background: #f2f5f7; border-radius: 10px; padding: 1px 9px; font-size: 11px; margin: 0 4px 4px 0; color: #44515c; }
  .hop-page .hop-chip.hop-chip-db { background: #e8f4fd; color: #1b6ca8; }
  .hop-page .hop-table-scroll { overflow-x: auto; }
  .hop-page .hop-empty { text-align: center; padding: 40px 20px; color: #8a9199; }
  .hop-page .hop-empty i { font-size: 42px; display: block; margin-bottom: 12px; color: #c9d2d9; }
  .hop-page .hop-run-meta { font-size: 11px; color: #8a9199; }
  .hop-page .hop-sync-state { font-size: 12px; color: #8a9199; font-weight: normal; margin-left: 8px; }
  .hop-page .hop-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
  .hop-page .hop-filters .form-control { height: 30px; padding: 4px 8px; font-size: 12px; width: auto; }
  .hop-page .hop-filters .hop-filter-count { color: #8a9199; font-size: 12px; margin-left: auto; }
  #hopLogBody { max-height: 62vh; overflow: auto; }

  /* Canvas. The palette follows Hop's own reading of a graph: green for the
     success path, red for the failure path, grey for unconditional. */
  .hop-canvas-host { background: #f7f9fb; border: 1px solid #e4e7ea; border-radius: 4px; overflow: auto; max-height: 66vh; }
  .hop-canvas { display: block; width: 100%; min-height: 320px; }
  .hop-canvas-empty { padding: 40px; text-align: center; color: #8a9199; }
  .hop-canvas .hop-node rect { fill: #fff; stroke: #b8c2cc; stroke-width: 1.5; }
  .hop-canvas .hop-node:hover rect, .hop-canvas .hop-node:focus rect { stroke: #3c8dbc; stroke-width: 2; }
  .hop-canvas .hop-node { cursor: pointer; }
  .hop-canvas .hop-node-name { font: 600 12px/1 "Helvetica Neue", Helvetica, Arial, sans-serif; fill: #2f3d4a; }
  .hop-canvas .hop-node-type { font: 10px/1 "Helvetica Neue", Helvetica, Arial, sans-serif; fill: #8a9199; }
  .hop-canvas .hop-node-start rect { fill: #eef7ee; stroke: #6fae6f; }
  .hop-canvas .hop-node-success rect { fill: #eef7ee; stroke: #45a145; }
  .hop-canvas .hop-node-failure rect { fill: #fdeeee; stroke: #d9534f; }
  .hop-canvas .hop-node-passthrough rect { stroke-dasharray: 4 3; }
  .hop-canvas .hop-edge { fill: none; stroke: #9aa5b1; stroke-width: 1.8; }
  .hop-canvas .hop-edge-success { stroke: #45a145; }
  .hop-canvas .hop-edge-failure { stroke: #d9534f; }
  .hop-canvas .hop-edge.is-disabled { stroke: #c9d2d9; stroke-dasharray: 5 4; }
  .hop-canvas .hop-edge-head { stroke: none; fill: #9aa5b1; }
  .hop-canvas .hop-edge-head.hop-edge-success { fill: #45a145; }
  .hop-canvas .hop-edge-head.hop-edge-failure { fill: #d9534f; }
  .hop-canvas .hop-edge-head.hop-edge-disabled { fill: #c9d2d9; }
  .hop-canvas .hop-note rect { fill: #fffbe6; stroke: #e6d999; }
  .hop-canvas .hop-note text { font: 11px/1 "Helvetica Neue", Helvetica, Arial, sans-serif; fill: #7a6f3d; }
  .hop-canvas-legend { font-size: 11px; color: #8a9199; margin-top: 8px; }
  .hop-canvas-legend span { margin-right: 14px; }
  .hop-canvas-legend i { display: inline-block; width: 16px; height: 3px; vertical-align: middle; margin-right: 4px; }
  .hop-canvas-host { position: relative; }
  .hop-canvas-toolbar { position: absolute; top: 8px; right: 8px; z-index: 2; display: flex; gap: 4px; }
  .hop-canvas-toolbar .btn { width: 26px; padding: 1px 0; font-size: 13px; line-height: 18px; }
</style>
<script src="<?php echo base_url(); ?>assets/js/hop-canvas.js?v=2" type="text/javascript"></script>

<div class="content-wrapper hop-page">
  <section class="content-header">
    <h1>Apache Hop <small>Visual data integration executed by Jenkins</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
      <li>Extract Transform Load</li>
      <li class="active">Apache Hop</li>
    </ol>
  </section>

  <section class="content">
    <?php if (! $hopEnabled) { ?>
      <div class="alert alert-warning">
        <i class="icon fa fa-warning"></i> Apache Hop is disabled on this installation. Set <code>JOBSEEKER_HOP_ENABLED=true</code> to offer it in Job Creation.
      </div>
    <?php } ?>

    <div class="row">
      <div class="col-lg-3 col-sm-6">
        <div class="info-box">
          <span class="info-box-icon bg-aqua"><i class="fa fa-random"></i></span>
          <div class="info-box-content"><span class="info-box-text">Hop Projects</span><span class="info-box-number"><?php echo count($projects); ?></span><small><?php echo $brokenProjects; ?> need attention</small></div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6">
        <div class="info-box">
          <span class="info-box-icon bg-green"><i class="fa fa-sitemap"></i></span>
          <div class="info-box-content"><span class="info-box-text">Workflows &amp; Pipelines</span><span class="info-box-number"><?php echo $totalWorkflows + $totalPipelines; ?></span><small><?php echo $totalWorkflows; ?> .hwf &middot; <?php echo $totalPipelines; ?> .hpl</small></div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6">
        <div class="info-box">
          <span class="info-box-icon bg-purple"><i class="fa fa-cogs"></i></span>
          <div class="info-box-content"><span class="info-box-text">Jenkins Jobs</span><span class="info-box-number"><?php echo $totalJobs; ?></span><small>Scheduled by Jenkins</small></div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6">
        <div class="info-box">
          <span class="info-box-icon <?php echo $failedRuns > 0 ? 'bg-red' : 'bg-yellow'; ?>"><i class="fa fa-bolt"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Hop Server Runs</span>
            <span class="info-box-number" id="hopRunCount"><?php echo count($executions); ?></span>
            <small id="hopRunSummary"><?php echo $externalRuns; ?> from the Hop GUI &middot; <?php echo $failedRuns; ?> failed</small>
          </div>
        </div>
      </div>
    </div>

    <div class="callout callout-info">
      <h4>Jenkins schedules, Hop executes</h4>
      <p>
        A Hop job is an ordinary Jenkins job, so it keeps environment parameters, cron and tag schedules, timeouts, e-mail notification, promotion, and it drops straight into
        <a href="<?php echo base_url(); ?>pipelines">Pipelines</a>. At run time JobSeeker turns every
        <a href="<?php echo base_url(); ?>dbSettings">connector</a> in scope into a Hop database connection named after its connector key, publishes every
        <a href="<?php echo base_url(); ?>data-assets">Data Asset</a> as a <code>${JOBSEEKER_ASSET_KEY}</code> variable, and records the run in
        <a href="<?php echo base_url(); ?>Tmf/data">Transaction Monitoring</a>.
      </p>
      <p>
        <a class="btn btn-primary btn-sm" href="<?php echo base_url(); ?>JobCreation"><i class="fa fa-plus"></i> Create an Apache Hop job</a>
      </p>
      <p style="margin-bottom:0">
        <small>Edit projects as text in the bundled OpenVSCode Server under <code>repository/hop/projects/</code>, or design them in the Apache Hop GUI and publish to the Hop Server below.</small>
      </p>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-server"></i> Execution engines</h3></div>
      <div class="box-body">
        <div class="hop-engine-grid">
          <div class="hop-engine-card">
            <strong><i class="fa fa-cube"></i> Container <small>(default)</small></strong>
            <span>One ephemeral <code>apache/hop:<?php echo html_escape($hopVersion); ?></code> container per build, on the Jenkins worker, with the job's CPU and memory limits.</span>
            <span class="label label-success">Always available</span>
          </div>
          <div class="hop-engine-card">
            <strong><i class="fa fa-bolt"></i> Hop Server</strong>
            <span>
              Warm JVM at <code><?php echo html_escape($server['url']); ?></code>.
              <span id="hopServerMessage"><?php echo html_escape($server['message']); ?></span>
              <?php if (! empty($server['version'])) { ?> Version <?php echo html_escape($server['version']); ?>.<?php } ?>
            </span>
            <span class="label <?php echo ! empty($server['reachable']) ? 'label-success' : 'label-warning'; ?>" id="hopServerBadge"><?php echo ! empty($server['reachable']) ? 'Reachable' : 'Not running'; ?></span>
          </div>
        </div>
        <p class="help-block" style="margin-bottom:0">
          Start the optional server with <code>docker compose --profile hop up -d hop-server</code>. Point the Apache Hop GUI at it with a
          <em>Hop Server</em> metadata object for <code><?php echo html_escape($server['url']); ?></code>; whatever you run there is picked up below and in Transaction Monitoring.
        </p>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-plug"></i> Connections on the Hop Server <small><?php echo html_escape($serverEnvironment); ?></small></h3>
      </div>
      <div class="box-body">
        <p class="help-block">
          A Jenkins Hop job is handed a run-scoped connector catalog and gives it back when the build ends, so it needs nothing here.
          A pipeline published straight from the Apache Hop GUI has no runner in front of it: without a published catalog it fails with
          <em>Relational database connection not found</em>. Publishing writes resolved credentials onto the server volume, so only connectors
          scoped to <strong>every job</strong> in <?php echo html_escape($serverEnvironment); ?> are offered, and a Jenkins run restores this catalog after borrowing the folder.
        </p>
        <p>
          <?php if ($publishedConnections) { ?>
            <?php foreach ($publishedConnections as $publishedConnection) { ?>
              <span class="hop-chip hop-chip-db"><i class="fa fa-database"></i> <?php echo html_escape($publishedConnection); ?></span>
            <?php } ?>
          <?php } else { ?>
            <span class="text-muted">No connection is published. Pipelines started from the Apache Hop GUI can only use connections defined inside their own project.</span>
          <?php } ?>
        </p>
        <?php if ($canManage) { ?>
          <button type="button" class="btn btn-primary btn-sm" id="hopPublishConnections"<?php echo $publishableConnections ? '' : ' disabled'; ?>>
            <i class="fa fa-upload"></i> Publish <?php echo count($publishableConnections); ?> connection<?php echo count($publishableConnections) === 1 ? '' : 's'; ?> to the Hop Server
          </button>
          <button type="button" class="btn btn-default btn-sm" id="hopWithdrawConnections"<?php echo $publishedConnections ? '' : ' disabled'; ?>>
            <i class="fa fa-eraser"></i> Withdraw
          </button>
        <?php } else { ?>
          <span class="text-muted">Publishing connections is an administrator or manager action.</span>
        <?php } ?>
        <?php if ($availableConnections && ! $publishableConnections) { ?>
          <p class="help-block" style="margin-top:8px">
            Every connector in scope is either non-relational or holds its secret outside JobSeeker, so none can be published to a shared server.
          </p>
        <?php } ?>
        <div id="hopConnectionsResult" class="hop-run-meta" style="margin-top:10px"></div>

        <?php if ($publishedDrivers) { ?>
          <h4 style="margin-top:18px;font-size:14px"><i class="fa fa-plug"></i> JDBC drivers these connections need</h4>
          <p class="help-block">
            The Apache Hop image bundles only the permissively licensed drivers, so the Hop Server installs whatever of these it is missing
            when it next starts &mdash; it is the only party that can see what its own image already carries. Installed jars are kept on the
            repository volume, so the download happens once rather than on every rebuild.
          </p>
          <p>
            <?php foreach ($publishedDrivers as $publishedDriver) { ?>
              <span class="hop-chip"><i class="fa fa-database"></i> <?php echo html_escape($publishedDriver); ?></span>
            <?php } ?>
            <?php if ($installedDrivers) { ?>
              <br><small class="text-muted">Downloaded to the shared folder: <?php echo html_escape(implode(', ', $installedDrivers)); ?></small>
            <?php } ?>
          </p>
        <?php } ?>

        <?php if ($publishedVariables) { ?>
          <h4 style="margin-top:18px;font-size:14px"><i class="fa fa-code"></i> Platform variables on the Hop Server</h4>
          <p class="help-block">
            The same names a Jenkins Hop job gets, so a pipeline designed against them runs unchanged once it is scheduled. No secret is here:
            a password reaches the server only inside the connection above. Hop reads variables when it starts, so a fresh publish needs
            <code>docker compose --profile hop restart hop-server</code>.
          </p>
          <div class="hop-table-scroll">
            <table class="table table-condensed" id="hopVariablesTable">
              <thead><tr><th style="width:320px">Variable</th><th>Value</th><th>Meaning</th></tr></thead>
              <tbody>
                <?php foreach ($publishedVariables as $publishedVariable) { ?>
                  <tr>
                    <td><code>${<?php echo html_escape($publishedVariable['name']); ?>}</code></td>
                    <td><small><?php echo html_escape($publishedVariable['value']); ?></small></td>
                    <td><small class="text-muted"><?php echo html_escape($publishedVariable['description']); ?></small></td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        <?php } ?>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">
          <i class="fa fa-history"></i> Hop Server executions
          <span class="hop-sync-state" id="hopSyncState"></span>
        </h3>
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-default btn-sm" id="hopRefreshExecutions"><i class="fa fa-refresh"></i> Refresh</button>
        </div>
      </div>
      <div class="box-body hop-table-scroll">
        <p class="help-block">
          Everything the Hop Server has run, whichever side started it &mdash; a Jenkins job using the <code>server</code> engine, or someone publishing straight from the Apache Hop GUI.
          A run started outside JobSeeker gets its own Transaction Monitoring row, carrying its Hop errors, so it is not invisible here.
          Click a name to see the canvas the designer drew, or <strong>Use in job</strong> to turn a run you are happy with into a scheduled Jenkins job.
        </p>
        <div class="hop-filters">
          <input type="search" class="form-control" id="hopRunSearch" placeholder="Search name, project or job" style="width:240px">
          <select class="form-control" id="hopRunState">
            <option value="">Any status</option>
            <option value="error">Failed</option>
            <option value="ready">Finished</option>
            <option value="running">Running</option>
          </select>
          <select class="form-control" id="hopRunSource">
            <option value="">Anyone</option>
            <option value="hop-gui">Apache Hop GUI</option>
            <option value="jenkins">Jenkins</option>
          </select>
          <select class="form-control" id="hopRunKind">
            <option value="">Workflows and pipelines</option>
            <option value="workflow">Workflows</option>
            <option value="pipeline">Pipelines</option>
          </select>
          <span class="hop-filter-count" id="hopRunFilterCount"></span>
        </div>
        <table class="table table-hover" id="hopExecutionsTable">
          <thead>
            <tr>
              <th style="width:160px">Started</th>
              <th>Workflow or pipeline</th>
              <th>Project</th>
              <th>Started by</th>
              <th>Status</th>
              <th style="width:150px">Rows</th>
              <th style="width:230px">Actions</th>
            </tr>
          </thead>
          <tbody id="hopExecutionsBody"></tbody>
        </table>
        <div class="hop-empty" id="hopExecutionsEmpty" style="display:none">
          <i class="fa fa-bolt"></i>
          <p>The Hop Server has not run anything yet.</p>
          <p>Create a Hop job with the <strong>Hop Server</strong> engine, or publish a pipeline to <code><?php echo html_escape($server['url']); ?></code> from the Apache Hop GUI.</p>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-folder-open"></i> Projects <small><?php echo html_escape($environment); ?></small></h3>
      </div>
      <div class="box-body hop-table-scroll">
        <?php if ($projects) { ?>
          <div class="hop-filters">
            <select class="form-control" id="hopProjectEnvironment">
              <option value="">Any environment</option>
              <?php
              $projectEnvironments = array();
              foreach ($projects as $project) { $projectEnvironments[$project['environment']] = TRUE; }
              foreach (array_keys($projectEnvironments) as $projectEnvironment) { ?>
                <option value="<?php echo html_escape($projectEnvironment); ?>"><?php echo html_escape($projectEnvironment); ?></option>
              <?php } ?>
            </select>
            <select class="form-control" id="hopProjectHealth">
              <option value="">Every project</option>
              <option value="attention">Needs attention</option>
              <option value="used">Used by a Jenkins job</option>
              <option value="unused">Not used yet</option>
            </select>
          </div>
        <?php } ?>
        <?php if (! $projects) { ?>
          <div class="hop-empty">
            <i class="fa fa-random"></i>
            <p>No Apache Hop project has been added yet.</p>
            <p>Open <a href="<?php echo base_url(); ?>JobCreation">Job Creation</a>, choose <strong>Execute Linux Command &rarr; Script/File Execution &rarr; Apache Hop</strong>, then upload a project archive, a <code>.hwf</code> workflow, or a <code>.hpl</code> pipeline - or start from a bundled sample.</p>
          </div>
        <?php } else { ?>
          <table class="table table-hover" id="hopProjectsTable">
            <thead>
              <tr>
                <th>Project</th>
                <th>Runnable files</th>
                <th>Hop database connections</th>
                <th>Jenkins jobs</th>
                <th>Environment</th>
                <th style="width:190px">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $project) { ?>
              <tr data-project="<?php echo html_escape($project['key']); ?>"
                  data-environment="<?php echo html_escape($project['environment']); ?>"
                  data-attention="<?php echo (! empty($project['missing']) || empty($project['valid'])) ? '1' : '0'; ?>"
                  data-used="<?php echo $project['jobs'] ? '1' : '0'; ?>">
                <td>
                  <strong><?php echo html_escape($project['key']); ?></strong>
                  <?php if ($project['name'] !== '' && $project['name'] !== $project['key']) { ?>
                    <span class="text-muted">(<?php echo html_escape($project['name']); ?>)</span>
                  <?php } ?>
                  <?php if (! empty($project['missing'])) { ?>
                    <span class="label label-danger">Folder missing</span>
                  <?php } else if (empty($project['valid'])) { ?>
                    <span class="label label-warning">No project-config.json</span>
                  <?php } ?>
                  <div><small class="text-muted"><?php echo html_escape($project['path']); ?></small></div>
                  <?php if ($project['description'] !== '') { ?>
                    <div><small><?php echo html_escape($project['description']); ?></small></div>
                  <?php } ?>
                </td>
                <td>
                  <?php if (! $project['entry_files']) { ?>
                    <span class="text-muted">None</span>
                  <?php } else { ?>
                    <ul class="hop-file-list">
                      <?php foreach ($project['entry_files'] as $entryFile) { ?>
                        <li<?php echo $entryFile === $project['entry_file'] ? ' title="Default entry file"' : ''; ?>>
                          <?php if (empty($project['missing'])) { ?>
                            <a href="#" class="hop-project-canvas" data-project="<?php echo html_escape($project['key']); ?>" data-file="<?php echo html_escape($entryFile); ?>" title="See the canvas"><?php echo html_escape($entryFile); ?></a>
                          <?php } else { echo html_escape($entryFile); } ?>
                          <?php echo $entryFile === $project['entry_file'] ? ' <i class="fa fa-star text-yellow"></i>' : ''; ?>
                        </li>
                      <?php } ?>
                    </ul>
                  <?php } ?>
                </td>
                <td>
                  <?php if (! $project['connections']) { ?>
                    <span class="text-muted">Generated at run time from connectors in scope</span>
                  <?php } else { foreach ($project['connections'] as $connection) { ?>
                    <span class="hop-chip hop-chip-db"><i class="fa fa-database"></i> <?php echo html_escape($connection); ?></span>
                  <?php } } ?>
                </td>
                <td>
                  <?php if (! $project['jobs']) { ?>
                    <span class="text-muted">Not used yet</span>
                  <?php } else { foreach ($project['jobs'] as $job) { ?>
                    <a class="hop-chip" href="<?php echo base_url(); ?>jobView?job=<?php echo rawurlencode($job['job_name']); ?>"><?php echo html_escape($job['job_name']); ?> &middot; <?php echo html_escape($job['engine']); ?></a>
                  <?php } } ?>
                </td>
                <td><span class="label label-default"><?php echo html_escape($project['environment']); ?></span></td>
                <td>
                  <?php if (empty($project['missing']) && ! empty($project['entry_files'])) { ?>
                    <a class="btn btn-xs btn-primary" href="<?php echo base_url(); ?>JobCreation?hop_project=<?php echo rawurlencode($project['key']); ?>"><i class="fa fa-share"></i> Use in job</a>
                  <?php } ?>
                  <?php if (empty($project['missing'])) { ?>
                    <a class="btn btn-xs btn-default" href="<?php echo base_url(); ?>hop/download?project=<?php echo rawurlencode($project['key']); ?>&amp;archive=1" title="Download the project as a zip to open in the Apache Hop GUI"><i class="fa fa-download"></i></a>
                  <?php } ?>
                  <?php if ($canManage) { ?>
                    <button type="button" class="btn btn-xs btn-danger hop-delete-project" data-project="<?php echo html_escape($project['key']); ?>" data-name="<?php echo html_escape($project['name']); ?>"><i class="fa fa-trash"></i> Remove</button>
                  <?php } else { ?>
                    <span class="text-muted">Read only</span>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        <?php } ?>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="hopDeleteModal" tabindex="-1" role="dialog" aria-labelledby="hopDeleteTitle">
  <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 id="hopDeleteTitle" class="modal-title"><i class="fa fa-trash"></i> Remove Hop project</h4>
    </div>
    <div class="modal-body">
      <p>Remove <strong id="hopDeleteName"></strong> from the Apache Hop registry?</p>
      <label><input type="checkbox" id="hopDeleteFiles" value="1"> Also delete the project folder from the repository</label>
      <p class="help-block">Jenkins jobs are never deleted here. A job whose project was removed fails with a clear error on its next run, so nothing disappears silently.</p>
      <div id="hopDeleteError" class="alert alert-danger" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
      <button type="button" id="hopDeleteConfirm" class="btn btn-danger"><i class="fa fa-trash"></i> Remove</button>
    </div>
  </div></div>
</div>

<div class="modal fade" id="hopLogModal" tabindex="-1" role="dialog" aria-labelledby="hopLogTitle">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 id="hopLogTitle" class="modal-title"><i class="fa fa-file-text-o"></i> Apache Hop log</h4>
    </div>
    <div class="modal-body">
      <p id="hopLogMeta" class="hop-run-meta"></p>
      <div id="hopLogBody">Loading…</div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
    </div>
  </div></div>
</div>

<div class="modal fade" id="hopCanvasModal" tabindex="-1" role="dialog" aria-labelledby="hopCanvasTitle">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 id="hopCanvasTitle" class="modal-title"><i class="fa fa-sitemap"></i> Apache Hop canvas</h4>
    </div>
    <div class="modal-body">
      <p id="hopCanvasMeta" class="hop-run-meta"></p>
      <div id="hopCanvasBody"></div>
      <div class="hop-canvas-legend">
        <span><i style="background:#9aa5b1"></i> unconditional</span>
        <span><i style="background:#45a145"></i> on success</span>
        <span><i style="background:#d9534f"></i> on failure</span>
        <span><i style="background:#c9d2d9"></i> disabled</span>
        <span>Drawn from the file's own layout &mdash; hover a box for its configuration.</span>
      </div>
      <div id="hopCanvasDetail" class="hop-run-meta" style="margin-top:10px"></div>
    </div>
    <div class="modal-footer">
      <a href="#" class="btn btn-default" id="hopCanvasDownload"><i class="fa fa-download"></i> Download file</a>
      <a href="#" class="btn btn-default" id="hopCanvasDownloadProject"><i class="fa fa-file-archive-o"></i> Download project</a>
      <a href="#" class="btn btn-primary" id="hopCanvasUseInJob"><i class="fa fa-share"></i> Use in job</a>
      <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
    </div>
  </div></div>
</div>

<script>
(function() {
  var deleteUrl = <?php echo json_encode(base_url().'hop/delete'); ?>;
  var executionsUrl = <?php echo json_encode(base_url().'hop/executions'); ?>;
  var executionLogUrl = <?php echo json_encode(base_url().'hop/execution-log'); ?>;
  var graphUrl = <?php echo json_encode(base_url().'hop/graph'); ?>;
  var jobCreationUrl = <?php echo json_encode(base_url().'JobCreation'); ?>;
  var jobViewUrl = <?php echo json_encode(base_url().'jobView?job='); ?>;
  var tmfUrl = <?php echo json_encode(base_url().'Tmf/data'); ?>;
  var publishConnectionsUrl = <?php echo json_encode(base_url().'hop/publish-connections'); ?>;
  var importExecutionUrl = <?php echo json_encode(base_url().'hop/import-execution'); ?>;
  var downloadUrl = <?php echo json_encode(base_url().'hop/download'); ?>;
  var serverEnvironment = <?php echo json_encode($serverEnvironment); ?>;
  var environment = <?php echo json_encode($environment); ?>;
  var executions = <?php echo json_encode($executions, JSON_UNESCAPED_SLASHES); ?>;
  var pending = null;
  var inFlight = false;
  var executionsTable = null;
  var projectsTable = null;

  function escapeHtml(value) {
    return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
  }

  function stateLabel(execution) {
    if (execution.state === 'error') { return '<span class="label label-danger">' + escapeHtml(execution.status || 'Failed') + '</span>'; }
    if (execution.state === 'running') { return '<span class="label label-info">' + escapeHtml(execution.status || 'Running') + '</span>'; }
    return '<span class="label label-success">' + escapeHtml(execution.status || 'Finished') + '</span>';
  }

  function startedBy(execution) {
    if (execution.source === 'jenkins') {
      var job = execution.job_name || '';
      return job
        ? '<a href="' + jobViewUrl + encodeURIComponent(job) + '"><i class="fa fa-cogs"></i> ' + escapeHtml(job) + '</a>'
        : '<span class="hop-chip"><i class="fa fa-cogs"></i> Jenkins</span>';
    }
    return '<span class="hop-chip" title="Published straight to the Hop Server"><i class="fa fa-desktop"></i> Apache Hop GUI</span>';
  }

  // "Use in job" carries the project, the exact file and the engine that ran it,
  // so a run somebody is happy with becomes a scheduled Jenkins job with nothing
  // retyped. It only makes sense once the run has been matched to a file on disk.
  function useInJobUrl(execution) {
    if (!execution.project_key || !execution.entry_file) { return ''; }
    return jobCreationUrl + '?hop_project=' + encodeURIComponent(execution.project_key) +
      '&hop_entry=' + encodeURIComponent(execution.entry_file) + '&hop_engine=server';
  }

  function matchesFilters(execution) {
    var term = $.trim(String($('#hopRunSearch').val() || '')).toLowerCase();
    var state = $('#hopRunState').val() || '';
    var source = $('#hopRunSource').val() || '';
    var kind = $('#hopRunKind').val() || '';

    if (state && execution.state !== state) { return false; }
    if (source && execution.source !== source) { return false; }
    if (kind && execution.kind !== kind) { return false; }
    if (!term) { return true; }

    return [execution.display_name, execution.name, execution.project_key, execution.job_name, execution.status, execution.execution_id]
      .some(function(value) { return String(value || '').toLowerCase().indexOf(term) >= 0; });
  }

  function executionRow(execution) {
      var icon = execution.kind === 'workflow' ? 'fa-sitemap' : 'fa-exchange';
      // The Hop GUI can publish either a file inside the shared repository or a
      // zip export of a project it holds locally. Only the first can become a
      // Jenkins job, so say which one this was rather than just "unmatched".
      var exported = /^zip:/i.test(String(execution.filename || ''));
      var project = execution.project_key
        ? '<span class="hop-chip">' + escapeHtml(execution.project_key) + '</span>'
        : (exported
          ? '<span class="text-muted" title="The Apache Hop GUI publishes by uploading a zip of whatever you have open, rather than running a file under repository/hop/projects. Import it to give it a home JobSeeker can schedule.">Published as an export</span>'
          : '<span class="text-muted">No file in the repository</span>');
      var errors = parseInt(execution.errors, 10) || 0;
      // The Apache Hop GUI leaves a new file's internal <name> as "New
      // workflow", so the file name is the label and Hop's own name is only
      // shown underneath when the two differ.
      var label = execution.display_name || execution.name;
      var canvasLink = execution.entry_file
        ? '<a href="#" class="hop-view-canvas" data-execution="' + escapeHtml(execution.execution_id) + '">' +
            escapeHtml(label) + '</a>'
        : escapeHtml(label);
      var registeredAs = execution.name && execution.name !== label
        ? ' &middot; registered as ' + escapeHtml(execution.name)
        : '';
      var reuse = useInJobUrl(execution);

      return (
        '<tr>' +
        '<td>' + escapeHtml(execution.started_at || execution.first_seen_at || '') + '</td>' +
        '<td><i class="fa ' + icon + '"></i> <strong>' + canvasLink + '</strong>' +
          '<div class="hop-run-meta">' + escapeHtml(execution.kind) + registeredAs + ' &middot; ' + escapeHtml(execution.execution_id) + '</div></td>' +
        '<td>' + project + '</td>' +
        '<td>' + startedBy(execution) + '</td>' +
        '<td>' + stateLabel(execution) +
          (errors > 0 ? ' <span class="label label-danger">' + errors + ' error' + (errors === 1 ? '' : 's') + '</span>' : '') + '</td>' +
        '<td><span class="hop-run-meta">read ' + (parseInt(execution.records_total, 10) || 0) +
          ' &middot; written ' + (parseInt(execution.records_processed, 10) || 0) + '</span></td>' +
        '<td>' +
          '<button type="button" class="btn btn-xs btn-default hop-view-log" data-execution="' + escapeHtml(execution.execution_id) +
            '" data-name="' + escapeHtml(label) + '" data-hop-name="' + escapeHtml(execution.name) + '"><i class="fa fa-file-text-o"></i> Log</button> ' +
          (reuse
            ? '<a class="btn btn-xs btn-primary" href="' + reuse + '" title="Create a Jenkins job that runs this file"><i class="fa fa-share"></i> Use in job</a> '
            : (execution.importable
              ? '<button type="button" class="btn btn-xs btn-warning hop-import-execution" data-execution="' +
                  escapeHtml(execution.execution_id) + '" data-name="' + escapeHtml(label) +
                  '" title="Copy the published archive into repository/hop/projects so it can become a Jenkins job">' +
                  '<i class="fa fa-download"></i> Import</button> '
              : '')) +
          (execution.entry_file
            ? '<a class="btn btn-xs btn-default" href="' + downloadUrl + '?execution=' + encodeURIComponent(execution.execution_id) +
                '" title="Download the file to open in the Apache Hop GUI"><i class="fa fa-download"></i></a> '
            : '') +
          '<a class="btn btn-xs btn-default" href="' + tmfUrl + '" title="Open Transaction Monitoring"><i class="fa fa-line-chart"></i> TMF</a>' +
        '</td>' +
        '</tr>'
      );
  }

  function executionTableContainer() {
    return executionsTable ? $(executionsTable.table().container()) : $('#hopExecutionsTable');
  }

  function replaceExecutionRows(rows) {
    if (executionsTable) {
      executionsTable.clear();
      rows.forEach(function(execution) {
        executionsTable.row.add($(executionRow(execution))[0]);
      });
      executionsTable.draw(false);
      return;
    }

    var body = $('#hopExecutionsBody').empty();
    if (!rows.length && executions.length) {
      body.append('<tr><td colspan="7" class="text-muted" style="text-align:center;padding:24px">No run matches these filters.</td></tr>');
      return;
    }
    rows.forEach(function(execution) {
      body.append(executionRow(execution));
    });
  }

  function renderRows() {
    var rows = executions.filter(matchesFilters);

    $('#hopRunCount').text(executions.length);
    $('#hopRunSummary').text(
      executions.filter(function(row) { return row.source === 'hop-gui'; }).length + ' from the Hop GUI · ' +
      executions.filter(function(row) { return row.state === 'error'; }).length + ' failed'
    );
    $('#hopRunFilterCount').text(
      rows.length === executions.length ? '' : rows.length + ' of ' + executions.length + ' shown'
    );

    replaceExecutionRows(rows);
    if (!executions.length) {
      executionTableContainer().hide();
      $('#hopExecutionsEmpty').show();
      return;
    }

    $('#hopExecutionsEmpty').hide();
    executionTableContainer().show();
    if (executionsTable) { executionsTable.columns.adjust(); }
  }

  function applyServer(server) {
    if (!server) { return; }
    $('#hopServerBadge')
      .text(server.reachable ? 'Reachable' : 'Not running')
      .removeClass('label-success label-warning')
      .addClass(server.reachable ? 'label-success' : 'label-warning');
    $('#hopServerMessage').text(server.message || '');
  }

  function refresh(manual) {
    if (inFlight) { return; }
    inFlight = true;
    $('#hopSyncState').text('syncing…');
    $.getJSON(executionsUrl, { environment: environment })
      .done(function(response) {
        applyServer(response.server);
        executions = response.executions || [];
        renderRows();
        $('#hopSyncState').text('updated ' + new Date().toLocaleTimeString());
      })
      .fail(function() {
        $('#hopSyncState').text(manual ? 'the Hop Server could not be reached' : '');
      })
      .always(function() { inFlight = false; });
  }

  // -- log viewer ------------------------------------------------------------
  // The same grouped console the rest of the platform uses, with an Apache Hop
  // parser so the log is grouped by the transform or action that wrote it.
  $(document).on('click', '.hop-view-log', function() {
    var executionId = $(this).data('execution');
    var name = String($(this).data('name') || '');
    var hopName = String($(this).data('hop-name') || name);
    $('#hopLogTitle').html('<i class="fa fa-file-text-o"></i> ' + escapeHtml(name));
    $('#hopLogMeta').text('');
    $('#hopLogBody').text('Loading…');
    $('#hopLogModal').modal('show');

    $.getJSON(executionLogUrl, { execution: executionId })
      .done(function(response) {
        var execution = response.execution || {};
        $('#hopLogMeta').text(
          execution.kind + ' · ' + (execution.status || execution.state) +
          ' · started ' + (execution.started_at || 'unknown') +
          ' · read ' + execution.records_total + ', written ' + execution.records_processed +
          ', errors ' + execution.errors
        );
        if (window.JobSeekerConsole) {
          window.JobSeekerConsole.setText('#hopLogBody', response.log || '', {
            parser: 'hop',
            name: execution.name || hopName,
            emptyMessage: 'Apache Hop returned no log for this execution.'
          });
        } else {
          $('#hopLogBody').text(response.log || '');
        }
      })
      .fail(function(response) {
        var message = (response && response.responseJSON && response.responseJSON.error) || 'The Apache Hop log could not be read.';
        $('#hopLogBody').text(message);
      });
  });

  // -- canvas ----------------------------------------------------------------
  function openCanvas(query, title) {
    $('#hopCanvasTitle').html('<i class="fa fa-sitemap"></i> ' + escapeHtml(title));
    $('#hopCanvasMeta').text('');
    $('#hopCanvasDetail').text('');
    $('#hopCanvasBody').html('<div class="hop-canvas-empty">Loading…</div>');
    $('#hopCanvasUseInJob, #hopCanvasDownload, #hopCanvasDownloadProject').hide();
    $('#hopCanvasModal').modal('show');

    $.getJSON(graphUrl, query)
      .done(function(graph) {
        $('#hopCanvasMeta').text(
          graph.kind + ' · ' + graph.file + ' · ' + (graph.nodes || []).length +
          (graph.kind === 'workflow' ? ' action' : ' transform') + ((graph.nodes || []).length === 1 ? '' : 's') +
          ' · ' + (graph.edges || []).length + ' hop' + ((graph.edges || []).length === 1 ? '' : 's') +
          (graph.description ? ' — ' + graph.description : '')
        );
        $('#hopCanvasUseInJob')
          .attr('href', jobCreationUrl + '?hop_project=' + encodeURIComponent(graph.project) +
            '&hop_entry=' + encodeURIComponent(graph.file) + '&hop_engine=server')
          .show();
        // The file is the same XML Hop wrote, so downloading it is how a run
        // seen here gets opened in the desktop Apache Hop GUI.
        $('#hopCanvasDownload')
          .attr('href', downloadUrl + '?project=' + encodeURIComponent(graph.project) +
            '&file=' + encodeURIComponent(graph.file))
          .show();
        $('#hopCanvasDownloadProject')
          .attr('href', downloadUrl + '?project=' + encodeURIComponent(graph.project) + '&archive=1')
          .show();
        window.JobSeekerHopCanvas.render('#hopCanvasBody', graph, {
          onSelect: function(node) {
            var details = [node.type ? 'type: ' + node.type : ''];
            Object.keys(node.detail || {}).forEach(function(key) { details.push(key + ': ' + node.detail[key]); });
            if (node.description) { details.push(node.description); }
            $('#hopCanvasDetail').html('<strong>' + escapeHtml(node.name) + '</strong> — ' +
              escapeHtml(details.filter(Boolean).join(' · ')));
          }
        });
      })
      .fail(function(response) {
        var message = (response && response.responseJSON && response.responseJSON.error) || 'The Apache Hop canvas could not be read.';
        $('#hopCanvasBody').html('<div class="hop-canvas-empty">' + escapeHtml(message) + '</div>');
      });
  }

  $(document).on('click', '.hop-view-canvas', function(event) {
    event.preventDefault();
    var executionId = $(this).data('execution');
    var execution = executions.filter(function(row) { return row.execution_id === executionId; })[0] || {};
    openCanvas({ execution: executionId }, execution.display_name || execution.name || 'Apache Hop canvas');
  });

  $(document).on('click', '.hop-project-canvas', function(event) {
    event.preventDefault();
    openCanvas({ project: $(this).data('project'), file: $(this).data('file') }, $(this).data('file'));
  });

  // -- filters ---------------------------------------------------------------
  $(document).on('input change', '#hopRunSearch, #hopRunState, #hopRunSource, #hopRunKind', renderRows);

  // A run published as a zip export has no file in the repository, so it cannot
  // be scheduled until the archive is imported. That is one click rather than a
  // paragraph of instructions.
  $(document).on('click', '.hop-import-execution', function() {
    var button = $(this).prop('disabled', true);
    var executionId = button.data('execution');
    var suggested = String(button.data('name') || '').toLowerCase().replace(/[^a-z0-9._-]+/g, '-').replace(/^-+|-+$/g, '');
    var name = window.prompt('Import this run into repository/hop/projects as:', suggested || 'imported-hop-project');
    if (!name) {
      button.prop('disabled', false);
      return;
    }

    $.post(importExecutionUrl, { execution: executionId, project: name })
      .done(function(response) {
        toastr.success('Imported as ' + response.project + ' / ' + response.entry_file, 'Apache Hop');
        refresh(true);
      })
      .fail(function(response) {
        var message = (response && response.responseJSON && response.responseJSON.error) || 'The published run could not be imported.';
        toastr.error(message, 'Apache Hop');
        button.prop('disabled', false);
      });
  });

  $(document).on('click', '#hopRefreshExecutions', function() { refresh(true); });

  // -- connections -----------------------------------------------------------
  function publishConnections(withdraw) {
    var buttons = $('#hopPublishConnections, #hopWithdrawConnections').prop('disabled', true);
    $('#hopConnectionsResult').text(withdraw ? 'Withdrawing…' : 'Publishing…');
    $.post(publishConnectionsUrl, { withdraw: withdraw ? '1' : '0', environment: serverEnvironment })
      .done(function(response) {
        var published = (response && response.published) || [];
        var skipped = (response && response.skipped) || [];
        var message = published.length
          ? published.length + ' connection' + (published.length === 1 ? '' : 's') + ' published: ' + published.join(', ')
          : 'No connection is published to the Hop Server.';
        if (skipped.length) {
          message += ' — skipped ' + skipped.map(function(item) { return item.key + ' (' + item.reason + ')'; }).join('; ');
        }
        $('#hopConnectionsResult').text(message);
        window.setTimeout(function() { window.location.reload(); }, 1200);
      })
      .fail(function(response) {
        var error = (response && response.responseJSON && response.responseJSON.error) || 'The Hop Server connections could not be changed.';
        $('#hopConnectionsResult').text(error);
        buttons.prop('disabled', false);
      });
  }

  $(document).on('click', '#hopPublishConnections', function() { publishConnections(false); });
  $(document).on('click', '#hopWithdrawConnections', function() { publishConnections(true); });

  // -- projects --------------------------------------------------------------
  $(document).on('click', '.hop-delete-project', function() {
    pending = $(this).data('project');
    $('#hopDeleteName').text($(this).data('name'));
    $('#hopDeleteFiles').prop('checked', false);
    $('#hopDeleteError').hide().text('');
    $('#hopDeleteModal').modal('show');
  });

  $(document).on('click', '#hopDeleteConfirm', function() {
    if (!pending) { return; }
    var button = $(this).prop('disabled', true);
    $.post(deleteUrl, { project: pending, remove_files: $('#hopDeleteFiles').is(':checked') ? '1' : '0' })
      .done(function() { window.location.reload(); })
      .fail(function(response) {
        var message = (response && response.responseJSON && response.responseJSON.error) || 'The Apache Hop project could not be removed.';
        $('#hopDeleteError').text(message).show();
        button.prop('disabled', false);
      });
  });

  renderRows();

  // A run published from the Apache Hop GUI has no other way of reaching this
  // screen, so the page polls while it is open. It stops while the tab is
  // hidden rather than reconciling a server for a page nobody is looking at.
  setInterval(function() {
    if (!document.hidden) { refresh(false); }
  }, 20000);

  // DataTables is loaded by the shared footer after this view. Deferring setup
  // until DOM ready ensures both growing lists actually receive pagination.
  $(function() {
    if (!$.fn.DataTable) { return; }

    if ($('#hopExecutionsTable').length) {
      executionsTable = $('#hopExecutionsTable').DataTable({
        paging: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        searching: false,
        ordering: true,
        order: [[0, 'desc']],
        info: true,
        autoWidth: false,
        deferRender: true,
        columnDefs: [{targets: 6, orderable: false}],
        language: {
          emptyTable: 'The Hop Server has not run anything yet.',
          zeroRecords: 'No run matches these filters.'
        }
      });
      if (!executions.length) { executionTableContainer().hide(); }
    }

    if ($('#hopProjectsTable').length) {
      projectsTable = $('#hopProjectsTable').DataTable({
        paging: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        searching: true,
        ordering: true,
        order: [],
        info: true,
        autoWidth: false,
        deferRender: true,
        columnDefs: [{targets: 5, orderable: false}]
      });

      // Filters over the columns a person actually narrows by. DataTables' own
      // search stays the free-text field.
      $.fn.dataTable.ext.search.push(function(settings, data, index, row, counter) {
        if (settings.nTable !== $('#hopProjectsTable')[0]) { return true; }
        var node = $(projectsTable.row(index).node());
        var environmentFilter = $('#hopProjectEnvironment').val() || '';
        var healthFilter = $('#hopProjectHealth').val() || '';

        if (environmentFilter && node.data('environment') !== environmentFilter) { return false; }
        if (healthFilter === 'attention' && String(node.data('attention')) !== '1') { return false; }
        if (healthFilter === 'used' && String(node.data('used')) !== '1') { return false; }
        if (healthFilter === 'unused' && String(node.data('used')) === '1') { return false; }
        return true;
      });

      $(document).on('change', '#hopProjectEnvironment, #hopProjectHealth', function() {
        projectsTable.draw();
      });
    }
  });
})();
</script>
