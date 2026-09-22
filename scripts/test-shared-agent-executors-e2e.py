#!/usr/bin/env python3
"""Verify that DEV builds use both shared and dedicated Jenkins executors.

The test creates one disposable Jenkins job with the old agent-only assignment,
triggers it through JobSeeker so routing is upgraded, and checks live executor
placement and the scoped monitor. The job and its builds are removed afterward.
"""

import base64
import http.cookiejar
import json
import os
import re
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from xml.sax.saxutils import escape


APP = os.environ.get("JOBSEEKER_E2E_URL", "http://127.0.0.1").rstrip("/")
JENKINS = os.environ.get("JOBSEEKER_JENKINS_URL", "http://127.0.0.1:8080").rstrip("/")
EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")
JENKINS_USER = os.environ.get("JENKINS_ADMIN_ID", "jobseeker")
JENKINS_PASSWORD = os.environ.get("JENKINS_ADMIN_PASSWORD", "jobseeker")
JOB = "e2e-shared-agent-" + uuid.uuid4().hex[:10]
AGENT_LABEL = "jobseeker-env-dev"
ROUTING_LABEL = AGENT_LABEL + " || built-in"
RUN_SECONDS = int(os.environ.get("JOBSEEKER_EXECUTOR_E2E_RUN_SECONDS", "20"))
WAIT_SECONDS = int(os.environ.get("JOBSEEKER_EXECUTOR_E2E_WAIT_SECONDS", "120"))


class HttpClient:
    def __init__(self, root, auth=None, cookies=False):
        self.root = root
        self.auth = auth
        self.jar = http.cookiejar.CookieJar() if cookies else None
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar)
        ) if cookies else urllib.request.build_opener()
        self.crumb = {}

    def request(self, path, method="GET", body=None, content_type=None, csrf=False):
        headers = {}
        if self.auth:
            headers["Authorization"] = "Basic " + base64.b64encode(
                (self.auth[0] + ":" + self.auth[1]).encode()
            ).decode()
        if csrf:
            headers.update(self.crumb)
        if content_type:
            headers["Content-Type"] = content_type
        req = urllib.request.Request(
            self.root + "/" + path.lstrip("/"),
            data=body,
            headers=headers,
            method=method,
        )
        try:
            with self.opener.open(req, timeout=25) as response:
                return response.status, response.read()
        except urllib.error.HTTPError as error:
            return error.code, error.read()

    def json(self, path):
        status, body = self.request(path)
        if status != 200:
            raise AssertionError("GET %s returned HTTP %s" % (path, status))
        return json.loads(body)


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def app_csrf(jar):
    for cookie in jar:
        if cookie.name == "csrf_cookie_name":
            return cookie.value
    raise AssertionError("JobSeeker did not issue a CSRF cookie")


def login(app):
    status, page = app.request("login")
    require(status == 200, "JobSeeker login page is unavailable")
    match = re.search(rb'name="([^"]*csrf[^"]*)" value="([^"]+)"', page)
    require(match, "JobSeeker login form has no CSRF token")
    fields = {
        match.group(1).decode(): match.group(2).decode(),
        "email": EMAIL,
        "password": PASSWORD,
    }
    status, page = app.request(
        "loginMe", "POST", urllib.parse.urlencode(fields).encode(),
        "application/x-www-form-urlencoded",
    )
    require(status == 200 and b"Email or password mismatch" not in page,
            "JobSeeker administrator login failed")
    require(app.json("jenkins/executorMonitor?environment=DEV").get("ok") is True,
            "JobSeeker executor monitor is unavailable after login")


def jenkins_computers(jenkins):
    return jenkins.json(
        "computer/api/json?tree=computer[displayName,offline,numExecutors,assignedLabels[name],"
        "executors[idle,currentExecutable[fullDisplayName,url]]]"
    ).get("computer", [])


def node_roles(nodes):
    shared = None
    agent = None
    for node in nodes:
        labels = {label.get("name") for label in node.get("assignedLabels", [])}
        if "built-in" in labels:
            shared = node
        if AGENT_LABEL in labels:
            agent = node
    require(shared and agent, "The shared node and DEV agent must both exist")
    require(not shared.get("offline") and not agent.get("offline"),
            "The shared node and DEV agent must both be online")
    return shared, agent


def job_config():
    return ("""<?xml version="1.1" encoding="UTF-8"?>
<project>
  <description>Disposable shared and agent executor test.</description>
  <properties><hudson.model.ParametersDefinitionProperty><parameterDefinitions>
    <hudson.model.StringParameterDefinition><name>ENVIRONMENT</name><defaultValue>DEV</defaultValue><trim>true</trim></hudson.model.StringParameterDefinition>
  </parameterDefinitions></hudson.model.ParametersDefinitionProperty></properties>
  <scm class="hudson.scm.NullSCM"/>
  <assignedNode>%s</assignedNode><canRoam>false</canRoam>
  <disabled>false</disabled><concurrentBuild>true</concurrentBuild>
  <builders><hudson.tasks.Shell><command>echo executor-e2e-start; sleep %d; echo executor-e2e-end</command></hudson.tasks.Shell></builders>
  <publishers/><buildWrappers/>
</project>
""" % (escape(AGENT_LABEL), RUN_SECONDS)).encode()


def create_job(jenkins):
    path = "createItem?" + urllib.parse.urlencode({"name": JOB})
    status, body = jenkins.request(path, "POST", job_config(), "application/xml", csrf=True)
    require(status in (200, 201, 302), "Could not create the disposable Jenkins job: HTTP %s %s" % (status, body[:160]))


def trigger_through_app(app):
    path = "jenkins/proxy?" + urllib.parse.urlencode({
        "path": "job/%s/buildWithParameters" % JOB,
        "csrf_test_name": app_csrf(app.jar),
    })
    status, body = app.request(
        path, "POST", urllib.parse.urlencode({"ENVIRONMENT": "DEV", "delay": "0sec"}).encode(),
        "application/x-www-form-urlencoded",
    )
    require(status in (200, 201), "JobSeeker build trigger failed: HTTP %s %s" % (status, body[:160]))


def monitor(app):
    data = app.json("jenkins/executorMonitor?environment=DEV")
    require(data.get("ok") is True, "Scoped executor monitor returned an error")
    return data


def running_test_builds(node):
    return [
        executor["currentExecutable"].get("fullDisplayName", "")
        for executor in node.get("executors", [])
        if isinstance(executor.get("currentExecutable"), dict)
        and executor["currentExecutable"].get("fullDisplayName", "").startswith(JOB + " #")
    ]


def cleanup(jenkins):
    path = "job/%s/api/json?tree=builds[number,building]" % JOB
    try:
        for build in jenkins.json(path).get("builds", []):
            if build.get("building"):
                jenkins.request("job/%s/%s/stop" % (JOB, build["number"]), "POST", b"", csrf=True)
    except Exception:
        pass
    try:
        jenkins.request("job/%s/doDelete" % JOB, "POST", b"", csrf=True)
    except Exception:
        pass


def main():
    app = HttpClient(APP, cookies=True)
    jenkins = HttpClient(JENKINS, auth=(JENKINS_USER, JENKINS_PASSWORD), cookies=True)
    login(app)
    crumb = jenkins.json("crumbIssuer/api/json")
    jenkins.crumb = {crumb["crumbRequestField"]: crumb["crumb"]}

    shared, agent = node_roles(jenkins_computers(jenkins))
    expected = int(shared["numExecutors"]) + int(agent["numExecutors"])
    require(expected >= 2, "No usable shared and agent executors were found")
    baseline = monitor(app)
    require(baseline["global"]["totalExecutors"] == expected,
            "DEV monitor does not add shared and dedicated executor capacity")
    require(len(baseline.get("executors", [])) == expected,
            "DEV monitor does not list every shared and dedicated executor")

    expression = urllib.parse.quote(ROUTING_LABEL, safe="")
    label = jenkins.json("label/%s/api/json?tree=totalExecutors,nodes[nodeName]" % expression)
    require(label.get("totalExecutors") == expected,
            "Jenkins does not resolve the combined routing label to both nodes")
    require(len(label.get("nodes", [])) == 2,
            "The routing label includes an unexpected worker node")

    created = False
    try:
        create_job(jenkins)
        created = True
        for _ in range(expected):
            trigger_through_app(app)

        status, config = jenkins.request("job/%s/config.xml" % JOB)
        require(status == 200 and ("<assignedNode>%s</assignedNode>" % escape(ROUTING_LABEL)).encode() in config,
                "JobSeeker did not upgrade the job to shared and agent routing")

        deadline = time.monotonic() + WAIT_SECONDS
        observed = None
        while time.monotonic() < deadline:
            nodes = jenkins_computers(jenkins)
            shared, agent = node_roles(nodes)
            require(all(not running_test_builds(node) for node in nodes
                        if node is not shared and node is not agent),
                    "A DEV test build ran on a different environment's agent")
            on_shared = running_test_builds(shared)
            on_agent = running_test_builds(agent)
            if on_shared and on_agent:
                observed = monitor(app)
                if observed["global"]["totalExecutors"] == expected:
                    break
            time.sleep(2)
        require(observed is not None, "Concurrent test builds never ran on both shared and DEV agent nodes")
        require(observed["global"]["totalExecutors"] == expected,
                "Scoped monitor changed its total while builds were running")
        require(observed["global"]["busyExecutors"] >= len(on_shared) + len(on_agent),
                "Scoped monitor undercounted live builds")
        require(observed["environments"]["DEV"]["onlineAgentExecutors"] == int(agent["numExecutors"]),
                "Scoped environment row lost the DEV agent capacity")

        completed = None
        deadline = time.monotonic() + WAIT_SECONDS
        while time.monotonic() < deadline:
            builds = jenkins.json("job/%s/api/json?tree=builds[number,building,result,builtOn]" % JOB).get("builds", [])
            if len(builds) >= expected and all(not build.get("building") for build in builds[:expected]):
                completed = builds[:expected]
                break
            time.sleep(2)
        require(completed is not None, "The concurrent test builds did not finish")
        require(all(build.get("result") == "SUCCESS" for build in completed),
                "A shared or agent test build failed: %s" % completed)
        print("PASS: DEV has %d shared + %d agent executors; %d test builds succeeded across both; monitor reported %d busy / %d total." % (
            shared["numExecutors"], agent["numExecutors"],
            len(completed),
            observed["global"]["busyExecutors"], expected,
        ))
    finally:
        if created:
            cleanup(jenkins)


if __name__ == "__main__":
    main()
