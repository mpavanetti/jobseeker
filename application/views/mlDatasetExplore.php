<?php
$cols = isset($profile['columns']) && is_array($profile['columns']) ? $profile['columns'] : array();
$sample = isset($profile['sample']) && is_array($profile['sample']) ? $profile['sample'] : array('columns' => array(), 'rows' => array());
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/ml-platform.css?v=2">
<div class="content-wrapper">
  <section class="content-header"><h1><?php echo html_escape($dataset->name); ?> <small>dataset explorer · <?php echo html_escape($dataset->dataset_key); ?></small></h1></section>
  <section class="content">

    <div class="box box-solid"><div class="box-body">
      <form method="get" class="form-inline">
        <label>Version</label>
        <select name="version" class="form-control input-sm" onchange="this.form.submit()">
          <?php foreach ($versions as $v): ?>
            <option value="<?php echo (int) $v->version; ?>" <?php echo $version && $v->version == $version->version ? 'selected' : ''; ?>>
              v<?php echo (int) $v->version; ?> — <?php echo html_escape($v->source_type); ?> — <?php echo $v->row_count === NULL ? '?' : number_format($v->row_count); ?> rows — <?php echo html_escape($v->created_at); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <a class="btn btn-sm btn-default" href="<?php echo base_url(); ?>machine-learning/datasets/download/<?php echo $version ? (int) $version->id : 0; ?>">Download</a>
        <span style="margin-left:16px">Drift</span>
        <select id="cmpA" class="form-control input-sm"><?php foreach ($versions as $v): ?><option><?php echo (int) $v->version; ?></option><?php endforeach; ?></select>
        <span>vs</span>
        <select id="cmpB" class="form-control input-sm"><?php foreach ($versions as $v): ?><option><?php echo (int) $v->version; ?></option><?php endforeach; ?></select>
        <button type="button" class="btn btn-sm btn-primary" id="cmpBtn">Compare</button>
      </form>
    </div></div>

    <?php if ($version): ?>
    <div class="ml-tiles">
      <div class="ml-tile"><div class="ml-tile-label">Rows</div><div class="ml-tile-value"><?php echo $version->row_count === NULL ? '?' : number_format($version->row_count); ?></div></div>
      <div class="ml-tile"><div class="ml-tile-label">Columns</div><div class="ml-tile-value"><?php echo (int) $version->column_count; ?></div></div>
      <div class="ml-tile"><div class="ml-tile-label">Size</div><div class="ml-tile-value"><?php echo number_format(($version->size_bytes ?? 0) / 1024, 0); ?> KB</div></div>
      <div class="ml-tile"><div class="ml-tile-label">Profile</div><div class="ml-tile-value ml-status <?php echo html_escape($version->profile_status); ?>" style="font-size:16px"><?php echo html_escape($version->profile_status); ?></div></div>
      <div class="ml-tile"><div class="ml-tile-label">Source</div><div class="ml-tile-value" style="font-size:16px"><?php echo html_escape($version->source_type); ?></div></div>
    </div>

    <div id="driftResult" style="display:none" class="box box-warning"><div class="box-header with-border"><h3 class="box-title">Version drift</h3></div><div class="box-body"></div></div>

    <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Column profiles</h3></div>
      <div class="box-body">
        <div class="ml-grid-charts">
          <?php foreach ($cols as $name => $c): ?>
            <div class="box box-default" style="margin:0"><div class="box-header with-border">
              <h3 class="box-title"><?php echo html_escape($name); ?> <span class="ml-badge"><?php echo html_escape($c['type'] ?? '?'); ?></span></h3></div>
              <div class="box-body">
                <div class="ml-kv"><span>missing</span><span><?php echo html_escape($c['missing'] ?? 0); ?> (<?php echo round(($c['missing_rate'] ?? 0) * 100, 1); ?>%)</span></div>
                <?php if (($c['type'] ?? '') === 'numeric'): ?>
                  <div class="ml-kv"><span>mean / std</span><span><?php echo html_escape(round($c['mean'] ?? 0, 3)); ?> / <?php echo html_escape(round($c['std'] ?? 0, 3)); ?></span></div>
                  <div class="ml-kv"><span>min / p50 / max</span><span><?php echo html_escape($c['min'] ?? 0); ?> / <?php echo html_escape($c['p50'] ?? 0); ?> / <?php echo html_escape($c['max'] ?? 0); ?></span></div>
                  <div class="ml-canvas sm" style="height:110px"><canvas class="js-hist" data-counts='<?php echo html_escape(json_encode($c['histogram']['counts'] ?? array())); ?>'></canvas></div>
                <?php else: ?>
                  <div class="ml-kv"><span>distinct</span><span><?php echo (int) ($c['distinct'] ?? 0); ?></span></div>
                  <div class="ml-canvas sm" style="height:110px"><canvas class="js-topk"
                    data-labels='<?php echo html_escape(json_encode(array_keys(array_slice($c['top'] ?? array(), 0, 8, TRUE)))); ?>'
                    data-values='<?php echo html_escape(json_encode(array_values(array_slice($c['top'] ?? array(), 0, 8, TRUE)))); ?>'></canvas></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($cols)): ?><p class="ml-muted">No column profile (binary format or profiling deferred).</p><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Data preview</h3>
      <div class="box-tools"><button class="btn btn-xs btn-default" id="moreRows">Load more</button></div></div>
      <div class="box-body table-responsive"><table class="table table-condensed table-bordered" id="previewTable">
        <thead><tr><?php foreach ($sample['columns'] as $c): ?><th><?php echo html_escape($c); ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($sample['rows'] as $row): ?>
          <tr><?php foreach ((array) $row as $cell): ?><td class="ml-mono" style="font-size:11px"><?php echo html_escape($cell); ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <?php if (count($versions) > 1): ?>
    <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Version timeline (drift between consecutive versions)</h3></div>
      <div class="box-body"><div class="ml-canvas"><canvas id="timelineChart"></canvas></div></div>
    </div>
    <?php endif; ?>
    <?php else: ?>
      <div class="callout callout-info">No versions yet. Register one from the Datasets screen.</div>
    <?php endif; ?>
  </section>
</div>
<script src="<?php echo base_url(); ?>assets/bower_components/chart.js/Chart.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-common.js?v=2"></script>
<script src="<?php echo base_url(); ?>assets/js/ml-ui.js?v=2"></script>
<script>
jQuery(function ($) {
  var datasetId = <?php echo (int) $dataset->id; ?>;
  var versionId = <?php echo $version ? (int) $version->id : 0; ?>;
  var previewOffset = <?php echo count($sample['rows']); ?>;

  $('.js-hist').each(function () {
    try {
      var counts = JSON.parse($(this).attr('data-counts') || '[]');
      MlUi.chart(this, 'bar', counts.map(function (_, i) { return i; }),
        [{ label: 'count', data: counts, backgroundColor: '#3c8dbc' }], { legend: { display: false } });
    } catch (e) {}
  });
  $('.js-topk').each(function () {
    try {
      var labels = JSON.parse($(this).attr('data-labels') || '[]');
      var values = JSON.parse($(this).attr('data-values') || '[]');
      MlUi.chart(this, 'horizontalBar', labels,
        [{ label: 'count', data: values, backgroundColor: '#f39c12' }], { legend: { display: false } });
    } catch (e) {}
  });

  $('#moreRows').on('click', function () {
    MlCommon.get(MlCommon.base + 'machine-learning/datasets/preview/' + versionId + '?offset=' + previewOffset + '&limit=50').done(function (r) {
      if (!r || !r.ok) { return; }
      var $b = $('#previewTable tbody');
      r.rows.forEach(function (row) {
        $b.append('<tr>' + row.map(function (v) { return '<td class="ml-mono" style="font-size:11px">' + MlCommon.escape(v) + '</td>'; }).join('') + '</tr>');
      });
      previewOffset += r.rows.length;
      if (!r.rows.length) { $('#moreRows').prop('disabled', true).text('End of data'); }
    });
  });

  $('#cmpBtn').on('click', function () {
    var $box = $('#driftResult').show().find('.box-body').html('Loading…');
    MlCommon.get(MlCommon.base + 'machine-learning/datasets/compare/' + datasetId + '?a=' + $('#cmpA').val() + '&b=' + $('#cmpB').val()).done(function (r) {
      if (!r || !r.ok) { $box.text('No comparable fingerprints.'); return; }
      var o = r.drift.overall;
      var html = '<p>Mean PSI <b>' + MlCommon.fmt(o.drift_psi_mean) + '</b>, max PSI <b>' + MlCommon.fmt(o.drift_psi_max) +
        '</b> — ' + o.features_drifted + '/' + o.features_total + ' features drifted (<span class="ml-status ' + o.status + '">' + o.status + '</span>).</p>' +
        '<div class="ml-grid-charts"></div>';
      $box.html(html);
      Object.keys(r.drift.features).forEach(function (f) {
        var fe = r.drift.features[f];
        var bf = (r.a.fingerprint.columns || {})[f], cf = (r.b.fingerprint.columns || {})[f];
        if (!bf || !cf) { return; }
        var card = $('<div class="box box-default" style="margin:0"><div class="box-header with-border"><h3 class="box-title">' +
          MlCommon.escape(f) + ' <span class="ml-status ' + (fe.status === 'drifted' ? 'warning' : 'ok') + '">PSI ' +
          MlCommon.fmt((fe.metrics || {}).drift_psi) + '</span></h3></div><div class="box-body"><div class="js-ovl"></div></div></div>');
        $box.find('.ml-grid-charts').append(card);
        if (bf.type === 'numeric' && bf.histogram) {
          MlUi.overlayBars(card.find('.js-ovl'), bf.histogram.edges || [], bf.histogram.counts || [],
            (cf.histogram && cf.histogram.counts) || [], ['v' + r.a.version, 'v' + r.b.version]);
        } else {
          var keys = Object.keys(bf.top || {});
          MlUi.overlayBars(card.find('.js-ovl'), keys, keys.map(function (k) { return (bf.top || {})[k] || 0; }),
            keys.map(function (k) { return (cf.top || {})[k] || 0; }), ['v' + r.a.version, 'v' + r.b.version]);
        }
      });
    });
  });

  <?php if (count($versions) > 1): ?>
  (function timeline() {
    var vs = <?php echo json_encode(array_map(function ($v) { return (int) $v->version; }, array_reverse($versions))); ?>;
    var pairs = [], psi = [];
    var chain = vs.slice();
    (function step(i) {
      if (i >= chain.length - 1) {
        MlUi.chart(document.getElementById('timelineChart'), 'line', pairs,
          [{ label: 'max PSI vs previous', data: psi, borderColor: '#dd4b39', fill: false }]);
        return;
      }
      MlCommon.get(MlCommon.base + 'machine-learning/datasets/compare/' + datasetId + '?a=' + chain[i] + '&b=' + chain[i + 1]).done(function (r) {
        pairs.push('v' + chain[i] + '→v' + chain[i + 1]);
        psi.push(r && r.ok ? r.drift.overall.drift_psi_max : 0);
        step(i + 1);
      });
    })(0);
  })();
  <?php endif; ?>
});
</script>
