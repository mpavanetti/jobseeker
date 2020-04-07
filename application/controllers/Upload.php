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
        print_r(json_encode($listJobsJson, JSON_PRETTY_PRINT));

     
    }

     public function listComponents($jobname) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $listComponent = $this->model->listComponents($jobname);

      print_r(json_encode($listComponent, JSON_PRETTY_PRINT));

    }

    public function listComponentType($jobname, $component) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $listComponentType = $this->model->listComponentType($jobname, $component);

      print_r(json_encode($listComponentType, JSON_PRETTY_PRINT));

    }

    public function listComponentPath($jobname, $component, $type) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $listComponentPath = $this->model->listComponentPath($jobname, $component, $type);

      print_r(json_encode($listComponentPath, JSON_PRETTY_PRINT));

       $FetchAll = $this->model->FetchAll($jobname, $component, $type);

      $fp = fopen(__DIR__ . '/../../json/result.json','w');
      fwrite($fp, json_encode($FetchAll, JSON_PRETTY_PRINT));
      fclose($fp);


    }

    public function listAll($jobname, $component, $type) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';


      $listAll = $this->model->listAll($jobname, $component, $type);

      print_r(json_encode($listAll, JSON_PRETTY_PRINT));

      
    }

    public function Path($jobname, $component, $type) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';


      $Path = $this->model->Path($jobname, $component, $type);

      print_r(json_encode($Path, JSON_PRETTY_PRINT));

      
    }

    public function countJobs() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $countJobs = $this->model->countJobs();

      print_r(json_encode($countJobs, JSON_PRETTY_PRINT));

    }

    public function countComponents() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $countComponents = $this->model->countComponents();

      print_r(json_encode($countComponents, JSON_PRETTY_PRINT));

    }

    public function countComponentsTypes() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $countComponentsTypes = $this->model->countComponentsTypes();

      print_r(json_encode($countComponentsTypes, JSON_PRETTY_PRINT));

    }

    public function countFileUploaded() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Job Seeker : Json Parse';

      $countFileUploaded = $this->model->countFileUploaded();
      print_r($countFileUploaded[0]->file_uploaded);

    }



     public function do_upload() {

      $this->global['pageTitle'] = 'Job Seeker : Upload';

      // Get the contents of the JSON file 
      $strJsonFileContents = file_get_contents(__DIR__ . '/../../json/result.json');
      $array = json_decode($strJsonFileContents, true);
     
     // echo $array[0]["file_path"];

    $ds = DIRECTORY_SEPARATOR;  //1
 
    $storeFolder = '../../repository/Talend/input/'.$array[0]["file_path"].'/';   //2
    $test = 'repository/Talend/input/'.$array[0]["file_path"];
    $jobname = $array[0]["job_name"];
    $component = $array[0]["job_component"];
    $type = $array[0]["component_type"];
    $fileUploaded = $this->model->fetchUploaded($jobname, $component, $type);
    $uploaded_amount = $fileUploaded[0]->file_uploaded;

    if (!file_exists($test)) {
     mkdir($test);
    } 

    if (!empty($_FILES)) {
         
        $tempFile = $_FILES['file']['tmp_name'];          //3             
          
        $targetPath = dirname( __FILE__ ) . $ds. $storeFolder . $ds;  //4
         
        $targetFile =  $targetPath. $_FILES['file']['name'];  //5
     
        move_uploaded_file($tempFile,$targetFile); //6

        $amount = $uploaded_amount + 1;

        $this->model->add($jobname, $component, $type, $amount);

        }

      }

}

?>