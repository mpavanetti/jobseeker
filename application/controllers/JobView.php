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
                $hopJobs = array();
                $hopSetting = strtolower(trim((string) getenv('JOBSEEKER_HOP_ENABLED')));
                $hopEnabled = ! in_array($hopSetting, array('0', 'false', 'off', 'no'), TRUE);
                if ($hopEnabled && $this->db->table_exists('hop_project_jobs')) {
                    $this->load->model('Hop_model');
                    $hopJobs = $this->Hop_model->hopJobs();
                }
                $data = array(
                    'job_creation_dates' => $this->readJobCreationDates(),
                    'hop_jobs' => $hopJobs
                );
        
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
                // json_decode() with assoc=TRUE hands back a PHP array, and a PHP array
            // key that looks like an integer becomes one. A job named "1" therefore
            // arrives here as int 1, failed is_string(), and was dropped - which is
            // why numerically named jobs reported "Created: Not tracked" even though
            // their date had been recorded. Only the value needs checking.
            $jobName = (string) $jobName;
            if (is_string($createdAt) && $jobName !== '' && $createdAt !== '') {
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
		$environment = $this->jobSeekerEffectiveEnvironment($environment);
        if ($jobName === '' || strlen($jobName) > 400) {
            $this->output->set_status_header(400);
            echo json_encode(array('ok' => FALSE, 'message' => 'A job name is required.'));
            return;
        }
        echo json_encode(array_merge(array('ok' => TRUE, 'job' => $jobName), $this->jobDependencyMap($jobName, $environment)));
    }

    /**
     * Task DAG and the per-task outcome of a run, for the Job View task graph.
     *
     * The page polls this on a timer while a build is running, so it stays a
     * bounded three-query read: the declared graph, the recent run list, and the
     * latest attempt of every task in the run being shown.
     */
    public function tasks()
    {
        $this->output->set_content_type('application/json');
        $jobName = trim((string) $this->input->get('job', TRUE));
        $environment = trim((string) $this->input->get('environment', TRUE));
        $runKey = trim((string) $this->input->get('run', TRUE));
        if ($environment === '') {
            $environment = $this->jobSeekerEnvironmentPreference();
        }
        $environment = $this->jobSeekerEffectiveEnvironment($environment);
        if ($jobName === '' || strlen($jobName) > 400) {
            $this->output->set_status_header(400);
            echo json_encode(array('ok' => FALSE, 'message' => 'A job name is required.'));
            return;
        }
        if (strlen($runKey) > 64) {
            $runKey = '';
        }
        $buildNumber = (int) $this->input->get('build', TRUE);
        $queueId = (int) $this->input->get('queue', TRUE);
        if ($queueId > 0) {
            $queueResponse = $this->requestJenkins(
                'GET',
                'queue/item/'.rawurlencode((string) $queueId).'/api/json?tree=cancelled,executable[number]'
            );
            if ((int) $queueResponse['status'] === 200) {
                $queue = json_decode((string) $queueResponse['body'], TRUE);
                if (isset($queue['executable']['number'])) {
                    $buildNumber = (int) $queue['executable']['number'];
                }
            }
        }
        echo json_encode(array_merge(
            array('ok' => TRUE, 'job' => $jobName, 'queueId' => $queueId ?: NULL),
            $this->jobTaskOverview($jobName, $environment, $runKey, $buildNumber)
        ));
    }






}

?>
