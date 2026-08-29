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
        'txt' => array('label' => 'Text', 'extensions' => array('txt', 'log', 'dat')),
        'binary' => array('label' => 'Binary / custom', 'extensions' => array())
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(array('url', 'form'));
        $this->load->model('DataAssets_model', 'model');
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

    private function manifestPayload()
    {
        $assets = array();
        foreach ($this->model->manifestAssets() as $asset) {
            $options = json_decode(isset($asset['options_json']) ? $asset['options_json'] : '', TRUE);
            $absolutePath = $this->absoluteStoragePath($asset['storage_path']);
            $assets[] = array(
                'key' => $asset['asset_key'],
                'name' => $asset['name'],
                'uri' => $this->assetUri($asset),
                'direction' => $asset['direction'],
                'format' => $asset['format'],
                'environment' => $asset['environment'],
                'job' => $asset['job_name'],
                'relative_path' => str_replace('\\', '/', $asset['storage_path']),
                'file_name' => $asset['file_name'],
                'required' => (bool) $asset['is_required'],
                'active' => (bool) $asset['is_active'],
                'version' => (int) $asset['version'],
                'size' => $asset['file_size'] === NULL ? NULL : (int) $asset['file_size'],
                'checksum' => $asset['checksum'],
                'uploaded_at' => $asset['uploaded_at'],
                'exists' => $absolutePath !== FALSE && is_file($absolutePath),
                'options' => is_array($options) ? $options : array(),
                'description' => $asset['description']
            );
        }

        return array(
            'schema_version' => 1,
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
        $data = array(
            'assets' => $this->model->listAssets($selectedEnvironment),
            'statistics' => $this->model->statistics($selectedEnvironment),
            'environments' => $environments,
            'formats' => $this->formats,
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
        $environment = $this->normalizeEnvironment($this->input->post('environment'));
        $selectedEnvironment = $this->selectedEnvironment();
        $jobName = $this->normalizeJobName($this->input->post('job_name'));
        $description = trim((string) $this->input->post('description'));
        $fileName = $this->normalizedFileName($this->input->post('file_name'));
        $hasUpload = isset($_FILES['asset_file']) && isset($_FILES['asset_file']['error']) && $_FILES['asset_file']['error'] !== UPLOAD_ERR_NO_FILE;

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

        if ($existing && empty($existing->legacy_source) && $existing->storage_path !== $storagePath) {
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
        } else if (is_file($absolutePath)) {
            $fileSize = filesize($absolutePath);
            $checksum = hash_file('sha256', $absolutePath);
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
        if (! $this->canManageAssets()) {
            $this->previewResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return;
        }

        $asset = $this->model->getAsset((int) $id);
        if (! $asset) {
            $this->previewResponse(array('ok' => FALSE, 'message' => 'The selected data asset no longer exists.'), 404);
            return;
        }
        if (! $this->assetMatchesSelectedEnvironment($asset)) {
            $this->previewResponse(array('ok' => FALSE, 'message' => 'The data asset is outside the selected environment.'), 404);
            return;
        }
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
        } else if (in_array($format, array('txt', 'xml'), TRUE)) {
            $handle = fopen($absolutePath, 'rb');
            $text = $handle === FALSE ? '' : fread($handle, 65536);
            $payload['truncated'] = filesize($absolutePath) > 65536;
            if ($handle !== FALSE) fclose($handle);
            $payload['kind'] = 'text';
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
