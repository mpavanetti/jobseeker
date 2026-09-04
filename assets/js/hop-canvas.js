/*
 * Draws an Apache Hop workflow or pipeline the way its designer laid it out.
 *
 * Hop stores the canvas coordinates inside the .hwf/.hpl itself, so the picture
 * a person sees in the Hop GUI can be rebuilt from the same file JobSeeker
 * already has on disk - no desktop tool, no second source of truth, and an
 * unknown transform type still appears as a box rather than breaking the view.
 *
 * The graph comes from /hop/graph as { nodes, edges, notes }; this module only
 * turns it into SVG.
 */
(function(root, factory) {
  var api = factory();

  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }

  if (root) {
    root.JobSeekerHopCanvas = api;
  }
}(typeof window !== 'undefined' ? window : null, function() {
  'use strict';

  var SVG_NS = 'http://www.w3.org/2000/svg';
  var NODE_HEIGHT = 44;
  var NODE_MIN_WIDTH = 110;
  var NODE_MAX_WIDTH = 220;
  var CHARACTER_WIDTH = 7.1;
  var PADDING = 48;
  // Hop positions a 32px icon; a readable box is several times wider, so the
  // designer's coordinates are spread out until no two boxes touch. Without
  // this, two adjacent actions overlap and the hop between them has no room to
  // be drawn at all.
  var MIN_GAP_X = 46;
  var MIN_GAP_Y = 26;
  var MAX_SCALE = 6;

  // Hop's own vocabulary for the handful of actions that are control flow
  // rather than work. Everything else is drawn as an ordinary step, which is
  // what keeps an unrecognised type visible instead of hidden.
  var NODE_ROLE = {
    SPECIAL: 'start',
    START: 'start',
    SUCCESS: 'success',
    ABORT: 'failure',
    DUMMY: 'passthrough'
  };

  function element(name, attributes, text) {
    var node = document.createElementNS(SVG_NS, name);
    Object.keys(attributes || {}).forEach(function(key) {
      node.setAttribute(key, attributes[key]);
    });
    if (text !== undefined && text !== null) {
      node.appendChild(document.createTextNode(String(text)));
    }
    return node;
  }

  function nodeWidth(node) {
    var label = String(node.name || '');
    return Math.max(NODE_MIN_WIDTH, Math.min(NODE_MAX_WIDTH, Math.round(label.length * CHARACTER_WIDTH) + 44));
  }

  /**
   * The scale that keeps the designer's arrangement but stops boxes touching.
   *
   * Only pairs that could actually collide are measured: two nodes far apart
   * vertically may sit at the same x without either box overlapping the other.
   */
  function spreadFactors(points, widths) {
    var neededX = 1;
    var neededY = 1;

    for (var i = 0; i < points.length; i++) {
      for (var j = i + 1; j < points.length; j++) {
        var dx = Math.abs(points[i].x - points[j].x);
        var dy = Math.abs(points[i].y - points[j].y);
        var wantX = (widths[i] + widths[j]) / 2 + MIN_GAP_X;
        var wantY = NODE_HEIGHT + MIN_GAP_Y;

        // Boxes clear each other if either axis separates them, so only ask an
        // axis to grow when it is the one doing the work.
        if (dx > 0.5 && dx * neededX < wantX && dy * neededY < wantY) {
          neededX = Math.max(neededX, wantX / dx);
        }
        if (dy > 0.5 && dy * neededY < wantY && dx * neededX < wantX) {
          neededY = Math.max(neededY, wantY / dy);
        }
      }
    }

    return { x: Math.min(neededX, MAX_SCALE), y: Math.min(neededY, MAX_SCALE) };
  }

  /**
   * Hop places a node by the top-left of its 32px icon and draws the name
   * underneath. Centring the box on that point, then spreading the whole graph
   * just enough that no two boxes touch, keeps the arrangement the designer
   * made recognisable while leaving room for readable labels and hops.
   */
  function layout(graph) {
    var nodes = graph.nodes || [];
    var widths = nodes.map(nodeWidth);
    var points = nodes.map(function(node, index) {
      // A file saved without coordinates (some generators omit them) still has
      // to be readable, so fall back to a simple left-to-right chain.
      var hasPosition = isFinite(node.x) && isFinite(node.y) && (node.x !== 0 || node.y !== 0 || index === 0);
      return hasPosition
        ? { x: node.x + 16, y: node.y + 16 }
        : { x: 80 + index * 200, y: 100 };
    });

    var scale = spreadFactors(points, widths);
    var placed = {};
    var boxes = nodes.map(function(node, index) {
      var box = {
        node: node,
        width: widths[index],
        height: NODE_HEIGHT,
        x: Math.round(points[index].x * scale.x - widths[index] / 2),
        y: Math.round(points[index].y * scale.y - NODE_HEIGHT / 2)
      };
      placed[node.name] = box;
      return box;
    });

    separate(boxes);
    return { boxes: boxes, byName: placed, scale: scale };
  }

  /**
   * Push apart anything the scale could not separate.
   *
   * Scaling alone is bounded — a graph whose nodes sit almost on top of each
   * other would need an absurd factor — so this is the guarantee: after it, no
   * two boxes overlap, and a hop between them always has room to be drawn.
   * Nodes move along whichever axis they are already closest to escaping on,
   * which keeps the designer's arrangement rather than re-laying it out.
   */
  function separate(boxes) {
    for (var pass = 0; pass < 24; pass++) {
      var moved = false;

      for (var i = 0; i < boxes.length; i++) {
        for (var j = i + 1; j < boxes.length; j++) {
          var a = boxes[i];
          var b = boxes[j];
          var overlapX = (a.width + b.width) / 2 + MIN_GAP_X - Math.abs((a.x + a.width / 2) - (b.x + b.width / 2));
          var overlapY = NODE_HEIGHT + MIN_GAP_Y - Math.abs((a.y + a.height / 2) - (b.y + b.height / 2));

          if (overlapX <= 0 || overlapY <= 0) {
            continue;
          }

          moved = true;
          if (overlapX <= overlapY) {
            var shiftX = Math.ceil(overlapX / 2);
            var leftFirst = a.x <= b.x;
            a.x += leftFirst ? -shiftX : shiftX;
            b.x += leftFirst ? shiftX : -shiftX;
          } else {
            var shiftY = Math.ceil(overlapY / 2);
            var aboveFirst = a.y <= b.y;
            a.y += aboveFirst ? -shiftY : shiftY;
            b.y += aboveFirst ? shiftY : -shiftY;
          }
        }
      }

      if (!moved) {
        return;
      }
    }
  }

  function bounds(boxes, notes) {
    if (!boxes.length) {
      return { minX: 0, minY: 0, maxX: 400, maxY: 200 };
    }
    var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    boxes.forEach(function(box) {
      minX = Math.min(minX, box.x);
      minY = Math.min(minY, box.y);
      maxX = Math.max(maxX, box.x + box.width);
      maxY = Math.max(maxY, box.y + box.height);
    });
    (notes || []).forEach(function(note) {
      minX = Math.min(minX, note.x);
      minY = Math.min(minY, note.y);
      maxX = Math.max(maxX, note.x + 220);
      maxY = Math.max(maxY, note.y + 40);
    });
    return { minX: minX, minY: minY, maxX: maxX, maxY: maxY };
  }

  function edgePath(from, to) {
    var forward = to.x >= from.x;
    var startX = forward ? from.x + from.width : from.x;
    var endX = forward ? to.x : to.x + to.width;
    var startY = from.y + from.height / 2;
    var endY = to.y + to.height / 2;
    var control = Math.max(28, Math.abs(endX - startX) / 2);
    var direction = forward ? 1 : -1;

    return 'M ' + startX + ' ' + startY +
      ' C ' + (startX + control * direction) + ' ' + startY +
      ', ' + (endX - control * direction) + ' ' + endY +
      ', ' + endX + ' ' + endY;
  }

  function truncate(value, limit) {
    var text = String(value == null ? '' : value);
    return text.length > limit ? text.slice(0, limit - 1) + '…' : text;
  }

  function describe(node) {
    var parts = [node.name];
    if (node.type) { parts.push('type: ' + node.type); }
    if (node.description) { parts.push(node.description); }
    Object.keys(node.detail || {}).forEach(function(key) {
      parts.push(key + ': ' + node.detail[key]);
    });
    return parts.join('\n');
  }

  function render(host, graph, options) {
    options = options || {};
    var target = typeof host === 'string' ? document.querySelector(host) : (host && host.jquery ? host[0] : host);
    if (!target) {
      return null;
    }

    while (target.firstChild) {
      target.removeChild(target.firstChild);
    }
    target.classList.add('hop-canvas-host');

    var nodes = graph && graph.nodes ? graph.nodes : [];
    if (!nodes.length) {
      var empty = document.createElement('div');
      empty.className = 'hop-canvas-empty';
      empty.textContent = options.emptyMessage || 'This Apache Hop file declares no transforms or actions.';
      target.appendChild(empty);
      return target;
    }

    var placement = layout(graph);
    var box = bounds(placement.boxes, graph.notes);
    var width = (box.maxX - box.minX) + PADDING * 2;
    var height = (box.maxY - box.minY) + PADDING * 2;

    var svg = element('svg', {
      class: 'hop-canvas',
      viewBox: (box.minX - PADDING) + ' ' + (box.minY - PADDING) + ' ' + width + ' ' + height,
      preserveAspectRatio: 'xMidYMid meet',
      role: 'img',
      'aria-label': 'Apache Hop ' + (graph.kind || 'graph') + ' ' + (graph.name || '')
    });

    var defs = element('defs');
    ['always', 'success', 'failure', 'disabled'].forEach(function(condition) {
      var marker = element('marker', {
        id: 'hop-arrow-' + condition,
        viewBox: '0 0 10 10', refX: '9', refY: '5',
        markerWidth: '7', markerHeight: '7', orient: 'auto-start-reverse'
      });
      marker.appendChild(element('path', { d: 'M 0 0 L 10 5 L 0 10 z', class: 'hop-edge-head hop-edge-' + condition }));
      defs.appendChild(marker);
    });
    svg.appendChild(defs);

    (graph.notes || []).forEach(function(note) {
      var group = element('g', { class: 'hop-note' });
      group.appendChild(element('rect', { x: note.x, y: note.y, width: 220, height: 34, rx: 4 }));
      group.appendChild(element('text', { x: note.x + 10, y: note.y + 21 }, truncate(note.text, 34)));
      svg.appendChild(group);
    });

    var edgeLayer = element('g', { class: 'hop-edges' });
    (graph.edges || []).forEach(function(edge) {
      var from = placement.byName[edge.from];
      var to = placement.byName[edge.to];
      if (!from || !to) {
        return;
      }
      var condition = edge.enabled === false ? 'disabled' : (edge.condition || 'always');
      var path = element('path', {
        d: edgePath(from, to),
        class: 'hop-edge hop-edge-' + condition + (edge.enabled === false ? ' is-disabled' : ''),
        'marker-end': 'url(#hop-arrow-' + condition + ')'
      });
      path.appendChild(element('title', {}, edge.from + ' → ' + edge.to +
        (edge.condition && edge.condition !== 'always' ? ' (on ' + edge.condition + ')' : '') +
        (edge.enabled === false ? ' — disabled' : '')));
      edgeLayer.appendChild(path);
    });
    svg.appendChild(edgeLayer);

    // Per-node run state, when the caller has it. The same canvas then shows
    // what the run is doing rather than only what the job is made of.
    var nodeState = options.nodeState || {};
    var nodeLayer = element('g', { class: 'hop-nodes' });
    placement.boxes.forEach(function(item) {
      var role = NODE_ROLE[String(item.node.type || '').toUpperCase()] || 'step';
      var state = nodeState[item.node.name];
      var running = state && /running|waiting|paused|started/i.test(String(state.status || ''));
      var failed = state && (parseInt(state.errors, 10) > 0 || /stopped|error/i.test(String(state.status || '')));
      var group = element('g', {
        class: 'hop-node hop-node-' + role + (running ? ' is-running' : '') + (failed ? ' is-failed' : ''),
        transform: 'translate(' + item.x + ',' + item.y + ')',
        tabindex: '0'
      });
      group.appendChild(element('rect', { width: item.width, height: item.height, rx: 6 }));
      group.appendChild(element('text', { class: 'hop-node-name', x: 12, y: 20 }, truncate(item.node.name, Math.floor(item.width / CHARACTER_WIDTH) - 2)));

      if (state) {
        group.appendChild(element(
          'text',
          { class: 'hop-node-metrics', x: 12, y: 35 },
          truncate('→ ' + (parseInt(state.written, 10) || 0) + ' rows · ' + (state.status || ''), Math.floor(item.width / 6.2) - 2)
        ));
      } else {
        group.appendChild(element('text', { class: 'hop-node-type', x: 12, y: 35 }, truncate(item.node.type || 'step', Math.floor(item.width / 6.2) - 2)));
      }

      group.appendChild(element('title', {}, describe(item.node) + (state
        ? '\n' + state.status + ' — read ' + state.read + ', written ' + state.written + ', errors ' + state.errors
        : '')));

      if (typeof options.onSelect === 'function') {
        group.addEventListener('click', function() { options.onSelect(item.node); });
      }
      nodeLayer.appendChild(group);
    });
    svg.appendChild(nodeLayer);

    // A large pipeline does not fit legibly in a modal, so the view can be
    // zoomed. The viewBox is the only thing that changes, which keeps text
    // crisp at every step.
    var view = { x: box.minX - PADDING, y: box.minY - PADDING, width: width, height: height };
    var toolbar = document.createElement('div');
    toolbar.className = 'hop-canvas-toolbar';
    [
      ['out', 'Zoom out', '\u2212'],
      ['in', 'Zoom in', '+'],
      ['fit', 'Fit', '\u2922']
    ].forEach(function(item) {
      var control = document.createElement('button');
      control.type = 'button';
      control.className = 'btn btn-default btn-xs';
      control.title = item[1];
      control.textContent = item[2];
      control.addEventListener('click', function() {
        if (item[0] === 'fit') {
          view = { x: box.minX - PADDING, y: box.minY - PADDING, width: width, height: height };
        } else {
          var factor = item[0] === 'in' ? 1 / 1.25 : 1.25;
          var centreX = view.x + view.width / 2;
          var centreY = view.y + view.height / 2;
          view = {
            width: view.width * factor,
            height: view.height * factor,
            x: centreX - (view.width * factor) / 2,
            y: centreY - (view.height * factor) / 2
          };
        }
        svg.setAttribute('viewBox', view.x + ' ' + view.y + ' ' + view.width + ' ' + view.height);
      });
      toolbar.appendChild(control);
    });

    target.appendChild(toolbar);
    target.appendChild(svg);
    return target;
  }

  return { render: render, layout: layout, bounds: bounds };
}));
