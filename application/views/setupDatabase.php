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
                <ul>
                    <li>Please, get all your database credentials and fill it in</li>
                    <li>If you need to create a database schema, let the <b>Schema Name</b> in default value (JobSeeker)</li>
                    <li>Note that Database charset and collaction were suggested, but you don't have to use them accordingly</li>
                </ul>
              </div>
              <div class="alert alert-danger alert-dismissible animated fadeInLeft" style="margin-left: 10px;">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <h4><i class="icon fa fa-info"></i> Alert</h4>
                <ul>
                    <li>Please, keep in mind if the database table already exists, <b>be careful</b>, this step will drop and create the system table structure</li>
                </ul>
              </div>
            </div>
            <div class="col-lg-4 col-md-4 col-xs-12">
                <div class="box box-success animated fadeIn" style="box-shadow: 10px 10px 5px 0px rgba(0,0,0,0.55);">
            <div class="box-header with-border">
              <h3 class="box-title"><b>Database Connection Manager</b></h3>
            </div>
            <!-- /.box-header -->
            <!-- form start -->
            <form id="check" action="<?php echo base_url() ?>setup/databaseCheck" method="post" role="form">
              <div class="box-body">
                <div class="form-group">
                  <label for="engine">Database Engine</label>
                  <select class="form-control" id="engine" name="engine" required>
                      <option value="mysqli">MySQL</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="host">Server Hostname</label>
                  <input type="text" class="form-control" id="host" placeholder="Server Hostname" name="host" required autocomplete="off">
                </div>
                <div class="form-group">
                  <label for="schema">Schema Name</label>
                  <input type="text" class="form-control" id="schema" placeholder="Schema Name" name="schema" required autocomplete="off">
                </div>
                <div class="form-group">
                  <label for="username">Username</label>
                  <input type="text" class="form-control" id="username" placeholder="Database Username" name="username" required autocomplete="off">
                </div>
                <div class="form-group">
                  <label for="password">Password</label>
                  <input type="password" class="form-control" id="password" placeholder="Database User Password" name="password" required autocomplete="off">
                </div>
                <div class="form-group">
                  <label for="charset">Charset</label>
                  <input type="text" class="form-control" id="charset" placeholder="Database Charset" name="charset" required autocomplete="off">
                </div>
                <div class="form-group">
                  <label for="dbcol">Database Collation</label>
                  <input type="text" class="form-control" id="dbcol" placeholder="Database Collation" name="dbcol" required autocomplete="off">
                </div>
              </div>
              <!-- /.box-body -->

              <div class="box-footer">
                <button type="submit" class="btn btn-default">Test Connection</button>
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
                    <a type="button" href="<?php echo base_url(); ?>setup"  class="btn btn-default">
                <i class="fa fa-arrow-left"></i> Previous Step
              </a>
                    <a href="<?php echo base_url(); ?>setup/jenkins" class="btn btn-success" style="margin: 10px;">Next Step <i class="fa fa-arrow-right"></i></a>
                </span>
            </div>
        </div> 
    </div>
</div>
</body>

<script type="text/javascript">
    $(document).ready(function(){
        $('#host').val("localhost");
        $('#schema').val("JobSeeker");
        $('#username').val("root");
        $('#charset').val("utf8");
        $('#dbcol').val("utf8_general_ci");
    });
</script>
</html>