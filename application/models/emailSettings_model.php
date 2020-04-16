<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class emailSettings_model extends CI_Model
{

    function listSettings() {

        $this->db->select('*');
        $this->db->from('email_settings');
        $query = $this->db->get();
        return $query->result();
    }

    function fetch($id) {

        $this->db->select('*');
        $this->db->from('email_settings');
        $this->db->where('id', $id);
        $query = $this->db->get();
        return $query->result();
    }

    function fetchSMTP() {

        $this->db->select('*');
        $this->db->from('smtp_settings');
        $query = $this->db->get();
        return $query->result();
    }

    function fetchXsmtp($id) {

        $this->db->select('email_settings.*, smtp_settings.smtp_host, smtp_settings.smtp_port, smtp_settings.username, smtp_settings.password, smtp_settings.ssl');
        $this->db->from('email_settings');
        $this->db->where('email_settings.id', $id);
        $this->db->join('smtp_settings', 'smtp_settings.name = email_settings.smtp');
        $query = $this->db->get();
        return $query->result();
    }

     // Validate if the record already exists.
     function validateSetting($name, $to, $from, $subject) {

        $this->db->select('*');
        $this->db->from('email_settings');
        $this->db->where('name', $name);
        $this->db->where('to', $to);
        $this->db->where('from', $from);
        $this->db->where('subject', $subject);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Insert record to DB.
    function insertDbSetting($Info)
    {
        $this->db->trans_start();
        $this->db->insert('email_settings', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    // Delete record from db
    function deleteSetting($id)
    {
        
        $this->db->where('id', $id);
		$this->db->delete('email_settings');

        
        return $this->db->affected_rows();
    }

    // Fetch data to input edit
    function EditSettingsFetchData($id = 0)
    {
        $this->db->where('id',$id);
        $sql = $this->db->get('email_settings');
        return $sql->row();
    }

    function updateDbSetting($Info, $id)
    {
        $this->db->where('id', $id);
        $this->db->update('email_settings', $Info);
        
        return TRUE;
    }

    
}

?>