<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_ENVIRONMENT_CONTEXT_TEST')) exit('No direct script access allowed');

/**
 * Context values supplied by the deployment rather than by an operator.
 *
 * A value in `.env` named `JOBSEEKER_CONTEXT_<KEY>` shows up on the Context
 * page and resolves in jobs exactly like a stored Context value, but it cannot
 * be edited or deleted from the UI: it belongs to whoever owns the deployment,
 * and the only way to change it is to change the environment and restart.
 *
 * Only that one prefix is read. The process environment also carries
 * `JOBSEEKER_DB_PASSWORD`, connector credentials and Jenkins tokens, and none
 * of those may ever reach a page or a job command because someone added a
 * broader rule here. A deployment secret belongs in a Connector, which is
 * materialised into a job's runtime without being displayed.
 */
class EnvironmentContext
{
    /** The one prefix that is exposed. Everything else in the environment stays private. */
    const PREFIX = 'JOBSEEKER_CONTEXT_';

    /** Environment-supplied values are not scoped to one runtime environment. */
    const SCOPE = 'ALL';

    const MAX_VALUES = 200;
    const MAX_VALUE_LENGTH = 1000;

    /** A key must look like a context key once the prefix is removed. */
    const KEY_PATTERN = '/^[a-z][a-z0-9_.-]{0,127}$/';

    /** Credentials belong in the Connector catalog, never in visible Context. */
    const SENSITIVE_KEY_PATTERN = '/(?:^|[._-])(?:user(?:name)?|password|passwd|pwd|secret|token|api[._-]?key|private[._-]?key|credential)(?:$|[._-])/i';

    private $values = NULL;

    /**
     * @return array key => value, read once per request
     */
    public function values()
    {
        if ($this->values !== NULL) {
            return $this->values;
        }

        $this->values = array();
        foreach ($this->candidates() as $name => $rawValue) {
            if (strpos($name, self::PREFIX) !== 0) {
                continue;
            }

            $key = strtolower(substr($name, strlen(self::PREFIX)));
            if ($key === '' || ! preg_match(self::KEY_PATTERN, $key) || $this->isSensitiveKey($key)) {
                continue;
            }

            $value = (string) $rawValue;
            if (strlen($value) > self::MAX_VALUE_LENGTH) {
                $value = substr($value, 0, self::MAX_VALUE_LENGTH);
            }
            // A context value is a single line of configuration. Control
            // characters - newlines included - would break the table cell and
            // the shell export, so such a value is refused rather than mangled.
            if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
                continue;
            }

            $this->values[$key] = $value;
            if (count($this->values) >= self::MAX_VALUES) {
                break;
            }
        }

        ksort($this->values);
        return $this->values;
    }

    public function isSensitiveKey($key)
    {
        return preg_match(self::SENSITIVE_KEY_PATTERN, strtolower(trim((string) $key))) === 1;
    }

    /** The environment variable name a context key is read from. */
    public function variableName($key)
    {
        return self::PREFIX.strtoupper(str_replace(array('.', '-'), '_', (string) $key));
    }

    public function has($key)
    {
        $values = $this->values();
        return isset($values[strtolower(trim((string) $key))]);
    }

    public function value($key, $default = NULL)
    {
        $values = $this->values();
        $key = strtolower(trim((string) $key));
        return isset($values[$key]) ? $values[$key] : $default;
    }

    /**
     * Context-page rows for the environment-supplied values.
     *
     * Shaped like a `contextdetails` row so the page renders them with the
     * stored ones, and flagged `readOnly` so it leaves the actions off.
     *
     * @param array $storedKeys keys already defined in the database, which win
     */
    public function rows(array $storedKeys = array())
    {
        $shadowed = array();
        foreach ($storedKeys as $storedKey) {
            $shadowed[strtolower(trim((string) $storedKey))] = TRUE;
        }

        $rows = array();
        foreach ($this->values() as $key => $value) {
            $row = new stdClass();
            $row->Id = 0;
            $row->ContextKey = $key;
            $row->ContextValue = $value;
            $row->isEncrypted = 0;
            $row->Environment = self::SCOPE;
            $row->ProjectName = 'Deployment';
            $row->Description = 'Supplied by '.$this->variableName($key).' in the deployment environment.';
            $row->IsActive = 1;
            $row->CreatedOn = NULL;
            $row->ModifiedOn = NULL;
            $row->CreatedBy = 'Environment';
            $row->ModifiedBy = NULL;
            $row->readOnly = TRUE;
            $row->source = 'environment';
            $row->variableName = $this->variableName($key);
            // A stored value for the same key is what a job resolves, so the
            // page has to say that this one is not the effective value.
            $row->shadowed = isset($shadowed[$key]);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Shell lines that put these values into a job's runtime, so a job can read
     * `JOBSEEKER_CONTEXT_*` directly as well as through the SDK resolver.
     */
    public function exportLines()
    {
        $lines = array();
        foreach ($this->values() as $key => $value) {
            $lines[] = 'export '.$this->variableName($key).'='.escapeshellarg($value);
        }
        return $lines;
    }

    /** The variable names a job's container needs forwarded to it. */
    public function variableNames()
    {
        $names = array();
        foreach (array_keys($this->values()) as $key) {
            $names[] = $this->variableName($key);
        }
        return $names;
    }

    /**
     * Overridable for tests; `getenv()` with no argument returns the whole
     * environment, which is what a deployment actually hands the app.
     */
    protected function candidates()
    {
        $environment = getenv();
        return is_array($environment) ? $environment : array();
    }
}
