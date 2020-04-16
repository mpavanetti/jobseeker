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

        $this->global['pageTitle'] = 'Talend Job Seeker : Input Components';

        $data["jobs"] = $this->model->listJobs();
        $data["role"] = $this->isManager();
        
        $this->loadViews("JobsTable", $this->global, $data, NULL);
    }

    public function outputTable()
    {

        $this->global['pageTitle'] = 'Talend Job Seeker : Output Components';

        $data["jobs"] = $this->model->listOutputComponents();
        $data["role"] = $this->isManager();
        
        $this->loadViews("outputTable", $this->global, $data, NULL);
    }

    function listJobs() {
        
        header('Content-type:application/json;charset=utf-8'); // declaring header

        $this->global['pageTitle'] = 'Talend Job Seeker : Json Parse';

        $listJobsJson["data"] = $this->model->listJobs();
        print_r(json_encode($listJobsJson, JSON_PRETTY_PRINT));

     
    }

    function listJobsName() {
        
        header('Content-type:application/json;charset=utf-8'); // declaring header

        $this->global['pageTitle'] = 'Talend Job Seeker : Json Parse';

        $listJobsJson["data"] = $this->model->listJobsName();
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
     * This function is used to delete the data using id
     * @return boolean $result : TRUE / FALSE
     */
    function deleteOutput()
    {
        if($this->isManager() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            $id = $this->input->post('userId');
            $userInfo = array('isDeleted'=> 1,'updatedBy'=>$this->vendorId, 'field' => $id,'updatedDtm'=>date('Y-m-d H:i:s'));
            
            $result = $this->model->deleteOutput($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }

     /**
     * Add Form Input Component 
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
            
            $this->global['pageTitle'] = 'Talend Job Seeker : Add New Input Component';

            $this->loadViews("addNewJob", $this->global, $data, NULL);
        }
    }

    /**
     * Add form Output Component 
     */

    function addNewOutputComponent()
    {
        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->model('user_model');
            $data['roles'] = $this->user_model->getUserRoles();
            
            $this->global['pageTitle'] = 'Talend Job Seeker : Add New Output Component';

            $this->loadViews("addNewOutputComponent", $this->global, $data, NULL);
        }
    }

    /**
     * Add form Insert Input Component 
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
            $this->form_validation->set_rules('job_component','Job Component Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('file_path','Repository','trim|required|max_length[30]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addNewJob();
            }
            else
            {
                $job_name = $this->security->xss_clean($this->input->post('job_name'));
                $job_component = $this->security->xss_clean($this->input->post('job_component'));
                $file_path = strtolower($this->security->xss_clean($this->input->post('file_path')));
                $file = $this->security->xss_clean($this->input->post('file'));
                $file_name = $this->input->post('file_name'); 

                     
            // Test if string contains the word 
            if(strpos($job_component, 'tFileInputExcel') !== false){
                $component_type = ".xlsx";
            } else if(strpos($job_component, 'tFileInputDelimited') !== false) {
                $component_type = ".csv";
            } else if (strpos($job_component, 'tFileInputJSON') !== false) {
                $component_type = ".json";
            } else if (strpos($job_component, 'tFileInputXML') !== false) {
                $component_type = ".xml";
            } else {
                $component_type = "None";
            }

              if ($file == 1){
                $newComponent_Type = $component_type;
              }  else {
                $newComponent_Type = NULL;
              }


            $validateComponent = $this->model->validateComponent($job_name, $job_component);
                            
            /*    $logs = array('job_name'=>$job_name, 'job_component'=>$job_component,'file_path' => $file_path, 'roleId'=>$roleId,
                                     'createdBy'=>$this->vendorId, 'createdDtm'=>date('Y-m-d H:i:s'));  Not Working Yet - Future Release */

                $Info = array('job_name'=>$job_name, 'job_component'=>$job_component, 'component_type' => $component_type,'creation_date'=>date('Y-m-d H:i:s'),
                    'file_path' => $file_path, 'file' => $file, 'file_name' => $file_name,
                    'path' => '/repository/talend/input/'.$file_path.'/'.$file_name.$newComponent_Type,
                    'file_uploaded' => 0, 'owner'=>$this->name);

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


     /**
     * Edit Input Component 
     */
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
                redirect('JobsTable');
            }
            
           
            $data['job'] = $this->model->getJobs($id);
            
            $this->global['pageTitle'] = 'Talend Job Seeker : Edit Data';
            
            $this->loadViews("editOldJob", $this->global, $data, NULL);
        }
    }


         /**
     * Edit Output Component 
     */
    function editOldOutput($id = NULL)
    {
        if($this->isManager() == TRUE )
        {
            $this->loadThis();
        }
        else
        {
            if($id == null)
            {
                redirect('JobsTable/outputTable');
            }
            
           
            $data['job'] = $this->model->getJobsOutput($id);
            
            $this->global['pageTitle'] = 'Talend Job Seeker : Edit Data';
            
            $this->loadViews("editOldJobOutput", $this->global, $data, NULL);
        }
    }



    /**
     * Edit function for update Input Component 
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

            
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('job_component','Job Component Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('file_path','Repository','trim|required|max_length[30]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addNewJob();
            }
            else
            {
                $id = $this->input->post('job_id');
                $job_name = $this->security->xss_clean($this->input->post('job_name'));
                $job_component = $this->security->xss_clean($this->input->post('job_component'));
                $file_path = strtolower($this->security->xss_clean($this->input->post('file_path')));
                $file = $this->security->xss_clean($this->input->post('file'));
                $file_name = $this->input->post('file_name');   

            if( $file_name == '' || $file_name == ' '){
                $file_name == NULL;
            }
                     
            // Test if string contains the word 
            if(strpos($job_component, 'tFileInputExcel') !== false){
                $component_type = ".xlsx";
            } else if(strpos($job_component, 'tFileInputDelimited') !== false) {
                $component_type = ".csv";
            } else if (strpos($job_component, 'tFileInputJSON') !== false) {
                $component_type = ".json";
            } else if (strpos($job_component, 'tFileInputXML') !== false) {
                $component_type = ".xml";
            } else {
                $component_type = "None";
            }

            if ($file == 1){
                $newComponent_Type = $component_type;
              }  else {
                $newComponent_Type = NULL;
              }

           //   $validateComponent = $this->model->validateComponent($job_name, $job_component, $file_path);

                            
                $logs = array('job_name'=>$job_name, 'job_component'=>$job_component,'file_path' => $file_path, 'roleId'=>$roleId,
                                     'createdBy'=>$this->vendorId, 'createdDtm'=>date('Y-m-d H:i:s'));

                $Info = array('job_name'=>$job_name, 'job_component'=>$job_component, 'component_type' => $component_type,'creation_date'=>date('Y-m-d H:i:s'),
                    'file_path' => $file_path, 'file' => $file, 'file_name' => $file_name,
                    'path' => '/repository/talend/input/'.$file_path.'/'.$file_name.$newComponent_Type,
                    'file_uploaded' => 0, 'owner'=>$this->name);

                  if($validateComponent > 0){

                    $this->session->set_flashdata('error', 'Component Input creation failed, The component seems to be already registered, Please choose another fill another value on form.');
                } else {

                
                $result = $this->model->editUser($Info, $id);
              
                
                if($result == true)
                {
                    $this->session->set_flashdata('success', 'Job updated successfully');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Job updation failed');
                }
                
                }
                redirect('JobsTable');
            }
        }
    }



    /**
     *  Insert Output Component function
     */
     
    function addNewOutputComponentInsert()
    {
        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('job_component','Job Component Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('file_path','Repository','trim|required|max_length[30]');
            $this->form_validation->set_rules('file_name','Repository','trim|required|max_length[30]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addNewOutputComponent();
            }
            else
            {
                $job_name = $this->security->xss_clean($this->input->post('job_name'));
                $job_component = $this->security->xss_clean($this->input->post('job_component'));
                $file_path = strtolower($this->security->xss_clean($this->input->post('file_path')));
                $file_name = $this->input->post('file_name'); 

                     
            // Test if string contains the word 
            if(strpos($job_component, 'tFileOutputExcel') !== false){
                $component_type = ".xlsx";
            } else if(strpos($job_component, 'tFileOutputDelimited') !== false) {
                $component_type = ".csv";
            } else if (strpos($job_component, 'tFileOutputJSON') !== false) {
                $component_type = ".json";
            } else if (strpos($job_component, 'tFileOutputXML') !== false) {
                $component_type = ".xml";
            } else {
                $component_type = "None";
            }


            $validateComponent = $this->model->validateOutputComponent($job_name, $job_component);
                            
               // $logs = array('job_name'=>$job_name, 'job_component'=>$job_component,'file_path' => $file_path, 'roleId'=>$roleId,
                                 //    'createdBy'=>$this->vendorId, 'createdDtm'=>date('Y-m-d H:i:s'));

                $Info = array('job_name'=>$job_name, 'job_component'=>$job_component, 'component_type' => $component_type,'creation_date'=>date('Y-m-d H:i:s'),
                    'file_path' => $file_path, 'file_name' => $file_name,
                    'path' => '/repository/talend/output/'.$file_path.'/'.$file_name.$component_type,
                    'file_downloaded' => 0, 'owner'=>$this->name);

                if($validateComponent > 0){

                    $this->session->set_flashdata('error', 'Component Input creation failed, The component seems to be already registered, Please choose another fill another value on form.');
                } else {
                
                $result = $this->model->addNewOutputComponentInsert($Info);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Component Input created successfully !');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Component Input creation failed');
                }
            }
                redirect('JobsTable/addNewOutputComponentInsert');
            }
        }
    }





    /**
     * Edit function for update Output Component 
     */
    function editJobOutput()
    {
        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->library('form_validation');

            
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('job_component','Job Component Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('file_path','Repository','trim|required|max_length[30]');
            $this->form_validation->set_rules('file_name','Repository','trim|required|max_length[30]');

            if($this->form_validation->run() == FALSE)
            {
                $this->editOldOutput();
            }
            else
            {
                $id = $this->input->post('job_id');
                $job_name = $this->security->xss_clean($this->input->post('job_name'));
                $job_component = $this->security->xss_clean($this->input->post('job_component'));
                $file_path = strtolower($this->security->xss_clean($this->input->post('file_path')));
                $file_name = $this->input->post('file_name');   

            if( $file_name == '' || $file_name == ' '){
                $file_name == NULL;
            }
                     
            // Test if string contains the word 
            if(strpos($job_component, 'tFileOutputExcel') !== false){
                $component_type = ".xlsx";
            } else if(strpos($job_component, 'tFileOutputDelimited') !== false) {
                $component_type = ".csv";
            } else if (strpos($job_component, 'tFileOutputJSON') !== false) {
                $component_type = ".json";
            } else if (strpos($job_component, 'tFileOutputXML') !== false) {
                $component_type = ".xml";
            } else {
                $component_type = "None";
            }

            //  $validateComponent = $this->model->validateOutputComponent($job_name, $job_component, $file_path);

             //   $logs = array('job_name'=>$job_name, 'job_component'=>$job_component,'file_path' => $file_path, 'roleId'=>$roleId,
             //                      'createdBy'=>$this->vendorId, 'createdDtm'=>date('Y-m-d H:i:s'));

                $Info = array('job_name'=>$job_name, 'job_component'=>$job_component, 'component_type' => $component_type,'creation_date'=>date('Y-m-d H:i:s'),
                    'file_path' => $file_path, 'file_name' => $file_name,
                    'path' => '/repository/talend/output/'.$file_path.'/'.$file_name.$component_type,
                    'file_downloaded' => 0, 'owner'=>$this->name);

                 if($validateComponent > 0){

                    $this->session->set_flashdata('error', 'Component Input creation failed, The component seems to be already registered, Please choose another fill another value on form.');
                } else {
                
                $result = $this->model->editOutput($Info, $id);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Component Input created successfully !');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Component Input creation failed');
                }
            }
                
                redirect('JobsTable/editOldOutput');
            }
        }
    }



    
}

?>