<style type="text/css">
  pre { 
    white-space: pre-wrap; 
    word-break: break-word;
    max-width: 750px;
}
</style>

<div class="content-wrapper">    
  <section class="content-header">
    <h1>
      View Job
      <small>Get further information about the job.</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="#">Job Management</a></li>
      <li class="active">View Job</li>
    </ol>
  </section>
  <section class="content">
    <div class="container">

    <div class="row" style="margin-top: 10px;">
     <div class="col-lg-6 col-md-6 col-xs-12">
      <div class="box box-warning">
        <div class="box-header with-border">
          <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
            </button>
          </div>
          <h3 class="box-title"><b>Job Selector</b></h3> 
        </div>
        <div class="box-body" style="padding: 20px;">
         <div class="form-group">
          <select id="selector" class="form-control">
            <option value="0">Please, Select an avaiable Job to execute.</option>
          </select>
        </div>
      </div>
      <div class="overlay" style="display:none;">
        <i class="fa fa-refresh fa-spin"></i>
      </div>
    </div>
  </div>

  <div class="col-lg-6 col-md-6 col-xs-12">
    <div class="box box-warning">
      <div class="box-header with-border">
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
          </button>
        </div>
        <h3 class="box-title"><b>Menu</b></h3> 
      </div>
      <div class="box-body text-center">
        <a class="btn btn-app" id="view">
          <i class="fa fa-eye"></i> View
        </a>
        <a class="btn btn-app" id="clean" style="display: none;">
          <i class="fa fa-times"></i> Clean
        </a>
      </div>
      <div id="overlay2" class="overlay" style="display:none;">
        <i class="fa fa-refresh fa-spin"></i>
      </div>
    </div>
  </div>

</div>



<div class="modal fade" id="modal-default" style="display: none;">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">×</span></button>
          <h4 class="modal-title"><b>Job Description</b></h4>
        </div>
        <div class="modal-body">
          <div id="load">

          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary pull-left" data-dismiss="modal">Close</button>
        </div>
      </div>
      <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
  </div>
</div>
</section>
</div>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/moment/moment.min.js"></script>
<script type="text/javascript">
  $(document).ready(function(){

    var delay = 15000;
    var errorDelay = 10000;

        // get Jenkins credentials
        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

        $.ajax({
          url: jenkins_url + 'api/json?tree=jobs[name,builds[number,actions[parameters[name,value]]]]&pretty=true',
          method: 'GET',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {

            $('.overlay').show();
            $('#overlay2').show();
          }
        }).done(function(data) {

         $.each(data["jobs"], function (key, item) {
          newJson = item.name;
          $('#selector').append($('<option>', {
            value: newJson,
            text: newJson
          }))
        });

         $('.overlay').hide();

       }).fail(function() {
        console.error(arguments);
      });

       $('#view').click(function(){
        var job = $('#selector').val();
        $('.destroy').remove();

        if(job == '0'){
          toastr.error("Please, Select an avaiable job to view.", "Error");
        } else {
         $('.overlay').show();
         $.ajax({
          url: jenkins_url + 'job/'+ job +'/api/json',
          method: 'GET',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {

            $('.overlay').show();
            $('#gif').fadeIn();
            $('#play').hide();
            
          }
        }).done(function(data) {
         console.log(data);
  
         if(Array.isArray(data.healthReport) && data.healthReport.length){
          healthReport = data.healthReport[0].description;
         } else {
          healthReport = "<b style='color:red;'>None</b>";
         }

        if(data.lastStableBuild == null) {
          lastStableBuild = "<b style='color:red;'>None</b>";
        } else {
          lastStableBuild = data.lastStableBuild.number;
        }

        if(data.lastBuild == null) {
          lastBuild = "<b style='color:red;'>None</b>";
        } else {
          lastBuild = data.lastBuild.number
        }

        if(data.lastCompletedBuild == null) {
          lastCompletedBuild = "<b style='color:red;'>None</b>";
        } else {
          lastCompletedBuild = data.lastCompletedBuild.number
        }

        if(data.lastUnstableBuild == null) {
          lastUnstableBuild = "<b style='color:red;'>None</b>";
        } else {
          lastUnstableBuild = data.lastUnstableBuild.number
        }

        if(data.lastSuccessfulBuild == null) {
          lastSuccessfulBuild = "<b style='color:red;'>None</b>";
        } else {
          lastSuccessfulBuild = data.lastSuccessfulBuild.number
        }

        if(data.lastUnsuccessfulBuild == null) {
          lastUnsuccessfulBuild = "<b style='color:red;'>None</b>";
        } else {
          lastUnsuccessfulBuild = data.lastUnsuccessfulBuild.number
        }

        if(data.color != null){
              if(data.color == 'red'){
                situation = '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/bad.png">';
              } else if (data.color == 'blue') {
                situation =  '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/good.png">';
              } else  if (data.color == 'notbuilt'){
                situation =  '<b>Never Built</b>';
              } else {
                situation =  '<img class="img img-responsive" width="32" height="32" src="<?php echo base_url(); ?>assets/images/items/loading.gif">';
              }
            } else {
              situation = "<b style='color:red;'>None</b>"
            }


          if(lastBuild != "<b style='color:red;'>None</b>") {
            var log = $.ajax({
              contentType: "application/text",
              url: jenkins_url + 'job/'+ data.fullName +'/lastBuild/consoleText',
              method: 'GET',
              headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
              async: false,
              beforeSend: function() {
               $(".overlay").show();
               $(".destroy").remove();

              },
              error: function() {
                 toastr.error("Error During query error list data \n Id: " + id, "Query Data Error");
              },

              success: function() {
              },
              complete: function(data) {
                  dateRequest = data;
                  $(".overlay").hide();
              }

           });
            output = log.responseText; 
         } else {
          output ="<b style='color:red;'>None</b>";
         }


         var xml = $.ajax({
              contentType: "application/text",
              url: jenkins_url + 'job/'+ data.fullName +'/config.xml',
              method: 'GET',
              headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
              async: false,
              beforeSend: function() {
               $(".overlay").show();

              },
              error: function() {
                 toastr.error("Error During query error list data \n Id: " + id, "Query Data Error");
              },

              success: function() {
              },
              complete: function(data) {
                  dateRequest = data;
                  $(".overlay").hide();
              }

           });
            showXml = jQuery.parseXML(xml.responseText); 

            var command = showXml.getElementsByTagName("command")[0]
            if(command != undefined) {
               readCommand = command.childNodes[0].textContent;
            } else {
              readCommand ="<b style='color:red;'>None</b>";
            }

            var trigger = showXml.getElementsByTagName("spec")[0]
            if(trigger != undefined) {
               readtrigger = trigger.childNodes[0].textContent;

                str = readtrigger.charAt(0);
                if(str == '@') {
                 var tag = readtrigger;
                 split = [];
                  split[0]  = "<b style='color:red;'>None</b>"; 
                  split[1]  = "<b style='color:red;'>None</b>"; 
                  split[2]  = "<b style='color:red;'>None</b>"; 
                  split[3]  = "<b style='color:red;'>None</b>"; 
                  split[4]  = "<b style='color:red;'>None</b>"; 
                } else {
                 var  tag ="<b style='color:red;'>None</b>"; 
                 split = readtrigger.split(' ')
                }
               
            } else {
              split = [];
              split[0]  = "<b style='color:red;'>None</b>"; 
              split[1]  = "<b style='color:red;'>None</b>"; 
              split[2]  = "<b style='color:red;'>None</b>"; 
              split[3]  = "<b style='color:red;'>None</b>"; 
              split[4]  = "<b style='color:red;'>None</b>"; 
              var  tag ="<b style='color:red;'>None</b>"; 
            }

            var timeOutSeconds = showXml.getElementsByTagName("timeoutSecondsString")[0]
            if(timeOutSeconds != undefined) {
               readTimeOutSeconds = timeOutSeconds.childNodes[0].textContent + ' Seconds';
            } else {
              readTimeOutSeconds ="<b style='color:red;'>None</b>";
            }

            var timeOutMinutes = showXml.getElementsByTagName("timeoutMinutes")[0]
            if(timeOutMinutes != undefined) {
               readTimeOutMinutes = timeOutMinutes.childNodes[0].textContent + ' Minutes';
            } else {
              readTimeOutMinutes ="<b style='color:red;'>None</b>";
            }

            var childProjects = showXml.getElementsByTagName("childProjects")[0]
            if(childProjects != undefined) {
               readChildProjects = childProjects.childNodes[0].textContent;
            } else {
              readChildProjects ="<b style='color:red;'>None</b>";
            }

            var childProjectsCase = showXml.getElementsByTagName("threshold")[0]
            if(childProjectsCase != undefined) {
              childNameProjectsCase = childProjectsCase.getElementsByTagName("name")[0]
               readChildProjectsCase = ' <b>[In case of '+ childNameProjectsCase.childNodes[0].textContent + ']</b>';
            } else {
              readChildProjectsCase ="";
            }

            var childMailer = showXml.getElementsByTagName("hudson.tasks.Mailer")[0]
            if(childMailer != undefined) {
              childMailerRecipients = childMailer.getElementsByTagName("recipients")[0]
               readChildMailerRecipients =  childMailerRecipients.childNodes[0].textContent;
            } else {
              readChildMailerRecipients = "<b style='color:red;'>None</b>";
            }

            var successMail = showXml.getElementsByTagName("hudson.plugins.emailext.plugins.trigger.SuccessTrigger")[0]
            if(successMail != undefined) {

              successMailRecipientList = successMail.getElementsByTagName("recipientList")[0]
               readSuccessMailRecipientList =  successMailRecipientList.childNodes[0].textContent;

               successMailSubject = successMail.getElementsByTagName("subject")[0]
               readSuccessMailSubject =  successMailSubject.childNodes[0].textContent;

               successMailBody = successMail.getElementsByTagName("body")[0]
               readSuccessMailBody =  '<pre>'+ successMailBody.childNodes[0].textContent + '</pre>';

               successMailAttBuild = successMail.getElementsByTagName("attachBuildLog")[0]
               readSuccessMailAttBuild =  successMailAttBuild.childNodes[0].textContent;

               successMailFrom = showXml.getElementsByTagName("from")[0]
               readSuccessMailFrom =  successMailFrom.childNodes[0].textContent;

            } else {
              readSuccessMailRecipientList = "<b style='color:red;'>None</b>";
              readSuccessMailSubject = "<b style='color:red;'>None</b>";
              readSuccessMailBody = "<b style='color:red;'>None</b>";
              readSuccessMailAttBuild = "<b style='color:red;'>None</b>";
              readSuccessMailFrom = "<b style='color:red;'>None</b>";
            }


            var failureMail = showXml.getElementsByTagName("hudson.plugins.emailext.plugins.trigger.FailureTrigger")[0]
            if(failureMail != undefined) {

              failureMailRecipientList = failureMail.getElementsByTagName("recipientList")[0]
               readFailureMailRecipientList =  failureMailRecipientList.childNodes[0].textContent;

               failureMailSubject = failureMail.getElementsByTagName("subject")[0]
               readFailureMailSubject =  failureMailSubject.childNodes[0].textContent;

               failureMailBody = failureMail.getElementsByTagName("body")[0]
               readFailureMailBody =  '<pre>'+ failureMailBody.childNodes[0].textContent + '</pre>';

               failureMailAttBuild = failureMail.getElementsByTagName("attachBuildLog")[0]
               readFailureMailAttBuild =  failureMailAttBuild.childNodes[0].textContent;

               failureMailFrom = showXml.getElementsByTagName("from")[0]
               readFailureMailFrom =  failureMailFrom.childNodes[0].textContent;

            } else {
              readFailureMailRecipientList = "<b style='color:red;'>None</b>";
              readFailureMailSubject = "<b style='color:red;'>None</b>";
              readFailureMailBody = "<b style='color:red;'>None</b>";
              readFailureMailAttBuild = "<b style='color:red;'>None</b>";
              readFailureMailFrom = "<b style='color:red;'>None</b>";
            }



         $('#load').append('<div class="destroy"> <table class="table table-bordered"> <tbody> <tr> <th style="width: 20px;">Header</th> <th>Task</th> </tr> <tr> <td>Display Name</td> <td><b>'+ data.fullName +'</b></td> </tr> <tr> <td>Description</td> <td>'+ data.description +'</td> </tr> <tr> <td>Build Situation</td> <td>'+ situation +'</td> </tr> <tr> <td>Build Description</td> <td>'+ healthReport +'</td> </tr> <tr> <td>Buildable</td> <td>'+ data.buildable +'</td> </tr> <tr> <td>Url</td> <td>'+ data.url +'</td> </tr> <tr> <td>Disabled</td> <td>'+ data.disabled +'</td> </tr> <tr> <td>Build Info</td> <td> <ul> <li><b>Last Build: </b> '+ lastBuild +'</li> <li><b>Last Completed Build: </b> '+ lastCompletedBuild +'</li> <li><b>Last Stable Build: </b> '+ lastStableBuild +'</li> <li><b>Last Unstable Build: </b> '+ lastUnstableBuild +'</li> <li><b>Last Successful Build: </b> '+ lastSuccessfulBuild +'</li> <li><b>Last Unsuccessful Build: </b> '+ lastUnsuccessfulBuild +'</li> </ul> </td> </tr> <tr> <td>Next Build Number</td> <td>'+ data.nextBuildNumber +'</td> </tr> <tr> <td>in Queue</td> <td>'+ data.inQueue +'</td> </tr> <tr> <td>Scheduler</td> <td> <ul> <li><b>Every Minute: </b> '+ split[0] +'</li> <li><b>At Hour: </b> '+ split[1] +'</li> <li><b>On Day of Month: </b> '+ split[2] +'</li> <li><b>On Month: </b> '+ split[3] +'</li> <li><b>On Day of Week: </b> '+ split[4] +'</li> <li><b>Tag: </b> '+ tag +'</li> </ul> </td> </tr> <tr> <td>Command</td> <td><pre>'+ readCommand  +'</pre></td> </tr><tr> <td>Abort Timeout Seconds</td> <td>'+ readTimeOutSeconds  +'</td> </tr> <tr> <td>Abort Absolute Minutes</td> <td>'+ readTimeOutMinutes  +'</td> </tr> <tr> <td>Run Next Jobs</td> <td>'+ readChildProjects  +' '+ readChildProjectsCase +'</td> </tr> <tr> <td>Email Recipients</td> <td>'+ readChildMailerRecipients  +'</td> </tr> <tr> <td>Success Mail Template</td> <td> <p><b>Send to: </b> '+ readSuccessMailRecipientList +' </p> <p><b>From: </b> '+ readSuccessMailFrom +' </p> <p><b>Subject: </b> '+ readSuccessMailSubject +' </p> <p><b>Attach Build Log: </b> '+ readSuccessMailAttBuild +' </p> <p><b>Body: </b> '+ readSuccessMailBody +' </p> </td> </tr>  <tr> <td>Failure Mail Template</td> <td> <p><b>Send to: </b> '+ readFailureMailRecipientList +' </p> <p><b>From: </b> '+ readFailureMailFrom +' </p> <p><b>Subject: </b> '+ readFailureMailSubject +' </p> <p><b>Attach Build Log: </b> '+ readFailureMailAttBuild +' </p> <p><b>Body: </b> '+ readFailureMailBody +' </p> </td> </tr> <tr> <td>Last Build Console Output</td> <td> <pre>'+ output +'</pre> </td> </tr> </tbody> </table> </div>');
         $('.overlay').hide();
         $('#modal-default').modal('show');

       }).fail(function() {
        console.error(arguments);
        $('.overlay').hide();
      });

     }
   });



     });
   </script>

