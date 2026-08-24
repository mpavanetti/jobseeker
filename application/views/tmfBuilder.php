 <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
</script>
<link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/bower_components/select2/dist/css/select2.min.css">
<style>
  .tmf-builder-page .content {
    padding: 18px;
  }

  .tmf-shell {
    max-width: 1560px;
    width: 100%;
  }

  .tmf-hero {
    align-items: center;
    background: linear-gradient(135deg, #102a43 0%, #1f6f78 52%, #f2a154 100%);
    border-radius: 6px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    min-height: 112px;
    overflow: hidden;
    padding: 22px 24px;
    position: relative;
  }

  .tmf-hero:after {
    background: repeating-linear-gradient(135deg, rgba(255,255,255,.18) 0, rgba(255,255,255,.18) 1px, transparent 1px, transparent 12px);
    bottom: 0;
    content: "";
    opacity: .35;
    position: absolute;
    right: 0;
    top: 0;
    width: 34%;
  }

  .tmf-hero h2 {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 4px;
  }

  .tmf-hero p {
    color: rgba(255,255,255,.82);
    margin: 0;
  }

  .tmf-hero .btn {
    border: 0;
    box-shadow: 0 8px 18px rgba(0,0,0,.18);
    position: relative;
    z-index: 1;
  }

  .tmf-summary-row {
    padding-top: 18px;
  }

  .tmf-builder-page .info-box {
    border: 1px solid #edf1f5;
    border-radius: 6px;
    box-shadow: 0 6px 18px rgba(16, 42, 67, .08);
    min-height: 86px;
  }

  .tmf-builder-page .info-box-icon {
    border-radius: 6px 0 0 6px;
    height: 86px;
    line-height: 86px;
  }

  .tmf-query-card {
    background: #fff;
    border: 1px solid #d8e0e8;
    border-radius: 6px;
    box-shadow: 0 10px 24px rgba(16, 42, 67, .08);
    margin-top: 18px;
    padding: 18px;
  }

  .tmf-query-card-header {
    align-items: center;
    border-bottom: 1px solid #edf1f5;
    display: flex;
    justify-content: space-between;
    margin: -2px 0 18px;
    padding-bottom: 12px;
  }

  .tmf-query-card-header h3 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
  }

  .tmf-query-card-header span {
    color: #6b7c8f;
  }

  .tmf-query-card .form-group label {
    color: #243b53;
    font-size: 12px;
    letter-spacing: .02em;
    text-transform: uppercase;
  }

  .tmf-query-card .select2-container--default .select2-selection--multiple,
  .tmf-query-card .form-control {
    border-color: #d8e0e8;
    border-radius: 4px;
    min-height: 38px;
  }

  .tmf-date-shortcuts {
    display: flex;
    gap: 6px;
    margin-top: 24px;
  }

  .tmf-date-shortcuts .btn {
    flex: 1 1 auto;
  }

  .tmf-action-group {
    display: flex;
    gap: 8px;
    margin-top: 25px;
  }

  .tmf-action-group .btn {
    min-width: 105px;
  }

  @media (max-width: 767px) {
    .tmf-hero {
      align-items: flex-start;
      flex-direction: column;
      gap: 14px;
    }

    .tmf-date-shortcuts,
    .tmf-action-group {
      margin-top: 0;
    }
  }
</style>
<div class="content-wrapper tmf-builder-page">
    <section class="content-header">
      <h1>
        <i class="fa fa-dashboard"></i> Transaction Monitoring Framework <b>Query Builder</b>
        <small>Log your data transactions</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Transaction Monitoring</a></li>
      </ol>
    </section>


    <section class="content tmf-builder-content">

      <div class="container-fluid tmf-shell">
        <div class="tmf-hero animated fadeIn">
          <div>
            <h2>Transaction Monitor</h2>
            <p>Build a precise TMF query across status, environment, job, dimensions, text, and run dates.</p>
          </div>
          <a href="<?php echo base_url(); ?>Tmf/data" class="btn btn-default"><i class="fa fa-table"></i> Open All Results</a>
        </div>
        <div class="row tmf-summary-row">
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-aqua"><i class="fa fa-pie-chart"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Available Status</span>
              <span class="info-box-number"><?php echo count($listStatus); ?></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-red"><i class="fa fa-bar-chart"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Available Jobs</span>
              <span class="info-box-number"><?php echo count($listJobName); ?></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->

        <!-- fix for small devices only -->
        <div class="clearfix visible-sm-block"></div>

        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-green"><i class="fa fa-database"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Available Dimensions</span>
              <span class="info-box-number"><?php echo count($listDimension); ?></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-yellow"><i class="fa fa-refresh"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Enabled Reprocess</span>
              <span class="info-box-number"><?php echo count($listReprocess); ?></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
      </div>
       
        <div class="tmf-query-card animated fadeIn">
          <div class="tmf-query-card-header">
            <h3><i class="fa fa-filter"></i> Query Filters</h3>
              <span><?php echo count($listStatus); ?> statuses &middot; <?php echo count($listJobName); ?> jobs &middot; <?php echo count($listEnvironment); ?> configured or observed environments</span>
          </div>
           <form action="<?php echo base_url() ?>Tmf/fetchData" method="POST" id="searchList">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>" />
            <div class="row">
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Status</label>
                      <select class="form-control select2" name="status[]" multiple="multiple">
                        <option value="*" selected>All</option>
                        <?php
                          if(!empty($listStatus))
                          {
                              foreach($listStatus as $record)
                              {
                                $statusValue = isset($record->status) ? $record->status : '';
                                $statusLabel = isset($record->status_label) ? $record->status_label : ucfirst($statusValue);
                          ?>
                           <option value="<?php echo html_escape($statusValue); ?>"><?php echo html_escape($statusLabel); ?></option>
                         <?php
                           }
                         }
                        ?>
                  </select>
                </div>
              </div>

               <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Available Job Name</label>
                      <select class="form-control select2" name="job_name[]" multiple="multiple">
                        <option value="*" selected>All</option>
                           <?php
                          if(!empty($listJobName))
                          {
                              foreach($listJobName as $record)
                              {
                          ?>
                           <option value="<?php echo html_escape($record->job_name) ?>"><?php echo html_escape($record->job_name) ?></option>
                         <?php
                           }
                         }
                        ?>
                  </select>
                </div>
              </div>

              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group tmf-builder-environment-filter">
                <div class="input-group" style="width: 100%;">
                  <label>Environment</label>
                      <select class="form-control select2" name="environment[]" multiple="multiple">
                        <option value="*" selected>All</option>
                        <option value="__UNKNOWN__">Unknown / not recorded</option>
                           <?php
                          if(!empty($listEnvironment))
                          {
                              foreach($listEnvironment as $record)
                              {
                                $environmentValue = isset($record->environment) ? trim((string) $record->environment) : '';
                                if ($environmentValue === '') {
                                  continue;
                                }
                          ?>
                           <option value="<?php echo html_escape($environmentValue); ?>"><?php echo html_escape($environmentValue); ?></option>
                         <?php
                           }
                         }
                        ?>
                  </select>
                </div>
              </div>

               <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Dimension (DW / DM)</label>
                      <select class="form-control select2" name="dimension[]" multiple="multiple">
                        <option value="*" selected>All</option>
                           <?php
                          if(!empty($listDimension))
                          {
                              foreach($listDimension as $record)
                              {
                          ?>
                           <option value="<?php echo html_escape($record->dimension) ?>"><?php echo html_escape($record->dimension) ?></option>
                         <?php
                           }
                         }
                        ?>
                  </select>
                </div>
              </div>
           </div>

           <div class="row animated fadeIn" style="margin-top: 25px;">
              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <label for="fromDate">From Date / Time</label>
                <div class="input-group">
                  <input id="fromDate" type="datetime-local" name="fromDate" value="" class="form-control" title="From date and time" step="60" autocomplete="off" />
                  <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                </div>
              </div>
              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <label for="toDate">To Date / Time</label>
                <div class="input-group">
                  <input id="toDate" type="datetime-local" name="toDate" value="" class="form-control" title="To date and time" step="60" autocomplete="off" />
                  <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                </div>
              </div>
              <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 form-group">
                <label for="searchText">Event Text</label>
                <div class="input-group">
                  <input id="searchText" type="search" name="eventText" value="" class="form-control" placeholder="Search event text" maxlength="200" autocomplete="off" spellcheck="false" />
                  <span class="input-group-addon"><i class="fa fa-font"></i></span>
                </div>
              </div>
              <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 form-group">
                <div class="tmf-date-shortcuts">
                  <button type="button" class="btn btn-default tmf-date-shortcut" data-days="0">Today</button>
                  <button type="button" class="btn btn-default tmf-date-shortcut" data-days="7">7 days</button>
                  <button type="button" class="btn btn-default tmf-date-shortcut" data-days="30">30 days</button>
                </div>
              </div>
              

            </div>
            <div class="row animated fadeIn" style="margin-top: 25px;">
              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Reprocess</label>
                      <select class="form-control" name="reprocess">
                        <option value="*">All</option>
                        <option value="0">Disabled</option>
                        <option value="1">Enabled</option>
                  </select>
                </div>
              </div>
              <div class="col-lg-4 col-md-5 col-sm-12 col-xs-12 form-group">
                <div class="tmf-action-group">
                  <button type="submit" class="btn btn-md btn-primary searchList"><i class="fa fa-search" aria-hidden="true"></i> Run Query</button>
                  <button type="reset" class="btn btn-md btn-default resetFilters"><i class="fa fa-refresh" aria-hidden="true"></i> Reset</button>
                </div>
              </div>

            </div>
            
         </form>
        </div>
         
    </div>
      
    </section>

    <!-- Main content -->
   
    <!-- /.content -->
</div> 

<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/select2/dist/js/select2.min.js"></script>

<script type="text/javascript">
  $(document).ready(function() {
    $('.select2').select2({
       placeholder: "Select filters",
       allowClear: true
    });

      function formatDateTimeLocal(value) {
        var month = String(value.getMonth() + 1).padStart(2, '0');
        var day = String(value.getDate()).padStart(2, '0');
        var hours = String(value.getHours()).padStart(2, '0');
        var minutes = String(value.getMinutes()).padStart(2, '0');
        return value.getFullYear() + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
      }

      $('.tmf-date-shortcut').on('click', function() {
        var days = parseInt($(this).data('days'), 10) || 0;
        var endDate = new Date();
        var startDate = new Date();
        startDate.setDate(endDate.getDate() - days);
        startDate.setHours(0, 0, 0, 0);
        $('#fromDate').val(formatDateTimeLocal(startDate));
        $('#toDate').val(formatDateTimeLocal(endDate));
      });

      $('.resetFilters').on('click', function() {
        setTimeout(function() {
          $('select[name="status[]"], select[name="job_name[]"], select[name="environment[]"], select[name="dimension[]"]').val(['*']).trigger('change');
          $('select[name="reprocess"]').val('*');
          $('#fromDate, #toDate, #searchText').val('');
        }, 0);
      });

      toastr.options = {
        "closeButton": true,
        "debug": false,
        "positionClass": "toast-top-right",
        "newestOnTop": false,
        "timeOut": "10000",
        "progressBar": true}
        
      toastr.info("Ready to Fetch data from server, Please select the filters in which fit to your needed", "Ready to Fetch")
});
</script>