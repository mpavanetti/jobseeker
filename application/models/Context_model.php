<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Context_model extends CI_Model
{

    function listProjects() {

        $this->db->select('*');
        $this->db->from('projectdetails');
        $query = $this->db->get();
        return $query->result();
    }

     function listAvailableProjects() {

        $this->db->distinct();
        $this->db->select('ProjectName');
        $this->db->from('projectdetails');
        $query = $this->db->get();
        return $query->num_rows();
    }

    function listActiveProjects() {

        $this->db->select('IsActive');
        $this->db->from('projectdetails');
        $this->db->where('IsActive', '1');
        $query = $this->db->get();
        return $query->num_rows();
    }


     // Validate if the record already exists.
     function validateProject($name) {

        $this->db->select('ProjectName');
        $this->db->from('projectdetails');
        $this->db->where('ProjectName', $name);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Insert record to DB.
    function insertProject($Info)
    {
        $this->db->trans_start();
        $this->db->insert('projectdetails', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    // Delete record from db
    function deleteProject($id)
    {
        
        $this->db->where('Id', $id);
		$this->db->delete('projectdetails');

        
        return $this->db->affected_rows();
    }

    // Fetch data to input edit
    function getProject($id = 0)
    {
        $this->db->where('Id',$id);
        $sql = $this->db->get('projectdetails');
        return $sql->row();
    }

    function updateProjectSetting($Info, $Id)
    {
        $this->db->where('Id', $Id);
        $this->db->update('projectdetails', $Info);
        
        return TRUE;
    }

    
}

?>