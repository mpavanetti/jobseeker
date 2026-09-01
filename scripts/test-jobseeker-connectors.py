#!/usr/bin/env python3
import importlib.util
import json
import os
import stat
import subprocess
import sys
import tempfile
import threading
import types
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

sys.dont_write_bytecode = True
module_path = Path(__file__).resolve().parents[1] / "application" / "third_party" / "python" / "jobseeker_sdk" / "src" / "jobseeker" / "__init__.py"
module_spec = importlib.util.spec_from_file_location("jobseeker", module_path)
assert module_spec is not None and module_spec.loader is not None
jobseeker = importlib.util.module_from_spec(module_spec)
sys.modules["jobseeker"] = jobseeker
module_spec.loader.exec_module(jobseeker)
ConnectorCatalog = jobseeker.ConnectorCatalog
JobSeeker = jobseeker.JobSeeker
JobSeekerError = jobseeker.JobSeekerError
materialize_connectors = jobseeker.materialize_connectors


class CatalogHandler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.headers.get("Authorization") != "Bearer test-token":
            self.send_response(401)
            self.end_headers()
            return
        payload = {
            "schema_version": 1,
            "generated_at": "2026-08-28T00:00:00Z",
            "connectors": [
                {
                    "key": "warehouse",
                    "type": "pgsql",
                    "environment": "DEV",
                    "job": "load-orders",
                    "description": "Orders warehouse",
                    "config": {"host": "warehouse.internal", "port": 5432, "database": "orders"},
                    "secret": {"backend": "local", "values": {"username": "etl", "password": "local-secret"}},
                },
                {
                    "key": "reporting",
                    "type": "mysql",
                    "environment": "ALL",
                    "job": "*",
                    "config": {"host": "reporting.internal", "port": 3306, "database": "reports"},
                    "secret": {
                        "backend": "environment",
                        "reference": {
                            "variables": {
                                "username": "REPORTING_USER",
                                "password": "REPORTING_PASSWORD",
                                "api_key": "REPORTING_API_KEY",
                            }
                        },
                    },
                },
            ],
        }
        if self.path == "/invalid-payload":
            payload = ["not", "a", "catalog"]
        elif self.path == "/malformed-connector":
            payload = {"schema_version": 1, "connectors": [{"type": "generic", "config": {}}]}
        elif self.path == "/duplicate-connectors":
            payload = {
                "schema_version": 1,
                "connectors": [
                    {"key": "duplicate", "type": "generic", "config": {}, "secret": {"backend": "local", "values": {}}},
                    {"key": "duplicate", "type": "generic", "config": {}, "secret": {"backend": "local", "values": {}}},
                ],
            }
        elif self.path == "/resolution-failure":
            payload = {
                "schema_version": 1,
                "generated_at": "2026-08-28T00:00:00Z",
                "connectors": [
                    {
                        "key": "would-be-partial",
                        "type": "generic",
                        "config": {"host": "partial.invalid"},
                        "secret": {"backend": "local", "values": {"token": "must-not-be-written"}},
                    },
                    {
                        "key": "missing-worker-secret",
                        "type": "generic",
                        "config": {},
                        "secret": {
                            "backend": "environment",
                            "reference": {"variables": {"token": "JOBSEEKER_TEST_MISSING_SECRET"}},
                        },
                    },
                ],
            }
        body = json.dumps(payload).encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format, *args):
        pass


def mode(path):
    return stat.S_IMODE(os.stat(path).st_mode)


def test_azure_key_vault_backend():
    calls = {"secrets": []}

    class Credential:
        kind = "default"

        def __init__(self, **options):
            calls["credential_kind"] = self.kind
            calls["credential_options"] = options

        def close(self):
            calls["credential_closed"] = True

    class ManagedIdentityCredential(Credential):
        kind = "managed_identity"

    class WorkloadIdentityCredential(Credential):
        kind = "workload_identity"

    class EnvironmentCredential(Credential):
        kind = "environment"

    class SecretClient:
        def __init__(self, vault_url, credential):
            calls["vault_url"] = vault_url
            calls["credential"] = credential

        def get_secret(self, name):
            calls["secrets"].append(name)
            return types.SimpleNamespace(value={"etl-user": "azure-user", "etl-token": "azure-secret"}[name])

        def close(self):
            calls["client_closed"] = True

    modules = {
        "azure": types.ModuleType("azure"),
        "azure.identity": types.ModuleType("azure.identity"),
        "azure.keyvault": types.ModuleType("azure.keyvault"),
        "azure.keyvault.secrets": types.ModuleType("azure.keyvault.secrets"),
    }
    modules["azure.identity"].DefaultAzureCredential = Credential
    modules["azure.identity"].EnvironmentCredential = EnvironmentCredential
    modules["azure.identity"].ManagedIdentityCredential = ManagedIdentityCredential
    modules["azure.identity"].WorkloadIdentityCredential = WorkloadIdentityCredential
    modules["azure.keyvault.secrets"].SecretClient = SecretClient
    previous = {name: sys.modules.get(name) for name in modules}
    sys.modules.update(modules)
    try:
        values = jobseeker._connector_secret_values(
            {
                "key": "azure-warehouse",
                "secret": {
                    "backend": "azure_key_vault",
                    "reference": {
                        "vault_url": "https://example.vault.azure.net",
                        "auth_mode": "managed_identity",
                        "secrets": {"username": "etl-user", "access_token": "etl-token"},
                        "managed_identity_client_id": "00000000-0000-0000-0000-000000000001",
                    },
                },
            }
        )
        assert values == {"username": "azure-user", "access_token": "azure-secret"}
        assert calls["credential_kind"] == "managed_identity"
        assert calls["credential_options"] == {"client_id": "00000000-0000-0000-0000-000000000001"}
        assert calls["vault_url"] == "https://example.vault.azure.net"
        assert calls["secrets"] == ["etl-user", "etl-token"]
        assert calls["client_closed"] and calls["credential_closed"]
        for auth_mode, expected_kind, expected_options in (
            ("default", "default", {"managed_identity_client_id": "00000000-0000-0000-0000-000000000001"}),
            ("workload_identity", "workload_identity", {"client_id": "00000000-0000-0000-0000-000000000001"}),
            ("environment", "environment", {}),
        ):
            calls["secrets"] = []
            jobseeker._connector_secret_values(
                {
                    "key": "azure-auth-test",
                    "secret": {
                        "backend": "azure_key_vault",
                        "reference": {
                            "vault_url": "https://example.vault.azure.net",
                            "auth_mode": auth_mode,
                            "secrets": {"username": "etl-user"},
                            "managed_identity_client_id": "00000000-0000-0000-0000-000000000001",
                        },
                    },
                }
            )
            assert calls["credential_kind"] == expected_kind
            assert calls["credential_options"] == expected_options
    finally:
        for name, module in previous.items():
            if module is None:
                sys.modules.pop(name, None)
            else:
                sys.modules[name] = module


def test_aws_secrets_manager_backend():
    calls = {}

    class SecretsManagerClient:
        def get_secret_value(self, SecretId):
            calls["secret_id"] = SecretId
            return {"SecretString": json.dumps({"credentials": {"etl_user": "aws-user", "etl_token": "aws-secret"}})}

        def close(self):
            calls["closed"] = True

    boto3 = types.ModuleType("boto3")

    class Session:
        def __init__(self, profile_name, region_name):
            calls["profile"] = profile_name
            calls["region"] = region_name

        def client(self, service):
            calls["service"] = service
            return SecretsManagerClient()

    boto3.Session = Session

    def default_client(service, region_name):
        calls["service"] = service
        calls["region"] = region_name
        calls["default_client"] = True
        return SecretsManagerClient()

    boto3.client = default_client
    previous = sys.modules.get("boto3")
    sys.modules["boto3"] = boto3
    try:
        values = jobseeker._connector_secret_values(
            {
                "key": "aws-warehouse",
                "secret": {
                    "backend": "aws_secrets_manager",
                    "reference": {
                        "region": "us-east-1",
                        "secret_id": "prod/warehouse",
                        "auth_mode": "profile",
                        "profile_name": "etl-profile",
                        "fields": {"username": "credentials.etl_user", "access_token": "credentials.etl_token"},
                    },
                },
            }
        )
        assert values == {"username": "aws-user", "access_token": "aws-secret"}
        assert calls == {
            "service": "secretsmanager",
            "region": "us-east-1",
            "profile": "etl-profile",
            "secret_id": "prod/warehouse",
            "closed": True,
        }
        calls.clear()
        values = jobseeker._connector_secret_values(
            {
                "key": "aws-default",
                "secret": {
                    "backend": "aws_secrets_manager",
                    "reference": {
                        "region": "us-east-1",
                        "secret_id": "prod/warehouse",
                        "auth_mode": "web_identity",
                        "fields": {"access_token": "credentials.etl_token"},
                    },
                },
            }
        )
        assert values == {"access_token": "aws-secret"}
        assert calls["default_client"] is True
    finally:
        if previous is None:
            sys.modules.pop("boto3", None)
        else:
            sys.modules["boto3"] = previous


def test_connection_tester():
    conntest = jobseeker.conntest
    Connector = jobseeker.Connector

    # generic_secret: no endpoint, secret bundle present -> passed
    result = Connector(
        key="vault", type="generic_secret", environment="DEV", job="*", config={}, secrets={"token": "abc"}
    ).test(timeout=1)
    assert result.status == conntest.PASSED and result.ok, result.to_dict()

    # unknown/undriven type against a dead port -> unreachable, never raises
    result = Connector(
        key="bus", type="kafka", environment="DEV", job="*", config={"host": "127.0.0.1", "port": 1}, secrets={}
    ).test(timeout=1)
    assert result.status in (conntest.UNREACHABLE, conntest.DRIVER_MISSING) and not result.ok, result.to_dict()

    # driver_missing degrades to a TCP probe against a listening socket
    listener = ThreadingHTTPServer(("127.0.0.1", 0), BaseHTTPRequestHandler)
    try:
        port = listener.server_address[1]
        saved = sys.modules.pop("redis", None)
        sys.modules["redis"] = None  # force ImportError inside the handler
        try:
            result = Connector(
                key="cache", type="redis", environment="DEV", job="*",
                config={"host": "127.0.0.1", "port": port}, secrets={},
            ).test(timeout=2)
        finally:
            if saved is not None:
                sys.modules["redis"] = saved
            else:
                sys.modules.pop("redis", None)
        assert result.status == conntest.DRIVER_MISSING, result.to_dict()
        assert any(check.name == "tcp" and check.ok for check in result.checks), result.to_dict()
    finally:
        listener.server_close()

    # secrets are scrubbed from messages / check details
    scrubbed = conntest.ConnectionTestResult(connector="x", type="mysql")
    scrubbed.add("connect", False, "Access denied for user 'etl' (using password: YES) token=supersecret")
    assert "supersecret" not in scrubbed.to_json()

    # HTTP endpoint check via a local server
    http_server = ThreadingHTTPServer(("127.0.0.1", 0), _OkHandler)
    http_thread = threading.Thread(target=http_server.serve_forever, daemon=True)
    http_thread.start()
    try:
        http_port = http_server.server_address[1]
        result = Connector(
            key="api", type="http_api", environment="DEV", job="*",
            config={"host": "http://127.0.0.1:%d/health" % http_port, "port": http_port}, secrets={},
        ).test(timeout=3)
        assert result.status == conntest.PASSED and result.ok, result.to_dict()
    finally:
        http_server.shutdown()
        http_server.server_close()

    # CLI: python -m jobseeker.conntest --json against a materialized catalog dir
    with tempfile.TemporaryDirectory() as directory:
        os.makedirs(os.path.join(directory, "vault-key"))
        with open(os.path.join(directory, "vault-key", "token"), "w", encoding="utf-8") as handle:
            handle.write("abc")
        manifest = {
            "schema_version": 1,
            "connectors": [
                {
                    "key": "vault-key",
                    "type": "generic_secret",
                    "environment": "DEV",
                    "job": "*",
                    "config": {},
                    "secret_files": {"token": "vault-key/token"},
                }
            ],
        }
        with open(os.path.join(directory, "connectors.json"), "w", encoding="utf-8") as handle:
            json.dump(manifest, handle)
        completed = subprocess.run(
            [sys.executable, "-m", "jobseeker.conntest", "--directory", directory, "--key", "vault-key", "--json"],
            capture_output=True, text=True,
            env=dict(os.environ, PYTHONPATH=str(module_path.parents[1])),
        )
        assert completed.returncode == 0, completed.stderr
        payload = json.loads(completed.stdout.strip().splitlines()[-1])
        assert payload["connector"] == "vault-key" and payload["ok"] is True, payload


class _OkHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200)
        self.end_headers()
        self.wfile.write(b"ok")

    def log_message(self, *args):
        pass


def main():
    test_azure_key_vault_backend()
    test_aws_secrets_manager_backend()
    test_connection_tester()
    server = ThreadingHTTPServer(("127.0.0.1", 0), CatalogHandler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    previous_user = os.environ.get("REPORTING_USER")
    previous_password = os.environ.get("REPORTING_PASSWORD")
    previous_api_key = os.environ.get("REPORTING_API_KEY")
    os.environ["REPORTING_USER"] = "report-reader"
    os.environ["REPORTING_PASSWORD"] = "environment-secret"
    os.environ["REPORTING_API_KEY"] = "environment-api-key"

    try:
        with tempfile.TemporaryDirectory(prefix="jobseeker-connectors-") as workspace:
            directory = os.path.join(workspace, "runtime")
            endpoint = "http://127.0.0.1:%d/connector-runtime" % server.server_port
            manifest_path = materialize_connectors(
                directory=directory,
                environment="DEV",
                job="load-orders",
                api_url=endpoint,
                api_token="test-token",
            )

            assert mode(directory) == 0o700
            assert mode(manifest_path) == 0o600
            source_variables_path = os.path.join(directory, ".source-environment-variables")
            assert mode(source_variables_path) == 0o600
            with open(source_variables_path, "r", encoding="utf-8") as stream:
                source_variables = set(stream.read().splitlines())
            assert source_variables == {"REPORTING_USER", "REPORTING_PASSWORD", "REPORTING_API_KEY"}
            assert mode(os.path.join(directory, "warehouse", "password")) == 0o600
            helper_path = os.path.join(directory, "jobseeker-connector")
            assert mode(helper_path) == 0o700
            with open(manifest_path, "r", encoding="utf-8") as stream:
                public_manifest = stream.read()
            assert "local-secret" not in public_manifest
            assert "environment-secret" not in public_manifest

            catalog = ConnectorCatalog(directory)
            warehouse = catalog.resolve("warehouse")
            assert warehouse.host == "warehouse.internal"
            assert warehouse.port == 5432
            assert warehouse.username == "etl"
            assert warehouse.password == "local-secret"
            assert "secrets" not in warehouse.as_dict()

            reporting = catalog.resolve("reporting")
            assert reporting.username == "report-reader"
            assert reporting.password == "environment-secret"
            assert reporting.value("api_key", required=True) == "environment-api-key"

            subprocess.run(
                [
                    helper_path,
                    "exec",
                    "warehouse",
                    "--",
                    "sh",
                    "-c",
                    'test "$JOBSEEKER_CONNECTOR_HOST" = warehouse.internal && test "$JOBSEEKER_CONNECTOR_USERNAME" = etl && test "$JOBSEEKER_CONNECTOR_PASSWORD" = local-secret',
                ],
                check=True,
                env=dict(os.environ, JOBSEEKER_CONNECTORS_DIR=directory),
            )

            seeker = JobSeeker(environment="DEV", job="load-orders", install_signal_handlers=False)
            seeker._connector_catalog = catalog
            assert seeker.connector("warehouse").database == "orders"
            try:
                catalog.resolve("missing")
                raise AssertionError("missing required connector should fail")
            except JobSeekerError as error:
                assert "warehouse" in str(error)

            stable_directory = os.path.join(workspace, "stable-runtime")
            os.makedirs(stable_directory)
            stable_marker = os.path.join(stable_directory, "last-good-catalog")
            with open(stable_marker, "w", encoding="utf-8") as stream:
                stream.write("preserved")
            os.environ.pop("JOBSEEKER_TEST_MISSING_SECRET", None)
            try:
                materialize_connectors(
                    directory=stable_directory,
                    environment="DEV",
                    job="load-orders",
                    api_url="http://127.0.0.1:%d/resolution-failure" % server.server_port,
                    api_token="test-token",
                )
                raise AssertionError("missing environment secret should fail materialization")
            except JobSeekerError as error:
                assert "JOBSEEKER_TEST_MISSING_SECRET" in str(error)
            assert os.path.isfile(stable_marker)
            assert not os.path.exists(os.path.join(stable_directory, "would-be-partial"))

            try:
                materialize_connectors(
                    directory=os.path.join(workspace, "invalid-runtime"),
                    environment="DEV",
                    job="load-orders",
                    api_url="http://127.0.0.1:%d/invalid-payload" % server.server_port,
                    api_token="test-token",
                )
                raise AssertionError("non-object catalog should fail materialization")
            except JobSeekerError as error:
                assert "response is invalid" in str(error)

            for path, expected_error in (
                ("malformed-connector", "missing a key"),
                ("duplicate-connectors", "duplicate connector key"),
            ):
                try:
                    materialize_connectors(
                        directory=stable_directory,
                        environment="DEV",
                        job="load-orders",
                        api_url="http://127.0.0.1:%d/%s" % (server.server_port, path),
                        api_token="test-token",
                    )
                    raise AssertionError("invalid connector entries should fail materialization")
                except JobSeekerError as error:
                    assert expected_error in str(error).lower()
                assert os.path.isfile(stable_marker)

            original_open = jobseeker.os.open

            def fail_catalog_write(path, flags, mode=0o777, *args, **kwargs):
                if str(path).endswith("/warehouse/host"):
                    raise OSError("simulated catalog write failure")
                return original_open(path, flags, mode, *args, **kwargs)

            jobseeker.os.open = fail_catalog_write
            try:
                try:
                    materialize_connectors(
                        directory=stable_directory,
                        environment="DEV",
                        job="load-orders",
                        api_url=endpoint,
                        api_token="test-token",
                    )
                    raise AssertionError("catalog write failure should fail materialization")
                except OSError as error:
                    assert "simulated catalog write failure" in str(error)
            finally:
                jobseeker.os.open = original_open
            assert os.path.isfile(stable_marker)
            assert not any(".stable-runtime." in name for name in os.listdir(workspace))

            original_replace = jobseeker.os.replace

            def fail_catalog_install(source, destination):
                if ".stable-runtime.tmp-" in source and destination == stable_directory:
                    raise OSError("simulated catalog install failure")
                return original_replace(source, destination)

            jobseeker.os.replace = fail_catalog_install
            try:
                try:
                    materialize_connectors(
                        directory=stable_directory,
                        environment="DEV",
                        job="load-orders",
                        api_url=endpoint,
                        api_token="test-token",
                    )
                    raise AssertionError("catalog install failure should fail materialization")
                except OSError as error:
                    assert "simulated catalog install failure" in str(error)
            finally:
                jobseeker.os.replace = original_replace
            assert os.path.isfile(stable_marker)
            assert not any(".stable-runtime." in name for name in os.listdir(workspace))
    finally:
        server.shutdown()
        server.server_close()
        if previous_user is None:
            os.environ.pop("REPORTING_USER", None)
        else:
            os.environ["REPORTING_USER"] = previous_user
        if previous_password is None:
            os.environ.pop("REPORTING_PASSWORD", None)
        else:
            os.environ["REPORTING_PASSWORD"] = previous_password
        if previous_api_key is None:
            os.environ.pop("REPORTING_API_KEY", None)
        else:
            os.environ["REPORTING_API_KEY"] = previous_api_key

    print("JobSeeker connector SDK tests passed.")


if __name__ == "__main__":
    main()
