<?php defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Curated starters for the Job Creation execution editor.
 *
 * Keep this catalog server-side so samples are reviewable, versioned, and can
 * grow into multi-file Python workspaces without embedding large strings in
 * the view. The browser receives this array as JSON from JobCreation::index().
 *
 * Design rules for every entry:
 *   - Shell samples are POSIX `sh` (dash) compatible. Jenkins runs freestyle
 *     shell steps with `/bin/sh -xe`, so no bashisms: no `[[ ]]`, no arrays,
 *     no process substitution `< <(...)`, no `${var,,}`.
 *   - Every sample is runnable on a fresh stack with nothing pre-provisioned.
 *     Samples that can use a governed Data Asset, connector or Context value
 *     do so when it exists and otherwise print an actionable hint and exit 0,
 *     rather than failing the build.
 *   - Connector samples use the built-in `jobseeker-mariadb` connector, which
 *     every deployment seeds (ALL environments, shared `*` job scope), so they
 *     exercise the real connector runtime end to end.
 */
return array(
    array(
        'id' => 'shell-hello-runtime',
        'name' => 'Runtime hello and diagnostics',
        'family' => 'shell',
        'complexity' => 'simple',
        'description' => 'Print the Jenkins build identity, environment, host, workspace, and available disk space.',
        'tags' => array('starter', 'diagnostics'),
        'integrations' => array('jenkins', 'environments'),
        'job_description' => 'A small shell smoke test generated from the JobSeeker sample catalog.',
        'command' => <<<'SHELL'
set -eu

echo "Hello from ${JOB_NAME:-JobSeeker} build ${BUILD_NUMBER:-local}"
echo "Environment: ${ENVIRONMENT:-LOCAL}"
echo "Host: $(hostname)"
echo "Workspace: ${WORKSPACE:-$(pwd)}"
echo "Data asset manifest: ${JOBSEEKER_DATA_ASSETS_MANIFEST:-not set}"
df -h "${WORKSPACE:-.}"
SHELL
    ),
    array(
        'id' => 'shell-safe-batch',
        'name' => 'Guarded batch processor',
        'family' => 'shell',
        'complexity' => 'intermediate',
        'description' => 'Process a directory of files with strict mode, a cleanup trap, counters, and a bounded limit. Seeds a sample batch when the input is empty.',
        'tags' => array('files', 'batch', 'safe'),
        'integrations' => array('jenkins', 'environments'),
        'job_description' => 'A guarded shell batch with cleanup, progress output, and a deterministic limit.',
        'command' => <<<'SHELL'
set -eu

work_root="${WORKSPACE:-$(pwd)}"
input_dir="${INPUT_DIR:-$work_root/incoming}"
output_dir="$work_root/processed"
limit="${JOBSEEKER_SAMPLE_LIMIT:-25}"
processed=0
skipped=0

cleanup() {
  rm -rf "$output_dir"
}
trap cleanup EXIT INT TERM

mkdir -p "$input_dir" "$output_dir"

# Seed a deterministic batch when the input directory is empty so the starter
# is runnable before a real Data Asset or upload is wired in.
set -- "$input_dir"/*
if [ ! -e "$1" ]; then
  seed="${JOBSEEKER_SAMPLE_SEED:-8}"
  i=1
  while [ "$i" -le "$seed" ]; do
    printf 'row=%s squared=%s\n' "$i" "$((i * i))" > "$input_dir/sample-$i.txt"
    i=$((i + 1))
  done
  echo "Seeded $seed sample files into $input_dir"
  set -- "$input_dir"/*
fi

for source_file in "$@"; do
  [ -f "$source_file" ] || continue
  if [ "$processed" -ge "$limit" ]; then
    skipped=$((skipped + 1))
    continue
  fi
  if cp "$source_file" "$output_dir/$(basename "$source_file")"; then
    processed=$((processed + 1))
    printf 'processed=%s file=%s\n' "$processed" "$source_file"
  else
    echo "Failed to copy $source_file" >&2
    exit 1
  fi
done

echo "Batch complete: $processed processed, $skipped skipped (limit $limit)"
test "$processed" -gt 0
SHELL
    ),
    array(
        'id' => 'shell-data-asset',
        'name' => 'Published data asset consumer',
        'family' => 'shell',
        'complexity' => 'intermediate',
        'description' => 'Resolve a governed Data Asset with the jobseeker-asset helper and validate it. Degrades with a hint when no matching asset is registered.',
        'tags' => array('data asset', 'contract', 'validation'),
        'integrations' => array('data_assets', 'jenkins', 'environments'),
        'job_description' => 'Resolves and validates a published JobSeeker Data Asset from a shell job.',
        'command' => <<<'SHELL'
set -eu

asset_key="${JOBSEEKER_ASSET_KEY:-etl}"

if ! command -v jobseeker-asset >/dev/null 2>&1; then
  echo "jobseeker-asset is not installed on this Jenkins worker; skipping asset validation."
  exit 0
fi

if ! asset_path="$(jobseeker-asset "$asset_key" 2>/dev/null)"; then
  echo "Data Asset '$asset_key' is not registered for this environment and job scope."
  echo "Register it under ETL > Data Assets, or set JOBSEEKER_ASSET_KEY in Context Settings, then re-run."
  exit 0
fi

if [ ! -f "$asset_path" ]; then
  echo "Resolved asset file is missing: $asset_path" >&2
  exit 1
fi

echo "Resolved $asset_key -> $asset_path"
echo "Bytes: $(wc -c < "$asset_path")"
echo "Lines: $(wc -l < "$asset_path")"
echo "--- preview ---"
head -n "${JOBSEEKER_PREVIEW_ROWS:-5}" "$asset_path"
SHELL
    ),
    array(
        'id' => 'shell-resilient-http',
        'name' => 'Resilient HTTP ingestion',
        'family' => 'shell',
        'complexity' => 'advanced',
        'description' => 'Download a JSON payload with bounded retries, timeouts, atomic output, and response validation. Defaults to an in-stack endpoint so it runs out of the box.',
        'tags' => array('api', 'retry', 'atomic'),
        'integrations' => array('jenkins', 'environments'),
        'job_description' => 'A production-oriented HTTP ingestion shell with retry and atomic publish behavior.',
        'command' => <<<'SHELL'
set -eu

# Override SOURCE_URL in Context Settings or the Jenkins environment for a real
# feed. The default is the bundled Mailpit info endpoint, reachable from every
# JobSeeker worker, so this starter runs with no configuration.
source_url="${SOURCE_URL:-http://mailpit:8025/api/v1/info}"
output_file="${OUTPUT_FILE:-${WORKSPACE:-.}/api-response.json}"
temporary_file="${output_file}.tmp.$$"
max_attempts="${HTTP_MAX_ATTEMPTS:-4}"
attempt=1

cleanup() {
  rm -f "$temporary_file"
}
trap cleanup EXIT INT TERM

echo "Fetching $source_url"
while : ; do
  if curl --fail --show-error --silent --location \
       --connect-timeout 10 --max-time 60 \
       --header 'Accept: application/json' \
       --output "$temporary_file" "$source_url"; then
    break
  fi

  if [ "$attempt" -ge "$max_attempts" ]; then
    echo "Giving up after $attempt attempt(s)." >&2
    exit 1
  fi

  echo "Attempt $attempt failed; retrying in 2s"
  attempt=$((attempt + 1))
  sleep 2
done

if command -v python3 >/dev/null 2>&1; then
  python3 -m json.tool "$temporary_file" > /dev/null
else
  head -c 1 "$temporary_file" | grep -q '[[{]' || {
    echo "Downloaded payload is not JSON." >&2
    exit 1
  }
fi

mkdir -p "$(dirname "$output_file")"
mv "$temporary_file" "$output_file"
echo "Published validated response to $output_file ($(wc -c < "$output_file") bytes)"
SHELL
    ),
    array(
        'id' => 'shell-db-connector',
        'name' => 'Database connector query',
        'family' => 'shell',
        'complexity' => 'intermediate',
        'description' => 'Test the built-in jobseeker-mariadb connector and run a scoped read-only query through it without ever printing credentials.',
        'tags' => array('connector', 'database', 'sql'),
        'integrations' => array('connectors', 'database', 'jenkins', 'environments'),
        'job_description' => 'Resolves the built-in JobSeeker database connector and runs a guarded query from a shell job.',
        'command' => <<<'SHELL'
set -eu

connector_key="${JOBSEEKER_DB_CONNECTOR:-jobseeker-mariadb}"

if ! command -v jobseeker-connector >/dev/null 2>&1; then
  echo "The JobSeeker connector helper is not installed on this Jenkins worker." >&2
  exit 78
fi

echo "Connector test: $connector_key"
jobseeker-connector test "$connector_key" --json

echo "Scoped query through the connector (credentials never printed):"
jobseeker-connector exec "$connector_key" -- python3 - <<'PY'
import json
import os

import mysql.connector

connection = mysql.connector.connect(
    host=os.environ["JOBSEEKER_CONNECTOR_HOST"],
    port=int(os.environ.get("JOBSEEKER_CONNECTOR_PORT", "3306")),
    user=os.environ["JOBSEEKER_CONNECTOR_USERNAME"],
    password=os.environ["JOBSEEKER_CONNECTOR_PASSWORD"],
    database=os.environ.get("JOBSEEKER_CONNECTOR_DATABASE") or None,
    connection_timeout=5,
)
try:
    cursor = connection.cursor()
    cursor.execute("SELECT VERSION()")
    server_version = cursor.fetchone()[0]
    cursor.execute(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
    )
    table_count = cursor.fetchone()[0]
    cursor.close()
finally:
    connection.close()

print(json.dumps({
    "connector": os.environ["JOBSEEKER_CONNECTOR_KEY"],
    "connector_type": os.environ.get("JOBSEEKER_CONNECTOR_TYPE", "unknown"),
    "server_version": server_version,
    "tables_in_schema": table_count,
}, sort_keys=True))
PY
SHELL
    ),
    array(
        'id' => 'shell-connector-asset-bridge',
        'name' => 'Connector and Data Asset bridge',
        'family' => 'shell',
        'complexity' => 'advanced',
        'description' => 'Verify a scoped connector, resolve an optional governed dataset, and run a consumer with protected connector values in its environment.',
        'tags' => array('connector', 'data asset', 'secure runtime'),
        'integrations' => array('connectors', 'database', 'data_assets', 'jenkins', 'environments'),
        'job_description' => 'An end-to-end shell handoff using JobSeeker connector and Data Asset runtime helpers.',
        'command' => <<<'SHELL'
set -eu

connector_key="${JOBSEEKER_DB_CONNECTOR:-jobseeker-mariadb}"
asset_key="${JOBSEEKER_ASSET_KEY:-etl}"

command -v jobseeker-connector >/dev/null 2>&1 || {
  echo "jobseeker-connector is not installed on this Jenkins worker." >&2
  exit 127
}

# The helper reports sanitized connection status and never prints credentials.
jobseeker-connector test "$connector_key" --json

asset_path=""
if command -v jobseeker-asset >/dev/null 2>&1 && asset_path="$(jobseeker-asset "$asset_key" 2>/dev/null)"; then
  echo "Resolved Data Asset $asset_key -> $asset_path"
else
  echo "Optional Data Asset '$asset_key' is not registered; continuing with the connector only."
  asset_path=""
fi

jobseeker-connector exec "$connector_key" -- python3 - "$asset_path" <<'PY'
import json
import os
import pathlib
import sys

asset_argument = sys.argv[1] if len(sys.argv) > 1 else ""
asset = pathlib.Path(asset_argument) if asset_argument else None

payload = {
    "connector": os.environ.get("JOBSEEKER_CONNECTOR_KEY", "unknown"),
    "connector_type": os.environ.get("JOBSEEKER_CONNECTOR_TYPE", "unknown"),
    "connector_host": os.environ.get("JOBSEEKER_CONNECTOR_HOST", ""),
    "environment": os.environ.get("ENVIRONMENT", "LOCAL"),
    "asset": str(asset) if asset else None,
    "asset_bytes": asset.stat().st_size if asset and asset.is_file() else None,
}
print(json.dumps(payload, sort_keys=True))
PY
SHELL
    ),
    array(
        'id' => 'python-sdk-progress',
        'name' => 'JobSeeker TMF progress',
        'family' => 'python',
        'complexity' => 'simple',
        'description' => 'Create one tracked TMF task, read a Context value, and publish bounded progress heartbeats.',
        'tags' => array('tmf', 'context', 'starter'),
        'integrations' => array('tmf', 'contexts', 'jenkins', 'environments'),
        'job_description' => 'A minimal Python JobSeeker SDK job with TMF progress reporting.',
        'entry_point' => 'main.py',
        'runtime' => 'local',
        'code' => <<<'PYTHON'
import os
import time

from jobseeker import JobSeeker


ENVIRONMENT = os.getenv("ENVIRONMENT", "LOCAL")
JOB_NAME = os.getenv("JOB_NAME", "python-tmf-progress")
PREVIEW = os.getenv("JOBSEEKER_PREVIEW") == "1"


def main() -> None:
    with JobSeeker(environment=ENVIRONMENT, job=JOB_NAME) as js:
        with js.task("Process sample rows", "STG_SAMPLE") as tmf:
            requested = tmf.context("sample_rows", cast=int, default=10)
            total = min(requested, 5) if PREVIEW else requested

            for processed in range(1, total + 1):
                print(f"Processing row {processed}/{total}")
                tmf.progress(processed=processed, total=total, msg=f"Processed {processed}/{total}")
                time.sleep(0.1 if PREVIEW else 0.5)

            tmf.finish(total=total, processed=total, msg="Sample processing complete")


if __name__ == "__main__":
    main()
PYTHON
    ),
    array(
        'id' => 'python-asset-transform',
        'name' => 'Data Asset transform',
        'family' => 'python',
        'complexity' => 'intermediate',
        'description' => 'Read an optional governed input, normalize records, and write a declared output contract. Falls back to preview rows when the asset is absent.',
        'tags' => array('data asset', 'transform', 'jsonl'),
        'integrations' => array('tmf', 'data_assets', 'jenkins', 'environments'),
        'job_description' => 'A standard-library transform using JobSeeker input and output Data Assets.',
        'entry_point' => 'main.py',
        'runtime' => 'local',
        'code' => <<<'PYTHON'
import os
from typing import Any

from jobseeker import JobSeeker


INPUT_ASSET = os.getenv("JOBSEEKER_INPUT_ASSET", "customer-reference")
OUTPUT_ASSET = os.getenv("JOBSEEKER_OUTPUT_ASSET", "customer-normalized")


def normalize(row: Any) -> dict[str, Any]:
    if isinstance(row, dict):
        return {str(key).strip().lower(): value for key, value in row.items()}
    return {"value": row}


def main() -> None:
    environment = os.getenv("ENVIRONMENT", "LOCAL")
    job_name = os.getenv("JOB_NAME", "python-asset-transform")

    with JobSeeker(environment=environment, job=job_name) as js:
        with js.task("Normalize customer reference", "STG_CUSTOMER") as tmf:
            source = tmf.asset(INPUT_ASSET, required=False)
            rows = source.read() if source is not None else [
                {"Customer_ID": 1, "Name": "Preview Customer"}
            ]
            normalized = [normalize(row) for row in rows]
            tmf.progress(total=len(rows), processed=len(rows), msg="Rows normalized")

            target = tmf.asset(OUTPUT_ASSET, mode="output", required=False)
            if target is None:
                print(f"Output asset {OUTPUT_ASSET} is not registered; printing preview")
                print(normalized[:5])
            else:
                target.write(normalized)
                print(f"Published {len(normalized)} rows to {target.uri}")

            tmf.finish(total=len(rows), processed=len(normalized), msg="Asset transform complete")


if __name__ == "__main__":
    main()
PYTHON
    ),
    array(
        'id' => 'python-connector-inventory',
        'name' => 'Scoped connector inventory',
        'family' => 'python',
        'complexity' => 'advanced',
        'description' => 'Resolve a scoped connector without logging secrets, run a live handshake, and capture the result in TMF. Defaults to the built-in database connector.',
        'tags' => array('connector', 'database', 'health check'),
        'integrations' => array('tmf', 'connectors', 'database', 'jenkins', 'environments'),
        'job_description' => 'Tests a materialized connector safely and records the result in TMF.',
        'entry_point' => 'main.py',
        'runtime' => 'local',
        'code' => <<<'PYTHON'
import json
import os

from jobseeker import JobSeeker


CONNECTOR_KEY = os.getenv("JOBSEEKER_CONNECTOR", "jobseeker-mariadb")


def main() -> None:
    environment = os.getenv("ENVIRONMENT", "LOCAL")
    job_name = os.getenv("JOB_NAME", "python-connector-inventory")

    with JobSeeker(environment=environment, job=job_name) as js:
        with js.task("Validate connector", "OPS_CONNECTIVITY") as tmf:
            connector = tmf.connector(CONNECTOR_KEY, required=False)
            if connector is None:
                print(f"Optional connector '{CONNECTOR_KEY}' is not assigned to this job")
                tmf.finish(total=1, processed=0, msg="Optional connector not configured")
                return

            # as_dict() excludes protected values unless explicitly requested.
            print(json.dumps(connector.as_dict(), indent=2, sort_keys=True))
            result = connector.test(timeout=5.0)
            if not result.ok:
                raise RuntimeError(f"Connector test failed: {result.message}")

            print(f"{connector.key} passed in {getattr(result, 'latency_ms', 0)} ms")
            tmf.finish(total=1, processed=1, msg=f"{connector.key} connection passed")


if __name__ == "__main__":
    main()
PYTHON
    ),
    array(
        'id' => 'python-db-connector-inspect',
        'name' => 'Database connector inspection',
        'family' => 'python',
        'complexity' => 'intermediate',
        'description' => 'Resolve the built-in jobseeker-mariadb connector, open a real MySQL connection with its materialized values, and read schema metadata under one TMF task.',
        'tags' => array('connector', 'database', 'sql', 'starter'),
        'integrations' => array('tmf', 'connectors', 'database', 'jenkins', 'environments'),
        'job_description' => 'A simple Python job that connects to the JobSeeker database through a scoped connector.',
        'entry_point' => 'main.py',
        'runtime' => 'local',
        'code' => <<<'PYTHON'
import json
import os

from jobseeker import JobSeeker


CONNECTOR_KEY = os.getenv("JOBSEEKER_DB_CONNECTOR", "jobseeker-mariadb")


def main() -> None:
    environment = os.getenv("ENVIRONMENT", "LOCAL")
    job_name = os.getenv("JOB_NAME", "python-db-connector-inspect")

    with JobSeeker(environment=environment, job=job_name) as js:
        with js.task("Inspect application database", "OPS_DB_INSPECT") as tmf:
            connector = tmf.connector(CONNECTOR_KEY, required=False)
            if connector is None:
                print(f"Connector '{CONNECTOR_KEY}' is not assigned to this job; nothing to inspect.")
                tmf.finish(total=1, processed=0, msg="Connector not configured")
                return

            import mysql.connector

            connection = mysql.connector.connect(
                host=connector.host,
                port=connector.port or 3306,
                user=connector.username,
                password=connector.password,
                database=connector.database or None,
                connection_timeout=5,
            )
            try:
                cursor = connection.cursor()
                cursor.execute("SELECT VERSION()")
                server_version = cursor.fetchone()[0]
                cursor.execute(
                    "SELECT table_name, table_rows "
                    "FROM information_schema.tables "
                    "WHERE table_schema = DATABASE() "
                    "ORDER BY table_name "
                    "LIMIT 10"
                )
                tables = [{"table": name, "approx_rows": rows} for name, rows in cursor.fetchall()]
                cursor.close()
            finally:
                connection.close()

            print(json.dumps({
                "connector": connector.key,
                "server_version": server_version,
                "sample_tables": tables,
            }, indent=2, sort_keys=True))

            tmf.progress(total=len(tables), processed=len(tables), msg="Schema metadata read")
            tmf.finish(total=len(tables), processed=len(tables), msg=f"Inspected {connector.key}")


if __name__ == "__main__":
    main()
PYTHON
    ),
    array(
        'id' => 'python-db-warehouse-etl',
        'name' => 'Database ETL warehouse workspace',
        'family' => 'python',
        'complexity' => 'advanced',
        'description' => 'A multi-file Docker ETL over the JobSeeker database connector: extract TMF history, aggregate it in a tested module, and publish a run summary as a Data Asset or table.',
        'tags' => array('etl', 'connector', 'database', 'docker', 'pytest', 'multi-file', 'metrics'),
        'integrations' => array('tmf', 'connectors', 'database', 'data_assets', 'email_metrics', 'pipelines', 'docker', 'tests', 'jenkins', 'environments'),
        'job_description' => 'A multi-file JobSeeker ETL that reads and writes through the built-in database connector, built and tested in Docker.',
        'entry_point' => 'main.py',
        'runtime' => 'docker',
        'docker_image' => 'python:3.13-slim',
        'use_dockerfile' => TRUE,
        'run_tests' => TRUE,
        'requirements' => '',
        'code' => <<<'PYTHON'
import json
import os
import time
from typing import Any

from jobseeker import JobSeeker

from warehouse.aggregate import summarize_runs
from warehouse.db import connect


CONNECTOR_KEY = os.getenv("JOBSEEKER_DB_CONNECTOR", "jobseeker-mariadb")
SUMMARY_ASSET = os.getenv("JOBSEEKER_SUMMARY_ASSET", "tmf-run-summary")
HISTORY_LIMIT = int(os.getenv("JOBSEEKER_HISTORY_LIMIT", "500"))


def extract_rows(connector: Any) -> list[dict[str, Any]]:
    connection = connect(connector)
    try:
        cursor = connection.cursor(dictionary=True)
        cursor.execute(
            """
            SELECT environment, status, records_processed, warnings,
                   distict_errors AS errors
            FROM tmf
            ORDER BY id DESC
            LIMIT %s
            """,
            (HISTORY_LIMIT,),
        )
        rows = list(cursor.fetchall())
        cursor.close()
        return rows
    finally:
        connection.close()


def main() -> None:
    environment = os.getenv("ENVIRONMENT", "LOCAL")
    job_name = os.getenv("JOB_NAME", "python-db-warehouse-etl")
    started = time.monotonic()

    with JobSeeker(environment=environment, job=job_name) as js:
        with js.task("Extract TMF history", "STG_TMF", records_total=HISTORY_LIMIT) as extract:
            connector = extract.connector(CONNECTOR_KEY, required=False)
            if connector is None:
                print(f"Connector '{CONNECTOR_KEY}' is not assigned; nothing to extract.")
                extract.finish(total=1, processed=0, msg="Connector not configured")
                return
            rows = extract_rows(connector)
            extract.finish(total=len(rows), processed=len(rows), msg=f"Read {len(rows)} TMF rows")

        with js.task("Aggregate run history", "DW_TMF", records_total=len(rows)) as aggregate:
            summary = summarize_runs(rows)
            aggregate.progress(total=len(rows), processed=len(rows), msg=f"{len(summary)} groups")
            aggregate.finish(total=len(rows), processed=len(summary), msg="Aggregation complete")

        with js.task("Publish run summary", "DM_TMF", records_total=len(summary)) as publish:
            target = publish.asset(SUMMARY_ASSET, mode="output", required=False)
            if target is not None:
                target.write(summary)
                print(f"Published {len(summary)} summary rows to {target.uri}")
            else:
                print(f"Output asset {SUMMARY_ASSET} is not registered; printing summary")
                print(json.dumps(summary, indent=2, sort_keys=True))
            publish.finish(total=len(summary), processed=len(summary), msg="Summary published")

        js.email_metrics(
            dataset=SUMMARY_ASSET,
            rows_read=len(rows),
            rows_written=len(summary),
            duration=f"{time.monotonic() - started:.2f} seconds",
        )


if __name__ == "__main__":
    main()
PYTHON,
        'files' => array(
            array(
                'path' => 'warehouse/__init__.py',
                'content' => "from .aggregate import summarize_runs\n\n__all__ = [\"summarize_runs\"]\n"
            ),
            array(
                'path' => 'warehouse/db.py',
                'content' => <<<'PYTHON'
"""Open a database connection from a resolved JobSeeker connector."""
from typing import Any


def connect(connector: Any):
    import mysql.connector

    return mysql.connector.connect(
        host=connector.host,
        port=connector.port or 3306,
        user=connector.username,
        password=connector.password,
        database=connector.database or None,
        connection_timeout=5,
    )
PYTHON
            ),
            array(
                'path' => 'warehouse/aggregate.py',
                'content' => <<<'PYTHON'
"""Pure aggregation logic for TMF run history, unit tested in isolation."""
from collections import defaultdict
from typing import Any, Iterable


def _to_int(value: Any) -> int:
    try:
        return int(value)
    except (TypeError, ValueError):
        return 0


def summarize_runs(rows: Iterable[dict[str, Any]]) -> list[dict[str, Any]]:
    buckets: dict[tuple[str, str], dict[str, int]] = defaultdict(
        lambda: {"runs": 0, "records": 0, "warnings": 0, "errors": 0}
    )

    for row in rows:
        environment = str(row.get("environment") or "UNKNOWN").upper()
        status = str(row.get("status") or "UNKNOWN").upper()
        bucket = buckets[(environment, status)]
        bucket["runs"] += 1
        bucket["records"] += _to_int(row.get("records_processed"))
        bucket["warnings"] += _to_int(row.get("warnings"))
        bucket["errors"] += _to_int(row.get("errors"))

    summary = [
        {
            "environment": environment,
            "status": status,
            "runs": values["runs"],
            "records": values["records"],
            "warnings": values["warnings"],
            "errors": values["errors"],
        }
        for (environment, status), values in buckets.items()
    ]
    summary.sort(key=lambda item: (item["environment"], item["status"]))
    return summary
PYTHON
            ),
            array(
                'path' => 'tests/test_aggregate.py',
                'content' => <<<'PYTHON'
from warehouse.aggregate import summarize_runs


def test_empty_history_returns_no_groups() -> None:
    assert summarize_runs([]) == []


def test_groups_by_environment_and_status() -> None:
    rows = [
        {"environment": "dev", "status": "success", "records_processed": "10", "warnings": 0, "errors": 0},
        {"environment": "DEV", "status": "SUCCESS", "records_processed": 5, "warnings": 1, "errors": 0},
        {"environment": "dev", "status": "failed", "records_processed": None, "warnings": 0, "errors": 2},
    ]
    summary = summarize_runs(rows)
    assert summary == [
        {"environment": "DEV", "status": "FAILED", "runs": 1, "records": 0, "warnings": 0, "errors": 2},
        {"environment": "DEV", "status": "SUCCESS", "runs": 2, "records": 15, "warnings": 1, "errors": 0},
    ]
PYTHON
            )
        )
    ),
    array(
        'id' => 'python-quality-workspace',
        'name' => 'Tested data-quality workspace',
        'family' => 'python',
        'complexity' => 'advanced',
        'description' => 'Load a multi-file Docker workspace with typed validation logic and pytest coverage.',
        'tags' => array('docker', 'pytest', 'multi-file', 'quality'),
        'integrations' => array('tmf', 'data_assets', 'docker', 'tests', 'jenkins', 'environments'),
        'job_description' => 'A multi-file JobSeeker data-quality gate built and tested in Docker.',
        'entry_point' => 'main.py',
        'runtime' => 'docker',
        'docker_image' => 'python:3.13-slim',
        'use_dockerfile' => TRUE,
        'run_tests' => TRUE,
        'requirements' => '',
        'code' => <<<'PYTHON'
import os

from jobseeker import JobSeeker

from quality.rules import validate_rows


def main() -> None:
    environment = os.getenv("ENVIRONMENT", "LOCAL")
    job_name = os.getenv("JOB_NAME", "python-quality-workspace")

    with JobSeeker(environment=environment, job=job_name) as js:
        with js.task("Customer quality gate", "DQ_CUSTOMER") as tmf:
            source = tmf.asset("customer-reference", required=False)
            rows = source.read() if source is not None else [
                {"customer_id": "1001", "email": "preview@example.com"}
            ]
            failures = validate_rows(rows)
            processed = len(rows) - len(failures)
            tmf.progress(total=len(rows), processed=processed, msg=f"Rejected {len(failures)} rows")

            if failures:
                examples = "; ".join(failures[:5])
                raise ValueError(f"Quality gate rejected {len(failures)} rows: {examples}")

            tmf.finish(total=len(rows), processed=processed, msg="Quality gate passed")


if __name__ == "__main__":
    main()
PYTHON,
        'files' => array(
            array(
                'path' => 'quality/__init__.py',
                'content' => "from .rules import validate_rows\n\n__all__ = [\"validate_rows\"]\n"
            ),
            array(
                'path' => 'quality/rules.py',
                'content' => <<<'PYTHON'
from typing import Any, Iterable


def validate_rows(rows: Iterable[dict[str, Any]]) -> list[str]:
    failures: list[str] = []
    for index, row in enumerate(rows, start=1):
        customer_id = str(row.get("customer_id", "")).strip()
        email = str(row.get("email", "")).strip()
        if not customer_id:
            failures.append(f"row {index}: customer_id is required")
        if "@" not in email:
            failures.append(f"row {index}: email is invalid")
    return failures
PYTHON
            ),
            array(
                'path' => 'tests/test_rules.py',
                'content' => <<<'PYTHON'
from quality.rules import validate_rows


def test_valid_customer_passes() -> None:
    assert validate_rows([{"customer_id": "1", "email": "a@example.com"}]) == []


def test_missing_fields_are_reported() -> None:
    failures = validate_rows([{"customer_id": "", "email": "invalid"}])
    assert len(failures) == 2
PYTHON
            )
        )
    ),
    array(
        'id' => 'python-multi-stage-etl',
        'name' => 'Multi-stage tracked ETL',
        'family' => 'python',
        'complexity' => 'advanced',
        'description' => 'Track extract, transform, and publish as independent TMF tasks and emit email metrics.',
        'tags' => array('etl', 'multiple tasks', 'metrics'),
        'integrations' => array('tmf', 'data_assets', 'email_metrics', 'pipelines', 'jenkins', 'environments'),
        'job_description' => 'A structured Python ETL with task-level TMF visibility and email metrics.',
        'entry_point' => 'main.py',
        'runtime' => 'local',
        'code' => <<<'PYTHON'
import os
import time
from typing import Any

from jobseeker import JobSeeker


def main() -> None:
    environment = os.getenv("ENVIRONMENT", "LOCAL")
    job_name = os.getenv("JOB_NAME", "python-multi-stage-etl")
    started = time.monotonic()

    with JobSeeker(environment=environment, job=job_name) as js:
        with js.task("Extract orders", "STG_ORDERS") as extract:
            source = extract.asset("orders-inbound", required=False)
            rows: list[dict[str, Any]] = source.read() if source is not None else [
                {"order_id": "preview-1", "amount": "42.50"}
            ]
            extract.finish(total=len(rows), processed=len(rows), msg="Orders extracted")

        with js.task("Transform orders", "DW_ORDERS", records_total=len(rows)) as transform:
            output = [
                {**row, "amount": round(float(row.get("amount", 0)), 2)}
                for row in rows
            ]
            transform.finish(total=len(rows), processed=len(output), msg="Orders transformed")

        with js.task("Publish orders", "DM_ORDERS", records_total=len(output)) as publish:
            target = publish.asset("orders-curated", mode="output", required=False)
            if target is not None:
                target.write(output)
            else:
                print(output[:5])
            publish.finish(total=len(output), processed=len(output), msg="Orders published")

        js.email_metrics(
            dataset="orders-curated",
            rows_read=len(rows),
            rows_written=len(output),
            duration=f"{time.monotonic() - started:.2f} seconds",
        )


if __name__ == "__main__":
    main()
PYTHON
    ),
    array(
        'id' => 'python-platform-pipeline',
        'name' => 'Full platform integration pipeline',
        'family' => 'python',
        'complexity' => 'advanced',
        'description' => 'Combine Contexts, a scoped connector, governed input/output assets, TMF stages, and email metrics in one pipeline-ready job.',
        'tags' => array('end to end', 'connector', 'dataset', 'context', 'metrics'),
        'integrations' => array('tmf', 'contexts', 'connectors', 'database', 'data_assets', 'email_metrics', 'pipelines', 'jenkins', 'environments'),
        'job_description' => 'A pipeline-ready JobSeeker workload exercising the core runtime integrations end to end.',
        'entry_point' => 'main.py',
        'runtime' => 'local',
        'code' => <<<'PYTHON'
import os
import time
from typing import Any

from jobseeker import JobSeeker


CONNECTOR_KEY = os.getenv("JOBSEEKER_CONNECTOR", "jobseeker-mariadb")


def as_bool(value: Any) -> bool:
    return str(value).strip().lower() in {"1", "true", "yes", "on"}


def normalize_order(row: dict[str, Any]) -> tuple[dict[str, Any] | None, str | None]:
    order_id = str(row.get("order_id", "")).strip()
    try:
        amount = round(float(row.get("amount", 0)), 2)
    except (TypeError, ValueError):
        return None, f"order {order_id or '<missing>'}: amount is invalid"
    if not order_id:
        return None, "order_id is required"
    if amount < 0:
        return None, f"order {order_id}: amount cannot be negative"
    return {**row, "order_id": order_id, "amount": amount}, None


def main() -> None:
    environment = os.getenv("ENVIRONMENT", "LOCAL")
    job_name = os.getenv("JOB_NAME", "python-platform-pipeline")
    pipeline_name = os.getenv("JOBSEEKER_PIPELINE_NAME", "manual-run")
    started = time.monotonic()

    with JobSeeker(environment=environment, job=job_name) as js:
        batch_size = js.get_context("pipeline_batch_size", cast=int, default=500)
        quality_threshold = js.get_context("quality_threshold", cast=float, default=0.98)
        validate_connector = js.get_context("validate_connector", cast=as_bool, default=False)

        with js.task("Discover platform inputs", "STG_ORDERS") as discover:
            connector = discover.connector(CONNECTOR_KEY, required=False)
            if connector is None:
                print(f"Optional connector '{CONNECTOR_KEY}' is not assigned; using the asset-only path")
            elif validate_connector:
                result = connector.test(timeout=5.0)
                if not result.ok:
                    raise RuntimeError(f"Connector check failed: {result.message}")
                print(f"Connector {connector.key} passed in {getattr(result, 'latency_ms', 0)} ms")

            source = discover.asset("orders-inbound", required=False)
            rows: list[dict[str, Any]] = source.read() if source is not None else [
                {"order_id": "preview-1", "amount": "42.50", "source": "preview"}
            ]
            rows = rows[:batch_size]
            discover.finish(total=len(rows), processed=len(rows), msg=f"Loaded {len(rows)} orders")

        accepted: list[dict[str, Any]] = []
        rejected: list[str] = []
        with js.task("Validate and normalize orders", "DQ_ORDERS", records_total=len(rows)) as quality:
            for index, row in enumerate(rows, start=1):
                normalized, failure = normalize_order(row)
                if failure is None and normalized is not None:
                    accepted.append(normalized)
                else:
                    rejected.append(failure or f"row {index} was rejected")
                if index % 100 == 0 or index == len(rows):
                    quality.progress(processed=index, total=len(rows), msg=f"Validated {index}/{len(rows)}")

            pass_rate = len(accepted) / max(1, len(rows))
            if pass_rate < quality_threshold:
                raise ValueError(
                    f"Quality threshold failed: {pass_rate:.2%} < {quality_threshold:.2%}; "
                    + "; ".join(rejected[:5])
                )
            quality.finish(total=len(rows), processed=len(accepted), msg=f"Accepted {len(accepted)} orders")

        with js.task("Publish curated orders", "DM_ORDERS", records_total=len(accepted)) as publish:
            target = publish.asset("orders-curated", mode="output", required=False)
            if target is None:
                print("Output asset orders-curated is not registered; printing a bounded preview")
                print(accepted[:5])
            else:
                target.write(accepted)
                print(f"Published {len(accepted)} rows to {target.uri}")
            publish.finish(total=len(accepted), processed=len(accepted), msg="Curated orders published")

        js.email_metrics(
            dataset="orders-curated",
            rows_read=len(rows),
            rows_written=len(accepted),
            rows_rejected=len(rejected),
            duration=f"{time.monotonic() - started:.2f} seconds ({pipeline_name})",
        )


if __name__ == "__main__":
    main()
PYTHON
    )
);
