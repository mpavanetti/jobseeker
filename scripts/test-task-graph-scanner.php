<?php
/**
 * Behavioural tests for TaskGraphScanner: the static read of the @dag.task
 * declarations a Python job makes. No job code is executed here or in the app.
 */
define('JOBSEEKER_TASK_GRAPH_TEST', TRUE);
require dirname(__DIR__).'/application/libraries/TaskGraphScanner.php';

$checks = 0;

function task_assert($condition, $message)
{
    global $checks;
    if (! $condition) {
        fwrite(STDERR, 'FAIL: '.$message."\n");
        exit(1);
    }
    $checks++;
}

function task_ids($result)
{
    return array_map(function ($task) { return $task['id']; }, $result['tasks']);
}

$scanner = new TaskGraphScanner();

// --- 1. A normal fan-out / fan-in graph -------------------------------------
$source = <<<'PY'
from jobseeker import dag


@dag.task(id="extract", produces=["raw_customers"], retries=2, retry_delay=15)
def extract(ctx):
    ctx.push("rows", 10)


@dag.task(id="clean_a", depends_on=["extract"], consumes=["raw_customers"])
def clean_a(ctx):
    pass


@dag.task(id="clean_b", depends_on=["extract"], description="Second branch")
def clean_b(ctx):
    pass


@dag.task(id="publish", depends_on=["clean_a", "clean_b"], track=True, dimension="DIM_CUSTOMER")
def publish(ctx):
    pass


@dag.task(id="alert", depends_on=["publish"], trigger="FAILURE")
def alert(ctx):
    pass


if __name__ == "__main__":
    dag.run()
PY;

$result = $scanner->scan(array(array('text' => $source, 'from' => 'code')));
task_assert($result['ok'], 'A valid graph must scan cleanly: '.$result['message']);
task_assert(count($result['tasks']) === 5, 'Five declared tasks were expected, got '.count($result['tasks']).'.');
task_assert($result['layers'] === array(array('extract'), array('clean_a', 'clean_b'), array('publish'), array('alert')),
    'Layers were not derived correctly: '.json_encode($result['layers']));
task_assert(count($result['edges']) === 5, 'Five edges were expected, got '.count($result['edges']).'.');

$byId = array();
foreach ($result['tasks'] as $task) {
    $byId[$task['id']] = $task;
}
task_assert($byId['extract']['produces'] === array('raw_customers'), 'produces was not parsed.');
task_assert($byId['extract']['retries'] === 2, 'retries was not parsed.');
task_assert($byId['extract']['retry_delay'] === 15, 'retry_delay was not parsed.');
task_assert($byId['clean_a']['consumes'] === array('raw_customers'), 'consumes was not parsed.');
task_assert($byId['clean_a']['depends_on'] === array('extract'), 'depends_on was not parsed.');
task_assert($byId['clean_b']['description'] === 'Second branch', 'description was not parsed.');
task_assert($byId['publish']['track'] === TRUE && $byId['publish']['dimension'] === 'DIM_CUSTOMER',
    'track / dimension were not parsed.');
task_assert($byId['alert']['trigger'] === 'FAILURE', 'trigger was not parsed.');
task_assert($byId['extract']['trigger'] === 'SUCCESS', 'The default trigger must be SUCCESS.');

// --- 2. jobseeker.task is a different decorator and must not be picked up ---
$tmfOnly = <<<'PY'
from jobseeker import task, JobSeeker


@task(event_text="Nightly load", dimension="DIM_SALES")
def load(tmf=None):
    pass
PY;
$result = $scanner->scan(array(array('text' => $tmfOnly, 'from' => 'code')));
task_assert(count($result['tasks']) === 0,
    'jobseeker.task opens a TMF transaction and must never be read as a DAG task.');

// A Celery-style decorator on an unrelated object must be ignored too.
$celery = <<<'PY'
from celery import Celery

app = Celery("worker")


@app.task(name="send_mail")
def send_mail():
    pass
PY;
$result = $scanner->scan(array(array('text' => $celery, 'from' => 'code')));
task_assert(count($result['tasks']) === 0, 'Only names bound to the JobSeeker DAG may be scanned.');

// --- 3. Alternative bindings -------------------------------------------------
$aliased = <<<'PY'
from jobseeker import dag as flow


@flow.task(id="only")
def only(ctx):
    pass
PY;
$result = $scanner->scan(array(array('text' => $aliased, 'from' => 'code')));
task_assert(task_ids($result) === array('only'), '`from jobseeker import dag as flow` must be honoured.');

$instance = <<<'PY'
from jobseeker.dag import Dag

nightly = Dag("nightly")


@nightly.task(id="first")
def first(ctx):
    pass


@nightly.task(id="second", depends_on=["first"])
def second(ctx):
    pass
PY;
$result = $scanner->scan(array(array('text' => $instance, 'from' => 'code')));
task_assert(task_ids($result) === array('first', 'second'), 'A named Dag instance must be honoured.');

$moduleForm = <<<'PY'
import jobseeker.dag


@jobseeker.dag.task(id="qualified")
def qualified(ctx):
    pass
PY;
$result = $scanner->scan(array(array('text' => $moduleForm, 'from' => 'code')));
task_assert(task_ids($result) === array('qualified'), 'The fully qualified decorator form must be honoured.');

// --- 4. The id falls back to the function name -------------------------------
$implicit = <<<'PY'
from jobseeker import dag


@dag.task()
def harvest(ctx):
    pass


@dag.task(depends_on=["harvest"])
def summarise(ctx):
    pass
PY;
$result = $scanner->scan(array(array('text' => $implicit, 'from' => 'code')));
task_assert(task_ids($result) === array('harvest', 'summarise'),
    'A task without an explicit id takes the decorated function name.');

// --- 5. Brackets and parentheses inside literals must not end the call -------
$tricky = <<<'PY'
from jobseeker import dag


@dag.task(id="weird", description="handles ) and ] safely", depends_on=[])
def weird(ctx):
    pass


@dag.task(
    id="multiline",
    depends_on=["weird"],
    retries=1,
)
def multiline(ctx):
    pass
PY;
$result = $scanner->scan(array(array('text' => $tricky, 'from' => 'code')));
task_assert(task_ids($result) === array('weird', 'multiline'),
    'Balanced-argument reading failed: '.json_encode(task_ids($result)));
$byId = array();
foreach ($result['tasks'] as $task) {
    $byId[$task['id']] = $task;
}
task_assert($byId['weird']['description'] === 'handles ) and ] safely',
    'A closing bracket inside a string literal must not truncate the call.');
task_assert($byId['weird']['depends_on'] === array(), 'An empty depends_on list must yield no edges.');
task_assert($byId['multiline']['retries'] === 1, 'A multi-line decorator must still be parsed.');

// --- 6. Cycles are refused ---------------------------------------------------
$cycle = <<<'PY'
from jobseeker import dag


@dag.task(id="a", depends_on=["b"])
def a(ctx):
    pass


@dag.task(id="b", depends_on=["a"])
def b(ctx):
    pass
PY;
$result = $scanner->scan(array(array('text' => $cycle, 'from' => 'code')));
task_assert(! $result['ok'], 'A cyclic task graph must be refused.');
task_assert(strpos($result['message'], 'cycle') !== FALSE, 'The cycle message must say so: '.$result['message']);

$selfEdge = <<<'PY'
from jobseeker import dag


@dag.task(id="loop", depends_on=["loop"])
def loop(ctx):
    pass
PY;
$result = $scanner->scan(array(array('text' => $selfEdge, 'from' => 'code')));
task_assert(! $result['ok'], 'A self-dependency must be refused.');

// --- 7. An unknown upstream warns but still draws ---------------------------
$dangling = <<<'PY'
from jobseeker import dag


@dag.task(id="child", depends_on=["missing_parent"])
def child(ctx):
    pass
PY;
$result = $scanner->scan(array(array('text' => $dangling, 'from' => 'code')));
task_assert($result['ok'], 'A dangling dependency must not break the graph.');
task_assert(count($result['edges']) === 0, 'A dangling dependency must not create an edge.');
task_assert(count($result['warnings']) === 1, 'A dangling dependency must warn.');

// --- 8. Several files make one graph, first declaration wins ----------------
$entry = <<<'PY'
from jobseeker import dag
from steps.extract import extract
from steps.load import load
PY;
$stepOne = <<<'PY'
from jobseeker import dag


@dag.task(id="extract")
def extract(ctx):
    pass
PY;
$stepTwo = <<<'PY'
from jobseeker import dag


@dag.task(id="load", depends_on=["extract"])
def load(ctx):
    pass
PY;
$result = $scanner->scan(array(
    array('text' => $entry, 'from' => 'code'),
    array('text' => $stepOne, 'from' => 'code'),
    array('text' => $stepTwo, 'from' => 'code'),
    array('text' => $stepOne, 'from' => 'code')
));
task_assert(task_ids($result) === array('extract', 'load'),
    'A multi-file workspace must produce one graph without duplicate nodes.');

// --- 9. A job with no tasks is valid and empty ------------------------------
$plain = <<<'PY'
import os


def main():
    print(os.getenv("ENVIRONMENT"))
PY;
$result = $scanner->scan(array(array('text' => $plain, 'from' => 'code')));
task_assert($result['ok'] && $result['tasks'] === array(),
    'A single-script job must scan to an empty graph, not an error.');

// --- 10. Invalid ids are dropped rather than poisoning the graph ------------
$badId = <<<'PY'
from jobseeker import dag


@dag.task(id="9invalid")
def fine(ctx):
    pass
PY;
$result = $scanner->scan(array(array('text' => $badId, 'from' => 'code')));
task_assert($result['tasks'] === array(), 'An id that the runtime would refuse must not be scanned in.');

echo "Task graph scanner checks passed ({$checks} assertions).\n";
