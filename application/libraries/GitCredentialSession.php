<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_GIT_CREDENTIAL_SESSION_TEST')) exit('No direct script access allowed');

/**
 * Lets Git inside one OpenVSCode workspace use the personal Git account of the
 * JobSeeker user who opened it. The workspace holds only this server-signed
 * token; the account secret is handed to Git in memory for each operation by
 * the GitCredential endpoint. Each token is restricted to the repository that
 * was opened. The session has no arbitrary wall-clock limit, so a workspace
 * does not stop working in the middle of development. Deleting an account or
 * rotating JOBSEEKER_CONNECTOR_API_TOKEN revokes access.
 *
 * OpenVSCode is shared, so while a session is valid anyone using that editor
 * could use it too. It narrows exposure; it is not isolation between users.
 */
class GitCredentialSession
{
    const PREFIX = 'jsgit1';

    /** @return string|FALSE */
    public function issue($userId, $jobName, $host, $path)
    {
        $key = $this->key();
        $host = strtolower(trim((string) $host));
        $path = strtolower(trim(preg_replace('#\.git$#i', '', (string) $path), '/'));
        if ($key === FALSE || ! preg_match('/^[a-z0-9.-]+(:[0-9]{1,5})?$/', $host)
            || $path === '' || strlen($path) > 1000 || strpos($path, '..') !== FALSE) {
            return FALSE;
        }
        $payload = $this->encode(json_encode(array(
            'uid' => (int) $userId,
            'job' => (string) $jobName,
            'host' => $host,
            'path' => $path
        )));
        return self::PREFIX.'.'.$payload.'.'.$this->encode(hash_hmac('sha256', $payload, $key, TRUE));
    }

    /** @return array|FALSE the claims (uid, job, host, path) of a valid session */
    public function verify($token)
    {
        $parts = explode('.', (string) $token);
        $key = $this->key();
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX || $key === FALSE
            || ! hash_equals($this->encode(hash_hmac('sha256', $parts[1], $key, TRUE)), $parts[2])) {
            return FALSE;
        }
        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
        return is_array($claims) && isset($claims['uid'], $claims['job'], $claims['host'], $claims['path'])
            && (! isset($claims['exp']) || (int) $claims['exp'] > time())
            ? $claims : FALSE;
    }

    /** A shipped placeholder (see config.php) is public, so it cannot sign anything. */
    private function key()
    {
        $workerToken = trim((string) getenv('JOBSEEKER_CONNECTOR_API_TOKEN'));
        $placeholders = array('change-this-local-connector-token-before-shared-use', 'jobseeker-local-connector-token');
        if (strlen($workerToken) < 24 || in_array($workerToken, $placeholders, TRUE)) {
            return FALSE;
        }
        return hash_hmac('sha256', 'git-credential-session', $workerToken, TRUE);
    }

    private function encode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
