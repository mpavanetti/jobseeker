<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Class : Login_model (Login Model)
 * Login model class to get to authenticate user credentials 
 * @author : Kishor Mali
 * @version : 1.1
 * @since : 15 November 2016
 */
class Upload_model extends CI_Model
{

    function listJobs() {

        $this->db->select('job_name');
        $this->db->distinct();
        $this->db->from('job_info');
        $query = $this->db->get();
        return $query->result();
    }

     function listComponents($jobname) {

        $this->db->select('job_component');
        $this->db->from('job_info');
        $this->db->where('job_name', $jobname);
        $this->db->like('job_component','Input');

        $query = $this->db->get();
        return $query->result();
    }

    function listComponentType($component) {

        $this->db->select('component_type');
        $this->db->from('job_info');
        $this->db->where('job_component', $component);
        $this->db->or_where_in('component_type','xml','json','excel','csv','txt');

        $query = $this->db->get();
        return $query->result();
    }

    function countJobs() {

        $this->db->select('id');
        $this->db->from('job_info');
        $this->db->group_by("job_name");
        $query = $this->db->get();

        return $query->num_rows();
    }

    function countComponents() {

        $this->db->select('job_component');
        $this->db->from('job_info');
        $query = $this->db->get();
        return $query->num_rows();
    }

     function countComponentsTypes() {
        $this->db->select('component_type');
        $this->db->distinct();
        $this->db->from('job_info');
        $query = $this->db->get();
        return $query->num_rows();
    }
    
}

?>