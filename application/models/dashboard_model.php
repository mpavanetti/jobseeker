<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Dashboard_model extends CI_Model
{

	function getLastjobs() {

        $this->db->select('status,job_name,event_text,records_processed');
        $this->db->from('tmf');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(5);
        $query = $this->db->get();
        return $query->result();
    }

    function listStatus($status) {

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->where('status', $status);
        $query = $this->db->get();
        return $query->num_rows();
    }

    function countAll(){

        $this->db->from('tmf');
        $query = $this->db->get();
        return $query->num_rows();
    }

    function graphMonth(){
    	$query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function graphReady(){
    	$query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "READY" GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function graphError(){
    	$query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "ERROR" GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function graphWarning(){
    	$query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "WARNING" GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function graphRunning(){
    	$query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "RUNNING" GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function months(){
    	$query = $this->db->query('SELECT  MONTHNAME(last_activity) "MONTH"  FROM tmf WHERE UPPER(STATUS) = "READY" GROUP BY STATUS, MONTH(last_activity)  UNION SELECT  MONTHNAME(last_activity) "MONTH"  FROM tmf WHERE UPPER(STATUS) = "ERROR" GROUP BY STATUS, MONTH(last_activity)  UNION SELECT  MONTHNAME(last_activity) "MONTH" FROM tmf WHERE UPPER(STATUS) = "WARNING" GROUP BY STATUS, MONTH(last_activity) UNION SELECT  MONTHNAME(last_activity) "MONTH"  FROM tmf WHERE UPPER(STATUS) = "RUNNING" GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function lastDate(){
    	$query = $this->db->query('SELECT last_activity FROM tmf ORDER BY last_activity DESC LIMIT 1');
        return $query->result();
    }

    function firstDate(){
    	$query = $this->db->query('SELECT last_activity FROM tmf ORDER BY last_activity ASC LIMIT 1');
        return $query->result();
    }

    function readyGrowth(){
    	$query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "READY" AND MONTH(last_Activity) - 1 GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function errorGrowth(){
    	$query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "ERROR" AND MONTH(last_Activity) - 1 GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function warningGrowth(){
    	$query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "WARNING" AND MONTH(last_Activity) - 1 GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function runningGrowth(){
    	$query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "RUNNING" AND MONTH(last_Activity) - 1 GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function statusGraph(){
    	$query = $this->db->query('SELECT STATUS, COUNT(STATUS) "AMOUNT" FROM tmf GROUP BY STATUS');
        return $query->result();
    }



    
}

?>