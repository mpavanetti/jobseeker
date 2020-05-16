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

    function fetchDataStatus($status)
    {
       
            $data["jobs"] = $this->model->fetchDataStatus($status);
            $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
            $this->loadViews("tmf", $this->global, $data, NULL);
               
    }

     function fetchDataJobName($jobName)
    {
       
            $data["jobs"] = $this->model->fetchDataJobName($jobName);
            $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
            $this->loadViews("tmf", $this->global, $data, NULL);
               
    }

     function getError($instanceId)
    {
        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
        $errorList["data"] = $this->model->getError($instanceId);

        
          print_r(json_encode($errorList, JSON_PRETTY_PRINT));
    }

     function listId($id)
    {
        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
        $list["data"] = $this->model->listId($id);

        
          print_r(json_encode($list, JSON_PRETTY_PRINT));
    }


     function updateUser($instanceId,$name)
    {
        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
        $errorList["data"] = $this->model->updateUser($instanceId,$name);

    }

     function updateStatus($id,$status)
    {
        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
        $errorList["data"] = $this->model->updateStatus($id,$status);

        echo "Ok";

    }

      /**
     * This function is used to delete the data using id
     * @return boolean $result : TRUE / FALSE
     */
    function delete()
    {
        if($this->isManager() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            $id = $this->input->post('userId');
            $userInfo = array('isDeleted'=> 1,'updatedBy'=>$this->vendorId, 'field' => $id,'updatedDtm'=>date('Y-m-d H:i:s'));
            
            $result = $this->model->delete($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }


   
    
}

?>