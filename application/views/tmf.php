 <?php $ready = 0; $error = 0; $warning = 0; $running = 0; ?>
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
    max-width: 750px;
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
            <div class="overlay" style="display:none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
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
                    <tr>
                      <td><?php echo '<span style="color:#3c8dbc;">'.$record->id.'</span>' ?></td>
                      <td id="status"><?php 
                      switch ($record->status) {
                          case 'ready':
                             echo '<span class="label label-success">Ready</span>';
                             $ready = $ready + 1;
                              break;
                          case 'running':
                             echo '<a class="btn btn-sm btn-info cancel" title="Click to Cancel this job">Running</a>';
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
                      <?php  if ($jenkins_enabled == true) { 
                         if($role == 1 || $role == 2) {  ?>
                      <td class="text-center"><?php echo ($record->reprocess == 1) ? '<span class="spin"><h3><i class="fa fa-refresh fa-spin "></i></h3></span><a href="#" class="btn btn-success reprocess" style="display: none;">Enable</a><span class="label label-danger reprocess-erro" style="display: none;">Error</span>' : '' ?></td><?php } else { echo '<td>Not Allowed</td>'; } } else { echo '<td>Not Available</td>';}?>
                      <td><?php echo $record->event_text ?></td>
                      <td><?php echo $record->environment ?></td>
                      <td><?php if ($record->msg == null) { echo ''; } else { echo '<a class="btn btn-sm btn-info msgSelect" href="#" title="Check Message">Check Message</a>'; } ?></td>
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
                       <td ><?php echo ($record->distict_errors == 1) ? '<a type="button" id="showError" class="btn btn-danger btnSelect"> Show Error </a>' : '' ?></td>
                       <td ><?php echo ($record->warnings == 1) ? '<a href="#" class="btn btn-warning">Warning</a>' : '' ?></td>
                       <td><?php echo $record->hostname ?></td>
                       <td><?php echo $record->username ?></td>
                       <td><?php echo $record->instance_id ?></td>
                      <?php if($role == 1 || $role == 2) {  ?> <td class="text-center">
                            <a class="btn btn-sm btn-danger deleteUser" href="#" data-userid="<?php echo $record->id; ?>" title="Delete"><i class="fa fa-trash"></i></a>
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

    //load 
 // $('#loading').fadeOut();
//  $('#main').delay(500).fadeIn();

});

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
        currentRow.parents('tr').remove();
        if(data.status = true) { alertify.success('Your record has been successfully deleted !'); }
        else if(data.status = false) { alertify.error("data deletion failed"); }
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

   alertify.confirm('Job cancelation request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure you want to send a cancelation request to the running job ' + job_name + ' ?</b></p><br></div></div></div>', 

              function(){ 
                 $.ajax({
                    url: jenkins_url + 'job/'+ job_name + '/lastBuild/stop',
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
                  $("#modal-main-msg").append('<div class="destroy-msg"><h4>Job Name: <b>' + value.job_name + '</b></h4><br><table class="table table-bordered"><tbody><tr><th>Header</th><th>Job Message</th></tr><tr><td>Instance ID</td><td>'+ value.instance_id +'</td></tr><tr><td>Job Name</td><td>'+ value.job_name +'</td></tr><tr><td>Message</td><td><pre>'+ value.msg +'</pre></td></tr></tbody></table><br></div>');
                });

  $('#modal-msg').modal('show');

});  

$("#table6").on('click','.btnSelect',function(){


         // get the current row Id, job name and instance id
         var currentRow=$(this).closest("tr"); 
         var instanceId=currentRow.find("td:eq(16)").text(); 
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
                  $("#modal-main").append('<div class="destroy"><h4>Error Id: <b>' + value.id + '</b></h4><br><table class="table table-bordered"><tbody><tr><th>Header</th><th>Job Message</th></tr><tr><td>Instance ID</td><td>'+ value.tmf_id +'</td></tr><tr><td>Job Name</td><td>'+ value.job_name +'</td></tr><tr><td>Moment</td><td>'+ moment(value.moment).format('dddd, MMMM Do YYYY, h:mm:ss') +'</td></tr><tr><td>Type</td><td>'+ value.type +'</td></tr><tr><td>Origin</td><td>'+ value.origin +'</td></tr><tr><td>Message</td><td>'+ value.message +'</td></tr></tbody></table><br></div>');
                });

         $('#modal-danger').modal('show');

    });


        // get Jenkins credentials
    var name = '<?php echo $name; ?>';
    var jenkins_url = '<?php echo $jenkins_url; ?>';
    var jenkins_username = '<?php echo $jenkins_username; ?>';
    var jenkins_token = '<?php echo $jenkins_token; ?>';
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


         alertify.confirm('Job Reprocess Confirmation', 'Are you sure you want to reprocess the job <b>' + jobName + '</b> ID (' + id +') ? \n \n *Please choose your option with caution.', 
          function(){ 

             $.ajax({
          url: jenkins_url + '/job/'+ jobName +'/build',
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