<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class User extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
        $this->isLoggedIn();   
    }
    
    /**
     * This function used to load the first screen of the user
     */
    public function index()
    {
        $this->global['pageTitle'] = 'Job Seeker : Dashboard';
        
        $this->loadViews("dashboard", $this->global, NULL , NULL);
    }
    
    /**
     * This function is used to load the user list
     */
    function userListing()
    {
        if($this->isAdmin() == TRUE)
        {
            $this->loadThis();
        }
        else
        {        
            $searchText = $this->security->xss_clean($this->input->post('searchText'));
            $data['searchText'] = $searchText;
            
            $this->load->library('pagination');
            
            $count = $this->user_model->userListingCount($searchText);

			$returns = $this->paginationCompress ( "userListing/", $count, 10 );
            
            $data['userRecords'] = $this->user_model->userListing($searchText, $returns["page"], $returns["segment"]);
            
            $this->global['pageTitle'] = 'Job Seeker : User Listing';
            
            $this->loadViews("users", $this->global, $data, NULL);
        }
    }

      /**
     * This function is used to load the groups list
     */
    function groupsListing()
    {
        if($this->isAdmin() == TRUE)
        {
            $this->loadThis();
        }
        else
        {  
            $this->global['pageTitle'] = 'Job Seeker : Group Listing';

            $data['groups'] = $this->user_model->getUserGroups();
            
            $this->loadViews("groups", $this->global, $data, NULL);
        }
    }

    /**
     * This function is used to load the add new form
     */
    function addNew()
    {
        if($this->isAdmin() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->model('user_model');
            $data['roles'] = $this->user_model->getUserRoles();
            $data['groups'] = $this->user_model->getUserGroups();
            
            $this->global['pageTitle'] = 'Job Seeker : Add New User';

            $this->loadViews("addNew", $this->global, $data, NULL);
        }
    }

    /**
     * This function is used to check whether email already exist or not
     */
    function checkEmailExists()
    {
        $userId = $this->input->post("userId");
        $email = $this->input->post("email");

        if(empty($userId)){
            $result = $this->user_model->checkEmailExists($email);
        } else {
            $result = $this->user_model->checkEmailExists($email, $userId);
        }

        if(empty($result)){ echo("true"); }
        else { echo("false"); }
    }
    
    /**
     * This function is used to add new user to the system
     */
    function addNewUser()
    {
        if($this->isAdmin() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('fname','Full Name','trim|required|max_length[128]');
            $this->form_validation->set_rules('email','Email','trim|required|valid_email|max_length[128]');
            $this->form_validation->set_rules('password','Password','required|max_length[20]');
            $this->form_validation->set_rules('cpassword','Confirm Password','trim|required|matches[password]|max_length[20]');
            $this->form_validation->set_rules('role','Role','trim|required|numeric');
            $this->form_validation->set_rules('group','Group Name','trim|required|max_length[128]');
            $this->form_validation->set_rules('mobile','Mobile Number','required|min_length[12]');
            
            if($this->form_validation->run() == FALSE)
            {
                $this->addNew();
            }
            else
            {
                $name = ucwords(strtolower($this->security->xss_clean($this->input->post('fname'))));
                $email = strtolower($this->security->xss_clean($this->input->post('email')));
                $password = $this->input->post('password');
                $roleId = $this->input->post('role');
                $mobile = $this->security->xss_clean($this->input->post('mobile'));
                $group = $this->security->xss_clean($this->input->post('group'));
                
                $userInfo = array('email'=>$email, 'password'=>getHashedPassword($password), 'roleId'=>$roleId, 'groupId' => $group, 'name'=> $name,
                                    'mobile'=>$mobile, 'createdBy'=>$this->vendorId, 'createdDtm'=>date('Y-m-d H:i:s'));
                
                $this->load->model('user_model');
                $result = $this->user_model->addNewUser($userInfo);
                
                if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New User created successfully');
                }
                else
                {
                    $this->session->set_flashdata('error', 'User creation failed');
                }
                
                redirect('addNew');
            }
        }
    }


    /**
     * This function is used to add new group to the system
     */
    function addNewGroup()
    {
        if($this->isAdmin() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->library('form_validation');
            
            $this->form_validation->set_rules('name','Group Name','trim|required|max_length[30]');
            
            if($this->form_validation->run() == FALSE)
            {
                $this->groupsListing();
            }
            else
            {
                $name = ucwords(strtolower($this->security->xss_clean($this->input->post('name'))));

                $userInfo = array('name'=> $name,'owner'=>$this->name, 'creation_date'=>date('Y-m-d H:i:s'));
                
                $this->load->model('user_model');
                $validate = $this->user_model->validateGroup($name);

                if ($validate >=  1) {
                   $this->session->set_flashdata('error', 'Group creation failed - This group name already exist');
                } else {
                    
                     $result = $this->user_model->addNewGroup($userInfo);
                     if($result > 0)
                {
                    $this->session->set_flashdata('success', 'New Group created successfully');
                }
                else
                {
                    $this->session->set_flashdata('error', 'Group creation failed');
                }

                }
                
                
                redirect('User/groupsListing');
            }
        }
    }

    
    /**
     * This function is used load user edit information
     * @param number $userId : Optional : This is user id
     */
    function editOld($userId = NULL)
    {
        if($this->isAdmin() == TRUE || $userId == 1)
        {
            $this->loadThis();
        }
        else
        {
            if($userId == null)
            {
                redirect('userListing');
            }
            
            $data['roles'] = $this->user_model->getUserRoles();
            $data['groups'] = $this->user_model->getUserGroups();
            $data['userInfo'] = $this->user_model->getUserInfo($userId);
            
            $this->global['pageTitle'] = 'Job Seeker : Edit User';
            
            $this->loadViews("editOld", $this->global, $data, NULL);
        }
    }
    
    
    /**
     * This function is used to edit the user information
     */
    function editUser()
    {
        if($this->isAdmin() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $this->load->library('form_validation');
            
            $userId = $this->input->post('userId');
            
            $this->form_validation->set_rules('fname','Full Name','trim|required|max_length[128]');
            $this->form_validation->set_rules('email','Email','trim|required|valid_email|max_length[128]');
            $this->form_validation->set_rules('password','Password','matches[cpassword]|max_length[20]');
            $this->form_validation->set_rules('cpassword','Confirm Password','matches[password]|max_length[20]');
            $this->form_validation->set_rules('role','Role','trim|required|numeric');
            $this->form_validation->set_rules('mobile','Mobile Number','required|min_length[10]');
            $this->form_validation->set_rules('group','Group Name','trim|required|max_length[128]');
            
            if($this->form_validation->run() == FALSE)
            {
                $this->editOld($userId);
            }
            else
            {
                $name = ucwords(strtolower($this->security->xss_clean($this->input->post('fname'))));
                $email = strtolower($this->security->xss_clean($this->input->post('email')));
                $password = $this->input->post('password');
                $roleId = $this->input->post('role');
                $mobile = $this->security->xss_clean($this->input->post('mobile'));
                $group = $this->input->post('group');
                
                $userInfo = array();
                
                if(empty($password))
                {
                    $userInfo = array('email'=>$email, 'roleId'=>$roleId, 'name' => $name,'groupId' => $group,'mobile'=>$mobile, 'updatedBy'=>$this->vendorId, 'updatedDtm'=>date('Y-m-d H:i:s'));
                }
                else
                {
                    $userInfo = array('email'=>$email, 'password'=>getHashedPassword($password), 'roleId'=>$roleId,
                        'name'=>ucwords($name), 'mobile'=>$mobile, 'updatedBy'=>$this->vendorId, 
                        'updatedDtm'=>date('Y-m-d H:i:s'));
                }
                
                $result = $this->user_model->editUser($userInfo, $userId);
                
                if($result == true)
                {
                    $this->session->set_flashdata('success', 'User updated successfully');
                }
                else
                {
                    $this->session->set_flashdata('error', 'User updation failed');
                }
                
                redirect('userListing');
            }
        }
    }


    /**
     * This function is used to delete the user using userId
     * @return boolean $result : TRUE / FALSE
     */
    function deleteUser()
    {
        if($this->isAdmin() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            if($this->input->method(TRUE) !== 'POST') {
                $this->output->set_status_header(405);
                echo(json_encode(array('status'=>FALSE, 'message'=>'Delete requests must use POST.')));
                return;
            }

            $userId = $this->input->post('userId');
            $userInfo = array('isDeleted'=>1,'updatedBy'=>$this->vendorId, 'updatedDtm'=>date('Y-m-d H:i:s'));
            
            $result = $this->user_model->deleteUser($userId, $userInfo);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE))); }
            else { echo(json_encode(array('status'=>FALSE))); }
        }
    }

        /**
     * This function is used to delete the group using userId
     * @return boolean $result : TRUE / FALSE
     */
    function deleteGroup()
    {
        if($this->isAdmin() == TRUE)
        {
            echo(json_encode(array('status'=>'access')));
        }
        else
        {
            if($this->input->method(TRUE) !== 'POST') {
                $this->output->set_status_header(405);
                echo(json_encode(array('status'=>FALSE, 'message'=>'Delete requests must use POST.')));
                return;
            }

            $userId = $this->input->post('userId');
            
            $result = $this->user_model->deleteGroup($userId);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE))); }
            else { echo(json_encode(array('status'=>FALSE))); }
        }
    }
    
    /**
     * Page not found : error 404
     */
    function pageNotFound()
    {
        $this->global['pageTitle'] = 'Job Seeker : 404 - Page Not Found';
        
        $this->loadViews("404", $this->global, NULL, NULL);
    }

    /**
     * This function used to show login history
     * @param number $userId : This is user id
     */
    function loginHistoy($userId = NULL)
    {
        if($this->isAdmin() == TRUE)
        {
            $this->loadThis();
        }
        else
        {
            $userId = ($userId == NULL ? 0 : $userId);

            $searchText = $this->input->post('searchText');
            $fromDate = $this->input->post('fromDate');
            $toDate = $this->input->post('toDate');

            $data["userInfo"] = $this->user_model->getUserInfoById($userId);

            $data['searchText'] = $searchText;
            $data['fromDate'] = $fromDate;
            $data['toDate'] = $toDate;
            
            $this->load->library('pagination');
            
            $count = $this->user_model->loginHistoryCount($userId, $searchText, $fromDate, $toDate);

            $returns = $this->paginationCompress ( "login-history/".$userId."/", $count, 10, 3);

            $data['userRecords'] = $this->user_model->loginHistory($userId, $searchText, $fromDate, $toDate, $returns["page"], $returns["segment"]);
            
            $this->global['pageTitle'] = 'Job Seeker : User Login History';
            
            $this->loadViews("loginHistory", $this->global, $data, NULL);
        }        
    }

    /**
     * This function is used to show users profile
     */
    function profile($active = "details")
    {
        $data["userInfo"] = $this->user_model->getUserInfoWithRole($this->vendorId);
        $data["active"] = $active;
        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $data["gitAccounts"] = $this->gitAccounts->accounts($this->vendorId);
        $this->load->library('GitHubOAuth');
        $data['githubOAuthEnabled'] = $this->githuboauth->enabled();
        $data['githubOAuthCallback'] = base_url('github/callback');
        
        $profileTitles = array(
            'details' => 'Job Seeker : My Profile',
            'changepass' => 'Job Seeker : Change Password',
            'git' => 'Job Seeker : Git Accounts'
        );
        $this->global['pageTitle'] = isset($profileTitles[$active]) ? $profileTitles[$active] : $profileTitles['details'];
        $this->loadViews("profile", $this->global, $data, NULL);
    }

    /** Add or update one repository-scoped personal Git credential. */
    function gitAccountSave()
    {
        $providers = array('github', 'gitlab', 'bitbucket', 'azure_devops', 'generic');
        $authTypes = array('token', 'username_password', 'ssh_key', 'ssh_generate');
        $provider = strtolower(trim((string) $this->input->post('provider')));
        $host = strtolower(trim((string) $this->input->post('host')));
        $pathPrefix = strtolower(trim(preg_replace('#\.git$#i', '', (string) $this->input->post('path_prefix')), '/'));
        $authType = strtolower(trim((string) $this->input->post('auth_type')));
        $label = trim((string) $this->input->post('label'));
        $username = trim((string) $this->input->post('username'));
        $secretValue = (string) $this->input->post('secret');
        $knownHosts = trim((string) $this->input->post('known_hosts'));
        // JobSeeker can create the key pair, so the private key never leaves
        // the server; the person adds the public key to their provider.
        $generated = FALSE;
        if ($authType === 'ssh_generate') {
            $this->load->library('GitSshKeys');
            $secretValue = $this->gitsshkeys->generate('jobseeker-'.$this->vendorId.'@'.$host);
            $authType = 'ssh_key';
            $generated = $secretValue !== '';
        }

        $valid = in_array($provider, $providers, TRUE) && in_array($authType, $authTypes, TRUE)
            && preg_match('/^[a-z0-9.-]+(:[0-9]{1,5})?$/', $host)
            && ($pathPrefix === '' || (strlen($pathPrefix) <= 255 && preg_match('#^[a-z0-9._/-]+$#', $pathPrefix) && strpos($pathPrefix, '..') === FALSE && strpos($pathPrefix, '//') === FALSE))
            && strlen($label) <= 255 && ! preg_match('/[\x00-\x1F\x7F]/', $label)
            && strlen($username) <= 255 && ! preg_match('/[\x00-\x1F\x7F]/', $username)
            && $secretValue !== '' && strlen($secretValue) <= 50000 && strpos($secretValue, "\0") === FALSE;
        if ($authType === 'username_password' && $username === '') {
            $valid = FALSE;
        }
        if ($authType !== 'ssh_key' && preg_match('/[\r\n]/', $secretValue)) {
            $valid = FALSE;
        }

        $metadata = array('public_key' => NULL, 'fingerprint' => '');
        if ($valid && $authType === 'ssh_key') {
            $this->load->library('GitSshKeys');
            $metadata = $this->gitsshkeys->metadata($secretValue);
            $valid = ! isset($metadata['error']) && $knownHosts !== '' && strlen($knownHosts) <= 50000
                && preg_match('/(?:^|\n)[^#\s]\S*\s+(?:ssh-|ecdsa-)[A-Za-z0-9+\/=]+/m', $knownHosts);
        }

        if (! $valid) {
            $this->session->set_flashdata('error', 'Check the provider, host, optional repository scope, authentication fields, and secret. SSH keys must be unencrypted and include pinned known_hosts entries.');
            redirect('profile/git');
            return;
        }

        $secret = $authType === 'ssh_key'
            ? array('private_key' => $secretValue, 'known_hosts' => $knownHosts."\n")
            : array($authType === 'token' ? 'token' : 'password' => $secretValue);
        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $saved = $this->gitAccounts->save($this->vendorId, array(
            'id' => (int) $this->input->post('id'), 'provider' => $provider,
            'label' => $label, 'host' => $host, 'path_prefix' => $pathPrefix,
            'auth_type' => $authType, 'username' => $username, 'secret' => $secret,
            'public_key' => $metadata['public_key'], 'fingerprint' => $metadata['fingerprint']
        ));
        $scope = $pathPrefix === '' ? $host : $host.'/'.$pathPrefix;
        $this->session->set_flashdata($saved ? 'success' : 'error', $saved
            ? ($generated ? 'SSH key for '.$scope.' created. Add its public key (shown on the account) to your '.$host.' account, then test it.' : 'Git account for '.$scope.' saved.')
            : 'The Git account could not be saved.');
        if ($saved && $generated) {
            $this->session->set_flashdata('git_account_highlight', (int) $saved);
        }
        redirect('profile/git');
    }

    /**
     * The provider's current SSH host keys and their fingerprints, for the
     * person to compare with the fingerprints the provider publishes before
     * pinning them. Strict host-key checking stays on; nothing is trusted
     * automatically.
     */
    function gitKnownHosts()
    {
        $this->output->set_content_type('application/json')->set_header('Cache-Control: no-store, max-age=0');
        $host = strtolower(trim((string) $this->input->get('host')));
        if (! preg_match('/^([a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?)(?::([0-9]{1,5}))?$/', $host, $parts)) {
            $this->output->set_status_header(400)->set_output(json_encode(array('ok' => FALSE, 'message' => 'Enter a provider host such as github.com.')));
            return;
        }
        $command = array('timeout', '15', 'ssh-keyscan', '-T', '8');
        if (! empty($parts[2])) {
            $command[] = '-p';
            $command[] = (string) (int) $parts[2];
        }
        $command[] = $parts[1];
        $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, NULL, array('PATH' => '/usr/local/bin:/usr/bin:/bin'));
        $output = '';
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        $lines = array();
        foreach (preg_split('/\r?\n/', (string) $output) as $line) {
            if (preg_match('/^[^#\s]\S*\s+(?:ssh-|ecdsa-)[A-Za-z0-9-]+\s+[A-Za-z0-9+\/=]+$/', trim($line))) {
                $lines[] = trim($line);
            }
        }
        if (empty($lines)) {
            $this->output->set_status_header(502)->set_output(json_encode(array('ok' => FALSE, 'message' => 'No SSH host keys were returned by '.$host.'. Paste them from the provider documentation instead.')));
            return;
        }
        $fingerprints = array();
        foreach ($lines as $line) {
            $process = proc_open(array('ssh-keygen', '-lf', '-'), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, NULL, array('PATH' => '/usr/local/bin:/usr/bin:/bin'));
            if (is_resource($process)) {
                fwrite($pipes[0], $line."\n");
                fclose($pipes[0]);
                $fingerprint = trim((string) stream_get_contents($pipes[1]));
                stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                if ($fingerprint !== '') {
                    $fingerprints[] = $fingerprint;
                }
            }
        }
        $this->output->set_output(json_encode(array('ok' => TRUE, 'known_hosts' => implode("\n", $lines), 'fingerprints' => $fingerprints)));
    }

    function gitAccountDelete()
    {
        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $deleted = $this->gitAccounts->delete($this->vendorId, $this->input->post('id'));
        $this->session->set_flashdata($deleted ? 'success' : 'error', $deleted ? 'Git account removed.' : 'The Git account was not found.');
        redirect('profile/git');
    }

    /** Start GitHub's browser-based authorization-code flow. */
    function githubConnect()
    {
        $this->load->library('GitHubOAuth');
        if (! $this->githuboauth->enabled()) {
            $this->session->set_flashdata('error', 'GitHub web authorization is not configured on this JobSeeker deployment.');
            redirect('profile/git');
            return;
        }
        $state = bin2hex(random_bytes(24));
        $this->session->set_userdata('github_oauth_state', $state);
        $this->session->set_userdata('github_oauth_started_at', time());
        // Only known pages, so the callback can never become an open redirect.
        $this->session->set_userdata('github_oauth_return', $this->input->get('return') === 'JobCreation' ? 'JobCreation' : 'profile/git');
        redirect($this->githuboauth->authorizationUrl(base_url('github/callback'), $state));
    }

    /** Finish GitHub authorization and save the encrypted user token. */
    function githubCallback()
    {
        $expectedState = (string) $this->session->userdata('github_oauth_state');
        $startedAt = (int) $this->session->userdata('github_oauth_started_at');
        $returnTo = $this->session->userdata('github_oauth_return') === 'JobCreation' ? 'JobCreation' : 'profile/git';
        $this->session->unset_userdata(array('github_oauth_state', 'github_oauth_started_at', 'github_oauth_return'));
        $state = (string) $this->input->get('state', TRUE);
        $code = (string) $this->input->get('code', TRUE);
        $error = trim((string) $this->input->get('error_description', TRUE));
        if ($error !== '') {
            $this->session->set_flashdata('error', 'GitHub authorization was cancelled or not completed. Start it again when you are ready.');
            redirect('profile/git');
            return;
        }
        if ($expectedState === '' || ! hash_equals($expectedState, $state) || $startedAt <= 0 || time() - $startedAt > 900 || $code === '') {
            $this->session->set_flashdata('error', 'GitHub authorization could not be verified. Start it again from your profile.');
            redirect('profile/git');
            return;
        }

        $this->load->library('GitHubOAuth');
        $tokenResult = $this->githuboauth->exchangeCode($code, base_url('github/callback'));
        if (empty($tokenResult['ok'])) {
            $this->session->set_flashdata('error', isset($tokenResult['message']) ? $tokenResult['message'] : 'GitHub authorization failed.');
            redirect('profile/git');
            return;
        }
        $userResult = $this->githuboauth->user($tokenResult['token']['access_token']);
        if (empty($userResult['ok'])) {
            $this->session->set_flashdata('error', $userResult['message']);
            redirect('profile/git');
            return;
        }

        $login = (string) $userResult['user']['login'];
        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $saved = $this->gitAccounts->save($this->vendorId, array(
            'provider' => 'github', 'label' => 'GitHub · '.$login,
            'host' => 'github.com', 'path_prefix' => '', 'auth_type' => 'token',
            'username' => $login, 'secret' => $this->gitAccounts->githubTokenSecret($tokenResult['token']),
            'public_key' => NULL, 'fingerprint' => ''
        ));
        $this->session->set_flashdata($saved ? 'success' : 'error', $saved
            ? 'GitHub account '.$login.' connected. Private repositories allowed by the authorization are now available to your VS Code workspaces.'
            : 'GitHub authorization succeeded, but the account could not be saved.');
        redirect($saved ? $returnTo : 'profile/git');
    }

    /** Test one saved personal account against an exact repository. */
    function gitAccountTest()
    {
        $this->output->set_content_type('application/json')->set_header('Cache-Control: no-store, max-age=0');
        if ($this->input->method(TRUE) !== 'POST') {
            $this->output->set_status_header(405)->set_output(json_encode(array('ok' => FALSE, 'message' => 'Use POST.')));
            return;
        }
        $this->load->library('GitBranchPolicy');
        $repositoryUrl = $this->gitbranchpolicy->cleanRepositoryUrl($this->input->post('repository_url'));
        $branch = $this->gitbranchpolicy->cleanBranch($this->input->post('branch'));
        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $account = $this->gitAccounts->credentialById($this->vendorId, (int) $this->input->post('account_id'));
        if ($repositoryUrl === FALSE || $branch === FALSE || $account === FALSE) {
            $this->output->set_status_header(400)->set_output(json_encode(array('ok' => FALSE, 'message' => 'Select an account and enter a valid repository URL and optional branch.')));
            return;
        }
        $location = $this->profileGitRepositoryLocation($repositoryUrl);
        $accountHost = preg_replace('/:\d+$/', '', strtolower((string) $account['host']));
        if ($location['host'] === '' || $accountHost !== $location['host']) {
            $this->output->set_status_header(422)->set_output(json_encode(array('ok' => FALSE, 'message' => 'This account is scoped to '.$account['host'].', not '.$location['host'].'.')));
            return;
        }
        $path = strtolower(trim(preg_replace('#\.git$#i', '', $location['path']), '/'));
        $scope = strtolower(trim((string) $account['path_prefix'], '/'));
        if ($scope !== '' && $path !== $scope && strpos($path, $scope.'/') !== 0) {
            $this->output->set_status_header(422)->set_output(json_encode(array('ok' => FALSE, 'message' => 'This account is scoped to '.$scope.', not '.$path.'.')));
            return;
        }

        $result = $this->runPersonalGitTest($account, $repositoryUrl, $branch);
        $this->output->set_status_header($result['ok'] ? 200 : 422)->set_output(json_encode($result));
    }

    private function profileGitRepositoryLocation($url)
    {
        if (preg_match('/^[A-Za-z0-9._%+\-]+@([A-Za-z0-9._\-]+):(.+)$/', (string) $url, $match)) {
            return array('host' => strtolower($match[1]), 'path' => trim($match[2], '/'));
        }
        return array('host' => strtolower((string) parse_url($url, PHP_URL_HOST)), 'path' => trim((string) parse_url($url, PHP_URL_PATH), '/'));
    }

    private function runPersonalGitTest($account, $repositoryUrl, $branch)
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jobseeker-profile-git-test-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0700)) {
            return array('ok' => FALSE, 'message' => 'A secure temporary credential directory could not be created.');
        }
        $location = $this->profileGitRepositoryLocation($repositoryUrl);
        $providerDefaults = array('github' => 'x-access-token', 'gitlab' => 'oauth2', 'bitbucket' => 'x-token-auth', 'azure_devops' => 'git');
        $values = array('host' => $location['host'], 'auth_type' => $account['auth_type']);
        if ($account['auth_type'] === 'ssh_key') {
            $values['private_key'] = isset($account['secret']['private_key']) ? $account['secret']['private_key'] : '';
            $values['known_hosts'] = isset($account['secret']['known_hosts']) ? $account['secret']['known_hosts'] : '';
        } else {
            $values['username'] = $account['username'] !== '' ? $account['username'] : (isset($providerDefaults[$account['provider']]) ? $providerDefaults[$account['provider']] : 'git');
            $secretKey = $account['auth_type'] === 'username_password' ? 'password' : 'token';
            $values[$secretKey] = isset($account['secret'][$secretKey]) ? $account['secret'][$secretKey] : '';
        }
        foreach ($values as $name => $value) {
            file_put_contents($directory.DIRECTORY_SEPARATOR.$name, (string) $value, LOCK_EX);
            chmod($directory.DIRECTORY_SEPARATOR.$name, 0600);
        }
        $command = array('timeout', '20', 'jobseeker-git', 'ls-remote', '--connector-dir', $directory);
        if ($branch !== '') {
            $command[] = '--branch';
            $command[] = $branch;
        }
        $command[] = '--';
        $command[] = $repositoryUrl;
        $started = microtime(TRUE);
        $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('redirect', 1)), $pipes, NULL, array('PATH' => '/usr/local/bin:/usr/bin:/bin', 'HOME' => sys_get_temp_dir(), 'GIT_TERMINAL_PROMPT' => '0'));
        $output = '';
        $code = 1;
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $code = proc_close($process);
        }
        foreach (array_keys($values) as $name) {
            @unlink($directory.DIRECTORY_SEPARATOR.$name);
        }
        @rmdir($directory);
        $latency = (int) round((microtime(TRUE) - $started) * 1000);
        if ($code === 0) {
            return array('ok' => TRUE, 'status' => 'passed', 'latencyMs' => $latency, 'message' => 'Authentication succeeded and the repository reference is readable.');
        }
        $message = $code === 124 ? 'The Git provider did not respond within 20 seconds.' : 'Authentication failed or the repository/branch is not accessible to this account.';
        return array('ok' => FALSE, 'status' => $code === 124 ? 'timeout' : 'auth_failed', 'latencyMs' => $latency, 'message' => $message);
    }

    /**
     * This function is used to update the user details
     * @param text $active : This is flag to set the active tab
     */
    function profileUpdate($active = "details")
    {
        $this->load->library('form_validation');
            
        $this->form_validation->set_rules('fname','Full Name','trim|required|max_length[128]');
        $this->form_validation->set_rules('mobile','Mobile Number','required|min_length[10]');
        $this->form_validation->set_rules('email','Email','trim|required|valid_email|max_length[128]|callback_emailExists');        
        
        if($this->form_validation->run() == FALSE)
        {
            $this->profile($active);
        }
        else
        {
            $name = ucwords(strtolower($this->security->xss_clean($this->input->post('fname'))));
            $mobile = $this->security->xss_clean($this->input->post('mobile'));
            $email = strtolower($this->security->xss_clean($this->input->post('email')));
            
            $userInfo = array('name'=>$name, 'email'=>$email, 'mobile'=>$mobile, 'updatedBy'=>$this->vendorId, 'updatedDtm'=>date('Y-m-d H:i:s'));
            
            $result = $this->user_model->editUser($userInfo, $this->vendorId);
            
            if($result == true)
            {
                $this->session->set_userdata('name', $name);
                $this->session->set_flashdata('success', 'Profile updated successfully');
            }
            else
            {
                $this->session->set_flashdata('error', 'Profile updation failed');
            }

            redirect('profile/'.$active);
        }
    }

    /**
     * This function is used to change the password of the user
     * @param text $active : This is flag to set the active tab
     */
    function changePassword($active = "changepass")
    {
        $this->load->library('form_validation');
        
        $this->form_validation->set_rules('oldPassword','Old password','required|max_length[20]');
        $this->form_validation->set_rules('newPassword','New password','required|max_length[20]');
        $this->form_validation->set_rules('cNewPassword','Confirm new password','required|matches[newPassword]|max_length[20]');
        
        if($this->form_validation->run() == FALSE)
        {
            $this->profile($active);
        }
        else
        {
            $oldPassword = $this->input->post('oldPassword');
            $newPassword = $this->input->post('newPassword');
            
            $resultPas = $this->user_model->matchOldPassword($this->vendorId, $oldPassword);
            
            if(empty($resultPas))
            {
                $this->session->set_flashdata('nomatch', 'Your old password is not correct');
                redirect('profile/'.$active);
            }
            else
            {
                $usersData = array('password'=>getHashedPassword($newPassword), 'updatedBy'=>$this->vendorId,
                                'updatedDtm'=>date('Y-m-d H:i:s'));
                
                $result = $this->user_model->changePassword($this->vendorId, $usersData);
                
                if($result > 0) { $this->session->set_flashdata('success', 'Password updation successful'); }
                else { $this->session->set_flashdata('error', 'Password updation failed'); }
                
                redirect('profile/'.$active);
            }
        }
    }

    /**
     * This function is used to check whether email already exist or not
     * @param {string} $email : This is users email
     */
    function emailExists($email)
    {
        $userId = $this->vendorId;
        $return = false;

        if(empty($userId)){
            $result = $this->user_model->checkEmailExists($email);
        } else {
            $result = $this->user_model->checkEmailExists($email, $userId);
        }

        if(empty($result)){ $return = true; }
        else {
            $this->form_validation->set_message('emailExists', 'The {field} already taken');
            $return = false;
        }

        return $return;
    }
}

?>
