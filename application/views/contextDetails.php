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
        <i class="fa fa-dashboard"></i> Context Settings <b>Context Details</b>
        <small>Setup and manage Context Details</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Extract, Transform, Load</a></li>
        <li><a href="#">Context Settings</a></li>
        <li><a href="#">Context Details</a></li>
      </ol>
    </section>

    <section class="content">
      <div class="container">
      <div class="container">
        <div class="row" style="padding-top: 15px;">
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-aqua"><i class="fa fa-pie-chart"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Available Contexts</span>
              <span class="info-box-number"><?php echo $contexts; ?></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-red"><i class="fa fa-bar-chart"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Active Contexts</span>
              <span class="info-box-number"><?php echo $activeContexts; ?></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
      </div>

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
           <form action="<?php echo base_url() ?>Context/addContext" method="POST" id="searchList">

            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Context Key</label>
                      <input id="contextKey" type="text" name="contextKey" value="" class="form-control" placeholder="Enter Context Key" maxlength="1000" autocomplete="off" required/>
                </div>
              </div>

            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Context Value</label>
                      <input id="contextValue" type="text" name="contextValue" value="" class="form-control" placeholder="Enter Context Value" maxlength="255" autocomplete="off" required/>
                </div>
              </div>
              

               <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Active Context</label>
                      <select id="active" class="form-control" name="active">
                        <option value="1">True</option>
                        <option value="0">False</option>
                      </select>
                </div>
              </div>

              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Is Encrypted</label>
                      <select id="encrypted" class="form-control" name="encrypted">
                        <option value="0">False</option>
                        <option value="1">True</option>
                      </select>
                </div>
              </div>

              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Project Name</label>
                      <select id="projectName" class="form-control" name="projectName">
                        <?php
                            if(!empty($listProjects))
                            {
                                foreach($listProjects as $projects)
                                {
                            ?>
                        <option value="<?php echo $projects->ProjectName ?>"><?php echo $projects->ProjectName ?></option>
                      <?php } } ?>
                      </select>
                </div>
              </div>
              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Environment Name</label>
                      <select id="environmentName" class="form-control" name="environmentName">
                         <?php
                            if(!empty($listEnvironments))
                            {
                                foreach($listEnvironments as $env)
                                {
                            ?>
                        <option value="<?php echo $env->Environment ?>"><?php echo $env->Environment ?></option>
                      <?php } } ?>
                      </select>
                </div>
              </div>


              <div class="col-lg-5 col-md-5 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Description</label>
                      <input id="description" type="text" name="description" value="" class="form-control" placeholder="Enter Description" maxlength="2000" autocomplete="off"/>
                </div>
              </div>

              <div class="col-lg-1 col-md-1 col-sm-6 col-xs-12 form-group" style="margin-top: 25px;">
                <button type="submit" class="btn btn-md btn-success btn-block searchList pull-right"><i class="fa fa-plus" aria-hidden="true"></i></button> 
              </div>
          </div>
         </form>

     <div class="row" style="margin-top: 20px;">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h3 class="box-title"><b>Available Contexts</b></h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <table class="table table-bordered table-striped dataTable2">
                <thead>
                <tr>
                  <th>Id</th>
                  <th>Creation Date</th>
                  <th>Context Key</th>
                  <th>Context Value</th>
                  <th>Is Encrypted</th>
                  <th>Environment</th>
                  <th>Project Name</th>
                  <th>Description</th>
                  <th>Is Active</th>
                  <th>Created By</th>
                  <th>Modified On</th>
                  <th>Modified By</th>
                  <?php if($role != 1) {  ?><th>Action</th><?php } ?>
                </tr>
                </thead>
                <tbody>
                  <?php
                    if(!empty($list))
                    {
                        foreach($list as $record)
                        {
                    ?>
                    <tr>
                      <td><?php echo $record->Id ?></td>
                      <td><?php echo date('Y-m-d H:i:s', strtotime($record->CreatedOn)) ?></td>
                        <td><?php echo '<b>'.$record->ContextKey.'</b>' ?></td>
                        <td><?php echo $record->ContextValue ?></td>
                        <td><?php echo ($record->isEncrypted == 1) ? 'True' : 'False' ?></td>
                        <td><?php echo $record->Environment ?></td>
                        <td><?php echo $record->ProjectName ?></td>
                        <td><?php echo $record->Description ?></td>
                        <td><?php echo ($record->IsActive == 1) ? 'True' : 'False' ?></td>
                        <td><?php echo $record->CreatedBy ?></td>
                        <td><?php if($record->ModifiedOn == null){ echo ""; } else { echo date('Y-m-d H:i:s', strtotime($record->ModifiedOn)); }  ?></td>
                        <td><?php echo $record->ModifiedBy ?></td>
                       <?php if($role != 1) {  ?> <td>
                           <a class="btn btn-sm btn-warning" href="<?php echo base_url().'Context/editContext/'.$record->Id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
                            <a class="btn btn-sm btn-danger deleteUser" href="#" data-userid="<?php echo $record->Id; ?>" title="Delete"><i class="fa fa-trash"></i></a>
                        </td><?php } ?>
                    </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                 <tr>
                  <th>Id</th>
                  <th>Creation Date</th>
                  <th>Context Key</th>
                  <th>Context Value</th>
                  <th>Is Encrypted</th>
                  <th>Environment</th>
                  <th>Project Name</th>
                  <th>Description</th>
                  <th>Is Active</th>
                  <th>Created By</th>
                  <th>Modified On</th>
                  <th>Modified By</th>
                  <?php if($role != 1) {  ?><th>Action</th><?php } ?>
                </tr>
                </tfoot>
              </table>
            </div>
            <!-- /.box-body -->
          </div>
          <!-- /.box -->
        </div>
        <!-- /.col -->
      </div>

         
    </div>
      </div>
    </section>

    <!-- Main content -->
   
    <!-- /.content -->
</div> 

<script type="text/javascript">
  $(document).ready(function() {

    jQuery(document).on("click", ".deleteUser", function(){
    
    var userId = $(this).data("userid"),
      hitURL = baseURL + "Context/deleteContext" ,
      currentRow = $(this);
   
    alertify.confirm('Context Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this Context record permanently ?</b></p></div></div></div>', 
      function(){ 
        jQuery.ajax({
      type : "POST",
      dataType : "json",
      url : hitURL,
      data : { userId : userId } 
      }).done(function(data){
        console.log(data);
        currentRow.parents('tr').remove();
        if(data.status = true) { alertify.success('Your Record has been successfully deleted !'); }
        else if(data.status = false) { alertify.error("data deletion failed"); }
        else { alert("Access denied..!"); }
      });

    }, 
      function(){ 
        alertify.error('Operation Aborted, good choice.')
    }
  );
    
  });

});
</script>