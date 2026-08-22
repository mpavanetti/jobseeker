<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


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

    private function selectedEnvironmentFilter()
    {
        $environment = trim((string) $this->security->xss_clean($this->input->get('environment')));

        if ($environment === '' || $environment === '*' || strtolower($environment) === 'all') {
            return 'all';
        }

        if ($environment === '__UNKNOWN__' || strtolower($environment) === 'unknown') {
            return '__UNKNOWN__';
        }

        return strtoupper($environment);
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : Dashboard';
        $selectedEnvironment = $this->selectedEnvironmentFilter();
        $this->global['selectedEnvironment'] = $selectedEnvironment;

        $data["selectedEnvironment"] = $selectedEnvironment;
        $data["lastJobs"] = $this->model->getLastjobs($selectedEnvironment);
        $data["jobsAmount"] = $this->model->jobsAmount($selectedEnvironment);
        $data["jobsStatusAmount"] = $this->model->jobsStatusAmount($selectedEnvironment);
        $data["environmentSummary"] = $this->model->environmentSummary($selectedEnvironment);
        
        $this->loadViews("dashboard", $this->global, $data, NULL);
    }

    public function query($status){

        header('Content-Type: application/json');
        $this->global['pageTitle'] = 'Job Seeker : Query';
        $selectedEnvironment = $this->selectedEnvironmentFilter();

        $query = $this->model->listStatus($status, $selectedEnvironment);

       $newQuery = json_encode($query,JSON_PRETTY_PRINT);

    echo $newQuery;

    }

    public function result(){

        header('Content-Type: application/json');
        $this->global['pageTitle'] = 'Job Seeker : Query';
        $selectedEnvironment = $this->selectedEnvironmentFilter();

        $query = $this->model->countAll($selectedEnvironment);

        $newQuery = json_encode($query,JSON_PRETTY_PRINT);

        echo $newQuery;

    }

    public function graphMonth(){

        header('Content-Type: application/json');
        $selectedEnvironment = $this->selectedEnvironmentFilter();

        $graphReady = $this->model->graphReady($selectedEnvironment);
        $graphError = $this->model->graphError($selectedEnvironment);
        $graphWarning = $this->model->graphWarning($selectedEnvironment);
        $graphRunning = $this->model->graphRunning($selectedEnvironment);
        $months = $this->model->months($selectedEnvironment);

        $readyGrowth = $this->model->readyGrowth($selectedEnvironment);
        $errorGrowth = $this->model->errorGrowth($selectedEnvironment);
        $warningGrowth = $this->model->warningGrowth($selectedEnvironment);
        $runningGrowth = $this->model->runningGrowth($selectedEnvironment);
        $statusGraph = $this->model->statusGraph($selectedEnvironment);

        $readyGrowthX90 = $this->model->readyGrowthX90($selectedEnvironment);
        $errorGrowthX90 = $this->model->errorGrowthX90($selectedEnvironment);
        $warningGrowthX90 = $this->model->warningGrowthX90($selectedEnvironment);
        $runningGrowthX90 = $this->model->runningGrowthX90($selectedEnvironment);

        $readyGrowthX180 = $this->model->readyGrowthX180($selectedEnvironment);
        $errorGrowthX180 = $this->model->errorGrowthX180($selectedEnvironment);
        $warningGrowthX180 = $this->model->warningGrowthX180($selectedEnvironment);
        $runningGrowthX180 = $this->model->runningGrowthX180($selectedEnvironment);

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

            'readyGrowthX90' => $readyGrowthX90,
            'errorGrowthX90' => $errorGrowthX90,
            'warningGrowthX90' => $warningGrowthX90,
            'runningGrowthX90' => $runningGrowthX90,

            'readyGrowthX180' => $readyGrowthX180,
            'errorGrowthX180' => $errorGrowthX180,
            'warningGrowthX180' => $warningGrowthX180,
            'runningGrowthX180' => $runningGrowthX180,

            'statusGraph' => $statusGraph

        );

        echo json_encode($result, JSON_PRETTY_PRINT);

    }

    public function getDate(){
        header('Content-Type: application/json');
        $selectedEnvironment = $this->selectedEnvironmentFilter();

         $firstDate = $this->model->firstDate($selectedEnvironment);
        $lastDate = $this->model->lastDate($selectedEnvironment);

         $result["data"] = array(

            'firstDate' => $firstDate,
            'lastDate' => $lastDate

        );

        echo json_encode($result, JSON_PRETTY_PRINT);

    }

    public function getAmount(){
        header('Content-Type: application/json');
        $selectedEnvironment = $this->selectedEnvironmentFilter();

        $stgTableAmount = $this->model->stgTableAmount($selectedEnvironment);
        $dimTableAmount = $this->model->dimTableAmount($selectedEnvironment);
        $factTableAmount = $this->model->factTableAmount($selectedEnvironment);
        $dwAmount = $this->model->dwAmount($selectedEnvironment);
        $dmAmount = $this->model->dmAmount($selectedEnvironment);
        $dmAmountExec = $this->model->dmAmountExec($selectedEnvironment);

        $dimAmountExec = $this->model->dimAmountExec($selectedEnvironment);
        $factAmountExec = $this->model->factAmountExec($selectedEnvironment);
        $stgAmountExec = $this->model->stgAmountExec($selectedEnvironment);

         $result["data"] = array(

            'stgTableAmount' => $stgTableAmount,
            'dimTableAmount' => $dimTableAmount,
            'factTableAmount' => $factTableAmount,
            'dwAmount' => $dwAmount,
            'dmAmount' => $dmAmount,
            'dmAmountExec' => $dmAmountExec,
            'dimAmountExec' => $dimAmountExec,
            'factAmountExec' => $factAmountExec,
            'stgAmountExec' => $stgAmountExec

        );

        echo json_encode($result, JSON_PRETTY_PRINT);

    }

    
}

?>