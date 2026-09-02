<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Agent-callable surface for the ML platform. No CodeIgniter session - bearer
 * token only (JOBSEEKER_ML_API_TOKEN), CSRF-excluded in config.php, mirroring
 * ConnectorRuntime.php / SparkRuntime.php.
 *
 * Two kinds of caller:
 *   - the Jenkins build step of an ML job -> trigger / status / logs / cancel
 *   - the jobseeker_ml SDK inside a run container -> heartbeat / ingest /
 *     artifact / model / dataset / resolve-dataset / resolve-model
 */
class MlRuntime extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('MlCatalog_model', 'catalog');
        $this->load->model('MlRun_model', 'runs');
        $this->load->model('MlDataset_model', 'datasets');
        $this->load->model('MlRegistry_model', 'registry');
        $this->load->library('MlRunOrchestrator', array(
            'catalog' => $this->catalog, 'runs' => $this->runs,
        ), 'orchestrator');
        require_once APPPATH.'libraries/MlArtifactStore.php';
        require_once APPPATH.'libraries/MlDatasetProfiler.php';
    }

    // --- plumbing ------------------------------------------------------

    private function json($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function authorized()
    {
        $expected = trim((string) getenv('JOBSEEKER_ML_API_TOKEN'));
        $authorization = trim((string) $this->input->get_request_header('Authorization', TRUE));
        $prefix = 'Bearer ';
        if ($expected === '' || strncmp($authorization, $prefix, strlen($prefix)) !== 0) {
            return FALSE;
        }
        return hash_equals($expected, substr($authorization, strlen($prefix)));
    }

    private function guard($method = 'POST')
    {
        if ($this->input->method(TRUE) !== $method) {
            $this->json(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
            return FALSE;
        }
        if (! $this->authorized()) {
            $this->json(array('ok' => FALSE, 'message' => 'Unauthorized.'), 401);
            return FALSE;
        }
        return TRUE;
    }

    private function normalizedEnvironment($value)
    {
        $value = strtoupper(trim((string) $value));
        return preg_match('/^[A-Z0-9._-]{1,100}$/', $value) ? $value : FALSE;
    }

    private function activeRunFromKey($runKey)
    {
        $run = $this->runs->getRunByKey((string) $runKey);
        if (! $run) {
            $this->json(array('ok' => FALSE, 'message' => 'Unknown run.'), 404);
            return NULL;
        }
        return $run;
    }

    private function jsonBody()
    {
        $raw = (string) $this->input->raw_input_stream;
        $decoded = json_decode($raw, TRUE);
        return is_array($decoded) ? $decoded : array();
    }

    private function store()
    {
        return MlArtifactStoreFactory::make();
    }

    // --- Jenkins build step -------------------------------------------------

    /** POST job_key, environment, build?, triggered_by? -> starts a run. */
    public function trigger()
    {
        if (! $this->guard()) {
            return;
        }
        $environment = $this->normalizedEnvironment($this->input->post('environment'));
        $jobKey = trim((string) $this->input->post('job_key'));
        if ($environment === FALSE || $jobKey === '' || strlen($jobKey) > 128) {
            $this->json(array('ok' => FALSE, 'message' => 'A valid job_key and environment are required.'), 422);
            return;
        }
        $job = $this->catalog->getJobByScope($jobKey, $environment);
        if (! $job || ! $job->is_active) {
            $this->json(array('ok' => FALSE, 'message' => 'No active ML job "'.$jobKey.'" in '.$environment.'.'), 404);
            return;
        }
        $runtime = $this->catalog->getRuntime($job->runtime_key);
        if (! $runtime) {
            $this->json(array('ok' => FALSE, 'message' => 'The job runtime "'.$job->runtime_key.'" is missing.'), 422);
            return;
        }
        $result = $this->orchestrator->start($job, $runtime, array(
            'trigger_source' => 'jenkins',
            'triggered_by' => trim((string) $this->input->post('triggered_by')) ?: 'Jenkins',
            'jenkins_build_number' => (int) $this->input->post('build'),
        ));
        $this->json(array(
            'ok' => (bool) $result['ok'],
            'message' => $result['message'],
            'run' => $result['run'] ? $this->runPayload($result['run']) : NULL,
        ), $result['ok'] ? 200 : 502);
    }

    /** GET - advance and report a run by key. Poll target for the build step. */
    public function status($runKey)
    {
        if (! $this->authorized()) {
            $this->json(array('ok' => FALSE, 'message' => 'Unauthorized.'), 401);
            return;
        }
        $run = $this->activeRunFromKey($runKey);
        if (! $run) {
            return;
        }
        $run = $this->orchestrator->advance($run);
        $this->json(array('ok' => TRUE, 'run' => $this->runPayload($run)));
    }

    public function logs($runKey)
    {
        if (! $this->authorized()) {
            $this->json(array('ok' => FALSE, 'message' => 'Unauthorized.'), 401);
            return;
        }
        $run = $this->activeRunFromKey($runKey);
        if (! $run) {
            return;
        }
        $this->json(array(
            'ok' => TRUE,
            'status' => (string) $run->status,
            'terminal' => in_array($run->status, MlRunOrchestrator::TERMINAL, TRUE),
            'logs' => $this->orchestrator->liveLogs($run),
        ));
    }

    public function cancel()
    {
        if (! $this->guard()) {
            return;
        }
        $run = $this->activeRunFromKey($this->input->post('run_key'));
        if (! $run) {
            return;
        }
        $run = $this->orchestrator->cancel($run, trim((string) $this->input->post('actor')) ?: 'Jenkins');
        $this->json(array('ok' => TRUE, 'run' => $this->runPayload($run)));
    }

    /**
     * Scheduled-agent / cron entry point: evaluate every active monitor that
     * carries a schedule. Session-free, bearer token only - the browser
     * "evaluate all" equivalent stays behind MlMonitoring.
     */
    public function runDueMonitors()
    {
        if (! $this->guard()) {
            return;
        }
        $this->load->model('MlMonitoring_model', 'monitoring');
        require_once APPPATH.'libraries/MlMonitorEvaluator.php';
        $evaluator = new MlMonitorEvaluator(array(
            'monitors' => $this->monitoring, 'datasets' => $this->datasets,
            'registry' => $this->registry, 'runs' => $this->runs,
        ));
        $evaluated = array();
        foreach ($this->monitoring->dueMonitors() as $monitor) {
            $result = $evaluator->evaluate($monitor, 'scheduled');
            $evaluated[] = array('monitor' => $monitor->monitor_key, 'ok' => ! empty($result['ok']),
                'message' => $result['message']);
        }
        $this->json(array('ok' => TRUE, 'count' => count($evaluated), 'evaluated' => $evaluated));
    }

    public function heartbeat()
    {
        if (! $this->guard()) {
            return;
        }
        $run = $this->runs->getRunByKey((string) $this->input->post('run_key'));
        if ($run) {
            $this->runs->updateRun($run->id, array('heartbeat_at' => date('Y-m-d H:i:s')));
        }
        $this->json(array('ok' => TRUE));
    }

    // --- SDK: metrics / params / tags / summary ---------------------------

    public function ingest()
    {
        if (! $this->guard()) {
            return;
        }
        $body = $this->jsonBody();
        $run = $this->runs->getRunByKey((string) (isset($body['run_key']) ? $body['run_key'] : ''));
        if (! $run) {
            $this->json(array('ok' => FALSE, 'message' => 'Unknown run.'), 404);
            return;
        }
        $kind = isset($body['kind']) ? (string) $body['kind'] : '';

        if ($kind === 'metric' && ! empty($body['metrics']) && is_array($body['metrics'])) {
            $now = date('Y-m-d H:i:s');
            foreach ($body['metrics'] as $metric) {
                if (! isset($metric['key']) || ! isset($metric['value']) || ! is_numeric($metric['value'])) {
                    continue;
                }
                $this->runs->recordMetric((int) $run->id, substr((string) $metric['key'], 0, 120),
                    (float) $metric['value'], isset($metric['step']) ? (int) $metric['step'] : 0, $now);
            }
            $this->runs->updateRun($run->id, array(
                'heartbeat_at' => date('Y-m-d H:i:s'),
                'metrics_summary_json' => json_encode($this->runs->latestMetrics((int) $run->id), JSON_UNESCAPED_SLASHES),
            ));
        } elseif ($kind === 'param' && ! empty($body['params']) && is_array($body['params'])) {
            $existing = json_decode((string) $run->params_json, TRUE);
            $existing = is_array($existing) ? $existing : array();
            foreach ($body['params'] as $k => $v) {
                $existing[substr((string) $k, 0, 120)] = is_scalar($v) ? $v : json_encode($v);
            }
            $this->runs->updateRun($run->id, array('params_json' => json_encode($existing, JSON_UNESCAPED_SLASHES)));
        } elseif ($kind === 'tag' && ! empty($body['tags']) && is_array($body['tags'])) {
            $existing = json_decode((string) $run->tags_json, TRUE);
            $existing = is_array($existing) ? $existing : array();
            foreach ($body['tags'] as $k => $v) {
                $existing[substr((string) $k, 0, 120)] = (string) $v;
            }
            $this->runs->updateRun($run->id, array('tags_json' => json_encode($existing, JSON_UNESCAPED_SLASHES)));
        } elseif ($kind === 'summary') {
            // advisory only - the orchestrator owns the authoritative status.
            $this->runs->updateRun($run->id, array('heartbeat_at' => date('Y-m-d H:i:s')));
        }
        $this->json(array('ok' => TRUE));
    }

    // --- SDK: artifact upload -------------------------------------------------

    public function artifact()
    {
        if (! $this->guard()) {
            return;
        }
        $run = $this->runs->getRunByKey((string) $this->input->post('run_key'));
        if (! $run) {
            $this->json(array('ok' => FALSE, 'message' => 'Unknown run.'), 404);
            return;
        }
        $file = $this->receiveFile('file');
        if (! $file['ok']) {
            $this->json(array('ok' => FALSE, 'message' => $file['message']), 422);
            return;
        }
        $role = preg_replace('/[^a-z_]/', '', strtolower((string) $this->input->post('role'))) ?: 'artifact';
        $name = $this->safeName((string) $this->input->post('name'), $file['name']);

        $stored = $this->store()->putFile($file['path'], $file['type'], $name);
        @unlink($file['path']);
        if (empty($stored['ok'])) {
            $this->json(array('ok' => FALSE, 'message' => $stored['message']), 500);
            return;
        }
        $artifactId = $this->runs->upsertArtifact($stored['sha256'], $stored['media_type'],
            $stored['size_bytes'], $this->store()->name(), $stored['uri'], $name);
        $this->runs->linkRunArtifact((int) $run->id, $artifactId, $role, $name);
        $this->json(array('ok' => TRUE, 'artifact_id' => $artifactId, 'sha256' => $stored['sha256']));
    }

    // --- SDK: model version -------------------------------------------------

    public function model()
    {
        if (! $this->guard()) {
            return;
        }
        $run = $this->runs->getRunByKey((string) $this->input->post('run_key'));
        if (! $run) {
            $this->json(array('ok' => FALSE, 'message' => 'Unknown run.'), 404);
            return;
        }
        $file = $this->receiveFile('file');
        if (! $file['ok']) {
            $this->json(array('ok' => FALSE, 'message' => $file['message']), 422);
            return;
        }
        $stored = $this->store()->putFile($file['path'], $file['type'], $file['name']);
        @unlink($file['path']);
        if (empty($stored['ok'])) {
            $this->json(array('ok' => FALSE, 'message' => $stored['message']), 500);
            return;
        }
        $artifactId = $this->runs->upsertArtifact($stored['sha256'], $stored['media_type'],
            $stored['size_bytes'], $this->store()->name(), $stored['uri'], $file['name']);
        $this->runs->linkRunArtifact((int) $run->id, $artifactId, 'model', $file['name']);

        $modelKey = $this->slug((string) $this->input->post('model_key'), 'model');
        $name = trim((string) $this->input->post('name')) ?: $modelKey;
        $framework = trim((string) $this->input->post('framework')) ?: NULL;
        $metrics = $this->decodeJsonField('metrics_json');
        $params = $this->decodeJsonField('params_json');
        $signature = $this->decodeJsonField('signature_json');
        $register = $this->input->post('register') !== '0';

        $response = array('ok' => TRUE, 'artifact_id' => $artifactId, 'registered' => FALSE);
        if ($register) {
            $modelId = $this->registry->findOrCreateModel($modelKey, $name, $run->environment,
                (string) $run->triggered_by, $this->inferTask($run, $metrics));
            $trainingDatasetVersionId = $this->firstInputDatasetVersion((int) $run->id);
            $created = $this->registry->createVersion($modelId, array(
                'run_id' => (int) $run->id,
                'artifact_id' => $artifactId,
                'framework' => $framework,
                'metrics_json' => $metrics ? json_encode($metrics, JSON_UNESCAPED_SLASHES) : NULL,
                'params_json' => $params ? json_encode($params, JSON_UNESCAPED_SLASHES) : NULL,
                'signature_json' => $signature ? json_encode($signature, JSON_UNESCAPED_SLASHES) : NULL,
                'training_dataset_version_id' => $trainingDatasetVersionId,
                'created_by' => (string) $run->triggered_by,
            ));
            $this->runs->addEdge('run', (int) $run->id, 'model_version', $created['id'], 'produces');
            if ($trainingDatasetVersionId) {
                $this->runs->addEdge('dataset_version', $trainingDatasetVersionId, 'model_version', $created['id'], 'trained_on');
            }
            $response['registered'] = TRUE;
            $response['model_id'] = $modelId;
            $response['model_version_id'] = $created['id'];
            $response['version'] = $created['version'];
        }
        $this->json($response);
    }

    // --- SDK: dataset output -------------------------------------------------

    public function dataset()
    {
        if (! $this->guard()) {
            return;
        }
        $run = $this->runs->getRunByKey((string) $this->input->post('run_key'));
        if (! $run) {
            $this->json(array('ok' => FALSE, 'message' => 'Unknown run.'), 404);
            return;
        }
        $file = $this->receiveFile('file');
        if (! $file['ok']) {
            $this->json(array('ok' => FALSE, 'message' => $file['message']), 422);
            return;
        }
        $key = $this->slug((string) $this->input->post('dataset_key'), 'dataset');
        $name = trim((string) $this->input->post('name')) ?: $key;
        $role = preg_replace('/[^a-z_]/', '', strtolower((string) $this->input->post('role'))) ?: 'output';
        $format = preg_replace('/[^a-z0-9]/', '', strtolower((string) $this->input->post('format'))) ?: 'csv';
        $wantProfile = $this->input->post('profile') !== '0';

        $datasetId = $this->datasets->findOrCreateDataset($key, $name, $run->environment,
            (string) $run->triggered_by, 'run_output');
        $dataset = $this->datasets->getDataset($datasetId);
        $version = $this->datasets->nextVersionNumber($datasetId);
        $stored = $this->datasets->storeVersionFile($dataset->dataset_key, $dataset->environment, $version, $format, $file['path']);

        $profile = array('ok' => TRUE, 'needs_runtime_profile' => TRUE);
        if ($wantProfile) {
            $profiler = new MlDatasetProfiler();
            $profile = $profiler->profile($file['path'], array('format' => $format));
        }
        @unlink($file['path']);
        if (empty($stored['ok'])) {
            $this->json(array('ok' => FALSE, 'message' => $stored['message']), 500);
            return;
        }

        $created = $this->datasets->createVersion($datasetId, array(
            'source_type' => 'run_output',
            'source_ref_json' => json_encode(array('run_key' => $run->run_key, 'role' => $role), JSON_UNESCAPED_SLASHES),
            'storage_path' => $stored['relative_path'],
            'checksum' => $stored['checksum'],
            'format' => $format,
            'row_count' => isset($profile['row_count']) ? $profile['row_count'] : NULL,
            'column_count' => isset($profile['column_count']) ? $profile['column_count'] : NULL,
            'size_bytes' => $stored['size'],
            'schema_json' => ! empty($profile['schema']) ? json_encode($profile['schema'], JSON_UNESCAPED_SLASHES) : NULL,
            'profile_json' => ! empty($profile['profile']) ? json_encode(array(
                'columns' => $profile['profile'], 'sample' => isset($profile['sample']) ? $profile['sample'] : NULL,
            ), JSON_UNESCAPED_SLASHES) : NULL,
            'fingerprint_json' => ! empty($profile['fingerprint']) ? json_encode($profile['fingerprint'], JSON_UNESCAPED_SLASHES) : NULL,
            'profile_status' => ! empty($profile['needs_runtime_profile']) ? 'skipped' : (! empty($profile['ok']) ? 'done' : 'failed'),
            'profile_error' => empty($profile['ok']) ? substr((string) (isset($profile['message']) ? $profile['message'] : ''), 0, 2000) : NULL,
            'produced_by_run_id' => (int) $run->id,
            'created_by' => (string) $run->triggered_by,
        ));
        $this->runs->addEdge('run', (int) $run->id, 'dataset_version', $created['id'], 'output:'.$role);
        $this->json(array('ok' => TRUE, 'dataset_id' => $datasetId,
            'dataset_version_id' => $created['id'], 'version' => $created['version']));
    }

    // --- SDK: resolve + download -------------------------------------------------

    public function resolveDataset()
    {
        if (! $this->guard()) {
            return;
        }
        $body = $this->jsonBody();
        $run = $this->runs->getRunByKey((string) (isset($body['run_key']) ? $body['run_key'] : ''));
        if (! $run) {
            $this->json(array('ok' => FALSE, 'message' => 'Unknown run.'), 404);
            return;
        }
        $ref = isset($body['ref']) ? (string) $body['ref'] : '';
        $role = isset($body['role']) ? (string) $body['role'] : '';
        $version = NULL;

        $job = $run->job_id ? $this->catalog->getJob((int) $run->job_id) : NULL;
        $bindings = $job ? json_decode((string) $job->dataset_bindings_json, TRUE) : NULL;
        if (is_array($bindings) && $role !== '' && isset($bindings[$role]) && ! empty($bindings[$role]['dataset_version_id'])) {
            $version = $this->datasets->getVersion((int) $bindings[$role]['dataset_version_id']);
        }
        if (! $version && ctype_digit($ref)) {
            $version = $this->datasets->getVersion((int) $ref);
        }
        if (! $version) {
            $dataset = $this->datasets->getDatasetByKey($ref, $run->environment);
            if ($dataset) {
                $version = $this->datasets->latestVersion((int) $dataset->id);
            }
        }
        if (! $version || $this->datasets->versionAbsolutePath($version) === FALSE) {
            $this->json(array('ok' => FALSE, 'message' => 'No downloadable dataset version for "'.$ref.'".'), 404);
            return;
        }
        $this->runs->addEdge('dataset_version', (int) $version->id, 'run', (int) $run->id, 'input:'.($role ?: 'resolved'));
        $this->json(array(
            'ok' => TRUE,
            'dataset_version_id' => (int) $version->id,
            'version' => (int) $version->version,
            'format' => (string) $version->format,
            'schema' => (function ($j) { $d = json_decode((string) $j, TRUE); return is_array($d) ? $d : array(); })($version->schema_json),
            'row_count' => $version->row_count === NULL ? NULL : (int) $version->row_count,
            'download_url' => 'machine-learning/runtime/dataset-download/'.(int) $version->id,
        ));
    }

    public function resolveModel()
    {
        if (! $this->guard()) {
            return;
        }
        $body = $this->jsonBody();
        $run = $this->runs->getRunByKey((string) (isset($body['run_key']) ? $body['run_key'] : ''));
        if (! $run) {
            $this->json(array('ok' => FALSE, 'message' => 'Unknown run.'), 404);
            return;
        }
        $ref = isset($body['ref']) ? (string) $body['ref'] : '';
        $version = NULL;
        if (preg_match('#^(.+?)[:@](\d+|latest|production|staging)$#', $ref, $m)) {
            $model = $this->registry->getModelByKey($this->slug($m[1], ''), $run->environment);
            if ($model) {
                $version = ctype_digit($m[2])
                    ? $this->firstWhere($this->registry->listVersions((int) $model->id, 300), 'version', (int) $m[2])
                    : $this->registry->versionInStage((int) $model->id, $m[2] === 'latest' ? 'none' : $m[2]);
                if (! $version && $m[2] === 'latest') {
                    $all = $this->registry->listVersions((int) $model->id, 1);
                    $version = $all ? $all[0] : NULL;
                }
            }
        } else {
            $model = $this->registry->getModelByKey($this->slug($ref, ''), $run->environment);
            if ($model) {
                $version = $this->registry->versionInStage((int) $model->id, 'production');
                if (! $version) {
                    $all = $this->registry->listVersions((int) $model->id, 1);
                    $version = $all ? $all[0] : NULL;
                }
            }
        }
        if (! $version || ! $version->artifact_id) {
            $this->json(array('ok' => FALSE, 'message' => 'No downloadable model for "'.$ref.'".'), 404);
            return;
        }
        $this->runs->addEdge('model_version', (int) $version->id, 'run', (int) $run->id, 'input');
        $this->json(array(
            'ok' => TRUE,
            'model_version_id' => (int) $version->id,
            'framework' => (string) $version->framework,
            'download_url' => 'machine-learning/runtime/model-download/'.(int) $version->id,
        ));
    }

    public function datasetDownload($versionId)
    {
        if (! $this->authorized()) {
            $this->output->set_status_header(401);
            return;
        }
        $version = $this->datasets->getVersion((int) $versionId);
        $path = $version ? $this->datasets->versionAbsolutePath($version) : FALSE;
        if ($path === FALSE) {
            $this->output->set_status_header(404);
            return;
        }
        $this->output
            ->set_content_type('application/octet-stream')
            ->set_header('Content-Length: '.filesize($path))
            ->set_header('Cache-Control: private, no-store')
            ->set_output('')
            ->_display();
        $h = fopen($path, 'rb');
        while ($h && ! feof($h)) {
            echo fread($h, 1048576);
        }
        if ($h) {
            fclose($h);
        }
        exit;
    }

    public function modelDownload($versionId)
    {
        if (! $this->authorized()) {
            $this->output->set_status_header(401);
            return;
        }
        $version = $this->registry->getVersion((int) $versionId);
        if (! $version || ! $version->artifact_id) {
            $this->output->set_status_header(404);
            return;
        }
        $this->streamArtifact((int) $version->artifact_id);
    }

    // --- helpers ------------------------------------------------------

    private function streamArtifact($artifactId)
    {
        $artifact = $this->runs->getArtifact($artifactId);
        if (! $artifact) {
            $this->output->set_status_header(404);
            return;
        }
        $path = $this->store()->localPath($artifact->storage_uri);
        if ($path === FALSE) {
            $this->output->set_status_header(404);
            return;
        }
        $this->output
            ->set_content_type($artifact->media_type ?: 'application/octet-stream')
            ->set_header('Content-Length: '.filesize($path))
            ->set_header('Cache-Control: private, no-store')
            ->set_output('')
            ->_display();
        $handle = fopen($path, 'rb');
        while ($handle && ! feof($handle)) {
            echo fread($handle, 1048576);
        }
        if ($handle) {
            fclose($handle);
        }
        exit;
    }

    private function receiveFile($field)
    {
        if (! isset($_FILES[$field]) || ! isset($_FILES[$field]['tmp_name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => FALSE, 'message' => 'No uploaded file in "'.$field.'".');
        }
        $tmp = $_FILES[$field]['tmp_name'];
        if (! is_uploaded_file($tmp)) {
            return array('ok' => FALSE, 'message' => 'Rejected non-upload file.');
        }
        $max = 512 * 1048576;
        if (filesize($tmp) > $max) {
            return array('ok' => FALSE, 'message' => 'Uploaded file exceeds 512 MB.');
        }
        $dest = tempnam(sys_get_temp_dir(), 'jsmlrx');
        if ($dest === FALSE || ! move_uploaded_file($tmp, $dest)) {
            return array('ok' => FALSE, 'message' => 'Could not buffer the upload.');
        }
        return array(
            'ok' => TRUE,
            'path' => $dest,
            'name' => $this->safeName((string) $_FILES[$field]['name'], 'upload.bin'),
            'type' => (string) $_FILES[$field]['type'] ?: 'application/octet-stream',
        );
    }

    private function safeName($candidate, $fallback)
    {
        $candidate = basename(trim((string) $candidate));
        $candidate = preg_replace('/[^A-Za-z0-9._-]+/', '_', $candidate);
        $candidate = trim($candidate, '._-');
        return $candidate !== '' ? substr($candidate, 0, 200) : $fallback;
    }

    private function slug($value, $fallback)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? substr($value, 0, 120) : $fallback;
    }

    private function decodeJsonField($field)
    {
        $decoded = json_decode((string) $this->input->post($field), TRUE);
        return is_array($decoded) ? $decoded : array();
    }

    private function firstInputDatasetVersion($runId)
    {
        foreach ($this->runs->edgesInto('run', $runId) as $edge) {
            if ($edge->src_kind === 'dataset_version' && strpos((string) $edge->role, 'input') === 0) {
                return (int) $edge->src_id;
            }
        }
        return NULL;
    }

    private function firstWhere($rows, $prop, $value)
    {
        foreach ($rows as $row) {
            if ((string) $row->$prop === (string) $value) {
                return $row;
            }
        }
        return NULL;
    }

    private function inferTask($run, $metrics)
    {
        $keys = array_map('strtolower', array_keys(is_array($metrics) ? $metrics : array()));
        foreach ($keys as $k) {
            if (strpos($k, 'auc') !== FALSE || strpos($k, 'accuracy') !== FALSE || strpos($k, 'f1') !== FALSE) {
                return 'classification';
            }
            if (strpos($k, 'rmse') !== FALSE || strpos($k, 'mae') !== FALSE || strpos($k, 'r2') !== FALSE) {
                return 'regression';
            }
        }
        return 'classification';
    }

    private function runPayload($run)
    {
        return array(
            'id' => (int) $run->id,
            'run_key' => (string) $run->run_key,
            'status' => (string) $run->status,
            'run_type' => (string) $run->run_type,
            'exit_code' => $run->exit_code === NULL ? NULL : (int) $run->exit_code,
            'error_message' => (string) $run->error_message,
            'terminal' => in_array($run->status, MlRunOrchestrator::TERMINAL, TRUE),
            'started_at' => (string) $run->started_at,
            'completed_at' => (string) $run->completed_at,
        );
    }
}
