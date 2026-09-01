<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_COMMAND_GUARD_TEST')) exit('No direct script access allowed');

/**
 * CommandGuard
 *
 * Defense-in-depth screening for operator-authored job commands (Linux shell,
 * Windows batch and the uploaded/inline scripts that JobSeeker compiles into a
 * Jenkins job).
 *
 * JobSeeker never executes these commands itself: they are written into a
 * Jenkins job and Jenkins runs them as the unprivileged "jenkins" user. The
 * operating system therefore already refuses the textbook "rm -rf /" against
 * system paths. What it does NOT protect is the data that actually matters on a
 * JobSeeker worker: the bind-mounted JENKINS_HOME (every job config, all build
 * history and the credentials store), the shared repository/ tree, the
 * resolved connector secrets in the build environment, and the internal Docker
 * network. A job author can destroy or exfiltrate all of that while staying
 * well within the "jenkins" user's permissions.
 *
 * This class flags a small, curated set of high-signal destructive, disk-wipe,
 * fork-bomb and remote-pipe-to-shell patterns so the author sees the risk
 * before the job is created. It is deliberately a legible bl[o]cklist rather
 * than a sandbox: a determined author can obfuscate around any pattern list, so
 * findings are advisory by default and only refuse a save when
 * JOBSEEKER_COMMAND_GUARD_ENFORCE is set to a truthy value. The durable
 * controls are container hardening (read-only root filesystem, cap_drop,
 * pids_limit, no-new-privileges), keeping Jenkins credentials off the agents,
 * and a network policy - see doc/jobseeker/Security/command-hardening.md.
 *
 * @author JobSeeker
 * @since  2026
 */
class CommandGuard
{
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_HIGH     = 'high';
    const SEVERITY_MEDIUM   = 'medium';

    /**
     * Absolute (or shell-variable) paths whose recursive removal, move or
     * permission change would break the worker or reach the host bind mounts.
     * Compared after light normalization (surrounding quotes and a single
     * trailing "/" or "/." or "/*" removed).
     *
     * @var string[]
     */
    private $protectedPaths = array(
        '/', '~', '$home', '${home}',
        '/bin', '/boot', '/dev', '/etc', '/home', '/lib', '/lib32', '/lib64',
        '/opt', '/proc', '/root', '/run', '/sbin', '/srv', '/sys', '/usr', '/var',
        '/var/jenkins_home', '/var/lib/jenkins', '/var/jenkins_config',
        '/repository', '/workspace', '/home/workspace', '/home/jenkins',
        '$jenkins_home', '${jenkins_home}', '$workspace', '${workspace}',
        '$home/jobs', '${home}/jobs',
    );

    /** @var array<int,array<string,string>> */
    private $segmentRules;

    /** @var array<int,array<string,string>> */
    private $lineRules;

    public function __construct()
    {
        $this->segmentRules = $this->buildSegmentRules();
        $this->lineRules = $this->buildLineRules();
    }

    /**
     * Whether a finding at or above SEVERITY_HIGH should stop the job from
     * being created. Off by default so the guard warns without blocking, which
     * matches how JobSeeker already treats unknown connector references.
     */
    public function isEnforced()
    {
        $flag = getenv('JOBSEEKER_COMMAND_GUARD_ENFORCE');

        if ($flag === FALSE || $flag === NULL) {
            return FALSE;
        }

        return in_array(strtolower(trim((string) $flag)), array('1', 'true', 'yes', 'on', 'enforce'), TRUE);
    }

    /**
     * Inspect one command string.
     *
     * @param  string $command  The raw command text as the author typed or uploaded it.
     * @param  string $language "shell" (default), "batch" or "python" - only changes finding wording.
     * @return array<int,array{id:string,severity:string,title:string,detail:string,snippet:string}>
     */
    public function inspect($command, $language = 'shell')
    {
        $command = (string) $command;
        $findings = array();

        if (trim($command) === '') {
            return $findings;
        }

        // Cheap guard against pathological input.
        if (strlen($command) > 200000) {
            $command = substr($command, 0, 200000);
        }

        $normalized = str_replace(array("\r\n", "\r"), "\n", $command);

        foreach ($this->collectSegments($normalized) as $segment) {
            $this->inspectRemoveMoveChown($segment, $findings);

            foreach ($this->segmentRules as $rule) {
                if (preg_match($rule['pattern'], $segment)) {
                    $findings[] = $this->finding($rule['id'], $rule['severity'], $rule['title'], $rule['detail'], $segment);
                }
            }
        }

        foreach ($this->lineRules as $rule) {
            if (preg_match($rule['pattern'], $normalized)) {
                $findings[] = $this->finding($rule['id'], $rule['severity'], $rule['title'], $rule['detail'], $this->firstMatchingLine($rule['pattern'], $normalized));
            }
        }

        return $this->dedupe($findings);
    }

    /**
     * Inspect several named command sources at once.
     *
     * @param  array<string,array{text:string,language?:string}> $sources  keyed by a human label
     * @return array{findings:array<int,array<string,mixed>>,blocked:bool,critical:int,high:int,medium:int}
     */
    public function inspectSources(array $sources)
    {
        $all = array();

        foreach ($sources as $label => $source) {
            if (is_string($source)) {
                $source = array('text' => $source);
            }

            $text = isset($source['text']) ? (string) $source['text'] : '';
            $language = isset($source['language']) ? (string) $source['language'] : 'shell';

            foreach ($this->inspect($text, $language) as $finding) {
                $finding['source'] = (string) $label;
                $all[] = $finding;
            }
        }

        $counts = array('critical' => 0, 'high' => 0, 'medium' => 0);
        foreach ($all as $finding) {
            if (isset($counts[$finding['severity']])) {
                $counts[$finding['severity']]++;
            }
        }

        $blocking = $counts['critical'] + $counts['high'];

        return array(
            'findings' => $all,
            'blocked'  => $this->isEnforced() && $blocking > 0,
            'critical' => $counts['critical'],
            'high'     => $counts['high'],
            'medium'   => $counts['medium'],
        );
    }

    /**
     * One-line human summary of a set of findings, for a flash message or log.
     */
    public function summarize(array $findings)
    {
        if (empty($findings)) {
            return '';
        }

        $titles = array();
        foreach ($findings as $finding) {
            $titles[$finding['title']] = TRUE;
        }

        $list = array_keys($titles);
        $shown = array_slice($list, 0, 3);
        $suffix = count($list) > 3 ? ' and ' . (count($list) - 3) . ' more' : '';

        return count($findings) . ' risky command pattern' . (count($findings) === 1 ? '' : 's')
            . ' detected: ' . implode('; ', $shown) . $suffix . '.';
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Break a command into rough "simple command" segments so a rule matches a
     * single pipeline stage rather than the whole script. Also pulls out the
     * bodies of $(...) and `...` command substitutions.
     *
     * @return string[]
     */
    private function collectSegments($normalized)
    {
        $segments = array();

        foreach (preg_split('/\|\||&&|[;\n|&]/', $normalized) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $segments[] = $part;
            }
        }

        if (preg_match_all('/\$\(([^()]{0,4000})\)|`([^`]{0,4000})`/', $normalized, $matches)) {
            foreach (array_merge($matches[1], $matches[2]) as $inner) {
                foreach (preg_split('/\|\||&&|[;\n|&]/', (string) $inner) as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $segments[] = $part;
                    }
                }
            }
        }

        if (empty($segments)) {
            $segments[] = trim($normalized);
        }

        return $segments;
    }

    /**
     * "rm" with a recursive flag, "mv" and "chmod/chown -R" against a protected
     * path are handled here because they need operand parsing, not just a regex.
     */
    private function inspectRemoveMoveChown($segment, array &$findings)
    {
        $tokens = $this->tokenize($segment);
        if (empty($tokens)) {
            return;
        }

        $command = $this->baseName($tokens[0]);

        // Allow a leading "sudo", "nohup", "time", "command", "xargs", "env".
        $offset = 0;
        while (in_array($command, array('sudo', 'nohup', 'time', 'command', 'xargs', 'env', 'nice', 'ionice', 'busybox'), TRUE) && isset($tokens[$offset + 1])) {
            $offset++;
            $command = $this->baseName($tokens[$offset]);
        }

        $operands = array();
        $recursive = FALSE;
        $force = FALSE;

        for ($i = $offset + 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if ($token === '--') {
                continue;
            }

            if (strpos($token, '--') === 0) {
                if (stripos($token, '--recursive') === 0) {
                    $recursive = TRUE;
                }
                if (stripos($token, '--force') === 0) {
                    $force = TRUE;
                }
                if (stripos($token, '--no-preserve-root') === 0) {
                    $recursive = TRUE;
                }
                continue;
            }

            if (strlen($token) > 1 && $token[0] === '-') {
                if (strpbrk($token, 'rR') !== FALSE) {
                    $recursive = TRUE;
                }
                if (strpbrk($token, 'f') !== FALSE) {
                    $force = TRUE;
                }
                continue;
            }

            $operands[] = $token;
        }

        if ($command === 'rm') {
            if (! $recursive) {
                return;
            }

            foreach ($operands as $operand) {
                if ($this->isProtectedPath($operand)) {
                    $findings[] = $this->finding(
                        'rm-recursive-protected-path',
                        self::SEVERITY_CRITICAL,
                        'Recursive delete of a protected path',
                        'Removes "' . $operand . '". On the Jenkins worker that path is a system directory, the bind-mounted JENKINS_HOME (every job config and the credentials store) or the shared repository tree - and the "jenkins" user is allowed to delete it, so the loss reaches the host.',
                        $segment
                    );
                } elseif ($this->isUnquotedSlashVariable($operand, $segment)) {
                    $findings[] = $this->finding(
                        'rm-recursive-unquoted-variable',
                        self::SEVERITY_MEDIUM,
                        'Recursive delete of an unquoted variable next to "/"',
                        'If "' . $operand . '" ever expands to an empty or unexpected value this deletes the wrong tree - the classic "rm -rf \$VAR/" outage. Quote the variable and guard against an empty value (for example: [ -n "$VAR" ] && rm -rf -- "$VAR"/).',
                        $segment
                    );
                }
            }

            return;
        }

        if ($command === 'mv' && ! empty($operands) && $this->isProtectedPath($operands[0])) {
            $findings[] = $this->finding(
                'mv-protected-path',
                self::SEVERITY_HIGH,
                'Moving or renaming a protected path',
                'Moving "' . $operands[0] . '" away is as destructive as deleting it and leaves the worker or its bind mounts broken.',
                $segment
            );
            return;
        }

        if (($command === 'chmod' || $command === 'chown' || $command === 'chgrp') && $recursive) {
            foreach ($operands as $operand) {
                if ($this->isProtectedPath($operand)) {
                    $findings[] = $this->finding(
                        'recursive-permission-change-protected-path',
                        self::SEVERITY_HIGH,
                        'Recursive permission or ownership change on a protected path',
                        'Running "' . $command . ' -R" over "' . $operand . '" breaks the worker and can make it insecure (for example "chmod -R 777 /").',
                        $segment
                    );
                    break;
                }
            }
        }
    }

    /**
     * Regex rules evaluated against each command segment.
     *
     * @return array<int,array<string,string>>
     */
    private function buildSegmentRules()
    {
        return array(
            array(
                'id'       => 'raw-device-write',
                'severity' => self::SEVERITY_CRITICAL,
                'title'    => 'Raw write to a block device',
                'detail'   => 'Writing to /dev/sd*, /dev/nvme*, /dev/xvd* or /dev/vd* destroys a disk or an attached volume irrecoverably.',
                'pattern'  => '#(?:\bdd\b[^\n]*?\bof\s*=\s*|\btee\b[^\n]*?|>\s*|>>\s*)/dev/(?:sd[a-z]|nvme\d+n\d+|xvd[a-z]|vd[a-z]|md\d+)#i',
            ),
            array(
                'id'       => 'filesystem-format',
                'severity' => self::SEVERITY_CRITICAL,
                'title'    => 'Reformatting or wiping a device',
                'detail'   => 'mkfs / mke2fs / wipefs / "sgdisk --zap" / "blkdiscard" reformats a partition or erases its table; on a mounted volume it takes the data with it.',
                'pattern'  => '#\b(?:mkfs(?:\.[a-z0-9]+)?|mke2fs|wipefs|blkdiscard|sgdisk|shred)\b#i',
            ),
            array(
                'id'       => 'kernel-power-control',
                'severity' => self::SEVERITY_HIGH,
                'title'    => 'Rebooting or halting the worker host',
                'detail'   => 'Writing to /proc/sysrq-trigger or calling reboot/halt/poweroff/shutdown/init 0 stops the worker (and, depending on the host, its neighbours).',
                'pattern'  => '#(?:>\s*/proc/sysrq-trigger|(?:^|\bsudo\s+)(?:reboot|halt|poweroff|shutdown)\b|\binit\s+0\b|\bsystemctl\s+(?:poweroff|reboot|halt)\b)#i',
            ),
            array(
                'id'       => 'find-delete-from-protected-path',
                'severity' => self::SEVERITY_HIGH,
                'title'    => '"find ... -delete" starting from a protected path',
                'detail'   => 'A find rooted at /, ~, JENKINS_HOME or the repository tree with -delete or "-exec rm" walks and removes the whole subtree.',
                'pattern'  => '#\bfind\s+(?:"|\x27)?(?:/(?:\s|"|\x27|$)|~|\$\{?HOME\}?|\$\{?JENKINS_HOME\}?|/var/jenkins_home|/repository)[^\n]*?\s-(?:delete\b|exec\s+(?:rm|unlink|shred)\b)#i',
            ),
            array(
                'id'       => 'disable-firewall',
                'severity' => self::SEVERITY_MEDIUM,
                'title'    => 'Disabling the host firewall',
                'detail'   => 'Flushing iptables/nftables or stopping ufw/firewalld from a job removes a network control the deployment may rely on.',
                'pattern'  => '#(?:\biptables\s+-F\b|\bnft\s+flush\s+ruleset\b|\bufw\s+disable\b|\bsystemctl\s+stop\s+(?:firewalld|ufw)\b)#i',
            ),
            array(
                'id'       => 'windows-mass-delete',
                'severity' => self::SEVERITY_CRITICAL,
                'title'    => 'Windows recursive force delete of a drive or system path',
                'detail'   => '"del /f /s /q", "rd /s /q" or "format" against a drive root or %SystemRoot% wipes it. "rmdir /s" on C:\\ is equivalent to "rm -rf /".',
                'pattern'  => '#(?:\b(?:del|erase)\b[^\n]*?/[sS][^\n]*?(?:[A-Za-z]:\\\\|%SystemRoot%|%windir%|\\\\)|\brd\b[^\n]*?/[sS][^\n]*?[A-Za-z]:\\\\|\brmdir\b[^\n]*?/[sS][^\n]*?[A-Za-z]:\\\\|\bformat\b\s+[A-Za-z]:|\bcipher\b\s+/w:)#i',
            ),
        );
    }

    /**
     * Regex rules evaluated against the whole command (constructs that span
     * segment separators, such as a fork bomb).
     *
     * @return array<int,array<string,string>>
     */
    private function buildLineRules()
    {
        return array(
            array(
                'id'       => 'pipe-remote-to-shell',
                'severity' => self::SEVERITY_CRITICAL,
                'title'    => 'Piping a downloaded script straight into a shell',
                'detail'   => 'The response body of curl/wget is executed without review. A changed, hijacked or man-in-the-middled URL then runs arbitrary code on the worker with the job\'s access to connector secrets and the internal network. Download to a file, verify it, then run it.',
                'pattern'  => '#\b(?:curl|wget|fetch|lwp-request)\b[^\n]*?\|[^\n]*?\b(?:ba|z|k|da|a)?sh\b#i',
            ),
            array(
                'id'       => 'fork-bomb-shell',
                'severity' => self::SEVERITY_CRITICAL,
                'title'    => 'Fork bomb',
                'detail'   => 'A self-replicating function such as ":(){ :|:& };:" exhausts the process table and freezes the worker. Set "pids_limit" on the container as the real control.',
                'pattern'  => '#[A-Za-z_.:][A-Za-z0-9_.:]*\s*\(\s*\)\s*\{[^{}]*\|[^{}]*&[^{}]*\}\s*;\s*[A-Za-z_.:]#',
            ),
            array(
                'id'       => 'fork-bomb-loop',
                'severity' => self::SEVERITY_CRITICAL,
                'title'    => 'Fork bomb',
                'detail'   => 'A "fork while" / unbounded background-spawn loop exhausts the process table.',
                'pattern'  => '#\bfork\s*while\s*(?:1|true|\(\s*\))#i',
            ),
            array(
                'id'       => 'fork-bomb-python',
                'severity' => self::SEVERITY_CRITICAL,
                'title'    => 'Fork bomb',
                'detail'   => 'An unguarded "while True: os.fork()" exhausts the process table.',
                'pattern'  => '#while\s+True\s*:\s*(?:\n\s*)?os\.fork\s*\(#i',
            ),
        );
    }

    private function tokenize($segment)
    {
        $tokens = array();

        // Split on whitespace but keep quoted runs together.
        if (! preg_match_all('/"[^"]*"|\x27[^\x27]*\x27|\S+/', $segment, $matches)) {
            return $tokens;
        }

        foreach ($matches[0] as $token) {
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private function baseName($token)
    {
        $token = $this->stripQuotes($token);
        $slash = strrpos($token, '/');

        if ($slash !== FALSE) {
            $token = substr($token, $slash + 1);
        }

        return strtolower($token);
    }

    private function stripQuotes($value)
    {
        $value = trim((string) $value);

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = substr($value, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        return $value;
    }

    private function isProtectedPath($operand)
    {
        $path = strtolower($this->stripQuotes($operand));
        $path = preg_replace('~/(?:\.|\*)?$~', '', $path);

        if ($path === '') {
            // The operand was "/", "/." or "/*".
            $path = '/';
        }

        // "$HOME/" style with a trailing slash already trimmed above.
        return in_array($path, $this->protectedPaths, TRUE);
    }

    private function isUnquotedSlashVariable($operand, $segment)
    {
        // e.g. rm -rf $BUILD_DIR/   or   rm -rf /$PREFIX
        if (! preg_match('~^\$\{?[A-Za-z_][A-Za-z0-9_]*\}?/?$~', $operand) && ! preg_match('~^/\$\{?[A-Za-z_]~', $operand)) {
            return FALSE;
        }

        // If the same token appears double-quoted in the raw segment, treat it as guarded.
        $bare = trim($operand, '/');
        if (strpos($segment, '"' . $bare . '"') !== FALSE || strpos($segment, '"' . $bare . '/"') !== FALSE) {
            return FALSE;
        }

        return TRUE;
    }

    private function finding($id, $severity, $title, $detail, $snippet)
    {
        $snippet = trim(preg_replace('/\s+/', ' ', (string) $snippet));
        if (strlen($snippet) > 240) {
            $snippet = substr($snippet, 0, 237) . '...';
        }

        return array(
            'id'       => $id,
            'severity' => $severity,
            'title'    => $title,
            'detail'   => $detail,
            'snippet'  => $snippet,
        );
    }

    private function firstMatchingLine($pattern, $normalized)
    {
        foreach (explode("\n", $normalized) as $line) {
            if (preg_match($pattern, $line)) {
                return $line;
            }
        }

        return $normalized;
    }

    private function dedupe(array $findings)
    {
        $seen = array();
        $unique = array();

        foreach ($findings as $finding) {
            $key = $finding['id'] . '|' . $finding['snippet'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = TRUE;
            $unique[] = $finding;
        }

        return $unique;
    }
}
