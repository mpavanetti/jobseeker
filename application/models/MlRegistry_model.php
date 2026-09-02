<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Model registry: a named ml_model plus immutable ml_model_version rows produced
 * by runs. A version carries the training metrics snapshot, an input/output
 * signature, a content-addressed artifact and a lifecycle stage
 * (none | staging | production | archived). Stage transitions are audited in
 * ml_model_stage_event.
 */
class MlRegistry_model extends CI_Model
{
    const STAGES = array('none', 'staging', 'production', 'archived');

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_model` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `model_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `task` varchar(48) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'classification',
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `primary_metric` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `metric_goal` varchar(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'max',
            `tags` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
            `latest_version` int(11) NOT NULL DEFAULT 0,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_model_scope` (`model_key`,`environment`),
            KEY `ml_model_environment` (`environment`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_model_version` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `model_id` int(11) NOT NULL,
            `version` int(11) NOT NULL,
            `run_id` bigint(20) unsigned DEFAULT NULL,
            `stage` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'none',
            `artifact_id` bigint(20) unsigned DEFAULT NULL,
            `framework` varchar(48) COLLATE utf8_unicode_ci DEFAULT NULL,
            `metrics_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `params_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `signature_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `training_dataset_version_id` bigint(20) unsigned DEFAULT NULL,
            `notes` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `created_by` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_model_version_scope` (`model_id`,`version`),
            KEY `ml_model_version_stage` (`model_id`,`stage`),
            KEY `ml_model_version_run` (`run_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_model_stage_event` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `model_version_id` bigint(20) unsigned NOT NULL,
            `from_stage` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
            `to_stage` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
            `reason` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `actor` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ml_model_stage_event_version` (`model_version_id`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    // --- models ------------------------------------------------------

    public function listModels($environment = 'ALL')
    {
        $this->db->from('ml_model');
        if ($environment !== '' && $environment !== 'ALL') {
            $this->db->where_in('environment', array($environment, 'ALL'));
        }
        return $this->db->order_by('is_active', 'DESC')->order_by('name', 'ASC')->get()->result();
    }

    public function getModel($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_model')->row();
    }

    public function getModelByKey($key, $environment)
    {
        $this->db->where('model_key', (string) $key);
        $this->db->group_start()->where('environment', (string) $environment)->or_where('environment', 'ALL')->group_end();
        return $this->db->order_by("environment = 'ALL'", 'ASC', FALSE)->get('ml_model')->row();
    }

    public function modelScopeExists($key, $environment, $excludeId = 0)
    {
        $this->db->from('ml_model')->where('model_key', (string) $key)->where('environment', (string) $environment);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveModel($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('ml_model', $data);
            return (int) $id;
        }
        $this->db->insert('ml_model', $data);
        return (int) $this->db->insert_id();
    }

    public function deleteModel($id)
    {
        $this->db->where('model_id', (int) $id)->delete('ml_model_version');
        $this->db->where('id', (int) $id)->delete('ml_model');
        return $this->db->affected_rows();
    }

    public function findOrCreateModel($key, $name, $environment, $owner, $task = 'classification')
    {
        $existing = $this->getModelByKey($key, $environment);
        if ($existing) {
            return (int) $existing->id;
        }
        $now = date('Y-m-d H:i:s');
        return $this->saveModel(array(
            'model_key' => (string) $key,
            'name' => $name !== '' ? $name : $key,
            'environment' => (string) $environment,
            'task' => (string) $task,
            'metric_goal' => 'max',
            'latest_version' => 0,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'owner' => (string) $owner,
        ));
    }

    // --- versions ------------------------------------------------------

    public function createVersion($modelId, $data)
    {
        $row = $this->db->select_max('version')->where('model_id', (int) $modelId)->get('ml_model_version')->row();
        $version = ($row && $row->version !== NULL) ? ((int) $row->version + 1) : 1;
        $data = array_merge(array(
            'model_id' => (int) $modelId,
            'version' => $version,
            'stage' => 'none',
            'created_at' => date('Y-m-d H:i:s'),
        ), $data);
        $this->db->insert('ml_model_version', $data);
        $newId = (int) $this->db->insert_id();
        $this->db->where('id', (int) $modelId)->update('ml_model', array(
            'latest_version' => $version,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
        return array('id' => $newId, 'version' => $version);
    }

    public function getVersion($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_model_version')->row();
    }

    public function listVersions($modelId, $limit = 100)
    {
        return $this->db->where('model_id', (int) $modelId)
            ->order_by('version', 'DESC')->limit(max(1, min(300, (int) $limit)))
            ->get('ml_model_version')->result();
    }

    public function versionInStage($modelId, $stage)
    {
        return $this->db->where('model_id', (int) $modelId)->where('stage', (string) $stage)
            ->order_by('version', 'DESC')->limit(1)->get('ml_model_version')->row();
    }

    public function productionVersions($environment = 'ALL')
    {
        $this->db->select('mv.*, m.model_key, m.name AS model_name, m.environment, m.task, m.primary_metric')
            ->from('ml_model_version mv')->join('ml_model m', 'm.id = mv.model_id', 'left')
            ->where('mv.stage', 'production');
        if ($environment !== 'ALL') {
            $this->db->where_in('m.environment', array($environment, 'ALL'));
        }
        return $this->db->order_by('mv.created_at', 'DESC')->get()->result();
    }

    public function updateVersion($id, $data)
    {
        $this->db->where('id', (int) $id)->update('ml_model_version', $data);
    }

    /**
     * Move a version to a stage. Production/Staging are single-occupancy: any
     * current holder is bumped to archived. Every transition is audited.
     */
    public function transitionStage($versionId, $toStage, $actor, $reason = '')
    {
        $version = $this->getVersion($versionId);
        if (! $version || ! in_array($toStage, self::STAGES, TRUE)) {
            return FALSE;
        }
        $now = date('Y-m-d H:i:s');
        if (in_array($toStage, array('production', 'staging'), TRUE)) {
            $holders = $this->db->where('model_id', (int) $version->model_id)->where('stage', $toStage)
                ->where('id !=', (int) $versionId)->get('ml_model_version')->result();
            foreach ($holders as $holder) {
                $this->db->where('id', (int) $holder->id)->update('ml_model_version', array('stage' => 'archived'));
                $this->db->insert('ml_model_stage_event', array(
                    'model_version_id' => (int) $holder->id,
                    'from_stage' => (string) $holder->stage,
                    'to_stage' => 'archived',
                    'reason' => 'Superseded by v'.$version->version,
                    'actor' => (string) $actor,
                    'created_at' => $now,
                ));
            }
        }
        $this->db->where('id', (int) $versionId)->update('ml_model_version', array('stage' => $toStage));
        $this->db->insert('ml_model_stage_event', array(
            'model_version_id' => (int) $versionId,
            'from_stage' => (string) $version->stage,
            'to_stage' => (string) $toStage,
            'reason' => $reason !== '' ? substr($reason, 0, 1000) : NULL,
            'actor' => (string) $actor,
            'created_at' => $now,
        ));
        return TRUE;
    }

    public function stageHistory($versionId)
    {
        return $this->db->where('model_version_id', (int) $versionId)
            ->order_by('id', 'DESC')->get('ml_model_stage_event')->result();
    }

    public function countByStage($environment = 'ALL')
    {
        $this->db->select('mv.stage, COUNT(*) AS total', FALSE)
            ->from('ml_model_version mv')->join('ml_model m', 'm.id = mv.model_id', 'left');
        if ($environment !== 'ALL') {
            $this->db->where_in('m.environment', array($environment, 'ALL'));
        }
        $out = array();
        foreach ($this->db->group_by('mv.stage')->get()->result() as $row) {
            $out[$row->stage] = (int) $row->total;
        }
        return $out;
    }
}
