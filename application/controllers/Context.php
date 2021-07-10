<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class Context extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('Context_model','model');
        $this->load->library('session');
        $this->isLoggedIn();   
        date_default_timezone_set('America/Sao_Paulo');
    }


     public function projectDetails() {

         if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->global['pageTitle'] = 'Job Seeker : Project Config';
            $user = $this->global['name'];

            $data["list"] = $this->model->listProjects();
            $data["projects"] = $this->model->listAvailableProjects();
            $data["activeprojects"] = $this->model->listActiveProjects();
            
            $this->loadViews("projectDetails", $this->global, $data, NULL);
        }
    }

       public function addProject() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('name','Project Name','required|max_length[1000]');
            $this->form_validation->set_rules('active','Active Project','required|max_length[1]');
            $this->form_validation->set_rules('gitpath','Git Path','trim|max_length[2000]');

            if($this->form_validation->run() == FALSE)
            {
                $this->projectDetails();
            }
            else
            {

                $name = $this->security->xss_clean($this->input->post('name'));
                $active = $this->security->xss_clean($this->input->post('active'));
                $gitpath = $this->security->xss_clean($this->input->post('gitpath'));

                if ($name == null || $active == null) {
                   $this->session->set_flashdata('error', 'Project Setup failed ! You must type a project name');
                   redirect('Context/projectDetails');
                }

                // Check if the data is alredy on table
                 $validateSetting = $this->model->validateProject($name);

                 $Info = array(
                    'ProjectName'=>$name, 
                    'IsActive'=>$active, 
                    'GitPath' => $gitpath,
                    'CreatedOn'=>date('Y-m-d H:i:s')
                 );

                 if($validateSetting > 0){

                    $this->session->set_flashdata('error', 'This row seems already created, please try changing the project name.');
                } else {
                
                $result = $this->model->insertProject($Info);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Project has successfully created and now is available to be used.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Project creation failed !');
                }

             }

              redirect('Context/projectDetails');

            }
           
        }

    }

      public function deleteProject() {

        if($this->isManager() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            $id = $this->input->post('userId');

            $result = $this->model->deleteProject($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }


     /**
     * Edit Input Component 
     */
    function editProject($id = NULL)
    {
        if($this->isManager() == TRUE )
        {
            $this->loadThis();
        }
        else
        {
            if($id == null)
            {
                redirect('Context/projectDetails');
            }
            
           
            $data['project'] = $this->model->getProject($id);
            
            $this->global['pageTitle'] = 'Talend Job Seeker : Edit Data';
            
            $this->loadViews("projectDetailsEdit", $this->global, $data, NULL);
        }
    }

    public function editProjectUpdate() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('name','Project Name','required|max_length[1000]');
            $this->form_validation->set_rules('Id','Project Id','required|max_length[11]');
            $this->form_validation->set_rules('active','Active Project','required|max_length[1]');
            $this->form_validation->set_rules('gitpath','Git Path','trim|max_length[2000]');

            if($this->form_validation->run() == FALSE)
            {
                $this->projectDetails();
            }
            else
            {
                $Id = $this->security->xss_clean($this->input->post('Id'));
                $name = $this->security->xss_clean($this->input->post('name'));
                $active = $this->security->xss_clean($this->input->post('active'));
                $gitpath = $this->security->xss_clean($this->input->post('gitpath'));

                if ($name == null || $active == null) {
                   $this->session->set_flashdata('error', 'Project Setup failed ! You must type a project name');
                   redirect('Context/projectDetails');
                }

                // Check if the data is alredy on table
                 $validateSetting = $this->model->validateProject($name);

                 $Info = array(
                    'ProjectName'=>$name, 
                    'IsActive'=>$active, 
                    'GitPath' => $gitpath,
                    'ModifiedOn'=>date('Y-m-d H:i:s')
                 );

                $result = $this->model->updateProjectSetting($Info,$Id);
                
                if($result == True)
                {
                    $this->session->set_flashdata('success', 'New Project has successfully updated and now is available to be used.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Project update failed !');
                }

              redirect('Context/projectDetails');

            }
           
        }

    }


     
}

?>