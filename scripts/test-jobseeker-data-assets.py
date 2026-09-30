#!/usr/bin/env python3
import json
import importlib.util
import os
import shutil
import sys
import tempfile
import threading
from pathlib import Path
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

from jobseeker import DataAssetCatalog, JobSeeker, JobSeekerError

sys.dont_write_bytecode = True


def test_documented_transform():
    example_path = Path(__file__).resolve().parents[1] / "doc" / "Python" / "code" / "data_asset_job.py"
    spec = importlib.util.spec_from_file_location("jobseeker_data_asset_example", example_path)
    assert spec is not None and spec.loader is not None
    example = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(example)

    summary, active_count = example.summarize_active_customers(
        [
            {"country": "UK", "active": "true"},
            {"country": "US", "active": "1"},
            {"country": "FI", "active": "false"},
            {"country": "US", "active": "yes"},
        ]
    )
    assert active_count == 3
    assert summary == [
        {"country": "UK", "active_customers": 1},
        {"country": "US", "active_customers": 2},
    ]


def contract(key, direction, environment, job, relative_path, required=True):
    return {
        "key": key,
        "name": key.replace("-", " ").title(),
        "uri": "jobseeker://{}/{}/{}".format(environment.lower(), "shared" if job == "*" else job, key),
        "direction": direction,
        "format": "csv",
        "environment": environment,
        "job": job,
        "relative_path": relative_path,
        "file_name": os.path.basename(relative_path),
        "required": required,
        "active": True,
        "version": 1,
        "options": {"delimiter": ",", "encoding": "UTF-8", "header": True},
    }


def test_documented_csv_transform():
    example_path = Path(__file__).resolve().parents[1] / "doc" / "Python" / "code" / "data_asset_job.py"
    sample_path = example_path.with_name("customer_reference.csv")
    spec = importlib.util.spec_from_file_location("jobseeker_data_asset_csv_example", example_path)
    assert spec is not None and spec.loader is not None
    example = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(example)

    with tempfile.TemporaryDirectory(prefix="jobseeker-data-asset-transform-") as repository:
        source_relative = "data-assets/dev/customer-transform/customer-reference/customers.csv"
        target_relative = "data-assets/dev/customer-transform/customer-summary/summary.csv"
        source_path = os.path.join(repository, source_relative)
        os.makedirs(os.path.dirname(source_path))
        shutil.copyfile(sample_path, source_path)

        payload = {
            "schema_version": 1,
            "assets": [
                contract("customer-reference", "input", "DEV", "customer-transform", source_relative),
                contract("customer-summary", "output", "DEV", "customer-transform", target_relative, required=False),
            ],
        }
        manifest_path = os.path.join(repository, "data-assets", "manifest.json")
        with open(manifest_path, "w", encoding="utf-8") as stream:
            json.dump(payload, stream)

        catalog = DataAssetCatalog(environment="DEV", job="customer-transform", repository_root=repository)
        source = catalog.resolve("customer-reference")
        target = catalog.resolve("customer-summary", mode="output")
        summary, active_count = example.summarize_active_customers(source.read())
        target.write(summary)

        assert active_count == 3
        with open(target.path, "r", encoding="utf-8") as stream:
            assert stream.read() == "country,active_customers\nUK,1\nUS,2\n"


def test_remote_source_materialization():
    class Handler(BaseHTTPRequestHandler):
        requests = 0

        def do_GET(self):
            Handler.requests += 1
            payload = b'{"data":{"items":[{"id":7,"state":"ready"}]}}'
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)

        def log_message(self, *args):
            pass

    server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    try:
        with tempfile.TemporaryDirectory(prefix="jobseeker-remote-data-asset-") as repository:
            relative_path = "data-assets/dev/remote-api/remote-items/items.json"
            item = contract("remote-items", "input", "DEV", "remote-api", relative_path)
            item.update({
                "format": "json",
                "source_type": "url",
                "source": {
                    "url": "http://127.0.0.1:%d/items" % server.server_port,
                    "response_path": "data.items",
                },
                "connector_key": None,
            })
            os.makedirs(os.path.join(repository, "data-assets"))
            with open(os.path.join(repository, "data-assets", "manifest.json"), "w", encoding="utf-8") as stream:
                json.dump({"schema_version": 2, "assets": [item]}, stream)

            asset = DataAssetCatalog(environment="DEV", job="remote-api", repository_root=repository).resolve("remote-items")
            assert asset is not None and asset.source_type == "url"
            assert asset.read() == [{"id": 7, "state": "ready"}]
            assert Path(asset.path).read_text(encoding="utf-8").startswith('{"data"')
            assert Handler.requests == 1, "one resolved asset instance should materialize its source once"
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=2)


def test_remote_source_credentials_stay_scoped():
    from jobseeker.sources import http_get

    seen = []

    class Target(BaseHTTPRequestHandler):
        def do_GET(self):
            seen.append((self.path, self.headers.get("Authorization")))
            if self.path == "/same":
                self.send_response(302)
                self.send_header("Location", "/final")
                self.end_headers()
                return
            if self.path == "/denied?key=super-secret-api-key":
                self.send_response(403)
                self.end_headers()
                return
            self.send_response(200)
            self.end_headers()
            self.wfile.write(b"ok")

        def log_message(self, *args):
            pass

    target = ThreadingHTTPServer(("127.0.0.1", 0), Target)

    class Origin(BaseHTTPRequestHandler):
        def do_GET(self):
            self.send_response(302)
            self.send_header("Location", "http://localhost:%d/elsewhere" % target.server_port)
            self.end_headers()

        def log_message(self, *args):
            pass

    origin = ThreadingHTTPServer(("127.0.0.1", 0), Origin)
    threads = [threading.Thread(target=server.serve_forever, daemon=True) for server in (target, origin)]
    for thread in threads:
        thread.start()
    try:
        credentials = {"Authorization": "Bearer connector-token"}
        http_get("http://127.0.0.1:%d/same" % target.server_port, credentials)
        assert seen[-1] == ("/final", "Bearer connector-token"), "a same-host redirect keeps the Connection credential"
        http_get("http://127.0.0.1:%d/start" % origin.server_port, credentials)
        assert seen[-1] == ("/elsewhere", None), "a cross-host redirect must not receive the Connection credential"
        try:
            http_get("http://127.0.0.1:%d/denied?key=super-secret-api-key" % target.server_port, {})
            raise AssertionError("an HTTP error must fail the read")
        except JobSeekerError as error:
            assert "super-secret-api-key" not in str(error), "HTTP errors must not print a URL's API key"
    finally:
        for server in (target, origin):
            server.shutdown()
            server.server_close()


def main():
    test_documented_transform()
    test_documented_csv_transform()
    test_remote_source_materialization()
    test_remote_source_credentials_stay_scoped()
    with tempfile.TemporaryDirectory(prefix="jobseeker-data-assets-") as repository:
        data_directory = os.path.join(repository, "data-assets")
        os.makedirs(os.path.join(data_directory, "all", "customer-reference"))
        os.makedirs(os.path.join(data_directory, "dev", "load-customers", "customer-reference"))
        os.makedirs(os.path.join(data_directory, "dev", "customer-summary"))

        shared_path = "data-assets/all/customer-reference/customers.csv"
        exact_path = "data-assets/dev/load-customers/customer-reference/customers.csv"
        output_path = "data-assets/dev/customer-summary/summary.csv"
        with open(os.path.join(repository, shared_path), "w", encoding="utf-8") as stream:
            stream.write("id,name\n1,Shared\n")
        with open(os.path.join(repository, exact_path), "w", encoding="utf-8") as stream:
            stream.write("id,name\n2,Exact\n")

        payload = {
            "schema_version": 1,
            "assets": [
                contract("customer-reference", "input", "ALL", "*", shared_path),
                contract("customer-reference", "input", "DEV", "load-customers", exact_path),
                contract("customer-summary", "output", "DEV", "*", output_path, required=False),
            ],
        }
        manifest = os.path.join(data_directory, "manifest.json")
        with open(manifest, "w", encoding="utf-8") as stream:
            json.dump(payload, stream)

        catalog = DataAssetCatalog(environment="DEV", job="load-customers", repository_root=repository)
        source = catalog.resolve("customer-reference")
        assert source is not None
        assert source.environment == "DEV" and source.job == "load-customers"
        assert os.fspath(source) == source.path and str(source) == source.path
        assert source.read() == [{"id": "2", "name": "Exact"}]

        fallback = catalog.resolve("customer-reference", environment="PROD", job="another-job")
        assert fallback is not None and fallback.environment == "ALL"
        assert fallback.read()[0]["name"] == "Shared"

        target = catalog.resolve("customer-summary", mode="output")
        assert target is not None
        target.write([{"country": "US", "customers": 2}])
        with open(target.path, "r", encoding="utf-8") as stream:
            assert stream.read() == "country,customers\nUS,2\n"

        assert catalog.resolve("missing", required=False) is None
        try:
            catalog.resolve("missing")
            raise AssertionError("required missing assets must fail with catalog guidance")
        except JobSeekerError as error:
            assert "Available input key(s): customer-reference" in str(error)
            assert "Data Assets" in str(error)
        try:
            source.write([{"id": 3}])
            raise AssertionError("input-only assets must reject writes")
        except JobSeekerError:
            pass

        with JobSeeker(environment="DEV", job="load-customers", install_signal_handlers=False) as seeker:
            seeker._data_asset_catalog = catalog
            assert seeker.dataset("customer-reference").path == source.path

        previous_asset_job = os.environ.get("JOBSEEKER_DATA_ASSET_JOB")
        os.environ["JOBSEEKER_DATA_ASSET_JOB"] = "previewed-job"
        try:
            with JobSeeker(environment="DEV", job="temporary-jenkins-job", install_signal_handlers=False) as seeker:
                assert seeker.data_assets.job == "previewed-job"
        finally:
            if previous_asset_job is None:
                os.environ.pop("JOBSEEKER_DATA_ASSET_JOB", None)
            else:
                os.environ["JOBSEEKER_DATA_ASSET_JOB"] = previous_asset_job

    print("JobSeeker Data Assets SDK tests passed.")


if __name__ == "__main__":
    main()
