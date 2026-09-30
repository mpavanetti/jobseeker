(function ($) {
  'use strict';

  var assets = [];
  try {
    assets = JSON.parse(document.getElementById('dataAssetsPayload').textContent || '[]');
  } catch (error) {
    assets = [];
  }

  // Per source: the formats it can deliver, the Connection types that reach
  // it, and whether a Connection is required (see DataAssets::sourceCatalog).
  var sources = (window.JobSeekerDataAssets && window.JobSeekerDataAssets.sources) || {};
  var defaultFormats = { upload: 'csv', url: 'json', object_storage: 'csv' };
  var fileNameHints = {
    upload: 'customers.csv', url: 'From the URL when empty', google_sheet: 'asset-key.csv when empty',
    database_table: 'table.csv when empty', object_storage: 'From the object path when empty', document_collection: 'collection.jsonl when empty'
  };

  function assetById(id) {
    id = Number(id);
    for (var index = 0; index < assets.length; index += 1) {
      if (Number(assets[index].id) === id) return assets[index];
    }
    return null;
  }

  function slug(value) {
    return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 128);
  }

  function openEditor() {
    $('#assetEditor').removeClass('is-collapsed');
    $('html, body').animate({ scrollTop: Math.max(0, $('#assetEditor').offset().top - 55) }, 180);
  }

  function closeEditor() {
    $('#assetEditor').addClass('is-collapsed');
  }

  function checked(name, value) {
    $('[name="' + name + '"]').prop('checked', false).filter('[value="' + value + '"]').prop('checked', true);
  }

  function setCheckbox(name, enabled) {
    $('input[type="checkbox"][name="' + name + '"]').prop('checked', Boolean(enabled));
  }

  function resetForm() {
    var form = document.getElementById('dataAssetForm');
    if (form) form.reset();
    $('#assetId').val('0');
    $('#assetKey').data('edited', false);
    $('#assetEditorTitle').text('Register data asset');
    $('#saveAssetLabel').text('Publish Data Asset');
    $('#assetJobName').val('*');
    $('#assetSourceType').val('upload');
    $('#assetConnector, #assetSourceUrl, #assetResponsePath, #assetSpreadsheetId, #assetSheetRange, #assetGooglePublicUrl, #assetTableSchema, #assetTableName, #assetObjectPath, #assetCollectionName').val('');
    checked('direction', 'input');
    setCheckbox('is_required', true);
    setCheckbox('is_active', true);
    updateSourceOptions();
    updatePreview();
  }

  function editAsset(asset) {
    if (!asset) return;
    resetForm();
    $('#assetId').val(asset.id);
    $('#assetName').val(asset.name);
    $('#assetKey').val(asset.key).data('edited', true);
    $('#assetEnvironment').val(asset.environment);
    $('#assetJobName').val(asset.job);
    $('#assetSourceType').val(asset.source_type || 'upload');
    updateSourceOptions(asset.format);
    $('#assetConnector').val(asset.connector_key || '');
    $('#assetFileName').val(asset.file_name);
    $('#assetDescription').val(asset.description || '');
    checked('direction', asset.direction);
    setCheckbox('is_required', Number(asset.required) === 1);
    setCheckbox('is_active', Number(asset.active) === 1);
    var options = asset.options || {};
    var source = asset.source || {};
    $('#assetSourceUrl').val(source.url || '');
    $('#assetResponsePath').val(source.response_path || '');
    $('#assetSpreadsheetId').val(source.spreadsheet_id || '');
    $('#assetSheetRange').val(source.range || '');
    $('#assetGooglePublicUrl').val((asset.source_type === 'google_sheet' && source.url) || '');
    if (asset.source_type === 'google_sheet') $('#assetSourceUrl').val('');
    $('#assetTableSchema').val(source.schema || '');
    $('#assetTableName').val(source.table || '');
    $('#assetObjectPath').val(source.path || '');
    $('#assetCollectionName').val(source.collection || '');
    $('#assetDelimiter').val(options.delimiter === '\t' ? '\t' : (options.delimiter || ','));
    $('#assetEncoding').val(options.encoding || 'UTF-8');
    $('#assetHeader').val(options.header === false ? '0' : '1');
    $('#assetSheet').val(options.sheet || '');
    $('#assetEditorTitle').text('Edit ' + asset.name);
    $('#saveAssetLabel').text('Update Data Asset');
    updateFormatOptions();
    updatePreview();
    openEditor();
  }

  function updateFormatOptions() {
    var sourceType = $('#assetSourceType').val() || 'upload';
    var format = $('#assetFormat').val();
    // Delimiter, encoding, and sheet describe files; a sheet or table has its own shape.
    var fileSource = ['upload', 'url', 'object_storage'].indexOf(sourceType) !== -1;
    $('#delimitedOptions').toggle(fileSource && format === 'csv');
    $('#excelOptions').toggle(fileSource && format === 'xlsx');
    $('#assetResponsePathGroup').toggle(format === 'json');
    var accept = {
      csv: '.csv', json: '.json', jsonl: '.jsonl,.ndjson', xlsx: '.xlsx,.xls',
      parquet: '.parquet', xml: '.xml', html: '.html,.htm', txt: '.txt,.log,.dat', binary: ''
    };
    $('#assetFile').attr('accept', accept[format] || '');
  }

  function updateSourceOptions(preferredFormat) {
    var sourceType = $('#assetSourceType').val() || 'upload';
    var source = sources[sourceType] || { formats: {}, connectors: [], connection: 'none', hint: '' };
    $('#uploadSourceOptions').toggle(sourceType === 'upload');
    $('#connectionSourceOptions').toggle(source.connection !== 'none');
    $('#urlSourceOptions').toggle(sourceType === 'url');
    $('#googleSheetSourceOptions').toggle(sourceType === 'google_sheet');
    $('#databaseSourceOptions').toggle(sourceType === 'database_table');
    $('#objectStorageSourceOptions').toggle(sourceType === 'object_storage');
    $('#documentSourceOptions').toggle(sourceType === 'document_collection');

    // Only uploaded files can be written by jobs; every other source is read-only.
    if (sourceType !== 'upload') {
      checked('direction', 'input');
      $('input[name="direction"][value!="input"]').prop('disabled', true);
    } else {
      $('input[name="direction"]').prop('disabled', false);
    }

    // A file source chooses its format; a table, sheet, or collection has one shape.
    var $format = $('#assetFormat');
    var wanted = preferredFormat || $format.val();
    var keys = Object.keys(source.formats || {});
    $format.empty();
    keys.forEach(function (key) { $format.append($('<option>').val(key).text(source.formats[key])); });
    $format.val(keys.indexOf(wanted) !== -1 ? wanted : (keys.indexOf(defaultFormats[sourceType]) !== -1 ? defaultFormats[sourceType] : keys[0]));
    $format.prop('disabled', keys.length < 2);
    $('#assetFormatHelp').text(keys.length < 2 ? 'Set by the source.' : 'The file format jobs read.');
    $('#assetFileName').attr('placeholder', fileNameHints[sourceType] || '');

    var allowed = source.connectors || [];
    var matching = 0;
    $('#assetConnector option').each(function () {
      var type = String($(this).data('type') || '');
      var usable = !type || allowed.indexOf(type) !== -1;
      if (type && usable) matching += 1;
      $(this).prop('disabled', !usable).toggle(usable);
    });
    if ($('#assetConnector option:selected').prop('disabled')) $('#assetConnector').val('');
    $('#assetConnector option[value=""]').text(source.connection === 'required' ? 'Select a Connection' : 'No Connection — public source');
    $('#assetConnectionHint').text((source.hint || '') + (source.connection === 'required' && !matching ? ' None is active in this environment yet.' : ''));
    updateFormatOptions();
    updatePreview();
  }

  function updatePreview() {
    var key = slug($('#assetKey').val()) || 'asset-key';
    var environment = String($('#assetEnvironment').val() || 'ALL').toLowerCase();
    var job = $.trim($('#assetJobName').val() || '*');
    var scope = !job || job === '*' ? 'shared' : encodeURIComponent(job.replace(/\//g, '~'));
    $('#assetUriPreview').text('jobseeker://' + environment + '/' + scope + '/' + key);
    $('.asset-key-example').text(key);
  }

  function syncRoleDefaults() {
    var role = $('input[name="direction"]:checked').val();
    if (role === 'output' && Number($('#assetId').val() || 0) === 0) setCheckbox('is_required', false);
    if (role === 'input' && Number($('#assetId').val() || 0) === 0) setCheckbox('is_required', true);
  }

  function filterRows() {
    var query = $.trim($('#assetSearch').val() || '').toLowerCase();
    var direction = $('#assetDirectionFilter').val();
    var format = $('#assetFormatFilter').val();
    var visible = 0;

    $('.data-asset-row').each(function () {
      var $row = $(this);
      var role = String($row.data('direction'));
      var roleMatches = !direction || role === direction || (direction === 'input' && role === 'input_output') || (direction === 'output' && role === 'input_output');
      var show = (!query || String($row.data('search')).indexOf(query) !== -1) &&
        roleMatches && (!format || String($row.data('format')) === format);
      $row.toggle(show);
      if (show) visible += 1;
    });

    $('#assetVisibleCount').text(visible + ' shown');
    $('#assetEmptyState').toggle(visible === 0);
  }

  function humanFileSize(bytes) {
    bytes = Number(bytes || 0);
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  function renderAssetPreview(payload) {
    var $body = $('#assetPreviewBody').empty();
    var meta = String(payload.file_name || '') + '  ·  ' + String(payload.format || '').toUpperCase() +
      '  ·  v' + Number(payload.version || 0) + '  ·  ' +
      (payload.source_label && !Number(payload.size) ? 'live source' : humanFileSize(payload.size));
    if (payload.source_label) meta += '  ·  ' + payload.source_label;
    if (payload.truncated) meta += '  ·  first sample shown';
    $('#assetPreviewMeta').text(meta);

    if (payload.kind === 'table') {
      var $table = $('<table>', { 'class': 'table table-bordered table-striped table-condensed' });
      var $headRow = $('<tr>');
      $.each(payload.columns || [], function (_, column) { $('<th>').text(column).appendTo($headRow); });
      $table.append($('<thead>').append($headRow));
      var $tbody = $('<tbody>');
      $.each(payload.rows || [], function (_, row) {
        var $row = $('<tr>');
        $.each(row, function (_, cell) { $('<td>').text(cell == null ? '' : String(cell)).appendTo($row); });
        $tbody.append($row);
      });
      $table.append($tbody);
      $body.append($('<div>', { 'class': 'table-responsive' }).append($table));
      if (!payload.rows || payload.rows.length === 0) $body.append($('<p>', { 'class': 'text-muted' }).text('The file contains no data rows.'));
    } else {
      $body.append($('<pre>', { 'class': 'data-asset-preview-text' }).text(payload.text || ''));
    }
  }

  function loadAssetPreview(url, name) {
    $('#assetPreviewTitle').html('<i class="fa fa-eye"></i> ').append(document.createTextNode(name || 'File preview'));
    $('#assetPreviewLoading').show();
    $('#assetPreviewError, #assetPreviewContent').hide();
    $('#assetPreviewBody').empty();
    $('#assetPreviewModal').modal('show');

    $.ajax({ url: url, dataType: 'json', cache: false })
      .done(function (payload) {
        if (!payload || !payload.ok) {
          $('#assetPreviewError').text((payload && payload.message) || 'The file could not be previewed.').show();
          return;
        }
        renderAssetPreview(payload);
        $('#assetPreviewContent').show();
      })
      .fail(function (xhr) {
        var response = xhr.responseJSON || {};
        $('#assetPreviewError').text(response.message || 'The file could not be previewed.').show();
      })
      .always(function () { $('#assetPreviewLoading').hide(); });
  }

  $(function () {
    resetForm();
    if (window.JobSeekerDataAssets && window.JobSeekerDataAssets.initialDirection) {
      $('#assetDirectionFilter').val(window.JobSeekerDataAssets.initialDirection);
      checked('direction', window.JobSeekerDataAssets.initialDirection);
      syncRoleDefaults();
    }
    if (window.JobSeekerDataAssets && window.JobSeekerDataAssets.initialEnvironment) {
      $('#assetEnvironmentFilter, #assetEnvironment').val(window.JobSeekerDataAssets.initialEnvironment);
    }
    filterRows();

    if (window.location.hash === '#assetEditor') openEditor();

    $('#showAssetForm, .show-asset-form').on('click', function () { resetForm(); openEditor(); });
    $('#closeAssetEditor').on('click', closeEditor);
    $('#resetAssetForm').on('click', resetForm);
    $('#assetSourceType').on('change', function () { updateSourceOptions(); });
    $('#assetFormat').on('change', function () { updateFormatOptions(); updatePreview(); });
    $('input[name="direction"]').on('change', syncRoleDefaults);
    $('#assetKey, #assetEnvironment, #assetJobName').on('input change', updatePreview);
    // The key follows the display name as it is typed, until someone edits
    // the key. (Filling it on blur raced the caret: typing into the key field
    // right after the name appended to the generated slug.)
    $('#assetName').on('input', function () { if (!$('#assetKey').data('edited')) $('#assetKey').val(slug(this.value)).trigger('input'); });
    $('#assetKey').on('input', function (event) { if (event.originalEvent) $(this).data('edited', this.value !== ''); });
    $('#assetKey').on('blur', function () { this.value = slug(this.value); updatePreview(); });
    $('#assetFile').on('change', function () { if (this.files && this.files[0] && !$('#assetFileName').val()) $('#assetFileName').val(this.files[0].name); });

    $('.edit-data-asset').on('click', function () { editAsset(assetById($(this).data('id'))); });
    $('.preview-data-asset').on('click', function () { loadAssetPreview($(this).data('url'), $(this).data('name')); });
    $('.delete-data-asset').on('click', function () {
      $('#deleteAssetId').val($(this).data('id'));
      $('#deleteAssetName').text($(this).data('name'));
      $('#deleteAssetFileOption').toggle(String($(this).data('managed')) === '1').find('input').prop('checked', false);
      $('#deleteAssetModal').modal('show');
    });

    $('#assetSearch').on('input', filterRows);
    $('#assetEnvironmentFilter').on('change', function () {
      var baseUrl = window.JobSeekerDataAssets && window.JobSeekerDataAssets.baseUrl ? window.JobSeekerDataAssets.baseUrl : window.location.pathname;
      window.location.href = baseUrl + '?environment=' + encodeURIComponent($(this).val() || 'ALL');
    });
    $('#assetDirectionFilter, #assetFormatFilter').on('change', filterRows);
    $('#clearAssetFilters').on('click', function () {
      $('#assetSearch, #assetDirectionFilter, #assetFormatFilter').val('');
      $('#assetEnvironmentFilter').val(window.JobSeekerDataAssets && window.JobSeekerDataAssets.initialEnvironment ? window.JobSeekerDataAssets.initialEnvironment : 'ALL');
      filterRows();
    });
  });
})(jQuery);
