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
        $this->model->ensureLocalDefaultSetting();
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
        $data["default_smtp"] = $this->model->defaultEnabledSetting();
        $data["jenkins_mailer_sync"] = $this->syncJenkinsMailerFromDefault(FALSE);
        $data["mailpit_url"] = $this->mailpitPublicUrl();
        
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
            $this->form_validation->set_rules('is_enabled','Enabled');
            $this->form_validation->set_rules('is_default','Default');
            $this->form_validation->set_rules('smtp_host','Smtp Host','trim|required|max_length[128]');
            $this->form_validation->set_rules('smtp_port','Smtp Port','trim|required|integer|max_length[30]');
            $this->form_validation->set_rules('username','Username','trim|max_length[128]');
            $this->form_validation->set_rules('password','Password','trim|max_length[128]');
            $this->form_validation->set_rules('reply_to','Reply To','trim|required|valid_email|max_length[200]');
            $this->form_validation->set_rules('description','Description','trim|required|max_length[200]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addSetting();
            }
            else
            {
                $ssl = $this->input->post('ssl') == '1' ? 1 : 0;
                $is_enabled = $this->input->post('is_enabled') == '1' ? 1 : 0;
                $is_default = $this->input->post('is_default') == '1' ? 1 : 0;
                $name = $this->security->xss_clean($this->input->post('name'));
                $smtp_host = $this->security->xss_clean($this->input->post('smtp_host'));
                $smtp_port = $this->security->xss_clean($this->input->post('smtp_port'));
                $username = $this->security->xss_clean($this->input->post('username'));
                $password = $this->security->xss_clean($this->input->post('password'));
                $reply_to = $this->security->xss_clean($this->input->post('reply_to'));
                $description = $this->security->xss_clean($this->input->post('description'));

                if($is_default == 1) {
                    $is_enabled = 1;
                }


                // Check if the data is alredy on table
                 $validateSetting = $this->model->validateSetting($name, $smtp_host, $smtp_port);


                 $Info = array(
                    'name'=> $name, 
                    'smtp_host'=> $smtp_host, 
                    'smtp_port' => $smtp_port,
                    'username' => $username,
                    'ssl' => $ssl,
                    'password' => $password,
                    'is_enabled' => $is_enabled,
                    'is_default' => $is_default,
                    'reply_to' => $reply_to,
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
                    $this->syncJenkinsMailerFromDefault(FALSE);
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
            $this->form_validation->set_rules('is_enabled','Enabled');
            $this->form_validation->set_rules('is_default','Default');
            $this->form_validation->set_rules('name','Smtp Name','trim|required|max_length[30]');
            $this->form_validation->set_rules('smtp_host','Smtp Host','trim|required|max_length[128]');
            $this->form_validation->set_rules('smtp_port','Smtp Port','trim|required|integer|max_length[30]');
            $this->form_validation->set_rules('username','Username','trim|max_length[128]');
            $this->form_validation->set_rules('password','Password','trim|max_length[128]');
            $this->form_validation->set_rules('reply_to','Reply To','trim|required|valid_email|max_length[200]');
            $this->form_validation->set_rules('description','Description','trim|required|max_length[200]');

            if($this->form_validation->run() == FALSE)
            {
                $this->addSetting();
            }
            else
            {

                $id = $this->security->xss_clean($this->input->post('id'));
                $ssl = $this->input->post('ssl') == '1' ? 1 : 0;
                $is_enabled = $this->input->post('is_enabled') == '1' ? 1 : 0;
                $is_default = $this->input->post('is_default') == '1' ? 1 : 0;
                $name = $this->security->xss_clean($this->input->post('name'));
                $smtp_host = $this->security->xss_clean($this->input->post('smtp_host'));
                $smtp_port = $this->security->xss_clean($this->input->post('smtp_port'));
                $username = $this->security->xss_clean($this->input->post('username'));
                $password = $this->security->xss_clean($this->input->post('password'));
                $reply_to = $this->security->xss_clean($this->input->post('reply_to'));
                $description = $this->security->xss_clean($this->input->post('description'));

                if($is_default == 1) {
                    $is_enabled = 1;
                }



                 $Info = array(
                    'name'=> $name, 
                    'smtp_host'=> $smtp_host, 
                    'smtp_port' => $smtp_port,
                    'username' => $username,
                    'password' => $password,
                    'ssl' => $ssl,
                    'is_enabled' => $is_enabled,
                    'is_default' => $is_default,
                    'reply_to' => $reply_to,
                    'description' => $description,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                 );

                
                $result = $this->model->updateSetting($Info, $id);
                
                if($result > 0)
                {
                    $this->syncJenkinsMailerFromDefault(FALSE);
                    $this->session->set_flashdata('success', 'New Smtp Setting has successfully updated.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Smtp Setting update failed !');
                }


              redirect('SmtpSettings');

            }
           
        }


    }

 

    public function syncJenkinsMailer() {

        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->syncJenkinsMailerFromDefault(TRUE);
            redirect('SmtpSettings');
        }
    }

    private function syncJenkinsMailerFromDefault($flash)
    {
        $setting = $this->model->defaultEnabledSetting();

        if(empty($setting)) {
            $message = 'No enabled SMTP setting is available to configure Jenkins Mailer.';
            log_message('error', $message);

            if($flash) {
                $this->session->set_flashdata('error', $message);
            }

            return array('ok' => FALSE, 'message' => $message);
        }

        $script = $this->jenkinsMailerGroovyScript($setting);
        $response = $this->requestJenkins('POST', 'scriptText', http_build_query(array('script' => $script)), 'application/x-www-form-urlencoded');
        $ok = isset($response['status']) && (int) $response['status'] >= 200 && (int) $response['status'] < 300 && $this->jenkinsMailerMatchesSetting($setting);

        if($ok) {
            $message = 'Jenkins Mailer synced to ' . $setting->name . ' (' . $setting->smtp_host . ':' . $setting->smtp_port . ').';

            if($flash) {
                $this->session->set_flashdata('success', $message);
            }

            return array('ok' => TRUE, 'message' => $message, 'setting' => $setting);
        }

        $message = 'Unable to sync Jenkins Mailer from SMTP settings.';
        log_message('error', $message . ' Jenkins response status: ' . (isset($response['status']) ? $response['status'] : 'unknown') . ' body: ' . (isset($response['body']) ? $response['body'] : ''));

        if($flash) {
            $this->session->set_flashdata('error', $message);
        }

        return array('ok' => FALSE, 'message' => $message, 'response' => $response);
    }

    private function jenkinsMailerMatchesSetting($setting)
    {
        $script = "def instance = jenkins.model.Jenkins.get()\n" .
            "def mailer = instance.getDescriptorByType(hudson.tasks.Mailer.DescriptorImpl.class)\n" .
            "def ext = instance.getDescriptor('hudson.plugins.emailext.ExtendedEmailPublisher')\n" .
            "def location = jenkins.model.JenkinsLocationConfiguration.get()\n" .
            "println('smtpHost=' + mailer.smtpHost)\n" .
            "println('smtpPort=' + mailer.smtpPort)\n" .
            "println('useSsl=' + mailer.useSsl)\n" .
            "println('replyTo=' + mailer.replyToAddress)\n" .
            "println('emailExtPresent=' + (ext != null))\n" .
            "if (ext != null) {\n" .
            "  println('emailExtSmtpHost=' + ext.smtpServer)\n" .
            "  println('emailExtSmtpPort=' + ext.smtpPort)\n" .
            "  println('emailExtUseSsl=' + ext.useSsl)\n" .
            "  println('emailExtReplyTo=' + ext.defaultReplyTo)\n" .
            "}\n" .
            "println('jenkinsUrl=' + location.url)\n" .
            "println('adminAddress=' + location.adminAddress)\n";
        $response = $this->requestJenkins('POST', 'scriptText', http_build_query(array('script' => $script)), 'application/x-www-form-urlencoded');

        if(!isset($response['status']) || (int) $response['status'] < 200 || (int) $response['status'] >= 300) {
            return FALSE;
        }

        $actual = array();
        foreach(preg_split('/\r\n|\r|\n/', trim($response['body'])) as $line) {
            $parts = explode('=', $line, 2);
            if(count($parts) === 2) {
                $actual[trim($parts[0])] = trim($parts[1]);
            }
        }

        return isset($actual['smtpHost'], $actual['smtpPort'], $actual['useSsl'], $actual['replyTo'], $actual['emailExtPresent'], $actual['emailExtSmtpHost'], $actual['emailExtSmtpPort'], $actual['emailExtUseSsl'], $actual['emailExtReplyTo'], $actual['jenkinsUrl'], $actual['adminAddress'])
            && $actual['smtpHost'] === (string) $setting->smtp_host
            && $actual['smtpPort'] === (string) $setting->smtp_port
            && $actual['useSsl'] === (((int) $setting->ssl === 1) ? 'true' : 'false')
            && $actual['replyTo'] === $this->smtpReplyTo($setting)
            && $actual['emailExtPresent'] === 'true'
            && $actual['emailExtSmtpHost'] === (string) $setting->smtp_host
            && $actual['emailExtSmtpPort'] === (string) $setting->smtp_port
            && $actual['emailExtUseSsl'] === (((int) $setting->ssl === 1) ? 'true' : 'false')
            && $actual['emailExtReplyTo'] === $this->smtpReplyTo($setting)
            && $actual['jenkinsUrl'] === $this->jenkinsPublicUrl()
            && $actual['adminAddress'] === $this->smtpReplyTo($setting);
    }

    private function jenkinsMailerGroovyScript($setting)
    {
        $smtpHost = $this->groovyString($setting->smtp_host);
        $smtpPort = $this->groovyString((string) $setting->smtp_port);
        $smtpUsername = $this->groovyString((string) $setting->username);
        $smtpPassword = $this->groovyString((string) $setting->password);
        $replyTo = $this->groovyString($this->smtpReplyTo($setting));
        $jenkinsUrl = $this->groovyString($this->jenkinsPublicUrl());
        $useSsl = ((int) $setting->ssl === 1) ? 'true' : 'false';

        return "import jenkins.model.Jenkins\n" .
            "import hudson.tasks.Mailer\n" .
            "def instance = Jenkins.get()\n" .
            "def smtpHost = " . $smtpHost . "\n" .
            "def smtpPort = " . $smtpPort . "\n" .
            "def smtpUsername = " . $smtpUsername . "\n" .
            "def smtpPassword = " . $smtpPassword . "\n" .
            "def replyTo = " . $replyTo . "\n" .
            "def jenkinsUrl = " . $jenkinsUrl . "\n" .
            "def useSsl = " . $useSsl . "\n" .
            "def location = jenkins.model.JenkinsLocationConfiguration.get()\n" .
            "location.setAdminAddress(replyTo)\n" .
            "location.setUrl(jenkinsUrl)\n" .
            "location.save()\n" .
            "def mailer = instance.getDescriptorByType(Mailer.DescriptorImpl.class)\n" .
            "mailer.smtpHost = smtpHost\n" .
            "mailer.smtpPort = smtpPort\n" .
            "mailer.useSsl = useSsl\n" .
            "mailer.useTls = false\n" .
            "mailer.charset = 'UTF-8'\n" .
            "mailer.replyToAddress = replyTo\n" .
            "mailer.setSmtpAuth(smtpUsername, smtpPassword)\n" .
            "mailer.save()\n" .
            "def ext = instance.getDescriptor('hudson.plugins.emailext.ExtendedEmailPublisher')\n" .
            "if (ext != null) {\n" .
            "  ext.smtpServer = smtpHost\n" .
            "  ext.smtpPort = smtpPort\n" .
            "  ext.useSsl = useSsl\n" .
            "  ext.charset = 'UTF-8'\n" .
            "  ext.defaultReplyTo = replyTo\n" .
            "  ext.setSmtpAuth(smtpUsername, smtpPassword)\n" .
            "  ext.save()\n" .
            "}\n" .
            "instance.save()\n" .
            "println('OK SMTP configured: ' + smtpHost + ':' + smtpPort)\n";
    }

    private function groovyString($value)
    {
        return json_encode((string) $value);
    }

    private function smtpReplyTo($setting)
    {
        if(isset($setting->reply_to) && trim($setting->reply_to) !== '') {
            return trim($setting->reply_to);
        }

        if(isset($setting->username) && filter_var($setting->username, FILTER_VALIDATE_EMAIL)) {
            return trim($setting->username);
        }

        return 'jobseeker@local.test';
    }

    private function jenkinsPublicUrl()
    {
        $url = getenv('JOBSEEKER_JENKINS_PUBLIC_URL');

        if($url === FALSE || trim($url) === '') {
            $config = $this->getRuntimeConfig();
            $url = isset($config->jenkins->url) ? $config->jenkins->url : 'http://localhost:8080/';
        }

        return rtrim(trim($url), '/') . '/';
    }

    private function mailpitPublicUrl()
    {
        $url = getenv('JOBSEEKER_MAILPIT_PUBLIC_URL');

        if($url !== FALSE && trim($url) !== '') {
            return rtrim(trim($url), '/') . '/';
        }

        $port = getenv('JOBSEEKER_MAILPIT_HTTP_PORT');
        if($port === FALSE || trim($port) === '') {
            $port = '8025';
        }

        $scheme = 'http';
        if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https' ? 'https' : 'http';
        } else if(!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            $scheme = 'https';
        }

        $host = 'localhost';
        if(!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
        } else if(!empty($_SERVER['HTTP_HOST'])) {
            $host = trim($_SERVER['HTTP_HOST']);
        } else if(!empty($_SERVER['SERVER_NAME'])) {
            $host = trim($_SERVER['SERVER_NAME']);
        }

        $hostWithoutPort = preg_replace('/:\d+$/', '', $host);

        if(preg_match('/^(.+)-\d+(\..+)$/', $hostWithoutPort, $matches) && preg_match('/github|githubpreview/i', $matches[2])) {
            return $scheme . '://' . $matches[1] . '-' . trim($port) . $matches[2] . '/';
        }

        return $scheme . '://' . $hostWithoutPort . ':' . trim($port) . '/';
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
            $this->syncJenkinsMailerFromDefault(FALSE);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }
    
}

?>