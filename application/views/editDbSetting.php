<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-users"></i> Database Settings Management
        <small>Add New Value for database call.</small>
      </h1>
    </section>
    
    <section class="content">
        <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-warning" href="<?php echo base_url(); ?>dbSettings"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</a>
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
                        <h3 class="box-title"><b>Type New Database Setting</b></h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <?php $this->load->helper("form"); ?>
                    <form role="form" id="InsertDbSettings" action="<?php echo base_url() ?>dbSettings/UpdateDbSettings" method="post" role="form">
                        <div class="box-body">
                            <h4>Database Info:</h4><br>
                            <div class="row">

                            	<div class="col-md-3" style="display: none;">                                
                                    <div class="form-group">
                                        <label for="job_name">Id</label>
                                        <input type="text" class="form-control required" value="<?php echo $fetch->id ?>" id="id" name="id" maxlength="20" required autocomplete="off">
                                    </div>
                                </div>

                                <div class="col-md-3">                                
                                    <div class="form-group">
                                        <label for="job_name">Job Name</label>
                                        <input type="text" class="form-control required" value="<?php echo $fetch->job_name ?>" id="job_name" name="job_name" maxlength="20" required autocomplete="off">
                                    </div>
                                </div>

                                <div class="col-md-3">
                                     <div class="form-group">
                                        <label for="file">Database Type</label>
                                        <select class="form-control required" id="db_type" name="db_type" required>
                                            <option value="general">General</option>
                                            <option value="oracle_sid">Oracle with SID</option>
                                            <option value="oracle_ServiceName">Oracle with Service Name</option>
                                        </select>
                                    </div>
                                </div>

                            </div>
                            <hr>
                             <h4>Database Credentials:</h4><br>
                            <div class="row">   

                                  <div class="col-md-3">

                                    <div class="form-group">
                                        <label for="value1">login</label>
                                      <input type="text" class="form-control required" id="login" value="<?php echo $fetch->login ?>" name="login" maxlength="200" autocomplete="off" required>
                                    </div>
                                </div>

                                 <div class="col-md-3">

                                    <div class="form-group">
                                        <label for="value2">Password</label>
                                      <input type="password" class="form-control" id="password" value="<?php echo $fetch->password ?>" name="password" maxlength="200" autocomplete="off" required>
                                    </div>
                                </div>


                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="value3">Address</label>
                                        <input type="address" class="form-control" id="address" value="<?php echo $fetch->address ?>" name="address" maxlength="200" autocomplete="off" required>
                                    </div>
                                </div> 

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="value4">Port</label>
                                        <input type="text" class="form-control" id="port" value="<?php echo $fetch->port ?>" name="port" maxlength="200" autocomplete="off" required>
                                    </div>
                                </div> 

                            </div>
                              <div class="row">
                                 <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="value5">Schema</label>
                                        <input type="text" class="form-control" id="schema" value="<?php echo $fetch->schema ?>" name="schema" maxlength="200" autocomplete="off" required>
                                    </div>
                                </div> 
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="value5">Additional Parameters</label>
                                        <input type="text" class="form-control" id="additional_parameters" value="<?php echo $fetch->additional_parameters ?>" name="additional_parameters" maxlength="200" autocomplete="off">
                                    </div>
                                </div> 
                                </div>
                            
                            <div id="oracle_ServiceName">
                            </div>

                            <div id="oracle_sid">
                            </div>


                            <hr>
                            <h4>Setting Description:</h4><br>
                            <div class="row">
                                 <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" id="description" value="<?php echo $fetch->description ?>" name="description" maxlength="500" rows="5" required><?php echo $fetch->description ?></textarea>
                                    </div>
                                </div> 
                                
                            </div>
                        </div><!-- /.box-body -->
                            
                        <div class="box-footer">
                            <input type="submit" class="btn btn-success" value="Update" />
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
                            <b>Database Type</b>
                            <ul>
                                <li>Select your Database, use General for (MySQL, MariaDB, Postgres, Sql Server) or for Oracle use (Oracle with Service name, Oracle with SID)</li>
                            </ul><br>
                            <b>Login and Password:</b>
                            <ul>
                            <li>Type your database instance login and password</li>
                            </ul><br>
                            <b>Address and Port:</b>
                            <ul>
                            <li>Type your database IP or DNS address and the database Port</li>
                            </ul><br>
                            <b>Schema:</b>
                            <ul>
                            <li>Type your database Schema</li>
                            </ul><br>
                            <b>Additional Parameters:</b>
                            <ul>
                            <li>Additional Parameters if any database needs.</li>
                            </ul><br>
                            <b>Description:</b>
                            <ul>
                            <li>Type a custom description to leave a brief information about the database and what it will be used for.</li>
                            </ul>
                            
                        </p>
                  </div>
            </div>
        </div>    
    </section>
    
</div>
<script type="text/javascript">

    $(document).ready(function(){
    
    var addUserForm = $("#InsertDbSettings");
    
    var validator = addUserForm.validate({
        
        rules:{
            job_name :{ required : true },
            login :{ required : true },
            password :{ required : true },
            address :{ required : true },
            port :{ required : true },
            schema :{ required : true },
            description :{ required : true },
        },
        messages:{
            job_name :{ required : "This field is required" },
            login :{ required : "This field is required" },    
            password :{ required : "This field is required" },    
            address :{ required : "This field is required" },    
            port :{ required : "This field is required" },    
            schema :{ required : "This field is required" },    
            description :{ required : "This field is required" },            
        }
    });
});


</script>

<script>
    $('#db_type').change(function(){
        var val = $('#db_type').val();

       if (val == 'oracle_sid'){
        $('.oracle_ServiceName').remove();

        $('#oracle_sid').append('<div class="animated fadeInLeft oracle_sid"><hr><h4>Oracle Settings</h4><small>This setting is only for Oracle Database in which uses SID</small><br><br><div class="row"><div class="col-md-3"><div class="form-group"><label for="value5">Oracle SID</label><input type="text" class="form-control" id="oracle_sid" value="<?php echo $fetch->oracle_sid ?>" name="oracle_sid" maxlength="200" autocomplete="off"></div></div></div></div>');

       } else if (val == 'oracle_ServiceName'){
        $('.oracle_sid').remove();

        $('#oracle_ServiceName').append('<div class="animated fadeInLeft oracle_ServiceName"><hr><h4>Oracle Settings</h4><small>This setting is only for Oracle Database in which uses Service Name</small><br><br><div class="row"><div class="col-md-3"><div class="form-group"><label for="value5">Oracle Service name</label><input type="text" class="form-control" id="oracle_ServiceName" value="<?php echo $fetch->oracle_ServiceName ?>" name="oracle_ServiceName" maxlength="200" autocomplete="off"></div></div></div></div>');

       } else {
        $('.oracle_ServiceName').remove();
        $('.oracle_sid').remove();

       }
    });



</script>