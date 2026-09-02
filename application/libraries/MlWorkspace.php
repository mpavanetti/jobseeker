<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Owns an ML job's on-disk workspace at repository/ml/jobs/<key>/ - the same
 * kind of real, multi-file Python project that inline-Python jobs get. Writes
 * the authored files plus generated helpers (jobseeker.yml, README.md,
 * .jobseeker/datasets.py), reads them back for the builder, hashes the
 * build-relevant subset, tars it for `docker build`, and bridges to the managed
 * OpenVSCode container so "Open in Editor" works exactly like it does for
 * inline-Python jobs.
 */
class MlWorkspace
{
    const TRANSIENT_DIRS = array('.git', '.venv', '.vscode', '__pycache__', '.mypy_cache',
        '.ruff_cache', '.pytest_cache', 'build', 'dist', '.uv-cache', '.ipynb_checkpoints');

    /** @var string repository/ml root as PHP sees it */
    private $mlRoot;

    public function __construct($config = array())
    {
        $this->mlRoot = rtrim((string) (getenv('JOBSEEKER_ML_REPOSITORY_ROOT')
            ?: (rtrim(FCPATH, '/\\').'/repository/ml')), '/');
    }

    public function jobKey($value)
    {
        $value = strtolower(trim((string) $value));
        return trim(preg_replace('/[^a-z0-9._-]+/', '-', $value), '-');
    }

    /** Absolute workspace dir as PHP sees it. */
    public function dir($jobKey)
    {
        return $this->mlRoot.'/jobs/'.$this->jobKey($jobKey);
    }

    /** Same dir as the docker-runtime engine sees it (for the fallback bind mount). */
    public function bindDir($jobKey)
    {
        $bind = rtrim((string) (getenv('JOBSEEKER_ML_BIND_SOURCE') ?: $this->mlRoot), '/');
        return $bind.'/jobs/'.$this->jobKey($jobKey);
    }

    // --- write ------------------------------------------------------

    /**
     * Materialise the workspace from the authored job fields.
     *
     * @param object $job ml_job row (name, job_key, environment, runtime_key,
     *               run_type, inline_code, dependency_mode, requirements_txt,
     *               pyproject_text, dockerfile, params_json, dataset_bindings_json)
     * @param object|null $runtime ml_runtime row (for the default FROM image)
     * @return array{dir:string, hash:string, files:string[]}
     */
    public function sync($job, $runtime = NULL)
    {
        $dir = $this->dir($job->job_key);
        $this->ensureDir($dir);
        $this->ensureDir($dir.'/.jobseeker');

        $mainPy = (string) $job->inline_code;
        if (trim($mainPy) === '') {
            $mainPy = $this->defaultMainPy($job);
        }
        $this->put($dir.'/main.py', $mainPy, TRUE);

        $mode = in_array($job->dependency_mode, array('requirements', 'pyproject', 'none'), TRUE)
            ? $job->dependency_mode : 'requirements';
        if ($mode === 'pyproject') {
            $this->put($dir.'/pyproject.toml', trim((string) $job->pyproject_text) !== ''
                ? (string) $job->pyproject_text : $this->defaultPyproject($job));
            @unlink($dir.'/requirements.txt');
        } elseif ($mode === 'requirements') {
            $this->put($dir.'/requirements.txt', (string) $job->requirements_txt);
            @unlink($dir.'/pyproject.toml');
        }

        $this->put($dir.'/Dockerfile', trim((string) $job->dockerfile) !== ''
            ? (string) $job->dockerfile : $this->defaultDockerfile($job, $runtime, $mode));

        $this->put($dir.'/jobseeker.yml', $this->jobseekerYml($job, $runtime), TRUE);
        $this->put($dir.'/README.md', $this->readme($job, $runtime), TRUE);
        $this->put($dir.'/.jobseeker/datasets.py', $this->datasetsHelper($job), TRUE);
        $this->put($dir.'/.jobseeker/__init__.py', "from .datasets import *  # noqa: F401,F403\n", TRUE);
        $this->put($dir.'/.gitignore', "__pycache__/\n*.pyc\n.venv/\ndata/\n", FALSE);

        return array('dir' => $dir, 'hash' => $this->hash($job->job_key), 'files' => $this->manifest($job->job_key));
    }

    /** Read the primary authored files back (builder hydration). */
    public function read($jobKey)
    {
        $dir = $this->dir($jobKey);
        return array(
            'main_py' => $this->get($dir.'/main.py'),
            'requirements_txt' => $this->get($dir.'/requirements.txt'),
            'pyproject_text' => $this->get($dir.'/pyproject.toml'),
            'dockerfile' => $this->get($dir.'/Dockerfile'),
            'files' => $this->manifest($jobKey),
        );
    }

    public function readFile($jobKey, $relative)
    {
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        if ($relative === '' || strpos($relative, '..') !== FALSE) {
            return FALSE;
        }
        $path = $this->dir($jobKey).'/'.$relative;
        return is_file($path) ? (string) file_get_contents($path) : FALSE;
    }

    public function writeFile($jobKey, $relative, $content)
    {
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        if ($relative === '' || strpos($relative, '..') !== FALSE
            || preg_match('#(^|/)\.jobseeker(/|$)#', $relative)) {
            return FALSE;
        }
        $path = $this->dir($jobKey).'/'.$relative;
        $this->ensureDir(dirname($path));
        return @file_put_contents($path, (string) $content) !== FALSE;
    }

    public function manifest($jobKey)
    {
        $dir = $this->dir($jobKey);
        $out = array();
        $this->walk($dir, $dir, $out);
        sort($out);
        return $out;
    }

    /** Hash of the build-relevant files only (main.py, all .py, deps, Dockerfile). */
    public function hash($jobKey)
    {
        $dir = $this->dir($jobKey);
        if (! is_dir($dir)) {
            return '';
        }
        $parts = array();
        foreach ($this->manifest($jobKey) as $rel) {
            if (strpos($rel, '.jobseeker/') === 0 || $rel === 'jobseeker.yml' || $rel === 'README.md') {
                continue;
            }
            if (preg_match('/\.(py|txt|toml)$/', $rel) || basename($rel) === 'Dockerfile') {
                $parts[] = $rel.':'.hash_file('sha256', $dir.'/'.$rel);
            }
        }
        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }

    public function delete($jobKey)
    {
        $dir = $this->dir($jobKey);
        if (is_dir($dir)) {
            $this->rrmdir($dir);
        }
    }

    // --- tar (for docker build) -------------------------------------------------

    /** In-memory ustar of the workspace, excluding transient dirs. */
    public function tar($jobKey)
    {
        $dir = $this->dir($jobKey);
        $tar = '';
        foreach ($this->manifest($jobKey) as $rel) {
            $tar .= $this->tarEntry($rel, (string) file_get_contents($dir.'/'.$rel));
        }
        return $tar.str_repeat("\0", 1024);
    }

    private function tarEntry($name, $content)
    {
        $name = ltrim($name, '/');
        $header = str_pad($name, 100, "\0");
        $header .= sprintf("%07o\0", 0644);              // mode
        $header .= sprintf("%07o\0", 0);                 // uid
        $header .= sprintf("%07o\0", 0);                 // gid
        $header .= sprintf("%011o\0", strlen($content)); // size
        $header .= sprintf("%011o\0", time());           // mtime
        $header .= str_repeat(' ', 8);                   // checksum placeholder
        $header .= '0';                                  // typeflag (regular file)
        $header .= str_repeat("\0", 100);                // linkname
        $header .= "ustar\0" . '00';                     // magic + version
        $header .= str_repeat("\0", 32 + 32 + 8 + 8);    // uname, gname, devmajor, devminor
        $header .= str_repeat("\0", 155);                // prefix
        $header = str_pad($header, 512, "\0");

        $checksum = 0;
        for ($i = 0; $i < 512; $i++) {
            $checksum += ord($header[$i]);
        }
        $header = substr_replace($header, sprintf("%06o\0 ", $checksum), 148, 8);

        $padding = (512 - (strlen($content) % 512)) % 512;
        return $header.$content.str_repeat("\0", $padding);
    }

    // --- OpenVSCode bridge -------------------------------------------------

    public function editorEnabled()
    {
        $value = trim((string) getenv('JOBSEEKER_OPENVSCODE_ENABLED'));
        return $value === '' || ! in_array(strtolower($value), array('0', 'false', 'no', 'off'), TRUE);
    }

    /**
     * Inspect / optionally start the managed jobseeker-openvscode container.
     * Mirrors JobCreation::openVsCodeRuntimeState().
     * @return array{available:bool, ready:bool, starting:bool, message:string}
     */
    public function editorState($startIfStopped = FALSE)
    {
        if (! $this->editorEnabled()) {
            return array('available' => FALSE, 'ready' => FALSE, 'starting' => FALSE,
                'message' => 'OpenVSCode is disabled for this deployment.');
        }
        $monitor = trim((string) getenv('JOBSEEKER_DOCKER_MONITOR_URL')) ?: 'http://docker-monitor-proxy:8080';
        $inspect = $this->http($monitor, 'GET', '/containers/jobseeker-openvscode/json', '', 3);
        if ($inspect['status'] === 404) {
            return array('available' => FALSE, 'ready' => FALSE, 'starting' => FALSE,
                'message' => 'OpenVSCode is not installed in this deployment.');
        }
        if ($inspect['status'] < 200 || $inspect['status'] >= 300) {
            return array('available' => FALSE, 'ready' => FALSE, 'starting' => FALSE,
                'message' => 'The Docker control service is unavailable.');
        }
        $details = json_decode($inspect['body'], TRUE);
        $running = is_array($details) && ! empty($details['State']['Running']);
        $started = FALSE;
        if (! $running && $startIfStopped) {
            $start = $this->http($monitor, 'POST', '/containers/jobseeker-openvscode/start', '{}', 5);
            if (! in_array($start['status'], array(204, 304), TRUE)) {
                return array('available' => TRUE, 'ready' => FALSE, 'starting' => FALSE,
                    'message' => 'OpenVSCode is stopped and could not be started.');
            }
            $running = TRUE;
            $started = TRUE;
        }
        $ready = FALSE;
        if ($running) {
            $internal = trim((string) getenv('JOBSEEKER_OPENVSCODE_INTERNAL_URL')) ?: 'http://openvscode:3000';
            $token = trim((string) getenv('JOBSEEKER_OPENVSCODE_TOKEN'));
            $health = $this->http($internal, 'GET', '/?'.http_build_query(array('tkn' => $token), '', '&', PHP_QUERY_RFC3986), '', 2);
            $ready = $health['status'] >= 200 && $health['status'] < 400;
        }
        return array(
            'available' => TRUE,
            'ready' => $ready,
            'starting' => $running && ! $ready,
            'started' => $started,
            'message' => $ready ? 'OpenVSCode is ready.'
                : ($started ? 'OpenVSCode was started; wait for it to become ready.' : 'OpenVSCode is starting.'),
        );
    }

    /** Editor URL that opens the job's workspace folder. */
    public function editorFolderUrl($jobKey, $publicUrlBase = NULL)
    {
        $workspaceRoot = rtrim(str_replace('\\', '/', trim((string) getenv('JOBSEEKER_OPENVSCODE_WORKSPACE')) ?: '/home/workspace'), '/');
        $folder = $workspaceRoot.'/repository/ml/jobs/'.$this->jobKey($jobKey);
        $params = array();
        $token = trim((string) getenv('JOBSEEKER_OPENVSCODE_TOKEN'));
        if ($token !== '') {
            $params['tkn'] = $token;
        }
        $params['folder'] = $folder;
        $base = $publicUrlBase !== NULL ? rtrim($publicUrlBase, '/') : $this->editorPublicUrl();
        return $base.'/?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function editorPublicUrl()
    {
        $configured = trim((string) getenv('JOBSEEKER_OPENVSCODE_PUBLIC_URL'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        $proto = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        if (! empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) ?: $proto;
        }
        $host = isset($_SERVER['HTTP_HOST']) ? explode(':', trim($_SERVER['HTTP_HOST']), 2)[0] : 'localhost';
        if (! empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
        }
        $port = trim((string) getenv('JOBSEEKER_OPENVSCODE_PORT')) ?: '3000';
        $default = $proto === 'https' ? '443' : '80';
        return $proto.'://'.($host ?: 'localhost').($port === $default ? '' : ':'.$port);
    }

    // --- generated file bodies -------------------------------------------------

    private function jobseekerYml($job, $runtime)
    {
        $params = json_decode((string) $job->params_json, TRUE);
        $bindings = json_decode((string) $job->dataset_bindings_json, TRUE);
        $lines = array(
            '# Managed by JobSeeker. Edit fields in the ML Jobs builder; re-synced on save.',
            'job: '.$job->job_key,
            'name: '.$this->yamlStr($job->name),
            'environment: '.$job->environment,
            'runtime: '.$job->runtime_key,
            'run_type: '.$job->run_type,
            'entrypoint: '.($job->entrypoint ?: 'main.py'),
        );
        if (is_array($params) && $params) {
            $lines[] = 'params:';
            foreach ($params as $k => $v) {
                $lines[] = '  '.$k.': '.$this->yamlScalar($v);
            }
        }
        if (is_array($bindings) && $bindings) {
            $lines[] = 'datasets:';
            foreach ($bindings as $role => $b) {
                $asset = is_array($b) ? (isset($b['dataset_key']) ? $b['dataset_key'] : (isset($b['asset']) ? $b['asset'] : '')) : $b;
                $ver = is_array($b) && isset($b['version']) ? $b['version'] : 'latest';
                $dir = is_array($b) && isset($b['direction']) ? $b['direction'] : 'input';
                $lines[] = '  '.$role.': {asset: '.$asset.', version: '.$ver.', direction: '.$dir.'}';
            }
        }
        return implode("\n", $lines)."\n";
    }

    private function readme($job, $runtime)
    {
        $image = $runtime ? ($runtime->image_repository.':'.$runtime->image_tag) : $job->runtime_key;
        $bindings = json_decode((string) $job->dataset_bindings_json, TRUE);
        $md = "# ".$job->name."\n\n".
            "JobSeeker ML job (`".$job->run_type."`). Base runtime image: `".$image."`.\n\n".
            "## Run it\n\nEdit `main.py`, then use **Test run** in the ML Jobs builder, or the job's Jenkins job.\n".
            "JobSeeker builds `jobseeker/ml-job/".$this->jobKey($job->job_key).":<n>` from the `Dockerfile` here and runs it.\n\n".
            "## Datasets\n\n";
        if (is_array($bindings) && $bindings) {
            foreach ($bindings as $role => $b) {
                $asset = is_array($b) ? (isset($b['dataset_key']) ? $b['dataset_key'] : '') : $b;
                $md .= "- `ml.datasets.".$role.".read()` &rarr; asset **".$asset."**\n";
            }
        } else {
            $md .= "_No datasets bound. Bind one in the builder to get `ml.datasets.<role>`._\n";
        }
        $md .= "\n## SDK\n\n```python\nimport jobseeker_ml as ml\nml.log_metric(\"accuracy\", 0.9)\nml.log_model(clf, register=True)\n```\n";
        return $md;
    }

    private function datasetsHelper($job)
    {
        $bindings = json_decode((string) $job->dataset_bindings_json, TRUE);
        $body = "\"\"\"Convenience handles for this job's bound datasets.\n\n".
            "Generated by JobSeeker from jobseeker.yml. `import jobseeker_ml as ml` and use\n".
            "`ml.datasets.<role>` directly, or import these names.\n\"\"\"\n\n".
            "import jobseeker_ml as _ml\n\n";
        $names = array();
        if (is_array($bindings)) {
            foreach ($bindings as $role => $b) {
                $safe = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $role));
                if ($safe === '' || ! preg_match('/^[a-z_]/', $safe)) {
                    continue;
                }
                $body .= $safe." = _ml.datasets.".$safe."\n";
                $names[] = $safe;
            }
        }
        $body .= "\n__all__ = ".json_encode($names)."\n";
        return $body;
    }

    private function defaultDockerfile($job, $runtime, $mode)
    {
        $from = $runtime ? ($runtime->image_repository.':'.$runtime->image_tag) : 'jobseeker/ml-runtime:cpu';
        $df = "# Managed default. Edit freely - JobSeeker rebuilds the image when this or\n".
            "# main.py / requirements change.\nFROM ".$from."\nWORKDIR /app\n";
        if ($mode === 'requirements') {
            $df .= "COPY requirements.txt ./\n".
                "RUN pip install --no-cache-dir -r requirements.txt || true\n";
        } elseif ($mode === 'pyproject') {
            $df .= "COPY pyproject.toml ./\n".
                "RUN pip install --no-cache-dir . || true\n";
        }
        $df .= "COPY . /app\nCMD [\"python\", \"-u\", \"".($job->entrypoint ?: 'main.py')."\"]\n";
        return $df;
    }

    private function defaultPyproject($job)
    {
        return "[project]\nname = \"".preg_replace('/[^a-z0-9-]/', '-', strtolower((string) $job->job_key))."\"\n".
            "version = \"0.1.0\"\nrequires-python = \">=3.10\"\ndependencies = []\n";
    }

    private function defaultMainPy($job)
    {
        return "\"\"\"".$job->name." - JobSeeker ML job.\"\"\"\n\n".
            "import jobseeker_ml as ml\n\n\n".
            "def main() -> None:\n".
            "    # df = ml.datasets.training.read()\n".
            "    ml.log_metric(\"placeholder\", 1.0)\n".
            "    print(\"replace me\")\n\n\n".
            "if __name__ == \"__main__\":\n    main()\n";
    }

    // --- fs helpers -------------------------------------------------

    private function ensureDir($dir)
    {
        return is_dir($dir) || @mkdir($dir, 0775, TRUE) || is_dir($dir);
    }

    private function put($path, $content, $overwrite = TRUE)
    {
        if (! $overwrite && is_file($path)) {
            return;
        }
        $this->ensureDir(dirname($path));
        @file_put_contents($path, (string) $content);
    }

    private function get($path)
    {
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function walk($base, $dir, &$out)
    {
        foreach ((array) @scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                if (in_array($item, self::TRANSIENT_DIRS, TRUE)) {
                    continue;
                }
                $this->walk($base, $path, $out);
            } elseif (is_file($path) && filesize($path) <= 5 * 1024 * 1024) {
                $out[] = ltrim(substr($path, strlen($base) + 1), '/');
            }
        }
    }

    private function rrmdir($dir)
    {
        foreach ((array) @scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function yamlStr($v)
    {
        return '"'.str_replace('"', '\"', (string) $v).'"';
    }

    private function yamlScalar($v)
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        return $this->yamlStr($v);
    }

    private function http($base, $method, $path, $body = '', $timeout = 3)
    {
        $context = stream_context_create(array('http' => array(
            'method' => $method,
            'header' => "Content-Type: application/json\r\nConnection: close",
            'content' => $body,
            'ignore_errors' => TRUE,
            'timeout' => $timeout,
        )));
        $response = @file_get_contents(rtrim($base, '/').$path, FALSE, $context);
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                }
            }
        }
        return array('status' => $status ?: ($response === FALSE ? 502 : 200), 'body' => (string) $response);
    }
}
