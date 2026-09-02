<?php
if (! function_exists('jobseeker_normalize_global_environment')) {
  function jobseeker_normalize_global_environment($value) {
    $normalized = strtoupper(trim((string) $value));
    $aliases = array(
      'QAS' => 'QA',
      'PRD' => 'PROD',
      'PRODUCTION' => 'PROD',
      'HOMOLOG' => 'HML',
      'HOMOLOGATION' => 'HML'
    );

    return isset($aliases[$normalized]) ? $aliases[$normalized] : $normalized;
  }
}

$jobseekerGlobalEnvironmentOptions = array();
if (class_exists('CI_Model')) {
  $CI =& get_instance();
  $CI->load->model('Context_model');
  $environmentRecords = $CI->Context_model->listEnvironments();

  if (! empty($environmentRecords)) {
    foreach ($environmentRecords as $environmentRecord) {
      $environmentName = isset($environmentRecord->Environment) ? trim((string) $environmentRecord->Environment) : '';
      if ($environmentName !== '') {
        $jobseekerGlobalEnvironmentOptions[] = $environmentName;
      }
    }
  }
}

$jobseekerGlobalEnvironmentOptions = array_values(array_unique($jobseekerGlobalEnvironmentOptions));
sort($jobseekerGlobalEnvironmentOptions);
$jobseekerGlobalEnvironmentOptionValues = array_values(array_unique(array_map('jobseeker_normalize_global_environment', $jobseekerGlobalEnvironmentOptions)));
$jobseekerCurrentController = strtolower((string) $this->router->fetch_class());
$jobseekerCurrentMethod = strtolower((string) $this->router->fetch_method());
$jobseekerExecutorMonitorActive = $jobseekerCurrentController === 'jobexecution' && $jobseekerCurrentMethod === 'executors';
$jobseekerDockerMonitorActive = $jobseekerCurrentController === 'dockermonitoring';
$jobseekerTransactionMonitorActive = $jobseekerCurrentController === 'tmf';
$jobseekerMonitoringActive = $jobseekerExecutorMonitorActive || $jobseekerDockerMonitorActive;
$jobseekerMlControllers = array('mloverview', 'mlruntimes', 'mlsamples', 'mldatasets', 'mljobs', 'mlruns', 'mlmodels', 'mlmonitoring');
$jobseekerMlActive = in_array($jobseekerCurrentController, $jobseekerMlControllers, TRUE);
$jobseekerMlEnabled = strtolower((string) (getenv('JOBSEEKER_ML_PLATFORM_ENABLED') ?: 'true')) !== 'false';
$jobseekerSelectedEnvironment = isset($selectedEnvironment) ? $selectedEnvironment : $this->input->get('environment', TRUE);
if (trim((string) $jobseekerSelectedEnvironment) === '') {
  $jobseekerPreferenceUserId = preg_replace('/[^0-9]/', '', (string) (isset($user_id) ? $user_id : ''));
  $jobseekerPreferenceUserId = $jobseekerPreferenceUserId === '' ? 'anonymous' : $jobseekerPreferenceUserId;
  $jobseekerSelectedEnvironment = $this->input->cookie('jobseeker_global_environment_user_'.$jobseekerPreferenceUserId, TRUE);
}
$jobseekerSelectedEnvironment = trim((string) $jobseekerSelectedEnvironment);
if ($jobseekerSelectedEnvironment === '' || $jobseekerSelectedEnvironment === '*' || strtolower($jobseekerSelectedEnvironment) === 'all') {
  $jobseekerSelectedEnvironment = 'all';
} elseif ($jobseekerSelectedEnvironment === '__UNKNOWN__' || strtolower($jobseekerSelectedEnvironment) === 'unknown') {
  $jobseekerSelectedEnvironment = 'all';
} else {
  $jobseekerSelectedEnvironment = jobseeker_normalize_global_environment($jobseekerSelectedEnvironment);
  if (! in_array($jobseekerSelectedEnvironment, $jobseekerGlobalEnvironmentOptionValues, TRUE)) {
    $jobseekerSelectedEnvironment = 'all';
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <link rel="shortcut icon" type="image/png" href="<?php echo base_url(); ?>assets/images/bi.png" sizes="16x16"/>
  <title><?php echo $pageTitle; ?></title>
  <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
  <!-- Bootstrap 3.3.4 -->
  <link href="<?php echo base_url(); ?>assets/bower_components/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <!-- FontAwesome 4.3.0 -->
  <link href="<?php echo base_url(); ?>assets/bower_components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
  <!-- Ionicons 2.0.0 -->
  <link href="<?php echo base_url(); ?>assets/bower_components/Ionicons/css/ionicons.min.css" rel="stylesheet" type="text/css" />
  <!-- Theme style -->
  <link href="<?php echo base_url(); ?>assets/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
  <!-- AdminLTE Skins. Choose a skin from the css/skins  folder instead of downloading all of them to reduce the load. -->
  <link href="<?php echo base_url(); ?>assets/dist/css/skins/_all-skins.min.css" rel="stylesheet" type="text/css" />
  <!-- Animate.css -->
  <link href="<?php echo base_url(); ?>assets/plugins/animate/animate.min.css" rel="stylesheet" type="text/css" />
  <!-- Toastr.css -->
  <link href="<?php echo base_url(); ?>assets/plugins/toastr/build/toastr.min.css" rel="stylesheet" type="text/css" />
  <!-- Datatable -->
  <link rel="stylesheet" href="<?php echo base_url(); ?>assets/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
  <!-- Alertify Js -->
  <link href="<?php echo base_url(); ?>assets/plugins/alertify/css/alertify.min.css" rel="stylesheet" type="text/css" />
  <link href="<?php echo base_url(); ?>assets/plugins/alertify/css/themes/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link href="<?php echo base_url(); ?>assets/dist/css/job-console-groups.css?v=2" rel="stylesheet" type="text/css" />
  <!-- jQuery UI -->
  <link href="<?php echo base_url(); ?>assets/bower_components/jquery-ui-1.12.1/jquery-ui.min.css" rel="stylesheet" type="text/css" />
  <link href="<?php echo base_url(); ?>assets/bower_components/jquery-ui-1.12.1/jquery-ui.theme.min.css" rel="stylesheet" type="text/css" />
  <style>
   .error{
    color:red;
    font-weight: normal;
  }
  .jt-toggle { display: inline-flex; border: 1px solid #c8d0da; border-radius: 4px; overflow: hidden; vertical-align: middle; }
  .jt-toggle .jt-btn { border: 0; background: #fff; color: #5a6b7b; font-size: 11px; line-height: 1; padding: 3px 8px; cursor: pointer; }
  .jt-toggle .jt-btn + .jt-btn { border-left: 1px solid #c8d0da; }
  .jt-toggle .jt-btn.is-active { background: #3c8dbc; color: #fff; }
  .jt-toggle .jt-btn:focus { outline: 2px solid #8ab8d6; outline-offset: -2px; }
</style>
<script src="<?php echo base_url(); ?>assets/bower_components/jquery/dist/jquery-3.4.1.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/job-environment.js?v=1" type="text/javascript"></script>
<script src="<?php echo base_url(); ?>assets/js/job-console-groups.js?v=4" type="text/javascript"></script>
<script type="text/javascript">
  window.jobseekerTime = {
    serverTimezone: 'UTC',
    displayTimezone: <?php echo json_encode($this->config->item('jobseeker_display_timezone') ?: 'UTC'); ?>
  };
</script>
<script src="<?php echo base_url(); ?>assets/js/jobseeker-time.js?v=1" type="text/javascript"></script>
<script type="text/javascript">
  var baseURL = "<?php echo base_url(); ?>";
  window.jobseekerUserId = <?php echo json_encode(isset($user_id) ? $user_id : ''); ?>;
  window.jobseekerCsrf = {
    name: <?php echo json_encode($this->security->get_csrf_token_name()); ?>,
    hash: <?php echo json_encode($this->security->get_csrf_hash()); ?>
  };
  window.jobseekerJenkinsUrl = <?php echo json_encode($jenkins_url); ?>;
  window.jobseekerJenkinsProxyUrl = <?php echo json_encode(base_url() . 'jenkins/proxy'); ?>;
  window.jobseekerRunningBuildsUrl = <?php echo json_encode(base_url() . 'jenkins/runningBuilds'); ?>;
  window.jobseekerGlobalEnvironmentOptions = <?php echo json_encode($jobseekerGlobalEnvironmentOptions); ?>;
  window.jobseekerDashboardEnvironment = <?php echo json_encode($jobseekerSelectedEnvironment); ?>;

  function addJobseekerCsrfToForm(form) {
    if (! window.jobseekerCsrf || ! window.jobseekerCsrf.name || ! window.jobseekerCsrf.hash) {
      return;
    }

    var $form = $(form);
    if (($form.attr('method') || '').toLowerCase() !== 'post') {
      return;
    }

    var selector = 'input[name="' + window.jobseekerCsrf.name + '"]';
    if ($form.find(selector).length === 0) {
      $('<input>', { type: 'hidden', name: window.jobseekerCsrf.name, value: window.jobseekerCsrf.hash }).appendTo($form);
    }
  }

  function addJobseekerCsrfToForms(root) {
    $(root).find('form').addBack('form').each(function() {
      addJobseekerCsrfToForm(this);
    });
  }

  function appendJobseekerCsrfToUrl(url) {
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    return url + separator + encodeURIComponent(window.jobseekerCsrf.name) + '=' + encodeURIComponent(window.jobseekerCsrf.hash);
  }

  function addJobseekerCsrfToAjax(options) {
    var method = (options.type || options.method || 'GET').toUpperCase();
    if ($.inArray(method, ['GET', 'HEAD', 'OPTIONS', 'TRACE']) !== -1 || ! window.jobseekerCsrf) {
      return;
    }

    if (window.FormData && options.data instanceof FormData) {
      if (! options.data.has(window.jobseekerCsrf.name)) {
        options.data.append(window.jobseekerCsrf.name, window.jobseekerCsrf.hash);
      }
      return;
    }

    if (typeof options.data === 'string') {
      if (options.data.indexOf(encodeURIComponent(window.jobseekerCsrf.name) + '=') === -1 && options.data.indexOf(window.jobseekerCsrf.name + '=') === -1) {
        options.data += (options.data.length ? '&' : '') + encodeURIComponent(window.jobseekerCsrf.name) + '=' + encodeURIComponent(window.jobseekerCsrf.hash);
      }
      return;
    }

    if ($.isPlainObject(options.data)) {
      options.data[window.jobseekerCsrf.name] = window.jobseekerCsrf.hash;
      return;
    }

    options.data = encodeURIComponent(window.jobseekerCsrf.name) + '=' + encodeURIComponent(window.jobseekerCsrf.hash);
  }

  window.JobSeekerGlobalEnvironment = (function() {
    var preferenceUserId = String(window.jobseekerUserId == null || window.jobseekerUserId === '' ? 'anonymous' : window.jobseekerUserId).replace(/[^0-9]/g, '') || 'anonymous';
    var storageKey = 'jobseeker.global.environment.user.' + preferenceUserId;
    var cookieName = 'jobseeker_global_environment_user_' + preferenceUserId;
    var applying = false;
    var retryTimer = null;
    var dashboardRedirecting = false;

    function configuredEnvironmentNames() {
      var names = [];

      $.each(window.jobseekerGlobalEnvironmentOptions || [], function(index, value) {
        var normalized = normalize(value);
        if (normalized !== '' && $.inArray(normalized, names) === -1) {
          names.push(normalized);
        }
      });

      return names;
    }

    function readStored() {
      try {
        return window.localStorage.getItem(storageKey) || 'all';
      } catch (error) {
        return 'all';
      }
    }

    function store(value) {
      try {
        window.localStorage.setItem(storageKey, value || 'all');
      } catch (error) {
        // The non-sensitive environment cookie still keeps server-rendered pages aligned.
      }
      document.cookie = cookieName + '=' + encodeURIComponent(value || 'all') + '; path=/; max-age=31536000; SameSite=Lax' + (window.location.protocol === 'https:' ? '; Secure' : '');
    }

    function normalize(value) {
      var raw = $.trim(String(value || ''));

      if (raw === '' || raw === '*' || raw.toLowerCase() === 'all') {
        return 'all';
      }

      if (raw === '__UNKNOWN__' || raw.toLowerCase() === 'unknown') {
        return '__UNKNOWN__';
      }

      if (window.JobSeekerEnvironment && window.JobSeekerEnvironment.normalize) {
        return window.JobSeekerEnvironment.normalize(raw) || raw.toUpperCase();
      }

      return raw.toUpperCase();
    }

    function hasGlobalOption(value) {
      value = normalize(value);
      return value === 'all' || $.inArray(value, configuredEnvironmentNames()) !== -1;
    }

    function coerceToOption(value) {
      value = normalize(value);
      return hasGlobalOption(value) ? value : 'all';
    }

    function isConfiguredEnvironment(value) {
      value = normalize(value);
      return value !== '' && value !== 'all' && value !== '__UNKNOWN__' && $.inArray(value, configuredEnvironmentNames()) !== -1;
    }

    function valuesForSelection(value) {
      var normalized = normalize(value);
      var values = [];

      if (normalized === 'all') {
        return ['all', '*'];
      }

      if (normalized === '__UNKNOWN__') {
        return ['__UNKNOWN__', 'Unknown'];
      }

      values.push(String(value || ''));
      values.push(normalized);

      if (normalized === 'QA') {
        values.push('QAS');
      }

      if (normalized === 'PROD') {
        values.push('PRD', 'PRODUCTION');
      }

      if (normalized === 'HML') {
        values.push('HOMOLOG', 'HOMOLOGATION');
      }

      return $.grep(values, function(item, index) {
        return item !== '' && $.inArray(item, values) === index;
      });
    }

    function currentUrlEnvironment() {
      try {
        var url = new URL(window.location.href);
        return url.searchParams.has('environment') ? normalize(url.searchParams.get('environment')) : '';
      } catch (error) {
        return '';
      }
    }

    function isDashboardPath() {
      return /\/dashboard\/?$/i.test(window.location.pathname) || /\/Dashboard\/?$/.test(window.location.pathname) || window.location.pathname.replace(/\/+$/, '') === '';
    }

    function syncDashboardUrl(value) {
      value = normalize(value);

      if (! isDashboardPath()) {
        return false;
      }

      try {
        var url = new URL(window.location.href);
        var currentValue = url.searchParams.has('environment') ? normalize(url.searchParams.get('environment')) : 'all';

        if (currentValue === value) {
          return false;
        }

        if (value === 'all') {
          url.searchParams.delete('environment');
        } else {
          url.searchParams.set('environment', value);
        }

        dashboardRedirecting = true;
        window.jobseekerDashboardEnvironmentRedirecting = true;
        window.location.replace(url.toString());
        return true;
      } catch (error) {
        return false;
      }
    }

    function configuredBaseUrl() {
      return window.baseURL || (typeof baseURL !== 'undefined' ? baseURL : '/');
    }

    function appUrl(path) {
      var base = String(configuredBaseUrl()).replace(/\/+$/, '');
      return base + '/' + String(path || '').replace(/^\/+/, '');
    }

    function normalizePathname(pathname) {
      var path = String(pathname || '/');

      try {
        var basePath = new URL(configuredBaseUrl(), window.location.href).pathname.replace(/\/+$/, '');
        var lowerPath = path.toLowerCase();
        var lowerBasePath = basePath.toLowerCase();

        if (basePath && basePath !== '/' && lowerPath === lowerBasePath) {
          path = '/';
        } else if (basePath && basePath !== '/' && lowerPath.indexOf(lowerBasePath + '/') === 0) {
          path = path.substring(basePath.length);
        }
      } catch (error) {
        path = String(pathname || '/');
      }

      path = path.replace(/\/index\.php/i, '').replace(/\/+$/, '').toLowerCase();
      return path || '/';
    }

    function normalizedPath() {
      return normalizePathname(window.location.pathname);
    }

    function isClientEnvironmentHandledPath() {
      var path = normalizedPath();
      return path === '/joblist'
        || path === '/joblist/full'
        || path === '/jobexecution'
        || path === '/jobexecution/executors'
        || path === '/jobview'
        || path === '/deletejob'
        || path === '/jobcreation'
        || path === '/context/promotion';
    }

    function environmentReloadUrl() {
      var path = normalizedPath();
      if (path === '/tmf/fetchdata') {
        return appUrl('Tmf/data');
      }

      return window.location.href;
    }

    function syncCurrentEnvironmentUrl(value) {
      value = normalize(value);

      if (isClientEnvironmentHandledPath()) {
        return false;
      }

      try {
        var url = new URL(environmentReloadUrl(), window.location.href);
        var currentValue = url.searchParams.has('environment') ? normalize(url.searchParams.get('environment')) : 'all';
        var targetPath = normalizePathname(url.pathname);
        var currentPath = normalizedPath();

        if (currentValue === value && targetPath === currentPath) {
          return false;
        }

        if (value === 'all') {
          url.searchParams.delete('environment');
        } else {
          url.searchParams.set('environment', value);
        }

        dashboardRedirecting = true;
        window.jobseekerDashboardEnvironmentRedirecting = true;
        window.location.replace(url.toString());
        return true;
      } catch (error) {
        return false;
      }
    }

    function syncEnvironmentUrl(value, allowGenericReload) {
      if (syncDashboardUrl(value)) {
        return true;
      }

      return allowGenericReload === true && syncCurrentEnvironmentUrl(value);
    }

    function initialEnvironment() {
      var urlEnvironment = currentUrlEnvironment();
      var serverEnvironment = normalize(window.jobseekerDashboardEnvironment || '');

      if (urlEnvironment) {
        return coerceToOption(urlEnvironment);
      }

      if (serverEnvironment !== 'all') {
        return coerceToOption(serverEnvironment);
      }

      return coerceToOption(readStored());
    }

    function matchingValue($select, values) {
      var selectedValue = '';

      $.each(values, function(index, value) {
        if (selectedValue !== '') {
          return false;
        }

        $select.find('option').each(function() {
          if ($(this).val() === value) {
            selectedValue = value;
            return false;
          }
        });
      });

      return selectedValue;
    }

    function syncControl($select, value) {
      var id = $select.attr('id') || '';
      var name = $select.attr('name') || '';
      var isFilter = id === 'monitorEnvironmentFilter' || id === 'jobEnvironmentFilter' || id === 'deleteEnvironmentFilter';
      var isTmfMulti = name === 'environment[]';
      var isJobEnvironment = id === 'environment';
      var normalized = normalize(value);
      var targetValue = matchingValue($select, valuesForSelection(value));

      if (! isFilter && ! isTmfMulti && ! isJobEnvironment) {
        return false;
      }

      if ((normalized === 'all' || normalized === '__UNKNOWN__') && isJobEnvironment) {
        return false;
      }

      if (! targetValue) {
        return false;
      }

      applying = true;
      if (isTmfMulti) {
        if (($select.val() || []).join('|') !== targetValue) {
          $select.val([targetValue]).trigger('change');
        }
      } else if ($select.val() !== targetValue) {
        $select.val(targetValue).trigger('change');
      }
      applying = false;
      return true;
    }

    function applyEnvironmentTheme(value) {
      var environment = coerceToOption(value || readStored());
      $('body').attr('data-jobseeker-environment', environment);
    }

    function syncControls(value) {
      value = coerceToOption(value || readStored());

      if (dashboardRedirecting) {
        return;
      }

      applyEnvironmentTheme(value);

      $('#monitorEnvironmentFilter, #jobEnvironmentFilter, #deleteEnvironmentFilter, #environment, select[name="environment[]"]').each(function() {
        syncControl($(this), value);
      });
    }

    function apply(value, notify) {
      value = coerceToOption(value || readStored());

      syncControls(value);

      if (notify !== false && ! dashboardRedirecting) {
        $(document).trigger('jobseeker:environment-change', [value]);
      }
    }

    function scheduleApply(value, allowGenericReload) {
      var attempts = 0;
      value = coerceToOption(value || readStored());
      clearInterval(retryTimer);
      if (syncEnvironmentUrl(value, allowGenericReload)) {
        return;
      }
      apply(value);
      retryTimer = setInterval(function() {
        attempts += 1;
        if (syncEnvironmentUrl(value, allowGenericReload)) {
          clearInterval(retryTimer);
          return;
        }
        syncControls(value);

        if (attempts >= 12) {
          clearInterval(retryTimer);
        }
      }, 500);
    }

    function set(value) {
      var normalized = coerceToOption(value);
      store(normalized);
      $('#globalEnvironmentSelector').val(normalized);
      scheduleApply(normalized, true);
    }

    $(document).on('change', '#globalEnvironmentSelector', function() {
      set($(this).val());
    });

    $(document).on('change', '#monitorEnvironmentFilter, #jobEnvironmentFilter, #deleteEnvironmentFilter', function() {
      if (applying) {
        return;
      }

      var value = normalize($(this).val() || 'all');
      store(value);
      $('#globalEnvironmentSelector').val(value);
      apply(value);
    });

    $(function() {
      var selected = initialEnvironment();
      store(selected);
      $('#globalEnvironmentSelector').val(selected);
      scheduleApply(selected, false);
    });

    return {
      apply: apply,
      coerceToOption: coerceToOption,
      configuredEnvironmentNames: configuredEnvironmentNames,
      isConfiguredEnvironment: isConfiguredEnvironment,
      normalize: normalize,
      scheduleApply: scheduleApply,
      selected: function() { return coerceToOption($('#globalEnvironmentSelector').val() || readStored()); },
      set: set,
      applyEnvironmentTheme: applyEnvironmentTheme,
      syncControls: syncControls,
      storageKey: storageKey
    };
  })();

  $.ajaxPrefilter(function(options) {
    var jenkinsUrl = window.jobseekerJenkinsUrl || '';

    if (typeof options.url !== 'string') {
      return;
    }

    var normalizedUrl = jenkinsUrl.charAt(jenkinsUrl.length - 1) === '/' ? jenkinsUrl : jenkinsUrl + '/';

    if (jenkinsUrl && options.url.indexOf(normalizedUrl) === 0) {
      options.url = window.jobseekerJenkinsProxyUrl + '?path=' + encodeURIComponent(options.url.substring(normalizedUrl.length));

      var method = (options.type || options.method || 'GET').toUpperCase();
      if ($.inArray(method, ['GET', 'HEAD', 'OPTIONS', 'TRACE']) === -1 && window.jobseekerCsrf) {
        options.url = appendJobseekerCsrfToUrl(options.url);
      }

      if (options.headers) {
        delete options.headers.Authorization;
        delete options.headers.authorization;
      }
      return;
    }

    addJobseekerCsrfToAjax(options);
  });

  $(function() {
    addJobseekerCsrfToForms(document);
    $(document).on('submit', 'form', function() {
      addJobseekerCsrfToForm(this);
    });

    if (window.MutationObserver) {
      new MutationObserver(function(mutations) {
        $.each(mutations, function(index, mutation) {
          $.each(mutation.addedNodes, function(nodeIndex, node) {
            if (node.nodeType === 1) {
              addJobseekerCsrfToForms(node);
              if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.syncControls) {
                window.JobSeekerGlobalEnvironment.syncControls();
              }
            }
          });
        });
      }).observe(document.body, { childList: true, subtree: true });
    }
  });
</script>
<script src="<?php echo base_url(); ?>assets/js/job-draft-cache.js?v=3" type="text/javascript"></script>

<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
<style>
  body {
    --jobseeker-env-navbar-start: #4A00E0;
    --jobseeker-env-navbar-end: #8E2DE2;
    --jobseeker-env-navbar-hover: #500ceb;
    --jobseeker-env-accent: #5b21b6;
    --jobseeker-env-shadow: rgba(46, 12, 107, .18);
  }

  body[data-jobseeker-environment="DEV"] {
    --jobseeker-env-navbar-start: #0f4c81;
    --jobseeker-env-navbar-end: #2563eb;
    --jobseeker-env-navbar-hover: #1d4ed8;
    --jobseeker-env-accent: #1e40af;
    --jobseeker-env-shadow: rgba(30, 64, 175, .22);
  }

  body[data-jobseeker-environment="QA"] {
    --jobseeker-env-navbar-start: #047857;
    --jobseeker-env-navbar-end: #14b8a6;
    --jobseeker-env-navbar-hover: #0f766e;
    --jobseeker-env-accent: #047857;
    --jobseeker-env-shadow: rgba(4, 120, 87, .2);
  }

  body[data-jobseeker-environment="UAT"] {
    --jobseeker-env-navbar-start: #7c3aed;
    --jobseeker-env-navbar-end: #0ea5e9;
    --jobseeker-env-navbar-hover: #6d28d9;
    --jobseeker-env-accent: #6d28d9;
    --jobseeker-env-shadow: rgba(109, 40, 217, .22);
  }

  body[data-jobseeker-environment="PREPROD"],
  body[data-jobseeker-environment="HML"] {
    --jobseeker-env-navbar-start: #b45309;
    --jobseeker-env-navbar-end: #f59e0b;
    --jobseeker-env-navbar-hover: #92400e;
    --jobseeker-env-accent: #b45309;
    --jobseeker-env-shadow: rgba(180, 83, 9, .2);
  }

  body[data-jobseeker-environment="PROD"] {
    --jobseeker-env-navbar-start: #7f1d1d;
    --jobseeker-env-navbar-end: #dc2626;
    --jobseeker-env-navbar-hover: #991b1b;
    --jobseeker-env-accent: #991b1b;
    --jobseeker-env-shadow: rgba(153, 27, 27, .24);
  }

  body[data-jobseeker-environment="LOCAL"] {
    --jobseeker-env-navbar-start: #334155;
    --jobseeker-env-navbar-end: #64748b;
    --jobseeker-env-navbar-hover: #475569;
    --jobseeker-env-accent: #334155;
    --jobseeker-env-shadow: rgba(51, 65, 85, .2);
  }

  .skin-purple .main-header .navbar {
    background: var(--jobseeker-env-navbar-end) !important;
    background: -webkit-linear-gradient(to right, var(--jobseeker-env-navbar-start), var(--jobseeker-env-navbar-end)) !important;
    background: linear-gradient(to right, var(--jobseeker-env-navbar-start), var(--jobseeker-env-navbar-end)) !important;
  }

  .skin-purple .main-header .logo {
    background: var(--jobseeker-env-navbar-start) !important;

  }

  .skin-purple .main-header .navbar .sidebar-toggle:hover {
    background: var(--jobseeker-env-navbar-hover);
  }

  .skin-purple .main-header .logo:hover {
    background-color: var(--jobseeker-env-navbar-hover) !important;
  }

  .skin-purple .sidebar-menu>li.active>a {
    border-left-color: var(--jobseeker-env-navbar-hover);
  }

  .skin-purple .main-header li.user-header {
    background-color: var(--jobseeker-env-navbar-hover);
  }

  .jobseeker-global-environment {
    padding: 7px 10px;
    min-width: 255px;
  }

  .jobseeker-env-control {
    align-items: center;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.28);
    border-radius: 999px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.18), 0 8px 18px var(--jobseeker-env-shadow);
    display: flex;
    gap: 9px;
    min-height: 36px;
    padding: 5px 34px 5px 8px;
    position: relative;
  }

  .jobseeker-env-icon {
    align-items: center;
    background: rgba(255,255,255,.92);
    border-radius: 50%;
    color: var(--jobseeker-env-accent);
    display: inline-flex;
    height: 24px;
    justify-content: center;
    width: 24px;
  }

  .jobseeker-env-copy {
    flex: 1 1 auto;
    min-width: 0;
  }

  .jobseeker-global-environment label {
    color: rgba(255,255,255,.86);
    display: block;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0;
    line-height: 1;
    margin: 0;
  }

  .jobseeker-global-environment .form-control {
    appearance: none;
    -moz-appearance: none;
    -webkit-appearance: none;
    background: transparent;
    border: 0;
    box-shadow: none;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    height: 18px;
    line-height: 18px;
    padding: 0;
  }

  .jobseeker-global-environment .form-control option {
    color: #25364a;
  }

  .jobseeker-env-caret {
    color: rgba(255,255,255,.86);
    position: absolute;
    right: 13px;
    top: 12px;
  }

  .main-sidebar .sidebar {
    display: block;
    min-height: calc(100vh - 50px);
  }

  @media (min-width: 768px) {
    .main-sidebar {
      min-height: 100%;
    }
  }

  .main-sidebar .sidebar-menu {
    margin-bottom: 0;
  }

  .jobseeker-sidebar-running {
    border-top: 1px solid rgba(255,255,255,.08);
    color: #b8c7ce;
    margin: 12px 10px 14px;
    padding: 12px 10px 14px;
    position: static;
  }

  .jobseeker-sidebar-running-title {
    align-items: center;
    color: #fff;
    display: flex;
    font-size: 12px;
    font-weight: 700;
    justify-content: space-between;
    letter-spacing: 0;
    margin-bottom: 8px;
    text-transform: uppercase;
  }

  .jobseeker-sidebar-running-title span {
    align-items: center;
    display: inline-flex;
    gap: 6px;
    min-width: 0;
  }

  .jobseeker-sidebar-running-title small {
    color: #8aa4af;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    text-transform: none;
    white-space: nowrap;
  }

  .jobseeker-sidebar-running-refresh {
    background: transparent;
    border: 0;
    color: #b8c7ce;
    padding: 0 2px;
  }

  .jobseeker-sidebar-running-refresh:hover,
  .jobseeker-sidebar-running-refresh:focus {
    color: #fff;
  }

  .jobseeker-sidebar-running-actions {
    align-items: center;
    display: inline-flex;
    gap: 7px;
  }

  .jobseeker-sidebar-cached-row {
    align-items: center;
    display: flex;
  }

  .jobseeker-sidebar-cached-row .jobseeker-sidebar-cached-build {
    flex: 1 1 auto;
    min-width: 0;
  }

  .jobseeker-sidebar-cached-delete {
    background: transparent;
    border: 0;
    color: #8aa4af;
    flex: 0 0 25px;
    opacity: .7;
    padding: 6px 4px;
  }

  .jobseeker-sidebar-cached-row:hover .jobseeker-sidebar-cached-delete,
  .jobseeker-sidebar-cached-delete:focus,
  .jobseeker-sidebar-cached-delete:hover {
    color: #dd4b39;
    opacity: 1;
  }

  .jobseeker-sidebar-running-empty,
  .jobseeker-sidebar-running-error {
    color: #8aa4af;
    font-size: 12px;
    line-height: 1.4;
    padding: 6px 0;
  }

  #sidebarRunningJobsList,
  #sidebarCachedJobsList {
    max-height: 240px;
    overflow-x: hidden;
    overflow-y: auto;
    padding-right: 3px;
  }

  .jobseeker-sidebar-running-env {
    margin-bottom: 10px;
  }

  .jobseeker-sidebar-running-env-header {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
  }

  .jobseeker-sidebar-running-env-header small {
    color: #8aa4af;
  }

  .jobseeker-sidebar-running-build {
    border-radius: 4px;
    color: #b8c7ce;
    display: block;
    padding: 6px 4px;
  }

  .jobseeker-sidebar-running-build:hover,
  .jobseeker-sidebar-running-build:focus {
    background: rgba(255,255,255,.08);
    color: #fff;
    text-decoration: none;
  }

  .jobseeker-sidebar-running-build strong,
  .jobseeker-sidebar-running-build small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .jobseeker-sidebar-running-build strong {
    font-size: 12px;
    font-weight: 600;
  }

  .jobseeker-sidebar-running-build small {
    color: #8aa4af;
    font-size: 11px;
    margin-top: 2px;
  }

  .jobseeker-sidebar-cached-build strong i {
    color: #f39c12;
    margin-right: 3px;
  }

  .sidebar-collapse .jobseeker-sidebar-running {
    display: none !important;
  }

  .monitor-environment-filter,
  .execution-environment-filter,
  .job-view-environment-filter,
  .delete-environment-filter-bar,
  .tmf-builder-environment-filter {
    display: none !important;
  }

  @media (max-width: 767px) {
    .jobseeker-global-environment {
      min-width: 170px;
      padding: 8px 6px;
    }

    .jobseeker-env-icon,
    .jobseeker-global-environment label {
      display: none;
    }

    .jobseeker-env-control {
      border-radius: 6px;
      min-height: 30px;
      padding: 6px 28px 6px 10px;
    }

    .jobseeker-env-caret {
      top: 9px;
    }
  }



</style>
</head>
<body class="hold-transition skin-purple sidebar-mini">
  <div class="wrapper">

    <header class="main-header">
      <!-- Logo -->
      <a href="<?php echo base_url(); ?>" class="logo">
        <!-- mini logo for sidebar mini 50x50 pixels -->
        <span class="logo-mini"><b>J</b>S</span>
        <!-- logo for regular state and mobile devices -->
        <span class="logo-lg"><b>Job Seeker</b></span>
      </a>
      <!-- Header Navbar: style can be found in header.less -->
      <nav class="navbar navbar-static-top" role="navigation">
        <!-- Sidebar toggle button-->
        <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
          <span class="sr-only">Toggle navigation</span>
        </a>
        <div class="navbar-custom-menu">
          <ul class="nav navbar-nav">
            <li class="jobseeker-global-environment">
              <div class="jobseeker-env-control">
                <span class="jobseeker-env-icon"><i class="fa fa-globe"></i></span>
                <div class="jobseeker-env-copy">
                  <label for="globalEnvironmentSelector">Environment</label>
                  <select id="globalEnvironmentSelector" class="form-control input-sm" title="Global environment selector">
                    <option value="all">All environments</option>
                    <?php foreach ($jobseekerGlobalEnvironmentOptions as $jobseekerGlobalEnvironmentOption) { ?>
                      <option value="<?php echo html_escape(jobseeker_normalize_global_environment($jobseekerGlobalEnvironmentOption)); ?>"><?php echo html_escape($jobseekerGlobalEnvironmentOption); ?></option>
                    <?php } ?>
                  </select>
                </div>
                <i class="fa fa-angle-down jobseeker-env-caret"></i>
              </div>
            </li>
            <li class="dropdown tasks-menu">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                <i class="fa fa-history"></i>
              </a>
              <ul class="dropdown-menu">
                <li class="header"> Last Login : <i class="fa fa-clock-o"></i> <?= empty($last_login) ? "First Time Login" : $last_login; ?></li>
              </ul>
            </li>
            <!-- User Account: style can be found in dropdown.less -->
            <li class="dropdown user user-menu">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                <img src="<?php echo base_url(); ?>assets/dist/img/avatar.png" class="user-image" alt="User Image"/>
                <span class="hidden-xs"><?php echo html_escape($name); ?></span>
              </a>
              <ul class="dropdown-menu">
                <!-- User image -->
                <li class="user-header">

                  <img src="<?php echo base_url(); ?>assets/dist/img/avatar.png" class="img-circle" alt="User Image" />
                  <p>
                    <?php echo html_escape($name); ?>
                    <small><?php echo html_escape($role_text); ?></small>
                  </p>

                </li>
                <!-- Menu Footer-->
                <li class="user-footer">
                  <div class="pull-left">
                    <a href="<?php echo base_url(); ?>profile" class="btn btn-warning btn-flat"><i class="fa fa-user-circle"></i> Profile</a>
                  </div>
                  <div class="pull-right">
                    <a href="<?php echo base_url(); ?>logout" class="btn btn-default btn-flat"><i class="fa fa-sign-out"></i> Sign out</a>
                  </div>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>
    </header>
    <!-- Left side column. contains the logo and sidebar -->
    <aside class="main-sidebar">
      <!-- sidebar: style can be found in sidebar.less -->
      <section class="sidebar">
        <!-- sidebar menu: : style can be found in sidebar.less -->
        <ul class="sidebar-menu" data-widget="tree">
          <li class="header">MAIN NAVIGATION</li>
          <li>
            <a href="<?php echo base_url(); ?>dashboard">
              <i class="fa fa-home"></i> <span>Dashboard</span></i>
            </a>
          </li>
          <li class="treeview">
            <a href="#">
              <i class="fa fa-dashboard"></i> <span>Data Visualization</span>
              <span class="pull-right-container">
                <i class="fa fa-angle-left pull-right"></i>
              </span>
            </a>
            <ul class="treeview-menu">
                <li>
                  <a href="<?php echo base_url(); ?>Visualization">
                    <i class="fa fa-compass"></i>
                    <span>Analytics Hub</span>
                  </a>
                </li>
                <li>
                  <a href="<?php echo base_url(); ?>Visualization/studio">
                    <i class="fa fa-magic"></i>
                    <span>Insight Studio</span>
                  </a>
                </li>
                <?php if($role == ROLE_ADMIN || $role == ROLE_MANAGER) { ?>
                <li>
                  <a href="<?php echo base_url(); ?>Visualization/dataSources">
                    <i class="fa fa-database"></i>
                    <span>Studio Data Sources</span>
                  </a>
                </li>
                <?php } ?>
               <?php
                if(!empty($allowedReports))
                {
                  foreach($allowedReports as $record)
                  {
                    ?>
                    <li>
                      <a href="<?php echo base_url(); ?>Visualization/view/<?php echo rawurlencode($record->report); ?>" >
                        <i class="fa fa-dashboard"></i>
                        <span><?php echo html_escape($record->report); ?></span>
                      </a>
                    </li>
                    <?php
                  }
                }
                ?>
              <?php   
              if($role == ROLE_ADMIN || $role == ROLE_MANAGER)
              {
                ?>
                <li>
                  <a href="<?php echo base_url(); ?>Visualization/config" >
                    <i class="fa fa-link"></i>
                    <span>Connected Reports</span>
                  </a>
                </li>
              <?php }  ?>
            </ul>
          </li>
          <li<?php echo $jobseekerTransactionMonitorActive ? ' class="active"' : ''; ?>>
            <a href="<?php echo base_url(); ?>Tmf">
              <i class="fa fa-desktop"></i> <span>Transaction Monitoring</span>
            </a>
          </li>
          <li class="treeview">
            <a href="#">
              <i class="fa fa-random"></i> <span>Extract Transform Load</span>
              <span class="pull-right-container">
                <i class="fa fa-angle-left pull-right"></i>
              </span>
            </a>
            <ul class="treeview-menu">
              <li>
                <a href="<?php echo base_url(); ?>data-assets" >
                  <i class="fa fa-cubes"></i>
                  <span>Data Assets</span>
                </a>
              </li>
              <li>
                <a href="<?php echo base_url(); ?>dbSettings" >
                  <i class="fa fa-database"></i>
                  <span>Connectors</span>
                </a>
              </li>
              <li>
                <a href="<?php echo base_url(); ?>pipelines" >
                  <i class="fa fa-sitemap"></i>
                  <span>Pipelines</span>
                </a>
              </li>
            <!-- <li>
              <a href="#" >
                <i class="fa fa-list"></i>
                <span>Console Job Logs</span>
              </a>
            </li> -->
            <li>
              <a href="<?php echo base_url(); ?>EmailSettings" >
                <i class="fa fa-envelope"></i>
                <span>Email Settings</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>SmtpSettings" >
                <i class="fa fa-server"></i>
                <span>Smtp Settings</span>
              </a>
            </li>
          </ul>
        </li>
        <?php if ($jobseekerMlEnabled && ($role == ROLE_ADMIN || $role == ROLE_MANAGER)) { ?>
        <li class="treeview<?php echo $jobseekerMlActive ? ' active' : ''; ?>">
          <a href="#">
            <i class="fa fa-flask"></i> <span>Machine Learning</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li<?php echo $jobseekerCurrentController === 'mloverview' ? ' class="active"' : ''; ?>>
              <a href="<?php echo base_url(); ?>machine-learning/overview"><i class="fa fa-tachometer"></i> <span>Overview</span></a>
            </li>
            <li<?php echo $jobseekerCurrentController === 'mldatasets' ? ' class="active"' : ''; ?>>
              <a href="<?php echo base_url(); ?>machine-learning/datasets"><i class="fa fa-table"></i> <span>Datasets</span></a>
            </li>
            <li<?php echo $jobseekerCurrentController === 'mljobs' ? ' class="active"' : ''; ?>>
              <a href="<?php echo base_url(); ?>machine-learning/jobs"><i class="fa fa-cogs"></i> <span>Jobs</span></a>
            </li>
            <li<?php echo $jobseekerCurrentController === 'mlruns' ? ' class="active"' : ''; ?>>
              <a href="<?php echo base_url(); ?>machine-learning/runs"><i class="fa fa-line-chart"></i> <span>Experiments &amp; Runs</span></a>
            </li>
            <li<?php echo $jobseekerCurrentController === 'mlmodels' ? ' class="active"' : ''; ?>>
              <a href="<?php echo base_url(); ?>machine-learning/models"><i class="fa fa-cube"></i> <span>Models</span></a>
            </li>
            <li<?php echo $jobseekerCurrentController === 'mlmonitoring' ? ' class="active"' : ''; ?>>
              <a href="<?php echo base_url(); ?>machine-learning/monitoring"><i class="fa fa-heartbeat"></i> <span>Monitoring</span></a>
            </li>
            <li<?php echo $jobseekerCurrentController === 'mlruntimes' ? ' class="active"' : ''; ?>>
              <a href="<?php echo base_url(); ?>machine-learning/runtimes"><i class="fa fa-cubes"></i> <span>Runtimes</span></a>
            </li>
            <li<?php echo $jobseekerCurrentController === 'mlsamples' ? ' class="active"' : ''; ?>>
              <a href="<?php echo base_url(); ?>machine-learning/samples"><i class="fa fa-magic"></i> <span>Samples</span></a>
            </li>
          </ul>
        </li>
        <?php } ?>
        <li class="treeview">
          <a href="#">
            <i class="fa fa-sitemap"></i> <span>Context Settings</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="<?php echo base_url(); ?>Context/projectDetails"><i class="fa fa-table"></i><span>Project Details</span></a></li>
            <li><a href="<?php echo base_url(); ?>Context/environment"><i class="fa fa-globe"></i><span>Environment Details</span></a></li>
            <li><a href="<?php echo base_url(); ?>Context/contextDetails"><i class="fa fa-sliders"></i><span>Context Details</span></a></li>
            <li><a href="<?php echo base_url(); ?>Context/promotion"><i class="fa fa-level-up"></i><span>Environment Deployment</span></a></li>
          </ul>
        </li>
        <?php  if ($jenkins_enabled == true) { 
          if($role == ROLE_ADMIN || $role == ROLE_MANAGER)
          {
            ?>
            <li class="treeview">
              <a href="#">
                <i class="fa fa-laptop"></i> <span>Job Management</span>
                <span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
              </a>
              <ul class="treeview-menu">
                <li>
                  <a href="<?php echo base_url(); ?>jobList" >
                    <i class="fa fa-list"></i>
                    <span>Job Build List</span>
                  </a>
                </li>
                <li>
                  <a href="<?php echo base_url(); ?>jobList/full" >
                    <i class="fa fa-list-ul"></i>
                    <span>Full Job Build List</span>
                  </a>
                </li>
                <li>
                  <a href="<?php echo base_url(); ?>jobExecution" >
                    <i class="fa fa-play"></i>
                    <span>Job Execution</span>
                  </a>
                </li>
                <li>
                  <a href="<?php echo base_url(); ?>jobView" >
                    <i class="fa fa-eye"></i>
                    <span>View Job</span>
                  </a>
                </li>
                <li>
                  <a href="<?php echo base_url(); ?>jobCreation" >
                    <i class="fa fa-plus-square"></i>
                    <span>Job Creation</span>
                  </a>
                </li>
                <li>
                  <a href="<?php echo base_url(); ?>deleteJob" >
                    <i class="fa fa-minus-square"></i>
                    <span>Delete Job</span>
                  </a>
                </li>
              </ul>
            </li>
          <?php } } ?>
          <?php if($role == ROLE_ADMIN || $role == ROLE_MANAGER) { ?>
          <li class="treeview<?php echo $jobseekerMonitoringActive ? ' active' : ''; ?>">
            <a href="#">
              <i class="fa fa-heartbeat"></i> <span>Infrastructure Monitoring</span>
              <span class="pull-right-container">
                <i class="fa fa-angle-left pull-right"></i>
              </span>
            </a>
            <ul class="treeview-menu">
              <?php if($jenkins_enabled == true) { ?>
              <li<?php echo $jobseekerExecutorMonitorActive ? ' class="active"' : ''; ?>>
                <a href="<?php echo base_url(); ?>jobExecution/executors">
                  <i class="fa fa-tachometer"></i> <span>Executor Monitor</span>
                </a>
              </li>
              <?php } ?>
              <li<?php echo $jobseekerDockerMonitorActive ? ' class="active"' : ''; ?>>
                <a href="<?php echo base_url(); ?>docker-monitoring">
                  <i class="fa fa-cubes"></i> <span>Docker Monitor</span>
                </a>
              </li>
            </ul>
          </li>
          <?php } ?>
          <?php
          if($role == ROLE_ADMIN)
          {
            ?>
            <!-- Can be used in a future release
              <li>
              <a href="<?php echo base_url(); ?>Cloud">
                <i class="fa fa-cloud"></i>
                <span>Cloud Enviroment</span>
              </a>
            </li> -->
            <?php  if ($jenkins_enabled == true) { ?>
              <li>
                <a href="<?php echo $jenkins_url; ?>" target="_blank">
                  <i class="fa fa-share-alt"></i>
                  <span>Jenkins Manager</span>
                </a>
              </li>
            <?php } ?>
            <li>
              <a href="<?php echo base_url(); ?>userListing">
                <i class="fa fa-user"></i>
                <span>Users</span>
              </a>
            </li>
            <li>
              <a href="<?php echo base_url(); ?>User/groupsListing">
                <i class="fa fa-users"></i>
                <span>Groups</span>
              </a>
            </li>
            <?php
          }
          ?>
        </ul>
        <?php if ($jenkins_enabled == true && ($role == ROLE_ADMIN || $role == ROLE_MANAGER)) { ?>
          <div class="jobseeker-sidebar-running" id="sidebarCachedJobs">
            <div class="jobseeker-sidebar-running-title">
              <span><i class="fa fa-save"></i> Cached Drafts <small id="sidebarCachedJobsCount"></small></span>
              <span class="jobseeker-sidebar-running-actions">
                <a class="jobseeker-sidebar-running-refresh" href="<?php echo base_url(); ?>jobCreation" title="Open cached job drafts"><i class="fa fa-pencil"></i></a>
                <button type="button" class="jobseeker-sidebar-running-refresh" id="sidebarCachedJobsClear" title="Clear all cached job drafts" style="display:none;"><i class="fa fa-trash"></i></button>
              </span>
            </div>
            <div id="sidebarCachedJobsList" class="jobseeker-sidebar-running-empty">Loading cached drafts...</div>
          </div>
        <?php } ?>
        <?php if ($jenkins_enabled == true) { ?>
          <div class="jobseeker-sidebar-running" id="sidebarRunningJobs">
            <div class="jobseeker-sidebar-running-title">
              <span><i class="fa fa-play-circle"></i> Running Jobs <small id="sidebarRunningJobsScope"></small></span>
              <button type="button" class="jobseeker-sidebar-running-refresh" id="sidebarRunningJobsRefresh" title="Refresh running jobs"><i class="fa fa-refresh"></i></button>
            </div>
            <div id="sidebarRunningJobsList" class="jobseeker-sidebar-running-empty">Loading running jobs...</div>
          </div>
        <?php } ?>
      </section>
      <!-- /.sidebar -->
    </aside>
