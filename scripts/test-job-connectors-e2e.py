#!/usr/bin/env python3
import html
import http.cookiejar
import importlib.util
import json
import os
import re
import subprocess
import sys
import tempfile
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

sys.dont_write_bytecode = True

BASE_URL = os.environ.get("JOBSEEKER_E2E_URL", "http://localhost").rstrip("/")
ADMIN_EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
ADMIN_PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")
API_TOKEN = os.environ.get("JOBSEEKER_CONNECTOR_API_TOKEN", "jobseeker-local-connector-token")
REPOSITORY_ROOT = Path(__file__).resolve().parents[1]


class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def csrf_token(self):
        for cookie in self.cookies:
            if cookie.name == "csrf_cookie_name":
                return cookie.value
        raise AssertionError("CSRF cookie is missing")

    def request(self, path, method="GET", fields=None, headers=None, csrf=False, expected=(200,)):
        values = dict(fields or {})
        if csrf:
            values["csrf_test_name"] = self.csrf_token()
        data = urllib.parse.urlencode(values).encode("utf-8") if fields is not None else None
        request = urllib.request.Request(
            BASE_URL + path,
            data=data,
            headers=headers or {},
            method=method,
        )
        try:
            with self.opener.open(request, timeout=15) as response:
                status = response.status
                body = response.read().decode("utf-8", "replace")
                response_headers = response.headers
        except urllib.error.HTTPError as error:
            status = error.code
            body = error.read().decode("utf-8", "replace")
            response_headers = error.headers
        if status not in expected:
            raise AssertionError("%s %s returned %s: %s" % (method, path, status, body[:500]))
        return status, body, response_headers


def connector_fields(key, job_name, **overrides):
    fields = {
        "connector_key": key,
        "job_name": job_name,
        "db_type": "mysql",
        "auth_type": "username_password",
        "secret_backend": "local",
        "address": "mariadb",
        "port": "3306",
        "schema": "jobseeker",
        "description": key,
        "additional_parameters": "",
        "oracle_ServiceName": "",
        "oracle_sid": "",
        "login": "e2e-user",
        "password": "e2e-password",
        "local_secret_fields": "",
        "is_active": "1",
    }
    fields.update(overrides)
    return fields


def save_connector(browser, environment, fields, connector_id=None, expected_message="Connector saved."):
    values = dict(fields)
    if connector_id is not None:
        values["id"] = str(connector_id)
        action = "UpdateDbSettings"
    else:
        action = "InsertDbSettings"
    _, body, _ = browser.request(
        "/dbSettings/%s?environment=%s" % (action, urllib.parse.quote(environment)),
        method="POST",
        fields=values,
        csrf=True,
    )
    if expected_message not in body:
        raise AssertionError("Connector save did not report %r" % expected_message)
    return body


def connector_rows(browser):
    _, body, _ = browser.request("/dbSettings?environment=ALL")
    rows = []
    for row_html in re.findall(r"<tr(?:\s[^>]*)?>(.*?)</tr>", body, re.DOTALL | re.IGNORECASE):
        key_match = re.search(r'<td class="connector-key">(.*?)</td>', row_html, re.DOTALL | re.IGNORECASE)
        id_match = re.search(r'data-id="([0-9]+)"', row_html)
        if key_match and id_match:
            key = html.unescape(re.sub(r"<[^>]+>", "", key_match.group(1))).strip()
            rows.append((key, int(id_match.group(1))))
    return rows


def connector_id(browser, key):
    matches = [row_id for row_key, row_id in connector_rows(browser) if row_key == key]
    if len(matches) != 1:
        raise AssertionError("Expected one connector %s, found %d" % (key, len(matches)))
    return matches[0]


def runtime_catalog(browser, environment, job_name, expected=(200,), token=API_TOKEN):
    headers = {"Authorization": "Bearer " + token, "Content-Type": "application/x-www-form-urlencoded"}
    status, body, response_headers = browser.request(
        "/connector-runtime",
        method="POST",
        fields={"environment": environment, "job_name": job_name},
        headers=headers,
        expected=expected,
    )
    payload = json.loads(body)
    return status, payload, response_headers


def test_connector(browser, connector_id_value, environment, expected=(200,), csrf=True, mode="quick"):
    # The default connection test now runs a real handshake on a Jenkins worker;
    # this matrix exercises the fast in-process readiness probe (mode=quick).
    status, body, _ = browser.request(
        "/dbSettings/testConnector?environment=" + urllib.parse.quote(environment),
        method="POST",
        fields={"id": str(connector_id_value), "mode": mode},
        csrf=csrf,
        expected=expected,
    )
    return status, json.loads(body) if body.startswith("{") else body


def delete_connector(browser, connector_id_value):
    _, body, _ = browser.request(
        "/dbSettings/deleteSetting?environment=ALL",
        method="POST",
        fields={"userId": str(connector_id_value)},
        csrf=True,
    )
    payload = json.loads(body)
    if payload.get("status") is not True:
        raise AssertionError("Connector deletion failed: %s" % body)


def connector_map(payload):
    return {item["key"]: item for item in payload["connectors"]}


def load_sdk():
    module_path = REPOSITORY_ROOT / "application" / "third_party" / "python" / "jobseeker_sdk" / "src" / "jobseeker" / "__init__.py"
    module_spec = importlib.util.spec_from_file_location("jobseeker_e2e", module_path)
    assert module_spec is not None and module_spec.loader is not None
    module = importlib.util.module_from_spec(module_spec)
    sys.modules[module_spec.name] = module
    module_spec.loader.exec_module(module)
    return module


def test_jenkins_worker_materialization(run_id, key, job_name):
    runtime_directory = "/tmp/jobseeker-connectors-e2e-" + run_id
    compose = ["docker", "compose", "exec", "-T", "jenkins"]
    subprocess.run(compose + ["rm", "-rf", runtime_directory], cwd=REPOSITORY_ROOT, check=True)
    try:
        subprocess.run(
            compose + [
                "jobseeker-connector",
                "materialize",
                "--directory",
                runtime_directory,
                "--environment",
                "DEV",
                "--job",
                job_name,
            ],
            cwd=REPOSITORY_ROOT,
            check=True,
            stdout=subprocess.DEVNULL,
        )
        check_script = (
            "import os,sys; from pathlib import Path; root=Path(sys.argv[1]); key=sys.argv[2]; "
            "assert (root/'connectors.json').is_file(); assert (root/key/'username').read_text() == 'worker-user'; "
            "assert (root/key/'password').read_text() == 'worker-password'; "
            "assert (root/'jobseeker-connector').stat().st_mode & 0o777 == 0o700; "
            "token=os.environ['JOBSEEKER_CONNECTOR_API_TOKEN'].encode(); "
            "assert all(token not in path.read_bytes() for path in root.rglob('*') if path.is_file())"
        )
        subprocess.run(
            compose + ["python3", "-c", check_script, runtime_directory, key],
            cwd=REPOSITORY_ROOT,
            check=True,
        )
    finally:
        subprocess.run(compose + ["rm", "-rf", runtime_directory], cwd=REPOSITORY_ROOT, check=True)


def remove_connector_access_logs(key_prefix):
    query = "DELETE FROM connector_access_log WHERE connector_key LIKE '%s%%'" % key_prefix
    subprocess.run(
        [
            "docker",
            "compose",
            "exec",
            "-T",
            "mariadb",
            "sh",
            "-c",
            'mariadb -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "$1"',
            "sh",
            query,
        ],
        cwd=REPOSITORY_ROOT,
        check=True,
        stdout=subprocess.DEVNULL,
    )


def main():
    browser = Browser()
    run_id = uuid.uuid4().hex[:10]
    prefix = "e2e-" + run_id
    job_name = prefix + "-job"
    cloud_job = prefix + "-cloud"
    worker_job = prefix + "-worker"
    local_key = prefix + "-local"
    environment_key = prefix + "-environment"
    none_key = prefix + "-none"
    portless_key = prefix + "-portless"
    unreachable_key = prefix + "-unreachable"
    inactive_key = prefix + "-inactive"
    azure_key = prefix + "-azure"
    aws_key = prefix + "-aws"
    worker_key = prefix + "-worker"
    precedence_key = prefix + "-precedence"
    created_prefix = prefix + "-"

    browser.request("/")
    _, login_body, _ = browser.request(
        "/loginMe",
        method="POST",
        fields={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD},
        csrf=True,
    )
    if "logout" not in login_body.lower():
        raise AssertionError("Administrator login failed")

    try:
        status, payload, headers = runtime_catalog(browser, "DEV", job_name, expected=(401,), token="wrong-token")
        assert status == 401 and payload["error"] == "Unauthorized."
        assert "no-store" in headers.get("Cache-Control", "")
        status, body, _ = browser.request("/connector-runtime", expected=(405,))
        assert status == 405 and json.loads(body)["error"] == "Method not allowed."
        status, payload, _ = runtime_catalog(browser, "BAD ENVIRONMENT", job_name, expected=(422,))
        assert status == 422 and "valid environment" in payload["error"]

        local_fields = connector_fields(
            local_key,
            job_name,
            description="local encrypted connector",
            local_secret_fields="api_key=e2e-api-key",
        )
        save_connector(browser, "DEV", local_fields)
        local_id = connector_id(browser, local_key)

        save_connector(
            browser,
            "DEV",
            local_fields,
            expected_message="already exists for the selected environment and job scope",
        )
        assert len([row for row in connector_rows(browser) if row[0] == local_key]) == 1

        invalid_environment_fields = connector_fields(
            prefix + "-invalid-environment",
            job_name,
            db_type="generic_secret",
            auth_type="token",
            secret_backend="environment",
            address="",
            port="0",
            login="",
            password="",
            environment_mappings="token=INVALID VARIABLE",
        )
        save_connector(
            browser,
            "DEV",
            invalid_environment_fields,
            expected_message="selected secret backend configuration is invalid",
        )

        save_connector(
            browser,
            "DEV",
            connector_fields(
                environment_key,
                job_name,
                db_type="generic_secret",
                auth_type="token",
                secret_backend="environment",
                address="",
                port="0",
                login="",
                password="",
                environment_mappings="token=JOBSEEKER_E2E_CONNECTOR_TOKEN",
            ),
        )
        save_connector(
            browser,
            "DEV",
            connector_fields(
                none_key,
                job_name,
                db_type="generic_secret",
                auth_type="none",
                address="",
                port="0",
                login="",
                password="",
            ),
        )
        save_connector(browser, "DEV", connector_fields(portless_key, job_name, port="0"))
        save_connector(browser, "DEV", connector_fields(unreachable_key, job_name, address="127.0.0.1", port="1"))
        save_connector(browser, "DEV", connector_fields(inactive_key, job_name, is_active="0"))
        save_connector(
            browser,
            "DEV",
            connector_fields(worker_key, worker_job, login="worker-user", password="worker-password"),
        )

        save_connector(
            browser,
            "DEV",
            connector_fields(
                azure_key,
                cloud_job,
                db_type="generic_secret",
                auth_type="managed_identity",
                secret_backend="azure_key_vault",
                address="",
                port="0",
                login="",
                password="",
                vault_url="https://e2evault.vault.azure.net",
                azure_auth_mode="managed_identity",
                managed_identity_client_id="00000000-0000-0000-0000-000000000001",
                azure_secret_mappings="token=e2e-token",
            ),
        )
        save_connector(
            browser,
            "DEV",
            connector_fields(
                aws_key,
                cloud_job,
                db_type="generic_secret",
                auth_type="token",
                secret_backend="aws_secrets_manager",
                address="",
                port="0",
                login="",
                password="",
                aws_region="us-east-1",
                aws_secret_id="e2e/connector",
                aws_auth_mode="profile",
                aws_profile_name="e2e-profile",
                aws_field_mappings="token=credentials.token",
            ),
        )

        for environment, scoped_job, description, username in (
            ("ALL", "*", "global wildcard", "global-wildcard"),
            ("ALL", job_name, "global exact job", "global-job"),
            ("DEV", "*", "environment wildcard", "dev-wildcard"),
            ("DEV", job_name, "environment exact job", "dev-job"),
        ):
            save_connector(
                browser,
                environment,
                connector_fields(precedence_key, scoped_job, description=description, login=username),
            )

        _, exact_payload, exact_headers = runtime_catalog(browser, "DEV", job_name)
        assert "application/json" in exact_headers.get("Content-Type", "")
        exact_connectors = connector_map(exact_payload)
        assert exact_connectors[precedence_key]["description"] == "environment exact job"
        assert inactive_key not in exact_connectors
        assert exact_connectors[none_key]["secret"]["values"] == {}
        assert exact_connectors[environment_key]["secret"]["reference"]["variables"]["token"] == "JOBSEEKER_E2E_CONNECTOR_TOKEN"
        assert exact_connectors[local_key]["secret"]["values"]["api_key"] == "e2e-api-key"

        _, dev_wildcard_payload, _ = runtime_catalog(browser, "DEV", job_name + "-other")
        _, global_job_payload, _ = runtime_catalog(browser, "QA", job_name)
        _, global_wildcard_payload, _ = runtime_catalog(browser, "QA", job_name + "-other")
        assert connector_map(dev_wildcard_payload)[precedence_key]["description"] == "environment wildcard"
        assert connector_map(global_job_payload)[precedence_key]["description"] == "global exact job"
        assert connector_map(global_wildcard_payload)[precedence_key]["description"] == "global wildcard"

        _, cloud_payload, _ = runtime_catalog(browser, "DEV", cloud_job)
        cloud_connectors = connector_map(cloud_payload)
        assert cloud_connectors[azure_key]["secret"]["reference"]["auth_mode"] == "managed_identity"
        assert cloud_connectors[aws_key]["secret"]["reference"]["fields"]["token"] == "credentials.token"
        assert "values" not in cloud_connectors[azure_key]["secret"]
        assert "values" not in cloud_connectors[aws_key]["secret"]
        test_jenkins_worker_materialization(run_id, worker_key, worker_job)

        status, test_payload = test_connector(browser, local_id, "DEV")
        assert status == 200 and test_payload["ok"] is True and test_payload["network"] == "reachable"
        none_id = connector_id(browser, none_key)
        status, test_payload = test_connector(browser, none_id, "DEV")
        assert status == 200 and test_payload["ok"] is True and test_payload["network"] == "skipped"
        portless_id = connector_id(browser, portless_key)
        status, test_payload = test_connector(browser, portless_id, "DEV", expected=(422,))
        assert status == 422 and test_payload["ok"] is False and test_payload["network"] == "not_configured"
        unreachable_id = connector_id(browser, unreachable_key)
        status, test_payload = test_connector(browser, unreachable_id, "DEV", expected=(422,))
        assert status == 422 and test_payload["ok"] is False and test_payload["network"] == "unreachable"
        status, test_payload = test_connector(browser, local_id, "QA", expected=(409,))
        assert status == 409 and test_payload["ok"] is False
        status, _ = test_connector(browser, local_id, "DEV", expected=(403,), csrf=False)
        assert status == 403

        rotated_fields = connector_fields(
            local_key,
            job_name,
            description="partially rotated local connector",
            login="",
            password="rotated-password",
            local_secret_fields="",
        )
        save_connector(browser, "DEV", rotated_fields, connector_id=local_id)
        _, rotated_payload, _ = runtime_catalog(browser, "DEV", job_name)
        rotated_values = connector_map(rotated_payload)[local_key]["secret"]["values"]
        assert rotated_values == {
            "api_key": "e2e-api-key",
            "username": "e2e-user",
            "password": "rotated-password",
        }

        sdk = load_sdk()
        previous_environment_token = os.environ.get("JOBSEEKER_E2E_CONNECTOR_TOKEN")
        os.environ["JOBSEEKER_E2E_CONNECTOR_TOKEN"] = "worker-environment-token"
        try:
            with tempfile.TemporaryDirectory(prefix="jobseeker-connectors-e2e-") as workspace:
                runtime_directory = os.path.join(workspace, "runtime")
                manifest_path = sdk.materialize_connectors(
                    directory=runtime_directory,
                    environment="DEV",
                    job=job_name,
                    api_url=BASE_URL + "/connector-runtime",
                    api_token=API_TOKEN,
                )
                catalog = sdk.ConnectorCatalog(runtime_directory)
                assert catalog.resolve(local_key).password == "rotated-password"
                assert catalog.resolve(environment_key).value("token", required=True) == "worker-environment-token"
                assert catalog.resolve(precedence_key).username == "dev-job"
                assert catalog.resolve(inactive_key, required=False) is None
                manifest_text = Path(manifest_path).read_text(encoding="utf-8")
                assert "rotated-password" not in manifest_text
                assert "worker-environment-token" not in manifest_text
        finally:
            if previous_environment_token is None:
                os.environ.pop("JOBSEEKER_E2E_CONNECTOR_TOKEN", None)
            else:
                os.environ["JOBSEEKER_E2E_CONNECTOR_TOKEN"] = previous_environment_token

        cleared_fields = connector_fields(
            local_key,
            job_name,
            auth_type="none",
            login="",
            password="",
            local_secret_fields="",
            clear_local_secrets="1",
        )
        save_connector(browser, "DEV", cleared_fields, connector_id=local_id)
        _, cleared_payload, _ = runtime_catalog(browser, "DEV", job_name)
        assert connector_map(cleared_payload)[local_key]["secret"]["values"] == {}
        status, test_payload = test_connector(browser, local_id, "DEV")
        assert status == 200 and test_payload["ok"] is True

        delete_connector(browser, local_id)
        _, deleted_payload, _ = runtime_catalog(browser, "DEV", job_name)
        assert local_key not in connector_map(deleted_payload)
    finally:
        for key, row_id in connector_rows(browser):
            if key.startswith(created_prefix):
                delete_connector(browser, row_id)
        remove_connector_access_logs(created_prefix)

    print("JobSeeker live connector E2E tests passed.")


if __name__ == "__main__":
    main()