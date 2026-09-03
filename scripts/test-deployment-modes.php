<?php

define('BASEPATH', __DIR__);

class CI_Controller {}

function log_message($level, $message) {}
function show_error($message, $status = 500) {
    throw new RuntimeException($status.': '.$message);
}

require dirname(__DIR__).'/application/libraries/BaseController.php';

class DeploymentModeProbe extends BaseController
{
    public function mode() { return $this->jobSeekerDeploymentMode(); }
    public function environment() { return $this->jobSeekerStandaloneEnvironment(); }
    public function effective($value = '', $all = 'ALL') { return $this->jobSeekerEffectiveEnvironment($value, $all); }
    public function allowed($value, $allowAll = FALSE) { return $this->jobSeekerEnvironmentIsAllowed($value, $allowAll); }
}

function verify($condition, $message) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$probe = new DeploymentModeProbe();

putenv('JOBSEEKER_DEPLOYMENT_MODE');
putenv('JOBSEEKER_STANDALONE_ENVIRONMENT');
verify($probe->mode() === 'multi', 'Multi mode must remain the default.');
verify($probe->effective('qa') === 'QA', 'Multi mode must preserve concrete environment selection.');
verify($probe->effective('') === 'ALL', 'Multi mode must preserve the all-environment default.');

putenv('JOBSEEKER_DEPLOYMENT_MODE=standalone');
putenv('JOBSEEKER_STANDALONE_ENVIRONMENT=production');
verify($probe->mode() === 'standalone', 'Standalone mode was not selected.');
verify($probe->environment() === 'PROD', 'Standalone environment aliases must be normalized.');
verify($probe->effective('DEV') === 'PROD', 'A request must not broaden the standalone scope.');
verify($probe->allowed('PROD'), 'The standalone environment must be allowed.');
verify(! $probe->allowed('DEV'), 'Another concrete environment must be rejected.');
verify($probe->allowed('ALL', TRUE), 'Explicit wildcard fallbacks should be supported only when requested.');

putenv('JOBSEEKER_STANDALONE_ENVIRONMENT=ALL');
try {
    $probe->environment();
    throw new RuntimeException('An invalid standalone environment was accepted.');
} catch (RuntimeException $exception) {
    verify(strpos($exception->getMessage(), 'requires JOBSEEKER_STANDALONE_ENVIRONMENT') !== FALSE, 'Unexpected invalid-configuration error.');
}

echo "Deployment mode behavior verified.\n";
