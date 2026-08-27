<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class EmailSettings_model extends CI_Model
{

        private $defaultTemplateNames = array(
                'success' => 'JobSeeker Success',
                'failure' => 'JobSeeker Failure',
                'aborted' => 'JobSeeker Abort'
        );

        function ensureDefaultTemplates() {
                $column = $this->db->query("SHOW COLUMNS FROM `email_settings` LIKE 'msg'")->row();
                if ($column && stripos((string) $column->Type, 'text') === FALSE) {
                        $this->db->query('ALTER TABLE `email_settings` MODIFY `msg` MEDIUMTEXT NOT NULL');
                }

                $smtpName = 'Local Mailpit';
                if ($this->db->table_exists('smtp_settings')) {
                        if ($this->db->field_exists('is_default', 'smtp_settings')) {
                                $this->db->order_by('is_default', 'DESC');
                        }
                        $smtp = $this->db->order_by('name', 'ASC')->get('smtp_settings', 1)->row();
                        if ($smtp && trim((string) $smtp->name) !== '') {
                                $smtpName = $smtp->name;
                        }
                }

                foreach ($this->defaultTemplateNames as $status => $name) {
                    $existing = $this->db->get_where('email_settings', array('name' => $name), 1)->row();
                    if ($existing) {
                        if ($status === 'failure' && $existing->owner === 'System' && strpos($existing->subject, '[FAILURE]') === 0) {
                            $this->db->where('id', $existing->id)->update('email_settings', array(
                                'subject' => '[FAILED] ${PROJECT_NAME} #${BUILD_NUMBER}'
                            ));
                        }
                        if ($status === 'success' && $existing->owner === 'System' && (strpos($existing->msg, '${ENV,var="JOBSEEKER_DATASET"}') !== FALSE || strpos($existing->msg, 'JOBSEEKER_EMAIL_DATASET=') !== FALSE)) {
                            $this->db->where('id', $existing->id)->update('email_settings', array(
                                'msg' => $this->defaultTemplateBody('success')
                            ));
                        }
                                continue;
                        }

                        $this->db->insert('email_settings', array(
                                'name' => $name,
                                'creation_date' => date('Y-m-d H:i:s'),
                                'to' => 'jobseeker@local.test',
                                'from' => 'JobSeeker',
                                'cc' => '',
                                'bcc' => '',
                                'subject' => '['.$this->defaultTemplateStatus($status).'] ${PROJECT_NAME} #${BUILD_NUMBER}',
                                'msg' => $this->defaultTemplateBody($status),
                                'attachment' => '',
                                'smtp' => $smtpName,
                                'enabled' => 1,
                                'owner' => 'System',
                                'description' => 'Default JobSeeker '.strtolower($status).' build notification.'
                        ));
                }
        }

            private function defaultTemplateStatus($status) {
                if ($status === 'failure') {
                    return 'FAILED';
                }

                return strtoupper($status);
            }

        private function defaultTemplateBody($status) {
                $settings = array(
                        'success' => array(
                                'label' => 'SUCCESS',
                                'title' => 'Build completed successfully',
                                'intro' => 'JobSeeker completed this ETL build successfully. Review the execution details and optional data metrics below.',
                                'header' => '#047857',
                                'accent' => '#059669',
                                'soft' => '#d1fae5'
                        ),
                        'failure' => array(
                                'label' => 'FAILED',
                                'title' => 'Build failed',
                                'intro' => 'Jenkins marked this JobSeeker build as failed. Start with the highlighted error excerpt, then open the console log if the surrounding context is needed.',
                                'header' => '#7f1d1d',
                                'accent' => '#dc2626',
                                'soft' => '#fee2e2'
                        ),
                        'aborted' => array(
                                'label' => 'ABORTED',
                                'title' => 'Build was aborted',
                                'intro' => 'This JobSeeker build stopped before completion. Review the cause and recent console output to determine whether it should be restarted.',
                                'header' => '#92400e',
                                'accent' => '#d97706',
                                'soft' => '#fef3c7'
                        )
                );
                $template = $settings[$status];
                $metrics = '';
                $console = '';

                if ($status === 'success') {
                        $metrics = <<<'HTML'
                    <h2 style="margin:20px 0 8px; font-size:16px;">ETL Data Summary</h2>
                    <table style="width:100%; border-collapse:collapse; margin:0 0 20px; font-size:14px;">
                        <tr><th align="left" style="width:150px; padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Dataset</th><td style="padding:8px; border:1px solid #d8dee9;">${PROPFILE,file="jobseeker-email-metrics.properties",property="dataset"}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Rows read</th><td style="padding:8px; border:1px solid #d8dee9;">${PROPFILE,file="jobseeker-email-metrics.properties",property="rows_read"}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Rows written</th><td style="padding:8px; border:1px solid #d8dee9;">${PROPFILE,file="jobseeker-email-metrics.properties",property="rows_written"}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Rows rejected</th><td style="padding:8px; border:1px solid #d8dee9;">${PROPFILE,file="jobseeker-email-metrics.properties",property="rows_rejected"}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Duration</th><td style="padding:8px; border:1px solid #d8dee9;">${PROPFILE,file="jobseeker-email-metrics.properties",property="duration"}</td></tr>
                    </table>
HTML;
                } else if ($status === 'failure') {
                        $console = <<<'HTML'
                    <h2 style="margin:20px 0 8px; font-size:16px;">Error Focus</h2>
                    <pre style="white-space:pre-wrap; word-break:break-word; background:#111827; color:#e5e7eb; padding:14px; border-radius:4px; font-size:12px; line-height:1.45;">${BUILD_LOG_REGEX, regex="(?i)(traceback|[a-z_][a-z0-9_]*(error|exception):|error|exception|fatal|command not found|no such file|permission denied|returned non-zero exit status|script returned exit code|build step .* marked build as failure)", linesBefore=5, linesAfter=0, maxTailMatches=6, maxLineLength=360, showTruncatedLines=false, escapeHtml=true, matchedLineHtmlStyle="color:#fecaca; font-weight:bold;", defaultValue="No explicit error lines were detected in the captured console output."}</pre>
HTML;
                }

                if ($status !== 'success') {
                        $console .= <<<'HTML'
                    <h2 style="margin:20px 0 8px; font-size:16px;">Recent Console Output</h2>
                    <pre style="white-space:pre-wrap; word-break:break-word; background:#0f172a; color:#e5e7eb; padding:14px; border-radius:4px; font-size:12px; line-height:1.45;">${BUILD_LOG, maxLines=160, maxLineLength=500, escapeHtml=true}</pre>
HTML;
                }

                return str_replace(
                        array('@@LABEL@@', '@@TITLE@@', '@@INTRO@@', '@@HEADER@@', '@@ACCENT@@', '@@SOFT@@', '@@METRICS@@', '@@CONSOLE@@'),
                        array($template['label'], $template['title'], $template['intro'], $template['header'], $template['accent'], $template['soft'], $metrics, $console),
                        <<<'HTML'
<html>
    <body style="margin:0; padding:0; background:#f3f4f6; color:#17202a; font-family:Arial, Helvetica, sans-serif;">
        <div style="max-width:780px; margin:0 auto; padding:24px;">
            <div style="background:#ffffff; border:1px solid #d8dee9; border-radius:6px; overflow:hidden;">
                <div style="background:@@HEADER@@; color:#ffffff; padding:20px 24px;">
                    <p style="margin:0 0 6px; font-size:12px; text-transform:uppercase; color:@@SOFT@@;">@@LABEL@@ - ${ENV,var="ENVIRONMENT"}</p>
                    <h1 style="margin:0; font-size:23px; line-height:1.3;">${PROJECT_NAME} #${BUILD_NUMBER} - @@TITLE@@</h1>
                    <p style="margin:8px 0 0; font-size:14px; line-height:1.4; color:@@SOFT@@;">${CAUSE}</p>
                </div>
                <div style="padding:24px;">
                    <p style="margin:0 0 18px; font-size:15px; line-height:1.55;">@@INTRO@@</p>
                    <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin:0 0 20px;"><tr>
                        <td style="padding:0 8px 8px 0;"><a href="${BUILD_URL}" style="display:block; white-space:nowrap; padding:9px 13px; background:#1f2937; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px;">Open build</a></td>
                        <td style="padding:0 8px 8px 0;"><a href="${BUILD_URL}console" style="display:block; white-space:nowrap; padding:9px 13px; background:@@ACCENT@@; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px;">Console log</a></td>
                        <td style="padding:0 8px 8px 0;"><a href="${BUILD_URL}consoleText" style="display:block; white-space:nowrap; padding:9px 13px; background:#475569; color:#ffffff; text-decoration:none; border-radius:4px; font-size:13px;">Raw log</a></td>
                        <td style="padding:0 0 8px;"><a href="${PROJECT_URL}" style="display:block; white-space:nowrap; padding:9px 13px; background:#e5e7eb; color:#111827; text-decoration:none; border-radius:4px; font-size:13px;">Job page</a></td>
                    </tr></table>
                    <table style="width:100%; border-collapse:collapse; margin:0 0 20px; font-size:14px;">
                        <tr><th align="left" style="width:150px; padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Job</th><td style="padding:8px; border:1px solid #d8dee9;">${PROJECT_NAME}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Environment</th><td style="padding:8px; border:1px solid #d8dee9; font-weight:bold;">${ENV,var="ENVIRONMENT"}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build</th><td style="padding:8px; border:1px solid #d8dee9;">#${BUILD_NUMBER}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Status</th><td style="padding:8px; border:1px solid #d8dee9; color:@@HEADER@@; font-weight:bold;">@@LABEL@@</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build ID</th><td style="padding:8px; border:1px solid #d8dee9;">${ENV,var="BUILD_ID"}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Node</th><td style="padding:8px; border:1px solid #d8dee9;">${ENV,var="NODE_NAME"} / executor ${ENV,var="EXECUTOR_NUMBER"}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Workspace</th><td style="padding:8px; border:1px solid #d8dee9; word-break:break-all;">${ENV,var="WORKSPACE"}</td></tr>
                        <tr><th align="left" style="padding:8px; border:1px solid #d8dee9; background:#f8fafc;">Build URL</th><td style="padding:8px; border:1px solid #d8dee9; word-break:break-all;"><a href="${BUILD_URL}" style="color:@@ACCENT@@;">${BUILD_URL}</a></td></tr>
                    </table>
@@METRICS@@
@@CONSOLE@@
                </div>
            </div>
        </div>
    </body>
</html>
HTML
                );
        }

    function listSettings() {

                $this->ensureDefaultTemplates();

        $this->db->select('*');
        $this->db->from('email_settings');
        $query = $this->db->get();
        return $query->result();
    }

    function fetchAll($colunm) {

        $this->ensureDefaultTemplates();

        if($colunm == "all") {
            $this->db->select('*');
        } else {
        $this->db->select($colunm);
        }
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

    function fetchName($name) {

        $this->ensureDefaultTemplates();

        $this->db->select('*');
        $this->db->from('email_settings');
        $this->db->where('name', $name);
        $query = $this->db->get();
        return $query->result();
    }

    private function smtpJoinColumns($includePassword = FALSE)
    {
        $columns = 'email_settings.*, smtp_settings.smtp_host, smtp_settings.smtp_port, smtp_settings.username, smtp_settings.ssl';

        if ($includePassword) {
            $columns .= ', smtp_settings.password';
        }

        $columns .= $this->db->field_exists('reply_to', 'smtp_settings') ? ', smtp_settings.reply_to' : ", 'jobseeker@local.test' AS reply_to";
        $columns .= $this->db->field_exists('is_enabled', 'smtp_settings') ? ', smtp_settings.is_enabled' : ', 1 AS is_enabled';

        return $columns;
    }

    function fetchSMTP() {

        $this->db->select('*');
        $this->db->from('smtp_settings');

        if ($this->db->field_exists('is_enabled', 'smtp_settings')) {
            $this->db->where('is_enabled', 1);
        }

        if ($this->db->field_exists('is_default', 'smtp_settings')) {
            $this->db->order_by('is_default', 'DESC');
        }

        $this->db->order_by('name', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }

    function fetchXsmtp($id) {

        $this->db->select($this->smtpJoinColumns(FALSE), FALSE);
        $this->db->from('email_settings');
        $this->db->where('email_settings.id', $id);
        $this->db->join('smtp_settings', 'smtp_settings.name = email_settings.smtp');
        $query = $this->db->get();
        return $query->result();
    }

    function fetchXsmtpCredentials($id) {

        $this->db->select($this->smtpJoinColumns(TRUE), FALSE);
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