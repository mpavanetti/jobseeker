<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Scaffolds the OpenVSCode workspace for a PySpark job or an All-Purpose cluster
 * scratchpad: a named *.code-workspace plus job.py / notebook.ipynb / connect.py /
 * README, all pre-wired to a Spark master URL. Shared by SparkJobs::develop and
 * SparkClusters::notebook so the two flows stay identical.
 *
 * There is deliberately no Dockerfile / devcontainer: the notebook talks to a
 * live cluster (in the DinD engine) as a remote Jupyter server, which a
 * "Reopen in Container" dev container on the editor host could never reach.
 */
class SparkWorkspace
{
    /**
     * Write the workspace into $dir. Returns the absolute path of the
     * *.code-workspace file (what the editor should open).
     *
     * @param string     $dir           absolute workspace directory (created by caller)
     * @param string     $slug          short kebab id, used for names
     * @param string     $title         human title for the README / notebook
     * @param string     $defaultMaster "spark://master:7077" for an attached cluster, else ""
     * @param array|null $persist       {name, jupyterVsCodeUrl, jupyterLabUrl, sparkUiUrl} or NULL
     * @param string     $jobSource     contents for job.py; '' => keep existing / write a starter
     * @param array      $extraSettings extra keys merged into .vscode/settings.json
     * @return string
     */
    public function scaffold($dir, $slug, $title, $defaultMaster, $persist, $jobSource = '', array $extraSettings = array())
    {
        $slug = preg_replace('/[^a-z0-9._-]+/', '-', strtolower((string) $slug));
        if ($slug === '') {
            $slug = 'spark';
        }

        $existingJob = is_file($dir.'/job.py') ? (string) file_get_contents($dir.'/job.py') : '';
        $jobSource = trim((string) $jobSource) !== '' ? (string) $jobSource
            : ($existingJob !== '' ? $existingJob : $this->starterJob($slug));

        $settings = array_merge(array(
            'python.analysis.typeCheckingMode' => 'basic',
            'files.exclude' => array('**/__pycache__' => TRUE, '**/.ipynb_checkpoints' => TRUE),
        ), $extraSettings);
        if ($defaultMaster !== '') {
            $settings['terminal.integrated.env.linux'] = array('SPARK_MASTER_URL' => $defaultMaster);
        }

        $workspaceFile = $slug.'.code-workspace';
        $files = array(
            'job.py' => $jobSource,
            'connect.py' => $this->connectPy($defaultMaster),
            'notebook.ipynb' => $this->notebookIpynb($title, $persist),
            'README.md' => $this->readme($title, $slug, $persist, $defaultMaster),
            '.vscode/settings.json' => json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
            $workspaceFile => json_encode(array(
                'folders' => array(array('path' => '.')),
                'settings' => (object) $settings,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        // README + connect.py + the workspace file are regenerated every open so
        // the cluster wiring stays current; job.py / notebook.ipynb are written
        // only when absent (never clobber the user's edits).
        $always = array('README.md', 'connect.py', $workspaceFile, '.vscode/settings.json');
        foreach ($files as $rel => $content) {
            $target = $dir.'/'.$rel;
            if (! is_dir(dirname($target))) {
                @mkdir(dirname($target), 0775, TRUE);
            }
            if (is_file($target) && ! in_array($rel, $always, TRUE)) {
                continue;
            }
            @file_put_contents($target, $content);
        }
        return $dir.'/'.$workspaceFile;
    }

    public function starterJob($slug)
    {
        return "\"\"\"".$slug." — JobSeeker PySpark job.\n\n"
            ."Runs via spark-submit (batch) and is import-safe for notebook.ipynb.\n\"\"\"\n\n"
            ."from connect import get_spark\n\n\n"
            ."def main() -> None:\n"
            ."    spark = get_spark(\"".$slug."\")\n"
            ."    try:\n"
            ."        print(\"rows:\", spark.range(1000).count())\n"
            ."    finally:\n"
            ."        spark.stop()\n\n\n"
            ."if __name__ == \"__main__\":\n"
            ."    main()\n";
    }

    public function connectPy($defaultMaster)
    {
        return "\"\"\"SparkSession helper. Order: \$SPARK_MASTER_URL -> DEFAULT_MASTER -> local[*].\"\"\"\n\n"
            ."import os\n\n"
            ."from pyspark.sql import SparkSession\n\n"
            ."DEFAULT_MASTER = ".json_encode((string) $defaultMaster, JSON_UNESCAPED_SLASHES)."\n\n\n"
            ."def get_spark(app_name: str = \"jobseeker\") -> SparkSession:\n"
            ."    master = os.environ.get(\"SPARK_MASTER_URL\", \"\").strip() or DEFAULT_MASTER\n"
            ."    builder = SparkSession.builder.appName(app_name)\n"
            ."    if master:\n"
            ."        builder = builder.master(master)\n"
            ."    return builder.getOrCreate()\n";
    }

    public function notebookIpynb($title, $persist)
    {
        $md = "# ".$title."\n\nInteractive PySpark. `get_spark()` from `connect.py` attaches to this job's compute.";
        if ($persist) {
            $md .= "\n\n**Attach the kernel:** VS Code → *Jupyter: Specify Jupyter Server for Connections* "
                ."→ *Existing* → paste\n\n`".$persist['jupyterVsCodeUrl']."`";
        }
        $cells = array(
            array('cell_type' => 'markdown', 'metadata' => new stdClass(), 'source' => array($md)),
            array('cell_type' => 'code', 'metadata' => new stdClass(), 'execution_count' => NULL, 'outputs' => array(),
                'source' => array("from connect import get_spark\n", "\n", "spark = get_spark(\"notebook\")\n", "spark.range(1000).count()")),
        );
        return json_encode(array(
            'cells' => $cells,
            'metadata' => array('language_info' => array('name' => 'python')),
            'nbformat' => 4, 'nbformat_minor' => 5,
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    public function readme($title, $slug, $persist, $defaultMaster)
    {
        $lines = array(
            '# '.$title,
            '',
            '- `job.py` — spark-submit entrypoint (also import-safe for the notebook).',
            '- `notebook.ipynb` — interactive dev surface; uses `connect.get_spark()`.',
            '- `connect.py` — `get_spark()`; master = `'.($defaultMaster !== '' ? $defaultMaster : 'local[*]').'` (override with `$SPARK_MASTER_URL`).',
        );
        if ($persist) {
            $lines[] = '- Kernel (VS Code → Existing Jupyter Server): `'.$persist['jupyterVsCodeUrl'].'`';
            $lines[] = '- JupyterLab: '.$persist['jupyterLabUrl'].'  ·  Spark UI: '.$persist['sparkUiUrl'];
        }
        return implode("\n", $lines)."\n";
    }
}
