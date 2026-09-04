<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';

/**
 * Apache Hop projects.
 *
 * Jenkins remains the scheduler and trigger for every Hop job; this screen is
 * the catalog side of the integration - which projects exist, what they can
 * run, which JobSeeker connectors they will see as Hop database connections,
 * which Jenkins jobs use them, and whether the optional Hop Server engine is
 * reachable. Jobs themselves are created in Job Creation, so there is one place
 * where a Jenkins job is born whatever runtime it uses.
 */
class Hop extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(array('url', 'form'));
        $this->load->library('session');
        $this->load->library('HopProject');
        $this->load->library('HopServer');
        $this->load->library('HopGraph');
        $this->load->model('Hop_model');
        $this->isLoggedIn();
    }

    private function canManageProjects()
    {
        return $this->role == ROLE_ADMIN || $this->role == ROLE_MANAGER;
    }

    private function hopEnabled()
    {
        $value = strtolower(trim((string) getenv('JOBSEEKER_HOP_ENABLED')));
        return ! in_array($value, array('0', 'false', 'off', 'no'), TRUE);
    }

    private function selectedEnvironment()
    {
        $environment = trim((string) $this->input->get('environment', TRUE));
        if ($environment === '') {
            $environment = $this->jobSeekerEnvironmentPreference();
        }
        $environment = $this->normalizeJobSeekerEnvironment($environment);
        return $environment === '' || $environment === '*' ? 'ALL' : $environment;
    }

    private function jsonResponse($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    // -- server engine -----------------------------------------------------

    private function serverStatus($timeoutSeconds = 3)
    {
        return $this->hopserver->status($timeoutSeconds);
    }

    /**
     * Reconcile what the Hop Server holds into JobSeeker.
     *
     * A pipeline or workflow published straight from the Apache Hop GUI to this
     * server is a real run over real data; it just did not come through
     * Jenkins. Without this it would be visible only inside Hop - not on this
     * screen and not in Transaction Monitoring, which is where anyone would
     * look for it.
     *
     * Runs JobSeeker itself started are kept as history but never given a
     * second TMF row: the runner already opened one, and says so by leaving a
     * claim file on the shared repository volume.
     */
    private function syncServerExecutions()
    {
        $executions = $this->hopserver->executions();
        if (! $executions) {
            return 0;
        }

        $claims = $this->hopserver->claims($this->repositoryRootPath());
        $environment = $this->hopserver->environment();
        $synced = 0;

        foreach ($executions as $execution) {
            $executionId = (string) $execution['execution_id'];
            if ($executionId === '') {
                continue;
            }

            $stored = $this->Hop_model->findExecution($executionId);
            $finished = $execution['state'] !== 'running';
            $log = $stored !== NULL && isset($stored['log_text']) ? (string) $stored['log_text'] : '';

            // A finished run cannot change, and the server's status *list* omits
            // the per-transform detail, so re-reading one would overwrite the
            // counters and the log this poll already captured with zeroes.
            if ($stored !== NULL && $stored['state'] !== 'running' && $log !== '') {
                $storedErrors = $this->hopserver->errorLines($log);
                if (! $storedErrors || $stored['state'] === 'error') {
                    continue;
                }

                // Repair history written by an older poller that trusted the
                // clean status despite ERROR/FATAL lines in the stored log.
                foreach (array('status', 'environment', 'project_key', 'filename', 'source', 'job_name',
                    'tmf_instance_id', 'records_total', 'records_processed', 'started_at', 'ended_at') as $field) {
                    if (array_key_exists($field, $stored)) {
                        $execution[$field] = $stored[$field];
                    }
                }
                $execution['state'] = 'error';
                $execution['errors'] = max((int) $stored['errors'], count($storedErrors));
            }

            $claim = isset($claims[$executionId]) ? $claims[$executionId] : NULL;
            if ($finished && $log === '') {
                $detail = $this->hopserver->execution($execution['kind'], $execution['name'], $executionId);
                if (is_array($detail)) {
                    $log = (string) $detail['log'];
                    foreach (array('status', 'state', 'error', 'ended_at', 'records_total', 'records_processed', 'errors', 'filename') as $field) {
                        if ((string) $detail[$field] !== '') {
                            $execution[$field] = $detail[$field];
                        }
                    }
                }
            }

            // Hop occasionally reports a clean "Finished" state and zero
            // structured errors even though a transform logged ERROR/FATAL
            // (connection initialization failures are a common example). The
            // log is the authoritative failure signal in that case.
            $logErrors = $this->hopserver->errorLines($log);
            if ($logErrors) {
                $execution['state'] = 'error';
                $execution['errors'] = max((int) $execution['errors'], count($logErrors));
            }

            $values = array(
                'execution_id' => $executionId,
                'name' => substr((string) $execution['name'], 0, 255),
                // Recomputed here rather than taken from the status list: only
                // the detail document names the file a workflow came from, and
                // that file name is the label a person recognises.
                'display_name' => substr($this->hopserver->displayName($execution['name'], $execution['filename']), 0, 255),
                'kind' => $execution['kind'],
                'status' => substr((string) $execution['status'], 0, 100),
                'state' => $execution['state'],
                'environment' => $claim !== NULL && isset($claim['environment']) && $claim['environment'] !== ''
                    ? strtoupper((string) $claim['environment'])
                    : $environment,
                'project_key' => $this->projectForExecution($execution, $stored),
                'filename' => substr((string) $execution['filename'], 0, 1000),
                'source' => $claim === NULL ? 'hop-gui' : 'jenkins',
                'job_name' => $claim !== NULL && isset($claim['job']) ? substr((string) $claim['job'], 0, 200) : NULL,
                'records_total' => (int) $execution['records_total'],
                'records_processed' => (int) $execution['records_processed'],
                'errors' => (int) $execution['errors'],
                'started_at' => $execution['started_at'] === '' ? NULL : $execution['started_at'],
                'ended_at' => $execution['ended_at'] === '' ? NULL : $execution['ended_at'],
                'log_text' => $log === '' ? NULL : $this->hopserver->truncateLog($log),
                'error_logged' => $stored !== NULL && ! empty($stored['error_logged']) ? 1 : 0
            );

            if ($claim !== NULL) {
                // The runner owns this run's TMF row; keep the pointer so the
                // screen can link the two, and never open a second one.
                $values['tmf_instance_id'] = isset($claim['tmf_instance_id']) ? substr((string) $claim['tmf_instance_id'], 0, 50) : NULL;
                $this->Hop_model->saveExecution($values);
                $synced++;
                continue;
            }

            $values['tmf_instance_id'] = $executionId;
            $errors = $execution['state'] === 'error' ? $logErrors : array();
            // Hop can end a run "with errors" and still report nr_errors 0 - an
            // action that failed to start, for instance - so fall back to what
            // the log actually said rather than showing a failure with no count.
            if ($execution['state'] === 'error' && $values['errors'] === 0) {
                $values['errors'] = max(1, count($errors));
            }

            $this->Hop_model->recordTmfRun(array(
                'instance_id' => $executionId,
                'job_name' => $values['display_name'] !== '' ? $values['display_name'] : ($values['name'] === '' ? $executionId : $values['name']),
                'environment' => $values['environment'],
                'state' => $execution['state'],
                'dimension' => 'hop-server',
                'event_text' => 'Apache Hop',
                'records_total' => $values['records_total'],
                'records_processed' => $values['records_processed'],
                'started_at' => $values['started_at'],
                'ended_at' => $values['ended_at'],
                'message' => $this->executionMessage($execution, $errors)
            ));

            if ($errors && empty($values['error_logged'])) {
                $this->Hop_model->recordTmfErrors($executionId, $values['display_name'], $errors);
                $values['error_logged'] = 1;
            }

            $this->Hop_model->saveExecution($values);
            $synced++;
        }

        return $synced;
    }

    /** What Transaction Monitoring shows as this run's message. */
    private function executionMessage($execution, $errors)
    {
        if ($errors) {
            $lines = array();
            foreach ($errors as $error) {
                $lines[] = $error['origin'] === '' ? $error['message'] : $error['origin'].': '.$error['message'];
            }
            return implode("\n", $lines);
        }
        if ((string) $execution['error'] !== '') {
            return (string) $execution['error'];
        }
        return sprintf(
            'Apache Hop %s "%s" on the Hop Server (read %d, written %d, errors %d)',
            $execution['kind'],
            $execution['name'],
            (int) $execution['records_total'],
            (int) $execution['records_processed'],
            (int) $execution['errors']
        );
    }

    /**
     * Which JobSeeker Hop project a server run belongs to.
     *
     * A workflow status carries its own file name, so that answers it exactly.
     * A pipeline status does not, so the project folders are searched once, on
     * the poll that first sees the run, and the answer is stored with it.
     */
    private function projectForExecution($execution, $stored)
    {
        if ($stored !== NULL && (string) $stored['project_key'] !== '') {
            return (string) $stored['project_key'];
        }

        $filename = str_replace('\\', '/', (string) $execution['filename']);
        $marker = '/hop/projects/';
        $position = strpos($filename, $marker);
        if ($position !== FALSE) {
            $key = explode('/', substr($filename, $position + strlen($marker)), 2)[0];
            $key = $this->hopproject->cleanProjectKey($key);
            if ($key !== FALSE) {
                return $key;
            }
        }

        // Otherwise the file whose base name matches the object name, which is
        // how Hop names a pipeline unless someone renamed it inside the file.
        $name = strtolower(trim((string) $execution['name']));
        $projectsRoot = $this->hopproject->projectsRoot($this->repositoryRootPath());
        if ($name === '' || ! is_dir($projectsRoot)) {
            return NULL;
        }

        $target = $name.($execution['kind'] === 'workflow' ? '.hwf' : '.hpl');
        foreach ((array) scandir($projectsRoot) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $projectPath = $projectsRoot.DIRECTORY_SEPARATOR.$entry;
            if (! is_dir($projectPath) || is_link($projectPath)) {
                continue;
            }
            foreach ($this->hopproject->entryFiles($projectPath) as $entryFile) {
                if (strtolower(basename($entryFile)) === $target) {
                    return $entry;
                }
            }
        }

        return NULL;
    }

    // -- catalog -----------------------------------------------------------

    /**
     * Merge what is on disk with what is registered. The filesystem wins: a
     * project copied in by hand shows up, and a registry row whose folder was
     * removed is reported as missing instead of silently disappearing.
     */
    private function catalog($environment = 'ALL')
    {
        $repositoryRoot = $this->repositoryRootPath();
        $projects = array();
        $usage = $this->Hop_model->jobUsage();
        $registered = array();
        foreach ($this->Hop_model->listProjects('') as $row) {
            $registered[(string) $row['project_key']] = $row;
        }

        foreach ($this->hopproject->listProjects($repositoryRoot) as $project) {
            $key = $project['key'];
            $row = isset($registered[$key]) ? $registered[$key] : NULL;
            unset($registered[$key]);

            $projectEnvironment = $row === NULL ? 'ALL' : strtoupper((string) $row['environment']);
            if ($environment !== 'ALL' && $projectEnvironment !== 'ALL' && $projectEnvironment !== $environment) {
                continue;
            }

            $project['registered'] = $row !== NULL;
            $project['environment'] = $projectEnvironment;
            $project['description'] = $row === NULL ? $project['manifest']['description'] : (string) $row['description'];
            $project['owner'] = $row === NULL ? '' : (string) $row['owner'];
            $project['jobs'] = isset($usage[$key]) ? $usage[$key] : array();
            $project['missing'] = FALSE;
            $projects[] = $project;
        }

        foreach ($registered as $key => $row) {
            $projects[] = array(
                'key' => $key,
                'name' => (string) $row['name'],
                'path' => (string) $row['storage_path'],
                'valid' => FALSE,
                'missing' => TRUE,
                'registered' => TRUE,
                'environment' => strtoupper((string) $row['environment']),
                'description' => (string) $row['description'],
                'owner' => (string) $row['owner'],
                'workflows' => array(),
                'pipelines' => array(),
                'entry_files' => array(),
                'entry_file' => (string) $row['entry_file'],
                'connections' => array(),
                'jobs' => isset($usage[$key]) ? $usage[$key] : array(),
                'manifest' => array(
                    'project' => (string) $row['name'],
                    'description' => (string) $row['description'],
                    'entry_file' => (string) $row['entry_file'],
                    'run_config' => (string) $row['run_config'],
                    'engine' => (string) $row['engine'],
                    'log_level' => (string) $row['log_level'],
                    'parameters' => array(),
                    'connectors' => array(),
                    'assets' => array(),
                    'context' => array()
                ),
                'updated_at' => (string) $row['updated_at']
            );
        }

        return $projects;
    }

    public function index()
    {
        $this->global['pageTitle'] = 'Job Seeker : Apache Hop';
        $environment = $this->selectedEnvironment();
        $server = $this->serverStatus();
        if (! empty($server['reachable'])) {
            $this->hopserver->ensureCatalogMirrored($this->repositoryRootPath());
            $this->syncServerExecutions();
        }

        $data = array(
            'hop_enabled' => $this->hopEnabled(),
            'environment' => $environment,
            'projects' => $this->catalog($environment),
            'server' => $server,
            'executions' => $this->decorateExecutions(
                $this->Hop_model->listExecutions($environment, $this->executionHistoryLimit())
            ),
            'server_environment' => $this->hopserver->environment(),
            'published_connections' => $this->hopserver->publishedConnections($this->repositoryRootPath()),
            'published_variables' => $this->hopserver->publishedVariables($this->repositoryRootPath()),
            'published_drivers' => $this->hopserver->publishedDrivers($this->repositoryRootPath()),
            'installed_drivers' => $this->hopserver->installedDrivers($this->repositoryRootPath()),
            'available_connections' => $this->availableServerConnections($this->hopserver->environment()),
            'hop_version' => trim((string) getenv('JOBSEEKER_HOP_VERSION')) !== '' ? trim((string) getenv('JOBSEEKER_HOP_VERSION')) : '2.19.0',
            'engines' => $this->hopproject->engines(),
            'can_manage' => $this->canManageProjects(),
            'repository_root' => $this->repositoryRootPath()
        );

        $this->loadViews('hop', $this->global, $data, NULL);
    }

    /**
     * Add the project file each run came from, so the screen can offer both
     * "see the canvas" and "make this a Jenkins job" without a second request.
     */
    private function decorateExecutions($executions)
    {
        $repositoryRoot = $this->repositoryRootPath();
        $resolved = array();
        foreach ($executions as $index => $execution) {
            $key = (string) $execution['project_key'].'|'.$execution['kind'].'|'.$execution['name'];
            if (! array_key_exists($key, $resolved)) {
                $resolved[$key] = $this->executionSource($execution['execution_id']);
            }
            $source = $resolved[$key];
            $executions[$index]['entry_file'] = $source === NULL ? '' : $source['file'];
            $executions[$index]['importable'] = $source === NULL
                && $this->hopserver->exportArchive($execution['filename'], $repositoryRoot) !== FALSE;
        }
        return $executions;
    }

    private function executionHistoryLimit()
    {
        $limit = (int) getenv('JOBSEEKER_HOP_EXECUTION_HISTORY');
        return $limit > 0 ? min($limit, 500) : 100;
    }

    /**
     * Poll target for the Apache Hop screen. Every refresh reconciles the Hop
     * Server first, so a run someone starts from the Hop GUI appears here, and
     * in Transaction Monitoring, within one refresh interval.
     */
    public function executions()
    {
        $environment = $this->selectedEnvironment();
        $server = $this->serverStatus();
        $synced = 0;
        if (! empty($server['reachable'])) {
            $this->hopserver->ensureCatalogMirrored($this->repositoryRootPath());
            $synced = $this->syncServerExecutions();
        }

        $this->jsonResponse(array(
            'server' => $server,
            'synced' => $synced,
            'executions' => $this->decorateExecutions(
                $this->Hop_model->listExecutions($environment, $this->executionHistoryLimit())
            )
        ));
    }

    // -- connections available to the Hop Server ---------------------------

    /**
     * What a pipeline started from the Apache Hop GUI can currently connect to.
     *
     * A Jenkins job gets a run-scoped connector catalog from the runner and
     * gives it back afterwards. A pipeline published straight to the server has
     * no runner in front of it, so unless an operator publishes a catalog it
     * fails with "Relational database connection 'x' not found" - which is the
     * single most confusing thing about publishing from the Hop GUI.
     *
     * Only connectors scoped to every job (`*`) in the chosen environment are
     * offered: the server is shared, so a connector scoped to one Jenkins job
     * must not become readable by anyone who can reach the Hop GUI.
     */
    public function connections()
    {
        $environment = $this->hopServerEnvironment();
        $this->jsonResponse(array(
            'environment' => $environment,
            'published' => $this->hopserver->publishedConnections($this->repositoryRootPath()),
            'variables' => $this->hopserver->publishedVariables($this->repositoryRootPath()),
            'drivers' => $this->hopserver->publishedDrivers($this->repositoryRootPath()),
            'installed_drivers' => $this->hopserver->installedDrivers($this->repositoryRootPath()),
            'available' => $this->availableServerConnections($environment),
            'can_manage' => $this->canManageProjects()
        ));
    }

    public function publishConnections()
    {
        if (! $this->canManageProjects()) {
            $this->jsonResponse(array('error' => 'Access denied.'), 403);
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('error' => 'Method not allowed.'), 405);
            return;
        }

        $environment = $this->hopServerEnvironment();
        $repositoryRoot = $this->repositoryRootPath();

        if ($this->input->post('withdraw') === '1') {
            $this->hopserver->withdrawCatalog($repositoryRoot);
            $this->jsonResponse(array('ok' => TRUE, 'environment' => $environment, 'published' => array()));
            return;
        }

        $this->load->model('DbSettings_model', 'connectors');
        $documents = array();
        $skipped = array();
        $connectorTypes = array();
        foreach ($this->connectors->runtimeSettings($environment, '*') as $row) {
            if (! $this->hopserver->isRelational($row['db_type'])) {
                $skipped[] = array('key' => (string) $row['connector_key'], 'reason' => 'not a relational database');
                continue;
            }
            $backend = isset($row['secret_backend']) ? (string) $row['secret_backend'] : 'local';
            if ($backend !== 'local') {
                // A cloud-held secret is resolved by the connector runtime at run
                // time on purpose. Copying it onto the server volume would defeat
                // the reason it is held there.
                $skipped[] = array('key' => (string) $row['connector_key'], 'reason' => 'held in '.$backend.', so it stays run-scoped');
                continue;
            }
            $secrets = $this->connectors->decryptLocalSecret(isset($row['secret_encrypted']) ? $row['secret_encrypted'] : '');
            if ($secrets === FALSE) {
                $skipped[] = array('key' => (string) $row['connector_key'], 'reason' => 'its secret could not be read');
                continue;
            }
            $document = $this->hopserver->rdbmsDocument($row, $secrets);
            if ($document === NULL) {
                $skipped[] = array('key' => (string) $row['connector_key'], 'reason' => 'Apache Hop has no driver for this database type');
                continue;
            }
            $documents[] = $document;
            $connectorTypes[] = (string) $row['db_type'];
        }

        $published = $this->hopserver->publishCatalog($repositoryRoot, $documents);
        if ($published === FALSE) {
            $this->jsonResponse(array('error' => 'The Hop Server connection catalog could not be written.'), 500);
            return;
        }

        // Two artefacts, two audiences: Hop system variables are what the server
        // itself resolves for a run started from the Apache Hop GUI, and the
        // environment file is what a designer attaches to their own project so
        // they build against the same names before it is ever scheduled.
        $serverVariables = $this->serverVariables($environment, $repositoryRoot);
        $variables = $this->hopserver->publishEnvironmentFile($repositoryRoot, $serverVariables);
        $applied = $this->hopserver->publishSystemVariables($repositoryRoot, $serverVariables);

        // The Hop image bundles the permissively licensed JDBC drivers only, so
        // record what these connections need. The server installs whatever it
        // finds missing when it next starts - it is the only party that can see
        // what its own image already carries.
        $drivers = $this->hopserver->requiredDrivers($connectorTypes);
        $this->hopserver->publishDrivers($repositoryRoot, $drivers);

        $this->jsonResponse(array(
            'ok' => TRUE,
            'environment' => $environment,
            'published' => $published,
            'variables' => $variables === FALSE ? array() : array_map(function($variable) { return $variable['name']; }, $variables),
            'variables_applied' => (bool) $applied,
            'drivers' => $drivers,
            'skipped' => $skipped
        ));
    }

    /**
     * The JobSeeker variables a Hop Server run should see, in the same names a
     * Jenkins Hop job gets, so a pipeline designed in the Hop GUI runs unchanged
     * once it is scheduled. Secrets are deliberately absent: a password reaches
     * the server only inside the 0600 database document.
     */
    private function serverVariables($environment, $repositoryRoot)
    {
        $serverRoot = $this->hopserver->repositoryServerRoot();
        $variables = array(
            'JOBSEEKER_ENVIRONMENT' => array('value' => $environment, 'description' => 'JobSeeker runtime environment'),
            'JOBSEEKER_REPOSITORY' => array('value' => $serverRoot, 'description' => 'JobSeeker repository root, as the Hop Server sees it'),
            'JOBSEEKER_DATA_ASSETS' => array('value' => $serverRoot.'/data-assets', 'description' => 'Data Asset root folder')
        );

        $this->load->model('DataAssets_model', 'dataAssets');
        foreach ($this->dataAssets->manifestAssets() as $asset) {
            $assetEnvironment = strtoupper((string) $asset['environment']);
            $jobScope = (string) $asset['job_name'];
            // Only assets every job in this environment may read: the Hop Server
            // is shared, so a job-scoped asset must stay job-scoped.
            if (($assetEnvironment !== 'ALL' && $assetEnvironment !== $environment) || ($jobScope !== '' && $jobScope !== '*')) {
                continue;
            }
            $name = $this->hopVariableName('JOBSEEKER_ASSET', (string) $asset['asset_key']);
            $variables[$name] = array(
                'value' => $serverRoot.'/data-assets/'.ltrim(str_replace('\\', '/', (string) $asset['storage_path']), '/'),
                'description' => 'Data Asset '.$asset['asset_key']
            );
        }

        $this->load->model('DbSettings_model', 'connectors');
        foreach ($this->connectors->runtimeSettings($environment, '*') as $row) {
            if (! $this->hopserver->isRelational($row['db_type'])) {
                continue;
            }
            $prefix = $this->hopVariableName('JOBSEEKER_CONN', (string) $row['connector_key']);
            $variables[$prefix.'_HOST'] = array('value' => (string) $row['address'], 'description' => 'Connector '.$row['connector_key'].' host');
            $variables[$prefix.'_PORT'] = array('value' => (string) (int) $row['port'], 'description' => 'Connector '.$row['connector_key'].' port');
            $variables[$prefix.'_DATABASE'] = array('value' => (string) $row['schema'], 'description' => 'Connector '.$row['connector_key'].' database');
        }

        ksort($variables);
        return $variables;
    }

    /** JOBSEEKER_ASSET_CUSTOMER_REFERENCE from "customer-reference". */
    private function hopVariableName($prefix, $key)
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $key));
        return $prefix.'_'.trim($normalized, '_');
    }

    /** The environment a Hop Server run belongs to, overridable per request. */
    private function hopServerEnvironment()
    {
        $requested = $this->normalizeJobSeekerEnvironment(trim((string) $this->input->post('environment')));
        if ($requested === '') {
            $requested = $this->normalizeJobSeekerEnvironment(trim((string) $this->input->get('environment', TRUE)));
        }
        if ($requested === '' || $requested === '*' || strtoupper($requested) === 'ALL') {
            return $this->hopserver->environment();
        }
        return strtoupper($requested);
    }

    private function availableServerConnections($environment)
    {
        $this->load->model('DbSettings_model', 'connectors');
        $available = array();
        foreach ($this->connectors->runtimeSettings($environment, '*') as $row) {
            $available[] = array(
                'key' => (string) $row['connector_key'],
                'type' => (string) $row['db_type'],
                'environment' => (string) $row['environment'],
                'relational' => $this->hopserver->isRelational($row['db_type']),
                'backend' => isset($row['secret_backend']) ? (string) $row['secret_backend'] : 'local'
            );
        }
        return $available;
    }

    /**
     * The canvas of one workflow or pipeline, as JSON.
     *
     * Apache Hop already stores the designer's layout inside the .hwf/.hpl, so
     * the same picture the Hop GUI draws can be drawn here - which is what makes
     * "what does this job actually do" answerable without a desktop tool.
     * Addressable either by project and file, or by a Hop Server execution.
     */
    public function graph()
    {
        $projectKey = $this->hopproject->cleanProjectKey($this->input->get('project', TRUE));
        $entryFile = $this->hopproject->cleanEntryFile($this->input->get('file', TRUE));
        $executionId = trim((string) $this->input->get('execution', TRUE));
        $jobName = trim((string) $this->input->get('job', TRUE));

        // Addressing by Jenkins job is what lets the execution screen draw the
        // canvas of the thing it is running, without knowing anything about Hop.
        if ($projectKey === FALSE && $jobName !== '') {
            $usage = $this->Hop_model->projectForJob($jobName);
            if (! $usage) {
                $this->jsonResponse(array('error' => 'That Jenkins job does not run an Apache Hop project.'), 404);
                return;
            }
            $projectKey = $this->hopproject->cleanProjectKey($usage['project_key']);
            $entryFile = $this->hopproject->cleanEntryFile($usage['entry_file']);
        }

        if ($projectKey === FALSE && $executionId !== '') {
            $resolved = $this->executionSource($executionId);
            if ($resolved === NULL) {
                $this->jsonResponse(array('error' => 'That Apache Hop execution cannot be matched to a project file.'), 404);
                return;
            }
            $projectKey = $resolved['project'];
            $entryFile = $resolved['file'];
        }

        if ($projectKey === FALSE || $entryFile === '') {
            $this->jsonResponse(array('error' => 'An Apache Hop project and file are required.'), 422);
            return;
        }

        $projectPath = $this->hopproject->projectPath($this->repositoryRootPath(), $projectKey);
        if ($projectPath === FALSE || ! $this->hopproject->hasEntryFile($projectPath, $entryFile)) {
            $this->jsonResponse(array('error' => 'That Apache Hop file was not found in the project.'), 404);
            return;
        }

        $graph = $this->hopgraph->parseFile($projectPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entryFile));
        if ($graph === FALSE) {
            $this->jsonResponse(array('error' => 'That Apache Hop file could not be read.'), 422);
            return;
        }

        $graph['project'] = $projectKey;
        $graph['file'] = $entryFile;

        // Live state, when the Hop Server still holds this run. It is the same
        // canvas either way; the numbers are what make it a picture of the run
        // rather than a picture of the design.
        if ($this->input->get('live') === '1') {
            $graph['live'] = NULL;
            $current = $this->hopserver->currentExecution($graph['kind'], $graph['name']);
            if ($current !== NULL) {
                $graph['live'] = array(
                    'execution_id' => $current['execution_id'],
                    'status' => $current['status'],
                    'state' => $current['state'],
                    'started_at' => $current['started_at'],
                    'nodes' => $this->hopserver->executionNodes($graph['kind'], $graph['name'], $current['execution_id'])
                );
            }
        }

        $this->jsonResponse($graph);
    }

    /**
     * Hand back a workflow, a pipeline, or a whole project as a file.
     *
     * A Hop file is the same XML wherever it came from, so downloading one is
     * what lets somebody open a run they are looking at here in the desktop
     * Apache Hop GUI, change it, and publish it back. A whole project comes as
     * a zip because Hop needs the descriptors and metadata beside the file.
     */
    public function download()
    {
        $projectKey = $this->hopproject->cleanProjectKey($this->input->get('project', TRUE));
        $entryFile = $this->hopproject->cleanEntryFile($this->input->get('file', TRUE));
        $executionId = trim((string) $this->input->get('execution', TRUE));
        $wholeProject = $this->input->get('archive') === '1';

        if ($projectKey === FALSE && $executionId !== '') {
            $resolved = $this->executionSource($executionId);
            if ($resolved === NULL) {
                $this->jsonResponse(array('error' => 'That Apache Hop execution has no file in the repository to download.'), 404);
                return;
            }
            $projectKey = $resolved['project'];
            $entryFile = $resolved['file'];
        }

        if ($projectKey === FALSE) {
            $this->jsonResponse(array('error' => 'A valid Apache Hop project is required.'), 422);
            return;
        }

        $projectPath = $this->hopproject->projectPath($this->repositoryRootPath(), $projectKey);
        if ($projectPath === FALSE || ! is_dir($projectPath)) {
            $this->jsonResponse(array('error' => 'The Apache Hop project was not found.'), 404);
            return;
        }

        if ($wholeProject) {
            $this->sendProjectArchive($projectPath, $projectKey);
            return;
        }

        if ($entryFile === '' || ! $this->hopproject->hasEntryFile($projectPath, $entryFile)) {
            $this->jsonResponse(array('error' => 'That Apache Hop file was not found in the project.'), 404);
            return;
        }

        $absolute = $projectPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entryFile);
        if (is_link($absolute) || ! is_file($absolute) || ! is_readable($absolute)) {
            $this->jsonResponse(array('error' => 'That Apache Hop file could not be read.'), 404);
            return;
        }

        $this->sendDownload(basename($entryFile), 'application/xml', (string) file_get_contents($absolute));
    }

    /** Zip a whole project so it opens in the Apache Hop GUI complete. */
    private function sendProjectArchive($projectPath, $projectKey)
    {
        if (! class_exists('ZipArchive')) {
            $this->jsonResponse(array('error' => 'This installation cannot create zip archives.'), 501);
            return;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'hop-project-');
        if ($temporary === FALSE) {
            $this->jsonResponse(array('error' => 'The archive could not be created.'), 500);
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::OVERWRITE) !== TRUE) {
            @unlink($temporary);
            $this->jsonResponse(array('error' => 'The archive could not be created.'), 500);
            return;
        }

        $root = realpath($projectPath);
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $added = 0;
        foreach ($entries as $entry) {
            // Never follow a link out of the project, and keep the archive to a
            // size a browser download can actually be expected to handle.
            if ($entry->isLink() || $added > 5000) {
                continue;
            }
            $relative = ltrim(substr($entry->getPathname(), strlen($root)), '/\\');
            if ($relative === '') {
                continue;
            }
            $relative = str_replace('\\', '/', $relative);
            if ($entry->isDir()) {
                $zip->addEmptyDir($relative);
                continue;
            }
            $zip->addFile($entry->getPathname(), $relative);
            $added++;
        }
        $zip->close();

        $payload = (string) file_get_contents($temporary);
        @unlink($temporary);
        $this->sendDownload($projectKey.'.zip', 'application/zip', $payload);
    }

    private function sendDownload($fileName, $contentType, $payload)
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $fileName);
        $this->output
            ->set_content_type($contentType)
            ->set_header('Content-Disposition: attachment; filename="'.$safeName.'"')
            ->set_header('Content-Length: '.strlen($payload))
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output($payload);
    }

    /**
     * The project file behind a Hop Server execution.
     *
     * A workflow status names its own file. A pipeline status does not, so the
     * project recorded when the run was first seen is searched for the file
     * whose base name matches the object Hop registered.
     */
    private function executionSource($executionId)
    {
        $execution = $this->Hop_model->findExecution($executionId);
        if (! $execution || (string) $execution['project_key'] === '') {
            return NULL;
        }

        $projectKey = $this->hopproject->cleanProjectKey($execution['project_key']);
        if ($projectKey === FALSE) {
            return NULL;
        }
        $projectPath = $this->hopproject->projectPath($this->repositoryRootPath(), $projectKey);
        if ($projectPath === FALSE) {
            return NULL;
        }

        $filename = str_replace('\\', '/', (string) $execution['filename']);
        if ($filename !== '') {
            $marker = '/hop/projects/'.$projectKey.'/';
            $position = strpos($filename, $marker);
            if ($position !== FALSE) {
                $relative = $this->hopproject->cleanEntryFile(substr($filename, $position + strlen($marker)));
                if ($relative !== '' && $this->hopproject->hasEntryFile($projectPath, $relative)) {
                    return array('project' => $projectKey, 'file' => $relative);
                }
            }
        }

        $target = strtolower((string) $execution['name']).($execution['kind'] === 'workflow' ? '.hwf' : '.hpl');
        foreach ($this->hopproject->entryFiles($projectPath) as $entryFile) {
            if (strtolower(basename($entryFile)) === $target) {
                return array('project' => $projectKey, 'file' => $entryFile);
            }
        }

        return NULL;
    }

    /**
     * Turn a run published from the Apache Hop GUI into a real project.
     *
     * The GUI publishes by uploading a zip of whatever the designer has open,
     * so the run has no file under repository/hop/projects and JobSeeker cannot
     * schedule it. Importing the archive gives it one, and the run then offers
     * "Use in job" like any other. Hop's own execution-configuration and
     * metadata side files are dropped: JobSeeker writes the run configuration
     * the executor needs, and the environment comes from the job, not from
     * whatever the designer had selected on their laptop.
     */
    public function importExecution()
    {
        if (! $this->canManageProjects()) {
            $this->jsonResponse(array('error' => 'Access denied.'), 403);
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('error' => 'Method not allowed.'), 405);
            return;
        }

        $executionId = trim((string) $this->input->post('execution'));
        $execution = preg_match('#^[A-Za-z0-9._-]{1,100}$#', $executionId)
            ? $this->Hop_model->findExecution($executionId)
            : NULL;
        if (! $execution) {
            $this->jsonResponse(array('error' => 'That Apache Hop execution is not in the JobSeeker history.'), 404);
            return;
        }

        $repositoryRoot = $this->repositoryRootPath();
        $resolved = $this->hopserver->exportArchive($execution['filename'], $repositoryRoot);
        if ($resolved === FALSE) {
            $this->jsonResponse(array(
                'error' => 'The published archive is no longer on the shared volume. Re-run it from the Apache Hop GUI, '
                    .'then import it here; a Hop Server started before this feature wrote its exports outside the repository.'
            ), 409);
            return;
        }

        $projectKey = $this->hopproject->cleanProjectKey(
            $this->input->post('project') !== NULL && trim((string) $this->input->post('project')) !== ''
                ? $this->input->post('project')
                : ($execution['display_name'] !== '' ? $execution['display_name'] : $execution['name'])
        );
        if ($projectKey === FALSE) {
            $this->jsonResponse(array('error' => 'That name cannot be used as an Apache Hop project folder.'), 422);
            return;
        }

        $projectsRoot = $this->hopproject->projectsRoot($repositoryRoot);
        $target = $projectsRoot.DIRECTORY_SEPARATOR.$projectKey;
        if (file_exists($target) || is_link($target)) {
            $this->jsonResponse(array(
                'error' => 'An Apache Hop project called "'.$projectKey.'" already exists. Choose another name.'
            ), 409);
            return;
        }

        $staging = $target.'-import-'.substr(sha1(uniqid('', TRUE)), 0, 12);
        $extracted = $this->extractZipSafely($resolved['archive'], $staging);
        if (! $extracted['ok']) {
            $this->removeDirectory($staging);
            $this->jsonResponse(array('error' => 'The published archive could not be read: '.$extracted['message']), 422);
            return;
        }

        $entryFile = $this->installImportedProject($staging, $resolved['entry']);
        if ($entryFile === FALSE) {
            $this->removeDirectory($staging);
            $this->jsonResponse(array('error' => 'The published archive contains no Apache Hop workflow or pipeline.'), 422);
            return;
        }

        if (! @rename($staging, $target)) {
            $this->removeDirectory($staging);
            $this->jsonResponse(array('error' => 'The imported Apache Hop project could not be installed.'), 500);
            return;
        }

        $this->hopproject->ensureProjectConfig($target);
        $this->hopproject->ensureRunConfigurations($target);
        $this->hopproject->saveManifest($target, array(
            'project' => $projectKey,
            'entry_file' => $entryFile,
            'engine' => 'server',
            'description' => 'Imported from a run published to the Hop Server from the Apache Hop GUI.'
        ));

        $now = gmdate('Y-m-d H:i:s');
        $this->Hop_model->saveProject(array(
            'project_key' => $projectKey,
            'name' => $projectKey,
            'description' => 'Imported from a run published to the Hop Server from the Apache Hop GUI.',
            'environment' => strtoupper((string) $execution['environment']),
            'storage_path' => $target,
            'entry_file' => $entryFile,
            'run_config' => HopProject::DEFAULT_RUN_CONFIG,
            'engine' => 'server',
            'log_level' => HopProject::DEFAULT_LOG_LEVEL,
            'parameters_json' => json_encode(array()),
            'source' => 'hop-gui-import',
            'is_active' => 1,
            'owner' => isset($this->name) ? (string) $this->name : ''
        ));

        // The run now has a home, so the screen can offer "Use in job" for it.
        $this->Hop_model->saveExecution(array(
            'execution_id' => $execution['execution_id'],
            'project_key' => $projectKey,
            'filename' => $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entryFile)
        ));
        unset($now);

        $this->jsonResponse(array(
            'ok' => TRUE,
            'project' => $projectKey,
            'entry_file' => $entryFile,
            'path' => $target
        ));
    }

    /**
     * Lay an extracted export out the way Hop expects a project, and return the
     * entry file. Hop's export is flat, so the workflow or pipeline is moved
     * into the folder its extension belongs in.
     */
    private function installImportedProject($root, $preferredEntry)
    {
        foreach (array('__workflow_execution_configuration__.xml', '__pipeline_execution_configuration__.xml', 'metadata.json') as $sideFile) {
            $path = $root.DIRECTORY_SEPARATOR.$sideFile;
            if (is_file($path) && ! is_link($path)) {
                @unlink($path);
            }
        }

        $entryFile = '';
        $preferred = str_replace('\\', '/', trim((string) $preferredEntry));
        foreach ($this->hopproject->entryFiles($root) as $candidate) {
            if ($entryFile === '' || ($preferred !== '' && strcasecmp($candidate, $preferred) === 0)) {
                $entryFile = $candidate;
            }
        }
        if ($entryFile === '') {
            return FALSE;
        }

        // A flat export has the file at the project root; Hop projects keep
        // workflows and pipelines in their own folders, and so does everything
        // JobSeeker generates.
        if (strpos($entryFile, '/') === FALSE) {
            $folder = strtolower(substr($entryFile, -4)) === '.hwf' ? 'workflows' : 'pipelines';
            $directory = $root.DIRECTORY_SEPARATOR.$folder;
            if (! is_dir($directory) && ! @mkdir($directory, 0775, TRUE)) {
                return FALSE;
            }
            if (! @rename($root.DIRECTORY_SEPARATOR.$entryFile, $directory.DIRECTORY_SEPARATOR.$entryFile)) {
                return FALSE;
            }
            $entryFile = $folder.'/'.$entryFile;
        }

        return $entryFile;
    }

    /** The stored Hop log for one execution, shown in the screen's log viewer. */
    public function executionLog()
    {
        $executionId = trim((string) $this->input->get('execution', TRUE));
        if ($executionId === '' || ! preg_match('#^[A-Za-z0-9._-]{1,100}$#', $executionId)) {
            $this->jsonResponse(array('error' => 'A valid Apache Hop execution id is required.'), 422);
            return;
        }

        $execution = $this->Hop_model->findExecution($executionId);
        if (! $execution) {
            $this->jsonResponse(array('error' => 'That Apache Hop execution is not in the JobSeeker history.'), 404);
            return;
        }

        // A run still going has no stored log yet, so read it live.
        $log = (string) $execution['log_text'];
        if ($log === '') {
            $detail = $this->hopserver->execution($execution['kind'], $execution['name'], $executionId);
            $log = is_array($detail) ? (string) $detail['log'] : '';
        }

        $this->jsonResponse(array(
            'execution' => array(
                'execution_id' => $execution['execution_id'],
                'name' => $execution['name'],
                'display_name' => $execution['display_name'],
                'kind' => $execution['kind'],
                'status' => $execution['status'],
                'state' => $execution['state'],
                'started_at' => $execution['started_at'],
                'ended_at' => $execution['ended_at'],
                'records_total' => (int) $execution['records_total'],
                'records_processed' => (int) $execution['records_processed'],
                'errors' => (int) $execution['errors'],
                'source' => $execution['source'],
                'tmf_instance_id' => $execution['tmf_instance_id']
            ),
            'log' => $log === '' ? 'Apache Hop did not return a log for this execution.' : $log
        ));
    }

    /** JSON list used by the Job Creation form to populate its project picker. */
    public function projects()
    {
        $projects = array();
        foreach ($this->catalog($this->selectedEnvironment()) as $project) {
            $projects[] = array(
                'key' => $project['key'],
                'name' => $project['name'],
                'path' => $project['path'],
                'entry_files' => $project['entry_files'],
                'entry_file' => $project['entry_file'],
                'valid' => $project['valid'],
                'missing' => $project['missing']
            );
        }
        $this->jsonResponse(array('projects' => $projects));
    }

    /**
     * Describe one project. Job Creation calls this after an upload so the entry
     * file picker reflects what actually arrived on the server.
     */
    public function inspect()
    {
        $key = $this->hopproject->cleanProjectKey($this->input->get('project', TRUE));
        if ($key === FALSE) {
            $this->jsonResponse(array('error' => 'A valid Apache Hop project key is required.'), 422);
            return;
        }

        $path = $this->hopproject->projectPath($this->repositoryRootPath(), $key);
        if ($path === FALSE || ! is_dir($path)) {
            $this->jsonResponse(array('error' => 'The Apache Hop project was not found.'), 404);
            return;
        }

        $located = $this->hopproject->locate($path);
        if ($located === FALSE) {
            $this->jsonResponse(array('error' => 'That folder does not contain an Apache Hop project.'), 404);
            return;
        }

        $this->jsonResponse($this->hopproject->describe($located));
    }

    public function serverStatusJson()
    {
        $this->jsonResponse($this->serverStatus());
    }

    /**
     * Remove a project from the registry and, when asked, from the repository.
     * The Jenkins jobs that used it are left alone: deleting a job is the Delete
     * Job screen's responsibility, and a job whose project vanished should fail
     * loudly rather than disappear quietly.
     */
    public function delete()
    {
        if (! $this->canManageProjects()) {
            $this->jsonResponse(array('error' => 'Access denied.'), 403);
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('error' => 'Method not allowed.'), 405);
            return;
        }

        $key = $this->hopproject->cleanProjectKey($this->input->post('project'));
        if ($key === FALSE) {
            $this->jsonResponse(array('error' => 'A valid Apache Hop project key is required.'), 422);
            return;
        }

        $removeFiles = $this->input->post('remove_files') === '1';
        $path = $this->hopproject->projectPath($this->repositoryRootPath(), $key);
        $jobs = $this->Hop_model->jobsForProject($key);

        $filesRemoved = FALSE;
        if ($removeFiles) {
            $projectsRoot = $this->hopproject->projectsRoot($this->repositoryRootPath());
            $safeTarget = $path !== FALSE && (
                is_link($path)
                || ! file_exists($path)
                || $this->pathWithinBase($path, $projectsRoot)
            );
            if (! $safeTarget) {
                $this->jsonResponse(array('error' => 'The Apache Hop project path is outside the repository.'), 409);
                return;
            }
            $filesRemoved = ! file_exists($path) && ! is_link($path) ? TRUE : $this->removeDirectory($path);
            if (! $filesRemoved) {
                $this->jsonResponse(array('error' => 'The Apache Hop project files could not be removed.'), 500);
                return;
            }
        }

        $this->Hop_model->deleteProject($key);
        $this->jsonResponse(array(
            'ok' => TRUE,
            'project' => $key,
            'files_removed' => $filesRemoved,
            'jobs' => array_map(function($job) { return $job['job_name']; }, $jobs)
        ));
    }

    private function removeDirectory($path)
    {
        // Never traverse a link: unlinking the link is safe, recursing through
        // it could remove content outside repository/hop/projects.
        if (is_link($path)) {
            return @unlink($path);
        }
        if (! is_dir($path)) {
            return @unlink($path);
        }
        $removed = TRUE;
        foreach ((array) scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (! $this->removeDirectory($path.DIRECTORY_SEPARATOR.$entry)) {
                $removed = FALSE;
            }
        }
        return $removed && @rmdir($path);
    }
}

/* End of file Hop.php */
/* Location: ./application/controllers/Hop.php */
