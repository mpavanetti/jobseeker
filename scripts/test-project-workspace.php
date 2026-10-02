<?php
// Project layout rules: job folders, workspaces, detection and the VS Code helper.
define('JOBSEEKER_PROJECT_WORKSPACE_TEST', TRUE);
require dirname(__DIR__).'/application/libraries/ProjectWorkspace.php';

$checks = 0;
function project_workspace_assert($condition, $message) {
    global $checks;
    if (! $condition) {
        fwrite(STDERR, 'FAIL: '.$message."\n");
        exit(1);
    }
    $checks++;
}

function project_workspace_tree($root, array $files) {
    foreach ($files as $path => $content) {
        $target = $root.'/'.$path;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, TRUE);
        }
        file_put_contents($target, $content);
    }
}

function project_workspace_remove($path) {
    if (is_dir($path) && ! is_link($path)) {
        foreach (array_diff(scandir($path), array('.', '..')) as $entry) {
            project_workspace_remove($path.'/'.$entry);
        }
        rmdir($path);
    } else if (file_exists($path) || is_link($path)) {
        unlink($path);
    }
}

$layout = new ProjectWorkspace();

// Job folders are plain relative paths, safe as a Git pathspec and unquoted.
project_workspace_assert($layout->cleanJobPath('') === '', 'an empty folder is the repository root');
project_workspace_assert($layout->cleanJobPath('./') === '', './ is the repository root');
project_workspace_assert($layout->cleanJobPath('jobs/load-orders') === 'jobs/load-orders', 'jobs/<job> is a job folder');
project_workspace_assert($layout->cleanJobPath('/jobs/load_orders/') === 'jobs/load_orders', 'surrounding slashes are dropped');
project_workspace_assert($layout->cleanJobPath('./jobs/a') === 'jobs/a', 'a leading ./ is dropped');
foreach (array('../etc', 'jobs/../x', 'jobs/.hidden', '.git', 'jobs/-rf', 'jobs/a b', 'jobs//a', "jobs/a\0", 'jobs/$(id)', str_repeat('a/', 120)) as $unsafe) {
    project_workspace_assert($layout->cleanJobPath($unsafe) === FALSE, 'unsafe job folder refused: '.json_encode($unsafe));
}
project_workspace_assert($layout->defaultJobPath('Load Orders') === 'jobs/load-orders', 'a job name becomes a folder name');
project_workspace_assert($layout->defaultJobPath('sales/extract.v2') === 'jobs/sales-extract.v2', 'job names with folders flatten to one folder');
project_workspace_assert($layout->defaultJobPath('***') === 'jobs/job', 'a name without letters still gets a folder');
project_workspace_assert($layout->cleanJobPath($layout->defaultJobPath('Ünïcode Job!')) !== FALSE, 'default folders are always valid');

project_workspace_assert($layout->cleanType('Python') === 'python' && $layout->cleanType('hop') === 'hop' && $layout->cleanType('java') === FALSE, 'project types');
project_workspace_assert($layout->workspaceParent(TRUE, 7) === 'workspaces/u7', 'a Git project has a working copy per person');
project_workspace_assert($layout->workspaceParent(FALSE, 7) === 'workspaces/shared', 'a project without Git has one shared folder');
project_workspace_assert($layout->projectDirectory(12, 'Customer Analytics') === 'customer-analytics-12', 'project folders are readable and unique by id');
project_workspace_assert($layout->personalBranch('Matheus.Pavanetti') === 'work/matheus.pavanetti', 'personal branches live under work/');
project_workspace_assert($layout->pathUp('workspaces/u1/sales-3') === '../../..', 'paths back to the repository root');

$root = sys_get_temp_dir().'/jobseeker-project-workspace-'.bin2hex(random_bytes(4));
mkdir($root.'/workspaces/u1', 0777, TRUE);
mkdir($root.'/workspaces/u1/old-name-12');
mkdir($root.'/workspaces/u1/other-112');
project_workspace_assert($layout->resolveDirectory($root.'/workspaces/u1', 12, 'New Name') === 'old-name-12', 'a renamed project keeps its folder');
project_workspace_assert($layout->resolveDirectory($root.'/workspaces/u1', 11, 'Other') === 'other-11', 'ids match exactly, not by suffix');

// Detection: every folder under jobs/, its entry file first.
$project = $root.'/workspaces/u1/old-name-12';
project_workspace_tree($project, array(
    'shared/__init__.py' => '',
    'jobs/load-orders/main.py' => 'print(1)',
    'jobs/load-orders/helpers.py' => '',
    'jobs/load-orders/pyproject.toml' => '',
    'jobs/load-orders/tests/test_main.py' => '',
    'jobs/load-orders/Dockerfile' => 'FROM python:3.13-slim',
    'jobs/report/report.py' => '',
    'jobs/report/test_report.py' => '',
    'jobs/report/requirements.txt' => '',
    'jobs/.hidden/main.py' => '',
    'jobs/README.md' => '',
    'jobs/cleanup/run.sh' => '',
    'jobs/flow/main.hwf' => '',
    'jobs/flow/pipelines/load.hpl' => '',
    'jobs/analysis/explore.ipynb' => '{}',
    'jobs/analysis/report-checkpoint.ipynb' => '{}',
    'jobs/load-orders/profile.ipynb' => '{}'
));
$jobs = $layout->detectJobs($project, 'python');
$byName = array();
foreach ($jobs as $job) {
    $byName[$job['name']] = $job;
}
project_workspace_assert(array_keys($byName) === array('analysis', 'cleanup', 'flow', 'load-orders', 'report'), 'job folders found, hidden ones skipped: '.implode(',', array_keys($byName)));
project_workspace_assert($byName['analysis']['entryPoint'] === 'explore.ipynb' && $byName['analysis']['notebooks'] === array('explore.ipynb'), 'a notebook is a Python entry file, checkpoints are not');
project_workspace_assert($byName['load-orders']['path'] === 'jobs/load-orders' && $byName['load-orders']['entryPoint'] === 'main.py', 'main.py is the entry file');
project_workspace_assert($byName['load-orders']['entryPoints'] === array('main.py', 'helpers.py', 'profile.ipynb'), 'other modules follow the entry file, then notebooks');
project_workspace_assert($byName['load-orders']['runtime'] === 'docker' && $byName['load-orders']['hasTests'] && $byName['load-orders']['hasPyproject'], 'a Dockerfile selects the Docker runtime');
project_workspace_assert($byName['report']['entryPoint'] === 'report.py' && $byName['report']['runtime'] === 'local' && $byName['report']['hasRequirements'], 'test files are never entry files');
$shellJobs = $layout->detectJobs($project, 'shell');
$shellCleanup = array_values(array_filter($shellJobs, function($job) { return $job['name'] === 'cleanup'; }));
project_workspace_assert($shellCleanup[0]['entryPoint'] === 'run.sh' && $shellCleanup[0]['notebooks'] === array(), 'shell jobs run run.sh');
$hopJobs = $layout->detectJobs($project, 'hop');
$hopFlow = array_values(array_filter($hopJobs, function($job) { return $job['name'] === 'flow'; }));
project_workspace_assert($hopFlow[0]['entryPoints'] === array('main.hwf', 'pipelines/load.hpl'), 'Hop workflows come before pipelines');
project_workspace_assert($layout->detectJobs($root.'/missing', 'python') === array(), 'a missing workspace has no jobs');

// Scaffolding and starters.
$scaffold = $layout->scaffoldFiles('python', 'Customer Analytics');
project_workspace_assert(isset($scaffold['README.md'], $scaffold['jobs/README.md'], $scaffold['shared/__init__.py']), 'a Python project starts with jobs/ and shared/');
project_workspace_assert(strpos($scaffold['README.md'], '# Customer Analytics') === 0, 'the README names the project');
project_workspace_assert(isset($layout->scaffoldFiles('shell', 'x')['shared/common.sh']), 'a Shell project shares common.sh');
$starter = $layout->jobStarter('python', 'Load Orders', '3.12');
project_workspace_assert(array_keys($starter) === array('main.py', 'pyproject.toml', 'tests/test_main.py'), 'a Python job starts with code, project file and a test');
project_workspace_assert(strpos($starter['main.py'], '"load-orders"') !== FALSE && strpos($starter['main.py'], '__JOBSEEKER_JOB__') === FALSE, 'the starter names its job');
project_workspace_assert(strpos($starter['pyproject.toml'], 'requires-python = ">=3.12,<4.0"') !== FALSE, 'the starter targets the workspace Python');
project_workspace_assert(strpos($starter['main.py'], '.format(') === FALSE && strpos($starter['main.py'], "    with (\n") !== FALSE, 'the starter passes Ruff (f-strings, one with statement)');
project_workspace_assert(array_keys($layout->jobStarter('shell', 'x')) === array('run.sh'), 'a Shell job starts with run.sh');

// The VS Code helper creates job folders and prints the Job Creation link.
if (trim((string) shell_exec('command -v sh')) !== '' && trim((string) shell_exec('command -v base64')) !== '') {
    $vscode = $project.'/.vscode';
    mkdir($vscode);
    file_put_contents($vscode.'/jobseeker.sh', $layout->helperScript('python', 'http://jobseeker.local/JobCreation', 12));
    $output = shell_exec('cd '.escapeshellarg($project).' && sh .vscode/jobseeker.sh new "Daily Sync" 2>&1');
    project_workspace_assert(is_file($project.'/jobs/daily-sync/main.py') && is_file($project.'/jobs/daily-sync/tests/test_main.py'), 'the new-job task creates a job folder: '.$output);
    project_workspace_assert(file_get_contents($project.'/jobs/daily-sync/main.py') === $layout->jobStarter('python', 'daily-sync', '3.13')['main.py'], 'the task writes the same starter as JobSeeker');
    project_workspace_assert(strpos($output, 'http://jobseeker.local/JobCreation?project=12&folder=jobs/daily-sync') !== FALSE, 'the task prints the Job Creation link: '.$output);
    $shared = sys_get_temp_dir().'/jobseeker-project-shared-'.bin2hex(random_bytes(4));
    mkdir($shared.'/.vscode', 0777, TRUE);
    file_put_contents($shared.'/.vscode/jobseeker.sh', $layout->helperScript('python', 'http://jobseeker.local/JobCreation', 5, '3.13', FALSE));
    $sharedOutput = shell_exec('cd '.escapeshellarg($shared).' && sh .vscode/jobseeker.sh new daily 2>&1');
    project_workspace_assert(strpos($sharedOutput, 'Commit and push') === FALSE && strpos($sharedOutput, 'shared folder') !== FALSE
        && strpos($sharedOutput, 'project=5&folder=jobs/daily') !== FALSE, 'a project without Git is not told to push: '.$sharedOutput);
    file_put_contents($shared.'/.vscode/jobseeker.sh', $layout->helperScript('shell', 'http://jobseeker.local/JobCreation', 6, '3.13', FALSE));
    $shellOutput = shell_exec('cd '.escapeshellarg($shared).' && sh .vscode/jobseeker.sh new cleanup 2>&1');
    project_workspace_assert(is_file($shared.'/jobs/cleanup/run.sh') && strpos($shellOutput, 'JobCreation') === FALSE,
        'Shell jobs are not sent to Job Creation, which cannot build them from a folder yet: '.$shellOutput);
    project_workspace_remove($shared);
    file_put_contents($project.'/jobs/daily-sync/main.py', 'edited');
    shell_exec('cd '.escapeshellarg($project).' && sh .vscode/jobseeker.sh new daily-sync 2>&1');
    project_workspace_assert(file_get_contents($project.'/jobs/daily-sync/main.py') === 'edited', 'the task never overwrites a file');
    exec('cd '.escapeshellarg($project).' && sh .vscode/jobseeker.sh new "../../x" 2>&1', $unused, $code);
    project_workspace_assert(! file_exists($root.'/workspaces/x') && is_dir($project.'/jobs/x'), 'a name cannot leave jobs/');
    // `run` works from the job folder with the project root importable.
    mkdir($project.'/.venv/bin', 0777, TRUE);
    file_put_contents($project.'/.venv/bin/python', "#!/bin/sh\necho \"cwd=\$(pwd) path=\$PYTHONPATH args=\$*\"\n");
    chmod($project.'/.venv/bin/python', 0755);
    $run = shell_exec('cd '.escapeshellarg($project).' && sh .vscode/jobseeker.sh run '.escapeshellarg($project.'/jobs/load-orders/main.py').' DEV 2>&1');
    project_workspace_assert(strpos($run, 'cwd='.realpath($project).'/jobs/load-orders ') !== FALSE && strpos($run, 'path='.realpath($project).'/jobs/load-orders:'.realpath($project).' ') !== FALSE && strpos($run, ' DEV') !== FALSE, 'run starts in the job folder: '.$run);
    $test = shell_exec('cd '.escapeshellarg($project).' && sh .vscode/jobseeker.sh test '.escapeshellarg($project.'/jobs/report/tests/x.py').' 2>&1');
    project_workspace_assert(strpos($test, 'Testing jobs/report') !== FALSE && strpos($test, 'args=-m pytest') !== FALSE, 'test runs the current job only: '.$test);

    // The sample task adds a sample from the library as a new job folder.
    project_workspace_assert($layout->sampleFolder('python-task-dag') === 'task-dag' && $layout->sampleFolder('Odd Name!') === 'odd-name', 'samples go into folders named after them');
    $samples = array(
        array('id' => 'python-task-dag', 'name' => 'Task DAG', 'files' => array('main.py' => "print('dag')\n", 'pipeline/rules.py' => "RULES = 'it''s'\n", 'pyproject.toml' => "[project]\n")),
        array('id' => 'python-sdk-progress', 'name' => 'Progress', 'files' => array('main.py' => "print('progress')\n"))
    );
    file_put_contents($vscode.'/jobseeker-samples.sh', $layout->sampleScript($samples, 'http://jobseeker.local/JobCreation', 12));
    $added = shell_exec('cd '.escapeshellarg($project).' && sh .vscode/jobseeker-samples.sh python-task-dag 2>&1');
    project_workspace_assert(file_get_contents($project.'/jobs/task-dag/main.py') === "print('dag')\n" && file_get_contents($project.'/jobs/task-dag/pipeline/rules.py') === "RULES = 'it''s'\n",
        'the sample task writes every file of the sample, byte for byte: '.$added);
    project_workspace_assert(strpos($added, 'http://jobseeker.local/JobCreation?project=12&folder=jobs/task-dag') !== FALSE && strpos($added, 'Commit and push') !== FALSE, 'it prints the Job Creation link: '.$added);
    file_put_contents($project.'/jobs/task-dag/main.py', 'edited');
    exec('cd '.escapeshellarg($project).' && sh .vscode/jobseeker-samples.sh python-task-dag 2>&1', $again, $code);
    project_workspace_assert($code !== 0 && file_get_contents($project.'/jobs/task-dag/main.py') === 'edited' && strpos(implode("\n", $again), 'already exists') !== FALSE, 'a sample never replaces a folder');
    shell_exec('cd '.escapeshellarg($project).' && sh .vscode/jobseeker-samples.sh python-task-dag "Second DAG" 2>&1');
    project_workspace_assert(is_file($project.'/jobs/second-dag/main.py'), 'a sample can go into a folder of another name');
    $unknown = array();
    exec('cd '.escapeshellarg($project).' && sh .vscode/jobseeker-samples.sh nope 2>&1', $unknown, $code);
    project_workspace_assert($code === 2 && strpos(implode("\n", $unknown), 'python-sdk-progress') !== FALSE && ! is_dir($project.'/jobs/nope'), 'an unknown sample lists the samples: '.implode(' ', $unknown));
}

project_workspace_remove($root);
echo "Project workspace layout: {$checks} checks passed.\n";
