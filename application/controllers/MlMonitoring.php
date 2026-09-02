<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';
require APPPATH.'/controllers/concerns/MlControllerTrait.php';

/**
 * Model monitoring: bind a production model to a baseline dataset version, run
 * drift + serving evaluations (manual or scheduled), chart the time series and
 * work the alert queue. Drift maths is MlDriftAnalyzer; an evaluation pass is
 * MlMonitorEvaluator.
 */
class MlMonitoring extends BaseController
{
    use MlControllerTrait;

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
        $this->load->model('MlMonitoring_model', 'monitoring');
        $this->load->model('MlRegistry_model', 'registry');
        $this->load->model('MlDataset_model', 'datasets');
        $this->load->model('MlRun_model', 'runs');
        require_once APPPATH.'libraries/MlMonitorEvaluator.php';
    }

    private function evaluator()
    {
        return new MlMonitorEvaluator(array(
            'monitors' => $this->monitoring, 'datasets' => $this->datasets,
            'registry' => $this->registry, 'runs' => $this->runs,
        ));
    }

    public function index()
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $environment = $this->mlSelectedEnvironment();
        $this->global['pageTitle'] = 'Job Seeker : ML Monitoring';
        $this->mlRenderView('mlMonitoring', array(
            'selectedEnvironment' => $environment,
            'environments' => $this->mlActiveEnvironments(),
            'monitors' => $this->monitoring->listMonitors($environment),
            'alerts' => $this->monitoring->listAlerts(array('environment' => $environment), 60),
            'openAlertCount' => $this->monitoring->openAlertCount($environment),
            'models' => $this->registry->listModels($environment),
            'datasets' => $this->datasets->listDatasets($environment),
        ));
    }

    public function detail($id)
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $monitor = $this->monitoring->getMonitor((int) $id);
        if (! $monitor) {
            show_404();
            return;
        }
        $model = $this->registry->getModel((int) $monitor->model_id);
        $config = $this->mlDecodeJson($monitor->config_json);
        $baseline = $monitor->baseline_dataset_version_id ? $this->datasets->getVersion((int) $monitor->baseline_dataset_version_id) : NULL;
        $current = NULL;
        if (! empty($config['comparison_dataset_id'])) {
            $current = $this->datasets->latestVersion((int) $config['comparison_dataset_id']);
        } elseif ($baseline) {
            $current = $this->datasets->latestVersion((int) $baseline->dataset_id);
        }
        $this->global['pageTitle'] = 'Job Seeker : Monitor '.$monitor->name;
        $this->mlRenderView('mlMonitorDetail', array(
            'monitor' => $monitor,
            'config' => $config,
            'model' => $model,
            'modelVersions' => $model ? $this->registry->listVersions((int) $model->id, 50) : array(),
            'baseline' => $baseline,
            'current' => $current,
            'baselineFingerprint' => $baseline ? $this->mlDecodeJson($baseline->fingerprint_json) : array(),
            'currentFingerprint' => $current ? $this->mlDecodeJson($current->fingerprint_json) : array(),
            'runsHistory' => $this->monitoring->listMonitorRuns((int) $id, 30),
            'series' => $this->monitoring->series((int) $id, NULL, 120),
            'alerts' => $this->monitoring->listAlerts(array('monitor_id' => (int) $id), 80),
            'datasets' => $this->datasets->listDatasets($monitor->environment),
        ));
    }

    /** Cross-monitor status grid + worst features + alert-volume trend. */
    public function overview()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $environment = $this->mlSelectedEnvironment();
        $grid = array();
        $worst = array();
        foreach ($this->monitoring->listMonitors($environment) as $mo) {
            $grid[] = array('id' => (int) $mo->id, 'key' => (string) $mo->monitor_key, 'name' => (string) $mo->name,
                'status' => (string) $mo->status, 'model' => (string) $mo->model_name, 'last_run_at' => (string) $mo->last_run_at);
            $series = $this->monitoring->series((int) $mo->id, 'drift_psi', 30);
            foreach (isset($series['drift_psi']) ? $series['drift_psi'] : array() as $feature => $pts) {
                if ($feature === '__overall__' || ! $pts) {
                    continue;
                }
                $last = end($pts);
                $worst[] = array('monitor' => $mo->name, 'feature' => $feature, 'psi' => round((float) $last['value'], 4));
            }
        }
        usort($worst, function ($a, $b) { return $b['psi'] <=> $a['psi']; });
        $this->mlJson(array('ok' => TRUE, 'grid' => $grid, 'worst' => array_slice($worst, 0, 12)));
    }

    public function save()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name', TRUE));
        $environment = strtoupper(trim((string) $this->input->post('environment'))) ?: 'ALL';
        $modelId = (int) $this->input->post('model_id');
        if ($name === '' || ! $this->registry->getModel($modelId)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'A name and a valid model are required.'), 422);
            return;
        }
        $key = $this->mlSlug($this->input->post('monitor_key', TRUE) ?: $name);
        if ($this->monitoring->monitorScopeExists($key, $environment, $id)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'A monitor with this key already exists in '.$environment.'.'), 409);
            return;
        }

        $baselineVersionId = (int) $this->input->post('baseline_dataset_version_id');
        if (! $baselineVersionId && (int) $this->input->post('baseline_dataset_id')) {
            $latest = $this->datasets->latestVersion((int) $this->input->post('baseline_dataset_id'));
            $baselineVersionId = $latest ? (int) $latest->id : 0;
        }

        $config = array(
            'thresholds' => array(
                'psi_warning' => (float) ($this->input->post('psi_warning') ?: 0.1),
                'psi_critical' => (float) ($this->input->post('psi_critical') ?: 0.25),
            ),
            'comparison_dataset_id' => (int) $this->input->post('comparison_dataset_id') ?: NULL,
            'accuracy_floor' => (float) $this->input->post('accuracy_floor') ?: NULL,
            'min_prediction_volume' => (int) $this->input->post('min_prediction_volume') ?: NULL,
            'notify_email' => trim((string) $this->input->post('notify_email', TRUE)) ?: NULL,
        );

        $now = date('Y-m-d H:i:s');
        $data = array(
            'monitor_key' => $key,
            'name' => $name,
            'environment' => $environment,
            'model_id' => $modelId,
            'model_version_id' => (int) $this->input->post('model_version_id') ?: NULL,
            'track_stage' => in_array($this->input->post('track_stage'), array('production', 'staging'), TRUE) ? $this->input->post('track_stage') : 'production',
            'baseline_dataset_version_id' => $baselineVersionId ?: NULL,
            'config_json' => json_encode($config, JSON_UNESCAPED_SLASHES),
            'schedule_cron' => trim((string) $this->input->post('schedule_cron')) ?: NULL,
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'updated_at' => $now,
        );
        if ($id <= 0) {
            $data['status'] = 'ok';
            $data['created_at'] = $now;
            $data['owner'] = (string) $this->name;
        }
        $savedId = $this->monitoring->saveMonitor($data, $id);
        $this->mlJson(array('ok' => TRUE, 'id' => $savedId, 'message' => $id > 0 ? 'Monitor updated.' : 'Monitor created.'));
    }

    public function delete()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        if (! $this->monitoring->getMonitor($id)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Monitor not found.'), 404);
            return;
        }
        $this->monitoring->deleteMonitor($id);
        $this->mlJson(array('ok' => TRUE, 'message' => 'Monitor and its history deleted.'));
    }

    public function run()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $monitor = $this->monitoring->getMonitor((int) $this->input->post('id'));
        if (! $monitor) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Monitor not found.'), 404);
            return;
        }
        $result = $this->evaluator()->evaluate($monitor, 'manual',
            (int) $this->input->post('current_dataset_version_id') ?: NULL);
        if (! empty($result['ok'])) {
            $this->notifyOpenAlerts($monitor);
        }
        $this->mlJson($result, empty($result['ok']) ? 422 : 200);
    }

    /**
     * Evaluate every active, scheduled monitor now (manager-initiated "run all").
     * The unattended cron / scheduled-agent equivalent is the session-free
     * machine-learning/runtime/run-due-monitors (MlRuntime), which takes the
     * bearer token instead of a session.
     */
    public function runDue()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return;
        }
        $evaluated = array();
        foreach ($this->monitoring->dueMonitors() as $monitor) {
            $result = $this->evaluator()->evaluate($monitor, 'scheduled');
            if (! empty($result['ok'])) {
                $this->notifyOpenAlerts($monitor);
            }
            $evaluated[] = array('monitor' => $monitor->monitor_key, 'ok' => ! empty($result['ok']),
                'message' => $result['message']);
        }
        $this->mlJson(array('ok' => TRUE, 'evaluated' => $evaluated, 'count' => count($evaluated)));
    }

    // --- alerts ------------------------------------------------------

    public function acknowledgeAlert()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $alert = $this->monitoring->getAlert((int) $this->input->post('id'));
        if (! $alert) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Alert not found.'), 404);
            return;
        }
        $state = $this->input->post('state') === 'resolved' ? 'resolved' : 'acknowledged';
        $this->monitoring->updateAlert((int) $alert->id, array(
            'state' => $state,
            'acknowledged_by' => (string) $this->name,
            'acknowledged_at' => date('Y-m-d H:i:s'),
        ));
        $this->mlJson(array('ok' => TRUE, 'message' => 'Alert '.$state.'.'));
    }

    private function notifyOpenAlerts($monitor)
    {
        $config = $this->mlDecodeJson($monitor->config_json);
        $to = isset($config['notify_email']) ? trim((string) $config['notify_email']) : '';
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $pending = array();
        foreach ($this->monitoring->pendingNotifications(20) as $alert) {
            if ((int) $alert->monitor_id === (int) $monitor->id) {
                $pending[] = $alert;
            }
        }
        if (! $pending) {
            return;
        }
        $this->load->library('email');
        $lines = array('Monitor: '.$monitor->name.' ('.$monitor->environment.')', '');
        foreach ($pending as $alert) {
            $lines[] = '['.strtoupper($alert->severity).'] '.$alert->title;
            $lines[] = '  '.$alert->detail;
        }
        $this->email->clear();
        $this->email->from(getenv('JOBSEEKER_MAIL_FROM') ?: 'jobseeker@localhost', 'JobSeeker ML Monitoring');
        $this->email->to($to);
        $this->email->subject('[JobSeeker ML] '.count($pending).' alert(s) on '.$monitor->name);
        $this->email->message(implode("\n", $lines));
        if ($this->email->send(FALSE)) {
            foreach ($pending as $alert) {
                $this->monitoring->updateAlert((int) $alert->id, array('notified_at' => date('Y-m-d H:i:s')));
            }
        }
    }
}
