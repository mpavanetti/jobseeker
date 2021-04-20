<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-users"></i> General Settings Management
        <small>Add New Setting for variable subsitution / replacement.</small>
      </h1>
    </section>
    
    <section class="content">
        <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-warning" href="<?php echo base_url(); ?>GenericSettings"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</a>
                </div>
            </div>
        </div>
        <div id="components-registered"></div>
        <div class="row">
             <div class="col-md-12">
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
            <!-- left column -->
            <div class="col-md-12">
              <!-- general form elements -->
              
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title"><b>Enter New Setting</b></h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <?php $this->load->helper("form"); ?>
                    <form role="form" id="InsertGenericSettings" action="<?php echo base_url() ?>InsertGenericSettings" method="post" role="form">
                        <div class="box-body">
                            <h4>Setting Info:</h4><br>
                            <div class="row">

                                <div class="col-md-3">                                
                                    <div class="form-group">
                                        <label for="job_name">Job Name</label>
                                        <input type="text" class="form-control required" value="<?php echo set_value('job_name'); ?>" id="job_name" name="job_name" maxlength="255" required autocomplete="off">
                                    </div>
                                </div>

                                <div class="col-md-3">

                                    <div class="form-group">
                                        <label for="setting_name">Setting Name</label>
                                      <input type="text" class="form-control required" id="setting" value="<?php echo set_value('setting'); ?>" name="setting" maxlength="255" autocomplete="off" required>
                                    </div>
                                </div>

                            </div>
                            <hr>
                             <h4>Values for Variable:</h4><br>
                            <div class="row">   

                                  <div class="col-md-3">

                                    <div class="form-group">
                                        <label for="value1">Value 1</label>
                                      <input type="text" class="form-control required" id="value1" value="<?php echo set_value('value1'); ?>" name="value1" maxlength="3000" autocomplete="off" required>
                                    </div>
                                </div>

                                 <div class="col-md-3">

                                    <div class="form-group">
                                        <label for="value2">Value 2</label>
                                      <input type="text" class="form-control" id="value2" value="<?php echo set_value('value2'); ?>" name="value2" maxlength="3000" autocomplete="off">
                                    </div>
                                </div>


                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="value3">Value 3</label>
                                        <input type="text" class="form-control" id="value3" value="<?php echo set_value('value3'); ?>" name="value3" maxlength="3000" autocomplete="off">
                                    </div>
                                </div> 

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="value4">Value 4</label>
                                        <input type="text" class="form-control" id="value4" value="<?php echo set_value('value4'); ?>" name="value4" maxlength="3000" autocomplete="off">
                                    </div>
                                </div> 

                            </div>
                              <div class="row">
                                    <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="value5">Value 5</label>
                                        <input type="text" class="form-control" id="value5" value="<?php echo set_value('value5'); ?>" name="value5" maxlength="3000" autocomplete="off">
                                    </div>
                                </div> 
                                </div>
                            <hr>
                            <h4>Setting Description:</h4><br>
                            <div class="row">

                                 <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" value="<?php echo set_value('description'); ?>" name="description" maxlength="5000" rows="5"></textarea>
                                    </div>
                                </div> 
                                
                            </div>
                        </div><!-- /.box-body -->
                            
                        <div class="box-footer">
                            <input type="submit" class="btn btn-primary" value="Submit" />
                        </div>
                    </form>
                </div>
                <div class="alert alert-info alert-dismissible" style="margin-top: 20px;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <h4><i class="icon fa fa-info"></i> Instructions</h4>
                        <p>
                            <b>For Job Name Input:</b>
                            <ul>
                                <li>Write the Job Name.</li>
                            </ul><br>
                            <b>Setting Name</b>
                            <ul>
                                <li>Type a custom Setting name to be called on Data Integrator.</li>
                            </ul><br>
                            <b>Values:</b>
                            <ul>
                            <li>Type at the least one value up to 5 values to asign to the setting (variable).</li>
                            <br>
                            </ul>
                            <b>Description:</b>
                            <ul>
                            <li>Type a custom description to leave a short information about the setting and what it will be used for.</li>
                            </ul>
                            
                        </p>
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
            setting_name :{ required : true },
            value1 :{ required : true },
            description :{ required : true },
        },
        messages:{
            job_name :{ required : "This field is required" },
            setting_name :{ required : "This field is required" },    
            value1 :{ required : "This field is required" },    
            description :{ required : "This field is required" },            
        }
    });
});


</script>

<script>
/*
    // Count components
     $("#job_name").ready(function() { 
      
      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>JobsTable/listJobsName",             
        dataType: "html",   //expect html to be returned                
        success: function(data){  
            var test = JSON.parse(data);
            $.each(test["data"], function(index, val) {
                console.log(val)
            $('#components-registered').append('<b>' + val + '</b>');

             $( function() {
                var availableTags = val.job_name
                $( "#job_name" ).autocomplete({
                  source: availableTags
                });
              } );
        });

         



        }

    });
});

Future Release , Not working*/

 

</script>