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

        $loadJson = file_get_contents('/var/www/html/application/config/config.json');
        $jsonToArray = json_decode($loadJson);
        $data = array();

        $data['jenkins_enabled'] = $jsonToArray->jenkins->enabled;
        $data['jenkins_url'] = $jsonToArray->jenkins->url;
        $data['jenkins_username'] = $jsonToArray->jenkins->username;
        $data['jenkins_token'] = $jsonToArray->jenkins->token;
        $data['jenkins_authorization'] = $jsonToArray->jenkins->authorization;
        $data['jenkins_home'] = $jsonToArray->jenkins->jenkins_home;
        
        $this->loadViewsSetup("setupJenkins", $this->global, $data, NULL);
    }

    public function saveJenkins()
    {
        $this->global['pageTitle'] = 'Setup Wizard : Jenkins';

        $jenkins = $this->input->post('jenkins');
        $username = $this->input->post('username');
        $token = $this->input->post('token');
        $auth = $this->input->post('auth');
        $home = $this->input->post('home');
        $url = $this->input->post('url');
        $port = $this->input->post('port');

        $file = array(
           'jenkins' => array(
            'enabled' => true,
            'url' => 'http://'.$url.':'.$port.'/',
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

        print_r($file);
        echo '<Br><hr><Br>';

        // Validate if selected using jenkins = true
        if ($jenkins == "true") {
            $connected = false;

            // Test connection with given url and port
            if($socket =@ fsockopen('tcp://'.$host, $port, $errno, $errstr, 30)) {
                $connected = true;
                fclose($socket);
                } else {
                $connected = false;
            } 

          if ($connected == false) {
                $this->session->set_flashdata('error', '<b>Bad Connection</b>, The given Jenkins URL and Port was not found.');
                 redirect('setup/jenkins');
            } else {

               $fp = fopen('application/config/config.json', 'w');
                fwrite($fp, json_encode($file,JSON_PRETTY_PRINT));
                fclose($fp);
              $this->session->set_flashdata('success', '<b>Config File Written</b>, The given information has been written to system config file, now able to consume jenkins api.');
                 redirect('Setup/jenkins');  
            }      
        }
     }

     public function env()
    {

        $this->global['pageTitle'] = 'Setup Wizard : Enviroment';
        
        $this->loadViewsSetup("setupEnv", $this->global, NULL, NULL);
    }

    public function databaseCheck()
    {
       $engine = $this->input->post('engine');
       $host = $this->input->post('host');
       $schema = $this->input->post('schema');
       $username = $this->input->post('username');
       $password = $this->input->post('password');
       $charset = $this->input->post('charset');
       $dbcol = $this->input->post('dbcol'); 

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
        $this->load->database($config);

        if ( $this->load->database() === FALSE )
        {
           exit('THE END IS NIGH!');
        }

       // print_r($config);

    }

     
}

?>