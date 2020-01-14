<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Class : Login_model (Login Model)
 * Login model class to get to authenticate user credentials 
 * @author : Kishor Mali
 * @version : 1.1
 * @since : 15 November 2016
 */
class GenericSettings_model extends CI_Model
{

    function listSettings() {

        $this->db->select('*');
        $this->db->from('generic_settings');
        $query = $this->db->get();
        return $query->result();
    }

    
}

?>