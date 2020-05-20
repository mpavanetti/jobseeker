

    <footer class="main-footer">
        <div class="pull-right hidden-xs">
          <b>Job Seeker</b> Admin System | Version 1.0
        </div>
        <strong>Copyright &copy; 2019-2020 <a href="<?php echo base_url(); ?>">Matheus Pavanetti</a>.</strong> All rights reserved.
    </footer>
    
    <script src="<?php echo base_url(); ?>assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>assets/dist/js/adminlte.min.js" type="text/javascript"></script>
    <!-- <script src="<?php echo base_url(); ?>assets/dist/js/pages/dashboard.js" type="text/javascript"></script> -->
    <script src="<?php echo base_url(); ?>assets/js/jquery.validate.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>assets/js/validation.js" type="text/javascript"></script>
    <!-- Toastr -->
    <script src="<?php echo base_url(); ?>assets/plugins/toastr/build/toastr.min.js" type="text/javascript"></script>
    <!-- Datatable -->
    <script src="<?php echo base_url(); ?>assets/bower_components/datatables.net/js/jquery.dataTables.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>assets/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js" type="text/javascript"></script>
    <!-- Alertify JS -->
    <script src="<?php echo base_url(); ?>assets/plugins/alertify/alertify.min.js" type="text/javascript"></script>
    <!-- jQuery UI -->
    <script src="<?php echo base_url(); ?>assets/bower_components/jquery-ui-1.12.1/jquery-ui.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/moment/moment.min.js"></script>

    <script type="text/javascript">
        $(document).ready(function(){

         $('.dataTable').DataTable( {
            "order": [[ 1, "desc" ]],
            lengthMenu:  [ 10, 20, 50, 100, 200, 500],

        });

          $('.dataTableMobile').DataTable( {
            "scrollX": true,
            "order": [[ 1, "desc" ]],
            lengthMenu:  [ 10, 20, 50, 100, 200, 500],

        });

        $('#table3').DataTable( {
            "scrollX": true,
            columnDefs: [
            { width: 50, targets: 14 },
            { width: 200, targets: 12 }
        ],

            "order": [[ 1, "desc" ]]
        } );

        $('#table4').DataTable( {
            "scrollX": true,

            "order": [[ 1, "desc" ]]
        } );

        $('#table5').DataTable( {
            "scrollX": true,
            columnDefs: [
            { width: 300, targets: 8 }
        ],

            "order": [[ 1, "desc" ]]
        } );

        $('#table6').DataTable( {
            "scrollX": true,

           columnDefs: [
            { width: 100, targets: 5 },
            { width: 100, targets: 9 },
            { width: 100, targets: 10 },
            { width: 100, targets: 11 }
        ],
        "order": [[ 0, "desc" ]]
        });

           
        });
    </script>
    <script type="text/javascript">
        jQuery(document).ready(function(){

        var jenkins_url = '<?php echo $jenkins_url; ?>';
        var jenkins_username = '<?php echo $jenkins_username; ?>';
        var jenkins_token = '<?php echo $jenkins_token; ?>';
        var jenkins_authorization = '<?php echo $jenkins_authorization; ?>';

        $('#myTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000,2000,5000],
          "pageLength": 5,
          "order": [[ 0, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "color"},
          {"data": "name"},
          {"data": "url"}
          ],
           fixedColumns: true
       });


        $('#listTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000],
          "pageLength": 5,
          "order": [[ 0, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,lastFailedBuild[displayName,result,timestamp],color,builds[number]{0,1}]',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "color"},
          {"data": "name"},
          {"data": "builds[].number"}

          ]
       });

        $('#listFailedTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000],
          "pageLength": 5,
          "order": [[ 3, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,lastFailedBuild[displayName,result,timestamp,duration,url,queueId,building]{0,1}]',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "name"},
          {"data": "lastFailedBuild.result"},
          {"data": "lastFailedBuild.displayName"},
          {"data": "lastFailedBuild.timestamp"},
          {"data": "lastFailedBuild.duration"},
          {"data": "lastFailedBuild.url"},
          {"data": "lastFailedBuild.queueId"},
          {"data": "lastFailedBuild.building"},
          ],
          columnDefs:[{targets:0, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:1, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:2, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:3, render:function(data){
            if(data != null){return moment(data).format('MMMM Do YYYY, h:mm:ss a');}else {return '' }
          }},{targets:4, render:function(data){
            if(data != null){return moment(data).utc().format('HH [Hours, ] mm [Minutes, ] ss [Seconds, ] SSS [Miliseconds.]');} else {return ''}      
          }},{targets:5, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:6, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:7, render:function(data){
            if(data != null){return data} else {return ''}
          }}]
       });

        $('#listSuccessTable').DataTable({
          "lengthMenu": [3,5,10,13,20,100,200,500,1000],
          "pageLength": 5,
          "order": [[ 3, "desc" ]],
          "ajax": {
            "url": jenkins_url +'api/json?tree=jobs[name,lastStableBuild[displayName,result,timestamp,duration,url,queueId,building]{0,1}]',
            "type": 'GET',
            "headers": {'Authorization': 'Basic ' + btoa(jenkins_username + ':' + jenkins_token)},
            "dataSrc": "jobs"
          },
          "columns": [
          {"data": "name"},
          {"data": "lastStableBuild.result"},
          {"data": "lastStableBuild.displayName"},
          {"data": "lastStableBuild.timestamp"},
          {"data": "lastStableBuild.duration"},
          {"data": "lastStableBuild.url"},
          {"data": "lastStableBuild.queueId"},
          {"data": "lastStableBuild.building"},

          ],
          columnDefs:[{targets:0, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:1, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:2, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:3, render:function(data){
            if(data != null){return moment(data).format('MMMM Do YYYY, h:mm:ss a');}else {return '' }
          }},{targets:4, render:function(data){
            if(data != null){return moment(data).utc().format('HH [Hours, ] mm [Minutes, ] ss [Seconds, ] SSS [Miliseconds.]');} else {return ''}      
          }},{targets:5, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:6, render:function(data){
            if(data != null){return data} else {return ''}
          }},{targets:7, render:function(data){
            if(data != null){return data} else {return ''}
          }}]
       });

      });
    </script>

    <script type="text/javascript">
        var windowURL = window.location.href;
        pageURL = windowURL.substring(0, windowURL.lastIndexOf('/'));
        var x= $('a[href="'+pageURL+'"]');
            x.addClass('active');
            x.parent().addClass('active');
        var y= $('a[href="'+windowURL+'"]');
            y.addClass('active');
            y.parent().addClass('active');
    </script>
  </body>
</html>