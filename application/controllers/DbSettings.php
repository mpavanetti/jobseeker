<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';
require APPPATH . '/controllers/concerns/JenkinsRunnerTrait.php';


class DbSettings extends BaseController
{
    use JenkinsRunnerTrait;

    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(array('url', 'form'));
        $this->load->model('DbSettings_model', 'model');
        $this->load->library('session');
        $this->isLoggedIn();
    }

    private function canManageConnectors()
    {
        return ! $this->isManager();
    }

    private function connectorTypes()
    {
        return array(
            'mysql' => 'MySQL / MariaDB',
            'pgsql' => 'PostgreSQL',
            'sqlserver' => 'SQL Server',
            'oracle_service' => 'Oracle service name',
            'oracle_sid' => 'Oracle SID',
            'mongodb' => 'MongoDB',
            'redis' => 'Redis',
            'snowflake' => 'Snowflake',
            'databricks' => 'Databricks SQL',
            'kafka' => 'Apache Kafka',
            'rabbitmq' => 'RabbitMQ',
            'elasticsearch' => 'Elasticsearch / OpenSearch',
            'sftp' => 'SFTP / SSH',
            'http_api' => 'HTTP API',
            'aws_s3' => 'AWS S3',
            'azure_blob' => 'Azure Blob Storage',
            'azure_data_lake' => 'Azure Data Lake',
            'gcs' => 'Google Cloud Storage',
            'git_repository' => 'Git repository credentials',
            'generic_secret' => 'Generic credential'
        );
    }

    private function authenticationTypes()
    {
        return array(
            'username_password' => 'Username and password',
            'token' => 'Bearer / access token',
            'api_key' => 'API key',
            'sas_token' => 'Azure SAS token',
            'connection_string' => 'Connection string',
            'managed_identity' => 'Azure managed identity',
            'workload_identity' => 'Workload identity',
            'service_principal' => 'Azure service principal',
            'iam_role' => 'AWS IAM role',
            'web_identity' => 'AWS web identity',
            'access_key' => 'AWS access key',
            'ssh_key' => 'SSH private key',
            'none' => 'No credential',
            'custom' => 'Custom fields'
        );
    }

    private function connectorNeedsEndpoint($type)
    {
        return ! in_array($type, array('aws_s3', 'azure_blob', 'azure_data_lake', 'gcs', 'generic_secret'), TRUE);
    }

    private function secretBackends()
    {
        return array(
            'local' => 'Encrypted in JobSeeker',
            'environment' => 'Environment variables',
            'azure_key_vault' => 'Azure Key Vault',
            'aws_secrets_manager' => 'AWS Secrets Manager'
        );
    }

    private function environments()
    {
        if (! $this->db->table_exists('environment')) {
            return array();
        }
        return $this->jobSeekerFilterEnvironmentRows($this->db->select('Environment')->from('environment')->where('IsActive', 1)->order_by('Environment', 'ASC')->get()->result());
    }

    private function selectedGlobalEnvironment()
    {
		if ($this->jobSeekerIsStandaloneDeployment()) {
			return $this->jobSeekerStandaloneEnvironment();
		}
        $value = trim((string) $this->input->get('environment', TRUE));
        if ($value === '') {
            $value = $this->jobSeekerEnvironmentPreference();
        }
        $environment = $this->normalizeJobSeekerEnvironment($value);
        if ($environment === '' || $environment === '*' || $environment === 'ALL') {
            return 'ALL';
        }
        $available = array_map(function($row) { return $this->normalizeJobSeekerEnvironment($row->Environment); }, $this->environments());
        return in_array($environment, $available, TRUE) ? $environment : 'ALL';
    }

    private function connectorMatchesSelectedEnvironment($connector)
    {
        if (! $connector) {
            return FALSE;
        }
        $selectedEnvironment = $this->selectedGlobalEnvironment();
        if ($selectedEnvironment === 'ALL') {
            return TRUE;
        }

        return in_array($this->normalizeJobSeekerEnvironment($connector->environment), array($selectedEnvironment, 'ALL'), TRUE);
    }

    private function normalizeConnectorKey($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }

    private function normalizeJobName($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '*' || strtoupper($value) === 'ALL') {
            return '*';
        }
        return preg_match('/^[A-Za-z0-9._\-\/ ]{1,200}$/', $value) ? $value : FALSE;
    }

    private function fieldMappings($inputName, $valuePattern = NULL)
    {
        $raw = str_replace("\r", '', (string) $this->input->post($inputName));
        if ($raw === '') {
            return array();
        }
        if (strlen($raw) > 32768) {
            return FALSE;
        }

        $mappings = array();
        foreach (explode("\n", $raw) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = explode('=', $line, 2);
            $name = strtolower(trim($parts[0]));
            $value = isset($parts[1]) ? $parts[1] : '';
            if (! preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $name) || $value === '' || strlen($value) > 4096
                || strpos($value, "\0") !== FALSE || ($valuePattern !== NULL && ! preg_match($valuePattern, $value))) {
                return FALSE;
            }
            $mappings[$name] = $value;
            if (count($mappings) > 32) {
                return FALSE;
            }
        }
        return $mappings;
    }

    private function secretReference($backend)
    {
        if ($backend === 'environment') {
            $mappings = $this->fieldMappings('environment_mappings', '/^[A-Za-z_][A-Za-z0-9_]*$/');
            if (empty($mappings)) {
                $usernameVariable = trim((string) $this->input->post('username_env'));
                $passwordVariable = trim((string) $this->input->post('password_env'));
                if ($usernameVariable !== '') {
                    $mappings['username'] = $usernameVariable;
                }
                if ($passwordVariable !== '') {
                    $mappings['password'] = $passwordVariable;
                }
            }
            if ($mappings === FALSE || empty($mappings)) {
                return FALSE;
            }
            return array('variables' => $mappings);
        }

        if ($backend === 'azure_key_vault') {
            $vaultUrl = rtrim(strtolower(trim((string) $this->input->post('vault_url'))), '/');
            $authMode = strtolower(trim((string) $this->input->post('azure_auth_mode')));
            $clientId = trim((string) $this->input->post('managed_identity_client_id'));
            $mappings = $this->fieldMappings('azure_secret_mappings', '/^[A-Za-z0-9-]{1,127}$/');
            if (empty($mappings)) {
                $usernameSecret = trim((string) $this->input->post('username_secret'));
                $passwordSecret = trim((string) $this->input->post('password_secret'));
                if ($usernameSecret !== '') {
                    $mappings['username'] = $usernameSecret;
                }
                if ($passwordSecret !== '') {
                    $mappings['password'] = $passwordSecret;
                }
            }
            if (! preg_match('#^https://[a-z0-9-]{3,63}\.vault\.azure\.net$#', $vaultUrl)
                || ! in_array($authMode, array('default', 'managed_identity', 'workload_identity', 'environment'), TRUE)
                || $mappings === FALSE || empty($mappings)
                || ($clientId !== '' && ! preg_match('/^[A-Fa-f0-9-]{36}$/', $clientId))) {
                return FALSE;
            }
            return array(
                'vault_url' => $vaultUrl,
                'auth_mode' => $authMode,
                'secrets' => $mappings,
                'managed_identity_client_id' => $clientId
            );
        }

        if ($backend === 'aws_secrets_manager') {
            $region = strtolower(trim((string) $this->input->post('aws_region')));
            $secretId = trim((string) $this->input->post('aws_secret_id'));
            $authMode = strtolower(trim((string) $this->input->post('aws_auth_mode')));
            $profileName = trim((string) $this->input->post('aws_profile_name'));
            $mappings = $this->fieldMappings('aws_field_mappings', '/^[A-Za-z0-9_.-]{1,128}$/');
            if (empty($mappings)) {
                $usernameField = trim((string) $this->input->post('aws_username_field'));
                $passwordField = trim((string) $this->input->post('aws_password_field'));
                if ($usernameField !== '') {
                    $mappings['username'] = $usernameField;
                }
                if ($passwordField !== '') {
                    $mappings['password'] = $passwordField;
                }
            }
            if (! preg_match('/^[a-z]{2}(?:-gov)?-[a-z]+-[0-9]$/', $region)
                || $secretId === '' || strlen($secretId) > 512 || preg_match('/[\x00-\x1F\x7F]/', $secretId)
                || ! in_array($authMode, array('default', 'iam_role', 'web_identity', 'environment', 'profile'), TRUE)
                || ($authMode === 'profile' && ! preg_match('/^[A-Za-z0-9_.-]{1,128}$/', $profileName))
                || $mappings === FALSE || empty($mappings)) {
                return FALSE;
            }
            return array(
                'region' => $region,
                'secret_id' => $secretId,
                'auth_mode' => $authMode,
                'profile_name' => $profileName,
                'fields' => $mappings
            );
        }

        return array();
    }

    private function saveConnector($id)
    {
        if (! $this->canManageConnectors()) {
            $this->loadThis();
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->output->set_status_header(405);
            return;
        }

        $existing = $id > 0 ? $this->model->getSetting($id, TRUE) : NULL;
        if ($id > 0 && ! $existing) {
            $this->session->set_flashdata('error', 'The connector no longer exists.');
            redirect('dbSettings');
        }

        $connectorKey = $this->normalizeConnectorKey($this->input->post('connector_key'));
        $environment = $existing ? (string) $existing->environment : $this->selectedGlobalEnvironment();
        $jobName = $this->normalizeJobName($this->input->post('job_name'));
        $dbType = strtolower(trim((string) $this->input->post('db_type')));
        $authType = strtolower(trim((string) $this->input->post('auth_type')));
        $backend = strtolower(trim((string) $this->input->post('secret_backend')));
        $address = trim((string) $this->input->post('address'));
        $port = (int) $this->input->post('port');
        $database = trim((string) $this->input->post('schema'));
        $description = trim((string) $this->input->post('description'));
        $additionalParameters = trim((string) $this->input->post('additional_parameters'));

        if ($connectorKey === '' || strlen($connectorKey) > 128 || $jobName === FALSE
            || ! isset($this->connectorTypes()[$dbType]) || ! isset($this->authenticationTypes()[$authType]) || ! isset($this->secretBackends()[$backend])
            || ($this->connectorNeedsEndpoint($dbType) && $address === '') || strlen($address) > 255 || preg_match('/[\x00-\x1F\x7F]/', $address)
            || $port < 0 || $port > 65535 || strlen($database) > 200
            || strlen($description) > 2000 || strlen($additionalParameters) > 1000) {
            $this->session->set_flashdata('error', 'Check the connector key, scope, database details, and secret backend.');
            redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
        }
        if (($dbType === 'oracle_service' && trim((string) $this->input->post('oracle_ServiceName')) === '')
            || ($dbType === 'oracle_sid' && trim((string) $this->input->post('oracle_sid')) === '')) {
            $this->session->set_flashdata('error', 'Provide the required Oracle service name or SID.');
            redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
        }
        if ($dbType === 'git_repository') {
            if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?)$/i', $address)
                || ! in_array($authType, array('token', 'api_key', 'username_password', 'ssh_key', 'none'), TRUE)) {
                $this->session->set_flashdata('error', 'Git connectors require a provider hostname and token, username/password, SSH key, or no authentication.');
                redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
            }
        }
        $availableEnvironments = array_map(function($row) { return strtoupper($row->Environment); }, $this->environments());
        if ($environment !== 'ALL' && ! in_array($environment, $availableEnvironments, TRUE)) {
            $this->session->set_flashdata('error', 'Select an environment configured in Context Settings.');
            redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
        }
        if ($this->model->scopeExists($connectorKey, $environment, $jobName, $id)) {
            $this->session->set_flashdata('error', 'That connector key already exists for the selected environment and job scope.');
            redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
        }

        $reference = $this->secretReference($backend);
        if ($reference === FALSE) {
            $this->session->set_flashdata('error', 'The selected secret backend configuration is invalid.');
            redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
        }

        $encryptedSecret = NULL;
        if ($backend === 'local') {
            $clearLocalSecrets = $existing && $existing->secret_backend === 'local'
                && (string) $this->input->post('clear_local_secrets') === '1';
            $username = (string) $this->input->post('login');
            $password = (string) $this->input->post('password');
            if (strlen($username) > 500 || strlen($password) > 2000) {
                $this->session->set_flashdata('error', 'The local username or password is too long.');
                redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
            }
            $additionalSecrets = $this->fieldMappings('local_secret_fields');
            if ($additionalSecrets === FALSE) {
                $this->session->set_flashdata('error', 'Additional local secrets must use field=value lines with safe field names.');
                redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
            }
            $secretValues = array();
            if ($existing && $existing->secret_backend === 'local' && ! $clearLocalSecrets) {
                $secretValues = $this->model->decryptLocalSecret($existing->secret_encrypted);
                if ($secretValues === FALSE) {
                    $this->session->set_flashdata('error', 'The existing local connector secret could not be read.');
                    redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
                }
            }
            $secretValues = array_merge($secretValues, $additionalSecrets);
            if ($username !== '') {
                $secretValues['username'] = $username;
            }
            if ($password !== '') {
                $secretValues['password'] = $password;
            }
            if ($authType === 'username_password'
                && (! isset($secretValues['username']) || (string) $secretValues['username'] === ''
                    || ! isset($secretValues['password']) || (string) $secretValues['password'] === '')) {
                $this->session->set_flashdata('error', $clearLocalSecrets
                    ? 'Username/password authentication cannot clear its required values. Provide both values or select another authentication type.'
                    : 'Username/password authentication requires both values.');
                redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
            }
            if ($dbType === 'git_repository' && $authType === 'ssh_key'
                && ((! isset($secretValues['private_key']) && ! isset($secretValues['ssh_key']))
                    || ! isset($secretValues['known_hosts']))) {
                $this->session->set_flashdata('error', 'SSH Git authentication requires private_key and known_hosts in the additional local secret fields.');
                redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
            }
            if ($dbType === 'git_repository' && in_array($authType, array('token', 'api_key'), TRUE)
                && ! isset($secretValues[$authType]) && ! isset($secretValues['password'])) {
                $this->session->set_flashdata('error', 'Git token authentication requires a token/api_key additional field or the password field.');
                redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
            }
            $encryptedSecret = $this->model->encryptSecretValues($secretValues);
            if ($encryptedSecret === FALSE || $encryptedSecret === NULL) {
                $this->session->set_flashdata('error', 'The local connector secret could not be encrypted.');
                redirect('dbSettings'.($id > 0 ? '?edit='.$id : '?create=1'));
            }
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'connector_key' => $connectorKey,
            'job_name' => $jobName,
            'environment' => $environment,
            'db_type' => $dbType,
            'auth_type' => $authType,
            'login' => '',
            'password' => '',
            'address' => $address,
            'port' => (string) $port,
            'schema' => $database,
            'description' => $description,
            'secret_backend' => $backend,
            'secret_reference' => json_encode($reference),
            'secret_encrypted' => $encryptedSecret,
            'is_active' => $this->input->post('is_active') === '0' ? 0 : 1,
            'additional_parameters' => $additionalParameters,
            'oracle_ServiceName' => $dbType === 'oracle_service' ? trim((string) $this->input->post('oracle_ServiceName')) : '',
            'oracle_sid' => $dbType === 'oracle_sid' ? trim((string) $this->input->post('oracle_sid')) : '',
            'updated_at' => $now,
            'owner' => $this->name
        );
        if (! $existing) {
            $data['creation_date'] = $now;
        }

        $savedId = $this->model->saveSetting($data, $id);
        $this->session->set_flashdata($savedId > 0 ? 'success' : 'error', $savedId > 0 ? 'Connector saved.' : 'Connector could not be saved.');
        redirect('dbSettings');
    }

    public function index()
    {
        if (! $this->canManageConnectors()) {
            $this->loadThis();
            return;
        }

        $selectedEnvironment = $this->selectedGlobalEnvironment();
        $editId = (int) $this->input->get('edit');
        $editing = $editId > 0 ? $this->model->getSetting($editId) : NULL;
        if ($editing && $selectedEnvironment !== 'ALL' && ! in_array($this->normalizeJobSeekerEnvironment($editing->environment), array($selectedEnvironment, 'ALL'), TRUE)) {
            $editing = NULL;
        }
        if ($editing) {
            $editing->secret_reference_values = json_decode((string) $editing->secret_reference, TRUE) ?: array();
        }

        $this->global['pageTitle'] = 'Job Seeker : Connectors';
        $data = array(
            'settings' => $this->model->listSettings($selectedEnvironment),
            'editing' => $editing,
            'showForm' => $this->input->get('create') === '1' || $editing !== NULL,
            'connectorTypes' => $this->connectorTypes(),
            'authenticationTypes' => $this->authenticationTypes(),
            'secretBackends' => $this->secretBackends(),
            'environments' => $this->environments(),
            'selectedEnvironment' => $selectedEnvironment
        );
        $this->loadViews('connectors', $this->global, $data, NULL);
    }

    public function addDbSetting()
    {
        redirect('dbSettings?create=1&environment='.rawurlencode($this->selectedGlobalEnvironment()));
    }


    /**
    * Edit Input Component
     */
    function EditSettingsFetchData($id = NULL)
    {
        redirect('dbSettings'.((int) $id > 0 ? '?edit='.(int) $id.'&environment='.rawurlencode($this->selectedGlobalEnvironment()) : ''));
    }


    public function InsertDbSettings() {
        $this->saveConnector(0);
    }

    public function UpdateDbSettings() {
        $this->saveConnector((int) $this->input->post('id'));
    }

    private function jsonResponse($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store, max-age=0')
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }

    private function connectorTestEndpoint($connector)
    {
        $endpoint = trim((string) $connector->address);
        $port = (int) $connector->port;
        if ($endpoint === '') {
            return array('status' => 'skipped', 'reachable' => NULL);
        }

        $endpoint = trim(explode(',', $endpoint, 2)[0]);
        $url = strpos($endpoint, '://') === FALSE ? 'tcp://'.$endpoint : $endpoint;
        $parts = parse_url($url);
        $host = is_array($parts) && isset($parts['host']) ? $parts['host'] : '';
        if ($port < 1 && is_array($parts) && isset($parts['port'])) {
            $port = (int) $parts['port'];
        }
        if ($port < 1 && is_array($parts) && isset($parts['scheme'])) {
            $port = strtolower($parts['scheme']) === 'http' ? 80 : (strtolower($parts['scheme']) === 'https' ? 443 : 0);
        }
        if ($host === '' || $port < 1 || $port > 65535) {
            return array('status' => 'not_configured', 'reachable' => NULL);
        }
        $resolvedHost = gethostbyname($host);
        if (strpos($resolvedHost, '169.254.') === 0 || stripos($host, 'fe80:') === 0) {
            return array('status' => 'blocked', 'reachable' => FALSE);
        }

        $errorNumber = 0;
        $errorMessage = '';
        $socket = @fsockopen($host, $port, $errorNumber, $errorMessage, 3);
        if ($socket === FALSE) {
            return array('status' => 'unreachable', 'reachable' => FALSE);
        }
        fclose($socket);
        return array('status' => 'reachable', 'reachable' => TRUE);
    }

    public function testConnector()
    {
        if (! $this->canManageConnectors()) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Access denied.'), 403);
            return;
        }
        if ($this->input->method(TRUE) !== 'POST') {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Method not allowed.'), 405);
            return;
        }

        $connector = $this->model->getSetting((int) $this->input->post('id'), TRUE);
        if (! $connector) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'Connector not found.'), 404);
            return;
        }
        if (! $this->connectorMatchesSelectedEnvironment($connector)) {
            $this->jsonResponse(array('ok' => FALSE, 'message' => 'The connector is outside the selected environment.'), 409);
            return;
        }

        $mode = strtolower(trim((string) ($this->input->post('mode') ?: $this->input->get('mode'))));
        if ($mode === 'quick') {
            $this->quickConnectorProbe($connector);
            return;
        }
        $this->liveConnectorTest($connector);
    }

    /**
     * Full protocol handshake on a Jenkins worker. Delegates the run to the
     * shared JenkinsRunnerTrait helper so Job Creation can reuse it.
     */
    private function liveConnectorTest($connector)
    {
        $environment = $this->connectionTestEnvironment($connector->environment);
        $result = $this->runConnectorConnectionTest((string) $connector->connector_key, $environment, (string) $connector->job_name);
        $this->model->logRuntimeAccess((array) $connector, $connector->environment, $connector->job_name, $result['ok'] ? 'test_passed' : 'test_failed');

        $httpStatus = isset($result['httpStatus']) ? (int) $result['httpStatus'] : ($result['ok'] ? 200 : 422);
        unset($result['httpStatus']);
        if (($result['connectorType'] ?? '') === '') {
            $result['connectorType'] = (string) $connector->db_type;
        }
        $this->jsonResponse($result, $httpStatus);
    }

    private function connectionTestEnvironment($connectorEnvironment)
    {
        $environment = $this->normalizeJobSeekerEnvironment((string) $connectorEnvironment);
        if ($environment === '' || $environment === 'ALL' || $environment === '*') {
            $active = array_map(function($row) { return strtoupper($row->Environment); }, $this->environments());
            $environment = ! empty($active) ? $active[0] : 'DEV';
        }
        return $environment;
    }

    private function quickConnectorProbe($connector)
    {
        $secretReady = FALSE;
        $credentialStatus = 'Credential reference is invalid.';
        if ($connector->secret_backend === 'local') {
            $values = $this->model->decryptLocalSecret($connector->secret_encrypted);
            $secretReady = is_array($values) && ($connector->auth_type === 'none' || ! empty($values));
            $credentialStatus = $secretReady ? 'Encrypted values are readable.' : 'Encrypted values could not be read.';
            unset($values);
        } else {
            $reference = json_decode((string) $connector->secret_reference, TRUE);
            $secretReady = is_array($reference) && ! empty($reference);
            $credentialStatus = $secretReady
                ? 'Reference is valid. Authentication is resolved by the Jenkins worker at run time.'
                : 'The external secret reference is incomplete.';
        }

        $network = $this->connectorTestEndpoint($connector);
        $networkReady = $network['reachable'] === TRUE
            || ($network['status'] === 'skipped' && ! $this->connectorNeedsEndpoint((string) $connector->db_type));
        $ok = $secretReady && $networkReady;
        $this->model->logRuntimeAccess((array) $connector, $connector->environment, $connector->job_name, $ok ? 'test_passed' : 'test_failed');

        if ($network['status'] === 'reachable') {
            $networkStatus = 'Endpoint accepted a TCP connection.';
        } else if ($network['status'] === 'blocked') {
            $networkStatus = 'TCP check blocked for a link-local address.';
        } else if ($network['status'] === 'unreachable') {
            $networkStatus = 'Endpoint did not accept a TCP connection within 3 seconds.';
        } else if ($network['status'] === 'not_configured') {
            $networkStatus = 'TCP check failed because the configured endpoint or port is incomplete.';
        } else {
            $networkStatus = 'TCP check skipped because this connector type does not require an endpoint.';
        }
        $this->jsonResponse(array(
            'ok' => $ok,
            'secretReady' => $secretReady,
            'network' => $network['status'],
            'message' => $credentialStatus.' '.$networkStatus
        ), $ok ? 200 : 422);
    }


    public function deleteSetting() {

        if (! $this->canManageConnectors())
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

            $id = (int) $this->input->post('userId');
            $connector = $this->model->getSetting($id, TRUE);
            if (! $this->connectorMatchesSelectedEnvironment($connector)) {
                $this->output->set_status_header($connector ? 409 : 404);
                echo(json_encode(array('status'=>FALSE, 'message'=>$connector ? 'The connector is outside the selected environment.' : 'Connector not found.')));
                return;
            }
            $result = $this->model->deleteSetting($id);
            
            if ($result > 0) { echo(json_encode(array('status'=>TRUE, 'id' => $id))); }
            else { echo(json_encode(array('status'=>FALSE, 'id' => $id))); }
        }
    }
    
}

?>
