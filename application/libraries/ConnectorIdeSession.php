<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_CONNECTOR_IDE_SESSION_TEST')) exit('No direct script access allowed');

/**
 * Connector access for jobs run inside OpenVSCode.
 *
 * The worker token (JOBSEEKER_CONNECTOR_API_TOKEN) can fetch any job's secrets
 * in any environment, so it never reaches the editor. Opening a workspace from
 * JobSeeker instead issues a token signed with a key derived from it, valid for
 * one job, in the one environment set by JOBSEEKER_OPENVSCODE_CONNECTOR_ENVIRONMENT
 * (DEV unless changed, empty disables). The connector API checks all three on
 * every request. Sessions deliberately have no wall-clock expiry: reopening an
 * editor must not interrupt a long-running development workspace. Rotating the
 * worker token still revokes every issued session immediately.
 */
class ConnectorIdeSession
{
    const PREFIX = 'jsdev1';

    /** @return string|FALSE the environment IDE runs may read, FALSE when disabled */
    public function environment()
    {
        $environment = getenv('JOBSEEKER_OPENVSCODE_CONNECTOR_ENVIRONMENT');
        $environment = strtoupper(trim($environment === FALSE ? 'DEV' : (string) $environment));
        return $environment !== '' && $this->key() !== FALSE ? $environment : FALSE;
    }

    /** @return string|FALSE */
    public function issue($jobName)
    {
        $environment = $this->environment();
        if ($environment === FALSE) {
            return FALSE;
        }
        $payload = $this->encode(json_encode(array(
            'env' => $environment,
            'job' => (string) $jobName
        )));
        return self::PREFIX.'.'.$payload.'.'.$this->encode(hash_hmac('sha256', $payload, $this->key(), TRUE));
    }

    /** True only for a signed session issued for this environment and job. */
    public function verify($token, $environment, $jobName)
    {
        $parts = explode('.', (string) $token);
        $allowedEnvironment = $this->environment();
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX || $allowedEnvironment === FALSE) {
            return FALSE;
        }
        $signature = $this->encode(hash_hmac('sha256', $parts[1], $this->key(), TRUE));
        if (! hash_equals($signature, $parts[2])) {
            return FALSE;
        }
        $claims = json_decode($this->decode($parts[1]), TRUE);
        return is_array($claims)
            && isset($claims['env'], $claims['job'])
            // Tokens issued by an older release can keep working until their
            // original expiry. New tokens intentionally omit this claim.
            && (! isset($claims['exp']) || (int) $claims['exp'] > time())
            && $claims['env'] === $allowedEnvironment
            && $claims['env'] === $environment
            && hash_equals((string) $claims['job'], (string) $jobName);
    }

    public function isSessionToken($token)
    {
        return strncmp((string) $token, self::PREFIX.'.', strlen(self::PREFIX) + 1) === 0;
    }

    /** A shipped placeholder (see config.php) is public, so it cannot sign anything. */
    private function key()
    {
        $workerToken = trim((string) getenv('JOBSEEKER_CONNECTOR_API_TOKEN'));
        $placeholders = array('change-this-local-connector-token-before-shared-use', 'jobseeker-local-connector-token');
        if (strlen($workerToken) < 24 || in_array($workerToken, $placeholders, TRUE)) {
            return FALSE;
        }
        return hash_hmac('sha256', 'openvscode-connector-session', $workerToken, TRUE);
    }

    private function encode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode($value)
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
