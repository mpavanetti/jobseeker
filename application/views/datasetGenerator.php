<style>
  .dataset-generator-hero {
    align-items: center;
    background: linear-gradient(135deg, #1d2731, #36566f);
    border-radius: 7px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    margin-bottom: 18px;
    padding: 22px 25px;
  }
  .dataset-generator-hero h2 { font-size: 22px; margin: 0 0 5px; }
  .dataset-generator-hero p { color: #d9e6ef; margin: 0; }
  .dataset-generator-badge { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.24); border-radius: 20px; padding: 7px 12px; }
  .dataset-total-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 18px; }
  .dataset-total { background: #fff; border-left: 4px solid #3c8dbc; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 14px 16px; }
  .dataset-total small { color: #72808b; display: block; text-transform: uppercase; }
  .dataset-total strong { color: #263746; display: block; font-size: 23px; margin-top: 3px; }
  .dataset-profile-grid { display: grid; gap: 10px; grid-template-columns: repeat(3, 1fr); margin-bottom: 14px; }
  .dataset-profile { border: 1px solid #dbe2e8; border-radius: 5px; cursor: pointer; display: block; padding: 12px; }
  .dataset-profile:hover, .dataset-profile.is-selected { background: #f2f9fd; border-color: #3c8dbc; }
  .dataset-profile input { margin-right: 5px; }
  .dataset-profile strong { display: block; margin-bottom: 4px; }
  .dataset-profile span { color: #6d7983; display: block; font-size: 12px; line-height: 1.45; }
  .dataset-generator-limits { background: #f7f9fa; border: 1px solid #e0e6ea; border-radius: 5px; margin: 12px 0 18px; padding: 14px; }
  .dataset-generator-limits h4 { margin: 0 0 12px; }
  .dataset-estimate { background: #edf7ed; border-left: 3px solid #00a65a; margin-top: 12px; padding: 10px 12px; }
  .dataset-metric { white-space: nowrap; }
  .dataset-batch-key { font-family: Menlo, Monaco, Consolas, monospace; }
  @media (max-width: 767px) {
    .dataset-generator-hero { align-items: flex-start; flex-direction: column; gap: 12px; }
    .dataset-profile-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>Dataset Generator <small>Admin control panel</small></h1>
    <ol class="breadcrumb"><li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-dashboard"></i> Home</a></li><li class="active">Dataset Generator</li></ol>
  </section>
  <section class="content">
    <?php foreach (array('success' => 'success', 'warning' => 'warning', 'error' => 'danger') as $flashKey => $alertClass) {
      $message = $this->session->flashdata($flashKey);
      if ($message) { ?>
        <div class="alert alert-<?php echo $alertClass; ?> alert-dismissable"><button type="button" class="close" data-dismiss="alert">&times;</button><?php echo html_escape($message); ?></div>
    <?php } } ?>

    <div class="dataset-generator-hero">
      <div><h2><i class="fa fa-database"></i> Repeatable performance data</h2><p>Create TMF history, failures, job identities, pipelines, and run history as one removable batch.</p></div>
      <span class="dataset-generator-badge"><i class="fa fa-lock"></i> Administrators only</span>
    </div>

    <div class="dataset-total-grid">
      <div class="dataset-total"><small>TMF rows</small><strong><?php echo number_format($totals['tmf']); ?></strong></div>
      <div class="dataset-total"><small>TMF errors</small><strong><?php echo number_format($totals['tmf_errors']); ?></strong></div>
      <div class="dataset-total"><small>Pipelines</small><strong><?php echo number_format($totals['pipelines']); ?></strong></div>
      <div class="dataset-total"><small>Pipeline runs</small><strong><?php echo number_format($totals['pipeline_runs']); ?></strong></div>
      <div class="dataset-total"><small>Generated batches</small><strong><?php echo number_format($totals['generated_batches']); ?></strong></div>
    </div>

    <div class="row">
      <div class="col-lg-5 col-md-12">
        <div class="box box-primary">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plus-circle"></i> Create a batch</h3></div>
          <form method="post" action="<?php echo base_url(); ?>dataset-generator/create" id="datasetGeneratorForm">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
            <div class="box-body">
              <label>Profile</label>
              <div class="dataset-profile-grid">
                <?php foreach ($profiles as $profileKey => $profile) { ?>
                  <label class="dataset-profile<?php echo $profileKey === 'performance' ? ' is-selected' : ''; ?>">
                    <strong><input type="radio" name="profile" value="<?php echo html_escape($profileKey); ?>"<?php echo $profileKey === 'performance' ? ' checked' : ''; ?>> <?php echo html_escape($profile['name']); ?></strong>
                    <span><?php echo html_escape($profile['description']); ?></span>
                  </label>
                <?php } ?>
              </div>
              <div class="form-group">
                <label for="batch_key">Batch key</label>
                <input class="form-control dataset-batch-key" id="batch_key" name="batch_key" value="<?php echo html_escape($suggestedBatchKey); ?>" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,31}" required>
                <p class="help-block">Used to identify every generated row and Jenkins job for exact cleanup.</p>
              </div>
              <div class="row">
                <div class="col-sm-8"><div class="form-group"><label for="environments">Environments</label><input class="form-control" id="environments" name="environments" value="DEV,QA,UAT,PROD" maxlength="100" required></div></div>
                <div class="col-sm-4"><div class="form-group"><label for="seed">Seed</label><input type="number" class="form-control" id="seed" name="seed" value="42" min="1" max="2147483647" required></div></div>
              </div>

              <?php $defaultProfile = $profiles['performance']; ?>
              <div class="dataset-generator-limits">
                <h4><i class="fa fa-sliders"></i> Volume overrides</h4>
                <div class="row">
                  <div class="col-sm-6"><div class="form-group"><label for="tmf_rows">TMF rows</label><input type="number" class="form-control dataset-volume" id="tmf_rows" name="tmf_rows" min="1" max="250000" value="<?php echo (int) $defaultProfile['tmf_rows']; ?>" required></div></div>
                  <div class="col-sm-6"><div class="form-group"><label for="jobs">Job identities</label><input type="number" class="form-control dataset-volume" id="jobs" name="jobs" min="1" max="200" value="<?php echo (int) $defaultProfile['jobs']; ?>" required></div></div>
                  <div class="col-sm-6"><div class="form-group"><label for="pipelines">Pipelines</label><input type="number" class="form-control dataset-volume" id="pipelines" name="pipelines" min="1" max="50" value="<?php echo (int) $defaultProfile['pipelines']; ?>" required></div></div>
                  <div class="col-sm-6"><div class="form-group"><label for="pipeline_runs">Runs per pipeline</label><input type="number" class="form-control dataset-volume" id="pipeline_runs" name="pipeline_runs" min="1" max="500" value="<?php echo (int) $defaultProfile['pipeline_runs']; ?>" required></div></div>
                </div>
                <div class="dataset-estimate" id="datasetEstimate"></div>
              </div>

              <div class="checkbox">
                <label><input type="checkbox" name="include_jenkins" value="1" id="include_jenkins"<?php echo empty($jenkins_enabled) ? ' disabled' : ''; ?>> Also create/update the generated job identities in Jenkins</label>
                <p class="help-block"><?php echo empty($jenkins_enabled) ? 'Jenkins integration is disabled in this deployment.' : 'Jobs rotate through runtime, TMF/context, Data Asset, connector, and pipeline-ready samples. They are configured but not triggered.'; ?></p>
              </div>
              <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Large batches intentionally put load on MariaDB and application queries. Run stress profiles only in a disposable or performance-test environment.</div>
            </div>
            <div class="box-footer"><button type="submit" class="btn btn-primary" id="generateDataset"><i class="fa fa-cogs"></i> Generate Dataset</button></div>
          </form>
        </div>
      </div>

      <div class="col-lg-7 col-md-12">
        <div class="box box-default">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-history"></i> Generated batches</h3></div>
          <div class="box-body table-responsive">
            <table class="table table-striped table-hover">
              <thead><tr><th>Batch</th><th>Profile / status</th><th>Rows</th><th>Generation performance</th><th>Created</th><th></th></tr></thead>
              <tbody>
              <?php if (empty($batches)) { ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:30px;">No generated batches yet.</td></tr>
              <?php } foreach ($batches as $batch) {
                $metrics = json_decode((string) $batch->metrics_json, TRUE);
                if (! is_array($metrics)) $metrics = array();
                $statusClass = $batch->status === 'ready' ? 'success' : ($batch->status === 'partial' ? 'warning' : 'default');
              ?>
                <tr>
                  <td><strong class="dataset-batch-key"><?php echo html_escape($batch->batch_key); ?></strong><br><small><?php echo html_escape($batch->created_by); ?></small></td>
                  <td><span class="label label-<?php echo $statusClass; ?>"><?php echo html_escape($batch->status); ?></span><br><small><?php echo html_escape($batch->profile); ?> / seed <?php echo (int) $batch->seed_value; ?></small></td>
                  <td class="dataset-metric"><?php echo number_format($batch->tmf_rows); ?> TMF<br><small><?php echo number_format($batch->error_rows); ?> errors / <?php echo number_format($batch->pipeline_count); ?> pipelines / <?php echo number_format($batch->pipeline_run_rows); ?> runs</small></td>
                  <td class="dataset-metric"><?php echo isset($metrics['database_seconds']) ? number_format($metrics['database_seconds'], 3).'s' : '&mdash;'; ?><br><small><?php echo isset($metrics['tmf_rows_per_second']) ? number_format($metrics['tmf_rows_per_second'], 1).' TMF rows/s' : ''; ?><?php echo ! empty($batch->include_jenkins) ? ' / '.(int) ($metrics['jenkins_created'] ?? 0).' Jenkins jobs' : ''; ?></small></td>
                  <td><?php echo html_escape($batch->created_at); ?></td>
                  <td>
                    <form method="post" action="<?php echo base_url(); ?>dataset-generator/delete" class="delete-generated-dataset">
                      <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                      <input type="hidden" name="batch_id" value="<?php echo (int) $batch->id; ?>">
                      <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> Remove</button>
                    </form>
                  </td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="box box-info">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-terminal"></i> Automation</h3></div>
          <div class="box-body"><p>The same workload is available from the repository for CI and repeatable command-line profiling:</p><pre>python3 scripts/generate-performance-dataset.py seed --profile performance --batch-key perf-ci --apply
python3 scripts/generate-performance-dataset.py cleanup --batch-key perf-ci --apply</pre><p class="help-block">The script requires <code>--apply</code> before it changes MariaDB or Jenkins.</p></div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
(function($) {
  var profiles = <?php echo json_encode($profiles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  function number(value) { return parseInt(value, 10) || 0; }
  function renderEstimate() {
    var rows = number($('#tmf_rows').val());
    var jobs = number($('#jobs').val());
    var pipelines = number($('#pipelines').val());
    var runs = number($('#pipeline_runs').val()) * pipelines;
    $('#datasetEstimate').html('<strong>Planned batch:</strong> ' + rows.toLocaleString() + ' TMF rows, approximately ' + Math.round(rows * .12).toLocaleString() + ' error rows, ' + jobs.toLocaleString() + ' job identities, ' + pipelines.toLocaleString() + ' pipelines, and ' + runs.toLocaleString() + ' pipeline runs.');
  }

  $('input[name="profile"]').on('change', function() {
    var profile = profiles[this.value];
    $('.dataset-profile').removeClass('is-selected');
    $(this).closest('.dataset-profile').addClass('is-selected');
    if (profile) {
      $('#tmf_rows').val(profile.tmf_rows);
      $('#jobs').val(profile.jobs);
      $('#pipelines').val(profile.pipelines);
      $('#pipeline_runs').val(profile.pipeline_runs);
    }
    renderEstimate();
  });
  $('.dataset-volume').on('input change', renderEstimate);
  renderEstimate();

  $('#datasetGeneratorForm').on('submit', function(event) {
    var rows = number($('#tmf_rows').val());
    var jobs = number($('#jobs').val());
    var extra = $('#include_jenkins').is(':checked') ? ' and create/update ' + jobs + ' Jenkins jobs' : '';
    if (! window.confirm('Generate ' + rows.toLocaleString() + ' TMF rows' + extra + '?')) event.preventDefault();
    else $('#generateDataset').prop('disabled', true).html('<i class="fa fa-refresh fa-spin"></i> Generating…');
  });
  $('.delete-generated-dataset').on('submit', function(event) {
    if (! window.confirm('Remove this generated batch, its TMF/error history, pipelines, run history, and tracked Jenkins jobs?')) event.preventDefault();
  });
})(jQuery);
</script>
