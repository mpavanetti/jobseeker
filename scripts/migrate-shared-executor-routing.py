#!/usr/bin/env python3
"""Move existing JobSeeker jobs from an agent-only label to agent + shared.

Only jobs whose ENVIRONMENT default matches --environment and whose assignedNode
is exactly that environment's configured agent label are changed. The command
prints a dry run unless --apply is given. Applying saves each original Jenkins
config.xml to a timestamped directory before changing it.
"""

import argparse
import base64
import http.cookiejar
import json
import os
from pathlib import Path
import re
import tempfile
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from xml.sax.saxutils import escape


ROOT = Path(__file__).resolve().parents[1]


def request(opener, url, auth, method="GET", body=None, crumb=None):
    headers = {"Authorization": "Basic " + base64.b64encode(auth.encode()).decode()}
    if crumb:
        headers.update(crumb)
    if body is not None:
        headers["Content-Type"] = "application/xml"
    req = urllib.request.Request(url, data=body, headers=headers, method=method)
    try:
        with opener.open(req, timeout=25) as response:
            return response.status, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.read()


def get_json(opener, url, auth):
    status, body = request(opener, url, auth)
    if status != 200:
        raise RuntimeError("Jenkins GET returned HTTP %s: %s" % (status, url))
    return json.loads(body)


def all_names(jobs):
    for job in jobs:
        if job.get("fullName") and not job.get("jobs"):
            yield job["fullName"]
        yield from all_names(job.get("jobs", []))


def job_path(name):
    return "/".join("job/" + urllib.parse.quote(part, safe="") for part in name.split("/"))


def candidate(xml, environment, old_label):
    root = ET.fromstring(xml)
    defaults = [definition.findtext("defaultValue", "") for definition in
                root.findall(".//hudson.model.StringParameterDefinition")
                if definition.findtext("name", "") == "ENVIRONMENT"]
    if not defaults or defaults[0].upper() != environment:
        return False
    return root.findtext("assignedNode", "").strip() == old_label


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--environment", default="DEV")
    parser.add_argument("--agent-label")
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    environment = args.environment.upper()
    config = json.loads((ROOT / "application/config/config.json").read_text())
    labels = config.get("jenkins", {}).get("environment_agent_labels", {})
    old_label = args.agent_label or labels.get(environment, "")
    if not re.fullmatch(r"[A-Za-z0-9_-]+", old_label):
        raise RuntimeError("A simple agent label is required for %s" % environment)
    new_label = old_label + " || built-in"

    base = os.environ.get("JOBSEEKER_JENKINS_URL", "http://127.0.0.1:8080").rstrip("/")
    auth = "%s:%s" % (
        os.environ.get("JENKINS_ADMIN_ID", "jobseeker"),
        os.environ.get("JENKINS_ADMIN_PASSWORD", "jobseeker"),
    )
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    tree = "jobs[fullName,jobs[fullName,jobs[fullName,jobs[fullName]]]]"
    listing = get_json(opener, base + "/api/json?tree=" + tree, auth)
    candidates = []
    for name in sorted(set(all_names(listing.get("jobs", [])))):
        url = base + "/" + job_path(name) + "/config.xml"
        status, body = request(opener, url, auth)
        if status == 200 and candidate(body, environment, old_label):
            candidates.append((name, url, body))

    print("%s: %d agent-only job(s) eligible for %s" % (environment, len(candidates), new_label))
    for name, _, _ in candidates:
        print("  " + name)
    if not args.apply or not candidates:
        return

    crumb_data = get_json(opener, base + "/crumbIssuer/api/json", auth)
    crumb = {crumb_data["crumbRequestField"]: crumb_data["crumb"]}
    backup_dir = Path(tempfile.mkdtemp(prefix="jobseeker-routing-backup-"))
    print("Original configurations: " + str(backup_dir))

    changed = 0
    for name, url, original in candidates:
        old_xml = "<assignedNode>%s</assignedNode>" % escape(old_label)
        new_xml = "<assignedNode>%s</assignedNode>" % escape(new_label)
        old_bytes = old_xml.encode()
        if original.count(old_bytes) != 1:
            raise RuntimeError("Expected exactly one assignment in %s; no further jobs changed" % name)
        backup = backup_dir / (urllib.parse.quote(name, safe="") + ".xml")
        backup.write_bytes(original)
        updated = original.replace(old_bytes, new_xml.encode(), 1)
        status, _ = request(opener, url, auth, "POST", updated, crumb)
        if status not in (200, 201, 302):
            raise RuntimeError("Could not update %s: HTTP %s; backup at %s" % (name, status, backup))
        verified_status, verified = request(opener, url, auth)
        if verified_status != 200 or not candidate(verified, environment, new_label):
            raise RuntimeError("Could not verify %s; backup at %s" % (name, backup))
        changed += 1

    print("Updated and verified %d job(s)." % changed)


if __name__ == "__main__":
    main()
