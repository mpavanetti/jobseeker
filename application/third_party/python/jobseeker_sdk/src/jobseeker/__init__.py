"""JobSeeker Python SDK for TMF logging and context lookup."""

from __future__ import print_function

import functools
import getpass
import inspect
import csv
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


def _data_asset_mode_allowed(direction: str, mode: str) -> bool:
    if mode == "input":
        return direction in ("input", "input_output")
    if mode == "output":
        return direction in ("output", "input_output")
    return direction in ("input", "output", "input_output")


@dataclass
class DataAsset:
    """A resolved, environment-aware file contract from the Data Assets catalog."""

    key: str
    name: str
    uri: str
    direction: str
    format: str
    environment: str
    job: str
    relative_path: str
    file_name: str
    required: bool = True
    version: int = 0
    size: Optional[int] = None
    checksum: Optional[str] = None
    uploaded_at: Optional[str] = None
    options: Optional[Dict[str, Any]] = None
    description: str = ""
    repository_root: str = ""

    @property
    def metadata(self) -> Dict[str, Any]:
        return {
            "key": self.key,
            "name": self.name,
            "uri": self.uri,
            "direction": self.direction,
            "format": self.format,
            "environment": self.environment,
            "job": self.job,
            "relative_path": self.relative_path,
            "file_name": self.file_name,
            "required": self.required,
            "version": self.version,
            "size": self.size,
            "checksum": self.checksum,
            "uploaded_at": self.uploaded_at,
            "options": dict(self.options or {}),
            "description": self.description,
        }

    @property
    def path(self) -> str:
        root = os.path.realpath(os.path.abspath(self.repository_root))
        candidate = os.path.realpath(os.path.abspath(os.path.join(root, self.relative_path)))
        try:
            within_root = os.path.commonpath((root, candidate)) == root
        except ValueError:
            within_root = False
        if not within_root:
            raise JobSeekerError("Data asset path escapes the configured repository: %s" % self.key)
        return candidate

    def __fspath__(self) -> str:
        """Allow pathlib, pandas, and other path-aware libraries to consume the asset directly."""

        return self.path

    def __str__(self) -> str:
        return self.path

    @property
    def exists(self) -> bool:
        return os.path.isfile(self.path)

    def require(self) -> "DataAsset":
        if not self.exists:
            raise JobSeekerError(
                "Required data asset %s has no file at %s. Upload a version in JobSeeker Data Assets."
                % (self.key, self.path)
            )
        return self

    def open(self, mode: str = "r", **kwargs: Any) -> Any:
        writing = any(flag in mode for flag in ("w", "a", "+", "x"))
        if writing and not _data_asset_mode_allowed(self.direction, "output"):
            raise JobSeekerError("Data asset %s is registered as input-only." % self.key)
        if not writing and not _data_asset_mode_allowed(self.direction, "input"):
            raise JobSeekerError("Data asset %s is registered as output-only." % self.key)

        if writing:
            os.makedirs(os.path.dirname(self.path), exist_ok=True)
        elif self.required:
            self.require()

        if "b" not in mode and "encoding" not in kwargs:
            kwargs["encoding"] = str((self.options or {}).get("encoding") or "UTF-8")
        return open(self.path, mode, **kwargs)

    def read(self, **kwargs: Any) -> Any:
        """Read common formats without forcing pandas on lightweight jobs."""

        metadata = dict(self.options or {})
        options = dict(kwargs)
        if self.format == "csv":
            delimiter = options.pop("delimiter", metadata.get("delimiter", ","))
            has_header = bool(options.pop("header", metadata.get("header", True)))
            with self.open("r", newline="") as stream:
                reader = csv.DictReader(stream, delimiter=delimiter, **options) if has_header else csv.reader(stream, delimiter=delimiter, **options)
                return list(reader)
        if self.format == "json":
            with self.open("r") as stream:
                return json.load(stream, **options)
        if self.format == "jsonl":
            with self.open("r") as stream:
                return [json.loads(line) for line in stream if line.strip()]
        if self.format in ("txt", "xml"):
            with self.open("r") as stream:
                return stream.read()
        if self.format == "binary":
            with self.open("rb") as stream:
                return stream.read()
        if self.format in ("xlsx", "parquet"):
            raise JobSeekerDependencyError(
                "%s assets require pandas and the matching engine; use asset.read_dataframe()." % self.format.upper()
            )
        raise JobSeekerError("Unsupported data asset format: %s" % self.format)

    def write(self, data: Any, **kwargs: Any) -> str:
        """Write common formats to a declared output or handoff asset."""

        metadata = dict(self.options or {})
        options = dict(kwargs)
        if self.format == "csv":
            rows = list(data)
            delimiter = options.pop("delimiter", metadata.get("delimiter", ","))
            has_header = bool(options.pop("header", metadata.get("header", True)))
            with self.open("w", newline="") as stream:
                if rows and isinstance(rows[0], Mapping):
                    writer = csv.DictWriter(stream, fieldnames=list(rows[0].keys()), delimiter=delimiter, **options)
                    if has_header:
                        writer.writeheader()
                    writer.writerows(rows)
                else:
                    csv.writer(stream, delimiter=delimiter, **options).writerows(rows)
        elif self.format == "json":
            with self.open("w") as stream:
                json.dump(data, stream, **options)
        elif self.format == "jsonl":
            with self.open("w") as stream:
                for row in data:
                    stream.write(json.dumps(row, **options) + "\n")
        elif self.format in ("txt", "xml"):
            with self.open("w") as stream:
                stream.write(str(data))
        elif self.format == "binary":
            with self.open("wb") as stream:
                stream.write(data)
        elif self.format in ("xlsx", "parquet"):
            raise JobSeekerDependencyError(
                "%s assets require pandas and the matching engine; use asset.write_dataframe(frame)." % self.format.upper()
            )
        else:
            raise JobSeekerError("Unsupported data asset format: %s" % self.format)
        return self.path

    def read_dataframe(self, **kwargs: Any) -> Any:
        try:
            import pandas as pd  # type: ignore
        except ImportError as error:
            raise JobSeekerDependencyError("pandas is required for DataAsset.read_dataframe().") from error

        options = dict(kwargs)
        metadata = dict(self.options or {})
        if self.required:
            self.require()
        if self.format == "csv":
            options.setdefault("sep", metadata.get("delimiter", ","))
            options.setdefault("encoding", metadata.get("encoding", "UTF-8"))
            options.setdefault("header", 0 if metadata.get("header", True) else None)
            return pd.read_csv(self.path, **options)
        if self.format in ("json", "jsonl"):
            options.setdefault("lines", self.format == "jsonl")
            return pd.read_json(self.path, **options)
        if self.format == "xlsx":
            if metadata.get("sheet"):
                options.setdefault("sheet_name", metadata["sheet"])
            return pd.read_excel(self.path, **options)
        if self.format == "parquet":
            return pd.read_parquet(self.path, **options)
        if self.format == "xml":
            return pd.read_xml(self.path, **options)
        raise JobSeekerError("Format %s cannot be loaded as a dataframe." % self.format)

    def write_dataframe(self, frame: Any, **kwargs: Any) -> str:
        if not _data_asset_mode_allowed(self.direction, "output"):
            raise JobSeekerError("Data asset %s is registered as input-only." % self.key)
        os.makedirs(os.path.dirname(self.path), exist_ok=True)
        metadata = dict(self.options or {})
        if self.format == "csv":
            kwargs.setdefault("sep", metadata.get("delimiter", ","))
            kwargs.setdefault("encoding", metadata.get("encoding", "UTF-8"))
            kwargs.setdefault("header", metadata.get("header", True))
            kwargs.setdefault("index", False)
            frame.to_csv(self.path, **kwargs)
        elif self.format == "json":
            frame.to_json(self.path, **kwargs)
        elif self.format == "jsonl":
            kwargs.setdefault("orient", "records")
            kwargs.setdefault("lines", True)
            frame.to_json(self.path, **kwargs)
        elif self.format == "xlsx":
            kwargs.setdefault("index", False)
            if metadata.get("sheet"):
                kwargs.setdefault("sheet_name", metadata["sheet"])
            frame.to_excel(self.path, **kwargs)
        elif self.format == "parquet":
            kwargs.setdefault("index", False)
            frame.to_parquet(self.path, **kwargs)
        elif self.format == "xml":
            frame.to_xml(self.path, **kwargs)
        else:
            raise JobSeekerError("Format %s cannot be written from a dataframe." % self.format)
        return self.path


class DataAssetCatalog:
    """Resolve catalog entries using exact environment/job matches and shared fallbacks."""

    def __init__(
        self,
        environment: Optional[str] = None,
        job: Optional[str] = None,
        repository_root: Optional[str] = None,
        manifest_path: Optional[str] = None,
    ):
        self.environment = (environment or _env("JOBSEEKER_ENVIRONMENT", "LOCAL")).upper()
        self.job = job or _env("JOBSEEKER_DATA_ASSET_JOB") or _safe_job_name(None)
        self.repository_root = os.path.abspath(
            repository_root or _env("JOBSEEKER_REPOSITORY_ROOT", "/php/repository")
        )
        self.manifest_path = os.path.abspath(
            manifest_path
            or _env("JOBSEEKER_DATA_ASSETS_MANIFEST", os.path.join(self.repository_root, "data-assets", "manifest.json"))
        )
        self._assets: Optional[List[Dict[str, Any]]] = None

    def _load(self) -> List[Dict[str, Any]]:
        if self._assets is not None:
            return self._assets
        try:
            with open(self.manifest_path, "r", encoding="utf-8") as stream:
                payload = json.load(stream)
        except FileNotFoundError as error:
            raise JobSeekerError(
                "Data Assets catalog was not found at %s. Open Data Assets in JobSeeker to publish it."
                % self.manifest_path
            ) from error
        except (OSError, ValueError) as error:
            raise JobSeekerError("Data Assets catalog is unreadable: %s" % error) from error
        assets = payload.get("assets", []) if isinstance(payload, dict) else []
        self._assets = [item for item in assets if isinstance(item, dict) and item.get("active", True)]
        return self._assets

    def refresh(self) -> "DataAssetCatalog":
        self._assets = None
        return self

    def list(self, mode: str = "any") -> List[DataAsset]:
        results = []
        for item in self._load():
            if _data_asset_mode_allowed(str(item.get("direction", "input")), mode):
                results.append(self._from_item(item))
        return results

    def resolve(
        self,
        key: str,
        mode: str = "input",
        environment: Optional[str] = None,
        job: Optional[str] = None,
        required: bool = True,
    ) -> Optional[DataAsset]:
        target_environment = (environment or self.environment).upper()
        target_job = job or self.job
        matches = []
        for item in self._load():
            if item.get("key") != key or not _data_asset_mode_allowed(str(item.get("direction", "input")), mode):
                continue
            item_environment = str(item.get("environment", "ALL")).upper()
            item_job = str(item.get("job", "*"))
            if item_environment not in (target_environment, "ALL") or item_job not in (target_job, "*"):
                continue
            score = (20 if item_environment == target_environment else 10) + (2 if item_job == target_job else 1)
            matches.append((score, item))

        if not matches:
            if required:
                key_contracts = [item for item in self._load() if item.get("key") == key]
                role_contracts = [
                    item
                    for item in key_contracts
                    if _data_asset_mode_allowed(str(item.get("direction", "input")), mode)
                ]
                if key_contracts and not role_contracts:
                    roles = sorted({str(item.get("direction", "input")) for item in key_contracts})
                    hint = " Registered role(s): %s." % ", ".join(roles)
                elif role_contracts:
                    scopes = sorted(
                        {
                            "%s/%s"
                            % (
                                str(item.get("environment", "ALL")).upper(),
                                "shared" if str(item.get("job", "*")) == "*" else str(item.get("job")),
                            )
                            for item in role_contracts
                        }
                    )
                    hint = " Published scope(s): %s." % ", ".join(scopes[:8])
                else:
                    available_keys = sorted(
                        {
                            str(item.get("key"))
                            for item in self._load()
                            if item.get("key") and _data_asset_mode_allowed(str(item.get("direction", "input")), mode)
                        }
                    )
                    hint = " Available %s key(s): %s." % (
                        mode,
                        ", ".join(available_keys[:8]) if available_keys else "none",
                    )
                raise JobSeekerError(
                    "Data asset %s was not published for environment=%s job=%s mode=%s.%s "
                    "Register a matching contract in Extract Transform Load > Data Assets, "
                    "or pass required=False for an optional input."
                    % (key, target_environment, target_job, mode, hint)
                )
            return None

        matches.sort(key=lambda match: match[0], reverse=True)
        asset = self._from_item(matches[0][1])
        if mode == "input" and (required or asset.required):
            asset.require()
        return asset

    def _from_item(self, item: Mapping[str, Any]) -> DataAsset:
        return DataAsset(
            key=str(item.get("key", "")),
            name=str(item.get("name", item.get("key", ""))),
            uri=str(item.get("uri", "")),
            direction=str(item.get("direction", "input")),
            format=str(item.get("format", "binary")),
            environment=str(item.get("environment", "ALL")),
            job=str(item.get("job", "*")),
            relative_path=str(item.get("relative_path", "")),
            file_name=str(item.get("file_name", "")),
            required=bool(item.get("required", True)),
            version=_coerce_int(item.get("version"), 0),
            size=None if item.get("size") is None else _coerce_int(item.get("size"), 0),
            checksum=item.get("checksum"),
            uploaded_at=item.get("uploaded_at"),
            options=dict(item.get("options") or {}),
            description=str(item.get("description") or ""),
            repository_root=self.repository_root,
        )


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
                "Add it to pyproject.toml or requirements.txt, or configure JOBSEEKER_API_URL when an API transport is available."
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
        self._data_asset_catalog: Optional[DataAssetCatalog] = None

        if install_signal_handlers:
            self.registerSignalHandlers()

        LOGGER.info("JobSeeker job=%s environment=%s instance_id=%s", self.job, self.environment, self.instance_id)

    @property
    def data_assets(self) -> DataAssetCatalog:
        if self._data_asset_catalog is None:
            asset_job = _env("JOBSEEKER_DATA_ASSET_JOB") or self.job
            self._data_asset_catalog = DataAssetCatalog(environment=self.environment, job=asset_job)
        return self._data_asset_catalog

    def asset(
        self,
        key: str,
        mode: str = "input",
        required: bool = True,
        environment: Optional[str] = None,
        job: Optional[str] = None,
    ) -> Optional[DataAsset]:
        """Resolve a named Data Asset for this job's runtime scope."""

        return self.data_assets.resolve(
            key,
            mode=mode,
            required=required,
            environment=environment,
            job=job,
        )

    dataset = asset

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

    def email_metrics(
        self,
        dataset: Any,
        rows_read: Any,
        rows_written: Any,
        rows_rejected: Any = 0,
        duration: Any = "",
    ) -> Dict[str, str]:
        metrics = {
            "DATASET": dataset,
            "ROWS_READ": rows_read,
            "ROWS_WRITTEN": rows_written,
            "ROWS_REJECTED": rows_rejected,
            "DURATION": duration,
        }
        normalized = {
            key: str(value if value is not None else "").replace("\r", " ").replace("\n", " ").strip()
            for key, value in metrics.items()
        }

        for key, value in normalized.items():
            print("JOBSEEKER_EMAIL_%s=%s" % (key, value))

        metrics_file = os.environ.get("JOBSEEKER_EMAIL_METRICS_FILE", "").strip()
        if metrics_file:
            directory = os.path.dirname(os.path.abspath(metrics_file))
            os.makedirs(directory, exist_ok=True)
            with open(metrics_file, "w", encoding="utf-8") as stream:
                for key, value in normalized.items():
                    property_value = value.replace("\\", "\\\\").replace("\t", "\\t")
                    stream.write("%s=%s\n" % (key.lower(), property_value))

        return normalized

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

    def asset(
        self,
        key: str,
        mode: str = "input",
        required: bool = True,
        environment: Optional[str] = None,
        job: Optional[str] = None,
    ) -> Optional[DataAsset]:
        return self.client.asset(key, mode=mode, required=required, environment=environment, job=job)

    dataset = asset

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


def get_asset(
    key: str,
    mode: str = "input",
    required: bool = True,
    environment: Optional[str] = None,
    job: Optional[str] = None,
    repository_root: Optional[str] = None,
    manifest_path: Optional[str] = None,
) -> Optional[DataAsset]:
    catalog = DataAssetCatalog(
        environment=environment,
        job=job,
        repository_root=repository_root,
        manifest_path=manifest_path,
    )
    return catalog.resolve(key, mode=mode, required=required)


def asset_cli() -> None:
    """Resolve an asset path for shell jobs: jobseeker-asset ASSET_KEY."""

    import argparse

    parser = argparse.ArgumentParser(description="Resolve a JobSeeker Data Asset runtime path.")
    parser.add_argument("key", help="Published Data Asset key")
    parser.add_argument("--mode", choices=("input", "output", "any"), default="input")
    parser.add_argument("--environment", default=None)
    parser.add_argument("--job", default=None)
    parser.add_argument("--metadata", action="store_true", help="Print the resolved contract as JSON")
    arguments = parser.parse_args()
    try:
        asset = get_asset(
            arguments.key,
            mode=arguments.mode,
            environment=arguments.environment,
            job=arguments.job,
        )
        if asset is None:
            raise JobSeekerError("Data asset was not found: %s" % arguments.key)
        print(json.dumps(asset.metadata, indent=2, sort_keys=True) if arguments.metadata else asset.path)
    except JobSeekerError as error:
        parser.exit(2, "jobseeker-asset: %s\n" % error)


__all__ = [
    "ApiConfig",
    "ApiTransport",
    "DataAsset",
    "DataAssetCatalog",
    "DatabaseConfig",
    "JobSeeker",
    "JobSeekerDependencyError",
    "JobSeekerError",
    "MariaDbTransport",
    "TmfTask",
    "TmfTransport",
    "client",
    "get_asset",
    "get_context",
    "jobSeeker",
    "task",
]
