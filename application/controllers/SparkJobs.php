<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';

/**
 * PySpark job definitions and their runs. A run provisions an ephemeral cluster
 * on the compute engine, submits the job and tears the cluster down; the browser
 * polls status()/logs() to advance the run.
 */
class SparkJobs extends BaseController
{
    private $samples = array(
        array('key' => 'spark-pi', 'name' => 'SparkPi', 'entry_point' => 'jobs/pi/main.py', 'application_args' => '100',
              'description' => 'Estimate Pi by sampling - the canonical job-cluster smoke test.'),
        array('key' => 'word-count', 'name' => 'Word Count', 'entry_point' => 'jobs/wordcount/main.py', 'application_args' => '15',
              'description' => 'Distributed word count over an in-memory corpus (shuffles + aggregation).'),
        array('key' => 'csv-aggregate', 'name' => 'CSV Aggregate', 'entry_point' => 'jobs/csv-aggregate/main.py', 'application_args' => '',
              'description' => 'Read a bundled CSV and aggregate it with the DataFrame API.'),
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model('SparkCompute_model', 'spark');
        $this->isLoggedIn();
        if (empty($this->global['compute_enabled'])) {
            show_404();
        }
        $this->load->library('SparkClusterOrchestrator', array('model' => $this->spark), 'orchestrator');
    }

    private function canManage()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function jsonResponse($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store, max-age=0')
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function requireManagerPost()
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return FALSE;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
            return FALSE;
        }
        return TRUE;
    }

    private function activeEnvironments()
    {
        if (! $this->db->table_exists('environment')) {
            return array();
        }
        $rows = $this->db->select('Environment')->from('environment')->where('IsActive', 1)->get()->result();
        return array_values(array_unique(array_map(function ($row) {
            return $this->normalizeJobSeekerEnvironment($row->Environment);
        }, $rows)));
    }

    private function selectedEnvironment()
    {
        $value = trim((string) $this->input->get('environment', TRUE));
        if ($value === '') {
            $value = $this->jobSeekerEnvironmentPreference();
        }
        $environment = $this->normalizeJobSeekerEnvironment($value);
        if ($environment === '' || $environment === '*' || $environment === 'ALL') {
            return 'ALL';
        }
        return in_array($environment, $this->activeEnvironments(), TRUE) ? $environment : 'ALL';
    }

    private function normalizeKey($value, $limit = 128)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim(substr($value, 0, $limit), '-');
    }

    private function runPayload($run)
    {
        return array(
            'id' => (int) $run->id,
            'run_key' => (string) $run->run_key,
            'status' => (string) $run->status,
            'worker_count' => (int) $run->worker_count,
            'exit_code' => $run->exit_code === NULL ? NULL : (int) $run->exit_code,
            'error_message' => (string) $run->error_message,
            'triggered_by' => (string) $run->triggered_by,
            'started_at' => (string) $run->started_at,
            'completed_at' => (string) $run->completed_at,
            'terminal' => in_array($run->status, SparkClusterOrchestrator::TERMINAL, TRUE),
        );
    }

    // --- screen ---------------------------------------------------

    public function index()
    {
        if (! $this->canManage()) {
            $this->loadThis();
            return;
        }
        $environment = $this->selectedEnvironment();
        $jobId = (int) $this->input->get('id');
        $job = $jobId > 0 ? $this->spark->getJob($jobId) : NULL;
        if ($job && $environment !== 'ALL' && $job->environment !== $environment) {
            $job = NULL;
        }
        $data = array(
            'selectedEnvironment' => $environment,
            'environments' => $this->activeEnvironments(),
            'jobs' => $this->spark->listJobs($environment),
            'clusters' => $this->spark->listClusters($environment),
            'runtimes' => $this->spark->listRuntimes(TRUE),
            'job' => $job,
            'samples' => $this->samples,
            'recentRuns' => $job ? $this->spark->recentSparkRuns($job->id, 12) : array(),
            'engineHealthy' => $this->orchestrator->engineHealthy(),
            'driverName' => $this->orchestrator->driverName(),
        );
        $this->global['pageTitle'] = 'Job Seeker : Spark Jobs';
        $this->loadViews('sparkJobs', $this->global, $data, NULL);
    }

    public function samples()
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE), 403);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE, 'samples' => $this->samples));
    }

    public function listJobs()
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE, 'jobs' => array()), 403);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE, 'jobs' => $this->spark->listJobs($this->selectedEnvironment())));
    }

    public function get($id)
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE), 403);
            return;
        }
        $job = $this->spark->getJob((int) $id);
        if (! $job) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Job not found.'), 404);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE, 'job' => $job, 'recentRuns' => $this->spark->recentSparkRuns($job->id, 12)));
    }

    public function save()
    {
        if (! $this->requireManagerPost()) {
            return;
        }

        $id = (int) $this->input->post('id');
        $environment = $this->normalizeJobSeekerEnvironment($this->input->post('environment', TRUE));
        $name = trim((string) $this->input->post('name', TRUE));
        $clusterId = (int) $this->input->post('cluster_id');
        $sourceType = $this->input->post('source_type') === 'inline' ? 'inline' : 'repository';
        $entryPoint = ltrim(trim((string) $this->input->post('entry_point', TRUE)), '/');
        $inlineCode = (string) $this->input->post('inline_code');

        if ($name === '' || $environment === '') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Name and environment are required.'), 422);
            return;
        }
        if (! in_array($environment, $this->activeEnvironments(), TRUE)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Unknown environment.'), 422);
            return;
        }
        $cluster = $this->spark->getCluster($clusterId);
        if (! $cluster || $cluster->environment !== $environment) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Choose a cluster in the same environment.'), 422);
            return;
        }

        if ($sourceType === 'inline') {
            if (strlen($inlineCode) < 10) {
                $this->jsonResponse(array('ok' => FALSE, 'message' => 'Inline PySpark code looks empty.'), 422);
                return;
            }
            if (strlen($inlineCode) > 200000) {
                $this->jsonResponse(array('ok' => FALSE, 'message' => 'Inline code is too large (200 KB max). Use a repository job.'), 422);
                return;
            }
            $entryPoint = '';
        } else {
            $inlineCode = '';
            if ($entryPoint === '' || strpos($entryPoint, '..') !== FALSE || ! preg_match('#^(jobs|inline)/[A-Za-z0-9._/-]+\.py$#', $entryPoint)) {
                $this->jsonResponse(array('ok' => FALSE, 'message' => 'Entry point must be a .py file under jobs/ or inline/.'), 422);
                return;
            }
        }

        $applicationArgs = trim((string) $this->input->post('application_args', TRUE));
        if (strlen($applicationArgs) > 2000) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Application args are too long.'), 422);
            return;
        }

        $sparkConf = trim((string) $this->input->post('spark_submit_conf_json'));
        if ($sparkConf !== '' && ! is_array(json_decode($sparkConf, TRUE))) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'spark-submit conf must be a JSON object.'), 422);
            return;
        }

        $jobKey = $this->normalizeKey($this->input->post('job_key', TRUE) ?: $name);
        if ($jobKey === '') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Could not derive a job key from the name.'), 422);
            return;
        }
        if ($this->spark->jobScopeExists($jobKey, $environment, $id)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'A job with this key already exists in '.$environment.'.'), 409);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'job_key' => $jobKey,
            'name' => $name,
            'group_name' => trim((string) $this->input->post('group_name', TRUE)) ?: 'General',
            'description' => trim((string) $this->input->post('description', TRUE)) ?: NULL,
            'environment' => $environment,
            'cluster_id' => $clusterId,
            'source_type' => $sourceType,
            'entry_point' => $entryPoint,
            'application_args' => $applicationArgs !== '' ? $applicationArgs : NULL,
            'inline_code' => $sourceType === 'inline' ? $inlineCode : NULL,
            'spark_submit_conf_json' => $sparkConf !== '' ? $sparkConf : NULL,
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'updated_at' => $now,
        );
        if ($id <= 0) {
            $data['created_at'] = $now;
            $data['owner'] = (string) $this->name;
        }

        $savedId = $this->spark->saveJob($data, $id);
        $this->jsonResponse(array('ok' => TRUE, 'id' => $savedId, 'message' => $id > 0 ? 'Job updated.' : 'Job created.'));
    }

    public function delete()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        if (! $this->spark->getJob($id)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Job not found.'), 404);
            return;
        }
        $this->spark->deleteJob($id);
        $this->jsonResponse(array('ok' => TRUE, 'message' => 'Job deleted.'));
    }

    public function run()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $job = $this->spark->getJob((int) $this->input->post('id'));
        if (! $job || ! $job->is_active) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Job not found or inactive.'), 404);
            return;
        }
        $cluster = $this->spark->getCluster((int) $job->cluster_id);
        $runtime = $cluster ? $this->spark->getRuntime($cluster->runtime_key) : NULL;
        if (! $cluster || ! $runtime) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'The job cluster or runtime is missing.'), 422);
            return;
        }

        $result = $this->orchestrator->start($job, $cluster, $runtime, (string) $this->name);
        $status = $result['ok'] ? 200 : 502;
        $this->jsonResponse(array(
            'ok' => $result['ok'],
            'message' => $result['message'],
            'run' => $result['run'] ? $this->runPayload($result['run']) : NULL,
        ), $status);
    }

    public function status($runId)
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE), 403);
            return;
        }
        $run = $this->spark->getSparkRun((int) $runId);
        if (! $run) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        $run = $this->orchestrator->advance($run);
        $this->jsonResponse(array('ok' => TRUE, 'run' => $this->runPayload($run)));
    }

    public function logs($runId)
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE), 403);
            return;
        }
        $run = $this->spark->getSparkRun((int) $runId);
        if (! $run) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        $this->jsonResponse(array(
            'ok' => TRUE,
            'status' => (string) $run->status,
            'terminal' => in_array($run->status, SparkClusterOrchestrator::TERMINAL, TRUE),
            'logs' => $this->orchestrator->liveLogs($run),
        ));
    }

    public function cancel()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $run = $this->spark->getSparkRun((int) $this->input->post('id'));
        if (! $run) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        $run = $this->orchestrator->cancel($run, (string) $this->name);
        $this->jsonResponse(array('ok' => TRUE, 'run' => $this->runPayload($run), 'message' => 'Run cancelled.'));
    }
}
