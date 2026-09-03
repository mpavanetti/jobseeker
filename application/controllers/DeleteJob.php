<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class DeleteJob extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();   
        $this->load->helper('url');
    }

    private function canManageJobs()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function requestedEnvironment()
    {
		if ($this->jobSeekerIsStandaloneDeployment()) {
			return $this->jobSeekerStandaloneEnvironment();
		}
        $environment = trim((string) $this->input->post('environment'));
        return $environment !== '' ? $environment : $this->jobSeekerEnvironmentPreference();
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {
        if(! $this->canManageJobs())
        {
            $this->loadThis();
        }
        else
        {
            
            $this->global['pageTitle'] = 'Job Seeker : Delete Job';
        
            $this->loadViews("deleteJob", $this->global, NULL, NULL);
        }
    }

    public function deleteRepository($job_name = NULL)
    {
        if (! $this->canManageJobs()) {
            $this->jsonDeleteResponse(array('exist' => false, 'error' => 'Access denied.'), 403);
            return;
        }

        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonDeleteResponse(array('exist' => false, 'error' => 'Delete requests must use POST.'), 405);
            return;
        }

        $jobName = $this->normaliseJobName($job_name !== NULL ? $job_name : $this->input->post('job_name'));
        if ($jobName === FALSE) {
            $this->jsonDeleteResponse(array('exist' => false, 'error' => 'Invalid job name.'), 400);
            return;
        }

        $scope = $this->jobMatchesRequestedEnvironment($jobName, $this->requestedEnvironment());
        if (! $scope['ok']) {
            $this->jsonDeleteResponse(array('exist' => false, 'error' => $scope['message']), $scope['status']);
            return;
        }

        $deletedSystems = $this->deleteRepositoryForJob($jobName);
        $this->jsonDeleteResponse(array('job' => $jobName, 'exist' => ! empty($deletedSystems), 'systems' => $deletedSystems));
    }

    public function deleteRepositories()
    {
        if (! $this->canManageJobs()) {
            $this->jsonDeleteResponse(array('deleted' => 0, 'results' => array(), 'error' => 'Access denied.'), 403);
            return;
        }

        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonDeleteResponse(array('deleted' => 0, 'results' => array(), 'error' => 'Delete requests must use POST.'), 405);
            return;
        }

        $rawJobs = $this->input->post('jobs');
        if ($rawJobs === NULL) {
            $payload = json_decode($this->input->raw_input_stream);
            if (is_object($payload) && isset($payload->jobs)) {
                $rawJobs = $payload->jobs;
            }
        }

        if (! is_array($rawJobs)) {
            $rawJobs = $rawJobs === NULL ? array() : explode(',', (string) $rawJobs);
        }

        $seenJobs = array();
        $results = array();
        $deletedCount = 0;

        foreach ($rawJobs as $rawJob) {
            $jobName = $this->normaliseJobName($rawJob);

            if ($jobName === FALSE) {
                $results[] = array('job' => (string) $rawJob, 'exist' => false, 'systems' => array(), 'error' => 'Invalid job name.');
                continue;
            }

            if (isset($seenJobs[$jobName])) {
                continue;
            }

            $seenJobs[$jobName] = true;
            $scope = $this->jobMatchesRequestedEnvironment($jobName, $this->requestedEnvironment());
            if (! $scope['ok']) {
                $results[] = array('job' => $jobName, 'exist' => false, 'systems' => array(), 'error' => $scope['message']);
                continue;
            }
            $deletedSystems = $this->deleteRepositoryForJob($jobName);
            if (! empty($deletedSystems)) {
                $deletedCount++;
            }

            $results[] = array('job' => $jobName, 'exist' => ! empty($deletedSystems), 'systems' => $deletedSystems);
        }

        if (empty($results)) {
            $this->jsonDeleteResponse(array('deleted' => 0, 'results' => array(), 'error' => 'No jobs were selected.'), 400);
            return;
        }

        $this->jsonDeleteResponse(array('deleted' => $deletedCount, 'requested' => count($seenJobs), 'results' => $results));
    }

    /**
     * Delete Jenkins jobs through JobSeeker so the selected environment is
     * re-checked server-side immediately before a destructive operation.
     */
    public function deleteJobs()
    {
        if (! $this->canManageJobs()) {
            $this->jsonDeleteResponse(array('deleted' => 0, 'results' => array(), 'error' => 'Access denied.'), 403);
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonDeleteResponse(array('deleted' => 0, 'results' => array(), 'error' => 'Delete requests must use POST.'), 405);
            return;
        }

        $rawJobs = $this->input->post('jobs');
        $rawJobs = is_array($rawJobs) ? $rawJobs : explode(',', (string) $rawJobs);
        $deleteRepositories = $this->input->post('delete_repositories') === '1';
        $environment = $this->requestedEnvironment();
        $results = array();
        $seen = array();
        $deleted = 0;

        foreach ($rawJobs as $rawJob) {
            $jobName = $this->normaliseJobName($rawJob);
            if ($jobName === FALSE || isset($seen[$jobName])) {
                if ($jobName === FALSE) {
                    $results[] = array('job' => (string) $rawJob, 'deleted' => FALSE, 'systems' => array(), 'error' => 'Invalid job name.');
                }
                continue;
            }
            $seen[$jobName] = TRUE;
            $scope = $this->jobMatchesRequestedEnvironment($jobName, $environment);
            if (! $scope['ok']) {
                $results[] = array('job' => $jobName, 'deleted' => FALSE, 'systems' => array(), 'error' => $scope['message']);
                continue;
            }

            $response = $this->requestJenkins('POST', $this->jenkinsJobPath($jobName).'/doDelete');
            $ok = in_array((int) $response['status'], array(200, 201, 302, 303), TRUE);
            $systems = $ok && $deleteRepositories ? $this->deleteRepositoryForJob($jobName) : array();
            if ($ok) {
                $this->removeJobCreationDate($jobName);
                $deleted++;
            }
            $results[] = array(
                'job' => $jobName,
                'deleted' => $ok,
                'systems' => $systems,
                'environment' => $scope['environment'],
                'error' => $ok ? '' : 'Jenkins rejected the delete request (HTTP '.(int) $response['status'].').'
            );
        }

        $status = empty($results) ? 400 : 200;
        $this->jsonDeleteResponse(array('deleted' => $deleted, 'requested' => count($seen), 'results' => $results), $status);
    }

    private function jenkinsJobPath($jobName)
    {
        return implode('/', array_map(function($segment) {
            return 'job/'.rawurlencode($segment);
        }, explode('/', trim((string) $jobName, '/'))));
    }

    private function jobMatchesRequestedEnvironment($jobName, $requestedEnvironment)
    {
        $requested = $this->normalizeJobSeekerEnvironment($requestedEnvironment);
        if ($requested === '' || $requested === '*' || $requested === 'ALL') {
            return array('ok' => TRUE, 'status' => 200, 'environment' => $this->jenkinsEnvironmentFromJobConfig($jobName), 'message' => '');
        }

        $actual = $this->normalizeJobSeekerEnvironment($this->jenkinsEnvironmentFromJobConfig($jobName));
        if ($actual === '') {
            return array('ok' => FALSE, 'status' => 409, 'environment' => '', 'message' => 'The Jenkins job environment could not be verified in the backend. Reload the list before deleting.');
        }
        if ($actual !== $requested) {
            return array('ok' => FALSE, 'status' => 409, 'environment' => $actual, 'message' => 'The job belongs to '.$actual.', not the selected '.$requested.' environment.');
        }

        return array('ok' => TRUE, 'status' => 200, 'environment' => $actual, 'message' => '');
    }

    private function normaliseJobName($jobName)
    {
        if (is_array($jobName) || is_object($jobName)) {
            return FALSE;
        }

        $safePath = $this->safeRelativePath(rawurldecode((string) $jobName));
        return $safePath === FALSE ? FALSE : str_replace(DIRECTORY_SEPARATOR, '/', $safePath);
    }

    private function deleteRepositoryForJob($jobName)
    {
        $deletedSystems = array();
        $locations = array(
            array('system' => 'batch', 'relative_root' => 'batch/jobs'),
            array('system' => 'bash', 'relative_root' => 'bash/jobs'),
            array('system' => 'talend', 'relative_root' => 'talend/jobs'),
            array('system' => 'python', 'relative_root' => 'python/jobs'),
            array('system' => 'python-inline', 'relative_root' => 'python/inline')
        );

        foreach ($locations as $location) {
            if ($this->deleteRepositoryPath($location['relative_root'], $jobName)) {
                $deletedSystems[] = $location['system'];
            }
        }

        return $deletedSystems;
    }

    private function removeJobCreationDate($jobName)
    {
        $path = APPPATH.'cache/job_creation_dates.json';
        $handle = fopen($path, 'c+');
        if ($handle === FALSE || ! flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return FALSE;
        }

        rewind($handle);
        $dates = json_decode(stream_get_contents($handle), TRUE);
        if (! is_array($dates) || ! array_key_exists($jobName, $dates)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return TRUE;
        }

        unset($dates[$jobName]);
        $payload = json_encode($dates, JSON_PRETTY_PRINT);
        rewind($handle);
        $written = $payload !== FALSE && ftruncate($handle, 0) && fwrite($handle, $payload) !== FALSE;
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $written;
    }

    private function jsonDeleteResponse($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_PRETTY_PRINT));
    }

    private function deleteRepositoryPath($relativeRoot, $jobName)
    {
        $jenkinsHome = $this->global['jenkins_home'];
        $repositoryRoot = $jenkinsHome != '' ? rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository' : FCPATH.'repository';
        $jobsRoot = $repositoryRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeRoot);
        $targetPath = $jobsRoot.DIRECTORY_SEPARATOR.$jobName;

        if (! $this->pathWithinBase($targetPath, $jobsRoot) || ! is_dir($targetPath)) {
            return false;
        }

        return $this->removeDirectory($targetPath);
    }

    private function removeDirectory($path)
    {
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.DIRECTORY_SEPARATOR.$item;
            if (is_dir($itemPath) && ! is_link($itemPath)) {
                if (! $this->removeDirectory($itemPath)) {
                    return false;
                }
            } elseif (! unlink($itemPath)) {
                return false;
            }
        }

        return rmdir($path);
    }

   
    
}

?>
