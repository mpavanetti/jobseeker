#!/usr/bin/env python3
"""Browser checks for the Apache Hop screens.

Static assertions cannot catch a panel that renders off-screen, a button that
does nothing, or a console error, so the two user-facing surfaces of the Hop
integration are driven in a real browser: the Apache Hop screen and the Apache
Hop branch of the Job Creation form.

Needs the running stack and Playwright:

    pip install playwright && python3 -m playwright install chromium
    python3 scripts/test-hop-ui-e2e.py

Environment:
    JOBSEEKER_UI_URL       base URL (default http://127.0.0.1)
    JOBSEEKER_UI_EMAIL     default admin@example.com
    JOBSEEKER_UI_PASSWORD  default 123456
    JOBSEEKER_JENKINS_URL  Jenkins URL (default http://127.0.0.1:8080)
    JENKINS_ADMIN_ID / JENKINS_ADMIN_PASSWORD
                           Jenkins credentials (default jobseeker/jobseeker)
    JOBSEEKER_DB_*         MariaDB settings (defaults match docker-compose)
"""

from __future__ import annotations

import base64
import http.cookiejar
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE_URL = os.environ.get("JOBSEEKER_UI_URL", "http://127.0.0.1").rstrip("/")
EMAIL = os.environ.get("JOBSEEKER_UI_EMAIL", "admin@example.com")
PASSWORD = os.environ.get("JOBSEEKER_UI_PASSWORD", "123456")
JENKINS_URL = os.environ.get("JOBSEEKER_JENKINS_URL", "http://127.0.0.1:8080").rstrip("/")
JENKINS_AUTH = "Basic " + base64.b64encode(
    ("%s:%s" % (os.environ.get("JENKINS_ADMIN_ID", "jobseeker"),
                 os.environ.get("JENKINS_ADMIN_PASSWORD", "jobseeker"))).encode()
).decode()
FIXTURE_JOB = "hop-ui-canvas-%s" % uuid.uuid4().hex[:6]
JENKINS_OPENER = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
)

PASSED: list = []
FAILED: list = []

# The legacy Job Creation page triggers Chrome's DOMSubtreeModified deprecation
# at error level. It predates this work and would drown out real failures.
IGNORED_CONSOLE = ("DOMSubtreeModified", "Deprecation", "favicon")
# A bare "Failed to load resource" console line carries no URL, so it is judged
# through the response listener below, which knows what actually failed.
UNATTRIBUTED_CONSOLE = "Failed to load resource"
# Endpoints that proxy to Jenkins answer 502 when the app is served without a
# reachable Jenkins. They are unrelated to Apache Hop, so they are reported but
# do not fail the run; everything else must load.
IGNORED_REQUESTS = ("/jenkins/", "/dashboard/overview", "/availableJobs")


def check(name: str, condition: bool, detail: str = "") -> None:
    if condition:
        PASSED.append(name)
        print("  PASS  %s" % name)
    else:
        FAILED.append((name, detail))
        print("  FAIL  %s%s" % (name, (" - " + detail) if detail else ""))


def stage(title: str) -> None:
    print("\n== %s ==" % title)


def jenkins(path: str, method: str = "GET", data: bytes = None, headers: dict = None):
    request = urllib.request.Request(JENKINS_URL + path, data=data, method=method)
    request.add_header("Authorization", JENKINS_AUTH)
    for name, value in (headers or {}).items():
        request.add_header(name, value)
    try:
        with JENKINS_OPENER.open(request, timeout=60) as response:
            return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode("utf-8", "replace")
    except urllib.error.URLError as error:
        return 0, str(error)


def jenkins_post_headers() -> dict:
    status, body = jenkins("/crumbIssuer/api/json")
    if status != 200:
        return {}
    crumb = json.loads(body)
    return {crumb["crumbRequestField"]: crumb["crumb"]}


def create_hop_job_fixture():
    """Create the smallest Jenkins/registry fixture needed by Job Execution.

    Job creation itself has a dedicated end-to-end suite. This fixture keeps the
    browser suite independent of that suite's cleanup while still making the
    canvas link and graph endpoint use the real Jenkins and MariaDB services.
    """

    config = """<?xml version='1.1' encoding='UTF-8'?>
<project>
  <actions/>
  <description>Temporary Apache Hop browser-test fixture</description>
  <keepDependencies>false</keepDependencies>
  <properties>
    <hudson.model.ParametersDefinitionProperty>
      <parameterDefinitions>
        <hudson.model.StringParameterDefinition>
          <name>ENVIRONMENT</name>
          <defaultValue>DEV</defaultValue>
          <trim>false</trim>
        </hudson.model.StringParameterDefinition>
      </parameterDefinitions>
    </hudson.model.ParametersDefinitionProperty>
  </properties>
  <scm class="hudson.scm.NullSCM"/>
  <canRoam>true</canRoam>
  <disabled>false</disabled>
  <blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding>
  <blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding>
  <triggers/>
  <concurrentBuild>false</concurrentBuild>
  <builders/>
  <publishers/>
  <buildWrappers/>
</project>
""".encode("utf-8")
    headers = dict(jenkins_post_headers(), **{"Content-Type": "application/xml"})
    status, body = jenkins(
        "/createItem?name=" + urllib.parse.quote(FIXTURE_JOB),
        method="POST",
        data=config,
        headers=headers,
    )
    if status not in (200, 201):
        return False, "Jenkins returned HTTP %s: %s" % (status, body[:160])

    try:
        import mysql.connector  # type: ignore

        connection = mysql.connector.connect(
            host=os.environ.get("JOBSEEKER_DB_HOST", "127.0.0.1"),
            port=int(os.environ.get("JOBSEEKER_DB_PORT", "3306")),
            user=os.environ.get("JOBSEEKER_DB_USER", "mysql"),
            password=os.environ.get("JOBSEEKER_DB_PASSWORD", "mysql"),
            database=os.environ.get("JOBSEEKER_DB_NAME", "jobseeker"),
        )
        try:
            cursor = connection.cursor()
            cursor.execute(
                "INSERT INTO hop_project_jobs "
                "(project_key, job_name, environment, entry_file, engine, created_at, updated_at) "
                "VALUES (%s, %s, 'DEV', 'workflows/main.hwf', 'container', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                ("jobseeker", FIXTURE_JOB),
            )
            connection.commit()
            cursor.close()
        finally:
            connection.close()
    except Exception as error:  # noqa: BLE001 - report fixture setup cleanly
        remove_hop_job_fixture()
        return False, "could not register the Hop fixture: %s" % error

    return True, ""


def remove_hop_job_fixture() -> None:
    """Remove only the Jenkins and registry rows created by this test run."""

    try:
        import mysql.connector  # type: ignore

        connection = mysql.connector.connect(
            host=os.environ.get("JOBSEEKER_DB_HOST", "127.0.0.1"),
            port=int(os.environ.get("JOBSEEKER_DB_PORT", "3306")),
            user=os.environ.get("JOBSEEKER_DB_USER", "mysql"),
            password=os.environ.get("JOBSEEKER_DB_PASSWORD", "mysql"),
            database=os.environ.get("JOBSEEKER_DB_NAME", "jobseeker"),
        )
        try:
            cursor = connection.cursor()
            cursor.execute("DELETE FROM hop_project_jobs WHERE job_name = %s", (FIXTURE_JOB,))
            connection.commit()
            cursor.close()
        finally:
            connection.close()
    except Exception:  # noqa: BLE001 - cleanup remains best effort
        pass

    try:
        jenkins(
            "/job/%s/doDelete" % urllib.parse.quote(FIXTURE_JOB),
            method="POST",
            data=b"",
            headers=jenkins_post_headers(),
        )
    except Exception:  # noqa: BLE001 - cleanup remains best effort
        pass


def main() -> int:
    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        print("Playwright is not installed. pip install playwright && python3 -m playwright install chromium")
        return 2

    print("JobSeeker Apache Hop UI checks against %s" % BASE_URL)
    errors: list = []
    fixture_ready, fixture_detail = create_hop_job_fixture()

    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch()
            context = browser.new_context(viewport={"width": 1440, "height": 900})
            page = context.new_page()
            page.on("pageerror", lambda error: errors.append("pageerror: %s" % error))
            page.on(
                "console",
                lambda message: errors.append("console: %s" % message.text)
                if message.type == "error"
                and UNATTRIBUTED_CONSOLE not in message.text
                and not any(token in message.text for token in IGNORED_CONSOLE)
                else None,
            )
            page.on(
                "response",
                lambda response: errors.append("http %d: %s" % (response.status, response.url))
                if response.status >= 400 and not any(token in response.url for token in IGNORED_REQUESTS)
                else None,
            )

            check("temporary Hop UI fixture is available", fixture_ready, fixture_detail)
            stage("Sign in")
            page.goto(BASE_URL + "/", wait_until="domcontentloaded")
            page.fill("input[name='email']", EMAIL)
            page.fill("input[name='password']", PASSWORD)
            page.click("button[type='submit'], input[type='submit']")
            page.wait_for_load_state("networkidle")
            check("signed in", "login" not in page.url.lower() or page.locator(".main-sidebar").count() > 0, page.url)

            # Measure an established screen first: comparing against it catches a
            # panel that escapes AdminLTE's content wrapper without hard-coding
            # numbers that depend on how the app is being served.
            page.goto(BASE_URL + "/data-assets", wait_until="networkidle")
            reference = page.locator(".content-wrapper").first.bounding_box() or {"x": 0, "width": 0}

            stage("Apache Hop screen")
            page.goto(BASE_URL + "/hop", wait_until="networkidle")
            check("the Apache Hop screen loads", page.locator(".hop-page").count() == 1, page.url)
            check("the heading names Apache Hop", "Apache Hop" in page.locator("h1").first.inner_text())

            # A Spark screen once shipped with its panel outside AdminLTE's content
            # wrapper and rendered off-screen. Compare the real geometry with a
            # screen known to be laid out correctly.
            box = page.locator(".content-wrapper.hop-page").first.bounding_box()
            check("the screen is laid out like the other ETL screens",
                  box is not None
                  and abs(box["x"] - reference["x"]) <= 2
                  and box["width"] >= reference["width"] - 2,
                  "hop=%s reference=%s" % (box, reference))
            check("the page does not scroll horizontally",
                  page.evaluate("document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1"))

            check("both engines are described", page.locator(".hop-engine-card").count() == 2)
            engine_text = page.locator(".hop-engine-grid").inner_text()
            for engine in ("Container", "Hop Server"):
                check("engine card: %s" % engine, engine in engine_text)
            check("the removed Jenkins agent engine is gone", "Jenkins agent" not in engine_text)
            check("the Hop Server status is reported",
                  page.locator(".hop-engine-card .label").count() == 2)
            page.wait_for_function(
                "window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#hopExecutionsTable')"
            )
            check("Hop Server executions use paginated DataTables",
                  page.locator("#hopExecutionsTable_wrapper").count() == 1
                  and page.evaluate("$('#hopExecutionsTable').DataTable().page.len()") == 25)
            if page.locator("#hopProjectsTable").count() > 0:
                page.wait_for_function("$.fn.DataTable.isDataTable('#hopProjectsTable')")
                check("Hop projects use paginated DataTables",
                      page.locator("#hopProjectsTable_wrapper").count() == 1
                      and page.evaluate("$('#hopProjectsTable').DataTable().page.len()") == 25)

            # A run published straight to the Hop Server reaches a person only
            # through this panel, so it has to be on the screen and it has to
            # keep itself current without a manual reload.
            stage("Hop Server executions panel")
            check("the executions panel is on the screen",
                  "Hop Server executions" in page.content())
            check("the panel explains where an unattributed run came from",
                  "Apache Hop GUI" in page.content() or "publishing straight from" in page.content())
            rows = page.locator("#hopExecutionsBody tr")
            if rows.count() > 0:
                check("an execution shows its status and row counts",
                      "read " in rows.first.inner_text())
                page.locator(".hop-view-log").first.click()
                page.wait_for_timeout(1200)
                check("the log viewer opens", page.locator("#hopLogModal").first.is_visible())
                check("the log viewer shows Hop output",
                      len(page.locator("#hopLogBody").inner_text().strip()) > 20,
                      page.locator("#hopLogBody").inner_text()[:120])
                page.locator("#hopLogModal button[data-dismiss='modal']").first.click()
                page.wait_for_timeout(500)
            else:
                check("the empty state explains how to get a run here",
                      page.locator("#hopExecutionsEmpty").first.is_visible())

            page.locator("#hopRefreshExecutions").click()
            page.wait_for_timeout(2500)
            check("refreshing reconciles the Hop Server",
                  page.locator("#hopSyncState").inner_text().strip() != "",
                  "the sync state stayed empty")
            check("refreshing preserves the executions DataTable",
                  page.evaluate("$.fn.DataTable.isDataTable('#hopExecutionsTable')"))

            # A run has to be openable in the desktop Apache Hop GUI, which
            # means the file itself has to be downloadable.
            stage("Canvas, download and connections")
            project_canvas = page.locator(".hop-project-canvas")
            if project_canvas.count() > 0:
                project_canvas.first.click()
                page.wait_for_timeout(2000)
                check("a project file opens its canvas",
                      page.locator("#hopCanvasBody .hop-node").count() > 0)
                check("the canvas draws its hops",
                      page.locator("#hopCanvasBody .hop-edge").count() > 0)
                check("the canvas can be zoomed",
                      page.locator("#hopCanvasBody .hop-canvas-toolbar .btn").count() == 3)
                # Overlapping boxes hide the hops between them, which is the one
                # thing the picture exists to show.
                overlaps = page.evaluate(
                    "() => { const b = [...document.querySelectorAll('#hopCanvasBody .hop-node rect')]"
                    ".map(r => r.getBoundingClientRect()); let n = 0;"
                    " for (let i=0;i<b.length;i++) for (let j=i+1;j<b.length;j++)"
                    "  if (b[i].left < b[j].right && b[j].left < b[i].right &&"
                    "      b[i].top < b[j].bottom && b[j].top < b[i].bottom) n++;"
                    " return n; }"
                )
                check("no two boxes overlap", overlaps == 0, "%s overlapping pair(s)" % overlaps)
                check("the canvas offers the file and the project for download",
                      page.locator("#hopCanvasDownload").is_visible()
                      and page.locator("#hopCanvasDownloadProject").is_visible())
                page.locator("#hopCanvasModal button[data-dismiss='modal']").first.click()
                page.wait_for_timeout(600)
            else:
                print("  SKIP  no Hop project file is available to draw")

            check("publishing connections to the Hop Server is offered",
                  "Connections on the Hop Server" in page.content())

            stage("Job Execution: the Apache Hop canvas")
            page.goto(BASE_URL + "/jobExecution", wait_until="networkidle")
            page.wait_for_timeout(3000)
            check("the canvas is not a separate execution-page section",
                  page.locator("#hopCanvasBox").count() == 0)
            fixture_link = page.locator(
                '.execution-hop-job-link[data-hop-job="%s"]' % FIXTURE_JOB
            )
            if fixture_ready:
                fixture_link.wait_for(state="visible")
                fixture_link.click()
                page.locator("#hopCanvasModal").wait_for(state="visible")
                page.wait_for_timeout(1500)
                check("a Hop job name opens the canvas modal",
                      page.locator("#hopCanvasModal").is_visible())
                check("the execution screen draws the canvas of a Hop job",
                      page.locator("#hopExecutionCanvas .hop-node").count() > 0)
                check("it says whether the Hop Server is running it now",
                      page.locator("#hopExecutionCanvasState").inner_text().strip() != "")
                page.locator("#hopCanvasModal button[data-dismiss='modal']").first.click()
            else:
                check("a Hop job is available on Job Execution", False, fixture_detail)

            page.goto(BASE_URL + "/hop", wait_until="networkidle")
            page.wait_for_timeout(1200)

            check("the screen links to Job Creation",
                  page.locator("a[href$='JobCreation']").count() > 0)
            check("a discovered project can be published through Job Creation",
                  page.locator("a[href*='JobCreation?hop_project=']").count() > 0)
            check("Apache Hop is in the sidebar",
                  page.locator(".main-sidebar a[href$='hop']").count() > 0)

            # The Remove button is the only destructive control on this screen.
            # Exercising the modal catches "the button does nothing"; the delete
            # itself is covered by test-hop-job-creation-e2e.py, which owns the
            # project it removes.
            if page.locator(".hop-delete-project").count() > 0:
                page.locator(".hop-delete-project").first.click()
                page.wait_for_timeout(500)
                check("the remove confirmation opens", page.locator("#hopDeleteModal").first.is_visible())
                check("it names the project being removed",
                      page.locator("#hopDeleteName").inner_text().strip() != "")
                check("it offers to keep or delete the files on disk",
                      page.locator("#hopDeleteFiles").count() == 1
                      and not page.locator("#hopDeleteFiles").is_checked())
                page.locator("#hopDeleteModal button[data-dismiss='modal']").first.click()
                page.wait_for_timeout(500)
                check("cancelling closes the confirmation without deleting",
                      not page.locator("#hopDeleteModal").first.is_visible()
                      and page.locator(".hop-delete-project").count() > 0)
            else:
                print("  SKIP  no Hop project is registered, so the remove control was not exercised")

            stage("Job Creation: the Apache Hop branch")
            page.goto(BASE_URL + "/JobCreation?hop_project=jobseeker", wait_until="networkidle")
            page.wait_for_timeout(400)
            check("Use in job preselects the shared Hop project",
                  page.locator(".hopSourceForm").first.is_visible()
                  and page.eval_on_selector("#linuxScriptType", "el => el.value") == "hop"
                  and page.eval_on_selector("#hopSourceMode", "el => el.value") == "path"
                  and page.eval_on_selector("#hopProjectPath", "el => el.value") == "hop/projects/jobseeker")

            page.goto(BASE_URL + "/JobCreation", wait_until="networkidle")
            check("the Apache Hop card is enabled",
                  page.locator("[data-linux-etl-choice='hop']").count() == 1
                  and not page.locator("[data-linux-etl-choice='hop']").first.is_disabled())
            check("the coming-soon placeholder is gone", "Coming soon" not in page.content())

            page.fill("#job_name", "ui-check-hop")
            hop_choice = page.locator("[data-linux-etl-choice='hop']").first
            # The deep-link selection is autosaved as a normal browser draft.
            # Depending on timing, the next navigation can therefore arrive
            # with Hop already active. Move to the ETL chooser deterministically
            # without coupling this test to that cache timing.
            for _ in range(2):
                if hop_choice.is_visible():
                    break
                page.click("[data-execution-family='etl']")
                page.wait_for_timeout(300)
            check("the Apache Hop runtime choice is visible", hop_choice.is_visible())
            page.click("[data-linux-etl-choice='hop']")
            page.wait_for_timeout(400)

            check("the Apache Hop panel opens", page.locator(".hopSourceForm").first.is_visible())
            check("the script type is Apache Hop", page.eval_on_selector("#linuxScriptType", "el => el.value") == "hop")
            for field in ("#hopSourceMode", "#hopEntryFile", "#hopEngine", "#hopRunConfig", "#hopLogLevel", "#hopParameters"):
                check("visible field %s" % field, page.locator(field).first.is_visible())

            panel = page.locator(".hopSourceForm").first.bounding_box()
            form = page.locator("#linuxColumn, .content-wrapper").first.bounding_box()
            check("the Apache Hop panel is inside the form, not floating off it",
                  panel is not None and form is not None
                  and panel["x"] >= form["x"] - 2
                  and panel["x"] + panel["width"] <= form["x"] + form["width"] + 2,
                  "panel=%s container=%s" % (panel, form))

            check("the Python runtime selector is replaced by the Hop engine",
                  not page.locator(".pythonRuntimeForm").first.is_visible())
            check("container limits are offered for the container engine",
                  page.locator(".pythonContainerLimits").first.is_visible())

            page.select_option("#hopEngine", "server")
            page.wait_for_timeout(300)
            check("container limits disappear for the Hop Server engine",
                  not page.locator(".pythonContainerLimits").first.is_visible())
            page.select_option("#hopEngine", "container")
            page.wait_for_timeout(200)

            page.select_option("#hopSourceMode", "sample")
            page.wait_for_timeout(300)
            check("the sample picker appears", page.locator(".hopSampleColumn").first.is_visible())
            check("starter projects are offered", page.locator("#hopSample option").count() >= 3)
            check("choosing a sample fills the entry file",
                  page.eval_on_selector("#hopEntryFile", "el => el.value") != "")

            page.select_option("#hopSourceMode", "path")
            page.wait_for_timeout(300)
            check("the repository path field appears", page.locator(".hopPathColumn").first.is_visible())
            check("the upload dropzone is hidden for a repository path",
                  page.locator("#dropzone").count() == 0 or not page.locator("#dropzone").first.is_visible())

            page.select_option("#hopSourceMode", "upload")
            page.wait_for_timeout(500)
            check("the upload dropzone returns for an upload",
                  page.locator("#dropzone").count() > 0)
            check("the review summary names Apache Hop",
                  "Apache Hop" in page.locator("body").inner_text())

            # Switching to another runtime family must not leave the Hop panel behind.
            page.click("[data-execution-family='shell']")
            page.wait_for_timeout(400)
            page.click("[data-linux-shell-choice='command']")
            page.wait_for_timeout(400)
            check("the Apache Hop panel closes when another runtime is chosen",
                  not page.locator(".hopSourceForm").first.is_visible())

            stage("Console and network")
            real_errors = [error for error in errors if not any(token in error for token in IGNORED_CONSOLE)]
            check("no browser errors and no failed requests", not real_errors, "; ".join(real_errors[:4]))

            page.screenshot(path="/tmp/jobseeker-hop-jobcreation.png", full_page=False)
            context.close()
            browser.close()
    finally:
        remove_hop_job_fixture()

    print("\n%d passed, %d failed" % (len(PASSED), len(FAILED)))
    for name, detail in FAILED:
        print("  FAILED: %s%s" % (name, (" - " + detail) if detail else ""))
    return 1 if FAILED else 0


if __name__ == "__main__":
    sys.exit(main())
