<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Storage for the task DAG a job declares and for what each task did on a run.
 *
 * Two concerns, two tables, joined by the TMF instance id:
 *
 *   job_task_graphs - the declared graph. Written by the static scanner when a
 *                     job is saved, and overwritten by the runtime with the
 *                     authoritative manifest every time the job runs.
 *   job_task_runs   - orchestration state per task attempt: did it run, which
 *                     attempt, how long, why it was skipped. Business telemetry
 *                     (rows read/written, dimension, errors) stays in TMF.
 *
 * Schema maintenance is lazy on purpose. Running it from the constructor would
 * charge every page that merely loads the model, which is the pattern
 * scripts/test-query-efficiency.js exists to prevent.
 */
class JobTask_model extends CI_Model
{
    private static $schemaChecked = FALSE;

    const MAX_RUNS = 25;
    const MAX_TASKS = 200;

    public function __construct()
    {
        parent::__construct();
    }

    private function ensureSchema()
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = TRUE;

        $this->db->query("CREATE TABLE IF NOT EXISTS `job_task_graphs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `graph_json` longtext COLLATE utf8_unicode_ci NOT NULL,
            `source` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'scan',
            `task_count` int(11) unsigned NOT NULL DEFAULT 0,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `job_task_graph_scope` (`job_name`,`environment`),
            KEY `job_task_graph_job` (`job_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `job_task_runs` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `run_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `build_number` int(11) unsigned DEFAULT NULL,
            `task_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `attempt` int(11) unsigned NOT NULL DEFAULT 1,
            `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'PENDING',
            `trigger_rule` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'SUCCESS',
            `upstream_json` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `tmf_instance_id` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
            `started_at` datetime DEFAULT NULL,
            `finished_at` datetime DEFAULT NULL,
            `duration_ms` bigint(20) unsigned DEFAULT NULL,
            `message` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `job_task_run_attempt` (`run_key`,`task_key`,`attempt`),
            KEY `job_task_run_job` (`job_name`,`environment`,`id`),
            KEY `job_task_run_key` (`run_key`,`task_key`),
            KEY `job_task_run_status` (`status`,`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->ensureTmfTaskColumns();
    }

    /**
     * Add the task linkage to `tmf` on an installation that predates it.
     *
     * A task opens an ordinary TMF transaction, so it already appears on the
     * Results page; these two columns are what let that row be read back as
     * part of a particular job run. The worker probes for them and simply
     * leaves them unset when they are missing, so a run never fails because
     * this migration has not happened yet.
     *
     * One information_schema read, not one probe per column - the pattern
     * scripts/test-query-efficiency.js exists to keep out of request paths.
     */
    private function ensureTmfTaskColumns()
    {
        if (! $this->db->table_exists('tmf')) {
            return;
        }

        $existing = array();
        $rows = $this->db->query(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '.
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN (?, ?)',
            array('tmf', 'run_key', 'task_key')
        )->result_array();
        foreach ($rows as $row) {
            $existing[$row['COLUMN_NAME']] = TRUE;
        }

        $additions = array();
        if (! isset($existing['run_key'])) {
            $additions[] = 'ADD COLUMN `run_key` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL';
        }
        if (! isset($existing['task_key'])) {
            $additions[] = 'ADD COLUMN `task_key` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL';
        }
        if (empty($additions)) {
            return;
        }

        $this->db->query('ALTER TABLE `tmf` '.implode(', ', $additions));

        $indexed = FALSE;
        foreach ($this->db->query('SHOW INDEX FROM `tmf`')->result_array() as $row) {
            if (isset($row['Key_name']) && $row['Key_name'] === 'tmf_task_run') {
                $indexed = TRUE;
                break;
            }
        }
        if (! $indexed) {
            $this->db->query('ALTER TABLE `tmf` ADD INDEX `tmf_task_run` (`run_key`,`task_key`)');
        }
    }

    private function normalizeEnvironment($environment)
    {
        $environment = strtoupper(trim((string) $environment));
        return $environment === '' ? 'ALL' : substr($environment, 0, 100);
    }

    /**
     * Environments a lookup should accept, most specific first. A job that ran
     * in DEV must still find a graph the scanner stored against ALL.
     */
    private function environmentScope($environment)
    {
        $environment = $this->normalizeEnvironment($environment);
        return $environment === 'ALL' ? array('ALL') : array($environment, 'ALL');
    }

    /**
     * Store the declared graph. `source` records who wrote it: 'scan' for the
     * static scanner, 'runtime' for the manifest the runner emitted. A runtime
     * manifest is authoritative and is never overwritten by a later scan of the
     * same scope unless the scan actually found tasks.
     */
    public function saveGraph($jobName, $environment, $graph, $source = 'scan')
    {
        $jobName = trim((string) $jobName);
        if ($jobName === '' || ! is_array($graph)) {
            return FALSE;
        }

        $this->ensureSchema();
        $environment = $this->normalizeEnvironment($environment);
        $tasks = isset($graph['tasks']) && is_array($graph['tasks']) ? $graph['tasks'] : array();
        if (count($tasks) > self::MAX_TASKS) {
            return FALSE;
        }

        $payload = json_encode(array(
            'version' => 1,
            'tasks' => array_values($tasks),
            'edges' => isset($graph['edges']) && is_array($graph['edges']) ? array_values($graph['edges']) : array(),
            'order' => isset($graph['order']) && is_array($graph['order']) ? array_values($graph['order']) : array(),
            'layers' => isset($graph['layers']) && is_array($graph['layers']) ? array_values($graph['layers']) : array()
        ), JSON_UNESCAPED_SLASHES);

        if ($payload === FALSE) {
            return FALSE;
        }

        if (empty($tasks)) {
            // Nothing declared any more: drop the row so Job View stops showing
            // a graph for a job whose tasks were removed.
            $this->db->where('job_name', $jobName)->where('environment', $environment)->delete('job_task_graphs');
            return TRUE;
        }

        $source = in_array($source, array('scan', 'runtime'), TRUE) ? $source : 'scan';
        $now = date('Y-m-d H:i:s');

        $sql = 'INSERT INTO job_task_graphs (job_name, environment, graph_json, source, task_count, updated_at) '.
            'VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE graph_json = VALUES(graph_json), '.
            'source = VALUES(source), task_count = VALUES(task_count), updated_at = VALUES(updated_at)';
        $this->db->query($sql, array($jobName, $environment, $payload, $source, count($tasks), $now));
        return TRUE;
    }

    /** The declared graph for a job, preferring the exact environment. */
    public function graphForJob($jobName, $environment)
    {
        $jobName = trim((string) $jobName);
        if ($jobName === '') {
            return NULL;
        }

        $this->ensureSchema();
        $scope = $this->environmentScope($environment);

        $this->db->select('environment, graph_json, source, task_count, updated_at', FALSE)
            ->from('job_task_graphs')
            ->where('job_name', $jobName)
            ->where_in('environment', $scope)
            ->limit(count($scope));
        $rows = $this->db->get()->result_array();
        if (empty($rows)) {
            return NULL;
        }

        $byEnvironment = array();
        foreach ($rows as $row) {
            $byEnvironment[$row['environment']] = $row;
        }
        foreach ($scope as $candidate) {
            if (! isset($byEnvironment[$candidate])) {
                continue;
            }
            $row = $byEnvironment[$candidate];
            $graph = json_decode($row['graph_json'], TRUE);
            if (! is_array($graph)) {
                continue;
            }
            $graph['environment'] = $row['environment'];
            $graph['source'] = $row['source'];
            $graph['updated_at'] = $row['updated_at'];
            return $graph;
        }

        return NULL;
    }

    /**
     * Recent runs for a job, newest first. One row per run key with its rolled
     * up status, so the run picker does not need a query per run.
     */
    public function recentRuns($jobName, $environment, $limit = 10)
    {
        $jobName = trim((string) $jobName);
        if ($jobName === '') {
            return array();
        }

        $this->ensureSchema();
        $limit = max(1, min(self::MAX_RUNS, (int) $limit));
        $scope = $this->environmentScope($environment);
        $placeholders = implode(',', array_fill(0, count($scope), '?'));

        $sql = 'SELECT run_key, MAX(build_number) AS build_number, MAX(environment) AS environment, '.
            'MIN(started_at) AS started_at, MAX(finished_at) AS finished_at, '.
            'COUNT(DISTINCT task_key) AS task_count, '.
            "SUM(status = 'FAILURE') AS failed, SUM(status = 'SKIPPED') AS skipped, ".
            "SUM(status = 'UPSTREAM_FAILED') AS upstream_failed, SUM(status = 'RUNNING') AS running, ".
            'MAX(id) AS last_id '.
            'FROM job_task_runs WHERE job_name = ? AND environment IN ('.$placeholders.') '.
            'GROUP BY run_key ORDER BY last_id DESC LIMIT '.$limit;

        $rows = $this->db->query($sql, array_merge(array($jobName), $scope))->result_array();
        $runs = array();
        foreach ($rows as $row) {
            $runs[] = array(
                'runKey' => $row['run_key'],
                'buildNumber' => $row['build_number'] === NULL ? NULL : (int) $row['build_number'],
                'environment' => $row['environment'],
                'startedAt' => $row['started_at'],
                'finishedAt' => $row['finished_at'],
                'taskCount' => (int) $row['task_count'],
                'status' => $this->rollupStatus($row)
            );
        }
        return $runs;
    }

    private function rollupStatus($row)
    {
        if ((int) $row['running'] > 0) {
            return 'RUNNING';
        }
        if ((int) $row['failed'] > 0 || (int) $row['upstream_failed'] > 0) {
            return 'FAILURE';
        }
        return 'SUCCESS';
    }

    /**
     * The latest attempt of every task in one run. A retried task appears once,
     * with its final attempt number, so the graph shows outcome not history.
     */
    public function tasksForRun($runKey)
    {
        $runKey = trim((string) $runKey);
        if ($runKey === '') {
            return array();
        }

        $this->ensureSchema();

        // The TMF row a task opened carries what the task did to the data -
        // rows read, rows written, its own error flag. Joining it here is what
        // puts that telemetry on the task graph, instead of making an operator
        // leave the job run to go and look for it.
        $tmfJoin = $this->db->table_exists('tmf')
            ? 'LEFT JOIN tmf t ON t.instance_id = r.tmf_instance_id AND r.tmf_instance_id IS NOT NULL '
            : '';
        $tmfColumns = $tmfJoin === ''
            ? ', NULL AS records_total, NULL AS records_processed, NULL AS tmf_status, NULL AS dimension, NULL AS tmf_errors '
            : ', t.records_total, t.records_processed, t.status AS tmf_status, t.dimension, t.distict_errors AS tmf_errors ';

        $sql = 'SELECT r.task_key, r.attempt, r.status, r.trigger_rule, r.upstream_json, r.tmf_instance_id, '.
            'r.started_at, r.finished_at, r.duration_ms, r.message, r.build_number'.$tmfColumns.
            'FROM job_task_runs r '.
            'JOIN (SELECT task_key, MAX(attempt) AS attempt FROM job_task_runs WHERE run_key = ? GROUP BY task_key) latest '.
            '  ON latest.task_key = r.task_key AND latest.attempt = r.attempt '.
            $tmfJoin.
            'WHERE r.run_key = ? ORDER BY r.id ASC LIMIT '.self::MAX_TASKS;

        $rows = $this->db->query($sql, array($runKey, $runKey))->result_array();
        $tasks = array();
        foreach ($rows as $row) {
            $upstream = json_decode((string) $row['upstream_json'], TRUE);
            $counts = $this->reportedCounts(
                isset($row['records_total']) ? $row['records_total'] : NULL,
                isset($row['records_processed']) ? $row['records_processed'] : NULL
            );
            $tasks[] = array(
                'id' => $row['task_key'],
                'attempt' => (int) $row['attempt'],
                'status' => strtoupper((string) $row['status']),
                'trigger' => strtoupper((string) $row['trigger_rule']),
                'upstream' => is_array($upstream) ? $upstream : array(),
                'tmfInstanceId' => $row['tmf_instance_id'],
                'tmfStatus' => isset($row['tmf_status']) && $row['tmf_status'] !== NULL ? (string) $row['tmf_status'] : NULL,
                'tmfDimension' => isset($row['dimension']) && $row['dimension'] !== NULL ? (string) $row['dimension'] : NULL,
                'tmfHasErrors' => isset($row['tmf_errors']) ? ((int) $row['tmf_errors'] === 1) : FALSE,
                'recordsTotal' => $counts['total'],
                'recordsProcessed' => $counts['processed'],
                'startedAt' => $row['started_at'],
                'finishedAt' => $row['finished_at'],
                'durationMs' => $row['duration_ms'] === NULL ? NULL : (int) $row['duration_ms'],
                'message' => $row['message'],
                'buildNumber' => $row['build_number'] === NULL ? NULL : (int) $row['build_number']
            );
        }
        return $tasks;
    }

    /**
     * What a task actually reported, as integers or NULL for "nothing".
     *
     * TMF stores counts as varchar and opens every transaction at zero, so a
     * task that never called progress() is indistinguishable from one that
     * processed no rows - except that both counts are zero together. Treating
     * that pair as "not reported" is what stops the graph telling an operator
     * that a cleanup step processed 0 rows, which it never claimed to.
     * A task that read none of a known total still reports 0 of 1000.
     */
    private function reportedCounts($total, $processed)
    {
        $total = $this->recordCount($total);
        $processed = $this->recordCount($processed);

        if (($total === NULL || $total === 0) && ($processed === NULL || $processed === 0)) {
            return array('total' => NULL, 'processed' => NULL);
        }

        return array('total' => $total, 'processed' => $processed);
    }

    private function recordCount($value)
    {
        if ($value === NULL || trim((string) $value) === '' || ! is_numeric($value)) {
            return NULL;
        }
        return (int) $value;
    }

    /**
     * The run key a given Jenkins build wrote.
     *
     * Job Execution watches one build, so it asks by build number rather than
     * taking "the latest run", which would show another concurrent build of the
     * same job in the same environment.
     */
    public function runKeyForBuild($jobName, $environment, $buildNumber)
    {
        $jobName = trim((string) $jobName);
        $buildNumber = (int) $buildNumber;
        if ($jobName === '' || $buildNumber <= 0) {
            return '';
        }

        $this->ensureSchema();
        $scope = $this->environmentScope($environment);
        $this->db->select('run_key', FALSE)
            ->from('job_task_runs')
            ->where('job_name', $jobName)
            ->where('build_number', $buildNumber)
            ->where_in('environment', $scope)
            ->order_by('id', 'DESC')
            ->limit(1);
        $row = $this->db->get()->row_array();
        return $row === NULL ? '' : (string) $row['run_key'];
    }

    /**
     * Everything Job View and Job Execution need for one job, in three queries:
     * the declared graph, the recent run list, and the task states of the run
     * being shown.
     */
    public function overview($jobName, $environment, $runKey = '', $buildNumber = 0)
    {
        $graph = $this->graphForJob($jobName, $environment);
        $runs = $this->recentRuns($jobName, $environment, 10);

        $runKey = trim((string) $runKey);
        $buildNumber = (int) $buildNumber;
        $pendingBuild = 0;

        if ($runKey === '' && $buildNumber > 0) {
            foreach ($runs as $run) {
                if ((int) $run['buildNumber'] === $buildNumber) {
                    $runKey = $run['runKey'];
                    break;
                }
            }
            if ($runKey === '') {
                $runKey = $this->runKeyForBuild($jobName, $environment, $buildNumber);
            }
            if ($runKey === '') {
                // A caller that named a build wants that build. A queued build
                // has written nothing yet, and answering with the previous run
                // shows last night's outcomes under this morning's header -
                // which reads as though the new build has already finished.
                $pendingBuild = $buildNumber;
            }
        }

        if ($runKey === '' && $pendingBuild === 0 && ! empty($runs)) {
            $runKey = $runs[0]['runKey'];
        }

        $tasks = $runKey === '' ? array() : $this->tasksForRun($runKey);
        $selected = NULL;
        foreach ($runs as $run) {
            if ($run['runKey'] === $runKey) {
                $selected = $run;
                break;
            }
        }

        return array(
            'graph' => $graph,
            'runs' => $runs,
            'run' => $selected,
            'runKey' => $runKey,
            'tasks' => $tasks,
            'runState' => $this->runState($runKey, $pendingBuild, $tasks),
            'pendingBuild' => $pendingBuild ?: NULL,
            'requestedBuild' => $buildNumber ?: NULL,
            'startedTasks' => count($tasks)
        );
    }

    /**
     * What the caller is looking at, so the panel can say so plainly:
     *   pending  - the build was named but has written nothing yet
     *   running  - at least one task is still going
     *   finished - every task that will run has run
     *   none     - this job has never recorded a run
     */
    private function runState($runKey, $pendingBuild, array $tasks)
    {
        if ($pendingBuild > 0) {
            return 'pending';
        }
        if ($runKey === '' || empty($tasks)) {
            return 'none';
        }
        foreach ($tasks as $task) {
            if ($task['status'] === 'RUNNING') {
                return 'running';
            }
        }
        return 'finished';
    }

    /**
     * Task counts for a list of jobs in one query, for the job list badges.
     */
    public function summaryForJobs(array $jobNames, $environment)
    {
        $names = array();
        foreach ($jobNames as $jobName) {
            $jobName = trim((string) $jobName);
            if ($jobName !== '' && ! in_array($jobName, $names, TRUE)) {
                $names[] = $jobName;
            }
        }
        if (empty($names)) {
            return array();
        }

        $this->ensureSchema();
        $scope = $this->environmentScope($environment);
        $this->db->select('job_name, environment, task_count', FALSE)
            ->from('job_task_graphs')
            ->where_in('job_name', $names)
            ->where_in('environment', $scope);
        $rows = $this->db->get()->result_array();

        $summary = array();
        foreach ($scope as $candidate) {
            foreach ($rows as $row) {
                if ($row['environment'] === $candidate && ! isset($summary[$row['job_name']])) {
                    $summary[$row['job_name']] = (int) $row['task_count'];
                }
            }
        }
        return $summary;
    }

    /** Remove every stored task row for a job, used when a job is deleted. */
    public function deleteForJob($jobName)
    {
        $jobName = trim((string) $jobName);
        if ($jobName === '') {
            return FALSE;
        }
        $this->ensureSchema();
        $this->db->where('job_name', $jobName)->delete('job_task_graphs');
        $this->db->where('job_name', $jobName)->delete('job_task_runs');
        return TRUE;
    }
}
