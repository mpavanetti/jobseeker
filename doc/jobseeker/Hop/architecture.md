# Apache Hop on JobSeeker — Architecture

JobSeeker executes Apache Hop workflows (`.hwf`) and pipelines (`.hpl`) as
first-class JobSeeker jobs. Jenkins stays the scheduler, orchestrator and
trigger; Hop is only an execution engine, exactly like the Python interpreter or
the Talend runtime already are.

This note records the design, the contracts it depends on, and the trade-offs.
It is written against **Apache Hop 2.19.0**.

---

## 1. Design goals

1. **Jenkins remains the control plane.** A Hop job is a normal Jenkins
   freestyle job with a shell builder, so it inherits scheduling, environment
   parameters, per-environment agents, timeouts, e-mail notification, the
   Pipelines DAG editor, promotion, and the Dashboard for free.
2. **Hop is not a silo.** Hop projects live in `repository/`, are editable in
   the bundled OpenVSCode Server, resolve Data Assets through the same
   `manifest.json`, resolve credentials through the same connector runtime, and
   report to the same Transaction Monitoring Framework.
3. **Two interchangeable execution engines**, chosen per job, behind one
   command-line contract, so the same project runs unchanged on a Jenkins
   worker and on Kubernetes. (A third, `agent`, ran `hop-run.sh` from a Hop
   installation on the worker. The platform never provides one, so it could
   only ever fail; it was removed rather than left as a trap.)
4. **Secrets never land in project files.** Container metadata uses
   `${VARIABLES}` backed by a run-scoped 0600 file. Hop Server needs resolved
   metadata, so JobSeeker writes it under an exclusive execution lock with mode
   0600 and removes it as soon as the synchronous request completes.

---

## 2. Component map

```
                    ┌──────────────────────────────────────────┐
   schedule/trigger │              Jenkins                     │
   ────────────────▶│  freestyle job → one shell builder       │
                    └───────────────┬──────────────────────────┘
                                    │ jobseeker-hop run …
                    ┌───────────────▼──────────────────────────┐
                    │      jobseeker-hop  (Python SDK)         │
                    │  • materialise connectors                │
                    │  • generate Hop metadata + variables     │
                    │  • open/close the TMF instance           │
                    │  • parse Hop result → record counts      │
                    └───┬───────────────┬──────────────────┬───┘
        engine=container│                       engine=server│
                        ▼                                  ▼
             docker run jobseeker-hop            POST /hop/execPipeline
             (ephemeral, resource-               to the long-lived
              limited, K8s Job seam)             hop-server container
```

Everything below the `jobseeker-hop` box reads the same three inputs: a Hop
**project folder**, an **environment config file** holding JobSeeker variables,
and a **run configuration** name.

---

## 3. Repository layout

```
repository/hop/
  projects/<job-or-project-slug>/     ← a real Apache Hop project
      project-config.json             ← Hop's own project descriptor
      .jobseeker-hop.json             ← JobSeeker manifest (entry file, engine, …)
      metadata/
        rdbms/                        ← generated from JobSeeker connectors
        pipeline-run-configuration/
        workflow-run-configuration/
      pipelines/*.hpl
      workflows/*.hwf
  runs/                               ← run-scoped generated variables (0600, transient)
```

`repository/` is already mounted into `php`, `jenkins`, every Jenkins agent and
OpenVSCode, so a project uploaded through the browser is immediately visible to
the executor and to the IDE. No extra volume, no copy step, no second source of
truth.

`.jobseeker-hop.json` is the JobSeeker-owned manifest:

```json
{
  "schema_version": 1,
  "project": "sales-etl",
  "entry_file": "workflows/main.hwf",
  "run_config": "local",
  "engine": "container",
  "log_level": "Basic",
  "parameters": { "BATCH_SIZE": "1000" },
  "connectors": ["jobseeker-mariadb"],
  "assets": ["customer-reference"]
}
```

It is written by the Job Creation form and read by the runner, so the Jenkins
shell command stays short and a project can be re-run by hand identically.

---

## 4. The `jobseeker-hop` runner

Shipped inside the existing `jobseeker-runtime` Python package
(`application/third_party/python/jobseeker_sdk`), so it is installed on the
Jenkins controller, on every agent, and inside the Hop image by the same
mechanism that already installs `jobseeker-asset` and `jobseeker-connector`.

```
jobseeker-hop run  --project repository/hop/projects/sales-etl \
                   --file workflows/main.hwf \
                   --engine container|server \
                   --environment DEV --job sales-etl-daily \
                   --run-config local --param BATCH_SIZE=1000
jobseeker-hop inspect --project …      # entry files, parameters, manifest, as JSON
jobseeker-hop metadata --environment DEV --job sales-etl-daily
                                        # preview scoped connector metadata
jobseeker-hop scaffold --project …     # create an empty Hop project skeleton
jobseeker-hop server-status            # Hop Server health for the UI
jobseeker-hop server-catalog --directory …   # build the Hop Server database catalog

# From inside a Hop workflow's "Execute a shell script" action, reporting into
# the very TMF row this build opened (${JOBSEEKER_TMF_INSTANCE_ID} is a Hop
# variable the runner publishes):
jobseeker-hop tmf heartbeat --records-processed 5000 --message "stage 2 of 4"

```

### 4.1 What a run does

1. **Connectors.** Calls `materialize_connectors()` — the same code path Python
   and shell jobs use — to fetch the environment-and-job-scoped catalog from
   `/connector-runtime` with the worker's bearer token.
2. **Hop metadata generation.** Each relational connector becomes
   `metadata/rdbms/<connector-key>.json` in a *generated overlay*, never in the
   uploaded project:

   ```json
   { "name": "jobseeker-mariadb",
     "rdbms": { "MYSQL": { "hostname": "${JOBSEEKER_CONN_JOBSEEKER_MARIADB_HOST}",
                           "port":     "${JOBSEEKER_CONN_JOBSEEKER_MARIADB_PORT}",
                           "username": "${JOBSEEKER_CONN_JOBSEEKER_MARIADB_USER}",
                           "password": "${JOBSEEKER_CONN_JOBSEEKER_MARIADB_PASSWORD}",
                           "databaseName": "…", "pluginId": "MYSQL" } } }
   ```

   A Hop transform therefore selects the connection **by its JobSeeker connector
   key**. Rotating a credential in the Connectors screen changes the next run
   with no edit to the `.hpl` file.
3. **Variables.** One Hop environment config file is written with:
   - `JOBSEEKER_ENVIRONMENT`, `JOBSEEKER_JOB_NAME`, `JOBSEEKER_BUILD_NUMBER`,
     `JOBSEEKER_TMF_INSTANCE_ID`
   - `JOBSEEKER_ASSET_<KEY>` for every Data Asset in scope, resolved to the path
     the engine will actually see
   - `JOBSEEKER_CONN_<KEY>_{HOST,PORT,DATABASE,USER,PASSWORD}` for every connector
   - every `--param` and every Context value requested by the manifest

   The file is written 0600 under `repository/hop/runs/<build>/` and removed in a
   `finally` block. Hop's `--environment` mechanism reads it; no secret is ever a
   process argument, so nothing shows up in `ps` or in the Jenkins console.
4. **Execute** through the selected engine.
5. **TMF.** The runner opens a TMF instance before Hop starts and closes it
   after, using the SDK's existing transport. Hop's own execution result
   (lines read/written/errors) is parsed out of the run output and recorded as
   `records_total` / `records_processed`, so a Hop job appears in Transaction
   Monitoring next to Python jobs with no work from the job author. A workflow
   that wants finer granularity calls `jobseeker-hop tmf heartbeat` from a Hop
   "Execute a shell script" action and updates the same row, exactly as Python
   jobs use the SDK directly.

### 4.2 Engines

| Engine | How it runs | Measured start-up | When to use |
| --- | --- | --- | --- |
| `container` (default) | `docker run --rm apache/hop:2.19.0` on the Jenkins worker, project streamed into a volume, CPU/memory capped with the same limits the Python and shell Docker runtimes use | ~50 s on a 1-vCPU box | Isolation, reproducibility, no Hop install on the worker. Maps 1:1 to a Kubernetes Job. |
| `server` | `POST /hop/execPipeline` or `/hop/execWorkflow` on the long-lived `hop-server` container, then the log and counters are read back from `/hop/pipelineStatus` | ~0.3 s | Short pipelines run often, where a cold JVM per build dominates the runtime. |

Both exit non-zero on failure, print the Hop log to stdout, and are
therefore indistinguishable to Jenkins, to the Pipelines DAG, and to the e-mail
notification templates.

Five details the server engine needs, all found by running it:

* `/hop/execPipeline` answers with a bare "executed successfully" and no
  metrics, so the runner reads the log and the row counters back from
  `/hop/pipelineStatus` and appends them. Without this a server run would report
  zero rows to TMF and show almost nothing in the Jenkins console. The status is
  fetched on failure too — that is exactly when the log matters.
* `/hop/execWorkflow` answers `OK` even for a workflow that failed. The run is
  therefore judged on the status document (`Finished (with errors)`, per-action
  `nr_errors`), not on the servlet's reply; without that a broken workflow was a
  green Jenkins build.
* A workflow status carries **no** per-action `log_text` at all: its whole log
  arrives only as `<logging_string>`, base64 of a gzip stream. Decoding it is
  the difference between Transaction Monitoring saying "Apache Hop reported
  errors" and saying which action failed and why.
* `/hop/execPipeline` returns an empty `<id>`, and a name-only status query
  answers with the wrong run — the *first* still registered for a pipeline, the
  *last* for a workflow. The runner therefore snapshots the registered ids
  before executing and diffs afterwards, then addresses status and removal by
  that exact id. Removing by name would delete a run somebody started from the
  Hop GUI.
* Hop Server's metadata folder is process-global. The runner therefore holds a
  repository-backed exclusive lock from catalog generation through cleanup,
  replaces stale generated connections before every run, deletes them
  afterwards, and restores any catalog an operator published (§ 5.3). That
  deliberately trades server parallelism for environment and credential
  isolation; the container engine remains the parallel default.
* The server runs a *file*, not a registered project, so it never sets
  `PROJECT_HOME`. The runner passes it, because
  `${PROJECT_HOME}/pipelines/x.hpl` is how the Hop GUI writes every
  intra-project reference; without it such a workflow fails before it starts.

### 4.3 JDBC drivers

The official `apache/hop` image bundles only the permissively licensed drivers:
PostgreSQL and MS SQL Server work out of the box, MySQL, MariaDB and Oracle do
not. Rather than require a private registry for a custom image, the runner
derives the missing driver ids from the connectors actually in scope and asks
Hop 2.19's own installer to fetch them on container start
(`HOP_DRIVERS_DOWNLOAD`). That costs about a minute per build and accepts the
vendor licence, which the console says plainly.

Building `docker/hop_image` and pointing `JOBSEEKER_HOP_IMAGE` at it removes
both the download and the licence prompt, because the drivers are baked in. The
runner skips the install whenever the image is not the stock one, and
`JOBSEEKER_HOP_DRIVERS` overrides the decision either way.

### 4.4 Kubernetes seam

`container` is the seam. The engine only needs "start a Hop container with this
project, these env vars, and these limits, then collect the exit code and the
log". The Docker implementation is one class; a `KubernetesEngine` that creates
a `batch/v1` Job in the environment's namespace implements the same three
methods. Nothing above the engine boundary — project layout, metadata
generation, variables, TMF — changes. Resource requests come from the same
`containerCpuLimit` / `containerMemoryLimitMb` form fields, so a spec can never
reserve more than the host that backs it.

---

## 5. Containers

### 5.1 `jobseeker-hop` image (`docker/hop_image`)

`FROM apache/hop:2.19.0` plus:

- Python 3 and the `jobseeker-runtime` SDK, so `jobseeker-asset`,
  `jobseeker-connector` and `jobseeker-hop` are callable from inside a Hop
  workflow's shell actions.
- JDBC drivers installed at build time with Hop's own
  `hop driver install --accept-license`, so runs need no network.
- The runtime user re-numbered to the uid that owns `repository/` (1000 by
  default), because the upstream image runs as 501 and anything it wrote back
  would be unreadable to PHP, Jenkins and OpenVSCode. Only the paths Hop writes
  to are re-owned: a recursive `chown` of `/opt/hop` rewrites every file in the
  Hop install and adds a ~2.5 GB duplicate layer for no benefit.
- `docker/hop/entrypoint-extension.sh`, wired through Hop's supported
  `HOP_CUSTOM_ENTRYPOINT_EXTENSION_SHELL_FILE_PATH` hook. It can build a
  persistent server connector catalog for non-JobSeeker API clients when
  explicitly enabled; normal jobs use their ephemeral scoped catalog instead.

Because `docker compose build` writes to the host daemon while Jenkins jobs talk
to the `docker-runtime` (Docker-in-Docker) daemon, a locally built image is
invisible to jobs until it is copied across. `scripts/load-hop-image.sh` does
that; skipping it simply falls back to the public image and the runtime driver
install.

The stock Apache Hop entrypoint contract is respected exactly
(`HOP_PROJECT_FOLDER`, `HOP_PROJECT_NAME`, `HOP_ENVIRONMENT_NAME`,
`HOP_ENVIRONMENT_CONFIG_FILE_NAME_PATHS`, `HOP_RUN_CONFIG`, `HOP_FILE_PATH`,
`HOP_RUN_PARAMETERS`, `HOP_LOG_LEVEL`), so the image can be swapped for the
upstream one and only the SDK conveniences are lost.

### 5.2 `hop-server` service

The same image with `HOP_FILE_PATH` empty, which makes the upstream entrypoint
start `hop-server.sh` on port 8080 instead of running one file. It mounts
`repository/` read-write, joins the `internal` network, and is reachable at
`http://hop-server:8080/`. Authentication is on by default
(`HOP_SERVER_USER` / `HOP_SERVER_PASS`), and its published port binds to
`127.0.0.1:8181` by default. Both address and port are configurable.

### 5.3 Publishing from the Apache Hop GUI

The Hop GUI can publish a pipeline or workflow straight to `hop-server`, and
people do. Such a run never touches Jenkins, so three things that a Jenkins job
gets for free have to be arranged deliberately.

**It has to be visible.** The Apache Hop screen reconciles `/hop/status` into
`hop_server_executions` and opens a Transaction Monitoring row per execution,
keyed by the id Hop assigned, so the ingestion is idempotent however often the
screen polls. A run JobSeeker started itself is recognised by a claim file the
runner leaves on the repository volume and is never given a second row — and
because JobSeeker also removes its own runs from the server's memory by id, the
panel is mostly what a person started from the GUI.

**It has to be schedulable.** The GUI publishes by uploading a zip of whatever
the designer has open, so the run has no file in the repository at all — its
only trace is `zip:file:///…/export_<uuid>.zip!Matheus.hwf`. Pointing the Hop
Server's JVM temp folder at the repository volume puts that archive somewhere
JobSeeker can read, and **Import** turns it into a real project: the workflow or
pipeline moves into the folder its extension belongs in, JobSeeker writes the
descriptors and the `local` run configuration, and Hop's own execution
configuration is dropped because the environment comes from the Jenkins job. The
archive path is resolved with `realpath` and refused unless it is inside that
folder.

**It has to reach its data.** There is no runner to build a scoped connector
catalog, so an operator publishes one from the screen. Only connectors scoped to
*every job* in the environment are offered — the server is shared, so a
connector scoped to one Jenkins job must not become readable by anyone who can
reach the Hop GUI — and a secret held in a cloud vault is never copied onto the
server volume. The catalog lives in `hop/server/published/rdbms` and is mirrored
into the folder Hop Server reads; a Jenkins run that borrows that folder puts it
back on the way out. Hop reads a database document per run, so publishing takes
effect immediately.

**It has to have a driver.** The Hop image bundles only the permissively
licensed JDBC drivers. Which of the rest it carries depends on how the image was
built, so the app records what the published connections *need* and the server
decides what is *missing* by asking `hop driver list` on start-up — it is the
only party that can see its own image. Installs go to the first folder on
`HOP_SHARED_JDBC_FOLDERS`, on the repository volume, so a driver is downloaded
once rather than on every rebuild; the image's own folder stays on the list, or
its bundled drivers would disappear from the scan.

**It has to resolve `${JOBSEEKER_*}`.** A GUI-started run has no project and no
environment registered, so a project-scoped environment file would never be
read. The variables are published as Hop **system** variables in
`hop-config.json` instead, in a configuration folder on the repository volume
that the container seeds from the image on first start. Hop reads system
variables at start-up, so republishing them needs a restart of `hop-server`.

**Hop Web is deliberately not part of this.** Projects are edited as text in the
bundled OpenVSCode Server and designed in the desktop Hop GUI; a second large
container that duplicates the desktop tool earns nothing.

---

## 5.4 Seeing the shape of a job

Hop stores the designer's layout inside the `.hwf`/`.hpl` — every action or
transform carries its coordinates, and every hop carries whether it is enabled
and, for a workflow, whether it follows success or failure. That is enough to
redraw the same picture the Hop GUI draws, so JobSeeker does: on the Apache Hop
screen for any project file or Hop Server run, and on Job Execution for a Hop
job. While the Hop Server still holds a run, each box is labelled with the rows
that transform has moved and whether it is still working, which turns a picture
of the design into a picture of the run.

The boxes are far wider than Hop's 32px icon, so the coordinates are spread
until nothing collides and then de-overlapped outright — an overlapping pair
hides the hop between them, which is the one thing the picture exists to show.
Nothing interprets a transform's configuration: an unknown type still appears as
a box, which is what keeps this working against a Hop version we have not seen.

---

## 6. Job Creation integration

`Job Creation → ETL Tool → Apache Hop` selects the integration and reveals:

- **Upload** (`.zip` project, or a bare `.hpl` / `.hwf` that JobSeeker wraps in a
  scaffolded project), **Repository path**, or **Sample project**.
- **Entry file** — populated from the project's own pipelines and workflows.
- **Engine**, **run configuration**, **log level**, **parameters**.
- Projects are editable directly under `repository/hop/projects/` in the bundled
  OpenVSCode workspace, or in the desktop Apache Hop GUI against the same folder.

**Use in job** is the other direction. Both the project catalog and the Hop
Server execution list carry it, and it opens this form with the project, the
exact file and the engine already chosen — so a run somebody was happy with in
the Hop GUI becomes a scheduled Jenkins job without retyping any of it.

The generated Jenkins builder is a single shell step:

```sh
export JOBSEEKER_REPOSITORY_ROOT=…            # data assets + connectors, as today
jobseeker-hop run --project '/php/repository/hop/projects/sales-etl' \
                  --file 'workflows/main.hwf' --engine 'container' \
                  --environment "$JOBSEEKER_ENVIRONMENT" \
                  --job "$JOBSEEKER_JOB_NAME"
```

Short, readable, and re-runnable by hand — unlike the long generated blocks the
Python and Talend Docker paths need, because all of the complexity moved into
the runner where it can be unit-tested.

---

## 7. Data model

Three tables keep the catalog, its Jenkins usage links and the shared server's
history queryable without walking every Jenkins configuration:

```
hop_projects:
id, project_key, name, description, environment, storage_path,
entry_file, run_config, engine, log_level, parameters_json,
is_active, created_at, updated_at, owner

hop_project_jobs:
id, project_key, job_name, environment, entry_file, engine,
created_at, updated_at

hop_server_executions:
id, execution_id, name, display_name, kind, status, state, environment,
project_key, filename, source, job_name, tmf_instance_id,
records_total, records_processed, errors, error_logged, log_text,
started_at, ended_at, first_seen_at, updated_at
```

`hop_server_executions` exists because the Hop Server's own memory cannot be
built on: it forgets a finished execution after its object timeout and loses
every one of them when it restarts. `execution_id` is the id Hop assigned, which
makes the TMF ingestion idempotent — one Hop run is one TMF row however often
the screen polls. `display_name` is the file name rather than the `<name>`
inside it, because the Hop GUI leaves that as "New workflow" unless someone
renames it by hand, and every run of the same file would otherwise pile up under
one meaningless label.

Deliberately **not** added: a Hop-specific dataset table (Data Assets already
covers it), a Hop-specific credential table (Connectors already covers it), a
run-history table for *Jenkins* Hop jobs (TMF and Jenkins build history already
cover those), and a Hop scheduler (Jenkins is the scheduler). The registry is a
convenience index over the filesystem, which stays authoritative — deleting a
row never deletes a project, and a project dropped into
`repository/hop/projects/` is discovered.

---

## 8. Trade-offs and rejected alternatives

**Hop Server as the only executor — rejected.** It is a single shared JVM: one
runaway pipeline degrades every other job, and per-job CPU/memory limits are
impossible. It is offered as an engine because a warm JVM is genuinely faster
for short pipelines, but the default is an ephemeral container.

**A Hop plugin written in Java — rejected for now.** A native JobSeeker
transform/action would be the most elegant way to expose Data Assets and TMF
inside the Hop GUI, but it adds a Maven/Java build to a PHP+Python repository
and pins us to a Hop API version. Shell actions calling the existing CLIs give
80 % of the value at 5 % of the cost. The plugin remains a clean follow-up: the
metadata generation and the variables contract would not change.

**Writing credentials straight into `metadata/rdbms/*.json` — rejected.** The
files would sit in a Git-tracked project folder and in the OpenVSCode workspace.
Variable indirection keeps them out of both.

**A separate Hop scheduler — rejected.** The user requirement is explicit and it
is also the right call: two schedulers means two places to look when something
did not run.

**An `agent` engine running `hop-run.sh` on the worker — removed.** It needed a
Hop installation the platform never provides, so in practice it could only fail
with "Apache Hop is not installed on this worker". The container engine covers
the same ground with no host dependency, and an engine that cannot work is worse
than no engine at all.

**Apache Hop Web — removed.** It duplicated the desktop Hop GUI in a ~3 GB
container, needed its own authentication and its own writable config, and earned
nothing that the desktop GUI publishing to `hop-server` does not already do.

**A background reconciler daemon for Hop Server runs — rejected.** The Apache
Hop screen reconciles while it is open and stops when the tab is hidden. A
daemon would be a third thing to run and to supervise for a feature whose whole
audience is a person looking at that screen; the Hop Server keeps a run in
memory long enough for the next visit to pick it up.

---

## 9. Contracts this design depends on

| Contract | Source |
| --- | --- |
| `HOP_PROJECT_FOLDER`, `HOP_ENVIRONMENT_CONFIG_FILE_NAME_PATHS`, `HOP_RUN_CONFIG`, `HOP_FILE_PATH`, `HOP_CUSTOM_ENTRYPOINT_EXTENSION_SHELL_FILE_PATH` | `docker/resources/load-and-execute.sh`, Apache Hop 2.19 |
| `{"variables":[{"name","value","description"}]}` environment config files | `org.apache.hop.core.config.plugin.ConfigFile` |
| `metadata/rdbms/<name>.json` shape and plugin ids (`MYSQL`, `MARIADB`, `POSTGRESQL`, `MSSQLNATIVE`, `ORACLE`, `GENERIC`) | `plugins/databases/*`, Hop integration tests |
| `POST /hop/execPipeline`, `/hop/execWorkflow`, `/hop/status` (form body; the servlet base delegates POST to the same handler) | `org.apache.hop.www.*Servlet` |
| `/hop/pipelineStatus`, `/hop/workflowStatus`, `/hop/removePipeline`, `/hop/removeWorkflow` accept `name` **and** `id`, and `<logging_string>` is base64 of a gzip stream | `org.apache.hop.www.*Servlet`, `HttpUtil.encodeBase64ZipString` |
| `hop-config.json` `variables[]` are system variables, resolved for a run with no project | `org.apache.hop.core.config.HopConfig` |
| `.hwf` `<action><xloc>/<yloc>` and `<hops><hop><evaluation>/<unconditional>`; `.hpl` `<transform><GUI><xloc>` and `<order><hop>` | the file formats the Hop GUI writes |

Each is asserted by `scripts/test-hop-integration.js` so an upstream change
fails a test rather than a production run.

## 10. How this was verified

| Suite | Scope | Result |
| --- | --- | --- |
| `npm run test:hop` | Static contracts: entry point variables, file formats, PHP/Python agreement, security properties, sample projects | in the default `npm test` chain |
| `npm run test:hop:e2e` | Real Apache Hop runs through both engines against the live stack: variables, Data Assets, connectors as Hop database connections, credential redaction, TMF success and failure rows carrying Hop's own error text, a server workflow resolving its own pipelines, run claiming and de-registration, run hygiene | 48 checks |
| `npm run test:hop:ui` | Playwright against both screens: layout compared with an existing ETL screen, engine cards, the Hop Server execution panel, the grouped log viewer, the Job Creation Hop panel, source modes, sample picker, engine-dependent controls, console and network errors | 48 checks |
| `npm run test:hop:jobs:e2e` | Live upload and Job Creation endpoints, atomic and hostile archive handling, generated Jenkins configuration, two real Jenkins builds, an externally published Hop Server run reaching the screen and Transaction Monitoring exactly once, catalog and deletion cleanup | 45 checks |

The end-to-end suites need Docker and the running stack, so they sit outside the
default chain. The live suites clean up the TMF rows, Jenkins jobs, and scratch
projects they create.
