<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Persistence for the Spark side of the Data Engineering plane:
 * runtime catalogue, cluster specs, job definitions and run history.
 *
 * Like Pipeline_model, the schema is self-healing (CREATE TABLE IF NOT EXISTS at
 * load) so the feature works on installs that predate the db_setup.sql section.
 */
class SparkCompute_model extends CI_Model
{
    const RUN_TERMINAL = array('SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT');

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `spark_runtimes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `display_name` varchar(160) COLLATE utf8_unicode_ci NOT NULL,
            `spark_version` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
            `image_repository` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `image_tag` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
            `base_image` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `description` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `is_default` tinyint(1) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` int(11) NOT NULL DEFAULT 100,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `spark_runtime_key` (`runtime_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `spark_clusters` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `cluster_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `group_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'General',
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `driver_cores` int(11) NOT NULL DEFAULT 1,
            `driver_memory_mb` int(11) NOT NULL DEFAULT 1024,
            `worker_cores` int(11) NOT NULL DEFAULT 1,
            `worker_memory_mb` int(11) NOT NULL DEFAULT 1024,
            `min_workers` int(11) NOT NULL DEFAULT 1,
            `max_workers` int(11) NOT NULL DEFAULT 2,
            `autoscale` tinyint(1) NOT NULL DEFAULT 0,
            `idle_timeout_minutes` int(11) NOT NULL DEFAULT 10,
            `spark_conf_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `env_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `version` int(11) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `spark_cluster_scope` (`cluster_key`,`environment`),
            KEY `spark_cluster_environment` (`environment`),
            KEY `spark_cluster_group` (`group_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `spark_jobs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `job_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `group_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'General',
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `cluster_id` int(11) NOT NULL,
            `source_type` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'repository',
            `entry_point` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
            `application_args` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `inline_code` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `requirements_txt` text COLLATE utf8_unicode_ci DEFAULT NULL,
            `spark_submit_conf_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `schedule_cron` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `workers` int(11) DEFAULT NULL,
            `jenkins_job_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `version` int(11) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `spark_job_scope` (`job_key`,`environment`),
            KEY `spark_job_environment` (`environment`),
            KEY `spark_job_cluster` (`cluster_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `spark_job_runs` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `job_id` int(11) NOT NULL,
            `run_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `status` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'QUEUED',
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `triggered_by` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `cluster_network` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `master_container_id` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
            `driver_container_id` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
            `worker_container_ids_json` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `jenkins_build_number` int(10) unsigned DEFAULT NULL,
            `jenkins_queue_id` bigint(20) unsigned DEFAULT NULL,
            `worker_count` int(11) NOT NULL DEFAULT 0,
            `exit_code` int(11) DEFAULT NULL,
            `log_tail` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `error_message` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `started_at` datetime NOT NULL,
            `provisioned_at` datetime DEFAULT NULL,
            `completed_at` datetime DEFAULT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `spark_job_run_key` (`run_key`),
            KEY `spark_job_run_job` (`job_id`,`id`),
            KEY `spark_job_run_status` (`status`,`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        if (! $this->db->field_exists('master_container_id', 'spark_job_runs')) {
            $this->db->query('ALTER TABLE `spark_job_runs` ADD `master_container_id` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `cluster_network`');
        }
        if (! $this->db->field_exists('jenkins_build_number', 'spark_job_runs')) {
            $this->db->query('ALTER TABLE `spark_job_runs` ADD `jenkins_build_number` int(10) unsigned DEFAULT NULL AFTER `worker_container_ids_json`');
        }
        if (! $this->db->field_exists('jenkins_queue_id', 'spark_job_runs')) {
            $this->db->query('ALTER TABLE `spark_job_runs` ADD `jenkins_queue_id` bigint(20) unsigned DEFAULT NULL AFTER `jenkins_build_number`');
        }
        if (! $this->db->field_exists('workers', 'spark_jobs')) {
            $this->db->query('ALTER TABLE `spark_jobs` ADD `workers` int(11) DEFAULT NULL AFTER `schedule_cron`');
        }
        if (! $this->db->field_exists('persistent_cluster_id', 'spark_job_runs')) {
            $this->db->query('ALTER TABLE `spark_job_runs` ADD `persistent_cluster_id` int(11) DEFAULT NULL AFTER `job_id`');
        }
        // interactive = notebook on an All-Purpose cluster; batch = spark-submit
        // (ephemeral Job cluster, or submit to an All-Purpose one).
        if (! $this->db->field_exists('mode', 'spark_jobs')) {
            $this->db->query("ALTER TABLE `spark_jobs` ADD `mode` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'batch' AFTER `source_type`");
        }
        if (! $this->db->field_exists('jenkins_job_name', 'spark_jobs')) {
            $this->db->query('ALTER TABLE `spark_jobs` ADD `jenkins_job_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `workers`');
        }

        // Two cluster modes: 'job' (ephemeral, provisioned per run) and
        // 'persistent' (long-lived, Start/Stop from the Spark Clusters screen,
        // attachable from a notebook in OpenVSCode).
        if (! $this->db->field_exists('lifecycle', 'spark_clusters')) {
            $this->db->query("ALTER TABLE `spark_clusters` ADD `lifecycle` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'job' AFTER `runtime_key`");
        }

        // One row per persistent cluster: the live containers, published host
        // ports (reachable from the app + editor as docker-runtime:<port>), and
        // the idle clock.
        $this->db->query("CREATE TABLE IF NOT EXISTS `spark_cluster_instances` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `cluster_id` int(11) NOT NULL,
            `cluster_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'STOPPED',
            `network` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `master_container_id` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
            `worker_container_ids_json` varchar(4000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `jupyter_container_id` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
            `jupyter_port` int(11) DEFAULT NULL,
            `jupyter_token` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
            `spark_ui_port` int(11) DEFAULT NULL,
            `master_port` int(11) DEFAULT NULL,
            `worker_count` int(11) NOT NULL DEFAULT 0,
            `error_message` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `triggered_by` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            `started_at` datetime DEFAULT NULL,
            `last_seen_at` datetime DEFAULT NULL,
            `stopped_at` datetime DEFAULT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `spark_cluster_instance_cluster` (`cluster_id`),
            KEY `spark_cluster_instance_status` (`status`,`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->seedRuntimes();
    }

    private function seedRuntimes()
    {
        if ((int) $this->db->count_all('spark_runtimes') > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert_batch('spark_runtimes', array(
            array(
                'runtime_key' => 'spark-4.0-python', 'display_name' => 'Spark 4.0.0 (Python 3, Java 17)',
                'spark_version' => '4.0.0', 'image_repository' => 'jobseeker/spark-runtime', 'image_tag' => '4.0.0-python',
                'base_image' => 'apache/spark:4.0.0',
                'description' => 'PySpark runtime with pandas and pyarrow for DataFrame interchange.',
                'is_default' => 1, 'is_active' => 1, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now,
            ),
            array(
                'runtime_key' => 'spark-4.0-scala', 'display_name' => 'Spark 4.0.0 (Scala 2.13, Java 17)',
                'spark_version' => '4.0.0', 'image_repository' => 'jobseeker/spark-runtime', 'image_tag' => '4.0.0-scala',
                'base_image' => 'apache/spark:4.0.0',
                'description' => 'JVM Spark runtime for Scala and Java workloads and spark-submit JARs.',
                'is_default' => 0, 'is_active' => 1, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now,
            ),
        ));
    }

    // --- runtimes ---------------------------------------------------

    public function listRuntimes($activeOnly = TRUE)
    {
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }
        return $this->db->order_by('sort_order', 'ASC')->order_by('display_name', 'ASC')->get('spark_runtimes')->result();
    }

    public function getRuntime($runtimeKey)
    {
        return $this->db->where('runtime_key', (string) $runtimeKey)->get('spark_runtimes')->row();
    }

    // --- clusters -------------------------------------------------

    public function listClusters($environment = '')
    {
        $this->db->from('spark_clusters');
        if ($environment !== '' && $environment !== 'ALL') {
            $this->db->where('environment', $environment);
        }
        return $this->db->order_by('group_name', 'ASC')->order_by('name', 'ASC')->get()->result();
    }

    public function getCluster($id)
    {
        return $this->db->where('id', (int) $id)->get('spark_clusters')->row();
    }

    public function clusterScopeExists($clusterKey, $environment, $excludeId = 0)
    {
        $this->db->from('spark_clusters')->where('cluster_key', (string) $clusterKey)->where('environment', (string) $environment);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveCluster($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->set('version', 'version + 1', FALSE)->update('spark_clusters', $data);
            return (int) $id;
        }
        $this->db->insert('spark_clusters', $data);
        return (int) $this->db->insert_id();
    }

    public function deleteCluster($id)
    {
        $this->db->where('id', (int) $id)->delete('spark_clusters');
        return $this->db->affected_rows();
    }

    public function clusterHasJobs($id)
    {
        return (int) $this->db->where('cluster_id', (int) $id)->count_all_results('spark_jobs') > 0;
    }

    // --- persistent cluster instances -----------------------------

    /** The (single) instance row for a persistent cluster, or NULL. */
    public function getClusterInstance($clusterId)
    {
        return $this->db->where('cluster_id', (int) $clusterId)->get('spark_cluster_instances')->row();
    }

    /**
     * Insert-or-update the instance row for a cluster. Always stamps updated_at.
     * Returns the fresh row.
     */
    public function upsertClusterInstance($clusterId, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($this->getClusterInstance($clusterId)) {
            $this->db->where('cluster_id', (int) $clusterId)->update('spark_cluster_instances', $data);
        } else {
            $data['cluster_id'] = (int) $clusterId;
            $this->db->insert('spark_cluster_instances', $data);
        }
        return $this->getClusterInstance($clusterId);
    }

    public function deleteClusterInstance($clusterId)
    {
        $this->db->where('cluster_id', (int) $clusterId)->delete('spark_cluster_instances');
    }

    /** Bump the idle clock so a live cluster (job submit, monitor poll) is not reaped. */
    public function touchClusterInstance($clusterId)
    {
        $this->db->where('cluster_id', (int) $clusterId)->update('spark_cluster_instances', array(
            'last_seen_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    /** Running persistent instances joined to their cluster spec. */
    public function runningPersistentInstances()
    {
        return $this->db->select('i.*, c.name AS cluster_name, c.runtime_key AS runtime_key, c.idle_timeout_minutes AS idle_timeout_minutes')
            ->from('spark_cluster_instances i')
            ->join('spark_clusters c', 'c.id = i.cluster_id', 'left')
            ->where_in('i.status', array('PROVISIONING', 'RUNNING', 'STOPPING'))
            ->order_by('i.id', 'ASC')
            ->get()->result();
    }

    /**
     * Running persistent instances whose per-cluster idle timeout has elapsed
     * since last_seen_at (0 / NULL timeout = never idle-reap).
     */
    public function idlePersistentInstances($fallbackMinutes = 120)
    {
        $now = time();
        $stale = array();
        foreach ($this->runningPersistentInstances() as $row) {
            $minutes = (int) $row->idle_timeout_minutes;
            if ($minutes <= 0) {
                $minutes = max(1, (int) $fallbackMinutes);
            }
            $seen = $row->last_seen_at ? strtotime($row->last_seen_at.' UTC') : ($row->started_at ? strtotime($row->started_at.' UTC') : $now);
            if ($now - $seen >= $minutes * 60) {
                $stale[] = $row;
            }
        }
        return $stale;
    }

    // --- jobs ---------------------------------------------------

    public function listJobs($environment = '')
    {
        $this->db->select('j.*, c.name AS cluster_name, c.runtime_key AS runtime_key')
            ->from('spark_jobs j')
            ->join('spark_clusters c', 'c.id = j.cluster_id', 'left');
        if ($environment !== '' && $environment !== 'ALL') {
            $this->db->where('j.environment', $environment);
        }
        return $this->db->order_by('j.group_name', 'ASC')->order_by('j.name', 'ASC')->get()->result();
    }

    public function getJob($id)
    {
        return $this->db->where('id', (int) $id)->get('spark_jobs')->row();
    }

    /** Used by the agent-callable trigger endpoint, which only knows the job's scope. */
    public function getJobByScope($jobKey, $environment)
    {
        return $this->db->where('job_key', (string) $jobKey)->where('environment', (string) $environment)
            ->get('spark_jobs')->row();
    }

    /** Resolve a Spark job from the Jenkins job name (Job Execution only knows that). */
    public function getJobByJenkinsName($jenkinsJobName)
    {
        $jenkinsJobName = trim((string) $jenkinsJobName);
        if ($jenkinsJobName === '') {
            return NULL;
        }
        return $this->db->where('jenkins_job_name', $jenkinsJobName)->get('spark_jobs')->row();
    }

    /** The run recorded for a given Jenkins build of a Spark job. */
    public function getRunByBuild($jobId, $buildNumber)
    {
        return $this->db->where('job_id', (int) $jobId)->where('jenkins_build_number', (int) $buildNumber)
            ->order_by('id', 'DESC')->limit(1)->get('spark_job_runs')->row();
    }

    public function jobScopeExists($jobKey, $environment, $excludeId = 0)
    {
        $this->db->from('spark_jobs')->where('job_key', (string) $jobKey)->where('environment', (string) $environment);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveJob($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->set('version', 'version + 1', FALSE)->update('spark_jobs', $data);
            return (int) $id;
        }
        $this->db->insert('spark_jobs', $data);
        return (int) $this->db->insert_id();
    }

    public function deleteJob($id)
    {
        $this->db->where('id', (int) $id)->delete('spark_jobs');
        return $this->db->affected_rows();
    }

    // --- job_info registry (so Spark jobs appear in Job List like any other job) ---

    /**
     * Mirror a Spark job into the shared `job_info` registry keyed by the
     * generated Jenkins job name. Idempotent: replaces any prior row.
     */
    public function registerJobInfo($jenkinsJobName, $owner)
    {
        $jenkinsJobName = trim((string) $jenkinsJobName);
        if ($jenkinsJobName === '' || ! $this->db->table_exists('job_info')) {
            return;
        }
        $this->db->where('job_name', $jenkinsJobName)->where('component_type', 'spark')->delete('job_info');
        $this->db->insert('job_info', array(
            'job_name' => $jenkinsJobName,
            'job_component' => 'PySpark',
            'component_type' => 'spark',
            'creation_date' => date('Y-m-d H:i:s.u'),
            'owner' => substr((string) $owner, 0, 50),
        ));
    }

    public function unregisterJobInfo($jenkinsJobName)
    {
        $jenkinsJobName = trim((string) $jenkinsJobName);
        if ($jenkinsJobName === '' || ! $this->db->table_exists('job_info')) {
            return;
        }
        $this->db->where('job_name', $jenkinsJobName)->where('component_type', 'spark')->delete('job_info');
    }

    // --- runs ---------------------------------------------------

    public function createSparkRun($jobId, $environment, $triggeredBy, $workerCount)
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('spark_job_runs', array(
            'job_id' => (int) $jobId,
            'run_key' => bin2hex(random_bytes(6)),
            'status' => 'QUEUED',
            'environment' => (string) $environment,
            'triggered_by' => (string) $triggeredBy,
            'worker_count' => (int) $workerCount,
            'started_at' => $now,
            'updated_at' => $now,
        ));
        return (int) $this->db->insert_id();
    }

    public function getSparkRun($id)
    {
        return $this->db->where('id', (int) $id)->get('spark_job_runs')->row();
    }

    public function updateSparkRun($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->update('spark_job_runs', $data);
    }

    public function recentSparkRuns($jobId, $limit = 12)
    {
        return $this->db->where('job_id', (int) $jobId)
            ->order_by('id', 'DESC')
            ->limit(max(1, min(50, (int) $limit)))
            ->get('spark_job_runs')
            ->result();
    }

    /** Recent runs across every Spark job, joined to job + cluster names, for the Spark Activity dashboard. */
    public function recentSparkRunsAll($environment = '', $limit = 40)
    {
        $this->db->select('r.*, j.name AS job_name, j.job_key AS job_key, j.jenkins_job_name AS jenkins_job_name, j.mode AS mode, c.name AS cluster_name, c.lifecycle AS cluster_lifecycle')
            ->from('spark_job_runs r')
            ->join('spark_jobs j', 'j.id = r.job_id', 'left')
            ->join('spark_clusters c', 'c.id = j.cluster_id', 'left');
        if ($environment !== '' && $environment !== 'ALL') {
            $this->db->where('r.environment', $environment);
        }
        return $this->db->order_by('r.id', 'DESC')->limit(max(1, min(200, (int) $limit)))->get()->result();
    }

    public function activeSparkRunCount()
    {
        return (int) $this->db->where_not_in('status', self::RUN_TERMINAL)->count_all_results('spark_job_runs');
    }

    public function staleActiveRuns($olderThanSeconds = 60)
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(10, (int) $olderThanSeconds));
        return $this->db->where_not_in('status', self::RUN_TERMINAL)
            ->where('updated_at <', $cutoff)
            ->order_by('id', 'ASC')
            ->limit(25)
            ->get('spark_job_runs')
            ->result();
    }
}
