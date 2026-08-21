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
        Smtp Settings
        <small>Smtp Setup</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Extract, Transform, Load</a></li>
        <li class="active">Smtp Settings</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container">
   <?php if($role != 1) {  ?>
    <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-primary" href="<?php echo base_url(); ?>SmtpSettings/addSetting"><i class="fa fa-plus"></i> Add New Setting</a>
                    <a class="btn btn-default" href="<?php echo base_url(); ?>SmtpSettings/syncJenkinsMailer"><i class="fa fa-refresh"></i> Sync Jenkins Mailer</a>
                    <a class="btn btn-info" href="<?php echo htmlspecialchars($mailpit_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fa fa-inbox"></i> Open Mailpit Inbox</a>
                </div>
            </div>
        </div> 
      <?php } ?>
      <?php if(!empty($default_smtp)) { ?>
        <div class="alert alert-info">
          <i class="fa fa-envelope"></i>
          Default enabled SMTP provider: <b><?php echo $default_smtp->name ?></b> at <b><?php echo $default_smtp->smtp_host ?>:<?php echo $default_smtp->smtp_port ?></b>.
        </div>
        <?php if(strtolower($default_smtp->smtp_host) === 'mailpit') { ?>
        <div class="alert alert-warning">
          <i class="fa fa-inbox"></i>
          Local Mailpit captures Jenkins emails for testing. Messages appear in the Mailpit inbox and are not delivered to external mailboxes until a real SMTP provider is enabled as default.
        </div>
        <?php } ?>
      <?php } else { ?>
        <div class="alert alert-warning">
          <i class="fa fa-warning"></i>
          No enabled SMTP provider is available. Jenkins build emails will not be sent until one is enabled.
        </div>
      <?php } ?>
      <?php if(!empty($jenkins_mailer_sync) && empty($jenkins_mailer_sync['ok'])) { ?>
        <div class="alert alert-warning">
          <i class="fa fa-warning"></i>
          <?php echo $jenkins_mailer_sync['message']; ?>
        </div>
      <?php } ?>
      <div class="row" style="margin-top: 5px;">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h3 class="box-title">Smtp Settings</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <table id="table5" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th>Id</th>
                  <th>Creation Date</th>
                  <th>Name</th>
                  <th>Smtp_Host</th>
                  <th>Smtp_Port</th>
                  <th>Enabled</th>
                  <th>Default</th>
                  <th>Reply To</th>
                  <th>Username</th>
                  <th>Password</th>
                  <th>Ssl</th>
                  <th>Description</th>
                  <th>Owner</th>
                  <?php if($role != 1) {  ?><th class="text-center">Actions</th><?php } ?>
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
                        <td><?php echo $record->name ?></td>
                        <td><?php echo $record->smtp_host ?></td>
                        <td><?php echo $record->smtp_port ?></td>
                        <td><?php echo ((int) $record->is_enabled === 1) ? '<span class="label label-success">Enabled</span>' : '<span class="label label-default">Disabled</span>' ?></td>
                        <td><?php echo ((int) $record->is_default === 1) ? '<span class="label label-primary">Default</span>' : '' ?></td>
                        <td><?php echo $record->reply_to ?></td>
                        <td><?php echo $record->username ?></td>
                        <?php 
                          if($role != 1) { 
                          echo '<td>'.$record->password.'</td>';
                        } else {
                          echo "<td>  *******</td>";
                        } ?>
                        <td><?php echo ($record->ssl === '1') ? 'Yes' : 'No' ?></td>
                        <td><?php echo $record->description ?></td>
                        <td><?php echo $record->owner ?></td>
                         <?php if($role != 1) {  ?> <td class="text-center">
                            <a class="btn btn-sm btn-warning" href="<?php echo base_url().'SmtpSettings/EditSettingsFetchData/'.$record->id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
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
                  <th>Name</th>
                  <th>Smtp_Host</th>
                  <th>Smtp_Port</th>
                  <th>Enabled</th>
                  <th>Default</th>
                  <th>Reply To</th>
                  <th>Username</th>
                  <th>Password</th>
                  <th>Ssl</th>
                  <th>Description</th>
                  <th>Owner</th>
                  <?php if($role != 1) {  ?><th class="text-center">Actions</th><?php } ?>
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
      hitURL = baseURL + "SmtpSettings/deleteSetting" ,
      currentRow = $(this);
   
    alertify.confirm('Smtp Setting Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this Smtp Setting permanently ?</b></p></div></div></div>', 
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
          alertify.success('Your Smtp Setting has been successfully deleted !');
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
  
  
  jQuery(document).on("click", ".searchList", function(){
    
  });
  
});

</script>