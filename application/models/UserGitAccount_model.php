<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/** Personal Git credentials used only by workspaces the owner opens. */
class UserGitAccount_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `user_git_accounts` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `provider` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'generic',
            `label` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `host` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `path_prefix` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `auth_type` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'token',
            `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `secret_encrypted` text COLLATE utf8_unicode_ci NOT NULL,
            `public_key` text COLLATE utf8_unicode_ci,
            `fingerprint` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT NULL,
            `last_used_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_git_account_scope` (`user_id`,`host`,`path_prefix`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $columns = array(
            'provider' => "varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'generic' AFTER `user_id`",
            'label' => "varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `provider`",
            'path_prefix' => "varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `host`",
            'auth_type' => "varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'token' AFTER `path_prefix`",
            'username' => "varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `auth_type`",
            'secret_encrypted' => "text COLLATE utf8_unicode_ci NULL AFTER `username`",
            'public_key' => "text COLLATE utf8_unicode_ci NULL AFTER `secret_encrypted`",
            'fingerprint' => "varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `public_key`",
            'created_at' => "datetime NULL AFTER `fingerprint`",
            'updated_at' => "datetime NULL AFTER `created_at`",
            'last_used_at' => "datetime NULL AFTER `updated_at`"
        );
        foreach ($columns as $name => $definition) {
            if (! $this->db->field_exists($name, 'user_git_accounts')) {
                $this->db->query('ALTER TABLE `user_git_accounts` ADD `'.$name.'` '.$definition);
            }
        }

        // The first Git-account prototype stored a single encrypted token in
        // token_encrypted. Preserve those accounts while moving them to the
        // provider/auth-neutral secret field. The credential reader accepts
        // this legacy plaintext-token payload after decryption.
        if ($this->db->field_exists('token_encrypted', 'user_git_accounts')) {
            $this->db->query("UPDATE `user_git_accounts` SET `secret_encrypted` = `token_encrypted` WHERE (`secret_encrypted` IS NULL OR `secret_encrypted` = '') AND `token_encrypted` IS NOT NULL");
            $this->db->query('ALTER TABLE `user_git_accounts` DROP COLUMN `token_encrypted`');
        }
        $secretColumn = $this->db->query("SHOW COLUMNS FROM `user_git_accounts` LIKE 'secret_encrypted'")->row_array();
        if (! empty($secretColumn) && strtoupper((string) $secretColumn['Null']) === 'YES') {
            $this->db->query("UPDATE `user_git_accounts` SET `secret_encrypted` = '' WHERE `secret_encrypted` IS NULL");
            $this->db->query("ALTER TABLE `user_git_accounts` MODIFY `secret_encrypted` text COLLATE utf8_unicode_ci NOT NULL");
        }

        $indexes = $this->db->query("SHOW INDEX FROM `user_git_accounts`")->result_array();
        $indexNames = array();
        foreach ($indexes as $index) {
            $indexNames[(string) $index['Key_name']] = TRUE;
        }
        if (isset($indexNames['user_git_account_host'])) {
            $this->db->query('ALTER TABLE `user_git_accounts` DROP INDEX `user_git_account_host`');
        }
        if (! isset($indexNames['user_git_account_scope'])) {
            $this->db->query('ALTER TABLE `user_git_accounts` ADD UNIQUE KEY `user_git_account_scope` (`user_id`,`host`,`path_prefix`)');
        }
    }

    /** The user's accounts, without their protected values. */
    public function accounts($userId)
    {
        return $this->db->select('id, provider, label, host, path_prefix, auth_type, username, public_key, fingerprint, created_at, updated_at, last_used_at')
            ->where('user_id', (int) $userId)->order_by('host')->order_by('path_prefix')
            ->get('user_git_accounts')->result();
    }

    public function save($userId, array $account)
    {
        $this->load->library('encryption');
        $encrypted = $this->encryption->encrypt(json_encode(isset($account['secret']) ? $account['secret'] : array()));
        if ($encrypted === FALSE) {
            return FALSE;
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'provider' => $account['provider'], 'label' => $account['label'],
            'host' => $account['host'], 'path_prefix' => $account['path_prefix'],
            'auth_type' => $account['auth_type'], 'username' => $account['username'],
            'secret_encrypted' => $encrypted,
            'public_key' => isset($account['public_key']) ? $account['public_key'] : NULL,
            'fingerprint' => isset($account['fingerprint']) ? $account['fingerprint'] : '',
            'updated_at' => $now
        );

        $id = isset($account['id']) ? (int) $account['id'] : 0;
        if ($id > 0) {
            $owned = $this->db->select('id')->where('id', $id)->where('user_id', (int) $userId)
                ->get('user_git_accounts')->row();
            if (! $owned) {
                return FALSE;
            }
            $this->db->where('id', $id)->where('user_id', (int) $userId)->update('user_git_accounts', $data);
            return $this->db->affected_rows() >= 0 ? $id : FALSE;
        }

        $existing = $this->db->select('id')->where('user_id', (int) $userId)
            ->where('host', $account['host'])->where('path_prefix', $account['path_prefix'])
            ->get('user_git_accounts')->row();
        if ($existing) {
            $this->db->where('id', (int) $existing->id)->where('user_id', (int) $userId)->update('user_git_accounts', $data);
            return (int) $existing->id;
        }

        $data['user_id'] = (int) $userId;
        $data['created_at'] = $now;
        return $this->db->insert('user_git_accounts', $data) ? (int) $this->db->insert_id() : FALSE;
    }

    public function delete($userId, $id)
    {
        $this->db->where('user_id', (int) $userId)->where('id', (int) $id)->delete('user_git_accounts');
        return $this->db->affected_rows() > 0;
    }

    /**
     * Resolve the longest matching repository path scope for this host.
     * $problem explains a FALSE result when an account matched but its secret
     * could not be used (unreadable after a key change, or a GitHub token that
     * could not be refreshed), so callers never mistake it for "no account".
     */
    public function credential($userId, $host, $path = '', $transport = '', &$problem = NULL)
    {
        $problem = NULL;
        $host = strtolower(trim((string) $host));
        $path = strtolower(trim(preg_replace('#\.git$#i', '', (string) $path), '/'));
        $rows = $this->db->where('user_id', (int) $userId)->where('host', $host)
            ->order_by('CHAR_LENGTH(path_prefix)', 'DESC', FALSE)->get('user_git_accounts')->result();

        foreach ($rows as $row) {
            $prefix = strtolower(trim((string) $row->path_prefix, '/'));
            if ($prefix !== '' && $path !== $prefix && strpos($path, $prefix.'/') !== 0) {
                continue;
            }
            if ($transport === 'http' && $row->auth_type === 'ssh_key') {
                continue;
            }
            if ($transport === 'ssh' && $row->auth_type !== 'ssh_key') {
                continue;
            }

            $credential = $this->credentialFromRow($row);
            if ($credential !== FALSE) {
                return $credential;
            }
            if ($problem === NULL) {
                $label = trim((string) $row->label) !== '' ? (string) $row->label : (string) $row->host;
                $problem = 'Your '.$label.' Git account matches this repository, but its credential could not be used. Reconnect or re-save it under Profile > Git Accounts.';
            }
        }
        return FALSE;
    }

    public function credentialById($userId, $id)
    {
        $row = $this->db->where('id', (int) $id)->where('user_id', (int) $userId)->get('user_git_accounts')->row();
        return $row ? $this->credentialFromRow($row) : FALSE;
    }

    private function credentialFromRow($row)
    {
        $this->load->library('encryption');
        $decrypted = $this->encryption->decrypt($row->secret_encrypted);
        if ($decrypted === FALSE) {
            return FALSE;
        }
        $secret = json_decode($decrypted, TRUE);
        if (! is_array($secret)) {
            $secret = array('token' => $decrypted);
        }

        // GitHub OAuth Apps may be configured with expiring user tokens. When
        // GitHub supplied a refresh token, rotate it before handing credentials
        // to Git and keep the refreshed bundle encrypted at rest.
        if ((string) $row->provider === 'github' && ! empty($secret['refresh_token'])
            && ! empty($secret['expires_at']) && (int) $secret['expires_at'] <= time() + 300) {
            $this->load->library('GitHubOAuth');
            $refreshed = $this->githuboauth->refresh($secret['refresh_token']);
            if (! empty($refreshed['ok'])) {
                $secret = $this->githubTokenSecret($refreshed['token']);
                $encrypted = $this->encryption->encrypt(json_encode($secret));
                if ($encrypted !== FALSE) {
                    $this->db->where('id', (int) $row->id)->update('user_git_accounts', array('secret_encrypted' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')));
                }
            } else {
                return FALSE;
            }
        }

        $this->db->where('id', (int) $row->id)->update('user_git_accounts', array('last_used_at' => date('Y-m-d H:i:s')));
        return array(
            'id' => (int) $row->id, 'provider' => (string) $row->provider,
            'label' => (string) $row->label, 'host' => (string) $row->host,
            'path_prefix' => (string) $row->path_prefix, 'auth_type' => (string) $row->auth_type,
            'username' => (string) $row->username, 'secret' => $secret
        );
    }

    public function githubTokenSecret(array $token)
    {
        $secret = array('token' => isset($token['access_token']) ? (string) $token['access_token'] : '');
        if (! empty($token['refresh_token'])) {
            $secret['refresh_token'] = (string) $token['refresh_token'];
        }
        if (! empty($token['expires_in'])) {
            $secret['expires_at'] = time() + (int) $token['expires_in'];
        }
        if (! empty($token['refresh_token_expires_in'])) {
            $secret['refresh_token_expires_at'] = time() + (int) $token['refresh_token_expires_in'];
        }
        if (isset($token['scope'])) {
            $secret['scope'] = (string) $token['scope'];
        }
        return $secret;
    }
}
