<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Dashboard_model extends CI_Model
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

    private function environmentSql($environment, $prefix = 'WHERE') {
        $environment = $this->normalizeEnvironmentFilter($environment);

        if ($environment === '') {
            return '';
        }

        if ($environment === '__UNKNOWN__') {
            return ' '.$prefix.' (environment IS NULL OR TRIM(environment) = "")';
        }

        $conditions = array();
        foreach ($this->environmentFilterValues($environment) as $value) {
            $conditions[] = 'UPPER(TRIM(environment)) = '.$this->db->escape($value);
        }

        return ' '.$prefix.' ('.implode(' OR ', $conditions).')';
    }

	function getLastjobs($environment = '') {

        $this->db->select('status,job_name,event_text,records_processed,environment');
        $this->db->from('tmf');
        $this->applyEnvironmentFilter($environment);
        $this->db->order_by('id', 'DESC');
        $this->db->limit(6);
        $query = $this->db->get();
        return $query->result();
    }

    function listStatus($status, $environment = '') {

        $this->db->select('*');
        $this->db->from('tmf');
        $this->db->where('status', $status);
        $this->applyEnvironmentFilter($environment);
        $query = $this->db->get();
        return $query->num_rows();
    }

    function countAll($environment = ''){

        $this->db->from('tmf');
        $this->applyEnvironmentFilter($environment);
        $query = $this->db->get();
        return $query->num_rows();
    }

    function graphMonth($environment = ''){
	    $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf'.$this->environmentSql($environment).' GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function graphReady($environment = ''){
	    $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "READY"'.$this->environmentSql($environment, 'AND').' GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function graphError($environment = ''){
	    $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "ERROR"'.$this->environmentSql($environment, 'AND').' GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function graphWarning($environment = ''){
	    $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "WARNING"'.$this->environmentSql($environment, 'AND').' GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function graphRunning($environment = ''){
	    $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", COUNT(STATUS) "AMOUNT" FROM tmf WHERE UPPER(STATUS) = "RUNNING"'.$this->environmentSql($environment, 'AND').' GROUP BY STATUS, MONTH(last_activity)');
        return $query->result();
    }

    function months($environment = ''){
	    $query = $this->db->query('SELECT MONTHNAME(last_activity) "MONTH" FROM tmf'.$this->environmentSql($environment).' GROUP BY MONTH(last_activity) ORDER BY MIN(last_activity)');
        return $query->result();
    }

    function lastDate($environment = ''){
	    $query = $this->db->query('SELECT last_activity FROM tmf'.$this->environmentSql($environment).' ORDER BY last_activity DESC LIMIT 1');
        return $query->result();
    }

    function firstDate($environment = ''){
	    $query = $this->db->query('SELECT last_activity FROM tmf'.$this->environmentSql($environment).' ORDER BY last_activity ASC LIMIT 1');
        return $query->result();
    }

    function readyGrowth($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "READY"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 30 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function errorGrowth($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "ERROR"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 30 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function warningGrowth($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "WARNING"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 30 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function runningGrowth($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "RUNNING"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 30 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function readyGrowthX90($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "READY"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 90 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function errorGrowthX90($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "ERROR"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 90 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function warningGrowthX90($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "WARNING"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 90 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function runningGrowthX90($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "RUNNING"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 90 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function readyGrowthX180($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "READY"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 180 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function errorGrowthX180($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "ERROR"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 180 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function warningGrowthX180($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "WARNING"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 180 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function runningGrowthX180($environment = ''){
        $query = $this->db->query('SELECT UPPER(STATUS) "LABEL", MONTHNAME(last_activity) "MONTH", last_activity "DATE", COUNT(STATUS) "AMOUNT"  FROM tmf  WHERE UPPER(STATUS) = "RUNNING"'.$this->environmentSql($environment, 'AND').'  AND last_Activity BETWEEN NOW() - INTERVAL 180 DAY AND NOW() GROUP BY STATUS, MONTH(last_activity) ORDER BY DATE DESC');
        return $query->result();
    }

    function statusGraph($environment = ''){
        $query = $this->db->query('SELECT LOWER(STATUS) AS STATUS, COUNT(STATUS) "AMOUNT" FROM tmf'.$this->environmentSql($environment).' GROUP BY LOWER(STATUS) ORDER BY LOWER(STATUS)');
        return $query->result();
    }

    function jobsAmount($environment = '') {
	    $query = $this->db->query('SELECT JOB_NAME,DIMENSION,COALESCE(NULLIF(TRIM(environment), ""), "Unknown") AS ENVIRONMENT,COUNT(job_name) AS AMOUNT FROM tmf'.$this->environmentSql($environment).' GROUP BY job_name,dimension,COALESCE(NULLIF(TRIM(environment), ""), "Unknown") ORDER BY DIMENSION ASC, ENVIRONMENT ASC');
    	return $query->result();
    }

    function jobsStatusAmount($environment = '') {
	    $query = $this->db->query('SELECT JOB_NAME,DIMENSION,STATUS,COALESCE(NULLIF(TRIM(environment), ""), "Unknown") AS ENVIRONMENT,COUNT(STATUS) AS AMOUNT FROM tmf'.$this->environmentSql($environment).' GROUP BY JOB_NAME,DIMENSION,STATUS,COALESCE(NULLIF(TRIM(environment), ""), "Unknown") ORDER BY DIMENSION ASC, ENVIRONMENT ASC');
    	return $query->result();
    }

    function environmentSummary($environment = '') {
        $query = $this->db->query('SELECT COALESCE(NULLIF(TRIM(environment), ""), "Unknown") AS ENVIRONMENT, COUNT(*) AS AMOUNT, SUM(CASE WHEN LOWER(status) = "ready" THEN 1 ELSE 0 END) AS READY, SUM(CASE WHEN LOWER(status) = "running" THEN 1 ELSE 0 END) AS RUNNING, SUM(CASE WHEN LOWER(status) IN ("error", "warning") THEN 1 ELSE 0 END) AS ATTENTION, MAX(last_activity) AS LAST_ACTIVITY FROM tmf'.$this->environmentSql($environment).' GROUP BY COALESCE(NULLIF(TRIM(environment), ""), "Unknown") ORDER BY ATTENTION DESC, AMOUNT DESC, ENVIRONMENT ASC');
        return $query->result();
    }

    function stgTableAmount($environment = '') {
        $query = $this->db->query('SELECT id AS STG FROM tmf WHERE job_name LIKE UPPER("%STG%")'.$this->environmentSql($environment, 'AND'));
    	return $query->num_rows();
    }

    function dimTableAmount($environment = '') {
        $query = $this->db->query('SELECT id AS DIM FROM tmf WHERE job_name LIKE UPPER("%DIM%")'.$this->environmentSql($environment, 'AND'));
    	return $query->num_rows();
    }

     function factTableAmount($environment = '') {
        $query = $this->db->query('SELECT job_name AS FACT FROM tmf WHERE (job_name LIKE UPPER("%MET%") OR job_name LIKE UPPER("%METRIC%") OR job_name LIKE UPPER("%FATO%") OR job_name LIKE UPPER("%FAT%") OR job_name LIKE UPPER("%FACT%"))'.$this->environmentSql($environment, 'AND'));
    	return $query->num_rows();
    }

    function dwAmount($environment = '') {
        $query = $this->db->query('SELECT DIMENSION AS DW FROM tmf WHERE dimension LIKE UPPER("%DW%")'.$this->environmentSql($environment, 'AND'));
    	return $query->num_rows();
    }

    function dmAmount($environment = '') {
        $query = $this->db->query('SELECT DIMENSION AS DW FROM tmf WHERE dimension LIKE UPPER("%DM%")'.$this->environmentSql($environment, 'AND'));
    	return $query->num_rows();
    }

    function dmAmountExec($environment = '') {
        $query = $this->db->query('SELECT DIMENSION, COUNT(DIMENSION) AS AMOUNT FROM tmf WHERE dimension LIKE UPPER("%DW%")'.$this->environmentSql($environment, 'AND').' GROUP BY DIMENSION');
    	return $query->result();
    }

    function dimAmountExec($environment = '') {
        $query = $this->db->query('SELECT job_name AS DIM, COUNT(job_name) AS AMOUNT FROM tmf WHERE job_name LIKE UPPER("%DIM%")'.$this->environmentSql($environment, 'AND').' GROUP BY job_name');
        return $query->result();

    }

    function factAmountExec($environment = '') {
        $query = $this->db->query('SELECT job_name AS FACT, COUNT(job_name) AS AMOUNT FROM tmf WHERE (job_name LIKE UPPER("%MET%") OR job_name LIKE UPPER("%METRIC%") OR job_name LIKE UPPER("%FATO%") OR job_name LIKE UPPER("%FAT%") OR job_name LIKE UPPER("%FACT%"))'.$this->environmentSql($environment, 'AND').' GROUP BY job_name');
        return $query->result();

    }

    function stgAmountExec($environment = '') {
        $query = $this->db->query('SELECT job_name AS STG, COUNT(job_name) AS AMOUNT FROM tmf WHERE job_name LIKE UPPER("%STG%")'.$this->environmentSql($environment, 'AND').' GROUP BY job_name');
        return $query->result();

    }



    
}

?>