(function ($) {
  'use strict';

  var assets = [];
  try {
    assets = JSON.parse(document.getElementById('dataAssetsPayload').textContent || '[]');
  } catch (error) {
    assets = [];
  }

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
    $('#assetEditorTitle').text('Register data asset');
    $('#saveAssetLabel').text('Publish Data Asset');
    $('#assetJobName').val('*');
    checked('direction', 'input');
    setCheckbox('is_required', true);
    setCheckbox('is_active', true);
    updateFormatOptions();
    updatePreview();
  }

  function editAsset(asset) {
    if (!asset) return;
    resetForm();
    $('#assetId').val(asset.id);
    $('#assetName').val(asset.name);
    $('#assetKey').val(asset.key);
    $('#assetEnvironment').val(asset.environment);
    $('#assetJobName').val(asset.job);
    $('#assetFormat').val(asset.format);
    $('#assetFileName').val(asset.file_name);
    $('#assetDescription').val(asset.description || '');
    checked('direction', asset.direction);
    setCheckbox('is_required', Number(asset.required) === 1);
    setCheckbox('is_active', Number(asset.active) === 1);
    var options = asset.options || {};
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
    var format = $('#assetFormat').val();
    $('#delimitedOptions').toggle(format === 'csv');
    $('#excelOptions').toggle(format === 'xlsx');
    var accept = {
      csv: '.csv', json: '.json', jsonl: '.jsonl,.ndjson', xlsx: '.xlsx,.xls',
      parquet: '.parquet', xml: '.xml', txt: '.txt,.log,.dat', binary: ''
    };
    $('#assetFile').attr('accept', accept[format] || '');
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
      '  ·  v' + Number(payload.version || 0) + '  ·  ' + humanFileSize(payload.size);
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
    $('#assetFormat').on('change', function () { updateFormatOptions(); updatePreview(); });
    $('input[name="direction"]').on('change', syncRoleDefaults);
    $('#assetKey, #assetEnvironment, #assetJobName').on('input change', updatePreview);
    $('#assetName').on('blur', function () { if (!$('#assetKey').val()) $('#assetKey').val(slug(this.value)).trigger('input'); });
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
