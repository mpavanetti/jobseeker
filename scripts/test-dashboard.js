const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const script = fs.readFileSync(path.join(root, 'assets', 'js', 'dashboard.js'), 'utf8');
const view = fs.readFileSync(path.join(root, 'application', 'views', 'dashboard.php'), 'utf8');
const model = fs.readFileSync(path.join(root, 'application', 'models', 'Dashboard_model.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'application', 'controllers', 'Dashboard.php'), 'utf8');
const jenkinsProxy = fs.readFileSync(path.join(root, 'application', 'controllers', 'JenkinsProxy.php'), 'utf8');
const baseController = fs.readFileSync(path.join(root, 'application', 'libraries', 'BaseController.php'), 'utf8');
const routes = fs.readFileSync(path.join(root, 'application', 'config', 'routes.php'), 'utf8');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function jquery(argument) {
  if (typeof argument === 'function') return undefined;
  return {
    text() { return this; }, html() { return ''; }, attr() { return this; },
    toggle() { return this; }, css() { return this; }, hide() { return this; },
    show() { return this; }, fadeIn() { return this; }, addClass() { return this; },
    removeClass() { return this; }, on() { return this; }, closest() { return this; }
  };
}

jquery.ajax = function() { throw new Error('Dashboard requests must not run during helper tests.'); };
jquery.Deferred = function() { return { reject() { return { promise() {} }; } }; };

const context = {
  jQuery: jquery,
  window: {
    jobseekerDashboardConfig: { environment: 'DEV' },
    setInterval() { return 1; }
  },
  document: {},
  console
};
context.window.window = context.window;
vm.createContext(context);
new vm.Script(script, { filename: 'dashboard.js' }).runInContext(context);

const helpers = context.window.JobSeekerDashboard;
assert(helpers.formatCompact(1250000) === '1.3M', 'Large record counts should use compact readable notation.');
assert(helpers.formatDuration(3665) === '1h 1m', 'Long runtimes should be rendered as hours and minutes.');
assert(helpers.withEnvironment('/dashboard/overview') === '/dashboard/overview?environment=DEV', 'Dashboard API requests must keep the selected environment.');
assert(helpers.withEnvironment('/dashboard/overview?fresh=1') === '/dashboard/overview?fresh=1&environment=DEV', 'Environment scope must preserve existing query parameters.');

assert(!script.includes('async: false'), 'Dashboard requests must remain asynchronous.');
assert(!script.includes('dashboardSyncJson'), 'The legacy blocking request helper must not return.');
assert(view.includes('Execution Health Trend') && view.includes('Data Platform Workloads'), 'The operational and data-platform dashboard sections must remain present.');
assert(view.includes('name="dashboardTrendDays" value="30"') && view.includes('value="90"') && view.includes('value="180"'), 'One trend chart must support 30, 90 and 180-day ranges.');
assert(view.indexOf('moment/moment.min.js') < view.indexOf('chart.js/Chart.min.js'), 'Moment must load before Chart.js so the time-scale adapter is registered.');
assert(!view.includes('readyGrowthDeclineX180'), 'The redundant legacy growth panels must not return.');
assert(script.includes('renderChartSafely'), 'A chart rendering error must not block the rest of the dashboard.');
assert(model.includes('public function overview('), 'The consolidated dashboard model payload must remain available.');
assert(model.includes('DATE(last_activity) AS activity_date'), 'Trend buckets must include the full date, not month name alone.');
assert(model.includes('Data Quality & Governance') && model.includes('Warehouse & Lakehouse') && model.includes('ML & Feature Pipelines'), 'Modern data workload classifications must remain supported.');
assert(controller.includes('function overview()'), 'The consolidated dashboard endpoint must remain available.');
assert(jenkinsProxy.includes('function dashboardMetrics()'), 'The sanitized Jenkins dashboard endpoint must remain available.');
assert(jenkinsProxy.includes("$slots[$field] += isset($row[$field])"), 'Dashboard slots must aggregate backend environment limits.');
assert(jenkinsProxy.includes("'onlineAgentExecutors' => 0"), 'Dashboard capacity rows must expose online agent executors.');
assert(baseController.includes("$effectiveLimit = $configuredLimit + (int) $slotStatus['environments'][$environmentName]['onlineAgentExecutors']"), 'Online environment-agent executors must be additive to dashboard slot limits.');
assert(baseController.includes("$includeControllerCapacity = $requestedEnvironment === 'LOCAL'") && baseController.includes("empty($status['environmentAgentsEnabled'])") && baseController.includes("$environmentRow['onlineAgentExecutors']") && baseController.includes('$controllerHasScopedBuild'), 'Scoped metrics must include controller capacity when routing is disabled, it is the environment fallback, or it is running a scoped build.');
assert(baseController.includes('function($row) use ($requestedEnvironment, $includeControllerCapacity)'), 'Scoped nodes and executors must apply the controller-capacity decision consistently.');
assert(routes.includes("dashboard/overview") && routes.includes("jenkins/dashboardMetrics"), 'Dashboard routes must remain registered.');

// The overview endpoint caches its aggregate query for a short window and
// honours ?fresh=1 so the manual refresh button always recomputes.
assert(controller.includes("\$this->cache->get(\$cacheKey)") && controller.includes('OVERVIEW_CACHE_TTL'), 'The dashboard overview must be served from a short-lived server-side cache.');
assert(controller.includes("in_array((string) \$this->input->get('fresh')"), 'The dashboard overview must support a fresh=1 cache bypass.');
assert(script.includes("+ 'fresh=1'"), 'The dashboard client must request a fresh payload on manual refresh.');

// Timezone is configured once centrally, not hardcoded per controller.
const config = fs.readFileSync(path.join(root, 'application', 'config', 'config.php'), 'utf8');
assert(config.includes("getenv('JOBSEEKER_TIMEZONE')") && config.includes('date_default_timezone_set($jobseekerTimezone)'), 'The application timezone must be set once from JOBSEEKER_TIMEZONE.');
assert(!controller.includes("date_default_timezone_set('America/Sao_Paulo')"), 'Controllers must not hardcode the timezone.');

console.log('Dashboard metric and rendering tests passed.');
