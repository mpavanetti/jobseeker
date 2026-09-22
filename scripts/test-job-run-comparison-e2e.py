#!/usr/bin/env python3
"""Compare three real parameterized Jenkins builds in the Job View browser UI.

Requires the running Compose stack and Playwright. The temporary Jenkins job is
deleted even when an assertion fails.
"""

import base64
import http.cookiejar
import json
import os
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

from playwright.sync_api import sync_playwright


APP = os.environ.get("JOBSEEKER_UI_URL", "http://127.0.0.1").rstrip("/")
JENKINS = os.environ.get("JOBSEEKER_JENKINS_URL", "http://127.0.0.1:8080").rstrip("/")
AUTH = base64.b64encode((os.environ.get("JENKINS_ADMIN_ID", "jobseeker") + ":" +
                         os.environ.get("JENKINS_ADMIN_PASSWORD", "jobseeker")).encode()).decode()
JOB = "jobseeker-run-compare-" + uuid.uuid4().hex[:8]
OPENER = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(path, method="GET", body=None, content_type=None, crumb=None):
    req = urllib.request.Request(JENKINS + path, data=body, method=method)
    req.add_header("Authorization", "Basic " + AUTH)
    if content_type:
        req.add_header("Content-Type", content_type)
    if crumb:
        req.add_header(crumb[0], crumb[1])
    try:
        with OPENER.open(req, timeout=30) as response:
            return response.status, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.read()


def wait_build(number):
    deadline = time.monotonic() + 90
    while time.monotonic() < deadline:
        status, payload = request("/job/%s/%d/api/json" % (JOB, number))
        if status == 200:
            build = json.loads(payload)
            if not build.get("building"):
                assert build.get("result") == "SUCCESS", "build #%d failed" % number
                return
        time.sleep(1)
    raise AssertionError("build #%d did not finish" % number)


def main():
    status, payload = request("/crumbIssuer/api/json")
    crumb = None
    if status == 200:
        data = json.loads(payload)
        crumb = (data["crumbRequestField"], data["crumb"])

    definitions = "".join(
        "<hudson.model.StringParameterDefinition><name>%s</name><defaultValue>%s</defaultValue>"
        "<trim>false</trim></hudson.model.StringParameterDefinition>" % (name, default)
        for name, default in (("ENVIRONMENT", "DEV"), ("BATCH_SIZE", "0"), ("API_TOKEN", ""))
    )
    config = (
        '<project><actions/><description>Temporary run comparison test</description>'
        '<keepDependencies>false</keepDependencies><properties>'
        '<hudson.model.ParametersDefinitionProperty><parameterDefinitions>%s</parameterDefinitions>'
        '</hudson.model.ParametersDefinitionProperty></properties>'
        '<scm class="hudson.scm.NullSCM"/><canRoam>true</canRoam><disabled>false</disabled>'
        '<blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding>'
        '<blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding><triggers/>'
        '<concurrentBuild>false</concurrentBuild><builders><hudson.tasks.Shell>'
        '<command>echo "batch=$BATCH_SIZE env=$ENVIRONMENT"</command></hudson.tasks.Shell>'
        '</builders><publishers/><buildWrappers/></project>'
    ) % definitions
    created = False
    try:
        status, payload = request("/createItem?name=" + urllib.parse.quote(JOB), "POST", config.encode(), "application/xml", crumb)
        assert status in (200, 201), "createItem HTTP %d: %s" % (status, payload[:200])
        created = True
        for number, batch in enumerate(("100", "200", "300"), 1):
            body = urllib.parse.urlencode({"ENVIRONMENT": "DEV", "BATCH_SIZE": batch, "API_TOKEN": "secret-" + batch}).encode()
            status, payload = request("/job/%s/buildWithParameters" % JOB, "POST", body, "application/x-www-form-urlencoded", crumb)
            assert status in (200, 201, 202, 302), "build request HTTP %d: %s" % (status, payload[:200])
            wait_build(number)

        with sync_playwright() as playwright:
            browser = playwright.chromium.launch()
            page = browser.new_page(viewport={"width": 1440, "height": 900})
            errors = []
            page.on("pageerror", lambda error: errors.append(str(error)))
            page.goto(APP + "/", wait_until="domcontentloaded")
            page.fill("input[name='email']", os.environ.get("JOBSEEKER_UI_EMAIL", "admin@example.com"))
            page.fill("input[name='password']", os.environ.get("JOBSEEKER_UI_PASSWORD", "123456"))
            page.click("button[type='submit'], input[type='submit']")
            page.wait_for_load_state("networkidle")
            page.goto(APP + "/jobView?job=" + urllib.parse.quote(JOB), wait_until="networkidle")
            assert page.locator(".job-detail-header .job-compare-runs.btn-primary").first.is_visible()
            assert page.locator(".job-detail-header .btn-default").count() == 2
            page.locator(".job-compare-runs").first.click()
            page.locator("#runCompareResults table").wait_for(timeout=30000)
            assert page.locator(".run-compare-log").count() == 2
            page.fill("#runCompareBuilds", "3, 2, 1")
            page.click("#runCompareGo")
            page.locator(".run-compare-log").nth(2).wait_for(timeout=30000)
            assert [value.strip() for value in page.locator("#runCompareResults th").all_inner_texts()[:4]] == ["Detail", "Build #3", "Build #2", "Build #1"]
            row = page.locator("#runCompareResults tr").filter(has=page.locator("td strong", has_text="BATCH_SIZE")).first
            assert "run-compare-different" in row.get_attribute("class")
            assert all(value in row.inner_text() for value in ("100", "200", "300"))
            token_row = page.locator("#runCompareResults tr").filter(has=page.locator("td strong", has_text="API_TOKEN")).first
            assert "Hidden" in token_row.inner_text() and "secret-" not in token_row.inner_text(), token_row.inner_text()
            assert page.locator(".run-compare-log .job-console-host").count() == 3
            assert page.locator(".run-compare-log .job-console-section").count() >= 3
            logs = page.evaluate("() => [...document.querySelectorAll('.run-compare-log .job-console-host')].map(host => JobSeekerConsole.getText(host))")
            assert all("batch=%s" % batch in log for batch, log in zip(("300", "200", "100"), logs))
            assert not errors, errors
            browser.close()
        print("Job run comparison end-to-end passed (3 builds, parameters, full logs, secret masking).")
    finally:
        if created:
            request("/job/%s/doDelete" % JOB, "POST", b"", "application/x-www-form-urlencoded", crumb)


if __name__ == "__main__":
    main()
