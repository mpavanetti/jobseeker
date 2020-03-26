<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Tmf_model extends CI_Model
{

    function listJobs() {

        $this->db->select('*');
        $this->db->from('tmf');
        $query = $this->db->get();
        return $query->result();
    }

    
}

?>