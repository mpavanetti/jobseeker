#!/usr/bin/env python3
"""End-to-end check of the job connector / dataset dependency map.

Creates a disposable, job-scoped data asset and an inline-Python job whose
main.py references that asset, the built-in jobseeker-mariadb connector, and a
deliberately unknown connector. Then it verifies:

* scanDependencies resolves each reference with the right light status
* testDependencies runs a real worker handshake and passes for jobseeker-mariadb
* scanDependencies?persist=1 writes a job_dependencies row set
* JobView/dependencies returns the stored map (stored: true)
"""
import http.cookiejar
import html
import json
import os
import re
import shutil
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

BASE_URL = os.environ.get("JOBSEEKER_E2E_URL", "http://localhost").rstrip("/")
ADMIN_EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
ADMIN_PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")
REPOSITORY_ROOT = Path(__file__).resolve().parents[1]

MAIN_PY = """import jobseeker

with jobseeker.client(environment="DEV", job="e2e") as js:
    warehouse = js.connector("jobseeker-mariadb")
    rows = js.asset("{asset_key}")
    missing = js.connector("nope-not-real")
"""


class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def csrf(self):
        for cookie in self.cookies:
            if cookie.name == "csrf_cookie_name":
                return cookie.value
        raise AssertionError("no CSRF cookie")

    def request(self, path, method="GET", fields=None, body=None, content_type=None, csrf=False, expected=(200,)):
        data = body
        headers = {}
        if fields is not None:
            values = list(fields.items()) if isinstance(fields, dict) else list(fields)
            if csrf:
                values.append(("csrf_test_name", self.csrf()))
            data = urllib.parse.urlencode(values).encode("utf-8")
        elif csrf:
            path += ("&" if "?" in path else "?") + urllib.parse.urlencode({"csrf_test_name": self.csrf()})
        if content_type:
            headers["Content-Type"] = content_type
        request = urllib.request.Request(BASE_URL + path, data=data, headers=headers, method=method)
        try:
            with self.opener.open(request, timeout=150) as response:
                status, text = response.status, response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as error:
            status, text = error.code, error.read().decode("utf-8", "replace")
        if status not in expected:
            raise AssertionError("%s %s -> %s: %s" % (method, path, status, text[:400]))
        return status, text

    def multipart(self, path, fields, file_field, file_name, file_content, content_type="text/csv"):
        boundary = "----jobseeker-e2e-" + uuid.uuid4().hex
        parts = []
        values = list(fields.items())
        values.append(("csrf_test_name", self.csrf()))
        for name, value in values:
            parts.extend([
                "--" + boundary,
                'Content-Disposition: form-data; name="%s"' % name,
                "",
                str(value),
            ])
        prefix = ("\r\n".join(parts) + "\r\n").encode("utf-8")
        file_header = (
            "--%s\r\n"
            "Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n"
            "Content-Type: %s\r\n\r\n"
            % (boundary, file_field, file_name, content_type)
        ).encode("utf-8")
        suffix = ("\r\n--%s--\r\n" % boundary).encode("utf-8")
        return self.request(
            path,
            method="POST",
            body=prefix + file_header + file_content + suffix,
            content_type="multipart/form-data; boundary=" + boundary,
        )


def assets_from_page(page):
    match = re.search(r'<script id="dataAssetsPayload" type="application/json">(.*?)</script>', page, re.S)
    if not match:
        raise AssertionError("the Data Assets page did not publish its JSON payload")
    return json.loads(html.unescape(match.group(1)))


def jenkins_config(job_name):
    command = "python3 '/php/repository/python/inline/%s/main.py'" % job_name
    return """<?xml version="1.1" encoding="UTF-8"?>
<project>
  <description>Disposable dependency-map E2E fixture.</description>
  <properties><hudson.model.ParametersDefinitionProperty><parameterDefinitions>
    <hudson.model.StringParameterDefinition><name>ENVIRONMENT</name><defaultValue>DEV</defaultValue><trim>true</trim></hudson.model.StringParameterDefinition>
  </parameterDefinitions></hudson.model.ParametersDefinitionProperty></properties>
  <scm class="hudson.scm.NullSCM"/><canRoam>true</canRoam><disabled>false</disabled>
  <triggers/><concurrentBuild>false</concurrentBuild>
  <builders><hudson.tasks.Shell><command>%s</command></hudson.tasks.Shell></builders>
  <publishers/><buildWrappers/>
</project>
""" % command


def by_key(items):
    return {item["key"]: item for item in items}


def main():
    run_id = uuid.uuid4().hex[:8]
    job_name = "e2e-deps-%s" % run_id
    asset_key = "e2e-dataset-%s" % run_id
    artifact = REPOSITORY_ROOT / "repository" / "python" / "inline" / job_name
    browser = Browser()
    created_job = False
    created_asset_id = 0

    browser.request("/")
    _, login = browser.request("/loginMe", method="POST", fields={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD}, csrf=True)
    assert "logout" in login.lower(), "admin login failed"

    try:
        # Publish a real, disposable asset through the same multipart endpoint
        # operators use. This keeps the test independent from mutable seed data.
        _, page = browser.multipart(
            "/data-assets/save?environment=DEV",
            {
                "asset_id": "0",
                "name": "Dependency E2E dataset",
                "asset_key": asset_key,
                "direction": "input",
                "environment": "DEV",
                "job_name": job_name,
                "format": "csv",
                "file_name": "dependency-e2e.csv",
                "delimiter": ",",
                "encoding": "UTF-8",
                "has_header": "1",
                "sheet": "",
                "description": "Disposable job dependency E2E fixture.",
                "is_required": "1",
                "is_active": "1",
            },
            "asset_file",
            "dependency-e2e.csv",
            b"id,value\n1,ready\n",
        )
        assets = assets_from_page(page)
        created_asset = next((item for item in assets if item["key"] == asset_key), None)
        assert created_asset and created_asset["job"] == job_name, assets
        created_asset_id = int(created_asset["id"])

        _, body = browser.request("/data-assets/preview/%d?environment=DEV" % created_asset_id)
        preview = json.loads(body)
        assert preview["ok"] is True and preview["rows"] == [["1", "ready"]], preview
        _, body = browser.request("/data-assets/catalog?environment=DEV")
        catalog_asset = next((item for item in json.loads(body)["assets"] if item["key"] == asset_key), None)
        assert catalog_asset and catalog_asset["exists"] is True and catalog_asset["version"] == 1, body

        artifact.mkdir(parents=True)
        (artifact / "main.py").write_text(MAIN_PY.format(asset_key=asset_key), encoding="utf-8")
        browser.request(
            "/jenkins/proxy?" + urllib.parse.urlencode({"path": "createItem?name=" + job_name}),
            method="POST", body=jenkins_config(job_name).encode("utf-8"),
            content_type="application/xml", csrf=True,
        )
        created_job = True

        # 1. Live scan of the saved job's repo source.
        _, body = browser.request(
            "/jobCreation/scanDependencies", method="POST",
            fields={"environment": "DEV", "job_name": job_name}, csrf=True,
        )
        scan = json.loads(body)
        connectors = by_key(scan["connectors"])
        datasets = by_key(scan["datasets"])
        assert connectors["jobseeker-mariadb"]["lightStatus"] == "ok", scan
        assert connectors["jobseeker-mariadb"]["refId"], scan
        assert connectors["nope-not-real"]["lightStatus"] == "missing", scan
        assert datasets[asset_key]["lightStatus"] == "ok", scan
        assert any("nope-not-real" in w for w in scan["warnings"]), scan

        # 2. Persist the map (this is what job save does).
        _, body = browser.request(
            "/jobCreation/scanDependencies", method="POST",
            fields={"environment": "DEV", "job_name": job_name, "persist": "1"}, csrf=True,
        )
        assert json.loads(body).get("persisted") is True, body

        # 3. Heavy worker handshake for the referenced connectors (client does this
        #    once after save); its result is recorded against the stored rows.
        _, body = browser.request(
            "/jobCreation/testDependencies", method="POST",
            fields=[("environment", "DEV"), ("job_name", job_name),
                    ("connector_keys[]", "jobseeker-mariadb"), ("connector_keys[]", "nope-not-real")],
            csrf=True, expected=(200,),
        )
        results = by_key(json.loads(body)["results"])
        assert results["jobseeker-mariadb"]["ok"] is True and results["jobseeker-mariadb"]["status"] == "passed", body
        assert results["jobseeker-mariadb"].get("serverVersion"), body
        assert results["nope-not-real"]["ok"] is False, body

        _, body = browser.request("/jobView/dependencies?" + urllib.parse.urlencode({"job": job_name, "environment": "DEV"}))
        stored = json.loads(body)
        assert stored["stored"] is True, stored
        stored_connectors = by_key(stored["connectors"])
        assert set(stored_connectors) == {"jobseeker-mariadb", "nope-not-real"}, stored
        assert {item["key"] for item in stored["datasets"]} == {asset_key}, stored
        # the heavy result recorded in step 2 must have stuck
        assert stored_connectors["jobseeker-mariadb"]["status"] == "passed", stored

        print("Job dependency mapping E2E test passed.")
    finally:
        subprocess.run(
            ["docker", "compose", "exec", "-T", "mariadb", "sh", "-c",
             'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "DELETE FROM job_dependencies WHERE job_name = \'%s\'"' % job_name],
            cwd=REPOSITORY_ROOT, capture_output=True,
        )
        if created_job:
            browser.request(
                "/DeleteJob/deleteJobs?environment=DEV", method="POST",
                fields={"jobs": job_name, "delete_repositories": "0"}, csrf=True, expected=(200,),
            )
        if created_asset_id:
            browser.request(
                "/data-assets/delete?environment=DEV", method="POST",
                fields={"asset_id": str(created_asset_id), "delete_file": "1"}, csrf=True, expected=(200,),
            )
            # The application deliberately retains empty scope directories.
            # This fixture owns both paths, so remove them only when empty.
            for directory in (
                REPOSITORY_ROOT / "repository" / "data-assets" / "dev" / job_name / asset_key,
                REPOSITORY_ROOT / "repository" / "data-assets" / "dev" / job_name,
            ):
                try:
                    directory.rmdir()
                except OSError:
                    pass
        shutil.rmtree(artifact, ignore_errors=True)


if __name__ == "__main__":
    main()
