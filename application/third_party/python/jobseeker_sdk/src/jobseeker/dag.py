"""JobSeeker task DAG: Airflow-style tasks inside a single JobSeeker job.

A JobSeeker job is one unit of scheduling, environment and notification, and it
runs as one Jenkins build on one executor or in one container. This module adds
the layer below it: a declared graph of *tasks* that the job runs in dependency
order, with per-task retries, trigger rules, skipping, parallelism and a
persisted per-task record that the Job View graph reads back.

    from jobseeker import dag

    @dag.task(id="extract", produces=["raw_customers"])
    def extract(ctx):
        ctx.push("rows", 1200)

    @dag.task(id="clean", depends_on=["extract"])
    def clean(ctx):
        rows = ctx.pull("extract", "rows", 0)

    if __name__ == "__main__":
        dag.run()

Design notes
------------
* Trigger rules reuse the three words the Pipelines graph already uses for its
  edge conditions - SUCCESS, FAILURE, ALWAYS - so the platform has one
  vocabulary for "when does this node run" instead of two.
* Orchestration state (did the task run, which attempt, how long) is written to
  `job_task_runs`. Business telemetry (rows read/written, dimension, errors)
  stays in TMF, opt-in per task with `track=True`. They join on the TMF
  instance id.
* Every database write is best effort. A DAG must still run end to end on a
  workstation, in a preview container, or on an agent that cannot reach
  MariaDB; it just loses the stored graph and falls back to console markers.
"""

from __future__ import annotations

import functools
import inspect
import json
import os
import re
import sys
import threading
import time
import traceback
import uuid
from concurrent.futures import FIRST_COMPLETED, ThreadPoolExecutor, wait
from dataclasses import dataclass, field
from typing import Any, Callable, Dict, Iterable, List, Mapping, Optional, Sequence, Tuple

__all__ = [
    "ALWAYS",
    "SkipTask",
    "Dag",
    "DagDefinitionError",
    "DagRunResult",
    "FAILURE",
    "SKIPPED",
    "SUCCESS",
    "TaskContext",
    "TaskResult",
    "TaskSpec",
    "UPSTREAM_FAILED",
    "current",
    "describe",
    "manifest",
    "run",
    "task",
]


# --- Statuses and trigger rules ---------------------------------------------

PENDING = "PENDING"
RUNNING = "RUNNING"
SUCCESS = "SUCCESS"
FAILURE = "FAILURE"
SKIPPED = "SKIPPED"
UPSTREAM_FAILED = "UPSTREAM_FAILED"

TERMINAL_STATUSES = (SUCCESS, FAILURE, SKIPPED, UPSTREAM_FAILED)
FAILED_STATUSES = (FAILURE, UPSTREAM_FAILED)

#: Trigger rules, deliberately the same three words as a Pipelines edge
#: condition. SUCCESS: every upstream succeeded. FAILURE: at least one upstream
#: failed, which is how an alert or compensation task is wired. ALWAYS: every
#: upstream reached a terminal state, whatever it was.
ALWAYS = "ALWAYS"
TRIGGERS = (SUCCESS, FAILURE, ALWAYS)

TASK_ID_PATTERN = re.compile(r"^[A-Za-z][A-Za-z0-9_-]{0,63}$")

MAX_TASKS = 200
MAX_EDGES = 600
XCOM_VALUE_LIMIT = 64 * 1024
XCOM_TOTAL_LIMIT = 1024 * 1024


class DagDefinitionError(Exception):
    """The declared graph is not runnable: bad id, unknown or cyclic edge."""


class DagRunError(Exception):
    """At least one task in the graph failed."""


class SkipTask(Exception):
    """Raised by a task to end itself as SKIPPED rather than FAILURE.

    Trigger rules decide whether a task runs from what happened upstream. A
    branch decides from what it finds when it gets there - the change set was
    too small, the file had not landed, this partition was empty - and that
    decision is not a failure. Raising this ends the task as SKIPPED, which
    skips everything downstream of it that waits on success, leaves the run
    green, and is not retried.
    """


# --- Small helpers -----------------------------------------------------------


def _env(name: str, default: str = "") -> str:
    value = os.environ.get(name)
    return default if value is None or value == "" else str(value)


def _flag(name: str, default: bool = False) -> bool:
    raw = _env(name).strip().lower()
    if raw == "":
        return default
    return raw in ("1", "true", "yes", "on")


def _int(value: Any, default: int) -> int:
    try:
        return int(str(value).strip())
    except (TypeError, ValueError):
        return default


def _slug(value: str, limit: int = 40) -> str:
    cleaned = re.sub(r"[^A-Za-z0-9._-]+", "-", str(value or "")).strip("-")
    return cleaned[:limit] if cleaned else "job"


def _tuple(values: Optional[Iterable[Any]]) -> Tuple[str, ...]:
    if values is None:
        return ()
    if isinstance(values, str):
        values = [values]
    cleaned: List[str] = []
    for value in values:
        text = str(value).strip()
        if text and text not in cleaned:
            cleaned.append(text)
    return tuple(cleaned)


def _emit(line: str) -> None:
    """Write a console marker.

    Markers are the fallback observability channel: they survive when the
    database is unreachable, and `job-console-groups.js` folds each task's
    output into its own collapsible section from them. A marker is never
    prefixed with a task id, so it goes to the underlying stream even while
    task output is being tagged.
    """

    stream = _OUTPUT.get("stdout")
    if stream is not None:
        stream.write_marker(line + "\n")
        return
    sys.stdout.write(line + "\n")
    sys.stdout.flush()


class _TaskOutputStream:
    """Tags every line a task prints with the task's id.

    Tasks run concurrently inside one process and share one console. Without a
    per-line owner their output interleaves, and a traceback raised by one task
    lands under another task's heading - which reads as a task failing when it
    actually succeeded. Prefixing at the stream is the only place that can
    attribute a bare `print()` or a library's logging call to the task that made
    it.

    The DAG's own markers go through write_marker() and are never tagged.
    """

    def __init__(self, stream: Any, state: "threading.local"):
        self._stream = stream
        self._state = state
        self._buffers: Dict[int, str] = {}
        self._lock = threading.RLock()

    # -- stream protocol --
    def write(self, text: Any) -> int:
        text = "" if text is None else str(text)
        if text == "":
            return 0

        task_id = getattr(self._state, "task_id", "")
        if not task_id:
            with self._lock:
                self._stream.write(text)
                self._stream.flush()
            return len(text)

        key = threading.get_ident()
        with self._lock:
            pending = self._buffers.get(key, "") + text
            lines = pending.split("\n")
            self._buffers[key] = lines.pop()
            if lines:
                prefix = "[" + task_id + "] "
                self._stream.write("".join(prefix + line + "\n" for line in lines))
                self._stream.flush()
        return len(text)

    def writelines(self, lines: Iterable[Any]) -> None:
        for line in lines:
            self.write(line)

    def flush(self) -> None:
        with self._lock:
            self._stream.flush()

    def isatty(self) -> bool:
        return False

    def writable(self) -> bool:
        return True

    def readable(self) -> bool:
        return False

    def seekable(self) -> bool:
        return False

    @property
    def encoding(self) -> str:
        return getattr(self._stream, "encoding", "utf-8")

    @property
    def errors(self) -> Any:
        return getattr(self._stream, "errors", None)

    # -- DAG use --
    def write_marker(self, text: str) -> None:
        """Write an untagged line, after flushing whatever this thread had
        half-written, so the marker cannot appear inside someone's line."""

        with self._lock:
            self.flush_thread()
            self._stream.write(text)
            self._stream.flush()

    def flush_thread(self) -> None:
        """Emit this thread's partial line. A task that ends with `print(x, end="")`
        must not have its last line swallowed or glued to the next task's."""

        key = threading.get_ident()
        with self._lock:
            pending = self._buffers.pop(key, "")
            if pending:
                task_id = getattr(self._state, "task_id", "")
                prefix = "[" + task_id + "] " if task_id else ""
                self._stream.write(prefix + pending + "\n")
                self._stream.flush()


#: The streams installed for the duration of a run, and the thread-local that
#: says which task the current thread is running.
_OUTPUT: Dict[str, Any] = {"stdout": None, "stderr": None}
_TASK_STATE = threading.local()


def _duration(seconds: float) -> str:
    return "%.3fs" % max(0.0, float(seconds))


def _accepts_argument(function: Callable[..., Any]) -> bool:
    """True when the task function wants the TaskContext handed to it."""

    try:
        signature = inspect.signature(function)
    except (TypeError, ValueError):  # builtins and C callables
        return False
    for parameter in signature.parameters.values():
        if parameter.kind in (parameter.POSITIONAL_ONLY, parameter.POSITIONAL_OR_KEYWORD):
            return True
        if parameter.kind == parameter.VAR_POSITIONAL:
            return True
    return False


# --- Task declaration --------------------------------------------------------


@dataclass
class TaskSpec:
    id: str
    function: Callable[..., Any]
    depends_on: Tuple[str, ...] = ()
    consumes: Tuple[str, ...] = ()
    produces: Tuple[str, ...] = ()
    retries: int = 0
    retry_delay: float = 0.0
    trigger: str = SUCCESS
    description: str = ""
    #: None means "track when this run is recording state at all", which is the
    #: default: a task that runs is a transaction, and an operator looking at
    #: Results should find it there without having to have opted in first.
    track: Optional[bool] = None
    dimension: str = ""

    @property
    def attempts(self) -> int:
        return max(1, self.retries + 1)

    def tracks(self, state_enabled: bool) -> bool:
        """Whether this task opens a TMF transaction on this run."""

        return state_enabled if self.track is None else bool(self.track)

    def as_dict(self) -> Dict[str, Any]:
        return {
            "id": self.id,
            "description": self.description,
            "depends_on": list(self.depends_on),
            "consumes": list(self.consumes),
            "produces": list(self.produces),
            "retries": self.retries,
            "retry_delay": self.retry_delay,
            "trigger": self.trigger,
            "track": self.track is not False,
            "dimension": self.dimension,
        }


@dataclass
class TaskResult:
    id: str
    status: str
    attempt: int = 0
    attempts: int = 0
    duration: float = 0.0
    message: str = ""
    value: Any = None
    tmf_instance_id: str = ""

    @property
    def ok(self) -> bool:
        return self.status == SUCCESS


@dataclass
class DagRunResult:
    run_key: str
    job: str
    environment: str
    status: str
    started_at: float
    duration: float
    results: Dict[str, TaskResult] = field(default_factory=dict)

    def counts(self) -> Dict[str, int]:
        totals = {SUCCESS: 0, FAILURE: 0, SKIPPED: 0, UPSTREAM_FAILED: 0}
        for result in self.results.values():
            totals[result.status] = totals.get(result.status, 0) + 1
        return totals

    @property
    def ok(self) -> bool:
        return self.status == SUCCESS

    def statuses(self) -> Dict[str, str]:
        return dict((task_id, result.status) for task_id, result in self.results.items())


# --- Cross-task values (the small-payload channel) ---------------------------


class XComStore:
    """Run-scoped key/value channel between tasks.

    Deliberately small. Large payloads belong in Data Assets, which are the
    platform's governed IO contract and are visible on the Data Assets page;
    this is for the row counts, watermarks and file names that make a downstream
    task's job easier. Backed by a JSON file so a future one-process-per-task
    backend reads the same values without any API change.
    """

    def __init__(self, path: Optional[str] = None):
        self.path = path
        self._lock = threading.Lock()
        self._values: Dict[str, Dict[str, Any]] = {}
        self._load()

    def _load(self) -> None:
        if not self.path or not os.path.isfile(self.path):
            return
        try:
            with open(self.path, "r", encoding="utf-8") as stream:
                stored = json.load(stream)
            if isinstance(stored, dict):
                for task_id, values in stored.items():
                    if isinstance(values, dict):
                        self._values[str(task_id)] = dict(values)
        except (OSError, ValueError):
            self._values = {}

    def _flush(self) -> None:
        if not self.path:
            return
        try:
            directory = os.path.dirname(self.path)
            if directory and not os.path.isdir(directory):
                os.makedirs(directory, exist_ok=True)
            temporary = self.path + ".tmp"
            with open(temporary, "w", encoding="utf-8") as stream:
                json.dump(self._values, stream)
            os.replace(temporary, self.path)
        except OSError:
            pass

    def push(self, task_id: str, key: str, value: Any) -> None:
        try:
            encoded = json.dumps(value)
        except (TypeError, ValueError) as error:
            raise ValueError(
                "Task %r pushed a value for %r that is not JSON serialisable. "
                "Publish large or binary results as a Data Asset instead." % (task_id, key)
            ) from error
        if len(encoded) > XCOM_VALUE_LIMIT:
            raise ValueError(
                "Task %r pushed %d bytes for %r; the per-value limit is %d bytes. "
                "Publish it as a Data Asset instead." % (task_id, len(encoded), key, XCOM_VALUE_LIMIT)
            )
        with self._lock:
            bucket = self._values.setdefault(task_id, {})
            bucket[str(key)] = json.loads(encoded)
            if len(json.dumps(self._values)) > XCOM_TOTAL_LIMIT:
                bucket.pop(str(key), None)
                raise ValueError(
                    "The task value store for this run would exceed %d bytes. "
                    "Publish large results as Data Assets." % XCOM_TOTAL_LIMIT
                )
            self._flush()

    def pull(self, task_id: str, key: str, default: Any = None) -> Any:
        with self._lock:
            return self._values.get(str(task_id), {}).get(str(key), default)

    def snapshot(self) -> Dict[str, Dict[str, Any]]:
        with self._lock:
            return json.loads(json.dumps(self._values))


# --- Persisted per-task state ------------------------------------------------

TASK_RUN_SCHEMA = """CREATE TABLE IF NOT EXISTS `job_task_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
  `build_number` int(11) unsigned DEFAULT NULL,
  `task_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `attempt` int(11) unsigned NOT NULL DEFAULT 1,
  `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'PENDING',
  `trigger_rule` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'SUCCESS',
  `upstream_json` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tmf_instance_id` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `duration_ms` bigint(20) unsigned DEFAULT NULL,
  `message` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_task_run_attempt` (`run_key`,`task_key`,`attempt`),
  KEY `job_task_run_job` (`job_name`,`environment`,`id`),
  KEY `job_task_run_key` (`run_key`,`task_key`),
  KEY `job_task_run_status` (`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci"""

TASK_GRAPH_SCHEMA = """CREATE TABLE IF NOT EXISTS `job_task_graphs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
  `graph_json` longtext COLLATE utf8_unicode_ci NOT NULL,
  `source` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'runtime',
  `task_count` int(11) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_task_graph_scope` (`job_name`,`environment`),
  KEY `job_task_graph_job` (`job_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci"""


class TaskStateStore:
    """Best-effort writer for `job_task_runs` and `job_task_graphs`.

    Never raises into the DAG. A job whose agent cannot reach MariaDB still runs
    its tasks; it just does not light up the Job View graph. The first failure
    prints one hint and the store goes quiet for the rest of the run.
    """

    def __init__(self, enabled: bool = True):
        self.enabled = enabled
        self._connection = None
        self._lock = threading.Lock()
        self._warned = False
        self._schema_ready = False

    def _warn(self, error: Exception) -> None:
        if self._warned:
            return
        self._warned = True
        _emit(
            "[JobSeeker DAG] task state is not being recorded (%s). "
            "The graph still runs; Job View will show the declared graph without run status." % error
        )

    def _connect(self):
        if self._connection is not None:
            return self._connection
        from . import DatabaseConfig  # local import keeps module import cheap

        import mysql.connector  # type: ignore

        config = DatabaseConfig.from_env()
        self._connection = mysql.connector.connect(
            host=config.host,
            port=config.port,
            user=config.user,
            password=config.password,
            database=config.database,
        )
        return self._connection

    def _execute(self, statements: Sequence[Tuple[str, Tuple[Any, ...]]]) -> bool:
        if not self.enabled:
            return False
        with self._lock:
            try:
                connection = self._connect()
                cursor = connection.cursor()
                try:
                    if not self._schema_ready:
                        for schema in (TASK_RUN_SCHEMA, TASK_GRAPH_SCHEMA):
                            try:
                                cursor.execute(schema)
                            except Exception:  # noqa: BLE001 - a read-only grant is fine
                                pass
                        self._schema_ready = True
                    for sql, values in statements:
                        cursor.execute(sql, values)
                    connection.commit()
                    return True
                finally:
                    cursor.close()
            except Exception as error:  # noqa: BLE001 - telemetry must never fail a run
                self.enabled = False
                self._connection = None
                self._warn(error)
                return False

    def record(self, row: Mapping[str, Any]) -> bool:
        sql = (
            "INSERT INTO job_task_runs "
            "(run_key, job_name, environment, build_number, task_key, attempt, status, trigger_rule, "
            "upstream_json, tmf_instance_id, started_at, finished_at, duration_ms, message, updated_at) "
            "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, now()) "
            "ON DUPLICATE KEY UPDATE status = VALUES(status), upstream_json = VALUES(upstream_json), "
            "tmf_instance_id = VALUES(tmf_instance_id), started_at = COALESCE(job_task_runs.started_at, VALUES(started_at)), "
            "finished_at = VALUES(finished_at), duration_ms = VALUES(duration_ms), message = VALUES(message), "
            "updated_at = now()"
        )
        values = (
            row.get("run_key"),
            row.get("job_name"),
            row.get("environment") or "ALL",
            row.get("build_number"),
            row.get("task_key"),
            _int(row.get("attempt"), 1),
            row.get("status") or PENDING,
            row.get("trigger_rule") or SUCCESS,
            row.get("upstream_json"),
            row.get("tmf_instance_id") or None,
            row.get("started_at"),
            row.get("finished_at"),
            row.get("duration_ms"),
            (row.get("message") or "")[:2000] or None,
        )
        return self._execute(((sql, values),))

    def save_graph(self, job_name: str, environment: str, graph: Mapping[str, Any]) -> bool:
        encoded = json.dumps(graph, sort_keys=True)
        sql = (
            "INSERT INTO job_task_graphs (job_name, environment, graph_json, source, task_count, updated_at) "
            "VALUES (%s, %s, %s, 'runtime', %s, now()) "
            "ON DUPLICATE KEY UPDATE graph_json = VALUES(graph_json), source = 'runtime', "
            "task_count = VALUES(task_count), updated_at = now()"
        )
        tasks = graph.get("tasks") if isinstance(graph, Mapping) else []
        values = (job_name, environment or "ALL", encoded, len(tasks or []))
        return self._execute(((sql, values),))

    def completed_tasks(self, run_key: str) -> Dict[str, str]:
        """Latest status per task for a previous run, used by resume."""

        if not self.enabled or not run_key:
            return {}
        with self._lock:
            try:
                connection = self._connect()
                cursor = connection.cursor()
                try:
                    cursor.execute(
                        "SELECT task_key, status FROM job_task_runs WHERE run_key = %s "
                        "ORDER BY attempt ASC, id ASC",
                        (run_key,),
                    )
                    statuses: Dict[str, str] = {}
                    for task_key, status in cursor.fetchall():
                        statuses[str(task_key)] = str(status)
                    return statuses
                finally:
                    cursor.close()
            except Exception as error:  # noqa: BLE001
                self.enabled = False
                self._connection = None
                self._warn(error)
                return {}

    def close(self) -> None:
        with self._lock:
            if self._connection is None:
                return
            try:
                self._connection.close()
            except Exception:  # noqa: BLE001
                pass
            finally:
                self._connection = None


# --- Runtime context handed to each task -------------------------------------


class TaskContext:
    """What a task function receives: its own identity plus the run's services."""

    def __init__(
        self,
        run: "_RunState",
        spec: TaskSpec,
        attempt: int,
        upstream: Mapping[str, str],
    ):
        self._run = run
        self._spec = spec
        self.attempt = attempt
        self.upstream = dict(upstream)
        self.tmf = None

    @property
    def task_id(self) -> str:
        return self._spec.id

    @property
    def attempts(self) -> int:
        return self._spec.attempts

    @property
    def run_key(self) -> str:
        return self._run.run_key

    @property
    def job(self) -> str:
        return self._run.job

    @property
    def environment(self) -> str:
        return self._run.environment

    @property
    def build_number(self) -> Optional[int]:
        return self._run.build_number

    @property
    def dag(self) -> "Dag":
        return self._run.dag

    def progress(self, total: Optional[int] = None, processed: Optional[int] = None,
                 msg: Optional[str] = None) -> None:
        """Report row counts for this task.

        Forwards to the task's TMF transaction when there is one and does
        nothing when there is not, so a job reports its throughput the same way
        whether or not the worker can reach the database. The counts are what
        Job View shows in the task's Rows column.
        """

        if self.tmf is None:
            return
        try:
            self.tmf.progress(total=total, processed=processed, msg=msg)
        except Exception as error:  # noqa: BLE001 - telemetry is never fatal
            self.log("could not report progress (%s)" % error)

    def log(self, message: str) -> None:
        """Print a line from this task. The task id prefix is added by the
        output stream, which tags everything the task writes, not just this."""

        print(message)

    # -- values between tasks --
    def push(self, key: str, value: Any) -> None:
        self._run.xcom.push(self._spec.id, key, value)

    def pull(self, task_id: str, key: str, default: Any = None) -> Any:
        return self._run.xcom.pull(task_id, key, default)

    # -- platform primitives --
    def asset(self, key: str, mode: str = "input", required: bool = True):
        """Resolve a governed Data Asset in this job's runtime scope.

        With required=False this returns None not only when the asset is absent
        from the catalog, but also when there is no catalog to read - a stack
        where no Data Asset has ever been published, a preview container, a
        workstation. A task that said it can live without the asset should not
        have to handle the catalog itself being missing as a separate case.
        """

        from . import DataAssetCatalog, JobSeekerError

        try:
            if self._run.asset_catalog is None:
                self._run.asset_catalog = DataAssetCatalog(
                    environment=self._run.environment,
                    job=_env("JOBSEEKER_DATA_ASSET_JOB") or self._run.job,
                )
            return self._run.asset_catalog.resolve(key, mode=mode, required=required)
        except JobSeekerError:
            if required:
                raise
            self.log("Data Assets are not available here; continuing without %r" % key)
            return None

    def context(self, key: str, default: Any = None, cast: Optional[Callable[[Any], Any]] = None,
                required: bool = False, project: Optional[str] = None) -> Any:
        """Resolve a Context value for this environment.

        A lookup that is not required falls back to `default` when the value is
        missing *or* when the settings database cannot be reached, so a job with
        sensible defaults still runs on a worker that has no route to it.
        """

        from . import JobSeekerError, get_context

        try:
            return get_context(
                key,
                default=default,
                cast=cast,
                required=required,
                project=project,
                environment=self._run.environment,
            )
        except JobSeekerError:
            if required:
                raise
            self.log("Context is not available here; using the default for %r" % key)
            return default
        except Exception as error:  # noqa: BLE001 - an unreachable database is not a job failure
            if required:
                raise
            self.log("Context lookup for %r failed (%s); using the default" % (key, error))
            return default


# --- The graph ---------------------------------------------------------------


class Dag:
    """A declared graph of tasks that one JobSeeker job runs."""

    def __init__(self, name: str = ""):
        self.name = name
        self._specs: "Dict[str, TaskSpec]" = {}

    # -- declaration --
    def task(
        self,
        id: str = "",
        depends_on: Optional[Iterable[str]] = None,
        consumes: Optional[Iterable[str]] = None,
        produces: Optional[Iterable[str]] = None,
        retries: int = 0,
        retry_delay: float = 0.0,
        trigger: str = SUCCESS,
        description: str = "",
        track: Optional[bool] = None,
        dimension: str = "",
    ) -> Callable[[Callable[..., Any]], Callable[..., Any]]:
        """Declare a task. Used as `@dag.task(id="extract", depends_on=[...])`.

        `track` controls the task's TMF transaction. Left unset it follows the
        run: a recorded run opens one per task, so every task shows up in
        Results without the author having to ask. Pass False for a task whose
        work is not a business transaction, such as a cleanup step.
        """

        def decorator(function: Callable[..., Any]) -> Callable[..., Any]:
            spec = TaskSpec(
                id=str(id).strip() or function.__name__,
                function=function,
                depends_on=_tuple(depends_on),
                consumes=_tuple(consumes),
                produces=_tuple(produces),
                retries=max(0, _int(retries, 0)),
                retry_delay=max(0.0, float(retry_delay or 0.0)),
                trigger=str(trigger or SUCCESS).strip().upper(),
                description=str(description or "").strip(),
                track=track,
                dimension=str(dimension or "").strip(),
            )
            self.add(spec)

            @functools.wraps(function)
            def wrapper(*args: Any, **kwargs: Any) -> Any:
                return function(*args, **kwargs)

            wrapper.jobseeker_task = spec  # type: ignore[attr-defined]
            return wrapper

        return decorator

    def add(self, spec: TaskSpec) -> TaskSpec:
        if not TASK_ID_PATTERN.match(spec.id):
            raise DagDefinitionError(
                "Task id %r is invalid. Use a letter followed by up to 63 letters, digits, dashes or underscores."
                % spec.id
            )
        if spec.id in self._specs:
            raise DagDefinitionError("Task id %r is declared more than once." % spec.id)
        if spec.trigger not in TRIGGERS:
            raise DagDefinitionError(
                "Task %r has trigger %r. Use one of %s." % (spec.id, spec.trigger, ", ".join(TRIGGERS))
            )
        if len(self._specs) >= MAX_TASKS:
            raise DagDefinitionError("A job may declare at most %d tasks." % MAX_TASKS)
        self._specs[spec.id] = spec
        return spec

    def reset(self) -> None:
        """Forget every declared task. Used by tests and by repeated imports."""

        self._specs = {}

    # -- introspection --
    @property
    def tasks(self) -> "Dict[str, TaskSpec]":
        return dict(self._specs)

    def __len__(self) -> int:
        return len(self._specs)

    def __contains__(self, task_id: object) -> bool:
        return str(task_id) in self._specs

    def edges(self) -> List[Dict[str, str]]:
        edges: List[Dict[str, str]] = []
        for spec in self._specs.values():
            for source in spec.depends_on:
                edges.append({"source": source, "target": spec.id, "condition": spec.trigger})
        return edges

    def validate(self) -> Dict[str, Any]:
        """Check ids, edges and acyclicity, and return the topological order.

        Raises DagDefinitionError with an actionable message; the same checks run
        before any task is executed so a bad graph fails in milliseconds rather
        than half way through a run.
        """

        if not self._specs:
            raise DagDefinitionError(
                "No tasks are declared. Decorate at least one function with @dag.task(...) before calling dag.run()."
            )

        edge_count = sum(len(spec.depends_on) for spec in self._specs.values())
        if edge_count > MAX_EDGES:
            raise DagDefinitionError("A job may declare at most %d task dependencies." % MAX_EDGES)

        in_degree: Dict[str, int] = dict((task_id, 0) for task_id in self._specs)
        adjacency: Dict[str, List[str]] = dict((task_id, []) for task_id in self._specs)
        for spec in self._specs.values():
            for source in spec.depends_on:
                if source == spec.id:
                    raise DagDefinitionError("Task %r cannot depend on itself." % spec.id)
                if source not in self._specs:
                    raise DagDefinitionError(
                        "Task %r depends on %r, which is not declared in this job." % (spec.id, source)
                    )
                adjacency[source].append(spec.id)
                in_degree[spec.id] += 1

        ready = sorted(task_id for task_id, degree in in_degree.items() if degree == 0)
        order: List[str] = []
        layers: List[List[str]] = []
        while ready:
            layer = list(ready)
            layers.append(layer)
            ready = []
            for task_id in layer:
                order.append(task_id)
                for target in adjacency[task_id]:
                    in_degree[target] -= 1
                    if in_degree[target] == 0:
                        ready.append(target)
            ready.sort()

        if len(order) != len(self._specs):
            stuck = sorted(set(self._specs) - set(order))
            raise DagDefinitionError(
                "The task graph contains a cycle. Break the loop between: %s." % ", ".join(stuck)
            )

        return {"order": order, "layers": layers}

    def manifest(self) -> Dict[str, Any]:
        """The serialisable graph: what Job View draws and what a future
        one-container-per-task backend compiles."""

        validation = self.validate()
        return {
            "version": 1,
            "name": self.name or _env("JOBSEEKER_JOB_NAME", _env("JOB_NAME", "")),
            "tasks": [self._specs[task_id].as_dict() for task_id in validation["order"]],
            "edges": self.edges(),
            "order": validation["order"],
            "layers": validation["layers"],
        }

    # -- execution --
    def run(self, **kwargs: Any) -> DagRunResult:
        return _run_dag(self, **kwargs)


# --- Execution ---------------------------------------------------------------


class _RunState:
    def __init__(self, dag: Dag, run_key: str, job: str, environment: str,
                 build_number: Optional[int], xcom: XComStore, store: TaskStateStore,
                 track_tasks: bool = True):
        self.dag = dag
        self.run_key = run_key
        self.job = job
        self.environment = environment
        self.build_number = build_number
        self.xcom = xcom
        self.store = store
        #: Whether tasks that did not state a preference open a TMF transaction.
        self.track_tasks = track_tasks
        self.asset_catalog = None


_CURRENT_DAG = Dag()
_CURRENT_CONTEXT = threading.local()


def current() -> Dag:
    """The module-level DAG that `@dag.task` writes into."""

    return _CURRENT_DAG


def task(**kwargs: Any) -> Callable[[Callable[..., Any]], Callable[..., Any]]:
    """Declare a task on the module-level DAG."""

    return _CURRENT_DAG.task(**kwargs)


def manifest() -> Dict[str, Any]:
    return _CURRENT_DAG.manifest()


def describe(dag: Optional[Dag] = None) -> Dict[str, Any]:
    """Print the graph as JSON without running anything.

    This is what `JOBSEEKER_DAG_DESCRIBE=1` triggers and what the
    `jobseeker-dag describe` CLI prints, so a caller that can execute the job's
    code in a sandbox gets the exact graph the run would use.
    """

    graph = (dag or _CURRENT_DAG).manifest()
    _emit("[JobSeeker DAG] manifest | %d task(s)" % len(graph["tasks"]))
    _emit("JOBSEEKER_DAG_MANIFEST " + json.dumps(graph, sort_keys=True))
    return graph


def run(**kwargs: Any) -> DagRunResult:
    """Run the module-level DAG. The normal entry point for a job."""

    return _run_dag(_CURRENT_DAG, **kwargs)


def _resolve_run_key(job: str, environment: str, build_number: Optional[int]) -> str:
    explicit = _env("JOBSEEKER_RUN_KEY")
    if explicit:
        return explicit[:64]
    if build_number is not None:
        return ("%s-%s-%d" % (_slug(job, 30), _slug(environment, 12), build_number))[:64]
    return ("local-%s-%s" % (_slug(job, 24), uuid.uuid4().hex[:12]))[:64]


def _decide(spec: TaskSpec, statuses: Mapping[str, str], selected: Optional[set]) -> Tuple[bool, str, str]:
    """Return (run?, status_if_not_run, reason) for a task whose upstream is done."""

    if selected is not None and spec.id not in selected:
        return False, SKIPPED, "not selected for this run"

    upstream = []
    for source in spec.depends_on:
        status = statuses.get(source, SKIPPED)
        # A task explicitly excluded from this run is treated as satisfied so a
        # single-task re-run is possible without replaying the whole graph.
        if selected is not None and source not in selected:
            status = SUCCESS
        upstream.append(status)

    if not upstream:
        return True, "", "root task"

    failed = [status for status in upstream if status in FAILED_STATUSES]
    skipped = [status for status in upstream if status == SKIPPED]

    if spec.trigger == ALWAYS:
        return True, "", "trigger ALWAYS"
    if spec.trigger == FAILURE:
        if failed:
            return True, "", "trigger FAILURE matched %d upstream failure(s)" % len(failed)
        return False, SKIPPED, "trigger FAILURE and no upstream failed"
    if failed:
        return False, UPSTREAM_FAILED, "upstream failed"
    if skipped:
        return False, SKIPPED, "upstream skipped"
    return True, "", "all upstream succeeded"


def _open_tmf(spec: TaskSpec, run_state: _RunState):
    """Open a TMF transaction for a task that asked to be tracked.

    Signal handlers are installed on the main thread only; a task runs on a
    worker thread, so the client is created without them.
    """

    from . import JobSeeker

    client = JobSeeker(
        environment=run_state.environment,
        job=run_state.job,
        install_signal_handlers=False,
    )
    transaction = client.task(
        event_text=spec.description or spec.id,
        dimension=spec.dimension or spec.id,
        run_key=run_state.run_key,
        task_key=spec.id,
    )
    return client, transaction


def _run_task(run_state: _RunState, spec: TaskSpec, upstream: Mapping[str, str]) -> TaskResult:
    """Run one task, including its retries. Never raises.

    The worker thread claims the task id for the duration so everything the task
    writes is attributed to it, and releases it afterwards: pool threads are
    reused, and a stale id would tag the next task's output with the wrong name.
    """

    try:
        return _run_task_attempts(run_state, spec, upstream, spec.attempts, time.time())
    finally:
        stream = _OUTPUT.get("stdout")
        if stream is not None:
            stream.flush_thread()
        stream = _OUTPUT.get("stderr")
        if stream is not None:
            stream.flush_thread()
        _TASK_STATE.task_id = ""


def _run_task_attempts(run_state: _RunState, spec: TaskSpec, upstream: Mapping[str, str],
                       attempts: int, started: float) -> TaskResult:
    last_error = ""

    for attempt in range(1, attempts + 1):
        attempt_started = time.time()
        _TASK_STATE.task_id = spec.id
        _emit("[JobSeeker Task] %s | RUNNING | attempt %d/%d" % (spec.id, attempt, attempts))
        run_state.store.record({
            "run_key": run_state.run_key,
            "job_name": run_state.job,
            "environment": run_state.environment,
            "build_number": run_state.build_number,
            "task_key": spec.id,
            "attempt": attempt,
            "status": RUNNING,
            "trigger_rule": spec.trigger,
            "upstream_json": json.dumps(dict(upstream))[:2000],
            "started_at": time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(attempt_started)),
        })

        context = TaskContext(run_state, spec, attempt, upstream)
        client = None
        transaction = None
        tmf_instance_id = ""
        try:
            if spec.tracks(run_state.track_tasks):
                try:
                    client, transaction = _open_tmf(spec, run_state)
                    transaction.__enter__()
                    tmf_instance_id = transaction.instance_id
                    context.tmf = transaction
                except Exception as error:  # noqa: BLE001 - telemetry is never fatal
                    _emit("[%s] TMF tracking is unavailable (%s); the task still runs." % (spec.id, error))
                    client = None
                    transaction = None

            if _accepts_argument(spec.function):
                value = spec.function(context)
            else:
                value = spec.function()

            if transaction is not None:
                transaction.__exit__(None, None, None)
            duration = time.time() - attempt_started
            _emit("[JobSeeker Task] %s | SUCCESS | attempt %d/%d | %s" % (spec.id, attempt, attempts, _duration(duration)))
            run_state.store.record({
                "run_key": run_state.run_key,
                "job_name": run_state.job,
                "environment": run_state.environment,
                "build_number": run_state.build_number,
                "task_key": spec.id,
                "attempt": attempt,
                "status": SUCCESS,
                "trigger_rule": spec.trigger,
                "upstream_json": json.dumps(dict(upstream))[:2000],
                "tmf_instance_id": tmf_instance_id,
                "started_at": time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(attempt_started)),
                "finished_at": time.strftime("%Y-%m-%d %H:%M:%S"),
                "duration_ms": int(duration * 1000),
            })
            return TaskResult(
                id=spec.id,
                status=SUCCESS,
                attempt=attempt,
                attempts=attempts,
                duration=time.time() - started,
                value=value,
                tmf_instance_id=tmf_instance_id,
            )
        except SkipTask as skip:
            duration = time.time() - attempt_started
            reason = str(skip) or "the task skipped itself"
            # A skip is a decision, not a failure: it is never retried, and the
            # TMF transaction is cancelled rather than marked in error.
            if transaction is not None:
                try:
                    transaction.cancel(reason)
                except Exception:  # noqa: BLE001
                    pass
            _emit("[JobSeeker Task] %s | SKIPPED | attempt %d/%d | %s | %s" % (
                spec.id, attempt, attempts, _duration(duration), reason))
            run_state.store.record({
                "run_key": run_state.run_key,
                "job_name": run_state.job,
                "environment": run_state.environment,
                "build_number": run_state.build_number,
                "task_key": spec.id,
                "attempt": attempt,
                "status": SKIPPED,
                "trigger_rule": spec.trigger,
                "upstream_json": json.dumps(dict(upstream))[:2000],
                "tmf_instance_id": tmf_instance_id,
                "started_at": time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(attempt_started)),
                "finished_at": time.strftime("%Y-%m-%d %H:%M:%S"),
                "duration_ms": int(duration * 1000),
                "message": reason,
            })
            return TaskResult(
                id=spec.id,
                status=SKIPPED,
                attempt=attempt,
                attempts=attempts,
                duration=time.time() - started,
                message=reason,
                tmf_instance_id=tmf_instance_id,
            )
        except BaseException as error:  # noqa: BLE001 - a failing task must not kill the run
            duration = time.time() - attempt_started
            last_error = "%s: %s" % (type(error).__name__, error)
            if transaction is not None:
                try:
                    transaction.__exit__(type(error), error, error.__traceback__)
                except Exception:  # noqa: BLE001
                    pass
            detail = traceback.format_exc().strip()
            sys.stdout.write(detail + "\n")
            sys.stdout.flush()
            final = attempt >= attempts
            status = FAILURE if final else "RETRY"
            _emit("[JobSeeker Task] %s | %s | attempt %d/%d | %s | %s" % (
                spec.id, status, attempt, attempts, _duration(duration), last_error))
            run_state.store.record({
                "run_key": run_state.run_key,
                "job_name": run_state.job,
                "environment": run_state.environment,
                "build_number": run_state.build_number,
                "task_key": spec.id,
                "attempt": attempt,
                "status": FAILURE,
                "trigger_rule": spec.trigger,
                "upstream_json": json.dumps(dict(upstream))[:2000],
                "tmf_instance_id": tmf_instance_id,
                "started_at": time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(attempt_started)),
                "finished_at": time.strftime("%Y-%m-%d %H:%M:%S"),
                "duration_ms": int(duration * 1000),
                "message": last_error,
            })
            if isinstance(error, KeyboardInterrupt):
                final = True
            if final:
                return TaskResult(
                    id=spec.id,
                    status=FAILURE,
                    attempt=attempt,
                    attempts=attempts,
                    duration=time.time() - started,
                    message=last_error,
                    tmf_instance_id=tmf_instance_id,
                )
            if spec.retry_delay > 0:
                _emit("[JobSeeker Task] %s | RETRY | waiting %s" % (spec.id, _duration(spec.retry_delay)))
                time.sleep(spec.retry_delay)
        finally:
            if client is not None:
                try:
                    client.close()
                except Exception:  # noqa: BLE001
                    pass

    return TaskResult(id=spec.id, status=FAILURE, attempts=attempts, message=last_error)


def _run_dag(
    dag: Dag,
    max_parallel: Optional[int] = None,
    fail_fast: Optional[bool] = None,
    raise_on_failure: bool = True,
    exit_on_failure: Optional[bool] = None,
    job: Optional[str] = None,
    environment: Optional[str] = None,
    run_key: Optional[str] = None,
    state: Optional[bool] = None,
    xcom_path: Optional[str] = None,
) -> DagRunResult:
    """Execute a declared graph in dependency order.

    Tasks become runnable the moment every upstream task has reached a terminal
    state, so independent branches overlap instead of waiting for a whole
    "layer" to finish - the same behaviour an Airflow scheduler gives, inside
    one container.
    """

    if _flag("JOBSEEKER_DAG_DESCRIBE"):
        describe(dag)
        return DagRunResult(
            run_key="", job="", environment="", status=SUCCESS,
            started_at=time.time(), duration=0.0, results={},
        )

    validation = dag.validate()
    order: List[str] = validation["order"]
    specs = dag.tasks

    job_name = job or _env("JOBSEEKER_JOB_NAME", _env("JOB_NAME", "jobseeker-job"))
    environment_name = environment or _env("JOBSEEKER_ENVIRONMENT", _env("ENVIRONMENT", "LOCAL"))
    build_number_raw = _env("BUILD_NUMBER")
    build_number = _int(build_number_raw, 0) if build_number_raw else None
    resolved_run_key = run_key or _resolve_run_key(job_name, environment_name, build_number)

    if max_parallel is None:
        max_parallel = _int(_env("JOBSEEKER_DAG_MAX_PARALLEL", "4"), 4)
    max_parallel = max(1, min(32, max_parallel))

    if fail_fast is None:
        fail_fast = _flag("JOBSEEKER_DAG_FAIL_FAST", False)

    state_enabled = _flag("JOBSEEKER_DAG_STATE", True) if state is None else bool(state)
    store = TaskStateStore(enabled=state_enabled)

    workspace = _env("WORKSPACE") or os.getcwd()
    if xcom_path is None:
        xcom_path = os.path.join(workspace, ".jobseeker-dag", "values-%s.json" % _slug(resolved_run_key, 64))
    xcom = XComStore(xcom_path)

    selected_raw = _env("JOBSEEKER_DAG_TASKS")
    selected = None
    if selected_raw:
        selected = set(part.strip() for part in selected_raw.split(",") if part.strip())
        unknown = sorted(selected - set(specs))
        if unknown:
            raise DagDefinitionError(
                "JOBSEEKER_DAG_TASKS names tasks that are not declared: %s." % ", ".join(unknown)
            )

    track_tasks = state_enabled and _flag("JOBSEEKER_DAG_TMF", True)
    run_state = _RunState(dag, resolved_run_key, job_name, environment_name, build_number,
                          xcom, store, track_tasks)
    store.save_graph(job_name, environment_name, dag.manifest())

    statuses: Dict[str, str] = dict((task_id, PENDING) for task_id in order)
    results: Dict[str, TaskResult] = {}

    restored: Dict[str, str] = {}
    resume_key = _env("JOBSEEKER_DAG_RESUME")
    if resume_key:
        previous = store.completed_tasks(resume_key)
        for task_id, status in previous.items():
            if task_id in statuses and status == SUCCESS:
                restored[task_id] = status
        if restored:
            _emit("[JobSeeker DAG] resume | %d task(s) restored from run %s" % (len(restored), resume_key))
        else:
            _emit("[JobSeeker DAG] resume | no completed tasks found for run %s" % resume_key)

    started_at = time.time()
    _emit("[JobSeeker DAG] start | %d task(s) | run %s | max parallel %d" % (
        len(order), resolved_run_key, max_parallel))

    def _finalise(task_id: str, result: TaskResult, reason: str = "") -> None:
        statuses[task_id] = result.status
        results[task_id] = result
        if result.status in (SKIPPED, UPSTREAM_FAILED):
            _emit("[JobSeeker Task] %s | %s | %s" % (task_id, result.status, reason or result.message))
            store.record({
                "run_key": resolved_run_key,
                "job_name": job_name,
                "environment": environment_name,
                "build_number": build_number,
                "task_key": task_id,
                "attempt": 1,
                "status": result.status,
                "trigger_rule": specs[task_id].trigger,
                "upstream_json": json.dumps(
                    dict((source, statuses.get(source, PENDING)) for source in specs[task_id].depends_on)
                )[:2000],
                "finished_at": time.strftime("%Y-%m-%d %H:%M:%S"),
                "duration_ms": 0,
                "message": reason or result.message,
            })

    for task_id, status in restored.items():
        statuses[task_id] = status
        results[task_id] = TaskResult(id=task_id, status=status, attempt=0, attempts=specs[task_id].attempts,
                                      message="restored from run %s" % resume_key)
        _emit("[JobSeeker Task] %s | SUCCESS | restored from run %s" % (task_id, resume_key))

    aborting = False
    running: Dict[Any, str] = {}

    # Tag task output for the whole run, not only when several tasks overlap, so
    # one job's console reads the same way whatever its parallelism is set to.
    previous_stdout, previous_stderr = sys.stdout, sys.stderr
    tagged_stdout = _TaskOutputStream(previous_stdout, _TASK_STATE)
    tagged_stderr = _TaskOutputStream(previous_stderr, _TASK_STATE)
    _OUTPUT["stdout"], _OUTPUT["stderr"] = tagged_stdout, tagged_stderr
    sys.stdout, sys.stderr = tagged_stdout, tagged_stderr

    try:
        with ThreadPoolExecutor(max_workers=max_parallel) as pool:
            while any(statuses[task_id] in (PENDING, RUNNING) for task_id in order):
                progressed = False

                for task_id in order:
                    if statuses[task_id] != PENDING:
                        continue
                    spec = specs[task_id]
                    if any(statuses[source] in (PENDING, RUNNING) for source in spec.depends_on):
                        continue

                    if aborting:
                        _finalise(task_id, TaskResult(id=task_id, status=SKIPPED, attempts=spec.attempts),
                                  "run aborted after an earlier failure")
                        progressed = True
                        continue

                    should_run, blocked_status, reason = _decide(spec, statuses, selected)
                    if not should_run:
                        _finalise(task_id, TaskResult(id=task_id, status=blocked_status, attempts=spec.attempts),
                                  reason)
                        progressed = True
                        continue

                    if len(running) >= max_parallel:
                        continue

                    upstream = dict((source, statuses[source]) for source in spec.depends_on)
                    statuses[task_id] = RUNNING
                    future = pool.submit(_run_task, run_state, spec, upstream)
                    running[future] = task_id
                    progressed = True

                if running:
                    done, _pending = wait(list(running.keys()), return_when=FIRST_COMPLETED)
                    for future in done:
                        finished_id = running.pop(future)
                        try:
                            result = future.result()
                        except BaseException as error:  # noqa: BLE001 - defensive; _run_task catches
                            result = TaskResult(id=finished_id, status=FAILURE,
                                                message="%s: %s" % (type(error).__name__, error))
                        statuses[finished_id] = result.status
                        results[finished_id] = result
                        if result.status == FAILURE and fail_fast:
                            aborting = True
                elif not progressed:
                    # Unreachable after validate(), but never spin forever.
                    for task_id in order:
                        if statuses[task_id] == PENDING:
                            _finalise(task_id, TaskResult(id=task_id, status=SKIPPED,
                                                          attempts=specs[task_id].attempts),
                                      "no runnable upstream state")
                    break
    finally:
        tagged_stdout.flush_thread()
        tagged_stderr.flush_thread()
        sys.stdout, sys.stderr = previous_stdout, previous_stderr
        _OUTPUT["stdout"], _OUTPUT["stderr"] = None, None
        store.close()

    duration = time.time() - started_at
    totals = {SUCCESS: 0, FAILURE: 0, SKIPPED: 0, UPSTREAM_FAILED: 0}
    for result in results.values():
        totals[result.status] = totals.get(result.status, 0) + 1

    failed = totals.get(FAILURE, 0) + totals.get(UPSTREAM_FAILED, 0)
    overall = FAILURE if failed else SUCCESS
    _emit("[JobSeeker DAG] finish | %s | %d succeeded, %d failed, %d skipped | %s" % (
        overall, totals.get(SUCCESS, 0), failed, totals.get(SKIPPED, 0), _duration(duration)))

    outcome = DagRunResult(
        run_key=resolved_run_key,
        job=job_name,
        environment=environment_name,
        status=overall,
        started_at=started_at,
        duration=duration,
        results=results,
    )

    if failed:
        names = sorted(task_id for task_id, result in results.items() if result.status in FAILED_STATUSES)
        message = "Task DAG failed: %s" % ", ".join(names)
        if exit_on_failure is None:
            exit_on_failure = bool(_env("BUILD_NUMBER")) or _flag("JOBSEEKER_DAG_EXIT_ON_FAILURE", False)
        if exit_on_failure:
            _emit("[JobSeeker DAG] %s" % message)
            raise SystemExit(1)
        if raise_on_failure:
            raise DagRunError(message)

    return outcome


# --- CLI ---------------------------------------------------------------------


def dag_cli(argv: Optional[Sequence[str]] = None) -> int:
    """`jobseeker-dag describe <entrypoint.py>` - print a job's task graph.

    Nothing in the app calls this during a normal run; it exists so a graph can
    be extracted from a sandbox without the caller having to know how the job
    builds its DAG, and so a future one-container-per-task backend has a stable
    way to read the manifest it compiles.
    """

    import argparse
    import runpy

    parser = argparse.ArgumentParser(prog="jobseeker-dag", description="Inspect a JobSeeker task DAG.")
    subparsers = parser.add_subparsers(dest="command")
    describe_parser = subparsers.add_parser("describe", help="Print the task graph as JSON without running it.")
    describe_parser.add_argument("entrypoint", help="Path to the job's Python entry point.")
    run_parser = subparsers.add_parser("run", help="Run the task graph in the current interpreter.")
    run_parser.add_argument("entrypoint", help="Path to the job's Python entry point.")
    run_parser.add_argument("--tasks", default="", help="Comma separated task ids to run.")

    arguments = parser.parse_args(list(argv) if argv is not None else None)
    if not arguments.command:
        parser.print_help()
        return 2

    entrypoint = os.path.abspath(arguments.entrypoint)
    if not os.path.isfile(entrypoint):
        sys.stderr.write("jobseeker-dag: entry point not found: %s\n" % entrypoint)
        return 66

    _CURRENT_DAG.reset()
    if arguments.command == "describe":
        os.environ["JOBSEEKER_DAG_DESCRIBE"] = "1"
        try:
            runpy.run_path(entrypoint, run_name="__main__")
        finally:
            os.environ.pop("JOBSEEKER_DAG_DESCRIBE", None)
        if len(_CURRENT_DAG):
            describe(_CURRENT_DAG)
        return 0

    if arguments.tasks:
        os.environ["JOBSEEKER_DAG_TASKS"] = arguments.tasks
    try:
        runpy.run_path(entrypoint, run_name="__main__")
    except SystemExit as exit_error:
        return _int(exit_error.code, 0)
    return 0


if __name__ == "__main__":  # pragma: no cover - module CLI
    raise SystemExit(dag_cli())
