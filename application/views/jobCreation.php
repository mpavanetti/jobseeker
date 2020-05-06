<link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/bower_components/select2/dist/css/select2.min.css">
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

    <div class="row" style="margin-top: 10px; margin-bottom: 40px;">
     <div class="col-lg-12 col-md-12 col-xs-12">
      <div class="text-center">
        <h3>Statistic Content</h3>
      </div>
    </div>
  </div>

  <div class="row" style="margin-top: 10px; margin-bottom: 40px;">
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


<div class="row">
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
        <h3 class="box-title"><b>Job Input Fields</b></h3> 
      </div>

      <!-- /.box-header -->
      <!-- form start -->
      <?php $this->load->helper("form"); ?>
      <form role="form" id="InsertDbSettings" action="<?php echo base_url() ?>jobCreation/send" method="post" role="form">
        <div class="box-body">
          <div class="form-group">
            <label for="exampleInputEmail1">Job Name</label>
            <input type="text" name ="job_name" class="form-control" id="job_name" placeholder="Enter Job name">
          </div>
          <div class="form-group">
            <div class="form-group">
              <label for="description">Description</label>
              <textarea class="form-control" id="description" value="" name="description" maxlength="500" rows="5" required></textarea>
            </div>
          </div>
          <div class="checkbox">
            <label>
              <input type="checkbox" name="confirmation" id="confirmation" value="1"> I <b>Confirm</b> this job build is my responsability and has my confidence.
            </label>
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
          <h3 class="box-title"><b>Options Control Panel</b></h3> 
        </div>
        <!-- /.box-header -->
        <!-- form start -->
        <div class="box-body">
          <div class="checkbox">
            <label>
              <input type="checkbox"> Trigger Job
            </label>
          </div>
          <div class="checkbox">
            <label>
              <input type="checkbox" name="checkBuild" id="checkBuild" value="1"> Build Periodically
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
          <div class="form-group">
            <div class="form-group">
              <div class="btn-group">
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Add Job Function
                  <span class="caret"></span>
                  <span class="sr-only">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu" role="menu">
                  <li><a href="#">Execute a Windows Command</a></li>
                  <li><a href="#">Execute a Linux Command</a></li>
                </ul>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="form-group">
              <div class="btn-group">
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Actions After Job Execution
                  <span class="caret"></span>
                  <span class="sr-only">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu" role="menu">
                  <li><a href="#">Execute another job</a></li>
                  <li><a href="#">Email Notification</a></li>
                  <li class="divider"></li>
                  <li><a href="#">Editable Email Notification</a></li>
                </ul>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="form-group">
              <button type="submit" id="send" href="#" class="btn btn-warning"> Build XML</button>
              <?php  
              $xml = $this->session->flashdata('xml');
              if($xml)
              {
                ?>
                <a href="#" class="btn btn-success send"><i class="fa fa-save"></i> Send Job</a>
              <?php } ?>
            </div>
          </div>
        </div>
        <!-- /.box-body -->
      </div>
    </div>

  </div>

  <div class="row">

    <div id="build" style="display: none;">
      <div class="col-lg-6 col-md-6 col-xs-12 removeBuild">
        <div class="box box-primary">
          <div class="box-header with-border">
            <div class="box-tools pull-right">
              <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
            </div>
            <h3 class="box-title">
              <b>Build Job Periodically</b></h3>
            </div>
            <div class="box-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="timeoutStrategy">Trigger Action</label>
                    <select class="form-control" id="action" name="action">
                      <option value="0">-- Select an action -- </option>
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
                          for ($i=0; $i < 13; $i++) { 
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
                          for ($i=0; $i < 13; $i++) { 
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


       <div id="abortIfStuck" style="display: none;">
      <div class="col-lg-6 col-md-6 col-xs-12 removeAbort">
        <div class="box box-primary">
          <div class="box-header with-border">
            <div class="box-tools pull-right">
              <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
            </div>
            <h3 class="box-title">
              <b>Abort the job if its stuck option</b></h3>
            </div><div class="box-body">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="timeoutStrategy">Timeout Stragegy</label>
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
    </form>
  </div>
  <div id="output"></div>

  <div class="row">
    <div class="col-lg-12 col-md-12 col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
            </button>
          </div>
          <h3 class="box-title"><b>XML Document</b></h3> 
        </div>
        <div class="box-body">
          <div>
           <?php  
           $xml = $this->session->flashdata('xml');
           if($xml)
           {
            ?>
            <div>
              <pre id="xml" class="xml"><?php echo $this->session->flashdata('xml'); ?> </pre>
            </div>
          <?php } ?>
        </div>
        <div class="overlay" style="display:none;">
          <i class="fa fa-refresh fa-spin"></i>
        </div>
      </div>
    </div>
  </div>
</div>

</section>
</div>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/select2/dist/js/select2.min.js"></script>
<script type="text/javascript">
  $(document).ready(function(){

    $('.select2').select2({
       placeholder: " Click to Select a option to fetch",
       allowClear: true
    });

    var addUserForm = $("#InsertDbSettings");
    var validator = addUserForm.validate({

      rules:{
        job_name :{ required : true },
        confirmation :{ required : true },
        description :{ required : true },
        timeoutMinutes :{ required : true }
      },
      messages:{
        job_name :{ required : "This field is required" },
        confirmation :{ required : "This field is required" },        
        description :{ required : "This field is required" }, 
        timeoutMinutes :{ required : "This field is required" }          
      }
    });



$('#checkBuild').click(function(){
      if($(this).is(":checked")){

        $('#build').show();

        $('#action').change(function(){
          var val = $('#action').val();
          console.log(val)
          if (val == 'single') {
            $('.tags').hide();
            $('.repetitive').hide();
            $('.singleForm').show();

            $('#send').hover(function(){
                var singleMinute = $('#singleMinute').val();
                var singleHour = $('#singleHour').val();
                var singleDayOfMonth = $('#singleDayOfMonth').val();
                var singleMonth = $('#singleMonth').val();
                var singleDayOfWeek = $('#singleDayOfWeek').val();

                if($('#confirmation').is(":not(:checked)")) {
                if (singleMinute == '*' && singleHour == '*' && singleDayOfMonth == '*' && singleMonth == '*' && singleDayOfWeek == '*'){
                  alertify.confirm('Allow job execution every minute','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you totally sure you need to execute this job every single minute ?</b></p><p>This option might be dangerous and request big efforts from server.</p></div></div></div>', 
                  function(){ 
                   alertify.success('You has agreeded with your choice, be careful !');
                   $("#confirmation"). prop("checked", true);
                }, 
                  function(){ 
                    alertify.error('Operation Aborted');
                    $("#confirmation"). prop("checked", false);
                }
              );
            }
                }
              });


          } else  if (val == 'repetitive'){
            $('.repetitive').show();
            $('.singleForm').hide();
            $('.tags').hide();
          } else if (val == 'tags'){
            $('.tags').show();
            $('.singleForm').hide();
            $('.repetitive').hide();
          }
        }); 

      }
      else if($(this).is(":not(:checked)")){
        $('#build').hide();
      }
    });

    $('#abort').click(function(){
      if($(this).is(":checked")){

        $('#abortIfStuck').show();
        $("#timeoutMinutes").prop('required',true);

        $('#timeoutStrategy').change(function(){
          var val = $('#timeoutStrategy').val();
          console.log(val)
          if (val == 'absolute') {
            $('.timeoutSeconds').hide();
            $('.timeoutMinutes').show();
          } else {
            $('.timeoutSeconds').show();
            $('.timeoutMinutes').hide();
          }
        });
     
      }
      else if($(this).is(":not(:checked)")){
        $('#abortIfStuck').hide();
      }
    });


  // Starts when click on build job button
  $('.send').click(function() {
    // Check if the confirmation checkbox is marked
    if($('#confirmation').is(":checked")){ // confirmation is maked
     var job_name = "<?php echo $this->session->flashdata('job_name'); ?>";
     console.log(job_name);
     var xml = $('#xml').text();


        // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

        //Ajax request to post the xml to jenkins api
        $.ajax({
          url: jenkins_url + 'createItem?name='+ job_name ,
          data: xml, 
          method: 'POST',
          contentType: "text/xml",
          dataType: "text",
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {
            $('.overlay').show();

            $('#input-form').each (function(){
              this.reset();
            });
          }
        }).done(function(data) {
          $('.overlay').hide();
          
          toastr.success("Your Execution Request has been sent to server.", "Request Sent")
        }).fail(function() {
          $('.overlay').hide();
          console.error(arguments);
          toastr.error("Your Job Creation Request Has Been Failed", "Request Error")
        });

        $('.send').remove();
        $('.xml').remove();
        $('.destroy').remove();
        $("#confirmation"). prop("checked", false);
        $("#checkBuild"). prop("checked", false);



    // Confirmation box is not checked
  } else if($('#confirmation').is(":not(:checked)")){ // confirmation is not marked
    toastr.error("Checkbox is unchecked.");
  }

  
});  


});
</script>

