<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class EmailSettings extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('emailSettings_model','model');
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
        
        $this->loadViews("emailSettings", $this->global, $data, NULL);
    }

    public function mail() {

        $result = false;

        $array = $this->input->post('object');
        $jsonArray = json_encode($array);
        print_r($jsonArray);

        $config = array();
        $config['protocol'] = 'smtp';
        $config['smtp_host'] = $array["smtp_host"];
        $config['smtp_user'] = $array["username"];
        $config['smtp_pass'] = $array["password"];
        $config['smtp_port'] = $array["smtp_port"]; 
        $config['mailtype'] = 'html';

        if($array["ssl"] == "1") {
            $config['smtp_crypto'] = 'ssl';
        } else {
            $config['smtp_crypto'] = 'tls'; 
        }

        $this->load->library('email', $config);
        $this->email->set_newline("\r\n");

         $this->email->to($array["to"]);
         $this->email->from($array["username"],$array["from"]);
         $this->email->cc($array["cc"]);
         $this->email->subject($array["subject"]);
         $this->email->message($array["msg"]); 
         $this->email->send();

         if ($this->email->send()) {
          $result = true;
          echo $result;

      }
      else {
          print_r($this->email->print_debugger());
          echo $result;
      }


    }

    public function mail2() {

        $config = array();
        $config['protocol'] = 'smtp';
        $config['smtp_host'] = 'smtp.gmail.com';
        $config['smtp_user'] = 'YourEmail@gmail.com';
        $config['smtp_pass'] = 'YOURPASSWORD';
        $config['smtp_port'] = '465'; 
        $config['mailtype'] = 'text';
        $config['smtp_crypto'] = 'ssl';

        $this->load->library('email', $config);

        $this->email->set_newline("\r\n");

        $this->email->to('YourEmail@hotmail.com');
        $this->email->from('YourEmail@gmail.com');
        $this->email->subject('Teste');
        $this->email->message('Teste'); 
        $this->email->send();

        if ($this->email->send()) {
          echo "Email Sent !";
      }
      else {
          print_r($this->email->print_debugger());
      }
  

}

    public function fetchAll($colunm) {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson["data"] = $this->model->fetchAll($colunm);
         print_r(json_encode($listJobsJson, JSON_PRETTY_PRINT));

     }

    public function fetch($id) {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson["data"] = $this->model->fetch($id);
         print_r(json_encode($listJobsJson, JSON_PRETTY_PRINT));

     }

     public function fetchSMTP() {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson = $this->model->fetchSMTP();
         print_r(json_encode($listJobsJson, JSON_PRETTY_PRINT));

     }

     public function fetchXsmtp($id) {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson = $this->model->fetchXsmtp($id);
         print_r(json_encode($listJobsJson, JSON_PRETTY_PRINT));

     }


     public function addSetting()
     {

       if($this->isManager() == TRUE)
       {
        $this->loadThis();
    }
    else
    {

        $this->global['pageTitle'] = 'Job Seeker : Add New Email Template';

        $this->loadViews("addEmailSetting", $this->global, NULL, NULL);
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
                redirect('EmailSettings');
            }
            
            $data['fetch'] = $this->model->EditSettingsFetchData($id);
            
            $this->global['pageTitle'] = 'Job Seeker : Edit Data';
            
            $this->loadViews("editEmailSettings", $this->global, $data, NULL);
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
            
            $this->form_validation->set_rules('name','Email Name','trim|required|max_length[300]');
            $this->form_validation->set_rules('smtp','SMTP Provider','trim|required|max_length[30]');
            $this->form_validation->set_rules('to','To','trim|required|max_length[200]');
            $this->form_validation->set_rules('from','From','trim|required|max_length[200]');
            $this->form_validation->set_rules('cc','Cc - Copy','trim|max_length[200]');
            $this->form_validation->set_rules('subject','Subject','trim|required|max_length[200]');
            $this->form_validation->set_rules('msg','Email Message Content','trim|required|max_length[15000]');
            $this->form_validation->set_rules('description','Database Description','trim|required|max_length[500]');
            $this->form_validation->set_rules('enabled','Enabled / Disabled','trim|max_length[200]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addSetting();
            }
            else
            {

                $name = $this->security->xss_clean($this->input->post('name'));
                $smtp = $this->security->xss_clean($this->input->post('smtp'));
                $to = $this->security->xss_clean($this->input->post('to'));
                $from = $this->security->xss_clean($this->input->post('from'));
                $cc = $this->security->xss_clean($this->input->post('cc'));
                $subject = $this->security->xss_clean($this->input->post('subject'));
                $msg = $this->input->post('msg');
                $description = $this->security->xss_clean($this->input->post('description')); 
                $enabled = $this->security->xss_clean($this->input->post('enabled')); 


                // Check if the data is alredy on table
                $validateSetting = $this->model->validateSetting($name, $to, $from, $subject);


                $Info = array(
                    'name'=>$name, 
                    'smtp'=>$smtp, 
                    'to' => $to,
                    'from' => $from,
                    'cc' => $cc,
                    'subject' => $subject,
                    'msg' => $msg,
                    'description'=> $description,
                    'enabled' => $enabled,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                );

                if($validateSetting > 0){

                    $this->session->set_flashdata('error', 'This row seems already created, please try changing the input names.');
                } else {

                    $result = $this->model->insertDbSetting($Info);

                    if($result > 0)
                    {
                        $this->session->set_flashdata('success', 'New Email Template has successfully created and now is available to be used.');
                    }
                    else
                    {
                        $this->session->set_flashdata('error', 'Database Setting creation failed !');
                    }

                }

                redirect('EmailSettings/addSetting');

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
            $this->form_validation->set_rules('id','Id','trim|required|max_length[11]');
            $this->form_validation->set_rules('name','Email Name','trim|required|max_length[300]');
            $this->form_validation->set_rules('smtp','SMTP Provider','trim|required|max_length[30]');
            $this->form_validation->set_rules('to','To','trim|required|max_length[200]');
            $this->form_validation->set_rules('from','From','trim|required|max_length[200]');
            $this->form_validation->set_rules('cc','Cc - Copy','trim|max_length[200]');
            $this->form_validation->set_rules('subject','Subject','trim|required|max_length[200]');
            $this->form_validation->set_rules('msg','Email Message Content','trim|required|max_length[15000]');
            $this->form_validation->set_rules('description','Database Description','trim|required|max_length[500]');
            $this->form_validation->set_rules('enabled','Enabled / Disabled','trim|max_length[200]');

            if($this->form_validation->run() == FALSE)
            {
                $this->EditSettingsFetchData();
            }
            else
            {
                $id = $this->security->xss_clean($this->input->post('id'));
                $name = $this->security->xss_clean($this->input->post('name'));
                $name = $this->security->xss_clean($this->input->post('name'));
                $smtp = $this->security->xss_clean($this->input->post('smtp'));
                $to = $this->security->xss_clean($this->input->post('to'));
                $from = $this->security->xss_clean($this->input->post('from'));
                $cc = $this->security->xss_clean($this->input->post('cc'));
                $subject = $this->security->xss_clean($this->input->post('subject'));
                $msg = $this->input->post('msg');
                $description = $this->security->xss_clean($this->input->post('description')); 
                $enabled = $this->security->xss_clean($this->input->post('enabled')); 


                $Info = array(
                    'name'=>$name, 
                    'smtp'=>$smtp, 
                    'to' => $to,
                    'from' => $from,
                    'cc' => $cc,
                    'subject' => $subject,
                    'msg' => $msg,
                    'description'=> $description,
                    'enabled' => $enabled,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                );
                
                $result = $this->model->updateDbSetting($Info, $id);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'Your email template has been successfully Updated !');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Email Template update failed !');
                }


                redirect('EmailSettings/EditSettingsFetchData');

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