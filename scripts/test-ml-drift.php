<?php
/**
 * Unit test for MlDriftAnalyzer (PSI / KL / summary drift).
 * Run:  php scripts/test-ml-drift.php
 */
define('BASEPATH', __DIR__);
require __DIR__.'/../application/libraries/MlDriftAnalyzer.php';

$a = new MlDriftAnalyzer();
$failures = 0;
function check($label, $cond) {
    global $failures;
    echo ($cond ? "  ok   " : "  FAIL ").$label."\n";
    if (! $cond) { $failures++; }
}

// Identical distributions -> ~0 PSI/KL.
$p = array(0.25, 0.25, 0.25, 0.25);
check('PSI(identical) ~ 0', abs($a->psi($p, $p)) < 1e-6);
check('KL(identical) ~ 0', abs($a->kl($p, $p)) < 1e-6);

// A clear shift -> PSI grows, and more shift -> more PSI.
$q1 = array(0.4, 0.3, 0.2, 0.1);
$q2 = array(0.7, 0.2, 0.07, 0.03);
check('PSI increases with shift', $a->psi($p, $q1) < $a->psi($p, $q2));
check('KL is non-negative', $a->kl($q2, $p) >= 0);

// Feature-level comparison over fingerprints.
$baseline = array('columns' => array(
    'amount' => array('type' => 'numeric', 'count' => 1000, 'missing' => 0, 'mean' => 50.0, 'std' => 10.0,
        'min' => 0, 'max' => 100, 'histogram' => array('edges' => range(0, 100, 10), 'counts' => array(50, 120, 200, 260, 200, 100, 40, 20, 8, 2))),
    'segment' => array('type' => 'categorical', 'count' => 1000, 'missing' => 0, 'top' => array('a' => 500, 'b' => 300, 'c' => 200)),
));
$stable = $baseline;
$res = $a->compare($baseline, $stable);
check('no drift when current == baseline', $res['overall']['features_drifted'] === 0);
check('overall status ok', $res['overall']['status'] === 'ok');

$drifted = array('columns' => array(
    'amount' => array('type' => 'numeric', 'count' => 1000, 'missing' => 120, 'mean' => 80.0, 'std' => 12.0,
        'min' => 0, 'max' => 100, 'histogram' => array('edges' => range(0, 100, 10), 'counts' => array(2, 5, 10, 20, 60, 120, 220, 260, 180, 123))),
    'segment' => array('type' => 'categorical', 'count' => 1000, 'missing' => 0, 'top' => array('a' => 100, 'b' => 200, 'c' => 700)),
));
$res = $a->compare($baseline, $drifted);
check('detects amount drift', isset($res['features']['amount']) && $res['features']['amount']['status'] === 'drifted');
check('detects segment drift', $res['features']['segment']['status'] === 'drifted');
check('overall status not ok', $res['overall']['status'] !== 'ok');
check('missing_delta surfaced for amount', $res['features']['amount']['metrics']['missing_delta'] > 0.1);

echo $failures === 0 ? "\nALL PASSED\n" : "\n{$failures} FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
