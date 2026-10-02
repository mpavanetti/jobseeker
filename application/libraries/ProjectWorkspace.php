<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_PROJECT_WORKSPACE_TEST')) exit('No direct script access allowed');

/**
 * Projects are where code is developed; jobs are folders inside them.
 *
 * A project has a type (Python, Shell or Apache Hop) and, optionally, a Git
 * repository. Whatever the type, its code follows one layout, so a single
 * repository can hold every job of the project:
 *
 *   jobs/<job>/   everything one job needs: entry file, dependencies,
 *                 Dockerfile and tests. Builds run from this folder and
 *                 clone only it (plus shared/), so jobs never see each
 *                 other's files, dependencies or tests.
 *   shared/       code every job of the project may import (`shared.x`).
 *
 * Every person develops in their own working copy of a Git project
 * (repository/workspaces/u<user>/<project>-<id>), so nobody edits the files
 * someone else is editing; their changes meet through Git branches. A
 * project without Git has one folder everyone shares
 * (repository/workspaces/shared/<project>-<id>), which jobs run from directly.
 *
 * This class only knows the layout. It never runs Git and never talks to the
 * database, so it can be tested on its own (scripts/test-project-workspace.php).
 */
class ProjectWorkspace
{
    const ROOT = 'workspaces';
    const JOBS_FOLDER = 'jobs';
    const SHARED_FOLDER = 'shared';
    const MAX_JOBS = 200;

    /** Project types, in the order pickers show them. */
    public function types()
    {
        return array(
            'python' => array('label' => 'Python', 'icon' => 'fa-code', 'help' => 'Python jobs with their own dependencies, Dockerfile and tests.'),
            'shell' => array('label' => 'Shell', 'icon' => 'fa-terminal', 'help' => 'Shell scripts, one folder per job.'),
            'hop' => array('label' => 'Apache Hop', 'icon' => 'fa-random', 'help' => 'Hop workflows and pipelines, designed in the Hop GUI.')
        );
    }

    /** @return string|FALSE */
    public function cleanType($type)
    {
        $type = strtolower(trim((string) $type));
        return isset($this->types()[$type]) ? $type : FALSE;
    }

    /**
     * A job's folder inside its repository: '' is the repository root (a
     * repository that holds a single job), anything else a relative path of
     * plain names. Hidden folders and names starting with a dash are refused,
     * so the value is safe unquoted in a shell and as a Git pathspec.
     *
     * @return string|FALSE
     */
    public function cleanJobPath($path)
    {
        if (strpos((string) $path, "\0") !== FALSE) {
            return FALSE;
        }
        $path = trim(str_replace('\\', '/', (string) $path));
        while (strpos($path, './') === 0) {
            $path = substr($path, 2);
        }
        $path = trim($path, '/');
        if ($path === '' || $path === '.') {
            return '';
        }
        if (strlen($path) > 200) {
            return FALSE;
        }
        foreach (explode('/', $path) as $segment) {
            if (! preg_match('/^[A-Za-z0-9_][A-Za-z0-9._-]{0,99}$/', $segment) || $segment === '..') {
                return FALSE;
            }
        }
        return $path;
    }

    /** Lower-case name made of letters, digits, dot, dash and underscore. */
    public function slug($value, $fallback = 'project')
    {
        $slug = strtolower(trim((string) $value));
        $slug = preg_replace('/[^a-z0-9._-]+/', '-', $slug);
        $slug = preg_replace('/-{2,}/', '-', $slug);
        $slug = trim(substr($slug, 0, 60), '-._');
        return $slug === '' ? $fallback : $slug;
    }

    /** Where a new job of a project lives: jobs/<job name as a folder name>. */
    public function defaultJobPath($jobName)
    {
        return self::JOBS_FOLDER.'/'.$this->slug(str_replace('/', '-', (string) $jobName), 'job');
    }

    public function userDirectory($userId)
    {
        return 'u'.(int) $userId;
    }

    /** "customer-analytics-12": readable, and unique by the project id. */
    public function projectDirectory($projectId, $projectName)
    {
        return $this->slug($projectName).'-'.(int) $projectId;
    }

    /**
     * The workspace of a project, relative to the JobSeeker repository root.
     * Each person has their own working copy of a Git project; a project
     * without Git has a single shared folder.
     */
    public function workspaceParent($hasGit, $userId)
    {
        return self::ROOT.'/'.($hasGit ? $this->userDirectory($userId) : 'shared');
    }

    /**
     * The folder of project $projectId under $parentDirectory. A renamed
     * project keeps the folder it already has (matched by its id suffix), so
     * renaming never strands someone's uncommitted work.
     */
    public function resolveDirectory($parentDirectory, $projectId, $projectName)
    {
        $suffix = '-'.(int) $projectId;
        foreach ((array) @scandir($parentDirectory) as $entry) {
            if ($entry !== '.' && $entry !== '..' && substr($entry, -strlen($suffix)) === $suffix
                && preg_match('/^[a-z0-9._-]+$/', $entry) && is_dir(rtrim($parentDirectory, '/\\').DIRECTORY_SEPARATOR.$entry)) {
                return $entry;
            }
        }
        return $this->projectDirectory($projectId, $projectName);
    }

    /** A person's own branch, forked from the project's development branch. */
    public function personalBranch($handle)
    {
        return 'work/'.$this->slug($handle, 'me');
    }

    /** Folder name as a relative path up to the repository root, e.g. "../../..". */
    public function pathUp($relativePath)
    {
        $relativePath = trim(str_replace('\\', '/', (string) $relativePath), '/');
        return $relativePath === '' ? '.' : implode('/', array_fill(0, count(explode('/', $relativePath)), '..'));
    }

    /**
     * The job folders of a project checkout: every folder directly under
     * jobs/, with the entry files a job of the project's type could run.
     */
    public function detectJobs($root, $type)
    {
        $jobsRoot = rtrim((string) $root, '/\\').DIRECTORY_SEPARATOR.self::JOBS_FOLDER;
        if (! is_dir($jobsRoot)) {
            return array();
        }
        $names = array();
        foreach ((array) @scandir($jobsRoot) as $entry) {
            if ($entry === '.' || $entry === '..' || $this->cleanJobPath($entry) !== $entry
                || ! is_dir($jobsRoot.DIRECTORY_SEPARATOR.$entry) || is_link($jobsRoot.DIRECTORY_SEPARATOR.$entry)) {
                continue;
            }
            $names[] = $entry;
        }
        natcasesort($names);

        $jobs = array();
        foreach (array_slice(array_values($names), 0, self::MAX_JOBS) as $name) {
            $folder = $jobsRoot.DIRECTORY_SEPARATOR.$name;
            $entries = $this->entryFiles($folder, $type);
            $jobs[] = array(
                'name' => $name,
                'path' => self::JOBS_FOLDER.'/'.$name,
                'entryPoint' => empty($entries) ? '' : $entries[0],
                'entryPoints' => $entries,
                'notebooks' => array_values(array_filter($entries, function($entry) { return substr($entry, -6) === '.ipynb'; })),
                'hasPyproject' => is_file($folder.DIRECTORY_SEPARATOR.'pyproject.toml'),
                'hasRequirements' => is_file($folder.DIRECTORY_SEPARATOR.'requirements.txt'),
                'hasDockerfile' => is_file($folder.DIRECTORY_SEPARATOR.'Dockerfile'),
                'hasTests' => is_dir($folder.DIRECTORY_SEPARATOR.'tests'),
                'runtime' => is_file($folder.DIRECTORY_SEPARATOR.'Dockerfile') ? 'docker' : 'local'
            );
        }
        return $jobs;
    }

    /** Runnable files of a job folder, the conventional entry file first. */
    public function entryFiles($folder, $type)
    {
        $folder = rtrim((string) $folder, '/\\');
        if ($type === 'hop') {
            $found = array();
            $this->collectFiles($folder, '', 2, array('hwf', 'hpl'), $found);
            usort($found, function($left, $right) {
                // Workflows orchestrate pipelines, so they are the usual entry.
                $leftRank = substr($left, -4) === '.hwf' ? 0 : 1;
                $rightRank = substr($right, -4) === '.hwf' ? 0 : 1;
                return $leftRank !== $rightRank ? $leftRank - $rightRank : strnatcasecmp($left, $right);
            });
            return array_slice($found, 0, 50);
        }

        // A Python job runs a script or, top to bottom, a Jupyter notebook.
        $extensions = $type === 'shell' ? array('sh') : array('py', 'ipynb');
        $preferred = $type === 'shell' ? array('run.sh', 'main.sh', 'job.sh') : array('main.py', 'app.py', 'run.py', 'job.py', '__main__.py');
        $ignored = array('conftest.py', 'setup.py', '__init__.py', 'noxfile.py');
        $files = array();
        foreach ((array) @scandir($folder) as $entry) {
            if (is_file($folder.DIRECTORY_SEPARATOR.$entry) && in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $extensions, TRUE)
                && strpos($entry, '.') !== 0 && ! in_array($entry, $ignored, TRUE) && ! preg_match('/^test_|_test\.py$|-checkpoint\.ipynb$/', $entry)) {
                $files[] = $entry;
            }
        }
        usort($files, function($left, $right) use ($preferred) {
            // The conventional script first, other scripts, then notebooks.
            $rank = function($file) use ($preferred) {
                $position = array_search($file, $preferred, TRUE);
                return $position !== FALSE ? $position : (substr($file, -6) === '.ipynb' ? 199 : 99);
            };
            $leftRank = $rank($left);
            $rightRank = $rank($right);
            return $leftRank !== $rightRank ? $leftRank - $rightRank : strnatcasecmp($left, $right);
        });
        return array_slice($files, 0, 50);
    }

    private function collectFiles($root, $relative, $depth, $extensions, &$found)
    {
        $directory = $root.($relative === '' ? '' : DIRECTORY_SEPARATOR.$relative);
        foreach ((array) @scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0 || count($found) >= 200) {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            $child = $relative === '' ? $entry : $relative.'/'.$entry;
            if (is_dir($path) && ! is_link($path) && $depth > 0) {
                $this->collectFiles($root, $child, $depth - 1, $extensions, $found);
            } else if (is_file($path) && in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $extensions, TRUE)) {
                $found[] = $child;
            }
        }
    }

    /**
     * The layout a new project starts with. Written only into an empty
     * repository or a new shared folder, and never over an existing file.
     */
    public function scaffoldFiles($type, $projectName)
    {
        $type = $this->cleanType($type) ?: 'python';
        $label = $this->types()[$type]['label'];
        $files = array(
            'README.md' => implode("\n", array(
                '# '.trim((string) $projectName),
                '',
                'A JobSeeker '.$label.' project. Each job is a folder under `jobs/`:',
                '',
                '```',
                'jobs/',
                '  <job>/        '.($type === 'python' ? 'main.py, pyproject.toml, tests/ and an optional Dockerfile' : ($type === 'shell' ? 'run.sh and anything it needs' : 'the Hop workflows and pipelines of one job')),
                'shared/         code every job of this project can use',
                '```',
                '',
                'Builds run a job from its own folder and fetch only that folder and `shared/`,',
                'so jobs never pick up each other\'s files, dependencies or tests.',
                '',
                'In VS Code, run the task **JobSeeker: new job** to add a job folder, then create',
                'the job in JobSeeker from it (Job Creation lists the folders it finds here).',
                ''
            )),
            self::JOBS_FOLDER.'/README.md' => "# Jobs\n\nOne folder per JobSeeker job. The folder name is the job's default name.\n"
        );
        if ($type === 'python') {
            $files[self::SHARED_FOLDER.'/__init__.py'] = '"""Code shared by the jobs of this project: `from shared import ...`."""'."\n";
            $files['.gitignore'] = implode("\n", array('__pycache__/', '*.py[cod]', '.venv/', '.pytest_cache/', '.mypy_cache/', '.ruff_cache/',
                '.coverage', 'coverage.xml', 'htmlcov/', 'build/', 'dist/', '*.egg-info/', '.env', '.env.*', '!.env.example', ''));
        } else if ($type === 'shell') {
            $files[self::SHARED_FOLDER.'/common.sh'] = implode("\n", array(
                '# Helpers shared by the jobs of this project.',
                '# Source it from a job: . "$(dirname "$0")/../../shared/common.sh"',
                'log() { printf \'[%s] %s\\n\' "$(date +%H:%M:%S)" "$*"; }',
                ''
            ));
        }
        return $files;
    }

    /**
     * The files of a new job folder. __JOBSEEKER_JOB__ stands for the job's
     * folder name, so the same templates serve JobSeeker and the "new job"
     * task inside VS Code.
     */
    public function jobStarterFiles($type, $pythonVersion = '3.13')
    {
        $type = $this->cleanType($type) ?: 'python';
        if ($type === 'shell') {
            return array('run.sh' => implode("\n", array(
                '#!/bin/sh',
                '# __JOBSEEKER_JOB__: a JobSeeker shell job. $1 is the environment (DEV, QA, ...).',
                'set -eu',
                'ENVIRONMENT="${1:-${JOBSEEKER_ENVIRONMENT:-LOCAL}}"',
                'echo "__JOBSEEKER_JOB__ running in $ENVIRONMENT"',
                ''
            )));
        }
        if ($type === 'hop') {
            return array('README.md' => "# __JOBSEEKER_JOB__\n\nSave this job's Hop workflow (`main.hwf`) and pipelines in this folder from the Hop GUI.\n");
        }
        $pythonVersion = preg_match('/^3\.[0-9]{1,2}$/', (string) $pythonVersion) ? (string) $pythonVersion : '3.13';
        return array(
            'main.py' => implode("\n", array(
                '"""__JOBSEEKER_JOB__: a JobSeeker job.',
                '',
                'Builds run this folder. Code shared by several jobs of the project belongs',
                'in the project\'s shared/ folder: `from shared import ...`.',
                '"""',
                '',
                'import os',
                'import sys',
                '',
                'from jobseeker import JobSeeker',
                '',
                'JOB_NAME = os.getenv("JOBSEEKER_JOB_NAME") or "__JOBSEEKER_JOB__"',
                'ENVIRONMENT = sys.argv[1] if len(sys.argv) > 1 else "LOCAL"',
                '',
                '',
                'def transform(rows: list[dict]) -> list[dict]:',
                '    """The job\'s logic, free of I/O so tests can call it directly."""',
                '    return [dict(row, processed=True) for row in rows]',
                '',
                '',
                'def main() -> None:',
                '    with (',
                '        JobSeeker(environment=ENVIRONMENT, job=JOB_NAME) as js,',
                '        js.task("__JOBSEEKER_JOB__", "DW_Master") as tmf,',
                '    ):',
                '        count = tmf.context("rows", cast=int, default=3)',
                '        rows = transform([{"id": index} for index in range(count)])',
                '        print(f"{JOB_NAME} processed {len(rows)} rows in {ENVIRONMENT}")',
                '',
                '',
                'if __name__ == "__main__":',
                '    main()',
                ''
            )),
            'pyproject.toml' => implode("\n", array(
                '[project]',
                'name = "__JOBSEEKER_JOB__"',
                'version = "0.1.0"',
                'description = "JobSeeker job __JOBSEEKER_JOB__"',
                'requires-python = ">='.$pythonVersion.',<4.0"',
                'dependencies = []',
                '',
                '[tool.poetry]',
                'package-mode = false',
                '',
                '[tool.poetry.group.dev.dependencies]',
                'pytest = ">=8.0,<10.0"',
                '',
                '[tool.pytest.ini_options]',
                'testpaths = ["tests"]',
                'addopts = "-ra"',
                '',
                '[tool.ruff]',
                'line-length = 100',
                ''
            )),
            'tests/test_main.py' => implode("\n", array(
                'from main import transform',
                '',
                '',
                'def test_transform_marks_every_row_processed() -> None:',
                '    assert transform([{"id": 1}]) == [{"id": 1, "processed": True}]',
                ''
            ))
        );
    }

    /** Starter files with the job's folder name filled in. */
    public function jobStarter($type, $jobFolderName, $pythonVersion = '3.13')
    {
        $name = $this->slug($jobFolderName, 'job');
        $files = array();
        foreach ($this->jobStarterFiles($type, $pythonVersion) as $path => $content) {
            $files[$path] = str_replace('__JOBSEEKER_JOB__', $name, $content);
        }
        return $files;
    }

    /**
     * .vscode/jobseeker.sh: runs, tests and creates the project's jobs from
     * VS Code tasks. `run` and `test` work from the jobs/<job> folder that
     * holds the current file, with the project root importable, exactly as a
     * build runs the job. `new` adds a job folder from the starter templates
     * and prints the Job Creation link that turns it into a JobSeeker job.
     */
    public function helperScript($type, $jobCreationUrl, $projectId, $pythonVersion = '3.13', $hasGit = TRUE)
    {
        $type = $this->cleanType($type) ?: 'python';
        $writes = array();
        foreach ($this->jobStarterFiles($type, $pythonVersion) as $path => $content) {
            $writes[] = '  jobseeker_write '.escapeshellarg($path).' '.escapeshellarg(base64_encode($content));
        }
        $runLine = $type === 'python'
            ? '    PYTHONPATH="$dir:$root" exec "$root/.venv/bin/python" -u "$file" "$@"'
            : ($type === 'shell' ? '    exec sh "$file" "$@"' : '    echo "Hop files run from the Apache Hop page or a Hop job." >&2; exit 2');
        $testLine = $type === 'python'
            ? '    PYTHONPATH="$dir:$root" exec "$root/.venv/bin/python" -m pytest'
            : '    echo "Only Python projects have a test runner." >&2; exit 2';
        // What to do with a new folder: builds run what a Git project has
        // pushed, a shared folder in place; only Python folders become jobs
        // from Job Creation so far.
        $link = rtrim((string) $jobCreationUrl, '?&').(strpos((string) $jobCreationUrl, '?') === FALSE ? '?' : '&').'project='.(int) $projectId.'&folder='.self::JOBS_FOLDER.'/';
        if ($type === 'python') {
            $nextSteps = array(
                '    echo',
                '    echo '.escapeshellarg($hasGit ? 'Commit and push it, then create the job in JobSeeker:' : 'Jobs run this shared folder as it is. Create the job in JobSeeker:'),
                '    echo '.escapeshellarg($link).'"$name"'
            );
        } else {
            $nextSteps = array(
                '    echo',
                '    echo '.escapeshellarg($type === 'shell' ? 'Run it with the task JobSeeker: run current job script.' : 'Design its workflow in the Apache Hop GUI and save it in this folder.'),
                $hasGit ? '    echo "Commit and push it to share it with the team."' : '    echo "Everyone who opens this project sees it: the folder is shared."'
            );
        }
        return implode("\n", array_merge(array(
            '#!/bin/sh',
            '# Generated by JobSeeker each time the project is opened; do not edit.',
            '# Usage: sh .vscode/jobseeker.sh run FILE | test FILE | new JOB',
            'set -eu',
            'root="$(cd "$(dirname "$0")/.." && pwd)"',
            '# The jobs/<job> folder that holds a file, else the project root.',
            'job_dir() {',
            '  case "$1" in',
            '    "$root"/'.self::JOBS_FOLDER.'/*/*) rest="${1#"$root"/'.self::JOBS_FOLDER.'/}"; printf \'%s/'.self::JOBS_FOLDER.'/%s\\n\' "$root" "${rest%%/*}" ;;',
            '    *) printf \'%s\\n\' "$root" ;;',
            '  esac',
            '}',
            'jobseeker_write() {',
            '  target="$job/$1"',
            '  [ -e "$target" ] && return 0',
            '  mkdir -p "$(dirname "$target")"',
            '  printf \'%s\' "$2" | base64 -d | sed "s/__JOBSEEKER_JOB__/$name/g" > "$target"',
            '  echo "  created ${target#"$root"/}"',
            '}',
            'command_name="${1:-}"',
            '[ "$#" -gt 0 ] && shift',
            'case "$command_name" in',
            '  run)',
            '    file="$1"; shift',
            '    dir="$(job_dir "$file")"',
            '    cd "$dir"',
            $runLine,
            '    ;;',
            '  test)',
            '    dir="$(job_dir "${1:-$root/x}")"',
            '    cd "$dir"',
            '    echo "Testing ${dir#"$root"/}"',
            $testLine,
            '    ;;',
            '  new)',
            '    name="$(printf \'%s\' "${1:-}" | tr \'[:upper:]\' \'[:lower:]\' | sed \'s/[^a-z0-9._-]/-/g; s/^[-._]*//; s/[-._]*$//\')"',
            '    [ -n "$name" ] || { echo "Give the new job a name, such as load-orders." >&2; exit 2; }',
            '    job="$root/'.self::JOBS_FOLDER.'/$name"',
            '    echo "New job folder '.self::JOBS_FOLDER.'/$name"'
        ), $writes, $nextSteps, array(
            '    ;;',
            '  *)',
            '    echo "Usage: sh .vscode/jobseeker.sh run FILE | test FILE | new JOB" >&2',
            '    exit 2',
            '    ;;',
            'esac',
            ''
        )));
    }

    /** The job folder a sample goes into: "python-task-dag" becomes "task-dag". */
    public function sampleFolder($sampleId)
    {
        return $this->slug(preg_replace('/^python-/', '', (string) $sampleId), 'sample');
    }

    /**
     * .vscode/jobseeker-samples.sh: adds a sample from JobSeeker's library as
     * a new job folder, jobs/<folder>, never over an existing one. The task
     * "JobSeeker: add sample" runs it; the samples travel inside the script,
     * so it works in any editor without reaching JobSeeker.
     *
     * @param array $samples list of id, name, files (path => content)
     */
    public function sampleScript(array $samples, $jobCreationUrl, $projectId, $hasGit = TRUE)
    {
        $folders = array();
        $writes = array();
        foreach ($samples as $sample) {
            $id = escapeshellarg((string) $sample['id']);
            $folders[] = '  '.$id.') folder="${2:-'.$this->sampleFolder($sample['id']).'}"; title='.escapeshellarg((string) $sample['name']).' ;;';
            $writes[] = '  '.$id.')';
            foreach ($sample['files'] as $path => $content) {
                $writes[] = '    jobseeker_write '.escapeshellarg((string) $path).' '.escapeshellarg(base64_encode((string) $content));
            }
            $writes[] = '    ;;';
        }
        $link = rtrim((string) $jobCreationUrl, '?&').(strpos((string) $jobCreationUrl, '?') === FALSE ? '?' : '&').'project='.(int) $projectId.'&folder='.self::JOBS_FOLDER.'/';
        return implode("\n", array_merge(array(
            '#!/bin/sh',
            '# Generated by JobSeeker each time the project is opened; do not edit.',
            '# Usage: sh .vscode/jobseeker-samples.sh SAMPLE [FOLDER]',
            'set -eu',
            'root="$(cd "$(dirname "$0")/.." && pwd)"',
            'sample="${1:-}"',
            'case "$sample" in'
        ), $folders, array(
            '  *)',
            '    echo "Choose a sample: sh .vscode/jobseeker-samples.sh SAMPLE [FOLDER]. Samples:" >&2',
            '    sed -n "s/^  \'\\([a-z0-9-]*\\)\') folder=.*/  \\1/p" "$0" >&2',
            '    exit 2',
            '    ;;',
            'esac',
            'folder="$(printf \'%s\' "$folder" | tr \'[:upper:]\' \'[:lower:]\' | sed \'s/[^a-z0-9._-]/-/g; s/^[-._]*//; s/[-._]*$//\')"',
            '[ -n "$folder" ] || { echo "Give the sample a folder name, such as orders-dag." >&2; exit 2; }',
            'job="$root/'.self::JOBS_FOLDER.'/$folder"',
            'if [ -e "$job" ]; then',
            '  echo "'.self::JOBS_FOLDER.'/$folder already exists. Add the sample under another name: sh .vscode/jobseeker-samples.sh $sample FOLDER" >&2',
            '  exit 1',
            'fi',
            'jobseeker_write() {',
            '  target="$job/$1"',
            '  mkdir -p "$(dirname "$target")"',
            '  printf \'%s\' "$2" | base64 -d > "$target"',
            '  echo "  created ${target#"$root"/}"',
            '}',
            'echo "Sample \\"$title\\" in '.self::JOBS_FOLDER.'/$folder"',
            'case "$sample" in'
        ), $writes, array(
            'esac',
            'if [ -s "$job/requirements.txt" ] && [ -f "$root/.vscode/bootstrap-python.sh" ]; then',
            '  echo',
            '  echo "Installing its dependencies into .venv"',
            '  sh "$root/.vscode/bootstrap-python.sh" || echo "Run the task JobSeeker: setup Python environment to install them." >&2',
            'fi',
            'echo',
            'echo "Run it with the task JobSeeker: run current job file (open its main.py first)."',
            'echo '.escapeshellarg($hasGit ? 'Commit and push it, then create the job in JobSeeker:' : 'Jobs run this shared folder as it is. Create the job in JobSeeker:'),
            'echo '.escapeshellarg($link).'"$folder"',
            ''
        )));
    }
}
