<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';

/**
 * Catalogue of Miniconda-based ML runtime images. Rows here populate the runtime
 * dropdown on the ML Jobs screen; the images themselves are built by
 * scripts/build-compute-runtimes.sh.
 */
class MlRuntimes extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('MlCompute_model', 'ml');
        $this->isLoggedIn();
        if (empty($this->global['compute_enabled'])) {
            show_404();
        }
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

    private function normalizeKey($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim(substr($value, 0, 64), '-');
    }

    public function index()
    {
        if (! $this->canManage()) {
            $this->loadThis();
            return;
        }
        $data = array('runtimes' => $this->ml->listRuntimes(FALSE));
        $this->global['pageTitle'] = 'Job Seeker : ML Runtimes';
        $this->loadViews('mlRuntimes', $this->global, $data, NULL);
    }

    public function listRuntimes()
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE, 'runtimes' => array()), 403);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE, 'runtimes' => $this->ml->listRuntimes(FALSE)));
    }

    public function get($id)
    {
        if (! $this->canManage()) {
            $this->jsonResponse(array('ok' => FALSE), 403);
            return;
        }
        $runtime = $this->ml->getRuntimeById((int) $id);
        if (! $runtime) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Runtime not found.'), 404);
            return;
        }
        $this->jsonResponse(array('ok' => TRUE, 'runtime' => $runtime));
    }

    public function save()
    {
        if (! $this->requireManagerPost()) {
            return;
        }

        $id = (int) $this->input->post('id');
        $displayName = trim((string) $this->input->post('display_name', TRUE));
        $repository = trim((string) $this->input->post('image_repository', TRUE));
        $tag = trim((string) $this->input->post('image_tag', TRUE));

        if ($displayName === '' || $repository === '' || $tag === '') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Display name, image repository and tag are required.'), 422);
            return;
        }
        if (! preg_match('#^[a-z0-9]([a-z0-9._/-]*[a-z0-9])?$#', $repository) || ! preg_match('#^[A-Za-z0-9._-]+$#', $tag)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Image repository or tag has invalid characters.'), 422);
            return;
        }

        $runtimeKey = $this->normalizeKey($this->input->post('runtime_key', TRUE) ?: $displayName);
        if ($runtimeKey === '') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Could not derive a runtime key.'), 422);
            return;
        }
        if ($this->ml->runtimeKeyExists($runtimeKey, $id)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'A runtime with this key already exists.'), 409);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'runtime_key' => $runtimeKey,
            'display_name' => $displayName,
            'image_repository' => $repository,
            'image_tag' => $tag,
            'base_image' => trim((string) $this->input->post('base_image', TRUE)) ?: 'continuumio/miniconda3',
            'conda_based' => $this->input->post('conda_based') === '0' ? 0 : 1,
            'library_summary' => trim((string) $this->input->post('library_summary', TRUE)) ?: NULL,
            'description' => trim((string) $this->input->post('description', TRUE)) ?: NULL,
            'is_default' => $this->input->post('is_default') ? 1 : 0,
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'sort_order' => max(1, min(999, (int) $this->input->post('sort_order') ?: 100)),
            'updated_at' => $now,
        );
        if ($id <= 0) {
            $data['created_at'] = $now;
        }

        $savedId = $this->ml->saveRuntime($data, $id);
        $this->jsonResponse(array('ok' => TRUE, 'id' => $savedId, 'message' => $id > 0 ? 'Runtime updated.' : 'Runtime created.'));
    }

    public function delete()
    {
        if (! $this->requireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $runtime = $this->ml->getRuntimeById($id);
        if (! $runtime) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Runtime not found.'), 404);
            return;
        }
        if ($this->ml->runtimeInUse($runtime->runtime_key)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Jobs still reference this runtime.'), 409);
            return;
        }
        $this->ml->deleteRuntime($id);
        $this->jsonResponse(array('ok' => TRUE, 'message' => 'Runtime deleted.'));
    }
}
