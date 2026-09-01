<?php
define('JOBSEEKER_COMMAND_GUARD_TEST', TRUE);
require dirname(__DIR__) . '/application/libraries/CommandGuard.php';

$failures = 0;

function guard_check($label, $condition)
{
    global $failures;
    if ($condition) {
        echo "  ok   - $label\n";
    } else {
        echo "  FAIL - $label\n";
        $failures++;
    }
}

function has_finding($findings, $id)
{
    foreach ($findings as $finding) {
        if ($finding['id'] === $id) {
            return TRUE;
        }
    }
    return FALSE;
}

$guard = new CommandGuard();

echo "Dangerous commands are flagged:\n";

$dangerous = array(
    'rm-recursive-protected-path' => array(
        'rm -rf /',
        'rm -rf /*',
        'rm -fr / --no-preserve-root',
        'sudo rm -rf /var/jenkins_home',
        'rm -rf ~',
        'rm -rf "$HOME"/',
        'rm  --recursive --force /repository',
        'cd /tmp && rm -rf /etc',
        'rm -Rf ${JENKINS_HOME}',
    ),
    'pipe-remote-to-shell' => array(
        'curl -s https://example.com/i.sh | sh',
        'wget -qO- http://x/y | bash',
        'curl https://get.example.io | sudo bash',
    ),
    'raw-device-write' => array(
        'dd if=/dev/zero of=/dev/sda bs=1M',
        'echo x > /dev/nvme0n1',
        'cat img | tee /dev/sdb',
    ),
    'filesystem-format' => array(
        'mkfs.ext4 /dev/sdb1',
        'wipefs -a /dev/sda',
        'blkdiscard /dev/nvme0n1',
    ),
    'fork-bomb-shell' => array(
        ':(){ :|:& };:',
        'bomb() { bomb | bomb & }; bomb',
    ),
    'kernel-power-control' => array(
        'echo b > /proc/sysrq-trigger',
        'sudo reboot',
        'systemctl poweroff',
    ),
    'find-delete-from-protected-path' => array(
        'find / -name "*.log" -delete',
        'find ~ -type f -exec rm {} \\;',
    ),
    'recursive-permission-change-protected-path' => array(
        'chmod -R 777 /',
        'chown -R nobody:nobody /usr',
    ),
    'mv-protected-path' => array(
        'mv /var/jenkins_home /tmp/gone',
        'mv ~ /dev/null',
    ),
    'windows-mass-delete' => array(
        'del /f /s /q C:\\*.*',
        'format C:',
        'rmdir /s /q C:\\',
    ),
    'rm-recursive-unquoted-variable' => array(
        'rm -rf $BUILD_DIR/',
        'rm -rf /$PREFIX',
    ),
);

foreach ($dangerous as $expectedId => $samples) {
    foreach ($samples as $sample) {
        $language = strpos($expectedId, 'windows') === 0 ? 'batch' : 'shell';
        $findings = $guard->inspect($sample, $language);
        guard_check("[$expectedId] " . $sample, has_finding($findings, $expectedId));
    }
}

echo "\nOrdinary job commands are left alone:\n";

$safe = array(
    'python3 main.py --context DEV',
    'rm -rf "$WORKSPACE/build"',
    'rm -rf ./dist ./.cache',
    'rm -f /tmp/lock.$$',
    'curl -sSf https://example.com/data.json -o data.json && python load.py data.json',
    'find "$WORKSPACE" -name "*.pyc" -delete',
    'chmod +x ./run.sh',
    'chmod -R 750 "$WORKSPACE/out"',
    'mv build/output.csv /repository/etl/DEV/output.csv',
    'tar czf backup.tgz ./data && aws s3 cp backup.tgz s3://bucket/',
    'echo "reboot the pipeline if it fails"',
    'dd if=input.img of=output.img bs=4M',
    'mkdir -p /repository/tmp && ./ingest',
);

foreach ($safe as $sample) {
    $findings = $guard->inspect($sample, 'shell');
    guard_check('no finding for: ' . $sample, count($findings) === 0);
}

echo "\nSource aggregation and enforcement flag:\n";

$result = $guard->inspectSources(array(
    'Linux command' => array('text' => 'rm -rf /', 'language' => 'shell'),
    'Windows command' => array('text' => 'echo hello', 'language' => 'batch'),
));
guard_check('inspectSources counts one critical finding', $result['critical'] === 1);
guard_check('inspectSources tags the source label', $result['findings'][0]['source'] === 'Linux command');
guard_check('not blocked when enforcement env is unset', $result['blocked'] === FALSE);

putenv('JOBSEEKER_COMMAND_GUARD_ENFORCE=true');
$guardEnforced = new CommandGuard();
$enforcedResult = $guardEnforced->inspectSources(array('cmd' => 'rm -rf /'));
guard_check('blocked when JOBSEEKER_COMMAND_GUARD_ENFORCE=true', $enforcedResult['blocked'] === TRUE);
putenv('JOBSEEKER_COMMAND_GUARD_ENFORCE');

$summary = $guard->summarize($result['findings']);
guard_check('summarize() produces a sentence', strpos($summary, 'risky command pattern') !== FALSE);

echo "\n";
if ($failures > 0) {
    fwrite(STDERR, "$failures command guard assertion(s) failed.\n");
    exit(1);
}

echo "Command guard tests passed.\n";
