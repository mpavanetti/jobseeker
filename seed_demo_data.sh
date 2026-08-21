#!/usr/bin/env bash
set -Eeuo pipefail

ACTION="${1:-seed}"
DEMO_PREFIX="${DEMO_PREFIX:-demo}"
JENKINS_URL="${JENKINS_URL:-http://localhost:8080}"
JENKINS_USER="${JENKINS_USER:-${JENKINS_ADMIN_ID:-jobseeker}}"
JENKINS_TOKEN="${JENKINS_TOKEN:-${JENKINS_ADMIN_PASSWORD:-jobseeker}}"
DB_USER="${JOBSEEKER_DB_USER:-mysql}"
DB_PASSWORD="${JOBSEEKER_DB_PASSWORD:-mysql}"
DB_NAME="${JOBSEEKER_DB_NAME:-jobseeker}"
DEMO_SLEEP_SECONDS="${DEMO_SLEEP_SECONDS:-900}"
DEMO_BLOCKER_COUNT="${DEMO_BLOCKER_COUNT:-5}"

JENKINS_URL="${JENKINS_URL%/}"
COOKIE_JAR="$(mktemp)"
TMP_DIR="$(mktemp -d)"

if [[ ! "$DEMO_PREFIX" =~ ^[A-Za-z0-9._-]+$ ]]; then
  echo "DEMO_PREFIX may only contain letters, numbers, dots, underscores, and hyphens." >&2
  exit 2
fi

cleanup_tmp() {
  rm -f "$COOKIE_JAR"
  rm -rf "$TMP_DIR"
}
trap cleanup_tmp EXIT

usage() {
  cat <<EOF
Usage: $0 [seed|--cleanup]

Creates a repeatable demo dataset for screenshots:
  - Jenkins demo jobs with success, failure, running, queued, disabled, and not-built states.
  - JobSeeker Python SDK sample jobs that import jobseeker and write live TMF rows.
  - TMF/TMF error database history across past dates, statuses, dimensions, and environments.
  - JobSeeker-created-date metadata so Available Jobs sorts newest created jobs first.

Environment overrides:
  DEMO_PREFIX=$DEMO_PREFIX
  JENKINS_URL=$JENKINS_URL
  JENKINS_USER=$JENKINS_USER
  JENKINS_TOKEN=<hidden>
  JOBSEEKER_DB_USER=$DB_USER
  JOBSEEKER_DB_PASSWORD=<hidden>
  JOBSEEKER_DB_NAME=$DB_NAME
  DEMO_SLEEP_SECONDS=$DEMO_SLEEP_SECONDS
  DEMO_BLOCKER_COUNT=$DEMO_BLOCKER_COUNT
EOF
}

if [[ "$ACTION" == "-h" || "$ACTION" == "--help" ]]; then
  usage
  exit 0
fi

urlencode() {
  python3 -c 'import sys, urllib.parse; print(urllib.parse.quote(sys.argv[1], safe=""))' "$1"
}

jenkins_path() {
  local name="$1"
  local encoded
  encoded="$(urlencode "$name")"
  printf 'job/%s' "$encoded"
}

jenkins_crumb() {
  local crumb_json
  crumb_json="$(curl -sS -u "$JENKINS_USER:$JENKINS_TOKEN" -c "$COOKIE_JAR" "$JENKINS_URL/crumbIssuer/api/json")"
  CRUMB_FIELD="$(python3 -c 'import json,sys; print(json.load(sys.stdin)["crumbRequestField"])' <<<"$crumb_json")"
  CRUMB_VALUE="$(python3 -c 'import json,sys; print(json.load(sys.stdin)["crumb"])' <<<"$crumb_json")"
}

jenkins_post() {
  local path="$1"
  local body_file="${2:-}"
  local content_type="${3:-}"
  local url="$JENKINS_URL/$path"
  local args=(-sS -o /tmp/jobseeker-demo-jenkins-response.txt -w '%{http_code}' -u "$JENKINS_USER:$JENKINS_TOKEN" -b "$COOKIE_JAR" -H "$CRUMB_FIELD: $CRUMB_VALUE" -X POST)

  if [[ -n "$content_type" ]]; then
    args+=(-H "Content-Type: $content_type")
  fi

  if [[ -n "$body_file" ]]; then
    args+=(--data-binary "@$body_file")
  fi

  curl "${args[@]}" "$url"
}

delete_job_if_exists() {
  local job_name="$1"
  local path
  path="$(jenkins_path "$job_name")"
  jenkins_post "$path/lastBuild/stop" >/dev/null || true
  jenkins_post "$path/doDelete" >/dev/null || true
}

create_job_config() {
  local job_name="$1"
  local description="$2"
  local command="$3"
  local disabled="$4"
  local cron="${5:-}"
  local config_file="$TMP_DIR/$job_name.xml"

  if [[ -n "$cron" ]]; then
    triggers="<triggers><hudson.triggers.TimerTrigger><spec>$cron</spec></hudson.triggers.TimerTrigger></triggers>"
  else
    triggers="<triggers/>"
  fi

  cat > "$config_file" <<XML
<?xml version='1.1' encoding='UTF-8'?>
<project>
  <description>$description</description>
  <keepDependencies>false</keepDependencies>
  <properties/>
  <scm class="hudson.scm.NullSCM"/>
  <canRoam>true</canRoam>
  <disabled>$disabled</disabled>
  <blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding>
  <blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding>
  $triggers
  <concurrentBuild>false</concurrentBuild>
  <builders>
    <hudson.tasks.Shell>
      <command><![CDATA[$command]]></command>
    </hudson.tasks.Shell>
  </builders>
  <publishers/>
  <buildWrappers/>
</project>
XML

  printf '%s' "$config_file"
}

create_job() {
  local job_name="$1"
  local description="$2"
  local command="$3"
  local disabled="$4"
  local cron="${5:-}"
  local config_file status path

  config_file="$(create_job_config "$job_name" "$description" "$command" "$disabled" "$cron")"
  path="createItem?name=$(urlencode "$job_name")"
  status="$(jenkins_post "$path" "$config_file" 'application/xml')"

  case "$status" in
    200|201|302|303) printf 'Created Jenkins job %-36s %s\n' "$job_name" "HTTP $status" ;;
    *) printf 'Failed to create Jenkins job %s: HTTP %s\n' "$job_name" "$status" >&2; return 1 ;;
  esac
}

stage_python_sdk_runtime() {
  local source="application/third_party/python/jobseeker_sdk"
  local target="repository/python/lib/jobseeker-sdk"

  if [[ ! -f "$source/pyproject.toml" ]]; then
    echo "JobSeeker Python SDK package not found at $source." >&2
    return 1
  fi

  rm -rf "$target"
  mkdir -p "$target"
  tar -C "$source" --exclude='__pycache__' --exclude='*.pyc' --exclude='build' --exclude='*.egg-info' -cf - . | tar -C "$target" -xf -
}

python_sdk_agent_command() {
  cat <<'SH'
set -e
export JOBSEEKER_PYTHON="${JOBSEEKER_PYTHON:-python3}"
export JOBSEEKER_PYTHON_SDK="/php/repository/python/lib/jobseeker-sdk"
export JOBSEEKER_RUNTIME_LIBS="$WORKSPACE/.jobseeker-runtime-libs"

rm -rf "$JOBSEEKER_RUNTIME_LIBS"
"$JOBSEEKER_PYTHON" -m pip install --quiet --disable-pip-version-check --target "$JOBSEEKER_RUNTIME_LIBS" "$JOBSEEKER_PYTHON_SDK"
export PYTHONPATH="$JOBSEEKER_RUNTIME_LIBS:${PYTHONPATH:-}"

"$JOBSEEKER_PYTHON" - <<'PY'
import os

from jobseeker import JobSeeker


environment = os.environ.get("JOBSEEKER_SAMPLE_ENV", "LOCAL")
job_name = os.environ.get("JOB_NAME") or "jobseeker-sdk-agent-sample"

with JobSeeker(environment=environment, job=job_name) as js:
    with js.task("Sample SDK Agent Job", "DW_Master") as tmf:
        rows = tmf.context("rows", cast=int, default=3)

        for index in range(1, rows + 1):
            print("Agent sample processed row {}/{}".format(index, rows))
            tmf.progress(total=rows, processed=index, msg="Agent sample processed {} of {} rows".format(index, rows))

        tmf.finish(total=rows, processed=rows, msg="JobSeeker SDK sample completed on Jenkins agent")

print("JobSeeker SDK agent sample complete")
PY
SH
}

python_sdk_docker_command() {
  cat <<'SH'
set -e
export JOBSEEKER_PYTHON_SDK="/php/repository/python/lib/jobseeker-sdk"

test -f "$JOBSEEKER_PYTHON_SDK/pyproject.toml" || { echo "JobSeeker SDK package is missing at $JOBSEEKER_PYTHON_SDK"; exit 1; }
command -v docker >/dev/null || { echo "Docker runtime selected, but docker is not available on this Jenkins agent."; exit 127; }

JOBSEEKER_DOCKER_CONTEXT="$WORKSPACE/jobseeker-sdk-demo-docker-context"
rm -rf "$JOBSEEKER_DOCKER_CONTEXT"
mkdir -p "$JOBSEEKER_DOCKER_CONTEXT/jobseeker-sdk"
cp -R "$JOBSEEKER_PYTHON_SDK/." "$JOBSEEKER_DOCKER_CONTEXT/jobseeker-sdk/"

cat > "$JOBSEEKER_DOCKER_CONTEXT/main.py" <<'PY'
import os

from jobseeker import JobSeeker


environment = os.environ.get("JOBSEEKER_SAMPLE_ENV", "LOCAL")
job_name = os.environ.get("JOB_NAME") or "jobseeker-sdk-docker-sample"

with JobSeeker(environment=environment, job=job_name) as js:
    with js.task("Sample SDK Docker Job", "DW_Master") as tmf:
        rows = tmf.context("rows", cast=int, default=4)
        midpoint = max(1, rows // 2)

        print("Docker sample heartbeat at {} of {} rows".format(midpoint, rows))
        tmf.progress(total=rows, processed=midpoint, msg="Docker sample heartbeat")
        tmf.finish(total=rows, processed=rows, msg="JobSeeker SDK sample completed in Docker")

print("JobSeeker SDK Docker sample complete")
PY

tar -C "$JOBSEEKER_DOCKER_CONTEXT" -cf - . | docker run --rm -i \
  --network host \
  -e JOB_NAME -e BUILD_NUMBER -e BUILD_ID \
  -e JOBSEEKER_DB_HOST -e JOBSEEKER_DB_PORT -e JOBSEEKER_DB_USER -e JOBSEEKER_DB_PASSWORD -e JOBSEEKER_DB_NAME \
  -e JOBSEEKER_SAMPLE_ENV="${JOBSEEKER_SAMPLE_ENV:-LOCAL}" \
  python:3.12-slim \
  sh -lc 'set -e
    mkdir -p /tmp/jobseeker-context
    tar -C /tmp/jobseeker-context -xf -
    rm -rf /tmp/jobseeker-runtime-libs
    PIP_ROOT_USER_ACTION=ignore python -m pip install --quiet --disable-pip-version-check --target /tmp/jobseeker-runtime-libs /tmp/jobseeker-context/jobseeker-sdk
    export PYTHONPATH="/tmp/jobseeker-runtime-libs:/tmp/jobseeker-context:${PYTHONPATH:-}"
    python /tmp/jobseeker-context/main.py'
SH
}

trigger_job() {
  local job_name="$1"
  local status
  status="$(jenkins_post "$(jenkins_path "$job_name")/build?delay=0sec")"

  case "$status" in
    200|201|302|303) printf 'Triggered Jenkins job %-34s %s\n' "$job_name" "HTTP $status" ;;
    *) printf 'Failed to trigger Jenkins job %s: HTTP %s\n' "$job_name" "$status" >&2; return 1 ;;
  esac
}

demo_jobs() {
  cat <<EOF
$DEMO_PREFIX-stg-customer-ingest
$DEMO_PREFIX-dim-product-refresh
$DEMO_PREFIX-fact-sales-refresh
$DEMO_PREFIX-dw-orders-quality
$DEMO_PREFIX-dm-marketing-sync
$DEMO_PREFIX-python-sdk-agent
$DEMO_PREFIX-python-sdk-docker
$DEMO_PREFIX-notbuilt-new-pipeline
$DEMO_PREFIX-disabled-legacy-import
$DEMO_PREFIX-queued-backfill
EOF

  local index
  for index in $(seq 1 "$DEMO_BLOCKER_COUNT"); do
    printf '%s-running-reconcile-%02d\n' "$DEMO_PREFIX" "$index"
  done
}

seed_database() {
  python3 - "$DEMO_PREFIX" <<'PY' | docker compose exec -T mariadb mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME"
import datetime
import sys

prefix = sys.argv[1]
now = datetime.datetime.now().replace(microsecond=0)
jobs = [
    (f"{prefix}-stg-customer-ingest", "STG_CUSTOMER_DW", "PROD"),
    (f"{prefix}-dim-product-refresh", "DIM_PRODUCT_DW", "PROD"),
    (f"{prefix}-fact-sales-refresh", "FACT_SALES_DW", "PROD"),
    (f"{prefix}-dw-orders-quality", "DW_ORDERS", "QA"),
    (f"{prefix}-dm-marketing-sync", "DM_MARKETING", "DEV"),
    (f"{prefix}-notbuilt-new-pipeline", "STG_NEW_PIPELINE", "DEV"),
    (f"{prefix}-disabled-legacy-import", "DIM_LEGACY_DW", "QA"),
]
statuses = ["ready", "ready", "ready", "warning", "error", "running", "ready", "warning"]
events = [
    "Extract source files",
    "Validate schema",
    "Load staging tables",
    "Build dimensions",
    "Refresh fact aggregates",
    "Publish downstream marts",
    "Quality gate review",
    "Archive operational logs",
]

def q(value):
    if value is None:
        return "NULL"
    return "'" + str(value).replace("\\", "\\\\").replace("'", "''") + "'"

print("START TRANSACTION;")
print(f"DELETE FROM tmf_error WHERE tmf_id LIKE {q(prefix + '-%')} OR job_name LIKE {q(prefix + '-%')};")
print(f"DELETE FROM tmf WHERE interface_id LIKE {q(prefix + '-%')} OR instance_id LIKE {q(prefix + '-%')} OR job_name LIKE {q(prefix + '-%')};")

rows = []
errors = []
record_id = 1
for job_index, (job_name, dimension, environment) in enumerate(jobs):
    for run_index in range(8):
        status = statuses[(job_index + run_index) % len(statuses)]
        days_ago = job_index * 9 + run_index * 4
        start = now - datetime.timedelta(days=days_ago, hours=(job_index + run_index) % 7, minutes=run_index * 3)
        duration_minutes = 5 + ((job_index * 4 + run_index * 7) % 70)
        last = start + datetime.timedelta(minutes=duration_minutes)
        total = 25000 + job_index * 8000 + run_index * 1750
        processed = total
        warning = "0"
        distinct_errors = 0
        reprocess = 0
        msg = f"{events[run_index]} completed for {environment}."

        if status == "error":
            processed = max(0, total - (1500 + run_index * 300))
            distinct_errors = 1
            reprocess = 1
            msg = f"{events[run_index]} failed after processing {processed} rows."
        elif status == "warning":
            processed = max(0, total - (250 + run_index * 100))
            warning = "1"
            msg = f"{events[run_index]} finished with data quality warnings."
        elif status == "running":
            processed = int(total * 0.62)
            last = now - datetime.timedelta(minutes=run_index + 3)
            msg = f"{events[run_index]} is still running."

        instance_id = f"{prefix}-tmf-{record_id:03d}"
        rows.append((
            f"{prefix}-if-{record_id:03d}", status, job_name, reprocess, events[run_index], dimension,
            environment, str(total), str(processed), last.strftime("%Y-%m-%d %H:%M:%S"),
            f"00:{duration_minutes // 60:02d}:{duration_minutes % 60:02d}", distinct_errors, warning,
            f"etl-node-{(job_index % 3) + 1:02d}", "demo.operator", instance_id,
            start.strftime("%Y-%m-%d %H:%M:%S"), msg
        ))

        if distinct_errors:
            errors.append((instance_id, job_name, last.strftime("%Y-%m-%d %H:%M:%S"), "Validation", dimension, msg, 9000 + record_id))

        record_id += 1

columns = "interface_id,status,job_name,reprocess,event_text,dimension,environment,records_total,records_processed,last_activity,running_time,distict_errors,warnings,hostname,username,instance_id,start_time,msg"
print(f"INSERT INTO tmf ({columns}) VALUES")
print(",\n".join("(" + ",".join(q(value) for value in row) + ")" for row in rows) + ";")

if errors:
    print("INSERT INTO tmf_error (tmf_id,job_name,moment,type,origin,message,code) VALUES")
    print(",\n".join("(" + ",".join(q(value) for value in row) + ")" for row in errors) + ";")

print("COMMIT;")
PY
}

seed_job_creation_dates() {
  python3 - "$DEMO_PREFIX" "$DEMO_BLOCKER_COUNT" <<'PY'
import datetime
import json
import os
import sys

prefix = sys.argv[1]
blockers = int(sys.argv[2])
path = os.path.join("application", "cache", "job_creation_dates.json")
now = datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0)
jobs = [
    f"{prefix}-stg-customer-ingest",
    f"{prefix}-dim-product-refresh",
    f"{prefix}-fact-sales-refresh",
    f"{prefix}-dw-orders-quality",
    f"{prefix}-dm-marketing-sync",
    f"{prefix}-python-sdk-agent",
    f"{prefix}-python-sdk-docker",
    f"{prefix}-notbuilt-new-pipeline",
    f"{prefix}-disabled-legacy-import",
    f"{prefix}-queued-backfill",
]
jobs.extend(f"{prefix}-running-reconcile-{index:02d}" for index in range(1, blockers + 1))

try:
    with open(path, "r", encoding="utf-8") as handle:
        data = json.load(handle)
except (FileNotFoundError, json.JSONDecodeError):
    data = {}

data = {key: value for key, value in data.items() if not key.startswith(prefix + "-")}
for index, job_name in enumerate(jobs):
    created_at = now - datetime.timedelta(days=index * 3 + 1, hours=index % 5)
    data[job_name] = created_at.isoformat()

os.makedirs(os.path.dirname(path), exist_ok=True)
with open(path, "w", encoding="utf-8") as handle:
    json.dump(data, handle, indent=4)
PY
}

cleanup_demo() {
  echo "Cleaning demo TMF rows and Jenkins jobs for prefix '$DEMO_PREFIX'..."
  jenkins_crumb
  while IFS= read -r job_name; do
    delete_job_if_exists "$job_name"
  done < <(demo_jobs)

  docker compose exec -T mariadb mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" <<SQL
DELETE FROM tmf_error WHERE tmf_id LIKE '${DEMO_PREFIX}-%' OR job_name LIKE '${DEMO_PREFIX}-%';
DELETE FROM tmf WHERE interface_id LIKE '${DEMO_PREFIX}-%' OR instance_id LIKE '${DEMO_PREFIX}-%' OR job_name LIKE '${DEMO_PREFIX}-%';
SQL

  python3 - "$DEMO_PREFIX" <<'PY'
import json
import os
import sys

prefix = sys.argv[1]
path = os.path.join("application", "cache", "job_creation_dates.json")
try:
    with open(path, "r", encoding="utf-8") as handle:
        data = json.load(handle)
except (FileNotFoundError, json.JSONDecodeError):
    data = {}

data = {key: value for key, value in data.items() if not key.startswith(prefix + "-")}
if data:
    with open(path, "w", encoding="utf-8") as handle:
        json.dump(data, handle, indent=4)
elif os.path.exists(path):
    os.unlink(path)
PY
}

seed_demo() {
  echo "Seeding demo data with prefix '$DEMO_PREFIX'..."
  cleanup_demo
  jenkins_crumb
  stage_python_sdk_runtime

  local sdk_agent_command
  local sdk_docker_command
  sdk_agent_command="$(python_sdk_agent_command)"
  sdk_docker_command="$(python_sdk_docker_command)"

  create_job "$DEMO_PREFIX-stg-customer-ingest" "Demo success job: customer staging load." "echo 'Loading customer staging data'; exit 0" false "H/15 * * * *"
  create_job "$DEMO_PREFIX-dim-product-refresh" "Demo success job: product dimension refresh." "echo 'Refreshing product dimension'; exit 0" false "H 2 * * *"
  create_job "$DEMO_PREFIX-fact-sales-refresh" "Demo failing job: sales fact refresh." "echo 'Sales fact validation failed'; exit 1" false "H 4 * * 1-5"
  create_job "$DEMO_PREFIX-dw-orders-quality" "Demo success job: data warehouse order quality checks." "echo 'Order quality checks passed'; exit 0" false "H/30 * * * *"
  create_job "$DEMO_PREFIX-dm-marketing-sync" "Demo manual job: marketing data mart sync." "echo 'Marketing mart sync'; exit 0" false ""
  create_job "$DEMO_PREFIX-python-sdk-agent" "Demo Python SDK job: writes TMF rows through the bundled jobseeker package on the Jenkins agent." "$sdk_agent_command" false ""
  create_job "$DEMO_PREFIX-python-sdk-docker" "Demo Python SDK job: writes TMF rows through the bundled jobseeker package inside Docker." "$sdk_docker_command" false ""
  create_job "$DEMO_PREFIX-notbuilt-new-pipeline" "Demo not-built job: newly configured pipeline." "echo 'First build has not been triggered yet'; exit 0" false ""
  create_job "$DEMO_PREFIX-disabled-legacy-import" "Demo disabled job: retired legacy import." "echo 'Legacy import disabled'; exit 0" true "@weekly"

  for index in $(seq 1 "$DEMO_BLOCKER_COUNT"); do
    create_job "$(printf '%s-running-reconcile-%02d' "$DEMO_PREFIX" "$index")" "Demo running job: occupies an executor for screenshots." "echo 'Long reconciliation started'; sleep $DEMO_SLEEP_SECONDS; echo 'Long reconciliation finished'" false ""
  done
  create_job "$DEMO_PREFIX-queued-backfill" "Demo queued job: waits while demo running jobs occupy executors." "echo 'Queued backfill started'; sleep 120; echo 'Queued backfill finished'" false ""

  trigger_job "$DEMO_PREFIX-stg-customer-ingest"
  trigger_job "$DEMO_PREFIX-dim-product-refresh"
  trigger_job "$DEMO_PREFIX-fact-sales-refresh"
  trigger_job "$DEMO_PREFIX-dw-orders-quality"

  for index in $(seq 1 "$DEMO_BLOCKER_COUNT"); do
    trigger_job "$(printf '%s-running-reconcile-%02d' "$DEMO_PREFIX" "$index")"
  done
  trigger_job "$DEMO_PREFIX-queued-backfill"

  seed_database
  seed_job_creation_dates

  trigger_job "$DEMO_PREFIX-python-sdk-agent"
  trigger_job "$DEMO_PREFIX-python-sdk-docker"

  echo "Demo data is ready. Open Dashboard, Job List, Job Creation, and TMF for screenshots."
  echo "Run '$0 --cleanup' to remove the demo dataset."
}

case "$ACTION" in
  seed)
    seed_demo
    ;;
  --cleanup|cleanup)
    cleanup_demo
    ;;
  *)
    usage >&2
    exit 2
    ;;
esac