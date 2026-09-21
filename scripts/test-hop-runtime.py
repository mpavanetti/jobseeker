#!/usr/bin/env python3
"""Fast runtime checks for Hop variable and log handling."""

from __future__ import annotations

import os
import sys
import tempfile
from types import SimpleNamespace


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

    connector_types = (
        "mysql", "pgsql", "sqlserver", "oracle_service", "oracle_sid",
        "mongodb", "redis", "snowflake", "databricks", "kafka", "rabbitmq",
        "elasticsearch", "sftp", "http_api", "aws_s3", "azure_blob",
        "azure_data_lake", "gcs", "git_repository", "generic_secret",
    )
    connectors = [
        SimpleNamespace(
            key="matrix-%s" % connector_type.replace("_", "-"),
            type=connector_type,
            host="connector.example.test",
            port=443,
            database="resource",
            username="matrix-user",
            password="matrix-password-%s" % connector_type,
            secrets={
                "username": "matrix-user",
                "password": "matrix-password-%s" % connector_type,
                "token": "matrix-token-%s" % connector_type,
                "api_key": "matrix-api-key-%s" % connector_type,
            },
            config={
                "oracle_service_name": "FREEPDB1",
                "oracle_sid": "XE",
            },
        )
        for connector_type in connector_types
    ]
    connector_variables = hop.build_run_variables("DEV", "connector-matrix", connectors=connectors)
    connector_values = {variable["name"]: variable["value"] for variable in connector_variables}
    for connector in connectors:
        prefix = hop._variable_name("JOBSEEKER_CONN", connector.key)
        assert connector_values[prefix + "_USER"] == "matrix-user"
        assert connector_values[prefix + "_PASSWORD"] == connector.password
        assert connector_values[prefix + "_TOKEN"] == connector.secrets["token"]
        assert connector_values[prefix + "_API_KEY"] == connector.secrets["api_key"]

    relational_types = set(hop.RELATIONAL_TYPES).intersection(connector_types)
    metadata = [hop.rdbms_metadata(connector) for connector in connectors]
    assert sum(document is not None for document in metadata) == len(relational_types)
    assert {
        connector.type for connector, document in zip(connectors, metadata) if document is not None
    } == relational_types
    serialized_metadata = str(metadata)
    assert "matrix-password" not in serialized_metadata and "matrix-token" not in serialized_metadata

    secret_names = set(hop.secret_variable_names(connector_variables))
    for connector in connectors:
        prefix = hop._variable_name("JOBSEEKER_CONN", connector.key)
        assert prefix + "_PASSWORD" in secret_names
        assert prefix + "_TOKEN" in secret_names
        assert prefix + "_API_KEY" in secret_names
    secret_text = " ".join(
        [connectors[0].password, connectors[0].secrets["token"], connectors[0].secrets["api_key"]]
    )
    assert hop.redact(secret_text, connector_variables) == "******** ******** ********"


clean_counters = hop.parse_hop_counters(
    "read.0 - Finished processing (I=1, O=0, R=1, W=1, U=0, E=0)"
)
errors = hop.extract_hop_errors(
    "2026/09/04 19:44:29 - read customers.0 - ERROR: Connection failed"
)
assert clean_counters["errors"] == 0
assert errors == [{"origin": "read customers.0", "message": "ERROR: Connection failed"}]

print("Apache Hop runtime checks passed")
