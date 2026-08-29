<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

class ConnectorRuntime extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('DbSettings_model', 'connectors');
    }

    private function jsonResponse($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function authorized()
    {
        $expected = trim((string) getenv('JOBSEEKER_CONNECTOR_API_TOKEN'));
        $authorization = trim((string) $this->input->get_request_header('Authorization', TRUE));
        $prefix = 'Bearer ';
        if ($expected === '' || strncmp($authorization, $prefix, strlen($prefix)) !== 0) {
            return FALSE;
        }
        return hash_equals($expected, substr($authorization, strlen($prefix)));
    }

    private function normalizedEnvironment($value)
    {
        $value = strtoupper(trim((string) $value));
        return preg_match('/^[A-Z0-9._-]{1,100}$/', $value) ? $value : FALSE;
    }

    private function normalizedJobName($value)
    {
        $value = trim((string) $value);
        return preg_match('/^[A-Za-z0-9._\-\/ ]{1,200}$/', $value) ? $value : FALSE;
    }

    private function connectorPayload($row)
    {
        $backend = isset($row['secret_backend']) ? (string) $row['secret_backend'] : 'local';
        $reference = json_decode(isset($row['secret_reference']) ? (string) $row['secret_reference'] : '', TRUE);
        if (! is_array($reference)) {
            $reference = array();
        }

        $secret = array('backend' => $backend);
        if ($backend === 'local') {
            $values = $this->connectors->decryptLocalSecret(isset($row['secret_encrypted']) ? $row['secret_encrypted'] : '');
            if ($values === FALSE) {
                throw new RuntimeException('Connector '.$row['connector_key'].' has an unreadable local secret.');
            }
            $secret['values'] = array_map(function($value) { return (string) $value; }, $values);
        } else {
            $secret['reference'] = $reference;
        }

        return array(
            'key' => (string) $row['connector_key'],
            'type' => (string) $row['db_type'],
            'environment' => (string) $row['environment'],
            'job' => (string) $row['job_name'],
            'description' => (string) $row['description'],
            'config' => array(
                'auth_type' => isset($row['auth_type']) ? (string) $row['auth_type'] : 'username_password',
                'host' => (string) $row['address'],
                'port' => (int) $row['port'],
                'database' => (string) $row['schema'],
                'additional_parameters' => (string) $row['additional_parameters'],
                'oracle_service_name' => (string) $row['oracle_ServiceName'],
                'oracle_sid' => (string) $row['oracle_sid']
            ),
            'secret' => $secret
        );
    }

    public function index()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('error' => 'Method not allowed.'), 405);
            return;
        }
        if (! $this->authorized()) {
            $this->jsonResponse(array('error' => 'Unauthorized.'), 401);
            return;
        }

        $environment = $this->normalizedEnvironment($this->input->post('environment'));
        $jobName = $this->normalizedJobName($this->input->post('job_name'));
        if ($environment === FALSE || $jobName === FALSE) {
            $this->jsonResponse(array('error' => 'A valid environment and job_name are required.'), 422);
            return;
        }

        try {
            $connectors = array();
            $this->connectors->pruneRuntimeAccessLogs();
            foreach ($this->connectors->runtimeSettings($environment, $jobName) as $row) {
                try {
                    $connectors[] = $this->connectorPayload($row);
                    $this->connectors->logRuntimeAccess($row, $environment, $jobName, 'granted');
                } catch (Exception $exception) {
                    $this->connectors->logRuntimeAccess($row, $environment, $jobName, 'failed');
                    throw $exception;
                }
            }
            $this->jsonResponse(array(
                'schema_version' => 1,
                'generated_at' => gmdate('c'),
                'environment' => $environment,
                'job' => $jobName,
                'connectors' => $connectors
            ));
        } catch (Exception $exception) {
            log_message('error', 'Connector runtime materialization failed: '.$exception->getMessage());
            $this->jsonResponse(array('error' => 'One or more connector secrets could not be resolved.'), 500);
        }
    }
}

?>
