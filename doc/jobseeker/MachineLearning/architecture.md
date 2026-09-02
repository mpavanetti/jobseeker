# JobSeeker Machine Learning platform — architecture

This is the design write-up for the ML platform added on `feature/ml-platform`.
It is deliberately its own stream of work so it can merge alongside the
security/timezone branch and Codex's dataset-templates branch.

## v2 changes (modernization pass)

- **Jobs are real Python projects.** Every job owns a workspace at
  `repository/ml/jobs/<key>/` (`main.py`, `Dockerfile`, `requirements.txt` or
  `pyproject.toml`, generated `jobseeker.yml` + `.jobseeker/`). `MlWorkspace`
  owns it and bridges to the managed **OpenVSCode** container so "Open in
  Editor" works exactly as it does for inline-Python jobs.
- **Per-job baked image.** JobSeeker builds `jobseeker/ml-job/<key>:<n>` from the
  workspace via the Docker Engine `/build` route (an in-PHP ustar tar of the
  workspace → `MlEngineClient::buildImage` → `MlComputeDriver::buildJobImage`).
  Runs execute that image with no bind mount; a broken Dockerfile falls back to
  the runtime image + a bind so a Test run still works.
- **Datasets unified into Data Assets.** The `ml_dataset*` tables are gone;
  datasets are `data_assets` rows and versions are immutable
  `data_asset_versions` rows (schema / profile / drift fingerprint per version).
  `MlDataset_model` is now a thin adapter over `DataAssets_model`. Version files
  live in the shared `repository/data-assets/` tree, so the existing preview and
  runtime manifest work on them and ETL jobs can consume ML outputs.
- **SDK dataset accessors.** `ml.datasets.training.read()` — a typed `Dataset`
  handle with `.schema`, `.version`, `.rows`, lazy download. Bindings arrive in
  `JOBSEEKER_ML_DATASETS`; the run pages show every input/output dataset with
  its schema and drift-vs-previous.
- **Modern screens.** Chart.js everywhere (bundled), a shared console component
  with a live metric strip (`ml-ui.js`), a tabbed run detail, a dataset explorer
  with distribution charts + baseline-vs-current overlays, and a monitoring
  dashboard (per-feature drift small-multiples, alert timeline, status grid).
- **Platform wiring.** ML runs appear in the sidebar running-jobs widget, ML
  jobs are badged in the pipeline builder (they are already real Jenkins jobs),
  and the main Dashboard carries an ML card.

## Goals

A self-contained MLOps surface inside JobSeeker: runtime images, a customisable
sample gallery, ML jobs (authored both in a dedicated section and in Create Job),
a dataset registry + explorer, an experiment/run tracker, a model registry with
lifecycle stages, and a monitoring environment with data drift and alerting.

## Shape of the system

```
            ┌──────────────── JobSeeker (CodeIgniter) ───────────────┐
 browser ── │ Ml{Overview,Runtimes,Samples,Datasets,Jobs,Runs,       │
            │      Models,Monitoring}  (session, ROLE_ADMIN/MANAGER)  │
            │                                                        │
 Jenkins ── │ MlRuntime  (bearer token, CSRF-excluded)               │
 SDK     ── │   trigger / status / logs / cancel / heartbeat         │
            │   ingest / artifact / model / dataset / resolve-*      │
            └───────┬───────────────────────┬───────────────────────┘
                    │ MlRunOrchestrator     │ models: MlCatalog / MlRun /
                    │                       │ MlDataset / MlRegistry / MlMonitoring
            ┌───────▼────────┐      ┌───────▼────────┐
            │ MlComputeDriver│      │ MlArtifactStore│
            │  Docker | K8s* │      │  local | s3*   │
            └───────┬────────┘      └────────────────┘
                    │ one ephemeral container per run
            ┌───────▼──────────────────────────────┐
            │ runtime image (jobseeker/ml-runtime) │
            │  /workspace (repo, ro) + jobseeker_ml│
            └──────────────────────────────────────┘
```
`*` = provider seam, stub today.

## Key decisions

### Lineage-first, content-addressed data model
One generic DAG table (`ml_lineage_edge`, `src_kind/src_id → dst_kind/dst_id`)
records dataset-version → run → model-version → predictions. Model files and
dataset snapshots are stored once, keyed by SHA-256, in `ml_artifact`; rows only
carry the digest + a `storage_uri`. This keeps the schema small and makes
"where did this model come from / what did this dataset feed" a single query.

### Zero-wiring capture through a Python SDK
`jobseeker_ml` (stdlib-only, baked into every runtime image) is what a job calls:
`log_metric/log_params/log_model/load_dataset/save_dataset/log_figure/…`. It
POSTs to `MlRuntime` with `JOBSEEKER_ML_API_TOKEN`. The orchestrator owns run
*execution*; the SDK owns run *content*. When `JOBSEEKER_ML_API` is unset the SDK
degrades to print-only, so the same `main.py` runs locally and on the platform.

### Smart job typing
`MlJobIntrospector` lexically scans the entry script (imports, `.fit(`/`.predict(`
/`.transform(`, SDK calls, output writes, CLI args, and the originating sample's
declared type) and classifies each job `train | batch_infer | evaluate |
preprocess | tune` with a confidence score and the signals it matched. It runs on
every save; the author can override, and the override is recorded
(`run_type_source = manual`).

### Provider-neutral compute — Docker now, Kubernetes seam
`MlComputeDriver` is the contract; `DockerMlComputeDriver` talks to the in-stack
`docker-runtime` (DinD) Engine API over stream contexts (the PHP-FPM image has no
ext-curl), runs one container per run, and enforces **host-proportional
admission** — a run is refused if its CPU/RAM ask exceeds the engine host's free
headroom (`capacitySnapshot()` + the check in `MlRunOrchestrator::start()`).
`KubernetesMlComputeDriver` keeps the same method set and fails loudly; finishing
it is one K8s `Job` per run, `requests`/`limits` from the job's limits, logs via
`GET .../pods/<pod>/log`, teardown via delete with
`propagationPolicy=Background`, plus an RBAC manifest and a job-source
ConfigMap/PVC. The single-host Docker path stays the local/dev backend.

### Provider-neutral storage — local volume now, S3 seam
`MlArtifactStore` → `LocalMlArtifactStore` writes under
`repository/ml/artifacts/<hh>/<sha256>`; `S3MlArtifactStore` is a stub for any
S3-compatible endpoint (MinIO included) on the Kubernetes path. Artifacts and
output datasets travel to JobSeeker over HTTP from the SDK, so the only shared
volume the run needs is the read-only job-source mount.

### Monitoring with real drift maths
`MlDriftAnalyzer` computes PSI / KL / mean-shift / missing-delta per feature in
pure PHP from the baseline and current dataset-version "fingerprints" (histograms
/ category counts written by `MlDatasetProfiler` or the SDK).
`MlMonitorEvaluator` runs a pass: drift points + serving signals (prediction
volume, output distribution, accuracy-on-feedback from recent `batch_infer` runs)
→ `ml_monitor_point` time series + `ml_alert` rows on threshold breach, e-mailed
via the existing `EmailSettings`. Monitors carry a cron; the session-free
`machine-learning/runtime/run-due-monitors` (on `MlRuntime`, bearer token) is the
entry point a scheduled agent / cron calls to evaluate every due monitor.

## Execution path of one run

1. Author saves an ML job → `ml_job` row + a thin Jenkins freestyle job whose
   shell step curls `machine-learning/runtime/trigger` then polls (this is the
   same "Jenkins job drives a JobSeeker runtime" pattern Spark/connectors use).
2. `trigger` → `MlRunOrchestrator::start()` materialises inline code to
   `repository/ml/jobs/<key>/main.py`, checks host headroom, creates the
   `ml_run`, and `MlComputeDriver::startRun()` launches one container:
   `python -u /workspace/jobs/<key>/main.py` with the repo mounted ro, limits
   applied, and `JOBSEEKER_ML_API` rewritten to a numeric address so the DinD
   child can reach nginx.
3. The job imports `jobseeker_ml`; metrics/artifacts/model/datasets stream to
   `MlRuntime`.
4. Browser or Jenkins polls `status` → `advance()` polls the engine, applies the
   timeout, and on exit captures the console + exit code, writes the metric
   summary, and tears the container down.
5. `log_model(register=True)` creates an `ml_model` + `ml_model_version` with
   lineage `run → model_version` and `dataset_version → model_version`.

## What is intentionally not here

GPU images (interface has room; no CUDA build), an online/real-time inference
serving endpoint (registry + batch inference only), and the bodies of the
Kubernetes driver and S3 store (stubs with a documented contract).
