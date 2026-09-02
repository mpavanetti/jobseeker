/* JobSeeker ML platform - shared UI components (v2).
 * Builds on ml-common.js. Charts use the bundled Chart.js (assets/bower_components)
 * when present; sparklines are inline SVG. */
(function (window, $) {
  'use strict';
  var ML = window.MlCommon || {};
  var UI = window.MlUi || {};

  /* --- console: log view + sticky metric strip -------------------------- */
  UI.console = function (el) {
    var $root = $(el).addClass('ml-console-wrap');
    $root.html(
      '<div class="ml-console-head"><span class="ml-console-phase ml-status">idle</span>' +
      '<span class="ml-console-metrics"></span></div>' +
      '<pre class="ml-console js-log"></pre>'
    );
    var $log = $root.find('.js-log');
    var $phase = $root.find('.ml-console-phase');
    var $metrics = $root.find('.ml-console-metrics');
    var series = {};
    return {
      setStatus: function (status) {
        $phase.attr('class', 'ml-console-phase ml-status ' + status).text(status);
      },
      setLog: function (text) {
        $log.text(text || '');
        $log.scrollTop($log[0].scrollHeight);
      },
      appendLog: function (text) {
        $log.append(document.createTextNode(text));
        $log.scrollTop($log[0].scrollHeight);
      },
      setMetrics: function (map) {
        Object.keys(map || {}).forEach(function (k) {
          (series[k] = series[k] || []).push(Number(map[k]));
          if (series[k].length > 40) { series[k].shift(); }
        });
        $metrics.html(Object.keys(series).map(function (k) {
          return '<span class="ml-metric-chip"><b>' + ML.escape(k) + '</b> ' +
            ML.fmt(series[k][series[k].length - 1]) +
            ' <span class="ml-metric-spark" data-k="' + ML.escape(k) + '"></span></span>';
        }).join(''));
        $metrics.find('.ml-metric-spark').each(function () {
          UI.sparkline(this, series[$(this).data('k')]);
        });
      },
      clear: function () { series = {}; $log.text(''); $metrics.empty(); }
    };
  };

  /* --- inline SVG sparkline -------------------------------------------------- */
  UI.sparkline = function (el, values, opts) {
    values = (values || []).map(Number).filter(function (v) { return !isNaN(v); });
    var w = (opts && opts.w) || 60, h = (opts && opts.h) || 16;
    if (values.length < 2) { $(el).html(''); return; }
    var min = Math.min.apply(null, values), max = Math.max.apply(null, values);
    if (max === min) { max = min + 1; }
    var d = values.map(function (v, i) {
      var x = (i / (values.length - 1)) * w;
      var y = h - ((v - min) / (max - min)) * h;
      return (i ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1);
    }).join(' ');
    var up = values[values.length - 1] >= values[0];
    $(el).html('<svg width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h + '">' +
      '<path d="' + d + '" fill="none" stroke="' + (up ? '#00a65a' : '#dd4b39') + '" stroke-width="1.5"/></svg>');
  };

  /* --- Chart.js wrapper (line / bar) --------------------------------------- */
  UI.chart = function (canvas, type, labels, datasets, opts) {
    if (!window.Chart) {
      $(canvas).replaceWith('<div class="ml-muted" style="padding:20px">Chart.js not loaded.</div>');
      return null;
    }
    var ctx = (canvas.getContext ? canvas : $(canvas)[0]).getContext('2d');
    return new window.Chart(ctx, {
      type: type,
      data: { labels: labels, datasets: datasets },
      options: $.extend({
        responsive: true, maintainAspectRatio: false,
        legend: { display: datasets.length > 1, position: 'bottom', labels: { boxWidth: 10, fontSize: 10 } },
        scales: {
          xAxes: [{ gridLines: { color: '#eef1f3' }, ticks: { fontSize: 10, maxTicksLimit: 8 } }],
          yAxes: [{ gridLines: { color: '#eef1f3' }, ticks: { fontSize: 10 } }]
        },
        elements: { point: { radius: 0, hitRadius: 6 }, line: { tension: 0.25, borderWidth: 2 } }
      }, opts || {})
    });
  };

  UI.palette = ['#3c8dbc', '#00a65a', '#f39c12', '#dd4b39', '#605ca8', '#39cccc', '#d81b60'];

  /* --- distribution overlay (baseline vs current bars) -------------------- */
  UI.overlayBars = function (el, edges, baseCounts, curCounts, labelsAB) {
    labelsAB = labelsAB || ['baseline', 'current'];
    var n = Math.min(baseCounts.length, curCounts.length);
    var norm = function (a) { var s = a.reduce(function (x, y) { return x + y; }, 0) || 1; return a.map(function (v) { return v / s; }); };
    var b = norm(baseCounts.slice(0, n)), c = norm(curCounts.slice(0, n));
    var max = Math.max.apply(null, b.concat(c, [0.01]));
    var bins = [];
    for (var i = 0; i < n; i++) {
      bins.push('<div class="ml-ovl-bin" title="bin ' + i + '">' +
        '<span class="ml-ovl-a" style="height:' + Math.round((b[i] / max) * 100) + '%"></span>' +
        '<span class="ml-ovl-b" style="height:' + Math.round((c[i] / max) * 100) + '%"></span></div>');
    }
    $(el).html('<div class="ml-ovl">' + bins.join('') + '</div>' +
      '<div class="ml-ovl-legend"><span class="ml-ovl-a"></span>' + ML.escape(labelsAB[0]) +
      ' <span class="ml-ovl-b"></span>' + ML.escape(labelsAB[1]) + '</div>');
  };

  /* --- schema chips ---------------------------------------------------------- */
  UI.schemaChips = function (schema) {
    return (schema || []).map(function (c) {
      return '<span class="ml-chip">' + ML.escape(c.name) + '<small>' + ML.escape(c.type || '?') + '</small></span>';
    }).join('');
  };

  window.MlUi = UI;
})(window, jQuery);
