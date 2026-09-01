<?php
define('JOBSEEKER_CRON_SCHEDULE_TEST', TRUE);
require dirname(__DIR__) . '/application/libraries/JenkinsCronSchedule.php';

$failures = 0;

function cron_check($label, $condition)
{
    global $failures;
    if ($condition) {
        echo "  ok   - $label\n";
    } else {
        echo "  FAIL - $label\n";
        $failures++;
    }
}

$cron = new JenkinsCronSchedule();

echo "Disabled schedule:\n";
$r = $cron->build(array('checkBuild' => '0', 'action' => 'single'));
cron_check('checkBuild=0 -> ok, empty spec, mode none', $r['ok'] && $r['spec'] === '' && $r['mode'] === 'none');

echo "\nSingle execution mode:\n";
$r = $cron->build(array(
    'checkBuild' => '1', 'action' => 'single',
    'singleMinute' => array('30'), 'singleHour' => array('2'),
    'singleDayOfMonth' => array('*'), 'singleMonth' => array('*'),
    'singleDayOfWeek' => array('1', '2', '3', '4', '5'),
));
cron_check('30 2 * * 1-5 style -> "30 2 * * 1,2,3,4,5"', $r['ok'] && $r['spec'] === '30 2 * * 1,2,3,4,5');

$r = $cron->build(array(
    'checkBuild' => '1', 'action' => 'single',
    'singleMinute' => array('*', '15'), 'singleHour' => array('*'),
    'singleDayOfMonth' => array('*'), 'singleMonth' => array('*'), 'singleDayOfWeek' => array('*'),
));
cron_check('"*" plus a value collapses to "*"', $r['ok'] && strpos($r['spec'], '* *') === 0);

$r = $cron->build(array(
    'checkBuild' => '1', 'action' => 'single',
    'singleMinute' => array('90'), 'singleHour' => array('2'),
    'singleDayOfMonth' => array('*'), 'singleMonth' => array('*'), 'singleDayOfWeek' => array('*'),
));
cron_check('minute 90 is rejected', ! $r['ok'] && strpos($r['error'], 'minute') !== FALSE);

$r = $cron->build(array(
    'checkBuild' => '1', 'action' => 'single',
    'singleMinute' => array('x'), 'singleHour' => array('2'),
    'singleDayOfMonth' => array('*'), 'singleMonth' => array('*'), 'singleDayOfWeek' => array('*'),
));
cron_check('non-numeric minute is rejected', ! $r['ok']);

echo "\nRepetitive mode:\n";
$r = $cron->build(array(
    'checkBuild' => '1', 'action' => 'repetitive',
    'repetitiveMinute' => '15', 'repetitiveHour' => '*',
    'repetitiveDayOfMonth' => '*', 'repetitiveMonth' => '*', 'repetitiveDayOfWeek' => '*',
));
cron_check('every 15 minutes -> "H/15 * * * *"', $r['ok'] && $r['spec'] === 'H/15 * * * *');

$r = $cron->build(array(
    'checkBuild' => '1', 'action' => 'repetitive',
    'repetitiveMinute' => '*', 'repetitiveHour' => '3',
    'repetitiveDayOfMonth' => '*', 'repetitiveMonth' => '*', 'repetitiveDayOfWeek' => '1',
));
cron_check('interval "*" with hour 3 weekday 1 -> "* 3 * * 1"', $r['ok'] && $r['spec'] === '* 3 * * 1');
cron_check('  ... and carries an every-minute warning', ! empty($r['warnings']));

$r = $cron->build(array(
    'checkBuild' => '1', 'action' => 'repetitive',
    'repetitiveMinute' => '0', 'repetitiveHour' => '*',
    'repetitiveDayOfMonth' => '*', 'repetitiveMonth' => '*', 'repetitiveDayOfWeek' => '*',
));
cron_check('interval 0 minutes is rejected', ! $r['ok']);

echo "\nTag mode:\n";
$r = $cron->build(array('checkBuild' => '1', 'action' => 'tags', 'tag' => '@daily'));
cron_check('@daily accepted', $r['ok'] && $r['spec'] === '@daily' && $r['mode'] === 'tags');
$r = $cron->build(array('checkBuild' => '1', 'action' => 'tags', 'tag' => '@decade'));
cron_check('@decade rejected', ! $r['ok']);

echo "\nCustom cron mode:\n";
foreach (array(
    'H 2 * * 1-5',
    'H/15 * * * *',
    '0 0 1 1 *',
    '*/5 * * * *',
    'H(0-29) 2,14 * * *',
    '0 8 * JAN-MAR MON-FRI',
    '@weekly',
) as $ok) {
    $r = $cron->build(array('checkBuild' => '1', 'action' => 'cron', 'customCronExpression' => $ok));
    cron_check('accepts "' . $ok . '"', $r['ok']);
}

foreach (array(
    '0 0 1 1 ?'          => 'Quartz "?" rejected',
    '0 0 L * *'          => 'Quartz "L" rejected',
    '0 0 1W * *'         => 'Quartz "W" rejected',
    '0 0 * * MON#1'      => 'Quartz "#" rejected',
    '60 * * * *'         => 'minute 60 rejected',
    '* 24 * * *'         => 'hour 24 rejected',
    '* * * 13 *'         => 'month 13 rejected',
    '* * * * 8-9'        => 'day-of-week range 8-9 rejected',
    'H 2 * *'            => 'four fields rejected',
    'H 2 * * * *'        => 'six fields rejected',
    '*/0 * * * *'        => 'zero step rejected',
    '5-1 * * * *'        => 'reversed range rejected',
) as $bad => $label) {
    $r = $cron->build(array('checkBuild' => '1', 'action' => 'cron', 'customCronExpression' => $bad));
    cron_check($label, ! $r['ok'] && $r['error'] !== '');
}

echo "\nvalidateSpec() direct:\n";
$v = $cron->validateSpec('  H   2  *  *  1-5 ');
cron_check('collapses whitespace and normalizes', $v['ok'] && $v['normalized'] === 'H 2 * * 1-5');
$v = $cron->validateSpec('* * * * *');
cron_check('every-minute spec is valid but warns', $v['ok'] && ! empty($v['warnings']));

echo "\n";
if ($failures > 0) {
    fwrite(STDERR, "$failures cron schedule assertion(s) failed.\n");
    exit(1);
}
echo "Cron schedule tests passed.\n";
