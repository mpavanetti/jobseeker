<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_HOP_TEST')) exit('No direct script access allowed');

/**
 * Apache Hop project inspection, scaffolding and validation.
 *
 * The filesystem stays authoritative: a project is a folder under
 * repository/hop/projects that Apache Hop itself recognises. This library never
 * parses .hpl/.hwf content - it only reads the descriptors Hop owns
 * (project-config.json) and the descriptor JobSeeker owns
 * (.jobseeker-hop.json), so a project exported from the Hop GUI drops in
 * unchanged, and a project JobSeeker created opens in the Hop GUI unchanged.
 *
 * The same contract is implemented for the runtime in the Python SDK
 * (jobseeker.hop). Keep the two in step; scripts/test-hop-integration.js
 * asserts they agree.
 */
class HopProject
{
    const MANIFEST_NAME = '.jobseeker-hop.json';
    const PROJECT_CONFIG_NAME = 'project-config.json';
    const SCHEMA_VERSION = 1;

    const DEFAULT_RUN_CONFIG = 'local';
    const DEFAULT_ENGINE = 'container';
    const DEFAULT_LOG_LEVEL = 'Basic';

    private $engines = array('container', 'server');
    private $logLevels = array('Nothing', 'Error', 'Minimal', 'Basic', 'Detailed', 'Debug', 'Rowlevel');

    /** Maximum files walked when listing a project, so a pathological upload cannot stall a page. */
    private $maxScannedEntries = 4000;

    public function engines()
    {
        return $this->engines;
    }

    public function logLevels()
    {
        return $this->logLevels;
    }

    public function cleanEngine($engine)
    {
        $engine = strtolower(trim((string) $engine));
        return in_array($engine, $this->engines, TRUE) ? $engine : self::DEFAULT_ENGINE;
    }

    public function cleanLogLevel($level)
    {
        $level = trim((string) $level);
        foreach ($this->logLevels as $candidate) {
            if (strcasecmp($candidate, $level) === 0) {
                return $candidate;
            }
        }
        return self::DEFAULT_LOG_LEVEL;
    }

    public function cleanRunConfig($runConfig)
    {
        $runConfig = trim((string) $runConfig);
        if ($runConfig === '' || strlen($runConfig) > 100 || ! preg_match('/^[A-Za-z0-9._ -]+$/', $runConfig)) {
            return self::DEFAULT_RUN_CONFIG;
        }
        return $runConfig;
    }

    public function cleanProjectKey($key)
    {
        $key = strtolower(trim((string) $key));
        $key = preg_replace('/[^a-z0-9._-]+/', '-', $key);
        $key = trim((string) $key, '-.');
        return $key === '' || strlen($key) > 100 ? FALSE : $key;
    }

    /**
     * Parse the "NAME=VALUE" lines the Job Creation form collects.
     * Returns FALSE with the offending line so the UI can be precise.
     */
    public function parseParameters($text)
    {
        $parameters = array();
        $lines = preg_split('/[\r\n]+/', (string) $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $position = strpos($line, '=');
            if ($position === FALSE || $position === 0) {
                return array('ok' => FALSE, 'message' => 'Hop parameters must be written as NAME=VALUE, one per line. Invalid line: '.$line);
            }
            $name = trim(substr($line, 0, $position));
            $value = substr($line, $position + 1);
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,99}$/', $name)) {
                return array('ok' => FALSE, 'message' => 'Hop parameter names must start with a letter or underscore and contain only letters, numbers and underscores: '.$name);
            }
            if (strlen($value) > 2000) {
                return array('ok' => FALSE, 'message' => 'Hop parameter '.$name.' is longer than 2000 characters.');
            }
            $parameters[$name] = $value;
            if (count($parameters) > 100) {
                return array('ok' => FALSE, 'message' => 'A Hop job cannot declare more than 100 parameters.');
            }
        }

        return array('ok' => TRUE, 'parameters' => $parameters);
    }

    public function formatParameters($parameters)
    {
        $lines = array();
        foreach ((array) $parameters as $name => $value) {
            $lines[] = $name.'='.$value;
        }
        return implode("\n", $lines);
    }

    // -- locations ---------------------------------------------------------

    public function projectsRoot($repositoryRoot)
    {
        return rtrim((string) $repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'hop'.DIRECTORY_SEPARATOR.'projects';
    }

    public function projectPath($repositoryRoot, $projectKey)
    {
        $projectKey = $this->cleanProjectKey($projectKey);
        if ($projectKey === FALSE) {
            return FALSE;
        }
        return $this->projectsRoot($repositoryRoot).DIRECTORY_SEPARATOR.$projectKey;
    }

    public function isProject($path)
    {
        return ! is_link($path)
            && is_dir($path)
            && is_file(rtrim((string) $path, '/\\').DIRECTORY_SEPARATOR.self::PROJECT_CONFIG_NAME);
    }

    public function listProjects($repositoryRoot)
    {
        $root = $this->projectsRoot($repositoryRoot);
        if (! is_dir($root)) {
            return array();
        }

        $projects = array();
        foreach ((array) scandir($root) as $entry) {
            if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0) {
                continue;
            }
            $path = $root.DIRECTORY_SEPARATOR.$entry;
            if (is_link($path) || ! is_dir($path)) {
                continue;
            }
            $projects[] = $this->describe($path);
        }

        usort($projects, function($left, $right) {
            return strcasecmp($left['key'], $right['key']);
        });

        return $projects;
    }

    /**
     * An uploaded archive routinely wraps everything in one folder. Resolve the
     * real project root so "upload the zip you exported" just works.
     */
    public function locate($path)
    {
        $path = rtrim((string) $path, '/\\');
        if ($path === '' || is_link($path) || ! is_dir($path)) {
            return FALSE;
        }
        if ($this->isProject($path)) {
            return $path;
        }

        foreach ((array) scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $nested = $path.DIRECTORY_SEPARATOR.$entry;
            if ($this->isProject($nested)) {
                return $nested;
            }
        }

        // A folder of loose .hpl/.hwf files is still a usable project once the
        // descriptor Hop needs is written for it.
        if ($this->entryFiles($path)) {
            return $path;
        }

        foreach ((array) scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $nested = $path.DIRECTORY_SEPARATOR.$entry;
            if (! is_link($nested) && is_dir($nested) && $this->entryFiles($nested)) {
                return $nested;
            }
        }

        return FALSE;
    }

    // -- contents ----------------------------------------------------------

    private function collect($root, $extension)
    {
        $results = array();
        if (is_link($root) || ! is_dir($root)) {
            return $results;
        }

        $skip = array('.git', 'metadata', 'datasets', 'audit', 'node_modules', '__pycache__');
        $scanned = 0;
        $stack = array('');
        while ($stack) {
            $relativeDirectory = array_pop($stack);
            $absoluteDirectory = $relativeDirectory === '' ? $root : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
            $entries = @scandir($absoluteDirectory);
            if ($entries === FALSE) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0) {
                    continue;
                }
                if (++$scanned > $this->maxScannedEntries) {
                    break 2;
                }
                $relative = $relativeDirectory === '' ? $entry : $relativeDirectory.'/'.$entry;
                $absolute = $absoluteDirectory.DIRECTORY_SEPARATOR.$entry;
                if (is_link($absolute)) {
                    continue;
                }
                if (is_dir($absolute)) {
                    if (! in_array($entry, $skip, TRUE)) {
                        $stack[] = $relative;
                    }
                    continue;
                }
                if (substr(strtolower($entry), -strlen($extension)) === $extension) {
                    $results[] = $relative;
                }
            }
        }

        sort($results);
        return $results;
    }

    public function pipelines($root)
    {
        return $this->collect($root, '.hpl');
    }

    public function workflows($root)
    {
        return $this->collect($root, '.hwf');
    }

    public function entryFiles($root)
    {
        return array_merge($this->workflows($root), $this->pipelines($root));
    }

    public function hasEntryFile($root, $entryFile)
    {
        return in_array($this->cleanEntryFile($entryFile), $this->entryFiles($root), TRUE);
    }

    public function cleanEntryFile($entryFile)
    {
        $entryFile = trim(str_replace('\\', '/', (string) $entryFile), '/');
        if ($entryFile === '' || strpos($entryFile, "\0") !== FALSE || strlen($entryFile) > 500) {
            return '';
        }
        foreach (explode('/', $entryFile) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return '';
            }
        }
        $lower = strtolower($entryFile);
        if (substr($lower, -4) !== '.hpl' && substr($lower, -4) !== '.hwf') {
            return '';
        }
        return $entryFile;
    }

    // -- descriptors -------------------------------------------------------

    private function readJson($path)
    {
        if (! is_file($path)) {
            return NULL;
        }
        $decoded = json_decode((string) file_get_contents($path), TRUE);
        return is_array($decoded) ? $decoded : NULL;
    }

    private function writeJson($path, $payload)
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, TRUE) && ! is_dir($directory)) {
            return FALSE;
        }
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === FALSE) {
            return FALSE;
        }
        $temporary = $directory.DIRECTORY_SEPARATOR.'.hop-'.uniqid('', TRUE).'.tmp';
        if (file_put_contents($temporary, $json."\n", LOCK_EX) === FALSE) {
            return FALSE;
        }
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            return FALSE;
        }
        @chmod($path, 0664);
        return TRUE;
    }

    public function manifest($root)
    {
        $values = $this->readJson(rtrim((string) $root, '/\\').DIRECTORY_SEPARATOR.self::MANIFEST_NAME);
        $values = is_array($values) ? $values : array();

        $parameters = array();
        foreach ((array) (isset($values['parameters']) ? $values['parameters'] : array()) as $name => $value) {
            $parameters[(string) $name] = (string) $value;
        }

        return array(
            'schema_version' => self::SCHEMA_VERSION,
            'project' => isset($values['project']) ? (string) $values['project'] : basename(rtrim((string) $root, '/\\')),
            'description' => isset($values['description']) ? (string) $values['description'] : '',
            'entry_file' => $this->cleanEntryFile(isset($values['entry_file']) ? $values['entry_file'] : ''),
            'run_config' => $this->cleanRunConfig(isset($values['run_config']) ? $values['run_config'] : ''),
            'engine' => $this->cleanEngine(isset($values['engine']) ? $values['engine'] : ''),
            'log_level' => $this->cleanLogLevel(isset($values['log_level']) ? $values['log_level'] : ''),
            'parameters' => $parameters,
            'connectors' => array_values(array_map('strval', (array) (isset($values['connectors']) ? $values['connectors'] : array()))),
            'assets' => array_values(array_map('strval', (array) (isset($values['assets']) ? $values['assets'] : array()))),
            'context' => array_values(array_map('strval', (array) (isset($values['context']) ? $values['context'] : array())))
        );
    }

    public function saveManifest($root, $manifest)
    {
        $current = $this->manifest($root);
        foreach ((array) $manifest as $name => $value) {
            if (array_key_exists($name, $current)) {
                $current[$name] = $value;
            }
        }
        $current['schema_version'] = self::SCHEMA_VERSION;
        $current['entry_file'] = $this->cleanEntryFile($current['entry_file']);
        $current['run_config'] = $this->cleanRunConfig($current['run_config']);
        $current['engine'] = $this->cleanEngine($current['engine']);
        $current['log_level'] = $this->cleanLogLevel($current['log_level']);
        // Keep the schema stable: no parameters is an empty object, not a JSON
        // list, so a reader can index it without a type check.
        $current['parameters'] = (object) (array) $current['parameters'];

        return $this->writeJson(rtrim((string) $root, '/\\').DIRECTORY_SEPARATOR.self::MANIFEST_NAME, $current) ? $current : FALSE;
    }

    /** Write Hop's own project descriptor when an upload did not carry one. */
    public function ensureProjectConfig($root)
    {
        $path = rtrim((string) $root, '/\\').DIRECTORY_SEPARATOR.self::PROJECT_CONFIG_NAME;
        if (is_file($path)) {
            return TRUE;
        }

        return $this->writeJson($path, array(
            'metadataBaseFolder' => '${PROJECT_HOME}/metadata',
            'unitTestsBasePath' => '${PROJECT_HOME}',
            'dataSetsCsvFolder' => '${PROJECT_HOME}/datasets',
            'enforcingExecutionInHome' => TRUE,
            'config' => array('variables' => array())
        ));
    }

    /**
     * Hop refuses to run without a run configuration, and an uploaded project
     * often carries it only in the author's local Hop installation.
     */
    public function ensureRunConfigurations($root)
    {
        $root = rtrim((string) $root, '/\\');
        $ok = TRUE;

        $pipeline = $root.DIRECTORY_SEPARATOR.'metadata'.DIRECTORY_SEPARATOR.'pipeline-run-configuration'.DIRECTORY_SEPARATOR.'local.json';
        if (! is_file($pipeline)) {
            $ok = $this->writeJson($pipeline, array(
                'name' => self::DEFAULT_RUN_CONFIG,
                'description' => 'JobSeeker default local pipeline engine',
                'defaultSelection' => TRUE,
                'configurationVariables' => array(),
                'engineRunConfiguration' => array('Local' => array(
                    'feedback_size' => '50000',
                    'sample_size' => '100',
                    'sample_type_in_gui' => 'Last',
                    'rowset_size' => '10000',
                    'safe_mode' => FALSE,
                    'show_feedback' => FALSE,
                    'topo_sort' => FALSE,
                    'gather_metrics' => FALSE
                ))
            )) && $ok;
        }

        $workflow = $root.DIRECTORY_SEPARATOR.'metadata'.DIRECTORY_SEPARATOR.'workflow-run-configuration'.DIRECTORY_SEPARATOR.'local.json';
        if (! is_file($workflow)) {
            $ok = $this->writeJson($workflow, array(
                'name' => self::DEFAULT_RUN_CONFIG,
                'description' => 'JobSeeker default local workflow engine',
                'defaultSelection' => TRUE,
                'configurationVariables' => array(),
                'engineRunConfiguration' => array('Local' => array('safe_mode' => FALSE))
            )) && $ok;
        }

        return $ok;
    }

    public function scaffold($root, $projectKey = '')
    {
        $root = rtrim((string) $root, '/\\');
        if ($root === '' || is_link($root)) {
            return FALSE;
        }
        $folders = array(
            '',
            'metadata'.DIRECTORY_SEPARATOR.'rdbms',
            'metadata'.DIRECTORY_SEPARATOR.'pipeline-run-configuration',
            'metadata'.DIRECTORY_SEPARATOR.'workflow-run-configuration',
            'pipelines',
            'workflows'
        );
        foreach ($folders as $folder) {
            $path = $folder === '' ? $root : $root.DIRECTORY_SEPARATOR.$folder;
            if (! is_dir($path) && ! mkdir($path, 0775, TRUE) && ! is_dir($path)) {
                return FALSE;
            }
        }

        if (! $this->ensureProjectConfig($root) || ! $this->ensureRunConfigurations($root)) {
            return FALSE;
        }

        $manifest = $this->manifest($root);
        $manifest['project'] = $projectKey !== '' ? $projectKey : basename($root);
        return $this->saveManifest($root, $manifest);
    }

    /**
     * Everything a screen or the Job Creation form needs about one project.
     */
    public function describe($root)
    {
        $root = rtrim((string) $root, '/\\');
        $workflows = $this->workflows($root);
        $pipelines = $this->pipelines($root);
        $manifest = $this->manifest($root);
        $entryFiles = array_merge($workflows, $pipelines);

        $entryFile = $manifest['entry_file'];
        if ($entryFile !== '' && ! in_array($entryFile, $entryFiles, TRUE)) {
            $entryFile = '';
        }
        if ($entryFile === '' && count($entryFiles) === 1) {
            $entryFile = $entryFiles[0];
        }

        $connections = array();
        $rdbms = $root.DIRECTORY_SEPARATOR.'metadata'.DIRECTORY_SEPARATOR.'rdbms';
        if (is_dir($rdbms)) {
            foreach ((array) scandir($rdbms) as $entry) {
                if (substr(strtolower((string) $entry), -5) === '.json') {
                    $connections[] = substr($entry, 0, -5);
                }
            }
            sort($connections);
        }

        return array(
            'key' => basename($root),
            'name' => $manifest['project'] !== '' ? $manifest['project'] : basename($root),
            'path' => $root,
            'valid' => $this->isProject($root),
            'workflows' => $workflows,
            'pipelines' => $pipelines,
            'entry_files' => $entryFiles,
            'entry_file' => $entryFile,
            'connections' => $connections,
            'manifest' => $manifest,
            'updated_at' => is_dir($root) ? gmdate('c', (int) filemtime($root)) : NULL
        );
    }

    /**
     * Explain why a project cannot be turned into a job yet. The Job Creation
     * form shows these before the user hits save, rather than after a failed
     * Jenkins build.
     */
    public function validate($root, $entryFile = '')
    {
        $problems = array();
        if (! is_dir($root)) {
            return array('ok' => FALSE, 'problems' => array('The Hop project folder does not exist.'));
        }

        $entryFiles = $this->entryFiles($root);
        if (! $entryFiles) {
            $problems[] = 'This project contains no .hwf workflow or .hpl pipeline file.';
        }

        $entryFile = $this->cleanEntryFile($entryFile);
        if ($entryFile === '' && count($entryFiles) > 1) {
            $problems[] = 'Select which workflow or pipeline this job runs.';
        } else if ($entryFile !== '' && ! in_array($entryFile, $entryFiles, TRUE)) {
            $problems[] = 'The selected entry file is not part of this project.';
        }

        if (! $this->isProject($root)) {
            $problems[] = 'This project has no '.self::PROJECT_CONFIG_NAME.'. JobSeeker writes one when the job is saved.';
        }

        return array('ok' => empty($problems), 'problems' => $problems);
    }
}

/* End of file HopProject.php */
/* Location: ./application/libraries/HopProject.php */
