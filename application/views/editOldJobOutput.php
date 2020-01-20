<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-users"></i> Job Management
        <small>Edit Output Component</small>
      </h1>
    </section>
    
    <section class="content">
        <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-warning" href="<?php echo base_url(); ?>JobsTable/outputTable"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</a>
                </div>
            </div>
        </div>
        <div class="row">
            <!-- left column -->
            <div class="col-md-8">
              <!-- general form elements -->
              
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">Enter the Output component details</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <?php $this->load->helper("form"); ?>
                    <form role="form" id="addNewJobEdit" action="<?php echo base_url() ?>JobsTable/editJobOutput" method="post" role="form">
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-6">                                
                                    <div class="form-group">
                                        <label for="job_name">Job Name</label>
                                        <input type="text" class="form-control required" value="<?php echo $job->job_name ?>" id="job_name" name="job_name" maxlength="128" required autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="job_component">Job Component Name</label>
                                      <input type="text" class="form-control required" id="job_component" value="<?php echo $job->job_component ?>" name="job_component" maxlength="128" autocomplete="off" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                              <div class ="col-md-6"> 
                                <div class="form-group">
                                    <label for="file_name">File Name</label>
                                    <input type="text" class="form-control required" id="file_name" value="<?php echo $job->file_name ?>" name="file_name" maxlength="128" required>
                                </div>
                            </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="file_path">Repository</label>
                                        <input type="text" class="form-control required" id="file_path" value="<?php echo $job->file_path ?>" name="file_path" maxlength="128" required autocomplete="off">
                                    </div>
                                </div> 
                                <div class="col-md-6 filename">
                                </div> 
                                <div class="col-md-6" style="display: none;">                                
                                    <div class="form-group">
                                        <label for="job_id">Id</label>
                                        <input type="text" class="form-control required" value="<?php echo $job->id ?>" id="job_id" name="job_id" maxlength="128" required>
                                    </div>
                                </div>
                              <!--  <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="file_name">File Name</label>
                                        <input type="text" class="form-control required email" id="email" value="<?php echo set_value('email'); ?>" name="email" maxlength="128">
                                    </div>
                                </div>  -->
                            </div>
                        </div><!-- /.box-body -->
    
                        <div class="box-footer">
                            <input type="submit" class="btn btn-primary" value="Update" />
                        </div>
                    </form>
                </div>
                <div class="alert alert-info alert-dismissible" style="margin-top: 20px;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h4><i class="icon fa fa-info"></i> Instructions</h4>
                        <p>
                            <b>For Job Name Input:</b>
                            <ul>
                                <li>Write the Talend Job Name.</li>
                            </ul><br>
                            <b>For Job Component Name, Select the component name in the Talend Job, like:</b>
                            <ul>
                                <li>tFileOutputExcel_1</li>
                                <li>tFileOutputDelimited_1</li>
                                <li>tFileOutputJSON_1</li>
                                <li>tFileOutputXML_1</li>
                            </ul>
                            Obs: If needed just change the number after the underscore _ according to the Talend Job Component Name.<br><br>
                            <b>For File Name:</b>
                            <ul>
                            <li>Choose the name according to the file name you want to upload.</li>
                           <!--  <li>Example: ExcelFile.xlsx, XmlFile.xml, JsonFile.json, DelimitedFile.csv</li>
                            <li>You can choose the file name, just remember to write the file extension also.</li> -->
                            <br>
                            </ul>
                            <b>For repository Name:</b>
                            <ul>
                            <li>Just choose a custom name to be your file repository.</li>
                            <li>*Choose the exactly the name from the file you are uploading.</li>
                            <li>*Don't user special characters and spaces.</li>
                            </ul>
                            
                        </p>
                  </div>
            </div>
            <div class="col-md-4">
                <?php 
                    $this->load->helper('form');
                    $error = $this->session->flashdata('error');
                    if($error)
                    {
                ?>
                <div class="alert alert-danger alert-dismissable">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <?php echo $this->session->flashdata('error'); ?>                    
                </div>
                <?php } ?>
                <?php  
                    $success = $this->session->flashdata('success');
                    if($success)
                    {
                ?>
                <div class="alert alert-success alert-dismissable">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <?php echo $this->session->flashdata('success'); ?>
                </div>
                <?php } ?>
                
                <div class="row">
                    <div class="col-md-12">
                        <?php echo validation_errors('<div class="alert alert-danger alert-dismissable">', ' <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button></div>'); ?>
                    </div>
                </div>
            </div>
        </div>    
    </section>
    
</div>
<script type="text/javascript">

    $(document).ready(function(){
    
    var addUserForm = $("#addNewJobInsert");
    
    var validator = addUserForm.validate({
        
        rules:{
            job_name :{ required : true },
            job_component :{ required : true },
            file_name :{ required : true },
            file_path :{ required : true },
        },
        messages:{
            job_name :{ required : "This field is required" },
            job_component :{ required : "This field is required" }, 
            file_name :{ required : "This field is required" }, 
            file_path :{ required : "This field is required" },            
        }
    });
});


</script>

<script>
  $( function() {
    var availableTags = [
      "tFileOutputExcel_",
      "tFileOutputDelimited_",
      "tFileOutputJSON_",
      "tFileOutputXML_",
    ];
    $( "#job_component" ).autocomplete({
      source: availableTags
    });
  } );


</script>