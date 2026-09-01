(function($) {
  'use strict';

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

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
      dom: 'rt<"context-datatable-footer"ip>',
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
        var $container = $(api.table().container());
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
        $('#contextActiveCount').text(activeCount);
        $('#contextEncryptedCount').text(encryptedCount);
        $('#contextEnvironmentCount').text(Object.keys(environments).length);
        $container.find('.dataTables_info').text(info.recordsDisplay === info.recordsTotal
          ? info.recordsTotal + ' context' + (info.recordsTotal === 1 ? '' : 's')
          : info.recordsDisplay + ' of ' + info.recordsTotal + ' contexts');
        $container.find('.dataTables_paginate').toggle(info.pages > 1);
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

  function initializeContextComparison() {
    var config = window.contextDetailsConfig || {};
    var rows = Array.isArray(config.comparisonRows) ? config.comparisonRows : [];
    var environments = Array.isArray(config.comparisonEnvironments) ? config.comparisonEnvironments.slice() : [];
    var $project = $('#contextCompareProject');
    var $key = $('#contextCompareKey');
    var $table = $('#contextCompareTable');
    var $empty = $('#contextComparisonEmpty');

    if (!$project.length || !$table.length) {
      return;
    }

    environments.sort(function(left, right) {
      return String(left).localeCompare(String(right));
    });

    function uniqueValues(values) {
      var seen = {};
      return values.filter(function(value) {
        var key = String(value);
        if (seen[key]) {
          return false;
        }
        seen[key] = true;
        return true;
      });
    }

    function option(value, text) {
      return $('<option>').val(value).text(text == null ? value : text);
    }

    function projects() {
      return uniqueValues(rows.map(function(row) { return row.project; })).sort(function(left, right) {
        return String(left).localeCompare(String(right));
      });
    }

    function keysForProject(project) {
      return uniqueValues(rows.filter(function(row) {
        return row.project === project;
      }).map(function(row) {
        return row.key;
      })).sort(function(left, right) {
        return String(left).localeCompare(String(right));
      });
    }

    function selectedRows() {
      var project = $project.val();
      var key = $key.val();
      var result = {};
      rows.forEach(function(row) {
        if (row.project !== project || row.key !== key) {
          return;
        }
        var environment = normalizeEnvironment(row.environment);
        if (!result[environment] || String(row.updated || '') > String(result[environment].updated || '')) {
          result[environment] = row;
        }
      });
      return result;
    }

    function comparisonCell(row, field) {
      if (!row) {
        return '<span class="context-compare-missing">Not configured</span>';
      }
      if (field === 'value') {
        return row.encrypted
          ? '<span class="context-badge context-badge-secret"><i class="fa fa-lock"></i> Encrypted value</span>'
          : '<code class="context-compare-value" title="' + escapeHtml(row.value) + '">' + escapeHtml(row.value) + '</code>';
      }
      if (field === 'status') {
        return '<span class="context-badge ' + (row.active ? 'context-badge-active' : 'context-badge-inactive') + '">' + (row.active ? 'Active' : 'Inactive') + '</span>';
      }
      if (field === 'protection') {
        return row.encrypted
          ? '<span class="context-badge context-badge-secret"><i class="fa fa-lock"></i> Encrypted</span>'
          : '<span class="context-row-meta">Standard</span>';
      }
      return escapeHtml(row[field] || 'Not available');
    }

    function comparisonSignature(row, field) {
      if (!row) {
        return null;
      }
      if (field === 'value') {
        return row.encrypted ? 'encrypted' : 'value:' + row.value;
      }
      if (field === 'status') {
        return row.active ? 'active' : 'inactive';
      }
      if (field === 'protection') {
        return row.encrypted ? 'encrypted' : 'standard';
      }
      return null;
    }

    function appendComparisonRow($body, label, field, environmentRows) {
      var signatures = uniqueValues(environments.map(function(environment) {
        return comparisonSignature(environmentRows[normalizeEnvironment(environment)], field);
      }).filter(function(signature) {
        return signature !== null;
      }));
      var $row = $('<tr>').toggleClass('context-compare-different', signatures.length > 1);
      $row.append($('<th scope="row">').text(label));
      environments.forEach(function(environment) {
        $row.append($('<td>').html(comparisonCell(environmentRows[normalizeEnvironment(environment)], field)));
      });
      $body.append($row);
    }

    function renderComparison() {
      var environmentRows = selectedRows();
      var configuredCount = Object.keys(environmentRows).length;
      var hasKey = Boolean($key.val());
      $empty.prop('hidden', hasKey);
      $table.prop('hidden', !hasKey);
      if (!hasKey) {
        $('#contextComparisonSummary').text('No context keys are available for this project.');
        return;
      }

      var $headRow = $('<tr>').append($('<th scope="col">').text('Attribute'));
      environments.forEach(function(environment) {
        $headRow.append($('<th scope="col">').text(environment));
      });
      $table.find('thead').empty().append($headRow);

      var $body = $table.find('tbody').empty();
      appendComparisonRow($body, 'Value', 'value', environmentRows);
      appendComparisonRow($body, 'Status', 'status', environmentRows);
      appendComparisonRow($body, 'Protection', 'protection', environmentRows);
      appendComparisonRow($body, 'Updated', 'updated', environmentRows);
      appendComparisonRow($body, 'Owner', 'owner', environmentRows);
      $('#contextComparisonSummary').text(configuredCount + ' of ' + environments.length + ' environments configured for ' + $key.val() + '.');
    }

    function populateKeys() {
      var keys = keysForProject($project.val());
      $key.empty();
      keys.forEach(function(key) {
        $key.append(option(key));
      });
      renderComparison();
    }

    $project.empty();
    projects().forEach(function(project) {
      $project.append(option(project));
    });
    $project.on('change', populateKeys);
    $key.on('change', renderComparison);
    populateKeys();

    $('[data-context-view]').on('click', function(event) {
      event.preventDefault();
      var view = $(this).data('context-view');
      $('[data-context-view]').parent().removeClass('active');
      $(this).parent().addClass('active');
      $('#contextManagePanel').prop('hidden', view !== 'manage');
      $('#contextComparePanel').prop('hidden', view !== 'compare');
      if (view === 'compare') {
        renderComparison();
      }
    });
  }

  $(function() {
    initializeValueControls();
    initializeContextTable();
    initializeDeleteAction();
    initializeContextComparison();
  });
})(jQuery);
