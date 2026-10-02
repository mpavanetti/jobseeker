/*
 * Workspace Runtimes page (application/views/workspaceRuntimes.php): the
 * catalog of runtimes, the template gallery, deployments, and the runtime
 * editor with its VS Code-style files (assets/js/code-editor.js).
 */
(function($) {
  'use strict';

  var config = window.jobseekerRuntimes || {urls: {}, kinds: {}, enabled: false};
  var urls = config.urls;
  var kinds = config.kinds || {};
  var statusLabels = {ready: 'Built', building: 'Building', failed: 'Failed', missing: 'Image missing', none: 'Not built', outdated: 'Editor update', unknown: 'Unknown'};
  var catalog = {runtimes: [], templates: [], categories: {}, deployments: [], usage: {}};
  var filters = {category: 'all', search: ''};
  var refreshTimer = null;
  var logTimer = null;
  var previewTimer = null;
  var previewRequest = null;
  var codeEditor = null;
  var editing = null;
  var condaTemplate = 'channels:\n  - conda-forge\ndependencies:\n  - python=3.12\n  - pip\n  - pandas\n  - pyarrow\n';
  var dockerfileTemplate = '# Base it on a glibc Linux (Debian, Ubuntu, RHEL); JobSeeker adds the editor on top.\nFROM python:3.12-slim\nRUN apt-get update \\\n    && apt-get install -y --no-install-recommends git curl \\\n    && rm -rf /var/lib/apt/lists/*\nRUN pip install --no-cache-dir pandas\n';

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function(character) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
    });
  }

  function when(value) {
    if (!value) {
      return '—';
    }
    var iso = String(value).replace(' ', 'T') + 'Z';
    return window.JobSeekerTime ? JobSeekerTime.tag(iso, {relative: true}) : escapeHtml(value + ' UTC');
  }

  function message(xhr, fallback) {
    return (xhr && xhr.responseJSON && xhr.responseJSON.message) || fallback;
  }

  function formatBytes(bytes) {
    var units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var index = 0;
    while (bytes >= 1024 && index < units.length - 1) {
      bytes /= 1024;
      index++;
    }
    return (index === 0 ? bytes : bytes.toFixed(1)) + ' ' + units[index];
  }

  function applyTime(root) {
    if (window.JobSeekerTime && root) {
      JobSeekerTime.apply(root);
    }
  }

  function runtimeByKey(key) {
    return $.grep(catalog.runtimes, function(runtime) { return runtime.key === key; })[0] || null;
  }

  function templateByKey(key) {
    return $.grep(catalog.templates, function(template) { return template.key === key; })[0] || null;
  }

  function categoryLabel(key) {
    return catalog.categories[key] ? catalog.categories[key].label : key;
  }

  function kindDetail(kind, spec) {
    spec = spec || {};
    if (kind === 'python') {
      return 'Python ' + escapeHtml(spec.python_version || '');
    }
    if (kind === 'conda') {
      var python = /python\s*=\s*([0-9.]+)/.exec(spec.environment_yml || '');
      return 'Conda' + (python ? ' · Python ' + escapeHtml(python[1]) : '');
    }
    var from = /^\s*FROM\s+(?:--platform=\S+\s+)?(\S+)/mi.exec(spec.dockerfile || '');
    return 'Dockerfile' + (from ? ' · ' + escapeHtml(from[1]) : '');
  }

  function chips(names, limit) {
    names = names || [];
    var shown = names.slice(0, limit);
    return '<div class="rt-chips-inline">' + $.map(shown, function(name) { return '<span class="rt-pkg">' + escapeHtml(name) + '</span>'; }).join('')
      + (names.length > limit ? '<span class="rt-pkg is-more">+' + (names.length - limit) + '</span>' : '') + '</div>';
  }

  function facts(spec, projects) {
    spec = spec || {};
    var items = [];
    if ((spec.extensions || []).length) {
      items.push('<span title="' + escapeHtml(spec.extensions.join(', ')) + '"><i class="fa fa-puzzle-piece"></i>' + spec.extensions.length + ' extension' + (spec.extensions.length === 1 ? '' : 's') + '</span>');
    }
    if ((spec.features || []).length) {
      items.push('<span title="' + escapeHtml($.map(spec.features, function(feature) { return feature.ref; }).join(', ')) + '"><i class="fa fa-cubes"></i>' + spec.features.length + ' feature' + (spec.features.length === 1 ? '' : 's') + '</span>');
    }
    if ((spec.ports || []).length) {
      items.push('<span><i class="fa fa-plug"></i>' + escapeHtml(spec.ports.join(', ')) + '</span>');
    }
    if (projects !== undefined) {
      items.push('<span><i class="fa fa-folder-open-o"></i>' + projects + ' project' + (projects === 1 ? '' : 's') + '</span>');
    }
    return items.length ? '<div class="rt-card-facts">' + items.join('') + '</div>' : '';
  }

  function statusPill(status) {
    return '<span class="rt-status is-' + escapeHtml(status) + '">' + (status === 'building' ? '<i class="fa fa-circle-o-notch fa-spin"></i>' : '') + escapeHtml(statusLabels[status] || status) + '</span>';
  }

  function builtinCards() {
    var usage = catalog.usage || {};
    return '<article class="rt-card is-builtin"><div class="rt-card-body"><div class="rt-card-head"><span class="rt-tile rt-cat-builtin"><i class="fa fa-desktop"></i></span>'
      + '<div class="rt-card-title"><h4>Default editor</h4><div class="rt-card-meta">Built in · shared OpenVSCode</div></div><span class="rt-status is-ready">Ready</span></div>'
      + '<p class="rt-card-description">Python with uv, Poetry, Ruff and the SDK, shared by everyone. Every project starts here, and small projects never need more.</p></div></article>'
      + '<article class="rt-card is-builtin"><div class="rt-card-body"><div class="rt-card-head"><span class="rt-tile rt-cat-builtin"><i class="fa fa-cube"></i></span>'
      + '<div class="rt-card-title"><h4>Project dev container</h4><div class="rt-card-meta">Built in · .devcontainer/devcontainer.json</div></div></div>'
      + '<p class="rt-card-description">A project brings its own dev container, features included, committed with its code. Start one from any runtime or template with <strong>.devcontainer</strong>, or from the launcher.</p>'
      + '<div class="rt-card-facts"><span><i class="fa fa-folder-open-o"></i>' + (usage.devcontainer || 0) + ' project' + (usage.devcontainer === 1 ? '' : 's') + '</span></div></div></article>';
  }

  function runtimeCard(runtime) {
    var build = runtime.build || {};
    var status = build.status || 'unknown';
    var building = status === 'building';
    var html = '<article class="rt-card" data-key="' + escapeHtml(runtime.key) + '"><div class="rt-card-body">'
      + '<div class="rt-card-head"><span class="rt-tile rt-cat-' + escapeHtml(runtime.category) + '"><i class="fa ' + escapeHtml(runtime.icon) + '"></i></span>'
      + '<div class="rt-card-title"><h4>' + escapeHtml(runtime.name) + '</h4><div class="rt-card-meta"><code>' + escapeHtml(runtime.key) + '</code> · ' + kindDetail(runtime.kind, runtime.spec) + '</div></div>'
      + statusPill(status) + '</div>'
      + (runtime.description ? '<p class="rt-card-description">' + escapeHtml(runtime.description) + '</p>' : '')
      + ((runtime.highlights || []).length ? chips(runtime.highlights, 6) : '')
      + facts(runtime.spec, runtime.projects)
      + '<div class="rt-card-image" title="Image jobs run">' + escapeHtml(runtime.image) + (status === 'ready' && build.finishedAt ? ' · built ' + when(build.finishedAt) : '') + '</div>'
      + (status === 'failed' && build.message ? '<div class="rt-card-error"><i class="fa fa-exclamation-circle"></i> ' + escapeHtml(build.message) + '</div>' : '')
      + (status === 'outdated' ? '<div class="rt-card-warning"><i class="fa fa-info-circle"></i> JobSeeker\'s editor layer changed; it is added again in seconds on the next open.</div>' : '')
      + $.map(runtime.warnings || [], function(warning) { return '<div class="rt-card-warning"><i class="fa fa-info-circle"></i> ' + escapeHtml(warning) + '</div>'; }).join('')
      + '</div><div class="rt-card-foot"><div class="rt-card-actions">'
      + '<button type="button" class="btn btn-default btn-sm" data-action="build"' + (building || !config.enabled ? ' disabled' : '') + ' title="' + (status === 'ready' ? 'Build again with newer base images and packages' : 'Build the runtime and its editor image') + '"><i class="fa fa-gavel"></i> ' + (status === 'ready' ? 'Rebuild' : 'Build') + '</button>'
      + '<button type="button" class="btn btn-default btn-sm" data-action="edit"><i class="fa fa-' + (runtime.canEdit ? 'pencil' : 'eye') + '"></i> ' + (runtime.canEdit ? 'Edit' : 'View') + '</button>'
      + '<button type="button" class="btn btn-default btn-sm" data-action="log"' + (status === 'none' || status === 'unknown' || status === 'outdated' ? ' disabled' : '') + ' title="Build log"><i class="fa fa-file-text-o"></i></button>'
      + '</div><div class="rt-card-actions">'
      + '<a class="btn btn-default btn-sm" href="' + escapeHtml(urls.devcontainer + '?key=' + encodeURIComponent(runtime.key)) + '" title="Download as a .devcontainer for VS Code or Codespaces"><i class="fa fa-download"></i> .devcontainer</a>'
      + '<button type="button" class="btn btn-default btn-sm" data-action="duplicate" title="Start a new runtime from this one"' + (!config.enabled ? ' disabled' : '') + '><i class="fa fa-clone"></i></button>'
      + (runtime.canEdit ? '<button type="button" class="btn btn-default btn-sm text-danger" data-action="delete"' + (runtime.projects ? ' disabled title="Used by projects"' : ' title="Delete"') + '><i class="fa fa-trash"></i></button>' : '')
      + '</div></div></article>';
    return html;
  }

  function templateCard(template) {
    var inCatalog = template.inCatalog || [];
    return '<article class="rt-card" data-template="' + escapeHtml(template.key) + '"><div class="rt-card-body">'
      + '<div class="rt-card-head"><span class="rt-tile rt-cat-' + escapeHtml(template.category) + '"><i class="fa ' + escapeHtml(template.icon) + '"></i></span>'
      + '<div class="rt-card-title"><h4>' + escapeHtml(template.name) + '</h4><div class="rt-card-meta">' + escapeHtml(categoryLabel(template.category)) + ' · ' + kindDetail(template.kind, template.draft.spec) + '</div></div>'
      + (inCatalog.length ? '<span class="rt-status is-catalog" title="' + escapeHtml(inCatalog.join(', ')) + '">In catalog</span>' : '') + '</div>'
      + '<p class="rt-card-description">' + escapeHtml(template.description) + '</p>'
      + chips(template.highlights, 8)
      + facts(template.draft.spec)
      + '</div><div class="rt-card-foot"><div class="rt-card-actions">'
      + '<button type="button" class="btn rt-btn-primary btn-sm" data-action="use"' + (!config.enabled ? ' disabled' : '') + '><i class="fa fa-plus"></i> Use template</button></div>'
      + '<div class="rt-card-actions"><a class="btn btn-default btn-sm" href="' + escapeHtml(urls.devcontainer + '?template=' + encodeURIComponent(template.key)) + '" title="Download as a .devcontainer for VS Code or Codespaces"><i class="fa fa-download"></i> .devcontainer</a></div>'
      + '</div></article>';
  }

  function renderStats() {
    var built = $.grep(catalog.runtimes, function(runtime) { return runtime.build && runtime.build.status === 'ready'; }).length;
    var running = $.grep(catalog.deployments, function(deployment) { return deployment.state === 'running'; }).length;
    $('#rtStatRuntimes').text(catalog.runtimes.length);
    $('#rtStatBuilt').text(built);
    $('#rtStatRunning').text(running);
    $('#rtStatTemplates').text(catalog.templates.length);
    $('#rtCountCatalog').text(catalog.runtimes.length);
    $('#rtCountTemplates').text(catalog.templates.length);
    $('#rtCountDeployments').text(catalog.deployments.length);
  }

  function renderCatalog() {
    $('#runtimeCatalog').html(builtinCards() + $.map(catalog.runtimes, runtimeCard).join('')
      + (catalog.runtimes.length ? '' : '<div class="rt-empty"><i class="fa fa-th-large"></i> No runtimes yet. <a href="#" data-rt-tab="templates">Start from a template</a>.</div>'));
    applyTime(document.getElementById('runtimeCatalog'));
  }

  function renderTemplates() {
    var counts = {all: catalog.templates.length};
    $.each(catalog.templates, function(index, template) {
      counts[template.category] = (counts[template.category] || 0) + 1;
    });
    var chipsHtml = ['<button type="button" class="rt-chip' + (filters.category === 'all' ? ' is-active' : '') + '" data-category="all">All <small>' + counts.all + '</small></button>'];
    $.each(catalog.categories, function(key, category) {
      if (counts[key]) {
        chipsHtml.push('<button type="button" class="rt-chip' + (filters.category === key ? ' is-active' : '') + '" data-category="' + escapeHtml(key) + '"><i class="fa ' + escapeHtml(category.icon) + '"></i>' + escapeHtml(category.label) + ' <small>' + counts[key] + '</small></button>');
      }
    });
    $('#rtCategoryChips').html(chipsHtml.join(''));
    var search = filters.search.toLowerCase();
    var visible = $.grep(catalog.templates, function(template) {
      var text = (template.name + ' ' + template.description + ' ' + (template.highlights || []).join(' ')).toLowerCase();
      return (filters.category === 'all' || template.category === filters.category) && (search === '' || text.indexOf(search) !== -1);
    });
    $('#runtimeTemplates').html(visible.length ? $.map(visible, templateCard).join('') : '<div class="rt-empty">No template matches. Try another word, or start from a blank runtime.</div>');
  }

  function renderDeployments(idleMinutes) {
    $('#runtimeIdleNote').text(idleMinutes > 0 ? 'Editors stop after ' + idleMinutes + ' minutes without a connected browser and start again on the next open.' : 'Editors keep running until stopped.');
    var rows = $.map(catalog.deployments, function(deployment) {
      var runtime = runtimeByKey(deployment.runtimeKey);
      var state = deployment.state || 'unknown';
      return '<tr data-id="' + deployment.id + '">'
        + '<td><strong>' + escapeHtml(deployment.projectName || ('Project ' + deployment.projectId)) + '</strong><span class="rt-sub"><code>' + escapeHtml(deployment.container) + '</code> · port ' + deployment.port + '</span></td>'
        + '<td>' + (deployment.shared ? '<i class="fa fa-users"></i> The team' : '<i class="fa fa-user"></i> ' + escapeHtml(deployment.userName || ('User ' + deployment.userId))) + '</td>'
        + '<td>' + escapeHtml(deployment.runtimeKey === 'devcontainer' ? 'Project dev container' : (runtime ? runtime.name : deployment.runtimeKey)) + '<span class="rt-sub rt-mono">' + escapeHtml(deployment.image) + '</span></td>'
        + '<td>' + statusPill(state) + '<span class="rt-sub">' + escapeHtml(deployment.status) + '</span></td>'
        + '<td>' + when(deployment.lastOpenedAt) + (deployment.lastOpenedBy ? '<span class="rt-sub">by ' + escapeHtml(deployment.lastOpenedBy) + '</span>' : '') + '</td>'
        + '<td class="rt-actions text-right">'
        + '<button type="button" class="btn btn-default btn-xs" data-action="logs"' + (state === 'removed' ? ' disabled' : '') + '><i class="fa fa-file-text-o"></i> Output</button> '
        + (deployment.canStop ? '<button type="button" class="btn btn-default btn-xs" data-action="stop"' + (state !== 'running' ? ' disabled' : '') + '><i class="fa fa-stop"></i> Stop</button> ' : '')
        + (deployment.canRemove ? '<button type="button" class="btn btn-default btn-xs text-danger" data-action="remove" title="Remove the container; project files stay"><i class="fa fa-trash"></i> Remove</button>' : '')
        + '</td></tr>';
    });
    $('#runtimeDeployments').html(rows.length ? rows.join('') : '<tr><td colspan="6" class="rt-empty">No editor has been deployed yet. Choose a runtime for a project under VS Code in the sidebar and open it.</td></tr>');
    applyTime(document.getElementById('runtimeDeployments'));
  }

  function renderEngine(engine) {
    var alert = $('#runtimeEngineAlert');
    if (!config.enabled || (engine && engine.message)) {
      alert.prop('hidden', false).html('<div class="callout callout-warning"><h4><i class="fa fa-exclamation-triangle"></i> Runtimes cannot be built right now</h4><p>'
        + escapeHtml(engine && engine.message ? engine.message : 'Workspace runtimes need the Docker job runtime, which this deployment does not run.') + '</p></div>');
    } else {
      alert.prop('hidden', true).empty();
    }
  }

  function load() {
    $.ajax({url: urls.catalog, type: 'GET', dataType: 'json', cache: false})
      .done(function(response) {
        catalog = $.extend({runtimes: [], templates: [], categories: {}, deployments: [], usage: {}}, response);
        $('#runtimeReclaim').prop('hidden', !response.canReclaim || !(response.engine && response.engine.available));
        renderEngine(response.engine);
        renderStats();
        renderCatalog();
        renderTemplates();
        renderDeployments(response.idleMinutes || 0);
        var building = $.grep(catalog.runtimes, function(runtime) { return runtime.build && runtime.build.status === 'building'; }).length > 0;
        window.clearTimeout(refreshTimer);
        if (building) {
          refreshTimer = window.setTimeout(load, 5000);
        }
      })
      .fail(function(xhr) {
        renderEngine({message: message(xhr, 'The runtimes could not be loaded.')});
        $('#runtimeCatalog').html('<div class="rt-empty text-danger">' + escapeHtml(message(xhr, 'The runtimes could not be loaded.')) + '</div>');
      });
  }

  function showTab(name) {
    $('.rt-tab').each(function() {
      var active = $(this).attr('data-rt-tab') === name;
      $(this).toggleClass('is-active', active).attr('aria-selected', active ? 'true' : 'false');
    });
    $('.rt-panel').each(function() {
      $(this).prop('hidden', $(this).attr('data-rt-panel') !== name);
    });
    try {
      window.history.replaceState(null, '', '#' + name);
    } catch (error) {
      // Only a convenience.
    }
  }

  /* ---- Editor ---------------------------------------------------------- */

  function featuresText(features) {
    if (!features || !features.length) {
      return '';
    }
    var map = {};
    $.each(features, function(index, feature) {
      map[feature.ref] = feature.options && !$.isArray(feature.options) ? feature.options : {};
    });
    return JSON.stringify(map, null, 2);
  }

  function envText(env) {
    return $.map(env || {}, function(value, name) { return name + '=' + value; }).join('\n');
  }

  function editorFiles(kind, spec) {
    var generated = function(id, name, language) {
      return {id: id, name: name, language: language, value: '', readOnly: true, note: name + ' generated from the form'};
    };
    if (kind === 'conda') {
      return [{id: 'environment.yml', name: 'environment.yml', language: 'yaml', value: spec.environment_yml || condaTemplate},
        generated('Dockerfile', 'Dockerfile', 'dockerfile'), generated('devcontainer.json', 'devcontainer.json', 'json'), generated('README.md', 'README.md', 'markdown')];
    }
    if (kind === 'dockerfile') {
      return [{id: 'Dockerfile', name: 'Dockerfile', language: 'dockerfile', value: spec.dockerfile || dockerfileTemplate},
        generated('devcontainer.json', 'devcontainer.json', 'json'), generated('README.md', 'README.md', 'markdown')];
    }
    return [{id: 'requirements.txt', name: 'requirements.txt', language: 'requirements', value: spec.python_packages || ''},
      generated('Dockerfile', 'Dockerfile', 'dockerfile'), generated('devcontainer.json', 'devcontainer.json', 'json'), generated('README.md', 'README.md', 'markdown')];
  }

  var codeHelp = {
    python: 'requirements.txt is preinstalled for every project on the runtime; keep job-specific libraries in each job\'s pyproject.toml.',
    conda: 'Created as the "jobseeker" environment on Miniforge and put first on PATH. Include pip if jobs install from pyproject.toml.',
    dockerfile: 'Built on its own, so COPY and ADD may only use --from or URLs. JobSeeker adds the editor, Git and curl on top.'
  };

  function currentKind() {
    return $('#runtimeKinds input:checked').val() || 'python';
  }

  function showKind(kind, spec) {
    $('#runtimeKinds .rt-kind').each(function() {
      var checked = $(this).attr('data-kind') === kind;
      $(this).toggleClass('is-checked', checked).find('input').prop('checked', checked);
    });
    $('[data-runtime-kind]').each(function() {
      $(this).prop('hidden', $(this).attr('data-runtime-kind').split(' ').indexOf(kind) === -1);
    });
    $('#runtimeKindHelp').text(kinds[kind] ? kinds[kind].help : '');
    $('#runtimeCodeHelp').text(codeHelp[kind] || '');
    codeEditor.setFiles(editorFiles(kind, spec || {}));
    schedulePreview();
  }

  function syncHiddenFields() {
    var kind = currentKind();
    $('#runtimeSystemPackages').val($('#runtimeSystemPackagesInput').val() || '');
    $('#runtimePythonPackages').val(kind === 'python' ? codeEditor.getValue('requirements.txt') : '');
    $('#runtimeEnvironment').val(kind === 'conda' ? codeEditor.getValue('environment.yml') : '');
    $('#runtimeDockerfile').val(kind === 'dockerfile' ? codeEditor.getValue('Dockerfile') : '');
  }

  function showErrors(errors, includeIdentity) {
    var list = $.map(errors || {}, function(text, field) {
      return includeIdentity || (field !== 'name' && field !== 'key') ? '<li>' + escapeHtml(text) + '</li>' : null;
    });
    $('#runtimeEditorError').prop('hidden', !list.length).html(list.length ? '<ul>' + list.join('') + '</ul>' : '');
  }

  function schedulePreview() {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(preview, 350);
  }

  function preview() {
    if (!codeEditor || !$('#runtimeEditor').hasClass('in')) {
      return;
    }
    syncHiddenFields();
    if (previewRequest) {
      previewRequest.abort();
    }
    previewRequest = $.ajax({url: urls.preview, type: 'POST', dataType: 'json', data: $('#runtimeEditorForm').serialize()})
      .done(function(response) {
        var files = response.files || {};
        var kind = currentKind();
        if (kind !== 'dockerfile') {
          codeEditor.setValue('Dockerfile', files['.devcontainer/Dockerfile'] || '');
        }
        codeEditor.setValue('devcontainer.json', files['.devcontainer/devcontainer.json'] || '');
        codeEditor.setValue('README.md', files['.devcontainer/README.md'] || '');
        showErrors(response.errors, false);
      })
      .always(function() { previewRequest = null; });
  }

  function fillEditor(draft, mode) {
    var spec = draft.spec || {};
    var form = $('#runtimeEditorForm');
    form[0].reset();
    editing = {mode: mode, key: draft.key || ''};
    var readOnly = mode === 'view';
    var titles = {'new': 'New runtime', edit: 'Edit ' + draft.name, view: draft.name, duplicate: 'New runtime from ' + draft.name, template: 'New runtime from the ' + draft.name + ' template'};
    $('#runtimeEditorTitle').text(titles[mode] || 'Runtime');
    $('#runtimeOriginalKey').val(mode === 'edit' ? draft.key : '');
    $('#runtimeTemplate').val(spec.template || '');
    $('#runtimeName').val(mode === 'duplicate' ? draft.name + ' (copy)' : (draft.name || ''));
    $('#runtimeKey').val(mode === 'edit' || mode === 'view' ? draft.key : (mode === 'template' && !runtimeByKey(draft.key) ? draft.key : '')).prop('readonly', mode === 'edit' || mode === 'view');
    $('#runtimeDescription').val(draft.description || '');
    $('#runtimePythonVersion').val(spec.python_version || '3.13');
    $('#runtimeSystemPackagesInput').val((spec.system_packages || []).join(' '));
    $('#runtimeExtensions').val((spec.extensions || []).join('\n'));
    $('#runtimeFeatures').val(featuresText(spec.features));
    $('#runtimePorts').val((spec.ports || []).join(', '));
    $('#runtimePostCreate').val(spec.post_create || '');
    $('#runtimeEnv').val(envText(spec.env));
    showErrors({}, true);
    showKind(draft.kind || 'python', spec);
    form.find('input, select, textarea').not('[type=hidden]').prop('disabled', readOnly);
    $('#runtimeEditorSave, #runtimeEditorSaveBuild').prop('hidden', readOnly);
    codeEditor.input.readOnly = readOnly || codeEditor.input.readOnly;
    var download = mode === 'edit' || mode === 'view' ? urls.devcontainer + '?key=' + encodeURIComponent(draft.key)
      : (mode === 'template' ? urls.devcontainer + '?template=' + encodeURIComponent(spec.template || draft.key) : '');
    $('#runtimeEditorDownload').prop('hidden', !download).attr('href', download || '#');
    $('#runtimeEditor').modal('show');
  }

  function blankDraft() {
    return {key: '', name: '', description: '', kind: 'python', spec: {python_version: '3.13', system_packages: [], python_packages: '', extensions: [], features: [], ports: [], post_create: '', env: {}}};
  }

  function save(build) {
    syncHiddenFields();
    var buttons = $('#runtimeEditorSave, #runtimeEditorSaveBuild').prop('disabled', true);
    $.ajax({url: urls.save, type: 'POST', dataType: 'json', data: $('#runtimeEditorForm').serialize()})
      .done(function(response) {
        $('#runtimeEditor').modal('hide');
        $.each(response.warnings || [], function(index, warning) { toastr.warning(warning, 'Runtimes', {timeOut: 12000}); });
        if (build) {
          startBuild(response.key, false);
        } else {
          toastr.success(response.message, 'Runtimes', {timeOut: 9000});
          load();
        }
        showTab('catalog');
      })
      .fail(function(xhr) {
        showErrors((xhr.responseJSON && xhr.responseJSON.errors) || {form: message(xhr, 'The runtime could not be saved.')}, true);
      })
      .always(function() { buttons.prop('disabled', false); });
  }

  /* ---- Builds, logs and deployments ------------------------------------- */

  function showLog(key) {
    var runtime = runtimeByKey(key);
    $('#runtimeLogTitle').text('Build log · ' + (runtime ? runtime.name : key));
    $('#runtimeLogText').text('Loading…');
    $('#runtimeLogStatus').text('');
    $('#runtimeLogModal').modal('show').data('key', key);
    pollLog(key, true);
  }

  function pollLog(key, first) {
    window.clearTimeout(logTimer);
    $.ajax({url: urls.log, type: 'GET', dataType: 'json', cache: false, data: {key: key}})
      .done(function(response) {
        var pre = $('#runtimeLogText');
        var atBottom = first || pre[0].scrollHeight - pre.scrollTop() - pre.outerHeight() < 40;
        pre.text((response.lines || []).join('\n') || 'No build has run for this recipe yet.');
        if (atBottom) {
          pre.scrollTop(pre[0].scrollHeight);
        }
        var build = response.build || {};
        $('#runtimeLogStatus').html(statusPill(build.status) + ' <span class="rt-mono">' + escapeHtml(response.image) + '</span>' + (build.message ? ' · ' + escapeHtml(build.message) : ''));
        // The first log response can beat Bootstrap's fade-in transition. The
        // selected key is set synchronously by showLog(), so it is the stable
        // signal that this modal still owns the poll; :visible briefly reports
        // false and used to leave a successful build stuck on "Building".
        if (build.status === 'building' && $('#runtimeLogModal').data('key') === key) {
          logTimer = window.setTimeout(function() { pollLog(key, false); }, 2500);
        } else if (!first) {
          load();
        }
      })
      .fail(function(xhr) { $('#runtimeLogText').text(message(xhr, 'The log could not be loaded.')); });
  }

  function startBuild(key, force) {
    $.ajax({url: urls.build, type: 'POST', dataType: 'json', data: {key: key, force: force ? '1' : '0'}})
      .done(function(response) {
        toastr[response.started ? 'success' : 'info'](response.message, 'Runtimes');
        load();
        if (response.started) {
          showLog(key);
        }
      })
      .fail(function(xhr) { toastr.error(message(xhr, 'The build could not start.'), 'Runtimes', {timeOut: 12000}); });
  }

  function remove(key) {
    var runtime = runtimeByKey(key);
    if (!window.confirm('Remove ' + (runtime ? runtime.name : key) + ' from the catalog? Its images stay in the job runtime for jobs that use them.')) {
      return;
    }
    $.ajax({url: urls.remove, type: 'POST', dataType: 'json', data: {key: key}})
      .done(function(response) { toastr.success(response.message, 'Runtimes'); load(); })
      .fail(function(xhr) { toastr.error(message(xhr, 'The runtime could not be removed.'), 'Runtimes', {timeOut: 12000}); });
  }

  function deploymentAction(id, action) {
    var data = {id: id, action: action};
    if (action === 'remove') {
      if (!window.confirm('Remove this editor container? Project files are not touched; anything installed inside the container is lost.')) {
        return;
      }
      data.with_home = window.confirm('Also delete its home volume (VS Code settings, extension state, caches)?\n\nOK deletes it, Cancel keeps it for the next deployment.') ? '1' : '0';
    }
    $.ajax({url: urls.deployment, type: 'POST', dataType: 'json', data: data})
      .done(function(response) { toastr.success(response.message, 'Runtimes'); load(); })
      .fail(function(xhr) { toastr.error(message(xhr, 'The editor could not be changed.'), 'Runtimes', {timeOut: 12000}); });
  }

  function deploymentLogs(id) {
    var deployment = $.grep(catalog.deployments, function(row) { return row.id === id; })[0];
    $('#runtimeLogTitle').text('Editor output · ' + (deployment ? deployment.projectName : ''));
    $('#runtimeLogStatus').html(deployment ? '<span class="rt-mono">' + escapeHtml(deployment.container) + '</span>' : '');
    $('#runtimeLogText').text('Loading…');
    $('#runtimeLogModal').modal('show').data('key', '');
    $.ajax({url: urls.deploymentLogs, type: 'GET', dataType: 'json', cache: false, data: {id: id}})
      .done(function(response) {
        var pre = $('#runtimeLogText');
        pre.text(response.text || 'The editor has printed nothing yet.');
        pre.scrollTop(pre[0].scrollHeight);
      })
      .fail(function(xhr) { $('#runtimeLogText').text(message(xhr, 'The output could not be loaded.')); });
  }

  /* ---- Reclaiming images ------------------------------------------------ */

  function reclaimSelection() {
    var total = 0;
    var count = 0;
    $('#runtimeReclaimBody input[data-reference]:checked').each(function() {
      total += Number($(this).attr('data-size')) || 0;
      count++;
    });
    $('#runtimeReclaimTotal').text(count ? count + ' image' + (count === 1 ? '' : 's') + ', up to ' + formatBytes(total) + ' (images share layers)' : '');
    $('#runtimeReclaimConfirm').prop('disabled', count === 0);
  }

  function openReclaim() {
    $('#runtimeReclaimBody').html('<p class="rt-empty"><i class="fa fa-spinner fa-spin"></i> Looking for unused images…</p>');
    $('#runtimeReclaimTotal').text('');
    $('#runtimeReclaimConfirm').prop('disabled', true);
    $('#runtimeReclaimModal').modal('show');
    $.ajax({url: urls.reclaim, type: 'GET', dataType: 'json', cache: false})
      .done(function(response) {
        var groups = {editor: [], runtime: []};
        $.each(response.candidates || [], function(index, candidate) {
          groups[candidate.kind].push(candidate);
        });
        var row = function(candidate, checked) {
          return '<label class="checkbox" style="margin:4px 0;font-weight:normal"><input type="checkbox" data-reference="' + escapeHtml(candidate.reference) + '" data-size="' + candidate.sizeBytes + '"' + (checked ? ' checked' : '') + '> '
            + '<span class="rt-mono">' + escapeHtml(candidate.reference) + '</span> <span class="rt-help" style="display:inline">' + formatBytes(candidate.sizeBytes) + ' · built ' + when(candidate.created) + '</span></label>';
        };
        var html = ['<h5><strong>Editor images</strong> <small>no deployment and no current runtime uses them; jobs never do</small></h5>'];
        html.push(groups.editor.length ? $.map(groups.editor, function(candidate) { return row(candidate, true); }).join('') : '<p class="rt-help">None.</p>');
        html.push('<h5 style="margin-top:16px"><strong>Runtime images</strong> <small>no catalog runtime, container or saved job names them</small></h5>');
        if (!response.jobsComplete) {
          html.push('<p class="text-warning"><i class="fa fa-exclamation-triangle"></i> Some job configurations could not be read from Jenkins, so no runtime image is offered.</p>');
        } else if (groups.runtime.length) {
          html.push('<p class="rt-help text-warning"><i class="fa fa-info-circle"></i> A job whose Dockerfile in a Git repository starts <code>FROM</code> one of these cannot be seen from here, so they are not selected.</p>');
          html.push($.map(groups.runtime, function(candidate) { return row(candidate, false); }).join(''));
        } else {
          html.push('<p class="rt-help">None.</p>');
        }
        html.push('<p class="rt-help" style="margin-top:12px">Untagged layers left behind by rebuilds are removed as well.</p>');
        $('#runtimeReclaimBody').html(html.join(''));
        applyTime(document.getElementById('runtimeReclaimBody'));
        reclaimSelection();
      })
      .fail(function(xhr) {
        $('#runtimeReclaimBody').html('<p class="text-danger">' + escapeHtml(message(xhr, 'Unused images could not be listed.')) + '</p>');
      });
  }

  function reclaim() {
    var references = $('#runtimeReclaimBody input[data-reference]:checked').map(function() { return $(this).attr('data-reference'); }).get();
    var button = $('#runtimeReclaimConfirm');
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Removing');
    $.ajax({url: urls.reclaim, type: 'POST', dataType: 'json', data: {references: references}})
      .done(function(response) {
        $('#runtimeReclaimModal').modal('hide');
        toastr.success('Removed ' + response.removed.length + ' image' + (response.removed.length === 1 ? '' : 's') + ', freeing up to ' + formatBytes(response.spaceBytes || 0) + '.', 'Runtimes', {timeOut: 9000});
        $.each(response.failed || [], function(index, failure) { toastr.warning(failure, 'Runtimes', {timeOut: 12000}); });
        load();
      })
      .fail(function(xhr) { toastr.error(message(xhr, 'The images could not be removed.'), 'Runtimes', {timeOut: 12000}); })
      .always(function() { button.html('<i class="fa fa-eraser"></i> Remove selected'); });
  }

  /* ---- Wiring ----------------------------------------------------------- */

  $(function() {
    codeEditor = new window.JobSeekerCodeEditor(document.getElementById('runtimeCodeEditor'), {files: [], onChange: schedulePreview});
    $('#runtimeEditor').appendTo('body').on('shown.bs.modal', function() {
      preview();
      $('#runtimeName').trigger('focus');
    }).on('hidden.bs.modal', function() {
      window.clearTimeout(previewTimer);
    });
    $('#runtimeReclaimModal, #runtimeLogModal').appendTo('body');

    $(document).on('click', '[data-rt-tab]', function(event) {
      event.preventDefault();
      showTab($(this).attr('data-rt-tab'));
    });
    $('#runtimeRefresh').on('click', load);
    $('#runtimeNew').on('click', function() { fillEditor(blankDraft(), 'new'); });
    $('#runtimeReclaim').on('click', openReclaim);
    $('#runtimeReclaimConfirm').on('click', reclaim);
    $('#runtimeReclaimBody').on('change', 'input[data-reference]', reclaimSelection);

    $('#runtimeKinds').on('change', 'input[name="kind"]', function() {
      var spec = {python_packages: codeEditor.getValue('requirements.txt')};
      showKind(this.value, spec);
    });
    $('#runtimeEditorForm').on('input change', 'input, select, textarea', function(event) {
      if (event.target.name !== 'kind') {
        schedulePreview();
      }
    });
    $('#runtimeName').on('input', function() {
      if (!$('#runtimeKey').prop('readonly')) {
        $('#runtimeKey').attr('placeholder', $.trim(this.value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'from the name');
      }
    });
    $('#runtimeEditorForm').on('submit', function(event) {
      event.preventDefault();
      var submitter = event.originalEvent && event.originalEvent.submitter;
      save(submitter ? $(submitter).attr('data-build') === '1' : false);
    });

    $('#runtimeCatalog').on('click', '[data-action]', function() {
      var key = $(this).closest('[data-key]').attr('data-key');
      var action = $(this).attr('data-action');
      var runtime = runtimeByKey(key);
      if (action === 'build') {
        startBuild(key, runtime && runtime.build && runtime.build.status === 'ready');
      } else if (action === 'log') {
        showLog(key);
      } else if (action === 'edit') {
        fillEditor(runtime, runtime.canEdit ? 'edit' : 'view');
      } else if (action === 'duplicate') {
        fillEditor($.extend(true, {}, runtime), 'duplicate');
      } else if (action === 'delete') {
        remove(key);
      }
    });
    $('#runtimeTemplates').on('click', '[data-action="use"]', function() {
      var template = templateByKey($(this).closest('[data-template]').attr('data-template'));
      if (template) {
        fillEditor($.extend(true, {}, template.draft), 'template');
      }
    });
    $('#rtCategoryChips').on('click', '[data-category]', function() {
      filters.category = $(this).attr('data-category');
      renderTemplates();
    });
    $('#rtTemplateSearch').on('input', function() {
      filters.search = $.trim(this.value);
      renderTemplates();
    });
    $('#runtimeDeployments').on('click', '[data-action]', function() {
      var id = Number($(this).closest('tr').attr('data-id'));
      var action = $(this).attr('data-action');
      if (action === 'logs') {
        deploymentLogs(id);
      } else {
        deploymentAction(id, action);
      }
    });
    $('#runtimeLogModal').on('hidden.bs.modal', function() {
      window.clearTimeout(logTimer);
      $(this).data('key', '');
    });

    var hash = (window.location.hash || '').replace('#', '');
    if (['catalog', 'templates', 'deployments'].indexOf(hash) !== -1) {
      showTab(hash);
    }
    load();
  });
})(jQuery);
