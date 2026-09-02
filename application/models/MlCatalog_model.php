<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Catalogue side of the Machine Learning platform: runtime images, the
 * customisable sample gallery, experiments and job definitions. Schema is
 * self-healing at load (same pattern as DataAssets_model) so a running stack
 * picks the tables up without a migration step; db_setup.sql keeps a fresh
 * install schema-complete.
 */
class MlCatalog_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
        $this->seedRuntimes();
        $this->seedSamples();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_runtime` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `display_name` varchar(160) COLLATE utf8_unicode_ci NOT NULL,
            `kind` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'cpu',
            `image_repository` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `image_tag` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
            `base_image` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'continuumio/miniconda3',
            `library_summary` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `description` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `default_cpu_limit` decimal(5,2) NOT NULL DEFAULT 1.00,
            `default_memory_mb` int(11) NOT NULL DEFAULT 2048,
            `is_default` tinyint(1) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` int(11) NOT NULL DEFAULT 100,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_runtime_key` (`runtime_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_sample` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `sample_key` varchar(96) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `category` varchar(48) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'tabular',
            `run_type` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'train',
            `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `entry_point` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
            `code` longtext COLLATE utf8_unicode_ci NOT NULL,
            `params_schema_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `dataset_roles_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `tags` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
            `is_builtin` tinyint(1) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` int(11) NOT NULL DEFAULT 100,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_sample_key` (`sample_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_experiment` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `experiment_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `primary_metric` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `metric_goal` varchar(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'max',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_experiment_scope` (`experiment_key`,`environment`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_job` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `job_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `group_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'General',
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `experiment_id` int(11) DEFAULT NULL,
            `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `sample_key` varchar(96) COLLATE utf8_unicode_ci DEFAULT NULL,
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `source_type` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'inline',
            `entry_point` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
            `entrypoint` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'main.py',
            `inline_code` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `dependency_mode` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'requirements',
            `requirements_txt` text COLLATE utf8_unicode_ci DEFAULT NULL,
            `pyproject_text` text COLLATE utf8_unicode_ci DEFAULT NULL,
            `dockerfile` text COLLATE utf8_unicode_ci DEFAULT NULL,
            `image_tag` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            `image_state` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'none',
            `image_digest` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `image_built_at` datetime DEFAULT NULL,
            `image_build_log` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `workspace_hash` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
            `application_args` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `params_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `dataset_bindings_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `env_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `run_type` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `run_type_source` varchar(12) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'auto',
            `run_type_confidence` decimal(4,3) NOT NULL DEFAULT 0.000,
            `introspection_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `cpu_limit` decimal(5,2) NOT NULL DEFAULT 1.00,
            `memory_limit_mb` int(11) NOT NULL DEFAULT 2048,
            `timeout_seconds` int(11) NOT NULL DEFAULT 3600,
            `schedule_cron` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `jenkins_job_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `version` int(11) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_job_scope` (`job_key`,`environment`),
            KEY `ml_job_environment` (`environment`),
            KEY `ml_job_runtime` (`runtime_key`),
            KEY `ml_job_experiment` (`experiment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        // v2: workspace-backed jobs + per-job baked image. Additive on upgrade.
        $this->db->query("ALTER TABLE `ml_job`
            ADD COLUMN IF NOT EXISTS `entrypoint` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'main.py' AFTER `entry_point`,
            ADD COLUMN IF NOT EXISTS `dependency_mode` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'requirements' AFTER `inline_code`,
            ADD COLUMN IF NOT EXISTS `pyproject_text` text COLLATE utf8_unicode_ci DEFAULT NULL AFTER `requirements_txt`,
            ADD COLUMN IF NOT EXISTS `dockerfile` text COLLATE utf8_unicode_ci DEFAULT NULL AFTER `pyproject_text`,
            ADD COLUMN IF NOT EXISTS `image_tag` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `dockerfile`,
            ADD COLUMN IF NOT EXISTS `image_state` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'none' AFTER `image_tag`,
            ADD COLUMN IF NOT EXISTS `image_digest` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `image_state`,
            ADD COLUMN IF NOT EXISTS `image_built_at` datetime DEFAULT NULL AFTER `image_digest`,
            ADD COLUMN IF NOT EXISTS `image_build_log` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL AFTER `image_built_at`,
            ADD COLUMN IF NOT EXISTS `workspace_hash` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `image_build_log`");
    }

    private function seedRuntimes()
    {
        if ((int) $this->db->count_all('ml_runtime') > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert_batch('ml_runtime', array(
            array(
                'runtime_key' => 'ml-cpu', 'display_name' => 'ML CPU (scikit-learn / XGBoost)', 'kind' => 'cpu',
                'image_repository' => 'jobseeker/ml-runtime', 'image_tag' => 'cpu', 'base_image' => 'continuumio/miniconda3',
                'library_summary' => 'numpy, pandas, scikit-learn, xgboost, matplotlib, pyarrow, joblib, jobseeker_ml',
                'description' => 'General purpose CPU runtime for tabular ML, feature engineering and batch inference.',
                'default_cpu_limit' => 1.00, 'default_memory_mb' => 2048,
                'is_default' => 1, 'is_active' => 1, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now,
            ),
            array(
                'runtime_key' => 'ml-dl-cpu', 'display_name' => 'Deep Learning CPU (PyTorch)', 'kind' => 'cpu',
                'image_repository' => 'jobseeker/ml-runtime', 'image_tag' => 'dl-cpu', 'base_image' => 'continuumio/miniconda3',
                'library_summary' => 'torch (cpu), lightning, torchvision, pandas, scikit-learn, jobseeker_ml',
                'description' => 'CPU deep-learning runtime with PyTorch and Lightning for text / vision workloads.',
                'default_cpu_limit' => 2.00, 'default_memory_mb' => 4096,
                'is_default' => 0, 'is_active' => 1, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now,
            ),
        ));
    }

    /**
     * Built-in samples are kept in sync from repository/ml/samples/<key>/ so the
     * gallery always reflects the code shipped in the repo. User-authored samples
     * (is_builtin = 0) are never touched here.
     */
    private function seedSamples()
    {
        $root = rtrim((string) (getenv('JOBSEEKER_ML_REPOSITORY_ROOT') ?: (FCPATH.'repository/ml')), '/');
        $dir = $root.'/samples';
        if (! is_dir($dir)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach (glob($dir.'/*', GLOB_ONLYDIR) as $sampleDir) {
            $key = basename($sampleDir);
            $manifestPath = $sampleDir.'/sample.json';
            $codePath = $sampleDir.'/main.py';
            if (! is_file($manifestPath) || ! is_file($codePath)) {
                continue;
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), TRUE);
            if (! is_array($manifest)) {
                continue;
            }
            $row = array(
                'sample_key' => $key,
                'name' => isset($manifest['name']) ? (string) $manifest['name'] : $key,
                'category' => isset($manifest['category']) ? (string) $manifest['category'] : 'tabular',
                'run_type' => isset($manifest['run_type']) ? (string) $manifest['run_type'] : 'train',
                'runtime_key' => isset($manifest['runtime_key']) ? (string) $manifest['runtime_key'] : 'ml-cpu',
                'description' => isset($manifest['description']) ? (string) $manifest['description'] : NULL,
                'entry_point' => 'samples/'.$key.'/main.py',
                'code' => (string) file_get_contents($codePath),
                'params_schema_json' => isset($manifest['params_schema']) ? json_encode($manifest['params_schema']) : NULL,
                'dataset_roles_json' => isset($manifest['dataset_roles']) ? json_encode($manifest['dataset_roles']) : NULL,
                'tags' => isset($manifest['tags']) ? implode(',', (array) $manifest['tags']) : NULL,
                'is_builtin' => 1,
                'is_active' => 1,
                'sort_order' => isset($manifest['sort_order']) ? (int) $manifest['sort_order'] : 100,
                'updated_at' => $now,
            );
            $existing = $this->db->where('sample_key', $key)->get('ml_sample')->row();
            if ($existing) {
                $this->db->where('id', (int) $existing->id)->update('ml_sample', $row);
            } else {
                $row['created_at'] = $now;
                $row['owner'] = 'JobSeeker';
                $this->db->insert('ml_sample', $row);
            }
        }
    }

    // --- runtimes ------------------------------------------------------

    public function listRuntimes($activeOnly = TRUE)
    {
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }
        return $this->db->order_by('sort_order', 'ASC')->order_by('display_name', 'ASC')->get('ml_runtime')->result();
    }

    public function getRuntime($runtimeKey)
    {
        return $this->db->where('runtime_key', (string) $runtimeKey)->get('ml_runtime')->row();
    }

    public function getRuntimeById($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_runtime')->row();
    }

    public function saveRuntime($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('ml_runtime', $data);
            return (int) $id;
        }
        $this->db->insert('ml_runtime', $data);
        return (int) $this->db->insert_id();
    }

    public function deleteRuntime($id)
    {
        $this->db->where('id', (int) $id)->delete('ml_runtime');
        return $this->db->affected_rows();
    }

    public function runtimeKeyExists($runtimeKey, $excludeId = 0)
    {
        $this->db->from('ml_runtime')->where('runtime_key', (string) $runtimeKey);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function runtimeInUse($runtimeKey)
    {
        return (int) $this->db->where('runtime_key', (string) $runtimeKey)->count_all_results('ml_job') > 0;
    }

    // --- samples ------------------------------------------------------

    public function listSamples($activeOnly = TRUE)
    {
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }
        return $this->db->order_by('sort_order', 'ASC')->order_by('name', 'ASC')->get('ml_sample')->result();
    }

    public function getSample($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_sample')->row();
    }

    public function getSampleByKey($key)
    {
        return $this->db->where('sample_key', (string) $key)->get('ml_sample')->row();
    }

    public function sampleKeyExists($key, $excludeId = 0)
    {
        $this->db->from('ml_sample')->where('sample_key', (string) $key);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveSample($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('ml_sample', $data);
            return (int) $id;
        }
        $this->db->insert('ml_sample', $data);
        return (int) $this->db->insert_id();
    }

    public function deleteSample($id)
    {
        $this->db->where('id', (int) $id)->where('is_builtin', 0)->delete('ml_sample');
        return $this->db->affected_rows();
    }

    // --- experiments ------------------------------------------------------

    public function listExperiments($environment = 'ALL')
    {
        $this->db->from('ml_experiment');
        if ($environment !== '' && $environment !== 'ALL') {
            $this->db->where_in('environment', array($environment, 'ALL'));
        }
        return $this->db->order_by('name', 'ASC')->get()->result();
    }

    public function getExperiment($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_experiment')->row();
    }

    public function findOrCreateExperiment($key, $name, $environment, $owner)
    {
        $key = (string) $key;
        $row = $this->db->where('experiment_key', $key)->where('environment', $environment)->get('ml_experiment')->row();
        if ($row) {
            return (int) $row->id;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert('ml_experiment', array(
            'experiment_key' => $key,
            'name' => $name !== '' ? $name : $key,
            'environment' => $environment,
            'metric_goal' => 'max',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'owner' => $owner,
        ));
        return (int) $this->db->insert_id();
    }

    public function saveExperiment($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('ml_experiment', $data);
            return (int) $id;
        }
        $this->db->insert('ml_experiment', $data);
        return (int) $this->db->insert_id();
    }

    // --- jobs ------------------------------------------------------

    public function listJobs($environment = 'ALL')
    {
        $this->db->from('ml_job');
        if ($environment !== '' && $environment !== 'ALL') {
            $this->db->where('environment', $environment);
        }
        return $this->db->order_by('group_name', 'ASC')->order_by('name', 'ASC')->get()->result();
    }

    public function getJob($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_job')->row();
    }

    public function getJobByScope($jobKey, $environment)
    {
        return $this->db->where('job_key', (string) $jobKey)->where('environment', (string) $environment)->get('ml_job')->row();
    }

    public function jobScopeExists($jobKey, $environment, $excludeId = 0)
    {
        $this->db->from('ml_job')->where('job_key', (string) $jobKey)->where('environment', (string) $environment);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveJob($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->set('version', 'version + 1', FALSE)->update('ml_job', $data);
            return (int) $id;
        }
        $this->db->insert('ml_job', $data);
        return (int) $this->db->insert_id();
    }

    public function deleteJob($id)
    {
        $this->db->where('id', (int) $id)->delete('ml_job');
        return $this->db->affected_rows();
    }

    public function jobsUsingSample($sampleKey)
    {
        return (int) $this->db->where('sample_key', (string) $sampleKey)->count_all_results('ml_job');
    }
}
