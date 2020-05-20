<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class jobList extends BaseController
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

        $this->global['pageTitle'] = 'Job Seeker : Job Listing';
        
        $this->loadViews("jobList", $this->global, NULL, NULL);
    }

    public function full()
    {

        $this->global['pageTitle'] = 'Job Seeker : Job Listing';
        
        $this->loadViews("fullJobList", $this->global, NULL, NULL);
    }

     
}

?>