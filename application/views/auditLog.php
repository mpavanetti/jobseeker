<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-shield"></i> Audit Log <small>Administrator-only activity trail</small></h1>
  </section>

  <section class="content">
    <div class="row">
      <div class="col-md-4 col-sm-6 col-xs-12">
        <div class="info-box">
          <span class="info-box-icon bg-aqua"><i class="fa fa-list"></i></span>
          <div class="info-box-content"><span class="info-box-text">Matching operations</span><span class="info-box-number"><?php echo number_format($summary['total']); ?></span></div>
        </div>
      </div>
      <div class="col-md-4 col-sm-6 col-xs-12">
        <div class="info-box">
          <span class="info-box-icon bg-green"><i class="fa fa-check"></i></span>
          <div class="info-box-content"><span class="info-box-text">Successful</span><span class="info-box-number"><?php echo number_format($summary['successful']); ?></span></div>
        </div>
      </div>
      <div class="col-md-4 col-sm-6 col-xs-12">
        <div class="info-box">
          <span class="info-box-icon bg-red"><i class="fa fa-warning"></i></span>
          <div class="info-box-content"><span class="info-box-text">Failed</span><span class="info-box-number"><?php echo number_format($summary['failed']); ?></span></div>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">Filter operations</h3>
      </div>
      <form method="get" action="<?php echo base_url('audit-log'); ?>">
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-4">
              <label for="auditSearch">Search</label>
              <input id="auditSearch" class="form-control" type="search" name="search" maxlength="200" value="<?php echo html_escape($filters['search']); ?>" placeholder="Actor, action, path, IP, or request data">
            </div>
            <div class="form-group col-md-2">
              <label for="auditActor">Actor</label>
              <select id="auditActor" class="form-control" name="user_id">
                <option value="">All actors</option>
                <?php foreach ($actors as $actor) { ?>
                  <option value="<?php echo (int) $actor->actor_user_id; ?>"<?php echo (int) $filters['user_id'] === (int) $actor->actor_user_id ? ' selected' : ''; ?>><?php echo html_escape($actor->actor_name); ?> (#<?php echo (int) $actor->actor_user_id; ?>)</option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label for="auditOperation">Operation</label>
              <select id="auditOperation" class="form-control" name="operation">
                <option value="">All operations</option>
                <?php foreach (array('read' => 'Read', 'write' => 'Write', 'authentication' => 'Authentication') as $value => $label) { ?>
                  <option value="<?php echo $value; ?>"<?php echo $filters['operation'] === $value ? ' selected' : ''; ?>><?php echo $label; ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label for="auditMethod">Method</label>
              <select id="auditMethod" class="form-control" name="method">
                <option value="">All methods</option>
                <?php foreach (array('GET', 'POST', 'PUT', 'PATCH', 'DELETE') as $method) { ?>
                  <option value="<?php echo $method; ?>"<?php echo $filters['method'] === $method ? ' selected' : ''; ?>><?php echo $method; ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group col-md-2">
              <label for="auditOutcome">Outcome</label>
              <select id="auditOutcome" class="form-control" name="outcome">
                <option value="">All outcomes</option>
                <option value="success"<?php echo $filters['outcome'] === 'success' ? ' selected' : ''; ?>>Success</option>
                <option value="failure"<?php echo $filters['outcome'] === 'failure' ? ' selected' : ''; ?>>Failure</option>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-2">
              <label for="auditFrom">From</label>
              <input id="auditFrom" class="form-control" type="date" name="from" value="<?php echo html_escape($filters['from']); ?>">
            </div>
            <div class="form-group col-md-2">
              <label for="auditTo">To</label>
              <input id="auditTo" class="form-control" type="date" name="to" value="<?php echo html_escape($filters['to']); ?>">
            </div>
          </div>
        </div>
        <div class="box-footer">
          <button class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> Apply filters</button>
          <a class="btn btn-default" href="<?php echo base_url('audit-log'); ?>">Clear</a>
        </div>
      </form>
    </div>

    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title">Operations <?php if ($total > 0) { ?><small><?php echo number_format($offset + 1); ?>–<?php echo number_format(min($offset + count($records), $total)); ?> of <?php echo number_format($total); ?></small><?php } ?></h3>
      </div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover audit-log-table">
          <thead><tr><th>Time</th><th>Actor</th><th>Operation</th><th>Action</th><th>Request</th><th>Outcome</th><th>Details</th></tr></thead>
          <tbody>
          <?php if (empty($records)) { ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:30px;">No audit operations match these filters.</td></tr>
          <?php } ?>
          <?php foreach ($records as $record) {
            $details = '';
            if ($record->request_data !== NULL && $record->request_data !== '') {
              $decoded = json_decode($record->request_data, TRUE);
              $details = is_array($decoded) ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $record->request_data;
            }
          ?>
            <tr>
              <td class="text-nowrap"><?php echo js_time($record->created_at); ?><br><small class="text-muted"><?php echo (int) $record->duration_ms; ?> ms</small></td>
              <td><strong><?php echo html_escape($record->actor_name); ?></strong><br><small class="text-muted"><?php echo html_escape($record->actor_role); ?> · #<?php echo (int) $record->actor_user_id; ?></small></td>
              <td><span class="label <?php echo $record->operation === 'write' ? 'label-warning' : ($record->operation === 'authentication' ? 'label-primary' : 'label-default'); ?>"><?php echo html_escape(strtoupper($record->operation)); ?></span></td>
              <td><code><?php echo html_escape($record->action); ?></code></td>
              <td><span class="label label-default"><?php echo html_escape($record->http_method); ?></span> <?php echo html_escape($record->request_uri === '' ? '/' : $record->request_uri); ?><br><small class="text-muted"><?php echo html_escape($record->ip_address); ?></small></td>
              <td><span class="label <?php echo $record->outcome === 'success' ? 'label-success' : 'label-danger'; ?>"><?php echo html_escape(strtoupper($record->outcome)); ?></span><br><small>HTTP <?php echo (int) $record->status_code; ?></small></td>
              <td>
                <details>
                  <summary>Inspect</summary>
                  <?php if ($details !== '') { ?><pre><?php echo html_escape($details); ?></pre><?php } ?>
                  <p><strong>User agent</strong><br><?php echo html_escape($record->user_agent); ?></p>
                </details>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
      <?php if ($total > $pageSize) { ?><div class="box-footer clearfix"><?php echo $this->pagination->create_links(); ?></div><?php } ?>
    </div>
  </section>
</div>

<style>
  .audit-log-table td { vertical-align: top !important; }
  .audit-log-table code { white-space: nowrap; }
  .audit-log-table details { min-width: 85px; max-width: 480px; }
  .audit-log-table summary { color: #3c8dbc; cursor: pointer; }
  .audit-log-table pre { margin-top: 8px; max-height: 260px; overflow: auto; white-space: pre-wrap; word-break: break-word; }
  .audit-log-table p { margin: 8px 0 0; word-break: break-word; }
</style>
