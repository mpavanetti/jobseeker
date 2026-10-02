<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Static text analysis of job source and generated Jenkins commands to discover
 * which JobSeeker connectors and data assets a job references. No code is
 * executed. Results feed the job dependency map shown in Job Creation, Job View
 * and Job Execution.
 */
class DependencyScanner
{
    /** Connector key references: js.connector("x"), get_connector('x'), self.connector("x"). */
    const CONNECTOR_CALL = '/(?<![A-Za-z0-9_])(?:get_)?connector\s*\(\s*(["\'])([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/';

    /** The same Python calls with a simple variable instead of a literal. */
    const CONNECTOR_VARIABLE_CALL = '/(?<![A-Za-z0-9_])(?:get_)?connector\s*\(\s*([A-Za-z_][A-Za-z0-9_]*)\b/';

    /** jobseeker-connector get|exec|test KEY  and  "$JOBSEEKER_CONNECTOR_HELPER" exec KEY. */
    const CONNECTOR_CLI = '/(?:jobseeker-connector|JOBSEEKER_CONNECTOR_HELPER"?)\s+(?:get|exec|test)\s+(["\']?)([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/';

    /** The same shell calls with "$variable" or "${variable}" instead of a literal. */
    const CONNECTOR_CLI_VARIABLE = '/(?:jobseeker-connector|JOBSEEKER_CONNECTOR_HELPER"?)\s+(?:get|exec|test)\s+"?\$\{?([A-Za-z_][A-Za-z0-9_]*)\}?"?/';

    /** Explicit JOBSEEKER_CONNECTOR_KEY=KEY assignment in a shell step. */
    const CONNECTOR_ENV = '/(?<![A-Za-z0-9_])JOBSEEKER_CONNECTOR_KEY\s*=\s*(["\']?)([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/';

    /** Git source credential selected by a generated JobSeeker shell step. */
    const GIT_CONNECTOR_ENV = '/(?<![A-Za-z0-9_])JOBSEEKER_GIT_CREDENTIAL_KEY\s*=\s*(["\']?)([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/';

    /** Data asset references: js.asset("x"), js.dataset('x'), get_asset("x"). */
    const ASSET_CALL = '/(?<![A-Za-z0-9_])(?:get_)?(?:asset|dataset)\s*\(\s*(["\'])([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/';

    /** The same Python calls with a simple variable instead of a literal. */
    const ASSET_VARIABLE_CALL = '/(?<![A-Za-z0-9_])(?:get_)?(?:asset|dataset)\s*\(\s*([A-Za-z_][A-Za-z0-9_]*)\b/';

    /** jobseeker-asset ASSET_KEY references used by shell jobs. */
    const ASSET_CLI = '/(?:^|[;&|(`]|\b(?:if|then|do)\s+)[ \t]*(?:jobseeker-asset|JOBSEEKER_ASSET_HELPER"?)\s+(["\']?)([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/m';

    /** The same shell calls with "$variable" or "${variable}" instead of a literal. */
    const ASSET_CLI_VARIABLE = '/(?:^|[;&|(`]|\b(?:if|then|do)\s+)[ \t]*(?:jobseeker-asset|JOBSEEKER_ASSET_HELPER"?)\s+"?\$\{?([A-Za-z_][A-Za-z0-9_]*)\}?"?/m';

    /** jobseeker://<environment>/<asset-key>[/...] runtime URIs. */
    const ASSET_URI = '#jobseeker://[A-Za-z0-9._-]+/([A-Za-z0-9][A-Za-z0-9._-]{0,127})#';

    /**
     * @param array $sources list of ['text' => string, 'from' => 'code'|'command'|'env']
     * @return array ['connectors' => [key => ['from' => [...]]], 'datasets' => [key => ['from' => [...]]]]
     */
    public function scan(array $sources)
    {
        $connectors = array();
        $datasets = array();

        foreach ($sources as $source) {
            $text = isset($source['text']) ? (string) $source['text'] : '';
            $from = isset($source['from']) ? (string) $source['from'] : 'code';
            if ($text === '') {
                continue;
            }

            foreach (array(self::CONNECTOR_CALL, self::CONNECTOR_CLI, self::CONNECTOR_ENV, self::GIT_CONNECTOR_ENV) as $pattern) {
                $this->collect($pattern, $text, $from, $connectors, TRUE);
            }
            $this->collectPythonVariableDefaults(self::CONNECTOR_VARIABLE_CALL, $text, $from, $connectors);
            $this->collectShellVariableDefaults(self::CONNECTOR_CLI_VARIABLE, $text, $from, $connectors);
            $this->collect(self::ASSET_CALL, $text, $from, $datasets, TRUE);
            $this->collectPythonVariableDefaults(self::ASSET_VARIABLE_CALL, $text, $from, $datasets);
            $this->collect(self::ASSET_CLI, $text, $from, $datasets, TRUE);
            $this->collectShellVariableDefaults(self::ASSET_CLI_VARIABLE, $text, $from, $datasets);
            $this->collect(self::ASSET_URI, $text, $from, $datasets, FALSE);
        }

        return array('connectors' => $connectors, 'datasets' => $datasets);
    }

    /**
     * Build the source bundle for a job from its generated Jenkins command plus
     * every readable file in its repository source directory.
     */
    public function sourcesForJob($command, $sourceDirectory)
    {
        $sources = array();
        if (trim((string) $command) !== '') {
            $sources[] = array('text' => (string) $command, 'from' => 'command');
        }
        foreach ($this->readSourceFiles($sourceDirectory) as $contents) {
            $sources[] = array('text' => $contents, 'from' => 'code');
        }
        $hopManifest = $this->hopManifestSource($sourceDirectory);
        if ($hopManifest !== NULL) {
            $sources[] = $hopManifest;
        }
        return $sources;
    }

    /**
     * An Apache Hop project declares the connectors and Data Assets it uses
     * in .jobseeker-hop.json (HopProject); its .hpl/.hwf files only carry
     * Hop's own connection names. Present that declaration in the call form
     * the patterns above already read.
     */
    private function hopManifestSource($directory)
    {
        $path = rtrim((string) $directory, '/\\').DIRECTORY_SEPARATOR.'.jobseeker-hop.json';
        if ((string) $directory === '' || ! is_file($path) || is_link($path) || filesize($path) > 256 * 1024) {
            return NULL;
        }
        $manifest = json_decode((string) @file_get_contents($path), TRUE);
        if (! is_array($manifest)) {
            return NULL;
        }
        $calls = array();
        foreach (array('connectors' => 'connector', 'assets' => 'asset') as $field => $call) {
            foreach (isset($manifest[$field]) && is_array($manifest[$field]) ? $manifest[$field] : array() as $key) {
                if (is_string($key) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $key)) {
                    $calls[] = $call.'("'.$key.'")';
                }
            }
        }
        return empty($calls) ? NULL : array('text' => implode("\n", $calls), 'from' => 'hop');
    }

    public function keys(array $scan, $kind)
    {
        return array_keys(isset($scan[$kind]) ? $scan[$kind] : array());
    }

    private function collect($pattern, $text, $from, &$bucket, $hasQuoteGroup)
    {
        if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return;
        }
        foreach ($matches as $match) {
            $raw = $hasQuoteGroup ? (isset($match[2]) ? $match[2] : '') : (isset($match[1]) ? $match[1] : '');
            $key = $this->normalizeKey($raw);
            if ($key === '' || strlen($key) > 128) {
                continue;
            }
            if (! isset($bucket[$key])) {
                $bucket[$key] = array('from' => array());
            }
            if (! in_array($from, $bucket[$key]['from'], TRUE)) {
                $bucket[$key]['from'][] = $from;
            }
        }
    }

    /**
     * Resolve the intentionally small, static subset used by configurable
     * Python jobs, for example:
     *   CONNECTOR_KEY = os.getenv("JOBSEEKER_DB_CONNECTOR", "jobseeker-mariadb")
     *   js.connector(CONNECTOR_KEY)
     *
     * No Python is executed and arbitrary expressions are ignored. A variable
     * is accepted only when it is passed to the matching API and assigned a
     * literal value or an os.getenv/os.environ.get literal default.
     */
    private function collectPythonVariableDefaults($callPattern, $text, $from, &$bucket)
    {
        if (! preg_match_all($callPattern, $text, $calls, PREG_SET_ORDER)) {
            return;
        }

        foreach ($calls as $call) {
            $variable = isset($call[1]) ? (string) $call[1] : '';
            if ($variable === '') {
                continue;
            }
            $name = preg_quote($variable, '/');
            $annotation = '(?:\s*:[^=\r\n]+)?';
            $literal = '/^\s*'.$name.$annotation.'\s*=\s*(["\'])([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1\s*(?:#.*)?$/m';
            $environmentDefault = '/^\s*'.$name.$annotation.'\s*=\s*os\.(?:getenv|environ\.get)\s*\(\s*(["\'])[^"\'\r\n]+\1\s*,\s*(["\'])([A-Za-z0-9][A-Za-z0-9._-]{0,127})\2\s*\)\s*(?:#.*)?$/m';
            $raw = '';
            if (preg_match($literal, $text, $assignment)) {
                $raw = isset($assignment[2]) ? $assignment[2] : '';
            } else if (preg_match($environmentDefault, $text, $assignment)) {
                $raw = isset($assignment[3]) ? $assignment[3] : '';
            }
            $key = $this->normalizeKey($raw);
            if ($key === '') {
                continue;
            }
            if (! isset($bucket[$key])) {
                $bucket[$key] = array('from' => array());
            }
            if (! in_array($from, $bucket[$key]['from'], TRUE)) {
                $bucket[$key]['from'][] = $from;
            }
        }
    }

    /**
     * The shell counterpart, for the samples' configurable form:
     *   connector_key="${JOBSEEKER_DB_CONNECTOR:-jobseeker-mariadb}"
     *   jobseeker-connector test "$connector_key"
     * Only a literal assignment or a ${NAME:-literal} default is accepted.
     */
    private function collectShellVariableDefaults($callPattern, $text, $from, &$bucket)
    {
        if (! preg_match_all($callPattern, $text, $calls, PREG_SET_ORDER)) {
            return;
        }

        foreach ($calls as $call) {
            $name = preg_quote((string) $call[1], '/');
            $prefix = '/^\s*(?:export\s+|local\s+|readonly\s+)?'.$name.'=';
            $withDefault = $prefix.'(["\']?)\$\{[A-Za-z_][A-Za-z0-9_]*:?-([A-Za-z0-9][A-Za-z0-9._-]{0,127})\}\1\s*(?:#.*)?$/m';
            $literal = $prefix.'(["\']?)([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1\s*(?:#.*)?$/m';
            $raw = '';
            if (preg_match($withDefault, $text, $assignment) || preg_match($literal, $text, $assignment)) {
                $raw = $assignment[2];
            }
            $key = $this->normalizeKey($raw);
            if ($key === '') {
                continue;
            }
            if (! isset($bucket[$key])) {
                $bucket[$key] = array('from' => array());
            }
            if (! in_array($from, $bucket[$key]['from'], TRUE)) {
                $bucket[$key]['from'][] = $from;
            }
        }
    }

    private function normalizeKey($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string) $value, '-');
    }

    private function readSourceFiles($directory)
    {
        $directory = (string) $directory;
        if ($directory === '' || ! is_dir($directory) || is_link($directory)) {
            return array();
        }
        $transient = array('.git', '.venv', 'venv', '.vscode', '.uv-cache', '.jobseeker-wheels', '.jobseeker-python-libs', '__pycache__', '.mypy_cache', '.ruff_cache', '.pytest_cache', 'node_modules', 'htmlcov', 'build', 'dist');
        $scannable = array('py', 'ipynb', 'sh', 'bash', 'ps1', 'bat', 'cmd', 'sql', 'item', 'properties', 'xml', 'txt', 'cfg', 'ini', 'toml', 'json', 'yaml', 'yml', 'java', 'groovy', 'r');

        $root = rtrim($directory, DIRECTORY_SEPARATOR);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        $files = array();
        $budget = 400;
        foreach ($iterator as $item) {
            if ($budget-- <= 0) {
                break;
            }
            if ($item->isLink() || ! $item->isFile()) {
                continue;
            }
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $lowerRelative = strtolower(str_replace('\\', '/', $relative));
            foreach (explode('/', $lowerRelative) as $segment) {
                if (in_array($segment, $transient, TRUE)) {
                    continue 2;
                }
            }
            $extension = strtolower(pathinfo($item->getPathname(), PATHINFO_EXTENSION));
            if ($extension !== '' && ! in_array($extension, $scannable, TRUE)) {
                continue;
            }
            // A notebook carries its outputs (images) too, so it may be larger.
            if ($item->getSize() > ($extension === 'ipynb' ? 8 * 1024 * 1024 : 512 * 1024)) {
                continue;
            }
            $contents = @file_get_contents($item->getPathname());
            if ($contents !== FALSE && $extension === 'ipynb') {
                $contents = $this->notebookCode($contents);
            }
            if ($contents !== FALSE && strpos($contents, "\0") === FALSE) {
                $files[] = $contents;
            }
        }
        return $files;
    }

    /** The code cells of a notebook, as the source a script would have. */
    private function notebookCode($json)
    {
        $notebook = json_decode($json, TRUE);
        if (! is_array($notebook) || ! isset($notebook['cells']) || ! is_array($notebook['cells'])) {
            return FALSE;
        }
        $code = array();
        foreach ($notebook['cells'] as $cell) {
            if (is_array($cell) && isset($cell['cell_type'], $cell['source']) && $cell['cell_type'] === 'code') {
                $code[] = is_array($cell['source']) ? implode('', $cell['source']) : (string) $cell['source'];
            }
        }
        return implode("\n\n", $code);
    }
}
