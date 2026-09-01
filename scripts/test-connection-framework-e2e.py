#!/usr/bin/env python3
"""End-to-end check of the connector connection-test framework and the unified
connection catalog.

* the seeded, editable ``jobseeker-mariadb`` connector exists
* a live worker test of it passes and reports a server version
* the disposable ``__jobseeker_conn_test_*`` Jenkins job is cleaned up
* a deliberately unreachable connector fails with status ``unreachable``
* a catalog MySQL connector is usable as an Insight Studio data source
"""
import html
import http.cookiejar
import json
import os
import re
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

BASE_URL = os.environ.get("JOBSEEKER_E2E_URL", "http://localhost").rstrip("/")
ADMIN_EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
ADMIN_PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")
REPOSITORY_ROOT = Path(__file__).resolve().parents[1]


class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def csrf(self):
        for cookie in self.cookies:
            if cookie.name == "csrf_cookie_name":
                return cookie.value
        raise AssertionError("CSRF cookie is missing")

    def request(self, path, method="GET", fields=None, csrf=False, expected=(200,), timeout=150):
        values = dict(fields or {})
        if csrf:
            values["csrf_test_name"] = self.csrf()
        data = urllib.parse.urlencode(values).encode("utf-8") if fields is not None else None
        request = urllib.request.Request(BASE_URL + path, data=data, method=method)
        try:
            with self.opener.open(request, timeout=timeout) as response:
                status, body = response.status, response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as error:
            status, body = error.code, error.read().decode("utf-8", "replace")
        if status not in expected:
            raise AssertionError("%s %s -> %s: %s" % (method, path, status, body[:400]))
        return status, body


def wait_for_conn_test_cleanup(attempts=15):
    """Jenkins removes the job directory asynchronously after doDelete returns."""
    for _ in range(attempts):
        result = subprocess.run(
            ["docker", "compose", "exec", "-T", "jenkins", "sh", "-c", "ls /var/jenkins_home/jobs/ 2>/dev/null || true"],
            cwd=REPOSITORY_ROOT, capture_output=True, text=True,
        )
        if "__jobseeker_conn_test_" not in result.stdout:
            return True
        time.sleep(1)
    return False


def connector_id(body, key):
    for block in re.findall(r'<tr data-connector-key="([^"]+)">(.*?)</tr>', body, re.DOTALL):
        if html.unescape(block[0]) != key:
            continue
        found = re.search(r'class="btn btn-info btn-xs testConnector"[^>]*data-id="(\d+)"', block[1])
        if found:
            return int(found.group(1))
    return None


def connector_fields(key, **overrides):
    fields = {
        "connector_key": key, "job_name": "*", "db_type": "mysql",
        "auth_type": "username_password", "secret_backend": "local",
        "address": "mariadb", "port": "3306", "schema": "jobseeker",
        "description": key, "additional_parameters": "", "oracle_ServiceName": "",
        "oracle_sid": "", "login": "mysql", "password": "mysql",
        "local_secret_fields": "", "is_active": "1",
    }
    fields.update(overrides)
    return fields


def main():
    run_id = uuid.uuid4().hex[:8]
    bad_key = "e2e-ct-%s-unreachable" % run_id
    catalog_key = "e2e-ct-%s-catalog" % run_id
    browser = Browser()
    created = []

    browser.request("/")
    _, login = browser.request("/loginMe", method="POST", fields={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD}, csrf=True)
    assert "logout" in login.lower(), "admin login failed"

    try:
        _, listing = browser.request("/dbSettings?environment=ALL")
        builtin_id = connector_id(listing, "jobseeker-mariadb")
        assert builtin_id, "the seeded jobseeker-mariadb connector is missing"

        # 1. Live worker test of the built-in connector.
        _, body = browser.request(
            "/dbSettings/testConnector?environment=ALL", method="POST",
            fields={"id": str(builtin_id), "mode": "live"}, csrf=True, expected=(200, 422),
        )
        result = json.loads(body)
        assert result.get("ok") is True, "built-in connector live test failed: %s" % body
        assert result.get("status") == "passed", result
        assert result.get("serverVersion"), "expected a server version in %s" % result
        assert result.get("buildResult") == "SUCCESS", result
        assert wait_for_conn_test_cleanup(), "a __jobseeker_conn_test_* job was left behind"

        # 2. Deliberately unreachable connector -> unreachable, not a crash.
        browser.request(
            "/dbSettings/InsertDbSettings?environment=DEV", method="POST",
            fields=connector_fields(bad_key, address="nonexistent.invalid", port="3306"),
            csrf=True,
        )
        created.append(bad_key)
        _, listing = browser.request("/dbSettings?environment=ALL")
        bad_id = connector_id(listing, bad_key)
        assert bad_id, "failed to create the unreachable connector fixture"
        _, body = browser.request(
            "/dbSettings/testConnector?environment=DEV", method="POST",
            fields={"id": str(bad_id), "mode": "live"}, csrf=True, expected=(200, 422),
        )
        result = json.loads(body)
        assert result.get("ok") is False and result.get("status") == "unreachable", result

        # 3. A catalog MySQL connector is offered to Insight Studio and can list tables.
        browser.request(
            "/dbSettings/InsertDbSettings?environment=DEV", method="POST",
            fields=connector_fields(catalog_key), csrf=True,
        )
        created.append(catalog_key)
        _, listing = browser.request("/dbSettings?environment=ALL")
        catalog_id = connector_id(listing, catalog_key)
        _, sources = browser.request("/Visualization/dataSources")
        assert catalog_key in sources, "catalog connector not shown on Insight Studio data sources"
        _, tables_body = browser.request("/Visualization/connectionTables/%d" % catalog_id, expected=(200, 422))
        tables = json.loads(tables_body)
        assert tables.get("status") is True and any(
            row["table_name"] == "database_settings" for row in tables.get("data", [])
        ), tables_body

        print("Connection framework E2E test passed.")
    finally:
        for _ in range(2):
            _, listing = browser.request("/dbSettings?environment=ALL")
            for key in list(created):
                cid = connector_id(listing, key)
                if cid:
                    browser.request(
                        "/dbSettings/deleteSetting?environment=ALL", method="POST",
                        fields={"userId": str(cid)}, csrf=True, expected=(200, 404, 409),
                    )


if __name__ == "__main__":
    main()
