<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Data Asset source coordinates and the Data Preview service client.
 *
 * Asset rows hold only non-secret coordinates and a connector key. Reading a
 * source happens in Python (jobseeker.sources): jobs materialize it, and the
 * Data Preview service returns a bounded sample for the UI. This library
 * validates coordinates when an asset is saved and forwards preview requests.
 */
class DataAssetSource
{
    /** Return NULL when a URL is safe, or a user-facing validation error. */
    public function validatePublicUrl($url)
    {
        $credentialError = $this->validateNoEmbeddedCredentials($url);
        if ($credentialError !== NULL) return $credentialError;
        $parts = @parse_url(trim((string) $url));
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), array('http', 'https'), TRUE)) {
            return 'Use an absolute HTTP or HTTPS URL.';
        }
        if (! $this->hostIsPublic($parts['host'])) {
            return 'Public URL assets cannot target private, loopback, link-local, or unresolved addresses. Use a managed Connection for an internal API.';
        }
        return NULL;
    }

    /** URLs are manifest metadata, so credentials must always be connector-owned. */
    public function validateNoEmbeddedCredentials($url)
    {
        $parts = @parse_url(trim((string) $url));
        if (is_array($parts) && (isset($parts['user']) || isset($parts['pass']))) {
            return 'Put credentials in a Connection, not in the source URL.';
        }
        $sensitive = array('token', 'access_token', 'api_key', 'apikey', 'key', 'password', 'passwd', 'secret', 'signature', 'sig', 'credential', 'authorization', 'auth');
        foreach (explode('&', isset($parts['query']) ? (string) $parts['query'] : '') as $pair) {
            $name = strtolower(rawurldecode((string) strtok($pair, '=')));
            if (in_array($name, $sensitive, TRUE)) {
                return 'Put API keys, tokens, signatures, and other credentials in a Connection instead of the source URL.';
            }
        }
        return NULL;
    }

    /**
     * POST a preview request to the Data Preview service. Returns its JSON
     * answer, or NULL when the service is not configured or not reachable.
     */
    public function requestPreview(array $request)
    {
        $url = rtrim(trim((string) getenv('JOBSEEKER_DATA_PREVIEW_URL')), '/');
        $token = trim((string) (getenv('JOBSEEKER_DATA_PREVIEW_TOKEN') ?: getenv('JOBSEEKER_CONNECTOR_API_TOKEN')));
        if ($url === '' || $token === '' || ! function_exists('curl_init')) {
            return NULL;
        }
        $handle = curl_init($url.'/preview');
        curl_setopt_array($handle, array(
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer '.$token),
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_FOLLOWLOCATION => FALSE,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 90
        ));
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $errno = curl_errno($handle);
        curl_close($handle);
        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            return array('ok' => FALSE, 'status' => 504, 'message' => 'The source did not answer within 90 seconds.');
        }
        if ($body === FALSE || $status === 0) {
            return NULL;
        }
        $decoded = json_decode((string) $body, TRUE);
        if ($status !== 200 || ! is_array($decoded)) {
            log_message('error', 'Data Preview service answered HTTP '.$status.'.');
            return array('ok' => FALSE, 'status' => 502, 'message' => 'The Data Preview service answered HTTP '.$status.'.');
        }
        return $decoded;
    }

    private function hostIsPublic($host)
    {
        $host = trim((string) $host, '[]');
        if ($host === '' || strtolower($host) === 'localhost') {
            return FALSE;
        }
        $addresses = $this->hostAddresses($host);
        if (empty($addresses)) return FALSE;
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return FALSE;
        }
        return TRUE;
    }

    private function hostAddresses($host)
    {
        $addresses = array();
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            foreach (is_array($records) ? $records : array() as $record) {
                if (! empty($record['ip'])) $addresses[] = $record['ip'];
                if (! empty($record['ipv6'])) $addresses[] = $record['ipv6'];
            }
        } else {
            $resolved = @gethostbyname($host);
            if ($resolved !== $host) $addresses[] = $resolved;
        }
        return array_values(array_unique($addresses));
    }
}
