// Notebook runs in the job console: job-console-groups.js opens a notebook
// section at "[JobSeeker] Notebook execution", and job-console-notebook.js
// turns the runner's markers back into cells.
const assert = require('assert');
const groups = require('../assets/js/job-console-groups.js');
const notebook = require('../assets/js/job-console-notebook.js');

const log = [
  'Started by user jobseeker',
  '[JobSeeker] Docker container execution',
  '+ docker run --rm -i --name jobseeker-job-report-3 -v jobseeker-email-report-3:/jobseeker-email -v jobseeker-assets-report-3:/jobseeker-repository -e JOBSEEKER_EMAIL_METRICS_FILE=/jobseeker-email/jobseeker-email-metrics.properties -e JOBSEEKER_DATA_ASSETS_MANIFEST=/jobseeker-repository/data-assets/manifest.json image sh -c set -e',
  'mkdir -p /tmp/jobseeker-context',
  'python -u -m jobseeker.notebook run "$JOBSEEKER_ENTRYPOINT" --output "/jobseeker-notebook/$(basename "$JOBSEEKER_ENTRYPOINT")" sh DEV',
  '[JobSeeker] Notebook execution',
  '[JobSeeker] No Jupyter kernel in this environment: installing nbclient and ipykernel for this run.',
  '[JobSeeker Notebook] start | jobs/report/report.ipynb | 6 cells | 4 code | kernel python3',
  '[JobSeeker Notebook] parameters | {"rows": {"value": 12, "from": "context rows"}, "region": {"value": "emea", "from": "value"}}',
  '[JobSeeker Notebook] cell 1/6 | markdown',
  '│ # Report',
  '│ Daily *sales*.',
  '[JobSeeker Notebook] cell 2/6 | code | tags parameters',
  '│ rows = 200',
  '[JobSeeker Notebook] run 2/6',
  '[JobSeeker Notebook] done 2/6 | ok | 0.01s',
  '[JobSeeker Notebook] cell 3/6 | code',
  '│ for i in range(rows):',
  '│     print(i)',
  '[JobSeeker Notebook] run 3/6',
  '0',
  '1',
  '[JobSeeker Notebook] result 3/6 | Out[2] | text/html 929 B',
  '   hour  amount',
  '0     2   55.12',
  '[JobSeeker Notebook] display 3/6 | image/png 14.0 KB',
  '<Figure size 800x300 with 1 Axes>',
  '[JobSeeker Notebook] stream 3/6 | stdout',
  'after the figure',
  '[JobSeeker Notebook] done 3/6 | ok | 1.20s',
  '[JobSeeker Notebook] cell 4/6 | code',
  '│ 1/0',
  '[JobSeeker Notebook] run 4/6',
  '[JobSeeker Notebook] error 4/6 | ZeroDivisionError | division by zero',
  'ZeroDivisionError                         Traceback (most recent call last)',
  'ZeroDivisionError: division by zero',
  '[JobSeeker Notebook] done 4/6 | error | 0.02s',
  '[JobSeeker Notebook] cell 5/6 | code',
  '│ print("never")',
  '[JobSeeker Notebook] done 5/6 | skipped | an earlier cell failed',
  '[JobSeeker Notebook] cell 6/6 | markdown',
  '│ ## End',
  '[JobSeeker] Cell 4 raised ZeroDivisionError: division by zero',
  '[JobSeeker Notebook] saved | notebook-runs/report/3/report.ipynb',
  '[JobSeeker Notebook] finish | FAILURE | 3/4 code cells | 1.40s',
  '+ docker run --rm --user 0 --entrypoint sh -v jobseeker-notebook-report-3:/jobseeker-notebook:ro image -c tar -C /jobseeker-notebook -cf - .',
  '[JobSeeker] Cleanup',
  '+ docker run --rm --user 0 --entrypoint cat -v jobseeker-email-report-3:/jobseeker-email:ro image /jobseeker-email/jobseeker-email-metrics.properties',
  'Finished: FAILURE'
].join('\n');

// The job's own docker run mounts the email and asset volumes: it is not
// cleanup, and the runner's command line is not a Python execution.
const parsed = groups.parse(log);
assert.deepStrictEqual(parsed.sections.map((section) => section.kind), ['jenkins', 'docker-execution', 'notebook', 'cleanup', 'result']);
const section = parsed.sections.find((item) => item.kind === 'notebook');
assert.strictEqual(section.title, 'Notebook run');
assert.ok(section.hasError, 'a failed cell marks the section');
assert.ok(parsed.sections.find((item) => item.kind === 'cleanup').text.includes('tar -C /jobseeker-notebook'));

const model = notebook.parse(section.text);
assert.strictEqual(model.path, 'jobs/report/report.ipynb');
assert.strictEqual(model.kernel, 'python3');
assert.strictEqual(model.codeCount, 4);
assert.deepStrictEqual(model.parameters.rows, {value: 12, from: 'context rows'});
assert.strictEqual(model.saved, 'notebook-runs/report/3/report.ipynb');
assert.deepStrictEqual(model.finish, {status: 'FAILURE', cells: '3/4 code cells', elapsed: '1.40s'});
assert.deepStrictEqual(model.cells.map((cell) => cell.index + ':' + cell.type + ':' + cell.state), [
  '1:markdown:text', '2:code:ok', '3:code:ok', '4:code:error', '5:code:skipped', '6:markdown:text'
]);
assert.deepStrictEqual(model.cells[0].source, ['# Report', 'Daily *sales*.']);
assert.deepStrictEqual(model.cells[1].tags, ['parameters']);
const outputs = model.cells[2].outputs;
assert.deepStrictEqual(outputs.map((out) => out.kind), ['stream', 'result', 'display', 'stream']);
assert.deepStrictEqual(outputs[0].text, ['0', '1']);
assert.strictEqual(outputs[1].label, 'Out[2]');
assert.deepStrictEqual(outputs[1].rich, [{mime: 'text/html', size: '929 B'}]);
assert.deepStrictEqual(outputs[1].text, ['   hour  amount', '0     2   55.12']);
assert.deepStrictEqual(outputs[3].text, ['after the figure']);
assert.strictEqual(model.cells[2].duration, '1.20s');
assert.strictEqual(model.cells[3].outputs[0].ename, 'ZeroDivisionError');
assert.strictEqual(model.cells[3].outputs[0].evalue, 'division by zero');
assert.strictEqual(model.cells[4].note, 'an earlier cell failed');
assert.deepStrictEqual(model.notesBefore, ['[JobSeeker] No Jupyter kernel in this environment: installing nbclient and ipykernel for this run.']);
assert.deepStrictEqual(model.notesAfter, ['[JobSeeker] Cell 4 raised ZeroDivisionError: division by zero']);

// A run that is still going: the running cell has no done marker yet.
const live = notebook.parse([
  '[JobSeeker Notebook] start | nb.ipynb | 2 cells | 2 code | kernel python3',
  '[JobSeeker Notebook] cell 1/2 | code',
  '│ import time',
  '[JobSeeker Notebook] run 1/2',
  'partial output'
].join('\n'));
assert.strictEqual(live.cells[0].state, 'running');
assert.strictEqual(live.finish, null);
assert.deepStrictEqual(live.cells[0].outputs[0].text, ['partial output']);

// Renderers escape everything they are given.
assert.ok(!notebook.renderMarkdown('<script>alert(1)</script> **b**').includes('<script>'));
assert.ok(notebook.renderMarkdown('# T\n- a\n- b\n[x](javascript:alert(1))').includes('<ul><li>a</li><li>b</li></ul>'));
assert.ok(!notebook.renderMarkdown('[x](javascript:alert(1))').includes('href="javascript'));
assert.strictEqual(notebook.highlightPython('x = "<b>"  # c'), 'x <span class="nbc-tok-operator">=</span> <span class="nbc-tok-string">&quot;&lt;b&gt;&quot;</span>  <span class="nbc-tok-comment"># c</span>');
assert.strictEqual(notebook.ansiToHtml('\u001b[0;31mErr\u001b[0m <x>'), '<span class="nbc-ansi-red">Err</span> &lt;x&gt;');

console.log('Job console notebook tests passed.');
