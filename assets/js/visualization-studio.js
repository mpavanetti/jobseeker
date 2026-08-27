(function($) {
  'use strict';

  var config = window.JobSeekerInsightStudio || {};
  var datasets = {};
  var chartInstances = {};
  var chartTypes = {
    bar: { label: 'Bars', icon: 'fa-bar-chart' },
    line: { label: 'Line', icon: 'fa-line-chart' },
    doughnut: { label: 'Donut', icon: 'fa-pie-chart' },
    kpi: { label: 'KPI', icon: 'fa-hashtag' },
    table: { label: 'Table', icon: 'fa-table' }
  };
  var palettes = {
    aurora: ['#5167f6', '#35d0a2', '#ff9f66', '#8b5cf6', '#ef5da8', '#2ab7ca', '#ffc857', '#748095'],
    signal: ['#30b782', '#ee6b63', '#f2b84b', '#4d8ff7', '#8b6ce0', '#ec7ab5', '#43b8aa', '#8d98aa'],
    ocean: ['#176b87', '#2b8ca3', '#45aab5', '#64ccc5', '#8ae0d2', '#39a7ff', '#577dba', '#a1ccd1'],
    mono: ['#263248', '#46536a', '#667288', '#8590a2', '#a4acba', '#c1c7d1', '#d7dbe2', '#737d8d']
  };
  var state = {
    currentId: 0,
    canEdit: true,
    owner: config.currentUserName || 'You',
    widgets: [],
    selectedId: '',
    dirty: false,
    recipe: { dataset: 'tmf_runs', dimension: 'status', measure: 'runs', chart: 'bar' }
  };

  $.each(config.datasets || [], function(_, dataset) { datasets[dataset.key] = dataset; });

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  function uid() {
    return 'visual_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);
  }

  function datasetFor(key) {
    return datasets[key] || datasets.tmf_runs || (config.datasets || [])[0];
  }

  function fieldFor(dataset, collection, key) {
    var found = null;
    $.each((dataset && dataset[collection]) || [], function(_, field) {
      if (field.key === key) found = field;
    });
    return found;
  }

  function labelFor(dataset, collection, key, fallback) {
    var field = fieldFor(dataset, collection, key);
    return field ? field.label : fallback;
  }

  function setDirty(isDirty) {
    state.dirty = !!isDirty;
    $('#studioDirty').toggleClass('is-visible', state.dirty);
  }

  function markDirty() { setDirty(true); }

  function defaultEnvironment() {
    var value = window.jobseekerDashboardEnvironment || 'all';
    return value === '' ? 'all' : value;
  }

  function makeWidget(options) {
    var dataset = datasetFor(options.dataset || state.recipe.dataset);
    var chart = options.chart || state.recipe.chart || 'bar';
    var dimension = chart === 'kpi' ? '' : (options.dimension || dataset.default_dimension);
    var measure = options.measure || dataset.default_measure;
    return {
      id: options.id || uid(),
      title: options.title || labelFor(dataset, 'measures', measure, dataset.name) + (dimension ? ' by ' + labelFor(dataset, 'dimensions', dimension, '') : ''),
      dataset: dataset.key,
      chart: chart,
      dimension: dimension,
      measure: measure,
      date_range: options.date_range || '30d',
      environment: options.environment || defaultEnvironment(),
      palette: options.palette || 'aurora',
      size: parseInt(options.size, 10) || (chart === 'kpi' ? 4 : 6)
    };
  }

  function starterWidgets() {
    return [
      makeWidget({ title: 'Runs in the last 30 days', dataset: 'tmf_runs', chart: 'kpi', measure: 'runs', size: 4 }),
      makeWidget({ title: 'Runs needing attention', dataset: 'tmf_runs', chart: 'kpi', measure: 'failures', palette: 'signal', size: 4 }),
      makeWidget({ title: 'Processing completion', dataset: 'tmf_runs', chart: 'kpi', measure: 'completion_rate', size: 4 }),
      makeWidget({ title: 'Run health', dataset: 'tmf_runs', chart: 'doughnut', dimension: 'status', measure: 'runs', palette: 'signal', size: 4 }),
      makeWidget({ title: 'Daily run volume', dataset: 'tmf_runs', chart: 'line', dimension: 'activity_day', measure: 'runs', size: 8 }),
      makeWidget({ title: 'Jobs needing attention', dataset: 'tmf_runs', chart: 'bar', dimension: 'job_name', measure: 'failures', palette: 'signal', size: 12 })
    ];
  }

  function renderChartButtons(target, selected) {
    var $target = $(target).empty();
    $.each(chartTypes, function(key, chart) {
      $('<button>', { type: 'button', title: chart.label, 'data-chart': key, 'aria-label': chart.label })
        .addClass(target === '#studioChartTypes' ? 'studio-chart-type' : '')
        .toggleClass('is-active', key === selected)
        .html('<i class="fa ' + chart.icon + '"></i>')
        .appendTo($target);
    });
  }

  function renderDatasets() {
    var $list = $('#studioDatasets').empty();
    $.each(config.datasets || [], function(_, dataset) {
      var $button = $('<button>', { type: 'button', 'data-dataset': dataset.key })
        .attr('draggable', 'true')
        .addClass('studio-dataset')
        .toggleClass('is-active', dataset.key === state.recipe.dataset);
      $button.append('<span class="studio-dataset-icon"><i class="fa ' + escapeHtml(dataset.icon) + '"></i></span>');
      $button.append($('<span>').append($('<strong>').text(dataset.name)).append($('<small>').text(dataset.freshness + ' · governed')));
      $button.append('<i class="fa fa-check-circle"></i>');
      $list.append($button);
    });
    renderFields();
    filterDataLibrary();
  }

  function renderFields() {
    var dataset = datasetFor(state.recipe.dataset);
    var $dimensions = $('#studioDimensions').empty();
    var $measures = $('#studioMeasures').empty();
    $.each(dataset.dimensions || [], function(_, field) {
      var icon = field.type === 'time' ? 'fa-calendar' : 'fa-font';
      $('<div>', { 'data-kind': 'dimension', 'data-field': field.key, 'data-dataset': dataset.key })
        .attr('draggable', 'true')
        .addClass('studio-field ' + (field.type === 'time' ? 'studio-field-time' : ''))
        .html('<i class="fa ' + icon + '"></i><span>' + escapeHtml(field.label) + '</span>')
        .appendTo($dimensions);
    });
    $.each(dataset.measures || [], function(_, field) {
      $('<div>', { 'data-kind': 'measure', 'data-field': field.key, 'data-dataset': dataset.key })
        .attr('draggable', 'true')
        .addClass('studio-field studio-field-measure')
        .html('<i class="fa fa-hashtag"></i><span>' + escapeHtml(field.label) + '</span>')
        .appendTo($measures);
    });
    updateRecipe();
  }

  function filterDataLibrary() {
    var query = $.trim($('#studioFieldSearch').val() || '').toLowerCase();
    $('.studio-dataset').each(function() {
      var dataset = datasetFor($(this).data('dataset'));
      var searchable = [dataset.name, dataset.description, dataset.connection_name || ''];
      $.each((dataset.dimensions || []).concat(dataset.measures || []), function(_, field) { searchable.push(field.label); });
      $(this).toggle(!query || searchable.join(' ').toLowerCase().indexOf(query) !== -1);
    });
    $('.studio-field').each(function() { $(this).toggle(!query || $(this).text().toLowerCase().indexOf(query) !== -1); });
  }

  function updateRecipe() {
    var dataset = datasetFor(state.recipe.dataset);
    $('#studioRecipeDataset strong').text(dataset.name);
    $('#studioDimensionDrop strong').text(state.recipe.chart === 'kpi' ? 'Not needed for a KPI' : labelFor(dataset, 'dimensions', state.recipe.dimension, 'Drop a field'));
    $('#studioDimensionDrop').toggleClass('is-disabled', state.recipe.chart === 'kpi');
    $('#studioMeasureDrop strong').text(labelFor(dataset, 'measures', state.recipe.measure, 'Drop a field'));
    renderChartButtons('#studioChartTypes', state.recipe.chart);
  }

  function destroyCharts() {
    $.each(chartInstances, function(id, instance) { if (instance && instance.destroy) instance.destroy(); });
    chartInstances = {};
  }

  function renderGrid() {
    destroyCharts();
    var $grid = $('#studioGrid').empty();
    $.each(state.widgets, function(_, widget) {
      var dataset = datasetFor(widget.dataset);
      var measureLabel = labelFor(dataset, 'measures', widget.measure, widget.measure);
      var dimensionLabel = widget.dimension ? labelFor(dataset, 'dimensions', widget.dimension, widget.dimension) : 'All records';
      var $widget = $('<article>', { 'data-widget-id': widget.id, 'data-chart': widget.chart, 'data-size': widget.size, 'data-palette': widget.palette })
        .addClass('studio-widget')
        .toggleClass('is-selected', widget.id === state.selectedId);
      var head = '<div class="studio-widget-head"><div class="studio-widget-title-wrap"><i class="fa fa-ellipsis-v studio-widget-grip"></i><div><h3>' + escapeHtml(widget.title) + '</h3><div class="studio-widget-meta">' + escapeHtml(dataset.name + ' · ' + measureLabel + ' · ' + dimensionLabel) + '</div></div></div><div class="studio-widget-actions"><button class="studio-widget-action studio-duplicate-widget" type="button" title="Duplicate"><i class="fa fa-copy"></i></button><button class="studio-widget-action studio-edit-widget" type="button" title="Edit"><i class="fa fa-sliders"></i></button><button class="studio-widget-action studio-remove-widget" type="button" title="Remove"><i class="fa fa-times"></i></button></div></div>';
      $widget.html(head + '<div class="studio-widget-body"><div class="studio-loading"><span><i class="fa fa-circle-o-notch fa-spin"></i> Querying governed data…</span></div></div>');
      $grid.append($widget);
      loadWidget(widget);
    });
    $('#studioWidgetCount').text(state.widgets.length + (state.widgets.length === 1 ? ' visual' : ' visuals'));
    $grid.css('display', state.widgets.length ? 'grid' : 'none');
    $('#studioEmptyCanvas').toggleClass('is-visible', state.widgets.length === 0);
    if ($('#studioGrid').data('ui-sortable')) $('#studioGrid').sortable('refresh');
    renderInspector();
  }

  function statusColor(label, fallback) {
    var key = String(label || '').toLowerCase();
    if (key === 'ready' || key === 'success') return '#30b782';
    if (key === 'error' || key === 'failed') return '#ee6b63';
    if (key === 'warning') return '#f2b84b';
    if (key === 'running') return '#4d8ff7';
    if (key === 'cancelled') return '#8d98aa';
    return fallback;
  }

  function colorsFor(widget, rows) {
    var palette = palettes[widget.palette] || palettes.aurora;
    return $.map(rows, function(row, index) {
      var fallback = palette[index % palette.length];
      return widget.palette === 'signal' ? statusColor(row.label, fallback) : fallback;
    });
  }

  function hexToRgba(hex, alpha) {
    var normalized = hex.replace('#', '');
    var value = parseInt(normalized.length === 3 ? normalized.replace(/(.)/g, '$1$1') : normalized, 16);
    return 'rgba(' + ((value >> 16) & 255) + ',' + ((value >> 8) & 255) + ',' + (value & 255) + ',' + alpha + ')';
  }

  function measureFormat(widget) {
    var field = fieldFor(datasetFor(widget.dataset), 'measures', widget.measure);
    return field ? field.format : 'number';
  }

  function formatValue(value, format, compact) {
    var number = Number(value || 0);
    if (format === 'percent') return number.toLocaleString(undefined, { maximumFractionDigits: 1 }) + '%';
    if (format === 'duration') {
      if (number < 60) return Math.round(number) + ' sec';
      if (number < 3600) return Math.floor(number / 60) + 'm ' + Math.round(number % 60) + 's';
      return Math.floor(number / 3600) + 'h ' + Math.round((number % 3600) / 60) + 'm';
    }
    if (compact && Math.abs(number) >= 1000 && window.Intl && Intl.NumberFormat) return new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(number);
    return number.toLocaleString(undefined, { maximumFractionDigits: number % 1 ? 1 : 0 });
  }

  function loadWidget(widget) {
    $.getJSON(config.endpoints.query, {
      dataset: widget.dataset,
      dimension: widget.chart === 'kpi' ? '' : widget.dimension,
      measure: widget.measure,
      date_range: widget.date_range,
      environment: widget.environment,
      limit: widget.chart === 'table' ? 30 : 16
    }).done(function(payload) {
      renderWidgetData(widget, payload.data || []);
    }).fail(function(xhr) {
      var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'This visual could not be loaded.';
      $('[data-widget-id="' + widget.id + '"] .studio-widget-body').html('<div class="studio-widget-empty"><div><i class="fa fa-exclamation-circle"></i><br>' + escapeHtml(message) + '</div></div>');
    });
  }

  function renderWidgetData(widget, rows) {
    var $body = $('[data-widget-id="' + widget.id + '"] .studio-widget-body');
    if (!$body.length) return;
    if (!rows.length) {
      $body.html('<div class="studio-widget-empty"><div><i class="fa fa-inbox"></i><br>No data for this time window.</div></div>');
      return;
    }
    if (widget.chart === 'kpi') {
      var value = rows[0] ? rows[0].value : 0;
      var dataset = datasetFor(widget.dataset);
      $body.html('<div class="studio-kpi"><div class="studio-kpi-value">' + escapeHtml(formatValue(value, measureFormat(widget), true)) + '</div><div class="studio-kpi-label">' + escapeHtml(labelFor(dataset, 'measures', widget.measure, widget.measure)) + '</div><div class="studio-kpi-context"><i class="fa fa-clock-o"></i> ' + escapeHtml(rangeLabel(widget.date_range)) + ' · ' + escapeHtml(environmentLabel(widget.environment)) + '</div></div>');
      return;
    }
    if (widget.chart === 'table') {
      renderTable($body, widget, rows);
      return;
    }
    var canvas = $('<canvas>')[0];
    $body.empty().append($('<div class="studio-chart-wrap">').append(canvas));
    var colors = colorsFor(widget, rows);
    var labels = $.map(rows, function(row) { return row.label; });
    var values = $.map(rows, function(row) { return Number(row.value || 0); });
    var primary = colors[0] || palettes.aurora[0];
    var datasetOptions = {
      label: labelFor(datasetFor(widget.dataset), 'measures', widget.measure, widget.measure),
      data: values,
      backgroundColor: widget.chart === 'line' ? hexToRgba(primary, .14) : colors,
      borderColor: widget.chart === 'line' ? primary : colors,
      borderWidth: widget.chart === 'line' ? 2 : 0,
      pointBackgroundColor: primary,
      pointRadius: widget.chart === 'line' ? 2 : 0,
      pointHoverRadius: 4,
      fill: widget.chart === 'line',
      lineTension: .32
    };
    chartInstances[widget.id] = new Chart(canvas.getContext('2d'), {
      type: widget.chart,
      data: { labels: labels, datasets: [datasetOptions] },
      options: chartOptions(widget)
    });
  }

  function chartOptions(widget) {
    var isDoughnut = widget.chart === 'doughnut';
    var options = {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 450 },
      legend: { display: isDoughnut, position: 'right', labels: { boxWidth: 10, padding: 12, fontSize: 10, fontColor: '#667288' } },
      tooltips: { mode: isDoughnut ? 'nearest' : 'index', intersect: false, callbacks: { label: function(item, data) { var label = isDoughnut ? data.labels[item.index] + ': ' : ''; var value = isDoughnut ? data.datasets[0].data[item.index] : item.yLabel; return label + formatValue(value, measureFormat(widget), false); } } }
    };
    if (!isDoughnut) {
      options.scales = {
        xAxes: [{ gridLines: { display: false }, ticks: { fontSize: 9, fontColor: '#7d8799', maxRotation: 35, minRotation: 0, autoSkip: true, maxTicksLimit: 12 } }],
        yAxes: [{ gridLines: { color: '#edf0f5', drawBorder: false }, ticks: { beginAtZero: true, fontSize: 9, fontColor: '#7d8799', callback: function(value) { return formatValue(value, measureFormat(widget), true); } } }]
      };
      options.legend.display = false;
    }
    return options;
  }

  function renderTable($body, widget, rows) {
    var dataset = datasetFor(widget.dataset);
    var dimension = labelFor(dataset, 'dimensions', widget.dimension, 'Category');
    var measure = labelFor(dataset, 'measures', widget.measure, 'Value');
    var $table = $('<table class="studio-data-table">');
    $table.append($('<thead>').append($('<tr>').append($('<th>').text(dimension)).append($('<th>').text(measure))));
    var $tbody = $('<tbody>');
    $.each(rows, function(_, row) { $tbody.append($('<tr>').append($('<td>').text(row.label)).append($('<td>').text(formatValue(row.value, measureFormat(widget), false)))); });
    $body.empty().append($table.append($tbody));
  }

  function rangeLabel(range) {
    return ({ '7d': 'Last 7 days', '30d': 'Last 30 days', '90d': 'Last 90 days', '180d': 'Last 180 days', all: 'All time' })[range] || 'Last 30 days';
  }

  function environmentLabel(environment) {
    return !environment || environment === 'all' || environment === '*' ? 'All environments' : (environment === '__UNKNOWN__' ? 'Unknown environment' : environment);
  }

  function widgetById(id) {
    var found = null;
    $.each(state.widgets, function(_, widget) { if (widget.id === id) found = widget; });
    return found;
  }

  function selectWidget(id) {
    state.selectedId = id || '';
    $('.studio-widget').toggleClass('is-selected', false);
    if (id) $('[data-widget-id="' + id + '"]').addClass('is-selected');
    renderInspector();
  }

  function setOptions($select, fields, selected, includeEmpty) {
    $select.empty();
    if (includeEmpty) $select.append($('<option>', { value: '' }).text('Not used'));
    $.each(fields || [], function(_, field) { $select.append($('<option>', { value: field.key }).text(field.label)); });
    $select.val(selected);
  }

  function populateInspectorFields(widget) {
    var dataset = datasetFor($('#studioInspectorDataset').val() || widget.dataset);
    var chart = $('#studioInspectorCharts button.is-active').data('chart') || widget.chart;
    var dimension = widget.dataset === dataset.key ? widget.dimension : dataset.default_dimension;
    var measure = widget.dataset === dataset.key ? widget.measure : dataset.default_measure;
    setOptions($('#studioInspectorDimension'), dataset.dimensions, chart === 'kpi' ? '' : dimension, chart === 'kpi');
    $('#studioInspectorDimension').prop('disabled', chart === 'kpi');
    setOptions($('#studioInspectorMeasure'), dataset.measures, measure, false);
  }

  function renderInspector() {
    var widget = widgetById(state.selectedId);
    $('#studioInspectorEmpty').toggle(!widget);
    $('#studioInspectorForm').toggleClass('is-visible', !!widget);
    $('#studioInspector').toggleClass('is-visible', !!widget);
    if (!widget) return;
    $('#studioWidgetTitle').val(widget.title);
    var $dataset = $('#studioInspectorDataset').empty();
    $.each(config.datasets || [], function(_, item) { $dataset.append($('<option>', { value: item.key }).text(item.name)); });
    $dataset.val(widget.dataset);
    renderChartButtons('#studioInspectorCharts', widget.chart);
    populateInspectorFields(widget);
    $('#studioInspectorRange').val(widget.date_range);
    if ($('#studioInspectorEnvironment option[value="' + widget.environment + '"]').length === 0) {
      $('#studioInspectorEnvironment').append($('<option>', { value: widget.environment }).text(environmentLabel(widget.environment)));
    }
    $('#studioInspectorEnvironment').val(widget.environment);
    $('#studioSizePicker button').removeClass('is-active').filter('[data-size="' + widget.size + '"]').addClass('is-active');
    $('#studioPalettePicker button').removeClass('is-active').filter('[data-palette="' + widget.palette + '"]').addClass('is-active');
  }

  function populateEnvironments() {
    var $selects = $('#studioInspectorEnvironment,#studioGlobalEnvironment');
    $.each(window.jobseekerGlobalEnvironmentOptions || [], function(_, environment) {
      var value = window.JobSeekerEnvironment && window.JobSeekerEnvironment.normalize ? window.JobSeekerEnvironment.normalize(environment) : String(environment).toUpperCase();
      $selects.each(function() { if ($(this).find('option[value="' + value + '"]').length === 0) $(this).append($('<option>', { value: value }).text(environment)); });
    });
    $selects.each(function() { if (!$(this).find('option[value="__UNKNOWN__"]').length) $(this).append($('<option>', { value: '__UNKNOWN__' }).text('Unknown environment')); });
    $('#studioGlobalEnvironment').val(defaultEnvironment());
  }

  function applyGlobalFilters() {
    if (!state.widgets.length) { toastr.info('Add a visual before applying dashboard filters.'); return; }
    var range = $('#studioGlobalRange').val();
    var environment = $('#studioGlobalEnvironment').val();
    $.each(state.widgets, function(_, widget) { widget.date_range = range; widget.environment = environment; });
    markDirty();
    renderGrid();
    toastr.success('Dashboard filters applied to every visual.');
  }

  function exportDashboard() {
    var name = $.trim($('#studioDashboardName').val()) || 'Insight Studio dashboard';
    var payload = { product: 'Jobseeker Insight Studio', exported_at: new Date().toISOString(), name: name, is_shared: $('#studioShareDashboard').is(':checked'), definition: { version: 1, widgets: state.widgets } };
    var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.json';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    toastr.success('Dashboard template exported.');
  }

  function importDashboard(file) {
    if (!file || file.size > 150000) { toastr.error('Choose a dashboard JSON file smaller than 150 KB.'); return; }
    var reader = new FileReader();
    reader.onload = function(event) {
      try {
        var payload = JSON.parse(event.target.result);
        var definition = payload.definition || payload;
        if (!definition || !Array.isArray(definition.widgets) || definition.widgets.length > 24) throw new Error('This file is not a supported dashboard template.');
        var widgets = $.map(definition.widgets, function(item) {
          var dataset = datasetFor(item.dataset);
          if (!dataset || !datasets[item.dataset] || !chartTypes[item.chart] || !fieldFor(dataset, 'measures', item.measure) || (item.chart !== 'kpi' && !fieldFor(dataset, 'dimensions', item.dimension))) throw new Error('The template uses a dataset or field that is not available.');
          return makeWidget(item);
        });
        confirmDiscard(function() {
          state.currentId = 0;
          state.canEdit = true;
          state.owner = config.currentUserName || 'You';
          state.widgets = widgets;
          state.selectedId = '';
          $('#studioDashboardName').val(String(payload.name || 'Imported dashboard').slice(0, 120));
          $('#studioShareDashboard').prop('checked', payload.is_shared === true);
          $('#studioDashboardPicker').val('');
          updateOwnership();
          markDirty();
          renderGrid();
          replaceDashboardUrl(0);
          toastr.success('Dashboard template imported. Review it, then save.');
        });
      } catch (error) {
        toastr.error(error.message || 'The dashboard JSON could not be imported.');
      }
    };
    reader.readAsText(file);
  }

  function togglePresentation() {
    var shell = document.querySelector('.studio-shell');
    if (document.fullscreenElement && document.exitFullscreen) {
      document.exitFullscreen();
    } else if (shell && shell.requestFullscreen) {
      shell.requestFullscreen().catch(function() { document.body.classList.toggle('studio-presentation'); syncPresentationState(); });
    } else {
      document.body.classList.toggle('studio-presentation');
      syncPresentationState();
    }
  }

  function syncPresentationState() {
    var active = !!document.fullscreenElement || document.body.classList.contains('studio-presentation');
    $('.studio-shell').toggleClass('is-presenting', active);
    $('#studioPresentation i').attr('class', active ? 'fa fa-compress' : 'fa fa-expand');
  }

  function applyInspector() {
    var widget = widgetById(state.selectedId);
    if (!widget) return;
    var dataset = datasetFor($('#studioInspectorDataset').val());
    var chart = $('#studioInspectorCharts button.is-active').data('chart') || widget.chart;
    widget.title = $.trim($('#studioWidgetTitle').val()) || dataset.name;
    widget.dataset = dataset.key;
    widget.chart = chart;
    widget.dimension = chart === 'kpi' ? '' : ($('#studioInspectorDimension').val() || dataset.default_dimension);
    widget.measure = $('#studioInspectorMeasure').val() || dataset.default_measure;
    widget.date_range = $('#studioInspectorRange').val();
    widget.environment = $('#studioInspectorEnvironment').val();
    widget.size = parseInt($('#studioSizePicker button.is-active').data('size'), 10) || 6;
    widget.palette = $('#studioPalettePicker button.is-active').data('palette') || 'aurora';
    markDirty();
    renderGrid();
  }

  function removeWidget(id) {
    state.widgets = $.grep(state.widgets, function(widget) { return widget.id !== id; });
    if (state.selectedId === id) state.selectedId = '';
    markDirty();
    renderGrid();
  }

  function duplicateWidget(id) {
    var widget = widgetById(id);
    if (!widget) return;
    var copy = $.extend({}, widget, { id: uid(), title: widget.title + ' copy' });
    var index = state.widgets.indexOf(widget);
    state.widgets.splice(index + 1, 0, copy);
    state.selectedId = copy.id;
    markDirty();
    renderGrid();
  }

  function addRecipeWidget() {
    var dataset = datasetFor(state.recipe.dataset);
    if (!state.recipe.measure || (state.recipe.chart !== 'kpi' && !state.recipe.dimension)) {
      toastr.warning('Drop a category and value into the visual recipe first.');
      return;
    }
    var widget = makeWidget({ dataset: dataset.key, dimension: state.recipe.dimension, measure: state.recipe.measure, chart: state.recipe.chart });
    state.widgets.push(widget);
    state.selectedId = widget.id;
    markDirty();
    renderGrid();
  }

  function useDataset(key) {
    var dataset = datasetFor(key);
    state.recipe.dataset = dataset.key;
    state.recipe.dimension = dataset.default_dimension;
    state.recipe.measure = dataset.default_measure;
    renderDatasets();
  }

  function useDroppedField(payload, expected) {
    if (!payload || (expected && payload.kind !== expected)) {
      toastr.info(expected === 'measure' ? 'Drop a numeric measure here.' : 'Drop a dimension or time field here.');
      return;
    }
    if (payload.dataset && payload.dataset !== state.recipe.dataset) useDataset(payload.dataset);
    state.recipe[payload.kind] = payload.field;
    updateRecipe();
  }

  function updateOwnership() {
    var text = state.currentId ? (state.canEdit ? 'Owned by you' : 'Shared by ' + state.owner + ' · opens read-only') : 'Unsaved dashboard';
    $('#studioOwnership').text(text);
    $('#studioDeleteDashboard').prop('disabled', !state.currentId || !state.canEdit);
    $('#studioSaveDashboard span').text(state.currentId && !state.canEdit ? 'Save a copy' : 'Save dashboard');
    $('#studioShareDashboard').prop('disabled', false);
  }

  function resetDashboard(useStarter) {
    state.currentId = 0;
    state.canEdit = true;
    state.owner = config.currentUserName || 'You';
    state.selectedId = '';
    state.widgets = useStarter ? starterWidgets() : [];
    $('#studioDashboardName').val(useStarter ? 'Operations pulse' : 'Untitled dashboard');
    $('#studioShareDashboard').prop('checked', false).prop('disabled', false);
    $('#studioDashboardPicker').val('');
    updateOwnership();
    setDirty(false);
    renderGrid();
    replaceDashboardUrl(0);
  }

  function openDashboard(id) {
    if (!id) return;
    $.getJSON(config.endpoints.dashboard + encodeURIComponent(id)).done(function(payload) {
      var dashboard = payload.data;
      state.currentId = parseInt(dashboard.id, 10);
      state.canEdit = dashboard.can_edit === true;
      state.owner = dashboard.owner;
      state.widgets = $.map((dashboard.definition && dashboard.definition.widgets) || [], function(widget) { return makeWidget(widget); });
      state.selectedId = '';
      $('#studioDashboardName').val(dashboard.name);
      $('#studioShareDashboard').prop('checked', parseInt(dashboard.is_shared, 10) === 1);
      $('#studioDashboardPicker').val(String(dashboard.id));
      updateOwnership();
      setDirty(false);
      renderGrid();
      replaceDashboardUrl(dashboard.id);
    }).fail(function(xhr) {
      toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Dashboard could not be opened.');
    });
  }

  function saveDashboard() {
    var name = $.trim($('#studioDashboardName').val());
    if (!name) { toastr.warning('Give this dashboard a name before saving.'); $('#studioDashboardName').focus(); return; }
    var saveAsCopy = state.currentId && !state.canEdit;
    var saveId = saveAsCopy ? 0 : state.currentId;
    if (saveAsCopy && !/ copy$/i.test(name)) name += ' copy';
    $('#studioSaveDashboard').prop('disabled', true).find('i').attr('class', 'fa fa-circle-o-notch fa-spin');
    $.post(config.endpoints.save, {
      id: saveId,
      name: name,
      description: 'Built in Insight Studio with ' + state.widgets.length + ' visual' + (state.widgets.length === 1 ? '.' : 's.'),
      definition: JSON.stringify({ version: 1, widgets: state.widgets }),
      is_shared: $('#studioShareDashboard').is(':checked') ? 1 : 0
    }).done(function(payload) {
      if (typeof payload === 'string') { try { payload = JSON.parse(payload); } catch (ignore) {} }
      if (!payload || !payload.status) { toastr.error(payload && payload.message ? payload.message : 'Dashboard could not be saved.'); return; }
      state.currentId = parseInt(payload.id, 10);
      state.canEdit = true;
      state.owner = config.currentUserName || 'You';
      $('#studioDashboardName').val(name);
      var $option = $('#studioDashboardPicker option[value="' + state.currentId + '"]');
      if (!$option.length) $option = $('<option>', { value: state.currentId }).appendTo('#studioDashboardPicker');
      $option.text(name);
      $('#studioDashboardPicker').val(String(state.currentId));
      setDirty(false);
      updateOwnership();
      replaceDashboardUrl(state.currentId);
      toastr.success('Dashboard saved.');
    }).fail(function(xhr) {
      toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Dashboard could not be saved.');
    }).always(function() {
      $('#studioSaveDashboard').prop('disabled', false).find('i').attr('class', 'fa fa-cloud-upload');
    });
  }

  function deleteCurrentDashboard() {
    if (!state.currentId || !state.canEdit) return;
    alertify.confirm('Delete dashboard', 'Delete this saved dashboard? This cannot be undone.', function() {
      var id = state.currentId;
      $.post(config.endpoints.delete, { id: id }).done(function(payload) {
        if (typeof payload === 'string') { try { payload = JSON.parse(payload); } catch (ignore) {} }
        if (payload && payload.status) {
          $('#studioDashboardPicker option[value="' + id + '"]').remove();
          resetDashboard(true);
          toastr.success('Dashboard deleted.');
        } else toastr.error(payload && payload.message ? payload.message : 'Dashboard could not be deleted.');
      }).fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Dashboard could not be deleted.'); });
    }, function() {});
  }

  function replaceDashboardUrl(id) {
    if (!window.history || !window.history.replaceState) return;
    var url = new URL(window.location.href);
    if (id) url.searchParams.set('dashboard', id); else url.searchParams.delete('dashboard');
    window.history.replaceState({}, '', url.toString());
  }

  function confirmDiscard(action) {
    if (!state.dirty) { action(); return; }
    alertify.confirm('Discard unsaved changes?', 'This dashboard has changes that have not been saved.', action, function() {});
  }

  function fitDataPanel() {
    var $panel = $('#studioDataPanel');
    if (window.innerWidth > 1180) {
      $panel.css('max-height', '');
      return;
    }
    if (!$panel.hasClass('is-visible')) return;
    var top = $panel[0].getBoundingClientRect().top;
    $panel.css('max-height', Math.max(260, window.innerHeight - top - 14) + 'px');
  }

  function bindEvents() {
    if ($.fn.sortable) {
      $('#studioGrid').sortable({ items: '.studio-widget', handle: '.studio-widget-grip', placeholder: 'studio-widget-placeholder', tolerance: 'pointer', start: function(_, ui) {
        ui.placeholder.attr('data-size', ui.item.attr('data-size')).height(ui.item.outerHeight());
      }, stop: function() {
        var order = $('#studioGrid .studio-widget').map(function() { return $(this).data('widget-id'); }).get();
        state.widgets.sort(function(a, b) { return order.indexOf(a.id) - order.indexOf(b.id); });
        markDirty();
      } });
    }

    $(document).on('click', '.studio-dataset', function() { useDataset($(this).data('dataset')); });
    $(document).on('dragstart', '.studio-dataset', function(event) { event.originalEvent.dataTransfer.setData('text/plain', JSON.stringify({ kind: 'dataset', dataset: $(this).data('dataset') })); });
    $(document).on('dragstart', '.studio-field', function(event) { event.originalEvent.dataTransfer.effectAllowed = 'copy'; event.originalEvent.dataTransfer.setData('text/plain', JSON.stringify({ kind: $(this).data('kind'), field: $(this).data('field'), dataset: $(this).data('dataset') })); });
    $(document).on('click', '.studio-field', function() { useDroppedField({ kind: $(this).data('kind'), field: $(this).data('field'), dataset: $(this).data('dataset') }, $(this).data('kind')); });
    $('.studio-dropzone').on('dragover', function(event) { event.preventDefault(); $(this).addClass('is-over'); }).on('dragleave', function() { $(this).removeClass('is-over'); }).on('drop', function(event) {
      event.preventDefault(); $(this).removeClass('is-over');
      var payload = null; try { payload = JSON.parse(event.originalEvent.dataTransfer.getData('text/plain')); } catch (ignore) {}
      if ($(this).is('#studioRecipeDataset') && payload && payload.kind === 'dataset') useDataset(payload.dataset);
      else useDroppedField(payload, $(this).data('accept'));
    });

    $('#studioChartTypes').on('click', 'button', function() { state.recipe.chart = $(this).data('chart'); updateRecipe(); });
    $('#studioAddWidget').on('click', addRecipeWidget);
    $('#studioGrid').on('click', '.studio-widget', function(event) { if ($(event.target).closest('button').length) return; selectWidget($(this).data('widget-id')); });
    $('#studioGrid').on('click', '.studio-edit-widget', function() { selectWidget($(this).closest('.studio-widget').data('widget-id')); });
    $('#studioGrid').on('click', '.studio-remove-widget', function() { removeWidget($(this).closest('.studio-widget').data('widget-id')); });
    $('#studioGrid').on('click', '.studio-duplicate-widget', function() { duplicateWidget($(this).closest('.studio-widget').data('widget-id')); });
    $('#studioInspectorCharts').on('click', 'button', function() { $('#studioInspectorCharts button').removeClass('is-active'); $(this).addClass('is-active'); populateInspectorFields(widgetById(state.selectedId)); });
    $('#studioInspectorDataset').on('change', function() { populateInspectorFields(widgetById(state.selectedId)); });
    $('#studioSizePicker').on('click', 'button', function() { $('#studioSizePicker button').removeClass('is-active'); $(this).addClass('is-active'); });
    $('#studioPalettePicker').on('click', 'button', function() { $('#studioPalettePicker button').removeClass('is-active'); $(this).addClass('is-active'); });
    $('#studioApplyWidget').on('click', applyInspector);
    $('#studioRemoveWidget').on('click', function() { removeWidget(state.selectedId); });
    $('#studioDashboardName').on('input', markDirty);
    $('#studioShareDashboard').on('change', markDirty);
    $('#studioSaveDashboard').on('click', saveDashboard);
    $('#studioDeleteDashboard').on('click', deleteCurrentDashboard);
    $('#studioRefresh').on('click', function() { renderGrid(); toastr.info('Live data refreshed.'); });
    $('#studioFieldSearch').on('input', filterDataLibrary);
    $('#studioApplyGlobalFilters').on('click', applyGlobalFilters);
    $('#studioExport').on('click', exportDashboard);
    $('#studioImport').on('click', function() { $('#studioImportFile').val('').trigger('click'); });
    $('#studioImportFile').on('change', function() { importDashboard(this.files && this.files[0]); });
    $('#studioPresentation').on('click', togglePresentation);
    $(document).on('fullscreenchange', syncPresentationState);
    $('#studioNewDashboard').on('click', function() { confirmDiscard(function() { resetDashboard(false); }); });
    $('#studioUseStarter').on('click', function() { state.widgets = starterWidgets(); markDirty(); renderGrid(); });
    $('#studioClearCanvas').on('click', function() { if (!state.widgets.length) return; alertify.confirm('Clear canvas', 'Remove every visual from this canvas?', function() { state.widgets = []; state.selectedId = ''; markDirty(); renderGrid(); }, function() {}); });
    $('#studioDashboardPicker').on('change', function() { var id = parseInt($(this).val(), 10); if (id) confirmDiscard(function() { openDashboard(id); }); });
    $('#studioDataToggle').on('click', function() {
      var $panel = $('#studioDataPanel');
      $panel.toggleClass('is-visible');
      if ($panel.hasClass('is-visible')) window.requestAnimationFrame(fitDataPanel);
    });
    $('#studioCloseData').on('click', function() { $('#studioDataPanel').removeClass('is-visible'); });
    $('#studioCloseInspector').on('click', function() { $('#studioInspector').removeClass('is-visible'); });
    $(window).on('resize', fitDataPanel);
    $(window).on('beforeunload', function() { if (state.dirty) return 'You have unsaved dashboard changes.'; });
  }

  function init() {
    if (!Object.keys(datasets).length) {
      $('#studioGrid').html('<div class="studio-widget-empty">No governed datasets are configured.</div>');
      return;
    }
    if (!datasets[state.recipe.dataset]) {
      var first = (config.datasets || [])[0];
      state.recipe.dataset = first.key;
      state.recipe.dimension = first.default_dimension;
      state.recipe.measure = first.default_measure;
    }
    populateEnvironments();
    renderDatasets();
    renderChartButtons('#studioChartTypes', state.recipe.chart);
    bindEvents();
    updateOwnership();
    if (config.initialDashboardId) openDashboard(config.initialDashboardId);
    else resetDashboard(true);
  }

  function boot() {
    try {
      init();
      $('.studio-page').attr('data-studio-ready', 'true');
    } catch (error) {
      if (window.console && console.error) console.error('Insight Studio failed to initialize.', error);
      $('#studioRuntimeNotice').show().find('span').text(error && error.message ? error.message : 'The Studio could not initialize.');
    }
  }

  $(boot);
})(jQuery);
