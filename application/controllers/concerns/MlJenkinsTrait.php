<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Turns a saved ml_job into a real Jenkins freestyle job whose single shell
 * step calls the agent-callable MlRuntime endpoint to start the run, then polls
 * it to completion and mirrors the container log into the Jenkins console -
 * the same "thin Jenkins job drives a JobSeeker runtime" pattern the Spark and
 * connector features use.
 *
 * Host controller must extend BaseController (requestJenkins / getRuntimeConfig).
 */
trait MlJenkinsTrait
{
    protected function mlJenkinsJobPath($jobName)
    {
        $segments = array();
        foreach (explode('/', trim((string) $jobName, '/')) as $segment) {
            if ($segment !== '') {
                $segments[] = 'job/'.rawurlencode($segment);
            }
        }
        return implode('/', $segments);
    }

    protected function mlJenkinsJobName($job)
    {
        $env = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $job->environment));
        $key = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $job->job_key));
        return 'ml/'.trim($env, '-').'/'.trim($key, '-');
    }

    protected function mlRuntimeBaseUrl()
    {
        return rtrim((string) (getenv('JOBSEEKER_ML_RUNTIME_PUBLIC_URL')
            ?: getenv('JOBSEEKER_ML_RUNTIME_URL')
            ?: 'http://nginx:8080/machine-learning/runtime'), '/');
    }

    protected function mlJenkinsShellCommand($job)
    {
        $base = $this->mlRuntimeBaseUrl();
        $token = (string) getenv('JOBSEEKER_ML_API_TOKEN');
        $jobKey = escapeshellarg((string) $job->job_key);
        $environment = escapeshellarg((string) $job->environment);
        $auth = 'Authorization: Bearer '.$token;

        return <<<SH
set -euo pipefail
BASE="{$base}"
AUTH="{$auth}"
echo "Starting ML run for {$job->job_key} (\$ENVIRONMENT)"
RESP="\$(curl -sS -X POST "\$BASE/trigger" -H "\$AUTH" \\
  --data-urlencode job_key={$jobKey} \\
  --data-urlencode environment="\$ENVIRONMENT" \\
  --data-urlencode build="\${BUILD_NUMBER:-0}" \\
  --data-urlencode triggered_by="\${BUILD_USER:-Jenkins}")"
echo "\$RESP"
RUN_KEY="\$(printf '%s' "\$RESP" | sed -n 's/.*"run_key":"\\([a-f0-9]\\{1,\\}\\)".*/\\1/p')"
if [ -z "\$RUN_KEY" ]; then echo "Could not start the run"; exit 1; fi
echo "run_key=\$RUN_KEY"
LAST=0
while : ; do
  sleep 5
  ST="\$(curl -sS "\$BASE/status/\$RUN_KEY" -H "\$AUTH")"
  LOGS="\$(curl -sS "\$BASE/logs/\$RUN_KEY" -H "\$AUTH")"
  BODY="\$(printf '%s' "\$LOGS" | sed -n 's/.*"logs":"\\(.*\\)"}.*/\\1/p')"
  CUR="\$(printf '%s' "\$BODY" | wc -c)"
  if [ "\$CUR" -gt "\$LAST" ]; then printf '%b' "\${BODY}" | tail -c +\$((LAST+1)); LAST=\$CUR; fi
  case "\$ST" in
    *'"status":"SUCCEEDED"'*) echo; echo "ML run SUCCEEDED"; exit 0 ;;
    *'"status":"FAILED"'*|*'"status":"TIMED_OUT"'*|*'"status":"CANCELLED"'*)
      echo; echo "ML run did not succeed: \$ST"; exit 1 ;;
  esac
done
SH;
    }

    /**
     * Create or update the Jenkins job. Returns
     * {ok:bool, job_name:string, updated:bool, status:int}.
     */
    protected function mlDeployJenkinsJob($job)
    {
        $jobName = $this->mlJenkinsJobName($job);
        $xml = $this->mlJenkinsJobXml($job);
        $path = $this->mlJenkinsJobPath($jobName);

        $probe = $this->requestJenkins('GET', $path.'/api/json');
        if ((int) $probe['status'] === 200) {
            $save = $this->requestJenkins('POST', $path.'/config.xml', $xml, 'text/xml');
            return array('ok' => in_array((int) $save['status'], array(200, 201, 302, 303), TRUE),
                'job_name' => $jobName, 'updated' => TRUE, 'status' => (int) $save['status']);
        }
        if ((int) $probe['status'] === 404) {
            $folder = trim(dirname($jobName), '/.');
            $this->mlEnsureJenkinsFolder($folder);
            $createPath = $folder === '' || $folder === '.'
                ? 'createItem?name='.rawurlencode($jobName)
                : $this->mlJenkinsJobPath($folder).'/createItem?name='.rawurlencode(basename($jobName));
            $create = $this->requestJenkins('POST', $createPath, $xml, 'text/xml');
            return array('ok' => in_array((int) $create['status'], array(200, 201, 302, 303), TRUE),
                'job_name' => $jobName, 'updated' => FALSE, 'status' => (int) $create['status']);
        }
        return array('ok' => FALSE, 'job_name' => $jobName, 'updated' => FALSE, 'status' => (int) $probe['status']);
    }

    protected function mlEnsureJenkinsFolder($folderPath)
    {
        $parts = array_values(array_filter(explode('/', trim((string) $folderPath, '/')), 'strlen'));
        $prefix = '';
        foreach ($parts as $part) {
            $checkPath = $this->mlJenkinsJobPath(ltrim($prefix.'/'.$part, '/'));
            $exists = $this->requestJenkins('GET', $checkPath.'/api/json');
            if ((int) $exists['status'] !== 200) {
                $createUnder = $prefix === '' ? 'createItem?name='.rawurlencode($part)
                    : $this->mlJenkinsJobPath($prefix).'/createItem?name='.rawurlencode($part);
                $folderXml = '<?xml version="1.0" encoding="UTF-8"?>'
                    .'<com.cloudbees.hudson.plugins.folder.Folder><actions/><description>JobSeeker ML jobs</description>'
                    .'<properties/><folderViews/><healthMetrics/></com.cloudbees.hudson.plugins.folder.Folder>';
                $this->requestJenkins('POST', $createUnder, $folderXml, 'text/xml');
            }
            $prefix = ltrim($prefix.'/'.$part, '/');
        }
    }

    protected function mlJenkinsJobXml($job)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = TRUE;
        $project = $dom->createElement('project');
        $dom->appendChild($project);
        $project->appendChild($dom->createElement('actions'));
        $project->appendChild($dom->createElement('description',
            'JobSeeker ML job "'.htmlspecialchars((string) $job->name, ENT_QUOTES).'" ('.$job->run_type.'). Managed - edit in JobSeeker.'));
        $project->appendChild($dom->createElement('keepDependencies', 'false'));

        $properties = $dom->createElement('properties');
        $project->appendChild($properties);
        $paramDef = $dom->createElement('hudson.model.ParametersDefinitionProperty');
        $properties->appendChild($paramDef);
        $paramDefs = $dom->createElement('parameterDefinitions');
        $paramDef->appendChild($paramDefs);
        $stringParam = $dom->createElement('hudson.model.StringParameterDefinition');
        $paramDefs->appendChild($stringParam);
        $stringParam->appendChild($dom->createElement('name', 'ENVIRONMENT'));
        $stringParam->appendChild($dom->createElement('description', 'Target environment'));
        $stringParam->appendChild($dom->createElement('defaultValue', (string) $job->environment));
        $stringParam->appendChild($dom->createElement('trim', 'true'));

        $project->appendChild($dom->createElement('scm'))->setAttribute('class', 'hudson.scm.NullSCM');
        $project->appendChild($dom->createElement('canRoam', 'true'));
        $project->appendChild($dom->createElement('disabled', $job->is_active ? 'false' : 'true'));
        $project->appendChild($dom->createElement('blockBuildWhenDownstreamBuilding', 'false'));
        $project->appendChild($dom->createElement('blockBuildWhenUpstreamBuilding', 'false'));
        $project->appendChild($dom->createElement('triggers'));
        $project->appendChild($dom->createElement('concurrentBuild', 'false'));

        $builders = $dom->createElement('builders');
        $project->appendChild($builders);
        $shell = $dom->createElement('hudson.tasks.Shell');
        $builders->appendChild($shell);
        $command = $dom->createElement('command');
        $command->appendChild($dom->createCDATASection($this->mlJenkinsShellCommand($job)));
        $shell->appendChild($command);

        $project->appendChild($dom->createElement('publishers'));
        $project->appendChild($dom->createElement('buildWrappers'));

        return $dom->saveXML();
    }
}
