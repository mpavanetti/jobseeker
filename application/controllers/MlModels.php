<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';
require APPPATH.'/controllers/concerns/MlControllerTrait.php';

/**
 * Model registry: named models, their immutable versions, metrics snapshots,
 * signatures and lifecycle stage (none/staging/production/archived) with an
 * audited promotion / rollback flow.
 */
class MlModels extends BaseController
{
    use MlControllerTrait;

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
        $this->load->model('MlRegistry_model', 'registry');
        $this->load->model('MlRun_model', 'runs');
        $this->load->model('MlDataset_model', 'datasets');
        $this->load->model('MlCatalog_model', 'catalog');
    }

    public function index()
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $environment = $this->mlSelectedEnvironment();
        $models = $this->registry->listModels($environment);
        $withVersions = array();
        foreach ($models as $model) {
            $withVersions[] = array(
                'model' => $model,
                'versions' => $this->registry->listVersions((int) $model->id, 6),
                'production' => $this->registry->versionInStage((int) $model->id, 'production'),
            );
        }
        $this->global['pageTitle'] = 'Job Seeker : ML Models';
        $this->mlRenderView('mlModels', array(
            'selectedEnvironment' => $environment,
            'environments' => $this->mlActiveEnvironments(),
            'models' => $withVersions,
            'stageCounts' => $this->registry->countByStage($environment),
        ));
    }

    public function version($id)
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $version = $this->registry->getVersion((int) $id);
        if (! $version) {
            show_404();
            return;
        }
        $model = $this->registry->getModel((int) $version->model_id);
        $run = $version->run_id ? $this->runs->getRun((int) $version->run_id) : NULL;
        $this->global['pageTitle'] = 'Job Seeker : '.$model->name.' v'.$version->version;
        $this->mlRenderView('mlModelVersion', array(
            'model' => $model,
            'version' => $version,
            'allVersions' => $this->registry->listVersions((int) $version->model_id, 100),
            'metrics' => $this->mlDecodeJson($version->metrics_json),
            'params' => $this->mlDecodeJson($version->params_json),
            'signature' => $this->mlDecodeJson($version->signature_json),
            'stageHistory' => $this->registry->stageHistory((int) $version->id),
            'run' => $run,
            'runArtifacts' => $run ? $this->runs->runArtifacts((int) $run->id) : array(),
            'trainingDatasetVersion' => $version->training_dataset_version_id
                ? $this->datasets->getVersion((int) $version->training_dataset_version_id) : NULL,
            'lineage' => $this->runs->neighbourhood('model_version', (int) $version->id),
        ));
    }

    public function saveModel()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name', TRUE));
        $environment = strtoupper(trim((string) $this->input->post('environment'))) ?: 'ALL';
        if ($name === '') {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Model name is required.'), 422);
            return;
        }
        $key = $this->mlSlug($this->input->post('model_key', TRUE) ?: $name);
        if ($this->registry->modelScopeExists($key, $environment, $id)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'A model with this key already exists in '.$environment.'.'), 409);
            return;
        }
        $now = date('Y-m-d H:i:s');
        $data = array(
            'model_key' => $key,
            'name' => $name,
            'environment' => $environment,
            'task' => in_array($this->input->post('task'), array('classification', 'regression', 'forecasting', 'clustering', 'ranking', 'nlp', 'vision', 'other'), TRUE) ? $this->input->post('task') : 'classification',
            'description' => trim((string) $this->input->post('description', TRUE)) ?: NULL,
            'primary_metric' => trim((string) $this->input->post('primary_metric', TRUE)) ?: NULL,
            'metric_goal' => $this->input->post('metric_goal') === 'min' ? 'min' : 'max',
            'tags' => trim((string) $this->input->post('tags', TRUE)) ?: NULL,
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'updated_at' => $now,
        );
        if ($id <= 0) {
            $data['latest_version'] = 0;
            $data['created_at'] = $now;
            $data['owner'] = (string) $this->name;
        }
        $savedId = $this->registry->saveModel($data, $id);
        $this->mlJson(array('ok' => TRUE, 'id' => $savedId, 'message' => $id > 0 ? 'Model updated.' : 'Model created.'));
    }

    public function deleteModel()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        if (! $this->registry->getModel($id)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Model not found.'), 404);
            return;
        }
        $this->registry->deleteModel($id);
        $this->mlJson(array('ok' => TRUE, 'message' => 'Model and its versions deleted.'));
    }

    public function transition()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $versionId = (int) $this->input->post('version_id');
        $stage = (string) $this->input->post('stage');
        if (! in_array($stage, MlRegistry_model::STAGES, TRUE)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Unknown stage.'), 422);
            return;
        }
        if (! $this->registry->getVersion($versionId)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Model version not found.'), 404);
            return;
        }
        $ok = $this->registry->transitionStage($versionId, $stage, (string) $this->name,
            trim((string) $this->input->post('reason')));
        $this->mlJson(array('ok' => (bool) $ok, 'message' => $ok ? 'Moved to '.$stage.'.' : 'Could not move the version.'));
    }

    public function updateVersionNotes()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $versionId = (int) $this->input->post('version_id');
        if (! $this->registry->getVersion($versionId)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Version not found.'), 404);
            return;
        }
        $this->registry->updateVersion($versionId, array('notes' => substr((string) $this->input->post('notes'), 0, 2000)));
        $this->mlJson(array('ok' => TRUE, 'message' => 'Notes saved.'));
    }
}
