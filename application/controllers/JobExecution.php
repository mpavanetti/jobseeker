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

                // Apache Hop jobs can show the canvas of what they run, so the
                // screen needs to know which of the jobs are Hop jobs.
                $hopJobs = array();
                $hopSetting = strtolower(trim((string) getenv('JOBSEEKER_HOP_ENABLED')));
                $hopEnabled = ! in_array($hopSetting, array('0', 'false', 'off', 'no'), TRUE);
                if ($hopEnabled && $this->db->table_exists('hop_project_jobs')) {
                    $this->load->model('Hop_model');
                    $hopJobs = $this->Hop_model->hopJobs();
                }

                $data = array(
                    'hop_jobs' => $hopJobs,
                    'job_creation_dates' => $this->readJobCreationDates(),
                    'resume_job' => trim((string) $this->security->xss_clean($this->input->get('job'))),
                    'resume_build' => $resumeBuild,
					'resume_environment' => $this->jobSeekerIsStandaloneDeployment()
						? $this->jobSeekerStandaloneEnvironment()
						: trim((string) $this->security->xss_clean($this->input->get('environment')))
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
     * Task DAG and the per-task outcome of a run, for the Job Execution task graph.
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
        echo json_encode(array_merge(array('ok' => TRUE, 'job' => $jobName), $this->jobTaskOverview($jobName, $environment, $runKey, $buildNumber)));
    }

    /**
     * Re-run a task graph, or part of one, from the task graph panel.
     *
     * The runtime already knows how to resume a run and how to run a named
     * subset; this is the operator-facing half of that. Both are Jenkins build
     * parameters, so the build is an ordinary build of the same job - it shows
     * in the job's history, honours its environment and notifications, and is
     * visible to anyone watching the job.
     *
     * Every value is validated against what the job actually declares, so the
     * parameters cannot be used to smuggle arbitrary text into a build.
     */
    public function runTasks()
    {
        $this->output->set_content_type('application/json');

        if ($this->role != ROLE_ADMIN && $this->role != ROLE_MANAGER) {
            $this->output->set_status_header(403);
            echo json_encode(array('ok' => FALSE, 'message' => 'Access denied.'));
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->output->set_status_header(405);
            echo json_encode(array('ok' => FALSE, 'message' => 'Method not allowed.'));
            return;
        }

        $jobName = trim((string) $this->input->post('job', TRUE));
        if ($jobName === '' || strlen($jobName) > 400 || ! preg_match('#^[A-Za-z0-9._\-/ ]+$#', $jobName)) {
            $this->output->set_status_header(400);
            echo json_encode(array('ok' => FALSE, 'message' => 'A valid job name is required.'));
            return;
        }

        $environment = trim((string) $this->input->post('environment', TRUE));
        if ($environment === '') {
            $environment = $this->jobSeekerEnvironmentPreference();
        }
        $environment = $this->jobSeekerEffectiveEnvironment($environment);

        $mode = strtolower(trim((string) $this->input->post('mode', TRUE)));
        if (! in_array($mode, array('all', 'resume', 'tasks'), TRUE)) {
            $mode = 'all';
        }

        $this->load->model('JobTask_model', 'jobTaskModel');
        $parameters = array('ENVIRONMENT' => $environment);

        if ($mode === 'resume') {
            $runKey = trim((string) $this->input->post('run', TRUE));
            $known = FALSE;
            foreach ($this->jobTaskModel->recentRuns($jobName, $environment, JobTask_model::MAX_RUNS) as $run) {
                if ($run['runKey'] === $runKey) {
                    $known = TRUE;
                    break;
                }
            }
            if (! $known) {
                $this->output->set_status_header(400);
                echo json_encode(array('ok' => FALSE, 'message' => 'That run does not belong to this job.'));
                return;
            }
            $parameters['JOBSEEKER_DAG_RESUME'] = $runKey;
        }

        if ($mode === 'tasks') {
            $graph = $this->jobTaskModel->graphForJob($jobName, $environment);
            $declared = array();
            foreach ((isset($graph['tasks']) && is_array($graph['tasks'])) ? $graph['tasks'] : array() as $task) {
                if (isset($task['id'])) {
                    $declared[(string) $task['id']] = TRUE;
                }
            }

            $requested = array();
            foreach (explode(',', (string) $this->input->post('tasks', TRUE)) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '' || in_array($candidate, $requested, TRUE)) {
                    continue;
                }
                if (! isset($declared[$candidate])) {
                    $this->output->set_status_header(400);
                    echo json_encode(array('ok' => FALSE, 'message' => 'This job does not declare a task called "'.$candidate.'".'));
                    return;
                }
                $requested[] = $candidate;
            }
            if (empty($requested) || count($requested) > JobTask_model::MAX_TASKS) {
                $this->output->set_status_header(400);
                echo json_encode(array('ok' => FALSE, 'message' => 'Select at least one declared task to run.'));
                return;
            }
            $parameters['JOBSEEKER_DAG_TASKS'] = implode(',', $requested);
        }

        $jobPath = $this->taskRunJobPath($jobName);
        $info = $this->requestJenkins('GET', $jobPath.'/api/json?tree=nextBuildNumber');
        $expectedBuild = NULL;
        if ((int) $info['status'] === 200) {
            $decoded = json_decode((string) $info['body'], TRUE);
            if (isset($decoded['nextBuildNumber'])) {
                $expectedBuild = (int) $decoded['nextBuildNumber'];
            }
        }

        $body = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        $response = $this->requestJenkins('POST', $jobPath.'/buildWithParameters', $body, 'application/x-www-form-urlencoded');
        if (! in_array((int) $response['status'], array(200, 201, 202, 302, 303), TRUE)) {
            $this->output->set_status_header(502);
            echo json_encode(array(
                'ok' => FALSE,
                'message' => 'Jenkins could not queue this run (HTTP '.$response['status'].'). '.
                    'A job created before task runs existed has no JOBSEEKER_DAG_RESUME parameter; save it again to add one.'
            ));
            return;
        }

        echo json_encode(array(
            'ok' => TRUE,
            'job' => $jobName,
            'environment' => $environment,
            'mode' => $mode,
            'expectedBuild' => $expectedBuild,
            'parameters' => array_keys($parameters)
        ));
    }

    private function taskRunJobPath($jobName)
    {
        $segments = array();
        foreach (explode('/', trim((string) $jobName, '/')) as $segment) {
            if ($segment !== '') {
                $segments[] = 'job/'.rawurlencode($segment);
            }
        }
        return implode('/', $segments);
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


 

   
    
}

?>
