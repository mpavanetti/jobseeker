<?php
define('JOBSEEKER_GIT_BRANCH_POLICY_TEST', TRUE);
define('JOBSEEKER_GIT_CREDENTIAL_SESSION_TEST', TRUE);
require dirname(__DIR__).'/application/libraries/GitBranchPolicy.php';
require dirname(__DIR__).'/application/libraries/GitCredentialSession.php';

$checks = 0;
function git_workspace_assert($condition, $message) {
    global $checks;
    if (! $condition) {
        fwrite(STDERR, 'FAIL: '.$message."\n");
        exit(1);
    }
    $checks++;
}

putenv('JOBSEEKER_GIT_ENVIRONMENT_BRANCHES=DEV=develop,QA=release/qa,DEFAULT=main');
$policy = new GitBranchPolicy();
git_workspace_assert($policy->forEnvironment('DEV') === 'develop', 'DEV defaults to develop');
git_workspace_assert($policy->forEnvironment('QA') === 'release/qa', 'an environment can predefine its own branch');
git_workspace_assert($policy->forEnvironment('PROD') === 'main', 'other environments use the default branch');
git_workspace_assert($policy->cleanBranch('release/2026.09') === 'release/2026.09', 'release branches are valid');
git_workspace_assert($policy->cleanBranch('../unsafe') === FALSE, 'unsafe refs are rejected');

putenv('JOBSEEKER_CONNECTOR_API_TOKEN=worker-secret-0123456789abcdef');
$sessions = new GitCredentialSession();
$token = $sessions->issue(42, 'load-orders', 'github.com', 'acme/load-orders.git');
$claims = $sessions->verify($token);
git_workspace_assert(is_array($claims) && $claims['uid'] === 42 && $claims['job'] === 'load-orders'
    && $claims['host'] === 'github.com' && $claims['path'] === 'acme/load-orders', 'a repository-scoped Git workspace session verifies');
$parts = explode('.', $token);
$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
git_workspace_assert(! isset($payload['exp']), 'Git workspace sessions have no arbitrary time limit');
putenv('JOBSEEKER_CONNECTOR_API_TOKEN=rotated-secret-0123456789abcdef');
git_workspace_assert($sessions->verify($token) === FALSE, 'rotating the platform token revokes Git workspace sessions');

echo "Git workspace policy/session: {$checks} checks passed.\n";
