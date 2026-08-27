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
        $this->load->model('EmailSettings_model','model');
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

    private function sendEmailJson($status, $message = '', $httpStatus = 200)
    {
        $this->output->set_status_header($httpStatus);
        echo json_encode(array('status' => $status, 'message' => $message));
    }

    private function emailDebuggerMessage()
    {
        $debug = strip_tags($this->email->print_debugger(array('headers', 'subject')));
        $debug = preg_replace('/\s+/', ' ', trim($debug));

        if ($debug === '') {
            return 'Email sending failed. Check SMTP host, port, encryption, credentials and From/Reply-To settings.';
        }

        if (strlen($debug) > 1200) {
            $debug = substr($debug, 0, 1200) . '...';
        }

        return $debug;
    }

    private function templatePreviewStatus($template)
    {
        $text = strtolower(trim((string) $template['name'].' '.(string) $template['subject']));
        if (strpos($text, 'abort') !== FALSE) {
            return 'ABORTED';
        }
        if (strpos($text, 'fail') !== FALSE) {
            return 'FAILED';
        }
        if (strpos($text, 'success') !== FALSE) {
            return 'SUCCESS';
        }

        return 'PREVIEW';
    }

    private function renderTemplatePreview($content, $template)
    {
        $publicUrl = rtrim((string) getenv('JOBSEEKER_JENKINS_PUBLIC_URL'), '/').'/';
        if ($publicUrl === '/') {
            $publicUrl = 'http://localhost:8080/';
        }

        $projectUrl = $publicUrl.'job/jobseeker-email-preview/';
        $buildUrl = $projectUrl.'42/';
        $environmentValues = array(
            'ENVIRONMENT' => 'DEV',
            'BUILD_ID' => '2026-08-26_204025',
            'BUILD_TAG' => 'jenkins-jobseeker-email-preview-42',
            'NODE_NAME' => 'built-in',
            'EXECUTOR_NUMBER' => '0',
            'WORKSPACE' => '/var/jenkins_home/workspace/jobseeker-email-preview',
            'JOBSEEKER_DATASET' => 'customers_daily',
            'JOBSEEKER_ROWS_READ' => '12,500',
            'JOBSEEKER_ROWS_WRITTEN' => '12,492',
            'JOBSEEKER_ROWS_REJECTED' => '8',
            'JOBSEEKER_DURATION' => '00:02:14'
        );
        $values = array(
            '${PROJECT_NAME}' => 'JobSeeker ETL Preview',
            '${BUILD_NUMBER}' => '42',
            '${BUILD_STATUS}' => $this->templatePreviewStatus($template),
            '${CAUSE}' => 'Started by the JobSeeker email template preview.',
            '${BUILD_URL}' => $buildUrl,
            '${PROJECT_URL}' => $projectUrl
        );

        $content = strtr((string) $content, $values);
        $content = preg_replace_callback('/\$\{ENV,\s*var="([^"]+)"\}/', function($matches) use ($environmentValues) {
            return isset($environmentValues[$matches[1]]) ? $environmentValues[$matches[1]] : 'Sample value';
        }, $content);
        $propertyValues = array(
            'dataset' => 'customers_daily',
            'rows_read' => '12,500',
            'rows_written' => '12,492',
            'rows_rejected' => '8',
            'duration' => '00:02:14'
        );
        $content = preg_replace_callback('/\$\{PROPFILE,\s*file="jobseeker-email-metrics\.properties",\s*property="([^"]+)"\}/', function($matches) use ($propertyValues) {
            return isset($propertyValues[$matches[1]]) ? $propertyValues[$matches[1]] : 'Not reported';
        }, $content);
        $content = preg_replace('/\$\{BUILD_LOG_REGEX\b[^}]*\}/s', "Traceback (most recent call last):\n  File \"etl_job.py\", line 84, in run\nRuntimeError: Sample transformation error for template preview", $content);
        $content = preg_replace('/\$\{BUILD_LOG\b[^}]*\}/s', "[JobSeeker] ETL execution\nRead 12,500 rows from customers_daily\nWrote 12,492 rows\nRejected 8 rows\nFinished: ".$this->templatePreviewStatus($template), $content);

        return $content;
    }

    public function mail() {

        $this->output->set_content_type('application/json');

        $id = $this->input->post('id');
        if ($id === NULL || ! ctype_digit((string) $id)) {
            $this->sendEmailJson(FALSE, 'Invalid email template.', 400);
            return;
        }

        $records = $this->model->fetchXsmtpCredentials($id);
        if (empty($records)) {
            $this->sendEmailJson(FALSE, 'Email template was not found.', 404);
            return;
        }

        $array = (array) $records[0];
        if ((int) $array['enabled'] === 0) {
            $this->sendEmailJson(FALSE, 'Email template is disabled.', 400);
            return;
        }

        if (isset($array['is_enabled']) && (int) $array['is_enabled'] === 0) {
            $this->sendEmailJson(FALSE, 'Selected SMTP provider is disabled.', 400);
            return;
        }

        $smtpHost = trim((string) $array['smtp_host']);
        $smtpPort = (int) $array['smtp_port'];
        $recipients = trim((string) $array['to']);

        if ($smtpHost === '' || $smtpPort <= 0) {
            $this->sendEmailJson(FALSE, 'Selected SMTP provider is missing host or port.', 400);
            return;
        }

        if ($recipients === '') {
            $this->sendEmailJson(FALSE, 'Email template has no recipients.', 400);
            return;
        }

        $smtpUsername = trim((string) $array['username']);
        $smtpPassword = (string) $array['password'];
        $replyTo = isset($array['reply_to']) ? trim((string) $array['reply_to']) : '';
        $templateFrom = trim((string) $array['from']);

        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $senderEmail = $replyTo;
        } else if (filter_var($smtpUsername, FILTER_VALIDATE_EMAIL)) {
            $senderEmail = $smtpUsername;
        } else if (filter_var($templateFrom, FILTER_VALIDATE_EMAIL)) {
            $senderEmail = $templateFrom;
        } else {
            $senderEmail = 'jobseeker@local.test';
        }

        $senderName = ($templateFrom !== '' && ! filter_var($templateFrom, FILTER_VALIDATE_EMAIL)) ? $templateFrom : 'JobSeeker';

        $config = array(
            'protocol' => 'smtp',
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_timeout' => 10,
            'mailtype' => 'html',
            'charset' => 'utf-8',
            'newline' => "\r\n",
            'crlf' => "\r\n"
        );

        if ($smtpUsername !== '' && trim($smtpPassword) !== '') {
            $config['smtp_user'] = $smtpUsername;
            $config['smtp_pass'] = $smtpPassword;
        }

        $crypto = strtolower(trim((string) $array['ssl']));
        if ($crypto === '1' || $crypto === 'ssl') {
            $config['smtp_crypto'] = 'ssl';
        } else if ($crypto === '2' || $crypto === 'tls') {
            $config['smtp_crypto'] = 'tls';
        }

        $this->load->library('email');
        $this->email->initialize($config);
        $this->email->clear(TRUE);
        $this->email->set_newline("\r\n");
        $this->email->set_crlf("\r\n");

        $this->email->to($recipients);
        $this->email->from($senderEmail, $senderName);
        $this->email->reply_to($senderEmail, $senderName);

        if (trim((string) $array['cc']) !== '') {
            $this->email->cc($array['cc']);
        }

        $this->email->subject($this->renderTemplatePreview($array['subject'], $array));
        $this->email->message($this->renderTemplatePreview($array['msg'], $array));

        $sent = $this->email->send();
        if (! $sent) {
            $message = $this->emailDebuggerMessage();
            log_message('error', 'Email template send failed for ID '.$id.': '.$message);
            $this->sendEmailJson(FALSE, $message);
            return;
        }

        $this->sendEmailJson(TRUE, 'Email sent successfully.');
    }

    public function mail2() {

        $config = array();
        $config['protocol'] = 'smtp';
        $config['smtp_host'] = 'smtp.gmail.com';
        $config['smtp_user'] = 'sender@example.com';
        $config['smtp_pass'] = 'YOURPASSWORD';
        $config['smtp_port'] = '465'; 
        $config['mailtype'] = 'text';
        $config['smtp_crypto'] = 'ssl';

        $this->load->library('email', $config);

        $this->email->set_newline("\r\n");

    $this->email->to('user@example.com');
    $this->email->from('sender@example.com');
        $this->email->subject('Teste');
        $this->email->message('Teste'); 

                if ($this->email->send()) {
          echo "Email Sent !";
      }
      else {
          log_message('error', 'Sample email send failed: '.$this->email->print_debugger(array('headers')));
      }
  

}

    public function fetchAll($colunm) {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson["data"] = $this->model->fetchAll($colunm);
         echo json_encode($listJobsJson, JSON_PRETTY_PRINT);

     }

    public function fetch($id) {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson["data"] = $this->model->fetch($id);
         if (! empty($listJobsJson["data"])) {
             $template = (array) $listJobsJson["data"][0];
             $listJobsJson["data"][0]->preview_subject = $this->renderTemplatePreview($template['subject'], $template);
             $listJobsJson["data"][0]->preview_msg = $this->renderTemplatePreview($template['msg'], $template);
         }
         echo json_encode($listJobsJson, JSON_PRETTY_PRINT);

     }

     public function fetchSMTP() {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson = $this->model->fetchSMTP();
         echo json_encode($listJobsJson, JSON_PRETTY_PRINT);

     }

     public function fetchXsmtp($id) {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson = $this->model->fetchXsmtp($id);
         echo json_encode($listJobsJson, JSON_PRETTY_PRINT);

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
            if($this->input->method(TRUE) !== 'POST') {
                $this->output->set_status_header(405);
                echo(json_encode(array('status'=>FALSE, 'message'=>'Delete requests must use POST.')));
                return;
            }

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