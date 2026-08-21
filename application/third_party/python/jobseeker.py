"""Development shim for the installable JobSeeker Python SDK package."""

from __future__ import annotations

import importlib.util
import sys
from pathlib import Path


_SDK_ENTRYPOINT = Path(__file__).with_name("jobseeker_sdk") / "src" / "jobseeker" / "__init__.py"
_SPEC = importlib.util.spec_from_file_location("_jobseeker_runtime_sdk", _SDK_ENTRYPOINT)

if _SPEC is None or _SPEC.loader is None:
    raise ImportError("Unable to load JobSeeker SDK package from {}".format(_SDK_ENTRYPOINT))

_MODULE = importlib.util.module_from_spec(_SPEC)
sys.modules[_SPEC.name] = _MODULE
_SPEC.loader.exec_module(_MODULE)

__all__ = list(getattr(_MODULE, "__all__", ()))

for _name in __all__:
    globals()[_name] = getattr(_MODULE, _name)