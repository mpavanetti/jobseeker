<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

class DataAssets_model extends CI_Model
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

        // --- unified store: immutable version history + profiling -----------
        // The ML platform, connector queries and job run outputs all cut a
        // data_asset_versions row so a dataset has a real history with a schema,
        // a statistical profile and a drift fingerprint per version. The parent
        // data_assets.version int is kept (checksum-driven) for ETL back-compat.
        $this->db->query("ALTER TABLE `data_assets`
            ADD COLUMN IF NOT EXISTS `kind` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'file' AFTER `format`,
            ADD COLUMN IF NOT EXISTS `source` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'manual' AFTER `kind`,
            ADD COLUMN IF NOT EXISTS `tags` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `description`,
            ADD COLUMN IF NOT EXISTS `latest_version` int(11) NOT NULL DEFAULT 0 AFTER `version`,
            ADD COLUMN IF NOT EXISTS `schema_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL AFTER `latest_version`,
            ADD COLUMN IF NOT EXISTS `profile_status` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'none' AFTER `schema_json`");

        $this->db->query("CREATE TABLE IF NOT EXISTS `data_asset_versions` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `asset_id` int(11) NOT NULL,
            `version` int(11) NOT NULL,
            `source_type` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'upload',
            `source_ref_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `storage_path` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `artifact_id` bigint(20) unsigned DEFAULT NULL,
            `checksum` char(64) COLLATE utf8_unicode_ci DEFAULT NULL,
            `size_bytes` bigint(20) unsigned DEFAULT NULL,
            `format` varchar(24) COLLATE utf8_unicode_ci DEFAULT NULL,
            `row_count` bigint(20) DEFAULT NULL,
            `column_count` int(11) DEFAULT NULL,
            `schema_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `profile_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `fingerprint_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `profile_status` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'pending',
            `profile_error` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `produced_by_run_id` bigint(20) unsigned DEFAULT NULL,
            `notes` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `created_by` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `data_asset_versions_scope` (`asset_id`,`version`),
            KEY `data_asset_versions_asset` (`asset_id`,`version`),
            KEY `data_asset_versions_run` (`produced_by_run_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    // -----------------------------------------------------------------------
    // Immutable version history (unified store). Used by the ML platform and by
    // connector-query / run-output dataset registration.
    // -----------------------------------------------------------------------

    public function findOrCreateAsset($assetKey, $name, $environment, $owner, $options = array())
    {
        $environment = strtoupper(trim((string) $environment)) ?: 'ALL';
        $existing = $this->db->where('asset_key', (string) $assetKey)
            ->where_in('environment', array($environment, 'ALL'))
            ->order_by("environment = 'ALL'", 'ASC', FALSE)
            ->get('data_assets')->row();
        if ($existing) {
            return (int) $existing->id;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert('data_assets', array(
            'asset_key' => (string) $assetKey,
            'name' => $name !== '' ? $name : (string) $assetKey,
            'direction' => isset($options['direction']) ? $options['direction'] : 'input',
            'format' => isset($options['format']) ? $options['format'] : 'csv',
            'kind' => isset($options['kind']) ? $options['kind'] : 'table',
            'source' => isset($options['source']) ? $options['source'] : 'ml',
            'environment' => $environment,
            'job_name' => isset($options['job_name']) ? $options['job_name'] : '*',
            'storage_path' => isset($options['storage_path']) ? $options['storage_path'] : '',
            'file_name' => isset($options['file_name']) ? $options['file_name'] : ((string) $assetKey.'.csv'),
            'description' => isset($options['description']) ? $options['description'] : NULL,
            'tags' => isset($options['tags']) ? $options['tags'] : NULL,
            'is_required' => 0,
            'is_active' => 1,
            'version' => 0,
            'latest_version' => 0,
            'profile_status' => 'none',
            'created_at' => $now,
            'updated_at' => $now,
            'owner' => (string) $owner,
        ));
        return (int) $this->db->insert_id();
    }

    public function nextVersion($assetId)
    {
        $row = $this->db->select_max('version')->where('asset_id', (int) $assetId)->get('data_asset_versions')->row();
        return ($row && $row->version !== NULL) ? ((int) $row->version + 1) : 1;
    }

    public function createVersion($assetId, $data)
    {
        $version = $this->nextVersion($assetId);
        $data = array_merge(array(
            'asset_id' => (int) $assetId,
            'version' => $version,
            'profile_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ), $data);
        $this->db->insert('data_asset_versions', $data);
        $newId = (int) $this->db->insert_id();
        $update = array(
            'latest_version' => $version,
            'version' => $version,
            'updated_at' => date('Y-m-d H:i:s'),
            'profile_status' => isset($data['profile_status']) ? $data['profile_status'] : 'pending',
        );
        if (! empty($data['schema_json'])) {
            $update['schema_json'] = $data['schema_json'];
        }
        if (! empty($data['format'])) {
            $update['format'] = $data['format'];
        }
        if (! empty($data['storage_path'])) {
            $update['storage_path'] = $data['storage_path'];
        }
        if (! empty($data['checksum'])) {
            $update['checksum'] = $data['checksum'];
        }
        if (isset($data['size_bytes'])) {
            $update['file_size'] = (int) $data['size_bytes'];
        }
        $this->db->where('id', (int) $assetId)->update('data_assets', $update);
        return array('id' => $newId, 'version' => $version);
    }

    public function getVersion($id)
    {
        return $this->db->where('id', (int) $id)->get('data_asset_versions')->row();
    }

    public function getVersionByNumber($assetId, $version)
    {
        return $this->db->where('asset_id', (int) $assetId)->where('version', (int) $version)
            ->get('data_asset_versions')->row();
    }

    public function latestVersion($assetId)
    {
        return $this->db->where('asset_id', (int) $assetId)
            ->order_by('version', 'DESC')->limit(1)->get('data_asset_versions')->row();
    }

    public function listVersions($assetId, $limit = 100)
    {
        return $this->db->where('asset_id', (int) $assetId)
            ->order_by('version', 'DESC')->limit(max(1, min(300, (int) $limit)))
            ->get('data_asset_versions')->result();
    }

    public function updateVersion($id, $data)
    {
        $this->db->where('id', (int) $id)->update('data_asset_versions', $data);
    }

    public function assetsWithVersions($environment = 'ALL', $sources = array())
    {
        $this->db->from('data_assets');
        $environment = strtoupper(trim((string) $environment));
        if ($environment !== '' && $environment !== '*' && $environment !== 'ALL') {
            $this->db->where_in('environment', array_merge($this->environmentFilterValues($environment), array('ALL')));
        }
        if (! empty($sources)) {
            $this->db->where_in('source', $sources);
        }
        return $this->db->order_by('is_active', 'DESC')->order_by('name', 'ASC')->get()->result();
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

    public function listAssets($environment = 'ALL')
    {
        $this->db->from('data_assets');
        $environment = strtoupper(trim((string) $environment));
        if ($environment !== '' && $environment !== '*' && $environment !== 'ALL') {
            $this->db->where_in('environment', array_merge($this->environmentFilterValues($environment), array('ALL')));
        }
        return $this->db
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

    public function statistics($environment = 'ALL')
    {
        $this->db->select("COUNT(*) AS total, SUM(is_active = 1) AS active, SUM(direction IN ('input','input_output')) AS inputs, SUM(direction IN ('output','input_output')) AS outputs, SUM(version > 0) AS uploaded", FALSE);
        $this->db->from('data_assets');
        $environment = strtoupper(trim((string) $environment));
        if ($environment !== '' && $environment !== '*' && $environment !== 'ALL') {
            $this->db->where_in('environment', array_merge($this->environmentFilterValues($environment), array('ALL')));
        }
        $row = $this->db->get()->row();

        return $row ?: (object) array('total' => 0, 'active' => 0, 'inputs' => 0, 'outputs' => 0, 'uploaded' => 0);
    }
}
