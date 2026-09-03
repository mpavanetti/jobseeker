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
          '  --label "com.jobseeker.runtime=${JOBSEEKER_CONTAINER_RUNTIME}" \\'
        );
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
        $runtimeLines = array_merge($this->dataAssetsRuntimeLines($repositoryRoot), $this->connectorRuntimeLines());

        if ($runtimeMode !== 'docker') {
          $runtimeLines[] = 'trap \'rm -rf "$JOBSEEKER_CONNECTORS_DIR"\' EXIT';
          $runtimeLines[] = 'printf "%s\n" "[JobSeeker] Shell execution"';
          $runtimeLines[] = $commandText;
          return implode("\n", $runtimeLines);
        }

        $lines = array_merge(array('set -e'), $runtimeLines);
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
        $lines[] = '  -e JOB_NAME -e BUILD_NUMBER -e BUILD_ID -e JOBSEEKER_CONTAINER_NAME \\';
        $lines[] = '  "$JOBSEEKER_DOCKER_IMAGE" \\';
        $lines[] = '  sh -lc \'export PATH="$JOBSEEKER_CONNECTORS_DIR:$PATH"; printf "%s" "$JOBSEEKER_LINUX_COMMAND_B64" | base64 -d | sh\' || JOBSEEKER_DOCKER_STATUS=$?';
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

        $runtimeLines = array_merge($this->dataAssetsRuntimeLines($repositoryRoot), $this->connectorRuntimeLines());

        if ($runtimeMode !== 'docker') {
          $runtimeLines[] = 'trap \'rm -rf "$JOBSEEKER_CONNECTORS_DIR"\' EXIT';
          $runtimeLines[] = 'printf "%s\n" "[JobSeeker] '.($execution['scriptType'] === 'talend' ? 'Talend execution' : 'Shell execution').'"';
          $runtimeLines[] = 'sh '.escapeshellarg($execution['scriptPath']).($argumentString !== '' ? ' '.$argumentString : '');
          return implode("\n", $runtimeLines);
        }

        $dockerScript = implode("\n", array(
          'set -e',
          'export PATH="$JOBSEEKER_CONNECTORS_DIR:$PATH"',
          'mkdir -p /tmp/jobseeker-context',
          'tar -C /tmp/jobseeker-context -xf -',
          'cd /tmp/jobseeker-context/source',
          'if [ -n "${JAVA_HOME:-}" ] && [ -d "$JAVA_HOME/bin" ]; then export PATH="$JAVA_HOME/bin:$PATH"; fi',
          'if [ -d /opt/java/openjdk/bin ]; then export PATH="/opt/java/openjdk/bin:$PATH"; fi',
          'sh "$JOBSEEKER_ENTRYPOINT" "$@"'
        ));

        $lines = array_merge(array('set -e'), $runtimeLines);
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
        $lines[] = '  -e JOB_NAME -e BUILD_NUMBER -e BUILD_ID -e JOBSEEKER_CONTAINER_NAME \\';
        $lines[] = '  "$JOBSEEKER_DOCKER_IMAGE" \\';
        $lines[] = '  sh -lc '.escapeshellarg($dockerScript).' sh'.($argumentString !== '' ? ' '.$argumentString : '').' || JOBSEEKER_DOCKER_STATUS=$?';
        $lines[] = 'printf "%s\n" "[JobSeeker] Cleanup"';
        $lines[] = 'docker run --rm --user 0 --entrypoint sh -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" "$JOBSEEKER_DOCKER_IMAGE" -c \'rm -f /jobseeker-repository/data-assets/manifest.json; tar -C /jobseeker-repository -cf - data-assets\' | tar -C "$JOBSEEKER_REPOSITORY_ROOT" -xf -';
        $lines[] = 'if [ "$JOBSEEKER_DOCKER_STATUS" -ne 0 ]; then exit "$JOBSEEKER_DOCKER_STATUS"; fi';

        return implode("\n", $lines);
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
        $lines = array_merge(array('set -e'), $this->dataAssetsRuntimeLines($repositoryRoot), $this->connectorRuntimeLines());

        if ($execution['mode'] === 'git') {
          $lines[] = 'printf "%s\n" "[JobSeeker] Git source checkout"';
          $lines[] = 'export JOBSEEKER_GIT_REPOSITORY_URL='.escapeshellarg($execution['repositoryUrl']);
          $lines[] = 'export JOBSEEKER_GIT_REPOSITORY_BRANCH='.escapeshellarg($execution['branch']);
          $lines[] = 'export JOBSEEKER_GIT_CREDENTIAL_KEY='.escapeshellarg(isset($execution['credentialKey']) ? $execution['credentialKey'] : '');
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
          $lines[] = $cloneCommand;
          $lines[] = 'export JOBSEEKER_SOURCE_DIR="$WORKSPACE/jobseeker-python-source"';
          $lines[] = 'export JOBSEEKER_ENTRYPOINT='.escapeshellarg($execution['entryPoint']);
          $lines[] = 'export JOBSEEKER_SCRIPT_PATH="$JOBSEEKER_SOURCE_DIR/$JOBSEEKER_ENTRYPOINT"';
          $lines[] = '[ -f "$JOBSEEKER_SCRIPT_PATH" ] || { echo "Python entry point was not found after the Git checkout: $JOBSEEKER_ENTRYPOINT" >&2; exit 66; }';
        } else {
          $lines[] = 'export JOBSEEKER_SOURCE_DIR='.escapeshellarg($execution['sourceDirectory']);
          $lines[] = 'export JOBSEEKER_SCRIPT_PATH='.escapeshellarg($execution['scriptPath']);
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

        if ($runtimeMode === 'docker') {
          $dockerScriptLines = array(
            'set -e',
            'export PATH="$JOBSEEKER_CONNECTORS_DIR:$PATH"',
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
            '    (cd "$JOBSEEKER_PROJECT_DIR" && POETRY_VIRTUALENVS_CREATE=false poetry install --no-root --no-interaction --no-ansi)',
            '  fi',
            'elif [ -n "$JOBSEEKER_REQUIREMENTS" ]; then',
            '  rm -rf /tmp/jobseeker-python-libs',
            '  PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet --disable-pip-version-check --target /tmp/jobseeker-python-libs -r "$JOBSEEKER_REQUIREMENTS"',
            '  JOBSEEKER_USER_LIBS="/tmp/jobseeker-python-libs"',
            'fi',
            'if [ -n "$JOBSEEKER_USER_LIBS" ]; then export PYTHONPATH="/tmp/jobseeker-runtime-libs:$JOBSEEKER_USER_LIBS:/tmp/jobseeker-context/source:/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"; else export PYTHONPATH="/tmp/jobseeker-runtime-libs:/tmp/jobseeker-context/source:/tmp/jobseeker-context/source/$JOBSEEKER_SCRIPT_DIR:$PYTHONPATH"; fi'
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

          $dockerScriptLines = array_merge($dockerScriptLines, array(
            'printf "%s\n" "[JobSeeker] Python execution"',
            'python -u "$JOBSEEKER_ENTRYPOINT" "$@"'
          ));
          $dockerScript = implode("\n", $dockerScriptLines);

          $lines[] = 'export JOBSEEKER_DOCKER_IMAGE='.escapeshellarg($dockerImage);
          $lines = array_merge($lines, $this->dockerJobResourceLines($runtimeOptions));
          $lines[] = 'printf "%s\n" "[JobSeeker] Docker image build"';
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
          $lines[] = 'jobseeker_python_docker_cleanup() { rm -rf "$JOBSEEKER_DOCKER_CONTEXT" "$JOBSEEKER_CONNECTORS_DIR"; if [ -n "$JOBSEEKER_CONNECTORS_VOLUME" ]; then docker volume rm "$JOBSEEKER_CONNECTORS_VOLUME" >/dev/null 2>&1 || true; fi; if [ -n "$JOBSEEKER_EMAIL_METRICS_VOLUME" ]; then docker volume rm "$JOBSEEKER_EMAIL_METRICS_VOLUME" >/dev/null 2>&1 || true; fi; if [ -n "$JOBSEEKER_DATA_ASSETS_VOLUME" ]; then docker volume rm "$JOBSEEKER_DATA_ASSETS_VOLUME" >/dev/null 2>&1 || true; fi; if [ -n "$JOBSEEKER_DOCKER_BUILT_IMAGE" ]; then docker image rm "$JOBSEEKER_DOCKER_BUILT_IMAGE" >/dev/null 2>&1 || true; fi; }';
          $lines[] = 'trap jobseeker_python_docker_cleanup EXIT';
          $lines[] = 'rm -rf "$JOBSEEKER_DOCKER_CONTEXT"';
          $lines[] = 'mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/source" "$JOBSEEKER_DOCKER_CONTEXT/jobseeker-sdk"';
          $lines[] = 'cp -R "$JOBSEEKER_SOURCE_DIR/." "$JOBSEEKER_DOCKER_CONTEXT/source/"';
          // Editor virtual environments and caches are local development
          // state. Never stream them into the disposable Jenkins container.
          $lines[] = 'find "$JOBSEEKER_DOCKER_CONTEXT/source" -type d \( -name .git -o -name .venv -o -name venv -o -name __pycache__ -o -name .pytest_cache -o -name .mypy_cache -o -name .ruff_cache \) -prune -exec rm -rf {} +';
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
          $lines[] = 'if [ -n "$JOBSEEKER_DOCKERFILE" ]; then JOBSEEKER_DOCKER_TAG="$(printf "%s" "${JOB_NAME:-job}-${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"; if [ -z "$JOBSEEKER_DOCKER_TAG" ]; then JOBSEEKER_DOCKER_TAG="manual"; fi; JOBSEEKER_DOCKER_RUN_IMAGE="jobseeker-python-custom:$JOBSEEKER_DOCKER_TAG"; JOBSEEKER_DOCKER_BUILT_IMAGE="$JOBSEEKER_DOCKER_RUN_IMAGE"; JOBSEEKER_DOCKER_BUILD_CONTEXT="$(dirname "$JOBSEEKER_DOCKERFILE")"; DOCKER_BUILDKIT=1 docker build --network host --pull -t "$JOBSEEKER_DOCKER_RUN_IMAGE" -f "$JOBSEEKER_DOCKERFILE" "$JOBSEEKER_DOCKER_BUILD_CONTEXT"; fi';
          $lines[] = 'JOBSEEKER_EMAIL_METRICS_VOLUME="$(printf "jobseeker-email-%s-%s" "${JOB_NAME:-job}" "${BUILD_NUMBER:-0}" | tr "[:upper:]/ " "[:lower:]--" | tr -cd "a-z0-9_.-" | cut -c1-120)"';
          $lines[] = 'docker volume create "$JOBSEEKER_EMAIL_METRICS_VOLUME" >/dev/null';
          $lines[] = 'docker run --rm --user 0 --entrypoint sh -v "$JOBSEEKER_EMAIL_METRICS_VOLUME:/jobseeker-email" "$JOBSEEKER_DOCKER_RUN_IMAGE" -c "chmod 0777 /jobseeker-email"';
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
          $lines[] = '  -e "JOBSEEKER_ENTRYPOINT=$JOBSEEKER_DOCKER_ENTRYPOINT" \\';
          $lines[] = '  -e JOBSEEKER_EMAIL_METRICS_FILE=/jobseeker-email/jobseeker-email-metrics.properties \\';
          $lines[] = '  -e JOBSEEKER_REPOSITORY_ROOT=/jobseeker-repository \\';
          $lines[] = '  -e JOBSEEKER_DATA_ASSETS_MANIFEST=/jobseeker-repository/data-assets/manifest.json \\';
          $lines[] = '  -e JOBSEEKER_CONNECTORS_DIR=/run/jobseeker-connectors \\';
          $lines[] = '  -e JOBSEEKER_CONNECTOR_HELPER=/run/jobseeker-connectors/jobseeker-connector \\';
          $lines[] = '  -e JOBSEEKER_ENVIRONMENT -e JOBSEEKER_JOB_NAME -e JOBSEEKER_DATA_ASSET_JOB \\';
          $lines[] = '  -e PYTHONUNBUFFERED \\';
          $lines[] = '  -e JOB_NAME -e BUILD_NUMBER -e BUILD_ID -e JOBSEEKER_CONTAINER_NAME \\';
          $lines[] = '  -e JOBSEEKER_DB_HOST -e JOBSEEKER_DB_PORT -e JOBSEEKER_DB_USER -e JOBSEEKER_DB_PASSWORD -e JOBSEEKER_DB_NAME \\';
          $lines[] = '  "$JOBSEEKER_DOCKER_RUN_IMAGE" \\';
          $lines[] = '  sh -lc '.escapeshellarg($dockerScript).' sh'.($environmentArgument !== '' ? ' '.$environmentArgument : '').' || JOBSEEKER_DOCKER_STATUS=$?';
          $lines[] = 'printf "%s\n" "[JobSeeker] Cleanup"';
          $lines[] = 'docker run --rm --user 0 --entrypoint cat -v "$JOBSEEKER_EMAIL_METRICS_VOLUME:/jobseeker-email:ro" "$JOBSEEKER_DOCKER_RUN_IMAGE" /jobseeker-email/jobseeker-email-metrics.properties > "$JOBSEEKER_EMAIL_METRICS_FILE.tmp" 2>/dev/null && mv "$JOBSEEKER_EMAIL_METRICS_FILE.tmp" "$JOBSEEKER_EMAIL_METRICS_FILE" || rm -f "$JOBSEEKER_EMAIL_METRICS_FILE.tmp"';
          $lines[] = 'docker run --rm --user 0 --entrypoint sh -v "$JOBSEEKER_DATA_ASSETS_VOLUME:/jobseeker-repository" "$JOBSEEKER_DOCKER_RUN_IMAGE" -c \'rm -f /jobseeker-repository/data-assets/manifest.json; tar -C /jobseeker-repository -cf - data-assets\' | tar -C "$JOBSEEKER_REPOSITORY_ROOT" -xf -';
          $lines[] = 'if [ "$JOBSEEKER_DOCKER_STATUS" -ne 0 ]; then exit "$JOBSEEKER_DOCKER_STATUS"; fi';
        } else {
          $lines[] = 'printf "%s\n" "[JobSeeker] Python environment"';
          $lines[] = 'JOBSEEKER_REQUIREMENTS=""';
          $lines[] = 'if [ -f "$JOBSEEKER_SOURCE_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="$JOBSEEKER_SOURCE_DIR/requirements.txt"; fi';
          $lines[] = 'if [ -f "$JOBSEEKER_SCRIPT_DIR/requirements.txt" ]; then JOBSEEKER_REQUIREMENTS="$JOBSEEKER_SCRIPT_DIR/requirements.txt"; fi';
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
          $lines[] = 'printf "%s\n" "[JobSeeker] Python execution"';
          $lines[] = '"$JOBSEEKER_RUN_PYTHON" -u "$JOBSEEKER_SCRIPT_PATH"'.($environmentArgument !== '' ? ' '.$environmentArgument : '');
        }

        return implode("\n", $lines);
      }
}
