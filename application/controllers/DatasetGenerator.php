<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

class DatasetGenerator extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();
        if (! $this->canGenerateDatasets()) {
            return;
        }
        // Pipeline_model owns forward-compatible creation of pipeline tables.
        $this->load->model('Pipeline_model', 'pipelines');
        $this->load->model('DatasetGenerator_model', 'generator');
    }

    private function canGenerateDatasets()
    {
        return $this->role == ROLE_ADMIN;
    }

    private function profiles()
    {
        $profiles = require APPPATH . 'config/dataset_profiles.php';
        return is_array($profiles) ? $profiles : array();
    }

    private function normalizeBatchKey($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim(substr($value, 0, 32), '-');
    }

    private function submittedCount($field, $fallback, $minimum, $maximum)
    {
        $raw = trim((string) $this->input->post($field));
        if ($raw === '') return (int) $fallback;
        if (! ctype_digit($raw)) return 0;
        $value = (int) $raw;
        return $value >= $minimum && $value <= $maximum ? $value : 0;
    }

    private function submittedEnvironments()
    {
		if ($this->jobSeekerIsStandaloneDeployment()) {
			return array($this->jobSeekerStandaloneEnvironment());
		}
        $raw = preg_split('/[\s,;]+/', strtoupper((string) $this->input->post('environments')));
        $environments = array();
        foreach ($raw as $value) {
            $value = $this->normalizeJobSeekerEnvironment($value);
            if ($value !== '' && preg_match('/^[A-Z][A-Z0-9_-]{0,14}$/', $value) && ! in_array($value, $environments, TRUE)) {
                $environments[] = $value;
            }
        }
        return array_slice($environments, 0, 8);
    }

    private function jenkinsJobPath($jobName)
    {
        $parts = array();
        foreach (explode('/', trim((string) $jobName, '/')) as $segment) {
            if ($segment !== '') $parts[] = 'job/'.rawurlencode($segment);
        }
        return implode('/', $parts);
    }

    private function successfulJenkinsStatus($status)
    {
        return in_array((int) $status, array(200, 201, 302, 303), TRUE);
    }

    private function sampleJenkinsCommand($index)
    {
        if ($index % 5 === 1) {
            return <<<'SHELL'
set -Eeuo pipefail
python3 - <<'PY'
import os
import time
from jobseeker import JobSeeker

job_name = os.getenv("JOB_NAME", "generated-python-sample")
environment = os.getenv("ENVIRONMENT", "DEV")
started = time.monotonic()
with JobSeeker(environment=environment, job=job_name) as js:
    with js.task("Generated Python sample", "PERF_PYTHON") as tmf:
        rows = tmf.context("generated_sample_rows", cast=int, default=25)
        asset = tmf.asset("customer-reference", required=False)
        connector = tmf.connector("jobseeker-mariadb", required=False)
        print("asset_registered=", asset is not None)
        print("connector_registered=", connector is not None)
        tmf.progress(total=rows, processed=rows, msg="Generated sample processed")
        tmf.finish(total=rows, processed=rows, msg="Generated Python sample complete")
    js.email_metrics(
        dataset="customer-reference",
        rows_read=rows,
        rows_written=rows,
        duration=f"{time.monotonic() - started:.3f} seconds",
    )
PY
SHELL;
        }
        if ($index % 5 === 2) {
            return <<<'SHELL'
set -Eeuo pipefail
if asset_path="$(jobseeker-asset customer-reference 2>/dev/null)"; then
  echo "data_asset=customer-reference path=$asset_path bytes=$(wc -c < "$asset_path")"
else
  echo "Optional Data Asset customer-reference is not registered for this generated job"
fi
SHELL;
        }
        if ($index % 5 === 3) {
            return <<<'SHELL'
set -Eeuo pipefail
jobseeker-connector list
if jobseeker-connector test jobseeker-mariadb --timeout 5 --json; then
  echo "Built-in JobSeeker connector is healthy"
else
  echo "Built-in connector test failed" >&2
  exit 1
fi
SHELL;
        }
        if ($index % 5 === 4) {
            return <<<'SHELL'
set -Eeuo pipefail
total="${GENERATED_SAMPLE_ROWS:-100}"
processed=0
for stage in extract validate publish; do
  while [ "$processed" -lt "$total" ]; do
    processed=$((processed + 25))
    [ "$processed" -gt "$total" ] && processed="$total"
    echo "pipeline_stage=$stage progress=$processed/$total environment=${ENVIRONMENT:-DEV}"
  done
  processed=0
done
echo "Pipeline-ready synthetic ETL workload complete"
SHELL;
        }
        return <<<'SHELL'
set -Eeuo pipefail
echo "Synthetic shell sample: ${JOB_NAME:-unknown} #${BUILD_NUMBER:-local}"
echo "Environment: ${ENVIRONMENT:-DEV}"
echo "Worker: $(hostname)"
printf 'workspace_bytes='
du -sk "${WORKSPACE:-.}" | awk '{print $1 * 1024}'
SHELL;
    }

    private function sampleJenkinsXml($jobName, $environment, $index)
    {
        $description = htmlspecialchars('Generated by JobSeeker Dataset Generator batch for '.$environment.'. Safe to remove from the admin control panel.', ENT_QUOTES | ENT_XML1, 'UTF-8');
        $command = str_replace(']]>', '] ]>', $this->sampleJenkinsCommand($index));
        $environment = htmlspecialchars($environment, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return "<?xml version='1.1' encoding='UTF-8'?>\n".
            '<project><actions/><description>'.$description.'</description><keepDependencies>false</keepDependencies>'.
            '<properties><hudson.model.ParametersDefinitionProperty><parameterDefinitions>'.
            '<hudson.model.StringParameterDefinition><name>ENVIRONMENT</name><description>JobSeeker runtime environment</description><defaultValue>'.$environment.'</defaultValue><trim>true</trim></hudson.model.StringParameterDefinition>'.
            '</parameterDefinitions></hudson.model.ParametersDefinitionProperty></properties>'.
            '<scm class="hudson.scm.NullSCM"/><canRoam>true</canRoam><disabled>false</disabled>'.
            '<blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding><blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding>'.
            '<triggers/><concurrentBuild>true</concurrentBuild><builders><hudson.tasks.Shell><command><![CDATA['.$command.']]></command></hudson.tasks.Shell></builders>'.
            '<publishers/><buildWrappers/></project>';
    }

    private function createJenkinsSamples($jobNames, $environments)
    {
        $createdNames = array();
        $failed = 0;
        $started = microtime(TRUE);
        foreach ($jobNames as $index => $jobName) {
            $path = $this->jenkinsJobPath($jobName);
            $environment = $environments[$index % count($environments)];
            $xml = $this->sampleJenkinsXml($jobName, $environment, $index);
            $existing = $this->requestJenkins('GET', $path.'/api/json');
            if ((int) $existing['status'] === 200) {
                $response = $this->requestJenkins('POST', $path.'/config.xml', $xml, 'application/xml');
            } else if ((int) $existing['status'] === 404) {
                $response = $this->requestJenkins('POST', 'createItem?name='.rawurlencode($jobName), $xml, 'application/xml');
            } else {
                $failed++;
                continue;
            }
            if ($this->successfulJenkinsStatus($response['status'])) {
                $createdNames[] = $jobName;
            } else {
                $failed++;
            }
        }
        return array('jobs' => $createdNames, 'created' => count($createdNames), 'failed' => $failed, 'seconds' => microtime(TRUE) - $started);
    }

    private function deleteJenkinsSamples($jobNames)
    {
        $deleted = 0;
        $failed = 0;
        foreach ($jobNames as $jobName) {
            if (! preg_match('/^[a-z0-9][a-z0-9-]{0,63}-job-[0-9]{3}$/', (string) $jobName)) {
                $failed++;
                continue;
            }
            $response = $this->requestJenkins('POST', $this->jenkinsJobPath($jobName).'/doDelete');
            if ($this->successfulJenkinsStatus($response['status']) || (int) $response['status'] === 404) $deleted++;
            else $failed++;
        }
        return array('deleted' => $deleted, 'failed' => $failed);
    }

    public function index()
    {
        if (! $this->canGenerateDatasets()) {
            $this->loadThis();
            return;
        }
        $this->global['pageTitle'] = 'Job Seeker : Dataset Generator';
        $data = array(
            'profiles' => $this->profiles(),
            'batches' => $this->generator->listBatches(),
            'totals' => $this->generator->totals(),
            'suggestedBatchKey' => 'perf-'.date('Ymd-His')
        );
        $this->loadViews('datasetGenerator', $this->global, $data, NULL);
    }

    public function create()
    {
        if (! $this->canGenerateDatasets()) {
            $this->output->set_status_header(403);
            $this->loadThis();
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->output->set_status_header(405);
            return;
        }

        $profiles = $this->profiles();
        $profileKey = strtolower(trim((string) $this->input->post('profile')));
        if (! isset($profiles[$profileKey])) $profileKey = 'performance';
        $profile = $profiles[$profileKey];
        $batchKey = $this->normalizeBatchKey($this->input->post('batch_key'));
        $environments = $this->submittedEnvironments();
        $seedRaw = trim((string) $this->input->post('seed'));
        $seed = ctype_digit($seedRaw) ? (int) $seedRaw : 1;
        $options = array(
            'batch_key' => $batchKey,
            'profile' => $profileKey,
            'tmf_rows' => $this->submittedCount('tmf_rows', $profile['tmf_rows'], 1, 250000),
            'jobs' => $this->submittedCount('jobs', $profile['jobs'], 1, 200),
            'pipelines' => $this->submittedCount('pipelines', $profile['pipelines'], 1, 50),
            'pipeline_runs' => $this->submittedCount('pipeline_runs', $profile['pipeline_runs'], 1, 500),
            'environments' => $environments,
            'seed' => max(1, min(2147483647, $seed)),
            'include_jenkins' => $this->input->post('include_jenkins') === '1' && ! empty($this->global['jenkins_enabled'])
        );

        if ($batchKey === '' || strlen($batchKey) < 3 || empty($environments) || $options['tmf_rows'] < 1 || $options['jobs'] < 1 || $options['pipelines'] < 1 || $options['pipeline_runs'] < 1) {
            $this->session->set_flashdata('error', 'Check the batch key, environments, and numeric limits. No data was generated.');
            redirect('dataset-generator');
            return;
        }
        if ($this->generator->batchExists($batchKey)) {
            $this->session->set_flashdata('error', 'That batch key already exists. Use a unique key or remove the earlier batch first.');
            redirect('dataset-generator');
            return;
        }

        @set_time_limit(0);
        $result = $this->generator->createBatch($options, $this->name ?: 'Administrator');
        if (empty($result['ok'])) {
            $this->session->set_flashdata('error', $result['message']);
            redirect('dataset-generator');
            return;
        }

        $jenkinsResult = array('jobs' => array(), 'created' => 0, 'failed' => 0, 'seconds' => 0);
        if ($options['include_jenkins']) {
            $jenkinsResult = $this->createJenkinsSamples($result['jobs'], $environments);
            // Track every deterministic name, including failed attempts, so a
            // later cleanup can remove an orphan left by an ambiguous response.
            $this->generator->recordJenkinsResult($result['id'], $result['jobs'], $jenkinsResult['created'], $jenkinsResult['failed'], $jenkinsResult['seconds']);
        }

        $message = sprintf('Generated %s TMF rows, %s error rows, %s pipelines, and %s pipeline runs in %ss.',
            number_format($options['tmf_rows']), number_format($result['error_rows']), number_format($options['pipelines']),
            number_format($options['pipelines'] * $options['pipeline_runs']), number_format($result['metrics']['database_seconds'], 3));
        if ($options['include_jenkins']) {
            $message .= sprintf(' Jenkins: %d created/updated, %d failed.', $jenkinsResult['created'], $jenkinsResult['failed']);
        }
        $this->session->set_flashdata($jenkinsResult['failed'] > 0 ? 'warning' : 'success', $message);
        redirect('dataset-generator');
    }

    public function delete()
    {
        if (! $this->canGenerateDatasets()) {
            $this->output->set_status_header(403);
            $this->loadThis();
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->output->set_status_header(405);
            return;
        }
        $batch = $this->generator->getBatch((int) $this->input->post('batch_id'));
        if (! $batch) {
            $this->session->set_flashdata('error', 'Generated batch was not found.');
            redirect('dataset-generator');
            return;
        }
        $config = json_decode((string) $batch->config_json, TRUE);
        $jenkinsJobs = is_array($config) && isset($config['jenkins_jobs']) && is_array($config['jenkins_jobs']) ? $config['jenkins_jobs'] : array();
        $jenkinsResult = empty($jenkinsJobs) ? array('deleted' => 0, 'failed' => 0) : $this->deleteJenkinsSamples($jenkinsJobs);
        if ($jenkinsResult['failed'] > 0) {
            $this->session->set_flashdata('warning', sprintf('Removed or confirmed %d Jenkins jobs, but %d could not be removed. Database rows were kept so cleanup can be retried safely.', $jenkinsResult['deleted'], $jenkinsResult['failed']));
            redirect('dataset-generator');
            return;
        }
        $result = $this->generator->deleteBatch($batch->id);
        if (empty($result['ok'])) {
            $this->session->set_flashdata('error', $result['message']);
        } else {
            $message = sprintf('Removed batch %s: %s TMF rows, %s error rows, and %s pipelines.', html_escape($batch->batch_key), number_format($result['tmf_rows']), number_format($result['error_rows']), number_format($result['pipelines']));
            if ($jenkinsResult['deleted']) $message .= sprintf(' Jenkins: %d removed or already absent.', $jenkinsResult['deleted']);
            $this->session->set_flashdata('success', $message);
        }
        redirect('dataset-generator');
    }
}

?>
