"""Data Asset sources: read what a Data Asset points at, for jobs and previews.

A Data Asset is a named contract; its *source* says where the data lives:

* ``upload``              a file uploaded to JobSeeker or produced by a job
* ``url``                 a web page, REST API response, or file URL
* ``google_sheet``        a Google Sheet (public, or through a Google Sheets Connection)
* ``database_table``      a table or view behind a SQL Connection
* ``object_storage``      a file in S3, Azure Blob/Data Lake, Google Cloud Storage, or SFTP
* ``document_collection`` a MongoDB collection or Elasticsearch/OpenSearch index

Jobs *materialize* a source into the asset's runtime path, so Python, shell,
Hop, and container jobs all read an ordinary file. The Data Preview service
reads a bounded *sample* of the same source for the web UI. Both go through
this module, so a preview shows what a job will read.

Credentials arrive as an already resolved :class:`jobseeker.Connector`. Nothing
here stores them, and messages never include a URL's query string (where an
API key may live) or a secret value.
"""

from __future__ import annotations

import base64
import csv
import html
import http.client
import io
import ipaddress
import json
import os
import posixpath
import re
import socket
import ssl
import tempfile
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser
from typing import TYPE_CHECKING, Any, Dict, Iterator, List, Mapping, Optional, Sequence, Tuple

from . import JobSeekerDependencyError, JobSeekerError, _CredentialScopedRedirect, _load_conntest

# Connection parsing is shared with the connection tests. The SDK's loader
# works whether it is installed as a package or vendored by file path.
_conntest = _load_conntest()
_first_host, _params, _sanitize = _conntest._first_host, _conntest._params, _conntest._sanitize

if TYPE_CHECKING:  # pragma: no cover
    from . import Connector, DataAsset


PREVIEW_ROWS = 20
PREVIEW_TEXT_CHARS = 65536
# Line-oriented formats need only their first records.
PREVIEW_STREAM_BYTES = 1048576
# JSON, Excel, and Parquet can only be parsed whole.
PREVIEW_WHOLE_FILE_BYTES = 67108864
# A URL is read into memory; storage objects and tables stream to disk.
MATERIALIZE_HTTP_BYTES = 104857600
TIMEOUT = 20

SOURCE_TYPES = ("upload", "url", "google_sheet", "database_table", "object_storage", "document_collection")
SQL_CONNECTORS = ("mysql", "pgsql", "sqlserver", "oracle_service", "oracle_sid", "snowflake", "databricks")
STORAGE_CONNECTORS = ("aws_s3", "azure_blob", "azure_data_lake", "gcs", "sftp")
DOCUMENT_CONNECTORS = ("mongodb", "elasticsearch")
WHOLE_FILE_FORMATS = ("json", "xlsx", "parquet")

_IDENTIFIER = re.compile(r"^[A-Za-z_][A-Za-z0-9_$]{0,127}$")
_COLLECTION = re.compile(r"^[A-Za-z0-9_][A-Za-z0-9_.\-*]{0,254}$")


# Shared helpers ------------------------------------------------------------------


def _display_url(url: str) -> str:
    """A URL without its query string or fragment, which can carry API keys."""

    return urllib.parse.urlsplit(url)._replace(query="", fragment="").geturl()


def _cell(value: Any, limit: int = 240) -> str:
    if value is None:
        text = ""
    elif isinstance(value, bool):
        text = "true" if value else "false"
    elif isinstance(value, (bytes, bytearray)):
        text = "0x" + bytes(value[:64]).hex()
    elif isinstance(value, (dict, list, tuple)):
        text = json.dumps(value, default=str, ensure_ascii=False)
    elif hasattr(value, "read") and callable(value.read):  # Oracle LOB
        text = str(value.read())
    else:
        text = str(value)
    text = re.sub(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]", "", text)
    return text if len(text) <= limit else text[: limit - 3] + "..."


def _csv_value(value: Any) -> Any:
    """A database value as a materialized CSV cell, without preview truncation."""

    if value is None:
        return ""
    if isinstance(value, (bytes, bytearray, memoryview)):
        return "0x" + bytes(value).hex()
    if isinstance(value, (dict, list)):
        return json.dumps(value, default=str, ensure_ascii=False)
    if hasattr(value, "read") and callable(value.read):  # Oracle LOB
        return value.read()
    return value


def _table(columns: Sequence[Any], rows: Sequence[Sequence[Any]], truncated: bool, **extra: Any) -> Dict[str, Any]:
    columns = [_cell(column, 120) for column in columns]
    width = len(columns)
    result = {
        "ok": True,
        "kind": "table",
        "columns": columns,
        "rows": [[_cell(value) for value in list(row)[:width]] + [""] * max(0, width - len(row)) for row in rows],
        "truncated": bool(truncated),
    }
    result.update(extra)
    return result


def _records_table(records: Sequence[Any], truncated: bool, **extra: Any) -> Dict[str, Any]:
    columns: List[str] = []
    normalized = []
    for record in records:
        record = record if isinstance(record, Mapping) else {"value": record}
        normalized.append(record)
        for column in record.keys():
            if str(column) not in columns and len(columns) < 50:
                columns.append(str(column))
    rows = [[record.get(column, "") for column in columns] for record in normalized]
    return _table(columns, rows, truncated, **extra)


def _matrix_table(matrix: Sequence[Sequence[Any]], has_header: bool, truncated: bool, **extra: Any) -> Dict[str, Any]:
    matrix = [list(row) for row in matrix]
    columns: List[Any] = list(matrix.pop(0)) if has_header and matrix else []
    width = max([len(columns)] + [len(row) for row in matrix])
    columns += ["Column %d" % index for index in range(len(columns) + 1, width + 1)]
    return _table(columns, matrix[:PREVIEW_ROWS], truncated or len(matrix) > PREVIEW_ROWS, **extra)


def _text(value: str, truncated: bool, **extra: Any) -> Dict[str, Any]:
    result = {"ok": True, "kind": "text", "text": value[:PREVIEW_TEXT_CHARS], "truncated": truncated or len(value) > PREVIEW_TEXT_CHARS}
    result.update(extra)
    return result


def _write_atomically(target: str, writer: Any, binary: bool = True) -> str:
    """Write through a temporary file so a failed read never leaves half a dataset."""

    directory = os.path.dirname(target)
    os.makedirs(directory, exist_ok=True)
    descriptor, temporary = tempfile.mkstemp(prefix=".jobseeker-asset-", dir=directory)
    try:
        if binary:
            with os.fdopen(descriptor, "wb") as stream:
                writer(stream)
        else:
            with os.fdopen(descriptor, "w", encoding="utf-8", newline="") as stream:
                writer(stream)
        os.replace(temporary, target)
    except BaseException:
        try:
            os.unlink(temporary)
        except OSError:
            pass
        raise
    return target


def _require(connector: Optional["Connector"], types: Sequence[str], what: str) -> "Connector":
    if connector is None:
        raise JobSeekerError("%s requires a Connection." % what)
    if str(connector.type).lower() not in types:
        raise JobSeekerError("Connection %s (%s) cannot serve %s." % (connector.key, connector.type, what.lower()))
    return connector


# HTTP ----------------------------------------------------------------------------


def _public_socket(host: str, port: int, timeout: Any) -> socket.socket:
    """Connect to a host only when every address it resolves to is public.

    The socket connects to the address that was checked, so a second DNS answer
    cannot redirect a public URL to an internal service (DNS rebinding).
    """

    try:
        addresses = socket.getaddrinfo(host, port, type=socket.SOCK_STREAM)
    except socket.gaierror as error:
        raise JobSeekerError("%s could not be resolved." % host) from error
    for _family, _type, _proto, _name, address in addresses:
        if not ipaddress.ip_address(address[0].split("%", 1)[0]).is_global:
            raise JobSeekerError(
                "Public URL sources cannot reach private, loopback, or link-local addresses (%s). "
                "Use a Connection for an internal API." % host
            )
    family, kind, proto, _name, address = addresses[0]
    connection = socket.socket(family, kind, proto)
    if timeout is not None and timeout is not socket._GLOBAL_DEFAULT_TIMEOUT:  # type: ignore[attr-defined]
        connection.settimeout(timeout)
    try:
        connection.connect(address)
    except OSError:
        connection.close()
        raise
    return connection


class _PublicHTTPConnection(http.client.HTTPConnection):
    def connect(self) -> None:
        self.sock = _public_socket(self.host, self.port, self.timeout)


class _PublicHTTPSConnection(http.client.HTTPSConnection):
    def connect(self) -> None:
        self.sock = self._context.wrap_socket(  # type: ignore[attr-defined]
            _public_socket(self.host, self.port, self.timeout), server_hostname=self.host
        )


def _host(url: str) -> str:
    return (urllib.parse.urlsplit(url).hostname or "").lower()


class _GuardedHTTPHandler(urllib.request.HTTPHandler):
    """Use the public-address connection for requests marked as guarded."""

    def http_open(self, req):  # type: ignore[no-untyped-def]
        return self.do_open(_PublicHTTPConnection if getattr(req, "jobseeker_guarded", False) else http.client.HTTPConnection, req)


class _GuardedHTTPSHandler(urllib.request.HTTPSHandler):
    def https_open(self, req):  # type: ignore[no-untyped-def]
        connection = _PublicHTTPSConnection if getattr(req, "jobseeker_guarded", False) else http.client.HTTPSConnection
        return self.do_open(connection, req, context=self._context)  # type: ignore[attr-defined]


class _GuardedRedirect(_CredentialScopedRedirect):
    """Keep credentials on their host, and guard every host but a trusted one."""

    def __init__(self, credential_headers: Any, public_only: bool, trusted_host: str):
        super().__init__(credential_headers)
        self.public_only = public_only
        self.trusted_host = trusted_host

    def redirect_request(self, req, fp, code, msg, headers, newurl):  # type: ignore[no-untyped-def]
        redirected = super().redirect_request(req, fp, code, msg, headers, newurl)
        if redirected is not None:
            redirected.jobseeker_guarded = self.public_only and _host(newurl) != self.trusted_host
        return redirected


def http_get(
    url: str,
    headers: Optional[Mapping[str, str]] = None,
    max_bytes: int = PREVIEW_STREAM_BYTES,
    public_only: bool = False,
    insecure: bool = False,
    method: str = "GET",
    body: Optional[bytes] = None,
    timeout: float = TIMEOUT,
    trust_origin: bool = False,
) -> Tuple[bytes, bool, str]:
    """Return up to ``max_bytes`` of a response, whether it was cut, and its type.

    ``public_only`` refuses private destinations, including after redirects;
    ``trust_origin`` exempts the URL's own host, as for a Connection's
    endpoint. Connection credentials in ``headers`` go only to that host.
    """

    parts = urllib.parse.urlsplit(url)
    if parts.scheme not in ("http", "https") or not parts.hostname:
        raise JobSeekerError("Use an absolute HTTP or HTTPS URL.")
    if parts.username or parts.password:
        raise JobSeekerError("Put credentials in a Connection, not in the source URL.")
    credentials = dict(headers or {})
    request_headers = {"User-Agent": "jobseeker-data-assets/2", "Accept": "*/*"}
    request_headers.update({str(name): str(value) for name, value in credentials.items()})
    context = ssl.create_default_context()
    if insecure:
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE
    trusted_host = _host(url) if trust_origin else ""
    handlers = [_GuardedRedirect(credentials.keys(), public_only, trusted_host), _GuardedHTTPHandler(), _GuardedHTTPSHandler(context=context)]
    request = urllib.request.Request(url, data=body, headers=request_headers, method=method)
    request.jobseeker_guarded = public_only and not trust_origin  # type: ignore[attr-defined]
    try:
        with urllib.request.build_opener(*handlers).open(request, timeout=timeout) as response:
            payload = response.read(max_bytes + 1)
            content_type = str(response.headers.get("Content-Type") or "")
    except urllib.error.HTTPError as error:
        raise JobSeekerError("%s returned HTTP %s." % (_display_url(url), error.code)) from None
    except urllib.error.URLError as error:
        reason = error.reason if isinstance(error.reason, (str, JobSeekerError)) else _sanitize(error.reason)
        raise JobSeekerError("%s could not be read: %s" % (_display_url(url), reason)) from None
    except (OSError, http.client.HTTPException) as error:
        raise JobSeekerError("%s could not be read: %s" % (_display_url(url), _sanitize(error))) from None
    return payload[:max_bytes], len(payload) > max_bytes, content_type


def connector_base_url(connector: "Connector") -> str:
    raw = str(connector.host or "").strip()
    if not raw:
        return ""
    if "://" not in raw:
        raw = ("http" if connector.port == 80 else "https") + "://" + raw
    parts = urllib.parse.urlsplit(raw)
    port = parts.port or connector.port
    default_port = (parts.scheme == "https" and port == 443) or (parts.scheme == "http" and port == 80)
    authority = parts.hostname or ""
    if port and not default_port:
        authority += ":%d" % port
    return "%s://%s%s" % (parts.scheme, authority, parts.path.rstrip("/"))


def connector_headers(connector: Optional["Connector"]) -> Dict[str, str]:
    if connector is None:
        return {}
    parameters = _params(connector)
    if connector.value("token", ""):
        return {"Authorization": (parameters.get("authorization_scheme") or "Bearer") + " " + str(connector.value("token"))}
    if connector.value("api_key", ""):
        return {parameters.get("api_key_header") or "X-API-Key": str(connector.value("api_key"))}
    if connector.username or connector.password:
        credential = base64.b64encode((connector.username + ":" + connector.password).encode("utf-8")).decode("ascii")
        return {"Authorization": "Basic " + credential}
    return {}


def _insecure(connector: Optional["Connector"]) -> bool:
    return connector is not None and _params(connector).get("insecure") in ("1", "true", "yes")


def _source_url(asset: "DataAsset", connector: Optional["Connector"]) -> str:
    url = str((asset.source or {}).get("url") or "").strip()
    if connector is not None:
        _require(connector, ("http_api",), "A Web / API source")
        base = connector_base_url(connector)
        if url.startswith("/"):
            url = base.rstrip("/") + url
        if (urllib.parse.urlsplit(url).hostname or "").lower() != (urllib.parse.urlsplit(base).hostname or "").lower():
            raise JobSeekerError("An authenticated URL must use the host of Connection %s." % connector.key)
    return url


# Google Sheets -------------------------------------------------------------------


def _sheet_matrix(
    asset: "DataAsset", connector: Optional["Connector"], max_bytes: int, public_only: bool
) -> Tuple[List[List[str]], bool]:
    source = dict(asset.source or {})
    spreadsheet_id = str(source.get("spreadsheet_id") or "").strip()
    sheet_range = str(source.get("range") or "").strip()
    if connector is None:
        url = str(source.get("url") or "").strip()
        if not url:
            if not spreadsheet_id:
                raise JobSeekerError("Data asset %s needs a public Google Sheet URL or spreadsheet ID." % asset.key)
            url = "https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv" % urllib.parse.quote(spreadsheet_id)
            if sheet_range:
                url += "&range=" + urllib.parse.quote(sheet_range)
        body, truncated, _ = http_get(url, {}, max_bytes, public_only)
        if truncated:
            body = body[: body.rfind(b"\n") + 1]
        return list(csv.reader(io.StringIO(body.decode("utf-8-sig", "replace")))), truncated
    _require(connector, ("google_sheets",), "A Google Sheet source")
    if not spreadsheet_id:
        raise JobSeekerError("Data asset %s needs its spreadsheet ID to use a Google Sheets Connection." % asset.key)
    url = "https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s" % (
        urllib.parse.quote(spreadsheet_id), urllib.parse.quote(sheet_range or "A:ZZ", safe="")
    )
    headers = {}
    if connector.value("api_key", ""):
        url += "?key=" + urllib.parse.quote(str(connector.value("api_key")))
    elif connector.value("token", ""):
        headers = connector_headers(connector)
    body, truncated, _ = http_get(url, headers, max(max_bytes, PREVIEW_WHOLE_FILE_BYTES), False)
    try:
        values = json.loads(body.decode("utf-8")).get("values", [])
    except (ValueError, UnicodeError, AttributeError):
        raise JobSeekerError("Google Sheets returned an invalid values response for %s." % asset.key) from None
    return [["" if value is None else str(value) for value in (row if isinstance(row, list) else [row])] for row in values], truncated


# SQL tables ----------------------------------------------------------------------


def table_reference(connector_type: str, schema: str, table: str) -> str:
    """A safely quoted ``schema.table`` for the Connection's SQL dialect.

    Identifiers are validated, never interpolated raw. ``schema`` may name a
    catalog or database too (``catalog.schema``), as Databricks, Snowflake, and
    SQL Server allow. Oracle and Snowflake names stay unquoted so they follow
    the database's own case folding.
    """

    parts = ([part for part in str(schema).split(".")] if str(schema).strip() else []) + [str(table)]
    if len(parts) > 3 or not all(_IDENTIFIER.match(part) for part in parts):
        raise JobSeekerError("Use letters, digits, _ and $ for the table name, and at most catalog.schema for its schema.")
    connector_type = connector_type.lower()
    if connector_type in ("mysql", "databricks"):
        return ".".join("`%s`" % part for part in parts)
    if connector_type == "pgsql":
        return ".".join('"%s"' % part for part in parts)
    if connector_type == "sqlserver":
        return ".".join("[%s]" % part for part in parts)
    if connector_type in ("oracle_service", "oracle_sid", "snowflake"):
        return ".".join(parts)
    raise JobSeekerError("Database table sources support %s Connections." % ", ".join(SQL_CONNECTORS))


def table_query(connector_type: str, reference: str, limit: Optional[int]) -> str:
    """The only statement a table source runs: a bounded or full ``SELECT *``."""

    if limit is None:
        return "SELECT * FROM %s" % reference
    limit = int(limit)
    if connector_type == "sqlserver":
        return "SELECT TOP (%d) * FROM %s" % (limit, reference)
    if connector_type in ("oracle_service", "oracle_sid"):
        return "SELECT * FROM %s WHERE ROWNUM <= %d" % (reference, limit)
    return "SELECT * FROM %s LIMIT %d" % (reference, limit)


def _driver(module: str, package: str) -> Any:
    import importlib

    try:
        return importlib.import_module(module)
    except ImportError:
        raise JobSeekerDependencyError("Install %s to read this Connection's data." % package) from None


def _sql_connect(connector: "Connector", timeout: float) -> Any:
    connector_type = str(connector.type).lower()
    params = _params(connector)
    host = _first_host(connector.host)
    if connector_type == "mysql":
        return _driver("mysql.connector", "mysql-connector-python").connect(
            host=host, port=connector.port or 3306, user=connector.username, password=connector.password,
            database=connector.database or None, connection_timeout=int(timeout),
        )
    if connector_type == "pgsql":
        try:
            driver = _driver("psycopg", "psycopg[binary]")
        except JobSeekerDependencyError:
            driver = _driver("psycopg2", "psycopg[binary]")
        return driver.connect(
            host=host, port=connector.port or 5432, user=connector.username, password=connector.password,
            dbname=connector.database or None, connect_timeout=int(timeout),
            sslmode=params.get("sslmode") or params.get("ssl_mode") or "prefer",
        )
    if connector_type == "sqlserver":
        return _driver("pymssql", "pymssql").connect(
            server=host, port=str(connector.port or 1433), user=connector.username, password=connector.password,
            database=connector.database or "", login_timeout=int(timeout), timeout=int(timeout) * 3,
        )
    if connector_type in ("oracle_service", "oracle_sid"):
        oracledb = _driver("oracledb", "oracledb")
        service_name = str(connector.value("oracle_service_name", "") or "")
        sid = str(connector.value("oracle_sid", "") or "")
        port = connector.port or 1521
        if sid and (connector_type == "oracle_sid" or not service_name):
            dsn = oracledb.makedsn(host, port, sid=sid)
        else:
            dsn = oracledb.makedsn(host, port, service_name=service_name or connector.database or "")
        return oracledb.connect(user=connector.username, password=connector.password, dsn=dsn, tcp_connect_timeout=timeout)
    if connector_type == "snowflake":
        return _driver("snowflake.connector", "snowflake-connector-python").connect(
            account=params.get("account") or host.split(".")[0], user=connector.username, password=connector.password,
            database=connector.database or None, warehouse=params.get("warehouse"), role=params.get("role"),
            login_timeout=int(timeout), network_timeout=int(timeout) * 3,
        )
    if connector_type == "databricks":
        return _driver("databricks.sql", "databricks-sql-connector").connect(
            server_hostname=host, http_path=params.get("http_path") or str(connector.value("http_path", "") or ""),
            access_token=str(connector.value("token", "") or connector.password or ""), _socket_timeout=int(timeout) * 3,
        )
    raise JobSeekerError("Database table sources support %s Connections." % ", ".join(SQL_CONNECTORS))


def table_rows(
    connector: "Connector", schema: str, table: str, limit: Optional[int] = None, timeout: float = TIMEOUT
) -> Iterator[Sequence[Any]]:
    """Yield a table's column names, then each of its rows."""

    _require(connector, SQL_CONNECTORS, "A database table source")
    connector_type = str(connector.type).lower()
    reference = table_reference(connector_type, schema, table)
    connection = None
    try:
        connection = _sql_connect(connector, timeout)
        cursor = connection.cursor()
        cursor.execute(table_query(connector_type, reference, limit))
        yield [str(column[0]) for column in (cursor.description or [])]
        while True:
            batch = cursor.fetchmany(1000)
            if not batch:
                break
            for row in batch:
                yield tuple(row)
    except JobSeekerError:
        raise
    except Exception as error:  # noqa: BLE001 - every driver raises its own types
        raise JobSeekerError("Table %s could not be read through %s: %s" % (reference, connector.key, _sanitize(error))) from None
    finally:
        if connection is not None:
            try:
                connection.close()
            except Exception:  # noqa: BLE001
                pass


# Object storage ------------------------------------------------------------------


def storage_container(connector: "Connector") -> str:
    """The bucket or container a storage Connection names, if any."""

    params = _params(connector)
    return str(connector.database or params.get("bucket") or params.get("container") or "").strip().strip("/")


def storage_location(connector: "Connector", path: str) -> Tuple[str, str]:
    """(bucket or container, object path) for a path within a Connection.

    The Connection's resource field, or its ``bucket=``/``container=``
    parameter, names the bucket or container. Without one, the path's first
    segment does.
    """

    path = str(path or "").strip()
    if not path or ".." in path.split("/") or re.search(r"[\x00-\x1f\x7f]", path):
        raise JobSeekerError("Provide an object path without .. segments or control characters.")
    if str(connector.type).lower() == "sftp":
        base = str(connector.database or "").strip()
        return "", posixpath.normpath(path if path.startswith("/") or not base else posixpath.join(base, path))
    path = path.lstrip("/")
    container = storage_container(connector)
    if not container:
        container, _, path = path.partition("/")
    if not container or not path:
        raise JobSeekerError("Name the bucket or container in the Connection's resource field, or start the path with it.")
    return container, path


def _s3_client(connector: "Connector", timeout: float) -> Any:
    boto3 = _driver("boto3", "boto3")
    config = _driver("botocore.config", "boto3").Config
    params = _params(connector)
    host = str(connector.host or "")
    endpoint = str(connector.value("endpoint_url", "") or (host if "://" in host else ""))
    kwargs: Dict[str, Any] = {}
    if connector.value("access_key_id", ""):
        kwargs["aws_access_key_id"] = str(connector.value("access_key_id"))
        kwargs["aws_secret_access_key"] = str(connector.value("secret_access_key", ""))
        if connector.value("session_token", ""):
            kwargs["aws_session_token"] = str(connector.value("session_token"))
    # S3-compatible stores (MinIO, Ceph) generally need path-style addressing.
    style = params.get("addressing_style") or ("path" if endpoint else "auto")
    return boto3.client(
        "s3", region_name=params.get("region") or None, endpoint_url=endpoint or None,
        config=config(connect_timeout=timeout, read_timeout=timeout * 3, retries={"max_attempts": 2}, s3={"addressing_style": style}),
        **kwargs,
    )


def _azure_credential(connector: "Connector") -> Any:
    auth = str(connector.value("auth_type", "") or "")
    if auth in ("sas_token",) or connector.value("sas_token", ""):
        return str(connector.value("sas_token", ""))
    if connector.value("account_key", "") or (auth == "access_key" and connector.value("secret_access_key", "")):
        return str(connector.value("account_key", "") or connector.value("secret_access_key"))
    if auth in ("managed_identity", "workload_identity", "service_principal"):
        identity = _driver("azure.identity", "azure-identity")
        params = _params(connector)
        if auth == "service_principal":
            return identity.ClientSecretCredential(
                tenant_id=str(connector.value("tenant_id", "") or params.get("tenant_id", "")),
                client_id=str(connector.value("client_id", "")), client_secret=str(connector.value("client_secret", "")),
            )
        client_id = str(connector.value("client_id", "") or params.get("client_id", "")) or None
        return identity.DefaultAzureCredential(managed_identity_client_id=client_id) if client_id else identity.DefaultAzureCredential()
    return None


def _azure_account_url(connector: "Connector", service: str) -> str:
    url = str(connector.host or "").strip()
    if "://" not in url:
        return "https://%s.%s.core.windows.net" % (url, service)
    return url.replace(".blob.core.windows.net", ".dfs.core.windows.net") if service == "dfs" else url


def _azure_downloader(connector: "Connector", container: str, path: str, offset: Optional[int], length: Optional[int], timeout: float) -> Any:
    connection_string = str(connector.value("connection_string", "") or "")
    if str(connector.type).lower() == "azure_data_lake":
        module = _driver("azure.storage.filedatalake", "azure-storage-file-datalake")
        service = (
            module.DataLakeServiceClient.from_connection_string(connection_string, connection_timeout=timeout)
            if connection_string
            else module.DataLakeServiceClient(_azure_account_url(connector, "dfs"), credential=_azure_credential(connector), connection_timeout=timeout)
        )
        return service.get_file_client(container, path).download_file(offset=offset, length=length)
    module = _driver("azure.storage.blob", "azure-storage-blob")
    service = (
        module.BlobServiceClient.from_connection_string(connection_string, connection_timeout=timeout)
        if connection_string
        else module.BlobServiceClient(_azure_account_url(connector, "blob"), credential=_azure_credential(connector), connection_timeout=timeout)
    )
    return service.get_blob_client(container, path).download_blob(offset=offset, length=length)


def _gcs_client(connector: "Connector") -> Any:
    storage = _driver("google.cloud.storage", "google-cloud-storage")
    kwargs: Dict[str, Any] = {}
    host = str(connector.host or "").strip()
    info = str(connector.value("service_account_json", "") or connector.value("credentials_json", "") or "")
    if info:
        account = _driver("google.oauth2.service_account", "google-auth")
        parsed = json.loads(info)
        kwargs = {"credentials": account.Credentials.from_service_account_info(parsed), "project": parsed.get("project_id")}
    elif "://" in host:
        # A GCS-compatible endpoint without a key, such as an emulator.
        kwargs = {"credentials": _driver("google.auth.credentials", "google-auth").AnonymousCredentials(),
                  "project": _params(connector).get("project") or "jobseeker"}
    elif _params(connector).get("project"):
        kwargs = {"project": _params(connector)["project"]}
    if "://" in host:
        kwargs["client_options"] = {"api_endpoint": host.rstrip("/")}
    return storage.Client(**kwargs)


def _gcs_blob(connector: "Connector", container: str, path: str) -> Any:
    return _gcs_client(connector).bucket(container).blob(path)


def _sftp_session(connector: "Connector", timeout: float) -> Tuple[Any, Any]:
    paramiko = _driver("paramiko", "paramiko")
    host = _first_host(connector.host)
    port = connector.port or 22
    transport = paramiko.Transport(socket.create_connection((host, port), timeout=timeout))
    transport.banner_timeout = timeout
    transport.start_client(timeout=timeout)
    known_hosts = str(connector.value("known_hosts", "") or "")
    if known_hosts:
        offered = transport.get_remote_server_key()
        names = {host, "[%s]:%d" % (host, port)}
        pinned = False
        for line in known_hosts.splitlines():
            fields = line.split()
            if len(fields) >= 3 and names & set(fields[0].split(",")) and fields[1] == offered.get_name():
                pinned = pinned or fields[2] == offered.get_base64()
        if not pinned:
            transport.close()
            raise JobSeekerError("The SFTP host key of %s does not match the Connection's known_hosts." % host)
    key = str(connector.value("private_key", "") or connector.value("ssh_key", "") or "")
    if key:
        loaded = None
        for loader in (paramiko.Ed25519Key, paramiko.ECDSAKey, paramiko.RSAKey):
            try:
                loaded = loader.from_private_key(io.StringIO(key))
                break
            except Exception:  # noqa: BLE001
                continue
        transport.auth_publickey(connector.username, loaded)
    else:
        transport.auth_password(connector.username, connector.password)
    return transport, paramiko.SFTPClient.from_transport(transport)


def _storage_error(connector: "Connector", container: str, path: str, error: Exception) -> JobSeekerError:
    location = "%s/%s" % (container, path) if container else path
    response = getattr(error, "response", None)
    codes = {type(error).__name__, str(getattr(error, "error_code", "") or ""), str(getattr(error, "status_code", "") or "")}
    if isinstance(response, dict):  # botocore
        codes.add(str(response.get("Error", {}).get("Code", "")))
    if isinstance(error, FileNotFoundError) or codes & {"NoSuchKey", "404", "NotFound", "ResourceNotFoundError", "BlobNotFound", "PathNotFound"}:
        return JobSeekerError("%s was not found through %s." % (location, connector.key))
    if codes & {"AccessDenied", "403", "Forbidden", "ClientAuthenticationError", "AuthorizationFailure"}:
        return JobSeekerError("%s denied access to %s." % (connector.key, location))
    return JobSeekerError("%s could not be read through %s: %s" % (location, connector.key, _sanitize(error)))


def read_object(connector: "Connector", path: str, max_bytes: int, timeout: float = TIMEOUT) -> Tuple[bytes, bool, Optional[int]]:
    """The first ``max_bytes`` of a stored file, whether it was cut, and its size."""

    _require(connector, STORAGE_CONNECTORS, "A cloud storage source")
    container, path = storage_location(connector, path)
    connector_type = str(connector.type).lower()
    try:
        if connector_type == "aws_s3":
            client = _s3_client(connector, timeout)
            size = int(client.head_object(Bucket=container, Key=path).get("ContentLength") or 0)
            data = client.get_object(Bucket=container, Key=path, Range="bytes=0-%d" % max_bytes)["Body"].read() if size else b""
        elif connector_type in ("azure_blob", "azure_data_lake"):
            try:
                downloader = _azure_downloader(connector, container, path, 0, max_bytes + 1, timeout)
                size = int(downloader.properties.size)
                data = downloader.readall()
            except Exception as error:  # noqa: BLE001
                if getattr(error, "status_code", None) != 416:  # a ranged read of an empty blob
                    raise
                size, data = 0, b""
        elif connector_type == "gcs":
            blob = _gcs_blob(connector, container, path)
            blob.reload(timeout=timeout)
            size = int(blob.size or 0)
            data = blob.download_as_bytes(start=0, end=max_bytes, timeout=timeout) if size else b""
        else:
            transport, client = _sftp_session(connector, timeout)
            try:
                size = int(client.stat(path).st_size or 0)
                with client.open(path, "rb") as stream:
                    data = stream.read(max_bytes + 1)
            finally:
                transport.close()
    except JobSeekerError:
        raise
    except Exception as error:  # noqa: BLE001 - every cloud SDK raises its own types
        raise _storage_error(connector, container, path, error) from None
    return data[:max_bytes], len(data) > max_bytes or size > max_bytes, size


def copy_object(connector: "Connector", path: str, stream: Any, timeout: float = TIMEOUT) -> None:
    """Stream a whole stored file into a binary file object."""

    _require(connector, STORAGE_CONNECTORS, "A cloud storage source")
    container, path = storage_location(connector, path)
    connector_type = str(connector.type).lower()
    try:
        if connector_type == "aws_s3":
            _s3_client(connector, timeout).download_fileobj(container, path, stream)
        elif connector_type in ("azure_blob", "azure_data_lake"):
            _azure_downloader(connector, container, path, None, None, timeout).readinto(stream)
        elif connector_type == "gcs":
            _gcs_blob(connector, container, path).download_to_file(stream, timeout=timeout)
        else:
            transport, client = _sftp_session(connector, timeout)
            try:
                client.getfo(path, stream)
            finally:
                transport.close()
    except JobSeekerError:
        raise
    except Exception as error:  # noqa: BLE001
        raise _storage_error(connector, container, path, error) from None


# Document collections ------------------------------------------------------------


def document_rows(connector: "Connector", collection: str, limit: Optional[int] = None, timeout: float = TIMEOUT) -> Iterator[Dict[str, Any]]:
    """Yield the documents of a MongoDB collection or Elasticsearch index."""

    _require(connector, DOCUMENT_CONNECTORS, "A document collection source")
    if not _COLLECTION.match(str(collection)):
        raise JobSeekerError("Use letters, digits, _, -, . and * for the collection or index name.")
    if str(connector.type).lower() == "mongodb":
        yield from _mongo_documents(connector, collection, limit, timeout)
    else:
        yield from _elasticsearch_documents(connector, collection, limit, timeout)


def _mongo_documents(connector: "Connector", collection: str, limit: Optional[int], timeout: float) -> Iterator[Dict[str, Any]]:
    pymongo = _driver("pymongo", "pymongo")
    host = str(connector.host or "")
    database = str(connector.database or "")
    kwargs: Dict[str, Any] = {"serverSelectionTimeoutMS": int(timeout * 1000)}
    if connector.username:
        kwargs.update(username=connector.username, password=connector.password)
    client = None
    try:
        client = pymongo.MongoClient(host, **kwargs) if "://" in host else pymongo.MongoClient(host=_first_host(host) or "localhost", port=connector.port or 27017, **kwargs)
        if not database:
            database = client.get_default_database().name
        for document in client[database][collection].find({}, limit=limit or 0, batch_size=500):
            yield json.loads(json.dumps(document, default=str))
    except JobSeekerError:
        raise
    except Exception as error:  # noqa: BLE001
        if "No default database" in str(error):
            raise JobSeekerError("Put the MongoDB database in Connection %s's resource field." % connector.key) from None
        raise JobSeekerError("Collection %s could not be read through %s: %s" % (collection, connector.key, _sanitize(error))) from None
    finally:
        if client is not None:
            client.close()


def _elasticsearch_documents(connector: "Connector", index: str, limit: Optional[int], timeout: float) -> Iterator[Dict[str, Any]]:
    base = connector_base_url(connector).rstrip("/")
    headers = dict(connector_headers(connector))
    headers["Content-Type"] = "application/json"
    insecure = _insecure(connector)

    def call(path: str, body: Mapping[str, Any], method: str = "POST") -> Dict[str, Any]:
        payload, _, _ = http_get(base + path, headers, PREVIEW_WHOLE_FILE_BYTES, False, insecure, method, json.dumps(body).encode("utf-8"), timeout)
        try:
            return json.loads(payload.decode("utf-8"))
        except ValueError:
            raise JobSeekerError("%s returned an invalid search response." % connector.key) from None

    def documents(hits: Sequence[Mapping[str, Any]]) -> Iterator[Dict[str, Any]]:
        for hit in hits:
            document = {"_id": hit.get("_id")}
            document.update(dict(hit.get("_source") or {}))
            yield document

    path = "/" + urllib.parse.quote(index, safe="*,")
    if limit is not None and limit <= 10000:
        response = call(path + "/_search", {"size": int(limit), "query": {"match_all": {}}})
        yield from documents(response.get("hits", {}).get("hits", []))
        return
    response = call(path + "/_search?scroll=2m", {"size": 1000, "query": {"match_all": {}}, "sort": ["_doc"]})
    scroll_id = response.get("_scroll_id")
    try:
        while response.get("hits", {}).get("hits"):
            yield from documents(response["hits"]["hits"])
            response = call("/_search/scroll", {"scroll": "2m", "scroll_id": scroll_id})
            scroll_id = response.get("_scroll_id", scroll_id)
    finally:
        if scroll_id:
            try:
                call("/_search/scroll", {"scroll_id": scroll_id}, "DELETE")
            except JobSeekerError:
                pass


# File formats ------------------------------------------------------------------


def json_path(value: Any, path: str) -> Any:
    """Follow a dotted response path such as ``data.items`` (list indexes allowed)."""

    path = str(path or "").strip()
    for segment in [part for part in path.split(".") if part]:
        if isinstance(value, list) and segment.isdigit() and int(segment) < len(value):
            value = value[int(segment)]
        elif isinstance(value, Mapping) and segment in value:
            value = value[segment]
        else:
            raise JobSeekerError("The JSON response path %s was not found." % path)
    return value


class _HTMLTables(HTMLParser):
    """Collect the first table of a web page, plus its visible text."""

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.rows: List[List[str]] = []
        self.has_header = False
        self.text: List[str] = []
        self._depth = 0
        self._done = False
        self._row: Optional[List[str]] = None
        self._cell: Optional[List[str]] = None
        self._skip = 0

    def handle_starttag(self, tag: str, attrs: Any) -> None:
        if tag in ("script", "style", "noscript"):
            self._skip += 1
        elif tag == "table" and not self._done:
            self._depth += 1
        elif self._depth == 1 and tag == "tr":
            self._row = []
        elif self._depth == 1 and tag in ("td", "th") and self._row is not None:
            self._cell = []
            if tag == "th" and not self.rows:
                self.has_header = True

    def handle_endtag(self, tag: str) -> None:
        if tag in ("script", "style", "noscript"):
            self._skip = max(0, self._skip - 1)
        elif tag == "table" and self._depth:
            self._depth -= 1
            self._done = self._done or (self._depth == 0 and bool(self.rows))
        elif self._depth == 1 and tag in ("td", "th") and self._cell is not None and self._row is not None:
            self._row.append(" ".join("".join(self._cell).split()))
            self._cell = None
        elif self._depth == 1 and tag == "tr" and self._row is not None:
            if any(self._row):
                self.rows.append(self._row)
            self._row = None

    def handle_data(self, data: str) -> None:
        if self._skip:
            return
        if self._cell is not None:
            self._cell.append(data)
        if data.strip():
            self.text.append(data.strip())


def _decode(data: bytes, options: Mapping[str, Any]) -> str:
    encoding = str(options.get("encoding") or "utf-8")
    try:
        return data.decode("utf-8-sig" if encoding.lower().replace("_", "-") in ("utf-8", "utf8") else encoding, "replace")
    except LookupError:
        return data.decode("utf-8", "replace")


def _complete_lines(data: bytes, truncated: bool) -> bytes:
    """Drop a trailing partial line of a stream that was cut."""

    return data[: data.rfind(b"\n") + 1] if truncated and b"\n" in data else data


def _binary_summary(data: bytes, size: Optional[int]) -> Dict[str, Any]:
    signatures = (
        (b"PK\x03\x04", "ZIP archive (or an Office document)"), (b"%PDF", "PDF document"), (b"\x89PNG", "PNG image"),
        (b"\xff\xd8\xff", "JPEG image"), (b"GIF8", "GIF image"), (b"\x1f\x8b", "gzip archive"), (b"PAR1", "Parquet file"),
        (b"SQLite format 3", "SQLite database"), (b"BZh", "bzip2 archive"), (b"7z\xbc\xaf", "7-Zip archive"),
    )
    kind = next((label for magic, label in signatures if data.startswith(magic)), "Unrecognized binary content")
    lines = []
    for offset in range(0, min(len(data), 256), 16):
        chunk = data[offset : offset + 16]
        printable = "".join(chr(byte) if 32 <= byte < 127 else "." for byte in chunk)
        lines.append("%08x  %-47s  %s" % (offset, " ".join("%02x" % byte for byte in chunk), printable))
    return _text("%s · %s bytes\n\n%s" % (kind, "{:,}".format(size if size is not None else len(data)), "\n".join(lines)), False, detail=kind)


def sample_file(
    data: bytes, file_format: str, options: Optional[Mapping[str, Any]] = None,
    source: Optional[Mapping[str, Any]] = None, truncated: bool = False, size: Optional[int] = None,
) -> Dict[str, Any]:
    """Parse the first records of file content into a preview payload."""

    options = dict(options or {})
    source = dict(source or {})
    file_format = str(file_format or "binary").lower()
    if file_format in WHOLE_FILE_FORMATS and truncated:
        raise JobSeekerError(
            "%s files are previewed whole, and this one is larger than the %d MB preview limit."
            % (file_format.upper(), PREVIEW_WHOLE_FILE_BYTES // 1048576)
        )
    if file_format in ("csv", "table"):
        delimiter = str(options.get("delimiter") or ",")
        text = _decode(_complete_lines(data, truncated), options)
        matrix = []
        for row in csv.reader(io.StringIO(text), delimiter="\t" if delimiter in ("\\t", "tab") else delimiter[0]):
            matrix.append(row)
            if len(matrix) > PREVIEW_ROWS + 1:
                break
        return _matrix_table(matrix, options.get("header", True) not in (False, 0, "0", "false"), truncated)
    if file_format == "jsonl":
        records = []
        for line in _decode(_complete_lines(data, truncated), options).splitlines():
            if line.strip():
                try:
                    records.append(json.loads(line))
                except ValueError:
                    records.append({"invalid_json": line.strip()})
            if len(records) > PREVIEW_ROWS:
                break
        return _records_table(records[:PREVIEW_ROWS], truncated or len(records) > PREVIEW_ROWS)
    if file_format == "json":
        try:
            value = json.loads(_decode(data, options))
        except ValueError as error:
            raise JobSeekerError("The source is not valid JSON: %s" % error) from None
        value = json_path(value, str(source.get("response_path") or ""))
        if isinstance(value, list):
            return _records_table(value[:PREVIEW_ROWS], len(value) > PREVIEW_ROWS, total_rows=len(value))
        return _text(json.dumps(value, indent=2, ensure_ascii=False, default=str), False,
                     detail="A JSON object; set a response path to show a list as rows")
    if file_format == "xlsx":
        openpyxl = _driver("openpyxl", "openpyxl")
        try:
            workbook = openpyxl.load_workbook(io.BytesIO(data), read_only=True, data_only=True)
        except Exception as error:  # noqa: BLE001
            raise JobSeekerError("The Excel workbook could not be opened: %s" % _sanitize(error)) from None
        try:
            name = str(options.get("sheet") or "")
            if name and name not in workbook.sheetnames:
                raise JobSeekerError("The workbook has no sheet named %s (sheets: %s)." % (name, ", ".join(workbook.sheetnames)))
            sheet = workbook[name] if name else workbook[workbook.sheetnames[0]]
            matrix = [list(row) for row in sheet.iter_rows(max_row=PREVIEW_ROWS + 2, values_only=True)]
            total = max(0, (sheet.max_row or 0) - 1)
            detail = "Sheet %s of %s" % (sheet.title, ", ".join(workbook.sheetnames))
        finally:
            workbook.close()
        return _matrix_table(matrix, True, total > PREVIEW_ROWS, total_rows=total or None, detail=detail)
    if file_format == "parquet":
        parquet = _driver("pyarrow.parquet", "pyarrow")
        try:
            handle = parquet.ParquetFile(io.BytesIO(data))
            batch = next(handle.iter_batches(batch_size=PREVIEW_ROWS), None)
        except Exception as error:  # noqa: BLE001
            raise JobSeekerError("The Parquet file could not be read: %s" % _sanitize(error)) from None
        total = int(handle.metadata.num_rows)
        columns = [str(name) for name in handle.schema_arrow.names]
        records = batch.to_pylist() if batch is not None else []
        detail = "%d row group(s) · %s" % (handle.metadata.num_row_groups,
                                            ", ".join("%s %s" % (field.name, field.type) for field in handle.schema_arrow)[:300])
        return _table(columns, [[record.get(column) for column in columns] for record in records], total > PREVIEW_ROWS,
                      total_rows=total, detail=detail)
    if file_format == "html":
        parser = _HTMLTables()
        parser.feed(_decode(data, options))
        parser.close()
        if len(parser.rows) >= 2:
            return _matrix_table(parser.rows, parser.has_header, truncated or len(parser.rows) > PREVIEW_ROWS + 1,
                                 detail="First table on the page")
        return _text(html.unescape(" ".join(parser.text)), truncated, detail="Page text")
    if file_format in ("xml", "txt"):
        return _text(_decode(data[: PREVIEW_TEXT_CHARS * 4], options), truncated)
    return _binary_summary(data, size)


# Reading a whole asset ---------------------------------------------------------


def _file_limit(file_format: str) -> int:
    return PREVIEW_WHOLE_FILE_BYTES if str(file_format).lower() in WHOLE_FILE_FORMATS else PREVIEW_STREAM_BYTES


def preview(asset: "DataAsset", connector: Optional["Connector"] = None, public_only: bool = True) -> Dict[str, Any]:
    """A bounded, read-only sample of any Data Asset source.

    ``public_only`` refuses private destinations for sources without a
    Connection; the web preview service always sets it.
    """

    source_type = asset.source_type or "upload"
    source = dict(asset.source or {})
    options = dict(asset.options or {})
    try:
        if source_type == "upload":
            path = asset._local_path()
            if not os.path.isfile(path):
                raise JobSeekerError("Upload a file, or run the job that produces it, before previewing %s." % asset.key)
            size = os.path.getsize(path)
            with open(path, "rb") as stream:
                data = stream.read(_file_limit(asset.format) + 1)
            limit = _file_limit(asset.format)
            result = sample_file(data[:limit], asset.format, options, source, len(data) > limit, size)
            result["size"] = size
        elif source_type == "url":
            data, truncated, content_type = http_get(
                _source_url(asset, connector), connector_headers(connector), _file_limit(asset.format),
                public_only, _insecure(connector), trust_origin=connector is not None,
            )
            result = sample_file(data, asset.format, options, source, truncated)
            result["size"] = len(data)
            result.setdefault("detail", content_type.split(";")[0] or None)
        elif source_type == "google_sheet":
            matrix, truncated = _sheet_matrix(asset, connector, PREVIEW_STREAM_BYTES, public_only)
            result = _matrix_table(matrix[: PREVIEW_ROWS + 2], True, truncated or len(matrix) > PREVIEW_ROWS + 1,
                                   total_rows=None if truncated else max(0, len(matrix) - 1))
        elif source_type == "database_table":
            rows = table_rows(_require(connector, SQL_CONNECTORS, "A database table source"),
                              str(source.get("schema") or ""), str(source.get("table") or ""), PREVIEW_ROWS + 1)
            try:
                columns = list(next(rows))
                sample = [row for _, row in zip(range(PREVIEW_ROWS + 1), rows)]
            finally:
                rows.close()
            result = _table(columns, sample[:PREVIEW_ROWS], len(sample) > PREVIEW_ROWS)
        elif source_type == "object_storage":
            data, truncated, size = read_object(_require(connector, STORAGE_CONNECTORS, "A cloud storage source"),
                                                str(source.get("path") or ""), _file_limit(asset.format))
            result = sample_file(data, asset.format, options, source, truncated, size)
            result["size"] = size
        elif source_type == "document_collection":
            documents = document_rows(_require(connector, DOCUMENT_CONNECTORS, "A document collection source"),
                                      str(source.get("collection") or ""), PREVIEW_ROWS + 1)
            try:
                sample = [document for _, document in zip(range(PREVIEW_ROWS + 1), documents)]
            finally:
                documents.close()
            result = _records_table(sample[:PREVIEW_ROWS], len(sample) > PREVIEW_ROWS)
        else:
            raise JobSeekerError("Data asset %s has an unsupported source type: %s." % (asset.key, source_type))
    except JobSeekerError as error:
        return {"ok": False, "message": str(error)}
    result.update({"source_type": source_type, "format": asset.format})
    return {name: value for name, value in result.items() if value is not None}


def materialize(asset: "DataAsset", connector: Optional["Connector"], target: str) -> str:
    """Write the whole source to ``target``, the file every runtime reads."""

    source_type = asset.source_type
    source = dict(asset.source or {})
    if source_type == "url":
        data, truncated, _ = http_get(_source_url(asset, connector), connector_headers(connector),
                                      MATERIALIZE_HTTP_BYTES, False, _insecure(connector), timeout=60)
        if truncated:
            raise JobSeekerError("Data asset %s is larger than the %d MB URL download limit." % (asset.key, MATERIALIZE_HTTP_BYTES // 1048576))
        return _write_atomically(target, lambda stream: stream.write(data))
    if source_type == "google_sheet":
        matrix, truncated = _sheet_matrix(asset, connector, MATERIALIZE_HTTP_BYTES, False)
        if truncated:
            raise JobSeekerError("Google Sheet %s is larger than the %d MB download limit." % (asset.key, MATERIALIZE_HTTP_BYTES // 1048576))
        return _write_atomically(target, lambda stream: csv.writer(stream).writerows(matrix), binary=False)
    if source_type == "database_table":
        rows = table_rows(_require(connector, SQL_CONNECTORS, "A database table source"),
                          str(source.get("schema") or ""), str(source.get("table") or ""), None, timeout=60)

        def write_table(stream: Any) -> None:
            writer = csv.writer(stream)
            for row in rows:
                writer.writerow([_csv_value(value) for value in row])

        return _write_atomically(target, write_table, binary=False)
    if source_type == "object_storage":
        storage = _require(connector, STORAGE_CONNECTORS, "A cloud storage source")
        return _write_atomically(target, lambda stream: copy_object(storage, str(source.get("path") or ""), stream, timeout=60))
    if source_type == "document_collection":
        documents = document_rows(_require(connector, DOCUMENT_CONNECTORS, "A document collection source"),
                                  str(source.get("collection") or ""), None, timeout=60)

        def write_documents(stream: Any) -> None:
            for document in documents:
                stream.write(json.dumps(document, ensure_ascii=False, default=str) + "\n")

        return _write_atomically(target, write_documents, binary=False)
    raise JobSeekerError("Data asset %s has an unsupported source type: %s." % (asset.key, source_type))
