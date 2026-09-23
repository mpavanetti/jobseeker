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

            # A build number that was never used is an ordinary typo. It used to
            # repeat "could not be loaded (HTTP 404)" in all five detail rows and
            # again over an empty console viewer, mark every parameter as
            # "differs" because the absent run supplied nothing, and still claim
            # to be comparing the full set of runs.
            missing = 4096
            page.fill("#runCompareBuilds", "1, 2, %d" % missing)
            page.click("#runCompareGo")
            page.wait_for_function(
                "() => document.querySelectorAll('#runCompareResults th').length === 4", timeout=30000)
            results = page.locator("#runCompareResults").inner_text()
            status = page.locator("#runCompareStatus").inner_text()

            assert "HTTP 404" not in results and "HTTP 404" not in status, results
            # The explanation is stated once where the column is introduced, and
            # once more as the body of that build's console panel, which is a
            # different question being answered. What it must never do again is
            # repeat itself down every detail row.
            note = "No build #%d" % missing
            header_text = page.locator("#runCompareResults thead").inner_text()
            body_text = page.locator("#runCompareResults tbody").inner_text()
            logs_text = page.locator(".run-compare-logs").inner_text()
            assert header_text.count(note) == 1, header_text
            assert body_text.count(note) == 0, body_text
            assert logs_text.count(note) == 1, logs_text
            assert results.count(note) == 2, results.count(note)
            # Two real builds keep their console viewers; the absent one gets none.
            assert page.locator(".run-compare-log").count() == 3
            assert page.locator(".run-compare-log .job-console-host").count() == 2
            # The absent column is marked, and its cells carry no invented values.
            assert page.locator("#runCompareResults th.run-compare-absent").count() == 1
            assert "Not supplied" not in results, results
            # BATCH_SIZE genuinely differs across builds 1 and 2, so that row must
            # still be highlighted - the fix suppresses false positives only.
            batch_row = page.locator("#runCompareResults tr").filter(
                has=page.locator("td strong", has_text="BATCH_SIZE")).first
            assert "run-compare-different" in batch_row.get_attribute("class")
            # The status line reports what was actually compared, and says which
            # build numbers would have worked.
            assert "2 of the 3 runs" in status, status
            assert "#%d does not exist" % missing in status, status
            assert "builds #1 to #3" in status, status

            # Console differences. The three builds ran with BATCH_SIZE 100/200/300,
            # so their logs differ in exactly that - but every log also carries
            # timestamps, a PID and a random /tmp/jenkins<n>.sh path, which differ
            # on every run. A raw line comparison would report that everything
            # changed, so the volatile parts are masked before comparing.
            page.fill("#runCompareBuilds", "3, 2, 1")
            page.click("#runCompareGo")
            page.wait_for_selector(".run-compare-diff-block", timeout=30000)
            console_row = page.locator("#runCompareResults tr").filter(
                has=page.locator("td strong", has_text="Console")).first
            console_text = console_row.inner_text()
            assert "Baseline for comparison" in console_text, console_text
            assert console_text.count("Differs from #3") == 2, console_text

            diff_text = page.locator(".run-compare-diff").inner_text()
            # The batch values are the real difference and must be surfaced.
            assert "batch=200" in diff_text and "batch=300" in diff_text, diff_text
            # The masked noise must not be: no bare timestamps and no temp script
            # path should have been reported as a difference.
            assert "/tmp/jenkins" not in diff_text, diff_text
            assert page.locator(".run-compare-diff-block").count() == 2
            # Two builds, each compared to the baseline, both ways round.
            assert page.locator(".run-compare-diff-added").count() >= 1
            assert page.locator(".run-compare-diff-removed").count() >= 1

            # Two runs of the same parameters have nothing left to differ on once
            # the volatile parts are masked.
            page.fill("#runCompareBuilds", "1, 1")
            page.click("#runCompareGo")
            page.wait_for_timeout(2000)

            # Who started the run, and what code changed, are worth as much as the
            # result when working out why two runs behaved differently.
            page.fill("#runCompareBuilds", "3, 1")
            page.click("#runCompareGo")
            page.wait_for_selector(".run-compare-diff-block", timeout=30000)
            labels = [t.strip() for t in page.locator("#runCompareResults tbody td strong").all_inner_texts()]
            for expected in ("Triggered by", "Changes", "Console"):
                assert expected in labels, labels

            assert not errors, errors
            browser.close()
        print("Job run comparison end-to-end passed (3 builds, parameters, full logs, secret masking, absent build).")
    finally:
        if created:
            request("/job/%s/doDelete" % JOB, "POST", b"", "application/x-www-form-urlencoded", crumb)


if __name__ == "__main__":
    main()
