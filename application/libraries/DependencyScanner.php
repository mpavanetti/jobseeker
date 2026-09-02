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

    /** jobseeker-connector get|exec|test KEY  and  "$JOBSEEKER_CONNECTOR_HELPER" exec KEY. */
    const CONNECTOR_CLI = '/(?:jobseeker-connector|JOBSEEKER_CONNECTOR_HELPER"?)\s+(?:get|exec|test)\s+(["\']?)([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/';

    /** Explicit JOBSEEKER_CONNECTOR_KEY=KEY assignment in a shell step. */
    const CONNECTOR_ENV = '/(?<![A-Za-z0-9_])JOBSEEKER_CONNECTOR_KEY\s*=\s*(["\']?)([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/';

    /** Data asset references: js.asset("x"), js.dataset('x'), get_asset("x"). */
    const ASSET_CALL = '/(?<![A-Za-z0-9_])(?:get_)?(?:asset|dataset)\s*\(\s*(["\'])([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/';

    /** jobseeker-asset ASSET_KEY references used by shell jobs. */
    const ASSET_CLI = '/(?:^|[;&|(`]|\b(?:if|then|do)\s+)[ \t]*(?:jobseeker-asset|JOBSEEKER_ASSET_HELPER"?)\s+(["\']?)([A-Za-z0-9][A-Za-z0-9._-]{0,127})\1/m';

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

            foreach (array(self::CONNECTOR_CALL, self::CONNECTOR_CLI, self::CONNECTOR_ENV) as $pattern) {
                $this->collect($pattern, $text, $from, $connectors, TRUE);
            }
            $this->collect(self::ASSET_CALL, $text, $from, $datasets, TRUE);
            $this->collect(self::ASSET_CLI, $text, $from, $datasets, TRUE);
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
        return $sources;
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
        $scannable = array('py', 'sh', 'bash', 'ps1', 'bat', 'cmd', 'sql', 'item', 'properties', 'xml', 'txt', 'cfg', 'ini', 'toml', 'json', 'yaml', 'yml', 'java', 'groovy', 'r');

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
            if ($item->getSize() > 512 * 1024) {
                continue;
            }
            $contents = @file_get_contents($item->getPathname());
            if ($contents !== FALSE && strpos($contents, "\0") === FALSE) {
                $files[] = $contents;
            }
        }
        return $files;
    }
}
