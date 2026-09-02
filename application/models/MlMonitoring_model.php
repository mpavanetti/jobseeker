<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Model monitoring store.
 *
 *  - ml_monitor        binds a production-facing model version to a baseline
 *                      dataset version and a threshold/config blob, with an
 *                      optional cron for scheduled evaluation.
 *  - ml_monitor_run    one evaluation pass (manual or scheduled) with a summary.
 *  - ml_monitor_point  time series: (monitor_id, ts, metric_key, feature, value)
 *                      e.g. drift_psi / feature=amount, prediction_volume,
 *                      accuracy, output_mean.
 *  - ml_alert          raised when a point breaches a threshold; acknowledged
 *                      in the UI, optionally e-mailed via EmailSettings.
 */
class MlMonitoring_model extends CI_Model
{
    const ALERT_STATES = array('open', 'acknowledged', 'resolved');

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_monitor` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `monitor_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `model_id` int(11) NOT NULL,
            `model_version_id` bigint(20) unsigned DEFAULT NULL,
            `track_stage` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'production',
            `baseline_dataset_version_id` bigint(20) unsigned DEFAULT NULL,
            `config_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `schedule_cron` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
            `jenkins_job_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
            `status` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ok',
            `last_run_at` datetime DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ml_monitor_scope` (`monitor_key`,`environment`),
            KEY `ml_monitor_model` (`model_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_monitor_run` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `monitor_id` int(11) NOT NULL,
            `status` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'RUNNING',
            `trigger_source` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'manual',
            `current_dataset_version_id` bigint(20) unsigned DEFAULT NULL,
            `summary_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `drift_score` double DEFAULT NULL,
            `alerts_opened` int(11) NOT NULL DEFAULT 0,
            `error_message` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `started_at` datetime NOT NULL,
            `completed_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `ml_monitor_run_monitor` (`monitor_id`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_monitor_point` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `monitor_id` int(11) NOT NULL,
            `monitor_run_id` bigint(20) unsigned DEFAULT NULL,
            `recorded_at` datetime NOT NULL,
            `metric_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `feature` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '__overall__',
            `value` double NOT NULL,
            `breached` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `ml_monitor_point_series` (`monitor_id`,`metric_key`,`feature`,`recorded_at`),
            KEY `ml_monitor_point_run` (`monitor_run_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `ml_alert` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `monitor_id` int(11) DEFAULT NULL,
            `monitor_run_id` bigint(20) unsigned DEFAULT NULL,
            `run_id` bigint(20) unsigned DEFAULT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `severity` varchar(12) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'warning',
            `category` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'drift',
            `title` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
            `detail` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `metric_key` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
            `feature` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            `observed_value` double DEFAULT NULL,
            `threshold_value` double DEFAULT NULL,
            `state` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'open',
            `fingerprint` char(40) COLLATE utf8_unicode_ci DEFAULT NULL,
            `notified_at` datetime DEFAULT NULL,
            `acknowledged_by` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            `acknowledged_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ml_alert_state` (`state`,`created_at`),
            KEY `ml_alert_monitor` (`monitor_id`,`state`),
            KEY `ml_alert_fingerprint` (`fingerprint`,`state`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    // --- monitors ------------------------------------------------------

    public function listMonitors($environment = 'ALL')
    {
        $this->db->select('mo.*, m.name AS model_name, m.model_key')
            ->from('ml_monitor mo')->join('ml_model m', 'm.id = mo.model_id', 'left');
        if ($environment !== 'ALL') {
            $this->db->where_in('mo.environment', array($environment, 'ALL'));
        }
        return $this->db->order_by('mo.is_active', 'DESC')->order_by('mo.name', 'ASC')->get()->result();
    }

    public function getMonitor($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_monitor')->row();
    }

    public function getMonitorByKey($key, $environment)
    {
        return $this->db->where('monitor_key', (string) $key)->where('environment', (string) $environment)
            ->get('ml_monitor')->row();
    }

    public function monitorScopeExists($key, $environment, $excludeId = 0)
    {
        $this->db->from('ml_monitor')->where('monitor_key', (string) $key)->where('environment', (string) $environment);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveMonitor($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('ml_monitor', $data);
            return (int) $id;
        }
        $this->db->insert('ml_monitor', $data);
        return (int) $this->db->insert_id();
    }

    public function deleteMonitor($id)
    {
        $this->db->where('monitor_id', (int) $id)->delete('ml_monitor_point');
        $this->db->where('monitor_id', (int) $id)->delete('ml_monitor_run');
        $this->db->where('monitor_id', (int) $id)->delete('ml_alert');
        $this->db->where('id', (int) $id)->delete('ml_monitor');
        return $this->db->affected_rows();
    }

    public function dueMonitors()
    {
        return $this->db->where('is_active', 1)->where('schedule_cron IS NOT NULL', NULL, FALSE)
            ->where("schedule_cron != ''", NULL, FALSE)->get('ml_monitor')->result();
    }

    // --- monitor runs ------------------------------------------------------

    public function createMonitorRun($monitorId, $triggerSource, $currentDatasetVersionId = NULL)
    {
        $this->db->insert('ml_monitor_run', array(
            'monitor_id' => (int) $monitorId,
            'status' => 'RUNNING',
            'trigger_source' => (string) $triggerSource,
            'current_dataset_version_id' => $currentDatasetVersionId ? (int) $currentDatasetVersionId : NULL,
            'started_at' => date('Y-m-d H:i:s'),
        ));
        return (int) $this->db->insert_id();
    }

    public function getMonitorRun($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_monitor_run')->row();
    }

    public function updateMonitorRun($id, $data)
    {
        $this->db->where('id', (int) $id)->update('ml_monitor_run', $data);
    }

    public function listMonitorRuns($monitorId, $limit = 30)
    {
        return $this->db->where('monitor_id', (int) $monitorId)
            ->order_by('id', 'DESC')->limit(max(1, min(100, (int) $limit)))
            ->get('ml_monitor_run')->result();
    }

    // --- points ------------------------------------------------------

    public function addPoint($monitorId, $monitorRunId, $metricKey, $feature, $value, $breached = 0, $recordedAt = NULL)
    {
        $this->db->insert('ml_monitor_point', array(
            'monitor_id' => (int) $monitorId,
            'monitor_run_id' => $monitorRunId ? (int) $monitorRunId : NULL,
            'recorded_at' => $recordedAt ?: date('Y-m-d H:i:s'),
            'metric_key' => (string) $metricKey,
            'feature' => (string) $feature,
            'value' => (float) $value,
            'breached' => $breached ? 1 : 0,
        ));
    }

    public function series($monitorId, $metricKey = NULL, $sinceDays = 90)
    {
        $this->db->where('monitor_id', (int) $monitorId)
            ->where('recorded_at >=', date('Y-m-d H:i:s', time() - $sinceDays * 86400));
        if ($metricKey !== NULL) {
            $this->db->where('metric_key', (string) $metricKey);
        }
        $rows = $this->db->order_by('recorded_at', 'ASC')->get('ml_monitor_point')->result();
        $out = array();
        foreach ($rows as $row) {
            $out[$row->metric_key][$row->feature][] = array(
                'ts' => $row->recorded_at, 'value' => (float) $row->value, 'breached' => (int) $row->breached,
            );
        }
        return $out;
    }

    public function pointsForRun($monitorRunId)
    {
        return $this->db->where('monitor_run_id', (int) $monitorRunId)
            ->order_by('metric_key', 'ASC')->order_by('value', 'DESC')->get('ml_monitor_point')->result();
    }

    // --- alerts ------------------------------------------------------

    public function raiseAlert($data)
    {
        $data = array_merge(array(
            'state' => 'open',
            'severity' => 'warning',
            'category' => 'drift',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ), $data);
        if (! empty($data['fingerprint'])) {
            $open = $this->db->where('fingerprint', $data['fingerprint'])->where('state', 'open')
                ->get('ml_alert')->row();
            if ($open) {
                $this->db->where('id', (int) $open->id)->update('ml_alert', array(
                    'observed_value' => isset($data['observed_value']) ? $data['observed_value'] : $open->observed_value,
                    'detail' => isset($data['detail']) ? $data['detail'] : $open->detail,
                    'updated_at' => date('Y-m-d H:i:s'),
                ));
                return (int) $open->id;
            }
        }
        $this->db->insert('ml_alert', $data);
        return (int) $this->db->insert_id();
    }

    public function listAlerts($filters = array(), $limit = 100)
    {
        $this->db->from('ml_alert');
        if (! empty($filters['state'])) {
            $this->db->where('state', (string) $filters['state']);
        }
        if (! empty($filters['environment']) && $filters['environment'] !== 'ALL') {
            $this->db->where_in('environment', array($filters['environment'], 'ALL'));
        }
        if (! empty($filters['monitor_id'])) {
            $this->db->where('monitor_id', (int) $filters['monitor_id']);
        }
        return $this->db->order_by('id', 'DESC')->limit(max(1, min(300, (int) $limit)))->get()->result();
    }

    public function getAlert($id)
    {
        return $this->db->where('id', (int) $id)->get('ml_alert')->row();
    }

    public function updateAlert($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->update('ml_alert', $data);
    }

    public function openAlertCount($environment = 'ALL')
    {
        $this->db->where('state', 'open');
        if ($environment !== 'ALL') {
            $this->db->where_in('environment', array($environment, 'ALL'));
        }
        return (int) $this->db->count_all_results('ml_alert');
    }

    public function pendingNotifications($limit = 20)
    {
        return $this->db->where('state', 'open')->where('notified_at', NULL)
            ->order_by('id', 'ASC')->limit(max(1, min(50, (int) $limit)))
            ->get('ml_alert')->result();
    }
}
