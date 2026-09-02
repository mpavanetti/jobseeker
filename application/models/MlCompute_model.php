<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Persistence for the Machine Learning side of the Data Engineering plane:
 * Miniconda runtime catalogue, job definitions and run history. Schema is
 * self-healing at load, matching SparkCompute_model.
 */
class MlCompute_model extends CI_Model
{
    const RUN_TERMINAL = array('SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT');

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_runtimes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `display_name` varchar(160) COLLATE utf8_unicode_ci NOT NULL,
            `image_repository` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `image_tag` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
            `base_image` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `conda_based` tinyint(1) NOT NULL DEFAULT 1,
            `library_summary` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `description` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `is_default` tinyint(1) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` int(11) NOT NULL DEFAULT 100,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_runtime_key` (`runtime_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_jobs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `job_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `group_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'General',
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `source_type` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'repository',
            `entry_point` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
            `application_args` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `inline_code` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `requirements_txt` text COLLATE utf8_unicode_ci DEFAULT NULL,
            `cpu_limit` decimal(4,2) NOT NULL DEFAULT 1.00,
            `memory_limit_mb` int(11) NOT NULL DEFAULT 2048,
            `env_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `schedule_cron` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `version` int(11) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_job_scope` (`job_key`,`environment`),
            KEY `ml_job_environment` (`environment`),
            KEY `ml_job_runtime` (`runtime_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_job_runs` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `job_id` int(11) NOT NULL,
            `run_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `status` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'QUEUED',
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `triggered_by` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `container_id` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
            `exit_code` int(11) DEFAULT NULL,
            `log_tail` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `error_message` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `started_at` datetime NOT NULL,
            `completed_at` datetime DEFAULT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_job_run_key` (`run_key`),
            KEY `ml_job_run_job` (`job_id`,`id`),
            KEY `ml_job_run_status` (`status`,`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->seedRuntimes();
    }

    private function seedRuntimes()
    {
        if ((int) $this->db->count_all('ml_runtimes') > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert_batch('ml_runtimes', array(
            array(
                'runtime_key' => 'ml-cpu', 'display_name' => 'ML CPU (Miniconda)',
                'image_repository' => 'jobseeker/ml-runtime', 'image_tag' => 'cpu',
                'base_image' => 'continuumio/miniconda3', 'conda_based' => 1,
                'library_summary' => 'numpy, pandas, scikit-learn, xgboost, matplotlib, joblib',
                'description' => 'General purpose CPU machine-learning runtime built on Miniconda.',
                'is_default' => 1, 'is_active' => 1, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now,
            ),
            array(
                'runtime_key' => 'ml-dl-cpu', 'display_name' => 'Deep Learning CPU (Miniconda)',
                'image_repository' => 'jobseeker/ml-runtime', 'image_tag' => 'dl-cpu',
                'base_image' => 'continuumio/miniconda3', 'conda_based' => 1,
                'library_summary' => 'pytorch (cpu), lightning, torchvision, pandas, scikit-learn',
                'description' => 'CPU deep-learning runtime with PyTorch and Lightning on Miniconda.',
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
        return $this->db->order_by('sort_order', 'ASC')->order_by('display_name', 'ASC')->get('ml_runtimes')->result();
    }

    public function getRuntime($runtimeKey)
    {
        return $this->db->where('runtime_key', (string) $runtimeKey)->get('ml_runtimes')->row();
    }

    public function saveRuntime($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('ml_runtimes', $data);
            return (int) $id;
        }
        $this->db->insert('ml_runtimes', $data);
        return (int) $this->db->insert_id();
    }

    public function getRuntimeById($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_runtimes')->row();
    }

    public function deleteRuntime($id)
    {
        $this->db->where('id', (int) $id)->delete('ml_runtimes');
        return $this->db->affected_rows();
    }

    public function runtimeKeyExists($runtimeKey, $excludeId = 0)
    {
        $this->db->from('ml_runtimes')->where('runtime_key', (string) $runtimeKey);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function runtimeInUse($runtimeKey)
    {
        return (int) $this->db->where('runtime_key', (string) $runtimeKey)->count_all_results('ml_jobs') > 0;
    }

    // --- jobs ---------------------------------------------------

    public function listJobs($environment = '')
    {
        $this->db->from('ml_jobs');
        if ($environment !== '' && $environment !== 'ALL') {
            $this->db->where('environment', $environment);
        }
        return $this->db->order_by('group_name', 'ASC')->order_by('name', 'ASC')->get()->result();
    }

    public function getJob($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_jobs')->row();
    }

    public function jobScopeExists($jobKey, $environment, $excludeId = 0)
    {
        $this->db->from('ml_jobs')->where('job_key', (string) $jobKey)->where('environment', (string) $environment);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveJob($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->set('version', 'version + 1', FALSE)->update('ml_jobs', $data);
            return (int) $id;
        }
        $this->db->insert('ml_jobs', $data);
        return (int) $this->db->insert_id();
    }

    public function deleteJob($id)
    {
        $this->db->where('id', (int) $id)->delete('ml_jobs');
        return $this->db->affected_rows();
    }

    // --- runs ---------------------------------------------------

    public function createMlRun($jobId, $environment, $triggeredBy)
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('ml_job_runs', array(
            'job_id' => (int) $jobId,
            'run_key' => bin2hex(random_bytes(6)),
            'status' => 'QUEUED',
            'environment' => (string) $environment,
            'triggered_by' => (string) $triggeredBy,
            'started_at' => $now,
            'updated_at' => $now,
        ));
        return (int) $this->db->insert_id();
    }

    public function getMlRun($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_job_runs')->row();
    }

    public function updateMlRun($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->update('ml_job_runs', $data);
    }

    public function recentMlRuns($jobId, $limit = 12)
    {
        return $this->db->where('job_id', (int) $jobId)
            ->order_by('id', 'DESC')
            ->limit(max(1, min(50, (int) $limit)))
            ->get('ml_job_runs')
            ->result();
    }

    public function activeMlRunCount()
    {
        return (int) $this->db->where_not_in('status', self::RUN_TERMINAL)->count_all_results('ml_job_runs');
    }

    public function staleActiveRuns($olderThanSeconds = 60)
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(10, (int) $olderThanSeconds));
        return $this->db->where_not_in('status', self::RUN_TERMINAL)
            ->where('updated_at <', $cutoff)
            ->order_by('id', 'ASC')
            ->limit(25)
            ->get('ml_job_runs')
            ->result();
    }
}
