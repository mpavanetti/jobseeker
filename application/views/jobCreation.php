<link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/bower_components/select2/dist/css/select2.min.css">
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

  .checkbox input {

    transform: scale(1.5);
  }
  .checkbox label {
    
    font-size: 16px;
  }
</style>
<div class="content-wrapper">    
  <section class="content-header">
    <h1>
      Job Creation
      <small>Run Jobs</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Job Management</a></li>
      <li class="active">Job Creation</li>
    </ol>
  </section>
  <section class="content">
    <div class="container">
      <div class="row" style="margin-top: 10px; margin-bottom: 10px;">
        <div class="col-xs-12 text-right">
          <a class="btn btn-default" href="<?php echo base_url(); ?>jobList"><i class="fa fa-list"></i> Job Build List</a>
        </div>
      </div>
<!--    <div class="row" style="margin-top: 10px; margin-bottom: 40px;">
     <div class="col-lg-12 col-md-12 col-xs-12">
      <div class="text-center">
        <h3>Statistic Content</h3>
      </div>
    </div>
  </div> -->

  <div class="row" style="margin-top: 10px; margin-bottom: 10px;">
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
      <div class="alert alert-success alert-dismissable destroy">
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

<div class="row" style="margin-top: 5px;">
  <div class="col-xs-12">
    <div id="box" class="box box-primary collapsed-box">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Available Jobs</b></h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body" style="width: 100%;">
        <table id="myTable" class="table table-bordered table-striped" style="width: 100%;">
          <thead>
            <tr>
              <th>Build Situation</th>
            <th>Job Name</th>
            <th>Url</th>
            <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
           <tr>
            <th>Build Situation</th>
            <th>Job Name</th>
            <th>Url</th>
            <th>Actions</th>
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
<?php $this->load->helper("form"); ?>
<form role="form" id="InsertDbSettings" action="<?php echo base_url() ?>jobCreation/send" method="post">
<div class="alert alert-info editJobBanner" style="display: none;">
  <i class="fa fa-pencil"></i>
  Editing <b class="editJobName"></b>. Saving will update this Jenkins job unless you change the job name.
  <button type="button" id="clearEditJob" class="btn btn-default btn-xs pull-right"><i class="fa fa-plus"></i> New Job</button>
</div>
<div class="row">
  <div class="col-lg-6 col-md-6 col-xs-12">
    <div class="box box-primary" style="padding-bottom: 15px;">
      <div class="overlay" style="display:none;">
        <i class="fa fa-refresh fa-spin"></i>
      </div>
      <div class="box-header with-border" style="padding-top: 15px;">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Job Input Fields</b></h3> 
      </div>

      <!-- /.box-header -->
      <!-- input fields -->
        <div class="box-body" style="padding-top: 15px;">
          <div class="form-group">
            <label for="exampleInputEmail1">Job Name</label>
            <input type="text" name ="job_name" class="form-control" id="job_name" maxlength="50" placeholder="Auto-generated if empty" onkeypress="return event.charCode != 32">
          </div>
          <div class="form-group" style="padding-top: 5px;">
            <div class="form-group">
              <label for="description">Description</label>
              <textarea class="form-control" id="description" value="" name="description" maxlength="500" rows="5"></textarea>
            </div>
          </div>
        </div>
        <!-- /.box-body -->
      </div>
    </div>

    <div class="col-lg-6 col-md-6 col-xs-12">
      <div class="box box-primary">
        <div class="overlay" style="display:none;">
          <i class="fa fa-refresh fa-spin"></i>
        </div>
        <div class="box-header with-border">
          <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
            </button>
          </div>
          <h3 class="box-title"><b>Job Options</b></h3>
        </div>
        <!-- /.box-header -->
        <!-- form start -->
        <div class="box-body">
          <div class="checkbox">
            <label>
              <input type="checkbox" name="checkBuild" id="checkBuild" value="1"> Schedule Job
            </label>
          </div>
          <div class="checkbox">
            <label>
              <input type="checkbox" name="checkEnvironment" id="checkEnvironment" value="1"> Choose Environment
            </label>
          </div>
          <div class="checkbox">
            <label>
              <input type="checkbox" name="abort" id="abort" value="1"> Abort the job if it's stuck
            </label>
          </div>
          <div class="checkbox">
            <label>
              <input type="checkbox" name="timestamp" id="timestamp" value="1"> Add timestamps to the Console Output
            </label>
          </div>
          <?php if($os == "windows") { ?>
            <div class="checkbox">
              <label>
                <input type="checkbox" name="winCommand" id="winCommand" value="1"> Execute a <b>Windows</b> local command or script
              </label>
            </div>
          <?php } else { ?>
            <div class="checkbox">
              <label>
                <input type="checkbox" name="linuxCommand" id="linuxCommand" value="1"> Execute a <b>Linux</b> local command or script
              </label>
            </div>
          <?php }?>
          <div class="checkbox">
            <label>
              <input type="checkbox" name="runJobCheck" id="runJobCheck" value="1"> Execute another job after this build
            </label>
          </div>
          <div class="checkbox">
            <label>
              <input type="checkbox" name="emailCheck" id="emailCheck" value="1"> Enable email notification in case of failure
            </label>
          </div>
          <div class="checkbox">
            <label>
              <input type="checkbox" name="editableEmailCheck" id="editableEmailCheck" value="1"> Enable editable email notification
            </label>
          </div>
          <div class="form-group" style="margin-top: 20px;">
            <div class="form-group">
              <input type="hidden" name="trigger_after_save" id="trigger_after_save" value="0">
              <button type="submit" id="send" href="#" class="btn btn-success buildXmlBtn"><i class="fa fa-save"></i> Save Job</button>
              <button type="submit" id="saveAndTrigger" class="btn btn-primary buildXmlBtn"><i class="fa fa-play"></i> Save And Trigger</button>
              <span class="saveJobStatus text-muted" style="display: none; margin-left: 10px;"></span>
            </div>
          </div>
        </div>
        <!-- /.box-body -->
      </div>
    </div>

  </div>

  <div class="row">


    <!-- Row and column for Schedule Job and Execute Windows / Linux Command, Script -->
    <div class="row">
      <div class="col-lg-12 col-md-12 col-xs-12">

        <!-- Run Windows Command,Script Area-->
        <div id="runWinCommand" style="display: none;">
          <div class="col-lg-6 col-md-6 col-xs-12">
            <div class="box box-primary">
              <div class="box-header with-border">
                <div class="box-tools pull-right">
                  <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
                <h3 class="box-title">
                  <b>Execute a Windows Command</b></h3>
                </div><div class="box-body">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="executionStrategy">Execution Strategy</label>
                        <select class="form-control" id="executionStrategy" name="executionStrategy">
                          <option value="0" selected>-- Select an action -- </option>
                          <option value="script">Script Execution</option>
                          <option value="command">Windows Command Execution</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-6 scriptTypeForm" style="display: none;">
                      <div class="form-group">
                        <label for="scriptType">Script Type</label>
                        <select class="form-control" id="scriptType" name="scriptType"><option value="0" selected>-- Select a script type -- </option>
                          <option value="batch">Windows Batch Script</option>
                          <option value="talend">Talend Data Integration Script</option>
                          <option value="python">Python Script</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <hr>
                  <div class="row windowsCommandForm" style="display: none;">
                    <div class="col-md-12 ">
                      <div class="form-group">
                        <label for="windowsCommandLine">Windows Command Line</label>
                        <textarea class="form-control" id="windowsCommandLine" name="windowsCommandLine"  maxlength="5000" autocomplete="off" rows="5"></textarea>
                      </div>
                    </div>
                  </div>
                  <div class="row uploadScript" style="display: none;">
                    <div class="col-md-12 ">
                      <div class="form-group">
                        <div id="windowsColumn"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Close Run Windows Command,Script Area -->

          <!-- Run Linux Command,Script Area -->
          <div id="runlinuxCommand" style="display: none;">
            <div class="col-lg-6 col-md-6 col-xs-12">
              <div class="box box-primary">
                <div class="box-header with-border">
                  <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    <button id="hideLinuxCommand" type="button" class="btn btn-box-tool"><i class="fa fa-times"></i></button>
                  </div>
                  <h3 class="box-title">
                    <b>Execute a Linux Command</b></h3>
                  </div><div class="box-body">
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="linuxExecutionStrategy">Execution Strategy</label>
                          <select class="form-control" id="linuxExecutionStrategy" name="linuxExecutionStrategy">
                            <option value="0" selected>-- Select an action -- </option>
                            <option value="script">Script Execution</option>
                            <option value="command">Linux Command Execution</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6 linuxScriptTypeForm" style="display: none;">
                        <div class="form-group">
                          <label for="linuxScriptType">Script Type</label>
                          <select class="form-control" id="linuxScriptType" name="linuxScriptType"><option value="0" selected>-- Select a script type -- </option>
                            <option value="bash">Linux Bash Script</option>
                            <option value="talend">Talend Data Integration Script</option>
                            <option value="python">Python Script</option>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="row pythonSourceForm" style="display: none;">
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="pythonSourceMode">Python Source</label>
                          <select class="form-control" id="pythonSourceMode" name="pythonSourceMode">
                            <option value="upload" selected>Uploaded File or Archive</option>
                            <option value="path">Repository Path</option>
                            <option value="git">Git Repository URL</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-8">
                        <div class="form-group">
                          <label for="pythonEntryPoint">Entry Python File</label>
                          <input type="text" class="form-control" id="pythonEntryPoint" name="pythonEntryPoint" maxlength="500" autocomplete="off" placeholder="main.py">
                        </div>
                      </div>
                    </div>
                    <div class="row pythonPathSourceForm" style="display: none;">
                      <div class="col-md-12">
                        <div class="form-group">
                          <label for="pythonSourcePath">Repository Path</label>
                          <input type="text" class="form-control" id="pythonSourcePath" name="pythonSourcePath" maxlength="1000" autocomplete="off" placeholder="python/jobs/my-job or /php/repository/python/jobs/my-job">
                        </div>
                      </div>
                    </div>
                    <div class="row pythonGitSourceForm" style="display: none;">
                      <div class="col-md-8">
                        <div class="form-group">
                          <label for="pythonRepositoryUrl">Git Repository URL</label>
                          <input type="text" class="form-control" id="pythonRepositoryUrl" name="pythonRepositoryUrl" maxlength="1000" autocomplete="off" placeholder="https://github.com/org/project.git">
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="pythonRepositoryBranch">Branch or Tag</label>
                          <input type="text" class="form-control" id="pythonRepositoryBranch" name="pythonRepositoryBranch" maxlength="200" autocomplete="off" placeholder="main">
                        </div>
                      </div>
                    </div>
                    <hr>
                    <div class="row linuxCommandForm" style="display: none;">
                      <div class="col-md-12 ">
                        <div class="form-group">
                          <label for="linuxCommandLine">Linux Command Line</label>
                          <textarea class="form-control" id="linuxCommandLine" name="linuxCommandLine"  maxlength="5000" autocomplete="off" rows="5"></textarea>
                        </div>
                      </div>
                    </div>
                    <div class="row linuxUploadScript" style="display: none;">
                      <div class="col-md-12 ">
                        <div class="form-group">
                          <div id="linuxColumn"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!--Close Run Liux Command, Script Area-->

            <!-- Schedule Job Area-->
            <div id="build" style="display: none;">
              <div class="col-lg-6 col-md-6 col-xs-12 removeBuild">
                <div class="box box-primary">
                  <div class="box-header with-border">
                    <div class="box-tools pull-right">
                      <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    </div>
                    <h3 class="box-title">
                      <b>Schedule Job</b></h3>
                    </div>
                    <div class="box-body" style="padding: 18px;">
                      <div class="row">
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="timeoutStrategy">Trigger Action</label>
                            <select class="form-control" id="action" name="action">
                              <option value="0">-- Select an action --</option>
                              <option value="single">Single Execution</option>
                              <option value="repetitive">Repetitive Executions</option>
                              <option value="tags">Execution Tags Options</option>
                            </select>
                          </div>
                        </div>
                      </div>

                      <div class="row tags" style="display: none;">
                        <hr>
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="tag">Execution Tag Option</label>
                            <select class="form-control" id="tag" name="tag">
                              <option value="@hourly">Hourly Executions</option>
                              <option value="@daily">Daily Executions</option>
                              <option value="@weekly">Weekly Executions</option>
                              <option value="@monthly">Monthly Executions</option>
                              <option value="@annually">Annually Executions</option>
                              <option value="@yearly">Yearly Executions</option>
                              <option value="@midnight">Midnight Executions</option>
                            </select>
                          </div>
                        </div>
                      </div>

                      <div class="row singleForm" style="display: none;">
                        <hr>
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                          <div class="input-group" style="width: 100%;">
                            <label for="singleMinute">Every Minute: </label><br>
                            <select class="form-control select2" id="singleMinute" name="singleMinute[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php  
                              $i = 0;
                              for ($i=0; $i < 60; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 form-group">
                          <div class="input-group" style="width: 100%;">
                            <label>At Hour: </label><br>
                            <select class="form-control select2" id="singleHour" name="singleHour[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php  
                              $i = 0;
                              for ($i=0; $i < 24; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                      </div> <!-- Close row -->
                      <div class="row singleForm" style="display: none;">
                        <div class="col-lg-6 col-md-6 col-xs-12">
                          <div class="form-group">
                            <label for="singleDayOfMonth">On Day of month:</label><br>
                            <select class="form-control select2" id="singleDayOfMonth" name="singleDayOfMonth[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php  
                              $i = 1;
                              for ($i=1; $i < 32; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-xs-12">
                          <div class="form-group">
                            <label for="singleMonth">On Month:</label><br>
                            <select class="form-control select2" id="singleMonth" name="singleMonth[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php  
                              $i = 1;
                              for ($i=1; $i < 13; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                      </div> <!-- Close row -->
                      <div class="row singleForm" style="display: none;">
                        <div class="col-lg-6 col-md-6 col-xs-12">
                          <div class="form-group">
                            <label for="singleDayOfWeek">On Day of Week:</label><br>
                            <select class="form-control select2" id="singleDayOfWeek" name="singleDayOfWeek[]" multiple="multiple">
                              <option value="*" selected>All</option>
                              <?php  
                              $i = 0;
                              for ($i=1; $i < 8; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                      </div> <!-- Close row -->
                      <div class="row repetitive" style="display: none;">
                        <hr>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveMinute">In X Minutes</label><br>
                            <select class="form-control" id="repetitiveMinute" name="repetitiveMinute">
                              <option value="*">All</option>
                              <?php  
                              $i = 0;
                              for ($i=0; $i < 60; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveHour">Hour</label>
                            <select class="form-control" id="repetitiveHour" name="repetitiveHour">
                              <option value="*">All</option>
                              <?php  
                              $i = 0;
                              for ($i=0; $i < 24; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveDayOfMonth">Day of month</label>
                            <select class="form-control" id="repetitiveDayOfMonth" name="repetitiveDayOfMonth">
                              <option value="*">All</option>
                              <?php  
                              $i = 1;
                              for ($i=1; $i < 32; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveMonth">Month</label>
                            <select class="form-control" id="repetitiveMonth" name="repetitiveMonth">
                              <option value="*">All</option>
                              <?php  
                              $i = 1;
                              for ($i=1; $i < 13; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="repetitiveDayOfWeek">Day of Week</label>
                            <select class="form-control" id="repetitiveDayOfWeek" name="repetitiveDayOfWeek">
                              <option value="*">All</option>
                              <?php  
                              $i = 0;
                              for ($i=1; $i < 8; $i++) { 
                                echo '<option value="'.$i.'">'.$i.'</option>';    
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Close Schedule Job Area-->
            </div>
          </div>

          <!-- Row and column for Abort Job and Email Notification Divs -->
          <div class="row">
            <div class="col-lg-12 col-md-12 col-xs-12">

             <!-- Email Notification Area -->
             <div id="enableEmail" style="display: none;">
              <div class="col-lg-6 col-md-6 col-xs-12">
                <div class="box box-primary">
                  <div class="box-header with-border">
                    <div class="box-tools pull-right">
                      <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    </div>
                    <h3 class="box-title">
                      <b>Enable Email Notification</b></h3>
                    </div><div class="box-body">
                      <div class="col-md-12">
                        <div class="form-group">
                          <label for="recipients">Recipients</label>
                          <input type="text" class="form-control" id="recipients" name="recipients">
                          <small><b>Example:</b> matheuspavanetti@gmail.com,matheuspavanetti@hotmail.com</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Close Email Notification Area -->

              <!-- Abort Job if it Stuck Area -->
              <div id="abortIfStuck" style="display: none;">
                <div class="col-lg-6 col-md-6 col-xs-12">
                  <div class="box box-primary">
                    <div class="box-header with-border">
                      <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                      </div>
                      <h3 class="box-title">
                        <b>Abort the job if its stuck option</b></h3>
                      </div>
                      <div class="box-body" style="padding: 20px;">
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="timeoutStrategy">Timeout Strategy</label>
                            <select class="form-control" id="timeoutStrategy" name="timeoutStrategy">
                              <option value="noActivity">No Activity</option>
                              <option value="absolute">Absolute</option>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-6 timeoutSeconds">
                          <div class="form-group">
                            <label for="timeoutMinutes">Timeout Seconds</label>
                            <input type="number" class="form-control" id="timeoutSeconds" name="timeoutSeconds" min="60" maxlength="50" autocomplete="off">
                          </div>
                        </div>
                        <div class="col-md-6 timeoutMinutes" style="display: none;">
                          <div class="form-group">
                            <label for="timeoutMinutes">Timeout Minutes</label>
                            <input type="number" class="form-control" id="timeoutMinutes" name="timeoutMinutes" min="1"  maxlength="50" autocomplete="off">
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Close Abort Job if it Stuck Area -->
              </div>
            </div>
            <!-- Close Row and column for Abort Job and Email Notification Divs -->

            <!-- Row and column for Job Execution Area and Editable Email Notification -->
            <div class="row">
              <div class="col-lg-12 col-md-12 col-xs-12">

               <!-- Job Execution Area -->
               <div id="runJob" style="display: none;">
                <div class="col-lg-6 col-md-6 col-xs-12">
                  <div class="box box-primary">
                    <div id="overlay" class="overlay" style="display: none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
                    <div class="box-header with-border">
                      <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                      </div>
                      <h3 class="box-title">
                        <b>Execute another job</b></h3>
                      </div>
                      <div class="box-body">
                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="timeoutStrategy">Select next jobs to be executed</label><br>
                              <select class="form-control select2" id="jobList" name="jobList[]" multiple="multiple" style="width: 200px;">
                              </select>
                            </div>
                          </div>
                        </div>
                        <div class="row">
                          <hr>
                          <div class="col-lg-12 col-md-12 col-xs-12">
                            <h5><b>Select an option for your next jobs.</b></h5>
                            <div class="form-group">
                              <div class="radio">
                                <label>
                                  <input type="radio" name="optionsRadios" id="option1" value="1" checked="">
                                  Run next jobs only if this job has been successfully executed.
                                </label>
                              </div>
                              <div class="radio">
                                <label>
                                  <input type="radio" name="optionsRadios" id="option2" value="2">
                                  Run next jobs even if this job has been failed.
                                </label>
                              </div>
                            </div>
                          </div>  
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Close Job Execution Area -->

                <!-- Editable Email Notification Area -->
               <div id="editableEmail" style="display: none;">
                <div class="col-lg-6 col-md-6 col-xs-12">
                  <div class="box box-primary">
                    <div id="overlay" class="overlay" style="display: none;">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
                    <div class="box-header with-border">
                      <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                      </div>
                      <h3 class="box-title">
                        <b>Editable email notification</b></h3>
                      </div>
                      <div class="box-body">
                        <div class="row">
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">On <b class="text-green">Success</b> email Template</label><br>
                              <select class="form-control fetchEmail" id="onSuccess" name="onSuccess" style="width: 200px;">
                                <option value="0">Please, select an option</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">Attach Job Log</label><br>
                              <select class="form-control" id="attSuccess" name="attSuccess" style="width: 200px;">
                                <option value="true">Yes</option>
                                <option value="false" selected>No</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">On <b class="text-red">Failure</b> email Template</label><br>
                              <select class="form-control fetchEmail" id="onFailure" name="onFailure" style="width: 200px;">
                                <option value="0">Please, select an option</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">Attach Job Log</label><br>
                              <select class="form-control" id="attFailure" name="attFailure" style="width: 200px;">
                                <option value="true">Yes</option>
                                <option value="false" selected>No</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">On <b class="text-red">Abort</b> email Template</label><br>
                              <select class="form-control fetchEmail" id="onAbort" name="onAbort" style="width: 200px;">
                                <option value="0">Please, select an option</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="form-group">
                              <label for="timeoutStrategy">Attach Job Log</label><br>
                              <select class="form-control" id="attAbort" name="attAbort" style="width: 200px;">
                                <option value="true">Yes</option>
                                <option value="false" selected>No</option>
                              </select>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Close Job Execution Area -->

                <!-- Open Environment Area -->
                <div id="environmentBox" style="display: none;">
                <div class="col-lg-6 col-md-6 col-xs-12">
                  <div class="box box-primary">
                    <div class="overlay" style="display:none;">
                        <i class="fa fa-refresh fa-spin"></i>
                    </div>
                    <div class="box-header with-border">
                      <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                      </div>
                      <h3 class="box-title">
                        <b>Select an Environment</b></h3>
                      </div>
                      <div class="box-body" style="padding: 20px;">
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="environment">Environment</label>
                            <select class="form-control env" id="environment" name="environment">
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Close Environment Area -->

              </div>
            </div>
            <!-- Close and column for Job Execution Area and Editable Email Notification -->
        </div>
      </form> <!-- Close Form -->
        <div id="output"></div>

        <?php
        $xml = $this->session->flashdata('xml');
        if($xml)
        {
        ?>
        <div class="row generatedXmlPanel">
          <div class="col-lg-12 col-md-12 col-xs-12">
            <div class="box">
              <div class="box-header with-border">
                <div class="box-tools pull-right">
                  <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                  </button>
                </div>
                <h3 class="box-title"><b>Generated Jenkins XML</b></h3>
              </div>
              <div class="box-body">
                <pre id="xml" class="xml"><?php echo $xml; ?> </pre>
              </div>
              <div class="overlay" style="display:none;">
                <i class="fa fa-refresh fa-spin"></i>
              </div>
            </div>
          </div>
        </div>
        <?php } ?>
    </div>
  </section>
</div>
  <script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/select2/dist/js/select2.min.js"></script>
  <script type="text/javascript" src="<?php echo base_url(); ?>assets/plugins/dropzone/dropzone.js"></script>
  <script type="text/javascript">
    $(document).ready(function(){
    
      $('.select2').select2({
       placeholder: "Click to Select a option",
       allowClear: true
     });

      var addUserForm = $("#InsertDbSettings");
      var validator = addUserForm.validate({

        rules:{
          job_name :{ maxlength : 50 },
          description :{ maxlength : 500 }
        },
        messages:{
          job_name :{ maxlength : "Job name can contain up to 50 characters" },
          description :{ maxlength : "Description can contain up to 500 characters" }
        }
      });

      $('#send').click(function(){
        $('#trigger_after_save').val('0');
      });

      $('#saveAndTrigger').click(function(){
        $('#trigger_after_save').val('1');
      });

      var petNamePrefixes = ['milo', 'luna', 'piper', 'nova', 'ruby', 'jasper', 'olive', 'cosmo'];
      var petNameSuffixes = ['sunny', 'maple', 'pixel', 'river', 'coco', 'sage', 'mango', 'ember'];
      var singleEveryMinuteAcknowledged = false;
      var repetitiveEveryMinuteAcknowledged = false;

      function randomJobNameToken() {
        return ('000' + Math.floor(Math.random() * 46656).toString(36)).slice(-3);
      }

      function generateJobName() {
        var prefix = petNamePrefixes[Math.floor(Math.random() * petNamePrefixes.length)];
        var suffix = petNameSuffixes[Math.floor(Math.random() * petNameSuffixes.length)];

        return prefix + '-' + suffix + '-' + randomJobNameToken();
      }

      function ensureJobName() {
        var jobName = $.trim($('#job_name').val());

        if (jobName == '') {
          jobName = generateJobName();
          $('#job_name').val(jobName);
          toastr.info('Generated job name: ' + jobName, 'Job Name');
        }

        return jobName;
      }

      function updatePythonSourceControls() {
        var isPythonScript = $('#linuxExecutionStrategy').val() == 'script' && $('#linuxScriptType').val() == 'python';
        var sourceMode = $('#pythonSourceMode').val() || 'upload';

        $('.pythonSourceForm').toggle(isPythonScript);
        $('.pythonPathSourceForm').toggle(isPythonScript && sourceMode == 'path');
        $('.pythonGitSourceForm').toggle(isPythonScript && sourceMode == 'git');

        if (! isPythonScript || sourceMode != 'upload') {
          $('.linuxUploadScript').hide();
          $('.destroyDropzone').remove();
        }
      }

      $('#pythonSourceMode').change(function() {
        updatePythonSourceControls();
        if ($('#linuxExecutionStrategy').val() == 'script' && $('#linuxScriptType').val() == 'python' && $('#pythonSourceMode').val() == 'upload') {
          $('#linuxScriptType').trigger('change');
        }
      });

     // get Jenkins credentials
     var jenkins_url = '<?php echo $jenkins_url; ?>';
    var jenkins_username = '';
    var jenkins_token = '';
     var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';    

     function escapeHtml(value) {
      return $('<div>').text(value == null ? '' : value).html();
    }

    function escapeAttribute(value) {
      return escapeHtml(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function firstXmlElement(xmlDoc, tagName, root) {
      var elements = (root || xmlDoc).getElementsByTagName(tagName);
      return elements.length ? elements[0] : null;
    }

    function firstXmlText(xmlDoc, tagName, root) {
      var element = firstXmlElement(xmlDoc, tagName, root);
      return element ? $.trim(element.textContent || '') : '';
    }

    function ensureSelectOption(selector, value) {
      if (value == null || value === '') {
        return;
      }

      var select = $(selector);
      var exists = select.find('option').filter(function() {
        return this.value == value;
      }).length > 0;

      if (!exists) {
        select.append($('<option>', { value: value, text: value }));
      }
    }

    function setSelectValue(selector, value) {
      ensureSelectOption(selector, value);
      $(selector).val(value).trigger('change');
    }

    function setSelectValues(selector, values) {
      values = $.grep(values || [], function(value) {
        return value != null && value !== '';
      });

      $.each(values, function(index, value) {
        ensureSelectOption(selector, value);
      });

      $(selector).val(values).trigger('change');
    }

    function cronValues(value) {
      return $.grep(($.trim(value || '')).split(','), function(part) {
        return part !== '';
      });
    }

    function resetJobCreationForm() {
      var form = $('#InsertDbSettings')[0];
      if (form) {
        form.reset();
      }

      $('#trigger_after_save').val('0');
      $('.editJobBanner').hide();
      $('.editJobName').text('');
      $('.saveJobStatus').hide().text('');
      $('.select2').val(null).trigger('change');
      $('#action').val('0');
      $('#linuxExecutionStrategy').val('0');
      $('#linuxScriptType').val('0');
      $('#executionStrategy').val('0');
      $('#scriptType').val('0');
      $('.singleForm, .repetitive, .tags, #build, #runWinCommand, #runlinuxCommand, .scriptTypeForm, .windowsCommandForm, .uploadScript, .linuxScriptTypeForm, .linuxCommandForm, .linuxUploadScript, .pythonSourceForm, .pythonPathSourceForm, .pythonGitSourceForm, #enableEmail, #abortIfStuck, #runJob, #environmentBox, #editableEmail').hide();
      $('#timeoutMinutes, #timeoutSeconds, #environment').prop('required', false);
      $('.destroyDropzone').remove();
    }

    function showEditBanner(jobName) {
      $('.editJobName').text(jobName);
      $('.editJobBanner').show();
    }

    function hydrateSchedule(xmlDoc) {
      var spec = firstXmlText(xmlDoc, 'spec');

      if (spec === '') {
        $('#checkBuild').prop('checked', false);
        $('#build').hide();
        return;
      }

      $('#checkBuild').prop('checked', true);
      $('#build').show();

      if (spec.charAt(0) == '@') {
        setSelectValue('#action', 'tags');
        setSelectValue('#tag', spec);
        $('.tags').show();
        $('.singleForm, .repetitive').hide();
        return;
      }

      var parts = spec.split(/\s+/);
      if (parts.length < 5) {
        return;
      }

      if (/^H\/\d+$/.test(parts[0])) {
        setSelectValue('#action', 'repetitive');
        setSelectValue('#repetitiveMinute', parts[0].replace('H/', ''));
        setSelectValue('#repetitiveHour', parts[1]);
        setSelectValue('#repetitiveDayOfMonth', parts[2]);
        setSelectValue('#repetitiveMonth', parts[3]);
        setSelectValue('#repetitiveDayOfWeek', parts[4]);
        $('.repetitive').show();
        $('.singleForm, .tags').hide();
        return;
      }

      setSelectValue('#action', 'single');
      setSelectValues('#singleMinute', cronValues(parts[0]));
      setSelectValues('#singleHour', cronValues(parts[1]));
      setSelectValues('#singleDayOfMonth', cronValues(parts[2]));
      setSelectValues('#singleMonth', cronValues(parts[3]));
      setSelectValues('#singleDayOfWeek', cronValues(parts[4]));
      $('.singleForm').show();
      $('.repetitive, .tags').hide();
    }

    function unquoteShellValue(value) {
      value = $.trim(value || '');
      if (value.length >= 2 && value.charAt(0) == "'" && value.charAt(value.length - 1) == "'") {
        return value.substring(1, value.length - 1).replace(/'\\''/g, "'");
      }

      if (value.length >= 2 && value.charAt(0) == '"' && value.charAt(value.length - 1) == '"') {
        return value.substring(1, value.length - 1);
      }

      return value;
    }

    function shellExportValue(command, variableName) {
      var prefix = 'export ' + variableName + '=';
      var lines = command.split(/\r?\n/);

      for (var index = 0; index < lines.length; index++) {
        if (lines[index].indexOf(prefix) === 0) {
          return unquoteShellValue(lines[index].substring(prefix.length));
        }
      }

      return '';
    }

    function relativeScriptPath(sourceDirectory, scriptPath) {
      if (sourceDirectory !== '' && scriptPath.indexOf(sourceDirectory + '/') === 0) {
        return scriptPath.substring(sourceDirectory.length + 1);
      }

      return scriptPath.split('/').pop();
    }

    function loadEnvironmentOptions(selectedEnvironment) {
      selectedEnvironment = selectedEnvironment || '';

      $('.env option').remove();
      $('.env').append($('<option>', {
        value: 0,
        text: "Please, select an option"
      }));

      if (selectedEnvironment !== '') {
        setSelectValue('#environment', selectedEnvironment);
      }

      return $.ajax({
        type: "GET",
        url: "<?php echo base_url(); ?>Context/fetchEnvironments",
        dataType: "html",
        beforeSend: function(){
          $('.overlay').fadeIn();
        },
        success: function(data){
          var json = JSON.parse(data);

          $('.env option').remove();
          $('.env').append($('<option>', {
            value: 0,
            text: "Please, select an option"
          }));

          $.each(json["data"], function(i, item) {
            var newJson = item.Environment;

            $('.env').append($('<option>', {
              value: newJson,
              text: newJson
            }));
          });

          if (selectedEnvironment !== '') {
            setSelectValue('#environment', selectedEnvironment);
          }

          $('.overlay').fadeOut();
        },
        error: function(arguments){
          toastr.error('Fail to fetch environments data' + arguments, 'Error to Fech Data')
          $('.overlay').fadeOut();
        }
      });
    }

    function showEnvironmentEditor(selectedEnvironment) {
      $('#checkEnvironment').prop('checked', true);
      $('#environmentBox').show();
      $('#environment').prop('required', true);
      loadEnvironmentOptions(selectedEnvironment);
    }

    function hydrateEnvironmentFromPythonCommand(command) {
      var runLine = '';
      var lines = command.split(/\r?\n/);

      $.each(lines, function(index, line) {
        if ($.trim(line).indexOf('python3 "$JOBSEEKER_SCRIPT_PATH"') === 0) {
          runLine = $.trim(line);
        }
      });

      var match = runLine.match(/^python3 "\$JOBSEEKER_SCRIPT_PATH"\s+(.+)$/);
      if (!match) {
        return;
      }

      var environment = unquoteShellValue(match[1]);
      if (environment !== '') {
        showEnvironmentEditor(environment);
      }
    }

    function hydratePythonCommand(jobName, command) {
      $('#linuxCommand').prop('checked', true);
      $('#runlinuxCommand').show();
      setSelectValue('#linuxExecutionStrategy', 'script');
      $('.linuxScriptTypeForm').show();
      $('.linuxCommandForm').hide();
      setSelectValue('#linuxScriptType', 'python');
      $('.pythonSourceForm').show();

      var cloneLine = '';
      $.each(command.split(/\r?\n/), function(index, line) {
        if (line.indexOf('git clone --depth 1') === 0) {
          cloneLine = line;
        }
      });

      if (cloneLine !== '') {
        var branchMatch = cloneLine.match(/--branch '([^']+)'/);
        var urlMatch = cloneLine.match(/'([^']+)' "\$WORKSPACE\/jobseeker-python-source"$/);
        setSelectValue('#pythonSourceMode', 'git');
        $('#pythonRepositoryUrl').val(urlMatch ? urlMatch[1] : '');
        $('#pythonRepositoryBranch').val(branchMatch ? branchMatch[1] : '');
        $('#pythonEntryPoint').val(shellExportValue(command, 'JOBSEEKER_ENTRYPOINT'));
        $('.pythonGitSourceForm').show();
        $('.pythonPathSourceForm, .linuxUploadScript').hide();
        hydrateEnvironmentFromPythonCommand(command);
        return;
      }

      var sourceDirectory = shellExportValue(command, 'JOBSEEKER_SOURCE_DIR');
      var scriptPath = shellExportValue(command, 'JOBSEEKER_SCRIPT_PATH');
      var entryPoint = relativeScriptPath(sourceDirectory, scriptPath);
      var uploadPath = '/python/jobs/' + jobName;

      if (sourceDirectory.indexOf(uploadPath) !== -1) {
        setSelectValue('#pythonSourceMode', 'upload');
        $('#pythonEntryPoint').val(entryPoint);
        $('.linuxUploadScript').show();
        $('.pythonPathSourceForm, .pythonGitSourceForm').hide();
      } else {
        setSelectValue('#pythonSourceMode', 'path');
        $('#pythonSourcePath').val(sourceDirectory);
        $('#pythonEntryPoint').val(entryPoint);
        $('.pythonPathSourceForm').show();
        $('.pythonGitSourceForm, .linuxUploadScript').hide();
      }

      hydrateEnvironmentFromPythonCommand(command);
    }

    function hydrateBuilders(xmlDoc, jobName) {
      var shell = firstXmlElement(xmlDoc, 'hudson.tasks.Shell');
      var batch = firstXmlElement(xmlDoc, 'hudson.tasks.BatchFile');

      if (shell) {
        var shellCommand = firstXmlText(xmlDoc, 'command', shell);
        if (shellCommand.indexOf('JOBSEEKER_SCRIPT_PATH') !== -1) {
          hydratePythonCommand(jobName, shellCommand);
        } else if ($.trim(shellCommand).indexOf('sh ') === 0) {
          $('#linuxCommand').prop('checked', true);
          $('#runlinuxCommand').show();
          setSelectValue('#linuxExecutionStrategy', 'script');
          $('.linuxScriptTypeForm').show();
          $('.linuxCommandForm').hide();
          setSelectValue('#linuxScriptType', 'bash');
        } else if (shellCommand !== '') {
          $('#linuxCommand').prop('checked', true);
          $('#runlinuxCommand').show();
          setSelectValue('#linuxExecutionStrategy', 'command');
          $('.linuxCommandForm').show();
          $('.linuxScriptTypeForm, .pythonSourceForm, .linuxUploadScript').hide();
          $('#linuxCommandLine').val(shellCommand);
        }
      }

      if (batch) {
        var batchCommand = firstXmlText(xmlDoc, 'command', batch);
        $('#winCommand').prop('checked', true);
        $('#runWinCommand').show();
        setSelectValue('#executionStrategy', 'command');
        $('.windowsCommandForm').show();
        $('.scriptTypeForm, .uploadScript').hide();
        $('#windowsCommandLine').val(batchCommand);
      }
    }

    function hydratePublishers(xmlDoc) {
      var mailer = firstXmlElement(xmlDoc, 'hudson.tasks.Mailer');
      if (mailer) {
        $('#emailCheck').prop('checked', true);
        $('#enableEmail').show();
        $('#recipients').val(firstXmlText(xmlDoc, 'recipients', mailer));
      }

      var buildTrigger = firstXmlElement(xmlDoc, 'hudson.tasks.BuildTrigger');
      if (buildTrigger) {
        var childProjects = $.map(firstXmlText(xmlDoc, 'childProjects', buildTrigger).split(','), function(value) {
          return $.trim(value);
        });
        $('#runJobCheck').prop('checked', true);
        $('#runJob').show();
        setSelectValues('#jobList', childProjects);

        var threshold = firstXmlElement(xmlDoc, 'threshold', buildTrigger);
        var thresholdName = firstXmlText(xmlDoc, 'name', threshold);
        $('input[name="optionsRadios"][value="' + (thresholdName == 'FAILURE' ? '2' : '1') + '"]').prop('checked', true);
      }

      if (firstXmlElement(xmlDoc, 'hudson.plugins.emailext.ExtendedEmailPublisher')) {
        toastr.warning('Editable email templates cannot be restored from Jenkins XML. Select templates again before saving if you want to keep editable email notifications.', 'Edit Job');
      }
    }

    function hydrateBuildWrappers(xmlDoc) {
      $('#timestamp').prop('checked', !!firstXmlElement(xmlDoc, 'hudson.plugins.timestamper.TimestamperBuildWrapper'));

      var timeoutWrapper = firstXmlElement(xmlDoc, 'hudson.plugins.build__timeout.BuildTimeoutWrapper');
      if (!timeoutWrapper) {
        return;
      }

      $('#abort').prop('checked', true);
      $('#abortIfStuck').show();
      var strategy = firstXmlElement(xmlDoc, 'strategy', timeoutWrapper);
      var strategyClass = strategy ? strategy.getAttribute('class') || '' : '';

      if (strategyClass.indexOf('AbsoluteTimeOutStrategy') !== -1) {
        setSelectValue('#timeoutStrategy', 'absolute');
        $('#timeoutMinutes').val(firstXmlText(xmlDoc, 'timeoutMinutes', timeoutWrapper)).prop('required', true);
        $('.timeoutMinutes').show();
        $('.timeoutSeconds').hide();
      } else {
        setSelectValue('#timeoutStrategy', 'noActivity');
        $('#timeoutSeconds').val(firstXmlText(xmlDoc, 'timeoutSecondsString', timeoutWrapper)).prop('required', true);
        $('.timeoutSeconds').show();
        $('.timeoutMinutes').hide();
      }
    }

    function hydrateJobFormFromXml(jobName, xmlText) {
      var xmlDoc = $.parseXML(xmlText);

      resetJobCreationForm();
      $('#job_name').val(jobName);
      $('#description').val(firstXmlText(xmlDoc, 'description'));
      hydrateSchedule(xmlDoc);
      hydrateBuilders(xmlDoc, jobName);
      hydratePublishers(xmlDoc);
      hydrateBuildWrappers(xmlDoc);
      showEditBanner(jobName);

      toastr.info('Loaded ' + jobName + ' for editing.', 'Edit Job');
      $('html, body').animate({ scrollTop: $('#InsertDbSettings').offset().top - 70 }, 300);
    }

    function loadJobForEdit(jobName) {
      setSaveJobState(true, 'Loading job...');
      $('.overlay').fadeIn();

      $.ajax({
        url: jenkins_url + jenkinsJobPath(jobName) + '/config.xml',
        method: 'GET',
        dataType: 'text',
        headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
      }).done(function(xmlText) {
        try {
          hydrateJobFormFromXml(jobName, xmlText);
        } catch (error) {
          console.error(error);
          toastr.error('Unable to read this job configuration.', 'Edit Job');
        }
      }).fail(function() {
        console.error(arguments);
        toastr.error('Unable to load this job from Jenkins.', 'Edit Job');
      }).always(function() {
        $('.overlay').fadeOut();
        setSaveJobState(false, '');
      });
    }

    $('#clearEditJob').click(function() {
      resetJobCreationForm();
      toastr.info('Ready to create a new job.', 'New Job');
    });

    $('#myTable').on('click', '.editJob', function() {
      loadJobForEdit($(this).data('job'));
    });

     // Logic for editable email notification

     $('#editableEmailCheck').click(function(){
      if($(this).is(":checked")){
      $('#editableEmail').fadeIn();
      $('.fetchEmail option').remove();
      $('.fetchEmail').append($('<option>', {
                value: 0,
                text: "Please, select an option"
                }))

      $.ajax({    //create an ajax request
        type: "GET",
        url: "<?php echo base_url(); ?>EmailSettings/fetchall/name",             
        dataType: "html",    
        beforeSend: function(){
          $('.overlay').fadeIn();
        },
        success: function(data){  
          var json = JSON.parse(data);  

           $.each(json["data"], function(i, item) {
            var newJson = (json["data"][i].name);

            $('.fetchEmail').append($('<option>', {
                value: newJson,
                text: newJson
                }))
             })
           $('.overlay').fadeOut();
        },
        error: function(arguments){
          toastr.error('Fail to fetch email template data' + arguments, 'Error to Fech Data')
          $('.overlay').fadeOut();
        }

    });

      } 
        else if($(this).is(":not(:checked)")){
          $('#editableEmail').fadeOut();
        }
      }); 


     // Logic for run another job after this build function
     $('#runJobCheck').click(function(){
      if($(this).is(":checked")){
       $('#jobList option').remove();
       $.ajax({
        url: jenkins_url + 'api/json?tree=jobs[name,builds[number,actions[parameters[name,value]]]]&pretty=true',
        method: 'GET',
        headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
        beforeSend: function() {

          $('#overlay').fadeIn();
        }
      }).done(function(data) {

       $.each(data["jobs"], function (key, item) {
         newJson = item.name;
         $('#jobList').append($('<option>', {
          value: newJson,
          text: newJson
        }))
       });

       $('#overlay').fadeOut();

     }).fail(function() {
      console.error(arguments);
      toastr.error("Failed to fetch available jobs from server", "Fail to fetch jobs")
    });

     $('#runJob').fadeIn();
   } 
   else if($(this).is(":not(:checked)")){
    $('#runJob').fadeOut();
  }
});


     // Logic for enable email notification
     $('#emailCheck').click(function(){
      if($(this).is(":checked")){
        $('#enableEmail').fadeIn();

      } 
      else if($(this).is(":not(:checked)")){
        $('#enableEmail').fadeOut();
      }
    });



    // Logic for Execute a Windows Command or script
    $('#winCommand').click(function(){ // If checkbox is checked
      if($(this).is(":checked")){

        // Show Windows command Div
        $('#runWinCommand').fadeIn();

       //Windows Execution Strategy area script
       $('#executionStrategy').change(function(){
        var val = $('#executionStrategy').val();

        // If the option is to execute windows command line
        if(val == 'command' && val != 0){
          $('.scriptTypeForm').fadeOut();
          $('.destroyDropzone').remove();
          $("#scriptType").val(0);
          $('.windowsCommandForm').fadeIn();

        // If the option is to execute an script
      } else if(val == 'script' && val != 0) {
        $('.scriptTypeForm').fadeIn();
        $('.windowsCommandForm').fadeOut();

          // Windows Script Execution 
          $('#scriptType').change(function(){
            var val = $('#scriptType').val();
            var job_name = $('#job_name').val();

            if (val != 0) {
              job_name = ensureJobName();

                if(job_name != '' && job_name != null){
                  var acceptedFiles = val == 'python' ? ".py,.zip" : ".zip";
                  var uploadMessage = val == 'python' ? "Drop Python .py or .zip files here or click to upload." : "Drop zip files here or click to upload.";
                  $('.uploadScript').show();
                  $('.destroyDropzone').remove();
                  $('#windowsColumn').append($('<DIV id="dropzone" class="destroyDropzone"><form class="dropzone needsclick" id="mydropzone" action="<?php echo base_url(); ?>upload/do_upload" enctype="multipart/form-data" method="post" style="height: 220px;"><DIV class="dz-message needsclick"><img src="<?php echo base_url(); ?>assets/images/bi.png" alt="cloud" style="height: 100px; width: 100px;"><h3><b>' + uploadMessage + '</b></h3><BR></DIV></form></DIV>'));

                  $("#mydropzone").dropzone({
                    maxFiles: 1,
                    acceptedFiles: acceptedFiles,
                    url: "<?php echo base_url(); ?>jobCreation/do_upload/" + encodeURIComponent(val) + "/" + encodeURIComponent(job_name),
                    maxFilesize: 100,
                    sending: function () {
                      toastr.info("Uploading File, please wait the file get uploaded", "File Uploading")
                      $(".buildXmlBtn").prop('disabled', true);
                    },
                    success: function(file, response) {
                      console.log(file)
                      console.log(response)
                      toastr.success("Your file has been succesfully uploaded and unziped, now you are able to build the xml in order to set the job to execute your zip file content.", "File Upload Success")
                      $(".buildXmlBtn").prop('disabled', false);
                    },
                    error: function(file, response) {
                      console.log(file)
                      console.log(response)
                      toastr.error("Erro during uploading file.", "File Upload Error")
                      $(".buildXmlBtn").prop('disabled', false);
                    }


                  });
                } else {
                  toastr.error("Please Select a job name to upload the file", "File Upload Error")
                  $("#scriptType").val(0);
                }

            } else {
              $('.uploadScript').fadeOut();
              $('.destroyDropzone').remove();
            }

          });

        } else if(val == 0){
          $('.windowsCommandForm').fadeOut();
          $('.scriptTypeForm').fadeOut();
        }

      });

     } 
      else if($(this).is(":not(:checked)")){ // If checkbox is NOT checked

        // Hide Windows Command Div
        $('#runWinCommand').fadeOut();
        
      }
    });


    // Linux Command / Script execution function
    $('#linuxCommand').click(function(){
      if($(this).is(":checked")){

        // Show Linux Command div
        $('#runlinuxCommand').fadeIn();

          //Linux Execution Strategy area script
          $('#linuxExecutionStrategy').change(function(){
            var val = $('#linuxExecutionStrategy').val();

            // If the option is to execute a linux command then
            if(val == 'command' && val != 0){
              $('.linuxScriptTypeForm').fadeOut();
              $('.destroyDropzone').remove();
              $('.linuxCommandForm').fadeIn();
              $("#linuxScriptType").val(0);
              updatePythonSourceControls();

            // If the option is to execute a linux script then  
          } else if(val == 'script' && val != 0) {
            $('.linuxScriptTypeForm').fadeIn();
            $('.linuxCommandForm').fadeOut();

              // Linux Command execution script
              $('#linuxScriptType').change(function(){
                var val = $('#linuxScriptType').val();
                var job_name = $('#job_name').val();
                console.log(job_name);
                updatePythonSourceControls();

                if (val != 0) {
                  job_name = ensureJobName();

                    if(job_name != '' && job_name != null){
                      if (val == 'python' && $('#pythonSourceMode').val() != 'upload') {
                        $('.linuxUploadScript').fadeOut();
                        $('.destroyDropzone').remove();
                        return;
                      }

                      var acceptedFiles = val == 'python' ? ".py,.zip" : ".zip";
                      var uploadMessage = val == 'python' ? "Drop Python .py or .zip files here or click to upload." : "Drop zip files here or click to upload.";
                      $('.linuxUploadScript').show();
                      $('.destroyDropzone').remove();
                      $('#linuxColumn').append($('<DIV id="dropzone" class="destroyDropzone"><form class="dropzone needsclick" id="mydropzone" action="<?php echo base_url(); ?>upload/do_upload" enctype="multipart/form-data" method="post" style="height: 220px;"><DIV class="dz-message needsclick"><img src="<?php echo base_url(); ?>assets/images/bi.png" alt="cloud" style="height: 100px; width: 100px;"><h3><b>' + uploadMessage + '</b></h3><BR></DIV></form></DIV>'));

                      $("#mydropzone").dropzone({
                        maxFiles: 1,
                        acceptedFiles: acceptedFiles,
                        url: "<?php echo base_url(); ?>jobCreation/do_upload/" + encodeURIComponent(val) + "/" + encodeURIComponent(job_name),
                        maxFilesize: 100,
                        sending: function () {
                          toastr.info("Uploading File, please wait the file get uploaded", "File Uploading")
                          $(".buildXmlBtn").prop('disabled', true);
                        },
                        success: function(file, response) {
                          console.log(file)
                          console.log(response)
                          toastr.success("Your file has been succesfully uploaded and unziped, now you are able to build the xml in order to set the job to execute your zip file content.", "File Upload Success")
                          $(".buildXmlBtn").prop('disabled', false);
                        },
                        error: function(file, response) {
                          console.log(file)
                          console.log(response)
                          toastr.error("Erro during uploading file.", "File Upload Error")
                          $(".buildXmlBtn").prop('disabled', false);
                        }


                      });
                    } else {
                      toastr.error("Please Select a job name to upload the file", "File Upload Error");
                      $("#linuxScriptType").val(0);
                    }

                } else {
                  $('.linuxUploadScript').fadeOut();
                  $('.destroyDropzone').remove();
                }

              });

            // If the option is nothing then   
          } else if(val == 0){
            $('.linuxScriptTypeForm').fadeOut();
            $('.linuxCommandForm').fadeOut();
            updatePythonSourceControls();
          }
        });

        } 
        else if($(this).is(":not(:checked)")){

          // Hide Linux Command div
          $('#runlinuxCommand').fadeOut();

        }
      });


    $('#checkBuild').click(function(){
      if($(this).is(":checked")){

        $('#build').fadeIn();

        $('#action').change(function(){
          var val = $('#action').val();
          console.log(val)
          if (val == 'single') {
            $('.tags').fadeOut();
            $('.repetitive').fadeOut();
            $('.singleForm').fadeIn();

            $('#send').hover(function(){
              var val = $('#action').val();
              var singleMinute = $('#singleMinute').val();
              var singleHour = $('#singleHour').val();
              var singleDayOfMonth = $('#singleDayOfMonth').val();
              var singleMonth = $('#singleMonth').val();
              var singleDayOfWeek = $('#singleDayOfWeek').val();
              var action = $('#action').val();

              if(!singleEveryMinuteAcknowledged && action != 0 && val == 'single') {
                if (singleMinute == '*' && singleHour == '*' && singleDayOfMonth == '*' && singleMonth == '*' && singleDayOfWeek == '*' && val == 'single' && $("#checkBuild").is(":checked")){
                  alertify.confirm('Allow job execution every minute','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you totally sure you need to execute this job every single minute ?</b></p><p>This option might be dangerous and request big efforts from server.</p></div></div></div>', 
                    function(){ 
                     alertify.success('You has agreeded with your choice, be careful !');
                     singleEveryMinuteAcknowledged = true;
                   }, 
                   function(){ 
                    alertify.error('Operation Aborted');
                    singleEveryMinuteAcknowledged = false;
                  }
                  );
                }
              }
            });
            
          } else  if (val == 'repetitive'){
            $('.repetitive').fadeIn();
            $('.singleForm').fadeOut();
            $('.tags').fadeOut();

            $('#send').hover(function(){
              var val = $('#action').val();
              var repetitiveMinute = $('#repetitiveMinute').val();
              var repetitiveHour = $('#repetitiveHour').val();
              var repetitiveDayOfMonth = $('#repetitiveDayOfMonth').val();
              var repetitiveMonth = $('#repetitiveMonth').val();
              var repetitiveDayOfWeek = $('#repetitiveDayOfWeek').val();
              var action = $('#action').val();

              if(!repetitiveEveryMinuteAcknowledged && action != 0 && val == 'repetitive') {
                if (repetitiveMinute == '*' && repetitiveHour == '*' && repetitiveDayOfMonth == '*' && repetitiveMonth == '*' && repetitiveDayOfWeek == '*' && val == 'repetitive' && $("#checkBuild").is(":checked")){
                  alertify.confirm('Allow job execution every minute','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you totally sure you need to execute this job every single minute ?</b></p><p>This option might be dangerous and request big efforts from server.</p></div></div></div>', 
                    function(){ 
                     alertify.success('You has agreeded with your choice, be careful !');
                     repetitiveEveryMinuteAcknowledged = true;
                   }, 
                   function(){ 
                    alertify.error('Operation Aborted');
                    repetitiveEveryMinuteAcknowledged = false;
                  }
                  );
                }
              }
            });


          } else if (val == 'tags'){
            $('.tags').fadeIn();
            $('.singleForm').fadeOut();
            $('.repetitive').fadeOut();
          } else if( val == 0 ){
            $('.singleForm').fadeOut();
            $('.repetitive').fadeOut();
            $('.tags').fadeOut();
          }
        }); 

      }
      else if($(this).is(":not(:checked)")){
        $('#build').fadeOut();
      }
    });

$('#abort').click(function(){
  function updateTimeoutRequiredFields() {
    var isAbsolute = $('#timeoutStrategy').val() == 'absolute';
    $('#timeoutMinutes').prop('required', isAbsolute);
    $('#timeoutSeconds').prop('required', !isAbsolute);
  }

  if($(this).is(":checked")){

    $('#abortIfStuck').fadeIn();
    updateTimeoutRequiredFields();

    $('#timeoutStrategy').change(function(){
      var val = $('#timeoutStrategy').val();
      console.log(val)
      if (val == 'absolute') {
        $('.timeoutSeconds').fadeOut();
        $('.timeoutMinutes').fadeIn();
      } else {
        $('.timeoutSeconds').fadeIn();
        $('.timeoutMinutes').fadeOut();
      }
      updateTimeoutRequiredFields();
    });

  }
  else if($(this).is(":not(:checked)")){
    $('#abortIfStuck').fadeOut();
    $('#timeoutMinutes').prop('required', false);
    $('#timeoutSeconds').prop('required', false);
  }
});

$('#checkEnvironment').click(function(){
  if($(this).is(":checked")){

    $('#environmentBox').fadeIn();
    $("#environment").prop('required',true);
    loadEnvironmentOptions($('#environment').val() != '0' ? $('#environment').val() : '');

  }
  else if($(this).is(":not(:checked)")){
    $('#environmentBox').fadeOut();
  }
});


  function jenkinsJobPath(jobName) {
    return 'job/' + encodeURIComponent(jobName);
  }

  function setSaveJobState(isSaving, message) {
    $('.buildXmlBtn').prop('disabled', isSaving);
    $('.saveJobStatus').text(message || '').toggle(!!message);
  }

  function refreshJobTable() {
    if ($.fn.DataTable.isDataTable('#myTable')) {
      $('#myTable').DataTable().ajax.reload(null, false);
    } else {
      loadTable();
    }
    $('#box').boxWidget('expand');
  }

  function saveJenkinsConfig(jobName, xml, isUpdate) {
    return $.ajax({
      url: isUpdate ? jenkins_url + jenkinsJobPath(jobName) + '/config.xml' : jenkins_url + 'createItem?name=' + encodeURIComponent(jobName),
      data: xml,
      method: 'POST',
      contentType: 'text/xml',
      dataType: 'text',
      headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
    });
  }

  function triggerJenkinsJob(jobName) {
    return $.ajax({
      url: jenkins_url + jenkinsJobPath(jobName) + '/build',
      method: 'POST',
      dataType: 'text',
      headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
    });
  }

  function completeGeneratedJobSave(jobName, isUpdate, triggerAfterSave) {
    toastr.success('Your job has been successfully ' + (isUpdate ? 'updated' : 'created') + '.', isUpdate ? 'Job Updated' : 'Job Created');
    refreshJobTable();
    $('.generatedXmlPanel').remove();

    if (!triggerAfterSave) {
      $('.overlay').fadeOut();
      setSaveJobState(false, '');
      return;
    }

    setSaveJobState(true, 'Triggering job...');
    triggerJenkinsJob(jobName).done(function() {
      toastr.success('Your job has been triggered.', 'Job Triggered');
    }).fail(function() {
      console.error(arguments);
      toastr.error('The job was saved, but the trigger request failed.', 'Trigger Error');
    }).always(function() {
      $('.overlay').fadeOut();
      setSaveJobState(false, '');
    });
  }

  function saveGeneratedJob() {
    var jobName = <?php echo json_encode($this->session->flashdata('job_name')); ?>;
    var triggerAfterSave = <?php echo json_encode($this->session->flashdata('trigger_after_save') == '1'); ?>;
    var xml = $('#xml').text();

    if (!jobName || !xml) {
      return;
    }

    setSaveJobState(true, 'Saving job...');
    $('.overlay').fadeIn();

    $.ajax({
      url: jenkins_url + jenkinsJobPath(jobName) + '/api/json',
      method: 'GET',
      dataType: 'json',
      headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)}
    }).done(function() {
      saveJenkinsConfig(jobName, xml, true).done(function() {
        completeGeneratedJobSave(jobName, true, triggerAfterSave);
      }).fail(function() {
        console.error(arguments);
        toastr.error('Your Job Update Request Has Failed', 'Request Error');
        $('.overlay').fadeOut();
        setSaveJobState(false, '');
      });
    }).fail(function(response) {
      if (response.status == 404) {
        saveJenkinsConfig(jobName, xml, false).done(function() {
          completeGeneratedJobSave(jobName, false, triggerAfterSave);
        }).fail(function() {
          console.error(arguments);
          toastr.error('Your Job Creation Request Has Failed', 'Request Error');
          $('.overlay').fadeOut();
          setSaveJobState(false, '');
        });
        return;
      }

      $('.overlay').fadeOut();
      setSaveJobState(false, '');
      console.error(arguments);
      toastr.error('Unable to check whether this job already exists.', 'Request Error');
    });
  }

  saveGeneratedJob();

  Dropzone.autoDiscover = false;

function loadTable () {
     $(".overlay").show();
        $("#myTable").dataTable().fnDestroy();
        $('#myTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000,2000,5000],
          "pageLength": 5,
          "order": [[ 0, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "color"},
          {"data": "name"},
          {"data": "url"},
          {"data": "name"}
          ],
          columnDefs:[{targets:0, render:function(data){
            if(data != null){
              if(data == 'red'){
                return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/bad.png">';
              } else if (data == 'blue') {
                return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/good.png">';
              } else  if (data == 'notbuilt'){
                return '<b>Never Built</b>';
              } else {
                 return '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/loading.gif">';
              }
            } else {return ''}
          }}, {targets:3, orderable:false, searchable:false, render:function(data){
            return '<button type="button" class="btn btn-info btn-xs editJob" data-job="' + escapeAttribute(data) + '"><i class="fa fa-pencil"></i> Edit</button>';
          }}]
       });
  $(".overlay").hide();  
}  

setTimeout(function(){ loadTable() }, 1000);

 
});

</script>

