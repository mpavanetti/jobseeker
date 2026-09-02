<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


/**
 * This function is used to print the content of any data
 */
function pre($data)
{
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

/**
 * This function used to get the CI instance
 */
if(!function_exists('get_instance'))
{
    function get_instance()
    {
        $CI = &get_instance();
    }
}

/**
 * This function used to generate the hashed password
 * @param {string} $plainPassword : This is plain text password
 */
if(!function_exists('getHashedPassword'))
{
    function getHashedPassword($plainPassword)
    {
        return password_hash($plainPassword, PASSWORD_DEFAULT);
    }
}

/**
 * This function used to generate the hashed password
 * @param {string} $plainPassword : This is plain text password
 * @param {string} $hashedPassword : This is hashed password
 */
if(!function_exists('verifyHashedPassword'))
{
    function verifyHashedPassword($plainPassword, $hashedPassword)
    {
        return password_verify($plainPassword, $hashedPassword) ? true : false;
    }
}

/**
 * This method used to get current browser agent
 */
if(!function_exists('getBrowserAgent'))
{
    function getBrowserAgent()
    {
        $CI = get_instance();
        $CI->load->library('user_agent');

        $agent = '';

        if ($CI->agent->is_browser())
        {
            $agent = $CI->agent->browser().' '.$CI->agent->version();
        }
        else if ($CI->agent->is_robot())
        {
            $agent = $CI->agent->robot();
        }
        else if ($CI->agent->is_mobile())
        {
            $agent = $CI->agent->mobile();
        }
        else
        {
            $agent = 'Unidentified User Agent';
        }

        return $agent;
    }
}

if(!function_exists('setProtocol'))
{
    function setProtocol()
    {
        $CI = &get_instance();
                    
        $CI->load->library('email');
        
        $config = array(
            'protocol' => PROTOCOL,
            'mailpath' => MAIL_PATH,
            'smtp_host' => SMTP_HOST,
            'smtp_port' => SMTP_PORT,
            'charset' => 'utf-8',
            'mailtype' => 'html',
            'newline' => "\r\n",
            'crlf' => "\r\n"
        );

        if (trim((string) SMTP_USER) !== '' && trim((string) SMTP_PASS) !== '') {
            $config['smtp_user'] = SMTP_USER;
            $config['smtp_pass'] = SMTP_PASS;
        }

        $fromEmail = EMAIL_FROM;

        if (isset($CI->db)) {
            $CI->load->model('SmtpSettings_model', 'smtp_settings_model');
            $setting = $CI->smtp_settings_model->defaultEnabledSetting();

            if (! empty($setting)) {
                $config['protocol'] = 'smtp';
                $config['smtp_host'] = trim((string) $setting->smtp_host);
                $config['smtp_port'] = (int) $setting->smtp_port;

                if (trim((string) $setting->username) !== '' && trim((string) $setting->password) !== '') {
                    $config['smtp_user'] = trim((string) $setting->username);
                    $config['smtp_pass'] = (string) $setting->password;
                } else {
                    unset($config['smtp_user'], $config['smtp_pass']);
                }

                $crypto = strtolower(trim((string) $setting->ssl));
                unset($config['smtp_crypto']);
                if ($crypto === '1' || $crypto === 'ssl') {
                    $config['smtp_crypto'] = 'ssl';
                } else if ($crypto === '2' || $crypto === 'tls') {
                    $config['smtp_crypto'] = 'tls';
                }

                if (isset($setting->reply_to) && filter_var($setting->reply_to, FILTER_VALIDATE_EMAIL)) {
                    $fromEmail = trim($setting->reply_to);
                } else if (filter_var($setting->username, FILTER_VALIDATE_EMAIL)) {
                    $fromEmail = trim($setting->username);
                }
            }
        }

        $CI->jobseeker_mail_from = filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? $fromEmail : 'jobseeker@local.test';
        $CI->email->initialize($config);
        
        return $CI;
    }
}

if(!function_exists('emailConfig'))
{
    function emailConfig()
    {
        return setProtocol();
    }
}

if(!function_exists('resetPasswordEmail'))
{
    function resetPasswordEmail($detail)
    {
        if (empty($detail['email']) || !filter_var($detail['email'], FILTER_VALIDATE_EMAIL) || empty($detail['reset_link'])) {
            log_message('error', 'Password reset email is missing a valid recipient or reset link.');
            return FALSE;
        }

        $data["data"] = $detail;
        $CI = setProtocol();        
        
        $fromEmail = isset($CI->jobseeker_mail_from) ? $CI->jobseeker_mail_from : EMAIL_FROM;
        $fromEmail = filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? $fromEmail : 'jobseeker@local.test';
        $fromName = 'Job Seeker';

        $CI->email->from($fromEmail, $fromName);
        $CI->email->reply_to($fromEmail, $fromName);
        $CI->email->subject("Reset your Job Seeker password");
        $CI->email->message($CI->load->view('email/resetPassword', $data, TRUE));
        $CI->email->set_alt_message(
            "A password reset was requested for your Job Seeker account. " .
            "This one-time link expires in 60 minutes: " . $detail['reset_link'] . "\n\n" .
            "If you did not request this change, you can ignore this email."
        );
        $CI->email->to($detail["email"]);
        $status = $CI->email->send();
        
        return $status;
    }
}

if(!function_exists('setFlashData'))
{
    function setFlashData($status, $flashMsg)
    {
        $CI = get_instance();
        $CI->session->set_flashdata($status, $flashMsg);
    }
}

if(!function_exists('js_time'))
{
    /**
     * Render a stored-in-UTC timestamp as a <time> element that
     * assets/js/jobseeker-time.js localizes in the browser according to the
     * per-viewer Local/UTC toggle in the top bar. The element's text content is
     * a plain UTC fallback so the value is still readable with JavaScript off.
     *
     * The application stores and computes every timestamp in UTC (PHP timezone
     * and the MariaDB session are both UTC), so a bare "Y-m-d H:i:s" string
     * coming out of the database is treated as UTC here.
     *
     * @param string|int|null $value   DB datetime string, ISO-8601 string, or epoch seconds.
     * @param array           $options relative  => bool  render "3 minutes ago" and keep it live
     *                                 date_only => bool  drop the time part
     *                                 empty     => string shown when $value is blank/zero/invalid (default "-")
     *                                 format    => date() format for the no-JS fallback
     * @return string HTML
     */
    function js_time($value, $options = array())
    {
        $empty = array_key_exists('empty', $options) ? (string) $options['empty'] : '-';

        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
            return html_escape($empty);
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($timestamp === false || $timestamp <= 0) {
            return html_escape((string) $value);
        }

        $dateOnly = ! empty($options['date_only']);
        $relative = ! empty($options['relative']);
        $fallbackFormat = array_key_exists('format', $options)
            ? (string) $options['format']
            : ($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s');

        $iso = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
        $fallback = gmdate($fallbackFormat, $timestamp).($dateOnly ? '' : ' UTC');

        $attributes = ' datetime="'.html_escape($iso).'"';
        if ($relative) {
            $attributes .= ' data-time-relative';
        }
        if ($dateOnly) {
            $attributes .= ' data-time-date-only';
        }

        return '<time'.$attributes.'>'.html_escape($fallback).'</time>';
    }
}

if(!function_exists('js_time_date'))
{
    function js_time_date($value, $options = array())
    {
        $options['date_only'] = true;
        return js_time($value, $options);
    }
}

if(!function_exists('js_time_from_now'))
{
    function js_time_from_now($value, $options = array())
    {
        $options['relative'] = true;
        return js_time($value, $options);
    }
}

?>
