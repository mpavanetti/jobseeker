<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class jobCreation extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
     //   $this->load->model('files_model');
        $this->isLoggedIn();   
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : Job Creation';

     //   $data["GetJobs"] = $fetchObj;
        
        $this->loadViews("jobCreation", $this->global, NULL, NULL);
    }

    public function send() {

        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {

            $this->load->library('form_validation');

            // Basic inputs
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[50]');
            $this->form_validation->set_rules('description','Database Type','trim|required|max_length[5000]');

            // Confirmation Flag
            $this->form_validation->set_rules('confirmation','Confirmation','trim|required|max_length[1]');

            // Timestamp Option
            $this->form_validation->set_rules('timestamp','Timestamp','trim|max_length[1]');

            //Abort Build
            $this->form_validation->set_rules('abort','Abort','trim|max_length[1]');
            $this->form_validation->set_rules('timeoutStrategy','Time Out Stragegy','trim|max_length[50]');
            $this->form_validation->set_rules('timeoutMinutes','Time Out in Minutes','trim|max_length[50]');
            $this->form_validation->set_rules('timeoutSeconds','Time Out in Seconds','trim|max_length[50]');


            if($this->form_validation->run() == FALSE)
            {
                $this->index();
            }
            else
            {
                // Basic Inputs
                $job_name = $this->security->xss_clean($this->input->post('job_name'));
                $description = $this->security->xss_clean($this->input->post('description')); 

                // Confirmation Checkbox
                $confirmation = $this->security->xss_clean($this->input->post('confirmation'));

                // Timestamp Checkbox
                $timestamp = $this->security->xss_clean($this->input->post('timestamp'));

                // Abort the build Checkbox
                $abort = $this->security->xss_clean($this->input->post('abort'));
                $timeoutStrategy = $this->security->xss_clean($this->input->post('timeoutStrategy'));
                $timeoutMinutes = $this->security->xss_clean($this->input->post('timeoutMinutes'));
                $timeoutSeconds = $this->security->xss_clean($this->input->post('timeoutSeconds'));
                
                $Info = array(
                    'job_name'=>$job_name, 
                    'description'=>$description, 
                    'confirmation' => $confirmation,
                    'timestamp' => $timestamp,
                    'abort' => $abort,
                    'timeoutStrategy' => $timeoutStrategy,
                    'timeoutMinutes' => $timeoutMinutes,
                    'timeoutSeconds' => $timeoutMinutes,
                    'creation_date'=> date('Y-m-d H:i:s'),
                    'owner'=> $this->name
                );


                print_r($Info);


                echo '<br><br><hr><br>';

               

                $dom = new DOMDocument();

                $dom->encoding = 'UTF-8';

                $dom->xmlVersion = '1.1';

                $dom->formatOutput = true;

                $xml_file_name = 'config.xml';

                $root = $dom->createElement('project');

              //  $movie_node = $dom->createElement('movie');

               // $attr_movie_id = new DOMAttr('movie_id', '5467');

              //  $movie_node->setAttributeNode($attr_movie_id);

                $node_description = $dom->createElement('description', $description);

                $root->appendChild($node_description);


                // Create Trigger Elements
                $triggers = $dom->createElement('triggers');
                $hudson_triggers = $dom->createElement('hudson.triggers.TimerTrigger');
                $spec = $dom->createElement('spec', 'H/15 * * * *');
                $hudson_triggers->appendChild($spec);  
                $triggers->appendChild($hudson_triggers);    
                $root->appendChild($triggers);


                // Create buildWrappers Elements
                $buildWrappers = $dom->createElement('buildWrappers');

                // If option to add timestamp is enabled then
                if($timestamp == 1) {
                $hudson_plugins_timestamper = $dom->createElement('hudson.plugins.timestamper.TimestamperBuildWrapper');
                $attr_hudson_timestamper = new DOMAttr('plugin', 'timestamper@1.10');
                $hudson_plugins_timestamper->setAttributeNode($attr_hudson_timestamper);
                $buildWrappers->appendChild($hudson_plugins_timestamper);
                }


                // Abort Build if Stucks Option if enabled then
                if ($abort == 1){
                 $hudson_plugins_timeout = $dom->createElement('hudson.plugins.build__timeout.BuildTimeoutWrapper');
                 $attr_hudson_plugins_timeout = new DOMAttr('plugin', 'build-timeout@1.19');
                 $hudson_plugins_timeout->setAttributeNode($attr_hudson_plugins_timeout);

                 if ($timeoutStrategy == 'absolute') { // if absolute then

                 $strategy = $dom->createElement('strategy');
                 $attr_stategy = new DOMAttr('class', 'hudson.plugins.build_timeout.impl.AbsoluteTimeOutStrategy');
                 $strategy->setAttributeNode($attr_stategy);

                 $timeoutMinutes_node = $dom->createElement('timeoutMinutes', $timeoutMinutes);
                 $strategy->appendChild($timeoutMinutes_node);
                 $hudson_plugins_timeout->appendChild($strategy);
                } else { // if not absolute then

                 $strategy = $dom->createElement('strategy');
                 $attr_stategy = new DOMAttr('class', 'hudson.plugins.build_timeout.impl.NoActivityTimeOutStrategy');
                 $strategy->setAttributeNode($attr_stategy);
                 $timeoutSeconds_node = $dom->createElement('timeoutSecondsString', $timeoutSeconds);
                 $strategy->appendChild($timeoutSeconds_node);
                 $hudson_plugins_timeout->appendChild($strategy);

                }

                 $operationList = $dom->createElement('operationList');
                 $hudson_plugins_abort = $dom->createElement('hudson.plugins.build__timeout.operations.AbortOperation');
                 $operationList->appendChild($hudson_plugins_abort);
                 $hudson_plugins_timeout->appendChild($operationList);
                 $buildWrappers->appendChild($hudson_plugins_timeout);
                }
                // End Abort Build if Stucks Option



                $root->appendChild($buildWrappers);

                // Append document to root node
                $dom->appendChild($root);
                // Save XML file
                $dom->save('xml/'.$xml_file_name);
                // Read XML File to obtain xml text
                $content = htmlentities(file_get_contents('xml/'.$xml_file_name));

                // Create flash data to send to view
                 $this->session->set_flashdata('xml', $content);
                 $this->session->set_flashdata('job_name', $job_name);
                 $this->session->set_flashdata('success', 'Your XML File has been successfully created !');


                redirect('jobCreation');

            }

        }


    }

    public function readXML() {

        header("Content-Type: text/xml");
        $content = file_get_contents("xml/config.xml");
        echo $content;

    }

}