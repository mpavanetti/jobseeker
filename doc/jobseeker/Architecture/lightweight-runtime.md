# Lightweight runtime assessment

## Recommendation

Keep the current PHP/CodeIgniter application and Jenkins execution contract for now. A React/Python rewrite would move code without removing the heaviest runtime component, and replacing Jenkins immediately would require rebuilding durable schedules, queues, retries, cancellation, credentials, worker routing, build history, and console streaming.

The practical path is to shrink the default topology, bound large result pages, and introduce an execution-engine boundary before considering a scheduler replacement.

## Measured baseline

The local Compose stack was measured on 2026-09-03 after loading the stress dataset (50,000 TMF rows, 6,000 errors, 15 pipelines, and 1,500 pipeline runs). The dataset inserted at roughly 24,950 TMF rows/second at the database layer and completed in 2.55 seconds wall time.

Idle/low-traffic container memory was approximately:

| Component | Memory |
| --- | ---: |
| Jenkins | 383 MiB |
| Docker-in-Docker runtime | 153 MiB |
| MariaDB | 140 MiB |
| PHP-FPM after a page request | 101 MiB |
| OpenVSCode Server idle | 29 MiB |
| Nginx | 5 MiB |

The largest local images were OpenVSCode (1.92 GB) and Jenkins (1.66 GB), followed by MariaDB (539 MB) and Docker-in-Docker (527 MB). Image size mainly affects pull/build time and disk, while Jenkins, Docker-in-Docker, MariaDB, and PHP dominate live memory.

The dashboard remained responsive at about 0.43 seconds. The TMF DEV result page returned 31.5 MB for roughly 50,000 total TMF rows, and the all-environment page exhausted the configured PHP memory. The main issue was an unbounded server query followed by server-rendering and browser-side DataTables processing, not ingestion throughput.

After adding the result indexes and applying a bounded truncation probe, both the DEV query and all-environment query completed in under 0.08 seconds through the MariaDB CLI. The error-detail join changed from full/block nested-loop scans to indexed lookups on `tmf_error.tmf_id` and `tmf.instance_id`.

An authenticated all-environment request against the modified application returned HTTP 200 in 0.052 seconds at the default 1,000-row window, with a 2.57 MB response. The same route previously returned HTTP 500 for the stress dataset. This is an immediate safety improvement; server-side keyset pagination remains the right long-term way to make response size independent of history volume.

## Changes in this iteration

- TMF result pages now fetch the newest 1,000 matching rows by default, detect truncation with a 1,001st row, and ask users to narrow filters for older history. `JOBSEEKER_TMF_RESULT_LIMIT` can raise the window up to 10,000.
- Environment and status predicates no longer wrap indexed columns in `UPPER`, `LOWER`, or `TRIM` for normal values. The table's case-insensitive collation already provides the intended comparison.
- Result, job, status, instance, and error-lookup indexes are installed for both fresh and existing databases.
- `docker-compose.light.yml` omits Docker-in-Docker, Docker monitoring, and OpenVSCode while retaining Jenkins and all non-Docker job functionality.
- OpenVSCode and local LLM services remain explicit optional capabilities, so they do not add steady-state cost to the lightweight topology.

Start the lightweight topology with:

```bash
docker compose -f docker-compose.yml -f docker-compose.light.yml up -d --build
```

Return to the complete topology when Docker or browser-IDE capabilities are needed:

```bash
docker compose up -d --build
```

Docker-backed jobs are intentionally unavailable while the Docker runtime is omitted. The standard `docker compose up -d --build` behavior remains unchanged.

## Scheduler direction

Do not clone Jenkins inside the PHP application. First define a small execution interface around the operations JobSeeker already uses: create/update definition, schedule, trigger, cancel, query queue/run, stream log, and delete. Keep Jenkins as the first adapter and move JobSeeker-owned metadata and normalized events into MariaDB.

After that boundary is exercised, a lightweight engine can be evaluated for simple Python and shell jobs using a database-backed scheduler plus isolated worker processes or containers. Jenkins should remain available for pipelines, plugins, distributed agents, and migration compatibility until the alternative proves restart recovery, concurrency limits, timeouts, cancellation, secret isolation, and complete audit history under stress. This staged approach makes a later PHP-to-Python backend decision independent from the execution-engine decision.

## Next performance work

The 1,000-row window removes the immediate memory failure. If operators need interactive access to millions of rows, replace server-rendered TMF rows with a JSON endpoint using keyset pagination (`id < last_seen_id`) and server-side filtering. Avoid offset pagination for deep history. Add repeatable latency and memory thresholds to CI using the existing dataset generator before changing frameworks.
