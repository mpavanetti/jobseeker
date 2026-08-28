# JobSeeker

Jenkins ETL scheduler and data pipeline monitoring portal.

JobSeeker is a self-hosted operations portal for Jenkins-backed ETL and batch jobs.

It gives data teams one place to create and trigger Jenkins jobs, schedule recurring runs, monitor execution status, inspect logs, query Transaction Monitoring Framework (TMF) history, and manage runtime configuration for Talend, Python, Bash, and other workloads.

## Highlights

- Create, update, schedule, trigger, stop, inspect, and delete Jenkins jobs from a controlled web UI.
- Bind Jenkins jobs to runtime environments, then filter and inspect that environment across creation, listing, viewing, execution, deletion, TMF, and dashboards. A global environment selector in the top bar keeps those views aligned.
- Schedule jobs with guided single-run, repetitive, tag-based, or custom Jenkins cron expressions.
- Promote Jenkins jobs between environments with dependency discovery, context-variable promotion, artifact folder copy, dry-run previews, and rollback checkpoints.
- Monitor running, queued, successful, failed, disabled, and not-built jobs with Jenkins build history, console output, environment badges, worker-node visibility, and focused environment filters.
- Query TMF records by job, status, environment, date/time range, dimension, event text, and reprocess flag.
- Run Python jobs with the bundled `jobseeker` SDK for TMF logging, context lookup, Data Asset discovery, progress updates, and Jenkins-agent or Docker execution.
- Publish environment-aware Data Assets that unify uploaded datasets, declared inputs, and generated outputs behind stable `jobseeker://` URIs for Python, shell, Talend, and Docker jobs.
- Track processed records, warnings, errors, messages, hostnames, users, and execution timing.
- Manage database settings, generic key-value settings, SMTP settings, email templates, versioned data files, projects, environments, and context variables.
- Publish embedded Power BI, Tableau, Qlik Sense, or iframe dashboards with user and group access control.
- Manage users, roles, uploads, runtime settings, and operational dashboards from the same portal.

## Architecture

JobSeeker is a CodeIgniter 3 PHP application backed by MariaDB and Jenkins.

The Docker stack includes:

- Nginx for the web server.
- PHP-FPM for the application runtime.
- MariaDB for JobSeeker metadata, TMF logs, users, settings, and contexts.
- Jenkins for job execution, scheduling, build history, console output, and job control.

In Docker, JobSeeker sends Jenkins API requests through an authenticated server-side proxy. The browser does not need Jenkins API credentials, and local Docker usage does not require manual Jenkins CORS setup.

## Project Status

Latest branch: `master` beta release.

## Default Access

### JobSeeker users

Role | Login | Password | Description
--- | --- | --- | ---
System Administrator | admin@example.com | 123456 | Full administration access
Developer | developer@example.com | 123456 | Job, file, ETL, dashboard, and monitoring access
Key User | keyuser@example.com | 123456 | Monitoring, dashboard, and log analysis access

### Jenkins user

Login | Password
--- | ---
jobseeker | jobseeker

Change the default users and passwords before using JobSeeker in a shared or production environment.

## Quick Start With Docker

Make sure Docker and Docker Compose are installed.

Clone the repository and start the full stack:
```bash
git clone https://github.com/mpavanetti/jobseeker.git
cd jobseeker

docker compose up -d --build
```

For local overrides, copy the environment template first:

```bash
cp .env.example .env
```

Open JobSeeker at http://localhost/ and Jenkins at http://localhost:8080/login.

The Docker stack configures the database connection, session encryption key, and internal Jenkins API endpoint through [docker-compose.yml](docker-compose.yml).

For a conservative local VM profile, set real values before starting the stack:
```bash
export JOBSEEKER_ENCRYPTION_KEY="replace-with-a-long-random-secret"
export JOBSEEKER_DB_PASSWORD="replace-with-a-database-password"
export JOBSEEKER_MYSQL_ROOT_PASSWORD="replace-with-a-root-password"
export JENKINS_ADMIN_PASSWORD="replace-with-a-jenkins-password-or-token"
export JENKINS_NUM_EXECUTORS="1"
export JOBSEEKER_JENKINS_PUBLIC_URL="http://localhost:8080/"
export JOBSEEKER_JENKINS_DEFAULT_ENVIRONMENT_SLOTS="1"
export JOBSEEKER_JENKINS_ENVIRONMENT_SLOTS="DEV=2,QA=1"
export JOBSEEKER_JENKINS_ENVIRONMENT_AGENTS_ENABLED="false"

docker compose up -d --build
```

Jenkins controller parallelism is controlled by `JENKINS_NUM_EXECUTORS`. The local `.env.example` template uses 1 controller executor so a 4-core development VM has CPU headroom for the application, database, Jenkins controller, and lightweight DEV/QA agents. Increase it for larger shared environments.

JobSeeker also gates build triggers by runtime environment before they reach Jenkins. `JOBSEEKER_JENKINS_DEFAULT_ENVIRONMENT_SLOTS` sets the per-environment default, and `JOBSEEKER_JENKINS_ENVIRONMENT_SLOTS` can override individual environments with comma-separated values such as `DEV=2,QA=1,PROD=2`. This prevents JobSeeker-triggered DEV jobs from consuming the configured PROD capacity.

Executors and slots are not CPU reservations. A Jenkins executor is permission for Jenkins to run one build concurrently on that node. A JobSeeker slot is an application-side trigger gate. If a VM has 4 CPU threads, setting 3 controller executors plus 5 agent executors means Jenkins may try to run 8 builds concurrently, but Docker/Linux will time-slice them unless you also set container CPU limits. For CPU-heavy ETL, keep online executors near the CPU capacity you actually want to spend.

Increasing `JENKINS_NUM_EXECUTORS` adds more parallel worker capacity inside the current Jenkins container; it does not automatically start more worker containers.

### Optional Jenkins Environment Agents

For a lightweight Airflow-worker-style layout, JobSeeker includes optional Jenkins inbound agents per environment. The default stack does not start them. On a small local VM, start only the environments you plan to run, usually DEV and QA:

Set routing in `.env` first:

```bash
JOBSEEKER_JENKINS_ENVIRONMENT_AGENTS_ENABLED=true
```

Then start the local DEV/QA workers and recreate PHP so the routing flag is loaded:

```bash
docker compose --profile jenkins-agents up -d --build --force-recreate php jenkins-agent-dev jenkins-agent-qa
```

For a shared environment where DEV, QA, UAT, and PROD should all have active workers, start the full profile:

```bash
docker compose --profile jenkins-agents up -d --build
```

If UAT/PROD agents were previously started but you want the lighter local profile, stop them:

```bash
docker compose stop jenkins-agent-uat jenkins-agent-prod
```

The profile starts these worker services by default:

Service | Environment | Default label | Executors
--- | --- | --- | ---
`jenkins-agent-dev` | DEV | `jobseeker-env-dev` | `JOBSEEKER_JENKINS_DEV_AGENT_EXECUTORS` or 2
`jenkins-agent-qa` | QA | `jobseeker-env-qa` | `JOBSEEKER_JENKINS_QA_AGENT_EXECUTORS` or 1
`jenkins-agent-uat` | UAT | `jobseeker-env-uat` | `JOBSEEKER_JENKINS_UAT_AGENT_EXECUTORS` or 1
`jenkins-agent-prod` | PROD | `jobseeker-env-prod` | `JOBSEEKER_JENKINS_PROD_AGENT_EXECUTORS` or 1

When `JOBSEEKER_JENKINS_ENVIRONMENT_AGENTS_ENABLED=true`, jobs created or promoted by JobSeeker are assigned to the configured environment label through Jenkins `assignedNode`. Existing unpinned jobs are also reconciled to the selected environment label before they are triggered through JobSeeker. Direct Jenkins UI/API triggers bypass that safety check and use the current Jenkins job configuration.

To enable agent routing in a local `.env`, set:

```bash
JOBSEEKER_JENKINS_ENVIRONMENT_AGENTS_ENABLED=true
```

Then recreate the PHP app container so the setting is loaded:

```bash
docker compose up -d --force-recreate php
```

For a 4-core local development VM, a practical starting point is:

```bash
JENKINS_NUM_EXECUTORS=1
JOBSEEKER_JENKINS_ENVIRONMENT_SLOTS=DEV=2,QA=1
JOBSEEKER_JENKINS_DEV_AGENT_EXECUTORS=2
JOBSEEKER_JENKINS_QA_AGENT_EXECUTORS=1
```

To disable routing, set the same variable to `false` and recreate `php`. The agent containers can still be online, but JobSeeker will stop writing `assignedNode` labels for newly created or promoted jobs.

`JOBSEEKER_JENKINS_ENVIRONMENT_AGENT_LABELS` can override the routing map with comma-separated values such as `DEV=jobseeker-env-dev,QA=jobseeker-env-qa,PROD=jobseeker-env-prod`. The agent container labels can also include aliases, but the routing label must be present on the matching Jenkins node.

Agents use the internal Jenkins TCP agent port by default through `JOBSEEKER_JENKINS_AGENT_TUNNEL=jenkins:50000`. Set `JOBSEEKER_JENKINS_AGENT_WEB_SOCKET=true` if you prefer WebSocket agents and your Jenkins root URL is reachable from the worker containers.

Per-agent parallelism is configured with the agent executor variables, for example:

```bash
JOBSEEKER_JENKINS_DEV_AGENT_EXECUTORS=2
JOBSEEKER_JENKINS_QA_AGENT_EXECUTORS=1
```

After changing an agent executor count, recreate that agent service:

```bash
docker compose --profile jenkins-agents up -d --force-recreate jenkins-agent-dev
```

The Executor Monitor page shows environment trigger slots, online worker nodes, worker executor capacity, queue state, and live executor usage. It also includes an Agent Setup Helper that recommends controller executors, per-environment agent executors, JobSeeker slot limits, and the matching `.env` values for either local Docker/VM deployments or a Kubernetes worker-pod path. Completed build history shows the Jenkins `builtOn` node so you can see where a job ran; the exact executor/core number is only available while the build is live in the Executor Monitor.

To tell where a job ran:

- While it is running, open Executor Monitor and check Live Executor Details. The row shows the Jenkins node and executor number, such as `jobseeker-dev-agent #0`.
- After it finishes, use Job List, Job View, or Full Job Build List. The Worker/Last Worker field comes from Jenkins `builtOn`.
- In Jenkins itself, the build page also exposes `builtOn`; a blank value means it ran on the built-in controller node.

Docker Compose is the recommended installation path because it starts the application, database, and Jenkins execution engine together.

### Runtime Stack

- PHP-FPM 8.3 with the required MySQL and ZIP extensions.
- Nginx 1.29 Alpine serving the CodeIgniter application.
- MariaDB 10.7 for JobSeeker and TMF data.
- Jenkins 2.568.2 LTS with pinned plugins, Docker CLI access, the JobSeeker Python SDK runtime, and optional inbound agent workers.

Frontend assets are managed with npm in [package.json](package.json) and [package-lock.json](package-lock.json). The application still serves legacy AdminLTE 2, Bootstrap 3, and jQuery-era paths under `assets/bower_components`, but that directory is generated and is not committed.

Docker Compose restores those assets automatically through the `assets` service before Nginx starts. To refresh them manually, run:

```bash
docker compose run --rm assets
```

Generated runtime cache files under `application/cache`, including job creation timestamps and promotion rollback checkpoints, are local artifacts and are ignored by Git.

### Inline Python workspace

Docker inline Python jobs can be opened as full projects in the bundled OpenVSCode Server. The workspace includes pinned Python, Poetry, uv, Ruff, mypy, BasedPyright, pytest/coverage, and debugpy tooling, plus Git/SSH and native build tools for packages that compile extensions. The selected stable Python minor is installed on demand and reused, while project dependencies are isolated in `.venv`.

Generated workspaces include `pyproject.toml`, `poetry.lock`, `.dockerignore`, pytest configuration and a smoke test, Ruff/mypy configuration, launch profiles, and VS Code tasks for setup, checks, tests, and coverage. The bootstrap task recreates an incompatible or prerelease virtual environment and installs the complete development dependency group. The lock file and editor/Docker project files are durable and participate in environment promotion; virtual environments and caches do not.

The generated Dockerfile installs locked project and test dependencies at image-build time, runs the job as an unprivileged user, and marks the image so Jenkins does not resolve the same dependencies again when the container starts. Custom Dockerfiles retain the runtime fallback; a custom image that already installs its project dependencies can opt out of that fallback with `JOBSEEKER_DEPENDENCIES_PREINSTALLED=1`.

Docker Python jobs enable **Run pytest before Python execution** by default. Jenkins discovers `test_*.py` and `*_test.py`, runs pytest as a blocking gate, and presents **Python tests** and **Python execution** as separate console sections. A failed test prevents the entry point from starting. The setting is stored per job and can be disabled when a deployment should run without tests; projects with no discovered tests continue normally.

Start or rebuild the editor with:

```bash
docker compose --profile openvscode up -d --build openvscode
```

The default Compose configuration binds the editor to localhost and protects it with `OPENVSCODE_SERVER_TOKEN`. Use a strong token and an HTTPS reverse proxy before exposing it beyond the local machine.

## Environment Promotion

The Context Settings menu contains projects, environments, context variables, and the Environment Promotion workbench.

Environment promotion is Jenkins-job based: JobSeeker reads the source job configuration, detects its current environment, rewrites environment-bound parameters and downstream links for the target environment, optionally promotes dependencies and context variables, and can copy matching artifact folders. Preview mode shows the planned job, context, artifact, and rollback impact before writing changes.

Inline Python promotion copies durable workspace content, including `poetry.lock`, Docker files, `.env.example`, tests, and `.vscode` project files. It leaves behind disposable local environments, tool caches, `.env` secrets, coverage reports, and build output. The promoted workspace recreates the required environments and caches when it is opened or run.

The Jenkins-agent inline Python preview creates `$WORKSPACE/.venv` only when `requirements.txt` contains dependencies, installs the job requirements there, runs with that interpreter, and removes the virtual environment when the run exits.

Jobs created through JobSeeker now require a runtime environment. Existing Jenkins jobs without a detectable environment remain visible as `Unknown` so older jobs can still be listed, inspected, filtered, and cleaned up safely. Use the top-bar environment selector to keep job lists, run/view/delete filters, TMF queries, and new job creation focused on the same environment.

## Demo Data

After the Docker stack is running, seed Jenkins and MariaDB with representative demo data:

```bash
./seed_demo_data.sh
```

The seed creates Jenkins jobs with successful, failed, disabled, not-built, running, and queued states. It also creates Python SDK sample jobs for Jenkins-agent and Docker execution, then inserts TMF rows across past dates, statuses, dimensions, environments, warnings, and errors.

Remove the demo dataset with:

```bash
./seed_demo_data.sh --cleanup
```

Useful overrides:

```bash
DEMO_PREFIX=showcase DEMO_SLEEP_SECONDS=1200 DEMO_BLOCKER_COUNT=5 ./seed_demo_data.sh
```

## Documentation and Use Cases

### Product Documentation

1. [Data Visualization](doc/jobseeker/DataVisualization)
2. [Transaction Monitoring Framework](doc/jobseeker/TransactionMonitoring)
3. [ETL Helpers and Runtime Configuration](doc/jobseeker/ETL)
4. [Job Management](doc/jobseeker/JobManagement)
5. [Jenkins Setup Notes](doc/Jenkins)

The visualization workspace includes a native drag-and-drop Insight Studio backed by six built-in operational datasets, governed MySQL/MariaDB or PostgreSQL table connections, and sandboxed external BI reports. It supports global filters, searchable fields, presentation mode, portable JSON templates, and private or team-shared dashboards. Database credentials are encrypted at rest; no arbitrary SQL or credentials are exposed to the browser.

### Example Implementations

1. [Talend Data Integration Use Case](doc/Talend)
2. [Python ETL Use Case](doc/Python)

## Screenshots

Dashboard with Jenkins and TMF status:

![JobSeeker Dashboard](doc/img/JobSeekerDashboard.png)

Transaction Monitoring records:

![JobSeeker TMF](doc/img/JobSeekerTMF.png)

Job creation and available jobs:

![JobSeeker Job Creation](doc/img/JobSeekerJobCreation.png)

Job build list:

![JobSeeker Job List](doc/img/JobSeekerJobList.png)

Job execution workspace:

![JobSeeker Job Execution](doc/img/JobSeekerJobExecution.png)

## Videos

English JobSeeker demonstration:

[![English JobSeeker Demonstration Video](doc/img/youtube1.JPG)](https://www.youtube.com/watch?v=p9Qusad2Kc0&t)

Brazilian Portuguese JobSeeker demonstration:

[![Portuguese JobSeeker Demonstration Video](doc/img/youtube2.JPG)](https://www.youtube.com/watch?v=Pms98qTvfA0)

## Credits

Matheus Pavanetti
(maintainer@example.com)

## Contributors

New contributors are always welcome.

## Notes

JobSeeker is currently beta software. Please report bugs with enough detail to reproduce the issue.
