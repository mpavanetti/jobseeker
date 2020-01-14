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
        $this->load->helper('url','form');
        $this->load->model('JobsTable_model','model');
        $this->load->library('session');
        $this->isLoggedIn();   
        date_default_timezone_set('America/Sao_Paulo');
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Talend Job Seeker : Jobs Table';

        $data["jobs"] = $this->model->listJobs();
        $data["role"] = $this->isManager();
        
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
        if($this->isManager() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            $id = $this->input->post('userId');
            $userInfo = array('isDeleted'=> 1,'updatedBy'=>$this->vendorId, 'field' => $id,'updatedDtm'=>date('Y-m-d H:i:s'));
            
            $result = $this->model->deleteUser($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }


      /**
     * This function is used to load the add new form
     */
    function addNewJob()
    {
        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->model('user_model');
            $data['roles'] = $this->user_model->getUserRoles();
            
            $this->global['pageTitle'] = 'Talend Job Seeker : Add New Job';

            $this->loadViews("addNewJob", $this->global, $data, NULL);
        }
    }


      /**
     * This function is used to add new user to the system
     */
    function addNewJobInsert()
    {
        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('job_component','Job Component Name','trim|required|max_length[20]');
            $this->form_validation->set_rules('file_path','Repository','trim|required|max_length[20]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addNewJob();
            }
            else
            {
                $job_name = ucwords(strtolower($this->security->xss_clean($this->input->post('job_name'))));
                $job_component = $this->security->xss_clean($this->input->post('job_component'));
                $file_path = strtolower($this->security->xss_clean($this->input->post('file_path')));

                     
            // Test if string contains the word 
            if(strpos($job_component, 'tFileInputExcel') !== false){
                $component_type = "xlsx";
            } else if(strpos($job_component, 'tFileInputDelimited') !== false) {
                $component_type = "csv";
            } else if (strpos($job_component, 'tFileInputJSON') !== false) {
                $component_type = "json";
            } else if (strpos($job_component, 'tFileInputXML') !== false) {
                $component_type = "xml";
            } else {
                $component_type = "None";
            }



            $validateComponent = $this->model->validateComponent($job_name, $job_component, $file_path);
                            
                $logs = array('job_name'=>$job_name, 'job_component'=>$job_component,'file_path' => $file_path, 'roleId'=>$roleId,
                                     'createdBy'=>$this->vendorId, 'createdDtm'=>date('Y-m-d H:i:s'));

                $Info = array('job_name'=>$job_name, 'job_component'=>$job_component, 'component_type' => $component_type,'creation_date'=>date('Y-m-d H:i:s'),
                    'file_path' => $file_path, 'file' => $file, 'file_name' => $file_name,'file_uploaded' => 0, 'owner'=>$this->name);

                if($validateComponent > 0){

                    $this->session->set_flashdata('error', 'Component Input creation failed, The component seems to be already registered, Please choose another fill another value on form.');
                } else {
                
                $result = $this->model->addNewUserInsert($Info);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Component Input created successfully !');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Component Input creation failed');
                }
            }
                redirect('addNewJobInsert');
            }
        }
    }


     
    function editOld($id = NULL)
    {
        if($this->isManager() == TRUE )
        {
            $this->loadThis();
        }
        else
        {
            if($id == null)
            {
                redirect('userListing');
            }
            
           
            $data['job'] = $this->model->getJobs($id);
            
            $this->global['pageTitle'] = 'Talend Job Seeker : Edit Data';
            
            $this->loadViews("editOldJob", $this->global, $data, NULL);
        }
    }



      /**
     * This function is used to add new user to the system
     */
    function editJob()
    {
        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->library('form_validation');

            $id = $this->input->post('job_id');
            
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('job_component','Job Component Name','trim|required|max_length[20]');
            $this->form_validation->set_rules('file_path','Repository','trim|required|max_length[20]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addNewJob();
            }
            else
            {
                $job_name = ucwords(strtolower($this->security->xss_clean($this->input->post('job_name'))));
                $job_component = $this->security->xss_clean($this->input->post('job_component'));
                $file_path = strtolower($this->security->xss_clean($this->input->post('file_path')));

                
                switch ($job_component) {
                        case "tFileInputExcel":
                            $component_type = "xlsx";
                            break;
                        case "tFileInputDelimited":
                            $component_type = "csv";
                            break;
                        case "tFileInputJSON":
                            $component_type = "json";
                            break;
                        case "tFileInputXML":
                            $component_type = "xml";
                            break;
                    }
                
                $logs = array('job_name'=>$job_name, 'job_component'=>$job_component,'file_path' => $file_path, 'roleId'=>$roleId,
                                     'createdBy'=>$this->vendorId, 'createdDtm'=>date('Y-m-d H:i:s'));

                $Info = array('job_name'=>$job_name, 'job_component'=>$job_component, 'component_type' => $component_type,'creation_date'=>date('Y-m-d H:i:s'), 'file_path' => $file_path, 'owner'=>$this->name);

                
                $result = $this->model->editUser($Info, $id);
              
                
                if($result == true)
                {
                    $this->session->set_flashdata('success', 'Job updated successfully');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Job updation failed');
                }
                
                
                redirect('JobsTable');
            }
        }
    }
    
}

?>