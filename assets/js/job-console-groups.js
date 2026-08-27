(function(root, factory) {
  var api = factory();

  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }

  if (root) {
    root.JobSeekerConsole = api;
  }
}(typeof window !== 'undefined' ? window : null, function() {
  'use strict';

  var SECTION_META = {
    jobseeker: { title: 'JobSeeker events', icon: 'fa-bolt' },
    jenkins: { title: 'Jenkins runtime', icon: 'fa-cogs' },
    'docker-build': { title: 'Docker image build', icon: 'fa-cube' },
    'docker-runtime': { title: 'Docker container setup', icon: 'fa-archive' },
    'python-environment': { title: 'Python environment', icon: 'fa-wrench' },
    'python-tests': { title: 'Python tests', icon: 'fa-check-square-o' },
    python: { title: 'Python execution', icon: 'fa-code' },
    email: { title: 'Email notification', icon: 'fa-envelope-o' },
    cleanup: { title: 'Cleanup', icon: 'fa-trash-o' },
    result: { title: 'Build result', icon: 'fa-flag-checkered' }
  };

  function normalizedLine(line) {
    return String(line == null ? '' : line).replace(/\x1b\[[0-?]*[ -\/]*[@-~]/g, '');
  }

  function isBuildResult(line) {
    return /^Finished:\s+(?:SUCCESS|FAILURE|ABORTED|UNSTABLE|NOT_BUILT)\s*$/i.test(line);
  }

  function isBuildResultDetail(line) {
    return /^(?:Build was aborted|Aborted by\b|Build step .* marked build as failure)/i.test(line);
  }

  function isEmailNotification(line) {
    return /^\[JobSeeker Email\]|^Email was triggered for:|^Sending email for trigger:|^Sending email to:|^Successfully sent email to:|^Not sent to the following|^An attempt to send an e-mail|^Email sending failed/i.test(line);
  }

  function isCleanup(line) {
    return /jobseeker_python_(?:docker_)?cleanup|rm -rf .*jobseeker-python-docker-context|docker image rm\b/i.test(line);
  }

  function isPythonCommand(line) {
    return /^(?:\+\s*)?"?(?:[^"\s]+\/)?python(?:3(?:\.\d+)?)?"?\s+-u(?:\s|$)/i.test(line);
  }

  function isPytestCommand(line) {
    return /^(?:\+\s*)?(?:"?(?:[^"\s]+\/)?python(?:3(?:\.\d+)?)?"?\s+-m\s+pytest|"?(?:[^"\s]+\/)?pytest"?)(?:\s|$)/i.test(line);
  }

  function explicitSectionKind(line) {
    if (/^\[JobSeeker\]\s+Python tests\s*$/i.test(line)) {
      return 'python-tests';
    }

    if (/^\[JobSeeker\]\s+Python execution\s*$/i.test(line)) {
      return 'python';
    }

    return '';
  }

  function isDockerRuntimeStart(line) {
    return /^\+\s+(?:tar\s+.*jobseeker-python-docker-context|docker\s+run\b)/i.test(line) ||
      /^docker\s+run\b/i.test(line);
  }

  function isDockerBuildStart(line) {
    return /Preparing Python Docker build context|JOBSEEKER_DOCKER_(?:IMAGE|RUN_IMAGE|BUILT_IMAGE|TAG|BUILD_CONTEXT)|JOBSEEKER_DOCKERFILE|DOCKER_BUILDKIT=.*docker build/i.test(line) ||
      /^#\d+\s+(?:\[|DONE\b|CACHED\b|ERROR\b|exporting\b|writing\b|naming\b|transferring\b)/i.test(line);
  }

  function isPythonEnvironment(line) {
    return /(?:creating|recreating|initializing).*virtual environment|installing (?:python )?dependenc|requirements\.txt|poetry install|pip install/i.test(line);
  }

  function hasError(line) {
    return /(?:^|\b)(?:ERROR|FAILURE|FAILED|Traceback|Exception|fatal:|command not found|No such file or directory|exited with (?:status|code) [1-9]\d*)/i.test(line) ||
      /\b[A-Za-z_][A-Za-z0-9_]*(?:Error|Exception):/.test(line);
  }

  function classifyLine(value, currentKind) {
    var line = normalizedLine(value);
    var explicitKind = explicitSectionKind(line);

    if (explicitKind) {
      return explicitKind;
    }

    if (/^\[JobSeeker\]/.test(line)) {
      return 'jobseeker';
    }

    if (isBuildResult(line) || isBuildResultDetail(line)) {
      return 'result';
    }

    if (isEmailNotification(line)) {
      return 'email';
    }

    if (isCleanup(line)) {
      return 'cleanup';
    }

    if (isPythonCommand(line)) {
      return 'python';
    }

    if (isPytestCommand(line)) {
      return 'python-tests';
    }

    if (currentKind === 'email') {
      return 'email';
    }

    if (currentKind === 'python-tests' || currentKind === 'python' || currentKind === 'cleanup' || currentKind === 'result') {
      return currentKind;
    }

    if (isDockerRuntimeStart(line)) {
      return 'docker-runtime';
    }

    if (currentKind === 'docker-runtime') {
      return 'docker-runtime';
    }

    if (isDockerBuildStart(line)) {
      return 'docker-build';
    }

    if (currentKind === 'docker-build') {
      return 'docker-build';
    }

    if (isPythonEnvironment(line)) {
      return 'python-environment';
    }

    if (currentKind === 'python-environment') {
      return 'python-environment';
    }

    return 'jenkins';
  }

  function parse(text) {
    var raw = String(text == null ? '' : text);
    var normalized = raw.replace(/\r\n?/g, '\n');
    var lines = normalized.split('\n');
    var sections = [];
    var occurrences = {};
    var current = null;

    if (lines.length > 1 && lines[lines.length - 1] === '') {
      lines.pop();
    }

    lines.forEach(function(line) {
      var kind = classifyLine(line, current ? current.kind : '');

      if (! current || current.kind !== kind) {
        occurrences[kind] = (occurrences[kind] || 0) + 1;
        current = {
          id: kind + '-' + occurrences[kind],
          kind: kind,
          title: SECTION_META[kind].title,
          icon: SECTION_META[kind].icon,
          lines: [],
          lineCount: 0,
          hasError: false
        };
        sections.push(current);
      }

      current.lines.push(line);
      current.lineCount += 1;
      current.hasError = current.hasError || hasError(normalizedLine(line));
    });

    sections.forEach(function(section) {
      section.text = section.lines.join('\n');
      delete section.lines;
    });

    return { raw: raw, sections: sections };
  }

  function stateFor(host) {
    if (! host.__jobSeekerConsoleState) {
      host.__jobSeekerConsoleState = {
        text: '',
        openById: {},
        touchedById: {},
        knownIds: {},
        rawVisible: false,
        lastSectionId: ''
      };
    }

    return host.__jobSeekerConsoleState;
  }

  function element(name, className, text) {
    var node = document.createElement(name);
    if (className) {
      node.className = className;
    }
    if (text != null) {
      node.textContent = text;
    }
    return node;
  }

  function button(action, label, icon) {
    var node = element('button', 'btn btn-default btn-xs job-console-action');
    node.type = 'button';
    node.setAttribute('data-console-action', action);
    if (icon) {
      node.appendChild(element('i', 'fa ' + icon));
      node.appendChild(document.createTextNode(' '));
    }
    node.appendChild(document.createTextNode(label));
    return node;
  }

  function defaultOpen(section, index, total, options) {
    return section.hasError || section.kind === 'python-tests' || section.kind === 'python' || section.kind === 'email' || section.kind === 'cleanup' ||
      section.kind === 'result' || total === 1 || (!! options.live && index === total - 1);
  }

  function copyText(text, sourceButton) {
    function copied() {
      var original = sourceButton.innerHTML;
      sourceButton.textContent = 'Copied';
      setTimeout(function() { sourceButton.innerHTML = original; }, 1200);
    }

    if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(copied);
      return;
    }

    var area = element('textarea');
    area.value = text;
    area.setAttribute('readonly', 'readonly');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    try {
      document.execCommand('copy');
      copied();
    } finally {
      document.body.removeChild(area);
    }
  }

  function render(host, parsed, options) {
    options = options || {};
    var state = stateFor(host);
    var previousLastId = state.lastSectionId;
    var fragment = document.createDocumentFragment();
    var toolbar = element('div', 'job-console-toolbar');
    var sectionList = element('div', 'job-console-sections');
    var raw = element('pre', 'job-console-raw', parsed.raw);

    host.classList.add('job-console-host');
    toolbar.appendChild(button('expand', 'Expand all', 'fa-angle-double-down'));
    toolbar.appendChild(button('collapse', 'Collapse all', 'fa-angle-double-up'));
    toolbar.appendChild(button('raw', state.rawVisible ? 'Grouped view' : 'Raw log', 'fa-file-text-o'));
    toolbar.appendChild(button('copy', 'Copy', 'fa-copy'));
    toolbar.appendChild(element('span', 'job-console-total', parsed.sections.length + ' section' + (parsed.sections.length === 1 ? '' : 's')));

    parsed.sections.forEach(function(section, index) {
      var isNew = ! state.knownIds[section.id];
      var details = element('details', 'job-console-section job-console-section-' + section.kind + (section.hasError ? ' has-error' : ''));
      var summary = element('summary', 'job-console-summary');
      var title = element('span', 'job-console-title');
      var meta = element('span', 'job-console-meta');
      var lineBadge = element('span', 'job-console-badge', section.lineCount + ' line' + (section.lineCount === 1 ? '' : 's'));
      var content = element('pre', 'job-console-content', section.text);

      details.setAttribute('data-console-section-id', section.id);
      details.setAttribute('data-console-kind', section.kind);

      if (isNew) {
        state.openById[section.id] = defaultOpen(section, index, parsed.sections.length, options);
        state.knownIds[section.id] = true;

        if (options.live && previousLastId && previousLastId !== section.id && ! state.touchedById[previousLastId]) {
          state.openById[previousLastId] = false;
        }
      }

      details.open = !! state.openById[section.id];

      title.appendChild(element('i', 'fa ' + section.icon));
      title.appendChild(document.createTextNode(' ' + section.title));
      meta.appendChild(lineBadge);

      if (section.hasError) {
        meta.appendChild(element('span', 'job-console-badge job-console-badge-error', 'error'));
      }

      if (options.live && index === parsed.sections.length - 1) {
        meta.appendChild(element('span', 'job-console-badge job-console-badge-live', 'live'));
      }

      summary.appendChild(title);
      summary.appendChild(meta);
      details.appendChild(summary);
      details.appendChild(content);
      details.addEventListener('toggle', function() {
        state.openById[section.id] = details.open;
        state.touchedById[section.id] = true;
      });
      sectionList.appendChild(details);
    });

    if (parsed.sections.length === 0) {
      sectionList.appendChild(element('div', 'job-console-empty', options.emptyMessage || 'Console output is empty.'));
    }

    state.lastSectionId = parsed.sections.length ? parsed.sections[parsed.sections.length - 1].id : '';
    sectionList.hidden = state.rawVisible;
    raw.hidden = ! state.rawVisible;
    fragment.appendChild(toolbar);
    fragment.appendChild(sectionList);
    fragment.appendChild(raw);

    while (host.firstChild) {
      host.removeChild(host.firstChild);
    }
    host.appendChild(fragment);

    toolbar.addEventListener('click', function(event) {
      var actionButton = event.target.closest ? event.target.closest('[data-console-action]') : null;
      var action = actionButton ? actionButton.getAttribute('data-console-action') : '';

      if (! action) {
        return;
      }

      if (action === 'copy') {
        copyText(state.text, actionButton);
        return;
      }

      if (action === 'raw') {
        state.rawVisible = ! state.rawVisible;
        render(host, parse(state.text), options);
        return;
      }

      Array.prototype.forEach.call(host.querySelectorAll('.job-console-section'), function(details) {
        var id = details.getAttribute('data-console-section-id');
        var shouldOpen = action === 'expand';
        state.openById[id] = shouldOpen;
        state.touchedById[id] = true;
        details.open = shouldOpen;
      });
    });
  }

  function resolveHost(target) {
    if (typeof target === 'string') {
      return document.querySelector(target);
    }
    return target && target.jquery ? target[0] : target;
  }

  function setText(target, text, options) {
    var host = resolveHost(target);
    if (! host) {
      return null;
    }

    var state = stateFor(host);
    state.text = String(text == null ? '' : text);
    render(host, parse(state.text), options || {});
    return host;
  }

  function appendText(target, text, options) {
    var host = resolveHost(target);
    if (! host || ! text) {
      return host || null;
    }

    var state = stateFor(host);
    state.text += String(text);
    render(host, parse(state.text), options || {});
    return host;
  }

  function getText(target) {
    var host = resolveHost(target);
    return host ? stateFor(host).text : '';
  }

  return {
    appendText: appendText,
    classifyLine: classifyLine,
    getText: getText,
    parse: parse,
    setText: setText
  };
}));
