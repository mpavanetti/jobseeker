<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="shortcut icon" type="image/png" href="<?php echo base_url(); ?>assets/images/bi.png" sizes="16x16"/>
    <title><?php echo $pageTitle; ?></title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <!-- Bootstrap 3.3.4 -->
    <link href="<?php echo base_url(); ?>assets/bower_components/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- FontAwesome 4.3.0 -->
    <link href="<?php echo base_url(); ?>assets/bower_components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <!-- Ionicons 2.0.0 -->
    <link href="<?php echo base_url(); ?>assets/bower_components/Ionicons/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <!-- Theme style -->
    <link href="<?php echo base_url(); ?>assets/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <!-- AdminLTE Skins. Choose a skin from the css/skins  folder instead of downloading all of them to reduce the load. -->
    <link href="<?php echo base_url(); ?>assets/dist/css/skins/_all-skins.min.css" rel="stylesheet" type="text/css" />
    <!-- Animate.css -->
    <link href="<?php echo base_url(); ?>assets/plugins/animate/animate.min.css" rel="stylesheet" type="text/css" />
    <!-- Toastr.css -->
    <link href="<?php echo base_url(); ?>assets/plugins/toastr/build/toastr.min.css" rel="stylesheet" type="text/css" />
    <!-- Datatable -->
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
    <!-- Alertify Js -->
    <link href="<?php echo base_url(); ?>assets/plugins/alertify/css/alertify.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/plugins/alertify/css/themes/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- jQuery UI -->
    <link href="<?php echo base_url(); ?>assets/bower_components/jquery-ui-1.12.1/jquery-ui.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>assets/bower_components/jquery-ui-1.12.1/jquery-ui.theme.min.css" rel="stylesheet" type="text/css" />
    <style>
    	.error{
    		color:red;
    		font-weight: normal;
    	}
    </style>
    <script src="<?php echo base_url(); ?>assets/bower_components/jquery/dist/jquery-3.4.1.min.js"></script>
    <script type="text/javascript">
        var baseURL = "<?php echo base_url(); ?>";
    </script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
    <style>
        .skin-purple .main-header .navbar { 
        background: #8E2DE2; 
        background: -webkit-linear-gradient(to right, #4A00E0, #8E2DE2); 
        background: linear-gradient(to right, #4A00E0, #8E2DE2); !important 
      }

      .skin-purple .main-header .logo {
        background: #4A00E0; 

      }

      .skin-purple .main-header .navbar .sidebar-toggle:hover {
        background: #500ceb;
      }

      .skin-purple .main-header .logo:hover {
        background-color: #500ceb;
      }

      .skin-purple .sidebar-menu>li.active>a {
        border-left-color: #500ceb;
      }

      .skin-purple .main-header li.user-header {
        background-color: #500ceb;
      }

    </style>
  </head>
  <body class="hold-transition skin-purple sidebar-mini">
    <div class="wrapper">
      
      <header class="main-header">
        <!-- Logo -->
        <a href="<?php echo base_url(); ?>" class="logo">
          <!-- mini logo for sidebar mini 50x50 pixels -->
          <span class="logo-mini"><b>J</b>S</span>
          <!-- logo for regular state and mobile devices -->
          <span class="logo-lg"><b>Job Seeker</b></span>
        </a>
        <!-- Header Navbar: style can be found in header.less -->
        <nav class="navbar navbar-static-top" role="navigation">
          <!-- Sidebar toggle button-->
          <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
            <span class="sr-only">Toggle navigation</span>
          </a>
          <div class="navbar-custom-menu">
            <ul class="nav navbar-nav">
              <li class="dropdown tasks-menu">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                  <i class="fa fa-history"></i>
                </a>
                <ul class="dropdown-menu">
                  <li class="header"> Last Login : <i class="fa fa-clock-o"></i> <?= empty($last_login) ? "First Time Login" : $last_login; ?></li>
                </ul>
              </li>
              <!-- User Account: style can be found in dropdown.less -->
              <li class="dropdown user user-menu">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                  <img src="<?php echo base_url(); ?>assets/dist/img/avatar.png" class="user-image" alt="User Image"/>
                  <span class="hidden-xs"><?php echo $name; ?></span>
                </a>
                <ul class="dropdown-menu">
                  <!-- User image -->
                  <li class="user-header">
                    
                    <img src="<?php echo base_url(); ?>assets/dist/img/avatar.png" class="img-circle" alt="User Image" />
                    <p>
                      <?php echo $name; ?>
                      <small><?php echo $role_text; ?></small>
                    </p>
                    
                  </li>
                  <!-- Menu Footer-->
                  <li class="user-footer">
                    <div class="pull-left">
                      <a href="<?php echo base_url(); ?>profile" class="btn btn-warning btn-flat"><i class="fa fa-user-circle"></i> Profile</a>
                    </div>
                    <div class="pull-right">
                      <a href="<?php echo base_url(); ?>logout" class="btn btn-default btn-flat"><i class="fa fa-sign-out"></i> Sign out</a>
                    </div>
                  </li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>
      </header>
      <!-- Left side column. contains the logo and sidebar -->
      <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <section class="sidebar">
          <!-- sidebar menu: : style can be found in sidebar.less -->
          <ul class="sidebar-menu" data-widget="tree">
            <li class="header">MAIN NAVIGATION</li>
            <li>
              <a href="<?php echo base_url(); ?>dashboard">
                <i class="fa fa-home"></i> <span>Dashboard</span></i>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>Tmf">
                <i class="fa fa-desktop"></i> <span>Transaction Monitoring</span></i>
              </a>
            </li>
            <li class="treeview">
          <a href="#">
            <i class="fa fa-random"></i> <span>Extract Transform Load</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li>
              <a href="<?php echo base_url(); ?>JobsTable" >
                <i class="fa fa-caret-square-o-up"></i>
                <span>Input Components</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>JobsTable/outputTable" >
                <i class="fa fa-caret-square-o-down"></i>
                <span>Output Components</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>upload" >
                <i class="fa fa-upload"></i>
                <span>File Upload</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>dbSettings" >
                <i class="fa fa-database"></i>
                <span>Database Settings</span>
              </a>
            </li>
            <!-- <li>
              <a href="#" >
                <i class="fa fa-list"></i>
                <span>Console Job Logs</span>
              </a>
            </li> -->
            <li>
              <a href="<?php echo base_url(); ?>GenericSettings" >
                <i class="fa fa-cogs"></i>
                <span>Generic Settings</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>EmailSettings" >
                <i class="fa fa-envelope"></i>
                <span>Email Settings</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>SmtpSettings" >
                <i class="fa fa-server"></i>
                <span>Smtp Settings</span>
              </a>
            </li>
          </ul>
        </li>
        <?php  if ($jenkins_enabled == true) { 
            if($role == ROLE_ADMIN || $role == ROLE_MANAGER)
            {
            ?>
        <li class="treeview">
          <a href="#">
            <i class="fa fa-laptop"></i> <span>Job Management</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li>
              <a href="<?php echo base_url(); ?>jobList" >
                <i class="fa fa-list"></i>
                <span>Job Build List</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>jobList/full" >
                <i class="fa fa-list-ul"></i>
                <span>Full Job Build List</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>jobExecution" >
                <i class="fa fa-play"></i>
                <span>Job Execution</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>jobView" >
                <i class="fa fa-eye"></i>
                <span>View Job</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>jobCreation" >
                <i class="fa fa-plus-square"></i>
                <span>Job Creation</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>deletejob" >
                <i class="fa fa-minus-square"></i>
                <span>Delete Job</span>
              </a>
            </li>
          </ul>
        </li>
      <?php } } ?>
            <?php
            if($role == ROLE_ADMIN || $role == ROLE_MANAGER)
            {
            ?>
            <li>
              <a href="<?php echo base_url(); ?>files">
                <i class="fa fa-files-o"></i>
                <span>File Manager</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>Database">
                <i class="fa fa-database"></i>
                <span>Database Manager</span>
              </a>
            </li>
            <?php
            }
            if($role == ROLE_ADMIN)
            {
            ?>
             <li>
              <a href="<?php echo base_url(); ?>Cloud">
                <i class="fa fa-cloud"></i>
                <span>Cloud Enviroment</span>
              </a>
            </li>
            <?php  if ($jenkins_enabled == true) { ?>
            <li>
              <a href="<?php echo $jenkins_url; ?>" target="_blank">
                <i class="fa fa-share-alt"></i>
                <span>Jenkins Manager</span>
              </a>
            </li>
          <?php } ?>
            <li>
              <a href="<?php echo base_url(); ?>serverinfo">
                <i class="fa fa-server"></i>
                <span>Server Statistics</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>userListing">
                <i class="fa fa-users"></i>
                <span>Users</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>User/groupsListing">
                <i class="fa fa-users"></i>
                <span>Groups</span>
              </a>
            </li>
            <?php
            }
            ?>
          </ul>
        </section>
        <!-- /.sidebar -->
      </aside>
