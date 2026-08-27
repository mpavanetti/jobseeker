<?php if(!defined('BASEPATH')) exit('No direct script access allowed');


class Login extends CI_Controller
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('login_model');
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {
        //$this->setup();
        $this->isLoggedIn();
    }
    
    /**
     * This function used to check the user is logged in or not
     */
    function isLoggedIn()
    {
       // $this->setup();
        $isLoggedIn = $this->session->userdata('isLoggedIn');
        
        if(!isset($isLoggedIn) || $isLoggedIn != TRUE)
        {
            $this->load->view('login');
        }
        else
        {
            redirect('/dashboard');
        }
    }
    
    
    /**
     * This function used to logged in user
     */
    public function loginMe()
    {
        $this->load->library('form_validation');
        
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[128]|trim');
        $this->form_validation->set_rules('password', 'Password', 'required|max_length[64]');
        
        if($this->form_validation->run() == FALSE)
        {
            $this->index();
        }
        else
        {
            $email = strtolower($this->security->xss_clean($this->input->post('email')));
            $password = $this->input->post('password');
            
            $result = $this->login_model->loginMe($email, $password);
            
            if(!empty($result))
            {
                $lastLogin = $this->login_model->lastLoginInfo($result->userId);
                $lastLoginTime = empty($lastLogin) ? '' : $lastLogin->createdDtm;

                $sessionArray = array('userId'=>$result->userId,                    
                                        'role'=>$result->roleId,
                                        'roleText'=>$result->role,
                                        'name'=>$result->name,
                                        'lastLogin'=> $lastLoginTime,
                                        'isLoggedIn' => TRUE
                                );

                            $this->session->sess_regenerate(TRUE);
                $this->session->set_userdata($sessionArray);

                unset($sessionArray['userId'], $sessionArray['isLoggedIn'], $sessionArray['lastLogin']);

                $loginInfo = array("userId"=>$result->userId, "sessionData" => json_encode($sessionArray), "machineIp"=>$_SERVER['REMOTE_ADDR'], "userAgent"=>getBrowserAgent(), "agentString"=>$this->agent->agent_string(), "platform"=>$this->agent->platform());

                $this->login_model->lastLogin($loginInfo);
                
                redirect('/dashboard');
            }
            else
            {
                $this->session->set_flashdata('error', 'Email or password mismatch');
                
                $this->index();
            }
        }
    }

    /**
     * This function used to load forgot password view
     */
    public function forgotPassword()
    {
        $isLoggedIn = $this->session->userdata('isLoggedIn');
        
        if(!isset($isLoggedIn) || $isLoggedIn != TRUE)
        {
            $this->load->view('forgotPassword');
        }
        else
        {
            redirect('/dashboard');
        }
    }
    
    /**
     * This function used to generate reset password request link
     */
    function resetPasswordUser()
    {
        $this->load->library('form_validation');
        
        $this->form_validation->set_rules('login_email','Email','trim|required|valid_email|max_length[128]');
                
        if($this->form_validation->run() == FALSE)
        {
            $this->forgotPassword();
        }
        else 
        {
            $email = strtolower(trim((string) $this->security->xss_clean($this->input->post('login_email'))));
            $genericMessage = "If the address is registered, a password reset link has been sent.";
            
            if($this->login_model->checkEmailExist($email))
            {
                $data['email'] = $email;
                try {
                    $resetToken = bin2hex(random_bytes(16));
                } catch (Exception $error) {
                    log_message('error', 'A cryptographically secure password reset token could not be generated.');
                    setFlashData('send', $genericMessage);
                    redirect('/forgotPassword');
                    return;
                }
                $data['activation_id'] = substr(hash('sha256', $resetToken), 0, 32);
                $data['createdDtm'] = date('Y-m-d H:i:s');
                $data['agent'] = getBrowserAgent();
                $data['client_ip'] = $this->input->ip_address();
                
                $save = $this->login_model->resetPasswordUser($data);                
                
                if($save)
                {
                    $data1['reset_link'] = base_url() . "resetPasswordConfirmUser/" . $resetToken;
                    $userInfo = $this->login_model->getCustomerInfoByEmail($email);
                    $data1["name"] = !empty($userInfo) && trim((string) $userInfo->name) !== '' ? $userInfo->name : 'there';
                    $data1["email"] = $email;
                    $data1["requested_at"] = date('Y-m-d H:i:s T');
                    $data1["client_ip"] = $data['client_ip'];

                    $sendStatus = resetPasswordEmail($data1);

                    if(!$sendStatus){
                        $this->login_model->deleteResetPasswordToken($email, $data['activation_id']);
                        log_message('error', 'Password reset email could not be sent for a registered account.');
                    }
                }
                else
                {
                    log_message('error', 'Password reset request could not be stored for a registered account.');
                }
            }
            setFlashData('send', $genericMessage);
            redirect('/forgotPassword');
        }
    }

    /**
     * This function used to reset the password 
     * @param string $activation_id : This is unique id
     * @param string $email : This is user email
     */
    function resetPasswordConfirmUser($activation_id, $email = '')
    {
        $activation_id = trim((string) $activation_id);

        if (!preg_match('/^[a-f0-9]{32}$/i', $activation_id)) {
            setFlashData('error', 'This password reset link is invalid or expired.');
            redirect('/forgotPassword');
            return;
        }
        
        $request = $this->login_model->getResetPasswordRequest($activation_id);
        $legacyEmail = strtolower(urldecode((string) $email));
        if (empty($request) || ($legacyEmail !== '' && strtolower($request->email) !== $legacyEmail)) {
            setFlashData('error', 'This password reset link is invalid or expired.');
            redirect('/forgotPassword');
            return;
        }

        $data['email'] = $request->email;
        $data['activation_code'] = $activation_id;
        $this->load->view('newPassword', $data);
    }
    
    /**
     * This function used to create new password for user
     */
    function createPasswordUser()
    {
        $activation_id = trim((string) $this->input->post("activation_code"));
        
        $this->load->library('form_validation');
        
        $this->form_validation->set_rules('password','Password','required|min_length[8]|max_length[64]');
        $this->form_validation->set_rules('cpassword','Confirm Password','trim|required|matches[password]|min_length[8]|max_length[64]');
        
        if($this->form_validation->run() == FALSE)
        {
            $this->resetPasswordConfirmUser($activation_id);
        }
        else
        {
            $password = $this->input->post('password');

            if ($this->login_model->createPasswordUser($password, $activation_id)) {
                setFlashData('success', 'Password reset successfully. Sign in with your new password.');
                redirect('/login');
            }

            setFlashData('error', 'This password reset link is invalid or expired. Request a new link.');
            redirect('/forgotPassword');
        }
    }
}

?>
