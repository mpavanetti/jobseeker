<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * The Git repository a Context project owns, when it has one.
 *
 * Git is optional: a project without a repository URL is only a context
 * scope. A Git project has one repository, one build credential (the key of
 * a Git connector; empty for a public repository) and a branch per
 * environment, stored per environment with a project-wide fallback row
 * (DEFAULT). Jobs bound to the project read all three at build time through
 * gitSource(), so promotion never copies them between environments.
 */
class ProjectGitSettings_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `project_git_defaults` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `project_id` int(11) NOT NULL,
            `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'DEFAULT',
            `branch` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `project_git_environment` (`project_id`,`environment`),
            CONSTRAINT `project_git_defaults_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projectdetails` (`Id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $columns = array();
        foreach ($this->db->query("SHOW COLUMNS FROM `projectdetails`")->result_array() as $column) {
            $columns[$column['Field']] = $column;
        }

        // Project Details previously accepted 2,000 characters while the
        // schema silently truncated at 510. The runtime migration and clean
        // install schema now agree with the 1,000-character Git URL policy.
        if (isset($columns['GitPath']) && preg_match('/varchar\((\d+)\)/i', (string) $columns['GitPath']['Type'], $matches) && (int) $matches[1] < 1000) {
            $this->db->query("ALTER TABLE `projectdetails` MODIFY `GitPath` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL");
        }
        if (! isset($columns['GitCredentialKey'])) {
            $this->db->query("ALTER TABLE `projectdetails` ADD `GitCredentialKey` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `GitPath`");
        }
        // What the project's jobs are written in (see ProjectWorkspace::types()).
        if (! isset($columns['ProjectType'])) {
            $this->db->query("ALTER TABLE `projectdetails` ADD `ProjectType` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'python' AFTER `ProjectName`");
        }
        $this->migrateEnvironmentCredentials();
    }

    /**
     * Early builds stored a credential on every environment row. Keep the
     * fallback row's key (or the first one set) as the project credential.
     */
    private function migrateEnvironmentCredentials()
    {
        if (! $this->db->field_exists('credential_key', 'project_git_defaults')) {
            return;
        }
        $rows = $this->db->query("SELECT project_id, environment, credential_key FROM `project_git_defaults` WHERE credential_key <> '' ORDER BY CASE WHEN environment = 'DEFAULT' THEN 0 ELSE 1 END, environment")->result();
        $migrated = array();
        foreach ($rows as $row) {
            $projectId = (int) $row->project_id;
            if (isset($migrated[$projectId])) {
                if ($migrated[$projectId] !== (string) $row->credential_key) {
                    log_message('info', 'Project '.$projectId.' used different Git credentials per environment; kept '.$migrated[$projectId].'. Scope one connector key per environment instead.');
                }
                continue;
            }
            $migrated[$projectId] = (string) $row->credential_key;
            $this->db->where('Id', $projectId)->where('GitCredentialKey', '')
                ->update('projectdetails', array('GitCredentialKey' => $migrated[$projectId]));
        }
        $this->db->query("DELETE FROM `project_git_defaults` WHERE branch = ''");
        if ($this->db->query("SHOW INDEX FROM `project_git_defaults` WHERE Key_name = 'project_git_credential'")->num_rows() > 0) {
            $this->db->query("ALTER TABLE `project_git_defaults` DROP INDEX `project_git_credential`");
        }
        $this->db->query("ALTER TABLE `project_git_defaults` DROP COLUMN `credential_key`");
    }

    /** Branch defaults keyed by environment, the fallback row ('DEFAULT') first. */
    public function settings($projectId)
    {
        $result = array();
        $rows = $this->db->where('project_id', (int) $projectId)
            ->order_by("CASE WHEN environment = 'DEFAULT' THEN 0 ELSE 1 END", 'ASC', FALSE)
            ->order_by('environment', 'ASC')->get('project_git_defaults')->result();
        foreach ($rows as $row) {
            $result[strtoupper(trim((string) $row->environment))] = array('branch' => (string) $row->branch);
        }
        return $result;
    }

    public function credentialKey($projectId)
    {
        $row = $this->db->select('GitCredentialKey')->where('Id', (int) $projectId)->get('projectdetails')->row();
        return $row ? (string) $row->GitCredentialKey : '';
    }

    /**
     * What a job bound to this project uses in an environment: the branch
     * for that environment (else the project fallback; '' lets the caller
     * apply the global branch policy) and the project's build credential.
     */
    public function settingForEnvironment($projectId, $environment)
    {
        $settings = $this->settings($projectId);
        $environment = strtoupper(trim((string) $environment));
        $branch = '';
        if ($environment !== '' && isset($settings[$environment])) {
            $branch = $settings[$environment]['branch'];
        } elseif (isset($settings['DEFAULT'])) {
            $branch = $settings['DEFAULT']['branch'];
        }
        return array('branch' => $branch, 'credentialKey' => $this->credentialKey($projectId));
    }

    /**
     * What a bound job clones in an environment: the project's repository and
     * credential, and its branch for that environment after the fallback row
     * and the deployment-wide policy. FALSE when the project has no repository.
     */
    public function gitSource($projectId, $environment)
    {
        $project = $this->project($projectId, FALSE);
        if ($project === FALSE || $project['repositoryUrl'] === '') {
            return FALSE;
        }
        $setting = $this->settingForEnvironment($projectId, $environment);
        $branch = $setting['branch'];
        if ($branch === '') {
            $this->load->library('GitBranchPolicy');
            $branch = $this->gitbranchpolicy->forEnvironment($environment);
        }
        return array(
            'projectId' => $project['id'],
            'projectName' => $project['name'],
            'repositoryUrl' => $project['repositoryUrl'],
            'branch' => $branch,
            'credentialKey' => $project['credentialKey']
        );
    }

    /** Saves the build credential and replaces every branch default. */
    public function save($projectId, $credentialKey, array $branches)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return FALSE;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();
        $this->db->where('Id', $projectId)->update('projectdetails', array('GitCredentialKey' => trim((string) $credentialKey)));
        $this->db->where('project_id', $projectId)->delete('project_git_defaults');
        foreach ($branches as $environment => $branch) {
            $branch = trim((string) $branch);
            if ($branch === '') {
                continue;
            }
            $this->db->insert('project_git_defaults', array(
                'project_id' => $projectId,
                'environment' => strtoupper(trim((string) $environment)),
                'branch' => $branch,
                'created_at' => $now,
                'updated_at' => $now
            ));
        }
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /** python, shell or hop; anything unknown is stored as python. */
    public function saveType($projectId, $type)
    {
        $type = strtolower(trim((string) $type));
        if (! in_array($type, array('python', 'shell', 'hop'), TRUE)) {
            $type = 'python';
        }
        return $this->db->where('Id', (int) $projectId)->update('projectdetails', array('ProjectType' => $type));
    }

    /** Projects for Git pickers pass $gitOnly: only those with a repository. */
    public function projects($activeOnly = TRUE, $gitOnly = FALSE)
    {
        if ($activeOnly) {
            $this->db->where('IsActive', 1);
        }
        if ($gitOnly) {
            $this->db->where("TRIM(COALESCE(GitPath, '')) <> ''", NULL, FALSE);
        }
        $rows = $this->db->order_by('ProjectName', 'ASC')->get('projectdetails')->result();
        return array_map(array($this, 'describe'), $rows);
    }

    public function project($projectId, $activeOnly = TRUE)
    {
        $this->db->where('Id', (int) $projectId);
        if ($activeOnly) {
            $this->db->where('IsActive', 1);
        }
        $row = $this->db->get('projectdetails')->row();
        return $row ? $this->describe($row) : FALSE;
    }

    private function describe($row)
    {
        return array(
            'id' => (int) $row->Id,
            'name' => (string) $row->ProjectName,
            'type' => isset($row->ProjectType) && in_array($row->ProjectType, array('python', 'shell', 'hop'), TRUE) ? (string) $row->ProjectType : 'python',
            'repositoryUrl' => trim((string) $row->GitPath),
            'credentialKey' => isset($row->GitCredentialKey) ? (string) $row->GitCredentialKey : '',
            'active' => (int) $row->IsActive === 1,
            'defaults' => $this->settings($row->Id)
        );
    }
}
