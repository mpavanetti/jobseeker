# Machine Learning job sources

This directory is mounted **read-only** into the Docker-in-Docker engine
(`docker-runtime`) at `/jobseeker/ml`, and from there into every ML job
container at `/workspace`.

```
repository/ml/
  jobs/      committed sample jobs (Iris training, pandas profiling)
  inline/    job code authored in the UI, written as inline/<job_key>/main.py
             (generated at run time, not committed)
```

An ML job's **entry point** is a path relative to this directory, e.g.
`jobs/iris-train/main.py`. Each run is a single ephemeral container on the
selected Miniconda-based runtime image:

```
python /workspace/<entry_point> <application args>
```

JobSeeker removes the container when it exits. Runtime images are built by
`scripts/build-compute-runtimes.sh` (or `docker compose --profile runtimes up`).
