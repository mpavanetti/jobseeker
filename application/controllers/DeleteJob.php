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

    /**
     * Index Page for this controller.
     */
    public function index()
    {
        if($this->isManager() == TRUE )
        {
            $this->loadThis();
        }
        else
        {
            
            $this->global['pageTitle'] = 'Job Seeker : Delete Job';
        
            $this->loadViews("deleteJob", $this->global, NULL, NULL);
        }
    }

    public function deleteRepository($job_name)
    {
        if($this->isManager() == TRUE )
        {
            $this->loadThis();
        }
        else
        {
          $jobName = $this->safePathSegment(rawurldecode($job_name));
          if ($jobName === FALSE) {
            $this->output->set_status_header(400)->set_content_type('application/json')->set_output(json_encode(array('exist' => false, 'error' => 'Invalid job name.')));
            return;
          }

          $deletedSystems = array();
          foreach (array('batch', 'bash', 'talend', 'python') as $system) {
            if ($this->deleteRepositoryPath($system, $jobName)) {
              $deletedSystems[] = $system;
            }
          }

          $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array('exist' => ! empty($deletedSystems), 'systems' => $deletedSystems), JSON_PRETTY_PRINT));

        }
    }

    private function deleteRepositoryPath($system, $jobName)
    {
        $jenkinsHome = $this->global['jenkins_home'];
        $repositoryRoot = $jenkinsHome != '' ? rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository' : FCPATH.'repository';
        $jobsRoot = $repositoryRoot.DIRECTORY_SEPARATOR.$system.DIRECTORY_SEPARATOR.'jobs';
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