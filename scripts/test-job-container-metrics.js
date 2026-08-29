const fs = require('fs');
const path = require('path');
const vm = require('vm');

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const root = path.join(__dirname, '..');
const executionView = fs.readFileSync(path.join(root, 'application', 'views', 'jobExecution.php'), 'utf8');
const scriptStart = executionView.lastIndexOf('<script');
const scriptBodyStart = executionView.indexOf('>', scriptStart) + 1;
const scriptEnd = executionView.indexOf('</script>', scriptBodyStart);
assert(scriptStart >= 0 && scriptEnd > scriptBodyStart, 'Job Execution browser script was not found.');

const browserScript = executionView
  .slice(scriptBodyStart, scriptEnd)
  .replace(/<\?php[\s\S]*?\?>/g, '"/test"');
new vm.Script(browserScript, {filename: 'jobExecution.container-metrics.js'});

const metricsStart = executionView.indexOf('function initializeContainerTracking');
const metricsEnd = executionView.indexOf('function isTerminal', metricsStart);
assert(metricsStart >= 0 && metricsEnd > metricsStart, 'Container metric functions were not found.');
const metricsContext = {};
vm.createContext(metricsContext);
new vm.Script(executionView.slice(metricsStart, metricsEnd), {filename: 'jobExecution.cpu-delta.js'}).runInContext(metricsContext);
const run = {};
metricsContext.initializeContainerTracking(run);
const first = {cpuTotalUsage: 1000000000, systemCpuUsage: 100000000000, onlineCpus: 4, cpuSampleAvailable: false};
metricsContext.normalizeRunContainerCpu(run, first);
assert(first.cpuSampleAvailable === false, 'The first fast Docker CPU sample should be marked as sampling.');
const second = {cpuTotalUsage: 3000000000, systemCpuUsage: 108000000000, onlineCpus: 4, cpuSampleAvailable: false};
metricsContext.normalizeRunContainerCpu(run, second);
assert(second.cpuSampleAvailable === true && Math.abs(second.cpuPercent - 100) < 0.001, 'CPU usage should be calculated from consecutive raw Docker counters.');

const generator = fs.readFileSync(path.join(root, 'application', 'controllers', 'JobCreation.php'), 'utf8');
const identityUses = (generator.match(/\$this->dockerJobRunIdentityOptions\(\)/g) || []).length;
assert(identityUses === 3, 'Every primary Docker execution path must add JobSeeker identity options.');
assert(generator.includes('com.jobseeker.kind=job'), 'Docker jobs must carry the managed job label.');
assert(generator.includes('com.jobseeker.job.name='), 'Docker jobs must carry their Jenkins job name.');
assert(generator.includes('com.jobseeker.build.number='), 'Docker jobs must carry their Jenkins build number.');
assert(generator.includes('--cpus "$JOBSEEKER_CONTAINER_CPUS"'), 'Docker jobs must enforce their configured CPU limit.');
assert(generator.includes('--memory "${JOBSEEKER_CONTAINER_MEMORY_MB}m"'), 'Docker jobs must enforce their configured memory limit.');
assert(generator.includes('--memory-swap "${JOBSEEKER_CONTAINER_MEMORY_MB}m"'), 'Docker jobs must prevent swap from bypassing the memory limit.');
assert((generator.match(/array_merge\(\$lines, \$this->dockerJobResourceLines\(\$runtimeOptions\)\)/g) || []).length === 3, 'Every primary Docker execution path must export resource limits.');
assert(generator.includes("dockerJobIdentityLines('python')"), 'Python Docker jobs must identify their runtime.');
assert(generator.includes("dockerJobIdentityLines('linux-shell')"), 'Inline shell Docker jobs must identify their runtime.');

console.log('Job container metrics tests passed.');
