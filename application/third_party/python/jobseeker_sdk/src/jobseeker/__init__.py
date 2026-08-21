"""JobSeeker Python SDK for TMF logging and context lookup."""

from __future__ import print_function

import functools
import getpass
import inspect
import json
import logging
import os
import signal
import socket
import sys
import traceback
import uuid
from dataclasses import dataclass
from typing import Any, Callable, Dict, Iterable, List, Mapping, Optional


LOGGER = logging.getLogger("jobseeker")


class JobSeekerError(Exception):
    """Base exception for JobSeeker SDK failures."""


class JobSeekerDependencyError(JobSeekerError):
    """Raised when an optional runtime dependency is missing."""


def _env(name: str, default: str = "") -> str:
    value = os.environ.get(name)
    return default if value is None or value == "" else value


def _coerce_int(value: Any, default: int = 0) -> int:
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


def _as_bool(value: Any) -> bool:
    if isinstance(value, bool):
        return value
    return str(value).strip().lower() in ("1", "true", "yes", "y", "on")


def _new_instance_id() -> str:
    return uuid.uuid4().hex.upper()


def _safe_job_name(job: Optional[str]) -> str:
    job_name = _env("JOB_NAME") or _env("JOBSEEKER_JOB_NAME") or (job or "")
    if job_name:
        return job_name

    script_name = os.path.basename(sys.argv[0] or "")
    return os.path.splitext(script_name)[0] or "python-job"


def _function_accepts_keyword(function: Callable[..., Any], keyword: str) -> bool:
    try:
        signature = inspect.signature(function)
    except (TypeError, ValueError):
        return False

    for parameter in signature.parameters.values():
        if parameter.kind == inspect.Parameter.VAR_KEYWORD:
            return True
        if parameter.name == keyword and parameter.kind in (
            inspect.Parameter.POSITIONAL_OR_KEYWORD,
            inspect.Parameter.KEYWORD_ONLY,
        ):
            return True

    return False


@dataclass(frozen=True)
class DatabaseConfig:
    host: str = "mariadb"
    port: int = 3306
    user: str = "mysql"
    password: str = "mysql"
    database: str = "jobseeker"

    @classmethod
    def from_env(cls) -> "DatabaseConfig":
        return cls.from_mapping()

    @classmethod
    def from_mapping(cls, mapping: Optional[Mapping[str, Any]] = None) -> "DatabaseConfig":
        values = mapping or {}
        return cls(
            host=_env("JOBSEEKER_DB_HOST", _env("MYSQL_HOST", str(values.get("host", "mariadb")))),
            port=_coerce_int(_env("JOBSEEKER_DB_PORT", _env("MYSQL_PORT", str(values.get("port", "3306")))), 3306),
            user=_env("JOBSEEKER_DB_USER", _env("MYSQL_USER", str(values.get("user", "mysql")))),
            password=_env("JOBSEEKER_DB_PASSWORD", _env("MYSQL_PASSWORD", str(values.get("password", "mysql")))),
            database=_env("JOBSEEKER_DB_NAME", _env("MYSQL_DATABASE", str(values.get("database", "jobseeker")))),
        )


@dataclass(frozen=True)
class ApiConfig:
    base_url: str
    token: str = ""
    timeout_seconds: int = 15

    @classmethod
    def from_env(cls) -> Optional["ApiConfig"]:
        base_url = _env("JOBSEEKER_API_URL")
        if not base_url:
            return None

        return cls(
            base_url=base_url.rstrip("/"),
            token=_env("JOBSEEKER_API_TOKEN"),
            timeout_seconds=_coerce_int(_env("JOBSEEKER_API_TIMEOUT", "15"), 15),
        )


class TmfTransport:
    """Transport boundary for TMF operations.

    The current app uses MariaDB directly. Keeping this interface explicit makes
    the Python API ready for a token-authenticated HTTP endpoint later.
    """

    def begin(self, payload: Dict[str, Any]) -> bool:
        raise NotImplementedError

    def finish(self, payload: Dict[str, Any]) -> bool:
        raise NotImplementedError

    def fail(self, payload: Dict[str, Any]) -> bool:
        raise NotImplementedError

    def cancel(self, payload: Dict[str, Any]) -> bool:
        raise NotImplementedError

    def heartbeat(self, payload: Dict[str, Any]) -> bool:
        raise NotImplementedError

    def get_context(self, payload: Dict[str, Any]) -> Optional[str]:
        raise NotImplementedError

    def close(self) -> None:
        pass


class MariaDbTransport(TmfTransport):
    def __init__(self, config: Optional[DatabaseConfig] = None):
        self.config = config or DatabaseConfig.from_env()
        self._connection = None

    def _mysql_connector(self):
        try:
            import mysql.connector  # type: ignore
        except ImportError as error:
            raise JobSeekerDependencyError(
                "mysql-connector-python is required for direct JobSeeker TMF access. "
                "Add it to requirements.txt or configure JOBSEEKER_API_URL when an API transport is available."
            ) from error

        return mysql.connector

    def _connect(self):
        mysql_connector = self._mysql_connector()
        if self._connection is not None:
            try:
                if self._connection.is_connected():
                    return self._connection
            except AttributeError:
                return self._connection

        self._connection = mysql_connector.connect(
            host=self.config.host,
            port=self.config.port,
            user=self.config.user,
            password=self.config.password,
            database=self.config.database,
        )
        return self._connection

    def _execute_write(self, statements: Iterable[Any]) -> bool:
        connection = self._connect()
        cursor = connection.cursor()
        try:
            last_rowcount = 0
            for sql, values in statements:
                cursor.execute(sql, values)
                last_rowcount = cursor.rowcount

            if last_rowcount >= 0:
                connection.commit()
                return True

            connection.rollback()
            return False
        except Exception:
            connection.rollback()
            raise
        finally:
            cursor.close()

    def begin(self, payload: Dict[str, Any]) -> bool:
        sql = (
            "INSERT INTO tmf "
            "(interface_id, status, job_name, reprocess, event_text, dimension, environment, "
            "records_total, records_processed, last_activity, running_time, distict_errors, warnings, "
            "hostname, username, instance_id, start_time, msg) "
            "VALUES (%s, 'running', %s, %s, %s, %s, %s, %s, 0, now(), NULL, 0, %s, %s, %s, %s, now(), %s)"
        )
        values = (
            payload["interface_id"],
            payload["job_name"],
            1 if payload.get("reprocess") else 0,
            payload.get("event_text") or "",
            payload.get("dimension") or "",
            payload.get("environment") or "",
            str(payload.get("records_total", 0)),
            payload.get("warnings") or "0",
            payload.get("hostname") or "",
            payload.get("username") or "",
            payload["instance_id"],
            payload.get("message") or None,
        )
        return self._execute_write(((sql, values),))

    def finish(self, payload: Dict[str, Any]) -> bool:
        sql = (
            "UPDATE tmf SET status = 'ready', records_total = %s, records_processed = %s, "
            "last_activity = now(), running_time = TIMEDIFF(now(), start_time), msg = %s "
            "WHERE instance_id = %s AND environment = %s AND job_name = %s"
        )
        values = (
            str(payload.get("records_total", 0)),
            str(payload.get("records_processed", 0)),
            payload.get("message") or "",
            payload["instance_id"],
            payload.get("environment") or "",
            payload["job_name"],
        )
        return self._execute_write(((sql, values),))

    def fail(self, payload: Dict[str, Any]) -> bool:
        insert_error = (
            "INSERT INTO tmf_error (tmf_id, job_name, moment, type, origin, message, code) "
            "VALUES (%s, %s, now(), %s, %s, %s, %s)"
        )
        insert_values = (
            payload["instance_id"],
            payload["job_name"],
            payload.get("type") or "Python Exception",
            payload.get("origin") or "Python",
            payload.get("message") or "",
            _coerce_int(payload.get("code"), 1),
        )
        update_tmf = (
            "UPDATE tmf SET status = 'error', event_text = %s, last_activity = now(), "
            "running_time = TIMEDIFF(now(), start_time), distict_errors = 1, warnings = '0', msg = %s "
            "WHERE instance_id = %s AND job_name = %s AND environment = %s"
        )
        update_values = (
            payload.get("event_text") or "Error(s) Found",
            payload.get("message") or "",
            payload["instance_id"],
            payload["job_name"],
            payload.get("environment") or "",
        )
        return self._execute_write(((insert_error, insert_values), (update_tmf, update_values)))

    def cancel(self, payload: Dict[str, Any]) -> bool:
        sql = (
            "UPDATE tmf SET status = 'cancelled', event_text = 'Cancelled', last_activity = now(), "
            "running_time = TIMEDIFF(now(), start_time), msg = %s "
            "WHERE instance_id = %s AND environment = %s AND job_name = %s AND LOWER(status) = 'running'"
        )
        values = (
            payload.get("message") or "Process cancelled",
            payload["instance_id"],
            payload.get("environment") or "",
            payload["job_name"],
        )
        return self._execute_write(((sql, values),))

    def heartbeat(self, payload: Dict[str, Any]) -> bool:
        assignments = ["last_activity = now()"]
        values = []

        if payload.get("records_total") is not None:
            assignments.append("records_total = %s")
            values.append(str(payload.get("records_total")))

        if payload.get("records_processed") is not None:
            assignments.append("records_processed = %s")
            values.append(str(payload.get("records_processed")))

        if payload.get("message") is not None:
            assignments.append("msg = %s")
            values.append(payload.get("message") or "")

        sql = "UPDATE tmf SET " + ", ".join(assignments) + " WHERE instance_id = %s AND environment = %s AND job_name = %s"
        values.extend((payload["instance_id"], payload.get("environment") or "", payload["job_name"]))
        return self._execute_write(((sql, tuple(values)),))

    def get_context(self, payload: Dict[str, Any]) -> Optional[str]:
        connection = self._connect()
        cursor = connection.cursor()
        try:
            values = [payload["key"], payload["environment"]]
            sql = (
                "SELECT ContextValue FROM vw_contextdetails "
                "WHERE ContextKey = %s AND Environment = %s AND IsActive = 1"
            )
            if payload.get("project"):
                sql += " AND ProjectName = %s"
                values.append(payload["project"])

            sql += " ORDER BY Id DESC LIMIT 1"
            cursor.execute(sql, tuple(values))
            row = cursor.fetchone()
            return None if row is None else row[0]
        finally:
            cursor.close()

    def close(self) -> None:
        if self._connection is None:
            return

        try:
            self._connection.close()
        finally:
            self._connection = None


class ApiTransport(TmfTransport):
    """HTTP transport for a future JobSeeker API.

    The PHP app does not expose this API yet. Jobs can switch to it later by
    setting JOBSEEKER_API_URL/JOBSEEKER_API_TOKEN without changing decorators or
    task code.
    """

    def __init__(self, config: ApiConfig):
        self.config = config

    def _request(self, path: str, payload: Dict[str, Any]) -> Any:
        from urllib.error import HTTPError
        from urllib.request import Request, urlopen

        body = json.dumps(payload).encode("utf-8")
        headers = {"Content-Type": "application/json", "Accept": "application/json"}
        if self.config.token:
            headers["Authorization"] = "Bearer " + self.config.token

        request = Request(self.config.base_url + path, data=body, headers=headers, method="POST")
        try:
            with urlopen(request, timeout=self.config.timeout_seconds) as response:
                content = response.read().decode("utf-8")
        except HTTPError as error:
            detail = error.read().decode("utf-8", "replace")
            raise JobSeekerError("JobSeeker API request failed: %s %s" % (error.code, detail))

        return json.loads(content or "{}")

    def _ok(self, path: str, payload: Dict[str, Any]) -> bool:
        response = self._request(path, payload)
        return bool(response.get("ok", True))

    def begin(self, payload: Dict[str, Any]) -> bool:
        return self._ok("/tmf/begin", payload)

    def finish(self, payload: Dict[str, Any]) -> bool:
        return self._ok("/tmf/finish", payload)

    def fail(self, payload: Dict[str, Any]) -> bool:
        return self._ok("/tmf/error", payload)

    def cancel(self, payload: Dict[str, Any]) -> bool:
        return self._ok("/tmf/cancel", payload)

    def heartbeat(self, payload: Dict[str, Any]) -> bool:
        return self._ok("/tmf/heartbeat", payload)

    def get_context(self, payload: Dict[str, Any]) -> Optional[str]:
        response = self._request("/contexts/value", payload)
        return response.get("value")


def default_transport(database_config: Optional[DatabaseConfig] = None) -> TmfTransport:
    api_config = ApiConfig.from_env()
    transport_name = _env("JOBSEEKER_TRANSPORT", "").lower()
    if transport_name == "api" or api_config is not None:
        if api_config is None:
            raise JobSeekerError("JOBSEEKER_TRANSPORT=api requires JOBSEEKER_API_URL.")
        return ApiTransport(api_config)

    return MariaDbTransport(database_config)


class JobSeeker:
    connection = {
        "host": "mariadb",
        "port": 3306,
        "user": "mysql",
        "password": "mysql",
        "database": "jobseeker",
    }

    def __init__(
        self,
        environment: Optional[str] = None,
        job: Optional[str] = None,
        transport: Optional[TmfTransport] = None,
        interface_id: str = "1",
        instance_id: Optional[str] = None,
        install_signal_handlers: bool = True,
    ):
        self.environment = environment or _env("JOBSEEKER_ENVIRONMENT", "LOCAL")
        self.job = _safe_job_name(job)
        self.interface_id = interface_id
        self.id = instance_id or _new_instance_id()
        self.instance_id = self.id
        self.transport = transport or default_transport(DatabaseConfig.from_mapping(self.connection))
        self.transaction_open = False
        self._active_tasks: List[Any] = []
        self._signal_handlers_installed = False

        if install_signal_handlers:
            self.registerSignalHandlers()

        LOGGER.info("JobSeeker job=%s environment=%s instance_id=%s", self.job, self.environment, self.instance_id)

    def _base_payload(self, instance_id: Optional[str] = None) -> Dict[str, Any]:
        return {
            "interface_id": self.interface_id,
            "job_name": self.job,
            "environment": self.environment,
            "instance_id": instance_id or self.instance_id,
            "hostname": socket.gethostname(),
            "username": getpass.getuser(),
        }

    def registerSignalHandlers(self) -> None:
        if self._signal_handlers_installed:
            return

        for signal_name in ("SIGTERM", "SIGINT"):
            if not hasattr(signal, signal_name):
                continue

            try:
                signal.signal(getattr(signal, signal_name), self.cancelOnSignal)
            except ValueError:
                pass

        self._signal_handlers_installed = True

    def cancelOnSignal(self, signum: int, frame: Any) -> None:
        message = "Process interrupted by signal %s" % signum
        if self._active_tasks:
            self._active_tasks[-1].cancel(message)
        else:
            self.cancel(message)
        raise SystemExit(128 + signum)

    def _register_task(self, task: Any) -> None:
        self._active_tasks.append(task)

    def _unregister_task(self, task: Any) -> None:
        try:
            self._active_tasks.remove(task)
        except ValueError:
            pass

    def get_context(
        self,
        key: str,
        default: Any = None,
        cast: Optional[Callable[[Any], Any]] = None,
        required: bool = False,
        project: Optional[str] = None,
    ) -> Any:
        payload = self._base_payload()
        payload.update({"key": key, "project": project})
        value = self.transport.get_context(payload)

        if value is None:
            if required:
                raise JobSeekerError("Context value not found: %s (%s)" % (key, self.environment))
            return default

        if cast is None:
            return value

        try:
            return cast(value)
        except Exception as error:
            raise JobSeekerError("Context value %s could not be converted: %s" % (key, error)) from error

    def getContext(self, context: str) -> Any:
        value = self.get_context(context)
        if value is None:
            return "Context provided was not found on database, please check your context parameter."
        return value

    def begin(
        self,
        eventText: str = "",
        dimension: str = "",
        records_total: int = 0,
        reprocess: bool = False,
        msg: Optional[str] = None,
    ) -> bool:
        payload = self._base_payload()
        payload.update(
            {
                "event_text": eventText,
                "dimension": dimension,
                "records_total": records_total,
                "reprocess": _as_bool(reprocess),
                "message": msg,
            }
        )
        ok = self.transport.begin(payload)
        self.transaction_open = bool(ok)
        if ok:
            print("[Transaction Started] 1 record inserted.")
        return ok

    def heartbeat(
        self,
        records_total: Optional[int] = None,
        records_processed: Optional[int] = None,
        msg: Optional[str] = None,
    ) -> bool:
        payload = self._base_payload()
        payload.update(
            {
                "records_total": records_total,
                "records_processed": records_processed,
                "message": msg,
            }
        )
        return self.transport.heartbeat(payload)

    def end(self, records_total: int = 0, records_processed: int = 0, msg: str = "") -> bool:
        payload = self._base_payload()
        payload.update(
            {
                "records_total": records_total,
                "records_processed": records_processed,
                "message": msg,
            }
        )
        ok = self.transport.finish(payload)
        if ok:
            self.transaction_open = False
            print("[Transaction Finished] 1 record updated.")
        return ok

    def error(self, msg: str = "", origin: str = "Python Method", code: int = 1) -> bool:
        payload = self._base_payload()
        payload.update(
            {
                "message": msg,
                "origin": origin,
                "code": code,
                "type": "Python Exception",
                "event_text": "Error(s) Found",
            }
        )
        ok = self.transport.fail(payload)
        if ok:
            self.transaction_open = False
            print("[Transaction Error] 1 record inserted.")
        return ok

    def cancel(self, msg: str = "Process cancelled") -> bool:
        if not self.transaction_open:
            return False

        payload = self._base_payload()
        payload.update({"message": msg})
        ok = self.transport.cancel(payload)
        if ok:
            self.transaction_open = False
            print("[Transaction Cancelled] 1 record updated.")
        return ok

    def task(
        self,
        event_text: str = "",
        dimension: str = "",
        records_total: int = 0,
        reprocess: bool = False,
        msg: Optional[str] = None,
    ) -> "TmfTask":
        return TmfTask(
            client=self,
            event_text=event_text,
            dimension=dimension,
            records_total=records_total,
            reprocess=reprocess,
            msg=msg,
        )

    transaction = task

    def track(
        self,
        event_text: str = "",
        dimension: str = "",
        records_total: int = 0,
        reprocess: bool = False,
        inject_as: str = "tmf",
    ) -> Callable[[Callable[..., Any]], Callable[..., Any]]:
        def decorator(function: Callable[..., Any]) -> Callable[..., Any]:
            @functools.wraps(function)
            def wrapper(*args: Any, **kwargs: Any) -> Any:
                with self.task(event_text, dimension, records_total, reprocess) as tmf:
                    if inject_as and inject_as not in kwargs and _function_accepts_keyword(function, inject_as):
                        kwargs[inject_as] = tmf
                    return function(*args, **kwargs)

            return wrapper

        return decorator

    def close(self) -> None:
        self.transport.close()

    def __enter__(self) -> "JobSeeker":
        return self

    def __exit__(self, exc_type: Any, exc_value: Any, exc_traceback: Any) -> None:
        self.close()


class TmfTask:
    def __init__(
        self,
        client: JobSeeker,
        event_text: str = "",
        dimension: str = "",
        records_total: int = 0,
        reprocess: bool = False,
        msg: Optional[str] = None,
    ):
        self.client = client
        self.event_text = event_text
        self.dimension = dimension
        self.records_total = records_total
        self.records_processed = 0
        self.reprocess = reprocess
        self.message = msg or ""
        self._instance_id = _new_instance_id()
        self.open = False

    @property
    def instance_id(self) -> str:
        return self._instance_id

    def _base_payload(self) -> Dict[str, Any]:
        return self.client._base_payload(self.instance_id)

    def __enter__(self) -> "TmfTask":
        payload = self._base_payload()
        payload.update(
            {
                "event_text": self.event_text,
                "dimension": self.dimension,
                "records_total": self.records_total,
                "reprocess": _as_bool(self.reprocess),
                "message": self.message or None,
            }
        )
        self.open = self.client.transport.begin(payload)
        if self.open:
            self.client._register_task(self)
        return self

    def __exit__(self, exc_type: Any, exc_value: Any, exc_traceback: Any) -> None:
        if exc_type is not None:
            if self.open:
                details = "".join(traceback.format_exception(exc_type, exc_value, exc_traceback)).strip()
                self.fail(details or str(exc_value), origin=getattr(exc_type, "__name__", "Python Exception"))
            return

        if self.open:
            self.finish(self.records_total, self.records_processed, self.message)

    def context(
        self,
        key: str,
        default: Any = None,
        cast: Optional[Callable[[Any], Any]] = None,
        required: bool = False,
        project: Optional[str] = None,
    ) -> Any:
        return self.client.get_context(key, default=default, cast=cast, required=required, project=project)

    def progress(
        self,
        processed: Optional[int] = None,
        total: Optional[int] = None,
        msg: Optional[str] = None,
    ) -> bool:
        if total is not None:
            self.records_total = total
        if processed is not None:
            self.records_processed = processed
        if msg is not None:
            self.message = msg

        payload = self._base_payload()
        payload.update(
            {
                "records_total": total,
                "records_processed": processed,
                "message": msg,
            }
        )
        return self.client.transport.heartbeat(payload)

    def finish(self, total: Optional[int] = None, processed: Optional[int] = None, msg: Optional[str] = None) -> bool:
        if total is not None:
            self.records_total = total
        if processed is not None:
            self.records_processed = processed
        if msg is not None:
            self.message = msg

        payload = self._base_payload()
        payload.update(
            {
                "records_total": self.records_total,
                "records_processed": self.records_processed,
                "message": self.message,
            }
        )
        ok = self.client.transport.finish(payload)
        self.open = False
        self.client._unregister_task(self)
        return ok

    def fail(self, msg: str, origin: str = "Python Method", code: int = 1) -> bool:
        payload = self._base_payload()
        payload.update(
            {
                "message": msg,
                "origin": origin,
                "code": code,
                "type": "Python Exception",
                "event_text": "Error(s) Found",
            }
        )
        ok = self.client.transport.fail(payload)
        self.open = False
        self.client._unregister_task(self)
        return ok

    def cancel(self, msg: str = "Process cancelled") -> bool:
        payload = self._base_payload()
        payload.update({"message": msg})
        ok = self.client.transport.cancel(payload)
        self.open = False
        self.client._unregister_task(self)
        return ok


class jobSeeker(JobSeeker):
    """Backward-compatible class name used by existing Python jobs."""


def client(environment: Optional[str] = None, job: Optional[str] = None, **kwargs: Any) -> JobSeeker:
    return JobSeeker(environment=environment, job=job, **kwargs)


def task(
    event_text: str = "",
    dimension: str = "",
    records_total: int = 0,
    reprocess: bool = False,
    environment: Optional[str] = None,
    job: Optional[str] = None,
    inject_as: str = "tmf",
) -> Callable[[Callable[..., Any]], Callable[..., Any]]:
    def decorator(function: Callable[..., Any]) -> Callable[..., Any]:
        @functools.wraps(function)
        def wrapper(*args: Any, **kwargs: Any) -> Any:
            with JobSeeker(environment=environment, job=job) as seeker:
                with seeker.task(event_text, dimension, records_total, reprocess) as tmf:
                    if inject_as and inject_as not in kwargs and _function_accepts_keyword(function, inject_as):
                        kwargs[inject_as] = tmf
                    return function(*args, **kwargs)

        return wrapper

    return decorator


def get_context(
    key: str,
    default: Any = None,
    cast: Optional[Callable[[Any], Any]] = None,
    required: bool = False,
    project: Optional[str] = None,
    environment: Optional[str] = None,
) -> Any:
    with JobSeeker(environment=environment, install_signal_handlers=False) as seeker:
        return seeker.get_context(key, default=default, cast=cast, required=required, project=project)


__all__ = [
    "ApiConfig",
    "ApiTransport",
    "DatabaseConfig",
    "JobSeeker",
    "JobSeekerDependencyError",
    "JobSeekerError",
    "MariaDbTransport",
    "TmfTask",
    "TmfTransport",
    "client",
    "get_context",
    "jobSeeker",
    "task",
]