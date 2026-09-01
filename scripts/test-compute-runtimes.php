<?php
// Unit checks for the compute libraries that do not need CodeIgniter: the
// Engine API client request guard and the driver's input sanitisers, plus
// consistency between the runtime Dockerfiles and the seeded catalogue.

define('BASEPATH', dirname(__DIR__).'/system/');
define('APPPATH', dirname(__DIR__).'/application/');

require APPPATH.'libraries/ComputeEngineClient.php';
require APPPATH.'libraries/ComputeDriver.php';

$failures = 0;
function check($label, $condition)
{
    global $failures;
    echo ($condition ? "  ok   - " : "  FAIL - ").$label."\n";
    if (! $condition) {
        $failures++;
    }
}

/**
 * Exposes the protected sanitisers so they can be exercised directly.
 * A stub engine keeps the constructor from touching the network.
 */
class StubEngine extends ComputeEngineClient
{
    public function __construct() {}
    public function ping() { return TRUE; }
}
class DriverProbe extends DockerComputeDriver
{
    public function __construct() { parent::__construct(array('engine' => new StubEngine())); }
    public function pEntryPoint($v) { return $this->assertEntryPoint($v); }
    public function pImage($v) { return $this->assertImage($v); }
    public function pEnv($v) { return $this->envList($v); }
    public function pConf($v) { return $this->sparkConfFlags($v); }
    public function pArgs($v) { return $this->argList($v); }
    public function pLabels($spec) { return $this->baseLabels('spark', $spec); }
}

$probe = new DriverProbe();

echo "Engine client request guard:\n";
$engine = new ComputeEngineClient(array('base_url' => 'http://docker-runtime:2375'));
$bad = $engine->request('PUT', '/containers/create');
check('rejects verbs other than GET/POST/DELETE', $bad['status'] === 400);
$badPath = $engine->request('GET', '/containers/../../etc');
check('rejects path traversal', $badPath['status'] === 400);
check('base url is normalised without a trailing slash', $engine->baseUrl() === 'http://docker-runtime:2375');

echo "Entry-point validation:\n";
check('accepts jobs/pi/main.py', $probe->pEntryPoint('jobs/pi/main.py') === 'jobs/pi/main.py');
check('accepts inline/foo/main.py', $probe->pEntryPoint('inline/foo/main.py') === 'inline/foo/main.py');
$threw = FALSE;
try { $probe->pEntryPoint('../etc/passwd.py'); } catch (RuntimeException $e) { $threw = TRUE; }
check('rejects ../ traversal', $threw);
$threw = FALSE;
try { $probe->pEntryPoint('jobs/pi/main.sh'); } catch (RuntimeException $e) { $threw = TRUE; }
check('rejects non-.py entry points', $threw);

echo "Image reference validation:\n";
check('accepts jobseeker/spark-runtime:4.0.0-python', $probe->pImage('jobseeker/spark-runtime:4.0.0-python') === 'jobseeker/spark-runtime:4.0.0-python');
$threw = FALSE;
try { $probe->pImage('bad image; rm -rf /'); } catch (RuntimeException $e) { $threw = TRUE; }
check('rejects images with shell metacharacters', $threw);

echo "Argument + env + conf sanitisers:\n";
check('env drops invalid keys', $probe->pEnv(array('OK' => '1', 'bad-key' => 'x', '9nope' => 'y')) === array('OK=1'));
check('env strips newlines from values', $probe->pEnv(array('A' => "one\ntwo")) === array('A=onetwo'));
check('spark conf becomes --conf pairs', $probe->pConf(array('spark.executor.cores' => '2')) === array('--conf', 'spark.executor.cores=2'));
check('spark conf drops invalid keys', $probe->pConf(array('bad key!' => '1')) === array());
check('args strip control characters', $probe->pArgs(array("a\nb", '', 'c')) === array('ab', 'c'));

echo "Run labels:\n";
$labels = $probe->pLabels(array('run_key' => 'abc123def456', 'job_name' => 'demo', 'environment' => 'DEV'));
check('labels carry the run key', ($labels['com.jobseeker.compute.run'] ?? '') === 'abc123def456');
check('labels mark the workload as managed compute', ($labels['com.jobseeker.kind'] ?? '') === 'compute' && ($labels['com.jobseeker.managed'] ?? '') === 'true');

echo "Dockerfile / catalogue consistency:\n";
$root = dirname(__DIR__);
$dbSetup = file_get_contents($root.'/db_setup.sql');
$sparkPy = file_get_contents($root.'/docker/spark/Dockerfile.spark-4.0-python');
$mlCpu = file_get_contents($root.'/docker/ml/Dockerfile.ml-cpu');
check('Spark python Dockerfile targets Spark 4.x', (bool) preg_match('/FROM apache\/spark:4\.\d/', $sparkPy));
check('ML CPU Dockerfile is Miniconda based', strpos($mlCpu, 'FROM continuumio/miniconda3') !== FALSE);
check('catalogue seeds the built Spark tag', strpos($dbSetup, "'jobseeker/spark-runtime'") !== FALSE && strpos($dbSetup, "'4.0.0-python'") !== FALSE);
check('catalogue seeds the built ML tags', strpos($dbSetup, "'jobseeker/ml-runtime'") !== FALSE && strpos($dbSetup, "'dl-cpu'") !== FALSE);

echo "\n";
if ($failures > 0) {
    echo $failures." check(s) FAILED\n";
    exit(1);
}
echo "All compute runtime checks passed.\n";
