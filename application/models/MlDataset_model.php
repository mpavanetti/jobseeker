<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ML-facing adapter over the unified Data Assets store.
 *
 * v2 folded the ML dataset registry into `data_assets` + `data_asset_versions`
 * (see DataAssets_model): one catalogue for ETL and ML, with immutable version
 * history, schema/profile/fingerprint per version and drift baselines. This
 * class keeps the method names the ML controllers/libraries already call and
 * translates them to the shared tables, so a "dataset" is just a `data_assets`
 * row (any row can be bound to an ML job) and a "dataset version" is a
 * `data_asset_versions` row. Rows the ML platform creates carry `source = 'ml'`.
 */
class MlDataset_model extends CI_Model
{
    const SOURCE_TYPES = array('upload', 'connector', 'repository', 'run_output');

    public function __construct()
    {
        parent::__construct();
        // DataAssets_model owns the shared schema (data_assets + versions).
        $this->load->model('DataAssets_model', 'assets');
    }

    private function aliasAsset($row)
    {
        if ($row && ! isset($row->dataset_key)) {
            $row->dataset_key = isset($row->asset_key) ? $row->asset_key : NULL;
            $row->default_source_type = isset($row->source) && $row->source !== 'manual' ? $row->source : 'upload';
        }
        return $row;
    }

    private function aliasVersion($row)
    {
        if ($row && ! isset($row->dataset_id)) {
            $row->dataset_id = isset($row->asset_id) ? (int) $row->asset_id : NULL;
        }
        return $row;
    }

    // --- datasets ------------------------------------------------------

    public function listDatasets($environment = 'ALL')
    {
        return array_map(array($this, 'aliasAsset'), $this->assets->assetsWithVersions($environment));
    }

    public function getDataset($id)
    {
        return $this->aliasAsset($this->assets->getAsset((int) $id));
    }

    public function getDatasetByKey($key, $environment)
    {
        $environment = strtoupper(trim((string) $environment)) ?: 'ALL';
        $row = $this->db->where('asset_key', (string) $key)
            ->group_start()->where('environment', $environment)->or_where('environment', 'ALL')->group_end()
            ->order_by("environment = 'ALL'", 'ASC', FALSE)
            ->get('data_assets')->row();
        return $this->aliasAsset($row);
    }

    public function datasetScopeExists($key, $environment, $excludeId = 0)
    {
        $this->db->from('data_assets')->where('asset_key', (string) $key)
            ->where('environment', strtoupper(trim((string) $environment)) ?: 'ALL');
        if ((int) $excludeId > 0) {
            $this->db->where('id !=', (int) $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function saveDataset($data, $id = 0)
    {
        $now = date('Y-m-d H:i:s');
        $row = array(
            'name' => isset($data['name']) ? $data['name'] : NULL,
            'environment' => isset($data['environment']) ? strtoupper($data['environment']) : 'ALL',
            'kind' => isset($data['kind']) ? $data['kind'] : 'table',
            'description' => isset($data['description']) ? $data['description'] : NULL,
            'tags' => isset($data['tags']) ? $data['tags'] : NULL,
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'updated_at' => $now,
        );
        if (isset($data['default_source_type'])) {
            $row['source'] = $data['default_source_type'] === 'upload' ? 'ml' : $data['default_source_type'];
        }
        if ((int) $id > 0) {
            $this->db->where('id', (int) $id)->update('data_assets', array_filter($row, function ($v) { return $v !== NULL; }));
            return (int) $id;
        }
        $key = isset($data['dataset_key']) ? $data['dataset_key'] : $data['asset_key'];
        $row['asset_key'] = $key;
        $row['direction'] = 'input';
        $row['format'] = isset($data['format']) ? $data['format'] : 'csv';
        $row['source'] = isset($row['source']) ? $row['source'] : 'ml';
        $row['job_name'] = '*';
        $row['storage_path'] = '';
        $row['file_name'] = $key.'.csv';
        $row['is_required'] = 0;
        $row['version'] = 0;
        $row['latest_version'] = 0;
        $row['profile_status'] = 'none';
        $row['created_at'] = $now;
        $row['owner'] = isset($data['owner']) ? $data['owner'] : 'JobSeeker';
        $this->db->insert('data_assets', $row);
        return (int) $this->db->insert_id();
    }

    public function deleteDataset($id)
    {
        $this->db->where('asset_id', (int) $id)->delete('data_asset_versions');
        $this->db->where('id', (int) $id)->delete('data_assets');
        return $this->db->affected_rows();
    }

    public function findOrCreateDataset($key, $name, $environment, $owner, $sourceType = 'run_output', $kind = 'table')
    {
        return $this->assets->findOrCreateAsset($key, $name, $environment, $owner, array(
            'source' => $sourceType === 'run_output' ? 'run_output' : 'ml',
            'kind' => $kind,
            'direction' => $sourceType === 'run_output' ? 'output' : 'input',
        ));
    }

    // --- versions ------------------------------------------------------

    public function nextVersionNumber($datasetId)
    {
        return $this->assets->nextVersion($datasetId);
    }

    public function createVersion($datasetId, $data)
    {
        if (isset($data['content_hash']) && ! isset($data['checksum'])) {
            $data['checksum'] = $data['content_hash'];
            unset($data['content_hash']);
        }
        return $this->assets->createVersion($datasetId, $data);
    }

    public function getVersion($id)
    {
        return $this->aliasVersion($this->assets->getVersion((int) $id));
    }

    public function getVersionByNumber($datasetId, $version)
    {
        return $this->aliasVersion($this->assets->getVersionByNumber($datasetId, $version));
    }

    public function latestVersion($datasetId)
    {
        return $this->aliasVersion($this->assets->latestVersion($datasetId));
    }

    public function listVersions($datasetId, $limit = 50)
    {
        return array_map(array($this, 'aliasVersion'), $this->assets->listVersions($datasetId, $limit));
    }

    public function updateVersion($id, $data)
    {
        $this->assets->updateVersion($id, $data);
    }

    public function pendingProfileVersions($limit = 5)
    {
        return $this->db->where('profile_status', 'pending')
            ->order_by('id', 'ASC')->limit(max(1, min(20, (int) $limit)))
            ->get('data_asset_versions')->result();
    }

    public function statistics($environment = 'ALL')
    {
        $this->db->select("COUNT(*) AS datasets, SUM(is_active = 1) AS active", FALSE)->from('data_assets');
        if ($environment !== 'ALL') {
            $this->db->where_in('environment', array($environment, 'ALL'));
        }
        $row = $this->db->get()->row();
        return $row ?: (object) array('datasets' => 0, 'active' => 0);
    }

    // --- version file storage (shared Data Assets tree) ------------------
    // Dataset version files live under repository/data-assets/ml/<env>/<key>/
    // so DataAssets::preview and the runtime manifest work on them directly.

    private function repositoryRoot()
    {
        $root = getenv('JOBSEEKER_REPOSITORY_ROOT') ?: (rtrim(FCPATH, '/\\').'/repository');
        return rtrim(str_replace('\\', '/', $root), '/');
    }

    public function versionRelativePath($assetKey, $environment, $version, $format)
    {
        $envSeg = strtolower(preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $environment)) ?: 'all';
        $keySeg = strtolower(preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $assetKey)) ?: 'dataset';
        $fmt = preg_replace('/[^a-z0-9]/', '', strtolower((string) $format)) ?: 'csv';
        return 'data-assets/ml/'.$envSeg.'/'.$keySeg.'/v'.(int) $version.'.'.$fmt;
    }

    /**
     * Copy a temp file into the shared tree for a dataset version.
     * @return array{ok:bool, relative_path?:string, absolute_path?:string, checksum?:string, size?:int, message?:string}
     */
    public function storeVersionFile($assetKey, $environment, $version, $format, $tmpPath)
    {
        if (! is_file($tmpPath) || ! is_readable($tmpPath)) {
            return array('ok' => FALSE, 'message' => 'Source file is not readable.');
        }
        $relative = $this->versionRelativePath($assetKey, $environment, $version, $format);
        $absolute = $this->repositoryRoot().'/'.$relative;
        $dir = dirname($absolute);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, TRUE) && ! is_dir($dir)) {
            return array('ok' => FALSE, 'message' => 'Could not create the dataset directory.');
        }
        if (! @copy($tmpPath, $absolute)) {
            return array('ok' => FALSE, 'message' => 'Could not write the dataset file.');
        }
        return array(
            'ok' => TRUE,
            'relative_path' => $relative,
            'absolute_path' => $absolute,
            'checksum' => hash_file('sha256', $absolute),
            'size' => (int) filesize($absolute),
        );
    }

    public function versionAbsolutePath($version)
    {
        if (! $version || empty($version->storage_path) || strpos((string) $version->storage_path, '..') !== FALSE) {
            return FALSE;
        }
        $abs = $this->repositoryRoot().'/'.ltrim((string) $version->storage_path, '/');
        return (is_file($abs) && is_readable($abs)) ? $abs : FALSE;
    }
}
