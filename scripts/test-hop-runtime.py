#!/usr/bin/env python3
"""Fast runtime checks for Hop variable and log handling."""

from __future__ import annotations

import os
import sys
import tempfile


ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "application", "third_party", "python", "jobseeker_sdk", "src"))

from jobseeker import hop  # noqa: E402


class FakeContext:
    def __init__(self, values):
        self.values = values

    def get_context(self, key):
        return self.values.get(key)


with tempfile.TemporaryDirectory(prefix="jobseeker-hop-test-") as root:
    with open(os.path.join(root, "project-config.json"), "w", encoding="utf-8") as stream:
        stream.write("{}\n")
    with open(os.path.join(root, "variables.hpl"), "w", encoding="utf-8") as stream:
        stream.write(
            "<pipeline>${CUSTOM_REGION} ${JOBSEEKER_ASSET_CUSTOMER_REFERENCE} "
            "${JOBSEEKER_ENVIRONMENT} ${PROJECT_HOME} ${HOP_LOG_LEVEL}</pipeline>\n"
        )

    project = hop.HopProject(root)
    manifest = hop.HopManifest({"context": ["JOBSEEKER_ENVIRONMENT"]})
    referenced = project.referenced_variables()
    assert "CUSTOM_REGION" in referenced
    assert "JOBSEEKER_ASSET_CUSTOMER_REFERENCE" in referenced

    context_names = hop._context_variable_names(
        project,
        manifest,
        ("JOBSEEKER_ENVIRONMENT",),
    )
    assert context_names == ["CUSTOM_REGION", "JOBSEEKER_ASSET_CUSTOMER_REFERENCE"]
    assert hop._runtime_environment(" dev ") == "DEV"

    values = hop._resolve_context(
        FakeContext({
            "CUSTOM_REGION": "south",
            "JOBSEEKER_ASSET_CUSTOMER_REFERENCE": "/data/customer.csv",
        }),
        context_names,
    )
    variables = hop.build_run_variables("DEV", "platform-variables", context=values)
    resolved = {variable["name"]: variable["value"] for variable in variables}
    assert resolved["CUSTOM_REGION"] == "south"
    assert resolved["JOBSEEKER_ASSET_CUSTOMER_REFERENCE"] == "/data/customer.csv"

    protected = hop.build_run_variables(
        "DEV",
        "platform-variables",
        context={"JOBSEEKER_ENVIRONMENT": "PROD"},
        parameters={"JOBSEEKER_JOB_NAME": "wrong-job"},
    )
    protected_values = {variable["name"]: variable["value"] for variable in protected}
    assert protected_values["JOBSEEKER_ENVIRONMENT"] == "DEV"
    assert protected_values["JOBSEEKER_JOB_NAME"] == "platform-variables"

    try:
        hop.run(
            root,
            "variables.hpl",
            parameters={"JOBSEEKER_ENVIRONMENT": "PROD"},
            dry_run=True,
        )
    except hop.HopError as error:
        assert "cannot replace JobSeeker runtime variables" in str(error)
    else:
        raise AssertionError("reserved JobSeeker parameters must be rejected")


clean_counters = hop.parse_hop_counters(
    "read.0 - Finished processing (I=1, O=0, R=1, W=1, U=0, E=0)"
)
errors = hop.extract_hop_errors(
    "2026/09/04 19:44:29 - read customers.0 - ERROR: Connection failed"
)
assert clean_counters["errors"] == 0
assert errors == [{"origin": "read customers.0", "message": "ERROR: Connection failed"}]

print("Apache Hop runtime checks passed")
