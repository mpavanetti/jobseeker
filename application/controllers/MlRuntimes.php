<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';
require APPPATH.'/controllers/concerns/MlControllerTrait.php';

/**
 * Catalogue of ML runtime images. Rows feed the runtime dropdown on the ML Jobs
 * screen; the images themselves are built by scripts/build-ml-runtimes.sh.
 */
class MlRuntimes extends BaseController
{
    use MlControllerTrait;

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
        $this->global['pageTitle'] = 'Job Seeker : ML Runtimes';
        $this->mlRenderView('mlRuntimes', array(
            'runtimes' => $this->catalog->listRuntimes(FALSE),
        ));
    }

    public function listRuntimes()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE, 'runtimes' => array()), 403);
            return;
        }
        $this->mlJson(array('ok' => TRUE, 'runtimes' => $this->catalog->listRuntimes(FALSE)));
    }

    public function get($id)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $runtime = $this->catalog->getRuntimeById((int) $id);
        $runtime
            ? $this->mlJson(array('ok' => TRUE, 'runtime' => $runtime))
            : $this->mlJson(array('ok' => FALSE, 'message' => 'Runtime not found.'), 404);
    }

    public function save()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $displayName = trim((string) $this->input->post('display_name', TRUE));
        $repository = trim((string) $this->input->post('image_repository', TRUE));
        $tag = trim((string) $this->input->post('image_tag', TRUE));

        if ($displayName === '' || $repository === '' || $tag === '') {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Display name, image repository and tag are required.'), 422);
            return;
        }
        if (! preg_match('#^[a-z0-9]([a-z0-9._/-]*[a-z0-9])?$#', $repository) || ! preg_match('#^[A-Za-z0-9._-]+$#', $tag)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Image repository or tag has invalid characters.'), 422);
            return;
        }
        $runtimeKey = $this->mlSlug($this->input->post('runtime_key', TRUE) ?: $displayName, 64);
        if ($runtimeKey === '') {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Could not derive a runtime key.'), 422);
            return;
        }
        if ($this->catalog->runtimeKeyExists($runtimeKey, $id)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'A runtime with this key already exists.'), 409);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'runtime_key' => $runtimeKey,
            'display_name' => $displayName,
            'kind' => in_array($this->input->post('kind'), array('cpu', 'gpu'), TRUE) ? $this->input->post('kind') : 'cpu',
            'image_repository' => $repository,
            'image_tag' => $tag,
            'base_image' => trim((string) $this->input->post('base_image', TRUE)) ?: 'continuumio/miniconda3',
            'library_summary' => trim((string) $this->input->post('library_summary', TRUE)) ?: NULL,
            'description' => trim((string) $this->input->post('description', TRUE)) ?: NULL,
            'default_cpu_limit' => max(0.25, min(32, (float) $this->input->post('default_cpu_limit') ?: 1.0)),
            'default_memory_mb' => max(256, min(262144, (int) $this->input->post('default_memory_mb') ?: 2048)),
            'is_default' => $this->input->post('is_default') ? 1 : 0,
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'sort_order' => max(1, min(999, (int) $this->input->post('sort_order') ?: 100)),
            'updated_at' => $now,
        );
        if ($id <= 0) {
            $data['created_at'] = $now;
        }
        if ($data['is_default']) {
            $this->db->update('ml_runtime', array('is_default' => 0));
        }
        $savedId = $this->catalog->saveRuntime($data, $id);
        $this->mlJson(array('ok' => TRUE, 'id' => $savedId, 'message' => $id > 0 ? 'Runtime updated.' : 'Runtime created.'));
    }

    public function delete()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $runtime = $this->catalog->getRuntimeById($id);
        if (! $runtime) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Runtime not found.'), 404);
            return;
        }
        if ($this->catalog->runtimeInUse($runtime->runtime_key)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Jobs still reference this runtime.'), 409);
            return;
        }
        $this->catalog->deleteRuntime($id);
        $this->mlJson(array('ok' => TRUE, 'message' => 'Runtime deleted.'));
    }
}
