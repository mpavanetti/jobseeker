/*
 * Sidebar VS Code launcher: choose or create a project and open your own
 * workspace of it in OpenVSCode (see ProjectWorkspaceTrait).
 */
(function($) {
  'use strict';

  var config = window.jobseekerProjectLauncher || {};
  var state = {projects: [], types: {}, selectedId: null, environment: 'DEV', loading: null, branchMode: 'shared', customBranch: '', createType: 'python'};
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

  function projectMeta(project) {
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
    html.push('<p class="project-launcher-layout"><i class="fa fa-sitemap"></i> <code>jobs/&lt;job&gt;/</code> one folder per job · <code>shared/</code> code the jobs share'
      + (project.type === 'python' ? ' · task <strong>JobSeeker: new job</strong> adds one' : '') + '</p>');
    detail.html(html.join(''));
    $('#projectLauncherOpen').prop('disabled', false);
    $('#projectLauncherCreateJob').prop('hidden', !(workspace.jobs > 0) || project.type !== 'python')
      .attr('href', config.jobCreationUrl + '?project=' + encodeURIComponent(project.id));
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

  function launch(response, target, attempt) {
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
    modal.on('shown.bs.modal', function() { $('#projectLauncherSearch').trigger('focus'); });
    $('#projectLauncherSearch').on('input', renderList);
    $('#projectLauncherList').on('click', '.project-launcher-item', function() {
      state.selectedId = Number($(this).attr('data-project-id'));
      state.branchMode = 'shared';
      renderList();
      renderDetail();
    }).on('dblclick', '.project-launcher-item', open)
      .on('click', '[data-launcher-new]', function(event) { event.preventDefault(); showCreate(); });
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
