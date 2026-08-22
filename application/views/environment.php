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

    .environment-page .content {
      padding: 18px;
    }

    .environment-shell {
      max-width: 1480px;
      width: 100%;
    }

    .environment-page .info-box,
    .environment-entry-form,
    .environment-page .box {
      border: 1px solid #d8e0e8;
      border-radius: 6px;
      box-shadow: 0 8px 20px rgba(16, 42, 67, .08);
    }

    .environment-summary-row {
      display: flex;
      flex-wrap: wrap;
      padding-top: 15px;
    }

    .environment-summary-row:before,
    .environment-summary-row:after,
    .environment-entry-form:before,
    .environment-entry-form:after {
      display: none;
    }

    .environment-summary-row > [class*="col-"] {
      display: flex;
    }

    .environment-page .info-box {
      display: flex;
      min-height: 104px;
      width: 100%;
    }

    .environment-page .info-box-icon {
      border-radius: 6px 0 0 6px;
      flex: 0 0 92px;
      height: 104px;
      line-height: 104px;
    }

    .environment-page .info-box-content {
      align-self: center;
      flex: 1 1 auto;
    }

    .environment-entry-form {
      background: #fff;
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      margin-top: 18px;
      padding: 18px 18px 6px;
    }

    .environment-entry-form .form-group {
      min-height: 72px;
    }

    .environment-form-action {
      align-items: flex-end;
      display: flex;
      min-height: 72px;
    }

    .environment-list-card .box-header {
      min-height: 50px;
    }

    .environment-list-card .box-body {
      min-height: 360px;
      overflow-x: auto;
    }

    .environment-entry-form label,
    .environment-page table th {
      color: #243b53;
      font-size: 12px;
      letter-spacing: .02em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .environment-status-pill {
      border-radius: 12px;
      display: inline-block;
      font-weight: 700;
      padding: 4px 9px;
    }

    .environment-status-active {
      background: #e6fffa;
      color: #047857;
    }

    .environment-status-inactive {
      background: #edf2f7;
      color: #4a5568;
    }
</style>


<link rel="stylesheet" type="text/css" href="<?php echo base_url(); ?>assets/bower_components/select2/dist/css/select2.min.css">
<div class="content-wrapper environment-page">
    <section class="content-header">
      <h1>
        <i class="fa fa-dashboard"></i> Context Settings <b>Environment Details</b>
        <small>Setup and manage environments</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Extract, Transform, Load</a></li>
        <li><a href="#">Context Settings</a></li>
        <li><a href="#">Environment Details</a></li>
      </ol>
    </section>

    <section class="content">

      <div class="container-fluid environment-shell">
        <div class="row environment-summary-row">
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-aqua"><i class="fa fa-pie-chart"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Available Environments</span>
              <span class="info-box-number"><?php echo $environments; ?></span>
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
              <span class="info-box-text">Active Environments</span>
              <span class="info-box-number"><?php echo $activeEnvironments; ?></span>
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
       
        <form action="<?php echo base_url() ?>Context/addEnvironment" method="POST" id="searchList">
          <div class="row animated fadeIn environment-entry-form">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>" />
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Environment Name</label>
                      <input id="name" type="text" name="name" value="" class="form-control" placeholder="Enter Environment Name" maxlength="100" autocomplete="off" required/>
                </div>
              </div>

               <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Active Environment</label>
                      <select id="active" class="form-control" name="active">
                        <option value="1">True</option>
                        <option value="0">False</option>
                      </select>
                </div>
              </div>

              <div class="col-lg-5 col-md-5 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Description</label>
                      <input id="description" type="text" name="description" value="" class="form-control" placeholder="Enter Description" maxlength="2000" autocomplete="off"/>
                </div>
              </div>

              <div class="col-lg-1 col-md-1 col-sm-6 col-xs-12 form-group environment-form-action">
                <button type="submit" class="btn btn-md btn-success btn-block searchList pull-right" title="Add environment"><i class="fa fa-plus" aria-hidden="true"></i></button>
              </div>
          </div>
         </form>

     <div class="row" style="margin-top: 20px;">
        <div class="col-xs-12">
          <div class="box box-primary environment-list-card">
            <div class="box-header">
              <h3 class="box-title"><b>Available Environments</b></h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <table id="tableReports" class="table table-bordered table-striped dataTable">
                <thead>
                <tr>
                  <th>Id</th>
                  <th>Creation Date</th>
                  <th>Environment Name</th>
                  <th>Description</th>
                  <th>Is Active</th>
                  <th>Modified On</th>
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
                      <td><?php echo (int) $record->Id ?></td>
                      <td><?php echo date('Y-m-d H:i:s', strtotime($record->CreatedOn)) ?></td>
                        <td><b><?php echo html_escape($record->Environment) ?></b></td>
                        <td><?php echo html_escape($record->Description) ?></td>
                        <td><?php echo ($record->IsActive == 1) ? '<span class="environment-status-pill environment-status-active">Active</span>' : '<span class="environment-status-pill environment-status-inactive">Inactive</span>' ?></td>
                        <td><?php if($record->ModifiedOn == null){ echo ""; } else { echo date('Y-m-d H:i:s', strtotime($record->ModifiedOn)); }  ?></td>
                       <?php if($role != 1) {  ?> <td>
                          <a class="btn btn-sm btn-warning" href="<?php echo base_url().'Context/editEnvironment/'.(int) $record->Id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
                            <a class="btn btn-sm btn-danger deleteUser" href="#" data-userid="<?php echo (int) $record->Id; ?>" title="Delete"><i class="fa fa-trash"></i></a>
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
                  <th>Environment Name</th>
                  <th>Description</th>
                  <th>Is Active</th>
                  <th>Modified On</th>
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
      
    </section>

    <!-- Main content -->
   
    <!-- /.content -->
</div> 



<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/select2/dist/js/select2.min.js"></script>

<script type="text/javascript">
  $(document).ready(function() {
 
    jQuery(document).on("click", ".deleteUser", function(){
    
    var userId = $(this).data("userid"),
      hitURL = baseURL + "Context/deleteEnvironment" ,
      currentRow = $(this);
   
    alertify.confirm('Environment Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this Environment record permanently ?</b></p></div></div></div>', 
      function(){ 
        jQuery.ajax({
      type : "POST",
      dataType : "json",
      url : hitURL,
      data : { userId : userId } 
      }).done(function(data){
        console.log(data);
        if(data.status === true) {
          currentRow.parents('tr').remove();
          alertify.success('Your Record has been successfully deleted !');
        }
        else if(data.status === false) { alertify.error("data deletion failed"); }
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