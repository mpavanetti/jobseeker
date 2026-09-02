"""Runtime client for the JobSeeker ML platform (stdlib only)."""

from __future__ import annotations

import io
import json
import mimetypes
import os
import sys
import tempfile
import time
import uuid
from typing import Any, Dict, Iterable, Optional
from urllib import request as _request
from urllib.error import HTTPError, URLError

_TERMINAL_TIMEOUT = 20


def _env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


class Run:
    """Handle for the current run. Usually you use the module-level helpers and
    never touch this directly, but ``ml.active_run()`` returns it."""

    def __init__(self) -> None:
        self.api = _env("JOBSEEKER_ML_API")
        self.run_key = _env("JOBSEEKER_ML_RUN_KEY") or _env("JOBSEEKER_ML_RUN") or ""
        self.token = _env("JOBSEEKER_ML_RUN_TOKEN") or _env("JOBSEEKER_ML_API_TOKEN")
        self.environment = _env("JOBSEEKER_ML_ENVIRONMENT")
        self.job_key = _env("JOBSEEKER_ML_JOB_KEY")
        self.run_type = _env("JOBSEEKER_ML_RUN_TYPE")
        self.offline = not (self.api and self.run_key)
        try:
            self.params: Dict[str, Any] = json.loads(_env("JOBSEEKER_ML_PARAMS") or "{}")
        except ValueError:
            self.params = {}
        try:
            self._datasets: Dict[str, Any] = json.loads(_env("JOBSEEKER_ML_DATASETS") or "{}")
        except ValueError:
            self._datasets = {}
        self._step = 0
        if self.offline:
            print("[jobseeker_ml] offline mode (JOBSEEKER_ML_API/RUN_KEY unset) - calls will print only", file=sys.stderr)

    # -- transport ---------------------------------------------------------

    def _url(self, path: str) -> str:
        return self.api.rstrip("/") + "/" + path.lstrip("/")

    def _headers(self, extra: Optional[Dict[str, str]] = None) -> Dict[str, str]:
        headers = {"Accept": "application/json"}
        if self.token:
            headers["Authorization"] = "Bearer " + self.token
        if extra:
            headers.update(extra)
        return headers

    def _post_json(self, path: str, payload: Dict[str, Any], timeout: int = 15) -> Dict[str, Any]:
        if self.offline:
            print(f"[jobseeker_ml] {path} <- {json.dumps(payload)[:400]}")
            return {"ok": True, "offline": True}
        body = json.dumps(payload).encode("utf-8")
        req = _request.Request(
            self._url(path), data=body, method="POST",
            headers=self._headers({"Content-Type": "application/json"}),
        )
        return self._send(req, timeout)

    def _post_multipart(self, path: str, fields: Dict[str, str], file_field: str,
                        file_name: str, file_bytes: bytes, content_type: Optional[str] = None,
                        timeout: int = 120) -> Dict[str, Any]:
        if self.offline:
            print(f"[jobseeker_ml] {path} <- multipart {file_name} ({len(file_bytes)} bytes) {fields}")
            return {"ok": True, "offline": True}
        boundary = "----jobseekerml" + uuid.uuid4().hex
        content_type = content_type or (mimetypes.guess_type(file_name)[0] or "application/octet-stream")
        buf = io.BytesIO()
        for key, value in fields.items():
            buf.write(f"--{boundary}\r\n".encode())
            buf.write(f'Content-Disposition: form-data; name="{key}"\r\n\r\n'.encode())
            buf.write(f"{value}\r\n".encode())
        buf.write(f"--{boundary}\r\n".encode())
        buf.write(
            f'Content-Disposition: form-data; name="{file_field}"; filename="{file_name}"\r\n'.encode()
        )
        buf.write(f"Content-Type: {content_type}\r\n\r\n".encode())
        buf.write(file_bytes)
        buf.write(f"\r\n--{boundary}--\r\n".encode())
        req = _request.Request(
            self._url(path), data=buf.getvalue(), method="POST",
            headers=self._headers({"Content-Type": f"multipart/form-data; boundary={boundary}"}),
        )
        return self._send(req, timeout)

    def _send(self, req: "_request.Request", timeout: int) -> Dict[str, Any]:
        for attempt in range(3):
            try:
                with _request.urlopen(req, timeout=timeout) as resp:
                    raw = resp.read().decode("utf-8", "replace")
                    try:
                        return json.loads(raw) if raw else {"ok": True}
                    except ValueError:
                        return {"ok": True, "raw": raw}
            except HTTPError as exc:
                detail = exc.read().decode("utf-8", "replace")[:400]
                print(f"[jobseeker_ml] HTTP {exc.code} on {req.full_url}: {detail}", file=sys.stderr)
                if exc.code < 500:
                    return {"ok": False, "status": exc.code, "detail": detail}
            except (URLError, TimeoutError) as exc:
                print(f"[jobseeker_ml] transport error ({exc}) attempt {attempt + 1}/3", file=sys.stderr)
            time.sleep(1.5 * (attempt + 1))
        return {"ok": False, "detail": "giving up after retries"}

    # -- logging ---------------------------------------------------------

    def log_param(self, key: str, value: Any) -> None:
        self._post_json("ingest", {"run_key": self.run_key, "kind": "param",
                                   "params": {str(key): _jsonable(value)}})

    def log_params(self, params: Dict[str, Any]) -> None:
        self._post_json("ingest", {"run_key": self.run_key, "kind": "param",
                                   "params": {str(k): _jsonable(v) for k, v in params.items()}})

    def set_tags(self, tags: Dict[str, Any]) -> None:
        self._post_json("ingest", {"run_key": self.run_key, "kind": "tag",
                                   "tags": {str(k): str(v) for k, v in tags.items()}})

    def log_metric(self, key: str, value: float, step: Optional[int] = None) -> None:
        if step is None:
            step = self._step
        self._post_json("ingest", {"run_key": self.run_key, "kind": "metric",
                                   "metrics": [{"key": str(key), "value": float(value), "step": int(step)}]})

    def log_metrics(self, metrics: Dict[str, float], step: Optional[int] = None) -> None:
        if step is None:
            step = self._step
        points = [{"key": str(k), "value": float(v), "step": int(step)}
                  for k, v in metrics.items() if _is_number(v)]
        if points:
            self._post_json("ingest", {"run_key": self.run_key, "kind": "metric", "metrics": points})

    def step(self, step: int) -> None:
        self._step = int(step)

    def log_artifact(self, path: str, name: Optional[str] = None, role: str = "artifact") -> Dict[str, Any]:
        with open(path, "rb") as handle:
            data = handle.read()
        name = name or os.path.basename(path)
        return self._post_multipart("artifact",
                                    {"run_key": self.run_key, "role": role, "name": name},
                                    "file", name, data)

    def log_bytes(self, data: bytes, name: str, role: str = "artifact") -> Dict[str, Any]:
        return self._post_multipart("artifact",
                                    {"run_key": self.run_key, "role": role, "name": name},
                                    "file", name, data)

    def log_figure(self, figure: Any, name: str = "figure.png") -> Dict[str, Any]:
        buf = io.BytesIO()
        try:
            figure.savefig(buf, format="png", bbox_inches="tight", dpi=120)
        except AttributeError:
            import matplotlib.pyplot as plt  # noqa: WPS433

            plt.savefig(buf, format="png", bbox_inches="tight", dpi=120)
        return self.log_bytes(buf.getvalue(), name, role="figure")

    def log_confusion_matrix(self, matrix: Iterable[Iterable[float]], labels: Optional[Iterable] = None) -> None:
        payload = {"matrix": [[float(x) for x in row] for row in matrix]}
        if labels is not None:
            payload["labels"] = [str(x) for x in labels]
        self.log_bytes(json.dumps(payload).encode(), "confusion_matrix.json", role="evaluation")

    def log_feature_importance(self, importances: Dict[str, float]) -> None:
        clean = {str(k): float(v) for k, v in importances.items() if _is_number(v)}
        self.log_bytes(json.dumps(clean).encode(), "feature_importance.json", role="evaluation")

    # -- models ---------------------------------------------------------

    def log_model(self, model: Any, name: Optional[str] = None, framework: Optional[str] = None,
                  metrics: Optional[Dict[str, float]] = None, params: Optional[Dict[str, Any]] = None,
                  signature: Optional[Dict[str, Any]] = None, register: bool = True) -> Dict[str, Any]:
        path, framework = _serialize_model(model, framework)
        with open(path, "rb") as handle:
            data = handle.read()
        model_key = name or self.job_key or "model"
        fields = {
            "run_key": self.run_key,
            "model_key": _slug(model_key),
            "name": str(model_key),
            "framework": framework or "",
            "register": "1" if register else "0",
            "metrics_json": json.dumps(metrics or self._collected_metrics()),
            "params_json": json.dumps({str(k): _jsonable(v) for k, v in (params or self.params).items()}),
            "signature_json": json.dumps(signature or {}),
        }
        result = self._post_multipart("model", fields, "file", os.path.basename(path), data)
        if os.path.dirname(path).startswith(tempfile.gettempdir()):
            try:
                os.remove(path)
            except OSError:
                pass
        return result

    def _collected_metrics(self) -> Dict[str, float]:
        return {}

    def load_model(self, uri_or_role: str) -> Any:
        info = self._post_json("resolve-model", {"run_key": self.run_key, "ref": uri_or_role})
        url = info.get("download_url") if isinstance(info, dict) else None
        if not url:
            raise RuntimeError(f"Could not resolve model '{uri_or_role}': {info}")
        local = _download(self._abs(url), self.token)
        return _load_model(local, info.get("framework"))

    # -- datasets ---------------------------------------------------------

    def load_dataset(self, role_or_key: str, as_: str = "auto"):
        ref = self._datasets.get(role_or_key, role_or_key)
        if isinstance(ref, dict):
            ref = ref.get("dataset_key") or ref.get("key") or role_or_key
        info = self._post_json("resolve-dataset", {"run_key": self.run_key, "ref": str(ref), "role": role_or_key})
        if not isinstance(info, dict) or not info.get("download_url"):
            raise RuntimeError(f"Could not resolve dataset '{role_or_key}': {info}")
        local = _download(self._abs(info["download_url"]), self.token, suffix="." + (info.get("format") or "csv"))
        if as_ in ("path", "file"):
            return local
        try:
            import pandas as pd  # noqa: WPS433
        except ImportError:
            return local
        fmt = (info.get("format") or "csv").lower()
        if fmt in ("parquet",):
            return pd.read_parquet(local)
        if fmt in ("jsonl", "ndjson"):
            return pd.read_json(local, lines=True)
        if fmt == "json":
            return pd.read_json(local)
        return pd.read_csv(local)

    def save_dataset(self, data: Any, key: str, name: Optional[str] = None, role: str = "output",
                     fmt: str = "csv", profile: bool = True) -> Dict[str, Any]:
        path, fmt = _serialize_dataset(data, fmt)
        with open(path, "rb") as handle:
            payload = handle.read()
        fields = {
            "run_key": self.run_key,
            "dataset_key": _slug(key),
            "name": str(name or key),
            "role": role,
            "format": fmt,
            "profile": "1" if profile else "0",
        }
        result = self._post_multipart("dataset", fields, "file", f"{_slug(key)}.{fmt}", payload)
        if os.path.dirname(path).startswith(tempfile.gettempdir()):
            try:
                os.remove(path)
            except OSError:
                pass
        return result

    # -- typed dataset accessors --------------------------------------------

    @property
    def datasets(self) -> "_DatasetNamespace":
        if not hasattr(self, "_ns"):
            self._ns = _DatasetNamespace(self)
        return self._ns

    def dataset(self, key: str, version: Optional[int] = None) -> "Dataset":
        ref = key if version is None else f"{key}:{version}"
        return Dataset(self, role=key, ref=ref)

    def _resolve_dataset(self, role: str, ref: str) -> Dict[str, Any]:
        info = self._post_json("resolve-dataset", {"run_key": self.run_key, "ref": str(ref), "role": role})
        if not isinstance(info, dict) or not info.get("download_url"):
            raise RuntimeError(f"Could not resolve dataset '{role or ref}': {info}")
        return info

    # -- helpers ---------------------------------------------------------

    def _abs(self, url: str) -> str:
        if url.startswith("http://") or url.startswith("https://"):
            return url
        return self.api.split("/machine-learning/")[0].rstrip("/") + "/" + url.lstrip("/")

    def finish(self, status: str = "SUCCEEDED") -> None:
        self._post_json("ingest", {"run_key": self.run_key, "kind": "summary", "status": status}, timeout=_TERMINAL_TIMEOUT)


class Dataset:
    """A bound dataset for the current job. `ml.datasets.training` returns one.

    Attributes come from the platform on first touch: ``.schema`` (list of
    ``{name, type, nullable}``), ``.version``, ``.rows``, ``.format``. ``.read()``
    downloads it (pandas when available, else a file path); ``.path`` is the
    local file. Everything is lazy - creating the object does no I/O.
    """

    def __init__(self, run: "Run", role: str, ref: Optional[str] = None) -> None:
        self._run = run
        self.role = role
        self._ref = ref if ref is not None else role
        self._info: Optional[Dict[str, Any]] = None
        self._local: Optional[str] = None

    def _resolve(self) -> Dict[str, Any]:
        if self._info is None:
            self._info = self._run._resolve_dataset(self.role, self._ref)
        return self._info

    @property
    def schema(self) -> list:
        return list(self._resolve().get("schema") or [])

    @property
    def columns(self) -> list:
        return [c.get("name") for c in self.schema]

    @property
    def version(self) -> Optional[int]:
        return self._resolve().get("version")

    @property
    def rows(self) -> Optional[int]:
        return self._resolve().get("row_count")

    @property
    def format(self) -> str:
        return str(self._resolve().get("format") or "csv")

    @property
    def path(self) -> str:
        if self._local is None:
            info = self._resolve()
            self._local = _download(self._run._abs(info["download_url"]), self._run.token,
                                    suffix="." + self.format)
        return self._local

    def read(self, as_: str = "auto"):
        if as_ in ("path", "file"):
            return self.path
        fmt = self.format.lower()
        if as_ == "polars":
            import polars as pl  # noqa: WPS433

            return pl.read_parquet(self.path) if fmt == "parquet" else pl.read_csv(self.path)
        try:
            import pandas as pd  # noqa: WPS433
        except ImportError:
            return self.path
        if fmt == "parquet":
            return pd.read_parquet(self.path)
        if fmt in ("jsonl", "ndjson"):
            return pd.read_json(self.path, lines=True)
        if fmt == "json":
            return pd.read_json(self.path)
        return pd.read_csv(self.path)

    def describe(self):
        frame = self.read()
        return frame.describe() if hasattr(frame, "describe") else frame

    def __repr__(self) -> str:
        try:
            info = self._resolve()
            return f"<Dataset {self.role} v{info.get('version')} · {info.get('row_count')} rows · {len(self.schema)} cols>"
        except Exception:  # noqa: BLE001
            return f"<Dataset {self.role} (unresolved)>"


class _DatasetNamespace:
    """``ml.datasets.<role>`` / ``ml.datasets['role']`` -> Dataset."""

    def __init__(self, run: "Run") -> None:
        self.__dict__["_run"] = run
        try:
            self.__dict__["_bindings"] = json.loads(_env("JOBSEEKER_ML_DATASETS") or "{}")
        except ValueError:
            self.__dict__["_bindings"] = {}

    def __getattr__(self, role: str) -> "Dataset":
        if role.startswith("_"):
            raise AttributeError(role)
        return self[role]

    def __getitem__(self, role: str) -> "Dataset":
        binding = self._bindings.get(role, {})
        ref = role
        if isinstance(binding, dict):
            key = binding.get("dataset_key") or binding.get("asset") or role
            ver = binding.get("version")
            ref = key if ver in (None, "", "latest") else f"{key}:{ver}"
        elif isinstance(binding, str):
            ref = binding
        return Dataset(self._run, role=role, ref=ref)

    def __iter__(self):
        return iter(self._bindings)

    def __contains__(self, role: str) -> bool:
        return role in self._bindings


# --- module-level singleton + helpers -----------------------------------

_RUN: Optional[Run] = None


def init(force: bool = False) -> Run:
    global _RUN
    if _RUN is None or force:
        _RUN = Run()
    return _RUN


def active_run() -> Run:
    return init()


def log_param(key: str, value: Any) -> None:
    init().log_param(key, value)


def log_params(params: Dict[str, Any]) -> None:
    init().log_params(params)


def set_tags(tags: Dict[str, Any]) -> None:
    init().set_tags(tags)


def log_metric(key: str, value: float, step: Optional[int] = None) -> None:
    init().log_metric(key, value, step)


def log_metrics(metrics: Dict[str, float], step: Optional[int] = None) -> None:
    init().log_metrics(metrics, step)


def log_artifact(path: str, name: Optional[str] = None, role: str = "artifact") -> Dict[str, Any]:
    return init().log_artifact(path, name, role)


def log_figure(figure: Any, name: str = "figure.png") -> Dict[str, Any]:
    return init().log_figure(figure, name)


def log_confusion_matrix(matrix: Iterable[Iterable[float]], labels: Optional[Iterable] = None) -> None:
    init().log_confusion_matrix(matrix, labels)


def log_feature_importance(importances: Dict[str, float]) -> None:
    init().log_feature_importance(importances)


def log_model(model: Any, name: Optional[str] = None, framework: Optional[str] = None,
              metrics: Optional[Dict[str, float]] = None, params: Optional[Dict[str, Any]] = None,
              signature: Optional[Dict[str, Any]] = None, register: bool = True) -> Dict[str, Any]:
    return init().log_model(model, name, framework, metrics, params, signature, register)


def load_model(uri_or_role: str) -> Any:
    return init().load_model(uri_or_role)


def load_dataset(role_or_key: str, as_: str = "auto"):
    return init().load_dataset(role_or_key, as_)


def save_dataset(data: Any, key: str, name: Optional[str] = None, role: str = "output",
                 fmt: str = "csv", profile: bool = True) -> Dict[str, Any]:
    return init().save_dataset(data, key, name, role, fmt, profile)


def dataset(key: str, version: Optional[int] = None) -> "Dataset":
    return init().dataset(key, version)


class _DatasetsProxy:
    """Module-level ``ml.datasets`` - forwards every access to the active run's
    namespace so ``import jobseeker_ml as ml; ml.datasets.training`` just works."""

    def __getattr__(self, role: str):
        if role.startswith("_"):
            raise AttributeError(role)
        return getattr(init().datasets, role)

    def __getitem__(self, role: str):
        return init().datasets[role]

    def __iter__(self):
        return iter(init().datasets)

    def __contains__(self, role: str) -> bool:
        return role in init().datasets


datasets = _DatasetsProxy()


# --- serialization utilities ------------------------------------------------

def _jsonable(value: Any) -> Any:
    if _is_number(value) or isinstance(value, (str, bool)) or value is None:
        return value
    return str(value)


def _is_number(value: Any) -> bool:
    try:
        float(value)
        return True
    except (TypeError, ValueError):
        return False


def _slug(value: str) -> str:
    out = "".join(ch.lower() if ch.isalnum() else "-" for ch in str(value))
    while "--" in out:
        out = out.replace("--", "-")
    return out.strip("-") or "item"


def _serialize_model(model: Any, framework: Optional[str]):
    if isinstance(model, str) and os.path.exists(model):
        return model, framework or "file"
    tmp = tempfile.mkdtemp(prefix="jsml_model_")
    module = type(model).__module__ or ""
    if "torch" in module or (framework or "").lower() in ("pytorch", "torch"):
        import torch  # noqa: WPS433

        path = os.path.join(tmp, "model.pt")
        torch.save(model.state_dict() if hasattr(model, "state_dict") else model, path)
        return path, "pytorch"
    try:
        import joblib  # noqa: WPS433

        path = os.path.join(tmp, "model.joblib")
        joblib.dump(model, path)
        return path, framework or ("sklearn" if "sklearn" in module else module.split(".")[0] or "joblib")
    except ImportError:
        import pickle  # noqa: WPS433

        path = os.path.join(tmp, "model.pkl")
        with open(path, "wb") as handle:
            pickle.dump(model, handle)
        return path, framework or "pickle"


def _load_model(path: str, framework: Optional[str]):
    framework = (framework or "").lower()
    if framework in ("pytorch", "torch") or path.endswith(".pt"):
        import torch  # noqa: WPS433

        return torch.load(path, map_location="cpu")
    try:
        import joblib  # noqa: WPS433

        return joblib.load(path)
    except Exception:  # noqa: BLE001
        import pickle  # noqa: WPS433

        with open(path, "rb") as handle:
            return pickle.load(handle)


def _serialize_dataset(data: Any, fmt: str):
    if isinstance(data, str) and os.path.exists(data):
        return data, (os.path.splitext(data)[1].lstrip(".") or fmt)
    tmp = tempfile.mkdtemp(prefix="jsml_ds_")
    try:
        import pandas as pd  # noqa: WPS433
    except ImportError:
        path = os.path.join(tmp, "dataset.json")
        with open(path, "w", encoding="utf-8") as handle:
            json.dump(list(data), handle)
        return path, "json"
    frame = data if isinstance(data, pd.DataFrame) else pd.DataFrame(data)
    if fmt == "parquet":
        path = os.path.join(tmp, "dataset.parquet")
        frame.to_parquet(path, index=False)
        return path, "parquet"
    path = os.path.join(tmp, "dataset.csv")
    frame.to_csv(path, index=False)
    return path, "csv"


def _download(url: str, token: str, suffix: str = "") -> str:
    headers = {"Authorization": "Bearer " + token} if token else {}
    req = _request.Request(url, headers=headers)
    fd, path = tempfile.mkstemp(suffix=suffix)
    with os.fdopen(fd, "wb") as out, _request.urlopen(req, timeout=120) as resp:
        while True:
            chunk = resp.read(1 << 20)
            if not chunk:
                break
            out.write(chunk)
    return path
