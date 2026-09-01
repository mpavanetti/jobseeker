#!/usr/bin/env python3
import html
import http.cookiejar
import json
import os
import shutil
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

BASE_URL = os.environ.get("JOBSEEKER_E2E_URL", "http://localhost").rstrip("/")
ADMIN_EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
ADMIN_PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")
REPOSITORY_ROOT = Path(__file__).resolve().parents[1]


class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def csrf_token(self):
        for cookie in self.cookies:
            if cookie.name == "csrf_cookie_name":
                return cookie.value
        raise AssertionError("CSRF cookie is missing")

    def request(self, path, method="GET", fields=None, body=None, content_type=None, csrf=False, expected=(200,)):
        if fields is not None and body is not None:
            raise ValueError("Use fields or body, not both")
        values = dict(fields or {})
        if csrf and fields is not None:
            values["csrf_test_name"] = self.csrf_token()
        if csrf and body is not None:
            separator = "&" if "?" in path else "?"
            path += separator + urllib.parse.urlencode({"csrf_test_name": self.csrf_token()})
        data = urllib.parse.urlencode(values).encode("utf-8") if fields is not None else body
        headers = {"Content-Type": content_type} if content_type else {}
        request = urllib.request.Request(BASE_URL + path, data=data, headers=headers, method=method)
        try:
            with self.opener.open(request, timeout=20) as response:
                status = response.status
                response_body = response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as error:
            status = error.code
            response_body = error.read().decode("utf-8", "replace")
        if status not in expected:
            raise AssertionError("%s %s returned %s: %s" % (method, path, status, response_body[:500]))
        return status, response_body


def jenkins_proxy_path(path):
    return "/jenkins/proxy?" + urllib.parse.urlencode({"path": path})


def job_config(job_name, environment):
    source_path = "/php/repository/python/inline/" + job_name
    command = "\n".join([
        "set -e",
        "export JOBSEEKER_ENVIRONMENT=" + environment,
        "export JOBSEEKER_SOURCE_DIR='" + source_path + "'",
        "python3 '" + source_path + "/main.py' '" + environment + "'",
        # Legacy docker inline-Python shape: environment baked as a positional arg
        # that is not at the end of the line.
        "  sh -lc 'cd /tmp && python -u \"$JOBSEEKER_ENTRYPOINT\" \"$@\"' sh '" + environment + "' || JOBSEEKER_DOCKER_STATUS=$?",
    ])
    return """<?xml version="1.1" encoding="UTF-8"?>
<project>
  <actions/>
  <description>Disposable pipeline deployment E2E fixture.</description>
  <keepDependencies>false</keepDependencies>
  <properties>
    <hudson.model.ParametersDefinitionProperty>
      <parameterDefinitions>
        <hudson.model.StringParameterDefinition>
          <name>ENVIRONMENT</name>
          <defaultValue>%s</defaultValue>
          <trim>true</trim>
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
  <builders><hudson.tasks.Shell><command>%s</command></hudson.tasks.Shell></builders>
  <publishers/>
  <buildWrappers/>
</project>
""" % (html.escape(environment), html.escape(command))


def json_response(body):
    try:
        return json.loads(body)
    except json.JSONDecodeError as error:
        raise AssertionError("Expected JSON response: %s" % body[:500]) from error


def main():
    run_id = uuid.uuid4().hex[:10]
    source_jobs = ["e2e-pipeline-%s-extract-DEV" % run_id, "e2e-pipeline-%s-load-DEV" % run_id]
    target_jobs = ["e2e-pipeline-%s-extract-QA" % run_id, "e2e-pipeline-%s-load-QA" % run_id]
    pipeline_key = "e2e-pipeline-%s" % run_id
    source_artifacts = [REPOSITORY_ROOT / "repository" / "python" / "inline" / job for job in source_jobs]
    target_artifacts = [REPOSITORY_ROOT / "repository" / "python" / "inline" / job for job in target_jobs]
    browser = Browser()
    source_pipeline_id = None
    target_pipeline_id = None

    browser.request("/")
    _, login_body = browser.request(
        "/loginMe",
        method="POST",
        fields={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD},
        csrf=True,
    )
    if "logout" not in login_body.lower():
        raise AssertionError("Administrator login failed")

    try:
        for index, (source_job, source_artifact) in enumerate(zip(source_jobs, source_artifacts)):
            source_artifact.mkdir(parents=True)
            (source_artifact / "main.py").write_text("print('pipeline deployment fixture')\n", encoding="utf-8")
            (source_artifact / "payload.txt").write_text("%s-%d\n" % (run_id, index), encoding="utf-8")
            # Inline Python jobs opened in the IDE carry a .venv / .uv-cache full of
            # symlinks. Deployment must skip these transient trees, not abort on them.
            venv_bin = source_artifact / ".venv" / "bin"
            venv_bin.mkdir(parents=True)
            (source_artifact / ".venv" / "lib64").symlink_to("lib")
            (venv_bin / "python").symlink_to("/usr/bin/python3")
            (source_artifact / "__pycache__").mkdir()
            (source_artifact / "__pycache__" / "main.cpython-311.pyc").write_text("stale\n", encoding="utf-8")
            browser.request(
                jenkins_proxy_path("createItem?name=" + urllib.parse.quote(source_job)),
                method="POST",
                body=job_config(source_job, "DEV").encode("utf-8"),
                content_type="application/xml",
                csrf=True,
            )

        graph_nodes = [
            {"id": "extract", "job": source_jobs[0], "label": source_jobs[0], "x": 80, "y": 80},
            {"id": "load", "job": source_jobs[1], "label": source_jobs[1], "x": 320, "y": 80},
        ]
        graph_edges = [{"source": "extract", "target": "load", "condition": "SUCCESS"}]
        _, save_body = browser.request(
            "/pipelines/save?environment=DEV",
            method="POST",
            fields={
                "id": "0",
                "name": "Pipeline deployment E2E " + run_id,
                "pipeline_key": pipeline_key,
                "group_name": "E2E",
                "description": "Disposable pipeline deployment fixture",
                "schedule_enabled": "0",
                "schedule_cron": "",
                "is_active": "1",
                "nodes_json": json.dumps(graph_nodes),
                "edges_json": json.dumps(graph_edges),
            },
            csrf=True,
        )
        saved = json_response(save_body)
        assert saved.get("ok") is True, saved
        source_pipeline_id = int(saved["id"])

        _, deploy_body = browser.request(
            "/pipelines/deploy?environment=DEV",
            method="POST",
            fields={"id": str(source_pipeline_id), "target_environment": "QA", "overwrite": "0"},
            csrf=True,
        )
        deployed = json_response(deploy_body)
        assert deployed.get("ok") is True, deployed
        assert deployed.get("deployedJobs") == 2, deployed
        assert deployed.get("artifactFolders") == 2, deployed
        assert [(item["sourceJob"], item["targetJob"]) for item in deployed.get("mappings", [])] == list(zip(source_jobs, target_jobs)), deployed
        target_pipeline_id = int(deployed["id"])

        for index, (source_job, target_job, target_artifact) in enumerate(zip(source_jobs, target_jobs, target_artifacts)):
            _, target_xml = browser.request(jenkins_proxy_path("job/%s/config.xml" % urllib.parse.quote(target_job)))
            assert "<defaultValue>QA</defaultValue>" in target_xml
            assert "python/inline/%s" % target_job in target_xml
            assert "python/inline/%s" % source_job not in target_xml
            unescaped = html.unescape(target_xml)
            assert "'QA'" in unescaped
            assert "main.py' 'DEV'" not in unescaped, "Trailing positional environment arg was not rewritten"
            assert "\"$@\"' sh 'QA'" in unescaped, "Legacy docker positional environment arg was not rewritten"
            assert "\"$@\"' sh 'DEV'" not in unescaped, "Legacy docker positional environment arg still points at the source environment"
            assert (target_artifact / "payload.txt").read_text(encoding="utf-8") == "%s-%d\n" % (run_id, index)
            assert not (target_artifact / ".venv").exists(), "Transient .venv must not be deployed"
            assert not (target_artifact / "__pycache__").exists(), "Transient __pycache__ must not be deployed"
            assert not any(p.is_symlink() for p in target_artifact.rglob("*")), "Deployed payload must not contain symlinks"

        print("Pipeline deployment E2E test passed.")
    finally:
        if target_pipeline_id is not None:
            browser.request(
                "/pipelines/delete?environment=QA",
                method="POST",
                fields={"id": str(target_pipeline_id)},
                csrf=True,
                expected=(200, 404),
            )
        if source_pipeline_id is not None:
            browser.request(
                "/pipelines/delete?environment=DEV",
                method="POST",
                fields={"id": str(source_pipeline_id)},
                csrf=True,
                expected=(200, 404),
            )
        for job_name, environment in [(job, "DEV") for job in source_jobs] + [(job, "QA") for job in target_jobs]:
            browser.request(
                "/DeleteJob/deleteJobs?environment=" + environment,
                method="POST",
                fields={"jobs": job_name, "delete_repositories": "0"},
                csrf=True,
                expected=(200,),
            )
        for artifact in source_artifacts + target_artifacts:
            shutil.rmtree(artifact, ignore_errors=True)


if __name__ == "__main__":
    main()
