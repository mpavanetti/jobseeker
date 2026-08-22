<?php
$jobs = isset($jobs) ? (array) $jobs : array();
$ready = 0;
$error = 0;
$warning = 0;
$running = 0;
$cancelled = 0;
$totalRecords = 0;
$totalProcessed = 0;
$jobNames = array();
$environments = array();
$attentionRows = array();
$slowestRows = array();
$staleRunning = 0;
$latestActivityTimestamp = null;

if (!function_exists('formatTmfDuration')) {
  function formatTmfDuration($seconds) {
    $seconds = max(0, (int) $seconds);
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $remainingSeconds = $seconds % 60;

    if ($hours > 0) {
      return $hours.'h '.$minutes.'m';
    }

    if ($minutes > 0) {
      return $minutes.'m '.$remainingSeconds.'s';
    }

    return $remainingSeconds.'s';
  }
}

foreach($jobs as $tmfJob) {
  $status = strtolower((string) $tmfJob->status);
  $recordsTotal = isset($tmfJob->records_total) ? (int) $tmfJob->records_total : 0;
  $recordsProcessed = isset($tmfJob->records_processed) ? (int) $tmfJob->records_processed : 0;
  $startTimestamp = !empty($tmfJob->start_time) ? strtotime($tmfJob->start_time) : false;
  $lastTimestamp = !empty($tmfJob->last_activity) ? strtotime($tmfJob->last_activity) : false;
  $durationSeconds = ($startTimestamp !== false && $lastTimestamp !== false) ? max(0, $lastTimestamp - $startTimestamp) : 0;

  switch ($status) {
    case 'ready':
      $ready++;
      break;
    case 'running':
      $running++;
      break;
    case 'error':
      $error++;
      break;
    case 'warning':
      $warning++;
      break;
    case 'cancelled':
      $cancelled++;
      break;
  }

  $totalRecords += $recordsTotal;
  $totalProcessed += $recordsProcessed;

  if (!empty($tmfJob->job_name)) {
    $jobNames[$tmfJob->job_name] = true;
  }

  if (!empty($tmfJob->environment)) {
    $environments[$tmfJob->environment] = true;
  }

  if ($lastTimestamp !== false && ($latestActivityTimestamp === null || $lastTimestamp > $latestActivityTimestamp)) {
    $latestActivityTimestamp = $lastTimestamp;
  }

  if (in_array($status, array('error', 'warning', 'running'), TRUE)) {
    $attentionRows[] = array(
      'id' => $tmfJob->id,
      'status' => $status,
      'job_name' => $tmfJob->job_name,
      'environment' => $tmfJob->environment,
      'last_activity' => $lastTimestamp !== false ? date('m-d-Y H:i:s', $lastTimestamp) : 'No activity'
    );
  }

  if ($status == 'running' && $lastTimestamp !== false && (time() - $lastTimestamp) > 900) {
    $staleRunning++;
  }

  if ($durationSeconds > 0) {
    $slowestRows[] = array(
      'job_name' => $tmfJob->job_name,
      'environment' => $tmfJob->environment,
      'duration' => $durationSeconds
    );
  }
}

$totalJobs = count($jobs);
$throughputRate = $totalRecords > 0 ? min(100, round(($totalProcessed / $totalRecords) * 100)) : 0;
$latestActivityLabel = $latestActivityTimestamp !== null ? date('m-d-Y H:i:s', $latestActivityTimestamp) : 'No activity';
usort($slowestRows, function($left, $right) {
  return $right['duration'] - $left['duration'];
});
$slowestRows = array_slice($slowestRows, 0, 5);
?>
 <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
  $( function() {
    $( document ).tooltip();
  } );
</script>
<style>
    .digital-clock {
      /*margin: auto; 
      position: absolute;*/
      top: 0;
      left: 0;
      bottom: 0;
      right: 0;
      width: 180px;
      height: 50px;
      color: #00000;
    /*  border: 2px solid #999; */
      border-radius: 10px;
      text-align: center;
      font: 40px/50px 'DIGITAL', Helvetica;
     /* background: linear-gradient(90deg, #4A00E0, #000); */
}

pre { 
    white-space: pre-wrap; 
    word-break: break-word;
  max-width: 980px;
}

.tmf-results-page .content {
  padding: 18px;
}

.tmf-results-shell {
  max-width: 1640px;
  width: 100%;
}

.tmf-results-toolbar {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-top: 15px;
}

.tmf-results-summary {
  margin-top: 18px;
}

.tmf-ops-card,
.tmf-metric {
  background: #fff;
  border: 1px solid #d8e0e8;
  border-radius: 6px;
  box-shadow: 0 8px 20px rgba(16, 42, 67, .08);
  padding: 16px;
}

.tmf-ops-card {
  min-height: 214px;
}

.tmf-ops-card h3 {
  font-size: 18px;
  font-weight: 700;
  margin: 0 0 12px;
}

.tmf-ops-card h3 small {
  color: #829ab1;
  display: block;
  font-size: 12px;
  font-weight: 400;
  margin-top: 3px;
}

.tmf-quick-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.tmf-attention-list,
.tmf-slowest-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.tmf-attention-list li,
.tmf-slowest-list li {
  align-items: center;
  border-top: 1px solid #edf1f5;
  display: flex;
  gap: 10px;
  justify-content: space-between;
  padding: 8px 0;
}

.tmf-attention-list li:first-child,
.tmf-slowest-list li:first-child {
  border-top: 0;
}

.tmf-attention-main,
.tmf-slowest-main {
  min-width: 0;
}

.tmf-attention-title,
.tmf-slowest-title {
  color: #102a43;
  display: block;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tmf-attention-meta,
.tmf-slowest-meta {
  color: #829ab1;
  display: block;
  font-size: 12px;
}

.tmf-empty-note {
  border: 1px dashed #d8e0e8;
  border-radius: 4px;
  color: #829ab1;
  padding: 16px;
  text-align: center;
}

.tmf-throughput-bar {
  background: #edf2f7;
  border-radius: 12px;
  height: 12px;
  margin-top: 12px;
  overflow: hidden;
}

.tmf-throughput-fill {
  background: linear-gradient(90deg, #2f855a, #38a169);
  height: 100%;
}

.tmf-metric-label {
  color: #6b7c8f;
  display: block;
  font-size: 12px;
  letter-spacing: .03em;
  text-transform: uppercase;
}

.tmf-metric-value {
  color: #102a43;
  display: block;
  font-size: 28px;
  font-weight: 700;
  margin-top: 4px;
}

.tmf-metric-foot {
  color: #829ab1;
  display: block;
  margin-top: 4px;
}

.tmf-status-strip {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 16px 0 6px;
}

.tmf-status-pill {
  border-radius: 16px;
  color: #fff;
  display: inline-flex;
  font-weight: 700;
  gap: 6px;
  padding: 5px 10px;
}

.tmf-status-ready { background: #2f855a; }
.tmf-status-running { background: #2b6cb0; }
.tmf-status-error { background: #c53030; }
.tmf-status-warning { background: #b7791f; }
.tmf-status-cancelled { background: #718096; }

.tmf-results-page .box {
  border-radius: 6px;
  box-shadow: 0 10px 24px rgba(16, 42, 67, .08);
}

.tmf-results-page .box-header {
  border-bottom: 1px solid #edf1f5;
}

.tmf-results-page #table6 th {
  color: #243b53;
  font-size: 12px;
  letter-spacing: .02em;
  text-transform: uppercase;
  white-space: nowrap;
}

.tmf-results-page #table6 td {
  vertical-align: middle;
}

.tmf-results-page #table6 td:nth-child(6),
.tmf-results-page #table6 td:nth-child(8) {
  max-width: 360px;
  white-space: normal;
  word-break: break-word;
}

.tmf-table-wrap {
  overflow: hidden;
}

.tmf-row-error > td { background: #fff5f5 !important; }
.tmf-row-warning > td { background: #fffaf0 !important; }
.tmf-row-running > td { background: #ebf8ff !important; }

@media (max-width: 767px) {
  .tmf-results-toolbar {
    align-items: flex-start;
    flex-direction: column;
    gap: 12px;
  }
}
</style>
<div class="content-wrapper tmf-results-page">
    <section class="content-header">
      <h1>
        Transaction Monitoring Framework
        <small>Log your job transactions</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Transaction Monitoring</a></li>
      </ol>
    </section>

    <div id="loading">
      <div class="row" style="margin-top: 15px; display: none;">
        <div class="container text-center">
          <img class="img img-responsive" src="<?php echo base_url(); ?>assets/images/gifs/loading.gif" style="display: inline;">
          <div class="col-lg-12 col-md-12 col-xs-12">
            <img class="img img-responsive" src="<?php echo base_url(); ?>assets/images/gifs/dashboard.gif" style="display: inline;">
          </div>    
        </div>
      </div>
    </div>

    <!-- Main content -->
    <section id="main" class="content tmf-results-content">
      <div class="container-fluid tmf-results-shell">
        <div class="tmf-results-toolbar">
          <a href="<?php echo base_url(); ?>Tmf" class="btn btn-warning"><i class="fa fa-arrow-left"></i> Back to Query</a>
          <div class="digital-clock">00:00:00</div>
        </div>
        <div class="row tmf-results-summary">
          <div class="col-lg-8 col-md-12 col-xs-12">
            <div class="tmf-ops-card">
              <h3><i class="fa fa-heartbeat"></i> Triage Board <small><?php echo number_format($totalJobs); ?> rows across <?php echo count($jobNames); ?> jobs and <?php echo count($environments); ?> environments</small></h3>
              <div class="tmf-quick-filters">
                <button type="button" class="btn btn-default btn-sm tmf-table-filter" data-filter="all"><i class="fa fa-list"></i> All</button>
                <button type="button" class="btn btn-danger btn-sm tmf-table-filter" data-filter="error"><i class="fa fa-times"></i> Errors</button>
                <button type="button" class="btn btn-warning btn-sm tmf-table-filter" data-filter="warning"><i class="fa fa-warning"></i> Warnings</button>
                <button type="button" class="btn btn-info btn-sm tmf-table-filter" data-filter="running"><i class="fa fa-refresh"></i> Running</button>
                <button type="button" class="btn btn-primary btn-sm tmf-table-filter" data-filter="attention"><i class="fa fa-bell"></i> Attention</button>
              </div>
              <?php if(!empty($attentionRows)) { ?>
              <ul class="tmf-attention-list">
                <?php foreach(array_slice($attentionRows, 0, 5) as $attentionRow) { ?>
                <li>
                  <span class="tmf-attention-main">
                    <span class="tmf-attention-title"><?php echo html_escape($attentionRow['job_name']); ?></span>
                    <span class="tmf-attention-meta">#<?php echo (int) $attentionRow['id']; ?> in <?php echo html_escape($attentionRow['environment']); ?> &middot; last activity <?php echo html_escape($attentionRow['last_activity']); ?></span>
                  </span>
                  <span class="label label-<?php echo ($attentionRow['status'] == 'error') ? 'danger' : (($attentionRow['status'] == 'warning') ? 'warning' : 'info'); ?>"><?php echo html_escape(ucfirst($attentionRow['status'])); ?></span>
                </li>
                <?php } ?>
              </ul>
              <?php } else { ?>
              <div class="tmf-empty-note">No errors, warnings, or running rows in this result set.</div>
              <?php } ?>
            </div>
          </div>
          <div class="col-lg-4 col-md-12 col-xs-12">
            <div class="tmf-ops-card">
              <h3><i class="fa fa-tachometer"></i> Run Signals <small>Latest activity <?php echo html_escape($latestActivityLabel); ?></small></h3>
              <span class="tmf-metric-label">Throughput</span>
              <span class="tmf-metric-value"><?php echo $throughputRate; ?>%</span>
              <span class="tmf-metric-foot"><?php echo number_format($totalProcessed); ?> processed of <?php echo number_format($totalRecords); ?> records</span>
              <div class="tmf-throughput-bar"><div class="tmf-throughput-fill" style="width: <?php echo $throughputRate; ?>%;"></div></div>
              <div class="tmf-status-strip">
                <span class="tmf-status-pill tmf-status-running"><i class="fa fa-refresh"></i><?php echo number_format($running); ?> running</span>
                <span class="tmf-status-pill tmf-status-error"><i class="fa fa-clock-o"></i><?php echo number_format($staleRunning); ?> stale</span>
              </div>
            </div>
          </div>
        </div>
        <div class="row" style="margin-top: 14px;">
          <div class="col-lg-12 col-md-12 col-xs-12">
            <div class="tmf-ops-card">
              <h3><i class="fa fa-sort-amount-desc"></i> Slowest Runs <small>Use this to spot expensive jobs in the current query result</small></h3>
              <?php if(!empty($slowestRows)) { ?>
              <ul class="tmf-slowest-list">
                <?php foreach($slowestRows as $slowestRow) { ?>
                <li>
                  <span class="tmf-slowest-main">
                    <span class="tmf-slowest-title"><?php echo html_escape($slowestRow['job_name']); ?></span>
                    <span class="tmf-slowest-meta"><?php echo html_escape($slowestRow['environment']); ?></span>
                  </span>
                  <span class="label label-primary"><?php echo html_escape(formatTmfDuration($slowestRow['duration'])); ?></span>
                </li>
                <?php } ?>
              </ul>
              <?php } else { ?>
              <div class="tmf-empty-note">No completed duration data in this result set.</div>
              <?php } ?>
            </div>
          </div>
        </div>
        <div class="tmf-status-strip">
          <span class="tmf-status-pill tmf-status-ready"><i class="fa fa-check"></i><?php echo number_format($ready); ?> ready</span>
          <span class="tmf-status-pill tmf-status-running"><i class="fa fa-refresh"></i><?php echo number_format($running); ?> running</span>
          <span class="tmf-status-pill tmf-status-error"><i class="fa fa-times"></i><?php echo number_format($error); ?> error</span>
          <span class="tmf-status-pill tmf-status-warning"><i class="fa fa-warning"></i><?php echo number_format($warning); ?> warning</span>
          <span class="tmf-status-pill tmf-status-cancelled"><i class="fa fa-ban"></i><?php echo number_format($cancelled); ?> cancelled</span>
        </div>
      <div class="row" style="margin-top: 12px;">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="overlay" style="display:none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
            <div class="box-header">
              <h3 class="box-title"><b>Available Jobs</b></h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body tmf-table-wrap">
              <div class="table-responsive">
              <table id="table6" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th>Id</th>
                  <th>Status</th>
                  <th>Job Name</th>
                  <th>Dimension</th>
                  <th>Reprocess</th>
                  <th>Event Text</th>
                  <th>Environment</th>
                  <th>Message</th>
                  <th>Records Total</th>
                  <th>Records Processed</th>
                  <th>Start Time</th>
                  <th>Last Activity</th>
                  <th>Running Time</th>
                  <th>Errors</th>
                  <th>Warnings</th>
                  <th>Hostname</th>
                  <th>Username</th>
                  <th>instance_id</th>
                  <?php if($role == 1 || $role == 2) {  ?><th>Action</th><?php } ?>
                </tr>
                </thead>
                <tbody>
                  <?php
                    if(!empty($jobs))
                    {
                        foreach($jobs as $record)
                        {
                    ?>
                    <tr class="tmf-row-<?php echo html_escape(strtolower((string) $record->status)); ?>">
                      <td><?php echo '<span style="color:#3c8dbc;">'.$record->id.'</span>' ?></td>
                      <td id="status"><?php 
                      switch ($record->status) {
                          case 'ready':
                             echo '<span class="label label-success">Ready</span>';
                              break;
                          case 'running':
                             echo '<a class="btn btn-sm btn-info cancel" title="Click to Cancel this job">Running</a>';
                              break;
                          case 'error':
                             echo '<span class="label label-danger">Error</span>';
                              break;
                          case 'warning':
                             echo '<span class="label label-warning">Warning</span>';
                              break;
                            case 'cancelled':
                            case 'Cancelled':
                             echo '<span class="label label-default">Cancelled</span>';
                              break;
                          default:
                              echo html_escape($record->status);
                              break;
                        }
                      ?></td>
                          <td><?php echo html_escape($record->job_name); ?></td>
                          <td><?php echo html_escape($record->dimension); ?></td>
                      <?php  if ($jenkins_enabled == true) { 
                         if($role == 1 || $role == 2) {  ?>
                      <td class="text-center"><?php echo ($record->reprocess == 1) ? '<span class="spin"><h3><i class="fa fa-refresh fa-spin "></i></h3></span><a href="#" class="btn btn-success reprocess" style="display: none;">Enable</a><span class="label label-danger reprocess-erro" style="display: none;">Error</span>' : '' ?></td><?php } else { echo '<td>Not Allowed</td>'; } } else { echo '<td>Not Available</td>';}?>
                      <td><?php echo html_escape($record->event_text); ?></td>
                      <td><?php echo html_escape($record->environment); ?></td>
                      <td><?php if ($record->msg == null) { echo ''; } else { echo '<a class="btn btn-sm btn-info msgSelect" href="#" title="Check Message">Check Message</a>'; } ?></td>
                      <td><?php echo (int) $record->records_total; ?></td>
                      <td><?php $rowTotal = (int) $record->records_total; $rowProcessed = (int) $record->records_processed; $rowProgress = $rowTotal > 0 ? min(100, round(($rowProcessed / $rowTotal) * 100)) : 0; echo (int) $record->records_processed; ?><?php if($rowTotal > 0) { ?><div class="progress progress-xs" style="margin:4px 0 0;"><div class="progress-bar progress-bar-success" style="width: <?php echo $rowProgress; ?>%;"></div></div><?php } ?></td>
                      <td><?php echo date('m-d-Y H:i:s', strtotime($record->start_time)) ?></td>
                      <td><?php echo date('m-d-Y H:i:s', strtotime($record->last_activity)) ?></td>
                       <td><?php
                        $d1 = new DateTime($record->start_time);
                        $d2 = new DateTime($record->last_activity);
                        $interval = $d2->diff($d1);
                        echo $interval->format('%d days, %H hours, %I minutes, %S seconds');
                        ?></td>
                       <td ><?php echo ($record->distict_errors == 1) ? '<a type="button" id="showError" class="btn btn-danger btnSelect"> Show Error </a>' : '' ?></td>
                       <td ><?php echo ($record->warnings == 1) ? '<a href="#" class="btn btn-warning">Warning</a>' : '' ?></td>
                         <td><?php echo html_escape($record->hostname); ?></td>
                         <td><?php echo html_escape($record->username); ?></td>
                         <td><?php echo html_escape($record->instance_id); ?></td>
                      <?php if($role == 1 || $role == 2) {  ?> <td class="text-center">
                          <a class="btn btn-sm btn-danger deleteUser" href="#" data-userid="<?php echo (int) $record->id; ?>" title="Delete"><i class="fa fa-trash"></i></a>
                        </td><?php } ?>
                    </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                 <tr>
                  <th>Id</th>
                  <th>Status</th>
                  <th>Job Name</th>
                  <th>Dimension</th>
                  <th>Reprocess</th>
                  <th>Event Text</th>
                  <th>Environment</th>
                  <th>Message</th>
                  <th>Records Total</th>
                  <th>Records Processed</th>
                  <th>Start Time</th>
                  <th>Last Activity</th>
                  <th>Running Time</th>
                  <th>Errors</th>
                  <th>Warnings</th>
                  <th>Hostname</th>
                  <th>Username</th>
                  <th>instance_id</th>
                  <?php if($role == 1 || $role == 2) {  ?><th>Action</th><?php } ?>
                </tr>
                </tfoot>
              </table>
              </div>
            </div>
            <!-- /.box-body -->
          </div>
          <!-- /.box -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
      </div>
    </section>
    <!-- /.content -->
</div> 

<div class="modal modal-danger fade" id="modal-danger" style="display: none;">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">×</span></button>
                <h4 class="modal-title">Error Description</h4>
              </div>
              <div class="modal-body">

              <div id="modal-main">
                
              </div>

              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline pull-left" data-dismiss="modal">Close</button>
              </div>
            </div>
            <!-- /.modal-content -->
          </div>
      <!-- /.modal-dialog -->
</div>

<div class="modal modal-primary fade" id="modal-msg" style="display: none;">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">×</span></button>
                <h4 class="modal-title">Message Description</h4>
              </div>
              <div class="modal-body">

              <div id="modal-main-msg">
                
              </div>

              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline pull-left" data-dismiss="modal">Close</button>
              </div>
            </div>
            <!-- /.modal-content -->
          </div>
      <!-- /.modal-dialog -->
</div>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/moment/moment.min.js"></script>
<script type="text/javascript">
    $(document).ready(function() {

  clockUpdate();
  setInterval(clockUpdate, 1000);

  var amount = "<?php echo count($jobs); ?>";
  var ready = "<?php echo $ready; ?>";
  var error = "<?php echo $error; ?>";
  var warning = "<?php echo $warning; ?>";
  var running = "<?php echo $running; ?>";

  toastr.options = {
        "closeButton": true,
        "debug": false,
        "positionClass": "toast-top-right",
        "newestOnTop": false,
        "timeOut": "10000",
        "progressBar": true}
        
    //  toastr.info("The total of " + amount + " Rows were fetch from database.", "Data Fetch with success");

      if (ready != 0 ) {
        toastr.success("The total of " + ready + " jobs were executed successfully", "Success");
      }

      if (error != 0 ) {
        toastr.error("The total of " + error + " jobs were failed", "Error");
      }

      if (warning != 0 ) {
        toastr.warning("The total of " + warning + " jobs has warnings", "Warning");
      }

      if (running != 0 ) {
        toastr.info("The total of " + running + " jobs are still running", "Running");
      }

      if (ready == 0 && error == 0 && warning == 0 && running == 0){
        toastr.info("No data has been found on database.", "No Data Available");
      }

      $('.tmf-table-filter').on('click', function() {
        var filter = $(this).data('filter');
        $('.tmf-table-filter').removeClass('active');
        $(this).addClass('active');

        if (! $.fn.DataTable || ! $.fn.DataTable.isDataTable('#table6')) {
          return;
        }

        var table = $('#table6').DataTable();
        if (filter == 'all') {
          table.column(1).search('').draw();
        } else if (filter == 'attention') {
          table.column(1).search('Error|Warning|Running', true, false, true).draw();
        } else {
          table.column(1).search(String(filter), false, false, true).draw();
        }
      });

    //load 
 // $('#loading').fadeOut();
//  $('#main').delay(500).fadeIn();

});

function escapeHtml(value) {
  return $('<div>').text(value == null ? '' : value).html();
}

function renderJobMessage(value) {
  var raw = value == null ? '' : String(value);
  if (raw.indexOf('<') === -1) {
    return '<pre>' + escapeHtml(raw) + '</pre>';
  }

  var allowedTags = {
    TABLE: true, THEAD: true, TBODY: true, TFOOT: true, TR: true, TH: true, TD: true,
    BR: true, P: true, DIV: true, SPAN: true, B: true, STRONG: true, I: true, EM: true,
    U: true, UL: true, OL: true, LI: true, PRE: true, CODE: true
  };
  var removedTags = { SCRIPT: true, STYLE: true, IFRAME: true, OBJECT: true, EMBED: true };
  var allowedAttrs = { class: true, colspan: true, rowspan: true };
  var $container = $('<div>').html(raw);

  $container.find('*').each(function() {
    if (removedTags[this.tagName]) {
      $(this).remove();
      return;
    }

    if (! allowedTags[this.tagName]) {
      $(this).replaceWith(escapeHtml($(this).text()));
      return;
    }

    var node = this;
    $.each($.makeArray(node.attributes), function(index, attr) {
      var attrName = attr.name.toLowerCase();
      if (! allowedAttrs[attrName]) {
        node.removeAttribute(attr.name);
      }
    });
  });

  return $container.html();
}

  jQuery(document).on("click", ".deleteUser", function(){
    
    var userId = $(this).data("userid"),
      hitURL = baseURL + "tmf/delete" ,
      currentRow = $(this);
   
    alertify.confirm('Record Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this record permanently ?</b></p></div></div></div>', 
      function(){ 
        jQuery.ajax({
      type : "POST",
      dataType : "json",
      url : hitURL,
      data : { userId : userId } 
      }).done(function(data){
       // console.log(data);
        if(data.status === true) {
          currentRow.parents('tr').remove();
          alertify.success('Your record has been successfully deleted !');
        }
        else if(data.status === false) { alertify.error("data deletion failed"); }
        else { alert("Access denied..!"); }
      });

    }, 
      function(){ 
        alertify.error('Operation Aborted')
    }
  );
    
  });

// Job Cancel request function
$("#table6").on('click','.cancel',function(){  
  var currentRow=$(this).closest("tr"); 
  var id=currentRow.find("td:eq(0)").text();
  var job_name=currentRow.find("td:eq(2)").text();
  var currentRow = $(this);

  alertify.confirm('Job cancelation request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure you want to send a cancelation request to the running job ' + escapeHtml(job_name) + ' ?</b></p><br></div></div></div>',

              function(){ 
                 $.ajax({
              url: jenkins_url + 'job/'+ encodeURIComponent(job_name) + '/lastBuild/stop',
                   method: 'POST',
                   async: false,
                   headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
                   beforeSend: function() {
                    
                  },
                  success: function(){
                   toastr.success("Your Stop Request has been sent to server.", "Request Sent")
                          $.ajax({
                           url: '<?php echo base_url(); ?>' + 'Tmf/updateStatus/' + id + '/Cancelled',
                           method: 'POST',
                           async: false,
                           beforeSend: function() {
                          },
                          success: function(){
                           toastr.success("Your job flag has been successfully updated", "Flag Updated")
                          },
                          error: function(data) {
                            toastr.error("An error has occured during job flag update request. <br><b> " + data.status + " " + data.statusText + "</b>", "Operation Error")
                          }
                        });
                   currentRow.parents('tr').remove();
                  },
                  error: function(data) {
                    toastr.error("An error has occured during job cancelation request. <br><b> " + data.status + " " + data.statusText + "</b>", "Operation Error")
                  }
                });

              }, 
              function(){ 
                alertify.error('Operation Aborted')
              },
              );

    }); 

$("#table6").on('click','.msgSelect',function(){

   var currentRow=$(this).closest("tr"); 
   var id=currentRow.find("td:eq(0)").text();

   var listId = $.parseJSON($.ajax({
            contentType: "application/json",
            url:  '<?php echo base_url(); ?>Tmf/listId/' + id,
            dataType: "json", 
            async: false,
            beforeSend: function() {
             //  toastr.info("Loading Error List For " + jobName + " \n Id: " + id, "Query Data");
             $(".destroy-msg").remove();
            },
            error: function() {
               toastr.error("Error During query error list data \n Id: " + id, "Query Data Error");
            },

            success: function() {
            },
            complete: function(data) {
                dateRequest = data;
            }

         }).responseText);

    $.each(listId["data"], function(index, value){
                // $("#result").append(index + ": " + value.id + '<br>');
                  $("#modal-main-msg").append('<div class="destroy-msg"><h4>Job Name: <b>' + escapeHtml(value.job_name) + '</b></h4><br><table class="table table-bordered"><tbody><tr><th>Header</th><th>Job Message</th></tr><tr><td>Instance ID</td><td>'+ escapeHtml(value.instance_id) +'</td></tr><tr><td>Job Name</td><td>'+ escapeHtml(value.job_name) +'</td></tr><tr><td>Message</td><td><div class="job-message-content">'+ renderJobMessage(value.msg) +'</div></td></tr></tbody></table><br></div>');
                });

  $('#modal-msg').modal('show');

});  

$("#table6").on('click','.btnSelect',function(){


         // get the current row Id, job name and instance id
         var currentRow=$(this).closest("tr"); 
         var instanceId=currentRow.find("td:eq(17)").text(); 
         var jobName=currentRow.find("td:eq(2)").text();
         var id=currentRow.find("td:eq(0)").text();


         var ErrorList = $.parseJSON($.ajax({
            contentType: "application/json",
            url:  '<?php echo base_url(); ?>Tmf/getError/' + instanceId,
            dataType: "json", 
            async: false,
            beforeSend: function() {
             //  toastr.info("Loading Error List For " + jobName + " \n Id: " + id, "Query Data");
             $(".destroy").remove();
            },
            error: function() {
               toastr.error("Error During query error list data \n Id: " + id, "Query Data Error");
            },

            success: function() {
            },
            complete: function(data) {
                dateRequest = data;
            }

         }).responseText);

         $.each(ErrorList["data"], function(index, value){
                // $("#result").append(index + ": " + value.id + '<br>');
                  $("#modal-main").append('<div class="destroy"><h4>Error Id: <b>' + escapeHtml(value.id) + '</b></h4><br><table class="table table-bordered"><tbody><tr><th>Header</th><th>Job Message</th></tr><tr><td>Instance ID</td><td>'+ escapeHtml(value.tmf_id) +'</td></tr><tr><td>Job Name</td><td>'+ escapeHtml(value.job_name) +'</td></tr><tr><td>Moment</td><td>'+ escapeHtml(moment(value.moment).format('dddd, MMMM Do YYYY, h:mm:ss')) +'</td></tr><tr><td>Type</td><td>'+ escapeHtml(value.type) +'</td></tr><tr><td>Origin</td><td>'+ escapeHtml(value.origin) +'</td></tr><tr><td>Message</td><td>'+ escapeHtml(value.message) +'</td></tr></tbody></table><br></div>');
                });

         $('#modal-danger').modal('show');

    });


        // get Jenkins credentials
    var name = <?php echo json_encode($name); ?>;
    var jenkins_url = '<?php echo $jenkins_url; ?>';
    var jenkins_username = '';
    var jenkins_token = '';
    var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

      $.ajax({
                url: jenkins_url + 'api/json?tree=jobs[name,builds[number,actions[parameters[name,value]]]]&pretty=true',
                method: 'GET',
                headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
                beforeSend: function() {
                  console.log("Loading Jenkins Jobs...")
              }
              }).done(function(data) {
                console.log("Success to fetch Jenkins Jobs...")
                 $.each(data["jobs"], function (key, item) {
                      newJson = item.name;
                      //Sucess Case to fech data from jenkins

                      $("#table6").on('click','.reprocess',function(){

         // get the current row Id, job name and instance id
         var currentRow=$(this).closest("tr"); 
         var instanceId=currentRow.find("td:last-child").text(); 
         var jobName=currentRow.find("td:eq(2)").text();
         var id=currentRow.find("td:eq(0)").text();


         alertify.confirm('Job Reprocess Confirmation', 'Are you sure you want to reprocess the job <b>' + escapeHtml(jobName) + '</b> ID (' + escapeHtml(id) +') ? \n \n *Please choose your option with caution.',
          function(){ 

             $.ajax({
         url: jenkins_url + '/job/'+ encodeURIComponent(jobName) +'/build',
          method: 'POST',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {

           $('.overlay').show();
          //  toastr.info("Your reprocess request has been sent to server for job: " + jobName, "Reprocess Data");
        }
        }).done(function(data) {
            toastr.success("Your Execution Request has been sent to server, Please wait some minutes and reload the page.", "Request Sent")
            $('.overlay').hide();

        }).fail(function() {
          toastr.error("Erro during reprocessing: <b>" + jobName + "</b> <br><br> Result: " + arguments[0].status + "\n"+ arguments[0].statusText, "Erro During Reprocessing");
          $('.overlay').hide();
        });

          }, 

          function(){

           alertify.error('Operation Aborted')

         });

    });
});

  $('.spin').hide();
  $('.reprocess').fadeIn();
   }).fail(function() {
      //console.error(arguments);
      console.log("Erro to fetch Jenkins Jobs...")
     $('.spin').hide();
     $('.reprocess-erro').fadeIn();
     });


function clockUpdate() {
  var date = new Date();
  function addZero(x) {
    if (x < 10) {
      return x = '0' + x;
    } else {
      return x;
    }
  }

  function twelveHour(x) {
    if (x > 12) {
      return x = x - 12;
    } else if (x == 0) {
      return x = 12;
    } else {
      return x;
    }
  }

  var h = addZero(twelveHour(date.getHours()));
  var m = addZero(date.getMinutes());
  var s = addZero(date.getSeconds());

  $('.digital-clock').text(h + ':' + m + ':' + s)

}
</script>