    <footer class="main-footer">
        <div class="pull-right hidden-xs">
          <b>Job Seeker</b> Admin System | Version 1.0
        </div>
        <strong>Copyright &copy; 2019-2020 <a href="https://www.linkedin.com/in/matheuspavanetti/">Matheus Pavanetti</a>.</strong> All rights reserved.<span style="margin-left: 100px;">Trust in god, he belives in you !</span>
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
