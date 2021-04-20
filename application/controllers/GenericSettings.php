<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


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
        date_default_timezone_set('America/Sao_Paulo'); 
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : Generic Settings';

        $data["settings"] = $this->model->listSettings();
        $data["role"] = $this->isManager();
        
        $this->loadViews("GenericSettings", $this->global, $data, NULL);
    }

    public function AddSettings()
    {

     if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->global['pageTitle'] = 'Job Seeker : Add New Setting';

            $this->loadViews("addSetting", $this->global, NULL, NULL);
        }
    }


    /**
     * Edit Input Component 
     */
    function EditSettingsFetchData($id = NULL)
    {
        if($this->isManager() == TRUE )
        {
            $this->loadThis();
        }
        else
        {
            if($id == null)
            {
                redirect('GenericSettings');
            }
            
            $data['fetch'] = $this->model->EditSettingsFetchData($id);
            
            $this->global['pageTitle'] = 'Job Seeker : Edit Data';
            
            $this->loadViews("editSettings", $this->global, $data, NULL);
        }
    }


    public function insertGenericSetting() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[255]');
            $this->form_validation->set_rules('setting','Setting Name','trim|required|max_length[255]');
            $this->form_validation->set_rules('value1','Value 1','trim|required|max_length[3000]');
            $this->form_validation->set_rules('value2','Value 2','trim|max_length[3000]');
            $this->form_validation->set_rules('value3','Value 3','trim|max_length[3000]');
            $this->form_validation->set_rules('value4','Value 4','trim|max_length[3000]');
            $this->form_validation->set_rules('value5','Value 5','trim|max_length[3000]');
            $this->form_validation->set_rules('description','Setting Description','trim|required|max_length[5000]');

            if($this->form_validation->run() == FALSE)
            {
                $this->AddSettings();
            }
            else
            {

                $job_name = $this->security->xss_clean($this->input->post('job_name'));
                $setting = $this->security->xss_clean($this->input->post('setting'));
                $value1 = $this->security->xss_clean($this->input->post('value1'));
                $value2 = $this->security->xss_clean($this->input->post('value2'));
                $value3 = $this->security->xss_clean($this->input->post('value3'));
                $value4 = $this->security->xss_clean($this->input->post('value4'));
                $value5 = $this->security->xss_clean($this->input->post('value5'));
                $description = $this->security->xss_clean($this->input->post('description')); 

                 $validateSetting = $this->model->validateSetting($job_name, $setting);


                 $Info = array(
                    'job_name'=>$job_name, 
                    'setting'=>$setting, 
                    'description' => $description,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'value1' => $value1,
                    'value2' => $value2,
                    'value3' => $value3,
                    'value4' => $value4,
                    'value5' => $value5,
                    'owner'=>$this->name
                 );

                 if($validateSetting > 0){

                    $this->session->set_flashdata('error', 'This Setting seems already created, please try changing the input names.');
                } else {
                
                $result = $this->model->insertGenericSetting($Info);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Generic Setting has successfully created and now is available to be used.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Generic Setting creation failed !');
                }

             }

              redirect('AddSettings');

            }
           
        }


    }

    public function updateGenericSetting() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('setting','Setting Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('value1','Value 1','trim|required|max_length[3000]');
            $this->form_validation->set_rules('value2','Value 2','trim|max_length[3000]');
            $this->form_validation->set_rules('value3','Value 3','trim|max_length[3000]');
            $this->form_validation->set_rules('value4','Value 4','trim|max_length[3000]');
            $this->form_validation->set_rules('value5','Value 5','trim|max_length[3000]');
            $this->form_validation->set_rules('description','Setting Description','trim|required|max_length[3000]');

            if($this->form_validation->run() == FALSE)
            {
                $this->updateGenericSetting();
            }
            else
            {
                $id = $this->security->xss_clean($this->input->post('id'));
                $job_name = $this->security->xss_clean($this->input->post('job_name'));
                $setting = $this->security->xss_clean($this->input->post('setting'));
                $value1 = $this->security->xss_clean($this->input->post('value1'));
                $value2 = $this->security->xss_clean($this->input->post('value2'));
                $value3 = $this->security->xss_clean($this->input->post('value3'));
                $value4 = $this->security->xss_clean($this->input->post('value4'));
                $value5 = $this->security->xss_clean($this->input->post('value5'));
                $description = $this->security->xss_clean($this->input->post('description')); 


                 $Info = array(
                    'job_name'=>$job_name, 
                    'setting'=>$setting, 
                    'description' => $description,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'value1' => $value1,
                    'value2' => $value2,
                    'value3' => $value3,
                    'value4' => $value4,
                    'value5' => $value5,
                    'owner'=>$this->name
                 );
                
                $result = $this->model->updateGenericSetting($Info,$id);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'The Generic Setting has been successfully updated !');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Generic Setting update failed !');
                }

              redirect('GenericSettings');

            }
           
        }


    }

    public function deleteGenericSetting() {

        if($this->isManager() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            $id = $this->input->post('userId');
            /*
            $userInfo = array('isDeleted'=> 1,'updatedBy'=>$this->vendorId, 'field' => $id,'updatedDtm'=>date('Y-m-d H:i:s')); Future Release Not working */
            
            $result = $this->model->deleteGenericSetting($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }
    
}

?>