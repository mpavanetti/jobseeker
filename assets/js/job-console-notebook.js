/*
 * The console of a notebook job, drawn like the notebook it ran.
 *
 * jobseeker.notebook prints one "[JobSeeker Notebook]" marker per cell
 * boundary, each cell's source prefixed "│ " and its text outputs in between
 * (application/third_party/python/jobseeker_sdk/src/jobseeker/notebook.py).
 * parse() turns that log back into cells; render() draws them as notebook
 * cells with a timeline of the run, its parameters, highlighted sources and
 * outputs. Images and HTML are only summarised in the log: once the runner
 * has published the executed notebook ("saved | notebook-runs/..."), it is
 * fetched and its rich outputs replace the summaries. Notebook HTML renders
 * in a sandboxed frame that runs no scripts and loads nothing.
 *
 * Used by job-console-groups.js for "[JobSeeker] Notebook execution"
 * sections; parse() also runs under Node for tests.
 */
(function(root, factory) {
  var api = factory(root);

  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }

  if (root) {
    root.JobSeekerNotebookConsole = api;
  }
}(typeof window !== 'undefined' ? window : null, function(root) {
  'use strict';

  var MARKER = /^\[JobSeeker Notebook\]\s+([a-z]+)(?:\s+(\d+)\/(\d+))?\s*(?:\|\s?([\s\S]*))?$/;
  var SOURCE = /^│ ?/;
  var ANSI = /\x1b\[[0-?]*[ -\/]*[@-~]/g;
  var SOURCE_PREVIEW_LINES = 24;
  var OUTPUT_PREVIEW_LINES = 60;

  function cleanLine(line) {
    return String(line == null ? '' : line).replace(ANSI, '').replace(/\r$/, '');
  }

  function richParts(parts) {
    return parts.map(function(part) {
      var split = String(part).indexOf(' ');
      return split === -1 ? {mime: part, size: ''} : {mime: part.slice(0, split), size: part.slice(split + 1)};
    });
  }

  /** Cells, parameters and progress of a notebook run, from its log. */
  function parse(text) {
    var model = {
      path: '', cellCount: 0, codeCount: 0, kernel: '', parameters: null,
      cells: [], saved: '', finish: null, notesBefore: [], notesAfter: [], started: false
    };
    var byIndex = {};
    var cell = null;
    var output = null;

    function cellAt(index, total) {
      if (! byIndex[index]) {
        byIndex[index] = {index: index, total: total, type: 'code', tags: [], source: [], outputs: [], state: 'pending', ran: false, closed: false, duration: '', note: ''};
        model.cells.push(byIndex[index]);
      }
      return byIndex[index];
    }

    String(text == null ? '' : text).replace(/\r\n?/g, '\n').split('\n').forEach(function(raw) {
      var line = cleanLine(raw);
      var match = MARKER.exec(line);
      if (match) {
        var name = match[1];
        var index = match[2] ? parseInt(match[2], 10) : 0;
        var total = match[3] ? parseInt(match[3], 10) : 0;
        var rest = match[4] == null ? '' : match[4];
        var parts = rest === '' ? [] : rest.split(' | ');
        if (name === 'start') {
          model.started = true;
          model.path = parts[0] || '';
          model.cellCount = parseInt(parts[1], 10) || 0;
          model.codeCount = parseInt(parts[2], 10) || 0;
          model.kernel = String(parts[3] || '').replace(/^kernel\s+/, '');
        } else if (name === 'parameters') {
          try { model.parameters = JSON.parse(rest); } catch (error) { model.parameters = null; }
        } else if (name === 'cell' && index) {
          cell = cellAt(index, total);
          cell.type = parts[0] || 'code';
          cell.tags = parts[1] ? parts[1].replace(/^tags\s+/, '').split(',') : [];
          cell.state = cell.type === 'code' ? 'pending' : 'text';
          output = null;
        } else if (name === 'run' && index) {
          cell = cellAt(index, total);
          cell.state = 'running';
          cell.ran = true;
          output = null;
        } else if ((name === 'stream' || name === 'result' || name === 'display' || name === 'error') && index) {
          cell = cellAt(index, total);
          if (name === 'stream') {
            output = {kind: 'stream', name: parts[0] || 'stdout', text: []};
          } else if (name === 'error') {
            output = {kind: 'error', ename: parts[0] || 'Error', evalue: parts.slice(1).join(' | '), text: []};
          } else {
            var label = parts.length && /^Out\[/.test(parts[0]) ? parts.shift() : '';
            output = {kind: name, label: label, rich: richParts(parts), text: []};
          }
          cell.outputs.push(output);
        } else if (name === 'done' && index) {
          cell = cellAt(index, total);
          cell.state = parts[0] || 'ok';
          if (cell.state === 'skipped') {
            cell.note = parts[1] || '';
          } else {
            cell.duration = parts[1] || '';
            cell.note = parts[2] || '';
          }
          cell.closed = true;
          output = null;
        } else if (name === 'saved') {
          model.saved = parts[0] || '';
        } else if (name === 'finish') {
          model.finish = {status: parts[0] || '', cells: parts[1] || '', elapsed: parts[2] || ''};
          cell = null;
          output = null;
        }
        return;
      }

      if (cell && ! cell.ran && ! cell.closed && output === null && SOURCE.test(line)) {
        cell.source.push(line.replace(SOURCE, ''));
      } else if (cell && cell.ran && ! cell.closed) {
        if (! output) {
          output = {kind: 'stream', name: 'stdout', text: []};
          cell.outputs.push(output);
        }
        output.text.push(line);
      } else if (/^\[JobSeeker\]\s+Notebook execution\s*$/.test(line) || /^\++ /.test(line)) {
        // The section's own heading, and the shell's trace of the runner's
        // set-up: the raw log keeps both.
      } else {
        var notes = model.started && (model.cells.length || model.finish) ? model.notesAfter : model.notesBefore;
        if (line.trim() !== '' || notes.length) {
          notes.push(line);
        }
      }
    });

    // A stream ends with the newline that ended its last print.
    model.cells.forEach(function(item) {
      item.outputs.forEach(function(out) {
        while (out.text.length && out.text[out.text.length - 1] === '') {
          out.text.pop();
        }
      });
    });
    model.cells.sort(function(left, right) { return left.index - right.index; });
    return model;
  }

  // ------------------------------------------------------------------
  // Small renderers: DOM helpers, Python highlighting, Markdown, ANSI.

  function el(name, className, text) {
    var node = document.createElement(name);
    if (className) {
      node.className = className;
    }
    if (text != null) {
      node.textContent = text;
    }
    return node;
  }

  function icon(name) {
    return el('i', 'fa ' + name);
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(character) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
    });
  }

  var PYTHON_RULES = [
    ['comment', /#.*/y],
    ['string', /[rbfuRBFU]{0,2}(?:"""[\s\S]*?(?:"""|$)|'''[\s\S]*?(?:'''|$)|"(?:[^"\\\n]|\\.)*"?|'(?:[^'\\\n]|\\.)*'?)/y],
    ['decorator', /@[A-Za-z_][\w.]*/y],
    ['keyword', /\b(?:False|None|True|and|as|assert|async|await|break|class|continue|def|del|elif|else|except|finally|for|from|global|if|import|in|is|lambda|nonlocal|not|or|pass|raise|return|try|while|with|yield|match|case)\b/y],
    ['builtin', /\b(?:print|len|range|enumerate|zip|map|filter|sorted|list|dict|set|tuple|str|int|float|bool|open|sum|min|max|abs|round|isinstance|type|super|self)\b/y],
    ['number', /\b(?:0[xob][\da-fA-F_]+|\d[\d_]*(?:\.\d*)?(?:[eE][+-]?\d+)?j?)\b/y],
    ['magic', /^[ \t]*[%!].*/my],
    ['operator', /[=+\-*\/%<>!&|^~]=?|:=/y]
  ];

  function highlightPython(source) {
    var html = '';
    var position = 0;
    var plain = '';
    var text = String(source);
    while (position < text.length) {
      var matched = false;
      for (var index = 0; index < PYTHON_RULES.length; index++) {
        var rule = PYTHON_RULES[index][1];
        if (rule.source.charAt(0) === '^' && position !== 0 && text.charAt(position - 1) !== '\n') {
          continue;
        }
        rule.lastIndex = position;
        var match = rule.exec(text);
        if (match && match[0].length) {
          html += escapeHtml(plain);
          plain = '';
          html += '<span class="nbc-tok-' + PYTHON_RULES[index][0] + '">' + escapeHtml(match[0]) + '</span>';
          position += match[0].length;
          matched = true;
          break;
        }
      }
      if (! matched) {
        plain += text.charAt(position);
        position++;
      }
    }
    return html + escapeHtml(plain);
  }

  function inlineMarkdown(text) {
    var html = escapeHtml(text);
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    html = html.replace(/\*\*([^*]+)\*\*|__([^_]+)__/g, function(all, a, b) { return '<strong>' + (a || b) + '</strong>'; });
    html = html.replace(/(^|[^*\w])\*([^*\s][^*]*)\*(?!\*)|(^|[^_\w])_([^_\s][^_]*)_(?!\w)/g, function(all, a, b, c, d) {
      return (a != null ? a : c) + '<em>' + (b != null ? b : d) + '</em>';
    });
    html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    return html;
  }

  /** A safe subset of Markdown: everything is escaped first. */
  function renderMarkdown(source) {
    var lines = String(source).replace(/\r\n?/g, '\n').split('\n');
    var html = [];
    var list = '';
    var paragraph = [];
    var fence = null;

    function flushParagraph() {
      if (paragraph.length) {
        html.push('<p>' + inlineMarkdown(paragraph.join(' ')) + '</p>');
        paragraph = [];
      }
    }
    function closeList() {
      if (list) {
        html.push('</' + list + '>');
        list = '';
      }
    }

    lines.forEach(function(line) {
      if (fence !== null) {
        if (/^\s*```/.test(line)) {
          html.push('<pre><code>' + escapeHtml(fence.join('\n')) + '</code></pre>');
          fence = null;
        } else {
          fence.push(line);
        }
        return;
      }
      var heading = /^(#{1,6})\s+(.*)$/.exec(line);
      var bullet = /^\s*[-*+]\s+(.*)$/.exec(line);
      var numbered = /^\s*\d+[.)]\s+(.*)$/.exec(line);
      if (/^\s*```/.test(line)) {
        flushParagraph(); closeList(); fence = [];
      } else if (heading) {
        flushParagraph(); closeList();
        html.push('<h' + heading[1].length + '>' + inlineMarkdown(heading[2]) + '</h' + heading[1].length + '>');
      } else if (bullet || numbered) {
        flushParagraph();
        var kind = bullet ? 'ul' : 'ol';
        if (list !== kind) { closeList(); html.push('<' + kind + '>'); list = kind; }
        html.push('<li>' + inlineMarkdown((bullet || numbered)[1]) + '</li>');
      } else if (/^\s*>\s?/.test(line)) {
        flushParagraph(); closeList();
        html.push('<blockquote>' + inlineMarkdown(line.replace(/^\s*>\s?/, '')) + '</blockquote>');
      } else if (/^\s*(?:---+|\*\*\*+)\s*$/.test(line)) {
        flushParagraph(); closeList(); html.push('<hr>');
      } else if (line.trim() === '') {
        flushParagraph(); closeList();
      } else {
        closeList(); paragraph.push(line.trim());
      }
    });
    if (fence !== null) {
      html.push('<pre><code>' + escapeHtml(fence.join('\n')) + '</code></pre>');
    }
    flushParagraph();
    closeList();
    return html.join('');
  }

  var ANSI_COLORS = {30: 'black', 31: 'red', 32: 'green', 33: 'yellow', 34: 'blue', 35: 'magenta', 36: 'cyan', 37: 'white',
    90: 'gray', 91: 'red', 92: 'green', 93: 'yellow', 94: 'blue', 95: 'magenta', 96: 'cyan', 97: 'white'};

  /** IPython's coloured tracebacks, as spans. */
  function ansiToHtml(text) {
    var html = '';
    var open = false;
    String(text).split(/(\x1b\[[0-9;]*m)/).forEach(function(part) {
      var codes = /^\x1b\[([0-9;]*)m$/.exec(part);
      if (! codes) {
        html += escapeHtml(part.replace(ANSI, ''));
        return;
      }
      if (open) {
        html += '</span>';
        open = false;
      }
      var classes = [];
      (codes[1] || '0').split(';').forEach(function(code) {
        code = parseInt(code, 10);
        if (ANSI_COLORS[code]) {
          classes.push('nbc-ansi-' + ANSI_COLORS[code]);
        } else if (code === 1) {
          classes.push('nbc-ansi-bold');
        }
      });
      if (classes.length) {
        html += '<span class="' + classes.join(' ') + '">';
        open = true;
      }
    });
    return html + (open ? '</span>' : '');
  }

  function joined(value) {
    return Array.isArray(value) ? value.join('') : String(value == null ? '' : value);
  }

  // ------------------------------------------------------------------
  // Executed notebooks: fetched once published, kept per path.

  function baseUrl() {
    if (root && typeof root.baseURL === 'string' && root.baseURL) {
      return root.baseURL.replace(/\/?$/, '/');
    }
    return '/';
  }

  function notebookEntry(ctx, path) {
    var cache = ctx.state.notebooks = ctx.state.notebooks || {};
    if (! cache[path]) {
      cache[path] = {status: 'idle', notebook: null, attempts: 0, lastTry: 0};
    }
    return cache[path];
  }

  // During a run the runner rewrites the notebook after every cell (on an
  // agent; a container publishes it when it ends), so it is fetched again as
  // cells finish, and once more when the run does.
  function loadNotebook(ctx, path, finished, progress) {
    var entry = notebookEntry(ctx, path);
    var now = Date.now();
    if (entry.status === 'loading' || typeof fetch !== 'function') {
      return entry;
    }
    if (entry.status === 'loaded' && (entry.final || (! finished && progress <= entry.progress))) {
      return entry;
    }
    if (entry.status === 'missing' && (entry.attempts >= (finished ? 20 : 400) || now - entry.lastTry < 4000 || (! finished && progress <= entry.progress))) {
      return entry;
    }
    var stale = entry.status === 'loaded' ? entry.notebook : null;
    entry.progress = progress;
    entry.status = 'loading';
    entry.attempts += 1;
    entry.lastTry = now;
    fetch(baseUrl() + 'jobView/notebookRun?path=' + encodeURIComponent(path), {credentials: 'same-origin', headers: {Accept: 'application/json'}})
      .then(function(response) {
        if (! response.ok) {
          throw new Error(String(response.status));
        }
        return response.json();
      })
      .then(function(notebook) {
        entry.notebook = notebook && Array.isArray(notebook.cells) ? notebook : stale;
        entry.status = entry.notebook ? 'loaded' : 'missing';
        entry.final = entry.status === 'loaded' && finished;
        if (ctx.rerender) {
          ctx.rerender();
        }
      })
      .catch(function() {
        entry.notebook = stale;
        entry.status = stale ? 'loaded' : 'missing';
        if (finished && entry.attempts < 20 && ctx.rerender && root && root.setTimeout) {
          root.setTimeout(ctx.rerender, 4000);
        }
      });
    return entry;
  }

  // ------------------------------------------------------------------
  // Drawing.

  var STATE_ICON = {ok: 'fa-check', error: 'fa-times', running: 'fa-circle-o-notch fa-spin', skipped: 'fa-ban', pending: 'fa-circle-thin'};
  var STATE_LABEL = {ok: 'ran', error: 'failed', running: 'running', skipped: 'skipped', pending: 'waiting'};

  function seconds(duration) {
    var match = /^(?:(\d+)m)?([\d.]+)s$/.exec(String(duration || ''));
    return match ? (parseInt(match[1] || '0', 10) * 60 + parseFloat(match[2])) : 0;
  }

  function preview(lines, limit, ctx, key, className) {
    var box = el('div', 'nbc-preview');
    var expanded = !! (ctx.state.expanded && ctx.state.expanded[key]);
    var shown = expanded || lines.length <= limit + 4 ? lines : lines.slice(0, limit);
    var pre = el('pre', className);
    pre.textContent = shown.join('\n');
    box.appendChild(pre);
    if (shown.length < lines.length) {
      var more = el('button', 'nbc-more', 'Show ' + (lines.length - shown.length) + ' more lines');
      more.type = 'button';
      more.addEventListener('click', function() {
        ctx.state.expanded = ctx.state.expanded || {};
        ctx.state.expanded[key] = true;
        if (ctx.rerender) {
          ctx.rerender();
        }
      });
      box.appendChild(more);
    }
    return box;
  }

  function frameFor(html) {
    var frame = el('iframe', 'nbc-html');
    // No scripts, no network: the frame only lays out the notebook's HTML.
    frame.setAttribute('sandbox', 'allow-same-origin');
    frame.setAttribute('referrerpolicy', 'no-referrer');
    frame.setAttribute('title', 'Notebook HTML output');
    frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8">'
      + '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'">'
      + '<style>body{margin:0;padding:8px;font:13px/1.45 -apple-system,Segoe UI,Arial,sans-serif;color:#1f2937;background:#fff}'
      + 'table{border-collapse:collapse;font-size:12px}th,td{border:0;padding:4px 10px;text-align:right}'
      + 'thead th{border-bottom:1px solid #cbd5e1;font-weight:600}tbody tr:nth-child(odd){background:#f5f7fa}tbody tr:hover{background:#e8f1fb}'
      + 'tbody th{font-weight:600}</style></head><body>' + html + '</body></html>';
    frame.addEventListener('load', function() {
      try {
        var height = frame.contentDocument.documentElement.scrollHeight;
        frame.style.height = Math.min(Math.max(height + 2, 40), 640) + 'px';
      } catch (error) {
        frame.style.height = '320px';
      }
    });
    return frame;
  }

  function richOutput(out, ctx, key) {
    var data = out.data || {};
    var box = el('div', 'nbc-rich');
    var image = ['image/png', 'image/jpeg', 'image/gif'].filter(function(mime) { return data[mime]; })[0];
    if (image) {
      var img = el('img', 'nbc-image');
      img.alt = 'Figure';
      img.src = 'data:' + image + ';base64,' + joined(data[image]).replace(/\s+/g, '');
      box.appendChild(img);
    } else if (data['image/svg+xml']) {
      var svg = el('img', 'nbc-image');
      svg.alt = 'Figure';
      svg.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(joined(data['image/svg+xml']));
      box.appendChild(svg);
    } else if (data['text/html']) {
      box.appendChild(frameFor(joined(data['text/html'])));
    } else if (data['text/markdown']) {
      var markdown = el('div', 'nbc-markdown nbc-markdown-output');
      markdown.innerHTML = renderMarkdown(joined(data['text/markdown']));
      box.appendChild(markdown);
    } else if (data['application/json']) {
      box.appendChild(preview(JSON.stringify(data['application/json'], null, 2).split('\n'), OUTPUT_PREVIEW_LINES, ctx, key, 'nbc-text'));
    } else if (data['text/plain'] != null) {
      box.appendChild(preview(joined(data['text/plain']).split('\n'), OUTPUT_PREVIEW_LINES, ctx, key, 'nbc-text'));
    } else {
      box.appendChild(el('span', 'nbc-placeholder', Object.keys(data).join(', ') || 'Empty output'));
    }
    return box;
  }

  function outputFrame(prompt, body, className) {
    var row = el('div', 'nbc-output ' + (className || ''));
    row.appendChild(el('div', 'nbc-prompt nbc-prompt-out', prompt || ''));
    var holder = el('div', 'nbc-output-body');
    holder.appendChild(body);
    row.appendChild(holder);
    return row;
  }

  /** Outputs as the executed notebook holds them (images, tables, colour). */
  function notebookOutputs(nbCell, ctx, key) {
    var list = el('div', 'nbc-outputs');
    (nbCell.outputs || []).forEach(function(out, position) {
      var outKey = key + ':' + position;
      if (out.output_type === 'stream') {
        list.appendChild(outputFrame('', preview(joined(out.text).replace(/\n$/, '').split('\n'), OUTPUT_PREVIEW_LINES, ctx, outKey, 'nbc-text' + (out.name === 'stderr' ? ' nbc-stderr' : '')), 'nbc-stream'));
      } else if (out.output_type === 'error') {
        var error = el('pre', 'nbc-text nbc-traceback');
        error.innerHTML = ansiToHtml((out.traceback || []).join('\n') || (out.ename + ': ' + out.evalue));
        list.appendChild(outputFrame('', error, 'nbc-error'));
      } else {
        var prompt = out.output_type === 'execute_result' && out.execution_count ? 'Out[' + out.execution_count + ']:' : '';
        list.appendChild(outputFrame(prompt, richOutput(out, ctx, outKey), out.output_type === 'execute_result' ? 'nbc-result' : 'nbc-display'));
      }
    });
    return list;
  }

  /** Outputs as the log carries them: text, and summaries of rich ones. */
  function logOutputs(cell, ctx, key, notebookState) {
    var list = el('div', 'nbc-outputs');
    cell.outputs.forEach(function(out, position) {
      var outKey = key + ':log:' + position;
      if (out.kind === 'stream') {
        list.appendChild(outputFrame('', preview(out.text, OUTPUT_PREVIEW_LINES, ctx, outKey, 'nbc-text' + (out.name === 'stderr' ? ' nbc-stderr' : '')), 'nbc-stream'));
      } else if (out.kind === 'error') {
        var body = el('div', 'nbc-error-body');
        body.appendChild(el('div', 'nbc-error-title', out.ename + (out.evalue ? ': ' + out.evalue : '')));
        if (out.text.length) {
          body.appendChild(preview(out.text, OUTPUT_PREVIEW_LINES, ctx, outKey, 'nbc-text nbc-traceback'));
        }
        list.appendChild(outputFrame('', body, 'nbc-error'));
      } else {
        var body2 = el('div', 'nbc-rich');
        out.rich.forEach(function(part) {
          var chip = el('span', 'nbc-placeholder');
          chip.appendChild(icon(/^image\//.test(part.mime) ? 'fa-picture-o' : (/html/.test(part.mime) ? 'fa-table' : 'fa-file-code-o')));
          chip.appendChild(document.createTextNode(' ' + part.mime + (part.size ? ' · ' + part.size : '') + ' — ' + (notebookState === 'loading' ? 'loading...' : 'shown when the executed notebook is published')));
          body2.appendChild(chip);
        });
        if (out.text.length) {
          body2.appendChild(preview(out.text, OUTPUT_PREVIEW_LINES, ctx, outKey, 'nbc-text'));
        }
        list.appendChild(outputFrame(out.label ? out.label + ':' : '', body2, out.kind === 'result' ? 'nbc-result' : 'nbc-display'));
      }
    });
    return list;
  }

  function cellId(ctx, index) {
    return (ctx.idPrefix || 'nbc') + '-cell-' + index;
  }

  function renderCell(cell, model, ctx, notebook, notebookState, executionNumbers) {
    var nbCell = notebook && notebook.cells ? notebook.cells[cell.index - 1] : null;
    var key = 'cell-' + cell.index;
    var node = el('div', 'nbc-cell nbc-' + cell.type + ' is-' + cell.state);
    node.id = cellId(ctx, cell.index);
    if (cell.tags.indexOf('injected-parameters') !== -1) {
      node.classList.add('is-injected');
    }

    if (cell.type === 'markdown') {
      var markdown = el('div', 'nbc-markdown');
      markdown.innerHTML = renderMarkdown(cell.source.join('\n'));
      node.appendChild(el('div', 'nbc-prompt'));
      node.appendChild(markdown);
      return node;
    }
    if (cell.type !== 'code') {
      node.appendChild(el('div', 'nbc-prompt'));
      node.appendChild(preview(cell.source, SOURCE_PREVIEW_LINES, ctx, key, 'nbc-raw'));
      return node;
    }

    var count = nbCell && nbCell.execution_count ? nbCell.execution_count : executionNumbers[cell.index];
    var prompt = el('div', 'nbc-prompt nbc-prompt-in', cell.state === 'running' ? 'In [*]:' : 'In [' + (count || ' ') + ']:');
    var body = el('div', 'nbc-body');
    var meta = el('div', 'nbc-cell-meta');
    var status = el('span', 'nbc-status nbc-status-' + cell.state);
    status.appendChild(icon(STATE_ICON[cell.state] || 'fa-circle-thin'));
    status.appendChild(document.createTextNode(' ' + (STATE_LABEL[cell.state] || cell.state)));
    meta.appendChild(status);
    cell.tags.forEach(function(tag) {
      meta.appendChild(el('span', 'nbc-tag', tag));
    });
    if (cell.duration) {
      meta.appendChild(el('span', 'nbc-duration', cell.duration));
    }
    if (cell.note) {
      meta.appendChild(el('span', 'nbc-note', cell.note));
    }
    meta.appendChild(el('span', 'nbc-cell-number', 'cell ' + cell.index + '/' + (cell.total || model.cellCount)));
    body.appendChild(meta);

    var expanded = !! (ctx.state.expanded && ctx.state.expanded[key]);
    var sourceLines = expanded || cell.source.length <= SOURCE_PREVIEW_LINES + 4 ? cell.source : cell.source.slice(0, SOURCE_PREVIEW_LINES);
    var source = el('pre', 'nbc-source');
    source.innerHTML = highlightPython(sourceLines.join('\n')) || '&nbsp;';
    body.appendChild(source);
    if (sourceLines.length < cell.source.length) {
      var more = el('button', 'nbc-more', 'Show ' + (cell.source.length - sourceLines.length) + ' more lines of code');
      more.type = 'button';
      more.addEventListener('click', function() {
        ctx.state.expanded = ctx.state.expanded || {};
        ctx.state.expanded[key] = true;
        if (ctx.rerender) {
          ctx.rerender();
        }
      });
      body.appendChild(more);
    }

    if (nbCell && nbCell.cell_type === 'code' && (nbCell.outputs || []).length) {
      body.appendChild(notebookOutputs(nbCell, ctx, key));
    } else if (cell.outputs.length) {
      body.appendChild(logOutputs(cell, ctx, key, notebookState));
    }
    node.appendChild(prompt);
    node.appendChild(body);
    return node;
  }

  function scrollToCell(ctx, index) {
    if (typeof document === 'undefined') {
      return;
    }
    var target = document.getElementById(cellId(ctx, index));
    if (target) {
      target.scrollIntoView({behavior: 'smooth', block: 'center'});
      target.classList.remove('is-flash');
      void target.offsetWidth;
      target.classList.add('is-flash');
    }
  }

  function renderTimeline(model, ctx) {
    var code = model.cells.filter(function(cell) { return cell.type === 'code'; });
    var expected = Math.max(model.codeCount, code.filter(function(cell) { return cell.source.join('').trim() !== ''; }).length);
    var strip = el('div', 'nbc-timeline');
    strip.setAttribute('role', 'list');
    var weights = code.map(function(cell) { return Math.max(seconds(cell.duration), 0.05); });
    var total = weights.reduce(function(sum, value) { return sum + value; }, 0) || 1;
    code.forEach(function(cell, position) {
      var segment = el('button', 'nbc-segment is-' + cell.state);
      segment.type = 'button';
      segment.setAttribute('role', 'listitem');
      segment.style.flexGrow = String(Math.max(weights[position] / total * 100, 2));
      segment.title = 'Cell ' + cell.index + ': ' + (STATE_LABEL[cell.state] || cell.state) + (cell.duration ? ' in ' + cell.duration : '') + (cell.note ? ' (' + cell.note + ')' : '');
      segment.addEventListener('click', function() { scrollToCell(ctx, cell.index); });
      strip.appendChild(segment);
    });
    for (var missing = code.length; missing < expected; missing++) {
      var pending = el('span', 'nbc-segment is-pending');
      pending.style.flexGrow = '2';
      strip.appendChild(pending);
    }
    return strip;
  }

  function renderHeader(model, ctx, notebookEntryState) {
    var head = el('div', 'nbc-head');
    var title = el('div', 'nbc-title');
    var badge = el('span', 'nbc-logo');
    badge.appendChild(icon('fa-book'));
    title.appendChild(badge);
    var name = el('div', 'nbc-name');
    name.appendChild(el('strong', '', model.path ? model.path.split('/').pop() : 'Notebook'));
    var facts = [];
    if (model.kernel) {
      facts.push('kernel ' + model.kernel);
    }
    if (model.codeCount) {
      facts.push(model.codeCount + ' code cell' + (model.codeCount === 1 ? '' : 's'));
    }
    if (model.path && model.path.indexOf('/') !== -1) {
      facts.push(model.path);
    }
    name.appendChild(el('span', '', facts.join(' · ')));
    title.appendChild(name);
    head.appendChild(title);

    var done = model.cells.filter(function(cell) { return cell.type === 'code' && (cell.state === 'ok' || cell.state === 'error'); }).length;
    var failed = model.cells.filter(function(cell) { return cell.state === 'error'; })[0];
    var running = model.cells.filter(function(cell) { return cell.state === 'running'; })[0];
    var status = model.finish ? model.finish.status : (ctx.live ? 'RUNNING' : (model.started ? 'STOPPED' : 'STARTING'));
    var chip = el('span', 'nbc-run-status is-' + status.toLowerCase());
    chip.appendChild(icon(status === 'SUCCESS' ? 'fa-check-circle' : (status === 'FAILURE' ? 'fa-times-circle' : (status === 'RUNNING' || status === 'STARTING' ? 'fa-circle-o-notch fa-spin' : 'fa-stop-circle'))));
    var summary = status === 'RUNNING' && running ? 'Running cell ' + running.index + ' of ' + (running.total || model.cellCount)
      : (status === 'RUNNING' && ! model.cells.length ? 'Starting the kernel' : status.charAt(0) + status.slice(1).toLowerCase());
    var progress = model.started && (model.codeCount || done) ? ' · ' + done + '/' + (model.codeCount || done) + ' cells' : '';
    chip.appendChild(document.createTextNode(' ' + summary + progress + (model.finish && model.finish.elapsed ? ' · ' + model.finish.elapsed : '')));
    head.appendChild(chip);

    var actions = el('div', 'nbc-actions');
    if (failed) {
      var jump = el('button', 'btn btn-xs nbc-action nbc-action-danger');
      jump.type = 'button';
      jump.appendChild(icon('fa-crosshairs'));
      jump.appendChild(document.createTextNode(' Failed cell ' + failed.index));
      jump.addEventListener('click', function() { scrollToCell(ctx, failed.index); });
      actions.appendChild(jump);
    }
    if (model.saved) {
      var download = el('a', 'btn btn-xs nbc-action');
      download.href = baseUrl() + 'jobView/notebookRun?download=1&path=' + encodeURIComponent(model.saved);
      download.setAttribute('download', '');
      download.title = notebookEntryState === 'loaded' ? 'The executed notebook, with every output' : 'Published when the run finishes';
      download.appendChild(icon('fa-download'));
      download.appendChild(document.createTextNode(' .ipynb'));
      actions.appendChild(download);
    }
    var log = el('button', 'btn btn-xs nbc-action');
    log.type = 'button';
    log.setAttribute('data-notebook-view', 'log');
    log.appendChild(icon('fa-file-text-o'));
    log.appendChild(document.createTextNode(' Log'));
    log.addEventListener('click', function() {
      ctx.state.view = 'log';
      if (ctx.rerender) {
        ctx.rerender();
      }
    });
    actions.appendChild(log);
    head.appendChild(actions);
    return head;
  }

  function renderParameters(model) {
    var parameters = model.parameters || {};
    var names = Object.keys(parameters);
    if (! names.length) {
      return null;
    }
    var row = el('div', 'nbc-parameters');
    row.appendChild(el('span', 'nbc-parameters-label', 'Parameters'));
    names.forEach(function(name) {
      var item = parameters[name] || {};
      var chip = el('span', 'nbc-parameter');
      chip.appendChild(el('code', '', name));
      chip.appendChild(document.createTextNode(' = '));
      chip.appendChild(el('code', 'nbc-parameter-value', JSON.stringify(item.value)));
      var origin = String(item.from || 'value');
      if (origin !== 'value') {
        chip.appendChild(el('span', 'nbc-parameter-from is-' + origin.split(' ')[0].replace(/[^a-z]/g, ''), origin));
      }
      row.appendChild(chip);
    });
    return row;
  }

  /**
   * The notebook view of a parsed run. ctx: {state, live, rerender,
   * idPrefix}; state persists between renders of the same console.
   */
  function render(model, ctx) {
    ctx = ctx || {};
    ctx.state = ctx.state || {};
    var finished = !! model.finish || ! ctx.live;
    var progress = model.cells.filter(function(cell) { return cell.closed; }).length;
    var entry = model.saved ? loadNotebook(ctx, model.saved, finished, progress) : null;
    var notebook = entry && entry.status === 'loaded' ? entry.notebook : null;
    var view = el('div', 'nbc' + (ctx.live && ! model.finish ? ' is-live' : ''));

    view.appendChild(renderHeader(model, ctx, entry ? entry.status : ''));
    view.appendChild(renderTimeline(model, ctx));
    var parameters = renderParameters(model);
    if (parameters) {
      view.appendChild(parameters);
    }
    if (model.notesBefore.length) {
      view.appendChild(preview(model.notesBefore, 12, ctx, 'notes-before', 'nbc-notes'));
    }
    var executionNumbers = {};
    var count = 0;
    model.cells.forEach(function(cell) {
      if (cell.type === 'code' && cell.ran) {
        count += 1;
        executionNumbers[cell.index] = count;
      }
    });
    var cells = el('div', 'nbc-cells');
    model.cells.forEach(function(cell) {
      cells.appendChild(renderCell(cell, model, ctx, notebook, entry ? entry.status : '', executionNumbers));
    });
    if (! model.cells.length) {
      cells.appendChild(el('div', 'nbc-empty', model.started ? 'Starting the kernel...' : 'Preparing the notebook...'));
    }
    view.appendChild(cells);
    if (model.notesAfter.length) {
      view.appendChild(preview(model.notesAfter, 20, ctx, 'notes-after', 'nbc-notes' + (model.finish && model.finish.status !== 'SUCCESS' ? ' is-failure' : '')));
    }
    return view;
  }

  return {
    ansiToHtml: ansiToHtml,
    highlightPython: highlightPython,
    parse: parse,
    render: render,
    renderMarkdown: renderMarkdown
  };
}));
