<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/** Minimal GitHub OAuth web-flow client; tokens are stored by UserGitAccount_model. */
class GitHubOAuth
{
    public function enabled()
    {
        return trim((string) getenv('JOBSEEKER_GITHUB_OAUTH_CLIENT_ID')) !== ''
            && trim((string) getenv('JOBSEEKER_GITHUB_OAUTH_CLIENT_SECRET')) !== '';
    }

    public function authorizationUrl($redirectUri, $state)
    {
        if (! $this->enabled()) {
            return FALSE;
        }
        $scope = trim((string) getenv('JOBSEEKER_GITHUB_OAUTH_SCOPES'));
        if ($scope === '') {
            $scope = 'repo';
        }
        return 'https://github.com/login/oauth/authorize?'.http_build_query(array(
            'client_id' => trim((string) getenv('JOBSEEKER_GITHUB_OAUTH_CLIENT_ID')),
            'redirect_uri' => (string) $redirectUri,
            'scope' => $scope,
            'state' => (string) $state,
            'allow_signup' => 'true'
        ), '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode($code, $redirectUri)
    {
        return $this->tokenRequest(array(
            'client_id' => trim((string) getenv('JOBSEEKER_GITHUB_OAUTH_CLIENT_ID')),
            'client_secret' => trim((string) getenv('JOBSEEKER_GITHUB_OAUTH_CLIENT_SECRET')),
            'code' => (string) $code,
            'redirect_uri' => (string) $redirectUri
        ));
    }

    public function refresh($refreshToken)
    {
        return $this->tokenRequest(array(
            'client_id' => trim((string) getenv('JOBSEEKER_GITHUB_OAUTH_CLIENT_ID')),
            'client_secret' => trim((string) getenv('JOBSEEKER_GITHUB_OAUTH_CLIENT_SECRET')),
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $refreshToken
        ));
    }

    private function tokenRequest(array $fields)
    {
        if (! $this->enabled()) {
            return array('ok' => FALSE, 'message' => 'GitHub web authorization is not configured.');
        }
        $response = $this->request('https://github.com/login/oauth/access_token', 'POST', $fields, 'application/x-www-form-urlencoded');
        if (! $response['ok'] || empty($response['json']['access_token'])) {
            $message = isset($response['json']['error_description']) ? $response['json']['error_description'] : 'GitHub did not return an access token.';
            return array('ok' => FALSE, 'message' => $message);
        }
        return array('ok' => TRUE, 'token' => $response['json']);
    }

    public function user($accessToken)
    {
        $response = $this->request('https://api.github.com/user', 'GET', array(), 'application/json', array(
            'Authorization: Bearer '.(string) $accessToken,
            'X-GitHub-Api-Version: 2022-11-28'
        ));
        if (! $response['ok'] || empty($response['json']['login'])) {
            return array('ok' => FALSE, 'message' => 'GitHub account details could not be loaded.');
        }
        return array('ok' => TRUE, 'user' => $response['json']);
    }

    private function request($url, $method, array $fields, $contentType, array $headers = array())
    {
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: JobSeeker-Git-Integration';
        $options = array(
            'http' => array(
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => TRUE,
                'timeout' => 15
            )
        );
        if ($method === 'POST') {
            $options['http']['header'] .= "\r\nContent-Type: ".$contentType;
            $options['http']['content'] = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        }
        $body = @file_get_contents($url, FALSE, stream_context_create($options));
        $status = 0;
        foreach (isset($http_response_header) ? $http_response_header : array() as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }
        $json = json_decode((string) $body, TRUE);
        return array('ok' => $status >= 200 && $status < 300 && is_array($json), 'status' => $status, 'json' => is_array($json) ? $json : array());
    }
}
