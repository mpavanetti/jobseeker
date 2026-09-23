#!/usr/bin/env python3
"""End-to-end check of the task DAG, against a running JobSeeker stack.

Creates a disposable inline-Python job whose main.py declares a real task graph
with a retry, a deliberate failure, a FAILURE-triggered handler and an
ALWAYS-triggered cleanup, runs it on Jenkins, and then verifies that:

* the runtime recorded one job_task_runs row per attempt, with the retry visible
* jobView/tasks returns the stored graph plus the per-task outcome of that build
* the graph is addressable by Jenkins build number, not just "the latest run"
* a second build with JOBSEEKER_DAG_RESUME re-runs only what did not succeed

Environment:
  JOBSEEKER_E2E_URL       default http://localhost
  JOBSEEKER_E2E_EMAIL     default admin@example.com
  JOBSEEKER_E2E_PASSWORD  default 123456
"""
import html
import http.cookiejar
import json
import os
import shutil
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
ROOT = Path(__file__).resolve().parents[1]
SDK_SOURCE = ROOT / "application/third_party/python/jobseeker_sdk"
SDK_TARGET = ROOT / "repository/python/lib/jobseeker-sdk"
BUILD_TIMEOUT_SECONDS = 240

MAIN_PY = '''from jobseeker import dag
from jobseeker.dag import SkipTask


@dag.task(id="extract", produces=["e2e_rows"], description="Read the batch")
def extract(ctx):
    ctx.push("rows", 7)
    ctx.progress(total=7, processed=7, msg="Batch read")


@dag.task(id="flaky", depends_on=["extract"], retries=1, description="Fails once, then passes")
def flaky(ctx):
    if ctx.attempt == 1:
        raise RuntimeError("first attempt always fails")
    ctx.progress(total=7, processed=6, msg="Recovered on retry")


@dag.task(id="branch_not_taken", depends_on=["extract"], retries=2, description="Skips itself")
def branch_not_taken(ctx):
    raise SkipTask("this batch does not need a full reload")


@dag.task(id="after_branch", depends_on=["branch_not_taken"], description="Never runs")
def after_branch(ctx):
    raise AssertionError("a task below a skipped branch must not run")


@dag.task(id="doomed", depends_on=["extract"], description="Always fails")
def doomed(ctx):
    raise ValueError("this task is meant to fail")


@dag.task(id="blocked", depends_on=["doomed"], description="Never runs")
def blocked(ctx):
    raise AssertionError("a task whose upstream failed must not run")


@dag.task(id="alert", depends_on=["doomed"], trigger="FAILURE", description="Handles the failure")
def alert(ctx):
    ctx.log("failure handler ran")


@dag.task(id="cleanup", depends_on=["flaky", "blocked"], trigger="ALWAYS", description="Always runs")
def cleanup(ctx):
    ctx.log("cleanup ran")


if __name__ == "__main__":
    dag.run()
'''


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
            values = list(fields.items())
            if csrf:
                values.append(("csrf_test_name", self.csrf()))
            data = urllib.parse.urlencode(values).encode("utf-8")
        elif csrf:
            path += ("&" if "?" in path else "?") + urllib.parse.urlencode({"csrf_test_name": self.csrf()})
        if content_type:
            headers["Content-Type"] = content_type
        request = urllib.request.Request(BASE_URL + path, data=data, headers=headers, method=method)
        try:
            with self.opener.open(request, timeout=90) as response:
                return response.status, response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as error:
            status, text = error.code, error.read().decode("utf-8", "replace")
        if status not in expected:
            raise AssertionError("%s %s -> %s: %s" % (method, path, status, text[:400]))
        return status, text


def proxy(path):
    return "/jenkins/proxy?" + urllib.parse.urlencode({"path": path})


def job_config(environment, source_path):
    """Mirror the DAG environment the real generated command exports.

    Keeping this in step with JobCreationExecutionTrait::dagRuntimeLines() is the
    point of the test: if the passthrough is dropped there, resume stops working
    and this fixture keeps passing, so the wiring is also asserted statically in
    scripts/test-job-task-graph.js.
    """
    command = "\n".join([
        "set -e",
        "export PYTHONUNBUFFERED=1",
        "export JOBSEEKER_ENVIRONMENT=\"${ENVIRONMENT:-%s}\"" % environment,
        "export JOBSEEKER_JOB_NAME=\"${JOB_NAME:-}\"",
        "export PYTHONPATH='/php/repository/python/lib/jobseeker-sdk/src'",
        "export JOBSEEKER_DAG_RESUME=\"${JOBSEEKER_DAG_RESUME:-}\"",
        "export JOBSEEKER_DAG_TASKS=\"${JOBSEEKER_DAG_TASKS:-}\"",
        "export JOBSEEKER_DAG_MAX_PARALLEL=\"${JOBSEEKER_DAG_MAX_PARALLEL:-4}\"",
        "export JOBSEEKER_DAG_FAIL_FAST=\"${JOBSEEKER_DAG_FAIL_FAST:-0}\"",
        "export JOBSEEKER_DAG_STATE=\"${JOBSEEKER_DAG_STATE:-1}\"",
        "python3 -u '%s/main.py'" % source_path,
    ])
    return """<?xml version="1.1" encoding="UTF-8"?>
<project>
  <description>Disposable task DAG E2E fixture.</description>
  <properties>
    <hudson.model.ParametersDefinitionProperty>
      <parameterDefinitions>
        <hudson.model.StringParameterDefinition>
          <name>ENVIRONMENT</name><defaultValue>%s</defaultValue><trim>true</trim>
        </hudson.model.StringParameterDefinition>
        <hudson.model.StringParameterDefinition>
          <name>JOBSEEKER_DAG_RESUME</name><defaultValue></defaultValue><trim>true</trim>
        </hudson.model.StringParameterDefinition>
        <hudson.model.StringParameterDefinition>
          <name>JOBSEEKER_DAG_TASKS</name><defaultValue></defaultValue><trim>true</trim>
        </hudson.model.StringParameterDefinition>
      </parameterDefinitions>
    </hudson.model.ParametersDefinitionProperty>
  </properties>
  <scm class="hudson.scm.NullSCM"/><canRoam>true</canRoam><disabled>false</disabled>
  <triggers/><concurrentBuild>false</concurrentBuild>
  <builders><hudson.tasks.Shell><command>%s</command></hudson.tasks.Shell></builders>
  <publishers/><buildWrappers/>
</project>
""" % (html.escape(environment), html.escape(command))


def sync_sdk():
    """Publish the app's SDK into the repository, as saving a job would."""
    if SDK_TARGET.exists():
        shutil.rmtree(SDK_TARGET)
    SDK_TARGET.parent.mkdir(parents=True, exist_ok=True)
    shutil.copytree(SDK_SOURCE, SDK_TARGET)


def run_build(browser, job_name, parameters):
    """Trigger one build and wait for it to finish. Returns its build number."""
    before = last_build_number(browser, job_name)
    browser.request(
        proxy("job/%s/buildWithParameters?%s" % (job_name, urllib.parse.urlencode(parameters))),
        method="POST", csrf=True, expected=(200, 201, 302),
    )

    deadline = time.time() + BUILD_TIMEOUT_SECONDS
    while time.time() < deadline:
        time.sleep(2)
        number = last_build_number(browser, job_name)
        if number <= before:
            continue
        _, body = browser.request(proxy("job/%s/%d/api/json?tree=building,result" % (job_name, number)),
                                  expected=(200, 404))
        try:
            state = json.loads(body)
        except json.JSONDecodeError:
            continue
        if not state.get("building") and state.get("result"):
            return number, state["result"]
    raise AssertionError("build of %s did not finish within %ds" % (job_name, BUILD_TIMEOUT_SECONDS))


def wait_for_build(browser, job_name, number):
    """Wait for a build whose number is already known, and return its result."""

    deadline = time.time() + BUILD_TIMEOUT_SECONDS
    while time.time() < deadline:
        time.sleep(2)
        _, body = browser.request(proxy("job/%s/%d/api/json?tree=building,result" % (job_name, number)),
                                  expected=(200, 404))
        try:
            state = json.loads(body)
        except json.JSONDecodeError:
            continue
        if not state.get("building") and state.get("result"):
            return number, state["result"]
    raise AssertionError("build #%d of %s did not finish within %ds" % (number, job_name, BUILD_TIMEOUT_SECONDS))


def last_build_number(browser, job_name):
    _, body = browser.request(proxy("job/%s/api/json?tree=lastBuild[number]" % job_name), expected=(200, 404))
    try:
        payload = json.loads(body)
    except json.JSONDecodeError:
        return 0
    return int((payload.get("lastBuild") or {}).get("number") or 0)


def query(sql):
    """Read job_task_runs straight from MariaDB, the way the runtime wrote it."""
    result = subprocess.run(
        ["docker", "exec", "jobseeker-mariadb-1", "mysql", "-umysql", "-pmysql", "jobseeker", "-Nse", sql],
        capture_output=True, text=True, check=False,
    )
    if result.returncode != 0:
        raise AssertionError("database read failed: %s" % result.stderr.strip())
    return [line.split("\t") for line in result.stdout.strip().splitlines() if line.strip()]


def statuses_for(payload):
    return {task["id"]: task["status"] for task in payload["tasks"]}


def main():
    run_id = uuid.uuid4().hex[:8]
    job_name = "e2e-task-dag-%s" % run_id
    source_dir = ROOT / "repository" / "python" / "inline" / job_name
    browser = Browser()
    created = False
    checks = 0

    def ok(label, condition):
        nonlocal checks
        assert condition, label
        checks += 1

    browser.request("/")
    _, login = browser.request("/loginMe", method="POST",
                               fields={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD}, csrf=True)
    assert "logout" in login.lower(), "admin login failed"

    try:
        sync_sdk()
        source_dir.mkdir(parents=True)
        (source_dir / "main.py").write_text(MAIN_PY, encoding="utf-8")

        browser.request(proxy("createItem?name=" + job_name), method="POST",
                        body=job_config("DEV", "/php/repository/python/inline/" + job_name).encode("utf-8"),
                        content_type="application/xml", csrf=True, expected=(200, 302))
        created = True

        # --- First build: the graph runs, one task fails on purpose ----------
        number, result = run_build(browser, job_name, {"ENVIRONMENT": "DEV"})
        ok("a DAG with a failing task must fail the build, got %s" % result, result == "FAILURE")

        rows = query("SELECT task_key, attempt, status FROM job_task_runs "
                     "WHERE job_name = '%s' ORDER BY id" % job_name)
        ok("the runtime recorded task rows", len(rows) > 0)

        final = {}
        for task_key, attempt, status in rows:
            if task_key not in final or int(attempt) >= final[task_key][0]:
                final[task_key] = (int(attempt), status)

        ok("extract succeeded", final["extract"][1] == "SUCCESS")
        ok("the retried task is recorded on its second attempt", final["flaky"] == (2, "SUCCESS"))
        ok("the first, failed attempt is kept as its own row",
           ("flaky", "1", "FAILURE") in [tuple(row) for row in rows])
        ok("the doomed task failed", final["doomed"][1] == "FAILURE")
        ok("a task whose upstream failed is UPSTREAM_FAILED", final["blocked"][1] == "UPSTREAM_FAILED")
        ok("the failure handler ran", final["alert"][1] == "SUCCESS")
        ok("the always-triggered cleanup ran", final["cleanup"][1] == "SUCCESS")
        ok("a branch that skipped itself is SKIPPED", final["branch_not_taken"][1] == "SKIPPED")
        ok("a self-skip is not retried", final["branch_not_taken"][0] == 1)
        ok("a skip propagates downstream", final["after_branch"][1] == "SKIPPED")

        ok("every row carries the Jenkins build number",
           all(int(row[0]) == number for row in
               query("SELECT DISTINCT build_number FROM job_task_runs WHERE job_name = '%s'" % job_name)))

        graph_rows = query("SELECT source, task_count FROM job_task_graphs WHERE job_name = '%s'" % job_name)
        ok("the runtime published its manifest", graph_rows and graph_rows[0][0] == "runtime")
        ok("the manifest has every task", graph_rows and int(graph_rows[0][1]) == 8)

        # --- The read endpoint returns what the UI draws ---------------------
        _, body = browser.request("/jobView/tasks?" + urllib.parse.urlencode(
            {"job": job_name, "environment": "DEV"}))
        payload = json.loads(body)
        ok("the endpoint answers", payload["ok"] is True)
        ok("the stored graph is returned", len(payload["graph"]["tasks"]) == 8)
        ok("the graph is marked as stored, not scanned", payload["stored"] is True)
        ok("edges are returned for the renderer", len(payload["graph"]["edges"]) == 8)
        ok("per-task outcomes are returned", statuses_for(payload) == {
            "extract": "SUCCESS", "flaky": "SUCCESS", "doomed": "FAILURE",
            "blocked": "UPSTREAM_FAILED", "alert": "SUCCESS", "cleanup": "SUCCESS",
            "branch_not_taken": "SKIPPED", "after_branch": "SKIPPED",
        })
        ok("a retried task reports its final attempt",
           next(t for t in payload["tasks"] if t["id"] == "flaky")["attempt"] == 2)
        ok("durations are recorded",
           all(t["durationMs"] is not None for t in payload["tasks"] if t["status"] == "SUCCESS"))
        ok("the run is listed", any(run["buildNumber"] == number for run in payload["runs"]))

        run_key = payload["runKey"]
        ok("a run key was resolved", bool(run_key))

        _, body = browser.request("/jobExecution/tasks?" + urllib.parse.urlencode(
            {"job": job_name, "environment": "DEV", "build": number}))
        by_build = json.loads(body)
        ok("a build number selects the same run", by_build["runKey"] == run_key)

        # --- Second build: resume only re-runs what did not succeed ----------
        number_two, result_two = run_build(browser, job_name,
                                           {"ENVIRONMENT": "DEV", "JOBSEEKER_DAG_RESUME": run_key})
        ok("the resumed build still fails on the task that always fails, got %s" % result_two,
           result_two == "FAILURE")

        resumed = query("SELECT task_key, attempt, status FROM job_task_runs "
                        "WHERE job_name = '%s' AND build_number = %d ORDER BY id" % (job_name, number_two))
        resumed_tasks = {row[0] for row in resumed}
        ok("a task that already succeeded is not re-run", "extract" not in resumed_tasks)
        ok("the retried task is not re-run either", "flaky" not in resumed_tasks)
        ok("the task that failed is re-run", "doomed" in resumed_tasks)

        _, body = browser.request(proxy("job/%s/%d/consoleText" % (job_name, number_two)))
        ok("the console says what was restored", "restored from run" in body)
        ok("the console prints one marker per task", body.count("[JobSeeker Task]") >= 4)
        ok("the console prints the run outcome", "[JobSeeker DAG] finish | FAILURE" in body)

        # --- Tasks are visible in TMF, and readable as one run ---------------
        tmf_rows = query(
            "SELECT task_key, status, records_total, records_processed FROM tmf "
            "WHERE run_key = '%s' ORDER BY id" % run_key
        )
        ok("every task that ran opened a TMF transaction", len(tmf_rows) >= 5)
        tmf_by_task = {row[0]: row for row in tmf_rows}
        ok("the extract transaction is linked to its task", "extract" in tmf_by_task)
        ok("a successful task closes its transaction", tmf_by_task["extract"][1] == "ready")
        ok("the reported row counts are stored",
           tmf_by_task["extract"][2] == "7" and tmf_by_task["extract"][3] == "7")
        ok("a failing task records an error", tmf_by_task["doomed"][1] == "error")
        ok("a task that skipped itself cancels its transaction",
           tmf_by_task["branch_not_taken"][1] == "cancelled")
        ok("a task that never ran opens no transaction", "blocked" not in tmf_by_task)

        ok("every task transaction names the job",
           all(row[0] == job_name for row in
               query("SELECT DISTINCT job_name FROM tmf WHERE run_key = '%s'" % run_key)))

        _, body = browser.request("/tmf/taskRun/" + urllib.parse.quote(run_key))
        ok("the Results page can be opened for one run", "Transaction Runs" in body)
        ok("it says which run it is scoped to", run_key in body)
        ok("and lists that run's tasks", "extract" in body and "doomed" in body)

        # --- The graph carries the TMF counts --------------------------------
        extract_task = next(t for t in payload["tasks"] if t["id"] == "extract")
        ok("row counts reach the task graph",
           extract_task["recordsProcessed"] == 7 and extract_task["recordsTotal"] == 7)
        ok("the task graph links to the transaction", bool(extract_task["tmfInstanceId"]))
        blocked_task = next(t for t in payload["tasks"] if t["id"] == "blocked")
        ok("a task that never ran reports no counts", blocked_task["recordsProcessed"] is None)

        # --- A build that has written nothing shows itself, not the last run --
        future_build = number_two + 50
        _, body = browser.request("/jobExecution/tasks?" + urllib.parse.urlencode(
            {"job": job_name, "environment": "DEV", "build": future_build}))
        queued = json.loads(body)
        ok("a build with no rows yet is pending", queued["runState"] == "pending")
        ok("and is not given another run's outcome", queued["tasks"] == [])
        ok("and names the build that was asked for", queued["pendingBuild"] == future_build)
        ok("while still showing the declared graph", len(queued["graph"]["tasks"]) == 8)

        # A caller that names no build still gets the latest run, as before.
        _, body = browser.request("/jobView/tasks?" + urllib.parse.urlencode(
            {"job": job_name, "environment": "DEV"}))
        latest = json.loads(body)
        ok("asking for no particular build still answers with the latest run",
           latest["runState"] == "finished" and len(latest["tasks"]) > 0)
        ok("a manager may re-run from the panel", latest["canRun"] is True)

        # --- Re-running from the panel ---------------------------------------
        _, body = browser.request("/jobExecution/runTasks", method="POST", csrf=True, fields={
            "job": job_name, "environment": "DEV", "mode": "tasks", "tasks": "extract"})
        queued_run = json.loads(body)
        ok("running a single task is accepted", queued_run["ok"] is True)
        ok("it reports the build it queued", queued_run["expectedBuild"] is not None)
        ok("it reports the exact Jenkins queue item", queued_run["queueId"] is not None)

        number_three, result_three = wait_for_build(browser, job_name, queued_run["expectedBuild"])
        ok("a single-task run succeeds, got %s" % result_three, result_three == "SUCCESS")
        # Every task is recorded for the run - the ones that were not selected as
        # SKIPPED - so the graph of that build shows the whole shape rather than
        # a single lonely node.
        subset = query("SELECT task_key, status FROM job_task_runs "
                       "WHERE job_name = '%s' AND build_number = %d" % (job_name, number_three))
        executed = sorted(row[0] for row in subset if row[1] != "SKIPPED")
        ok("only the requested task ran, got %r" % executed, executed == ["extract"])
        ok("the tasks that were not selected are shown as skipped",
           len([row for row in subset if row[1] == "SKIPPED"]) == 7)

        _, body = browser.request("/jobExecution/runTasks", method="POST", csrf=True, expected=(400,),
                                  fields={"job": job_name, "environment": "DEV",
                                          "mode": "tasks", "tasks": "not-a-task"})
        ok("a task the job does not declare is refused", json.loads(body)["ok"] is False)

        _, body = browser.request("/jobExecution/runTasks", method="POST", csrf=True, expected=(400,),
                                  fields={"job": job_name, "environment": "DEV",
                                          "mode": "resume", "run": "someone-elses-run"})
        ok("a run key from another job is refused", json.loads(body)["ok"] is False)

        print("Task DAG end-to-end checks passed (%d assertions)." % checks)
    finally:
        if created:
            # Jenkins refuses a bare doDelete through the proxy; job removal has
            # to go through the environment-scoped JobSeeker endpoint, which also
            # clears the job's stored task graph.
            browser.request("/DeleteJob/deleteJobs?environment=DEV", method="POST",
                            fields={"jobs": job_name, "delete_repositories": "0"},
                            csrf=True, expected=(200,))
        shutil.rmtree(source_dir, ignore_errors=True)
        subprocess.run(
            ["docker", "exec", "jobseeker-mariadb-1", "mysql", "-umysql", "-pmysql", "jobseeker", "-e",
             "DELETE FROM job_task_runs WHERE job_name = '%s'; "
             "DELETE FROM job_task_graphs WHERE job_name = '%s'; "
             "DELETE FROM tmf WHERE job_name = '%s'; "
             "DELETE FROM tmf_error WHERE job_name = '%s';" % (job_name, job_name, job_name, job_name)],
            capture_output=True, text=True, check=False,
        )


if __name__ == "__main__":
    main()
