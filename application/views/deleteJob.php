<style type="text/css">
   .checkbox input {

    transform: scale(1.5);
}

</style>
<div class="content-wrapper">    
    <section class="content-header">
      <h1>
        Delete Job Panel
        <small>This is a area to delete jobs files and repositories.</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Job Management</a></li>
        <li class="active">Delete Job</li>
    </ol>
</section>
<section class="content">
    <div class="row" style="margin-top: 15px;">
       <div class="col-lg-6 col-md-6 col-xs-12">
         <div class="box box-primary">
            <div class="overlay" style="display:none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
            <div class="box-header">
                <b>Delete Job</b>
            </div>
            <form role="form" id="delJob">
             <div class="box-body">
                 <div class="col-lg-6 col-md-6 col-xs-12">
                     <div class="form-group">
                        <label for="job_name">Delete Below Job</label>
                        <select class="form-control selector" id="deleteJobSelect">
                            <option value="0">Select a Job to delete.</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6 col-xs-12">
                 <div class="form-group">
                    <label for="job_name">Delete Job Repository</label>
                    <div class="checkbox">
                        <label for="deleteRepository">
                          <input type="checkbox" name="deleteRepoCheck" id="deleteRepoCheck" value="1"> Delete asigned job repository.
                      </label>
                  </div>
              </div>
          </div>
      </div>
      <div class="box-footer">
         <a id="deleteJob" href="#" class="btn btn-primary"><i class="fa fa-save"></i>  Delete Job</a>
     </div>
 </form> 
</div>
</div>
<div class="col-lg-6 col-md-6 col-xs-12">
 <div class="box box-primary">
    <div class="overlay" style="display:none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
    <div class="box-header">
        <b>Delete Job Repository</b>
    </div>
    <form role="form" id="delRepository">
     <div class="box-body">
         <div class="col-lg-12 col-md-12 col-xs-12">
             <div class="form-group">
                <label for="job_name">Delete only job repository and files.</label>
                <select class="form-control selector" id="deleteRepoSelect">
                    <option value="0">Select a job repository to delete.</option>
                </select>
            </div>
        </div>
</div>
<div class="box-footer">
 <a id="delRepoBtn" href="#" class="btn btn-primary"><i class="fa fa-save"></i>  Delete Repository</a>
</div>
</form> 
</div>
</div>
</div>
</section>
</div>

<script type="text/javascript">

$(document).ready(function(){

    $("#deleteRepoCheck"). prop("checked", true);

      // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

    $.ajax({
          url: jenkins_url + 'api/json?tree=jobs[name,builds[number,actions[parameters[name,value]]]]&pretty=true',
          method: 'GET',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {
           
            $('.overlay').show();
        }
        }).done(function(data) {

           $.each(data["jobs"], function (key, item) {
                newJson = item.name;
                $('.selector').append($('<option>', {
                value: newJson,
                text: newJson
                }))
            });

           $('.overlay').hide();

        }).fail(function() {
          console.error(arguments);
        });

});

$('#deleteJob').click(function(){
    // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

    var job = $('#deleteJobSelect').val();

    if(job != 0 ){

         alertify.confirm('Delete Job Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this job permanently ?</b></p></div></div></div>', 
      function(){ 
       
         $.ajax({
          url: jenkins_url + 'job/'+ job + '/doDelete',
          method: 'POST',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {
           
            $('.overlay').show();
        },
        success: function() {

             toastr.success('Your job has ben successfully deleted !', 'Job Successfully Deleted');
           $('.overlay').hide();
           $('#deleteJobSelect').find('[value="'+job+'"]').remove();

        },
        error: function(){
             console.error(arguments);
             toastr.error('Some Error has occured during delete job', 'Error to delete Job');
             $('.overlay').hide();
        }
        });

          if($('#deleteRepoCheck').is(":checked")){
            console.log("Delete Repo Selected")

            $.ajax({
          url: '<?php echo base_url(); ?>DeleteJob/deleteRepository/' + job ,
          method: 'POST',
          beforeSend: function() {
           
            $('.overlay').show();
        },
        success: function(data) {
             if(data != '' && data != value) {
                var value = JSON.parse(data);
                if (value.exist == true) {
                toastr.info('It has found ' + value.system + ' files inside the job repository', 'Repository Found');
                toastr.success('Your Repository and files has been succesfully Deleted.', 'Repository Successfully Deleted');
                }
             } else {
                toastr.warning('It has not found any available repository to delete.', 'No Repository found');
             }

           $('.overlay').hide();

        },
        error: function(){
             console.error(arguments);
             toastr.error('Some Error has occured during deleting the job repository', 'Error to delete Job Repository');
             $('.overlay').hide();
        }
        });

         }
    }, 
      function(){ 
        alertify.error('Operation Aborted, good choice.')
    }
  );

    } else {
        toastr.error('Please, Select a job to be deleted', 'Select a Job');
    }
   

});

$('#delRepoBtn').click(function(){
    // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

    var job = $('#deleteRepoSelect').val();

    if(job != 0 ){


         $.ajax({
          url: '<?php echo base_url(); ?>DeleteJob/deleteRepository/' + job ,
          method: 'POST',
          beforeSend: function() {
           
            $('.overlay').show();
        },
        success: function(data) {
             if(data != '' && data != value) {
                var value = JSON.parse(data);
                if (value.exist == true) {
                toastr.info('It has found ' + value.system + ' files inside the job repository', 'Repository Found');
                toastr.success('Your Repository and files has been succesfully Deleted.', 'Repository Successfully Deleted');
                }
             } else {
                toastr.warning('It has not found any available repository to delete.', 'No Repository found');
             }

           $('.overlay').hide();

        },
        error: function(){
             console.error(arguments);
             toastr.error('Some Error has occured during deleting the job repository', 'Error to delete Job Repository');
             $('.overlay').hide();
        }
        });



    }

});

</script>