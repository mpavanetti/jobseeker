<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class Dashboard extends BaseController
{
    /** Seconds to serve a cached /dashboard/overview payload before recomputing. */
    const OVERVIEW_CACHE_TTL = 20;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url', 'form');
        $this->load->model('Dashboard_model', 'model');
        $this->load->library('session');
        $this->isLoggedIn();
    }

    private function selectedEnvironmentFilter()
    {
		if ($this->jobSeekerIsStandaloneDeployment()) {
			return $this->jobSeekerStandaloneEnvironment();
		}
        $environment = trim((string) $this->security->xss_clean($this->input->get('environment')));
        if ($environment === '') {
            $environment = $this->jobSeekerEnvironmentPreference();
        }

        if ($environment === '' || $environment === '*' || strtolower($environment) === 'all') {
            return 'all';
        }

        if ($environment === '__UNKNOWN__' || strtolower($environment) === 'unknown') {
            return '__UNKNOWN__';
        }

        return strtoupper($environment);
    }

    public function index()
    {
        $this->global['pageTitle'] = 'Job Seeker : Dashboard';
        $selectedEnvironment = $this->selectedEnvironmentFilter();
        $this->global['selectedEnvironment'] = $selectedEnvironment;

        $this->loadViews('dashboard', $this->global, array(
            'selectedEnvironment' => $selectedEnvironment
        ), NULL);
    }

    public function overview()
    {
        $environment = $this->selectedEnvironmentFilter();
        $fresh = in_array((string) $this->input->get('fresh'), array('1', 'true', 'yes'), TRUE);

        $this->load->driver('cache', array('adapter' => 'file'));
        $cacheKey = 'dashboard_overview_' . preg_replace('/[^A-Za-z0-9_]/', '_', $environment);

        $data = $fresh ? FALSE : $this->cache->get($cacheKey);
        $fromCache = ($data !== FALSE);

        if (! $fromCache) {
            $data = $this->model->overview($environment);
            $this->cache->save($cacheKey, $data, self::OVERVIEW_CACHE_TTL);
        }

        $payload = array(
            'ok' => TRUE,
            'fromCache' => $fromCache,
            'cacheTtl' => self::OVERVIEW_CACHE_TTL,
            'data' => $data
        );

        $this->output
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload));
    }
}
