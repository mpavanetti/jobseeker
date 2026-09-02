/**
 * JobSeekerTime - render UTC timestamps in the viewer's local time or in UTC.
 *
 * The application stores and serves every timestamp in UTC. This module is the
 * single place the browser decides how to show them: a per-browser toggle
 * ("Local" / "UTC") persisted in localStorage. It has no dependencies - it uses
 * the platform Intl APIs.
 *
 * Global config (optional), set before this script loads:
 *   window.jobseekerTime = { serverTimezone: 'UTC', displayTimezone: 'UTC' }
 *
 * API (window.JobSeekerTime):
 *   mode()                       -> 'local' | 'utc'
 *   setMode('local' | 'utc')
 *   onChange(fn)                 -> fn(mode) whenever the mode changes
 *   format(value [, overrides])  -> "Sep 1, 2026, 3:20 AM" (+ " UTC" in utc mode)
 *   formatDate(value)            -> "Sep 1, 2026"
 *   fromNow(value)               -> "3 minutes ago"
 *   apply([root])                -> rewrite <time datetime> / [data-utc] nodes in root
 *   renderToggle(target)         -> mount the Local/UTC switch into an element
 *
 * A value may be a Date, an epoch-millis number, an ISO 8601 string, or a bare
 * "YYYY-MM-DD HH:MM:SS[.ffffff]" string (treated as UTC, since that is how the
 * database returns it).
 */
(function (w) {
  'use strict';

  var STORAGE_KEY = 'jobseeker.time.mode';
  var cfg = w.jobseekerTime || {};
  var listeners = [];

  function readStored() {
    try {
      var v = w.localStorage.getItem(STORAGE_KEY);
      return v === 'utc' || v === 'local' ? v : null;
    } catch (e) {
      return null;
    }
  }

  function defaultMode() {
    var display = String(cfg.displayTimezone || 'UTC').toUpperCase();
    return display === 'UTC' || display === 'ETC/UTC' ? 'utc' : 'local';
  }

  var mode = readStored() || defaultMode();

  function getMode() {
    return mode;
  }

  function setMode(next) {
    next = next === 'utc' ? 'utc' : 'local';
    if (next === mode) {
      return;
    }
    mode = next;
    try {
      w.localStorage.setItem(STORAGE_KEY, mode);
    } catch (e) {
      /* private mode / storage disabled - the choice just will not persist */
    }
    apply(w.document);
    for (var i = 0; i < listeners.length; i++) {
      try {
        listeners[i](mode);
      } catch (e) {
        /* a bad listener must not break the others */
      }
    }
  }

  function onChange(fn) {
    if (typeof fn === 'function') {
      listeners.push(fn);
    }
  }

  function toDate(value) {
    if (value instanceof Date) {
      return isNaN(value.getTime()) ? null : value;
    }
    if (typeof value === 'number') {
      var n = new Date(value);
      return isNaN(n.getTime()) ? null : n;
    }
    var s = String(value == null ? '' : value).trim();
    if (s === '') {
      return null;
    }
    // Bare "YYYY-MM-DD HH:MM:SS(.ffffff)" with no zone -> it is UTC (DB output).
    if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?/.test(s) && !/([zZ]|[+-]\d{2}:?\d{2})$/.test(s)) {
      s = s.replace(' ', 'T').replace(/\.\d+$/, '') + 'Z';
    }
    var d = new Date(s);
    return isNaN(d.getTime()) ? null : d;
  }

  var BASE_OPTS = { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };

  function buildOpts(overrides) {
    var o = {};
    var k;
    for (k in BASE_OPTS) {
      if (BASE_OPTS.hasOwnProperty(k)) {
        o[k] = BASE_OPTS[k];
      }
    }
    if (overrides) {
      for (k in overrides) {
        if (overrides.hasOwnProperty(k)) {
          if (overrides[k] === undefined) {
            delete o[k];
          } else {
            o[k] = overrides[k];
          }
        }
      }
    }
    if (mode === 'utc') {
      o.timeZone = 'UTC';
    }
    return o;
  }

  function format(value, overrides) {
    var d = toDate(value);
    if (!d) {
      return value == null ? '' : String(value);
    }
    try {
      var text = new Intl.DateTimeFormat(undefined, buildOpts(overrides)).format(d);
      return mode === 'utc' ? text + ' UTC' : text;
    } catch (e) {
      return d.toISOString();
    }
  }

  function formatDate(value) {
    return format(value, { hour: undefined, minute: undefined });
  }

  var REL = null;
  try {
    REL = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
  } catch (e) {
    REL = null;
  }
  var REL_UNITS = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
    ['second', 1]
  ];

  function fromNow(value) {
    var d = toDate(value);
    if (!d) {
      return value == null ? '' : String(value);
    }
    var diffSeconds = (d.getTime() - Date.now()) / 1000;
    var abs = Math.abs(diffSeconds);
    for (var i = 0; i < REL_UNITS.length; i++) {
      var unit = REL_UNITS[i][0];
      var size = REL_UNITS[i][1];
      if (abs >= size || unit === 'second') {
        var amount = Math.round(diffSeconds / size);
        if (REL) {
          return REL.format(amount, unit);
        }
        var plural = Math.abs(amount) === 1 ? '' : 's';
        return amount <= 0
          ? Math.abs(amount) + ' ' + unit + plural + ' ago'
          : 'in ' + amount + ' ' + unit + plural;
      }
    }
    return format(value);
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Build a <time> element (as an HTML string) that apply() will keep localized.
  // Use this from JS that injects timestamps into innerHTML so they follow the
  // Local/UTC toggle without the view having to re-render.
  function tag(value, opts) {
    opts = opts || {};
    var d = toDate(value);
    if (!d) {
      return esc(opts.empty != null ? opts.empty : (value == null ? '' : String(value)));
    }
    var iso = d.toISOString();
    var attrs = ' datetime="' + esc(iso) + '"';
    if (opts.relative) {
      attrs += ' data-time-relative';
    }
    if (opts.dateOnly) {
      attrs += ' data-time-date-only';
    }
    var text = opts.relative ? fromNow(iso) : (opts.dateOnly ? formatDate(iso) : format(iso));
    return '<time' + attrs + '>' + esc(text) + '</time>';
  }

  function apply(root) {
    var scope = root || w.document;
    if (!scope || !scope.querySelectorAll) {
      return;
    }
    var nodes = scope.querySelectorAll('time[datetime], [data-utc]');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      var iso = el.getAttribute('datetime') || el.getAttribute('data-utc');
      if (!iso) {
        continue;
      }
      var relative = el.hasAttribute('data-time-relative');
      var dateOnly = el.hasAttribute('data-time-date-only');
      el.textContent = relative ? fromNow(iso) : (dateOnly ? formatDate(iso) : format(iso));
      el.setAttribute('title', format(iso));
    }
  }

  function renderToggle(target) {
    var host = typeof target === 'string' ? w.document.querySelector(target) : target;
    if (!host) {
      return;
    }
    host.classList.add('jt-toggle-host');
    host.innerHTML =
      '<span class="jt-toggle" role="group" aria-label="Timestamp timezone">' +
      '<button type="button" class="jt-btn" data-jt-mode="local">Local</button>' +
      '<button type="button" class="jt-btn" data-jt-mode="utc">UTC</button>' +
      '</span>';

    function paint() {
      var btns = host.querySelectorAll('[data-jt-mode]');
      for (var i = 0; i < btns.length; i++) {
        var active = btns[i].getAttribute('data-jt-mode') === mode;
        btns[i].classList.toggle('is-active', active);
        btns[i].setAttribute('aria-pressed', active ? 'true' : 'false');
      }
    }

    host.addEventListener('click', function (event) {
      var btn = event.target;
      while (btn && btn !== host && !btn.getAttribute('data-jt-mode')) {
        btn = btn.parentNode;
      }
      if (btn && btn.getAttribute) {
        setMode(btn.getAttribute('data-jt-mode'));
      }
    });

    onChange(paint);
    paint();
  }

  w.JobSeekerTime = {
    mode: getMode,
    setMode: setMode,
    onChange: onChange,
    format: format,
    formatDate: formatDate,
    fromNow: fromNow,
    tag: tag,
    apply: apply,
    renderToggle: renderToggle
  };

  if (w.document) {
    if (w.document.readyState === 'loading') {
      w.document.addEventListener('DOMContentLoaded', function () {
        apply(w.document);
      });
    } else {
      apply(w.document);
    }
  }
})(window);
