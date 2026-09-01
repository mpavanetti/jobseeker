# Job command hardening

JobSeeker never runs an operator's job command itself. Every Linux shell, Windows
batch, Python and Talend job is compiled into a Jenkins job and **Jenkins** runs
it - on the controller container or an environment agent - as the unprivileged
`jenkins` user. Even the *Run preview* action creates a throwaway Jenkins job and
triggers it through the Jenkins API. Nothing an operator types executes in the
nginx or PHP container.

## What the operating system already prevents

Because the job runs as a non-root user, the textbook `rm -rf /` against
`/bin`, `/usr`, `/etc` and similar fails with *permission denied*. That part is
genuinely covered and needs nothing from JobSeeker.

## What it does **not** prevent

The `jenkins` user owns the data that actually matters on a worker, so the OS
happily lets a job:

- `rm -rf $HOME`, `rm -rf /var/jenkins_home/*`, `rm -rf ~/jobs` - deletes every
  job config, all build history and the credentials store. `./jenkins` is a bind
  mount, so the loss reaches the host directory too.
- `rm -rf` the shared `repository/` tree - every Data Asset and job source.
- Read `JOBSEEKER_CONNECTOR_API_TOKEN` and the materialized connector secrets out
  of the build environment and POST them anywhere.
- `curl https://… | sh` a miner or a reverse shell.
- `:(){ :|:& };:` a fork bomb, fill the disk, `mkfs`/`dd` a mounted volume,
  scan the internal Docker network.

"Give the job the right user and the OS rejects it" is only true for the classic
system-path delete. It is not a security boundary for a JobSeeker worker.

## Layer 1 - CommandGuard (authoring-time advisory)

`application/libraries/CommandGuard.php` screens the shell / batch / inline-Python
commands an operator submits (and, for a saved job, the generated `<command>`)
against a small curated list of high-signal patterns: recursive delete / move /
`chmod -R` of `/`, `~`, `$JENKINS_HOME`, `/var/jenkins_home` or the repository
tree; `dd` / `mkfs` / `wipefs` / `blkdiscard` on a device; fork bombs;
`curl … | sh`; `find / … -delete`; kernel power control; the Windows drive-wipe
equivalents; and the classic unquoted-variable `rm -rf "$X/"` reliability bug.

- **Default (`JOBSEEKER_COMMAND_GUARD_ENFORCE=false`)**: findings appear in the
  job's *Command safety* panel in Job Creation and are written to the PHP log.
  The job is still created. This matches how JobSeeker treats unknown connector
  references.
- **Enforced (`JOBSEEKER_COMMAND_GUARD_ENFORCE=true`)**: a `critical` or `high`
  finding refuses the save and blocks the inline Python preview.

CommandGuard is a **blocklist, not a sandbox**. A determined author can obfuscate
around any pattern list (`r""m`, base64, `$(…)`, a downloaded script). Treat it as
a guard rail that catches mistakes and casual abuse, and rely on Layer 2 for
containment.

Run its test suite (needs PHP, e.g. inside the `php` container):

```bash
php scripts/test-command-guard.php
```

## Layer 2 - container controls (the real boundary)

Apply these to the Jenkins controller and every agent in a shared or production
deployment. They are what actually contains a hostile job.

| Control | Compose / runtime setting | Effect |
| --- | --- | --- |
| Non-root, fixed UID | `user: "1000:1000"` | Job cannot touch root-owned paths. |
| No privilege escalation | `security_opt: ["no-new-privileges:true"]` | `sudo`/setuid cannot regain root. |
| Drop capabilities | `cap_drop: ["ALL"]` (add back only what builds need) | No raw sockets, no mount, no `mknod`. |
| Read-only root FS | `read_only: true` + `tmpfs: [/tmp, /run]` and a dedicated writable workspace volume | System paths cannot be modified even by the owning user. |
| Process cap | `pids_limit: 512` | Fork bombs cannot exhaust the host. |
| Memory / CPU cap | `mem_limit`, `cpus` | One job cannot starve neighbours. |
| Keep the bind mount off agents | give agents a named volume workspace, not `./jenkins` | A destructive job cannot reach the host `./jenkins` directory. |
| Don't mount Jenkins credentials on agents | scope the JobSeeker Jenkins token to the controller | A job on an agent cannot drive the Jenkins API as admin. |
| Network policy | put agents on an egress-restricted network; allowlist the connector endpoint and package mirrors | Limits exfiltration and lateral movement. |
| Least-privilege connector accounts | scope every connector's external credential to read-only / single-schema where possible | Caps the blast radius of a leaked secret. |

## Layer 3 - operational

- Job creation and editing already require the `canManageJobs` role - keep that
  role small and audited.
- Ship the PHP log (CommandGuard advisories, promotion history) to a central
  store and alert on `critical`/`high` findings.
- Take regular off-host backups of `./jenkins` and `./repository` so a
  destructive job is recoverable regardless of the guard.
