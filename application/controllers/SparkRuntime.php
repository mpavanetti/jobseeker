<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Agent-callable trigger for Spark jobs. A Spark job authored in Create Job is
 * saved as both a `spark_jobs` row and a real Jenkins job (see
 * SparkJenkinsTrait); the Jenkins build's single shell step calls this
 * controller to provision the ephemeral cluster, poll it to completion, and
 * stream the driver log into the Jenkins console. No CodeIgniter session -
 * mirrors ConnectorRuntime.php's bearer-token pattern (excluded from CSRF in
 * application/config/config.php).
 */
class SparkRuntime extends CI_Controller
{
    const LOG_CHUNK_MAX = 32000;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('SparkCompute_model', 'spark');
        $this->load->library('SparkClusterOrchestrator', array('model' => $this->spark), 'orchestrator');
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
        $expected = trim((string) getenv('JOBSEEKER_SPARK_TRIGGER_TOKEN'));
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

    private function runPayload($run)
    {
        return array(
            'run_id' => (int) $run->id,
            'run_key' => (string) $run->run_key,
            'status' => (string) $run->status,
            'terminal' => in_array($run->status, SparkClusterOrchestrator::TERMINAL, TRUE),
            'exit_code' => $run->exit_code === NULL ? NULL : (int) $run->exit_code,
            'error_message' => (string) $run->error_message,
            'worker_count' => (int) $run->worker_count,
        );
    }

    /**
     * POST job_key, environment, workers? (int), build? (Jenkins BUILD_NUMBER),
     * triggered_by? -> provisions the cluster and starts spark-submit.
     */
    public function trigger()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
            return;
        }
        if (! $this->authorized()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Unauthorized.'), 401);
            return;
        }

        $environment = $this->normalizedEnvironment($this->input->post('environment'));
        $jobKey = trim((string) $this->input->post('job_key'));
        if ($environment === FALSE || $jobKey === '' || strlen($jobKey) > 128) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'A valid job_key and environment are required.'), 422);
            return;
        }

        $job = $this->spark->getJobByScope($jobKey, $environment);
        if (! $job || ! $job->is_active) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'No active Spark job "'.$jobKey.'" in '.$environment.'.'), 404);
            return;
        }

        $cluster = $this->spark->getCluster((int) $job->cluster_id);
        $runtime = $cluster ? $this->spark->getRuntime($cluster->runtime_key) : NULL;
        if (! $cluster || ! $runtime) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'The job cluster or runtime is missing.'), 422);
            return;
        }

        $workerOverride = NULL;
        $workersPost = trim((string) $this->input->post('workers'));
        if ($workersPost !== '') {
            $workerOverride = (int) $workersPost;
        } elseif ($job->workers !== NULL) {
            $workerOverride = (int) $job->workers;
        }

        $triggeredBy = trim((string) $this->input->post('triggered_by'));
        $triggeredBy = $triggeredBy !== '' ? substr($triggeredBy, 0, 200) : 'jenkins';

        $result = $this->orchestrator->start($job, $cluster, $runtime, $triggeredBy, $workerOverride);

        $build = trim((string) $this->input->post('build'));
        if ($result['ok'] && $result['run'] && preg_match('/^[1-9][0-9]*$/', $build)) {
            $this->spark->updateSparkRun($result['run']->id, array('jenkins_build_number' => (int) $build));
        }

        $status = $result['ok'] ? 200 : 502;
        $this->jsonResponse(array(
            'ok' => $result['ok'],
            'message' => $result['message'],
            'run' => $result['run'] ? $this->runPayload($result['run']) : NULL,
        ), $status);
    }

    public function status($runId)
    {
        if (! $this->authorized()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Unauthorized.'), 401);
            return;
        }
        $run = $this->spark->getSparkRun((int) $runId);
        if (! $run) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        $run = $this->orchestrator->advance($run);
        $this->jsonResponse(array('ok' => TRUE) + $this->runPayload($run));
    }

    /**
     * Incremental log fetch for the Jenkins console: pass back only what is new
     * since the caller's offset (byte count of what it already printed).
     */
    public function logs($runId)
    {
        if (! $this->authorized()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Unauthorized.'), 401);
            return;
        }
        $run = $this->spark->getSparkRun((int) $runId);
        if (! $run) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        // The Jenkins runner script polls only this endpoint in its wait loop, so
        // it must be the thing that advances the run - otherwise the DB status
        // never leaves RUNNING and the cluster is never torn down.
        $run = $this->orchestrator->advance($run);

        $offset = max(0, (int) $this->input->get('offset'));
        $logs = (string) $this->orchestrator->liveLogs($run);
        $length = strlen($logs);
        if ($offset > $length) {
            // The tail buffer was replaced (e.g. rotated); resync from the top
            // rather than silently drop output.
            $offset = 0;
        }

        $chunk = substr($logs, $offset, self::LOG_CHUNK_MAX);
        $this->jsonResponse(array(
            'ok' => TRUE,
            'logs' => $chunk,
            'next_offset' => $offset + strlen($chunk),
            'status' => (string) $run->status,
            'terminal' => in_array($run->status, SparkClusterOrchestrator::TERMINAL, TRUE),
        ));
    }

    public function cancel($runId)
    {
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
            return;
        }
        if (! $this->authorized()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Unauthorized.'), 401);
            return;
        }
        $run = $this->spark->getSparkRun((int) $runId);
        if (! $run) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Run not found.'), 404);
            return;
        }
        $run = $this->orchestrator->cancel($run, 'jenkins-abort');
        $this->jsonResponse(array('ok' => TRUE) + $this->runPayload($run));
    }
}
