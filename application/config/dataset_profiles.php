<?php defined('BASEPATH') OR exit('No direct script access allowed');

return array(
    'quick' => array(
        'name' => 'Quick smoke',
        'description' => 'A fast UI and filter check for local development.',
        'tmf_rows' => 500,
        'jobs' => 6,
        'pipelines' => 2,
        'pipeline_runs' => 8
    ),
    'performance' => array(
        'name' => 'Performance',
        'description' => 'A representative history for dashboard, TMF, job, and pipeline profiling.',
        'tmf_rows' => 10000,
        'jobs' => 24,
        'pipelines' => 6,
        'pipeline_runs' => 40
    ),
    'stress' => array(
        'name' => 'Stress',
        'description' => 'A large, intentionally expensive dataset for pagination and query-plan testing.',
        'tmf_rows' => 50000,
        'jobs' => 60,
        'pipelines' => 15,
        'pipeline_runs' => 100
    )
);
