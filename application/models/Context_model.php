<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Context_model extends CI_Model
{

    function listProjects() {

        $this->db->select('*');
        $this->db->from('projectdetails');
        $query = $this->db->get();
        return $query->result();
    }

    function listContexts() {

        $this->db->select('env.Environment,pd.ProjectName,cd.*');
        $this->db->from('ContextDetails cd');
        $this->db->join('environment env','env.id=cd.environmentFK');
        $this->db->join('projectdetails pd', 'pd.id=cd.projectdetailsFK');
        $query = $this->db->get();
        return $query->result();
    }

    function listContextId($Id) {

        $this->db->select('env.Environment,pd.ProjectName,cd.*');
        $this->db->from('ContextDetails cd');
        $this->db->join('environment env','env.id=cd.environmentFK');
        $this->db->join('projectdetails pd', 'pd.id=cd.projectdetailsFK');
        $this->db->where('cd.Id', $Id);
        $query = $this->db->get();
        return $query->result();
    }

    function listEnvironments() {

        $this->db->select('*');
        $this->db->from('environment');
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

    function listAvailableContexts() {

        $this->db->distinct();
        $this->db->select('ContextKey');
        $this->db->from('contextDetails');
        $query = $this->db->get();
        return $query->num_rows();
    }

    function listAvailableEnvironments() {

        $this->db->distinct();
        $this->db->select('environment');
        $this->db->from('environment');
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

    function listActiveContexts() {

        $this->db->select('IsActive');
        $this->db->from('contextDetails');
        $this->db->where('IsActive', '1');
        $query = $this->db->get();
        return $query->num_rows();
    }

    function listActiveEnvironments() {

        $this->db->select('IsActive');
        $this->db->from('environment');
        $this->db->where('IsActive', '1');
        $query = $this->db->get();
        return $query->num_rows();
    }

    function getProjectId($projectName) {

        $this->db->select('id');
        $this->db->from('projectdetails');
        $this->db->where('ProjectName', $projectName);
        $query = $this->db->get();
        return $query->result();
    }

    function getEnvironmentId($environmentName) {

        $this->db->select('id');
        $this->db->from('environment');
        $this->db->where('Environment', $environmentName);
        $query = $this->db->get();
        return $query->result();
    }


     // Validate if the record already exists.
     function validateProject($name) {

        $this->db->select('ProjectName');
        $this->db->from('projectdetails');
        $this->db->where('ProjectName', $name);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Validate if the record already exists.
     function validateEnvironment($name) {

        $this->db->select('Environment');
        $this->db->from('environment');
        $this->db->where('Environment', $name);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Validate if the record already exists.
     function validateContext($contextKey,$projectName,$environmentName) {

        $this->db->select('env.Environment,pd.ProjectName,cd.ContextKey');
        $this->db->from('ContextDetails cd');
        $this->db->join('environment env','env.id=cd.environmentFK');
        $this->db->join('projectdetails pd', 'pd.id=cd.projectdetailsFK');
        $this->db->where('cd.ContextKey', $contextKey);
        $this->db->where('pd.ProjectName', $projectName);
        $this->db->where('env.Environment', $environmentName);
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

    // Insert record to DB.
    function insertEnvironment($Info)
    {
        $this->db->trans_start();
        $this->db->insert('environment', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    // Insert record to DB.
    function insertContext($Info)
    {
        $this->db->trans_start();
        $this->db->insert('contextdetails', $Info);
        
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

    // Delete record from db
    function deleteEnvironment($id)
    {
        
        $this->db->where('Id', $id);
        $this->db->delete('environment');

        
        return $this->db->affected_rows();
    }

    // Delete record from db
    function deleteContext($id)
    {
        
        $this->db->where('Id', $id);
        $this->db->delete('contextdetails');

        
        return $this->db->affected_rows();
    }

    // Fetch data to input edit
    function getProject($id = 0)
    {
        $this->db->where('Id',$id);
        $sql = $this->db->get('projectdetails');
        return $sql->row();
    }

    // Fetch data to input edit
    function getEnvironment($id = 0)
    {
        $this->db->where('Id',$id);
        $sql = $this->db->get('environment');
        return $sql->row();
    }

    function updateProjectSetting($Info, $Id)
    {
        $this->db->where('Id', $Id);
        $this->db->update('projectdetails', $Info);
        
        return TRUE;
    }

    function updateEnvironment($Info, $Id)
    {
        $this->db->where('Id', $Id);
        $this->db->update('environment', $Info);
        
        return TRUE;
    }

    function updatedContext($Info, $Id)
    {
        $this->db->where('Id', $Id);
        $this->db->update('ContextDetails', $Info);
        
        return TRUE;
    }

    
}

?>