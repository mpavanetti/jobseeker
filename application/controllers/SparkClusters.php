<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';

/**
 * Spark cluster specifications (Databricks job-cluster model). These rows only
 * describe a cluster; containers are created by SparkJobs when a job runs.
 */
class SparkClusters extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('SparkCompute_model', 'spark');
        $this->isLoggedIn();
        if (empty($this->global['compute_enabled'])) {
            show_404();
        }
        $this->load->library('SparkClusterOrchestrator', array('model' => $this->spark), 'orchestrator');
        $this->load->library('OpenVsCodeWorkspace', array(), 'editor');
        $this->load->library('SparkWorkspace', array(), 'workspace');
    }

    /** Databricks-style label for a cluster's lifecycle column. */
    private function lifecycleLabel($value)
    {
        return $value === 'persistent' ? 'All-Purpose' : 'Job';
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

    private function decodeJsonMap($raw, $label)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }
        $decoded = json_decode($raw, TRUE);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException($label.' must be a JSON object.');
        }
        $map = array();
        foreach ($decoded as $key => $value) {
            if (! is_scalar($value) && $value !== NULL) {
                throw new InvalidArgumentException($label.' values must be scalars.');
            }
            $map[(string) $key] = (string) $value;
        }
        return $map;
    }

    // --- screen ---------------------------------------------------

    public function index()
    {
        if (! $this->canManage()) {
            $this->loadThis();
            return;
        }
        $environment = $this->selectedEnvironment();
        $data = array(
            'selectedEnvironment' => $environment,
            'environments' => $this->activeEnvironments(),
            'clusters' => $this->spark->listClusters($environment),
            'runtimes' => $this->spark->listRuntimes(TRUE),
        );
        $this->global['pageTitle'] = 'Job Seeker : Spark Clusters';
        $this->loadViews('sparkClusters', $this->global, $data, NULL);
    }

    public function runtimes()
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE, 'runtimes' => array()), 403);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE, 'runtimes' => $this->spark->listRuntimes(TRUE)));
    }

    public function listClusters()
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE, 'clusters' => array()), 403);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE, 'clusters' => $this->spark->listClusters($this->selectedEnvironment())));
    }

    public function get($id)
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE), 403);
            return;
        }
        $cluster = $this->spark->getCluster((int) $id);
        if (! $cluster) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Cluster not found.'), 404);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE, 'cluster' => $cluster));
    }

    public function save()
    {
        if (! $this->requireManagerPost()) {
            return;
        }

        $id = (int) $this->input->post('id');
        $environment = $this->normalizeJobSeekerEnvironment($this->input->post('environment', TRUE));
        $name = trim((string) $this->input->post('name', TRUE));
        $runtimeKey = trim((string) $this->input->post('runtime_key', TRUE));
        $lifecycle = $this->input->post('lifecycle') === 'persistent' ? 'persistent' : 'job';

        if ($name === '' || $environment === '') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Name and environment are required.'), 422);
            return;
        }
        if (! in_array($environment, $this->activeEnvironments(), TRUE)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Unknown environment.'), 422);
            return;
        }
        if (! $this->spark->getRuntime($runtimeKey)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Select a valid Spark runtime.'), 422);
            return;
        }

        $clusterKey = $this->normalizeKey($this->input->post('cluster_key', TRUE) ?: $name);
        if ($clusterKey === '') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Could not derive a cluster key from the name.'), 422);
            return;
        }
        if ($this->spark->clusterScopeExists($clusterKey, $environment, $id)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'A cluster with this key already exists in '.$environment.'.'), 409);
            return;
        }

        try {
            $sparkConf = $this->decodeJsonMap($this->input->post('spark_conf_json'), 'Spark conf');
            $envMap = $this->decodeJsonMap($this->input->post('env_json'), 'Environment');
        } catch (InvalidArgumentException $exception) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => $exception->getMessage()), 422);
            return;
        }

        $minWorkers = max(1, min(64, (int) $this->input->post('min_workers')));
        $maxWorkers = max($minWorkers, min(64, (int) $this->input->post('max_workers') ?: $minWorkers));
        $now = date('Y-m-d H:i:s');
        $data = array(
            'cluster_key' => $clusterKey,
            'name' => $name,
            'group_name' => trim((string) $this->input->post('group_name', TRUE)) ?: 'General',
            'description' => trim((string) $this->input->post('description', TRUE)) ?: NULL,
            'environment' => $environment,
            'runtime_key' => $runtimeKey,
            'lifecycle' => $lifecycle,
            'driver_cores' => max(1, min(16, (int) $this->input->post('driver_cores') ?: 1)),
            'driver_memory_mb' => max(512, min(65536, (int) $this->input->post('driver_memory_mb') ?: 1024)),
            'worker_cores' => max(1, min(32, (int) $this->input->post('worker_cores') ?: 1)),
            'worker_memory_mb' => max(512, min(131072, (int) $this->input->post('worker_memory_mb') ?: 1024)),
            'min_workers' => $minWorkers,
            'max_workers' => $maxWorkers,
            'autoscale' => $this->input->post('autoscale') ? 1 : 0,
            'idle_timeout_minutes' => max(1, min(240, (int) $this->input->post('idle_timeout_minutes') ?: 10)),
            'spark_conf_json' => json_encode((object) $sparkConf, JSON_UNESCAPED_SLASHES),
            'env_json' => json_encode((object) $envMap, JSON_UNESCAPED_SLASHES),
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'updated_at' => $now,
        );
        if ($id <= 0) {
            $data['created_at'] = $now;
            $data['owner'] = (string) $this->name;
        }

        $savedId = $this->spark->saveCluster($data, $id);
        $this->jsonResponse(array('ok' => TRUE, 'id' => $savedId, 'message' => $id > 0 ? 'Cluster updated.' : 'Cluster created.'));
    }

    public function delete()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        if (! $this->spark->getCluster($id)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Cluster not found.'), 404);
            return;
        }
        if ($this->spark->clusterHasJobs($id)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Detach or delete the jobs that use this cluster first.'), 409);
            return;
        }
        // Never orphan a running persistent cluster's containers.
        $cluster = $this->spark->getCluster($id);
        if ($cluster && $cluster->lifecycle === 'persistent') {
            $this->orchestrator->stopPersistent($cluster);
        }
        $this->spark->deleteClusterInstance($id);
        $this->spark->deleteCluster($id);
        $this->jsonResponse(array('ok' => TRUE, 'message' => 'Cluster deleted.'));
    }

    // --- persistent cluster lifecycle ---------------------------------

    private function persistentStatusPayload($cluster)
    {
        $stats = $this->orchestrator->persistentStats($cluster);
        $instance = isset($stats['instance']) ? $stats['instance'] : NULL;
        $base = rtrim(base_url(), '/');
        $links = NULL;
        if ($instance && $instance->status === 'RUNNING') {
            $token = (string) $instance->jupyter_token;
            $tokenQs = $token !== '' ? '?token='.rawurlencode($token) : '';
            $links = array(
                'jupyterVsCode' => 'http://docker-runtime:'.(int) $instance->jupyter_port.'/'.$tokenQs,
                'jupyterLab' => $base.'/spark-persist/'.(int) $instance->jupyter_port.'/lab'.$tokenQs,
                'sparkUi' => $base.'/spark-persist/'.(int) $instance->spark_ui_port.'/',
            );
        }
        $uptime = 0;
        if ($instance && $instance->status === 'RUNNING' && $instance->started_at) {
            $uptime = max(0, time() - strtotime($instance->started_at.' UTC'));
        }
        $workerRunning = 0;
        foreach ($stats['containers'] as $c) {
            if (($c['role'] ?? '') === 'worker' && ! empty($c['running'])) {
                $workerRunning++;
            }
        }
        return array(
            'id' => (int) $cluster->id,
            'lifecycle' => (string) $cluster->lifecycle,
            'lifecycleLabel' => $this->lifecycleLabel((string) $cluster->lifecycle),
            'clusterStatus' => $stats['status'],
            'running' => ! empty($stats['running']),
            'uptimeSeconds' => $uptime,
            'workerRunning' => $workerRunning,
            'workerTarget' => $instance ? (int) $instance->worker_count : (int) $cluster->min_workers,
            'containers' => $stats['containers'],
            'aggregate' => $stats['aggregate'],
            'spark' => $stats['spark'],
            'links' => $links,
            'instance' => $instance ? array(
                'status' => (string) $instance->status,
                'worker_count' => (int) $instance->worker_count,
                'started_at' => (string) $instance->started_at,
                'last_seen_at' => (string) $instance->last_seen_at,
                'error_message' => (string) $instance->error_message,
            ) : NULL,
        );
    }

    private function persistentClusterOr422($requirePost = TRUE)
    {
        if ($requirePost && ! $this->requireManagerPost()) {
            return NULL;
        }
        if (! $requirePost && ! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return NULL;
        }
        $cluster = $this->spark->getCluster((int) $this->input->post('id') ?: (int) $this->input->get('id'));
        if (! $cluster) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Cluster not found.'), 404);
            return NULL;
        }
        if ($cluster->lifecycle !== 'persistent') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Only persistent clusters have a lifecycle.'), 422);
            return NULL;
        }
        return $cluster;
    }

    public function startCluster()
    {
        $cluster = $this->persistentClusterOr422(TRUE);
        if (! $cluster) {
            return;
        }
        $runtime = $this->spark->getRuntime($cluster->runtime_key);
        if (! $runtime) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'The cluster runtime is missing.'), 422);
            return;
        }
        $result = $this->orchestrator->startPersistent($cluster, $runtime, (string) $this->name);
        $this->jsonResponse(array(
            'ok' => $result['ok'],
            'message' => $result['message'],
        ) + $this->persistentStatusPayload($cluster), $result['ok'] ? 200 : 502);
    }

    public function stopCluster()
    {
        $cluster = $this->persistentClusterOr422(TRUE);
        if (! $cluster) {
            return;
        }
        $result = $this->orchestrator->stopPersistent($cluster);
        $this->jsonResponse(array(
            'ok' => $result['ok'],
            'message' => $result['message'],
        ) + $this->persistentStatusPayload($cluster));
    }

    public function clusterStatus($id)
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE), 403);
            return;
        }
        $cluster = $this->spark->getCluster((int) $id);
        if (! $cluster) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Cluster not found.'), 404);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE) + $this->persistentStatusPayload($cluster));
    }

    public function restartCluster()
    {
        $cluster = $this->persistentClusterOr422(TRUE);
        if (! $cluster) {
            return;
        }
        $runtime = $this->spark->getRuntime($cluster->runtime_key);
        if (! $runtime) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'The cluster runtime is missing.'), 422);
            return;
        }
        $this->orchestrator->stopPersistent($cluster);
        $result = $this->orchestrator->startPersistent($cluster, $runtime, (string) $this->name);
        $this->jsonResponse(array(
            'ok' => $result['ok'],
            'message' => $result['ok'] ? 'Persistent cluster restarted.' : $result['message'],
        ) + $this->persistentStatusPayload($cluster), $result['ok'] ? 200 : 502);
    }

    /**
     * Lightweight list feed for the screen's 5s pill refresh: every cluster spec
     * in the environment plus, for All-Purpose ones, their live status, uptime
     * and links. Plus host capacity for the toolbar gauge.
     */
    public function overview()
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE), 403);
            return;
        }
        $environment = $this->selectedEnvironment();
        $rows = array();
        foreach ($this->spark->listClusters($environment) as $cluster) {
            $row = array(
                'id' => (int) $cluster->id,
                'name' => (string) $cluster->name,
                'cluster_key' => (string) $cluster->cluster_key,
                'lifecycle' => (string) $cluster->lifecycle,
                'lifecycleLabel' => $this->lifecycleLabel((string) $cluster->lifecycle),
                'is_active' => (int) $cluster->is_active,
                'clusterStatus' => $cluster->lifecycle === 'persistent' ? 'STOPPED' : 'JOB',
                'uptimeSeconds' => 0,
                'workerRunning' => 0,
                'workerTarget' => (int) $cluster->min_workers,
                'links' => NULL,
            );
            if ($cluster->lifecycle === 'persistent') {
                $payload = $this->persistentStatusPayload($cluster);
                $row['clusterStatus'] = $payload['clusterStatus'];
                $row['uptimeSeconds'] = $payload['uptimeSeconds'];
                $row['links'] = $payload['links'];
                $running = 0;
                foreach ($payload['containers'] as $c) {
                    if (($c['role'] ?? '') === 'worker' && ! empty($c['running'])) {
                        $running++;
                    }
                }
                $row['workerRunning'] = $running;
                if ($payload['instance']) {
                    $row['workerTarget'] = max($row['workerTarget'], (int) $payload['instance']['worker_count']);
                }
            }
            $rows[] = $row;
        }
        $capacity = $this->orchestrator->capacity(NULL);
        $this->jsonResponse(array('ok' => TRUE, 'environment' => $environment, 'clusters' => $rows, 'capacity' => $capacity));
    }

    /**
     * POST id -> scaffold (or refresh) an All-Purpose cluster's notebook
     * scratchpad in OpenVSCode and return the editor URL.
     */
    public function notebook()
    {
        $cluster = $this->persistentClusterOr422(TRUE);
        if (! $cluster) {
            return;
        }
        if (! $this->editor->enabled()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'OpenVSCode Server is disabled for this deployment.'), 404);
            return;
        }
        $runState = $this->editor->ensureRunning();
        if (empty($runState['available'])) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => $runState['message']), 503);
            return;
        }

        $repoRoot = rtrim((string) (getenv('JOBSEEKER_COMPUTE_REPOSITORY_ROOT') ?: '/php/repository'), '/');
        $key = preg_replace('/[^a-z0-9._-]+/', '-', strtolower((string) $cluster->cluster_key));
        $dir = $repoRoot.'/spark/scratch/'.$key;
        if (! is_dir($dir) && ! @mkdir($dir, 0775, TRUE) && ! is_dir($dir)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Could not create the scratch workspace.'), 500);
            return;
        }

        $instance = $this->spark->getClusterInstance($cluster->id);
        $persist = NULL;
        $defaultMaster = '';
        if ($instance && $instance->status === 'RUNNING') {
            $base = rtrim(base_url(), '/');
            $token = (string) $instance->jupyter_token;
            $tokenQs = $token !== '' ? '?token='.rawurlencode($token) : '';
            $persist = array(
                'name' => (string) $cluster->name,
                'jupyterVsCodeUrl' => 'http://docker-runtime:'.(int) $instance->jupyter_port.'/'.$tokenQs,
                'jupyterLabUrl' => $base.'/spark-persist/'.(int) $instance->jupyter_port.'/lab'.$tokenQs,
                'sparkUiUrl' => $base.'/spark-persist/'.(int) $instance->spark_ui_port.'/',
            );
            $defaultMaster = 'spark://master:7077';
        }

        $this->workspace->scaffold($dir, $key, $cluster->name.' — scratchpad', $defaultMaster, $persist);
        $url = $this->editor->folderUrl($dir);
        if ($url === '') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Could not resolve the editor workspace path.'), 500);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE, 'url' => $url, 'attached' => $persist ? TRUE : FALSE,
            'jupyterVsCodeUrl' => $persist ? $persist['jupyterVsCodeUrl'] : NULL));
    }
}
