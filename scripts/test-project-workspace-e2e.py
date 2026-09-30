#!/usr/bin/env python3
"""Project workspaces and repositories that hold many jobs, end to end.

A throwaway smart-HTTP Git server on the stack network stands in for GitHub
(the same one test-python-git-workspace-e2e.py uses). A Python project is
created from the sidebar launcher's endpoint with an empty repository, opened
in the person's own working copy, and grows two jobs and shared code from the
VS Code "new job" task. Job Creation lists the job folders; each becomes a job
that builds from its own folder only, imports shared/, and runs only its own
tests. A worker whose jobseeker-git predates sparse clones still builds (with
the whole branch); one that has them fetches only the job's folder.
"""

from __future__ import annotations

import html as html_lib
import importlib.util
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
_spec = importlib.util.spec_from_file_location("sample_matrix", ROOT / "scripts" / "test-job-samples-e2e.py")
matrix = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(matrix)
_spec = importlib.util.spec_from_file_location("git_workspace", ROOT / "scripts" / "test-python-git-workspace-e2e.py")
git_workspace = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(git_workspace)

RUN = uuid.uuid4().hex[:6]
SERVER = f"jobseeker-git-projects-{RUN}"
VOLUME = SERVER
NETWORK = os.environ.get("JOBSEEKER_E2E_NETWORK", "jobseeker_internal")
URL = f"http://{SERVER}:8000/mono.git"
PROJECT = f"E2E Mono {RUN}"
PERSONAL_PROJECT = f"E2E Mono Personal {RUN}"
CONNECTOR = f"e2e-mono-{RUN}"
ORDERS_JOB = f"e2e-orders-{RUN}"
REPORT_JOB = f"e2e-report-{RUN}"
MOVED_JOB = f"e2e-moved-{RUN}"
SHARED_PROJECT = f"E2E Shared {RUN}"
SHARED_JOB = f"e2e-shared-{RUN}"
JENKINS = "jobseeker-jenkins-1"
HELPER = "/usr/local/bin/jobseeker-git"

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


def sql(query: str) -> str:
    return run("docker", "compose", "exec", "-T", "mariadb", "mariadb", "-umysql", "-pmysql",
               "-D", "jobseeker", "-N", "-e", query, cwd=ROOT).stdout.strip()


def remote(*arguments: str) -> str:
    return run("docker", "exec", SERVER, "git", "--git-dir", "/srv/mono.git", *arguments).stdout.strip()


def post_json(browser, path: str, fields: dict, expected=(200,)) -> tuple[int, dict]:
    status, body = browser.request(path, method="POST", csrf=True, fields=fields, expected=expected)
    try:
        return status, json.loads(body)
    except ValueError:
        check(f"{path} returned JSON", False, body[:800])
        raise


def get_json(browser, path: str) -> dict:
    _, body = browser.request(path)
    return json.loads(body)


def ide(workspace: str, command: str) -> subprocess.CompletedProcess:
    return run("docker", "exec", "-u", "openvscode-server", "-w", workspace, "jobseeker-openvscode", "sh", "-c", command)


def save_job(browser, job: str, project_id: str, folder: str, runtime: str, tests: str) -> None:
    browser.request("/jobCreation/send", method="POST", csrf=True, fields={
        "job_name": job, "job_names": "", "description": "Project workspace e2e", "environment": "DEV",
        "checkEnvironment": "1", "timestamp": "1", "trigger_after_save": "0", "linuxCommand": "1",
        "linuxExecutionStrategy": "script", "linuxScriptType": "python", "pythonSourceMode": "git",
        "pythonProjectId": project_id, "pythonRepositoryUrl": "", "pythonRepositoryBranch": "", "pythonGitCredentialKey": "",
        "pythonGitJobPath": folder, "pythonEntryPoint": "main.py", "pythonRuntimeMode": runtime, "pythonVersion": "python3",
        "pythonDockerImage": "python:3.13-slim", "pythonUseDockerfile": "0", "pythonRunTests": tests,
        "checkBuild": "0", "abort": "0", "runJobCheck": "0", "emailCheck": "0", "editableEmailCheck": "0",
        "winCommand": "0", "action": "0"})


def build(browser, job: str) -> tuple[str, str]:
    quoted = urllib.parse.quote(job, safe="")
    _, body = browser.request(matrix.proxy_path(f"job/{quoted}/api/json?tree=lastBuild[number]"))
    previous = (json.loads(body).get("lastBuild") or {}).get("number", 0)
    browser.request(matrix.proxy_path(f"job/{quoted}/buildWithParameters?ENVIRONMENT=DEV"),
                    method="POST", csrf=True, expected=(200, 201, 302))
    while True:
        result, number, console = matrix.wait_for_build(browser, job)
        if number > previous:
            return result, console
        time.sleep(1)


def main() -> None:
    browser = matrix.Browser()
    browser.request("/")
    _, page = browser.request("/loginMe", method="POST", csrf=True,
                              fields={"email": matrix.ADMIN_EMAIL, "password": matrix.ADMIN_PASSWORD})
    check("admin login", "logout" in page.lower())
    user_id = sql("SELECT userId FROM tbl_users WHERE email='%s' LIMIT 1" % matrix.ADMIN_EMAIL.replace("'", "''"))

    run("docker", "volume", "create", VOLUME)
    started = run("docker", "run", "-d", "--name", SERVER, "--network", NETWORK, "--user", "0", "-v", f"{VOLUME}:/srv",
                  "--entrypoint", "python3", "jobseeker-jenkins", "-c", git_workspace.GIT_HTTP_SERVER)
    original_helper = run("docker", "exec", JENKINS, "cat", HELPER).stdout
    project_ids: list[str] = []
    jobs: list[str] = []
    account_id = ""
    connector_id = ""
    workspaces: list[Path] = []
    try:
        check("Git server started", started.returncode == 0, started.stderr)
        seeded = run("docker", "exec", SERVER, "git", "init", "-q", "--bare", "-b", "main", "/srv/mono.git")
        check("an empty project repository exists", seeded.returncode == 0, seeded.stderr)

        _, dashboard = browser.request("/dashboard")
        check("the sidebar offers the VS Code project launcher",
              'id="sidebarOpenVsCode"' in dashboard and 'id="projectWorkspaceLauncher"' in dashboard)

        browser.request("/gitAccountSave", method="POST", csrf=True, fields={
            "provider": "generic", "label": "E2E mono Git", "host": SERVER + ":8000", "path_prefix": "",
            "auth_type": "token", "username": "e2e", "secret": "e2e-token", "known_hosts": "",
        })
        account_id = sql("SELECT id FROM user_git_accounts WHERE host='%s:8000' LIMIT 1" % SERVER)
        check("personal Git account saved", account_id.isdigit(), account_id)
        browser.request("/dbSettings/InsertDbSettings?environment=DEV", method="POST", csrf=True, fields={
            "connector_key": CONNECTOR, "job_name": "*", "db_type": "git_repository", "auth_type": "token",
            "secret_backend": "local", "address": SERVER, "port": "8000", "schema": URL,
            "description": "Project workspace e2e build credential", "additional_parameters": "",
            "oracle_ServiceName": "", "oracle_sid": "", "login": "e2e", "password": "e2e-token",
            "local_secret_fields": "", "is_active": "1",
        })
        connector_id = sql("SELECT id FROM database_settings WHERE connector_key='%s' LIMIT 1" % CONNECTOR)
        check("build connector saved", connector_id.isdigit(), connector_id)

        # --- A project created from the launcher ------------------------------
        _, created = post_json(browser, "/jobCreation/projectWorkspaceCreateProject",
                               {"name": PROJECT, "type": "python", "repository_url": URL, "environment": "DEV"})
        project = created.get("project") or {}
        project_id = str(project.get("id", ""))
        project_ids.append(project_id)
        check("the launcher creates a typed Git project", created.get("ok") is True and project.get("type") == "python"
              and project.get("hasGit") is True, json.dumps(created))
        status, duplicate = post_json(browser, "/jobCreation/projectWorkspaceCreateProject",
                                      {"name": PROJECT, "type": "python", "repository_url": ""}, expected=(409,))
        check("project names stay unique", status == 409, json.dumps(duplicate))
        browser.request("/Context/editProjectUpdate", method="POST", csrf=True, fields={
            "Id": project_id, "name": PROJECT, "active": "1", "projectType": "python", "gitEnabled": "1",
            "gitBranchMode": "environment", "gitpath": URL, "gitBranch[DEFAULT]": "main", "gitBranch[DEV]": "develop",
            "gitCredentialKey": CONNECTOR,
        })
        check("Project Details keeps the type and sets the build credential",
              sql("SELECT CONCAT(ProjectType, ':', GitCredentialKey) FROM projectdetails WHERE Id=%s" % project_id) == f"python:{CONNECTOR}")

        listing = get_json(browser, "/jobCreation/projectWorkspaces?environment=DEV")
        listed = next((item for item in listing.get("projects", []) if str(item["id"]) == project_id), {})
        check("the launcher lists the project with the DEV branch and a personal branch",
              listed.get("branches", {}).get("shared") == "develop" and listed["branches"]["personal"].startswith("work/")
              and listed.get("workspace", {}).get("exists") is False and listed.get("account") == "E2E mono Git", json.dumps(listed))
        relative = listed["workspace"]["path"]
        check("each person has their own working copy", relative.startswith(f"repository/workspaces/u{user_id}/"), relative)
        workspace = ROOT / relative
        workspaces.append(workspace)
        ide_workspace = "/home/workspace/" + relative

        _, opened = post_json(browser, "/jobCreation/projectWorkspaceOpen",
                              {"project_id": project_id, "environment": "DEV", "branch_mode": "shared"})
        check("opening clones the project on its DEV branch", opened.get("ok") is True and opened.get("created") is True
              and opened.get("workspaceBranch") == "develop" and ide_workspace in urllib.parse.unquote(opened.get("openVsCodeUrl", "")),
              json.dumps(opened))
        check("an empty repository starts with the project layout",
              {"README.md", "jobs/README.md", "shared/__init__.py"} <= set(opened.get("scaffolded", [])), json.dumps(opened))
        check("the workspace has the VS Code helper and a connector session",
              (workspace / ".vscode" / "jobseeker.sh").is_file() and (workspace / ".env.jobseeker").is_file())
        status_lines = run("git", "-C", str(workspace), "status", "--porcelain").stdout.split("\n")
        check("editor files never show as changes", not any(".vscode" in line or ".env.jobseeker" in line or ".venv" in line for line in status_lines),
              "\n".join(status_lines))

        # --- Jobs grow as folders, from VS Code -------------------------------
        created_jobs = ide(ide_workspace, "sh .vscode/jobseeker.sh new orders && sh .vscode/jobseeker.sh new report")
        check("the new-job task creates job folders and prints the Job Creation link",
              created_jobs.returncode == 0 and f"JobCreation?project={project_id}&folder=jobs/orders" in created_jobs.stdout,
              created_jobs.stdout + created_jobs.stderr)
        (workspace / "shared" / "greeting.py").write_text('def hello() -> str:\n    return "HELLO-FROM-SHARED"\n')
        (workspace / "jobs" / "orders" / "main.py").write_text(
            "import sys\nfrom shared.greeting import hello\n\n\ndef total() -> int:\n    return 42\n\n\n"
            "if __name__ == '__main__':\n    print('ORDERS-RAN', hello(), sys.argv[1:])\n")
        (workspace / "jobs" / "orders" / "tests" / "test_main.py").write_text(
            "from main import total\n\n\ndef test_total() -> None:\n    assert total() == 42\n")
        (workspace / "jobs" / "report" / "main.py").write_text(
            "import os\nfrom shared.greeting import hello\n\n"
            "here = os.path.dirname(os.path.abspath(__file__))\n"
            "print('REPORT-RAN', hello(), 'SIBLING-VISIBLE' if os.path.isdir(os.path.join(here, '..', 'orders')) else 'SIBLING-HIDDEN')\n")
        # Another job's failing test must never fail this job's build.
        (workspace / "jobs" / "report" / "tests" / "test_main.py").write_text("def test_report_is_broken() -> None:\n    assert False\n")

        _, found = post_json(browser, "/jobCreation/projectWorkspaceJobs", {"project_id": project_id, "environment": "DEV"})
        states = {job["path"]: job for job in found.get("jobs", [])}
        check("Job Creation finds the job folders", set(states) == {"jobs/orders", "jobs/report"}, json.dumps(found))
        check("uncommitted folders say builds cannot see them yet",
              states["jobs/orders"]["state"] == "untracked" and states["jobs/orders"]["entryPoint"] == "main.py", json.dumps(states))

        pushed = ide(ide_workspace, "git add -A && git commit -qm 'Two jobs' && git push -q origin develop 2>&1")
        check("the developer pushes develop from VS Code", pushed.returncode == 0, pushed.stdout + pushed.stderr)
        _, found = post_json(browser, "/jobCreation/projectWorkspaceJobs", {"project_id": project_id, "environment": "DEV"})
        check("pushed folders are ready to build", all(job["state"] == "pushed" for job in found["jobs"])
              and found["workspace"]["buildBranch"] == "develop", json.dumps(found))
        check("tooling stayed out of the commit", not any(name.startswith((".vscode", ".venv")) or name.endswith(".env.jobseeker")
              for name in remote("ls-tree", "-r", "--name-only", "develop").split("\n")))

        _, link_page = browser.request(f"/JobCreation?project={project_id}&folder=jobs/orders")
        check("Job Creation opens a job folder from the task's link",
              f'openProjectJobFromLink({{"projectId":{project_id},"folder":"jobs\\/orders"}})' in link_page)

        # Setting up the editor installs every job's dependencies without
        # writing lock files into anyone's job folders.
        (workspace / "jobs" / "orders" / "pyproject.toml").write_text(
            (workspace / "jobs" / "orders" / "pyproject.toml").read_text().replace("dependencies = []", 'dependencies = ["six>=1.16"]'))
        bootstrapped = ide(ide_workspace, "sh .vscode/bootstrap-python.sh >/dev/null 2>&1; .venv/bin/python -c 'import six, jobseeker; print(\"deps-ok\")'; git status --porcelain")
        check("the editor environment installs job dependencies and writes no lock files",
              "deps-ok" in bootstrapped.stdout and "poetry.lock" not in bootstrapped.stdout, bootstrapped.stdout + bootstrapped.stderr)
        run("git", "-C", str(workspace), "checkout", "--", "jobs/orders/pyproject.toml")

        # Opening a job whose code is on a branch never adds starter files
        # that would collide with it on the next merge.
        _, reopened = post_json(browser, "/jobCreation/gitPythonExternalOpen", {
            "job_name": f"e2e-not-here-{RUN}", "environment": "DEV", "pythonProjectId": project_id, "pythonRepositoryUrl": "",
            "pythonRepositoryBranch": "", "pythonGitJobPath": "jobs/not-here", "pythonEntryPoint": "main.py", "scaffold_job": "0"})
        check("a job with code elsewhere gets no starter files", reopened.get("ok") is True and reopened.get("jobFolderCreated") is False
              and not (workspace / "jobs" / "not-here").exists() and "pull or merge" in reopened.get("message", ""), json.dumps(reopened))

        # --- Two jobs, one repository -----------------------------------------
        save_job(browser, ORDERS_JOB, project_id, "jobs/orders", "docker", "1")
        jobs.append(ORDERS_JOB)
        save_job(browser, REPORT_JOB, project_id, "jobs/report", "local", "0")
        jobs.append(REPORT_JOB)
        _, config_xml = browser.request(matrix.proxy_path(f"job/{urllib.parse.quote(ORDERS_JOB, safe='')}/config.xml"))
        check("the job keeps its folder", "export JOBSEEKER_GIT_JOB_PATH='jobs/orders'" in html_lib.unescape(config_xml))

        # This worker's helper may predate sparse clones: builds still work.
        old_helper = "features" not in original_helper
        result, console = build(browser, REPORT_JOB)
        check("the report job builds from its folder on the agent", result == "SUCCESS" and "REPORT-RAN HELLO-FROM-SHARED" in console
              and "[JobSeeker] Job folder jobs/report" in console, console[-2000:])
        if old_helper:
            check("an older helper clones the whole branch", "cannot clone one folder" in console and "SIBLING-VISIBLE" in console, console[-1500:])

        installed = run("docker", "cp", str(ROOT / "docker" / "jenkins" / "jobseeker-git"), f"{JENKINS}:{HELPER}")
        run("docker", "exec", "-u", "0", JENKINS, "chmod", "0755", HELPER)
        check("the new helper is on the worker", installed.returncode == 0, installed.stderr)
        result, console = build(browser, REPORT_JOB)
        check("with sparse clones a job fetches only its folder and shared/",
              result == "SUCCESS" and "SIBLING-HIDDEN" in console and "REPORT-RAN HELLO-FROM-SHARED" in console, console[-2000:])

        result, console = build(browser, ORDERS_JOB)
        check("the Docker job builds from its folder with shared/ importable", result == "SUCCESS"
              and "ORDERS-RAN HELLO-FROM-SHARED" in console, console[-2500:])
        check("only the job's own tests run", "1 passed" in console and "test_report_is_broken" not in console, console[-2500:])

        # --- A project without Git ---------------------------------------------
        _, local = post_json(browser, "/jobCreation/projectWorkspaceCreateProject",
                             {"name": SHARED_PROJECT, "type": "python", "repository_url": ""})
        local_id = str(local["project"]["id"])
        project_ids.append(local_id)
        _, local_open = post_json(browser, "/jobCreation/projectWorkspaceOpen", {"project_id": local_id, "environment": "DEV"})
        shared_folder = ROOT / local_open["workspacePath"]
        workspaces.append(shared_folder)
        check("a project without Git opens one shared folder", local_open.get("sharedWorkspace") is True
              and local_open["workspacePath"].startswith("repository/workspaces/shared/"), json.dumps(local_open))
        shared_ide = "/home/workspace/" + local_open["workspacePath"]
        made = ide(shared_ide, "sh .vscode/jobseeker.sh new daily")
        check("its new-job task does not ask for a push", made.returncode == 0 and "push" not in made.stdout, made.stdout)
        (shared_folder / "shared" / "ledger.py").write_text('def ledger() -> str:\n    return "LEDGER-FROM-SHARED"\n')
        daily = shared_folder / "jobs" / "daily" / "main.py"
        daily.write_text(daily.read_text().replace("from jobseeker import JobSeeker", "from jobseeker import JobSeeker\nfrom shared.ledger import ledger")
                         .replace('        print(f"', '        print(ledger())\n        print(f"'))
        _, local_jobs = post_json(browser, "/jobCreation/projectWorkspaceJobs", {"project_id": local_id})
        daily_job = next(job for job in local_jobs["jobs"] if job["name"] == "daily")
        browser.request("/jobCreation/send", method="POST", csrf=True, fields={
            "job_name": SHARED_JOB, "job_names": "", "description": "Shared folder e2e", "environment": "DEV",
            "checkEnvironment": "1", "timestamp": "1", "trigger_after_save": "0", "linuxCommand": "1",
            "linuxExecutionStrategy": "script", "linuxScriptType": "python", "pythonSourceMode": "path",
            "pythonSourcePath": daily_job["sourcePath"], "pythonEntryPoint": "main.py", "pythonRuntimeMode": "local",
            "pythonVersion": "python3", "checkBuild": "0", "abort": "0", "runJobCheck": "0", "emailCheck": "0",
            "editableEmailCheck": "0", "winCommand": "0", "action": "0"})
        jobs.append(SHARED_JOB)
        result, console = build(browser, SHARED_JOB)
        check("a shared-folder job imports the project's shared/ code in its build",
              result == "SUCCESS" and "LEDGER-FROM-SHARED" in console, console[-2000:])

        # --- A personal branch -------------------------------------------------
        _, second = post_json(browser, "/jobCreation/projectWorkspaceCreateProject",
                              {"name": PERSONAL_PROJECT, "type": "python", "repository_url": URL})
        second_id = str(second["project"]["id"])
        project_ids.append(second_id)
        _, mine = post_json(browser, "/jobCreation/projectWorkspaceOpen",
                            {"project_id": second_id, "environment": "DEV", "branch_mode": "personal"})
        personal = ROOT / mine["workspacePath"]
        workspaces.append(personal)
        check("a personal branch forks the DEV branch", mine.get("workspaceBranch", "").startswith("work/")
              and run("git", "-C", str(personal), "rev-parse", "HEAD").stdout == run("git", "-C", str(workspace), "rev-parse", "origin/develop").stdout,
              json.dumps(mine))
        check("a personal branch is published by its first push, not merged into develop",
              run("git", "-C", str(personal), "rev-parse", "--abbrev-ref", "@{upstream}").returncode != 0)
        check("an existing repository gets no layout files", mine.get("scaffolded") == [], json.dumps(mine))

        # --- Move an inline job into the project --------------------------------
        inline = ROOT / "repository" / "python" / "inline" / MOVED_JOB
        workspaces.append(inline)
        _, moved = post_json(browser, "/jobCreation/inlinePythonConvertToGit", {
            "job_name": MOVED_JOB, "entry_point": "main.py", "pythonInlineCode": 'print("MOVED")\n',
            "pythonRequirementsText": "", "pythonPyprojectText": "", "pythonDockerfileText": "",
            "pythonInlineFilesJson": json.dumps({"files": [], "directories": []}), "pythonWorkspaceSignature": "",
            "pythonRuntimeMode": "local", "pythonUseDockerfile": "0", "pythonVersion": "python3", "pythonDockerImage": "",
            "environment": "DEV", "pythonProjectId": project_id, "pythonRepositoryBranch": "",
            "pythonGitJobPath": "jobs/moved", "commit_message": f"e2e move {RUN}", "overwrite": "0"})
        check("Move to Git pushes into the job's folder", moved.get("ok") is True and moved.get("jobPath") == "jobs/moved"
              and "jobs/moved/main.py" in remote("ls-tree", "-r", "--name-only", "develop"), json.dumps(moved))
        check("the mover's own workspace is left for them to pull", "Pull develop" in moved.get("message", "")
              and not (workspace / "jobs" / "moved").exists(), json.dumps(moved))
    finally:
        run("docker", "exec", "-u", "0", "-i", JENKINS, "sh", "-c", f"cat > {HELPER} && chmod 0755 {HELPER}", input=original_helper)
        for job in jobs:
            browser.request("/delete-job/jobs", method="POST", csrf=True, fields={"jobs": job, "environment": "DEV"})
        for project_id in project_ids:
            if project_id:
                browser.request("/Context/deleteProject", method="POST", csrf=True, fields={"userId": project_id})
        if connector_id:
            browser.request("/dbSettings/deleteSetting?environment=DEV", method="POST", csrf=True, fields={"userId": connector_id})
        if account_id:
            browser.request("/gitAccountDelete", method="POST", csrf=True, fields={"id": account_id})
        for path in workspaces:
            shutil.rmtree(path, ignore_errors=True)
        run("docker", "rm", "-f", SERVER)
        run("docker", "volume", "rm", VOLUME)

    print(f"\nProject workspace e2e: {passed} checks passed.")


if __name__ == "__main__":
    main()
