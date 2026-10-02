<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

trait JobCreationExecutionTrait
{
      private function pythonEnvironmentArgument($environment, $checkEnvironment) {
        // Pass the environment to the Python entrypoint as a runtime reference, not
        // a baked-in literal, so sys.argv[1] always follows the job's ENVIRONMENT
        // parameter - after an environment promotion/deployment, or when the job is
        // triggered manually with a different ENVIRONMENT value.
        return ($environment != '0' && $checkEnvironment == 1) ? '"$JOBSEEKER_ENVIRONMENT"' : '';
      }

      private function shellArgumentString($arguments) {
        $escapedArguments = array();

        foreach ($arguments as $argument) {
          $escapedArguments[] = escapeshellarg($argument);
        }

        return implode(' ', $escapedArguments);
      }

      private function dataAssetsRuntimeLines($repositoryRoot) {
        $repositoryRoot = rtrim((string) $repositoryRoot, '/\\');
        return array(
          'export JOBSEEKER_REPOSITORY_ROOT='.escapeshellarg($repositoryRoot),
          'export JOBSEEKER_DATA_ASSETS_MANIFEST="$JOBSEEKER_REPOSITORY_ROOT/data-assets/manifest.json"',
          'export JOBSEEKER_ENVIRONMENT="${ENVIRONMENT:-${JOBSEEKER_ENVIRONMENT:-}}"',
          'export JOBSEEKER_JOB_NAME="${JOB_NAME:-${JOBSEEKER_JOB_NAME:-}}"',
          'export JOBSEEKER_DATA_ASSET_JOB="${JOBSEEKER_DATA_ASSET_JOB:-${JOBSEEKER_JOB_NAME:-}}"'
        );
      }

      private function environmentContext() {
        $this->load->library('EnvironmentContext');
        return $this->environmentcontext;
      }

      /**
       * Context values the deployment supplies through JOBSEEKER_CONTEXT_* in
       * .env, exported into the job so a script can read them directly as well
       * as through the SDK resolver. They are baked into the generated command
       * rather than read from the worker's own environment, because a Jenkins
       * agent or a job container does not inherit the app's .env.
       *
       * Only that prefix is ever exported - see EnvironmentContext.
       */
      private function environmentContextLines() {
        return $this->environmentContext()->exportLines();
      }

      /** `-e NAME` lines so a containerised job receives the same values. */
      private function dockerContextEnvLines() {
        $lines = array();
        foreach ($this->environmentContext()->variableNames() as $name) {
          $lines[] = '  -e '.$name.' \\';
        }
        return $lines;
      }

      private function connectorRuntimeLines() {
        return array(
          'export JOBSEEKER_CONNECTORS_DIR="$WORKSPACE/.jobseeker-connectors"',
          'rm -rf "$JOBSEEKER_CONNECTORS_DIR"',
          'if [ -z "${JOBSEEKER_CONNECTOR_API_URL:-}" ] || [ -z "${JOBSEEKER_CONNECTOR_API_TOKEN:-}" ]; then echo "JobSeeker connector runtime is not configured on this Jenkins worker." >&2; exit 78; fi',
          'command -v jobseeker-connector >/dev/null || { echo "The JobSeeker connector helper is not installed on this Jenkins worker." >&2; exit 127; }',
          'umask 077',
          'jobseeker-connector materialize --directory "$JOBSEEKER_CONNECTORS_DIR" --environment "${JOBSEEKER_ENVIRONMENT:-LOCAL}" --job "${JOBSEEKER_JOB_NAME:-job}" >/dev/null || { rm -rf "$JOBSEEKER_CONNECTORS_DIR"; exit 1; }',
          'if [ -f "$JOBSEEKER_CONNECTORS_DIR/.source-environment-variables" ]; then while IFS= read -r JOBSEEKER_SECRET_VARIABLE; do if [ -n "$JOBSEEKER_SECRET_VARIABLE" ]; then unset "$JOBSEEKER_SECRET_VARIABLE"; fi; done < "$JOBSEEKER_CONNECTORS_DIR/.source-environment-variables"; unset JOBSEEKER_SECRET_VARIABLE; fi',
          'unset JOBSEEKER_CONNECTOR_API_URL JOBSEEKER_CONNECTOR_API_TOKEN AZURE_TENANT_ID AZURE_CLIENT_ID AZURE_CLIENT_SECRET AZURE_FEDERATED_TOKEN_FILE AZURE_AUTHORITY_HOST AWS_REGION AWS_DEFAULT_REGION AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_SESSION_TOKEN AWS_ROLE_ARN AWS_WEB_IDENTITY_TOKEN_FILE',
          'export JOBSEEKER_CONNECTOR_HELPER="$(command -v jobseeker-connector)"'
        );
      }

      private function dockerConnectorSetupLines($dockerImage, $imageIsVariable = FALSE) {
        $imageArgument = $imageIsVariable ? '"$JOBSEEKER_DOCKER_RUN_IMAGE"' : escapeshellarg($dockerImage);
        return array(
          'if [ -d "$JOBSEEKER_REPOSITORY_ROOT/python/lib/jobseeker-sdk/src/jobseeker" ]; then rm -rf "$JOBSEEKER_CONNECTORS_DIR/.jobseeker-sdk"; cp -R "$JOBSEEKER_REPOSITORY_ROOT/python/lib/jobseeker-sdk" "$JOBSEEKER_CONNECTORS_DIR/.jobseeker-sdk"; printf \'%s\\n\' \'#!/bin/sh\' \'set -eu\' \'root=${JOBSEEKER_CONNECTORS_DIR:-/run/jobseeker-connectors}\' \'command -v python3 >/dev/null 2>&1 || { echo "jobseeker-asset requires a Python-capable Docker image." >&2; exit 127; }\' \'PYTHONPATH="$root/.jobseeker-sdk/src${PYTHONPATH:+:$PYTHONPATH}" exec python3 -c "from jobseeker import asset_cli; asset_cli()" "$@"\' > "$JOBSEEKER_CONNECTORS_DIR/jobseeker-asset"; chmod 0700 "$JOBSEEKER_CONNECTORS_DIR/jobseeker-asset"; fi',
          'JOBSEEKER_CONNECTORS_VOLUME="$(printf "jobseeker-connectors-%s-%s" "${JOB_NAME:-job}" "${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"',
          'docker volume create "$JOBSEEKER_CONNECTORS_VOLUME" >/dev/null',
          'tar -C "$JOBSEEKER_CONNECTORS_DIR" -cf - . | docker run --rm -i --user 0 --entrypoint sh -v "$JOBSEEKER_CONNECTORS_VOLUME:/run/jobseeker-connectors" '.$imageArgument.' -c "cd /run/jobseeker-connectors && tar -xf - && find . -type d -exec chmod 0555 {} + && find . -type f ! -name jobseeker-connector ! -name jobseeker-asset -exec chmod 0444 {} + && chmod 0555 ./jobseeker-connector && if [ -f ./jobseeker-asset ]; then chmod 0555 ./jobseeker-asset; fi"'
        );
      }

      private function dockerJobIdentityLines($runtime) {
        return array(
          'JOBSEEKER_CONTAINER_IDENTITY="${JOB_NAME:-job}-${BUILD_NUMBER:-0}"',
          'JOBSEEKER_CONTAINER_SLUG="$(printf "%s" "${JOB_NAME:-job}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-72)"',
          'if [ -z "$JOBSEEKER_CONTAINER_SLUG" ]; then JOBSEEKER_CONTAINER_SLUG="job"; fi',
          'JOBSEEKER_CONTAINER_FINGERPRINT="$(printf "%s" "$JOBSEEKER_CONTAINER_IDENTITY" | cksum | awk \'{print $1}\')"',
          'JOBSEEKER_CONTAINER_NAME="$(printf "jobseeker-job-%s-%s-%s" "$JOBSEEKER_CONTAINER_SLUG" "${BUILD_NUMBER:-0}" "$JOBSEEKER_CONTAINER_FINGERPRINT" | cut -c1-120)"',
          'export JOBSEEKER_CONTAINER_NAME',
          'export JOBSEEKER_CONTAINER_RUNTIME='.escapeshellarg($runtime)
        );
      }

      private function dockerJobRunIdentityOptions() {
        return array(
          '  --name "$JOBSEEKER_CONTAINER_NAME" \\',
          '  --cpus "$JOBSEEKER_CONTAINER_CPUS" \\',
          '  --memory "${JOBSEEKER_CONTAINER_MEMORY_MB}m" \\',
          '  --memory-swap "${JOBSEEKER_CONTAINER_MEMORY_MB}m" \\',
          '  --label com.jobseeker.managed=true \\',
          '  --label com.jobseeker.kind=job \\',
          '  --label "com.jobseeker.job.name=${JOB_NAME:-${JOBSEEKER_JOB_NAME:-job}}" \\',
          '  --label "com.jobseeker.build.number=${BUILD_NUMBER:-0}" \\',
          '  --label "com.jobseeker.environment=${JOBSEEKER_ENVIRONMENT:-}" \\',
          '  --label "com.jobseeker.runtime=${JOBSEEKER_CONTAINER_RUNTIME}" \\',
          '  -e "ENVIRONMENT=${JOBSEEKER_ENVIRONMENT:-}" \\'
        );
      }

      /**
       * The shell a job's container runs its script in: a login shell, which
       * resets PATH from /etc/profile and so drops what the image put there
       * (a Dockerfile's /opt/venv/bin, a runtime's tools). The image's PATH
       * travels as JOBSEEKER_IMAGE_PATH, and scripts put it first again, so a
       * job finds the Python it was developed with in the editor.
       */
      private function dockerLoginShell() {
        return 'sh -c \'JOBSEEKER_IMAGE_PATH="$PATH"; export JOBSEEKER_IMAGE_PATH; exec sh -lc "$@"\' jobseeker';
      }

      /** The first line of every script dockerLoginShell() runs. */
      private function dockerScriptPathLine() {
        return 'export PATH="$JOBSEEKER_CONNECTORS_DIR:${JOBSEEKER_IMAGE_PATH:+$JOBSEEKER_IMAGE_PATH:}$PATH"';
      }

      private function dockerJobResourceLines($runtimeOptions) {
        $cpu = isset($runtimeOptions['cpuLimit']) ? $runtimeOptions['cpuLimit'] : '1';
        $memory = isset($runtimeOptions['memoryLimitMb']) ? (int) $runtimeOptions['memoryLimitMb'] : 512;
        return array(
          'export JOBSEEKER_CONTAINER_CPUS='.escapeshellarg($cpu),
          'export JOBSEEKER_CONTAINER_MEMORY_MB='.escapeshellarg($memory)
        );
      }

      private function buildLinuxCommandExecutionCommand($commandText, $runtimeOptions = array(), $repositoryRoot = '') {
        $commandText = str_replace(array("\r\n", "\r"), "\n", (string) $commandText);
        $runtimeMode = isset($runtimeOptions['mode']) ? $runtimeOptions['mode'] : 'local';
        $dockerImage = isset($runtimeOptions['dockerImage']) ? $runtimeOptions['dockerImage'] : 'alpine:3.20';
        $runtimeLines = array_merge($this->dataAssetsRuntimeLines($repositoryRoot), $this->environmentContextLines(), $this->connectorRuntimeLines());

        if ($runtimeMode !== 'docker') {
          $runtimeLines[] = 'trap \'rm -rf "$JOBSEEKER_CONNECTORS_DIR"\' EXIT';
          $runtimeLines[] = 'printf "%s\n" "[JobSeeker] Shell execution"';
          $runtimeLines[] = $commandText;
          return implode("\n", $runtimeLines);
        }

        $lines = array_merge(array('set -e'), $runtimeLines);
        $lines[] = 'printf "%s\n" "[JobSeeker] Docker runtime setup"';
        $lines[] = 'export JOBSEEKER_LINUX_RUNTIME=\'docker\'';
        $lines[] = 'export JOBSEEKER_DOCKER_IMAGE='.escapeshellarg($dockerImage);
        $lines = array_merge($lines, $this->dockerJobResourceLines($runtimeOptions));
        $lines[] = 'export JOBSEEKER_LINUX_COMMAND_B64='.escapeshellarg(base64_encode($commandText));
        $lines[] = 'command -v docker >/dev/null || { echo "Docker runtime selected, but docker is not available on this Jenkins agent."; exit 127; }';
        $lines = array_merge($lines, $this->dockerJobIdentityLines('linux-shell'));
        $lines[] = 'mkdir -p "$JOBSEEKER_REPOSITORY_ROOT/data-assets"';
        $lines[] = 'JOBSEEKER_DATA_ASSETS_VOLUME="$(printf "jobseeker-assets-%s-%s" "${JOB_NAME:-job}" "${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"';
        $lines[] = 'docker volume create "$JOBSEEKER_DATA_ASSETS_VOLUME" >/dev/null';
        $lines = array_merge($lines, $this->dockerConnectorSetupLines($dockerImage));
        $lines[] = 'jobseeker_asset_cleanup() { rm -rf "$JOBSEEKER_CONNECTORS_DIR"; docker volume rm "$JOBSEEKER_CONNECTORS_VOLUME" >/dev/null 2>&1 || true; docker volume rm "$JOBSEEKER_DATA_ASSETS_VOLUME" >/dev/null 2>&1 || true; }';
        $lines[] = 'trap jobseeker_asset_cleanup EXIT';
        $lines[] = 'tar -C "$JOBSEEKER_REPOSITORY_ROOT" -cf - data-assets | docker run --rm -i --user 0 --entrypoint sh -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" "$JOBSEEKER_DOCKER_IMAGE" -c "cd /jobseeker-repository && tar -xf - && chmod -R a+rwX data-assets"';
        $lines[] = 'printf "%s\n" "[JobSeeker] Docker container execution"';
        $lines[] = 'JOBSEEKER_DOCKER_STATUS=0';
        $lines[] = 'docker run --rm -i \\';
        $lines = array_merge($lines, $this->dockerJobRunIdentityOptions());
        $lines[] = '  --network host \\';
        $lines[] = '  -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" \\';
        $lines[] = '  -v "$JOBSEEKER_CONNECTORS_VOLUME:/run/jobseeker-connectors:ro" \\';
        $lines[] = '  -e "JOBSEEKER_LINUX_COMMAND_B64=$JOBSEEKER_LINUX_COMMAND_B64" \\';
        $lines[] = '  -e JOBSEEKER_REPOSITORY_ROOT=/jobseeker-repository \\';
        $lines[] = '  -e JOBSEEKER_DATA_ASSETS_MANIFEST=/jobseeker-repository/data-assets/manifest.json \\';
        $lines[] = '  -e JOBSEEKER_CONNECTORS_DIR=/run/jobseeker-connectors \\';
        $lines[] = '  -e JOBSEEKER_CONNECTOR_HELPER=/run/jobseeker-connectors/jobseeker-connector \\';
        $lines[] = '  -e JOBSEEKER_ENVIRONMENT -e JOBSEEKER_JOB_NAME -e JOBSEEKER_DATA_ASSET_JOB \\';
        $lines = array_merge($lines, $this->dockerContextEnvLines());
        $lines[] = '  -e JOB_NAME -e BUILD_NUMBER -e BUILD_ID -e JOBSEEKER_CONTAINER_NAME \\';
        $lines[] = '  "$JOBSEEKER_DOCKER_IMAGE" \\';
        $lines[] = '  '.$this->dockerLoginShell().' '.escapeshellarg($this->dockerScriptPathLine().'; printf "%s" "$JOBSEEKER_LINUX_COMMAND_B64" | base64 -d | sh').' || JOBSEEKER_DOCKER_STATUS=$?';
        $lines[] = 'printf "%s\n" "[JobSeeker] Cleanup"';
        $lines[] = 'docker run --rm --user 0 --entrypoint sh -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" "$JOBSEEKER_DOCKER_IMAGE" -c \'rm -f /jobseeker-repository/data-assets/manifest.json; tar -C /jobseeker-repository -cf - data-assets\' | tar -C "$JOBSEEKER_REPOSITORY_ROOT" -xf -';
        $lines[] = 'if [ "$JOBSEEKER_DOCKER_STATUS" -ne 0 ]; then exit "$JOBSEEKER_DOCKER_STATUS"; fi';

        return implode("\n", $lines);
      }

      private function buildShellScriptExecutionCommand($execution, $runtimeOptions = array(), $repositoryRoot = '') {
        $arguments = isset($execution['arguments']) ? $execution['arguments'] : array();
        $argumentString = $this->shellArgumentString($arguments);
        $runtimeMode = isset($runtimeOptions['mode']) ? $runtimeOptions['mode'] : 'local';
        $dockerImage = isset($runtimeOptions['dockerImage']) ? $runtimeOptions['dockerImage'] : ($execution['scriptType'] === 'talend' ? 'eclipse-temurin:17-jre-alpine' : 'alpine:3.20');

        $runtimeLines = array_merge($this->dataAssetsRuntimeLines($repositoryRoot), $this->environmentContextLines(), $this->connectorRuntimeLines());

        if ($runtimeMode !== 'docker') {
          $runtimeLines[] = 'trap \'rm -rf "$JOBSEEKER_CONNECTORS_DIR"\' EXIT';
          $runtimeLines[] = 'printf "%s\n" "[JobSeeker] '.($execution['scriptType'] === 'talend' ? 'Talend execution' : 'Shell execution').'"';
          $runtimeLines[] = 'sh '.escapeshellarg($execution['scriptPath']).($argumentString !== '' ? ' '.$argumentString : '');
          return implode("\n", $runtimeLines);
        }

        $dockerScript = implode("\n", array(
          'set -e',
          $this->dockerScriptPathLine(),
          'mkdir -p /tmp/jobseeker-context',
          'tar -C /tmp/jobseeker-context -xf -',
          'cd /tmp/jobseeker-context/source',
          'if [ -n "${JAVA_HOME:-}" ] && [ -d "$JAVA_HOME/bin" ]; then export PATH="$JAVA_HOME/bin:$PATH"; fi',
          'if [ -d /opt/java/openjdk/bin ]; then export PATH="/opt/java/openjdk/bin:$PATH"; fi',
          'sh "$JOBSEEKER_ENTRYPOINT" "$@"'
        ));

        $lines = array_merge(array('set -e'), $runtimeLines);
        $lines[] = 'printf "%s\n" "[JobSeeker] Docker runtime setup"';
        $lines[] = 'export JOBSEEKER_LINUX_RUNTIME=\'docker\'';
        $lines[] = 'export JOBSEEKER_LINUX_SCRIPT_TYPE='.escapeshellarg($execution['scriptType']);
        $lines[] = 'export JOBSEEKER_SOURCE_DIR='.escapeshellarg($execution['sourceDirectory']);
        $lines[] = 'export JOBSEEKER_SCRIPT_PATH='.escapeshellarg($execution['scriptPath']);
        $lines[] = 'export JOBSEEKER_DOCKER_IMAGE='.escapeshellarg($dockerImage);
        $lines = array_merge($lines, $this->dockerJobResourceLines($runtimeOptions));
        $lines[] = 'command -v docker >/dev/null || { echo "Docker runtime selected, but docker is not available on this Jenkins agent."; exit 127; }';
        $lines = array_merge($lines, $this->dockerJobIdentityLines($execution['scriptType'] === 'talend' ? 'talend' : 'linux-shell'));
        $lines[] = 'JOBSEEKER_DOCKER_ENTRYPOINT="${JOBSEEKER_SCRIPT_PATH#$JOBSEEKER_SOURCE_DIR/}"';
        $lines[] = 'if [ "$JOBSEEKER_DOCKER_ENTRYPOINT" = "$JOBSEEKER_SCRIPT_PATH" ]; then JOBSEEKER_DOCKER_ENTRYPOINT="$(basename "$JOBSEEKER_SCRIPT_PATH")"; fi';
        $lines[] = 'JOBSEEKER_DOCKER_CONTEXT="$WORKSPACE/jobseeker-linux-docker-context"';
        $lines[] = 'rm -rf "$JOBSEEKER_DOCKER_CONTEXT"';
        $lines[] = 'mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/source"';
        $lines[] = 'cp -R "$JOBSEEKER_SOURCE_DIR/." "$JOBSEEKER_DOCKER_CONTEXT/source/"';
        $lines[] = 'find "$JOBSEEKER_DOCKER_CONTEXT/source" -type d \( -name .git -o -name .venv -o -name venv -o -name __pycache__ -o -name .pytest_cache -o -name .mypy_cache -o -name .ruff_cache \) -prune -exec rm -rf {} +';
        $lines[] = 'mkdir -p "$JOBSEEKER_REPOSITORY_ROOT/data-assets"';
        $lines[] = 'JOBSEEKER_DATA_ASSETS_VOLUME="$(printf "jobseeker-assets-%s-%s" "${JOB_NAME:-job}" "${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"';
        $lines[] = 'docker volume create "$JOBSEEKER_DATA_ASSETS_VOLUME" >/dev/null';
        $lines = array_merge($lines, $this->dockerConnectorSetupLines($dockerImage));
        $lines[] = 'jobseeker_linux_docker_cleanup() { rm -rf "$JOBSEEKER_DOCKER_CONTEXT" "$JOBSEEKER_CONNECTORS_DIR"; docker volume rm "$JOBSEEKER_CONNECTORS_VOLUME" >/dev/null 2>&1 || true; docker volume rm "$JOBSEEKER_DATA_ASSETS_VOLUME" >/dev/null 2>&1 || true; }';
        $lines[] = 'trap jobseeker_linux_docker_cleanup EXIT';
        $lines[] = 'tar -C "$JOBSEEKER_REPOSITORY_ROOT" -cf - data-assets | docker run --rm -i --user 0 --entrypoint sh -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" "$JOBSEEKER_DOCKER_IMAGE" -c "cd /jobseeker-repository && tar -xf - && chmod -R a+rwX data-assets"';
        $lines[] = 'printf "%s\n" "[JobSeeker] Docker container execution"';
        $lines[] = 'JOBSEEKER_DOCKER_STATUS=0';
        $lines[] = 'tar -C "$JOBSEEKER_DOCKER_CONTEXT" -cf - . | docker run --rm -i \\';
        $lines = array_merge($lines, $this->dockerJobRunIdentityOptions());
        $lines[] = '  --network host \\';
        $lines[] = '  -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" \\';
        $lines[] = '  -v "$JOBSEEKER_CONNECTORS_VOLUME:/run/jobseeker-connectors:ro" \\';
        $lines[] = '  -e "JOBSEEKER_ENTRYPOINT=$JOBSEEKER_DOCKER_ENTRYPOINT" \\';
        $lines[] = '  -e JOBSEEKER_REPOSITORY_ROOT=/jobseeker-repository \\';
        $lines[] = '  -e JOBSEEKER_DATA_ASSETS_MANIFEST=/jobseeker-repository/data-assets/manifest.json \\';
        $lines[] = '  -e JOBSEEKER_CONNECTORS_DIR=/run/jobseeker-connectors \\';
        $lines[] = '  -e JOBSEEKER_CONNECTOR_HELPER=/run/jobseeker-connectors/jobseeker-connector \\';
        $lines[] = '  -e JOBSEEKER_ENVIRONMENT -e JOBSEEKER_JOB_NAME -e JOBSEEKER_DATA_ASSET_JOB \\';
        $lines = array_merge($lines, $this->dockerContextEnvLines());
        $lines[] = '  -e JOB_NAME -e BUILD_NUMBER -e BUILD_ID -e JOBSEEKER_CONTAINER_NAME \\';
        $lines[] = '  "$JOBSEEKER_DOCKER_IMAGE" \\';
        $lines[] = '  '.$this->dockerLoginShell().' '.escapeshellarg($dockerScript).' sh'.($argumentString !== '' ? ' '.$argumentString : '').' || JOBSEEKER_DOCKER_STATUS=$?';
        $lines[] = 'printf "%s\n" "[JobSeeker] Cleanup"';
        $lines[] = 'docker run --rm --user 0 --entrypoint sh -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" "$JOBSEEKER_DOCKER_IMAGE" -c \'rm -f /jobseeker-repository/data-assets/manifest.json; tar -C /jobseeker-repository -cf - data-assets\' | tar -C "$JOBSEEKER_REPOSITORY_ROOT" -xf -';
        $lines[] = 'if [ "$JOBSEEKER_DOCKER_STATUS" -ne 0 ]; then exit "$JOBSEEKER_DOCKER_STATUS"; fi';

        return implode("\n", $lines);
      }

      /**
       * Environment the task DAG runtime reads.
       *
       * Forwarded from the build rather than baked into the job so a re-run can
       * resume a previous run, narrow itself to a few tasks, or turn off state
       * recording, by setting a Jenkins build parameter - without the job having
       * to be regenerated. Defaults match jobseeker.dag's own defaults so a job
       * behaves the same whether or not the parameters are present.
       */
      private function dagRuntimeLines() {
        return array(
          'export JOBSEEKER_DAG_RESUME="${JOBSEEKER_DAG_RESUME:-}"',
          'export JOBSEEKER_DAG_TASKS="${JOBSEEKER_DAG_TASKS:-}"',
          'export JOBSEEKER_DAG_MAX_PARALLEL="${JOBSEEKER_DAG_MAX_PARALLEL:-4}"',
          'export JOBSEEKER_DAG_FAIL_FAST="${JOBSEEKER_DAG_FAIL_FAST:-0}"',
          'export JOBSEEKER_DAG_STATE="${JOBSEEKER_DAG_STATE:-1}"'
        );
      }

      /**
       * A project-bound Git job asks JobSeeker, as the build starts, which
       * repository, branch and credential its project uses in this
       * environment. The values saved here are only a snapshot for the form
       * and the dependency scanner; a non-empty branch is a deliberate pin.
       * Runs before connectorRuntimeLines(), which clears the worker token.
       */
      private function projectGitSourceLines($execution) {
        return array(
          'printf "%s\n" "[JobSeeker] Git source from project "'.escapeshellarg(isset($execution['projectName']) ? $execution['projectName'] : ''),
          'export JOBSEEKER_PROJECT_ID='.escapeshellarg((string) (int) $execution['projectId']),
          'export JOBSEEKER_PROJECT_NAME='.escapeshellarg(isset($execution['projectName']) ? $execution['projectName'] : ''),
          'export JOBSEEKER_GIT_FOLLOW_PROJECT=1',
          'export JOBSEEKER_GIT_REPOSITORY_URL='.escapeshellarg($execution['repositoryUrl']),
          'export JOBSEEKER_GIT_REPOSITORY_BRANCH='.escapeshellarg($execution['branch']),
          'export JOBSEEKER_GIT_CREDENTIAL_KEY='.escapeshellarg(isset($execution['credentialKey']) ? $execution['credentialKey'] : ''),
          'JOBSEEKER_GIT_SOURCE="$(PYTHONPATH="$JOBSEEKER_REPOSITORY_ROOT/python/lib/jobseeker-sdk/src${PYTHONPATH:+:$PYTHONPATH}" python3 -c \'from jobseeker import connector_cli; connector_cli()\' git-source --project "$JOBSEEKER_PROJECT_ID" --environment "${JOBSEEKER_ENVIRONMENT:-LOCAL}")" || { echo "JobSeeker could not resolve the Git repository of project $JOBSEEKER_PROJECT_NAME for ${JOBSEEKER_ENVIRONMENT:-LOCAL}." >&2; exit 78; }',
          'JOBSEEKER_GIT_REPOSITORY_URL="$(printf "%s\n" "$JOBSEEKER_GIT_SOURCE" | sed -n 1p)"',
          'if [ -z "$JOBSEEKER_GIT_REPOSITORY_BRANCH" ]; then JOBSEEKER_GIT_REPOSITORY_BRANCH="$(printf "%s\n" "$JOBSEEKER_GIT_SOURCE" | sed -n 2p)"; JOBSEEKER_GIT_BRANCH_SOURCE=project; else JOBSEEKER_GIT_BRANCH_SOURCE=pinned; fi',
          'JOBSEEKER_GIT_CREDENTIAL_KEY="$(printf "%s\n" "$JOBSEEKER_GIT_SOURCE" | sed -n 3p)"',
          'unset JOBSEEKER_GIT_SOURCE',
          'printf "%s\n" "[JobSeeker] ${JOBSEEKER_ENVIRONMENT:-LOCAL} runs $JOBSEEKER_GIT_REPOSITORY_BRANCH ($JOBSEEKER_GIT_BRANCH_SOURCE) from $JOBSEEKER_GIT_REPOSITORY_URL${JOBSEEKER_GIT_CREDENTIAL_KEY:+ with $JOBSEEKER_GIT_CREDENTIAL_KEY}"'
        );
      }

      /**
       * Samples and edits in the job's VS Code working copy reach a build only
       * once they are pushed, so a missing entry file says where it was looked for.
       */
      private function gitEntryPointCheckLine() {
        return '[ -f "$JOBSEEKER_SCRIPT_PATH" ] || { echo "Python entry point $JOBSEEKER_ENTRYPOINT is not on ${JOBSEEKER_GIT_REPOSITORY_BRANCH:-the default branch} of $JOBSEEKER_GIT_REPOSITORY_URL${JOBSEEKER_GIT_JOB_PATH:+ in $JOBSEEKER_GIT_JOB_PATH}. Commit and push it from the job\'s Git workspace in VS Code, or correct the entry file." >&2; exit 66; }';
      }

      /**
       * Clones the job's repository into $WORKSPACE/jobseeker-python-source
       * and points JOBSEEKER_SOURCE_DIR at the job's folder in it.
       *
       * A repository can hold many jobs, one folder each (jobs/<job>). Such a
       * job clones sparsely and without file contents outside its folder and
       * shared/, so a build downloads only what it runs and never sees another
       * job's files. Workers whose jobseeker-git predates sparse clones fall
       * back to a full shallow clone of the branch. A job without a folder
       * runs from the repository root, as before.
       *
       * Expects JOBSEEKER_GIT_REPOSITORY_URL, _BRANCH and _CREDENTIAL_KEY.
       */
      private function gitSourceCheckoutLines($execution, $withBranch) {
        $jobPath = isset($execution['jobPath']) ? (string) $execution['jobPath'] : '';
        $target = '"$WORKSPACE/jobseeker-python-source"';
        $branchOption = $withBranch ? ' --branch "$JOBSEEKER_GIT_REPOSITORY_BRANCH"' : '';
        $publicFailure = ' || { echo "Git could not clone $JOBSEEKER_GIT_REPOSITORY_URL without a credential. A private repository needs a build credential: choose a Git connector under Build access (builds never use a personal Git account)." >&2; exit 128; }';
        $lines = array('rm -rf '.$target);
        if ($jobPath !== '') {
          $lines[] = 'export JOBSEEKER_GIT_JOB_PATH='.escapeshellarg($jobPath);
          // shared/ holds the code a project's jobs have in common.
          $lines[] = 'JOBSEEKER_GIT_SPARSE_PATHS="$JOBSEEKER_GIT_JOB_PATH shared"';
        }
        $sparseProbe = $jobPath !== '' ? ' JOBSEEKER_GIT_SPARSE_ARGS=""; if jobseeker-git features 2>/dev/null | grep -qx sparse; then for JOBSEEKER_GIT_SPARSE_PATH in $JOBSEEKER_GIT_SPARSE_PATHS; do JOBSEEKER_GIT_SPARSE_ARGS="$JOBSEEKER_GIT_SPARSE_ARGS --sparse $JOBSEEKER_GIT_SPARSE_PATH"; done; else echo "[JobSeeker] This worker\'s jobseeker-git cannot clone one folder; cloning the whole branch."; fi;' : '';
        $credentialClone = ' command -v jobseeker-git >/dev/null || { echo "The secure JobSeeker Git helper is not installed on this Jenkins worker." >&2; exit 127; };'
          .$sparseProbe
          .' JOBSEEKER_CONNECTOR_KEY="$JOBSEEKER_GIT_CREDENTIAL_KEY" jobseeker-git clone'.($jobPath !== '' ? ' $JOBSEEKER_GIT_SPARSE_ARGS' : '').' --connector-dir "$JOBSEEKER_CONNECTORS_DIR/$JOBSEEKER_GIT_CREDENTIAL_KEY"'.$branchOption.' -- "$JOBSEEKER_GIT_REPOSITORY_URL" '.$target.';';
        $publicClone = $jobPath !== ''
          ? ' git clone --depth 1 --filter=blob:none --sparse'.$branchOption.' -- "$JOBSEEKER_GIT_REPOSITORY_URL" '.$target.$publicFailure.';'
            // The paths are validated folder names, safe to split on spaces.
            .' git -C '.$target.' sparse-checkout set --cone -- $JOBSEEKER_GIT_SPARSE_PATHS;'
          : ' git clone --depth 1'.$branchOption.' -- "$JOBSEEKER_GIT_REPOSITORY_URL" '.$target.$publicFailure.';';
        $lines[] = 'if [ -n "$JOBSEEKER_GIT_CREDENTIAL_KEY" ]; then'.$credentialClone.' else'.$publicClone.' fi';
        $lines[] = 'export JOBSEEKER_GIT_ROOT='.$target;
        if ($jobPath !== '') {
          $lines[] = 'export JOBSEEKER_PROJECT_ROOT="$JOBSEEKER_GIT_ROOT"';
          $lines[] = 'export JOBSEEKER_SOURCE_DIR="$JOBSEEKER_GIT_ROOT/$JOBSEEKER_GIT_JOB_PATH"';
          $lines[] = 'printf "%s\n" "[JobSeeker] Job folder $JOBSEEKER_GIT_JOB_PATH"';
          $lines[] = '[ -d "$JOBSEEKER_SOURCE_DIR" ] || { echo "The job folder $JOBSEEKER_GIT_JOB_PATH is not on ${JOBSEEKER_GIT_REPOSITORY_BRANCH:-the default branch} of $JOBSEEKER_GIT_REPOSITORY_URL. Commit and push it from your project workspace in VS Code, or correct the job folder." >&2; exit 66; }';
        } else {
          $lines[] = 'export JOBSEEKER_SOURCE_DIR="$JOBSEEKER_GIT_ROOT"';
        }
        $lines[] = 'export JOBSEEKER_ENTRYPOINT='.escapeshellarg($execution['entryPoint']);
        $lines[] = 'export JOBSEEKER_SCRIPT_PATH="$JOBSEEKER_SOURCE_DIR/$JOBSEEKER_ENTRYPOINT"';
        $lines[] = $this->gitEntryPointCheckLine();
        return $lines;
      }

      /**
       * The Docker runtime installs a project from pyproject.toml, so the
       * agent must too, or a job that `uv add`ed a package breaks when it moves
       * to the Jenkins Agent. requirements.txt still wins when there is one; the
       * JobSeeker SDK is always installed on its own.
       */
      /** Python that prints a pyproject.toml's [project] dependencies, one per line, without the SDK. */
      private function pyprojectDependencyReader() {
        return 'import re, sys, tomllib'."\n"
          .'deps = tomllib.load(open(sys.argv[1], "rb")).get("project", {}).get("dependencies", [])'."\n"
          .'sys.stdout.write("".join(d.strip() + "\n" for d in deps if re.split(r"[\s\[<>=!~;@(]", d.strip(), maxsplit=1)[0].lower().replace("_", "-") != "jobseeker-runtime"))';
      }

      private function agentPyprojectRequirementsLines() {
        $reader = $this->pyprojectDependencyReader();
        $read = '"$JOBSEEKER_PYTHON" -c '.escapeshellarg($reader).' "$JOBSEEKER_PYPROJECT" > "$JOBSEEKER_PYPROJECT_REQUIREMENTS" 2>/dev/null'
          .' || python3 -c '.escapeshellarg($reader).' "$JOBSEEKER_PYPROJECT" > "$JOBSEEKER_PYPROJECT_REQUIREMENTS" 2>/dev/null';
        return array(
          'JOBSEEKER_PYPROJECT=""',
          'if [ -z "$JOBSEEKER_REQUIREMENTS" ] && [ -f "$JOBSEEKER_SOURCE_DIR/pyproject.toml" ]; then JOBSEEKER_PYPROJECT="$JOBSEEKER_SOURCE_DIR/pyproject.toml"; fi',
          'if [ -z "$JOBSEEKER_REQUIREMENTS" ] && [ -f "$JOBSEEKER_SCRIPT_DIR/pyproject.toml" ]; then JOBSEEKER_PYPROJECT="$JOBSEEKER_SCRIPT_DIR/pyproject.toml"; fi',
          'if [ -n "$JOBSEEKER_PYPROJECT" ]; then',
          '  JOBSEEKER_PYPROJECT_REQUIREMENTS="$WORKSPACE/.jobseeker-pyproject-requirements.txt"',
          '  if '.$read.'; then',
          '    if [ -s "$JOBSEEKER_PYPROJECT_REQUIREMENTS" ]; then echo "No requirements.txt; installing the dependencies listed in pyproject.toml."; JOBSEEKER_REQUIREMENTS="$JOBSEEKER_PYPROJECT_REQUIREMENTS"; fi',
          '  else',
          '    echo "The dependencies in pyproject.toml could not be read (invalid TOML, or no Python 3.11+ on this agent); they are not installed." >&2',
          '  fi',
          'fi'
        );
      }

      /** Whether a Python job's entry file is a Jupyter notebook. */
      private function isNotebookExecution($execution) {
        $entry = isset($execution['entryPoint']) && $execution['entryPoint'] !== '' ? $execution['entryPoint'] : (isset($execution['scriptPath']) ? $execution['scriptPath'] : '');
        return strtolower(pathinfo((string) $entry, PATHINFO_EXTENSION)) === 'ipynb';
      }

      /**
       * Settings of a notebook run, exported where Edit mode reads them back,
       * and the folder its executed notebook is published in:
       * repository/notebook-runs/<job>/<build>/, the last 20 builds per job.
       * JOBSEEKER_NOTEBOOK_PARAMETERS is the job's Jenkins parameter, so one
       * run can override values (a JSON object) without editing the job.
       */
      private function notebookRuntimeLines($runtimeOptions) {
        $notebook = isset($runtimeOptions['notebook']) && is_array($runtimeOptions['notebook']) ? $runtimeOptions['notebook'] : array();
        $parameters = isset($notebook['parameters']) && is_array($notebook['parameters']) ? $notebook['parameters'] : array();
        $lines = array('export JOBSEEKER_NOTEBOOK=1');
        if (! empty($parameters)) {
          $lines[] = 'export JOBSEEKER_NOTEBOOK_SPEC='.escapeshellarg('b64:'.base64_encode(json_encode(array_values($parameters), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        }
        $lines[] = 'export JOBSEEKER_NOTEBOOK_CELL_TIMEOUT='.escapeshellarg((string) (isset($notebook['cellTimeout']) ? (int) $notebook['cellTimeout'] : 0));
        $lines[] = 'export JOBSEEKER_NOTEBOOK_ALLOW_ERRORS='.escapeshellarg(! empty($notebook['allowErrors']) ? '1' : '0');
        $lines[] = 'export JOBSEEKER_NOTEBOOK_TMF='.escapeshellarg(! isset($notebook['track']) || $notebook['track'] ? '1' : '0');
        $lines[] = 'export JOBSEEKER_NOTEBOOK_PARAMETERS="${JOBSEEKER_NOTEBOOK_PARAMETERS:-}"';
        $lines[] = 'JOBSEEKER_NOTEBOOK_SLUG="$(printf "%s" "${JOB_NAME:-${JOBSEEKER_JOB_NAME:-job}}" | tr "/ " "--" | tr -cd "A-Za-z0-9_.-" | cut -c1-120)"';
        $lines[] = 'JOBSEEKER_NOTEBOOK_RUNS="$JOBSEEKER_REPOSITORY_ROOT/notebook-runs/${JOBSEEKER_NOTEBOOK_SLUG:-job}"';
        $lines[] = 'export JOBSEEKER_NOTEBOOK_RUN_DIR="$JOBSEEKER_NOTEBOOK_RUNS/${BUILD_NUMBER:-0}"';
        $lines[] = 'export JOBSEEKER_NOTEBOOK_PUBLISHED="notebook-runs/${JOBSEEKER_NOTEBOOK_SLUG:-job}/${BUILD_NUMBER:-0}/$(basename "$JOBSEEKER_SCRIPT_PATH")"';
        // Group-readable, so the web app can serve it whichever user it runs as.
        $lines[] = 'rm -rf "$JOBSEEKER_NOTEBOOK_RUN_DIR"; (umask 027; mkdir -p "$JOBSEEKER_NOTEBOOK_RUN_DIR")';
        $lines[] = 'ls -1 "$JOBSEEKER_NOTEBOOK_RUNS" | grep -E "^[0-9]+$" | sort -rn | tail -n +21 | while read -r JOBSEEKER_OLD_RUN; do rm -rf "$JOBSEEKER_NOTEBOOK_RUNS/$JOBSEEKER_OLD_RUN"; done';
        return $lines;
      }

      /**
       * Runs the notebook with the SDK's runner (jobseeker.notebook), after
       * installing nbclient and ipykernel when the environment lacks them;
       * they go after the job's own packages on PYTHONPATH. Without them the
       * runner still runs plain Python cells.
       */
      private function notebookRunnerLines($python, $librariesDirectory, $notebook, $output) {
        $check = ' -c "import nbclient, nbformat, ipykernel" >/dev/null 2>&1';
        return array(
          'printf "%s\n" "[JobSeeker] Notebook execution"',
          'if ! '.$python.$check.'; then',
          // An agent's workspace keeps the last install between builds.
          '  if [ -d '.$librariesDirectory.' ] && PYTHONPATH="${PYTHONPATH:+$PYTHONPATH:}"'.$librariesDirectory.' '.$python.$check.'; then',
          '    export PYTHONPATH="${PYTHONPATH:+$PYTHONPATH:}"'.$librariesDirectory,
          '  else',
          '    echo "[JobSeeker] No Jupyter kernel in this environment: installing nbclient and ipykernel for this run. A runtime with Jupyter, or ipykernel in the job\'s dependencies, skips this step."',
          '    rm -rf '.$librariesDirectory,
          '    if PIP_ROOT_USER_ACTION=ignore '.$python.' -m pip install --quiet --disable-pip-version-check --target '.$librariesDirectory.' "nbclient>=0.10" "nbformat>=5.10" "ipykernel>=6.29"; then export PYTHONPATH="${PYTHONPATH:+$PYTHONPATH:}"'.$librariesDirectory.'; else echo "[JobSeeker] nbclient and ipykernel could not be installed; the notebook runs without a Jupyter kernel."; fi',
          '  fi',
          'fi',
          $python.' -u -m jobseeker.notebook run '.$notebook.' --output '.$output
        );
      }

      private function buildPythonExecutionCommand($execution, $repositoryRoot, $environmentArgument, $runtimeOptions = array()) {
        $pythonLibraryPath = rtrim($repositoryRoot, '/\\').'/python/lib';
        $runtimeMode = isset($runtimeOptions['mode']) ? $runtimeOptions['mode'] : 'local';
        $pythonExecutable = isset($runtimeOptions['pythonExecutable']) ? $runtimeOptions['pythonExecutable'] : 'python3';
        $dockerImage = isset($runtimeOptions['dockerImage']) ? $runtimeOptions['dockerImage'] : $this->defaultPythonDockerImage();
        $requirementsText = isset($runtimeOptions['requirementsText']) ? (string) $runtimeOptions['requirementsText'] : '';
        $pyprojectText = isset($runtimeOptions['pyprojectText']) ? (string) $runtimeOptions['pyprojectText'] : '';
        $dockerfileText = isset($runtimeOptions['dockerfileText']) ? (string) $runtimeOptions['dockerfileText'] : '';
        $runTests = ! isset($runtimeOptions['runTests']) || (bool) $runtimeOptions['runTests'];
        $followsProject = $execution['mode'] === 'git' && ! empty($execution['followProject']) && ! empty($execution['projectId']);
        $notebook = $this->isNotebookExecution($execution);
        $lines = array_merge(
          array('set -e'),
          $this->dataAssetsRuntimeLines($repositoryRoot),
          $this->environmentContextLines(),
          $followsProject ? $this->projectGitSourceLines($execution) : array(),
          $this->connectorRuntimeLines(),
          $this->dagRuntimeLines()
        );

        if ($followsProject) {
          $lines = array_merge($lines, $this->gitSourceCheckoutLines($execution, TRUE));
        } else if ($execution['mode'] === 'git') {
          $lines[] = 'printf "%s\n" "[JobSeeker] Git source checkout"';
          $lines[] = 'export JOBSEEKER_GIT_REPOSITORY_URL='.escapeshellarg($execution['repositoryUrl']);
          $lines[] = 'export JOBSEEKER_GIT_REPOSITORY_BRANCH='.escapeshellarg($execution['branch']);
          $lines[] = 'export JOBSEEKER_GIT_CREDENTIAL_KEY='.escapeshellarg(isset($execution['credentialKey']) ? $execution['credentialKey'] : '');
          $lines[] = 'export JOBSEEKER_PROJECT_ID='.escapeshellarg(isset($execution['projectId']) ? (string) $execution['projectId'] : '');
          $lines[] = 'export JOBSEEKER_PROJECT_NAME='.escapeshellarg(isset($execution['projectName']) ? $execution['projectName'] : '');
          if (empty($execution['jobPath'])) {
            // Unchanged for single-job repositories, whose saved configs and
            // promotions match these lines.
            $lines[] = 'rm -rf "$WORKSPACE/jobseeker-python-source"';
            if (! empty($execution['credentialKey'])) {
              $lines[] = 'command -v jobseeker-git >/dev/null || { echo "The secure JobSeeker Git helper is not installed on this Jenkins worker." >&2; exit 127; }';
              $cloneCommand = 'JOBSEEKER_CONNECTOR_KEY='.escapeshellarg($execution['credentialKey']).' jobseeker-git clone --connector-dir "$JOBSEEKER_CONNECTORS_DIR/$JOBSEEKER_GIT_CREDENTIAL_KEY"';
            } else {
              $cloneCommand = 'git clone --depth 1';
            }
            if ($execution['branch'] !== '') {
              $cloneCommand .= ' --branch "$JOBSEEKER_GIT_REPOSITORY_BRANCH"';
            }
            $cloneCommand .= ' -- "$JOBSEEKER_GIT_REPOSITORY_URL" "$WORKSPACE/jobseeker-python-source"';
            if (empty($execution['credentialKey'])) {
              $cloneCommand .= ' || { echo "Git could not clone $JOBSEEKER_GIT_REPOSITORY_URL without a credential. A private repository needs a build credential: choose a Git connector under Build access (builds never use a personal Git account)." >&2; exit 128; }';
            }
            $lines[] = $cloneCommand;
            $lines[] = 'export JOBSEEKER_SOURCE_DIR="$WORKSPACE/jobseeker-python-source"';
            $lines[] = 'export JOBSEEKER_ENTRYPOINT='.escapeshellarg($execution['entryPoint']);
            $lines[] = 'export JOBSEEKER_SCRIPT_PATH="$JOBSEEKER_SOURCE_DIR/$JOBSEEKER_ENTRYPOINT"';
            $lines[] = $this->gitEntryPointCheckLine();
          } else {
            $lines = array_merge($lines, $this->gitSourceCheckoutLines($execution, $execution['branch'] !== ''));
          }
        } else {
          $lines[] = 'export JOBSEEKER_SOURCE_DIR='.escapeshellarg($execution['sourceDirectory']);
          $lines[] = 'export JOBSEEKER_SCRIPT_PATH='.escapeshellarg($execution['scriptPath']);
          // A job folder of a project without Git runs in place; its
          // project's shared/ code is importable, as it is in the editor.
          if (preg_match('#^(.+/workspaces/shared/[a-z0-9._-]+)/jobs/[^/]+/?$#', str_replace('\\', '/', (string) $execution['sourceDirectory']), $projectMatch)) {
            $lines[] = 'export JOBSEEKER_PROJECT_ROOT='.escapeshellarg($projectMatch[1]);
            // Context values are read for the job's own project first.
            if (! empty($execution['projectName'])) {
              $lines[] = 'export JOBSEEKER_PROJECT_ID='.escapeshellarg((string) (int) $execution['projectId']);
              $lines[] = 'export JOBSEEKER_PROJECT_NAME='.escapeshellarg($execution['projectName']);
            }
          }
        }

        $lines[] = 'export JOBSEEKER_PYTHON_LIB='.escapeshellarg($pythonLibraryPath);
        $lines[] = 'export JOBSEEKER_PYTHON_SDK="$JOBSEEKER_PYTHON_LIB/jobseeker-sdk"';
        $lines[] = 'export JOBSEEKER_RUNTIME_LIBS="$WORKSPACE/.jobseeker-runtime-libs"';
        $lines[] = 'export JOBSEEKER_VENV="$WORKSPACE/.venv"';
        $lines[] = 'jobseeker_python_cleanup() { rm -rf "$JOBSEEKER_CONNECTORS_DIR"; if [ -n "${JOBSEEKER_VENV:-}" ]; then rm -rf "$JOBSEEKER_VENV"; fi; }';
        $lines[] = 'trap jobseeker_python_cleanup EXIT';
        $lines[] = 'export JOBSEEKER_PYTHON_RUNTIME='.escapeshellarg($runtimeMode);
        $lines[] = 'export JOBSEEKER_RUN_PYTEST='.escapeshellarg($runTests ? '1' : '0');
        $lines[] = 'export JOBSEEKER_PYTHON='.escapeshellarg($pythonExecutable);
        $lines[] = 'export PYTHONUNBUFFERED=1';
        $lines[] = 'export JOBSEEKER_EMAIL_METRICS_FILE="$WORKSPACE/jobseeker-email-metrics.properties"';
        $lines[] = 'printf "%s\n" "dataset=Not reported" "rows_read=Not reported" "rows_written=Not reported" "rows_rejected=Not reported" "duration=Not reported" > "$JOBSEEKER_EMAIL_METRICS_FILE"';
        $lines[] = 'export JOBSEEKER_SCRIPT_DIR="$(dirname "$JOBSEEKER_SCRIPT_PATH")"';
        $lines[] = 'cd "$JOBSEEKER_SOURCE_DIR"';
        if ($notebook) {
          $lines = array_merge($lines, $this->notebookRuntimeLines($runtimeOptions));
        }

        if ($runtimeMode === 'docker') {
          $dockerScriptLines = array(
            'set -e',
            $this->dockerScriptPathLine(),
            'mkdir -p /tmp/jobseeker-context',
            'tar -C /tmp/jobseeker-context -xf -',
            'cd /tmp/jobseeker-context/source',
            'JOBSEEKER_SCRIPT_DIR="$(dirname "$JOBSEEKER_ENTRYPOINT")"',
            'rm -rf /tmp/jobseeker-runtime-libs',
            'PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet --disable-pip-version-check --target /tmp/jobseeker-runtime-libs /tmp/jobseeker-context/jobseeker-sdk',
            'JOBSEEKER_PROJECT_DIR=""',
            'JOBSEEKER_REQUIREMENTS=""',
            'JOBSEEKER_USER_LIBS=""',
            'if [ -f "/tmp/jobseeker-context/source/pyproject.toml" ]; then JOBSEEKER_PROJECT_DIR="/tmp/jobseeker-context/source"; fi',
            'if [ -f "/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR/pyproject.toml" ]; then JOBSEEKER_PROJECT_DIR="/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR"; fi',
            'if [ -f "/tmp/jobseeker-context/source/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="/tmp/jobseeker-context/source/requirements.txt"; fi',
            'if [ -f "/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR/requirements.txt"; fi',
            'if [ -n "$JOBSEEKER_PROJECT_DIR" ]; then',
            '  if [ "${JOBSEEKER_DEPENDENCIES_PREINSTALLED:-0}" != "1" ]; then',
            '    PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet --disable-pip-version-check "poetry==2.4.1"',
            '    if (cd "$JOBSEEKER_PROJECT_DIR" && export POETRY_VIRTUALENVS_CREATE=false && if [ -f poetry.lock ] && ! poetry check --lock --no-interaction >/dev/null 2>&1; then echo "poetry.lock does not match pyproject.toml; refreshing it for this run."; poetry lock --no-interaction --no-ansi; fi && poetry install --no-root --no-interaction --no-ansi) > /tmp/jobseeker-poetry.log 2>&1; then',
            '      cat /tmp/jobseeker-poetry.log',
            '    else',
            '      cat /tmp/jobseeker-poetry.log',
            // A requires-python that leaves out the image's Python (a job
            // written for another runtime) is what Poetry alone refuses; the
            // editor installs with pip, so the job does too, and says so.
            '      grep -q "is not supported by the project" /tmp/jobseeker-poetry.log || exit 1',
            '      echo "[JobSeeker] requires-python in pyproject.toml leaves out this image\'s Python $(python -c \'import platform; print(platform.python_version())\'), so Poetry refuses it. Installing the dependencies it lists with pip, as VS Code does. Set requires-python to include the runtime\'s Python to install with Poetry again."',
            '      python -c '.escapeshellarg($this->pyprojectDependencyReader()).' "$JOBSEEKER_PROJECT_DIR/pyproject.toml" > /tmp/jobseeker-pyproject-requirements.txt',
            '      if [ -s /tmp/jobseeker-pyproject-requirements.txt ]; then PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet --disable-pip-version-check -r /tmp/jobseeker-pyproject-requirements.txt; fi',
            '    fi',
            '  fi',
            'elif [ -n "$JOBSEEKER_REQUIREMENTS" ]; then',
            '  rm -rf /tmp/jobseeker-python-libs',
            '  PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet --disable-pip-version-check --target /tmp/jobseeker-python-libs -r "$JOBSEEKER_REQUIREMENTS"',
            '  JOBSEEKER_USER_LIBS="/tmp/jobseeker-python-libs"',
            'fi',
            'if [ -n "$JOBSEEKER_USER_LIBS" ]; then export PYTHONPATH="/tmp/jobseeker-runtime-libs:$JOBSEEKER_USER_LIBS:/tmp/jobseeker-context/source:/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"; else export PYTHONPATH="/tmp/jobseeker-runtime-libs:/tmp/jobseeker-context/source:/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"; fi',
            'if [ -d /tmp/jobseeker-context/project ]; then export PYTHONPATH="$PYTHONPATH:/tmp/jobseeker-context/project"; fi'
          );

          if ($runTests) {
            $dockerScriptLines = array_merge($dockerScriptLines, array(
              'JOBSEEKER_TEST_ROOT="/tmp/jobseeker-context/source"',
              'if [ -n "$JOBSEEKER_PROJECT_DIR" ]; then JOBSEEKER_TEST_ROOT="$JOBSEEKER_PROJECT_DIR"; elif [ -d "/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR/tests" ]; then JOBSEEKER_TEST_ROOT="/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR"; fi',
              'JOBSEEKER_TEST_FILE="$(find "$JOBSEEKER_TEST_ROOT" -type f \( -name "test_*.py" -o -name "*_test.py" \) -print -quit 2>/dev/null || true)"',
              'printf "%s\n" "[JobSeeker] Python tests"',
              'if [ -n "$JOBSEEKER_TEST_FILE" ]; then',
              '  if ! python -c "import pytest" >/dev/null 2>&1; then',
              '    rm -rf /tmp/jobseeker-pytest-libs',
              '    PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet --disable-pip-version-check --target /tmp/jobseeker-pytest-libs "pytest>=8,<10"',
              '    export PYTHONPATH="/tmp/jobseeker-pytest-libs:$PYTHONPATH"',
              '  fi',
              '  (cd "$JOBSEEKER_TEST_ROOT" && python -m pytest)',
              'else',
              '  echo "No pytest test files were found; continuing to Python execution."',
              'fi'
            ));
          }

          $dockerScriptLines = array_merge($dockerScriptLines, $notebook
            ? $this->notebookRunnerLines('python', '/tmp/jobseeker-notebook-libs', '"$JOBSEEKER_ENTRYPOINT"', '"/jobseeker-notebook/$(basename "$JOBSEEKER_ENTRYPOINT")"')
            : array(
              'printf "%s\n" "[JobSeeker] Python execution"',
              'python -u "$JOBSEEKER_ENTRYPOINT" "$@"'
            ));
          $dockerScript = implode("\n", $dockerScriptLines);

          $lines[] = 'export JOBSEEKER_DOCKER_IMAGE='.escapeshellarg($dockerImage);
          $lines = array_merge($lines, $this->dockerJobResourceLines($runtimeOptions));
          $lines[] = 'printf "%s\n" "[JobSeeker] Docker runtime setup"';
          $lines[] = 'echo "Preparing Python Docker build context..."';
          $lines[] = 'JOBSEEKER_RESTORE_XTRACE=0; case "$-" in *x*) JOBSEEKER_RESTORE_XTRACE=1; set +x ;; esac';
          if (trim($requirementsText) !== '') {
            $lines[] = 'export JOBSEEKER_PYTHON_REQUIREMENTS_B64='.escapeshellarg(base64_encode($requirementsText));
          }
          if (trim($pyprojectText) !== '') {
            $lines[] = 'export JOBSEEKER_PYPROJECT_B64='.escapeshellarg(base64_encode($pyprojectText));
          }
          if (trim($dockerfileText) !== '') {
            $lines[] = 'export JOBSEEKER_PYTHON_DOCKERFILE_B64='.escapeshellarg(base64_encode($dockerfileText));
          }
          $lines[] = 'command -v docker >/dev/null || { echo "Docker runtime selected, but docker is not available on this Jenkins agent."; exit 127; }';
          $lines = array_merge($lines, $this->dockerJobIdentityLines('python'));
          $lines[] = 'JOBSEEKER_DOCKER_ENTRYPOINT="${JOBSEEKER_SCRIPT_PATH#$JOBSEEKER_SOURCE_DIR/}"';
          $lines[] = 'JOBSEEKER_DOCKER_CONTEXT="$WORKSPACE/jobseeker-python-docker-context"';
          $lines[] = 'JOBSEEKER_DOCKER_BUILT_IMAGE=""';
          $lines[] = 'JOBSEEKER_EMAIL_METRICS_VOLUME=""';
          $lines[] = 'JOBSEEKER_DATA_ASSETS_VOLUME=""';
          $lines[] = 'JOBSEEKER_CONNECTORS_VOLUME=""';
          if ($notebook) {
            $lines[] = 'JOBSEEKER_NOTEBOOK_VOLUME=""';
          }
          $lines[] = 'jobseeker_python_docker_cleanup() { rm -rf "$JOBSEEKER_DOCKER_CONTEXT" "$JOBSEEKER_CONNECTORS_DIR"; '.($notebook ? 'if [ -n "$JOBSEEKER_NOTEBOOK_VOLUME" ]; then docker volume rm "$JOBSEEKER_NOTEBOOK_VOLUME" >/dev/null 2>&1 || true; fi; ' : '').'if [ -n "$JOBSEEKER_CONNECTORS_VOLUME" ]; then docker volume rm "$JOBSEEKER_CONNECTORS_VOLUME" >/dev/null 2>&1 || true; fi; if [ -n "$JOBSEEKER_EMAIL_METRICS_VOLUME" ]; then docker volume rm "$JOBSEEKER_EMAIL_METRICS_VOLUME" >/dev/null 2>&1 || true; fi; if [ -n "$JOBSEEKER_DATA_ASSETS_VOLUME" ]; then docker volume rm "$JOBSEEKER_DATA_ASSETS_VOLUME" >/dev/null 2>&1 || true; fi; if [ -n "$JOBSEEKER_DOCKER_BUILT_IMAGE" ]; then docker image rm "$JOBSEEKER_DOCKER_BUILT_IMAGE" >/dev/null 2>&1 || true; fi; }';
          $lines[] = 'trap jobseeker_python_docker_cleanup EXIT';
          $lines[] = 'rm -rf "$JOBSEEKER_DOCKER_CONTEXT"';
          $lines[] = 'mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/source" "$JOBSEEKER_DOCKER_CONTEXT/jobseeker-sdk"';
          $lines[] = 'cp -R "$JOBSEEKER_SOURCE_DIR/." "$JOBSEEKER_DOCKER_CONTEXT/source/"';
          // Editor virtual environments and caches are local development
          // state. Never stream them into the disposable Jenkins container.
          $lines[] = 'find "$JOBSEEKER_DOCKER_CONTEXT/source" -type d \( -name .git -o -name .venv -o -name venv -o -name __pycache__ -o -name .pytest_cache -o -name .mypy_cache -o -name .ruff_cache \) -prune -exec rm -rf {} +';
          // The editor's connector session stays in the editor.
          $lines[] = 'find "$JOBSEEKER_DOCKER_CONTEXT/source" -type f -name .env.jobseeker -delete';
          // A job folder's project code in shared/ travels with it and is
          // importable in the container as it is on the agent (`shared.x`).
          $lines[] = 'if [ -n "${JOBSEEKER_PROJECT_ROOT:-}" ] && [ -d "$JOBSEEKER_PROJECT_ROOT/shared" ]; then mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/project" && cp -R "$JOBSEEKER_PROJECT_ROOT/shared" "$JOBSEEKER_DOCKER_CONTEXT/project/shared" && find "$JOBSEEKER_DOCKER_CONTEXT/project" -type d \\( -name .git -o -name __pycache__ \\) -prune -exec rm -rf {} +; fi';
          $lines[] = 'cp -R "$JOBSEEKER_PYTHON_SDK/." "$JOBSEEKER_DOCKER_CONTEXT/jobseeker-sdk/"';
          $lines[] = 'JOBSEEKER_DOCKER_SCRIPT_DIR="$(dirname "$JOBSEEKER_DOCKER_ENTRYPOINT")"';
          // The copied workspace is authoritative. Embedded values support
          // legacy/path sources only when the corresponding live project file
          // is absent; they must not split pyproject.toml from poetry.lock.
          $lines[] = 'if [ -n "${JOBSEEKER_PYTHON_REQUIREMENTS_B64:-}" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/requirements.txt" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/pyproject.toml" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/pyproject.toml" ]; then mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR"; printf "%s" "$JOBSEEKER_PYTHON_REQUIREMENTS_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt"; fi';
          $lines[] = 'if [ -n "${JOBSEEKER_PYPROJECT_B64:-}" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/pyproject.toml" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/pyproject.toml" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/requirements.txt" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/requirements.txt" ]; then printf "%s" "$JOBSEEKER_PYPROJECT_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/pyproject.toml"; fi';
          $lines[] = 'if [ -n "${JOBSEEKER_PYTHON_DOCKERFILE_B64:-}" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile" ] && [ ! -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/Dockerfile" ]; then printf "%s" "$JOBSEEKER_PYTHON_DOCKERFILE_B64" | base64 -d > "$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile"; fi';
          $lines[] = 'if [ "$JOBSEEKER_RESTORE_XTRACE" = "1" ]; then set -x; fi';
          $lines[] = 'JOBSEEKER_DOCKERFILE=""';
          $lines[] = 'if [ -f "$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile" ]; then JOBSEEKER_DOCKERFILE="$JOBSEEKER_DOCKER_CONTEXT/source/Dockerfile"; fi';
          $lines[] = 'if [ -f "$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/Dockerfile" ]; then JOBSEEKER_DOCKERFILE="$JOBSEEKER_DOCKER_CONTEXT/source/$JOBSEEKER_DOCKER_SCRIPT_DIR/Dockerfile"; fi';
          $lines[] = 'JOBSEEKER_DOCKER_RUN_IMAGE="$JOBSEEKER_DOCKER_IMAGE"';
          $lines[] = 'if [ -n "$JOBSEEKER_DOCKERFILE" ]; then printf "%s\n" "[JobSeeker] Docker image build"; JOBSEEKER_DOCKER_TAG="$(printf "%s" "${JOB_NAME:-job}-${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"; if [ -z "$JOBSEEKER_DOCKER_TAG" ]; then JOBSEEKER_DOCKER_TAG="manual"; fi; JOBSEEKER_DOCKER_RUN_IMAGE="jobseeker-python-custom:$JOBSEEKER_DOCKER_TAG"; JOBSEEKER_DOCKER_BUILT_IMAGE="$JOBSEEKER_DOCKER_RUN_IMAGE"; JOBSEEKER_DOCKER_BUILD_CONTEXT="$(dirname "$JOBSEEKER_DOCKERFILE")"; JOBSEEKER_DOCKER_PULL="--pull"; if grep -Eiq "^[[:space:]]*FROM[[:space:]]+(--platform=[^[:space:]]+[[:space:]]+)?jobseeker-runtime/" "$JOBSEEKER_DOCKERFILE"; then JOBSEEKER_DOCKER_PULL=""; echo "Building FROM a JobSeeker workspace runtime, which exists only in this job runtime: base images are not pulled."; fi; DOCKER_BUILDKIT=1 docker build --network host $JOBSEEKER_DOCKER_PULL -t "$JOBSEEKER_DOCKER_RUN_IMAGE" -f "$JOBSEEKER_DOCKERFILE" "$JOBSEEKER_DOCKER_BUILD_CONTEXT"; fi';
          $lines[] = 'JOBSEEKER_EMAIL_METRICS_VOLUME="$(printf "jobseeker-email-%s-%s" "${JOB_NAME:-job}" "${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"';
          $lines[] = 'docker volume create "$JOBSEEKER_EMAIL_METRICS_VOLUME" >/dev/null';
          $lines[] = 'docker run --rm --user 0 --entrypoint sh -v "$JOBSEEKER_EMAIL_METRICS_VOLUME:/jobseeker-email" "$JOBSEEKER_DOCKER_RUN_IMAGE" -c "chmod 0777 /jobseeker-email"';
          if ($notebook) {
            // The executed notebook comes back out of the container through a
            // volume of its own, into the published run folder.
            $lines[] = 'JOBSEEKER_NOTEBOOK_VOLUME="$(printf "jobseeker-notebook-%s-%s" "${JOB_NAME:-job}" "${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"';
            $lines[] = 'docker volume create "$JOBSEEKER_NOTEBOOK_VOLUME" >/dev/null';
            $lines[] = 'docker run --rm --user 0 --entrypoint sh -v "$JOBSEEKER_NOTEBOOK_VOLUME:/jobseeker-notebook" "$JOBSEEKER_DOCKER_RUN_IMAGE" -c "chmod 0777 /jobseeker-notebook"';
          }
          $lines[] = 'mkdir -p "$JOBSEEKER_REPOSITORY_ROOT/data-assets"';
          $lines[] = 'JOBSEEKER_DATA_ASSETS_VOLUME="$(printf "jobseeker-assets-%s-%s" "${JOB_NAME:-job}" "${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"';
          $lines[] = 'docker volume create "$JOBSEEKER_DATA_ASSETS_VOLUME" >/dev/null';
          $lines = array_merge($lines, $this->dockerConnectorSetupLines('', TRUE));
          $lines[] = 'tar -C "$JOBSEEKER_REPOSITORY_ROOT" -cf - data-assets | docker run --rm -i --user 0 --entrypoint sh -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" "$JOBSEEKER_DOCKER_RUN_IMAGE" -c "cd /jobseeker-repository && tar -xf - && chmod -R a+rwX data-assets"';
          $lines[] = 'printf "%s\n" "[JobSeeker] Docker container execution"';
          $lines[] = 'JOBSEEKER_DOCKER_STATUS=0';
          $lines[] = 'tar -C "$JOBSEEKER_DOCKER_CONTEXT" -cf - . | docker run --rm -i \\';
          $lines = array_merge($lines, $this->dockerJobRunIdentityOptions());
          $lines[] = '  --network host \\';
          $lines[] = '  -v "$JOBSEEKER_EMAIL_METRICS_VOLUME:/jobseeker-email" \\';
          $lines[] = '  -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" \\';
          $lines[] = '  -v "$JOBSEEKER_CONNECTORS_VOLUME:/run/jobseeker-connectors:ro" \\';
          if ($notebook) {
            $lines[] = '  -v "$JOBSEEKER_NOTEBOOK_VOLUME:/jobseeker-notebook" \\';
            $lines[] = '  -e JOBSEEKER_NOTEBOOK_SPEC -e JOBSEEKER_NOTEBOOK_CELL_TIMEOUT -e JOBSEEKER_NOTEBOOK_ALLOW_ERRORS \\';
            $lines[] = '  -e JOBSEEKER_NOTEBOOK_TMF -e JOBSEEKER_NOTEBOOK_PARAMETERS -e JOBSEEKER_NOTEBOOK_PUBLISHED \\';
          }
          $lines[] = '  -e "JOBSEEKER_ENTRYPOINT=$JOBSEEKER_DOCKER_ENTRYPOINT" \\';
          $lines[] = '  -e JOBSEEKER_EMAIL_METRICS_FILE=/jobseeker-email/jobseeker-email-metrics.properties \\';
          $lines[] = '  -e JOBSEEKER_REPOSITORY_ROOT=/jobseeker-repository \\';
          $lines[] = '  -e JOBSEEKER_DATA_ASSETS_MANIFEST=/jobseeker-repository/data-assets/manifest.json \\';
          $lines[] = '  -e JOBSEEKER_CONNECTORS_DIR=/run/jobseeker-connectors \\';
          $lines[] = '  -e JOBSEEKER_CONNECTOR_HELPER=/run/jobseeker-connectors/jobseeker-connector \\';
          $lines[] = '  -e JOBSEEKER_ENVIRONMENT -e JOBSEEKER_JOB_NAME -e JOBSEEKER_DATA_ASSET_JOB \\';
          $lines[] = '  -e JOBSEEKER_PROJECT_ID -e JOBSEEKER_PROJECT_NAME \\';
          $lines = array_merge($lines, $this->dockerContextEnvLines());
          $lines[] = '  -e JOBSEEKER_DAG_RESUME -e JOBSEEKER_DAG_TASKS -e JOBSEEKER_DAG_MAX_PARALLEL \\';
          $lines[] = '  -e JOBSEEKER_DAG_FAIL_FAST -e JOBSEEKER_DAG_STATE \\';
          $lines[] = '  -e PYTHONUNBUFFERED \\';
          $lines[] = '  -e JOB_NAME -e BUILD_NUMBER -e BUILD_ID -e JOBSEEKER_CONTAINER_NAME \\';
          $lines[] = '  -e JOBSEEKER_DB_HOST -e JOBSEEKER_DB_PORT -e JOBSEEKER_DB_USER -e JOBSEEKER_DB_PASSWORD -e JOBSEEKER_DB_NAME \\';
          $lines[] = '  "$JOBSEEKER_DOCKER_RUN_IMAGE" \\';
          $lines[] = '  '.$this->dockerLoginShell().' '.escapeshellarg($dockerScript).' sh'.($environmentArgument !== '' ? ' '.$environmentArgument : '').' || JOBSEEKER_DOCKER_STATUS=$?';
          if ($notebook) {
            $lines[] = 'docker run --rm --user 0 --entrypoint sh -v "$JOBSEEKER_NOTEBOOK_VOLUME:/jobseeker-notebook:ro" "$JOBSEEKER_DOCKER_RUN_IMAGE" -c "tar -C /jobseeker-notebook -cf - ." | (umask 027; tar -C "$JOBSEEKER_NOTEBOOK_RUN_DIR" -xf -) || echo "[JobSeeker] The executed notebook could not be copied out of the container."';
          }
          $lines[] = 'printf "%s\n" "[JobSeeker] Cleanup"';
          $lines[] = 'docker run --rm --user 0 --entrypoint cat -v "$JOBSEEKER_EMAIL_METRICS_VOLUME:/jobseeker-email:ro" "$JOBSEEKER_DOCKER_RUN_IMAGE" /jobseeker-email/jobseeker-email-metrics.properties > "$JOBSEEKER_EMAIL_METRICS_FILE.tmp" 2>/dev/null && mv "$JOBSEEKER_EMAIL_METRICS_FILE.tmp" "$JOBSEEKER_EMAIL_METRICS_FILE" || rm -f "$JOBSEEKER_EMAIL_METRICS_FILE.tmp"';
          $lines[] = 'docker run --rm --user 0 --entrypoint sh -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" "$JOBSEEKER_DOCKER_RUN_IMAGE" -c \'rm -f /jobseeker-repository/data-assets/manifest.json; tar -C /jobseeker-repository -cf - data-assets\' | tar -C "$JOBSEEKER_REPOSITORY_ROOT" -xf -';
          $lines[] = 'if [ "$JOBSEEKER_DOCKER_STATUS" -ne 0 ]; then exit "$JOBSEEKER_DOCKER_STATUS"; fi';
        } else {
          $lines[] = 'printf "%s\n" "[JobSeeker] Python environment"';
          $lines[] = 'JOBSEEKER_REQUIREMENTS=""';
          $lines[] = 'if [ -f "$JOBSEEKER_SOURCE_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="$JOBSEEKER_SOURCE_DIR/requirements.txt"; fi';
          $lines[] = 'if [ -f "$JOBSEEKER_SCRIPT_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="$JOBSEEKER_SCRIPT_DIR/requirements.txt"; fi';
          $lines = array_merge($lines, $this->agentPyprojectRequirementsLines());
          $lines[] = 'if [ -n "$JOBSEEKER_REQUIREMENTS" ]; then';
          $lines[] = '  rm -rf "$JOBSEEKER_VENV" "$JOBSEEKER_SOURCE_DIR/.jobseeker-python-libs"';
          $lines[] = '  "$JOBSEEKER_PYTHON" -m venv "$JOBSEEKER_VENV" || { echo "Unable to create Python virtual environment. Install python3-venv on this Jenkins agent or switch this job to Docker runtime."; exit 127; }';
          $lines[] = '  JOBSEEKER_RUN_PYTHON="$JOBSEEKER_VENV/bin/python"';
          $lines[] = '  "$JOBSEEKER_RUN_PYTHON" -m pip install --quiet --disable-pip-version-check "$JOBSEEKER_PYTHON_SDK"';
          $lines[] = '  "$JOBSEEKER_RUN_PYTHON" -m pip install --quiet --disable-pip-version-check -r "$JOBSEEKER_REQUIREMENTS"';
          $lines[] = '  export PYTHONPATH="$JOBSEEKER_SOURCE_DIR:$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"';
          $lines[] = 'else';
          $lines[] = '  JOBSEEKER_RUN_PYTHON="$JOBSEEKER_PYTHON"';
          $lines[] = '  rm -rf "$JOBSEEKER_VENV" "$JOBSEEKER_RUNTIME_LIBS"';
          $lines[] = '  "$JOBSEEKER_PYTHON" -m pip install --quiet --disable-pip-version-check --target "$JOBSEEKER_RUNTIME_LIBS" "$JOBSEEKER_PYTHON_SDK"';
          $lines[] = '  export PYTHONPATH="$JOBSEEKER_RUNTIME_LIBS:$JOBSEEKER_SOURCE_DIR:$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"';
          $lines[] = 'fi';
          $lines[] = 'if [ -n "${JOBSEEKER_PROJECT_ROOT:-}" ]; then export PYTHONPATH="$PYTHONPATH:$JOBSEEKER_PROJECT_ROOT"; fi';
          if ($notebook) {
            $lines = array_merge($lines, $this->notebookRunnerLines('"$JOBSEEKER_RUN_PYTHON"', '"$WORKSPACE/.jobseeker-notebook-libs"', '"$JOBSEEKER_SCRIPT_PATH"', '"$JOBSEEKER_NOTEBOOK_RUN_DIR/$(basename "$JOBSEEKER_SCRIPT_PATH")"'));
          } else {
            $lines[] = 'printf "%s\n" "[JobSeeker] Python execution"';
            $lines[] = '"$JOBSEEKER_RUN_PYTHON" -u "$JOBSEEKER_SCRIPT_PATH"'.($environmentArgument !== '' ? ' '.$environmentArgument : '');
          }
        }

        return implode("\n", $lines);
      }

      /**
       * Apache Hop execution.
       *
       * Jenkins keeps the schedule, the environment parameter, the timeout and
       * the notifications; the whole Hop runtime concern lives in the
       * jobseeker-hop runner, which materializes connectors into Hop database
       * connections, publishes Data Assets and Context values as Hop variables,
       * opens and closes the TMF instance, and starts the engine the job chose.
       * Keeping this builder short means a failed build can be reproduced by
       * copying one command out of the Jenkins console.
       */
      private function buildHopExecutionCommand($execution, $repositoryRoot) {
        $engine = isset($execution['engine']) ? (string) $execution['engine'] : 'container';
        $lines = array_merge(array('set -e', 'export PYTHONUNBUFFERED=1'), $this->dataAssetsRuntimeLines($repositoryRoot), $this->environmentContextLines());
        $lines[] = 'command -v jobseeker-hop >/dev/null || { echo "The JobSeeker Apache Hop runner is not installed on this Jenkins worker. Rebuild the Jenkins image to pick up the bundled SDK." >&2; exit 127; }';
        $lines[] = 'printf "%s\n" '.escapeshellarg('[JobSeeker] Apache Hop execution ('.$engine.')');

        $command = array(
          'jobseeker-hop run',
          '  --project '.escapeshellarg((string) $execution['projectPath']),
          '  --file '.escapeshellarg((string) $execution['entryFile']),
          '  --engine '.escapeshellarg($engine),
          '  --run-config '.escapeshellarg((string) $execution['runConfig']),
          '  --log-level '.escapeshellarg((string) $execution['logLevel']),
          '  --environment "$JOBSEEKER_ENVIRONMENT"',
          '  --job "$JOBSEEKER_JOB_NAME"',
          '  --repository-root "$JOBSEEKER_REPOSITORY_ROOT"'
        );

        if ($engine === 'container') {
          if (! empty($execution['image'])) {
            $command[] = '  --image '.escapeshellarg((string) $execution['image']);
          }
          $command[] = '  --cpu-limit '.escapeshellarg(isset($execution['cpuLimit']) ? (string) $execution['cpuLimit'] : '1');
          $command[] = '  --memory-limit-mb '.escapeshellarg((string) (isset($execution['memoryLimitMb']) ? (int) $execution['memoryLimitMb'] : 1024));
        }

        foreach ((array) (isset($execution['parameters']) ? $execution['parameters'] : array()) as $name => $value) {
          $command[] = '  --param '.escapeshellarg($name.'='.$value);
        }

        $lines[] = implode(" \\\n", $command);

        return implode("\n", $lines);
      }
}
