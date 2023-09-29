<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class SmtpSettings extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('SmtpSettings_model','model');
        $this->load->library('session');
        $this->isLoggedIn();  
        date_default_timezone_set('America/Sao_Paulo'); 
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : Email Settings';

        $data["settings"] = $this->model->listSettings();
        $data["role"] = $this->isManager();
        
        $this->loadViews("smtpSettings", $this->global, $data, NULL);
    }

    public function addSetting()
    {

     if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->global['pageTitle'] = 'Job Seeker : Add New Setting';

            $this->loadViews("addSmtpSetting", $this->global, NULL, NULL);
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
                redirect('SmtpSettings');
            }
            
            $data['fetch'] = $this->model->EditSettingsFetchData($id);
            
            $this->global['pageTitle'] = 'Job Seeker : Edit Data';
            
            $this->loadViews("editSmtpSetting", $this->global, $data, NULL);
        }
    }


    public function insertSetting() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('name','Smtp Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('ssl','ssl');
            $this->form_validation->set_rules('smtp_host','Smtp Host','trim|required|max_length[30]');
            $this->form_validation->set_rules('smtp_port','Smtp Port','trim|required|max_length[30]');
            $this->form_validation->set_rules('username','Username','trim|required|max_length[30]');
            $this->form_validation->set_rules('password','Password','trim|required|max_length[30]');
            $this->form_validation->set_rules('description','Description','trim|required|max_length[500]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addSetting();
            }
            else
            {
                $ssl = $this->security->xss_clean($this->input->post('ssl'));
                $name = $this->security->xss_clean($this->input->post('name'));
                $smtp_host = $this->security->xss_clean($this->input->post('smtp_host'));
                $smtp_port = $this->security->xss_clean($this->input->post('smtp_port'));
                $username = $this->security->xss_clean($this->input->post('username'));
                $password = $this->security->xss_clean($this->input->post('password'));
                $description = $this->security->xss_clean($this->input->post('description'));


                // Check if the data is alredy on table
                 $validateSetting = $this->model->validateSetting($name, $smtp_host, $smtp_port);


                 $Info = array(
                    'name'=> $name, 
                    'smtp_host'=> $smtp_host, 
                    'smtp_port' => $smtp_port,
                    'username' => $username,
                    'ssl' => $ssl,
                    'password' => $password,
                    'description' => $description,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                 );

                 if($validateSetting > 0){

                    $this->session->set_flashdata('error', 'This row seems already created, please try changing the input names.');
                } else {
                
                $result = $this->model->insertSetting($Info);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Smtp Setting has successfully created and now is available to be used.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Smtp Setting creation failed !');
                }

             }

              redirect('SmtpSettings/addSetting');

            }
           
        }


    }

    public function UpdateSettings() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');

            $this->form_validation->set_rules('id','Id','trim|required|max_length[11]');
            $this->form_validation->set_rules('ssl','ssl');
            $this->form_validation->set_rules('name','Smtp Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('smtp_host','Smtp Host','trim|required|max_length[30]');
            $this->form_validation->set_rules('smtp_port','Smtp Port','trim|required|max_length[30]');
            $this->form_validation->set_rules('username','Username','trim|required|max_length[30]');
            $this->form_validation->set_rules('password','Password','trim|required|max_length[30]');
            $this->form_validation->set_rules('description','Description','trim|required|max_length[500]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addSetting();
            }
            else
            {

                $id = $this->security->xss_clean($this->input->post('id'));
                $ssl = $this->security->xss_clean($this->input->post('ssl'));
                $name = $this->security->xss_clean($this->input->post('name'));
                $smtp_host = $this->security->xss_clean($this->input->post('smtp_host'));
                $smtp_port = $this->security->xss_clean($this->input->post('smtp_port'));
                $username = $this->security->xss_clean($this->input->post('username'));
                $password = $this->security->xss_clean($this->input->post('password'));
                $description = $this->security->xss_clean($this->input->post('description'));



                 $Info = array(
                    'name'=> $name, 
                    'smtp_host'=> $smtp_host, 
                    'smtp_port' => $smtp_port,
                    'username' => $username,
                    'password' => $password,
                    'ssl' => $ssl,
                    'description' => $description,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                 );

                
                $result = $this->model->updateSetting($Info, $id);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Smtp Setting has successfully updated.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Smtp Setting update failed !');
                }


              redirect('SmtpSettings/EditSettingsFetchData');

            }
           
        }


    }

 

    public function deleteSetting() {

        if($this->isManager() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            $id = $this->input->post('userId');
            /*
            $userInfo = array('isDeleted'=> 1,'updatedBy'=>$this->vendorId, 'field' => $id,'updatedDtm'=>date('Y-m-d H:i:s')); Future Release Not working */
            
            $result = $this->model->deleteSetting($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }
    
}

?>