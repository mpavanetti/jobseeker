/*
 * A small VS Code-style code editor: file tabs, line numbers, syntax
 * highlighting and a status bar over a plain textarea, so forms keep
 * working without it. Used by the Runtimes page for Dockerfiles,
 * devcontainer.json, requirements.txt and environment.yml.
 *
 *   var editor = new JobSeekerCodeEditor(element, {
 *     files: [{id: 'Dockerfile', name: 'Dockerfile', language: 'dockerfile', value: '', readOnly: false}],
 *     onChange: function(id, value) {}
 *   });
 *   editor.setFiles([...]); editor.setValue(id, text); editor.getValue(id); editor.activate(id);
 */
(function(window) {
  'use strict';

  function escapeHtml(value) {
    return String(value).replace(/[&<>"]/g, function(character) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[character];
    });
  }

  // Each language is a list of [class, regex] tried in order at every position.
  var dockerInstructions = 'FROM|RUN|CMD|LABEL|MAINTAINER|EXPOSE|ENV|ADD|COPY|ENTRYPOINT|VOLUME|USER|WORKDIR|ARG|ONBUILD|STOPSIGNAL|HEALTHCHECK|SHELL';
  var languages = {
    dockerfile: [
      ['comment', /#.*/y],
      // Instructions only open a line; `npm run build` is not RUN.
      ['keyword', new RegExp('^\\s*(?:' + dockerInstructions + ')\\b', 'yi')],
      ['keyword', /\bAS\b/y],
      ['string', /"(?:[^"\\]|\\.)*"?|'[^']*'?/y],
      ['variable', /\$\{[^}]*\}|\$[A-Za-z_][A-Za-z0-9_]*/y],
      ['attribute', /--[A-Za-z][A-Za-z0-9-]*(?==?)/y],
      ['number', /\b\d+(?:\.\d+)*\b/y],
      ['operator', /&&|\|\||\\$|[|;]/y]
    ],
    json: [
      ['comment', /\/\/.*|\/\*.*?(?:\*\/|$)/y],
      ['key', /"(?:[^"\\]|\\.)*"(?=\s*:)/y],
      ['string', /"(?:[^"\\]|\\.)*"?/y],
      ['number', /-?\b\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b/y],
      ['keyword', /\b(?:true|false|null)\b/y]
    ],
    yaml: [
      ['comment', /#.*/y],
      ['key', /[A-Za-z0-9_.\-/"' ]+?(?=:(?:\s|$))/y],
      ['operator', /^\s*-\s/y],
      ['string', /"(?:[^"\\]|\\.)*"?|'[^']*'?/y],
      ['number', /\b\d+(?:\.\d+)*\b/y],
      ['keyword', /\b(?:true|false|null|yes|no)\b/y]
    ],
    requirements: [
      ['comment', /#.*/y],
      ['attribute', /--?[A-Za-z][A-Za-z0-9-]*/y],
      ['string', /https?:\/\/\S+/y],
      ['type', /^[A-Za-z0-9][A-Za-z0-9._-]*(?:\[[^\]]*\])?/y],
      ['operator', /[<>=!~]=?|,|;/y],
      ['number', /\b\d+(?:\.\d+)*(?:[a-z]+\d*)?\b/y]
    ],
    shell: [
      ['comment', /#.*/y],
      ['string', /"(?:[^"\\]|\\.)*"?|'[^']*'?/y],
      ['variable', /\$\{[^}]*\}|\$[A-Za-z_][A-Za-z0-9_]*/y],
      ['keyword', /\b(?:if|then|else|fi|for|do|done|case|esac|in|export|set)\b/y],
      ['operator', /&&|\|\||[|;]/y]
    ],
    markdown: [
      ['keyword', /^#{1,6} .*/y],
      ['string', /`[^`]*`?/y],
      ['operator', /^\s*[-*] /y]
    ],
    text: []
  };

  function highlightLine(line, rules) {
    var html = '';
    var position = 0;
    var plain = '';
    while (position < line.length) {
      var matched = false;
      for (var index = 0; index < rules.length; index++) {
        var rule = rules[index][1];
        // ^ in a rule only matches at the start of the line.
        if (rule.source.charAt(0) === '^' && position !== 0) {
          continue;
        }
        rule.lastIndex = position;
        var match = rule.exec(line);
        if (match && match[0].length) {
          if (plain) {
            html += escapeHtml(plain);
            plain = '';
          }
          html += '<span class="tok-' + rules[index][0] + '">' + escapeHtml(match[0]) + '</span>';
          position += match[0].length;
          matched = true;
          break;
        }
      }
      if (!matched) {
        plain += line.charAt(position);
        position++;
      }
    }
    return html + escapeHtml(plain);
  }

  function highlight(text, language) {
    var rules = languages[language] || languages.text;
    return String(text).split('\n').map(function(line) { return highlightLine(line, rules); }).join('\n');
  }

  var icons = {dockerfile: 'fa-cube', json: 'fa-code', yaml: 'fa-list-alt', requirements: 'fa-list', shell: 'fa-terminal', markdown: 'fa-file-text-o', text: 'fa-file-o'};
  var labels = {dockerfile: 'Dockerfile', json: 'JSON with Comments', yaml: 'YAML', requirements: 'pip requirements', shell: 'Shell Script', markdown: 'Markdown', text: 'Plain Text'};

  function JobSeekerCodeEditor(element, options) {
    this.element = element;
    this.options = options || {};
    this.files = [];
    this.active = null;
    this.build();
    this.setFiles(this.options.files || []);
  }

  JobSeekerCodeEditor.prototype.build = function() {
    var self = this;
    this.element.classList.add('js-code-editor');
    this.element.innerHTML = '<div class="js-code-tabs" role="tablist"></div>'
      + '<div class="js-code-body"><div class="js-code-gutter" aria-hidden="true"></div>'
      + '<div class="js-code-area"><pre class="js-code-highlight" aria-hidden="true"></pre>'
      + '<textarea class="js-code-input" spellcheck="false" wrap="off" autocapitalize="off" autocomplete="off"></textarea></div></div>'
      + '<div class="js-code-status"><span class="js-code-status-file"></span><span class="js-code-status-right">'
      + '<span class="js-code-status-position">Ln 1, Col 1</span><span>Spaces: 2</span><span>UTF-8</span><span>LF</span><span class="js-code-status-language"></span></span></div>';
    this.tabs = this.element.querySelector('.js-code-tabs');
    this.gutter = this.element.querySelector('.js-code-gutter');
    this.highlightElement = this.element.querySelector('.js-code-highlight');
    this.input = this.element.querySelector('.js-code-input');
    this.tabs.addEventListener('click', function(event) {
      var tab = event.target.closest('[data-file]');
      if (tab) {
        self.activate(tab.getAttribute('data-file'));
      }
    });
    this.input.addEventListener('input', function() {
      var file = self.file(self.active);
      if (file && !file.readOnly) {
        file.value = self.input.value;
        self.render();
        if (self.options.onChange) {
          self.options.onChange(file.id, file.value);
        }
      }
    });
    this.input.addEventListener('scroll', function() { self.syncScroll(); });
    ['keyup', 'click', 'select', 'focus'].forEach(function(name) {
      self.input.addEventListener(name, function() { self.updateStatus(); });
    });
    this.input.addEventListener('keydown', function(event) {
      var file = self.file(self.active);
      if (event.key === 'Tab' && !event.shiftKey && file && !file.readOnly) {
        event.preventDefault();
        var start = self.input.selectionStart;
        self.input.setRangeText('  ', start, self.input.selectionEnd, 'end');
        self.input.dispatchEvent(new Event('input'));
      }
    });
  };

  JobSeekerCodeEditor.prototype.file = function(id) {
    for (var index = 0; index < this.files.length; index++) {
      if (this.files[index].id === id) {
        return this.files[index];
      }
    }
    return null;
  };

  JobSeekerCodeEditor.prototype.setFiles = function(files) {
    var previous = this.active;
    this.files = files.map(function(file) {
      return {id: file.id, name: file.name || file.id, language: file.language || 'text', value: file.value || '', readOnly: !!file.readOnly, note: file.note || ''};
    });
    this.renderTabs();
    this.activate(this.file(previous) ? previous : (this.files[0] ? this.files[0].id : null));
  };

  JobSeekerCodeEditor.prototype.setValue = function(id, value) {
    var file = this.file(id);
    if (!file) {
      return;
    }
    file.value = value || '';
    if (id === this.active && this.input.value !== file.value) {
      var start = this.input.selectionStart;
      this.input.value = file.value;
      this.input.selectionStart = this.input.selectionEnd = Math.min(start, file.value.length);
    }
    this.render();
  };

  JobSeekerCodeEditor.prototype.getValue = function(id) {
    var file = this.file(id);
    return file ? file.value : '';
  };

  JobSeekerCodeEditor.prototype.renderTabs = function() {
    var active = this.active;
    this.tabs.innerHTML = this.files.map(function(file) {
      return '<button type="button" role="tab" class="js-code-tab' + (file.id === active ? ' is-active' : '') + '" data-file="' + escapeHtml(file.id) + '" aria-selected="' + (file.id === active) + '">'
        + '<i class="fa ' + (icons[file.language] || icons.text) + ' js-code-tab-icon is-' + escapeHtml(file.language) + '"></i>' + escapeHtml(file.name)
        + (file.readOnly ? '<span class="js-code-tab-flag" title="Generated from the form; read-only">generated</span>' : '') + '</button>';
    }).join('');
  };

  JobSeekerCodeEditor.prototype.activate = function(id) {
    var file = this.file(id);
    this.active = file ? file.id : null;
    this.renderTabs();
    this.input.value = file ? file.value : '';
    this.input.readOnly = !file || file.readOnly;
    this.element.classList.toggle('is-read-only', !file || file.readOnly);
    this.input.scrollTop = 0;
    this.input.scrollLeft = 0;
    this.element.querySelector('.js-code-status-file').textContent = file ? (file.readOnly ? 'Read-only · ' : '') + (file.note || file.name) : '';
    this.element.querySelector('.js-code-status-language').textContent = file ? (labels[file.language] || labels.text) : '';
    this.render();
    this.updateStatus();
  };

  JobSeekerCodeEditor.prototype.render = function() {
    var file = this.file(this.active);
    var text = file ? file.value : '';
    // A trailing space keeps the last empty line as tall as the textarea's.
    this.highlightElement.innerHTML = highlight(text, file ? file.language : 'text') + ' ';
    var lines = text.split('\n').length;
    var numbers = [];
    for (var line = 1; line <= lines; line++) {
      numbers.push('<span>' + line + '</span>');
    }
    this.gutter.innerHTML = numbers.join('');
    this.syncScroll();
    this.updateStatus();
  };

  JobSeekerCodeEditor.prototype.syncScroll = function() {
    this.highlightElement.style.transform = 'translate(' + (-this.input.scrollLeft) + 'px,' + (-this.input.scrollTop) + 'px)';
    this.gutter.style.transform = 'translateY(' + (-this.input.scrollTop) + 'px)';
  };

  JobSeekerCodeEditor.prototype.updateStatus = function() {
    var before = this.input.value.slice(0, this.input.selectionStart);
    var line = before.split('\n').length;
    var column = before.length - before.lastIndexOf('\n');
    this.element.querySelector('.js-code-status-position').textContent = 'Ln ' + line + ', Col ' + column;
    var current = this.gutter.querySelector('.is-current');
    if (current) {
      current.classList.remove('is-current');
    }
    var number = this.gutter.children[line - 1];
    if (number) {
      number.classList.add('is-current');
    }
  };

  JobSeekerCodeEditor.highlight = highlight;
  window.JobSeekerCodeEditor = JobSeekerCodeEditor;
})(window);
