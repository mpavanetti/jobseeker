<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Dashboard_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->ensureDashboardIndexes();
    }

    private function ensureDashboardIndexes()
    {
        if (! $this->db->table_exists('tmf')) {
            return;
        }

        $indexes = array();
        foreach ($this->db->query('SHOW INDEX FROM `tmf`')->result_array() as $row) {
            if (isset($row['Key_name'])) {
                $indexes[$row['Key_name']] = TRUE;
            }
        }

        if (! isset($indexes['tmf_dashboard_activity'])) {
            $this->db->query('ALTER TABLE `tmf` ADD INDEX `tmf_dashboard_activity` (`last_activity`,`status`,`environment`)');
        }

        if (! isset($indexes['tmf_dashboard_environment'])) {
            $this->db->query('ALTER TABLE `tmf` ADD INDEX `tmf_dashboard_environment` (`environment`,`last_activity`,`status`)');
        }
    }

    private function normalizeEnvironmentFilter($environment) {
        $environment = trim((string) $environment);

        if ($environment === '' || $environment === '*' || strtolower($environment) === 'all') {
            return '';
        }

        if ($environment === '__UNKNOWN__' || strtolower($environment) === 'unknown') {
            return '__UNKNOWN__';
        }

        return strtoupper($environment);
    }

    private function environmentFilterValues($environment) {
        $environment = $this->normalizeEnvironmentFilter($environment);

        if ($environment === '' || $environment === '__UNKNOWN__') {
            return array();
        }

        $aliases = array(
            'QA' => array('QA', 'QAS'),
            'QAS' => array('QA', 'QAS'),
            'PROD' => array('PROD', 'PRD', 'PRODUCTION'),
            'PRD' => array('PROD', 'PRD', 'PRODUCTION'),
            'PRODUCTION' => array('PROD', 'PRD', 'PRODUCTION'),
            'HML' => array('HML', 'HOMOLOG', 'HOMOLOGATION'),
            'HOMOLOG' => array('HML', 'HOMOLOG', 'HOMOLOGATION'),
            'HOMOLOGATION' => array('HML', 'HOMOLOG', 'HOMOLOGATION')
        );

        return isset($aliases[$environment]) ? $aliases[$environment] : array($environment);
    }

    private function applyEnvironmentFilter($environment) {
        $environment = $this->normalizeEnvironmentFilter($environment);

        if ($environment === '') {
            return;
        }

        if ($environment === '__UNKNOWN__') {
            $this->db->group_start();
            $this->db->where('environment IS NULL', null, false);
            $this->db->or_where('TRIM(environment) =', '');
            $this->db->group_end();
            return;
        }

        // The TMF environment column uses a case-insensitive collation. Avoid
        // wrapping it in UPPER/TRIM so the dashboard environment index remains usable.
        $this->db->where_in('environment', $this->environmentFilterValues($environment));
    }

    private function applyDashboardFilter($environment) {
        $this->db->group_start();
        $this->db->where('job_name IS NULL', null, false);
        $this->db->or_where('LEFT(job_name, 12) <> '.$this->db->escape('__jobseeker_'), null, false);
        $this->db->group_end();
        $this->applyEnvironmentFilter($environment);
    }

    private function environmentSql($environment, $prefix = 'WHERE') {
        $environment = $this->normalizeEnvironmentFilter($environment);
        $conditions = array('(job_name IS NULL OR LEFT(job_name, 12) <> '.$this->db->escape('__jobseeker_').')');

        if ($environment === '__UNKNOWN__') {
            $conditions[] = '(environment IS NULL OR TRIM(environment) = "")';
        } else if ($environment !== '') {
            $environmentValues = array();
            foreach ($this->environmentFilterValues($environment) as $value) {
                $environmentValues[] = $this->db->escape($value);
            }
            $conditions[] = 'environment IN ('.implode(', ', $environmentValues).')';
        }

        return ' '.$prefix.' '.implode(' AND ', $conditions);
    }

    private function dashboardWhere($environment, $additional = '')
    {
        $where = trim($this->environmentSql($environment, 'WHERE'));
        if ($additional !== '') {
            $where .= ' AND '.$additional;
        }
        return ' '.$where;
    }

    private function canonicalEnvironmentExpression()
    {
        return 'CASE
            WHEN environment IS NULL OR TRIM(environment) = "" THEN "Unknown"
            WHEN UPPER(TRIM(environment)) IN ("QA", "QAS") THEN "QA"
            WHEN UPPER(TRIM(environment)) IN ("PROD", "PRD", "PRODUCTION") THEN "PROD"
            WHEN UPPER(TRIM(environment)) IN ("HML", "HOMOLOG", "HOMOLOGATION") THEN "HML"
            ELSE UPPER(TRIM(environment))
        END';
    }

    private function workloadExpression($includeEventText = TRUE)
    {
        $columns = $includeEventText
            ? 'COALESCE(dimension, ""), COALESCE(job_name, ""), COALESCE(event_text, "")'
            : 'COALESCE(dimension, ""), COALESCE(job_name, "")';
        $subject = 'LOWER(CONCAT_WS(" ", '.$columns.'))';

        return 'CASE
            WHEN '.$subject.' REGEXP "(^|[^a-z])(quality|validate|validation|schema|audit|lineage|governance|reconcile|test)([^a-z]|$)" THEN "Data Quality & Governance"
            WHEN '.$subject.' REGEXP "(^|[^a-z])(machine learning|ml|model|feature|training|inference|artificial intelligence|ai)([^a-z]|$)" THEN "ML & Feature Pipelines"
            WHEN '.$subject.' REGEXP "(^|[^a-z])(stg|stage|staging|ingest|ingestion|extract|landing|raw|bronze|source|upload)([^a-z]|$)" THEN "Ingestion & Landing"
            WHEN '.$subject.' REGEXP "(^|[^a-z])(dm|mart|fact|fato|dim|dimension|metric|semantic|report|publish|serving)([^a-z]|$)" THEN "Serving & Semantic"
            WHEN '.$subject.' REGEXP "(^|[^a-z])(transform|transformation|etl|elt|pipeline|orchestrat|dbt|talend|airflow)([^a-z]|$)" THEN "Transform & Orchestration"
            WHEN '.$subject.' REGEXP "(^|[^a-z])(dw|warehouse|lakehouse|lake|silver|gold|curated)([^a-z]|$)" THEN "Warehouse & Lakehouse"
            ELSE "General Workloads"
        END';
    }

    private function numericRecordsExpression($column = 'records_processed')
    {
        return 'CASE WHEN '.$column.' REGEXP "^[0-9]+$" THEN CAST('.$column.' AS UNSIGNED) ELSE 0 END';
    }

    private function rowValue($row, $key, $default = 0)
    {
        return is_array($row) && array_key_exists($key, $row) && $row[$key] !== NULL ? $row[$key] : $default;
    }

    private function percent($numerator, $denominator)
    {
        return (float) $denominator > 0 ? round(((float) $numerator / (float) $denominator) * 100, 1) : NULL;
    }

    private function percentChange($current, $previous)
    {
        if ((float) $previous == 0.0) {
            return (float) $current == 0.0 ? 0.0 : NULL;
        }

        return round((((float) $current - (float) $previous) / abs((float) $previous)) * 100, 1);
    }

    private function periodSnapshot($row, $prefix)
    {
        $status = array(
            'ready' => (int) $this->rowValue($row, $prefix.'_ready'),
            'warning' => (int) $this->rowValue($row, $prefix.'_warning'),
            'error' => (int) $this->rowValue($row, $prefix.'_error'),
            'running' => (int) $this->rowValue($row, $prefix.'_running'),
            'cancelled' => (int) $this->rowValue($row, $prefix.'_cancelled'),
            'other' => (int) $this->rowValue($row, $prefix.'_other')
        );
        $assessed = $status['ready'] + $status['warning'] + $status['error'];

        return array(
            'executions' => (int) $this->rowValue($row, $prefix.'_executions'),
            'status' => $status,
            'assessed' => $assessed,
            'readyRate' => $this->percent($status['ready'], $assessed),
            'attention' => $status['warning'] + $status['error'],
            'recordsProcessed' => (int) $this->rowValue($row, $prefix.'_records'),
            'averageDurationSeconds' => round((float) $this->rowValue($row, $prefix.'_average_duration'), 1)
        );
    }

    private function dashboardPeriodSummary($environment)
    {
        $records = $this->numericRecordsExpression();
        $where = $this->dashboardWhere($environment, 'last_activity <= NOW()');
        $statusOther = 'LOWER(status) NOT IN ("ready", "warning", "error", "running", "cancelled")';
        $select = array(
            'COUNT(*) AS history_executions',
            'MIN(last_activity) AS first_activity',
            'MAX(last_activity) AS last_activity',
            'SUM(CASE WHEN LOWER(status) = "running" THEN 1 ELSE 0 END) AS active_running',
            'SUM(CASE WHEN LOWER(status) = "running" AND last_activity < NOW() - INTERVAL 2 HOUR THEN 1 ELSE 0 END) AS stale_running'
        );

        foreach (array('current' => 30, 'previous' => 60) as $prefix => $days) {
            $lower = 'last_activity >= NOW() - INTERVAL '.$days.' DAY';
            $upper = $prefix === 'previous' ? ' AND last_activity < NOW() - INTERVAL 30 DAY' : '';
            $period = '('.$lower.$upper.')';
            $select[] = 'SUM(CASE WHEN '.$period.' THEN 1 ELSE 0 END) AS '.$prefix.'_executions';
            foreach (array('ready', 'warning', 'error', 'running', 'cancelled') as $status) {
                $select[] = 'SUM(CASE WHEN '.$period.' AND LOWER(status) = "'.$status.'" THEN 1 ELSE 0 END) AS '.$prefix.'_'.$status;
            }
            $select[] = 'SUM(CASE WHEN '.$period.' AND '.$statusOther.' THEN 1 ELSE 0 END) AS '.$prefix.'_other';
            $select[] = 'SUM(CASE WHEN '.$period.' THEN '.$records.' ELSE 0 END) AS '.$prefix.'_records';
            $select[] = 'AVG(CASE WHEN '.$period.' AND running_time IS NOT NULL THEN TIME_TO_SEC(running_time) ELSE NULL END) AS '.$prefix.'_average_duration';
        }

        $row = $this->db->query('SELECT '.implode(', ', $select).' FROM tmf'.$where)->row_array();
        $current = $this->periodSnapshot($row, 'current');
        $previous = $this->periodSnapshot($row, 'previous');

        return array(
            'history' => array(
                'executions' => (int) $this->rowValue($row, 'history_executions'),
                'firstActivity' => $this->rowValue($row, 'first_activity', NULL),
                'lastActivity' => $this->rowValue($row, 'last_activity', NULL)
            ),
            'active' => array(
                'running' => (int) $this->rowValue($row, 'active_running'),
                'fresh' => max(0, (int) $this->rowValue($row, 'active_running') - (int) $this->rowValue($row, 'stale_running')),
                'stale' => (int) $this->rowValue($row, 'stale_running'),
                'staleAfterMinutes' => 120
            ),
            'current' => $current,
            'previous' => $previous,
            'change' => array(
                'executionsPercent' => $this->percentChange($current['executions'], $previous['executions']),
                'readyRatePoints' => $current['readyRate'] === NULL || $previous['readyRate'] === NULL ? NULL : round($current['readyRate'] - $previous['readyRate'], 1),
                'attentionPercent' => $this->percentChange($current['attention'], $previous['attention']),
                'recordsPercent' => $this->percentChange($current['recordsProcessed'], $previous['recordsProcessed']),
                'averageDurationPercent' => $this->percentChange($current['averageDurationSeconds'], $previous['averageDurationSeconds'])
            )
        );
    }

    private function dashboardTrend($environment)
    {
        $query = $this->db->query('SELECT DATE(last_activity) AS activity_date,
            COUNT(*) AS executions,
            SUM(CASE WHEN LOWER(status) = "ready" THEN 1 ELSE 0 END) AS ready,
            SUM(CASE WHEN LOWER(status) = "warning" THEN 1 ELSE 0 END) AS warning,
            SUM(CASE WHEN LOWER(status) = "error" THEN 1 ELSE 0 END) AS error,
            SUM(CASE WHEN LOWER(status) = "cancelled" THEN 1 ELSE 0 END) AS cancelled
            FROM tmf'.$this->dashboardWhere($environment, 'last_activity >= CURDATE() - INTERVAL 179 DAY AND last_activity < CURDATE() + INTERVAL 1 DAY').'
            GROUP BY DATE(last_activity) ORDER BY activity_date ASC');

        $rowsByDate = array();
        foreach ($query->result_array() as $row) {
            $rowsByDate[$row['activity_date']] = $row;
        }

        $trend = array();
        $cursor = new DateTime('today -179 days');
        $end = new DateTime('today');
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $row = isset($rowsByDate[$date]) ? $rowsByDate[$date] : array();
            $trend[] = array(
                'date' => $date,
                'executions' => (int) $this->rowValue($row, 'executions'),
                'ready' => (int) $this->rowValue($row, 'ready'),
                'warning' => (int) $this->rowValue($row, 'warning'),
                'error' => (int) $this->rowValue($row, 'error'),
                'cancelled' => (int) $this->rowValue($row, 'cancelled')
            );
            $cursor->modify('+1 day');
        }

        return $trend;
    }

    private function dashboardEnvironmentSummary($environment)
    {
        $canonical = $this->canonicalEnvironmentExpression();
        $records = $this->numericRecordsExpression();
        $query = $this->db->query('SELECT '.$canonical.' AS environment,
            COUNT(*) AS executions,
            SUM(CASE WHEN LOWER(status) = "ready" THEN 1 ELSE 0 END) AS ready,
            SUM(CASE WHEN LOWER(status) = "warning" THEN 1 ELSE 0 END) AS warning,
            SUM(CASE WHEN LOWER(status) = "error" THEN 1 ELSE 0 END) AS error,
            SUM(CASE WHEN LOWER(status) = "running" THEN 1 ELSE 0 END) AS running,
            SUM(CASE WHEN LOWER(status) = "cancelled" THEN 1 ELSE 0 END) AS cancelled,
            SUM('.$records.') AS records_processed,
            AVG(CASE WHEN running_time IS NOT NULL THEN TIME_TO_SEC(running_time) ELSE NULL END) AS average_duration,
            MAX(last_activity) AS last_activity
            FROM tmf'.$this->dashboardWhere($environment, 'last_activity >= NOW() - INTERVAL 30 DAY AND last_activity <= NOW()').'
            GROUP BY '.$canonical.' ORDER BY executions DESC, environment ASC');

        $result = array();
        foreach ($query->result_array() as $row) {
            $assessed = (int) $row['ready'] + (int) $row['warning'] + (int) $row['error'];
            $result[] = array(
                'environment' => $row['environment'],
                'executions' => (int) $row['executions'],
                'ready' => (int) $row['ready'],
                'warning' => (int) $row['warning'],
                'error' => (int) $row['error'],
                'running' => (int) $row['running'],
                'cancelled' => (int) $row['cancelled'],
                'attention' => (int) $row['warning'] + (int) $row['error'],
                'readyRate' => $this->percent($row['ready'], $assessed),
                'recordsProcessed' => (int) $row['records_processed'],
                'averageDurationSeconds' => round((float) $row['average_duration'], 1),
                'lastActivity' => $row['last_activity']
            );
        }

        return $result;
    }

    private function dashboardWorkloads($environment)
    {
        $category = $this->workloadExpression(TRUE);
        $query = $this->db->query('SELECT '.$category.' AS category,
            COUNT(*) AS executions,
            SUM(CASE WHEN LOWER(status) = "ready" THEN 1 ELSE 0 END) AS ready,
            SUM(CASE WHEN LOWER(status) IN ("warning", "error") THEN 1 ELSE 0 END) AS attention,
            COUNT(DISTINCT job_name) AS jobs
            FROM tmf'.$this->dashboardWhere($environment, 'last_activity >= NOW() - INTERVAL 180 DAY AND last_activity <= NOW()').'
            GROUP BY '.$category.' ORDER BY executions DESC, category ASC');

        $result = array();
        foreach ($query->result_array() as $row) {
            $result[] = array(
                'category' => $row['category'],
                'executions' => (int) $row['executions'],
                'ready' => (int) $row['ready'],
                'attention' => (int) $row['attention'],
                'jobs' => (int) $row['jobs']
            );
        }
        return $result;
    }

    private function dashboardTopPipelines($environment)
    {
        $category = $this->workloadExpression(FALSE);
        $records = $this->numericRecordsExpression();
        $query = $this->db->query('SELECT job_name,
            MAX(dimension) AS dimension,
            '.$category.' AS category,
            COUNT(*) AS executions,
            SUM(CASE WHEN LOWER(status) = "ready" THEN 1 ELSE 0 END) AS ready,
            SUM(CASE WHEN LOWER(status) IN ("warning", "error") THEN 1 ELSE 0 END) AS attention,
            SUM('.$records.') AS records_processed,
            AVG(CASE WHEN running_time IS NOT NULL THEN TIME_TO_SEC(running_time) ELSE NULL END) AS average_duration,
            MAX(last_activity) AS last_activity
            FROM tmf'.$this->dashboardWhere($environment, 'last_activity >= NOW() - INTERVAL 30 DAY AND last_activity <= NOW()').'
            GROUP BY job_name, '.$category.' ORDER BY executions DESC, last_activity DESC LIMIT 8');

        $result = array();
        foreach ($query->result_array() as $row) {
            $assessed = (int) $row['ready'] + (int) $row['attention'];
            $result[] = array(
                'job' => $row['job_name'],
                'dimension' => $row['dimension'],
                'category' => $row['category'],
                'executions' => (int) $row['executions'],
                'attention' => (int) $row['attention'],
                'readyRate' => $this->percent($row['ready'], $assessed),
                'recordsProcessed' => (int) $row['records_processed'],
                'averageDurationSeconds' => round((float) $row['average_duration'], 1),
                'lastActivity' => $row['last_activity']
            );
        }
        return $result;
    }

    private function dashboardRecentExecutions($environment)
    {
        $category = $this->workloadExpression(TRUE);
        $query = $this->db->query('SELECT job_name, '.$this->canonicalEnvironmentExpression().' AS environment,
            '.$category.' AS category, LOWER(status) AS status, event_text,
            CASE WHEN records_processed REGEXP "^[0-9]+$" THEN CAST(records_processed AS UNSIGNED) ELSE NULL END AS records_processed,
            TIME_TO_SEC(running_time) AS duration_seconds, last_activity
            FROM tmf'.$this->dashboardWhere($environment, 'last_activity <= NOW()').'
            ORDER BY last_activity DESC, id DESC LIMIT 8');

        $result = array();
        foreach ($query->result_array() as $row) {
            $result[] = array(
                'job' => $row['job_name'],
                'environment' => $row['environment'],
                'category' => $row['category'],
                'status' => $row['status'],
                'event' => $row['event_text'],
                'recordsProcessed' => $row['records_processed'] === NULL ? NULL : (int) $row['records_processed'],
                'durationSeconds' => $row['duration_seconds'] === NULL ? NULL : round((float) $row['duration_seconds'], 1),
                'lastActivity' => $row['last_activity']
            );
        }
        return $result;
    }

    private function dashboardAssets($environment)
    {
        $summary = array(
            'active' => 0,
            'inputs' => 0,
            'outputs' => 0,
            'other' => 0,
            'storedBytes' => 0,
            'formats' => array()
        );
        if (! $this->db->table_exists('data_assets')) {
            return $summary;
        }

        $normalized = $this->normalizeEnvironmentFilter($environment);
        $this->db->select('LOWER(direction) AS direction, LOWER(format) AS format, COUNT(*) AS amount, SUM(COALESCE(file_size, 0)) AS stored_bytes', FALSE);
        $this->db->from('data_assets');
        $this->db->where('is_active', 1);
        if ($normalized !== '') {
            $this->db->group_start();
            $this->db->where('UPPER(TRIM(environment)) =', 'ALL');
            if ($normalized === '__UNKNOWN__') {
                $this->db->or_where('environment IS NULL', NULL, FALSE);
                $this->db->or_where('TRIM(environment) =', '');
            } else {
                foreach ($this->environmentFilterValues($normalized) as $value) {
                    $this->db->or_where('UPPER(TRIM(environment)) =', $value);
                }
            }
            $this->db->group_end();
        }
        $this->db->group_by(array('LOWER(direction)', 'LOWER(format)'));

        $formats = array();
        foreach ($this->db->get()->result_array() as $row) {
            $amount = (int) $row['amount'];
            $direction = $row['direction'];
            $format = $row['format'] !== '' ? $row['format'] : 'unknown';
            $summary['active'] += $amount;
            $summary['storedBytes'] += (int) $row['stored_bytes'];
            if ($direction === 'input') {
                $summary['inputs'] += $amount;
            } else if ($direction === 'output') {
                $summary['outputs'] += $amount;
            } else {
                $summary['other'] += $amount;
            }
            $formats[$format] = isset($formats[$format]) ? $formats[$format] + $amount : $amount;
        }

        arsort($formats);
        foreach ($formats as $format => $amount) {
            $summary['formats'][] = array('format' => $format, 'amount' => (int) $amount);
        }
        return $summary;
    }

    public function overview($environment = '')
    {
        $period = $this->dashboardPeriodSummary($environment);

        return array(
            'generatedAt' => date('c'),
            'scope' => $this->normalizeEnvironmentFilter($environment) === '' ? 'all' : $this->normalizeEnvironmentFilter($environment),
            'history' => $period['history'],
            'active' => $period['active'],
            'period' => array(
                'days' => 30,
                'current' => $period['current'],
                'previous' => $period['previous'],
                'change' => $period['change']
            ),
            'trend' => $this->dashboardTrend($environment),
            'environments' => $this->dashboardEnvironmentSummary($environment),
            'workloads' => $this->dashboardWorkloads($environment),
            'assets' => $this->dashboardAssets($environment),
            'topPipelines' => $this->dashboardTopPipelines($environment),
            'recentExecutions' => $this->dashboardRecentExecutions($environment)
        );
    }
}
