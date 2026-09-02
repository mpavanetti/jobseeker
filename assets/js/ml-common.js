/* JobSeeker ML platform - shared browser helpers.
 *
 * CSRF is added to every non-GET request by the global $.ajaxPrefilter in
 * includes/header.php, so these wrappers stay plain. Charts are hand-rolled
 * inline SVG (no plotting library) to keep the ML screens dependency-free. */
(function (window, $) {
  'use strict';

  var ML = window.MlCommon || {};

  ML.base = window.baseURL || '/';

  ML.get = function (url) {
    return $.ajax({ url: url, method: 'GET', dataType: 'json', cache: false });
  };

  ML.post = function (url, data) {
    return $.ajax({ url: url, method: 'POST', dataType: 'json', data: data || {} });
  };

  ML.postForm = function (url, formData) {
    return $.ajax({
      url: url, method: 'POST', dataType: 'json', data: formData,
      processData: false, contentType: false
    });
  };

  ML.toast = function (kind, message, title) {
    if (window.toastr) {
      // ML screens have busy right-hand panels; keep toasts out of the form.
      var prev = window.toastr.options && window.toastr.options.positionClass;
      window.toastr.options = window.toastr.options || {};
      window.toastr.options.positionClass = 'toast-bottom-right';
      window.toastr.options.timeOut = 3500;
      (window.toastr[kind] || window.toastr.info)(message, title || '');
      if (prev) { window.toastr.options.positionClass = prev; }
    } else if (window.alertify) {
      (window.alertify[kind === 'error' ? 'error' : 'success'] || window.alertify.message)(message);
    }
  };

  ML.escape = function (value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  };

  ML.fmt = function (value, digits) {
    if (value == null || value === '' || isNaN(value)) { return '—'; }
    var n = Number(value);
    if (Math.abs(n) >= 1000) { return n.toLocaleString(undefined, { maximumFractionDigits: 0 }); }
    return n.toFixed(digits == null ? 4 : digits).replace(/\.?0+$/, '');
  };

  ML.ago = function (iso) {
    if (!iso) { return '—'; }
    var t = Date.parse(iso.replace(' ', 'T') + (/[zZ]|[+-]\d\d:?\d\d$/.test(iso) ? '' : 'Z'));
    if (isNaN(t)) { return ML.escape(iso); }
    var s = Math.max(0, (Date.now() - t) / 1000);
    if (s < 60) { return Math.round(s) + 's ago'; }
    if (s < 3600) { return Math.round(s / 60) + 'm ago'; }
    if (s < 86400) { return Math.round(s / 3600) + 'h ago'; }
    return Math.round(s / 86400) + 'd ago';
  };

  /* Poll `fn` (returns a jqXHR) every intervalMs until `done(response)` is true
     or `max` polls elapse. Returns a stop() function. */
  ML.poll = function (fn, opts) {
    opts = opts || {};
    var interval = opts.interval || 3000;
    var max = opts.max || 400;
    var count = 0;
    var stopped = false;
    function tick() {
      if (stopped) { return; }
      count += 1;
      $.when(fn()).always(function (response) {
        if (stopped) { return; }
        if ((opts.done && opts.done(response)) || count >= max) {
          stopped = true;
          if (opts.finished) { opts.finished(response); }
          return;
        }
        setTimeout(tick, interval);
      });
    }
    tick();
    return function () { stopped = true; };
  };

  /* --- inline SVG line chart -------------------------------------------------
     series: [{ name, points:[{step|x, value|y}], className? }]  */
  ML.lineChart = function (el, series, opts) {
    opts = opts || {};
    var $el = $(el);
    var w = $el.width() || 600;
    var h = $el.height() || 220;
    var pad = { l: 44, r: 12, t: 10, b: 24 };
    series = (series || []).filter(function (s) { return s.points && s.points.length; });
    if (!series.length) {
      $el.html('<div class="ml-muted" style="padding:24px;text-align:center">No data yet.</div>');
      return;
    }
    var xs = [], ys = [];
    series.forEach(function (s) {
      s.points.forEach(function (p) {
        xs.push(p.step != null ? p.step : p.x);
        ys.push(p.value != null ? p.value : p.y);
      });
    });
    var xMin = Math.min.apply(null, xs), xMax = Math.max.apply(null, xs);
    var yMin = opts.yMin != null ? opts.yMin : Math.min.apply(null, ys);
    var yMax = opts.yMax != null ? opts.yMax : Math.max.apply(null, ys);
    if (xMax === xMin) { xMax = xMin + 1; }
    if (yMax === yMin) { yMax = yMin + 1; }
    var sx = function (v) { return pad.l + (v - xMin) / (xMax - xMin) * (w - pad.l - pad.r); };
    var sy = function (v) { return h - pad.b - (v - yMin) / (yMax - yMin) * (h - pad.t - pad.b); };

    var parts = ['<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none">'];
    for (var g = 0; g <= 4; g++) {
      var gy = pad.t + g / 4 * (h - pad.t - pad.b);
      parts.push('<line class="ml-grid" x1="' + pad.l + '" y1="' + gy + '" x2="' + (w - pad.r) + '" y2="' + gy + '"/>');
      parts.push('<text x="4" y="' + (gy + 3) + '">' + ML.fmt(yMax - g / 4 * (yMax - yMin), 3) + '</text>');
    }
    parts.push('<line class="ml-axis" x1="' + pad.l + '" y1="' + (h - pad.b) + '" x2="' + (w - pad.r) + '" y2="' + (h - pad.b) + '"/>');
    series.forEach(function (s, i) {
      var d = s.points.map(function (p, j) {
        var x = sx(p.step != null ? p.step : p.x);
        var y = sy(p.value != null ? p.value : p.y);
        return (j ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1);
      }).join(' ');
      parts.push('<path class="ml-line ml-line-' + (i % 5) + (s.className ? ' ' + s.className : '') + '" d="' + d + '"/>');
    });
    parts.push('</svg>');
    $el.html(parts.join(''));

    if (opts.legend !== false && series.length) {
      var legend = series.map(function (s, i) {
        return '<span style="margin-right:12px;font-size:11px"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:' +
          ['#3c8dbc', '#00a65a', '#f39c12', '#dd4b39', '#605ca8'][i % 5] + ';margin-right:4px"></span>' + ML.escape(s.name) + '</span>';
      }).join('');
      $el.append('<div style="margin-top:6px">' + legend + '</div>');
    }
  };

  ML.histogram = function (el, counts) {
    counts = counts || [];
    var max = Math.max.apply(null, counts.concat([1]));
    $(el).html('<div class="ml-histogram">' + counts.map(function (c) {
      return '<span style="height:' + Math.round((c / max) * 100) + '%" title="' + c + '"></span>';
    }).join('') + '</div>');
  };

  ML.envQuery = function (url) {
    var env = window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.current
      ? window.JobSeekerGlobalEnvironment.current() : null;
    if (!env || env === 'all') { return url; }
    return url + (url.indexOf('?') === -1 ? '?' : '&') + 'environment=' + encodeURIComponent(env);
  };

  window.MlCommon = ML;
})(window, jQuery);
