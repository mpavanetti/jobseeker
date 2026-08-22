<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Tmf_model extends CI_Model
{

    private function parseFilterDate($value, $endOfDay)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $date = DateTime::createFromFormat('d-m-Y', $value);
        if ($date === false) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return '';
            }
            $date = new DateTime(date('Y-m-d', $timestamp));
        }

        $date->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0);
        return $date->format('Y-m-d H:i:s');
    }

    function listJobs($status,$job_name,$dimension,$reprocess,$eventText,$fromDate,$toDate,$environment) {

        $this->db->select('*');
        $this->db->from('tmf');

        $status = (array) $status;
        $job_name = (array) $job_name;
        $dimension = (array) $dimension;
        $environment = (array) $environment;

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
                    $this->db->where_in('environment',$environment);
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

    function fetchDataStatus($status) {

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->where('status', $status);
        $this->db->order_by('id', 'DESC');
        $query = $this->db->get();
        return $query->result();
    }

    function fetchDataJobName($jobName) {

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->where('job_name', $jobName);
        $this->db->order_by('id', 'DESC');
        $query = $this->db->get();
        return $query->result();
    }

    function listStatus() {

        $this->db->select('status');
        $this->db->distinct();
        $this->db->from('tmf');
        $query = $this->db->get();
        return $query->result();
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

     $query = $this->db->query('SELECT DISTINCT(environment) FROM tmf');
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