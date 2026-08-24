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

    <script type="text/javascript">
        (function() {
            var runningTimer = null;
            var requestInFlight = null;
            var refreshMs = 15000;
            var preferredEnvironmentOrder = ['DEV', 'QA', 'UAT', 'PREPROD', 'HML', 'PROD', 'UNKNOWN'];

            function endpoint() {
                return window.jobseekerRunningBuildsUrl || ((window.baseURL || baseURL || '/') + 'jenkins/runningBuilds');
            }

            function escapeHtml(value) {
                return String(value == null ? '' : value).replace(/[&<>'"]/g, function(character) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        "'": '&#039;',
                        '"': '&quot;'
                    }[character];
                });
            }

            function environmentBadgeClass(environment) {
                if (window.JobSeekerEnvironment && window.JobSeekerEnvironment.badgeClass) {
                    return window.JobSeekerEnvironment.badgeClass(environment);
                }

                return environment === 'PROD' ? 'danger' : (environment === 'DEV' ? 'info' : 'default');
            }

            function formatElapsed(milliseconds, timestamp) {
                milliseconds = parseInt(milliseconds, 10);
                timestamp = parseInt(timestamp, 10);

                if ((! milliseconds || milliseconds < 0) && timestamp > 0) {
                    milliseconds = Date.now() - timestamp;
                }

                if (! milliseconds || milliseconds < 0) {
                    return 'Starting';
                }

                var totalSeconds = Math.floor(milliseconds / 1000);
                var hours = Math.floor(totalSeconds / 3600);
                var minutes = Math.floor((totalSeconds % 3600) / 60);
                var seconds = totalSeconds % 60;

                if (hours > 0) {
                    return hours + 'h ' + minutes + 'm';
                }

                if (minutes > 0) {
                    return minutes + 'm ' + seconds + 's';
                }

                return seconds + 's';
            }

            function jobExecutionUrl(build) {
                var target;
                try {
                    target = new URL((window.baseURL || baseURL || '/') + 'jobExecution', window.location.href);
                    target.searchParams.set('job', build.jobName || build.job || '');
                    target.searchParams.set('build', build.buildNumber || build.number || '');
                    target.searchParams.set('environment', build.environment || 'UNKNOWN');
                    return target.toString();
                } catch (error) {
                    return (window.baseURL || baseURL || '/') + 'jobExecution?job=' + encodeURIComponent(build.jobName || build.job || '') + '&build=' + encodeURIComponent(build.buildNumber || build.number || '') + '&environment=' + encodeURIComponent(build.environment || 'UNKNOWN');
                }
            }

            function environmentSort(left, right) {
                var leftIndex = preferredEnvironmentOrder.indexOf(left);
                var rightIndex = preferredEnvironmentOrder.indexOf(right);

                if (leftIndex !== -1 || rightIndex !== -1) {
                    leftIndex = leftIndex === -1 ? 999 : leftIndex;
                    rightIndex = rightIndex === -1 ? 999 : rightIndex;
                    return leftIndex - rightIndex;
                }

                return left.localeCompare(right);
            }

            function render(payload) {
                var container = $('#sidebarRunningJobsList');
                var environments = payload && payload.environments ? payload.environments : {};
                var names = Object.keys(environments).filter(function(environment) {
                    return environments[environment] && parseInt(environments[environment].running, 10) > 0;
                }).sort(environmentSort);
                var html = '';

                if (! container.length) {
                    return;
                }

                if (! payload || payload.ok !== true) {
                    container.html('<div class="jobseeker-sidebar-running-error">Unable to load running jobs.</div>');
                    return;
                }

                if (! names.length) {
                    container.html('<div class="jobseeker-sidebar-running-empty">No running Jenkins jobs.</div>');
                    return;
                }

                $.each(names, function(index, environment) {
                    var group = environments[environment] || {};
                    var builds = Array.isArray(group.builds) ? group.builds : [];
                    html += '<div class="jobseeker-sidebar-running-env">' +
                        '<div class="jobseeker-sidebar-running-env-header">' +
                          '<span class="label label-' + environmentBadgeClass(environment) + '">' + escapeHtml(environment) + '</span>' +
                          '<small>' + escapeHtml(group.running || builds.length) + ' running</small>' +
                        '</div>';

                    $.each(builds, function(buildIndex, build) {
                        html += '<a class="jobseeker-sidebar-running-build" href="' + escapeHtml(jobExecutionUrl(build)) + '" title="Open live console for ' + escapeHtml(build.jobName || build.job || '') + '">' +
                            '<strong>' + escapeHtml(build.rank || buildIndex + 1) + '. ' + escapeHtml(build.jobName || build.job || '') + '</strong>' +
                            '<small>#' + escapeHtml(build.buildNumber || build.number || '?') + ' - ' + escapeHtml(formatElapsed(build.elapsedMs, build.timestamp)) + ' elapsed</small>' +
                          '</a>';
                    });

                    html += '</div>';
                });

                container.html(html);
            }

            function refreshRunningJobs() {
                if (! $('#sidebarRunningJobsList').length) {
                    return;
                }

                if (requestInFlight && requestInFlight.readyState !== 4) {
                    return;
                }

                $('#sidebarRunningJobsRefresh i').addClass('fa-spin');
                requestInFlight = $.getJSON(endpoint(), {limit: 5})
                    .done(render)
                    .fail(function() {
                        $('#sidebarRunningJobsList').html('<div class="jobseeker-sidebar-running-error">Unable to load running jobs.</div>');
                    })
                    .always(function() {
                        $('#sidebarRunningJobsRefresh i').removeClass('fa-spin');
                    });
            }

            window.JobSeekerRunningJobs = {
                refresh: refreshRunningJobs
            };

            $(function() {
                if (! $('#sidebarRunningJobsList').length) {
                    return;
                }

                refreshRunningJobs();
                runningTimer = window.setInterval(refreshRunningJobs, refreshMs);
                $('#sidebarRunningJobsRefresh').on('click', function() {
                    refreshRunningJobs();
                });
                $(document).on('jobseeker:environment-change', refreshRunningJobs);
                $(window).on('beforeunload', function() {
                    if (runningTimer) {
                        window.clearInterval(runningTimer);
                    }
                });
            });
        })();
    </script>
  </body>
</html>
