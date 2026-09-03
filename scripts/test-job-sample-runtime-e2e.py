#!/usr/bin/env python3
"""Load every curated Python sample in a real browser and check the runtime select.

Loading a sample used to overwrite the Python runtime with the sample's own
``runtime`` value, so an operator who had picked **Docker Container** was pushed
back to **Jenkins Agent** as soon as they loaded a starter. A sample may pin the
runtime only when its content actually needs Docker; otherwise the operator's
selection must survive the load.

Requires the docker-compose stack to be up and reachable at JOBSEEKER_E2E_URL.
"""

from __future__ import annotations

import os
import sys

try:
    from playwright.sync_api import sync_playwright
except ImportError:  # pragma: no cover - environment guard
    sys.exit("playwright is required: pip install playwright==1.48.0")

BASE_URL = os.environ.get("JOBSEEKER_E2E_URL", "http://localhost").rstrip("/")
ADMIN_EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
ADMIN_PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")

# (starting runtime, sample id, expected runtime after load, description)
CASES = (
    ("docker", "python-sdk-progress", "docker", "Docker selection survives a runtime-neutral sample"),
    ("docker", "python-asset-transform", "docker", "Docker selection survives an asset sample"),
    ("docker", "python-connector-inventory", "docker", "Docker selection survives a connector sample"),
    ("docker", "python-db-connector-inspect", "docker", "Docker selection survives a database sample"),
    ("docker", "python-multi-stage-etl", "docker", "Docker selection survives a multi-file workspace"),
    ("docker", "python-platform-pipeline", "docker", "Docker selection survives the pipeline sample"),
    ("docker", "python-db-warehouse-etl", "docker", "A Docker-only sample keeps Docker"),
    ("docker", "python-quality-workspace", "docker", "A Docker-only sample keeps Docker"),
    ("local", "python-db-warehouse-etl", "docker", "A Docker-only sample still upgrades from the agent"),
    ("local", "python-quality-workspace", "docker", "A Docker-only sample still upgrades from the agent"),
    ("local", "python-sdk-progress", "local", "Agent selection survives a runtime-neutral sample"),
    ("local", "python-asset-transform", "local", "Agent selection survives an asset sample"),
    ("local", "python-multi-stage-etl", "local", "Agent selection survives a multi-file workspace"),
    ("local", "python-platform-pipeline", "local", "Agent selection survives the pipeline sample"),
)

FORM_STATE = (
    "({runtime: $('#pythonRuntimeMode').val(),"
    " dockerfile: $('#pythonUseDockerfile').is(':checked'),"
    " dockerfileText: ($.trim($('#pythonDockerfileText').val() || '') !== ''),"
    " code: ($.trim($('#pythonInlineCode').val() || '') !== '')})"
)


def sign_in(page):
    page.goto(BASE_URL + "/", wait_until="domcontentloaded")
    page.fill('input[name="email"]', ADMIN_EMAIL)
    page.fill('input[name="password"]', ADMIN_PASSWORD)
    page.click('button[type="submit"], input[type="submit"]')
    page.wait_for_load_state("domcontentloaded")
    if "/dashboard" not in page.url:
        raise SystemExit("Sign-in failed; landed on " + page.url)


def open_python_panel(page):
    page.goto(BASE_URL + "/jobCreation", wait_until="domcontentloaded")
    page.wait_for_selector('.job-execution-option[data-execution-family="python"]')
    page.click('.job-execution-option[data-execution-family="python"]')
    page.wait_for_selector("#pythonRuntimeMode", state="visible", timeout=20000)
    page.wait_for_timeout(1500)


def load_sample(page, sample_id):
    page.locator(".open-job-sample-library").first.click()
    page.wait_for_selector('.job-sample-card[data-job-sample-id="%s"]' % sample_id, timeout=15000)
    page.click('.job-sample-card[data-job-sample-id="%s"]' % sample_id)
    page.wait_for_selector("#loadSelectedJobSample:not([disabled])", timeout=10000)
    page.click("#loadSelectedJobSample")
    page.wait_for_timeout(1200)


def main():
    failures = []
    with sync_playwright() as runner:
        browser = runner.chromium.launch()
        page = browser.new_page()
        try:
            sign_in(page)
            # The panel stays open across cases so the server-side job draft
            # cache cannot replay the previous sample on a fresh page load.
            open_python_panel(page)
            for start, sample_id, expected, description in CASES:
                page.evaluate("$('#pythonInlineCode').val(''); $('#pythonDockerfileText').val('');")
                page.select_option("#pythonRuntimeMode", start)
                page.wait_for_timeout(400)
                if page.evaluate("$('#pythonRuntimeMode').val()") != start:
                    failures.append("%s: could not select the %s runtime" % (sample_id, start))
                    continue

                load_sample(page, sample_id)
                state = page.evaluate(FORM_STATE)

                if state["runtime"] != expected:
                    failures.append("%s + %s: runtime became %s, expected %s (%s)"
                                    % (start, sample_id, state["runtime"], expected, description))
                if not state["code"]:
                    failures.append("%s + %s: the sample code did not load" % (start, sample_id))
                if state["runtime"] == "docker" and state["dockerfile"] and not state["dockerfileText"]:
                    failures.append("%s + %s: the Dockerfile build is enabled but the Dockerfile is empty"
                                    % (start, sample_id))
        finally:
            browser.close()

    if failures:
        print("Job sample runtime checks failed:")
        for failure in failures:
            print("  - " + failure)
        return 1

    print("Job sample runtime checks passed (%d browser cases)." % len(CASES))
    return 0


if __name__ == "__main__":
    sys.exit(main())
