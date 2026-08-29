"""JobSeeker Python SDK for TMF logging and context lookup."""

from __future__ import print_function

import base64
import functools
import getpass
import inspect
import csv
import json
import logging
import os
import signal
import shutil
import socket
import sys
import traceback
import urllib.error
import urllib.parse
import urllib.request
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
class Connector:
    """A named, scoped connection with secrets loaded from protected runtime files."""

    key: str
    type: str
    environment: str
    job: str
    config: Mapping[str, Any]
    secrets: Mapping[str, str]
    description: str = ""

    def value(self, name: str, default: Any = None, required: bool = False) -> Any:
        value = self.secrets.get(name, self.config.get(name, default))
        if required and (value is None or value == ""):
            raise JobSeekerError("Connector %s has no value for %s." % (self.key, name))
        return value

    @property
    def host(self) -> str:
        return str(self.value("host", ""))

    @property
    def port(self) -> int:
        return _coerce_int(self.value("port", 0), 0)

    @property
    def database(self) -> str:
        return str(self.value("database", ""))

    @property
    def username(self) -> str:
        return str(self.value("username", ""))

    @property
    def password(self) -> str:
        return str(self.value("password", ""))

    def as_dict(self, include_secrets: bool = False) -> Dict[str, Any]:
        values = {
            "key": self.key,
            "type": self.type,
            "environment": self.environment,
            "job": self.job,
            "description": self.description,
            "config": dict(self.config),
        }
        if include_secrets:
            values["secrets"] = dict(self.secrets)
        return values

    def environment_variables(self) -> Dict[str, str]:
        values = dict(self.config)
        values.update(self.secrets)
        result = {
            "JOBSEEKER_CONNECTOR_KEY": self.key,
            "JOBSEEKER_CONNECTOR_TYPE": self.type,
        }
        for name, value in values.items():
            normalized = "".join(character if character.isalnum() else "_" for character in str(name)).upper()
            if normalized:
                result["JOBSEEKER_CONNECTOR_" + normalized] = str(value)
        return result


class ConnectorCatalog:
    """Resolve connectors materialized for the current build and scope."""

    def __init__(self, directory: Optional[str] = None):
        self.directory = os.path.abspath(directory or _env("JOBSEEKER_CONNECTORS_DIR", ".jobseeker-connectors"))
        self.manifest_path = os.path.join(self.directory, "connectors.json")
        self._connectors: Optional[Dict[str, Connector]] = None

    def _safe_secret_path(self, relative_path: str) -> str:
        root = os.path.realpath(self.directory)
        candidate = os.path.realpath(os.path.join(root, relative_path))
        try:
            within_root = os.path.commonpath((root, candidate)) == root
        except ValueError:
            within_root = False
        if not within_root:
            raise JobSeekerError("Connector secret path escapes the runtime directory.")
        return candidate

    def _load(self) -> Dict[str, Connector]:
        if self._connectors is not None:
            return self._connectors
        try:
            with open(self.manifest_path, "r", encoding="utf-8") as stream:
                payload = json.load(stream)
        except FileNotFoundError as error:
            raise JobSeekerError(
                "Connector catalog was not materialized at %s. Check the job runtime connector configuration."
                % self.manifest_path
            ) from error
        except (OSError, ValueError) as error:
            raise JobSeekerError("Connector catalog is unreadable: %s" % error) from error

        self._connectors = {}
        for item in payload.get("connectors", []) if isinstance(payload, dict) else []:
            if not isinstance(item, dict) or not item.get("key"):
                continue
            secrets = {}
            for name, relative_path in dict(item.get("secret_files") or {}).items():
                try:
                    with open(self._safe_secret_path(str(relative_path)), "r", encoding="utf-8") as stream:
                        secrets[str(name)] = stream.read()
                except OSError as error:
                    raise JobSeekerError("Secret %s for connector %s is unreadable." % (name, item["key"])) from error
            connector = Connector(
                key=str(item["key"]),
                type=str(item.get("type", "generic")),
                environment=str(item.get("environment", "ALL")),
                job=str(item.get("job", "*")),
                config=dict(item.get("config") or {}),
                secrets=secrets,
                description=str(item.get("description") or ""),
            )
            self._connectors[connector.key] = connector
        return self._connectors

    def refresh(self) -> "ConnectorCatalog":
        self._connectors = None
        return self

    def list(self) -> List[Connector]:
        return list(self._load().values())

    def resolve(self, key: str, required: bool = True) -> Optional[Connector]:
        connector = self._load().get(key)
        if connector is None and required:
            available = ", ".join(sorted(self._load())) or "none"
            raise JobSeekerError("Connector %s is not available to this job. Available connector key(s): %s." % (key, available))
        return connector


def _connector_secret_values(item: Mapping[str, Any]) -> Dict[str, str]:
    secret = dict(item.get("secret") or {})
    backend = str(secret.get("backend") or "local")
    if backend == "local":
        return {str(name): str(value) for name, value in dict(secret.get("values") or {}).items()}
    reference = dict(secret.get("reference") or {})
    if backend == "environment":
        values = {}
        variables = dict(reference.get("variables") or {})
        if not variables:
            variables = {
                name: reference.get(name + "_env")
                for name in ("username", "password")
                if reference.get(name + "_env")
            }
        for name, variable_value in variables.items():
            variable = str(variable_value or "")
            value = os.environ.get(variable) if variable else None
            if value is None:
                raise JobSeekerError("Connector %s requires environment variable %s." % (item.get("key"), variable or "<missing>"))
            values[str(name)] = value
        return values
    if backend == "azure_key_vault":
        try:
            from azure.identity import (  # type: ignore
                DefaultAzureCredential,
                EnvironmentCredential,
                ManagedIdentityCredential,
                WorkloadIdentityCredential,
            )
            from azure.keyvault.secrets import SecretClient  # type: ignore
        except ImportError as error:
            raise JobSeekerDependencyError(
                "Azure Key Vault connectors require azure-identity and azure-keyvault-secrets on the Jenkins worker."
            ) from error
        vault_url = str(reference.get("vault_url") or "")
        client_id = str(reference.get("managed_identity_client_id") or "")
        auth_mode = str(reference.get("auth_mode") or "default")
        if auth_mode == "managed_identity":
            credential = ManagedIdentityCredential(client_id=client_id or None)
        elif auth_mode == "workload_identity":
            credential = WorkloadIdentityCredential(client_id=client_id or None)
        elif auth_mode == "environment":
            credential = EnvironmentCredential()
        else:
            credential_options = {"managed_identity_client_id": client_id} if client_id else {}
            credential = DefaultAzureCredential(**credential_options)
        client = SecretClient(vault_url=vault_url, credential=credential)
        try:
            secret_names = dict(reference.get("secrets") or {})
            if not secret_names:
                secret_names = {
                    name: reference.get(name + "_secret")
                    for name in ("username", "password")
                    if reference.get(name + "_secret")
                }
            return {str(name): str(client.get_secret(str(secret_name)).value) for name, secret_name in secret_names.items()}
        finally:
            close_client = getattr(client, "close", None)
            if callable(close_client):
                close_client()
            close_credential = getattr(credential, "close", None)
            if callable(close_credential):
                close_credential()
    if backend == "aws_secrets_manager":
        try:
            import boto3  # type: ignore
        except ImportError as error:
            raise JobSeekerDependencyError(
                "AWS Secrets Manager connectors require boto3 on the Jenkins worker."
            ) from error
        region = str(reference.get("region") or "")
        auth_mode = str(reference.get("auth_mode") or "default")
        if auth_mode == "profile":
            session = boto3.Session(profile_name=str(reference.get("profile_name") or ""), region_name=region)
            client = session.client("secretsmanager")
        else:
            client = boto3.client("secretsmanager", region_name=region)
        try:
            response = client.get_secret_value(SecretId=str(reference.get("secret_id") or ""))
            if response.get("SecretString") is not None:
                payload = str(response["SecretString"])
            else:
                payload = base64.b64decode(response.get("SecretBinary") or b"").decode("utf-8")
            values = json.loads(payload)
            if not isinstance(values, dict):
                raise ValueError("secret payload is not an object")
            fields = dict(reference.get("fields") or {})
            if not fields:
                fields = {
                    "username": str(reference.get("username_field") or "username"),
                    "password": str(reference.get("password_field") or "password"),
                }
            resolved = {}
            for name, field_path in fields.items():
                current: Any = values
                for segment in str(field_path).split("."):
                    if not isinstance(current, dict) or segment not in current:
                        raise ValueError("configured fields are missing")
                    current = current[segment]
                resolved[str(name)] = str(current)
            return resolved
        except (ValueError, TypeError, UnicodeError) as error:
            raise JobSeekerError("AWS secret for connector %s is not a valid credential object." % item.get("key")) from error
        finally:
            close_client = getattr(client, "close", None)
            if callable(close_client):
                close_client()
    raise JobSeekerError("Connector %s uses unsupported secret backend %s." % (item.get("key"), backend))


def materialize_connectors(
    directory: Optional[str] = None,
    environment: Optional[str] = None,
    job: Optional[str] = None,
    api_url: Optional[str] = None,
    api_token: Optional[str] = None,
) -> str:
    """Fetch the scoped catalog and write build-only secret files with mode 0600."""

    target_directory = os.path.abspath(directory or _env("JOBSEEKER_CONNECTORS_DIR", ".jobseeker-connectors"))
    endpoint = api_url or _env("JOBSEEKER_CONNECTOR_API_URL")
    token = api_token or _env("JOBSEEKER_CONNECTOR_API_TOKEN")
    target_environment = (environment or _env("JOBSEEKER_ENVIRONMENT", "LOCAL")).upper()
    target_job = job or _safe_job_name(None)
    if not endpoint or not token:
        raise JobSeekerError("Connector materialization requires JOBSEEKER_CONNECTOR_API_URL and JOBSEEKER_CONNECTOR_API_TOKEN.")

    request = urllib.request.Request(
        endpoint,
        data=urllib.parse.urlencode({"environment": target_environment, "job_name": target_job}).encode("utf-8"),
        headers={"Authorization": "Bearer " + token, "Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=15) as response:
            payload = json.loads(response.read().decode("utf-8"))
    except (urllib.error.URLError, ValueError) as error:
        raise JobSeekerError("Connector catalog request failed: %s" % error) from error

    if os.path.islink(target_directory):
        os.unlink(target_directory)
    elif os.path.lexists(target_directory):
        shutil.rmtree(target_directory)
    os.makedirs(target_directory, mode=0o700)
    os.chmod(target_directory, 0o700)
    manifest = {
        "schema_version": 1,
        "generated_at": payload.get("generated_at"),
        "environment": target_environment,
        "job": target_job,
        "connectors": [],
    }

    def write_value(path: str, value: Any, file_mode: int = 0o600) -> None:
        descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, file_mode)
        with os.fdopen(descriptor, "w", encoding="utf-8") as stream:
            stream.write(str(value))

    consumed_environment_variables = set()
    for item in payload.get("connectors", []):
        if not isinstance(item, dict) or not item.get("key"):
            continue
        key = str(item["key"])
        if not all(character.isalnum() or character in "-_" for character in key):
            raise JobSeekerError("Connector key contains unsafe characters: %s" % key)
        connector_directory = os.path.join(target_directory, key)
        os.makedirs(connector_directory, mode=0o700)
        os.chmod(connector_directory, 0o700)
        config = dict(item.get("config") or {})
        config["type"] = item.get("type", "generic")
        for name, value in config.items():
            normalized_name = str(name)
            if not normalized_name or not all(character.isalnum() or character in "-_" for character in normalized_name):
                raise JobSeekerError("Connector field contains unsafe characters: %s" % normalized_name)
            write_value(os.path.join(connector_directory, normalized_name), value)
        secret_definition = dict(item.get("secret") or {})
        secret_reference = dict(secret_definition.get("reference") or {})
        if secret_definition.get("backend") == "environment":
            source_variables = dict(secret_reference.get("variables") or {})
            if not source_variables:
                source_variables = {
                    name: secret_reference.get(name + "_env")
                    for name in ("username", "password")
                    if secret_reference.get(name + "_env")
                }
            for variable in source_variables.values():
                variable_name = str(variable or "")
                if not variable_name or not variable_name.replace("_", "a").isalnum() or variable_name[0].isdigit():
                    raise JobSeekerError("Connector environment variable name is invalid.")
                consumed_environment_variables.add(variable_name)
        secret_files = {}
        for name, value in _connector_secret_values(item).items():
            if not name or not all(character.isalnum() or character in "-_" for character in name):
                raise JobSeekerError("Connector secret field contains unsafe characters: %s" % name)
            secret_path = os.path.join(connector_directory, name)
            write_value(secret_path, value)
            secret_files[name] = key + "/" + name
        manifest["connectors"].append({
            "key": key,
            "type": item.get("type", "generic"),
            "environment": item.get("environment", "ALL"),
            "job": item.get("job", "*"),
            "description": item.get("description", ""),
            "config": dict(item.get("config") or {}),
            "secret_files": secret_files,
        })

    manifest_path = os.path.join(target_directory, "connectors.json")
    descriptor = os.open(manifest_path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, "w", encoding="utf-8") as stream:
        json.dump(manifest, stream, indent=2, sort_keys=True)
        stream.write("\n")

        source_variables_path = os.path.join(target_directory, ".source-environment-variables")
        write_value(source_variables_path, "\n".join(sorted(consumed_environment_variables)) + ("\n" if consumed_environment_variables else ""))

        helper_path = os.path.join(target_directory, "jobseeker-connector")
        helper = """#!/bin/sh
set -eu
root=${JOBSEEKER_CONNECTORS_DIR:-$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)}
command_name=${1:-}
case "$command_name" in
    list)
        for directory in "$root"/*; do [ -d "$directory" ] && basename "$directory"; done
        ;;
    get)
        key=${2:-}; field=${3:-}
        case "$key" in ''|*[!A-Za-z0-9_-]*) echo "Invalid connector key." >&2; exit 2;; esac
        case "$field" in ''|*[!A-Za-z0-9_-]*) echo "Invalid connector field." >&2; exit 2;; esac
        [ -f "$root/$key/$field" ] || { echo "Connector field not found: $key/$field" >&2; exit 2; }
        cat "$root/$key/$field"
        ;;
    exec)
        key=${2:-}; shift 2
        [ "${1:-}" = "--" ] && shift
        case "$key" in ''|*[!A-Za-z0-9_-]*) echo "Invalid connector key." >&2; exit 2;; esac
        [ -d "$root/$key" ] || { echo "Connector not found: $key" >&2; exit 2; }
        [ "$#" -gt 0 ] || { echo "Connector exec requires a command after --." >&2; exit 2; }
        export JOBSEEKER_CONNECTOR_KEY="$key"
        for value_file in "$root/$key"/*; do
            [ -f "$value_file" ] || continue
            field=$(basename "$value_file" | tr '[:lower:]-' '[:upper:]_')
            value=$(cat "$value_file")
            export "JOBSEEKER_CONNECTOR_${field}=$value"
        done
        exec "$@"
        ;;
    *)
        echo "Usage: jobseeker-connector {list|get KEY FIELD|exec KEY -- COMMAND...}" >&2
        exit 2
        ;;
esac
"""
        write_value(helper_path, helper, 0o700)
    return manifest_path


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
        self._connector_catalog: Optional[ConnectorCatalog] = None

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

    @property
    def connectors(self) -> ConnectorCatalog:
        if self._connector_catalog is None:
            self._connector_catalog = ConnectorCatalog()
        return self._connector_catalog

    def connector(self, key: str, required: bool = True) -> Optional[Connector]:
        """Resolve a named connector materialized for this job's runtime scope."""

        return self.connectors.resolve(key, required=required)

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

    def connector(self, key: str, required: bool = True) -> Optional[Connector]:
        return self.client.connector(key, required=required)

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


def get_connector(
    key: str,
    required: bool = True,
    directory: Optional[str] = None,
) -> Optional[Connector]:
    return ConnectorCatalog(directory=directory).resolve(key, required=required)


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


def connector_cli() -> None:
    """Materialize or consume build-scoped connectors without logging secrets."""

    import argparse

    parser = argparse.ArgumentParser(description="Use JobSeeker runtime connectors.")
    commands = parser.add_subparsers(dest="command", required=True)
    materialize_parser = commands.add_parser("materialize", help="Fetch and materialize connectors for this build")
    materialize_parser.add_argument("--directory", default=None)
    materialize_parser.add_argument("--environment", default=None)
    materialize_parser.add_argument("--job", default=None)

    list_parser = commands.add_parser("list", help="List available connector metadata")
    list_parser.add_argument("--directory", default=None)

    get_parser = commands.add_parser("get", help="Print one connector field")
    get_parser.add_argument("key")
    get_parser.add_argument("field")
    get_parser.add_argument("--directory", default=None)

    exec_parser = commands.add_parser("exec", help="Run a command with one connector in environment variables")
    exec_parser.add_argument("key")
    exec_parser.add_argument("--directory", default=None)
    exec_parser.add_argument("program", nargs=argparse.REMAINDER)

    arguments = parser.parse_args()
    try:
        if arguments.command == "materialize":
            print(materialize_connectors(directory=arguments.directory, environment=arguments.environment, job=arguments.job))
            return

        catalog = ConnectorCatalog(directory=arguments.directory)
        if arguments.command == "list":
            print(json.dumps([item.as_dict() for item in catalog.list()], indent=2, sort_keys=True))
            return

        connector = catalog.resolve(arguments.key)
        if connector is None:
            raise JobSeekerError("Connector was not found: %s" % arguments.key)
        if arguments.command == "get":
            print(connector.value(arguments.field, required=True))
            return

        program = list(arguments.program)
        if program and program[0] == "--":
            program.pop(0)
        if not program:
            raise JobSeekerError("connector exec requires a command after --.")
        environment_values = dict(os.environ)
        environment_values.update(connector.environment_variables())
        os.execvpe(program[0], program, environment_values)
    except JobSeekerError as error:
        parser.exit(2, "jobseeker-connector: %s\n" % error)


__all__ = [
    "ApiConfig",
    "ApiTransport",
    "Connector",
    "ConnectorCatalog",
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
    "get_connector",
    "get_context",
    "jobSeeker",
    "materialize_connectors",
    "task",
]
