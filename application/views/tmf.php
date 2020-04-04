 <?php $ready = 0; $error = 0; $warning = 0; $running = 0; ?>
 <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
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
</style>
<div class="content-wrapper">    
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
    <section id="main" class="content">
        <a href="<?php echo base_url(); ?>Tmf" class="btn btn-warning" style="margin-top: 15px;"><i class="fa fa-arrow-left"></i> Back to Query </a>
        <div class="digital-clock pull-right" >00:00:00</div>
      <div class="row" style="margin-top: 30px;">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h3 class="box-title"><b>Available Jobs</b></h3>
            </div>
            <!-- /.box-header -->

            <div class="box-body">
              <table id="table6" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th>Id</th>
                  <th>Status</th>
                  <th>Job Name</th>
                  <th>Dimension</th>
                  <th>Reprocess</th>
                  <th>Event Text</th>
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
                </tr>
                </thead>
                <tbody>
                  <?php
                    if(!empty($jobs))
                    {
                        foreach($jobs as $record)
                        {
                    ?>
                    <tr>
                      <td><?php echo '<span style="color:#3c8dbc;">'.$record->id.'</span>' ?></td>
                      <td id="status"><?php 
                      switch ($record->status) {
                          case 'ready':
                             echo '<span class="label label-success">Ready</span>';
                             $ready = $ready + 1;
                              break;
                          case 'running':
                             echo '<span class="label label-info">Running</span>';
                             $running = $running + 1;
                              break;
                          case 'error':
                             echo '<span class="label label-danger">Error</span>';
                             $error = $error + 1;
                              break;
                          case 'warning':
                             echo '<span class="label label-warning">Warning</span>';
                             $warning = $warning + 1;
                              break;
                          default:
                              echo $record->status;
                              break;
                        }
                      ?></td>
                      <td><?php echo $record->job_name ?></td>
                      <td><?php echo $record->dimension ?></td>
                      <td ><?php echo ($record->reprocess == 1) ? '<a href="#" class="btn btn-success">Enable</a>' : 'Disabled' ?></td>
                      <td><?php echo $record->event_text ?></td>
                      <td><?php echo $record->records_total ?></td>
                      <td><?php echo $record->records_processed ?></td>
                      <td><?php echo date('m-d-Y H:i:s', strtotime($record->start_time)) ?></td>
                      <td><?php echo date('m-d-Y H:i:s', strtotime($record->last_activity)) ?></td>
                       <td><?php
                        $d1 = new DateTime($record->start_time);
                        $d2 = new DateTime($record->last_activity);
                        $interval = $d2->diff($d1);
                        echo $interval->format('%d days, %H hours, %I minutes, %S seconds');
                        ?></td>
                       <td ><?php echo ($record->distict_errors == 1) ? '<a href="#" class="btn btn-danger">Error</a>' : 'None' ?></td>
                       <td ><?php echo ($record->warnings == 1) ? '<a href="#" class="btn btn-warning">Warning</a>' : 'None' ?></td>
                       <td><?php echo $record->hostname ?></td>
                       <td><?php echo $record->username ?></td>
                       <td><?php echo $record->instance_id ?></td>

                        <!-- <td><?php echo ($record->file_name == NULL) ? 'Not Available' : $record->file_name; ?></td> -->
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
                </tr>
                </tfoot>
              </table>
            </div>
            <!-- /.box-body -->
          </div>
          <!-- /.box -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
</div> 

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

    //load 
 // $('#loading').fadeOut();
//  $('#main').delay(500).fadeIn();

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