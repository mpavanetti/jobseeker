<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Execution + provenance store for the ML platform.
 *
 *  - ml_run          one execution of an ml_job (train / batch_infer / evaluate
 *                    / preprocess / tune), plus its params, tags and driver
 *                    handle.
 *  - ml_run_metric   time series: (run_id, key, step, value, ts). Scalar
 *                    metrics are just step 0.
 *  - ml_artifact     content-addressed blob metadata (sha256 -> storage uri),
 *                    shared by runs, datasets and model versions.
 *  - ml_run_artifact join row giving an artifact a role/path inside a run.
 *  - ml_lineage_edge one generic DAG: (src_kind, src_id) -> (dst_kind, dst_id)
 *                    with a role, e.g. dataset_version -> run -> model_version.
 */
class MlRun_model extends CI_Model
{
    const TERMINAL = array('SUCCEEDED', 'FAILED', 'CANCELLED', 'TIMED_OUT');

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_run` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `run_key` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
            `job_id` int(11) DEFAULT NULL,
            `experiment_id` int(11) DEFAULT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `run_type` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `status` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'QUEUED',
            `trigger_source` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'manual',
            `triggered_by` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `runtime_key` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
            `image_ref` varchar(300) COLLATE utf8_unicode_ci DEFAULT NULL,
            `driver` varchar(24) COLLATE utf8_unicode_ci DEFAULT NULL,
            `container_id` varchar(96) COLLATE utf8_unicode_ci DEFAULT NULL,
            `params_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `tags_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `metrics_summary_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `cpu_limit` decimal(5,2) NOT NULL DEFAULT 1.00,
            `memory_limit_mb` int(11) NOT NULL DEFAULT 2048,
            `jenkins_build_number` int(10) unsigned DEFAULT NULL,
            `exit_code` int(11) DEFAULT NULL,
            `log_tail` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `error_message` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `queued_at` datetime NOT NULL,
            `started_at` datetime DEFAULT NULL,
            `completed_at` datetime DEFAULT NULL,
            `heartbeat_at` datetime DEFAULT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_run_key` (`run_key`),
            KEY `ml_run_job` (`job_id`,`id`),
            KEY `ml_run_experiment` (`experiment_id`,`id`),
            KEY `ml_run_status` (`status`,`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_run_metric` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `run_id` bigint(20) unsigned NOT NULL,
            `metric_key` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
            `step` int(11) NOT NULL DEFAULT 0,
            `value` double NOT NULL,
            `recorded_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_run_metric_point` (`run_id`,`metric_key`,`step`),
            KEY `ml_run_metric_run` (`run_id`,`metric_key`,`step`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_artifact` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `sha256` char(64) COLLATE utf8_unicode_ci NOT NULL,
            `media_type` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'application/octet-stream',
            `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
            `storage_backend` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'local',
            `storage_uri` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
            `original_name` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_artifact_sha` (`sha256`),
            KEY `ml_artifact_backend` (`storage_backend`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_run_artifact` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `run_id` bigint(20) unsigned NOT NULL,
            `artifact_id` bigint(20) unsigned NOT NULL,
            `role` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'artifact',
            `path` varchar(700) COLLATE utf8_unicode_ci NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_run_artifact_path` (`run_id`,`path`),
            KEY `ml_run_artifact_run` (`run_id`),
            KEY `ml_run_artifact_artifact` (`artifact_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_lineage_edge` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `src_kind` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
            `src_id` bigint(20) unsigned NOT NULL,
            `dst_kind` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
            `dst_id` bigint(20) unsigned NOT NULL,
            `role` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'uses',
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_lineage_edge_unique` (`src_kind`,`src_id`,`dst_kind`,`dst_id`,`role`),
            KEY `ml_lineage_src` (`src_kind`,`src_id`),
            KEY `ml_lineage_dst` (`dst_kind`,`dst_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    // --- runs ------------------------------------------------------

    public function createRun($data)
    {
        $now = date('Y-m-d H:i:s');
        $data = array_merge(array(
            'run_key' => bin2hex(random_bytes(8)),
            'status' => 'QUEUED',
            'queued_at' => $now,
            'updated_at' => $now,
        ), $data);
        $this->db->insert('ml_run', $data);
        return (int) $this->db->insert_id();
    }

    public function getRun($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_run')->row();
    }

    public function getRunByKey($runKey)
    {
        return $this->db->where('run_key', (string) $runKey)->get('ml_run')->row();
    }

    public function updateRun($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->update('ml_run', $data);
    }

    public function listRuns($filters = array(), $limit = 100)
    {
        $this->db->from('ml_run');
        if (! empty($filters['environment']) && $filters['environment'] !== 'ALL') {
            $this->db->where('environment', $filters['environment']);
        }
        if (! empty($filters['experiment_id'])) {
            $this->db->where('experiment_id', (int) $filters['experiment_id']);
        }
        if (! empty($filters['job_id'])) {
            $this->db->where('job_id', (int) $filters['job_id']);
        }
        if (! empty($filters['run_type'])) {
            $this->db->where('run_type', (string) $filters['run_type']);
        }
        if (! empty($filters['status'])) {
            $this->db->where('status', (string) $filters['status']);
        }
        return $this->db->order_by('id', 'DESC')->limit(max(1, min(500, (int) $limit)))->get()->result();
    }

    public function recentRunsForJob($jobId, $limit = 15)
    {
        return $this->db->where('job_id', (int) $jobId)
            ->order_by('id', 'DESC')->limit(max(1, min(50, (int) $limit)))
            ->get('ml_run')->result();
    }

    public function activeRunCount()
    {
        return (int) $this->db->where_not_in('status', self::TERMINAL)->count_all_results('ml_run');
    }

    public function staleActiveRuns($olderThanSeconds = 300)
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(30, (int) $olderThanSeconds));
        return $this->db->where_not_in('status', self::TERMINAL)
            ->group_start()->where('heartbeat_at <', $cutoff)->or_where('heartbeat_at', NULL)->group_end()
            ->where('updated_at <', $cutoff)
            ->order_by('id', 'ASC')->limit(25)->get('ml_run')->result();
    }

    public function statusCounts($environment = 'ALL', $sinceDays = 30)
    {
        $this->db->select("status, COUNT(*) AS total", FALSE)->from('ml_run')
            ->where('queued_at >=', date('Y-m-d H:i:s', time() - $sinceDays * 86400));
        if ($environment !== 'ALL') {
            $this->db->where('environment', $environment);
        }
        $out = array();
        foreach ($this->db->group_by('status')->get()->result() as $row) {
            $out[$row->status] = (int) $row->total;
        }
        return $out;
    }

    // --- metrics ------------------------------------------------------

    public function recordMetric($runId, $key, $value, $step = 0, $recordedAt = NULL)
    {
        $recordedAt = $recordedAt ?: date('Y-m-d H:i:s');
        $this->db->query(
            'INSERT INTO `ml_run_metric` (`run_id`,`metric_key`,`step`,`value`,`recorded_at`)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `recorded_at` = VALUES(`recorded_at`)',
            array((int) $runId, (string) $key, (int) $step, (float) $value, $recordedAt)
        );
    }

    public function metricSeries($runId)
    {
        $rows = $this->db->where('run_id', (int) $runId)
            ->order_by('metric_key', 'ASC')->order_by('step', 'ASC')
            ->get('ml_run_metric')->result();
        $series = array();
        foreach ($rows as $row) {
            $series[$row->metric_key][] = array('step' => (int) $row->step, 'value' => (float) $row->value);
        }
        return $series;
    }

    /** Latest value per metric key, used for run cards and summaries. */
    public function latestMetrics($runId)
    {
        $rows = $this->db->query(
            'SELECT m.metric_key, m.value, m.step
               FROM ml_run_metric m
               JOIN (SELECT metric_key, MAX(step) AS max_step
                       FROM ml_run_metric WHERE run_id = ? GROUP BY metric_key) x
                 ON x.metric_key = m.metric_key AND x.max_step = m.step
              WHERE m.run_id = ?', array((int) $runId, (int) $runId)
        )->result();
        $out = array();
        foreach ($rows as $row) {
            $out[$row->metric_key] = (float) $row->value;
        }
        return $out;
    }

    public function metricKeysForRuns(array $runIds)
    {
        if (empty($runIds)) {
            return array();
        }
        $rows = $this->db->select('DISTINCT metric_key', FALSE)
            ->where_in('run_id', array_map('intval', $runIds))
            ->order_by('metric_key', 'ASC')->get('ml_run_metric')->result();
        return array_map(function ($r) { return $r->metric_key; }, $rows);
    }

    // --- artifacts ------------------------------------------------------

    public function upsertArtifact($sha256, $mediaType, $sizeBytes, $backend, $uri, $originalName = NULL)
    {
        $existing = $this->db->where('sha256', (string) $sha256)->get('ml_artifact')->row();
        if ($existing) {
            return (int) $existing->id;
        }
        $this->db->insert('ml_artifact', array(
            'sha256' => (string) $sha256,
            'media_type' => (string) $mediaType,
            'size_bytes' => (int) $sizeBytes,
            'storage_backend' => (string) $backend,
            'storage_uri' => (string) $uri,
            'original_name' => $originalName !== NULL ? (string) $originalName : NULL,
            'created_at' => date('Y-m-d H:i:s'),
        ));
        return (int) $this->db->insert_id();
    }

    public function getArtifact($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_artifact')->row();
    }

    public function linkRunArtifact($runId, $artifactId, $role, $path)
    {
        $this->db->query(
            'INSERT IGNORE INTO `ml_run_artifact` (`run_id`,`artifact_id`,`role`,`path`,`created_at`) VALUES (?,?,?,?,?)',
            array((int) $runId, (int) $artifactId, (string) $role, (string) $path, date('Y-m-d H:i:s'))
        );
    }

    public function runArtifacts($runId)
    {
        return $this->db->select('ra.*, a.sha256, a.media_type, a.size_bytes, a.storage_backend, a.storage_uri')
            ->from('ml_run_artifact ra')->join('ml_artifact a', 'a.id = ra.artifact_id', 'left')
            ->where('ra.run_id', (int) $runId)->order_by('ra.role', 'ASC')->order_by('ra.path', 'ASC')
            ->get()->result();
    }

    // --- lineage ------------------------------------------------------

    public function addEdge($srcKind, $srcId, $dstKind, $dstId, $role = 'uses')
    {
        $this->db->query(
            'INSERT IGNORE INTO `ml_lineage_edge` (`src_kind`,`src_id`,`dst_kind`,`dst_id`,`role`,`created_at`)
             VALUES (?,?,?,?,?,?)',
            array((string) $srcKind, (int) $srcId, (string) $dstKind, (int) $dstId, (string) $role, date('Y-m-d H:i:s'))
        );
    }

    public function edgesInto($kind, $id)
    {
        return $this->db->where('dst_kind', (string) $kind)->where('dst_id', (int) $id)->get('ml_lineage_edge')->result();
    }

    public function edgesOutOf($kind, $id)
    {
        return $this->db->where('src_kind', (string) $kind)->where('src_id', (int) $id)->get('ml_lineage_edge')->result();
    }

    /** Neighbourhood (one hop each way) for the run-detail lineage panel. */
    public function neighbourhood($kind, $id)
    {
        return array(
            'in' => $this->edgesInto($kind, $id),
            'out' => $this->edgesOutOf($kind, $id),
        );
    }
}
