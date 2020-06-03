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
        <i class="fa fa-dashboard"></i> Data Visualization <b>Configuration</b>
        <small>Setup and manage reports</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Data Visualization</a></li>
        <li><a href="#">Configuration</a></li>
      </ol>
    </section>

    <section class="content">

      <div class="container">
        <div class="row" style="padding-top: 15px;">
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-aqua"><i class="fa fa-pie-chart"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Available Reports</span>
              <span class="info-box-number"><?php echo $reports; ?></span>
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
              <span class="info-box-text">Software Types</span>
              <span class="info-box-number"><?php echo $types; ?></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->

        <!-- fix for small devices only -->
        <div class="clearfix visible-sm-block"></div>

        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-green"><i class="fa fa-user"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Available Users</span>
              <span class="info-box-number"><?php echo count($users); ?></span>
            </div>
            <!-- /.info-box-content -->
          </div>
          <!-- /.info-box -->
        </div>
        <!-- /.col -->
        <div class="col-md-3 col-sm-6 col-xs-12">
          <div class="info-box animated flipInX">
            <span class="info-box-icon bg-yellow"><i class="fa fa-users"></i></span>

            <div class="info-box-content">
              <span class="info-box-text">Avaiable Groups</span>
              <span class="info-box-number"><?php echo count($groups); ?></span>
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
           <form action="<?php echo base_url() ?>Visualization/add" method="POST" id="searchList">
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Report Name</label>
                      <input id="name" type="text" name="name" value="" class="form-control" placeholder="Enter Report Name" maxlength="20" autocomplete="off" required/>
                </div>
              </div>

               <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>Report Software Type</label>
                      <select id="type" class="form-control" name="type">
                        <option value="pbi">Microsoft Power BI</option>
                        <option value="tbl">Tableau</option>
                        <option value="tblPublic">Tableau Public</option>
                        <option value="qlikSense">Qlik Sense</option>
                        <option value="qlikView">Qlik View</option>
                        <option value="superset">Apache Superset</option>
                        <option value="microstrategy">MicroStrategy</option>
                      </select>
                </div>
              </div>

               <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>List of Users can view</label>
                      <select id="users" class="form-control select2" name="users[]" multiple="multiple">
                        <option value="*">All</option>
                           <?php
                          if(!empty($users))
                          {
                              foreach($users as $record)
                              {
                          ?>
                           <option value="<?php echo $record->name ?>"><?php echo $record->name ?></option>
                         <?php
                           }
                         }
                        ?>
                  </select>
                </div>
              </div>

              <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 form-group">
                <div class="input-group" style="width: 100%;">
                  <label>List of User Groups can view</label>
                      <select id="groups" class="form-control select2" name="groups[]" multiple="multiple">
                           <?php
                          if(!empty($groups))
                          {
                              foreach($groups as $record)
                              {
                          ?>
                           <option value="<?php echo $record->name ?>"><?php echo $record->name ?></option>
                         <?php
                           }
                         }
                        ?>
                  </select>
                </div>
              </div>
          </div>
            <?php // listReports(); ?>
           <div class="row animated fadeIn" style="margin-top: 25px;">
              <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 form-group">
                <input id="code" type="text" name="code" value="" class="form-control" placeholder="Fill in Embebed Iframe code or Report URL" autocomplete="off" required/>
              </div>
              <div class="col-lg-1 col-md-1 col-sm-6 col-xs-6 form-group">
                <button type="submit" class="btn btn-md btn-success btn-block searchList pull-right"><i class="fa fa-plus" aria-hidden="true"></i></button> 
              </div>
            </div>
         </form>

     <div class="row" style="margin-top: 20px;">
        <div class="col-xs-12">
          <div class="box box-primary">
            <div class="box-header">
              <h3 class="box-title"><b>Available Reports</b></h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
              <table id="tableReports" class="table table-bordered table-striped dataTable">
                <thead>
                <tr>
                  <th>Id</th>
                  <th>Creation Date</th>
                  <th>Report Name</th>
                  <th>Report Software Type</th>
                  <th>List of Users Can view</th>
                  <th>List of Users Group can view</th>
                  <th>Embebed Code</th>
                  <th>Owner</th>
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
                      <td><?php echo $record->id ?></td>
                      <td><?php echo date('Y-m-d H:i:s', strtotime($record->creation_date)) ?></td>
                        <td><?php echo $record->name ?></td>
                        <td><?php echo $record->type ?></td>
                        <td><?php echo $record->users ?></td>
                        <td><?php echo $record->groups ?></td>
                        <td class="text-center show"><a href="#" class="btn btn-sm btn-info">Check</a></td>
                        <td><?php echo $record->owner ?></td>
                       <?php if($role != 1) {  ?> <td>
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
                  <th>Report Name</th>
                  <th>Report Software Type</th>
                  <th>List of Users Can view</th>
                  <th>List of Users Group can view</th>
                  <th>Embebed Code</th>
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

         
    </div>
      
    </section>

    <!-- Main content -->
   
    <!-- /.content -->
</div> 

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



<script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/select2/dist/js/select2.min.js"></script>

<script type="text/javascript">
  $(document).ready(function() {
    $('.select2').select2({
       placeholder: " Click to Select a option to fetch",
       allowClear: true
    });


    jQuery(document).on("click", ".deleteUser", function(){
    
    var userId = $(this).data("userid"),
      hitURL = baseURL + "Visualization/delete" ,
      currentRow = $(this);
   
    alertify.confirm('Report Delete Confirmation Required','<div class="row"><div class="col-3"><div class="text-center"><img src="<?php echo base_url(); ?>assets/images/warning.png" width="200"><h2 style="color: red;"><b>WARNING !</b></h2><p><b>Are you sure to delete this Report record permanently ?</b></p></div></div></div>', 
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



$("#tableReports").on('click','.show',function(){

         var currentRow=$(this).closest("tr"); 
         var id=currentRow.find("td:eq(0)").text();

         var show = $.parseJSON($.ajax({
            contentType: "application/json",
            url:  '<?php echo base_url(); ?>Visualization/fetch/' + id,
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


         fetch = show.data[0];


         $("#content").append('<div class="destroy"><table class="table table-bordered"><tbody><tr><th style="width: 20px;">Header</th><th>Task</th></tr><tr><td>Creation Date</td><td>'+ moment(fetch.creation_date).format('dddd, MMMM Do YYYY, h:mm:ss')+'</td></tr><tr><td>Report Name</td><td>'+ fetch.name +'</td></tr><tr><td>Report Type</td><td>'+ fetch.type +'</td></tr><tr><td>Allowed Users</td><td>'+ fetch.users +'</td></tr><tr><td>Allowed Groups</td><td>'+ fetch.groups +'</td></tr><tr><td>Owner</td><td>'+ fetch.owner +'</td></tr><tr><td>Embebed Report</td><td style="height: 600px;">'+ fetch.code +'</td></tr></tbody></table></div>')

         $('#modal-default').modal('show');

    });   

});
</script>