/**
 * Shared renderer + client for the job connector / dataset dependency map used by
 * Job Creation (live), Job View and Job Execution (stored).
 */
(function(window, $) {
  'use strict';

  function base() {
    return window.baseURL || (typeof baseURL !== 'undefined' ? baseURL : '/');
  }

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  var LIGHT = {
    ok: {cls: 'jd-ok', icon: 'fa-check-circle', label: 'in scope'},
    out_of_scope: {cls: 'jd-warn', icon: 'fa-exclamation-triangle', label: 'not in this environment / scope'},
    inactive: {cls: 'jd-warn', icon: 'fa-exclamation-triangle', label: 'inactive'},
    missing: {cls: 'jd-bad', icon: 'fa-times-circle', label: 'not found in catalog'},
    unknown: {cls: 'jd-muted', icon: 'fa-question-circle', label: 'not resolved'}
  };

  function testedBadge(item) {
    if (!item || !item.status || item.status === 'unknown') {
      return '';
    }
    if (item.status === 'passed') {
      return '<span class="jd-tested jd-ok" title="' + escapeHtml(item.statusMessage || 'Connection test passed') + '"><i class="fa fa-plug"></i> tested</span>';
    }
    if (item.status === 'driver_missing') {
      return '<span class="jd-tested jd-warn" title="' + escapeHtml(item.statusMessage || '') + '"><i class="fa fa-plug"></i> driver missing</span>';
    }
    return '<span class="jd-tested jd-bad" title="' + escapeHtml(item.statusMessage || 'Connection test failed') + '"><i class="fa fa-plug"></i> failed</span>';
  }

  function linkFor(item, environment) {
    var url = base();
    if (item.kind === 'dataset') {
      return url + 'data-assets';
    }
    if (item.refId) {
      return url + 'dbSettings?edit=' + encodeURIComponent(item.refId) + (environment ? '&environment=' + encodeURIComponent(environment) : '');
    }
    return url + 'dbSettings?create=1' + (environment ? '&environment=' + encodeURIComponent(environment) : '');
  }

  function renderChip(item, environment) {
    var light = LIGHT[item.lightStatus] || LIGHT.unknown;
    var kindIcon = item.kind === 'dataset' ? 'fa-table' : 'fa-plug';
    var meta = item.kind === 'dataset'
      ? (item.type ? escapeHtml(item.type) : 'data asset')
      : (item.type ? escapeHtml(item.type) : 'connector');
    return '' +
      '<span class="jd-chip ' + light.cls + '" title="' + escapeHtml(light.label) + '">' +
        '<i class="fa ' + kindIcon + '"></i>' +
        '<a href="' + escapeHtml(linkFor(item, environment)) + '" target="_blank" rel="noopener">' + escapeHtml(item.key) + '</a>' +
        '<small>' + meta + '</small>' +
        '<i class="fa ' + light.icon + ' jd-light-icon"></i>' +
        testedBadge(item) +
      '</span>';
  }

  function render(container, data, options) {
    options = options || {};
    var $box = $(container);
    if (!$box.length) {
      return;
    }
    var connectors = (data && data.connectors) || [];
    var datasets = (data && data.datasets) || [];
    var environment = options.environment || (data && data.environment) || '';

    if (!connectors.length && !datasets.length) {
      $box.html('<p class="jd-empty text-muted">No connectors or datasets are referenced in this job’s code.</p>');
      return;
    }

    var html = '';
    if (connectors.length) {
      html += '<div class="jd-group"><span class="jd-group-label">Connectors</span><div class="jd-chips">' +
        connectors.map(function(item) { return renderChip(item, environment); }).join('') + '</div></div>';
    }
    if (datasets.length) {
      html += '<div class="jd-group"><span class="jd-group-label">Datasets</span><div class="jd-chips">' +
        datasets.map(function(item) { return renderChip(item, environment); }).join('') + '</div></div>';
    }
    var warnings = (data && data.warnings) || [];
    if (warnings.length && options.showWarnings !== false) {
      html += '<ul class="jd-warnings">' + warnings.map(function(w) { return '<li><i class="fa fa-exclamation-triangle"></i> ' + escapeHtml(w) + '</li>'; }).join('') + '</ul>';
    }
    $box.html(html);
  }

  function summaryText(data) {
    var connectors = (data && data.connectors) || [];
    var datasets = (data && data.datasets) || [];
    var attention = connectors.concat(datasets).filter(function(item) {
      return ['missing', 'inactive', 'out_of_scope'].indexOf(item.lightStatus) !== -1;
    }).length;
    var parts = [];
    if (connectors.length) { parts.push(connectors.length + ' connector' + (connectors.length === 1 ? '' : 's')); }
    if (datasets.length) { parts.push(datasets.length + ' dataset' + (datasets.length === 1 ? '' : 's')); }
    if (!parts.length) { return ''; }
    return parts.join(' · ') + (attention ? ' · ' + attention + ' need attention' : '');
  }

  function scan(payload) {
    return $.post(base() + 'jobCreation/scanDependencies', payload);
  }

  function test(payload) {
    return $.post(base() + 'jobCreation/testDependencies', payload);
  }

  function load(controller, jobName, environment) {
    return $.getJSON(base() + controller + '/dependencies', {job: jobName, environment: environment || ''});
  }

  window.JobSeekerJobDependencies = {
    render: render,
    renderChip: renderChip,
    summaryText: summaryText,
    scan: scan,
    test: test,
    load: load
  };
})(window, jQuery);
