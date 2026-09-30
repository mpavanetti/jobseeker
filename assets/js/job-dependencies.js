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
    return $('<div>').text(value == null ? '' : String(value)).html().replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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

  function renderChip(item, environment, job) {
    var light = LIGHT[item.lightStatus] || LIGHT.unknown;
    var kindIcon = item.kind === 'dataset' ? 'fa-table' : 'fa-plug';
    var meta = item.kind === 'dataset'
      ? (item.type ? escapeHtml(item.type) : 'data asset')
      : (item.type ? escapeHtml(item.type) : 'connector');
    var preview = item.kind === 'dataset' && item.refId && item.lightStatus === 'ok'
      ? '<button type="button" class="jd-preview-asset" data-asset-id="' + Number(item.refId) + '" data-asset-name="' + escapeHtml(item.key) + '" data-environment="' + escapeHtml(environment) + '" data-job="' + escapeHtml(job || '') + '" title="Preview this Data Asset"><i class="fa fa-eye"></i><span class="sr-only">Preview</span></button>'
      : '';
    return '' +
      '<span class="jd-chip ' + light.cls + '" title="' + escapeHtml(light.label) + '">' +
        '<i class="fa ' + kindIcon + '"></i>' +
        '<a href="' + escapeHtml(linkFor(item, environment)) + '" target="_blank" rel="noopener">' + escapeHtml(item.key) + '</a>' +
        '<small>' + meta + '</small>' +
        '<i class="fa ' + light.icon + ' jd-light-icon"></i>' +
        testedBadge(item) +
        preview +
      '</span>';
  }

  // The job lets a shared asset use a job-scoped Connection, as in a run.
  function previewUrl(item, environment, job) {
    return base() + 'data-assets/preview/' + encodeURIComponent(item.refId || item.id) +
      '?environment=' + encodeURIComponent(environment || 'ALL') + (job ? '&job=' + encodeURIComponent(job) : '');
  }

  function humanFileSize(bytes) {
    bytes = Number(bytes || 0);
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  function previewHtml(payload) {
    if (payload.kind === 'table') {
      var head = (payload.columns || []).map(function(column) { return '<th>' + escapeHtml(column) + '</th>'; }).join('');
      var rows = (payload.rows || []).map(function(row) {
        return '<tr>' + (row || []).map(function(cell) { return '<td>' + escapeHtml(cell) + '</td>'; }).join('') + '</tr>';
      }).join('');
      return '<div class="table-responsive jd-preview-scroll"><table class="table table-bordered table-striped table-condensed"><thead><tr>' + head + '</tr></thead><tbody>' + rows + '</tbody></table></div>' +
        (!(payload.rows || []).length ? '<p class="text-muted">The source contains no rows.</p>' : '');
    }
    return '<pre class="jd-preview-text">' + escapeHtml(payload.text || '') + '</pre>';
  }

  function previewMeta(payload) {
    var parts = [payload.file_name || payload.name || '', String(payload.format || '').toUpperCase(), humanFileSize(payload.size)];
    if (payload.source_label) parts.push(payload.source_label);
    if (payload.total_rows) parts.push(Number(payload.total_rows).toLocaleString() + ' rows');
    if (payload.detail) parts.push(payload.detail);
    if (payload.truncated) parts.push('first sample shown');
    return parts.filter(Boolean).map(escapeHtml).join(' &middot; ');
  }

  function ensurePreviewModal() {
    if ($('#jobDataAssetPreviewModal').length) return;
    $('body').append(
      '<div class="modal fade" id="jobDataAssetPreviewModal" tabindex="-1" role="dialog" aria-labelledby="jobDataAssetPreviewTitle">' +
        '<div class="modal-dialog modal-lg" role="document"><div class="modal-content">' +
          '<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button><h4 id="jobDataAssetPreviewTitle" class="modal-title"><i class="fa fa-eye"></i> Data Asset preview</h4></div>' +
          '<div class="modal-body"><div class="jd-preview-loading"><i class="fa fa-refresh fa-spin"></i> Reading a bounded sample&hellip;</div><div class="alert alert-warning jd-preview-error" style="display:none"></div><div class="jd-preview-content" style="display:none"><p class="jd-preview-meta"></p><div class="jd-preview-body"></div></div></div>' +
          '<div class="modal-footer"><span class="pull-left text-muted"><i class="fa fa-shield"></i> Read-only, bounded preview. Connection secrets stay server-side.</span><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>' +
        '</div></div>' +
      '</div>'
    );
  }

  function openPreview(item, environment, job) {
    ensurePreviewModal();
    var $modal = $('#jobDataAssetPreviewModal');
    $modal.find('.modal-title').html('<i class="fa fa-eye"></i> ' + escapeHtml(item.key || item.name || 'Data Asset'));
    $modal.find('.jd-preview-loading').show();
    $modal.find('.jd-preview-error, .jd-preview-content').hide();
    $modal.modal('show');
    $.getJSON(previewUrl(item, environment, job)).done(function(payload) {
      if (!payload || !payload.ok) {
        $modal.find('.jd-preview-error').text((payload && payload.message) || 'The Data Asset could not be previewed.').show();
        return;
      }
      $modal.find('.jd-preview-meta').html(previewMeta(payload));
      $modal.find('.jd-preview-body').html(previewHtml(payload));
      $modal.find('.jd-preview-content').show();
    }).fail(function(xhr) {
      $modal.find('.jd-preview-error').text((xhr.responseJSON && xhr.responseJSON.message) || 'The Data Asset could not be previewed.').show();
    }).always(function() { $modal.find('.jd-preview-loading').hide(); });
  }

  function loadInlineAssets($host, datasets, environment, job) {
    var $body = $host.find('.jd-inline-assets-body').empty().show();
    var previewable = datasets.filter(function(item) { return item.refId && item.lightStatus === 'ok'; });
    if (!previewable.length) {
      $body.html('<p class="text-muted jd-empty">No in-scope Data Assets can be previewed.</p>');
      return;
    }
    previewable.forEach(function(item) {
      var $card = $('<div class="jd-inline-asset"><div class="jd-inline-asset-title"><strong></strong><span class="text-muted">Loading…</span></div><div class="jd-inline-asset-body"></div></div>');
      $card.find('strong').text(item.key);
      $body.append($card);
      $.getJSON(previewUrl(item, environment, job)).done(function(payload) {
        if (!payload || !payload.ok) {
          $card.find('.jd-inline-asset-body').html('<p class="text-warning"></p>').find('p').text((payload && payload.message) || 'Preview unavailable.');
          return;
        }
        $card.find('.jd-inline-asset-title span').html(previewMeta(payload));
        $card.find('.jd-inline-asset-body').html(previewHtml(payload));
      }).fail(function(xhr) {
        $card.find('.jd-inline-asset-body').html('<p class="text-warning"></p>').find('p').text((xhr.responseJSON && xhr.responseJSON.message) || 'Preview unavailable.');
      });
    });
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
    var job = options.job || (data && data.job) || '';
    var commandSafety = (data && data.commandSafety) || null;
    var guardHtml = options.showWarnings === false ? '' : renderCommandSafety(commandSafety);

    if (!connectors.length && !datasets.length) {
      var emptyHtml = '<p class="jd-empty text-muted">No connectors or datasets are referenced in this job’s code.</p>';
      $box.html(emptyHtml + guardHtml);
      return;
    }

    var html = '';
    if (connectors.length) {
      html += '<div class="jd-group"><span class="jd-group-label">Connectors</span><div class="jd-chips">' +
        connectors.map(function(item) { return renderChip(item, environment, job); }).join('') + '</div></div>';
    }
    if (datasets.length) {
      html += '<div class="jd-group"><span class="jd-group-label">Datasets</span><div class="jd-chips">' +
        datasets.map(function(item) { return renderChip(item, environment, job); }).join('') + '</div></div>';
      if (options.inlineAssets) {
        html += '<div class="jd-inline-assets"><button type="button" class="btn btn-default btn-xs jd-toggle-inline-assets"><i class="fa fa-table"></i> Show data above logs</button><div class="jd-inline-assets-body" style="display:none"></div></div>';
      }
    }
    var warnings = (data && data.warnings) || [];
    if (warnings.length && options.showWarnings !== false) {
      html += '<ul class="jd-warnings">' + warnings.map(function(w) { return '<li><i class="fa fa-exclamation-triangle"></i> ' + escapeHtml(w) + '</li>'; }).join('') + '</ul>';
    }
    $box.html(html + guardHtml);
    $box.data('jobseeker-datasets', datasets).data('jobseeker-environment', environment).data('jobseeker-job', job);
  }

  function renderCommandSafety(commandSafety) {
    var findings = (commandSafety && commandSafety.findings) || [];
    if (!findings.length) {
      return '';
    }
    var enforced = !!(commandSafety && commandSafety.enforced);
    var blocking = findings.filter(function(f) { return f.severity === 'critical' || f.severity === 'high'; }).length;
    var headline = enforced && blocking
      ? blocking + ' command pattern' + (blocking === 1 ? '' : 's') + ' will block this job from being created'
      : findings.length + ' risky command pattern' + (findings.length === 1 ? '' : 's') + ' detected — review before creating this job';
    var items = findings.map(function(f) {
      var sev = f.severity === 'medium' ? 'jd-warn' : 'jd-bad';
      return '<li class="' + sev + '">' +
        '<i class="fa fa-exclamation-triangle"></i> ' +
        '<strong>' + escapeHtml(f.title) + '</strong>' +
        (f.source ? ' <small>(' + escapeHtml(f.source) + ')</small>' : '') +
        '<br><code>' + escapeHtml(f.snippet) + '</code>' +
        '<br><span class="text-muted">' + escapeHtml(f.detail) + '</span>' +
      '</li>';
    }).join('');
    return '<div class="jd-command-guard' + (enforced && blocking ? ' jd-command-guard-blocking' : '') + '">' +
      '<span class="jd-group-label"><i class="fa fa-shield"></i> Command safety</span>' +
      '<p class="jd-command-guard-headline">' + escapeHtml(headline) + '</p>' +
      '<ul class="jd-warnings">' + items + '</ul>' +
    '</div>';
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

  $(document).on('click', '.jd-preview-asset', function(event) {
    event.preventDefault();
    event.stopPropagation();
    openPreview({refId: $(this).data('asset-id'), key: $(this).data('asset-name')}, $(this).data('environment'), $(this).data('job'));
  });

  $(document).on('click', '.jd-toggle-inline-assets', function() {
    var $button = $(this);
    var $host = $button.closest('.job-dependency-panel');
    var $body = $host.find('.jd-inline-assets-body');
    if ($body.is(':visible')) {
      $body.slideUp(120);
      $button.html('<i class="fa fa-table"></i> Show data above logs');
      return;
    }
    $button.html('<i class="fa fa-chevron-up"></i> Hide data');
    if (!$body.children().length) loadInlineAssets($host, $host.data('jobseeker-datasets') || [], $host.data('jobseeker-environment') || '', $host.data('jobseeker-job') || '');
    else $body.slideDown(120);
  });
})(window, jQuery);
