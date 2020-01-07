<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Class : Login_model (Login Model)
 * Login model class to get to authenticate user credentials 
 * @author : Kishor Mali
 * @version : 1.1
 * @since : 15 November 2016
 */
class JobsTable_model extends CI_Model
{

    function listJobs() {

        $this->db->select('*');
        $this->db->from('job_info');
        $query = $this->db->get();
        return $query->result();
    }


    function deleteUser($id)
    {
        
        $this->db->where('id', $id);
		$this->db->delete('job_info');

        
        return $this->db->affected_rows();
    }
    
}

?>