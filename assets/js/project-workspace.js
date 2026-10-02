/*
 * Sidebar VS Code launcher: choose or create a project and open your own
 * workspace of it in OpenVSCode (see ProjectWorkspaceTrait).
 */
(function($) {
  'use strict';

  var config = window.jobseekerProjectLauncher || {};
  var state = {projects: [], types: {}, selectedId: null, environment: 'DEV', loading: null, branchMode: 'shared', customBranch: '', createType: 'python',
    runtimesEnabled: false, runtime: {}, runtimeLoading: null, samples: [], sampleUrl: ''};
  var runtimeKindIcons = {python: 'fa-code', conda: 'fa-flask', dockerfile: 'fa-cube'};
  var buildLabels = {ready: 'built', building: 'building…', failed: 'build failed', missing: 'image missing', none: 'not built yet', outdated: 'editor update', unknown: ''};
  var typeIcons = {python: 'fa-code', shell: 'fa-terminal', hop: 'fa-random'};

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function(character) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
    });
  }

  function notify(level, message, title) {
    if (window.toastr) {
      window.toastr[level](message, title || 'VS Code', {timeOut: level === 'error' ? 12000 : 8000});
    }
  }

  function globalEnvironment() {
    var helper = window.JobSeekerGlobalEnvironment;
    var value = helper && helper.selected ? String(helper.selected() || '') : '';
    return value.toUpperCase() === 'ALL' ? '' : value;
  }

  function selectedProject() {
    return $.grep(state.projects, function(project) { return project.id === state.selectedId; })[0] || null;
  }

  function typeLabel(type) {
    return state.types[type] ? state.types[type].label : type;
  }

  function runtimeMeta(project) {
    var runtime = project.runtime || {};
    return state.runtimesEnabled && runtime.key && runtime.key !== 'default'
      ? ' · <i class="fa fa-cubes"></i> ' + escapeHtml(runtime.label) + (runtime.isolation === 'shared' ? ' (shared)' : '')
      : '';
  }

  function projectMeta(project) {
    return projectLocationMeta(project) + runtimeMeta(project);
  }

  function projectLocationMeta(project) {
    var workspace = project.workspace || {};
    if (!project.hasGit) {
      return '<i class="fa fa-folder-o"></i> Shared folder' + (workspace.jobs ? ' · ' + workspace.jobs + ' job' + (workspace.jobs === 1 ? '' : 's') : '');
    }
    if (!workspace.exists) {
      return '<i class="fa fa-code-fork"></i> ' + escapeHtml(project.repository || project.repositoryUrl) + ' · not cloned yet';
    }
    var changes = workspace.changes ? ' · ' + workspace.changes + ' change' + (workspace.changes === 1 ? '' : 's') : '';
    return '<i class="fa fa-code-fork"></i> ' + escapeHtml(workspace.branch || 'detached') + changes + (workspace.jobs ? ' · ' + workspace.jobs + ' job' + (workspace.jobs === 1 ? '' : 's') : '');
  }

  function renderList() {
    var list = $('#projectLauncherList');
    var search = $.trim($('#projectLauncherSearch').val() || '').toLowerCase();
    var visible = $.grep(state.projects, function(project) {
      return search === '' || project.name.toLowerCase().indexOf(search) !== -1 || String(project.repositoryUrl || '').toLowerCase().indexOf(search) !== -1;
    });
    if (!state.projects.length) {
      list.html('<div class="project-launcher-empty"><i class="fa fa-folder-open-o"></i> No projects yet. <a href="#" data-launcher-new>Create the first one</a>.</div>');
      return;
    }
    if (!visible.length) {
      list.html('<div class="project-launcher-empty">No project matches “' + escapeHtml(search) + '”.</div>');
      return;
    }
    list.html($.map(visible, function(project) {
      var selected = project.id === state.selectedId;
      return '<button type="button" class="project-launcher-item' + (selected ? ' is-selected' : '') + '" role="option" aria-selected="' + selected + '" data-project-id="' + project.id + '">'
        + '<span class="project-launcher-type is-' + escapeHtml(project.type) + '" title="' + escapeHtml(typeLabel(project.type)) + '"><i class="fa ' + (typeIcons[project.type] || 'fa-code') + '"></i></span>'
        + '<span class="project-launcher-item-text"><strong>' + escapeHtml(project.name) + '</strong><small>' + projectMeta(project) + '</small></span>'
        + (project.workspace && project.workspace.exists ? '<span class="project-launcher-dot" title="You have a workspace"></span>' : '')
        + '</button>';
    }).join(''));
  }

  function branchChoice(mode, title, branch, help) {
    var checked = state.branchMode === mode;
    return '<label class="project-launcher-branch' + (checked ? ' is-checked' : '') + '">'
      + '<input type="radio" name="projectLauncherBranchMode" value="' + mode + '"' + (checked ? ' checked' : '') + '>'
      + '<span><strong>' + title + (branch ? ' <code>' + escapeHtml(branch) + '</code>' : '') + '</strong><small>' + help + '</small></span></label>';
  }

  function renderDetail() {
    var project = selectedProject();
    var detail = $('#projectLauncherDetail');
    $('#projectLauncherCreate').prop('hidden', true);
    detail.prop('hidden', false);
    $('#projectLauncherHop').prop('hidden', !(project && project.type === 'hop'));
    if (!project) {
      detail.html('<div class="project-launcher-placeholder"><i class="fa fa-folder-open-o"></i><p>Choose a project, or create one.</p></div>');
      $('#projectLauncherOpen').prop('disabled', true);
      $('#projectLauncherCreateJob').prop('hidden', true);
      return;
    }
    var workspace = project.workspace || {};
    var html = ['<div class="project-launcher-heading"><span class="project-launcher-type is-' + escapeHtml(project.type) + '"><i class="fa ' + (typeIcons[project.type] || 'fa-code') + '"></i></span>'
      + '<div><h5>' + escapeHtml(project.name) + '</h5><span>' + escapeHtml(typeLabel(project.type)) + ' project'
      + ' · <a href="' + escapeHtml(config.projectDetailsUrl + project.id) + '">settings</a></span></div></div>'];

    if (project.hasGit) {
      html.push('<dl class="project-launcher-facts"><dt>Repository</dt><dd><code>' + escapeHtml(project.repository || project.repositoryUrl) + '</code></dd>'
        + '<dt>Your working copy</dt><dd><code>' + escapeHtml(workspace.path) + '</code>'
        + (workspace.exists ? '<br><span class="project-launcher-ok"><i class="fa fa-check-circle"></i> on <code>' + escapeHtml(workspace.branch) + '</code>'
          + (workspace.changes ? ', ' + workspace.changes + ' uncommitted change' + (workspace.changes === 1 ? '' : 's') : ', clean')
          + (workspace.ahead ? ', ' + workspace.ahead + ' commit' + (workspace.ahead === 1 ? '' : 's') + ' to push' : '') + '</span>'
          : '<br><span class="text-muted">Cloned the first time you open it. Only you work in it.</span>')
        + '</dd><dt>Builds</dt><dd>' + (project.credentialKey
          ? '<span class="project-launcher-ok"><i class="fa fa-lock"></i> clone with <code>' + escapeHtml(project.credentialKey) + '</code></span>'
          : '<span class="project-launcher-warn"><i class="fa fa-globe"></i> No build credential:</span> builds can only clone a public repository. <a href="' + escapeHtml(config.projectDetailsUrl + project.id) + '">Set one</a>')
        + '</dd><dt>Git account</dt><dd>' + (project.account
          ? '<span class="project-launcher-ok"><i class="fa fa-user-circle"></i> ' + escapeHtml(project.account) + '</span> clones, pulls and pushes for you.'
          : '<span class="project-launcher-warn"><i class="fa fa-exclamation-triangle"></i> No account for this host on your profile.</span> Only a public repository opens, and pushing needs one. <a href="' + escapeHtml(config.gitProfileUrl) + '" target="_blank" rel="noopener">Add an account</a>')
        + '</dd></dl>');
      if (!workspace.exists) {
        var branches = project.branches || {};
        html.push('<fieldset class="project-launcher-branches"><legend>Start working on</legend>'
          + branchChoice('shared', 'The team branch', branches.shared, 'Everyone\'s ' + escapeHtml(state.environment) + ' work meets here, and ' + escapeHtml(state.environment) + ' jobs run it. Pull before you push.')
          + branchChoice('personal', 'My own branch', branches.personal, 'Forked from <code>' + escapeHtml(branches.shared || '') + '</code>. Nobody else pushes to it; merge it into the team branch when it is ready.')
          + branchChoice('custom', 'Another branch', '', '<input type="text" class="form-control input-sm" id="projectLauncherCustomBranch" maxlength="200" spellcheck="false" placeholder="feature/orders" value="' + escapeHtml(state.customBranch) + '">')
          + '</fieldset>');
      } else {
        html.push('<p class="project-launcher-note"><i class="fa fa-info-circle"></i> Switch or create branches from the VS Code status bar.</p>');
      }
    } else {
      html.push('<dl class="project-launcher-facts"><dt>Folder</dt><dd><code>' + escapeHtml(workspace.path) + '</code>' + (workspace.exists ? '' : ' <span class="text-muted">(created on first open)</span>') + '</dd></dl>'
        + '<p class="project-launcher-note is-warning"><i class="fa fa-users"></i> Without Git this folder is shared: everyone edits the same files, and jobs run them as they are. '
        + '<a href="' + escapeHtml(config.projectDetailsUrl + project.id) + '">Link a repository</a> for personal working copies and branches.</p>');
    }
    if (project.type === 'hop') {
      html.push('<p class="project-launcher-note"><i class="fa fa-random"></i> Hop workflows and pipelines are designed in the Hop GUI; VS Code is for the files around them.</p>');
    }
    if (state.runtimesEnabled) {
      html.push('<section class="project-launcher-runtime" id="projectLauncherRuntime" aria-live="polite">' + runtimePanel(project) + '</section>');
    }
    if (project.type === 'python' && state.samples.length) {
      html.push(samplePanel(project));
    }
    html.push('<p class="project-launcher-layout"><i class="fa fa-sitemap"></i> <code>jobs/&lt;job&gt;/</code> one folder per job · <code>shared/</code> code the jobs share'
      + (project.type === 'python' ? ' · tasks <strong>JobSeeker: new job</strong> and <strong>JobSeeker: add sample</strong> add one' : '') + '</p>');
    detail.html(html.join(''));
    if (state.runtimesEnabled && !state.runtime[project.id]) {
      loadRuntime(project.id);
    }
    $('#projectLauncherOpen').prop('disabled', false);
    $('#projectLauncherCreateJob').prop('hidden', !(workspace.jobs > 0) || project.type !== 'python')
      .attr('href', config.jobCreationUrl + '?project=' + encodeURIComponent(project.id));
  }

  // A sample from the library, added to the viewer's workspace as a job folder.
  function samplePanel(project) {
    var opened = project.workspace && project.workspace.exists;
    var options = $.map(state.samples, function(sample) {
      return '<option value="' + escapeHtml(sample.id) + '" title="' + escapeHtml(sample.description) + '">' + escapeHtml(sample.name)
        + (sample.complexity ? ' · ' + escapeHtml(sample.complexity) : '') + '</option>';
    });
    return '<section class="project-launcher-runtime project-launcher-samples"><div class="project-launcher-runtime-head"><strong><i class="fa fa-flask"></i> Start from a sample</strong></div>'
      + '<div class="project-launcher-starter"><select class="form-control input-sm" id="projectLauncherSample" aria-label="Sample"' + (opened ? '' : ' disabled') + '>' + options.join('') + '</select>'
      + '<button type="button" class="btn btn-default btn-sm" id="projectLauncherSampleAdd"' + (opened ? '' : ' disabled') + '><i class="fa fa-plus"></i> Add</button></div>'
      + '<p class="project-launcher-runtime-help">' + (opened
        ? 'Added as a new folder under <code>jobs/</code> in your workspace, set up for this project\'s Python. In VS Code, the task <strong>JobSeeker: add sample</strong> does the same.'
        : 'Open the project once, then add samples here or with the task <strong>JobSeeker: add sample</strong> in VS Code.') + '</p></section>';
  }

  function addSample() {
    var project = selectedProject();
    var sampleId = $('#projectLauncherSample').val();
    if (!project || !sampleId || !state.sampleUrl) {
      return;
    }
    var button = $('#projectLauncherSampleAdd');
    button.prop('disabled', true);
    $.ajax({url: state.sampleUrl, type: 'POST', dataType: 'json', data: {project_id: project.id, sample_id: sampleId}})
      .done(function(response) {
        notify('success', response.message, project.name);
        load(project.id);
      })
      .fail(function(xhr) {
        notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'The sample could not be added.', project.name);
        button.prop('disabled', false);
      });
  }

  function formatMemory(megabytes) {
    return megabytes >= 1024 ? (Math.round(megabytes / 102.4) / 10) + ' GB' : megabytes + ' MB';
  }

  function runtimeOption(value, label, selected) {
    return '<option value="' + escapeHtml(value) + '"' + (selected ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
  }

  // The project's runtime: what it opens in, for whom, and how it stands.
  function runtimePanel(project) {
    var info = state.runtime[project.id];
    var head = '<div class="project-launcher-runtime-head"><strong><i class="fa fa-cubes"></i> Runtime</strong>'
      + (config.runtimesUrl ? '<a href="' + escapeHtml(config.runtimesUrl) + '" target="_blank" rel="noopener">Manage runtimes</a>' : '') + '</div>';
    if (!info) {
      return head + '<p class="project-launcher-runtime-help"><i class="fa fa-spinner fa-spin"></i> Loading runtimes…</p>';
    }
    if (info.error) {
      return head + '<p class="project-launcher-runtime-help is-error">' + escapeHtml(info.error) + '</p>';
    }
    if (!info.enabled) {
      return head + '<p class="project-launcher-runtime-help">' + escapeHtml(info.message || 'Runtimes are not available here.') + '</p>';
    }
    var settings = info.settings || {};
    var key = settings.runtimeKey || 'default';
    var options = [runtimeOption('default', 'Default editor (shared OpenVSCode)', key === 'default')];
    var catalog = $.map(info.runtimes || [], function(runtime) {
      var status = buildLabels[runtime.status] ? ' · ' + buildLabels[runtime.status] : '';
      return runtimeOption(runtime.key, runtime.name + status, key === runtime.key);
    });
    if (catalog.length) {
      options.push('<optgroup label="Catalog">' + catalog.join('') + '</optgroup>');
    }
    var templates = $.map(info.templates || [], function(template) {
      return template.inCatalog ? null : runtimeOption('template:' + template.key, template.name + ' · ' + template.category);
    });
    if (templates.length) {
      options.push('<optgroup label="Templates (added to the catalog when chosen)">' + templates.join('') + '</optgroup>');
    }
    options.push(runtimeOption('devcontainer', info.devcontainer ? 'Dev container (' + info.devcontainer + ')' : 'Dev container (add .devcontainer/devcontainer.json)', key === 'devcontainer'));
    var html = [head, '<select class="form-control input-sm" id="projectLauncherRuntimeSelect" aria-label="Runtime">' + options.join('') + '</select>'];
    var selected = $.grep(info.runtimes || [], function(runtime) { return runtime.key === key; })[0];
    if (key === 'default') {
      html.push('<p class="project-launcher-runtime-help">The editor everyone shares, with Python, uv and Poetry. Right for small projects; pick a runtime when a project needs its own Python, Conda or system libraries.</p>');
      return html.join('');
    }
    html.push('<p class="project-launcher-runtime-help">' + (key === 'devcontainer'
      ? 'Built from the project\'s own <code>devcontainer.json</code>, read from your workspace. Commit it so the team gets the same environment.'
      : escapeHtml(selected ? selected.description : '')) + '</p>');
    if (key === 'devcontainer' && !info.devcontainer) {
      var opened = project.workspace && project.workspace.exists;
      if (opened) {
        var sources = ['<option value="">Python starter</option>'];
        var templateSources = $.map(info.templates || [], function(template) { return runtimeOption('template:' + template.key, template.name); });
        var runtimeSources = $.map(info.runtimes || [], function(runtime) { return runtimeOption('runtime:' + runtime.key, runtime.name); });
        if (templateSources.length) {
          sources.push('<optgroup label="Templates">' + templateSources.join('') + '</optgroup>');
        }
        if (runtimeSources.length) {
          sources.push('<optgroup label="Catalog runtimes">' + runtimeSources.join('') + '</optgroup>');
        }
        html.push('<div class="project-launcher-runtime-help is-warning"><i class="fa fa-info-circle"></i> This project has no dev container yet. Start one in your workspace from:'
          + '<div class="project-launcher-starter"><select class="form-control input-sm" id="projectLauncherStarterSource" aria-label="Start the dev container from">' + sources.join('') + '</select>'
          + '<button type="button" class="btn btn-default btn-sm" id="projectLauncherDevcontainerStarter"><i class="fa fa-plus"></i> Add a starter</button></div>'
          + 'Then commit <code>.devcontainer/</code> so the team gets the same environment.</div>');
      } else {
        html.push('<p class="project-launcher-runtime-help is-warning"><i class="fa fa-info-circle"></i> This project has no dev container yet. Choose another runtime to open it the first time, or add <code>.devcontainer/devcontainer.json</code> to its repository.</p>');
      }
    }
    var shared = settings.isolation === 'shared';
    html.push('<div class="project-launcher-isolation" role="radiogroup" aria-label="Who uses the editor">'
      + '<label class="' + (!shared ? 'is-checked' : '') + '"><input type="radio" name="projectLauncherIsolation" value="user"' + (!shared ? ' checked' : '') + '><strong><i class="fa fa-user"></i> Just me</strong><small>Everyone gets a container of their own.</small></label>'
      + '<label class="' + (shared ? 'is-checked' : '') + '"><input type="radio" name="projectLauncherIsolation" value="shared"' + (shared ? ' checked' : '') + '><strong><i class="fa fa-users"></i> Shared</strong><small>One container the team opens together.</small></label></div>');
    var memoryChoices = [1024, 2048, 4096, 8192, 16384, 32768];
    if ($.inArray(settings.memoryMb, memoryChoices) === -1) {
      memoryChoices.push(settings.memoryMb);
      memoryChoices.sort(function(a, b) { return a - b; });
    }
    html.push('<div class="project-launcher-resources"><label>CPUs <input type="number" class="form-control input-sm" id="projectLauncherRuntimeCpus" min="0.5" max="64" step="0.5" value="' + escapeHtml(settings.cpus) + '"></label>'
      + '<label>Memory <select class="form-control input-sm" id="projectLauncherRuntimeMemory">' + $.map(memoryChoices, function(value) {
        return '<option value="' + value + '"' + (value === settings.memoryMb ? ' selected' : '') + '>' + formatMemory(value) + '</option>';
      }).join('') + '</select></label></div>');
    if (info.gateway && info.gateway.secure === false) {
      html.push('<p class="project-launcher-runtime-help is-warning"><i class="fa fa-unlock-alt"></i> Notebooks and other webviews stay blank here: editors open on <code>'
        + escapeHtml(info.gateway.url) + '</code>, plain HTTP, where browsers turn off the service workers they need. Serve the gateway over HTTPS'
        + ' (<code>scripts/workspace-gateway-certificate.sh</code>, then <code>JOBSEEKER_WORKSPACE_GATEWAY_TLS=true</code>) or open JobSeeker on localhost.</p>');
    }
    if (info.gateway && info.gateway.tls) {
      html.push(trustNotice(info.gateway, project.id));
    }
    html.push(runtimeStatus(info));
    return html.join('');
  }

  // Editors use HTTPS with JobSeeker's own certificate authority. Until a
  // browser trusts it, every editor opens behind a warning; say why and how
  // to trust it once, instead of leaving people to click past it.
  function trustNotice(gateway, projectId) {
    var trust = state.gatewayTrust;
    if (!trust || trust.url !== gateway.url) {
      trust = state.gatewayTrust = {url: gateway.url, trusted: null};
      var settle = function(trusted) {
        if (trust.trusted === null) {
          trust.trusted = trusted;
          renderRuntime(projectId);
        }
      };
      // An image, because JobSeeker's CSP allows images from HTTPS origins
      // but no connections to other origins.
      var probe = new Image();
      probe.onload = function() { settle(true); };
      probe.onerror = function() { settle(false); };
      window.setTimeout(function() { settle(true); }, 8000);
      probe.src = gateway.url + '/jobseeker-gateway-check.gif?' + Date.now();
    }
    if (trust.trusted !== false) {
      return '';
    }
    return '<div class="project-launcher-runtime-help is-warning project-launcher-trust"><i class="fa fa-lock"></i> This browser does not trust the editors\' certificate yet, so VS Code opens behind a warning (<em>Advanced &rsaquo; Proceed</em>). Trust JobSeeker\'s certificate once to skip it: '
      + '<a href="' + escapeHtml(gateway.certificateUrl) + '" download><i class="fa fa-download"></i> download it</a>, add it as a trusted authority, then restart the browser.'
      + '<details><summary>How</summary><ul>'
      + '<li><strong>macOS</strong>: open the file, then in Keychain Access set it to <em>Always Trust</em>.</li>'
      + '<li><strong>Windows</strong>: open the file, <em>Install Certificate</em> &rsaquo; Local Machine &rsaquo; <em>Trusted Root Certification Authorities</em>.</li>'
      + '<li><strong>Linux</strong> (Chrome): Settings &rsaquo; Privacy and security &rsaquo; Security &rsaquo; Manage certificates &rsaquo; Authorities &rsaquo; Import.</li>'
      + '<li><strong>Firefox</strong>: Settings &rsaquo; Privacy &amp; Security &rsaquo; View Certificates &rsaquo; Authorities &rsaquo; Import.</li>'
      + '</ul>The certificate can only vouch for this server\'s own names. <button type="button" class="btn btn-link btn-xs" id="projectLauncherTrustRecheck"><i class="fa fa-refresh"></i> Check again</button></details></div>';
  }

  function runtimeStatus(info) {
    var lines = [];
    if (info.message) {
      lines.push('<p class="project-launcher-runtime-help is-error"><i class="fa fa-exclamation-triangle"></i> ' + escapeHtml(info.message) + '</p>');
    }
    var build = info.build;
    if (build) {
      var built = build.status === 'ready';
      lines.push('<div class="project-launcher-runtime-state"><span class="project-launcher-runtime-badge is-' + escapeHtml(build.status) + '">' + escapeHtml(buildLabels[build.status] || build.status) + '</span> '
        + (built && build.rebuilding ? '<code>' + escapeHtml(build.image) + '</code> <span class="text-muted"><i class="fa fa-spinner fa-spin"></i> rebuilding; editors move to the new image at their next open</span>'
          : built ? '<code>' + escapeHtml(build.image) + '</code> <button type="button" class="btn btn-link btn-xs" id="projectLauncherRuntimeRebuild" title="Build it again for newer base images and packages"><i class="fa fa-refresh"></i> Rebuild</button>'
          : build.status === 'building' ? 'The editor opens when the image is ready.'
          : build.status === 'failed' ? escapeHtml(build.message || 'The last build failed.') + ' Open to build again.'
          : build.status === 'outdated' ? 'JobSeeker\'s editor layer changed; it is added again on the next open, in seconds.'
          : 'Built the first time the project is opened (a few minutes).') + '</div>');
    }
    if (build && build.rebuildFailed) {
      lines.push('<p class="project-launcher-runtime-help is-warning"><i class="fa fa-exclamation-triangle"></i> The last rebuild failed, so editors keep the previous image: ' + escapeHtml(build.rebuildFailed) + '</p>');
    }
    var deployment = info.deployment;
    if (deployment && deployment.state !== 'removed') {
      var running = deployment.state === 'ready' || deployment.state === 'starting';
      lines.push('<div class="project-launcher-runtime-state"><span class="project-launcher-runtime-badge is-' + (running ? 'ready' : 'none') + '">' + (running ? 'running' : 'stopped') + '</span> '
        + (deployment.shared ? 'The team\'s editor' : 'Your editor') + (deployment.current === false && build && build.status === 'ready' ? ' is replaced with the new image on the next open.' : running ? ' is running.' : ' starts on the next open.')
        + (running ? ' <button type="button" class="btn btn-link btn-xs" id="projectLauncherRuntimeStop"><i class="fa fa-stop"></i> Stop</button>' : '') + '</div>');
    }
    $.each(info.warnings || [], function(index, warning) {
      lines.push('<p class="project-launcher-runtime-help is-warning"><i class="fa fa-info-circle"></i> ' + escapeHtml(warning) + '</p>');
    });
    return lines.join('');
  }

  function renderRuntime(projectId) {
    var project = selectedProject();
    if (project && project.id === projectId) {
      $('#projectLauncherRuntime').html(runtimePanel(project));
    }
  }

  function loadRuntime(projectId) {
    if (!config.runtimeUrl) {
      return;
    }
    if (state.runtimeLoading) {
      state.runtimeLoading.abort();
    }
    state.runtimeLoading = $.ajax({url: config.runtimeUrl, type: 'GET', dataType: 'json', cache: false, data: {project_id: projectId}})
      .done(function(response) {
        state.runtime[projectId] = response;
      })
      .fail(function(xhr, status) {
        if (status !== 'abort') {
          state.runtime[projectId] = {error: (xhr.responseJSON && xhr.responseJSON.message) || 'The runtime could not be loaded.'};
        }
      })
      .always(function(xhr, status) {
        state.runtimeLoading = null;
        if (status !== 'abort') {
          renderRuntime(projectId);
        }
      });
  }

  function saveRuntime() {
    var project = selectedProject();
    var info = project && state.runtime[project.id];
    if (!info || !info.settings) {
      return;
    }
    var data = {
      project_id: project.id,
      runtime: $('#projectLauncherRuntimeSelect').val() || 'default',
      isolation: $('input[name="projectLauncherIsolation"]:checked').val() || info.settings.isolation || 'user',
      cpus: $('#projectLauncherRuntimeCpus').length ? $('#projectLauncherRuntimeCpus').val() : info.settings.cpus,
      memory_mb: $('#projectLauncherRuntimeMemory').length ? $('#projectLauncherRuntimeMemory').val() : info.settings.memoryMb
    };
    $('#projectLauncherRuntime').find('select, input').prop('disabled', true);
    $.ajax({url: config.runtimeSaveUrl, type: 'POST', dataType: 'json', data: data})
      .done(function(response) {
        notify('success', response.message, project.name);
        delete state.runtime[project.id];
        load(project.id);
      })
      .fail(function(xhr) {
        notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'The runtime could not be saved.', project.name);
        renderRuntime(project.id);
      });
  }

  function addDevcontainerStarter() {
    var project = selectedProject();
    if (!project) {
      return;
    }
    $('#projectLauncherDevcontainerStarter').prop('disabled', true);
    $.ajax({url: config.devcontainerStarterUrl, type: 'POST', dataType: 'json', data: {project_id: project.id, source: $('#projectLauncherStarterSource').val() || ''}})
      .done(function(response) { notify('success', response.message, project.name); })
      .fail(function(xhr) { notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'The dev container could not be added.', project.name); })
      .always(function() { loadRuntime(project.id); });
  }

  function rebuildRuntime() {
    var project = selectedProject();
    if (!project || !window.confirm('Build this runtime again, for newer base images and packages? It takes a few minutes; editors move to the new image at their next open.')) {
      return;
    }
    $('#projectLauncherRuntimeRebuild').prop('disabled', true);
    $.ajax({url: config.runtimeRebuildUrl, type: 'POST', dataType: 'json', data: {project_id: project.id}})
      .done(function(response) { notify(response.started ? 'success' : 'info', response.message, project.name); })
      .fail(function(xhr) { notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'The runtime could not be rebuilt.', project.name); })
      .always(function() { loadRuntime(project.id); });
  }

  function stopRuntime() {
    var project = selectedProject();
    if (!project) {
      return;
    }
    $('#projectLauncherRuntimeStop').prop('disabled', true);
    $.ajax({url: config.runtimeStopUrl, type: 'POST', dataType: 'json', data: {project_id: project.id}})
      .done(function(response) { notify('success', response.message, project.name); })
      .fail(function(xhr) { notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'The editor could not be stopped.', project.name); })
      .always(function() { loadRuntime(project.id); });
  }

  function load(selectId) {
    if (state.loading) {
      state.loading.abort();
    }
    var environment = globalEnvironment();
    state.loading = $.ajax({url: config.listUrl, type: 'GET', dataType: 'json', cache: false, data: {environment: environment}})
      .done(function(response) {
        state.projects = response.projects || [];
        state.types = response.types || {};
        state.runtimesEnabled = !!response.runtimesEnabled;
        state.samples = response.samples || [];
        state.sampleUrl = response.sampleUrl || '';
        state.runtime = {};
        state.environment = response.environment || 'DEV';
        $('#projectLauncherEnvironment').text(state.environment);
        if (selectId) {
          state.selectedId = selectId;
        } else if (!selectedProject()) {
          var withWorkspace = $.grep(state.projects, function(project) { return project.workspace && project.workspace.exists; });
          state.selectedId = withWorkspace.length ? withWorkspace[0].id : (state.projects.length === 1 ? state.projects[0].id : null);
        }
        renderList();
        renderDetail();
      })
      .fail(function(xhr, status) {
        if (status !== 'abort') {
          $('#projectLauncherList').html('<div class="project-launcher-empty is-error">' + escapeHtml((xhr.responseJSON && xhr.responseJSON.message) || 'Projects could not be loaded.') + '</div>');
        }
      })
      .always(function() { state.loading = null; });
  }

  function renderTypes() {
    var types = $.isEmptyObject(state.types) ? {python: {label: 'Python', help: ''}, shell: {label: 'Shell', help: ''}, hop: {label: 'Apache Hop', help: ''}} : state.types;
    $('#projectLauncherTypes').html($.map(types, function(type, key) {
      var checked = state.createType === key;
      return '<label class="project-launcher-type-card' + (checked ? ' is-checked' : '') + '"><input type="radio" name="projectLauncherType" value="' + escapeHtml(key) + '"' + (checked ? ' checked' : '') + '>'
        + '<span class="project-launcher-type is-' + escapeHtml(key) + '"><i class="fa ' + (typeIcons[key] || 'fa-code') + '"></i></span>'
        + '<strong>' + escapeHtml(type.label) + '</strong><small>' + escapeHtml(type.help || '') + '</small></label>';
    }).join(''));
  }

  function showCreate() {
    renderTypes();
    $('#projectLauncherDetail').prop('hidden', true);
    $('#projectLauncherCreate').prop('hidden', false);
    $('#projectLauncherOpen').prop('disabled', true);
    $('#projectLauncherCreateJob, #projectLauncherHop').prop('hidden', true);
    $('#projectLauncherName').val($.trim($('#projectLauncherSearch').val() || '')).trigger('focus');
  }

  function waitingWindow(target) {
    try {
      target.document.title = 'Opening VS Code';
      target.document.body.style.cssText = 'font:14px -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#ecf0f5;color:#333;padding:40px;';
      target.document.body.innerHTML = '<div style="max-width:520px;margin:10vh auto;background:#fff;border-top:3px solid #007acc;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.15)"><h2 style="margin:0 0 12px;font-size:20px">Preparing your workspace</h2><p>JobSeeker is getting the project ready and starting the editor. This tab opens it as soon as it is healthy.</p></div>';
    } catch (error) {
      // A popup policy may keep us from styling the tab; it still navigates.
    }
  }

  // Shows a runtime build or start in the tab that will hold the editor.
  function waitingRuntime(target, status, title) {
    if (!target || target.closed) {
      return;
    }
    try {
      var build = status.build || {};
      var failed = status.ok === false;
      var building = build.status && build.status !== 'ready';
      var heading = failed ? 'The runtime could not start' : building ? 'Building ' + (status.label || 'the runtime') : 'Starting ' + (status.label || 'the editor');
      var text = failed ? (status.message || build.message || 'Check the build log on the Runtimes page.')
        : building ? 'The first build of a runtime installs its packages and the editor, which takes a few minutes. Later opens start in seconds.'
        : 'The image is ready; the editor is starting.';
      var log = (build.log || []).slice(-40).join('\n');
      target.document.title = failed ? 'Runtime failed' : building ? 'Building runtime' : 'Starting editor';
      target.document.body.style.cssText = 'font:14px -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#ecf0f5;color:#333;padding:40px;margin:0;';
      target.document.body.innerHTML = '<div style="max-width:860px;margin:6vh auto;background:#fff;border-top:3px solid ' + (failed ? '#dd4b39' : '#007acc') + ';padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.15)">'
        + '<h2 style="margin:0 0 6px;font-size:20px">' + escapeHtml(heading) + '</h2>'
        + '<p style="color:#6b7a89;margin:0 0 12px">' + escapeHtml(title || '') + '</p><p>' + escapeHtml(text) + '</p>'
        + (log ? '<pre style="background:#1e1e1e;color:#d4d4d4;font:12px Menlo,Consolas,monospace;max-height:50vh;overflow:auto;padding:12px;white-space:pre-wrap;word-break:break-word">' + escapeHtml(log) + '</pre>' : '')
        + '</div>';
      var pre = target.document.querySelector('pre');
      if (pre) {
        pre.scrollTop = pre.scrollHeight;
      }
    } catch (error) {
      // A popup policy may keep us from writing to the tab.
    }
  }
  window.jobseekerRuntimeWaiting = waitingRuntime;

  // Polls a runtime until its editor answers (building it first if needed).
  function followRuntime(response, target, title, attempt, done) {
    if (attempt > 900) {
      notify('error', 'The runtime did not start within 45 minutes. Check it on the Runtimes page.', title);
      return;
    }
    window.setTimeout(function() {
      $.ajax({url: response.openVsCodeStatusUrl, type: 'POST', dataType: 'json'})
        .done(function(status) {
          if (status && status.ready) {
            var url = status.openVsCodeUrl || response.openVsCodeUrl;
            if (target && !target.closed) {
              target.location.href = url;
            } else {
              window.open(url, '_blank');
            }
            if (done) {
              done(status);
            }
            return;
          }
          waitingRuntime(target, status || {}, title);
          if (status && status.ok === false) {
            notify('error', status.message || (status.build && status.build.message) || 'The runtime failed.', title);
            if (done) {
              done(status);
            }
            return;
          }
          followRuntime(response, target, title, attempt + 1, done);
        })
        .fail(function() { followRuntime(response, target, title, attempt + 1, done); });
    }, attempt === 0 ? 800 : 2500);
  }
  window.jobseekerFollowRuntime = followRuntime;

  function launch(response, target, attempt) {
    if (response.openVsCodeStatusUrl && response.openVsCodeReady === false) {
      var project = selectedProject();
      waitingRuntime(target, {label: response.runtime && response.runtime.label, build: {status: response.runtime && response.runtime.status === 'building' ? 'building' : 'ready'}}, project ? project.name : '');
      followRuntime(response, target, project ? project.name : '', 0, function() {
        if (project) {
          loadRuntime(project.id);
        }
      });
      return;
    }
    var url = response.launchUrls && response.launchUrls.web ? response.launchUrls.web : response.openVsCodeUrl;
    if (response.openVsCodeReady === false && attempt < 30) {
      window.setTimeout(function() {
        $.ajax({url: config.statusUrl, type: 'GET', dataType: 'json', cache: false})
          .done(function(status) {
            launch(status && status.ready ? $.extend({}, response, {openVsCodeReady: true}) : response, target, attempt + 1);
          })
          .fail(function() { launch(response, target, attempt + 1); });
      }, 1500);
      return;
    }
    if (target && !target.closed) {
      target.location.href = url;
    } else {
      window.open(url, '_blank');
    }
  }

  function open() {
    var project = selectedProject();
    if (!project) {
      return;
    }
    // Opened synchronously with the click so popup blockers allow it.
    var target = window.open('about:blank', '_blank');
    if (target) {
      waitingWindow(target);
    }
    var button = $('#projectLauncherOpen');
    var original = button.html();
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + (project.hasGit && !(project.workspace && project.workspace.exists) ? 'Cloning...' : 'Opening...'));
    $.ajax({url: config.openUrl, type: 'POST', dataType: 'json', data: {
      project_id: project.id,
      environment: globalEnvironment(),
      branch_mode: state.branchMode,
      branch: state.branchMode === 'custom' ? $.trim($('#projectLauncherCustomBranch').val() || '') : ''
    }}).done(function(response) {
      launch(response, target, 0);
      var created = response.scaffolded && response.scaffolded.length ? ' Added ' + response.scaffolded.join(', ') + ' to start the project layout.' : '';
      notify('success', response.message + created, project.name);
      load(project.id);
    }).fail(function(xhr) {
      if (target) {
        target.close();
      }
      notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'The project could not be opened.', project.name);
    }).always(function() {
      button.prop('disabled', false).html(original);
    });
  }

  function create(event) {
    event.preventDefault();
    var button = $('#projectLauncherCreateSubmit');
    var original = button.html();
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');
    $.ajax({url: config.createUrl, type: 'POST', dataType: 'json', data: {
      name: $.trim($('#projectLauncherName').val() || ''),
      type: state.createType,
      repository_url: $.trim($('#projectLauncherRepository').val() || ''),
      environment: globalEnvironment()
    }}).done(function(response) {
      notify('success', response.message, 'Project created');
      $('#projectLauncherCreate')[0].reset();
      $('#projectLauncherSearch').val('');
      state.branchMode = 'shared';
      load(response.project.id);
    }).fail(function(xhr) {
      notify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'The project could not be created.', 'New project');
    }).always(function() {
      button.prop('disabled', false).html(original);
    });
  }

  $(function() {
    var modal = $('#projectWorkspaceLauncher');
    if (!modal.length) {
      return;
    }
    // Outside AdminLTE's wrapper so the backdrop stacks correctly.
    modal.appendTo('body');

    $(document).on('click', '#sidebarOpenVsCode', function(event) {
      event.preventDefault();
      modal.modal('show');
      load(state.selectedId);
    });
    // Other pages open the launcher on one project (View Job's "VS Code").
    window.jobseekerOpenProject = function(projectId) {
      state.selectedId = Number(projectId) || state.selectedId;
      modal.modal('show');
      load(state.selectedId);
    };
    modal.on('shown.bs.modal', function() { $('#projectLauncherSearch').trigger('focus'); });
    $('#projectLauncherSearch').on('input', renderList);
    $('#projectLauncherList').on('click', '.project-launcher-item', function() {
      state.selectedId = Number($(this).attr('data-project-id'));
      state.branchMode = 'shared';
      renderList();
      renderDetail();
    }).on('dblclick', '.project-launcher-item', open)
      .on('click', '[data-launcher-new]', function(event) { event.preventDefault(); showCreate(); });
    $('#projectLauncherDetail').on('change', '#projectLauncherRuntimeSelect, input[name="projectLauncherIsolation"], #projectLauncherRuntimeCpus, #projectLauncherRuntimeMemory', saveRuntime)
      .on('click', '#projectLauncherRuntimeStop', stopRuntime)
      .on('click', '#projectLauncherRuntimeRebuild', rebuildRuntime)
      .on('click', '#projectLauncherDevcontainerStarter', addDevcontainerStarter)
      .on('click', '#projectLauncherSampleAdd', addSample)
      .on('click', '#projectLauncherTrustRecheck', function() {
        state.gatewayTrust = null;
        var project = selectedProject();
        if (project) {
          renderRuntime(project.id);
        }
      });
    $('#projectLauncherDetail').on('change', 'input[name="projectLauncherBranchMode"]', function() {
      state.branchMode = this.value;
      renderDetail();
      if (state.branchMode === 'custom') {
        $('#projectLauncherCustomBranch').trigger('focus');
      }
    }).on('input', '#projectLauncherCustomBranch', function() {
      state.customBranch = this.value;
    });
    $('#projectLauncherNew').on('click', showCreate);
    $('#projectLauncherCreateCancel').on('click', renderDetail);
    $('#projectLauncherTypes').on('change', 'input[name="projectLauncherType"]', function() {
      state.createType = this.value;
      renderTypes();
    });
    $('#projectLauncherCreate').on('submit', create);
    $('#projectLauncherOpen').on('click', open);
  });
})(jQuery);
