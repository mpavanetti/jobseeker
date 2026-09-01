(function($) {
  'use strict';

  var config = window.JobSeekerVisualizationSources || {};
  var endpoints = config.endpoints || {};
  var numericTypes = ['tinyint','smallint','mediumint','int','integer','bigint','decimal','numeric','float','double','real','smallserial','serial','bigserial','money'];
  var dateTypes = ['date','datetime','timestamp','timestamp without time zone','timestamp with time zone'];

  function escapeHtml(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }
  function notify(status, message) {
    var $notice = $('#vizSourceNotice').removeClass('alert-success alert-danger').addClass(status ? 'alert-success' : 'alert-danger').html('<i class="fa ' + (status ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + escapeHtml(message)).show();
    window.scrollTo({ top: Math.max(0, $notice.offset().top - 90), behavior: 'smooth' });
  }
  function errorMessage(xhr, fallback) { return xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fallback; }
  function busy($button, isBusy, icon) { $button.prop('disabled', isBusy).find('i').attr('class', isBusy ? 'fa fa-circle-o-notch fa-spin' : 'fa ' + icon); }

  $('#vizDatasetConnection').on('change', function() {
    var id = $(this).val();
    var $table = $('#vizDatasetTable').prop('disabled', true).html('<option>Discovering tables…</option>');
    $('#vizColumnWorkbench').hide();
    $('#vizSaveDataset').prop('disabled', true);
    if (!id) { $table.html('<option>Choose a connection first…</option>'); return; }
    $.getJSON(endpoints.tables + encodeURIComponent(id)).done(function(payload) {
      $table.empty().append($('<option>', { value: '' }).text('Choose a table…'));
      $.each(payload.data || [], function(_, table) {
        var value = JSON.stringify([table.table_schema, table.table_name]);
        $table.append($('<option>', { value: value }).text(table.table_schema + '.' + table.table_name));
      });
      $table.prop('disabled', false);
      if (!(payload.data || []).length) $table.html('<option>No base tables discovered</option>').prop('disabled', true);
    }).fail(function(xhr) { $table.html('<option>Table discovery failed</option>'); notify(false, errorMessage(xhr, 'Tables could not be discovered.')); });
  });

  $('#vizDatasetTable').on('change', function() {
    var value = $(this).val();
    if (!value) { $('#vizColumnWorkbench').hide(); $('#vizSaveDataset').prop('disabled', true); return; }
    var table;
    try { table = JSON.parse(value); } catch (ignore) { return; }
    $('#vizDatasetSchema').val(table[0]);
    $('#vizDatasetTableName').val(table[1]);
    $('#vizColumnWorkbench').show().addClass('is-loading');
    $('#vizDimensionColumns,#vizMeasureColumns').html('<div class="viz-column-loading"><i class="fa fa-circle-o-notch fa-spin"></i> Discovering columns…</div>');
    $.getJSON(endpoints.columns + encodeURIComponent($('#vizDatasetConnection').val()), { schema: table[0], table: table[1] }).done(function(payload) {
      renderColumns(payload.data || []);
    }).fail(function(xhr) { $('#vizColumnWorkbench').hide(); notify(false, errorMessage(xhr, 'Columns could not be discovered.')); });
  });

  function renderColumns(columns) {
    var $dimensions = $('#vizDimensionColumns').empty();
    var $measures = $('#vizMeasureColumns').empty();
    var $time = $('#vizTimeColumn').html('<option value="">No global time filter</option>');
    var $environment = $('#vizEnvironmentColumn').html('<option value="">No environment filter</option>');
    $.each(columns, function(_, column) {
      var type = String(column.data_type || '').toLowerCase();
      var isNumeric = numericTypes.indexOf(type) !== -1;
      var isDate = dateTypes.indexOf(type) !== -1;
      var label = '<label class="viz-column-option"><input type="checkbox" name="dimension_columns[]" value="' + escapeHtml(column.column_name) + '"><span><strong>' + escapeHtml(column.column_name) + '</strong><small>' + escapeHtml(type) + '</small></span></label>';
      $dimensions.append(label);
      if (isNumeric) $measures.append('<label class="viz-column-option"><input type="checkbox" name="measure_columns[]" value="' + escapeHtml(column.column_name) + '"><span><strong>' + escapeHtml(column.column_name) + '</strong><small>' + escapeHtml(type) + ' · sum + average</small></span></label>');
      if (isDate) $time.append($('<option>', { value: column.column_name }).text(column.column_name));
      $environment.append($('<option>', { value: column.column_name }).text(column.column_name));
    });
    if (!$measures.children().length) $measures.html('<div class="viz-column-loading">No numeric columns. Row count will still be available.</div>');
    $('#vizColumnCount').text(columns.length + (columns.length === 1 ? ' column' : ' columns'));
    $('#vizColumnWorkbench').removeClass('is-loading');
    $('#vizSaveDataset').prop('disabled', false);
  }

  $('#vizDatasetForm').on('submit', function(event) {
    event.preventDefault();
    var $button = $('#vizSaveDataset');
    busy($button, true, 'fa-cloud-upload');
    $.post(endpoints.saveDataset, $(this).serialize()).done(function(payload) {
      if (payload && payload.status) { notify(true, payload.message); window.setTimeout(function() { window.location.reload(); }, 650); }
      else notify(false, payload && payload.message ? payload.message : 'Dataset could not be published.');
    }).fail(function(xhr) { notify(false, errorMessage(xhr, 'Dataset could not be published.')); }).always(function() { busy($button, false, 'fa-cloud-upload'); });
  });

  $('.viz-delete-dataset').on('click', function() {
    var id = $(this).closest('[data-dataset-id]').data('dataset-id');
    alertify.confirm('Remove connected dataset', 'Remove this dataset from Insight Studio? Existing dashboards using it will stop loading.', function() {
      $.post(endpoints.deleteDataset, { id: id }).done(function(payload) { if (payload.status) window.location.reload(); else notify(false, payload.message); }).fail(function(xhr) { notify(false, errorMessage(xhr, 'Dataset could not be removed.')); });
    }, function() {});
  });
})(jQuery);
