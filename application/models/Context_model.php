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
        $this->db->from('contextdetails cd');
        $this->db->join('environment env','env.id=cd.environmentFK');
        $this->db->join('projectdetails pd', 'pd.id=cd.projectdetailsFK');
        $query = $this->db->get();
        return $query->result();
    }

    function listContextId($Id) {

        $this->db->select('env.Environment,pd.ProjectName,cd.*');
        $this->db->from('contextdetails cd');
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
        $this->db->from('contextdetails');
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
        $this->db->from('contextdetails');
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

    function validateProjectExcept($name, $projectId) {

        $this->db->select('Id');
        $this->db->from('projectdetails');
        $this->db->where('ProjectName', $name);
        $this->db->where('Id !=', (int) $projectId);
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

    function validateEnvironmentExcept($name, $environmentId) {

        $this->db->select('Id');
        $this->db->from('environment');
        $this->db->where('Environment', $name);
        $this->db->where('Id !=', (int) $environmentId);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Validate if the record already exists.
     function validateContext($contextKey,$projectName,$environmentName) {

        $this->db->select('env.Environment,pd.ProjectName,cd.ContextKey');
        $this->db->from('contextdetails cd');
        $this->db->join('environment env','env.id=cd.environmentFK');
        $this->db->join('projectdetails pd', 'pd.id=cd.projectdetailsFK');
        $this->db->where('cd.ContextKey', $contextKey);
        $this->db->where('pd.ProjectName', $projectName);
        $this->db->where('env.Environment', $environmentName);
        $query = $this->db->get();
        return $query->num_rows();
    }

    // Validate uniqueness while allowing the record currently being edited.
    function validateContextExcept($contextKey, $projectName, $environmentName, $contextId) {

        $this->db->select('cd.Id');
        $this->db->from('contextdetails cd');
        $this->db->join('environment env', 'env.id=cd.environmentFK');
        $this->db->join('projectdetails pd', 'pd.id=cd.projectdetailsFK');
        $this->db->where('cd.ContextKey', $contextKey);
        $this->db->where('pd.ProjectName', $projectName);
        $this->db->where('env.Environment', $environmentName);
        $this->db->where('cd.Id !=', (int) $contextId);
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

    function listContextsByProjectEnvironment($projectId, $environmentId)
    {
        $this->db->select('*');
        $this->db->from('contextdetails');
        $this->db->where('ProjectDetailsFK', $projectId);
        $this->db->where('EnvironmentFK', $environmentId);
        $this->db->order_by('ContextKey', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }

    function findContextByKeyProjectEnvironment($contextKey, $projectId, $environmentId)
    {
        $this->db->select('*');
        $this->db->from('contextdetails');
        $this->db->where('ContextKey', $contextKey);
        $this->db->where('ProjectDetailsFK', $projectId);
        $this->db->where('EnvironmentFK', $environmentId);
        $query = $this->db->get();
        return $query->row();
    }

    function promoteContexts($projectId, $sourceEnvironmentId, $targetEnvironmentId, $user, $overwrite)
    {
        $sourceContexts = $this->listContextsByProjectEnvironment($projectId, $sourceEnvironmentId);
        $result = array('total' => count($sourceContexts), 'created' => 0, 'updated' => 0, 'skipped' => 0);

        $this->db->trans_start();

        foreach ($sourceContexts as $context) {
            $existingContext = $this->findContextByKeyProjectEnvironment($context->ContextKey, $projectId, $targetEnvironmentId);

            $contextInfo = array(
                'ContextKey' => $context->ContextKey,
                'ContextValue' => $context->ContextValue,
                'IsActive' => $context->IsActive,
                'IsEncrypted' => $context->isEncrypted,
                'EnvironmentFK' => $targetEnvironmentId,
                'ProjectDetailsFK' => $projectId,
                'Description' => $context->Description,
            );

            if (!empty($existingContext)) {
                if (!$overwrite) {
                    $result['skipped']++;
                    continue;
                }

                $contextInfo['ModifiedBy'] = $user;
                $contextInfo['ModifiedOn'] = date('Y-m-d H:i:s');

                $this->db->where('Id', $existingContext->Id);
                $this->db->update('contextdetails', $contextInfo);
                $result['updated']++;
                continue;
            }

            $contextInfo['CreatedBy'] = $user;
            $contextInfo['CreatedOn'] = date('Y-m-d H:i:s');
            $this->db->insert('contextdetails', $contextInfo);
            $result['created']++;
        }

        $this->db->trans_complete();

        return $result;
    }

    function snapshotContextsForPromotion($projectId, $sourceEnvironmentId, $targetEnvironmentId)
    {
        $sourceContexts = $this->listContextsByProjectEnvironment($projectId, $sourceEnvironmentId);
        $snapshot = array('total' => count($sourceContexts), 'rows' => array());

        foreach ($sourceContexts as $context) {
            $existingContext = $this->findContextByKeyProjectEnvironment($context->ContextKey, $projectId, $targetEnvironmentId);
            $row = array(
                'context_key' => $context->ContextKey,
                'project_id' => (int) $projectId,
                'target_environment_id' => (int) $targetEnvironmentId,
                'existed' => !empty($existingContext)
            );

            if (!empty($existingContext)) {
                $row['record'] = array(
                    'Id' => (int) $existingContext->Id,
                    'ContextKey' => $existingContext->ContextKey,
                    'ContextValue' => $existingContext->ContextValue,
                    'IsActive' => $existingContext->IsActive,
                    'IsEncrypted' => $existingContext->isEncrypted,
                    'EnvironmentFK' => $existingContext->EnvironmentFK,
                    'ProjectDetailsFK' => $existingContext->ProjectDetailsFK,
                    'Description' => $existingContext->Description,
                    'CreatedBy' => $existingContext->CreatedBy,
                    'CreatedOn' => $existingContext->CreatedOn,
                    'ModifiedBy' => $existingContext->ModifiedBy,
                    'ModifiedOn' => $existingContext->ModifiedOn
                );
            }

            $snapshot['rows'][] = $row;
        }

        return $snapshot;
    }

    function rollbackContextsFromSnapshot($snapshot)
    {
        $result = array('restored' => 0, 'deleted' => 0, 'skipped' => 0);

        if (empty($snapshot['rows']) || !is_array($snapshot['rows'])) {
            return $result;
        }

        $this->db->trans_start();

        foreach ($snapshot['rows'] as $row) {
            if (!empty($row['existed']) && !empty($row['record'])) {
                $record = $row['record'];
                $id = isset($record['Id']) ? (int) $record['Id'] : 0;

                if ($id <= 0) {
                    $result['skipped']++;
                    continue;
                }

                $this->db->where('Id', $id);
                $this->db->update('contextdetails', array(
                    'ContextKey' => $record['ContextKey'],
                    'ContextValue' => $record['ContextValue'],
                    'IsActive' => $record['IsActive'],
                    'IsEncrypted' => $record['IsEncrypted'],
                    'EnvironmentFK' => $record['EnvironmentFK'],
                    'ProjectDetailsFK' => $record['ProjectDetailsFK'],
                    'Description' => $record['Description'],
                    'CreatedBy' => $record['CreatedBy'],
                    'CreatedOn' => $record['CreatedOn'],
                    'ModifiedBy' => $record['ModifiedBy'],
                    'ModifiedOn' => $record['ModifiedOn']
                ));
                $result['restored']++;
                continue;
            }

            $contextKey = isset($row['context_key']) ? $row['context_key'] : '';
            $projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            $targetEnvironmentId = isset($row['target_environment_id']) ? (int) $row['target_environment_id'] : 0;

            if ($contextKey === '' || $projectId <= 0 || $targetEnvironmentId <= 0) {
                $result['skipped']++;
                continue;
            }

            $existingContext = $this->findContextByKeyProjectEnvironment($contextKey, $projectId, $targetEnvironmentId);
            if (!empty($existingContext)) {
                $this->db->where('Id', $existingContext->Id);
                $this->db->delete('contextdetails');
                $result['deleted']++;
            } else {
                $result['skipped']++;
            }
        }

        $this->db->trans_complete();

        return $result;
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
        $this->db->update('contextdetails', $Info);
        
        return TRUE;
    }

    
}

?>
