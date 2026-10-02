#!/usr/bin/env python3
"""Workspace runtimes, end to end (doc/jobseeker/Architecture/workspace-runtimes.md).

On the running Compose stack: a Python runtime is added to the catalog and
built through the Runtimes page; a project without Git chooses it and opens
in an editor of its own, reached through the workspace gateway, which never
forwards JobSeeker's cookies; the project's bootstrap builds .venv on the
runtime's Python and preinstalled packages; a Docker job runs the same image,
and another builds FROM it. The project then switches to its own
devcontainer.json, shared by the team, which builds on first open and applies
its environment, extensions, settings and postCreateCommand. Stopping,
removing and deleting clean everything up.
"""

from __future__ import annotations

import importlib.util
import io
import json
import os
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zipfile
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
RUNTIME = f"e2e-rt-{RUN}"
PROJECT = f"E2E Runtime {RUN}"
GIT_PROJECT = f"E2E Runtime Git {RUN}"
GIT_SERVER = f"jobseeker-git-runtime-{RUN}"
GIT_URL = f"http://{GIT_SERVER}:8000/team.git"
NETWORK = os.environ.get("JOBSEEKER_E2E_NETWORK", "jobseeker_internal")
DEVELOPER_EMAIL = os.environ.get("JOBSEEKER_E2E_DEVELOPER_EMAIL", "developer@example.com")
DEVELOPER_PASSWORD = os.environ.get("JOBSEEKER_E2E_DEVELOPER_PASSWORD", "123456")
ENGINE = os.environ.get("JOBSEEKER_E2E_DOCKER_RUNTIME", "jobseeker-docker-runtime-1")
def _gateway_tls() -> bool:
    """Whether the stack serves the workspace gateway over HTTPS (.env)."""
    env = ROOT / ".env"
    lines = env.read_text(encoding="utf-8").splitlines() if env.is_file() else []
    values = dict(line.split("=", 1) for line in lines if "=" in line and not line.lstrip().startswith("#"))
    return values.get("JOBSEEKER_WORKSPACE_GATEWAY_TLS", "false").strip().lower() in ("1", "true", "yes", "on")


GATEWAY_CA = ROOT / "nginx" / "workspace-gateway-tls" / "ca.crt"
GATEWAY = os.environ.get("JOBSEEKER_E2E_GATEWAY_URL", ("https" if _gateway_tls() else "http") + "://127.0.0.1:3001")
ECHO_PORT = 3499

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


def sign_in(email: str, password: str):
    browser = matrix.Browser()
    browser.request("/")
    _, page = browser.request("/loginMe", method="POST", fields={"email": email, "password": password}, csrf=True)
    check(f"{email} signs in", "logout" in page.lower())
    return browser


def mounts(container: str) -> list[str]:
    return sorted(mount["Destination"] for mount in json.loads(engine("inspect", container).stdout)[0]["Mounts"])


def engine(*command: str) -> subprocess.CompletedProcess:
    return run("docker", "exec", ENGINE, "docker", *command)


def post_json(browser, path: str, fields: dict, expected=(200,)) -> tuple[int, dict]:
    status, body = browser.request(path, method="POST", csrf=True, fields=fields, expected=expected)
    return status, json.loads(body)


def get_json(browser, path: str, expected=(200,)) -> dict:
    _, body = browser.request(path, expected=expected)
    return json.loads(body)


def follow(browser, project_id: int, timeout: int = 1200) -> dict:
    """Polls a project's runtime as the launcher does, until its editor answers."""
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        _, status = post_json(browser, f"/jobCreation/projectWorkspaceRuntimeStatus?project_id={project_id}", {"project_id": project_id})
        if status.get("ready"):
            return status
        if status.get("ok") is False:
            check("the runtime starts", False, json.dumps(status)[:3000])
        time.sleep(3)
    check("the runtime starts in time", False)
    return {}


def editor(container: str, folder: str, command: str, uid: str) -> subprocess.CompletedProcess:
    # VS Code tasks inherit this from the editor server.
    return engine("exec", "-u", uid, "-e", "JOBSEEKER_VENV_SYSTEM_SITE_PACKAGES=1", "-w", folder, container, "sh", "-c", command)


def http(url: str, cookie: str = "") -> tuple[int, dict, str]:
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args, **kwargs):
            return None
    handlers = [NoRedirect]
    if url.startswith("https://"):
        # Verified against the gateway's own authority, which covers 127.0.0.1.
        import ssl
        handlers.append(urllib.request.HTTPSHandler(context=ssl.create_default_context(cafile=str(GATEWAY_CA))))
    opener = urllib.request.build_opener(*handlers)
    request = urllib.request.Request(url, headers={"Cookie": cookie} if cookie else {})
    try:
        with opener.open(request, timeout=20) as response:
            return response.status, dict(response.headers), response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, dict(error.headers), error.read().decode("utf-8", "replace")


def templates_phase(browser, owner: str, cleanup: dict) -> None:
    """The template gallery: previews, .devcontainer downloads, and a project choosing a template."""
    catalog = get_json(browser, "/workspace-runtimes/catalog")
    templates = {template["key"]: template for template in catalog["templates"]}
    check("the gallery offers many templates across categories", len(templates) >= 18 and {"data-engineering", "streaming", "analytics", "apis", "ml"} <= set(catalog["categories"])
          and all(templates[key]["category"] in catalog["categories"] for key in templates), json.dumps(sorted(templates)))
    draft = templates["fullstack"]["draft"]
    fields = {"name": draft["name"], "kind": draft["kind"], "python_version": draft["spec"]["python_version"], "python_packages": draft["spec"]["python_packages"],
              "extensions": "\n".join(draft["spec"]["extensions"]), "ports": ", ".join(str(port) for port in draft["spec"]["ports"]),
              "features": json.dumps({feature["ref"]: feature["options"] or {} for feature in draft["spec"]["features"]})}
    _, preview = post_json(browser, "/workspace-runtimes/preview", fields)
    definition = preview["files"].get(".devcontainer/devcontainer.json", "")
    check("the editor previews the Dockerfile and devcontainer.json a form makes", not preview["errors"] and "FROM python:3.13-slim" in preview["files"][".devcontainer/Dockerfile"]
          and "ghcr.io/devcontainers/features/node:1" in definition and "5173" in definition and "esbenp.prettier-vscode" in definition, json.dumps(preview)[:1500])
    raw = browser.opener.open(matrix.BASE_URL + "/workspace-runtimes/devcontainer?template=machine-learning").read()
    with zipfile.ZipFile(io.BytesIO(raw)) as archive:
        names = sorted(archive.namelist())
        exported = archive.read(".devcontainer/devcontainer.json").decode()
        dockerfile = archive.read(".devcontainer/Dockerfile").decode()
    sdk = [name for name in names if name.startswith(".devcontainer/jobseeker-sdk/")]
    check("any template downloads as a .devcontainer for VS Code or Codespaces", [name for name in names if name not in sdk] == [".devcontainer/Dockerfile", ".devcontainer/README.md", ".devcontainer/devcontainer.json", ".devcontainer/requirements.txt"]
          and "ms-toolsai.jupyter" in exported and "5000" in exported, json.dumps(names))
    check("the download bundles the JobSeeker SDK and installs it", ".devcontainer/jobseeker-sdk/pyproject.toml" in sdk and ".devcontainer/jobseeker-sdk/src/jobseeker/__init__.py" in sdk
          and not any("__pycache__" in name or name.endswith(".pyc") for name in sdk) and "COPY jobseeker-sdk /opt/jobseeker-sdk" in dockerfile, json.dumps(sdk))

    existing = {runtime["key"] for runtime in catalog["runtimes"] if runtime["spec"].get("template") == "python-essentials" or runtime["key"] == "python-essentials"}
    _, created = post_json(browser, "/jobCreation/projectWorkspaceCreateProject", {"name": f"E2E Template {RUN}", "type": "python", "repository_url": ""})
    project_id = int(created["project"]["id"])
    cleanup["projects"].append(project_id)
    cleanup["folders"].append(ROOT / created["project"]["workspace"]["path"])
    _, chosen = post_json(browser, "/jobCreation/projectWorkspaceRuntimeSave", {"project_id": project_id, "runtime": "template:python-essentials", "isolation": "user"})
    runtime_key = chosen["settings"]["runtimeKey"]
    added = next((runtime for runtime in get_json(browser, "/workspace-runtimes/catalog")["runtimes"] if runtime["key"] == runtime_key), None)
    check("choosing a template adds it to the catalog", added is not None and added["spec"].get("template") == "python-essentials", json.dumps(chosen))
    if runtime_key not in existing:
        cleanup["runtimes"].append(runtime_key)
    _, opened = post_json(browser, "/jobCreation/projectWorkspaceOpen", {"project_id": project_id, "environment": "DEV"})
    follow(browser, project_id)
    container = f"jobseeker-ide-p{project_id}-u1"
    probe = engine("exec", "-u", owner, container, "sh", "-c", "python -c 'import httpx, typer, pydantic_settings' && echo ok && ls /opt/jobseeker-ide/extensions")
    check("a project opens in the template's runtime, with its packages and extensions", probe.returncode == 0 and probe.stdout.startswith("ok")
          and "tamasfe.even-better-toml" in probe.stdout, probe.stdout + probe.stderr)


def git_phase(admin, owner: str, cleanup: dict) -> None:
    """Git projects: a working copy per person, and editors just for them or shared."""
    started = run("docker", "run", "-d", "--name", GIT_SERVER, "--network", NETWORK, "--user", "0", "-v", f"{GIT_SERVER}:/srv",
                  "--entrypoint", "python3", "jobseeker-jenkins", "-c", git_workspace.GIT_HTTP_SERVER)
    cleanup["containers"].append(GIT_SERVER)
    check("a team Git server is running", started.returncode == 0, started.stderr)
    run("docker", "exec", GIT_SERVER, "git", "init", "-q", "--bare", "-b", "main", "/srv/team.git")
    developer = sign_in(DEVELOPER_EMAIL, DEVELOPER_PASSWORD)
    people = {}
    for name, browser, email in (("admin", admin, matrix.ADMIN_EMAIL), ("developer", developer, DEVELOPER_EMAIL)):
        browser.request("/gitAccountSave", method="POST", csrf=True, fields={
            "provider": "generic", "label": f"E2E team Git {name}", "host": GIT_SERVER + ":8000", "path_prefix": "",
            "auth_type": "token", "username": "e2e", "secret": "e2e-token", "known_hosts": ""})
        user_id = sql("SELECT userId FROM tbl_users WHERE email='%s'" % email)
        account = sql("SELECT id FROM user_git_accounts WHERE host='%s:8000' AND user_id=%s" % (GIT_SERVER, user_id))
        check(f"the {name} has a personal Git account", account.isdigit(), account)
        cleanup["accounts"].append((browser, account))
        people[name] = {"browser": browser, "id": user_id, "email": email}

    _, created = post_json(admin, "/jobCreation/projectWorkspaceCreateProject", {"name": GIT_PROJECT, "type": "python", "repository_url": GIT_URL})
    project_id = int(created["project"]["id"])
    cleanup["projects"].append(project_id)
    post_json(admin, "/jobCreation/projectWorkspaceRuntimeSave", {"project_id": project_id, "runtime": RUNTIME, "isolation": "user"})

    def open_as(name: str) -> tuple[str, str]:
        browser = people[name]["browser"]
        _, opened = post_json(browser, "/jobCreation/projectWorkspaceOpen", {"project_id": project_id, "environment": "DEV", "branch_mode": "shared"})
        follow(browser, project_id)
        path = opened["workspacePath"]
        cleanup["folders"].append(ROOT / path)
        return "/home/workspace/" + path, opened.get("runtime", {})

    # --- Just me: a container per person, each with only their own copy -----
    admin_copy, _ = open_as("admin")
    admin_container = f"jobseeker-ide-p{project_id}-u{people['admin']['id']}"
    pushed = editor(admin_container, admin_copy, "git add -A && git commit -q -m 'Start the project' && git push -q origin HEAD 2>&1 && git log -1 --format=%ae", owner)
    check("the admin commits and pushes from a runtime editor with their own account", pushed.returncode == 0
          and pushed.stdout.strip().endswith(matrix.ADMIN_EMAIL), pushed.stdout + pushed.stderr)
    remote = run("docker", "exec", GIT_SERVER, "git", "--git-dir", "/srv/team.git", "log", "--all", "--format=%ae %s").stdout
    check("the push reached the team repository", f"{matrix.ADMIN_EMAIL} Start the project" in remote, remote)

    developer_copy, _ = open_as("developer")
    developer_container = f"jobseeker-ide-p{project_id}-u{people['developer']['id']}"
    check("the developer's copy is cloned with the admin's push", (ROOT / developer_copy.replace("/home/workspace/", "")).joinpath("README.md").is_file())
    check("each person's editor mounts only their own working copy",
          admin_copy in mounts(admin_container) and developer_copy not in mounts(admin_container)
          and developer_copy in mounts(developer_container) and admin_copy not in mounts(developer_container),
          json.dumps({"admin": mounts(admin_container), "developer": mounts(developer_container)}))
    hidden = engine("exec", admin_container, "ls", developer_copy)
    check("one person's editor cannot see another's work", hidden.returncode != 0)

    # --- Shared: one container with everyone's working copies ---------------
    post_json(admin, "/jobCreation/projectWorkspaceRuntimeSave", {"project_id": project_id, "runtime": RUNTIME, "isolation": "shared"})
    open_as("admin")
    shared = f"jobseeker-ide-p{project_id}-shared"
    check("the team shares one container with every working copy", admin_copy in mounts(shared) and developer_copy in mounts(shared), json.dumps(mounts(shared)))
    _, reopened = open_as("developer")
    check("a teammate opening it joins the same container", reopened.get("shared") is True and not reopened.get("recreated")
          and json.loads(engine("inspect", shared).stdout)[0]["State"]["Running"], json.dumps(reopened))
    (ROOT / developer_copy.replace("/home/workspace/", "")).joinpath("jobs", "notes.md").write_text("From the developer\n")
    pushed = editor(shared, developer_copy, "git pull -q --rebase origin develop 2>/dev/null; git add -A && git commit -q -m 'Add notes' && git push -q origin HEAD 2>&1 && git log -1 --format=%ae", owner)
    check("in the shared editor each copy commits and pushes as its own person", pushed.returncode == 0
          and pushed.stdout.strip().endswith(DEVELOPER_EMAIL), pushed.stdout + pushed.stderr)
    remote = run("docker", "exec", GIT_SERVER, "git", "--git-dir", "/srv/team.git", "log", "--all", "--format=%ae %s").stdout
    check("both people's work meets in the repository", f"{DEVELOPER_EMAIL} Add notes" in remote and f"{matrix.ADMIN_EMAIL} Start the project" in remote, remote)


def main() -> None:
    browser = matrix.Browser()
    browser.request("/")
    _, login = browser.request("/loginMe", method="POST", fields={"email": matrix.ADMIN_EMAIL, "password": matrix.ADMIN_PASSWORD}, csrf=True)
    check("the administrator signs in", "logout" in login.lower())
    owner = run("stat", "-c", "%u:%g", str(ROOT / "repository")).stdout.strip()
    project_id = 0
    folder = None
    jobs: list[str] = []
    # Filled by git_phase: projects, folders, Git accounts (browser, id), containers.
    cleanup: dict = {"projects": [], "folders": [], "accounts": [], "containers": [], "runtimes": []}
    try:
        catalog = get_json(browser, "/workspace-runtimes/catalog")
        check("the job runtime and workspace toolkit are available", catalog["engine"]["available"] and catalog["engine"]["toolkit"], json.dumps(catalog["engine"]))

        # --- The catalog -------------------------------------------------------
        fields = {"name": f"E2E Runtime {RUN}", "key": RUNTIME, "description": "Workspace runtime e2e", "kind": "python",
                  "python_version": "3.13", "system_packages": "jq", "python_packages": "tabulate==0.9.0\n"}
        _, saved = post_json(browser, "/workspace-runtimes/save", fields)
        check("a Python runtime is added to the catalog", saved["ok"] and saved["key"] == RUNTIME, json.dumps(saved))
        status, duplicate = post_json(browser, "/workspace-runtimes/save", fields, expected=(409,))
        check("a second runtime cannot take its key", status == 409 and "already exists" in duplicate["message"])
        status, invalid = post_json(browser, "/workspace-runtimes/save", dict(fields, key="", name="Broken", system_packages="curl; rm -rf /"), expected=(400,))
        check("shell in package names is refused", status == 400 and "system_packages" in invalid.get("errors", {}), json.dumps(invalid))

        _, started = post_json(browser, "/workspace-runtimes/build", {"key": RUNTIME, "force": "0"})
        check("the build starts in the background", started["ok"] and started["started"], json.dumps(started))
        deadline = time.monotonic() + 1200
        log = {}
        while time.monotonic() < deadline:
            log = get_json(browser, f"/workspace-runtimes/log?key={RUNTIME}")
            if log["build"]["status"] != "building":
                break
            time.sleep(5)
        check("the runtime and its editor image are built", log["build"]["status"] == "ready"
              and any(line.startswith("Successfully tagged " + log["ideImage"]) for line in log["lines"]), json.dumps(log)[-2000:])
        image = log["image"]
        check("the runtime image exists in the job runtime", engine("image", "inspect", image).returncode == 0)
        _, again = post_json(browser, "/workspace-runtimes/build", {"key": RUNTIME, "force": "0"})
        check("an unchanged recipe is never rebuilt", again["ok"] and not again["started"], json.dumps(again))

        # --- A project on the runtime, just for me ----------------------------
        _, created = post_json(browser, "/jobCreation/projectWorkspaceCreateProject", {"name": PROJECT, "type": "python", "repository_url": ""})
        project_id = int(created["project"]["id"])
        folder = ROOT / created["project"]["workspace"]["path"]
        panel = get_json(browser, f"/jobCreation/projectWorkspaceRuntime?project_id={project_id}")
        check("a new project opens in the Default editor", panel["enabled"] and panel["settings"]["runtimeKey"] == "default")
        check("the launcher lists the built runtime", any(r["key"] == RUNTIME and r["status"] == "ready" for r in panel["runtimes"]), json.dumps(panel["runtimes"]))
        status, refused = post_json(browser, "/jobCreation/projectWorkspaceRuntimeSave", {"project_id": project_id, "runtime": "nope"}, expected=(400,))
        check("a runtime outside the catalog is refused", status == 400)
        _, chosen = post_json(browser, "/jobCreation/projectWorkspaceRuntimeSave", {"project_id": project_id, "runtime": RUNTIME, "isolation": "user", "cpus": "1.5", "memory_mb": "2048"})
        check("the project chooses the runtime", chosen["settings"]["runtimeKey"] == RUNTIME, json.dumps(chosen))

        _, opened = post_json(browser, "/jobCreation/projectWorkspaceOpen", {"project_id": project_id, "environment": "DEV", "branch_mode": "shared", "branch": ""})
        check("opening deploys the runtime editor", opened["runtime"]["key"] == RUNTIME and opened.get("openVsCodeStatusUrl"), json.dumps(opened))
        status = follow(browser, project_id)
        url = status.get("openVsCodeUrl") or opened["openVsCodeUrl"]
        check("the editor answers through the workspace gateway", url.startswith(GATEWAY + "/ide/31"), url)
        port = urllib.parse.urlparse(url).path.split("/")[2]
        token = urllib.parse.parse_qs(urllib.parse.urlparse(url).query)["tkn"][0]
        container = f"jobseeker-ide-p{project_id}-u1"
        inspect = json.loads(engine("inspect", container).stdout)[0]
        check("each person gets a container of their own", inspect["State"]["Running"])
        check("the editor runs as the checkout owner, without capabilities", inspect["Config"]["User"] == owner
              and inspect["HostConfig"]["CapDrop"] == ["ALL"] and "no-new-privileges" in inspect["HostConfig"]["SecurityOpt"], json.dumps(inspect["Config"]["User"]))
        check("the project's CPU and memory limits apply", inspect["HostConfig"]["NanoCpus"] == 1500000000 and inspect["HostConfig"]["Memory"] == 2048 * 1048576)
        targets = sorted(mount["Destination"] for mount in inspect["Mounts"])
        workspace = "/home/workspace/" + created["project"]["workspace"]["path"]
        check("only the project, the SDK and Data Assets are mounted", targets == sorted(["/home/workspace", workspace,
              "/home/workspace/repository/python/lib", "/home/workspace/repository/data-assets"]), json.dumps(targets))

        # --- The gateway ------------------------------------------------------
        code, headers, _ = http(url)
        check("the launch URL trades its token for a cookie scoped to this editor", code == 302 and "vscode-tkn=" + token in headers.get("Set-Cookie", "")
              and "Path" not in headers.get("Set-Cookie", ""), json.dumps(headers))
        code, _, page = http(f"{GATEWAY}/ide/{port}/", f"ci_session=SECRET; vscode-tkn={token}")
        check("the cookie opens the workbench", code == 200 and "workbench" in page.lower())
        code, _, _ = http(f"{GATEWAY}/ide/{port}/", "ci_session=SECRET")
        check("without its token the editor refuses", code == 403)
        check("the gateway serves nothing else", http(f"{GATEWAY}/")[0] == 404 and http(f"{GATEWAY}/ide/8080/")[0] == 404)
        engine("run", "-d", "--rm", "--name", f"e2e-echo-{RUN}", "--network", "host", "alpine:3.20", "sh", "-c",
               f'printf "HTTP/1.1 200 OK\\r\\nContent-Length: 2\\r\\nConnection: close\\r\\n\\r\\nok" | nc -l -p {ECHO_PORT}; sleep 30')
        time.sleep(2)
        http(f"{GATEWAY}/ide/{ECHO_PORT}/probe", "ci_session=SECRET-SESSION; csrf_cookie_name=abc; vscode-tkn=TOKEN")
        received = engine("logs", f"e2e-echo-{RUN}").stdout
        engine("rm", "-f", f"e2e-echo-{RUN}")
        check("editors never see JobSeeker's cookies", "Cookie: vscode-tkn=TOKEN" in received and "SECRET-SESSION" not in received and "csrf" not in received, received)

        # --- Developing in the runtime ----------------------------------------
        made = editor(container, workspace, "sh .vscode/jobseeker.sh new orders", owner)
        check("the project's own tasks run in the runtime", made.returncode == 0, made.stdout + made.stderr)
        (folder / "jobs" / "orders" / "pyproject.toml").write_text('[project]\nname = "orders"\nversion = "0.1.0"\nrequires-python = ">=3.13"\ndependencies = ["python-slugify==8.0.4"]\n')
        boot = editor(container, workspace, "sh .vscode/bootstrap-python.sh", owner)
        check("the workspace bootstrap succeeds in the runtime", boot.returncode == 0 and "OSTYPE" not in boot.stderr, (boot.stdout + boot.stderr)[-2000:])
        probe = editor(container, workspace, '.venv/bin/python -c "import tabulate, slugify, jobseeker; print(tabulate.__file__)"; cat .venv/.jobseeker-runtime; command -v jq', owner)
        check(".venv sees the runtime's packages and gets the job's own", probe.returncode == 0 and "/usr/local/lib/python3.13" in probe.stdout
              and f"{RUNTIME} --system-site-packages" in probe.stdout and "/usr/bin/jq" in probe.stdout, probe.stdout + probe.stderr)
        tests = editor(container, workspace + "/jobs/orders", f"PYTHONPATH={workspace} ../../.venv/bin/python -m pytest -q", owner)
        check("the job's tests pass in the runtime", tests.returncode == 0 and "1 passed" in tests.stdout, tests.stdout + tests.stderr)
        # A notebook kernel runs .venv's Python with the container's environment, not a terminal's.
        kernel = engine("exec", "-u", owner, "-w", workspace + "/jobs/orders", container, "sh", "-c",
                        '../../.venv/bin/python -c "import os, shared; print(shared.__file__, os.environ[\'JOBSEEKER_REPOSITORY_ROOT\'])"; command -v ps;'
                        ' grep -c -- --disable-workspace-trust /opt/jobseeker-ide/bin/jobseeker-ide')
        check("notebooks import shared/, resolve Data Assets, and open trusted, with ps for their kernels", kernel.returncode == 0
              and f"{workspace}/shared/__init__.py /home/workspace/repository" in kernel.stdout and "/ps\n" in kernel.stdout and kernel.stdout.strip().endswith("1"),
              kernel.stdout + kernel.stderr)
        # Code in the editor reads connectors and Context for the environment the
        # workspace was opened in, through the session JobSeeker writes there.
        session = engine("exec", "-u", owner, "-w", workspace + "/jobs/orders", container, "sh", "-c",
                         '../../.venv/bin/python -c "from jobseeker import JobSeeker, get_connector; print(get_connector(\'jobseeker-mariadb\').host, JobSeeker(install_signal_handlers=False).environment)"')
        check("runs in the editor resolve connectors and their environment through the workspace session", session.returncode == 0
              and session.stdout.split()[-1:] == ["DEV"] and "mariadb" in session.stdout, session.stdout + session.stderr)
        sampled = editor(container, workspace, "sh .vscode/jobseeker-samples.sh python-task-dag", owner)
        check("the sample task adds a library sample as a job folder for the runtime's Python", sampled.returncode == 0
              and (folder / "jobs" / "task-dag" / "main.py").is_file() and not (folder / "jobs" / "task-dag" / "Dockerfile").exists()
              and '">=3.13' in (folder / "jobs" / "task-dag" / "pyproject.toml").read_text(), sampled.stdout + sampled.stderr)
        _, launched = post_json(browser, "/jobCreation/projectWorkspaceSample", {"project_id": project_id, "sample_id": "python-sdk-progress"})
        again, twice = post_json(browser, "/jobCreation/projectWorkspaceSample", {"project_id": project_id, "sample_id": "python-sdk-progress"}, expected=(409,))
        check("the launcher adds samples too, never over a folder", launched.get("path") == "jobs/sdk-progress" and (folder / "jobs" / "sdk-progress" / "main.py").is_file()
              and again == 409, json.dumps([launched, twice]))

        # --- Jobs run the same image ------------------------------------------
        _, page = browser.request("/jobCreation")
        check("Job Creation offers the runtime image to Docker jobs", f'value="{image}"' in page)
        (folder / "jobs" / "report").mkdir(parents=True, exist_ok=True)
        shutil.copy(folder / "jobs" / "orders" / "main.py", folder / "jobs" / "report" / "main.py")
        # Its image keeps packages in a virtual environment on PATH, which the
        # job's login shell must not lose.
        (folder / "jobs" / "report" / "main.py").write_text("import humanize  # noqa: F401\n" + (folder / "jobs" / "report" / "main.py").read_text())
        (folder / "jobs" / "report" / "Dockerfile").write_text(f"FROM {image}\nRUN python -m venv /opt/venv && /opt/venv/bin/pip install --quiet humanize==4.12.3\n"
                                                                "ENV PATH=/opt/venv/bin:$PATH\nWORKDIR /app\nCOPY . .\n")
        picked = post_json(browser, "/jobCreation/projectWorkspaceJobs", {"project_id": project_id})[1]
        check("a project's jobs default to its runtime image", (picked.get("runtime") or {}).get("image") == image, json.dumps(picked.get("runtime")))
        found = {job["name"]: job for job in picked["jobs"]}
        for name in ("orders", "report"):
            job = f"e2e-rt-{name}-{RUN}"
            browser.request("/jobCreation/send", method="POST", csrf=True, fields={
                "job_name": job, "job_names": "", "description": "Workspace runtime e2e", "environment": "DEV",
                "checkEnvironment": "1", "timestamp": "1", "trigger_after_save": "0", "linuxCommand": "1",
                "linuxExecutionStrategy": "script", "linuxScriptType": "python", "pythonSourceMode": "path",
                "pythonSourcePath": found[name]["sourcePath"], "pythonEntryPoint": "main.py", "pythonRuntimeMode": "docker",
                "pythonVersion": "python3", "pythonDockerImage": image, "pythonUseDockerfile": "0", "pythonRunTests": "1",
                "checkBuild": "0", "abort": "0", "runJobCheck": "0", "emailCheck": "0", "editableEmailCheck": "0", "winCommand": "0", "action": "0"})
            jobs.append(job)
            browser.request(matrix.proxy_path(f"job/{urllib.parse.quote(job, safe='')}/buildWithParameters?ENVIRONMENT=DEV"),
                            method="POST", csrf=True, expected=(200, 201, 302))
            result, _, console = matrix.wait_for_build(browser, job)
            check(f"a Docker job {'builds FROM' if name == 'report' else 'runs'} the runtime image", result == "SUCCESS" and f"processed" in console
                  and (name == "orders" or "base images are not pulled" in console), console[-2500:])

        # --- A notebook as a job's entry file ----------------------------------
        _, added = post_json(browser, "/jobCreation/projectWorkspaceSample", {"project_id": project_id, "sample_id": "notebook-data-checks"})
        notebook_folder = folder / "jobs" / "notebook-data-checks"
        check("the launcher adds a notebook sample with its kernel dependencies", added.get("path") == "jobs/notebook-data-checks"
              and json.loads((notebook_folder / "checks.ipynb").read_text())["nbformat"] == 4
              and "ipykernel" in (notebook_folder / "requirements.txt").read_text(), json.dumps(added))
        picked = post_json(browser, "/jobCreation/projectWorkspaceJobs", {"project_id": project_id})[1]
        notebook_job = {job["name"]: job for job in picked["jobs"]}["notebook-data-checks"]
        check("a folder's notebook is its entry file", notebook_job["entryPoint"] == "checks.ipynb" and notebook_job["notebooks"] == ["checks.ipynb"], json.dumps(notebook_job))
        defaults = post_json(browser, "/jobCreation/notebookParameters", {"source_path": notebook_job["sourcePath"], "entry": "checks.ipynb", "environment": "DEV"})[1]
        check("Job Creation reads the notebook's parameters cell", defaults["hasParametersCell"] and [p["name"] for p in defaults["parameters"]][:2] == ["asset_key", "min_rows"]
              and defaults["project"]["id"] == project_id, json.dumps(defaults))
        sql(f"INSERT INTO contextdetails (ProjectDetailsFK, ContextKey, ContextValue, isEncrypted, EnvironmentFK, Description, IsActive, CreatedOn, CreatedBy) "
            f"SELECT {project_id}, 'e2e_min_rows', '2', 0, Id, 'notebook e2e', 1, NOW(), 'e2e' FROM environment WHERE Environment = 'DEV'")
        notebook_name = f"e2e-rt-notebook-{RUN}"
        browser.request("/jobCreation/send", method="POST", csrf=True, fields={
            "job_name": notebook_name, "job_names": "", "description": "Workspace runtime notebook e2e", "environment": "DEV",
            "checkEnvironment": "1", "timestamp": "1", "trigger_after_save": "0", "linuxCommand": "1",
            "linuxExecutionStrategy": "script", "linuxScriptType": "python", "pythonSourceMode": "path",
            "pythonSourcePath": notebook_job["sourcePath"], "pythonEntryPoint": "checks.ipynb", "pythonRuntimeMode": "docker",
            "pythonVersion": "python3", "pythonDockerImage": image, "pythonUseDockerfile": "0", "pythonRunTests": "0",
            "pythonNotebookParameters": json.dumps([{"name": "min_rows", "source": "context", "value": "e2e_min_rows"}, {"name": "max_amount", "source": "value", "value": "500"}]),
            "pythonNotebookCellTimeout": "300", "pythonNotebookTrack": "1",
            "checkBuild": "0", "abort": "0", "runJobCheck": "0", "emailCheck": "0", "editableEmailCheck": "0", "winCommand": "0", "action": "0"})
        jobs.append(notebook_name)
        config = browser.request(matrix.proxy_path(f"job/{urllib.parse.quote(notebook_name, safe='')}/config.xml"))[1]
        check("a notebook job takes per-run parameters instead of task ones", "<name>JOBSEEKER_NOTEBOOK_PARAMETERS</name>" in config
              and "<name>JOBSEEKER_DAG_RESUME</name>" not in config and "JOBSEEKER_NOTEBOOK_SPEC=" in config and "jobseeker.notebook run" in config, config[:3000])
        browser.request(matrix.proxy_path(f"job/{urllib.parse.quote(notebook_name, safe='')}/buildWithParameters?ENVIRONMENT=DEV"), method="POST", csrf=True, expected=(200, 201, 302))
        result, build, console = matrix.wait_for_build(browser, notebook_name)
        check("the notebook runs in the runtime image, its parameters from Context and the job", result == "SUCCESS"
              and '"min_rows": {"value": 2, "from": "context e2e_min_rows"}' in console and "[JobSeeker Notebook] finish | SUCCESS" in console, console[-3000:])
        published = f"notebook-runs/{notebook_name}/{build}/checks.ipynb"
        check("the executed notebook is published with the run", f"[JobSeeker Notebook] saved | {published}" in console and (ROOT / "repository" / published).is_file(), console[-1500:])
        served = json.loads(browser.request("/jobView/notebookRun?path=" + urllib.parse.quote(published))[1])
        check("View Job serves the executed notebook", served["metadata"]["jobseeker"]["status"] == "success"
              and any("injected-parameters" in (c.get("metadata", {}).get("tags") or []) for c in served["cells"]))
        tmf = sql(f"SELECT status, dimension, records_total, records_processed FROM tmf WHERE job_name = '{notebook_name}' ORDER BY id DESC LIMIT 1")
        check("Transaction Monitoring records the run, cells as records", tmf.split("\t")[:2] == ["ready", "notebook"] and tmf.split("\t")[2] == tmf.split("\t")[3], tmf)
        browser.request(matrix.proxy_path(f"job/{urllib.parse.quote(notebook_name, safe='')}/buildWithParameters?ENVIRONMENT=DEV&"
                                          + urllib.parse.urlencode({"JOBSEEKER_NOTEBOOK_PARAMETERS": '{"min_rows": 10}'})), method="POST", csrf=True, expected=(200, 201, 302))
        previous = build
        deadline = time.monotonic() + 180
        while time.monotonic() < deadline:
            last = json.loads(browser.request(matrix.proxy_path(f"job/{urllib.parse.quote(notebook_name, safe='')}/api/json?tree=lastBuild[number]"))[1]).get("lastBuild") or {}
            if int(last.get("number") or 0) > previous:
                break
            time.sleep(2)
        result, build, console = matrix.wait_for_build(browser, notebook_name)
        check("a run override fails the run at the check that does not hold", result == "FAILURE" and '"from": "run override"' in console
              and "[JobSeeker Notebook] error 5/7 | AssertionError | Only 3 rows, expected at least 10" in console
              and "[JobSeeker Notebook] done 7/7 | skipped" in console, console[-3000:])
        failure = sql(f"SELECT msg FROM tmf WHERE job_name = '{notebook_name}' ORDER BY id DESC LIMIT 1")
        check("the TMF error carries the failing cell's traceback", "Traceback (most recent call last)" in failure and "checks.ipynb, cell 5" in failure, failure)

        # --- The project's own dev container, shared ---------------------------
        _, starter = post_json(browser, "/jobCreation/projectWorkspaceDevcontainerStarter", {"project_id": project_id, "source": "template:dashboards"})
        written = folder / ".devcontainer"
        check("the launcher starts a project's dev container from a template", sorted(starter["written"]) == [".devcontainer/Dockerfile", ".devcontainer/README.md", ".devcontainer/devcontainer.json", ".devcontainer/requirements.txt"]
              and "streamlit" in (written / "requirements.txt").read_text() and '"forwardPorts": [' in (written / "devcontainer.json").read_text(), json.dumps(starter))
        status, again = post_json(browser, "/jobCreation/projectWorkspaceDevcontainerStarter", {"project_id": project_id}, expected=(409,))
        check("a starter never replaces a dev container", status == 409)
        devcontainer = folder / ".devcontainer"
        devcontainer.mkdir(exist_ok=True)
        (devcontainer / "devcontainer.json").write_text("""{
  // The team's environment for this project.
  "build": { "dockerfile": "Dockerfile", "context": "..", "args": { "EXTRA": "tabulate==0.9.0" }, },
  "containerEnv": { "PROJECT_FLAVOR": "e2e", "SKIPPED": "${localEnv:HOME}" },
  "customizations": { "vscode": { "extensions": ["redhat.vscode-yaml"], "settings": { "editor.tabSize": 2 } } },
  "postCreateCommand": "python -c 'import rich, tabulate' && echo post-create-ok > /home/workspace/.jobseeker/marker",
  "features": { "ghcr.io/devcontainers/features/node:1": { "version": "20", "nodeGypDependencies": false } },
  "runArgs": ["--privileged"],
}
""")
        (devcontainer / "Dockerfile").write_text('FROM python:3.12-slim\nARG EXTRA\nCOPY requirements-dev.txt /tmp/\nRUN pip install --no-cache-dir -r /tmp/requirements-dev.txt "$EXTRA"\n')
        (folder / "requirements-dev.txt").write_text("rich==13.9.4\n")
        post_json(browser, "/jobCreation/projectWorkspaceRuntimeSave", {"project_id": project_id, "runtime": "devcontainer", "isolation": "shared", "cpus": "1", "memory_mb": "2048"})
        panel = get_json(browser, f"/jobCreation/projectWorkspaceRuntime?project_id={project_id}")
        check("the launcher finds the project's devcontainer.json", panel["devcontainer"] == ".devcontainer/devcontainer.json")
        check("unsupported dev container parts are reported", any("runArgs" in warning for warning in panel["warnings"])
              and any("SKIPPED" in warning for warning in panel["warnings"]) and not any("feature" in warning for warning in panel["warnings"]),
              json.dumps(panel.get("warnings")))
        _, opened = post_json(browser, "/jobCreation/projectWorkspaceOpen", {"project_id": project_id, "environment": "DEV"})
        check("a first open builds the dev container before the editor starts", opened["runtime"]["status"] == "building"
              and "openVsCodeUrl" not in opened, json.dumps(opened)[:1500])
        follow(browser, project_id)
        shared = f"jobseeker-ide-p{project_id}-shared"
        time.sleep(3)
        inside = engine("exec", shared, "sh", "-c", 'python --version; python -c "import rich, tabulate"; echo "$PROJECT_FLAVOR ${SKIPPED:-unset}";'
                        ' cat /home/workspace/.jobseeker/marker; ls /opt/jobseeker-ide/extensions; cat /home/workspace/.openvscode-server/data/Machine/settings.json')
        check("the team shares one container built from the project's Dockerfile", inside.returncode == 0 and "Python 3.12" in inside.stdout, inside.stdout + inside.stderr)
        check("its environment, extensions, settings and postCreateCommand apply", "e2e unset" in inside.stdout and "post-create-ok" in inside.stdout
              and "redhat.vscode-yaml" in inside.stdout and '"editor.tabSize":2' in inside.stdout, inside.stdout)
        node = engine("exec", "-u", owner, shared, "sh", "-c", ". /etc/jobseeker-features.env && node --version && npm --version")
        check("its features are installed and on the editor's PATH", node.returncode == 0 and node.stdout.startswith("v20."), node.stdout + node.stderr)
        check("a feature's recommended extensions are installed", "dbaeumer.vscode-eslint" in inside.stdout, inside.stdout)
        (folder / "jobs" / "orders" / "main.py").write_text((folder / "jobs" / "orders" / "main.py").read_text() + "\n# edited\n")
        _, reopened = post_json(browser, "/jobCreation/projectWorkspaceOpen", {"project_id": project_id, "environment": "DEV"})
        check("editing code never rebuilds the dev container", reopened["runtime"]["status"] in ("ready", "starting")
              and not reopened["runtime"].get("recreated"), json.dumps(reopened["runtime"]))
        _, rebuilt = post_json(browser, "/jobCreation/projectWorkspaceRuntimeRebuild", {"project_id": project_id})
        check("the launcher rebuilds a runtime on request", rebuilt["ok"] and rebuilt["started"], json.dumps(rebuilt))
        _, during = post_json(browser, "/jobCreation/projectWorkspaceOpen", {"project_id": project_id, "environment": "DEV"})
        check("the editor still opens while its runtime rebuilds", during["runtime"]["status"] in ("ready", "starting")
              and "openVsCodeUrl" in during, json.dumps(during["runtime"]))
        deadline = time.monotonic() + 1200
        while time.monotonic() < deadline and (get_json(browser, f"/jobCreation/projectWorkspaceRuntime?project_id={project_id}").get("build") or {}).get("rebuilding"):
            time.sleep(5)
        _, after = post_json(browser, "/jobCreation/projectWorkspaceOpen", {"project_id": project_id, "environment": "DEV"})
        check("the next open moves the editor to the rebuilt image", after["runtime"].get("recreated") is True, json.dumps(after["runtime"]))
        follow(browser, project_id)

        # --- Stopping, removing and deleting ------------------------------------
        _, stopped = post_json(browser, "/jobCreation/projectWorkspaceRuntimeStop", {"project_id": project_id})
        check("the launcher stops the editor", stopped["ok"] and json.loads(engine("inspect", shared).stdout)[0]["State"]["Running"] is False)
        post_json(browser, "/jobCreation/projectWorkspaceOpen", {"project_id": project_id, "environment": "DEV"})
        follow(browser, project_id)
        check("the next open starts it again", json.loads(engine("inspect", shared).stdout)[0]["State"]["Running"] is True)
        deployments = get_json(browser, "/workspace-runtimes/catalog")["deployments"]
        mine = [row for row in deployments if row["projectId"] == project_id]
        check("the Runtimes page lists both deployments", sorted(row["container"] for row in mine) == sorted([container, shared]), json.dumps(mine))
        personal = next(row for row in mine if not row["shared"])
        _, removed = post_json(browser, "/workspace-runtimes/deployment", {"id": personal["id"], "action": "remove", "with_home": "1"})
        check("a deployment is removed with its home", removed["ok"] and engine("inspect", container).returncode != 0
              and engine("volume", "inspect", f"jobseeker-ide-home-p{project_id}-u1").returncode != 0)
        check("project files survive removing an editor", (folder / "jobs" / "orders" / "main.py").is_file())
        templates_phase(browser, owner, cleanup)
        git_phase(browser, owner, cleanup)

        post_json(browser, "/jobCreation/projectWorkspaceRuntimeSave", {"project_id": project_id, "runtime": RUNTIME, "isolation": "user"})
        status, busy = post_json(browser, "/workspace-runtimes/delete", {"key": RUNTIME}, expected=(409,))
        check("a runtime in use cannot be deleted", status == 409 and "is used by 2 projects" in busy["message"], json.dumps(busy))

        # --- Reclaiming images nothing needs --------------------------------
        stale_editor = f"jobseeker-runtime/{RUNTIME}:{'a' * 12}-ide"
        stale_runtime = f"jobseeker-runtime/{RUNTIME}:{'b' * 12}"
        for stale in (stale_editor, stale_runtime):
            engine("tag", "alpine:3.20", stale)
        reclaimable = get_json(browser, "/workspace-runtimes/reclaim")
        offered = {candidate["reference"]: candidate["kind"] for candidate in reclaimable["candidates"]}
        check("unused editor and runtime images are offered", offered.get(stale_editor) == "editor" and offered.get(stale_runtime) == "runtime"
              and reclaimable["jobsComplete"], json.dumps(reclaimable)[:1500])
        check("images of saved jobs, containers and current runtimes are kept", image not in offered and log["ideImage"] not in offered
              and not any(reference.startswith(f"jobseeker-runtime/p{project_id}:") for reference in offered), json.dumps(sorted(offered)))
        developer = sign_in(DEVELOPER_EMAIL, DEVELOPER_PASSWORD)
        check("only administrators reclaim images", developer.request("/workspace-runtimes/reclaim", expected=(403,))[0] == 403)
        status, body = browser.request("/workspace-runtimes/reclaim", method="POST", csrf=True,
                                       fields=[("references[]", stale_editor), ("references[]", stale_runtime), ("references[]", image)])
        reclaimed = json.loads(body)
        check("the chosen unused images are removed, and nothing else", sorted(reclaimed["removed"]) == sorted([stale_editor, stale_runtime])
              and engine("image", "inspect", image).returncode == 0 and engine("image", "inspect", stale_editor).returncode != 0, json.dumps(reclaimed))
    finally:
        for git_project in cleanup["projects"]:
            browser.request("/Context/deleteProject", method="POST", csrf=True, fields={"userId": str(git_project), "force": "1"})
        for added in cleanup["runtimes"]:
            browser.request("/workspace-runtimes/delete", method="POST", csrf=True, fields={"key": added}, expected=(200, 404, 409))
            tags = engine("images", f"jobseeker-runtime/{added}", "--format", "{{.Repository}}:{{.Tag}}").stdout.split()
            if tags:
                engine("rmi", *tags)
        for account_browser, account_id in cleanup["accounts"]:
            account_browser.request("/gitAccountDelete", method="POST", csrf=True, fields={"id": account_id})
        for path in cleanup["folders"]:
            shutil.rmtree(path, ignore_errors=True)
        for name in cleanup["containers"]:
            run("docker", "rm", "-f", name)
            run("docker", "volume", "rm", name)
        if project_id:
            sql(f"DELETE FROM contextdetails WHERE ProjectDetailsFK = {project_id} AND ContextKey = 'e2e_min_rows'")
        shutil.rmtree(ROOT / "repository" / "notebook-runs" / f"e2e-rt-notebook-{RUN}", ignore_errors=True)
        for job in jobs:
            browser.request("/delete-job/jobs", method="POST", csrf=True, fields={"jobs": job, "environment": "DEV"}, expected=(200, 302, 303, 404, 500))
        if project_id:
            browser.request("/Context/deleteProject", method="POST", csrf=True, fields={"userId": str(project_id), "force": "1"})
        browser.request("/workspace-runtimes/delete", method="POST", csrf=True, fields={"key": RUNTIME}, expected=(200, 404, 409))
        if folder is not None:
            shutil.rmtree(folder, ignore_errors=True)
        for repository in (f"jobseeker-runtime/{RUNTIME}", f"jobseeker-runtime/p{project_id}"):
            tags = engine("images", repository, "--format", "{{.Repository}}:{{.Tag}}").stdout.split()
            if tags:
                engine("rmi", *tags)

    containers = engine("ps", "-a", "--filter", f"label=com.jobseeker.project={project_id}", "--format", "{{.Names}}").stdout.split()
    check("deleting the project removes its editors", containers == [], " ".join(containers))
    print(f"\nWorkspace runtime e2e: {passed} checks passed.")


if __name__ == "__main__":
    main()
