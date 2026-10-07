# Workspace runtimes

Projects need different environments. One project is plain Python with a few
PyPI packages, the next needs Conda for GDAL and PyTorch, a third needs a
system ODBC driver that only a custom Dockerfile can install. Before runtimes,
every project opened in the same OpenVSCode container, with one Python and one
set of system libraries, while builds could already run each job's own
Dockerfile. The editor and the build did not agree on the environment.

A **workspace runtime** is a container image that a project develops in and
that jobs can run in. JobSeeker builds it, deploys it as a VS Code dev
container for a project, and offers the same image to Docker jobs, so code is
written, tested and scheduled in one environment.

## Concepts

Concept | What it is
--- | ---
Runtime | A recipe for an image: a Python version with packages, a Conda `environment.yml`, or a Dockerfile. Runtimes form a catalog every project can choose from.
Project runtime | The project's choice of runtime and isolation. **Default** keeps the bundled OpenVSCode editor; **Dev container** builds the project's own `.devcontainer/devcontainer.json`.
Build | The runtime image, plus an IDE variant with the VS Code server added on top. Builds are content-addressed: the tag is a hash of the recipe, so an unchanged recipe is never rebuilt and a changed one never overwrites an image a job still uses.
Deployment | A running editor container of a project. **Just me** gives every person their own container; **Shared** runs one container the whole team opens.

```
catalog runtime / devcontainer.json
        │  build (Docker Engine API, job runtime)
        ▼
jobseeker-runtime/<key>:<hash>        ← Docker jobs run this image
        │  + VS Code server, Git helpers, extensions (toolkit layer)
        ▼
jobseeker-runtime/<key>:<hash>-ide    ← editor deployments run this image
        │  one per project (Shared) or per project and person (Just me)
        ▼
jobseeker-ide-p<project>-u<user>      → http://host:3001/ide/<port>/
```

## Kinds of runtime

Kind | Recipe | Environment
--- | --- | ---
Python | Python version (`python:<version>-slim`), system packages, Python packages | System Python with uv, Poetry and Git. The packages are preinstalled in the image.
Conda | `environment.yml` on Miniforge | The file is applied to the base environment, so `python` is Conda's.
Dockerfile | Any Dockerfile | Whatever it builds. It must be glibc-based (Debian, Ubuntu, RHEL, and so on), because the VS Code server does not run on Alpine/musl.
Dev container (per project) | `.devcontainer/devcontainer.json` in the project | `image`, or `build.dockerfile` with `context`, `args` and `target`. `features` published to a registry, `containerEnv`/`remoteEnv`, `customizations.vscode.extensions`, `customizations.vscode.settings`, `postCreateCommand` and `postStartCommand` are applied. Local features, `mounts`, `runArgs`, `forwardPorts` and Compose-based dev containers are reported as unsupported. The launcher can add a starter `.devcontainer` (slim Python, a shared requirements file, room for extensions) to a project that has none.

The **Default** runtime is the existing shared OpenVSCode service. It needs no
build and stays the right choice for small projects.

Inside every runtime the project is laid out and bootstrapped as before: one
`.venv` at the project root holds the dependencies of `shared/` and every
`jobs/<job>` folder (from `poetry.lock`, `uv.lock`, `pyproject.toml` or
`requirements.txt`). In a runtime the virtual environment is created with
`--system-site-packages` from the runtime's Python, so packages the image
provides (Conda's GDAL, a CUDA PyTorch) are importable while each job still
declares its own dependencies. When the runtime's Python is itself a virtual
environment (a Dockerfile that puts `/opt/venv/bin` on `PATH`), that only
reaches the interpreter underneath, so the bootstrap adds the environment's
`site-packages` to `.venv` with a `.pth` file. Another `.pth` puts the project
root on `.venv`'s path, so `from shared import ...` works in notebooks and the
debugger as it does in terminals and builds. Switching runtime recreates
`.venv`.

The editor image sets what the bootstrap relies on
(`JOBSEEKER_VENV_SYSTEM_SITE_PACKAGES`, the toolkit on `PATH`) in its own
environment, and the container sets `JOBSEEKER_REPOSITORY_ROOT` and
`JOBSEEKER_DATA_ASSETS_MANIFEST`, so every process builds and reads the same
`.venv` and Data Assets: tasks, terminals, notebook kernels and `docker exec`.

### Notebooks

A runtime with the Jupyter extension (the **Jupyter notebooks** template and
the machine learning, deep learning, geospatial and R templates) runs
notebooks in the project's `.venv`: choose **Select Kernel → Python
Environments → .venv**. Runtime editors open their project trusted
(`--disable-workspace-trust`): in Restricted Mode VS Code turns off the Jupyter
extension and the task that builds `.venv`, so a notebook finds no kernel. A
deployment only ever sees its own project. The editor step also installs `ps`
(procps) when the image lacks it, which the Jupyter extension uses to stop a
kernel's processes on restart.

### Samples

Python projects can start jobs from JobSeeker's sample library
(`application/config/job_samples.php`), scripts and notebooks, in any editor:

- in VS Code, the task **JobSeeker: add sample** lists the samples and writes
  the chosen one into `jobs/<sample>/` (`.vscode/jobseeker-samples.sh`, which
  carries the samples, so it needs no connection to JobSeeker), installs its
  dependencies into `.venv`, and prints the Job Creation link;
- in the launcher, **Start from a sample** adds one to your workspace of the
  selected project; an editor that is already open sees it at once.

Dependencies follow the job folder, however it got there. The setup task
stamps each folder it installs (`.venv/.jobseeker-deps/`, a checksum of its
`pyproject.toml`, lock files and `requirements.txt`), and **JobSeeker: run
current job file**, **JobSeeker: test current job** and the debugger (its
pre-launch task) install a job folder first when it has no stamp or its
dependency files changed: a sample added from the launcher, or a package added
to a job's `pyproject.toml`. **Run current job file** on a notebook runs it as
its job does, top to bottom through `jobseeker.notebook`.

A sample never replaces an existing folder. Its `pyproject.toml` requires the
project runtime's Python. A sample that ships a Dockerfile keeps it only on
the Default editor: a runtime's jobs run in the runtime's image. Projects
opened before this existed get the task on their next open (`tasks.json` is
merged, never rewritten).

## Runtimes are dev containers

Every runtime, whatever its kind, carries what a `devcontainer.json` does:
VS Code extensions (installed from open-vsx.org into its editor image), dev
container features, forwarded ports, a post-create command and environment
variables. That makes a runtime portable in both directions:

- **Out**: **.devcontainer** on the Runtimes page downloads any catalog runtime
  or template as a `.devcontainer/` folder (definition, Dockerfile, its
  requirements or environment file, a README, and the JobSeeker SDK in
  `jobseeker-sdk/`, which the Dockerfile installs so jobs import `jobseeker`
  as they do in JobSeeker). Put it in a repository and open it with the VS Code
  Dev Containers extension or GitHub Codespaces; the same environment runs on
  a laptop.
- **In**: a project whose runtime is **Dev container** builds the
  `.devcontainer/` it commits. The launcher's **Add a starter** writes one into
  your workspace from the Python starter, any template or any catalog runtime.

Forwarded ports matter when the dev container is opened in VS Code on a
machine, which forwards them; JobSeeker's browser editors do not forward ports.

## Templates

The Runtimes page's **Templates** tab is a gallery of ready-made runtimes.
**Use template** opens the runtime editor filled in, to add it to the catalog
as it is or adapted; in the launcher, a project can choose a template directly,
which adds it to the catalog. Every template builds (checked on Debian trixie
images):

Category | Templates
--- | ---
Essentials | Python essentials (HTTP, settings, CLI, testing), Web scraping (httpx, BeautifulSoup, lxml, Scrapy)
Data engineering | Data engineering (pandas, Polars, DuckDB, Arrow, SQLAlchemy, Postgres/MySQL/ODBC drivers, cloud storage), dbt analytics engineering (DuckDB and Postgres adapters, SQLFluff), Data quality & contracts (Pandera, Hypothesis, Faker)
Streaming | Streaming & messaging (Kafka, RabbitMQ, MQTT, Redis, Avro, Protobuf), Stream processing (Quix Streams, Bytewax)
Analytics & visualization | Dashboards & data apps (Streamlit, Dash, Panel, Plotly, Altair), Jupyter notebooks, R for data science (tidyverse, IRkernel)
APIs & apps | API development (FastAPI, SQLModel, Alembic), Full stack (FastAPI plus Node.js LTS from its feature)
Machine learning & AI | Machine learning (scikit-learn, XGBoost, LightGBM, Optuna, MLflow), Deep learning (PyTorch CPU, Transformers, Datasets), LLM & AI apps (Anthropic and OpenAI SDKs, MCP, LangChain, Chroma)
Big data | Apache Spark (PySpark, Delta Lake, OpenJDK 21)
Geospatial | Geospatial (GDAL, GeoPandas, Rasterio from conda-forge)
Cloud & DevOps | Cloud & DevOps (AWS, Azure and Google SDKs, AWS and Azure CLIs, Terraform)

Templates live in `WorkspaceRuntime::templates()`; each is an ordinary runtime
recipe, so adding one is a few lines.

## Editing a runtime

The runtime editor puts the form (kind, Python version, system packages,
extensions, features, ports, environment, post-create command) next to a
VS Code-style editor (`assets/js/code-editor.js`): the file you edit
(`requirements.txt`, `environment.yml` or the `Dockerfile`) with syntax
highlighting, line numbers and a status bar, beside read-only tabs with the
`Dockerfile`, `devcontainer.json` and `README.md` the form generates, refreshed
as you type. **Save & build** saves and starts the build with its log open.

## Isolation

Mode | Containers | Mounted | Use it for
--- | --- | --- | ---
Just me (default) | One per project and person | That person's working copy of the project (or the project's shared folder when it has no Git), the SDK's folder `python/lib` read-only, and Data Assets | Independent work, heavy local runs, and an environment someone can break without affecting others
Shared | One per project | The working copies of everyone who has opened the project (or its shared folder), the SDK's folder `python/lib` read-only, and Data Assets | A team box: one warm environment, lower cost, shared state

Either way a deployment sees only its own project, unlike the Default editor,
which mounts the whole repository. A Shared deployment is recreated when
somebody opens it for the first time, so their working copy is mounted; the
others reconnect. The SDK's parent folder is mounted rather than the SDK
itself because JobSeeker refreshes the SDK by renaming a new folder into
place, which a bind mount of the old folder would never see (the Default
editor's Compose mount does the same).

Every editor process runs as the checkout owner (the uid JobSeeker's services
already use), with `no-new-privileges`, all capabilities dropped, a PID limit,
and the project's CPU and memory limits. A deployment stops itself after the
idle timeout without a connected browser, keeps its container, and starts
again on the next open. Changing the runtime, the image or the resources
recreates the container; the home volume (VS Code settings, extension state,
caches) survives, and is handed to the checkout owner before each new
container. An editor that exits right after starting is reported with its last
output instead of being restarted by the launcher's polling; opening the
project again retries it.

Changing a project from Just me to Shared (or back) leaves the earlier
deployments to stop when idle; remove them on the Runtimes page. Deleting a
project removes its deployments and their homes.

## Building

Builds run on the job runtime's Docker Engine (the `docker-runtime` service)
through its API, in the background, and are logged under
`application/cache/workspace_runtime_builds/`. Each build is two steps:

1. The runtime image from its recipe.
2. The IDE image: `FROM` the runtime image, adding `/opt/jobseeker-ide` from
   the `jobseeker-workspace-toolkit:local` image. The toolkit carries the
   OpenVSCode server, the Python, debugpy, Ruff, mypy and BasedPyright
   extensions, uv, and JobSeeker's Git credential helpers. The IDE step installs
   Git, curl, the OpenSSH client and `ps` when the image lacks them, and
   creates an account for the checkout owner. (The Mypy extension stays on
   2026.4.0: 2026.6.0 on Open VSX ships without a module its server imports.)

A dev container's features are installed in the IDE step, as the dev
container spec runs them. A build stage fetches each one from its registry
(`docker/workspace_runtime/jobseeker-features`; references and options travel
in a `features.json`, never in a shell line), then its `install.sh` runs as
root in the declared order with its options (defaults first) in the
environment and `_REMOTE_USER` set to the checkout owner. Its `containerEnv`
(Node.js's `PATH`, for example) is written to `/etc/jobseeker-features.env`,
which the editor sources when it starts, and the extensions it recommends are
installed. Features belong to the editor image, not the runtime image jobs
use: they are development tooling. `installsAfter` and `dependsOn` are not
resolved, so list features in the order they need.

The toolkit is built by the one-shot `workspace-toolkit` Compose service when
the stack starts. A new toolkit changes the hash of every IDE image, so editors
pick it up at their next open, and the runtime images jobs use stay as they
are.

A build starts when someone presses **Build** on the Runtimes page, or on the
first open of a project whose runtime has no image yet. The editor tab
follows the build log and opens when the image is ready.

**Rebuild** (on the Runtimes page for a catalog runtime, in the launcher for a
project's runtime) builds the same recipe again for newer base images and
packages: it pulls the base images and skips the build cache. The tag stays
(same recipe), so the editor spec includes the image id and each editor moves
to the new image at its next open. While a rebuild runs, or after one fails,
editors keep opening on the last good image; the launcher says so.

Images are never removed on their own: a job may still run an old runtime
image. **Reclaim images** on the Runtimes page (administrators) lists what
nothing needs any more and removes what is chosen:

- editor images of no current runtime and no container (preselected; jobs
  never use them);
- runtime images no catalog runtime, container or saved job names (offered,
  not preselected: a Git job whose Dockerfile starts `FROM` one cannot be seen
  from JobSeeker, and is only listed when every job's configuration could be
  read from Jenkins).

Every image a kept image is built on is kept too, and untagged layers left by
rebuilds are pruned.

## Jobs

A built runtime appears in Job Creation's Docker image suggestions for Python
Docker jobs as `jobseeker-runtime/<key>:<hash>`. Choosing a job folder of a
project on a runtime (**From a project workspace**) sets the job to Docker with
that image, so it runs where it was developed; a folder with its own
Dockerfile builds that instead. A job's own Dockerfile can
also start `FROM` a runtime image; such builds skip `--pull`, because runtime
images exist only in the job runtime. The runtime is pinned by its hash, so
rebuilding it with new packages changes nothing for existing jobs until they
are saved with the new tag.

A job's Python follows its project's runtime. The starter files of a new job
folder set `requires-python` to the runtime's Python version: the Python kind's
version, the `python=` pin of a Conda environment, or the `python:<version>`
image a Dockerfile or a dev container starts from. When the version cannot be
read (another base image), starters ask for `>=3.10`, and the Default editor
keeps its own Python. A Docker job whose `pyproject.toml` still leaves out the
image's Python, such as a job written for another runtime, is not refused:
Poetry rejects it, so the build prints a `[JobSeeker]` warning and installs the
dependencies the file lists with pip, as the editor's `.venv` does. Any other
Poetry failure still fails the build. Setting `requires-python` to include the
image's Python brings back the Poetry install.

A job's entry file can be a notebook of the project. It runs in the same
image as in the editor, with `jobseeker.notebook` (nbclient and ipykernel,
installed for the run when the image lacks them), and its parameters can read
the project's Context values. The executed notebook is published under
`repository/notebook-runs/<job>/<build>/`, and the build log draws the run as
the notebook (`assets/js/job-console-notebook.js`). See "Notebooks as jobs"
in `doc/Python/README.MD`.

A notebook in the editor reads Context values as its job will: the SDK falls
back to the workspace's `.env.jobseeker` for the environment the workspace
was opened for and for the project, when `JOBSEEKER_ENVIRONMENT` and
`JOBSEEKER_PROJECT_NAME` are not set. Opening a project also refreshes the
repository's copy of the SDK, which the editor installs into `.venv`.

A Docker job runs its script in a login shell, which resets `PATH` from
`/etc/profile`. The image's own `PATH` is handed in as `JOBSEEKER_IMAGE_PATH`
and put first again, so a runtime (or a job Dockerfile) that installs its
packages into a virtual environment on `PATH` runs the job with that Python,
as the editor does.

## Routing and security

Editors run with the job runtime's network (like Docker jobs), each on a port
from `JOBSEEKER_WORKSPACE_IDE_PORTS` (3100-3199 by default). The workspace
gateway, a second nginx server published on `JOBSEEKER_WORKSPACE_GATEWAY_PORT`
(3001), proxies `/ide/<port>/` to it, with WebSockets. OpenVSCode serves under
that base path. Like JobSeeker itself, the gateway is published on every
interface, over IPv4 and IPv6: browsers often reach names such as
`host.local` over IPv6 only, and a port published on `0.0.0.0` refuses them.
`JOBSEEKER_WORKSPACE_GATEWAY_HOST` pins it to one address, such as `127.0.0.1`
behind a reverse proxy.

- Every deployment has its own connection token. JobSeeker hands the launch
  URL only to the person a Just me deployment belongs to, or to anyone who can
  open the project for a Shared one.
- The gateway strips every cookie except the editor's own before proxying, so
  code running in an editor never sees a JobSeeker session cookie.
- The gateway is a different origin from JobSeeker (another port), as the
  Default editor already is.
- With HTTPS, the gateway's certificate comes from a local authority limited
  to this server's names (`scripts/workspace-gateway-certificate.sh`).
  JobSeeker serves that authority's certificate (never its key) at
  `/workspace-gateway-ca.crt`, and the launcher checks whether the browser
  trusts it (an image from `/jobseeker-gateway-check.gif`, which JobSeeker's
  Content Security Policy allows); until it does, the launcher explains the
  warning editors open behind and how to trust the certificate once.
- Connection tokens are redacted from editor output JobSeeker shows (the
  Runtimes page and crash messages).
- Connector sessions, Git credential sessions and Data Assets work as in the
  Default editor: runs in the editor use the signed, job-scoped session that
  opening the workspace writes.

## Tests

- `scripts/test-workspace-runtime.php` (`npm run test:workspace-runtime`):
  recipes, generated Dockerfiles, devcontainer.json parsing and export, every
  template, build contexts and hashes, tar archives and container specs.
- `scripts/test-workspace-runtime-e2e.py` (`npm run test:workspace-runtime:e2e`):
  on the running Compose stack, builds a runtime, opens a project in it,
  checks the gateway and its cookie filtering, bootstraps the workspace inside
  the editor, checks what notebook kernels see, adds samples from the task and
  the launcher, runs Docker jobs on and `FROM` the image (with a virtual
  environment on its `PATH`), runs a notebook sample as a job with a Context
  parameter and a failing run override (published notebook, TMF), deploys a shared dev
  container with a registry feature, previews and downloads templates as
  dev containers, opens a project in a template's runtime, gives two people
  Just me and Shared
  editors of a Git project that commit and push as themselves, rebuilds a
  runtime while its editor stays usable, reclaims unused images, and cleans
  up.

## Kubernetes

This release implements the Docker backend used by the Compose stack. The
Kubernetes base has no Docker engine, so projects there keep the Default
runtime and the Runtimes page says so. The Kubernetes backend is designed as:

- builds with an in-cluster BuildKit (rootless) or Kaniko Job that pushes
  `runtime` and `-ide` images to the deployment's registry;
- one Pod and ClusterIP Service per deployment, labelled like the Docker
  containers, created by a dedicated service account limited to Pods and
  Services in the namespace, mounting the repository claim with `subPath`s
  for the project's working copies;
- the gateway resolving `jobseeker-ide-<id>.<namespace>.svc` instead of a port,
  and an idle controller scaling unused Pods to zero.

The catalog, project settings, content-addressed builds and launch flow are
backend-independent, so only the image build and container calls change.

## Configuration

Variable | Default | Purpose
--- | --- | ---
`JOBSEEKER_WORKSPACE_RUNTIMES_ENABLED` | `true` | Shows runtimes in the launcher and the Runtimes page. Needs the Docker job runtime.
`JOBSEEKER_WORKSPACE_GATEWAY_HOST` / `_PORT` | every interface / `3001` | Where the workspace gateway is published. Empty means every interface, over IPv4 and IPv6.
`JOBSEEKER_WORKSPACE_GATEWAY_PUBLIC_URL` | derived from the request | Public URL of the gateway behind a reverse proxy.
`JOBSEEKER_WORKSPACE_IDE_PORTS` | `3100-3199` | Ports editors listen on inside the job runtime (at most 3100-3499, which the gateway accepts).
`JOBSEEKER_WORKSPACE_RUNTIME_REPOSITORY` | `/php/repository` | Where the job runtime mounts `./repository`.
`JOBSEEKER_WORKSPACE_DEFAULT_CPUS` / `_MEMORY_MB` | `2` / `4096` | Resource limits of a new project deployment.
`JOBSEEKER_OPENVSCODE_IDLE_TIMEOUT_MINUTES` | `30` | Also stops idle runtime deployments. `0` keeps them running.
`JOBSEEKER_WORKSPACE_CONNECTOR_API_URL` / `_GIT_CREDENTIAL_URL` | `http://nginx:8080/connector-runtime` / `http://nginx:8080/git-credential` | Where editors reach JobSeeker's connector API and Git credential endpoint, as the Default editor does.
