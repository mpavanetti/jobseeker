 <?php
  $hasJobsAmount = !empty($jobsAmount);
  $hasJobsStatusAmount = !empty($jobsStatusAmount);
  $hasEnvironmentSummary = !empty($environmentSummary);
  $dashboardEnvironmentLabel = (isset($selectedEnvironment) && $selectedEnvironment !== '' && strtolower((string) $selectedEnvironment) !== 'all') ? strtoupper((string) $selectedEnvironment) : 'All environments';
 ?>
 <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
</script>
<link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.css">
<style type="text/css">
  .dashboard-jenkins-panel {
    border: 1px solid #dbe3eb;
    border-left: 4px solid #587c9f;
    box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
  }

  .dashboard-jenkins-panel .box-body {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
  }

  .dashboard-jenkins-title {
    color: #2f4054;
    font-weight: 600;
    margin: 0 0 3px;
  }

  .dashboard-jenkins-detail {
    color: #66727f;
    margin: 0;
  }

  .dashboard-jenkins-stats {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
  }

  .dashboard-jenkins-stat {
    min-width: 108px;
  }

  .dashboard-jenkins-stat strong {
    display: block;
    color: #2f4054;
    font-size: 24px;
    line-height: 1.1;
  }

  .dashboard-jenkins-stat span {
    color: #66727f;
    font-size: 12px;
    text-transform: uppercase;
  }

  .dashboard-status-row .small-box {
    border: 1px solid #dbe3eb;
    box-shadow: 0 1px 2px rgba(31, 45, 61, 0.06);
    color: #2f4054 !important;
  }

  .dashboard-status-row .small-box .icon {
    color: rgba(47, 64, 84, 0.16);
  }

  .dashboard-status-row .small-box-footer {
    background: rgba(255, 255, 255, 0.55) !important;
    color: #44627d !important;
  }

  .dashboard-status-row .bg-aqua {
    background: #eaf3f8 !important;
  }

  .dashboard-status-row .bg-green {
    background: #eaf4ef !important;
  }

  .dashboard-status-row .bg-yellow {
    background: #f7f0e4 !important;
  }

  .dashboard-status-row .bg-red {
    background: #f8e8e5 !important;
  }

  .dashboard-chart-panel canvas {
    max-width: 100%;
  }

  .dashboard-equal-row {
    align-items: stretch;
    display: flex;
    flex-wrap: wrap;
  }

  .dashboard-equal-row:before,
  .dashboard-equal-row:after {
    display: none;
  }

  .dashboard-equal-row > [class*="col-"] {
    display: flex;
    float: none;
  }

  .dashboard-equal-row .clearfix {
    display: none !important;
  }

  .dashboard-card {
    display: flex;
    flex-direction: column;
    width: 100%;
  }

  .dashboard-environment-scope {
    align-items: center;
    background: #f7fbff;
    border: 1px solid #dbe8f4;
    border-radius: 5px;
    color: #2f4054;
    display: inline-flex;
    gap: 8px;
    margin-top: 8px;
    padding: 7px 10px;
  }

  .dashboard-environment-scope .label {
    font-size: 12px;
    padding: 4px 7px;
  }

  .dashboard-card > .box-body {
    flex: 1 1 auto;
  }

  .dashboard-card > .box-footer {
    margin-top: auto;
  }

  .dashboard-card canvas {
    max-width: 100%;
  }

  .dashboard-status-row .small-box {
    display: flex;
    flex-direction: column;
    min-height: 128px;
    width: 100%;
  }

  .dashboard-status-row .small-box .inner {
    flex: 1 1 auto;
  }

  .dashboard-status-row .small-box-footer {
    margin-top: auto;
  }

  .dashboard-paired-row .dashboard-card > .box-body,
  .dashboard-table-row .dashboard-card > .box-body {
    min-height: 220px;
  }

  .dashboard-chart-card > .box-body {
    min-height: 210px;
  }

  .dashboard-percent-card .chart-responsive {
    margin: 0 auto;
    height: 220px;
    max-width: 320px;
  }

  .dashboard-info-row .info-box {
    min-height: 92px;
    width: 100%;
  }

  .dashboard-empty-panel {
    background: #f7fafc;
    border: 1px dashed #c8d4df;
    border-radius: 4px;
    color: #607080;
    align-items: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
    margin: 0;
    min-height: 210px;
    padding: 34px 20px;
    text-align: center;
  }

  .dashboard-empty-panel-small {
    min-height: 150px;
    padding: 32px 20px;
  }

  .dashboard-paired-row .dashboard-empty-panel-small {
    min-height: 210px;
  }

  .dashboard-table-row .dashboard-empty-panel-small {
    min-height: 180px;
  }

  .dashboard-chart-card .dashboard-empty-panel-small {
    min-height: 180px;
  }

  .dashboard-empty-panel i {
    color: #8aa1b4;
    font-size: 42px;
    margin-bottom: 10px;
  }

  .dashboard-empty-panel h4 {
    color: #2f4054;
    font-weight: 600;
    margin: 0 0 6px;
  }

  .dashboard-empty-panel p {
    margin-bottom: 0;
  }

  .dashboard-table-card .dataTables_wrapper {
    width: 100%;
  }

  .dashboard-table-card table {
    table-layout: fixed;
    width: 100% !important;
  }

  .dashboard-table-card th,
  .dashboard-table-card td {
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle !important;
    white-space: nowrap;
  }

  .dashboard-table-card .dashboard-job-name {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
</style>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-home" aria-hidden="true"></i> Dashboard
        <small>Control panel</small>
      </h1>
    </section>

    <div id="loading">
      <div class="dashboard-environment-scope">
        <i class="fa fa-globe"></i>
        <span>Metrics scoped to</span>
        <span class="label label-primary"><?php echo html_escape($dashboardEnvironmentLabel); ?></span>
      </div>
      <div class="row" style="margin-top: 15px;">
        <div class="container text-center">
          <img class="img img-responsive" src="<?php echo base_url(); ?>assets/images/gifs/loading.gif" style="display: inline;">
          <div class="col-lg-12 col-md-12 col-xs-12">
            <img class="img img-responsive" src="<?php echo base_url(); ?>assets/images/gifs/dashboard.gif" style="display: inline;">
          </div>    
        </div>
      </div>
    </div>
    
    <section id="main" class="content" style="display: none;">
      <div class="container">

      <div id="dashboardEmptyState" class="callout callout-info" style="display: none;">
        <h4><i class="fa fa-info-circle"></i> No dashboard records yet</h4>
        <p>Run a job or import TMF data to populate dashboard metrics and charts.</p>
      </div>

      <div class="row">
        <div class="col-xs-12">
          <div class="box box-solid dashboard-jenkins-panel dashboard-card">
            <div class="box-body">
              <div>
                <h4 class="dashboard-jenkins-title"><i class="fa fa-server"></i> Jenkins Live</h4>
                <p class="dashboard-jenkins-detail" id="dashboardJenkinsDetail">Loading Jenkins state...</p>
              </div>
              <div class="dashboard-jenkins-stats">
                <div class="dashboard-jenkins-stat">
                  <strong id="dashboardJenkinsCapacity">--</strong>
                  <span>JobSeeker Slots</span>
                </div>
                <div class="dashboard-jenkins-stat">
                  <strong id="dashboardJenkinsQueue">--</strong>
                  <span>Slot Queue</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

        <div class="row dashboard-status-row dashboard-equal-row">
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-aqua running animated">

                <div class="inner">
                 <span id="running"><h3></h3></span>
                  <p>Running Entries</p>
                </div>
                <div class="icon">
                  <i class="fa fa-refresh"></i>
                </div>
                <a href="<?php echo base_url(); ?>tmf/fetchDataStatus/running" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
              </div>

            </div><!-- ./col -->

            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-green ready animated">
                <div class="inner">
                  <h3 id="ready"></h3>
                  <p>Ready Entries</p>
                </div>
                <div class="icon">
                  <i class="fa fa-check-square-o"></i>
                </div>
                <a href="<?php echo base_url(); ?>tmf/fetchDataStatus/ready" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
              </div>
            </div><!-- ./col -->
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-yellow warning animated">
                <div class="inner">
                  <h3 id="warning"></h3>
                  <p>Warning Entries</p>
                </div>
                <div class="icon">
                  <i class="fa fa-warning"></i>
                </div>
                <a href="<?php echo base_url(); ?>tmf/fetchDataStatus/warning" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
              </div>
            </div><!-- ./col -->
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-red error animated">
                <div class="inner">
                  <h3 id="error"></h3>
                  <p>Error Entries</p>
                </div>
                <div class="icon">
                  <i class="fa fa-thumbs-o-down"></i>
                </div>
                <a href="<?php echo base_url(); ?>tmf/fetchDataStatus/error" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
              </div>
            </div><!-- ./col -->
          </div>

          <!-- Aqui entra os graficos estastítisco -->

          <div class="row animated zoomIn" style="margin-top: 15px;">
            <div class="col-lg-12 col-xs-12">
              <div class="box box-primary dashboard-card dashboard-survey-card">
            <div class="box-header with-border">
              <h3 class="box-title"><b>Jobs survey report</b></h3>

              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <div class="btn-group">
                </div>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <div id="dashboardSurveyContent" class="row">
                <div class="col-md-8">
                  <p class="text-center">
                    <strong id="date"></strong>
                  </p>

                  <div class="chart">
                    <!-- Sales Chart Canvas -->
                     <canvas id="chart" class="lineChart" style="height: 300px; width: 600px;" height="300" width="600"></canvas> 
                  </div>
                  <!-- /.chart-responsive -->
                </div>
                <!-- /.col -->
                <div class="col-md-4">
                  <p class="text-center">
                    <strong>TMF status distribution</strong>
                  </p>

                  <div class="progress-group runningGraph animated">
                    <span class="progress-text">Running Entries</span>
                    <span class="progress-number" id="runningGraph"></span>

                    <div class="progress sm">
                      <div id="runningGraphBar" class="progress-bar progress-bar-aqua"></div>
                    </div>
                  </div>
                  <!-- /.progress-group -->
                  <div class="progress-group readyGraph animated ">
                    <span class="progress-text">Ready Entries</span>
                    <span class="progress-number" id="readyGraph"><b></b></span>

                    <div class="progress sm">
                      <div id="readyGraphBar" class="progress-bar progress-bar-green" style="width: 0%;"></div>
                    </div>
                  </div>
                     <!-- /.progress-group -->
                  <div class="progress-group warningGraph animated">
                    <span class="progress-text">Warning Entries</span>
                    <span class="progress-number" id="warningGraph"></span>

                    <div class="progress sm">
                      <div id="warningGraphBar" class="progress-bar progress-bar-yellow" style="width: 0%;"></div>
                    </div>
                  </div>
                  <!-- /.progress-group -->
                  <div class="progress-group errorGraph animated">
                    <span class="progress-text">Error Entries</span>
                    <span class="progress-number" id="errorGraph"></span>

                    <div class="progress sm">
                      <div id="errorGraphBar" class="progress-bar progress-bar-red"></div>
                    </div>
                  </div>
                </div>
                <!-- /.col -->
              </div>
              <div id="dashboardSurveyEmptyState" class="dashboard-empty-panel" style="display: none;">
                <i class="fa fa-line-chart"></i>
                <h4>No survey data yet</h4>
                <p>Job trends and status progress will appear after TMF records are created.</p>
              </div>
              <!-- /.row -->
            </div>
            <!-- ./box-body -->
            <div id="dashboardSurveyFooter" class="box-footer">
              <div class="row">
                <div class="col-sm-12 col-xs-12">
                  <span class="text-center"><h5><b>Growth X Decline in 30 days (1 Month)</b></h5></span>
                </div>
                <hr>
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="readyGrowthDecline"> </span>
                    
                    <span class="description-text text-green">Ready </span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="errorGrowthDecline"> </span>
                    
                    <span class="description-text text-red">Error</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="warningGrowthDecline"></span>
                    
                    <span class="description-text text-yellow">Warning</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="runningGrowthDecline"> </span>
                    
                    <span class="description-text text-blue">Running Entries</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
              </div>
              <!-- /.row -->
              <hr>
              <div class="row">
                <div class="col-sm-12 col-xs-12">
                  <span class="text-center"><h5><b>Growth X Decline in 90 days (3 Month)</b></h5></span>
                </div>
                <hr>
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="readyGrowthDeclineX90"> </span>
                    
                    <span class="description-text text-green">Ready </span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="errorGrowthDeclineX90"> </span>
                    
                    <span class="description-text text-red">Error</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="warningGrowthDeclineX90"></span>
                    
                    <span class="description-text text-yellow">Warning</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="runningGrowthDeclineX90"> </span>
                    
                    <span class="description-text text-blue">Running Entries</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
              </div>
              <!-- /.row -->
              <hr>
              <div class="row">
                <div class="col-sm-12 col-xs-12">
                  <span class="text-center"><h5><b>Growth X Decline in 180 days (6 Month)</b></h5></span>
                </div>
                <hr>
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="readyGrowthDeclineX180"> </span>
                    
                    <span class="description-text text-green">Ready </span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="errorGrowthDeclineX180"> </span>
                    
                    <span class="description-text text-red">Error</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="warningGrowthDeclineX180"></span>
                    
                    <span class="description-text text-yellow">Warning</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
                <!-- /.col -->
                <div class="col-sm-3 col-xs-6">
                  <div class="description-block border-right">
                    <span class="description-percentage" id="runningGrowthDeclineX180"> </span>
                    
                    <span class="description-text text-blue">Running Entries</span> Growth
                  </div>
                  <!-- /.description-block -->
                </div>
              </div>
              <!-- /.row -->
            </div>
            <!-- /.box-footer -->
          </div>
            </div>
          </div>

              <div class="row dashboard-equal-row dashboard-paired-row">
               <!-- Div last jobs -->
            <div class="col-lg-6 col-md-6 col-xs-12 animated fadeInLeft">
                <div class="box box-primary dashboard-card dashboard-recent-card">
            <div class="box-header with-border">
              <h3 class="box-title">Recently Added Jobs</h3>

              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <ul id="dashboardRecentJobsList" class="products-list product-list-in-box">
                 <?php
                    if(!empty($lastJobs))
                    {
                        foreach($lastJobs as $record)
                        {
                    ?>
                <!-- item -->
                <li class="item">
                  <div class="product-img">
                    <?php 
                      switch ($record->status) {
                        case 'ready':
                          echo '<img src="' . base_url() . 'assets/images/items/ready.png" alt="Ready">';
                          break;

                        case 'error':
                          echo '<img src="' . base_url() . 'assets/images/items/error.png" alt="Error">';
                          break;

                          case 'warning':
                          echo '<img src="' . base_url() . 'assets/images/items/warning.png" alt="Warning">';
                          break;

                          case 'running':
                          echo '<img src="' . base_url() . 'assets/images/items/running.png" alt="Running">';
                          break;

                          case 'cancelled':
                          case 'Cancelled':
                          echo '<img src="' . base_url() . 'assets/images/items/404.png" alt="Cancelled">';
                          break;
                        
                        default:
                          echo '<img src="' . base_url() . 'assets/images/items/404.png" alt="Error 404">';
                          break;
                      }
                    ?>
                  </div>
                  <div class="product-info">
                    <a href="<?php echo base_url(); ?>tmf/fetchDataJobName/<?php echo rawurlencode($record->job_name); ?>" class="product-title"><?php echo html_escape($record->job_name); ?>
                    <?php 
                      switch ($record->status) {
                        case 'ready':
                          echo '<span class="label label-success pull-right">Ready</span>';
                          break;

                        case 'error':
                          echo '<span class="label label-danger pull-right">Error</span>';
                          break;

                          case 'warning':
                          echo '<span class="label label-warning pull-right">Warning</span>';
                          break;

                          case 'running':
                          echo '<span class="label label-primary pull-right">Running</span>';
                          break;

                          case 'cancelled':
                          case 'Cancelled':
                          echo '<span class="label label-default pull-right">Cancelled</span>';
                          break;
                        
                        default:
                          echo '<span class="label label-danger pull-right">404 Error</span>';
                          break;
                      }
                    ?>
                    </a>
                    <span class="product-description"> <?php echo html_escape($record->event_text); ?> </span>
                    <span class="product-description"><i class="fa fa-globe"></i> <?php echo trim((string) $record->environment) !== '' ? html_escape($record->environment) : '<span class="label label-default">Unknown</span>'; ?></span>
                    <span class="product-description"> <?php if ($record->records_processed != 0) { echo (int) $record->records_processed.' Rows Were Affected.'; }?>
                    </span>

                  </div>
                </li>
                <!-- /.item -->
                 <?php
                        }
                    }
                    else
                    {
                 ?>
                <li id="dashboardRecentJobsEmptyState" class="item">
                  <div class="dashboard-empty-panel dashboard-empty-panel-small">
                    <i class="fa fa-history"></i>
                    <h4>No recent jobs</h4>
                    <p>Recent TMF job activity will appear here.</p>
                  </div>
                </li>
                 <?php
                    }
                 ?>
             
              </ul>
            </div>
            <!-- /.box-body -->
            <div class="box-footer text-center">
              <a href="<?php echo base_url(); ?>tmf" class="uppercase">View All Jobs</a>
            </div>
            <!-- /.box-footer -->
          </div>
            </div>
            <!-- End Div last jobs -->

            <!-- Div Graficos -->
            <div class="col-lg-6 col-md-6 col-xs-12 animated fadeInRight">
              <div class="box box-primary dashboard-card dashboard-percent-card">
            <div class="box-header with-border">
              <h3 class="box-title">TMF Status Percent Report</h3>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <div id="dashboardPercentContent" class="row">
                <div class="col-md-8">
                  <div class="chart-responsive">
                    <canvas id="pieChart" height="220" width="320" style="width: 320px; height: 220px;"></canvas>
                  </div>
                  <!-- ./chart-responsive -->
                </div>
                <!-- /.col -->
                <div class="col-md-4">
                  <ul class="chart-legend clearfix">
                    <li><i class="fa fa-circle-o text-green"></i> Ready</li>
                    <li><i class="fa fa-circle-o text-red"></i> error</li>
                    <li><i class="fa fa-circle-o text-yellow"></i> Warning</li>
                    <li><i class="fa fa-circle-o text-aqua"></i> Running Entries</li>
                    <li><i class="fa fa-circle-o text-muted"></i> Cancelled</li>
                  </ul>
                </div>
                <div class="col-md-4">
                  <ul class="chart-legend clearfix">
                    <li id="totalJobs"> </li>
                    <li>Represents 100%</li>
                  </ul>
                </div>
                <!-- /.col -->
              </div>
              <div id="dashboardPercentEmptyState" class="dashboard-empty-panel" style="display: none;">
                <i class="fa fa-pie-chart"></i>
                <h4>No status distribution yet</h4>
                <p>Percentages will appear here after TMF records are created.</p>
              </div>
              <!-- /.row -->
            </div>
            <!-- /.box-body -->
            <div id="dashboardPercentFooter" class="box-footer" style="padding: 9px;">
              <ul class="nav nav-pills nav-stacked">
                <li><a href="#">Percent of <b class="text-green">Ready</b> from total
                  <span class="pull-right" id="pecentTotalReady"> </span></a></li>

                <li><a href="#">Percent of <b class="text-red">Error</b> from total
                  <span class="pull-right" id="pecentTotalError"> </span></a></li>

                  <li><a href="#">Percent of <b class="text-yellow">Warning</b> from total
                  <span class="pull-right" id="pecentTotalWarning"> </span></a></li>

                  <li><a href="#">Percent of <b class="text-blue">Running Entries</b> from total
                  <span class="pull-right" id="pecentTotalRunning"> </span></a></li>
               
              </ul>
            </div>
            <!-- /.footer -->
          </div>

        </div>
        <!-- End Div Graficos -->
      </div>

      <div class="row dashboard-equal-row dashboard-table-row">
        <div class="col-lg-6 col-md-6 col-xs-12">
          <div class="box box-info dashboard-card dashboard-table-card">
            <div class="box-header">
              <h3 class="box-title">Available job execution amount</h3>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <div id="dashboardJobsAmountEmptyState" class="dashboard-empty-panel dashboard-empty-panel-small" style="<?php echo $hasJobsAmount ? 'display: none;' : ''; ?>">
                <i class="fa fa-table"></i>
                <h4>No job execution amounts</h4>
                <p>Execution totals by job and dimension will appear here.</p>
              </div>
              <table id="dashboardJobsAmountTable" class="table table-bordered table-striped<?php echo $hasJobsAmount ? ' table-config' : ''; ?>" style="<?php echo $hasJobsAmount ? '' : 'display: none;'; ?>">
                <thead>
                <tr>
                  <th>Job Name</th>
                  <th>Dimension</th>
                  <th>Environment</th>
                  <th>Amount</th>
                </tr>
                </thead>
                <tbody>
                  <?php
                    if(!empty($jobsAmount))
                    {
                        foreach($jobsAmount as $record)
                        {
                    ?>
                    <tr>
                      <td><span class="dashboard-job-name" title="<?php echo html_escape($record->JOB_NAME); ?>"><?php echo html_escape($record->JOB_NAME); ?></span></td>
                        <td><?php echo html_escape($record->DIMENSION); ?></td>
                        <td><?php echo $record->ENVIRONMENT === 'Unknown' ? '<span class="label label-default">Unknown</span>' : html_escape($record->ENVIRONMENT); ?></td>
                        <td><?php echo $record->AMOUNT ?></td>
                    </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                 <tr>
                  <th>Job Name</th>
                  <th>Dimension</th>
                  <th>Environment</th>
                  <th>Amount</th>
                </tr>
                </tfoot>
              </table>
            </div>
            <!-- /.box-body -->
          </div>
          <!-- /.box -->
        </div>

        <div class="col-lg-6 col-md-6 col-xs-12">
          <div class="box box-info dashboard-card dashboard-table-card">
            <div class="box-header">
              <h3 class="box-title">Available status amount per jobs</h3>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <div id="dashboardJobsStatusAmountEmptyState" class="dashboard-empty-panel dashboard-empty-panel-small" style="<?php echo $hasJobsStatusAmount ? 'display: none;' : ''; ?>">
                <i class="fa fa-list-alt"></i>
                <h4>No status totals</h4>
                <p>Status totals by job and dimension will appear here.</p>
              </div>
              <table id="dashboardJobsStatusAmountTable" class="table table-bordered table-striped<?php echo $hasJobsStatusAmount ? ' table-config' : ''; ?>" style="<?php echo $hasJobsStatusAmount ? '' : 'display: none;'; ?>">
                <thead>
                <tr>
                  <th>Job Name</th>
                  <th>Dimension</th>
                  <th>Environment</th>
                  <th>Status</th>
                  <th>Amount</th>
                </tr>
                </thead>
                <tbody>
                  <?php
                    if(!empty($jobsStatusAmount))
                    {
                        foreach($jobsStatusAmount as $record)
                        {
                    ?>
                    <tr>
                      <td><span class="dashboard-job-name" title="<?php echo html_escape($record->JOB_NAME); ?>"><?php echo html_escape($record->JOB_NAME); ?></span></td>
                        <td><?php echo html_escape($record->DIMENSION); ?></td>
                        <td><?php echo $record->ENVIRONMENT === 'Unknown' ? '<span class="label label-default">Unknown</span>' : html_escape($record->ENVIRONMENT); ?></td>
                        <td><?php 
                      switch (strtolower((string) $record->STATUS)) {
                        case 'ready':
                          echo '<span class="label label-success">Ready</span>';
                          break;

                        case 'error':
                          echo '<span class="label label-danger">Error</span>';
                          break;

                          case 'warning':
                          echo '<span class="label label-warning">Warning</span>';
                          break;

                          case 'running':
                          echo '<span class="label label-primary">Running</span>';
                          break;

                          case 'cancelled':
                          case 'Cancelled':
                          echo '<span class="label label-default">Cancelled</span>';
                          break;
                        
                        default:
                          echo '<span class="label label-default">Unknown</span>';
                          break;
                      }
                    ?></td>
                        <td><?php echo $record->AMOUNT ?></td>
                    </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                 <tr>
                  <th>Job Name</th>
                  <th>Dimension</th>
                  <th>Environment</th>
                  <th>Status</th>
                  <th>Amount</th>
                </tr>
                </tfoot>
              </table>
            </div>
            <!-- /.box-body -->
          </div>
          <!-- /.box -->
        </div>
      </div>

      <div class="row dashboard-equal-row dashboard-table-row">
        <div class="col-lg-12 col-md-12 col-xs-12">
          <div class="box box-info dashboard-card dashboard-table-card">
            <div class="box-header">
              <h3 class="box-title">Environment execution summary</h3>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <div id="dashboardEnvironmentSummaryEmptyState" class="dashboard-empty-panel dashboard-empty-panel-small" style="<?php echo $hasEnvironmentSummary ? 'display: none;' : ''; ?>">
                <i class="fa fa-globe"></i>
                <h4>No environment activity</h4>
                <p>Environment totals will appear after TMF records include runtime context.</p>
              </div>
              <table id="dashboardEnvironmentSummaryTable" class="table table-bordered table-striped<?php echo $hasEnvironmentSummary ? ' table-config' : ''; ?>" style="<?php echo $hasEnvironmentSummary ? '' : 'display: none;'; ?>">
                <thead>
                <tr>
                  <th>Environment</th>
                  <th>Total Runs</th>
                  <th>Ready</th>
                  <th>Running</th>
                  <th>Attention</th>
                  <th>Last Activity</th>
                </tr>
                </thead>
                <tbody>
                  <?php
                    if(!empty($environmentSummary))
                    {
                        foreach($environmentSummary as $record)
                        {
                          $environmentName = isset($record->ENVIRONMENT) ? (string) $record->ENVIRONMENT : 'Unknown';
                    ?>
                    <tr>
                      <td><?php echo $environmentName === 'Unknown' ? '<span class="label label-default">Unknown</span>' : html_escape($environmentName); ?></td>
                      <td><?php echo number_format((int) $record->AMOUNT); ?></td>
                      <td><span class="label label-success"><?php echo number_format((int) $record->READY); ?></span></td>
                      <td><span class="label label-primary"><?php echo number_format((int) $record->RUNNING); ?></span></td>
                      <td><span class="label label-<?php echo ((int) $record->ATTENTION) > 0 ? 'danger' : 'default'; ?>"><?php echo number_format((int) $record->ATTENTION); ?></span></td>
                      <td><?php echo empty($record->LAST_ACTIVITY) ? '-' : html_escape(date('m-d-Y H:i:s', strtotime($record->LAST_ACTIVITY))); ?></td>
                    </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-12 col-xs-12">
          <div class="box box-solid" style="padding: 10px; box-shadow: 10px 10px 5px -4px rgba(0,0,0,0.75);">
            <div class="text-center">
              <h4><b>Data Warehouse and Data Marts</b></h4>
            </div>
          </div>
        </div>
      </div>


      <div class="row dashboard-equal-row dashboard-info-row" style="margin-top: 15px;">
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-bar-chart"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Data Warehouses</span>
              <small>Loads</small>
              <span class="info-box-number" id="dwAmount"></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-database"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Dimensions Tables</span>
              <small>Loads</small>
              <span class="info-box-number" id="dimTableAmount"></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->

        <!-- fix for small devices only -->
        <div class="clearfix visible-sm-block"></div>

        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-database"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Facts Tables</span>
              <small>Loads</small>
              <span class="info-box-number" id="factTableAmount"></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-database"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Stagging Tables</span>
              <small>Loads</small>
              <span class="info-box-number" id="stgTableAmount"></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
      </div>

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-12 col-md-12 col-xs-12">
          <div class="box box-primary dashboard-card dashboard-chart-card">
            <div class="box-header">
              <h4><b>Data Warehouse and Data Marts Execution</b></h4>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <div id="dashboardDwChartContent">
                <canvas id="dwChart" style="height: 200px; width: 600px;" height="230" width="600"></canvas>
              </div>
              <div id="dashboardDwChartEmptyState" class="dashboard-empty-panel dashboard-empty-panel-small" style="display: none;">
                <i class="fa fa-bar-chart"></i>
                <h4>No warehouse executions</h4>
                <p>Warehouse and data mart execution charts will appear here.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-12 col-md-12 col-xs-12">
          <div class="box box-primary dashboard-card dashboard-chart-card">
            <div class="box-header">
              <h4><b>Dimension Tables Executions</b></h4>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <div id="dashboardDmChartContent">
                <canvas id="dmChart" style="height: 200px; width: 600px;" height="230" width="600"></canvas>
              </div>
              <div id="dashboardDmChartEmptyState" class="dashboard-empty-panel dashboard-empty-panel-small" style="display: none;">
                <i class="fa fa-database"></i>
                <h4>No dimension executions</h4>
                <p>Dimension table execution charts will appear here.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-12 col-md-12 col-xs-12">
          <div class="box box-primary dashboard-card dashboard-chart-card">
            <div class="box-header">
              <h4><b>Fact Tables Executions</b></h4>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <div id="dashboardFactChartContent">
                <canvas id="factChart" style="height: 200px; width: 600px;" height="230" width="600"></canvas>
              </div>
              <div id="dashboardFactChartEmptyState" class="dashboard-empty-panel dashboard-empty-panel-small" style="display: none;">
                <i class="fa fa-database"></i>
                <h4>No fact executions</h4>
                <p>Fact table execution charts will appear here.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-12 col-md-12 col-xs-12">
          <div class="box box-primary dashboard-card dashboard-chart-card">
            <div class="box-header">
              <h4><b>Stg Tables Executions</b></h4>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <div id="dashboardStgChartContent">
                <canvas id="stgChart" style="height: 200px; width: 600px;" height="230" width="600"></canvas>
              </div>
              <div id="dashboardStgChartEmptyState" class="dashboard-empty-panel dashboard-empty-panel-small" style="display: none;">
                <i class="fa fa-database"></i>
                <h4>No staging executions</h4>
                <p>Staging table execution charts will appear here.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      </div>
        
    </section>
</div>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.js"></script>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/moment/moment.min.js"></script>
<script>
$(document).ready(function(){
    if(window.matchMedia("(max-width: 767px)").matches){
        // The viewport is less than 768 pixels wide
        // alert("This is a mobile device.");
        $('.table-config').removeClass('dataTable')
        $('.table-config').addClass('dataTableMobile')
        $('.lineChart').remove();
       $('.chart').append('<canvas id="chart" class="lineChart" style="height: 300px; width: 300px;" height="300" width="300"></canvas>');
    } else{
        // The viewport is at least 768 pixels wide
       // alert("This is a tablet or desktop.");
       
    }
});
</script>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/dashboard.js?v=35"></script>

