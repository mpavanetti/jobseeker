 <?php // print_r($jobs) ?>
 <!-- <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse');
  });
</script> -->

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
      </div>
    </div>
  </div> 
<?php } ?>
<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box" class="box collapsed-box">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Loaded Jobs</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <table id="myTable" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>Build Situation</th>
              <th>Job Name</th>
              <th>Url</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
            <th>Build Situation</th>
            <th>Job Name</th>
            <th>Url</th>
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
    <div id="box2" class="box collapsed-box">
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
              <th>Job Name</th>
              <th>Last Build Number</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
            <th>Build Situation</th>
            <th>Job Name</th>
            <th>Last Build Number</th>
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

        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

        toastr.info('Fetching data from server...', 'Query Data');
        $(".overlay").show();
        $("#myTable").dataTable().fnDestroy();
        $('#myTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000,2000,5000],
          "pageLength": 5,
          "order": [[ 0, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "color"},
          {"data": "name"},
          {"data": "url"}
          ],
           fixedColumns: true
       });
       $('#box').boxWidget('expand'); 

        $("#listTable").dataTable().fnDestroy();
        $('#listTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000],
          "pageLength": 5,
          "order": [[ 0, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,lastFailedBuild[displayName,result,timestamp],color,builds[number]{0,1}]',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "color"},
          {"data": "name"},
          {"data": "builds[].number"}

          ]
       });
      $('#box2').boxWidget('expand');

        $("#listFailedTable").dataTable().fnDestroy();
        $('#listFailedTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000],
          "pageLength": 5,
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
            if(data != null){return data} else {return ''}
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
        $('#box3').boxWidget('expand');

        $("#listSuccessTable").dataTable().fnDestroy();
        $('#listSuccessTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000],
          "pageLength": 5,
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
            if(data != null){return data} else {return ''}
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
  })
    
</script>