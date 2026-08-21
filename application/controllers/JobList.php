<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class JobList extends BaseController
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

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : Job Listing';
        $data['role'] = $this->isManager();
        $data['canManageJobs'] = $this->canManageJobs();
        
        $this->loadViews("jobList", $this->global, $data, NULL);
    }

    public function full()
    {

        $this->global['pageTitle'] = 'Job Seeker : Job Listing';
        $data['role'] = $this->isManager();
        $data['canManageJobs'] = $this->canManageJobs();
        
        $this->loadViews("fullJobList", $this->global, $data, NULL);
    }

     
}

?>