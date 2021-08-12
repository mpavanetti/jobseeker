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
        <b>Output Components</b>
        <small>advanced tables</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Extract Transform and Load</a></li>
        <li class="active">Output Components</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container">
   <?php if($role != 1) {  ?>
    <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-primary" href="<?php echo base_url(); ?>JobsTable/addNewOutputComponent"><i class="fa fa-plus"></i> Add New</a>
                </div>
            </div>
        </div> 
      <?php } ?>
      <div class="row" style="margin-top: 5px;">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h3 class="box-title">Available Jobs and Output Components</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <table class="table table-bordered table-striped dataTable">
                <thead>
                <tr>
                  <th>Id</th>
                  <th>Creation Date</th>
                  <th>Job Name</th>
                  <th>Job Component</th>
                  <th>Component Type</th>
                  <th>File Name</th>
                  <th>Repository</th>
                  <!-- <th>Files Downloaded</th> -->
                  <th>Owner</th>
                  <?php if($role != 1) {  ?><th>Action</th><?php } ?>
                </tr>
                </thead>
                <tbody>
                  <?php
                    if(!empty($jobs))
                    {
                        foreach($jobs as $record)
                        {
                    ?>
                    <tr>
                      <td><?php echo $record->id ?></td>
                      <td><?php echo date('Y-m-d H:i:s', strtotime($record->creation_date)) ?></td>
                        <td><?php echo $record->job_name ?></td>
                        <td><?php echo $record->job_component ?></td>
                        <td><?php echo $record->component_type ?></td>
                        <td><?php echo ($record->file_name == NULL) ? 'Not Available' : $record->file_name; ?></td>
                        <td><?php echo $record->file_path ?></td>
                      <!--  <td><?php echo ($record->file_downloaded === '0') ? 'None yet' : $record->file_downloaded; ?></td> -->
                        <td><?php echo $record->owner ?></td>
                       <?php if($role != 1) {  ?> <td>
                            <a class="btn btn-sm btn-warning" href="<?php echo base_url().'JobsTable/editOldOutput/'.$record->id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
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
                  <th>Job Component</th>
                  <th>Component Type</th>
                  <th>File Name</th>
                  <th>Repository</th>
                  <!-- <th>Files Downloaded</th> -->
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
      </div>
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

<script type="text/javascript">
  jQuery(document).ready(function(){
  
  jQuery(document).on("click", ".deleteUser", function(){
    
    var userId = $(this).data("userid"),
      hitURL = baseURL + "deleteOutput" ,
      currentRow = $(this);
   
    alertify.confirm('Output Component Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this output component permanently ?</b></p></div></div></div>', 
      function(){ 
        jQuery.ajax({
      type : "POST",
      dataType : "json",
      url : hitURL,
      data : { userId : userId } 
      }).done(function(data){
        console.log(data);
        currentRow.parents('tr').remove();
        if(data.status = true) { alertify.success('Your input has been successfully deleted !'); }
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