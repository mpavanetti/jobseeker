(function($) {
  'use strict';

  function normalizeEnvironment(value) {
    if (window.JobSeekerGlobalEnvironment && window.JobSeekerGlobalEnvironment.normalize) {
      return window.JobSeekerGlobalEnvironment.normalize(value || 'all');
    }

    var normalized = $.trim(String(value || '')).toUpperCase();
    if (!normalized || normalized === 'ALL' || normalized === '*') {
      return 'all';
    }
    if (normalized === 'QAS') {
      return 'QA';
    }
    if (normalized === 'PRD' || normalized === 'PRODUCTION') {
      return 'PROD';
    }
    if (normalized === 'HOMOLOG' || normalized === 'HOMOLOGATION') {
      return 'HML';
    }
    return normalized;
  }

  function matchingEnvironmentValue($select, environment) {
    var normalized = normalizeEnvironment(environment);
    var match = 'all';

    if (normalized === 'all') {
      return match;
    }

    $select.find('option').each(function() {
      if (normalizeEnvironment($(this).val()) === normalized) {
        match = $(this).val();
        return false;
      }
    });

    return match;
  }

  function setSecretInputState() {
    var encrypted = $('#encrypted').val() === '1';
    var $input = $('#contextValue');
    var $button = $('#toggleContextValue');

    if (!$input.length) {
      return;
    }

    $input.attr('type', encrypted ? 'password' : 'text');
    $button.toggle(encrypted);
    $button.attr('aria-pressed', 'false').html('<i class="fa fa-eye"></i> Show');
  }

  function initializeValueControls() {
    $('#encrypted').on('change', setSecretInputState);
    $('#toggleContextValue').on('click', function() {
      var $input = $('#contextValue');
      var showing = $input.attr('type') === 'text';
      $input.attr('type', showing ? 'password' : 'text');
      $(this)
        .attr('aria-pressed', showing ? 'false' : 'true')
        .html(showing ? '<i class="fa fa-eye"></i> Show' : '<i class="fa fa-eye-slash"></i> Hide');
    });
    $('#contextCreateForm, #contextEditForm').on('reset', function() {
      window.setTimeout(setSecretInputState, 0);
    });
    setSecretInputState();
  }

  function initializeContextTable() {
    var $table = $('#contextsTable');
    if (!$table.length || !$.fn.DataTable) {
      return;
    }

    var table = $table.DataTable({
      autoWidth: false,
      dom: 'rtp',
      order: [[6, 'desc']],
      pageLength: 20,
      lengthMenu: [10, 20, 50, 100],
      columnDefs: [
        { orderable: false, targets: 'context-actions-column' }
      ],
      language: {
        emptyTable: 'No context variables have been created yet.',
        zeroRecords: 'No contexts match these filters.'
      },
      drawCallback: function() {
        var api = this.api();
        var info = api.page.info();
        var environments = {};
        var activeCount = 0;
        var encryptedCount = 0;

        $(api.rows({ search: 'applied' }).nodes()).each(function() {
          var $row = $(this);
          activeCount += $row.find('.context-badge-active').length ? 1 : 0;
          encryptedCount += $row.find('.context-badge-secret').length ? 1 : 0;
          var environment = $.trim($row.find('td').eq(2).text());
          if (environment) {
            environments[environment] = true;
          }
        });

        $('#contextVisibleCount').text(info.recordsDisplay);
        $('#contextTotalCount').text(info.recordsTotal);
        $('#contextActiveCount').text(activeCount);
        $('#contextEncryptedCount').text(encryptedCount);
        $('#contextEnvironmentCount').text(Object.keys(environments).length);
        $('#contextTableInfo').text(info.recordsDisplay === info.recordsTotal
          ? info.recordsTotal + ' context' + (info.recordsTotal === 1 ? '' : 's')
          : info.recordsDisplay + ' of ' + info.recordsTotal + ' contexts');
        var $pagination = $(api.table().container()).find('.dataTables_paginate');
        if ($pagination.length) {
          $('#contextTablePagination').append($pagination);
        }
      }
    });

    function exactColumnFilter(column, value) {
      var searchValue = value ? '^' + $.fn.dataTable.util.escapeRegex(value) + '$' : '';
      table.column(column).search(searchValue, true, false).draw();
    }

    function applyEnvironment(environment) {
      var $filter = $('#contextEnvironmentFilter');
      var selectedValue = matchingEnvironmentValue($filter, environment);
      $filter.val(selectedValue);
      $('#contextScopeLabel').text(selectedValue === 'all' ? 'All environments' : selectedValue);

      var $formEnvironment = $('#environmentName');
      if (selectedValue !== 'all' && $formEnvironment.length) {
        $formEnvironment.val(selectedValue);
      }
    }

    $('#contextSearch').on('input', function() {
      table.search(this.value).draw();
    });
    $('#contextEnvironmentFilter').on('change', function() {
      var value = $(this).val() || 'all';
      var baseUrl = window.contextDetailsConfig && window.contextDetailsConfig.baseUrl ? window.contextDetailsConfig.baseUrl : window.location.pathname;
      window.location.href = baseUrl + '?environment=' + encodeURIComponent(value);
    });
    $('#contextProjectFilter').on('change', function() {
      exactColumnFilter(3, $(this).val());
    });
    $('#contextStatusFilter').on('change', function() {
      exactColumnFilter(4, $(this).val());
    });
    $('#contextEncryptionFilter').on('change', function() {
      exactColumnFilter(5, $(this).val());
    });
    $('#contextResetFilters').on('click', function() {
      $('#contextSearch').val('');
      $('#contextProjectFilter, #contextStatusFilter, #contextEncryptionFilter').val('');
      table.search('').columns().search('').draw();
      applyEnvironment(window.contextDetailsConfig && window.contextDetailsConfig.selectedEnvironment ? window.contextDetailsConfig.selectedEnvironment : 'all');
    });

    $(document).on('jobseeker:environment-change', function(event, environment) {
      applyEnvironment(environment);
    });

    applyEnvironment(window.contextDetailsConfig && window.contextDetailsConfig.selectedEnvironment ? window.contextDetailsConfig.selectedEnvironment : 'all');

    $('#contextPageLength').on('change', function() {
      table.page.len(parseInt($(this).val(), 10) || 20).draw();
    });

    window.JobSeekerContextTable = table;
  }

  function initializeDeleteAction() {
    $(document).on('click', '.delete-context', function(event) {
      event.preventDefault();
      var $button = $(this);
      var contextId = $button.data('context-id');
      var contextKey = $('<div>').text($button.data('context-key') || 'this context').html();
      var endpoint = (window.contextDetailsConfig && window.contextDetailsConfig.deleteUrl) || (window.baseURL + 'Context/deleteContext');

      alertify.confirm(
        'Delete context variable?',
        '<p>You are about to permanently delete <strong>' + contextKey + '</strong>.</p><p class="text-muted">Jobs that rely on this value may fail the next time they run.</p>',
        function() {
          $.ajax({
            type: 'POST',
            dataType: 'json',
            url: endpoint,
            data: {
              userId: contextId,
              environment: window.contextDetailsConfig && window.contextDetailsConfig.selectedEnvironment ? window.contextDetailsConfig.selectedEnvironment : 'ALL'
            }
          }).done(function(response) {
            if (response.status === true) {
              if (window.JobSeekerContextTable) {
                window.JobSeekerContextTable.row($button.closest('tr')).remove().draw();
              } else {
                $button.closest('tr').remove();
              }
              alertify.success('Context variable deleted.');
            } else if (response.status === 'access') {
              alertify.error('You do not have permission to delete contexts.');
            } else {
              alertify.error('The context variable could not be deleted.');
            }
          }).fail(function() {
            alertify.error('The delete request failed. Please try again.');
          });
        },
        function() {}
      );
    });
  }

  $(function() {
    initializeValueControls();
    initializeContextTable();
    initializeDeleteAction();
  });
})(jQuery);
