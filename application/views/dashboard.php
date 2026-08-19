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

      <div class="row">
        <div class="col-xs-12">
          <div class="box box-solid dashboard-jenkins-panel">
            <div class="box-body">
              <div>
                <h4 class="dashboard-jenkins-title"><i class="fa fa-server"></i> Jenkins Live</h4>
                <p class="dashboard-jenkins-detail" id="dashboardJenkinsDetail">Loading Jenkins state...</p>
              </div>
              <div class="dashboard-jenkins-stats">
                <div class="dashboard-jenkins-stat">
                  <strong id="dashboardJenkinsCapacity">--</strong>
                  <span>Executors Busy</span>
                </div>
                <div class="dashboard-jenkins-stat">
                  <strong id="dashboardJenkinsQueue">--</strong>
                  <span>Queued Builds</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

        <div class="row dashboard-status-row">
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
              <div class="box box-primary">
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
              <div class="row">
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
              <!-- /.row -->
            </div>
            <!-- ./box-body -->
            <div class="box-footer">
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

          <div class="row">
               <!-- Div last jobs -->
            <div class="col-lg-6 col-md-6 col-xs-12 animated fadeInLeft">
                <div class="box box-primary">
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
              <ul class="products-list product-list-in-box">
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
                    <span class="product-description"> <?php if ($record->records_processed != 0) { echo (int) $record->records_processed.' Rows Were Affected.'; }?>
                    </span>

                  </div>
                </li>
                <!-- /.item -->
                 <?php
                        }
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
            <div class="col-lg 6 col-md-6 col-xs-12 animated fadeInRight">
                <div class="box box-primary">
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
              <div class="row">
                <div class="col-md-8">
                  <div class="chart-responsive">
                    <canvas id="pieChart" height="520" width="600" style="width: 600px; height: 520px;"></canvas>
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
                  <ul class="chart-legend clearfix" style="margin-top: 120px;">
                    <li id="totalJobs"> </li>
                    <li>Represents 100%</li>
                  </ul>
                </div>
                <!-- /.col -->
              </div>
              <!-- /.row -->
            </div>
            <!-- /.box-body -->
            <div class="box-footer" style="padding: 9px;">
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

      <div class="row">
        <div class="col-lg-6 col-md-6 col-xs-12">
          <div class="box box-info">
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
              <table class="table table-bordered table-striped table-config dataTable">
                <thead>
                <tr>
                  <th>Job Name</th>
                  <th>Dimension</th>
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
                      <td><?php echo $record->JOB_NAME ?></td>
                        <td><?php echo $record->DIMENSION ?></td>
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
          <div class="box box-info">
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
              <table class="table table-bordered table-striped table-config dataTable">
                <thead>
                <tr>
                  <th>Job Name</th>
                  <th>Dimension</th>
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
                      <td><?php echo $record->JOB_NAME ?></td>
                        <td><?php echo $record->DIMENSION ?></td>
                        <td><?php 
                      switch ($record->STATUS) {
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
                          echo '<span class="label label-danger">404 Error</span>';
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

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-12 col-xs-12">
          <div class="box box-solid" style="padding: 10px; box-shadow: 10px 10px 5px -4px rgba(0,0,0,0.75);">
            <div class="text-center">
              <h4><b>Data Warehouse and Data Marts</b></h4>
            </div>
          </div>
        </div>
      </div>


      <div class="row" style="margin-top: 15px;">
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
          <div class="box box-primary">
            <div class="box-header">
              <h4><b>Data Warehouse and Data Marts Execution</b></h4>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <canvas id="dwChart" style="height: 200px; width: 600px;" height="230" width="600"></canvas> 
            </div>
          </div>
        </div>
      </div>

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-12 col-md-12 col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h4><b>Dimension Tables Executions</b></h4>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <canvas id="dmChart" style="height: 200px; width: 600px;" height="230" width="600"></canvas> 
            </div>
          </div>
        </div>
      </div>

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-12 col-md-12 col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h4><b>Fact Tables Executions</b></h4>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <canvas id="factChart" style="height: 200px; width: 600px;" height="230" width="600"></canvas> 
            </div>
          </div>
        </div>
      </div>

      <div class="row" style="margin-top: 15px;">
        <div class="col-lg-12 col-md-12 col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h4><b>Stg Tables Executions</b></h4>
              <div class="box-tools pull-right">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                </button>
                <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
              </div>
            </div>
            <div class="box-body">
              <canvas id="stgChart" style="height: 200px; width: 600px;" height="230" width="600"></canvas> 
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
<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/dashboard.js?v=27"></script>

