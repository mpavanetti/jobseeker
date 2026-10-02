<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Captures one audit event for every authenticated application request. The
 * shutdown callback also runs for redirect()/exit, which is how most legacy
 * write actions finish, while preserving the actor before logout destroys the
 * session. Machine-token endpoints have no user actor and are not recorded.
 */
class AuditLogHook
{
    private static $registered = FALSE;
    private $initialActor = NULL;
    private $request = array();

    public function capture()
    {
        if (self::$registered || PHP_SAPI === 'cli') {
            return;
        }
        self::$registered = TRUE;

        $CI =& get_instance();
        if (! $CI || ! isset($CI->session) || ! isset($CI->router)) {
            return;
        }

        $this->initialActor = $this->actorFromSession($CI->session);
        $method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
        $controller = (string) $CI->router->fetch_class();
        $controllerMethod = (string) $CI->router->fetch_method();
        $action = $controller.'.'.$controllerMethod;

        $this->request = array(
            'started_at' => isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime(TRUE),
            'method' => $method,
            'operation' => $this->operationType($method, $action),
            'action' => $action,
            // Query values are stored in request_data after redaction. Keeping
            // them out of this field prevents OAuth codes or reset tokens from
            // being copied verbatim into an otherwise safe path column.
            'uri' => (string) $CI->uri->uri_string(),
            'data' => $this->requestData($CI),
            'ip_address' => (string) $CI->input->ip_address(),
            'user_agent' => (string) $CI->input->user_agent()
        );

        register_shutdown_function(array($this, 'persist'));
    }

    public function persist()
    {
        $CI =& get_instance();
        if (! $CI || empty($this->request)) {
            return;
        }

        $actor = $this->initialActor;
        if ($actor === NULL && isset($CI->session)) {
            // A successful Login.loginMe request gains an actor during the
            // controller action, after this hook was registered.
            $actor = $this->actorFromSession($CI->session);
        }
        if ($actor === NULL) {
            return;
        }

        $status = http_response_code();
        $status = is_int($status) && $status >= 100 ? $status : 200;
        $lastError = error_get_last();
        if ($lastError && in_array($lastError['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), TRUE)) {
            $status = 500;
        }

        try {
            $CI->load->model('AuditLog_model', 'auditLogWriter');
            $recorded = $CI->auditLogWriter->record(array(
                'actor_user_id' => $actor['user_id'],
                'actor_name' => $actor['name'],
                'actor_role' => $actor['role'],
                'operation' => $this->request['operation'],
                'action' => $this->request['action'],
                'http_method' => $this->request['method'],
                'request_uri' => $this->request['uri'],
                'request_data' => $this->request['data'],
                'ip_address' => $this->request['ip_address'],
                'user_agent' => $this->request['user_agent'],
                'status_code' => $status,
                'outcome' => $status >= 400 ? 'failure' : 'success',
                'duration_ms' => (int) round((microtime(TRUE) - $this->request['started_at']) * 1000),
                'created_at' => date('Y-m-d H:i:s')
            ));
            if (! $recorded) {
                log_message('error', 'Unable to persist audit log entry.');
            }
        } catch (Throwable $error) {
            // Auditing must never replace the user's real response with an
            // audit-storage error. Operators can opt into CI error logs.
            log_message('error', 'Unable to persist audit log entry: '.$error->getMessage());
        }
    }

    private function actorFromSession($session)
    {
        if ($session->userdata('isLoggedIn') !== TRUE || (int) $session->userdata('userId') < 1) {
            return NULL;
        }

        return array(
            'user_id' => (int) $session->userdata('userId'),
            'name' => (string) $session->userdata('name'),
            'role' => (string) $session->userdata('roleText')
        );
    }

    private function operationType($method, $action)
    {
        if (strcasecmp($action, 'Login.loginMe') === 0 || strcasecmp($action, 'User.logout') === 0) {
            return 'authentication';
        }

        return in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), TRUE) ? 'write' : 'read';
    }

    private function requestData($CI)
    {
        $data = array();
        if (! empty($_GET)) {
            $data['query'] = $this->sanitize($_GET);
        }
        if (! empty($_POST)) {
            $data['body'] = $this->sanitize($_POST);
        } elseif ($this->isJsonRequest($CI) && (int) $CI->input->server('CONTENT_LENGTH') <= 65536) {
            $decoded = json_decode((string) $CI->input->raw_input_stream, TRUE);
            if (is_array($decoded)) {
                $data['body'] = $this->sanitize($decoded);
            }
        }
        if (! empty($_FILES)) {
            $files = array();
            foreach ($_FILES as $field => $file) {
                $files[$field] = $this->fileMetadata($file);
            }
            $data['files'] = $files;
        }

        if (empty($data)) {
            return NULL;
        }

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (! is_string($json)) {
            return NULL;
        }
        if (strlen($json) > 16384) {
            $json = substr($json, 0, 16320).'...[truncated]';
        }
        return $json;
    }

    private function isJsonRequest($CI)
    {
        $contentType = strtolower((string) $CI->input->get_request_header('Content-Type', TRUE));
        return strpos($contentType, 'application/json') !== FALSE;
    }

    private function sanitize($value, $key = '', $depth = 0)
    {
        if ($key !== '' && preg_match('/password|passwd|cpassword|secret|token|authorization|cookie|csrf|private.?key|known.?hosts|activation.?code|oauth.?code|context.?value|^(code|state)$/i', $key)) {
            return '[REDACTED]';
        }
        if ($depth >= 6) {
            return '[DEPTH LIMIT]';
        }
        if (is_array($value)) {
            $clean = array();
            $count = 0;
            foreach ($value as $childKey => $childValue) {
                if ($count++ >= 100) {
                    $clean['_truncated'] = TRUE;
                    break;
                }
                $clean[$childKey] = $this->sanitize($childValue, (string) $childKey, $depth + 1);
            }
            return $clean;
        }
        if (is_string($value)) {
            return strlen($value) > 1000 ? substr($value, 0, 1000).'...[truncated]' : $value;
        }
        if (is_scalar($value) || $value === NULL) {
            return $value;
        }
        return '[UNSUPPORTED VALUE]';
    }

    private function fileMetadata($file)
    {
        if (! is_array($file)) {
            return array('name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE);
        }

        return array(
            'name' => $this->sanitize(isset($file['name']) ? $file['name'] : ''),
            'size' => $this->sanitize(isset($file['size']) ? $file['size'] : 0),
            'error' => $this->sanitize(isset($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE)
        );
    }
}
