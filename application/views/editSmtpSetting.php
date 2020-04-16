<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-users"></i> SMTP Setting
        <small>Edit Smtp Setting</small>
      </h1>
    </section>
    
    <section class="content">
        <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-warning" href="<?php echo base_url(); ?>SmtpSettings"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</a>
                </div>
            </div>
        </div>
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
              <!-- general form elements -->
              
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">Edit new smtp setting</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <?php $this->load->helper("form"); ?>
                    <form role="form" id="addNewJobInsert" action="<?php echo base_url() ?>SmtpSettings/UpdateSettings" method="post" role="form">
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-3" style="display: none;">                                
                                    <div class="form-group">
                                        <label for="id">Id</label>
                                        <input type="text" class="form-control required" value="<?php echo $fetch->id; ?>" id="id" name="id" maxlength="128" required autocomplete="off">
                                    </div>
                                    
                                </div>
                                <div class="col-md-3">                                
                                    <div class="form-group">
                                        <label for="name">Name</label>
                                        <input type="text" class="form-control required" value="<?php echo $fetch->name; ?>" id="name" name="name" maxlength="128" required autocomplete="off">
                                    </div>
                                    
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="smtp_host">Smtp Host</label>
                                      <input type="text" class="form-control required" id="smtp_host" value="<?php echo $fetch->smtp_host; ?>" name="smtp_host" maxlength="128" autocomplete="off" required>
                                    </div>
                                </div>

                                <div class ="col-md-3"> 
                                    <div class="form-group">
                                        <label for="smtp_port">Smtp Port</label>
                                        <input type="text" class="form-control required" id="smtp_port" value="<?php echo $fetch->smtp_port; ?>" name="smtp_port" maxlength="128">
                                    </div>
                                </div>
                            </div>


                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" class="form-control required" id="username" value="<?php echo $fetch->username; ?>" name="username" maxlength="128" required autocomplete="off">
                                    </div>
                                </div> 
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="password">Password</label>
                                        <input type="password" class="form-control required" id="password" value="<?php echo $fetch->password; ?>" name="password" maxlength="128" required autocomplete="off">
                                    </div>
                                </div> 

                                <div class="col-md-3">
                                    <div class="form-group" style="margin-top: 25px;">
                                          <div class="checkbox">
                                            <label>
                                              <input type="checkbox" id="ssl" value="1" name="ssl">
                                              Require SSL
                                            </label>
                                          </div>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                 <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" value="<?php echo $fetch->description; ?>" name="description" maxlength="500" rows="5" required><?php echo $fetch->description; ?></textarea>
                                    </div>
                                </div> 
                            </div>
                        </div><!-- /.box-body -->
                            
                        <div class="box-footer">
                            <input type="submit" class="btn btn-success" value="Update" />
                        </div>
                    </form>
                </div>
               
            </div>
            <div class="col-md-6">
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
            name :{ required : true },
            smtp_host :{ required : true },
            smtp_port :{ required : true },
            username :{ required : true },
            password :{ required : true },
        },
        messages:{
            name :{ required : "This field is required" },
            smtp_host :{ required : "This field is required" },    
            smtp_port :{ required : "This field is required" }, 
            username :{ required : "This field is required" },           
            password :{ required : "This field is required" },
        }
    });
});

</script>
