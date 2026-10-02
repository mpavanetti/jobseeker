<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Workspace runtimes (doc/jobseeker/Architecture/workspace-runtimes.md):
 *
 *   workspace_runtimes           the catalog of runtime recipes
 *   workspace_runtime_builds     one row per built image pair, by content hash
 *   project_workspace_runtimes   each project's runtime and isolation
 *   workspace_runtime_instances  editor deployments (one per project and
 *                                person, or one shared per project)
 *
 * A project without a row uses the Default editor.
 */
class WorkspaceRuntime_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `workspace_runtimes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `runtime_key` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `description` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `kind` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
            `spec_json` longtext COLLATE utf8_unicode_ci NOT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_by` int(11) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `workspace_runtimes_key` (`runtime_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `workspace_runtime_builds` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `build_hash` char(12) COLLATE utf8_unicode_ci NOT NULL,
            `image_key` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
            `runtime_image` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `ide_image` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'building',
            `message` varchar(2000) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `started_by` int(11) NOT NULL DEFAULT 0,
            `started_at` datetime NOT NULL,
            `finished_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `workspace_runtime_builds_hash` (`build_hash`),
            KEY `workspace_runtime_builds_key` (`image_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `project_workspace_runtimes` (
            `project_id` int(11) NOT NULL,
            `runtime_key` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'default',
            `isolation` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'user',
            `cpus` decimal(5,2) NOT NULL DEFAULT 2.00,
            `memory_mb` int(11) NOT NULL DEFAULT 4096,
            `updated_by` int(11) NOT NULL DEFAULT 0,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`project_id`),
            CONSTRAINT `project_workspace_runtimes_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projectdetails` (`Id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `workspace_runtime_instances` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `project_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL DEFAULT 0,
            `container_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
            `port` int(11) NOT NULL,
            `token_salt` char(32) COLLATE utf8_unicode_ci NOT NULL,
            `runtime_key` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
            `image` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `spec_hash` char(12) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `created_at` datetime NOT NULL,
            `last_opened_at` datetime DEFAULT NULL,
            `last_opened_by` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `workspace_runtime_instances_owner` (`project_id`,`user_id`),
            UNIQUE KEY `workspace_runtime_instances_name` (`container_name`),
            UNIQUE KEY `workspace_runtime_instances_port` (`port`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    /** Adds the preset runtimes once, while the catalog is empty. */
    public function seed(array $presets)
    {
        if ($this->db->count_all('workspace_runtimes') > 0) {
            return;
        }
        foreach ($presets as $preset) {
            $this->saveRuntime($preset, 0);
        }
    }

    private function runtimeRow($row)
    {
        if (! $row) {
            return FALSE;
        }
        $spec = json_decode((string) $row->spec_json, TRUE);
        return array(
            'id' => (int) $row->id,
            'key' => (string) $row->runtime_key,
            'name' => (string) $row->name,
            'description' => (string) $row->description,
            'kind' => (string) $row->kind,
            'spec' => is_array($spec) ? $spec : array(),
            'active' => (int) $row->is_active === 1,
            'createdBy' => (int) $row->created_by,
            'createdByName' => isset($row->created_by_name) ? (string) $row->created_by_name : '',
            'createdAt' => (string) $row->created_at,
            'updatedAt' => (string) $row->updated_at
        );
    }

    public function runtimes($activeOnly = FALSE)
    {
        $this->db->select('r.*, u.name AS created_by_name', FALSE)
            ->from('workspace_runtimes r')
            ->join('tbl_users u', 'u.userId = r.created_by', 'left')
            ->order_by('r.name', 'ASC');
        if ($activeOnly) {
            $this->db->where('r.is_active', 1);
        }
        return array_map(array($this, 'runtimeRow'), $this->db->get()->result());
    }

    public function runtime($key)
    {
        return $this->runtimeRow($this->db->where('runtime_key', (string) $key)->get('workspace_runtimes')->row());
    }

    public function saveRuntime(array $runtime, $userId, $existingKey = '')
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = array(
            'runtime_key' => $runtime['key'],
            'name' => $runtime['name'],
            'description' => $runtime['description'],
            'kind' => $runtime['kind'],
            'spec_json' => json_encode($runtime['spec'], JSON_UNESCAPED_SLASHES),
            'updated_at' => $now
        );
        if ($existingKey !== '') {
            return $this->db->where('runtime_key', $existingKey)->update('workspace_runtimes', $row);
        }
        $row['created_by'] = (int) $userId;
        $row['created_at'] = $now;
        $row['is_active'] = 1;
        return $this->db->insert('workspace_runtimes', $row);
    }

    public function deleteRuntime($key)
    {
        return $this->db->where('runtime_key', (string) $key)->delete('workspace_runtimes');
    }

    /** How many projects use each runtime, by key. */
    public function runtimeUsage()
    {
        $usage = array();
        foreach ($this->db->select('runtime_key, COUNT(*) AS projects', FALSE)->group_by('runtime_key')->get('project_workspace_runtimes')->result() as $row) {
            $usage[(string) $row->runtime_key] = (int) $row->projects;
        }
        return $usage;
    }

    public function build($hash)
    {
        $row = $this->db->where('build_hash', (string) $hash)->get('workspace_runtime_builds')->row_array();
        return $row ?: FALSE;
    }

    /** The newest successful build of an image key, for jobs. */
    public function readyBuilds()
    {
        return $this->db->where('status', 'ready')->order_by('finished_at', 'DESC')->get('workspace_runtime_builds')->result_array();
    }

    /**
     * Claims a build: TRUE when this call registered it (or restarted a failed
     * one), FALSE when it is already building or built.
     */
    public function claimBuild($hash, $imageKey, $runtimeImage, $ideImage, $userId)
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->query('INSERT IGNORE INTO `workspace_runtime_builds` (build_hash, image_key, runtime_image, ide_image, status, message, started_by, started_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            array($hash, $imageKey, $runtimeImage, $ideImage, 'building', '', (int) $userId, $now));
        if ($this->db->affected_rows() > 0) {
            return TRUE;
        }
        $this->db->where('build_hash', $hash)->where_in('status', array('failed', 'missing'))
            ->update('workspace_runtime_builds', array('status' => 'building', 'message' => '', 'started_by' => (int) $userId, 'started_at' => $now, 'finished_at' => NULL));
        return $this->db->affected_rows() > 0;
    }

    public function finishBuild($hash, $status, $message)
    {
        return $this->db->where('build_hash', (string) $hash)->where('status', 'building')->update('workspace_runtime_builds', array(
            'status' => $status,
            'message' => substr((string) $message, 0, 2000),
            'finished_at' => gmdate('Y-m-d H:i:s')
        ));
    }

    /** Lets a ready build be claimed again, for a rebuild on request. */
    public function releaseBuild($hash)
    {
        return $this->db->where('build_hash', (string) $hash)->where('status', 'ready')->update('workspace_runtime_builds', array('status' => 'failed'));
    }

    /** A ready build whose image has disappeared must be built again. */
    public function markBuildMissing($hash)
    {
        return $this->db->where('build_hash', (string) $hash)->update('workspace_runtime_builds', array('status' => 'missing', 'message' => 'The image no longer exists in the job runtime.'));
    }

    public function projectSettings($projectId)
    {
        $row = $this->db->where('project_id', (int) $projectId)->get('project_workspace_runtimes')->row();
        return $row ? array(
            'runtimeKey' => (string) $row->runtime_key,
            'isolation' => (string) $row->isolation,
            'cpus' => (float) $row->cpus,
            'memoryMb' => (int) $row->memory_mb
        ) : FALSE;
    }

    public function allProjectSettings()
    {
        $settings = array();
        foreach ($this->db->get('project_workspace_runtimes')->result() as $row) {
            $settings[(int) $row->project_id] = array(
                'runtimeKey' => (string) $row->runtime_key,
                'isolation' => (string) $row->isolation,
                'cpus' => (float) $row->cpus,
                'memoryMb' => (int) $row->memory_mb
            );
        }
        return $settings;
    }

    public function saveProjectSettings($projectId, array $settings, $userId)
    {
        return $this->db->replace('project_workspace_runtimes', array(
            'project_id' => (int) $projectId,
            'runtime_key' => $settings['runtimeKey'],
            'isolation' => $settings['isolation'],
            'cpus' => $settings['cpus'],
            'memory_mb' => (int) $settings['memoryMb'],
            'updated_by' => (int) $userId,
            'updated_at' => gmdate('Y-m-d H:i:s')
        ));
    }

    public function instance($projectId, $userId)
    {
        $row = $this->db->where('project_id', (int) $projectId)->where('user_id', (int) $userId)->get('workspace_runtime_instances')->row_array();
        return $row ?: FALSE;
    }

    public function instanceByName($name)
    {
        $row = $this->db->where('container_name', (string) $name)->get('workspace_runtime_instances')->row_array();
        return $row ?: FALSE;
    }

    public function instances()
    {
        return $this->db->select('i.*, p.ProjectName AS project_name, u.name AS user_name, o.name AS opened_by_name', FALSE)
            ->from('workspace_runtime_instances i')
            ->join('projectdetails p', 'p.Id = i.project_id', 'left')
            ->join('tbl_users u', 'u.userId = i.user_id', 'left')
            ->join('tbl_users o', 'o.userId = i.last_opened_by', 'left')
            ->order_by('i.last_opened_at', 'DESC')
            ->get()->result_array();
    }

    /**
     * Registers a deployment on the first free port of [$start, $end]. The
     * unique port key settles races between two people opening at once.
     *
     * @return array|FALSE the row
     */
    public function createInstance($projectId, $userId, $containerName, $runtimeKey, $start, $end)
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $used = array();
            foreach ($this->db->select('port')->get('workspace_runtime_instances')->result() as $row) {
                $used[(int) $row->port] = TRUE;
            }
            $port = 0;
            for ($candidate = $start; $candidate <= $end; $candidate++) {
                if (! isset($used[$candidate])) {
                    $port = $candidate;
                    break;
                }
            }
            if ($port === 0) {
                return FALSE;
            }
            $this->db->query('INSERT IGNORE INTO `workspace_runtime_instances` (project_id, user_id, container_name, port, token_salt, runtime_key, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                array((int) $projectId, (int) $userId, $containerName, $port, bin2hex(random_bytes(16)), $runtimeKey, gmdate('Y-m-d H:i:s')));
            $row = $this->instance($projectId, $userId);
            if ($row !== FALSE) {
                return $row;
            }
        }
        return FALSE;
    }

    public function updateInstance($id, array $fields)
    {
        return $this->db->where('id', (int) $id)->update('workspace_runtime_instances', $fields);
    }

    public function deleteInstance($id)
    {
        return $this->db->where('id', (int) $id)->delete('workspace_runtime_instances');
    }
}
