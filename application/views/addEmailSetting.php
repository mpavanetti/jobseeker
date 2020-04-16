<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-users"></i> Email Setup Management
        <small>Add New custom email template.</small>
      </h1>
    </section>
    
    <section class="content">
        <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-warning" href="<?php echo base_url(); ?>EmailSettings"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</a>
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
                        <h3 class="box-title"><b>Fill in the form according to your email needs</b></h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <?php $this->load->helper("form"); ?>
                    <form role="form" id="InsertDbSettings" action="<?php echo base_url() ?>EmailSettings/InsertDbSettings" method="post" role="form">
                        <div class="box-body">
                            <h4>Email Info:</h4><br>
                            <div class="row">

                                <div class="col-md-3">                                
                                    <div class="form-group">
                                        <label for="name">Email Name</label>
                                        <input type="text" class="form-control required" value="<?php echo set_value('name'); ?>" id="name" name="name" maxlength="50" required autocomplete="off">
                                    </div>
                                </div>

                                <div class="col-md-3">
                                     <div class="form-group">
                                        <label for="smtp">SMTP Provider</label>
                                        <select class="form-control required" id="smtp" name="smtp" required>
                                        </select>
                                    </div>
                                </div>

                            </div>
                            <hr>
                             <h4>Email Header:</h4><br>
                            <div class="row">   

                                  <div class="col-md-3">

                                    <div class="form-group">
                                        <label for="to">To</label>
                                      <input type="email" class="form-control required" id="to" value="<?php echo set_value('to'); ?>" name="to" maxlength="200" autocomplete="on" required>
                                    </div>
                                </div>

                                 <div class="col-md-3">

                                    <div class="form-group">
                                        <label for="from">From</label>
                                      <input type="email" class="form-control" id="from" value="<?php echo set_value('from'); ?>" name="from" maxlength="200" autocomplete="off" required>
                                    </div>
                                </div>


                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="cc">Cc</label>
                                        <input type="cc" class="form-control" id="cc" value="<?php echo set_value('cc'); ?>" name="cc" maxlength="200" autocomplete="off">
                                    </div>
                                </div> 

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="subject">Subject</label>
                                        <input type="text" class="form-control" id="subject" value="<?php echo set_value('subject'); ?>" name="subject" maxlength="200" autocomplete="off" required>
                                    </div>
                                </div> 

                            </div>
                             

                            <hr>
                            <h4>Email Message:</h4><br>
                            <div class="row">
                                 <div class="col-lg-12 col-md-12 col-xs-12">
                                    <div class="form-group">
                                        <textarea name="msg" id="msg" rows="1000" cols="80"></textarea>
                                    </div>
                                </div> 
                                
                            </div>

                             <hr>
                            <h4>Email Description:</h4><br>
                            <div class="row">
                                 <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" value="<?php echo set_value('description'); ?>" name="description" maxlength="500" rows="5" required></textarea>
                                    </div>
                                </div> 
                                
                            </div>
                             <hr>
                            <h4>Enable / Disable:</h4><br>
                            <div class="row">
                                 <div class="col-md-3">
                                    <div class="form-group">
                                          <div class="checkbox">
                                            <label>
                                              <input type="checkbox" id="enabled" value="1" name="enabled">
                                              Enabled
                                            </label>
                                          </div>
                                    </div>
                                </div> 
                            </div>
                        </div><!-- /.box-body -->
                            
                        <div class="box-footer">
                            <input type="submit" class="btn btn-primary" value="Submit" />
                        </div>
                    </form>
                </div>
            </div>
        </div>    
    </section>
    
</div>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/ckeditor/ckeditor.js"></script>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/ckeditor/config.js"></script>

<script type="text/javascript">

    $(document).ready(function(){
    
    var addUserForm = $("#InsertDbSettings");
    
    var validator = addUserForm.validate({
        
        rules:{
            name :{ required : true },
            smtp :{ required : true },
            to :{ required : true },
            from :{ required : true },
            subject :{ required : true },
            msg :{ required : true },
            description :{ required : true },
        },
        messages:{
            name :{ required : "This field is required" },
            smtp :{ required : "This field is required" },
            to :{ required : "This field is required" },    
            from :{ required : "This field is required" },   
            subject :{ required : "This field is required" },    
            msg :{ required : "This field is required" },    
            description :{ required : "This field is required" },            
        }
    });

    $.ajax({
           type: "GET",
           url: '<?php echo base_url(); ?>EmailSettings/fetchSMTP/',
           dataType: 'html',
            success: function(data){  
              var json = JSON.parse(data); 
               $.each(json, function(i, item) {
                var newJson = (json[i].name);
                $('#smtp').append($('<option>', {
                    value: newJson,
                    text: newJson
                    }));

                     })
                }
            
          });
});


</script>

 <script>
    // Replace the <textarea id="editor1"> with a CKEditor
    // instance, using default configuration.
    
     CKEDITOR.replace('msg', {
      language: 'en'
    });
    CKEDITOR.editorConfig = function( config ) {
    config.language = 'en';
    config.uiColor = '#F7B42C';
    config.height = 300;
    config.toolbarCanCollapse = true;
};
</script>


