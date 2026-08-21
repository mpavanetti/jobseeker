<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class SmtpSettings_model extends CI_Model
{
    private $localDefaultName = 'Local Mailpit';
    private $localDefaultDescription = 'Local test inbox; captures Jenkins emails in Mailpit and does not deliver to external mailboxes.';

    function ensureSchema()
    {
        if (! $this->db->field_exists('is_enabled', 'smtp_settings')) {
            $this->db->query('ALTER TABLE `smtp_settings` ADD `is_enabled` INT(1) NOT NULL DEFAULT 1 AFTER `ssl`');
        }

        if (! $this->db->field_exists('is_default', 'smtp_settings')) {
            $this->db->query('ALTER TABLE `smtp_settings` ADD `is_default` INT(1) NOT NULL DEFAULT 0 AFTER `is_enabled`');
        }

        if (! $this->db->field_exists('reply_to', 'smtp_settings')) {
            $this->db->query("ALTER TABLE `smtp_settings` ADD `reply_to` VARCHAR(200) NOT NULL DEFAULT 'jobseeker@local.test' AFTER `is_default`");
        }
    }

    function ensureLocalDefaultSetting()
    {
        $this->ensureSchema();

        $localEnabled = $this->localSmtpEnabledByDefault() ? 1 : 0;
        $query = $this->db->get_where('smtp_settings', array('name' => $this->localDefaultName), 1);

        if ($query->num_rows() === 0) {
            $this->db->insert('smtp_settings', array(
                'name' => $this->localDefaultName,
                'smtp_host' => getenv('JOBSEEKER_LOCAL_SMTP_HOST') ?: 'mailpit',
                'smtp_port' => getenv('JOBSEEKER_LOCAL_SMTP_PORT') ?: 1025,
                'username' => '',
                'password' => '',
                'ssl' => 0,
                'is_enabled' => $localEnabled,
                'is_default' => $localEnabled,
                'reply_to' => getenv('JOBSEEKER_LOCAL_SMTP_REPLY_TO') ?: 'jobseeker@local.test',
                'creation_date' => date('Y-m-d H:i:s'),
                'owner' => 'System',
                'description' => $this->localDefaultDescription
            ));
        } else {
            $row = $query->row();
            if(empty($row->description) || $row->description === 'Local SMTP capture server for Jenkins build email tests.') {
                $this->db->where('id', $row->id);
                $this->db->update('smtp_settings', array('description' => $this->localDefaultDescription));
            }
        }

        $defaultQuery = $this->db
            ->where('is_default', 1)
            ->count_all_results('smtp_settings');

        if ($defaultQuery === 0 && $localEnabled) {
            $this->db->where('name', $this->localDefaultName);
            $this->db->update('smtp_settings', array('is_default' => 1, 'is_enabled' => 1));
        }
    }

    private function localSmtpEnabledByDefault()
    {
        $value = strtolower(trim((string) getenv('JOBSEEKER_LOCAL_SMTP_ENABLED')));

        return ! in_array($value, array('0', 'false', 'no', 'off'), TRUE);
    }

    function listSettings() {
        $this->ensureLocalDefaultSetting();

        $this->db->select('*');
        $this->db->from('smtp_settings');
        $this->db->order_by('is_default', 'DESC');
        $this->db->order_by('is_enabled', 'DESC');
        $this->db->order_by('name', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }

    function defaultEnabledSetting()
    {
        $this->ensureLocalDefaultSetting();

        $this->db->select('*');
        $this->db->from('smtp_settings');
        $this->db->where('is_enabled', 1);
        $this->db->order_by('is_default', 'DESC');
        $this->db->order_by('id', 'ASC');
        $this->db->limit(1);
        $query = $this->db->get();

        return $query->row();
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
        $this->ensureSchema();

        $this->db->trans_start();

        if (! empty($Info['is_default'])) {
            $this->db->update('smtp_settings', array('is_default' => 0));
        }

        $this->db->insert('smtp_settings', $Info);
        
        $insert_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        return $insert_id;
    }

    // Delete record from db
    function deleteSetting($id)
    {
        $this->ensureSchema();
        
        $this->db->where('id', $id);
		$this->db->delete('smtp_settings');

        
        return $this->db->affected_rows();
    }

    // Fetch data to input edit
    function EditSettingsFetchData($id = 0)
    {
        $this->ensureLocalDefaultSetting();

        $this->db->where('id',$id);
        $sql = $this->db->get('smtp_settings');
        return $sql->row();
    }

    function updateSetting($Info, $id)
    {
        $this->ensureSchema();

        $this->db->trans_start();

        if (! empty($Info['is_default'])) {
            $this->db->update('smtp_settings', array('is_default' => 0));
        }

        $this->db->where('id', $id);
        $this->db->update('smtp_settings', $Info);

        $this->db->trans_complete();
        
        return TRUE;
    }

    
}

?>