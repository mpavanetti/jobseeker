<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/visualization.css?v=1">
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/bower_components/select2/dist/css/select2.min.css">
<style>
  .viz-config-page .select2-container { width: 100% !important; }
  .viz-config-page .select2-container--default .select2-selection--multiple { min-height: 42px; border-color: #dbe1eb; border-radius: 9px; }
  .viz-config-page .alert { border: 0; border-radius: 11px; }
  .viz-connection-preview { height: 520px; overflow: hidden; border: 1px solid #e3e8f1; border-radius: 12px; background: #f6f8fb; }
  .viz-connection-preview iframe { width: 100% !important; height: 100% !important; }
</style>
<?php
$providerLabels = array('pbi' => 'Microsoft Power BI', 'tbl' => 'Tableau', 'tblPublic' => 'Tableau Public', 'qlikSense' => 'Qlik Sense', 'qlikView' => 'QlikView', 'superset' => 'Apache Superset', 'metabase' => 'Metabase', 'grafana' => 'Grafana', 'looker' => 'Looker', 'microstrategy' => 'MicroStrategy', 'custom' => 'Other HTTPS embed');
?>
<div class="content-wrapper viz-page viz-config-page">
  <section class="content">
    <div class="viz-shell">
      <div class="viz-viewer-bar">
        <div class="viz-viewer-title"><a class="viz-btn viz-btn-light" href="<?php echo base_url(); ?>Visualization"><i class="fa fa-arrow-left"></i></a><div><h1>Connected analytics</h1><p>Curate external reports and control who can discover them.</p></div></div>
        <a class="viz-btn viz-btn-blue" href="<?php echo base_url(); ?>Visualization/studio"><i class="fa fa-magic"></i> Native Insight Studio</a>
      </div>

      <?php $error = $this->session->flashdata('error'); if($error) { ?><div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button><?php echo html_escape($error); ?></div><?php } ?>
      <?php $success = $this->session->flashdata('success'); if($success) { ?><div class="alert alert-success"><button type="button" class="close" data-dismiss="alert">&times;</button><?php echo html_escape($success); ?></div><?php } ?>
      <?php if(isset($this->form_validation)) { echo validation_errors('<div class="alert alert-danger">', '</div>'); } ?>

      <div class="viz-config-grid">
        <div class="viz-panel">
          <div class="viz-panel-head"><div><h2><i class="fa fa-link"></i> Add a trusted report</h2><p>Paste the provider's share URL or its iframe snippet.</p></div><span class="viz-pill"><i class="fa fa-shield"></i> Sanitized</span></div>
          <div class="viz-panel-body">
            <form class="viz-form" action="<?php echo base_url(); ?>Visualization/add" method="POST">
              <div class="row">
                <div class="col-md-7 form-group"><label for="name">Report name</label><input id="name" name="name" class="form-control" maxlength="120" placeholder="e.g. Weekly delivery health" required></div>
                <div class="col-md-5 form-group"><label for="type">Provider</label><select id="type" name="type" class="form-control"><?php foreach($providerLabels as $value => $label) { ?><option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option><?php } ?></select></div>
              </div>
              <div class="form-group"><label for="code">Secure share URL or iframe</label><textarea id="code" name="code" class="form-control" maxlength="5000" placeholder="https://analytics.example.com/embed/dashboard/..." required></textarea><span class="viz-help">Use a presentation/embed URL—not a database connection. Jobseeker stores no provider password or token and strips unsupported iframe attributes.</span></div>
              <div class="row">
                <div class="col-md-6 form-group"><label for="users">People</label><select id="users" class="form-control select2" name="users[]" multiple><option value="*">Everyone</option><?php foreach($users as $user) { ?><option value="<?php echo html_escape($user->name); ?>"><?php echo html_escape($user->name); ?></option><?php } ?></select></div>
                <div class="col-md-6 form-group"><label for="groups">Groups</label><select id="groups" class="form-control select2" name="groups[]" multiple><?php foreach($groups as $group) { ?><option value="<?php echo html_escape($group->name); ?>"><?php echo html_escape($group->name); ?></option><?php } ?></select></div>
              </div>
              <div class="viz-form-actions"><button class="viz-btn viz-btn-blue" type="submit"><i class="fa fa-plus"></i> Add connection</button><span class="viz-form-note">At least one person or group is required.</span></div>
            </form>
          </div>
        </div>
        <aside class="viz-panel">
          <div class="viz-panel-head"><div><h3>Connection boundary</h3><p>What Jobseeker enforces.</p></div></div>
          <div class="viz-panel-body">
            <div class="viz-trust-list">
              <div class="viz-trust-item"><i class="fa fa-code"></i><div><strong>Markup is normalized</strong><p>Only one iframe and its HTTP(S) source survive. Scripts and event handlers are discarded.</p></div></div>
              <div class="viz-trust-item"><i class="fa fa-shield"></i><div><strong>Capabilities are constrained</strong><p>Every report receives a fixed sandbox and no-referrer policy at render time.</p></div></div>
              <div class="viz-trust-item"><i class="fa fa-user-secret"></i><div><strong>Identity stays upstream</strong><p>Use your provider's guest, SSO or signed-embed flow. Never paste a password into the URL.</p></div></div>
              <div class="viz-trust-item"><i class="fa fa-server"></i><div><strong>No server-side fetching</strong><p>The browser talks directly to the provider, avoiding an SSRF-style report proxy.</p></div></div>
            </div>
            <div class="viz-provider-row"><span>Power BI</span><span>Tableau</span><span>Superset</span><span>Metabase</span><span>Grafana</span><span>Looker</span></div>
          </div>
        </aside>
      </div>

      <section class="viz-section viz-panel">
        <div class="viz-panel-head"><div><h2>Report catalog</h2><p><?php echo number_format((int) $reports); ?> reports across <?php echo number_format((int) $types); ?> provider types.</p></div></div>
        <div class="table-responsive">
          <?php if(!empty($list)) { ?>
          <table id="tableReports" class="table viz-table">
            <thead><tr><th>Report</th><th>Provider</th><th>Audience</th><th>Owner</th><th>Added</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
              <?php foreach($list as $record) { $provider = isset($providerLabels[$record->type]) ? $providerLabels[$record->type] : $record->type; ?>
              <tr data-report-row="<?php echo (int) $record->id; ?>">
                <td><span class="viz-table-title"><?php echo html_escape($record->name); ?></span><br><small class="text-muted">#<?php echo (int) $record->id; ?></small></td>
                <td><span class="viz-pill viz-pill-neutral"><?php echo html_escape($provider); ?></span></td>
                <td><strong><?php echo html_escape($record->users === '*' ? 'Everyone' : $record->users); ?></strong><br><small class="text-muted"><?php echo html_escape($record->groups); ?></small></td>
                <td><?php echo html_escape($record->owner); ?></td>
                <td><?php echo js_time_date($record->creation_date, array('format' => 'M j, Y')); ?></td>
                <td class="text-right"><button type="button" class="viz-btn viz-btn-light viz-preview-report" data-reportid="<?php echo (int) $record->id; ?>"><i class="fa fa-eye"></i></button> <button type="button" class="viz-btn viz-btn-danger viz-delete-report" data-reportid="<?php echo (int) $record->id; ?>"><i class="fa fa-trash"></i></button></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
          <?php } else { ?><div class="viz-empty"><i class="fa fa-plug"></i><strong>No connected reports yet</strong><p>Add a trusted embed above, or use the native studio without any external dependency.</p></div><?php } ?>
        </div>
      </section>
    </div>
  </section>
</div>

<div class="modal fade" id="vizPreviewModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content" style="border-radius:16px;overflow:hidden"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Connection preview</h4></div><div class="modal-body"><div id="vizPreviewMeta" style="margin-bottom:12px"></div><div id="vizPreviewFrame" class="viz-connection-preview"></div></div></div></div>
</div>
<script src="<?php echo base_url(); ?>assets/bower_components/select2/dist/js/select2.min.js"></script>
<script>
$(function() {
  $('.select2').select2({ placeholder: 'Select people or groups', allowClear: true });

  $(document).on('click', '.viz-preview-report', function() {
    var id = $(this).data('reportid');
    $('#vizPreviewMeta').html('<i class="fa fa-spinner fa-spin"></i> Loading safe preview…');
    $('#vizPreviewFrame').empty();
    $.getJSON(baseURL + 'Visualization/fetch/' + encodeURIComponent(id)).done(function(payload) {
      var report = payload.data && payload.data[0];
      if (!report) { toastr.error('Report not found.'); return; }
      $('#vizPreviewMeta').html($('<strong>').text(report.name)).append(' · ').append($('<span class="text-muted">').text(report.type + ' · ' + report.owner));
      $('#vizPreviewFrame').html(report.code || '<div class="viz-empty">The saved connection is invalid.</div>');
      $('#vizPreviewModal').modal('show');
    }).fail(function() { toastr.error('Could not load the report preview.'); });
  });

  $(document).on('click', '.viz-delete-report', function() {
    var id = $(this).data('reportid');
    alertify.confirm('Remove connected report', 'Remove this report from the Jobseeker catalog? The upstream report will not be deleted.', function() {
      $.post(baseURL + 'Visualization/delete', { userId: id }).done(function(payload) {
        if (typeof payload === 'string') { try { payload = JSON.parse(payload); } catch (ignore) {} }
        if (payload && payload.status === true) { $('[data-report-row="' + id + '"]').remove(); toastr.success('Connected report removed.'); }
        else { toastr.error('Report could not be removed.'); }
      }).fail(function() { toastr.error('Report could not be removed.'); });
    }, function() {});
  });

  $('#vizPreviewModal').on('hidden.bs.modal', function() { $('#vizPreviewFrame').empty(); });
});
</script>
