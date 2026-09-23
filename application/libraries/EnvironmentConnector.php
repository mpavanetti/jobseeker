<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_ENVIRONMENT_CONNECTOR_TEST')) exit('No direct script access allowed');

/**
 * Read-only connectors supplied by the deployment through `.env`.
 *
 * A connector named `warehouse` is described with variables such as:
 *
 *   JOBSEEKER_CONNECTOR_WAREHOUSE_TYPE=mysql
 *   JOBSEEKER_CONNECTOR_WAREHOUSE_HOST=db.internal
 *   JOBSEEKER_CONNECTOR_WAREHOUSE_USERNAME=etl
 *   JOBSEEKER_CONNECTOR_WAREHOUSE_PASSWORD=...
 *
 * The values never enter the database and secret values are never put in a UI
 * row. ConnectorRuntime receives them in-process and returns the same ephemeral
 * secret payload it returns for an encrypted local connector.
 */
class EnvironmentConnector
{
    const PREFIX = 'JOBSEEKER_CONNECTOR_';
    const MAX_CONNECTORS = 100;
    const MAX_SECRET_LENGTH = 16384;

    private $connectors = NULL;

    private $types = array(
        'mysql', 'pgsql', 'sqlserver', 'oracle_service', 'oracle_sid',
        'mongodb', 'redis', 'snowflake', 'databricks', 'kafka',
        'rabbitmq', 'elasticsearch', 'sftp', 'http_api', 'aws_s3',
        'azure_blob', 'azure_data_lake', 'gcs', 'git_repository',
        'generic_secret'
    );

    private $authTypes = array(
        'username_password', 'token', 'api_key', 'sas_token',
        'connection_string', 'managed_identity', 'workload_identity',
        'service_principal', 'iam_role', 'web_identity', 'access_key',
        'ssh_key', 'none', 'custom'
    );

    private $fieldSuffixes = array(
        'ADDITIONAL_PARAMETERS' => 'additional_parameters',
        'CONNECTION_STRING' => 'connection_string',
        'ORACLE_SERVICE_NAME' => 'oracle_ServiceName',
        'ENVIRONMENT' => 'environment',
        'DESCRIPTION' => 'description',
        'PRIVATE_KEY' => 'private_key',
        'KNOWN_HOSTS' => 'known_hosts',
        'AUTH_TYPE' => 'auth_type',
        'USERNAME' => 'username',
        'PASSWORD' => 'password',
        'SAS_TOKEN' => 'sas_token',
        'API_KEY' => 'api_key',
        'DATABASE' => 'schema',
        'PARAMETERS' => 'additional_parameters',
        'ADDRESS' => 'address',
        'TOKEN' => 'token',
        'SECRET' => 'secret',
        'ACCESS_KEY' => 'access_key',
        'SECRET_KEY' => 'secret_key',
        'TYPE' => 'db_type',
        'HOST' => 'address',
        'PORT' => 'port',
        'SCHEMA' => 'schema',
        'JOB' => 'job_name',
        'SID' => 'oracle_sid'
    );

    private $secretFields = array(
        'username', 'password', 'token', 'api_key', 'sas_token',
        'connection_string', 'private_key', 'known_hosts', 'secret',
        'access_key', 'secret_key'
    );

    /** @return array[] connector rows shaped like database_settings rows */
    public function rows($includeSecrets = FALSE)
    {
        $rows = array();
        foreach ($this->definitions() as $definition) {
            $row = $definition['row'];
            if ($includeSecrets) {
                $row['_secret_values'] = $definition['secrets'];
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function definitions()
    {
        if ($this->connectors !== NULL) {
            return $this->connectors;
        }

        $grouped = array();
        foreach ($this->candidates() as $variable => $rawValue) {
            $parsed = $this->fieldForVariable((string) $variable);
            if ($parsed === NULL) {
                continue;
            }
            $value = (string) $rawValue;
            if (strpos($value, "\0") !== FALSE || strlen($value) > self::MAX_SECRET_LENGTH) {
                continue;
            }
            if (! isset($grouped[$parsed['name']])) {
                $grouped[$parsed['name']] = array('fields' => array(), 'variables' => array());
            }
            $grouped[$parsed['name']]['fields'][$parsed['field']] = $value;
            $grouped[$parsed['name']]['variables'][$parsed['field']] = (string) $variable;
        }

        ksort($grouped);
        $this->connectors = array();
        foreach ($grouped as $name => $item) {
            $definition = $this->definition($name, $item['fields'], $item['variables']);
            if ($definition !== NULL) {
                $this->connectors[] = $definition;
            }
            if (count($this->connectors) >= self::MAX_CONNECTORS) {
                break;
            }
        }
        return $this->connectors;
    }

    private function fieldForVariable($variable)
    {
        if (strpos($variable, self::PREFIX) !== 0) {
            return NULL;
        }
        $tail = substr($variable, strlen(self::PREFIX));
        foreach ($this->fieldSuffixes as $suffix => $field) {
            $marker = '_'.$suffix;
            if (strlen($tail) <= strlen($marker) || substr($tail, -strlen($marker)) !== $marker) {
                continue;
            }
            $name = substr($tail, 0, -strlen($marker));
            if (! preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $name)) {
                return NULL;
            }
            return array('name' => $name, 'field' => $field);
        }
        return NULL;
    }

    private function definition($name, array $fields, array $variables)
    {
        $type = strtolower(trim(isset($fields['db_type']) ? $fields['db_type'] : ''));
        if (! in_array($type, $this->types, TRUE)) {
            // TYPE is deliberately required. It prevents platform variables
            // such as JOBSEEKER_CONNECTOR_API_TOKEN from becoming connectors.
            return NULL;
        }

        $environment = strtoupper(trim(isset($fields['environment']) ? $fields['environment'] : 'ALL'));
        $jobName = trim(isset($fields['job_name']) ? $fields['job_name'] : '*');
        $address = trim(isset($fields['address']) ? $fields['address'] : '');
        $schema = trim(isset($fields['schema']) ? $fields['schema'] : '');
        $description = trim(isset($fields['description']) ? $fields['description'] : '');
        $parameters = trim(isset($fields['additional_parameters']) ? $fields['additional_parameters'] : '');
        $port = isset($fields['port']) && $fields['port'] !== '' ? (int) $fields['port'] : $this->defaultPort($type);

        if (! preg_match('/^(?:ALL|[A-Z0-9][A-Z0-9._-]{0,99})$/', $environment)
            || ! preg_match('/^(?:\*|[A-Za-z0-9._\-\/ ][A-Za-z0-9._\-\/ ]{0,199})$/', $jobName)
            || $port < 0 || $port > 65535
            || strlen($address) > 255 || strlen($schema) > 200
            || strlen($description) > 2000 || strlen($parameters) > 1000
            || preg_match('/[\x00-\x1F\x7F]/', $address.$schema.$description.$parameters)) {
            return NULL;
        }
        if ($this->needsEndpoint($type) && $address === '') {
            return NULL;
        }

        $secrets = array();
        $secretVariables = array();
        foreach ($this->secretFields as $field) {
            if (! array_key_exists($field, $fields) || $fields[$field] === '') {
                continue;
            }
            $secrets[$field] = (string) $fields[$field];
            $secretVariables[$field] = $variables[$field];
        }

        $authType = strtolower(trim(isset($fields['auth_type']) ? $fields['auth_type'] : $this->defaultAuthType($secrets)));
        if (! in_array($authType, $this->authTypes, TRUE)) {
            return NULL;
        }

        $key = strtolower(str_replace('_', '-', $name));
        $row = array(
            'id' => 0,
            'connector_key' => $key,
            'job_name' => $jobName === '' ? '*' : $jobName,
            'environment' => $environment,
            'db_type' => $type,
            'auth_type' => $authType,
            'login' => '',
            'password' => '',
            'address' => $address,
            'port' => (string) $port,
            'schema' => $schema,
            'description' => $description !== '' ? $description : 'Supplied by the deployment environment.',
            'secret_backend' => 'deployment',
            'secret_reference' => json_encode(array('variables' => $secretVariables)),
            'secret_encrypted' => NULL,
            'is_active' => 1,
            'additional_parameters' => $parameters,
            'oracle_ServiceName' => isset($fields['oracle_ServiceName']) ? trim($fields['oracle_ServiceName']) : '',
            'oracle_sid' => isset($fields['oracle_sid']) ? trim($fields['oracle_sid']) : '',
            'creation_date' => NULL,
            'updated_at' => NULL,
            'owner' => 'environment',
            'readOnly' => TRUE,
            'source' => 'environment',
            'secretFields' => array_keys($secrets),
            'variablePrefix' => self::PREFIX.$name.'_'
        );
        return array('row' => $row, 'secrets' => $secrets);
    }

    private function defaultAuthType(array $secrets)
    {
        if (isset($secrets['username']) || isset($secrets['password'])) return 'username_password';
        if (isset($secrets['token'])) return 'token';
        if (isset($secrets['api_key'])) return 'api_key';
        if (isset($secrets['sas_token'])) return 'sas_token';
        if (isset($secrets['connection_string'])) return 'connection_string';
        if (isset($secrets['private_key'])) return 'ssh_key';
        return empty($secrets) ? 'none' : 'custom';
    }

    private function defaultPort($type)
    {
        $ports = array(
            'mysql' => 3306, 'pgsql' => 5432, 'sqlserver' => 1433,
            'oracle_service' => 1521, 'oracle_sid' => 1521,
            'mongodb' => 27017, 'redis' => 6379, 'snowflake' => 443,
            'databricks' => 443, 'kafka' => 9092, 'rabbitmq' => 5672,
            'elasticsearch' => 9200, 'sftp' => 22, 'http_api' => 443,
            'git_repository' => 443
        );
        return isset($ports[$type]) ? $ports[$type] : 0;
    }

    private function needsEndpoint($type)
    {
        return ! in_array($type, array('aws_s3', 'azure_blob', 'azure_data_lake', 'gcs', 'generic_secret'), TRUE);
    }

    /** Overridable by the behavioral test. */
    protected function candidates()
    {
        $environment = getenv();
        return is_array($environment) ? $environment : array();
    }
}
