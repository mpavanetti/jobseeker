 <?php // print_r($jobs) ?>
 <!-- <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse');
  });
</script> -->

<style type="text/css">
  /* The switch - the box around the slider */
.switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 34px;
}

/* Hide default HTML checkbox */
.switch input {
  opacity: 0;
  width: 0;
  height: 5;
}

/* The slider */
.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  -webkit-transition: .4s;
  transition: .4s;
}

.slider:before {
  position: absolute;
  content: "";
  height: 26px;
  width: 26px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  -webkit-transition: .4s;
  transition: .4s;
}

input:checked + .slider {
  background-color: #2196F3;
}

input:focus + .slider {
  box-shadow: 0 0 1px #2196F3;
}

input:checked + .slider:before {
  -webkit-transform: translateX(26px);
  -ms-transform: translateX(26px);
  transform: translateX(26px);
}

/* Rounded sliders */
.slider.round {
  border-radius: 34px;
}

.slider.round:before {
  border-radius: 50%;
}

pre { 
    white-space: pre-wrap; 
    word-break: break-word;
    max-width: 750px;
}

</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1>
     <b>Job Build List</b>
     <small>Quick access to your jobs.</small>
   </h1>
   <ol class="breadcrumb">
    <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
    <li><a href="#">Job Management</a></li>
    <li class="active">Job List</li>
  </ol>
</section>

<!-- Main content -->
<section class="content">
 <?php if($role != 1) {  ?>
  <div class="row">
    <div class="col-xs-12 text-left">
      <div class="form-group">
        <a class="btn btn-primary" href="<?php echo base_url(); ?>jobCreation"><i class="fa fa-plus"></i> Add New Job</a>
        <a id="load" class="btn btn-success" href="#" style="margin-left: 10px;"><i class="fa fa-refresh"></i> Load Data</a>
         <label class="switch" style="margin-left: 15px; padding-top: 3px;">
          <input type="checkbox" name="refresh" id="refresh" value="1">
          <span class="slider round"></span>
        </label> <b style="margin-left: 10px; font-size: 15px;">Enable Auto Refresh</b>
      </div>

      
    </div>
  </div> 
<?php } ?>

<div class="modal fade" id="modal-default" style="display: none;">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span></button>
          <h4 class="modal-title">Job Build Console Log</h4>
        </div>
        <div class="modal-body" id="addLog">
          
       </div>
       <div class="modal-footer">
        <button type="button" class="btn btn-primary pull-left" data-dismiss="modal">Close</button>
      </div>
    </div>
    <!-- /.modal-content -->
  </div>
  <!-- /.modal-dialog -->
</div>


<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box2" class="box box-primary collapsed-box">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Available Jobs</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <table id="listTable" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>Build Situation</th>
              <th>Last Build Result</th>
              <th>Job Name</th>
              <th>Last Build Description</th>
              <th>Last Build Time</th>
              <th>Last Build Duration</th>
              <th>Last Build Number</th>
              <th>Trigger Job</th>
              <th>Last Build Output</th>
              <th>Abort Job</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
              <th>Build Situation</th>
              <th>Last Build Result</th>
              <th>Job Name</th>
              <th>Last Build Description</th>
              <th>Last Build Time</th>
              <th>Last Build Duration</th>
              <th>Last Build Number</th>
              <th>Trigger Job</th>
              <th>Last Build Output</th>
              <th>Abort Job</th>
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

<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box3" class="box box-danger collapsed-box">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Last Failed Job Builds</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <table id="listFailedTable" class="table table-bordered table-striped">
          <thead>
            <tr>
            <th>Job Name</th>
            <th>Result</th>
            <th>Last Build Number</th>
            <th>Last Failure Time</th>
            <th>Job Duration</th>
            <th>Job Url</th>
            <th>Queue Id</th>
            <th>Building</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
            <th>Job Name</th>
            <th>Result</th>
            <th>Last Build Number</th>
            <th>Last Failure Time</th>
            <th>Job Duration</th>
            <th>Job Url</th>
            <th>Queue Id</th>
            <th>Building</th>
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


<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box4" class="box box-success collapsed-box">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Last Success Job Builds</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <table id="listSuccessTable" class="table table-bordered table-striped">
          <thead>
            <tr>
            <th>Job Name</th>
            <th>Result</th>
            <th>Last Build Number</th>
            <th>Last Success Time</th>
            <th>Job Duration</th>
            <th>Job Url</th>
            <th>Queue Id</th>
            <th>Building</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
            <th>Job Name</th>
            <th>Result</th>
            <th>Last Build Number</th>
            <th>Last Success Time</th>
            <th>Job Duration</th>
            <th>Job Url</th>
            <th>Queue Id</th>
            <th>Building</th>
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
<!-- /.content-wrapper -->

<script type="text/javascript">

  $('#load').click(function(){
    $("#refresh").prop('checked', true);
    
        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

        toastr.info('Fetching data from server...', 'Query Data');
        $(".overlay").show();

      
        $("#listTable").dataTable().fnDestroy();
        $('#listTable').DataTable({
          "lengthMenu": [3,5,10,15,20,100,200,500,1000],
          "pageLength": 20,
          "order": [[ 1, "asc" ]],
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,color,description,fullName,builds[number,timestamp,duration,result]{0,1}]',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "color"},
          {"data": "builds[].result"},
          {"data": "name"},
          {"data": "description"},
          {"data": "builds[].timestamp"},
          {"data": "builds[].duration"},
          {"data": "builds[].number"},
          {"data": "fullName"},
          {"data": "name"},
          {"data": "color"}

          ],
           columnDefs:[{targets:0, render:function(data){
            if(data != null){
              if(data == 'aborted'){
                return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/bad.png">';
              }
              if(data == 'red'){
                return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/bad.png">';
              } else if (data == 'blue') {
                return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/good.png">';
              } else  if (data == 'notbuilt'){
                return '<b>Never Built</b>';
              } else {
                 return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/loading.gif">';
              }
            } else {return ''}
          }},{targets:4, render:function(data){
                if(data != ''){return moment(parseInt(data)).format('MMMM Do YYYY, h:mm:ss a');}else {return '<b>Never Built</b>' }
              }},{targets:5, render:function(data){
                if(data != ''){return moment(parseInt(data)).utc().format('HH [Hours, ] mm [Minutes, ] ss [Seconds, ] SSS [Miliseconds.]');} else {return '<b>Never Built</b>'}      
              }},{targets:1, render:function(data){
            if(data != null){if(data == 'SUCCESS') { return '<b style="color: green;">' + data + '</b>'} else {return '<b style="color: red;">' + data + '</b>'}} else {return ''}
          }},{targets:7, render:function(data){
            if(data != null){return '<button class="btn btn-sm btn-primary run" href="#" value="'+ data +'" title="click to trigger this job build">Build</button>'} else {return ''}
          }},{targets:8, render:function(data){
            if(data != null){return '<button class="btn btn-sm btn-info log" href="#" value="'+ data +'" title="click check this job console output">Check</button>'} else {return ''}
          }},{targets:9, render:function(data){
            if(data != null){
              if(data != 'aborted' && data != 'red' && data != 'blue' && data != 'notbuilt'){
                return '<button class="btn btn-sm btn-danger abort" href="#" value="'+ data +'" title="Click to cancel this job execution">Abort</button>';
              } else {return ''}
            }
          }}]
       });
      $('#box2').boxWidget('expand');

        $("#listFailedTable").dataTable().fnDestroy();
        $('#listFailedTable').DataTable({
          "lengthMenu": [3,5,10,15,20,100,200,500,1000],
          "pageLength": 20,
          "order": [[ 3, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,lastFailedBuild[displayName,result,timestamp,duration,url,queueId,building]{0,1}]',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "name"},
          {"data": "lastFailedBuild.result"},
          {"data": "lastFailedBuild.displayName"},
          {"data": "lastFailedBuild.timestamp"},
          {"data": "lastFailedBuild.duration"},
          {"data": "lastFailedBuild.url"},
          {"data": "lastFailedBuild.queueId"},
          {"data": "lastFailedBuild.building"},
          ],
          columnDefs:[{targets:0, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:1, render:function(data){
            if(data != null){if(data == 'SUCCESS') { return '<b style="color: green;">' + data + '</b>'} else {return '<b style="color: red;">' + data + '</b>'}} else {return ''}
          }},{targets:2, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:3, render:function(data){
            if(data != null){return moment(data).format('MMMM Do YYYY, h:mm:ss a');}else {return ''}
          }},{targets:4, render:function(data){
            if(data != null){return moment(data).utc().format('HH [Hours, ] mm [Minutes, ] ss [Seconds, ] SSS [Miliseconds.]');} else {return ''}      
          }},{targets:5, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:6, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:7, render:function(data){
            if(data != null){return data} else {return ''}
          }}]
       });
        $('#box3').boxWidget('expand');

        $("#listSuccessTable").dataTable().fnDestroy();
        $('#listSuccessTable').DataTable({
          "lengthMenu": [3,5,10,15,20,100,200,500,1000],
          "pageLength": 20,
          "order": [[ 3, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,lastStableBuild[displayName,result,timestamp,duration,url,queueId,building]{0,1}]',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "name"},
          {"data": "lastStableBuild.result"},
          {"data": "lastStableBuild.displayName"},
          {"data": "lastStableBuild.timestamp"},
          {"data": "lastStableBuild.duration"},
          {"data": "lastStableBuild.url"},
          {"data": "lastStableBuild.queueId"},
          {"data": "lastStableBuild.building"},

          ],
          columnDefs:[{targets:0, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:1, render:function(data){
            if(data != null){if(data == 'SUCCESS') { return '<b style="color: green;">' + data + '</b>'} else {return '<b style="color: red;">' + data + '</b>'}} else {return ''}
          }},{targets:2, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:3, render:function(data){
            if(data != null){return moment(data).format('MMMM Do YYYY, h:mm:ss a');}else {return '' }
          }},{targets:4, render:function(data){
            if(data != null){return moment(data).utc().format('HH [Hours, ] mm [Minutes, ] ss [Seconds, ] SSS [Miliseconds.]');} else {return ''}      
          }},{targets:5, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:6, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:7, render:function(data){
            if(data != null){return data} else {return ''}
          }}]
       });
        $('#box4').boxWidget('expand');

       $(".overlay").hide(); 

       
       if($('#refresh').is(":checked")){
         setInterval(function(){ 
          if($('#refresh').is(":checked")){
            $('#listTable').DataTable().ajax.reload();
            $('#listSuccessTable').DataTable().ajax.reload();
            $('#listFailedTable').DataTable().ajax.reload();

          } else if($(this).is(":not(:checked)")){
        }
         }, 1000);
       } else if($(this).is(":not(:checked)")){
        }
           
  })

 $('.abort').click(function(){
                  $.ajax({
                    url: jenkins_url + 'job/'+ job + '/' + data.id + '/stop',
                    method: 'POST',
                    async: false,
                    headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
                    beforeSend: function() {
                      toastr.warning("Your Stop Request has been sent to server.", "Request Sent")
                  }
                  }).done(function(data) {
                      toastr.error("Your Stop Request has been sent to server.", "Operation Aborted")
                  });
              }); 



 $("#listTable").on('click','.abort',function(){

       var jenkins_url = '<?php echo $jenkins_url; ?>',
           jenkins_username = '<?php echo $jenkins_username; ?>',
           jenkins_token = '<?php echo $jenkins_token; ?>',
           jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

       var currentRow=$(this).closest("tr"),
        job=currentRow.find("td:eq(8) button").val();
         

            alertify.confirm('Job <b style="color:red;">'+ job +'</b> Abort Request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p>Are you sure you want to Abort the job <b>'+ job +' ?</b></p></div></div></div>', 
      function(){ 

         $.ajax({
                    url: jenkins_url + 'job/'+ job + '/lastBuild/stop',
                    method: 'POST',
                    async: false,
                    headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
                    beforeSend: function() {
                      toastr.warning("Your Stop Request has been sent to server.", "Request Sent")
                  }
                  }).done(function(data) {
                toastr.warning("Your Abort Request has been sent to server.", "Request Sent")
                 setTimeout(function(){
                $('#listTable').DataTable().ajax.reload();
                $('#listSuccessTable').DataTable().ajax.reload();
                $('#listFailedTable').DataTable().ajax.reload();
             }, 1000);
                $('.overlay').hide();
              })
    }, 
      function(){ 
        alertify.error('Operation Aborted')
    }
  );
    
});

 $("#listTable").on('click','.run',function(){

       var jenkins_url = '<?php echo $jenkins_url; ?>',
           jenkins_username = '<?php echo $jenkins_username; ?>',
           jenkins_token = '<?php echo $jenkins_token; ?>',
           jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

       var currentRow=$(this).closest("tr"),
        job=currentRow.find("td:eq(8) button").val();
         

            alertify.confirm('Job <b style="color:red;">'+ job +'</b> Trigger Request','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p>Are you sure you want to trigger the job <b>'+ job +' ?</b></p></div></div></div>', 
      function(){ 

         $.ajax({
          url: jenkins_url + 'job/'+ job +'/build',
          method: 'POST',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {
            $('.overlay').show();
        }
        }).done(function(data) {
            toastr.warning("Your Execution Request has been sent to server.", "Request Sent")
             setTimeout(function(){
            $('#listTable').DataTable().ajax.reload();
            $('#listSuccessTable').DataTable().ajax.reload();
            $('#listFailedTable').DataTable().ajax.reload();
         }, 1000);
            $('.overlay').hide();
          })
    }, 
      function(){ 
        alertify.error('Operation Aborted')
    }
  );
    
});


$("#listTable").on('click','.log',function(){

        // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>',
            jenkins_username = '<?php echo $jenkins_username; ?>',
            jenkins_token = '<?php echo $jenkins_token; ?>',
            jenkins_authorization = '<?php echo $jenkins_authorization; ?>';


         // get the current row Id, job name and instance id
         var currentRow=$(this).closest("tr"),
             name=currentRow.find("td:eq(2)").text(),
             result=currentRow.find("td:eq(1)").text(),
             buildNumber=currentRow.find("td:eq(6)").text(),
             date=currentRow.find("td:eq(4)").text();

         var log = $.ajax({
            contentType: "application/text",
            url: jenkins_url + 'job/'+ name +'/'+ buildNumber +'/consoleText',
            method: 'GET',
            headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            async: false,
            beforeSend: function() {
             $(".overlay").show();
             $(".destroy").remove();

            },
            error: function() {
               toastr.error("Error During query error list data \n Id: " + id, "Query Data Error");
            },

            success: function() {
            },
            complete: function(data) {
                dateRequest = data;
                $(".overlay").hide();
            }

         });

          output = log.responseText;

          if(buildNumber == '' || buildNumber == null){
           output = '<div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p>Your requested job <b>'+ name + '</b> has not been executed yet</p><p>Please, Try again later.</p</div></div></div>';
         }
           
                  $("#addLog").append('<div class="destroy"><table class="table table-bordered"><tbody><tr><th width="10px">Header</th><th>Task</th></tr><tr><td>Execution Date</td><td>'+ date +'</td></tr><tr><td>Job Name</td><td>'+ name +' <b>['+ buildNumber +']</b> </td></tr><tr><td>Status</td><td>'+ result +'</td></tr><tr><td>Console Log</td><td><pre>'+ output +'</pre></td></tr></tbody></table></div>');
              

         $('#modal-default').modal('show');

    });

    
</script>