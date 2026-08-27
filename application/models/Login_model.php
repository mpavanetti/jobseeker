<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Login_model extends CI_Model
{
    
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
