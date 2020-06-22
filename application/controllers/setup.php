<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class setup extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();  
        $this->load->helper('url');
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Setup Wizard : Welcome';
        
        $this->loadViewsSetup("setup", $this->global, NULL, NULL);
    }

     
}

?>