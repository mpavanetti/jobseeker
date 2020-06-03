<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <i class="fa fa-users"></i> Groups Management
        <small>Add or Delete a user group.</small>
      </h1>
    </section>
    <section class="content">
        <form action="<?php echo base_url() ?>User/addNewGroup" method="POST" id="groupAdd">
            <div class="row" style="margin-top: 25px;">
                  <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 form-group">
                    <input id="code" type="text" name="name" value="" class="form-control" placeholder="Fill in the Group Name to create it" autocomplete="off" required/>
                  </div>
                  <div class="col-lg-1 col-md-1 col-sm-6 col-xs-6 form-group">
                    <button type="submit" class="btn btn-md btn-success btn-block searchList pull-right"><i class="fa fa-plus" aria-hidden="true" title="Click to inser a new group of users."></i></button> 
                  </div>
                </div>
         </form>

         <div class="row">
             <div class="col-md-4">
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

        <div class="row">
            <div class="col-xs-12">
              <div class="box box-primary">
                <div class="box-header">
                    <h3 class="box-title"><b>Group List</b></h3>
                </div><!-- /.box-header -->
                <div class="box-body table-responsive">
                  <table class="table table-striped table-bordered dataTable">
                    <thead>
                    <tr>
                        <th>Id</th>
                        <th>Group Name</th>
                        <th>Owner</th>
                        <th>Created On</th>
                        <th class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <?php
                    if(!empty($groups))
                    {
                        foreach($groups as $record)
                        {
                    ?>
                    <tr>
                        <td><?php echo $record->id ?></td>
                        <td><?php echo $record->name ?></td>
                        <td><?php echo $record->owner ?></td>
                        <td><?php echo date("d-m-Y", strtotime($record->creation_date)) ?></td>
                        <td class="text-center"> 
                            <a class="btn btn-sm btn-danger deleteUser" href="#" data-userid="<?php echo $record->id; ?>" title="Delete"><i class="fa fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php
                        }
                    }
                    ?>
                     <tfoot>
                     <tr>
                       <th>Id</th>
                        <th>Group Name</th>
                        <th>Owner</th>
                        <th>Created On</th>
                        <th class="text-center">Actions</th>
                    </tr>
                    </tfoot>  
                  </table>
                  
                </div><!-- /.box-body -->
              </div><!-- /.box -->
            </div>
        </div>
    </section>
</div>

<script type="text/javascript">
    jQuery(document).ready(function(){
         jQuery(document).on("click", ".deleteUser", function(){
            var userId = $(this).data("userid"),
              hitURL = baseURL + "User/deleteGroup" ,
              currentRow = $(this);
           
            alertify.confirm('Group Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this group permanently ?</b></p></div></div></div>', 
              function(){ 
                jQuery.ajax({
              type : "POST",
              dataType : "json",
              url : hitURL,
              data : { userId : userId } 
              }).done(function(data){
                console.log(data);
                currentRow.parents('tr').remove();
                if(data.status = true) { alertify.success('Your Group has been successfully deleted !'); }
                else if(data.status = false) { alertify.error("data deletion failed"); }
                else { alert("Access denied..!"); }
              });

            }, 
              function(){ 
                alertify.error('Operation Aborted')
            }
          );
    
  });
    });
</script>
