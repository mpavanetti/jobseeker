(function(root, factory) {
  var api = factory(typeof jQuery !== 'undefined' ? jQuery : null);
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.JobSeekerPipelineBuilder = api;
}(typeof window !== 'undefined' ? window : null, function($) {
  'use strict';

  function validateGraph(nodes, edges) {
    nodes = Array.isArray(nodes) ? nodes : [];
    edges = Array.isArray(edges) ? edges : [];
    if (nodes.length < 1) return {ok: false, message: 'Add at least one job node.', layers: []};
    var ids = {};
    var incoming = {};
    var outgoing = {};
    var duplicateEdges = {};
    var error = '';
    nodes.forEach(function(node) {
      if (!node || !/^[A-Za-z][A-Za-z0-9_-]{0,63}$/.test(String(node.id || '')) || ids[node.id]) error = 'Workflow node IDs must be valid and unique.';
      ids[node.id] = true;
      incoming[node.id] = 0;
      outgoing[node.id] = [];
    });
    if (error) return {ok: false, message: error, layers: []};
    edges.forEach(function(edge) {
      var pair = edge && edge.source + '>' + edge.target;
      if (!edge || !ids[edge.source] || !ids[edge.target] || edge.source === edge.target) error = 'Connections must link two different existing nodes.';
      else if (duplicateEdges[pair]) error = 'Only one connection is allowed between two nodes.';
      else if (['SUCCESS', 'FAILURE', 'ALWAYS'].indexOf(String(edge.condition || '').toUpperCase()) === -1) error = 'Connection condition is invalid.';
      if (!error) {
        duplicateEdges[pair] = true;
        incoming[edge.target] += 1;
        outgoing[edge.source].push(edge.target);
      }
    });
    if (error) return {ok: false, message: error, layers: []};
    var queue = Object.keys(incoming).filter(function(id) { return incoming[id] === 0; }).sort();
    var visited = [];
    var layers = [];
    while (queue.length) {
      var layer = queue.slice();
      queue = [];
      layers.push(layer);
      layer.forEach(function(id) {
        visited.push(id);
        outgoing[id].forEach(function(target) {
          incoming[target] -= 1;
          if (incoming[target] === 0) queue.push(target);
        });
      });
      queue.sort();
    }
    if (visited.length !== nodes.length) return {ok: false, message: 'Workflow connections contain a cycle.', layers: []};
    return {ok: true, message: 'Workflow graph is valid.', layers: layers, order: visited};
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(character) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
    });
  }

  function slug(value) {
    return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 52) || 'job';
  }

  function nodeIdForJob(jobName, sequence) {
    return 'node-' + slug(jobName) + '-' + Number(sequence || 0);
  }

  function initialize(config) {
    if (!$ || !config || !document.getElementById('pipelineCanvas')) return null;
    var state = {
      id: config.pipeline && Number(config.pipeline.id) || 0,
      nodes: Array.isArray(config.graph && config.graph.nodes) ? config.graph.nodes.slice() : [],
      edges: Array.isArray(config.graph && config.graph.edges) ? config.graph.edges.slice() : [],
      jobs: Array.isArray(config.jobs) ? config.jobs.slice() : [],
      selectedNode: '',
      selectedEdge: -1,
      connectSource: '',
      dirty: false,
      currentRunId: Number(config.currentRunId || 0),
      pollTimer: null,
      runNodeStates: {},
      selectedConsoleNode: '',
      consolePollTimer: null,
      console: null,
      sequence: 0,
      pointerLinkSource: '',
      draggingJob: false
    };
    var canvas = document.getElementById('pipelineCanvas');
    var nodeLayer = document.getElementById('pipelineNodeLayer');
    var edgeLayer = document.getElementById('pipelineEdgeLayer');

    state.nodes.forEach(function(node) {
      var match = String(node.id || '').match(/-(\d+)$/);
      if (match) state.sequence = Math.max(state.sequence, Number(match[1]));
      node.status = node.status || 'PENDING';
    });

    function markDirty() {
      state.dirty = true;
      $('#pipelineSave').addClass('btn-warning').removeClass('btn-primary');
    }

    function nodeById(id) {
      return state.nodes.find(function(node) { return node.id === id; });
    }

    function jenkinsJobPath(jobName) {
      return 'job/' + String(jobName || '').split('/').map(function(segment) {
        return encodeURIComponent(segment);
      }).join('/job/');
    }

    function isTerminalNodeStatus(status) {
      return ['SUCCESS', 'FAILURE', 'ABORTED', 'UNSTABLE', 'SKIPPED', 'NOT_BUILT'].indexOf(String(status || '').toUpperCase()) !== -1;
    }

    function clearConsolePollTimer() {
      if (state.consolePollTimer) clearTimeout(state.consolePollTimer);
      state.consolePollTimer = null;
    }

    function resetConsoleSession(message) {
      clearConsolePollTimer();
      state.console = null;
      var output = $('#pipelineConsoleOutput');
      if (window.JobSeekerConsole) window.JobSeekerConsole.setText(output, '', {live: false});
      output.html('<div class="job-console-empty">' + escapeHtml(message) + '</div>');
    }

    function renderStatusTrack() {
      if (!state.nodes.length) {
        $('#pipelineStatusTrack').html('<div class="pipeline-empty">Add jobs to visualize pipeline execution.</div>');
        $('#pipelineMonitorTotal, #pipelineMonitorRunning, #pipelineMonitorFinished').text(0);
        return;
      }
      var validation = validateGraph(state.nodes, state.edges);
      var layers = validation.ok ? validation.layers : [state.nodes.map(function(node) { return node.id; })];
      var running = 0;
      var finished = 0;
      var html = '';
      layers.forEach(function(layer, layerIndex) {
        html += '<div class="pipeline-status-wave"><span class="pipeline-status-wave-label">Wave ' + (layerIndex + 1) + '</span><div class="pipeline-status-wave-jobs">';
        layer.forEach(function(id) {
          var node = nodeById(id);
          if (!node) return;
          var details = state.runNodeStates[id] || {};
          var status = String(details.status || node.status || 'PENDING').toUpperCase();
          if (status === 'RUNNING') running += 1;
          if (isTerminalNodeStatus(status)) finished += 1;
          html += '<button type="button" class="pipeline-status-job' + (state.selectedConsoleNode === id ? ' is-selected' : '') + '" data-node-id="' + escapeHtml(id) + '" data-status="' + escapeHtml(status) + '" aria-pressed="' + (state.selectedConsoleNode === id ? 'true' : 'false') + '">' +
            '<span class="pipeline-status-job-dot"></span><span class="pipeline-status-job-copy"><strong>' + escapeHtml(node.label || node.job) + '</strong><small>' + escapeHtml(node.job) + '</small></span>' +
            '<span class="pipeline-status-job-result">' + escapeHtml(status.replace('_', ' ')) + (details.buildNumber ? '<small>#' + escapeHtml(details.buildNumber) + '</small>' : '') + '</span></button>';
        });
        html += '</div></div>';
      });
      $('#pipelineStatusTrack').html(html || '<div class="pipeline-empty">Add jobs to visualize pipeline execution.</div>');
      $('#pipelineMonitorTotal').text(state.nodes.length);
      $('#pipelineMonitorRunning').text(running);
      $('#pipelineMonitorFinished').text(finished);
    }

    function updateConsoleIdentity(node, details) {
      var status = String(details && details.status || node && node.status || 'PENDING').toUpperCase();
      $('#pipelineConsoleTitle').text(node ? (node.label || node.job) : 'Job console');
      $('#pipelineConsoleMeta').text(node ? status.replace('_', ' ') + (details && details.buildNumber ? ' - Build #' + details.buildNumber : '') + ' - ' + node.job : 'Select a job above to inspect its output.');
    }

    function appendConsoleText(session, text, live) {
      if (!text) return;
      var output = $('#pipelineConsoleOutput');
      if (window.JobSeekerConsole) {
        if (session.hasText) window.JobSeekerConsole.appendText(output, text, {live: live});
        else window.JobSeekerConsole.setText(output, text, {live: live});
      } else {
        if (!session.hasText) output.text('');
        output.append(document.createTextNode(text));
      }
      session.hasText = true;
      if ($('#pipelineConsoleAutoScroll').is(':checked') && output[0]) output.scrollTop(output[0].scrollHeight);
    }

    function scheduleConsolePoll(session) {
      clearConsolePollTimer();
      if (!session.complete) {
        state.consolePollTimer = setTimeout(function() { pollConsole(session); }, 1200);
      }
    }

    function pollConsole(session) {
      if (!session || state.console !== session || session.inFlight || session.complete || !session.buildNumber) return;
      session.inFlight = true;
      var jenkinsUrl = window.jobseekerJenkinsUrl || '';
      $.ajax({
        url: jenkinsUrl + jenkinsJobPath(session.job) + '/' + encodeURIComponent(session.buildNumber) + '/logText/progressiveText?start=' + encodeURIComponent(session.start),
        method: 'GET',
        cache: false,
        dataType: 'text'
      }).done(function(data, textStatus, xhr) {
        if (state.console !== session) return;
        var details = state.runNodeStates[session.nodeId] || {};
        var nextSize = parseInt(xhr.getResponseHeader('X-Text-Size'), 10);
        var hasMoreData = xhr.getResponseHeader('X-More-Data') === 'true';
        var terminal = isTerminalNodeStatus(details.status);
        appendConsoleText(session, data || '', !terminal);
        session.start = isNaN(nextSize) ? session.start + String(data || '').length : nextSize;
        session.complete = terminal && !hasMoreData;
        if (session.renderedStatus !== details.status && session.hasText && window.JobSeekerConsole) {
          window.JobSeekerConsole.setText($('#pipelineConsoleOutput'), window.JobSeekerConsole.getText($('#pipelineConsoleOutput')), {live: !terminal});
          session.renderedStatus = details.status;
        }
        scheduleConsolePoll(session);
      }).fail(function(xhr) {
        if (state.console !== session) return;
        var details = state.runNodeStates[session.nodeId] || {};
        if (isTerminalNodeStatus(details.status)) {
          appendConsoleText(session, '\n[JobSeeker] Unable to finish reading console output (HTTP ' + xhr.status + ').\n', false);
          session.complete = true;
        } else {
          scheduleConsolePoll(session);
        }
      }).always(function() {
        session.inFlight = false;
      });
    }

    function openNodeConsole(nodeId, forceReload) {
      var node = nodeById(nodeId);
      if (!node) return;
      state.selectedConsoleNode = nodeId;
      var details = state.runNodeStates[nodeId] || {};
      updateConsoleIdentity(node, details);
      renderStatusTrack();
      $('#pipelineConsoleReload').prop('disabled', !details.buildNumber);
      if (!details.buildNumber) {
        resetConsoleSession(details.status === 'SKIPPED' ? 'This job was skipped by the pipeline condition.' : 'Waiting for Jenkins to start this job.');
        return;
      }
      if (!forceReload && state.console && state.console.nodeId === nodeId && String(state.console.buildNumber) === String(details.buildNumber)) {
        if (!state.console.complete && !state.console.inFlight && !state.consolePollTimer) scheduleConsolePoll(state.console);
        return;
      }
      resetConsoleSession('Loading console output...');
      state.console = {
        nodeId: nodeId,
        job: node.job,
        buildNumber: details.buildNumber,
        start: 0,
        complete: false,
        inFlight: false,
        hasText: false,
        renderedStatus: details.status || ''
      };
      pollConsole(state.console);
    }

    function pipelineUrl(id) {
      return config.baseUrl + 'pipelines?id=' + encodeURIComponent(id) + '&environment=' + encodeURIComponent(config.environment);
    }

    function renderJobs() {
      var query = $.trim($('#pipelineJobSearch').val() || '').toLowerCase();
      var html = '';
      state.jobs.forEach(function(job) {
        if (query && String(job.name).toLowerCase().indexOf(query) === -1 && String(job.label || '').toLowerCase().indexOf(query) === -1) return;
        html += '<button type="button" class="pipeline-job-item" draggable="true" data-job="' + escapeHtml(job.name) + '" data-label="' + escapeHtml(job.label || job.name) + '">' +
          '<span class="pipeline-job-name">' + escapeHtml(job.label || job.name) + '</span>' +
          '<span class="pipeline-job-meta">' + escapeHtml(job.name) + '</span><span class="pipeline-job-add"><i class="fa fa-plus"></i></span></button>';
      });
      $('#pipelineJobList').html(html || '<div class="pipeline-empty">No jobs</div>');
    }

    function nextNodePosition() {
      for (var slot = 0; slot < 100; slot += 1) {
        var position = {x: 70 + (slot % 3) * 260, y: 65 + Math.floor(slot / 3) * 135};
        var occupied = state.nodes.some(function(node) {
          return Math.abs(Number(node.x || 0) - position.x) < 170 && Math.abs(Number(node.y || 0) - position.y) < 80;
        });
        if (!occupied) return position;
      }
      return {x: 70, y: 65 + state.nodes.length * 105};
    }

    function edgePath(source, target) {
      var sourceX = Number(source.x) + 190;
      var sourceY = Number(source.y) + 38;
      var targetX = Number(target.x);
      var targetY = Number(target.y) + 38;
      var distance = Math.max(60, Math.abs(targetX - sourceX) * 0.45);
      return 'M ' + sourceX + ' ' + sourceY + ' C ' + (sourceX + distance) + ' ' + sourceY + ', ' + (targetX - distance) + ' ' + targetY + ', ' + targetX + ' ' + targetY;
    }

    function resizeCanvas() {
      var viewport = canvas.parentElement;
      var width = viewport ? viewport.clientWidth : 0;
      var height = viewport ? viewport.clientHeight : 0;
      state.nodes.forEach(function(node) {
        width = Math.max(width, Number(node.x || 0) + 250);
        height = Math.max(height, Number(node.y || 0) + 150);
      });
      canvas.style.width = Math.max(1, Math.ceil(width)) + 'px';
      canvas.style.height = Math.max(500, Math.ceil(height)) + 'px';
    }

    function renderEdges() {
      var svg = '<defs>' +
        '<marker id="pipelineArrowSuccess" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 Z" fill="#348a54"></path></marker>' +
        '<marker id="pipelineArrowFailure" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 Z" fill="#bd3f3f"></path></marker>' +
        '<marker id="pipelineArrowAlways" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 Z" fill="#3976a8"></path></marker>' +
        '</defs>';
      state.edges.forEach(function(edge, index) {
        var source = nodeById(edge.source);
        var target = nodeById(edge.target);
        if (!source || !target) return;
        var condition = String(edge.condition || 'SUCCESS').toUpperCase();
        var css = condition === 'FAILURE' ? ' is-failure' : (condition === 'ALWAYS' ? ' is-always' : '');
        var marker = condition === 'FAILURE' ? 'pipelineArrowFailure' : (condition === 'ALWAYS' ? 'pipelineArrowAlways' : 'pipelineArrowSuccess');
        var path = edgePath(source, target);
        var labelX = (Number(source.x) + Number(target.x) + 190) / 2;
        var labelY = (Number(source.y) + Number(target.y)) / 2 + 30;
        svg += '<g class="pipeline-edge-group' + (state.selectedEdge === index ? ' is-selected' : '') + '" data-edge-index="' + index + '">' +
          '<path class="pipeline-edge-visible' + css + '" d="' + path + '" marker-end="url(#' + marker + ')"></path>' +
          '<path class="pipeline-edge-hit" d="' + path + '"></path>' +
          '<text class="pipeline-edge-label" x="' + labelX + '" y="' + labelY + '" text-anchor="middle">' + condition + '</text></g>';
      });
      edgeLayer.innerHTML = svg;
    }

    function renderNodes() {
      var html = '';
      state.nodes.forEach(function(node) {
        html += '<div class="pipeline-node' + (state.selectedNode === node.id ? ' is-selected' : '') + (state.connectSource === node.id ? ' is-source' : '') + '" data-node-id="' + escapeHtml(node.id) + '" data-status="' + escapeHtml(node.status || 'PENDING') + '" style="left:' + Number(node.x || 0) + 'px;top:' + Number(node.y || 0) + 'px">' +
          '<button type="button" class="pipeline-port pipeline-port-in" title="Connect to this job" aria-label="Connect to this job"></button>' +
          '<button type="button" class="pipeline-port pipeline-port-out" title="Connect from this job" aria-label="Connect from this job"></button>' +
          '<div class="pipeline-node-head">' + escapeHtml(node.status || 'PENDING') + '<span class="pipeline-node-status"></span></div>' +
          '<div class="pipeline-node-body"><span class="pipeline-node-label">' + escapeHtml(node.label || node.job) + '</span><span class="pipeline-node-job">' + escapeHtml(node.job) + '</span></div></div>';
      });
      nodeLayer.innerHTML = html;
      resizeCanvas();
      $('#pipelineCanvasEmpty').toggleClass('is-visible', state.nodes.length === 0);
      if ($.fn.draggable) {
        $('.pipeline-node').draggable({
          containment: '#pipelineCanvas',
          cancel: '.pipeline-port',
          grid: [12, 12],
          drag: function(event, ui) {
            var node = nodeById($(this).data('node-id'));
            if (node) {
              node.x = Math.max(0, Math.round(ui.position.left));
              node.y = Math.max(0, Math.round(ui.position.top));
              renderEdges();
            }
          },
          stop: function(event, ui) {
            var node = nodeById($(this).data('node-id'));
            if (node) {
              node.x = Math.max(0, Math.round(ui.position.left));
              node.y = Math.max(0, Math.round(ui.position.top));
              markDirty();
              renderEdges();
            }
          }
        });
      }
    }

    function renderInspector() {
      var html = '';
      if (state.selectedEdge >= 0 && state.edges[state.selectedEdge]) {
        var edge = state.edges[state.selectedEdge];
        var source = nodeById(edge.source);
        var target = nodeById(edge.target);
        html = '<h4>Connection</h4><p><strong>' + escapeHtml(source ? source.label : edge.source) + '</strong><br><i class="fa fa-long-arrow-down"></i><br><strong>' + escapeHtml(target ? target.label : edge.target) + '</strong></p>' +
          '<div class="pipeline-condition btn-group" role="group">' +
          ['SUCCESS', 'FAILURE', 'ALWAYS'].map(function(condition) { return '<button type="button" class="btn btn-default btn-xs pipeline-condition-button' + (edge.condition === condition ? ' is-active' : '') + '" data-condition="' + condition + '">' + condition + '</button>'; }).join('') +
          '</div><button type="button" class="btn btn-danger btn-sm btn-block" id="pipelineDeleteEdge" style="margin-top:10px"><i class="fa fa-trash"></i> Remove connection</button>';
      } else if (state.selectedNode && nodeById(state.selectedNode)) {
        var node = nodeById(state.selectedNode);
        html = '<h4>Job node</h4><div class="form-group"><label for="pipelineNodeLabel">Label</label><input id="pipelineNodeLabel" class="form-control input-sm" maxlength="120" value="' + escapeHtml(node.label || node.job) + '"></div>' +
          '<dl><dt>Jenkins job</dt><dd>' + escapeHtml(node.job) + '</dd><dt>Node ID</dt><dd><code>' + escapeHtml(node.id) + '</code></dd></dl>' +
          '<button type="button" class="btn btn-danger btn-sm btn-block" id="pipelineDeleteNode"><i class="fa fa-trash"></i> Remove job</button>';
      } else {
        html = '<div class="pipeline-empty">Nothing selected</div>';
      }
      $('#pipelineInspector').html(html);
    }

    function renderAll() {
      renderNodes();
      renderEdges();
      renderInspector();
    }

    function addNode(jobName, label, x, y) {
      state.sequence += 1;
      var node = {id: nodeIdForJob(jobName, state.sequence), job: jobName, label: label || jobName, x: Math.max(0, x), y: Math.max(0, y), status: 'PENDING'};
      state.nodes.push(node);
      state.selectedNode = node.id;
      state.selectedEdge = -1;
      markDirty();
      renderAll();
    }

    function addEdge(source, target) {
      var edge = {source: source, target: target, condition: 'SUCCESS'};
      state.edges.push(edge);
      var validation = validateGraph(state.nodes, state.edges);
      if (!validation.ok) {
        state.edges.pop();
        toastr.error(validation.message, 'Pipeline');
        return;
      }
      state.selectedEdge = state.edges.length - 1;
      state.selectedNode = '';
      state.connectSource = '';
      markDirty();
      renderAll();
    }

    function serialize() {
      return {
        id: state.id,
        name: $.trim($('#pipelineName').val()),
        pipeline_key: $.trim($('#pipelineKey').val()),
        group_name: $.trim($('#pipelineGroup').val()),
        description: $.trim($('#pipelineDescription').val()),
        is_active: $('#pipelineActive').is(':checked') ? 1 : 0,
        schedule_enabled: $('#pipelineScheduleEnabled').is(':checked') ? 1 : 0,
        schedule_cron: $.trim($('#pipelineScheduleCron').val()),
        nodes_json: JSON.stringify(state.nodes.map(function(node) { return {id: node.id, job: node.job, label: node.label, x: node.x, y: node.y}; })),
        edges_json: JSON.stringify(state.edges)
      };
    }

    function savePipeline(callback) {
      var payload = serialize();
      if (!payload.name || !payload.pipeline_key || !payload.group_name) {
        toastr.error('Name, key, and group are required.', 'Pipeline');
        return;
      }
      if (payload.schedule_enabled && !payload.schedule_cron) {
        toastr.error('Enter a Jenkins cron schedule.', 'Pipeline');
        $('#pipelineScheduleCron').trigger('focus');
        return;
      }
      var validation = validateGraph(state.nodes, state.edges);
      if (!validation.ok) {
        toastr.error(validation.message, 'Pipeline');
        return;
      }
      $('#pipelineSave').prop('disabled', true);
      $.ajax({type: 'POST', dataType: 'json', url: config.urls.save, data: payload}).done(function(response) {
        state.id = Number(response.id);
        state.dirty = false;
        $('#pipelineSave').removeClass('btn-warning').addClass('btn-primary');
        $('#pipelineRun, #pipelineDeploy, #pipelineDelete').prop('disabled', false);
        window.history.replaceState({}, '', pipelineUrl(state.id));
        toastr.success(response.message, 'Pipeline');
        if (typeof callback === 'function') callback(response);
      }).fail(function(xhr) {
        var response = xhr.responseJSON || {};
        toastr.error(response.message || 'Pipeline could not be saved.', 'Pipeline');
      }).always(function() { $('#pipelineSave').prop('disabled', false); });
    }

    var scheduleValidationTimer = null;
    var scheduleValidationSeq = 0;

    function debounceScheduleValidation() {
      clearTimeout(scheduleValidationTimer);
      scheduleValidationTimer = setTimeout(refreshScheduleValidation, 350);
    }

    function refreshScheduleValidation() {
      var $box = $('#pipelineScheduleValidation');
      if (!$box.length || !config.urls || !config.urls.validateSchedule) {
        return;
      }
      var enabled = $('#pipelineScheduleEnabled').is(':checked');
      var cron = $.trim($('#pipelineScheduleCron').val());
      if (!enabled || !cron) {
        $box.attr('hidden', true).empty();
        return;
      }
      var seq = ++scheduleValidationSeq;
      $.ajax({type: 'POST', dataType: 'json', url: config.urls.validateSchedule, data: {schedule_cron: cron}})
        .done(function(data) { if (seq === scheduleValidationSeq) { renderScheduleValidation(data); } })
        .fail(function() { if (seq === scheduleValidationSeq) { $box.attr('hidden', true).empty(); } });
    }

    function renderScheduleValidation(data) {
      var $box = $('#pipelineScheduleValidation');
      var html = '';
      if (data && data.spec) {
        html += '<div class="psv-spec"><code>' + escapeHtml(data.spec) + '</code></div>';
      }
      if (data && !data.ok && data.error) {
        html += '<div class="psv-line psv-error"><i class="fa fa-times-circle"></i> ' + escapeHtml(data.error) + '</div>';
      }
      if (data && data.jenkins && data.jenkins.message) {
        var level = data.jenkins.level === 'error' ? 'psv-error' : (data.jenkins.level === 'warning' ? 'psv-warn' : 'psv-ok');
        html += '<div class="psv-line ' + level + '"><i class="fa fa-clock-o"></i> ' + escapeHtml(data.jenkins.message) + '</div>';
      }
      (data && data.warnings ? data.warnings : []).forEach(function(w) {
        html += '<div class="psv-line psv-warn"><i class="fa fa-exclamation-triangle"></i> ' + escapeHtml(w) + '</div>';
      });
      if (data && data.ok && !data.error && !html) {
        html = '<div class="psv-line psv-ok"><i class="fa fa-check-circle"></i> Schedule looks valid.</div>';
      }
      $box.html(html).attr('hidden', html === '' ? true : null);
    }

    function autoLayout() {
      var validation = validateGraph(state.nodes, state.edges);
      if (!validation.ok) {
        toastr.error(validation.message, 'Pipeline');
        return;
      }
      validation.layers.forEach(function(layer, column) {
        layer.forEach(function(id, row) {
          var node = nodeById(id);
          node.x = 70 + column * 280;
          node.y = 65 + row * 135;
        });
      });
      markDirty();
      renderAll();
    }

    function updateRun(response) {
      $('#pipelineRunStatus').text(response.status).attr('data-status', response.status);
      $('#pipelineMonitorStatus').text(response.status).attr('data-status', response.status);
      var nodeStates = {};
      (response.nodes || []).forEach(function(item) { nodeStates[item.nodeId] = item; });
      state.runNodeStates = nodeStates;
      state.nodes.forEach(function(node) { node.status = nodeStates[node.id] ? nodeStates[node.id].status : 'PENDING'; });
      var html = '';
      state.nodes.forEach(function(node) {
        var details = nodeStates[node.id];
        html += '<div class="pipeline-run-node" data-status="' + escapeHtml(node.status || 'PENDING') + '"><span class="pipeline-run-dot"></span><span>' + escapeHtml(node.label || node.job) + '</span>' + (details && details.buildNumber ? '<small>#' + details.buildNumber + '</small>' : '') + '</div>';
      });
      $('#pipelineRunNodes').html(html);
      renderNodes();
      renderEdges();
      renderStatusTrack();
      if (!state.selectedConsoleNode && state.nodes.length) state.selectedConsoleNode = state.nodes[0].id;
      if (state.selectedConsoleNode) openNodeConsole(state.selectedConsoleNode, false);
      var active = response.status === 'RUNNING' || response.status === 'QUEUED';
      $('#pipelineStop').prop('disabled', !active);
      if (!active && state.pollTimer) {
        clearInterval(state.pollTimer);
        state.pollTimer = null;
      }
    }

    function pollRun() {
      if (!state.currentRunId) return;
      $.getJSON(config.urls.status.replace('{id}', state.currentRunId)).done(updateRun).fail(function() {
        $('#pipelineRunStatus').text('UNAVAILABLE').attr('data-status', 'FAILURE');
      });
    }

    function beginPolling() {
      if (state.pollTimer) clearInterval(state.pollTimer);
      pollRun();
      state.pollTimer = setInterval(pollRun, 3000);
    }

    function runPipeline() {
      if (!state.id) {
        toastr.warning('Save the pipeline before running it.', 'Pipeline');
        return;
      }
      if (state.dirty) {
        toastr.warning('Save pipeline changes before running.', 'Pipeline');
        return;
      }
      $('#pipelineRun').prop('disabled', true);
      $.ajax({type: 'POST', dataType: 'json', url: config.urls.run, data: {id: state.id}}).done(function(response) {
        state.currentRunId = Number(response.runId);
        state.runNodeStates = {};
        state.nodes.forEach(function(node) { node.status = 'PENDING'; });
        resetConsoleSession('Waiting for Jenkins to start this job.');
        updateRun({status: 'QUEUED', nodes: []});
        beginPolling();
        toastr.success(response.message, 'Pipeline');
      }).fail(function(xhr) {
        var response = xhr.responseJSON || {};
        toastr.error(response.message || 'Pipeline could not be queued.', 'Pipeline');
      }).always(function() { $('#pipelineRun').prop('disabled', false); });
    }

    $('#pipelineJobSearch').on('input', renderJobs);
    $('#pipelineJobList').on('dragstart', '.pipeline-job-item', function(event) {
      state.draggingJob = true;
      var transfer = event.originalEvent.dataTransfer;
      transfer.setData('application/x-jobseeker-job', JSON.stringify({job: $(this).data('job'), label: $(this).data('label')}));
      transfer.effectAllowed = 'copy';
    });
    $('#pipelineJobList').on('dragend', '.pipeline-job-item', function() {
      setTimeout(function() { state.draggingJob = false; }, 0);
    });
    $('#pipelineJobList').on('click', '.pipeline-job-item', function() {
      if (state.draggingJob) return;
      var position = nextNodePosition();
      addNode($(this).data('job'), $(this).data('label'), position.x, position.y);
    });
    $(canvas).on('dragover', function(event) { event.preventDefault(); event.originalEvent.dataTransfer.dropEffect = 'copy'; });
    $(canvas).on('drop', function(event) {
      event.preventDefault();
      var raw = event.originalEvent.dataTransfer.getData('application/x-jobseeker-job');
      if (!raw) return;
      var item;
      try { item = JSON.parse(raw); } catch (error) { return; }
      var rect = canvas.getBoundingClientRect();
      addNode(item.job, item.label, Math.round(event.originalEvent.clientX - rect.left - 95), Math.round(event.originalEvent.clientY - rect.top - 38));
    });
    $(nodeLayer).on('click', '.pipeline-node', function(event) {
      if ($(event.target).hasClass('pipeline-port')) return;
      state.selectedNode = $(this).data('node-id');
      state.selectedEdge = -1;
      renderAll();
    });
    $(nodeLayer).on('click', '.pipeline-port-out', function(event) {
      event.stopPropagation();
      state.connectSource = $(this).closest('.pipeline-node').data('node-id');
      state.selectedNode = state.connectSource;
      state.selectedEdge = -1;
      renderAll();
    });
    $(nodeLayer).on('click', '.pipeline-port-in', function(event) {
      event.stopPropagation();
      var target = $(this).closest('.pipeline-node').data('node-id');
      if (!state.connectSource) {
        state.selectedNode = target;
        renderAll();
        return;
      }
      addEdge(state.connectSource, target);
    });
    $(nodeLayer).on('pointerdown', '.pipeline-port-out', function(event) {
      if (event.originalEvent.button !== 0) return;
      event.preventDefault();
      event.stopPropagation();
      state.pointerLinkSource = $(this).closest('.pipeline-node').data('node-id');
      state.connectSource = state.pointerLinkSource;
      state.selectedNode = state.pointerLinkSource;
      state.selectedEdge = -1;
      $(canvas).addClass('is-linking');
      $('.pipeline-node').removeClass('is-source');
      $(this).closest('.pipeline-node').addClass('is-source');
    });
    $(document).off('pointerup.pipelineBuilder').on('pointerup.pipelineBuilder', function(event) {
      if (!state.pointerLinkSource) return;
      var source = state.pointerLinkSource;
      var sourcePort = $(event.target).closest('.pipeline-port-out');
      var releasedSource = sourcePort.length && $.contains(nodeLayer, sourcePort[0]) ? sourcePort.closest('.pipeline-node').data('node-id') : '';
      var targetPort = $(event.target).closest('.pipeline-port-in');
      var target = targetPort.length && $.contains(nodeLayer, targetPort[0]) ? targetPort.closest('.pipeline-node').data('node-id') : '';
      state.pointerLinkSource = '';
      $(canvas).removeClass('is-linking');
      if (target) {
        addEdge(source, target);
      } else if (releasedSource === source) {
        state.connectSource = source;
        state.selectedNode = source;
        state.selectedEdge = -1;
        renderAll();
      } else {
        state.connectSource = '';
        renderAll();
      }
    });
    $(edgeLayer).on('click', '.pipeline-edge-hit', function(event) {
      event.stopPropagation();
      state.selectedEdge = Number($(this).closest('.pipeline-edge-group').attr('data-edge-index'));
      state.selectedNode = '';
      state.connectSource = '';
      renderAll();
    });
    $(canvas).on('click', function(event) {
      if (event.target !== canvas) return;
      state.selectedNode = '';
      state.selectedEdge = -1;
      state.connectSource = '';
      renderAll();
    });
    $('#pipelineInspector').on('click', '.pipeline-condition-button', function() {
      if (state.selectedEdge < 0) return;
      state.edges[state.selectedEdge].condition = $(this).data('condition');
      markDirty();
      renderEdges();
      renderInspector();
    });
    $('#pipelineInspector').on('click', '#pipelineDeleteEdge', function() {
      if (state.selectedEdge < 0) return;
      state.edges.splice(state.selectedEdge, 1);
      state.selectedEdge = -1;
      markDirty();
      renderAll();
    });
    $('#pipelineInspector').on('click', '#pipelineDeleteNode', function() {
      var id = state.selectedNode;
      state.nodes = state.nodes.filter(function(node) { return node.id !== id; });
      state.edges = state.edges.filter(function(edge) { return edge.source !== id && edge.target !== id; });
      state.selectedNode = '';
      state.connectSource = '';
      markDirty();
      renderAll();
    });
    $('#pipelineInspector').on('input', '#pipelineNodeLabel', function() {
      var node = nodeById(state.selectedNode);
      if (node) {
        node.label = $(this).val();
        markDirty();
        $('.pipeline-node[data-node-id="' + node.id + '"] .pipeline-node-label').text(node.label);
      }
    });
    $('#pipelineName').on('input', function() {
      if (!$('#pipelineKey').val()) $('#pipelineKey').val(slug($(this).val()));
      markDirty();
    });
    $('#pipelineKey, #pipelineGroup, #pipelineDescription, #pipelineActive, #pipelineScheduleCron').on('input change', markDirty);
    $('#pipelineScheduleEnabled').on('change', function() {
      $('#pipelineScheduleCron').prop('disabled', !this.checked);
      markDirty();
      if (this.checked) $('#pipelineScheduleCron').trigger('focus');
      refreshScheduleValidation();
    });
    $('#pipelineScheduleCron').on('input change', debounceScheduleValidation);
    refreshScheduleValidation();
    $('#pipelinePicker').on('change', function() {
      var id = Number($(this).val() || 0);
      window.location.href = id ? pipelineUrl(id) : config.baseUrl + 'pipelines?environment=' + encodeURIComponent(config.environment);
    });
    $('#pipelineSave').on('click', function() { savePipeline(); });
    $('#pipelineValidate').on('click', function() {
      var payload = serialize();
      $.ajax({type: 'POST', dataType: 'json', url: config.urls.validate, data: {nodes_json: payload.nodes_json, edges_json: payload.edges_json}}).done(function(response) {
        toastr.success(response.message + ' ' + response.layers.length + ' execution wave(s).', 'Pipeline');
      }).fail(function(xhr) { toastr.error((xhr.responseJSON || {}).message || 'Workflow is invalid.', 'Pipeline'); });
    });
    $('#pipelineAutoLayout').on('click', autoLayout);
    $('#pipelineDeploy').on('click', function() {
      if (!state.id) return;
      if (state.dirty) {
        toastr.warning('Save pipeline changes before deploying.', 'Pipeline');
        return;
      }
      $('#pipelineDeployError').hide().empty();
      $('#pipelineDeployTarget').val('');
      $('#pipelineDeployOverwrite').prop('checked', false);
      $('#pipelineDeployModal').modal('show');
    });
    $('#pipelineDeployConfirm').on('click', function() {
      var targetEnvironment = $('#pipelineDeployTarget').val();
      if (!targetEnvironment) {
        $('#pipelineDeployError').text('Select a target environment.').show();
        return;
      }
      var button = $(this).prop('disabled', true);
      $('#pipelineDeployError').hide().empty();
      $.ajax({
        type: 'POST',
        dataType: 'json',
        url: config.urls.deploy,
        data: {id: state.id, target_environment: targetEnvironment, overwrite: $('#pipelineDeployOverwrite').is(':checked') ? 1 : 0}
      }).done(function(response) {
        $('#pipelineDeployModal').modal('hide');
        toastr.success(response.message, 'Pipeline');
        window.location.href = config.baseUrl + 'pipelines?id=' + encodeURIComponent(response.id) + '&environment=' + encodeURIComponent(response.environment);
      }).fail(function(xhr) {
        var response = xhr.responseJSON || {};
        var message = response.message || 'Pipeline could not be deployed.';
        $('#pipelineDeployError').text(message).show();
      }).always(function() { button.prop('disabled', false); });
    });
    $('#pipelineRun').on('click', runPipeline);
    $('#pipelineEmptyFocusJobs').on('click', function() {
      var rail = $('.pipeline-rail-left');
      rail.addClass('is-emphasized');
      $('#pipelineJobSearch').trigger('focus');
      setTimeout(function() { rail.removeClass('is-emphasized'); }, 1200);
    });
    $('#pipelineStatusTrack').on('click', '.pipeline-status-job', function() {
      openNodeConsole($(this).data('node-id'), false);
    });
    $('#pipelineConsoleReload').on('click', function() {
      if (state.selectedConsoleNode) openNodeConsole(state.selectedConsoleNode, true);
    });
    $('#pipelineStop').on('click', function() {
      if (!state.currentRunId) return;
      $.ajax({type: 'POST', dataType: 'json', url: config.urls.stop, data: {run_id: state.currentRunId}}).done(function(response) { toastr.info(response.message, 'Pipeline'); pollRun(); });
    });
    $('#pipelineDelete').on('click', function() {
      if (!state.id) return;
      alertify.confirm('Delete pipeline', 'Delete this pipeline and its Jenkins orchestrator job?', function() {
        $.ajax({type: 'POST', dataType: 'json', url: config.urls.delete, data: {id: state.id}}).done(function() { window.location.href = config.baseUrl + 'pipelines?environment=' + encodeURIComponent(config.environment); });
      }, function() {});
    });
    $('.pipeline-run-history').on('click', function() {
      state.currentRunId = Number($(this).data('run-id'));
      state.runNodeStates = {};
      state.nodes.forEach(function(node) { node.status = 'PENDING'; });
      resetConsoleSession('Loading the selected pipeline run.');
      renderStatusTrack();
      beginPolling();
    });
    $(window).on('resize.pipelineBuilder', function() {
      resizeCanvas();
      renderEdges();
    });

    renderJobs();
    renderAll();
    renderStatusTrack();
    if (state.nodes.length) openNodeConsole(state.nodes[0].id, false);
    if (state.currentRunId) beginPolling();
    return state;
  }

  return {validateGraph: validateGraph, nodeIdForJob: nodeIdForJob, initialize: initialize};
}));
