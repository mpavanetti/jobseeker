<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

class Pipeline_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `job_pipelines` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `pipeline_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `group_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'General',
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `graph_json` longtext COLLATE utf8_unicode_ci NOT NULL,
            `jenkins_job_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `sync_status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'pending',
            `sync_error` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `schedule_enabled` tinyint(1) NOT NULL DEFAULT 0,
            `schedule_cron` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `version` int(11) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `job_pipeline_scope` (`pipeline_key`,`environment`),
            KEY `job_pipeline_environment` (`environment`),
            KEY `job_pipeline_group` (`group_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS `job_pipeline_runs` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `pipeline_id` int(11) NOT NULL,
            `jenkins_queue_id` bigint(20) unsigned DEFAULT NULL,
            `jenkins_build_number` int(11) unsigned DEFAULT NULL,
            `status` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'QUEUED',
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `triggered_by` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `started_at` datetime NOT NULL,
            `completed_at` datetime DEFAULT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `job_pipeline_run_pipeline` (`pipeline_id`,`id`),
            KEY `job_pipeline_run_status` (`status`,`updated_at`),
            CONSTRAINT `job_pipeline_runs_pipeline_fk` FOREIGN KEY (`pipeline_id`) REFERENCES `job_pipelines` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        if (! $this->db->field_exists('schedule_enabled', 'job_pipelines')) {
            $this->db->query('ALTER TABLE `job_pipelines` ADD `schedule_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_active`');
        }
        if (! $this->db->field_exists('schedule_cron', 'job_pipelines')) {
            $this->db->query('ALTER TABLE `job_pipelines` ADD `schedule_cron` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `schedule_enabled`');
        }
    }

    public function listPipelines($environment = '')
    {
        $this->db->from('job_pipelines');
        if ($environment !== '' && $environment !== 'ALL') {
            $this->db->where('environment', $environment);
        }
        return $this->db
            ->order_by('group_name', 'ASC')
            ->order_by('name', 'ASC')
            ->get()
            ->result();
    }

    public function getPipeline($id)
    {
        return $this->db->where('id', (int) $id)->get('job_pipelines')->row();
    }

    public function getPipelineByScope($pipelineKey, $environment)
    {
        return $this->db
            ->where('pipeline_key', (string) $pipelineKey)
            ->where('environment', (string) $environment)
            ->get('job_pipelines')
            ->row();
    }

    public function scopeExists($pipelineKey, $environment, $excludeId = 0)
    {
        $this->db->from('job_pipelines')
            ->where('pipeline_key', $pipelineKey)
            ->where('environment', $environment);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function savePipeline($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('job_pipelines', $data);
            return $this->db->affected_rows() >= 0 ? (int) $id : 0;
        }
        $this->db->insert('job_pipelines', $data);
        return (int) $this->db->insert_id();
    }

    public function updateSync($id, $jenkinsJobName, $status, $error = '')
    {
        $this->db->where('id', (int) $id)->update('job_pipelines', array(
            'jenkins_job_name' => (string) $jenkinsJobName,
            'sync_status' => (string) $status,
            'sync_error' => $error === '' ? NULL : substr((string) $error, 0, 1000),
            'updated_at' => date('Y-m-d H:i:s')
        ));
    }

    public function deletePipeline($id)
    {
        $this->db->where('id', (int) $id)->delete('job_pipelines');
        return $this->db->affected_rows();
    }

    public function createRun($pipelineId, $environment, $triggeredBy, $queueId = NULL)
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('job_pipeline_runs', array(
            'pipeline_id' => (int) $pipelineId,
            'jenkins_queue_id' => $queueId === NULL ? NULL : (int) $queueId,
            'status' => 'QUEUED',
            'environment' => (string) $environment,
            'triggered_by' => (string) $triggeredBy,
            'started_at' => $now,
            'updated_at' => $now
        ));
        return (int) $this->db->insert_id();
    }

    public function getRun($runId)
    {
        return $this->db->where('id', (int) $runId)->get('job_pipeline_runs')->row();
    }

    public function getRunByBuild($pipelineId, $buildNumber)
    {
        return $this->db
            ->where('pipeline_id', (int) $pipelineId)
            ->where('jenkins_build_number', (int) $buildNumber)
            ->get('job_pipeline_runs')
            ->row();
    }

    public function createObservedRun($pipelineId, $buildNumber, $status, $environment, $triggeredBy, $startedAt, $completedAt)
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('job_pipeline_runs', array(
            'pipeline_id' => (int) $pipelineId,
            'jenkins_build_number' => (int) $buildNumber,
            'status' => (string) $status,
            'environment' => (string) $environment,
            'triggered_by' => (string) $triggeredBy,
            'started_at' => (string) $startedAt,
            'completed_at' => $completedAt,
            'updated_at' => $now
        ));
        return (int) $this->db->insert_id();
    }

    public function updateRun($runId, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $runId)->update('job_pipeline_runs', $data);
    }

    public function recentRuns($pipelineId, $limit = 10)
    {
        return $this->db
            ->where('pipeline_id', (int) $pipelineId)
            ->order_by('id', 'DESC')
            ->limit(max(1, min(25, (int) $limit)))
            ->get('job_pipeline_runs')
            ->result();
    }
}

?>
