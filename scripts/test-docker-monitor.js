const fs = require('fs');
const path = require('path');
const vm = require('vm');

const viewPath = path.join(__dirname, '..', 'application', 'views', 'dockerMonitoring.php');
const view = fs.readFileSync(viewPath, 'utf8');
const controller = fs.readFileSync(path.join(__dirname, '..', 'application', 'controllers', 'DockerMonitoring.php'), 'utf8');
const routes = fs.readFileSync(path.join(__dirname, '..', 'application', 'config', 'routes.php'), 'utf8');
const proxy = fs.readFileSync(path.join(__dirname, '..', 'docker', 'monitor', 'nginx.conf'), 'utf8');
const scriptStart = view.lastIndexOf('<script>');
const scriptEnd = view.indexOf('</script>', scriptStart);
const browserScript = view.slice(scriptStart + '<script>'.length, scriptEnd).replace(/<\?php[\s\S]*?\?>/g, '"/test"');
new vm.Script(browserScript, { filename: 'dockerMonitoring.browser.js' });
const start = view.indexOf('function lifecycleMoment');
const end = view.indexOf('function progress', start);

if (start < 0 || end < 0) {
  throw new Error('Docker monitor health functions were not found.');
}

const context = {
  bytes(value) { return `${Number(value) || 0} B`; }
};
vm.createContext(context);
new vm.Script(view.slice(start, end), { filename: 'dockerMonitoring.health.js' }).runInContext(context);

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

assert(context.containerSeverity({ state: 'running', health: 'healthy', cpuPercent: 12, memoryPercent: 35, restartCount: 0 }) === 'normal', 'Healthy workloads should remain normal.');
assert(context.containerSeverity({ state: 'running', health: 'unhealthy' }) === 'critical', 'Unhealthy workloads should be critical.');
assert(context.containerSeverity({ state: 'exited', oomKilled: true, exitCode: 137 }) === 'critical', 'OOM exits should be critical.');
assert(context.containerSeverity({ state: 'running', health: 'healthy', memoryPercent: 91 }) === 'warning', 'High memory should produce a warning.');
assert(context.containerSeverity({ state: 'exited', exitCode: 1 }) === 'warning', 'Non-zero exits should produce a warning.');
assert(context.containerSeverity({ state: 'exited', exitCode: 143, finishedAt: new Date().toISOString() }) === 'normal', 'Intentional SIGTERM exits should not become active incidents.');
assert(context.containerSeverity({ state: 'exited', exitCode: 1, finishedAt: '2020-01-01T00:00:00Z' }) === 'normal', 'Old failed exits should age out of active health.');
assert(context.hasPastExit({ state: 'exited', exitCode: 1, finishedAt: '2020-01-01T00:00:00Z' }), 'Old failed exits should remain available as history.');

const issue = context.containerIssue({ state: 'exited', oomKilled: true, exitCode: 137, restartCount: 2 });
assert(issue.includes('out of memory') && issue.includes('2 restarts') && issue.includes('exit 137'), 'Issue descriptions should preserve actionable diagnostics.');

const diskIssues = context.hostIssues({ diskTotalBytes: 1000, diskUsedBytes: 960, diskFreeBytes: 40 }, null);
assert(diskIssues.length === 1 && diskIssues[0].severity === 'critical', 'Critical host disk pressure must be surfaced.');

assert(view.includes('id="dockerReclaimCache"'), 'Docker monitoring must expose the cache reclaim action.');
assert(view.includes('Reclaim Docker Build Cache'), 'Cache cleanup must require an explicit confirmation.');
assert(routes.includes("docker-monitoring/reclaim-cache"), 'The protected cache reclaim route must be registered.');
assert(controller.includes("'/build/prune?all=1'"), 'Cache cleanup must target only Docker build cache.');
assert(controller.includes("method(TRUE) !== 'POST'"), 'Cache cleanup must reject non-POST requests.');
assert(!controller.includes("'/system/prune'"), 'Broad Docker system prune must never be exposed.');
assert(!controller.includes("'/images/prune'"), 'Image prune must remain outside cache cleanup.');
assert(!controller.includes("'/volumes/prune'"), 'Volume prune must remain outside cache cleanup.');
assert(/location = \/build\/prune\s*\{[\s\S]*?limit_except POST/.test(proxy), 'The host socket proxy must expose an exact POST-only build prune route.');

console.log('Docker monitor health tests passed.');
