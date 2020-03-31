<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

/**
 * Class : Login (LoginController)
 * Login class to control to authenticate user credentials and starts user's session.
 * @author : Kishor Mali
 * @version : 1.1
 * @since : 15 November 2016
 */
class Dashboard extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('Dashboard_model','model');
        $this->load->library('session');
        $this->isLoggedIn();  
        date_default_timezone_set('America/Sao_Paulo'); 
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Talend Job Seeker : Dashboard';

        $data["lastJobs"] = $this->model->getLastjobs();
        
        $this->loadViews("dashboard", $this->global, $data, NULL);
    }

    public function query($status){

        header('Content-Type: application/json');
        $this->global['pageTitle'] = 'Talend Job Seeker : Query';

       $query = $this->model->listStatus($status);

       $newQuery = json_encode($query,JSON_PRETTY_PRINT);

       print_r($newQuery);

    }

    public function result(){

        header('Content-Type: application/json');
        $this->global['pageTitle'] = 'Talend Job Seeker : Query';

        $query = $this->model->countAll();

        $newQuery = json_encode($query,JSON_PRETTY_PRINT);

        print_r($newQuery);

    }


    public function graphMonth(){

        header('Content-Type: application/json');

        $graphReady = $this->model->graphReady();
        $graphError = $this->model->graphError();
        $graphWarning = $this->model->graphWarning();
        $graphRunning = $this->model->graphRunning();
        $months = $this->model->months();

        $readyGrowth = $this->model->readyGrowth();
        $errorGrowth = $this->model->errorGrowth();
        $warningGrowth = $this->model->warningGrowth();
        $runningGrowth = $this->model->runningGrowth();
        $statusGraph = $this->model->statusGraph();

        $result["data"] = array(

            'ready' => $graphReady,
            'error' => $graphError,
            'warning' => $graphWarning,
            'running' => $graphRunning,
            'months' => $months,
            'readyGrowth' => $readyGrowth,
            'errorGrowth' => $errorGrowth,
            'warningGrowth' => $warningGrowth,
            'runningGrowth' => $runningGrowth,
            'statusGraph' => $statusGraph

        );

        print_r(JSON_encode($result, JSON_PRETTY_PRINT));

    }

    public function getDate(){
        header('Content-Type: application/json');

         $firstDate = $this->model->firstDate();
        $lastDate = $this->model->lastDate();

         $result["data"] = array(

            'firstDate' => $firstDate,
            'lastDate' => $lastDate

        );

        print_r(JSON_encode($result, JSON_PRETTY_PRINT));

    }

 
    
}

?>