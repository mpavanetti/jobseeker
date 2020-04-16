<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class smtpSettings_model extends CI_Model
{

    function listSettings() {

        $this->db->select('*');
        $this->db->from('smtp_settings');
        $query = $this->db->get();
        return $query->result();
    }

     // Validate if the record already exists.
     function validateSetting($name, $smtp_host, $smtp_port) {

        $this->db->select('*');
        $this->db->from('smtp_settings');
        $this->db->where('name', $name);
        $this->db->where('smtp_host', $smtp_host);
        $this->db->where('smtp_port', $smtp_port);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Insert record to DB.
    function insertSetting($Info)
    {
        $this->db->trans_start();
        $this->db->insert('smtp_settings', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    // Delete record from db
    function deleteSetting($id)
    {
        
        $this->db->where('id', $id);
		$this->db->delete('smtp_settings');

        
        return $this->db->affected_rows();
    }

    // Fetch data to input edit
    function EditSettingsFetchData($id = 0)
    {
        $this->db->where('id',$id);
        $sql = $this->db->get('smtp_settings');
        return $sql->row();
    }

    function updateSetting($Info, $id)
    {
        $this->db->where('id', $id);
        $this->db->update('smtp_settings', $Info);
        
        return TRUE;
    }

    
}

?>