 <?php // print_r($jobs) ?>
 <script>
  $(document).ready(function(){
    $('body').addClass('sidebar-collapse')
  });
</script>
<style>
  pre { 
    white-space: pre-wrap; 
    word-break: break-word;
    max-width: 750px;
}
</style>
 <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        Email Settings
        <small>Email Setup</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li><a href="#">Extract, Transform, Load</a></li>
        <li class="active">Email Settings</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container">
   <?php if($role != 1) {  ?>
    <div class="row">
            <div class="col-xs-12 text-left">
                <div class="form-group">
                    <a class="btn btn-primary" href="<?php echo base_url(); ?>emailSettings/addSetting"><i class="fa fa-plus"></i> Add New Setting</a>
                </div>
            </div>
        </div> 
      <?php } ?>
      <div id="test"></div>
      <div class="row" style="margin-top: 5px;">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h3 class="box-title">Email Settings</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <table id="table4" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <?php if($role != 1) {  ?><th class="text-center">Send Email</th><?php } ?>
                  <th>Id</th>
                  <th>Creation Date</th>
                  <th>Name</th>
                  <th>To</th>
                  <th>From</th>
                  <th>Cc</th>
                  <th>Subject</th>
                  <th>Message</th>
                  <th>Attachment</th>
                  <th>Smtp</th>
                  <th>Status</th>
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
                       <?php if($role != 1) {  ?> <td class="text-center">
                            <a class="btn btn-sm btn-success sendEmail" href="#" data-settingid="<?php echo (int) $record->id; ?>" title="Send Email"><i class="fa fa-send"></i></a>
                        </td><?php } ?>
                      <td><?php echo (int) $record->id ?></td>
                      <td><?php echo html_escape(date('Y-m-d H:i:s', strtotime($record->creation_date))) ?></td>
                        <td><?php echo html_escape($record->name) ?></td>
                        <td><?php echo html_escape($record->to) ?></td>
                        <td><?php echo html_escape($record->from) ?></td>
                        <td><?php echo html_escape($record->cc) ?></td>
                        <td><?php echo html_escape($record->subject) ?></td>
                        <td><a class="btn btn-sm btn-info showMail" href="#" data-settingid="<?php echo (int) $record->id; ?>" title="View Email">Check Content</a></td>
                        <td><?php echo html_escape($record->attachment) ?></td>
                        <td><?php echo html_escape($record->smtp) ?></td>
                        <td><?php echo ($record->enabled === '1') ? 'Enabled' : 'Disabled' ?></td>
                        <td><?php echo html_escape($record->description) ?></td>
                        <td><?php echo html_escape($record->owner) ?></td>
                        <?php if($role != 1) {  ?> <td class="text-center">
                            <a class="btn btn-sm btn-warning" href="<?php echo base_url().'EmailSettings/EditSettingsFetchData/'.(int) $record->id; ?>" title="Edit"><i class="fa fa-pencil"></i></a>
                            <a class="btn btn-sm btn-danger deleteUser" href="#" data-userid="<?php echo (int) $record->id; ?>" title="Delete"><i class="fa fa-trash"></i></a>
                        </td><?php } ?>
                    </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                 <tr>
                  <?php if($role != 1) {  ?><th class="text-center">Send Email</th><?php } ?>
                  <th>Id</th>
                  <th>Creation Date</th>
                  <th>Name</th>
                  <th>To</th>
                  <th>From</th>
                  <th>Cc</th>
                  <th>Subject</th>
                  <th>Message</th>
                  <th>Attachment</th>
                  <th>Smtp</th>
                  <th>Status</th>
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

<!-- Modal -->
  <div class="modal fade" id="modal-default" style="display: none;">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">×</span></button>
                <h4 class="modal-title"><b>Email Content Info</b></h4>
              </div>
              <div class="modal-body">

                <div id="content">
                  
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-primary pull-left" data-dismiss="modal">Close</button>
              </div>
            </div>
            <!-- /.modal-content -->
          </div>
          <!-- /.modal-dialog -->
        </div>
<!-- Modal -->
<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/moment/moment.min.js"></script>
<script type="text/javascript">
  jQuery(document).ready(function(){
  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }
  
  jQuery(document).on("click", ".deleteUser", function(){
    
    var userId = $(this).data("userid"),
      hitURL = baseURL + "emailSettings/deleteSetting" ,
      currentRow = $(this);
   
    alertify.confirm('Email Template Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this Email Template permanently ?</b></p></div></div></div>', 
      function(){ 
        jQuery.ajax({
      type : "POST",
      dataType : "json",
      url : hitURL,
      data : { userId : userId } 
      }).done(function(data){
        if(data.status === true) {
          currentRow.parents('tr').remove();
          alertify.success('Your Email Template has been successfully deleted !');
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
  

$("#table4").on('click','.sendEmail',function(){


         // get the current row Id, job name and instance id
         var currentRow=$(this).closest("tr"); 

         // get id row value to select from table
         var id = $(this).data("settingid") || currentRow.find("td:eq(1)").text();

        // Get email info
         var showMail = $.parseJSON($.ajax({
            contentType: "application/json",
          url:  '<?php echo base_url(); ?>EmailSettings/fetch/' + encodeURIComponent(id),
            dataType: "json", 
            async: false,
            beforeSend: function() {

              $(".destroy").remove();
            },
            error: function() {
               toastr.error("Error During query error list data \n Id: " + id, "Query Data Error");
            },

            success: function() {
            },
            complete: function(data) {
                dateRequest = data;
            }

         }).responseText);


         // Get all email info
         var id = showMail["data"][0].id;
         var name = showMail["data"][0].name;
         var enabled = showMail["data"][0].enabled;

         if (enabled != 0) {
          alertify.confirm('Email Sending Confirmation', 'Are you sure you want to send the email  <b>'+ escapeHtml(name) + '</b> ?',
          function(){ 

            $.ajax({    //create an ajax request
              type: "POST",
              url: baseURL + "EmailSettings/mail/",
              data: {id: id},
              dataType: "json",
              beforeSend: function() {
                toastr.info('Your email request has been sent to server queue.')
            },
               error: function(xhr) {
             var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Some Error has been occurred';
             alertify.error(message)
               
              },             
              success: function(data){
                if (data.status === true) {
                  alertify.success(data.message || 'Your email has been successfully sent !')
                } else {
                  alertify.error(data.message || 'Email sending failed')
                }
              }
          });

          }, 

          function(){

           alertify.error('Operation Aborted')

         });
        } else {
          alertify.error('The Email Template <b>' + escapeHtml(name) + '</b> is <b style="color:red;"> Disabled !</b>')
        }

         

      

    });


$("#table4").on('click','.showMail',function(){

         var currentRow=$(this).closest("tr"); 

         var id = $(this).data("settingid") || currentRow.find("td:eq(1)").text();

         var showMail = $.parseJSON($.ajax({
            contentType: "application/json",
            url:  '<?php echo base_url(); ?>EmailSettings/fetch/' + encodeURIComponent(id),
            dataType: "json", 
            async: false,
            beforeSend: function() {

              $(".destroy").remove();
            },
            error: function() {
               toastr.error("Error During query error list data \n Id: " + id, "Query Data Error");
            },

            success: function() {
            },
            complete: function(data) {
                dateRequest = data;
            }

         }).responseText);

         var mail = showMail.data && showMail.data[0] ? showMail.data[0] : {};
         var status = mail.enabled;
         if (status == 0) { var status = 'Disabled'} else {var status = 'Enabled'};

         var creationDate = moment(mail.creation_date);
         $("#content").append('<div class="destroy"><table class="table table-bordered"><tbody><tr><th>Header</th><th>Task</th></tr><tr><td>Creation Date</td><td>'+ escapeHtml(creationDate.isValid() ? creationDate.format('dddd, MMMM Do YYYY, h:mm:ss') : '') +'</td></tr><tr><td>Name</td><td>'+ escapeHtml(mail.name) +'</td></tr><tr><td>To</td><td>'+ escapeHtml(mail['to']) +'</td></tr><tr><td>From</td><td>'+ escapeHtml(mail['from']) +'</td></tr><tr><td>Cc</td><td>'+ escapeHtml(mail.cc) +'</td></tr><tr><td>Subject</td><td>'+ escapeHtml(mail.subject) +'</td></tr><tr><td>Smtp</td><td>'+ escapeHtml(mail.smtp) +'</td></tr><tr><td>Description</td><td>'+ escapeHtml(mail.description) +'</td></tr><tr><td>Status</td><td>'+ escapeHtml(status) +'</td></tr><tr><td>Email Content</td><td><pre>'+ escapeHtml(mail.msg) +'</pre></td></tr></tbody></table></div>')

         $('#modal-default').modal('show');

    });

  
});

</script>