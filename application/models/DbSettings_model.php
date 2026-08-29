<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

class DbSettings_model extends CI_Model
{
    private function environmentFilterValues($environment)
    {
        $environment = strtoupper(trim((string) $environment));
        $aliases = array(
            'QA' => array('QA', 'QAS'), 'QAS' => array('QA', 'QAS'),
            'PROD' => array('PROD', 'PRD', 'PRODUCTION'), 'PRD' => array('PROD', 'PRD', 'PRODUCTION'), 'PRODUCTION' => array('PROD', 'PRD', 'PRODUCTION'),
            'HML' => array('HML', 'HOMOLOG', 'HOMOLOGATION'), 'HOMOLOG' => array('HML', 'HOMOLOG', 'HOMOLOGATION'), 'HOMOLOGATION' => array('HML', 'HOMOLOG', 'HOMOLOGATION')
        );
        return isset($aliases[$environment]) ? $aliases[$environment] : array($environment);
    }
    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
        $this->migrateLegacySettings();
        $this->ensureIndexes();
    }

    private function ensureSchema()
    {
        $columns = array(
            'connector_key' => "varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `id`",
            'environment' => "varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL' AFTER `job_name`",
            'auth_type' => "varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'username_password' AFTER `db_type`",
            'secret_backend' => "varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'local' AFTER `description`",
            'secret_reference' => "longtext COLLATE utf8_unicode_ci DEFAULT NULL AFTER `secret_backend`",
            'secret_encrypted' => "longtext COLLATE utf8_unicode_ci DEFAULT NULL AFTER `secret_reference`",
            'is_active' => "tinyint(1) NOT NULL DEFAULT 1 AFTER `secret_encrypted`",
            'updated_at' => "datetime DEFAULT NULL AFTER `creation_date`"
        );

        foreach ($columns as $name => $definition) {
            if (! $this->db->field_exists($name, 'database_settings')) {
                $this->db->query('ALTER TABLE `database_settings` ADD `'.$name.'` '.$definition);
            }
        }

        $lengths = array(
            'address' => 255,
            'schema' => 200,
            'description' => 2000,
            'additional_parameters' => 1000,
            'oracle_ServiceName' => 200,
            'oracle_sid' => 200
        );
        foreach ($lengths as $name => $length) {
            $column = $this->db->query(
                'SELECT CHARACTER_MAXIMUM_LENGTH AS max_length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
                array('database_settings', $name)
            )->row();
            if ($column && (int) $column->max_length < $length) {
                $this->db->query('ALTER TABLE `database_settings` MODIFY `'.$name.'` varchar('.$length.') COLLATE utf8_unicode_ci NOT NULL');
            }
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `connector_access_log` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `connector_id` int(11) DEFAULT NULL,
            `connector_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `secret_backend` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
            `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
            `accessed_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `connector_accessed_at` (`accessed_at`),
            KEY `connector_access_scope` (`connector_key`,`environment`,`job_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    private function connectorKey($value, $fallback)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        return substr($value === '' ? $fallback : $value, 0, 128);
    }

    private function migrateLegacySettings()
    {
        $rows = $this->db
            ->group_start()
                ->where('connector_key IS NULL', NULL, FALSE)
                ->or_where('connector_key', '')
                ->or_where('login !=', '')
                ->or_where('password !=', '')
            ->group_end()
            ->get('database_settings')
            ->result_array();
        if (empty($rows)) {
            return;
        }
        $this->load->library('encryption');

        foreach ($rows as $row) {
            $updates = array();
            if (empty($row['connector_key'])) {
                $connectorKey = $this->connectorKey(
                    (isset($row['job_name']) ? $row['job_name'] : '').'-'.(isset($row['db_type']) ? $row['db_type'] : ''),
                    'connector-'.(int) $row['id']
                );
                $environment = isset($row['environment']) && $row['environment'] !== '' ? $row['environment'] : 'ALL';
                $jobName = isset($row['job_name']) && $row['job_name'] !== '' ? $row['job_name'] : '*';
                if ($this->scopeExists($connectorKey, $environment, $jobName, (int) $row['id'])) {
                    $connectorKey = substr($connectorKey, 0, 116).'-'.(int) $row['id'];
                }
                $updates['connector_key'] = $connectorKey;
            }

            $hasEncryptedSecret = ! empty($row['secret_encrypted']);
            $legacyPassword = isset($row['password']) ? (string) $row['password'] : '';
            if (! $hasEncryptedSecret && $legacyPassword !== '') {
                $secret = $this->encryptLocalSecret(isset($row['login']) ? $row['login'] : '', $legacyPassword);
                if ($secret !== FALSE) {
                    $updates['secret_encrypted'] = $secret;
                    $updates['login'] = '';
                    $updates['password'] = '';
                }
            }

            if (! empty($updates)) {
                $updates['updated_at'] = date('Y-m-d H:i:s');
                $this->db->where('id', (int) $row['id'])->update('database_settings', $updates);
            }
        }
    }

    private function ensureIndexes()
    {
        $column = $this->db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='database_settings' AND COLUMN_NAME='connector_key'")->row();
        if ($column && $column->IS_NULLABLE === 'YES') {
            $this->db->query('ALTER TABLE `database_settings` MODIFY `connector_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL');
        }
        $index = $this->db->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='database_settings' AND INDEX_NAME='database_settings_scope' LIMIT 1")->row();
        if (! $index) {
            $this->db->query('ALTER TABLE `database_settings` ADD UNIQUE KEY `database_settings_scope` (`connector_key`,`environment`,`job_name`)');
        }
    }

    public function listSettings($environment = 'ALL')
    {
        $this->db
            ->select('id,connector_key,job_name,environment,db_type,auth_type,address,port,`schema`,description,secret_backend,secret_reference,is_active,creation_date,updated_at,owner,additional_parameters,oracle_ServiceName,oracle_sid')
            ->from('database_settings');
        if ($environment !== 'ALL') {
            $this->db->where_in('environment', array_merge($this->environmentFilterValues($environment), array('ALL')));
        }
        return $this->db
            ->order_by('is_active', 'DESC')
            ->order_by('connector_key', 'ASC')
            ->order_by('environment', 'ASC')
            ->order_by('job_name', 'ASC')
            ->get()
            ->result();
    }

    public function getSetting($id, $includeSecret = FALSE)
    {
        if (! $includeSecret) {
            $this->db->select('id,connector_key,job_name,environment,db_type,auth_type,address,port,`schema`,description,secret_backend,secret_reference,is_active,creation_date,updated_at,owner,additional_parameters,oracle_ServiceName,oracle_sid');
        }
        return $this->db->where('id', (int) $id)->get('database_settings')->row();
    }

    public function scopeExists($connectorKey, $environment, $jobName, $excludeId = 0)
    {
        $this->db->from('database_settings')
            ->where('connector_key', $connectorKey)
            ->where('environment', $environment)
            ->where('job_name', $jobName);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveSetting($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('database_settings', $data);
            return $this->db->affected_rows() >= 0 ? (int) $id : 0;
        }

        $this->db->insert('database_settings', $data);
        return (int) $this->db->insert_id();
    }

    public function encryptLocalSecret($username, $password)
    {
        return $this->encryptSecretValues(array('username' => (string) $username, 'password' => (string) $password));
    }

    public function encryptSecretValues($values)
    {
        $this->load->library('encryption');
        $payload = json_encode($values);
        return $payload === FALSE ? FALSE : $this->encryption->encrypt($payload);
    }

    public function decryptLocalSecret($encrypted)
    {
        if (trim((string) $encrypted) === '') {
            return FALSE;
        }
        $this->load->library('encryption');
        $payload = $this->encryption->decrypt((string) $encrypted);
        if ($payload === FALSE) {
            return FALSE;
        }
        $secret = json_decode($payload, TRUE);
        return is_array($secret) ? $secret : FALSE;
    }

    public function runtimeSettings($environment, $jobName)
    {
        $rows = $this->db
            ->from('database_settings')
            ->where('is_active', 1)
            ->where_in('environment', array($environment, 'ALL'))
            ->where_in('job_name', array($jobName, '*'))
            ->get()
            ->result_array();
        $selected = array();

        foreach ($rows as $row) {
            $score = ($row['environment'] === $environment ? 20 : 10) + ($row['job_name'] === $jobName ? 2 : 1);
            $key = (string) $row['connector_key'];
            if ($key !== '' && (! isset($selected[$key]) || $score > $selected[$key]['score'])) {
                $selected[$key] = array('score' => $score, 'row' => $row);
            }
        }

        ksort($selected);
        return array_map(function($item) { return $item['row']; }, array_values($selected));
    }

    public function logRuntimeAccess($row, $environment, $jobName, $status)
    {
        $this->db->insert('connector_access_log', array(
            'connector_id' => isset($row['id']) ? (int) $row['id'] : NULL,
            'connector_key' => isset($row['connector_key']) ? (string) $row['connector_key'] : '',
            'environment' => (string) $environment,
            'job_name' => (string) $jobName,
            'secret_backend' => isset($row['secret_backend']) ? (string) $row['secret_backend'] : '',
            'status' => (string) $status,
            'accessed_at' => date('Y-m-d H:i:s')
        ));
    }

    public function pruneRuntimeAccessLogs()
    {
        $this->db->where('accessed_at <', date('Y-m-d H:i:s', time() - 7776000))->delete('connector_access_log');
    }

    public function deleteSetting($id)
    {
        $this->db->where('id', (int) $id)->delete('database_settings');
        return $this->db->affected_rows();
    }

    public function validateSetting($jobName, $dbType, $login, $address, $port, $schema)
    {
        return $this->db->from('database_settings')
            ->where('job_name', $jobName)
            ->where('db_type', $dbType)
            ->where('address', $address)
            ->where('port', $port)
            ->where('schema', $schema)
            ->count_all_results();
    }

    public function insertDbSetting($info)
    {
        return $this->saveSetting($info);
    }

    public function EditSettingsFetchData($id = 0)
    {
        return $this->getSetting($id, TRUE);
    }

    public function updateDbSetting($info, $id)
    {
        return $this->saveSetting($info, $id) > 0;
    }
}

?>
