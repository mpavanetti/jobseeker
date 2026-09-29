<?php
/**
 * Behavioural tests for ConnectorIdeSession: the job- and environment-scoped,
 * signed tokens that let OpenVSCode runs fetch connectors without the worker
 * token.
 */
define('JOBSEEKER_CONNECTOR_IDE_SESSION_TEST', TRUE);
require dirname(__DIR__).'/application/libraries/ConnectorIdeSession.php';

$checks = 0;

function session_assert($condition, $message)
{
    global $checks;
    if (! $condition) {
        fwrite(STDERR, 'FAIL: '.$message."\n");
        exit(1);
    }
    $checks++;
}

function b64url($value)
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

putenv('JOBSEEKER_CONNECTOR_API_TOKEN=worker-secret-0123456789abcdef');
putenv('JOBSEEKER_OPENVSCODE_CONNECTOR_ENVIRONMENT');
$session = new ConnectorIdeSession();

session_assert($session->environment() === 'DEV', 'the environment defaults to DEV');
$token = $session->issue('load-orders');
session_assert($session->isSessionToken($token), 'issued tokens are recognisable');
session_assert(strpos($token, 'worker-secret-0123456789abcdef') === FALSE, 'the worker token is not embedded');
session_assert($session->verify($token, 'DEV', 'load-orders'), 'a fresh token verifies for its job and environment');
session_assert(! $session->verify($token, 'DEV', 'other-job'), 'another job is refused');
session_assert(! $session->verify($token, 'PROD', 'load-orders'), 'another environment is refused');

$parts = explode('.', $token);
$claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
session_assert(! isset($claims['exp']), 'new workspace tokens have no arbitrary time limit');
$claims['job'] = 'other-job';
$tampered = $parts[0].'.'.b64url(json_encode($claims)).'.'.$parts[2];
session_assert(! $session->verify($tampered, 'DEV', 'other-job'), 'edited claims are refused');

$key = hash_hmac('sha256', 'openvscode-connector-session', 'worker-secret-0123456789abcdef', TRUE);
$expiredPayload = b64url(json_encode(array('env' => 'DEV', 'job' => 'load-orders', 'exp' => time() - 1)));
$expired = 'jsdev1.'.$expiredPayload.'.'.b64url(hash_hmac('sha256', $expiredPayload, $key, TRUE));
session_assert(! $session->verify($expired, 'DEV', 'load-orders'), 'an expired token from an older release is refused');

putenv('JOBSEEKER_CONNECTOR_API_TOKEN=rotated-secret-0123456789abcdef');
session_assert(! $session->verify($token, 'DEV', 'load-orders'), 'rotating the worker token revokes sessions');

putenv('JOBSEEKER_OPENVSCODE_CONNECTOR_ENVIRONMENT=QA');
session_assert(! $session->verify($session->issue('load-orders'), 'DEV', 'load-orders'), 'only the configured environment is served');

putenv('JOBSEEKER_OPENVSCODE_CONNECTOR_ENVIRONMENT=');
session_assert($session->issue('load-orders') === FALSE, 'an empty environment disables sessions');

putenv('JOBSEEKER_OPENVSCODE_CONNECTOR_ENVIRONMENT=DEV');
putenv('JOBSEEKER_CONNECTOR_API_TOKEN=change-this-local-connector-token-before-shared-use');
session_assert($session->issue('load-orders') === FALSE, 'the public placeholder token cannot sign sessions');

echo "ConnectorIdeSession: {$checks} checks passed.\n";
