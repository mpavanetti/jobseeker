(function(window, $) {
  'use strict';

  if (! $) {
    return;
  }

  var modalId = 'jobInspectModal';
  var titleId = 'jobInspectModalTitle';
  var bodyId = 'jobInspectModalBody';
  var styleId = 'jobInspectModalStyle';

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  function noneText() {
    return '<span class="text-muted">None</span>';
  }

  function valueOrNone(value) {
    if (value == null || value === '') {
      return noneText();
    }

    return escapeHtml(value);
  }

  function labelHtml(text, className) {
    return '<span class="label label-' + className + '">' + escapeHtml(text) + '</span>';
  }

  function booleanLabel(value) {
    return value ? labelHtml('Yes', 'success') : labelHtml('No', 'default');
  }

  function environmentInfo(configText, jobName, data) {
    if (window.JobSeekerEnvironment) {
      var configInfo = window.JobSeekerEnvironment.detectFromConfig(configText || '', jobName || data.fullName || data.name || '');

      if (! configInfo || configInfo.unknown) {
        return window.JobSeekerEnvironment.detectFromJob(data || {name: jobName});
      }

      return configInfo;
    }

    return {environment: 'Unknown', source: 'Not detected', unknown: true};
  }

  function environmentLabel(info) {
    return window.JobSeekerEnvironment ? window.JobSeekerEnvironment.label(info) : labelHtml(info.environment || 'Unknown', 'default');
  }

  function jenkinsJobPath(jobName) {
    return String(jobName == null ? '' : jobName).split('/').map(function(segment) {
      return 'job/' + encodeURIComponent(segment);
    }).join('/');
  }

  function ensureStyle() {
    if (document.getElementById(styleId)) {
      return;
    }

    $('<style>', {
      id: styleId,
      text: [
        '.job-inspect-modal .modal-body{max-height:calc(100vh - 190px);overflow-y:auto;}',
        '.job-inspect-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:15px;}',
        '.job-inspect-tile{background:#f9fafc;border:1px solid #e5e9ef;border-radius:4px;padding:10px;}',
        '.job-inspect-label{color:#777;font-size:11px;font-weight:700;text-transform:uppercase;}',
        '.job-inspect-value{font-size:16px;font-weight:700;margin-top:4px;}',
        '.job-inspect-modal .table>tbody>tr>td:first-child{color:#555;font-weight:700;width:170px;}',
        '.job-inspect-modal pre{max-height:260px;margin:0;overflow:auto;white-space:pre-wrap;word-break:break-word;}',
        '.job-inspect-modal .panel{margin-bottom:12px;}'
      ].join('')
    }).appendTo('head');
  }

  function ensureModal() {
    ensureStyle();

    if (document.getElementById(modalId)) {
      return;
    }

    $('body').append(
      '<div class="modal fade job-inspect-modal" id="' + modalId + '" style="display: none;">' +
        '<div class="modal-dialog modal-lg">' +
          '<div class="modal-content">' +
            '<div class="modal-header">' +
              '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
              '<h4 class="modal-title" id="' + titleId + '">Inspect Job</h4>' +
            '</div>' +
            '<div class="modal-body" id="' + bodyId + '"></div>' +
            '<div class="modal-footer">' +
              '<button type="button" class="btn btn-primary pull-left" data-dismiss="modal">Close</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>'
    );
  }

  function rowHtml(label, value, allowHtml) {
    return '<tr><td>' + escapeHtml(label) + '</td><td>' + (allowHtml ? value : valueOrNone(value)) + '</td></tr>';
  }

  function panelHtml(title, body) {
    return '<div class="panel panel-default"><div class="panel-heading"><b>' + escapeHtml(title) + '</b></div>' + body + '</div>';
  }

  function tablePanel(title, rows) {
    return panelHtml(title, '<table class="table table-bordered table-condensed"><tbody>' + rows.join('') + '</tbody></table>');
  }

  function loadingHtml(jobName) {
    return '<div class="text-center" style="padding: 30px 10px;">' +
      '<i class="fa fa-refresh fa-spin"></i> Loading inspection details for <b>' + escapeHtml(jobName) + '</b>...' +
    '</div>';
  }

  function formatTime(timestamp) {
    timestamp = parseInt(timestamp, 10);

    if (! timestamp) {
      return noneText();
    }

    if (typeof moment === 'function') {
      return escapeHtml(moment(timestamp).format('YYYY-MM-DD HH:mm:ss'));
    }

    return escapeHtml(new Date(timestamp).toLocaleString());
  }

  function formatDuration(duration) {
    duration = parseInt(duration, 10);

    if (! duration && duration !== 0) {
      return noneText();
    }

    var totalSeconds = Math.round(duration / 1000);
    var hours = Math.floor(totalSeconds / 3600);
    var minutes = Math.floor((totalSeconds % 3600) / 60);
    var seconds = totalSeconds % 60;
    var parts = [];

    if (hours) {
      parts.push(hours + 'h');
    }
    if (minutes) {
      parts.push(minutes + 'm');
    }
    if (seconds || parts.length === 0) {
      parts.push(seconds + 's');
    }

    return escapeHtml(parts.join(' '));
  }

  function renderSituation(data) {
    var color = String(data && data.color ? data.color : '').replace('_anime', '');

    if (data && data.inQueue) {
      return labelHtml('Queued', 'info');
    }
    if (String(data && data.color ? data.color : '').indexOf('_anime') !== -1) {
      return labelHtml('Running', 'warning');
    }
    if (color === 'blue') {
      return labelHtml('Healthy', 'success');
    }
    if (color === 'red') {
      return labelHtml('Failed', 'danger');
    }
    if (color === 'notbuilt') {
      return labelHtml('Never Built', 'default');
    }
    if (data && data.buildable === false) {
      return labelHtml('Disabled', 'danger');
    }

    return valueOrNone(color);
  }

  function buildValue(build, field) {
    if (! build) {
      return '';
    }

    var value = build[field];
    return value == null ? '' : value;
  }

  function renderBuildNumber(build) {
    var number = buildValue(build, 'number');
    return number === '' ? noneText() : '#' + escapeHtml(number);
  }

  function renderBuildResult(build) {
    if (! build) {
      return noneText();
    }

    if (build.building === true) {
      return labelHtml('RUNNING', 'warning');
    }

    var result = buildValue(build, 'result');
    if (result === 'SUCCESS') {
      return labelHtml(result, 'success');
    }
    if (result === 'FAILURE') {
      return labelHtml(result, 'danger');
    }
    if (result) {
      return labelHtml(result, 'warning');
    }

    return noneText();
  }

  function firstXmlNode(root, tagName) {
    if (! root) {
      return null;
    }

    var nodes = root.getElementsByTagName(tagName);
    return nodes && nodes.length ? nodes[0] : null;
  }

  function readNodeText(node, tagName) {
    var target = tagName ? firstXmlNode(node, tagName) : node;

    if (! target || target.textContent == null) {
      return '';
    }

    return $.trim(target.textContent);
  }

  function readAllNodeText(root, tagName) {
    if (! root) {
      return [];
    }

    return $.map(root.getElementsByTagName(tagName), function(node) {
      return readNodeText(node);
    }).filter(function(value) {
      return value !== '';
    });
  }

  function readEmailTrigger(root, tagName) {
    var node = firstXmlNode(root, tagName);

    if (! node) {
      return null;
    }

    return {
      recipients: readNodeText(node, 'recipientList'),
      from: readNodeText(node, 'from'),
      subject: readNodeText(node, 'subject'),
      attachBuildLog: readNodeText(node, 'attachBuildLog')
    };
  }

  function parseJobConfig(xmlText) {
    var config = {
      commands: [],
      schedule: '',
      timeoutSeconds: '',
      timeoutMinutes: '',
      childProjects: '',
      childProjectsCondition: '',
      mailRecipients: '',
      successMail: null,
      failureMail: null,
      parseError: ''
    };

    if (! xmlText) {
      return config;
    }

    try {
      var xml = $.parseXML(xmlText);
      var threshold = firstXmlNode(xml, 'threshold');
      var mailer = firstXmlNode(xml, 'hudson.tasks.Mailer');

      config.commands = readAllNodeText(xml, 'command');
      config.schedule = readNodeText(xml, 'spec');
      config.timeoutSeconds = readNodeText(xml, 'timeoutSecondsString');
      config.timeoutMinutes = readNodeText(xml, 'timeoutMinutes');
      config.childProjects = readNodeText(xml, 'childProjects');
      config.childProjectsCondition = threshold ? readNodeText(threshold, 'name') : '';
      config.mailRecipients = mailer ? readNodeText(mailer, 'recipients') : '';
      config.successMail = readEmailTrigger(xml, 'hudson.plugins.emailext.plugins.trigger.SuccessTrigger');
      config.failureMail = readEmailTrigger(xml, 'hudson.plugins.emailext.plugins.trigger.FailureTrigger');
    } catch (error) {
      config.parseError = error && error.message ? error.message : 'Unable to parse Jenkins config.xml.';
    }

    return config;
  }

  function renderSchedule(schedule) {
    if (! schedule) {
      return noneText();
    }

    if (schedule.charAt(0) === '@') {
      return '<b>Tag:</b> ' + escapeHtml(schedule);
    }

    var parts = schedule.split(/\s+/);
    if (parts.length < 5) {
      return escapeHtml(schedule);
    }

    return '<ul class="list-unstyled" style="margin-bottom: 0;">' +
      '<li><b>Every Minute:</b> ' + escapeHtml(parts[0]) + '</li>' +
      '<li><b>At Hour:</b> ' + escapeHtml(parts[1]) + '</li>' +
      '<li><b>On Day of Month:</b> ' + escapeHtml(parts[2]) + '</li>' +
      '<li><b>On Month:</b> ' + escapeHtml(parts[3]) + '</li>' +
      '<li><b>On Day of Week:</b> ' + escapeHtml(parts[4]) + '</li>' +
    '</ul>';
  }

  function renderMailTrigger(trigger) {
    if (! trigger) {
      return noneText();
    }

    return '<ul class="list-unstyled" style="margin-bottom: 0;">' +
      '<li><b>Send to:</b> ' + valueOrNone(trigger.recipients) + '</li>' +
      '<li><b>From:</b> ' + valueOrNone(trigger.from) + '</li>' +
      '<li><b>Subject:</b> ' + valueOrNone(trigger.subject) + '</li>' +
      '<li><b>Attach Build Log:</b> ' + valueOrNone(trigger.attachBuildLog) + '</li>' +
    '</ul>';
  }

  function renderSummaryTile(label, value) {
    return '<div class="job-inspect-tile"><div class="job-inspect-label">' + escapeHtml(label) + '</div><div class="job-inspect-value">' + value + '</div></div>';
  }

  function renderJobInspect(data, configText, configError, requestedJob) {
    var jobName = data.fullName || data.name || requestedJob;
    var config = parseJobConfig(configText);
    var jobEnvironment = environmentInfo(configText, jobName, data || {});
    var healthReport = Array.isArray(data.healthReport) && data.healthReport.length ? data.healthReport[0].description : '';
    var command = config.commands.length ? '<pre>' + escapeHtml(config.commands.join('\n\n')) + '</pre>' : noneText();
    var downstream = config.childProjects ? escapeHtml(config.childProjects) : noneText();

    if (config.childProjectsCondition) {
      downstream += ' <b>[In case of ' + escapeHtml(config.childProjectsCondition) + ']</b>';
    }

    var summary = '<div class="job-inspect-summary">' +
      renderSummaryTile('Environment', environmentLabel(jobEnvironment)) +
      renderSummaryTile('Situation', renderSituation(data)) +
      renderSummaryTile('Last Result', renderBuildResult(data.lastBuild)) +
      renderSummaryTile('Last Build', renderBuildNumber(data.lastBuild)) +
      renderSummaryTile('Queue', data.inQueue ? labelHtml('Queued', 'info') : labelHtml('Idle', 'default')) +
    '</div>';

    var overviewRows = [
      rowHtml('Display Name', jobName),
      rowHtml('Environment', environmentLabel(jobEnvironment) + '<br><small>' + escapeHtml(jobEnvironment.source || 'Not detected') + '</small>', true),
      rowHtml('Description', data.description),
      rowHtml('Build Situation', renderSituation(data), true),
      rowHtml('Build Description', healthReport),
      rowHtml('Buildable', booleanLabel(data.buildable), true),
      rowHtml('Disabled', booleanLabel(data.disabled), true),
      rowHtml('URL', data.url),
      rowHtml('Next Build Number', data.nextBuildNumber),
      rowHtml('In Queue', booleanLabel(data.inQueue), true)
    ];

    var buildRows = [
      rowHtml('Last Build', renderBuildNumber(data.lastBuild), true),
      rowHtml('Last Build Result', renderBuildResult(data.lastBuild), true),
      rowHtml('Last Build Time', formatTime(buildValue(data.lastBuild, 'timestamp')), true),
      rowHtml('Last Build Duration', formatDuration(buildValue(data.lastBuild, 'duration')), true),
      rowHtml('Last Completed Build', renderBuildNumber(data.lastCompletedBuild), true),
      rowHtml('Last Stable Build', renderBuildNumber(data.lastStableBuild), true),
      rowHtml('Last Unstable Build', renderBuildNumber(data.lastUnstableBuild), true),
      rowHtml('Last Successful Build', renderBuildNumber(data.lastSuccessfulBuild), true),
      rowHtml('Last Unsuccessful Build', renderBuildNumber(data.lastUnsuccessfulBuild), true)
    ];

    var configRows = [
      rowHtml('Environment Binding', environmentLabel(jobEnvironment) + '<br><small>' + escapeHtml(jobEnvironment.source || 'Not detected') + '</small>', true),
      rowHtml('Scheduler', renderSchedule(config.schedule), true),
      rowHtml('Command', command, true),
      rowHtml('Abort Timeout Seconds', config.timeoutSeconds ? escapeHtml(config.timeoutSeconds + ' Seconds') : noneText(), true),
      rowHtml('Abort Absolute Minutes', config.timeoutMinutes ? escapeHtml(config.timeoutMinutes + ' Minutes') : noneText(), true),
      rowHtml('Run Next Jobs', downstream, true),
      rowHtml('Email Recipients', config.mailRecipients),
      rowHtml('Success Mail Template', renderMailTrigger(config.successMail), true),
      rowHtml('Failure Mail Template', renderMailTrigger(config.failureMail), true)
    ];

    if (configError) {
      configRows.unshift(rowHtml('Config XML', '<span class="text-warning">' + escapeHtml(configError) + '</span>', true));
    } else if (config.parseError) {
      configRows.unshift(rowHtml('Config XML', '<span class="text-warning">' + escapeHtml(config.parseError) + '</span>', true));
    }

    return summary + tablePanel('Overview', overviewRows) + tablePanel('Build Info', buildRows) + tablePanel('Configuration', configRows);
  }

  function xhrMessage(xhr, fallback) {
    if (xhr && xhr.responseText) {
      return xhr.responseText;
    }

    return fallback;
  }

  function setButtonLoading(button, isLoading) {
    if (! button || ! button.length) {
      return;
    }

    if (isLoading) {
      button.data('job-inspect-original-html', button.html());
      button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Inspecting');
      return;
    }

    button.prop('disabled', false).html(button.data('job-inspect-original-html') || '<i class="fa fa-eye"></i> Inspect');
  }

  function open(options) {
    options = options || {};

    var jobName = options.jobName || '';
    var jenkinsUrl = options.jenkinsUrl || window.jobseekerJenkinsUrl || '';
    var headers = options.headers || {};
    var button = options.button ? $(options.button) : $();

    if (jobName === '' || jenkinsUrl === '') {
      return;
    }

    ensureModal();
    setButtonLoading(button, true);
    $('#' + titleId).text('Inspect Job: ' + jobName);
    $('#' + bodyId).html(loadingHtml(jobName));
    $('#' + modalId).modal('show');

    var tree = 'name,fullName,displayName,description,color,buildable,disabled,inQueue,nextBuildNumber,url,healthReport[description,score],lastBuild[number,result,timestamp,duration,building],lastCompletedBuild[number,result,timestamp,duration,building],lastStableBuild[number,result,timestamp,duration,building],lastUnstableBuild[number,result,timestamp,duration,building],lastSuccessfulBuild[number,result,timestamp,duration,building],lastUnsuccessfulBuild[number,result,timestamp,duration,building]';
    var jobPath = jenkinsJobPath(jobName);
    var jobRequest = $.ajax({
      url: jenkinsUrl + jobPath + '/api/json?tree=' + encodeURIComponent(tree),
      method: 'GET',
      headers: headers
    });
    var configRequest = $.ajax({
      url: jenkinsUrl + jobPath + '/config.xml',
      method: 'GET',
      dataType: 'text',
      headers: headers
    }).then(function(xmlText) {
      return {xmlText: xmlText, error: ''};
    }, function(xhr) {
      return {xmlText: '', error: xhrMessage(xhr, 'Unable to fetch Jenkins config.xml.')};
    });

    $.when(jobRequest, configRequest).done(function(jobResponse, configResult) {
      var data = $.isArray(jobResponse) ? jobResponse[0] : jobResponse;
      $('#' + bodyId).html(renderJobInspect(data || {}, configResult.xmlText, configResult.error, jobName));
    }).fail(function(xhr) {
      $('#' + bodyId).html('<div class="alert alert-danger">' + escapeHtml(xhrMessage(xhr, 'Unable to inspect this Jenkins job.')) + '</div>');
      if (window.toastr) {
        toastr.error('Unable to inspect Jenkins job.', 'Inspect Failed');
      }
    }).always(function() {
      setButtonLoading(button, false);
    });
  }

  window.JobSeekerJobInspect = {
    open: open,
    jenkinsJobPath: jenkinsJobPath
  };
})(window, window.jQuery);