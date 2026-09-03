// Guards the Job Creation job-list hydration path.
//
// The list used to fetch <jenkins>/job/<name>/config.xml for EVERY job on every
// page load, purely to label each row with its environment. With 59 jobs that
// was 59 proxied Jenkins round trips per page view, and it grew linearly with
// the number of jobs. The server already resolves the environment from the
// Jenkins API tree it fetches anyway, so a job whose environment came from a
// Jenkins parameter is flagged and the browser skips the redundant request.
// Jobs the server cannot resolve must still fall back to parsing config.xml,
// because only that reveals an environment declared in the build command.
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const jobView = read('application/views/jobCreation.php');
const jobController = read('application/controllers/JobCreation.php');
const baseController = read('application/libraries/BaseController.php');
const environmentJs = read('assets/js/job-environment.js');
const jobListView = read('application/views/jobList.php');
const promotionView = read('application/views/contextPromotion.php');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

// --- The server publishes the confidence of its own detection ---------------
assert(/\$job->environmentFromParameter\s*=\s*\$environment\s*!==\s*''\s*&&\s*\$parameterEnvironment\s*===\s*\$environment;/.test(jobController),
  'availableJobs must tell the browser when the environment came from a Jenkins parameter.');
assert(/\$parameterEnvironment\s*=\s*\$this->normalizeJobSeekerEnvironment\(\$this->jenkinsEnvironmentFromJobProperty\(\$job\)\);/.test(jobController),
  'The parameter-derived environment must be normalized the same way as the resolved environment.');
assert(/\$job->environmentSource\s*=\s*\$environment\s*===\s*''\s*\?\s*'Not detected'\s*:\s*'Jenkins metadata';/.test(jobController),
  'The existing environmentSource label must keep its meaning for the views that display it.');

// --- The browser trusts that flag instead of re-fetching config.xml ---------
assert(/if \(item\.row\.environmentFromParameter && item\.row\.environment\) \{/.test(jobView),
  'The job list must skip the per-job config.xml request when the server already resolved the environment.');
assert(/source: 'Jenkins parameter'/.test(jobView),
  'A skipped fetch must still record where the environment came from.');
const hydrate = jobView.slice(jobView.indexOf('function hydrateAvailableJobEnvironment'));
assert(/config\.xml/.test(hydrate.slice(0, hydrate.indexOf('function hydrateAvailableJobEnvironments'))),
  'Jobs the server could not resolve must still be parsed from config.xml.');

// --- Both sides must agree on what counts as an environment parameter -------
const serverNames = (baseController.match(/const JENKINS_ENVIRONMENT_PARAMETER_NAMES = '\/\^\(([^)]+)\)\$\/';/) || [])[1];
const clientNames = (environmentJs.match(/\/\^\(([^)]+)\)\$\/\.test\(parameterName\)/) || [])[1];
assert(serverNames, 'BaseController must declare the environment parameter names it accepts.');
assert(clientNames, 'job-environment.js must declare the environment parameter names it accepts.');
assert(serverNames === clientNames,
  `The server and browser must accept the same environment parameter names.\n  server: ${serverNames}\n  client: ${clientNames}`);

// An empty parameter default is not a declaration and must not win over the
// job-name fallback.
assert(/if \(trim\(\$value\) !== ''\) \{/.test(baseController),
  'An empty parameter default must not be treated as an environment declaration.');

// --- The job list reads every schedule once, not once per job ---------------
// Jenkins only stores a cron trigger in config.xml, so the browser used to make
// one proxied request per job. With a PHP-FPM pool of a dozen workers a single
// job list view saturated the pool, and the results still fell back to job-name
// detection: a job called "smp-python-multi-stage-etl-r3" was labelled STAGE
// even though its ENVIRONMENT parameter says LOCAL.
assert(/jobCreation\/jobSchedules/.test(jobListView),
  'The job list must read schedules from the batched endpoint.');
assert(/function jobScheduleIndex\(\)/.test(jobListView) && /jobScheduleIndexRequest/.test(jobListView),
  'The schedule index must be fetched once and shared.');
assert(/jobScheduleIndex\(\)\.done\(function\(\) \{[\s\S]{0,240}hydrateVisibleJobSchedules/.test(jobListView),
  'Visible-row hydration must wait for the shared index instead of racing it.');
assert(/entry\.environmentFromParameter/.test(jobListView),
  'Only a parameter-derived environment may skip the per-job config.xml request.');
assert(/function hydrateRemainingJobEnvironments/.test(jobListView) && /config\.xml/.test(jobListView),
  'Jobs the index cannot answer must still fall back to their own config.xml.');
assert(/updateMonitorSummary\(jobs \|\| \[\]\);/.test(jobListView),
  'The monitor counters must be summarized from the job list, not read back off a table that is still being drawn.');

// --- One job payload feeds all three tables ---------------------------------
assert(/function loadAvailableJobs\(\)/.test(jobListView) && /availableJobsRequestKey/.test(jobListView),
  'The three job tables must share one availableJobs request per environment.');
assert((jobListView.match(/loadAvailableJobs\(\)\.done\(/g) || []).length === 3,
  'All three tables (all jobs, failed, successful) must go through the shared loader.');
assert(!/"url": availableJobsUrl,/.test(jobListView),
  'No table may still fetch availableJobs on its own.');

// A reload has to re-read both shared responses, or an edit made elsewhere
// would stay hidden behind an already-resolved promise.
assert(/jobScheduleIndexRequest = null;\s*availableJobsRequest = null;/.test(jobListView),
  'Aborting a job list reload must invalidate both shared requests.');

// --- Sharing must not defeat the auto-refresh timer -------------------------
// The three tables are reloaded on a timer. Sharing a settled response for
// longer than it takes all three to ask for it freezes the list on its first
// result, so the build list only updates on a full page reload.
const shareWindow = Number((jobListView.match(/var availableJobsShareWindowMs = (\d+);/) || [])[1]);
const refreshInterval = Number((jobListView.match(/var jobListRefreshIntervalMs = (\d+);/) || [])[1]);
assert(Number.isFinite(shareWindow) && shareWindow > 0,
  'The shared job list response must declare how long it may be reused.');
assert(Number.isFinite(refreshInterval) && refreshInterval > 0,
  'The job list must declare its auto-refresh interval.');
assert(shareWindow < refreshInterval,
  `A shared job list response must expire well inside the ${refreshInterval}ms refresh interval, otherwise the auto-refresh redraws stale rows (share window is ${shareWindow}ms).`);
assert(/availableJobsRequest\.state\(\) === 'pending'/.test(jobListView),
  'Concurrent table reloads must share the in-flight request rather than a settled one.');
assert(/availableJobsRequestKey === environment/.test(jobListView),
  'Switching environment must issue a new request rather than reuse the previous scope.');

// --- Promotion history resolves environments from the same index ------------
// Each history row used to read config.xml straight from Jenkins, with neither a
// cache nor de-duplication, so the same job was re-read once per row it appeared on.
assert(/jobCreation\/jobSchedules/.test(promotionView),
  'Promotion history must resolve environments from the batched endpoint.');
assert(/function jobEnvironmentIndex\(\)/.test(promotionView),
  'Promotion history must share one environment index request.');
assert(/indexed && indexed\.environmentFromParameter/.test(promotionView),
  'Only a parameter-derived environment may skip a promotion row config.xml read.');
assert(/pending\[entry\.jobName\]\.push\(entry\.row\);/.test(promotionView),
  'A job appearing on several history rows must be read once, not once per row.');
assert(/\$\.each\(targets, function\(idx, \$row\) \{\s*setInventoryEnvironment\(\$row, info\);/.test(promotionView),
  'One config.xml read must update every row that shares that job.');

console.log('Job list hydration checks passed (%d environment parameter names shared).', serverNames.split('|').length);
