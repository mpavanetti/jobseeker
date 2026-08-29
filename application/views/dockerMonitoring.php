<style>
  .docker-monitor-page .content { padding: 18px; }
  .docker-monitor-toolbar { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; margin-bottom: 14px; }
  .docker-monitor-toolbar-controls { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; }
  .docker-monitor-refresh { color: #777; display: block; font-size: 12px; margin-top: 4px; }
  .docker-monitor-page .info-box { min-height: 94px; }
  .docker-monitor-page .info-box-icon { height: 94px; line-height: 94px; }
  .docker-monitor-page .info-box-content { margin-left: 94px; padding-top: 12px; }
  .docker-monitor-page .progress-description { color: #777; white-space: normal; }
  .docker-monitor-summary { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
  .docker-monitor-chip { background: #f4f4f4; border: 1px solid #ddd; border-radius: 3px; color: #555; font-size: 12px; padding: 5px 9px; }
  .docker-monitor-chip strong { color: #333; }
  .docker-monitor-table td { vertical-align: middle !important; }
  .docker-monitor-name { font-weight: 700; }
  .docker-monitor-subtext { color: #777; display: block; font-size: 11px; margin-top: 2px; }
  .docker-monitor-state { border-radius: 3px; color: #fff; display: inline-block; font-size: 11px; font-weight: 700; min-width: 66px; padding: 3px 7px; text-align: center; text-transform: uppercase; }
  .docker-monitor-state-running { background: #00a65a; }
  .docker-monitor-state-exited { background: #777; }
  .docker-monitor-state-dead { background: #dd4b39; }
  .docker-monitor-state-paused, .docker-monitor-state-restarting { background: #f39c12; }
  .docker-monitor-state-created, .docker-monitor-state-unknown { background: #777; }
  .docker-monitor-metric { min-width: 90px; }
  .docker-monitor-metric .progress { height: 5px; margin: 4px 0 0; }
  .docker-monitor-empty { color: #777; padding: 30px !important; text-align: center; }
  .docker-monitor-ports { font-family: Menlo, Monaco, Consolas, "Courier New", monospace; font-size: 11px; min-width: 150px; white-space: nowrap; }
  .docker-monitor-identity { min-width: 155px; }
  .docker-monitor-health { display: block; font-size: 11px; font-weight: 700; margin-top: 4px; text-transform: uppercase; }
  .docker-monitor-health-healthy { color: #00a65a; }
  .docker-monitor-health-unhealthy { color: #dd4b39; }
  .docker-monitor-health-starting { color: #f39c12; }
  .docker-engine-warning { margin-bottom: 10px; }
  .docker-monitor-operational { margin-bottom: 14px; }
  .docker-monitor-operational p { margin-bottom: 0; }
  .docker-monitor-filterbar { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; margin-bottom: 12px; }
  .docker-monitor-filterbar .badge { margin-left: 3px; }
  .docker-monitor-engine-filter { width: 190px; }
  .docker-monitor-unavailable { color: #999; font-size: 12px; }
  .docker-monitor-storage { background: #fafafa; border: 1px solid #e5e5e5; border-radius: 3px; color: #666; font-size: 12px; margin-bottom: 12px; padding: 8px 10px; }
  .docker-monitor-storage .docker-monitor-chip { background: #fff; display: inline-block; margin: 3px 5px 3px 0; }
  .docker-monitor-history { color: #8a6d3b; display: block; font-size: 11px; font-weight: 700; margin-top: 4px; text-transform: uppercase; }
  .docker-monitor-history-row { background: #fcfcfc; }
  .docker-monitor-tabs { margin-bottom: 14px; }
  .docker-monitor-job-table td { vertical-align: middle !important; }
  .docker-monitor-job-name { min-width: 190px; }
  .docker-monitor-job-trend { display: block; height: 24px; margin-top: 4px; width: 100%; }
  .docker-monitor-job-policy { color: #777; font-size: 11px; margin-top: 3px; }
  .docker-monitor-job-empty { color: #777; padding: 38px 20px !important; text-align: center; }
  @media (max-width: 767px) {
    .docker-monitor-toolbar { align-items: stretch; flex-direction: column; }
    .docker-monitor-toolbar .form-control { width: 100% !important; }
  }
</style>

<div class="content-wrapper docker-monitor-page">
  <section class="content-header">
    <h1>Docker Monitoring <small>Container runtime and host capacity</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?php echo base_url(); ?>dashboard"><i class="fa fa-home"></i> Home</a></li>
      <li>Infrastructure Monitoring</li>
      <li class="active">Docker Monitoring</li>
    </ol>
  </section>

  <section class="content">
    <div class="docker-monitor-toolbar">
      <div>
        <div class="docker-monitor-toolbar-controls">
          <button type="button" class="btn btn-primary btn-sm" id="dockerMonitorRefresh"><i class="fa fa-refresh"></i> Refresh</button>
          <button type="button" class="btn btn-warning btn-sm" id="dockerReclaimCache" title="Reclaim unused Docker build cache across both engines"><i class="fa fa-eraser"></i> Reclaim Build Cache</button>
          <label class="checkbox-inline"><input type="checkbox" id="dockerMonitorAutoRefresh" checked> Auto refresh</label>
          <select class="form-control input-sm" id="dockerMonitorInterval" aria-label="Refresh interval" style="width:82px;">
            <option value="5000">5 sec</option>
            <option value="15000">15 sec</option>
            <option value="30000">30 sec</option>
          </select>
        </div>
        <span class="docker-monitor-refresh" id="dockerMonitorUpdated">Waiting for the first snapshot…</span>
        <span class="docker-monitor-refresh" id="dockerCacheActionStatus" aria-live="polite"></span>
      </div>
      <div class="input-group input-group-sm" style="width:280px;">
        <span class="input-group-addon"><i class="fa fa-search"></i></span>
        <input type="search" class="form-control" id="dockerMonitorSearch" placeholder="Filter containers, images, engines">
      </div>
    </div>

    <div id="dockerMonitorWarnings"></div>
    <div id="dockerOperationalSummary" class="callout callout-info docker-monitor-operational">
      <h4><i class="fa fa-circle-o-notch fa-spin"></i> Evaluating workload health</h4>
      <p>Waiting for container state and resource metrics.</p>
    </div>

    <div class="row">
      <div class="col-lg-3 col-sm-6">
        <div class="info-box">
          <span class="info-box-icon bg-aqua"><i class="fa fa-microchip"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Host CPU</span>
            <span class="info-box-number" id="dockerHostCpu">—</span>
            <div class="progress"><div class="progress-bar" id="dockerHostCpuBar" style="width:0%"></div></div>
            <span class="progress-description" id="dockerHostLoad">Load unavailable</span>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6">
        <div class="info-box">
          <span class="info-box-icon bg-green"><i class="fa fa-server"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Host Memory</span>
            <span class="info-box-number" id="dockerHostMemory">—</span>
            <div class="progress"><div class="progress-bar" id="dockerHostMemoryBar" style="width:0%"></div></div>
            <span class="progress-description" id="dockerHostMemoryDetail">Memory unavailable</span>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6">
        <div class="info-box">
          <span class="info-box-icon bg-yellow"><i class="fa fa-hdd-o"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Repository Disk</span>
            <span class="info-box-number" id="dockerHostDisk">—</span>
            <div class="progress"><div class="progress-bar" id="dockerHostDiskBar" style="width:0%"></div></div>
            <span class="progress-description" id="dockerHostDiskDetail">Disk unavailable</span>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6">
        <div class="info-box">
          <span class="info-box-icon bg-red"><i class="fa fa-cubes"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Containers</span>
            <span class="info-box-number" id="dockerContainerCount">—</span>
            <div class="progress"><div class="progress-bar" id="dockerContainerBar" style="width:0%"></div></div>
            <span class="progress-description" id="dockerContainerDetail">Runtime unavailable</span>
          </div>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-cubes"></i> Container Workloads</h3>
        <div class="box-tools pull-right"><span class="label label-default" id="dockerMonitorPollState">5s refresh</span></div>
      </div>
      <div class="box-body">
        <div class="docker-monitor-summary" id="dockerEngineSummary"></div>
        <div class="docker-monitor-storage" id="dockerStorageSummary"><i class="fa fa-circle-o-notch fa-spin"></i> Measuring Docker image, volume, and build-cache storage…</div>
        <ul class="nav nav-tabs docker-monitor-tabs" role="tablist">
          <li role="presentation" class="active"><a href="#dockerWorkloadsTab" aria-controls="dockerWorkloadsTab" role="tab" data-toggle="tab"><i class="fa fa-cubes"></i> All Containers</a></li>
          <li role="presentation"><a href="#dockerRunningJobsTab" aria-controls="dockerRunningJobsTab" role="tab" data-toggle="tab"><i class="fa fa-bolt"></i> Running Jobs <span class="badge" id="dockerRunningJobCount">0</span></a></li>
        </ul>
        <div class="tab-content">
          <div role="tabpanel" class="tab-pane active" id="dockerWorkloadsTab">
            <div class="docker-monitor-filterbar">
              <div class="btn-group btn-group-sm" id="dockerMonitorStateFilters" role="group" aria-label="Container state filters">
                <button type="button" class="btn btn-default" data-state="all">All <span class="badge" id="dockerFilterAll">0</span></button>
                <button type="button" class="btn btn-default active" data-state="running">Running <span class="badge" id="dockerFilterRunning">0</span></button>
                <button type="button" class="btn btn-default" data-state="attention">Needs Attention <span class="badge" id="dockerFilterAttention">0</span></button>
                <button type="button" class="btn btn-default" data-state="history">Past Exits <span class="badge" id="dockerFilterHistory">0</span></button>
                <button type="button" class="btn btn-default" data-state="stopped">Stopped <span class="badge" id="dockerFilterStopped">0</span></button>
              </div>
              <select class="form-control input-sm docker-monitor-engine-filter" id="dockerMonitorEngineFilter" aria-label="Docker engine filter">
                <option value="all">All Docker engines</option>
              </select>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-hover docker-monitor-table">
                <thead>
                  <tr>
                    <th>Container</th><th>Identity / Network</th><th>Ports</th><th>Engine</th><th>Status</th><th>CPU</th><th>Memory</th><th>Network I/O</th><th>Block I/O</th><th>PIDs</th>
                  </tr>
                </thead>
                <tbody id="dockerContainerRows"><tr><td colspan="10" class="docker-monitor-empty"><i class="fa fa-spinner fa-spin"></i> Loading Docker metrics…</td></tr></tbody>
              </table>
            </div>
          </div>
          <div role="tabpanel" class="tab-pane" id="dockerRunningJobsTab">
            <p class="text-muted"><i class="fa fa-info-circle"></i> Live resource use for labeled Python, shell, and ETL job containers on the isolated Job runtime. Peaks and trends cover this browser session.</p>
            <div class="table-responsive">
              <table class="table table-bordered table-hover docker-monitor-job-table">
                <thead><tr><th>Job / Build</th><th>Container</th><th>Runtime</th><th>Duration</th><th>CPU</th><th>Memory</th><th>Network I/O</th><th>Block I/O</th><th>PIDs</th></tr></thead>
                <tbody id="dockerRunningJobRows"><tr><td colspan="9" class="docker-monitor-job-empty">No containerized jobs are running.</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="box-footer text-muted"><i class="fa fa-shield"></i> Metrics use read-only Docker API routes. Cache reclamation is limited to unused build cache; containers, images, volumes, and networks are not removed. Network and block I/O are cumulative since container start; CPU and memory are current samples.</div>
    </div>
  </section>
</div>

<script>
(function($) {
  'use strict';

  var snapshotUrl = <?php echo json_encode(base_url().'docker-monitoring/snapshot'); ?>;
  var storageUrl = <?php echo json_encode(base_url().'docker-monitoring/storage'); ?>;
  var reclaimCacheUrl = <?php echo json_encode(base_url().'docker-monitoring/reclaim-cache'); ?>;
  var jobExecutionUrl = <?php echo json_encode(base_url().'jobExecution'); ?>;
  var previousCpu = null;
  var latestHostCpu = null;
  var latestSnapshot = null;
  var latestStorage = null;
  var request = null;
  var storageRequest = null;
  var cachePruneRequest = null;
  var lastStorageAttemptAt = 0;
  var timer = null;
  var stateFilter = 'running';
  var engineFilter = 'all';
  var jobMetricHistory = {};
  var containerCpuSamples = {};

  function clamp(value) {
    return Math.max(0, Math.min(100, Number(value) || 0));
  }

  function bytes(value) {
    value = Number(value) || 0;
    if (value <= 0) { return '0 B'; }
    var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    var index = Math.min(units.length - 1, Math.floor(Math.log(value) / Math.log(1024)));
    return (value / Math.pow(1024, index)).toFixed(index > 1 ? 1 : 0) + ' ' + units[index];
  }

  function duration(seconds) {
    seconds = Math.max(0, Number(seconds) || 0);
    var days = Math.floor(seconds / 86400);
    var hours = Math.floor((seconds % 86400) / 3600);
    return days ? days + 'd ' + hours + 'h' : hours + 'h';
  }

  function age(timestamp) {
    var moment = typeof timestamp === 'number' ? timestamp * 1000 : Date.parse(timestamp || '');
    if (! moment || isNaN(moment)) { return ''; }
    var seconds = Math.max(0, Math.floor((Date.now() - moment) / 1000));
    if (seconds < 60) { return seconds + 's ago'; }
    if (seconds < 3600) { return Math.floor(seconds / 60) + 'm ago'; }
    if (seconds < 86400) { return Math.floor(seconds / 3600) + 'h ago'; }
    return Math.floor(seconds / 86400) + 'd ago';
  }

  function lifecycleMoment(container) {
    var value = container.finishedAt || container.created;
    var moment = typeof value === 'number' ? value * 1000 : Date.parse(value || '');
    return moment && ! isNaN(moment) ? moment : 0;
  }

  function lifecycleIsRecent(container) {
    var moment = lifecycleMoment(container);
    return ! moment || Date.now() - moment <= 24 * 60 * 60 * 1000;
  }

  function hasPastExit(container) {
    var state = String(container.state || '').toLowerCase();
    return state !== 'running' && Number(container.exitCode) !== 0 && containerSeverity(container) === 'normal';
  }

  function containerSeverity(container) {
    var state = String(container.state || '').toLowerCase();
    var health = String(container.health || '').toLowerCase();
    if (state === 'dead' || state === 'restarting' || (state === 'running' && (container.oomKilled || container.stateError || health === 'unhealthy'))) {
      return 'critical';
    }
    if (state !== 'running' && lifecycleIsRecent(container) && (container.oomKilled || container.stateError)) {
      return 'critical';
    }
    if (state === 'paused' || (state === 'running' && (health === 'starting' || Number(container.cpuPercent) >= 90 || Number(container.memoryPercent) >= 85 || (Number(container.restartCount) > 0 && lifecycleIsRecent(container))))) {
      return 'warning';
    }
    // Exit 143 is SIGTERM, Docker's normal result for an intentional stop.
    // Other non-zero exits age out of active health after 24 hours but remain
    // visible in the Past Exits filter for forensic context.
    if (state !== 'running' && lifecycleIsRecent(container) && Number(container.exitCode) !== 0 && Number(container.exitCode) !== 143) {
      return 'warning';
    }
    return 'normal';
  }

  function containerIssue(container) {
    var reasons = [];
    var state = String(container.state || '').toLowerCase();
    var health = String(container.health || '').toLowerCase();
    if (container.oomKilled) { reasons.push('out of memory'); }
    if (container.stateError) { reasons.push(container.stateError); }
    if (health === 'unhealthy') { reasons.push('unhealthy'); }
    if (state === 'dead' || state === 'restarting' || state === 'paused') { reasons.push(state); }
    if (Number(container.cpuPercent) >= 90) { reasons.push((Number(container.cpuPercent) || 0).toFixed(1) + '% CPU'); }
    if (Number(container.memoryPercent) >= 85) { reasons.push((Number(container.memoryPercent) || 0).toFixed(1) + '% memory'); }
    if (Number(container.restartCount) > 0 && lifecycleIsRecent(container)) { reasons.push(container.restartCount + ' restart' + (Number(container.restartCount) === 1 ? '' : 's')); }
    if (state !== 'running' && lifecycleIsRecent(container) && Number(container.exitCode) !== 0 && Number(container.exitCode) !== 143) { reasons.push('exit ' + container.exitCode); }
    return reasons.join(', ');
  }

  function hostIssues(host, cpuPercent) {
    host = host || {};
    var issues = [];
    var diskPercent = host.diskTotalBytes ? (Number(host.diskUsedBytes) / Number(host.diskTotalBytes)) * 100 : 0;
    var memoryPercent = host.memoryTotalBytes ? (Number(host.memoryUsedBytes) / Number(host.memoryTotalBytes)) * 100 : 0;
    if (diskPercent >= 95) {
      issues.push({ severity: 'critical', name: 'Repository disk', reason: diskPercent.toFixed(1) + '% used; ' + bytes(host.diskFreeBytes) + ' free' });
    } else if (diskPercent >= 85) {
      issues.push({ severity: 'warning', name: 'Repository disk', reason: diskPercent.toFixed(1) + '% used; ' + bytes(host.diskFreeBytes) + ' free' });
    }
    if (memoryPercent >= 95) {
      issues.push({ severity: 'critical', name: 'Host memory', reason: memoryPercent.toFixed(1) + '% used' });
    } else if (memoryPercent >= 85) {
      issues.push({ severity: 'warning', name: 'Host memory', reason: memoryPercent.toFixed(1) + '% used' });
    }
    if (cpuPercent !== null && Number(cpuPercent) >= 95) {
      issues.push({ severity: 'critical', name: 'Host CPU', reason: Number(cpuPercent).toFixed(1) + '% used' });
    } else if (cpuPercent !== null && Number(cpuPercent) >= 85) {
      issues.push({ severity: 'warning', name: 'Host CPU', reason: Number(cpuPercent).toFixed(1) + '% used' });
    }
    return issues;
  }

  function progress(width, color) {
    return $('<div class="progress progress-xs">').append($('<div class="progress-bar">').addClass(color || 'progress-bar-aqua').css('width', clamp(width) + '%'));
  }

  function metricCell(label, percent, detail, color) {
    return $('<td class="docker-monitor-metric">')
      .append($('<strong>').text(label))
      .append(progress(percent, color))
      .append($('<span class="docker-monitor-subtext">').text(detail || ''));
  }

  function unavailableMetricCell(detail) {
    return $('<td class="docker-monitor-metric">').append($('<span class="docker-monitor-unavailable">').text('—')).append($('<span class="docker-monitor-subtext">').text(detail || 'metrics unavailable'));
  }

  function recordJobMetric(container) {
    var key = container.id || container.name;
    var history = jobMetricHistory[key] || { cpu: [], memory: [], peakCpu: 0, peakMemory: 0, peakPids: 0 };
    var cpu = Number(container.cpuPercent) || 0;
    var memory = Number(container.memoryPercent) || 0;
    history.cpu.push(cpu);
    history.memory.push(memory);
    if (history.cpu.length > 30) { history.cpu.shift(); }
    if (history.memory.length > 30) { history.memory.shift(); }
    history.peakCpu = Math.max(history.peakCpu, cpu);
    history.peakMemory = Math.max(history.peakMemory, Number(container.memoryBytes) || 0);
    history.peakPids = Math.max(history.peakPids, Number(container.pids) || 0);
    jobMetricHistory[key] = history;
    return history;
  }

  function metricTrend(values, color, minimumScale) {
    var svg = $(document.createElementNS('http://www.w3.org/2000/svg', 'svg')).attr({ viewBox: '0 0 100 24', preserveAspectRatio: 'none', 'aria-hidden': 'true' }).addClass('docker-monitor-job-trend');
    var samples = values && values.length ? values : [0];
    var scale = Math.max(Number(minimumScale) || 100, Math.max.apply(Math, samples));
    var points = $.map(samples, function(value, index) {
      var x = samples.length === 1 ? 100 : (index / (samples.length - 1)) * 100;
      var y = 22 - Math.min(scale, Math.max(0, Number(value) || 0)) / scale * 20;
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ');
    var line = $(document.createElementNS('http://www.w3.org/2000/svg', 'polyline')).attr({ points: points, fill: 'none', stroke: color, 'stroke-width': 2, 'vector-effect': 'non-scaling-stroke' });
    svg.append(line);
    return svg;
  }

  function runtimeName(value) {
    value = String(value || 'container').toLowerCase();
    if (value === 'python') { return 'Python'; }
    if (value === 'talend') { return 'Talend / ETL'; }
    if (value === 'linux-shell') { return 'Linux Shell'; }
    return 'Container';
  }

  function renderRunningJobs(snapshot) {
    var rows = $('#dockerRunningJobRows').empty();
    var jobs = $.grep(allContainers(snapshot), function(container) {
      return container.source === 'Job runtime' && container.state === 'running' && container.isJobContainer;
    }).sort(function(left, right) {
      return (Number(right.cpuPercent) || 0) - (Number(left.cpuPercent) || 0);
    });
    $('#dockerRunningJobCount').text(jobs.length);

    if (! jobs.length) {
      rows.append($('<tr>').append($('<td colspan="9" class="docker-monitor-job-empty">').append($('<i class="fa fa-check-circle text-green">')).append(document.createTextNode(' No labeled containerized jobs are running. Existing Docker jobs receive live metrics after they are saved with the current job generator.'))));
      return;
    }

    $.each(jobs, function(index, container) {
      var history = recordJobMetric(container);
      var row = $('<tr>');
      var jobCell = $('<td class="docker-monitor-job-name">');
      if (container.jobName && container.buildNumber) {
        jobCell.append($('<a>').attr('href', jobExecutionUrl + '?job=' + encodeURIComponent(container.jobName) + '&build=' + encodeURIComponent(container.buildNumber) + '&environment=' + encodeURIComponent(container.jobEnvironment || '')).append($('<strong>').text(container.jobName + ' #' + container.buildNumber)));
      } else {
        jobCell.append($('<strong>').text(container.jobName || 'Unidentified job container'));
      }
      jobCell.append($('<span class="docker-monitor-subtext">').text(container.jobEnvironment ? 'Environment ' + container.jobEnvironment : 'Environment not reported'));
      row.append(jobCell);
      row.append($('<td>').append($('<strong>').text(container.name)).append($('<span class="docker-monitor-subtext">').text(container.image + ' · ' + container.id)));
      var runtimeCell = $('<td>').append($('<span class="label label-info">').text(runtimeName(container.jobRuntime)));
      var policy = [];
      policy.push(Number(container.cpuLimitCores) > 0 ? container.cpuLimitCores + ' CPU limit' : 'no CPU limit');
      policy.push(Number(container.configuredMemoryLimitBytes) > 0 ? bytes(container.configuredMemoryLimitBytes) + ' memory limit' : 'no memory limit');
      runtimeCell.append($('<div class="docker-monitor-job-policy">').text(policy.join(' · ')));
      row.append(runtimeCell);
      row.append($('<td>').append($('<strong>').text(age(container.startedAt).replace(' ago', '') || '—')).append($('<span class="docker-monitor-subtext">').text('started ' + (age(container.startedAt) || 'unknown'))));

      if (container.metricsAvailable) {
        var cpuLabel = container.cpuSampleAvailable === false ? 'Sampling…' : (Number(container.cpuPercent) || 0).toFixed(1) + '%';
        var cpuDetail = container.cpuSampleAvailable === false ? 'waiting for second sample' : 'peak ' + history.peakCpu.toFixed(1) + '%';
        var cpu = metricCell(cpuLabel, container.cpuPercent, cpuDetail, Number(container.cpuPercent) >= 90 ? 'progress-bar-red' : 'progress-bar-aqua');
        cpu.append(metricTrend(history.cpu, '#3c8dbc', 100));
        row.append(cpu);
        var configuredLimit = Number(container.configuredMemoryLimitBytes) || 0;
        var memoryDetail = configuredLimit > 0
          ? (Number(container.memoryPercent) || 0).toFixed(1) + '% of ' + bytes(configuredLimit)
          : (Number(container.memoryPercent) || 0).toFixed(1) + '% engine share';
        var memory = metricCell(bytes(container.memoryBytes), container.memoryPercent, memoryDetail + ' · peak ' + bytes(history.peakMemory), Number(container.memoryPercent) >= 85 ? 'progress-bar-red' : 'progress-bar-green');
        memory.append(metricTrend(history.memory, '#00a65a', 100));
        row.append(memory);
        row.append($('<td>').text(bytes(container.networkRxBytes) + ' ↓').append($('<span class="docker-monitor-subtext">').text(bytes(container.networkTxBytes) + ' ↑ cumulative')));
        row.append($('<td>').text(bytes(container.blockReadBytes) + ' read').append($('<span class="docker-monitor-subtext">').text(bytes(container.blockWriteBytes) + ' written')));
        row.append($('<td>').append($('<strong>').text(container.pids || 0)).append($('<span class="docker-monitor-subtext">').text('peak ' + history.peakPids)));
      } else {
        row.append(unavailableMetricCell('sampling'));
        row.append(unavailableMetricCell('sampling'));
        row.append($('<td colspan="3" class="docker-monitor-unavailable">').text('Metrics are not available for this sample.'));
      }
      rows.append(row);
    });
  }

  function hostCpuPercent(host) {
    var current = { total: Number(host.cpuTotal) || 0, idle: Number(host.cpuIdle) || 0 };
    var percent = null;
    if (previousCpu && current.total > previousCpu.total) {
      var totalDelta = current.total - previousCpu.total;
      var idleDelta = current.idle - previousCpu.idle;
      percent = totalDelta > 0 ? ((totalDelta - idleDelta) / totalDelta) * 100 : 0;
    }
    previousCpu = current;
    return percent === null ? null : clamp(percent);
  }

  function renderHost(host) {
    host = host || {};
    var cpu = hostCpuPercent(host);
    latestHostCpu = cpu;
    var memoryPercent = host.memoryTotalBytes ? (host.memoryUsedBytes / host.memoryTotalBytes) * 100 : 0;
    var diskPercent = host.diskTotalBytes ? (host.diskUsedBytes / host.diskTotalBytes) * 100 : 0;
    $('#dockerHostCpu').text(cpu === null ? 'Sampling…' : cpu.toFixed(1) + '%');
    $('#dockerHostCpuBar').css('width', (cpu === null ? 0 : cpu) + '%');
    $('#dockerHostLoad').text((host.cpuCores || '—') + ' CPUs · load ' + (host.load || [0, 0, 0]).join(' / ') + ' · uptime ' + duration(host.uptimeSeconds));
    $('#dockerHostMemory').text(memoryPercent.toFixed(1) + '%');
    $('#dockerHostMemoryBar').css('width', clamp(memoryPercent) + '%');
    $('#dockerHostMemoryDetail').text(bytes(host.memoryUsedBytes) + ' of ' + bytes(host.memoryTotalBytes));
    $('#dockerHostDisk').text(diskPercent.toFixed(1) + '%');
    $('#dockerHostDiskBar').css('width', clamp(diskPercent) + '%');
    $('#dockerHostDiskDetail').text(bytes(host.diskFreeBytes) + ' free of ' + bytes(host.diskTotalBytes));
    $('#dockerHostDisk').closest('.info-box').find('.info-box-icon').removeClass('bg-yellow bg-red').addClass(diskPercent >= 95 ? 'bg-red' : 'bg-yellow');
  }

  function allContainers(snapshot) {
    var containers = [];
    $.each(snapshot.engines || [], function(index, engine) {
      $.each(engine.containers || [], function(containerIndex, container) { containers.push(container); });
    });
    return containers;
  }

  function normalizeContainerCpu(snapshot) {
    var active = {};
    $.each(allContainers(snapshot), function(index, container) {
      if (! container.metricsAvailable || container.state !== 'running') { return; }
      var key = String(container.source || '') + ':' + String(container.id || container.name || '');
      var current = {
        container: Number(container.cpuTotalUsage) || 0,
        system: Number(container.systemCpuUsage) || 0,
        cpus: Math.max(1, Number(container.onlineCpus) || 1)
      };
      var previous = containerCpuSamples[key];
      if (previous && current.container > previous.container && current.system > previous.system) {
        container.cpuPercent = Math.max(0, (current.container - previous.container) / (current.system - previous.system) * current.cpus * 100);
        container.cpuSampleAvailable = true;
      } else if (container.cpuSampleAvailable !== true) {
        container.cpuPercent = 0;
        container.cpuSampleAvailable = false;
      }
      containerCpuSamples[key] = current;
      active[key] = true;
    });
    $.each(Object.keys(containerCpuSamples), function(index, key) {
      if (! active[key]) { delete containerCpuSamples[key]; }
    });
  }

  function engineContainers(snapshot) {
    return $.grep(allContainers(snapshot), function(container) {
      return engineFilter === 'all' || container.source === engineFilter;
    });
  }

  function matchesStateFilter(container) {
    if (stateFilter === 'running') { return container.state === 'running'; }
    if (stateFilter === 'stopped') { return container.state !== 'running'; }
    if (stateFilter === 'attention') { return containerSeverity(container) !== 'normal'; }
    if (stateFilter === 'history') { return hasPastExit(container); }
    return true;
  }

  function renderFilterControls(snapshot) {
    var select = $('#dockerMonitorEngineFilter');
    var previousEngine = engineFilter;
    select.empty().append($('<option value="all">').text('All Docker engines'));
    $.each(snapshot.engines || [], function(index, engine) {
      if (engine.available) {
        select.append($('<option>').val(engine.label).text(engine.label));
      }
    });
    if (select.find('option[value="' + previousEngine.replace(/"/g, '\\"') + '"]').length) {
      select.val(previousEngine);
    } else {
      engineFilter = 'all';
      select.val('all');
    }

    var containers = engineContainers(snapshot);
    $('#dockerFilterAll').text(containers.length);
    $('#dockerFilterRunning').text($.grep(containers, function(container) { return container.state === 'running'; }).length);
    $('#dockerFilterAttention').text($.grep(containers, function(container) { return containerSeverity(container) !== 'normal'; }).length);
    $('#dockerFilterHistory').text($.grep(containers, hasPastExit).length);
    $('#dockerFilterStopped').text($.grep(containers, function(container) { return container.state !== 'running'; }).length);
  }

  function reclaimableBuildCache(storage) {
    var total = 0;
    $.each(storage && storage.engines || [], function(index, engine) {
      if (engine.available) { total += Number(engine.buildCacheReclaimableBytes) || 0; }
    });
    return total;
  }

  function renderStorage(storage) {
    latestStorage = storage;
    var panel = $('#dockerStorageSummary').empty();
    var available = 0;
    $.each(storage && storage.engines || [], function(index, engine) {
      if (! engine.available) {
        panel.append($('<span class="docker-monitor-chip text-muted">').append($('<strong>').text(engine.label)).append(document.createTextNode(' · storage unavailable')));
        return;
      }
      available++;
      var details = [
        bytes(engine.layersBytes) + ' layers',
        bytes(engine.volumeBytes) + ' volumes',
        bytes(engine.containerWritableBytes) + ' writable data',
        bytes(engine.buildCacheReclaimableBytes) + ' reclaimable build cache'
      ].join(' · ');
      panel.append($('<span class="docker-monitor-chip">').append($('<strong>').text(engine.label)).append(document.createTextNode(' · ' + details)));
    });
    if (! available) {
      panel.append($('<span class="text-muted">').text('Docker storage inventory is unavailable. Live workload metrics are unaffected.'));
    } else {
      panel.prepend(document.createTextNode(' Engine storage ')).prepend($('<i class="fa fa-database">'));
    }
    var reclaimable = reclaimableBuildCache(storage);
    $('#dockerReclaimCache').attr('title', reclaimable > 0
      ? 'Latest inventory: ' + bytes(reclaimable) + ' of unused build cache can be reclaimed'
      : 'No reclaimable build cache was reported by the latest inventory');
  }

  function setCachePruneBusy(busy) {
    var button = $('#dockerReclaimCache');
    button.prop('disabled', busy);
    button.find('i').toggleClass('fa-eraser', ! busy).toggleClass('fa-circle-o-notch fa-spin', busy);
  }

  function cachePruneFailureSummary(response) {
    var failed = $.grep(response && response.engines || [], function(engine) { return ! engine.ok; });
    return $.map(failed, function(engine) {
      return engine.label + ': ' + (engine.message || ('HTTP ' + (engine.status || 502)));
    }).join(' · ');
  }

  function reclaimDockerBuildCache() {
    if (cachePruneRequest) { return; }
    setCachePruneBusy(true);
    $('#dockerCacheActionStatus').text('Reclaiming unused build cache across both Docker engines…');
    cachePruneRequest = $.ajax({
      url: reclaimCacheUrl,
      method: 'POST',
      dataType: 'json',
      data: {},
      timeout: 40000
    }).done(function(response) {
      var reclaimed = bytes(response.totalSpaceReclaimedBytes);
      var failed = cachePruneFailureSummary(response);
      if (response.complete) {
        toastr.success(reclaimed + ' of unused build cache reclaimed.', 'Docker Cache Reclaimed');
      } else {
        toastr.warning(reclaimed + ' reclaimed. ' + (failed || 'One Docker engine did not complete cleanup.'), 'Docker Cache Partially Reclaimed');
      }
      $('#dockerCacheActionStatus').text('Last cache reclaim: ' + reclaimed + ' at ' + new Date().toLocaleTimeString() + (failed ? ' · ' + failed : ''));
      if (storageRequest) {
        storageRequest.abort();
        storageRequest = null;
      }
      latestStorage = null;
      lastStorageAttemptAt = 0;
      $('#dockerStorageSummary').html('<i class="fa fa-circle-o-notch fa-spin"></i> Re-measuring Docker storage after cleanup…');
      loadStorage(true);
      loadSnapshot();
    }).fail(function(xhr) {
      var response = xhr.responseJSON || {};
      var message = response.message || 'Unable to reclaim Docker build cache.';
      var failed = cachePruneFailureSummary(response);
      if (failed) { message += ' ' + failed; }
      $('#dockerCacheActionStatus').text('Cache reclaim failed at ' + new Date().toLocaleTimeString() + ': ' + message);
      toastr.error(message, 'Docker Cache Cleanup Failed');
    }).always(function() {
      cachePruneRequest = null;
      setCachePruneBusy(false);
    });
  }

  function confirmCacheReclaim() {
    var reclaimable = reclaimableBuildCache(latestStorage);
    var measured = reclaimable > 0
      ? '<p><b>Latest measured reclaimable cache: ' + bytes(reclaimable) + '.</b></p>'
      : '<p>The current inventory did not report reclaimable cache. Docker will verify both engines during cleanup.</p>';
    alertify.confirm(
      'Reclaim Docker Build Cache',
      '<div class="text-left">' + measured + '<p>This removes <b>unused build cache</b> from the Application host and Job runtime. It does not delete containers, images, volumes, or networks.</p><p class="text-muted">Future image builds may take longer while removed layers are rebuilt.</p></div>',
      reclaimDockerBuildCache,
      function() {}
    );
  }

  function renderOperationalSummary(snapshot) {
    var summary = $('#dockerOperationalSummary');
    var containerIssues = $.map(allContainers(snapshot), function(container) {
      var severity = containerSeverity(container);
      return severity === 'normal' ? null : { name: container.name, severity: severity, reason: containerIssue(container) || 'requires attention' };
    });
    var issues = hostIssues(snapshot.host, latestHostCpu).concat(containerIssues);
    var pastExitCount = $.grep(allContainers(snapshot), hasPastExit).length;
    var unavailableEngines = $.grep(snapshot.engines || [], function(engine) { return ! engine.available; }).length;
    var critical = $.grep(issues, function(issue) { return issue.severity === 'critical'; }).length;
    var warning = issues.length - critical;
    summary.removeClass('callout-info callout-success callout-warning callout-danger').empty();

    if (critical > 0 || unavailableEngines > 0) {
      summary.addClass('callout-danger').append($('<h4>').append($('<i class="fa fa-exclamation-circle">')).append(document.createTextNode(' ' + (critical + unavailableEngines) + ' critical monitoring issue' + ((critical + unavailableEngines) === 1 ? '' : 's'))));
    } else if (warning > 0) {
      summary.addClass('callout-warning').append($('<h4>').append($('<i class="fa fa-warning">')).append(document.createTextNode(' ' + warning + ' infrastructure warning' + (warning === 1 ? '' : 's'))));
    } else {
      summary.addClass('callout-success').append($('<h4>').append($('<i class="fa fa-check-circle">')).append(document.createTextNode(' Docker workloads are healthy')));
    }

    if (issues.length) {
      var detail = $.map(issues.slice(0, 5), function(issue) { return issue.name + ': ' + issue.reason; }).join(' · ');
      if (issues.length > 5) { detail += ' · +' + (issues.length - 5) + ' more'; }
      if (unavailableEngines > 0) { detail += ' · ' + unavailableEngines + ' Docker engine' + (unavailableEngines === 1 ? ' is' : 's are') + ' unreachable'; }
      var reclaimable = reclaimableBuildCache(latestStorage);
      if (reclaimable > 0 && $.grep(issues, function(issue) { return issue.name === 'Repository disk'; }).length) {
        detail += ' · ' + bytes(reclaimable) + ' Docker build cache is reclaimable';
      }
      summary.append($('<p>').text(detail));
    } else if (unavailableEngines > 0) {
      summary.append($('<p>').text('One or more Docker engines could not be queried. Review the warning above.'));
    } else {
      var healthyDetail = 'No unhealthy containers, restart loops, recent failed exits, or high resource conditions were detected.';
      if (pastExitCount) { healthyDetail += ' ' + pastExitCount + ' older or signal-based exit' + (pastExitCount === 1 ? ' remains' : 's remain') + ' available under Past Exits.'; }
      summary.append($('<p>').text(healthyDetail));
    }
  }

  function renderEngines(snapshot) {
    var summary = $('#dockerEngineSummary').empty();
    var warnings = $('#dockerMonitorWarnings').empty();
    var running = 0;
    var stopped = 0;
    var host = snapshot.host || {};
    var hostDetail = [host.hostname || 'unknown host', host.kernel || '', host.architecture || '', host.cpuModel || ''].filter(Boolean).join(' · ');
    summary.append($('<span class="docker-monitor-chip">').attr('title', host.cpuModel || '').append($('<strong>').text('Web host')).append(document.createTextNode(' · ' + hostDetail)));
    $.each(snapshot.engines || [], function(index, engine) {
      if (! engine.available) {
        warnings.append($('<div class="alert alert-warning docker-engine-warning">').append($('<i class="fa fa-warning">')).append(document.createTextNode(' ' + engine.label + ': ' + (engine.message || 'unavailable'))));
        return;
      }
      running += Number(engine.containersRunning) || 0;
      stopped += Number(engine.containersStopped) || 0;
      var engineDetails = [
        engine.engineName || 'unknown host',
        'Docker ' + (engine.serverVersion || 'unknown'),
        (engine.operatingSystem || 'unknown OS') + (engine.architecture ? ' / ' + engine.architecture : ''),
        engine.cpus + ' CPUs',
        bytes(engine.memoryBytes),
        engine.storageDriver ? engine.storageDriver + ' storage' : '',
        engine.loggingDriver ? engine.loggingDriver + ' logs' : '',
        engine.cgroupVersion ? 'cgroup v' + engine.cgroupVersion : '',
        (Number(engine.images) || 0) + ' images'
      ].filter(Boolean).join(' · ');
      summary.append($('<span class="docker-monitor-chip">').attr('title', [engine.engineId || '', engine.kernelVersion || ''].filter(Boolean).join(' · ')).append($('<strong>').text(engine.label)).append(document.createTextNode(' · ' + engineDetails)));
      if (engine.containersTruncated) {
        warnings.append($('<div class="alert alert-warning docker-engine-warning">').append($('<i class="fa fa-warning">')).append(document.createTextNode(' ' + engine.label + ': showing ' + engine.containersReturned + ' of ' + engine.containersTotal + ' containers. Refine or clean historical containers to restore complete visibility.')));
      }
    });
    $('#dockerContainerCount').text(running + ' running');
    $('#dockerContainerDetail').text(stopped + ' stopped across ' + (snapshot.engines || []).length + ' engines');
    $('#dockerContainerBar').css('width', (running + stopped) ? clamp((running / (running + stopped)) * 100) + '%' : '0%');
  }

  function renderContainers(snapshot) {
    var query = $.trim($('#dockerMonitorSearch').val() || '').toLowerCase();
    var rows = $('#dockerContainerRows').empty();
    var containers = engineContainers(snapshot).slice().sort(function(left, right) {
      function sortClass(container) {
        var severity = containerSeverity(container);
        return severity === 'normal' && hasPastExit(container) ? 'history' : severity;
      }
      var severityOrder = { critical: 0, warning: 1, history: 2, normal: 3 };
      var severityDifference = severityOrder[sortClass(left)] - severityOrder[sortClass(right)];
      if (severityDifference !== 0) { return severityDifference; }
      var resourceDifference = Math.max(Number(right.cpuPercent) || 0, Number(right.memoryPercent) || 0) - Math.max(Number(left.cpuPercent) || 0, Number(left.memoryPercent) || 0);
      return resourceDifference || String(left.name || '').localeCompare(String(right.name || ''));
    });
    var visible = 0;
    $.each(containers, function(index, container) {
      var haystack = [container.name, container.image, container.source, container.state, container.status, container.hostname, container.composeProject, container.composeService, container.loggingDriver, (container.ipAddresses || []).join(' '), (container.ports || []).join(' ')].join(' ').toLowerCase();
      if (! matchesStateFilter(container) || (query && haystack.indexOf(query) === -1)) { return; }
      visible++;
      var state = String(container.state || 'unknown').toLowerCase();
      var severity = containerSeverity(container);
      var historicalExit = hasPastExit(container);
      var row = $('<tr>').addClass(severity === 'critical' ? 'danger' : (severity === 'warning' ? 'warning' : (historicalExit ? 'docker-monitor-history-row' : '')));
      row.append($('<td>').attr('title', container.command || '').append($('<span class="docker-monitor-name">').text(container.name)).append($('<span class="docker-monitor-subtext">').text(container.image + ' · ' + container.id)));
      var identity = $('<td class="docker-monitor-identity">').append($('<strong>').text(container.hostname || '—'));
      if (container.composeService) { identity.append($('<span class="docker-monitor-subtext">').text((container.composeProject ? container.composeProject + ' / ' : '') + container.composeService)); }
      else { identity.append($('<span class="docker-monitor-subtext">').text('standalone / unmanaged')); }
      if (container.composeOneOff) { identity.append($('<span class="docker-monitor-subtext">').text('Compose one-off task')); }
      if ((container.ipAddresses || []).length) { identity.append($('<span class="docker-monitor-subtext">').text(container.ipAddresses.join(' · '))); }
      if (container.networkMode) { identity.append($('<span class="docker-monitor-subtext">').text('network: ' + container.networkMode)); }
      if (Number(container.mountCount) > 0) { identity.append($('<span class="docker-monitor-subtext">').text(container.mountCount + ' mount' + (Number(container.mountCount) === 1 ? '' : 's'))); }
      var lifecycleAge = container.state === 'running' ? age(container.startedAt) : age(container.finishedAt || container.created);
      if (lifecycleAge) { identity.append($('<span class="docker-monitor-subtext">').text((container.state === 'running' ? 'started ' : 'changed ') + lifecycleAge)); }
      row.append(identity);
      var ports = $('<td class="docker-monitor-ports">');
      if ((container.ports || []).length) {
        $.each(container.ports, function(portIndex, port) { ports.append($('<div>').text(port)); });
      } else {
        ports.text('—');
      }
      row.append(ports);
      row.append($('<td>').text(container.source));
      var statusCell = $('<td>').append($('<span class="docker-monitor-state">').addClass('docker-monitor-state-' + (/^[a-z]+$/.test(state) ? state : 'unknown')).text(state)).append($('<span class="docker-monitor-subtext">').text(container.status || ''));
      if (container.health) { statusCell.append($('<span class="docker-monitor-health">').addClass('docker-monitor-health-' + container.health).text(container.health)); }
      if (container.restartCount || container.restartPolicy) { statusCell.append($('<span class="docker-monitor-subtext">').text(container.restartCount + ' restarts · policy ' + (container.restartPolicy || 'none'))); }
      if (state !== 'running') { statusCell.append($('<span class="docker-monitor-subtext">').text('exit code ' + (Number(container.exitCode) || 0))); }
      if (historicalExit) { statusCell.append($('<span class="docker-monitor-history">').text(Number(container.exitCode) === 143 ? 'SIGTERM stop · not active' : 'historical exit · not active')); }
      if (container.loggingDriver === 'none') { statusCell.append($('<span class="docker-monitor-subtext text-yellow">').text('container logs disabled')); }
      else if (container.loggingDriver) { statusCell.append($('<span class="docker-monitor-subtext">').text('logs: ' + container.loggingDriver)); }
      if (container.autoRemove) { statusCell.append($('<span class="docker-monitor-subtext">').text('auto-remove enabled')); }
      if (container.oomKilled) { statusCell.append($('<span class="label label-danger">').text('OOM killed')); }
      if (container.stateError) { statusCell.append($('<span class="docker-monitor-subtext text-red">').text(container.stateError)); }
      row.append(statusCell);
      if (container.metricsAvailable) {
        var cpuColor = Number(container.cpuPercent) >= 90 ? 'progress-bar-red' : (Number(container.cpuPercent) >= 75 ? 'progress-bar-yellow' : 'progress-bar-aqua');
        var memoryColor = Number(container.memoryPercent) >= 85 ? 'progress-bar-red' : (Number(container.memoryPercent) >= 75 ? 'progress-bar-yellow' : 'progress-bar-green');
        row.append(metricCell(container.cpuSampleAvailable === false ? 'Sampling…' : (Number(container.cpuPercent) || 0).toFixed(1) + '%', container.cpuPercent, container.cpuSampleAvailable === false ? 'waiting for second sample' : 'current usage', cpuColor));
        row.append(metricCell(bytes(container.memoryBytes), container.memoryPercent, (Number(container.memoryPercent) || 0).toFixed(1) + '% of ' + bytes(container.memoryLimitBytes), memoryColor));
        row.append($('<td>').text(bytes(container.networkRxBytes) + ' ↓').append($('<span class="docker-monitor-subtext">').text(bytes(container.networkTxBytes) + ' ↑')));
        row.append($('<td>').text(bytes(container.blockReadBytes) + ' read').append($('<span class="docker-monitor-subtext">').text(bytes(container.blockWriteBytes) + ' written')));
      } else {
        var unavailableDetail = state === 'running' ? 'metrics unavailable' : 'container stopped';
        row.append(unavailableMetricCell(unavailableDetail));
        row.append(unavailableMetricCell(unavailableDetail));
        row.append($('<td class="docker-monitor-unavailable">').text('—'));
        row.append($('<td class="docker-monitor-unavailable">').text('—'));
      }
      row.append($('<td>').text(container.pids || 0));
      rows.append(row);
    });
    if (! visible) {
      rows.append($('<tr>').append($('<td colspan="10" class="docker-monitor-empty">').text((query || stateFilter !== 'all' || engineFilter !== 'all') ? 'No containers match the current filters.' : 'No containers were returned.')));
    }
  }

  function render(snapshot) {
    latestSnapshot = snapshot;
    normalizeContainerCpu(snapshot);
    renderHost(snapshot.host);
    renderEngines(snapshot);
    renderOperationalSummary(snapshot);
    renderFilterControls(snapshot);
    renderContainers(snapshot);
    renderRunningJobs(snapshot);
    var generated = snapshot.generatedAt ? new Date(snapshot.generatedAt) : new Date();
    $('#dockerMonitorUpdated').text('Updated ' + generated.toLocaleTimeString());
  }

  function loadStorage(force) {
    if (storageRequest || (! force && Date.now() - lastStorageAttemptAt < 60000)) { return; }
    lastStorageAttemptAt = Date.now();
    storageRequest = $.ajax({ url: storageUrl, dataType: 'json', cache: false, timeout: 20000 })
      .done(function(response) {
        if (response && response.ok) {
          renderStorage(response);
          if (latestSnapshot) { renderOperationalSummary(latestSnapshot); }
        }
      })
      .fail(function() {
        $('#dockerStorageSummary').empty().append($('<i class="fa fa-warning text-yellow">')).append(document.createTextNode(' Docker storage inventory is temporarily unavailable. Live workload metrics are unaffected.'));
      })
      .always(function() { storageRequest = null; });
  }

  function loadSnapshot() {
    if (request) { return; }
    var startedAt = Date.now();
    $('#dockerMonitorRefresh i').addClass('fa-spin');
    request = $.ajax({ url: snapshotUrl, dataType: 'json', cache: false, timeout: 15000 })
      .done(function(response) {
        if (response && response.ok) {
          render(response);
          loadStorage(false);
          var generated = response.generatedAt ? new Date(response.generatedAt) : new Date();
          $('#dockerMonitorUpdated').text('Updated ' + generated.toLocaleTimeString() + ' · ' + (Date.now() - startedAt) + ' ms');
        }
      })
      .fail(function(xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to load Docker monitoring data.';
        $('#dockerMonitorWarnings').empty().append($('<div class="alert alert-danger">').text(message));
      })
      .always(function() {
        request = null;
        $('#dockerMonitorRefresh i').removeClass('fa-spin');
      });
  }

  function refreshLabel() {
    if (! $('#dockerMonitorAutoRefresh').is(':checked')) { return 'refresh paused'; }
    if (document.hidden) { return 'paused while hidden'; }
    return (Number($('#dockerMonitorInterval').val()) / 1000) + 's refresh';
  }

  function scheduleRefresh() {
    if (timer) {
      window.clearInterval(timer);
      timer = null;
    }
    if ($('#dockerMonitorAutoRefresh').is(':checked')) {
      timer = window.setInterval(function() { if (! document.hidden) { loadSnapshot(); } }, Number($('#dockerMonitorInterval').val()) || 5000);
    }
    $('#dockerMonitorPollState').text(refreshLabel());
  }

  $(function() {
    $('#dockerMonitorRefresh').on('click', function() { loadSnapshot(); loadStorage(true); });
    $('#dockerReclaimCache').on('click', confirmCacheReclaim);
    $('#dockerMonitorSearch').on('input', function() { if (latestSnapshot) { renderContainers(latestSnapshot); } });
    $('#dockerMonitorStateFilters').on('click', '[data-state]', function() {
      stateFilter = $(this).data('state') || 'all';
      $('#dockerMonitorStateFilters [data-state]').removeClass('active');
      $(this).addClass('active');
      if (latestSnapshot) { renderContainers(latestSnapshot); }
    });
    $('#dockerMonitorEngineFilter').on('change', function() {
      engineFilter = $(this).val() || 'all';
      if (latestSnapshot) {
        renderFilterControls(latestSnapshot);
        renderContainers(latestSnapshot);
      }
    });
    $('#dockerMonitorAutoRefresh, #dockerMonitorInterval').on('change', scheduleRefresh);
    $(document).on('visibilitychange', function() {
      $('#dockerMonitorPollState').text(refreshLabel());
      if (! document.hidden && $('#dockerMonitorAutoRefresh').is(':checked')) { loadSnapshot(); }
    });
    loadSnapshot();
    loadStorage(false);
    scheduleRefresh();
  });
})(jQuery);
</script>
