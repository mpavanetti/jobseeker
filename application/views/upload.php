 <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
</script>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/plugins/dropzone/dropzone.css">
<style>
    .dropzone {
        background: white;
        border-radius: 5px;
        border: 2px dashed rgb(0, 135, 247);
        border-image: none;
        max-width: 100%;
        margin-left: auto;
        margin-right: auto;
    }
</style>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/plugins/dropzone/dropzone.js"></script>

<div class="content-wrapper">    
    <section class="content-header">
      <h1>
        File Upload
        <small>Section</small>
      </h1>
    </section>
    <section class="content">
      <div class="container">
        <!-- Info boxes -->
      <div class="row">
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated fadeIn">
            <span class="info-box-icon bg-aqua"><i class="fa fa-server"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Jobs Registered</span>
            <b><span id="jobs-registered" class="info-box-number"></span></b>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated fadeIn">
            <span class="info-box-icon bg-red"><i class="fa fa-upload"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Files Uploaded</span>
              <b><span id="files-uploaded" class="info-box-number"></span></b>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->

        <!-- fix for small devices only -->
        <div class="clearfix visible-sm-block"></div>

        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated fadeIn">
            <span class="info-box-icon bg-green"><i class="ion ion-ios-gear-outline"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Job Components</span>
             <b> <span id="components-registered" class="info-box-number"></span></b>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated fadeIn">
            <span class="info-box-icon bg-yellow"><i class="fa fa-align-left"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Component Types</span>
             <b><span id="componentType-registered" class="info-box-number"></span></b>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
<style>
    .box.box-primary {
        border-top-color: #4A00E0;
    }

    .box.box-secondary {
        border-top-color: #8E2DE2;
    }
</style>
    <div id="loading" class="row" style="margin-top: 20px; margin-bottom: 20px; display: none;">
            <div class="col-md-12 text-center" >
                <img src="<?php echo base_url(); ?>assets/images/gifs/line.gif" alt="robot" width="900" 
                style="border-radius: 25px; 
                  -webkit-box-shadow: 10px 10px 18px -3px rgba(0,0,0,0.33);
                    -moz-box-shadow: 10px 10px 18px -3px rgba(0,0,0,0.33);
                    box-shadow: 10px 10px 18px -3px rgba(0,0,0,0.33); ">
            </div>
    </div>

       <div id="form" class="row" style="margin-top: 30px;">
        <!-- left column -->
        <div class="col-md-6">
          <!-- general form elements -->
          <div class="box box-primary animated fadeIn">
            <div class="box-header with-border">
              <h3 class="box-title">Job Selection</h3>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            <form role="form">
              <div class="box-body">
                <!-- select -->
                <div class="form-group">
                  <label>Available Jobs</label>
                  <select class="form-control" id="job-name" name="job-name" required="required">
                  </select>
                </div>
                <!-- select -->
                <div class="form-group">
                  <label>Available Components</label>
                  <select class="form-control"  id="job-component" name="job-component" required="required">
                  </select>
                </div>
                <!-- select -->
                <div class="form-group">
                  <label>Available Files Type</label>
                  <select class="form-control"  id="job-fileType" name="job-fileType" required="required">
                  </select>
                </div>
                <div class="form-group">
                  <label>Repository</label>
                  <input type="text" class="form-control"  id="job-filePath" name="job-filePath" required="required" autocomplete="off" disabled> 
                </div>

                <div id="file-form">
                </div>

                <div id="file-name-form">
                </div>

                  <div id="file-input">
                </div>
              </div>
              <!-- /.box-body -->
              <div id="box-footer">
              </div>

            </form>
          </div>
          <!-- /.box --> 
          <div id="for-seta">
          </div>
      </div>

      <div id="coluna" class="col-md-6" >
      </div>

        </div><!-- Row -->
        </div>
    </section>
</div>

<script>
    $(document).ready(function(){

      toastr.options = {
        "closeButton": true,
        "debug": false,
        "positionClass": "toast-top-right",
        "newestOnTop": false,
        "timeOut": "10000",
        "progressBar": true}
      toastr.warning("Fill out correctly the form,  Once the file is uploaded it can replace the old one on server.", "Attention")


        $('#submit').click(function(){
            $('#form').hide();
            $('#loading').fadeIn(1500);
            $('#loading').delay(5500).fadeOut(1500);

            setTimeout(function() {
              location.reload();
          }, 8500);
            
        });

        // Populate Job Name Field
        $("#job-name").ready(function() { 
        $('#job-name').empty();
        $('#seta').remove(); 
        $('#drop').remove(); 
        $('.file-form').remove();
        $('.file-name-form').remove();

        $('#job-name').append($('<option>', {
                value: 0,
                text: 'Please, Select an avaiable job name.',
                })); 
                  
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>Upload/listJobsJson",             
        dataType: "html",   //expect html to be returned                
        success: function(data){  
          var json = JSON.parse(data);  
           $.each(json, function(i, item) {
            var newJson = (json[i].job_name);
            $('#job-name').append($('<option>', {
                value: newJson,
                text: newJson
                }))
             })
        }

    });
});

    //Populate component Name
    $("#job-name").change(function(){
    $('#job-component').empty();
    $('#job-fileType').empty();
    $('#job-filePath').val('');
    $('#seta').remove();
    $('#drop').remove();
    $('.file-form').remove();
    $('.file-name-form').remove();


     $('#job-component').append($('<option>', {
                value: 0,
                text: 'Please, Select an avaiable job component name.',
                })); 

    var id = $("#job-name").val();

     if (id != 0) {

      $.ajax({
           type: "GET",
           url: '<?php echo base_url(); ?>Upload/listComponents/'+id,
           dataType: 'html',
            success: function(data){  
              var json = JSON.parse(data);   
               $.each(json, function(i, item) {
                var newJson = (json[i].job_component);
                $('#job-component').append($('<option>', {
                    value: newJson,
                    text: newJson
                    }));

                     })
                }
            
          });
        var component = $("#job-component").val();

      } 
})

     //Populate file type
    $("#job-component").change(function(){
    $('#job-fileType').empty();
    $('#job-filePath').val('');
    $('#drop').remove();
    $('#seta').remove();
    $('.file-form').remove();
    $('.file-name-form').remove();

    $('#job-fileType').append($('<option>', {
                value: 0,
                text: 'Please, Select an avaiable job component type.',
                })); 

    var name = $("#job-name").val();
    var component = $("#job-component").val();

    if (name != 0 && component != 0) {

      $.ajax({
           type: "GET",
           url: '<?php echo base_url(); ?>Upload/listComponentType/'+ name + '/' + component,
           dataType: 'html',
            success: function(data){  
              var json = JSON.parse(data);   
               $.each(json, function(i, item) {
                var newJson = (json[i].component_type);

             //  console.log(newJson);

                $('#job-fileType').append($('<option>', {
                    value: newJson,
                    text: newJson
                    }));

                     })
                }
            
          });

      }

    })

      //Populate file path
    $("#job-fileType").change(function(){
    $('#job-filePath').empty();

    var name = $("#job-name").val();
    var component = $("#job-component").val();
    var type = $("#job-fileType").val();

      $.ajax({
           type: "GET",
           url: '<?php echo base_url(); ?>Upload/listComponentPath/'+ name + '/' + component + '/' + type,
           dataType: 'html',
            success: function(data){  
              var json = JSON.parse(data);   
               $.each(json, function(i, item) {
                var newJson = (json[i].file_path);
                var file = (json[i].file);
                var fileName = (json[i].file_name);
                
                $('#file-form').append($('<div class="form-group file-form" style="display:none;"><label>File Type</label><input type="text" class="form-control"  id="file" name="file" autocomplete="off" disabled></div>'))

                if(fileName != null) {

                  $('#file-name-form').append($('<div class="form-group file-name-form"><label>File Name</label><input type="text" class="form-control"  id="file-name" name="file-name" autocomplete="off" disabled></div>'))
                }


                $('#job-filePath').val(newJson);

                $('#file').val(file);
                
                $('#file-name').val(fileName);

                $('#coluna').append($('<div id="drop" class="box box-secondary animated fadeIn" id="box-dropzone"><div class="box-header with-border"><h3 class="box-title">File Upload</h3></div><div class="box-body" style="padding: 20px;  height: 262px;"><DIV id="dropzone"><form class="dropzone needsclick" id="mydropzone" action="<?php echo base_url(); ?>upload/do_upload" enctype="multipart/form-data" method="post" style="height: 220px;"><DIV class="dz-message needsclick"><img src="<?php echo base_url(); ?>assets/images/bi.png" alt="cloud" style="height: 100px; width: 100px;"><h3><b>Drop files here or click to upload.</b></h3><BR></DIV></form></DIV></div></div><div id="seta" class="text-left animated fadeInLeft" style=" margin-top: 60px; margin-left: 10px; margin-bottom: 30px;"><img src="<?php echo base_url(); ?>assets/images/seta-azul-direita.png" alt="Seta" style="transform: rotate(-40deg);"></div>'));
                
                $("#job-fileType option[value= 0]").remove();
                toastr.success("Right ! Form Completed", "Success")
                toastr.info("Now Click on the box below to upload your file.", "Info")
                    })
                var type = $("#job-fileType").val();

              var fileType = $('#file').val();

              if(fileType == 1){

              var fileName = $('#file-name').val() + type;  

              }

              var kind = $("#job-fileType").val();

              $("#mydropzone").dropzone({
                      maxFiles: 20000,
                      acceptedFiles: type,
                      url: "<?php echo base_url(); ?>upload/do_upload/",

                     accept: function(file, done) {
                    if(fileType == 2){
                      done();
                      toastr.success("Your File <b>"+ file.name +"</b> has been successfully Uploaded", "Success")
                    }

                else if (file.name == fileName && file.name != null) {
                 done();
                 toastr.success("Your File <b>"+ file.name +"</b> has been successfully Uploaded", "Success")
                }
                else { 
                  done('Error ! Invalid File Detected, Please Check the file name, the required file is: ' + fileName );
                  toastr.error("Your file does NOT match which the required file, please try again.<br><b>Required: "+ fileName + "<br> Provided: " + file.name + " </b>", "Error");

                 }
              }
                  });
                }
          });

    })

    var path = $('#job-filePath').val();

    // Count Jobs
     $("#jobs-registered").ready(function() { 
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>Upload/countJobs",             
        dataType: "html",   //expect html to be returned                
        success: function(data){ 
         $('#jobs-registered').append('<b>' + data + '</b>');
        }

    });
});

 // Count components
     $("#components-registered").ready(function() { 
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>Upload/countComponents",             
        dataType: "html",   //expect html to be returned                
        success: function(data){  
         $('#components-registered').append('<b>' + data + '</b>');
        }

    });
});

      // fetch fileuploaded
     $("#files-uploaded").ready(function() { 
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>Upload/countFileUploaded",             
        dataType: "html",   //expect html to be returned                
        success: function(data){  
         $('#files-uploaded').append('<b>' + data + '</b>');
        }

    });
});


 // Count components types registered
     $("#componentType-registered").ready(function() { 
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>Upload/countComponentsTypes",             
        dataType: "html",   //expect html to be returned                
        success: function(data){ 
         $('#componentType-registered').append('<b>' + data + '</b>');
        }

    });
});


    });
</script>

<script type="text/javascript">

   $("#job-fileType").change(function(){
 
});

   Dropzone.autoDiscover = false;

</script>