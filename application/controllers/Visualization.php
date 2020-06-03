<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class Visualization extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('Visualization_model','model');
        $this->load->library('session');
        $this->isLoggedIn();   
        date_default_timezone_set('America/Sao_Paulo');
    }

    /**
     * Index Page for this controller.
     */
    public function view($report = null)
    {

        $this->global['pageTitle'] = 'Job Seeker : Data Visualization';
        $name = urldecode($report);
        $user = $this->global['name'];
        $data["view"] = $this->model->view($name);

        $validate= $this->model->permission($name,$user);

        if ($validate >= 1){

            $this->loadViews("visualization", $this->global, $data, NULL);

        } else {
           redirect('pageNotFound');
        }
        
        
    }

    public function fetch($id) {

      if($this->isManager() == TRUE)
        {
             $this->loadThis();
        }
         else
         {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson["data"] = $this->model->fetch($id);
         print_r(json_encode($listJobsJson, JSON_PRETTY_PRINT));
     }

     }

     public function config()
    {

         if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->global['pageTitle'] = 'Job Seeker : Visualization Config';
            $user = $this->global['name'];

            $data["list"] = $this->model->list();
            $data["reports"] = $this->model->listReports();
            $data["types"] = $this->model->listTypes();
            $data["users"] = $this->model->listUsers();
            $data["groups"] = $this->model->listGroups();
            
            $this->loadViews("visualizationConfig", $this->global, $data, NULL);
        }
    }

       public function add() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('name','Report Name','required|max_length[20]');
            $this->form_validation->set_rules('type','Report Software Type','required|max_length[30]');
            $this->form_validation->set_rules('code','Embebed Code','trim|required|max_length[5000]');

            if($this->form_validation->run() == FALSE)
            {
                $this->config();
            }
            else
            {

                $name = $this->security->xss_clean($this->input->post('name'));
                $type = $this->security->xss_clean($this->input->post('type'));
                $users = $this->security->xss_clean($this->input->post('users'));
                $groups = $this->security->xss_clean($this->input->post('groups'));
                $code = $this->input->post('code');

             

                if($code[0] !== '<' && $code[strlen($code) - 1] !== '>') {
                    if(filter_var($code, FILTER_VALIDATE_URL)){
                        $code = '<iframe src="'.$code.'" style="border:none;width:100%;height:100%;"></iframe>';
                    } else {
                        $this->session->set_flashdata('error', 'Report creation failed ! This is not a valid URL');
                   redirect('Visualization/config');
                    }
                    
                }

                if ($users == null && $groups == null) {
                   $this->session->set_flashdata('error', 'Report creation failed ! You must select at the least one user or group');
                   redirect('Visualization/config');
                }

                if($users == null) {
                    $stringUsers = 'All Users from group';
                } else {
                  $stringUsers = implode(",", $users);
                }

                if($groups == null) {
                    $stringGroups = 'None';
                } else {
                  $stringGroups = implode(",", $groups);
                }

                // Check if the data is alredy on table
                 $validateSetting = $this->model->validate($name);


                 $Info = array(
                    'name'=>$name, 
                    'type'=>$type, 
                    'users' => $stringUsers,
                    'groups' => $stringGroups,
                    'code' => $code,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                 );

                 if($validateSetting > 0){

                    $this->session->set_flashdata('error', 'This row seems already created, please try changing the report name.');
                } else {
                
                $result = $this->model->insert($Info);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Report has successfully created and now is available to be used.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Report creation failed !');
                }

             }

              redirect('Visualization/config');

            }
           
        }

    }

      public function delete() {

        if($this->isManager() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            $id = $this->input->post('userId');
            /*
            $userInfo = array('isDeleted'=> 1,'updatedBy'=>$this->vendorId, 'field' => $id,'updatedDtm'=>date('Y-m-d H:i:s')); Future Release Not working */
            
            $result = $this->model->delete($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }


     
}

?>