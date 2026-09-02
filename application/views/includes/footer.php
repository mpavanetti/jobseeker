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
    <script src="<?php echo base_url(); ?>assets/bower_components/jquery-ui/jquery-ui.min.js" type="text/javascript"></script>
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
            "scrollCollapse": true,

           columnDefs: [
            { width: 100, targets: 5 },
            { width: 100, targets: 9 },
            { width: 100, targets: 10 },
            { width: 100, targets: 11 }
        ],
        "order": [[ 0, "desc" ]],
        "initComplete": function() {
            var table = this.api();
            window.setTimeout(function() {
                table.columns.adjust();
            }, 350);
        }
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

                function isDesktopLayout() {
                    return window.matchMedia ? window.matchMedia('(min-width: 768px)').matches : $(window).width() >= 768;
                }

                function shouldCollapseSidebar() {
                    var path = String(window.location.pathname || '').replace(/^\/+|\/+$/g, '').toLowerCase();
                    return path === 'pipelines' || path === 'jobcreation' || path === 'dockermonitoring';
                }

                function applySidebarPreference() {
                    if (!isDesktopLayout()) {
                        return;
                    }

                    $('body').toggleClass('sidebar-collapse', shouldCollapseSidebar());
                }

                $(function() {
                    applySidebarPreference();
                    $(window).on('resize', applySidebarPreference);
                });
            })();
    </script>

    <script type="text/javascript">
        (function() {
            var runningTimer = null;
            var requestInFlight = null;
            var requestSerial = 0;
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

            function normalizeEnvironment(value) {
                var raw = $.trim(String(value == null ? '' : value));

                if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
                    return window.JobSeekerGlobalEnvironment.normalize(raw || 'all');
                }

                if (raw === '' || raw === '*' || raw.toLowerCase() === 'all') {
                    return 'all';
                }

                raw = raw.toUpperCase();
                if (raw === 'QAS') {
                    return 'QA';
                }
                if (raw === 'PRD' || raw === 'PRODUCTION') {
                    return 'PROD';
                }
                if (raw === 'HOMOLOG' || raw === 'HOMOLOGATION') {
                    return 'HML';
                }

                return raw;
            }

            function selectedEnvironment(value) {
                if (typeof value !== 'undefined' && value !== null) {
                    return normalizeEnvironment(value);
                }

                if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.selected) {
                    return normalizeEnvironment(window.JobSeekerGlobalEnvironment.selected());
                }

                var bodyEnvironment = $('body').attr('data-jobseeker-environment');
                return normalizeEnvironment(bodyEnvironment || 'all');
            }

            function isAllEnvironment(environment) {
                environment = normalizeEnvironment(environment);
                return environment === '' || environment === 'all' || environment === 'ALL' || environment === '__UNKNOWN__';
            }

            function updateScope(environment) {
                var scope = isAllEnvironment(environment) ? 'All' : normalizeEnvironment(environment);
                $('#sidebarRunningJobsScope').text(scope ? '(' + scope + ')' : '');
            }

            function runningJobsRequestData(environment) {
                var data = {limit: 5};

                if (! isAllEnvironment(environment)) {
                    data.environment = normalizeEnvironment(environment);
                }

                return data;
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

            function render(payload, environment) {
                var container = $('#sidebarRunningJobsList');
                var environments = payload && payload.environments ? payload.environments : {};
                var names = Object.keys(environments).filter(function(environmentName) {
                    return environments[environmentName] && parseInt(environments[environmentName].running, 10) > 0;
                }).sort(environmentSort);
                var html = '';

                environment = selectedEnvironment(environment);
                updateScope(environment);

                if (! container.length) {
                    return;
                }

                if (! payload || payload.ok !== true) {
                    container.html('<div class="jobseeker-sidebar-running-error">Unable to load running jobs.</div>');
                    return;
                }

                if (! names.length) {
                    var emptyText = isAllEnvironment(environment) ? 'No running Jenkins jobs.' : 'No running ' + normalizeEnvironment(environment) + ' Jenkins jobs.';
                    container.html('<div class="jobseeker-sidebar-running-empty">' + escapeHtml(emptyText) + '</div>');
                    return;
                }

                $.each(names, function(index, environmentName) {
                    var group = environments[environmentName] || {};
                    var builds = Array.isArray(group.builds) ? group.builds : [];
                    html += '<div class="jobseeker-sidebar-running-env">' +
                        '<div class="jobseeker-sidebar-running-env-header">' +
                          '<span class="label label-' + environmentBadgeClass(environmentName) + '">' + escapeHtml(environmentName) + '</span>' +
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

            function refreshRunningJobs(environment, force) {
                var requestedEnvironment = selectedEnvironment(environment);
                var currentSerial;

                if (! $('#sidebarRunningJobsList').length) {
                    return;
                }

                updateScope(requestedEnvironment);

                if (requestInFlight && requestInFlight.readyState !== 4) {
                    if (! force) {
                        return;
                    }

                    requestInFlight.abort();
                }

                currentSerial = ++requestSerial;
                $('#sidebarRunningJobsRefresh i').addClass('fa-spin');
                requestInFlight = $.getJSON(endpoint(), runningJobsRequestData(requestedEnvironment))
                    .done(function(payload) {
                        if (currentSerial !== requestSerial) {
                            return;
                        }

                        render(payload, requestedEnvironment);
                    })
                    .fail(function(xhr, textStatus) {
                        if (textStatus === 'abort' || currentSerial !== requestSerial) {
                            return;
                        }

                        $('#sidebarRunningJobsList').html('<div class="jobseeker-sidebar-running-error">Unable to load running jobs.</div>');
                    })
                    .always(function() {
                        if (currentSerial === requestSerial) {
                            $('#sidebarRunningJobsRefresh i').removeClass('fa-spin');
                        }
                    });
            }

            window.JobSeekerRunningJobs = {
                refresh: function(environment) {
                    refreshRunningJobs(environment, true);
                }
            };

            $(function() {
                if (! $('#sidebarRunningJobsList').length) {
                    return;
                }

                refreshRunningJobs();
                runningTimer = window.setInterval(function() {
                    refreshRunningJobs();
                }, refreshMs);
                $('#sidebarRunningJobsRefresh').on('click', function() {
                    refreshRunningJobs(null, true);
                });
                $(document).on('jobseeker:environment-change', function(event, environment) {
                    refreshRunningJobs(environment, true);
                });
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
