<?php
define('JOBSEEKER_WORKFLOW_TEST', TRUE);
require dirname(__DIR__).'/application/libraries/WorkflowCompiler.php';

function workflow_assert($condition, $message)
{
    if (! $condition) {
        fwrite(STDERR, $message."\n");
        exit(1);
    }
}

$compiler = new WorkflowCompiler();
$nodes = array(
    array('id' => 'extract', 'job' => 'extract-job', 'label' => 'Extract', 'x' => 10, 'y' => 20),
    array('id' => 'load_a', 'job' => 'load-a', 'label' => 'Load A', 'x' => 30, 'y' => 40),
    array('id' => 'load_b', 'job' => 'load-b', 'label' => 'Load B', 'x' => 30, 'y' => 140),
    array('id' => 'publish', 'job' => 'publish-job', 'label' => 'Publish', 'x' => 60, 'y' => 80)
);
$edges = array(
    array('source' => 'extract', 'target' => 'load_a', 'condition' => 'SUCCESS'),
    array('source' => 'extract', 'target' => 'load_b', 'condition' => 'SUCCESS'),
    array('source' => 'load_a', 'target' => 'publish', 'condition' => 'ALWAYS'),
    array('source' => 'load_b', 'target' => 'publish', 'condition' => 'FAILURE')
);

$valid = $compiler->validateGraph($nodes, $edges);
workflow_assert($valid['ok'], $valid['message']);
workflow_assert($valid['layers'] === array(array('extract'), array('load_a', 'load_b'), array('publish')), 'Parallel layers were not compiled correctly.');

$cycle = $compiler->validateGraph($nodes, array_merge($edges, array(
    array('source' => 'publish', 'target' => 'extract', 'condition' => 'ALWAYS')
)));
workflow_assert(! $cycle['ok'], 'A cyclic graph was accepted.');

$duplicate = $compiler->validateGraph($nodes, array_merge($edges, array($edges[0])));
workflow_assert(! $duplicate['ok'], 'A duplicate connection was accepted.');

$script = $compiler->compileScript("Daily load's pipeline", 'DEV', $nodes, $edges);
foreach (array(
    'def pending = [',
    'parallel branches',
    'propagate: false',
    'edge.condition == "SUCCESS"',
    'edge.condition == "FAILURE"',
    'edge.condition == "ALWAYS"',
    'JOBSEEKER_PIPELINE_NODE',
    "Daily load\\'s pipeline"
) as $needle) {
    workflow_assert(strpos($script, $needle) !== FALSE, 'Generated script is missing: '.$needle);
}
workflow_assert(strpos($script, 'new LinkedHashSet') === FALSE, 'Generated script contains a sandbox-rejected constructor.');

echo "Workflow compiler tests passed.\n";
