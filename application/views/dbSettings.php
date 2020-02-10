 <?php // print_r($jobs) ?>
 <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
</script>

 <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        Database Settings
        <small>database setup</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Extract, Transform, Load</a></li>
        <li class="active">Database Settings</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
   <?php if($role != 1) {  ?>
    <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-primary" href="<?php echo base_url(); ?>dbSettings/InsertDbSettings"><i class="fa fa-plus"></i> Add New Setting</a>
                </div>
            </div>
        </div> 
      <?php } ?>
      <div class="row" style="margin-top: 5px;">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h3 class="box-title">Database Settings</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <table id="table3" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th>Id</th>
                  <th>Creation Date</th>
                  <th>Job Name</th>
                  <th>Database Type</th>
                  <th>Address</th>
                  <th>Port</th>
                  <th>Login</th>
                  <th>Password</th>
                  <th>Schema</th>
                  <th>Additional Parameters</th>
                  <th>Oracle Service Name</th>
                  <th>Oracle Sid</th>
                  <th>Description</th>
                  <th>Owner</th>
                  <?php if($role != 1) {  ?><th>Action</th><?php } ?>
                </tr>
                </thead>
                <tbody>
                  <?php
                    if(!empty($settings))
                    {
                        foreach($settings as $record)
                        {
                    ?>
                    <tr>
                      <td><?php echo $record->id ?></td>
                      <td><?php echo date('Y-m-d H:i:s', strtotime($record->creation_date)) ?></td>
                        <td><?php echo $record->job_name ?></td>
                        <td><?php echo $record->db_type ?></td>
                        <td><?php echo $record->address ?></td>
                        <td><?php echo $record->port ?></td>
                        <td><?php echo $record->login ?></td>
                        <?php 
                          if($role != 1) { 
                          echo '<td>'.$record->password.'</td>';
                        } else {
                          echo "<td>  *******</td>";
                        } ?>
                        <td><?php echo $record->schema ?></td>
                        <td><?php echo $record->additional_parameters ?></td>
                        <td><?php echo $record->oracle_ServiceName ?></td>
                        <td><?php echo $record->oracle_sid ?></td>
                        <td><?php echo $record->description ?></td>
                        <td><?php echo $record->owner ?></td>
                       <?php if($role != 1) {  ?> <td>
                            <a class="btn btn-sm btn-warning" href="<?php echo base_url().'dbSettings/EditSettingsFetchData/'.$record->id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
                            <a class="btn btn-sm btn-danger deleteUser" href="#" data-userid="<?php echo $record->id; ?>" title="Delete"><i class="fa fa-trash"></i></a>
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
                  <th>Job Name</th>
                  <th>Database Type</th>
                  <th>Address</th>
                  <th>Port</th>
                  <th>Login</th>
                  <th>Password</th>
                  <th>Schema</th>
                  <th>Additional Parameters</th>
                  <th>Oracle Service Name</th>
                  <th>Oracle Sid</th>
                  <th>Description</th>
                  <th>Owner</th>
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
      <!-- /.row -->
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

<script type="text/javascript">
  jQuery(document).ready(function(){
  
  jQuery(document).on("click", ".deleteUser", function(){
    
    var userId = $(this).data("userid"),
      hitURL = baseURL + "dbSettings/deleteSetting" ,
      currentRow = $(this);
   
    alertify.confirm('Database Setting Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this Database Setting permanently ?</b></p></div></div></div>', 
      function(){ 
        jQuery.ajax({
      type : "POST",
      dataType : "json",
      url : hitURL,
      data : { userId : userId } 
      }).done(function(data){
        console.log(data);
        currentRow.parents('tr').remove();
        if(data.status = true) { alertify.success('Your Database Setting has been successfully deleted !'); }
        else if(data.status = false) { alertify.error("data deletion failed"); }
        else { alert("Access denied..!"); }
      });

    }, 
      function(){ 
        alertify.error('Operation Aborted, good choice.')
    }
  );
    
  
  });
  
  
  jQuery(document).on("click", ".searchList", function(){
    
  });
  
});

</script>