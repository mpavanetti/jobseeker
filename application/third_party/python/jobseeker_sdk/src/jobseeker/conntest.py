"""Live connection tests for JobSeeker runtime connectors.

This module runs the *same* connector resolution a JobSeeker Python job uses
(``jobseeker.ConnectorCatalog`` / ``jobseeker.Connector``) and then performs a
real protocol handshake for the connector's type: connect, authenticate, and run
the cheapest possible "are you alive" call.

It is deliberately dependency-light. Every driver is imported lazily inside its
handler; when a driver is not installed the test degrades to a raw TCP probe and
reports ``status="driver_missing"`` instead of failing hard.

Usage on a Jenkins worker (after ``jobseeker-connector materialize``)::

    python -m jobseeker.conntest --directory "$DIR" --key my-warehouse --json
"""

from __future__ import annotations

import argparse
import http.client
import json
import os
import re
import shutil
import socket
import ssl
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from typing import TYPE_CHECKING, Any, Dict, List, Optional

if TYPE_CHECKING:  # pragma: no cover - typing only
    from jobseeker import Connector


# Result -----------------------------------------------------------------------

PASSED = "passed"
UNREACHABLE = "unreachable"
AUTH_FAILED = "auth_failed"
DRIVER_MISSING = "driver_missing"
UNSUPPORTED = "unsupported"
SKIPPED = "skipped"


@dataclass
class ConnectionCheck:
    name: str
    ok: bool
    detail: str = ""

    def to_dict(self) -> Dict[str, Any]:
        return {"name": self.name, "ok": self.ok, "detail": self.detail}


@dataclass
class ConnectionTestResult:
    connector: str
    type: str
    ok: bool = False
    status: str = SKIPPED
    secret_backend: str = ""
    environment: str = ""
    job: str = ""
    latency_ms: Optional[int] = None
    server_version: str = ""
    message: str = ""
    checks: List[ConnectionCheck] = field(default_factory=list)

    def add(self, name: str, ok: bool, detail: str = "") -> "ConnectionTestResult":
        self.checks.append(ConnectionCheck(name, ok, _sanitize(detail)))
        return self

    def finish(self, status: str, message: str, server_version: str = "") -> "ConnectionTestResult":
        self.status = status
        self.ok = status == PASSED
        self.message = _sanitize(message)
        if server_version:
            self.server_version = _sanitize(server_version)[:200]
        return self

    def to_dict(self) -> Dict[str, Any]:
        return {
            "connector": self.connector,
            "type": self.type,
            "ok": self.ok,
            "status": self.status,
            "secret_backend": self.secret_backend,
            "environment": self.environment,
            "job": self.job,
            "latency_ms": self.latency_ms,
            "server_version": self.server_version,
            "message": self.message,
            "checks": [check.to_dict() for check in self.checks],
        }

    def to_json(self) -> str:
        return json.dumps(self.to_dict(), sort_keys=True)


# Helpers --------------------------------------------------------------------

_SECRET_HINTS = ("password", "passwd", "secret", "token", "api_key", "apikey", "sas", "key=", "pwd")


def _sanitize(text: Any) -> str:
    """Best-effort scrub of anything that looks like a credential from a message."""

    value = str(text or "")
    value = re.sub(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]", " ", value)
    value = re.sub(r"://([^/@:\s]+):([^/@\s]+)@", r"://\1:***@", value)
    value = re.sub(
        r"(?i)\b(password|passwd|pwd|secret|token|api[_-]?key|sas|authorization)\s*[=:]\s*\S+",
        r"\1=***",
        value,
    )
    return value.strip()[:600]


def _first_host(raw: str) -> str:
    raw = str(raw or "").strip()
    for separator in (",", " "):
        if separator in raw:
            raw = raw.split(separator, 1)[0].strip()
    if "://" in raw:
        parsed = urllib.parse.urlparse(raw)
        return parsed.hostname or raw
    if raw.startswith("[") and "]" in raw:  # bracketed IPv6
        return raw[1 : raw.index("]")]
    if raw.count(":") == 1:
        return raw.split(":", 1)[0]
    return raw


def _params(connector: "Connector") -> Dict[str, str]:
    """Parse the free-form ``additional_parameters`` field into a dict."""

    raw = str(connector.value("additional_parameters", "") or "")
    out: Dict[str, str] = {}
    for chunk in re.split(r"[;&\s]+", raw):
        if "=" in chunk:
            name, _, val = chunk.partition("=")
            name = name.strip().lower()
            if name:
                out[name] = val.strip()
    return out


def _tcp_probe(result: ConnectionTestResult, host: str, port: int, timeout: float) -> bool:
    host = _first_host(host)
    if not host or port < 1 or port > 65535:
        result.add("tcp", False, "no usable host/port to probe")
        return False
    started = time.monotonic()
    try:
        with socket.create_connection((host, port), timeout=timeout):
            pass
    except OSError as error:
        result.add("tcp", False, "%s:%s -> %s" % (host, port, error))
        return False
    result.latency_ms = int((time.monotonic() - started) * 1000)
    result.add("tcp", True, "%s:%s accepted a connection" % (host, port))
    return True


def _elapsed_ms(started: float) -> int:
    return int((time.monotonic() - started) * 1000)


# Per-type handlers --------------------------------------------------------------
# Each handler receives (connector, result, timeout) and returns the result.
# It should call result.finish(...) exactly once.


def _test_mysql(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        import mysql.connector  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "mysql-connector-python")
    started = time.monotonic()
    connection = None
    try:
        connection = mysql.connector.connect(
            host=_first_host(connector.host),
            port=connector.port or 3306,
            user=connector.username,
            password=connector.password,
            database=connector.database or None,
            connection_timeout=int(timeout) or 5,
        )
        result.latency_ms = _elapsed_ms(started)
        result.add("connect", True, "authenticated")
        cursor = connection.cursor()
        cursor.execute("SELECT VERSION()")
        version = str((cursor.fetchone() or [""])[0])
        cursor.close()
        result.add("query", True, "SELECT VERSION() returned a row")
        return result.finish(PASSED, "Connected and authenticated.", version)
    except Exception as error:  # noqa: BLE001 - driver raises many concrete types
        return _classify_db_error(connector, result, error, timeout)
    finally:
        if connection is not None:
            try:
                connection.close()
            except Exception:  # noqa: BLE001
                pass


def _test_pgsql(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        import psycopg  # type: ignore

        driver = "psycopg"
    except ImportError:
        try:
            import psycopg2 as psycopg  # type: ignore

            driver = "psycopg2"
        except ImportError:
            return _driver_missing(connector, result, timeout, "psycopg[binary]")
    params = _params(connector)
    sslmode = params.get("sslmode") or params.get("ssl_mode") or "prefer"
    started = time.monotonic()
    connection = None
    try:
        connection = psycopg.connect(
            host=_first_host(connector.host),
            port=connector.port or 5432,
            user=connector.username,
            password=connector.password,
            dbname=connector.database or None,
            connect_timeout=int(timeout) or 5,
            sslmode=sslmode,
        )
        result.latency_ms = _elapsed_ms(started)
        result.add("connect", True, "authenticated (%s, sslmode=%s)" % (driver, sslmode))
        cursor = connection.cursor()
        cursor.execute("SELECT version()")
        version = str((cursor.fetchone() or [""])[0])
        cursor.close()
        result.add("query", True, "SELECT version() returned a row")
        return result.finish(PASSED, "Connected and authenticated.", version)
    except Exception as error:  # noqa: BLE001
        return _classify_db_error(connector, result, error, timeout)
    finally:
        if connection is not None:
            try:
                connection.close()
            except Exception:  # noqa: BLE001
                pass


def _test_sqlserver(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        import pymssql  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "pymssql")
    started = time.monotonic()
    connection = None
    try:
        connection = pymssql.connect(
            server=_first_host(connector.host),
            port=str(connector.port or 1433),
            user=connector.username,
            password=connector.password,
            database=connector.database or "",
            login_timeout=int(timeout) or 5,
            timeout=int(timeout) or 5,
        )
        result.latency_ms = _elapsed_ms(started)
        result.add("connect", True, "authenticated")
        cursor = connection.cursor()
        cursor.execute("SELECT @@VERSION")
        version = str((cursor.fetchone() or [""])[0]).splitlines()[0]
        cursor.close()
        result.add("query", True, "SELECT @@VERSION returned a row")
        return result.finish(PASSED, "Connected and authenticated.", version)
    except Exception as error:  # noqa: BLE001
        return _classify_db_error(connector, result, error, timeout)
    finally:
        if connection is not None:
            try:
                connection.close()
            except Exception:  # noqa: BLE001
                pass


def _test_oracle(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        import oracledb  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "oracledb")
    service_name = str(connector.value("oracle_service_name", "") or "")
    sid = str(connector.value("oracle_sid", "") or "")
    host = _first_host(connector.host)
    port = connector.port or 1521
    if service_name:
        dsn = oracledb.makedsn(host, port, service_name=service_name)
    elif sid:
        dsn = oracledb.makedsn(host, port, sid=sid)
    else:
        dsn = oracledb.makedsn(host, port, service_name=connector.database or "")
    started = time.monotonic()
    connection = None
    try:
        connection = oracledb.connect(user=connector.username, password=connector.password, dsn=dsn)
        result.latency_ms = _elapsed_ms(started)
        result.add("connect", True, "authenticated (thin mode)")
        cursor = connection.cursor()
        cursor.execute("SELECT 1 FROM dual")
        cursor.fetchone()
        cursor.close()
        result.add("query", True, "SELECT 1 FROM dual returned a row")
        return result.finish(PASSED, "Connected and authenticated.", str(getattr(connection, "version", "")))
    except Exception as error:  # noqa: BLE001
        return _classify_db_error(connector, result, error, timeout)
    finally:
        if connection is not None:
            try:
                connection.close()
            except Exception:  # noqa: BLE001
                pass


def _test_mongodb(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        from pymongo import MongoClient  # type: ignore
        from pymongo.errors import PyMongoError  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "pymongo")
    started = time.monotonic()
    client = None
    try:
        host = str(connector.host or "")
        kwargs: Dict[str, Any] = {"serverSelectionTimeoutMS": int(timeout * 1000) or 5000}
        if connector.username:
            kwargs["username"] = connector.username
            kwargs["password"] = connector.password
        if "://" in host:
            client = MongoClient(host, **kwargs)
        else:
            client = MongoClient(host=_first_host(host) or "localhost", port=connector.port or 27017, **kwargs)
        info = client.admin.command("ping")
        result.latency_ms = _elapsed_ms(started)
        result.add("ping", bool(info.get("ok")), "admin ping")
        build = client.admin.command("buildInfo")
        return result.finish(PASSED, "Connected and authenticated.", str(build.get("version", "")))
    except PyMongoError as error:
        return _classify_db_error(connector, result, error, timeout)
    except Exception as error:  # noqa: BLE001
        return _classify_db_error(connector, result, error, timeout)
    finally:
        if client is not None:
            try:
                client.close()
            except Exception:  # noqa: BLE001
                pass


def _test_redis(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        import redis  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "redis")
    started = time.monotonic()
    client = None
    try:
        client = redis.Redis(
            host=_first_host(connector.host) or "localhost",
            port=connector.port or 6379,
            password=connector.password or None,
            username=connector.username or None,
            socket_connect_timeout=timeout,
            socket_timeout=timeout,
            db=int(connector.database) if str(connector.database).isdigit() else 0,
        )
        client.ping()
        result.latency_ms = _elapsed_ms(started)
        result.add("ping", True, "PONG")
        info = client.info(section="server")
        return result.finish(PASSED, "Connected and authenticated.", str(info.get("redis_version", "")))
    except Exception as error:  # noqa: BLE001
        return _classify_db_error(connector, result, error, timeout)
    finally:
        if client is not None:
            try:
                client.close()
            except Exception:  # noqa: BLE001
                pass


def _test_http(connector: "Connector", result: ConnectionTestResult, timeout: float, kind: str) -> ConnectionTestResult:
    raw = str(connector.host or "").strip()
    if "://" not in raw:
        scheme = "https" if connector.port in (0, 443, 9243) else "http"
        raw = "%s://%s%s" % (scheme, raw, (":%d" % connector.port) if connector.port else "")
    if connector.database and kind == "elasticsearch":
        raw = raw.rstrip("/") + "/"
    started = time.monotonic()
    headers = {"User-Agent": "jobseeker-conntest/1"}
    token = str(connector.value("token", "") or connector.value("api_key", "") or "")
    if token:
        headers["Authorization"] = "Bearer " + token
    context = ssl.create_default_context()
    if _params(connector).get("insecure") in ("1", "true", "yes"):
        context.check_hostname = False
        context.verify_mode = ssl.CERT_NONE
    try:
        request = urllib.request.Request(raw, headers=headers, method="GET")
        with urllib.request.urlopen(request, timeout=timeout, context=context) as response:
            status = response.status
            body = response.read(4096)
        result.latency_ms = _elapsed_ms(started)
        result.add("request", status < 500, "GET %s -> HTTP %s" % (raw, status))
        version = ""
        if kind == "elasticsearch":
            try:
                payload = json.loads(body.decode("utf-8", "replace"))
                version = str(payload.get("version", {}).get("number", ""))
            except ValueError:
                pass
        if status < 500:
            return result.finish(PASSED, "Endpoint responded with HTTP %s." % status, version)
        return result.finish(UNREACHABLE, "Endpoint returned HTTP %s." % status, version)
    except urllib.error.HTTPError as error:
        result.latency_ms = _elapsed_ms(started)
        ok = error.code < 500
        result.add("request", ok, "GET %s -> HTTP %s" % (raw, error.code))
        status = AUTH_FAILED if error.code in (401, 403) else (PASSED if ok else UNREACHABLE)
        return result.finish(status, "Endpoint returned HTTP %s." % error.code)
    except (urllib.error.URLError, ssl.SSLError, http.client.HTTPException, OSError) as error:
        result.add("request", False, str(error))
        return result.finish(UNREACHABLE, "Could not reach the endpoint: %s" % error)


def _test_sftp(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    host = _first_host(connector.host)
    port = connector.port or 22
    try:
        import paramiko  # type: ignore
    except ImportError:
        # Fall back to reading the SSH banner over a raw socket.
        started = time.monotonic()
        try:
            with socket.create_connection((host, port), timeout=timeout) as sock:
                sock.settimeout(timeout)
                banner = sock.recv(256).decode("utf-8", "replace").strip()
            result.latency_ms = _elapsed_ms(started)
            result.add("banner", banner.startswith("SSH-"), banner or "no banner")
            result.server_version = banner
            result.finish(
                DRIVER_MISSING,
                "paramiko is not installed; only the SSH banner was checked. Add paramiko to test authentication.",
                banner,
            )
            return result
        except OSError as error:
            result.add("tcp", False, str(error))
            return result.finish(UNREACHABLE, "Could not reach the SFTP host: %s" % error)
    started = time.monotonic()
    transport = None
    try:
        transport = paramiko.Transport((host, port))
        transport.banner_timeout = timeout
        transport.start_client(timeout=timeout)
        banner = transport.remote_version
        key = str(connector.value("ssh_key", "") or connector.value("private_key", "") or "")
        if key:
            import io

            pkey = None
            for loader in (paramiko.RSAKey, paramiko.Ed25519Key, paramiko.ECDSAKey):
                try:
                    pkey = loader.from_private_key(io.StringIO(key))
                    break
                except Exception:  # noqa: BLE001
                    continue
            transport.auth_publickey(connector.username, pkey)
        else:
            transport.auth_password(connector.username, connector.password)
        result.latency_ms = _elapsed_ms(started)
        result.add("auth", transport.is_authenticated(), "authenticated as %s" % connector.username)
        return result.finish(PASSED, "Connected and authenticated.", banner)
    except paramiko.AuthenticationException as error:
        return result.finish(AUTH_FAILED, "Authentication was rejected: %s" % error)
    except Exception as error:  # noqa: BLE001
        result.add("connect", False, str(error))
        return result.finish(UNREACHABLE, "Could not open an SSH session: %s" % error)
    finally:
        if transport is not None:
            try:
                transport.close()
            except Exception:  # noqa: BLE001
                pass


def _test_s3(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        import boto3  # type: ignore
        from botocore.config import Config  # type: ignore
        from botocore.exceptions import BotoCoreError, ClientError  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "boto3")
    bucket = connector.database or _first_host(connector.host)
    params = _params(connector)
    region = params.get("region") or None
    endpoint = connector.value("endpoint_url", "") or (connector.host if "://" in str(connector.host) else "")
    started = time.monotonic()
    try:
        session_kwargs: Dict[str, Any] = {}
        if connector.value("access_key_id", ""):
            session_kwargs["aws_access_key_id"] = str(connector.value("access_key_id", ""))
            session_kwargs["aws_secret_access_key"] = str(connector.value("secret_access_key", ""))
            if connector.value("session_token", ""):
                session_kwargs["aws_session_token"] = str(connector.value("session_token", ""))
        client = boto3.client(
            "s3",
            region_name=region,
            endpoint_url=endpoint or None,
            config=Config(connect_timeout=timeout, read_timeout=timeout, retries={"max_attempts": 1}),
            **session_kwargs,
        )
        if bucket:
            client.head_bucket(Bucket=bucket)
            result.add("head_bucket", True, "bucket %s is reachable" % bucket)
        else:
            client.list_buckets()
            result.add("list_buckets", True, "listed buckets")
        result.latency_ms = _elapsed_ms(started)
        return result.finish(PASSED, "Authenticated with the object store.")
    except ClientError as error:
        code = error.response.get("Error", {}).get("Code", "")
        if code in ("403", "AccessDenied", "InvalidAccessKeyId", "SignatureDoesNotMatch"):
            return result.finish(AUTH_FAILED, "The object store rejected the credentials (%s)." % code)
        if code in ("404", "NoSuchBucket"):
            return result.finish(UNREACHABLE, "Bucket %s was not found." % bucket)
        return result.finish(UNREACHABLE, "Object store error: %s" % code)
    except (BotoCoreError, OSError) as error:
        return result.finish(UNREACHABLE, "Could not reach the object store: %s" % error)


def _test_azure_blob(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        from azure.storage.blob import BlobServiceClient  # type: ignore
        from azure.core.exceptions import AzureError, ClientAuthenticationError  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "azure-storage-blob")
    account_url = str(connector.host or "")
    if "://" not in account_url:
        account_url = "https://%s.blob.core.windows.net" % account_url
    sas = str(connector.value("sas_token", "") or "")
    connection_string = str(connector.value("connection_string", "") or "")
    started = time.monotonic()
    try:
        if connection_string:
            client = BlobServiceClient.from_connection_string(connection_string, connection_timeout=timeout)
        else:
            credential = sas or connector.value("account_key", "") or None
            client = BlobServiceClient(account_url=account_url, credential=credential, connection_timeout=timeout)
        info = client.get_account_information()
        result.latency_ms = _elapsed_ms(started)
        result.add("account", True, "sku=%s" % info.get("sku_name", "?"))
        return result.finish(PASSED, "Authenticated with Azure Storage.")
    except ClientAuthenticationError as error:
        return result.finish(AUTH_FAILED, "Azure Storage rejected the credentials: %s" % error)
    except (AzureError, OSError) as error:
        return result.finish(UNREACHABLE, "Could not reach Azure Storage: %s" % error)


def _test_gcs(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        from google.cloud import storage  # type: ignore
        from google.api_core.exceptions import GoogleAPICallError, Forbidden, NotFound  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "google-cloud-storage")
    bucket = connector.database or _first_host(connector.host)
    started = time.monotonic()
    try:
        client = storage.Client()
        if bucket:
            client.get_bucket(bucket, timeout=timeout)
            result.add("get_bucket", True, "bucket %s is reachable" % bucket)
        else:
            next(iter(client.list_buckets(max_results=1, timeout=timeout)), None)
            result.add("list_buckets", True, "listed buckets")
        result.latency_ms = _elapsed_ms(started)
        return result.finish(PASSED, "Authenticated with Google Cloud Storage.")
    except Forbidden as error:
        return result.finish(AUTH_FAILED, "GCS rejected the credentials: %s" % error)
    except NotFound:
        return result.finish(UNREACHABLE, "Bucket %s was not found." % bucket)
    except (GoogleAPICallError, OSError) as error:
        return result.finish(UNREACHABLE, "Could not reach GCS: %s" % error)


def _test_snowflake(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        import snowflake.connector  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "snowflake-connector-python")
    params = _params(connector)
    started = time.monotonic()
    connection = None
    try:
        connection = snowflake.connector.connect(
            account=params.get("account") or _first_host(connector.host).split(".")[0],
            user=connector.username,
            password=connector.password,
            database=connector.database or None,
            warehouse=params.get("warehouse"),
            role=params.get("role"),
            login_timeout=int(timeout) or 5,
            network_timeout=int(timeout) or 5,
        )
        cursor = connection.cursor()
        cursor.execute("SELECT CURRENT_VERSION()")
        version = str((cursor.fetchone() or [""])[0])
        cursor.close()
        result.latency_ms = _elapsed_ms(started)
        result.add("query", True, "SELECT CURRENT_VERSION() returned a row")
        return result.finish(PASSED, "Connected and authenticated.", version)
    except Exception as error:  # noqa: BLE001
        return _classify_db_error(connector, result, error, timeout)
    finally:
        if connection is not None:
            try:
                connection.close()
            except Exception:  # noqa: BLE001
                pass


def _test_databricks(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        from databricks import sql as databricks_sql  # type: ignore
    except ImportError:
        return _driver_missing(connector, result, timeout, "databricks-sql-connector")
    params = _params(connector)
    http_path = params.get("http_path") or str(connector.value("http_path", "") or "")
    token = str(connector.value("token", "") or connector.password or "")
    started = time.monotonic()
    connection = None
    try:
        connection = databricks_sql.connect(
            server_hostname=_first_host(connector.host),
            http_path=http_path,
            access_token=token,
            _socket_timeout=int(timeout) or 5,
        )
        cursor = connection.cursor()
        cursor.execute("SELECT 1")
        cursor.fetchone()
        cursor.close()
        result.latency_ms = _elapsed_ms(started)
        result.add("query", True, "SELECT 1 returned a row")
        return result.finish(PASSED, "Connected and authenticated.")
    except Exception as error:  # noqa: BLE001
        return _classify_db_error(connector, result, error, timeout)
    finally:
        if connection is not None:
            try:
                connection.close()
            except Exception:  # noqa: BLE001
                pass


def _test_kafka(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        from kafka import KafkaAdminClient  # type: ignore
        from kafka.errors import KafkaError  # type: ignore
    except ImportError:
        if _tcp_probe(result, connector.host, connector.port or 9092, timeout):
            return result.finish(
                DRIVER_MISSING,
                "kafka-python is not installed; only TCP reachability was checked.",
            )
        return result.finish(UNREACHABLE, "Could not reach the Kafka broker.")
    started = time.monotonic()
    admin = None
    try:
        servers = connector.host if "," in str(connector.host) else "%s:%s" % (_first_host(connector.host), connector.port or 9092)
        admin = KafkaAdminClient(bootstrap_servers=servers, request_timeout_ms=int(timeout * 1000) or 5000)
        admin.list_topics()
        result.latency_ms = _elapsed_ms(started)
        result.add("metadata", True, "fetched broker metadata")
        return result.finish(PASSED, "Connected to the Kafka cluster.")
    except KafkaError as error:
        return result.finish(UNREACHABLE, "Kafka error: %s" % error)
    finally:
        if admin is not None:
            try:
                admin.close()
            except Exception:  # noqa: BLE001
                pass


def _test_rabbitmq(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    try:
        import pika  # type: ignore
    except ImportError:
        if _tcp_probe(result, connector.host, connector.port or 5672, timeout):
            return result.finish(DRIVER_MISSING, "pika is not installed; only TCP reachability was checked.")
        return result.finish(UNREACHABLE, "Could not reach the RabbitMQ broker.")
    started = time.monotonic()
    connection = None
    try:
        credentials = pika.PlainCredentials(connector.username or "guest", connector.password or "guest")
        parameters = pika.ConnectionParameters(
            host=_first_host(connector.host) or "localhost",
            port=connector.port or 5672,
            virtual_host=connector.database or "/",
            credentials=credentials,
            socket_timeout=timeout,
            blocked_connection_timeout=timeout,
        )
        connection = pika.BlockingConnection(parameters)
        result.latency_ms = _elapsed_ms(started)
        result.add("connect", connection.is_open, "opened an AMQP connection")
        return result.finish(PASSED, "Connected and authenticated.")
    except pika.exceptions.ProbableAuthenticationError as error:
        return result.finish(AUTH_FAILED, "RabbitMQ rejected the credentials: %s" % error)
    except Exception as error:  # noqa: BLE001
        result.add("connect", False, str(error))
        return result.finish(UNREACHABLE, "Could not open an AMQP connection: %s" % error)
    finally:
        if connection is not None and connection.is_open:
            try:
                connection.close()
            except Exception:  # noqa: BLE001
                pass


def _driver_missing(
    connector: "Connector", result: ConnectionTestResult, timeout: float, package: str
) -> ConnectionTestResult:
    reachable = _tcp_probe(result, connector.host, connector.port, timeout)
    message = "The %s client is not installed on this worker; only TCP reachability was checked." % package
    return result.finish(DRIVER_MISSING if reachable else UNREACHABLE, message)


def _classify_db_error(
    connector: "Connector", result: ConnectionTestResult, error: Exception, timeout: float
) -> ConnectionTestResult:
    text = _sanitize(error)
    lowered = text.lower()
    if any(token in lowered for token in ("access denied", "authentication failed", "password authentication", "login failed", "auth", "not authorized", "permission denied")):
        result.add("connect", False, text)
        return result.finish(AUTH_FAILED, "The server rejected the credentials.")
    if any(token in lowered for token in ("unknown database", "does not exist", "no such database", "invalid catalog")):
        result.add("connect", False, text)
        return result.finish(UNREACHABLE, "Authenticated, but the target database/schema was not found.")
    result.add("connect", False, text)
    return result.finish(UNREACHABLE, "Could not connect: %s" % text)


def _test_git(connector: "Connector", result: ConnectionTestResult, timeout: float) -> ConnectionTestResult:
    """Verify Git repository access through JobSeeker's secret-safe helper."""

    repository_url = str(connector.database or "").strip()
    if not repository_url:
        result.add("repository", False, "no test repository URL configured")
        return result.finish(
            UNSUPPORTED,
            "Set the connector resource to a repository URL to verify Git authentication.",
        )

    helper = shutil.which("jobseeker-git")
    connector_root = str(getattr(connector, "runtime_directory", "") or "")
    connector_directory = os.path.join(connector_root, connector.key) if connector_root else ""
    if not helper or not connector_directory or not os.path.isdir(connector_directory):
        result.add("helper", False, "secure Git helper or connector directory unavailable")
        return result.finish(
            DRIVER_MISSING,
            "The Jenkins worker does not have the secure JobSeeker Git helper.",
        )

    started = time.monotonic()
    try:
        completed = subprocess.run(
            [helper, "ls-remote", "--connector-dir", connector_directory, "--", repository_url],
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
            text=True,
            timeout=timeout,
            check=False,
        )
    except subprocess.TimeoutExpired:
        result.latency_ms = int((time.monotonic() - started) * 1000)
        result.add("git", False, "repository check timed out")
        return result.finish(UNREACHABLE, "The Git provider did not respond before the timeout.")
    except OSError:
        result.add("helper", False, "secure Git helper could not be started")
        return result.finish(DRIVER_MISSING, "The secure JobSeeker Git helper could not be started.")

    result.latency_ms = int((time.monotonic() - started) * 1000)
    if completed.returncode == 0:
        result.add("git", True, "repository HEAD is readable")
        return result.finish(PASSED, "Git repository authentication and read access succeeded.")

    error_text = str(completed.stderr or "").lower()
    auth_failure = any(token in error_text for token in (
        "authentication failed",
        "permission denied",
        "access denied",
        "could not read username",
        "repository not found",
        "access rights",
    ))
    result.add("git", False, "repository access was denied" if auth_failure else "repository check failed")
    return result.finish(
        AUTH_FAILED if auth_failure else UNREACHABLE,
        "Git credentials were rejected or repository access was denied."
        if auth_failure else "The Git repository could not be reached.",
    )


_HANDLERS = {
    "mysql": _test_mysql,
    "mariadb": _test_mysql,
    "pgsql": _test_pgsql,
    "postgres": _test_pgsql,
    "postgresql": _test_pgsql,
    "sqlserver": _test_sqlserver,
    "mssql": _test_sqlserver,
    "oracle_service": _test_oracle,
    "oracle_sid": _test_oracle,
    "oracle": _test_oracle,
    "mongodb": _test_mongodb,
    "redis": _test_redis,
    "sftp": _test_sftp,
    "aws_s3": _test_s3,
    "s3": _test_s3,
    "azure_blob": _test_azure_blob,
    "azure_data_lake": _test_azure_blob,
    "gcs": _test_gcs,
    "snowflake": _test_snowflake,
    "databricks": _test_databricks,
    "kafka": _test_kafka,
    "rabbitmq": _test_rabbitmq,
    "amqp": _test_rabbitmq,
    "git_repository": _test_git,
}


_NO_ENDPOINT_TYPES = {"generic_secret"}


def test_connector(connector: "Connector", timeout: float = 5.0) -> ConnectionTestResult:
    """Run a live handshake for ``connector`` and return a structured result."""

    connector_type = str(getattr(connector, "type", "") or "").strip().lower()
    result = ConnectionTestResult(
        connector=str(getattr(connector, "key", "")),
        type=connector_type or "unknown",
        environment=str(getattr(connector, "environment", "") or ""),
        job=str(getattr(connector, "job", "") or ""),
    )
    try:
        timeout = max(1.0, float(timeout))
    except (TypeError, ValueError):
        timeout = 5.0

    if connector_type in _NO_ENDPOINT_TYPES:
        readable = bool(getattr(connector, "secrets", {}) or connector.value("auth_type", "") == "none")
        result.add("secret", readable, "resolved secret values" if readable else "no secret values resolved")
        return result.finish(
            PASSED if readable else UNREACHABLE,
            "This connector has no endpoint; the secret bundle was checked instead.",
        )

    if connector_type == "elasticsearch":
        handler = lambda c, r, t: _test_http(c, r, t, "elasticsearch")  # noqa: E731
    elif connector_type == "http_api":
        handler = lambda c, r, t: _test_http(c, r, t, "http_api")  # noqa: E731
    else:
        handler = _HANDLERS.get(connector_type)

    if handler is None:
        reachable = _tcp_probe(result, connector.host, connector.port, timeout)
        return result.finish(
            UNSUPPORTED if not reachable else PASSED,
            "No protocol test is implemented for %r; checked TCP reachability only." % connector_type,
        )

    try:
        return handler(connector, result, timeout)
    except Exception as error:  # noqa: BLE001 - never let a driver crash the test
        result.add("handler", False, _sanitize(error))
        return result.finish(UNREACHABLE, "The connection test raised an unexpected error: %s" % _sanitize(error))


# CLI -------------------------------------------------------------------------


def main(argv: Optional[List[str]] = None) -> int:
    parser = argparse.ArgumentParser(
        prog="python -m jobseeker.conntest",
        description="Run a live connection test for a materialized JobSeeker connector.",
    )
    parser.add_argument("--directory", default=None, help="Connector runtime directory (default: $JOBSEEKER_CONNECTORS_DIR)")
    parser.add_argument("--key", required=True, help="Connector key to test")
    parser.add_argument("--timeout", type=float, default=5.0, help="Per-check timeout in seconds")
    parser.add_argument("--json", action="store_true", help="Emit a single JSON line")
    arguments = parser.parse_args(argv)

    from jobseeker import ConnectorCatalog, JobSeekerError  # local import avoids a cycle

    try:
        catalog = ConnectorCatalog(directory=arguments.directory)
        connector = catalog.resolve(arguments.key, required=True)
    except JobSeekerError as error:
        payload = ConnectionTestResult(connector=arguments.key, type="unknown").finish(UNREACHABLE, str(error))
        print(payload.to_json() if arguments.json else payload.message, file=sys.stderr)
        return 2

    result = test_connector(connector, timeout=arguments.timeout)
    result.secret_backend = str((getattr(connector, "config", {}) or {}).get("secret_backend", ""))
    if arguments.json:
        print(result.to_json())
    else:
        print("%s: %s (%s)" % (result.connector, result.status, result.message))
        for check in result.checks:
            print("  [%s] %s - %s" % ("ok" if check.ok else "!!", check.name, check.detail))
    return 0 if result.ok else 1


if __name__ == "__main__":  # pragma: no cover
    raise SystemExit(main())
