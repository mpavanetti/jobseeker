#!/usr/bin/env python3
import json
import importlib.util
import os
import tempfile
from pathlib import Path

from jobseeker import DataAssetCatalog, JobSeeker, JobSeekerError


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


def main():
    test_documented_transform()
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
