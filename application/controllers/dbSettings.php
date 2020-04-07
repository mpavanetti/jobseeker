<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class dbSettings extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('dbSettings_model','model');
        $this->load->library('session');
        $this->isLoggedIn();  
        date_default_timezone_set('America/Sao_Paulo'); 
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : Database Settings';

        $data["settings"] = $this->model->listSettings();
        $data["role"] = $this->isManager();
        
        $this->loadViews("dbSettings", $this->global, $data, NULL);
    }

    public function addDbSetting()
    {

     if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->global['pageTitle'] = 'Job Seeker : Add New Db Setting';

            $this->loadViews("addDbSetting", $this->global, NULL, NULL);
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
                redirect('dbSettings');
            }
            
            $data['fetch'] = $this->model->EditSettingsFetchData($id);
            
            $this->global['pageTitle'] = 'Job Seeker : Edit Data';
            
            $this->loadViews("editDbSetting", $this->global, $data, NULL);
        }
    }


    public function InsertDbSettings() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('db_type','Database Type','trim|required|max_length[30]');
            $this->form_validation->set_rules('login','Login','trim|required|max_length[200]');
            $this->form_validation->set_rules('password','Password','trim|required|max_length[200]');
            $this->form_validation->set_rules('address','Database Address','trim|required|max_length[200]');
            $this->form_validation->set_rules('port','Database Port','trim|required|max_length[200]');
            $this->form_validation->set_rules('schema','Database Schema','trim|required|max_length[200]');
            $this->form_validation->set_rules('description','Database Description','trim|required|max_length[200]');
            $this->form_validation->set_rules('additional_parameters','Additional Parameters','trim|max_length[200]');
            $this->form_validation->set_rules('oracle_ServiceName','Oracle Service Name','trim|max_length[200]');
            $this->form_validation->set_rules('oracle_sid','Oracle SID','trim|max_length[200]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addDbSetting();
            }
            else
            {

                $job_name = $this->security->xss_clean($this->input->post('job_name'));
                $db_type = $this->security->xss_clean($this->input->post('db_type'));
                $login = $this->security->xss_clean($this->input->post('login'));
                $password = $this->security->xss_clean($this->input->post('password'));
                $address = $this->security->xss_clean($this->input->post('address'));
                $port = $this->security->xss_clean($this->input->post('port'));
                $schema = $this->security->xss_clean($this->input->post('schema'));
                $description = $this->security->xss_clean($this->input->post('description')); 
                $additional_parameters = $this->security->xss_clean($this->input->post('additional_parameters'));
                $oracle_ServiceName = $this->security->xss_clean($this->input->post('oracle_ServiceName'));
                $oracle_sid = $this->security->xss_clean($this->input->post('oracle_sid'));


                // Check if the data is alredy on table
                 $validateSetting = $this->model->validateSetting($job_name, $db_type, $login, $address, $port, $schema);


                 $Info = array(
                    'job_name'=>$job_name, 
                    'db_type'=>$db_type, 
                    'login' => $login,
                    'password' => $password,
                    'address' => $address,
                    'port' => $port,
                    'schema' => $schema,
                    'description'=> $description,
                    'additional_parameters' => $additional_parameters,
                    'oracle_ServiceName' => $oracle_ServiceName,
                    'oracle_sid' => $oracle_sid,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                 );

                 if($validateSetting > 0){

                    $this->session->set_flashdata('error', 'This row seems already created, please try changing the input names.');
                } else {
                
                $result = $this->model->insertDbSetting($Info);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Database Setting has successfully created and now is available to be used.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Database Setting creation failed !');
                }

             }

              redirect('dbSettings/addDbSetting');

            }
           
        }


    }

    public function UpdateDbSettings() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');

            $this->form_validation->set_rules('id','Id','trim|required|max_length[30]');
            
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('db_type','Database Type','trim|required|max_length[30]');
            $this->form_validation->set_rules('login','Login','trim|required|max_length[200]');
            $this->form_validation->set_rules('password','Password','trim|required|max_length[200]');
            $this->form_validation->set_rules('address','Database Address','trim|required|max_length[200]');
            $this->form_validation->set_rules('port','Database Port','trim|required|max_length[200]');
            $this->form_validation->set_rules('schema','Database Schema','trim|required|max_length[200]');
            $this->form_validation->set_rules('description','Database Description','trim|required|max_length[200]');
            $this->form_validation->set_rules('additional_parameters','Additional Parameters','trim|max_length[200]');
            $this->form_validation->set_rules('oracle_ServiceName','Oracle Service Name','trim|max_length[200]');
            $this->form_validation->set_rules('oracle_sid','Oracle SID','trim|max_length[200]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addDbSetting();
            }
            else
            {
                $id = $this->security->xss_clean($this->input->post('id'));
                $job_name = $this->security->xss_clean($this->input->post('job_name'));
                $db_type = $this->security->xss_clean($this->input->post('db_type'));
                $login = $this->security->xss_clean($this->input->post('login'));
                $password = $this->security->xss_clean($this->input->post('password'));
                $address = $this->security->xss_clean($this->input->post('address'));
                $port = $this->security->xss_clean($this->input->post('port'));
                $schema = $this->security->xss_clean($this->input->post('schema'));
                $description = $this->security->xss_clean($this->input->post('description')); 
                $additional_parameters = $this->security->xss_clean($this->input->post('additional_parameters'));
                $oracle_ServiceName = $this->security->xss_clean($this->input->post('oracle_ServiceName'));
                $oracle_sid = $this->security->xss_clean($this->input->post('oracle_sid'));



                 $Info = array(
                    'job_name'=>$job_name, 
                    'db_type'=>$db_type, 
                    'login' => $login,
                    'password' => $password,
                    'address' => $address,
                    'port' => $port,
                    'schema' => $schema,
                    'description'=> $description,
                    'additional_parameters' => $additional_parameters,
                    'oracle_ServiceName' => $oracle_ServiceName,
                    'oracle_sid' => $oracle_sid,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                 );

               
                
                $result = $this->model->updateDbSetting($Info, $id);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Database Setting has been successfully updated and now is available to be used.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Database Setting creation failed !');
                }

             

              redirect('dbSettings');

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