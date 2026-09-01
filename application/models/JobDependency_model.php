<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Stores and resolves the connector / data-asset dependency map for a job.
 *
 * "Light" resolution answers exists / active / in-scope from the catalogs.
 * "Heavy" results (a real worker connection handshake) are written back per
 * connector with recordTestResult() and surfaced on Job View / Job Execution.
 */
class JobDependency_model extends CI_Model
{
    private function environmentAliases($environment)
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
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `job_dependencies` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
            `kind` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
            `ref_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `ref_id` int(11) DEFAULT NULL,
            `detected_from` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'code',
            `light_status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `status_message` varchar(600) COLLATE utf8_unicode_ci DEFAULT NULL,
            `checked_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `job_dependencies_row` (`job_name`,`environment`,`kind`,`ref_key`),
            KEY `job_dependencies_job` (`job_name`,`environment`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    /**
     * @param array $connectorKeys scanned + normalised connector keys
     * @param array $datasetKeys   scanned + normalised data-asset keys
     * @return array ['connectors' => [...], 'datasets' => [...], 'warnings' => [...]]
     */
    public function resolve($connectorKeys, $datasetKeys, $environment, $jobName)
    {
        $environment = strtoupper(trim((string) $environment)) ?: 'ALL';
        $jobName = trim((string) $jobName);

        return array(
            'connectors' => $this->resolveConnectors(array_values(array_unique((array) $connectorKeys)), $environment, $jobName),
            'datasets' => $this->resolveDatasets(array_values(array_unique((array) $datasetKeys)), $environment, $jobName),
        );
    }

    private function resolveConnectors($keys, $environment, $jobName)
    {
        if (empty($keys)) {
            return array();
        }
        $rows = $this->db->select('id,connector_key,environment,job_name,db_type,is_active')
            ->from('database_settings')
            ->where_in('connector_key', $keys)
            ->get()->result_array();

        $byKey = array();
        foreach ($rows as $row) {
            $byKey[$row['connector_key']][] = $row;
        }
        $scopeEnvironments = array_merge($this->environmentAliases($environment), array('ALL'));

        $resolved = array();
        foreach ($keys as $key) {
            $candidates = isset($byKey[$key]) ? $byKey[$key] : array();
            $exists = ! empty($candidates);
            $activeRows = array_values(array_filter($candidates, function($row) { return (int) $row['is_active'] === 1; }));
            $active = ! empty($activeRows);
            $scopedRows = array_values(array_filter($activeRows, function($row) use ($scopeEnvironments, $environment, $jobName) {
                $environmentMatch = $environment === 'ALL' || in_array(strtoupper($row['environment']), $scopeEnvironments, TRUE);
                $jobMatch = $row['job_name'] === '*' || $jobName === '' || $row['job_name'] === $jobName;
                return $environmentMatch && $jobMatch;
            }));
            $scoped = ! empty($scopedRows);
            $best = $scoped ? $scopedRows[0] : ($active ? $activeRows[0] : ($exists ? $candidates[0] : NULL));

            $resolved[] = array(
                'key' => $key,
                'kind' => 'connector',
                'exists' => $exists,
                'active' => $active,
                'inScope' => $scoped,
                'type' => $best ? (string) $best['db_type'] : '',
                'refId' => $best ? (int) $best['id'] : NULL,
                'environments' => array_values(array_unique(array_map(function($row) { return $row['environment']; }, $candidates))),
                'lightStatus' => $this->lightStatus($exists, $active, $scoped),
            );
        }
        return $resolved;
    }

    private function resolveDatasets($keys, $environment, $jobName)
    {
        if (empty($keys)) {
            return array();
        }
        $rows = $this->db->select('id,asset_key,environment,job_name,direction,format,is_active')
            ->from('data_assets')
            ->where_in('asset_key', $keys)
            ->get()->result_array();

        $byKey = array();
        foreach ($rows as $row) {
            $byKey[$row['asset_key']][] = $row;
        }
        $scopeEnvironments = array_merge($this->environmentAliases($environment), array('ALL'));

        $resolved = array();
        foreach ($keys as $key) {
            $candidates = isset($byKey[$key]) ? $byKey[$key] : array();
            $exists = ! empty($candidates);
            $activeRows = array_values(array_filter($candidates, function($row) { return (int) $row['is_active'] === 1; }));
            $active = ! empty($activeRows);
            $scopedRows = array_values(array_filter($activeRows, function($row) use ($scopeEnvironments, $jobName) {
                $environmentMatch = $environment === 'ALL' || in_array(strtoupper($row['environment']), $scopeEnvironments, TRUE);
                $jobMatch = $row['job_name'] === '*' || $jobName === '' || $row['job_name'] === $jobName;
                return $environmentMatch && $jobMatch;
            }));
            $scoped = ! empty($scopedRows);
            $best = $scoped ? $scopedRows[0] : ($active ? $activeRows[0] : ($exists ? $candidates[0] : NULL));

            $resolved[] = array(
                'key' => $key,
                'kind' => 'dataset',
                'exists' => $exists,
                'active' => $active,
                'inScope' => $scoped,
                'type' => $best ? (string) $best['direction'] : '',
                'format' => $best ? (string) $best['format'] : '',
                'refId' => $best ? (int) $best['id'] : NULL,
                'environments' => array_values(array_unique(array_map(function($row) { return $row['environment']; }, $candidates))),
                'lightStatus' => $this->lightStatus($exists, $active, $scoped),
            );
        }
        return $resolved;
    }

    private function firstMatchById($rows, $id)
    {
        foreach ($rows as $row) {
            if ((int) $row['id'] === (int) $id) {
                return $row;
            }
        }
        return NULL;
    }

    private function lightStatus($exists, $active, $inScope)
    {
        if (! $exists) {
            return 'missing';
        }
        if (! $active) {
            return 'inactive';
        }
        if (! $inScope) {
            return 'out_of_scope';
        }
        return 'ok';
    }

    /**
     * Replace the stored dependency set for a job/environment with the freshly
     * resolved rows, preserving any recorded heavy-test status for keys that
     * are still referenced.
     */
    public function replaceForJob($jobName, $environment, $resolved)
    {
        $jobName = trim((string) $jobName);
        $environment = strtoupper(trim((string) $environment)) ?: 'ALL';
        if ($jobName === '') {
            return;
        }
        $now = date('Y-m-d H:i:s');

        $existing = array();
        foreach ($this->db->from('job_dependencies')->where('job_name', $jobName)->where('environment', $environment)->get()->result_array() as $row) {
            $existing[$row['kind'].':'.$row['ref_key']] = $row;
        }

        $keep = array();
        foreach (array('connectors', 'datasets') as $group) {
            foreach (isset($resolved[$group]) ? $resolved[$group] : array() as $item) {
                $kind = $group === 'connectors' ? 'connector' : 'dataset';
                $mapKey = $kind.':'.$item['key'];
                $keep[$mapKey] = TRUE;
                $prior = isset($existing[$mapKey]) ? $existing[$mapKey] : NULL;
                $data = array(
                    'job_name' => $jobName,
                    'environment' => $environment,
                    'kind' => $kind,
                    'ref_key' => $item['key'],
                    'ref_id' => isset($item['refId']) ? $item['refId'] : NULL,
                    'detected_from' => isset($item['detectedFrom']) ? implode(',', (array) $item['detectedFrom']) : (isset($item['from']) ? implode(',', (array) $item['from']) : 'code'),
                    'light_status' => isset($item['lightStatus']) ? $item['lightStatus'] : 'unknown',
                    'status' => $prior && $kind === 'connector' ? $prior['status'] : 'unknown',
                    'status_message' => $prior && $kind === 'connector' ? $prior['status_message'] : NULL,
                    'checked_at' => $prior && $kind === 'connector' ? $prior['checked_at'] : NULL,
                    'updated_at' => $now,
                );
                if ($prior) {
                    $this->db->where('id', (int) $prior['id'])->update('job_dependencies', $data);
                } else {
                    $data['created_at'] = $now;
                    $this->db->insert('job_dependencies', $data);
                }
            }
        }

        foreach ($existing as $mapKey => $row) {
            if (! isset($keep[$mapKey])) {
                $this->db->where('id', (int) $row['id'])->delete('job_dependencies');
            }
        }
    }

    public function recordTestResult($jobName, $environment, $connectorKey, $status, $message)
    {
        $this->db->where('job_name', trim((string) $jobName))
            ->where('environment', strtoupper(trim((string) $environment)) ?: 'ALL')
            ->where('kind', 'connector')
            ->where('ref_key', $connectorKey)
            ->update('job_dependencies', array(
                'status' => (string) $status,
                'status_message' => $message === NULL ? NULL : substr((string) $message, 0, 600),
                'checked_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ));
    }

    public function listForJob($jobName, $environment)
    {
        $rows = $this->db->from('job_dependencies')
            ->where('job_name', trim((string) $jobName))
            ->where('environment', strtoupper(trim((string) $environment)) ?: 'ALL')
            ->order_by('kind', 'ASC')->order_by('ref_key', 'ASC')
            ->get()->result_array();

        $connectors = array();
        $datasets = array();
        foreach ($rows as $row) {
            $entry = array(
                'key' => $row['ref_key'],
                'kind' => $row['kind'],
                'refId' => $row['ref_id'] !== NULL ? (int) $row['ref_id'] : NULL,
                'detectedFrom' => $row['detected_from'] === '' ? array() : explode(',', $row['detected_from']),
                'lightStatus' => $row['light_status'],
                'status' => $row['status'],
                'statusMessage' => $row['status_message'],
                'checkedAt' => $row['checked_at'],
            );
            if ($row['kind'] === 'connector') {
                $connectors[] = $entry;
            } else {
                $datasets[] = $entry;
            }
        }
        return array('connectors' => $connectors, 'datasets' => $datasets);
    }

    public function summaryForJobs($jobNames, $environment)
    {
        $jobNames = array_values(array_filter(array_map('trim', (array) $jobNames)));
        if (empty($jobNames)) {
            return array();
        }
        $rows = $this->db->select('job_name,kind,light_status,status,COUNT(*) AS total', FALSE)
            ->from('job_dependencies')
            ->where_in('job_name', $jobNames)
            ->where('environment', strtoupper(trim((string) $environment)) ?: 'ALL')
            ->group_by(array('job_name', 'kind', 'light_status', 'status'))
            ->get()->result_array();

        $summary = array();
        foreach ($rows as $row) {
            $name = $row['job_name'];
            if (! isset($summary[$name])) {
                $summary[$name] = array('connectors' => 0, 'datasets' => 0, 'attention' => 0, 'tested_ok' => 0, 'tested_failed' => 0);
            }
            $count = (int) $row['total'];
            $summary[$name][$row['kind'] === 'connector' ? 'connectors' : 'datasets'] += $count;
            if (in_array($row['light_status'], array('missing', 'inactive', 'out_of_scope'), TRUE)) {
                $summary[$name]['attention'] += $count;
            }
            if ($row['status'] === 'passed') {
                $summary[$name]['tested_ok'] += $count;
            } elseif (in_array($row['status'], array('unreachable', 'auth_failed', 'error', 'timeout'), TRUE)) {
                $summary[$name]['tested_failed'] += $count;
            }
        }
        return $summary;
    }

    public function deleteForJob($jobName, $environment = NULL)
    {
        $this->db->where('job_name', trim((string) $jobName));
        if ($environment !== NULL) {
            $this->db->where('environment', strtoupper(trim((string) $environment)) ?: 'ALL');
        }
        $this->db->delete('job_dependencies');
        return $this->db->affected_rows();
    }
}
