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


    public function do_upload($val,$job_name) {

      $this->global['pageTitle'] = 'Job Seeker : Upload';

     

    $ds = DIRECTORY_SEPARATOR;  //1
 
    $storeFolder = '../../repository/'.$val.'/jobs/'; 


    if (!file_exists($storeFolder)) {
     mkdir($storeFolder);
    } 

    if (!empty($_FILES)) {
         
        $tempFile = $_FILES['file']['tmp_name'];          //3             
          
        $targetPath = dirname( __FILE__ ) . $ds. $storeFolder . $ds;  //4
         
        $targetFile =  $targetPath. $_FILES['file']['name'];  //5
     
        move_uploaded_file($tempFile,$targetFile); //6

        // assuming file.zip is in the same directory as the executing script.
   

          $zip = new ZipArchive;
          $res = $zip->open($targetFile);
          if ($res === TRUE) {
            // extract it to the path we determined above
            $zip->extractTo($targetPath.$job_name);
            $zip->close();
            echo "WOOT! $file extracted to $path";
            unlink($targetFile);
          } else {
            echo "Doh! I couldn't open $file";
          }

        }

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

            // Trigger Build Periodically Option
            $this->form_validation->set_rules('checkBuild','Check Build','trim|max_length[1]');


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


                // Trigger Build Periodically Option 
                 $checkBuild = $this->security->xss_clean($this->input->post('checkBuild'));
                $action = $this->security->xss_clean($this->input->post('action'));

                // Single Build Options
                $singleMinute = $this->input->post('singleMinute');
                $singleHour = $this->input->post('singleHour');
                $singleDayOfMonth = $this->input->post('singleDayOfMonth');
                $singleMonth = $this->input->post('singleMonth');
                $singleDayOfWeek = $this->input->post('singleDayOfWeek');

                // Execute a Windows Command Option

                // Start Windows File Upload
                $executionStrategy = $this->input->post('executionStrategy');
                $scriptType = $this->input->post('scriptType');
                $windowsCommandLine = $this->input->post('windowsCommandLine');

                if($executionStrategy == 'script'){
                  if($scriptType == 'talend'){
                          $filelist = glob("repository/".$scriptType."/jobs/".$job_name."/*");
                          $file = glob($filelist[0].'/*.bat');
                          $filePath = realpath($file[0]);
                          echo 'WINDOWS - TALEND File Path: <b>'.$filePath.'</b>';
                          echo '<hr><br>';
                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('jobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                echo "The file $filePath does not exists";
                            }
                          }
                  } else if ($scriptType == 'batch') {
                        $filelist = glob("repository/".$scriptType."/jobs/".$job_name."/*.bat");
                          $file = glob($filelist[0]);
                          $filePath = realpath($file[0]);
                          echo 'WINDOWS - BATCH File Path: <b>'.$filePath.'</b>';
                          echo '<hr><br>';
                          // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('jobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                echo "The file $filePath does not exists";
                            }
                          } 
                           
                  } else if ($scriptType == 'python') {
                        $filelist = glob("repository/".$scriptType."/jobs/".$job_name."/*.py");
                          $file = glob($filelist[0]);
                          $filePath = realpath($file[0]);
                          echo 'WINDOWS - PYTHON File Path: <b>'.$filePath.'</b>';
                          echo '<hr><br>';

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('jobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                echo "The file $filePath does not exists";
                            }
                          }
                  }
                } else if ($executionStrategy == 'command'){

                  $filePath = $windowsCommandLine;
                  
                }
                // END Windows File Upload

                // Start Windows File Upload
                $linuxExecutionStrategy = $this->input->post('linuxExecutionStrategy');
                $linuxScriptType = $this->input->post('linuxScriptType');
                $linuxCommandLine = $this->input->post('linuxCommandLine');

                if($linuxExecutionStrategy == 'script'){
                  if($linuxScriptType == 'talend'){
                          $filelist = glob("repository/".$linuxScriptType."/jobs/".$job_name."/*");
                          $file = glob($filelist[0].'/*.sh');
                          $filePath = realpath($file[0]);

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('jobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                echo "The file $filePath does not exists";
                            }
                          }

                          $filePath = '.'.$filePath;
                          echo 'LINUX - TALEND File Path: <b>'.$filePath.'</b>';
                          echo '<hr><br>';
                  } else if ($linuxScriptType == 'bash') {
                        $filelist = glob("repository/".$linuxScriptType."/jobs/".$job_name."/*.sh");
                          $file = glob($filelist[0]);
                          $filePath = realpath($file[0]);

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('jobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                echo "The file $filePath does not exists";
                            }
                          }

                          $filePath = '.'.$filePath;
                          echo 'LINUX - BASH File Path: <b>'.$filePath.'</b>';
                          echo '<hr><br>';
                  } else if ($linuxScriptType == 'python') {
                        $filelist = glob("repository/".$linuxScriptType."/jobs/".$job_name."/*.py");
                          $file = glob($filelist[0]);
                          $filePath = realpath($file[0]);

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('jobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                echo "The file $filePath does not exists";
                            }
                          }

                          $filePath = '.'.$filePath;
                          echo 'LINUX - PYTHON File Path: <b>'.$filePath.'</b>';
                          echo '<hr><br>';
                  }
                } else if ($linuxExecutionStrategy == 'command'){

                  $filePath = $linuxCommandLine;  
                  
                }

               
                // END Linux File Upload

                
                // Validation if nothing comes null
                if ($singleMinute == null || $singleHour == null || $singleDayOfMonth == null || $singleMonth == null || $singleDayOfWeek == null){

                  $this->session->set_flashdata('error', 'You missed to select one field value for Build Periodically function');
                    redirect('jobCreation');
                }

                 // Repetitive Build Options
                $repetitiveMinute = $this->security->xss_clean($this->input->post('repetitiveMinute'));
                $repetitiveHour = $this->security->xss_clean($this->input->post('repetitiveHour'));
                $repetitiveDayOfMonth = $this->security->xss_clean($this->input->post('repetitiveDayOfMonth'));
                $repetitiveMonth = $this->security->xss_clean($this->input->post('repetitiveMonth'));
                $repetitiveDayOfWeek = $this->security->xss_clean($this->input->post('repetitiveDayOfWeek'));

                // Tag Build Option
                $tag = $this->security->xss_clean($this->input->post('tag'));


                // Abort the build Checkbox
                $abort = $this->security->xss_clean($this->input->post('abort'));
                $timeoutStrategy = $this->security->xss_clean($this->input->post('timeoutStrategy'));
                $timeoutMinutes = $this->security->xss_clean($this->input->post('timeoutMinutes'));
                $timeoutSeconds = $this->security->xss_clean($this->input->post('timeoutSeconds'));
                

                  $test = $this->input->post('test');

                //Array of Information.
                $Info = array(
                    'job_name'=>$job_name, 
                    'description'=>$description, 

                    'confirmation' => $confirmation,

                    //Add timestamp Option
                    'timestamp' => $timestamp,

                    // Abort Build Option
                    'abort' => $abort,
                    'timeoutStrategy' => $timeoutStrategy,
                    'timeoutMinutes' => $timeoutMinutes,
                    'timeoutSeconds' => $timeoutMinutes,

                    // Build Job Periodically
                    'checkBuild' => $checkBuild,
                    'action' => $action,

                    // Build Job Periodically - Single Build Option
                    'singleMinute' => $singleMinute,
                    'singleHour' => $singleHour,
                    'singleDayOfMonth' => $singleDayOfMonth,
                    'singleMonth' => $singleMonth,
                    'singleDayOfWeek' => $singleDayOfWeek,

                    // Build Job Periodically - Repetitive Build Option
                    'repetitiveMinute' => $repetitiveMinute,
                    'repetitiveHour' => $repetitiveHour,
                    'repetitiveDayOfMonth' => $repetitiveDayOfMonth,
                    'repetitiveMonth' => $repetitiveMonth,
                    'repetitiveDayOfWeek' => $repetitiveDayOfWeek,

                    // Build Job Periodically - Tag Build Option
                    'tag' => $tag,

                    'creation_date'=> date('Y-m-d H:i:s'),
                    'owner'=> $this->name
                );


                print_r($Info);


                echo '<br><br><hr><br>';

               
               
                // Array to String Conversion Section
                $singleMinuteString = rtrim(implode(',', $singleMinute), ',');
                $singleHourString = rtrim(implode(',', $singleHour), ',');
                $singleDayOfMonthString = rtrim(implode(',', $singleDayOfMonth), ',');
                $singleMonthString = rtrim(implode(',', $singleMonth), ',');
                $singleDayOfWeekString = rtrim(implode(',', $singleDayOfWeek), ',');
                // Array to String Conversion Section



                // XMl Creation Node Section

                $dom = new DOMDocument();

                $dom->encoding = 'UTF-8';

                $dom->xmlVersion = '1.1';

                $dom->formatOutput = true;

                $xml_file_name = 'config.xml';

                $root = $dom->createElement('project');

                $node_description = $dom->createElement('description', $description);

                $root->appendChild($node_description);


                // Create Trigger Elements
                if($checkBuild == 1){ // If Build Periodically Build is selected then

                  // If Single Build option is selected then
                    if($action == "single") {
                      $triggers = $dom->createElement('triggers');
                      $hudson_triggers = $dom->createElement('hudson.triggers.TimerTrigger');
                      $spec = $dom->createElement('spec', $singleMinuteString.' '.$singleHourString.' '.$singleDayOfMonthString.' '.$singleMonthString.' '.$singleDayOfWeekString);
                      $hudson_triggers->appendChild($spec);  
                      $triggers->appendChild($hudson_triggers);    
                      $root->appendChild($triggers);
                  }

                  // If Repetitive Build option is selected then
                  if($action == "repetitive") {
                     $triggers = $dom->createElement('triggers');
                      $hudson_triggers = $dom->createElement('hudson.triggers.TimerTrigger');
                      $spec = $dom->createElement('spec', 'H/'.$repetitiveMinute.' '.$repetitiveHour.' '.$repetitiveDayOfMonth.' '.$repetitiveMonth.' '.$repetitiveDayOfWeek);
                      $hudson_triggers->appendChild($spec);  
                      $triggers->appendChild($hudson_triggers);    
                      $root->appendChild($triggers);
                  }

                  // If Single Tags option is selected then
                  if($action == "tags") {
                     $triggers = $dom->createElement('triggers');
                      $hudson_triggers = $dom->createElement('hudson.triggers.TimerTrigger');
                      $spec = $dom->createElement('spec', $tag);
                      $hudson_triggers->appendChild($spec);  
                      $triggers->appendChild($hudson_triggers);    
                      $root->appendChild($triggers);
                  }
                }

                // Create builders Elements
                $builders = $dom->createElement('builders');

                // Windows Script Execution
                if($executionStrategy == 'script' && $scriptType != "0" || $executionStrategy == 'command'){

                  $hudson_task_BatchFile = $dom->createElement('hudson.tasks.BatchFile');
                  $command = $dom->createElement('command', $filePath);
                  $hudson_task_BatchFile->appendChild($command);
                  $builders->appendChild($hudson_task_BatchFile);
                  
                }

                // Linux Script Execution
                if($linuxExecutionStrategy == 'script' && $linuxScriptType != "0" || $linuxExecutionStrategy == 'command'){

                  $hudson_task_BashFile = $dom->createElement('hudson.tasks.Shell');
                  $command = $dom->createElement('command', $filePath);
                  $hudson_task_BashFile->appendChild($command);
                  $builders->appendChild($hudson_task_BashFile);
                  
                }

                // Append Builders to root node
                $root->appendChild($builders);


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