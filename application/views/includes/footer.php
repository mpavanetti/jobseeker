    <footer class="main-footer">
        <div class="pull-right hidden-xs">
          <b>Job Seeker</b> Admin System | Version 1.0
        </div>
        <strong>Copyright &copy; 2019-<?php echo date("Y")?> <a href="https://www.linkedin.com/in/matheuspavanetti/">Matheus Pavanetti</a>.</strong> All rights reserved.<span style="margin-left: 100px;"></span>
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

        function isDataTableInitialized(table) {
            return $.fn.dataTable && $.fn.dataTable.isDataTable && $.fn.dataTable.isDataTable(table);
        }

        function initializeDataTable(selector, options) {
            $(selector).each(function() {
                if (! isDataTableInitialized(this)) {
                    $(this).DataTable(options);
                }
            });
        }

         initializeDataTable('.dataTable', {
            "order": [[ 1, "desc" ]],
            lengthMenu:  [ 10, 20, 50, 100, 200, 500]
        });

         $('.dataTable2').each(function() {
            var dataTable2Options = {
                "scrollX": true,
                "order": [[ 1, "desc" ]],
                lengthMenu:  [ 10, 20, 50, 100, 200, 500]
            };

            if ($(this).find('thead th').length > 12) {
                dataTable2Options.columnDefs = [
                    { width: 50, targets: 12 }
                ];
            }

            if (! isDataTableInitialized(this)) {
                $(this).DataTable(dataTable2Options);
            }
        });

          initializeDataTable('.dataTableMobile', {
            "scrollX": true,
            "order": [[ 1, "desc" ]],
            lengthMenu:  [ 10, 20, 50, 100, 200, 500]
        });

        initializeDataTable('#table3', {
            "scrollX": true,
            columnDefs: [
            { width: 50, targets: 14 },
            { width: 200, targets: 12 }
        ],

            "order": [[ 1, "desc" ]]
        });

        initializeDataTable('#table4', {
            "scrollX": true,

            "order": [[ 1, "desc" ]]
        });

        initializeDataTable('#table5', {
            "scrollX": true,
            columnDefs: [
            { width: 300, targets: 8 }
        ],

            "order": [[ 1, "desc" ]]
        });

        initializeDataTable('#table6', {
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
            $('.sidebar-menu li.active').parents('li.treeview').addClass('active menu-open').children('.treeview-menu').show();
        })();
        </script>

        <script type="text/javascript">
            (function() {
                var storageKey = 'jobseeker.sidebar.state';

                function storageAvailable() {
                    try {
                        window.localStorage.setItem('jobseeker.sidebar.test', '1');
                        window.localStorage.removeItem('jobseeker.sidebar.test');
                        return true;
                    } catch (error) {
                        return false;
                    }
                }

                function isDesktopLayout() {
                    return window.matchMedia ? window.matchMedia('(min-width: 768px)').matches : $(window).width() >= 768;
                }

                function savedState() {
                    if (!storageAvailable()) {
                        return '';
                    }

                    return window.localStorage.getItem(storageKey) || '';
                }

                function saveState(state) {
                    if (storageAvailable()) {
                        window.localStorage.setItem(storageKey, state);
                    }
                }

                function applySidebarPreference() {
                    if (!isDesktopLayout()) {
                        return;
                    }

                    if (savedState() === 'collapsed') {
                        $('body').addClass('sidebar-collapse');
                    } else {
                        $('body').removeClass('sidebar-collapse');
                    }
                }

                $(function() {
                    applySidebarPreference();

                    $(document).on('expanded.pushMenu collapsed.pushMenu', 'body', function() {
                        if (isDesktopLayout()) {
                            saveState($('body').hasClass('sidebar-collapse') ? 'collapsed' : 'expanded');
                        }
                    });

                    $('[data-toggle="push-menu"]').on('click', function() {
                        window.setTimeout(function() {
                            if (isDesktopLayout()) {
                                saveState($('body').hasClass('sidebar-collapse') ? 'collapsed' : 'expanded');
                            }
                        }, 0);
                    });

                    $(window).on('resize', applySidebarPreference);
                });
            })();
    </script>
  </body>
</html>
