/*
 * Project Details > Source code in Git.
 *
 * One repository, one build credential for every environment, and a branch
 * per environment (or one everywhere). Check access lists the repository's
 * branches once, as builds see them, and marks every environment's branch.
 */
(function($) {
  'use strict';

  var config = window.projectGitConfig || {};
  var catalog = $.extend({}, config.catalog || {});
  var globalBranches = config.globalBranches || {DEFAULT: 'main'};
  var environments = config.environments || [];
  var remoteBranches = null;
  var fetchedHostKeys = '';

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  // Mirrors jobseeker-git: connector hosts are compared without the port.
  function repositoryLocation(value) {
    value = $.trim(String(value || ''));
    var scp = value.match(/^[^@\s\/]+@([^:\s\/]+):(.+)$/);
    if (scp) {
      return {host: scp[1].toLowerCase(), port: '', path: scp[2].replace(/^\/+|\/+$/g, '').replace(/\.git$/i, ''), transport: 'ssh'};
    }
    try {
      var parsed = new URL(value);
      if (['http:', 'https:', 'ssh:'].indexOf(parsed.protocol) === -1 || !parsed.hostname) {
        return null;
      }
      return {host: parsed.hostname.toLowerCase(), port: parsed.port, path: parsed.pathname.replace(/^\/+|\/+$/g, '').replace(/\.git$/i, ''), transport: parsed.protocol === 'ssh:' ? 'ssh' : 'http'};
    } catch (error) {
      return null;
    }
  }

  function gitEnabled() {
    return $('#projectGitEnabled').is(':checked');
  }

  function singleBranch() {
    return $('input[name="gitBranchMode"]:checked').val() === 'single';
  }

  function selectedCredential() {
    var value = $('#gitCredentialKey').val() || '';
    return value.indexOf('__new') === 0 ? '' : value;
  }

  function resolveCredential(key, environment) {
    var entry = catalog[key];
    if (!entry) {
      return null;
    }
    if (entry.scopes[environment]) {
      return $.extend({scope: environment}, entry.scopes[environment]);
    }
    return entry.scopes.ALL ? $.extend({scope: 'ALL'}, entry.scopes.ALL) : null;
  }

  // Why a connector cannot clone this URL, or ''. A deploy key also works
  // with an HTTPS URL: jobseeker-git switches it to SSH.
  function credentialProblem(resolved, location) {
    if (!location) {
      return '';
    }
    if (resolved.host && resolved.host !== location.host) {
      return 'it is for ' + resolved.host + ', not ' + location.host;
    }
    if (['token', 'api_key', 'username_password'].indexOf(resolved.authType) !== -1 && location.transport !== 'http') {
      return 'a token needs the HTTPS URL of the repository';
    }
    return '';
  }

  function branchValue(environment) {
    return $.trim($('.project-git-branch-row[data-environment="' + environment + '"] .project-git-branch').val() || '');
  }

  // ProjectGitSettings_model order: the environment, the fallback, the policy.
  function inheritedBranch(environment) {
    var fallback = branchValue('DEFAULT');
    return fallback !== ''
      ? {branch: fallback, source: 'other environments'}
      : {branch: globalBranches[environment] || globalBranches.DEFAULT || 'main', source: 'global policy'};
  }

  function effectiveBranch(environment) {
    if (singleBranch()) {
      return branchValue('DEFAULT') || globalBranches.DEFAULT || 'main';
    }
    return branchValue(environment) || inheritedBranch(environment).branch;
  }

  function renderBranches() {
    var single = singleBranch();
    $('.project-git-environment-row').prop('hidden', single);
    var label = $('.project-git-branch-label');
    label.text(single ? label.data('single') : label.data('multi'));
    $('.project-git-environment-row').each(function() {
      var environment = String($(this).data('environment'));
      var inherited = inheritedBranch(environment);
      $(this).find('.project-git-branch').attr('placeholder', inherited.branch + '  ·  ' + inherited.source);
    });
    $('#projectGitBranchDEFAULT').attr('placeholder', single ? (globalBranches.DEFAULT || 'main') : 'Global policy');
    $('#projectGitBranchFootnote').text(single
      ? 'Every environment runs this branch or tag. A job can still pin its own.'
      : 'Empty fields show what they inherit. Other environments fall back to the global policy (' + $.map(globalBranches, function(branch, environment) { return (environment === 'DEFAULT' ? 'others' : environment) + ' ' + branch; }).join(', ') + '). A job can still pin its own branch or tag.');
    renderBranchStates();
  }

  function renderBranchStates() {
    var missing = {};
    $('.project-git-branch-row').each(function() {
      var row = $(this);
      var environment = String(row.data('environment'));
      var state = row.find('.project-git-branch-state');
      if (!remoteBranches || row.prop('hidden') || (environment === 'DEFAULT' && !singleBranch() && branchValue('DEFAULT') === '')) {
        state.empty();
        return;
      }
      var branch = effectiveBranch(environment);
      if (remoteBranches.indexOf(branch) === -1) {
        (missing[branch] = missing[branch] || []).push(environment === 'DEFAULT' ? (singleBranch() ? 'every environment' : 'other environments') : environment);
      }
      state.html(remoteBranches.indexOf(branch) !== -1
        ? '<span class="is-ok" title="' + escapeHtml(branch) + ' exists"><i class="fa fa-check-circle"></i></span>'
        : '<span class="is-warning" title="The repository has no branch ' + escapeHtml(branch) + '. It can still be a tag."><i class="fa fa-exclamation-triangle"></i></span>');
    });
    // One line instead of reading every icon: which branches are missing where.
    var summary = $('#projectGitBranchSummary');
    var names = Object.keys(missing);
    if (!remoteBranches) {
      summary.prop('hidden', true).empty();
    } else if (!names.length) {
      summary.prop('hidden', false).attr('class', 'project-git-branch-summary is-ok').html('<i class="fa fa-check-circle"></i> Every environment runs a branch that exists.');
    } else {
      summary.prop('hidden', false).attr('class', 'project-git-branch-summary is-warning').html('<i class="fa fa-exclamation-triangle"></i> <span>The repository has no '
        + $.map(names, function(branch) { return '<code>' + escapeHtml(branch) + '</code> (' + escapeHtml(missing[branch].join(', ')) + ')'; }).join(', ')
        + '. Pick a branch from the suggestions, or keep it if it is a tag.</span>');
    }
  }

  function statusLine(level, html) {
    var icon = {ok: 'fa-check-circle', warning: 'fa-exclamation-triangle', error: 'fa-times-circle', muted: 'fa-info-circle', running: 'fa-spinner fa-spin'}[level];
    $('#projectGitAccessStatus').attr('class', 'project-git-access-status is-' + level).html('<i class="fa ' + icon + '"></i> <span>' + html + '</span>');
  }

  function renderAccess() {
    var location = repositoryLocation($('#gitpath').val());
    var key = selectedCredential();
    var url = $.trim($('#gitpath').val() || '');
    var canCheck = !!location;
    if (($('#gitCredentialKey').val() || '').indexOf('__new') === 0) {
      statusLine('muted', 'Finish creating the credential above, or cancel.');
      canCheck = false;
    } else if (url === '') {
      statusLine('muted', 'Enter the repository URL.');
      canCheck = false;
    } else if (!location) {
      statusLine('error', 'Use an <code>https://</code>, <code>ssh://</code> or <code>git@host:org/repo.git</code> URL.');
    } else if (key === '') {
      if (location.transport === 'ssh') {
        statusLine('error', 'SSH URLs always need a key. Add a deploy key, or use the HTTPS URL of a public repository.');
        canCheck = false;
      } else {
        statusLine('muted', 'Builds clone without a credential, so the repository must be public.');
      }
    } else if (!catalog[key]) {
      statusLine('error', '<code>' + escapeHtml(key) + '</code> is not an active Git connector available to all jobs.');
      canCheck = false;
    } else {
      var missing = [];
      var problems = [];
      var scopes = {};
      $.each(environments, function(index, environment) {
        var resolved = resolveCredential(key, environment);
        if (!resolved) {
          missing.push(environment);
          return;
        }
        scopes[resolved.scope] = resolved;
        var problem = credentialProblem(resolved, location);
        if (problem && problems.indexOf(problem) === -1) {
          problems.push(problem);
        }
      });
      var scopeNames = Object.keys(scopes);
      if (missing.length) {
        statusLine('error', '<code>' + escapeHtml(key) + '</code> is only set up for ' + escapeHtml(scopeNames.join(', ') || 'no environment')
          + ', so builds in ' + escapeHtml(missing.join(', ')) + ' cannot clone. Choose a credential for all environments.');
      } else if (problems.length) {
        statusLine('warning', 'Builds cannot use <code>' + escapeHtml(key) + '</code>: ' + escapeHtml(problems.join('; ')) + '.');
      } else {
        var separate = scopeNames.filter(function(name) { return name !== 'ALL'; });
        statusLine('ok', 'Builds in every environment clone with <code>' + escapeHtml(key) + '</code>'
          + (separate.length ? ' (with a separate secret in ' + escapeHtml(separate.join(', ')) + ')' : '') + '.');
      }
    }
    $('#projectGitCheck').prop('disabled', !canCheck);
  }

  function personalAccountFor(location) {
    var host = location.host + (location.port ? ':' + location.port : '');
    var path = location.path.toLowerCase();
    var accounts = $.grep(config.personalAccounts || [], function(account) {
      var prefix = String(account.path_prefix || '').replace(/^\/+|\/+$/g, '').toLowerCase();
      return String(account.host || '').toLowerCase() === host
        && (prefix === '' || path === prefix || path.indexOf(prefix + '/') === 0)
        && (location.transport === 'ssh') === (account.auth_type === 'ssh_key');
    });
    accounts.sort(function(left, right) { return String(right.path_prefix || '').length - String(left.path_prefix || '').length; });
    return accounts.length ? accounts[0] : null;
  }

  function renderNotes() {
    var notes = [];
    var location = repositoryLocation($('#gitpath').val());
    if (location) {
      var account = personalAccountFor(location);
      var where = escapeHtml(location.host + '/' + location.path);
      if (account) {
        var label = account.label || (String(account.provider || 'Git').replace('_', ' ') + (account.username ? ' · ' + account.username : ''));
        notes.push({level: 'ok', html: 'In VS Code you open and push <code>' + where + '</code> as <strong>' + escapeHtml(label) + '</strong>. Each developer uses their own account.'});
      } else {
        var connect = location.host === 'github.com' && config.githubOAuthEnabled
          ? '<a href="' + escapeHtml(config.githubConnectUrl) + '" target="_blank" rel="noopener"><i class="fa fa-github"></i> Connect GitHub</a>'
          : '<a href="' + escapeHtml(config.profileGitUrl) + '" target="_blank" rel="noopener">Add a ' + escapeHtml(location.host) + ' account</a>';
        notes.push({level: 'info', html: 'You have no ' + escapeHtml(location.host) + ' account on your profile, so VS Code can only open this repository if it is public. ' + connect});
      }
    }
    $('#projectGitNotes').html($.map(notes, function(note) {
      var icon = {ok: 'fa-user-circle', info: 'fa-info-circle'}[note.level];
      return '<div class="project-git-note is-' + note.level + '"><i class="fa ' + icon + '"></i><span>' + note.html + '</span></div>';
    }).join(''));
  }

  function render() {
    var enabled = gitEnabled();
    $('#projectGitSettings').toggleClass('is-enabled', enabled);
    $('#projectGitBody').prop('hidden', !enabled);
    $('#projectGitOffWarning').prop('hidden', enabled);
    renderAccess();
    renderNotes();
    renderBranches();
  }

  function resetCheck() {
    remoteBranches = null;
    $('#projectGitBranchOptions').empty();
    renderBranchStates();
  }

  // One listing, as builds see it, checks access and every branch at once.
  function checkAccess() {
    var key = selectedCredential();
    var environment = '';
    if (key !== '') {
      environment = $.grep(environments, function(name) { return resolveCredential(key, name); })[0] || '';
    }
    var button = $('#projectGitCheck');
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Checking');
    statusLine('running', key === '' ? 'Listing branches anonymously...' : 'Listing branches on a Jenkins worker with <code>' + escapeHtml(key) + '</code>, as a build would...');
    $.ajax({url: config.branchesUrl, type: 'POST', dataType: 'json', data: {
      repository_url: $('#gitpath').val() || '',
      credential_key: key,
      environment: environment
    }}).done(function(response) {
      remoteBranches = response.branches || [];
      $('#projectGitBranchOptions').html($.map(remoteBranches, function(branch) { return '<option value="' + escapeHtml(branch) + '">'; }).join(''));
      statusLine('ok', escapeHtml(response.message || 'Access works.') + ' ' + remoteBranches.length + ' branch' + (remoteBranches.length === 1 ? '' : 'es') + (response.latencyMs != null ? ' (' + response.latencyMs + ' ms)' : '') + '.');
      renderBranchStates();
    }).fail(function(xhr) {
      var response = xhr.responseJSON || {};
      resetCheck();
      statusLine('error', escapeHtml(response.message || 'The access check failed.') + (response.detail ? '<br><code>' + escapeHtml(response.detail) + '</code>' : ''));
    }).always(function() {
      button.prop('disabled', false).html('<i class="fa fa-plug"></i> Check access');
    });
  }

  function showNewCredential(kind) {
    $('#projectGitNewToken').prop('hidden', kind !== 'token');
    $('#projectGitNewKey').prop('hidden', kind !== 'key');
    if (kind === 'token') {
      var location = repositoryLocation($('#gitpath').val()) || {};
      var hints = {
        'github.com': 'A fine-grained token with <em>Contents: Read-only</em> on this repository. Leave the username empty.',
        'gitlab.com': 'A project or personal access token with <em>read_repository</em>. Leave the username empty.',
        'bitbucket.org': 'A repository access token with <em>Repositories: Read</em> (leave the username empty), or an API token with your username.',
        'dev.azure.com': 'A personal access token with <em>Code (Read)</em>.'
      };
      $('#projectGitTokenHelp').html((hints[location.host] || 'A read-only token for this repository.') + ' It is encrypted and only builds receive it.');
      $('#projectGitToken').trigger('focus');
    }
    if (kind === 'key') {
      fetchedHostKeys = '';
      $('#projectGitHostKeys').empty();
      $('#projectGitCreateKey').prop('disabled', true);
    }
    renderAccess();
  }

  function closeNewCredential(selectKey) {
    $('#projectGitNewToken, #projectGitNewKey').prop('hidden', true);
    $('#projectGitToken, #projectGitTokenUser').val('');
    var select = $('#gitCredentialKey');
    select.val(selectKey != null ? selectKey : (select.data('previous') || ''));
    select.data('previous', select.val());
    resetCheck();
    render();
  }

  function addCatalogEntry(entry) {
    catalog[entry.key] = entry;
    var scope = entry.scopes.ALL;
    var option = $('<option>').val(entry.key).text(entry.key + ' · ' + scope.host + ' · ' + (scope.authType === 'ssh_key' ? 'deploy key' : 'token'));
    var group = $('#gitCredentialKey optgroup[label="Git connectors"]');
    if (!group.length) {
      group = $('<optgroup label="Git connectors">').insertAfter($('#gitCredentialKey option[value=""]'));
    }
    group.append(option);
  }

  function createCredential(data, button) {
    var originalHtml = button.html();
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving');
    return $.ajax({url: config.credentialUrl, type: 'POST', dataType: 'json', data: $.extend({
      repository_url: $('#gitpath').val() || '',
      project_name: $('#name').val() || ''
    }, data)}).fail(function(xhr) {
      var response = xhr.responseJSON || {};
      statusLine('error', escapeHtml(response.message || 'The credential could not be created.'));
    }).always(function() {
      button.prop('disabled', false).html(originalHtml);
    });
  }

  function deployKeyPage(location) {
    var path = location.path;
    if (location.host === 'github.com') {
      return {url: 'https://github.com/' + path + '/settings/keys/new', label: 'Add it on GitHub', steps: 'Leave <em>Allow write access</em> unchecked.'};
    }
    if (location.host === 'gitlab.com') {
      return {url: 'https://gitlab.com/' + path + '/-/settings/repository#js-deploy-keys-settings', label: 'Add it on GitLab', steps: 'Leave <em>Grant write permissions</em> unchecked.'};
    }
    if (location.host === 'bitbucket.org') {
      return {url: 'https://bitbucket.org/' + path + '/admin/access-keys/', label: 'Add it on Bitbucket', steps: 'Access keys are read-only.'};
    }
    return {url: '', label: '', steps: 'Add it to the repository as a read-only deploy key.'};
  }

  $(function() {
    if (!$('#projectGitSettings').length) {
      return;
    }
    var select = $('#gitCredentialKey');
    select.data('previous', select.val());

    $('#projectGitEnabled').on('change', function() {
      render();
      if (gitEnabled()) {
        $('#gitpath').trigger('focus');
      }
    });
    $('#gitpath').on('input change', function() {
      resetCheck();
      $('#projectGitDeployKey').prop('hidden', true);
      render();
    });
    select.on('change', function() {
      var value = select.val() || '';
      if (value === '__new_token' || value === '__new_key') {
        if (!repositoryLocation($('#gitpath').val())) {
          select.val(select.data('previous') || '');
          statusLine('error', 'Enter the repository URL first.');
          $('#gitpath').trigger('focus');
          return;
        }
        showNewCredential(value === '__new_token' ? 'token' : 'key');
        return;
      }
      select.data('previous', value);
      $('#projectGitNewToken, #projectGitNewKey').prop('hidden', true);
      resetCheck();
      render();
    });
    $('.project-git-new-cancel').on('click', function() {
      closeNewCredential(null);
    });

    $('#projectGitSaveToken').on('click', function() {
      var button = $(this);
      if ($.trim($('#projectGitToken').val() || '') === '') {
        $('#projectGitToken').trigger('focus');
        return;
      }
      createCredential({kind: 'token', token: $('#projectGitToken').val(), username: $('#projectGitTokenUser').val() || ''}, button).done(function(response) {
        addCatalogEntry(response.catalogEntry);
        closeNewCredential(response.key);
        checkAccess();
      });
    });

    $('#projectGitFetchHostKeys').on('click', function() {
      var location = repositoryLocation($('#gitpath').val());
      var button = $(this);
      var sshPort = location.transport === 'ssh' && location.port ? ':' + location.port : '';
      button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Fetching');
      $.ajax({url: config.knownHostsUrl, dataType: 'json', data: {host: location.host + sshPort}}).done(function(response) {
        fetchedHostKeys = response.known_hosts || '';
        $('#projectGitHostKeys').html('<p>Compare these fingerprints with the ones ' + escapeHtml(location.host) + ' publishes before you continue:</p><ul>'
          + $.map(response.fingerprints || [], function(fingerprint) { return '<li><code>' + escapeHtml(fingerprint) + '</code></li>'; }).join('') + '</ul>');
        $('#projectGitCreateKey').prop('disabled', fetchedHostKeys === '');
      }).fail(function(xhr) {
        var response = xhr.responseJSON || {};
        $('#projectGitHostKeys').html('<p class="text-danger">' + escapeHtml(response.message || 'The host keys could not be fetched.') + '</p>');
      }).always(function() {
        button.prop('disabled', false).html('<i class="fa fa-shield"></i> Fetch host keys');
      });
    });

    $('#projectGitCreateKey').on('click', function() {
      var location = repositoryLocation($('#gitpath').val());
      createCredential({kind: 'deploy_key', known_hosts: fetchedHostKeys}, $(this)).done(function(response) {
        addCatalogEntry(response.catalogEntry);
        closeNewCredential(response.key);
        var page = deployKeyPage(location);
        $('#projectGitPublicKey').val(response.publicKey || '');
        $('#projectGitDeployKeyHelp').html((page.url ? '<a href="' + escapeHtml(page.url) + '" target="_blank" rel="noopener">' + escapeHtml(page.label) + ' <i class="fa fa-external-link"></i></a>. ' : '')
          + page.steps + ' Then use <strong>Check access</strong>. Fingerprint <code>' + escapeHtml(response.fingerprint || '') + '</code>.');
        $('#projectGitDeployKey').prop('hidden', false);
      });
    });

    $('#projectGitCopyKey').on('click', function() {
      var field = $('#projectGitPublicKey');
      var done = function() { toastr.success('Public key copied.'); };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(field.val()).then(done);
      } else {
        field.trigger('select');
        document.execCommand('copy');
        done();
      }
    });

    $('#projectGitCheck').on('click', checkAccess);
    $('input[name="gitBranchMode"]').on('change', renderBranches);
    $('.project-git-branch').on('input change', renderBranches);

    // A new token or key that was never saved must not be submitted.
    select.closest('form').on('submit', function() {
      if ((select.val() || '').indexOf('__new') === 0) {
        select.val(select.data('previous') || '');
      }
    }).on('reset', function() {
      setTimeout(function() {
        closeNewCredential(select.val());
        $('#projectGitDeployKey').prop('hidden', true);
      }, 0);
    });
    render();
  });
})(jQuery);
