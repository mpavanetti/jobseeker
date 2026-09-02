<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';
require APPPATH.'/controllers/concerns/MlControllerTrait.php';

/**
 * The customisable ML sample gallery. Built-in samples are synced from
 * repository/ml/samples/ on model load; managers can add their own, edit any,
 * and "save a job as a sample" (see MlJobs::saveAsSample).
 */
class MlSamples extends BaseController
{
    use MlControllerTrait;

    private $categories = array('tabular', 'timeseries', 'nlp', 'vision', 'clustering', 'recommender', 'other');
    private $runTypes = array('train', 'batch_infer', 'evaluate', 'preprocess', 'tune');

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
        $this->load->model('MlCatalog_model', 'catalog');
    }

    public function index()
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $this->global['pageTitle'] = 'Job Seeker : ML Samples';
        $this->mlRenderView('mlSamples', array(
            'samples' => $this->catalog->listSamples(FALSE),
            'runtimes' => $this->catalog->listRuntimes(TRUE),
            'categories' => $this->categories,
            'runTypes' => $this->runTypes,
        ));
    }

    public function listSamples()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE, 'samples' => array()), 403);
            return;
        }
        $this->mlJson(array('ok' => TRUE, 'samples' => $this->catalog->listSamples(TRUE)));
    }

    public function get($id)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $sample = $this->catalog->getSample((int) $id);
        $sample
            ? $this->mlJson(array('ok' => TRUE, 'sample' => $sample))
            : $this->mlJson(array('ok' => FALSE, 'message' => 'Sample not found.'), 404);
    }

    public function save()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $existing = $id > 0 ? $this->catalog->getSample($id) : NULL;
        $name = trim((string) $this->input->post('name', TRUE));
        $code = (string) $this->input->post('code');
        if ($name === '' || strlen($code) < 10) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Name and a non-empty code body are required.'), 422);
            return;
        }
        if (strlen($code) > 400000) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Sample code is too large (400 KB max).'), 422);
            return;
        }
        $category = in_array($this->input->post('category'), $this->categories, TRUE) ? $this->input->post('category') : 'tabular';
        $runType = in_array($this->input->post('run_type'), $this->runTypes, TRUE) ? $this->input->post('run_type') : 'train';
        $runtimeKey = trim((string) $this->input->post('runtime_key', TRUE)) ?: 'ml-cpu';
        if (! $this->catalog->getRuntime($runtimeKey)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Pick a valid runtime.'), 422);
            return;
        }
        $paramsSchema = trim((string) $this->input->post('params_schema_json'));
        if ($paramsSchema !== '' && ! is_array(json_decode($paramsSchema, TRUE))) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Params schema must be a JSON object.'), 422);
            return;
        }
        $datasetRoles = trim((string) $this->input->post('dataset_roles_json'));
        if ($datasetRoles !== '' && ! is_array(json_decode($datasetRoles, TRUE))) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Dataset roles must be a JSON array.'), 422);
            return;
        }
        $sampleKey = $this->mlSlug($this->input->post('sample_key', TRUE) ?: $name, 96);
        if ($sampleKey === '') {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Could not derive a sample key.'), 422);
            return;
        }
        if ($this->catalog->sampleKeyExists($sampleKey, $id)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'A sample with this key already exists.'), 409);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'sample_key' => $sampleKey,
            'name' => $name,
            'category' => $category,
            'run_type' => $runType,
            'runtime_key' => $runtimeKey,
            'description' => trim((string) $this->input->post('description', TRUE)) ?: NULL,
            'entry_point' => $existing && $existing->is_builtin ? $existing->entry_point : NULL,
            'code' => $code,
            'params_schema_json' => $paramsSchema !== '' ? $paramsSchema : NULL,
            'dataset_roles_json' => $datasetRoles !== '' ? $datasetRoles : NULL,
            'tags' => trim((string) $this->input->post('tags', TRUE)) ?: NULL,
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'sort_order' => max(1, min(999, (int) $this->input->post('sort_order') ?: 100)),
            'updated_at' => $now,
        );
        if (! $existing) {
            $data['is_builtin'] = 0;
            $data['created_at'] = $now;
            $data['owner'] = (string) $this->name;
        }
        $savedId = $this->catalog->saveSample($data, $id);
        $this->mlJson(array('ok' => TRUE, 'id' => $savedId, 'message' => $id > 0 ? 'Sample updated.' : 'Sample created.'));
    }

    public function delete()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $sample = $this->catalog->getSample($id);
        if (! $sample) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Sample not found.'), 404);
            return;
        }
        if ($sample->is_builtin) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Built-in samples cannot be deleted; deactivate it instead.'), 409);
            return;
        }
        $this->catalog->deleteSample($id);
        $this->mlJson(array('ok' => TRUE, 'message' => 'Sample deleted.'));
    }
}
