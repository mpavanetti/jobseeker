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
