/**
 * Renderer and client for a job's task DAG.
 *
 * A Pipeline is a graph of jobs and is drawn by pipeline-builder.js on its own
 * canvas. This is the layer below: the graph of tasks inside one job, drawn
 * wherever a job is inspected - Job View, Job Execution and the live preview in
 * the execution editor.
 *
 * The layout and every piece of markup are pure functions of (payload,
 * options), so they can be unit tested in Node without a DOM, and so the same
 * coordinates drive the SVG, the accessible table and the detail panel.
 */
(function(root, factory) {
  var api = factory();

  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }

  if (root) {
    root.JobSeekerTaskGraph = api;
  }
}(typeof window !== 'undefined' ? window : null, function() {
  'use strict';

  var NODE_WIDTH = 168;
  var NODE_HEIGHT = 56;
  var COLUMN_GAP = 68;
  var ROW_GAP = 18;
  var PADDING = 16;

  var STATUS_META = {
    SUCCESS: {cls: 'jtg-success', label: 'Succeeded', icon: 'fa-check-circle'},
    FAILURE: {cls: 'jtg-failure', label: 'Failed', icon: 'fa-times-circle'},
    UPSTREAM_FAILED: {cls: 'jtg-upstream-failed', label: 'Upstream failed', icon: 'fa-chain-broken'},
    RUNNING: {cls: 'jtg-running', label: 'Running', icon: 'fa-spinner'},
    SKIPPED: {cls: 'jtg-skipped', label: 'Skipped', icon: 'fa-minus-circle'},
    PENDING: {cls: 'jtg-pending', label: 'Waiting', icon: 'fa-clock-o'},
    QUEUED: {cls: 'jtg-queued', label: 'Queued', icon: 'fa-hourglass-o'},
    DECLARED: {cls: 'jtg-declared', label: 'Declared', icon: 'fa-circle-o'}
  };

  var TRIGGER_LABEL = {
    SUCCESS: 'on success',
    FAILURE: 'on failure',
    ALWAYS: 'always'
  };

  // What an un-started task looks like depends on what the run is doing. A
  // queued build has not started anything yet; a running build has tasks still
  // waiting their turn; a job that has never run only has declarations. Showing
  // the same neutral node for all three is what made a queued build look as
  // though it had already produced the previous run's outcome.
  var UNSTARTED_STATUS = {
    pending: 'QUEUED',
    running: 'PENDING',
    finished: 'DECLARED',
    none: 'DECLARED'
  };

  function escapeText(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function statusMeta(status) {
    return STATUS_META[String(status || '').toUpperCase()] || STATUS_META.DECLARED;
  }

  function truncate(value, limit) {
    var text = String(value == null ? '' : value);
    return text.length > limit ? text.slice(0, limit - 1) + '…' : text;
  }

  function formatDuration(milliseconds) {
    if (milliseconds == null || isNaN(milliseconds)) {
      return '';
    }
    var value = Number(milliseconds);
    if (value < 1000) {
      return value + 'ms';
    }
    if (value < 60000) {
      return (value / 1000).toFixed(1) + 's';
    }
    var minutes = Math.floor(value / 60000);
    var seconds = Math.round((value % 60000) / 1000);
    return minutes + 'm ' + seconds + 's';
  }

  function formatCount(value) {
    if (value == null || isNaN(value)) {
      return '';
    }
    return Number(value).toLocaleString();
  }

  /**
   * How many rows a task reported to TMF, as "processed of total" when both are
   * known. A task that opened a transaction but never called progress() reports
   * nothing, which is different from reporting zero.
   */
  function rowsLabel(node) {
    var processed = node.recordsProcessed;
    var total = node.recordsTotal;
    if (processed == null && total == null) {
      return '';
    }
    if (total == null || total === processed) {
      return formatCount(processed == null ? total : processed) + ' rows';
    }
    if (processed == null) {
      return formatCount(total) + ' rows';
    }
    return formatCount(processed) + ' / ' + formatCount(total) + ' rows';
  }

  /**
   * Recompute layers from the edges rather than trusting the stored `layers`.
   * A graph can arrive from the static scanner, from the runtime manifest, or
   * from an older stored version; deriving the layout here keeps one code path
   * and means a graph with a missing layer list still draws.
   */
  function layersFor(tasks, edges) {
    var indegree = {};
    var adjacency = {};
    var index = {};

    tasks.forEach(function(task) {
      indegree[task.id] = 0;
      adjacency[task.id] = [];
      index[task.id] = true;
    });

    edges.forEach(function(edge) {
      if (!index[edge.source] || !index[edge.target]) {
        return;
      }
      adjacency[edge.source].push(edge.target);
      indegree[edge.target] += 1;
    });

    var ready = Object.keys(indegree).filter(function(id) { return indegree[id] === 0; }).sort();
    var layers = [];
    var placed = 0;

    while (ready.length) {
      var layer = ready.slice();
      layers.push(layer);
      ready = [];
      layer.forEach(function(id) {
        placed += 1;
        adjacency[id].forEach(function(target) {
          indegree[target] -= 1;
          if (indegree[target] === 0) {
            ready.push(target);
          }
        });
      });
      ready.sort();
    }

    // A cycle should never reach the browser - both the runtime and the scanner
    // reject one - but never drop nodes on the floor if it does.
    if (placed < tasks.length) {
      var seen = {};
      layers.forEach(function(layer) {
        layer.forEach(function(id) { seen[id] = true; });
      });
      var orphans = tasks.filter(function(task) { return !seen[task.id]; }).map(function(task) { return task.id; });
      if (orphans.length) {
        layers.push(orphans.sort());
      }
    }

    return layers;
  }

  /**
   * Position every node and edge. Layers become columns so long chains read
   * left to right, which is how both Airflow and the Pipelines canvas draw a
   * DAG, and independent branches stack vertically inside a column.
   */
  function layout(graph, states, runState) {
    graph = graph || {};
    states = states || {};
    var unstarted = UNSTARTED_STATUS[String(runState || 'none')] || 'DECLARED';
    var tasks = (graph.tasks || []).filter(function(task) { return task && task.id; });
    var edges = (graph.edges || []).filter(function(edge) { return edge && edge.source && edge.target; });

    if (!tasks.length) {
      return {nodes: [], edges: [], width: 0, height: 0, layers: [], byId: {}};
    }

    var layers = layersFor(tasks, edges);
    var declared = {};
    tasks.forEach(function(task) { declared[task.id] = task; });

    var tallest = layers.reduce(function(maximum, layer) { return Math.max(maximum, layer.length); }, 0);
    var contentHeight = tallest * NODE_HEIGHT + Math.max(0, tallest - 1) * ROW_GAP;
    var nodes = [];
    var positions = {};

    layers.forEach(function(layer, layerIndex) {
      var layerHeight = layer.length * NODE_HEIGHT + Math.max(0, layer.length - 1) * ROW_GAP;
      var offset = PADDING + (contentHeight - layerHeight) / 2;
      layer.forEach(function(id, rowIndex) {
        var task = declared[id] || {id: id};
        var state = states[id] || null;
        var node = {
          id: id,
          label: task.description || id,
          x: PADDING + layerIndex * (NODE_WIDTH + COLUMN_GAP),
          y: offset + rowIndex * (NODE_HEIGHT + ROW_GAP),
          width: NODE_WIDTH,
          height: NODE_HEIGHT,
          layer: layerIndex,
          row: rowIndex,
          started: state !== null,
          status: state ? String(state.status || 'PENDING').toUpperCase() : unstarted,
          attempt: (state && state.attempt) || 0,
          retries: task.retries || 0,
          trigger: String(task.trigger || 'SUCCESS').toUpperCase(),
          durationMs: state && state.durationMs != null ? state.durationMs : null,
          startedAt: (state && state.startedAt) || '',
          finishedAt: (state && state.finishedAt) || '',
          message: (state && state.message) || '',
          consumes: task.consumes || [],
          produces: task.produces || [],
          dependsOn: task.depends_on || [],
          tracked: task.track !== false,
          recordsTotal: state && state.recordsTotal != null ? state.recordsTotal : null,
          recordsProcessed: state && state.recordsProcessed != null ? state.recordsProcessed : null,
          tmfInstanceId: (state && state.tmfInstanceId) || '',
          tmfStatus: (state && state.tmfStatus) || '',
          tmfHasErrors: !!(state && state.tmfHasErrors),
          upstream: (state && state.upstream) || {}
        };
        positions[id] = node;
        nodes.push(node);
      });
    });

    var drawnEdges = [];
    edges.forEach(function(edge) {
      var source = positions[edge.source];
      var target = positions[edge.target];
      if (!source || !target) {
        return;
      }
      var x1 = source.x + source.width;
      var y1 = source.y + source.height / 2;
      var x2 = target.x;
      var y2 = target.y + target.height / 2;
      var curve = Math.max(24, (x2 - x1) / 2);
      drawnEdges.push({
        source: edge.source,
        target: edge.target,
        condition: String(edge.condition || target.trigger || 'SUCCESS').toUpperCase(),
        x1: x1, y1: y1, x2: x2, y2: y2,
        path: 'M ' + x1 + ' ' + y1 + ' C ' + (x1 + curve) + ' ' + y1 + ', ' + (x2 - curve) + ' ' + y2 + ', ' + x2 + ' ' + y2
      });
    });

    return {
      nodes: nodes,
      byId: positions,
      edges: drawnEdges,
      layers: layers,
      width: PADDING * 2 + layers.length * NODE_WIDTH + Math.max(0, layers.length - 1) * COLUMN_GAP,
      height: PADDING * 2 + contentHeight
    };
  }

  /** Map the API payload's task rows to a { taskId: state } lookup. */
  function statesFrom(payload) {
    var states = {};
    ((payload && payload.tasks) || []).forEach(function(task) {
      if (task && task.id) {
        states[task.id] = task;
      }
    });
    return states;
  }

  function runStateOf(payload) {
    return String((payload && payload.runState) || 'none');
  }

  function summaryText(payload) {
    var graph = (payload && payload.graph) || null;
    var tasks = (graph && graph.tasks) || [];
    if (!tasks.length) {
      return '';
    }
    var states = statesFrom(payload);
    var counts = {SUCCESS: 0, FAILURE: 0, UPSTREAM_FAILED: 0, RUNNING: 0, SKIPPED: 0};
    var started = 0;
    tasks.forEach(function(task) {
      var state = states[task.id];
      if (!state) {
        return;
      }
      started += 1;
      var status = String(state.status || '').toUpperCase();
      if (counts[status] !== undefined) {
        counts[status] += 1;
      }
    });

    var parts = [tasks.length + ' task' + (tasks.length === 1 ? '' : 's')];
    if (runStateOf(payload) === 'pending') {
      parts.push('none started yet');
      return parts.join(' · ');
    }
    if (started < tasks.length && started > 0) {
      parts.push(started + ' started');
    }
    if (counts.RUNNING) { parts.push(counts.RUNNING + ' running'); }
    if (counts.SUCCESS) { parts.push(counts.SUCCESS + ' succeeded'); }
    var failed = counts.FAILURE + counts.UPSTREAM_FAILED;
    if (failed) { parts.push(failed + ' failed'); }
    if (counts.SKIPPED) { parts.push(counts.SKIPPED + ' skipped'); }
    return parts.join(' · ');
  }

  /** The tasks a re-run of the failures would cover. */
  function failedTasks(payload) {
    return ((payload && payload.tasks) || []).filter(function(task) {
      var status = String(task.status || '').toUpperCase();
      return status === 'FAILURE' || status === 'UPSTREAM_FAILED';
    }).map(function(task) { return task.id; });
  }

  function base() {
    if (typeof window === 'undefined') {
      return '/';
    }
    return window.baseURL || (typeof baseURL !== 'undefined' ? baseURL : '/');
  }

  // --- markup ---------------------------------------------------------------

  function nodeClasses(node, selection) {
    var classes = ['jtg-node', statusMeta(node.status).cls];
    if (selection) {
      if (node.id === selection.id) {
        classes.push('jtg-selected');
      } else if (selection.related[node.id]) {
        classes.push('jtg-related');
      } else {
        classes.push('jtg-dimmed');
      }
    }
    return classes.join(' ');
  }

  function renderNode(node, selection) {
    var meta = statusMeta(node.status);
    var badges = [];
    if (node.trigger !== 'SUCCESS') {
      badges.push(TRIGGER_LABEL[node.trigger] || node.trigger.toLowerCase());
    }
    if (node.attempt > 1) {
      badges.push('attempt ' + node.attempt);
    }
    var duration = formatDuration(node.durationMs);
    if (duration) {
      badges.push(duration);
    }
    var rows = rowsLabel(node);
    if (rows) {
      badges.push(rows);
    }

    var title = node.id + ' — ' + meta.label +
      (node.label && node.label !== node.id ? '\n' + node.label : '') +
      (rows ? '\n' + rows : '') +
      (node.message ? '\n' + node.message : '') +
      (node.consumes.length ? '\nconsumes: ' + node.consumes.join(', ') : '') +
      (node.produces.length ? '\nproduces: ' + node.produces.join(', ') : '');

    return '<g class="' + nodeClasses(node, selection) + '" data-task="' + escapeText(node.id) + '" ' +
      'transform="translate(' + node.x + ',' + node.y + ')" ' +
      'tabindex="0" role="button" aria-pressed="' + (selection && selection.id === node.id ? 'true' : 'false') + '" ' +
      'aria-label="' + escapeText(node.id + ', ' + meta.label + '. Select for details.') + '">' +
      '<title>' + escapeText(title) + '</title>' +
      '<rect class="jtg-node-box" width="' + node.width + '" height="' + node.height + '" rx="6" ry="6"></rect>' +
      '<rect class="jtg-node-bar" width="4" height="' + node.height + '" rx="2" ry="2"></rect>' +
      '<text class="jtg-node-id" x="14" y="22">' + escapeText(truncate(node.id, 22)) + '</text>' +
      '<text class="jtg-node-meta" x="14" y="40">' + escapeText(truncate(badges.join(' · ') || meta.label, 26)) + '</text>' +
      '</g>';
  }

  function edgeClasses(edge, selection) {
    var classes = ['jtg-edge', 'jtg-edge-' + edge.condition.toLowerCase()];
    if (selection) {
      if (edge.source === selection.id || edge.target === selection.id) {
        classes.push('jtg-edge-active');
      } else {
        classes.push('jtg-edge-dimmed');
      }
    }
    return classes.join(' ');
  }

  function renderSvg(plan, selection) {
    if (!plan.nodes.length) {
      return '';
    }
    var edges = plan.edges.map(function(edge) {
      return '<path class="' + edgeClasses(edge, selection) + '" d="' + edge.path + '" ' +
        'marker-end="url(#jtg-arrow)"><title>' + escapeText(edge.source + ' → ' + edge.target +
        ' (' + (TRIGGER_LABEL[edge.condition] || edge.condition.toLowerCase()) + ')') + '</title></path>';
    }).join('');

    return '<div class="jtg-canvas">' +
      '<svg class="jtg-svg" viewBox="0 0 ' + plan.width + ' ' + plan.height + '" width="' + plan.width + '" height="' + plan.height + '" role="img" aria-label="Task graph">' +
        '<defs><marker id="jtg-arrow" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="7" markerHeight="7" orient="auto-start-reverse">' +
          '<path d="M 0 0 L 8 4 L 0 8 z" class="jtg-arrow-head"></path></marker></defs>' +
        '<g class="jtg-edges">' + edges + '</g>' +
        '<g class="jtg-nodes">' + plan.nodes.map(function(node) { return renderNode(node, selection); }).join('') + '</g>' +
      '</svg>' +
    '</div>';
  }

  function renderTable(plan, selection) {
    if (!plan.nodes.length) {
      return '';
    }
    var anyRows = plan.nodes.some(function(node) { return rowsLabel(node) !== ''; });
    var rows = plan.nodes.map(function(node) {
      var meta = statusMeta(node.status);
      var describedAssets = []
        .concat(node.consumes.length ? ['consumes ' + node.consumes.join(', ')] : [])
        .concat(node.produces.length ? ['produces ' + node.produces.join(', ')] : []);
      var detail = node.message ||
        (node.label && node.label !== node.id ? node.label : '') ||
        describedAssets.join(' · ');
      return '<tr class="' + meta.cls + (selection && selection.id === node.id ? ' jtg-row-selected' : '') + '" data-task="' + escapeText(node.id) + '">' +
        '<td class="jtg-cell-id"><code>' + escapeText(node.id) + '</code></td>' +
        '<td><span class="jtg-status"><i class="fa ' + meta.icon + '"></i> ' + escapeText(meta.label) + '</span></td>' +
        '<td>' + escapeText(node.attempt ? node.attempt + (node.retries ? '/' + (node.retries + 1) : '') : '—') + '</td>' +
        '<td>' + escapeText(formatDuration(node.durationMs) || '—') + '</td>' +
        (anyRows ? '<td>' + escapeText(rowsLabel(node) || '—') + '</td>' : '') +
        '<td class="jtg-cell-detail">' + escapeText(truncate(detail, 160) || '—') + '</td>' +
      '</tr>';
    }).join('');

    return '<table class="table table-condensed jtg-table">' +
      '<thead><tr><th>Task</th><th>Status</th><th>Attempt</th><th>Duration</th>' +
      (anyRows ? '<th>Rows</th>' : '') + '<th>Detail</th></tr></thead>' +
      '<tbody>' + rows + '</tbody></table>';
  }

  function renderRunPicker(payload, options) {
    var runs = (payload && payload.runs) || [];
    if (runs.length < 2 || (options && options.runPicker === false)) {
      return '';
    }
    var selected = payload.runKey || '';
    var choices = runs.map(function(run) {
      var label = (run.buildNumber ? '#' + run.buildNumber : run.runKey) +
        ' · ' + String(run.status || '').toLowerCase() +
        (run.startedAt ? ' · ' + run.startedAt : '');
      return '<option value="' + escapeText(run.runKey) + '"' + (run.runKey === selected ? ' selected' : '') + '>' +
        escapeText(label) + '</option>';
    }).join('');
    return '<div class="jtg-run-picker"><label>Run</label><select class="form-control input-sm jtg-run-select">' + choices + '</select></div>';
  }

  /** One line saying exactly which run is on screen, so it can never be mistaken. */
  function runLabel(payload) {
    var state = runStateOf(payload);
    if (state === 'pending') {
      var build = payload.pendingBuild || payload.requestedBuild;
      return {cls: 'jtg-scope-pending', text: 'Build #' + build + ' is queued — no task has started yet.'};
    }
    var run = payload.run || null;
    if (!run) {
      return {cls: 'jtg-scope-none', text: 'This job has not recorded a task run yet.'};
    }
    var name = run.buildNumber ? 'build #' + run.buildNumber : 'run ' + run.runKey;
    if (state === 'running') {
      return {cls: 'jtg-scope-running', text: 'Showing ' + name + ', still running.'};
    }
    return {cls: 'jtg-scope-finished', text: 'Showing ' + name + (run.startedAt ? ', started ' + run.startedAt : '') + '.'};
  }

  function renderActions(payload, options, selection) {
    if (!payload || payload.canRun !== true || (options && options.actions === false)) {
      return '';
    }
    var buttons = [];
    var failures = failedTasks(payload);

    if (failures.length && payload.runKey) {
      buttons.push('<button type="button" class="btn btn-warning btn-xs jtg-action" data-action="resume" ' +
        'title="Start a build that skips the tasks which already succeeded in this run">' +
        '<i class="fa fa-repeat"></i> Re-run ' + failures.length + ' failed task' + (failures.length === 1 ? '' : 's') + '</button>');
    }
    if (selection) {
      buttons.push('<button type="button" class="btn btn-primary btn-xs jtg-action" data-action="task" ' +
        'data-task="' + escapeText(selection.id) + '" title="Start a build that runs only this task">' +
        '<i class="fa fa-play"></i> Run ' + escapeText(truncate(selection.id, 20)) + '</button>');
    }
    buttons.push('<button type="button" class="btn btn-default btn-xs jtg-action" data-action="all" ' +
      'title="Start an ordinary build of this job"><i class="fa fa-play-circle-o"></i> Run all</button>');

    return '<div class="jtg-actions">' + buttons.join('') + '</div>';
  }

  function renderDetail(plan, payload, selection) {
    if (!selection) {
      return '<p class="jtg-detail-hint text-muted">Select a task to see its attempts, timing, datasets and transaction.</p>';
    }
    var node = plan.byId[selection.id];
    if (!node) {
      return '';
    }
    var meta = statusMeta(node.status);

    function row(label, value) {
      return value === '' || value == null
        ? ''
        : '<div class="jtg-detail-item"><span>' + escapeText(label) + '</span><strong>' + value + '</strong></div>';
    }

    var upstream = Object.keys(node.upstream || {}).map(function(id) {
      return '<code>' + escapeText(id) + '</code> ' + escapeText(String(node.upstream[id]).toLowerCase());
    }).join(', ') || (node.dependsOn.length
      ? node.dependsOn.map(function(id) { return '<code>' + escapeText(id) + '</code>'; }).join(', ')
      : '');

    var tmf = '';
    if (node.tmfInstanceId) {
      tmf = '<a href="' + escapeText(base() + 'tmf/taskRun/' + encodeURIComponent(payload.runKey || '')) + '" ' +
        'target="_blank" rel="noopener"><code>' + escapeText(truncate(node.tmfInstanceId, 18)) + '</code> ' +
        '<i class="fa fa-external-link"></i></a>' +
        (node.tmfHasErrors ? ' <span class="jtg-detail-flag">errors recorded</span>' : '');
    } else if (node.tracked && node.started) {
      tmf = '<span class="text-muted">not recorded</span>';
    }

    return '<div class="jtg-detail" data-task="' + escapeText(node.id) + '">' +
      '<div class="jtg-detail-head">' +
        '<strong><code>' + escapeText(node.id) + '</code></strong>' +
        '<span class="jtg-status ' + meta.cls + '"><i class="fa ' + meta.icon + '"></i> ' + escapeText(meta.label) + '</span>' +
        '<button type="button" class="close jtg-detail-close" aria-label="Close">&times;</button>' +
      '</div>' +
      (node.label && node.label !== node.id ? '<p class="jtg-detail-description">' + escapeText(node.label) + '</p>' : '') +
      '<div class="jtg-detail-grid">' +
        row('Trigger', escapeText(TRIGGER_LABEL[node.trigger] || node.trigger)) +
        row('Attempt', node.attempt ? escapeText(node.attempt + ' of ' + (node.retries + 1)) : '') +
        row('Duration', escapeText(formatDuration(node.durationMs))) +
        row('Rows', escapeText(rowsLabel(node))) +
        row('Started', escapeText(node.startedAt)) +
        row('Finished', escapeText(node.finishedAt)) +
        row('Upstream', upstream) +
        row('Consumes', node.consumes.map(function(k) { return '<code>' + escapeText(k) + '</code>'; }).join(', ')) +
        row('Produces', node.produces.map(function(k) { return '<code>' + escapeText(k) + '</code>'; }).join(', ')) +
        row('Transaction', tmf) +
      '</div>' +
      (node.message ? '<pre class="jtg-detail-message">' + escapeText(node.message) + '</pre>' : '') +
    '</div>';
  }

  /** The selected task plus its direct neighbours, which stay undimmed. */
  function selectionFor(plan, taskId) {
    if (!taskId || !plan.byId[taskId]) {
      return null;
    }
    var related = {};
    plan.edges.forEach(function(edge) {
      if (edge.source === taskId) { related[edge.target] = true; }
      if (edge.target === taskId) { related[edge.source] = true; }
    });
    return {id: taskId, related: related};
  }

  function renderHtml(payload, options) {
    options = options || {};
    payload = payload || {};
    var graph = payload.graph || null;
    var tasks = (graph && graph.tasks) || [];

    if (!tasks.length) {
      return '<p class="text-muted jtg-empty">' +
        'This job runs a single script. Declare <code>@dag.task(...)</code> functions and call <code>dag.run()</code> to split it into tasks.' +
        '</p>';
    }

    var state = runStateOf(payload);
    var plan = layout(graph, statesFrom(payload), state);
    var selection = selectionFor(plan, options.selected);
    var scope = runLabel(payload);

    var header = '<div class="jtg-header">' +
      '<span class="jtg-summary">' + escapeText(summaryText(payload)) + '</span>' +
      ((graph.source === 'scan' || payload.stored === false) && state === 'none'
        ? '<span class="jtg-source" title="Read from the job source. Run the job once to record per-task status.">declared</span>'
        : '') +
      renderRunPicker(payload, options) +
      '</div>' +
      '<p class="jtg-scope ' + scope.cls + '">' + escapeText(scope.text) + '</p>' +
      renderActions(payload, options, selection);

    var warnings = ((graph && graph.warnings) || []).map(function(warning) {
      return '<li><i class="fa fa-exclamation-triangle"></i> ' + escapeText(warning) + '</li>';
    }).join('');

    return '<div class="jtg-panel' + (selection ? ' jtg-has-selection' : '') + '" data-run-state="' + escapeText(state) + '">' +
      header + renderSvg(plan, selection) + renderDetail(plan, payload, selection) + renderTable(plan, selection) +
      (warnings ? '<ul class="jtg-warnings">' + warnings + '</ul>' : '') + '</div>';
  }

  // --- mounting -------------------------------------------------------------

  function resolveElement(container) {
    if (typeof container === 'string') {
      return typeof document !== 'undefined' ? document.querySelector(container) : null;
    }
    if (container && container.jquery) {
      return container.get(0);
    }
    return container || null;
  }

  /**
   * Re-render the element from the payload it already holds. Selecting a task
   * is a pure view change, so it never refetches.
   */
  function repaint(element) {
    var payload = element.jobseekerTaskPayload;
    if (!payload) {
      return;
    }
    element.innerHTML = renderHtml(payload, element.jobseekerTaskOptions || {});
  }

  function selectTask(element, taskId) {
    var options = element.jobseekerTaskOptions || {};
    options.selected = options.selected === taskId ? '' : taskId;
    element.jobseekerTaskOptions = options;
    repaint(element);
  }

  function attach(element) {
    if (element.jobseekerTaskBound) {
      return;
    }
    element.jobseekerTaskBound = true;

    element.addEventListener('click', function(event) {
      var action = event.target.closest ? event.target.closest('.jtg-action') : null;
      if (action) {
        event.preventDefault();
        runAction(element, action);
        return;
      }
      if (event.target.closest && event.target.closest('.jtg-detail-close')) {
        event.preventDefault();
        selectTask(element, '');
        return;
      }
      var owner = event.target.closest ? event.target.closest('[data-task]') : null;
      if (owner && !owner.classList.contains('jtg-detail')) {
        selectTask(element, owner.getAttribute('data-task'));
      }
    });

    element.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        selectTask(element, '');
        return;
      }
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }
      var owner = event.target.closest ? event.target.closest('.jtg-node') : null;
      if (owner) {
        event.preventDefault();
        selectTask(element, owner.getAttribute('data-task'));
      }
    });
  }

  function runAction(element, button) {
    var payload = element.jobseekerTaskPayload || {};
    var options = element.jobseekerTaskOptions || {};
    var mode = button.getAttribute('data-action');
    var request = {
      job: payload.job,
      environment: options.environment || payload.environment || '',
      mode: mode === 'task' ? 'tasks' : mode
    };
    if (mode === 'resume') {
      request.run = payload.runKey;
    }
    if (mode === 'task') {
      request.tasks = button.getAttribute('data-task');
    }

    button.disabled = true;
    var promise = run(request);
    if (!promise) {
      button.disabled = false;
      return;
    }
    promise.done(function(response) {
      if (typeof options.onRun === 'function') {
        options.onRun(response, request);
      }
    }).fail(function(xhr) {
      if (typeof options.onRunFailed === 'function') {
        options.onRunFailed(xhr, request);
      }
    }).always(function() {
      button.disabled = false;
    });
  }

  function render(container, payload, options) {
    var element = resolveElement(container);
    if (!element) {
      return null;
    }

    options = options || {};
    var previous = element.jobseekerTaskOptions || {};
    if (options.selected === undefined) {
      options.selected = previous.selected || '';
    }
    element.jobseekerTaskPayload = payload;
    element.jobseekerTaskOptions = options;

    var html = renderHtml(payload, options);
    element.innerHTML = html;
    attach(element);
    return html;
  }

  // --- client ---------------------------------------------------------------

  /**
   * `buildNumber` matters on Job Execution: it watches one build, and asking
   * for "the latest run" would show a concurrent build of the same job.
   */
  function load(controller, jobName, environment, runKey, buildNumber) {
    var jQueryRef = typeof window !== 'undefined' ? window.jQuery : null;
    if (!jQueryRef) {
      return null;
    }
    return jQueryRef.getJSON(base() + controller + '/tasks', {
      job: jobName,
      environment: environment || '',
      run: runKey || '',
      build: buildNumber || ''
    });
  }

  function scan(payload) {
    var jQueryRef = typeof window !== 'undefined' ? window.jQuery : null;
    if (!jQueryRef) {
      return null;
    }
    return jQueryRef.post(base() + 'jobCreation/scanTasks', payload);
  }

  /** Queue a build that resumes a run, runs named tasks, or runs everything. */
  function run(request) {
    var jQueryRef = typeof window !== 'undefined' ? window.jQuery : null;
    if (!jQueryRef) {
      return null;
    }
    return jQueryRef.post(base() + 'jobExecution/runTasks', request);
  }

  return {
    NODE_WIDTH: NODE_WIDTH,
    NODE_HEIGHT: NODE_HEIGHT,
    STATUS_META: STATUS_META,
    UNSTARTED_STATUS: UNSTARTED_STATUS,
    escapeText: escapeText,
    failedTasks: failedTasks,
    formatCount: formatCount,
    formatDuration: formatDuration,
    layersFor: layersFor,
    layout: layout,
    load: load,
    render: render,
    renderHtml: renderHtml,
    rowsLabel: rowsLabel,
    run: run,
    scan: scan,
    selectionFor: selectionFor,
    statesFrom: statesFrom,
    summaryText: summaryText
  };
}));
