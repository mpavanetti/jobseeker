<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

/**
 * Class : Login (LoginController)
 * Login class to control to authenticate user credentials and starts user's session.
 * @author : Kishor Mali
 * @version : 1.1
 * @since : 15 November 2016
 */
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

        $this->global['pageTitle'] = 'Talend Job Seeker : File Upload';
        
        $this->loadViews("upload", $this->global, NULL, NULL);
    }

    function listJobsJson() {
        header('Content-type:application/json;charset=utf-8'); // declaring header


        $this->global['pageTitle'] = 'Talend Job Seeker : Json Parse';

        $listJobsJson = $this->model->listJobs();
        print_r(json_encode($listJobsJson, JSON_PRETTY_PRINT));

     
    }

     public function listComponents($jobname) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Talend Job Seeker : Json Parse';

      $listComponent = $this->model->listComponents($jobname);

      print_r(json_encode($listComponent, JSON_PRETTY_PRINT));

    }

    public function listComponentType($component) {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Talend Job Seeker : Json Parse';

      $listComponentType = $this->model->listComponentType($component);

      print_r(json_encode($listComponentType, JSON_PRETTY_PRINT));

    }

    public function countJobs() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Talend Job Seeker : Json Parse';

      $countJobs = $this->model->countJobs();

      print_r(json_encode($countJobs, JSON_PRETTY_PRINT));

    }

    public function countComponents() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Talend Job Seeker : Json Parse';

      $countComponents = $this->model->countComponents();

      print_r(json_encode($countComponents, JSON_PRETTY_PRINT));

    }

    public function countComponentsTypes() {
     header('Content-Type: application/json');

      $this->global['pageTitle'] = 'Talend Job Seeker : Json Parse';

      $countComponentsTypes = $this->model->countComponentsTypes();

      print_r(json_encode($countComponentsTypes, JSON_PRETTY_PRINT));

    }


   
    
}

?>