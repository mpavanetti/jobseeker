#!/usr/bin/env python3
"""Compile every executable heredoc in the curated job sample catalog."""

from __future__ import annotations

import ast
import pathlib
import re
import subprocess
import tempfile


CATALOG = pathlib.Path(__file__).parents[1] / "application/config/job_samples.php"
SOURCE = CATALOG.read_text(encoding="utf-8")


def heredocs(marker: str) -> list[str]:
    pattern = re.compile(
        r"^[^\n]*<<<'" + re.escape(marker) + r"'\n(.*?)\n" + re.escape(marker) + r",?$",
        re.DOTALL | re.MULTILINE,
    )
    return pattern.findall(SOURCE)


python_sources = heredocs("PYTHON")
python_sources.extend(
    re.findall(r"(?<!<)<<'PYTHON'\n(.*?)\nPYTHON$", SOURCE, re.DOTALL | re.MULTILINE)
)
shell_sources = heredocs("SHELL")
assert len(python_sources) >= 9, "expected Python entry points, workspace modules, tests, and the embedded shell consumer"
assert len(shell_sources) >= 5, "expected every shell sample to be syntax checked"

for index, source in enumerate(python_sources, start=1):
    try:
        ast.parse(source, filename=f"job-sample-python-{index}.py")
    except SyntaxError as error:
        raise AssertionError(f"Python sample heredoc {index} is invalid: {error}") from error

for index, source in enumerate(shell_sources, start=1):
    with tempfile.NamedTemporaryFile("w", suffix=".sh", encoding="utf-8") as stream:
        stream.write(source)
        stream.flush()
        result = subprocess.run(["bash", "-n", stream.name], text=True, capture_output=True, check=False)
    assert result.returncode == 0, f"Shell sample heredoc {index} is invalid: {result.stderr.strip()}"

print(f"Job sample syntax checks passed ({len(shell_sources)} shell, {len(python_sources)} Python heredocs).")
