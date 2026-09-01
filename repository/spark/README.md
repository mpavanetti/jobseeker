# Spark job sources

This directory is mounted **read-only** into the Docker-in-Docker engine
(`docker-runtime`) at `/jobseeker/spark`, and from there into every Spark
master / worker / driver container at `/workspace`.

```
repository/spark/
  jobs/      committed sample PySpark jobs (SparkPi, word count, CSV aggregate)
  inline/    job code authored in the UI, written as inline/<job_key>/main.py
             (generated at run time, not committed)
```

A Spark job's **entry point** is a path relative to this directory, e.g.
`jobs/pi/main.py` or `inline/my-job/main.py`. The driver container runs:

```
/opt/spark/bin/spark-submit --master spark://<master>:7077 \
  --deploy-mode client /workspace/<entry_point> <application args>
```

Clusters are ephemeral: JobSeeker creates the master and workers when a job is
triggered and removes them (and the per-run network) when the driver exits, the
same model as Databricks job clusters. Runtime images are built by
`scripts/build-compute-runtimes.sh` (or `docker compose --profile runtimes up`).
