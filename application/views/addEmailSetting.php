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

                <div class="col-md-3">
                   <div class="form-group">
                    <label for="smtp">Jenkins Enviroment Variables</label>
                    <button type="button" class="btn btn-info" data-toggle="modal" data-target="#modal-default">
                        Jenkins Variables
                    </button>
                </div>
            </div>

        </div>
        <hr>
        <h4>Email Header:</h4><br>
        <div class="row">   

          <div class="col-md-3">

            <div class="form-group">
                <label for="to">To</label>
                <input type="text" class="form-control required" id="to" value="<?php echo set_value('to'); ?>" name="to" maxlength="200" autocomplete="on" required>
            </div>
        </div>

        <div class="col-md-3">

            <div class="form-group">
                <label for="from">From</label>
                <input type="text" class="form-control" id="from" value="<?php echo set_value('from'); ?>" name="from" maxlength="200" autocomplete="off" onkeypress="return event.charCode != 32">
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

<div class="modal fade" id="modal-default" style="display: none;">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span></button>
          <h4 class="modal-title"><b>Jenkins Enviroment Variables</b></h4>
      </div>
      <div class="modal-body">
          <div class="alert alert-info alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <h4><i class="icon fa fa-info"></i> Usage Information</h4>
            <p><b>Note:</b> All these variables will just be replaced if you call them using jenkins editable email function during job creation, otherwise your mailer will text the variable name.</p>
            <p><b>Usage</b> In order to use them, just write as it is in your email message</p>
            <p><b>Example:</b> My job is $JOB_NAME and my build is $BUILD_NUMBER</p>
            <p>The variable replacement will also work if you text it in Subject field.</p>
            <p><b>Best Regards:</b></p> <p>Matheus Pavanetti</p>
        </div>
        <hr>
        <table class="table table-bordered stripped" role="grid" style="padding: 0px;"><thead class="tableFloatingHeaderOriginal"><tr role="row" class="tablesorter-headerRow"><th class="confluenceTh tablesorter-header sortableHeader tablesorter-headerUnSorted" data-column="0" tabindex="0" scope="col" role="columnheader" aria-disabled="false" unselectable="on" aria-sort="none" aria-label="Environment Variable: No sort applied, activate to apply an ascending sort" style="user-select: none;"><div class="tablesorter-header-inner"><p>Environment Variable</p></div></th><th class="confluenceTh tablesorter-header sortableHeader tablesorter-headerUnSorted" data-column="1" tabindex="0" scope="col" role="columnheader" aria-disabled="false" unselectable="on" aria-sort="none" aria-label="Description: No sort applied, activate to apply an ascending sort" style="user-select: none;"><div class="tablesorter-header-inner"><p>Description</p></div></th></tr></thead><thead class="tableFloatingHeader" style="display: none;"><tr role="row" class="tablesorter-headerRow"><th class="confluenceTh tablesorter-header sortableHeader tablesorter-headerUnSorted" data-column="0" tabindex="0" scope="col" role="columnheader" aria-disabled="false" unselectable="on" aria-sort="none" aria-label="Environment Variable: No sort applied, activate to apply an ascending sort" style="user-select: none;"><div class="tablesorter-header-inner"><p>Environment Variable</p></div></th><th class="confluenceTh tablesorter-header sortableHeader tablesorter-headerUnSorted" data-column="1" tabindex="0" scope="col" role="columnheader" aria-disabled="false" unselectable="on" aria-sort="none" aria-label="Description: No sort applied, activate to apply an ascending sort" style="user-select: none;"><div class="tablesorter-header-inner"><p>Description</p></div></th></tr></thead><tbody aria-live="polite" aria-relevant="all"><tr role="row"><td class="confluenceTd"><p>$BUILD_NUMBER</p></td><td class="confluenceTd"><p>The current build number, such as "153"</p></td></tr><tr role="row"><td class="confluenceTd"><p>$BUILD_ID</p></td><td class="confluenceTd"><p>The current build id, such as "2005-08-22_23-59-59" (YYYY-MM-DD_hh-mm-ss, <a href="https://issues.jenkins-ci.org/browse/JENKINS-26520" class="external-link" rel="nofollow">defunct</a>&nbsp;since&nbsp;version 1.597)</p></td></tr><tr role="row"><td class="confluenceTd"><p>$BUILD_URL</p></td><td class="confluenceTd"><p>The URL where the results of this build can be found (e.g. <span class="nolink">http://buildserver/jenkins/job/MyJobName/666/</span>)</p></td></tr><tr role="row"><td class="confluenceTd"><p>$NODE_NAME</p></td><td class="confluenceTd"><p>The name of the node the current build is running on. Equals 'master' for master node.</p></td></tr><tr role="row"><td class="confluenceTd"><p>$JOB_NAME</p></td><td class="confluenceTd"><p>Name of the project of this build. This is the name you gave your job when you first set it up. It's the third column of the Jenkins Dashboard main page.</p></td></tr><tr role="row"><td class="confluenceTd"><p>$BUILD_TAG</p></td><td class="confluenceTd"><p>String of <code>jenkins-${JOB_NAME}-${BUILD_NUMBER</code>}. Convenient to put into a resource file, a jar file, etc for easier identification.</p></td></tr><tr role="row"><td class="confluenceTd"><p>$JENKINS_URL</p></td><td class="confluenceTd"><p>Set to the URL of the Jenkins master that's running the build. This value is used by <a href="/display/JENKINS/Jenkins+CLI">Jenkins CLI</a> for example</p></td></tr><tr role="row"><td class="confluenceTd"><p>$EXECUTOR_NUMBER</p></td><td class="confluenceTd"><p>The unique number that identifies the current executor (among executors of the same machine) that's carrying out this build. This is the number you see in the "build executor status", except that the number starts from 0, not 1.</p></td></tr><tr role="row"><td class="confluenceTd"><p>$JAVA_HOME</p></td><td class="confluenceTd"><p>If your job is configured to use a specific JDK, this variable is set to the JAVA_HOME of the specified JDK. When this variable is set, PATH is also updated to have $JAVA_HOME/bin.</p></td></tr><tr role="row"><td class="confluenceTd"><p>$WORKSPACE</p></td><td class="confluenceTd"><p>The absolute path of the workspace.</p></td></tr><tr role="row"><td class="confluenceTd"><p>$SVN_REVISION</p></td><td class="confluenceTd"><p>For Subversion-based projects, this variable contains the revision number of the module. If you have more than one module specified, this won't be set.</p></td></tr><tr role="row"><td class="confluenceTd"><p>$CVS_BRANCH</p></td><td class="confluenceTd"><p>For CVS-based projects, this variable contains the branch of the module. If CVS is configured to check out the trunk, this environment variable will not be set.</p></td></tr><tr role="row"><td class="confluenceTd"><p>$GIT_COMMIT</p></td><td class="confluenceTd"><p>For Git-based projects, this variable contains the Git hash of the commit checked out for the build (like&nbsp;ce9a3c1404e8c91be604088670e93434c4253f03) (all the GIT_* variables require git plugin)&nbsp; &nbsp;&nbsp;</p></td></tr><tr role="row"><td class="confluenceTd"><p>$GIT_URL</p></td><td class="confluenceTd"><p>For Git-based projects, this variable contains the Git url (like&nbsp;git@github.com:user/repo.git or [https://github.com/user/repo.git])</p></td></tr><tr role="row"><td class="confluenceTd"><p>$GIT_BRANCH</p></td><td class="confluenceTd"><p>For Git-based projects, this variable contains the Git branch that was checked out for the build (normally origin/master)</p></td></tr></tbody></table>

        

    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-primary pull-left" data-dismiss="modal">Close</button>
    </div>
</div>
<!-- /.modal-content -->
</div>
<!-- /.modal-dialog -->
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
                subject :{ required : true },
                msg :{ required : true },
                description :{ required : true },
            },
            messages:{
                name :{ required : "This field is required" },
                smtp :{ required : "This field is required" },
                to :{ required : "This field is required" },     
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
    CKEDITOR.replace('msg', {
      language: 'en'
  });

   CKEDITOR.config.extraPlugins = 'colorbutton,justify,find,font,templates,tableresize,tableselection,tabletools,selectall,codesnippet,indentblock'; 
</script>


