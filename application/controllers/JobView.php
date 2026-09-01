<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class JobView extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
     //   $this->load->model('files_model');
        $this->isLoggedIn();   
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : View Job';
                $data = array('job_creation_dates' => $this->readJobCreationDates());
        
                $this->loadViews("jobView", $this->global, $data, NULL);
    }

        private function jobCreationDatesPath() {
            return APPPATH . 'cache/job_creation_dates.json';
        }

        private function readJobCreationDates() {
            $path = $this->jobCreationDatesPath();

            if (! is_readable($path)) {
                return array();
            }

            $json = file_get_contents($path);
            $dates = json_decode($json, TRUE);

            if (! is_array($dates)) {
                return array();
            }

            $cleanDates = array();
            foreach ($dates as $jobName => $createdAt) {
                if (is_string($jobName) && is_string($createdAt) && $jobName !== '' && $createdAt !== '') {
                    $cleanDates[$jobName] = $createdAt;
                }
            }

            return $cleanDates;
        }

    public function dependencies()
    {
        $this->output->set_content_type('application/json');
        $jobName = trim((string) $this->input->get('job', TRUE));
        $environment = trim((string) $this->input->get('environment', TRUE));
        if ($environment === '') {
            $environment = $this->jobSeekerEnvironmentPreference();
        }
        if ($jobName === '' || strlen($jobName) > 400) {
            $this->output->set_status_header(400);
            echo json_encode(array('ok' => FALSE, 'message' => 'A job name is required.'));
            return;
        }
        echo json_encode(array_merge(array('ok' => TRUE, 'job' => $jobName), $this->jobDependencyMap($jobName, $environment)));
    }






}

?>