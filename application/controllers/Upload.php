<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class Upload extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('file','url');
        $this->load->model('Upload_model','model');
        $this->load->library('session');
        $this->isLoggedIn();   

    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : File Upload';
        
        $this->loadViews("upload", $this->global, NULL, NULL);
    }

    function listJobsJson() {
        header('Content-type:application/json;charset=utf-8'); // declaring header


        $this->global['pageTitle'] = 'Job Seeker : Json Parse';

        $listJobsJson = $this->model->listJobs();
        echo json_encode($listJobsJson, JSON_PRETTY_PRINT);

     
    }

     public function listComponents($jobname) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $listComponent = $this->model->listComponents($jobname);

      echo json_encode($listComponent, JSON_PRETTY_PRINT);

    }

    public function listComponentType($jobname, $component) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $listComponentType = $this->model->listComponentType($jobname, $component);

      echo json_encode($listComponentType, JSON_PRETTY_PRINT);

    }

    public function listComponentPath($jobname, $component, $type) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $listComponentPath = $this->model->listComponentPath($jobname, $component, $type);

      echo json_encode($listComponentPath, JSON_PRETTY_PRINT);

    }

    public function listAll($jobname, $component, $type) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';


      $listAll = $this->model->listAll($jobname, $component, $type);

      echo json_encode($listAll, JSON_PRETTY_PRINT);

      
    }

    public function Path($jobname, $component, $type) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';


      $Path = $this->model->Path($jobname, $component, $type);

      echo json_encode($Path, JSON_PRETTY_PRINT);

      
    }

    public function countJobs() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $countJobs = $this->model->countJobs();

      echo json_encode($countJobs, JSON_PRETTY_PRINT);

    }

    public function countComponents() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $countComponents = $this->model->countComponents();

      echo json_encode($countComponents, JSON_PRETTY_PRINT);

    }

    public function countComponentsTypes() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $countComponentsTypes = $this->model->countComponentsTypes();

      echo json_encode($countComponentsTypes, JSON_PRETTY_PRINT);

    }

    public function countFileUploaded() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $countFileUploaded = $this->model->countFileUploaded();
      echo $countFileUploaded[0]->file_uploaded;

    }



     public function do_upload($jobname = NULL, $component = NULL, $type = NULL) {

      $this->global['pageTitle'] = 'Job Seeker : Upload';

      if ($jobname === NULL || $component === NULL || $type === NULL) {
        $this->output->set_status_header(400);
        echo 'Upload target is missing.';
        return;
      }

      $jobname = rawurldecode($jobname);
      $component = rawurldecode($component);
      $type = rawurldecode($type);

      $records = $this->model->FetchAll($jobname, $component, $type);
      if (empty($records)) {
        $this->output->set_status_header(404);
        echo 'Upload target was not found.';
        return;
      }

      $uploadTarget = $records[0];
      $relativePath = $this->safeRelativePath($uploadTarget->file_path);
      if ($relativePath === FALSE) {
        $this->output->set_status_header(400);
        echo 'Upload target path is invalid.';
        return;
      }

    $ds = DIRECTORY_SEPARATOR;  //1
 
    $repositoryRoot = empty($this->global['jenkins_home']) ? FCPATH . 'repository' : rtrim($this->global['jenkins_home'], '/\\') . $ds . 'repository';
    $targetPath = $repositoryRoot . $ds . 'Talend' . $ds . 'input' . $ds . $relativePath . $ds;
    $fileUploaded = $this->model->fetchUploaded($jobname, $component, $type);
    $uploaded_amount = empty($fileUploaded) ? 0 : (int) $fileUploaded[0]->file_uploaded;

    if (! $this->ensureDirectory($targetPath)) {
      $this->output->set_status_header(500);
      echo 'Unable to create upload directory.';
      return;
    }

    $upload = $this->getUploadedFile('file', $this->allowedUploadExtensions($uploadTarget->component_type), 104857600);
    if (! $upload['ok']) {
      $this->output->set_status_header(400);
      echo $upload['message'];
      return;
    }

    if ((int) $uploadTarget->file === 1 && $uploadTarget->file_name !== NULL && $uploadTarget->file_name !== '') {
      $expectedName = $this->safeUploadFileName($uploadTarget->file_name . $uploadTarget->component_type);
      if ($expectedName === FALSE || $upload['safe_name'] !== $expectedName) {
        $this->output->set_status_header(400);
        echo 'Uploaded file does not match the configured file name.';
        return;
      }
    }

    $realTargetPath = realpath($targetPath);
    $targetFile = $targetPath . $upload['safe_name'];
    if ($realTargetPath === FALSE || ! $this->pathWithinBase($targetFile, $realTargetPath)) {
      $this->output->set_status_header(400);
      echo 'Upload target path is invalid.';
      return;
    }
     
    if (! move_uploaded_file($upload['tmp_name'],$targetFile)) {
      $this->output->set_status_header(500);
      echo 'Unable to store uploaded file.';
      return;
    }

        $amount = $uploaded_amount + 1;

        $this->model->add($jobname, $component, $type, $amount);

        echo 'File uploaded.';

      }

      private function allowedUploadExtensions($type) {
        $extensions = array();
        foreach (preg_split('/[\s,]+/', (string) $type) as $extension) {
          $extension = strtolower(ltrim(trim($extension), '.'));
          if ($extension !== '' && preg_match('/^[a-z0-9]+$/', $extension)) {
            $extensions[] = $extension;
          }
        }

        return $extensions;
      }

}

?>