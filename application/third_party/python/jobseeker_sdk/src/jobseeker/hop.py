"""Apache Hop execution for JobSeeker jobs.

Jenkins stays the scheduler and trigger; this module is the executor it calls.
One command line runs an Apache Hop workflow or pipeline through either of two
interchangeable engines, after wiring the project into the rest of the platform:

* JobSeeker **connectors** become Hop relational database connections, named
  after the connector key, with the credentials kept out of the project files.
* JobSeeker **Data Assets** become Hop variables that already point at the path
  the engine will actually see.
* **Context** values and job parameters become Hop variables.
* The run is wrapped in a **TMF** instance, and Hop's own row counters are read
  back out of the execution log, so a Hop job shows up in Transaction
  Monitoring next to Python jobs without the author doing anything.

The contracts used here (container entry point variables, the environment
config file shape, the ``metadata/rdbms`` shape, and the Hop Server servlets)
are documented in ``doc/jobseeker/Hop/architecture.md`` and asserted by
``scripts/test-hop-integration.js``.
"""

from __future__ import annotations

import base64
import fcntl
import gzip
import io
import json
import os
import re
import shutil
import subprocess  # noqa: S404 - the engines are process launchers by design
import sys
import tarfile
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from typing import Any, Dict, Iterable, List, Mapping, Optional, Sequence, Tuple

from jobseeker import (
    ConnectorCatalog,
    DataAssetCatalog,
    JobSeeker,
    JobSeekerError,
    materialize_connectors,
)

__all__ = [
    "HopError",
    "HopManifest",
    "HopProject",
    "HopRunResult",
    "ContainerEngine",
    "ServerEngine",
    "build_run_variables",
    "decode_hop_log",
    "extract_hop_errors",
    "parse_hop_counters",
    "rdbms_metadata",
    "run",
    "main",
]

SCHEMA_VERSION = 1
MANIFEST_NAME = ".jobseeker-hop.json"
PROJECT_CONFIG_NAME = "project-config.json"

HOP_VERSION = "2.19.0"
# The public Apache Hop image is the default so the Docker daemon Jenkins talks
# to can pull it with no registry of our own. JOBSEEKER_HOP_IMAGE switches a job
# to the richer jobseeker-hop image when a workflow shells out to the SDK.
DEFAULT_IMAGE = "apache/hop:" + HOP_VERSION
DEFAULT_RUN_CONFIG = "local"
DEFAULT_LOG_LEVEL = "Basic"
DEFAULT_ENGINE = "container"

ENGINES = ("container", "server")
LOG_LEVELS = ("Nothing", "Error", "Minimal", "Basic", "Detailed", "Debug", "Rowlevel")

PIPELINE_EXTENSION = ".hpl"
WORKFLOW_EXTENSION = ".hwf"

# Paths the engines expose inside a Hop container. They are fixed so a job's
# generated Jenkins command stays stable and readable.
CONTAINER_PROJECT_PATH = "/files/project"
CONTAINER_RUN_PATH = "/files/run"
CONTAINER_REPOSITORY_PATH = "/jobseeker-repository"


class HopError(JobSeekerError):
    """An Apache Hop project, configuration, or execution problem."""


# ---------------------------------------------------------------------------
# Small helpers
# ---------------------------------------------------------------------------


def _env(name: str, default: str = "") -> str:
    value = os.environ.get(name)
    return default if value is None or value == "" else str(value)


def _runtime_environment(value: str = "") -> str:
    """One normalized environment scope for every run-time integration."""

    requested = str(value or "").strip()
    if not requested:
        requested = _env("JOBSEEKER_ENVIRONMENT", "LOCAL").strip()
    return (requested or "LOCAL").upper()


def _variable_name(prefix: str, key: str) -> str:
    normalized = "".join(character if character.isalnum() else "_" for character in str(key)).upper()
    normalized = re.sub(r"_+", "_", normalized).strip("_")
    return "%s_%s" % (prefix, normalized) if normalized else prefix


def _relative_posix(path: str, base: str) -> Optional[str]:
    """Return ``path`` relative to ``base`` in POSIX form, or None if outside."""

    try:
        resolved_base = os.path.realpath(base)
        resolved_path = os.path.realpath(path)
        if os.path.commonpath((resolved_base, resolved_path)) != resolved_base:
            return None
    except (OSError, ValueError):
        return None
    relative = os.path.relpath(resolved_path, resolved_base)
    return "." if relative == "." else relative.replace(os.sep, "/")


def _write_json(path: str, payload: Any, mode: int = 0o600) -> str:
    directory = os.path.dirname(os.path.abspath(path))
    os.makedirs(directory, exist_ok=True)
    descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, mode)
    with os.fdopen(descriptor, "w", encoding="utf-8") as stream:
        json.dump(payload, stream, indent=2, sort_keys=True)
        stream.write("\n")
    os.chmod(path, mode)
    return os.path.abspath(path)


def _read_json(path: str) -> Optional[Any]:
    try:
        with open(path, "r", encoding="utf-8") as stream:
            return json.load(stream)
    except FileNotFoundError:
        return None
    except (OSError, ValueError) as error:
        raise HopError("%s is not readable JSON: %s" % (path, error)) from error


def _docker_safe_name(*parts: str) -> str:
    raw = "-".join(str(part) for part in parts if str(part))
    cleaned = re.sub(r"[^a-zA-Z0-9_.-]+", "-", raw).strip("-.").lower()
    return (cleaned or "jobseeker-hop")[:120]


# ---------------------------------------------------------------------------
# Project model
# ---------------------------------------------------------------------------


class HopManifest:
    """The JobSeeker-owned side of a Hop project (``.jobseeker-hop.json``).

    Apache Hop owns ``project-config.json``; JobSeeker never writes to it beyond
    creating a default when scaffolding, so a project exported from Hop GUI can
    be dropped in unchanged.
    """

    def __init__(self, values: Optional[Mapping[str, Any]] = None):
        values = dict(values or {})
        self.schema_version = int(values.get("schema_version") or SCHEMA_VERSION)
        self.project = str(values.get("project") or "")
        self.description = str(values.get("description") or "")
        self.entry_file = str(values.get("entry_file") or "")
        self.run_config = str(values.get("run_config") or DEFAULT_RUN_CONFIG)
        self.engine = str(values.get("engine") or DEFAULT_ENGINE)
        self.log_level = str(values.get("log_level") or DEFAULT_LOG_LEVEL)
        self.parameters: Dict[str, str] = {
            str(name): str(value) for name, value in dict(values.get("parameters") or {}).items()
        }
        self.connectors: List[str] = [str(item) for item in list(values.get("connectors") or [])]
        self.assets: List[str] = [str(item) for item in list(values.get("assets") or [])]
        self.context: List[str] = [str(item) for item in list(values.get("context") or [])]

    def as_dict(self) -> Dict[str, Any]:
        return {
            "schema_version": self.schema_version,
            "project": self.project,
            "description": self.description,
            "entry_file": self.entry_file,
            "run_config": self.run_config,
            "engine": self.engine,
            "log_level": self.log_level,
            "parameters": dict(self.parameters),
            "connectors": list(self.connectors),
            "assets": list(self.assets),
            "context": list(self.context),
        }


class HopProject:
    """A folder on disk that Apache Hop recognises as a project."""

    def __init__(self, root: str):
        self.root = os.path.abspath(root)
        if not os.path.isdir(self.root):
            raise HopError("Hop project folder does not exist: %s" % self.root)
        self.name = os.path.basename(self.root.rstrip(os.sep))

    # -- discovery ---------------------------------------------------------

    @classmethod
    def locate(cls, path: str) -> "HopProject":
        """Accept a project folder, a nested folder, or a file inside one.

        Uploaded archives routinely contain a single top-level directory, so the
        project marker is searched upwards from the given path and then one level
        down, which is what makes "just upload the zip you exported" work.
        """

        candidate = os.path.abspath(path)
        if os.path.isfile(candidate):
            candidate = os.path.dirname(candidate)
        if not os.path.isdir(candidate):
            raise HopError("Hop project path does not exist: %s" % path)

        probe = candidate
        # Entry files are often passed several folders below the project root.
        # Walk upward to the filesystem boundary; the prior relative-path guard
        # stopped after one parent and missed perfectly valid nested files.
        while True:
            if os.path.isfile(os.path.join(probe, PROJECT_CONFIG_NAME)):
                return cls(probe)
            parent = os.path.dirname(probe)
            if parent == probe:
                break
            probe = parent

        for entry in sorted(os.listdir(candidate)):
            nested = os.path.join(candidate, entry)
            if os.path.isdir(nested) and os.path.isfile(os.path.join(nested, PROJECT_CONFIG_NAME)):
                return cls(nested)

        # A folder holding loose .hpl/.hwf files is still usable: scaffold the
        # descriptor Hop needs rather than rejecting the upload.
        if cls(candidate).entry_files():
            project = cls(candidate)
            project.ensure_project_config()
            return project

        raise HopError(
            "No Apache Hop project was found under %s. Expected a %s file, or at least one %s / %s file."
            % (path, PROJECT_CONFIG_NAME, WORKFLOW_EXTENSION, PIPELINE_EXTENSION)
        )

    def _collect(self, extension: str) -> List[str]:
        results: List[str] = []
        skip = {".git", "metadata", "datasets", "audit", "node_modules", "__pycache__"}
        for directory, folders, files in os.walk(self.root):
            folders[:] = sorted(name for name in folders if name not in skip and not name.startswith("."))
            for name in sorted(files):
                if name.lower().endswith(extension):
                    relative = _relative_posix(os.path.join(directory, name), self.root)
                    if relative:
                        results.append(relative)
        return sorted(results)

    def pipelines(self) -> List[str]:
        return self._collect(PIPELINE_EXTENSION)

    def workflows(self) -> List[str]:
        return self._collect(WORKFLOW_EXTENSION)

    def entry_files(self) -> List[str]:
        return self.workflows() + self.pipelines()

    def referenced_variables(self) -> List[str]:
        """Return the Hop variables explicitly referenced by this project.

        Context Details used to require a second declaration in the JobSeeker
        manifest. That was easy to miss and left ``${NAME}`` untouched at run
        time even though NAME existed for the selected environment. The Hop
        files are already the authoritative declaration of what a run needs, so
        discover those references directly and resolve only that small set.
        """

        names = set()
        pattern = re.compile(r"\$\{([A-Za-z_][A-Za-z0-9_.-]{0,199})\}")
        for relative in self.entry_files():
            path = os.path.join(self.root, relative.replace("/", os.sep))
            try:
                # Hop files are XML and normally tiny. Cap the read so an
                # uploaded project cannot turn variable discovery into a large
                # allocation before the engine starts.
                with open(path, "r", encoding="utf-8", errors="replace") as stream:
                    content = stream.read(4 * 1024 * 1024)
            except OSError:
                continue
            names.update(pattern.findall(content))
        return sorted(names)

    def resolve_entry(self, entry_file: str) -> str:
        """Return the project-relative POSIX path of a runnable file."""

        entry_file = str(entry_file or "").strip().replace("\\", "/").lstrip("/")
        if not entry_file:
            manifest_entry = self.manifest().entry_file
            if manifest_entry:
                return self.resolve_entry(manifest_entry)
            available = self.entry_files()
            if len(available) == 1:
                return available[0]
            raise HopError(
                "This Hop project has %d runnable files, so --file is required. Available: %s"
                % (len(available), ", ".join(available) or "none")
            )

        absolute = os.path.join(self.root, entry_file.replace("/", os.sep))
        relative = _relative_posix(absolute, self.root)
        if relative is None:
            raise HopError("The entry file escapes the Hop project folder: %s" % entry_file)
        if not os.path.isfile(absolute):
            matches = [item for item in self.entry_files() if os.path.basename(item) == os.path.basename(entry_file)]
            if len(matches) == 1:
                return matches[0]
            raise HopError(
                "Entry file %s was not found in the Hop project. Available: %s"
                % (entry_file, ", ".join(self.entry_files()) or "none")
            )
        if not relative.lower().endswith((PIPELINE_EXTENSION, WORKFLOW_EXTENSION)):
            raise HopError("A Hop entry file must be a %s or %s file." % (WORKFLOW_EXTENSION, PIPELINE_EXTENSION))
        return relative

    # -- manifests ---------------------------------------------------------

    def manifest_path(self) -> str:
        return os.path.join(self.root, MANIFEST_NAME)

    def manifest(self) -> HopManifest:
        values = _read_json(self.manifest_path()) or {}
        if not isinstance(values, dict):
            values = {}
        values.setdefault("project", self.name)
        return HopManifest(values)

    def save_manifest(self, manifest: HopManifest) -> str:
        manifest.project = manifest.project or self.name
        return _write_json(self.manifest_path(), manifest.as_dict(), mode=0o644)

    def ensure_project_config(self) -> str:
        """Create Hop's project descriptor when a bare upload did not carry one."""

        path = os.path.join(self.root, PROJECT_CONFIG_NAME)
        if os.path.isfile(path):
            return path
        return _write_json(
            path,
            {
                "metadataBaseFolder": "${PROJECT_HOME}/metadata",
                "unitTestsBasePath": "${PROJECT_HOME}",
                "dataSetsCsvFolder": "${PROJECT_HOME}/datasets",
                "enforcingExecutionInHome": True,
                "config": {"variables": []},
            },
            mode=0o644,
        )

    def ensure_run_configurations(self) -> List[str]:
        """Guarantee the ``local`` pipeline and workflow run configurations exist.

        Hop refuses to start without one, and an uploaded project frequently has
        the run configuration only in the author's local Hop installation.
        """

        written: List[str] = []
        pipeline_config = os.path.join(self.root, "metadata", "pipeline-run-configuration", "local.json")
        if not os.path.isfile(pipeline_config):
            written.append(
                _write_json(
                    pipeline_config,
                    {
                        "name": DEFAULT_RUN_CONFIG,
                        "description": "JobSeeker default local pipeline engine",
                        "defaultSelection": True,
                        "configurationVariables": [],
                        "engineRunConfiguration": {
                            "Local": {
                                "feedback_size": "50000",
                                "sample_size": "100",
                                "sample_type_in_gui": "Last",
                                "rowset_size": "10000",
                                "safe_mode": False,
                                "show_feedback": False,
                                "topo_sort": False,
                                "gather_metrics": False,
                            }
                        },
                    },
                    mode=0o644,
                )
            )

        workflow_config = os.path.join(self.root, "metadata", "workflow-run-configuration", "local.json")
        if not os.path.isfile(workflow_config):
            written.append(
                _write_json(
                    workflow_config,
                    {
                        "name": DEFAULT_RUN_CONFIG,
                        "description": "JobSeeker default local workflow engine",
                        "defaultSelection": True,
                        "configurationVariables": [],
                        "engineRunConfiguration": {"Local": {"safe_mode": False}},
                    },
                    mode=0o644,
                )
            )
        return written

    def describe(self) -> Dict[str, Any]:
        manifest = self.manifest()
        return {
            "name": self.name,
            "root": self.root,
            "has_project_config": os.path.isfile(os.path.join(self.root, PROJECT_CONFIG_NAME)),
            "workflows": self.workflows(),
            "pipelines": self.pipelines(),
            "manifest": manifest.as_dict(),
        }


def scaffold_project(root: str, name: str = "") -> HopProject:
    """Create the folder skeleton Hop and JobSeeker both expect."""

    root = os.path.abspath(root)
    for folder in ("", "metadata/rdbms", "metadata/pipeline-run-configuration", "metadata/workflow-run-configuration", "pipelines", "workflows"):
        os.makedirs(os.path.join(root, folder.replace("/", os.sep)) if folder else root, exist_ok=True)
    project = HopProject(root)
    project.ensure_project_config()
    project.ensure_run_configurations()
    manifest = project.manifest()
    manifest.project = name or project.name
    project.save_manifest(manifest)
    return project


# ---------------------------------------------------------------------------
# Connector -> Hop relational database metadata
# ---------------------------------------------------------------------------

# JobSeeker connector type -> (Hop plugin id, Hop plugin name, default port,
# extra database attributes). Anything not listed here is still usable through
# Hop's GENERIC driver as long as the connector carries a JDBC URL.
HOP_DATABASE_PLUGINS: Dict[str, Tuple[str, str, int, Dict[str, str]]] = {
    "mysql": (
        "MYSQL",
        "MySQL",
        3306,
        {
            "EXTRA_OPTION_MYSQL.useCursorFetch": "true",
            "EXTRA_OPTION_MYSQL.defaultFetchSize": "500",
            "EXTRA_OPTION_MYSQL.useSSL": "false",
            "EXTRA_OPTION_MYSQL.allowPublicKeyRetrieval": "true",
            "EXTRA_OPTION_MYSQL.zeroDateTimeBehaviorValue": "CONVERT_TO_NULL",
        },
    ),
    "mariadb": ("MARIADB", "MariaDB", 3306, {}),
    "pgsql": ("POSTGRESQL", "PostgreSQL", 5432, {}),
    "postgresql": ("POSTGRESQL", "PostgreSQL", 5432, {}),
    "sqlserver": ("MSSQLNATIVE", "MS SQL Server (Native)", 1433, {"EXTRA_OPTION_MSSQLNATIVE.encrypt": "false"}),
    "oracle_service": ("ORACLE", "Oracle", 1521, {}),
    "oracle_sid": ("ORACLE", "Oracle", 1521, {}),
}

RELATIONAL_TYPES = frozenset(HOP_DATABASE_PLUGINS)

# The official apache/hop image bundles only the permissively licensed JDBC
# drivers, so PostgreSQL and MS SQL Server work out of the box while MySQL,
# MariaDB and Oracle do not. Hop 2.19 can install those on container start
# ("hop driver install"), which is what makes a connector-backed job run on the
# stock image with no private registry. Bake them into docker/hop_image and
# point JOBSEEKER_HOP_IMAGE at it to skip the per-build download.
HOP_MISSING_DRIVERS: Dict[str, str] = {
    "mysql": "mysql",
    "mariadb": "mariadb",
    "oracle_service": "oracle",
    "oracle_sid": "oracle",
}


def required_hop_drivers(connectors: Iterable[Any]) -> List[str]:
    """JDBC driver ids the stock Apache Hop image would otherwise be missing."""

    drivers = {
        HOP_MISSING_DRIVERS[str(getattr(connector, "type", "")).lower()]
        for connector in connectors
        if str(getattr(connector, "type", "")).lower() in HOP_MISSING_DRIVERS
    }
    return sorted(drivers)


def rdbms_metadata(connector: Any, use_variables: bool = True) -> Optional[Dict[str, Any]]:
    """Build the ``metadata/rdbms/<key>.json`` document for one connector.

    With ``use_variables`` (the default) the document holds ``${VARIABLE}``
    references and the values travel separately in a 0600 environment config
    file, so a credential never reaches a project folder, a Git checkout, or the
    OpenVSCode workspace.
    """

    connector_type = str(getattr(connector, "type", "") or "").lower()
    plugin = HOP_DATABASE_PLUGINS.get(connector_type)
    if plugin is None:
        return None

    plugin_id, plugin_name, default_port, attributes = plugin
    prefix = _variable_name("JOBSEEKER_CONN", connector.key)
    config = dict(getattr(connector, "config", {}) or {})

    port = str(getattr(connector, "port", 0) or config.get("port") or default_port)
    database = str(getattr(connector, "database", "") or config.get("database") or "")
    if connector_type == "oracle_service" and config.get("oracle_service_name"):
        database = str(config.get("oracle_service_name"))
    if connector_type == "oracle_sid" and config.get("oracle_sid"):
        database = str(config.get("oracle_sid"))

    if use_variables:
        hostname = "${%s_HOST}" % prefix
        port_value = "${%s_PORT}" % prefix
        database_name = "${%s_DATABASE}" % prefix
        username = "${%s_USER}" % prefix
        password = "${%s_PASSWORD}" % prefix
    else:
        hostname = str(getattr(connector, "host", "") or config.get("host") or "")
        port_value = port
        database_name = database
        username = str(getattr(connector, "username", "") or "")
        password = str(getattr(connector, "password", "") or "")

    document_attributes = {
        "SUPPORTS_BOOLEAN_DATA_TYPE": "Y",
        "SUPPORTS_TIMESTAMP_DATA_TYPE": "Y",
        "PRESERVE_RESERVED_WORD_CASE": "Y",
        "FORCE_IDENTIFIERS_TO_LOWERCASE": "N",
        "FORCE_IDENTIFIERS_TO_UPPERCASE": "N",
        "QUOTE_ALL_FIELDS": "N",
        "SQL_CONNECT": "",
        "PREFERRED_SCHEMA_NAME": "",
    }
    document_attributes.update(attributes)

    additional = str(config.get("additional_parameters") or "").strip()
    for parameter in additional.replace("&", ";").split(";"):
        if "=" not in parameter:
            continue
        name, _, value = parameter.partition("=")
        name = name.strip()
        if name:
            document_attributes["EXTRA_OPTION_%s.%s" % (plugin_id, name)] = value.strip()

    return {
        "name": str(connector.key),
        "virtualPath": "",
        "rdbms": {
            plugin_id: {
                "pluginId": plugin_id,
                "pluginName": plugin_name,
                "accessType": 0,
                "hostname": hostname,
                "port": port_value,
                "databaseName": database_name,
                "username": username,
                "password": password,
                "manualUrl": "",
                "attributes": document_attributes,
            }
        },
    }


def write_rdbms_metadata(
    directory: str,
    connectors: Iterable[Any],
    use_variables: bool = True,
    replace: bool = False,
) -> List[str]:
    """Write one Hop database document per relational connector."""

    target = os.path.join(os.path.abspath(directory), "rdbms")
    os.makedirs(target, exist_ok=True)
    if replace:
        for name in os.listdir(target):
            path = os.path.join(target, name)
            if name.endswith(".json") and os.path.isfile(path) and not os.path.islink(path):
                os.unlink(path)
    written: List[str] = []
    for connector in connectors:
        document = rdbms_metadata(connector, use_variables=use_variables)
        if document is None:
            continue
        # A connector key is validated by the connector runtime before it ever
        # reaches here, but keep the file name defensive anyway.
        file_name = re.sub(r"[^A-Za-z0-9_.-]+", "-", str(connector.key)) + ".json"
        written.append(_write_json(os.path.join(target, file_name), document, mode=0o600 if not use_variables else 0o644))
    return written


# ---------------------------------------------------------------------------
# Run variables
# ---------------------------------------------------------------------------


def _described(name: str, value: Any, description: str) -> Dict[str, str]:
    return {"name": str(name), "value": "" if value is None else str(value), "description": description}


def build_run_variables(
    environment: str,
    job: str,
    instance_id: str = "",
    build_number: str = "",
    connectors: Optional[Sequence[Any]] = None,
    assets: Optional[Sequence[Any]] = None,
    parameters: Optional[Mapping[str, str]] = None,
    context: Optional[Mapping[str, str]] = None,
    path_translator: Optional[Any] = None,
) -> List[Dict[str, str]]:
    """Assemble every Hop variable a JobSeeker run should expose.

    ``path_translator`` rewrites a path on the machine running this process into
    the path the Hop engine will see, so a pipeline reads the same
    ``${JOBSEEKER_ASSET_X}`` whether it runs in a container or on the Hop Server.
    """

    translate = path_translator or (lambda value: value)
    platform_variables: List[Dict[str, str]] = [
        _described("JOBSEEKER_ENVIRONMENT", environment, "JobSeeker runtime environment"),
        _described("JOBSEEKER_JOB_NAME", job, "Jenkins job that triggered this run"),
    ]
    if instance_id:
        platform_variables.append(_described("JOBSEEKER_TMF_INSTANCE_ID", instance_id, "Transaction Monitoring instance id"))
    if build_number:
        platform_variables.append(_described("JOBSEEKER_BUILD_NUMBER", build_number, "Jenkins build number"))

    variables: List[Dict[str, str]] = []
    for name, value in dict(context or {}).items():
        variables.append(_described(name, value, "JobSeeker Context value"))
    for name, value in dict(parameters or {}).items():
        variables.append(_described(name, value, "Job parameter"))

    for asset in assets or []:
        try:
            path = translate(asset.path)
        except JobSeekerError:
            continue
        variables.append(
            _described(
                _variable_name("JOBSEEKER_ASSET", asset.key),
                path,
                "Data Asset %s (%s, %s)" % (asset.key, asset.direction, asset.format),
            )
        )

    for connector in connectors or []:
        prefix = _variable_name("JOBSEEKER_CONN", connector.key)
        config = dict(getattr(connector, "config", {}) or {})
        database = str(getattr(connector, "database", "") or config.get("database") or "")
        if str(getattr(connector, "type", "")).lower() == "oracle_service" and config.get("oracle_service_name"):
            database = str(config.get("oracle_service_name"))
        if str(getattr(connector, "type", "")).lower() == "oracle_sid" and config.get("oracle_sid"):
            database = str(config.get("oracle_sid"))
        variables.extend(
            (
                _described(prefix + "_HOST", getattr(connector, "host", ""), "Connector %s host" % connector.key),
                _described(prefix + "_PORT", getattr(connector, "port", ""), "Connector %s port" % connector.key),
                _described(prefix + "_DATABASE", database, "Connector %s database" % connector.key),
                _described(prefix + "_USER", getattr(connector, "username", ""), "Connector %s user" % connector.key),
                _described(prefix + "_PASSWORD", getattr(connector, "password", ""), "Connector %s password" % connector.key),
            )
        )

    # Platform identity and resolved resources are authoritative. In
    # particular, a Context row or generic caller cannot turn a DEV build into
    # PROD by replacing JOBSEEKER_ENVIRONMENT after scope resolution.
    variables.extend(platform_variables)

    # Later definitions win, matching how Hop merges environment config files.
    unique: Dict[str, Dict[str, str]] = {}
    for variable in variables:
        unique[variable["name"]] = variable
    return [unique[name] for name in sorted(unique)]


def write_environment_file(path: str, variables: Sequence[Mapping[str, str]]) -> str:
    """Write a Hop environment config file (``{"variables": [...]}``), mode 0600."""

    return _write_json(path, {"variables": [dict(variable) for variable in variables]}, mode=0o600)


def secret_variable_names(variables: Sequence[Mapping[str, str]]) -> List[str]:
    return [str(variable.get("name")) for variable in variables if str(variable.get("name", "")).endswith("_PASSWORD")]


def redact(text: str, variables: Sequence[Mapping[str, str]]) -> str:
    """Blank out any credential that a Hop stack trace may have echoed."""

    for variable in variables:
        value = str(variable.get("value") or "")
        if len(value) >= 4 and str(variable.get("name", "")).endswith("_PASSWORD"):
            text = text.replace(value, "********")
    return text


# ---------------------------------------------------------------------------
# Hop output parsing
# ---------------------------------------------------------------------------

# Hop closes each transform with a metrics line such as
#   Transform.0 - Finished processing (I=0, O=0, R=10, W=10, U=0, E=0)
_TRANSFORM_METRICS = re.compile(
    r"\(\s*I\s*=\s*(\d+)\s*,\s*O\s*=\s*(\d+)\s*,\s*R\s*=\s*(\d+)\s*,\s*W\s*=\s*(\d+)\s*,\s*U\s*=\s*(\d+)\s*,\s*E\s*=\s*(\d+)\s*\)"
)
# Workflows end with: Result: nr=0, errors=0, exit_status=0, result=true
_WORKFLOW_RESULT = re.compile(r"errors\s*=\s*(\d+)", re.IGNORECASE)
# "Copy files action finished with 1 error(s)." and "finished with 2 errors"
_ACTION_ERROR_COUNT = re.compile(r"finished with (\d+) error", re.IGNORECASE)
# Hop log lines are "<timestamp> - <origin> - ERROR: <message>". Splitting on the
# separator rather than matching the whole line keeps origins that contain
# punctuation, such as "read customers.0", intact.
_LOG_SEPARATOR = " - "
_ERROR_PREFIXES = ("ERROR", "FATAL")


def decode_hop_log(raw: str) -> str:
    """Decode a Hop status ``<logging_string>``: base64 of a gzip stream.

    Both pipeline and workflow status documents carry the complete run log this
    way. The per-transform ``<log_text>`` sections are only a subset of it, and
    a workflow status has none at all - which is why a failed workflow used to
    reach Transaction Monitoring with counters but no error text.
    """

    text = _unescape_xml(str(raw or "")).strip()
    if text.startswith("<![CDATA[") and text.endswith("]]>"):
        text = text[len("<![CDATA["):-len("]]>")].strip()
    if not text:
        return ""

    try:
        payload = base64.b64decode(text)
    except Exception:  # noqa: BLE001 - an undecodable log must never fail a run
        return text
    if payload[:2] != b"\x1f\x8b":
        return text
    try:
        return gzip.decompress(payload).decode("utf-8", "replace")
    except Exception:  # noqa: BLE001
        return ""


def extract_hop_errors(output: str, limit: int = 25) -> List[Dict[str, str]]:
    """Pull the real error lines, with their transform or action, out of a log.

    Hop reports what went wrong on ordinary log lines; the exit code alone says
    only that something did. These are what Transaction Monitoring shows the
    person who has to fix the job, so they are extracted here rather than left
    in the Jenkins console.
    """

    errors: List[Dict[str, str]] = []
    seen = set()
    for raw_line in str(output or "").splitlines():
        line = raw_line.strip()
        if not line:
            continue

        parts = line.split(_LOG_SEPARATOR)
        index = next(
            (position for position, part in enumerate(parts) if part.lstrip().upper().startswith(_ERROR_PREFIXES)),
            -1,
        )
        if index < 0:
            continue

        message = _LOG_SEPARATOR.join(parts[index:]).strip()
        origin = parts[index - 1].strip() if index > 0 else ""
        # The first field is the timestamp, which is noise in a TMF message.
        if index == 1 and re.match(r"^\d{4}/\d{2}/\d{2}\b", origin):
            origin = ""

        key = (origin, message)
        if key in seen:
            continue
        seen.add(key)
        errors.append({"origin": origin, "message": message})
        if len(errors) >= limit:
            break

    return errors


def parse_hop_counters(output: str) -> Dict[str, int]:
    """Derive TMF record counts from a Hop execution log.

    ``records_processed`` is the largest number of rows any transform wrote,
    which is the output row count for the overwhelmingly common single-sink
    pipeline. ``records_total`` is the largest number of rows any transform read
    or took in, falling back to the written count for a generator-only pipeline.
    """

    read = written = errors = 0
    matched = False
    for match in _TRANSFORM_METRICS.finditer(output or ""):
        matched = True
        inputs, _outputs, rows_read, rows_written, _updated, row_errors = (int(value) for value in match.groups())
        read = max(read, inputs, rows_read)
        written = max(written, rows_written)
        errors += row_errors

    if not matched:
        for match in _WORKFLOW_RESULT.finditer(output or ""):
            errors = max(errors, int(match.group(1)))
        # A workflow whose action failed reports "... finished with 1 error(s)."
        # and nothing else countable, so without this a failing workflow would
        # be recorded as a clean run whenever its engine still exited zero.
        for match in _ACTION_ERROR_COUNT.finditer(output or ""):
            errors = max(errors, int(match.group(1)))

    return {
        "records_total": read or written,
        "records_processed": written,
        "errors": errors,
        "has_metrics": 1 if matched else 0,
    }


# ---------------------------------------------------------------------------
# Engines
# ---------------------------------------------------------------------------


class HopRunResult:
    """One engine execution: its exit code, its log, and what Hop counted.

    ``counters`` is set by an engine that can read Hop's own structured result -
    the Hop Server status document, for instance - and then wins over parsing
    the log, which is the fallback for engines that only produce console output.
    """

    def __init__(
        self,
        exit_code: int,
        output: str,
        engine: str,
        detail: str = "",
        counters: Optional[Mapping[str, int]] = None,
    ):
        self.exit_code = int(exit_code)
        self.output = output
        self.engine = engine
        self.detail = detail
        self.counters = dict(counters) if counters else None

    @property
    def ok(self) -> bool:
        return self.exit_code == 0


class HopEngine:
    """Start Hop for one file and collect its exit code and log.

    Everything above this boundary — the project layout, the generated metadata,
    the variables, and the TMF envelope — is engine independent, which is what
    lets a Kubernetes engine be added later without touching the rest.
    """

    name = "base"

    def __init__(self, project: HopProject, run_directory: str, repository_root: str, options: Mapping[str, Any]):
        self.project = project
        self.run_directory = os.path.abspath(run_directory)
        self.repository_root = os.path.abspath(repository_root)
        self.options = dict(options)

    def translate_path(self, path: str) -> str:
        return path

    def project_metadata_directory(self) -> str:
        """Where generated Hop metadata must be written for this engine."""

        return os.path.join(self.run_directory, "metadata")

    def execute(self, entry_file: str, environment_file: str, variables: Sequence[Mapping[str, str]]) -> HopRunResult:
        raise NotImplementedError

    def cleanup(self) -> None:
        pass

    # -- shared helpers ----------------------------------------------------

    def _hop_environment(self, entry_path: str, project_path: str, environment_file: str) -> Dict[str, str]:
        return {
            "HOP_PROJECT_NAME": self.project.name,
            "HOP_PROJECT_FOLDER": project_path,
            "HOP_PROJECT_CONFIG_FILE_NAME": PROJECT_CONFIG_NAME,
            "HOP_ENVIRONMENT_NAME": str(self.options.get("environment") or "JOBSEEKER"),
            "HOP_ENVIRONMENT_CONFIG_FILE_NAME_PATHS": environment_file,
            "HOP_RUN_CONFIG": str(self.options.get("run_config") or DEFAULT_RUN_CONFIG),
            "HOP_FILE_PATH": entry_path,
            "HOP_LOG_LEVEL": str(self.options.get("log_level") or DEFAULT_LOG_LEVEL),
        }

    @staticmethod
    def _stream(
        command: Sequence[str],
        environment: Optional[Mapping[str, str]] = None,
        cwd: Optional[str] = None,
        redact_variables: Sequence[Mapping[str, str]] = (),
    ) -> Tuple[int, str]:
        """Run a command, echoing output live so the Jenkins console stays useful."""

        process = subprocess.Popen(  # noqa: S603 - fixed argv, no shell
            list(command),
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            env=dict(environment) if environment is not None else None,
            cwd=cwd,
            text=True,
            bufsize=1,
        )
        captured: List[str] = []
        assert process.stdout is not None
        for line in process.stdout:
            safe_line = redact(line, redact_variables)
            captured.append(safe_line)
            sys.stdout.write(safe_line)
            sys.stdout.flush()
        return process.wait(), "".join(captured)


class ContainerEngine(HopEngine):
    """Run one ephemeral Apache Hop container per build.

    The Docker daemon that Jenkins talks to does not share a filesystem with the
    worker, so the project and the Data Assets are streamed into named volumes
    rather than bind mounted — the same technique the Python and Talend Docker
    runtimes already use. This is also the seam a Kubernetes engine slots into:
    the contract is "start a Hop container with this project and these
    variables, return the exit code and the log".
    """

    name = "container"

    def __init__(self, project: HopProject, run_directory: str, repository_root: str, options: Mapping[str, Any]):
        super().__init__(project, run_directory, repository_root, options)
        token = uuid.uuid4().hex[:8]
        identity = _docker_safe_name(str(options.get("job") or "job"), str(options.get("build") or "0"), token)
        self.image = str(options.get("image") or _env("JOBSEEKER_HOP_IMAGE", DEFAULT_IMAGE))
        self.files_volume = "jobseeker-hop-files-%s" % identity
        self.assets_volume = "jobseeker-hop-assets-%s" % identity
        self.container_name = ("jobseeker-job-%s" % identity)[:120]
        self._volumes: List[str] = []

    def translate_path(self, path: str) -> str:
        relative = _relative_posix(path, self.repository_root)
        if relative is not None:
            return CONTAINER_REPOSITORY_PATH if relative == "." else CONTAINER_REPOSITORY_PATH + "/" + relative
        relative = _relative_posix(path, self.project.root)
        if relative is not None:
            return CONTAINER_PROJECT_PATH if relative == "." else CONTAINER_PROJECT_PATH + "/" + relative
        return path

    def project_metadata_directory(self) -> str:
        return os.path.join(self.run_directory, "metadata")

    # -- docker plumbing ---------------------------------------------------

    @staticmethod
    def _docker(*arguments: str) -> Tuple[int, str]:
        completed = subprocess.run(  # noqa: S603 - fixed argv, no shell
            ["docker", *arguments],
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            check=False,
        )
        return completed.returncode, completed.stdout

    def _create_volume(self, name: str) -> None:
        code, output = self._docker("volume", "create", name)
        if code != 0:
            raise HopError("Could not create the Docker volume %s: %s" % (name, output.strip()))
        self._volumes.append(name)

    def _upload(self, volume: str, sources: Mapping[str, str], mount: str) -> None:
        """Stream directories into a Docker volume as a tar on stdin."""

        buffer = io.BytesIO()
        with tarfile.open(fileobj=buffer, mode="w") as archive:
            for name, path in sources.items():
                if os.path.exists(path):
                    archive.add(path, arcname=name, filter=self._tar_filter)
        buffer.seek(0)

        process = subprocess.Popen(  # noqa: S603 - fixed argv, no shell
            [
                "docker", "run", "--rm", "-i", "--user", "0", "--entrypoint", "sh",
                "-v", "%s:%s" % (volume, mount), self.image,
                "-c", 'cd "%s" && tar -xf - && chmod -R a+rwX .' % mount,
            ],
            stdin=subprocess.PIPE,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=False,
        )
        output, _ = process.communicate(buffer.getvalue())
        if process.returncode != 0:
            raise HopError("Could not stage the Hop project into %s: %s" % (volume, output.decode("utf-8", "replace").strip()))

    @staticmethod
    def _tar_filter(info: "tarfile.TarInfo") -> Optional["tarfile.TarInfo"]:
        name = os.path.basename(info.name)
        if name in (".git", "__pycache__", ".venv", "node_modules"):
            return None
        info.uid = info.gid = 0
        info.uname = info.gname = "root"
        return info

    def _download_assets(self) -> None:
        """Copy generated Data Asset files back onto the repository volume."""

        assets_root = os.path.join(self.repository_root, "data-assets")
        os.makedirs(assets_root, exist_ok=True)
        process = subprocess.Popen(  # noqa: S603 - fixed argv, no shell
            [
                "docker", "run", "--rm", "--user", "0", "--entrypoint", "sh",
                "-v", "%s:%s" % (self.assets_volume, CONTAINER_REPOSITORY_PATH), self.image,
                "-c", 'rm -f %s/data-assets/manifest.json; cd %s && tar -cf - data-assets' % (CONTAINER_REPOSITORY_PATH, CONTAINER_REPOSITORY_PATH),
            ],
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
        )
        payload, _ = process.communicate()
        if process.returncode != 0 or not payload:
            return
        try:
            with tarfile.open(fileobj=io.BytesIO(payload), mode="r") as archive:
                repository_root = os.path.realpath(self.repository_root)
                for member in archive.getmembers():
                    normalized = member.name.replace("\\", "/")
                    if (
                        not (normalized == "data-assets" or normalized.startswith("data-assets/"))
                        or os.path.isabs(normalized)
                        or ".." in normalized.split("/")
                        or not (member.isfile() or member.isdir())
                    ):
                        continue
                    destination = os.path.realpath(os.path.join(repository_root, normalized))
                    try:
                        if os.path.commonpath((repository_root, destination)) != repository_root:
                            continue
                    except ValueError:
                        continue
                    if member.isdir():
                        os.makedirs(destination, exist_ok=True)
                        continue
                    source = archive.extractfile(member)
                    if source is None:
                        continue
                    os.makedirs(os.path.dirname(destination), exist_ok=True)
                    with source, open(destination, "wb") as target:
                        shutil.copyfileobj(source, target)
                    os.chmod(destination, member.mode & 0o777)
        except (tarfile.TarError, OSError) as error:
            print("[JobSeeker] Hop outputs could not be copied back: %s" % error, file=sys.stderr)

    def driver_ids(self) -> List[str]:
        """JDBC drivers to install on container start, if any.

        A custom image is expected to carry its own drivers, so the download is
        only arranged for the stock Apache Hop image. ``JOBSEEKER_HOP_DRIVERS``
        overrides the decision either way ("none" disables it).
        """

        override = _env("JOBSEEKER_HOP_DRIVERS").strip()
        if override:
            if override.lower() in ("none", "off", "false", "0"):
                return []
            return [item.strip() for item in override.split(",") if item.strip()]

        if not self.image.startswith("apache/hop"):
            return []
        return list(self.options.get("drivers") or [])

    def execute(self, entry_file: str, environment_file: str, variables: Sequence[Mapping[str, str]]) -> HopRunResult:
        code, docker_version = self._docker("version", "--format", "{{.Server.Version}}")
        if code != 0:
            raise HopError(
                "The container engine needs Docker on this Jenkins worker, but it is not available: %s"
                % docker_version.strip()
            )

        self._create_volume(self.files_volume)
        self._create_volume(self.assets_volume)

        # The project first, then the generated metadata over the top of it, so
        # a project keeps its own run configurations while JobSeeker owns the
        # database connections. Merging here rather than inside the container is
        # what lets the stock apache/hop image be used unmodified.
        self._upload(self.files_volume, {"project": self.project.root}, "/files")
        overlay = {
            "run/environment.json": environment_file,
            "project/metadata": self.project_metadata_directory(),
        }
        self._upload(self.files_volume, {name: path for name, path in overlay.items() if os.path.exists(path)}, "/files")
        assets_directory = os.path.join(self.repository_root, "data-assets")
        os.makedirs(assets_directory, exist_ok=True)
        self._upload(self.assets_volume, {"data-assets": assets_directory}, CONTAINER_REPOSITORY_PATH)

        container_environment = self._hop_environment(
            CONTAINER_PROJECT_PATH + "/" + entry_file,
            CONTAINER_PROJECT_PATH,
            CONTAINER_RUN_PATH + "/environment.json",
        )
        container_environment["JOBSEEKER_REPOSITORY_ROOT"] = CONTAINER_REPOSITORY_PATH
        container_environment["JOBSEEKER_DATA_ASSETS_MANIFEST"] = CONTAINER_REPOSITORY_PATH + "/data-assets/manifest.json"
        container_environment["JOBSEEKER_ENVIRONMENT"] = str(self.options.get("environment") or "")
        container_environment["JOBSEEKER_JOB_NAME"] = str(self.options.get("job") or "")
        parameters = dict(self.options.get("parameters") or {})
        if parameters:
            container_environment["HOP_RUN_PARAMETERS"] = ",".join(
                "%s=%s" % (name, value) for name, value in sorted(parameters.items())
            )

        drivers = self.driver_ids()
        if drivers:
            # Installing a restricted (Category X) driver means accepting its
            # vendor licence, so say so in the console rather than silently.
            container_environment["HOP_DRIVERS_DOWNLOAD"] = ",".join(drivers)
            container_environment["HOP_DRIVERS_ACCEPT_LICENSE"] = "true"
            maven_repository = _env("JOBSEEKER_HOP_DRIVERS_MAVEN_REPO")
            if maven_repository:
                container_environment["HOP_DRIVERS_MAVEN_REPO"] = maven_repository
            print(
                "[JobSeeker] Installing JDBC driver(s) %s into the Hop container, accepting their vendor licences. "
                "Build the jobseeker-hop image and set JOBSEEKER_HOP_IMAGE to bake them in instead."
                % ", ".join(drivers)
            )

        command = [
            "docker", "run", "--rm", "-i",
            "--name", self.container_name,
            "--cpus", str(self.options.get("cpu_limit") or "1"),
            "--memory", "%sm" % int(self.options.get("memory_limit_mb") or 1024),
            "--memory-swap", "%sm" % int(self.options.get("memory_limit_mb") or 1024),
            "--label", "com.jobseeker.managed=true",
            "--label", "com.jobseeker.kind=job",
            "--label", "com.jobseeker.runtime=apache-hop",
            "--label", "com.jobseeker.job.name=%s" % str(self.options.get("job") or ""),
            "--label", "com.jobseeker.environment=%s" % str(self.options.get("environment") or ""),
            "--label", "com.jobseeker.build.number=%s" % str(self.options.get("build") or "0"),
            "--network", str(self.options.get("network") or _env("JOBSEEKER_HOP_NETWORK", "host")),
            "-v", "%s:/files" % self.files_volume,
            "-v", "%s:%s" % (self.assets_volume, CONTAINER_REPOSITORY_PATH),
        ]
        for name, value in sorted(container_environment.items()):
            command.extend(("-e", "%s=%s" % (name, value)))
        command.append(self.image)

        exit_code, output = self._stream(command, redact_variables=variables)
        self._download_assets()
        return HopRunResult(exit_code, output, self.name)

    def cleanup(self) -> None:
        for volume in self._volumes:
            self._docker("volume", "rm", volume)
        self._volumes = []


class ServerEngine(HopEngine):
    """Execute on the long-lived Hop Server over its REST interface.

    ``/hop/execPipeline`` and ``/hop/execWorkflow`` run synchronously and answer
    with the Hop log, which is exactly the shape a Jenkins build step wants. The
    request is a POST so credentials travel in the body and never reach an
    access log or ``ps``.
    """

    name = "server"

    def __init__(self, project: HopProject, run_directory: str, repository_root: str, options: Mapping[str, Any]):
        super().__init__(project, run_directory, repository_root, options)
        self.base_url = str(options.get("server_url") or _env("JOBSEEKER_HOP_SERVER_URL", "http://hop-server:8080")).rstrip("/")
        self.user = str(options.get("server_user") or _env("JOBSEEKER_HOP_SERVER_USER", "cluster"))
        self.password = str(options.get("server_password") or _env("JOBSEEKER_HOP_SERVER_PASSWORD", "cluster"))
        self.timeout = float(options.get("server_timeout") or _env("JOBSEEKER_HOP_SERVER_TIMEOUT", "3600"))
        self.server_repository_root = str(
            options.get("server_repository_root") or _env("JOBSEEKER_HOP_SERVER_REPOSITORY_ROOT", "/php/repository")
        ).rstrip("/")
        self._lock_handle: Optional[Any] = None

    def translate_path(self, path: str) -> str:
        relative = _relative_posix(path, self.repository_root)
        if relative is None:
            return path
        return self.server_repository_root if relative == "." else self.server_repository_root + "/" + relative

    def project_metadata_directory(self) -> str:
        # The server resolves metadata from its own metadata folder, which is on
        # the shared repository volume. Serialize the whole prepare/execute/
        # cleanup lifecycle so concurrent environments cannot see one another's
        # generated connector catalog.
        override = self.options.get("server_metadata_directory") or _env("JOBSEEKER_HOP_SERVER_METADATA")
        directory = os.path.abspath(str(override)) if override else os.path.join(
            self.repository_root, "hop", "server", "metadata"
        )
        if self._lock_handle is None:
            lock_directory = os.path.dirname(directory)
            os.makedirs(lock_directory, exist_ok=True)
            handle = open(os.path.join(lock_directory, ".execution.lock"), "a+", encoding="utf-8")
            print("[JobSeeker] Waiting for the shared Hop Server execution slot")
            fcntl.flock(handle.fileno(), fcntl.LOCK_EX)
            self._lock_handle = handle
        return directory

    def cleanup(self) -> None:
        if self._lock_handle is None:
            return
        try:
            # Server metadata is JobSeeker-owned and contains resolved connector
            # values only for the synchronous request that just completed.
            metadata = self.options.get("server_metadata_directory") or _env("JOBSEEKER_HOP_SERVER_METADATA")
            directory = os.path.abspath(str(metadata)) if metadata else os.path.join(
                self.repository_root, "hop", "server", "metadata"
            )
            rdbms = os.path.join(directory, "rdbms")
            if os.path.isdir(rdbms):
                for name in os.listdir(rdbms):
                    path = os.path.join(rdbms, name)
                    if name.endswith(".json") and os.path.isfile(path) and not os.path.islink(path):
                        os.unlink(path)
            self._restore_published_catalog(rdbms)
        finally:
            fcntl.flock(self._lock_handle.fileno(), fcntl.LOCK_UN)
            self._lock_handle.close()
            self._lock_handle = None

    def _restore_published_catalog(self, rdbms_directory: str) -> None:
        """Put back the catalog an operator published to the Hop Server.

        The server's metadata folder is process-global, so a JobSeeker run
        borrows it for the length of its synchronous request. Anything an
        operator published there for pipelines started from the Apache Hop GUI
        has to survive that, or the first Jenkins server-engine build would
        silently break every GUI-published pipeline.
        """

        published = os.path.join(self.repository_root, "hop", "server", "published", "rdbms")
        if not os.path.isdir(published):
            return
        try:
            os.makedirs(rdbms_directory, exist_ok=True)
            for name in os.listdir(published):
                source = os.path.join(published, name)
                if not name.endswith(".json") or not os.path.isfile(source) or os.path.islink(source):
                    continue
                target = os.path.join(rdbms_directory, name)
                shutil.copyfile(source, target)
                os.chmod(target, 0o600)
        except OSError as error:
            print("[JobSeeker] The published Hop Server connections could not be restored: %s" % error, file=sys.stderr)

    @staticmethod
    def _hop_object_name(absolute_path: str, entry_file: str) -> str:
        """The name Hop registers a run under, which is the file's own <name>."""

        try:
            with open(absolute_path, "r", encoding="utf-8", errors="replace") as stream:
                head = stream.read(8192)
        except OSError:
            head = ""
        match = re.search(r"<name>(.*?)</name>", head, re.DOTALL)
        if match and match.group(1).strip():
            return match.group(1).strip()
        return os.path.splitext(os.path.basename(entry_file))[0]

    def _registered_ids(self) -> Dict[str, str]:
        """Every execution the server currently holds, as id -> name.

        Taking this before and after the request is how a run identifies its own
        execution: /hop/execPipeline answers with an empty <id>, and a name-only
        status query returns the *first* run still registered under that name -
        which could be an earlier build, or a pipeline someone launched from the
        Hop GUI against this same server.
        """

        registered: Dict[str, str] = {}
        try:
            code, body = self._request("/hop/status", {"xml": "Y"})
        except HopError:
            return registered
        if code != 200 or not body:
            return registered

        for block in re.findall(r"<pipeline-status>(.*?)</pipeline-status>", body, re.DOTALL):
            self._collect_identity(block, "pipeline_name", registered)
        for block in re.findall(r"<workflow-status>(.*?)</workflow-status>", body, re.DOTALL):
            self._collect_identity(block, "workflowname", registered)
        return registered

    @staticmethod
    def _collect_identity(block: str, name_tag: str, registered: Dict[str, str]) -> None:
        identifier = re.search(r"<id>(.*?)</id>", block, re.DOTALL)
        name = re.search(r"<%s>(.*?)</%s>" % (name_tag, name_tag), block, re.DOTALL)
        if identifier and identifier.group(1).strip():
            registered[identifier.group(1).strip()] = name.group(1).strip() if name else ""

    def _forget_run(self, name: str, execution_id: str, is_workflow: bool) -> bool:
        """Drop one execution, by id, from the server's memory.

        JobSeeker owns the runs it starts: its log and counters are already in
        the Jenkins console and in Transaction Monitoring, so leaving them in a
        long-lived JVM only grows its heap and buries the runs a person started
        from the Hop GUI. Removal is by id, never by name, so a same-named run
        belonging to somebody else is never touched.
        """

        if not execution_id:
            return False
        endpoint = "/hop/removeWorkflow" if is_workflow else "/hop/removePipeline"
        try:
            code, body = self._request(endpoint, {"name": name, "id": execution_id, "xml": "Y"})
        except HopError:
            return False
        return code == 200 and "<result>OK</result>" in body.replace(" ", "")

    def _claim_run(self, execution_id: str, name: str, is_workflow: bool) -> None:
        """Record that this build owns a Hop Server execution.

        The Apache Hop screen reconciles whatever the server holds into
        Transaction Monitoring so a run started from the Hop GUI is not
        invisible. A claim file, written on the repository volume both the agent
        and PHP see, is what stops it from opening a second TMF row for a run
        the runner is already reporting itself.
        """

        if not execution_id:
            return
        directory = os.path.join(self.repository_root, "hop", "server", "claims")
        try:
            os.makedirs(directory, exist_ok=True)
            _write_json(
                os.path.join(directory, "%s.json" % execution_id),
                {
                    "execution_id": execution_id,
                    "name": name,
                    "kind": "workflow" if is_workflow else "pipeline",
                    "job": str(self.options.get("job") or ""),
                    "environment": str(self.options.get("environment") or ""),
                    "build": str(self.options.get("build") or ""),
                    "tmf_instance_id": str(self.options.get("tmf_instance_id") or ""),
                    "claimed_at": time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime()),
                },
                mode=0o644,
            )
        except OSError as error:
            print("[JobSeeker] The Hop Server run could not be claimed: %s" % error, file=sys.stderr)

    def _collect_status(self, name: str, execution_id: str, is_workflow: bool) -> Dict[str, Any]:
        """Read one execution's log and counters back from the server.

        /hop/execPipeline answers with a bare "executed successfully", so without
        this a server run would show almost nothing in the Jenkins console and
        report zero rows to Transaction Monitoring. It is fetched for a failed
        request too: that is exactly when the log matters.
        """

        endpoint = "/hop/workflowStatus" if is_workflow else "/hop/pipelineStatus"
        fields = {"name": name, "xml": "Y"}
        if execution_id:
            fields["id"] = execution_id
        try:
            code, body = self._request(endpoint, fields)
        except HopError:
            return {}
        if code != 200 or not body:
            return {}

        status = re.search(r"<status_desc>(.*?)</status_desc>", body, re.DOTALL)
        error_description = re.search(r"<error_desc>(.*?)</error_desc>", body, re.DOTALL)
        log = "\n".join(
            decoded
            for decoded in (decode_hop_log(raw) for raw in re.findall(r"<logging_string>(.*?)</logging_string>", body, re.DOTALL))
            if decoded
        )
        if not log:
            # Older servers, and a status fetched while the run is still
            # starting, carry only the per-transform sections.
            sections = []
            for raw in re.findall(r"<log_text>(.*?)</log_text>", body, re.DOTALL):
                decoded = _unescape_xml(raw).strip()
                if decoded.startswith("<![CDATA[") and decoded.endswith("]]>"):
                    decoded = decoded[len("<![CDATA["):-len("]]>")].strip()
                if decoded:
                    sections.append(decoded)
            log = "\n".join(sections)

        return {
            "status": status.group(1).strip() if status else "",
            "error": _unescape_xml(error_description.group(1)).strip() if error_description else "",
            "log": log,
            "counters": self._status_counters(body, is_workflow),
        }

    @staticmethod
    def _status_counters(body: str, is_workflow: bool) -> Dict[str, int]:
        """Hop's own per-transform / per-action result, which beats log parsing."""

        read = written = errors = 0

        def field(text: str, tag: str) -> int:
            found = re.search(r"<%s>(-?\d+)</%s>" % (tag, tag), text, re.DOTALL)
            return int(found.group(1)) if found else 0

        if is_workflow:
            # Only the per-action results. A workflow status also carries the
            # overall result and a tracker tree that repeat the same counters,
            # so summing every <nr_errors> in the document multiplies them.
            actions = re.findall(r"<action_status>(.*?)</action_status>", body, re.DOTALL)
            for action in actions:
                read = max(read, field(action, "lines_read"))
                written = max(written, field(action, "lines_written"))
                errors += max(0, field(action, "nr_errors"))
            if not actions:
                read = max(0, field(body, "lines_read"))
                written = max(0, field(body, "lines_written"))
                errors = max(0, field(body, "nr_errors"))
        else:
            for block in re.findall(r"<transform_status>(.*?)</transform_status>", body, re.DOTALL):
                read = max(read, field(block, "linesRead"), field(block, "linesInput"))
                written = max(written, field(block, "linesWritten"))
                errors += max(0, field(block, "errors"))

        return {"records_total": read or written, "records_processed": written, "errors": errors}

    def _request(self, path: str, fields: Mapping[str, str]) -> Tuple[int, str]:
        url = "%s%s" % (self.base_url, path)
        body = urllib.parse.urlencode({name: value for name, value in fields.items() if value is not None}).encode("utf-8")
        credentials = base64.b64encode(("%s:%s" % (self.user, self.password)).encode("utf-8")).decode("ascii")
        request = urllib.request.Request(
            url,
            data=body,
            method="POST",
            headers={
                "Authorization": "Basic " + credentials,
                "Content-Type": "application/x-www-form-urlencoded",
                "Accept": "application/json",
            },
        )
        try:
            with urllib.request.urlopen(request, timeout=self.timeout) as response:  # noqa: S310 - fixed scheme
                return int(response.status), response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as error:
            return int(error.code), error.read().decode("utf-8", "replace")
        except urllib.error.URLError as error:
            raise HopError(
                "The Hop Server at %s did not answer: %s. Start the hop-server container, or use --engine container."
                % (self.base_url, error)
            ) from error

    def status(self) -> Dict[str, Any]:
        code, body = self._request("/hop/status", {"xml": "Y"})
        return {"ok": code == 200, "status": code, "url": self.base_url, "body": body[:4000]}

    def execute(self, entry_file: str, environment_file: str, variables: Sequence[Mapping[str, str]]) -> HopRunResult:
        server_path = self.translate_path(os.path.join(self.project.root, entry_file.replace("/", os.sep)))
        is_workflow = entry_file.lower().endswith(WORKFLOW_EXTENSION)
        endpoint = "/hop/execWorkflow" if is_workflow else "/hop/execPipeline"

        fields: Dict[str, str] = {
            "workflow" if is_workflow else "pipeline": server_path,
            "level": str(self.options.get("log_level") or DEFAULT_LOG_LEVEL),
            "runConfig": str(self.options.get("run_config") or DEFAULT_RUN_CONFIG),
            # The server runs a file, not a registered project, so it never sets
            # PROJECT_HOME. Without it a workflow action that points at
            # ${PROJECT_HOME}/pipelines/x.hpl - which is how the Hop GUI writes
            # every intra-project reference - resolves against the server's own
            # default project and fails with "the pipeline path ... is invalid".
            "PROJECT_HOME": self.translate_path(self.project.root),
        }
        # Every other field becomes a Hop variable or a declared parameter.
        for variable in variables:
            name = str(variable.get("name") or "")
            if name and name not in fields:
                fields[name] = str(variable.get("value") or "")
        for name, value in dict(self.options.get("parameters") or {}).items():
            fields[str(name)] = str(value)

        object_name = self._hop_object_name(
            os.path.join(self.project.root, entry_file.replace("/", os.sep)), entry_file
        )
        known_before = set(self._registered_ids())

        print("[JobSeeker] Hop Server execution: %s%s (%s)" % (self.base_url, endpoint, server_path))
        code, body = self._request(endpoint, fields)
        output = redact(_describe_web_result(body), variables)
        failed = code >= 400 or '"result":"ERROR"' in body.replace(" ", "") or "<result>ERROR</result>" in body

        execution_id = ""
        for identifier, name in self._registered_ids().items():
            if identifier not in known_before and (name == object_name or not name):
                execution_id = identifier
                break

        # The log is read back whether or not the request itself succeeded: a
        # pipeline that started and then failed reports why only here.
        status = self._collect_status(object_name, execution_id, is_workflow)
        counters = status.get("counters") or None
        if status.get("log"):
            output = output + "\n" + redact(str(status["log"]), variables)
        if status.get("error"):
            output = output + "\n" + redact(str(status["error"]), variables)
        if not failed and "with errors" in str(status.get("status") or "").lower():
            failed = True

        if execution_id:
            self._claim_run(execution_id, object_name, is_workflow)
            self._forget_run(object_name, execution_id, is_workflow)
        elif not status:
            print(
                "[JobSeeker] The Hop Server did not report a status for %r, so its row counts are unknown."
                % object_name
            )

        sys.stdout.write(output + "\n")
        sys.stdout.flush()
        detail = str(status.get("error") or "") or ("HTTP %d" % code)
        return HopRunResult(1 if failed else 0, output, self.name, detail, counters)


def _unescape_xml(value: str) -> str:
    text = re.sub(
        r"&#(x[0-9a-fA-F]+|\d+);",
        lambda match: chr(int(match.group(1)[1:], 16) if match.group(1)[0] in "xX" else int(match.group(1))),
        str(value),
    )
    return (
        text
        .replace("&lt;", "<")
        .replace("&gt;", ">")
        .replace("&quot;", '"')
        .replace("&apos;", "'")
        .replace("&amp;", "&")
    )


def _describe_web_result(body: str) -> str:
    """Unwrap a Hop ``WebResult`` document into readable console output.

    Hop answers as JSON or as XML depending on the servlet and the request, and
    either shape can carry a null message. Neither "null" nor an XML-escaped
    quote belongs in a Jenkins console, so both are handled here rather than
    everywhere the result is printed.
    """

    text = str(body or "").strip()
    try:
        payload = json.loads(text)
    except ValueError:
        payload = None

    if isinstance(payload, dict):
        envelope = payload.get("webresult") if isinstance(payload.get("webresult"), dict) else payload
        result = envelope.get("result")
        message = envelope.get("message")
        parts = []
        if result not in (None, ""):
            parts.append("Hop Server result: %s" % result)
        if message not in (None, "", "null"):
            parts.append(_unescape_xml(str(message)).strip())
        return "\n".join(part for part in parts if part)

    message = re.search(r"<message>(.*?)</message>", text, re.DOTALL)
    result = re.search(r"<result>(.*?)</result>", text, re.DOTALL)
    parts = []
    if result:
        parts.append("Hop Server result: %s" % result.group(1).strip())
    if message:
        decoded = _unescape_xml(message.group(1)).strip()
        if decoded and decoded != "null":
            parts.append(decoded)
    return "\n".join(parts) if parts else text


def make_engine(name: str, project: HopProject, run_directory: str, repository_root: str, options: Mapping[str, Any]) -> HopEngine:
    engines = {"container": ContainerEngine, "server": ServerEngine}
    if name not in engines:
        raise HopError("Unknown Hop engine %r. Use one of: %s." % (name, ", ".join(ENGINES)))
    return engines[name](project, run_directory, repository_root, options)


# ---------------------------------------------------------------------------
# Run orchestration
# ---------------------------------------------------------------------------


def _failure_message(errors: Sequence[Mapping[str, str]], detail: str, summary: str) -> str:
    """What Transaction Monitoring should show for a failed Hop run.

    Hop's own error lines first, because they say what to fix; the engine's
    detail and the run summary only when Hop said nothing useful, which happens
    when the failure was the engine itself rather than the pipeline.
    """

    lines = [
        ("%s: %s" % (error["origin"], error["message"])) if error.get("origin") else str(error["message"])
        for error in errors
    ]
    if lines:
        return "\n".join(lines)
    if detail:
        return "%s. %s" % (detail.rstrip("."), summary)
    return "Apache Hop reported errors: " + summary


def _failure_origin(errors: Sequence[Mapping[str, str]], engine_name: str) -> str:
    """The transform or action that failed, so TMF points at one place."""

    for error in errors:
        if error.get("origin"):
            return ("Apache Hop / %s" % error["origin"])[:200]
    return "Apache Hop (%s)" % engine_name


def _resolve_assets(catalog: DataAssetCatalog, keys: Sequence[str]) -> List[Any]:
    assets: List[Any] = []
    if keys:
        for key in keys:
            asset = catalog.resolve(key, mode="any", required=False)
            if asset is None:
                print("[JobSeeker] Data Asset %s is not published for this scope; its Hop variable is skipped." % key)
                continue
            assets.append(asset)
        return assets
    try:
        return catalog.list(mode="any")
    except JobSeekerError as error:
        print("[JobSeeker] Data Assets are unavailable: %s" % error)
        return []


def _resolve_context(job: "JobSeeker", keys: Sequence[str]) -> Dict[str, str]:
    values: Dict[str, str] = {}
    for key in keys:
        try:
            value = job.get_context(key)
        except Exception as error:  # noqa: BLE001 - a context miss must not fail the run
            print("[JobSeeker] Context value %s could not be read: %s" % (key, error))
            continue
        if value is not None:
            values[str(key)] = str(value)
        else:
            print("[JobSeeker] Context value %s is not configured for this run scope." % key)
    return values


def _context_variable_names(project: HopProject, manifest: HopManifest, known: Sequence[str]) -> List[str]:
    """Context keys needed by the project but not supplied by the platform.

    Hop internals and platform values already supplied for this run are
    excluded. A Context Detail called ``CUSTOMER_REGION`` is resolved
    automatically when the project uses ``${CUSTOMER_REGION}``. An unresolved
    platform-style reference can also be supplied explicitly by Context Details
    before the empty-value fallback is applied.
    """

    excluded = set(str(name) for name in known)
    requested = set()
    candidates = list(manifest.context) + project.referenced_variables()
    for name in candidates:
        name = str(name)
        if (
            name in excluded
            or name.startswith("HOP_")
            or name in ("PROJECT_HOME", "PROJECT_NAME")
            or name.startswith("Internal.")
            or name.startswith("java.")
        ):
            continue
        requested.add(name)
    return sorted(requested)


def run(
    project_path: str,
    entry_file: str = "",
    engine: str = "",
    environment: str = "",
    job: str = "",
    run_config: str = "",
    log_level: str = "",
    parameters: Optional[Mapping[str, str]] = None,
    repository_root: str = "",
    image: str = "",
    cpu_limit: str = "",
    memory_limit_mb: int = 0,
    with_connectors: bool = True,
    with_assets: bool = True,
    with_tmf: bool = True,
    keep_run_directory: bool = False,
    dry_run: bool = False,
) -> int:
    """Execute one Hop workflow or pipeline and report it to TMF.

    Returns the process exit code the Jenkins build should adopt.
    """

    project = HopProject.locate(project_path)
    manifest = project.manifest()
    project.ensure_project_config()
    project.ensure_run_configurations()

    entry = project.resolve_entry(entry_file or manifest.entry_file)
    engine_name = (engine or manifest.engine or DEFAULT_ENGINE).strip().lower()
    if engine_name not in ENGINES:
        raise HopError("Unknown Hop engine %r. Use one of: %s." % (engine_name, ", ".join(ENGINES)))

    resolved_environment = _runtime_environment(environment)
    resolved_job = job or _env("JOBSEEKER_JOB_NAME") or _env("JOB_NAME") or project.name
    resolved_run_config = run_config or manifest.run_config or DEFAULT_RUN_CONFIG
    resolved_log_level = log_level or manifest.log_level or DEFAULT_LOG_LEVEL
    if resolved_log_level not in LOG_LEVELS:
        raise HopError("Unknown Hop log level %r. Use one of: %s." % (resolved_log_level, ", ".join(LOG_LEVELS)))

    merged_parameters = dict(manifest.parameters)
    merged_parameters.update(dict(parameters or {}))
    reserved_parameters = sorted(name for name in merged_parameters if str(name).upper().startswith("JOBSEEKER_"))
    if reserved_parameters:
        raise HopError(
            "Hop parameters cannot replace JobSeeker runtime variables: %s"
            % ", ".join(reserved_parameters)
        )

    resolved_repository = os.path.abspath(repository_root or _env("JOBSEEKER_REPOSITORY_ROOT", "/php/repository"))
    build_number = _env("BUILD_NUMBER", "0")

    try:
        resolved_memory_limit = int(memory_limit_mb or int(_env("JOBSEEKER_HOP_MEMORY_LIMIT_MB", "1024")))
    except (TypeError, ValueError) as error:
        raise HopError("The Apache Hop memory limit must be an integer number of MiB.") from error
    if resolved_memory_limit < 128:
        raise HopError("The Apache Hop memory limit must be at least 128 MiB.")

    runs_root = os.path.join(resolved_repository, "hop", "runs")
    try:
        os.makedirs(runs_root, exist_ok=True)
        run_directory = tempfile.mkdtemp(prefix="%s-%s-" % (_docker_safe_name(resolved_job) or "job", build_number), dir=runs_root)
    except OSError:
        run_directory = tempfile.mkdtemp(prefix="jobseeker-hop-")
    os.chmod(run_directory, 0o700)

    options: Dict[str, Any] = {
        "environment": resolved_environment,
        "job": resolved_job,
        "build": build_number,
        "run_config": resolved_run_config,
        "log_level": resolved_log_level,
        "parameters": merged_parameters,
        "image": image,
        "cpu_limit": cpu_limit or _env("JOBSEEKER_HOP_CPU_LIMIT", "1"),
        "memory_limit_mb": resolved_memory_limit,
    }

    engine_instance: Optional[HopEngine] = None
    jobseeker: Optional[JobSeeker] = None
    monitoring_active = False
    variables: List[Dict[str, str]] = []
    started = time.time()

    try:
        engine_instance = make_engine(engine_name, project, run_directory, resolved_repository, options)
        connectors: List[Any] = []
        if with_connectors:
            try:
                connectors_directory = os.path.join(run_directory, "connectors")
                materialize_connectors(
                    directory=connectors_directory,
                    environment=resolved_environment,
                    job=resolved_job,
                )
                catalog = ConnectorCatalog(directory=connectors_directory)
                connectors = [
                    connector
                    for connector in catalog.list()
                    if not manifest.connectors or connector.key in manifest.connectors
                ]
            except JobSeekerError as error:
                print("[JobSeeker] Connectors are not available to this Hop run: %s" % error)

        assets: List[Any] = []
        if with_assets:
            asset_catalog = DataAssetCatalog(
                environment=resolved_environment,
                job=_env("JOBSEEKER_DATA_ASSET_JOB") or resolved_job,
                repository_root=resolved_repository,
            )
            assets = _resolve_assets(asset_catalog, manifest.assets)

        referenced_variables = project.referenced_variables()
        known_variable_names = list(merged_parameters)
        known_variable_names.extend((
            "JOBSEEKER_ENVIRONMENT",
            "JOBSEEKER_JOB_NAME",
            "JOBSEEKER_TMF_INSTANCE_ID",
            "JOBSEEKER_BUILD_NUMBER",
        ))
        known_variable_names.extend(
            _variable_name("JOBSEEKER_ASSET", asset.key) for asset in assets
        )
        for connector in connectors:
            prefix = _variable_name("JOBSEEKER_CONN", connector.key)
            known_variable_names.extend(
                prefix + suffix for suffix in ("_HOST", "_PORT", "_DATABASE", "_USER", "_PASSWORD")
            )
        context_names = _context_variable_names(project, manifest, known_variable_names)

        # Context lookup and TMF share the SDK transport, but they are separate
        # capabilities: --no-tmf must not silently disable Context variables
        # referenced by the Hop files or explicitly listed in the manifest.
        if with_tmf or context_names:
            try:
                jobseeker = JobSeeker(
                    environment=resolved_environment,
                    job=resolved_job,
                    install_signal_handlers=False,
                )
            except Exception as error:  # noqa: BLE001 - monitoring must never block execution
                capability = "Transaction Monitoring and Context" if with_tmf and context_names else (
                    "Transaction Monitoring" if with_tmf else "Context"
                )
                print("[JobSeeker] %s is unavailable for this run: %s" % (capability, error))
                jobseeker = None

        context_values = _resolve_context(jobseeker, context_names) if jobseeker is not None and context_names else {}

        variables = build_run_variables(
            environment=resolved_environment,
            job=resolved_job,
            instance_id=jobseeker.instance_id if with_tmf and jobseeker is not None else "",
            build_number=build_number,
            connectors=connectors,
            assets=assets,
            parameters=merged_parameters,
            context=context_values,
            path_translator=engine_instance.translate_path,
        )

        # A Context or platform variable explicitly used by a project must
        # never survive as a literal ``${NAME}`` string. Real scoped values
        # above always win; absent optional values resolve to an empty string.
        resolved_names = {variable["name"] for variable in variables}
        for name in referenced_variables:
            if (name in context_names or name.startswith("JOBSEEKER_")) and name not in resolved_names:
                variables.append(_described(name, "", "Unavailable in this JobSeeker run scope"))
                resolved_names.add(name)
        variables.sort(key=lambda variable: variable["name"])

        if context_values:
            print("[JobSeeker] Context variables: %s" % ", ".join(sorted(context_values)))

        relational = [connector for connector in connectors if str(getattr(connector, "type", "")).lower() in RELATIONAL_TYPES]
        # The engine copies its options at construction, so set it on the instance.
        engine_instance.options["drivers"] = required_hop_drivers(relational)
        engine_instance.options["tmf_instance_id"] = jobseeker.instance_id if with_tmf and jobseeker is not None else ""
        metadata_directory = engine_instance.project_metadata_directory()
        # The Hop Server reads its metadata folder directly and has no variable
        # space of its own before a run, so it needs resolved values there.
        write_rdbms_metadata(
            metadata_directory,
            relational,
            use_variables=engine_name != "server",
            replace=engine_name == "server",
        )

        environment_file = write_environment_file(os.path.join(run_directory, "environment.json"), variables)

        print("[JobSeeker] Apache Hop %s run" % engine_name)
        print("[JobSeeker] project=%s file=%s environment=%s run-config=%s" % (project.name, entry, resolved_environment, resolved_run_config))
        if relational:
            print("[JobSeeker] Hop database connections: %s" % ", ".join(sorted(connector.key for connector in relational)))
        if assets:
            print("[JobSeeker] Data Asset variables: %s" % ", ".join(sorted(_variable_name("JOBSEEKER_ASSET", asset.key) for asset in assets)))

        if dry_run:
            print(json.dumps({
                "engine": engine_name,
                "project": project.root,
                "entry_file": entry,
                "run_config": resolved_run_config,
                "log_level": resolved_log_level,
                "variables": [variable["name"] for variable in variables],
                "connections": sorted(connector.key for connector in relational),
                "environment_file": environment_file,
                "metadata_directory": metadata_directory,
            }, indent=2, sort_keys=True))
            return 0

        if with_tmf and jobseeker is not None:
            try:
                jobseeker.begin(eventText="Apache Hop", dimension=engine_name, msg=entry)
                monitoring_active = True
            except Exception as error:  # noqa: BLE001
                print("[JobSeeker] Could not open the Transaction Monitoring instance: %s" % error)

        result = engine_instance.execute(entry, environment_file, variables)
        # An engine that read Hop's own structured result wins over parsing its
        # console output; the log parser stays the fallback for the rest.
        counters = dict(result.counters) if result.counters else parse_hop_counters(result.output)
        parsed = parse_hop_counters(result.output)
        counters.setdefault("has_metrics", parsed.get("has_metrics", 0))
        counters["errors"] = max(int(counters.get("errors") or 0), int(parsed.get("errors") or 0))
        # Some Hop transforms log ERROR/FATAL but still leave both the engine
        # exit code and structured error counter at zero. Treat Hop's own error
        # lines as authoritative so Jenkins and TMF cannot report success while
        # the console says a connection or transform failed.
        hop_errors = extract_hop_errors(result.output)
        if hop_errors and counters["errors"] == 0:
            counters["errors"] = len(hop_errors)
        failed = not result.ok or counters["errors"] > 0 or bool(hop_errors)
        elapsed = time.time() - started
        summary = "%s in %.1fs (read %d, written %d, errors %d)" % (
            entry, elapsed, counters["records_total"], counters["records_processed"], counters["errors"]
        )
        if hop_errors:
            print("[JobSeeker] Apache Hop reported %d error line(s):" % len(hop_errors))
            for hop_error in hop_errors:
                print("[JobSeeker]   %s%s" % (
                    (hop_error["origin"] + ": ") if hop_error["origin"] else "", hop_error["message"]
                ))

        if monitoring_active and jobseeker is not None:
            try:
                if failed:
                    jobseeker.error(
                        msg=redact(_failure_message(hop_errors, result.detail, summary), variables)[:4000],
                        origin=_failure_origin(hop_errors, engine_name),
                        code=result.exit_code or 1,
                        type="Apache Hop",
                    )
                else:
                    jobseeker.end(
                        records_total=counters["records_total"],
                        records_processed=counters["records_processed"],
                        msg=summary[:4000],
                    )
            except Exception as error:  # noqa: BLE001
                print("[JobSeeker] Transaction Monitoring could not be closed: %s" % error)

        print("[JobSeeker] %s %s" % ("FAILED" if failed else "Completed", summary))
        if failed and result.exit_code == 0:
            return 1
        return result.exit_code
    finally:
        try:
            if engine_instance is not None:
                engine_instance.cleanup()
        finally:
            if jobseeker is not None:
                try:
                    jobseeker.close()
                except Exception:  # noqa: BLE001
                    pass
            if not keep_run_directory:
                shutil.rmtree(run_directory, ignore_errors=True)


# ---------------------------------------------------------------------------
# Command line
# ---------------------------------------------------------------------------


def _parse_parameters(values: Optional[Sequence[str]]) -> Dict[str, str]:
    parameters: Dict[str, str] = {}
    for item in values or []:
        name, separator, value = str(item).partition("=")
        if not separator or not name.strip():
            raise HopError("A Hop parameter must be NAME=VALUE, not %r." % item)
        parameters[name.strip()] = value
    return parameters


def main(argv: Optional[Sequence[str]] = None) -> int:
    import argparse

    parser = argparse.ArgumentParser(prog="jobseeker-hop", description="Run Apache Hop workflows and pipelines as JobSeeker jobs.")
    commands = parser.add_subparsers(dest="command", required=True)

    run_parser = commands.add_parser("run", help="Execute a Hop workflow or pipeline")
    run_parser.add_argument("--project", required=True, help="Hop project folder (or any path inside it)")
    run_parser.add_argument("--file", default="", help="Project-relative .hwf or .hpl file")
    run_parser.add_argument("--engine", default="", choices=("",) + ENGINES)
    run_parser.add_argument("--environment", default="", help="JobSeeker environment, for example DEV")
    run_parser.add_argument("--job", default="", help="Jenkins job name used for scoping and TMF")
    run_parser.add_argument("--run-config", default="", help="Hop run configuration name")
    run_parser.add_argument("--log-level", default="", choices=("",) + LOG_LEVELS)
    run_parser.add_argument("--param", action="append", default=[], metavar="NAME=VALUE")
    run_parser.add_argument("--repository-root", default="")
    run_parser.add_argument("--image", default="", help="Hop container image for --engine container")
    run_parser.add_argument("--cpu-limit", default="")
    run_parser.add_argument("--memory-limit-mb", type=int, default=0)
    run_parser.add_argument("--no-connectors", action="store_true")
    run_parser.add_argument("--no-assets", action="store_true")
    run_parser.add_argument("--no-tmf", action="store_true")
    run_parser.add_argument("--keep-run-directory", action="store_true")
    run_parser.add_argument("--dry-run", action="store_true", help="Resolve everything and print the plan without starting Hop")

    inspect_parser = commands.add_parser("inspect", help="Describe a Hop project as JSON")
    inspect_parser.add_argument("--project", required=True)

    metadata_parser = commands.add_parser("metadata", help="Preview the Hop database metadata generated from connectors")
    metadata_parser.add_argument("--environment", default="")
    metadata_parser.add_argument("--job", default="")
    metadata_parser.add_argument("--directory", default="", help="Existing materialized connector directory")

    scaffold_parser = commands.add_parser("scaffold", help="Create an empty Hop project skeleton")
    scaffold_parser.add_argument("--project", required=True)
    scaffold_parser.add_argument("--name", default="")

    tmf_parser = commands.add_parser(
        "tmf",
        help="Report progress into this run's Transaction Monitoring row from inside a Hop workflow",
    )
    tmf_parser.add_argument("operation", choices=("begin", "heartbeat", "end", "error", "cancel"))
    tmf_parser.add_argument("--environment", default="")
    tmf_parser.add_argument("--job", default="")
    tmf_parser.add_argument("--instance-id", default="", help="Defaults to ${JOBSEEKER_TMF_INSTANCE_ID}")
    tmf_parser.add_argument("--records-total", type=int, default=0)
    tmf_parser.add_argument("--records-processed", type=int, default=0)
    tmf_parser.add_argument("--message", default="")
    tmf_parser.add_argument("--event-text", default="Apache Hop")
    tmf_parser.add_argument("--dimension", default="")
    tmf_parser.add_argument("--origin", default="Apache Hop")
    tmf_parser.add_argument("--code", type=int, default=1)

    catalog_parser = commands.add_parser(
        "server-catalog",
        help="Write the Hop Server database catalog from the JobSeeker connectors in scope",
    )
    catalog_parser.add_argument("--directory", required=True, help="Hop Server metadata folder")
    catalog_parser.add_argument("--environment", default="")
    catalog_parser.add_argument("--job", default="")

    status_parser = commands.add_parser("server-status", help="Check the Hop Server")
    status_parser.add_argument("--server-url", default="")

    arguments = parser.parse_args(list(argv) if argv is not None else None)

    try:
        if arguments.command == "run":
            return run(
                project_path=arguments.project,
                entry_file=arguments.file,
                engine=arguments.engine,
                environment=arguments.environment,
                job=arguments.job,
                run_config=arguments.run_config,
                log_level=arguments.log_level,
                parameters=_parse_parameters(arguments.param),
                repository_root=arguments.repository_root,
                image=arguments.image,
                cpu_limit=arguments.cpu_limit,
                memory_limit_mb=arguments.memory_limit_mb,
                with_connectors=not arguments.no_connectors,
                with_assets=not arguments.no_assets,
                with_tmf=not arguments.no_tmf,
                keep_run_directory=arguments.keep_run_directory,
                dry_run=arguments.dry_run,
            )

        if arguments.command == "inspect":
            print(json.dumps(HopProject.locate(arguments.project).describe(), indent=2, sort_keys=True))
            return 0

        if arguments.command == "metadata":
            directory = arguments.directory or None
            temporary_directory = None
            try:
                if directory is None:
                    temporary_directory = tempfile.mkdtemp(prefix="jobseeker-hop-metadata-")
                    directory = temporary_directory
                    materialize_connectors(
                        directory=directory,
                        environment=arguments.environment or None,
                        job=arguments.job or None,
                    )
                catalog = ConnectorCatalog(directory=directory)
                documents = {}
                for connector in catalog.list():
                    document = rdbms_metadata(connector)
                    if document is not None:
                        documents[connector.key] = document
                print(json.dumps(documents, indent=2, sort_keys=True))
                return 0
            finally:
                if temporary_directory is not None:
                    shutil.rmtree(temporary_directory, ignore_errors=True)

        if arguments.command == "scaffold":
            project = scaffold_project(arguments.project, arguments.name)
            print(json.dumps(project.describe(), indent=2, sort_keys=True))
            return 0

        if arguments.command == "tmf":
            # The runner publishes JOBSEEKER_TMF_INSTANCE_ID as a Hop variable,
            # so a workflow's shell action can update the very row its own build
            # opened - the same contract Python jobs get from the SDK directly.
            instance_id = arguments.instance_id or _env("JOBSEEKER_TMF_INSTANCE_ID")
            if not instance_id and arguments.operation != "begin":
                raise HopError(
                    "No TMF instance id. Pass --instance-id, or run this from a job whose "
                    "${JOBSEEKER_TMF_INSTANCE_ID} variable is set."
                )
            # The SDK narrates to stdout. A shell action needs to capture the
            # instance id with $(...), so the narration goes to stderr and the
            # id is the only thing on stdout.
            import contextlib

            monitor = JobSeeker(
                environment=arguments.environment or None,
                job=arguments.job or _env("JOBSEEKER_JOB_NAME") or None,
                instance_id=instance_id or None,
                install_signal_handlers=False,
            )
            try:
                with contextlib.redirect_stdout(sys.stderr):
                    if arguments.operation == "begin":
                        monitor.begin(
                            eventText=arguments.event_text,
                            dimension=arguments.dimension,
                            records_total=arguments.records_total,
                            msg=arguments.message or None,
                        )
                    elif arguments.operation == "heartbeat":
                        monitor.heartbeat(
                            records_total=arguments.records_total or None,
                            records_processed=arguments.records_processed or None,
                            msg=arguments.message or None,
                        )
                    elif arguments.operation == "end":
                        monitor.end(
                            records_total=arguments.records_total,
                            records_processed=arguments.records_processed,
                            msg=arguments.message,
                        )
                    elif arguments.operation == "cancel":
                        monitor.transaction_open = True
                        monitor.cancel(msg=arguments.message or "Process cancelled")
                    else:
                        monitor.error(
                            msg=arguments.message,
                            origin=arguments.origin,
                            code=arguments.code,
                            type="Apache Hop",
                        )
            finally:
                monitor.close()
            if arguments.operation == "begin":
                print(monitor.instance_id)
            return 0

        if arguments.command == "server-catalog":
            staging = tempfile.mkdtemp(prefix="jobseeker-hop-catalog-")
            try:
                materialize_connectors(
                    directory=staging,
                    environment=arguments.environment or None,
                    job=arguments.job or _env("JOBSEEKER_HOP_SERVER_JOB", "hop-server"),
                )
                connectors = [
                    connector
                    for connector in ConnectorCatalog(directory=staging).list()
                    if str(getattr(connector, "type", "")).lower() in RELATIONAL_TYPES
                ]
                # The server has no per-run variable space, so its own metadata
                # folder is where Hop expects the resolved credentials to live.
                written = write_rdbms_metadata(arguments.directory, connectors, use_variables=False, replace=True)
                print("Wrote %d Hop database connection(s) to %s" % (len(written), arguments.directory))
                return 0
            finally:
                shutil.rmtree(staging, ignore_errors=True)

        if arguments.command == "server-status":
            project_directory = tempfile.mkdtemp(prefix="jobseeker-hop-status-")
            run_directory = tempfile.mkdtemp(prefix="jobseeker-hop-status-run-")
            try:
                engine = ServerEngine(
                    HopProject(project_directory),
                    run_directory,
                    _env("JOBSEEKER_REPOSITORY_ROOT", "/php/repository"),
                    {"server_url": arguments.server_url},
                )
                status = engine.status()
                print(json.dumps(status, indent=2, sort_keys=True))
                return 0 if status["ok"] else 1
            finally:
                shutil.rmtree(project_directory, ignore_errors=True)
                shutil.rmtree(run_directory, ignore_errors=True)
    except JobSeekerError as error:
        sys.stderr.write("jobseeker-hop: %s\n" % error)
        return 2

    return 2


if __name__ == "__main__":
    raise SystemExit(main())
