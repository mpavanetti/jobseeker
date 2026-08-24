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
        $data["data"] = $detail;
        // pre($detail);
        // die;
        
        $CI = setProtocol();        
        
        $fromEmail = isset($CI->jobseeker_mail_from) ? $CI->jobseeker_mail_from : EMAIL_FROM;
        $fromEmail = filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? $fromEmail : 'jobseeker@local.test';
        $fromName = trim((string) FROM_NAME) !== '' ? FROM_NAME : 'JobSeeker';

        $CI->email->from($fromEmail, $fromName);
        $CI->email->reply_to($fromEmail, $fromName);
        $CI->email->subject("Reset Password");
        $CI->email->message($CI->load->view('email/resetPassword', $data, TRUE));
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

?>