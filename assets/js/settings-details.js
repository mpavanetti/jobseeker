(function($) {
  'use strict';

  function initializeTable() {
    var $table = $('#settingsDetailsTable');
    if (!$table.length || !$.fn.DataTable) {
      return;
    }

    var table = $table.DataTable({
      autoWidth: false,
      dom: 'rt<"settings-table-footer"<"settings-table-info"i><"settings-table-pagination"p>>',
      order: [[3, 'desc']],
      pageLength: 20,
      lengthMenu: [10, 20, 50, 100],
      columnDefs: [
        { orderable: false, searchable: false, targets: 'settings-actions-column' }
      ],
      language: {
        emptyTable: 'No records have been created yet.',
        zeroRecords: 'No records match these filters.'
      },
      drawCallback: function() {
        var api = this.api();
        var activeCount = 0;
        $(api.rows({search: 'applied'}).nodes()).each(function() {
          activeCount += $(this).find('.context-badge-active').length ? 1 : 0;
        });
        $('#settingsVisibleCount').text(api.page.info().recordsDisplay);
        $('#settingsActiveCount').text(activeCount);
      }
    });

    $('#settingsSearch').on('input', function() {
      table.search(this.value).draw();
    });

    $('#settingsStatusFilter').on('change', function() {
      var value = $(this).val() || '';
      table.column(2).search(value ? '^' + $.fn.dataTable.util.escapeRegex(value) + '$' : '', true, false).draw();
    });

    $('#settingsPageLength').on('change', function() {
      table.page.len(parseInt($(this).val(), 10) || 20).draw();
    });

    $('#settingsResetFilters').on('click', function() {
      $('#settingsSearch, #settingsStatusFilter').val('');
      table.search('').columns().search('').draw();
    });

    window.JobSeekerSettingsTable = table;
  }

  function initializeDelete() {
    $(document).on('click', '.delete-setting', function(event) {
      event.preventDefault();
      var $button = $(this);
      var id = $button.data('setting-id');
      var name = $('<div>').text($button.data('setting-name') || 'this record').html();
      var config = window.settingsDetailsConfig || {};
      var type = config.type || 'record';

      alertify.confirm(
        'Delete ' + type + '?',
        '<p>Permanently delete <strong>' + name + '</strong>?</p><p class="text-muted">Deletion may be blocked when context variables still depend on this ' + type + '.</p>',
        function() {
          $.ajax({
            type: 'POST',
            dataType: 'json',
            url: config.deleteUrl,
            data: {userId: id}
          }).done(function(response) {
            if (response.status === true) {
              if (window.JobSeekerSettingsTable) {
                window.JobSeekerSettingsTable.row($button.closest('tr')).remove().draw();
              }
              alertify.success((type.charAt(0).toUpperCase() + type.slice(1)) + ' deleted.');
            } else if (response.status === 'access') {
              alertify.error('You do not have permission to delete this ' + type + '.');
            } else {
              alertify.error('The ' + type + ' could not be deleted. It may still be in use.');
            }
          }).fail(function() {
            alertify.error('The delete request failed. The ' + type + ' may still be in use.');
          });
        },
        function() {}
      );
    });
  }

  $(function() {
    initializeTable();
    initializeDelete();
  });
})(jQuery);
