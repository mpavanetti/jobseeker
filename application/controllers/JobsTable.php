<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

/**
 * Class : Login (LoginController)
 * Login class to control to authenticate user credentials and starts user's session.
 * @author : Kishor Mali
 * @version : 1.1
 * @since : 15 November 2016
 */
class JobsTable extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('file','url');
        $this->load->model('JobsTable_model','model');
        $this->load->library('session');
        $this->isLoggedIn();   
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Talend Job Seeker : Jobs Table';

        $data["jobs"] = $this->model->listJobs();
        
        $this->loadViews("JobsTable", $this->global, $data, NULL);
    }

    function listJobs() {
        
        header('Content-type:application/json;charset=utf-8'); // declaring header

        $this->global['pageTitle'] = 'Talend Job Seeker : Json Parse';

        $listJobsJson["data"] = $this->model->listJobs();
        print_r(json_encode($listJobsJson, JSON_PRETTY_PRINT));

     
    }

     /**
     * This function is used to delete the data using id
     * @return boolean $result : TRUE / FALSE
     */
    function delete()
    {
        if($this->isAdmin() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            $id = $this->input->post('userId');
            $userInfo = array('isDeleted'=>1,'updatedBy'=>$this->vendorId, 'field' => $id,'updatedDtm'=>date('Y-m-d H:i:s'));
            
            $result = $this->model->deleteUser($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }
    
}

?>