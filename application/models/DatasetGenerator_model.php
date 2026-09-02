<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

class DatasetGenerator_model extends CI_Model
{
    const TMF_CHUNK_SIZE = 500;

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `generated_datasets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `batch_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `profile` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
            `status` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'generating',
            `tmf_rows` int(11) unsigned NOT NULL DEFAULT 0,
            `error_rows` int(11) unsigned NOT NULL DEFAULT 0,
            `job_count` int(11) unsigned NOT NULL DEFAULT 0,
            `pipeline_count` int(11) unsigned NOT NULL DEFAULT 0,
            `pipeline_run_rows` int(11) unsigned NOT NULL DEFAULT 0,
            `seed_value` int(11) unsigned NOT NULL DEFAULT 1,
            `include_jenkins` tinyint(1) NOT NULL DEFAULT 0,
            `config_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `metrics_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
            `created_by` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `generated_dataset_batch` (`batch_key`),
            KEY `generated_dataset_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }

    public function batchExists($batchKey)
    {
        return $this->db->where('batch_key', (string) $batchKey)->count_all_results('generated_datasets') > 0;
    }

    public function getBatch($id)
    {
        return $this->db->where('id', (int) $id)->get('generated_datasets')->row();
    }

    public function listBatches()
    {
        return $this->db->order_by('id', 'DESC')->get('generated_datasets')->result();
    }

    public function totals()
    {
        return array(
            'tmf' => $this->db->count_all('tmf'),
            'tmf_errors' => $this->db->count_all('tmf_error'),
            'pipelines' => $this->db->count_all('job_pipelines'),
            'pipeline_runs' => $this->db->count_all('job_pipeline_runs'),
            'generated_batches' => $this->db->count_all('generated_datasets')
        );
    }

    public function sampleJobNames($batchKey, $count)
    {
        $names = array();
        for ($index = 1; $index <= (int) $count; $index++) {
            $names[] = sprintf('%s-job-%03d', $batchKey, $index);
        }
        return $names;
    }

    private function insertChunk($table, &$rows)
    {
        if (empty($rows)) {
            return;
        }
        $this->db->insert_batch($table, $rows);
        $rows = array();
    }

    private function environmentAt($index, $seed, $environments)
    {
        return $environments[($index + $seed) % count($environments)];
    }

    private function statusAt($index, $seed)
    {
        // A stable weighted distribution: ready 58%, warning 14%, error 12%,
        // running 8%, cancelled 5%, and queued/other 3%.
        $slot = (($index * 37) + ($seed * 17)) % 100;
        if ($slot < 58) return 'ready';
        if ($slot < 72) return 'warning';
        if ($slot < 84) return 'error';
        if ($slot < 92) return 'running';
        if ($slot < 97) return 'cancelled';
        return 'queued';
    }

    private function buildTmfRows($options, &$errorCount)
    {
        $batchKey = $options['batch_key'];
        $rowCount = (int) $options['tmf_rows'];
        $jobNames = $this->sampleJobNames($batchKey, $options['jobs']);
        $environments = $options['environments'];
        $seed = (int) $options['seed'];
        $dimensions = array('STG_CUSTOMER', 'STG_ORDERS', 'DW_CUSTOMER', 'DW_ORDERS', 'FACT_SALES', 'DM_FINANCE', 'DQ_GOVERNANCE', 'ML_FEATURES');
        $events = array('Extract source', 'Validate schema', 'Normalize records', 'Load warehouse', 'Publish mart', 'Reconcile totals', 'Archive output', 'Refresh features');
        $tmfRows = array();
        $errorRows = array();
        $errorCount = 0;
        $started = time();

        for ($index = 0; $index < $rowCount; $index++) {
            $sequence = $index + 1;
            $status = $this->statusAt($index, $seed);
            $environment = $this->environmentAt($index, $seed, $environments);
            $jobName = $jobNames[($index + $seed) % count($jobNames)];
            $dimension = $dimensions[($index * 3 + $seed) % count($dimensions)];
            $event = $events[($index * 5 + $seed) % count($events)];
            $total = 1000 + (($index * 7919 + $seed * 101) % 250000);
            $processed = $total;
            if ($status === 'warning') $processed = max(0, $total - (($index % 250) + 1));
            if ($status === 'error') $processed = (int) floor($total * (0.30 + (($index % 50) / 100)));
            if ($status === 'running') $processed = (int) floor($total * (0.05 + (($index % 80) / 100)));
            if ($status === 'cancelled') $processed = (int) floor($total * 0.2);
            if ($status === 'queued') $processed = 0;

            $ageSeconds = (($index * 1543 + $seed * 97) % (180 * 86400));
            $startTimestamp = $started - $ageSeconds;
            if ($status === 'running' || $status === 'queued') {
                $startTimestamp = $started - (($index % 90) * 60);
            }
            $duration = 5 + (($index * 29 + $seed) % 7200);
            $activityTimestamp = min($started, $startTimestamp + $duration);
            $instanceId = sprintf('%s-tmf-%09d', $batchKey, $sequence);
            $message = sprintf('%s %s for %s: %d of %d records.', $event, $status, $environment, $processed, $total);

            $tmfRows[] = array(
                'interface_id' => sprintf('%s-if-%09d', $batchKey, $sequence),
                'status' => $status,
                'job_name' => $jobName,
                'reprocess' => $status === 'error' && $index % 2 === 0 ? 1 : 0,
                'event_text' => $event,
                'dimension' => $dimension,
                'environment' => $environment,
                'records_total' => (string) $total,
                'records_processed' => (string) $processed,
                'last_activity' => date('Y-m-d H:i:s', $activityTimestamp),
                'running_time' => gmdate('H:i:s', min($duration, 86399)),
                'distict_errors' => $status === 'error' ? 1 : 0,
                'warnings' => $status === 'warning' ? '1' : '0',
                'hostname' => sprintf('synthetic-worker-%02d', ($index % 12) + 1),
                'username' => 'dataset.generator',
                'instance_id' => $instanceId,
                'start_time' => date('Y-m-d H:i:s', $startTimestamp),
                'msg' => $message
            );

            if ($status === 'error') {
                $errorRows[] = array(
                    'tmf_id' => $instanceId,
                    'job_name' => $jobName,
                    'moment' => date('Y-m-d H:i:s', $activityTimestamp),
                    'type' => $index % 3 === 0 ? 'Data Quality' : 'Synthetic Failure',
                    'origin' => $dimension,
                    'message' => 'Generated failure for performance and error-detail testing. '.$message,
                    'code' => 7000 + ($index % 1000)
                );
                $errorCount++;
            }

            if (count($tmfRows) >= self::TMF_CHUNK_SIZE) {
                $this->insertChunk('tmf', $tmfRows);
            }
            if (count($errorRows) >= self::TMF_CHUNK_SIZE) {
                $this->insertChunk('tmf_error', $errorRows);
            }
        }

        $this->insertChunk('tmf', $tmfRows);
        $this->insertChunk('tmf_error', $errorRows);
    }

    private function buildPipelineRows($options)
    {
        $batchKey = $options['batch_key'];
        $pipelineCount = (int) $options['pipelines'];
        $runsPerPipeline = (int) $options['pipeline_runs'];
        $jobNames = $this->sampleJobNames($batchKey, $options['jobs']);
        $runStatuses = array('SUCCESS', 'SUCCESS', 'SUCCESS', 'FAILURE', 'UNSTABLE', 'ABORTED', 'RUNNING', 'QUEUED');
        $now = time();

        for ($pipelineIndex = 0; $pipelineIndex < $pipelineCount; $pipelineIndex++) {
            $nodes = array();
            $edges = array();
            $nodeCount = min(4, count($jobNames));
            for ($nodeIndex = 0; $nodeIndex < $nodeCount; $nodeIndex++) {
                $jobName = $jobNames[($pipelineIndex * 3 + $nodeIndex) % count($jobNames)];
                $nodes[] = array(
                    'id' => 'node-'.$nodeIndex,
                    'job' => $jobName,
                    'label' => $jobName,
                    'x' => 80 + ($nodeIndex * 220),
                    'y' => 100
                );
                if ($nodeIndex > 0) {
                    $edges[] = array('source' => 'node-'.($nodeIndex - 1), 'target' => 'node-'.$nodeIndex, 'condition' => 'SUCCESS');
                }
            }
            $environment = $this->environmentAt($pipelineIndex, $options['seed'], $options['environments']);
            $pipelineKey = sprintf('%s-pipeline-%03d', $batchKey, $pipelineIndex + 1);
            $createdAt = date('Y-m-d H:i:s', $now - (($pipelineIndex + 1) * 86400));
            $this->db->insert('job_pipelines', array(
                'pipeline_key' => $pipelineKey,
                'name' => sprintf('%s Pipeline %03d', $batchKey, $pipelineIndex + 1),
                'group_name' => 'Generated datasets',
                'description' => 'Synthetic end-to-end pipeline created by the admin Dataset Generator.',
                'environment' => $environment,
                'graph_json' => json_encode(array('nodes' => $nodes, 'edges' => $edges), JSON_UNESCAPED_SLASHES),
                'jenkins_job_name' => NULL,
                'sync_status' => 'sample',
                'sync_error' => NULL,
                'is_active' => 1,
                'version' => 1,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'owner' => 'Dataset Generator'
            ));
            $pipelineId = (int) $this->db->insert_id();
            $runRows = array();
            for ($runIndex = 0; $runIndex < $runsPerPipeline; $runIndex++) {
                $status = $runStatuses[($runIndex + $pipelineIndex + $options['seed']) % count($runStatuses)];
                $startedAt = $now - (($runIndex + 1) * 3600) - ($pipelineIndex * 300);
                $completedAt = in_array($status, array('RUNNING', 'QUEUED'), TRUE) ? NULL : date('Y-m-d H:i:s', $startedAt + 60 + (($runIndex * 47) % 2400));
                $runRows[] = array(
                    'pipeline_id' => $pipelineId,
                    'jenkins_queue_id' => 100000 + ($pipelineIndex * $runsPerPipeline) + $runIndex,
                    'jenkins_build_number' => $runIndex + 1,
                    'status' => $status,
                    'environment' => $environment,
                    'triggered_by' => $runIndex % 4 === 0 ? 'Jenkins schedule' : 'dataset.generator',
                    'started_at' => date('Y-m-d H:i:s', $startedAt),
                    'completed_at' => $completedAt,
                    'updated_at' => $completedAt ?: date('Y-m-d H:i:s', $startedAt + 30)
                );
            }
            $this->db->insert_batch('job_pipeline_runs', $runRows);
        }
    }

    public function createBatch($options, $createdBy)
    {
        $startedAt = microtime(TRUE);
        $now = date('Y-m-d H:i:s');
        $config = array(
            'environments' => $options['environments'],
            'jenkins_jobs' => array()
        );

        $this->db->trans_begin();
        $this->db->insert('generated_datasets', array(
            'batch_key' => $options['batch_key'],
            'profile' => $options['profile'],
            'status' => 'generating',
            'tmf_rows' => $options['tmf_rows'],
            'error_rows' => 0,
            'job_count' => $options['jobs'],
            'pipeline_count' => $options['pipelines'],
            'pipeline_run_rows' => $options['pipelines'] * $options['pipeline_runs'],
            'seed_value' => $options['seed'],
            'include_jenkins' => ! empty($options['include_jenkins']) ? 1 : 0,
            'config_json' => json_encode($config, JSON_UNESCAPED_SLASHES),
            'metrics_json' => NULL,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now
        ));
        $batchId = (int) $this->db->insert_id();

        $errorCount = 0;
        $this->buildTmfRows($options, $errorCount);
        $this->buildPipelineRows($options);
        $databaseSeconds = microtime(TRUE) - $startedAt;
        $metrics = array(
            'database_seconds' => round($databaseSeconds, 3),
            'tmf_rows_per_second' => $databaseSeconds > 0 ? round($options['tmf_rows'] / $databaseSeconds, 1) : NULL,
            'jenkins_seconds' => 0,
            'jenkins_created' => 0,
            'jenkins_failed' => 0
        );
        $this->db->where('id', $batchId)->update('generated_datasets', array(
            'status' => 'ready',
            'error_rows' => $errorCount,
            'metrics_json' => json_encode($metrics, JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s')
        ));

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return array('ok' => FALSE, 'message' => 'The database rejected the generated dataset. No rows were kept.');
        }
        $this->db->trans_commit();

        return array(
            'ok' => TRUE,
            'id' => $batchId,
            'error_rows' => $errorCount,
            'metrics' => $metrics,
            'jobs' => $this->sampleJobNames($options['batch_key'], $options['jobs'])
        );
    }

    public function recordJenkinsResult($batchId, $jobNames, $created, $failed, $seconds)
    {
        $batch = $this->getBatch($batchId);
        if (! $batch) return;
        $config = json_decode((string) $batch->config_json, TRUE);
        $metrics = json_decode((string) $batch->metrics_json, TRUE);
        if (! is_array($config)) $config = array();
        if (! is_array($metrics)) $metrics = array();
        $config['jenkins_jobs'] = array_values($jobNames);
        $metrics['jenkins_seconds'] = round((float) $seconds, 3);
        $metrics['jenkins_created'] = (int) $created;
        $metrics['jenkins_failed'] = (int) $failed;
        $this->db->where('id', (int) $batchId)->update('generated_datasets', array(
            'status' => $failed > 0 ? 'partial' : 'ready',
            'config_json' => json_encode($config, JSON_UNESCAPED_SLASHES),
            'metrics_json' => json_encode($metrics, JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s')
        ));
    }

    public function deleteBatch($id)
    {
        $batch = $this->getBatch($id);
        if (! $batch) {
            return array('ok' => FALSE, 'message' => 'Generated batch was not found.');
        }

        $batchKey = (string) $batch->batch_key;
        $this->db->trans_begin();
        $this->db->like('tmf_id', $batchKey.'-tmf-', 'after')->delete('tmf_error');
        $errors = $this->db->affected_rows();
        $this->db->like('interface_id', $batchKey.'-if-', 'after')->delete('tmf');
        $tmf = $this->db->affected_rows();
        $this->db->like('pipeline_key', $batchKey.'-pipeline-', 'after')->delete('job_pipelines');
        $pipelines = $this->db->affected_rows();
        $this->db->where('id', (int) $id)->delete('generated_datasets');

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return array('ok' => FALSE, 'message' => 'The generated batch could not be removed.');
        }
        $this->db->trans_commit();
        return array('ok' => TRUE, 'tmf_rows' => $tmf, 'error_rows' => $errors, 'pipelines' => $pipelines);
    }
}

?>
