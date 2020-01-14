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


    function addNewUserInsert($Info)
    {
        $this->db->trans_start();
        $this->db->insert('job_info', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    function getJobs($id = 0)
    {
        $this->db->where('id',$id);
        $sql = $this->db->get('job_info');
        //return $sql->result();
        return $sql->row();
    }


    function editUser($Info, $id)
    {
        $this->db->where('id', $id);
        $this->db->update('job_info', $Info);
        
        return TRUE;
    }

    // Validations
     function validateComponent($job_name, $job_component, $file_path) {

        $this->db->select('*');
        $this->db->from('job_info');
        $this->db->where('job_name', $job_name);
        $this->db->where('job_component', $job_component);
        $this->db->where('file_path', $file_path);

        $query = $this->db->get();
        return $query->num_rows();
    }

    
}

?>