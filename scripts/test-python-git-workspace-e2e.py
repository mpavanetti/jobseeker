#!/usr/bin/env python3
"""Git repository jobs developed in OpenVSCode, end to end.

A throwaway smart-HTTP Git server on the stack network stands in for GitHub.
The job runs `main`; "Open in VS Code" clones the repository once onto
`develop`; a push to `develop` must not reach the job, and a merge into `main`
must reach the next build without redeploying it.
"""

from __future__ import annotations

import importlib.util
import html as html_lib
import json
import os
import shutil
import subprocess
import sys
import time
import urllib.parse
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
os.environ.setdefault("JOBSEEKER_E2E_URL", "http://127.0.0.1:8088")
# Reuse the sample matrix's logged-in browser and Jenkins helpers.
_spec = importlib.util.spec_from_file_location("sample_matrix", ROOT / "scripts" / "test-job-samples-e2e.py")
matrix = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(matrix)

RUN = uuid.uuid4().hex[:6]
JOB = f"e2e-git-ide-{RUN}"
PROJECT = f"E2E Git Project {RUN}"
# One build-credential key, defined per environment scope: the connector
# catalog resolves it to the DEV or QA row at build time.
CONNECTOR = f"e2e-git-{RUN}"
SERVER = f"jobseeker-git-e2e-{RUN}"
VOLUME = SERVER
NETWORK = os.environ.get("JOBSEEKER_E2E_NETWORK", "jobseeker_internal")
URL = f"http://{SERVER}:8000/remote.git"
# A project's jobs open the opener's own workspace of the project; set once
# Open in VS Code reports where it is.
WORKSPACE = ROOT / "repository" / "python" / "git" / JOB
IDE_WORKSPACE = f"/home/workspace/repository/python/git/{JOB}"

# `git http-backend` behind a minimal CGI bridge: clone, fetch and push.
GIT_HTTP_SERVER = r'''
import base64, http.server, os, subprocess
class Git(http.server.BaseHTTPRequestHandler):
    def serve(self):
        expected = "Basic " + base64.b64encode(b"e2e:e2e-token").decode()
        if self.headers.get("Authorization") != expected:
            self.send_response(401)
            self.send_header("WWW-Authenticate", 'Basic realm="JobSeeker E2E"')
            self.send_header("Content-Length", "0")
            self.end_headers()
            return
        path, _, query = self.path.partition("?")
        body = self.rfile.read(int(self.headers.get("Content-Length") or 0))
        env = dict(os.environ, GIT_PROJECT_ROOT="/srv", GIT_HTTP_EXPORT_ALL="1", PATH_INFO=path,
                   QUERY_STRING=query, REQUEST_METHOD=self.command, REMOTE_USER="e2e",
                   CONTENT_TYPE=self.headers.get("Content-Type", ""), CONTENT_LENGTH=str(len(body)),
                   HTTP_CONTENT_ENCODING=self.headers.get("Content-Encoding", ""))
        head, _, payload = subprocess.run(["git", "http-backend"], input=body, env=env,
                                          capture_output=True).stdout.partition(b"\r\n\r\n")
        status, headers = 200, []
        for line in head.decode().split("\r\n"):
            name, _, value = line.partition(":")
            if name.lower() == "status":
                status = int(value.split()[0])
            elif name:
                headers.append((name, value.strip()))
        self.send_response(status)
        for header in headers:
            self.send_header(*header)
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)
    do_GET = do_POST = serve
    def log_message(self, *args):
        pass
http.server.ThreadingHTTPServer(("0.0.0.0", 8000), Git).serve_forever()
'''

passed = 0


def check(name: str, condition: bool, detail: str = "") -> None:
    global passed
    if not condition:
        print(f"FAIL: {name}" + (f"\n  {detail}" if detail else ""))
        sys.exit(1)
    passed += 1
    print(f"ok - {name}")


def run(*command: str, **kwargs) -> subprocess.CompletedProcess:
    return subprocess.run(command, capture_output=True, text=True, **kwargs)


def ide(command: str) -> subprocess.CompletedProcess:
    return run("docker", "exec", "-u", "openvscode-server", "-w", IDE_WORKSPACE, "jobseeker-openvscode", "sh", "-c", command)


def workspace_git(*arguments: str) -> str:
    return run("git", "-C", str(WORKSPACE), *arguments).stdout.strip()


def build_output(browser) -> str:
    job = urllib.parse.quote(JOB, safe="")
    _, body = browser.request(matrix.proxy_path(f"job/{job}/api/json?tree=lastBuild[number]"))
    previous = (json.loads(body).get("lastBuild") or {}).get("number", 0)
    browser.request(matrix.proxy_path(f"job/{job}/buildWithParameters?ENVIRONMENT=DEV"),
                    method="POST", csrf=True, expected=(200, 201, 302))
    # Until the new build starts, the last build is still the previous one.
    while True:
        result, number, console = matrix.wait_for_build(browser, JOB)
        if number > previous:
            break
        time.sleep(1)
    check(f"build {number} finished ({result})", result == "SUCCESS", console[-1500:])
    return console


def main() -> None:
    global WORKSPACE, IDE_WORKSPACE
    browser = matrix.Browser()
    browser.request("/")
    _, page = browser.request("/loginMe", method="POST", csrf=True,
                              fields={"email": matrix.ADMIN_EMAIL, "password": matrix.ADMIN_PASSWORD})
    check("admin login", "logout" in page.lower())

    run("docker", "volume", "create", VOLUME)
    started = run("docker", "run", "-d", "--name", SERVER, "--network", NETWORK, "--user", "0", "-v", f"{VOLUME}:/srv",
                  "--entrypoint", "python3", "jobseeker-jenkins", "-c", GIT_HTTP_SERVER)
    job_saved = False
    project_id = ""
    account_id = ""
    connector_ids: dict[str, str] = {}
    try:
        check("Git server started", started.returncode == 0, started.stderr)
        seeded = run("docker", "exec", SERVER, "sh", "-c",
                     "set -e; git init -q --bare -b main /srv/remote.git; cd \"$(mktemp -d)\"; git init -q -b main;"
                     " printf 'print(\"GIT-E2E main v1\")\\n' > main.py; git add main.py;"
                     " git -c user.name=e2e -c user.email=e2e@example.com commit -qm v1; git push -q /srv/remote.git main")
        check("remote seeded with main", seeded.returncode == 0, seeded.stderr)

        browser.request("/gitAccountSave", method="POST", csrf=True, fields={
            "provider": "generic", "label": "E2E private Git", "host": SERVER + ":8000", "path_prefix": "remote",
            "auth_type": "token", "username": "e2e", "secret": "e2e-token", "known_hosts": "",
        })
        account_query = "SELECT id FROM user_git_accounts WHERE host='%s:8000' AND path_prefix='remote' LIMIT 1" % SERVER
        account_id = run("docker", "compose", "exec", "-T", "mariadb", "mariadb", "-umysql", "-pmysql",
                         "-D", "jobseeker", "-N", "-e", account_query, cwd=ROOT).stdout.strip()
        check("private repository Profile account saved", account_id.isdigit(), account_id)
        status, test_body = browser.request("/gitAccountTest", method="POST", csrf=True, fields={
            "account_id": account_id, "repository_url": URL, "branch": "main",
        }, expected=(200, 422))
        profile_test = json.loads(test_body)
        check("Profile Git connection test authenticates to the private repository", status == 200 and profile_test.get("ok") is True, test_body)

        # Profile credentials are deliberately scoped to a user's interactive
        # VS Code workspace. An unattended Jenkins build uses a managed Git
        # connector, which the project names once instead of each job; the
        # same key holds a separate secret in each environment scope.
        for connector_environment in ("DEV", "QA"):
            browser.request(f"/dbSettings/InsertDbSettings?environment={connector_environment}", method="POST", csrf=True, fields={
                "connector_key": CONNECTOR, "job_name": "*", "db_type": "git_repository",
                "auth_type": "token", "secret_backend": "local", "address": SERVER, "port": "8000",
                "schema": URL, "description": f"Private Git {connector_environment} build credential for workspace E2E",
                "additional_parameters": "", "oracle_ServiceName": "", "oracle_sid": "",
                "login": "e2e", "password": "e2e-token", "local_secret_fields": "", "is_active": "1",
            })
            connector_query = ("SELECT id FROM database_settings WHERE connector_key='%s' AND environment='%s' LIMIT 1"
                               % (CONNECTOR, connector_environment))
            connector_id = run("docker", "compose", "exec", "-T", "mariadb", "mariadb", "-umysql", "-pmysql",
                               "-D", "jobseeker", "-N", "-e", connector_query, cwd=ROOT).stdout.strip()
            connector_ids[connector_environment] = connector_id
            check(f"private repository {connector_environment} build connector saved", connector_id.isdigit(), connector_id)
        status, test_body = browser.request("/dbSettings/testConnector?environment=DEV", method="POST", csrf=True,
                                            fields={"id": connector_ids["DEV"], "mode": "live"}, expected=(200, 422))
        connector_test = json.loads(test_body)
        check("Git connector test authenticates from a Jenkins worker",
              status == 200 and connector_test.get("ok") is True, test_body[-1600:])

        project_fields = {
            "name": PROJECT, "active": "1", "gitEnabled": "1", "gitBranchMode": "environment", "gitpath": URL,
            "gitBranch[DEFAULT]": "main", "gitBranch[DEV]": "develop", "gitBranch[QA]": "project-qa",
            "gitCredentialKey": CONNECTOR,
        }
        browser.request("/Context/addProject", method="POST", csrf=True, fields=project_fields)
        project_query = "SELECT Id FROM projectdetails WHERE ProjectName='%s' LIMIT 1" % PROJECT.replace("'", "''")
        project_id = run("docker", "compose", "exec", "-T", "mariadb", "mariadb", "-umysql", "-pmysql",
                         "-D", "jobseeker", "-N", "-e", project_query, cwd=ROOT).stdout.strip()
        check("Project Details stores the Git project", project_id.isdigit(), project_id)
        defaults_query = ("SELECT CONCAT(d.environment, '=', d.branch, ':', p.GitCredentialKey) FROM project_git_defaults d "
                          "JOIN projectdetails p ON p.Id = d.project_id WHERE d.project_id=%s ORDER BY d.environment" % project_id)
        defaults = run("docker", "compose", "exec", "-T", "mariadb", "mariadb", "-umysql", "-pmysql",
                       "-D", "jobseeker", "-N", "-e", defaults_query, cwd=ROOT).stdout
        check("Project Details stores environment branches and one build credential",
              f"DEV=develop:{CONNECTOR}" in defaults and f"QA=project-qa:{CONNECTOR}" in defaults, defaults)

        fields = {"job_name": JOB, "environment": "DEV", "pythonProjectId": project_id, "pythonRepositoryUrl": "",
                  "pythonRepositoryBranch": "main", "pythonGitCredentialKey": "", "pythonEntryPoint": "main.py"}
        status, body = browser.request("/jobCreation/gitPythonExternalOpen", method="POST", csrf=True, fields=fields)
        opened = json.loads(body)
        WORKSPACE = ROOT / opened.get("workspacePath", "missing")
        IDE_WORKSPACE = "/home/workspace/" + opened.get("workspacePath", "missing")
        check("a project job opens the opener's own workspace of the project",
              opened.get("workspacePath", "").startswith("repository/workspaces/u"), body[:400])
        check("Open in VS Code clones the repository", opened.get("ok") is True and IDE_WORKSPACE in opened.get("openVsCodeUrl", "")
              .replace("%2F", "/"), body[:400])
        check("the working copy is on develop", workspace_git("rev-parse", "--abbrev-ref", "HEAD") == "develop")
        check("develop starts from main", workspace_git("log", "-1", "--format=%s") == "v1")
        check("IDE files do not show as changes", workspace_git("status", "--porcelain") == "", workspace_git("status", "--porcelain"))
        check("commits are authored as the JobSeeker user", workspace_git("config", "user.name") != "")
        check("the IDE has a connector session", (WORKSPACE / ".env.jobseeker").is_file())

        ran = ide("sh .vscode/bootstrap-python.sh >/dev/null 2>&1 && .venv/bin/python main.py")
        check("the job runs inside OpenVSCode", "GIT-E2E main v1" in ran.stdout, ran.stdout + ran.stderr)

        def save_job(branch: str) -> None:
            # A bound job takes the project's repository and credential; the
            # branch is a pin, or empty to follow the project.
            browser.request("/jobCreation/send", method="POST", csrf=True, fields={
                "job_name": JOB, "job_names": "", "description": "Git workspace e2e", "environment": "DEV",
                "checkEnvironment": "1", "timestamp": "1", "trigger_after_save": "0", "linuxCommand": "1",
                "linuxExecutionStrategy": "script", "linuxScriptType": "python", "pythonSourceMode": "git",
                "pythonProjectId": project_id, "pythonRepositoryUrl": "", "pythonRepositoryBranch": branch, "pythonGitCredentialKey": "",
                "pythonEntryPoint": "main.py", "pythonRuntimeMode": "local", "pythonVersion": "python3",
                "checkBuild": "0", "abort": "0", "runJobCheck": "0", "emailCheck": "0", "editableEmailCheck": "0",
                "winCommand": "0", "action": "0"})

        save_job("main")
        job_saved = True

        job_path = urllib.parse.quote(JOB, safe="")
        _, config_xml = browser.request(matrix.proxy_path(f"job/{job_path}/config.xml"))
        config_command = html_lib.unescape(config_xml)
        check("the job persists its Project Details binding",
              f"JOBSEEKER_PROJECT_ID='{project_id}'" in config_command and f"JOBSEEKER_PROJECT_NAME='{PROJECT}'" in config_command,
              config_xml[:2000])

        _, project_preview_body = browser.request("/Context/previewJobPromotion", method="POST", csrf=True, fields={
            "sourceJob": JOB, "targetJobName": JOB + "-qa-project", "sourceEnvironment": "2", "targetEnvironment": "3",
            "targetGitBranch": "main", "targetGitBranchMode": "default", "overwriteExisting": "0", "includeDependencies": "0",
            "promoteContexts": "0", "promotionProject": "", "overwriteContexts": "0", "createRollback": "0",
        })
        project_preview = json.loads(project_preview_body)
        check("promotion uses the bound project's target-environment branch",
              project_preview.get("ok") is True and project_preview.get("target_git_branch") == "project-qa"
              and (project_preview.get("git_project") or {}).get("id") == int(project_id), project_preview_body[:1000])
        check("promotion keeps the project's build credential, which resolves to the QA connector at build time",
              project_preview.get("target_git_credential") == CONNECTOR
              and project_preview.get("git_credential_updates", 0) == 0, project_preview_body[:1200])

        # Preview runs the same config transformation as deployment without
        # leaving another Jenkins job or a promotion-history record behind.
        _, preview_body = browser.request("/Context/previewJobPromotion", method="POST", csrf=True, fields={
            "sourceJob": JOB, "targetJobName": JOB + "-qa", "sourceEnvironment": "2", "targetEnvironment": "3",
            "targetGitBranch": "release/qa", "overwriteExisting": "0", "includeDependencies": "0",
            "promoteContexts": "0", "promotionProject": "", "overwriteContexts": "0", "createRollback": "0",
        })
        preview = json.loads(preview_body)
        check("promotion accepts an explicit target branch",
              preview.get("ok") is True and preview.get("target_git_branch") == "release/qa", preview_body[:800])
        check("promotion rewrites the Git-backed job config", preview.get("branch_updates", 0) >= 1,
              preview_body[:800])

        check("the job runs main", "GIT-E2E main v1" in build_output(browser))

        pushed = ide("printf 'print(\"GIT-E2E develop v2\")\\n' > main.py && git commit -qam v2 && git push -q origin develop")
        check("a developer pushes develop from OpenVSCode", pushed.returncode == 0, pushed.stderr)
        check("develop does not reach the job", "GIT-E2E main v1" in build_output(browser))

        merged = ide("git push -q origin develop:main")
        check("develop is merged into main", merged.returncode == 0, merged.stderr)
        console = build_output(browser)
        check("the merge reaches the next build", "GIT-E2E develop v2" in console, console[-1200:])

        head = workspace_git("rev-parse", "HEAD")
        _, body = browser.request("/jobCreation/gitPythonExternalOpen", method="POST", csrf=True, fields=fields)
        check("reopening keeps the working copy", json.loads(body).get("ok") is True and workspace_git("rev-parse", "HEAD") == head)

        # Without a pin the job follows its project: each build asks JobSeeker
        # which branch the project runs in the build's environment.
        pushed = ide("printf 'print(\"GIT-E2E develop v3\")\\n' > main.py && git commit -qam v3 && git push -q origin develop")
        check("a developer pushes develop v3", pushed.returncode == 0, pushed.stderr)
        save_job("")
        _, config_xml = browser.request(matrix.proxy_path(f"job/{job_path}/config.xml"))
        config_command = html_lib.unescape(config_xml)
        check("an unpinned job follows its project",
              "export JOBSEEKER_GIT_FOLLOW_PROJECT=1" in config_command and "export JOBSEEKER_GIT_REPOSITORY_BRANCH=''" in config_command,
              config_command[:2000])
        console = build_output(browser)
        check("DEV builds run the project's DEV branch", "GIT-E2E develop v3" in console and "DEV runs develop (project)" in console, console[-1500:])

        # A project change reaches the next build without touching the job.
        browser.request("/Context/editProjectUpdate", method="POST", csrf=True,
                        fields=dict(project_fields, Id=project_id, **{"gitBranch[DEV]": "main"}))
        console = build_output(browser)
        check("the project's new DEV branch reaches the next build", "GIT-E2E develop v2" in console and "DEV runs main (project)" in console, console[-1500:])
    finally:
        if job_saved:
            browser.request("/delete-job/jobs", method="POST", csrf=True, fields={"jobs": JOB, "environment": "DEV"})
        if project_id:
            browser.request("/Context/deleteProject", method="POST", csrf=True, fields={"userId": project_id})
        for connector_environment, connector_id in connector_ids.items():
            if connector_id:
                browser.request(f"/dbSettings/deleteSetting?environment={connector_environment}", method="POST", csrf=True,
                                fields={"userId": connector_id})
        if account_id:
            browser.request("/gitAccountDelete", method="POST", csrf=True, fields={"id": account_id})
        shutil.rmtree(WORKSPACE, ignore_errors=True)
        run("docker", "rm", "-f", SERVER)
        run("docker", "volume", "rm", VOLUME)

    print(f"\nGit workspace e2e: {passed} checks passed.")


if __name__ == "__main__":
    main()
