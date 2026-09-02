<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'libraries/MlDriftAnalyzer.php';

/**
 * Runs one evaluation pass of an ml_monitor: takes the monitor's baseline
 * dataset-version fingerprint and a "current" dataset-version fingerprint
 * (latest version of the comparison dataset, or an explicitly supplied one),
 * computes drift with MlDriftAnalyzer, and also derives prediction-volume /
 * output-distribution / accuracy-on-feedback signals from recent batch-inference
 * runs of the tracked model. Writes ml_monitor_point time series and raises
 * ml_alert rows on threshold breaches.
 */
class MlMonitorEvaluator
{
    /** @var object MlMonitoring_model */
    private $monitors;
    /** @var object MlDataset_model */
    private $datasets;
    /** @var object MlRegistry_model */
    private $registry;
    /** @var object MlRun_model */
    private $runs;
    /** @var MlDriftAnalyzer */
    private $analyzer;

    public function __construct($config = array())
    {
        $this->monitors = isset($config['monitors']) ? $config['monitors'] : NULL;
        $this->datasets = isset($config['datasets']) ? $config['datasets'] : NULL;
        $this->registry = isset($config['registry']) ? $config['registry'] : NULL;
        $this->runs = isset($config['runs']) ? $config['runs'] : NULL;
        $this->analyzer = new MlDriftAnalyzer();
    }

    /**
     * @param object   $monitor                 ml_monitor row
     * @param string   $triggerSource           manual|scheduled
     * @param int|null $currentDatasetVersionId override for the comparison version
     * @return array{ok:bool, monitor_run_id:int, summary:array, message:string}
     */
    public function evaluate($monitor, $triggerSource = 'manual', $currentDatasetVersionId = NULL)
    {
        $config = json_decode((string) $monitor->config_json, TRUE);
        $config = is_array($config) ? $config : array();
        $thresholds = isset($config['thresholds']) && is_array($config['thresholds']) ? $config['thresholds'] : array();

        $baseline = $monitor->baseline_dataset_version_id
            ? $this->datasets->getVersion((int) $monitor->baseline_dataset_version_id) : NULL;
        if (! $baseline) {
            return array('ok' => FALSE, 'monitor_run_id' => 0, 'summary' => array(),
                'message' => 'The monitor has no baseline dataset version.');
        }

        $current = NULL;
        if ($currentDatasetVersionId) {
            $current = $this->datasets->getVersion((int) $currentDatasetVersionId);
        } elseif (! empty($config['comparison_dataset_id'])) {
            $current = $this->datasets->latestVersion((int) $config['comparison_dataset_id']);
        } else {
            // default: the newest version of the baseline's own dataset
            $current = $this->datasets->latestVersion((int) $baseline->dataset_id);
        }
        if (! $current) {
            return array('ok' => FALSE, 'monitor_run_id' => 0, 'summary' => array(),
                'message' => 'No current dataset version to compare against yet.');
        }

        $monitorRunId = $this->monitors->createMonitorRun($monitor->id, $triggerSource, (int) $current->id);
        $now = date('Y-m-d H:i:s');
        $alertsOpened = 0;

        $baseFp = $this->fingerprint($baseline);
        $curFp = $this->fingerprint($current);
        $drift = $this->analyzer->compare($baseFp, $curFp, $thresholds);

        foreach ($drift['features'] as $feature => $result) {
            if (empty($result['metrics'])) {
                continue;
            }
            foreach ($result['metrics'] as $metricKey => $value) {
                $breached = FALSE;
                foreach (isset($result['breaches']) ? $result['breaches'] : array() as $b) {
                    if ($b['metric'] === $metricKey) {
                        $breached = TRUE;
                    }
                }
                $this->monitors->addPoint($monitor->id, $monitorRunId, $metricKey, $feature, $value, $breached ? 1 : 0, $now);
            }
            foreach (isset($result['breaches']) ? $result['breaches'] : array() as $b) {
                $alertsOpened += $this->raise($monitor, $monitorRunId, 'drift', $b['level'],
                    'Drift on "'.$feature.'" ('.$b['metric'].')',
                    sprintf('%s = %.4f breached the %s threshold %.4f (baseline v%d vs current v%d).',
                        $b['metric'], $result['metrics'][$b['metric']], $b['level'], $b['threshold'],
                        $baseline->version, $current->version),
                    $b['metric'], $feature, $result['metrics'][$b['metric']], $b['threshold']);
            }
        }

        $this->monitors->addPoint($monitor->id, $monitorRunId, 'drift_psi', '__overall__',
            $drift['overall']['drift_psi_mean'], $drift['overall']['status'] === 'ok' ? 0 : 1, $now);

        // --- serving signals from recent batch-inference runs ----------------
        $serving = $this->servingSignals($monitor, $config);
        foreach ($serving['points'] as $point) {
            $this->monitors->addPoint($monitor->id, $monitorRunId, $point['metric_key'], $point['feature'],
                $point['value'], ! empty($point['breached']) ? 1 : 0, $now);
        }
        foreach ($serving['alerts'] as $alert) {
            $alertsOpened += $this->raise($monitor, $monitorRunId, $alert['category'], $alert['level'],
                $alert['title'], $alert['detail'], $alert['metric_key'], $alert['feature'],
                $alert['observed'], $alert['threshold']);
        }

        $summary = array(
            'baseline_version' => (int) $baseline->version,
            'current_version' => (int) $current->version,
            'drift' => $drift['overall'],
            'serving' => $serving['summary'],
            'thresholds' => $drift['thresholds'],
        );
        $status = $drift['overall']['status'] === 'critical' ? 'critical'
            : (($alertsOpened > 0 || $drift['overall']['status'] === 'warning') ? 'warning' : 'ok');

        $this->monitors->updateMonitorRun($monitorRunId, array(
            'status' => 'SUCCEEDED',
            'summary_json' => json_encode($summary, JSON_UNESCAPED_SLASHES),
            'drift_score' => $drift['overall']['drift_psi_max'],
            'alerts_opened' => $alertsOpened,
            'completed_at' => date('Y-m-d H:i:s'),
        ));
        $this->monitors->saveMonitor(array(
            'status' => $status,
            'last_run_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ), (int) $monitor->id);

        return array('ok' => TRUE, 'monitor_run_id' => $monitorRunId, 'summary' => $summary,
            'message' => 'Evaluated: '.$drift['overall']['features_drifted'].'/'.$drift['overall']['features_total']
                .' features drifted, '.$alertsOpened.' alert(s).');
    }

    private function fingerprint($version)
    {
        $fp = json_decode((string) $version->fingerprint_json, TRUE);
        if (is_array($fp) && isset($fp['columns'])) {
            return $fp;
        }
        $schema = json_decode((string) $version->schema_json, TRUE);
        return array('columns' => is_array($schema) ? $schema : array());
    }

    /**
     * Prediction volume + output distribution + accuracy-on-feedback from the
     * tracked model's recent batch-inference runs. Best-effort: reads the run
     * metrics the SDK logs (`prediction_count`, `output_mean`, `accuracy`, ...).
     */
    private function servingSignals($monitor, $config)
    {
        $points = array();
        $alerts = array();
        $summary = array('inference_runs' => 0);
        if (! $this->runs || empty($monitor->model_id)) {
            return array('points' => $points, 'alerts' => $alerts, 'summary' => $summary);
        }
        $modelVersion = $monitor->model_version_id
            ? $this->registry->getVersion((int) $monitor->model_version_id) : NULL;
        $modelRunIds = array();
        foreach ($this->registry->listVersions((int) $monitor->model_id, 50) as $mv) {
            if ($mv->run_id) {
                $modelRunIds[] = (int) $mv->run_id;
            }
        }
        $recent = $this->runs->listRuns(array('run_type' => 'batch_infer', 'environment' => $monitor->environment), 40);
        $volume = 0.0;
        $accWindow = array();
        $runsSeen = 0;
        foreach ($recent as $run) {
            $metrics = $this->runs->latestMetrics((int) $run->id);
            if (! $metrics) {
                continue;
            }
            $runsSeen++;
            if (isset($metrics['prediction_count'])) {
                $volume += (float) $metrics['prediction_count'];
            }
            if (isset($metrics['output_mean'])) {
                $points[] = array('metric_key' => 'output_mean', 'feature' => '__overall__', 'value' => (float) $metrics['output_mean']);
            }
            if (isset($metrics['accuracy'])) {
                $accWindow[] = (float) $metrics['accuracy'];
            }
        }
        $summary['inference_runs'] = $runsSeen;
        $summary['prediction_volume'] = $volume;
        if ($runsSeen > 0) {
            $points[] = array('metric_key' => 'prediction_volume', 'feature' => '__overall__', 'value' => $volume);
        }
        if ($accWindow) {
            $acc = array_sum($accWindow) / count($accWindow);
            $summary['accuracy_recent'] = round($acc, 4);
            $points[] = array('metric_key' => 'accuracy', 'feature' => '__overall__', 'value' => $acc);
            $floor = isset($config['accuracy_floor']) ? (float) $config['accuracy_floor'] : 0.0;
            if ($floor > 0 && $acc < $floor) {
                $alerts[] = array(
                    'category' => 'performance', 'level' => 'critical',
                    'title' => 'Model accuracy below floor',
                    'detail' => sprintf('Recent labelled accuracy %.4f is below the configured floor %.4f.', $acc, $floor),
                    'metric_key' => 'accuracy', 'feature' => '__overall__', 'observed' => $acc, 'threshold' => $floor,
                );
            }
        }
        $minVol = isset($config['min_prediction_volume']) ? (float) $config['min_prediction_volume'] : 0.0;
        if ($minVol > 0 && $runsSeen > 0 && $volume < $minVol) {
            $alerts[] = array(
                'category' => 'availability', 'level' => 'warning',
                'title' => 'Prediction volume dropped',
                'detail' => sprintf('Only %d predictions across %d recent runs (expected >= %d).', (int) $volume, $runsSeen, (int) $minVol),
                'metric_key' => 'prediction_volume', 'feature' => '__overall__', 'observed' => $volume, 'threshold' => $minVol,
            );
        }
        return array('points' => $points, 'alerts' => $alerts, 'summary' => $summary);
    }

    private function raise($monitor, $monitorRunId, $category, $level, $title, $detail, $metricKey, $feature, $observed, $threshold)
    {
        $fingerprint = sha1($monitor->id.'|'.$category.'|'.$title.'|'.$feature);
        $this->monitors->raiseAlert(array(
            'monitor_id' => (int) $monitor->id,
            'monitor_run_id' => (int) $monitorRunId,
            'environment' => (string) $monitor->environment,
            'severity' => $level === 'critical' ? 'critical' : 'warning',
            'category' => $category,
            'title' => $title,
            'detail' => $detail,
            'metric_key' => $metricKey,
            'feature' => $feature,
            'observed_value' => $observed,
            'threshold_value' => $threshold,
            'fingerprint' => $fingerprint,
        ));
        return 1;
    }
}
