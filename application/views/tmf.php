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
$attentionCount = 0;
$reprocessEligible = 0;
$incompleteRuns = 0;
$staleRunning = 0;
$devRows = 0;
$latestActivityTimestamp = null;
$currentTimestamp = time();
$staleThresholdSeconds = 900;

if (!function_exists('tmfTimestamp')) {
  function tmfTimestamp($value) {
    if (empty($value)) {
      return false;
    }

    $timestamp = strtotime((string) $value);
    return $timestamp === false ? false : $timestamp;
  }
}

if (!function_exists('formatTmfDateValue')) {
  function formatTmfDateValue($value) {
    // Returns a <time> element localized in the browser by jobseeker-time.js.
    return js_time($value, array('format' => 'm-d-Y H:i:s', 'empty' => '-'));
  }
}

if (!function_exists('formatTmfAge')) {
  function formatTmfAge($timestamp) {
    if ($timestamp === false) {
      return 'no activity';
    }

    $seconds = max(0, time() - (int) $timestamp);
    if ($seconds < 60) {
      return 'just now';
    }

    if ($seconds < 3600) {
      return floor($seconds / 60).'m ago';
    }

    if ($seconds < 86400) {
      return floor($seconds / 3600).'h ago';
    }

    return floor($seconds / 86400).'d ago';
  }
}

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
  $lastTimestamp = tmfTimestamp($tmfJob->last_activity);
  if ($lastTimestamp !== false && ($latestActivityTimestamp === null || $lastTimestamp > $latestActivityTimestamp)) {
    $latestActivityTimestamp = $lastTimestamp;
  }
}

$activityReferenceTimestamp = $latestActivityTimestamp !== null ? max($currentTimestamp, $latestActivityTimestamp) : $currentTimestamp;

foreach($jobs as $tmfJob) {
  $status = strtolower((string) $tmfJob->status);
  $recordsTotal = isset($tmfJob->records_total) ? (int) $tmfJob->records_total : 0;
  $recordsProcessed = isset($tmfJob->records_processed) ? (int) $tmfJob->records_processed : 0;
  $startTimestamp = tmfTimestamp($tmfJob->start_time);
  $lastTimestamp = tmfTimestamp($tmfJob->last_activity);
  $durationSeconds = ($startTimestamp !== false && $lastTimestamp !== false) ? max(0, $lastTimestamp - $startTimestamp) : 0;
  $isStaleRunning = ($status == 'running' && $lastTimestamp !== false && ($activityReferenceTimestamp - $lastTimestamp) > $staleThresholdSeconds);
  $hasAttention = in_array($status, array('error', 'warning'), TRUE) || $isStaleRunning || (isset($tmfJob->distict_errors) && (int) $tmfJob->distict_errors == 1) || (isset($tmfJob->warnings) && (int) $tmfJob->warnings == 1);

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

  $environmentLabel = trim((string) $tmfJob->environment) !== '' ? trim((string) $tmfJob->environment) : 'Unknown';
  $environmentIsDev = strtoupper($environmentLabel) === 'DEV';
  $environments[$environmentLabel] = true;

  if ($environmentIsDev) {
    $devRows++;
  }


  if ($hasAttention) {
    $attentionCount++;
  }

  if ($isStaleRunning) {
    $staleRunning++;
  }

  if (isset($tmfJob->reprocess) && (int) $tmfJob->reprocess == 1) {
    $reprocessEligible++;
  }

  if ($recordsTotal > 0 && $recordsProcessed < $recordsTotal) {
    $incompleteRuns++;
  }
}

$totalJobs = count($jobs);
$throughputRate = $totalRecords > 0 ? min(100, round(($totalProcessed / $totalRecords) * 100)) : 0;
$latestActivityLabel = $latestActivityTimestamp !== null ? js_time($latestActivityTimestamp, array('format' => 'm-d-Y H:i:s')) : 'No activity';
$canManageTmf = isset($role) && ((string) $role === (string) ROLE_ADMIN || (string) $role === (string) ROLE_MANAGER);
$tmfSelectedEnvironment = isset($selectedEnvironment) ? strtoupper(trim((string) $selectedEnvironment)) : 'all';
if ($tmfSelectedEnvironment === '' || $tmfSelectedEnvironment === '*' || strtolower($tmfSelectedEnvironment) === 'all') {
  $tmfSelectedEnvironment = 'all';
} elseif ($tmfSelectedEnvironment === '__UNKNOWN__' || strtolower($tmfSelectedEnvironment) === 'unknown') {
  $tmfSelectedEnvironment = '__UNKNOWN__';
}
$showDeleteDevBulkAction = $canManageTmf && $tmfSelectedEnvironment === 'DEV';
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
pre { 
    white-space: pre-wrap; 
    word-break: break-word;
  max-width: none;
}

.tmf-results-page .content {
  padding: 18px;
}

.tmf-results-shell {
  max-width: none;
  width: 100%;
}

.tmf-results-toolbar {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-top: 15px;
}

.tmf-refresh-note {
  color: #52616f;
  font-weight: 600;
}

.tmf-workbench {
  background: #fff;
  border: 1px solid #d8e0e8;
  border-radius: 6px;
  box-shadow: 0 8px 20px rgba(16, 42, 67, .08);
  margin-top: 14px;
  padding: 12px;
}

.tmf-quick-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 10px 0;
}

.tmf-quick-filters .badge {
  background: rgba(0,0,0,.18);
  margin-left: 4px;
}

.tmf-quick-filters .btn[disabled] {
  opacity: .48;
}

.tmf-workbench-actions {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: space-between;
}

.tmf-result-signals {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tmf-signal {
  background: #f8fafc;
  border: 1px solid #e3e8ef;
  border-radius: 4px;
  color: #6b7c8f;
  display: inline-flex;
  font-size: 12px;
  gap: 6px;
  padding: 6px 9px;
}

.tmf-signal strong {
  color: #102a43;
  font-size: 13px;
}

.tmf-signal-danger strong { color: #c53030; }
.tmf-signal-warning strong { color: #b7791f; }
.tmf-signal-info strong { color: #2b6cb0; }

.tmf-table-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
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

.tmf-results-page #table6 {
  min-width: 1500px;
  width: 100% !important;
}

.tmf-results-page #table6 td:nth-child(6),
.tmf-results-page #table6 td:nth-child(8) {
  max-width: 360px;
  white-space: normal;
  word-break: break-word;
}

.tmf-table-wrap {
  overflow: hidden;
  width: 100%;
}

.tmf-table-wrap .dataTables_wrapper,
.tmf-table-wrap .dataTables_scroll,
.tmf-table-wrap .dataTables_scrollHead,
.tmf-table-wrap .dataTables_scrollBody {
  width: 100%;
}

.tmf-table-wrap .dataTables_scrollHeadInner,
.tmf-table-wrap .dataTables_scrollHeadInner > table {
  min-width: 1500px;
}

.tmf-table-wrap .dataTables_scrollHeadInner > table,
.tmf-table-wrap .dataTables_scrollBody > table {
  margin: 0 !important;
}

.tmf-row-error > td { background: #fff5f5 !important; }
.tmf-row-warning > td { background: #fffaf0 !important; }
.tmf-row-running > td { background: #ebf8ff !important; }
.tmf-row-stale > td { box-shadow: inset 3px 0 0 #c53030; }
.tmf-row-selected > td { background: #e6fffa !important; }

.tmf-progress-label {
  display: block;
  font-weight: 600;
}

.tmf-age {
  color: #829ab1;
  display: block;
  font-size: 12px;
}

.tmf-instance-id {
  font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
  font-size: 12px;
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tmf-results-page .modal-dialog {
  max-width: calc(100vw - 30px);
  width: 760px;
}

.tmf-results-page .modal-body {
  max-height: calc(100vh - 190px);
  overflow: auto;
}

.tmf-results-page .modal-body table {
  table-layout: fixed;
  width: 100%;
}

.tmf-results-page .modal-body table th:first-child,
.tmf-results-page .modal-body table td:first-child {
  width: 125px;
}

.tmf-results-page .modal-body td,
.tmf-results-page .job-message-content {
  overflow-wrap: anywhere;
  white-space: pre-wrap;
  word-break: break-word;
}

.tmf-results-page .job-message-content {
  font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
  font-size: 12px;
  line-height: 1.45;
}

@media (max-width: 767px) {
  .tmf-results-toolbar {
    align-items: flex-start;
    flex-direction: column;
    gap: 12px;
  }

  .tmf-workbench-actions {
    align-items: flex-start;
    flex-direction: column;
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
          <span class="tmf-refresh-note"><i class="fa fa-database"></i> <?php echo number_format($totalJobs); ?> rows returned &middot; latest <?php echo $latestActivityLabel; /* js_time() output or the static 'No activity' string, both HTML-safe */ ?></span>
        </div>
        <div class="tmf-workbench animated fadeIn">
          <div class="tmf-result-signals">
            <span class="tmf-signal"><strong><?php echo number_format($totalJobs); ?></strong> rows</span>
            <?php if($attentionCount > 0) { ?><span class="tmf-signal tmf-signal-danger"><strong><?php echo number_format($attentionCount); ?></strong> needs review</span><?php } ?>
            <?php if($running > 0) { ?><span class="tmf-signal tmf-signal-info"><strong><?php echo number_format($running); ?></strong> running</span><?php } ?>
            <?php if($staleRunning > 0) { ?><span class="tmf-signal tmf-signal-danger"><strong><?php echo number_format($staleRunning); ?></strong> stale running</span><?php } ?>
            <?php if($incompleteRuns > 0) { ?><span class="tmf-signal tmf-signal-warning"><strong><?php echo number_format($incompleteRuns); ?></strong> incomplete</span><?php } ?>
            <?php if($reprocessEligible > 0) { ?><span class="tmf-signal"><strong><?php echo number_format($reprocessEligible); ?></strong> reprocess eligible</span><?php } ?>
            <span class="tmf-signal"><strong><?php echo $throughputRate; ?>%</strong> processed</span>
          </div>
          <div class="tmf-workbench-actions">
            <div class="tmf-quick-filters btn-group" role="group" aria-label="TMF row filters">
              <button type="button" class="btn btn-default btn-sm tmf-table-filter active" data-filter="all"><i class="fa fa-list"></i> All <span class="badge"><?php echo number_format($totalJobs); ?></span></button>
              <button type="button" class="btn btn-danger btn-sm tmf-table-filter" data-filter="attention"<?php echo $attentionCount == 0 ? ' disabled' : ''; ?>><i class="fa fa-bell"></i> Needs Review <span class="badge"><?php echo number_format($attentionCount); ?></span></button>
              <button type="button" class="btn btn-danger btn-sm tmf-table-filter" data-filter="error"<?php echo $error == 0 ? ' disabled' : ''; ?>><i class="fa fa-times"></i> Errors <span class="badge"><?php echo number_format($error); ?></span></button>
              <button type="button" class="btn btn-warning btn-sm tmf-table-filter" data-filter="warning"<?php echo $warning == 0 ? ' disabled' : ''; ?>><i class="fa fa-warning"></i> Warnings <span class="badge"><?php echo number_format($warning); ?></span></button>
              <button type="button" class="btn btn-info btn-sm tmf-table-filter" data-filter="running"<?php echo $running == 0 ? ' disabled' : ''; ?>><i class="fa fa-refresh"></i> Running <span class="badge"><?php echo number_format($running); ?></span></button>
              <button type="button" class="btn btn-default btn-sm tmf-table-filter" data-filter="stale"<?php echo $staleRunning == 0 ? ' disabled' : ''; ?>><i class="fa fa-clock-o"></i> Stale <span class="badge"><?php echo number_format($staleRunning); ?></span></button>
              <button type="button" class="btn btn-default btn-sm tmf-table-filter" data-filter="incomplete"<?php echo $incompleteRuns == 0 ? ' disabled' : ''; ?>><i class="fa fa-tasks"></i> Incomplete <span class="badge"><?php echo number_format($incompleteRuns); ?></span></button>
              <button type="button" class="btn btn-default btn-sm tmf-table-filter" data-filter="reprocess"<?php echo $reprocessEligible == 0 ? ' disabled' : ''; ?>><i class="fa fa-repeat"></i> Reprocess <span class="badge"><?php echo number_format($reprocessEligible); ?></span></button>
            </div>
            <div class="tmf-table-actions">
              <?php if($canManageTmf) { ?><button type="button" class="btn btn-danger btn-sm tmf-delete-dev" title="Delete all DEV rows in this result set" style="<?php echo $showDeleteDevBulkAction ? '' : 'display: none;'; ?>"<?php echo (! $showDeleteDevBulkAction || $devRows == 0) ? ' disabled' : ''; ?>><i class="fa fa-trash"></i> Delete DEV <span class="badge"><?php echo number_format($devRows); ?></span></button><?php } ?>
              <?php if($canManageTmf) { ?><button type="button" class="btn btn-danger btn-sm tmf-delete-stale" title="Delete all stale running rows in this result set"<?php echo $staleRunning == 0 ? ' disabled' : ''; ?>><i class="fa fa-clock-o"></i> Delete Stale <span class="badge"><?php echo number_format($staleRunning); ?></span></button><?php } ?>
              <button type="button" class="btn btn-default btn-sm tmf-focus-selected" title="Show only the selected rows in the current result" disabled><i class="fa fa-eye"></i> Focus Selected</button>
              <button type="button" class="btn btn-default btn-sm tmf-export-csv" title="Export selected rows when any are selected; otherwise export the current filtered table"><i class="fa fa-download"></i> Export CSV</button>
              <button type="button" class="btn btn-default btn-sm tmf-clear-selection" title="Clear selected rows" disabled><i class="fa fa-eraser"></i> Clear</button>
              <span class="label label-primary"><span id="tmfVisibleRows"><?php echo number_format($totalJobs); ?></span> visible</span>
              <span class="label label-default"><span id="tmfSelectionCount">0</span> selected</span>
            </div>
          </div>
        </div>
      <div class="row" style="margin-top: 12px;">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="overlay" style="display:none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
            <div class="box-header">
              <h3 class="box-title"><b>Transaction Runs</b></h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body tmf-table-wrap">
              <div class="tmf-datatable-container">
              <table id="table6" class="table table-bordered table-striped" style="width: 100%;">
                <thead>
                <tr>
                  <th>Id</th>
                  <th>Status</th>
                  <th>Jenkins Job</th>
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
                  <?php if($canManageTmf) {  ?><th>Action</th><?php } ?>
                </tr>
                </thead>
                <tbody>
                  <?php
                    if(!empty($jobs))
                    {
                        foreach($jobs as $record)
                        {
                          $recordStatus = strtolower((string) $record->status);
                          $rowTotal = isset($record->records_total) ? (int) $record->records_total : 0;
                          $rowProcessed = isset($record->records_processed) ? (int) $record->records_processed : 0;
                          $rowProgress = $rowTotal > 0 ? min(100, round(($rowProcessed / $rowTotal) * 100)) : 0;
                          $rowStartTimestamp = tmfTimestamp($record->start_time);
                          $rowLastTimestamp = tmfTimestamp($record->last_activity);
                          $rowDurationSeconds = ($rowStartTimestamp !== false && $rowLastTimestamp !== false) ? max(0, $rowLastTimestamp - $rowStartTimestamp) : 0;
                          $rowStale = ($recordStatus == 'running' && $rowLastTimestamp !== false && ($activityReferenceTimestamp - $rowLastTimestamp) > $staleThresholdSeconds);
                          $rowHasErrors = isset($record->distict_errors) && (int) $record->distict_errors == 1;
                          $rowHasWarnings = isset($record->warnings) && (int) $record->warnings == 1;
                          $rowAttention = in_array($recordStatus, array('error', 'warning'), TRUE) || $rowStale || $rowHasErrors || $rowHasWarnings;
                          $rowReprocess = isset($record->reprocess) && (int) $record->reprocess == 1;
                            $rowIncomplete = $rowTotal > 0 && $rowProcessed < $rowTotal;
                            $rowJenkinsJobName = isset($record->jenkins_job_name) && trim((string) $record->jenkins_job_name) !== '' ? trim((string) $record->jenkins_job_name) : trim((string) $record->job_name);
                            $rowEnvironment = trim((string) $record->environment) !== '' ? trim((string) $record->environment) : 'Unknown';
                            $rowEnvironmentIsDev = strtoupper($rowEnvironment) === 'DEV';
                            $rowCanDelete = $rowEnvironmentIsDev || $rowStale;
                            $rowDeleteScope = $rowEnvironmentIsDev ? 'dev' : ($rowStale ? 'stale' : 'none');
                    ?>
                          <tr class="tmf-row-<?php echo html_escape($recordStatus); ?><?php echo $rowStale ? ' tmf-row-stale' : ''; ?><?php echo $rowAttention ? ' tmf-row-attention' : ''; ?>" data-tmf-id="<?php echo (int) $record->id; ?>" data-instance-id="<?php echo html_escape($record->instance_id); ?>" data-job-name="<?php echo html_escape($rowJenkinsJobName); ?>" data-jenkins-job-name="<?php echo html_escape($rowJenkinsJobName); ?>" data-status="<?php echo html_escape($recordStatus); ?>" data-environment="<?php echo html_escape($rowEnvironment); ?>" data-stale="<?php echo $rowStale ? 1 : 0; ?>" data-can-delete="<?php echo $rowCanDelete ? 1 : 0; ?>" data-delete-scope="<?php echo html_escape($rowDeleteScope); ?>" data-attention="<?php echo $rowAttention ? 1 : 0; ?>" data-reprocess="<?php echo $rowReprocess ? 1 : 0; ?>" data-incomplete="<?php echo $rowIncomplete ? 1 : 0; ?>">
                      <td data-order="<?php echo (int) $record->id; ?>"><?php echo '<span style="color:#3c8dbc;">'.(int) $record->id.'</span>' ?></td>
                      <td data-order="<?php echo html_escape($recordStatus); ?>"><?php
                      switch ($recordStatus) {
                          case 'ready':
                             echo '<span class="label label-success">Ready</span>';
                              break;
                          case 'running':
                             echo '<a class="btn btn-sm btn-info cancel" title="Click to cancel this job">Running</a>';
                             if ($rowStale) {
                               echo ' <span class="label label-danger">Stale</span>';
                             }
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
                          <td data-order="<?php echo html_escape($rowJenkinsJobName); ?>"><?php echo html_escape($rowJenkinsJobName); ?></td>
                          <td><?php echo html_escape($record->dimension); ?></td>
                      <?php  if ($jenkins_enabled == true) { 
                         if($canManageTmf) {  ?>
                      <td class="text-center" data-order="<?php echo $rowReprocess ? 1 : 0; ?>"><?php echo ($record->reprocess == 1) ? '<span class="spin"><h3><i class="fa fa-refresh fa-spin "></i></h3></span><a href="#" class="btn btn-success reprocess" style="display: none;">Enable</a><span class="label label-danger reprocess-erro" style="display: none;">Error</span>' : '<span class="text-muted">-</span>' ?></td><?php } else { echo '<td>Not Allowed</td>'; } } else { echo '<td>Not Available</td>';}?>
                      <td><?php echo html_escape($record->event_text); ?></td>
                      <td data-order="<?php echo html_escape($rowEnvironment); ?>"><?php echo $rowEnvironment === 'Unknown' ? '<span class="label label-default">Unknown</span>' : html_escape($rowEnvironment); ?></td>
                      <td><?php if ($record->msg == null) { echo ''; } else { echo '<a class="btn btn-sm btn-info msgSelect" href="#" data-tmf-id="'.(int) $record->id.'" data-job-name="'.html_escape($record->job_name).'" title="Check Message">Check Message</a>'; } ?></td>
                      <td data-order="<?php echo $rowTotal; ?>"><?php echo number_format($rowTotal); ?></td>
                      <td data-order="<?php echo $rowProgress; ?>"><span class="tmf-progress-label"><?php echo number_format($rowProcessed); ?> / <?php echo number_format($rowTotal); ?> <span class="text-muted"><?php echo $rowProgress; ?>%</span></span><?php if($rowTotal > 0) { ?><div class="progress progress-xs" style="margin:4px 0 0;"><div class="progress-bar progress-bar-<?php echo $rowIncomplete ? 'warning' : 'success'; ?>" style="width: <?php echo $rowProgress; ?>%;"></div></div><?php } ?></td>
                      <td data-order="<?php echo $rowStartTimestamp !== false ? (int) $rowStartTimestamp : 0; ?>"><?php echo formatTmfDateValue($record->start_time); ?></td>
                      <td data-order="<?php echo $rowLastTimestamp !== false ? (int) $rowLastTimestamp : 0; ?>"><?php echo formatTmfDateValue($record->last_activity); ?><span class="tmf-age"><?php echo html_escape(formatTmfAge($rowLastTimestamp)); ?></span></td>
                       <td data-order="<?php echo (int) $rowDurationSeconds; ?>"><?php echo html_escape(formatTmfDuration($rowDurationSeconds)); ?></td>
                       <td data-order="<?php echo $rowHasErrors ? 1 : 0; ?>"><?php echo ($record->distict_errors == 1) ? '<a type="button" href="#" class="btn btn-danger btnSelect" data-tmf-id="'.(int) $record->id.'" data-instance-id="'.html_escape($record->instance_id).'" data-job-name="'.html_escape($record->job_name).'">View Errors</a>' : '<span class="text-muted">-</span>' ?></td>
                       <td data-order="<?php echo $rowHasWarnings ? 1 : 0; ?>"><?php echo ($record->warnings == 1) ? '<span class="label label-warning">Warning</span>' : '<span class="text-muted">-</span>' ?></td>
                         <td><?php echo html_escape($record->hostname); ?></td>
                         <td><?php echo html_escape($record->username); ?></td>
                         <td class="tmf-instance-id" title="<?php echo html_escape($record->instance_id); ?>"><?php echo html_escape($record->instance_id); ?></td>
                      <?php if($canManageTmf) {  ?> <td class="text-center">
                          <?php if($rowCanDelete) { ?><a class="btn btn-sm btn-danger deleteUser" href="#" data-userid="<?php echo (int) $record->id; ?>" title="Delete <?php echo $rowEnvironmentIsDev ? 'DEV' : 'stale'; ?> TMF row"><i class="fa fa-trash"></i></a><?php } else { ?><span class="text-muted" title="Only DEV rows or stale running rows can be deleted">-</span><?php } ?>
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
                  <th>Jenkins Job</th>
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
                  <?php if($canManageTmf) {  ?><th>Action</th><?php } ?>
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
          <div class="modal-dialog modal-lg">
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
          <div class="modal-dialog modal-lg">
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
var activeTmfFilter = 'all';
var activeTmfSelectionOnly = false;
var tmfSelectedEnvironment = <?php echo json_encode($tmfSelectedEnvironment); ?>;
var tmfColumnAdjustTimer = null;

function scheduleTmfColumnAlignment(delay) {
  window.clearTimeout(tmfColumnAdjustTimer);
  tmfColumnAdjustTimer = window.setTimeout(function() {
    var table = getTmfTable();
    if (! table) {
      return;
    }

    table.columns.adjust();
  }, typeof delay === 'number' ? delay : 0);
}

    $(document).ready(function() {

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

      if ($.fn.dataTable && $.fn.dataTable.ext) {
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
          if (! settings.nTable || settings.nTable.id !== 'table6') {
            return true;
          }

          var row = settings.aoData[dataIndex].nTr;
          if (! row) {
            return true;
          }

          var $row = $(row);
          var matchesFilter = activeTmfFilter === 'all';
          var rowStatus = String($row.attr('data-status') || '').toLowerCase();
          var rowStale = $row.attr('data-stale') === '1';
          var rowAttention = $row.attr('data-attention') === '1';
          var rowReprocess = $row.attr('data-reprocess') === '1';
          var rowIncomplete = $row.attr('data-incomplete') === '1';

          if (activeTmfFilter === 'attention') {
            matchesFilter = rowAttention;
          } else if (activeTmfFilter === 'stale') {
            matchesFilter = rowStale;
          } else if (activeTmfFilter === 'reprocess') {
            matchesFilter = rowReprocess;
          } else if (activeTmfFilter === 'incomplete') {
            matchesFilter = rowIncomplete;
          } else if (activeTmfFilter !== 'all') {
            matchesFilter = rowStatus === activeTmfFilter;
          }

          return matchesFilter && (! activeTmfSelectionOnly || $row.hasClass('tmf-row-selected'));
        });
      }

      $('.tmf-table-filter').on('click', function() {
        activeTmfFilter = $(this).data('filter');
        activeTmfSelectionOnly = false;
        $('.tmf-table-filter').removeClass('active');
        $(this).addClass('active');
        $('.tmf-focus-selected').removeClass('active');

        var table = getTmfTable();
        if (table) {
          table.draw();
          updateTmfVisibleRows();
        }
      });

      $('#table6').on('init.dt draw.dt search.dt', function() {
        updateTmfVisibleRows();
        scheduleTmfColumnAlignment();
      });

      $(window).on('load resize', function() {
        scheduleTmfColumnAlignment(50);
      });

      $(document).on('expanded.pushMenu collapsed.pushMenu', function() {
        scheduleTmfColumnAlignment(350);
      });

      $('#table6 tbody').on('click', 'tr', function(event) {
        if ($(event.target).closest('a, button, input, label').length) {
          return;
        }

        $(this).toggleClass('tmf-row-selected');
        syncTmfSelectionTools();
        if (activeTmfSelectionOnly) {
          var table = getTmfTable();
          if (table) {
            table.draw();
          }
        }
      });

      $('.tmf-clear-selection').on('click', function() {
        getAllTmfRows().removeClass('tmf-row-selected');
        activeTmfSelectionOnly = false;
        $('.tmf-focus-selected').removeClass('active');
        var table = getTmfTable();
        if (table) {
          table.draw();
        }
        syncTmfSelectionTools();
      });

      $('.tmf-focus-selected').on('click', function() {
        var selectedRows = getTmfVisibleRows().filter('.tmf-row-selected');
        if (! selectedRows.length) {
          toastr.warning('Select one or more rows before focusing the result set.', 'Focus Selected');
          return;
        }

        activeTmfSelectionOnly = ! activeTmfSelectionOnly;
        $(this).toggleClass('active', activeTmfSelectionOnly);

        var table = getTmfTable();
        if (table) {
          table.draw();
        }
        syncTmfSelectionTools();
      });

      $('.tmf-export-csv').on('click', exportTmfCsv);
      $(document).on('jobseeker:environment-change', syncTmfDeleteControls);
      syncTmfDeleteControls();
      setTimeout(updateTmfVisibleRows, 250);
      setTimeout(function() { scheduleTmfColumnAlignment(); }, 400);

    //load 
 // $('#loading').fadeOut();
//  $('#main').delay(500).fadeIn();

});

function normalizeTmfEnvironment(value) {
  if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
    return window.JobSeekerGlobalEnvironment.normalize(value);
  }

  var normalized = $.trim(String(value || '')).toUpperCase();
  if (normalized === '' || normalized === '*' || normalized === 'ALL') {
    return 'all';
  }
  if (normalized === 'UNKNOWN') {
    return '__UNKNOWN__';
  }
  if (normalized === 'QAS') {
    return 'QA';
  }
  if (normalized === 'PRD' || normalized === 'PRODUCTION') {
    return 'PROD';
  }
  if (normalized === 'HOMOLOG' || normalized === 'HOMOLOGATION') {
    return 'HML';
  }

  return normalized;
}

function currentTmfEnvironment() {
  if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.selected) {
    return normalizeTmfEnvironment(window.JobSeekerGlobalEnvironment.selected());
  }

  return normalizeTmfEnvironment($('#globalEnvironmentSelector').val() || tmfSelectedEnvironment || 'all');
}

function isTmfDevEnvironmentSelected() {
  return currentTmfEnvironment() === 'DEV';
}

function getTmfTable() {
  if ($.fn.DataTable && $.fn.DataTable.isDataTable('#table6')) {
    return $('#table6').DataTable();
  }

  return null;
}

function getTmfVisibleRows() {
  var table = getTmfTable();
  if (table) {
    return $(table.rows({ search: 'applied' }).nodes());
  }

  return $('#table6 tbody tr');
}

function getAllTmfRows() {
  var table = getTmfTable();
  if (table) {
    return $(table.rows().nodes());
  }

  return $('#table6 tbody tr');
}

function updateTmfVisibleRows() {
  var visibleRows = getTmfVisibleRows().length;
  $('#tmfVisibleRows').text(visibleRows);
  syncTmfSelectionTools();
}

function getTmfRowsForExport() {
  var visibleRows = getTmfVisibleRows();
  var selectedRows = visibleRows.filter('.tmf-row-selected');
  return {
    rows: selectedRows.length ? selectedRows : visibleRows,
    scope: selectedRows.length ? 'selected' : 'visible'
  };
}

function syncTmfSelectionTools() {
  var selectedCount = getTmfVisibleRows().filter('.tmf-row-selected').length;
  $('#tmfSelectionCount').text(selectedCount);
  $('.tmf-focus-selected').prop('disabled', selectedCount === 0);
  $('.tmf-clear-selection').prop('disabled', selectedCount === 0);

  if (selectedCount === 0 && activeTmfSelectionOnly) {
    activeTmfSelectionOnly = false;
    $('.tmf-focus-selected').removeClass('active');
  }
}

function csvEscape(value) {
  var cleanValue = String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
  return '"' + cleanValue.replace(/"/g, '""') + '"';
}

function getTmfCellText(cell) {
  var clone = $(cell).clone();
  clone.find('.progress, .spin, script').remove();
  return $.trim(clone.text());
}

function exportTmfCsv() {
  var exportScope = getTmfRowsForExport();
  var rows = exportScope.rows;
  if (! rows.length) {
    toastr.warning('No visible TMF rows to export.', 'CSV Export');
    return;
  }

  var columnIndexes = [];
  var headers = [];
  $('#table6 thead th').each(function(index) {
    var headerText = $.trim($(this).text());
    if (headerText.toLowerCase() !== 'action') {
      columnIndexes.push(index);
      headers.push(headerText);
    }
  });

  var csvRows = [headers.map(csvEscape).join(',')];
  rows.each(function() {
    var cells = $(this).children('td');
    var values = $.map(columnIndexes, function(index) {
      return csvEscape(getTmfCellText(cells.eq(index)));
    });
    csvRows.push(values.join(','));
  });

  var blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
  var url = URL.createObjectURL(blob);
  var link = document.createElement('a');
  link.href = url;
  link.download = 'tmf-results-' + moment().format('YYYYMMDD-HHmmss') + '.csv';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
  toastr.success(rows.length + ' ' + exportScope.scope + ' TMF rows exported.', 'CSV Export');
}

function escapeHtml(value) {
  return $('<div>').text(value == null ? '' : value).html();
}

function tmfRowValue(trigger, attrName, cellSelector, fallbackCellIndex) {
  var $trigger = $(trigger);
  var value = $trigger.attr(attrName);
  var $row = $trigger.closest('tr');

  if (value) {
    return $.trim(value);
  }

  value = $row.attr(attrName);
  if (value) {
    return $.trim(value);
  }

  if (cellSelector) {
    value = $row.find(cellSelector).first().text();
    if ($.trim(value) !== '') {
      return $.trim(value);
    }
  }

  if (typeof fallbackCellIndex !== 'undefined') {
    return $.trim($row.children('td').eq(fallbackCellIndex).text());
  }

  return '';
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

function jenkinsJobPath(jobName) {
  return 'job/' + String(jobName || '').split('/').map(function(segment) {
    return encodeURIComponent(segment);
  }).join('/job/');
}

function hasKnownEnvironment(environment) {
  environment = normalizeTmfEnvironment(environment);
  return environment && environment !== 'all' && environment !== '__UNKNOWN__';
}

function tmfReprocessErrorMessage(xhr, jobName) {
  if (xhr && parseInt(xhr.status, 10) === 404) {
    return 'Jenkins job "' + jobName + '" does not exist anymore. It may have been deleted or renamed in Jenkins.';
  }

  if (xhr && xhr.responseText) {
    return xhr.responseText;
  }

  if (xhr && xhr.status) {
    return xhr.status + ' ' + (xhr.statusText || '');
  }

  return 'Unable to trigger this job.';
}

function triggerJenkinsJob(jobName, environment) {
  if (! jenkins_url) {
    return $.Deferred().reject({responseText: 'Jenkins URL is not configured.'}).promise();
  }

  environment = normalizeTmfEnvironment(environment);
  if (hasKnownEnvironment(environment)) {
    return $.ajax({
      url: jenkins_url + jenkinsJobPath(jobName) + '/buildWithParameters',
      method: 'POST',
      headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
      data: { ENVIRONMENT: environment }
    }).then(null, function(xhr) {
      if (xhr && (xhr.status === 400 || xhr.status === 404)) {
        return $.ajax({
          url: jenkins_url + jenkinsJobPath(jobName) + '/build',
          method: 'POST',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
        });
      }

      return $.Deferred().rejectWith(this, arguments).promise();
    });
  }

  return $.ajax({
    url: jenkins_url + jenkinsJobPath(jobName) + '/build',
    method: 'POST',
    headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
  }).then(null, function(xhr) {
    if (xhr && (xhr.status === 400 || xhr.status === 404)) {
      return $.ajax({
        url: jenkins_url + jenkinsJobPath(jobName) + '/buildWithParameters',
        method: 'POST',
        headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
      });
    }

    return $.Deferred().rejectWith(this, arguments).promise();
  });
}

function tmfDeleteRequest(id) {
  return jQuery.ajax({
    type : "POST",
    dataType : "json",
    url : baseURL + "tmf/delete?environment=" + encodeURIComponent(currentTmfEnvironment()),
    data : { userId : id, environment: currentTmfEnvironment() }
  });
}

function tmfDeletePolicyMessage(data) {
  return data && data.message ? data.message : 'Only DEV TMF rows or stale running TMF rows can be deleted.';
}

function removeTmfRowFromTable(row) {
  var $row = $(row);
  var table = getTmfTable();

  if (table) {
    table.row($row).remove().draw(false);
  } else {
    $row.remove();
    updateTmfVisibleRows();
  }

  syncTmfDeleteControls();
}

function formatTmfCount(value) {
  value = parseInt(value, 10) || 0;
  return value.toLocaleString ? value.toLocaleString() : String(value);
}

function syncTmfDeleteControls() {
  var allRows = getAllTmfRows();
  var staleCount = allRows.filter('[data-stale="1"][data-can-delete="1"]').length;
  var devCount = allRows.filter('[data-delete-scope="dev"]').length;
  var staleFilter = $('.tmf-table-filter[data-filter="stale"]');

  $('.tmf-delete-dev')
    .toggle(isTmfDevEnvironmentSelected())
    .prop('disabled', ! isTmfDevEnvironmentSelected() || devCount === 0)
    .find('.badge').text(formatTmfCount(devCount));
  $('.tmf-delete-stale').prop('disabled', staleCount === 0).find('.badge').text(formatTmfCount(staleCount));
  staleFilter.prop('disabled', staleCount === 0);
  staleFilter.find('.badge').text(formatTmfCount(staleCount));

  if (activeTmfFilter === 'stale' && staleCount === 0) {
    activeTmfFilter = 'all';
    $('.tmf-table-filter').removeClass('active');
    $('.tmf-table-filter[data-filter="all"]').addClass('active');
  }

  updateTmfVisibleRows();
}

function deleteTmfRows(rows, title, noun) {
  var totalRows = rows.length;

  if (! totalRows) {
    toastr.info('No ' + noun + ' TMF rows are available in this result set.', title);
    syncTmfDeleteControls();
    return;
  }

  alertify.confirm(title, '<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Delete ' + formatTmfCount(totalRows) + ' ' + noun + ' TMF row(s) from this result set?</b></p></div></div></div>',
    function(){
      var deletedRows = 0;
      var failedRows = 0;
      var deniedRows = 0;
      var index = 0;

      function deleteNextRow() {
        if (index >= totalRows) {
          if (deletedRows > 0) {
            alertify.success(formatTmfCount(deletedRows) + ' TMF row(s) deleted.');
          }
          if (deniedRows > 0) {
            alertify.error(formatTmfCount(deniedRows) + ' TMF row(s) were protected by the DEV/stale delete rule.');
          }
          if (failedRows > 0) {
            alertify.error(formatTmfCount(failedRows) + ' TMF row(s) could not be deleted.');
          }
          syncTmfDeleteControls();
          return;
        }

        var row = rows[index++];
        var $row = $(row);
        var userId = $row.attr('data-tmf-id') || $row.find('.deleteUser').data('userid');

        if (! userId) {
          failedRows++;
          deleteNextRow();
          return;
        }

        tmfDeleteRequest(userId).done(function(data) {
          if (data.status === true) {
            deletedRows++;
            removeTmfRowFromTable($row);
          } else if (data.status === 'restricted') {
            deniedRows++;
          } else {
            failedRows++;
          }
        }).fail(function() {
          failedRows++;
        }).always(deleteNextRow);
      }

      deleteNextRow();
    },
    function(){
      alertify.error('Operation Aborted')
    }
  );
}

jQuery(document).on("click", "#table6 .deleteUser", function(event){
  event.preventDefault();
  event.stopPropagation();

  var userId = $(this).data("userid");
  var currentRow = $(this).closest('tr');

  alertify.confirm('Record Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this TMF row permanently ?</b></p></div></div></div>',
    function(){
      tmfDeleteRequest(userId).done(function(data){
        if(data.status === true) {
          removeTmfRowFromTable(currentRow);
          alertify.success('Your record has been successfully deleted !');
        }
        else if(data.status === 'restricted') { alertify.error(tmfDeletePolicyMessage(data)); }
        else if(data.status === false) { alertify.error("data deletion failed"); }
        else { alert("Access denied..!"); }
      }).fail(function(){
        alertify.error("data deletion request failed");
      });

    },
    function(){
      alertify.error('Operation Aborted')
  }
);

});

$(document).on('click', '.tmf-delete-dev', function(event) {
  event.preventDefault();
  deleteTmfRows(getAllTmfRows().filter('[data-delete-scope="dev"]'), 'DEV TMF Delete Confirmation', 'DEV');
});

$(document).on('click', '.tmf-delete-stale', function(event) {
  event.preventDefault();
  deleteTmfRows(getAllTmfRows().filter('[data-stale="1"][data-can-delete="1"]'), 'Stale TMF Delete Confirmation', 'stale running');
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
                           url: '<?php echo base_url(); ?>' + 'Tmf/updateStatus/' + id + '/Cancelled?environment=' + encodeURIComponent(currentTmfEnvironment()),
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

$(document).on('click', '#table6 .msgSelect', function(event){

   event.preventDefault();
   event.stopPropagation();

   var id = tmfRowValue(this, 'data-tmf-id', null, 0);
   if (! id) {
     toastr.error('This TMF row is missing its message id.', 'Query Data Error');
     return;
   }

   $.ajax({
            contentType: "application/json",
            url:  '<?php echo base_url(); ?>Tmf/listId/' + encodeURIComponent(id) + '?environment=' + encodeURIComponent(currentTmfEnvironment()),
            dataType: "json", 
            beforeSend: function() {
             //  toastr.info("Loading Error List For " + jobName + " \n Id: " + id, "Query Data");
             $(".destroy-msg").remove();
            }
         }).done(function(listId){
            $.each((listId && listId.data) || [], function(index, value){
                // $("#result").append(index + ": " + value.id + '<br>');
                  $("#modal-main-msg").append('<div class="destroy-msg"><h4>Job Name: <b>' + escapeHtml(value.job_name) + '</b></h4><br><table class="table table-bordered"><tbody><tr><th>Header</th><th>Job Message</th></tr><tr><td>Instance ID</td><td>'+ escapeHtml(value.instance_id) +'</td></tr><tr><td>Job Name</td><td>'+ escapeHtml(value.job_name) +'</td></tr><tr><td>Message</td><td><div class="job-message-content">'+ renderJobMessage(value.msg) +'</div></td></tr></tbody></table><br></div>');
                });

            $('#modal-msg').modal('show');
         }).fail(function(){
            toastr.error("Error During query message data <br>Id: " + escapeHtml(id), "Query Data Error");
         });

});  

$(document).on('click', '#table6 .btnSelect', function(event){

         event.preventDefault();
         event.stopPropagation();

         // get the current row Id, job name and instance id
         var instanceId = tmfRowValue(this, 'data-instance-id', '.tmf-instance-id', 17);
         var jobName = tmfRowValue(this, 'data-job-name', null, 2);
         var id = tmfRowValue(this, 'data-tmf-id', null, 0);

         if (! instanceId) {
            toastr.error("This TMF row is missing its instance id. <br>Id: " + escapeHtml(id), "Query Data Error");
            return;
         }

         $.ajax({
            contentType: "application/json",
            url:  '<?php echo base_url(); ?>Tmf/getError/' + encodeURIComponent(instanceId) + '?environment=' + encodeURIComponent(currentTmfEnvironment()),
            dataType: "json", 
            beforeSend: function() {
             //  toastr.info("Loading Error List For " + jobName + " \n Id: " + id, "Query Data");
             $(".destroy").remove();
            }
         }).done(function(ErrorList){
            $.each((ErrorList && ErrorList.data) || [], function(index, value){
                // $("#result").append(index + ": " + value.id + '<br>');
                  $("#modal-main").append('<div class="destroy"><h4>Error Id: <b>' + escapeHtml(value.id) + '</b></h4><br><table class="table table-bordered"><tbody><tr><th>Header</th><th>Job Message</th></tr><tr><td>Instance ID</td><td>'+ escapeHtml(value.tmf_id) +'</td></tr><tr><td>Job Name</td><td>'+ escapeHtml(value.job_name || jobName) +'</td></tr><tr><td>Moment</td><td>'+ escapeHtml(moment(value.moment).format('dddd, MMMM Do YYYY, h:mm:ss')) +'</td></tr><tr><td>Type</td><td>'+ escapeHtml(value.type) +'</td></tr><tr><td>Origin</td><td>'+ escapeHtml(value.origin) +'</td></tr><tr><td>Message</td><td>'+ escapeHtml(value.message) +'</td></tr></tbody></table><br></div>');
                });

            $('#modal-danger').modal('show');
         }).fail(function(){
            toastr.error("Error During query error list data <br>Id: " + escapeHtml(id), "Query Data Error");
         });

    });


        // get Jenkins credentials
    var name = <?php echo json_encode($name); ?>;
    var jenkins_url = <?php echo json_encode($jenkins_url); ?>;
    var jenkins_username = '';
    var jenkins_token = '';
    jenkins_url = String(jenkins_url || '');
    if (jenkins_url && jenkins_url.charAt(jenkins_url.length - 1) !== '/') {
      jenkins_url += '/';
    }

      $(document).on('click', '#table6 .reprocess', function(event){
         event.preventDefault();
         event.stopPropagation();

         var currentRow = $(this).closest('tr');
         var jobName = currentRow.attr('data-job-name') || $.trim(currentRow.find('td:eq(2)').text());
         var environment = currentRow.attr('data-environment') || 'Unknown';
         var id = currentRow.attr('data-tmf-id') || $.trim(currentRow.find('td:eq(0)').text());

         if (! jobName) {
           toastr.error('This TMF row is missing its Jenkins job name.', 'Reprocess Error');
           return;
         }

         alertify.confirm('Job Reprocess Confirmation', 'Are you sure you want to reprocess the job <b>' + escapeHtml(jobName) + '</b> ID (' + escapeHtml(id) + ')?<br><br>Environment: <b>' + escapeHtml(environment) + '</b><br><br>*Please choose your option with caution.',
          function(){ 

            $('.overlay').show();
            triggerJenkinsJob(jobName, environment).done(function() {
              toastr.success("Your Execution Request has been sent to server, Please wait some minutes and reload the page.", "Request Sent");
              $('.overlay').hide();

        }).fail(function(xhr) {
          var message = tmfReprocessErrorMessage(xhr, jobName);
          toastr.error("Error during reprocessing: <b>" + escapeHtml(jobName) + "</b><br><br>" + escapeHtml(message), "Error During Reprocessing");
          $('.overlay').hide();
        });

          }, 

          function(){

           alertify.error('Operation Aborted')

         });

      });

  $('.spin').hide();
  $('.reprocess').fadeIn();
</script>
