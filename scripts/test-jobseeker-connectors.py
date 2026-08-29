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


def main():
    test_azure_key_vault_backend()
    test_aws_secrets_manager_backend()
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
