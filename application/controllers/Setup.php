<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class Setup extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
        $this->guardSetupAccess();
    }

    /**
     * The setup wizard writes application/config/config.json (Jenkins URL and
     * credentials) and opens server-side database connections to whatever host
     * the form supplies. Once the instance has been provisioned it must never be
     * reachable anonymously, and only an administrator may re-run it - otherwise
     * anyone who can load the page could repoint the application at a hostile
     * Jenkins/database or probe the internal network.
     *
     * First-run bootstrap (no users provisioned yet) is still allowed so the
     * very first configuration can happen before any account exists.
     */
    private function guardSetupAccess()
    {
        $config = $this->getRuntimeConfig();
        $locked = ! empty($config->setup) && ! empty($config->setup->enabled);

        if (! $locked) {
            try {
                if ($this->db->table_exists('tbl_users')) {
                    $locked = ((int) $this->db->where('isDeleted', 0)->count_all_results('tbl_users')) > 0;
                }
            } catch (Exception $exception) {
                $locked = FALSE;
            }
        }

        if (! $locked) {
            return;
        }

        $loggedIn = $this->session->userdata('isLoggedIn') === TRUE;
        if (! $loggedIn) {
            if ($this->input->is_ajax_request()) {
                $this->output
                    ->set_status_header(401)
                    ->set_content_type('application/json', 'utf-8')
                    ->set_output(json_encode(array('status' => FALSE, 'error' => 'Authentication required.')));
                exit;
            }
            redirect('login');
            exit;
        }

        if ($this->session->userdata('role') != ROLE_ADMIN) {
            show_error('Setup has already been completed. Only an administrator can change it.', 403);
        }
    }

    public function index()
    {

        $this->global['pageTitle'] = 'Setup Wizard : Welcome';
        
        $this->loadViewsSetup("setup", $this->global, NULL, NULL);
    }

   
    public function database()
    {

        $this->global['pageTitle'] = 'Setup Wizard : Database';
        
        $this->loadViewsSetup("setupDatabase", $this->global, NULL, NULL);
    }

    
    public function jenkins()
    {

        $this->global['pageTitle'] = 'Setup Wizard : Jenkins';

        $jsonToArray = $this->getRuntimeConfig();
        $data = array();

        $data['jenkins_enabled'] = $jsonToArray->jenkins->enabled;
        $data['jenkins_url'] = $jsonToArray->jenkins->url;
        $data['jenkins_username'] = '';
        $data['jenkins_token'] = '';
        $data['jenkins_home'] = $jsonToArray->jenkins->jenkins_home;
        
        $this->loadViewsSetup("setupJenkins", $this->global, $data, NULL);
    }

    public function saveJenkins()
    {
        $this->global['pageTitle'] = 'Setup Wizard : Jenkins';

        $jenkins = $this->input->post('jenkins');
        $username = trim($this->input->post('username'));
        $token = trim($this->input->post('token'));
        $auth = trim($this->input->post('auth'));
        $home = trim($this->input->post('home'));
        $host = trim($this->input->post('url'));
        $port = (int) $this->input->post('port');
        $jenkinsEnabled = ($jenkins == "true");

        $file = array(
           'jenkins' => array(
            'enabled' => $jenkinsEnabled,
            'url' => 'http://'.$host.':'.$port.'/',
            'username' => $username,
            'token' => $token,
            'authorization' => $auth,
            'jenkins_home' => $home
           ),
           'setup' => array(
            'enabled' => true,
            'env' => ''
           ) 
        );

        // Validate if selected using jenkins = true
        if ($jenkinsEnabled) {
            $connected = false;
            $connectionError = '';

            // Test connection with given url and port
            set_error_handler(function($severity, $message) use (&$connectionError) {
                $connectionError = $message;
            });
            $socket = fsockopen($host, $port, $errno, $errstr, 10);
            restore_error_handler();

            if($socket) {
                $connected = true;
                fclose($socket);
                } else {
                $connected = false;
            } 

          if ($connected == false) {
                $this->session->set_flashdata('error', '<b>Bad Connection</b>, The given Jenkins URL and Port was not found.');
                 redirect('setup/jenkins');
            } else {

                if (file_put_contents(JOBSEEKER_CONFIG_PATH, json_encode($file, JSON_PRETTY_PRINT), LOCK_EX) === FALSE) {
                    $this->session->set_flashdata('error', '<b>Config File Error</b>, Unable to write the Jenkins configuration file.');
                    redirect('setup/jenkins');
                }
              $this->session->set_flashdata('success', '<b>Config File Written</b>, The given information has been written to system config file, now able to consume jenkins api.');
                 redirect('Setup/jenkins');  
            }
        } else {
            if (file_put_contents(JOBSEEKER_CONFIG_PATH, json_encode($file, JSON_PRETTY_PRINT), LOCK_EX) === FALSE) {
                $this->session->set_flashdata('error', '<b>Config File Error</b>, Unable to write the Jenkins configuration file.');
                redirect('setup/jenkins');
            }

            $this->session->set_flashdata('success', '<b>Config File Written</b>, Jenkins integration is disabled.');
            redirect('setup/env');
        }
     }

     public function testJenkinsApi()
    {
        $response = $this->requestJenkins('GET', 'api/json?tree=jobs[name,builds[number,actions[parameters[name,value]]]]&pretty=true');

        $this->output
            ->set_status_header($response['status'])
            ->set_content_type($response['content_type'])
            ->set_output($response['body']);
    }

     public function env()
    {

        $this->global['pageTitle'] = 'Setup Wizard : Enviroment';
        
        $this->loadViewsSetup("setupEnv", $this->global, NULL, NULL);
    }

    public function databaseCheck()
    {
        if($this->input->method(TRUE) !== 'POST') {
            redirect('setup/database');
            return;
        }

        $engine = trim((string) $this->input->post('engine'));
        $host = trim((string) $this->input->post('host'));
        $schema = trim((string) $this->input->post('schema'));
        $username = trim((string) $this->input->post('username'));
        $password = (string) $this->input->post('password');
        $charset = trim((string) $this->input->post('charset'));
        $dbcol = trim((string) $this->input->post('dbcol'));

        if($engine === '' || $host === '' || $schema === '' || $username === '' || $charset === '' || $dbcol === '') {
            $this->session->set_flashdata('error', 'Please fill all required database connection fields.');
            redirect('setup/database');
            return;
        }

        $config['hostname'] = $host;
        $config['username'] = $username;
        $config['password'] = $password;
        $config['database'] = $schema;
        $config['dbdriver'] = $engine;
        $config['dbprefix'] = '';
        $config['pconnect'] = FALSE;
        $config['db_debug'] = FALSE;
        $config['cache_on'] = FALSE;
        $config['cachedir'] = '';
        $config['char_set'] = $charset;
        $config['dbcollat'] = $dbcol;

        $db = $this->load->database($config, TRUE);
        $connected = is_object($db) && ! empty($db->conn_id);

        if(is_object($db)) {
            $db->close();
        }

        if($connected) {
            $this->session->set_flashdata('success', 'Database connection succeeded.');
        } else {
            $this->session->set_flashdata('error', 'Unable to connect to the database with the provided settings.');
        }

        redirect('setup/database');
    }

     
}

?>