<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Visualization_model extends CI_Model
{

    function list() {

        $this->db->select('*');
        $this->db->from('reports');
        $query = $this->db->get();
        return $query->result();
    }

    function fetch($id) {

        $this->db->select('*');
        $this->db->from('reports');
        $this->db->where('id', $id);
        $query = $this->db->get();
        return $query->result();
    }

    function view($name) {

        $this->db->select('name,type,code');
        $this->db->from('reports');
        $this->db->where('name', $name);
        $query = $this->db->get();
        return $query->result();
    }

     function listReports() {

        $this->db->distinct();
        $this->db->select('name');
        $this->db->from('reports');
        $query = $this->db->get();
        return $query->num_rows();
    }

    function listTypes() {

        $this->db->distinct();
        $this->db->select('type');
        $this->db->from('reports');
        $query = $this->db->get();
        return $query->num_rows();
    }

    function listUsers() {
        
        $this->db->distinct();
        $this->db->select('name');
        $this->db->from('tbl_users');
        $query = $this->db->get();
        return $query->result();
    }

    function listGroups() {
        
        $this->db->distinct();
        $this->db->select('name');
        $this->db->from('tbl_groups');
        $query = $this->db->get();
        return $query->result();
    }

     // Validate if the record already exists.
     function validate($name) {

        $this->db->select('*');
        $this->db->from('reports');
        $this->db->where('name', $name);
        $query = $this->db->get();
        return $query->num_rows();
    }

      // Validate if the record already exists.
     function permission($name,$user) {

        $query = $this->db->query("SELECT NAME 'report',type FROM reports r WHERE r.name = '$name' AND r.groups = (SELECT DISTINCT g.name 'group' FROM tbl_users u, tbl_groups g WHERE u.groupId = g.id AND u.name ='$user') UNION SELECT NAME 'report',type FROM reports r WHERE r.users LIKE '%*%'");
        return $query->num_rows();
    }


     function allowedUser($user) {

        $query = $this->db->query("SELECT NAME 'report',type FROM reports r WHERE r.groups = (SELECT DISTINCT g.name 'group' FROM tbl_users u, tbl_groups g WHERE u.groupId = g.id AND u.name ='$user') UNION SELECT NAME 'report',type FROM reports r WHERE r.users LIKE '%*%'");
        return $query->result();
    }

    function allowedGroup($user) {

        $this->db->select('name,users');
        $this->db->from('reports');
        $this->db->like('name', $user);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Insert record to DB.
    function insert($Info)
    {
        $this->db->trans_start();
        $this->db->insert('reports', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    // Delete record from db
    function delete($id)
    {
        
        $this->db->where('id', $id);
		$this->db->delete('reports');

        
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