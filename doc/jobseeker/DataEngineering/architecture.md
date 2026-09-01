# Data Engineering compute - architecture and scaling

## Where it is today

Spark and ML jobs run as **ephemeral job clusters** on the in-stack
Docker-in-Docker engine (`docker-runtime`). When a Spark job is triggered
JobSeeker creates a per-run bridge network, starts one `master` container and
*N* `worker` containers from the selected runtime image, starts a `driver`
container that runs `spark-submit --master spark://master:7077`, streams status
and logs, and removes every container and the network when the driver exits. ML
jobs are a single ephemeral container. Nothing runs between jobs.

This is deliberately the same model as Databricks **job clusters**: cheap,
isolated, reproducible, and driven entirely by `spark_clusters` / `ml_*` rows.

## The ceiling

Everything runs on **one host** - the machine the Docker engine lives on. A
cluster can never be larger than that host, and concurrent runs compete for the
same CPU and RAM. JobSeeker now measures this and refuses to over-commit
(`DockerComputeDriver::capacitySnapshot()` + `SparkClusterOrchestrator::admitToHost()`):
a run is **scaled down** to the workers that fit in the host's free headroom, and
rejected outright only if the driver plus one worker will not fit. The Spark Jobs
screen shows host vCPU / memory, what is free, and what the selected cluster
would consume.

That keeps the single-host model *safe*, but it does not make it *bigger*. Past
a few workers, or a few simultaneous jobs, the box is full.

## The options for going bigger

| Approach | Elastic? | Isolation | Work to build | Operational cost |
|---|---|---|---|---|
| Single-host Docker (today) | no | good (per-run containers) | done | none |
| Multiple remote Docker engines | manual | good | medium - engine registry + a scheduler + spread logic | we run the scheduler |
| Docker Swarm | node-level only | good | medium - Swarm join, overlay networks, service specs | Swarm is legacy-ish |
| **Kubernetes** | **yes - Cluster Autoscaler / Karpenter add nodes on demand** | **strong - namespaces, NetworkPolicy, ResourceQuota, LimitRange** | medium - finish `KubernetesComputeDriver`, add RBAC + manifests | the platform runs the scheduler; we submit specs |
| VM-per-cluster (true Databricks) | yes - cloud VM API | strongest | large - cloud provisioning, image bake, bootstrap, teardown, billing | we operate a fleet manager, cloud-coupled |

## Recommendation

**Keep the Docker driver for local and small deployments; make Kubernetes the
production compute backend.** Reasons:

1. **Elasticity is solved for us.** Spark-on-Kubernetes is first-class
   (`spark-submit --master k8s://...`, or the Spark Operator). Executor pods are
   scheduled by Kubernetes; when the cluster runs out of nodes the **Cluster
   Autoscaler / Karpenter adds machines**, and removes them when idle. That is
   exactly the "spin up more machines, don't stay limited" behaviour - without
   JobSeeker owning a VM fleet.
2. **Proportionality is enforced by the platform.** Pod `requests`/`limits`,
   `ResourceQuota` per namespace (one namespace per environment), and
   `LimitRange` defaults mean a job physically cannot take more than its slice.
   JobSeeker's admission check becomes a *pre-flight hint*, not the only guard.
3. **The seam already exists.** `application/libraries/KubernetesComputeDriver.php`
   implements the full `ComputeDriver` contract and fails loudly today. The
   orchestrators, controllers, screens, schema and tests are all
   driver-agnostic. Finishing the K8s driver is additive:
   - Spark cluster -> headless `Service` + master `Pod` + a worker `Deployment`
     (replicas = workers, optional `HorizontalPodAutoscaler` when autoscale is
     on), all labelled `com.jobseeker.compute.run=<run>`.
   - `spark-submit` -> a Kubernetes `Job` whose pod mounts the job source
     (a `ConfigMap` for inline code, a `ReadOnlyMany` PVC for repository jobs)
     at `/workspace`.
   - ML job -> a single Kubernetes `Job` with `requests`/`limits` from
     `cpu_limit` / `memory_limit_mb`.
   - `poll*` -> read `Job`/`Pod` status; `fetch*Logs` -> `GET .../pods/<pod>/log`;
     `teardown*` -> delete with `propagationPolicy=Background`.
   - New config: `JOBSEEKER_K8S_API_URL`, in-cluster service-account token (or
     `JOBSEEKER_K8S_TOKEN`), `JOBSEEKER_K8S_NAMESPACE_PREFIX`,
     `JOBSEEKER_K8S_JOB_SOURCE_PVC`.
4. **VM-per-cluster is not worth it.** It is the most faithful Databricks clone
   but also the most infrastructure to build and operate, and it couples
   JobSeeker to a specific cloud. Kubernetes gets ~90% of the benefit
   (elastic capacity, isolation, quotas) with a fraction of the moving parts,
   and most target environments already have a cluster.

**Do not** build the multi-remote-Docker or Swarm options - they reinvent a
scheduler that Kubernetes already is, with weaker isolation.

## Suggested sequencing

1. *(done)* Host-proportional admission control + live per-container monitoring
   on the Docker driver.
2. Finish `KubernetesComputeDriver` behind `JOBSEEKER_COMPUTE_DRIVER=kubernetes`,
   starting with ML jobs (single `Job`, simplest), then Spark.
3. Ship the RBAC manifest, namespace/quota templates, and the job-source
   `ConfigMap`/PVC wiring as `deploy/kubernetes/`.
4. Optional: node autoscaling guidance (Cluster Autoscaler / Karpenter) in the
   deployment docs - this is cluster configuration, not JobSeeker code.
