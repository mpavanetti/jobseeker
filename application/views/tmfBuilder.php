 <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
</script>
<link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/bower_components/select2/dist/css/select2.min.css">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker3.min.css" />
<div class="content-wrapper">    
    <section class="content-header">
      <h1>
        <i class="fa fa-dashboard"></i> Transaction Monitoring Framework <b>Query Builder</b>
        <small>Log your talend data transactions</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Transaction Monitoring</a></li>
      </ol>
    </section>


    <section class="content">

      <div class="container">
        <div class="row" style="padding-top: 15px;">
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
       
        <div class="row animated fadeIn" style="margin-top: 25px;">
           <form action="<?php echo base_url() ?>Tmf/fetchData" method="POST" id="searchList">
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
                          ?>
                           <option value="<?php echo $record->status ?>"><?php echo $record->status ?></option>
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
                           <option value="<?php echo $record->job_name ?>"><?php echo $record->job_name ?></option>
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
                           <option value="<?php echo $record->dimension ?>"><?php echo $record->dimension ?></option>
                         <?php
                           }
                         }
                        ?>
                  </select>
                </div>
              </div>

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
          </div>

           <div class="row animated fadeIn" style="margin-top: 25px;">
              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group">
                  <input id="fromDate" type="text" name="fromDate" value="" class="form-control datepicker" placeholder="From Date" autocomplete="off" />
                  <span class="input-group-addon"><label for="fromDate"><i class="fa fa-calendar"></i></label></span>
                </div>
              </div>
              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group">
                  <input id="toDate" type="text" name="toDate" value="" class="form-control datepicker" placeholder="To Date" autocomplete="off" />
                  <span class="input-group-addon"><label for="toDate"><i class="fa fa-calendar"></i></label></span>
                </div>
              </div>
              <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 form-group">
                <input id="searchText" type="text" name="eventText" value="" class="form-control" placeholder="Event Text"/>
              </div>
              <div class="col-lg-1 col-md-1 col-sm-6 col-xs-6 form-group">
                <button type="submit" class="btn btn-md btn-primary btn-block searchList pull-right"><i class="fa fa-search" aria-hidden="true"></i></button> 
              </div>
              <div class="col-lg-1 col-md-1 col-sm-6 col-xs-6 form-group">
                <button type="reset" class="btn btn-md btn-default btn-block pull-right resetFilters"><i class="fa fa-refresh" aria-hidden="true"></i></button>
              </div>

            </div>

            <div class="row">
              <div class="col-lg-12 col-md-12">
                  <div class="text-center">
                    <img class="img img-responsive animated bounceIn a" src="<?php echo base_url() ?>assets/images/gifs/truhero.gif" height="300" width="700" style="display: inline;">
                    <h2 class="b" style="display:none;"><b>Click to process</b></h2>
                  
                  </div>
              </div>
            </div>
            
         </form>
         
    </div>
      
    </section>

    <!-- Main content -->
   
    <!-- /.content -->
</div> 

<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/select2/dist/js/select2.min.js"></script>
<script src="<?php echo base_url(); ?>assets/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>

<script type="text/javascript">
  $(document).ready(function() {
    $('.select2').select2({
       placeholder: " Click to Select a option to fetch",
       allowClear: true
    });

      $('.datepicker').datepicker({
          autoclose: true,
          format : "dd-mm-yyyy"
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