#!/usr/bin/env python3
"""Behavioural tests for jobseeker.dag - the task DAG that runs inside one job.

These run the real scheduler: real threads, real retries, real trigger rules.
Only the database is stubbed, because a job must run its tasks whether or not it
can reach MariaDB.
"""

from __future__ import annotations

import contextlib
import io
import os
import pathlib
import re
import sys
import tempfile
import threading
import time

ROOT = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "application/third_party/python/jobseeker_sdk/src"))

# Never touch a database from these tests; the store has its own test below.
os.environ["JOBSEEKER_DAG_STATE"] = "0"
os.environ.pop("JOBSEEKER_DAG_RESUME", None)
os.environ.pop("JOBSEEKER_DAG_TASKS", None)
os.environ.pop("BUILD_NUMBER", None)

from jobseeker import dag as dagmod  # noqa: E402

CHECKS = 0


def check(condition, message):
    global CHECKS
    assert condition, message
    CHECKS += 1


@contextlib.contextmanager
def captured():
    """Capture stdout and stderr together, the way Jenkins merges them."""

    buffer = io.StringIO()
    with contextlib.redirect_stdout(buffer), contextlib.redirect_stderr(buffer):
        yield buffer


def new_dag(name="test"):
    return dagmod.Dag(name)


def run(graph, **kwargs):
    kwargs.setdefault("raise_on_failure", False)
    kwargs.setdefault("exit_on_failure", False)
    kwargs.setdefault("state", False)
    kwargs.setdefault("xcom_path", os.path.join(tempfile.mkdtemp(), "values.json"))
    return graph.run(**kwargs)


# --- 1. Declaration and validation ------------------------------------------

graph = new_dag()


@graph.task(id="only")
def only_task():
    return 1


check(len(graph) == 1, "One declared task was expected.")
check("only" in graph, "The task id must be registered.")

for bad_id in ("9bad", "", "has space", "a" * 80):
    duplicate = new_dag()
    try:
        duplicate.add(dagmod.TaskSpec(id=bad_id, function=lambda: None))
        check(False, "Task id %r should have been refused." % bad_id)
    except dagmod.DagDefinitionError:
        CHECKS += 1

duplicate = new_dag()
duplicate.add(dagmod.TaskSpec(id="same", function=lambda: None))
try:
    duplicate.add(dagmod.TaskSpec(id="same", function=lambda: None))
    check(False, "A duplicate task id should have been refused.")
except dagmod.DagDefinitionError:
    CHECKS += 1

bad_trigger = new_dag()
try:
    bad_trigger.add(dagmod.TaskSpec(id="x", function=lambda: None, trigger="MAYBE"))
    check(False, "An unknown trigger should have been refused.")
except dagmod.DagDefinitionError:
    CHECKS += 1

unknown = new_dag()
unknown.add(dagmod.TaskSpec(id="child", function=lambda: None, depends_on=("ghost",)))
try:
    unknown.validate()
    check(False, "A dependency on an undeclared task should have been refused.")
except dagmod.DagDefinitionError as error:
    check("ghost" in str(error), "The message must name the missing task.")

cyclic = new_dag()
cyclic.add(dagmod.TaskSpec(id="a", function=lambda: None, depends_on=("b",)))
cyclic.add(dagmod.TaskSpec(id="b", function=lambda: None, depends_on=("a",)))
try:
    cyclic.validate()
    check(False, "A cyclic graph should have been refused.")
except dagmod.DagDefinitionError as error:
    check("cycle" in str(error), "The message must say the graph has a cycle.")

self_edge = new_dag()
self_edge.add(dagmod.TaskSpec(id="loop", function=lambda: None, depends_on=("loop",)))
try:
    self_edge.validate()
    check(False, "A self-dependency should have been refused.")
except dagmod.DagDefinitionError:
    CHECKS += 1

empty = new_dag()
try:
    empty.validate()
    check(False, "An empty graph should have been refused at run time.")
except dagmod.DagDefinitionError:
    CHECKS += 1


# --- 2. Dependency order -----------------------------------------------------

graph = new_dag()
order = []
lock = threading.Lock()


def record(name):
    with lock:
        order.append(name)


@graph.task(id="extract")
def extract(ctx):
    record("extract")


@graph.task(id="clean_a", depends_on=["extract"])
def clean_a(ctx):
    record("clean_a")


@graph.task(id="clean_b", depends_on=["extract"])
def clean_b(ctx):
    record("clean_b")


@graph.task(id="publish", depends_on=["clean_a", "clean_b"])
def publish(ctx):
    record("publish")


result = run(graph)
check(result.status == dagmod.SUCCESS, "A clean graph must succeed.")
check(order[0] == "extract", "The root task must run first.")
check(order[-1] == "publish", "The join task must run last.")
check(set(order) == {"extract", "clean_a", "clean_b", "publish"}, "Every task must run exactly once.")
check(len(order) == 4, "No task may run twice.")
check(graph.validate()["layers"] == [["extract"], ["clean_a", "clean_b"], ["publish"]],
      "Layers were not derived correctly.")


# --- 3. Independent branches really overlap ---------------------------------

graph = new_dag()
started = []


@graph.task(id="root")
def root(ctx):
    pass


@graph.task(id="slow_a", depends_on=["root"])
def slow_a(ctx):
    started.append(time.time())
    time.sleep(0.35)


@graph.task(id="slow_b", depends_on=["root"])
def slow_b(ctx):
    started.append(time.time())
    time.sleep(0.35)


begin = time.time()
result = run(graph, max_parallel=2)
elapsed = time.time() - begin
check(result.status == dagmod.SUCCESS, "The parallel graph must succeed.")
check(elapsed < 0.6, "Independent tasks must overlap; the run took %.2fs." % elapsed)

# ...and must not overlap when the run is serialised.
graph_serial = new_dag()


@graph_serial.task(id="a")
def serial_a(ctx):
    time.sleep(0.2)


@graph_serial.task(id="b")
def serial_b(ctx):
    time.sleep(0.2)


begin = time.time()
run(graph_serial, max_parallel=1)
elapsed = time.time() - begin
check(elapsed >= 0.4, "max_parallel=1 must serialise independent tasks; took %.2fs." % elapsed)


# --- 4. Retries --------------------------------------------------------------

graph = new_dag()
attempts = []


@graph.task(id="flaky", retries=2)
def flaky(ctx):
    attempts.append(ctx.attempt)
    if ctx.attempt < 3:
        raise RuntimeError("transient")


result = run(graph)
check(result.status == dagmod.SUCCESS, "A task must succeed on its last allowed attempt.")
check(attempts == [1, 2, 3], "Every attempt must be numbered in order: %r." % attempts)
check(result.results["flaky"].attempt == 3, "The reported attempt must be the one that succeeded.")

graph = new_dag()
calls = []


@graph.task(id="doomed", retries=1)
def doomed(ctx):
    calls.append(1)
    raise RuntimeError("permanent")


result = run(graph)
check(result.status == dagmod.FAILURE, "A task that exhausts its retries must fail the run.")
check(len(calls) == 2, "retries=1 means two attempts, not %d." % len(calls))
check("permanent" in result.results["doomed"].message, "The failure message must be kept.")


# --- 5. Trigger rules and skip propagation ----------------------------------

graph = new_dag()
ran = []


@graph.task(id="source")
def source(ctx):
    ran.append("source")
    raise ValueError("bad row")


@graph.task(id="downstream", depends_on=["source"])
def downstream(ctx):
    ran.append("downstream")


@graph.task(id="alert", depends_on=["source"], trigger="FAILURE")
def alert(ctx):
    ran.append("alert")


@graph.task(id="cleanup", depends_on=["source", "downstream"], trigger="ALWAYS")
def cleanup(ctx):
    ran.append("cleanup")


@graph.task(id="after_downstream", depends_on=["downstream"])
def after_downstream(ctx):
    ran.append("after_downstream")


result = run(graph)
statuses = result.statuses()
check(statuses["source"] == dagmod.FAILURE, "The failing task must be FAILURE.")
check(statuses["downstream"] == dagmod.UPSTREAM_FAILED,
      "A SUCCESS-triggered task whose upstream failed must be UPSTREAM_FAILED.")
check(statuses["alert"] == dagmod.SUCCESS, "A FAILURE-triggered task must run when its upstream fails.")
check(statuses["cleanup"] == dagmod.SUCCESS, "An ALWAYS-triggered task must run regardless.")
check(statuses["after_downstream"] == dagmod.UPSTREAM_FAILED,
      "UPSTREAM_FAILED must propagate down the chain.")
check("downstream" not in ran and "after_downstream" not in ran,
      "A blocked task's body must never be called.")
check(result.status == dagmod.FAILURE, "A run with a failed task must report FAILURE.")

# A FAILURE-triggered task is skipped, not run, when everything succeeded.
graph = new_dag()


@graph.task(id="fine")
def fine(ctx):
    pass


@graph.task(id="never", depends_on=["fine"], trigger="FAILURE")
def never(ctx):
    raise AssertionError("This task must not run.")


result = run(graph)
check(result.statuses()["never"] == dagmod.SKIPPED,
      "A FAILURE-triggered task must be skipped when no upstream failed.")
check(result.status == dagmod.SUCCESS, "A skipped task must not fail the run.")

# A skipped upstream skips its SUCCESS-triggered downstream.
graph = new_dag()


@graph.task(id="ok")
def ok(ctx):
    pass


@graph.task(id="skipped_parent", depends_on=["ok"], trigger="FAILURE")
def skipped_parent(ctx):
    pass


@graph.task(id="skipped_child", depends_on=["skipped_parent"])
def skipped_child(ctx):
    raise AssertionError("This task must not run.")


result = run(graph)
check(result.statuses()["skipped_child"] == dagmod.SKIPPED, "Skips must cascade.")


# --- 6. fail_fast ------------------------------------------------------------

graph = new_dag()
touched = []


@graph.task(id="boom")
def boom(ctx):
    raise RuntimeError("stop")


@graph.task(id="independent")
def independent(ctx):
    time.sleep(0.05)
    touched.append("independent")


result = run(graph, fail_fast=True, max_parallel=1)
check(result.status == dagmod.FAILURE, "fail_fast must still report FAILURE.")
check(result.statuses()["independent"] in (dagmod.SKIPPED, dagmod.SUCCESS),
      "An independent task is either finished or abandoned, never left pending.")

graph = new_dag()
reached = []


@graph.task(id="fails")
def fails(ctx):
    raise RuntimeError("stop")


@graph.task(id="other")
def other(ctx):
    reached.append("other")


result = run(graph, fail_fast=False, max_parallel=1)
check("other" in reached, "Without fail_fast, an independent branch must still run.")


# --- 7. Values between tasks -------------------------------------------------

graph = new_dag()
pulled = {}


@graph.task(id="producer")
def producer(ctx):
    ctx.push("rows", 1200)
    ctx.push("watermark", "2026-09-22")


@graph.task(id="consumer", depends_on=["producer"])
def consumer(ctx):
    pulled["rows"] = ctx.pull("producer", "rows")
    pulled["watermark"] = ctx.pull("producer", "watermark")
    pulled["missing"] = ctx.pull("producer", "nope", "fallback")
    pulled["upstream"] = dict(ctx.upstream)


result = run(graph)
check(result.status == dagmod.SUCCESS, "The value-passing graph must succeed.")
check(pulled["rows"] == 1200, "A pushed value must arrive downstream.")
check(pulled["watermark"] == "2026-09-22", "Several values per task must survive.")
check(pulled["missing"] == "fallback", "An absent key must return the default.")
check(pulled["upstream"] == {"producer": dagmod.SUCCESS}, "Upstream statuses must be visible to a task.")

# Values are written to disk so a future one-process-per-task backend reads them.
store_path = os.path.join(tempfile.mkdtemp(), "values.json")
graph = new_dag()


@graph.task(id="writer")
def writer(ctx):
    ctx.push("key", [1, 2, 3])


run(graph, xcom_path=store_path)
check(os.path.isfile(store_path), "The value store must be persisted.")
check(dagmod.XComStore(store_path).pull("writer", "key") == [1, 2, 3],
      "A persisted value must be readable by a fresh store.")

# Oversized and unserialisable payloads are refused with an actionable message.
graph = new_dag()
errors = []


@graph.task(id="too_big")
def too_big(ctx):
    try:
        ctx.push("blob", "x" * (dagmod.XCOM_VALUE_LIMIT + 10))
    except ValueError as error:
        errors.append(str(error))


@graph.task(id="not_json")
def not_json(ctx):
    try:
        ctx.push("obj", object())
    except ValueError as error:
        errors.append(str(error))


run(graph)
check(len(errors) == 2, "Both oversized and unserialisable values must be refused.")
check(all("Data Asset" in message for message in errors),
      "The refusal must point at Data Assets as the right home for large payloads.")


# --- 8. Task functions may ignore the context -------------------------------

graph = new_dag()
calls = []


@graph.task(id="no_args")
def no_args():
    calls.append("no_args")


@graph.task(id="with_ctx", depends_on=["no_args"])
def with_ctx(ctx):
    calls.append(ctx.task_id)


result = run(graph)
check(result.status == dagmod.SUCCESS, "A task function may take no arguments.")
check(calls == ["no_args", "with_ctx"], "Both signatures must be supported: %r." % calls)


# --- 9. Console markers -------------------------------------------------------

graph = new_dag()


@graph.task(id="marked")
def marked(ctx):
    print("job output")


@graph.task(id="failing", depends_on=["marked"])
def failing(ctx):
    raise ValueError("nope")


@graph.task(id="blocked", depends_on=["failing"])
def blocked(ctx):
    pass


with captured() as buffer:
    run(graph)
output = buffer.getvalue()

marker = re.compile(r"^\[JobSeeker Task\]\s+([A-Za-z][A-Za-z0-9_-]{0,63})\s*\|\s*([A-Z_]+)\b")
markers = [marker.match(line).groups() for line in output.splitlines() if marker.match(line)]
check(("marked", "RUNNING") in markers, "A task must announce that it started.")
check(("marked", "SUCCESS") in markers, "A task must announce that it succeeded.")
check(("failing", "FAILURE") in markers, "A failing task must announce its failure.")
check(("blocked", "UPSTREAM_FAILED") in markers, "A blocked task must announce why it did not run.")
check(output.count("[JobSeeker DAG] start") == 1, "The run must announce its start once.")
check("[JobSeeker DAG] finish | FAILURE" in output, "The run must announce its outcome.")
check("job output" in output, "A task's own stdout must not be swallowed.")
check("ValueError: nope" in output, "A task's traceback must reach the console.")


# --- 9b. Output is attributed to the task that wrote it ----------------------
#
# Tasks run concurrently in one process and share one console. Unless every line
# carries its owner, a traceback raised by one task lands under another task's
# heading and paints a task that succeeded red.

graph = new_dag()


@graph.task(id="talker")
def talker(ctx):
    print("plain print from a task")
    ctx.log("through ctx.log")
    sys.stderr.write("written to stderr\n")
    print("no trailing newline", end="")


@graph.task(id="thrower", depends_on=["talker"])
def thrower(ctx):
    raise ValueError("the traceback belongs to thrower")


with captured() as buffer:
    run(graph)
lines = buffer.getvalue().splitlines()

owned = [line for line in lines if line.startswith("[talker] ")]
check("[talker] plain print from a task" in lines, "A bare print() must be tagged with the task id.")
check("[talker] through ctx.log" in lines, "ctx.log must be tagged exactly once, not twice.")
check(not any(line.startswith("[talker] [talker]") for line in lines),
      "Output must never be double-tagged.")
check("[talker] written to stderr" in lines, "stderr must be tagged too; tracebacks arrive that way.")
check("[talker] no trailing newline" in lines,
      "A task's last line must be flushed even without a trailing newline.")
check(all(not line.startswith("[JobSeeker") for line in owned),
      "A task tag must never be applied to a DAG marker.")
check(any(line.startswith("[thrower] ") and "ValueError" in line for line in lines),
      "A traceback must be attributed to the task that raised it.")
check(not any(line.startswith("[talker] ") and "ValueError" in line for line in lines),
      "A traceback must never be attributed to a task that succeeded.")

markers = [line for line in lines if line.startswith("[JobSeeker")]
check(len(markers) >= 5, "Markers must still be emitted untagged.")

# The real streams must be handed back whatever happened.
check(sys.stdout is not None and not hasattr(sys.stdout, "write_marker"),
      "The tagging stream must be uninstalled when the run ends.")


# --- 10. Describe mode --------------------------------------------------------

graph = new_dag("nightly")


@graph.task(id="first", produces=["curated"], retries=2)
def first(ctx):
    raise AssertionError("describe must not execute tasks")


@graph.task(id="second", depends_on=["first"], consumes=["curated"], trigger="ALWAYS")
def second(ctx):
    raise AssertionError("describe must not execute tasks")


with captured() as buffer:
    manifest = dagmod.describe(graph)
described = buffer.getvalue()
check("JOBSEEKER_DAG_MANIFEST " in described, "describe() must emit a machine readable manifest line.")
check(manifest["order"] == ["first", "second"], "The manifest must carry the topological order.")
check(manifest["layers"] == [["first"], ["second"]], "The manifest must carry the layers.")
check(manifest["edges"] == [{"source": "first", "target": "second", "condition": "ALWAYS"}],
      "The manifest must carry the edges with their trigger: %r" % manifest["edges"])
check(manifest["tasks"][0]["produces"] == ["curated"], "The manifest must carry asset declarations.")
check(manifest["tasks"][0]["retries"] == 2, "The manifest must carry retry policy.")

os.environ["JOBSEEKER_DAG_DESCRIBE"] = "1"
try:
    with captured() as buffer:
        outcome = graph.run(state=False)
    check("JOBSEEKER_DAG_MANIFEST " in buffer.getvalue(),
          "JOBSEEKER_DAG_DESCRIBE=1 must print the manifest instead of running.")
    check(outcome.status == dagmod.SUCCESS, "Describe mode must not report a failure.")
finally:
    os.environ.pop("JOBSEEKER_DAG_DESCRIBE", None)


# --- 11. Running a subset -----------------------------------------------------

graph = new_dag()
executed = []


@graph.task(id="one")
def one(ctx):
    executed.append("one")


@graph.task(id="two", depends_on=["one"])
def two(ctx):
    executed.append("two")


@graph.task(id="three", depends_on=["two"])
def three(ctx):
    executed.append("three")


os.environ["JOBSEEKER_DAG_TASKS"] = "two"
try:
    result = run(graph)
finally:
    os.environ.pop("JOBSEEKER_DAG_TASKS", None)

check(executed == ["two"], "Only the selected task may run: %r." % executed)
check(result.statuses()["two"] == dagmod.SUCCESS,
      "A selected task must not be blocked by an unselected upstream.")
check(result.statuses()["one"] == dagmod.SKIPPED, "An unselected task must be skipped.")
check(result.status == dagmod.SUCCESS, "Skipping unselected tasks must not fail the run.")

os.environ["JOBSEEKER_DAG_TASKS"] = "ghost"
try:
    graph.run(state=False, raise_on_failure=False, exit_on_failure=False)
    check(False, "Selecting a task that is not declared must be refused.")
except dagmod.DagDefinitionError:
    CHECKS += 1
finally:
    os.environ.pop("JOBSEEKER_DAG_TASKS", None)


# --- 12. Resume ---------------------------------------------------------------

class FakeStore:
    """Stands in for job_task_runs so resume can be tested without MariaDB."""

    def __init__(self, enabled=True):
        self.enabled = enabled
        self.rows = []
        self.graphs = []

    def record(self, row):
        self.rows.append(dict(row))
        return True

    def save_graph(self, job_name, environment, graph):
        self.graphs.append((job_name, environment, graph))
        return True

    def completed_tasks(self, run_key):
        return {"one": dagmod.SUCCESS, "two": dagmod.SUCCESS} if run_key == "previous-run" else {}

    def close(self):
        pass


graph = new_dag()
executed = []


@graph.task(id="one")
def one(ctx):
    executed.append("one")


@graph.task(id="two", depends_on=["one"])
def two(ctx):
    executed.append("two")


@graph.task(id="three", depends_on=["two"])
def three(ctx):
    executed.append("three")


original_store = dagmod.TaskStateStore
store = FakeStore()
dagmod.TaskStateStore = lambda enabled=True: store
os.environ["JOBSEEKER_DAG_RESUME"] = "previous-run"
try:
    result = run(graph, state=True)
finally:
    os.environ.pop("JOBSEEKER_DAG_RESUME", None)
    dagmod.TaskStateStore = original_store

check(executed == ["three"], "Resume must re-run only what did not succeed: %r." % executed)
check(result.statuses() == {"one": dagmod.SUCCESS, "two": dagmod.SUCCESS, "three": dagmod.SUCCESS},
      "Restored tasks must report their previous success.")
check(any(row["task_key"] == "three" and row["status"] == dagmod.SUCCESS for row in store.rows),
      "The resumed task must still be recorded.")
check(store.graphs and store.graphs[0][2]["order"] == ["one", "two", "three"],
      "The runtime must publish its manifest to the graph store.")


# --- 13. State recording -------------------------------------------------------

graph = new_dag()


@graph.task(id="alpha", retries=1)
def alpha(ctx):
    if ctx.attempt == 1:
        raise RuntimeError("first attempt fails")


@graph.task(id="beta", depends_on=["alpha"])
def beta(ctx):
    pass


store = FakeStore()
dagmod.TaskStateStore = lambda enabled=True: store
try:
    result = run(graph, state=True, job="nightly", environment="QA")
finally:
    dagmod.TaskStateStore = original_store

alpha_rows = [row for row in store.rows if row["task_key"] == "alpha"]
check(any(row["attempt"] == 1 and row["status"] == dagmod.FAILURE for row in alpha_rows),
      "A failed attempt must be recorded as its own attempt row.")
check(any(row["attempt"] == 2 and row["status"] == dagmod.SUCCESS for row in alpha_rows),
      "The successful retry must be recorded as attempt 2.")
check(all(row["job_name"] == "nightly" and row["environment"] == "QA" for row in store.rows),
      "Every row must carry the job and environment.")
check(all(row["run_key"] == result.run_key for row in store.rows),
      "Every row must carry the same run key.")


# --- 14. Telemetry never fails a run ------------------------------------------

class ExplodingStore(FakeStore):
    def record(self, row):
        raise RuntimeError("database is down")

    def save_graph(self, job_name, environment, graph):
        raise RuntimeError("database is down")


graph = new_dag()


@graph.task(id="resilient")
def resilient(ctx):
    return "done"


dagmod.TaskStateStore = lambda enabled=True: ExplodingStore()
try:
    failed = False
    try:
        result = run(graph, state=True)
    except Exception:  # noqa: BLE001
        failed = True
finally:
    dagmod.TaskStateStore = original_store

check(failed, "This stub raises deliberately, proving the next assertion is meaningful.")

# The real store swallows its own errors, which is what actually protects a run.
real_store = dagmod.TaskStateStore(enabled=True)
real_store._connect = lambda: (_ for _ in ()).throw(RuntimeError("no database here"))
with captured() as buffer:
    recorded = real_store.record({"run_key": "k", "job_name": "j", "task_key": "t", "status": "SUCCESS"})
check(recorded is False, "An unreachable database must report failure, not raise.")
check(real_store.enabled is False, "The store must go quiet after its first failure.")
check("task state is not being recorded" in buffer.getvalue(),
      "The operator must be told once that run state is missing.")
with captured() as buffer:
    real_store.record({"run_key": "k", "job_name": "j", "task_key": "t2", "status": "SUCCESS"})
check(buffer.getvalue() == "", "The warning must not repeat for every task.")


# --- 15. Exit code for Jenkins -------------------------------------------------

graph = new_dag()


@graph.task(id="explodes")
def explodes(ctx):
    raise RuntimeError("bad")


raised = None
try:
    graph.run(state=False, exit_on_failure=True,
              xcom_path=os.path.join(tempfile.mkdtemp(), "values.json"))
except SystemExit as error:
    raised = error
check(raised is not None and raised.code == 1,
      "A failed DAG must exit non-zero so Jenkins marks the build red.")

raised = None
try:
    graph.run(state=False, exit_on_failure=False, raise_on_failure=True,
              xcom_path=os.path.join(tempfile.mkdtemp(), "values.json"))
except dagmod.DagRunError as error:
    raised = error
check(raised is not None and "explodes" in str(raised),
      "Outside Jenkins, a failed DAG must raise naming the failed tasks.")


# --- 16. The module level DAG is the documented entry point -------------------

check(callable(dagmod.task), "jobseeker.dag.task must be callable.")
check(callable(dagmod.run), "jobseeker.dag.run must be callable.")
check(dagmod.current() is not None, "The module level DAG must exist.")

import jobseeker  # noqa: E402

check(jobseeker.dag is dagmod, "`jobseeker.dag` must resolve to the same module.")
check("dag" in jobseeker.__all__, "`dag` must be part of the SDK's public API.")
check(hasattr(jobseeker, "task"), "jobseeker.task must remain available for TMF tracking.")
check(jobseeker.task is not dagmod.task, "The TMF decorator and the DAG decorator must stay distinct.")

# --- 17. TMF integration -----------------------------------------------------
#
# A task that runs is a transaction, so it should appear on the Results page
# without the author having had to ask; and the row has to say which run and
# which task it belongs to, or it is just a loose row with a job name on it.

check(dagmod.TaskSpec(id="a", function=lambda: None).track is None,
      "A task states no tracking preference by default.")
check(dagmod.TaskSpec(id="a", function=lambda: None).tracks(True) is True,
      "Left unset, tracking follows a run that records state.")
check(dagmod.TaskSpec(id="a", function=lambda: None).tracks(False) is False,
      "Left unset, tracking is off when the run records nothing.")
check(dagmod.TaskSpec(id="a", function=lambda: None, track=False).tracks(True) is False,
      "An explicit opt-out is honoured even on a recorded run.")
check(dagmod.TaskSpec(id="a", function=lambda: None, track=True).tracks(False) is True,
      "An explicit opt-in is honoured even when the run records nothing.")

graph = new_dag()


@graph.task(id="declared_default")
def declared_default(ctx):
    pass


@graph.task(id="declared_off", track=False)
def declared_off(ctx):
    pass


manifest = graph.manifest()
tracked = {task["id"]: task["track"] for task in manifest["tasks"]}
check(tracked["declared_default"] is True, "The manifest shows a default task as tracked.")
check(tracked["declared_off"] is False, "The manifest shows an opted-out task as untracked.")


class FakeTransaction:
    def __init__(self, **kwargs):
        self.kwargs = kwargs
        self.instance_id = "fake-" + str(kwargs.get("task_key", ""))
        self.entered = False
        self.exited = None
        self.progress_calls = []

    def __enter__(self):
        self.entered = True
        return self

    def __exit__(self, exc_type, exc_value, traceback_object):
        self.exited = exc_type
        return None

    def progress(self, processed=None, total=None, msg=None):
        self.progress_calls.append({"processed": processed, "total": total, "msg": msg})
        return True


class FakeClient:
    opened = []

    def __init__(self, environment=None, job=None, install_signal_handlers=True):
        self.environment = environment
        self.job = job
        self.install_signal_handlers = install_signal_handlers
        self.closed = False

    def task(self, **kwargs):
        transaction = FakeTransaction(**kwargs)
        FakeClient.opened.append(transaction)
        return transaction

    def close(self):
        self.closed = True


graph = new_dag()
seen = {}


@graph.task(id="reports", description="Load the batch", dimension="DIM_ORDERS")
def reports(ctx):
    seen["tmf"] = ctx.tmf
    ctx.progress(total=1200, processed=1180, msg="loaded")


@graph.task(id="untracked", depends_on=["reports"], track=False)
def untracked(ctx):
    seen["untracked_tmf"] = ctx.tmf


import jobseeker as sdk  # noqa: E402

FakeClient.opened = []
original_client = sdk.JobSeeker
sdk.JobSeeker = FakeClient
store = FakeStore()
dagmod.TaskStateStore = lambda enabled=True: store
try:
    result = run(graph, state=True, job="nightly", environment="QA")
finally:
    sdk.JobSeeker = original_client
    dagmod.TaskStateStore = original_store

check(result.status == dagmod.SUCCESS, "A tracked run must still succeed.")
check(len(FakeClient.opened) == 1, "Only the tracked task opens a transaction: %d." % len(FakeClient.opened))

transaction = FakeClient.opened[0]
check(transaction.kwargs["run_key"] == result.run_key, "The transaction must carry the run key.")
check(transaction.kwargs["task_key"] == "reports", "The transaction must carry the task id.")
check(transaction.kwargs["dimension"] == "DIM_ORDERS", "A declared dimension must be used.")
check(transaction.kwargs["event_text"] == "Load the batch", "The description becomes the event text.")
check(transaction.entered and transaction.exited is None, "A successful task closes its transaction cleanly.")
check(transaction.progress_calls == [{"processed": 1180, "total": 1200, "msg": "loaded"}],
      "ctx.progress must reach the transaction: %r" % transaction.progress_calls)
check(seen["untracked_tmf"] is None, "A task that opted out gets no transaction.")
check(any(row["task_key"] == "reports" and row.get("tmf_instance_id") == transaction.instance_id
          for row in store.rows),
      "The task's run row must point at its transaction, which is how the two tables join.")

# A task with no dimension of its own is filed under its task id, so the
# Results page groups a DAG's tasks the way a person would expect.
graph = new_dag()


@graph.task(id="plain")
def plain(ctx):
    pass


FakeClient.opened = []
sdk.JobSeeker = FakeClient
store = FakeStore()
dagmod.TaskStateStore = lambda enabled=True: store
try:
    run(graph, state=True)
finally:
    sdk.JobSeeker = original_client
    dagmod.TaskStateStore = original_store

check(FakeClient.opened[0].kwargs["dimension"] == "plain",
      "A task without a dimension is filed under its own id.")
check(FakeClient.opened[0].kwargs["event_text"] == "plain",
      "A task without a description uses its id as the event text.")

# A failing tracked task must hand its exception to the transaction, so TMF
# records the error rather than a silent success.
graph = new_dag()


@graph.task(id="breaks")
def breaks(ctx):
    raise ValueError("bad batch")


FakeClient.opened = []
sdk.JobSeeker = FakeClient
store = FakeStore()
dagmod.TaskStateStore = lambda enabled=True: store
try:
    result = run(graph, state=True)
finally:
    sdk.JobSeeker = original_client
    dagmod.TaskStateStore = original_store

check(result.status == dagmod.FAILURE, "A failing tracked task still fails the run.")
check(FakeClient.opened[0].exited is ValueError,
      "The transaction must be closed with the exception, so TMF records the error.")

# Turning tracking off for a run leaves the graph working.
graph = new_dag()


@graph.task(id="quiet")
def quiet(ctx):
    seen["quiet_tmf"] = ctx.tmf


FakeClient.opened = []
sdk.JobSeeker = FakeClient
store = FakeStore()
dagmod.TaskStateStore = lambda enabled=True: store
os.environ["JOBSEEKER_DAG_TMF"] = "0"
try:
    result = run(graph, state=True)
finally:
    os.environ.pop("JOBSEEKER_DAG_TMF", None)
    sdk.JobSeeker = original_client
    dagmod.TaskStateStore = original_store

check(result.status == dagmod.SUCCESS, "JOBSEEKER_DAG_TMF=0 must not break the run.")
check(FakeClient.opened == [], "JOBSEEKER_DAG_TMF=0 must open no transactions.")
check(seen["quiet_tmf"] is None, "A task in an untracked run sees no transaction.")

# A run that records nothing at all also tracks nothing, and ctx.progress on a
# task with no transaction is simply a no-op rather than an error.
graph = new_dag()
progressed = []


@graph.task(id="offline")
def offline(ctx):
    ctx.progress(total=10, processed=10)
    progressed.append(ctx.tmf)


result = run(graph, state=False)
check(result.status == dagmod.SUCCESS, "A task may report progress with no transaction open.")
check(progressed == [None], "An unrecorded run opens no transaction.")

# --- 18. A task can skip itself ---------------------------------------------
#
# Trigger rules decide from what happened upstream. A branch decides from what
# it finds when it gets there, and that decision is not a failure.

graph = new_dag()
ran = []


@graph.task(id="decide")
def decide(ctx):
    ran.append("decide")
    ctx.push("full", False)


@graph.task(id="incremental", depends_on=["decide"])
def incremental(ctx):
    ran.append("incremental")
    if ctx.pull("decide", "full", False):
        raise dagmod.SkipTask("the change set is large")


@graph.task(id="full_reload", depends_on=["decide"], retries=2)
def full_reload(ctx):
    ran.append("full_reload")
    if not ctx.pull("decide", "full", False):
        raise dagmod.SkipTask("the change set is small")


@graph.task(id="after_full", depends_on=["full_reload"])
def after_full(ctx):
    raise AssertionError("a task below a skipped branch must not run")


@graph.task(id="join", depends_on=["incremental", "full_reload"], trigger="ALWAYS")
def join(ctx):
    ran.append("join")


result = run(graph)
statuses = result.statuses()
check(result.status == dagmod.SUCCESS, "Skipping a branch must leave the run green.")
check(statuses["full_reload"] == dagmod.SKIPPED, "A task that skips itself is SKIPPED, not FAILURE.")
check(statuses["incremental"] == dagmod.SUCCESS, "The branch that was taken still succeeds.")
check(statuses["after_full"] == dagmod.SKIPPED, "A skip must propagate to what waits on it.")
check(statuses["join"] == dagmod.SUCCESS, "An ALWAYS join still runs after a skipped branch.")
check(ran.count("full_reload") == 1, "A skip must never be retried: ran %d times." % ran.count("full_reload"))

# The reason is kept, because "why was this grey" is the whole question.
check("the change set is small" in result.results["full_reload"].message,
      "The skip reason must be kept: %r" % result.results["full_reload"].message)

with captured() as buffer:
    run(graph)
check("[JobSeeker Task] full_reload | SKIPPED" in buffer.getvalue(),
      "A self-skip must announce itself on the console.")

# A skipped task cancels its transaction rather than recording an error.
graph = new_dag()


@graph.task(id="skips")
def skips(ctx):
    raise dagmod.SkipTask("nothing to do")


class CancelTracking(FakeTransaction):
    cancelled = []

    def cancel(self, message):
        CancelTracking.cancelled.append(message)
        return True


class CancelClient(FakeClient):
    def task(self, **kwargs):
        transaction = CancelTracking(**kwargs)
        FakeClient.opened.append(transaction)
        return transaction


FakeClient.opened = []
CancelTracking.cancelled = []
sdk.JobSeeker = CancelClient
store = FakeStore()
dagmod.TaskStateStore = lambda enabled=True: store
try:
    result = run(graph, state=True)
finally:
    sdk.JobSeeker = original_client
    dagmod.TaskStateStore = original_store

check(result.statuses()["skips"] == dagmod.SKIPPED, "A self-skip is recorded as SKIPPED.")
check(CancelTracking.cancelled == ["nothing to do"],
      "A skipped task cancels its transaction rather than failing it: %r" % CancelTracking.cancelled)
check(any(row["task_key"] == "skips" and row["status"] == dagmod.SKIPPED for row in store.rows),
      "The skip must be recorded in the run state.")

check("SkipTask" in dagmod.__all__, "SkipTask must be part of the module's public API.")

print("JobSeeker task DAG checks passed (%d assertions)." % CHECKS)
