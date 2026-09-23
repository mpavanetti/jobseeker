(function(root, factory) {
  var api = factory();

  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }

  if (root) {
    root.JobSeekerConsole = api;
  }
}(typeof window !== 'undefined' ? window : null, function() {
  'use strict';

  var SECTION_META = {
    jobseeker: { title: 'JobSeeker events', icon: 'fa-bolt' },
    jenkins: { title: 'Jenkins runtime', icon: 'fa-cogs' },
    source: { title: 'Source checkout', icon: 'fa-code-fork' },
    'docker-build': { title: 'Docker image build', icon: 'fa-cube' },
    'docker-runtime': { title: 'Docker container setup', icon: 'fa-archive' },
    'docker-execution': { title: 'Docker execution', icon: 'fa-play-circle' },
    'hop-execution': { title: 'Apache Hop execution', icon: 'fa-sitemap' },
    'python-environment': { title: 'Python environment', icon: 'fa-wrench' },
    'python-tests': { title: 'Python tests', icon: 'fa-check-square-o' },
    python: { title: 'Python execution', icon: 'fa-code' },
    shell: { title: 'Shell execution', icon: 'fa-terminal' },
    email: { title: 'Email notification', icon: 'fa-envelope-o' },
    cleanup: { title: 'Cleanup', icon: 'fa-trash-o' },
    result: { title: 'Build result', icon: 'fa-flag-checkered' },
    // Apache Hop logs are grouped by the transform or action that wrote each
    // line, which is how Hop's own UI presents a run.
    'hop-run': { title: 'Apache Hop run', icon: 'fa-sitemap' },
    'hop-step': { title: 'Transform or action', icon: 'fa-cog' },
    'hop-log': { title: 'Run log', icon: 'fa-file-text-o' },
    // A job that declares tasks prints one marker per task, so each task's
    // output folds into its own section the way Hop groups by transform.
    task: { title: 'Task', icon: 'fa-check-square-o' },
    dag: { title: 'Task DAG', icon: 'fa-sitemap' }
  };

  // [JobSeeker Task] extract | SUCCESS | attempt 1/3 | 1.204s
  var TASK_LINE = /^\[JobSeeker Task\]\s+([A-Za-z][A-Za-z0-9_-]{0,63})\s*\|\s*([A-Z_]+)\b/;

  // [JobSeeker DAG] start | 5 task(s) | run nightly-DEV-42 | max parallel 4
  var DAG_LINE = /^\[JobSeeker DAG\]\s+\S+/;

  // The runtime tags every line a task writes with that task's id, because
  // tasks run concurrently and their output interleaves in one console. The id
  // is only honoured once a marker has introduced that task, so an ordinary
  // bracketed log prefix is never mistaken for one.
  var TASK_OWNED_LINE = /^\[([A-Za-z][A-Za-z0-9_-]{0,63})\]\s/;

  // 2026/09/04 17:38:00 - Copy files - ERROR: File/folder [...] does not exist!
  var HOP_LINE = /^(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}:\d{2}(?:\.\d+)?)\s+-\s+([\s\S]+?)\s+-\s?([\s\S]*)$/;
  var HOP_ACTION_START = /^Starting action \[([\s\S]+)\]\s*$/;
  var HOP_ACTION_END = /^Finished action \[([\s\S]+?)\]\s*\(result=\[([^\]]*)\]\)/;
  // A transform logs once per copy, as "<name>.<copy>", while the canvas
  // addresses the transform itself. All copies therefore share one owner.
  var HOP_COPY_SUFFIX = /\.\d+$/;

  function normalizedLine(line) {
    return String(line == null ? '' : line).replace(/\x1b\[[0-?]*[ -\/]*[@-~]/g, '');
  }

  function hopOwner(origin, message) {
    var action = HOP_ACTION_START.exec(String(message || '').trim()) ||
      HOP_ACTION_END.exec(String(message || '').trim());
    if (action) {
      return String(action[1] || '').trim();
    }
    return String(origin || '').trim().replace(HOP_COPY_SUFFIX, '');
  }

  function isBuildResult(line) {
    return /^Finished:\s+(?:SUCCESS|FAILURE|ABORTED|UNSTABLE|NOT_BUILT)\s*$/i.test(line);
  }

  function isBuildResultDetail(line) {
    return /^(?:Build was aborted|Aborted by\b|Build step .* marked build as failure)/i.test(line);
  }

  function isEmailNotification(line) {
    return /^\[JobSeeker Email\]|^Email was triggered for:|^Sending email for trigger:|^Sending email to:|^Successfully sent email to:|^Not sent to the following|^An attempt to send an e-mail|^Email sending failed/i.test(line);
  }

  function isCleanup(line) {
    return /jobseeker_python_(?:docker_)?cleanup|rm -rf .*jobseeker-python-docker-context|docker image rm\b|docker run .*jobseeker-email.*jobseeker-email-metrics\.properties|rm -f .*jobseeker-email-metrics\.properties\.tmp|docker run .*jobseeker-assets.*data-assets\/manifest\.json/i.test(line);
  }

  function isPythonCommand(line) {
    return /^(?:\+\s*)?"?(?:[^"\s]+\/)?python(?:3(?:\.\d+)?)?"?\s+-u(?:\s|$)/i.test(line);
  }

  function isPytestCommand(line) {
    return /^(?:\+\s*)?(?:"?(?:[^"\s]+\/)?python(?:3(?:\.\d+)?)?"?\s+-m\s+pytest|"?(?:[^"\s]+\/)?pytest"?)(?:\s|$)/i.test(line);
  }

  function explicitSectionKind(line) {
    if (/^\[JobSeeker\]\s+Git source checkout\s*$/i.test(line)) {
      return 'source';
    }

    if (/^\[JobSeeker\]\s+Docker image build\s*$/i.test(line)) {
      return 'docker-build';
    }

    if (/^\[JobSeeker\]\s+Docker (?:container|runtime) setup\s*$/i.test(line)) {
      return 'docker-runtime';
    }

    if (/^\[JobSeeker\]\s+Docker container (?:execution|run)\s*$/i.test(line)) {
      return 'docker-execution';
    }

    if (/^\[JobSeeker\]\s+(?:Apache Hop (?:execution\b|(?:container|server) run\b)|Hop Server execution\b)/i.test(line)) {
      return 'hop-execution';
    }

    if (/^\[JobSeeker\]\s+Python environment\s*$/i.test(line)) {
      return 'python-environment';
    }

    if (/^\[JobSeeker\]\s+Python tests\s*$/i.test(line)) {
      return 'python-tests';
    }

    if (/^\[JobSeeker\]\s+Python execution\s*$/i.test(line)) {
      return 'python';
    }

    if (/^\[JobSeeker\]\s+Cleanup\s*$/i.test(line)) {
      return 'cleanup';
    }

    if (/^\[JobSeeker\]\s+(?:Shell|Bash|Talend) execution\s*$/i.test(line)) {
      return 'shell';
    }

    return '';
  }

  function isDockerRuntimeStart(line) {
    return /^\+\s+(?:tar\s+.*jobseeker-python-docker-context|docker\s+run\b)/i.test(line) ||
      /^docker\s+run\b/i.test(line);
  }

  function isDockerBuildStart(line) {
    return /Preparing Python Docker build context|JOBSEEKER_DOCKER_(?:IMAGE|RUN_IMAGE|BUILT_IMAGE|TAG|BUILD_CONTEXT)|JOBSEEKER_DOCKERFILE|DOCKER_BUILDKIT=.*docker build/i.test(line) ||
      /^#\d+\s+(?:\[|DONE\b|CACHED\b|ERROR\b|exporting\b|writing\b|naming\b|transferring\b)/i.test(line);
  }

  function isPythonEnvironment(line) {
    return /(?:creating|recreating|initializing).*virtual environment|installing (?:python )?dependenc|requirements\.txt|poetry install|pip install/i.test(line);
  }

  function hasError(line) {
    // Hop output can contain business data such as `status = error`. Once a
    // line has Hop's timestamp/origin envelope, only Hop's structured
    // ERROR/FATAL message prefix is a runtime error. The broad generic matcher
    // otherwise paints a successful transform red merely because it printed a
    // row describing failed records from another system.
    var hop = HOP_LINE.exec(line);
    if (hop) {
      return /^(?:ERROR|FATAL)(?:\s*:|\b)/i.test(String(hop[3] || '').trim());
    }

    // Treat failure words as log syntax, not as arbitrary data. Successful
    // jobs routinely print values such as `status=error`, `0 failed`, and
    // `failure notifications disabled`; none of those is an error record.
    // Exception only counts as part of a class name, so licence names such as
    // "Universal FOSS Exception" also remain neutral.
    return /^\s*(?:\[[^\]]+\]\s*)?(?:ERROR|FATAL|FAILURE|FAILED)(?:\s*:|\b)/.test(line) ||
      /\b(?:ERROR|FATAL|FAILURE|FAILED)\s*:/i.test(line) ||
      /\[(?:ERROR|FATAL)\]/i.test(line) ||
      /^Email sending failed\b/i.test(line) ||
      /^\s*\d{4}[-/]\d{2}[-/]\d{2}[^\n]*\b(?:ERROR|FATAL)\b/.test(line) ||
      /(?:^|\s)(?:npm\s+ERR!|Traceback\b|fatal:|command not found|No such file or directory)/i.test(line) ||
      /\b(?:command failed|marked build as failure|returned non-zero exit status|exited with (?:status|code) [1-9]\d*|script returned exit code [1-9]\d*)/i.test(line) ||
      /\b[1-9]\d*\s+failed\b/i.test(line) ||
      /\b[A-Za-z_][A-Za-z0-9_.$]+Exception\b/.test(line) ||
      /\b[A-Za-z_][A-Za-z0-9_.$]*(?:Error|Exception):/.test(line) ||
      /^Finished:\s+(?:FAILURE|UNSTABLE)\s*$/i.test(line);
  }

  function classifyLine(value, currentKind) {
    var line = normalizedLine(value);
    var explicitKind = explicitSectionKind(line);

    if (explicitKind) {
      return explicitKind;
    }

    if (TASK_LINE.test(line)) {
      return 'task';
    }

    if (DAG_LINE.test(line)) {
      return 'dag';
    }

    // Anything a task printed - stdout, a traceback - belongs to that task,
    // until the next task marker or an explicit JobSeeker section heading.
    if (currentKind === 'task') {
      return 'task';
    }

    if (/^\[JobSeeker\]/.test(line)) {
      if (currentKind === 'hop-execution') {
        return 'hop-execution';
      }
      return 'jobseeker';
    }

    if (isBuildResult(line) || isBuildResultDetail(line)) {
      return 'result';
    }

    if (isEmailNotification(line)) {
      return 'email';
    }

    if (isCleanup(line)) {
      return 'cleanup';
    }

    if (isPythonCommand(line)) {
      return 'python';
    }

    if (isPytestCommand(line)) {
      return 'python-tests';
    }

    if (currentKind === 'email') {
      return 'email';
    }

    if (currentKind === 'source' || currentKind === 'docker-execution' || currentKind === 'hop-execution' || currentKind === 'python-tests' || currentKind === 'python' || currentKind === 'shell' || currentKind === 'cleanup' || currentKind === 'result') {
      return currentKind;
    }

    if (isDockerRuntimeStart(line)) {
      return 'docker-runtime';
    }

    if (currentKind === 'docker-runtime') {
      return 'docker-runtime';
    }

    if (isDockerBuildStart(line)) {
      return 'docker-build';
    }

    if (currentKind === 'docker-build') {
      return 'docker-build';
    }

    if (isPythonEnvironment(line)) {
      return 'python-environment';
    }

    if (currentKind === 'python-environment') {
      return 'python-environment';
    }

    return 'jenkins';
  }

  function parse(text) {
    var raw = String(text == null ? '' : text);
    var normalized = raw.replace(/\r\n?/g, '\n');
    var lines = normalized.split('\n');
    var sections = [];
    var occurrences = {};
    var current = null;

    if (lines.length > 1 && lines[lines.length - 1] === '') {
      lines.pop();
    }

    var knownTasks = {};

    lines.forEach(function(line) {
      var normalized = normalizedLine(line);
      // A hop-step section is still inside the Hop execution as far as
      // classification is concerned; it is only grouped more finely.
      var carriedKind = current ? (current.kind === 'hop-step' ? 'hop-execution' : current.kind) : '';
      var kind = classifyLine(line, carriedKind);

      // Apache Hop stamps every line with the transform or action that wrote
      // it. The Hop page already groups by that; doing it here too means a
      // build console can be pointed at one transform - which is what makes
      // clicking a node on the canvas able to answer "what did this print?".
      var hopSectionOwner = '';
      if (kind === 'hop-execution') {
        var hopMatch = HOP_LINE.exec(normalized);
        var hopOrigin = hopMatch ? String(hopMatch[2]).trim() : '';
        if (hopOrigin !== '') {
          kind = 'hop-step';
          hopSectionOwner = hopOwner(hopOrigin, hopMatch[3]);
        } else if (current && current.kind === 'hop-step' && ! /^\[JobSeeker\]/.test(normalized)) {
          // A continuation line - a stack trace, a wrapped message - belongs to
          // whichever transform is currently speaking.
          kind = 'hop-step';
          hopSectionOwner = current.owner || '';
        }
      }

      var taskMatch = TASK_LINE.exec(normalized);
      var ownerMatch = taskMatch ? null : TASK_OWNED_LINE.exec(normalized);
      var attributed = false;
      var taskId = '';

      if (taskMatch) {
        knownTasks[taskMatch[1]] = true;
        kind = 'task';
        taskId = taskMatch[1];
        attributed = true;
      } else if (ownerMatch && knownTasks[ownerMatch[1]]) {
        kind = 'task';
        taskId = ownerMatch[1];
        attributed = true;
      } else if (kind === 'task' && current && current.kind === 'task') {
        taskId = current.taskId;
      }

      // Two tasks in a row are the same kind but must not share a section, and
      // concurrent tasks interleave, so the owning task breaks the group as
      // well as the kind does. The same holds for two transforms in a row.
      var startsSection = ! current || current.kind !== kind ||
        (kind === 'task' && attributed && current.taskId !== taskId) ||
        (kind === 'hop-step' && hopSectionOwner !== '' && current.owner !== hopSectionOwner);

      if (startsSection) {
        occurrences[kind] = (occurrences[kind] || 0) + 1;
        current = {
          id: kind + '-' + occurrences[kind],
          kind: kind,
          title: kind === 'task' && taskId
            ? 'Task ' + taskId
            : (kind === 'hop-step' && hopSectionOwner ? hopSectionOwner : SECTION_META[kind].title),
          icon: SECTION_META[kind].icon,
          lines: [],
          lineCount: 0,
          hasError: false
        };
        if (kind === 'task') {
          current.taskId = taskId;
          current.owner = taskId;
        }
        if (kind === 'hop-step' && hopSectionOwner) {
          current.owner = hopSectionOwner;
        }
        sections.push(current);
      }

      current.lines.push(line);
      current.lineCount += 1;
      current.hasError = current.hasError || hasError(normalizedLine(line));
    });

    // Concurrent tasks and Hop transforms interleave line by line. Regroup each
    // contiguous run by owner so a graph click opens the complete output for
    // that node rather than whichever one-line fragment happened to come first.
    // Order inside an owner is preserved; unrelated phases stay in place.
    var regrouped = [];
    var cursor = 0;
    while (cursor < sections.length) {
      var ownedKind = sections[cursor].kind;
      if (ownedKind !== 'task' && ownedKind !== 'hop-step') {
        regrouped.push(sections[cursor]);
        cursor += 1;
        continue;
      }
      var end = cursor;
      while (end < sections.length && sections[end].kind === ownedKind) {
        end += 1;
      }
      var byOwner = {};
      var seenOrder = [];
      sections.slice(cursor, end).forEach(function(section) {
        var key = section.owner || '';
        if (! byOwner[key]) {
          byOwner[key] = section;
          seenOrder.push(key);
          return;
        }
        var target = byOwner[key];
        target.lines = target.lines.concat(section.lines);
        target.lineCount += section.lineCount;
        target.hasError = target.hasError || section.hasError;
      });
      seenOrder.forEach(function(key) { regrouped.push(byOwner[key]); });
      cursor = end;
    }
    sections = regrouped;

    // Ids stay unique after regrouping so the UI can address each section.
    var idCounts = {};
    sections.forEach(function(section) {
      idCounts[section.kind] = (idCounts[section.kind] || 0) + 1;
      section.id = section.kind + '-' + idCounts[section.kind];
    });

    // A task can still own two separate stretches when an unrelated section
    // falls between them. Number those rather than repeating a heading.
    var taskSectionCounts = {};
    sections.forEach(function(section) {
      if (section.kind === 'task' && section.taskId) {
        taskSectionCounts[section.taskId] = (taskSectionCounts[section.taskId] || 0) + 1;
      }
    });
    var taskSectionSeen = {};
    sections.forEach(function(section) {
      if (section.kind === 'task' && section.taskId && taskSectionCounts[section.taskId] > 1) {
        taskSectionSeen[section.taskId] = (taskSectionSeen[section.taskId] || 0) + 1;
        section.part = taskSectionSeen[section.taskId];
        section.parts = taskSectionCounts[section.taskId];
        section.title = section.title + ' (' + section.part + '/' + section.parts + ')';
      }
    });

    sections.forEach(function(section) {
      section.text = section.lines.join('\n');
      delete section.lines;
    });

    return { raw: raw, sections: sections };
  }

  /**
   * Group an Apache Hop log the way Hop itself does: by the transform or action
   * that produced each line. A Hop run has no Jenkins markers, so the generic
   * parser would collapse the whole thing into one block - which is exactly the
   * wall of text a person opens the log to avoid.
   */
  function parseHop(text, options) {
    options = options || {};
    var raw = String(text == null ? '' : text);
    var normalized = raw.replace(/\r\n?/g, '\n');
    var lines = normalized.split('\n');
    var runName = String(options.name || '').trim();
    var order = [];
    var byOrigin = {};
    var lastOrigin = '';

    if (lines.length > 1 && lines[lines.length - 1] === '') {
      lines.pop();
    }

    lines.forEach(function(line) {
      var clean = normalizedLine(line);
      var match = HOP_LINE.exec(clean);
      var origin = match ? match[2].trim() : lastOrigin;
      var body = match ? (match[1] + '  ' + match[3]) : clean;

      lastOrigin = origin;
      if (! byOrigin[origin]) {
        var isRun = runName !== '' ? origin === runName : order.length === 0 && origin !== '';
        var kind = origin === '' ? 'hop-log' : (isRun ? 'hop-run' : 'hop-step');
        byOrigin[origin] = {
          id: 'hop-' + order.length,
          kind: kind,
          // What this section is about, so a caller can ask for "the console
          // for the transform I just clicked on the canvas".
          owner: origin === '' ? '' : origin,
          title: origin === '' ? SECTION_META['hop-log'].title : origin,
          icon: SECTION_META[kind].icon,
          lines: [],
          lineCount: 0,
          hasError: false
        };
        order.push(byOrigin[origin]);
      }

      var section = byOrigin[origin];
      section.lines.push(body);
      section.lineCount += 1;
      section.hasError = section.hasError || hasError(clean);
    });

    order.forEach(function(section) {
      section.text = section.lines.join('\n');
      delete section.lines;
    });

    return { raw: raw, sections: order };
  }

  // Hop closes each transform with a metrics line and brackets each workflow
  // action with a start and a finish line:
  //   read customers.0 - Finished processing (I=0, O=0, R=10, W=10, U=0, E=0)
  //   main - Starting action [load]
  //   main - Finished action [load] (result=[true])
  var HOP_METRICS = /\(\s*I\s*=\s*(\d+)\s*,\s*O\s*=\s*(\d+)\s*,\s*R\s*=\s*(\d+)\s*,\s*W\s*=\s*(\d+)\s*,\s*U\s*=\s*(\d+)\s*,\s*E\s*=\s*(\d+)\s*\)/;
  var HOP_TIMESTAMP = /^(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}:\d{2}(?:\.\d+)?)\s+-\s+([\s\S]*)$/;

  /**
   * Split a Hop log line into its origin and message.
   *
   * The separator is " - " rather than a bare dash so that a transform whose
   * own name contains one - "read-customers" - still resolves to the right
   * origin.
   */
  function hopLineParts(line) {
    var match = HOP_TIMESTAMP.exec(normalizedLine(line));
    if (! match) {
      return null;
    }
    var rest = match[2];
    var split = rest.indexOf(' - ');
    if (split < 0) {
      return null;
    }
    return { origin: rest.slice(0, split).trim(), message: rest.slice(split + 3).trim() };
  }

  /**
   * Derive per-node run state from an Apache Hop build console.
   *
   * A job running on the "container" engine starts an ephemeral Hop container
   * and never registers with the Hop Server, so the server has no status to
   * report for it. The build console is the only place that run's per-transform
   * and per-action state exists, and the execution screen is already streaming
   * it. Parsing it here produces the same shape the Hop Server path returns -
   * { status, read, written, errors } keyed by node name - so the canvas
   * overlay renders identically whichever engine produced the run.
   */
  function hopNodeState(text, options) {
    options = options || {};
    var workflow = options.kind === 'workflow';
    var normalized = String(text == null ? '' : text).replace(/\r\n?/g, '\n');
    var nodes = {};
    var count = 0;
    var loggedErrors = {};
    var metricErrors = {};

    function node(name) {
      if (! nodes[name]) {
        nodes[name] = { status: '', read: 0, written: 0, errors: 0 };
        count += 1;
      }
      return nodes[name];
    }

    normalized.split('\n').forEach(function(line) {
      var parts = hopLineParts(line);
      if (! parts || parts.origin === '') {
        return;
      }

      if (workflow) {
        var started = HOP_ACTION_START.exec(parts.message);
        if (started) {
          node(started[1].trim()).status = 'Running';
          return;
        }
        var ended = HOP_ACTION_END.exec(parts.message);
        if (ended) {
          var ok = String(ended[2]).trim().toLowerCase() === 'true';
          var action = node(ended[1].trim());
          action.status = ok ? 'Finished' : 'Failed';
          action.errors = ok ? action.errors : action.errors + 1;
        }
        return;
      }

      // Hop prefixes transform messages with a copy number. The first such
      // line can arrive long before the closing metrics line, so mark that
      // transform active while its rows are still moving. Pipeline headers
      // have no copy suffix and do not represent a node on the canvas.
      if (! HOP_COPY_SUFFIX.test(parts.origin)) {
        return;
      }
      var transformName = parts.origin.replace(HOP_COPY_SUFFIX, '');
      var transform = node(transformName);
      if (/^(?:ERROR|FATAL)(?:\s*:|\b)/i.test(parts.message)) {
        transform.status = 'Failed';
        loggedErrors[transformName] = (loggedErrors[transformName] || 0) + 1;
        transform.errors = Math.max(loggedErrors[transformName], metricErrors[transformName] || 0);
      } else if (transform.status !== 'Failed') {
        transform.status = 'Running';
      }
      var metrics = HOP_METRICS.exec(parts.message);
      if (! metrics) {
        return;
      }
      // Copies of one transform each report their own slice of the work.
      transform.read += parseInt(metrics[3], 10) || 0;
      transform.written += parseInt(metrics[4], 10) || 0;
      metricErrors[transformName] = (metricErrors[transformName] || 0) + (parseInt(metrics[6], 10) || 0);
      transform.errors = Math.max(loggedErrors[transformName] || 0, metricErrors[transformName]);
      transform.status = transform.errors > 0 ? 'Failed' : (/^Finished processing/.test(parts.message) ? 'Finished' : 'Running');
    });

    return { nodes: nodes, nodeCount: count };
  }

  function parserFor(options) {
    return options && options.parser === 'hop'
      ? function(text) { return parseHop(text, options); }
      : parse;
  }

  function stateFor(host) {
    if (! host.__jobSeekerConsoleState) {
      host.__jobSeekerConsoleState = {
        text: '',
        openById: {},
        touchedById: {},
        knownIds: {},
        rawVisible: false,
        lastSectionId: '',
        focusedOwner: '',
        focusUntil: 0
      };
    }

    return host.__jobSeekerConsoleState;
  }

  function element(name, className, text) {
    var node = document.createElement(name);
    if (className) {
      node.className = className;
    }
    if (text != null) {
      node.textContent = text;
    }
    return node;
  }

  function button(action, label, icon) {
    var node = element('button', 'btn btn-default btn-xs job-console-action');
    node.type = 'button';
    node.setAttribute('data-console-action', action);
    if (icon) {
      node.appendChild(element('i', 'fa ' + icon));
      node.appendChild(document.createTextNode(' '));
    }
    node.appendChild(document.createTextNode(label));
    return node;
  }

  function defaultOpen(section, index, total, options) {
    if (section.kind === 'hop-run' || section.kind === 'hop-step' || section.kind === 'hop-log') {
      // A pipeline can have dozens of transforms. Open what failed, the run
      // itself, and a log with nothing to choose between.
      return section.hasError || section.kind === 'hop-run' || total === 1;
    }
    return section.hasError || section.kind === 'docker-execution' || section.kind === 'hop-execution' || section.kind === 'python-tests' || section.kind === 'python' || section.kind === 'shell' || section.kind === 'email' || section.kind === 'cleanup' ||
      section.kind === 'result' || total === 1 || (!! options.live && index === total - 1);
  }

  function copyText(text, sourceButton) {
    function copied() {
      var original = sourceButton.innerHTML;
      sourceButton.textContent = 'Copied';
      setTimeout(function() { sourceButton.innerHTML = original; }, 1200);
    }

    if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(copied);
      return;
    }

    var area = element('textarea');
    area.value = text;
    area.setAttribute('readonly', 'readonly');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    try {
      document.execCommand('copy');
      copied();
    } finally {
      document.body.removeChild(area);
    }
  }

  function render(host, parsed, options) {
    options = options || {};
    var state = stateFor(host);
    state.options = options;
    var previousLastId = state.lastSectionId;
    var fragment = document.createDocumentFragment();
    var toolbar = element('div', 'job-console-toolbar');
    var sectionList = element('div', 'job-console-sections');
    var raw = element('pre', 'job-console-raw', parsed.raw);

    host.classList.add('job-console-host');
    toolbar.appendChild(button('expand', 'Expand all', 'fa-angle-double-down'));
    toolbar.appendChild(button('collapse', 'Collapse all', 'fa-angle-double-up'));
    toolbar.appendChild(button('raw', state.rawVisible ? 'Grouped view' : 'Raw log', 'fa-file-text-o'));
    toolbar.appendChild(button('copy', 'Copy', 'fa-copy'));
    toolbar.appendChild(element('span', 'job-console-total', parsed.sections.length + ' section' + (parsed.sections.length === 1 ? '' : 's')));

    parsed.sections.forEach(function(section, index) {
      var isNew = ! state.knownIds[section.id];
      var details = element('details', 'job-console-section job-console-section-' + section.kind + (section.hasError ? ' has-error' : ''));
      var summary = element('summary', 'job-console-summary');
      var title = element('span', 'job-console-title');
      var meta = element('span', 'job-console-meta');
      var lineBadge = element('span', 'job-console-badge', section.lineCount + ' line' + (section.lineCount === 1 ? '' : 's'));
      var content = element('pre', 'job-console-content', section.text);

      details.setAttribute('data-console-section-id', section.id);
      details.setAttribute('data-console-kind', section.kind);
      if (section.owner) {
        details.setAttribute('data-console-owner', section.owner);
      }

      if (isNew) {
        state.openById[section.id] = defaultOpen(section, index, parsed.sections.length, options);
        state.knownIds[section.id] = true;

        if (options.live && previousLastId && previousLastId !== section.id && ! state.touchedById[previousLastId]) {
          state.openById[previousLastId] = false;
        }
      }

      if (section.owner && state.focusedOwner && Date.now() < state.focusUntil &&
          String(section.owner).replace(HOP_COPY_SUFFIX, '').toLowerCase() === state.focusedOwner) {
        details.classList.add('job-console-section-focus');
        state.openById[section.id] = true;
      }

      details.open = !! state.openById[section.id];

      title.appendChild(element('i', 'fa ' + section.icon));
      title.appendChild(document.createTextNode(' ' + section.title));
      meta.appendChild(lineBadge);

      if (section.hasError) {
        meta.appendChild(element('span', 'job-console-badge job-console-badge-error', 'error'));
      }

      if (options.live && index === parsed.sections.length - 1) {
        meta.appendChild(element('span', 'job-console-badge job-console-badge-live', 'live'));
      }

      summary.appendChild(title);
      summary.appendChild(meta);
      details.appendChild(summary);
      details.appendChild(content);
      details.addEventListener('toggle', function() {
        state.openById[section.id] = details.open;
        state.touchedById[section.id] = true;
      });
      sectionList.appendChild(details);
    });

    if (parsed.sections.length === 0) {
      sectionList.appendChild(element('div', 'job-console-empty', options.emptyMessage || 'Console output is empty.'));
    }

    state.lastSectionId = parsed.sections.length ? parsed.sections[parsed.sections.length - 1].id : '';
    sectionList.hidden = state.rawVisible;
    raw.hidden = ! state.rawVisible;
    fragment.appendChild(toolbar);
    fragment.appendChild(sectionList);
    fragment.appendChild(raw);

    while (host.firstChild) {
      host.removeChild(host.firstChild);
    }
    host.appendChild(fragment);

    toolbar.addEventListener('click', function(event) {
      var actionButton = event.target.closest ? event.target.closest('[data-console-action]') : null;
      var action = actionButton ? actionButton.getAttribute('data-console-action') : '';

      if (! action) {
        return;
      }

      if (action === 'copy') {
        copyText(state.text, actionButton);
        return;
      }

      if (action === 'raw') {
        state.rawVisible = ! state.rawVisible;
        render(host, parserFor(options)(state.text), options);
        return;
      }

      Array.prototype.forEach.call(host.querySelectorAll('.job-console-section'), function(details) {
        var id = details.getAttribute('data-console-section-id');
        var shouldOpen = action === 'expand';
        state.openById[id] = shouldOpen;
        state.touchedById[id] = true;
        details.open = shouldOpen;
      });
    });
  }

  /**
   * Open, scroll to and flash the console section belonging to one task or Hop
   * transform.
   *
   * Clicking a node on a graph is a question - "what did *this* one print?" -
   * and the answer is already on screen, just some distance down a log of
   * everything. This takes the reader there instead of making them hunt.
   *
   * Returns the section element, or null when the run produced no output for
   * that owner, so a caller can say so rather than appearing to do nothing.
   */
  function focusSection(target, owner, options) {
    options = options || {};
    var host = resolveHost(target);
    owner = String(owner == null ? '' : owner).trim();
    if (! host || owner === '') {
      return null;
    }

    var state = stateFor(host);
    // A graph click always means "show me the grouped section". If the reader
    // had switched to the raw log, restore the grouped view before locating it.
    if (state.rawVisible) {
      state.rawVisible = false;
      render(host, parserFor(state.options)(state.text), state.options);
    }

    var sections = host.querySelectorAll('[data-console-owner]');
    var match = null;
    var ownerKey = owner.replace(HOP_COPY_SUFFIX, '').toLowerCase();
    Array.prototype.forEach.call(sections, function(details) {
      var candidate = String(details.getAttribute('data-console-owner') || '').trim();
      if (! match && (candidate === owner || candidate.replace(HOP_COPY_SUFFIX, '').toLowerCase() === ownerKey)) {
        match = details;
      }
    });
    if (! match) {
      return null;
    }

    // Opening it counts as the reader's own choice, so live polling must not
    // fold it shut again on the next tick.
    var id = match.getAttribute('data-console-section-id');
    if (id) {
      state.openById[id] = true;
      state.touchedById[id] = true;
    }
    state.focusedOwner = ownerKey;
    state.focusUntil = Date.now() + 3000;
    match.open = true;

    if (options.scroll !== false && typeof match.scrollIntoView === 'function') {
      match.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    var summary = match.querySelector('.job-console-summary');
    if (options.focus !== false && summary && typeof summary.focus === 'function') {
      try { summary.focus({preventScroll: true}); } catch (error) { summary.focus(); }
    }

    if (options.highlight !== false) {
      match.classList.remove('job-console-section-focus');
      // Reading offsetWidth restarts the animation when the same section is
      // clicked twice; without it the class is added back in the same frame
      // and nothing appears to happen.
      void match.offsetWidth;
      match.classList.add('job-console-section-focus');
      if (typeof window !== 'undefined' && window.setTimeout) {
        window.setTimeout(function() {
          match.classList.remove('job-console-section-focus');
          if (state.focusedOwner === ownerKey) {
            state.focusedOwner = '';
            state.focusUntil = 0;
          }
        }, 3000);
      }
    }

    return match;
  }

  function resolveHost(target) {
    if (typeof target === 'string') {
      return document.querySelector(target);
    }
    return target && target.jquery ? target[0] : target;
  }

  function setText(target, text, options) {
    var host = resolveHost(target);
    if (! host) {
      return null;
    }

    options = options || {};
    var state = stateFor(host);
    state.text = String(text == null ? '' : text);
    render(host, parserFor(options)(state.text), options);
    return host;
  }

  function appendText(target, text, options) {
    var host = resolveHost(target);
    if (! host || ! text) {
      return host || null;
    }

    options = options || {};
    var state = stateFor(host);
    state.text += String(text);
    render(host, parserFor(options)(state.text), options);
    return host;
  }

  function getText(target) {
    var host = resolveHost(target);
    return host ? stateFor(host).text : '';
  }

  return {
    appendText: appendText,
    classifyLine: classifyLine,
    focusSection: focusSection,
    getText: getText,
    hopNodeState: hopNodeState,
    parse: parse,
    parseHop: parseHop,
    setText: setText
  };
}));
