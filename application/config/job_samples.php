<?php defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Curated starters for the Job Creation execution editor.
 *
 * Keep this catalog server-side so samples are reviewable, versioned, and can
 * grow into multi-file Python workspaces without embedding large strings in
 * the view. The browser receives this array as JSON from JobCreation::index().
 */
return array(
    array(
        'id' => 'shell-hello-runtime',
        'name' => 'Runtime hello and diagnostics',
        'family' => 'shell',
        'complexity' => 'simple',
        'description' => 'Print the Jenkins build identity, environment, host, and available disk space.',
        'tags' => array('starter', 'diagnostics'),
        'integrations' => array('jenkins', 'environments'),
        'job_description' => 'A small shell smoke test generated from the JobSeeker sample catalog.',
        'command' => <<<'SHELL'
set -Eeuo pipefail

echo "Hello from ${JOB_NAME:-JobSeeker} build ${BUILD_NUMBER:-local}"
echo "Environment: ${ENVIRONMENT:-LOCAL}"
echo "Host: $(hostname)"
echo "Workspace: ${WORKSPACE:-$(pwd)}"
df -h "${WORKSPACE:-.}"
SHELL
    ),
    array(
        'id' => 'shell-safe-batch',
        'name' => 'Guarded batch processor',
        'family' => 'shell',
        'complexity' => 'intermediate',
        'description' => 'Process files safely with strict mode, cleanup traps, counters, and a bounded preview mode.',
        'tags' => array('files', 'batch', 'safe'),
        'integrations' => array('jenkins', 'environments'),
        'job_description' => 'A guarded shell batch with cleanup, progress output, and deterministic preview limits.',
        'command' => <<<'SHELL'
set -Eeuo pipefail

input_dir="${INPUT_DIR:-${WORKSPACE:-.}/incoming}"
work_dir="$(mktemp -d)"
processed=0
failed=0

cleanup() {
  rm -rf "$work_dir"
}
trap cleanup EXIT INT TERM

mkdir -p "$input_dir"
limit="${JOBSEEKER_SAMPLE_LIMIT:-100}"

while IFS= read -r -d '' source_file; do
  if cp "$source_file" "$work_dir/$(basename "$source_file")"; then
    processed=$((processed + 1))
  else
    failed=$((failed + 1))
  fi

  printf 'processed=%d failed=%d file=%s\n' "$processed" "$failed" "$source_file"
  if [ "$processed" -ge "$limit" ]; then
    break
  fi
done < <(find "$input_dir" -maxdepth 1 -type f -print0)

echo "Batch complete: $processed processed, $failed failed"
test "$failed" -eq 0
SHELL
    ),
    array(
        'id' => 'shell-data-asset',
        'name' => 'Published data asset consumer',
        'family' => 'shell',
        'complexity' => 'intermediate',
        'description' => 'Resolve a governed Data Asset and validate it before handing it to another command.',
        'tags' => array('data asset', 'contract', 'validation'),
        'integrations' => array('data_assets', 'jenkins', 'environments'),
        'job_description' => 'Resolves and validates a published JobSeeker Data Asset from a shell job.',
        'command' => <<<'SHELL'
set -Eeuo pipefail

asset_key="${JOBSEEKER_ASSET_KEY:-customer-reference}"
command -v jobseeker-asset >/dev/null 2>&1 || {
  echo "jobseeker-asset is not installed on this Jenkins agent" >&2
  exit 127
}

asset_path="$(jobseeker-asset "$asset_key")"
test -f "$asset_path" || {
  echo "Resolved asset does not exist: $asset_path" >&2
  exit 2
}

echo "Resolved $asset_key to $asset_path"
echo "Bytes: $(wc -c < "$asset_path")"
echo "Rows: $(wc -l < "$asset_path")"
head -n "${JOBSEEKER_PREVIEW_ROWS:-5}" "$asset_path"
SHELL
    ),
    array(
        'id' => 'shell-resilient-http',
        'name' => 'Resilient HTTP ingestion',
        'family' => 'shell',
        'complexity' => 'advanced',
        'description' => 'Download an API payload with retries, timeouts, atomic output, and response validation.',
        'tags' => array('api', 'retry', 'atomic'),
        'integrations' => array('jenkins', 'environments'),
        'job_description' => 'A production-oriented HTTP ingestion shell with retry and atomic publish behavior.',
        'command' => <<<'SHELL'
set -Eeuo pipefail

source_url="${SOURCE_URL:?Set SOURCE_URL in Context Settings or the Jenkins environment}"
output_file="${OUTPUT_FILE:-${WORKSPACE:-.}/api-response.json}"
temporary_file="${output_file}.tmp.$$"

cleanup() {
  rm -f "$temporary_file"
}
trap cleanup EXIT INT TERM

curl --fail --show-error --silent --location \
  --connect-timeout 10 --max-time 120 \
  --retry 4 --retry-all-errors --retry-delay 2 \
  --header 'Accept: application/json' \
  --output "$temporary_file" "$source_url"

python3 -m json.tool "$temporary_file" >/dev/null
mkdir -p "$(dirname "$output_file")"
mv "$temporary_file" "$output_file"
echo "Published validated response to $output_file ($(wc -c < "$output_file") bytes)"
SHELL
    ),
    array(
        'id' => 'shell-connector-asset-bridge',
        'name' => 'Connector and Data Asset bridge',
        'family' => 'shell',
        'complexity' => 'advanced',
        'description' => 'Resolve a governed dataset, verify a scoped connector, and run a consumer with protected connector values.',
        'tags' => array('connector', 'data asset', 'secure runtime'),
        'integrations' => array('connectors', 'data_assets', 'jenkins', 'environments'),
        'job_description' => 'An end-to-end shell handoff using JobSeeker connector and Data Asset runtime helpers.',
        'command' => <<<'SHELL'
set -Eeuo pipefail

command -v jobseeker-asset >/dev/null 2>&1 || {
  echo "jobseeker-asset is not installed on this Jenkins agent" >&2
  exit 127
}
command -v jobseeker-connector >/dev/null 2>&1 || {
  echo "jobseeker-connector is not installed on this Jenkins agent" >&2
  exit 127
}

orders_path="$(jobseeker-asset orders-inbound)"
test -f "$orders_path" || {
  echo "The orders-inbound asset is registered but its file is unavailable" >&2
  exit 2
}

# The helper reports sanitized connection status and never prints credentials.
jobseeker-connector test warehouse --timeout 5 --json
jobseeker-connector exec warehouse -- python3 - "$orders_path" <<'PYTHON'
import json
import os
import pathlib
import sys

source = pathlib.Path(sys.argv[1])
print(json.dumps({
    "asset": str(source),
    "bytes": source.stat().st_size,
    "connector": os.environ.get("JOBSEEKER_CONNECTOR_KEY", "unknown"),
    "connector_type": os.environ.get("JOBSEEKER_CONNECTOR_TYPE", "unknown"),
    "environment": os.environ.get("ENVIRONMENT", "LOCAL"),
}, sort_keys=True))
PYTHON
SHELL
    ),
    array(
        'id' => 'python-sdk-progress',
        'name' => 'JobSeeker TMF progress',
        'family' => 'python',
        'complexity' => 'simple',
        'description' => 'Create one tracked TMF task, read context, and publish bounded progress heartbeats.',
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
        'description' => 'Read an optional governed input, normalize records, and write a declared output contract.',
        'tags' => array('data asset', 'transform', 'jsonl'),
        'integrations' => array('tmf', 'data_assets', 'jenkins', 'environments'),
        'job_description' => 'A standard-library transform using JobSeeker input and output Data Assets.',
        'entry_point' => 'main.py',
        'runtime' => 'local',
        'code' => <<<'PYTHON'
import os
from typing import Any

from jobseeker import JobSeeker


def normalize(row: Any) -> dict[str, Any]:
    if isinstance(row, dict):
        return {str(key).strip().lower(): value for key, value in row.items()}
    return {"value": row}


def main() -> None:
    environment = os.getenv("ENVIRONMENT", "LOCAL")
    job_name = os.getenv("JOB_NAME", "python-asset-transform")

    with JobSeeker(environment=environment, job=job_name) as js:
        with js.task("Normalize customer reference", "STG_CUSTOMER") as tmf:
            source = tmf.asset("customer-reference", required=False)
            rows = source.read() if source is not None else [
                {"Customer_ID": 1, "Name": "Preview Customer"}
            ]
            normalized = [normalize(row) for row in rows]
            tmf.progress(total=len(rows), processed=len(rows), msg="Rows normalized")

            target = tmf.asset("customer-normalized", mode="output", required=False)
            if target is None:
                print("Output asset customer-normalized is not registered; printing preview")
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
        'description' => 'Resolve a scoped connector without logging secrets, test connectivity, and capture TMF state.',
        'tags' => array('connector', 'security', 'health check'),
        'integrations' => array('tmf', 'connectors', 'jenkins', 'environments'),
        'job_description' => 'Tests a materialized connector safely and records the result in TMF.',
        'entry_point' => 'main.py',
        'runtime' => 'local',
        'code' => <<<'PYTHON'
import json
import os

from jobseeker import JobSeeker


def main() -> None:
    environment = os.getenv("ENVIRONMENT", "LOCAL")
    job_name = os.getenv("JOB_NAME", "python-connector-inventory")

    with JobSeeker(environment=environment, job=job_name) as js:
        with js.task("Validate warehouse connector", "OPS_CONNECTIVITY") as tmf:
            connector = tmf.connector("warehouse", required=False)
            if connector is None:
                print("Optional connector 'warehouse' is not assigned to this job")
                tmf.finish(total=1, processed=0, msg="Optional connector not configured")
                return

            # as_dict() excludes protected values unless explicitly requested.
            print(json.dumps(connector.as_dict(), indent=2, sort_keys=True))
            result = connector.test(timeout=5.0)
            if not result.ok:
                raise RuntimeError(f"Connector test failed: {result.message}")

            tmf.finish(total=1, processed=1, msg=f"{connector.key} connection passed")


if __name__ == "__main__":
    main()
PYTHON
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
        'description' => 'Combine contexts, scoped connectors, governed input/output assets, TMF stages, and email metrics in one pipeline-ready job.',
        'tags' => array('end to end', 'connector', 'dataset', 'context', 'metrics'),
        'integrations' => array('tmf', 'contexts', 'connectors', 'data_assets', 'email_metrics', 'pipelines', 'jenkins', 'environments'),
        'job_description' => 'A pipeline-ready JobSeeker workload exercising the core runtime integrations end to end.',
        'entry_point' => 'main.py',
        'runtime' => 'local',
        'code' => <<<'PYTHON'
import os
import time
from typing import Any

from jobseeker import JobSeeker


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
            warehouse = discover.connector("warehouse", required=False)
            if warehouse is None:
                print("Optional warehouse connector is not assigned; using the asset-only path")
            elif validate_connector:
                result = warehouse.test(timeout=5.0)
                if not result.ok:
                    raise RuntimeError(f"Warehouse connector check failed: {result.message}")
                print(f"Connector {warehouse.key} passed in {result.latency_ms or 0} ms")

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
