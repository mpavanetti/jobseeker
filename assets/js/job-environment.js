(function(window, $) {
  'use strict';

  var knownEnvironments = ['LOCAL', 'DEV', 'QA', 'QAS', 'UAT', 'PREPROD', 'PROD', 'STAGE', 'STAGING', 'TEST', 'HML'];
  var environmentAliases = {
    HOMOLOG: 'HML',
    HOMOLOGATION: 'HML',
    QAS: 'QA',
    PRD: 'PROD',
    PRODUCTION: 'PROD'
  };

  function escapeHtml(value) {
    if ($) {
      return $('<div>').text(value == null ? '' : value).html();
    }

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

  function cleanToken(value) {
    return String(value == null ? '' : value)
      .replace(/^['"`]+|['"`,;]+$/g, '')
      .trim();
  }

  function normalizeEnvironment(value) {
    var cleaned = cleanToken(value);

    if (! cleaned) {
      return '';
    }

    var normalized = cleaned.toUpperCase();

    if (environmentAliases[normalized]) {
      return environmentAliases[normalized];
    }

    if (knownEnvironments.indexOf(normalized) !== -1) {
      return normalized;
    }

    if (/^[A-Z][A-Z0-9_-]{1,24}$/.test(normalized)) {
      return normalized;
    }

    return '';
  }

  function readCapture(match) {
    if (! match) {
      return '';
    }

    for (var captureIndex = 1; captureIndex < match.length; captureIndex += 1) {
      if (match[captureIndex]) {
        return match[captureIndex];
      }
    }

    return '';
  }

  function detectWithPatterns(text) {
    var commandText = String(text || '');
    var valuePattern = '(?:"([^"\\r\\n]+)"|\'([^\'\\r\\n]+)\'|([^\\s;]+))';
    var patterns = [
      new RegExp('(?:^|[\\s"\'])--context["\']?(?:=|\\s+)' + valuePattern, 'i'),
      new RegExp('(?:^|[\\s"\'])-context["\']?\\s+' + valuePattern, 'i'),
      new RegExp('(?:^|[\\s"\'])--environment["\']?(?:=|\\s+)' + valuePattern, 'i'),
      new RegExp('(?:^|[\\s;])(?:export\\s+)?(?:ENVIRONMENT|JOBSEEKER_ENVIRONMENT|JOB_CONTEXT|CONTEXT)\\s*=\\s*' + valuePattern, 'i')
    ];

    for (var patternIndex = 0; patternIndex < patterns.length; patternIndex += 1) {
      var match = patterns[patternIndex].exec(commandText);
      var environment = normalizeEnvironment(readCapture(match));

      if (environment) {
        return environment;
      }
    }

    return '';
  }

  function detectKnownToken(text) {
    var source = String(text || '').toUpperCase();

    for (var environmentIndex = 0; environmentIndex < knownEnvironments.length; environmentIndex += 1) {
      var environment = knownEnvironments[environmentIndex];
      var tokenPattern = new RegExp('(^|[^A-Z0-9])' + environment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '($|[^A-Z0-9])');

      if (tokenPattern.test(source)) {
        return normalizeEnvironment(environment);
      }
    }

    return '';
  }

  function nodeText(node) {
    return node && node.textContent != null ? $.trim(node.textContent) : '';
  }

  function childText(node, tagName) {
    if (! node) {
      return '';
    }

    var children = node.getElementsByTagName(tagName);
    return children.length ? nodeText(children[0]) : '';
  }

  function allText(root, tagName) {
    var values = [];

    if (! root) {
      return values;
    }

    var nodes = root.getElementsByTagName(tagName);
    for (var nodeIndex = 0; nodeIndex < nodes.length; nodeIndex += 1) {
      var value = nodeText(nodes[nodeIndex]);

      if (value) {
        values.push(value);
      }
    }

    return values;
  }

  function detectFromParameters(root) {
    var elements = root ? root.getElementsByTagName('*') : [];

    for (var elementIndex = 0; elementIndex < elements.length; elementIndex += 1) {
      var element = elements[elementIndex];
      var tagName = element.tagName || '';

      if (! /ParameterDefinition$/.test(tagName)) {
        continue;
      }

      var parameterName = childText(element, 'name').toUpperCase();
      if (! /^(ENVIRONMENT|ENV|CONTEXT|JOB_CONTEXT|JOBSEEKER_ENVIRONMENT|TARGET_ENVIRONMENT|CONTEXT_ENVIRONMENT)$/.test(parameterName)) {
        continue;
      }

      var environment = normalizeEnvironment(childText(element, 'defaultValue') || childText(element, 'value'));
      if (environment) {
        return environment;
      }
    }

    return '';
  }

  function unknown(source) {
    return {
      environment: 'Unknown',
      source: source || 'Not detected',
      unknown: true
    };
  }

  function detected(environment, source) {
    return {
      environment: environment,
      source: source,
      unknown: false
    };
  }

  function detectFromConfig(xmlText, fallbackJobName) {
    if ($ && xmlText) {
      try {
        var xml = $.parseXML(xmlText);
        var parameterEnvironment = detectFromParameters(xml);

        if (parameterEnvironment) {
          return detected(parameterEnvironment, 'Jenkins parameter');
        }

        var commands = allText(xml, 'command');
        for (var commandIndex = 0; commandIndex < commands.length; commandIndex += 1) {
          var commandEnvironment = detectWithPatterns(commands[commandIndex]);

          if (commandEnvironment) {
            return detected(commandEnvironment, 'Jenkins command');
          }
        }
      } catch (error) {
        var fallbackEnvironment = detectFromName(fallbackJobName).environment;

        if (fallbackEnvironment !== 'Unknown') {
          return detected(fallbackEnvironment, 'Job name');
        }

        return unknown('Config parse failed');
      }
    }

    return detectFromName(fallbackJobName);
  }

  function detectFromName(jobName) {
    var environment = detectKnownToken(jobName);

    return environment ? detected(environment, 'Job name') : unknown('Not detected');
  }

  function detectFromJob(job) {
    if (! job) {
      return unknown('Not detected');
    }

    var explicitEnvironment = normalizeEnvironment(job.environment || job.jobseekerEnvironment || job.context || job.jobContext);
    if (explicitEnvironment) {
      return detected(explicitEnvironment, 'Job metadata');
    }

    return detectFromName(job.fullName || job.name || job.jobName || '');
  }

  function badgeClass(environment) {
    environment = normalizeEnvironment(environment) || String(environment || '').toUpperCase();

    if (environment === 'PROD') {
      return 'danger';
    }

    if (environment === 'PREPROD' || environment === 'UAT') {
      return 'primary';
    }

    if (environment === 'QA' || environment === 'QAS' || environment === 'TEST' || environment === 'HML') {
      return 'warning';
    }

    if (environment === 'DEV' || environment === 'STAGE' || environment === 'STAGING') {
      return 'info';
    }

    if (environment === 'LOCAL') {
      return 'default';
    }

    return 'default';
  }

  function label(info) {
    info = info || unknown('Not detected');
    var environment = info.environment || 'Unknown';
    var title = info.unknown ? 'Environment was not detected from Jenkins config or job name' : 'Environment detected from ' + info.source;

    return '<span class="label label-' + badgeClass(environment) + ' job-environment-label" title="' + escapeHtml(title) + '">' + escapeHtml(environment) + '</span>';
  }

  function text(info) {
    info = info || unknown('Not detected');
    return info.environment || 'Unknown';
  }

  window.JobSeekerEnvironment = {
    badgeClass: badgeClass,
    detectFromConfig: detectFromConfig,
    detectFromJob: detectFromJob,
    detectFromName: detectFromName,
    label: label,
    normalize: normalizeEnvironment,
    text: text,
    unknown: unknown
  };
})(window, window.jQuery);