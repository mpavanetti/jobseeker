<?php
define('BASEPATH', dirname(__DIR__).'/system/');
require dirname(__DIR__).'/application/libraries/DependencyScanner.php';

$scanner = new DependencyScanner();
$scan = $scanner->scan(array(array(
    'from' => 'code',
    'text' => <<<'PYTHON'
CONNECTOR_KEY = os.getenv("JOBSEEKER_DB_CONNECTOR", "jobseeker-mariadb")
ASSET_KEY: str = os.environ.get("JOBSEEKER_DATA_ASSET", "orders-current")
ignored = os.getenv("NOT_A_DEPENDENCY", "do-not-collect")
tmf.connector(CONNECTOR_KEY, required=False)
tmf.asset(ASSET_KEY, required=False)
PYTHON
)));

if (array_keys($scan['connectors']) !== array('jobseeker-mariadb')) {
    fwrite(STDERR, "FAIL: configurable connector default was not resolved safely.\n");
    exit(1);
}
if (array_keys($scan['datasets']) !== array('orders-current')) {
    fwrite(STDERR, "FAIL: configurable dataset default was not resolved safely.\n");
    exit(1);
}
if (isset($scan['connectors']['do-not-collect']) || isset($scan['datasets']['do-not-collect'])) {
    fwrite(STDERR, "FAIL: an unrelated environment default was collected.\n");
    exit(1);
}

$shell = $scanner->scan(array(array(
    'from' => 'command',
    'text' => <<<'SHELL'
connector_key="${JOBSEEKER_DB_CONNECTOR:-jobseeker-mariadb}"
asset_key=orders-current
dynamic="$(pick-one)"
spaced="${X:-two words}"
jobseeker-connector test "$connector_key" --json
jobseeker-connector exec "${connector_key}" -- true
asset_path="$(jobseeker-asset "$asset_key")"
jobseeker-connector get "$dynamic"
jobseeker-asset "$spaced"
SHELL
)));
if (array_keys($shell['connectors']) !== array('jobseeker-mariadb')) {
    fwrite(STDERR, 'FAIL: shell ${VAR:-default} connector was not resolved safely: '.json_encode($shell)."\n");
    exit(1);
}
if (array_keys($shell['datasets']) !== array('orders-current')) {
    fwrite(STDERR, "FAIL: shell asset variable was not resolved safely: ".json_encode($shell)."\n");
    exit(1);
}

echo "DependencyScanner variable defaults: 5 checks passed.\n";
