#!/usr/bin/env python3
"""Create a real Apache Hop Jenkins job through the Job Creation form.

The form-to-Jenkins path is where a Hop job is actually born, and a fixture that
builds its own minimal config.xml can pass while real job creation is broken.
This drives the live form, reads the Jenkins configuration it produced, asserts
the generated shell builder, then deletes the job and the project it copied.

Needs the running stack:

    python3 scripts/test-hop-job-creation-e2e.py

Environment:
    JOBSEEKER_UI_URL          base URL of the app (default http://127.0.0.1)
    JOBSEEKER_UI_EMAIL        default admin@example.com
    JOBSEEKER_UI_PASSWORD     default 123456
    JOBSEEKER_JENKINS_URL     default http://127.0.0.1:8080
    JENKINS_ADMIN_ID          default jobseeker
    JENKINS_ADMIN_PASSWORD    default jobseeker
    JOBSEEKER_HOP_E2E_ROOT    repository root to inspect (default ./repository)
"""

from __future__ import annotations

import base64
import http.cookiejar
import io
import json
import os
import re
import shutil
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zipfile

BASE = os.environ.get("JOBSEEKER_UI_URL", "http://127.0.0.1").rstrip("/")
EMAIL = os.environ.get("JOBSEEKER_UI_EMAIL", "admin@example.com")
PASSWORD = os.environ.get("JOBSEEKER_UI_PASSWORD", "123456")
JENKINS = os.environ.get("JOBSEEKER_JENKINS_URL", "http://127.0.0.1:8080").rstrip("/")
JENKINS_AUTH = "Basic " + base64.b64encode(
    ("%s:%s" % (os.environ.get("JENKINS_ADMIN_ID", "jobseeker"),
                os.environ.get("JENKINS_ADMIN_PASSWORD", "jobseeker"))).encode()
).decode()
REPOSITORY = os.path.abspath(os.environ.get(
    "JOBSEEKER_HOP_E2E_ROOT",
    os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "repository"),
))
JOB = "hop-e2e-form-%s" % uuid.uuid4().hex[:6]
UPLOAD_JOB = "hop-e2e-upload-%s" % uuid.uuid4().hex[:6]
ARCHIVE_JOB = "hop-e2e-archive-%s" % uuid.uuid4().hex[:6]
UNSAFE_ARCHIVE_JOB = "hop-e2e-unsafe-%s" % uuid.uuid4().hex[:6]
BUILD_TIMEOUT = int(os.environ.get("JOBSEEKER_HOP_BUILD_TIMEOUT", "360"))

PASSED: list = []
FAILED: list = []

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def check(name: str, condition: bool, detail: str = "") -> None:
    if condition:
        PASSED.append(name)
        print("  PASS  %s" % name)
    else:
        FAILED.append((name, detail))
        print("  FAIL  %s%s" % (name, (" - " + detail) if detail else ""))


def csrf_token() -> str:
    for cookie in jar:
        if cookie.name == "csrf_cookie_name":
            return cookie.value
    return ""


def app_post(path: str, fields: dict):
    fields = dict(fields)
    token = csrf_token()
    if token:
        fields["csrf_test_name"] = token
    request = urllib.request.Request(
        BASE + path, data=urllib.parse.urlencode(fields, doseq=True).encode("utf-8"), method="POST"
    )
    try:
        with opener.open(request, timeout=180) as response:
            return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode("utf-8", "replace")


def app_get(path: str):
    try:
        with opener.open(urllib.request.Request(BASE + path), timeout=180) as response:
            return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode("utf-8", "replace")


def app_upload(path: str, filename: str, content: bytes):
    """Send the same multipart field Dropzone uses, including CI's CSRF token."""
    boundary = "----JobSeekerHop%s" % uuid.uuid4().hex
    chunks = []
    token = csrf_token()
    if token:
        chunks.extend([
            ("--%s\r\n" % boundary).encode(),
            b'Content-Disposition: form-data; name="csrf_test_name"\r\n\r\n',
            token.encode(),
            b"\r\n",
        ])
    chunks.extend([
        ("--%s\r\n" % boundary).encode(),
        ('Content-Disposition: form-data; name="file"; filename="%s"\r\n' % filename).encode(),
        b"Content-Type: application/octet-stream\r\n\r\n",
        content,
        b"\r\n",
        ("--%s--\r\n" % boundary).encode(),
    ])
    request = urllib.request.Request(BASE + path, data=b"".join(chunks), method="POST")
    request.add_header("Content-Type", "multipart/form-data; boundary=%s" % boundary)
    try:
        with opener.open(request, timeout=180) as response:
            return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode("utf-8", "replace")


def hop_server(path: str, fields: dict = None):
    """Talk to the Hop Server the way the Apache Hop GUI would."""

    url = os.environ.get("JOBSEEKER_HOP_SERVER_PUBLIC_URL", "http://127.0.0.1:8181").rstrip("/") + path
    credentials = base64.b64encode(
        ("%s:%s" % (os.environ.get("JOBSEEKER_HOP_SERVER_USER", "cluster"),
                    os.environ.get("JOBSEEKER_HOP_SERVER_PASSWORD", "cluster"))).encode()
    ).decode()
    body = urllib.parse.urlencode(fields or {}).encode("utf-8") if fields is not None else None
    request = urllib.request.Request(
        url, data=body, method="POST" if body is not None else "GET",
        headers={"Authorization": "Basic " + credentials},
    )
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode("utf-8", "replace")
    except urllib.error.URLError as error:
        return 0, str(error)


def jenkins(path: str, method: str = "GET", data=None, headers=None):
    request = urllib.request.Request(JENKINS + path, data=data, method=method)
    request.add_header("Authorization", JENKINS_AUTH)
    for name, value in (headers or {}).items():
        request.add_header(name, value)
    try:
        with opener.open(request, timeout=120) as response:
            return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode("utf-8", "replace")


def delete_jenkins_job(name: str) -> int:
    status, body = jenkins("/crumbIssuer/api/json")
    headers = {}
    if status == 200:
        crumb = json.loads(body)
        headers[crumb["crumbRequestField"]] = crumb["crumb"]
    code, _ = jenkins("/job/%s/doDelete" % urllib.parse.quote(name), method="POST", data=b"", headers=headers)
    return code


def delete_tmf_rows(names) -> None:
    """Remove only the transaction history produced by this test run."""
    try:
        import mysql.connector  # type: ignore
    except ImportError:
        return

    connection = mysql.connector.connect(
        host=os.environ.get("JOBSEEKER_DB_HOST", "127.0.0.1"),
        port=int(os.environ.get("JOBSEEKER_DB_PORT", "3306")),
        user=os.environ.get("JOBSEEKER_DB_USER", "mysql"),
        password=os.environ.get("JOBSEEKER_DB_PASSWORD", "mysql"),
        database=os.environ.get("JOBSEEKER_DB_NAME", "jobseeker"),
    )
    try:
        cursor = connection.cursor()
        for name in names:
            cursor.execute("DELETE FROM tmf_error WHERE job_name = %s", (name,))
            cursor.execute("DELETE FROM tmf WHERE job_name = %s", (name,))
        connection.commit()
        cursor.close()
    finally:
        connection.close()


def tmf_rows_for(job_name: str):
    """(instance_id, status, dimension) for one TMF job name, newest first."""

    try:
        import mysql.connector  # type: ignore
    except ImportError:
        return []

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
            "SELECT instance_id, status, dimension FROM tmf WHERE job_name = %s ORDER BY id DESC",
            (job_name,),
        )
        rows = cursor.fetchall()
        cursor.close()
        return rows
    finally:
        connection.close()


def forget_hop_executions(name: str) -> None:
    """Drop this run's rows from the Hop Server execution history."""

    if not name:
        return
    try:
        import mysql.connector  # type: ignore
    except ImportError:
        return

    connection = mysql.connector.connect(
        host=os.environ.get("JOBSEEKER_DB_HOST", "127.0.0.1"),
        port=int(os.environ.get("JOBSEEKER_DB_PORT", "3306")),
        user=os.environ.get("JOBSEEKER_DB_USER", "mysql"),
        password=os.environ.get("JOBSEEKER_DB_PASSWORD", "mysql"),
        database=os.environ.get("JOBSEEKER_DB_NAME", "jobseeker"),
    )
    try:
        cursor = connection.cursor()
        cursor.execute("DELETE FROM hop_server_executions WHERE name = %s", (name,))
        connection.commit()
        cursor.close()
    finally:
        connection.close()


def jenkins_post_headers() -> dict:
    status, body = jenkins("/crumbIssuer/api/json")
    if status != 200:
        return {}
    crumb = json.loads(body)
    return {crumb["crumbRequestField"]: crumb["crumb"]}


def run_jenkins_job(name: str):
    payload = urllib.parse.urlencode({"ENVIRONMENT": "DEV"}).encode("utf-8")
    status, body = jenkins(
        "/job/%s/buildWithParameters" % urllib.parse.quote(name),
        method="POST",
        data=payload,
        headers=dict(jenkins_post_headers(), **{"Content-Type": "application/x-www-form-urlencoded"}),
    )
    if status not in (200, 201, 202, 302, 303):
        return status, {}, body

    deadline = time.time() + BUILD_TIMEOUT
    last = {}
    while time.time() < deadline:
        code, response = jenkins(
            "/job/%s/lastBuild/api/json?tree=number,building,result,url" % urllib.parse.quote(name)
        )
        if code == 200:
            last = json.loads(response)
            if not last.get("building") and last.get("result"):
                console_code, console = jenkins(
                    "/job/%s/%s/consoleText" % (urllib.parse.quote(name), last.get("number"))
                )
                return console_code, last, console
        time.sleep(2)
    return 408, last, "Timed out after %d seconds" % BUILD_TIMEOUT


def main() -> int:
    print("JobSeeker Apache Hop job creation, end to end")
    print("app     : %s" % BASE)
    print("jenkins : %s" % JENKINS)
    print("job     : %s" % JOB)
    print("upload  : %s\n" % UPLOAD_JOB)

    app_get("/")
    status, _ = app_post("/loginMe", {"email": EMAIL, "password": PASSWORD})
    if status >= 400:
        print("Could not sign in (HTTP %d). Is the stack running?" % status)
        return 2

    # Exercise the archive path independently of Jenkins execution: exports
    # commonly wrap the project in a top-level folder, which must be flattened
    # and atomically installed before Job Creation can inspect it.
    sample_root = os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
        "application", "third_party", "hop", "samples", "platform-hello",
    )
    archive_buffer = io.BytesIO()
    with zipfile.ZipFile(archive_buffer, "w", zipfile.ZIP_DEFLATED) as archive:
        for directory, _, names in os.walk(sample_root):
            for name in names:
                source = os.path.join(directory, name)
                relative = os.path.relpath(source, sample_root).replace(os.sep, "/")
                archive.write(source, "exported-project/" + relative)
    archive_status, archive_body = app_upload(
        "/jobCreation/do_upload/hop/%s" % urllib.parse.quote(ARCHIVE_JOB),
        "project.zip",
        archive_buffer.getvalue(),
    )
    archive_root = os.path.join(REPOSITORY, "hop", "projects", ARCHIVE_JOB.lower())
    check("a wrapped Hop project archive uploads atomically",
          archive_status == 200 and "project uploaded" in archive_body.lower(),
          "HTTP %s: %s" % (archive_status, archive_body[:200]))
    check("the uploaded archive is flattened to a runnable project",
          os.path.isfile(os.path.join(archive_root, "project-config.json"))
          and os.path.isfile(os.path.join(archive_root, "workflows", "main.hwf"))
          and not os.path.exists(os.path.join(archive_root, "exported-project")))

    invalid_buffer = io.BytesIO()
    with zipfile.ZipFile(invalid_buffer, "w", zipfile.ZIP_DEFLATED) as archive:
        archive.writestr("not-a-hop-project/readme.txt", "invalid replacement")
    invalid_status, _ = app_upload(
        "/jobCreation/do_upload/hop/%s" % urllib.parse.quote(ARCHIVE_JOB),
        "invalid-project.zip",
        invalid_buffer.getvalue(),
    )
    check("an invalid archive cannot overwrite the last working Hop project",
          invalid_status == 400
          and os.path.isfile(os.path.join(archive_root, "workflows", "main.hwf")))

    unsafe_buffer = io.BytesIO()
    with zipfile.ZipFile(unsafe_buffer, "w") as archive:
        link = zipfile.ZipInfo("exported-project/pipelines/linked.hpl")
        link.create_system = 3
        link.external_attr = 0o120777 << 16
        archive.writestr(link, "../../outside.hpl")
    unsafe_status, unsafe_body = app_upload(
        "/jobCreation/do_upload/hop/%s" % urllib.parse.quote(UNSAFE_ARCHIVE_JOB),
        "unsafe-project.zip",
        unsafe_buffer.getvalue(),
    )
    unsafe_root = os.path.join(REPOSITORY, "hop", "projects", UNSAFE_ARCHIVE_JOB.lower())
    check("an archive symlink is rejected without leaving a project behind",
          unsafe_status == 400
          and "unsupported link or special file" in unsafe_body.lower()
          and not os.path.exists(unsafe_root),
          "HTTP %s: %s" % (unsafe_status, unsafe_body[:200]))

    remove_status, remove_body = app_post(
        "/hop/delete", {"project": ARCHIVE_JOB.lower(), "remove_files": "1"}
    )
    try:
        remove_result = json.loads(remove_body)
    except json.JSONDecodeError:
        remove_result = {}
    check("the Hop catalog can safely remove an uploaded project and its files",
          remove_status == 200
          and remove_result.get("files_removed") is True
          and not os.path.exists(archive_root),
          "HTTP %s: %s" % (remove_status, remove_body[:250]))

    fields = {
        "job_name": JOB,
        "job_names": "",
        "description": "Apache Hop end-to-end check",
        "environment": "DEV",
        "checkEnvironment": "1",
        "timestamp": "1",
        "trigger_after_save": "0",
        "linuxCommand": "1",
        "linuxExecutionStrategy": "script",
        "linuxScriptType": "hop",
        "hopSourceMode": "sample",
        "hopSample": "platform-hello",
        "hopEntryFile": "workflows/main.hwf",
        "hopEngine": "container",
        "hopRunConfig": "local",
        "hopLogLevel": "Basic",
        "hopParameters": "BATCH_SIZE=250\nSOURCE=crm",
        "containerCpuLimit": "0.5",
        "containerMemoryLimitMb": "768",
        "checkBuild": "0",
        "abort": "0",
        "runJobCheck": "0",
        "emailCheck": "0",
        "editableEmailCheck": "0",
        "winCommand": "0",
        "pythonRuntimeMode": "local",
        "pythonVersion": "python3",
        "action": "0",
    }
    status, body = app_post("/jobCreation/send", fields)
    # Match the rendered flash element, not the string "alert-danger" wherever
    # it appears inside the page's own JavaScript.
    problem = re.search(r'<div class="alert alert-danger[^"]*">(.*?)</div>', body, re.DOTALL)
    if problem:
        print("  form error:", " ".join(re.sub(r"<[^>]+>", " ", problem.group(1)).split())[:250])

    status, xml = app_get("/jenkins/proxy?path=" + urllib.parse.quote("job/%s/config.xml" % JOB, safe=""))
    check("the form created a Jenkins job", status == 200 and "<project" in xml, "HTTP %s" % status)

    command = re.search(r"<command>(.*?)</command>", xml, re.DOTALL)
    shell = (command.group(1) if command else "")
    for entity, character in (("&quot;", '"'), ("&apos;", "'"), ("&lt;", "<"), ("&gt;", ">"), ("&amp;", "&")):
        shell = shell.replace(entity, character)

    check("the builder runs the JobSeeker Hop runner", "jobseeker-hop run" in shell)
    check("it checks the runner is installed before running", "command -v jobseeker-hop" in shell)
    check("it points at the project copied for this job", "/repository/hop/projects/%s" % JOB.lower() in shell)
    check("it names the selected entry file", "workflows/main.hwf" in shell)
    check("it selects the chosen engine", "--engine 'container'" in shell)
    # After a promotion the job runs with a different ENVIRONMENT, so the
    # environment must be read at build time, never baked into the command.
    check("the environment follows the Jenkins parameter", '--environment "$JOBSEEKER_ENVIRONMENT"' in shell)
    check("the job name follows the Jenkins parameter", '--job "$JOBSEEKER_JOB_NAME"' in shell)
    check("the repository root follows the worker", '--repository-root "$JOBSEEKER_REPOSITORY_ROOT"' in shell)
    check("the container limits from the form are applied",
          "--cpu-limit '0.5'" in shell and "--memory-limit-mb '768'" in shell)
    check("every parameter is passed and shell-escaped",
          "--param 'BATCH_SIZE=250'" in shell and "--param 'SOURCE=crm'" in shell)
    check("output is unbuffered so the console streams", "PYTHONUNBUFFERED=1" in shell)
    check("no credential is baked into the Jenkins configuration", "password" not in shell.lower())
    check("the ENVIRONMENT parameter defaults to the chosen environment",
          "<defaultValue>DEV</defaultValue>" in xml)

    project_root = os.path.join(REPOSITORY, "hop", "projects", JOB.lower())
    check("the starter project was copied into the repository", os.path.isdir(project_root), project_root)
    manifest_path = os.path.join(project_root, ".jobseeker-hop.json")
    if os.path.isfile(manifest_path):
        with open(manifest_path, "r", encoding="utf-8") as stream:
            manifest = json.load(stream)
        check("the manifest records this job's choices",
              manifest.get("entry_file") == "workflows/main.hwf"
              and manifest.get("engine") == "container"
              and manifest.get("parameters", {}).get("BATCH_SIZE") == "250",
              json.dumps(manifest))
        check("the copy is renamed to this job, not the starter",
              manifest.get("project") == JOB.lower(), str(manifest.get("project")))
        check("Hop's own descriptor is present",
              os.path.isfile(os.path.join(project_root, "project-config.json")))
        check("a local run configuration was ensured",
              os.path.isfile(os.path.join(project_root, "metadata", "pipeline-run-configuration", "local.json")))

    # The Apache Hop screen must show the job that was just created.
    status, screen = app_get("/hop")
    check("the Apache Hop screen lists the new project", status == 200 and JOB.lower() in screen, "HTTP %s" % status)
    check("the Apache Hop screen links the Jenkins job", JOB in screen)

    print("\nexecuting the generated Jenkins job")
    build_status, build, console = run_jenkins_job(JOB)
    check("Jenkins executes the generated Hop job", build_status == 200, "HTTP %s" % build_status)
    check("the Jenkins build succeeds", build.get("result") == "SUCCESS", str(build.get("result")))
    check("the Jenkins console contains the Hop runner output",
          "[JobSeeker] Apache Hop container run" in console and "[JobSeeker] Completed" in console,
          console[-500:] if console else "empty console")

    print("\nuploading and publishing a standalone Hop pipeline")
    pipeline_source = os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
        "application", "third_party", "hop", "samples", "platform-hello",
        "pipelines", "platform-variables.hpl",
    )
    with open(pipeline_source, "rb") as stream:
        upload_status, upload_body = app_upload(
            "/jobCreation/do_upload/hop/%s" % urllib.parse.quote(UPLOAD_JOB),
            "uploaded-pipeline.hpl",
            stream.read(),
        )
    check("a standalone .hpl can be uploaded through the live endpoint",
          upload_status == 200 and "pipeline uploaded" in upload_body.lower(),
          "HTTP %s: %s" % (upload_status, upload_body[:200]))

    upload_root = os.path.join(REPOSITORY, "hop", "projects", UPLOAD_JOB.lower())
    status, description = app_get("/hop/inspect?project=" + urllib.parse.quote(UPLOAD_JOB.lower()))
    try:
        uploaded_project = json.loads(description)
    except json.JSONDecodeError:
        uploaded_project = {}
    check("the upload is scaffolded as a native Hop project",
          status == 200
          and uploaded_project.get("valid") is True
          and "pipelines/uploaded-pipeline.hpl" in uploaded_project.get("entry_files", []),
          "HTTP %s: %s" % (status, description[:250]))
    check("the uploaded project receives local run metadata",
          os.path.isfile(os.path.join(upload_root, "project-config.json"))
          and os.path.isfile(os.path.join(upload_root, "metadata", "pipeline-run-configuration", "local.json")))

    upload_fields = dict(fields)
    upload_fields.update({
        "job_name": UPLOAD_JOB,
        "description": "Uploaded Apache Hop pipeline end-to-end check",
        "hopSourceMode": "upload",
        "hopSample": "",
        "hopEntryFile": "pipelines/uploaded-pipeline.hpl",
        "hopParameters": "",
    })
    upload_status, upload_page = app_post("/jobCreation/send", upload_fields)
    upload_problem = re.search(r'<div class="alert alert-danger[^"]*">(.*?)</div>', upload_page, re.DOTALL)
    check("the uploaded pipeline can be published as a Jenkins job",
          upload_status == 200 and upload_problem is None,
          "HTTP %s: %s" % (upload_status, " ".join(re.sub(r"<[^>]+>", " ", upload_problem.group(1)).split())[:250] if upload_problem else ""))

    status, upload_xml = app_get(
        "/jenkins/proxy?path=" + urllib.parse.quote("job/%s/config.xml" % UPLOAD_JOB, safe="")
    )
    check("the uploaded pipeline builder uses its scaffolded entry file",
          status == 200
          and "jobseeker-hop run" in upload_xml
          and "pipelines/uploaded-pipeline.hpl" in upload_xml,
          "HTTP %s" % status)

    upload_build_status, upload_build, upload_console = run_jenkins_job(UPLOAD_JOB)
    check("Jenkins executes the uploaded pipeline", upload_build_status == 200, "HTTP %s" % upload_build_status)
    check("the uploaded pipeline build succeeds", upload_build.get("result") == "SUCCESS",
          "%s: %s" % (upload_build.get("result"), upload_console[-500:]))

    # A pipeline published straight to the Hop Server from the Apache Hop GUI
    # never touches Jenkins. It used to be visible only inside Hop; the Apache
    # Hop screen now reconciles it into the catalog and into Transaction
    # Monitoring, which is where anyone would look for it.
    print("\nexternal Hop Server run")
    external_name = ""
    server_status, server_body = hop_server("/hop/status/?xml=Y")
    if server_status != 200:
        print("  SKIP  no Hop Server at the public URL; "
              "start it with docker compose --profile hop up -d hop-server")
    else:
        external_project = os.path.join(REPOSITORY, "hop", "projects", "jobseeker")
        external_file = "/php/repository/hop/projects/jobseeker/pipelines/platform-variables.hpl"
        if not os.path.isfile(os.path.join(external_project, "pipelines", "platform-variables.hpl")):
            print("  SKIP  the shared authoring project is not initialized")
        else:
            before_status, before_body = app_get("/hop/executions")
            known = set()
            if before_status == 200:
                known = {row["execution_id"] for row in json.loads(before_body).get("executions", [])}

            exec_status, exec_body = hop_server(
                "/hop/execPipeline", {"pipeline": external_file, "level": "Basic", "runConfig": "local"}
            )
            check("the Hop Server accepts a run published outside JobSeeker",
                  exec_status == 200 and "OK" in exec_body, "HTTP %s: %s" % (exec_status, exec_body[:200]))

            after_status, after_body = app_get("/hop/executions")
            rows = json.loads(after_body).get("executions", []) if after_status == 200 else []
            fresh = [row for row in rows if row["execution_id"] not in known]
            check("the Apache Hop screen picks up a run it did not start",
                  bool(fresh), "no new execution appeared in /hop/executions")
            if fresh:
                run = fresh[0]
                external_name = run["name"]
                check("the external run is attributed to the Hop GUI, not to Jenkins",
                      run["source"] == "hop-gui", "source=%s" % run["source"])
                check("the external run carries its Hop log",
                      "Execution started for pipeline" in (run.get("log_text") or ""),
                      "the stored log is empty")
                log_status, log_body = app_get("/hop/execution-log?execution=" + urllib.parse.quote(run["execution_id"]))
                check("the log viewer returns the run's log", log_status == 200 and "log" in log_body,
                      "HTTP %s" % log_status)

                external_rows = tmf_rows_for(run["name"])
                check("an externally started run reaches Transaction Monitoring",
                      any(row[0] == run["execution_id"] for row in external_rows),
                      "no tmf row with instance_id %s" % run["execution_id"])
                check("the TMF row names the Hop Server as its dimension",
                      any(row[2] == "hop-server" for row in external_rows),
                      "dimensions=%s" % [row[2] for row in external_rows])

                # Polling again must not open a second row for the same run.
                app_get("/hop/executions")
                repeated = tmf_rows_for(run["name"])
                check("a repeated poll does not duplicate the Transaction Monitoring row",
                      len([row for row in repeated if row[0] == run["execution_id"]]) == 1,
                      "%d rows" % len([row for row in repeated if row[0] == run["execution_id"]]))

    print("\ncleaning up")
    delete_status, delete_body = app_post("/delete-job/jobs", {
        "jobs[]": [JOB, UPLOAD_JOB],
        "environment": "DEV",
        "delete_repositories": "1",
    })
    try:
        delete_result = json.loads(delete_body)
    except json.JSONDecodeError:
        delete_result = {}
    check("JobSeeker deletes both Jenkins jobs and their Hop projects",
          delete_status == 200
          and delete_result.get("deleted") == 2
          and not os.path.exists(project_root)
          and not os.path.exists(upload_root),
          "HTTP %s: %s" % (delete_status, delete_body[:500]))

    # Cleanup is best-effort even when the assertion above fails, so repeated
    # test runs never accumulate Jenkins jobs or repository projects.
    for name, root in ((JOB, project_root), (UPLOAD_JOB, upload_root)):
        status, _ = jenkins("/job/%s/api/json" % urllib.parse.quote(name))
        if status == 200:
            delete_jenkins_job(name)
        shutil.rmtree(root, ignore_errors=True)
        app_post("/hop/delete", {"project": name.lower(), "remove_files": "0"})
    delete_tmf_rows((JOB, UPLOAD_JOB) + ((external_name,) if external_name else ()))
    forget_hop_executions(external_name)
    shutil.rmtree(archive_root, ignore_errors=True)
    shutil.rmtree(unsafe_root, ignore_errors=True)

    print("\n%d passed, %d failed" % (len(PASSED), len(FAILED)))
    for name, detail in FAILED:
        print("  FAILED: %s%s" % (name, (" - " + detail) if detail else ""))
    return 1 if FAILED else 0


if __name__ == "__main__":
    sys.exit(main())
