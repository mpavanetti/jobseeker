<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class Tmf extends BaseController
{
    /**
     * This is default constructor of the class
     */
   public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('Tmf_model','model');
        $this->load->library('session');
        $this->isLoggedIn();   
        date_default_timezone_set('America/Sao_Paulo');
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Talend Job Seeker : Transaction Monitoring Framework';

        $data["jobs"] = $this->model->listJobs();
        $data["role"] = $this->isManager();
        
        $this->loadViews("tmf", $this->global, $data, NULL);
    }

   
    
}

?>