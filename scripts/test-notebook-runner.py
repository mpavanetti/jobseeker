#!/usr/bin/env python3
"""Notebook jobs: the SDK runner (jobseeker.notebook) without a Jupyter kernel.

Parameters are typed and injected as papermill does, a Context or
environment parameter resolves or fails loudly, the console carries the
marker protocol job-console-notebook.js reads, and the plain runner (used
when nbclient/ipykernel are missing) executes cells, stops at the first
failure and saves the executed notebook.
"""

from __future__ import annotations

import io
import json
import os
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "application/third_party/python/jobseeker_sdk/src"))

from jobseeker import notebook  # noqa: E402

passed = 0


def check(name: str, condition: bool, detail: str = "") -> None:
    global passed
    if not condition:
        raise AssertionError(name + (": " + detail if detail else ""))
    passed += 1


def cell(kind: str, source: str, tags=None) -> dict:
    item = {"cell_type": kind, "id": "c%d" % cell.counter, "metadata": {"tags": tags} if tags else {}, "source": source}
    cell.counter += 1
    if kind == "code":
        item.update({"execution_count": None, "outputs": []})
    return item


cell.counter = 0


def write(path: Path, cells: list) -> None:
    path.write_text(json.dumps({"cells": cells, "metadata": {"kernelspec": {"name": "python3", "language": "python"}},
                                "nbformat": 4, "nbformat_minor": 5}), encoding="utf-8")


# Values are typed like papermill's -p.
infer = notebook.infer_value
check("integers", infer("42") == 42 and isinstance(infer("42"), int))
check("floats", infer("0.25") == 0.25)
check("JSON booleans", infer("true") is True and infer("false") is False)
check("Python booleans", infer("True") is True and infer("None") is None)
check("lists", infer("[1, 2]") == [1, 2])
check("quoted strings stay strings", infer('"007"') == "007")
check("text stays text", infer("emea") == "emea" and infer("2026-10-01") == "2026-10-01")
check("NaN is not a number", infer("NaN") == "NaN")

# Parameter specs: list, object, base64.
specs = notebook._parameter_specs('[{"name": "rows", "source": "context", "value": "rows"}, {"name": "region", "value": "eu"}]')
check("specs keep their source", specs[0]["source"] == "context" and specs[1]["source"] == "value")
check("objects are literal values", notebook._parameter_specs({"a": 1})[0] == {"name": "a", "source": "value", "value": "1"})
import base64  # noqa: E402
encoded = "b64:" + base64.b64encode(b'[{"name": "x", "value": "1"}]').decode()
check("b64: specs", notebook._parameter_specs(encoded)[0]["name"] == "x")
for bad in ('[{"name": "1bad"}]', '[{"name": "x", "source": "secret"}]', '{"not": "a list"', '"text"'):
    try:
        notebook._parameter_specs(bad)
        check("rejects " + bad, False)
    except notebook.NotebookError:
        check("rejects " + bad, True)

# Resolution: context, env, overrides; a missing context value fails.
calls = []


def lookup(key, environment, project):
    calls.append((key, environment, project))
    return {"rows": "12"}.get(key)


os.environ["JOBSEEKER_TEST_REGION"] = "apac"
values, origins = notebook.resolve_parameters(
    notebook._parameter_specs([{"name": "rows", "source": "context", "value": ""}, {"name": "region", "source": "env", "value": "JOBSEEKER_TEST_REGION"},
                               {"name": "limit", "source": "value", "value": "5"}]),
    "DEV", "notebooks", overrides={"limit": "7", "extra": True}, context_lookup=lookup)
check("context values are typed", values["rows"] == 12 and calls == [("rows", "DEV", "notebooks")], str(calls))
check("env values", values["region"] == "apac" and origins["region"] == "env JOBSEEKER_TEST_REGION")
check("run overrides win", values["limit"] == 7 and origins["limit"] == "run override" and values["extra"] is True)
try:
    notebook.resolve_parameters([{"name": "missing", "source": "context", "value": "nope"}], "QA", "notebooks", context_lookup=lookup)
    check("a missing context value fails", False)
except notebook.NotebookError as error:
    check("a missing context value fails", "nope" in str(error) and "QA" in str(error) and "notebooks" in str(error), str(error))

# Injection after the parameters cell, replacing an earlier injection.
nb = {"nbformat": 4, "nbformat_minor": 5, "cells": [cell("markdown", "# t"), cell("code", "rows = 1", ["parameters"]),
                                                     cell("code", "old = 1", ["injected-parameters"]), cell("code", "print(rows)")]}
position = notebook.inject_parameters(nb, {"rows": 3, "name": "x"}, "header")
check("injected after the parameters cell", position == 2 and "rows = 3" in nb["cells"][2]["source"] and "name = 'x'" in nb["cells"][2]["source"])
check("an earlier injection is replaced", sum(1 for c in nb["cells"] if "injected-parameters" in c["metadata"].get("tags", [])) == 1 and len(nb["cells"]) == 4)
plain = {"nbformat": 4, "nbformat_minor": 4, "cells": [cell("code", "print(1)")]}
check("without a parameters cell values go first", notebook.inject_parameters(plain, {"a": 1}) == 0 and "id" not in plain["cells"][0])
check("no values, no cell", notebook.inject_parameters({"cells": [cell("code", "x")]}, {}) is None)

# The parameters cell's defaults, for the form.
defaults = notebook.notebook_parameters({"cells": [cell("code", "rows = 200  # orders\nregion: str = 'us'\nprint(1)\n", ["parameters"])]})
check("parameters cell defaults", [d["name"] for d in defaults] == ["rows", "region"] and defaults[0]["comment"] == "orders" and defaults[1]["value"] == "'us'", str(defaults))

# The plain runner end to end, with the console protocol.
with tempfile.TemporaryDirectory() as directory:
    path = Path(directory) / "jobs" / "report" / "report.ipynb"
    path.parent.mkdir(parents=True)
    write(path, [
        cell("markdown", "# Report"),
        cell("code", "rows = 2\nlabel = 'x'", ["parameters"]),
        cell("code", "import os\nfor i in range(rows):\n    print(label, i)\nos.getcwd()"),
        cell("code", "%matplotlib inline\nassert rows < 3, 'too many'\n'checked'"),
        cell("code", "print('never')"),
    ])
    output = Path(directory) / "runs" / "report.ipynb"
    console = io.StringIO()
    os.environ["JOBSEEKER_PROJECT_ROOT"] = directory
    code = notebook.run_notebook(str(path), parameters='[{"name": "rows", "value": "5"}]', output=str(output),
                                 published_as="notebook-runs/job/1/report.ipynb", track=False, console=notebook._Console(console))
    log = console.getvalue()
    check("a failing cell fails the run", code == notebook.EXIT_CELL_FAILED, log)
    check("start marker names the project path", "[JobSeeker Notebook] start | jobs/report/report.ipynb | 6 cells | 5 code" in log, log)
    check("parameters marker", '[JobSeeker Notebook] parameters | {"rows": {"value": 5, "from": "value"}}' in log, log)
    check("sources are prefixed", "│ for i in range(rows):" in log)
    check("streams are printed", "x 0\nx 1\nx 2\nx 3\nx 4\n" in log)
    check("the kernel runs in the notebook's folder", "'%s'" % str(path.parent) in log, log)
    check("results are marked", "[JobSeeker Notebook] result 4/6 | Out[3]" in log, log)
    check("errors are marked", "[JobSeeker Notebook] error 5/6 | AssertionError | too many" in log, log)
    check("later cells are skipped", "[JobSeeker Notebook] done 6/6 | skipped | an earlier cell failed" in log)
    check("saved and finished", "[JobSeeker Notebook] saved | notebook-runs/job/1/report.ipynb" in log and "[JobSeeker Notebook] finish | FAILURE | 4/5 code cells" in log, log)
    saved = json.loads(output.read_text())
    meta = saved["metadata"]["jobseeker"]
    check("the executed notebook is saved", meta["status"] == "failure" and meta["cells"]["4"]["status"] == "error" and meta["cells"]["5"]["status"] == "skipped", json.dumps(meta))
    check("tracebacks start at the cell", "notebook.py" not in json.dumps(saved["cells"][4]["outputs"][-1]["traceback"]) and "<cell 5>" in json.dumps(saved["cells"][4]["outputs"][-1]["traceback"]))
    check("outputs are kept", saved["cells"][3]["outputs"][0]["output_type"] == "stream" and saved["cells"][4]["outputs"][-1]["output_type"] == "error")
    check("the injected cell is saved", "injected-parameters" in saved["cells"][2]["metadata"]["tags"])

    # Errors allowed in the JSON spec but a broken spec stops before running.
    console = io.StringIO()
    code = notebook.run_notebook(str(path), parameters='[{"name": "bad name"}]', output="", track=False, console=notebook._Console(console))
    check("bad parameters stop the run", code == notebook.EXIT_USAGE and "not a valid notebook parameter name" in console.getvalue())
    console = io.StringIO()
    check("a missing notebook", notebook.run_notebook(str(path) + ".missing", track=False, console=notebook._Console(console)) == notebook.EXIT_USAGE)

print("Notebook runner tests passed (%d checks)." % passed)
