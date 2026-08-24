<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Tmf_model extends CI_Model
{

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

        $values = $this->environmentFilterValues($environment);
        $this->db->group_start();
        foreach ($values as $index => $value) {
            if ($index === 0) {
                $this->db->where('UPPER(TRIM(environment)) =', $value);
            } else {
                $this->db->or_where('UPPER(TRIM(environment)) =', $value);
            }
        }
        $this->db->group_end();
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

        $this->db->select('*');
        $this->db->from('tmf');

        $status = (array) $status;
        $job_name = (array) $job_name;
        $dimension = (array) $dimension;
        $environment = (array) $environment;
        $includeUnknownEnvironment = in_array('__UNKNOWN__', $environment, TRUE);
        $environment = array_values(array_filter($environment, function($value) {
            $value = trim((string) $value);
            return $value !== '' && $value !== '__UNKNOWN__';
        }));

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
                  if (!empty($environment) && !in_array('*', $environment, TRUE)) {
                      $this->db->group_start();
                      $this->db->where_in('environment',$environment);

                      if ($includeUnknownEnvironment) {
                       $this->db->or_group_start();
                       $this->db->where('environment IS NULL', null, false);
                       $this->db->or_where('TRIM(environment) =', '');
                       $this->db->group_end();
                      }

                      $this->db->group_end();
                  } elseif ($includeUnknownEnvironment && !in_array('*', $environment, TRUE)) {
                      $this->db->group_start();
                      $this->db->where('environment IS NULL', null, false);
                      $this->db->or_where('TRIM(environment) =', '');
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
        $query = $this->db->get();
        return $query->result();
        
    }

    function list() {

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->order_by('id', 'DESC');
        $query = $this->db->get();
        return $query->result();
    }

    function listId($id) {

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->where('id', $id);
        $query = $this->db->get();
        return $query->result();
    }

    function deletePolicy($id, $staleThresholdSeconds = 900) {

        $this->db->select('id,status,environment,last_activity');
        $this->db->from('tmf');
        $this->db->where('id', (int) $id);
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

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->where('LOWER(status) =', strtolower((string) $status));
        $this->applyEnvironmentFilter($environment);
        $this->db->order_by('id', 'DESC');
        $query = $this->db->get();
        return $query->result();
    }

    function fetchDataJobName($jobName, $environment = '') {

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->where('job_name', $jobName);
        $this->applyEnvironmentFilter($environment);
        $this->db->order_by('id', 'DESC');
        $query = $this->db->get();
        return $query->result();
    }

    function listStatus() {

        $this->db->select('status');
        $this->db->distinct();
        $this->db->from('tmf');
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

    function listJobName() {

        $this->db->select('job_name');
        $this->db->distinct();
        $this->db->from('tmf');
        $query = $this->db->get();
        return $query->result();
    }

    function listDimension() {

        $this->db->select('dimension');
        $this->db->distinct();
        $this->db->from('tmf');
        $query = $this->db->get();
        return $query->result();
    }

    function listReprocess() {

     $query = $this->db->query('SELECT DISTINCT(JOB_NAME),REPROCESS FROM tmf WHERE REPROCESS = 1');
        return $query->result();
    }

    function listEnvironment() {

     $query = $this->db->query('SELECT DISTINCT normalized_environment AS environment FROM (SELECT NULLIF(TRIM(environment), "") AS normalized_environment FROM tmf UNION SELECT NULLIF(TRIM(Environment), "") AS normalized_environment FROM environment WHERE IsActive = 1) env WHERE normalized_environment IS NOT NULL ORDER BY normalized_environment');
        return $query->result();
    }

    function getError($instanceId) {

        $this->db->select('tmf_error.*');
        $this->db->from('tmf_error');
        $this->db->where('tmf_id', $instanceId);
        $this->db->join('tmf', 'tmf_error.tmf_id = tmf.instance_id');
        $query = $this->db->get();
        return $query->result();
    }

    function updateUser($instanceId,$name) {

        $this->db->set('username', $name);
        $this->db->where('instance_id', $instanceId);
        $this->db->update('tmf');
    }

    function updateStatus($id,$status) {

        $this->db->set('status', $status);
        $this->db->where('id', $id);
        $this->db->update('tmf');
    }


    function delete($id)
    {
        
        $this->db->where('id', $id);
        $this->db->delete('tmf');

        
        return $this->db->affected_rows();
    }

    
}

?>