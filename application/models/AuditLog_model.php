<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

class AuditLog_model extends CI_Model
{
    const TABLE = 'audit_log';
    private static $schemaEnsured = FALSE;

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    /**
     * Keep existing installations upgradeable without a separate migration
     * runner. Fresh installations also receive this table from db_setup.sql.
     */
    private function ensureSchema()
    {
        if (self::$schemaEnsured || $this->db->table_exists(self::TABLE)) {
            self::$schemaEnsured = TRUE;
            return;
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `".self::TABLE."` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `actor_user_id` int(11) NOT NULL,
            `actor_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
            `actor_role` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `operation` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
            `action` varchar(190) COLLATE utf8_unicode_ci NOT NULL,
            `http_method` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
            `request_uri` varchar(2048) COLLATE utf8_unicode_ci NOT NULL,
            `request_data` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `ip_address` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `user_agent` varchar(512) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `status_code` smallint(5) unsigned NOT NULL DEFAULT 200,
            `outcome` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
            `duration_ms` int(10) unsigned NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `audit_log_created` (`created_at`),
            KEY `audit_log_actor` (`actor_user_id`,`created_at`),
            KEY `audit_log_action` (`action`,`created_at`),
            KEY `audit_log_outcome` (`outcome`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        self::$schemaEnsured = TRUE;
    }

    public function record($entry)
    {
        return $this->db->insert(self::TABLE, array(
            'actor_user_id' => (int) $entry['actor_user_id'],
            'actor_name' => substr((string) $entry['actor_name'], 0, 128),
            'actor_role' => substr((string) $entry['actor_role'], 0, 50),
            'operation' => substr((string) $entry['operation'], 0, 20),
            'action' => substr((string) $entry['action'], 0, 190),
            'http_method' => substr((string) $entry['http_method'], 0, 10),
            'request_uri' => substr((string) $entry['request_uri'], 0, 2048),
            'request_data' => isset($entry['request_data']) ? $entry['request_data'] : NULL,
            'ip_address' => substr((string) $entry['ip_address'], 0, 45),
            'user_agent' => substr((string) $entry['user_agent'], 0, 512),
            'status_code' => max(0, min(999, (int) $entry['status_code'])),
            'outcome' => substr((string) $entry['outcome'], 0, 20),
            'duration_ms' => max(0, (int) $entry['duration_ms']),
            'created_at' => (string) $entry['created_at']
        ));
    }

    private function applyFilters($filters)
    {
        if (! empty($filters['user_id'])) {
            $this->db->where('actor_user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['operation'])) {
            $this->db->where('operation', $filters['operation']);
        }
        if (! empty($filters['method'])) {
            $this->db->where('http_method', $filters['method']);
        }
        if (! empty($filters['outcome'])) {
            $this->db->where('outcome', $filters['outcome']);
        }
        if (! empty($filters['from'])) {
            $this->db->where('created_at >=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $to = date('Y-m-d', strtotime($filters['to'].' +1 day'));
            $this->db->where('created_at <', $to.' 00:00:00');
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $this->db->group_start();
            $this->db->like('actor_name', $search);
            $this->db->or_like('actor_role', $search);
            $this->db->or_like('action', $search);
            $this->db->or_like('request_uri', $search);
            $this->db->or_like('request_data', $search);
            $this->db->or_like('ip_address', $search);
            $this->db->group_end();
        }
    }

    public function countEntries($filters)
    {
        $this->applyFilters($filters);
        return (int) $this->db->count_all_results(self::TABLE);
    }

    public function entries($filters, $limit, $offset)
    {
        $this->applyFilters($filters);
        $this->db->order_by('id', 'DESC');
        $this->db->limit((int) $limit, (int) $offset);
        return $this->db->get(self::TABLE)->result();
    }

    public function actors()
    {
        $this->db->select('actor_user_id, actor_name');
        $this->db->from(self::TABLE);
        $this->db->group_by(array('actor_user_id', 'actor_name'));
        $this->db->order_by('actor_name', 'ASC');
        return $this->db->get()->result();
    }

    public function summary($filters)
    {
        $this->applyFilters($filters);
        $this->db->select("COUNT(*) AS total, SUM(CASE WHEN outcome = 'success' THEN 1 ELSE 0 END) AS successful, SUM(CASE WHEN outcome = 'failure' THEN 1 ELSE 0 END) AS failed", FALSE);
        $row = $this->db->get(self::TABLE)->row();

        return array(
            'total' => $row ? (int) $row->total : 0,
            'successful' => $row ? (int) $row->successful : 0,
            'failed' => $row ? (int) $row->failed : 0
        );
    }
}
