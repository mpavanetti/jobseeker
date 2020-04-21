<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Tmf_model extends CI_Model
{

    function listJobs($status,$job_name,$dimension,$reprocess,$eventText,$fromDate,$toDate) {

        $this->db->select('*');
        $this->db->from('tmf');

    // Check Status
     if ($status != null) {    
        if($status[0] == "*"){
        } else {
        	$this->db->where_in('status',$status);
        } 
 	}

 	// Check Job Name
    if ($job_name != null) {
        if($job_name[0] == "*"){
        } else {
        	$this->db->where_in('job_name',$job_name);
        }
     }

     // Check Dimension
     if ($dimension != null) {
        if($dimension[0] == "*"){
        } else {
        	$this->db->where_in('dimension',$dimension);
        }
     }
        // Check Reprocess
        if($reprocess == "*"){
        } else {
        	$this->db->where_in('reprocess',$reprocess);
        }

        // Check Event Text
        if($eventText == " " || $eventText == null || $eventText == ""){
        } else {
        	$this->db->like('event_text',$eventText);
        }

         // Check Dates From date up to today (Interval between dates)
       
        if ($fromDate != null  && $toDate == null) {
        	$fromDate = date('Y-m-d 00:00:00', strtotime($fromDate));
        	$toDate =  date('Y-m-d 23:59:59');
			$this->db->where('start_time >=',$fromDate);
			$this->db->where('start_time <=',$toDate); 

        } else {}

        // Check Dates From Inicial date up to toDate (Interval between dates)
       
        if ($fromDate == null  && $toDate != null) {
        	$fromDate = date('2010-01-01 00:00:00');
        	$toDate =  date('Y-m-d 23:59:59', strtotime($toDate));
			$this->db->where('start_time >=',$fromDate);
			$this->db->where('start_time <=',$toDate); 

        } else {}
        

        // Check Dates From date and To Date (Interval between dates)
      
        if ($fromDate == null  && $toDate == null) {
        } else {
        	$fromDate = date('Y-m-d 00:00:00', strtotime($fromDate));
        	$toDate =  date('Y-m-d 23:59:59', strtotime($toDate));
			$this->db->where('start_time >=',$fromDate);
			$this->db->where('start_time <=',$toDate);
        }

       

        $query = $this->db->get();
        return $query->result();
        
    }

    function list() {

        $this->db->select('*');
        $this->db->from('tmf');
        $query = $this->db->get();
        return $query->result();
    }

    function fetchDataStatus($status) {

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->where('status', $status);
        $query = $this->db->get();
        return $query->result();
    }

    function fetchDataJobName($jobName) {

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->where('job_name', $jobName);
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

    function getError($instanceId) {

        $this->db->select('tmf_error.*');
        $this->db->from('tmf_error');
        $this->db->where('tmf_id', $instanceId);
        $this->db->join('tmf', 'tmf_error.tmf_id = tmf.instance_id');
        $query = $this->db->get();
        return $query->result();
    }

    function updateUser($instanceId,$name) {

    	$this->db->set('username', $name, FALSE);
		$this->db->where('instance_id', $instanceId);
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