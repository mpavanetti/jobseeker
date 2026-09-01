#!/usr/bin/env python3
"""End-to-end check of the job connector / dataset dependency map.

Creates a disposable inline-Python job whose main.py references the built-in
jobseeker-mariadb connector, the seeded "etl" data asset, and a deliberately
unknown connector. Then it verifies:

* scanDependencies resolves each reference with the right light status
* testDependencies runs a real worker handshake and passes for jobseeker-mariadb
* scanDependencies?persist=1 writes a job_dependencies row set
* JobView/dependencies returns the stored map (stored: true)
"""
import http.cookiejar
import json
import os
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
    rows = js.asset("etl")
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
    artifact = REPOSITORY_ROOT / "repository" / "python" / "inline" / job_name
    browser = Browser()
    created_job = False

    browser.request("/")
    _, login = browser.request("/loginMe", method="POST", fields={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD}, csrf=True)
    assert "logout" in login.lower(), "admin login failed"

    try:
        artifact.mkdir(parents=True)
        (artifact / "main.py").write_text(MAIN_PY, encoding="utf-8")
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
        assert datasets["etl"]["lightStatus"] == "ok", scan
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
        assert {item["key"] for item in stored["datasets"]} == {"etl"}, stored
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
        shutil.rmtree(artifact, ignore_errors=True)


if __name__ == "__main__":
    main()
