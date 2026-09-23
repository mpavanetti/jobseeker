'use strict';

// Guards against two per-request query patterns that were measured on the live
// stack and fixed. Both are source-level assertions so they run anywhere; the
// matching runtime numbers are in scripts/test-tmf-query-performance.py.
//
//   1. DbSettings_model::ensureSchema() ran in the model constructor, so every
//      page that touches a connector paid for it. It probed eight columns with
//      $this->db->field_exists() and six more with one information_schema query
//      each. CodeIgniter 3.1.10's list_fields() has no cache - unlike
//      list_tables() - so each field_exists() is a fresh SHOW COLUMNS: fourteen
//      schema round trips per request. Measured on /hop: 41 statements before,
//      28 after collapsing them into a single information_schema read.
//
//   2. The TMF landing page ran four SELECT DISTINCT queries over a 250k-row
//      table on every load to populate its filter dropdowns. Measured: 277ms
//      serial and 3.6 req/s at 8x concurrency before caching, 7ms and 248 req/s
//      after.

const fs = require('fs');
const assert = require('assert');

const read = (p) => fs.readFileSync(p, 'utf8');
let checks = 0;
function ok(label, condition) {
  assert(condition, label);
  checks++;
}

// --- 1. Connector schema maintenance reads the column list once -------------
const dbSettings = read('application/models/DbSettings_model.php');
const ensureSchema = dbSettings.match(/private function ensureSchema\(\)\s*\{[\s\S]*?\n    \}/);
ok('DbSettings_model::ensureSchema() exists', ensureSchema !== null);
const schemaBody = ensureSchema[0];

// Match the call, not the comment above it that explains why it is gone.
ok('ensureSchema must not probe columns with field_exists()',
  !/\$this->db->field_exists\(/.test(schemaBody));
ok('ensureSchema reads the column list in one information_schema query',
  /SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH FROM information_schema\.COLUMNS/.test(schemaBody));
// One read, not one per column: the query must not filter on COLUMN_NAME.
ok('the column snapshot covers the whole table, not one column at a time',
  !/information_schema\.COLUMNS[^;]*COLUMN_NAME\s*=\s*\?/.test(schemaBody));
ok('ensureSchema still adds a missing column',
  /ALTER TABLE `database_settings` ADD/.test(schemaBody));
ok('ensureSchema still widens a column that is too narrow',
  /ALTER TABLE `database_settings` MODIFY/.test(schemaBody));
// The snapshot is taken before the ADDs, which is only safe while the two lists
// are disjoint - a column added here is created at its final width.
const added = [...schemaBody.matchAll(/^\s{12}'([a-z_A-Z]+)' => "/gm)].map((m) => m[1]);
const widened = [...schemaBody.matchAll(/^\s{12}'([a-z_A-Z]+)' => (\d+)/gm)].map((m) => m[1]);
ok('the added-column and widened-column lists must stay disjoint',
  added.length > 0 && widened.length > 0 && !added.some((c) => widened.includes(c)),
  );

// --- 2. TMF filter dropdowns are cached --------------------------------------
const tmf = read('application/controllers/Tmf.php');
ok('Tmf declares a filter cache TTL', /const FILTER_CACHE_TTL = \d+;/.test(tmf));
const ttl = Number((tmf.match(/const FILTER_CACHE_TTL = (\d+);/) || [])[1]);
ok('the filter cache TTL is short enough to stay fresh', ttl > 0 && ttl <= 120, 'ttl=' + ttl);
ok('Tmf builds its dropdowns through the cached helper',
  /private function filterOptions\(\$environment\)/.test(tmf));
ok('the filter cache is keyed per environment',
  /'tmf_filter_options_' \. preg_replace/.test(tmf));
ok('the filter cache can be bypassed with ?fresh=1',
  /\$fresh = in_array\(\(string\) \$this->input->get\('fresh'\)/.test(tmf));
ok('index() no longer calls the DISTINCT model methods directly',
  !/\$data\["listStatus"\] = \$this->model->listStatus/.test(tmf));
for (const key of ['status', 'jobName', 'dimension', 'reprocess', 'environment']) {
  ok('the cached payload carries ' + key,
    new RegExp("'" + key + "' => \\$this->model->list").test(tmf));
}

// --- 3. The dashboard categorises distinct subjects, not rows ---------------
// Workload categories come from six REGEXP branches over
// LOWER(CONCAT_WS(dimension, job_name, event_text)). Each branch costs roughly
// a second per 250k rows, so running them per row put ~6.7s into every
// dashboard cache miss. Those three columns take only ~150 distinct
// combinations across a quarter of a million rows, so the rows are folded down
// first and the regular expressions run over what is left: measured 6.7s -> 1.4s,
// and 10.0s -> 4.6s for the whole endpoint, with byte-identical output.
const dashboard = read('application/models/Dashboard_model.php');
const workloads = dashboard.match(/private function dashboardWorkloads\(\$environment\)[\s\S]*?ORDER BY executions DESC, category ASC'\);/);
ok('Dashboard_model::dashboardWorkloads() exists', workloads !== null);
const workloadBody = workloads[0];

ok('the subject is built by its own helper so it can be reused',
  /private function workloadSubjectExpression\(/.test(dashboard) &&
  workloadBody.includes('$this->workloadSubjectExpression(TRUE)'));
ok('the category CASE runs against the pre-computed column, not the raw concat',
  workloadBody.includes("$this->workloadExpression(TRUE, 'workload_subject')"));
ok('rows are aggregated before they are categorised',
  /GROUP BY workload_subject, job_name/.test(workloadBody));
ok('the outer query sums the pre-aggregated counts',
  /SUM\(executions\) AS executions/.test(workloadBody) && /SUM\(ready\) AS ready/.test(workloadBody));
ok('the distinct job count still comes from the grouped rows',
  /COUNT\(DISTINCT job_name\) AS jobs/.test(workloadBody));
// The whole point is that the expensive expression appears once, in the inner
// query, rather than in both the SELECT list and the GROUP BY.
const sqlOnly = workloadBody.slice(workloadBody.indexOf("$this->db->query("));
ok('the subject expression is interpolated exactly once',
  (sqlOnly.match(/\$subject/g) || []).length === 1, 'found ' + (sqlOnly.match(/\$subject/g) || []).length);
ok('the category expression is interpolated exactly once',
  (sqlOnly.match(/\$category/g) || []).length === 1, 'found ' + (sqlOnly.match(/\$category/g) || []).length);

console.log('Query efficiency regression checks passed (' + checks + ' assertions).');
