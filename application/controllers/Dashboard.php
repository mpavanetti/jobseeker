<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class Dashboard extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url', 'form');
        $this->load->model('Dashboard_model', 'model');
        $this->load->library('session');
        $this->isLoggedIn();
        date_default_timezone_set('America/Sao_Paulo');
    }

    private function selectedEnvironmentFilter()
    {
        $environment = trim((string) $this->security->xss_clean($this->input->get('environment')));

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
        $payload = array(
            'ok' => TRUE,
            'data' => $this->model->overview($this->selectedEnvironmentFilter())
        );

        $this->output
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload));
    }
}
