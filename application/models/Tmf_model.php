<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Tmf_model extends CI_Model
{
    private $resultLimit = 1000;
    private $lastResultTruncated = false;

    public function __construct()
    {
        parent::__construct();

        $configuredLimit = filter_var(getenv('JOBSEEKER_TMF_RESULT_LIMIT'), FILTER_VALIDATE_INT);
        if ($configuredLimit !== false && $configuredLimit > 0) {
            $this->resultLimit = min(10000, (int) $configuredLimit);
        }

        $this->ensureResultIndexes();
    }

    private function ensureResultIndexes()
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

        $requiredIndexes = array(
            'tmf_results_environment' => '(`environment`,`id`)',
            'tmf_results_status' => '(`status`,`id`)',
            'tmf_results_job' => '(`job_name`,`id`)',
            'tmf_instance' => '(`instance_id`)'
        );
        foreach ($requiredIndexes as $name => $columns) {
            if (! isset($indexes[$name])) {
                $this->db->query('ALTER TABLE `tmf` ADD INDEX `'.$name.'` '.$columns);
            }
        }

        if ($this->db->table_exists('tmf_error')) {
            $errorIndexes = array();
            foreach ($this->db->query('SHOW INDEX FROM `tmf_error`')->result_array() as $row) {
                if (isset($row['Key_name'])) {
                    $errorIndexes[$row['Key_name']] = TRUE;
                }
            }
            if (! isset($errorIndexes['tmf_error_instance'])) {
                $this->db->query('ALTER TABLE `tmf_error` ADD INDEX `tmf_error_instance` (`tmf_id`)');
            }
        }
    }

    private function boundedResults()
    {
        $this->lastResultTruncated = false;
        $this->db->limit($this->resultLimit + 1);
        $results = $this->db->get()->result();
        if (count($results) > $this->resultLimit) {
            array_pop($results);
            $this->lastResultTruncated = true;
        }

        return $results;
    }

    public function resultLimit()
    {
        return $this->resultLimit;
    }

    public function lastResultWasTruncated()
    {
        return $this->lastResultTruncated;
    }

    private function selectTmfRows() {
        $this->db->select('tmf.*, tmf.job_name AS jenkins_job_name', FALSE);
    }

    private function hideInternalJobs() {
        $this->db->group_start();
        $this->db->where('tmf.job_name IS NULL', null, false);
        $this->db->or_where('LEFT(tmf.job_name, 12) <> '.$this->db->escape('__jobseeker_'), null, false);
        $this->db->group_end();
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

    private function applyEnvironmentFilter($environment, $column = 'tmf.environment') {
        $environment = $this->normalizeEnvironmentFilter($environment);

        if ($environment === '') {
            return;
        }

        if ($environment === '__UNKNOWN__') {
            $this->db->group_start();
            $this->db->where($column.' IS NULL', null, false);
            $this->db->or_where('TRIM('.$column.') =', '');
            $this->db->group_end();
            return;
        }

        // TMF uses a case-insensitive collation. Keeping the indexed column bare
        // lets MariaDB use the environment indexes instead of scanning every row.
        $this->db->where_in($column, $this->environmentFilterValues($environment));
    }

    private function parseFilterDate($value, $endOfDay)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $hasTime = preg_match('/\d{1,2}:\d{2}/', $value) === 1;
        $hasSeconds = preg_match('/\d{1,2}:\d{2}:\d{2}/', $value) === 1;
        $formats = $hasTime
            ? array('Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd/m/Y H:i:s', 'd/m/Y H:i')
            : array('Y-m-d', 'd-m-Y', 'd/m/Y');

        $date = false;
        foreach ($formats as $format) {
            $parsedDate = DateTime::createFromFormat('!'.$format, $value);
            $errors = DateTime::getLastErrors();
            if ($parsedDate !== false && ($errors === false || ($errors['warning_count'] == 0 && $errors['error_count'] == 0))) {
                $date = $parsedDate;
                break;
            }
        }

        if ($date === false) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return '';
            }
            $date = new DateTime(date('Y-m-d H:i:s', $timestamp));
        }

        if (!$hasTime) {
            $date->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0);
        } elseif ($endOfDay && !$hasSeconds) {
            $date->setTime((int) $date->format('H'), (int) $date->format('i'), 59);
        }

        return $date->format('Y-m-d H:i:s');
    }

    function listJobs($status,$job_name,$dimension,$reprocess,$eventText,$fromDate,$toDate,$environment) {

        $this->selectTmfRows();
        $this->db->from('tmf');
        $this->hideInternalJobs();

        $status = (array) $status;
        $job_name = (array) $job_name;
        $dimension = (array) $dimension;
        $environment = (array) $environment;
        $includeAllEnvironments = in_array('*', $environment, TRUE) || in_array('all', array_map('strtolower', array_map('strval', $environment)), TRUE);
        $includeUnknownEnvironment = in_array('__UNKNOWN__', $environment, TRUE) || in_array('unknown', array_map('strtolower', array_map('strval', $environment)), TRUE);
        $environmentValues = array();
        foreach ($environment as $environmentName) {
            foreach ($this->environmentFilterValues($environmentName) as $value) {
                if (! in_array($value, $environmentValues, TRUE)) {
                    $environmentValues[] = $value;
                }
            }
        }

    // Check Status
     if (!empty($status) && !in_array('*', $status, TRUE)) {
	        $this->db->where_in('status',$status);
	}

 	// Check Job Name
    if (!empty($job_name) && !in_array('*', $job_name, TRUE)) {
	        $this->db->where_in('job_name',$job_name);
     }

     // Check Dimension
     if (!empty($dimension) && !in_array('*', $dimension, TRUE)) {
	        $this->db->where_in('dimension',$dimension);
     }
        // Check Reprocess
        if($reprocess !== null && $reprocess !== '' && $reprocess !== "*"){
	        $this->db->where('reprocess',$reprocess);
        }

        // Check Environment
                  if (! $includeAllEnvironments && (! empty($environmentValues) || $includeUnknownEnvironment)) {
                      $this->db->group_start();
                      if (! empty($environmentValues)) {
                          $this->db->where_in('tmf.environment', $environmentValues);
                      }

                      if ($includeUnknownEnvironment) {
                       if (! empty($environmentValues)) {
                           $this->db->or_group_start();
                       } else {
                           $this->db->group_start();
                       }
                       $this->db->where('tmf.environment IS NULL', null, false);
                       $this->db->or_where('TRIM(tmf.environment) =', '');
                       $this->db->group_end();
                      }

                      $this->db->group_end();
                  }

        // Check Event Text
        if(trim((string) $eventText) !== ""){
        	$this->db->like('event_text',$eventText);
        }

        $fromDate = trim((string) $fromDate);
        $toDate = trim((string) $toDate);

        // Check Dates From date and To Date (Interval between dates)
        if ($fromDate !== '' || $toDate !== '') {
            $startDate = $this->parseFilterDate($fromDate, false);
            $endDate = $this->parseFilterDate($toDate, true);
            $startDate = $startDate !== '' ? $startDate : '2010-01-01 00:00:00';
            $endDate = $endDate !== '' ? $endDate : date('Y-m-d 23:59:59');
			$this->db->where('start_time >=',$startDate);
			$this->db->where('start_time <=',$endDate);
        }

       

        $this->db->order_by('id', 'DESC');
        return $this->boundedResults();
        
    }

    function list($environment = '') {

        $this->selectTmfRows();
        $this->db->from('tmf');
        $this->hideInternalJobs();
        $this->applyEnvironmentFilter($environment);
        $this->db->order_by('id', 'DESC');
        return $this->boundedResults();
    }

    function listId($id, $environment = '') {

        $this->selectTmfRows();
        $this->db->from('tmf');
        $this->hideInternalJobs();
        $this->db->where('id', $id);
        $this->applyEnvironmentFilter($environment);
        $query = $this->db->get();
        return $query->result();
    }

    function deletePolicy($id, $environment = '', $staleThresholdSeconds = 900) {

        $this->db->select('id,status,environment,last_activity');
        $this->db->from('tmf');
        $this->db->where('id', (int) $id);
        $this->applyEnvironmentFilter($environment);
        $query = $this->db->get();
        $row = $query->row();

        if (empty($row)) {
            return array(
                'exists' => false,
                'allowed' => false,
                'isDev' => false,
                'isStale' => false
            );
        }

        $this->db->select_max('last_activity', 'latest_activity');
        $latestQuery = $this->db->get('tmf');
        $latestRow = $latestQuery->row();
        $latestTimestamp = !empty($latestRow) ? strtotime((string) $latestRow->latest_activity) : false;
        $activityReferenceTimestamp = $latestTimestamp !== false ? max(time(), $latestTimestamp) : time();

        $environment = strtoupper(trim((string) $row->environment));
        $lastTimestamp = strtotime((string) $row->last_activity);
        $isDev = $environment === 'DEV';
        $isStale = strtolower((string) $row->status) === 'running'
            && $lastTimestamp !== false
            && ($activityReferenceTimestamp - $lastTimestamp) > (int) $staleThresholdSeconds;

        return array(
            'exists' => true,
            'allowed' => $isDev || $isStale,
            'isDev' => $isDev,
            'isStale' => $isStale
        );
    }

    function fetchDataStatus($status, $environment = '') {

        $this->selectTmfRows();
        $this->db->from('tmf');
        $this->hideInternalJobs();
        $this->db->where('status', strtolower((string) $status));
        $this->applyEnvironmentFilter($environment);
        $this->db->order_by('id', 'DESC');
        return $this->boundedResults();
    }

    function fetchDataJobName($jobName, $environment = '') {

        $this->selectTmfRows();
        $this->db->from('tmf');
        $this->hideInternalJobs();
        $this->db->where('job_name', $jobName);
        $this->applyEnvironmentFilter($environment);
        $this->db->order_by('id', 'DESC');
        return $this->boundedResults();
    }

    function listStatus($environment = '') {

        $this->db->select('status');
        $this->db->distinct();
        $this->db->from('tmf');
        $this->hideInternalJobs();
        $this->applyEnvironmentFilter($environment);
        $this->db->where('status IS NOT NULL', null, false);
        $query = $this->db->get();

        $statuses = array(
            'ready' => 'Ready',
            'running' => 'Running',
            'error' => 'Failed Runs',
            'warning' => 'Warnings',
            'cancelled' => 'Cancelled'
        );

        foreach ($query->result() as $record) {
            $status = trim((string) $record->status);
            if ($status !== '' && !array_key_exists($status, $statuses)) {
                $statuses[$status] = ucfirst($status);
            }
        }

        $result = array();
        foreach ($statuses as $status => $label) {
            $row = new stdClass();
            $row->status = $status;
            $row->status_label = $label;
            $result[] = $row;
        }

        return $result;
    }

    function listJobName($environment = '') {

        $this->db->select('job_name');
        $this->db->distinct();
        $this->db->from('tmf');
        $this->hideInternalJobs();
        $this->applyEnvironmentFilter($environment);
        $query = $this->db->get();
        return $query->result();
    }

    function listDimension($environment = '') {

        $this->db->select('dimension');
        $this->db->distinct();
        $this->db->from('tmf');
        $this->hideInternalJobs();
        $this->applyEnvironmentFilter($environment);
        $query = $this->db->get();
        return $query->result();
    }

    function listReprocess($environment = '') {
        $this->db->select('job_name,reprocess');
        $this->db->distinct();
        $this->db->from('tmf');
        $this->db->where('reprocess', 1);
        $this->hideInternalJobs();
        $this->applyEnvironmentFilter($environment);
        $query = $this->db->get();
        return $query->result();
    }

    function listEnvironment() {

     $query = $this->db->query('SELECT DISTINCT normalized_environment AS environment FROM (SELECT NULLIF(TRIM(environment), "") AS normalized_environment FROM tmf UNION SELECT NULLIF(TRIM(Environment), "") AS normalized_environment FROM environment WHERE IsActive = 1) env WHERE normalized_environment IS NOT NULL ORDER BY normalized_environment');
        return $query->result();
    }

    function getError($instanceId, $environment = '') {

        $this->db->select('tmf_error.*');
        $this->db->from('tmf_error');
        $this->db->where('tmf_id', $instanceId);
        $this->db->join('tmf', 'tmf_error.tmf_id = tmf.instance_id');
        $this->hideInternalJobs();
        $this->applyEnvironmentFilter($environment);
        $query = $this->db->get();
        return $query->result();
    }

    function updateUser($instanceId,$name) {

        $this->db->set('username', $name);
        $this->db->where('instance_id', $instanceId);
        $this->db->update('tmf');
    }

    function updateStatus($id,$status,$environment = '') {

        $this->db->set('status', $status);
        $this->db->where('id', $id);
        $this->applyEnvironmentFilter($environment, 'environment');
        $this->db->update('tmf');
    }


    function delete($id, $environment = '')
    {
        
        $this->db->where('id', $id);
        $this->applyEnvironmentFilter($environment, 'environment');
        $this->db->delete('tmf');

        
        return $this->db->affected_rows();
    }

    
}

?>
