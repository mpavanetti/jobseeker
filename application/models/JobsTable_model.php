<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class JobsTable_model extends CI_Model
{

    function listJobs() {

        $this->db->select('*');
        $this->db->from('job_info');
        $query = $this->db->get();
        return $query->result();
    }

    function listJobsName() {

        $this->db->select('job_name');
        $this->db->from('job_info');
        $query = $this->db->get();
        return $query->result();
    }

    function listOutputComponents() {

        $this->db->select('*');
        $this->db->from('job_output');
        $query = $this->db->get();
        return $query->result();
    }


    function deleteUser($id)
    {
        
        $this->db->where('id', $id);
		$this->db->delete('job_info');

        
        return $this->db->affected_rows();
    }

    function deleteOutput($id)
    {
        
        $this->db->where('id', $id);
        $this->db->delete('job_output');

        
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

    function addNewOutputComponentInsert($Info)
    {
        $this->db->trans_start();
        $this->db->insert('job_output', $Info);
        
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

     function getJobsOutput($id = 0)
    {
        $this->db->where('id',$id);
        $sql = $this->db->get('job_output');
        //return $sql->result();
        return $sql->row();
    }


    function editUser($Info, $id)
    {
        $this->db->where('id', $id);
        $this->db->update('job_info', $Info);
        
        return TRUE;
    }

     function editOutput($Info, $id)
    {
        $this->db->where('id', $id);
        $this->db->update('job_output', $Info);
        
        return TRUE;
    }

    // Validations
     function validateComponent($job_name, $job_component) {

        $this->db->select('*');
        $this->db->from('job_info');
        $this->db->where('job_name', $job_name);
        $this->db->where('job_component', $job_component);

        $query = $this->db->get();
        return $query->num_rows();
    }

    // Validations
     function validateOutputComponent($job_name, $job_component) {

        $this->db->select('*');
        $this->db->from('job_output');
        $this->db->where('job_name', $job_name);
        $this->db->where('job_component', $job_component);

        $query = $this->db->get();
        return $query->num_rows();
    }

    
}

?>