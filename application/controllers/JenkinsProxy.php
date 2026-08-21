<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class JenkinsProxy extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
    }

    public function proxy()
    {
        $method = $this->input->method(TRUE);

        if (! in_array($method, array('GET', 'HEAD', 'OPTIONS'), TRUE)) {
            $tokenName = $this->security->get_csrf_token_name();
            $token = $this->input->get($tokenName);

            if (empty($token) || ! hash_equals($this->security->get_csrf_hash(), $token)) {
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('text/plain')
                    ->set_output('Invalid CSRF token.');
                return;
            }
        }

        $path = $this->input->get('path');
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : NULL;
        $response = $this->requestJenkins($method, $path, $this->input->raw_input_stream, $contentType);

        if (! in_array($method, array('GET', 'HEAD', 'OPTIONS'), TRUE) && in_array($response['status'], array(301, 302, 303), TRUE)) {
            $response['status'] = 200;
        }

        $this->forwardJenkinsResponseHeaders($response);

        $this->output
            ->set_status_header($response['status'])
            ->set_content_type($response['content_type'])
            ->set_output($response['body']);
    }

    private function forwardJenkinsResponseHeaders($response)
    {
        if (empty($response['headers']) || ! is_array($response['headers'])) {
            return;
        }

        $headersToForward = array('Location', 'X-Text-Size', 'X-More-Data');

        foreach ($response['headers'] as $header) {
            foreach ($headersToForward as $name) {
                if (stripos($header, $name . ':') !== 0) {
                    continue;
                }

                $value = trim(substr($header, strlen($name) + 1));

                if ($value === '' || preg_match('/[\r\n]/', $value)) {
                    continue;
                }

                if ($name === 'Location') {
                    $this->output->set_header('X-JobSeeker-Jenkins-Location: ' . $value, TRUE);
                } else {
                    $this->output->set_header($name . ': ' . $value, TRUE);
                }
            }
        }
    }
}