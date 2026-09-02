<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';
require APPPATH.'/controllers/concerns/MlControllerTrait.php';
require APPPATH.'/controllers/concerns/MlJenkinsTrait.php';

/**
 * ML jobs (v2): workspace-backed Python projects. Each job owns a real
 * repository/ml/jobs/<key>/ directory (main.py, Dockerfile, requirements.txt or
 * pyproject.toml, jobseeker.yml, .jobseeker/). JobSeeker bakes a per-job image
 * from it and runs that. Author here or in OpenVSCode ("Open in Editor"); "Test
 * run" streams a short run. The same authoring helpers are reused by the Create
 * Job screen via JobCreationMlTrait.
 */
class MlJobs extends BaseController
{
    use MlControllerTrait;
    use MlJenkinsTrait;

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
        $this->load->model('MlCatalog_model', 'catalog');
        $this->load->model('MlRun_model', 'runs');
        $this->load->model('MlDataset_model', 'datasets');
        $this->load->library('MlRunOrchestrator', array('catalog' => $this->catalog, 'runs' => $this->runs), 'orchestrator');
        $this->load->library('MlJobIntrospector', array(), 'introspector');
        require_once APPPATH.'libraries/MlWorkspace.php';
    }

    private function workspace()
    {
        return new MlWorkspace();
    }

    // --- screens ------------------------------------------------------

    public function index()
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $environment = $this->mlSelectedEnvironment();
        $jobId = (int) $this->input->get('id');
        $job = $jobId > 0 ? $this->catalog->getJob($jobId) : NULL;
        $ws = $job ? $this->workspace()->read($job->job_key) : array();
        $this->global['pageTitle'] = 'Job Seeker : ML Jobs';
        $this->mlRenderView('mlJobs', array(
            'selectedEnvironment' => $environment,
            'environments' => $this->mlActiveEnvironments(),
            'jobs' => $this->catalog->listJobs($environment),
            'runtimes' => $this->catalog->listRuntimes(TRUE),
            'samples' => $this->catalog->listSamples(TRUE),
            'experiments' => $this->catalog->listExperiments($environment),
            'datasets' => $this->datasetPickList($environment),
            'job' => $job,
            'workspace' => $ws,
            'recentRuns' => $job ? $this->runs->recentRunsForJob((int) $job->id, 15) : array(),
            'engineHealthy' => $this->orchestrator->engineHealthy(),
            'driverName' => $this->orchestrator->driverName(),
            'editorEnabled' => $this->workspace()->editorEnabled(),
            'capacity' => $this->orchestrator->capacity(),
        ));
    }

    private function datasetPickList($environment)
    {
        $out = array();
        foreach ($this->datasets->listDatasets($environment) as $ds) {
            $latest = $this->datasets->latestVersion((int) $ds->id);
            $out[] = array(
                'id' => (int) $ds->id, 'key' => (string) $ds->dataset_key, 'name' => (string) $ds->name,
                'kind' => (string) $ds->kind, 'environment' => (string) $ds->environment,
                'latest_version' => (int) $ds->latest_version,
                'rows' => $latest ? (int) $latest->row_count : NULL,
                'schema' => $latest ? $this->mlDecodeJson($latest->schema_json) : array(),
            );
        }
        return $out;
    }

    // --- JSON: catalogue ------------------------------------------------------

    public function listJobs()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE, 'jobs' => array()), 403);
            return;
        }
        $this->mlJson(array('ok' => TRUE, 'jobs' => $this->catalog->listJobs($this->mlSelectedEnvironment())));
    }

    public function get($id)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $job = $this->catalog->getJob((int) $id);
        if (! $job) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Job not found.'), 404);
            return;
        }
        $this->mlJson(array(
            'ok' => TRUE,
            'job' => $job,
            'workspace' => $this->workspace()->read($job->job_key),
            'recentRuns' => $this->runs->recentRunsForJob((int) $job->id, 15),
        ));
    }

    public function introspect()
    {
        if (! $this->mlCanManage() || $this->input->method(TRUE) !== 'POST') {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $context = array();
        $sampleKey = trim((string) $this->input->post('sample_key'));
        if ($sampleKey !== '' && ($sample = $this->catalog->getSampleByKey($sampleKey))) {
            $context['sample_run_type'] = $sample->run_type;
        }
        $result = $this->introspector->analyze((string) $this->input->post('code'),
            (string) $this->input->post('application_args'), $context);
        $result['ok'] = TRUE;
        $result['label'] = $this->introspector->label($result['run_type']);
        $this->mlJson($result);
    }

    public function save()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $outcome = $this->persistJob($this->input->post(NULL, FALSE), (string) $this->name);
        $this->mlJson($outcome['payload'], $outcome['status']);
    }

    /**
     * Shared job upsert (this controller + JobCreationMlTrait).
     * @return array{status:int, payload:array}
     */
    public function persistJob($post, $actorName)
    {
        $get = function ($key, $default = '') use ($post) {
            return isset($post[$key]) ? $post[$key] : $default;
        };
        $id = (int) $get('id');
        $existing = $id > 0 ? $this->catalog->getJob($id) : NULL;
        $name = trim((string) $get('name'));
        $environment = strtoupper(trim((string) $get('environment')));
        $runtimeKey = trim((string) $get('runtime_key'));
        $mainPy = (string) ($get('main_py') !== '' ? $get('main_py') : $get('inline_code'));
        $entrypoint = trim((string) $get('entrypoint')) ?: 'main.py';

        if ($name === '' || $environment === '') {
            return $this->fail(422, 'Name and environment are required.');
        }
        if (! in_array($environment, $this->mlActiveEnvironments(), TRUE)) {
            return $this->fail(422, 'Unknown environment.');
        }
        $runtime = $this->catalog->getRuntime($runtimeKey);
        if (! $runtime) {
            return $this->fail(422, 'Select a valid ML runtime.');
        }
        if (strlen($mainPy) < 5) {
            return $this->fail(422, 'main.py looks empty.');
        }
        if (strlen($mainPy) > 600000) {
            return $this->fail(422, 'main.py is too large (600 KB max).');
        }
        if (! preg_match('#^[A-Za-z0-9._/-]+\.py$#', $entrypoint) || strpos($entrypoint, '..') !== FALSE) {
            return $this->fail(422, 'Entry point must be a .py file inside the workspace.');
        }
        $dependencyMode = in_array($get('dependency_mode'), array('requirements', 'pyproject', 'none'), TRUE)
            ? $get('dependency_mode') : 'requirements';

        foreach (array('params_json' => 'Params', 'dataset_bindings_json' => 'Dataset bindings', 'env_json' => 'Environment') as $field => $label) {
            $raw = trim((string) $get($field));
            if ($raw !== '' && ! is_array(json_decode($raw, TRUE))) {
                return $this->fail(422, $label.' must be valid JSON.');
            }
        }

        $jobKey = $this->mlSlug($get('job_key') ?: $name);
        if ($jobKey === '') {
            return $this->fail(422, 'Could not derive a job key.');
        }
        if ($this->catalog->jobScopeExists($jobKey, $environment, $id)) {
            return $this->fail(409, 'A job with this key already exists in '.$environment.'.');
        }

        // Classify from main.py.
        $context = array();
        $sampleKey = trim((string) $get('sample_key')) ?: NULL;
        if ($sampleKey && ($sample = $this->catalog->getSampleByKey($sampleKey))) {
            $context['sample_run_type'] = $sample->run_type;
        }
        $introspection = $this->introspector->analyze($mainPy, (string) $get('application_args'), $context);
        $manualType = trim((string) $get('run_type'));
        $runType = in_array($manualType, MlJobIntrospector::TYPES, TRUE) && $manualType !== ''
            ? $manualType : $introspection['run_type'];
        $runTypeSource = ($manualType !== '' && $manualType !== $introspection['run_type']) ? 'manual' : 'auto';

        $experimentId = (int) $get('experiment_id') ?: NULL;
        if (! $experimentId && trim((string) $get('experiment_name')) !== '') {
            $experimentId = $this->catalog->findOrCreateExperiment(
                $this->mlSlug($get('experiment_name')), trim((string) $get('experiment_name')), $environment, $actorName);
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'job_key' => $jobKey,
            'name' => $name,
            'group_name' => trim((string) $get('group_name')) ?: 'General',
            'environment' => $environment,
            'experiment_id' => $experimentId,
            'runtime_key' => $runtimeKey,
            'sample_key' => $sampleKey,
            'description' => trim((string) $get('description')) ?: NULL,
            'source_type' => 'workspace',
            'entrypoint' => $entrypoint,
            'inline_code' => $mainPy,
            'dependency_mode' => $dependencyMode,
            'requirements_txt' => (string) $get('requirements_txt') ?: NULL,
            'pyproject_text' => (string) $get('pyproject_text') ?: NULL,
            'dockerfile' => (string) $get('dockerfile') ?: NULL,
            'application_args' => trim((string) $get('application_args')) ?: NULL,
            'params_json' => trim((string) $get('params_json')) ?: NULL,
            'dataset_bindings_json' => trim((string) $get('dataset_bindings_json')) ?: NULL,
            'env_json' => trim((string) $get('env_json')) ?: NULL,
            'run_type' => $runType,
            'run_type_source' => $runTypeSource,
            'run_type_confidence' => (float) $introspection['confidence'],
            'introspection_json' => json_encode($introspection, JSON_UNESCAPED_SLASHES),
            'cpu_limit' => max(0.25, min(32, (float) $get('cpu_limit') ?: 1.0)),
            'memory_limit_mb' => max(256, min(262144, (int) $get('memory_limit_mb') ?: 2048)),
            'timeout_seconds' => max(120, min(43200, (int) $get('timeout_seconds') ?: 3600)),
            'schedule_cron' => trim((string) $get('schedule_cron')) ?: NULL,
            'is_active' => $get('is_active') === '0' ? 0 : 1,
            'updated_at' => $now,
        );
        if ($id <= 0) {
            $data['created_at'] = $now;
            $data['owner'] = (string) $actorName;
        }
        $savedId = $this->catalog->saveJob($data, $id);
        $job = $this->catalog->getJob($savedId);

        // Materialise the workspace; mark the image stale if the build inputs changed.
        $oldHash = $existing ? (string) $existing->workspace_hash : '';
        $sync = $this->workspace()->sync($job, $runtime);
        $imageState = ($sync['hash'] !== $oldHash && $job->image_state === 'ready') ? 'stale' : $job->image_state;
        $this->catalog->saveJob(array(
            'workspace_hash' => $sync['hash'],
            'image_state' => $imageState,
            'updated_at' => $now,
        ), $savedId);

        $jenkins = array('ok' => FALSE, 'status' => 0, 'job_name' => NULL);
        try {
            $jenkins = $this->mlDeployJenkinsJob($this->catalog->getJob($savedId));
            if (! empty($jenkins['ok']) && $jenkins['job_name'] !== $job->jenkins_job_name) {
                $this->catalog->saveJob(array('jenkins_job_name' => $jenkins['job_name'], 'updated_at' => $now), $savedId);
            }
        } catch (Exception $e) {
            $jenkins['message'] = $e->getMessage();
        }

        return array('status' => 200, 'payload' => array(
            'ok' => TRUE,
            'id' => $savedId,
            'run_type' => $runType,
            'run_type_confidence' => (float) $introspection['confidence'],
            'introspection' => $introspection,
            'image_state' => $imageState,
            'workspace_files' => $sync['files'],
            'jenkins' => $jenkins,
            'message' => ($id > 0 ? 'Job updated.' : 'Job created.')
                .($imageState === 'stale' || $imageState === 'none' ? ' Image will build on the next run.' : '')
                .(empty($jenkins['ok']) ? ' (Jenkins job not deployed: HTTP '.$jenkins['status'].')' : ''),
        ));
    }

    private function fail($status, $message)
    {
        return array('status' => $status, 'payload' => array('ok' => FALSE, 'message' => $message));
    }

    public function delete()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $job = $this->catalog->getJob($id);
        if (! $job) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Job not found.'), 404);
            return;
        }
        foreach ($this->runs->recentRunsForJob($id, 25) as $run) {
            if (! in_array($run->status, MlRunOrchestrator::TERMINAL, TRUE)) {
                $this->orchestrator->cancel($run, (string) $this->name);
            }
        }
        if ($job->jenkins_job_name) {
            try {
                $this->requestJenkins('POST', $this->mlJenkinsJobPath($job->jenkins_job_name).'/doDelete');
            } catch (Exception $e) {
                // best effort
            }
        }
        $this->workspace()->delete($job->job_key);
        $this->catalog->deleteJob($id);
        $this->mlJson(array('ok' => TRUE, 'message' => 'Job and workspace deleted.'));
    }

    // --- editor + image -------------------------------------------------

    /** Sync workspace, ensure OpenVSCode is up, return the folder URL. */
    public function develop()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $job = $this->catalog->getJob((int) $this->input->post('id'));
        if (! $job) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Job not found.'), 404);
            return;
        }
        $ws = $this->workspace();
        if (! $ws->editorEnabled()) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'OpenVSCode is disabled for this deployment.'), 404);
            return;
        }
        $ws->sync($job, $this->catalog->getRuntime($job->runtime_key));
        $state = $ws->editorState(TRUE);
        if (empty($state['available'])) {
            $this->mlJson(array('ok' => FALSE, 'message' => $state['message']), 503);
            return;
        }
        $this->mlJson(array(
            'ok' => TRUE,
            'url' => $ws->editorFolderUrl($job->job_key),
            'ready' => ! empty($state['ready']),
            'starting' => ! empty($state['starting']),
            'message' => $state['message'],
        ));
    }

    /** Read the workspace file list + one file's content. */
    public function workspaceFile($id)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $job = $this->catalog->getJob((int) $id);
        if (! $job) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Job not found.'), 404);
            return;
        }
        $ws = $this->workspace();
        if ($this->input->method(TRUE) === 'POST') {
            $rel = (string) $this->input->post('path');
            if (! $ws->writeFile($job->job_key, $rel, (string) $this->input->post('content'))) {
                $this->mlJson(array('ok' => FALSE, 'message' => 'Could not write that path.'), 422);
                return;
            }
            if ($rel === ($job->entrypoint ?: 'main.py')) {
                $this->catalog->saveJob(array('inline_code' => (string) $this->input->post('content'),
                    'workspace_hash' => $ws->hash($job->job_key), 'updated_at' => date('Y-m-d H:i:s')), (int) $job->id);
            }
            $this->mlJson(array('ok' => TRUE, 'files' => $ws->manifest($job->job_key)));
            return;
        }
        $rel = trim((string) $this->input->get('path'));
        $this->mlJson(array(
            'ok' => TRUE,
            'files' => $ws->manifest($job->job_key),
            'content' => $rel !== '' ? $ws->readFile($job->job_key, $rel) : NULL,
        ));
    }

    public function buildImage()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $job = $this->catalog->getJob((int) $this->input->post('id'));
        if (! $job) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Job not found.'), 404);
            return;
        }
        $runtime = $this->catalog->getRuntime($job->runtime_key);
        if (! $runtime) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'The job runtime is missing.'), 422);
            return;
        }
        if (! $this->orchestrator->engineHealthy()) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'The compute engine is not reachable.'), 502);
            return;
        }
        $result = $this->orchestrator->rebuildImage($job, $runtime);
        $this->mlJson(array(
            'ok' => (bool) $result['ok'],
            'tag' => $result['tag'],
            'log' => substr((string) $result['log'], -8000),
            'message' => $result['ok'] ? 'Image built: '.$result['tag'] : ('Build failed: '.$result['message']),
        ), $result['ok'] ? 200 : 500);
    }

    public function imageStatus($id)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $job = $this->catalog->getJob((int) $id);
        if (! $job) {
            $this->mlJson(array('ok' => FALSE), 404);
            return;
        }
        $this->mlJson(array(
            'ok' => TRUE,
            'image_state' => (string) $job->image_state,
            'image_tag' => (string) $job->image_tag,
            'image_built_at' => (string) $job->image_built_at,
            'log_tail' => substr((string) $job->image_build_log, -6000),
        ));
    }

    public function saveAsSample()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $job = $this->catalog->getJob((int) $this->input->post('id'));
        if (! $job) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Job not found.'), 404);
            return;
        }
        $key = $this->mlSlug(trim((string) $this->input->post('sample_key')) ?: ($job->job_key.'-sample'), 96);
        if ($this->catalog->sampleKeyExists($key, 0)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'A sample with this key already exists.'), 409);
            return;
        }
        $now = date('Y-m-d H:i:s');
        $sampleId = $this->catalog->saveSample(array(
            'sample_key' => $key,
            'name' => trim((string) $this->input->post('name')) ?: ($job->name.' sample'),
            'category' => in_array($this->input->post('category'), array('tabular', 'timeseries', 'nlp', 'vision', 'clustering', 'recommender', 'other'), TRUE) ? $this->input->post('category') : 'tabular',
            'run_type' => $job->run_type,
            'runtime_key' => $job->runtime_key,
            'description' => trim((string) $this->input->post('description')) ?: ('Saved from job '.$job->name),
            'code' => (string) $job->inline_code,
            'params_schema_json' => $job->params_json,
            'dataset_roles_json' => $job->dataset_bindings_json,
            'is_builtin' => 0, 'is_active' => 1, 'sort_order' => 200,
            'created_at' => $now, 'updated_at' => $now, 'owner' => (string) $this->name,
        ), 0);
        $this->mlJson(array('ok' => TRUE, 'id' => $sampleId, 'message' => 'Saved as sample "'.$key.'".'));
    }

    // --- runs ------------------------------------------------------

    public function run()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $job = $this->catalog->getJob((int) $this->input->post('id'));
        if (! $job || ! $job->is_active) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Job not found or inactive.'), 404);
            return;
        }
        $runtime = $this->catalog->getRuntime($job->runtime_key);
        if (! $runtime) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'The job runtime is missing.'), 422);
            return;
        }
        $overrideParams = $this->mlDecodeJson($this->input->post('params_json'), NULL);
        $preview = $this->input->post('preview') === '1';
        $result = $this->orchestrator->start($job, $runtime, array(
            'trigger_source' => $preview ? 'preview' : 'manual',
            'triggered_by' => (string) $this->name,
            'params' => is_array($overrideParams) ? $overrideParams : NULL,
        ));
        $this->mlJson(array(
            'ok' => (bool) $result['ok'],
            'message' => $result['message'],
            'run' => $result['run'] ? $this->runPayload($result['run']) : NULL,
        ), $result['ok'] ? 200 : 502);
    }

    public function status($runId)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $run = $this->runs->getRun((int) $runId);
        if (! $run) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        $run = $this->orchestrator->advance($run);
        $this->mlJson(array(
            'ok' => TRUE,
            'run' => $this->runPayload($run),
            'metrics' => $this->runs->latestMetrics((int) $run->id),
            'stats' => $this->orchestrator->runStats($run),
        ));
    }

    public function logs($runId)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $run = $this->runs->getRun((int) $runId);
        if (! $run) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        $this->mlJson(array(
            'ok' => TRUE,
            'status' => (string) $run->status,
            'terminal' => in_array($run->status, MlRunOrchestrator::TERMINAL, TRUE),
            'logs' => $this->orchestrator->liveLogs($run),
        ));
    }

    public function cancel()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $run = $this->runs->getRun((int) $this->input->post('id'));
        if (! $run) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        $run = $this->orchestrator->cancel($run, (string) $this->name);
        $this->mlJson(array('ok' => TRUE, 'run' => $this->runPayload($run), 'message' => 'Run cancelled.'));
    }

    public function capacity()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $this->mlJson(array('ok' => TRUE) + $this->orchestrator->capacity());
    }

    private function runPayload($run)
    {
        return array(
            'id' => (int) $run->id,
            'run_key' => (string) $run->run_key,
            'status' => (string) $run->status,
            'run_type' => (string) $run->run_type,
            'trigger_source' => (string) $run->trigger_source,
            'exit_code' => $run->exit_code === NULL ? NULL : (int) $run->exit_code,
            'error_message' => (string) $run->error_message,
            'terminal' => in_array($run->status, MlRunOrchestrator::TERMINAL, TRUE),
            'started_at' => (string) $run->started_at,
            'completed_at' => (string) $run->completed_at,
        );
    }
}
