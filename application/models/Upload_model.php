<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


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

    function listComponentType($jobname, $component) {

        $this->db->select('component_type');
        $this->db->from('job_info');
        $this->db->where('job_name', $jobname);
        $this->db->where('job_component', $component);

        $query = $this->db->get();
        return $query->result();
    }

    function listComponentPath($jobname, $component, $type) {

        $this->db->select('*');
        $this->db->from('job_info');
        $this->db->where('job_name', $jobname);
        $this->db->where('job_component', $component);
        $this->db->where('component_type', $type);

        $query = $this->db->get();
        return $query->result();
    }

    function listAll($jobname, $component, $type) {

        $this->db->select('file_name');
        $this->db->from('job_info');
        $this->db->where('job_name', $jobname);
        $this->db->where('job_component', $component);
        $this->db->where('component_type', $type);

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

     function countFileUploaded() {
       $this->db->select_sum('file_uploaded');
        $query = $this->db->get('job_info'); // Produces: SELECT SUM(age) as age FROM members
        return $query->result();
    }

   
    function Path($jobname, $component, $type) {

        $this->db->select('file');
        $this->db->from('job_info');
        $this->db->where('job_name', $jobname);
        $this->db->where('job_component', $component);
        $this->db->where('component_type', $type);

        $query = $this->db->get();
        return $query->result();
    }

    function FetchAll($jobname, $component, $type) {

        $this->db->select('*');
        $this->db->from('job_info');
        $this->db->where('job_name', $jobname);
        $this->db->where('job_component', $component);
        $this->db->where('component_type', $type);

        $query = $this->db->get();
        return $query->result();
    }

    function add($jobname, $component, $type, $amount) {

        $data = array(
            'file_uploaded' => $amount,
        );

        $this->db->where('job_name', $jobname);
        $this->db->where('job_component', $component);
        $this->db->where('component_type', $type);

        $this->db->update('job_info', $data);

      }

      function fetchUploaded ($jobname, $component, $type) {

        $this->db->select('file_uploaded');
        $this->db->from('job_info');
        $this->db->where('job_name', $jobname);
        $this->db->where('job_component', $component);
        $this->db->where('component_type', $type);

        $query = $this->db->get();
        return $query->result();

      }
    
}

?>