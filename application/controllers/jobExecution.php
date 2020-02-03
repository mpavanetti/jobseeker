<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

/**
 * Class : Login (LoginController)
 * Login class to control to authenticate user credentials and starts user's session.
 * @author : Matheus Pavanetti
 * @version : 1.0
 * @since : 27 january 2020
 */
class jobExecution extends BaseController
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

        $this->global['pageTitle'] = 'Talend Job Seeker : Job Execution';

     //   $data["GetJobs"] = $fetchObj;
        
        $this->loadViews("jobExecution", $this->global, NULL, NULL);
    }


 

   
    
}

?>