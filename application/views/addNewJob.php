<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-users"></i> Job Management
        <small>Add New Job</small>
      </h1>
    </section>
    
    <section class="content">
        <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-warning" href="<?php echo base_url(); ?>JobsTable">Back</a>
                </div>
            </div>
        </div>
        <div class="row">
            <!-- left column -->
            <div class="col-md-8">
              <!-- general form elements -->
              
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">Enter New Job Component Details</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <?php $this->load->helper("form"); ?>
                    <form role="form" id="addNewJobInsert" action="<?php echo base_url() ?>addNewJobInsert" method="post" role="form">
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-6">                                
                                    <div class="form-group">
                                        <label for="job_name">Job Name</label>
                                        <input type="text" class="form-control required" value="<?php echo set_value('job_name'); ?>" id="job_name" name="job_name" maxlength="128" required autocomplete="off">
                                    </div>
                                    
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="job_component">Job Component Name</label>
                                     <!-- <input type="text" class="form-control required" id="job_component" value="<?php echo set_value('job_component'); ?>" name="job_component" maxlength="128" required>-->

                                        <select class="form-control required" id="job_component" name="job_component" required>
                                            <option value="tFileInputXML">tFileInputXML</option>
                                            <option value="tFileInputExcel">tFileInputExcel</option>
                                            <option value="tFileInputDelimited">tFileInputDelimited</option>
                                            <option value="tFileInputJSON">tFileInputJSON</option>
                                        </select> 

                                    </div>
                                </div>
                            </div>
                            <div class="row">
                              <!--  <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="file">Type</label>
                                        <select class="form-control required" id="file" name="file">
                                            <option value="0"> -- Select the File or Folder --</option>
                                            <option value="1">File</option>
                                            <option value="2">Folder</option>
                                        </select>
                                    </div>
                                </div>  -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="file_path">Repository</label>
                                        <input type="text" class="form-control required" id="file_path" value="<?php echo set_value('file_path'); ?>" name="file_path" maxlength="128" required autocomplete="off">
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
                            <input type="submit" class="btn btn-primary" value="Submit" />
                            <input type="reset" class="btn btn-default" value="Reset" />
                        </div>
                    </form>
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
            file_path :{ required : true },
        },
        messages:{
            job_name :{ required : "This field is required" },
            job_component :{ required : "This field is required" },    
            file_path :{ required : "This field is required" },            
        }
    });
});


</script>