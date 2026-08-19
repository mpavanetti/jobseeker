<!-- <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse');
  });
</script>-->
<style>

pre { 
    white-space: pre-wrap; 
    word-break: break-word;
    max-width: 750px;
}

.checkbox {

    transform: scale(1.5);
  }

</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1>
     <b>Full Job Build List</b>
     <small>Quick access to your jobs build logs.</small>
   </h1>
   <ol class="breadcrumb">
    <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
    <li><a href="#">Job Management</a></li>
    <li class="active">Job List</li>
  </ol>
</section>

<!-- Main content -->
<section class="content">
<div class="container">
  <div class="row animated fadeIn" style="margin-top: 25px;">
   <form action="<?php echo base_url() ?>Tmf/fetchData" method="POST" id="searchList">

    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
      <div class="input-group" style="width: 100%;">
        <label>Job Name</label>
        <select class="form-control" name="job_name" id="job_name">
        </select>
      </div>
    </div>

    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
      <div class="form-group">
        <label for="timeoutMinutes">Min rows to fetch</label>
        <input type="number" class="form-control" id="minRows" name="minRows"maxlength="50" autocomplete="off">
      </div>
    </div>

    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
      <div class="form-group">
        <label for="timeoutMinutes">Max rows to fetch</label>
        <input type="number" class="form-control" id="maxRows" name="maxRows" maxlength="50" autocomplete="off">
      </div>
    </div>
    <div style="padding-top: 25px;">
      <div class="col-lg-1 col-md-1 col-sm-6 col-xs-6 form-group">
        <button id="search" type="button" class="btn btn-md btn-primary btn-block searchList pull-right"><i class="fa fa-search" aria-hidden="true"></i></button> 
      </div>
      <div class="col-lg-1 col-md-1 col-sm-6 col-xs-6 form-group">
        <button id="reload" type="button" class="btn btn-md btn-default btn-block pull-right resetFilters"><i class="fa fa-refresh" aria-hidden="true"></i></button>
      </div>
    </div> 
  </div>
</form>


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
    <div id="box" class="box box box-primary collapsed-box">
      <div class="overlay" style="display:none;">
        <i class="fa fa-refresh fa-spin"></i>
      </div>
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Available Jobs</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <table id="fetch" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>Job Name</th>
              <th>Result</th>
              <th>Build Number</th>
              <th>Execution Date</th>
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
            <th>Build Number</th>
            <th>Execution Date</th>
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
</div>
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

  function buildsFromJenkinsResponse(json) {
    return json && Array.isArray(json.builds) ? json.builds : [];
  }

  function destroyDataTable(selector) {
    if ($.fn.DataTable.isDataTable(selector)) {
      $(selector).DataTable().clear().destroy();
    }
  }

  function reloadFetchTable() {
    if ($.fn.DataTable.isDataTable('#fetch')) {
      $('#fetch').DataTable().ajax.reload(null, false);
    }
  }

  function renderBuildTime(data) {
    return data != null && data !== '' ? moment(parseInt(data, 10)).format('MMMM Do YYYY, h:mm:ss a') : '';
  }

  function renderDuration(data) {
    return data != null && data !== '' ? moment(parseInt(data, 10)).utc().format('HH [Hours, ] mm [Minutes, ] ss [Seconds, ] SSS [Miliseconds.]') : '';
  }

  function renderText(data) {
    return data == null ? '' : data;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function(character) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
      }[character];
    });
  }

  $(document).ready(function(){

        // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>',
          jenkins_username = '',
          jenkins_token = '',
            jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

        $('#reload').click(function(){
          $('.overlay').show();
          reloadFetchTable();
          toastr.info('Refreshing Table rows...','Refreshing ')
          $('.overlay').hide();
        });

        $.ajax({
          url: jenkins_url + 'api/json?tree=jobs[name,builds[number,actions[parameters[name,value]]]]&pretty=true',
          method: 'GET',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {

            $('.overlay').show();
          }
        }).done(function(data) {

         $.each(data["jobs"], function (key, item) {
               // console.log(item.name);
               newJson = item.name;

               $('#job_name').append($('<option>', {
                value:  newJson,
                text: newJson
              }))
             });

         $('.overlay').hide();

       }).fail(function() {
        console.error(arguments);
      });


       $('#search').click(function(){

        var job_name = $('#job_name').val(),
        minRows = $('#minRows').val(),
        maxRows = $('#maxRows').val();

        if(minRows == '' || minRows == null) {
          minRows = 0;
        }

        if(maxRows == '' || maxRows == null){
          maxRows = 999999;
        }     

        if(minRows < maxRows){

          if(job_name != '' && job_name != null){
            $('.overlay').show();
            destroyDataTable('#fetch');
            $('#fetch').DataTable({
              "lengthMenu": [3,5,10,13,20,100,200,500,1000],
              "pageLength": 5,
              "order": [[ 2, "desc" ]],
              "ajax": {
                "url": jenkins_url +'job/'+ encodeURIComponent(job_name) +'/api/json?tree=builds[number,number,fullDisplayName,result,timestamp,duration,url,queueId,building]{'+ minRows +','+maxRows+'}',
                "type": 'GET',
                "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
                "dataSrc": buildsFromJenkinsResponse,
                "bDestroy": true
              },
              "columns": [
              {"data": "fullDisplayName", "defaultContent": ""},
              {"data": "result", "defaultContent": ""},
              {"data": "number", "defaultContent": ""},
              {"data": "timestamp", "defaultContent": ""},
              {"data": "duration", "defaultContent": ""},
              {"data": "url", "defaultContent": ""},
              {"data": "queueId", "defaultContent": ""},
              {"data": "building", "defaultContent": ""}

              ],
              columnDefs:[{targets:0, render:function(data){
                return renderText(data);
              }},{targets:1, render:function(data){
                if(data != null){if(data == 'SUCCESS') { return '<b style="color: green;">' + data + '</b>'} else {return '<b style="color: red;">' + data + '</b>'}} else {return ''}
              }},{targets:2, render:function(data){
                if(data != null){return '<a class="btn btn-sm btn-info log text-center" href="#" style="margin-left: 20px;" title="Click to check the build console output.">'+ data + '</a>'} else {return ''}
              }},{targets:3, render:function(data){
                return renderBuildTime(data);
              }},{targets:4, render:function(data){
                return renderDuration(data);
              }},{targets:5, render:function(data){
                return renderText(data);
              }},{targets:6, render:function(data){
                return renderText(data);
              }},{targets:7, render:function(data){
                return renderText(data);
              }}]
          });

            $('#box').boxWidget('expand');
            reloadFetchTable();
            $('.overlay').hide();

          } else {
            toastr.error('Please, select a job name to fetch.', 'Job Name Empty');
          }

        } else {
          toastr.error('The min rows must be less than max rows !', 'Rows Error');
        }    

      });

     });

$("#fetch").on('click','.log',function(){

        // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>',
          jenkins_username = '',
          jenkins_token = '',
            jenkins_authorization = '<?php echo $jenkins_authorization; ?>';


         // get the current row Id, job name and instance id
         var currentRow=$(this).closest("tr"),
             job_name=currentRow.find("td:eq(0)").text(),
             result=currentRow.find("td:eq(1)").text(),
             build=currentRow.find("td:eq(2)").text(),
             date=currentRow.find("td:eq(3)").text(),
             //buildNumber = build.substring(1),
             name = job_name.split("#");

        $.ajax({
            contentType: "application/text",
          url: jenkins_url + 'job/'+ encodeURIComponent(name[0].trim()) +'/'+ encodeURIComponent(build) +'/consoleText',
            method: 'GET',
            headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            beforeSend: function() {
             $(".overlay").show();
             $(".destroy").remove();

            },
            error: function() {
            toastr.error("Error during console log query.", "Query Data Error");
            },
          success: function(output) {
              $("#addLog").append('<div class="destroy"><table class="table table-bordered"><tbody><tr><th width="10px">Header</th><th>Task</th></tr><tr><td>Execution Date</td><td>'+ escapeHtml(date) +'</td></tr><tr><td>Job Name</td><td>'+ escapeHtml(job_name) +'</td></tr><tr><td>Status</td><td>'+ escapeHtml(result) +'</td></tr><tr><td>Console Log</td><td><pre>'+ escapeHtml(output) +'</pre></td></tr></tbody></table></div>');
              $('#modal-default').modal('show');
            },
            complete: function(data) {
                dateRequest = data;
                $(".overlay").hide();
            }

         });

    });


   </script>