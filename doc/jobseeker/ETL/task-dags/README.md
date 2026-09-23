# Task DAGs

A Python job normally runs one script end to end: one Jenkins build, one console log, one result. A **task DAG** splits that script into named tasks that JobSeeker runs in dependency order inside the same build, with per-task retries, trigger rules, parallelism, and a per-task record you can read back on Job View.

## Where this sits

JobSeeker orchestrates at three levels. They do not compete; each one is the unit below the last.

Level | Unit | Runs as | Where you define it
--- | --- | --- | ---
Pipeline | a graph of **jobs** | a Jenkins Pipeline job | Extract Transform Load > Pipelines
Job | scheduling, environment, permissions, notification | a Jenkins freestyle build | Job Creation
**Task** | **a graph of steps inside one job** | **the JobSeeker runner, in that build** | **your job's Python code**

Use a Pipeline when the steps are separately schedulable, separately owned, or need different environments. Use a task DAG when the steps belong to one unit of work and want to share a container, a virtualenv, and an installed dependency set.

## Declaring tasks

```python
from jobseeker import dag


@dag.task(id="extract", produces=["raw_customers"])
def extract(ctx):
    ctx.push("rows", 1200)


@dag.task(id="clean", depends_on=["extract"], consumes=["raw_customers"], retries=2, retry_delay=30)
def clean(ctx):
    rows = ctx.pull("extract", "rows", 0)
    ctx.log(f"cleaning {rows} rows")


@dag.task(id="alert", depends_on=["clean"], trigger="FAILURE")
def alert(ctx):
    ctx.log("the batch did not land")


if __name__ == "__main__":
    dag.run()
```

`dag.run()` validates the graph before anything executes. A duplicate id, an edge to a task that is not declared, a self-dependency, or a cycle fails in milliseconds with a message naming the tasks involved, rather than half way through a run.

Five starters in the Job Creation sample library show this working end to end. Filter the library by the **Task DAG** integration to find them:

| Sample | Shows |
| --- | --- |
| Task DAG with retries and a cleanup step | fan-out, per-task retries, a failure handler, an always-runs cleanup |
| Parallel partition loader | independent tasks overlapping, row counts reported to TMF, a fan-in reconciliation |
| Branching load with a skipped path | `SkipTask`, a skipped branch, an `ALWAYS` join, a Context-driven decision |
| Task DAG over governed data assets | `consumes`/`produces`, TMF dimensions, a multi-file workspace with pytest |
| Resumable incremental load | writing tasks that are safe to re-run, so a partial failure can be resumed |

> `jobseeker.task` is a different decorator. It opens a TMF transaction and is unrelated to the DAG; that is why the DAG decorator lives under `jobseeker.dag`. Both remain available, and a task can use both.

### `@dag.task` arguments

Argument | Default | Meaning
--- | --- | ---
`id` | the function name | Stable identifier. Letters, digits, dashes and underscores, starting with a letter.
`depends_on` | `[]` | Task ids that must reach a terminal state before this one is considered.
`consumes` / `produces` | `[]` | Data Asset keys this task reads and writes. Documentation that Job View shows next to the graph.
`retries` | `0` | Extra attempts after the first. `retries=2` means up to three attempts.
`retry_delay` | `0` | Seconds to wait between attempts.
`trigger` | `SUCCESS` | When the task runs. See below.
`description` | `""` | One line shown in the graph tooltip and the task table.
`track` / `dimension` | unset / `""` | Whether this task opens a TMF transaction. Unset follows the run: a recorded run opens one per task. Pass `False` for a step that is not a business transaction.

### Trigger rules

Deliberately the same three words a Pipelines connection uses, so the platform has one vocabulary.

Trigger | The task runs when
--- | ---
`SUCCESS` | every upstream task succeeded
`FAILURE` | at least one upstream task failed
`ALWAYS` | every upstream task reached a terminal state, whatever it was

A task whose rule does not match is `SKIPPED`. A `SUCCESS` task whose upstream failed is `UPSTREAM_FAILED`, which is distinguished from `SKIPPED` so an operator can tell "blocked by a failure" from "not applicable". Both propagate downstream.

### Branching: a task that skips itself

Trigger rules decide from what happened upstream. A branch decides from what it finds when it gets there — the change set was too small, the file had not landed, this partition was empty — and that is not a failure. Raise `SkipTask` and the task ends `SKIPPED`:

```python
from jobseeker.dag import SkipTask

@dag.task(id="full_reload", depends_on=["detect_changes"])
def full_reload(ctx):
    if not ctx.pull("detect_changes", "full_reload", False):
        raise SkipTask("the change set is small enough for an incremental load")
```

A self-skip is never retried, leaves the run green, cancels the task's TMF transaction rather than recording it as an error, and skips everything below it that waits on success. Join the branches back together with a `trigger="ALWAYS"` task. There is no separate branch operator to learn: a branch is an ordinary task that knows when not to run.

## The task context

Every task may take one argument. A task that needs nothing from the runtime can declare no parameters at all.

Member | Purpose
--- | ---
`ctx.task_id`, `ctx.run_key`, `ctx.job`, `ctx.environment`, `ctx.build_number` | Identity of this task and this run.
`ctx.attempt`, `ctx.attempts` | Which attempt is running, out of how many.
`ctx.upstream` | `{task_id: status}` for every direct upstream task.
`ctx.push(key, value)` / `ctx.pull(task_id, key, default)` | Small values between tasks.
`ctx.asset(key, mode=..., required=...)` | Resolve a governed Data Asset in this job's scope. With `required=False` this returns `None` both when the asset is absent from the catalog and when there is no catalog to read at all.
`ctx.context(key, default=...)` | Resolve a Context value for this environment. A lookup that is not required falls back to `default` when the value is missing *or* when the settings database cannot be reached.
`ctx.progress(total=, processed=, msg=)` | Report row counts to this task's TMF transaction, and do nothing when there is none.
`ctx.tmf` | The task's TMF transaction, or `None` when the task is not tracked. Prefer `ctx.progress`.
`ctx.log(message)` | Print a line prefixed with the task id.

### Passing data between tasks

`push` and `pull` are for the row counts, watermarks and file names that make a downstream task's job easier. Values must be JSON serialisable, are capped at 64 KB each and 1 MB per run, and are refused with a message pointing at Data Assets when they are not.

**Large data belongs in a Data Asset.** That is the platform's governed IO contract, it is visible on the Data Assets page, and declaring it with `consumes` / `produces` makes the dependency between two tasks readable without reading the code.

## Execution

A task becomes runnable the moment every upstream task has reached a terminal state, so independent branches overlap instead of waiting for a whole layer. Up to `JOBSEEKER_DAG_MAX_PARALLEL` tasks run at once, in threads, in the job's single container.

Threads are the right shape for IO-bound work: database reads, HTTP calls, file transfers. They will not speed up CPU-bound work such as large pandas transforms, because those hold the interpreter lock. Split CPU-heavy work across Pipeline jobs instead, where each job gets its own executor.

If any task ends `FAILURE` or `UPSTREAM_FAILED`, the run is a failure and the Jenkins build goes red.

## Watching a run

**Job View > Task graph** and **Job Execution** both draw the graph, coloured by the outcome of the run being shown, with a table underneath giving each task's status, attempt, duration, rows and message.

The panel always says which run it is showing, because that is what makes a graph trustworthy:

| The panel says | What it means | How the tasks look |
| --- | --- | --- |
| Build #N is queued | the build exists but has written nothing | every task **Queued** |
| Showing build #N, still running | some tasks have finished, others have not started | finished tasks coloured, the rest **Waiting** |
| Showing build #N, started … | the run is over | every task carries its outcome |
| This job has not recorded a task run yet | the graph is read from the source | every task **Declared** |

A build that has been queued but has produced nothing is never given a previous run's outcomes. Job Execution asks by build number, so watching one build cannot show you another one that happens to be more recent. A run that is still going is re-read on a timer until it finishes, so a graph opened mid-build fills in rather than staying frozen.

### Clicking around

Selecting a task — by clicking a node or a table row, or tabbing to a node and pressing Enter — opens its detail: trigger rule, attempt, timing, rows, upstream outcomes, the datasets it declares, and a link to its TMF transaction. The selected task's edges are highlighted and everything unrelated dims, so a wide graph can be read one branch at a time. Escape clears the selection.

### Re-running from the graph

An operator who may run jobs gets three controls above the graph:

| Control | What it does |
| --- | --- |
| Re-run *n* failed tasks | queues a build with `JOBSEEKER_DAG_RESUME` set to this run, so the tasks that already succeeded are restored and only the rest run |
| Run *task* | queues a build with `JOBSEEKER_DAG_TASKS` set to the selected task |
| Run all | an ordinary build of the job |

Each is a normal build of the same job: it appears in the job's history, honours its environment and notifications, and is visible to anyone watching. Both values are validated server-side against what the job actually declares, so the parameters cannot carry arbitrary text into a build. A viewer without the job-running role is not offered the controls at all.

Generated Python jobs declare the two parameters automatically. A job created before task DAGs existed does not have them; saving it again adds them.

### The console

Each task gets its own collapsible section, titled with the task id, holding every attempt of a retried task.

Because tasks run concurrently in one process and share one console, the runtime tags every line a task writes — a bare `print()`, a logging call, a traceback, anything on stdout or stderr — with `[task-id]`, and the console viewer regroups those lines by owner. Without that, a traceback raised by one task lands under whichever task happened to start last and marks a task that succeeded as failed. `ctx.log()` is tagged by the same mechanism, so it is never double-prefixed, and the DAG's own markers are never tagged.

Order inside a task is preserved exactly. Only the interleaving between tasks running at the same instant is undone, and that ordering carried no meaning. A job that declares no tasks is grouped exactly as before.

### The editor

The Job Creation editor previews the graph as you type, from static analysis of the buffer. Tasks built dynamically — ids computed at import time, tasks registered in a loop — will not appear until the job has run once and the runtime has published its own manifest.

## Tasks in TMF

A task that runs is a transaction, so it belongs on the Results page like any other. Unless a task says otherwise, a recorded run opens a TMF transaction per task:

- **Jenkins Job** is the job, **Dimension** is the task id (or the `dimension` you declared), **Event Text** is the task's description.
- `ctx.progress(total=, processed=, msg=)` fills in the row counts.
- A task that fails records its exception in `tmf_error`, exactly as a hand-written transaction does.
- A task that skips itself is **cancelled**, not errored.
- Two columns, `run_key` and `task_key`, say which run and which task a row belongs to.

That linkage is what makes the integration two-way:

- **From the job run to TMF** — a task's detail panel links to its transaction, and the graph's Rows column and the node labels come from what the task reported. You do not have to leave the job run to see what it did to the data.
- **From TMF to the job run** — `/tmf/taskRun/<run key>` opens Results scoped to a single job run instead of the whole table filtered by hand.

Tracking can be turned off for one task with `track=False`, or for a whole run with `JOBSEEKER_DAG_TMF=0`. Orchestration state is recorded either way; TMF is the business telemetry, not the run record.

## Stored state

Two tables, deliberately separate:

- `job_task_graphs` — the declared graph, one row per job and environment. Written by the static scanner when a job is saved, and overwritten with the authoritative manifest each time the job runs.
- `job_task_runs` — one row per task attempt: status, trigger rule, upstream statuses, timing, message, and the TMF instance id when the task was tracked.

Business telemetry stays in `tmf` — dimension, rows read, written and rejected, errors — with `run_key` and `task_key` saying which run and task a row belongs to. The two join on `tmf_instance_id`. Keeping run state out of `tmf` is what makes resume possible without reinterpreting someone's row counts as orchestration state.

A row whose total and processed counts are both zero is treated as "nothing reported" rather than "processed zero rows", because TMF opens every transaction at zero. A task that read none of a known total still reports 0 of 1000.

Every database write is best effort. A job on an agent that cannot reach MariaDB still runs its whole graph; it prints one line saying run state is not being recorded, and Job View falls back to showing the declared graph.

## Re-running part of a graph

Both are Jenkins build parameters on the job, forwarded into the container by the generated command.

Parameter | Effect
--- | ---
`JOBSEEKER_DAG_RESUME` | A previous run key. Tasks that succeeded in that run are restored and not re-run; everything else runs normally.
`JOBSEEKER_DAG_TASKS` | Comma-separated task ids. Only those run; the rest are skipped, and an unselected upstream is treated as satisfied.

Neither has to be typed by hand: the task graph's re-run controls set them. Both are there for the cases the buttons do not cover, such as a scheduled catch-up run.

Resume is the main reason per-task state is persisted: a failure forty minutes into a forty-five minute job no longer costs the whole forty-five.

## Runtime environment

Variable | Default | Purpose
--- | --- | ---
`JOBSEEKER_DAG_MAX_PARALLEL` | `4` | Most tasks running at once. Clamped to 1–32.
`JOBSEEKER_DAG_FAIL_FAST` | `0` | `1` abandons not-yet-started tasks after the first failure instead of letting independent branches finish.
`JOBSEEKER_DAG_STATE` | `1` | `0` turns off run recording. Previews set this automatically so a throwaway run never appears in the job's history.
`JOBSEEKER_DAG_RESUME` | empty | See above.
`JOBSEEKER_DAG_TASKS` | empty | See above.
`JOBSEEKER_DAG_TMF` | `1` | `0` stops tasks opening TMF transactions for this run. Run state is still recorded.
`JOBSEEKER_DAG_DESCRIBE` | unset | `1` prints the graph as JSON and exits without running any task.

## Inspecting a graph without running it

```
jobseeker-dag describe main.py
```

Prints a human summary and one `JOBSEEKER_DAG_MANIFEST {…}` line carrying the tasks, edges, topological order and layers. Nothing in the application calls this during a normal run; it exists so a graph can be extracted from a sandbox, and so a future one-container-per-task backend has a stable manifest to compile.

## Where this is going

The declared graph is the durable artifact; how it executes is a runtime choice. Today every task runs in the job's own container, which is the cheapest option and works on the built-in Jenkins executor, on agents, and in every deployment mode without new infrastructure. The same manifest is what a future backend would compile into one Jenkins stage or one Kubernetes pod per task, for graphs whose tasks need different images or resource limits.

## Tests

Command | Covers
--- | ---
`npm run test:task-dag` | The renderer, the storage model, the endpoints and view wiring, plus the runtime's scheduler, retries, trigger rules, self-skip, values, resume, TMF linkage and console tagging.
`npm run test:task-dag:scanner` | The static scan of `@dag.task` declarations. Needs a local `php`.
`npm run test:task-dag:e2e` | A real job on a running stack: it runs a graph with a retry, a self-skip and a deliberate failure, checks every recorded attempt and every TMF row, proves a queued build shows itself rather than the previous run, resumes it, and re-runs a single task through the endpoint the graph's buttons call.
