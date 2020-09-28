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
        <div class="col-lg-4 col-md-4 col-xs-12">
        </div>
       
        <div class="row">
            <div class="col-lg-4 col-md-4 col-xs-12">
                <div class="box box-danger animated fadeIn" style="box-shadow: 10px 10px 5px 0px rgba(0,0,0,0.55);">
            <div class="box-header with-border">
              <h3 class="box-title"><b>JobSeeker Enviroment</b></h3>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            <form id="check" action="<?php echo base_url() ?>setup/databaseCheck" method="post" role="form">
              <div class="box-body">
                <div class="form-group" style="padding: 10px;">
                  <label for="engine">JobSeeker Enviroment</label>
                  <select class="form-control" id="env" name="env" required>
                      <option value="dev">Development</option>
                      <option value="qa">Quality</option>
                      <option value="prod">Production</option>
                  </select>
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
                <span class="pull-right">
                    <a type="button" href="<?php echo base_url(); ?>setup/jenkins"  class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Previous Step
              </a>
                    <a href="<?php echo base_url(); ?>" class="btn btn-success" style="margin: 10px;">Next Step <i class="fa fa-arrow-right"></i></a>
                </span>
            </div>
        </div> 
    </div>
</div>
</body>
</html>