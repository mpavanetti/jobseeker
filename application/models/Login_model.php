<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Login_model extends CI_Model
{
    /**
     * Sliding-window brute-force throttle for the sign-in form. All values are
     * overridable from the environment so an operator can tighten or relax the
     * policy without a code change.
     */
    const LOGIN_ATTEMPT_TABLE = 'tbl_login_attempts';

    public function __construct()
    {
        parent::__construct();
        $this->ensureLoginAttemptSchema();
    }

    private function ensureLoginAttemptSchema()
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS `'.self::LOGIN_ATTEMPT_TABLE.'` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `email` varchar(190) COLLATE utf8_unicode_ci NOT NULL,
            `ip_address` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT \'\',
            `successful` tinyint(1) NOT NULL DEFAULT 0,
            `attempted_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `login_attempts_email` (`email`,`attempted_at`),
            KEY `login_attempts_ip` (`ip_address`,`attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');
    }

    private function loginThrottleConfig()
    {
        $intOrDefault = function ($name, $default, $min, $max) {
            $value = getenv($name);
            if ($value === FALSE || ! preg_match('/^\d+$/', trim((string) $value))) {
                return $default;
            }
            return max($min, min($max, (int) trim((string) $value)));
        };

        return array(
            'window'         => $intOrDefault('JOBSEEKER_LOGIN_LOCKOUT_MINUTES', 15, 1, 1440),
            'email_attempts' => $intOrDefault('JOBSEEKER_LOGIN_MAX_ATTEMPTS', 5, 3, 100),
            'ip_attempts'    => $intOrDefault('JOBSEEKER_LOGIN_MAX_ATTEMPTS_PER_IP', 50, 10, 1000),
        );
    }

    /**
     * @return array{blocked:bool, retry_after:int} retry_after is whole minutes.
     */
    public function loginThrottleState($email, $ip)
    {
        $config = $this->loginThrottleConfig();
        $since = date('Y-m-d H:i:s', time() - ($config['window'] * 60));
        $email = strtolower(trim((string) $email));
        $ip = trim((string) $ip);

        $failuresFor = function ($column, $value) use ($since) {
            if ($value === '') {
                return 0;
            }
            $this->db->where($column, $value);
            $this->db->where('successful', 0);
            $this->db->where('attempted_at >=', $since);
            return (int) $this->db->count_all_results(self::LOGIN_ATTEMPT_TABLE);
        };

        $blocked = $failuresFor('email', $email) >= $config['email_attempts']
            || $failuresFor('ip_address', $ip) >= $config['ip_attempts'];

        return array('blocked' => $blocked, 'retry_after' => $config['window']);
    }

    public function recordLoginAttempt($email, $ip, $successful)
    {
        $this->db->insert(self::LOGIN_ATTEMPT_TABLE, array(
            'email'        => strtolower(trim((string) $email)),
            'ip_address'   => substr(trim((string) $ip), 0, 45),
            'successful'   => $successful ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s'),
        ));

        // A successful sign-in clears the slate for that address so a legitimate
        // user is never locked out by their own earlier typos.
        if ($successful) {
            $this->db->where('email', strtolower(trim((string) $email)));
            $this->db->where('successful', 0);
            $this->db->delete(self::LOGIN_ATTEMPT_TABLE);
        }

        // Opportunistic prune so the table cannot grow without bound.
        if (mt_rand(1, 20) === 1) {
            $this->db->where('attempted_at <', date('Y-m-d H:i:s', time() - 86400));
            $this->db->delete(self::LOGIN_ATTEMPT_TABLE);
        }
    }

    /**
     * This function used to check the login credentials of the user
     * @param string $email : This is email of the user
     * @param string $password : This is encrypted password of the user
     */
    function loginMe($email, $password)
    {
        $this->db->select('BaseTbl.userId, BaseTbl.password, BaseTbl.name, BaseTbl.roleId, Roles.role');
        $this->db->from('tbl_users as BaseTbl');
        $this->db->join('tbl_roles as Roles','Roles.roleId = BaseTbl.roleId');
        $this->db->where('BaseTbl.email', $email);
        $this->db->where('BaseTbl.isDeleted', 0);
        $query = $this->db->get();
        
        $user = $query->row();
        
        if(!empty($user)){
            if(verifyHashedPassword($password, $user->password)){
                return $user;
            } else {
                return array();
            }
        } else {
            return array();
        }
    }

    /**
     * This function used to check email exists or not
     * @param {string} $email : This is users email id
     * @return {boolean} $result : TRUE/FALSE
     */
    function checkEmailExist($email)
    {
        $this->db->select('userId');
        $this->db->where('email', $email);
        $this->db->where('isDeleted', 0);
        $query = $this->db->get('tbl_users');

        if ($query->num_rows() > 0){
            return true;
        } else {
            return false;
        }
    }


    /**
     * This function used to insert reset password data
     * @param {array} $data : This is reset password data
     * @return {boolean} $result : TRUE/FALSE
     */
    function resetPasswordUser($data)
    {
        $this->db->trans_start();
        $this->db->query('SELECT userId FROM tbl_users WHERE email = ? AND isDeleted = 0 FOR UPDATE', array($data['email']));
        $this->db->where('createdDtm <', date('Y-m-d H:i:s', time() - 3600));
        $this->db->delete('tbl_reset_password');
        $this->db->delete('tbl_reset_password', array('email' => $data['email']));
        $this->db->insert('tbl_reset_password', $data);
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    function deleteResetPasswordToken($email, $activation_id)
    {
        $this->db->where('email', $email);
        $this->db->where_in('activation_id', $this->resetTokenValues($activation_id));
        return $this->db->delete('tbl_reset_password');
    }

    /**
     * This function is used to get customer information by email-id for forget password email
     * @param string $email : Email id of customer
     * @return object $result : Information of customer
     */
    function getCustomerInfoByEmail($email)
    {
        $this->db->select('userId, email, name');
        $this->db->from('tbl_users');
        $this->db->where('isDeleted', 0);
        $this->db->where('email', $email);
        $query = $this->db->get();

        return $query->row();
    }

    /**
     * This function used to check correct activation deatails for forget password.
     * @param string $email : Email id of user
     * @param string $activation_id : This is activation string
     */
    function checkActivationDetails($email, $activation_id)
    {
        $request = $this->getResetPasswordRequest($activation_id);
        return !empty($request) && strtolower($request->email) === strtolower($email) ? 1 : 0;
    }

    function getResetPasswordRequest($activation_id)
    {
        $this->db->select('id, email, createdDtm');
        $this->db->from('tbl_reset_password');
        $this->db->where_in('activation_id', $this->resetTokenValues($activation_id));
        $this->db->where('isDeleted', 0);
        $this->db->where('createdDtm >=', date('Y-m-d H:i:s', time() - 3600));
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $query = $this->db->get();
        return $query->row();
    }

    // This function used to create new password by reset link
    function createPasswordUser($password, $activation_id)
    {
        $tokenValues = $this->resetTokenValues($activation_id);
        $this->db->trans_begin();
        $query = $this->db->query(
            'SELECT id, email FROM tbl_reset_password WHERE activation_id IN (?, ?) AND isDeleted = 0 AND createdDtm >= ? ORDER BY id DESC LIMIT 1 FOR UPDATE',
            array($tokenValues[0], $tokenValues[1], date('Y-m-d H:i:s', time() - 3600))
        );
        $request = $query->row();

        if (empty($request)) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->where('email', $request->email);
        $this->db->where('isDeleted', 0);
        $this->db->update('tbl_users', array('password'=>getHashedPassword($password)));

        if ($this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->delete('tbl_reset_password', array('email'=>$request->email));
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return TRUE;
    }

    private function resetTokenValues($activation_id)
    {
        $activation_id = trim((string) $activation_id);
        return array($activation_id, substr(hash('sha256', $activation_id), 0, 32));
    }

    /**
     * This function used to save login information of user
     * @param array $loginInfo : This is users login information
     */
    function lastLogin($loginInfo)
    {
        $this->db->trans_start();
        $this->db->insert('tbl_last_login', $loginInfo);
        $this->db->trans_complete();
    }

    /**
     * This function is used to get last login info by user id
     * @param number $userId : This is user id
     * @return number $result : This is query result
     */
    function lastLoginInfo($userId)
    {
        $this->db->select('BaseTbl.createdDtm');
        $this->db->where('BaseTbl.userId', $userId);
        $this->db->order_by('BaseTbl.id', 'DESC');
        $this->db->limit(1);
        $query = $this->db->get('tbl_last_login as BaseTbl');

        return $query->row();
    }
}

?>
