<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Registry for Apache Hop projects and the Jenkins jobs that run them.
 *
 * The folders under repository/hop/projects remain the source of truth: a
 * project dropped in by hand is discovered, and deleting a row never deletes a
 * project. These tables add ownership, environment scope, run defaults and a
 * usable index so the Apache Hop screen does not have to walk every Jenkins
 * configuration to show project usage.
 */
class Hop_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `hop_projects` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `project_key` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `storage_path` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
            `entry_file` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL,
            `run_config` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'local',
            `engine` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'container',
            `log_level` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Basic',
            `parameters_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `source` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'upload',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `hop_projects_key` (`project_key`),
            KEY `hop_projects_environment` (`environment`),
            KEY `hop_projects_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `hop_project_jobs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `project_key` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `entry_file` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL,
            `engine` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'container',
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `hop_project_jobs_scope` (`job_name`,`environment`),
            KEY `hop_project_jobs_project` (`project_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        // Durable history for what the Hop Server ran, including the runs a
        // person published straight from the Apache Hop GUI. The server itself
        // forgets a finished execution after its object timeout and loses all
        // of them when it restarts, so the catalog screen and Transaction
        // Monitoring cannot be built on its memory alone.
        $this->db->query("CREATE TABLE IF NOT EXISTS `hop_server_executions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `execution_id` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `display_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `kind` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'pipeline',
            `status` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
            `state` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'running',
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `project_key` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
            `filename` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `source` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'hop-gui',
            `job_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            `tmf_instance_id` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
            `records_total` int(11) NOT NULL DEFAULT 0,
            `records_processed` int(11) NOT NULL DEFAULT 0,
            `errors` int(11) NOT NULL DEFAULT 0,
            `error_logged` tinyint(1) NOT NULL DEFAULT 0,
            `log_text` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `started_at` datetime DEFAULT NULL,
            `ended_at` datetime DEFAULT NULL,
            `first_seen_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `hop_server_executions_id` (`execution_id`),
            KEY `hop_server_executions_started` (`started_at`),
            KEY `hop_server_executions_state` (`state`,`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        // An installation that already has the table predates display_name.
        if ($this->db->table_exists('hop_server_executions')
            && ! $this->db->field_exists('display_name', 'hop_server_executions')) {
            $this->db->query("ALTER TABLE `hop_server_executions`
                ADD COLUMN `display_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `name`");
        }
    }

    public function listProjects($environment = '')
    {
        $this->db->from('hop_projects')->order_by('project_key', 'ASC');
        $environment = strtoupper(trim((string) $environment));
        if ($environment !== '' && $environment !== 'ALL' && $environment !== '*') {
            $this->db->group_start()->where('environment', $environment)->or_where('environment', 'ALL')->group_end();
        }
        return $this->db->get()->result_array();
    }

    public function getProject($projectKey)
    {
        return $this->db->where('project_key', (string) $projectKey)->get('hop_projects')->row_array();
    }

    /**
     * Insert or update one project row. Returns the stored row.
     */
    public function saveProject($values)
    {
        $projectKey = isset($values['project_key']) ? (string) $values['project_key'] : '';
        if ($projectKey === '') {
            return FALSE;
        }

        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->getProject($projectKey);
        $values['updated_at'] = $now;
        if ($existing) {
            $this->db->where('project_key', $projectKey)->update('hop_projects', $values);
        } else {
            $values['created_at'] = $now;
            $this->db->insert('hop_projects', $values);
        }

        return $this->getProject($projectKey);
    }

    public function deleteProject($projectKey)
    {
        $this->db->where('project_key', (string) $projectKey)->delete('hop_projects');
        $this->db->where('project_key', (string) $projectKey)->delete('hop_project_jobs');
        return TRUE;
    }

    public function linkJob($projectKey, $jobName, $environment, $entryFile, $engine)
    {
        $projectKey = (string) $projectKey;
        $jobName = (string) $jobName;
        $environment = strtoupper(trim((string) $environment));
        if ($projectKey === '' || $jobName === '') {
            return FALSE;
        }

        $now = gmdate('Y-m-d H:i:s');
        $values = array(
            'project_key' => $projectKey,
            'job_name' => $jobName,
            'environment' => $environment === '' ? 'ALL' : $environment,
            'entry_file' => (string) $entryFile,
            'engine' => (string) $engine,
            'updated_at' => $now
        );

        // One Jenkins controller cannot hold two jobs with the same full name.
        // Updating a job's environment or Hop project must therefore move its
        // usage row, not leave an obsolete row under the previous environment.
        $existing = $this->db
            ->where('job_name', $jobName)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('hop_project_jobs')
            ->row_array();

        if ($existing) {
            $this->db->where('id', (int) $existing['id'])->update('hop_project_jobs', $values);
            $this->db->where('job_name', $jobName)->where('id !=', (int) $existing['id'])->delete('hop_project_jobs');
            return TRUE;
        }

        $values['created_at'] = $now;
        $this->db->insert('hop_project_jobs', $values);
        return TRUE;
    }

    public function unlinkJob($jobName, $environment = '')
    {
        $this->db->where('job_name', (string) $jobName);
        $environment = strtoupper(trim((string) $environment));
        if ($environment !== '') {
            $this->db->where('environment', $environment);
        }
        $this->db->delete('hop_project_jobs');
        return TRUE;
    }

    public function jobsForProject($projectKey)
    {
        return $this->db
            ->where('project_key', (string) $projectKey)
            ->order_by('job_name', 'ASC')
            ->get('hop_project_jobs')
            ->result_array();
    }

    public function jobUsage()
    {
        $usage = array();
        foreach ($this->db->get('hop_project_jobs')->result_array() as $row) {
            $key = (string) $row['project_key'];
            if (! isset($usage[$key])) {
                $usage[$key] = array();
            }
            $usage[$key][] = $row;
        }
        return $usage;
    }

    /** Every Jenkins job that runs a Hop project, for screens that draw it. */
    public function hopJobs($environment = '')
    {
        $this->db->from('hop_project_jobs')->order_by('job_name', 'ASC');
        $environment = strtoupper(trim((string) $environment));
        if ($environment !== '' && $environment !== 'ALL' && $environment !== '*') {
            $this->db->group_start()->where('environment', $environment)->or_where('environment', 'ALL')->group_end();
        }
        return $this->db->get()->result_array();
    }

    public function projectForJob($jobName, $environment = '')
    {
        $this->db->where('job_name', (string) $jobName);
        $environment = strtoupper(trim((string) $environment));
        if ($environment !== '') {
            $this->db->group_start()->where('environment', $environment)->or_where('environment', 'ALL')->group_end();
        }
        return $this->db->order_by('id', 'DESC')->limit(1)->get('hop_project_jobs')->row_array();
    }

    // -- Hop Server executions ---------------------------------------------

    public function findExecution($executionId)
    {
        return $this->db->where('execution_id', (string) $executionId)->get('hop_server_executions')->row_array();
    }

    /**
     * Insert or update one Hop Server execution. The execution id the server
     * assigns is the identity, so the same run seen on ten polls stays one row.
     */
    public function saveExecution($values)
    {
        $executionId = isset($values['execution_id']) ? (string) $values['execution_id'] : '';
        if ($executionId === '') {
            return FALSE;
        }

        $now = gmdate('Y-m-d H:i:s');
        $values['updated_at'] = $now;
        $existing = $this->findExecution($executionId);
        if ($existing) {
            $this->db->where('execution_id', $executionId)->update('hop_server_executions', $values);
        } else {
            $values['first_seen_at'] = $now;
            $this->db->insert('hop_server_executions', $values);
        }

        return $this->findExecution($executionId);
    }

    public function listExecutions($environment = 'ALL', $limit = 100)
    {
        $this->db->from('hop_server_executions');
        $environment = strtoupper(trim((string) $environment));
        if ($environment !== '' && $environment !== 'ALL' && $environment !== '*') {
            $this->db->group_start()->where('environment', $environment)->or_where('environment', 'ALL')->group_end();
        }
        return $this->db
            ->order_by('COALESCE(started_at, first_seen_at)', 'DESC', FALSE)
            ->limit(max(1, (int) $limit))
            ->get()
            ->result_array();
    }

    /** Drop history older than the retention window, so the table stays small. */
    public function pruneExecutions($maxAgeDays = 30)
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, (int) $maxAgeDays) * 86400);
        $this->db
            ->where('state !=', 'running')
            ->where('COALESCE(started_at, first_seen_at) < '.$this->db->escape($cutoff), NULL, FALSE)
            ->delete('hop_server_executions');
        return TRUE;
    }

    // -- Transaction Monitoring ingestion ----------------------------------

    /**
     * Open or update the Transaction Monitoring row for one Hop Server run.
     *
     * The Hop execution id is the TMF instance id, which makes the ingestion
     * idempotent: whatever the poll sees, one Hop run is one TMF row. Rows are
     * written the same way the Python SDK writes them, because the TMF screens,
     * the Dashboard and the e-mail templates read one shape.
     */
    public function recordTmfRun($values)
    {
        $instanceId = isset($values['instance_id']) ? (string) $values['instance_id'] : '';
        $jobName = isset($values['job_name']) ? (string) $values['job_name'] : '';
        if ($instanceId === '' || $jobName === '') {
            return FALSE;
        }

        $state = isset($values['state']) ? (string) $values['state'] : 'running';
        $environment = strtoupper((string) (isset($values['environment']) ? $values['environment'] : ''));
        $startedAt = isset($values['started_at']) && $values['started_at'] !== '' ? (string) $values['started_at'] : NULL;
        $endedAt = isset($values['ended_at']) && $values['ended_at'] !== '' ? (string) $values['ended_at'] : NULL;
        $message = substr((string) (isset($values['message']) ? $values['message'] : ''), 0, 4000);

        $row = array(
            'status' => $state,
            'job_name' => $jobName,
            'reprocess' => 0,
            'event_text' => $state === 'error' ? 'Error(s) Found' : (string) (isset($values['event_text']) ? $values['event_text'] : 'Apache Hop'),
            'dimension' => (string) (isset($values['dimension']) ? $values['dimension'] : 'hop-server'),
            'environment' => $environment,
            'records_total' => (string) (int) (isset($values['records_total']) ? $values['records_total'] : 0),
            'records_processed' => (string) (int) (isset($values['records_processed']) ? $values['records_processed'] : 0),
            'distict_errors' => $state === 'error' ? 1 : 0,
            'warnings' => '0',
            'hostname' => (string) (isset($values['hostname']) ? $values['hostname'] : 'hop-server'),
            'username' => (string) (isset($values['username']) ? $values['username'] : 'hop-server'),
            'msg' => $message
        );

        $existing = $this->db->where('instance_id', $instanceId)->limit(1)->get('tmf')->row_array();
        if ($existing) {
            $this->db->set($row);
            $this->db->set('last_activity', 'now()', FALSE);
            if ($startedAt !== NULL && $endedAt !== NULL) {
                $this->db->set('running_time', 'TIMEDIFF('.$this->db->escape($endedAt).', '.$this->db->escape($startedAt).')', FALSE);
            }
            $this->db->where('instance_id', $instanceId)->update('tmf');
            return (int) $existing['id'];
        }

        $row['interface_id'] = '1';
        $row['instance_id'] = $instanceId;
        $row['start_time'] = $startedAt;
        $this->db->set($row);
        $this->db->set('last_activity', 'now()', FALSE);
        if ($startedAt !== NULL && $endedAt !== NULL) {
            $this->db->set('running_time', 'TIMEDIFF('.$this->db->escape($endedAt).', '.$this->db->escape($startedAt).')', FALSE);
        }
        $this->db->insert('tmf');
        return (int) $this->db->insert_id();
    }

    /** One tmf_error row per Hop error line, so the TMF error view is usable. */
    public function recordTmfErrors($instanceId, $jobName, $errors)
    {
        $instanceId = (string) $instanceId;
        if ($instanceId === '' || ! $errors) {
            return 0;
        }

        $now = gmdate('Y-m-d H:i:s');
        $rows = array();
        foreach ((array) $errors as $error) {
            $origin = trim((string) (isset($error['origin']) ? $error['origin'] : ''));
            $rows[] = array(
                'tmf_id' => $instanceId,
                'job_name' => (string) $jobName,
                'moment' => $now,
                'type' => 'Apache Hop',
                'origin' => substr($origin === '' ? 'Apache Hop (Hop Server)' : 'Apache Hop / '.$origin, 0, 200),
                'message' => substr((string) (isset($error['message']) ? $error['message'] : ''), 0, 5000),
                'code' => 1
            );
        }

        if ($rows) {
            $this->db->insert_batch('tmf_error', $rows);
        }
        return count($rows);
    }

    public function statistics($environment = 'ALL')
    {
        $projects = count($this->listProjects($environment));
        $jobs = (int) $this->db->count_all_results('hop_project_jobs');
        return array('projects' => $projects, 'jobs' => $jobs);
    }
}

/* End of file Hop_model.php */
/* Location: ./application/models/Hop_model.php */
