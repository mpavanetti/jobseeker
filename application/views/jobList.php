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
      </div>
    </div>
  </div> 
<?php } ?>
<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box" class="box box">
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
    <div id="box2" class="box box">
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
    <div id="box3" class="box box-danger">
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
    <div id="box4" class="box box-success">
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
  $(document).ready(function(){
    $('#box').boxWidget('collapse');
    $('#box2').boxWidget('collapse');
    $('#box3').boxWidget('collapse');
    $('#box4').boxWidget('collapse');
  });
</script>

<script type="text/javascript">
  
  $(document).ready(function(){

        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';


    $.ajax({
          url: jenkins_url + 'api/json?tree=jobs[name,lastStableBuild[displayName,result,timestamp,duration,url,queueId,building]{0,1}]&pretty=true',
          method: 'GET',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {
           
            $('.overlay').show();
            $('#overlay2').show();
        }
        }).done(function(data) {

           $.each(data["jobs"], function (key, item) {
               // console.log(item.name);
                newJson = item.lastStableBuild.url;
                
                console.log(newJson);
                
            });

           $('.overlay').hide();

        }).fail(function() {
          console.error(arguments);
        });

  });
</script>