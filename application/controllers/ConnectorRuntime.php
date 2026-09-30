<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class ConnectorRuntime extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('DbSettings_model', 'connectors');
		$this->ensureJobSeekerStandaloneEnvironmentRecord();
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

    private function bearerToken()
    {
        $authorization = trim((string) $this->input->get_request_header('Authorization', TRUE));
        return strncmp($authorization, 'Bearer ', 7) === 0 ? substr($authorization, 7) : '';
    }

    private function isWorkerToken($token)
    {
        $expected = trim((string) getenv('JOBSEEKER_CONNECTOR_API_TOKEN'));
        return $expected !== '' && hash_equals($expected, $token);
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

    public function index()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('error' => 'Method not allowed.'), 405);
            return;
        }
        $token = $this->bearerToken();
        $this->load->library('ConnectorIdeSession');
        $ide = ! $this->isWorkerToken($token);
        if ($ide && ! $this->connectoridesession->isSessionToken($token)) {
            $this->jsonResponse(array('error' => 'Unauthorized.'), 401);
            return;
        }

        if ($this->input->post('git_project') !== NULL) {
            if ($ide) {
                $this->jsonResponse(array('error' => 'Only Jenkins workers resolve project Git sources.'), 403);
                return;
            }
            $this->gitSource();
            return;
        }

        $environment = $this->normalizedEnvironment($this->input->post('environment'));
        $jobName = $this->normalizedJobName($this->input->post('job_name'));
        if ($environment === FALSE || $jobName === FALSE) {
            $this->jsonResponse(array('error' => 'A valid environment and job_name are required.'), 422);
            return;
        }
        if ($ide && ! $this->connectoridesession->verify($token, $environment, $jobName)) {
            $this->jsonResponse(array('error' => 'The OpenVSCode connector session is invalid. Reopen the workspace from JobSeeker.'), 401);
            return;
        }
		if (! $this->jobSeekerEnvironmentIsAllowed($environment)) {
			$this->jsonResponse(array('error' => 'This standalone deployment only exposes '.$this->jobSeekerStandaloneEnvironment().' connectors.'), 409);
			return;
		}
		$environment = $this->jobSeekerEffectiveEnvironment($environment);

        try {
            $connectors = array();
            $unavailable = array();
            $this->connectors->pruneRuntimeAccessLogs();
            foreach ($this->connectors->runtimeSettings($environment, $jobName) as $row) {
                // Cloud and worker-variable secrets resolve with the worker's own
                // credentials, which the editor does not have.
                if ($ide && ! in_array(isset($row['secret_backend']) ? $row['secret_backend'] : 'local', array('local', 'deployment'), TRUE)) {
                    $unavailable[] = (string) $row['connector_key'];
                    continue;
                }
                // Isolate a per-connector resolution failure (for example an
                // undecryptable local secret or a missing cloud reference) so a
                // single broken or wildcard-scoped connector cannot take down
                // every job that asks for its catalog. The connector is dropped
                // from the catalog and reported; a job that genuinely needs it
                // still fails, but with a precise "connector unavailable" error
                // from its own step instead of a blanket HTTP 500 here.
                try {
                    $connectors[] = $this->connectors->runtimePayload($row);
                    $this->connectors->logRuntimeAccess($row, $environment, $jobName, $ide ? 'granted-ide' : 'granted');
                } catch (Exception $exception) {
                    $this->connectors->logRuntimeAccess($row, $environment, $jobName, $ide ? 'failed-ide' : 'failed');
                    $unavailable[] = (string) $row['connector_key'];
                    log_message('error', 'Connector runtime skipped "'.$row['connector_key'].'" for job "'.$jobName.'" ('.$environment.'): '.$exception->getMessage());
                }
            }
            $response = array(
                'schema_version' => 1,
                'generated_at' => gmdate('c'),
                'environment' => $environment,
                'job' => $jobName,
                'connectors' => $connectors
            );
            if (! empty($unavailable)) {
                $response['unavailable'] = array_values(array_unique($unavailable));
            }
            $this->jsonResponse($response);
        } catch (Exception $exception) {
            log_message('error', 'Connector runtime materialization failed: '.$exception->getMessage());
            $this->jsonResponse(array('error' => 'The connector catalog could not be built.'), 500);
        }
    }

    /**
     * The repository, branch and build credential a project-bound Git job
     * clones in its environment. Jobs ask at build time, so a project's
     * branch map and credential are never copied into job configurations.
     */
    private function gitSource()
    {
        $projectId = (int) $this->input->post('git_project');
        $environment = $this->normalizedEnvironment($this->input->post('environment'));
        if ($projectId <= 0 || $environment === FALSE) {
            $this->jsonResponse(array('error' => 'A valid git_project and environment are required.'), 422);
            return;
        }
        if (! $this->jobSeekerEnvironmentIsAllowed($environment)) {
            $this->jsonResponse(array('error' => 'This standalone deployment only exposes '.$this->jobSeekerStandaloneEnvironment().'.'), 409);
            return;
        }
        $environment = $this->jobSeekerEffectiveEnvironment($environment);
        $this->load->model('ProjectGitSettings_model', 'projectGitSettings');
        $source = $this->projectGitSettings->gitSource($projectId, $environment);
        if ($source === FALSE) {
            $this->jsonResponse(array('error' => 'Project '.$projectId.' does not exist or has no Git repository.'), 404);
            return;
        }
        $source['environment'] = $environment;
        $this->jsonResponse(array('schema_version' => 1, 'git_source' => $source));
    }
}
