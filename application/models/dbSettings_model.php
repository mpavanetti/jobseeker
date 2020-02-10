<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Class : Login_model (Login Model)
 * Login model class to get to authenticate user credentials 
 * @author : Kishor Mali
 * @version : 1.1
 * @since : 15 November 2016
 */
class dbSettings_model extends CI_Model
{

    function listSettings() {

        $this->db->select('*');
        $this->db->from('database_settings');
        $query = $this->db->get();
        return $query->result();
    }

     // Validate if the record already exists.
     function validateSetting($job_name, $db_type, $login, $address, $port, $schema) {

        $this->db->select('*');
        $this->db->from('database_settings');
        $this->db->where('job_name', $job_name);
        $this->db->where('db_type', $db_type);
        $this->db->where('login', $login);
        $this->db->where('address', $address);
        $this->db->where('port', $port);
        $this->db->where('schema', $schema);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Insert record to DB.
    function insertDbSetting($Info)
    {
        $this->db->trans_start();
        $this->db->insert('database_settings', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    // Delete record from db
    function deleteSetting($id)
    {
        
        $this->db->where('id', $id);
		$this->db->delete('database_settings');

        
        return $this->db->affected_rows();
    }

    // Fetch data to input edit
    function EditSettingsFetchData($id = 0)
    {
        $this->db->where('id',$id);
        $sql = $this->db->get('database_settings');
        return $sql->row();
    }

    function updateDbSetting($Info, $id)
    {
        $this->db->where('id', $id);
        $this->db->update('database_settings', $Info);
        
        return TRUE;
    }

    
}

?>