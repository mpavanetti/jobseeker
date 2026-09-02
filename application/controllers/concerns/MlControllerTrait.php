<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Shared helpers for the Machine Learning platform's session-facing controllers.
 * The host controller must extend BaseController (for $this->role, $this->name,
 * loadViews(), normalizeJobSeekerEnvironment(), jobSeekerEnvironmentPreference()).
 */
trait MlControllerTrait
{
    protected function mlCanManage()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    protected function mlJson($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store, max-age=0')
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    protected function mlRequireManagerPost()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return FALSE;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
            return FALSE;
        }
        return TRUE;
    }

    protected function mlActiveEnvironments()
    {
        if (! $this->db->table_exists('environment')) {
            return array();
        }
        $rows = $this->db->select('Environment')->from('environment')->where('IsActive', 1)->get()->result();
        return array_values(array_unique(array_map(function ($row) {
            return $this->normalizeJobSeekerEnvironment($row->Environment);
        }, $rows)));
    }

    protected function mlSelectedEnvironment()
    {
        $value = trim((string) $this->input->get('environment', TRUE));
        if ($value === '') {
            $value = $this->jobSeekerEnvironmentPreference();
        }
        $environment = $this->normalizeJobSeekerEnvironment($value);
        if ($environment === '' || $environment === '*' || $environment === 'ALL') {
            return 'ALL';
        }
        return in_array($environment, $this->mlActiveEnvironments(), TRUE) ? $environment : 'ALL';
    }

    protected function mlSlug($value, $limit = 128)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim(substr($value, 0, $limit), '-');
    }

    protected function mlDecodeJson($raw, $default = array())
    {
        $decoded = json_decode((string) $raw, TRUE);
        return is_array($decoded) ? $decoded : $default;
    }

    protected function mlRenderView($view, $data)
    {
        $this->load->helper('url');
        $this->global['mlSection'] = TRUE;
        $this->loadViews($view, $this->global, $data, NULL);
    }
}
