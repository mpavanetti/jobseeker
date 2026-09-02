#!/usr/bin/env python3
"""Generate and remove repeatable JobSeeker performance datasets.

The script streams SQL to the existing MariaDB container in bounded batches,
so stress profiles do not require holding the whole dataset in memory. It is a
dry run unless --apply is supplied. Optional Jenkins jobs are configured but
never triggered.
"""

from __future__ import annotations

import argparse
import base64
import datetime as dt
import http.cookiejar
import json
import os
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Iterable, Iterator, Sequence
from xml.sax.saxutils import escape as xml_escape


PROFILES = {
    "quick": {"tmf_rows": 500, "jobs": 6, "pipelines": 2, "pipeline_runs": 8},
    "performance": {"tmf_rows": 10_000, "jobs": 24, "pipelines": 6, "pipeline_runs": 40},
    "stress": {"tmf_rows": 50_000, "jobs": 60, "pipelines": 15, "pipeline_runs": 100},
}
ENVIRONMENT_ALIASES = {"QAS": "QA", "PRD": "PROD", "PRODUCTION": "PROD", "HOMOLOG": "HML", "HOMOLOGATION": "HML"}
CHUNK_SIZE = 500
REGISTRY_SCHEMA = """
CREATE TABLE IF NOT EXISTS `generated_datasets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `profile` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'generating',
  `tmf_rows` int(11) unsigned NOT NULL DEFAULT 0,
  `error_rows` int(11) unsigned NOT NULL DEFAULT 0,
  `job_count` int(11) unsigned NOT NULL DEFAULT 0,
  `pipeline_count` int(11) unsigned NOT NULL DEFAULT 0,
  `pipeline_run_rows` int(11) unsigned NOT NULL DEFAULT 0,
  `seed_value` int(11) unsigned NOT NULL DEFAULT 1,
  `include_jenkins` tinyint(1) NOT NULL DEFAULT 0,
  `config_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `metrics_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_by` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `generated_dataset_batch` (`batch_key`),
  KEY `generated_dataset_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
"""


@dataclass(frozen=True)
class Settings:
    command: str
    profile: str
    batch_key: str
    tmf_rows: int
    jobs: int
    pipelines: int
    pipeline_runs: int
    environments: tuple[str, ...]
    seed: int
    include_jenkins: bool
    apply: bool
    db_service: str
    db_user: str
    db_password: str
    db_name: str
    jenkins_url: str
    jenkins_user: str
    jenkins_token: str


def positive_int(value: str) -> int:
    parsed = int(value)
    if parsed < 1:
        raise argparse.ArgumentTypeError("must be at least 1")
    return parsed


def batch_key(value: str) -> str:
    normalized = re.sub(r"[^a-z0-9]+", "-", value.strip().lower()).strip("-")[:32]
    if len(normalized) < 3:
        raise argparse.ArgumentTypeError("must contain at least three letters or numbers")
    return normalized


def environment_list(value: str) -> tuple[str, ...]:
    values: list[str] = []
    for item in re.split(r"[\s,;]+", value.upper().strip()):
        item = ENVIRONMENT_ALIASES.get(item, item)
        if item and re.fullmatch(r"[A-Z][A-Z0-9_-]{0,14}", item) and item not in values:
            values.append(item)
    if not values:
        raise argparse.ArgumentTypeError("provide at least one valid environment")
    return tuple(values[:8])


def parser() -> argparse.ArgumentParser:
    result = argparse.ArgumentParser(description=__doc__)
    result.add_argument("command", choices=("seed", "cleanup"))
    result.add_argument("--profile", choices=tuple(PROFILES), default="performance")
    result.add_argument("--batch-key", type=batch_key, required=True)
    result.add_argument("--tmf-rows", type=positive_int)
    result.add_argument("--jobs", type=positive_int)
    result.add_argument("--pipelines", type=positive_int)
    result.add_argument("--pipeline-runs", type=positive_int)
    result.add_argument("--environments", type=environment_list, default=environment_list("DEV,QA,UAT,PROD"))
    result.add_argument("--seed", type=positive_int, default=42)
    result.add_argument("--jenkins", action="store_true", help="also create/remove generated Jenkins jobs")
    result.add_argument("--apply", action="store_true", help="perform changes; without this flag the script only prints its plan")
    result.add_argument("--db-service", default=os.getenv("JOBSEEKER_DB_SERVICE", "mariadb"))
    result.add_argument("--db-user", default=os.getenv("JOBSEEKER_DB_USER", "mysql"))
    result.add_argument("--db-password", default=os.getenv("JOBSEEKER_DB_PASSWORD", "mysql"))
    result.add_argument("--db-name", default=os.getenv("JOBSEEKER_DB_NAME", "jobseeker"))
    result.add_argument("--jenkins-url", default=os.getenv("JENKINS_URL", "http://localhost:8080"))
    result.add_argument("--jenkins-user", default=os.getenv("JENKINS_USER", os.getenv("JENKINS_ADMIN_ID", "jobseeker")))
    result.add_argument("--jenkins-token", default=os.getenv("JENKINS_TOKEN", os.getenv("JENKINS_ADMIN_PASSWORD", "jobseeker")))
    return result


def settings_from_args(arguments: argparse.Namespace) -> Settings:
    profile = PROFILES[arguments.profile]
    tmf_rows = arguments.tmf_rows or profile["tmf_rows"]
    jobs = arguments.jobs or profile["jobs"]
    pipelines = arguments.pipelines or profile["pipelines"]
    pipeline_runs = arguments.pipeline_runs or profile["pipeline_runs"]
    bounds = (("TMF rows", tmf_rows, 250_000), ("jobs", jobs, 200), ("pipelines", pipelines, 50), ("pipeline runs", pipeline_runs, 500))
    for label, value, maximum in bounds:
        if value > maximum:
            raise SystemExit(f"{label} exceeds the safety limit of {maximum:,}")
    return Settings(
        command=arguments.command,
        profile=arguments.profile,
        batch_key=arguments.batch_key,
        tmf_rows=tmf_rows,
        jobs=jobs,
        pipelines=pipelines,
        pipeline_runs=pipeline_runs,
        environments=arguments.environments,
        seed=min(arguments.seed, 2_147_483_647),
        include_jenkins=arguments.jenkins,
        apply=arguments.apply,
        db_service=arguments.db_service,
        db_user=arguments.db_user,
        db_password=arguments.db_password,
        db_name=arguments.db_name,
        jenkins_url=arguments.jenkins_url.rstrip("/"),
        jenkins_user=arguments.jenkins_user,
        jenkins_token=arguments.jenkins_token,
    )


def mysql_command(settings: Settings) -> tuple[list[str], dict[str, str]]:
    environment = os.environ.copy()
    environment["MYSQL_PWD"] = settings.db_password
    command = [
        "docker", "compose", "exec", "-T", "-e", "MYSQL_PWD",
        settings.db_service, "mariadb", "-N", "-B", "-u", settings.db_user, settings.db_name,
    ]
    return command, environment


def run_sql(settings: Settings, sql: str, capture: bool = False) -> str:
    command, environment = mysql_command(settings)
    result = subprocess.run(command, input=sql, text=True, capture_output=True, env=environment, check=False)
    if result.returncode:
        error = result.stderr.strip() or result.stdout.strip() or "MariaDB command failed"
        raise RuntimeError(error)
    return result.stdout.strip() if capture else ""


def sql_quote(value: object | None) -> str:
    if value is None:
        return "NULL"
    text = str(value).replace("\\", "\\\\").replace("'", "''").replace("\x00", "")
    return "'" + text + "'"


def chunks(values: Iterable[Sequence[object]], size: int = CHUNK_SIZE) -> Iterator[list[Sequence[object]]]:
    batch: list[Sequence[object]] = []
    for value in values:
        batch.append(value)
        if len(batch) >= size:
            yield batch
            batch = []
    if batch:
        yield batch


def insert_statement(table: str, columns: Sequence[str], rows: Sequence[Sequence[object]]) -> str:
    values = ",\n".join("(" + ",".join(sql_quote(value) for value in row) + ")" for row in rows)
    return f"INSERT INTO `{table}` (`{'`,`'.join(columns)}`) VALUES\n{values};\n"


def job_names(settings: Settings) -> list[str]:
    return [f"{settings.batch_key}-job-{index:03d}" for index in range(1, settings.jobs + 1)]


def status_at(index: int, seed: int) -> str:
    slot = ((index * 37) + (seed * 17)) % 100
    if slot < 58:
        return "ready"
    if slot < 72:
        return "warning"
    if slot < 84:
        return "error"
    if slot < 92:
        return "running"
    if slot < 97:
        return "cancelled"
    return "queued"


def tmf_records(settings: Settings, now: dt.datetime) -> Iterator[tuple[object, ...]]:
    names = job_names(settings)
    dimensions = ("STG_CUSTOMER", "STG_ORDERS", "DW_CUSTOMER", "DW_ORDERS", "FACT_SALES", "DM_FINANCE", "DQ_GOVERNANCE", "ML_FEATURES")
    events = ("Extract source", "Validate schema", "Normalize records", "Load warehouse", "Publish mart", "Reconcile totals", "Archive output", "Refresh features")
    for index in range(settings.tmf_rows):
        sequence = index + 1
        status = status_at(index, settings.seed)
        environment = settings.environments[(index + settings.seed) % len(settings.environments)]
        name = names[(index + settings.seed) % len(names)]
        dimension = dimensions[(index * 3 + settings.seed) % len(dimensions)]
        event = events[(index * 5 + settings.seed) % len(events)]
        total = 1_000 + ((index * 7_919 + settings.seed * 101) % 250_000)
        processed = total
        if status == "warning":
            processed = max(0, total - ((index % 250) + 1))
        elif status == "error":
            processed = int(total * (0.30 + ((index % 50) / 100)))
        elif status == "running":
            processed = int(total * (0.05 + ((index % 80) / 100)))
        elif status == "cancelled":
            processed = int(total * 0.2)
        elif status == "queued":
            processed = 0
        age = (index * 1_543 + settings.seed * 97) % (180 * 86_400)
        started = now - dt.timedelta(seconds=age)
        if status in ("running", "queued"):
            started = now - dt.timedelta(minutes=index % 90)
        duration = 5 + ((index * 29 + settings.seed) % 7_200)
        activity = min(now, started + dt.timedelta(seconds=duration))
        instance = f"{settings.batch_key}-tmf-{sequence:09d}"
        message = f"{event} {status} for {environment}: {processed} of {total} records."
        yield (
            f"{settings.batch_key}-if-{sequence:09d}", status, name, int(status == "error" and index % 2 == 0), event,
            dimension, environment, total, processed, activity.strftime("%Y-%m-%d %H:%M:%S"),
            str(dt.timedelta(seconds=duration)), int(status == "error"), "1" if status == "warning" else "0",
            f"synthetic-worker-{(index % 12) + 1:02d}", "dataset.generator", instance,
            started.strftime("%Y-%m-%d %H:%M:%S"), message,
        )


def error_records(settings: Settings, now: dt.datetime) -> Iterator[tuple[object, ...]]:
    names = job_names(settings)
    dimensions = ("STG_CUSTOMER", "STG_ORDERS", "DW_CUSTOMER", "DW_ORDERS", "FACT_SALES", "DM_FINANCE", "DQ_GOVERNANCE", "ML_FEATURES")
    for index in range(settings.tmf_rows):
        if status_at(index, settings.seed) != "error":
            continue
        environment = settings.environments[(index + settings.seed) % len(settings.environments)]
        moment = now - dt.timedelta(seconds=(index * 1_543 + settings.seed * 97) % (180 * 86_400))
        dimension = dimensions[(index * 3 + settings.seed) % len(dimensions)]
        yield (
            f"{settings.batch_key}-tmf-{index + 1:09d}", names[(index + settings.seed) % len(names)],
            moment.strftime("%Y-%m-%d %H:%M:%S"), "Data Quality" if index % 3 == 0 else "Synthetic Failure",
            dimension, f"Generated failure for performance testing in {environment}.", 7_000 + (index % 1_000),
        )


def graph_for_pipeline(settings: Settings, pipeline_index: int) -> str:
    names = job_names(settings)
    nodes = []
    edges = []
    for node_index in range(min(4, len(names))):
        name = names[(pipeline_index * 3 + node_index) % len(names)]
        nodes.append({"id": f"node-{node_index}", "job": name, "label": name, "x": 80 + (node_index * 220), "y": 100})
        if node_index:
            edges.append({"source": f"node-{node_index - 1}", "target": f"node-{node_index}", "condition": "SUCCESS"})
    return json.dumps({"nodes": nodes, "edges": edges}, separators=(",", ":"))


def stream_seed(settings: Settings) -> tuple[int, float]:
    command, environment = mysql_command(settings)
    process = subprocess.Popen(command, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, env=environment)
    assert process.stdin is not None
    now = dt.datetime.now().replace(microsecond=0)
    config = json.dumps({"environments": settings.environments, "jenkins_jobs": []}, separators=(",", ":"))
    tmf_columns = ("interface_id", "status", "job_name", "reprocess", "event_text", "dimension", "environment", "records_total", "records_processed", "last_activity", "running_time", "distict_errors", "warnings", "hostname", "username", "instance_id", "start_time", "msg")
    error_columns = ("tmf_id", "job_name", "moment", "type", "origin", "message", "code")
    started_at = time.monotonic()
    try:
        process.stdin.write("SET autocommit=0; START TRANSACTION;\n")
        registry_row = (
            settings.batch_key, settings.profile, "generating", settings.tmf_rows, 0, settings.jobs, settings.pipelines,
            settings.pipelines * settings.pipeline_runs, settings.seed, int(settings.include_jenkins), config, None,
            "performance-dataset.py", now.strftime("%Y-%m-%d %H:%M:%S"), now.strftime("%Y-%m-%d %H:%M:%S"),
        )
        process.stdin.write(insert_statement("generated_datasets", ("batch_key", "profile", "status", "tmf_rows", "error_rows", "job_count", "pipeline_count", "pipeline_run_rows", "seed_value", "include_jenkins", "config_json", "metrics_json", "created_by", "created_at", "updated_at"), [registry_row]))
        written = 0
        for batch in chunks(tmf_records(settings, now)):
            process.stdin.write(insert_statement("tmf", tmf_columns, batch))
            written += len(batch)
            if written % 10_000 == 0:
                print(f"streamed {written:,}/{settings.tmf_rows:,} TMF rows", file=sys.stderr)
        error_count = 0
        for batch in chunks(error_records(settings, now)):
            process.stdin.write(insert_statement("tmf_error", error_columns, batch))
            error_count += len(batch)

        run_statuses = ("SUCCESS", "SUCCESS", "SUCCESS", "FAILURE", "UNSTABLE", "ABORTED", "RUNNING", "QUEUED")
        for pipeline_index in range(settings.pipelines):
            environment_name = settings.environments[(pipeline_index + settings.seed) % len(settings.environments)]
            key = f"{settings.batch_key}-pipeline-{pipeline_index + 1:03d}"
            created = now - dt.timedelta(days=pipeline_index + 1)
            pipeline_row = (
                key, f"{settings.batch_key} Pipeline {pipeline_index + 1:03d}", "Generated datasets",
                "Synthetic end-to-end pipeline created by generate-performance-dataset.py.", environment_name,
                graph_for_pipeline(settings, pipeline_index), None, "sample", None, 1, 1,
                created.strftime("%Y-%m-%d %H:%M:%S"), created.strftime("%Y-%m-%d %H:%M:%S"), "Dataset Generator",
            )
            process.stdin.write(insert_statement("job_pipelines", ("pipeline_key", "name", "group_name", "description", "environment", "graph_json", "jenkins_job_name", "sync_status", "sync_error", "is_active", "version", "created_at", "updated_at", "owner"), [pipeline_row]))
            process.stdin.write("SET @jobseeker_generated_pipeline_id = LAST_INSERT_ID();\n")
            run_rows = []
            for run_index in range(settings.pipeline_runs):
                status = run_statuses[(run_index + pipeline_index + settings.seed) % len(run_statuses)]
                run_started = now - dt.timedelta(hours=run_index + 1, minutes=pipeline_index * 5)
                completed = None if status in ("RUNNING", "QUEUED") else run_started + dt.timedelta(seconds=60 + ((run_index * 47) % 2_400))
                run_rows.append((
                    "@PIPELINE_ID@", 100_000 + pipeline_index * settings.pipeline_runs + run_index, run_index + 1, status,
                    environment_name, "Jenkins schedule" if run_index % 4 == 0 else "dataset.generator",
                    run_started.strftime("%Y-%m-%d %H:%M:%S"), completed.strftime("%Y-%m-%d %H:%M:%S") if completed else None,
                    (completed or run_started + dt.timedelta(seconds=30)).strftime("%Y-%m-%d %H:%M:%S"),
                ))
            # LAST_INSERT_ID() must remain an expression, not a quoted value.
            statement = insert_statement("job_pipeline_runs", ("pipeline_id", "jenkins_queue_id", "jenkins_build_number", "status", "environment", "triggered_by", "started_at", "completed_at", "updated_at"), run_rows)
            process.stdin.write(statement.replace("'@PIPELINE_ID@'", "@jobseeker_generated_pipeline_id"))
        process.stdin.write(f"UPDATE generated_datasets SET status='ready', error_rows={error_count}, updated_at=NOW() WHERE batch_key={sql_quote(settings.batch_key)}; COMMIT;\n")
        process.stdin.close()
        stdout = process.stdout.read() if process.stdout else ""
        stderr = process.stderr.read() if process.stderr else ""
        return_code = process.wait()
        if return_code:
            raise RuntimeError(stderr.strip() or stdout.strip() or "MariaDB rejected the generated dataset")
    except Exception:
        if process.stdin and not process.stdin.closed:
            process.stdin.close()
        process.kill()
        process.wait()
        raise
    return error_count, time.monotonic() - started_at


class JenkinsClient:
    def __init__(self, settings: Settings):
        self.settings = settings
        self.crumb_header: tuple[str, str] | None = None
        cookie_jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))
        self.authorization = "Basic " + base64.b64encode(f"{settings.jenkins_user}:{settings.jenkins_token}".encode()).decode()

    def request(self, method: str, path: str, data: bytes | None = None, content_type: str | None = None) -> tuple[int, bytes]:
        headers = {"Authorization": self.authorization}
        if method != "GET":
            self.ensure_crumb()
            if self.crumb_header and self.crumb_header[0]:
                headers[self.crumb_header[0]] = self.crumb_header[1]
        if content_type:
            headers["Content-Type"] = content_type
        request = urllib.request.Request(f"{self.settings.jenkins_url}/{path.lstrip('/')}", data=data, headers=headers, method=method)
        try:
            with self.opener.open(request, timeout=30) as response:
                return response.status, response.read()
        except urllib.error.HTTPError as error:
            return error.code, error.read()

    def ensure_crumb(self) -> None:
        if self.crumb_header is not None:
            return
        request = urllib.request.Request(f"{self.settings.jenkins_url}/crumbIssuer/api/json", headers={"Authorization": self.authorization})
        try:
            with self.opener.open(request, timeout=15) as response:
                payload = json.loads(response.read())
                self.crumb_header = (payload["crumbRequestField"], payload["crumb"])
        except urllib.error.HTTPError as error:
            if error.code == 404:
                self.crumb_header = ("", "")
            else:
                raise


def jenkins_path(name: str) -> str:
    return "/".join("job/" + urllib.parse.quote(segment, safe="") for segment in name.strip("/").split("/") if segment)


def jenkins_command(index: int) -> str:
    if index % 5 == 1:
        return """set -Eeuo pipefail
python3 - <<'PY'
import os
import time
from jobseeker import JobSeeker

started = time.monotonic()
with JobSeeker(environment=os.getenv("ENVIRONMENT", "DEV"), job=os.getenv("JOB_NAME")) as js:
    with js.task("Generated Python sample", "PERF_PYTHON") as tmf:
        rows = tmf.context("generated_sample_rows", cast=int, default=25)
        asset = tmf.asset("customer-reference", required=False)
        connector = tmf.connector("jobseeker-mariadb", required=False)
        print("asset_registered=", asset is not None)
        print("connector_registered=", connector is not None)
        tmf.finish(total=rows, processed=rows, msg="Generated sample complete")
    js.email_metrics("customer-reference", rows, rows, duration=f"{time.monotonic() - started:.3f} seconds")
PY"""
    if index % 5 == 2:
        return """set -Eeuo pipefail
if asset_path="$(jobseeker-asset customer-reference 2>/dev/null)"; then
  echo "data_asset=customer-reference path=$asset_path bytes=$(wc -c < "$asset_path")"
else
  echo "Optional Data Asset customer-reference is not registered for this generated job"
fi"""
    if index % 5 == 3:
        return """set -Eeuo pipefail
jobseeker-connector list
jobseeker-connector test jobseeker-mariadb --timeout 5 --json"""
    if index % 5 == 4:
        return 'set -Eeuo pipefail\nfor stage in extract validate publish; do echo "pipeline_stage=$stage environment=${ENVIRONMENT:-DEV}"; done'
    return 'set -Eeuo pipefail\necho "shell_sample=${JOB_NAME:-unknown} build=${BUILD_NUMBER:-local}"\necho "environment=${ENVIRONMENT:-DEV} worker=$(hostname)"'


def jenkins_xml(environment: str, index: int) -> bytes:
    command = jenkins_command(index).replace("]]>", "] ]>")
    description = xml_escape("Generated by JobSeeker Dataset Generator. Safe to remove as a tracked batch.")
    environment = xml_escape(environment)
    xml = (
        "<?xml version='1.1' encoding='UTF-8'?><project><actions/><description>" + description + "</description>"
        "<keepDependencies>false</keepDependencies><properties><hudson.model.ParametersDefinitionProperty><parameterDefinitions>"
        "<hudson.model.StringParameterDefinition><name>ENVIRONMENT</name><description>JobSeeker runtime environment</description>"
        f"<defaultValue>{environment}</defaultValue><trim>true</trim></hudson.model.StringParameterDefinition>"
        "</parameterDefinitions></hudson.model.ParametersDefinitionProperty></properties><scm class='hudson.scm.NullSCM'/>"
        "<canRoam>true</canRoam><disabled>false</disabled><blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding>"
        "<blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding><triggers/><concurrentBuild>true</concurrentBuild>"
        f"<builders><hudson.tasks.Shell><command><![CDATA[{command}]]></command></hudson.tasks.Shell></builders>"
        "<publishers/><buildWrappers/></project>"
    )
    return xml.encode()


def create_jenkins_jobs(settings: Settings) -> tuple[list[str], int, float]:
    client = JenkinsClient(settings)
    created: list[str] = []
    failed = 0
    started = time.monotonic()
    for index, name in enumerate(job_names(settings)):
        try:
            path = jenkins_path(name)
            existing, _ = client.request("GET", path + "/api/json")
            xml = jenkins_xml(settings.environments[index % len(settings.environments)], index)
            if existing == 200:
                status, _ = client.request("POST", path + "/config.xml", xml, "application/xml")
            elif existing == 404:
                status, _ = client.request("POST", "createItem?name=" + urllib.parse.quote(name, safe=""), xml, "application/xml")
            else:
                status = existing
        except (OSError, urllib.error.URLError) as error:
            failed += 1
            print(f"Jenkins job {name} failed: {error}", file=sys.stderr)
            continue
        if status in (200, 201, 302, 303):
            created.append(name)
        else:
            failed += 1
            print(f"Jenkins job {name} failed with HTTP {status}", file=sys.stderr)
    return created, failed, time.monotonic() - started


def delete_jenkins_jobs(settings: Settings, count: int) -> tuple[int, int]:
    client = JenkinsClient(settings)
    deleted = failed = 0
    for index in range(1, count + 1):
        name = f"{settings.batch_key}-job-{index:03d}"
        try:
            status, _ = client.request("POST", jenkins_path(name) + "/doDelete")
        except (OSError, urllib.error.URLError) as error:
            failed += 1
            print(f"Jenkins cleanup for {name} failed: {error}", file=sys.stderr)
            continue
        if status in (200, 201, 302, 303, 404):
            deleted += 1
        else:
            failed += 1
    return deleted, failed


def seed(settings: Settings) -> None:
    run_sql(settings, REGISTRY_SCHEMA)
    exists = run_sql(settings, f"SELECT COUNT(*) FROM generated_datasets WHERE batch_key={sql_quote(settings.batch_key)};", capture=True)
    if exists != "0":
        raise RuntimeError(f"batch {settings.batch_key!r} already exists; clean it up or choose another key")
    error_count, database_seconds = stream_seed(settings)
    metrics = {
        "database_seconds": round(database_seconds, 3),
        "tmf_rows_per_second": round(settings.tmf_rows / database_seconds, 1) if database_seconds else None,
        "jenkins_seconds": 0,
        "jenkins_created": 0,
        "jenkins_failed": 0,
    }
    config = {"environments": settings.environments, "jenkins_jobs": []}
    if settings.include_jenkins:
        names, failed, jenkins_seconds = create_jenkins_jobs(settings)
        config["jenkins_jobs"] = job_names(settings)
        metrics.update(jenkins_seconds=round(jenkins_seconds, 3), jenkins_created=len(names), jenkins_failed=failed)
        status = "partial" if failed else "ready"
    else:
        status = "ready"
    run_sql(settings, f"UPDATE generated_datasets SET status={sql_quote(status)}, config_json={sql_quote(json.dumps(config, separators=(',', ':')))}, metrics_json={sql_quote(json.dumps(metrics, separators=(',', ':')))}, updated_at=NOW() WHERE batch_key={sql_quote(settings.batch_key)};")
    print(f"Generated {settings.tmf_rows:,} TMF rows ({error_count:,} errors), {settings.pipelines:,} pipelines, and {settings.pipelines * settings.pipeline_runs:,} pipeline runs in {database_seconds:.3f}s.")
    if settings.include_jenkins:
        print(f"Jenkins: {metrics['jenkins_created']} created/updated, {metrics['jenkins_failed']} failed in {metrics['jenkins_seconds']:.3f}s.")


def cleanup(settings: Settings) -> None:
    run_sql(settings, REGISTRY_SCHEMA)
    details = run_sql(settings, f"SELECT CONCAT(job_count, ':', include_jenkins) FROM generated_datasets WHERE batch_key={sql_quote(settings.batch_key)};", capture=True)
    if not details:
        raise RuntimeError(f"batch {settings.batch_key!r} was not found")
    job_count_text, included_text = details.split(":", 1)
    if settings.include_jenkins or included_text == "1":
        deleted, failed = delete_jenkins_jobs(settings, int(job_count_text))
        print(f"Jenkins: {deleted} removed or already absent, {failed} failed.")
        if failed:
            raise RuntimeError("Jenkins cleanup was incomplete; database rows were kept so cleanup can be retried safely")
    counts_sql = (
        "SELECT CONCAT("
        f"(SELECT COUNT(*) FROM tmf WHERE interface_id LIKE {sql_quote(settings.batch_key + '-if-%')}), ':', "
        f"(SELECT COUNT(*) FROM tmf_error WHERE tmf_id LIKE {sql_quote(settings.batch_key + '-tmf-%')}), ':', "
        f"(SELECT COUNT(*) FROM job_pipelines WHERE pipeline_key LIKE {sql_quote(settings.batch_key + '-pipeline-%')}));"
    )
    counts = run_sql(settings, counts_sql, capture=True).split(":")
    cleanup_sql = (
        "START TRANSACTION;"
        f"DELETE FROM tmf_error WHERE tmf_id LIKE {sql_quote(settings.batch_key + '-tmf-%')};"
        f"DELETE FROM tmf WHERE interface_id LIKE {sql_quote(settings.batch_key + '-if-%')};"
        f"DELETE FROM job_pipelines WHERE pipeline_key LIKE {sql_quote(settings.batch_key + '-pipeline-%')};"
        f"DELETE FROM generated_datasets WHERE batch_key={sql_quote(settings.batch_key)};COMMIT;"
    )
    run_sql(settings, cleanup_sql)
    print(f"Removed batch {settings.batch_key}: {int(counts[0]):,} TMF rows, {int(counts[1]):,} errors, and {int(counts[2]):,} pipelines.")


def print_plan(settings: Settings) -> None:
    action = "Generate" if settings.command == "seed" else "Remove"
    print(f"DRY RUN — {action} batch: {settings.batch_key}")
    if settings.command == "seed":
        print(f"Profile: {settings.profile}; seed: {settings.seed}; environments: {', '.join(settings.environments)}")
        print(f"TMF rows: {settings.tmf_rows:,}; job identities: {settings.jobs:,}; pipelines: {settings.pipelines:,}; pipeline runs: {settings.pipelines * settings.pipeline_runs:,}")
        print(f"Jenkins jobs: {'yes (configure only)' if settings.include_jenkins else 'no'}")
    else:
        print(f"Tracked Jenkins cleanup requested: {'yes' if settings.include_jenkins else 'use registry setting'}")
    print("Add --apply to perform this operation.")


def main() -> int:
    settings = settings_from_args(parser().parse_args())
    if not settings.apply:
        print_plan(settings)
        return 0
    try:
        if settings.command == "seed":
            seed(settings)
        else:
            cleanup(settings)
    except (OSError, RuntimeError, urllib.error.URLError) as error:
        print(f"Dataset generator failed: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
