<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class JobExecution extends BaseController
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

        $this->global['pageTitle'] = 'Job Seeker : Job Execution';

     //   $data["GetJobs"] = $fetchObj;
                $resumeBuild = trim((string) $this->security->xss_clean($this->input->get('build')));
                if (! preg_match('/^[1-9][0-9]*$/', $resumeBuild)) {
                    $resumeBuild = '';
                }

                $data = array(
                    'job_creation_dates' => $this->readJobCreationDates(),
                    'resume_job' => trim((string) $this->security->xss_clean($this->input->get('job'))),
                    'resume_build' => $resumeBuild,
                    'resume_environment' => trim((string) $this->security->xss_clean($this->input->get('environment')))
                );
        
                $this->loadViews("jobExecution", $this->global, $data, NULL);
    }

    public function executors()
    {
        if ($this->role != ROLE_ADMIN && $this->role != ROLE_MANAGER) {
            redirect('/dashboard');
            return;
        }

        $this->global['pageTitle'] = 'Job Seeker : Jenkins Executors';
        $this->loadViews("jenkinsExecutors", $this->global, array(), NULL);
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


 

   
    
}

?>
