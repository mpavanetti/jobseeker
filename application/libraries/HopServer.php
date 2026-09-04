<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_HOP_TEST')) exit('No direct script access allowed');

/**
 * The long-lived Apache Hop Server, seen from JobSeeker.
 *
 * Two different things reach this server. JobSeeker's own jobs do, through the
 * `server` execution engine, and those report themselves to Transaction
 * Monitoring from the runner. People do too: the Apache Hop GUI can publish a
 * pipeline or workflow straight to a remote server, and until this library
 * existed such a run was visible only inside Hop - not in the Apache Hop screen
 * and not in Transaction Monitoring, which is where somebody looking for it
 * would go.
 *
 * So this reads what the server holds and turns it into two things JobSeeker
 * already understands: a durable execution history, and a TMF row per run. The
 * server's memory stays authoritative for what is *running*; the JobSeeker
 * table is what survives the server forgetting a finished run (its default
 * object timeout) or being restarted.
 *
 * A run JobSeeker itself started is skipped, because the runner is already
 * reporting it. The runner says so by leaving a claim file on the shared
 * repository volume, keyed by the Hop execution id.
 */
class HopServer
{
    const CLAIM_MAX_AGE_SECONDS = 604800; // 7 days
    /** Longest log kept per execution, so one runaway run cannot fill the table. */
    const MAX_STORED_LOG_BYTES = 200000;

    private $claimCache = NULL;

    // -- configuration -----------------------------------------------------

    public function enabled()
    {
        $value = strtolower(trim((string) getenv('JOBSEEKER_HOP_ENABLED')));
        return ! in_array($value, array('0', 'false', 'off', 'no'), TRUE);
    }

    public function baseUrl()
    {
        $url = trim((string) getenv('JOBSEEKER_HOP_SERVER_URL'));
        return $url === '' ? 'http://hop-server:8080' : rtrim($url, '/');
    }

    public function environment()
    {
        $environment = strtoupper(trim((string) getenv('JOBSEEKER_HOP_SERVER_ENVIRONMENT')));
        return $environment === '' ? 'DEV' : $environment;
    }

    public function repositoryServerRoot()
    {
        $root = trim((string) getenv('JOBSEEKER_HOP_SERVER_REPOSITORY_ROOT'));
        return $root === '' ? '/php/repository' : rtrim($root, '/');
    }

    private function credentials()
    {
        $user = (string) getenv('JOBSEEKER_HOP_SERVER_USER');
        $password = (string) getenv('JOBSEEKER_HOP_SERVER_PASSWORD');
        return array($user === '' ? 'cluster' : $user, $password === '' ? 'cluster' : $password);
    }

    // -- transport ---------------------------------------------------------

    /**
     * One request to the Hop Server. Credentials travel in a header, never in
     * the URL, so they cannot reach an access log.
     */
    public function request($path, $query = array(), $timeoutSeconds = 5)
    {
        if (! $this->enabled()) {
            return array('ok' => FALSE, 'status' => 0, 'body' => '');
        }

        list($user, $password) = $this->credentials();
        $url = $this->baseUrl().$path;
        if ($query) {
            $url .= (strpos($path, '?') === FALSE ? '?' : '&').http_build_query($query);
        }

        $context = stream_context_create(array('http' => array(
            'method' => 'GET',
            'timeout' => (int) $timeoutSeconds,
            'ignore_errors' => TRUE,
            'header' => 'Authorization: Basic '.base64_encode($user.':'.$password)."\r\n"
        )));

        $body = @file_get_contents($url, FALSE, $context);
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                    $status = (int) $matches[1];
                }
            }
        }

        return array('ok' => $body !== FALSE && $status === 200, 'status' => $status, 'body' => $body === FALSE ? '' : (string) $body);
    }

    /** Health for the Apache Hop screen and the Job Creation engine picker. */
    public function status($timeoutSeconds = 3)
    {
        if (! $this->enabled()) {
            return array(
                'url' => $this->baseUrl(),
                'reachable' => FALSE,
                'status' => 0,
                'version' => '',
                'environment' => $this->environment(),
                'message' => 'Apache Hop is disabled by JOBSEEKER_HOP_ENABLED.'
            );
        }

        $response = $this->request('/hop/status/', array('xml' => 'Y'), $timeoutSeconds);

        $version = '';
        if (preg_match('#<hopVersion>(.*?)</hopVersion>#s', $response['body'], $matches)) {
            $version = trim($matches[1]);
        }

        if ($response['status'] === 401) {
            $message = 'The Hop Server rejected the configured credentials.';
        } else if (! $response['ok']) {
            $message = 'The Hop Server is not reachable. Start it with: docker compose --profile hop up -d hop-server';
        } else {
            $message = 'The Hop Server is running.';
        }

        return array(
            'url' => $this->baseUrl(),
            'reachable' => $response['ok'],
            'status' => $response['status'],
            'version' => $version,
            'environment' => $this->environment(),
            'message' => $message
        );
    }

    // -- executions --------------------------------------------------------

    /**
     * Every pipeline and workflow the server currently holds.
     *
     * Parsed with regular expressions rather than an XML parser on purpose: the
     * status document embeds raw run logs, and a job that logged one stray
     * control character should not blank the whole screen.
     */
    public function executions($timeoutSeconds = 5)
    {
        $response = $this->request('/hop/status/', array('xml' => 'Y'), $timeoutSeconds);
        if (! $response['ok']) {
            return array();
        }

        $executions = array();
        foreach ($this->blocks($response['body'], 'pipeline-status') as $block) {
            $execution = $this->parseExecution($block, 'pipeline');
            if ($execution !== NULL) {
                $executions[] = $execution;
            }
        }
        foreach ($this->blocks($response['body'], 'workflow-status') as $block) {
            $execution = $this->parseExecution($block, 'workflow');
            if ($execution !== NULL) {
                $executions[] = $execution;
            }
        }

        usort($executions, function($left, $right) {
            return strcmp((string) $right['started_at'], (string) $left['started_at']);
        });

        return $executions;
    }

    /** One execution with its decoded log, addressed by the id the server gave it. */
    public function execution($kind, $name, $executionId, $timeoutSeconds = 10)
    {
        $endpoint = $kind === 'workflow' ? '/hop/workflowStatus/' : '/hop/pipelineStatus/';
        $query = array('name' => (string) $name, 'xml' => 'Y');
        if ((string) $executionId !== '') {
            $query['id'] = (string) $executionId;
        }

        $response = $this->request($endpoint, $query, $timeoutSeconds);
        if (! $response['ok']) {
            return NULL;
        }

        $execution = $this->parseExecution($response['body'], $kind);
        if ($execution === NULL) {
            return NULL;
        }
        $execution['log'] = $this->log($response['body']);
        return $execution;
    }

    /**
     * Per-transform / per-action state for one execution, keyed by node name.
     *
     * This is what turns a static picture of a pipeline into a picture of the
     * run: the same canvas, with the rows each transform has moved and which
     * one is still working. Hop keeps it only while the execution is in memory,
     * which is exactly the window a person is watching a build in.
     */
    public function executionNodes($kind, $name, $executionId, $timeoutSeconds = 5)
    {
        $endpoint = $kind === 'workflow' ? '/hop/workflowStatus/' : '/hop/pipelineStatus/';
        $query = array('name' => (string) $name, 'xml' => 'Y');
        if ((string) $executionId !== '') {
            $query['id'] = (string) $executionId;
        }

        $response = $this->request($endpoint, $query, $timeoutSeconds);
        if (! $response['ok']) {
            return array();
        }

        $nodes = array();
        if ($kind === 'workflow') {
            foreach ($this->blocks($response['body'], 'action_status') as $block) {
                $nodeName = $this->value($block, 'name');
                if ($nodeName === '') {
                    continue;
                }
                $nodes[$nodeName] = array(
                    'status' => $this->value($block, 'status'),
                    'read' => (int) $this->value($block, 'lines_read'),
                    'written' => (int) $this->value($block, 'lines_written'),
                    'errors' => (int) $this->value($block, 'nr_errors')
                );
            }
            return $nodes;
        }

        foreach ($this->blocks($response['body'], 'transform_status') as $block) {
            $nodeName = $this->value($block, 'transformName');
            if ($nodeName === '') {
                continue;
            }
            $nodes[$nodeName] = array(
                'status' => $this->value($block, 'statusDescription'),
                'read' => max((int) $this->value($block, 'linesRead'), (int) $this->value($block, 'linesInput')),
                'written' => (int) $this->value($block, 'linesWritten'),
                'errors' => (int) $this->value($block, 'errors')
            );
        }
        return $nodes;
    }

    /** The execution the server currently holds for one object, newest first. */
    public function currentExecution($kind, $name, $timeoutSeconds = 5)
    {
        $latest = NULL;
        foreach ($this->executions($timeoutSeconds) as $execution) {
            if ($execution['kind'] !== $kind || $execution['name'] !== $name) {
                continue;
            }
            if ($latest === NULL || strcmp((string) $execution['started_at'], (string) $latest['started_at']) > 0) {
                $latest = $execution;
            }
        }
        return $latest;
    }

    private function blocks($body, $tag)
    {
        $matches = array();
        preg_match_all('#<'.$tag.'>(.*?)</'.$tag.'>#s', (string) $body, $matches);
        return isset($matches[1]) ? $matches[1] : array();
    }

    private function value($block, $tag)
    {
        if (preg_match('#<'.$tag.'>(.*?)</'.$tag.'>#s', (string) $block, $matches)) {
            return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }
        return '';
    }

    private function parseExecution($block, $kind)
    {
        $name = $this->value($block, $kind === 'workflow' ? 'workflowname' : 'pipeline_name');
        $executionId = $this->value($block, 'id');
        if ($name === '' && $executionId === '') {
            return NULL;
        }

        $counters = $this->counters($block, $kind);
        $statusDescription = $this->value($block, 'status_desc');
        $errorDescription = $this->value($block, 'error_desc');

        $filename = $this->value($block, 'workflow_filename');
        return array(
            'execution_id' => $executionId,
            'name' => $name,
            // Hop registers a run under the <name> inside the file, which the
            // Apache Hop GUI leaves as "New workflow" unless somebody renames
            // it by hand. The file name is what a person actually recognises,
            // so it becomes the label wherever one is shown.
            'display_name' => $this->displayName($name, $filename),
            'kind' => $kind,
            'status' => $statusDescription,
            'state' => $this->state($statusDescription, $counters['errors'], $errorDescription),
            'error' => $errorDescription,
            'started_at' => $this->timestamp($this->value($block, 'execution_start_date')),
            'ended_at' => $this->timestamp($this->value($block, 'execution_end_date')),
            'filename' => $filename,
            'records_total' => $counters['records_total'],
            'records_processed' => $counters['records_processed'],
            'errors' => $counters['errors']
        );
    }

    /**
     * The file this run came from, without its folder or extension.
     *
     * The Apache Hop GUI can publish a zip export, which arrives as
     * `zip:file:///tmp/export_<uuid>.zip!Matheus.hwf`; the part after the "!"
     * is the file the designer saved.
     */
    public function sourceName($filename)
    {
        $filename = trim(str_replace('\\', '/', (string) $filename));
        if ($filename === '') {
            return '';
        }
        $separator = strrpos($filename, '!');
        if ($separator !== FALSE) {
            $filename = substr($filename, $separator + 1);
        }
        $base = basename($filename);
        return trim(preg_replace('/\.(hwf|hpl)$/i', '', $base));
    }

    public function displayName($name, $filename)
    {
        $source = $this->sourceName($filename);
        return $source !== '' ? $source : (string) $name;
    }

    /**
     * Hop's own per-transform / per-action result. Reading the counters here
     * rather than from the log means a run reports the same numbers to
     * Transaction Monitoring whoever started it.
     */
    private function counters($block, $kind)
    {
        $read = 0;
        $written = 0;
        $errors = 0;

        if ($kind === 'workflow') {
            // Only the per-action results. A workflow status also carries the
            // overall result and a tracker tree that repeat the same counters,
            // so summing every <nr_errors> in the document multiplies them.
            $actions = $this->blocks($block, 'action_status');
            foreach ($actions as $action) {
                $read = max($read, (int) $this->value($action, 'lines_read'));
                $written = max($written, (int) $this->value($action, 'lines_written'));
                $errors += max(0, (int) $this->value($action, 'nr_errors'));
            }
            if (! $actions) {
                $read = max(0, (int) $this->value($block, 'lines_read'));
                $written = max(0, (int) $this->value($block, 'lines_written'));
                $errors = max(0, (int) $this->value($block, 'nr_errors'));
            }
        } else {
            foreach ($this->blocks($block, 'transform_status') as $transform) {
                foreach (array('linesRead', 'linesInput') as $tag) {
                    $read = max($read, (int) $this->value($transform, $tag));
                }
                $written = max($written, (int) $this->value($transform, 'linesWritten'));
                $errors += max(0, (int) $this->value($transform, 'errors'));
            }
        }

        return array(
            'records_total' => $read > 0 ? $read : $written,
            'records_processed' => $written,
            'errors' => $errors
        );
    }

    /** Map Hop's own words onto the three states the rest of JobSeeker uses. */
    private function state($statusDescription, $errors, $errorDescription = '')
    {
        $description = strtolower((string) $statusDescription);
        if ($errors > 0 || trim((string) $errorDescription) !== ''
            || strpos($description, 'error') !== FALSE || strpos($description, 'stopped') !== FALSE) {
            return 'error';
        }
        if (strpos($description, 'running') !== FALSE || strpos($description, 'waiting') !== FALSE || strpos($description, 'paused') !== FALSE) {
            return 'running';
        }
        if (strpos($description, 'finished') !== FALSE) {
            return 'ready';
        }
        return $description === '' ? 'running' : 'ready';
    }

    /** Hop writes 2026/09/04 17:38:00.774; MySQL wants 2026-09-04 17:38:00. */
    private function timestamp($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^(\d{4})/(\d{2})/(\d{2})\s+(\d{2}):(\d{2}):(\d{2})#', $value, $matches)) {
            return $matches[1].'-'.$matches[2].'-'.$matches[3].' '.$matches[4].':'.$matches[5].':'.$matches[6];
        }
        return '';
    }

    /**
     * The complete run log, which Hop sends as base64 of a gzip stream.
     *
     * The per-transform <log_text> sections are only part of it and a workflow
     * status has none at all, so this is the only place a failed workflow says
     * what actually went wrong.
     */
    public function log($body)
    {
        $matches = array();
        preg_match_all('#<logging_string>(.*?)</logging_string>#s', (string) $body, $matches);
        $sections = array();
        foreach (isset($matches[1]) ? $matches[1] : array() as $raw) {
            $decoded = $this->decodeLog($raw);
            if ($decoded !== '') {
                $sections[] = $decoded;
            }
        }
        if ($sections) {
            return implode("\n", $sections);
        }

        preg_match_all('#<log_text>(.*?)</log_text>#s', (string) $body, $matches);
        foreach (isset($matches[1]) ? $matches[1] : array() as $raw) {
            $decoded = trim($this->stripCdata(html_entity_decode($raw, ENT_QUOTES | ENT_XML1, 'UTF-8')));
            if ($decoded !== '') {
                $sections[] = $decoded;
            }
        }
        return implode("\n", $sections);
    }

    private function stripCdata($value)
    {
        $value = trim((string) $value);
        if (strpos($value, '<![CDATA[') === 0 && substr($value, -3) === ']]>') {
            return trim(substr($value, 9, -3));
        }
        return $value;
    }

    private function decodeLog($raw)
    {
        $text = $this->stripCdata(html_entity_decode((string) $raw, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        if ($text === '') {
            return '';
        }

        $payload = base64_decode($text, TRUE);
        if ($payload === FALSE || strlen($payload) < 2 || substr($payload, 0, 2) !== "\x1f\x8b") {
            return $text;
        }

        $decoded = @gzdecode($payload);
        return $decoded === FALSE ? '' : $decoded;
    }

    /**
     * The error lines out of a Hop log, with the transform or action that
     * raised them. Hop says what went wrong on ordinary log lines; the status
     * only says that something did.
     */
    public function errorLines($log, $limit = 25)
    {
        $errors = array();
        $seen = array();
        foreach (preg_split('/\r\n|\r|\n/', (string) $log) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode(' - ', $line);
            $index = -1;
            foreach ($parts as $position => $part) {
                $upper = strtoupper(ltrim($part));
                if (strpos($upper, 'ERROR') === 0 || strpos($upper, 'FATAL') === 0) {
                    $index = $position;
                    break;
                }
            }
            if ($index < 0) {
                continue;
            }

            $message = trim(implode(' - ', array_slice($parts, $index)));
            $origin = $index > 0 ? trim($parts[$index - 1]) : '';
            if ($index === 1 && preg_match('#^\d{4}/\d{2}/\d{2}\b#', $origin)) {
                $origin = '';
            }

            $key = $origin."\n".$message;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = TRUE;
            $errors[] = array('origin' => $origin, 'message' => $message);
            if (count($errors) >= $limit) {
                break;
            }
        }
        return $errors;
    }

    public function truncateLog($log)
    {
        $log = (string) $log;
        if (strlen($log) <= self::MAX_STORED_LOG_BYTES) {
            return $log;
        }
        // Keep the end: that is where a Hop failure explains itself.
        return "[... earlier log truncated by JobSeeker ...]\n".substr($log, -self::MAX_STORED_LOG_BYTES);
    }

    // -- published connection catalog --------------------------------------
    //
    // A pipeline someone publishes from the Apache Hop GUI runs on the server
    // with no JobSeeker runner in front of it, so it never gets the run-scoped
    // catalog a Jenkins job gets - which is why such a run fails with
    // "Relational database connection 'x' not found". An operator can publish
    // the catalog for one environment instead. It holds resolved credentials,
    // so it is an explicit, role-gated act with an explicit way to withdraw it,
    // and it is kept in a JobSeeker-owned folder so a Jenkins run that
    // temporarily swaps in its own scoped catalog can put this one back.

    /** Hop's own database plugin ids. Mirrors HOP_DATABASE_PLUGINS in the SDK. */
    private $databasePlugins = array(
        'mysql' => array('MYSQL', 'MySQL', 3306, array(
            'EXTRA_OPTION_MYSQL.useCursorFetch' => 'true',
            'EXTRA_OPTION_MYSQL.defaultFetchSize' => '500',
            'EXTRA_OPTION_MYSQL.useSSL' => 'false',
            'EXTRA_OPTION_MYSQL.allowPublicKeyRetrieval' => 'true',
            'EXTRA_OPTION_MYSQL.zeroDateTimeBehaviorValue' => 'CONVERT_TO_NULL'
        )),
        'mariadb' => array('MARIADB', 'MariaDB', 3306, array()),
        'pgsql' => array('POSTGRESQL', 'PostgreSQL', 5432, array()),
        'postgresql' => array('POSTGRESQL', 'PostgreSQL', 5432, array()),
        'sqlserver' => array('MSSQLNATIVE', 'MS SQL Server (Native)', 1433, array('EXTRA_OPTION_MSSQLNATIVE.encrypt' => 'false')),
        'oracle_service' => array('ORACLE', 'Oracle', 1521, array()),
        'oracle_sid' => array('ORACLE', 'Oracle', 1521, array())
    );

    /**
     * The Hop JDBC driver id each connector type needs.
     *
     * Which of these an image already carries differs between the stock
     * apache/hop image and a JobSeeker-built one, so this only says what a
     * connector *needs*; the Hop Server decides what is missing by asking
     * `hop driver list` on start-up. Guessing here would either install jars
     * nobody needs or skip one that is genuinely absent.
     */
    private $databaseDrivers = array(
        'mysql' => 'mysql',
        'mariadb' => 'mariadb',
        'pgsql' => 'postgresql',
        'postgresql' => 'postgresql',
        'sqlserver' => 'mssqlnative',
        'oracle_service' => 'oracle',
        'oracle_sid' => 'oracle'
    );

    public function isRelational($type)
    {
        return isset($this->databasePlugins[strtolower(trim((string) $type))]);
    }

    /** Driver ids for a set of connector types, de-duplicated and sorted. */
    public function requiredDrivers($connectorTypes)
    {
        $drivers = array();
        foreach ((array) $connectorTypes as $type) {
            $type = strtolower(trim((string) $type));
            if (isset($this->databaseDrivers[$type])) {
                $drivers[$this->databaseDrivers[$type]] = TRUE;
            }
        }
        $drivers = array_keys($drivers);
        sort($drivers);
        return $drivers;
    }

    public function driversFilePath($repositoryRoot)
    {
        return rtrim((string) $repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'hop'.DIRECTORY_SEPARATOR.'server'
            .DIRECTORY_SEPARATOR.'published'.DIRECTORY_SEPARATOR.'drivers.json';
    }

    /**
     * Record which JDBC drivers the published connections need.
     *
     * The Hop Server installs whatever of these it does not already have when
     * it next starts, into a folder on the repository volume so the download
     * happens once rather than on every container rebuild.
     */
    public function publishDrivers($repositoryRoot, $drivers)
    {
        $path = $this->driversFilePath($repositoryRoot);
        if (! $this->ensureDirectory(dirname($path))) {
            return FALSE;
        }
        $payload = array('schema_version' => 1, 'generated_at' => gmdate('c'), 'drivers' => array_values($drivers));
        return @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE;
    }

    public function publishedDrivers($repositoryRoot)
    {
        $payload = json_decode((string) @file_get_contents($this->driversFilePath($repositoryRoot)), TRUE);
        return is_array($payload) && isset($payload['drivers']) && is_array($payload['drivers'])
            ? array_values(array_map('strval', $payload['drivers']))
            : array();
    }

    /** Driver jars already present in the shared folder, for the screen. */
    public function installedDrivers($repositoryRoot)
    {
        $folder = trim((string) getenv('JOBSEEKER_HOP_SERVER_JDBC_FOLDER'));
        if ($folder === '') {
            $folder = rtrim((string) $repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'hop'.DIRECTORY_SEPARATOR.'server'.DIRECTORY_SEPARATOR.'jdbc';
        }
        if (! is_dir($folder)) {
            return array();
        }
        $jars = array();
        foreach ((array) scandir($folder) as $entry) {
            if (substr($entry, -4) === '.jar') {
                $jars[] = $entry;
            }
        }
        sort($jars);
        return $jars;
    }

    public function publishedDirectory($repositoryRoot)
    {
        return rtrim((string) $repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'hop'.DIRECTORY_SEPARATOR.'server'.DIRECTORY_SEPARATOR.'published'.DIRECTORY_SEPARATOR.'rdbms';
    }

    public function metadataDirectory($repositoryRoot)
    {
        $override = trim((string) getenv('JOBSEEKER_HOP_SERVER_METADATA'));
        $base = $override !== ''
            ? $override
            : rtrim((string) $repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'hop'.DIRECTORY_SEPARATOR.'server'.DIRECTORY_SEPARATOR.'metadata';
        return rtrim($base, '/\\').DIRECTORY_SEPARATOR.'rdbms';
    }

    /**
     * Build the Hop database document for one JobSeeker connector.
     *
     * ``$secrets`` carries the resolved user and password. The Hop Server has no
     * variable space of its own before a run, so a published catalog has to hold
     * values rather than ${VARIABLE} references - which is exactly why the file
     * is written 0600 and why publishing is a deliberate action.
     */
    public function rdbmsDocument($connector, $secrets = array())
    {
        $type = strtolower(trim((string) (isset($connector['db_type']) ? $connector['db_type'] : '')));
        if (! isset($this->databasePlugins[$type])) {
            return NULL;
        }
        list($pluginId, $pluginName, $defaultPort, $attributes) = $this->databasePlugins[$type];

        $database = (string) (isset($connector['schema']) ? $connector['schema'] : '');
        if ($type === 'oracle_service' && ! empty($connector['oracle_ServiceName'])) {
            $database = (string) $connector['oracle_ServiceName'];
        }
        if ($type === 'oracle_sid' && ! empty($connector['oracle_sid'])) {
            $database = (string) $connector['oracle_sid'];
        }

        $documentAttributes = array_merge(array(
            'SUPPORTS_BOOLEAN_DATA_TYPE' => 'Y',
            'SUPPORTS_TIMESTAMP_DATA_TYPE' => 'Y',
            'PRESERVE_RESERVED_WORD_CASE' => 'Y',
            'FORCE_IDENTIFIERS_TO_LOWERCASE' => 'N',
            'FORCE_IDENTIFIERS_TO_UPPERCASE' => 'N',
            'QUOTE_ALL_FIELDS' => 'N',
            'SQL_CONNECT' => '',
            'PREFERRED_SCHEMA_NAME' => ''
        ), $attributes);

        $additional = trim((string) (isset($connector['additional_parameters']) ? $connector['additional_parameters'] : ''));
        foreach (explode(';', str_replace('&', ';', $additional)) as $parameter) {
            if (strpos($parameter, '=') === FALSE) {
                continue;
            }
            list($name, $value) = explode('=', $parameter, 2);
            $name = trim($name);
            if ($name !== '') {
                $documentAttributes['EXTRA_OPTION_'.$pluginId.'.'.$name] = trim($value);
            }
        }

        $port = (int) (isset($connector['port']) ? $connector['port'] : 0);
        return array(
            'name' => (string) $connector['connector_key'],
            'virtualPath' => '',
            'rdbms' => array($pluginId => array(
                'pluginId' => $pluginId,
                'pluginName' => $pluginName,
                'accessType' => 0,
                'hostname' => (string) (isset($connector['address']) ? $connector['address'] : ''),
                'port' => (string) ($port > 0 ? $port : $defaultPort),
                'databaseName' => $database,
                'username' => (string) (isset($secrets['username']) ? $secrets['username'] : ''),
                'password' => (string) (isset($secrets['password']) ? $secrets['password'] : ''),
                'manualUrl' => '',
                'attributes' => $documentAttributes
            ))
        );
    }

    /**
     * Replace the published catalog and mirror it into the folder Hop Server
     * reads. Returns the connector keys now available to the server.
     */
    public function publishCatalog($repositoryRoot, $documents)
    {
        $published = $this->publishedDirectory($repositoryRoot);
        if (! $this->ensureDirectory($published)) {
            return FALSE;
        }

        $this->clearJsonFiles($published);
        $keys = array();
        foreach ($documents as $document) {
            $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', (string) $document['name']);
            if ($name === '') {
                continue;
            }
            $path = $published.DIRECTORY_SEPARATOR.$name.'.json';
            if (@file_put_contents($path, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === FALSE) {
                return FALSE;
            }
            @chmod($path, 0600);
            $keys[] = (string) $document['name'];
        }

        return $this->mirrorPublishedCatalog($repositoryRoot) ? $keys : FALSE;
    }

    /** Withdraw the published catalog and variables. */
    public function withdrawCatalog($repositoryRoot)
    {
        $this->clearJsonFiles($this->publishedDirectory($repositoryRoot));
        $this->clearJsonFiles($this->metadataDirectory($repositoryRoot));
        $environmentFile = $this->environmentFilePath($repositoryRoot);
        if (is_file($environmentFile) && ! is_link($environmentFile)) {
            @unlink($environmentFile);
        }
        $this->publishSystemVariables($repositoryRoot, array());
        $drivers = $this->driversFilePath($repositoryRoot);
        if (is_file($drivers) && ! is_link($drivers)) {
            @unlink($drivers);
        }
        return TRUE;
    }

    /**
     * Put the published catalog back if the live folder has drifted from it.
     *
     * The Hop Server's metadata folder is process-global and a Jenkins run
     * borrows it for the length of one request, so a crashed build, a restarted
     * worker or an operator clearing it by hand can leave the folder short. A
     * person would only find out when a pipeline they started from the Hop GUI
     * failed with "connection not found", so the screen repairs it instead.
     */
    public function ensureCatalogMirrored($repositoryRoot)
    {
        $published = $this->publishedDirectory($repositoryRoot);
        if (! is_dir($published)) {
            return FALSE;
        }

        $metadata = $this->metadataDirectory($repositoryRoot);
        foreach ((array) scandir($published) as $entry) {
            if (substr($entry, -5) !== '.json') {
                continue;
            }
            $source = $published.DIRECTORY_SEPARATOR.$entry;
            $target = $metadata.DIRECTORY_SEPARATOR.$entry;
            if (! is_file($source) || is_link($source)) {
                continue;
            }
            if (! is_file($target) || filesize($target) !== filesize($source)) {
                return $this->mirrorPublishedCatalog($repositoryRoot);
            }
        }

        return FALSE;
    }

    /** Copy the published catalog into the folder the Hop Server reads. */
    public function mirrorPublishedCatalog($repositoryRoot)
    {
        $published = $this->publishedDirectory($repositoryRoot);
        $metadata = $this->metadataDirectory($repositoryRoot);
        if (! $this->ensureDirectory($metadata)) {
            return FALSE;
        }

        $this->clearJsonFiles($metadata);
        if (! is_dir($published)) {
            return TRUE;
        }
        foreach ((array) scandir($published) as $entry) {
            if (substr($entry, -5) !== '.json') {
                continue;
            }
            $source = $published.DIRECTORY_SEPARATOR.$entry;
            if (! is_file($source) || is_link($source)) {
                continue;
            }
            $target = $metadata.DIRECTORY_SEPARATOR.$entry;
            if (! @copy($source, $target)) {
                return FALSE;
            }
            @chmod($target, 0600);
        }
        return TRUE;
    }

    public function environmentFilePath($repositoryRoot)
    {
        return rtrim((string) $repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'hop'.DIRECTORY_SEPARATOR.'server'
            .DIRECTORY_SEPARATOR.'published'.DIRECTORY_SEPARATOR.'jobseeker-environment.json';
    }

    /**
     * Write the JobSeeker variables a Hop Server run should see.
     *
     * Hop's own environment config shape, so the Hop GUI can add the same file
     * to a project and design against the identical names a Jenkins job gets.
     * No secret goes in it: a password reaches the server only inside the
     * database document, which is 0600 and never referenced by a project file.
     */
    public function publishEnvironmentFile($repositoryRoot, $variables)
    {
        $path = $this->environmentFilePath($repositoryRoot);
        if (! $this->ensureDirectory(dirname($path))) {
            return FALSE;
        }

        $entries = array();
        foreach ($variables as $name => $variable) {
            $entries[] = array(
                'name' => (string) $name,
                'value' => (string) $variable['value'],
                'description' => (string) $variable['description']
            );
        }

        $written = @file_put_contents($path, json_encode(array('variables' => $entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($written === FALSE) {
            return FALSE;
        }
        @chmod($path, 0644);
        return $entries;
    }

    public function configFolder($repositoryRoot)
    {
        $configured = trim((string) getenv('JOBSEEKER_HOP_SERVER_CONFIG_FOLDER'));
        if ($configured !== '') {
            return rtrim($configured, '/\\');
        }
        return rtrim((string) $repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'hop'.DIRECTORY_SEPARATOR.'server'.DIRECTORY_SEPARATOR.'config';
    }

    /**
     * Publish the variables as Hop *system* variables.
     *
     * This is the only place a Hop Server resolves a variable for a pipeline
     * somebody started from the Apache Hop GUI: it has no project and no
     * environment registered for that run, so a project-scoped environment file
     * would never be read. Everything else in hop-config.json is preserved -
     * it is Hop's file, not JobSeeker's.
     */
    public function publishSystemVariables($repositoryRoot, $variables)
    {
        $path = $this->configFolder($repositoryRoot).DIRECTORY_SEPARATOR.'hop-config.json';
        if (! is_file($path) || is_link($path)) {
            return FALSE;
        }

        $config = json_decode((string) @file_get_contents($path), TRUE);
        if (! is_array($config)) {
            return FALSE;
        }

        // Keep variables JobSeeker does not own, so a manual addition survives.
        $retained = array();
        foreach (isset($config['variables']) && is_array($config['variables']) ? $config['variables'] : array() as $variable) {
            $name = isset($variable['name']) ? (string) $variable['name'] : '';
            if ($name !== '' && strpos($name, 'JOBSEEKER_') !== 0) {
                $retained[] = $variable;
            }
        }

        foreach ($variables as $name => $variable) {
            $retained[] = array(
                'name' => (string) $name,
                'value' => (string) $variable['value'],
                'description' => (string) $variable['description']
            );
        }

        $config['variables'] = $retained;
        return @file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== FALSE;
    }

    public function publishedVariables($repositoryRoot)
    {
        $path = $this->environmentFilePath($repositoryRoot);
        if (! is_file($path)) {
            return array();
        }
        $payload = json_decode((string) @file_get_contents($path), TRUE);
        return is_array($payload) && isset($payload['variables']) && is_array($payload['variables'])
            ? $payload['variables']
            : array();
    }

    /** Connector keys currently published to the Hop Server. */
    public function publishedConnections($repositoryRoot)
    {
        $published = $this->publishedDirectory($repositoryRoot);
        $keys = array();
        if (! is_dir($published)) {
            return $keys;
        }
        foreach ((array) scandir($published) as $entry) {
            if (substr($entry, -5) !== '.json') {
                continue;
            }
            $path = $published.DIRECTORY_SEPARATOR.$entry;
            if (! is_file($path) || is_link($path)) {
                continue;
            }
            $document = json_decode((string) @file_get_contents($path), TRUE);
            if (is_array($document) && isset($document['name'])) {
                $keys[] = (string) $document['name'];
            }
        }
        sort($keys);
        return $keys;
    }

    private function ensureDirectory($path)
    {
        if (is_dir($path)) {
            return TRUE;
        }
        return @mkdir($path, 0775, TRUE) && is_dir($path);
    }

    private function clearJsonFiles($directory)
    {
        if (! is_dir($directory)) {
            return;
        }
        foreach ((array) scandir($directory) as $entry) {
            if (substr($entry, -5) !== '.json') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            if (is_file($path) && ! is_link($path)) {
                @unlink($path);
            }
        }
    }

    // -- exports published from the Apache Hop GUI --------------------------

    public function exportFolder($repositoryRoot)
    {
        $configured = trim((string) getenv('JOBSEEKER_HOP_SERVER_EXPORT_FOLDER'));
        if ($configured !== '') {
            return rtrim($configured, '/\\');
        }
        return rtrim((string) $repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'hop'.DIRECTORY_SEPARATOR.'server'.DIRECTORY_SEPARATOR.'exports';
    }

    public function isExport($filename)
    {
        return stripos(trim((string) $filename), 'zip:') === 0;
    }

    /**
     * Resolve `zip:file:///…/export_x.zip!Matheus.hwf` into the archive on disk
     * and the file inside it.
     *
     * The Apache Hop GUI publishes by uploading a zip of whatever the designer
     * has open, so this is the only trace of a run that has no file in the
     * repository. The archive is only usable if the Hop Server was pointed at
     * the shared volume; anything outside it is refused rather than read.
     */
    public function exportArchive($filename, $repositoryRoot)
    {
        $filename = trim((string) $filename);
        if (! $this->isExport($filename)) {
            return FALSE;
        }

        $body = substr($filename, 4);
        $separator = strrpos($body, '!');
        $entry = $separator === FALSE ? '' : ltrim(substr($body, $separator + 1), '/');
        $archive = $separator === FALSE ? $body : substr($body, 0, $separator);

        if (stripos($archive, 'file://') === 0) {
            $archive = substr($archive, 7);
        }
        $archive = rawurldecode($archive);
        if ($archive === '' || strpos($archive, "\0") !== FALSE) {
            return FALSE;
        }

        $real = realpath($archive);
        if ($real === FALSE || ! is_file($real) || is_link($archive)) {
            return FALSE;
        }

        $folder = realpath($this->exportFolder($repositoryRoot));
        if ($folder === FALSE || strpos($real, rtrim($folder, '/\\').DIRECTORY_SEPARATOR) !== 0) {
            return FALSE;
        }

        return array('archive' => $real, 'entry' => $entry);
    }

    // -- claims ------------------------------------------------------------

    public function claimsDirectory($repositoryRoot)
    {
        return rtrim((string) $repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'hop'.DIRECTORY_SEPARATOR.'server'.DIRECTORY_SEPARATOR.'claims';
    }

    /**
     * Executions the JobSeeker runner has already taken responsibility for,
     * keyed by Hop execution id. Stale claims are swept here so nothing has to
     * schedule a cleanup job for them.
     */
    public function claims($repositoryRoot)
    {
        if ($this->claimCache !== NULL) {
            return $this->claimCache;
        }

        $directory = $this->claimsDirectory($repositoryRoot);
        $claims = array();
        if (! is_dir($directory)) {
            $this->claimCache = $claims;
            return $claims;
        }

        $cutoff = time() - self::CLAIM_MAX_AGE_SECONDS;
        foreach ((array) scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..' || substr($entry, -5) !== '.json') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            if (! is_file($path) || is_link($path)) {
                continue;
            }
            if ((int) @filemtime($path) < $cutoff) {
                @unlink($path);
                continue;
            }
            $payload = json_decode((string) @file_get_contents($path), TRUE);
            if (is_array($payload) && isset($payload['execution_id'])) {
                $claims[(string) $payload['execution_id']] = $payload;
            }
        }

        $this->claimCache = $claims;
        return $claims;
    }
}

/* End of file HopServer.php */
/* Location: ./application/libraries/HopServer.php */
