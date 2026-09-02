<?php $mlAuthoringId = 'mlJobAuthoring'; ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=2">
<div class="content-wrapper">
  <section class="content-header"><h1>ML Jobs <small>workspace-backed Python jobs</small></h1></section>
  <section class="content">
    <?php if (empty($engineHealthy)): ?>
      <div class="callout callout-warning">The compute engine (<?php echo html_escape($driverName); ?>) is not reachable. Jobs can be authored but not built or run.</div>
    <?php endif; ?>

    <div class="row">
      <div class="col-md-3">
        <div class="box box-primary">
          <div class="box-header with-border"><h3 class="box-title">Jobs</h3>
            <div class="box-tools"><a href="<?php echo base_url(); ?>machine-learning/jobs" class="btn btn-xs btn-primary">New</a></div></div>
          <div class="box-body no-padding">
            <table class="table table-hover">
              <tbody>
              <?php foreach ($jobs as $j): ?>
                <tr<?php echo $job && $job->id == $j->id ? ' class="active"' : ''; ?>>
                  <td><a href="<?php echo base_url(); ?>machine-learning/jobs?id=<?php echo (int) $j->id; ?>"><?php echo html_escape($j->name); ?></a>
                    <div class="ml-muted" style="font-size:11px">
                      <span class="ml-badge <?php echo html_escape($j->run_type); ?>"><?php echo html_escape($j->run_type); ?></span>
                      <?php echo html_escape($j->environment); ?> ·
                      <span class="ml-image-pill <?php echo html_escape($j->image_state); ?>" style="font-size:10px">img: <?php echo html_escape($j->image_state); ?></span>
                    </div></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($jobs)): ?><tr><td class="ml-muted">No jobs yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-md-9">
        <div class="box box-default">
          <div class="box-header with-border"><h3 class="box-title"><?php echo $job ? 'Edit: '.html_escape($job->name) : 'New ML job'; ?></h3></div>
          <div class="box-body">
            <?php $this->load->view('includes/mlJobAuthoring', array('mlAuthoringId' => $mlAuthoringId)); ?>
          </div>
        </div>

        <?php if ($job): ?>
        <div class="box box-default">
          <div class="box-header with-border"><h3 class="box-title">Recent runs</h3></div>
          <div class="box-body no-padding">
            <table class="table">
              <thead><tr><th>Run</th><th>Type</th><th>Status</th><th>Started</th><th>Metrics</th></tr></thead>
              <tbody>
              <?php foreach ($recentRuns as $run): ?>
                <tr>
                  <td><a href="<?php echo base_url(); ?>machine-learning/runs/detail/<?php echo (int) $run->id; ?>"><?php echo html_escape(substr($run->run_key, 0, 8)); ?></a>
                    <?php if ($run->trigger_source === 'preview'): ?><span class="ml-muted">(test)</span><?php endif; ?></td>
                  <td><span class="ml-badge <?php echo html_escape($run->run_type); ?>"><?php echo html_escape($run->run_type); ?></span></td>
                  <td><span class="ml-status <?php echo html_escape($run->status); ?>"><?php echo html_escape($run->status); ?></span></td>
                  <td class="ml-muted ml-nowrap"><?php echo html_escape($run->started_at); ?></td>
                  <td class="ml-mono" style="font-size:11px"><?php echo html_escape($run->metrics_summary_json); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($recentRuns)): ?><tr><td colspan="5" class="ml-muted">No runs yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<script id="mlJobsBootstrap" type="application/json"><?php echo json_encode(array(
  'environments' => $environments,
  'runtimes' => $runtimes,
  'samples' => $samples,
  'experiments' => $experiments,
), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?></script>
<script src="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=2"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-ui.js?v=2"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-job-authoring.js?v=2"></script>
<script>
jQuery(function () {
  var boot = JSON.parse(document.getElementById('mlJobsBootstrap').textContent);
  MlJobAuthoring.mount('#mlJobAuthoring', {
    bootstrap: boot
    <?php if ($job): ?>, editJobId: <?php echo (int) $job->id; ?><?php endif; ?>
  });
});
</script>
