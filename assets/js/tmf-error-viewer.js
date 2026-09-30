(function(root, factory) {
  'use strict';

  var api = factory();

  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }

  if (root) {
    root.JobSeekerTmfErrors = api;
  }
})(typeof window !== 'undefined' ? window : this, function() {
  'use strict';

  var FRAME_PATTERN = /^(\s*(?:[|+]\s*)*)File "([^"]+)", line (\d+)(?:, in (.*?))?\s*$/;
  var EXCEPTION_PATTERN = /^([A-Za-z_][\w.]*(?:Error|Exception|Warning|Interrupt|Exit|Fault|StopIteration|GeneratorExit))(?:\:\s*(.*))?$/;
  var PYTHON_KEYWORDS = {
    'and': true, 'as': true, 'assert': true, 'async': true, 'await': true,
    'break': true, 'class': true, 'continue': true, 'def': true, 'del': true,
    'elif': true, 'else': true, 'except': true, 'False': true, 'finally': true,
    'for': true, 'from': true, 'global': true, 'if': true, 'import': true,
    'in': true, 'is': true, 'lambda': true, 'None': true, 'nonlocal': true,
    'not': true, 'or': true, 'pass': true, 'raise': true, 'return': true,
    'True': true, 'try': true, 'while': true, 'with': true, 'yield': true
  };

  function normalizedText(value) {
    return value == null ? '' : String(value).replace(/\r\n?/g, '\n');
  }

  function stripExceptionPrefix(line) {
    return String(line || '').replace(/^\s*(?:[|+]\s*)*/, '').trim();
  }

  function exceptionParts(line) {
    var candidate = stripExceptionPrefix(line);
    var match = EXCEPTION_PATTERN.exec(candidate);

    if (!match && !/^(?:ERROR|FATAL|CRITICAL|WARN(?:ING)?|INFO|DEBUG)\s*:/i.test(candidate) &&
        /^[A-Za-z_][\w.]*\s*:\s*.+$/.test(candidate)) {
      match = /^([A-Za-z_][\w.]*)(?:\:\s*(.*))?$/.exec(candidate);
    }

    return match ? { type: match[1], message: match[2] || '' } : null;
  }

  function looksPython(record, message) {
    var runtime = [record && record.type, record && record.origin].join(' ');
    return /Traceback \(most recent call last\):/m.test(message) ||
      /^\s*(?:[|+]\s*)*File "[^"]+", line \d+/m.test(message) ||
      /\bpython\b|(?:Error|Exception|Warning|Interrupt|StopIteration)\b/i.test(runtime);
  }

  function shortPath(path) {
    var parts = String(path || '').split(/[\\/]/);
    return parts[parts.length - 1] || path || 'unknown';
  }

  function cleanSummary(value) {
    return normalizedText(value)
      .replace(/<[^>]*>/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function jsonPayload(value) {
    var candidate = normalizedText(value).trim();
    if (!candidate || (candidate.charAt(0) !== '{' && candidate.charAt(0) !== '[')) {
      return null;
    }

    try {
      var parsed = JSON.parse(candidate);
      if (!parsed || typeof parsed !== 'object') {
        return null;
      }
      return {
        value: parsed,
        pretty: JSON.stringify(parsed, null, 2)
      };
    } catch (error) {
      return null;
    }
  }

  function analyzeMessage(record) {
    record = record || {};
    var raw = normalizedText(record.msg);
    var json = jsonPayload(raw);
    var display = json ? json.pretty : raw;
    var trimmed = raw.trim();
    var kind = 'note';
    var label = 'Run note';

    if (json) {
      kind = 'json';
      label = 'JSON payload';
    } else if (/^\s*</.test(trimmed) && /<\/?[A-Za-z][^>]*>/.test(trimmed)) {
      kind = 'html';
      label = 'HTML source';
    } else if (display.indexOf('\n') !== -1) {
      kind = 'log';
      label = 'Multiline log';
    }

    var total = parseInt(record.records_total, 10);
    var processed = parseInt(record.records_processed, 10);
    total = isNaN(total) ? 0 : Math.max(0, total);
    processed = isNaN(processed) ? 0 : Math.max(0, processed);

    return {
      raw: raw,
      display: display,
      lines: display.split('\n'),
      kind: kind,
      label: label,
      json: json ? json.value : null,
      summary: cleanSummary(raw).slice(0, 180) || 'No run message was recorded.',
      status: String(record.status || 'unknown').trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '-') || 'unknown',
      total: total,
      processed: processed,
      progress: total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : null
    };
  }

  function suggestedCheck(analysis) {
    var type = String(analysis && analysis.exceptionType || '');
    var text = [type, analysis && analysis.exceptionMessage, analysis && analysis.message].join(' ');

    if (/ModuleNotFoundError|No module named|ImportError/i.test(text)) {
      return 'Check that the package is declared in requirements.txt or pyproject.toml and installed in the runtime used by this job.';
    }
    if (/FileNotFoundError|No such file or directory/i.test(text)) {
      return 'Verify the path at the crash site and that the file is present in the job workspace or mounted data asset.';
    }
    if (/PermissionError|permission denied|access denied/i.test(text)) {
      return 'Check the runtime user and the permissions on the referenced file, connector, or destination.';
    }
    if (/KeyError/i.test(text)) {
      return 'Inspect the input record or mapping at the crash site for the missing key before retrying the run.';
    }
    if (/SyntaxError|IndentationError|TabError/i.test(text)) {
      return 'Open the highlighted source location; the caret and final exception line identify the syntax to correct.';
    }
    if (/Timeout|timed out|ConnectionError|ConnectionRefused|could not connect|connection failed/i.test(text)) {
      return 'Check connector health, network reachability, credentials, and timeout settings for the failing origin.';
    }
    if (/MemoryError|out of memory|OOMKilled/i.test(text)) {
      return 'Review the job memory limit and consider processing the input in smaller batches.';
    }
    return '';
  }

  function analyze(record) {
    record = record || {};
    var message = normalizedText(record.message);
    var lines = message.split('\n');
    var frames = [];
    var sourceLines = {};
    var caretLines = {};
    var exception = null;
    var exceptionLineIndex = -1;

    lines.forEach(function(line, index) {
      var frameMatch = FRAME_PATTERN.exec(line);
      if (frameMatch) {
        frames.push({
          path: frameMatch[2],
          shortPath: shortPath(frameMatch[2]),
          line: parseInt(frameMatch[3], 10),
          functionName: frameMatch[4] || '<module>',
          lineIndex: index,
          prefix: frameMatch[1] || ''
        });

        if (index + 1 < lines.length && lines[index + 1].trim() !== '' &&
            !FRAME_PATTERN.test(lines[index + 1]) && !exceptionParts(lines[index + 1]) &&
            !/Traceback \(most recent call last\):/.test(lines[index + 1])) {
          sourceLines[index + 1] = true;
          if (index + 2 < lines.length && /^\s*(?:[|]\s*)*[\^~]+\s*$/.test(lines[index + 2])) {
            caretLines[index + 2] = true;
          }
        }
      }
    });

    for (var index = lines.length - 1; index >= 0; index -= 1) {
      var parts = exceptionParts(lines[index]);
      if (parts) {
        exception = parts;
        exceptionLineIndex = index;
        break;
      }
    }

    if (!exception) {
      var origin = stripExceptionPrefix(record.origin || '');
      if (EXCEPTION_PATTERN.test(origin)) {
        exception = { type: origin, message: '' };
      } else {
        exception = {
          type: record.type || 'Runtime error',
          message: lines.filter(function(line) { return line.trim() !== ''; }).slice(-1)[0] || 'No diagnostic message was recorded.'
        };
      }
    }

    if (frames.length) {
      frames[frames.length - 1].crashSite = true;
    }

    return {
      isPython: looksPython(record, message),
      message: message,
      lines: lines,
      frames: frames,
      sourceLines: sourceLines,
      caretLines: caretLines,
      exceptionType: exception.type,
      exceptionMessage: exception.message,
      exceptionLineIndex: exceptionLineIndex
    };
  }

  function lineKind(analysis, line, index) {
    if (FRAME_PATTERN.test(line)) {
      return 'frame';
    }
    if (analysis.sourceLines[index]) {
      return 'source';
    }
    if (analysis.caretLines[index] || /^\s*(?:[|]\s*)*[\^~]+\s*$/.test(line)) {
      return 'caret';
    }
    if (/Traceback \(most recent call last\):/.test(line) || /Exception Group Traceback/.test(line)) {
      return 'header';
    }
    if (/During handling of the above exception|direct cause of the following exception/.test(line)) {
      return 'chain';
    }
    if (index === analysis.exceptionLineIndex || exceptionParts(line)) {
      return 'exception';
    }
    if (/\b(?:ERROR|FATAL|FAILED?|CRITICAL)\b/i.test(line)) {
      return 'error';
    }
    if (/\bWARN(?:ING)?\b/i.test(line)) {
      return 'warning';
    }
    return line.trim() === '' ? 'empty' : 'plain';
  }

  function element(tagName, className, text) {
    var node = document.createElement(tagName);
    if (className) {
      node.className = className;
    }
    if (text !== undefined && text !== null) {
      node.textContent = String(text);
    }
    return node;
  }

  function icon(name) {
    var node = element('i', 'fa ' + name);
    node.setAttribute('aria-hidden', 'true');
    return node;
  }

  function button(className, label, iconName) {
    var node = element('button', className);
    node.type = 'button';
    if (iconName) {
      node.appendChild(icon(iconName));
      node.appendChild(document.createTextNode(' '));
    }
    node.appendChild(document.createTextNode(label));
    return node;
  }

  function copyText(text, sourceButton) {
    var originalHtml = sourceButton.innerHTML;

    function copied() {
      sourceButton.textContent = 'Copied';
      sourceButton.classList.add('is-copied');
      window.setTimeout(function() {
        sourceButton.innerHTML = originalHtml;
        sourceButton.classList.remove('is-copied');
      }, 1200);
    }

    function legacyCopy() {
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

    if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(copied, legacyCopy);
      return;
    }

    legacyCopy();
  }

  function appendMetadata(list, label, value, className) {
    var item = element('div', 'tmf-error-fact' + (className ? ' ' + className : ''));
    item.appendChild(element('dt', '', label));
    item.appendChild(element('dd', '', value || 'Not recorded'));
    list.appendChild(item);
  }

  function appendPythonSource(code, source) {
    var tokenPattern = /("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|#.*$|\b(?:and|as|assert|async|await|break|class|continue|def|del|elif|else|except|False|finally|for|from|global|if|import|in|is|lambda|None|nonlocal|not|or|pass|raise|return|True|try|while|with|yield)\b|\b\d+(?:\.\d+)?\b)/g;
    var cursor = 0;
    var match;

    while ((match = tokenPattern.exec(source)) !== null) {
      if (match.index > cursor) {
        code.appendChild(document.createTextNode(source.slice(cursor, match.index)));
      }

      var token = match[0];
      var className = 'tmf-py-number';
      if (token.charAt(0) === '#') {
        className = 'tmf-py-comment';
      } else if (token.charAt(0) === '"' || token.charAt(0) === "'") {
        className = 'tmf-py-string';
      } else if (PYTHON_KEYWORDS[token]) {
        className = 'tmf-py-keyword';
      }
      code.appendChild(element('span', className, token));
      cursor = match.index + token.length;
    }

    if (cursor < source.length) {
      code.appendChild(document.createTextNode(source.slice(cursor)));
    }
  }

  function appendJsonSource(code, source) {
    var tokenPattern = /("(?:\\.|[^"\\])*")(?=\s*:)|("(?:\\.|[^"\\])*")|\b(true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/g;
    var cursor = 0;
    var match;

    while ((match = tokenPattern.exec(source)) !== null) {
      if (match.index > cursor) {
        code.appendChild(document.createTextNode(source.slice(cursor, match.index)));
      }
      var className = match[1] ? 'tmf-json-key' : (match[2] ? 'tmf-json-string' : (match[3] ? 'tmf-json-literal' : 'tmf-json-number'));
      code.appendChild(element('span', className, match[0]));
      cursor = match.index + match[0].length;
    }

    if (cursor < source.length) {
      code.appendChild(document.createTextNode(source.slice(cursor)));
    }
  }

  function appendFrameLine(code, line) {
    var match = FRAME_PATTERN.exec(line);
    if (!match) {
      code.textContent = line;
      return;
    }

    code.appendChild(document.createTextNode((match[1] || '') + 'File "'));
    code.appendChild(element('span', 'tmf-trace-path', match[2]));
    code.appendChild(document.createTextNode('", line '));
    code.appendChild(element('span', 'tmf-trace-line-number', match[3]));
    if (match[4]) {
      code.appendChild(document.createTextNode(', in '));
      code.appendChild(element('span', 'tmf-trace-function', match[4]));
    }
  }

  function appendExceptionLine(code, line) {
    var prefixMatch = /^(\s*(?:[|+]\s*)*)/.exec(line);
    var prefix = prefixMatch ? prefixMatch[1] : '';
    var parts = exceptionParts(line);
    if (!parts) {
      code.textContent = line;
      return;
    }

    code.appendChild(document.createTextNode(prefix));
    code.appendChild(element('span', 'tmf-trace-exception-name', parts.type));
    if (parts.message) {
      code.appendChild(document.createTextNode(': '));
      code.appendChild(element('span', 'tmf-trace-exception-message', parts.message));
    }
  }

  function renderEditor(analysis) {
    var editor = element('section', 'tmf-trace-editor is-wrapped');
    editor.setAttribute('aria-label', analysis.isPython ? 'Python traceback' : 'Runtime log');
    var toolbar = element('div', 'tmf-trace-toolbar');
    var trafficLights = element('span', 'tmf-trace-window-controls');
    trafficLights.setAttribute('aria-hidden', 'true');
    trafficLights.appendChild(element('i', 'tmf-window-dot tmf-window-dot-red'));
    trafficLights.appendChild(element('i', 'tmf-window-dot tmf-window-dot-yellow'));
    trafficLights.appendChild(element('i', 'tmf-window-dot tmf-window-dot-green'));
    toolbar.appendChild(trafficLights);
    toolbar.appendChild(element('strong', 'tmf-trace-language', analysis.isPython ? 'Python traceback' : 'Runtime log'));
    toolbar.appendChild(element('span', 'tmf-trace-line-count', analysis.lines.length + (analysis.lines.length === 1 ? ' line' : ' lines')));

    var search = element('label', 'tmf-trace-search');
    search.appendChild(icon('fa-search'));
    var searchInput = element('input', 'tmf-trace-search-input');
    searchInput.type = 'search';
    searchInput.placeholder = 'Find in log';
    searchInput.setAttribute('aria-label', 'Find text in this diagnostic log');
    search.appendChild(searchInput);
    var searchCount = element('span', 'tmf-trace-search-count');
    search.appendChild(searchCount);
    toolbar.appendChild(search);

    var actions = element('span', 'tmf-trace-actions');
    var wrapButton = button('tmf-trace-action is-active', 'Wrap lines', 'fa-align-left');
    wrapButton.setAttribute('aria-pressed', 'true');
    wrapButton.addEventListener('click', function() {
      var wrapped = editor.classList.toggle('is-wrapped');
      wrapButton.classList.toggle('is-active', wrapped);
      wrapButton.setAttribute('aria-pressed', wrapped ? 'true' : 'false');
    });
    actions.appendChild(wrapButton);

    var copyButton = button('tmf-trace-action', 'Copy traceback', 'fa-clipboard');
    copyButton.addEventListener('click', function() {
      copyText(analysis.message, copyButton);
    });
    actions.appendChild(copyButton);
    toolbar.appendChild(actions);
    editor.appendChild(toolbar);

    var lines = element('div', 'tmf-trace-lines');
    var crashLine = analysis.frames.length ? analysis.frames[analysis.frames.length - 1].lineIndex : -1;
    analysis.lines.forEach(function(line, index) {
      var kind = lineKind(analysis, line, index);
      var row = element('div', 'tmf-trace-row tmf-trace-row-' + kind + (index === crashLine ? ' is-crash-site' : ''));
      row.setAttribute('data-trace-line', String(index + 1));
      row.setAttribute('data-trace-text', line.toLowerCase());
      row.appendChild(element('span', 'tmf-trace-gutter', index + 1));
      var code = element('code', 'tmf-trace-code');

      if (kind === 'frame') {
        appendFrameLine(code, line);
      } else if (kind === 'source' && analysis.isPython) {
        appendPythonSource(code, line);
      } else if (kind === 'exception') {
        appendExceptionLine(code, line);
      } else {
        code.textContent = line || ' ';
      }

      row.appendChild(code);
      lines.appendChild(row);
    });
    editor.appendChild(lines);

    function updateSearch() {
      var query = searchInput.value.trim().toLowerCase();
      var matches = 0;
      Array.prototype.forEach.call(lines.querySelectorAll('.tmf-trace-row'), function(row) {
        var visible = !query || String(row.getAttribute('data-trace-text') || '').indexOf(query) !== -1;
        row.classList.toggle('is-search-hidden', !visible);
        if (query && visible) {
          matches += 1;
        }
      });
      searchCount.textContent = query ? String(matches) : '';
      search.classList.toggle('has-no-matches', !!query && matches === 0);
    }

    searchInput.addEventListener('input', updateSearch);
    searchInput.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        searchInput.value = '';
        updateSearch();
      }
    });
    editor.clearTraceSearch = function() {
      if (!searchInput.value) {
        return;
      }
      searchInput.value = '';
      updateSearch();
    };
    return editor;
  }

  function renderFrames(analysis, editor) {
    if (!analysis.frames.length) {
      return null;
    }

    var section = element('section', 'tmf-error-stack');
    var heading = element('div', 'tmf-error-stack-heading');
    heading.appendChild(icon('fa-code-fork'));
    heading.appendChild(document.createTextNode(' Call stack'));
    heading.appendChild(element('span', 'tmf-error-stack-count', analysis.frames.length + (analysis.frames.length === 1 ? ' frame' : ' frames')));
    section.appendChild(heading);
    var frameList = element('div', 'tmf-error-frame-list');

    analysis.frames.forEach(function(frame, index) {
      var frameButton = button('tmf-error-frame' + (frame.crashSite ? ' is-crash-site' : ''), '', 'fa-file-code-o');
      frameButton.setAttribute('title', frame.path + ':' + frame.line);
      frameButton.setAttribute('aria-label', (frame.crashSite ? 'Crash site, ' : '') + frame.path + ', line ' + frame.line + ', in ' + frame.functionName);
      frameButton.appendChild(element('span', 'tmf-error-frame-file', frame.shortPath));
      frameButton.appendChild(element('span', 'tmf-error-frame-line', ':' + frame.line));
      frameButton.appendChild(element('span', 'tmf-error-frame-function', frame.functionName));
      if (frame.crashSite) {
        frameButton.appendChild(element('span', 'tmf-error-frame-badge', 'crash site'));
      }
      frameButton.addEventListener('click', function() {
        if (typeof editor.clearTraceSearch === 'function') {
          editor.clearTraceSearch();
        }
        var target = editor.querySelector('[data-trace-line="' + (frame.lineIndex + 1) + '"]');
        if (!target) {
          return;
        }
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
        target.classList.remove('is-focused');
        window.setTimeout(function() { target.classList.add('is-focused'); }, 0);
        window.setTimeout(function() { target.classList.remove('is-focused'); }, 2200);
      });
      frameList.appendChild(frameButton);
    });

    section.appendChild(frameList);
    return section;
  }

  function recordCopyText(record) {
    var fields = [
      'Error ' + (record.id || 'unknown'),
      'Job: ' + (record.job_name || 'Unknown'),
      'Instance: ' + (record.tmf_id || 'Unknown'),
      'Type: ' + (record.type || 'Unknown'),
      'Origin: ' + (record.origin || 'Unknown'),
      'Code: ' + (record.code == null || record.code === '' ? 'Unknown' : record.code),
      'Moment: ' + (record.moment || 'Unknown'),
      '',
      normalizedText(record.message)
    ];
    return fields.join('\n');
  }

  function renderCard(record, index, options) {
    var analysis = analyze(record);
    var card = element('article', 'tmf-error-card' + (analysis.isPython ? ' is-python' : ' is-runtime'));
    var header = element('header', 'tmf-error-card-header');
    var heading = element('div', 'tmf-error-heading');
    var indexLabel = element('span', 'tmf-error-index', String(index + 1));
    indexLabel.setAttribute('aria-label', 'Error ' + (index + 1));
    heading.appendChild(indexLabel);
    var title = element('div', 'tmf-error-title');
    title.appendChild(element('strong', '', analysis.exceptionType || record.type || 'Runtime error'));
    if (analysis.exceptionMessage) {
      title.appendChild(element('span', '', analysis.exceptionMessage));
    }
    heading.appendChild(title);
    header.appendChild(heading);

    var copyButton = button('btn btn-default btn-xs tmf-error-copy', 'Copy error', 'fa-clipboard');
    copyButton.addEventListener('click', function() {
      copyText(recordCopyText(record), copyButton);
    });
    header.appendChild(copyButton);
    card.appendChild(header);

    var facts = element('dl', 'tmf-error-facts');
    appendMetadata(facts, 'Job', record.job_name || options.jobName || 'Unknown');
    appendMetadata(facts, 'Occurred', options.formatMoment ? options.formatMoment(record.moment) : record.moment);
    appendMetadata(facts, 'Origin', record.origin || 'Unknown');
    appendMetadata(facts, 'Type', record.type || 'Unknown');
    appendMetadata(facts, 'Code', record.code == null || record.code === '' ? 'Unknown' : String(record.code), 'tmf-error-fact-code');
    appendMetadata(facts, 'Error ID', record.id == null ? 'Unknown' : String(record.id));
    card.appendChild(facts);

    var guidanceText = suggestedCheck(analysis);
    if (guidanceText) {
      var guidance = element('aside', 'tmf-error-guidance');
      guidance.appendChild(icon('fa-lightbulb-o'));
      var guidanceCopy = element('span');
      guidanceCopy.appendChild(element('strong', '', 'Suggested next check'));
      guidanceCopy.appendChild(document.createTextNode(guidanceText));
      guidance.appendChild(guidanceCopy);
      card.appendChild(guidance);
    }

    var editor = renderEditor(analysis);
    var stack = renderFrames(analysis, editor);
    if (stack) {
      card.appendChild(stack);
    }
    card.appendChild(editor);
    return card;
  }

  function render(container, records, options) {
    options = options || {};
    records = Array.isArray(records) ? records : [];
    container.textContent = '';

    if (!records.length) {
      var empty = element('div', 'tmf-error-state tmf-error-state-empty');
      empty.appendChild(icon('fa-check-circle'));
      empty.appendChild(element('h4', '', 'No error details were recorded'));
      empty.appendChild(element('p', '', 'The transaction is marked for review, but its diagnostic payload is empty. Check the Jenkins console for the complete build output.'));
      container.appendChild(empty);
      return [];
    }

    var analyses = records.map(analyze);
    var overview = element('div', 'tmf-error-overview');
    var overviewText = element('div', 'tmf-error-overview-text');
    overviewText.appendChild(element('strong', '', records.length + (records.length === 1 ? ' error event' : ' error events')));
    var context = options.jobName || records[0].job_name || 'Unknown job';
    if (options.instanceId || records[0].tmf_id) {
      context += '  /  ' + (options.instanceId || records[0].tmf_id);
    }
    overviewText.appendChild(element('span', '', context));
    overview.appendChild(overviewText);
    var copyAll = button('btn btn-default btn-sm tmf-error-copy-all', 'Copy all diagnostics', 'fa-clipboard');
    copyAll.addEventListener('click', function() {
      copyText(records.map(recordCopyText).join('\n\n' + new Array(73).join('-') + '\n\n'), copyAll);
    });
    overview.appendChild(copyAll);
    container.appendChild(overview);

    var cards = element('div', 'tmf-error-cards');
    records.forEach(function(record, index) {
      cards.appendChild(renderCard(record || {}, index, options));
    });
    container.appendChild(cards);
    return analyses;
  }

  function appendRunFact(list, label, value, className) {
    var item = element('div', 'tmf-log-fact' + (className ? ' ' + className : ''));
    item.appendChild(element('dt', '', label));
    item.appendChild(element('dd', '', value == null || value === '' ? 'Not recorded' : value));
    list.appendChild(item);
  }

  function renderMessageLines(analysis) {
    var editor = element('section', 'tmf-log-editor is-wrapped');
    var toolbar = element('div', 'tmf-log-toolbar');
    toolbar.appendChild(icon(analysis.kind === 'json' ? 'fa-code' : 'fa-terminal'));
    toolbar.appendChild(element('strong', 'tmf-log-language', analysis.label));
    toolbar.appendChild(element('span', 'tmf-log-line-count', analysis.lines.length + (analysis.lines.length === 1 ? ' line' : ' lines')));

    var search = element('label', 'tmf-log-search');
    search.appendChild(icon('fa-search'));
    var input = element('input', 'tmf-log-search-input');
    input.type = 'search';
    input.placeholder = 'Find in message';
    input.setAttribute('aria-label', 'Find text in the run message');
    search.appendChild(input);
    var count = element('span', 'tmf-log-search-count');
    search.appendChild(count);
    toolbar.appendChild(search);

    var actions = element('span', 'tmf-log-actions');
    var wrap = button('tmf-log-action is-active', 'Wrap', 'fa-align-left');
    wrap.setAttribute('aria-pressed', 'true');
    wrap.addEventListener('click', function() {
      var wrapped = editor.classList.toggle('is-wrapped');
      wrap.classList.toggle('is-active', wrapped);
      wrap.setAttribute('aria-pressed', wrapped ? 'true' : 'false');
    });
    actions.appendChild(wrap);
    var copy = button('tmf-log-action', 'Copy', 'fa-clipboard');
    copy.addEventListener('click', function() { copyText(analysis.raw, copy); });
    actions.appendChild(copy);
    toolbar.appendChild(actions);
    editor.appendChild(toolbar);

    var lines = element('div', 'tmf-log-lines');
    analysis.lines.forEach(function(line, index) {
      var row = element('div', 'tmf-log-row');
      row.setAttribute('data-log-text', line.toLowerCase());
      row.appendChild(element('span', 'tmf-log-gutter', index + 1));
      var code = element('code', 'tmf-log-code');
      if (analysis.kind === 'json') {
        appendJsonSource(code, line);
      } else {
        code.textContent = line || ' ';
      }
      row.appendChild(code);
      lines.appendChild(row);
    });
    editor.appendChild(lines);

    function updateSearch() {
      var query = input.value.trim().toLowerCase();
      var matches = 0;
      Array.prototype.forEach.call(lines.querySelectorAll('.tmf-log-row'), function(row) {
        var visible = !query || String(row.getAttribute('data-log-text') || '').indexOf(query) !== -1;
        row.classList.toggle('is-search-hidden', !visible);
        if (query && visible) {
          matches += 1;
        }
      });
      count.textContent = query ? String(matches) : '';
      search.classList.toggle('has-no-matches', !!query && matches === 0);
    }
    input.addEventListener('input', updateSearch);
    input.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        input.value = '';
        updateSearch();
      }
    });
    return editor;
  }

  function renderMessage(container, record, options) {
    options = options || {};
    record = record || {};
    var analysis = analyzeMessage(record);
    container.textContent = '';

    var overview = element('section', 'tmf-log-overview tmf-log-status-' + analysis.status);
    var status = element('span', 'tmf-log-status');
    status.appendChild(icon(analysis.status === 'ready' ? 'fa-check-circle' : (analysis.status === 'running' ? 'fa-refresh' : (analysis.status === 'error' ? 'fa-times-circle' : 'fa-info-circle'))));
    status.appendChild(document.createTextNode(' ' + (analysis.status || 'unknown')));
    overview.appendChild(status);
    var overviewCopy = element('div', 'tmf-log-overview-copy');
    overviewCopy.appendChild(element('strong', '', record.job_name || options.jobName || 'Unknown job'));
    overviewCopy.appendChild(element('span', '', analysis.summary));
    overview.appendChild(overviewCopy);
    var copy = button('btn btn-default btn-sm tmf-log-copy', 'Copy message', 'fa-clipboard');
    copy.addEventListener('click', function() { copyText(analysis.raw, copy); });
    overview.appendChild(copy);
    container.appendChild(overview);

    var facts = element('dl', 'tmf-log-facts');
    appendRunFact(facts, 'Environment', record.environment);
    appendRunFact(facts, 'Transaction', record.instance_id || options.instanceId, 'tmf-log-fact-code');
    appendRunFact(facts, 'Event', record.event_text);
    appendRunFact(facts, 'Progress', analysis.total > 0 ? analysis.processed + ' / ' + analysis.total + ' (' + analysis.progress + '%)' : (analysis.processed ? String(analysis.processed) : 'Not recorded'));
    appendRunFact(facts, 'Last activity', options.formatMoment ? options.formatMoment(record.last_activity) : record.last_activity);
    appendRunFact(facts, 'Message format', analysis.label);
    container.appendChild(facts);

    if (analysis.progress !== null) {
      var progress = element('div', 'tmf-log-progress');
      var progressBar = element('span', 'tmf-log-progress-bar');
      progressBar.style.width = analysis.progress + '%';
      progress.appendChild(progressBar);
      progress.setAttribute('role', 'progressbar');
      progress.setAttribute('aria-valuemin', '0');
      progress.setAttribute('aria-valuemax', '100');
      progress.setAttribute('aria-valuenow', String(analysis.progress));
      container.appendChild(progress);
    }

    if (!analysis.raw.trim()) {
      var empty = element('div', 'tmf-error-state tmf-error-state-empty');
      empty.appendChild(icon('fa-comment-o'));
      empty.appendChild(element('h4', '', 'No run message was recorded'));
      empty.appendChild(element('p', '', 'This transaction has status and progress metadata, but no message payload.'));
      container.appendChild(empty);
    } else {
      container.appendChild(renderMessageLines(analysis));
    }
    return analysis;
  }

  return {
    analyze: analyze,
    analyzeMessage: analyzeMessage,
    lineKind: lineKind,
    suggestedCheck: suggestedCheck,
    render: render,
    renderMessage: renderMessage
  };
});
