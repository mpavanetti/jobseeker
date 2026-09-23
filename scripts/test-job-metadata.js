'use strict';

// Two bugs that produced wrong data silently - no error, no empty state, just a
// plausible-looking value that was not true.
//
//   1. Jobs whose name is a number reported "Created: Not tracked" even though
//      their creation date had been recorded. json_decode($json, TRUE) returns a
//      PHP array, and a PHP array key that looks like an integer becomes one, so
//      a job named "1" arrived at the filter as int 1, failed is_string() and was
//      dropped. Bulk-created jobs are named this way, so it hit whole batches.
//
//   2. The Jenkins proxy read the first status line and the first Content-Type
//      out of $http_response_header. PHP follows redirects itself and appends
//      every response in the chain to that array, so both belonged to the
//      redirect rather than to the body actually returned: the proxy answered
//      "302 Found" carrying a 200's payload (which jQuery routes to .fail()) and
//      labelled JSON with whatever the redirect declared. A caller handed a
//      string instead of an object then read every field as undefined and
//      rendered a blank build number, "No result" and "Not available".

const fs = require('fs');
const assert = require('assert');

const read = (p) => fs.readFileSync(p, 'utf8');
let checks = 0;
function ok(label, condition, detail) {
  assert(condition, label + (detail ? ' -> ' + detail : ''));
  checks++;
}

// --- 1. Creation dates survive a numeric job name ---------------------------
const readers = [
  'application/controllers/JobView.php',
  'application/controllers/JobExecution.php',
  'application/controllers/JobCreation.php'
];
for (const file of readers) {
  const body = read(file);
  // Anchor on the function's own last statement: its early returns are also
  // brace-terminated, so matching the first closing brace truncates the body.
  const reader = body.match(/private function readJobCreationDates\(\)[\s\S]*?return \$cleanDates;/);
  ok(file + ' defines readJobCreationDates()', reader !== null);
  const fn = reader[0];
  ok(file + ' must not drop numerically named jobs with is_string($jobName)',
    !/is_string\(\$jobName\)/.test(fn));
  ok(file + ' casts the decoded key back to a string',
    /\$jobName = \(string\) \$jobName;/.test(fn));
  // The value is still worth checking - a malformed file could hold anything.
  ok(file + ' still validates the recorded date', /is_string\(\$createdAt\)/.test(fn));
  ok(file + ' still rejects empty names and dates',
    /\$jobName !== ''/.test(fn) && /\$createdAt !== ''/.test(fn));
}

// --- 2. The proxy reports the final response, not the redirect --------------
const base = read('application/libraries/BaseController.php');
const request = base.match(/protected function requestJenkins\([\s\S]*?\n\t\}/);
ok('requestJenkins() exists', request !== null);
const body = request[0];

ok('the status code is no longer taken from $responseHeaders[0]',
  !/\$responseHeaders\[0\]/.test(body));
ok('every status line in the chain is parsed',
  body.includes("preg_match('#^HTTP/[\\d.]+\\s+(\\d{3})#', $header, $matches)"));
// Without the break the loop keeps walking, so the last Content-Type wins.
const contentTypeLoop = body.match(/foreach \(\$responseHeaders as \$header\) \{[\s\S]*?\n\t\t\}/);
ok('the header chain is walked in a single loop', contentTypeLoop !== null);
ok('the Content-Type scan no longer stops at the first match',
  !contentTypeLoop[0].includes('break;'));
ok('a new status line resets the content type for that response',
  /\$statusCode = \(int\) \$matches\[1\];[\s\S]{0,40}\$responseContentType = 'text\/plain';/.test(contentTypeLoop[0]));

// --- 3. The comparison request does not trust the response type -------------
const jobView = read('application/views/jobView.php');
const fetchRun = jobView.match(/function fetchRunForComparison\(jobName, number\)\s*\{[\s\S]*?\n    \}/);
ok('fetchRunForComparison() exists', fetchRun !== null);
ok('the build request pins dataType so a non-JSON content type cannot slip through',
  /api\/json\?tree=' \+ tree, 'GET', \{dataType: 'json'\}/.test(fetchRun[0]));
ok('a response that is not a build is reported instead of rendered blank',
  /typeof build !== 'object' \|\| ! build\.number/.test(fetchRun[0]));

console.log('Job metadata and proxy response tests passed (' + checks + ' assertions).');
