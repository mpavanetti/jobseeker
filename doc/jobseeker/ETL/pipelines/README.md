# Pipelines

Pipelines are top-level, environment-aware workflow definitions that coordinate existing Jenkins jobs. They provide a separate drag-and-drop DAG editor without replacing Jenkins as the execution engine.

## Editor

Open **Extract Transform Load > Pipelines** after selecting a global environment. The editor contains:

- Saved pipelines grouped by the pipeline's Group value
- Jenkins jobs available in the selected environment
- A large draggable canvas with persisted node positions
- Output and input ports for creating directed connections
- Per-connection conditions: `SUCCESS`, `FAILURE`, and `ALWAYS`
- Server and client DAG validation with cycle prevention
- Automatic layout by execution wave
- Save/synchronize, run, stop, and delete actions
- Recent runs and live per-node status

Drag a Jenkins job from the palette onto the canvas. Use a node's right port as the source and another node's left port as the target. Select a connection to change its condition.

## Execution semantics

Pipelines must be directed acyclic graphs. JobSeeker topologically evaluates the graph in waves:

1. Root nodes have no dependencies and run together.
2. A node waits until every parent has a terminal result.
3. Every inbound connection condition must match before the node runs.
4. All runnable nodes in the same wave run through Jenkins `parallel`.
5. A node whose conditions do not match is marked `SKIPPED`.

`SUCCESS` matches only a successful parent. `FAILURE` matches `FAILURE`, `UNSTABLE`, `ABORTED`, or `NOT_BUILT`. `ALWAYS` matches any parent that actually ran; it does not activate after a skipped parent.

A fan-in node can depend on multiple branches. For example, a publish node connected to two successful load jobs waits for both. Mixed conditions are supported, such as requiring one parent to succeed and another parent to fail.

A failure recovery branch does not erase the original child failure. The recovery and later fan-in jobs can run, but the overall pipeline result remains failed when any executed child failed.

## Jenkins orchestration

Saving a pipeline compiles it to a hidden Jenkins Pipeline job named in the `__jobseeker_pipeline_*` namespace. The generated job:

- Uses the active Jenkins Pipeline plugins
- Passes the pipeline's global `ENVIRONMENT` parameter to every child
- Calls child jobs with `wait: true` and `propagate: false`
- Uses Jenkins `parallel` for ready nodes
- Prevents concurrent runs of the same pipeline definition
- Emits stable node markers that JobSeeker parses for live status
- Keeps durable console output and build history in Jenkins

JobSeeker stores pipeline definitions, canvas positions, groups, versions, synchronization state, and local pointers to Jenkins queue/build IDs. It does not run a second scheduler or worker engine.

## Validation and safety

The server validates node IDs, Jenkins job names, conditions, duplicate connections, graph size, and cycles. A pipeline can reference only runnable Jenkins jobs detected in its global environment. Save, run, stop, and delete operations require an administrator or manager session and CSRF protection.

The optional pipeline schedule is validated with the same `JenkinsCronSchedule` grammar Job Creation uses, so it rejects the Quartz-only `?`, `L`, `W` and `#` tokens that a Jenkins timer trigger would refuse and reports the specific field at fault. While editing, the schedule field shows the resolved spec plus Jenkins' own previous and next fire times.

Generated orchestrators are hidden from normal job creation palettes and regular JobSeeker job lists. Deleting a pipeline removes its generated Jenkins job and cascades its local run records; it does not delete the child jobs.
