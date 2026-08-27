<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Visualization_model extends CI_Model
{

    private $dashboardSchemaReady = FALSE;
    private $dataSourceSchemaReady = FALSE;
    private $lastDataSourceError = '';

    /**
     * Studio data sources are deliberately defined here instead of accepting a
     * table name, column name, or SQL fragment from the browser. This is the
     * security boundary that lets analysts explore operational data without
     * receiving database credentials or a general-purpose query endpoint.
     */
    function studioDatasets()
    {
        $datasets = array(
            'tmf_runs' => array(
                'key' => 'tmf_runs',
                'name' => 'TMF runs',
                'description' => 'Execution health, throughput and processing history.',
                'icon' => 'fa-heartbeat',
                'freshness' => 'Live',
                'default_dimension' => 'status',
                'default_measure' => 'runs',
                'dimensions' => array(
                    array('key' => 'status', 'label' => 'Status', 'type' => 'category'),
                    array('key' => 'job_name', 'label' => 'Job name', 'type' => 'category'),
                    array('key' => 'dimension', 'label' => 'Dimension', 'type' => 'category'),
                    array('key' => 'environment', 'label' => 'Environment', 'type' => 'category'),
                    array('key' => 'activity_day', 'label' => 'Activity day', 'type' => 'time'),
                    array('key' => 'activity_month', 'label' => 'Activity month', 'type' => 'time')
                ),
                'measures' => array(
                    array('key' => 'runs', 'label' => 'Runs', 'format' => 'number'),
                    array('key' => 'failures', 'label' => 'Needs attention', 'format' => 'number'),
                    array('key' => 'total_records', 'label' => 'Records expected', 'format' => 'number'),
                    array('key' => 'processed_records', 'label' => 'Records processed', 'format' => 'number'),
                    array('key' => 'completion_rate', 'label' => 'Completion rate', 'format' => 'percent'),
                    array('key' => 'avg_runtime', 'label' => 'Average runtime', 'format' => 'duration')
                )
            ),
            'tmf_errors' => array(
                'key' => 'tmf_errors',
                'name' => 'TMF errors',
                'description' => 'Error events joined to their originating TMF run.',
                'icon' => 'fa-bug',
                'freshness' => 'Live',
                'default_dimension' => 'type',
                'default_measure' => 'errors',
                'dimensions' => array(
                    array('key' => 'type', 'label' => 'Error type', 'type' => 'category'),
                    array('key' => 'origin', 'label' => 'Origin', 'type' => 'category'),
                    array('key' => 'job_name', 'label' => 'Job name', 'type' => 'category'),
                    array('key' => 'environment', 'label' => 'Environment', 'type' => 'category'),
                    array('key' => 'event_day', 'label' => 'Event day', 'type' => 'time')
                ),
                'measures' => array(
                    array('key' => 'errors', 'label' => 'Errors', 'format' => 'number'),
                    array('key' => 'affected_runs', 'label' => 'Affected runs', 'format' => 'number')
                )
            ),
            'job_catalog' => array(
                'key' => 'job_catalog',
                'name' => 'Job catalog',
                'description' => 'Components registered in the Jobseeker job inventory.',
                'icon' => 'fa-cubes',
                'freshness' => 'Live',
                'default_dimension' => 'component_type',
                'default_measure' => 'components',
                'dimensions' => array(
                    array('key' => 'component_type', 'label' => 'Component type', 'type' => 'category'),
                    array('key' => 'job_name', 'label' => 'Job name', 'type' => 'category'),
                    array('key' => 'owner', 'label' => 'Owner', 'type' => 'category'),
                    array('key' => 'created_month', 'label' => 'Created month', 'type' => 'time')
                ),
                'measures' => array(
                    array('key' => 'components', 'label' => 'Components', 'format' => 'number'),
                    array('key' => 'uploaded_files', 'label' => 'Uploaded files', 'format' => 'number')
                )
            ),
            'job_outputs' => array(
                'key' => 'job_outputs',
                'name' => 'Job outputs',
                'description' => 'Files and artifacts produced by registered jobs.',
                'icon' => 'fa-file-archive-o',
                'freshness' => 'Live',
                'default_dimension' => 'component_type',
                'default_measure' => 'outputs',
                'dimensions' => array(
                    array('key' => 'component_type', 'label' => 'Component type', 'type' => 'category'),
                    array('key' => 'job_name', 'label' => 'Job name', 'type' => 'category'),
                    array('key' => 'owner', 'label' => 'Owner', 'type' => 'category'),
                    array('key' => 'created_month', 'label' => 'Created month', 'type' => 'time')
                ),
                'measures' => array(
                    array('key' => 'outputs', 'label' => 'Output files', 'format' => 'number'),
                    array('key' => 'downloaded_outputs', 'label' => 'Downloaded outputs', 'format' => 'number'),
                    array('key' => 'download_events', 'label' => 'Download events', 'format' => 'number')
                )
            ),
            'login_activity' => array(
                'key' => 'login_activity',
                'name' => 'Login activity',
                'description' => 'Account sessions by user, platform and time.',
                'icon' => 'fa-sign-in',
                'freshness' => 'Live',
                'default_dimension' => 'activity_day',
                'default_measure' => 'sessions',
                'dimensions' => array(
                    array('key' => 'user', 'label' => 'User', 'type' => 'category'),
                    array('key' => 'platform', 'label' => 'Platform', 'type' => 'category'),
                    array('key' => 'activity_day', 'label' => 'Activity day', 'type' => 'time'),
                    array('key' => 'activity_hour', 'label' => 'Hour of day', 'type' => 'category')
                ),
                'measures' => array(
                    array('key' => 'sessions', 'label' => 'Sessions', 'format' => 'number'),
                    array('key' => 'unique_users', 'label' => 'Unique users', 'format' => 'number')
                )
            ),
            'context_inventory' => array(
                'key' => 'context_inventory',
                'name' => 'Context inventory',
                'description' => 'Configuration-key coverage without exposing context values.',
                'icon' => 'fa-key',
                'freshness' => 'Live',
                'default_dimension' => 'environment',
                'default_measure' => 'keys',
                'dimensions' => array(
                    array('key' => 'environment', 'label' => 'Environment', 'type' => 'category'),
                    array('key' => 'project', 'label' => 'Project', 'type' => 'category'),
                    array('key' => 'state', 'label' => 'State', 'type' => 'category'),
                    array('key' => 'created_month', 'label' => 'Created month', 'type' => 'time')
                ),
                'measures' => array(
                    array('key' => 'keys', 'label' => 'Context keys', 'format' => 'number'),
                    array('key' => 'active_keys', 'label' => 'Active keys', 'format' => 'number'),
                    array('key' => 'encrypted_keys', 'label' => 'Encrypted keys', 'format' => 'number')
                )
            )
        );

        foreach($this->externalDatasetsMetadata() as $dataset) {
            $datasets[$dataset['key']] = $dataset;
        }

        return $datasets;
    }

    private function studioQueryDefinition($dataset)
    {
        $definitions = array(
            'tmf_runs' => array(
                'from' => 'tmf',
                'time' => 'last_activity',
                'environment' => 'environment',
                'dimensions' => array(
                    'status' => 'COALESCE(NULLIF(LOWER(TRIM(status)), ""), "unknown")',
                    'job_name' => 'COALESCE(NULLIF(TRIM(job_name), ""), "Unnamed job")',
                    'dimension' => 'COALESCE(NULLIF(TRIM(dimension), ""), "Unassigned")',
                    'environment' => 'COALESCE(NULLIF(UPPER(TRIM(environment)), ""), "Unknown")',
                    'activity_day' => 'DATE(last_activity)',
                    'activity_month' => 'DATE_FORMAT(last_activity, "%Y-%m")'
                ),
                'measures' => array(
                    'runs' => 'COUNT(*)',
                    'failures' => 'SUM(CASE WHEN LOWER(status) IN ("error", "warning") THEN 1 ELSE 0 END)',
                    'total_records' => 'SUM(CAST(COALESCE(NULLIF(records_total, ""), "0") AS UNSIGNED))',
                    'processed_records' => 'SUM(CAST(COALESCE(NULLIF(records_processed, ""), "0") AS UNSIGNED))',
                    'completion_rate' => 'ROUND(100 * SUM(CAST(COALESCE(NULLIF(records_processed, ""), "0") AS UNSIGNED)) / NULLIF(SUM(CAST(COALESCE(NULLIF(records_total, ""), "0") AS UNSIGNED)), 0), 1)',
                    'avg_runtime' => 'ROUND(AVG(TIME_TO_SEC(running_time)), 1)'
                )
            ),
            'tmf_errors' => array(
                'from' => 'tmf_error error_event',
                'join' => array('tmf run', 'run.instance_id = error_event.tmf_id', 'left'),
                'time' => 'error_event.moment',
                'environment' => 'run.environment',
                'dimensions' => array(
                    'type' => 'COALESCE(NULLIF(TRIM(error_event.type), ""), "Unknown")',
                    'origin' => 'COALESCE(NULLIF(TRIM(error_event.origin), ""), "Unknown")',
                    'job_name' => 'COALESCE(NULLIF(TRIM(error_event.job_name), ""), "Unnamed job")',
                    'environment' => 'COALESCE(NULLIF(UPPER(TRIM(run.environment)), ""), "Unknown")',
                    'event_day' => 'DATE(error_event.moment)'
                ),
                'measures' => array(
                    'errors' => 'COUNT(error_event.id)',
                    'affected_runs' => 'COUNT(DISTINCT error_event.tmf_id)'
                )
            ),
            'job_catalog' => array(
                'from' => 'job_info',
                'time' => 'creation_date',
                'environment' => '',
                'dimensions' => array(
                    'component_type' => 'COALESCE(NULLIF(TRIM(component_type), ""), "Unknown")',
                    'job_name' => 'COALESCE(NULLIF(TRIM(job_name), ""), "Unnamed job")',
                    'owner' => 'COALESCE(NULLIF(TRIM(owner), ""), "Unassigned")',
                    'created_month' => 'DATE_FORMAT(creation_date, "%Y-%m")'
                ),
                'measures' => array(
                    'components' => 'COUNT(*)',
                    'uploaded_files' => 'SUM(CASE WHEN file_uploaded IS NOT NULL AND file_uploaded > 0 THEN 1 ELSE 0 END)'
                )
            ),
            'job_outputs' => array(
                'from' => 'job_output',
                'time' => 'creation_date',
                'environment' => '',
                'dimensions' => array(
                    'component_type' => 'COALESCE(NULLIF(TRIM(component_type), ""), "Unknown")',
                    'job_name' => 'COALESCE(NULLIF(TRIM(job_name), ""), "Unnamed job")',
                    'owner' => 'COALESCE(NULLIF(TRIM(owner), ""), "Unassigned")',
                    'created_month' => 'DATE_FORMAT(creation_date, "%Y-%m")'
                ),
                'measures' => array(
                    'outputs' => 'COUNT(*)',
                    'downloaded_outputs' => 'SUM(CASE WHEN file_downloaded IS NOT NULL AND file_downloaded > 0 THEN 1 ELSE 0 END)',
                    'download_events' => 'SUM(COALESCE(file_downloaded, 0))'
                )
            ),
            'login_activity' => array(
                'from' => 'tbl_last_login login_event',
                'join' => array('tbl_users login_user', 'login_user.userId = login_event.userId', 'left'),
                'time' => 'login_event.createdDtm',
                'environment' => '',
                'dimensions' => array(
                    'user' => 'COALESCE(NULLIF(TRIM(login_user.name), ""), "Unknown user")',
                    'platform' => 'COALESCE(NULLIF(TRIM(login_event.platform), ""), "Unknown")',
                    'activity_day' => 'DATE(login_event.createdDtm)',
                    'activity_hour' => 'LPAD(HOUR(login_event.createdDtm), 2, "0")'
                ),
                'measures' => array(
                    'sessions' => 'COUNT(login_event.id)',
                    'unique_users' => 'COUNT(DISTINCT login_event.userId)'
                )
            ),
            'context_inventory' => array(
                'from' => 'contextdetails context_item',
                'joins' => array(
                    array('environment context_environment', 'context_environment.Id = context_item.EnvironmentFK', 'left'),
                    array('projectdetails context_project', 'context_project.Id = context_item.ProjectDetailsFK', 'left')
                ),
                'time' => 'context_item.CreatedOn',
                'environment' => 'context_environment.Environment',
                'dimensions' => array(
                    'environment' => 'COALESCE(NULLIF(UPPER(TRIM(context_environment.Environment)), ""), "Unknown")',
                    'project' => 'COALESCE(NULLIF(TRIM(context_project.ProjectName), ""), "Unassigned")',
                    'state' => 'CASE WHEN context_item.IsActive = 1 THEN "Active" ELSE "Inactive" END',
                    'created_month' => 'DATE_FORMAT(context_item.CreatedOn, "%Y-%m")'
                ),
                'measures' => array(
                    'keys' => 'COUNT(context_item.Id)',
                    'active_keys' => 'SUM(CASE WHEN context_item.IsActive = 1 THEN 1 ELSE 0 END)',
                    'encrypted_keys' => 'SUM(CASE WHEN context_item.isEncrypted = 1 THEN 1 ELSE 0 END)'
                )
            )
        );

        return isset($definitions[$dataset]) ? $definitions[$dataset] : FALSE;
    }

    function studioQuery($dataset, $dimension, $measure, $dateRange, $environment, $limit = 20)
    {
        $definition = $this->studioQueryDefinition($dataset);
        if($definition === FALSE) {
            return $this->externalStudioQuery($dataset, $dimension, $measure, $dateRange, $environment, $limit);
        }
        if(!isset($definition['measures'][$measure])) {
            return FALSE;
        }

        $dimensionExpression = '';
        if($dimension !== '') {
            if(!isset($definition['dimensions'][$dimension])) {
                return FALSE;
            }
            $dimensionExpression = $definition['dimensions'][$dimension];
            $this->db->select($dimensionExpression.' AS label', FALSE);
        } else {
            $this->db->select('"Total" AS label', FALSE);
        }

        $measureExpression = $definition['measures'][$measure];
        $this->db->select($measureExpression.' AS value', FALSE);
        $this->db->from($definition['from']);

        if(isset($definition['join'])) {
            $this->db->join($definition['join'][0], $definition['join'][1], $definition['join'][2]);
        }
        if(isset($definition['joins'])) {
            foreach($definition['joins'] as $join) {
                $this->db->join($join[0], $join[1], $join[2]);
            }
        }

        $rangeDays = array('7d' => 7, '30d' => 30, '90d' => 90, '180d' => 180);
        if(isset($rangeDays[$dateRange])) {
            $this->db->where($definition['time'].' >= DATE_SUB(NOW(), INTERVAL '.(int) $rangeDays[$dateRange].' DAY)', NULL, FALSE);
        }

        $this->applyStudioEnvironmentFilter($definition['environment'], $environment);

        if($dimensionExpression !== '') {
            $this->db->group_by($dimensionExpression, FALSE);
            if(in_array($dimension, array('activity_day', 'activity_month', 'event_day', 'created_month'), TRUE)) {
                $this->db->order_by('label', 'ASC');
            } else {
                $this->db->order_by('value', 'DESC');
            }
            $this->db->limit(max(1, min(50, (int) $limit)));
        }

        return $this->db->get()->result_array();
    }

    function lastDataSourceError()
    {
        return $this->lastDataSourceError;
    }

    function ensureDataSourceSchema()
    {
        if($this->dataSourceSchemaReady) {
            return;
        }

        $this->db->query('CREATE TABLE IF NOT EXISTS `visualization_connections` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
            `driver` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
            `host` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `port` int(11) NOT NULL,
            `database_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `username` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `password_encrypted` text COLLATE utf8_unicode_ci NOT NULL,
            `ssl_mode` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT \'required\',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `owner_id` int(11) NOT NULL,
            `owner` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `visualization_connections_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');
        $this->db->query('CREATE TABLE IF NOT EXISTS `visualization_datasets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `connection_id` int(11) NOT NULL,
            `name` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
            `dataset_key` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
            `description` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT \'\',
            `table_schema` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `table_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `dimensions_json` mediumtext COLLATE utf8_unicode_ci NOT NULL,
            `measures_json` mediumtext COLLATE utf8_unicode_ci NOT NULL,
            `time_column` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT \'\',
            `environment_column` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT \'\',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `owner_id` int(11) NOT NULL,
            `owner` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `visualization_datasets_key` (`dataset_key`),
            KEY `visualization_datasets_connection` (`connection_id`),
            KEY `visualization_datasets_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');
        $this->dataSourceSchemaReady = TRUE;
    }

    private function externalDatasetsMetadata()
    {
        $this->ensureDataSourceSchema();
        $this->db->select('dataset.dataset_key,dataset.name,dataset.description,dataset.dimensions_json,dataset.measures_json,connection.name AS connection_name');
        $this->db->from('visualization_datasets dataset');
        $this->db->join('visualization_connections connection', 'connection.id = dataset.connection_id');
        $this->db->where('dataset.is_active', 1);
        $this->db->where('connection.is_active', 1);
        $this->db->order_by('dataset.name', 'ASC');
        $rows = $this->db->get()->result_array();
        $datasets = array();

        foreach($rows as $row) {
            $dimensions = json_decode($row['dimensions_json'], TRUE);
            $measures = json_decode($row['measures_json'], TRUE);
            if(!is_array($dimensions) || !is_array($measures) || empty($measures)) {
                continue;
            }
            $publicDimensions = array();
            foreach($dimensions as $field) {
                $publicDimensions[] = array(
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => isset($field['type']) ? $field['type'] : 'category'
                );
            }
            $publicMeasures = array();
            foreach($measures as $field) {
                $publicMeasures[] = array(
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'format' => isset($field['format']) ? $field['format'] : 'number'
                );
            }
            $datasets[] = array(
                'key' => $row['dataset_key'],
                'name' => $row['name'],
                'description' => $row['description'],
                'icon' => 'fa-plug',
                'freshness' => 'Connected',
                'connection_name' => $row['connection_name'],
                'default_dimension' => !empty($publicDimensions) ? $publicDimensions[0]['key'] : '',
                'default_measure' => $publicMeasures[0]['key'],
                'dimensions' => $publicDimensions,
                'measures' => $publicMeasures
            );
        }

        return $datasets;
    }

    function listConnections()
    {
        $this->ensureDataSourceSchema();
        $this->db->select('connection.id,connection.name,connection.driver,connection.host,connection.port,connection.database_name,connection.username,connection.ssl_mode,connection.is_active,connection.owner,connection.created_at,connection.updated_at,COUNT(dataset.id) AS dataset_count');
        $this->db->from('visualization_connections connection');
        $this->db->join('visualization_datasets dataset', 'dataset.connection_id = connection.id', 'left');
        $this->db->group_by('connection.id');
        $this->db->order_by('connection.name', 'ASC');
        return $this->db->get()->result();
    }

    function getConnection($id, $withSecret = FALSE)
    {
        $this->ensureDataSourceSchema();
        $fields = 'id,name,driver,host,port,database_name,username,ssl_mode,is_active,owner_id,owner,created_at,updated_at';
        if($withSecret) {
            $fields .= ',password_encrypted';
        }
        $this->db->select($fields);
        $this->db->from('visualization_connections');
        $this->db->where('id', (int) $id);
        return $this->db->get()->row_array();
    }

    function saveConnection($id, $data)
    {
        $this->ensureDataSourceSchema();
        if((int) $id > 0) {
            $this->db->where('id', (int) $id);
            $this->db->update('visualization_connections', $data);
            return (int) $id;
        }
        $this->db->insert('visualization_connections', $data);
        return (int) $this->db->insert_id();
    }

    function deleteConnection($id)
    {
        $this->ensureDataSourceSchema();
        $this->db->trans_start();
        $this->db->where('connection_id', (int) $id)->delete('visualization_datasets');
        $this->db->where('id', (int) $id)->delete('visualization_connections');
        $deleted = $this->db->affected_rows();
        $this->db->trans_complete();
        return $deleted;
    }

    function listExternalDatasets()
    {
        $this->ensureDataSourceSchema();
        $this->db->select('dataset.id,dataset.connection_id,dataset.name,dataset.dataset_key,dataset.description,dataset.table_schema,dataset.table_name,dataset.time_column,dataset.environment_column,dataset.is_active,dataset.owner,dataset.created_at,dataset.updated_at,connection.name AS connection_name,connection.driver');
        $this->db->from('visualization_datasets dataset');
        $this->db->join('visualization_connections connection', 'connection.id = dataset.connection_id');
        $this->db->order_by('dataset.name', 'ASC');
        return $this->db->get()->result();
    }

    function saveExternalDataset($id, $data)
    {
        $this->ensureDataSourceSchema();
        if((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('visualization_datasets', $data);
            return (int) $id;
        }
        $this->db->insert('visualization_datasets', $data);
        return (int) $this->db->insert_id();
    }

    function deleteExternalDataset($id)
    {
        $this->ensureDataSourceSchema();
        $this->db->where('id', (int) $id)->delete('visualization_datasets');
        return $this->db->affected_rows();
    }

    function testExternalConnection($connection, $password)
    {
        try {
            $pdo = $this->externalPdo($connection, $password);
            $pdo->query('SELECT 1')->fetchColumn();
            return array('status' => TRUE, 'message' => 'Connection verified.');
        } catch(Exception $exception) {
            return array('status' => FALSE, 'message' => $this->safeConnectionError($exception));
        }
    }

    function discoverExternalTables($connectionId)
    {
        list($connection, $pdo) = $this->openStoredConnection($connectionId);
        if(!$connection || !$pdo) {
            return FALSE;
        }
        try {
            if($connection['driver'] === 'pgsql') {
                $statement = $pdo->prepare("SELECT table_schema, table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_catalog = ? AND table_schema NOT IN ('pg_catalog','information_schema') ORDER BY table_schema, table_name");
            } else {
                $statement = $pdo->prepare("SELECT table_schema, table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_schema = ? ORDER BY table_name");
            }
            $statement->execute(array($connection['database_name']));
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $exception) {
            $this->lastDataSourceError = $this->safeConnectionError($exception);
            return FALSE;
        }
    }

    function discoverExternalColumns($connectionId, $schema, $table)
    {
        list($connection, $pdo) = $this->openStoredConnection($connectionId);
        if(!$connection || !$pdo) {
            return FALSE;
        }
        try {
            $statement = $pdo->prepare('SELECT column_name, data_type, ordinal_position FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position');
            $statement->execute(array($schema, $table));
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $exception) {
            $this->lastDataSourceError = $this->safeConnectionError($exception);
            return FALSE;
        }
    }

    private function externalStudioQuery($datasetKey, $dimensionKey, $measureKey, $dateRange, $environment, $limit)
    {
        $this->ensureDataSourceSchema();
        if(!preg_match('/^source_[a-f0-9]{16}$/', (string) $datasetKey)) {
            return FALSE;
        }
        $this->db->select('dataset.*,connection.driver,connection.host,connection.port,connection.database_name,connection.username,connection.password_encrypted,connection.ssl_mode');
        $this->db->from('visualization_datasets dataset');
        $this->db->join('visualization_connections connection', 'connection.id = dataset.connection_id');
        $this->db->where('dataset.dataset_key', $datasetKey);
        $this->db->where('dataset.is_active', 1);
        $this->db->where('connection.is_active', 1);
        $record = $this->db->get()->row_array();
        if(!$record) {
            return FALSE;
        }

        $dimensions = json_decode($record['dimensions_json'], TRUE);
        $measures = json_decode($record['measures_json'], TRUE);
        $dimension = $this->fieldDefinition($dimensions, $dimensionKey);
        $measure = $this->fieldDefinition($measures, $measureKey);
        if(!$measure || ($dimensionKey !== '' && !$dimension)) {
            return FALSE;
        }

        try {
            $password = $this->decryptConnectionPassword($record['password_encrypted']);
            if($password === FALSE) {
                throw new RuntimeException('The stored credential could not be decrypted.');
            }
            $pdo = $this->externalPdo($record, $password);
            $driver = $record['driver'];
            $table = $this->quoteIdentifier($record['table_schema'], $driver).'.'.$this->quoteIdentifier($record['table_name'], $driver);
            $params = array();
            $labelExpression = $dimension ? $this->externalDimensionExpression($dimension, $driver) : "'Total'";
            $valueExpression = $this->externalMeasureExpression($measure, $driver);
            $sql = 'SELECT '.$labelExpression.' AS label, '.$valueExpression.' AS value FROM '.$table.' WHERE 1=1';

            $rangeDays = array('7d' => 7, '30d' => 30, '90d' => 90, '180d' => 180);
            if(isset($rangeDays[$dateRange]) && $record['time_column'] !== '') {
                $sql .= ' AND '.$this->quoteIdentifier($record['time_column'], $driver).' >= ?';
                $params[] = date('Y-m-d H:i:s', time() - ($rangeDays[$dateRange] * 86400));
            }
            $environment = strtoupper(trim((string) $environment));
            if($record['environment_column'] !== '' && $environment !== '' && $environment !== '*' && $environment !== 'ALL') {
                $environmentColumn = $this->externalTextExpression($record['environment_column'], $driver);
                if($environment === '__UNKNOWN__' || $environment === 'UNKNOWN') {
                    $sql .= ' AND ('.$environmentColumn." IS NULL OR TRIM(".$environmentColumn.") = '')";
                } else {
                    $aliases = array('QA' => array('QA', 'QAS'), 'QAS' => array('QA', 'QAS'), 'PROD' => array('PROD', 'PRD', 'PRODUCTION'), 'PRD' => array('PROD', 'PRD', 'PRODUCTION'));
                    $values = isset($aliases[$environment]) ? $aliases[$environment] : array($environment);
                    $sql .= ' AND UPPER(TRIM('.$environmentColumn.')) IN ('.implode(',', array_fill(0, count($values), '?')).')';
                    $params = array_merge($params, $values);
                }
            }
            if($dimension) {
                $sql .= ' GROUP BY '.$labelExpression.' ORDER BY '.($dimension['type'] === 'time' ? 'label ASC' : 'value DESC').' LIMIT '.max(1, min(50, (int) $limit));
            }

            if($driver === 'pgsql') {
                $pdo->beginTransaction();
                $pdo->exec('SET TRANSACTION READ ONLY');
                $pdo->exec("SET LOCAL statement_timeout = '5000ms'");
            } else {
                // MySQL/MariaDB apply SET TRANSACTION to the next transaction,
                // so the read-only characteristic must be set before BEGIN.
                $pdo->exec('SET TRANSACTION READ ONLY');
                $pdo->beginTransaction();
            }
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $pdo->rollBack();
            return $rows;
        } catch(Exception $exception) {
            if(isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->lastDataSourceError = $this->safeConnectionError($exception);
            log_message('error', 'Insight Studio connected dataset query failed: '.$exception->getMessage());
            return FALSE;
        }
    }

    private function fieldDefinition($fields, $key)
    {
        if($key === '' || !is_array($fields)) {
            return FALSE;
        }
        foreach($fields as $field) {
            if(isset($field['key']) && hash_equals((string) $field['key'], (string) $key)) {
                return $field;
            }
        }
        return FALSE;
    }

    private function externalDimensionExpression($field, $driver)
    {
        $source = $this->quoteIdentifier($field['source'], $driver);
        if(isset($field['bucket']) && $field['bucket'] === 'month') {
            return $driver === 'pgsql' ? "TO_CHAR(".$source.", 'YYYY-MM')" : "DATE_FORMAT(".$source.", '%Y-%m')";
        }
        if(isset($field['bucket']) && $field['bucket'] === 'day') {
            return $driver === 'pgsql' ? 'CAST('.$source.' AS DATE)' : 'DATE('.$source.')';
        }
        $text = $this->externalTextExpression($field['source'], $driver);
        return "COALESCE(NULLIF(TRIM(".$text."), ''), 'Unknown')";
    }

    private function externalMeasureExpression($field, $driver)
    {
        $aggregation = isset($field['aggregation']) ? $field['aggregation'] : 'count';
        if($aggregation === 'count') {
            return 'COUNT(*)';
        }
        $source = $this->quoteIdentifier($field['source'], $driver);
        if($aggregation === 'sum') {
            return 'SUM(COALESCE('.$source.', 0))';
        }
        if($aggregation === 'avg') {
            return 'AVG('.$source.')';
        }
        throw new RuntimeException('Unsupported connected measure.');
    }

    private function externalTextExpression($column, $driver)
    {
        $source = $this->quoteIdentifier($column, $driver);
        return $driver === 'pgsql' ? 'CAST('.$source.' AS TEXT)' : 'CAST('.$source.' AS CHAR)';
    }

    private function quoteIdentifier($identifier, $driver)
    {
        if(!preg_match('/^[^\x00-\x1F\x7F]{1,128}$/u', (string) $identifier)) {
            throw new RuntimeException('Invalid database identifier.');
        }
        return $driver === 'pgsql' ? '"'.str_replace('"', '""', $identifier).'"' : '`'.str_replace('`', '``', $identifier).'`';
    }

    private function openStoredConnection($connectionId)
    {
        $connection = $this->getConnection($connectionId, TRUE);
        if(!$connection || !(int) $connection['is_active']) {
            $this->lastDataSourceError = 'Connection not found or inactive.';
            return array(FALSE, FALSE);
        }
        $password = $this->decryptConnectionPassword($connection['password_encrypted']);
        if($password === FALSE) {
            $this->lastDataSourceError = 'The stored credential could not be decrypted.';
            return array(FALSE, FALSE);
        }
        try {
            return array($connection, $this->externalPdo($connection, $password));
        } catch(Exception $exception) {
            $this->lastDataSourceError = $this->safeConnectionError($exception);
            return array(FALSE, FALSE);
        }
    }

    private function decryptConnectionPassword($encrypted)
    {
        $this->load->library('encryption');
        return $this->encryption->decrypt((string) $encrypted);
    }

    private function externalPdo($connection, $password)
    {
        $driver = isset($connection['driver']) ? $connection['driver'] : '';
        if(!in_array($driver, array('mysql', 'pgsql'), TRUE)) {
            throw new RuntimeException('Unsupported database driver.');
        }
        if(!extension_loaded('pdo_'.$driver)) {
            throw new RuntimeException(strtoupper($driver).' support is not installed on this server.');
        }

        $host = (string) $connection['host'];
        $port = (int) $connection['port'];
        $database = (string) $connection['database_name'];
        $sslMode = isset($connection['ssl_mode']) ? $connection['ssl_mode'] : 'required';
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => FALSE,
            PDO::ATTR_PERSISTENT => FALSE
        );
        if($driver === 'pgsql') {
            $dsn = 'pgsql:host='.$host.';port='.$port.';dbname='.$database.';sslmode='.($sslMode === 'disabled' ? 'disable' : ($sslMode === 'preferred' ? 'prefer' : 'require'));
        } else {
            $dsn = 'mysql:host='.$host.';port='.$port.';dbname='.$database.';charset=utf8mb4';
            if($sslMode === 'required') {
                if(defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = TRUE;
                }
                if(defined('PDO::MYSQL_ATTR_SSL_CA') && is_readable('/etc/ssl/certs/ca-certificates.crt')) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
                }
            }
        }
        return new PDO($dsn, (string) $connection['username'], (string) $password, $options);
    }

    private function safeConnectionError($exception)
    {
        $message = $exception->getMessage();
        if(stripos($message, 'not installed') !== FALSE || stripos($message, 'could not find driver') !== FALSE) {
            return 'This database driver is not installed on the Jobseeker server.';
        }
        if(stripos($message, 'decrypt') !== FALSE) {
            return 'The stored credential could not be decrypted. Save the connection again.';
        }
        return 'Could not connect with these settings. Check the host, network access, TLS mode, database, and read-only credentials.';
    }

    private function applyStudioEnvironmentFilter($column, $environment)
    {
        $environment = strtoupper(trim((string) $environment));
        if($column === '' || $environment === '' || $environment === '*' || $environment === 'ALL') {
            return;
        }

        if($environment === '__UNKNOWN__' || $environment === 'UNKNOWN') {
            $this->db->group_start();
            $this->db->where($column.' IS NULL', NULL, FALSE);
            $this->db->or_where('TRIM('.$column.') =', '');
            $this->db->group_end();
            return;
        }

        $aliases = array(
            'QA' => array('QA', 'QAS'),
            'QAS' => array('QA', 'QAS'),
            'PROD' => array('PROD', 'PRD', 'PRODUCTION'),
            'PRD' => array('PROD', 'PRD', 'PRODUCTION'),
            'HML' => array('HML', 'HOMOLOG', 'HOMOLOGATION')
        );
        $values = isset($aliases[$environment]) ? $aliases[$environment] : array($environment);
        $this->db->group_start();
        foreach($values as $index => $value) {
            if($index === 0) {
                $this->db->where('UPPER(TRIM('.$column.')) =', $value);
            } else {
                $this->db->or_where('UPPER(TRIM('.$column.')) =', $value);
            }
        }
        $this->db->group_end();
    }

    function ensureDashboardSchema()
    {
        if($this->dashboardSchemaReady) {
            return;
        }

        $this->db->query('CREATE TABLE IF NOT EXISTS `visualization_dashboards` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
            `description` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT \'\',
            `owner_id` int(11) NOT NULL,
            `owner` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `definition_json` mediumtext COLLATE utf8_unicode_ci NOT NULL,
            `is_shared` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `visualization_dashboards_owner` (`owner_id`),
            KEY `visualization_dashboards_shared` (`is_shared`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');
        $this->dashboardSchemaReady = TRUE;
    }

    function listDashboards($userId)
    {
        $this->ensureDashboardSchema();
        $this->db->select('id,name,description,owner_id,owner,is_shared,created_at,updated_at');
        $this->db->from('visualization_dashboards');
        $this->db->group_start();
        $this->db->where('owner_id', (int) $userId);
        $this->db->or_where('is_shared', 1);
        $this->db->group_end();
        $this->db->order_by('updated_at', 'DESC');
        return $this->db->get()->result();
    }

    function getDashboard($id, $userId)
    {
        $this->ensureDashboardSchema();
        $this->db->from('visualization_dashboards');
        $this->db->where('id', (int) $id);
        $this->db->group_start();
        $this->db->where('owner_id', (int) $userId);
        $this->db->or_where('is_shared', 1);
        $this->db->group_end();
        return $this->db->get()->row();
    }

    function saveDashboard($id, $userId, $owner, $name, $description, $definition, $isShared)
    {
        $this->ensureDashboardSchema();
        $now = date('Y-m-d H:i:s');
        $record = array(
            'name' => $name,
            'description' => $description,
            'definition_json' => $definition,
            'is_shared' => $isShared ? 1 : 0,
            'updated_at' => $now
        );

        if((int) $id > 0) {
            $this->db->where('id', (int) $id);
            $this->db->where('owner_id', (int) $userId);
            $this->db->update('visualization_dashboards', $record);
            return $this->db->affected_rows() >= 0 ? (int) $id : FALSE;
        }

        $record['owner_id'] = (int) $userId;
        $record['owner'] = $owner;
        $record['created_at'] = $now;
        $this->db->insert('visualization_dashboards', $record);
        return (int) $this->db->insert_id();
    }

    function deleteDashboard($id, $userId, $canDeleteAny = FALSE)
    {
        $this->ensureDashboardSchema();
        $this->db->where('id', (int) $id);
        if(!$canDeleteAny) {
            $this->db->where('owner_id', (int) $userId);
        }
        $this->db->delete('visualization_dashboards');
        return $this->db->affected_rows();
    }

    function list() {

        $this->db->select('*');
        $this->db->from('reports');
        $query = $this->db->get();
        return $query->result();
    }

    function fetch($id) {

        $this->db->select('*');
        $this->db->from('reports');
        $this->db->where('id', $id);
        $query = $this->db->get();
        return $query->result();
    }

    function view($name) {

        $this->db->select('name,type,code');
        $this->db->from('reports');
        $this->db->where('name', $name);
        $query = $this->db->get();
        return $query->result();
    }

     function listReports() {

        $this->db->distinct();
        $this->db->select('name');
        $this->db->from('reports');
        $query = $this->db->get();
        return $query->num_rows();
    }

    function listTypes() {

        $this->db->distinct();
        $this->db->select('type');
        $this->db->from('reports');
        $query = $this->db->get();
        return $query->num_rows();
    }

    function listUsers() {
        
        $this->db->distinct();
        $this->db->select('name');
        $this->db->from('tbl_users');
        $query = $this->db->get();
        return $query->result();
    }

    function listGroups() {
        
        $this->db->distinct();
        $this->db->select('name');
        $this->db->from('tbl_groups');
        $query = $this->db->get();
        return $query->result();
    }

     // Validate if the record already exists.
     function validate($name) {

        $this->db->select('*');
        $this->db->from('reports');
        $this->db->where('name', $name);
        $query = $this->db->get();
        return $query->num_rows();
    }

      // Validate if the record already exists.
     function permission($name,$user) {

                $query = $this->db->query("SELECT r.name AS report, r.type
                    FROM reports r
                    LEFT JOIN tbl_users u ON u.name = ? AND u.isDeleted = 0
                    LEFT JOIN tbl_groups g ON g.id = u.groupId
                    WHERE r.name = ? AND (
                        FIND_IN_SET('*', REPLACE(r.users, ' ', '')) > 0
                        OR FIND_IN_SET(?, REPLACE(r.users, ' ', '')) > 0
                        OR FIND_IN_SET(g.name, REPLACE(r.groups, ' ', '')) > 0
                    )", array($user, $name, $user));
        return $query->num_rows();
    }


    function allowedUser($user) {

                $query = $this->db->query("SELECT DISTINCT r.name AS report, r.type
                    FROM reports r
                    LEFT JOIN tbl_users u ON u.name = ? AND u.isDeleted = 0
                    LEFT JOIN tbl_groups g ON g.id = u.groupId
                    WHERE FIND_IN_SET('*', REPLACE(r.users, ' ', '')) > 0
                        OR FIND_IN_SET(?, REPLACE(r.users, ' ', '')) > 0
                        OR FIND_IN_SET(g.name, REPLACE(r.groups, ' ', '')) > 0
                    ORDER BY r.name", array($user, $user));
        return $query->result();
    }

    function allowedGroup($user) {

        $this->db->select('name,users');
        $this->db->from('reports');
        $this->db->like('name', $user);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Insert record to DB.
    function insert($Info)
    {
        $this->db->trans_start();
        $this->db->insert('reports', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    // Delete record from db
    function delete($id)
    {
        
        $this->db->where('id', $id);
		$this->db->delete('reports');

        
        return $this->db->affected_rows();
    }

    // Fetch data to input edit
    function EditSettingsFetchData($id = 0)
    {
        $this->db->where('id',$id);
        $sql = $this->db->get('database_settings');
        return $sql->row();
    }

    function updateDbSetting($Info, $id)
    {
        $this->db->where('id', $id);
        $this->db->update('database_settings', $Info);
        
        return TRUE;
    }

    
}

?>
