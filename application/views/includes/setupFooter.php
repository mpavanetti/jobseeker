    <script src="<?php echo base_url(); ?>assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>assets/js/jquery.validate.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>assets/js/validation.js" type="text/javascript"></script>
    <!-- Toastr -->
    <script src="<?php echo base_url(); ?>assets/plugins/toastr/build/toastr.min.js" type="text/javascript"></script>
    <!-- Alertify JS -->
    <script src="<?php echo base_url(); ?>assets/plugins/alertify/alertify.min.js" type="text/javascript"></script>
    <!-- jQuery UI -->
    <script src="<?php echo base_url(); ?>assets/bower_components/jquery-ui-1.12.1/jquery-ui.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="<?php echo base_url(); ?>assets/bower_components/moment/moment.min.js"></script>
 

    <script type="text/javascript">
        (function() {
            function sidebarUrlKey(url) {
                var link = document.createElement('a');
                link.href = url;

                return link.origin + link.pathname.replace(/\/+$/, '').toLowerCase();
            }

            var currentKey = sidebarUrlKey(window.location.href);
            var activeLink = $();

            $('.sidebar-menu a[href]').each(function() {
                if (sidebarUrlKey(this.href) === currentKey) {
                    activeLink = $(this);
                    return false;
                }
            });

            activeLink.addClass('active');
            activeLink.parent().addClass('active');
        })();
    </script>
  </body>
</html>
