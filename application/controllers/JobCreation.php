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
      $data = array('job_creation_dates' => $this->readJobCreationDates());
        
      $this->loadViews("jobCreation", $this->global, $data, NULL);

    }

    private function canManageJobs() {
      return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function jenkinsJobPath($jobName) {
      $segments = explode('/', trim((string) $jobName, '/'));
      $path = array();

      foreach ($segments as $segment) {
        if ($segment !== '') {
          $path[] = 'job/' . rawurlencode($segment);
        }
      }

      return implode('/', $path);
    }

    private function isSuccessfulJenkinsStatus($status) {
      return in_array((int) $status, array(200, 201, 302, 303), TRUE);
    }

    private function saveGeneratedJenkinsJob($jobName, $xml) {
      $jobPath = $this->jenkinsJobPath($jobName);
      $jobResponse = $this->requestJenkins('GET', $jobPath . '/api/json');

      if ((int) $jobResponse['status'] === 200) {
        $saveResponse = $this->requestJenkins('POST', $jobPath . '/config.xml', $xml, 'text/xml');

        return array(
          'ok' => $this->isSuccessfulJenkinsStatus($saveResponse['status']),
          'updated' => TRUE,
          'status' => $saveResponse['status']
        );
      }

      if ((int) $jobResponse['status'] === 404) {
        $saveResponse = $this->requestJenkins('POST', 'createItem?name=' . rawurlencode($jobName), $xml, 'text/xml');

        return array(
          'ok' => $this->isSuccessfulJenkinsStatus($saveResponse['status']),
          'updated' => FALSE,
          'status' => $saveResponse['status']
        );
      }

      return array(
        'ok' => FALSE,
        'updated' => FALSE,
        'status' => $jobResponse['status']
      );
    }

    private function jobCreationDatesPath() {
      return APPPATH . 'cache/job_creation_dates.json';
    }

    private function readJobCreationDates() {
      $path = $this->jobCreationDatesPath();

      if (! is_readable($path)) {
        return array();
      }

      $json = file_get_contents($path);
      $dates = json_decode($json, TRUE);

      if (! is_array($dates)) {
        return array();
      }

      $cleanDates = array();
      foreach ($dates as $jobName => $createdAt) {
        if (is_string($jobName) && is_string($createdAt) && $jobName !== '' && $createdAt !== '') {
          $cleanDates[$jobName] = $createdAt;
        }
      }

      return $cleanDates;
    }

    private function recordJobCreationDate($jobName, $createdAt) {
      $dates = $this->readJobCreationDates();
      $dates[$jobName] = $createdAt;

      return file_put_contents($this->jobCreationDatesPath(), json_encode($dates, JSON_PRETTY_PRINT), LOCK_EX) !== FALSE;
    }

    private function generateJobName() {
      $names = array('milo', 'luna', 'piper', 'nova', 'ruby', 'jasper', 'olive', 'cosmo');
      $traits = array('sunny', 'maple', 'pixel', 'river', 'coco', 'sage', 'mango', 'ember');

      try {
        $token = dechex(random_int(4096, 65535));
      } catch (Exception $exception) {
        $token = substr(uniqid('', TRUE), -4);
      }

      return $names[array_rand($names)].'-'.$traits[array_rand($traits)].'-'.$token;
    }

    public function do_upload($val,$job_name) {

      header('Content-Type: text/html; charset=utf-8');

      if (! $this->canManageJobs()) {
        $this->output->set_status_header(403);
        echo 'Access denied.';
        return;
      }

      $this->global['pageTitle'] = 'Job Seeker : Upload';
      $jenkins_home = $this->global['jenkins_home'];

      $safeScriptType = $this->safePathSegment(rawurldecode($val));
      $safeJobName = $this->safePathSegment(rawurldecode($job_name));

      if ($safeScriptType === FALSE || $safeJobName === FALSE) {
        $this->output->set_status_header(400);
        echo 'Invalid upload destination.';
        return;
      }

     $ds = DIRECTORY_SEPARATOR;

      // Check if jenkins home variable exist
     if($jenkins_home === '' || $jenkins_home === null){
      $storeFolder = '../../repository/'.$safeScriptType.'/jobs/';
      $targetPath = dirname( __FILE__ ) . $ds. $storeFolder . $ds; 

     } else {

      $storeFolder = rtrim($jenkins_home, '/\\').'/repository/'.$safeScriptType.'/jobs/';
      $targetPath = $storeFolder;
      
     }

      $allowedExtensions = $safeScriptType === 'python' ? array('zip', 'py') : array('zip');
      $upload = $this->getUploadedFile('file', $allowedExtensions, 104857600);
      if (! $upload['ok']) {
        $this->output->set_status_header(400);
        echo $upload['message'];
        return;
      }

      $targetJobPath = rtrim($targetPath, '/\\') . $ds . $safeJobName;

      if ($safeScriptType === 'python' && $upload['extension'] === 'py') {
        if (! $this->ensureDirectory($targetJobPath)) {
          $this->output->set_status_header(500);
          echo 'Unable to create upload directory.';
          return;
        }

        $targetFile = $targetJobPath . $ds . $upload['safe_name'];
        if (! move_uploaded_file($upload['tmp_name'], $targetFile)) {
          $this->output->set_status_header(500);
          echo 'Unable to store uploaded file.';
          return;
        }

        echo 'Python file uploaded.';
        return;
      }

      if (! $this->ensureDirectory($targetPath)) {
        $this->output->set_status_header(500);
        echo 'Unable to create upload directory.';
        return;
      }

      $targetFile = $targetPath . uniqid('job_', TRUE) . '.zip';
      if (! move_uploaded_file($upload['tmp_name'], $targetFile)) {
        $this->output->set_status_header(500);
        echo 'Unable to store uploaded file.';
        return;
      }

      $destinationExisted = is_dir($targetJobPath);
      $extractResult = $this->extractZipSafely($targetFile, $targetJobPath);
      @unlink($targetFile);

      if (! $extractResult['ok']) {
        if (! $destinationExisted && is_dir($targetJobPath)) {
          $this->removeUploadDirectory($targetJobPath);
        }
        $this->output->set_status_header(400);
        echo $extractResult['message'];
        return;
      }

      echo 'File uploaded and extracted.';

      }

      private function cronFieldString($values, $min, $max) {
        if (! is_array($values)) {
          $values = array($values);
        }

        $cleanValues = array();
        foreach ($values as $value) {
          $value = trim((string) $value);

          if ($value === '') {
            continue;
          }

          if ($value === '*') {
            $cleanValues[] = '*';
            continue;
          }

          if (! ctype_digit($value)) {
            return FALSE;
          }

          $number = (int) $value;
          if ($number < $min || $number > $max) {
            return FALSE;
          }

          $cleanValues[] = (string) $number;
        }

        $cleanValues = array_values(array_unique($cleanValues));
        if (empty($cleanValues)) {
          return FALSE;
        }

        if (in_array('*', $cleanValues, TRUE) && count($cleanValues) > 1) {
          $cleanValues = array_values(array_diff($cleanValues, array('*')));
        }

        return implode(',', $cleanValues);
      }

      private function cronMinuteStepString($value) {
        $value = trim((string) $value);

        if ($value === '*') {
          return '*';
        }

        if (! ctype_digit($value)) {
          return FALSE;
        }

        $number = (int) $value;
        if ($number < 1 || $number > 59) {
          return FALSE;
        }

        return 'H/'.$number;
      }

      private function removeUploadDirectory($path) {
        foreach (scandir($path) as $item) {
          if ($item === '.' || $item === '..') {
            continue;
          }

          $itemPath = $path . DIRECTORY_SEPARATOR . $item;
          if (is_dir($itemPath) && ! is_link($itemPath)) {
            $this->removeUploadDirectory($itemPath);
          } else {
            @unlink($itemPath);
          }
        }

        @rmdir($path);
      }

      private function ensurePythonSharedLibrary($repositoryRoot) {
        $sourceFile = APPPATH.'third_party/python/jobseeker.py';
        $targetDirectory = rtrim($repositoryRoot, '/\\').DIRECTORY_SEPARATOR.'python'.DIRECTORY_SEPARATOR.'lib';
        $targetFile = $targetDirectory.DIRECTORY_SEPARATOR.'jobseeker.py';

        if (! is_readable($sourceFile) || ! $this->ensureDirectory($targetDirectory)) {
          return FALSE;
        }

        return copy($sourceFile, $targetFile);
      }

      private function selectedPythonSourceMode($sourceMode) {
        return in_array($sourceMode, array('upload', 'path', 'git'), TRUE) ? $sourceMode : 'upload';
      }

      private function cleanPythonEntryPoint($entryPoint, $required = FALSE) {
        $entryPoint = trim((string) $entryPoint);

        if ($entryPoint === '') {
          return $required ? FALSE : '';
        }

        $safeEntryPoint = $this->safeRelativePath($entryPoint);
        if ($safeEntryPoint === FALSE || strtolower(pathinfo($safeEntryPoint, PATHINFO_EXTENSION)) !== 'py') {
          return FALSE;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $safeEntryPoint);
      }

      private function repositoryRealPath($repositoryRoot) {
        $repositoryRealPath = realpath($repositoryRoot);
        return $repositoryRealPath === FALSE ? FALSE : rtrim($repositoryRealPath, DIRECTORY_SEPARATOR);
      }

      private function resolveRepositoryPath($path, $repositoryRoot) {
        $path = trim((string) $path);
        if ($path === '') {
          return FALSE;
        }

        $repositoryRealPath = $this->repositoryRealPath($repositoryRoot);
        if ($repositoryRealPath === FALSE) {
          return FALSE;
        }

        if (strpos($path, '/php/repository/') === 0 || $path === '/php/repository') {
          $candidatePath = $path;
        } else {
          $path = trim(str_replace('\\', '/', $path), '/');
          if (strpos($path, 'repository/') === 0) {
            $path = substr($path, strlen('repository/'));
          }

          $safePath = $this->safeRelativePath($path);
          if ($safePath === FALSE) {
            return FALSE;
          }

          $candidatePath = $repositoryRealPath.DIRECTORY_SEPARATOR.$safePath;
        }

        $resolvedPath = realpath($candidatePath);
        if ($resolvedPath === FALSE || ! $this->pathWithinBase($resolvedPath, $repositoryRealPath)) {
          return FALSE;
        }

        return $resolvedPath;
      }

      private function resolvePythonFile($sourceDirectory, $entryPoint) {
        $scriptPath = realpath(rtrim($sourceDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entryPoint));
        if ($scriptPath === FALSE || ! is_file($scriptPath) || strtolower(pathinfo($scriptPath, PATHINFO_EXTENSION)) !== 'py' || ! $this->pathWithinBase($scriptPath, $sourceDirectory)) {
          return FALSE;
        }

        return $scriptPath;
      }

      private function resolveUploadedPythonExecution($repositoryRoot, $jobName, $entryPoint) {
        $jobDirectory = $this->resolveRepositoryPath('python/jobs/'.$jobName, $repositoryRoot);
        if ($jobDirectory === FALSE || ! is_dir($jobDirectory)) {
          return FALSE;
        }

        if ($entryPoint !== '') {
          $scriptPath = $this->resolvePythonFile($jobDirectory, $entryPoint);
        } else {
          $files = glob($jobDirectory.DIRECTORY_SEPARATOR.'*.py');
          $scriptPath = empty($files) ? FALSE : realpath($files[0]);
        }

        if ($scriptPath === FALSE || ! is_file($scriptPath)) {
          return FALSE;
        }

        return array(
          'mode' => 'local',
          'sourceDirectory' => $jobDirectory,
          'scriptPath' => $scriptPath
        );
      }

      private function resolvePathPythonExecution($repositoryRoot, $sourcePath, $entryPoint) {
        $resolvedSourcePath = $this->resolveRepositoryPath($sourcePath, $repositoryRoot);
        if ($resolvedSourcePath === FALSE) {
          return FALSE;
        }

        if (is_file($resolvedSourcePath)) {
          $sourceDirectory = dirname($resolvedSourcePath);
          $scriptPath = $resolvedSourcePath;
        } else if (is_dir($resolvedSourcePath) && $entryPoint !== '') {
          $sourceDirectory = $resolvedSourcePath;
          $scriptPath = $this->resolvePythonFile($sourceDirectory, $entryPoint);
        } else {
          return FALSE;
        }

        if ($scriptPath === FALSE || strtolower(pathinfo($scriptPath, PATHINFO_EXTENSION)) !== 'py') {
          return FALSE;
        }

        return array(
          'mode' => 'local',
          'sourceDirectory' => $sourceDirectory,
          'scriptPath' => $scriptPath
        );
      }

      private function cleanPythonRepositoryUrl($repositoryUrl) {
        $repositoryUrl = trim((string) $repositoryUrl);
        if ($repositoryUrl === '' || strlen($repositoryUrl) > 1000 || preg_match('/[\x00-\x1F\x7F]/', $repositoryUrl)) {
          return FALSE;
        }

        if (filter_var($repositoryUrl, FILTER_VALIDATE_URL) !== FALSE) {
          return $repositoryUrl;
        }

        if (preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9._\-]+:[A-Za-z0-9._\-\/]+(?:\.git)?$/', $repositoryUrl)) {
          return $repositoryUrl;
        }

        return FALSE;
      }

      private function cleanPythonRepositoryBranch($branch) {
        $branch = trim((string) $branch);
        if ($branch === '') {
          return '';
        }

        return preg_match('/^[A-Za-z0-9._\/-]+$/', $branch) ? $branch : FALSE;
      }

      private function resolveGitPythonExecution($repositoryUrl, $branch, $entryPoint) {
        $repositoryUrl = $this->cleanPythonRepositoryUrl($repositoryUrl);
        $branch = $this->cleanPythonRepositoryBranch($branch);
        $entryPoint = $this->cleanPythonEntryPoint($entryPoint, TRUE);

        if ($repositoryUrl === FALSE || $branch === FALSE || $entryPoint === FALSE) {
          return FALSE;
        }

        return array(
          'mode' => 'git',
          'repositoryUrl' => $repositoryUrl,
          'branch' => $branch,
          'entryPoint' => $entryPoint
        );
      }

      private function pythonEnvironmentArgument($environment, $checkEnvironment) {
        return ($environment != '0' && $checkEnvironment == 1) ? escapeshellarg($environment) : '';
      }

      private function buildPythonExecutionCommand($execution, $repositoryRoot, $environmentArgument) {
        $pythonLibraryPath = rtrim($repositoryRoot, '/\\').'/python/lib';
        $lines = array('set -e');

        if ($execution['mode'] === 'git') {
          $cloneCommand = 'git clone --depth 1';
          if ($execution['branch'] !== '') {
            $cloneCommand .= ' --branch '.escapeshellarg($execution['branch']);
          }
          $cloneCommand .= ' '.escapeshellarg($execution['repositoryUrl']).' "$WORKSPACE/jobseeker-python-source"';

          $lines[] = 'rm -rf "$WORKSPACE/jobseeker-python-source"';
          $lines[] = $cloneCommand;
          $lines[] = 'export JOBSEEKER_SOURCE_DIR="$WORKSPACE/jobseeker-python-source"';
          $lines[] = 'export JOBSEEKER_ENTRYPOINT='.escapeshellarg($execution['entryPoint']);
          $lines[] = 'export JOBSEEKER_SCRIPT_PATH="$JOBSEEKER_SOURCE_DIR/$JOBSEEKER_ENTRYPOINT"';
        } else {
          $lines[] = 'export JOBSEEKER_SOURCE_DIR='.escapeshellarg($execution['sourceDirectory']);
          $lines[] = 'export JOBSEEKER_SCRIPT_PATH='.escapeshellarg($execution['scriptPath']);
        }

        $lines[] = 'export JOBSEEKER_PYTHON_LIB='.escapeshellarg($pythonLibraryPath);
        $lines[] = 'export JOBSEEKER_SCRIPT_DIR="$(dirname "$JOBSEEKER_SCRIPT_PATH")"';
        $lines[] = 'cd "$JOBSEEKER_SOURCE_DIR"';
        $lines[] = 'export PYTHONPATH="$JOBSEEKER_SOURCE_DIR:$JOBSEEKER_SCRIPT_DIR:$JOBSEEKER_PYTHON_LIB:$PYTHONPATH"';
        $lines[] = 'JOBSEEKER_REQUIREMENTS=""';
        $lines[] = 'if [ -f "$JOBSEEKER_SOURCE_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="$JOBSEEKER_SOURCE_DIR/requirements.txt"; fi';
        $lines[] = 'if [ -f "$JOBSEEKER_SCRIPT_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="$JOBSEEKER_SCRIPT_DIR/requirements.txt"; fi';
        $lines[] = 'if [ -n "$JOBSEEKER_REQUIREMENTS" ]; then';
        $lines[] = '  rm -rf "$JOBSEEKER_SOURCE_DIR/.jobseeker-python-libs"';
        $lines[] = '  python3 -m pip install --quiet --disable-pip-version-check --target "$JOBSEEKER_SOURCE_DIR/.jobseeker-python-libs" -r "$JOBSEEKER_REQUIREMENTS"';
        $lines[] = '  export PYTHONPATH="$JOBSEEKER_SOURCE_DIR/.jobseeker-python-libs:$PYTHONPATH"';
        $lines[] = 'fi';
        $lines[] = 'python3 "$JOBSEEKER_SCRIPT_PATH"'.($environmentArgument !== '' ? ' '.$environmentArgument : '');

        return implode("\n", $lines);
      }


    public function send() {

      if(! $this->canManageJobs())
        {
            $this->loadThis();
        }
        else
        {
            header('Content-Type: text/html; charset=utf-8');

            $this->load->library('form_validation');

            // Basic inputs
            $this->form_validation->set_rules('job_name','Job Name','trim|max_length[50]');
            $this->form_validation->set_rules('description','Description','trim|max_length[5000]');

      
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
                $job_name = trim((string) $this->security->xss_clean($this->input->post('job_name')));
                if ($job_name === '') {
                  $job_name = $this->generateJobName();
                }
                $description = trim((string) $this->security->xss_clean($this->input->post('description')));
                $triggerAfterSave = $this->security->xss_clean($this->input->post('trigger_after_save')) == '1' ? '1' : '0';

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
                $pythonSourceMode = $this->selectedPythonSourceMode($this->input->post('pythonSourceMode'));
                $pythonSourcePath = $this->input->post('pythonSourcePath');
                $pythonRepositoryUrl = $this->input->post('pythonRepositoryUrl');
                $pythonRepositoryBranch = $this->input->post('pythonRepositoryBranch');
                $pythonEntryPointRaw = $this->input->post('pythonEntryPoint');
                $pythonEntryPoint = $this->cleanPythonEntryPoint($pythonEntryPointRaw, FALSE);
                $pythonExecution = NULL;

                if ($pythonEntryPoint === FALSE) {
                  $this->session->set_flashdata('error', 'You missed to select a valid Python entry file.');
                  redirect('JobCreation');
                }

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
                          $repositoryRoot = rtrim($storeFolder, '/\\');

                          if ($pythonSourceMode === 'path') {
                            $pythonExecution = $this->resolvePathPythonExecution($repositoryRoot, $pythonSourcePath, $pythonEntryPoint);
                          } else if ($pythonSourceMode === 'git') {
                            $pythonExecution = $this->resolveGitPythonExecution($pythonRepositoryUrl, $pythonRepositoryBranch, $pythonEntryPointRaw);
                          } else {
                            $pythonExecution = $this->resolveUploadedPythonExecution($repositoryRoot, $job_name, $pythonEntryPoint);
                          }

                          if ($pythonExecution === FALSE) {
                           $this->session->set_flashdata('error', 'JobSeeker could not resolve the Python source. Check the upload, repository path, Git URL, and entry file.');
                           redirect('JobCreation');
                          }
                  }
                } else if ($linuxExecutionStrategy == 'command'){

                  $filePath = $linuxCommandLine;  
                  
                }

                // END Linux File Upload
          
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

                if ($abort == 1) {
                  if ($timeoutStrategy == 'absolute') {
                    if (! ctype_digit((string) $timeoutMinutes) || (int) $timeoutMinutes < 1) {
                      $this->session->set_flashdata('error', 'You missed to select a valid timeout in minutes for the abort option.');
                      redirect('JobCreation');
                    }
                  } else {
                    $timeoutStrategy = 'noActivity';
                    if (! ctype_digit((string) $timeoutSeconds) || (int) $timeoutSeconds < 60) {
                      $this->session->set_flashdata('error', 'You missed to select a valid timeout in seconds for the abort option.');
                      redirect('JobCreation');
                    }
                  }
                }
            
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
                $singleMinuteString = $this->cronFieldString($singleMinute, 0, 59);
                $singleHourString = $this->cronFieldString($singleHour, 0, 23);
                $singleDayOfMonthString = $this->cronFieldString($singleDayOfMonth, 1, 31);
                $singleMonthString = $this->cronFieldString($singleMonth, 1, 12);
                $singleDayOfWeekString = $this->cronFieldString($singleDayOfWeek, 1, 7);

                $repetitiveMinuteString = $this->cronMinuteStepString($repetitiveMinute);
                $repetitiveHourString = $this->cronFieldString($repetitiveHour, 0, 23);
                $repetitiveDayOfMonthString = $this->cronFieldString($repetitiveDayOfMonth, 1, 31);
                $repetitiveMonthString = $this->cronFieldString($repetitiveMonth, 1, 12);
                $repetitiveDayOfWeekString = $this->cronFieldString($repetitiveDayOfWeek, 1, 7);

                if($checkBuild == 1){
                  if($action == "single" && ($singleMinuteString === FALSE || $singleHourString === FALSE || $singleDayOfMonthString === FALSE || $singleMonthString === FALSE || $singleDayOfWeekString === FALSE)){
                    $this->session->set_flashdata('error', 'You missed to select valid values for Single Execution scheduling.');
                    redirect('JobCreation');
                  } else if($action == "repetitive" && ($repetitiveMinuteString === FALSE || $repetitiveHourString === FALSE || $repetitiveDayOfMonthString === FALSE || $repetitiveMonthString === FALSE || $repetitiveDayOfWeekString === FALSE)){
                    $this->session->set_flashdata('error', 'You missed to select valid values for Repetitive Execution scheduling.');
                    redirect('JobCreation');
                  } else if($action == "tags" && ! in_array($tag, array('@hourly', '@daily', '@weekly', '@monthly', '@annually', '@yearly', '@midnight'), TRUE)){
                    $this->session->set_flashdata('error', 'You missed to select a valid Execution Tag option.');
                    redirect('JobCreation');
                  } else if($action != "single" && $action != "repetitive" && $action != "tags"){
                    $this->session->set_flashdata('error', 'You missed to select one field value for Build Periodically function');
                    redirect('JobCreation');
                  }
                }

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
                      $spec = $dom->createElement('spec', $repetitiveMinuteString.' '.$repetitiveHourString.' '.$repetitiveDayOfMonthString.' '.$repetitiveMonthString.' '.$repetitiveDayOfWeekString);
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
                    if($linuxScriptType == 'python'){
                      $repositoryRoot = rtrim($storeFolder, '/\\');
                      if (! $this->ensurePythonSharedLibrary($repositoryRoot)) {
                        $this->session->set_flashdata('error', 'Unable to prepare the shared Python jobseeker helper.');
                        redirect('JobCreation');
                      }
                      $command = $dom->createElement('command', $this->buildPythonExecutionCommand($pythonExecution, $repositoryRoot, $this->pythonEnvironmentArgument($environment, $checkEnvironment)));
                    } else {
                      $command = $dom->createElement('command', 'sh '.$filePath);
                    }
                    
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
                $xmlPath = '/php/data/'.$xml_file_name;
                if ($dom->save($xmlPath) === FALSE) {
                  $this->session->set_flashdata('error', 'Unable to prepare the Jenkins job configuration.');
                  redirect('JobCreation');
                }

                $xmlContent = file_get_contents($xmlPath);
                if ($xmlContent === FALSE) {
                  $this->session->set_flashdata('error', 'Unable to read the generated Jenkins job configuration.');
                  redirect('JobCreation');
                }

                $saveResult = $this->saveGeneratedJenkinsJob($job_name, $xmlContent);

                if (! $saveResult['ok']) {
                  $this->session->set_flashdata('error', 'Your Jenkins job save request failed. Jenkins returned HTTP '.$saveResult['status'].'.');
                  redirect('JobCreation');
                }

                $createdAt = NULL;
                if (! $saveResult['updated']) {
                  $createdAt = date('c');
                  $this->recordJobCreationDate($job_name, $createdAt);
                }

                $successMessage = 'Your job has been successfully '.($saveResult['updated'] ? 'updated' : 'created').'.';

                if ($triggerAfterSave === '1') {
                  $triggerResponse = $this->requestJenkins('POST', $this->jenkinsJobPath($job_name) . '/build');

                  if ($this->isSuccessfulJenkinsStatus($triggerResponse['status'])) {
                    $successMessage .= ' It has also been triggered.';
                  } else {
                    $this->session->set_flashdata('error', 'The job was saved, but the trigger request failed. Jenkins returned HTTP '.$triggerResponse['status'].'.');
                  }
                }

                 $this->session->set_flashdata('success', $successMessage);
                 $this->session->set_flashdata('saved_job_name', $job_name);
                 if ($createdAt !== NULL) {
                   $this->session->set_flashdata('saved_job_created_at', $createdAt);
                 }

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