<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

class DataAssets_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
        $this->migrateLegacyAssets();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `data_assets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `asset_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `direction` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'input',
            `format` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'csv',
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '*',
            `storage_path` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
            `file_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `options_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `is_required` tinyint(1) NOT NULL DEFAULT 1,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `version` int(11) NOT NULL DEFAULT 0,
            `file_size` bigint(20) unsigned DEFAULT NULL,
            `checksum` char(64) COLLATE utf8_unicode_ci DEFAULT NULL,
            `uploaded_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `legacy_source` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
            `legacy_id` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `data_assets_scope` (`asset_key`,`environment`,`job_name`),
            UNIQUE KEY `data_assets_legacy` (`legacy_source`,`legacy_id`),
            KEY `data_assets_environment` (`environment`),
            KEY `data_assets_direction` (`direction`),
            KEY `data_assets_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS `data_asset_migrations` (
            `migration_key` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `applied_at` datetime NOT NULL,
            PRIMARY KEY (`migration_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    private function slug($value, $fallback)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        return $value === '' ? $fallback : substr($value, 0, 128);
    }

    private function legacyStoragePath($path, $direction, $filePath, $fileName, $format)
    {
        $path = trim(str_replace('\\', '/', (string) $path));
        $path = preg_replace('#^/?repository/#i', '', $path);
        if ($path !== '') {
            return ltrim($path, '/');
        }

        $extension = $format === 'binary' ? '' : '.'.$format;
        return 'talend/'.$direction.'/'.trim((string) $filePath, '/').'/'.trim((string) $fileName).$extension;
    }

    private function migrateLegacyAssets()
    {
        $migrationKey = 'legacy_components_v1';
        if ($this->db->where('migration_key', $migrationKey)->count_all_results('data_asset_migrations') > 0) {
            return;
        }

        $sources = array(
            array('table' => 'job_info', 'source' => 'job_info', 'direction' => 'input'),
            array('table' => 'job_output', 'source' => 'job_output', 'direction' => 'output')
        );

        foreach ($sources as $source) {
            if (! $this->db->table_exists($source['table'])) {
                continue;
            }

            $rows = $this->db->get($source['table'])->result_array();
            foreach ($rows as $row) {
                $legacyId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($legacyId <= 0) {
                    continue;
                }

                $format = strtolower(ltrim(trim(isset($row['component_type']) ? $row['component_type'] : ''), '.'));
                if (! in_array($format, array('csv', 'json', 'jsonl', 'xml', 'xlsx', 'xls', 'parquet', 'txt'), TRUE)) {
                    $format = 'binary';
                }

                $jobName = trim(isset($row['job_name']) ? $row['job_name'] : '');
                $component = trim(isset($row['job_component']) ? $row['job_component'] : '');
                $fileName = trim(isset($row['file_name']) ? $row['file_name'] : '');
                if ($fileName === '') {
                    $fileName = $this->slug($component, 'asset-'.$legacyId).($format === 'binary' ? '' : '.'.$format);
                } else if (pathinfo($fileName, PATHINFO_EXTENSION) === '' && $format !== 'binary') {
                    $fileName .= '.'.$format;
                }

                $assetKey = $this->slug($jobName.'-'.$component, 'legacy-'.$source['direction'].'-'.$legacyId);
                $storagePath = $this->legacyStoragePath(
                    isset($row['path']) ? $row['path'] : '',
                    $source['direction'],
                    isset($row['file_path']) ? $row['file_path'] : '',
                    $fileName,
                    $format
                );
                $createdAt = ! empty($row['creation_date']) ? $row['creation_date'] : date('Y-m-d H:i:s');
                $options = array('legacy_component' => $component, 'legacy_repository' => isset($row['file_path']) ? $row['file_path'] : '');

                $this->db->query(
                    "INSERT IGNORE INTO `data_assets`
                    (`asset_key`,`name`,`direction`,`format`,`environment`,`job_name`,`storage_path`,`file_name`,`options_json`,`description`,`is_required`,`is_active`,`version`,`created_at`,`updated_at`,`owner`,`legacy_source`,`legacy_id`)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    array(
                        $assetKey,
                        $component === '' ? $assetKey : $component,
                        $source['direction'],
                        $format,
                        'ALL',
                        $jobName === '' ? '*' : $jobName,
                        $storagePath,
                        $fileName,
                        json_encode($options),
                        'Imported from the legacy '.$source['source'].' registry.',
                        $source['direction'] === 'input' ? 1 : 0,
                        1,
                        isset($row['file_uploaded']) ? (int) $row['file_uploaded'] : 0,
                        $createdAt,
                        $createdAt,
                        ! empty($row['owner']) ? $row['owner'] : 'Legacy import',
                        $source['source'],
                        $legacyId
                    )
                );
            }
        }

        $this->db->query(
            'INSERT IGNORE INTO `data_asset_migrations` (`migration_key`,`applied_at`) VALUES (?,?)',
            array($migrationKey, date('Y-m-d H:i:s'))
        );
    }

    public function listAssets()
    {
        return $this->db
            ->from('data_assets')
            ->order_by('is_active', 'DESC')
            ->order_by('environment', 'ASC')
            ->order_by('name', 'ASC')
            ->get()
            ->result();
    }

    public function manifestAssets()
    {
        return $this->db
            ->from('data_assets')
            ->where('is_active', 1)
            ->order_by('asset_key', 'ASC')
            ->order_by('environment', 'ASC')
            ->order_by('job_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function getAsset($id)
    {
        return $this->db->where('id', (int) $id)->get('data_assets')->row();
    }

    public function scopeExists($assetKey, $environment, $jobName, $excludeId = 0)
    {
        $this->db->from('data_assets')
            ->where('asset_key', $assetKey)
            ->where('environment', $environment)
            ->where('job_name', $jobName);
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveAsset($data, $id = 0)
    {
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('data_assets', $data);
            return $this->db->affected_rows() >= 0 ? (int) $id : 0;
        }

        $this->db->insert('data_assets', $data);
        return (int) $this->db->insert_id();
    }

    public function deleteAsset($id)
    {
        $this->db->where('id', (int) $id)->delete('data_assets');
        return $this->db->affected_rows();
    }

    public function environments()
    {
        if (! $this->db->table_exists('environment')) {
            return array();
        }

        return $this->db
            ->select('Environment,IsActive')
            ->from('environment')
            ->where('IsActive', 1)
            ->order_by('Environment', 'ASC')
            ->get()
            ->result();
    }

    public function statistics()
    {
        $row = $this->db->query("SELECT
            COUNT(*) AS total,
            SUM(is_active = 1) AS active,
            SUM(direction IN ('input','input_output')) AS inputs,
            SUM(direction IN ('output','input_output')) AS outputs,
            SUM(version > 0) AS uploaded
            FROM data_assets")->row();

        return $row ?: (object) array('total' => 0, 'active' => 0, 'inputs' => 0, 'outputs' => 0, 'uploaded' => 0);
    }
}
