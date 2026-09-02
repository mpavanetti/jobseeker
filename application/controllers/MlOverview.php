<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';
require APPPATH.'/controllers/concerns/MlControllerTrait.php';

/**
 * Machine Learning platform overview: engine + host capacity, in-flight runs,
 * recent model versions, open alerts and the 30-day run success rate.
 */
class MlOverview extends BaseController
{
    use MlControllerTrait;

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
        $this->load->model('MlCatalog_model', 'catalog');
        $this->load->model('MlRun_model', 'runs');
        $this->load->model('MlDataset_model', 'datasets');
        $this->load->model('MlRegistry_model', 'registry');
        $this->load->model('MlMonitoring_model', 'monitoring');
        $this->load->library('MlRunOrchestrator', array('catalog' => $this->catalog, 'runs' => $this->runs), 'orchestrator');
    }

    public function index()
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $environment = $this->mlSelectedEnvironment();
        $data = array(
            'selectedEnvironment' => $environment,
            'environments' => $this->mlActiveEnvironments(),
            'capacity' => $this->orchestrator->capacity(),
            'driverName' => $this->orchestrator->driverName(),
            'statusCounts' => $this->runs->statusCounts($environment, 30),
            'recentRuns' => $this->runs->listRuns(array('environment' => $environment), 12),
            'productionModels' => $this->registry->productionVersions($environment),
            'modelStages' => $this->registry->countByStage($environment),
            'openAlerts' => $this->monitoring->listAlerts(array('state' => 'open', 'environment' => $environment), 10),
            'openAlertCount' => $this->monitoring->openAlertCount($environment),
            'datasetStats' => $this->datasets->statistics($environment),
            'jobCount' => count($this->catalog->listJobs($environment)),
            'monitorCount' => count($this->monitoring->listMonitors($environment)),
        );
        $this->global['pageTitle'] = 'Job Seeker : ML Overview';
        $this->mlRenderView('mlOverview', $data);
    }

    /** JSON refresh for the live tiles. */
    public function pulse()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $environment = $this->mlSelectedEnvironment();
        $this->mlJson(array(
            'ok' => TRUE,
            'capacity' => $this->orchestrator->capacity(),
            'statusCounts' => $this->runs->statusCounts($environment, 30),
            'modelVersionCount' => array_sum(array_map('intval', (array) $this->registry->countByStage($environment))),
            'productionModelCount' => count($this->registry->productionVersions($environment)),
            'openAlertCount' => $this->monitoring->openAlertCount($environment),
            'activeRuns' => array_map(function ($run) {
                return array('id' => (int) $run->id, 'name' => (string) $run->name,
                    'status' => (string) $run->status, 'run_type' => (string) $run->run_type,
                    'run_key' => (string) $run->run_key);
            }, $this->runs->listRuns(array('environment' => $environment, 'status' => 'RUNNING'), 20)),
        ));
    }
}
