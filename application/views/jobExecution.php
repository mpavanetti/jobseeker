<div class="content-wrapper">    
    <section class="content-header">
      <h1>
        Job Execution
        <small>Run Jobs</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Extract, Transform, Load</a></li>
        <li class="active">Job Execution</li>
      </ol>
    </section>
    <section class="content">
      <div class="container">
      <div class="row" style="margin-top: 10px;">
         <div class="col-lg-6 col-md-6 col-xs-12">
                <div class="box box-primary">
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
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                        </button>
                      </div>
                       <h3 class="box-title"><b>Job Execution Menu</b></h3> 
                    </div>
                    <div class="box-body text-center">
                          <a class="btn btn-app" id="play">
                            <i class="fa fa-play"></i> Play
                          </a>
                          <a class="btn btn-app" id="clean" style="display: none;">
                            <i class="fa fa-times"></i> Clean
                          </a>
                          <a class="btn btn-app" id="repeat" style="display: none;">
                            <i class="fa fa-repeat"></i> Repeat
                          </a>
                      <span id="spanStop">
                        
                      </span>
                    </div>
                      <div id="overlay2" class="overlay" style="display:none;">
                        <i class="fa fa-refresh fa-spin"></i>
                    </div>
                </div>
               </div>

            </div>

               <div id="gif" style="display:none;">
                       <div class="col-xs-12 text-center">
                             <img src="<?php echo base_url(); ?>assets/images/gifs/machine2.gif" width="650">
                             <h3 class="text-center"><b>Data Processing...</b></h3>
                       </div>
               </div>

              <div class="row">
                <div id="info">
                </div>
               </div>

          <div class="row">
              <div id="console">
              </div>
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
               // console.log(item.name);
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

    $('#play').click(function(){
        var job = $('#selector').val();
         $('.info').remove();
        $('.console').remove();
        $('#selector').val(0);
        $('#repeat').hide();
        $('#clean').hide();
        $('.stop').remove();

        if(job == '0'){
            toastr.error("Please, Select an avaiable job to execute.", "Error");
        } else {
             $('.overlay').show();
             $.ajax({
          url: jenkins_url + 'job/'+ job +'/build',
          method: 'POST',
          headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
          beforeSend: function() {
           
            $('.overlay').show();
            $('#gif').fadeIn();
            $('#play').hide();
            
        }
        }).done(function(data) {
          $('#spanStop').append('<a class="btn btn-app stop" id="stop"><i class="fa fa-stop"></i> Stop</a>');
            toastr.warning("Your Execution Request has been sent to server.", "Request Sent")


         var timer = setTimeout(function() {    
          $.ajax({
              url: jenkins_url + 'job/'+ job +'/lastBuild/api/json?pretty=true',
              method: 'GET',
              headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
              beforeSend: function() {

              
               
            }
            }).done(function(data) {

              if(data.building == true) {

                toastr.info("Data is still executing, able to stop if needed.")
                $('#overlay2').hide();

               $('#stop').click(function(){
                  $.ajax({
                    url: jenkins_url + 'job/'+ job + '/' + data.id + '/stop',
                    method: 'POST',
                    async: false,
                    headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
                    beforeSend: function() {
                      toastr.warning("Your Stop Request has been sent to server.", "Request Sent")
                  }
                  }).done(function(data) {
                      toastr.error("Your Stop Request has been sent to server.", "Operation Aborted")
                  });
              }); 
                
               
            }
          });
        }, errorDelay);

         $(this).data('timer', timer);

        }).fail(function() {
          console.error(arguments);
        });


        var info = function() {

            $.ajax({
              url: jenkins_url + 'job/'+ job +'/lastBuild/api/json?pretty=true',
              method: 'GET',
              headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
              beforeSend: function() {

              
               
            }
            }).done(function(data) {

              if(data.building == false) {
               
                   $('#info').append($('<div class="col-xs-12 info animated fadeInLeft"><div id="boxHeader" class="box box-success"><div class="box-header with-border"><div class="box-tools pull-right"><button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i> </button></div><h3 class="box-title"><b>Build Execution Info</b></h3></div><div class="box-body"><table class="table table-striped"><tbody><tr><th style="width: 140px">Summary</th><th>Info</th><th>Progress</th><th style="width: 40px">Label</th></tr><tr><td>Status:</td><td><b><span id="status"> </span></b></td><td><div class="progress progress-xs progress-striped active"><div class="progress-bar progress-bar-success" style="width: 100%"></div></div></td><td><span class="badge bg-green">100%</span></td></tr><tr><td>Building:</td><td><span id="building"> </span></td><td><div class="progress progress-xs progress-striped active"><div class="progress-bar progress-bar-success" style="width: 100%"></div></div></td><td><span class="badge bg-green">100%</span></td></tr><tr><td>Build Id:</td><td><span id="buildId"> </span></td><td><div class="progress progress-xs progress-striped active"><div class="progress-bar progress-bar-success" style="width: 100%"></div></div></td><td><span class="badge bg-green">100%</span></td></tr><tr><td>Queue Id:</td><td><span id="queueId"> </span></td><td><div class="progress progress-xs progress-striped active"><div class="progress-bar progress-bar-success" style="width: 100%"></div></div></td><td><span class="badge bg-green">100%</span></tr><tr><td>URL:</td><td><span id="url"> </span></td><td><div class="progress progress-xs progress-striped active"><div class="progress-bar progress-bar-success" style="width: 100%"></div></div></td><td><span class="badge bg-green">100%</span></td></tr><tr><td>Description:</td><td><span id="description"></span></td><td><div class="progress progress-xs progress-striped active"><div class="progress-bar progress-bar-success" style="width: 100%"></div></div></td><td><span class="badge bg-green">100%</span></td></tr><tr><td>Display Name:</td><td><span id="name"> </span></td><td><div class="progress progress-xs progress-striped active"><div class="progress-bar progress-bar-success" style="width: 100%"></div></div></td><td><span class="badge bg-green">100%</span></td></tr><tr><td>Elapsed Time:</td><td><span id="duration"> </span></td><td><div class="progress progress-xs progress-striped active"> <div class="progress-bar progress-bar-success" style="width: 100%"></div></div></td><td><span class="badge bg-green">100%</span></td></tr></tbody></table></div></div></div>'))
             

               $('#status').html(data.result);
               
               if(data.building == false) {
                $('#building').html("Ready");
               }
               $('#buildId').html(data.id);
               $('#queueId').html(data.queueId);
               $('#url').html(data.url);

               if(data.description == null){
                $('#description').html("Empty")
               } else {
                $('#description').html(data.description); 
               }
               

               $('#name').html(data.fullDisplayName);
               $('#duration').html(moment.utc(data.duration).format('HH [Hours, ] mm [Minutes, ] ss [Seconds, ] SSS [Miliseconds.]'));
            

               if(data.result != 'SUCCESS'){

                $('#boxHeader').removeClass("box-success")
                $('#boxHeader').addClass("box-danger")
                $('#status').addClass("text-red")
                toastr.error('Some Error has occured during the execution, please check the logs', 'Error')
               } else {
                $('#status').addClass("text-green")
                toastr.info('Build Id: ' + data.id + '<br> Build Name: ' + data.fullDisplayName ,'Info')
                toastr.success('The request Build has been successfully executed, please check the logs', 'Executed')
               }

               $('#play').show();
               $('#clean').show();
               $('#stop').remove();
               $('#repeat').show();

               $.ajax({
              url: jenkins_url + 'job/'+ job +'/lastBuild/consoleText',
              method: 'GET',
              headers: {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
              beforeSend: function() {

            }
            }).done(function(data) {

                   $('#console').append($('<div class="col-xs-12 console animated fadeInLeft" style="margin-bottom: 30px;"><div class="box"><div class="box-header with-border"><div class="box-tools pull-right"><button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button></div><h3 class="box-title"><b>Console Log Output</b></h3></div><div class="box-body text-center"><div class="log" style="padding: 10px;"><pre class="text-left" id="write"></pre></div></div></div></div>'));
                 $('#write').html(data);

                $('.overlay').fadeOut();
                $('#gif').fadeOut();

            }).fail(function() {
              console.error(arguments);
            });


                $('.overlay').fadeOut();
                $('#gif').fadeOut();

               } else {

                setTimeout(info, 100);

               }

            }).fail(function() {
              console.error(arguments);
            });
        }
            
           
          setTimeout(info, delay);
   

            }
        })

    $('#clean').click(function(){
       var job = $('#selector').val();

        toastr.info('Your desktop has been cleaned','Clean Request')
        $('.console').remove();
        $('.info').remove();
        $('#selector').val(0);
        $('#stop').remove();
        $('#repeat').hide();
        $('#clean').hide();
    })

        });
</script>

