<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class Visualization extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url','form');
        $this->load->model('Visualization_model','model');
        $this->load->library('session');
        $this->isLoggedIn();   
        date_default_timezone_set('America/Sao_Paulo');
    }

    public function index()
    {
        $this->global['pageTitle'] = 'Job Seeker : Data Visualization';
        $data['reports'] = $this->model->allowedUser($this->name);
        $data['dashboards'] = $this->model->listDashboards($this->vendorId);
        $data['datasets'] = $this->model->studioDatasets();
        $this->loadViews('visualizationHub', $this->global, $data, NULL);
    }

    /**
     * Index Page for this controller.
     */
    public function view($report = null)
    {

        $this->global['pageTitle'] = 'Job Seeker : Data Visualization';
        $name = urldecode($report);
        $user = $this->global['name'];
        $data["view"] = $this->sanitizeReportRows($this->model->view($name));
        $data['reportName'] = $name;

        $validate= $this->model->permission($name,$user);

        if ($validate >= 1){

            $this->loadViews("visualization", $this->global, $data, NULL);

        } else {
           redirect('pageNotFound');
        }
        
        
    }

    public function studio()
    {
        $this->global['pageTitle'] = 'Job Seeker : Insight Studio';
        $data['datasets'] = $this->model->studioDatasets();
        $data['dashboards'] = $this->model->listDashboards($this->vendorId);
        $data['currentUserId'] = (int) $this->vendorId;
        $data['currentUserName'] = $this->name;
        $data['canManageData'] = $this->isManager() === FALSE;
        $this->loadViews('visualizationStudio', $this->global, $data, NULL);
    }

    public function dataSources()
    {
        if($this->isManager() === TRUE) {
            $this->loadThis();
            return;
        }
        $this->global['pageTitle'] = 'Job Seeker : Insight Studio Data Sources';
        $data['connections'] = $this->model->listConnections();
        $data['connectedDatasets'] = $this->model->listExternalDatasets();
        $this->loadViews('visualizationDataSources', $this->global, $data, NULL);
    }

    public function saveConnection()
    {
        if(!$this->requireVisualizationManagerJson('POST')) {
            return;
        }
        $id = (int) $this->input->post('id');
        $driver = trim((string) $this->input->post('driver', TRUE));
        $name = trim(strip_tags((string) $this->input->post('name')));
        $host = trim((string) $this->input->post('host', TRUE));
        $port = (int) $this->input->post('port');
        $database = trim((string) $this->input->post('database_name', TRUE));
        $username = trim((string) $this->input->post('username', TRUE));
        $password = (string) $this->input->post('password');
        $sslMode = trim((string) $this->input->post('ssl_mode', TRUE));
        $isActive = (string) $this->input->post('is_active') !== '0';

        if(!in_array($driver, array('mysql', 'pgsql'), TRUE)
            || !in_array($sslMode, array('required', 'preferred', 'disabled'), TRUE)
            || $name === '' || strlen($name) > 120
            || !preg_match('/^[A-Za-z0-9._:-]{1,255}$/', $host)
            || $port < 1 || $port > 65535
            || !preg_match('/^[^\x00-\x1F\x7F]{1,128}$/', $database)
            || !preg_match('/^[^\x00-\x1F\x7F]{1,128}$/', $username)) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Check the connection name, driver, host, port, database, username, and TLS mode.'), 422);
            return;
        }

        $existing = $id > 0 ? $this->model->getConnection($id, TRUE) : FALSE;
        if($id > 0 && !$existing) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Connection not found.'), 404);
            return;
        }
        $this->load->library('encryption');
        if($password === '' && $existing) {
            $password = $this->encryption->decrypt($existing['password_encrypted']);
        }
        if($password === FALSE || ($password === '' && !$existing) || strlen((string) $password) > 1000) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Enter a valid database password.'), 422);
            return;
        }

        $connection = array(
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database_name' => $database,
            'username' => $username,
            'ssl_mode' => $sslMode
        );
        $test = $this->model->testExternalConnection($connection, $password);
        if(!$test['status']) {
            $this->jsonResponse($test, 422);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = array_merge($connection, array(
            'name' => $name,
            'password_encrypted' => $this->encryption->encrypt($password),
            'is_active' => $isActive ? 1 : 0,
            'updated_at' => $now
        ));
        if(!$existing) {
            $data['owner_id'] = (int) $this->vendorId;
            $data['owner'] = $this->name;
            $data['created_at'] = $now;
        }
        $savedId = $this->model->saveConnection($id, $data);
        $this->jsonResponse(array('status' => TRUE, 'id' => $savedId, 'message' => 'Connection verified and saved.'));
    }

    public function deleteConnection()
    {
        if(!$this->requireVisualizationManagerJson('POST')) {
            return;
        }
        $deleted = $this->model->deleteConnection((int) $this->input->post('id'));
        $this->jsonResponse(array('status' => $deleted > 0, 'message' => $deleted > 0 ? 'Connection and its curated datasets were removed.' : 'Connection not found.'), $deleted > 0 ? 200 : 404);
    }

    public function connectionTables($id = 0)
    {
        if(!$this->requireVisualizationManagerJson('GET')) {
            return;
        }
        $tables = $this->model->discoverExternalTables((int) $id);
        if($tables === FALSE) {
            $this->jsonResponse(array('status' => FALSE, 'message' => $this->model->lastDataSourceError()), 422);
            return;
        }
        $this->jsonResponse(array('status' => TRUE, 'data' => $tables));
    }

    public function connectionColumns($id = 0)
    {
        if(!$this->requireVisualizationManagerJson('GET')) {
            return;
        }
        $schema = trim((string) $this->input->get('schema', TRUE));
        $table = trim((string) $this->input->get('table', TRUE));
        if($schema === '' || $table === '' || strlen($schema) > 128 || strlen($table) > 128) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Choose a table first.'), 422);
            return;
        }
        $columns = $this->model->discoverExternalColumns((int) $id, $schema, $table);
        if($columns === FALSE) {
            $this->jsonResponse(array('status' => FALSE, 'message' => $this->model->lastDataSourceError()), 422);
            return;
        }
        $this->jsonResponse(array('status' => TRUE, 'data' => $columns));
    }

    public function saveDataset()
    {
        if(!$this->requireVisualizationManagerJson('POST')) {
            return;
        }
        $id = (int) $this->input->post('id');
        $connectionId = (int) $this->input->post('connection_id');
        $name = trim(strip_tags((string) $this->input->post('name')));
        $description = trim(strip_tags((string) $this->input->post('description')));
        $schema = trim((string) $this->input->post('table_schema', TRUE));
        $table = trim((string) $this->input->post('table_name', TRUE));
        $dimensionColumns = $this->input->post('dimension_columns');
        $measureColumns = $this->input->post('measure_columns');
        $timeColumn = trim((string) $this->input->post('time_column', TRUE));
        $environmentColumn = trim((string) $this->input->post('environment_column', TRUE));
        $dimensionColumns = is_array($dimensionColumns) ? array_values(array_unique($dimensionColumns)) : array();
        $measureColumns = is_array($measureColumns) ? array_values(array_unique($measureColumns)) : array();

        if($connectionId < 1 || $name === '' || strlen($name) > 120 || strlen($description) > 500 || $schema === '' || $table === '' || count($dimensionColumns) > 20 || count($measureColumns) > 10) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Choose a connection and table, then provide a concise dataset name.'), 422);
            return;
        }
        $tables = $this->model->discoverExternalTables($connectionId);
        $tableAllowed = FALSE;
        foreach((array) $tables as $candidate) {
            if($candidate['table_schema'] === $schema && $candidate['table_name'] === $table) {
                $tableAllowed = TRUE;
                break;
            }
        }
        if(!$tableAllowed) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'That table is not available through this connection.'), 422);
            return;
        }
        $columns = $this->model->discoverExternalColumns($connectionId, $schema, $table);
        if($columns === FALSE) {
            $this->jsonResponse(array('status' => FALSE, 'message' => $this->model->lastDataSourceError()), 422);
            return;
        }
        $columnMap = array();
        foreach($columns as $column) {
            $columnMap[$column['column_name']] = strtolower($column['data_type']);
        }
        foreach(array_merge($dimensionColumns, $measureColumns, array($timeColumn, $environmentColumn)) as $column) {
            if($column !== '' && !isset($columnMap[$column])) {
                $this->jsonResponse(array('status' => FALSE, 'message' => 'One of the selected columns is no longer available.'), 422);
                return;
            }
        }

        $numericTypes = array('tinyint','smallint','mediumint','int','integer','bigint','decimal','numeric','float','double','real','smallserial','serial','bigserial','money');
        $dateTypes = array('date','datetime','timestamp','timestamp without time zone','timestamp with time zone');
        $dimensions = array();
        foreach($dimensionColumns as $column) {
            $label = $this->humanizeDataField($column);
            $slug = $this->dataFieldKey($column);
            if(in_array($columnMap[$column], $dateTypes, TRUE)) {
                $dimensions[] = array('key' => 'd_'.$slug.'_day', 'label' => $label.' · day', 'type' => 'time', 'source' => $column, 'bucket' => 'day');
                $dimensions[] = array('key' => 'd_'.$slug.'_month', 'label' => $label.' · month', 'type' => 'time', 'source' => $column, 'bucket' => 'month');
            } else {
                $dimensions[] = array('key' => 'd_'.$slug, 'label' => $label, 'type' => 'category', 'source' => $column);
            }
        }
        $measures = array(array('key' => 'rows', 'label' => 'Rows', 'format' => 'number', 'aggregation' => 'count'));
        foreach($measureColumns as $column) {
            if(!in_array($columnMap[$column], $numericTypes, TRUE)) {
                $this->jsonResponse(array('status' => FALSE, 'message' => $column.' is not a numeric measure.'), 422);
                return;
            }
            $label = $this->humanizeDataField($column);
            $slug = $this->dataFieldKey($column);
            $measures[] = array('key' => 'sum_'.$slug, 'label' => $label.' · sum', 'format' => 'number', 'source' => $column, 'aggregation' => 'sum');
            $measures[] = array('key' => 'avg_'.$slug, 'label' => $label.' · average', 'format' => 'number', 'source' => $column, 'aggregation' => 'avg');
        }
        if(empty($dimensions)) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Select at least one dimension column.'), 422);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'connection_id' => $connectionId,
            'name' => $name,
            'description' => $description,
            'table_schema' => $schema,
            'table_name' => $table,
            'dimensions_json' => json_encode($dimensions),
            'measures_json' => json_encode($measures),
            'time_column' => $timeColumn,
            'environment_column' => $environmentColumn,
            'is_active' => 1,
            'updated_at' => $now
        );
        if($id < 1) {
            $data['dataset_key'] = 'source_'.bin2hex(random_bytes(8));
            $data['owner_id'] = (int) $this->vendorId;
            $data['owner'] = $this->name;
            $data['created_at'] = $now;
        }
        $savedId = $this->model->saveExternalDataset($id, $data);
        $this->jsonResponse(array('status' => TRUE, 'id' => $savedId, 'message' => 'Dataset published to Insight Studio.'));
    }

    public function deleteDataset()
    {
        if(!$this->requireVisualizationManagerJson('POST')) {
            return;
        }
        $deleted = $this->model->deleteExternalDataset((int) $this->input->post('id'));
        $this->jsonResponse(array('status' => $deleted > 0, 'message' => $deleted > 0 ? 'Dataset removed from Insight Studio.' : 'Dataset not found.'), $deleted > 0 ? 200 : 404);
    }

    public function datasets()
    {
        $this->jsonResponse(array('status' => TRUE, 'data' => array_values($this->model->studioDatasets())));
    }

    public function query()
    {
        $dataset = trim((string) $this->input->get('dataset', TRUE));
        $dimension = trim((string) $this->input->get('dimension', TRUE));
        $measure = trim((string) $this->input->get('measure', TRUE));
        $dateRange = trim((string) $this->input->get('date_range', TRUE));
        $environment = trim((string) $this->input->get('environment', TRUE));
        $limit = (int) $this->input->get('limit', TRUE);
        $allowedRanges = array('7d', '30d', '90d', '180d', 'all');

        if(!in_array($dateRange, $allowedRanges, TRUE)) {
            $dateRange = '30d';
        }

        $rows = $this->model->studioQuery($dataset, $dimension, $measure, $dateRange, $environment, $limit ?: 20);
        if($rows === FALSE) {
            $connectionError = $this->model->lastDataSourceError();
            $this->jsonResponse(array('status' => FALSE, 'message' => $connectionError !== '' ? $connectionError : 'The requested dataset field combination is not available.'), 422);
            return;
        }

        foreach($rows as &$row) {
            $row['label'] = isset($row['label']) && $row['label'] !== NULL ? (string) $row['label'] : 'Unknown';
            $row['value'] = isset($row['value']) && $row['value'] !== NULL ? (float) $row['value'] : 0;
        }

        $this->jsonResponse(array(
            'status' => TRUE,
            'data' => $rows,
            'meta' => array(
                'dataset' => $dataset,
                'dimension' => $dimension,
                'measure' => $measure,
                'date_range' => $dateRange,
                'environment' => $environment === '' ? 'all' : $environment,
                'generated_at' => date(DATE_ATOM)
            )
        ));
    }

    public function dashboard($id = 0)
    {
        $record = $this->model->getDashboard((int) $id, $this->vendorId);
        if(empty($record)) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Dashboard not found or not shared with you.'), 404);
            return;
        }

        $definition = json_decode($record->definition_json, TRUE);
        unset($record->definition_json);
        $record->definition = is_array($definition) ? $definition : array('widgets' => array());
        $record->can_edit = (int) $record->owner_id === (int) $this->vendorId;
        $this->jsonResponse(array('status' => TRUE, 'data' => $record));
    }

    public function saveDashboard()
    {
        if($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Dashboard saves must use POST.'), 405);
            return;
        }

        $id = (int) $this->input->post('id');
        $name = trim(strip_tags((string) $this->input->post('name')));
        $description = trim(strip_tags((string) $this->input->post('description')));
        $definitionJson = (string) $this->input->post('definition');
        $isShared = (string) $this->input->post('is_shared') === '1';

        if($name === '' || strlen($name) > 120 || strlen($description) > 500 || strlen($definitionJson) > 150000) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Use a name up to 120 characters and a compact dashboard definition.'), 422);
            return;
        }

        $definition = $this->normalizeDashboardDefinition($definitionJson);
        if($definition === FALSE) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'The dashboard contains an unsupported chart or dataset field.'), 422);
            return;
        }

        if($id > 0) {
            $existing = $this->model->getDashboard($id, $this->vendorId);
            if(empty($existing) || (int) $existing->owner_id !== (int) $this->vendorId) {
                $this->jsonResponse(array('status' => FALSE, 'message' => 'Shared dashboards are read-only. Save a copy to customize one.'), 403);
                return;
            }
        }

        $savedId = $this->model->saveDashboard(
            $id,
            $this->vendorId,
            $this->name,
            $name,
            $description,
            json_encode($definition),
            $isShared
        );

        if($savedId === FALSE || $savedId < 1) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Dashboard could not be saved.'), 500);
            return;
        }

        $this->jsonResponse(array('status' => TRUE, 'id' => $savedId, 'message' => 'Dashboard saved.'));
    }

    public function deleteDashboard()
    {
        if($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Dashboard deletes must use POST.'), 405);
            return;
        }

        $id = (int) $this->input->post('id');
        $deleted = $this->model->deleteDashboard($id, $this->vendorId, $this->role === ROLE_ADMIN);
        $this->jsonResponse(array(
            'status' => $deleted > 0,
            'message' => $deleted > 0 ? 'Dashboard deleted.' : 'Dashboard not found or you do not own it.'
        ), $deleted > 0 ? 200 : 404);
    }

    public function fetch($id) {

      if($this->isManager() == TRUE)
        {
             $this->loadThis();
        }
         else
         {

         header('Content-type:application/json;charset=utf-8'); // declaring header

         $this->global['pageTitle'] = 'Job Seeker : Json Parse';

         $listJobsJson["data"] = $this->sanitizeReportRows($this->model->fetch((int) $id));
         echo json_encode($listJobsJson, JSON_PRETTY_PRINT);
     }

     }

     public function config()
    {

         if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->global['pageTitle'] = 'Job Seeker : Visualization Config';
            $user = $this->global['name'];

            $data["list"] = $this->model->list();
            $data["reports"] = $this->model->listReports();
            $data["types"] = $this->model->listTypes();
            $data["users"] = $this->model->listUsers();
            $data["groups"] = $this->model->listGroups();
            
            $this->loadViews("visualizationConfig", $this->global, $data, NULL);
        }
    }

       public function add() {

        if($this->isManager() == TRUE)
            {
                $this->loadThis();
            }
            else
            {
            
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('name','Report Name','required|max_length[120]');
            $this->form_validation->set_rules('type','Report Software Type','required|max_length[30]');
            $this->form_validation->set_rules('code','Embebed Code','trim|required|max_length[5000]');

            if($this->form_validation->run() == FALSE)
            {
                $this->config();
            }
            else
            {

                $name = $this->security->xss_clean($this->input->post('name'));
                $type = $this->security->xss_clean($this->input->post('type'));
                $allowedTypes = array('pbi', 'tbl', 'tblPublic', 'qlikSense', 'qlikView', 'superset', 'metabase', 'grafana', 'looker', 'microstrategy', 'custom');
                if(!in_array($type, $allowedTypes, TRUE)) {
                    $type = 'custom';
                }
                $users = $this->security->xss_clean($this->input->post('users'));
                $groups = $this->security->xss_clean($this->input->post('groups'));
                $code = $this->normalizeReportEmbed($this->input->post('code'));

                if($code === FALSE) {
                    $this->session->set_flashdata('error', 'Report creation failed ! Use a valid HTTP(S) report URL or iframe embed code.');
                    redirect('Visualization/config');
                }

                $users = is_array($users) ? $users : array();
                $groups = is_array($groups) ? $groups : array();

                if (empty($users) && empty($groups)) {
                   $this->session->set_flashdata('error', 'Report creation failed ! You must select at the least one user or group');
                   redirect('Visualization/config');
                }

                if(empty($users)) {
                    $stringUsers = 'All Users from group';
                } else {
                  $stringUsers = implode(",", $users);
                }

                if(empty($groups)) {
                    $stringGroups = 'None';
                } else {
                  $stringGroups = implode(",", $groups);
                }

                // Check if the data is alredy on table
                 $validateSetting = $this->model->validate($name);


                 $Info = array(
                    'name'=>$name, 
                    'type'=>$type, 
                    'users' => $stringUsers,
                    'groups' => $stringGroups,
                    'code' => $code,
                    'creation_date'=>date('Y-m-d H:i:s'),
                    'owner'=>$this->name
                 );

                 if($validateSetting > 0){

                    $this->session->set_flashdata('error', 'This row seems already created, please try changing the report name.');
                } else {
                
                $result = $this->model->insert($Info);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Report has successfully created and now is available to be used.');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Report creation failed !');
                }

             }

              redirect('Visualization/config');

            }
           
        }

    }

      public function delete() {

        if($this->isManager() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            if($this->input->method(TRUE) !== 'POST') {
                $this->output->set_status_header(405);
                echo(json_encode(array('status'=>FALSE, 'message'=>'Delete requests must use POST.')));
                return;
            }

            $id = (int) $this->input->post('userId');
            /*
            $userInfo = array('isDeleted'=> 1,'updatedBy'=>$this->vendorId, 'field' => $id,'updatedDtm'=>date('Y-m-d H:i:s')); Future Release Not working */
            
            $result = $this->model->delete($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }


    private function sanitizeReportRows($rows)
    {
        if(empty($rows)) {
            return $rows;
        }

        foreach($rows as $row) {
            if(isset($row->code)) {
                $safeCode = $this->normalizeReportEmbed($row->code);
                $row->code = ($safeCode === FALSE) ? '' : $safeCode;
            }
        }

        return $rows;
    }

    private function normalizeReportEmbed($code)
    {
        $code = trim((string) $code);

        if($code === '') {
            return FALSE;
        }

        if(strpos($code, '<') === FALSE && strpos($code, '>') === FALSE) {
            return $this->buildReportIframe($code);
        }

        return $this->sanitizeIframeEmbed($code);
    }

    private function sanitizeIframeEmbed($html)
    {
        if(!class_exists('DOMDocument')) {
            if(preg_match('/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>\s*<\/iframe>/is', $html, $matches)) {
                return $this->buildReportIframe($matches[2]);
            }

            return FALSE;
        }

        $previousErrors = libxml_use_internal_errors(TRUE);
        $dom = new DOMDocument();
        $options = 0;
        if(defined('LIBXML_HTML_NODEFDTD')) {
            $options |= LIBXML_HTML_NODEFDTD;
        }
        if(defined('LIBXML_HTML_NOIMPLIED')) {
            $options |= LIBXML_HTML_NOIMPLIED;
        }

        $loaded = $dom->loadHTML('<!DOCTYPE html><html><body>'.$html.'</body></html>', $options);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if(!$loaded) {
            return FALSE;
        }

        $iframes = $dom->getElementsByTagName('iframe');
        if($iframes->length !== 1) {
            return FALSE;
        }

        $iframe = $iframes->item(0);
        $attributes = array();
        foreach(array('title') as $attribute) {
            if($iframe->hasAttribute($attribute)) {
                $attributes[$attribute] = $iframe->getAttribute($attribute);
            }
        }
        if($iframe->hasAttribute('allowfullscreen')) {
            $attributes['allowfullscreen'] = TRUE;
        }

        return $this->buildReportIframe($iframe->getAttribute('src'), $attributes);
    }

    private function buildReportIframe($url, $sourceAttributes = array())
    {
        $url = trim((string) $url);
        if(!$this->isSafeReportUrl($url)) {
            return FALSE;
        }

        $attributes = array(
            'src' => $url,
            'title' => 'Connected analytics report',
            'style' => 'border:none;width:100%;height:100%;',
            'sandbox' => 'allow-scripts allow-same-origin allow-forms allow-popups allow-downloads',
            'referrerpolicy' => 'no-referrer',
            'loading' => 'lazy',
            'allow' => 'fullscreen; clipboard-write',
            'allowfullscreen' => TRUE
        );
        foreach($sourceAttributes as $name => $value) {
            $cleanValue = $this->cleanIframeAttribute($name, $value);
            if($cleanValue !== NULL) {
                $attributes[$name] = $cleanValue;
            }
        }

        $htmlAttributes = array();
        foreach($attributes as $name => $value) {
            if($name === 'allowfullscreen') {
                continue;
            }
            $htmlAttributes[] = $name.'="'.$this->escapeAttribute($value).'"';
        }
        if(!empty($attributes['allowfullscreen'])) {
            $htmlAttributes[] = 'allowfullscreen';
        }

        return '<iframe '.implode(' ', $htmlAttributes).'></iframe>';
    }

    private function cleanIframeAttribute($name, $value)
    {
        $value = trim((string) $value);

        switch($name) {
            case 'title':
                return substr(strip_tags($value), 0, 200);
            case 'width':
            case 'height':
                return preg_match('/^\d{1,4}(\.\d{1,2})?(%|px)?$/', $value) ? $value : NULL;
            case 'frameborder':
                return in_array($value, array('0', '1'), TRUE) ? $value : NULL;
            case 'allow':
                return (strlen($value) <= 500 && !preg_match('/[\x00-\x1F\x7F]/', $value)) ? $value : NULL;
            case 'sandbox':
                return preg_match('/^[a-zA-Z0-9\- ]{0,300}$/', $value) ? $value : NULL;
            case 'referrerpolicy':
                return in_array(strtolower($value), array('no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url'), TRUE) ? strtolower($value) : NULL;
            case 'loading':
                return in_array(strtolower($value), array('lazy', 'eager'), TRUE) ? strtolower($value) : NULL;
            case 'allowfullscreen':
                return $value ? TRUE : NULL;
            default:
                return NULL;
        }
    }

    private function isSafeReportUrl($url)
    {
        if(strlen($url) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return FALSE;
        }

        if(!filter_var($url, FILTER_VALIDATE_URL)) {
            return FALSE;
        }

        $parts = parse_url($url);
        if(empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), TRUE)) {
            return FALSE;
        }

        // A scripted, same-origin frame combined with allow-same-origin could
        // remove its own sandbox. Connected analytics must live on a distinct
        // origin (a different host or port) from Jobseeker itself.
        $applicationParts = parse_url(base_url());
        $urlPort = isset($parts['port']) ? (int) $parts['port'] : (strtolower($parts['scheme']) === 'https' ? 443 : 80);
        $applicationPort = isset($applicationParts['port']) ? (int) $applicationParts['port'] : (isset($applicationParts['scheme']) && strtolower($applicationParts['scheme']) === 'https' ? 443 : 80);
        if(!empty($applicationParts['host']) && !empty($parts['host'])
            && strtolower($applicationParts['host']) === strtolower($parts['host'])
            && $applicationPort === $urlPort) {
            return FALSE;
        }

        return empty($parts['user']) && empty($parts['pass']);
    }

    private function normalizeDashboardDefinition($definitionJson)
    {
        $definition = json_decode($definitionJson, TRUE);
        if(!is_array($definition) || !isset($definition['widgets']) || !is_array($definition['widgets']) || count($definition['widgets']) > 24) {
            return FALSE;
        }

        $datasets = $this->model->studioDatasets();
        $chartTypes = array('bar', 'line', 'doughnut', 'kpi', 'table');
        $sizes = array(4, 6, 8, 12);
        $ranges = array('7d', '30d', '90d', '180d', 'all');
        $palettes = array('aurora', 'signal', 'ocean', 'mono');
        $widgets = array();
        $widgetIds = array();

        foreach($definition['widgets'] as $index => $widget) {
            if(!is_array($widget) || empty($widget['dataset']) || !isset($datasets[$widget['dataset']])) {
                return FALSE;
            }

            $dataset = $datasets[$widget['dataset']];
            $dimensionKeys = array_map(function($field) { return $field['key']; }, $dataset['dimensions']);
            $measureKeys = array_map(function($field) { return $field['key']; }, $dataset['measures']);
            $chart = isset($widget['chart']) && in_array($widget['chart'], $chartTypes, TRUE) ? $widget['chart'] : 'bar';
            $dimension = isset($widget['dimension']) ? (string) $widget['dimension'] : '';
            $measure = isset($widget['measure']) ? (string) $widget['measure'] : '';

            if($chart !== 'kpi' && !in_array($dimension, $dimensionKeys, TRUE)) {
                return FALSE;
            }
            if($chart === 'kpi') {
                $dimension = '';
            }
            if(!in_array($measure, $measureKeys, TRUE)) {
                return FALSE;
            }

            $title = isset($widget['title']) ? trim(strip_tags((string) $widget['title'])) : '';
            if($title === '') {
                $title = $dataset['name'];
            }

            $widgetId = isset($widget['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $widget['id']) : '';
            if($widgetId === '' || isset($widgetIds[$widgetId])) {
                $widgetId = 'widget_'.($index + 1).'_'.substr(sha1($title.$index), 0, 8);
            }
            $widgetIds[$widgetId] = TRUE;

            $widgets[] = array(
                'id' => $widgetId,
                'title' => substr($title, 0, 100),
                'dataset' => $widget['dataset'],
                'chart' => $chart,
                'dimension' => $dimension,
                'measure' => $measure,
                'date_range' => isset($widget['date_range']) && in_array($widget['date_range'], $ranges, TRUE) ? $widget['date_range'] : '30d',
                'environment' => isset($widget['environment']) ? substr(preg_replace('/[^a-zA-Z0-9_*\-]/', '', (string) $widget['environment']), 0, 30) : 'all',
                'palette' => isset($widget['palette']) && in_array($widget['palette'], $palettes, TRUE) ? $widget['palette'] : 'aurora',
                'size' => isset($widget['size']) && in_array((int) $widget['size'], $sizes, TRUE) ? (int) $widget['size'] : 6
            );
        }

        return array('version' => 1, 'widgets' => $widgets);
    }

    private function requireVisualizationManagerJson($method)
    {
        if($this->isManager() === TRUE) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'Administrator or manager access is required.'), 403);
            return FALSE;
        }
        if($this->input->method(TRUE) !== strtoupper($method)) {
            $this->jsonResponse(array('status' => FALSE, 'message' => 'This action must use '.strtoupper($method).'.'), 405);
            return FALSE;
        }
        return TRUE;
    }

    private function humanizeDataField($column)
    {
        $label = preg_replace('/[_\-]+/', ' ', (string) $column);
        $label = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $label);
        return ucwords(trim($label));
    }

    private function dataFieldKey($column)
    {
        $key = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $column));
        $key = trim($key, '_');
        if($key === '') {
            $key = 'field';
        }
        return substr($key, 0, 36).'_'.substr(sha1((string) $column), 0, 6);
    }

    private function jsonResponse($payload, $status = 200)
    {
        $this->output
            ->set_status_header((int) $status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload));
    }

    private function escapeAttribute($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

}

?>
