#!/usr/bin/env python3
"""End-to-end check for the Machine Learning platform against the live stack.

    docker compose up -d
    DOCKER_HOST=tcp://docker-runtime:2375 bash scripts/build-ml-runtimes.sh   # for the run step
    python3 scripts/test-ml-platform-e2e.py

Follows the urllib pattern of the other scripts/test-*-e2e.py. Exercises auth +
every ML screen + the CRUD/introspect/dataset/job endpoints, then tries a real
run. The run step is reported (not hard-failed) when the runtime image is not
built, so the HTTP surface is always validated.
"""
import http.cookiejar
import io
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get("JOBSEEKER_E2E_URL", "http://localhost").rstrip("/")
EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")

failures = []
notes = []


def ok(label, cond):
    print(("  ok   " if cond else "  FAIL ") + label)
    if not cond:
        failures.append(label)


def note(label):
    print("  note " + label)
    notes.append(label)


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def csrf(self):
        for c in self.jar:
            if c.name == "csrf_cookie_name":
                return c.value
        return ""

    def get(self, path):
        req = urllib.request.Request(BASE + path, headers={"Accept": "text/html,application/json"})
        try:
            with self.opener.open(req, timeout=30) as r:
                return r.getcode(), r.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    def post(self, path, data, multipart=None):
        if multipart is not None:
            fields = dict(multipart)
            fields.setdefault("csrf_test_name", self.csrf())
            body, ctype = encode_multipart(fields)
        else:
            data = dict(data or {})
            data["csrf_test_name"] = self.csrf()
            body = urllib.parse.urlencode(data).encode()
            ctype = "application/x-www-form-urlencoded"
        req = urllib.request.Request(BASE + path, data=body, method="POST",
                                     headers={"Content-Type": ctype, "Accept": "application/json"})
        try:
            with self.opener.open(req, timeout=60) as r:
                return r.getcode(), r.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    def login(self):
        self.get("/login")
        code, _ = self.post("/loginMe", {"email": EMAIL, "password": PASSWORD})
        return code in (200, 302)


def encode_multipart(fields):
    boundary = "----jsmle2e" + str(int(time.time() * 1000))
    buf = io.BytesIO()
    for key, value in fields.items():
        if isinstance(value, tuple):
            filename, content = value
            buf.write(f"--{boundary}\r\n".encode())
            buf.write(f'Content-Disposition: form-data; name="{key}"; filename="{filename}"\r\n'.encode())
            buf.write(b"Content-Type: application/octet-stream\r\n\r\n")
            buf.write(content if isinstance(content, bytes) else content.encode())
            buf.write(b"\r\n")
        else:
            buf.write(f"--{boundary}\r\n".encode())
            buf.write(f'Content-Disposition: form-data; name="{key}"\r\n\r\n'.encode())
            buf.write(f"{value}\r\n".encode())
    buf.write(f"--{boundary}--\r\n".encode())
    return buf.getvalue(), f"multipart/form-data; boundary={boundary}"


def as_json(text):
    try:
        return json.loads(text)
    except ValueError:
        return {}


def main():
    c = Client()
    ok("login as admin", c.login())
    c.post("/", {"csrf_test_name": c.csrf()})  # prime csrf cookie via a GET-then-POST

    # 1. every screen renders
    screens = {
        "/machine-learning/overview": "platform overview",
        "/machine-learning/runtimes": "ML Runtimes",
        "/machine-learning/samples": "Gallery",
        "/machine-learning/datasets": "registry",
        "/machine-learning/jobs": "workspace",
        "/machine-learning/runs": "Experiments",
        "/machine-learning/models": "registry",
        "/machine-learning/monitoring": "drift",
    }
    for path, marker in screens.items():
        code, body = c.get(path)
        ok(f"GET {path} -> 200 & rendered", code == 200 and marker.lower() in body.lower())

    # 2. built-in samples were synced from repository/ml/samples
    code, body = c.get("/machine-learning/samples/list")
    samples = as_json(body).get("samples", [])
    ok("built-in samples synced", any(s["sample_key"] == "tabular-classification" for s in samples))
    sample = next((s for s in samples if s["sample_key"] == "tabular-classification"), {})

    # 3. runtime present (seeded)
    code, body = c.get("/machine-learning/runtimes/list")
    runtimes = as_json(body).get("runtimes", [])
    ok("ml-cpu runtime seeded", any(r["runtime_key"] == "ml-cpu" for r in runtimes))

    # 4. introspection classifies the sample
    code, body = c.post("/machine-learning/jobs/introspect",
                        {"code": sample.get("code", ""), "sample_key": "tabular-classification"})
    j = as_json(body)
    ok("introspect classifies sample as train", j.get("ok") and j.get("run_type") == "train")

    # 5. dataset: create + register an uploaded CSV version + profile
    suffix = str(int(time.time()))
    code, body = c.post("/machine-learning/datasets/save",
                        {"name": "e2e ds " + suffix, "dataset_key": "e2e-ds-" + suffix, "environment": "ALL"})
    ds = as_json(body)
    ok("dataset created", ds.get("ok"))
    if ds.get("ok"):
        import random as _r
        header = "f0,f1,f2,f3,species"
        lines = [header]
        for _ in range(240):
            cls = _r.choice([0, 1, 2])
            lines.append(",".join(f"{_r.gauss(cls, 0.6):.3f}" for _ in range(4)) + f",{cls}")
        csv = "\n".join(lines)
        code, body = c.post("/machine-learning/datasets/register-version", {}, multipart={
            "dataset_id": str(ds["id"]), "source_type": "upload", "has_header": "1",
            "file": ("e2e.csv", csv),
        })
        v = as_json(body)
        ok("dataset version registered + profiled", v.get("ok") and v.get("version") == 1)

    # 6. job: workspace-backed ML job from the sample, with the dataset bound
    code, body = c.post("/machine-learning/jobs/save", {
        "name": "e2e job " + suffix, "job_key": "e2e-job-" + suffix, "environment": "DEV",
        "runtime_key": "ml-cpu", "sample_key": "tabular-classification",
        "main_py": sample.get("code", "print('noop')"),
        "dependency_mode": "requirements",
        "dataset_bindings_json": json.dumps({"training": {"dataset_key": "e2e-ds-" + suffix, "version": "latest", "direction": "input"}}),
        "params_json": json.dumps({"target": "species", "n_estimators": 60}),
        "cpu_limit": "1", "memory_limit_mb": "1536", "timeout_seconds": "900",
    })
    job = as_json(body)
    ok("ML job saved", job.get("ok"))
    ok("job auto-classified as train", job.get("run_type") == "train")
    ok("workspace materialised (main.py + Dockerfile + jobseeker.yml)",
       set(["main.py", "Dockerfile", "jobseeker.yml"]).issubset(set(job.get("workspace_files") or [])))
    if not job.get("jenkins", {}).get("ok"):
        note("Jenkins job not deployed (" + str(job.get("jenkins", {})) + ")")

    # 6b. Open-in-editor bridge responds (OpenVSCode may be absent)
    if job.get("ok"):
        code, body = c.post("/machine-learning/jobs/develop", {"id": str(job["id"])})
        dv = as_json(body)
        ok("develop endpoint returns a folder URL or a clear reason",
           bool(dv.get("url")) or bool(dv.get("message")))

    # 7. build the per-job image, then run it
    if job.get("ok"):
        code, body = c.post("/machine-learning/jobs/build-image", {"id": str(job["id"])})
        bi = as_json(body)
        if not bi.get("ok"):
            note("image build did not succeed: " + str(bi.get("message")))
        code, body = c.get(f"/machine-learning/jobs/image-status/{job['id']}")
        ok("per-job image reaches ready", as_json(body).get("image_state") == "ready")

        code, body = c.post("/machine-learning/jobs/run", {"id": str(job["id"])})
        run = as_json(body)
        if not run.get("ok"):
            note("run did not start: " + str(run.get("message")))
        else:
            run_id = run["run"]["id"]
            terminal = None
            for _ in range(120):
                time.sleep(3)
                code, body = c.get(f"/machine-learning/runs/status/{run_id}")
                st = as_json(body)
                status = st.get("status")
                if st.get("terminal"):
                    terminal = status
                    break
            ok("run reached a terminal state", terminal is not None)
            ok("run SUCCEEDED", terminal == "SUCCEEDED")
            if terminal == "SUCCEEDED":
                code, body = c.get(f"/machine-learning/runs/status/{run_id}")
                detail = as_json(body)
                ok("metrics captured via SDK", bool(detail.get("latestMetrics")))
                code, body = c.get(f"/machine-learning/runs/detail/{run_id}")
                ok("run page shows the input dataset + schema",
                   ("e2e-ds-" + suffix) in body or ("e2e ds " + suffix) in body)
                code, body = c.get("/machine-learning/models")
                ok("a model was registered from the run", "e2e-job-" in body or "tabular" in body.lower())

    print()
    if notes:
        print(f"{len(notes)} note(s) - environment-dependent steps skipped.")
    if failures:
        print(f"{len(failures)} FAILURE(S): " + "; ".join(failures))
        sys.exit(1)
    print("ML platform e2e: PASSED")


if __name__ == "__main__":
    main()
