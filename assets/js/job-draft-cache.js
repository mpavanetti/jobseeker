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

    if (! drafts.length) {
      container.html('<div class="jobseeker-sidebar-running-empty">No cached job drafts.</div>');
      return;
    }

    var html = drafts.map(function(draft, index) {
      var name = $.trim(String(draft.job_name || '')) || 'Untitled draft ' + (index + 1);
      var environment = $.trim(String(draft.environment || ''));
      var detail = environment && environment !== '0' ? environment + ' - ' : '';
      detail += formatUpdatedAt(draft._cacheUpdatedAt || value.updatedAt);
      return '<a class="jobseeker-sidebar-running-build jobseeker-sidebar-cached-build" href="' + escapeHtml(draftUrl(draft)) + '" title="Continue editing ' + escapeHtml(name) + '">' +
        '<strong><i class="fa fa-file-text-o"></i> ' + escapeHtml(name) + '</strong>' +
        '<small>' + escapeHtml(detail) + '</small>' +
      '</a>';
    }).join('');

    container.html(html);
  }

  window.JobSeekerDraftCache = {
    clear: clear,
    key: storageKey,
    newId: newId,
    read: read,
    removeByNames: removeByNames,
    render: render,
    save: save,
    targetDraftId: targetDraftId
  };

  $(function() {
    render();
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
