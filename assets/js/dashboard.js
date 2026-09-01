(function($, window) {
  'use strict';

  var config = window.jobseekerDashboardConfig || {};
  var state = { overview: null, trendDays: 30, charts: {}, loading: false };
  var colors = {
    ready: '#00a65a', warning: '#f39c12', error: '#dd4b39', running: '#00c0ef',
    cancelled: '#7f8c8d', other: '#605ca8', blue: '#3c8dbc', navy: '#001f3f',
    teal: '#39cccc', purple: '#605ca8', olive: '#3d9970'
  };

  function number(value) {
    var parsed = Number(value);
    return isFinite(parsed) ? parsed : 0;
  }

  function array(value) {
    return Array.isArray(value) ? value : [];
  }

  function withEnvironment(url) {
    var environment = String(config.environment || 'all');
    if (!environment || environment.toLowerCase() === 'all') {
      return url;
    }
    return url + (url.indexOf('?') === -1 ? '?' : '&') + 'environment=' + encodeURIComponent(environment);
  }

  function request(url) {
    return $.ajax({ url: withEnvironment(url), method: 'GET', dataType: 'json', cache: false });
  }

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  function formatInteger(value) {
    return Math.round(number(value)).toLocaleString();
  }

  function formatCompact(value) {
    var amount = number(value);
    var absolute = Math.abs(amount);
    if (absolute >= 1000000000) return (amount / 1000000000).toFixed(absolute >= 10000000000 ? 0 : 1).replace(/\.0$/, '') + 'B';
    if (absolute >= 1000000) return (amount / 1000000).toFixed(absolute >= 10000000 ? 0 : 1).replace(/\.0$/, '') + 'M';
    if (absolute >= 1000) return (amount / 1000).toFixed(absolute >= 10000 ? 0 : 1).replace(/\.0$/, '') + 'K';
    return formatInteger(amount);
  }

  function formatPercent(value) {
    return value == null ? 'N/A' : number(value).toFixed(1).replace(/\.0$/, '') + '%';
  }

  function formatDuration(value) {
    var seconds = number(value);
    if (!seconds) return '0s';
    if (seconds < 60) return (seconds < 10 ? seconds.toFixed(1).replace(/\.0$/, '') : Math.round(seconds)) + 's';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + Math.round(seconds % 60) + 's';
    return Math.floor(seconds / 3600) + 'h ' + Math.round((seconds % 3600) / 60) + 'm';
  }

  function formatActivity(value) {
    if (!value || !window.moment) return value || 'N/A';
    var activity = moment(value);
    return activity.isValid() ? activity.fromNow() : value;
  }

  function titleActivity(value) {
    if (!value || !window.moment) return value || '';
    var activity = moment(value);
    return activity.isValid() ? activity.format('lll') : value;
  }

  function plural(value, singular, pluralValue) {
    return number(value) === 1 ? singular : (pluralValue || singular + 's');
  }

  function setEmpty(wrapSelector, emptySelector, empty) {
    $(wrapSelector).toggle(!empty);
    $(emptySelector).css('display', empty ? 'flex' : 'none');
  }

  function destroyChart(name) {
    if (state.charts[name]) {
      state.charts[name].destroy();
      state.charts[name] = null;
    }
  }

  function renderChartSafely(name, renderer, wrapSelector, emptySelector) {
    try {
      renderer();
    } catch (error) {
      destroyChart(name);
      $(wrapSelector).hide();
      $(emptySelector).css('display', 'flex');
      $(emptySelector).find('strong').text('Chart temporarily unavailable');
      $(emptySelector).find('span').text('The remaining dashboard metrics are still current.');
      if (window.console && typeof window.console.error === 'function') {
        window.console.error('Unable to render dashboard ' + name + ' chart.', error);
      }
    }
  }

  function statusLabel(status) {
    var normalized = String(status || 'other').toLowerCase();
    var className = {
      ready: 'label-success', warning: 'label-warning', error: 'label-danger',
      running: 'label-info', cancelled: 'label-default'
    }[normalized] || 'label-primary';
    return '<span class="label ' + className + ' dashboard-status-label">' + escapeHtml(normalized) + '</span>';
  }

  function deltaMarkup(value, suffix, positiveIsGood) {
    if (value == null) return '<span class="text-muted"><i class="fa fa-minus"></i> No baseline</span>';
    var delta = number(value);
    if (delta === 0) return '<span class="text-muted"><i class="fa fa-minus"></i> No change</span>';
    var good = positiveIsGood ? delta > 0 : delta < 0;
    var arrow = delta > 0 ? 'fa-caret-up' : 'fa-caret-down';
    return '<span class="' + (good ? 'text-green' : 'text-red') + '"><i class="fa ' + arrow + '"></i> ' + Math.abs(delta).toFixed(1).replace(/\.0$/, '') + suffix + '</span>';
  }

  function renderKpis(data) {
    var current = data.period.current;
    var active = data.active;
    $('#dashboardActive').text(formatInteger(active.fresh));
    $('#dashboardActiveDetail').text(active.stale > 0 ? active.stale + ' stale running ' + plural(active.stale, 'state') + ' need reconciliation' : 'No stale running executions');
    $('#dashboardReadyRate').text(formatPercent(current.readyRate));
    $('#dashboardReadyDetail').text(formatInteger(current.status.ready) + ' ready of ' + formatInteger(current.assessed) + ' assessed');
    $('#dashboardAttention').text(formatInteger(current.attention));
    $('#dashboardAttentionDetail').text(formatInteger(current.status.warning) + ' warning · ' + formatInteger(current.status.error) + ' error');
    $('#dashboardRecords').text(formatCompact(current.recordsProcessed)).attr('title', formatInteger(current.recordsProcessed));
    $('#dashboardRecordsDetail').text(formatInteger(current.executions) + ' ' + plural(current.executions, 'execution') + ' reported');

    var first = data.history.firstActivity ? moment(data.history.firstActivity).format('MMM D, YYYY') : 'No activity';
    var last = data.history.lastActivity ? moment(data.history.lastActivity).format('MMM D, YYYY') : 'No activity';
    $('#dashboardHistoryDetail').text(formatInteger(data.history.executions) + ' total executions · ' + first + ' — ' + last);
  }

  function renderComparison(data) {
    var current = data.period.current;
    var change = data.period.change;
    $('#dashboardExecutionChange').html(deltaMarkup(change.executionsPercent, '%', true));
    $('#dashboardRateChange').html(deltaMarkup(change.readyRatePoints, ' pts', true));
    $('#dashboardAttentionChange').html(deltaMarkup(change.attentionPercent, '%', false));
    $('#dashboardDurationChange').html(deltaMarkup(change.averageDurationPercent, '%', false));
    $('#dashboardExecutionCurrent').text(formatInteger(current.executions));
    $('#dashboardRateCurrent').text(formatPercent(current.readyRate));
    $('#dashboardAttentionCurrent').text(formatInteger(current.attention));
    $('#dashboardDurationCurrent').text(formatDuration(current.averageDurationSeconds));
  }

  function renderTrend() {
    var rows = array(state.overview && state.overview.trend).slice(-state.trendDays);
    var hasData = rows.some(function(row) { return number(row.ready) + number(row.warning) + number(row.error) + number(row.cancelled) > 0; });
    destroyChart('trend');
    setEmpty('#dashboardTrendWrap', '#dashboardTrendEmpty', !hasData);
    if (!hasData || !window.Chart) return;

    var context = document.getElementById('dashboardTrendChart').getContext('2d');
    state.charts.trend = new Chart(context, {
      type: 'line',
      data: {
        labels: rows.map(function(row) { return row.date; }),
        datasets: [
          { label: 'Ready', data: rows.map(function(row) { return number(row.ready); }), borderColor: colors.ready, backgroundColor: 'rgba(0,166,90,.08)', pointRadius: state.trendDays <= 30 ? 2 : 0, borderWidth: 2, fill: false },
          { label: 'Warning', data: rows.map(function(row) { return number(row.warning); }), borderColor: colors.warning, backgroundColor: 'rgba(243,156,18,.08)', pointRadius: state.trendDays <= 30 ? 2 : 0, borderWidth: 2, fill: false },
          { label: 'Error', data: rows.map(function(row) { return number(row.error); }), borderColor: colors.error, backgroundColor: 'rgba(221,75,57,.08)', pointRadius: state.trendDays <= 30 ? 2 : 0, borderWidth: 2, fill: false },
          { label: 'Cancelled', data: rows.map(function(row) { return number(row.cancelled); }), borderColor: colors.cancelled, backgroundColor: 'rgba(127,140,141,.08)', pointRadius: state.trendDays <= 30 ? 2 : 0, borderWidth: 2, borderDash: [4, 3], fill: false }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } },
        tooltips: { mode: 'index', intersect: false }, hover: { mode: 'nearest', intersect: true },
        scales: {
          xAxes: [{ type: 'time', time: { unit: state.trendDays <= 30 ? 'day' : 'week', tooltipFormat: 'll' }, gridLines: { display: false }, ticks: { maxTicksLimit: state.trendDays <= 30 ? 10 : 12 } }],
          yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, scaleLabel: { display: true, labelString: 'Executions' } }]
        }
      }
    });
  }

  function renderStatus(data) {
    var status = data.period.current.status || {};
    var order = ['ready', 'warning', 'error', 'running', 'cancelled', 'other'];
    var rows = order.filter(function(key) { return number(status[key]) > 0; });
    destroyChart('status');
    setEmpty('#dashboardStatusWrap', '#dashboardStatusEmpty', rows.length === 0);
    if (!rows.length || !window.Chart) return;

    state.charts.status = new Chart(document.getElementById('dashboardStatusChart').getContext('2d'), {
      type: 'doughnut',
      data: { labels: rows.map(function(key) { return key.charAt(0).toUpperCase() + key.slice(1); }), datasets: [{ data: rows.map(function(key) { return number(status[key]); }), backgroundColor: rows.map(function(key) { return colors[key] || colors.other; }), borderWidth: 2, borderColor: '#fff' }] },
      options: { responsive: true, maintainAspectRatio: false, cutoutPercentage: 62, legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } }, tooltips: { callbacks: { label: function(item, chart) { return chart.labels[item.index] + ': ' + formatInteger(chart.datasets[0].data[item.index]); } } } }
    });
  }

  function renderEnvironmentTable(data) {
    var rows = array(data.environments);
    var html = rows.map(function(row) {
      var attentionClass = number(row.attention) > 0 ? 'text-red' : 'text-green';
      return '<tr><td class="dashboard-primary-cell"><span class="label label-primary">' + escapeHtml(row.environment) + '</span></td>' +
        '<td>' + formatInteger(row.executions) + (number(row.cancelled) ? '<span class="dashboard-secondary-cell">' + formatInteger(row.cancelled) + ' cancelled</span>' : '') + '</td><td>' + formatPercent(row.readyRate) + '</td>' +
        '<td class="' + attentionClass + '">' + formatInteger(row.attention) + '<span class="dashboard-secondary-cell">' + formatInteger(row.warning) + ' warning · ' + formatInteger(row.error) + ' error</span></td>' +
        '<td>' + formatInteger(row.running) + '</td><td title="' + formatInteger(row.recordsProcessed) + '">' + formatCompact(row.recordsProcessed) + '</td>' +
        '<td>' + formatDuration(row.averageDurationSeconds) + '</td><td title="' + escapeHtml(titleActivity(row.lastActivity)) + '">' + escapeHtml(formatActivity(row.lastActivity)) + '</td></tr>';
    }).join('');
    $('#dashboardEnvironmentRows').html(html).closest('table').toggle(rows.length > 0);
    $('#dashboardEnvironmentEmpty').css('display', rows.length ? 'none' : 'flex');
  }

  function renderRecentTable(data) {
    var rows = array(data.recentExecutions);
    var html = rows.map(function(row) {
      return '<tr><td class="dashboard-primary-cell" title="' + escapeHtml(row.job) + '">' + escapeHtml(row.job || 'Unnamed job') + '<span class="dashboard-secondary-cell" title="' + escapeHtml(row.category) + '">' + escapeHtml(row.event || row.category) + '</span></td>' +
        '<td><span class="label label-default">' + escapeHtml(row.environment) + '</span></td><td>' + statusLabel(row.status) + '</td>' +
        '<td>' + (row.recordsProcessed == null ? 'N/A' : formatCompact(row.recordsProcessed)) + '</td><td>' + (row.durationSeconds == null ? 'N/A' : formatDuration(row.durationSeconds)) + '</td>' +
        '<td title="' + escapeHtml(titleActivity(row.lastActivity)) + '">' + escapeHtml(formatActivity(row.lastActivity)) + '</td></tr>';
    }).join('');
    $('#dashboardRecentRows').html(html).closest('table').toggle(rows.length > 0);
    $('#dashboardRecentEmpty').css('display', rows.length ? 'none' : 'flex');
  }

  function renderPipelineTable(data) {
    var rows = array(data.topPipelines);
    var html = rows.map(function(row) {
      return '<tr><td class="dashboard-primary-cell" title="' + escapeHtml(row.job) + '">' + escapeHtml(row.job || 'Unnamed job') + '<span class="dashboard-secondary-cell" title="' + escapeHtml(row.category) + '">' + escapeHtml(row.category) + '</span></td>' +
        '<td>' + formatInteger(row.executions) + '</td><td>' + formatPercent(row.readyRate) + '</td><td class="' + (number(row.attention) ? 'text-red' : 'text-green') + '">' + formatInteger(row.attention) + '</td>' +
        '<td title="' + formatInteger(row.recordsProcessed) + '">' + formatCompact(row.recordsProcessed) + '</td><td>' + formatDuration(row.averageDurationSeconds) + '</td></tr>';
    }).join('');
    $('#dashboardPipelineRows').html(html).closest('table').toggle(rows.length > 0);
    $('#dashboardPipelineEmpty').css('display', rows.length ? 'none' : 'flex');
  }

  function renderWorkloads(data) {
    var rows = array(data.workloads);
    var total = rows.reduce(function(sum, row) { return sum + number(row.executions); }, 0);
    $('#dashboardWorkloadExecutions').text(formatCompact(total)).attr('title', formatInteger(total));
    destroyChart('workloads');
    setEmpty('#dashboardWorkloadWrap', '#dashboardWorkloadEmpty', rows.length === 0);
    if (!rows.length || !window.Chart) return;

    state.charts.workloads = new Chart(document.getElementById('dashboardWorkloadChart').getContext('2d'), {
      type: 'horizontalBar',
      data: { labels: rows.map(function(row) { return row.category; }), datasets: [
        { label: 'Ready', data: rows.map(function(row) { return number(row.ready); }), backgroundColor: 'rgba(0,166,90,.78)' },
        { label: 'Needs attention', data: rows.map(function(row) { return number(row.attention); }), backgroundColor: 'rgba(221,75,57,.78)' },
        { label: 'Other states', data: rows.map(function(row) { return Math.max(0, number(row.executions) - number(row.ready) - number(row.attention)); }), backgroundColor: 'rgba(60,141,188,.65)' }
      ] },
      options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' }, tooltips: { mode: 'index', intersect: false }, scales: { xAxes: [{ stacked: true, ticks: { beginAtZero: true, precision: 0 }, scaleLabel: { display: true, labelString: 'Observed executions' } }], yAxes: [{ stacked: true, gridLines: { display: false } }] } }
    });
  }

  function renderAssets(data) {
    var assets = data.assets || {};
    var formats = array(assets.formats);
    $('#dashboardAssetActive').text(formatInteger(assets.active));
    $('#dashboardAssetActive').attr('title', formatInteger(assets.storedBytes || 0) + ' stored bytes');
    $('#dashboardAssetInputs').text(formatInteger(assets.inputs));
    $('#dashboardAssetOutputs').text(formatInteger(assets.outputs));
    destroyChart('assets');
    setEmpty('#dashboardAssetWrap', '#dashboardAssetEmpty', formats.length === 0);
    if (!formats.length || !window.Chart) return;

    var palette = [colors.blue, colors.ready, colors.warning, colors.purple, colors.teal, colors.navy, colors.olive, colors.cancelled];
    state.charts.assets = new Chart(document.getElementById('dashboardAssetChart').getContext('2d'), {
      type: 'doughnut',
      data: { labels: formats.map(function(row) { return String(row.format || 'unknown').toUpperCase(); }), datasets: [{ data: formats.map(function(row) { return number(row.amount); }), backgroundColor: formats.map(function(row, index) { return palette[index % palette.length]; }), borderColor: '#fff', borderWidth: 2 }] },
      options: { responsive: true, maintainAspectRatio: false, cutoutPercentage: 58, legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } } }
    });
  }

  function renderOverview(data) {
    state.overview = data;
    renderKpis(data);
    renderComparison(data);
    renderEnvironmentTable(data);
    renderRecentTable(data);
    renderPipelineTable(data);
    renderChartSafely('trend', renderTrend, '#dashboardTrendWrap', '#dashboardTrendEmpty');
    renderChartSafely('status', function() { renderStatus(data); }, '#dashboardStatusWrap', '#dashboardStatusEmpty');
    renderChartSafely('workloads', function() { renderWorkloads(data); }, '#dashboardWorkloadWrap', '#dashboardWorkloadEmpty');
    renderChartSafely('assets', function() { renderAssets(data); }, '#dashboardAssetWrap', '#dashboardAssetEmpty');
    $('#dashboardUpdated').text('Operational snapshot updated ' + formatActivity(data.generatedAt) + '.');
    $('#dashboardLoading').hide();
    $('#dashboardError').hide();
    $('#dashboardContent').fadeIn(180);
  }

  function setJenkinsUnavailable(message) {
    $('#dashboardJenkinsHeadline').text('Jenkins metrics unavailable');
    $('#dashboardJenkinsDetail').text(message || 'The Jenkins API could not be reached. TMF dashboard metrics remain available.');
    $('#dashboardExecutors, #dashboardQueue, #dashboardAgents, #dashboardSlots').text('N/A');
    $('#dashboardExecutorBar').css('width', '0%');
    $('#dashboardExecutorStat, #dashboardQueueStat, #dashboardAgentStat, #dashboardSlotStat').removeClass('is-warning is-danger');
  }

  function renderJenkins(payload) {
    if (!payload || payload.ok !== true) {
      setJenkinsUnavailable(payload && payload.message);
      return;
    }
    var executors = payload.executors || {};
    var nodes = payload.nodes || {};
    var slots = payload.slots || {};
    var totalAgents = number(nodes.agents);
    var utilization = executors.utilization == null ? 0 : number(executors.utilization);
    var limitText = number(slots.limit) > 0 ? formatInteger(slots.limit) : '∞';

    $('#dashboardExecutors').text(formatInteger(executors.busy) + ' / ' + formatInteger(executors.total));
    $('#dashboardExecutorBar').css('width', Math.min(100, utilization) + '%');
    $('#dashboardQueue').text(formatInteger(payload.queueDepth));
    $('#dashboardAgents').text(formatInteger(nodes.onlineAgents) + ' / ' + formatInteger(totalAgents));
    $('#dashboardSlots').text(formatInteger(slots.active) + ' / ' + limitText);
    $('#dashboardExecutorStat').toggleClass('is-warning', utilization >= 80 && utilization < 100).toggleClass('is-danger', utilization >= 100);
    $('#dashboardQueueStat').toggleClass('is-warning', number(payload.queueDepth) > 0).toggleClass('is-danger', number(payload.queueDepth) >= Math.max(3, number(executors.total)));
    $('#dashboardAgentStat').toggleClass('is-danger', number(nodes.offline) > 0 || number(nodes.onlineAgents) < totalAgents);
    $('#dashboardSlotStat').toggleClass('is-warning', number(slots.queued) > 0).toggleClass('is-danger', number(slots.limit) > 0 && number(slots.active) >= number(slots.limit));

    var scope = payload.scope === 'all' ? 'all environments' : payload.scope;
    var routing = payload.environmentAgentsEnabled ? 'Environment-agent routing is enabled.' : 'Environment-agent routing is disabled.';
    $('#dashboardJenkinsHeadline').text('Jenkins is available · ' + utilization.toFixed(1).replace(/\.0$/, '') + '% executor utilization');
    $('#dashboardJenkinsDetail').text(formatInteger(executors.idle) + ' idle ' + plural(executors.idle, 'executor') + ', ' + formatInteger(payload.queueDepth) + ' queued ' + plural(payload.queueDepth, 'build') + ' for ' + scope + '. ' + routing);
  }

  function loadJenkins() {
    return request(config.jenkinsMetricsUrl).done(renderJenkins).fail(function(xhr) {
      var payload = xhr.responseJSON || {};
      setJenkinsUnavailable(payload.message);
    });
  }

  function loadOverview(fresh) {
    if (state.loading) return $.Deferred().reject().promise();
    state.loading = true;
    $('#dashboardRefresh i').addClass('dashboard-refresh-spin');
    var url = fresh ? config.overviewUrl + (config.overviewUrl.indexOf('?') === -1 ? '?' : '&') + 'fresh=1' : config.overviewUrl;
    return request(url).done(function(payload) {
      if (!payload || payload.ok !== true || !payload.data) {
        $('#dashboardLoading').hide();
        $('#dashboardErrorMessage').text('The dashboard returned an invalid response.');
        $('#dashboardError').show();
        return;
      }
      try {
        renderOverview(payload.data);
      } catch (error) {
        $('#dashboardLoading').hide();
        $('#dashboardContent').show();
        $('#dashboardErrorMessage').text('Some dashboard components could not be rendered. The available metrics are shown below.');
        $('#dashboardError').show();
        if (window.console && typeof window.console.error === 'function') {
          window.console.error('Unable to render dashboard overview.', error);
        }
      }
    }).fail(function(xhr) {
      var payload = xhr.responseJSON || {};
      $('#dashboardLoading').hide();
      $('#dashboardErrorMessage').text(payload.message || 'The dashboard API could not produce an operational snapshot.');
      $('#dashboardError').show();
    }).always(function() {
      state.loading = false;
      $('#dashboardRefresh i').removeClass('dashboard-refresh-spin');
    });
  }

  function refreshAll(fresh) {
    loadOverview(fresh === true);
    loadJenkins();
  }

  $(function() {
    $('body').addClass('sidebar-collapse');
    refreshAll();
    $('input[name="dashboardTrendDays"]').on('change', function() {
      state.trendDays = Math.max(30, Math.min(180, number(this.value) || 30));
      renderTrend();
    });
    $('#dashboardRefresh').on('click', function() { refreshAll(true); });
    window.jobseekerDashboardJenkinsTimer = window.jobseekerDashboardJenkinsTimer || window.setInterval(loadJenkins, 30000);
  });

  window.JobSeekerDashboard = {
    formatCompact: formatCompact,
    formatDuration: formatDuration,
    withEnvironment: withEnvironment
  };
})(jQuery, window);
