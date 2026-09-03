<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class Tmf extends BaseController
{
    /**
     * This is default constructor of the class
     */
   public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('Tmf_model','model');
        $this->load->library('session');
        $this->isLoggedIn();
    }


    private function normalizeEnvironmentSelectionValue($environment)
    {
        $environment = trim((string) $this->security->xss_clean($environment));

        if ($environment === '' || $environment === '*' || strtolower($environment) === 'all') {
            return 'all';
        }

        if ($environment === '__UNKNOWN__' || strtolower($environment) === 'unknown') {
            return '__UNKNOWN__';
        }

        return strtoupper($environment);
    }

    private function selectedEnvironmentFilter()
    {
		if ($this->jobSeekerIsStandaloneDeployment()) {
			return $this->jobSeekerStandaloneEnvironment();
		}
        $environment = trim((string) $this->input->get('environment', TRUE));
        if ($environment === '') {
            $environment = $this->jobSeekerEnvironmentPreference();
        }

        return $this->normalizeEnvironmentSelectionValue($environment);
    }

    private function selectedEnvironmentFromSelection($environment)
    {
		if ($this->jobSeekerIsStandaloneDeployment()) {
			return $this->jobSeekerStandaloneEnvironment();
		}
        if (! is_array($environment)) {
            return $this->normalizeEnvironmentSelectionValue($environment);
        }

        $selected = array();
        foreach ($environment as $value) {
            $normalized = $this->normalizeEnvironmentSelectionValue($value);
            if ($normalized === 'all') {
                return 'all';
            }
            if (! in_array($normalized, $selected, TRUE)) {
                $selected[] = $normalized;
            }
        }

        return count($selected) === 1 ? $selected[0] : 'all';
    }

    private function canManageTmf()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function addResultWindowMetadata(&$data)
    {
        $data['resultLimit'] = $this->model->resultLimit();
        $data['resultsTruncated'] = $this->model->lastResultWasTruncated();
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {

        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
          $selectedEnvironment = $this->selectedEnvironmentFilter();

          $data["listStatus"] = $this->model->listStatus($selectedEnvironment);
          $data["listJobName"] = $this->model->listJobName($selectedEnvironment);
          $data["listDimension"] = $this->model->listDimension($selectedEnvironment);
          $data["listReprocess"] = $this->model->listReprocess($selectedEnvironment);
		  $data["listEnvironment"] = $this->jobSeekerFilterEnvironmentRows($this->model->listEnvironment(), 'environment');
          $data["selectedEnvironment"] = $selectedEnvironment;
          $this->global['selectedEnvironment'] = $data["selectedEnvironment"];



        $this->loadViews("tmfBuilder", $this->global, $data, NULL);
    }

    public function data() {

        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';

        $environment = $this->selectedEnvironmentFilter();
        $data["jobs"] = $this->model->list($environment);
        $this->addResultWindowMetadata($data);
        $data["role"] = $this->role;
        $data["selectedEnvironment"] = $environment;
        $this->global['selectedEnvironment'] = $environment;

        $this->loadViews("tmf", $this->global, $data, NULL);

    }

     function fetchData()
    {
            $status = $this->input->post('status');
            $job_name = $this->input->post('job_name');
            $dimension = $this->input->post('dimension');
            $reprocess = $this->input->post('reprocess');
            $fromDate = $this->input->post('fromDate');
            $toDate = $this->input->post('toDate');
            $eventText = trim((string) $this->security->xss_clean($this->input->post('eventText')));
            if (strlen($eventText) > 200) {
                $eventText = substr($eventText, 0, 200);
            }
            $environment = $this->input->post('environment');
            $globalEnvironment = $this->selectedEnvironmentFilter();
            if ($globalEnvironment !== 'all') {
                // The global environment is an authoritative backend scope;
                // form values may narrow an all-environment view but cannot
                // broaden a concrete environment selected in the header.
                $environment = array($globalEnvironment);
            }

            $data["jobs"] = $this->model->listJobs($status,$job_name,$dimension,$reprocess,$eventText,$fromDate,$toDate,$environment);
            $this->addResultWindowMetadata($data);
            $data["role"] = $this->role;
            $data["selectedEnvironment"] = $globalEnvironment !== 'all' ? $globalEnvironment : $this->selectedEnvironmentFromSelection($environment);

            $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
            $this->global['selectedEnvironment'] = $data["selectedEnvironment"];
            $this->loadViews("tmf", $this->global, $data, NULL);

    }

    function fetchDataStatus($status)
    {
            $environment = $this->selectedEnvironmentFilter();
            $data["jobs"] = $this->model->fetchDataStatus($status, $environment);
            $this->addResultWindowMetadata($data);
            $data["role"] = $this->role;
            $data["selectedEnvironment"] = $environment;
            $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
            $this->global['selectedEnvironment'] = $environment;
            $this->loadViews("tmf", $this->global, $data, NULL);

    }

     function fetchDataJobName($jobName)
    {
            $environment = $this->selectedEnvironmentFilter();
            $data["jobs"] = $this->model->fetchDataJobName($jobName, $environment);
            $this->addResultWindowMetadata($data);
            $data["role"] = $this->role;
            $data["selectedEnvironment"] = $environment;
            $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
            $this->global['selectedEnvironment'] = $environment;
            $this->loadViews("tmf", $this->global, $data, NULL);

    }

    function getError($instanceId)
    {
        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
        $errorList["data"] = $this->model->getError($instanceId, $this->selectedEnvironmentFilter());


          echo json_encode($errorList, JSON_PRETTY_PRINT);
    }

     function listId($id)
    {
        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
        $list["data"] = $this->model->listId($id, $this->selectedEnvironmentFilter());


          echo json_encode($list, JSON_PRETTY_PRINT);
    }


     function updateUser($instanceId,$name)
    {
        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
        $errorList["data"] = $this->model->updateUser($instanceId,$name);

    }

     function updateStatus($id,$status)
    {
        $this->global['pageTitle'] = 'Job Seeker : Transaction Monitoring Framework';
        $errorList["data"] = $this->model->updateStatus($id,$status,$this->selectedEnvironmentFilter());

        echo "Ok";

    }

      /**
     * This function is used to delete the data using id
     * @return boolean $result : TRUE / FALSE
     */
    function delete()
    {
        if(! $this->canManageTmf())
        {
            echo(json_encode(array('status'=>'access')));
            return;
        }

        if($this->input->method(TRUE) !== 'POST') {
            $this->output->set_status_header(405);
            echo(json_encode(array('status'=>FALSE, 'message'=>'Delete requests must use POST.')));
            return;
        }

        $id = (int) $this->input->post('userId');
        if ($id <= 0) {
            echo(json_encode(array('status'=>FALSE, 'id' => $id)));
            return;
        }

        $environment = $this->selectedEnvironmentFilter();
        $deletePolicy = $this->model->deletePolicy($id, $environment);
        if (empty($deletePolicy['exists'])) {
            echo(json_encode(array('status'=>FALSE, 'id' => $id)));
            return;
        }

        if (empty($deletePolicy['allowed'])) {
            echo(json_encode(array(
                'status'=>'restricted',
                'id' => $id,
                'message' => 'Only DEV TMF rows or stale running TMF rows can be deleted.'
            )));
            return;
        }

        $result = $this->model->delete($id, $environment);

        if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
        else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
    }




}

?>
