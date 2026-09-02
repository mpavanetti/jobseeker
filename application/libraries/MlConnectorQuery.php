<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Runs a bounded read-only SELECT against a stored JobSeeker connector and
 * writes the result to a temp CSV, so a dataset can be registered from a
 * connector query. Supports the same drivers the Visualization studio does
 * (mysql / pgsql) via PDO; other connector types are rejected with a clear
 * message rather than half-working.
 */
class MlConnectorQuery
{
    const MAX_ROWS = 200000;

    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci = get_instance();
        $this->ci->load->model('DbSettings_model', 'ml_connectors');
    }

    public function listConnectors($environment = 'ALL')
    {
        return $this->ci->ml_connectors->listSettings($environment);
    }

    public function getConnector($id)
    {
        return $this->ci->ml_connectors->getSetting((int) $id, TRUE);
    }

    /**
     * @return array{ok:bool, message:string, path?:string, row_count?:int,
     *               columns?:array, format?:string}
     */
    public function run($connectorId, $sql, $maxRows = 100000)
    {
        $connector = $this->getConnector($connectorId);
        if (! $connector) {
            return array('ok' => FALSE, 'message' => 'Connector not found.');
        }
        $driver = strtolower((string) $connector->db_type);
        if (! in_array($driver, array('mysql', 'mariadb', 'pgsql', 'postgres', 'postgresql'), TRUE)) {
            return array('ok' => FALSE, 'message' => 'Dataset-from-connector currently supports MySQL/MariaDB and PostgreSQL connectors only.');
        }
        $sql = trim((string) $sql);
        if ($sql === '' || ! preg_match('/^\s*(select|with)\b/i', $sql) || preg_match('/;\s*\S/', $sql)) {
            return array('ok' => FALSE, 'message' => 'Provide a single read-only SELECT (or WITH ... SELECT) statement.');
        }
        if (preg_match('/\b(insert|update|delete|drop|alter|create|truncate|grant|revoke|call|do)\b/i', $sql)) {
            return array('ok' => FALSE, 'message' => 'Only read-only queries are allowed.');
        }

        $secret = $this->ci->ml_connectors->decryptLocalSecret(isset($connector->secret_encrypted) ? $connector->secret_encrypted : '');
        if ($secret === FALSE) {
            return array('ok' => FALSE, 'message' => 'The connector secret could not be decrypted. Re-save the connector.');
        }
        $username = isset($secret['username']) ? (string) $secret['username'] : '';
        $password = isset($secret['password']) ? (string) $secret['password'] : '';

        $isPg = in_array($driver, array('pgsql', 'postgres', 'postgresql'), TRUE);
        $pdoDriver = $isPg ? 'pgsql' : 'mysql';
        if (! extension_loaded('pdo_'.$pdoDriver)) {
            return array('ok' => FALSE, 'message' => strtoupper($pdoDriver).' PDO support is not installed on this server.');
        }
        $host = (string) $connector->address;
        $port = (int) $connector->port;
        $database = (string) $connector->schema;
        $dsn = $isPg
            ? 'pgsql:host='.$host.';port='.($port ?: 5432).';dbname='.$database
            : 'mysql:host='.$host.';port='.($port ?: 3306).';dbname='.$database.';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, $username, $password, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 8,
                PDO::ATTR_EMULATE_PREPARES => FALSE,
            ));
            $limit = max(1, min(self::MAX_ROWS, (int) $maxRows));
            $wrapped = 'SELECT * FROM ('.$sql.') AS jsml_sub LIMIT '.$limit;
            $statement = $pdo->query($wrapped);

            $path = tempnam(sys_get_temp_dir(), 'jsmlcq');
            $handle = fopen($path, 'wb');
            $columns = array();
            $rowCount = 0;
            while (($row = $statement->fetch()) !== FALSE) {
                if ($rowCount === 0) {
                    $columns = array_keys($row);
                    fputcsv($handle, $columns);
                }
                fputcsv($handle, array_map(function ($v) {
                    return $v === NULL ? '' : (is_scalar($v) ? $v : json_encode($v));
                }, $row));
                $rowCount++;
            }
            fclose($handle);
            if ($rowCount === 0) {
                @unlink($path);
                return array('ok' => FALSE, 'message' => 'The query returned no rows.');
            }
            return array('ok' => TRUE, 'message' => 'ok', 'path' => $path, 'row_count' => $rowCount,
                'columns' => $columns, 'format' => 'csv');
        } catch (Exception $e) {
            return array('ok' => FALSE, 'message' => 'Query failed: '.substr($e->getMessage(), 0, 300));
        }
    }
}
