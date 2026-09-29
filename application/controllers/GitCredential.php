<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

/**
 * Answers the Git credential helper in OpenVSCode workspaces (see
 * docker/openvscode/jobseeker-git-credential and GitCredentialSession) with the
 * personal Git account of the user who opened the workspace, in Git's
 * credential format. Nothing is cached on the editor side.
 */
class GitCredential extends BaseController
{
    public function index()
    {
        $this->output->set_header('Cache-Control: no-store, max-age=0')->set_content_type('text/plain');
        $authorization = trim((string) $this->input->get_request_header('Authorization', TRUE));
        $host = strtolower(trim((string) $this->input->post('host')));
        $path = strtolower(trim((string) $this->input->post('path'), '/'));
        $protocol = strtolower(trim((string) $this->input->post('protocol')));
        if ($this->input->method(TRUE) !== 'POST' || strncmp($authorization, 'Bearer ', 7) !== 0
            || ! preg_match('/^[a-z0-9.-]+(:[0-9]{1,5})?$/', $host)
            || strlen($path) > 1000 || strpos($path, '..') !== FALSE || preg_match('/[\x00-\x1F\x7F]/', $path)
            || ($protocol !== '' && ! in_array($protocol, array('http', 'https', 'ssh'), TRUE))) {
            $this->output->set_status_header(400);
            return;
        }

        $this->load->library('GitCredentialSession');
        $claims = $this->gitcredentialsession->verify(substr($authorization, 7));
        if ($claims === FALSE) {
            $this->output->set_status_header(401)->set_output("The workspace session is invalid. Reopen the workspace from JobSeeker.\n");
            return;
        }
        $normalizedPath = strtolower(trim(preg_replace('#\.git$#i', '', $path), '/'));
        if (! hash_equals((string) $claims['host'], $host) || ! hash_equals((string) $claims['path'], $normalizedPath)) {
            $this->output->set_status_header(403)->set_output("This workspace session is scoped to a different repository.\n");
            return;
        }

        $this->load->model('UserGitAccount_model', 'gitAccounts');
        $transport = $protocol === 'ssh' ? 'ssh' : 'http';
        $credential = $this->gitAccounts->credential($claims['uid'], $host, $path, $transport);
        if ($credential === FALSE) {
            $this->output->set_status_header(404)->set_output("No Git account for ".$host." on your JobSeeker profile.\n");
            return;
        }
        if ($transport === 'ssh') {
            $privateKey = isset($credential['secret']['private_key']) ? $credential['secret']['private_key'] : '';
            $knownHosts = isset($credential['secret']['known_hosts']) ? $credential['secret']['known_hosts'] : '';
            if ($privateKey === '' || $knownHosts === '') {
                $this->output->set_status_header(422)->set_output("The matching SSH account is incomplete.\n");
                return;
            }
            log_message('info', 'Personal SSH Git account '.(int) $credential['id'].' of user '.(int) $claims['uid'].' used for '.$host.' from workspace '.$claims['job'].'.');
            $this->output->set_output("private_key=".base64_encode($privateKey)."\nknown_hosts=".base64_encode($knownHosts)."\n");
            return;
        }
        $secretName = $credential['auth_type'] === 'username_password' ? 'password' : 'token';
        $password = isset($credential['secret'][$secretName]) ? $credential['secret'][$secretName] : '';
        $providerDefaults = array('github' => 'x-access-token', 'gitlab' => 'oauth2', 'bitbucket' => 'x-token-auth', 'azure_devops' => 'git');
        $username = $credential['username'] !== '' ? $credential['username']
            : (isset($providerDefaults[$credential['provider']]) ? $providerDefaults[$credential['provider']] : 'git');
        if ($password === '') {
            $this->output->set_status_header(422)->set_output("The matching Git account has no usable HTTP secret.\n");
            return;
        }
        log_message('info', 'Personal Git account '.(int) $credential['id'].' of user '.(int) $claims['uid'].' used for '.$host.' from workspace '.$claims['job'].'.');
        $this->output->set_output("username=".$username."\npassword=".$password."\n");
    }
}
