'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.join(__dirname, '..');
const viewer = require(path.join(root, 'assets', 'js', 'tmf-error-viewer.js'));
const view = fs.readFileSync(path.join(root, 'application', 'views', 'tmf.php'), 'utf8');
const model = fs.readFileSync(path.join(root, 'application', 'models', 'Tmf_model.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets', 'dist', 'css', 'tmf-error-viewer.css'), 'utf8');

const traceback = [
  'Traceback (most recent call last):',
  '  File "/workspace/jobs/customer_sync/main.py", line 18, in <module>',
  '    run_sync(limit=10)',
  '  File "/workspace/jobs/customer_sync/sync.py", line 74, in run_sync',
  '    raise ValueError("Customer id is missing")',
  'ValueError: Customer id is missing'
].join('\n');

const parsed = viewer.analyze({
  type: 'Python Exception',
  origin: 'ValueError',
  message: traceback
});

assert.strictEqual(parsed.isPython, true, 'Python tracebacks must be detected.');
assert.strictEqual(parsed.frames.length, 2, 'Every Python stack frame must be indexed.');
assert.deepStrictEqual(
  parsed.frames.map((frame) => [frame.shortPath, frame.line, frame.functionName]),
  [['main.py', 18, '<module>'], ['sync.py', 74, 'run_sync']],
  'Frame navigation must retain file, line, and function details.'
);
assert.strictEqual(parsed.frames[1].crashSite, true, 'The innermost Python frame must be identified as the crash site.');
assert.strictEqual(parsed.exceptionType, 'ValueError', 'The exception type must be lifted out of the traceback.');
assert.strictEqual(parsed.exceptionMessage, 'Customer id is missing', 'The exception summary must retain its message.');
assert.strictEqual(viewer.lineKind(parsed, parsed.lines[0], 0), 'header');
assert.strictEqual(viewer.lineKind(parsed, parsed.lines[1], 1), 'frame');
assert.strictEqual(viewer.lineKind(parsed, parsed.lines[2], 2), 'source');
assert.strictEqual(viewer.lineKind(parsed, parsed.lines[5], 5), 'exception');

const syntaxError = viewer.analyze({
  type: 'Python Exception',
  origin: 'SyntaxError',
  message: [
    'Traceback (most recent call last):',
    '  File "/app/main.py", line 3',
    '    if ready',
    '            ^',
    "SyntaxError: expected ':'"
  ].join('\n')
});
assert.strictEqual(viewer.lineKind(syntaxError, syntaxError.lines[3], 3), 'caret', 'Syntax-error carets must be emphasized.');
assert.strictEqual(syntaxError.exceptionType, 'SyntaxError');

const chained = viewer.analyze({
  type: 'Python Exception',
  origin: 'RuntimeError',
  message: [
    'KeyError: customer_id',
    '',
    'The above exception was the direct cause of the following exception:',
    '',
    'Traceback (most recent call last):',
    '  File "/app/load.py", line 9, in load',
    '    raise RuntimeError("Load failed")',
    'RuntimeError: Load failed'
  ].join('\n')
});
assert.strictEqual(chained.exceptionType, 'RuntimeError', 'The final exception in a chained traceback must lead the summary.');
assert.strictEqual(viewer.lineKind(chained, chained.lines[2], 2), 'chain', 'Exception-chain separators must be distinct.');

const hop = viewer.analyze({ type: 'Apache Hop', origin: 'read customers.0', message: 'ERROR: Connection failed' });
assert.strictEqual(hop.isPython, false, 'Non-Python TMF errors must retain the generic runtime-log presentation.');
assert.strictEqual(viewer.lineKind(hop, hop.lines[0], 0), 'error');

assert(
  viewer.suggestedCheck(viewer.analyze({message: 'ModuleNotFoundError: No module named "pandas"'})).includes('requirements.txt'),
  'Common Python failures should include a practical next check.'
);

const jsonMessage = viewer.analyzeMessage({
  status: 'ready',
  records_total: '8',
  records_processed: '6',
  msg: '{"loaded":6,"rejected":2,"valid":true}'
});
assert.strictEqual(jsonMessage.kind, 'json', 'Structured TMF messages must be recognized as JSON.');
assert.strictEqual(jsonMessage.progress, 75, 'Run message context must calculate bounded progress.');
assert(jsonMessage.display.includes('\n  "loaded": 6'), 'JSON messages must be formatted for inspection.');

const multilineMessage = viewer.analyzeMessage({msg: 'Extracted 8 rows\nLoaded 6 rows'});
assert.strictEqual(multilineMessage.kind, 'log', 'Multiline TMF messages must use the log presentation.');
assert.strictEqual(multilineMessage.lines.length, 2);

const htmlMessage = viewer.analyzeMessage({msg: '<strong>Loaded</strong> 6 rows'});
assert.strictEqual(htmlMessage.kind, 'html', 'Legacy HTML messages must be identified and shown as source, not injected into the page.');
assert.strictEqual(htmlMessage.summary, 'Loaded 6 rows');

assert(view.includes('assets/dist/css/tmf-error-viewer.css'), 'The TMF results page must load the scoped diagnostics styles.');
assert(view.includes('assets/js/tmf-error-viewer.js'), 'The TMF results page must load the diagnostics renderer.');
assert(view.includes('JobSeekerTmfErrors.render'), 'TMF errors must be passed through the structured renderer.');
assert(view.includes('JobSeekerTmfErrors.renderMessage'), 'TMF run messages must use the structured log inspector.');
assert(view.includes('Inspect Log') && view.includes('Run log inspector'), 'The legacy Check Message dialog must be replaced by an operational log inspector.');
assert(!view.includes('renderJobMessage'), 'Run messages must not be rebuilt with HTML string concatenation.');
assert(!view.includes("'<td>'+ escapeHtml(value.message) +'"), 'The legacy unstructured traceback cell must not return.');
assert(model.includes("order_by('tmf_error.moment', 'ASC')") && model.includes("order_by('tmf_error.id', 'ASC')"), 'Multiple errors must be returned in deterministic occurrence order.');
assert(css.includes('.tmf-trace-row-frame') && css.includes('.tmf-py-keyword'), 'Traceback frames and Python source tokens must have distinct IDE colors.');
assert(css.includes('.tmf-log-search') && css.includes('.tmf-json-key'), 'Run messages must support search and structured JSON colors.');
assert(css.includes('@media (prefers-reduced-motion: reduce)'), 'Trace navigation must respect reduced-motion preferences.');

console.log('TMF error diagnostics tests passed.');
