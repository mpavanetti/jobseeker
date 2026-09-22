#!/usr/bin/env python3
"""Run every curated job sample through Jenkins in local and Docker modes.

The matrix uses disposable Jenkins jobs and inline-Python workspaces. It tests
the real command generator without leaving jobs, workspaces, or dependency-map
rows behind.
"""

from __future__ import annotations

import http.cookiejar
import html
import json
import os
import re
import shutil
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
import xml.etree.ElementTree as ET
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
BASE_URL = os.environ.get("JOBSEEKER_E2E_URL", "http://localhost").rstrip("/")
ADMIN_EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
ADMIN_PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")

CONSOLE_ANALYZER = r"""
const fs = require('fs');
const groups = require('./assets/js/job-console-groups');
const parsed = groups.parse(fs.readFileSync(0, 'utf8'));
process.stdout.write(JSON.stringify(parsed.sections.map(section => ({
  kind: section.kind,
  title: section.title,
  hasError: section.hasError
}))));
"""

PHP_RENDERER = r'''
define('BASEPATH', '/workspace/system/');
require '/workspace/application/controllers/concerns/JobCreationExecutionTrait.php';

class SampleMatrixCommandRenderer
{
    use JobCreationExecutionTrait;

    private function defaultPythonDockerImage()
    {
        return 'python:3.13-slim';
    }

    public function shellCommand($command, $runtime, $image)
    {
        return $this->buildLinuxCommandExecutionCommand($command, array(
            'mode' => $runtime,
            'dockerImage' => $image,
            'cpuLimit' => '1',
            'memoryLimitMb' => 512
        ), '/php/repository');
    }

    public function pythonCommand($sample, $runtime, $jobName)
    {
        $image = isset($sample['docker_image']) ? $sample['docker_image'] : 'python:3.13-slim';
        $projectName = preg_replace('/[^a-z0-9-]+/', '-', strtolower($jobName));
        $pyproject = implode("\n", array(
            '[project]',
            'name = "'.$projectName.'"',
            'version = "0.1.0"',
            'requires-python = ">=3.13,<4.0"',
            'dependencies = []',
            '',
            '[tool.poetry]',
            'package-mode = false',
            '',
            '[tool.poetry.group.dev.dependencies]',
            'pytest = ">=8.0,<10.0"',
            ''
        ));
        $dockerfile = '';
        if (! empty($sample['use_dockerfile'])) {
            $dockerfile = implode("\n", array(
                'FROM '.$image,
                'ARG POETRY_VERSION=2.4.1',
                'WORKDIR /app',
                'ENV POETRY_VIRTUALENVS_CREATE=false PIP_DISABLE_PIP_VERSION_CHECK=1 JOBSEEKER_DEPENDENCIES_PREINSTALLED=1',
                'RUN python -m pip install --no-cache-dir "poetry==$POETRY_VERSION" && groupadd --system jobseeker && useradd --system --gid jobseeker --create-home jobseeker',
                'COPY . .',
                'RUN poetry install --no-root --no-interaction --no-ansi && chown -R jobseeker:jobseeker /app',
                'USER jobseeker',
                ''
            ));
        }
        $entryPoint = isset($sample['entry_point']) ? $sample['entry_point'] : 'main.py';
        $sourceDirectory = '/php/repository/python/inline/'.$jobName;
        return $this->buildPythonExecutionCommand(array(
            'mode' => 'inline',
            'sourceDirectory' => $sourceDirectory,
            'scriptPath' => $sourceDirectory.'/'.$entryPoint,
            'entryPoint' => $entryPoint
        ), '/php/repository', '"$JOBSEEKER_ENVIRONMENT"', array(
            'mode' => $runtime,
            'pythonExecutable' => 'python3',
            'dockerImage' => $image,
            'requirementsText' => $runtime === 'local' && isset($sample['requirements']) ? $sample['requirements'] : '',
            'pyprojectText' => $runtime === 'docker' ? $pyproject : '',
            'dockerfileText' => $runtime === 'docker' ? $dockerfile : '',
            'runTests' => ! empty($sample['run_tests']),
            'cpuLimit' => '1',
            'memoryLimitMb' => 512
        ));
    }
}

$samples = require '/workspace/application/config/job_samples.php';
$renderer = new SampleMatrixCommandRenderer();
$runId = getenv('JOBSEEKER_SAMPLE_MATRIX_ID');
$cases = array();
foreach ($samples as $sample) {
    foreach (array('local', 'docker') as $runtime) {
        $slug = substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($sample['id'])), 0, 30);
        $jobName = 'e2e-sm-'.$slug.'-'.($runtime === 'docker' ? 'd' : 'l').'-'.$runId;
        $image = isset($sample['docker_image']) ? $sample['docker_image'] : 'alpine:3.20';
        $command = $sample['family'] === 'shell'
            ? $renderer->shellCommand($sample['command'], $runtime, $image)
            : $renderer->pythonCommand($sample, $runtime, $jobName);
        $cases[] = array(
            'id' => $sample['id'],
            'family' => $sample['family'],
            'runtime' => $runtime,
            'job_name' => $jobName,
            'description' => $sample['job_description'],
            'entry_point' => isset($sample['entry_point']) ? $sample['entry_point'] : '',
            'code' => isset($sample['code']) ? $sample['code'] : '',
            'requirements' => isset($sample['requirements']) ? $sample['requirements'] : '',
            'use_dockerfile' => ! empty($sample['use_dockerfile']),
            'files' => isset($sample['files']) ? $sample['files'] : array(),
            'command' => $command
        );
    }
}
echo json_encode($cases, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
'''


class Browser:
    def __init__(self) -> None:
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def csrf(self) -> str:
        for cookie in self.cookies:
            if cookie.name == "csrf_cookie_name":
                return cookie.value
        raise RuntimeError("No CSRF cookie was issued.")

    def request(
        self,
        path: str,
        *,
        method: str = "GET",
        fields: dict[str, str] | list[tuple[str, str]] | None = None,
        body: bytes | None = None,
        content_type: str | None = None,
        csrf: bool = False,
        expected: tuple[int, ...] = (200,),
        timeout: int = 180,
    ) -> tuple[int, str]:
        data = body
        headers: dict[str, str] = {}
        if fields is not None:
            values = list(fields.items()) if isinstance(fields, dict) else list(fields)
            if csrf:
                values.append(("csrf_test_name", self.csrf()))
            data = urllib.parse.urlencode(values).encode()
        elif csrf:
            separator = "&" if "?" in path else "?"
            path += separator + urllib.parse.urlencode({"csrf_test_name": self.csrf()})
        if content_type:
            headers["Content-Type"] = content_type
        request = urllib.request.Request(BASE_URL + path, data=data, headers=headers, method=method)
        try:
            with self.opener.open(request, timeout=timeout) as response:
                status = response.status
                text = response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as error:
            status = error.code
            text = error.read().decode("utf-8", "replace")
        if status not in expected:
            raise RuntimeError(f"{method} {path} returned {status}: {text[:500]}")
        return status, text

    def multipart(
        self,
        path: str,
        fields: dict[str, str],
        file_field: str,
        file_name: str,
        file_content: bytes,
        content_type: str = "text/csv",
    ) -> tuple[int, str]:
        boundary = "----jobseeker-samples-" + uuid.uuid4().hex
        parts: list[str] = []
        values = list(fields.items()) + [("csrf_test_name", self.csrf())]
        for name, value in values:
            parts.extend([
                "--" + boundary,
                f'Content-Disposition: form-data; name="{name}"',
                "",
                str(value),
            ])
        prefix = ("\r\n".join(parts) + "\r\n").encode()
        file_header = (
            f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="{file_field}"; filename="{file_name}"\r\n'
            f"Content-Type: {content_type}\r\n\r\n"
        ).encode()
        suffix = f"\r\n--{boundary}--\r\n".encode()
        return self.request(
            path,
            method="POST",
            body=prefix + file_header + file_content + suffix,
            content_type="multipart/form-data; boundary=" + boundary,
        )


def assets_from_page(page: str) -> list[dict[str, Any]]:
    match = re.search(r'<script id="dataAssetsPayload" type="application/json">(.*?)</script>', page, re.S)
    if not match:
        raise RuntimeError("The Data Assets page did not publish its JSON payload.")
    return json.loads(html.unescape(match.group(1)))


def console_sections(console: str) -> list[dict[str, Any]]:
    completed = subprocess.run(
        ["node", "-e", CONSOLE_ANALYZER],
        cwd=ROOT,
        input=console,
        text=True,
        capture_output=True,
    )
    if completed.returncode != 0:
        raise RuntimeError("Console classifier failed: " + completed.stderr.strip())
    return json.loads(completed.stdout)


def console_contract_error(case: dict[str, Any], console: str) -> str:
    sections = console_sections(console)
    kinds = [section["kind"] for section in sections]
    red = [section["title"] for section in sections if section["hasError"]]
    if red:
        return "successful console contains red section(s): " + ", ".join(red)

    docker_kinds = {"docker-runtime", "docker-build", "docker-execution"}
    if case["runtime"] == "local":
        unexpected = sorted(docker_kinds.intersection(kinds))
        if unexpected:
            return "local runtime was grouped as Docker: " + ", ".join(unexpected)
        required = "shell" if case["family"] == "shell" else "python"
        if required not in kinds:
            return "local console is missing the %s execution section" % required
        if case["family"] == "python" and "python-environment" not in kinds:
            return "local Python console is missing its environment section"
        return ""

    for required in ("docker-runtime", "docker-execution"):
        if required not in kinds:
            return "Docker console is missing the %s section" % required
    expects_build = case["family"] == "python" and bool(case["use_dockerfile"])
    if expects_build != ("docker-build" in kinds):
        return (
            "custom Dockerfile build was not grouped as an image build"
            if expects_build
            else "prebuilt Docker image was incorrectly grouped as an image build"
        )
    return ""


SAMPLE_ASSETS: dict[str, list[tuple[str, str, bytes | None]]] = {
    "shell-data-asset": [("etl", "input", b"id,value\n1,ready\n")],
    "shell-connector-asset-bridge": [("etl", "input", b"id,value\n1,ready\n")],
    "python-asset-transform": [
        ("customer-reference", "input", b"Customer_ID,Name,customer_id,email\n1,Alice,1,alice@example.com\n"),
        ("customer-normalized", "output", None),
    ],
    "python-db-warehouse-etl": [("tmf-run-summary", "output", None)],
    "python-quality-workspace": [
        ("customer-reference", "input", b"Customer_ID,Name,customer_id,email\n1,Alice,1,alice@example.com\n"),
    ],
    "python-multi-stage-etl": [
        ("orders-inbound", "input", b"order_id,amount,source\norder-1,42.50,e2e\n"),
        ("orders-curated", "output", None),
    ],
    "python-platform-pipeline": [
        ("orders-inbound", "input", b"order_id,amount,source\norder-1,42.50,e2e\n"),
        ("orders-curated", "output", None),
    ],
}


def register_sample_asset(
    browser: Browser,
    job_name: str,
    key: str,
    direction: str,
    content: bytes | None,
) -> dict[str, Any]:
    fields = {
        "asset_id": "0",
        "name": f"Sample E2E {key}",
        "asset_key": key,
        "direction": direction,
        "environment": "DEV",
        "job_name": job_name,
        "format": "csv",
        "file_name": key + ".csv",
        "delimiter": ",",
        "encoding": "UTF-8",
        "has_header": "1",
        "sheet": "",
        "description": "Disposable sample runtime E2E fixture.",
        "is_required": "1" if direction == "input" else "0",
        "is_active": "1",
    }
    if content is None:
        _, page = browser.request(
            "/data-assets/save?environment=DEV", method="POST", fields=fields, csrf=True
        )
    else:
        _, page = browser.multipart(
            "/data-assets/save?environment=DEV", fields, "asset_file", key + ".csv", content
        )
    asset = next(
        (item for item in assets_from_page(page) if item["key"] == key and item["job"] == job_name),
        None,
    )
    if not asset:
        raise RuntimeError(f"Data Asset fixture {key!r} was not registered for {job_name!r}.")
    return asset


def render_cases(run_id: str) -> list[dict[str, Any]]:
    result = subprocess.run(
        [
            "docker",
            "run",
            "--rm",
            "-e",
            f"JOBSEEKER_SAMPLE_MATRIX_ID={run_id}",
            "-v",
            f"{ROOT}:/workspace:ro",
            "-w",
            "/workspace",
            "jobseeker-php",
            "php",
            "-r",
            PHP_RENDERER,
        ],
        check=True,
        capture_output=True,
        text=True,
    )
    cases = json.loads(result.stdout)
    if len(cases) != 28:
        raise RuntimeError(f"Expected 28 sample/runtime cases, received {len(cases)}.")
    return cases


def job_xml(case: dict[str, Any]) -> bytes:
    project = ET.Element("project")
    ET.SubElement(project, "description").text = f"Disposable sample-matrix fixture: {case['description']}"
    ET.SubElement(project, "keepDependencies").text = "false"
    properties = ET.SubElement(project, "properties")
    definitions = ET.SubElement(properties, "hudson.model.ParametersDefinitionProperty")
    parameters = ET.SubElement(definitions, "parameterDefinitions")
    environment = ET.SubElement(parameters, "hudson.model.StringParameterDefinition")
    ET.SubElement(environment, "name").text = "ENVIRONMENT"
    ET.SubElement(environment, "description").text = "Runtime environment managed by JobSeeker."
    ET.SubElement(environment, "defaultValue").text = "DEV"
    ET.SubElement(environment, "trim").text = "true"
    ET.SubElement(project, "scm", {"class": "hudson.scm.NullSCM"})
    ET.SubElement(project, "canRoam").text = "true"
    ET.SubElement(project, "disabled").text = "false"
    ET.SubElement(project, "blockBuildWhenDownstreamBuilding").text = "false"
    ET.SubElement(project, "blockBuildWhenUpstreamBuilding").text = "false"
    ET.SubElement(project, "triggers")
    ET.SubElement(project, "concurrentBuild").text = "false"
    builders = ET.SubElement(project, "builders")
    shell = ET.SubElement(builders, "hudson.tasks.Shell")
    ET.SubElement(shell, "command").text = case["command"]
    ET.SubElement(shell, "configuredLocalRules")
    ET.SubElement(project, "publishers")
    ET.SubElement(project, "buildWrappers")
    return ET.tostring(project, encoding="utf-8", xml_declaration=True)


def create_workspace(case: dict[str, Any]) -> Path | None:
    if case["family"] != "python":
        return None
    workspace = ROOT / "repository" / "python" / "inline" / case["job_name"]
    workspace.mkdir(parents=True, exist_ok=False)
    entry_path = workspace / case["entry_point"]
    entry_path.parent.mkdir(parents=True, exist_ok=True)
    entry_path.write_text(case["code"], encoding="utf-8")
    if case.get("requirements"):
        (workspace / "requirements.txt").write_text(case["requirements"], encoding="utf-8")
    for extra in case.get("files", []):
        relative = Path(extra["path"])
        if relative.is_absolute() or ".." in relative.parts:
            raise RuntimeError(f"Unsafe sample path: {relative}")
        destination = workspace / relative
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_text(extra["content"], encoding="utf-8")
    return workspace


def proxy_path(path: str) -> str:
    return "/jenkins/proxy?" + urllib.parse.urlencode({"path": path})


def wait_for_build(browser: Browser, job_name: str, timeout: int = 900) -> tuple[str, int, str]:
    deadline = time.monotonic() + timeout
    build_number = 0
    while time.monotonic() < deadline:
        _, body = browser.request(
            proxy_path(f"job/{urllib.parse.quote(job_name, safe='')}/api/json?tree=lastBuild[number,building,result]")
        )
        payload = json.loads(body)
        last_build = payload.get("lastBuild") or {}
        build_number = int(last_build.get("number") or 0)
        if build_number and not last_build.get("building") and last_build.get("result"):
            result = str(last_build["result"])
            _, console = browser.request(
                proxy_path(
                    f"job/{urllib.parse.quote(job_name, safe='')}/{build_number}/consoleText"
                )
            )
            return result, build_number, console
        time.sleep(1)
    return "TIMEOUT", build_number, "Build did not finish before the matrix timeout."


def main() -> None:
    run_id = uuid.uuid4().hex[:6]
    cases = render_cases(run_id)
    browser = Browser()
    created_jobs: list[str] = []
    created_assets: list[dict[str, Any]] = []
    output_assets: list[dict[str, Any]] = []
    workspaces: list[Path] = []
    failures: list[tuple[dict[str, Any], str, str]] = []

    browser.request("/")
    _, login = browser.request(
        "/loginMe",
        method="POST",
        fields={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD},
        csrf=True,
    )
    if "logout" not in login.lower():
        raise RuntimeError("Admin login failed.")

    try:
        for case in cases:
            for key, direction, content in SAMPLE_ASSETS.get(case["id"], []):
                asset = register_sample_asset(browser, case["job_name"], key, direction, content)
                created_assets.append(asset)
                if direction == "output":
                    output_assets.append(asset)

        total = len(cases)
        for index, case in enumerate(cases, start=1):
            label = f"{case['family']}/{case['id']} [{case['runtime']}]"
            print(f"[{index:02d}/{total}] {label}: preparing", flush=True)
            workspace = create_workspace(case)
            if workspace is not None:
                workspaces.append(workspace)

            browser.request(
                proxy_path("createItem?name=" + urllib.parse.quote(case["job_name"], safe="")),
                method="POST",
                body=job_xml(case),
                content_type="application/xml",
                csrf=True,
                expected=(200,),
            )
            created_jobs.append(case["job_name"])
            browser.request(
                proxy_path(
                    f"job/{urllib.parse.quote(case['job_name'], safe='')}/buildWithParameters?ENVIRONMENT=DEV"
                ),
                method="POST",
                csrf=True,
                expected=(200, 201, 302),
            )
            result, build_number, console = wait_for_build(browser, case["job_name"])
            print(f"[{index:02d}/{total}] {label}: {result} (build {build_number})", flush=True)
            if result != "SUCCESS":
                failures.append((case, result, console))
            elif (console_error := console_contract_error(case, console)):
                failures.append((case, "CONSOLE_GROUPING: " + console_error, console))
            elif case["id"] == "shell-data-asset" and "Resolved etl ->" not in console:
                failures.append((case, "DATA_ASSET_NOT_RESOLVED", console))
            elif case["id"] == "shell-connector-asset-bridge" and "Resolved Data Asset etl ->" not in console:
                failures.append((case, "DATA_ASSET_NOT_RESOLVED", console))
            elif case["id"] == "python-asset-transform" and "Published 1 rows to jobseeker://dev/" not in console:
                failures.append((case, "DATA_ASSET_NOT_PUBLISHED", console))
            elif case["id"] == "python-db-warehouse-etl" and "Published " not in console:
                failures.append((case, "DATA_ASSET_NOT_PUBLISHED", console))
            elif case["id"] == "python-platform-pipeline" and "Published 1 rows to jobseeker://dev/" not in console:
                failures.append((case, "DATA_ASSET_NOT_PUBLISHED", console))

        if failures:
            print("\nSample matrix failures:", flush=True)
            for case, result, console in failures:
                label = f"{case['family']}/{case['id']} [{case['runtime']}]"
                tail = "\n".join(console.splitlines()[-60:])
                print(f"\n--- {label}: {result} ---\n{tail}", flush=True)
            raise SystemExit(1)

        # Opening the catalog refreshes metadata for files written by jobs.
        _, catalog_body = browser.request("/data-assets/catalog?environment=DEV")
        catalog = json.loads(catalog_body)["assets"]
        catalog_by_scope = {(item["key"], item["job"]): item for item in catalog}
        missing_outputs = []
        for asset in output_assets:
            published = catalog_by_scope.get((asset["key"], asset["job"]))
            if not published or not published["exists"] or int(published["version"]) < 1 or not published["checksum"]:
                missing_outputs.append(f"{asset['job']}:{asset['key']}")
        if missing_outputs:
            raise RuntimeError("Sample jobs did not publish output Data Assets: " + ", ".join(missing_outputs))

        print(
            f"All {len(cases)} sample/runtime Jenkins builds and {len(created_assets)} scoped Data Assets passed.",
            flush=True,
        )
    finally:
        for job_name in reversed(created_jobs):
            try:
                browser.request(
                    "/DeleteJob/deleteJobs?environment=DEV",
                    method="POST",
                    fields={"jobs": job_name, "delete_repositories": "0"},
                    csrf=True,
                    expected=(200,),
                )
            except Exception as error:  # noqa: BLE001 - cleanup must continue
                print(f"Cleanup warning for Jenkins job {job_name}: {error}", flush=True)
        for workspace in reversed(workspaces):
            shutil.rmtree(workspace, ignore_errors=True)
        for asset in reversed(created_assets):
            try:
                browser.request(
                    "/data-assets/delete?environment=DEV",
                    method="POST",
                    fields={"asset_id": str(asset["id"]), "delete_file": "1"},
                    csrf=True,
                    expected=(200,),
                )
            except Exception as error:  # noqa: BLE001 - cleanup must continue
                print(f"Cleanup warning for Data Asset {asset['key']}: {error}", flush=True)
        # The API leaves empty key/scope directories by design. Remove only
        # empty directories owned by this run; never unlink unexpected files.
        for job_name in {case["job_name"] for case in cases}:
            asset_root = ROOT / "repository" / "data-assets" / "dev" / job_name
            if asset_root.is_dir():
                for directory in sorted(
                    (path for path in asset_root.rglob("*") if path.is_dir()),
                    key=lambda path: len(path.parts),
                    reverse=True,
                ):
                    try:
                        directory.rmdir()
                    except OSError:
                        pass
                try:
                    asset_root.rmdir()
                except OSError:
                    pass
        if created_jobs:
            placeholders = ",".join("'%s'" % name.replace("'", "''") for name in created_jobs)
            subprocess.run(
                [
                    "docker",
                    "compose",
                    "exec",
                    "-T",
                    "mariadb",
                    "sh",
                    "-lc",
                    f'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "'
                    f'DELETE FROM tmf_error WHERE job_name IN ({placeholders}); '
                    f'DELETE FROM tmf WHERE job_name IN ({placeholders}); '
                    f'DELETE FROM job_dependencies WHERE job_name IN ({placeholders});"',
                ],
                cwd=ROOT,
                capture_output=True,
                check=False,
            )


if __name__ == "__main__":
    main()
