<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

/**
 * Class : Login (LoginController)
 * Login class to control to authenticate user credentials and starts user's session.
 * @author : Kishor Mali
 * @version : 1.1
 * @since : 15 November 2016
 */
class GenericSettings extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('GenericSettings_model','model');
        $this->load->library('session');
        $this->isLoggedIn();   
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Talend Job Seeker : Generic Settings';

        $data["settings"] = $this->model->listSettings();
        $data["role"] = $this->isManager();
        
        $this->loadViews("GenericSettings", $this->global, $data, NULL);
    }

    
}

?>