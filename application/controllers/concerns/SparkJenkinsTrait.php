<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Turns a `spark_jobs` row into a real Jenkins job, so a Spark job authored in
 * Create Job behaves like any other JobSeeker job: it shows up in Job List,
 * triggers from Job Execution, can be wired into pipelines, and is scheduled by
 * Jenkins. The Jenkins job's single shell step doesn't run Spark itself - it
 * calls the agent-callable SparkRuntime controller, which drives the existing
 * ephemeral-cluster orchestrator, and streams the driver log back into the
 * Jenkins console via incremental polling.
 *
 * Requires the composing class to also `use JenkinsRunnerTrait;` (for
 * jenkinsRunnerShellJobXml / jenkinsJobPath) and to extend BaseController (for
 * requestJenkins). JobCreation and SparkJobs both do.
 */
trait SparkJenkinsTrait
{
    /**
     * The shell script a Spark job's Jenkins build runs. Talks only to
     * SparkRuntime (never the Docker engine directly) so the same token-authed
     * contract works whether the build runs on the Jenkins controller or an
     * agent container.
     */
    private function sparkRunnerShellCommand($jobKey)
    {
        $jobKeyArg = escapeshellarg((string) $jobKey);

        $lines = array(
            // A leading shebang makes Jenkins exec this file directly instead of
            // wrapping it as `sh -xe <file>` - keeps the console to JobSeeker's
            // own echo lines and the driver log, not a `set -x` trace of every
            // curl/python invocation.
            '#!/bin/sh',
            'set -eu',
            'JOBSEEKER_SPARK_JOB_KEY='.$jobKeyArg,
            ': "${JOBSEEKER_SPARK_TRIGGER_URL:?JOBSEEKER_SPARK_TRIGGER_URL is not set on this Jenkins worker}"',
            ': "${JOBSEEKER_SPARK_TRIGGER_TOKEN:?JOBSEEKER_SPARK_TRIGGER_TOKEN is not set on this Jenkins worker}"',
            'command -v curl >/dev/null || { echo "curl is not installed on this Jenkins worker."; exit 127; }',
            'command -v python3 >/dev/null || { echo "python3 is not installed on this Jenkins worker."; exit 127; }',
            '',
            '# 1. Provision the ephemeral cluster and start spark-submit.',
            'TRIGGER_RESPONSE=$(curl -fsS -X POST "$JOBSEEKER_SPARK_TRIGGER_URL/trigger" \\',
            '  -H "Authorization: Bearer $JOBSEEKER_SPARK_TRIGGER_TOKEN" \\',
            '  --data-urlencode "job_key=$JOBSEEKER_SPARK_JOB_KEY" \\',
            '  --data-urlencode "environment=$ENVIRONMENT" \\',
            '  --data-urlencode "build=$BUILD_NUMBER" \\',
            '  --data-urlencode "triggered_by=jenkins:$BUILD_TAG")',
            'RUN_ID=$(printf \'%s\' "$TRIGGER_RESPONSE" | python3 -c \'import json,sys; d=json.load(sys.stdin); print((d.get("run") or {}).get("run_id") or "")\' 2>/dev/null || echo "")',
            'if [ -z "$RUN_ID" ]; then',
            '  echo "JobSeeker: the Spark cluster could not be started."',
            '  printf \'%s\\n\' "$TRIGGER_RESPONSE"',
            '  exit 1',
            'fi',
            'echo "JobSeeker: Spark run $RUN_ID starting on its ephemeral cluster."',
            '',
            '# 2. Always release the cluster if this build is aborted.',
            'cleanup() { curl -fsS -X POST "$JOBSEEKER_SPARK_TRIGGER_URL/cancel/$RUN_ID" -H "Authorization: Bearer $JOBSEEKER_SPARK_TRIGGER_TOKEN" >/dev/null 2>&1 || true; }',
            'trap cleanup INT TERM',
            '',
            '# 3. Stream the driver log into the Jenkins console until the run ends.',
            'OFFSET=0',
            'TERMINAL=""',
            'while [ -z "$TERMINAL" ]; do',
            '  LOG_RESPONSE=$(curl -fsS "$JOBSEEKER_SPARK_TRIGGER_URL/logs/$RUN_ID?offset=$OFFSET" -H "Authorization: Bearer $JOBSEEKER_SPARK_TRIGGER_TOKEN")',
            '  printf \'%s\' "$LOG_RESPONSE" | python3 -c \'import json,sys; d=json.load(sys.stdin); sys.stdout.write(d.get("logs") or "")\' 2>/dev/null || true',
            '  OFFSET=$(printf \'%s\' "$LOG_RESPONSE" | python3 -c \'import json,sys; print(json.load(sys.stdin).get("next_offset") or 0)\')',
            '  TERMINAL=$(printf \'%s\' "$LOG_RESPONSE" | python3 -c \'import json,sys; print("1" if json.load(sys.stdin).get("terminal") else "")\')',
            '  [ -n "$TERMINAL" ] || sleep 3',
            'done',
            'trap - INT TERM',
            '',
            '# 4. Reflect the driver\'s outcome as this build\'s result.',
            'STATUS_RESPONSE=$(curl -fsS "$JOBSEEKER_SPARK_TRIGGER_URL/status/$RUN_ID" -H "Authorization: Bearer $JOBSEEKER_SPARK_TRIGGER_TOKEN")',
            'STATUS=$(printf \'%s\' "$STATUS_RESPONSE" | python3 -c \'import json,sys; print(json.load(sys.stdin).get("status") or "")\')',
            'echo "JobSeeker: Spark run $RUN_ID finished with status $STATUS."',
            '[ "$STATUS" = "SUCCEEDED" ]',
        );

        return implode("\n", $lines)."\n";
    }

    /**
     * Freestyle project XML for a Spark job: an ENVIRONMENT string parameter, an
     * optional TimerTrigger, and the single shell step that calls SparkRuntime.
     */
    private function sparkJenkinsJobXml($jobKey, $environment, $description, $scheduleSpec = '', $disabled = FALSE)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = TRUE;
        $text = function ($name, $value) use ($dom) {
            $el = $dom->createElement($name);
            $el->appendChild($dom->createTextNode((string) $value));
            return $el;
        };

        $project = $dom->createElement('project');
        $project->appendChild($text('description', $description !== '' ? $description
            : 'JobSeeker Spark job. Provisions an ephemeral job cluster on trigger and streams the driver log here.'));
        $project->appendChild($text('keepDependencies', 'false'));

        $properties = $dom->createElement('properties');
        $params = $dom->createElement('hudson.model.ParametersDefinitionProperty');
        $definitions = $dom->createElement('parameterDefinitions');
        $stringParam = $dom->createElement('hudson.model.StringParameterDefinition');
        $stringParam->appendChild($text('name', 'ENVIRONMENT'));
        $stringParam->appendChild($text('description', 'Runtime environment managed by JobSeeker.'));
        $stringParam->appendChild($text('defaultValue', $environment));
        $stringParam->appendChild($text('trim', 'true'));
        $definitions->appendChild($stringParam);
        $params->appendChild($definitions);
        $properties->appendChild($params);
        $project->appendChild($properties);

        $scm = $dom->createElement('scm');
        $scm->setAttribute('class', 'hudson.scm.NullSCM');
        $project->appendChild($scm);
        $project->appendChild($text('canRoam', 'true'));
        $project->appendChild($text('disabled', $disabled ? 'true' : 'false'));
        $project->appendChild($text('blockBuildWhenDownstreamBuilding', 'false'));
        $project->appendChild($text('blockBuildWhenUpstreamBuilding', 'false'));

        $triggers = $dom->createElement('triggers');
        if (trim((string) $scheduleSpec) !== '') {
            $timer = $dom->createElement('hudson.triggers.TimerTrigger');
            $timer->appendChild($text('spec', trim((string) $scheduleSpec)));
            $triggers->appendChild($timer);
        }
        $project->appendChild($triggers);

        $project->appendChild($text('concurrentBuild', 'false'));

        $builders = $dom->createElement('builders');
        $shell = $dom->createElement('hudson.tasks.Shell');
        $shell->appendChild($text('command', $this->sparkRunnerShellCommand($jobKey)));
        $builders->appendChild($shell);
        $project->appendChild($builders);

        $project->appendChild($dom->createElement('publishers'));
        $project->appendChild($dom->createElement('buildWrappers'));

        $dom->appendChild($project);
        return $dom->saveXML();
    }

    /**
     * Create or update the Jenkins job for a Spark job.
     *
     * @return array{ok:bool, updated:bool, status:int}
     */
    private function syncSparkJenkinsJob($jenkinsJobName, $jobKey, $environment, $description, $scheduleSpec = '', $disabled = FALSE)
    {
        return $this->saveGeneratedJenkinsJob(
            $jenkinsJobName,
            $this->sparkJenkinsJobXml($jobKey, $environment, $description, $scheduleSpec, $disabled)
        );
    }

    /** Best-effort delete; a missing Jenkins job is not an error. */
    private function deleteSparkJenkinsJob($jenkinsJobName)
    {
        $jenkinsJobName = trim((string) $jenkinsJobName);
        if ($jenkinsJobName === '') {
            return TRUE;
        }
        $response = $this->requestJenkins('POST', $this->jenkinsRunnerJobPath($jenkinsJobName).'/doDelete');
        return in_array((int) $response['status'], array(200, 302, 303, 404), TRUE);
    }
}
