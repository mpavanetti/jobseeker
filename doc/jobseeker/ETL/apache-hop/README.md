# Apache Hop

JobSeeker runs Apache Hop workflows (`.hwf`) and pipelines (`.hpl`) as ordinary
Jenkins jobs. Jenkins keeps the schedule, the environment parameter, the
timeout, the notifications and the promotion path; Hop is only the engine that
executes the file, exactly as Python and Talend already are.

Open **Extract Transform Load > Apache Hop** to see the project catalog and
which engines are available. Jobs themselves are created in **Job Creation**, so
there is one place a Jenkins job is born whatever runtime it uses.

## Creating a Hop job

In Job Creation choose **Execute Linux Command > ETL tool > Apache Hop**, then
pick where the project comes from:

Source | Use it for
--- | ---
**Upload** | A project archive exported from the Hop GUI, or a single `.hwf` / `.hpl`. A bare file is wrapped in the minimum project structure Hop needs. An archive that wraps everything in one folder is flattened automatically.
**Sample project** | One of the bundled starters, copied into `repository/hop/projects/<job>` and editable afterwards.
**Repository path** | A Hop project already in the JobSeeker repository, shared by several jobs.

Then choose the **workflow or pipeline** to run (press **Detect** to list what
the project actually contains), the **execution engine**, the **run
configuration**, the **log level**, and any **parameters** as `NAME=VALUE` lines.

Uploaded projects live under `repository/hop/projects/`, which is mounted into
the application, Jenkins, every agent and OpenVSCode — so a project uploaded in
the browser is immediately editable in the bundled IDE and visible to the
executor, with no copy step.

## Execution engines

Engine | How it runs | Choose it when
--- | --- | ---
**Container** (default) | One ephemeral Apache Hop container per build on the Jenkins worker, with the CPU and memory limits set on the job | You want isolation and reproducibility, and no Hop install on the worker
**Hop Server** | The shared long-lived `hop-server` container over its REST interface | Short pipelines run often, where a cold JVM per build dominates the runtime

The container engine starts a run in roughly a minute on a small host; the Hop
Server answers in well under a second. The trade-off is isolation: the server is
shared and a run is not resource-capped. JobSeeker serializes server executions,
writes the scoped connector catalog with mode `0600` for the synchronous request,
and removes it afterwards so environments cannot race or retain credentials.
Use the container engine when builds need parallelism or process isolation.

Start the optional server with:

```bash
docker compose --profile hop up -d hop-server
```

## Connectors become Hop database connections

Every [connector](../connectors) in scope for the job's environment is published
to the run as a Hop **relational database connection named after its connector
key**. A `Table input` transform simply selects `jobseeker-mariadb`; nothing in
the `.hpl` refers to a host or a password.

The generated connection references `${JOBSEEKER_CONN_<KEY>_PASSWORD}` and
similar variables. Their values travel in a run-scoped file with mode `0600`
that is deleted when the build ends, so a credential never lands in a project
folder, a Git checkout, the OpenVSCode workspace, or the Jenkins console.
Rotating a credential in the Connectors screen changes the next run with no edit
to any Hop file.

MySQL, MariaDB and Oracle JDBC drivers are not shipped in the public Apache Hop
image. JobSeeker installs the ones a job's connectors need when the container
starts, which costs about a minute per build. To remove that cost, build the
JobSeeker Hop image once and point jobs at it:

```bash
docker compose --profile hop build hop-server
./scripts/load-hop-image.sh
# then set JOBSEEKER_HOP_IMAGE=jobseeker-hop:local in .env
```

## Data Assets and Context become Hop variables

Every [Data Asset](../data-assets) in scope is published as
`${JOBSEEKER_ASSET_<KEY>}`, already resolved to the path the selected engine
will see. An asset registered as `customer-reference` is used directly in a file
name field as `${JOBSEEKER_ASSET_CUSTOMER_REFERENCE}`.

Every run also gets `${JOBSEEKER_ENVIRONMENT}`, `${JOBSEEKER_JOB_NAME}`,
`${JOBSEEKER_BUILD_NUMBER}` and `${JOBSEEKER_TMF_INSTANCE_ID}`, plus any
[Context](../contextsettings) values the project's manifest requests and every
parameter set on the job.

## Transaction Monitoring

A Hop run opens a TMF instance before Hop starts and closes it afterwards, with
the row counts read from Hop's own transform metrics. A Hop job therefore
appears in **Transaction Monitoring** next to Python jobs with no work from the
job author: the environment, the engine, the rows read and written, and the
failure reason are all recorded.

A workflow that wants finer granularity can report into the same row from an
"Execute a shell script" action, because the run's instance id is a Hop
variable:

```sh
jobseeker-hop tmf heartbeat --records-processed 5000 --message "stage 2 of 4"
```

## Starter projects

Sample | What it demonstrates
--- | ---
`platform-hello` | The JobSeeker runtime context and Data Asset variables. Needs no connector and no asset, so it runs on a fresh stack.
`connector-inventory` | Reading through the seeded `jobseeker-mariadb` connector as a Hop database connection.
`parameterised-load` | Job parameters used inside SQL, with a row-count guard that aborts the build so the failure path is exercised.

## Editing and designing projects

Hop files are XML, so a project opens in the bundled **OpenVSCode Server** like
any other repository workspace. For graphical work, point the desktop Apache Hop
GUI at `repository/hop/projects/` — the same folder the executor reads — and add
a **Hop Server** metadata object for `http://localhost:8181` to run there.

You do not need to open Hop to see what a job does: click a workflow or pipeline
name on the **Apache Hop** screen and JobSeeker draws the canvas from the layout
already stored in the file, hops coloured by success and failure. **Job
Execution** draws the same canvas for a Hop job and, while the Hop Server still
holds the run, labels each box with the rows it has moved and whether it is
still working.

To take one back into the desktop GUI, use the download beside a run, a project
file, or the whole project as a zip &mdash; it is the same XML Hop wrote, so it
opens unchanged.

## Runs published straight from the Hop GUI

A pipeline you run against the shared Hop Server from the Hop GUI never touches
Jenkins. The **Apache Hop** screen still shows it, with its log and its row
counts, and opens a Transaction Monitoring row for it so a failure is not
invisible — including the Hop error lines that say what to fix. Press **Use in
job** on that row and Job Creation opens with the project, the file and the
engine already chosen.

Two things such a run does not get automatically, because there is no JobSeeker
runner in front of it:

* **Connections.** Press **Publish connections to the Hop Server** on the same
  screen. Only connectors scoped to *every job* in the environment are offered,
  because the server is shared, and a secret held in a cloud vault is never
  copied onto it. This takes effect immediately.
* **JDBC drivers.** The Apache Hop image bundles only the permissively licensed
  drivers, so PostgreSQL and SQL Server (including Azure SQL) work out of the
  box while MySQL, MariaDB and Oracle do not. Publishing records which drivers
  the published connections need; the Hop Server asks its own catalog what it is
  missing on the next start and installs only that, into a folder on the
  repository volume so the download happens once.
* **Platform variables.** The same action publishes `${JOBSEEKER_ENVIRONMENT}`,
  `${JOBSEEKER_ASSET_*}` and the non-secret connector values as Hop system
  variables. Hop reads those when it starts, so run
  `docker compose --profile hop restart hop-server` afterwards.

A Jenkins job needs none of this: it is handed a run-scoped catalog and gives it
back when the build ends, and it restores whatever you published on its way out.

If a run shows as **Published as an export**, the Hop GUI uploaded a zip of
whatever you had open rather than running a file under
`repository/hop/projects/`. Press **Import** on that row: JobSeeker copies the
archive into a real project, writes the descriptors Hop needs, and the run then
offers **Use in job** like any other. (Hop's own execution configuration is
dropped — the environment comes from the Jenkins job, not from what was selected
on your laptop.) Publishing a file that already lives in the repository skips
this step entirely.

## Settings

See [.env.example](../../../../.env.example) for the full list. The ones that
matter most are `JOBSEEKER_HOP_ENABLED`, `JOBSEEKER_HOP_IMAGE` and
`JOBSEEKER_HOP_SERVER_PORT`.

The design, the contracts it relies on, and the trade-offs are recorded in
[the architecture note](../../Hop/architecture.md).
