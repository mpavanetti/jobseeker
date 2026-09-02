#!/usr/bin/env python3
"""Focused deterministic tests for generate-performance-dataset.py."""

from __future__ import annotations

import datetime as dt
import importlib.util
import pathlib
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET


SCRIPT = pathlib.Path(__file__).with_name("generate-performance-dataset.py")
SPEC = importlib.util.spec_from_file_location("performance_dataset_generator", SCRIPT)
assert SPEC and SPEC.loader
module = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = module
SPEC.loader.exec_module(module)


def settings(**overrides):
    values = dict(
        command="seed",
        profile="quick",
        batch_key="unit-sample",
        tmf_rows=250,
        jobs=7,
        pipelines=3,
        pipeline_runs=5,
        environments=("DEV", "QA", "PROD"),
        seed=42,
        include_jenkins=False,
        apply=False,
        db_service="mariadb",
        db_user="mysql",
        db_password="not-in-command",
        db_name="jobseeker",
        jenkins_url="http://localhost:8080",
        jenkins_user="jobseeker",
        jenkins_token="jobseeker",
    )
    values.update(overrides)
    return module.Settings(**values)


now = dt.datetime(2026, 9, 2, 12, 0, 0)
first = list(module.tmf_records(settings(), now))
second = list(module.tmf_records(settings(), now))
assert first == second, "same seed and clock must produce identical TMF data"
assert len(first) == 250
assert all(len(row) == 18 for row in first)
assert {row[6] for row in first} == {"DEV", "QA", "PROD"}
assert {row[1] for row in first} >= {"ready", "warning", "error", "running", "cancelled", "queued"}

errors = list(module.error_records(settings(), now))
assert len(errors) == sum(row[1] == "error" for row in first)
assert all(error[0].startswith("unit-sample-tmf-") for error in errors)

graph = module.json.loads(module.graph_for_pipeline(settings(), 1))
assert len(graph["nodes"]) == 4 and len(graph["edges"]) == 3
assert graph["edges"][0] == {"source": "node-0", "target": "node-1", "condition": "SUCCESS"}
assert graph["nodes"][0]["label"].startswith("unit-sample-job-")

statement = module.insert_statement("example", ("name", "value"), [("O'Reilly", None)])
assert "'O''Reilly'" in statement and "NULL" in statement

xml = module.jenkins_xml("DEV", 1)
root = ET.fromstring(xml)
assert root.findtext(".//name") == "ENVIRONMENT"
assert root.findtext(".//defaultValue") == "DEV"

jenkins_commands = [module.jenkins_command(index) for index in range(5)]
combined_commands = "\n".join(jenkins_commands)
assert "tmf.context" in combined_commands and "js.email_metrics" in combined_commands
assert "jobseeker-asset customer-reference" in combined_commands
assert "jobseeker-connector test jobseeker-mariadb" in combined_commands
assert "pipeline_stage=" in combined_commands
for index, sample_command in enumerate(jenkins_commands):
    with tempfile.NamedTemporaryFile("w", suffix=".sh", encoding="utf-8") as stream:
        stream.write(sample_command)
        stream.flush()
        syntax = subprocess.run(["bash", "-n", stream.name], text=True, capture_output=True, check=False)
    assert syntax.returncode == 0, f"generated Jenkins command {index} is invalid: {syntax.stderr.strip()}"

command, environment = module.mysql_command(settings())
assert "not-in-command" not in " ".join(command), "database password must not appear in process arguments"
assert environment["MYSQL_PWD"] == "not-in-command"
assert module.batch_key(" Perf CI_01 ") == "perf-ci-01"
assert module.environment_list("dev,qas,prd") == ("DEV", "QA", "PROD")

cleanup_calls = []
original_run_sql = module.run_sql


def fake_run_sql(_settings, sql, capture=False):
    cleanup_calls.append(sql)
    if "CONCAT(job_count" in sql:
        return "7:0"
    if sql.startswith("SELECT CONCAT("):
        return "250:30:3"
    return ""


module.run_sql = fake_run_sql
module.cleanup(settings(command="cleanup"))
module.run_sql = original_run_sql
assert any("DELETE FROM tmf_error" in sql and "DELETE FROM generated_datasets" in sql for sql in cleanup_calls)

print("Performance dataset generator deterministic tests passed.")
