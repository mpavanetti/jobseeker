<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class DataAssets extends BaseController
{
    private $formats = array(
        'csv' => array('label' => 'CSV', 'extensions' => array('csv')),
        'json' => array('label' => 'JSON', 'extensions' => array('json')),
        'jsonl' => array('label' => 'JSON Lines', 'extensions' => array('jsonl', 'ndjson')),
        'xlsx' => array('label' => 'Excel', 'extensions' => array('xlsx', 'xls')),
        'parquet' => array('label' => 'Parquet', 'extensions' => array('parquet')),
        'xml' => array('label' => 'XML', 'extensions' => array('xml')),
        'html' => array('label' => 'Web page / HTML', 'extensions' => array('html', 'htm')),
        'txt' => array('label' => 'Text', 'extensions' => array('txt', 'log', 'dat')),
        'table' => array('label' => 'Table rows, materialized as CSV', 'extensions' => array('csv')),
        'binary' => array('label' => 'Binary / custom', 'extensions' => array())
    );

    /**
     * Where an asset's data lives, the formats that make sense for it, and the
     * Connection types that can reach it. A file format applies only to
     * sources that deliver a file. Tables, sheets, and collections have their
     * own shape, which jobs receive as CSV or JSON Lines.
     */
    private function sourceCatalog()
    {
        $files = array('csv', 'json', 'jsonl', 'xlsx', 'parquet', 'xml', 'html', 'txt', 'binary');
        $labels = function($keys) {
            $result = array();
            foreach ($keys as $key) $result[$key] = $this->formats[$key]['label'];
            return $result;
        };
        return array(
            'upload' => array(
                'label' => 'File uploaded or written by a job', 'short' => 'File',
                'formats' => $labels($files), 'connectors' => array(), 'connection' => 'none', 'hint' => ''
            ),
            'url' => array(
                'label' => 'Web page, REST API, or file URL', 'short' => 'Web / API',
                'formats' => $labels(array('json', 'jsonl', 'csv', 'xml', 'html', 'txt', 'xlsx', 'parquet', 'binary')),
                'connectors' => array('http_api'), 'connection' => 'optional',
                'hint' => 'Optional: an HTTP API Connection adds its authentication, and a URL starting with / uses its host.'
            ),
            'google_sheet' => array(
                'label' => 'Google Sheet', 'short' => 'Google Sheet',
                'formats' => array('csv' => 'Sheet rows, materialized as CSV'),
                'connectors' => array('google_sheets'), 'connection' => 'optional',
                'hint' => 'Public and published sheets need no Connection; private ones use a Google Sheets API Connection.'
            ),
            'database_table' => array(
                'label' => 'Database table or view', 'short' => 'Database table',
                'formats' => array('table' => 'Table rows, materialized as CSV'),
                'connectors' => array('mysql', 'pgsql', 'sqlserver', 'oracle_service', 'oracle_sid', 'snowflake', 'databricks'),
                'connection' => 'required',
                'hint' => 'A MySQL/MariaDB, PostgreSQL, SQL Server, Oracle, Snowflake, or Databricks Connection.'
            ),
            'object_storage' => array(
                'label' => 'File in cloud storage or SFTP', 'short' => 'Cloud storage',
                'formats' => $labels($files),
                'connectors' => array('aws_s3', 'azure_blob', 'azure_data_lake', 'gcs', 'sftp'), 'connection' => 'required',
                'hint' => 'An S3, Azure Blob, Azure Data Lake, Google Cloud Storage, or SFTP Connection.'
            ),
            'document_collection' => array(
                'label' => 'MongoDB collection or Elasticsearch index', 'short' => 'Documents',
                'formats' => array('jsonl' => 'Documents, materialized as JSON Lines'),
                'connectors' => array('mongodb', 'elasticsearch'), 'connection' => 'required',
                'hint' => 'A MongoDB or Elasticsearch / OpenSearch Connection.'
            )
        );
    }

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(array('url', 'form'));
        $this->load->model('DataAssets_model', 'model');
        $this->load->model('DbSettings_model', 'connectorCatalog');
        $this->load->library('DataAssetSource');
        $this->load->library('session');
        $this->isLoggedIn();
    }

    private function canManageAssets()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function repositoryRoot()
    {
        $jenkinsHome = isset($this->global['jenkins_home']) ? trim((string) $this->global['jenkins_home']) : '';
        return $jenkinsHome === '' ? rtrim(FCPATH, '/\\').DIRECTORY_SEPARATOR.'repository' : rtrim($jenkinsHome, '/\\').DIRECTORY_SEPARATOR.'repository';
    }

    private function normalizeAssetKey($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }

    private function normalizeEnvironment($value)
    {
        $value = strtoupper(trim((string) $value));
        return $value === '' || $value === '*' ? 'ALL' : $value;
    }

    private function selectedEnvironment()
    {
		if ($this->jobSeekerIsStandaloneDeployment()) {
			return $this->jobSeekerStandaloneEnvironment();
		}
        $environment = trim((string) $this->input->get('environment', TRUE));
        if ($environment === '') {
            $environment = $this->jobSeekerEnvironmentPreference();
        }
        $environment = $this->normalizeJobSeekerEnvironment($environment);
        return $environment === '' || $environment === '*' ? 'ALL' : $environment;
    }

    private function assetMatchesSelectedEnvironment($asset)
    {
        if (! $asset) {
            return FALSE;
        }
        $selectedEnvironment = $this->selectedEnvironment();
        return $selectedEnvironment === 'ALL' || in_array($this->normalizeJobSeekerEnvironment($asset->environment), array($selectedEnvironment, 'ALL'), TRUE);
    }

    private function normalizeJobName($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '*' || strtoupper($value) === 'ALL') {
            return '*';
        }
        return preg_match('/^[A-Za-z0-9._\-\/ ]{1,200}$/', $value) ? $value : FALSE;
    }

    private function normalizedFileName($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $safeName = $this->safeUploadFileName($value);
        return $safeName === FALSE ? '' : $safeName;
    }

    private function managedStoragePath($assetKey, $environment, $jobName, $fileName)
    {
        $environmentSegment = strtolower(preg_replace('/[^A-Za-z0-9._-]/', '-', $environment));
        $segments = array('data-assets', $environmentSegment === '' ? 'all' : $environmentSegment);
        if ($jobName !== '*') {
            $jobSegment = strtolower(preg_replace('/[^A-Za-z0-9._-]+/', '-', str_replace('/', '-', $jobName)));
            $segments[] = trim($jobSegment, '-') ?: 'job';
        }
        $segments[] = $assetKey;
        $segments[] = $fileName;
        return implode('/', $segments);
    }

    private function absoluteStoragePath($relativePath)
    {
        $relativePath = $this->safeRelativePath($relativePath);
        if ($relativePath === FALSE) {
            return FALSE;
        }

        $root = $this->repositoryRoot();
        if (! $this->ensureDirectory($root)) {
            return FALSE;
        }
        $realRoot = realpath($root);
        $absolutePath = rtrim($root, '/\\').DIRECTORY_SEPARATOR.$relativePath;
        if ($realRoot === FALSE || ! $this->pathWithinBase($absolutePath, $realRoot)) {
            return FALSE;
        }
        return $absolutePath;
    }

    private function assetUri($asset)
    {
        $jobName = isset($asset['job_name']) ? $asset['job_name'] : $asset->job_name;
        $environment = isset($asset['environment']) ? $asset['environment'] : $asset->environment;
        $assetKey = isset($asset['asset_key']) ? $asset['asset_key'] : $asset->asset_key;
        $scope = $jobName === '*' ? 'shared' : rawurlencode(str_replace('/', '~', $jobName));
        return 'jobseeker://'.strtolower($environment).'/'.$scope.'/'.$assetKey;
    }

    private function sourceType($asset)
    {
        $type = is_array($asset) ? (isset($asset['source_type']) ? $asset['source_type'] : '') : (isset($asset->source_type) ? $asset->source_type : '');
        $catalog = $this->sourceCatalog();
        return isset($catalog[trim((string) $type)]) ? trim((string) $type) : 'upload';
    }

    private function connectorForAssetScope($connectorKey, $environment, $jobName)
    {
        if ($connectorKey === '') return NULL;
        foreach ($this->connectorCatalog->listSettings($environment) as $connector) {
            if ((string) $connector->connector_key !== $connectorKey || (int) $connector->is_active !== 1 || ! empty($connector->shadowed)) continue;
            $environmentMatches = $environment === 'ALL' || in_array($this->normalizeJobSeekerEnvironment($connector->environment), array($this->normalizeJobSeekerEnvironment($environment), 'ALL'), TRUE);
            $jobMatches = $jobName === '*' || $connector->job_name === '*' || (string) $connector->job_name === (string) $jobName;
            if ($environmentMatches && $jobMatches) return $connector;
        }
        return NULL;
    }

    /** One asset as the runtime catalog publishes it: coordinates, never secrets. */
    private function manifestEntry(array $asset)
    {
        $options = json_decode(isset($asset['options_json']) ? $asset['options_json'] : '', TRUE);
        $source = json_decode(isset($asset['source_config_json']) ? $asset['source_config_json'] : '', TRUE);
        $sourceType = $this->sourceType($asset);
        $absolutePath = $this->absoluteStoragePath($asset['storage_path']);
        return array(
            'key' => $asset['asset_key'],
            'name' => $asset['name'],
            'uri' => $this->assetUri($asset),
            'direction' => $asset['direction'],
            'format' => $asset['format'],
            'environment' => $asset['environment'],
            'job' => $asset['job_name'],
            'source_type' => $sourceType,
            'connector_key' => ! empty($asset['connector_key']) ? $asset['connector_key'] : NULL,
            'source' => is_array($source) ? $source : array(),
            'relative_path' => str_replace('\\', '/', $asset['storage_path']),
            'file_name' => $asset['file_name'],
            'required' => (bool) $asset['is_required'],
            'active' => (bool) $asset['is_active'],
            'version' => (int) $asset['version'],
            'size' => $asset['file_size'] === NULL ? NULL : (int) $asset['file_size'],
            'checksum' => $asset['checksum'],
            'uploaded_at' => $asset['uploaded_at'],
            'exists' => $sourceType !== 'upload' || ($absolutePath !== FALSE && is_file($absolutePath)),
            'options' => is_array($options) ? $options : array(),
            'description' => $asset['description']
        );
    }

    private function manifestPayload()
    {
        $assets = array();
        foreach ($this->model->manifestAssets() as $asset) {
            $assets[] = $this->manifestEntry($asset);
        }

        return array(
            'schema_version' => 2,
            'generated_at' => gmdate('c'),
            'repository_root_env' => 'JOBSEEKER_REPOSITORY_ROOT',
            'assets' => $assets
        );
    }

    private function writeManifest()
    {
        $directory = $this->repositoryRoot().DIRECTORY_SEPARATOR.'data-assets';
        if (! $this->ensureDirectory($directory)) {
            return FALSE;
        }

        $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';
        $temporaryPath = $directory.DIRECTORY_SEPARATOR.'.manifest-'.uniqid('', TRUE).'.tmp';
        $json = json_encode($this->manifestPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        if (file_put_contents($temporaryPath, $json, LOCK_EX) === FALSE) {
            return FALSE;
        }
        if (! rename($temporaryPath, $manifestPath)) {
            @unlink($temporaryPath);
            return FALSE;
        }
        return TRUE;
    }

    private function refreshStoredMetadata()
    {
        foreach ($this->model->listAssets() as $asset) {
            if ($this->sourceType($asset) !== 'upload') {
                continue;
            }
            $absolutePath = $this->absoluteStoragePath($asset->storage_path);
            if ($absolutePath === FALSE || ! is_file($absolutePath)) {
                continue;
            }

            $fileSize = filesize($absolutePath);
            $fileModifiedAt = filemtime($absolutePath);
            $observedAt = empty($asset->uploaded_at) ? FALSE : strtotime($asset->uploaded_at);
            if ($asset->checksum !== NULL && $asset->checksum !== '' && $asset->file_size !== NULL &&
                (int) $asset->file_size === (int) $fileSize && $observedAt !== FALSE &&
                $fileModifiedAt !== FALSE && $fileModifiedAt <= $observedAt) {
                continue;
            }

            $checksum = hash_file('sha256', $absolutePath);
            if ($checksum === FALSE) {
                continue;
            }

            $now = date('Y-m-d H:i:s');
            if ($asset->checksum !== NULL && $asset->checksum !== '' && hash_equals((string) $asset->checksum, $checksum)) {
                $this->model->saveAsset(array(
                    'file_size' => $fileSize,
                    'uploaded_at' => $now,
                    'updated_at' => $now
                ), $asset->id);
                continue;
            }

            $this->model->saveAsset(array(
                'version' => (int) $asset->version + 1,
                'file_size' => $fileSize,
                'checksum' => $checksum,
                'uploaded_at' => $now,
                'updated_at' => $now,
                'owner' => empty($asset->owner) ? 'Runtime job' : $asset->owner
            ), $asset->id);
        }
    }

    public function index()
    {
        if (! $this->canManageAssets()) {
            $this->loadThis();
            return;
        }

        $this->refreshStoredMetadata();
        $this->writeManifest();
        $selectedEnvironment = $this->selectedEnvironment();
        $this->global['pageTitle'] = 'Job Seeker : Data Assets';
        $environments = $this->model->environments();
        if ($selectedEnvironment !== 'ALL') {
            $environments = array_values(array_filter($environments, function($row) use ($selectedEnvironment) {
                return $this->normalizeJobSeekerEnvironment($row->Environment) === $selectedEnvironment;
            }));
        }
        $sourceConnectorTypes = array();
        foreach ($this->sourceCatalog() as $source) {
            $sourceConnectorTypes = array_merge($sourceConnectorTypes, $source['connectors']);
        }
        $data = array(
            'assets' => $this->model->listAssets($selectedEnvironment),
            'statistics' => $this->model->statistics($selectedEnvironment),
            'environments' => $environments,
            'formats' => $this->formats,
            'sourceTypes' => $this->sourceCatalog(),
            'connections' => array_values(array_filter($this->connectorCatalog->listSettings($selectedEnvironment), function($connector) use ($sourceConnectorTypes) {
                return (int) $connector->is_active === 1 && empty($connector->shadowed)
                    && in_array((string) $connector->db_type, $sourceConnectorTypes, TRUE);
            })),
            'initialDirection' => in_array($this->input->get('direction'), array('input', 'output'), TRUE) ? $this->input->get('direction') : '',
            'initialEnvironment' => $selectedEnvironment
        );
        $this->global['selectedEnvironment'] = $selectedEnvironment;
        $this->loadViews('dataAssets', $this->global, $data, NULL);
    }

    public function save()
    {
        if (! $this->canManageAssets()) {
            $this->output->set_status_header(403);
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->output->set_status_header(405);
            return;
        }

        $id = (int) $this->input->post('asset_id');
        $existing = $id > 0 ? $this->model->getAsset($id) : NULL;
        if ($id > 0 && ! $existing) {
            $this->session->set_flashdata('error', 'The selected data asset no longer exists.');
            redirect('data-assets');
        }
        if ($existing && ! $this->assetMatchesSelectedEnvironment($existing)) {
            $this->session->set_flashdata('error', 'The selected data asset is outside the current environment.');
            redirect('data-assets');
        }

        $assetKey = $this->normalizeAssetKey($this->input->post('asset_key'));
        $name = trim((string) $this->input->post('name'));
        $direction = trim((string) $this->input->post('direction'));
        $format = strtolower(trim((string) $this->input->post('format')));
        $environment = $this->jobSeekerIsStandaloneDeployment()
			? $this->jobSeekerStandaloneEnvironment()
			: $this->normalizeEnvironment($this->input->post('environment'));
        $selectedEnvironment = $this->selectedEnvironment();
        $jobName = $this->normalizeJobName($this->input->post('job_name'));
        $sourceType = strtolower(trim((string) $this->input->post('source_type')));
        if ($sourceType === '') {
            $sourceType = $existing ? $this->sourceType($existing) : 'upload';
        }
        $connectorKey = $this->normalizeAssetKey($this->input->post('connector_key'));
        $description = trim((string) $this->input->post('description'));
        $fileName = $this->normalizedFileName($this->input->post('file_name'));
        $hasUpload = isset($_FILES['asset_file']) && isset($_FILES['asset_file']['error']) && $_FILES['asset_file']['error'] !== UPLOAD_ERR_NO_FILE;

        $catalog = $this->sourceCatalog();
        if (! isset($catalog[$sourceType])) {
            $this->session->set_flashdata('error', 'Select a supported Data Asset source.');
            redirect('data-assets');
        }
        $source = $catalog[$sourceType];
        // A table, sheet, or collection has one shape; files choose a format.
        if (count($source['formats']) === 1) {
            $format = key($source['formats']);
        } else if (! isset($source['formats'][$format])) {
            $this->session->set_flashdata('error', 'A '.$source['label'].' source cannot deliver '.(isset($this->formats[$format]) ? $this->formats[$format]['label'] : 'that format').'.');
            redirect('data-assets');
        }
        if ($source['connection'] === 'none') {
            $connectorKey = '';
        }
        if ($sourceType !== 'upload' && $direction !== 'input') {
            $this->session->set_flashdata('error', 'Web, spreadsheet, database, storage, and document sources are read-only inputs. Use a file asset for outputs.');
            redirect('data-assets');
        }
        if ($sourceType !== 'upload' && $hasUpload) {
            $this->session->set_flashdata('error', 'Choose either an external source or an uploaded file, not both.');
            redirect('data-assets');
        }
        if ($sourceType === 'upload' && $existing && $this->sourceType($existing) !== 'upload' && ! $hasUpload) {
            $this->session->set_flashdata('error', 'Upload a seed file when changing a connected source back to a file asset.');
            redirect('data-assets');
        }

        if ($assetKey === '' || strlen($assetKey) > 128 || $name === '' || strlen($name) > 200) {
            $this->session->set_flashdata('error', 'Provide a name and an asset key using letters, numbers, or dashes.');
            redirect('data-assets');
        }
        if (! in_array($direction, array('input', 'output', 'input_output'), TRUE) || ! isset($this->formats[$format])) {
            $this->session->set_flashdata('error', 'Select a supported asset role and file format.');
            redirect('data-assets');
        }
        if ($jobName === FALSE || strlen($description) > 2000) {
            $this->session->set_flashdata('error', 'The job scope or description is invalid.');
            redirect('data-assets');
        }
        if ($selectedEnvironment !== 'ALL' && ! in_array($this->normalizeJobSeekerEnvironment($environment), array($selectedEnvironment, 'ALL'), TRUE)) {
            $this->session->set_flashdata('error', 'The data asset environment is outside the current backend scope.');
            redirect('data-assets');
        }
        if ($environment !== 'ALL') {
            $available = array_map(function($row) { return strtoupper($row->Environment); }, $this->model->environments());
            if (! in_array($environment, $available, TRUE)) {
                $this->session->set_flashdata('error', 'Select an environment configured in Context Settings.');
                redirect('data-assets');
            }
        }
        if ($this->model->scopeExists($assetKey, $environment, $jobName, $id)) {
            $this->session->set_flashdata('error', 'That asset key already exists for the selected environment and job scope.');
            redirect('data-assets');
        }

        $sourceConfig = array();
        if ($sourceType === 'url') {
            $sourceUrl = trim((string) $this->input->post('source_url'));
            $responsePath = trim((string) $this->input->post('response_path'));
            if ($sourceUrl === '' || strlen($sourceUrl) > 2000 || strlen($responsePath) > 500) {
                $this->session->set_flashdata('error', 'Provide a valid web or API source URL.');
                redirect('data-assets');
            }
            $credentialError = $this->dataassetsource->validateNoEmbeddedCredentials($sourceUrl);
            if ($credentialError !== NULL) {
                $this->session->set_flashdata('error', $credentialError);
                redirect('data-assets');
            }
            if ($connectorKey === '') {
                $urlError = $this->dataassetsource->validatePublicUrl($sourceUrl);
                if ($urlError !== NULL) {
                    $this->session->set_flashdata('error', $urlError);
                    redirect('data-assets');
                }
            } else if ($sourceUrl[0] !== '/' && ! preg_match('#^https?://#i', $sourceUrl)) {
                $this->session->set_flashdata('error', 'Authenticated API URLs must be absolute or start with / to use the Connection host.');
                redirect('data-assets');
            }
            $sourceConfig = array('url' => $sourceUrl, 'response_path' => $responsePath);
        } else if ($sourceType === 'google_sheet') {
            $sourceUrl = trim((string) $this->input->post('google_public_url'));
            $spreadsheetId = trim((string) $this->input->post('spreadsheet_id'));
            $sheetRange = trim((string) $this->input->post('sheet_range'));
            if (($sourceUrl === '' && $spreadsheetId === '') || strlen($sourceUrl) > 2000
                || ! preg_match('/^[A-Za-z0-9_-]{0,200}$/', $spreadsheetId)
                || strlen($sheetRange) > 300 || preg_match('/[\x00-\x1F\x7F]/', $sheetRange)) {
                $this->session->set_flashdata('error', 'Provide a public Google Sheet URL or a valid spreadsheet ID and range.');
                redirect('data-assets');
            }
            if ($sourceUrl !== '') {
                $credentialError = $this->dataassetsource->validateNoEmbeddedCredentials($sourceUrl);
                if ($credentialError !== NULL) {
                    $this->session->set_flashdata('error', $credentialError);
                    redirect('data-assets');
                }
            }
            if ($connectorKey === '' && $sourceUrl !== '') {
                $urlError = $this->dataassetsource->validatePublicUrl($sourceUrl);
                if ($urlError !== NULL) {
                    $this->session->set_flashdata('error', $urlError);
                    redirect('data-assets');
                }
            }
            $sourceConfig = array('url' => $sourceUrl, 'spreadsheet_id' => $spreadsheetId, 'range' => $sheetRange);
        } else if ($sourceType === 'database_table') {
            $tableName = trim((string) $this->input->post('table_name'));
            $tableSchema = trim((string) $this->input->post('table_schema'));
            $identifier = '[A-Za-z_][A-Za-z0-9_$]{0,127}';
            if (! preg_match('/^'.$identifier.'$/', $tableName)
                || ($tableSchema !== '' && ! preg_match('/^'.$identifier.'(\.'.$identifier.')?$/', $tableSchema))) {
                $this->session->set_flashdata('error', 'Use letters, digits, _ and $ for the table name, and schema or catalog.schema for its schema.');
                redirect('data-assets');
            }
            $sourceConfig = array('table' => $tableName, 'schema' => $tableSchema);
        } else if ($sourceType === 'object_storage') {
            $objectPath = trim((string) $this->input->post('object_path'));
            if ($objectPath === '' || strlen($objectPath) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $objectPath)
                || in_array('..', explode('/', $objectPath), TRUE)) {
                $this->session->set_flashdata('error', 'Provide the object path, without .. segments, such as landing/customers.csv.');
                redirect('data-assets');
            }
            $sourceConfig = array('path' => $objectPath);
        } else if ($sourceType === 'document_collection') {
            $collection = trim((string) $this->input->post('collection_name'));
            if (! preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.\-*]{0,254}$/', $collection)) {
                $this->session->set_flashdata('error', 'Use letters, digits, _, -, . and * for the collection or index name.');
                redirect('data-assets');
            }
            $sourceConfig = array('collection' => $collection);
        }

        if ($connectorKey !== '') {
            $connector = $this->connectorForAssetScope($connectorKey, $environment, $jobName);
            if (! $connector) {
                $this->session->set_flashdata('error', 'The selected Connection is inactive or outside this asset scope. Configure it in Connections first.');
                redirect('data-assets');
            }
            if (! in_array((string) $connector->db_type, $source['connectors'], TRUE)) {
                $this->session->set_flashdata('error', 'A '.$source['label'].' source cannot use a '.$connector->db_type.' Connection.');
                redirect('data-assets');
            }
        } else if ($source['connection'] === 'required') {
            $this->session->set_flashdata('error', 'A '.$source['label'].' source needs a Connection. Create one under Connections first.');
            redirect('data-assets');
        }

        $upload = NULL;
        if ($hasUpload) {
            $upload = $this->getUploadedFile('asset_file', $this->formats[$format]['extensions'], 104857600);
            if (! $upload['ok']) {
                $this->session->set_flashdata('error', $upload['message']);
                redirect('data-assets');
            }
            if ($fileName === '') {
                $fileName = $upload['safe_name'];
            }
        }

        if ($fileName === '' && $existing) {
            $fileName = $existing->file_name;
        }
        if ($fileName === '' && in_array($sourceType, array('url', 'object_storage'), TRUE)) {
            $sourcePath = $sourceType === 'url' ? parse_url(isset($sourceConfig['url']) ? $sourceConfig['url'] : '', PHP_URL_PATH) : $sourceConfig['path'];
            $fileName = $this->normalizedFileName(basename((string) $sourcePath));
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($fileName !== '' && ! empty($this->formats[$format]['extensions']) && ! in_array($extension, $this->formats[$format]['extensions'], TRUE)) {
                $fileName .= '.'.$this->formats[$format]['extensions'][0];
            }
        }
        if ($fileName === '' && $sourceType === 'database_table') {
            $fileName = $sourceConfig['table'].'.csv';
        }
        if ($fileName === '' && $sourceType === 'document_collection') {
            $fileName = $this->normalizedFileName(str_replace('*', 'all', $sourceConfig['collection'])).'.jsonl';
        }
        if ($fileName === '' && $sourceType === 'google_sheet') {
            $fileName = $assetKey.'.csv';
        }
        if ($fileName === '' && $sourceType !== 'upload') {
            $extension = isset($this->formats[$format]) && ! empty($this->formats[$format]['extensions']) ? $this->formats[$format]['extensions'][0] : 'dat';
            $fileName = $assetKey.'.'.$extension;
        }
        if ($fileName === '') {
            $this->session->set_flashdata('error', 'Provide the runtime file name or upload an input file.');
            redirect('data-assets');
        }

        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (! empty($this->formats[$format]['extensions']) && ! in_array($fileExtension, $this->formats[$format]['extensions'], TRUE)) {
            $this->session->set_flashdata('error', 'The runtime file name does not match the selected format.');
            redirect('data-assets');
        }

        $storagePath = ($existing && ! empty($existing->legacy_source))
            ? $existing->storage_path
            : $this->managedStoragePath($assetKey, $environment, $jobName, $fileName);
        $absolutePath = $this->absoluteStoragePath($storagePath);
        if ($absolutePath === FALSE || ! $this->ensureDirectory(dirname($absolutePath))) {
            $this->session->set_flashdata('error', 'JobSeeker could not prepare the asset repository path.');
            redirect('data-assets');
        }

        if ($sourceType === 'upload' && $existing && empty($existing->legacy_source) && $existing->storage_path !== $storagePath) {
            $oldPath = $this->absoluteStoragePath($existing->storage_path);
            if (! $hasUpload && $oldPath !== FALSE && is_file($oldPath) && ! @rename($oldPath, $absolutePath)) {
                $this->session->set_flashdata('error', 'The existing asset file could not be moved to its new scope.');
                redirect('data-assets');
            }
        }

        $version = $existing ? (int) $existing->version : 0;
        $fileSize = $existing ? $existing->file_size : NULL;
        $checksum = $existing ? $existing->checksum : NULL;
        $uploadedAt = $existing ? $existing->uploaded_at : NULL;
        if ($hasUpload) {
            if (! move_uploaded_file($upload['tmp_name'], $absolutePath)) {
                $this->session->set_flashdata('error', 'The uploaded file could not be stored.');
                redirect('data-assets');
            }
            $version++;
            $fileSize = filesize($absolutePath);
            $checksum = hash_file('sha256', $absolutePath);
            $uploadedAt = date('Y-m-d H:i:s');
        } else if ($sourceType === 'upload' && is_file($absolutePath)) {
            $fileSize = filesize($absolutePath);
            $checksum = hash_file('sha256', $absolutePath);
        } else if ($sourceType !== 'upload') {
            $previousSourceType = $existing ? $this->sourceType($existing) : '';
            $previousConfig = $existing ? (string) $existing->source_config_json : '';
            $nextConfig = json_encode($sourceConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (! $existing || $previousSourceType !== $sourceType || $previousConfig !== $nextConfig
                || (string) $existing->connector_key !== $connectorKey) {
                $version++;
            }
            $fileSize = NULL;
            $checksum = NULL;
            $uploadedAt = date('Y-m-d H:i:s');
        }

        $delimiter = (string) $this->input->post('delimiter');
        if ($delimiter === '\\t') {
            $delimiter = "\t";
        }
        if (! in_array($delimiter, array(',', ';', '|', "\t"), TRUE)) {
            $delimiter = ',';
        }
        $encoding = strtoupper(trim((string) $this->input->post('encoding')));
        if (! in_array($encoding, array('UTF-8', 'UTF-16', 'ISO-8859-1'), TRUE)) {
            $encoding = 'UTF-8';
        }
        $options = array(
            'delimiter' => $delimiter,
            'encoding' => $encoding,
            'header' => $this->input->post('has_header') === '1',
            'sheet' => trim((string) $this->input->post('sheet'))
        );

        $now = date('Y-m-d H:i:s');
        $data = array(
            'asset_key' => $assetKey,
            'name' => $name,
            'direction' => $direction,
            'format' => $format,
            'environment' => $environment,
            'job_name' => $jobName,
            'source_type' => $sourceType,
            'connector_key' => $connectorKey === '' ? NULL : $connectorKey,
            'source_config_json' => json_encode($sourceConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'storage_path' => str_replace('\\', '/', $storagePath),
            'file_name' => $fileName,
            'options_json' => json_encode($options),
            'description' => $description,
            'is_required' => $this->input->post('is_required') === '1' ? 1 : 0,
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'version' => $version,
            'file_size' => $fileSize,
            'checksum' => $checksum,
            'uploaded_at' => $uploadedAt,
            'updated_at' => $now,
            'owner' => $this->name
        );
        if (! $existing) {
            $data['created_at'] = $now;
        }

        $savedId = $this->model->saveAsset($data, $id);
        if ($savedId <= 0 || ! $this->writeManifest()) {
            $this->session->set_flashdata('error', 'The data asset could not be published to the runtime catalog.');
            redirect('data-assets');
        }

        $this->session->set_flashdata('success', ($existing ? 'Data asset updated.' : 'Data asset registered.').($hasUpload ? ' File version '.$version.' is ready for jobs.' : ' The runtime path is ready.'));
        redirect('data-assets');
    }

    public function delete()
    {
        if (! $this->canManageAssets()) {
            $this->output->set_status_header(403);
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->output->set_status_header(405);
            return;
        }

        $asset = $this->model->getAsset((int) $this->input->post('asset_id'));
        if (! $asset) {
            $this->session->set_flashdata('error', 'The selected data asset no longer exists.');
            redirect('data-assets');
        }
        if (! $this->assetMatchesSelectedEnvironment($asset)) {
            $this->session->set_flashdata('error', 'The selected data asset is outside the current environment.');
            redirect('data-assets');
        }

        if ($this->input->post('delete_file') === '1' && empty($asset->legacy_source) && strpos($asset->storage_path, 'data-assets/') === 0) {
            $absolutePath = $this->absoluteStoragePath($asset->storage_path);
            if ($absolutePath !== FALSE && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        $this->model->deleteAsset($asset->id);
        $this->writeManifest();
        $this->session->set_flashdata('success', 'Data asset registration deleted.');
        redirect('data-assets');
    }

    public function download($id)
    {
        if (! $this->canManageAssets()) {
            $this->output->set_status_header(403);
            return;
        }
        $asset = $this->model->getAsset((int) $id);
        if (! $asset) {
            show_404();
            return;
        }
        if (! $this->assetMatchesSelectedEnvironment($asset)) {
            show_404();
            return;
        }
        $absolutePath = $this->absoluteStoragePath($asset->storage_path);
        if ($absolutePath === FALSE || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            show_error('The asset has no uploaded file yet.', 404);
            return;
        }

        $downloadName = $this->safeUploadFileName($asset->file_name);
        if ($downloadName === FALSE) {
            $downloadName = 'data-asset-download';
        }
        $handle = fopen($absolutePath, 'rb');
        if ($handle === FALSE) {
            show_error('The asset file could not be opened.', 500);
            return;
        }

        $this->output
            ->set_content_type('application/octet-stream')
            ->set_header('Content-Disposition: attachment; filename="'.$downloadName.'"')
            ->set_header('Content-Length: '.filesize($absolutePath))
            ->set_header('Cache-Control: private, no-store')
            ->set_output('')
            ->_display();

        while (! feof($handle)) {
            echo fread($handle, 1048576);
        }
        fclose($handle);
        exit;
    }

    private function previewResponse($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function previewCell($value, $encoding = 'UTF-8', $maxLength = 240)
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } else if ($value === NULL) {
            $value = '';
        } else if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $value = (string) $value;
        }

        if (function_exists('iconv')) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if ($converted !== FALSE) {
                $value = $converted;
            }
        }
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        if ($maxLength > 3 && strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength - 3).'...';
        }
        return $value;
    }

    private function tablePreviewFromRecords($records, $limit = 20)
    {
        $records = array_slice((array) $records, 0, $limit);
        $columns = array();
        foreach ($records as $record) {
            if (is_object($record)) {
                $record = (array) $record;
            }
            if (! is_array($record)) {
                $record = array('value' => $record);
            }
            foreach (array_keys($record) as $column) {
                if (! in_array((string) $column, $columns, TRUE) && count($columns) < 30) {
                    $columns[] = (string) $column;
                }
            }
        }

        $rows = array();
        foreach ($records as $record) {
            $record = is_object($record) ? (array) $record : (is_array($record) ? $record : array('value' => $record));
            $row = array();
            foreach ($columns as $column) {
                $row[] = $this->previewCell(array_key_exists($column, $record) ? $record[$column] : '');
            }
            $rows[] = $row;
        }
        return array('kind' => 'table', 'columns' => $columns, 'rows' => $rows);
    }

    public function preview($id)
    {
        $asset = $this->model->getAsset((int) $id);
        if (! $asset) {
            $this->previewResponse(array('ok' => FALSE, 'message' => 'The selected data asset no longer exists.'), 404);
            return;
        }
        if (! $this->assetMatchesSelectedEnvironment($asset)) {
            $this->previewResponse(array('ok' => FALSE, 'message' => 'The data asset is outside the selected environment.'), 404);
            return;
        }

        $result = $this->servicePreview($asset);
        if ($result === NULL) {
            // Without the Data Preview service, uploaded text files still
            // preview in-process; every other source needs the service.
            if ($this->sourceType($asset) === 'upload' && in_array(strtolower((string) $asset->format), array('csv', 'json', 'jsonl', 'txt', 'xml', 'html'), TRUE)) {
                $this->legacyFilePreview($asset);
                return;
            }
            $this->previewResponse(array('ok' => FALSE, 'message' => 'The Data Preview service is not reachable, so this source cannot be previewed. Start the data-preview service.'), 503);
            return;
        }
        $status = ! empty($result['ok']) ? 200 : (isset($result['status']) ? (int) $result['status'] : 422);
        unset($result['status']);
        if (! empty($result['ok'])) {
            $catalog = $this->sourceCatalog();
            $result = array_merge(array(
                'name' => $asset->name,
                'file_name' => $asset->file_name,
                'version' => (int) $asset->version,
                'source_label' => $catalog[$this->sourceType($asset)]['short']
            ), $result);
        }
        $this->previewResponse($result, $status);
    }

    /** The environment and job whose Connection scope a preview uses. */
    private function previewScope($asset)
    {
        $environment = $this->selectedEnvironment();
        if ($environment === 'ALL') {
            $assetEnvironment = $this->normalizeJobSeekerEnvironment($asset->environment);
            $environment = $assetEnvironment !== '' && $assetEnvironment !== 'ALL' ? $assetEnvironment : 'DEV';
        }
        $jobName = (string) $asset->job_name;
        if ($jobName === '*') {
            // Job screens pass the job, so job-scoped Connections resolve as in a run.
            $requested = $this->normalizeJobName($this->input->get('job', TRUE));
            $jobName = $requested !== FALSE && $requested !== '*' ? $requested : 'jobseeker-data-preview';
        }
        return array($environment, $jobName);
    }

    /**
     * Ask the Data Preview service for a sample. It receives the asset's
     * catalog entry and, when the asset uses one, that single Connection's
     * runtime payload. NULL means the service could not be reached.
     */
    private function servicePreview($asset)
    {
        $request = array('asset' => $this->manifestEntry((array) $asset));
        if (! empty($asset->connector_key)) {
            list($environment, $jobName) = $this->previewScope($asset);
            $row = $this->connectorCatalog->runtimeSetting($asset->connector_key, $environment, $jobName);
            if (! $row) {
                return array('ok' => FALSE, 'status' => 422, 'message' => 'Connection '.$asset->connector_key.' is not active for '.$environment.' / '.$jobName.'. Check it under Connections.');
            }
            try {
                $request['connector'] = $this->connectorCatalog->runtimePayload((array) $row);
            } catch (Exception $exception) {
                $this->connectorCatalog->logRuntimeAccess((array) $row, $environment, $jobName, 'failed-preview');
                log_message('error', 'Data Asset preview could not resolve connector '.$asset->connector_key.': '.$exception->getMessage());
                return array('ok' => FALSE, 'status' => 502, 'message' => 'The credential of Connection '.$asset->connector_key.' could not be read.');
            }
            $this->connectorCatalog->logRuntimeAccess((array) $row, $environment, $jobName, 'granted-preview');
        }
        return $this->dataassetsource->requestPreview($request);
    }

    /** In-process preview of an uploaded text file, used when the service is down. */
    private function legacyFilePreview($asset)
    {
        $absolutePath = $this->absoluteStoragePath($asset->storage_path);
        if ($absolutePath === FALSE || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            $this->previewResponse(array('ok' => FALSE, 'message' => 'Upload or generate the asset file before previewing it.'), 404);
            return;
        }

        $options = json_decode($asset->options_json, TRUE);
        $options = is_array($options) ? $options : array();
        $format = strtolower((string) $asset->format);
        $payload = array(
            'ok' => TRUE,
            'name' => $asset->name,
            'format' => $format,
            'file_name' => $asset->file_name,
            'version' => (int) $asset->version,
            'size' => (int) filesize($absolutePath),
            'truncated' => FALSE
        );

        if ($format === 'csv') {
            $handle = fopen($absolutePath, 'rb');
            if ($handle === FALSE) {
                $this->previewResponse(array('ok' => FALSE, 'message' => 'The CSV file could not be opened.'), 500);
                return;
            }
            $delimiter = isset($options['delimiter']) && in_array($options['delimiter'], array(',', ';', '|', "\t"), TRUE) ? $options['delimiter'] : ',';
            $encoding = isset($options['encoding']) ? strtoupper($options['encoding']) : 'UTF-8';
            $hasHeader = ! isset($options['header']) || (bool) $options['header'];
            $records = array();
            $columns = array();
            $rowNumber = 0;
            while ($rowNumber < 21 && ($row = fgetcsv($handle, 65536, $delimiter)) !== FALSE) {
                $row = array_slice(array_map(function($value) use ($encoding) { return $this->previewCell($value, $encoding); }, $row), 0, 30);
                if ($rowNumber === 0 && $hasHeader) {
                    $columns = $row;
                } else {
                    $records[] = $row;
                }
                $rowNumber++;
            }
            $payload['truncated'] = ! feof($handle);
            fclose($handle);
            if (empty($columns)) {
                $width = empty($records) ? 0 : count($records[0]);
                for ($index = 1; $index <= $width; $index++) {
                    $columns[] = 'Column '.$index;
                }
            }
            foreach ($records as &$record) {
                $record = array_pad(array_slice($record, 0, count($columns)), count($columns), '');
            }
            unset($record);
            $payload['kind'] = 'table';
            $payload['columns'] = $columns;
            $payload['rows'] = array_slice($records, 0, 20);
        } else if ($format === 'json') {
            if (filesize($absolutePath) > 2097152) {
                $this->previewResponse(array('ok' => FALSE, 'message' => 'JSON preview is limited to 2 MB. Consume this asset in job code for efficient streaming.'), 413);
                return;
            }
            $decoded = json_decode(file_get_contents($absolutePath), TRUE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->previewResponse(array('ok' => FALSE, 'message' => 'The JSON file is not valid: '.json_last_error_msg()), 422);
                return;
            }
            if (is_array($decoded) && (empty($decoded) || array_keys($decoded) === range(0, count($decoded) - 1))) {
                $payload = array_merge($payload, $this->tablePreviewFromRecords($decoded));
                $payload['truncated'] = count($decoded) > 20;
            } else {
                $payload['kind'] = 'text';
                $payload['text'] = $this->previewCell(
                    json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'UTF-8',
                    65536
                );
            }
        } else if ($format === 'jsonl') {
            $handle = fopen($absolutePath, 'rb');
            $records = array();
            while ($handle !== FALSE && count($records) < 20 && ($line = fgets($handle, 65536)) !== FALSE) {
                if (trim($line) === '') continue;
                $decoded = json_decode($line, TRUE);
                $records[] = json_last_error() === JSON_ERROR_NONE ? $decoded : array('invalid_json' => trim($line));
            }
            $payload['truncated'] = $handle !== FALSE && ! feof($handle);
            if ($handle !== FALSE) fclose($handle);
            $payload = array_merge($payload, $this->tablePreviewFromRecords($records));
        } else if (in_array($format, array('txt', 'xml', 'html'), TRUE)) {
            $handle = fopen($absolutePath, 'rb');
            $text = $handle === FALSE ? '' : fread($handle, 65536);
            $payload['truncated'] = filesize($absolutePath) > 65536;
            if ($handle !== FALSE) fclose($handle);
            $payload['kind'] = 'text';
            if ($format === 'html') {
                $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
            }
            $payload['text'] = $this->previewCell($text, isset($options['encoding']) ? strtoupper($options['encoding']) : 'UTF-8', 65536);
        } else {
            $this->previewResponse(array(
                'ok' => FALSE,
                'message' => strtoupper($format).' preview stays in the consumer runtime. Use the Python SDK read_dataframe() helper or the appropriate ETL engine.'
            ), 415);
            return;
        }

        $this->previewResponse($payload);
    }

    public function catalog()
    {
        if (! $this->canManageAssets()) {
            $this->output->set_status_header(403);
            return;
        }
        $this->refreshStoredMetadata();
        $this->writeManifest();
        $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($this->manifestPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
