<html lang="en"><head>
  <meta charset="utf-8">
  <title>Error</title>
  <style type="text/css">

    ::selection { background-color: #E13300; color: white; }
    ::-moz-selection { background-color: #E13300; color: white; }

    body {
      background-color: #fff;
      margin: 40px;
      font: 13px/20px normal Helvetica, Arial, sans-serif;
      color: #4F5155;
    }

    a {
      color: #003399;
      background-color: transparent;
      font-weight: normal;
    }

    h1 {
      color: #444;
      background-color: transparent;
      border-bottom: 1px solid #D0D0D0;
      font-size: 19px;
      font-weight: normal;
      margin: 0 0 14px 0;
      padding: 14px 15px 10px 15px;
    }

    code {
      font-family: Consolas, Monaco, Courier New, Courier, monospace;
      font-size: 12px;
      background-color: #f9f9f9;
      border: 1px solid #D0D0D0;
      color: #002166;
      display: block;
      margin: 14px 0 14px 0;
      padding: 12px 10px 12px 10px;
    }

    #container {
      margin: 10px;
      border: 1px solid #D0D0D0;
      box-shadow: 0 0 8px #D0D0D0;
    }

    p {
      margin: 12px 15px 12px 15px;
    }
  </style>
</head>
<body>
  <div id="container" class="animated fadeIn">
    <h1><b>Job Seeker - Setup Wizard</b></h1>

    <div class="row">
      <div class="col-lg-4 col-md-4 col-xs-12">
        <div class="alert alert-info alert-dismissible animated fadeInLeft" style="margin-left: 10px;">
          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
          <h4><i class="icon fa fa-info"></i> Note</h4>
          <p>Using Jenkins API, these feature will be available:</p>
          <ul>
            <li>Create server job build</li>
            <li>List server job build</li>
            <li>Delete server job build</li>
            <li>Schedule server job build</li>
          </ul>
          <p>It's <b>highly recommended</b> to you use Jenkins API</p>
        </div>


         <?php 
        $this->load->helper('form');
        $success = $this->session->flashdata('success');
        if($success)
        {
          ?>
          <div class="alert alert-success alert-dismissable animated bounceIn" style="margin-left: 10px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <?php echo $this->session->flashdata('success'); ?>           
          </div>
        <?php } ?>

        <?php 
        $error = $this->session->flashdata('error');
        if($error)
        {
          ?>
          <div class="alert alert-danger alert-dismissable animated bounceIn" style="margin-left: 10px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <?php echo $this->session->flashdata('error'); ?>                    
          </div>
        <?php } ?>

      </div>
      <div class="col-lg-4 col-md-4 col-xs-12">
        <div class="box box-warning animated fadeIn" style="box-shadow: 10px 10px 5px 0px rgba(0,0,0,0.55);">
          <div class="overlay" style="display:none;">
               <i class="fa fa-refresh fa-spin"></i>
          </div>
          <div class="box-header with-border">
            <h3 class="box-title"><b>Jenkins API Connection</b></h3>
          </div>
          <!-- /.box-header -->
          <!-- form start -->
          <form id="check" action="<?php echo base_url() ?>setup/saveJenkins" method="post" role="form">
            <div class="box-body">
              <div class="form-group">
                <label for="engine">Using Jenkins</label>
                <select class="form-control" id="jenkins" name="jenkins" required>
                  <option value="true">TRUE</option>
                  <option value="false">FALSE</option>
                </select>
                <small><b>Example:</b> True if willing use Jenkins</small>
              </div>
              <div class="jenkinsForm">
                <div class="form-group">
                  <label for="url">Jenkins Host</label>
                  <input type="text" class="form-control" id="url" placeholder="Jenkins Host" name="url" required autocomplete="off">
                  <small><b>Example:</b> localhost</small>
                </div>
                <div class="form-group">
                  <label for="port">Jenkins  Port</label>
                  <input type="text" class="form-control" id="port" placeholder="Jenkins Port" name="port" required autocomplete="off">
                  <small><b>Example:</b> 8080</small>
                </div>
                <div class="form-group">
                  <label for="username">Username</label>
                  <input type="text" class="form-control" id="username" placeholder="Jenkins Username" name="username" required autocomplete="off">
                  <small><b>Example:</b> matheus</small>
                </div>
                <div class="form-group">
                  <label for="token">Token</label>
                  <input type="text" class="form-control" id="token" placeholder="Jenkins User Token" name="token" required autocomplete="off">
                  <small><b>Example:</b> 11883b9bbf07ade64064dc291d899c34cc</small>
                </div>
                <div class="form-group">
                  <label for="auth">Authorization</label>
                  <input type="text" class="form-control" id="auth" placeholder="API Authorization" name="auth" required autocomplete="off">
                  <small><b>Example:</b> Basic</small>
                </div>
                <div class="form-group">
                  <label for="home">Jenkins Home (Container Volume)</label>
                  <input type="text" class="form-control" id="home" placeholder="Jenkins Home" name="home" autocomplete="off">
                  <small><b>Example:</b> /jenkins</small><br>
                  <small>* Leave it blank if willing use system folder as jenkins repository.</small>
                </div>
              </div>
            </div>
            <!-- /.box-body -->
            <div class="jenkinsForm">
              <div class="box-footer">
                <button type="submit" class="btn btn-default test">Test Jenkins Host</button>
                <?php 
                if($error)
                {
                  ?>
                  <span class="animated bounceIn" style="color:red; margin-left: 5px;"><b>
                    Bad Connection, try again.
                  </b></span>
                <?php } ?>  
                <?php 
                    $success = $this->session->flashdata('success');
                    if($success)
                    {
                      ?>
                      <a  class="btn btn-success testApi pull-right animated bounceIn">Test Jenkins API</a>
                <?php } ?>   
              </div>
            </div>
          </form>
        </div>
      </div> 
    </div>
    <div class="row">
     <hr>
     <div class="col-lg-12 col-md-12 col-xs-12" >
      <div>
        <span class="pull-right" style="margin: 10px;">
          <a type="button" href="<?php echo base_url(); ?>setup/database"  class="btn btn-default">
            <i class="fa fa-arrow-left"></i> Previous Step
          </a>
          <a href="<?php echo base_url(); ?>setup/env" class="btn btn-success next" style="display:none;">Next Step <i class="fa fa-arrow-right"></i></a>
        </span>
      </div>
    </div> 
  </div>
</div>
</body>

<script type="text/javascript">
  $(document).ready(function(){
    var jenkins = $('#jenkins').val();

    $('#schema').val("JobSeeker");
    $('#auth').val("Basic");
   
  });

   $('#jenkins').change(function(){
      var jenkins = $('#jenkins').val();
      console.log(jenkins);
      if(jenkins == 'false') {
        $('.jenkinsForm').hide();
        $('.next').show();
      } else {
        $('.jenkinsForm').show();
        $('.next').hide();
      }
    });

   $('.test').click(function(){
     var url = $('#url').val();
     var port = $('#port').val();
     var username = $('#username').val();
     var token = $('#token').val();

     if(url != '' && port != '' && username != '' && token != ''){
      toastr.info('Please wait, the server is checking the given URL Address...','Check URL');
    } else {
      toastr.error('Please, type all required information.','Lack of information');
    }
  });

   $('.testApi').click(function(){
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
        }
        }).done(function(data) {

          toastr.success('Your Jenkins API has been successfully connected and ready to use','Successfully Connected');

           $('.next').show();
           $('.overlay').hide();

        }).fail(function() {
          console.error(arguments);
          toastr.error('Some error has been occured, please review your credentials','Connection Error');
          $('.overlay').hide();
        });

   });
</script>
</html>