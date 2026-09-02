<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Adds a "Machine Learning job" path to the Create Job screen. The authoring
 * form is rendered by application/views/includes/mlJobAuthoring.php and posts
 * straight to the machine-learning/jobs/* endpoints (MlJobs controller), so
 * there is exactly one job-persist code path. This trait only bootstraps the
 * tab with the option lists it needs.
 *
 * Host controller (JobCreation) extends BaseController and calls isLoggedIn().
 */
trait JobCreationMlTrait
{
    public function mlJobOptions()
    {
        if ($this->role != ROLE_ADMIN && $this->role != ROLE_MANAGER) {
            $this->output->set_status_header(403)->set_content_type('application/json')
                ->set_output(json_encode(array('ok' => FALSE)));
            return;
        }
        $this->load->model('MlCatalog_model', 'mlCatalog');
        $this->load->model('MlDataset_model', 'mlDatasets');

        $environment = trim((string) $this->input->get('environment', TRUE));
        $environment = $this->normalizeJobSeekerEnvironment($environment);
        if ($environment === '' || $environment === '*') {
            $environment = 'ALL';
        }

        $environments = array();
        if ($this->db->table_exists('environment')) {
            foreach ($this->db->select('Environment')->where('IsActive', 1)->get('environment')->result() as $row) {
                $environments[] = $this->normalizeJobSeekerEnvironment($row->Environment);
            }
            $environments = array_values(array_unique($environments));
        }

        $mlEnabled = strtolower((string) (getenv('JOBSEEKER_ML_PLATFORM_ENABLED') ?: 'true')) !== 'false';

        $this->output->set_content_type('application/json')->set_output(json_encode(array(
            'ok' => TRUE,
            'enabled' => $mlEnabled,
            'environments' => $environments,
            'runtimes' => $this->mlCatalog->listRuntimes(TRUE),
            'samples' => $this->mlCatalog->listSamples(TRUE),
            'experiments' => $this->mlCatalog->listExperiments($environment),
            'datasets' => $this->mlDatasets->listDatasets($environment),
            'endpoints' => array(
                'introspect' => base_url('machine-learning/jobs/introspect'),
                'save' => base_url('machine-learning/jobs/save'),
                'run' => base_url('machine-learning/jobs/run'),
                'status' => base_url('machine-learning/jobs/status/'),
                'logs' => base_url('machine-learning/jobs/logs/'),
                'develop' => base_url('machine-learning/jobs/develop'),
                'buildImage' => base_url('machine-learning/jobs/build-image'),
                'imageStatus' => base_url('machine-learning/jobs/image-status/'),
                'pick' => base_url('machine-learning/datasets/pick'),
                'manage' => base_url('machine-learning/jobs'),
            ),
        ), JSON_UNESCAPED_SLASHES));
    }
}
