"""JobSeeker notebook runner: a Jupyter notebook as the entry file of a job.

A Python job whose entry file is an ``.ipynb`` runs it top to bottom in a
Jupyter kernel, the way "Run All" does in VS Code, and fails the build on the
first cell that raises:

    python -m jobseeker.notebook run jobs/report/analysis.ipynb \\
        --parameters '[{"name": "rows", "source": "context", "value": "rows"}]' \\
        --output /tmp/analysis.ipynb

Design notes
------------
* Parameters follow papermill: a cell tagged ``parameters`` holds the
  defaults, and the job's values go into a new cell tagged
  ``injected-parameters`` right after it (at the top when there is none).
  A value is a literal, a Context value (resolved for the job's environment
  and project, like ``tmf.context()``) or an environment variable.
* The console carries one ``[JobSeeker Notebook]`` marker line per cell
  boundary, with each cell's source (every line prefixed ``│ ``) and its
  text outputs in between, so the build log reads like the notebook and
  JobSeeker's console renders it as one. Images and HTML are summarised
  there and kept in the executed notebook, which is written after every cell.
* Transaction Monitoring sees one transaction per run: the code cells are its
  records, so the TMF page shows how far a notebook got and why it stopped.
  Telemetry is best effort and never fails a notebook.
* Without nbclient and ipykernel the runner still executes plain Python cells
  in one namespace, skipping IPython magics, and says so.
"""

from __future__ import annotations

import argparse
import ast
import base64
import contextlib
import io
import json
import os
import re
import signal
import sys
import tempfile
import time
import traceback
import uuid
from typing import Any, Dict, List, Optional, Tuple

__all__ = [
    "NotebookError",
    "infer_value",
    "inject_parameters",
    "notebook_parameters",
    "resolve_parameters",
    "run_notebook",
]

MARKER = "[JobSeeker Notebook]"
SOURCE_PREFIX = "│ "
INJECTED_TAG = "injected-parameters"
PARAMETERS_TAG = "parameters"
PARAMETER_NAME = re.compile(r"^[A-Za-z_][A-Za-z0-9_]{0,63}$")
ANSI_ESCAPE = re.compile(r"\x1b\[[0-?]*[ -/]*[@-~]")
RICH_MIME_TYPES = ("image/png", "image/jpeg", "image/gif", "image/svg+xml", "text/html", "text/markdown",
                   "text/latex", "application/json", "application/vnd.plotly.v1+json")
EXIT_OK = 0
EXIT_CELL_FAILED = 1
EXIT_USAGE = 2
EXIT_KERNEL = 3


class NotebookError(Exception):
    """A notebook could not be prepared or run (not a failing cell)."""


class _Interrupted(Exception):
    pass


def _coerce_int(value: Any) -> int:
    try:
        return max(0, int(str(value).strip() or "0"))
    except ValueError:
        return 0


def _strip_ansi(text: str) -> str:
    return ANSI_ESCAPE.sub("", str(text or ""))


def _source_text(cell: Any) -> str:
    source = cell.get("source", "")
    return "".join(source) if isinstance(source, list) else str(source or "")


def _cell_tags(cell: Any) -> List[str]:
    tags = (cell.get("metadata") or {}).get("tags") or []
    return [str(tag) for tag in tags] if isinstance(tags, list) else []


def _human_size(size: int) -> str:
    if size < 1024:
        return "%d B" % size
    if size < 1024 * 1024:
        return "%.1f KB" % (size / 1024.0)
    return "%.1f MB" % (size / 1024.0 / 1024.0)


def _seconds(value: float) -> str:
    return "%.2fs" % value if value < 60 else "%dm%02ds" % (int(value) // 60, int(value) % 60)


class _Console:
    """Writes the build log: markers always start on a line of their own."""

    def __init__(self, stream: Any = None):
        self.stream = stream or sys.stdout
        self.at_line_start = True

    def write(self, text: str) -> None:
        if not text:
            return
        self.stream.write(text)
        self.stream.flush()
        self.at_line_start = text.endswith("\n")

    def line(self, text: str = "") -> None:
        if not self.at_line_start:
            self.write("\n")
        self.write(text + "\n")

    def marker(self, *parts: Any) -> None:
        self.line(" | ".join([MARKER + " " + str(parts[0])] + [str(part) for part in parts[1:]]))

    def block(self, text: str, prefix: str = "") -> None:
        text = _strip_ansi(text).rstrip("\n")
        if text == "":
            return
        for line in text.split("\n"):
            self.line(prefix + line)


# --------------------------------------------------------------------------
# Parameters


def infer_value(text: Any) -> Any:
    """A parameter value from its text, typed the way papermill's -p does.

    Numbers, true/false/null, JSON lists and objects and quoted strings are
    read as JSON; True/False/None as Python; anything else stays a string.
    Wrap a value in double quotes to keep "007" a string.
    """

    if not isinstance(text, str):
        return text
    stripped = text.strip()
    if stripped == "":
        return ""
    if stripped in ("True", "False"):
        return stripped == "True"
    if stripped == "None":
        return None

    def reject_constant(name: str) -> Any:
        raise ValueError(name)

    try:
        return json.loads(stripped, parse_constant=reject_constant)
    except ValueError:
        return text


def _parameter_specs(raw: Any) -> List[Dict[str, str]]:
    """Normalises --parameters: a JSON list of {name, source, value}, or a
    JSON object of name -> literal value. "b64:" and "@file" are accepted."""

    if raw is None:
        return []
    if isinstance(raw, str):
        text = raw.strip()
        if text == "":
            return []
        if text.startswith("b64:"):
            text = base64.b64decode(text[4:]).decode("utf-8")
        elif text.startswith("@"):
            with open(text[1:], "r", encoding="utf-8") as handle:
                text = handle.read()
        try:
            raw = json.loads(text) if text.strip() else []
        except ValueError as error:
            raise NotebookError("The notebook parameters are not valid JSON: %s" % error)
    if isinstance(raw, dict):
        raw = [{"name": name, "source": "value", "value": value} for name, value in raw.items()]
    if not isinstance(raw, list):
        raise NotebookError("The notebook parameters must be a list of {name, source, value}.")
    specs = []
    for item in raw:
        if not isinstance(item, dict):
            raise NotebookError("Each notebook parameter must be an object with a name.")
        name = str(item.get("name", "")).strip()
        if not PARAMETER_NAME.match(name):
            raise NotebookError("%r is not a valid notebook parameter name: use a Python identifier." % name)
        source = str(item.get("source", "value") or "value").strip().lower()
        if source not in ("value", "context", "env"):
            raise NotebookError("Notebook parameter %s has an unknown source %r (value, context or env)." % (name, source))
        value = item.get("value", "")
        specs.append({"name": name, "source": source, "value": value if isinstance(value, str) else json.dumps(value)})
    return specs


def _context_lookup(key: str, environment: str, project: str) -> Optional[str]:
    from jobseeker import JobSeeker  # imported late: the SDK needs a database driver

    with JobSeeker(environment=environment or None, install_signal_handlers=False) as seeker:
        return seeker.get_context(key, project=project or None)


def resolve_parameters(specs: List[Dict[str, str]], environment: str = "", project: str = "",
                       overrides: Optional[Dict[str, Any]] = None, context_lookup: Any = None) -> Tuple[Dict[str, Any], Dict[str, str]]:
    """Values for every parameter, and where each came from.

    A Context value missing in the job's environment fails the run: running a
    notebook on a silently different input is worse than not running it.
    """

    lookup = context_lookup or _context_lookup
    values: Dict[str, Any] = {}
    origins: Dict[str, str] = {}
    for spec in specs:
        name, source, value = spec["name"], spec["source"], str(spec["value"])
        if source == "context":
            key = value.strip() or name
            resolved = lookup(key, environment, project)
            if resolved is None:
                where = environment or "this environment"
                raise NotebookError("Notebook parameter %s reads the Context value %s, which is not set for %s%s. Add it under Context Settings."
                                    % (name, key, where, " in project " + project if project else ""))
            values[name] = infer_value(str(resolved))
            origins[name] = "context " + key
        elif source == "env":
            variable = value.strip() or name
            if variable not in os.environ:
                raise NotebookError("Notebook parameter %s reads the environment variable %s, which is not set." % (name, variable))
            values[name] = infer_value(os.environ[variable])
            origins[name] = "env " + variable
        else:
            values[name] = infer_value(value)
            origins[name] = "value"
    for name, value in (overrides or {}).items():
        if not PARAMETER_NAME.match(str(name)):
            raise NotebookError("%r is not a valid notebook parameter name: use a Python identifier." % name)
        values[str(name)] = infer_value(value) if isinstance(value, str) else value
        origins[str(name)] = "run override"
    return values, origins


def _python_literal(value: Any) -> str:
    return repr(value)


def _new_code_cell(source: str, tags: List[str]) -> Dict[str, Any]:
    return {
        "cell_type": "code",
        "execution_count": None,
        "id": uuid.uuid4().hex[:8],
        "metadata": {"tags": list(tags)},
        "outputs": [],
        "source": source,
    }


def inject_parameters(nb: Any, values: Dict[str, Any], header: str = "") -> Optional[int]:
    """Puts a cell tagged injected-parameters after the cell tagged
    parameters (or first), replacing one left by an earlier run. Returns its
    index, or None when there is nothing to inject."""

    nb["cells"] = [cell for cell in nb["cells"] if INJECTED_TAG not in _cell_tags(cell)]
    if not values:
        return None
    lines = ["# " + (header or "Parameters injected by JobSeeker")]
    lines += ["%s = %s" % (name, _python_literal(value)) for name, value in values.items()]
    cell = _new_code_cell("\n".join(lines) + "\n", [INJECTED_TAG])
    try:  # nbformat validates ids against the notebook's minor version
        import nbformat  # type: ignore

        cell = nbformat.from_dict(cell)
        if nb.get("nbformat_minor", 5) < 5 or nb.get("nbformat", 4) < 4:
            cell.pop("id", None)
    except ImportError:
        if nb.get("nbformat_minor", 5) < 5:
            cell.pop("id", None)
    position = 0
    for index, existing in enumerate(nb["cells"]):
        if PARAMETERS_TAG in _cell_tags(existing):
            position = index + 1
            break
    nb["cells"].insert(position, cell)
    return position


def notebook_parameters(nb: Any) -> List[Dict[str, Any]]:
    """The defaults in a notebook's parameters cell, for forms: name, value
    (as text) and comment of each top-level assignment."""

    for cell in nb.get("cells", []):
        if cell.get("cell_type") != "code" or PARAMETERS_TAG not in _cell_tags(cell):
            continue
        source = _source_text(cell)
        try:
            tree = ast.parse(source)
        except SyntaxError:
            return []
        lines = source.split("\n")
        found = []
        for node in tree.body:
            target = None
            if isinstance(node, ast.Assign) and len(node.targets) == 1 and isinstance(node.targets[0], ast.Name):
                target = node.targets[0].id
            elif isinstance(node, ast.AnnAssign) and isinstance(node.target, ast.Name) and node.value is not None:
                target = node.target.id
            if target is None:
                continue
            segment = ast.get_source_segment(source, node.value) if hasattr(ast, "get_source_segment") else None
            line = lines[node.lineno - 1] if 0 < node.lineno <= len(lines) else ""
            comment = line.split("#", 1)[1].strip() if "#" in line else ""
            found.append({"name": target, "value": segment if segment is not None else "", "comment": comment})
        return found
    return []


# --------------------------------------------------------------------------
# Reading and writing


def _read_notebook(path: str) -> Any:
    try:
        import nbformat  # type: ignore

        with open(path, "r", encoding="utf-8") as handle:
            return nbformat.read(handle, as_version=4)
    except ImportError:
        with open(path, "r", encoding="utf-8") as handle:
            nb = json.load(handle)
        if int(nb.get("nbformat", 0)) != 4:
            raise NotebookError("%s is notebook format %s; without nbformat only format 4 can be read." % (path, nb.get("nbformat")))
        return nb


def _write_notebook(nb: Any, path: str) -> None:
    directory = os.path.dirname(os.path.abspath(path))
    os.makedirs(directory, exist_ok=True)
    temporary = os.path.join(directory, ".%s.%s.tmp" % (os.path.basename(path), os.getpid()))
    with open(temporary, "w", encoding="utf-8") as handle:
        json.dump(nb, handle, indent=1, ensure_ascii=False, sort_keys=True)
        handle.write("\n")
    # Jobs run with umask 077 for their secrets; JobSeeker serves this file.
    os.chmod(temporary, 0o644)
    os.replace(temporary, path)


# --------------------------------------------------------------------------
# Execution


class _Run:
    """State of one notebook run: console, saved notebook, TMF."""

    def __init__(self, nb: Any, path: str, output: str, published_as: str, track: bool, console: _Console):
        self.nb = nb
        self.path = path
        self.output = output
        self.published_as = published_as
        self.console = console
        self.total = len(nb["cells"])
        self.code_total = sum(1 for cell in nb["cells"] if cell.get("cell_type") == "code" and _source_text(cell).strip())
        self.executed = 0
        self.started = time.time()
        self.cell_started = 0.0
        self.last_kind: Dict[int, str] = {}
        self.failed_cell: Optional[int] = None
        self.failure = ""
        self.failure_detail = ""
        self.tmf = None
        self.tmf_task = None
        self.meta = nb.setdefault("metadata", {}).setdefault("jobseeker", {})
        self.meta.update({
            "status": "running",
            "job": os.environ.get("JOBSEEKER_JOB_NAME") or os.environ.get("JOB_NAME", ""),
            "build": os.environ.get("BUILD_NUMBER", ""),
            "environment": os.environ.get("JOBSEEKER_ENVIRONMENT", ""),
            "notebook": path,
            "started": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(self.started)),
            "cells": {},
        })
        if track:
            self._tmf_begin()

    # TMF ---------------------------------------------------------------

    def _tmf_call(self, action: str, *args: Any, **kwargs: Any) -> None:
        if self.tmf_task is None:
            return
        try:
            getattr(self.tmf_task, action)(*args, **kwargs)
        except Exception as error:  # noqa: BLE001 - telemetry is best effort
            self.console.line("[JobSeeker] Transaction Monitoring could not record the notebook (%s): %s" % (action, error))
            self.tmf_task = None

    def _tmf_begin(self) -> None:
        try:
            from jobseeker import JobSeeker

            self.tmf = JobSeeker(install_signal_handlers=False)
            task = self.tmf.task(event_text="Notebook " + os.path.basename(self.path), dimension="notebook",
                                 records_total=self.code_total, msg="Running %d code cells" % self.code_total)
            task.__enter__()
            if task.open:
                self.tmf_task = task
                self.meta["tmf_instance"] = task.instance_id
        except Exception as error:  # noqa: BLE001 - telemetry is best effort
            self.console.line("[JobSeeker] Transaction Monitoring is not tracking this notebook: %s" % error)
            self.tmf_task = None

    # Saving ------------------------------------------------------------

    def save(self) -> None:
        if not self.output:
            return
        try:
            _write_notebook(self.nb, self.output)
        except Exception as error:  # noqa: BLE001 - a log is still worth more than a crash
            self.console.line("[JobSeeker] The executed notebook could not be saved to %s: %s" % (self.output, error))
            self.output = ""

    # Console -----------------------------------------------------------

    def display_path(self) -> str:
        """The notebook as the project names it (jobs/report/report.ipynb)."""
        absolute = os.path.abspath(self.path)
        for root in (os.environ.get("JOBSEEKER_PROJECT_ROOT", ""), os.environ.get("JOBSEEKER_SOURCE_DIR", "")):
            if root and absolute.startswith(os.path.abspath(root) + os.sep):
                return os.path.relpath(absolute, os.path.abspath(root)).replace(os.sep, "/")
        return self.path

    def start(self, kernel: str, values: Dict[str, Any], origins: Dict[str, str], injected: Optional[int]) -> None:
        self.console.marker("start", self.display_path(), "%d cells" % self.total, "%d code" % self.code_total, "kernel " + kernel)
        if values:
            described = {name: {"value": value, "from": origins.get(name, "value")} for name, value in values.items()}
            self.console.marker("parameters", json.dumps(described, default=str, sort_keys=False))
            self.meta["parameters"] = described
        if injected is not None:
            self.meta["injected_cell"] = injected
        self.save()
        # Named now so a log viewer can follow the notebook as cells finish.
        if self.published_as and self.output:
            self.console.marker("saved", self.published_as)

    def cell_header(self, index: int, cell: Any) -> None:
        kind = cell.get("cell_type", "code")
        tags = [tag for tag in _cell_tags(cell) if tag in (PARAMETERS_TAG, INJECTED_TAG, "skip-execution", "raises-exception")]
        parts = ["cell %d/%d" % (index + 1, self.total), kind]
        if tags:
            parts.append("tags " + ",".join(tags))
        self.console.marker(*parts)
        source = _source_text(cell).rstrip("\n")
        if source:
            for line in source.split("\n"):
                self.console.line(SOURCE_PREFIX + line)

    def cell_run(self, index: int) -> None:
        self.cell_started = time.time()
        self.console.marker("run %d/%d" % (index + 1, self.total))

    def cell_output(self, index: int, out: Any) -> None:
        kind = out.get("output_type")
        position = "%d/%d" % (index + 1, self.total)
        if kind == "stream":
            if self.last_kind.get(index) not in (None, "stream"):
                self.console.marker("stream " + position, out.get("name", "stdout"))
            text = out.get("text", "")
            self.console.write(_strip_ansi("".join(text) if isinstance(text, list) else text))
        elif kind == "error":
            self.console.marker("error " + position, out.get("ename", "Error"), _strip_ansi(out.get("evalue", ""))[:500])
            self.console.block("\n".join(out.get("traceback") or []))
        elif kind in ("execute_result", "display_data"):
            data = out.get("data") or {}
            label = "result" if kind == "execute_result" else "display"
            rich = [mime for mime in data if mime != "text/plain"]
            parts = [label + " " + position]
            if kind == "execute_result" and out.get("execution_count"):
                parts.append("Out[%s]" % out.get("execution_count"))
            for mime in rich:
                payload = data[mime]
                payload = "".join(payload) if isinstance(payload, list) else (payload if isinstance(payload, str) else json.dumps(payload))
                size = len(payload) * 3 // 4 if mime.startswith("image/") and mime != "image/svg+xml" else len(payload.encode("utf-8"))
                parts.append("%s %s" % (mime, _human_size(size)))
            self.console.marker(*parts)
            text = data.get("text/plain")
            if text is not None:
                self.console.block("".join(text) if isinstance(text, list) else str(text))
        self.last_kind[index] = kind or ""

    def cell_done(self, index: int, status: str, note: str = "") -> None:
        duration = time.time() - self.cell_started if self.cell_started else 0.0
        self.cell_started = 0.0
        parts = ["done %d/%d" % (index + 1, self.total), status, _seconds(duration)]
        if note:
            parts.append(note)
        self.console.marker(*parts)
        self.meta["cells"][str(index)] = {"status": status, "duration": round(duration, 3)}
        if status in ("ok", "error"):
            self.executed += 1
            self._tmf_call("progress", total=self.code_total, processed=self.executed,
                           msg="Cell %d of %d" % (index + 1, self.total))
        self.save()

    def cell_skipped(self, index: int, reason: str) -> None:
        self.console.marker("done %d/%d" % (index + 1, self.total), "skipped", reason)
        self.meta["cells"][str(index)] = {"status": "skipped", "reason": reason}

    def record_error(self, index: int, cell: Any) -> None:
        """Keeps the failing cell's traceback for Transaction Monitoring."""
        errors = [out for out in cell.get("outputs", []) if out.get("output_type") == "error"]
        if not errors:
            return
        lines = [_strip_ansi(line) for line in "\n".join(errors[-1].get("traceback") or []).split("\n")]
        lines = [line for line in lines if not re.match(r"^-{20,}$", line.strip()) and "Traceback (most recent call last)" not in line]
        self.failure_detail = "Traceback (most recent call last):\n  Notebook %s, cell %d\n%s" % (
            self.display_path(), index + 1, "\n".join("    " + line if line.strip() else line for line in lines).rstrip())

    def tmf_failure_message(self, message: str) -> str:
        text = (message or "Notebook failed") + ("\n\n" + self.failure_detail if self.failure_detail else "")
        return text if len(text) <= 4900 else text[:4880] + "\n[truncated]"

    def finish(self, status: str, message: str = "") -> None:
        elapsed = time.time() - self.started
        self.meta.update({"status": status.lower(), "finished": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                          "duration": round(elapsed, 3), "message": message})
        self.save()
        if self.published_as and self.output:
            self.console.marker("saved", self.published_as)
        self.console.marker("finish", status, "%d/%d code cells" % (self.executed, self.code_total), _seconds(elapsed))
        if status == "SUCCESS":
            self._tmf_call("finish", total=self.code_total, processed=self.executed,
                           msg="Ran %d code cells in %s" % (self.executed, _seconds(elapsed)))
        elif status == "CANCELLED":
            self._tmf_call("cancel", message or "Notebook cancelled")
        else:
            self._tmf_call("fail", self.tmf_failure_message(message), origin="Notebook cell %s" % (
                self.failed_cell + 1 if self.failed_cell is not None else "?"), type="Notebook Cell Error")
        if self.tmf is not None:
            try:
                self.tmf.close()
            except Exception:  # noqa: BLE001
                pass


def _kernel_name(nb: Any, requested: str) -> str:
    """The kernel to start: the requested one, the notebook's own when it is
    installed (an R notebook's "ir"), else the native Python kernel. A
    VS Code notebook names its virtual environment's kernel, which does not
    exist in a build; the Python running this runner is that environment."""

    spec = (nb.get("metadata") or {}).get("kernelspec") or {}
    language = str(spec.get("language") or (nb.get("metadata") or {}).get("language_info", {}).get("name") or "python").lower()
    wanted = requested or str(spec.get("name") or "")
    try:
        from jupyter_client.kernelspec import KernelSpecManager  # type: ignore

        available = KernelSpecManager().find_kernel_specs()
    except Exception:  # noqa: BLE001
        available = {}
    if requested:
        return requested
    if language != "python":
        if wanted in available:
            return wanted
        raise NotebookError("The notebook's kernel %s (%s) is not installed in this runtime. Choose a runtime with it, such as the R template." % (wanted or "?", language))
    return "python3"


def _execute_with_nbclient(run: _Run, kernel: str, cwd: str, cell_timeout: Optional[int], allow_errors: bool) -> None:
    from nbclient import NotebookClient  # type: ignore
    from nbclient.exceptions import CellExecutionError, CellTimeoutError, DeadKernelError  # type: ignore

    class StreamingClient(NotebookClient):  # type: ignore[misc, valid-type]
        def output(self, outs, msg, display_id, cell_index):  # type: ignore[no-untyped-def]
            out = super().output(outs, msg, display_id, cell_index)
            if out is not None:
                run.cell_output(cell_index, out)
            return out

    client = StreamingClient(run.nb, timeout=cell_timeout or None, startup_timeout=180, kernel_name=kernel,
                             allow_errors=allow_errors, record_timing=True, resources={"metadata": {"path": cwd}})
    # The kernel's own stderr (its start-up notices, a crash) goes to a file
    # shown only when the kernel fails; what cells print arrives as outputs.
    with tempfile.TemporaryFile(mode="w+", encoding="utf-8") as kernel_log:
        try:
            _run_cells(run, client, kernel_log, cell_timeout, CellExecutionError, CellTimeoutError, DeadKernelError)
        except Exception:
            kernel_log.seek(0)
            tail = [line for line in kernel_log.read().splitlines() if line.strip() and "without encryption" not in line][-20:]
            if tail:
                run.console.line("[JobSeeker] Kernel log:")
                run.console.block("\n".join(tail), "    ")
            raise


def _run_cells(run: _Run, client: Any, kernel_log: Any, cell_timeout: Optional[int],
               CellExecutionError: Any, CellTimeoutError: Any, DeadKernelError: Any) -> None:
    count = 0
    with client.setup_kernel(stderr=kernel_log):
        for index, cell in enumerate(run.nb["cells"]):
            run.cell_header(index, cell)
            if run.failed_cell is not None:
                run.cell_skipped(index, "an earlier cell failed")
                continue
            if cell.get("cell_type") != "code":
                continue
            if not _source_text(cell).strip():
                continue
            if "skip-execution" in _cell_tags(cell):
                run.cell_skipped(index, "tagged skip-execution")
                continue
            count += 1
            run.cell_run(index)
            try:
                client.execute_cell(cell, index, execution_count=count)
            except CellTimeoutError:
                run.failed_cell = index
                run.failure = "Cell %d ran longer than %ss." % (index + 1, cell_timeout)
                run.cell_done(index, "error", "timeout")
                continue
            except DeadKernelError as error:
                run.failed_cell = index
                run.failure = "The kernel died in cell %d: %s" % (index + 1, error)
                run.cell_done(index, "error", "kernel died")
                raise
            except CellExecutionError:
                run.failed_cell = index
                run.record_error(index, cell)
                errors = [out for out in cell.get("outputs", []) if out.get("output_type") == "error"]
                run.failure = "Cell %d raised %s" % (index + 1, (errors[-1].get("ename", "an error") + ": " + _strip_ansi(errors[-1].get("evalue", ""))) if errors else "an error")
                run.cell_done(index, "error")
                continue
            errors = [out for out in cell.get("outputs", []) if out.get("output_type") == "error"]
            run.cell_done(index, "error" if errors else "ok", "error allowed" if errors else "")
        try:
            client.set_widgets_metadata()
        except Exception:  # noqa: BLE001 - widget state is cosmetic
            pass


class _Tee(io.TextIOBase):
    """stdout/stderr of the plain runner: to the console and the cell."""

    def __init__(self, run: _Run, index: int, outputs: List[Dict[str, Any]], name: str):
        self.run, self.index, self.outputs, self.name = run, index, outputs, name

    def write(self, text: str) -> int:  # type: ignore[override]
        if not text:
            return 0
        out = {"output_type": "stream", "name": self.name, "text": text}
        if self.outputs and self.outputs[-1].get("output_type") == "stream" and self.outputs[-1].get("name") == self.name:
            self.outputs[-1]["text"] += text
        else:
            self.outputs.append(out)
        self.run.cell_output(self.index, out)
        return len(text)

    def flush(self) -> None:
        pass


def _execute_plain(run: _Run, cwd: str) -> None:
    """Fallback without Jupyter: plain Python cells, one shared namespace."""

    run.console.line("[JobSeeker] nbclient/ipykernel are not available: running the notebook's Python cells without a Jupyter kernel. IPython magics and shell escapes are skipped, and plots are not captured.")
    namespace: Dict[str, Any] = {"__name__": "__main__"}
    previous = os.getcwd()
    os.chdir(cwd)
    count = 0
    try:
        for index, cell in enumerate(run.nb["cells"]):
            run.cell_header(index, cell)
            if run.failed_cell is not None:
                run.cell_skipped(index, "an earlier cell failed")
                continue
            source = _source_text(cell)
            if cell.get("cell_type") != "code" or not source.strip():
                continue
            if "skip-execution" in _cell_tags(cell):
                run.cell_skipped(index, "tagged skip-execution")
                continue
            count += 1
            cell["execution_count"] = count
            outputs: List[Dict[str, Any]] = []
            cell["outputs"] = outputs
            run.cell_run(index)
            lines = [line if not line.lstrip().startswith(("%", "!")) else "pass  # skipped: " + line.strip() for line in source.split("\n")]
            code = "\n".join(lines)
            try:
                with contextlib.redirect_stdout(_Tee(run, index, outputs, "stdout")), contextlib.redirect_stderr(_Tee(run, index, outputs, "stderr")):
                    tree = ast.parse(code, filename="<cell %d>" % (index + 1))
                    last = tree.body.pop() if tree.body and isinstance(tree.body[-1], ast.Expr) else None
                    exec(compile(tree, "<cell %d>" % (index + 1), "exec"), namespace)  # noqa: S102 - running the job's own notebook
                    if last is not None:
                        value = eval(compile(ast.Expression(last.value), "<cell %d>" % (index + 1), "eval"), namespace)  # noqa: S307
                        if value is not None:
                            out = {"output_type": "execute_result", "execution_count": count, "metadata": {}, "data": {"text/plain": repr(value)}}
                            outputs.append(out)
                            run.cell_output(index, out)
            except Exception as error:  # noqa: BLE001 - the cell's own failure
                # The cell's own frames, not the runner's.
                frames = traceback.extract_tb(error.__traceback__)
                frames = [frame for frame in frames if frame.filename.startswith("<cell")] or frames
                out = {"output_type": "error", "ename": type(error).__name__, "evalue": str(error),
                       "traceback": ["Traceback (most recent call last):\n"] + traceback.format_list(frames)
                       + traceback.format_exception_only(type(error), error)}
                outputs.append(out)
                run.cell_output(index, out)
                run.failed_cell = index
                run.failure_detail = "".join(out["traceback"]).rstrip()
                run.failure = "Cell %d raised %s: %s" % (index + 1, type(error).__name__, error)
                run.cell_done(index, "error")
                continue
            run.cell_done(index, "ok")
    finally:
        os.chdir(previous)


def _jupyter_available() -> bool:
    try:
        import ipykernel  # type: ignore  # noqa: F401
        import nbclient  # type: ignore  # noqa: F401
        import nbformat  # type: ignore  # noqa: F401
    except ImportError:
        return False
    return True


def run_notebook(path: str, parameters: Any = None, output: str = "", published_as: str = "",
                 cell_timeout: Optional[int] = None, allow_errors: bool = False, kernel: str = "",
                 track: bool = True, cwd: str = "", overrides: Optional[Dict[str, Any]] = None,
                 console: Optional[_Console] = None) -> int:
    """Runs a notebook and returns the process exit code."""

    console = console or _Console()
    if not os.path.isfile(path):
        console.marker("finish", "FAILURE", "0/0 code cells", "0.00s")
        console.line("[JobSeeker] The notebook %s does not exist." % path)
        return EXIT_USAGE
    environment = os.environ.get("JOBSEEKER_ENVIRONMENT", "")
    project = os.environ.get("JOBSEEKER_PROJECT_NAME", "")
    try:
        nb = _read_notebook(path)
        values, origins = resolve_parameters(_parameter_specs(parameters), environment, project, overrides)
        header = "Parameters injected by JobSeeker%s" % (" for " + environment if environment else "")
        injected = inject_parameters(nb, values, header)
        kernel_name = _kernel_name(nb, kernel) if _jupyter_available() else "plain-python"
    except (NotebookError, OSError, ValueError) as error:
        console.line("[JobSeeker] %s" % error)
        console.marker("finish", "FAILURE", "0/0 code cells", "0.00s")
        return EXIT_USAGE

    run = _Run(nb, path, output, published_as, track, console)
    run.start(kernel_name, values, origins, injected)
    working_directory = cwd or os.path.dirname(os.path.abspath(path))

    def interrupt(signum: int, frame: Any) -> None:
        raise _Interrupted("signal %s" % signum)

    previous_handler = None
    try:
        previous_handler = signal.signal(signal.SIGTERM, interrupt)
    except ValueError:  # not the main thread
        pass

    status, code = "SUCCESS", EXIT_OK
    try:
        if kernel_name == "plain-python":
            _execute_plain(run, working_directory)
        else:
            _execute_with_nbclient(run, kernel_name, working_directory, cell_timeout, allow_errors)
        if run.failed_cell is not None:
            status, code = "FAILURE", EXIT_CELL_FAILED
    except (_Interrupted, KeyboardInterrupt):
        status, code = "CANCELLED", 130
        run.failure = "The notebook was stopped."
    except NotebookError as error:
        status, code = "FAILURE", EXIT_USAGE
        run.failure = str(error)
        console.line("[JobSeeker] %s" % error)
    except Exception as error:  # noqa: BLE001 - a kernel that would not start, or died
        status, code = "FAILURE", EXIT_KERNEL
        run.failure = run.failure or "The notebook kernel failed: %s" % error
        console.line("[JobSeeker] %s" % run.failure)
    finally:
        if previous_handler is not None:
            try:
                signal.signal(signal.SIGTERM, previous_handler)
            except ValueError:
                pass
    if run.failure and status != "SUCCESS":
        console.line("[JobSeeker] %s" % run.failure)
    run.finish(status, run.failure)
    return code


def _main(argv: Optional[List[str]] = None) -> int:
    parser = argparse.ArgumentParser(prog="jobseeker-notebook", description="Run Jupyter notebooks as JobSeeker jobs.")
    commands = parser.add_subparsers(dest="command")
    run = commands.add_parser("run", help="Run a notebook top to bottom.")
    run.add_argument("notebook")
    env = os.environ.get
    run.add_argument("--parameters", default=env("JOBSEEKER_NOTEBOOK_SPEC", ""),
                     help="JSON list of {name, source: value|context|env, value}, or a JSON object; b64: and @file accepted.")
    run.add_argument("--output", default=env("JOBSEEKER_NOTEBOOK_OUTPUT", ""), help="Where to write the executed notebook (after every cell).")
    run.add_argument("--published-as", default=env("JOBSEEKER_NOTEBOOK_PUBLISHED", ""), help="The executed notebook's path as JobSeeker serves it.")
    run.add_argument("--cell-timeout", type=int, default=_coerce_int(env("JOBSEEKER_NOTEBOOK_CELL_TIMEOUT", "0")),
                     help="Seconds one cell may run; 0 for no limit.")
    run.add_argument("--allow-errors", action="store_true", default=env("JOBSEEKER_NOTEBOOK_ALLOW_ERRORS", "0") == "1",
                     help="Run every cell even after one raises.")
    run.add_argument("--kernel", default=env("JOBSEEKER_NOTEBOOK_KERNEL", ""), help="Kernel name; by default the notebook's language decides.")
    run.add_argument("--no-tmf", action="store_true", default=env("JOBSEEKER_NOTEBOOK_TMF", "1") == "0",
                     help="Do not record the run in Transaction Monitoring.")
    run.add_argument("--cwd", default="", help="Working directory of the kernel; the notebook's folder by default.")
    params = commands.add_parser("parameters", help="Print the parameters cell's defaults as JSON.")
    params.add_argument("notebook")
    args = parser.parse_args(argv)

    if args.command == "parameters":
        print(json.dumps(notebook_parameters(_read_notebook(args.notebook)), indent=2))
        return EXIT_OK
    if args.command != "run":
        parser.print_help()
        return EXIT_USAGE

    overrides: Dict[str, Any] = {}
    raw_overrides = os.environ.get("JOBSEEKER_NOTEBOOK_PARAMETERS", "").strip()
    if raw_overrides:
        try:
            overrides = json.loads(raw_overrides)
        except ValueError:
            print("[JobSeeker] JOBSEEKER_NOTEBOOK_PARAMETERS is not a JSON object; the run overrides are ignored.")
            overrides = {}
        if not isinstance(overrides, dict):
            print("[JobSeeker] JOBSEEKER_NOTEBOOK_PARAMETERS must be a JSON object of name: value; the run overrides are ignored.")
            overrides = {}
    return run_notebook(args.notebook, parameters=args.parameters, output=args.output, published_as=args.published_as,
                        cell_timeout=args.cell_timeout or None, allow_errors=args.allow_errors, kernel=args.kernel,
                        track=not args.no_tmf, cwd=args.cwd, overrides=overrides)


def main() -> None:
    sys.exit(_main())


if __name__ == "__main__":  # pragma: no cover - module CLI
    main()
