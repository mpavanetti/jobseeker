 <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
</script>
<style>
    body {
      margin: 0;
    }
    iframe {
      height: 600px;
      width: 500px;
      box-sizing: border-box;
    }
</style>


<link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/bower_components/select2/dist/css/select2.min.css">
<div class="content-wrapper">    
    <section class="content-header">
      <h1>
        <i class="fa fa-dashboard"></i> Context Settings <b>Edit Project Details</b>
        <small>Setup and manage projects</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Extract, Transform, Load</a></li>
        <li><a href="#">Context Settings</a></li>
        <li><a href="#">Project Details</a></li>
      </ol>
    </section>

    <section class="content">

      <div class="container">

       <div class="row">
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
                <div class="alert alert-success alert-dismissable">
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
       
        <div class="row animated fadeIn" style="margin-top: 25px;">
           <form action="<?php echo base_url() ?>Context/editProjectUpdate" method="POST" id="searchList">
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
              <div class="input-group" style="width: 100%; display: none;">
                  <label>Id Name</label>
                      <input id="name" type="text" name="Id" value="<?php echo $project->Id ?>" class="form-control" placeholder="Enter Project Id" maxlength="11" autocomplete="off" required/>
                </div>
                <div class="input-group" style="width: 100%;">
                  <label>Project Name</label>
                      <input id="name" type="text" name="name" value="<?php echo $project->ProjectName ?>" class="form-control" placeholder="Enter Project Name" maxlength="1000" autocomplete="off" required/>
                </div>
              </div>

               <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Active Project</label>
                      <select id="type" class="form-control" name="active">
                        <option value="1">True</option>
                        <option value="0">False</option>
                      </select>
                </div>
              </div>

              <div class="col-lg-5 col-md-5 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Git Path</label>
                      <input id="gitpath" type="text" name="gitpath" value="<?php echo $project->GitPath ?>" class="form-control" placeholder="Enter Git Path" maxlength="2000" autocomplete="off"/>
                </div>
              </div>

              <div class="col-lg-1 col-md-1 col-sm-6 col-xs-12 form-group" style="margin-top: 25px;">
                <button type="submit" class="btn btn-md btn-success btn-block searchList pull-right"><i class="fa fa-save" aria-hidden="true"></i></button> 
              </div>
          </div>
         </form>
    </div>
      
    </section>

    <!-- Main content -->
   
    <!-- /.content -->
</div> 