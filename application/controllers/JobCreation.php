<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class JobCreation extends BaseController
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
        
        $this->loadViews("jobCreation", $this->global, NULL, NULL);

    }


    public function do_upload($val,$job_name) {

      header('Content-Type: text/html; charset=utf-8');

      $this->global['pageTitle'] = 'Job Seeker : Upload';
      $jenkins_home = $this->global['jenkins_home'];
      
     $ds = DIRECTORY_SEPARATOR;  

      // Check if jenkins home variable exist
     if($jenkins_home === '' || $jenkins_home === null){
      $storeFolder = '../../repository/'.$val.'/jobs/'; 

     } else {

      $storeFolder = $jenkins_home.'/repository/'.$val.'/jobs/';
      
     }


    if (!empty($_FILES)) {
      echo "File Found";
         
        $tempFile = $_FILES['file']['tmp_name'];          //3             
          
        $targetPath = dirname( __FILE__ ) . $ds. $storeFolder . $ds;  //4
         
        $targetFile =  $targetPath. $_FILES['file']['name'];  //5
     
        move_uploaded_file($tempFile,$targetFile); //6

          $zip = new ZipArchive;
          $res = $zip->open($targetFile);
          if ($res === TRUE) {
            // extract it to the path we determined above
            $zip->extractTo($targetPath.$job_name);
            $zip->close();
             echo "WOOT! $tempFile extracted to $targetFile";
            unlink($targetFile);
          } else {
             echo "Doh! I couldn't open ";
          }

        } else {
          echo "No FIle";
        }

      }


    public function send() {

        if($this->isManager() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            header('Content-Type: text/html; charset=utf-8');

            $this->load->library('form_validation');

            // Basic inputs
            $this->form_validation->set_rules('job_name','Job Name','trim|required|max_length[50]');
            $this->form_validation->set_rules('description','Database Type','trim|required|max_length[5000]');

      
            // Abort Build
            $this->form_validation->set_rules('timeoutMinutes','Time Out in Minutes','trim|max_length[50]');
            $this->form_validation->set_rules('timeoutSeconds','Time Out in Seconds','trim|max_length[50]');

            // Enable Email Notification
             $this->form_validation->set_rules('recipients','Recipients','trim|max_length[1000]');


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
                $winCommand = $this->input->post('winCommand');
                $executionStrategy = $this->input->post('executionStrategy');
                $scriptType = $this->input->post('scriptType');
                $windowsCommandLine = $this->input->post('windowsCommandLine');

                //Environment
                $environment = $this->input->post('environment');
                $checkEnvironment = $this->security->xss_clean($this->input->post('checkEnvironment'));

                if($executionStrategy == 'script'){
                  if($scriptType == 'talend'){
                          $filelist = glob("repository/".$scriptType."/jobs/".$job_name."/*");
                          $file = glob($filelist[0].'/*.bat');

                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = realpath($file[0]).' --context='.$environment;  
                          } else {
                            $filePath = realpath($file[0]);
                          }
                          
                          // // echo 'WINDOWS - TALEND File Path: <b>'.$filePath.'</b>';
                          // // echo '<hr><br>';
                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // // echo "The file $filePath does not exists";
                            }
                          }
                  } else if ($scriptType == 'batch') {
                        $filelist = glob("repository/".$scriptType."/jobs/".$job_name."/*.bat");
                          $file = glob($filelist[0]);

                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = realpath($file[0]).' --context='.$environment;  
                          } else {
                            $filePath = realpath($file[0]);
                          }
                          // // echo 'WINDOWS - BATCH File Path: <b>'.$filePath.'</b>';
                          // // echo '<hr><br>';
                          // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // // echo "The file $filePath does not exists";
                            }
                          } 
                           
                  } else if ($scriptType == 'python') {
                        $filelist = glob("repository/".$scriptType."/jobs/".$job_name."/*.py");
                          $file = glob($filelist[0]);

                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = realpath($file[0]).' '.$environment;  
                          } else {
                            $filePath = realpath($file[0]);
                          }
                          // // echo 'WINDOWS - PYTHON File Path: <b>'.$filePath.'</b>';
                          // // echo '<hr><br>';

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // // echo "The file $filePath does not exists";
                            }
                          }
                  }
                } else if ($executionStrategy == 'command'){

                  $filePath = $windowsCommandLine;
                  
                }
                // END Windows File Upload

                // Start Linux File Upload
                $jenkins_home = $this->global['jenkins_home'];
                $linuxCommand = $this->input->post('linuxCommand');
                $linuxExecutionStrategy = $this->input->post('linuxExecutionStrategy');
                $linuxScriptType = $this->input->post('linuxScriptType');
                $linuxCommandLine = $this->input->post('linuxCommandLine');

                if($linuxExecutionStrategy == 'script'){

                  // Check if jenkins home variable exist
                   if($jenkins_home != ''){
                         $storeFolder = $jenkins_home.'/repository/';
                         } else {
                            $storeFolder = 'repository/'; 
                            }

                  if($linuxScriptType == 'talend'){

                          $filelist = glob($storeFolder.$linuxScriptType."/jobs/".$job_name."/*");
                          $file = glob($filelist[0].'/*.sh');
                          
                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = realpath($file[0]).' --context='.$environment;  
                          } else {
                            $filePath = realpath($file[0]);
                          }

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // // echo "The file $filePath does not exists";
                            }
                          }

                          // // echo 'LINUX - TALEND File Path: <b>'.$filePath.'</b>';
                          // // echo '<hr><br>';
                  } else if ($linuxScriptType == 'bash') {
                        $filelist = glob($storeFolder.$linuxScriptType."/jobs/".$job_name."/*.sh");
                          $file = glob($filelist[0]);

                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = realpath($file[0]).' -context "'.$environment.'"';  
                          } else {
                            $filePath = realpath($file[0]);
                          }

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // echo "The file $filePath does not exists";
                            }
                          }

                         
                          // echo 'LINUX - BASH File Path: <b>'.$filePath.'</b>';
                          // echo '<hr><br>';
                  } else if ($linuxScriptType == 'python') {
                        $filelist = glob($storeFolder.$linuxScriptType."/jobs/".$job_name."/*.py");
                          $file = glob($filelist[0]);

                          // Check if using environemnt
                          if($environment != '0' && $checkEnvironment == 1){
                              $filePath = realpath($file[0]).' '.$environment;  
                          } else {
                            $filePath = realpath($file[0]);
                          }

                           // checking whether a file is directory or not 
                          if (is_dir($filePath)) {
                            // echo "My File is a directory";
                           $this->session->set_flashdata('error', 'Your file was not  uploaded to the server or no executable file was found inside the zip archive.');
                           redirect('JobCreation');
                          } else {
                            if (file_exists($filePath)) {
                            } else {
                                // echo "The file $filePath does not exists";
                            }
                          }

                          
                          // echo 'LINUX - PYTHON File Path: <b>'.$filePath.'</b>';
                          // echo '<hr><br>';
                  }
                } else if ($linuxExecutionStrategy == 'command'){

                  $filePath = $linuxCommandLine;  
                  
                }

                // END Linux File Upload
          
                // Validation if nothing comes null
                if ($singleMinute == null || $singleHour == null || $singleDayOfMonth == null || $singleMonth == null || $singleDayOfWeek == null){

                  $this->session->set_flashdata('error', 'You missed to select one field value for Build Periodically function');
                    redirect('JobCreation');
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
            
                // Execute another job section 
                $runJobCheck = $this->security->xss_clean($this->input->post('runJobCheck'));
                $jobList = $this->security->xss_clean($this->input->post('jobList'));
                $optionsRadios = $this->security->xss_clean($this->input->post('optionsRadios'));

                // Enable Email Notification
                $emailCheck = $this->security->xss_clean($this->input->post('emailCheck'));
                $recipients = $this->security->xss_clean($this->input->post('recipients'));

                // Enable Editable Email Notification
                $editableEmailCheck = $this->security->xss_clean($this->input->post('editableEmailCheck'));
                $onSuccess = $this->security->xss_clean($this->input->post('onSuccess'));
                $attSuccess = $this->security->xss_clean($this->input->post('attSuccess'));
                $onFailure = $this->security->xss_clean($this->input->post('onFailure'));
                $attFailure = $this->security->xss_clean($this->input->post('attFailure'));
                $onAbort = $this->security->xss_clean($this->input->post('onAbort'));
                $attAbort = $this->security->xss_clean($this->input->post('attAbort'));

                // Check if some field is missing from editable email notification
                if($editableEmailCheck == 1){
                  if($onSuccess == "0" && $onFailure == "0" && $onAbort == "0"){
                    $this->session->set_flashdata('error', 'You missed to select one field value for Editable email notification.');
                    redirect('JobCreation');
                  }
                }

               
                // Array to String Conversion Section
                $singleMinuteString = rtrim(implode(',', $singleMinute), ',');
                $singleHourString = rtrim(implode(',', $singleHour), ',');
                $singleDayOfMonthString = rtrim(implode(',', $singleDayOfMonth), ',');
                $singleMonthString = rtrim(implode(',', $singleMonth), ',');
                $singleDayOfWeekString = rtrim(implode(',', $singleDayOfWeek), ',');

                if ($jobList != null) {
                  $jobListString = rtrim(implode(', ', $jobList), ',');
                }
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
                if($winCommand == 1){ // Check if the windows command checkbox is marked
                  if($executionStrategy == 'script' && $scriptType != "0" || $executionStrategy == 'command'){

                    $hudson_task_BatchFile = $dom->createElement('hudson.tasks.BatchFile');
                    $command = $dom->createElement('command', $filePath);
                    $hudson_task_BatchFile->appendChild($command);
                    $builders->appendChild($hudson_task_BatchFile);
                  }
                }  

                // Linux Script Execution
                if($linuxCommand == 1) {
                  if($linuxExecutionStrategy == 'script' && $linuxScriptType != "0"){

                    $hudson_task_BashFile = $dom->createElement('hudson.tasks.Shell');
                    $command = $dom->createElement('command', 'sh '.$filePath);
                    $hudson_task_BashFile->appendChild($command);
                    $builders->appendChild($hudson_task_BashFile);
                    
                  } else if($linuxExecutionStrategy == 'command') {

                    $hudson_task_BashFile = $dom->createElement('hudson.tasks.Shell');
                    $command = $dom->createElement('command', $filePath);
                    $hudson_task_BashFile->appendChild($command);
                    $builders->appendChild($hudson_task_BashFile);

                  }
                }

                // Append Builders to root node
                $root->appendChild($builders);

                 // Create Publishers Elements
                 $publishers = $dom->createElement('publishers');





                 // Editable Email Notification
                 if($editableEmailCheck == 1){ // if enable editable email notification is marked

               
                  $hudson_ExtendedMailer = $dom->createElement('hudson.plugins.emailext.ExtendedEmailPublisher');
                  $attr_hudson_ExtendedMailer = new DOMAttr('plugin', 'email-ext@2.68');
                  $hudson_ExtendedMailer->setAttributeNode($attr_hudson_ExtendedMailer);
                  $publishers->appendChild($hudson_ExtendedMailer);
                
                  $configuredTriggers = $dom->createElement('configuredTriggers');
                  $hudson_ExtendedMailer->appendChild($configuredTriggers);

                  // On Success
                  if($onSuccess != "0") { // if On success template is selected

                  // Load EmailSettings Model in order to fech the email template
                  $this->load->model('emailSettings_model','model');
                  $listMailTemplates = $this->model->fetchName($onSuccess); 

                  // CC String to array, array to string function
                  $string = $listMailTemplates[0]->cc;
                  $array = explode(",", $string);
                  if($listMailTemplates[0]->cc != ''){
                   for ($i=0; $i < sizeof($array); $i++) { 
                    $array2[$i] = ', cc:'.$array[$i];
                   }
                  }
                  $array2String = rtrim(implode('', $array2), ',');


                  $successTrigger = $dom->createElement('hudson.plugins.emailext.plugins.trigger.SuccessTrigger');
                  $configuredTriggers->appendChild($successTrigger);

                  $email = $dom->createElement('email');
                  $successTrigger->appendChild($email);

                  $recipientList = $dom->createElement('recipientList', $listMailTemplates[0]->to.$array2String);
                  $email->appendChild($recipientList);

                  $subject = $dom->createElement('subject', $listMailTemplates[0]->subject);
                  $email->appendChild($subject);

                  $body = $dom->createElement('body', $listMailTemplates[0]->msg);
                  $email->appendChild($body);

                  $recipientProviders = $dom->createElement('recipientProviders');
                  $email->appendChild($recipientProviders);

                  $recipientProvidersPlugin = $dom->createElement('hudson.plugins.emailext.plugins.recipients.DevelopersRecipientProvider');
                  $recipientProviders->appendChild($recipientProvidersPlugin);

                  $attachments = $dom->createElement('attachmentsPattern', '');
                  $email->appendChild($attachments);

                  $attachBuildLog = $dom->createElement('attachBuildLog', $attSuccess);
                  $email->appendChild($attachBuildLog);

                  $compressBuildLog = $dom->createElement('compressBuildLog', 'false');
                  $email->appendChild($compressBuildLog);

                  $replyTo = $dom->createElement('replyTo', '$PROJECT_DEFAULT_REPLYTO');
                  $email->appendChild($replyTo);

                  $contentType = $dom->createElement('contentType', 'both');
                  $email->appendChild($contentType);

                  $from = $dom->createElement('from', $listMailTemplates[0]->from);
                  $hudson_ExtendedMailer->appendChild($from);

                  }


                  // On Failure
                  if($onFailure != "0") { // if On success template is selected

                  // Load EmailSettings Model in order to fech the email template
                  $this->load->model('emailSettings_model','model');
                  $listMailTemplates = $this->model->fetchName($onFailure); 

                  // CC String to array, array to string function
                  $string = $listMailTemplates[0]->cc;
                  $array = explode(",", $string);
                  if($listMailTemplates[0]->cc != ''){
                   for ($i=0; $i < sizeof($array); $i++) { 
                    $array2[$i] = ', cc:'.$array[$i];
                   }
                  }
                  $array2String = rtrim(implode('', $array2), ',');

                  $successTrigger = $dom->createElement('hudson.plugins.emailext.plugins.trigger.FailureTrigger');
                  $configuredTriggers->appendChild($successTrigger);

                  $email = $dom->createElement('email');
                  $successTrigger->appendChild($email);

                  $recipientList = $dom->createElement('recipientList', $listMailTemplates[0]->to.$array2String);
                  $email->appendChild($recipientList);

                  $subject = $dom->createElement('subject', $listMailTemplates[0]->subject);
                  $email->appendChild($subject);

                  $body = $dom->createElement('body', $listMailTemplates[0]->msg);
                  $email->appendChild($body);

                  $recipientProviders = $dom->createElement('recipientProviders');
                  $email->appendChild($recipientProviders);

                  $recipientProvidersPlugin = $dom->createElement('hudson.plugins.emailext.plugins.recipients.DevelopersRecipientProvider');
                  $recipientProviders->appendChild($recipientProvidersPlugin);

                  $attachments = $dom->createElement('attachmentsPattern', '');
                  $email->appendChild($attachments);

                  $attachBuildLog = $dom->createElement('attachBuildLog', $attFailure);
                  $email->appendChild($attachBuildLog);

                  $compressBuildLog = $dom->createElement('compressBuildLog', 'false');
                  $email->appendChild($compressBuildLog);

                  $replyTo = $dom->createElement('replyTo', '$PROJECT_DEFAULT_REPLYTO');
                  $email->appendChild($replyTo);

                  $contentType = $dom->createElement('contentType', 'both');
                  $email->appendChild($contentType);

                  $from = $dom->createElement('from', $listMailTemplates[0]->from);
                  $hudson_ExtendedMailer->appendChild($from);

                  }

                   // On Abort
                  if($onAbort != "0") { // if On success template is selected

                  // Load EmailSettings Model in order to fech the email template
                  $this->load->model('emailSettings_model','model');
                  $listMailTemplates = $this->model->fetchName($onAbort);

                   // CC String to array, array to string function
                  $string = $listMailTemplates[0]->cc;
                  $array = explode(",", $string);
                  if($listMailTemplates[0]->cc != ''){
                   for ($i=0; $i < sizeof($array); $i++) { 
                    $array2[$i] = ', cc:'.$array[$i];
                   }
                  }
                  $array2String = rtrim(implode('', $array2), ',');

                  $successTrigger = $dom->createElement('hudson.plugins.emailext.plugins.trigger.AbortedTrigger');
                  $configuredTriggers->appendChild($successTrigger);

                  $email = $dom->createElement('email');
                  $successTrigger->appendChild($email);

                  $recipientList = $dom->createElement('recipientList', $listMailTemplates[0]->to.$array2String);
                  $email->appendChild($recipientList);

                  $subject = $dom->createElement('subject', $listMailTemplates[0]->subject);
                  $email->appendChild($subject);

                  $body = $dom->createElement('body', $listMailTemplates[0]->msg);
                  $email->appendChild($body);

                  $recipientProviders = $dom->createElement('recipientProviders');
                  $email->appendChild($recipientProviders);

                  $recipientProvidersPlugin = $dom->createElement('hudson.plugins.emailext.plugins.recipients.DevelopersRecipientProvider');
                  $recipientProviders->appendChild($recipientProvidersPlugin);

                  $attachments = $dom->createElement('attachmentsPattern', '');
                  $email->appendChild($attachments);

                  $attachBuildLog = $dom->createElement('attachBuildLog', $attAbort);
                  $email->appendChild($attachBuildLog);

                  $compressBuildLog = $dom->createElement('compressBuildLog', 'false');
                  $email->appendChild($compressBuildLog);

                  $replyTo = $dom->createElement('replyTo', '$PROJECT_DEFAULT_REPLYTO');
                  $email->appendChild($replyTo);

                  $contentType = $dom->createElement('contentType', 'both');
                  $email->appendChild($contentType);

                  $from = $dom->createElement('from', $listMailTemplates[0]->from);
                  $hudson_ExtendedMailer->appendChild($from);

                  }

                 }


                 // Email Notification (Mailer)
                 if ($emailCheck == 1) { // if email notification checkbox is marked then
                    if ($recipients != '') {

                      $hudson_Mailer = $dom->createElement('hudson.tasks.Mailer');
                      $attr_hudson_Mailer = new DOMAttr('plugin', 'mailer@1.30');
                      $hudson_Mailer->setAttributeNode($attr_hudson_Mailer);
                      $childRecipients = $dom->createElement('recipients', $recipients );
                      $hudson_Mailer->appendChild($childRecipients);
                      $childUnstableBuild = $dom->createElement('dontNotifyEveryUnstableBuild', 'false' );
                      $hudson_Mailer->appendChild($childUnstableBuild);
                      $sendToIndividuals = $dom->createElement('sendToIndividuals', 'false' );
                      $hudson_Mailer->appendChild($sendToIndividuals);
                      $publishers->appendChild($hudson_Mailer);
                    
                    }
                  }

                if($runJobCheck == 1){ // if Run Job Checkbox is marked then   
                  if ($jobList != null){
                    $BuildTrigger = $dom->createElement('hudson.tasks.BuildTrigger');
                    $publishers->appendChild($BuildTrigger);

                    $childProjects = $dom->createElement('childProjects', $jobListString );
                    $BuildTrigger->appendChild($childProjects);

                    if ($optionsRadios == "1"){
                      $threshold = $dom->createElement('threshold');
                      $thresholdName = $dom->createElement('name', 'SUCCESS' );
                      $thresholdOrdinal = $dom->createElement('ordinal', '0' );
                      $thresholdColor = $dom->createElement('color', 'BLUE' );
                      $thresholdCompleteBuild = $dom->createElement('completeBuild', 'true' );
                    } else if ($optionsRadios == "2") {
                      $threshold = $dom->createElement('threshold');
                      $thresholdName = $dom->createElement('name', 'FAILURE' );
                      $thresholdOrdinal = $dom->createElement('ordinal', '2' );
                      $thresholdColor = $dom->createElement('color', 'RED' );
                      $thresholdCompleteBuild = $dom->createElement('completeBuild', 'true' );
                    }

                    $threshold->appendChild($thresholdName);
                    $threshold->appendChild($thresholdOrdinal);
                    $threshold->appendChild($thresholdColor);
                    $threshold->appendChild($thresholdCompleteBuild);
                    $BuildTrigger->appendChild($threshold);
                  }
                }

                // Append Builders to root node
                $root->appendChild($publishers);

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
                $dom->save('/php/data/'.$xml_file_name);
                // Read XML File to obtain xml text
                $content = htmlentities(file_get_contents('/php/data/'.$xml_file_name));

                // Create flash data to send to view
                 $this->session->set_flashdata('xml', $content);
                 $this->session->set_flashdata('job_name', $job_name);
                 $this->session->set_flashdata('success', 'Your XML File has been successfully created !');

                redirect('JobCreation');

            }
        }
    }

    public function readXML() {

        header("Content-Type: text/xml");
        $content = file_get_contents("xml/config.xml");
        // // echo $content;

    }

}