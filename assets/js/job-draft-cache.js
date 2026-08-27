(function(window, $) {
  'use strict';

  var version = 1;
  var userId = String(window.jobseekerUserId == null ? 'anonymous' : window.jobseekerUserId);
  var storageKey = 'jobseeker.jobCreation.drafts.v' + version + '.user.' + userId;

  function storageAvailable() {
    try {
      var testKey = storageKey + '.test';
      window.localStorage.setItem(testKey, '1');
      window.localStorage.removeItem(testKey);
      return true;
    } catch (error) {
      return false;
    }
  }

  function read() {
    if (! storageAvailable()) {
      return null;
    }

    try {
      var value = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
      if (! value || value.version !== version || ! Array.isArray(value.drafts)) {
        return null;
      }
      return value;
    } catch (error) {
      return null;
    }
  }

  function newId() {
    return 'draft-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
  }

  function clear() {
    if (! storageAvailable()) {
      return {ok: false, message: 'Draft storage is unavailable.'};
    }

    window.localStorage.removeItem(storageKey);
    $(document).trigger('jobseeker:draft-cache-change', [null]);
    return {ok: true};
  }

  function save(drafts, activeIndex) {
    if (! storageAvailable() || ! Array.isArray(drafts) || ! drafts.length) {
      return {ok: false, message: 'Draft storage is unavailable.'};
    }

    var now = new Date().toISOString();
    var savedDrafts = drafts.map(function(draft, index) {
      var saved = $.extend(true, {}, draft || {});
      saved._cacheId = saved._cacheId || newId();
      if (index === activeIndex) {
        saved._cacheUpdatedAt = now;
      } else {
        saved._cacheUpdatedAt = saved._cacheUpdatedAt || now;
      }
      draft._cacheId = saved._cacheId;
      draft._cacheUpdatedAt = saved._cacheUpdatedAt;
      return saved;
    });
    var activeDraft = savedDrafts[Math.max(0, Math.min(parseInt(activeIndex, 10) || 0, savedDrafts.length - 1))];
    var value = {
      version: version,
      updatedAt: now,
      activeDraftId: activeDraft ? activeDraft._cacheId : '',
      drafts: savedDrafts
    };

    try {
      window.localStorage.setItem(storageKey, JSON.stringify(value));
      $(document).trigger('jobseeker:draft-cache-change', [value]);
      return {ok: true, value: value};
    } catch (error) {
      return {ok: false, message: 'Draft storage is full or unavailable.'};
    }
  }

  function removeByNames(names, removeUnnamed) {
    var cached = read();
    if (! cached) {
      return {ok: true};
    }

    var removedNames = {};
    (Array.isArray(names) ? names : [names]).forEach(function(name) {
      name = $.trim(String(name || ''));
      if (name) {
        removedNames[name] = true;
      }
    });
    var remaining = cached.drafts.filter(function(draft) {
      var name = $.trim(String(draft && draft.job_name || ''));
      return ! removedNames[name] && ! (removeUnnamed && ! name);
    });

    if (! remaining.length) {
      return clear();
    }

    var activeIndex = 0;
    remaining.some(function(draft, index) {
      if (draft._cacheId === cached.activeDraftId) {
        activeIndex = index;
        return true;
      }
      return false;
    });
    return save(remaining, activeIndex);
  }

  function removeByIds(ids) {
    var cached = read();
    if (! cached) {
      return {ok: true};
    }

    var removedIds = {};
    (Array.isArray(ids) ? ids : [ids]).forEach(function(id) {
      id = $.trim(String(id || ''));
      if (id) {
        removedIds[id] = true;
      }
    });

    var remaining = cached.drafts.filter(function(draft) {
      return ! removedIds[$.trim(String(draft && draft._cacheId || ''))];
    });

    if (! remaining.length) {
      return clear();
    }

    var activeIndex = 0;
    remaining.some(function(draft, index) {
      if (draft._cacheId === cached.activeDraftId) {
        activeIndex = index;
        return true;
      }
      return false;
    });
    return save(remaining, activeIndex);
  }

  function targetDraftId() {
    try {
      return new URL(window.location.href).searchParams.get('draft') || '';
    } catch (error) {
      var match = String(window.location.search || '').match(/[?&]draft=([^&]+)/);
      return match ? decodeURIComponent(match[1]) : '';
    }
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function(character) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[character];
    });
  }

  function draftUrl(draft) {
    var root = window.baseURL || '/';
    var url = root + 'jobCreation?draft=' + encodeURIComponent(draft._cacheId || '');
    var environment = $.trim(String(draft.environment || ''));
    if (environment && environment !== '0') {
      url += '&environment=' + encodeURIComponent(environment);
    }
    return url;
  }

  function formatUpdatedAt(value) {
    var timestamp = Date.parse(value || '');
    if (isNaN(timestamp)) {
      return 'Saved locally';
    }

    return 'Saved ' + new Date(timestamp).toLocaleString();
  }

  function render(value) {
    var container = $('#sidebarCachedJobsList');
    if (! container.length) {
      return;
    }

    value = value && Array.isArray(value.drafts) ? value : read();
    var drafts = value && Array.isArray(value.drafts) ? value.drafts : [];
    $('#sidebarCachedJobsCount').text(drafts.length ? '(' + drafts.length + ')' : '');
    $('#sidebarCachedJobsClear').toggle(drafts.length > 0);

    if (! drafts.length) {
      container.html('<div class="jobseeker-sidebar-running-empty">No cached job drafts.</div>');
      return;
    }

    var html = drafts.map(function(draft, index) {
      var name = $.trim(String(draft.job_name || '')) || 'Untitled draft ' + (index + 1);
      var environment = $.trim(String(draft.environment || ''));
      var detail = environment && environment !== '0' ? environment + ' - ' : '';
      detail += formatUpdatedAt(draft._cacheUpdatedAt || value.updatedAt);
      return '<div class="jobseeker-sidebar-cached-row">' +
        '<a class="jobseeker-sidebar-running-build jobseeker-sidebar-cached-build" href="' + escapeHtml(draftUrl(draft)) + '" title="Continue editing ' + escapeHtml(name) + '">' +
          '<strong><i class="fa fa-file-text-o"></i> ' + escapeHtml(name) + '</strong>' +
          '<small>' + escapeHtml(detail) + '</small>' +
        '</a>' +
        '<button type="button" class="jobseeker-sidebar-cached-delete" data-draft-id="' + escapeHtml(draft._cacheId || '') + '" data-draft-name="' + escapeHtml(name) + '" title="Delete cached draft ' + escapeHtml(name) + '"><i class="fa fa-trash"></i></button>' +
      '</div>';
    }).join('');

    container.html(html);
  }

  window.JobSeekerDraftCache = {
    clear: clear,
    key: storageKey,
    newId: newId,
    read: read,
    removeByIds: removeByIds,
    removeByNames: removeByNames,
    render: render,
    save: save,
    targetDraftId: targetDraftId
  };

  $(function() {
    render();

    function confirmDelete(title, message, callback) {
      if (window.alertify && window.alertify.confirm) {
        window.alertify.confirm(title, message, callback, function() {});
        return;
      }

      if (window.confirm($('<div>').html(message).text())) {
        callback();
      }
    }

    function refreshCreationPageIfNeeded() {
      if (/\/jobcreation\/?$/i.test(window.location.pathname || '')) {
        window.jobseekerDraftCacheSkipBeforeUnload = true;
        window.location.reload();
      }
    }

    $(document).on('click', '.jobseeker-sidebar-cached-delete', function(event) {
      event.preventDefault();
      event.stopPropagation();
      var id = $(this).data('draft-id') || '';
      var name = $(this).data('draft-name') || 'this draft';

      confirmDelete(
        'Delete cached draft?',
        '<p>Delete the locally cached draft <strong>' + escapeHtml(name) + '</strong>?</p><p class="text-muted">This does not delete any Jenkins job.</p>',
        function() {
          var result = removeByIds([id]);
          if (result.ok) {
            if (window.toastr) {
              window.toastr.success('Cached draft deleted.');
            }
            refreshCreationPageIfNeeded();
          }
        }
      );
    });

    $('#sidebarCachedJobsClear').on('click', function() {
      confirmDelete(
        'Clear all cached drafts?',
        '<p>Delete every locally cached Job Creation draft for this user?</p><p class="text-muted">Created Jenkins jobs are not affected.</p>',
        function() {
          var result = clear();
          if (result.ok) {
            if (window.toastr) {
              window.toastr.success('All cached drafts cleared.');
            }
            refreshCreationPageIfNeeded();
          }
        }
      );
    });

    $(window).on('storage', function(event) {
      var original = event.originalEvent || event;
      if (original.key === storageKey) {
        render();
      }
    });
    $(document).on('jobseeker:draft-cache-change', function(event, value) {
      render(value);
    });
  });
})(window, window.jQuery);
