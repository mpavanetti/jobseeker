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
            <div class="col-lg-12 col-md-12 col-xs-12">
                <div class="text-center">
                    <img class="img " src="<?php echo base_url(); ?>assets/images/logo.jpeg">
                </div>
            </div>   
        </div>
        <div class="row">
            <div class="col-lg-12 col-md-12 col-xs-12">
                <div class="text-center">
                    <h3><b>Welcome to Job Seeker project Setup Wizard</b></h3><Br>
                    <h4>Please, follow up carefully with the setup steps.</h4><Br>
                </div>
            </div> 
        </div>
        <div class="row">
         <hr>
         <div class="col-lg-12 col-md-12 col-xs-12" >
            <div>
                <span  class="pull-right">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-default">
                About <i class="fa fa-info-circle"></i>
              </button>
                    <a href="<?php echo base_url(); ?>setup/database" class="btn btn-success" style="margin: 10px;">Next Step <i class="fa fa-arrow-right"></i></a>
                </span>
            </div>
        </div> 
    </div>
</div>
  <div class="modal fade" id="modal-default" style="display: none;">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">×</span></button>
                <h4 class="modal-title"><b>Job Seeker Info</b></h4>
              </div>
              <div class="modal-body">
                <p>Job Seeker is a platform to assist the management of the ETL (Extraction, Transformation and Loading) process for a Data Warehouse in a cloud and container environment (AWS, Docker)</p>
                <p>
                    <span>The platform has the main features:</span>
                    <ul>
                        <li>Management of ETL execution loads in real time</li>
                        <li>Error tracking by loads</li>
                        <li>Monitoring of records processed by loads</li>
                        <li>Visualization of the steps to build the Data Warehouse model</li>
                        <li>Upload files to feed the ETL process on the platform</li>
                        <li>Dynamic Variables Control (Database credentials, File name and directory, variables)</li>
                        <li>Execution of loads on the platform</li>
                        <li>Schedule build executions</li>
                        <li>Customized Email Templates</li>
                        <li>Alerts in case of Success or Failure by email</li>
                        <li>User Rules Control (Key user, Manager, Admin)</li>
                        <li>Data Visualization dashboards management</li>
                    </ul>
                </p>
                <p><div style="position: relative; padding-bottom: 56.22254758418741%; height: 0; box-shadow: 10px 10px 5px 0px rgba(0,0,0,0.75);" ><iframe src="https://www.loom.com/embed/1ce1c2d778ec4c67a86a43de1846c0ce" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></iframe></div></p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
              </div>
            </div>
            <!-- /.modal-content -->
          </div>
          <!-- /.modal-dialog -->
        </div>
</body>
</html>