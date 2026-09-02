<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';
require APPPATH.'/controllers/concerns/MlControllerTrait.php';

/**
 * Experiments, the run list and the run-detail view (metric charts, params,
 * artifacts, logs, lineage). Also serves the experiment "compare runs" grid.
 */
class MlRuns extends BaseController
{
    use MlControllerTrait;

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
        $this->load->model('MlCatalog_model', 'catalog');
        $this->load->model('MlRun_model', 'runs');
        $this->load->model('MlRegistry_model', 'registry');
        $this->load->model('MlDataset_model', 'datasets');
        $this->load->library('MlRunOrchestrator', array('catalog' => $this->catalog, 'runs' => $this->runs), 'orchestrator');
    }

    public function index()
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $environment = $this->mlSelectedEnvironment();
        $filters = array('environment' => $environment);
        foreach (array('experiment_id', 'job_id', 'run_type', 'status') as $key) {
            if ($this->input->get($key) !== NULL && $this->input->get($key) !== '') {
                $filters[$key] = $this->input->get($key);
            }
        }
        $this->global['pageTitle'] = 'Job Seeker : ML Runs';
        $this->mlRenderView('mlRuns', array(
            'selectedEnvironment' => $environment,
            'environments' => $this->mlActiveEnvironments(),
            'experiments' => $this->catalog->listExperiments($environment),
            'runs' => $this->runs->listRuns($filters, 200),
            'filters' => $filters,
        ));
    }

    public function detail($id)
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $run = $this->runs->getRun((int) $id);
        if (! $run) {
            show_404();
            return;
        }
        if (! in_array($run->status, MlRunOrchestrator::TERMINAL, TRUE)) {
            $run = $this->orchestrator->advance($run);
        }
        $lineage = $this->runs->neighbourhood('run', (int) $run->id);
        $this->global['pageTitle'] = 'Job Seeker : Run '.substr($run->run_key, 0, 8);
        $this->mlRenderView('mlRunDetail', array(
            'run' => $run,
            'job' => $run->job_id ? $this->catalog->getJob((int) $run->job_id) : NULL,
            'experiment' => $run->experiment_id ? $this->catalog->getExperiment((int) $run->experiment_id) : NULL,
            'metricSeries' => $this->runs->metricSeries((int) $run->id),
            'latestMetrics' => $this->runs->latestMetrics((int) $run->id),
            'params' => $this->mlDecodeJson($run->params_json),
            'tags' => $this->mlDecodeJson($run->tags_json),
            'artifacts' => $this->runs->runArtifacts((int) $run->id),
            'introspection' => $run->job_id ? $this->mlDecodeJson(optional_get($this->catalog->getJob((int) $run->job_id), 'introspection_json')) : array(),
            'lineage' => $lineage,
            'inputDatasets' => $this->datasetsFromEdges($lineage['in'], 'dataset_version', 'src'),
            'outputDatasets' => $this->datasetsFromEdges($lineage['out'], 'dataset_version', 'dst'),
            'modelVersions' => $this->modelVersionsFromEdges($lineage['out']),
            'logs' => $this->orchestrator->liveLogs($run),
        ));
    }

    /** Resolve dataset-version lineage edges into displayable dataset cards. */
    private function datasetsFromEdges($edges, $kind, $side)
    {
        $out = array();
        foreach ($edges as $edge) {
            $ek = $side === 'src' ? $edge->src_kind : $edge->dst_kind;
            $eid = $side === 'src' ? $edge->src_id : $edge->dst_id;
            if ($ek !== $kind) {
                continue;
            }
            $version = $this->datasets->getVersion((int) $eid);
            if (! $version) {
                continue;
            }
            $dataset = $this->datasets->getDataset((int) $version->dataset_id);
            $prev = ($version->version > 1) ? $this->datasets->getVersionByNumber((int) $version->dataset_id, (int) $version->version - 1) : NULL;
            $drift = NULL;
            if ($prev && $prev->fingerprint_json && $version->fingerprint_json) {
                require_once APPPATH.'libraries/MlDriftAnalyzer.php';
                $a = new MlDriftAnalyzer();
                $cmp = $a->compare($this->mlDecodeJson($prev->fingerprint_json), $this->mlDecodeJson($version->fingerprint_json));
                $drift = $cmp['overall'];
            }
            $out[] = array(
                'role' => preg_replace('/^(input|output):/', '', (string) $edge->role),
                'dataset_id' => (int) $version->dataset_id,
                'name' => $dataset ? (string) $dataset->name : ('dataset '.$version->dataset_id),
                'key' => $dataset ? (string) $dataset->dataset_key : '',
                'version' => (int) $version->version,
                'row_count' => $version->row_count === NULL ? NULL : (int) $version->row_count,
                'format' => (string) $version->format,
                'schema' => $this->mlDecodeJson($version->schema_json),
                'version_id' => (int) $version->id,
                'drift_vs_prev' => $drift,
            );
        }
        return $out;
    }

    private function modelVersionsFromEdges($edges)
    {
        $out = array();
        foreach ($edges as $edge) {
            if ($edge->dst_kind !== 'model_version') {
                continue;
            }
            $mv = $this->registry->getVersion((int) $edge->dst_id);
            if (! $mv) {
                continue;
            }
            $model = $this->registry->getModel((int) $mv->model_id);
            $out[] = array('id' => (int) $mv->id, 'version' => (int) $mv->version, 'stage' => (string) $mv->stage,
                'name' => $model ? (string) $model->name : 'model', 'metrics' => $this->mlDecodeJson($mv->metrics_json));
        }
        return $out;
    }

    public function status($id)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $run = $this->runs->getRun((int) $id);
        if (! $run) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        if (! in_array($run->status, MlRunOrchestrator::TERMINAL, TRUE)) {
            $run = $this->orchestrator->advance($run);
        }
        $series = $this->runs->metricSeries((int) $run->id);
        $this->mlJson(array(
            'ok' => TRUE,
            'status' => (string) $run->status,
            'terminal' => in_array($run->status, MlRunOrchestrator::TERMINAL, TRUE),
            'metricSeries' => $series,
            'latestMetrics' => $this->runs->latestMetrics((int) $run->id),
            'stats' => $this->orchestrator->runStats($run),
            'hints' => $this->trainingHints($series),
            'logs' => $this->orchestrator->liveLogs($run),
        ));
    }

    /** Cheap "loss rising / metric plateaued" hints for the live run monitor. */
    private function trainingHints($series)
    {
        $hints = array();
        foreach ($series as $key => $points) {
            if (count($points) < 4) {
                continue;
            }
            $tail = array_slice(array_map(function ($p) { return (float) $p['value']; }, $points), -4);
            $rising = $tail[3] > $tail[2] && $tail[2] > $tail[1] && $tail[1] > $tail[0];
            $falling = $tail[3] < $tail[2] && $tail[2] < $tail[1] && $tail[1] < $tail[0];
            $flat = abs($tail[3] - $tail[0]) < 1e-4;
            if (preg_match('/loss|error|rmse|mae/i', $key) && $rising) {
                $hints[] = array('level' => 'warning', 'text' => $key.' has risen 3 steps running - consider early stopping.');
            } elseif (preg_match('/acc|auc|f1|r2|score/i', $key) && $falling) {
                $hints[] = array('level' => 'warning', 'text' => $key.' is trending down over the last 3 steps.');
            } elseif ($flat && count($points) >= 6) {
                $hints[] = array('level' => 'info', 'text' => $key.' has plateaued.');
            }
        }
        return $hints;
    }

    /** In-flight ML runs for the sidebar running-jobs widget. */
    public function active()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE, 'runs' => array()), 403);
            return;
        }
        $environment = $this->mlSelectedEnvironment();
        $runs = array();
        foreach ($this->runs->listRuns(array('environment' => $environment, 'status' => 'RUNNING'), 20) as $run) {
            $runs[] = array(
                'id' => (int) $run->id, 'run_key' => (string) $run->run_key, 'name' => (string) $run->name,
                'run_type' => (string) $run->run_type, 'environment' => (string) $run->environment,
                'started_at' => (string) $run->started_at,
                'url' => base_url('machine-learning/runs/detail/'.(int) $run->id),
            );
        }
        foreach ($this->runs->listRuns(array('environment' => $environment, 'status' => 'QUEUED'), 10) as $run) {
            $runs[] = array(
                'id' => (int) $run->id, 'run_key' => (string) $run->run_key, 'name' => (string) $run->name,
                'run_type' => (string) $run->run_type, 'environment' => (string) $run->environment,
                'started_at' => (string) $run->queued_at, 'queued' => TRUE,
                'url' => base_url('machine-learning/runs/detail/'.(int) $run->id),
            );
        }
        $this->mlJson(array('ok' => TRUE, 'runs' => $runs));
    }

    /** Experiment compare grid: JSON of runs x metric keys. */
    public function compare($experimentId)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $experiment = $this->catalog->getExperiment((int) $experimentId);
        if (! $experiment) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Experiment not found.'), 404);
            return;
        }
        $runs = $this->runs->listRuns(array('experiment_id' => (int) $experimentId), 100);
        $runIds = array_map(function ($r) { return (int) $r->id; }, $runs);
        $metricKeys = $this->runs->metricKeysForRuns($runIds);
        $rows = array();
        foreach ($runs as $run) {
            $latest = $this->runs->latestMetrics((int) $run->id);
            $rows[] = array(
                'id' => (int) $run->id,
                'run_key' => (string) $run->run_key,
                'name' => (string) $run->name,
                'run_type' => (string) $run->run_type,
                'status' => (string) $run->status,
                'started_at' => (string) $run->started_at,
                'params' => $this->mlDecodeJson($run->params_json),
                'metrics' => $latest,
            );
        }
        $this->mlJson(array(
            'ok' => TRUE,
            'experiment' => array('name' => $experiment->name, 'primary_metric' => $experiment->primary_metric,
                'metric_goal' => $experiment->metric_goal),
            'metric_keys' => $metricKeys,
            'runs' => $rows,
        ));
    }

    public function registerModelFromRun()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $run = $this->runs->getRun((int) $this->input->post('run_id'));
        if (! $run) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        $modelArtifact = NULL;
        foreach ($this->runs->runArtifacts((int) $run->id) as $artifact) {
            if ($artifact->role === 'model') {
                $modelArtifact = $artifact;
                break;
            }
        }
        if (! $modelArtifact) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'This run has no "model" artifact to register.'), 422);
            return;
        }
        $modelKey = $this->mlSlug(trim((string) $this->input->post('model_key')) ?: (string) $run->name);
        $modelId = $this->registry->findOrCreateModel($modelKey, trim((string) $this->input->post('name')) ?: $modelKey,
            $run->environment, (string) $this->name, trim((string) $this->input->post('task')) ?: 'classification');
        $created = $this->registry->createVersion($modelId, array(
            'run_id' => (int) $run->id,
            'artifact_id' => (int) $modelArtifact->artifact_id,
            'metrics_json' => $run->metrics_summary_json,
            'params_json' => $run->params_json,
            'created_by' => (string) $this->name,
        ));
        $this->runs->addEdge('run', (int) $run->id, 'model_version', $created['id'], 'produces');
        $this->mlJson(array('ok' => TRUE, 'model_id' => $modelId, 'model_version_id' => $created['id'],
            'version' => $created['version'], 'message' => 'Registered as '.$modelKey.' v'.$created['version'].'.'));
    }

    public function saveExperiment()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name', TRUE));
        if ($name === '') {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Experiment name is required.'), 422);
            return;
        }
        $now = date('Y-m-d H:i:s');
        $data = array(
            'name' => $name,
            'environment' => strtoupper(trim((string) $this->input->post('environment'))) ?: 'ALL',
            'description' => trim((string) $this->input->post('description', TRUE)) ?: NULL,
            'primary_metric' => trim((string) $this->input->post('primary_metric', TRUE)) ?: NULL,
            'metric_goal' => $this->input->post('metric_goal') === 'min' ? 'min' : 'max',
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'updated_at' => $now,
        );
        if ($id <= 0) {
            $data['experiment_key'] = $this->mlSlug($name);
            $data['created_at'] = $now;
            $data['owner'] = (string) $this->name;
        }
        $savedId = $this->catalog->saveExperiment($data, $id);
        $this->mlJson(array('ok' => TRUE, 'id' => $savedId, 'message' => $id > 0 ? 'Experiment updated.' : 'Experiment created.'));
    }
}

if (! function_exists('optional_get')) {
    function optional_get($object, $property)
    {
        return $object && isset($object->$property) ? $object->$property : '';
    }
}
