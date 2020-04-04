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

        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';

          $data["listStatus"] = $this->model->listStatus();
          $data["listJobName"] = $this->model->listJobName();
          $data["listDimension"] = $this->model->listDimension();
          $data["listReprocess"] = $this->model->listReprocess();

      
        
        $this->loadViews("tmfBuilder", $this->global, $data, NULL);
    }

    public function data() {

        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';

        $data["jobs"] = $this->model->list();
        $data["role"] = $this->isManager();
        
        $this->loadViews("tmf", $this->global, $data, NULL);

    }

     function fetchData()
    {
        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            
            $status = $this->input->post('status');
            $job_name = $this->input->post('job_name');
            $dimension = $this->input->post('dimension');
            $reprocess = $this->input->post('reprocess');
            $fromDate = $this->input->post('fromDate');
            $toDate = $this->input->post('toDate');
            $eventText = $this->input->post('eventText');

            $data["jobs"] = $this->model->listJobs($status,$job_name,$dimension,$reprocess,$eventText,$fromDate,$toDate);

            $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
            $this->loadViews("tmf", $this->global, $data, NULL);
        }        
    }


   
    
}

?>