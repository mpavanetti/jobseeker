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
        <form role="form" id="input-form">
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
                <input type="checkbox" id="confirmation"> I <b>Confirm</b> this job build is my responsability and has my confidence.
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
                <input type="checkbox"> Build Periodicly
              </label>
            </div>
            <div class="checkbox">
              <label>
                <input type="checkbox"> Abort the job if it's stuck
              </label>
            </div>
            <div class="checkbox">
              <label>
                <input type="checkbox" name="timestamp" id="timestamp"> Add timestamps to the Console Output
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
                <a href="#" class="btn btn-success send"><i class="fa fa-save"></i> Build Job</a>
              </div>
            </div>
          </div>
          <!-- /.box-body -->
        </form>
      </div>
    </div>
  </div>

  <div id="output"></div>

</section>
</div>

<script type="text/javascript">
  $(document).ready(function(){


  // Starts when click on build job button
  $('.send').click(function() {
    // Check if the confirmation checkbox is marked
    if($('#confirmation').is(":checked")){ // confirmation is maked
     var job_name = $('#job_name').val();
     var description = $('#description').val();


        $.createElement = function(name)
            {
              return $('<'+name+' />');
            };

            $.fn.appendNewElement = function(name)
            {
              this.each(function(i)
              {
                $(this).append('<'+name+' />');
              });
              return this;
            }

                var $root = $('<XMLDocument />');

                $root.append
                (
            // one method of adding a basic structure
            $('<project />').append( // Create the root node
              $('<actions/>').append(), // Append Actions Node
              $('<description />').text(description), // Add Description node
              $('<keepDependencies />').text("false"), // Add KeepDependencies Node
              $('<properties/>').append(), // Add Properties Node
              $('<scm class="hudson.scm.NullSCM"/>').append(), //Add Scm node
              $('<canRoam />').text("true"), // Add canRoam Node
              $('<disabled />').text("false"), // Add Disabled Node
              $('<blockBuildWhenDownstreamBuilding />').text("false"),
              $('<blockBuildWhenUpstreamBuilding />').text("false"),
              $('<triggers/>').append(), // Append Triggers Node
              $('<concurrentBuild />').text("false"),
              $('<builders/>').append(), // Append Builders Node
              $('<publishers/>').append(), // Append publishers
              $('<buildWrappers />').append(
                $('<hudson.plugins.timestamper.TimestamperBuildWrapper plugin="timestamper@1.10"/>').append()
              )


             )
            );

              var data = $root.html();
                console.log(data);

     //Validate fields Message
     if (job_name == ''){
      toastr.error("The Job Name field is Null, please fill it", "Error");
    } else if (description == '') {
      toastr.error("The Description field is Null, please fill it", "Error");
    }

    // If Validation is ok
    if (job_name != '' && description != '') {


        // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

        //Ajax request to post the xml to jenkins api
        $.ajax({
          url: jenkins_url + 'createItem?name='+ job_name ,
          data: data, 
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
          toastr.error("Your Job Creation Request Has Been Failed, it seems this job already exist", "Request Error")
        });

        console.log("Checkbox is checked.");

      }


    }

    // Confirmation box is not checked
   else if($('#confirmation').is(":not(:checked)")){ // confirmation is not marked
    toastr.error("Checkbox is unchecked.");
  }

});  






  function generateXML(){

    $.createElement = function(name)
    {
      return $('<'+name+' />');
    };

    $.fn.appendNewElement = function(name)
    {
      this.each(function(i)
      {
        $(this).append('<'+name+' />');
      });
      return this;
    }

        var $root = $('<XMLDocument />');

        $root.append
        (
    // one method of adding a basic structure
    $('<project />').append(
      $('<description />').text('Teste Matheus 123456 Teste')
      )
    );

        console.log($root.html());

      }
      generateXML();

    });
  </script>

