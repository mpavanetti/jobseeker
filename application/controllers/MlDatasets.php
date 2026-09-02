<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'/libraries/BaseController.php';
require APPPATH.'/controllers/concerns/MlControllerTrait.php';

/**
 * ML-facing view over the unified Data Assets store (v2). A "dataset" is a
 * `data_assets` row; a "version" is an immutable `data_asset_versions` row with
 * schema / profile / drift fingerprint. Any data asset can be bound to an ML
 * job. Version files live in the shared repository/data-assets tree so the
 * existing preview + runtime manifest work on them.
 */
class MlDatasets extends BaseController
{
    use MlControllerTrait;

    private $formats = array('csv', 'tsv', 'json', 'jsonl', 'parquet');

    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
        $this->load->helper(array('form'));
        $this->load->model('MlDataset_model', 'datasets');
        $this->load->model('MlRun_model', 'runs');
        require_once APPPATH.'libraries/MlDatasetProfiler.php';
    }

    // --- screens ------------------------------------------------------

    public function index()
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $environment = $this->mlSelectedEnvironment();
        $this->load->library('MlConnectorQuery', array(), 'connectorQuery');
        $this->global['pageTitle'] = 'Job Seeker : ML Datasets';
        $this->mlRenderView('mlDatasets', array(
            'selectedEnvironment' => $environment,
            'environments' => $this->mlActiveEnvironments(),
            'datasets' => $this->decorateList($this->datasets->listDatasets($environment)),
            'statistics' => $this->datasets->statistics($environment),
            'connectors' => $this->connectorQuery->listConnectors($environment),
        ));
    }

    public function explore($id)
    {
        if (! $this->mlCanManage()) {
            $this->loadThis();
            return;
        }
        $dataset = $this->datasets->getDataset((int) $id);
        if (! $dataset) {
            show_404();
            return;
        }
        $versions = $this->datasets->listVersions((int) $id, 100);
        $selected = (int) $this->input->get('version');
        $version = $selected > 0 ? $this->datasets->getVersionByNumber((int) $id, $selected)
            : $this->datasets->latestVersion((int) $id);
        $this->global['pageTitle'] = 'Job Seeker : Dataset '.$dataset->name;
        $this->mlRenderView('mlDatasetExplore', array(
            'dataset' => $dataset,
            'versions' => $versions,
            'version' => $version,
            'profile' => $version ? $this->mlDecodeJson($version->profile_json) : array(),
            'schema' => $version ? $this->mlDecodeJson($version->schema_json) : array(),
            'lineage' => $version ? $this->runs->neighbourhood('dataset_version', (int) $version->id) : array('in' => array(), 'out' => array()),
        ));
    }

    // --- JSON ------------------------------------------------------

    public function listDatasets()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $this->mlJson(array('ok' => TRUE, 'datasets' => $this->decorateList($this->datasets->listDatasets($this->mlSelectedEnvironment()))));
    }

    /** Rich picker payload for the job builder: assets + latest-version schema. */
    public function pick()
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $out = array();
        foreach ($this->datasets->listDatasets($this->mlSelectedEnvironment()) as $ds) {
            $latest = $this->datasets->latestVersion((int) $ds->id);
            $out[] = array(
                'id' => (int) $ds->id,
                'key' => (string) $ds->dataset_key,
                'name' => (string) $ds->name,
                'kind' => (string) $ds->kind,
                'environment' => (string) $ds->environment,
                'latest_version' => (int) $ds->latest_version,
                'rows' => $latest ? (int) $latest->row_count : NULL,
                'format' => $latest ? (string) $latest->format : (string) $ds->format,
                'schema' => $latest ? $this->mlDecodeJson($latest->schema_json) : $this->mlDecodeJson($ds->schema_json),
            );
        }
        $this->mlJson(array('ok' => TRUE, 'datasets' => $out));
    }

    public function versionProfile($versionId)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $version = $this->datasets->getVersion((int) $versionId);
        if (! $version) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Version not found.'), 404);
            return;
        }
        $this->mlJson(array(
            'ok' => TRUE,
            'version' => $version,
            'profile' => $this->mlDecodeJson($version->profile_json),
            'schema' => $this->mlDecodeJson($version->schema_json),
            'fingerprint' => $this->mlDecodeJson($version->fingerprint_json),
        ));
    }

    public function compareVersions($datasetId)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $a = $this->datasets->getVersionByNumber((int) $datasetId, (int) $this->input->get('a'));
        $b = $this->datasets->getVersionByNumber((int) $datasetId, (int) $this->input->get('b'));
        if (! $a || ! $b) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Pick two existing versions.'), 422);
            return;
        }
        require_once APPPATH.'libraries/MlDriftAnalyzer.php';
        $analyzer = new MlDriftAnalyzer();
        $this->mlJson(array(
            'ok' => TRUE,
            'a' => array('version' => (int) $a->version, 'row_count' => $a->row_count, 'fingerprint' => $this->mlDecodeJson($a->fingerprint_json)),
            'b' => array('version' => (int) $b->version, 'row_count' => $b->row_count, 'fingerprint' => $this->mlDecodeJson($b->fingerprint_json)),
            'drift' => $analyzer->compare($this->mlDecodeJson($a->fingerprint_json), $this->mlDecodeJson($b->fingerprint_json)),
        ));
    }

    /** Paginated row preview of a version file (CSV / JSON / JSONL). */
    public function preview($versionId)
    {
        if (! $this->mlCanManage()) {
            $this->mlJson(array('ok' => FALSE), 403);
            return;
        }
        $version = $this->datasets->getVersion((int) $versionId);
        $path = $version ? $this->datasets->versionAbsolutePath($version) : FALSE;
        if ($path === FALSE) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'This version has no readable file (binary format or profiling deferred).'), 404);
            return;
        }
        $offset = max(0, (int) $this->input->get('offset'));
        $limit = max(1, min(200, (int) $this->input->get('limit') ?: 50));
        $format = strtolower((string) $version->format);
        $rows = array();
        $columns = array();

        if ($format === 'csv' || $format === 'tsv') {
            $delim = $format === 'tsv' ? "\t" : ',';
            $h = fopen($path, 'rb');
            $i = 0;
            $header = fgetcsv($h, 0, $delim);
            $columns = is_array($header) ? array_map('strval', $header) : array();
            while (($rec = fgetcsv($h, 0, $delim)) !== FALSE) {
                if ($i++ < $offset) { continue; }
                if (count($rows) >= $limit) { break; }
                $rows[] = array_map(function ($v) { return $v === NULL ? '' : (strlen($v) > 200 ? substr($v, 0, 197).'...' : $v); }, $rec);
            }
            fclose($h);
        } elseif ($format === 'jsonl' || $format === 'ndjson') {
            $h = fopen($path, 'rb');
            $i = 0;
            while (($line = fgets($h)) !== FALSE) {
                $line = trim($line);
                if ($line === '') { continue; }
                if ($i++ < $offset) { continue; }
                if (count($rows) >= $limit) { break; }
                $d = json_decode($line, TRUE);
                if (is_array($d)) {
                    foreach (array_keys($d) as $k) { if (! in_array((string) $k, $columns, TRUE)) { $columns[] = (string) $k; } }
                    $rows[] = $d;
                }
            }
            fclose($h);
            $rows = array_map(function ($r) use ($columns) {
                $o = array();
                foreach ($columns as $c) { $v = isset($r[$c]) ? $r[$c] : ''; $o[] = is_array($v) ? json_encode($v) : $v; }
                return $o;
            }, $rows);
        } else {
            $this->mlJson(array('ok' => FALSE, 'message' => strtoupper($format).' preview is not supported inline.'), 415);
            return;
        }
        $this->mlJson(array('ok' => TRUE, 'columns' => $columns, 'rows' => $rows, 'offset' => $offset, 'limit' => $limit));
    }

    // --- create / register ------------------------------------------------------

    public function save()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name', TRUE));
        $environment = strtoupper(trim((string) $this->input->post('environment', TRUE))) ?: 'ALL';
        if ($name === '') {
            $this->mlJson(array('ok' => FALSE, 'message' => 'A dataset name is required.'), 422);
            return;
        }
        $key = $this->mlSlug($this->input->post('dataset_key', TRUE) ?: $name);
        if ($key === '') {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Could not derive a dataset key.'), 422);
            return;
        }
        if ($environment !== 'ALL' && ! in_array($environment, $this->mlActiveEnvironments(), TRUE)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Unknown environment.'), 422);
            return;
        }
        if ($id <= 0 && $this->datasets->datasetScopeExists($key, $environment)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'A dataset with this key already exists in '.$environment.'.'), 409);
            return;
        }
        $savedId = $this->datasets->saveDataset(array(
            'id' => $id,
            'dataset_key' => $key,
            'name' => $name,
            'environment' => $environment,
            'kind' => in_array($this->input->post('kind'), array('table', 'text', 'image', 'timeseries', 'model'), TRUE) ? $this->input->post('kind') : 'table',
            'description' => trim((string) $this->input->post('description', TRUE)) ?: NULL,
            'default_source_type' => in_array($this->input->post('default_source_type'), MlDataset_model::SOURCE_TYPES, TRUE) ? $this->input->post('default_source_type') : 'upload',
            'tags' => trim((string) $this->input->post('tags', TRUE)) ?: NULL,
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'owner' => (string) $this->name,
        ), $id);
        $this->mlJson(array('ok' => TRUE, 'id' => $savedId, 'message' => $id > 0 ? 'Dataset updated.' : 'Dataset created.'));
    }

    public function delete()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $id = (int) $this->input->post('id');
        if (! $this->datasets->getDataset($id)) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Dataset not found.'), 404);
            return;
        }
        $this->datasets->deleteDataset($id);
        $this->mlJson(array('ok' => TRUE, 'message' => 'Dataset and its versions deleted.'));
    }

    public function registerVersion()
    {
        if (! $this->mlRequireManagerPost()) {
            return;
        }
        $dataset = $this->datasets->getDataset((int) $this->input->post('dataset_id'));
        if (! $dataset) {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Dataset not found.'), 404);
            return;
        }
        $sourceType = in_array($this->input->post('source_type'), MlDataset_model::SOURCE_TYPES, TRUE)
            ? $this->input->post('source_type') : 'upload';

        $tmp = NULL;
        $cleanup = FALSE;
        $format = 'csv';
        $sourceRef = array();

        if ($sourceType === 'upload') {
            if (! isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file($_FILES['file']['tmp_name'])) {
                $this->mlJson(array('ok' => FALSE, 'message' => 'Attach a file to upload.'), 422);
                return;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'jsmlds');
            move_uploaded_file($_FILES['file']['tmp_name'], $tmp);
            $cleanup = TRUE;
            $format = strtolower(pathinfo((string) $_FILES['file']['name'], PATHINFO_EXTENSION)) ?: 'csv';
            $sourceRef = array('original_name' => basename((string) $_FILES['file']['name']));
        } elseif ($sourceType === 'connector') {
            $this->load->library('MlConnectorQuery', array(), 'connectorQuery');
            $result = $this->connectorQuery->run((int) $this->input->post('connector_id'), (string) $this->input->post('sql'),
                (int) $this->input->post('max_rows') ?: 100000);
            if (empty($result['ok'])) {
                $this->mlJson(array('ok' => FALSE, 'message' => $result['message']), 422);
                return;
            }
            $tmp = $result['path'];
            $cleanup = TRUE;
            $format = 'csv';
            $sourceRef = array('connector_id' => (int) $this->input->post('connector_id'),
                'sql' => substr((string) $this->input->post('sql'), 0, 4000));
        } elseif ($sourceType === 'repository') {
            $relative = ltrim(str_replace('\\', '/', (string) $this->input->post('repository_path')), '/');
            $root = getenv('JOBSEEKER_REPOSITORY_ROOT') ?: (rtrim(FCPATH, '/\\').'/repository');
            $abs = realpath(rtrim($root, '/').'/'.$relative);
            $realRoot = realpath($root);
            if ($relative === '' || strpos($relative, '..') !== FALSE || $abs === FALSE || $realRoot === FALSE
                || strpos($abs, $realRoot) !== 0 || ! is_file($abs)) {
                $this->mlJson(array('ok' => FALSE, 'message' => 'That repository file was not found.'), 404);
                return;
            }
            $tmp = $abs;
            $format = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'csv';
            $sourceRef = array('repository_path' => $relative);
        } else {
            $this->mlJson(array('ok' => FALSE, 'message' => 'Run outputs are registered by the SDK save_dataset().'), 422);
            return;
        }

        $version = $this->datasets->nextVersionNumber((int) $dataset->id);
        $stored = $this->datasets->storeVersionFile($dataset->dataset_key, $dataset->environment, $version, $format, $tmp);
        $profiler = new MlDatasetProfiler();
        $profile = $profiler->profile($tmp, array('format' => $format,
            'delimiter' => (string) $this->input->post('delimiter') ?: ',',
            'header' => $this->input->post('has_header') !== '0'));
        if ($cleanup) {
            @unlink($tmp);
        }
        if (empty($stored['ok'])) {
            $this->mlJson(array('ok' => FALSE, 'message' => $stored['message']), 500);
            return;
        }

        $created = $this->datasets->createVersion((int) $dataset->id, array(
            'source_type' => $sourceType,
            'source_ref_json' => json_encode($sourceRef, JSON_UNESCAPED_SLASHES),
            'storage_path' => $stored['relative_path'],
            'checksum' => $stored['checksum'],
            'size_bytes' => $stored['size'],
            'format' => $format,
            'row_count' => isset($profile['row_count']) ? $profile['row_count'] : NULL,
            'column_count' => isset($profile['column_count']) ? $profile['column_count'] : NULL,
            'schema_json' => ! empty($profile['schema']) ? json_encode($profile['schema'], JSON_UNESCAPED_SLASHES) : NULL,
            'profile_json' => ! empty($profile['profile']) ? json_encode(array(
                'columns' => $profile['profile'], 'sample' => isset($profile['sample']) ? $profile['sample'] : NULL,
            ), JSON_UNESCAPED_SLASHES) : NULL,
            'fingerprint_json' => ! empty($profile['fingerprint']) ? json_encode($profile['fingerprint'], JSON_UNESCAPED_SLASHES) : NULL,
            'profile_status' => ! empty($profile['needs_runtime_profile']) ? 'skipped' : (! empty($profile['ok']) ? 'done' : 'failed'),
            'profile_error' => empty($profile['ok']) ? substr((string) (isset($profile['message']) ? $profile['message'] : ''), 0, 2000) : NULL,
            'notes' => trim((string) $this->input->post('notes', TRUE)) ?: NULL,
            'created_by' => (string) $this->name,
        ));
        $this->mlJson(array('ok' => TRUE, 'dataset_version_id' => $created['id'], 'version' => $created['version'],
            'message' => 'Version '.$created['version'].' registered'
                .(! empty($profile['needs_runtime_profile']) ? ' (binary format - profile deferred).' : '.')));
    }

    public function download($versionId)
    {
        if (! $this->mlCanManage()) {
            $this->output->set_status_header(403);
            return;
        }
        $version = $this->datasets->getVersion((int) $versionId);
        $path = $version ? $this->datasets->versionAbsolutePath($version) : FALSE;
        if ($path === FALSE) {
            show_error('This version has no stored file.', 404);
            return;
        }
        $this->output
            ->set_content_type('application/octet-stream')
            ->set_header('Content-Disposition: attachment; filename="dataset-v'.(int) $version->version.'.'.$version->format.'"')
            ->set_header('Content-Length: '.filesize($path))
            ->set_output('')
            ->_display();
        $h = fopen($path, 'rb');
        while ($h && ! feof($h)) {
            echo fread($h, 1048576);
        }
        if ($h) {
            fclose($h);
        }
        exit;
    }

    private function decorateList($rows)
    {
        foreach ($rows as $row) {
            $latest = $this->datasets->latestVersion((int) $row->id);
            $row->latest_row_count = $latest ? (int) $latest->row_count : NULL;
            $row->latest_format = $latest ? (string) $latest->format : (string) $row->format;
        }
        return $rows;
    }
}
