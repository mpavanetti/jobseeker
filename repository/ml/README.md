# repository/ml

Everything the JobSeeker Machine Learning platform reads or writes on disk.

```
repository/ml/
  sdk/            the jobseeker_ml Python package (installed into every runtime image)
  samples/<key>/  built-in job templates: main.py + sample.json (synced into ml_sample on load)
  jobs/<key>/     inline job source written by JobSeeker when an inline ML job is saved / run
  artifacts/      content-addressed model + dataset-snapshot blobs (local artifact store)
```

`samples/` and `sdk/` are version-controlled. `jobs/` and `artifacts/` are
runtime state (git-ignored).

## How a run reaches back

A run container gets `repository/ml` mounted read-only at `/workspace` and runs
`python -u /workspace/<entry>`. The `jobseeker_ml` SDK (baked into the image)
streams metrics, artifacts, model versions and output datasets to
`machine-learning/runtime/*` over HTTP using `JOBSEEKER_ML_API_TOKEN`. Nothing is
shared back through the filesystem.

## Building the runtime images

```
bash scripts/build-ml-runtimes.sh
# inside the stack, targeting the compute engine:
DOCKER_HOST=tcp://docker-runtime:2375 bash scripts/build-ml-runtimes.sh
```
