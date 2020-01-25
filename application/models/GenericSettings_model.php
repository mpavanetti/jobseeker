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

     // Validate if the record already exists.
     function validateSetting($job_name, $setting) {

        $this->db->select('*');
        $this->db->from('generic_settings');
        $this->db->where('job_name', $job_name);
        $this->db->where('setting', $setting);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Insert record to DB.
    function insertGenericSetting($Info)
    {
        $this->db->trans_start();
        $this->db->insert('generic_settings', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    // Delete record from db
    function deleteGenericSetting($id)
    {
        
        $this->db->where('id', $id);
		$this->db->delete('generic_settings');

        
        return $this->db->affected_rows();
    }

    // Fetch data to input edit
    function EditSettingsFetchData($id = 0)
    {
        $this->db->where('id',$id);
        $sql = $this->db->get('generic_settings');
        return $sql->row();
    }

    function updateGenericSetting($Info, $id)
    {
        $this->db->where('id', $id);
        $this->db->update('generic_settings', $Info);
        
        return TRUE;
    }

    
}

?>