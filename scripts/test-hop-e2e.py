#!/usr/bin/env python3
"""End-to-end check of the JobSeeker Apache Hop integration.

Runs real Apache Hop workflows and pipelines through both engines - the
ephemeral container and the long-lived Hop Server - and asserts what a static
test cannot: that Hop actually starts, that the JobSeeker variables and
connector-derived database connections resolve inside the engine, that record
counts come back, and that both the success and the failure path land in
Transaction Monitoring carrying Hop's own error text.

This is deliberately outside the default `npm test` chain: it needs Docker and
the running stack. Run it with:

    python3 scripts/test-hop-e2e.py

Environment (all optional, defaults match docker-compose):
    JOBSEEKER_DB_HOST / _PORT / _USER / _PASSWORD / _NAME
    JOBSEEKER_CONNECTOR_API_URL, JOBSEEKER_CONNECTOR_API_TOKEN
    JOBSEEKER_HOP_IMAGE      apache/hop:2.19.0 by default
    JOBSEEKER_HOP_E2E_ROOT   repository root to use (default ./repository)
    JOBSEEKER_HOP_NETWORK    Docker network for the run container. On a Jenkins
                             worker the default "host" already sees the platform
                             services; from a development box outside the
                             Compose network, set it to jobseeker_internal so
                             the connector's host name resolves.

The stock apache/hop image ships no MySQL/MariaDB JDBC driver, so a
connector-backed run installs one on container start. That costs about a minute
per run here; building docker/hop_image and pointing JOBSEEKER_HOP_IMAGE at it
removes the download.
"""

from __future__ import annotations

import io
import json
import os
import shutil
import subprocess
import sys
import time
import uuid
from contextlib import redirect_stdout

REPOSITORY_DEFAULT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "repository")
SAMPLES = os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
    "application", "third_party", "hop", "samples",
)
SDK_SOURCE = os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
    "application", "third_party", "python", "jobseeker_sdk", "src",
)

sys.path.insert(0, SDK_SOURCE)

from jobseeker import hop  # noqa: E402

PASSED: list = []
FAILED: list = []


def check(name: str, condition: bool, detail: str = "") -> None:
    if condition:
        PASSED.append(name)
        print("  PASS  %s" % name)
    else:
        FAILED.append((name, detail))
        print("  FAIL  %s%s" % (name, (" - " + detail) if detail else ""))


def stage(title: str) -> None:
    print("\n== %s ==" % title)


def install_sample(repository_root: str, sample: str, project_key: str) -> str:
    target = os.path.join(repository_root, "hop", "projects", project_key)
    os.makedirs(os.path.dirname(target), exist_ok=True)
    if os.path.isdir(target):
        shutil.rmtree(target)
    shutil.copytree(os.path.join(SAMPLES, sample), target)
    return target


def run_hop(**kwargs):
    """Run one Hop execution, capturing the console the way Jenkins would see it."""

    buffer = io.StringIO()
    try:
        with redirect_stdout(buffer):
            code = hop.run(**kwargs)
    except Exception as error:  # noqa: BLE001 - the test reports, it does not crash
        return 99, buffer.getvalue() + "\n[runner exception] %s" % error
    return code, buffer.getvalue()


def _connect():
    import mysql.connector  # type: ignore

    return mysql.connector.connect(
        host=os.environ.get("JOBSEEKER_DB_HOST", "127.0.0.1"),
        port=int(os.environ.get("JOBSEEKER_DB_PORT", "3306")),
        user=os.environ.get("JOBSEEKER_DB_USER", "mysql"),
        password=os.environ.get("JOBSEEKER_DB_PASSWORD", "mysql"),
        database=os.environ.get("JOBSEEKER_DB_NAME", "jobseeker"),
    )


def tmf_errors(job_name: str):
    """The tmf_error rows a failed run filed, newest first."""

    try:
        connection = _connect()
    except ImportError:
        return []
    try:
        cursor = connection.cursor()
        cursor.execute(
            "SELECT type, origin, message FROM tmf_error WHERE job_name = %s ORDER BY id DESC",
            (job_name,),
        )
        rows = cursor.fetchall()
        cursor.close()
        return rows
    finally:
        connection.close()


def tmf_rows(job_name: str):
    try:
        import mysql.connector  # type: ignore
    except ImportError:
        return None

    connection = mysql.connector.connect(
        host=os.environ.get("JOBSEEKER_DB_HOST", "127.0.0.1"),
        port=int(os.environ.get("JOBSEEKER_DB_PORT", "3306")),
        user=os.environ.get("JOBSEEKER_DB_USER", "mysql"),
        password=os.environ.get("JOBSEEKER_DB_PASSWORD", "mysql"),
        database=os.environ.get("JOBSEEKER_DB_NAME", "jobseeker"),
    )
    try:
        cursor = connection.cursor()
        cursor.execute(
            "SELECT instance_id, status, records_total, records_processed, environment, event_text, dimension, msg "
            "FROM tmf WHERE job_name = %s ORDER BY id DESC",
            (job_name,),
        )
        rows = cursor.fetchall()
        cursor.close()
        return rows
    finally:
        connection.close()


def delete_tmf_rows(job_name: str) -> None:
    try:
        import mysql.connector  # type: ignore
    except ImportError:
        return
    connection = mysql.connector.connect(
        host=os.environ.get("JOBSEEKER_DB_HOST", "127.0.0.1"),
        port=int(os.environ.get("JOBSEEKER_DB_PORT", "3306")),
        user=os.environ.get("JOBSEEKER_DB_USER", "mysql"),
        password=os.environ.get("JOBSEEKER_DB_PASSWORD", "mysql"),
        database=os.environ.get("JOBSEEKER_DB_NAME", "jobseeker"),
    )
    try:
        cursor = connection.cursor()
        cursor.execute("DELETE FROM tmf_error WHERE job_name = %s", (job_name,))
        cursor.execute("DELETE FROM tmf WHERE job_name = %s", (job_name,))
        connection.commit()
        cursor.close()
    finally:
        connection.close()


def adopt_stack_connector_settings() -> None:
    """Use the connector endpoint and token the running stack actually uses.

    The SDK's defaults are the ones a fresh checkout ships, and a deployment
    that has set its own ``JOBSEEKER_CONNECTOR_API_TOKEN`` in .env will answer
    the SDK's default with HTTP 401. That surfaces here as "connection not
    found" from Hop, which points at the wrong thing entirely, so the values
    are read back from the container that serves the API.
    """

    if not os.environ.get("JOBSEEKER_CONNECTOR_API_URL"):
        os.environ["JOBSEEKER_CONNECTOR_API_URL"] = "http://127.0.0.1/connector-runtime"
    if os.environ.get("JOBSEEKER_CONNECTOR_API_TOKEN"):
        return

    container = compose_container("php")
    if not container:
        return
    try:
        token = subprocess.check_output(
            ["docker", "exec", container, "printenv", "JOBSEEKER_CONNECTOR_API_TOKEN"],
            text=True,
            stderr=subprocess.DEVNULL,
        ).strip()
    except (OSError, subprocess.CalledProcessError):
        return

    if token:
        os.environ["JOBSEEKER_CONNECTOR_API_TOKEN"] = token


def compose_container(service: str) -> str:
    """Find a running container for one Compose service.

    ``docker compose ps`` only sees the project named after this directory, and
    a stack is often brought up under a different project name. Falling back to
    the Compose service label finds it whatever the project is called.
    """

    for command in (
        ["docker", "compose", "ps", "-q", service],
        ["docker", "ps", "-q", "--filter", "label=com.docker.compose.service=" + service],
    ):
        try:
            found = subprocess.check_output(command, text=True, stderr=subprocess.DEVNULL).strip()
        except (OSError, subprocess.CalledProcessError):
            continue
        if found:
            return found.splitlines()[0].strip()
    return ""


def configure_local_stack_network() -> None:
    """Join run containers to the same Compose network as the live app.

    Jenkins talks to Docker-in-Docker and can use ``host`` because that daemon
    itself is attached to the application network. This test talks to the host
    daemon, so it discovers nginx's network instead of requiring a
    project-name-specific environment variable.
    """

    if os.environ.get("JOBSEEKER_HOP_NETWORK"):
        return
    try:
        container = compose_container("nginx")
        if not container:
            return
        networks = json.loads(subprocess.check_output(
            ["docker", "inspect", "--format", "{{json .NetworkSettings.Networks}}", container],
            text=True,
            stderr=subprocess.DEVNULL,
        ))
        names = [name for name in networks if name.endswith("_internal")]
        if names:
            os.environ["JOBSEEKER_HOP_NETWORK"] = sorted(names)[0]
    except (OSError, subprocess.CalledProcessError, ValueError):
        pass


def main() -> int:
    repository_root = os.path.abspath(os.environ.get("JOBSEEKER_HOP_E2E_ROOT", REPOSITORY_DEFAULT))
    os.makedirs(os.path.join(repository_root, "data-assets"), exist_ok=True)
    os.environ.setdefault("JOBSEEKER_DB_HOST", "127.0.0.1")
    os.environ.setdefault("JOBSEEKER_CONNECTOR_API_URL", "http://127.0.0.1/connector-runtime")
    os.environ.setdefault("JOBSEEKER_CONNECTOR_API_TOKEN", "jobseeker-local-connector-token")
    os.environ.setdefault("JOBSEEKER_HOP_SERVER_URL", "http://127.0.0.1:8181")
    os.environ["JOBSEEKER_REPOSITORY_ROOT"] = repository_root
    adopt_stack_connector_settings()
    configure_local_stack_network()
    suffix = uuid.uuid4().hex[:6]

    print("JobSeeker Apache Hop end-to-end")
    print("repository root : %s" % repository_root)
    print("hop image       : %s" % os.environ.get("JOBSEEKER_HOP_IMAGE", hop.DEFAULT_IMAGE))
    print("docker network  : %s" % os.environ.get("JOBSEEKER_HOP_NETWORK", "host"))

    created_jobs = []
    # Only judge this run's own scratch directories, not anything a previous
    # debugging session deliberately kept.
    runs_before = set(os.listdir(os.path.join(repository_root, "hop", "runs"))) \
        if os.path.isdir(os.path.join(repository_root, "hop", "runs")) else set()

    # -- 1. Project model ---------------------------------------------------
    stage("Project discovery and scaffolding")
    project_key = "e2e-platform-hello-%s" % suffix
    project = install_sample(repository_root, "platform-hello", project_key)
    described = hop.HopProject.locate(project).describe()
    check("project is discovered", described["has_project_config"])
    check("workflow is listed", "workflows/main.hwf" in described["workflows"])
    check("pipeline is listed", "pipelines/platform-variables.hpl" in described["pipelines"])
    check("manifest declares the entry file", described["manifest"]["entry_file"] == "workflows/main.hwf")

    # A bare .hpl upload must still become a runnable project.
    scratch = os.path.join(repository_root, "hop", "projects", "e2e-bare-%s" % suffix)
    if os.path.isdir(scratch):
        shutil.rmtree(scratch)
    bare = hop.scaffold_project(scratch, "e2e-bare")
    shutil.copyfile(
        os.path.join(SAMPLES, "platform-hello", "pipelines", "platform-variables.hpl"),
        os.path.join(scratch, "pipelines", "platform-variables.hpl"),
    )
    check("scaffolded project is a Hop project", os.path.isfile(os.path.join(scratch, "project-config.json")))
    check("scaffolded project has a local run configuration",
          os.path.isfile(os.path.join(scratch, "metadata", "pipeline-run-configuration", "local.json")))
    check("single runnable file needs no explicit selection",
          hop.HopProject(scratch).resolve_entry("") == "pipelines/platform-variables.hpl")
    redaction_secret = "hop-secret-%s" % suffix
    redaction_variables = [{"name": "JOBSEEKER_CONN_TEST_PASSWORD", "value": redaction_secret}]
    redaction_console = io.StringIO()
    with redirect_stdout(redaction_console):
        redaction_code, redaction_output = hop.HopEngine._stream(
            [sys.executable, "-c", "print(%r)" % redaction_secret],
            redact_variables=redaction_variables,
        )
    check("engine output is redacted before it reaches Jenkins",
          redaction_code == 0
          and redaction_secret not in redaction_output
          and redaction_secret not in redaction_console.getvalue()
          and "********" in redaction_output)
    del bare

    # -- 2. Container engine, variables and TMF ------------------------------
    stage("Container engine: variables, Data Assets and Transaction Monitoring")
    job_name = "hop-e2e-platform-%s" % suffix
    created_jobs.append(job_name)
    os.environ["BUILD_NUMBER"] = "1"
    started = time.time()
    code, output = run_hop(
        project_path=project,
        entry_file="workflows/main.hwf",
        engine="container",
        environment="DEV",
        job=job_name,
        repository_root=repository_root,
        with_connectors=False,
        with_tmf=True,
        memory_limit_mb=1024,
    )
    elapsed = time.time() - started
    check("Hop workflow succeeds in a container", code == 0, "exit code %s" % code)
    check("Hop actually started", "Start of workflow execution" in output)
    check("JOBSEEKER_ENVIRONMENT reaches the pipeline", "environment = DEV" in output)
    check("JOBSEEKER_JOB_NAME reaches the pipeline", "job_name = %s" % job_name in output)
    check("JOBSEEKER_BUILD_NUMBER reaches the pipeline", "build_number = 1" in output)
    check("a referenced Context Detail reaches the pipeline automatically",
          "custom_context = This is a custom context from jobseeker DEV" in output)
    check("the Data Asset variable is resolved without a literal placeholder",
          "customer_reference_path =" in output
          and "${JOBSEEKER_ASSET_CUSTOMER_REFERENCE}" not in output)
    check("row counters are parsed from Hop", "read 1, written 1, errors 0" in output)

    rows = tmf_rows(job_name)
    if rows is None:
        check("Transaction Monitoring row is written", False, "mysql-connector-python is not installed")
    else:
        check("Transaction Monitoring row is written", len(rows) == 1, "found %d row(s)" % len(rows))
        if rows:
            instance_id, status, records_total, records_processed, environment, event_text, dimension, _msg = rows[0]
            check("TMF run is marked ready", status == "ready", "status=%s" % status)
            check("TMF records the environment", environment == "DEV", "environment=%s" % environment)
            check("TMF names Apache Hop as the event", event_text == "Apache Hop", "event_text=%s" % event_text)
            check("TMF records the engine as the dimension", dimension == "container", "dimension=%s" % dimension)
            check("TMF carries Hop's row counts", str(records_total) == "1" and str(records_processed) == "1",
                  "total=%s processed=%s" % (records_total, records_processed))
            check("the TMF instance id was visible to the pipeline",
                  "tmf_instance_id = %s" % instance_id in output,
                  "the pipeline printed a different instance id")
    print("  (container run took %.1fs)" % elapsed)

    # -- 3. Connectors become Hop database connections ------------------------
    stage("Connectors as Hop database connections")
    connector_key = "e2e-connector-inventory-%s" % suffix
    connector_project = install_sample(repository_root, "connector-inventory", connector_key)
    connector_job = "hop-e2e-connector-%s" % suffix
    created_jobs.append(connector_job)

    os.environ["BUILD_NUMBER"] = "2"
    code, output = run_hop(
        project_path=connector_project,
        entry_file="workflows/main.hwf",
        engine="container",
        environment="DEV",
        job=connector_job,
        repository_root=repository_root,
        with_connectors=True,
        with_tmf=True,
        memory_limit_mb=1024,
    )
    connectors_available = "Hop database connections:" in output
    if not connectors_available:
        print("  SKIP  connector catalog is not reachable from here; connector assertions skipped")
        print("        (set JOBSEEKER_CONNECTOR_API_URL and JOBSEEKER_CONNECTOR_API_TOKEN to cover this path)")
    else:
        check("the connector is exposed as a Hop database connection", "jobseeker-mariadb" in output)
        check("the Hop pipeline reads through the connector", code == 0, "exit code %s" % code)
        check("Hop read rows through the connector",
              "Finished reading query" in output and "read 0, written 0" not in output)
        rows = tmf_rows(connector_job)
        if rows:
            check("the connector run is recorded in TMF", rows[0][1] == "ready", "status=%s" % rows[0][1])

        # The generated Hop database document must never hold a literal
        # credential: the values travel in the 0600 environment config file.
        sentinel = "s3nt1nel-" + suffix
        fake = type("Connector", (), {
            "key": "sentinel-connector",
            "type": "mysql",
            "host": "db.internal",
            "port": 3306,
            "database": "warehouse",
            "username": "reader",
            "password": sentinel,
            "config": {},
        })()
        document = json.dumps(hop.rdbms_metadata(fake))
        check("generated Hop metadata holds no credential", sentinel not in document)
        check("generated Hop metadata references a variable instead",
              "${JOBSEEKER_CONN_SENTINEL_CONNECTOR_PASSWORD}" in document, document[:200])
        variables = hop.build_run_variables(environment="DEV", job="x", connectors=[fake])
        check("the credential travels as a run variable",
              any(item["name"].endswith("_PASSWORD") and item["value"] == sentinel for item in variables))
        check("a leaked credential is redacted from the console",
              sentinel not in hop.redact("password=" + sentinel, variables))

    # -- 4. Hop Server engine --------------------------------------------------
    stage("Hop Server engine")
    server_engine = hop.ServerEngine(
        hop.HopProject(project), os.path.join(repository_root, "hop", "runs"), repository_root, {}
    )
    try:
        status = server_engine.status()
    except Exception:  # noqa: BLE001
        status = {"ok": False}

    if not status.get("ok"):
        print("  SKIP  no Hop Server at %s" % server_engine.base_url)
        print("        start one with: docker compose --profile hop up -d hop-server")
    else:
        server_job = "hop-e2e-server-%s" % suffix
        created_jobs.append(server_job)
        os.environ["BUILD_NUMBER"] = "4"
        code, output = run_hop(
            project_path=project,
            entry_file="pipelines/platform-variables.hpl",
            engine="server",
            environment="DEV",
            job=server_job,
            repository_root=repository_root,
            with_connectors=False,
            with_tmf=True,
        )
        check("the Hop Server runs the pipeline", code == 0, "exit code %s" % code)
        check("the server run returns the Hop log", "Finished processing" in output)
        check("the server run reports row counts", "read 1, written 1, errors 0" in output)

        # A repeat run must report its own counters: the server answers a
        # name-only status query with the first run still registered under that
        # name, so a stale read here would misreport every build after the first.
        os.environ["BUILD_NUMBER"] = "5"
        repeat_job = "hop-e2e-server-repeat-%s" % suffix
        created_jobs.append(repeat_job)
        code, output = run_hop(
            project_path=project,
            entry_file="pipelines/platform-variables.hpl",
            engine="server",
            environment="DEV",
            job=repeat_job,
            repository_root=repository_root,
            with_connectors=False,
            with_tmf=True,
        )
        check("a repeated server run reports fresh counters",
              code == 0 and "read 1, written 1, errors 0" in output and "older run" not in output)
        rows = tmf_rows(repeat_job)
        if rows:
            check("the server run is recorded in TMF with its engine",
                  rows[0][1] == "ready" and rows[0][6] == "server",
                  "status=%s dimension=%s" % (rows[0][1], rows[0][6]))

        # The server uses a process-global metadata folder, so this also proves
        # the runner supplies a scoped connector catalog under its execution
        # lock and removes the resolved credential document afterwards.
        server_connector_job = "hop-e2e-server-connector-%s" % suffix
        created_jobs.append(server_connector_job)
        os.environ["BUILD_NUMBER"] = "6"
        code, output = run_hop(
            project_path=connector_project,
            entry_file="pipelines/tmf-inventory.hpl",
            engine="server",
            environment="DEV",
            job=server_connector_job,
            repository_root=repository_root,
            with_connectors=True,
            with_tmf=True,
        )
        check("the Hop Server reads through a scoped connector", code == 0, "exit code %s" % code)

        # The run's own catalog holds resolved credentials for the length of one
        # synchronous request and must not outlive it. What may remain is the
        # catalog an operator published for pipelines started from the Apache Hop
        # GUI, which the runner borrows the folder from and puts back - so the
        # folder must end up exactly equal to the published one, never more.
        server_rdbms = os.path.join(repository_root, "hop", "server", "metadata", "rdbms")
        published_rdbms = os.path.join(repository_root, "hop", "server", "published", "rdbms")
        retained = sorted(name for name in os.listdir(server_rdbms) if name.endswith(".json")) \
            if os.path.isdir(server_rdbms) else []
        published = sorted(name for name in os.listdir(published_rdbms) if name.endswith(".json")) \
            if os.path.isdir(published_rdbms) else []
        check("the Hop Server retains no connector credential the run created",
              retained == published,
              "left behind: %s (published: %s)" % (", ".join(retained) or "none", ", ".join(published) or "none"))
        if published:
            differing = [
                name for name in published
                if open(os.path.join(server_rdbms, name), "rb").read()
                != open(os.path.join(published_rdbms, name), "rb").read()
            ]
            check("a published catalog survives a Jenkins run unchanged", not differing, ", ".join(differing))

        # A workflow action points at ${PROJECT_HOME}/pipelines/x.hpl, which is
        # how the Hop GUI writes every intra-project reference. The server runs
        # a file rather than a registered project, so the runner has to supply
        # PROJECT_HOME or the reference resolves against the server's own
        # default project and the workflow fails before it starts.
        server_workflow_job = "hop-e2e-server-workflow-%s" % suffix
        created_jobs.append(server_workflow_job)
        os.environ["BUILD_NUMBER"] = "7"
        code, output = run_hop(
            project_path=connector_project,
            entry_file="workflows/main.hwf",
            engine="server",
            environment="DEV",
            job=server_workflow_job,
            repository_root=repository_root,
            with_connectors=True,
            with_tmf=True,
        )
        check("a Hop Server workflow resolves its own pipelines",
              "is invalid, and will not run successfully" not in output,
              "PROJECT_HOME did not reach the server run")
        check("the Hop Server workflow succeeds", code == 0, "exit code %s: %s" % (code, output[-400:]))

        # JobSeeker owns the runs it starts, so it takes them back out of the
        # server's memory and leaves a claim, which is what stops the Apache Hop
        # screen from opening a second TMF row for the same run.
        claims_root = os.path.join(repository_root, "hop", "server", "claims")
        claims = [name for name in os.listdir(claims_root)] if os.path.isdir(claims_root) else []
        check("a server run claims its Hop execution", bool(claims), "no claim file was written")
        registered = hop.ServerEngine(
            hop.HopProject(connector_project), os.path.join(repository_root, "hop", "runs"), repository_root, {}
        )._registered_ids()
        claimed_ids = {name[:-5] for name in claims if name.endswith(".json")}
        check("JobSeeker removes its own runs from the shared server",
              not (claimed_ids & set(registered)),
              "still registered: %s" % sorted(claimed_ids & set(registered)))
        for name in claims:
            if name.endswith(".json"):
                os.unlink(os.path.join(claims_root, name))

    # -- 5. Failure path ------------------------------------------------------
    stage("Failure path")
    failing_job = "hop-e2e-failure-%s" % suffix
    created_jobs.append(failing_job)
    os.environ["BUILD_NUMBER"] = "3"
    code, output = run_hop(
        project_path=connector_project,
        entry_file="pipelines/tmf-inventory.hpl",
        engine="container",
        environment="DEV",
        job=failing_job,
        repository_root=repository_root,
        with_connectors=False,  # the jobseeker-mariadb connection is deliberately absent
        with_tmf=True,
        memory_limit_mb=1024,
    )
    check("a broken Hop pipeline fails the build", code != 0, "exit code %s" % code)
    rows = tmf_rows(failing_job)
    if rows:
        check("the failure is recorded in TMF as an error", rows[0][1] == "error", "status=%s" % rows[0][1])
        # The point of the failure path: somebody reading Transaction Monitoring
        # must see what Hop said, not just that something failed.
        message = str(rows[0][7] or "")
        check("the TMF message carries Hop's own error text",
              "ERROR" in message and "reported errors:" not in message,
              "msg=%s" % message[:200])
        errors = tmf_errors(failing_job)
        check("a tmf_error row is filed against Apache Hop, not Python",
              bool(errors) and errors[0][0] == "Apache Hop",
              "type=%s" % (errors[0][0] if errors else "no row"))
        check("the tmf_error origin names the failing transform",
              bool(errors) and errors[0][1].startswith("Apache Hop"),
              "origin=%s" % (errors[0][1] if errors else "no row"))
    elif rows is not None:
        check("the failure is recorded in TMF as an error", False, "no TMF row was written")

    # -- 6. Generated artefacts are cleaned up --------------------------------
    stage("Run hygiene")
    runs_root = os.path.join(repository_root, "hop", "runs")
    after = set(os.listdir(runs_root)) if os.path.isdir(runs_root) else set()
    own_prefixes = tuple(hop._docker_safe_name(job) + "-" for job in created_jobs)
    leftovers = sorted(name for name in after - runs_before if name.startswith(own_prefixes))
    check("run directories are removed after the build", not leftovers, "left behind: %s" % leftovers)
    check("no credential file survives the run",
          not any(name.endswith(".json") for name in leftovers))

    # -- Cleanup ---------------------------------------------------------------
    stage("Cleanup")
    for job in created_jobs:
        delete_tmf_rows(job)
    for folder in (project, connector_project, scratch):
        shutil.rmtree(folder, ignore_errors=True)
    print("  removed %d TMF job(s) and 3 scratch project(s)" % len(created_jobs))

    print("\n%d passed, %d failed" % (len(PASSED), len(FAILED)))
    for name, detail in FAILED:
        print("  FAILED: %s%s" % (name, (" - " + detail) if detail else ""))
    return 1 if FAILED else 0


if __name__ == "__main__":
    raise SystemExit(main())
