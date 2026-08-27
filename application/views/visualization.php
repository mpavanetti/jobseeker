<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/visualization.css?v=1">
<?php
$report = !empty($view) ? $view[0] : NULL;
$typeLabels = array('pbi' => 'Microsoft Power BI', 'tbl' => 'Tableau', 'tblPublic' => 'Tableau Public', 'qlikSense' => 'Qlik Sense', 'qlikView' => 'QlikView', 'superset' => 'Apache Superset', 'metabase' => 'Metabase', 'grafana' => 'Grafana', 'looker' => 'Looker', 'microstrategy' => 'MicroStrategy', 'custom' => 'Connected analytics');
$typeLabel = $report && isset($typeLabels[$report->type]) ? $typeLabels[$report->type] : ($report ? $report->type : 'Connected analytics');
?>
<div class="content-wrapper viz-page">
  <section class="content">
    <div class="viz-shell">
      <div class="viz-viewer-bar">
        <div class="viz-viewer-title">
          <a class="viz-btn viz-btn-light" href="<?php echo base_url(); ?>Visualization" aria-label="Back to visualization hub"><i class="fa fa-arrow-left"></i></a>
          <div><h1><?php echo html_escape($report ? $report->name : $reportName); ?></h1><p><span class="viz-live-dot"></span><?php echo html_escape($typeLabel); ?> &middot; isolated connected report</p></div>
        </div>
        <div>
          <button id="vizReloadReport" class="viz-btn viz-btn-light" type="button"><i class="fa fa-refresh"></i> Refresh</button>
          <a id="vizOpenOrigin" class="viz-btn viz-btn-light" href="#" target="_blank" rel="noopener noreferrer" style="display:none"><i class="fa fa-external-link"></i> Open source</a>
        </div>
      </div>
      <div id="vizReportFrame" class="viz-viewer-frame">
        <?php if($report && $report->code !== '') { echo $report->code; } else { ?>
          <div class="viz-viewer-empty"><div><i class="fa fa-unlink fa-2x"></i><h3>Report unavailable</h3><p>The connection is empty or no longer passes the embed safety policy.</p></div></div>
        <?php } ?>
      </div>
      <div class="viz-safety" style="margin-top:14px">
        <i class="fa fa-shield"></i>
        <div><h3>External content boundary</h3><p>This report runs in a sandboxed frame with a no-referrer policy. Authentication stays with the report provider.</p></div>
        <span class="viz-safety-point"><i class="fa fa-check"></i> No credentials stored here</span>
        <span class="viz-safety-point"><i class="fa fa-check"></i> Restricted frame capabilities</span>
      </div>
    </div>
  </section>
</div>
<script>
$(function() {
  $('body').addClass('sidebar-collapse');
  var frame = document.querySelector('#vizReportFrame iframe');
  if (!frame) return;
  $('#vizOpenOrigin').attr('href', frame.src).show();
  $('#vizReloadReport').on('click', function() {
    frame.src = frame.src;
    toastr.info('Refreshing connected report…');
  });
});
</script>
