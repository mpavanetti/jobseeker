#!/usr/bin/env python3
"""Unit checks for the Data Asset preview engine (jobseeker.sources).

Run inside the data-preview image, which has the Excel and Parquet readers:
    docker compose exec -T data-preview python - < scripts/test-data-asset-preview.py
Elsewhere, the Excel, Parquet, and service checks are skipped.
"""

import importlib.util
import io
import json
import os
import tempfile
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

from jobseeker import Connector, JobSeekerError, data_asset_from_item, sources


def connector(connector_type, **config):
    return Connector(key="fixture", type=connector_type, environment="DEV", job="*", config=config, secrets={})


def rejects(function, *arguments):
    try:
        function(*arguments)
    except JobSeekerError:
        return
    raise AssertionError("expected a JobSeekerError for %r" % (arguments,))


def asset_item(**overrides):
    item = {
        "key": "fixture", "name": "Fixture", "direction": "input", "format": "csv", "environment": "DEV",
        "job": "*", "relative_path": "data-assets/dev/fixture/fixture.csv", "file_name": "fixture.csv",
        "required": True, "options": {"delimiter": ",", "header": True}, "source_type": "upload", "source": {},
    }
    item.update(overrides)
    return item


class JsonServer:
    """A local HTTP server answering every GET with one JSON document."""

    def __init__(self, payload):
        body = json.dumps(payload).encode("utf-8")

        class Handler(BaseHTTPRequestHandler):
            def do_GET(self):
                self.send_response(200)
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(body)

            def log_message(self, *arguments):
                pass

        self.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        self.url = "http://127.0.0.1:%d/items" % self.server.server_port

    def __enter__(self):
        threading.Thread(target=self.server.serve_forever, daemon=True).start()
        return self

    def __exit__(self, *exc):
        self.server.shutdown()
        self.server.server_close()


def test_sql_references():
    assert sources.table_reference("mysql", "", "orders") == "`orders`"
    assert sources.table_reference("pgsql", "sales", "orders") == '"sales"."orders"'
    assert sources.table_reference("sqlserver", "dbo", "orders") == "[dbo].[orders]"
    assert sources.table_reference("oracle_service", "SALES", "ORDERS") == "SALES.ORDERS"
    assert sources.table_reference("snowflake", "ANALYTICS.PUBLIC", "ORDERS") == "ANALYTICS.PUBLIC.ORDERS"
    assert sources.table_reference("databricks", "main.sales", "orders") == "`main`.`sales`.`orders`"
    assert sources.table_query("sqlserver", "[dbo].[orders]", 21) == "SELECT TOP (21) * FROM [dbo].[orders]"
    assert sources.table_query("oracle_sid", "ORDERS", 21) == "SELECT * FROM ORDERS WHERE ROWNUM <= 21"
    assert sources.table_query("snowflake", "S.T", 21) == "SELECT * FROM S.T LIMIT 21"
    assert sources.table_query("mysql", "`t`", None) == "SELECT * FROM `t`"
    for table in ("orders; DROP TABLE x", "or`ders", 'a"b', "a]b", "1orders", ""):
        rejects(sources.table_reference, "mysql", "", table)
    rejects(sources.table_reference, "pgsql", "a.b.c", "orders")
    rejects(sources.table_reference, "redis", "", "orders")


def test_storage_locations():
    assert sources.storage_location(connector("aws_s3", database="landing"), "/2026/a.csv") == ("landing", "2026/a.csv")
    assert sources.storage_location(connector("gcs"), "bucket/x/y.parquet") == ("bucket", "x/y.parquet")
    assert sources.storage_location(connector("sftp", database="/upload"), "in/a.csv") == ("", "/upload/in/a.csv")
    assert sources.storage_location(connector("sftp", database="/upload"), "/data/a.csv") == ("", "/data/a.csv")
    for path in ("../etc/passwd", "a/../../b", "", "a\nb"):
        rejects(sources.storage_location, connector("aws_s3", database="landing"), path)
    rejects(sources.storage_location, connector("aws_s3"), "file.csv")


def test_file_formats():
    sample = sources.sample_file(b"id;name\n1;Ada\n2;Grace\n3;Al", "csv", {"delimiter": ";"}, truncated=True)
    assert sample["columns"] == ["id", "name"] and sample["rows"] == [["1", "Ada"], ["2", "Grace"]], sample
    assert sample["truncated"] is True
    sample = sources.sample_file(b"1,Ada\n2,Grace\n", "csv", {"header": False})
    assert sample["columns"] == ["Column 1", "Column 2"] and len(sample["rows"]) == 2, sample
    sample = sources.sample_file(("\n".join("%d,x" % index for index in range(30))).encode(), "csv", {})
    assert len(sample["rows"]) == 20 and sample["truncated"] is True, sample

    sample = sources.sample_file(b'{"a":1}\n{"a":2,"b":{"c":3}}\n', "jsonl")
    assert sample["columns"] == ["a", "b"] and sample["rows"][1] == ["2", '{"c": 3}'], sample

    payload = json.dumps({"data": {"items": [{"id": 1}, {"id": 2}]}}).encode()
    sample = sources.sample_file(payload, "json", source={"response_path": "data.items"})
    assert sample["kind"] == "table" and sample["total_rows"] == 2, sample
    assert sources.sample_file(payload, "json")["kind"] == "text"
    rejects(sources.sample_file, payload, "json", {}, {"response_path": "data.missing"})
    rejects(sources.sample_file, b"{not json", "json")
    rejects(sources.sample_file, b"[]", "json", {}, {}, True)

    page = (b"<html><head><script>var x = 1;</script></head><body><p>Population by city</p><table>"
            b"<tr><th>City</th><th>Population</th></tr><tr><td>Calgary</td><td>1.3M</td></tr>"
            b"<tr><td>Edmonton</td><td>1.1M</td></tr></table></body></html>")
    sample = sources.sample_file(page, "html")
    assert sample["kind"] == "table" and sample["columns"] == ["City", "Population"], sample
    assert sample["rows"][0] == ["Calgary", "1.3M"], sample
    sample = sources.sample_file(b"<p>Hello <b>there</b></p><script>secret()</script>", "html")
    assert sample["kind"] == "text" and "Hello" in sample["text"] and "secret" not in sample["text"], sample

    sample = sources.sample_file(b"%PDF-1.7\n%binary", "binary", size=12)
    assert sample["kind"] == "text" and "PDF document" in sample["text"], sample

    if importlib.util.find_spec("openpyxl"):
        import openpyxl

        workbook = openpyxl.Workbook()
        workbook.active.title = "First"
        second = workbook.create_sheet("Second")
        second.append(["region", "amount"])
        for index in range(25):
            second.append(["r%d" % index, index])
        stream = io.BytesIO()
        workbook.save(stream)
        sample = sources.sample_file(stream.getvalue(), "xlsx", {"sheet": "Second"})
        assert sample["columns"] == ["region", "amount"] and len(sample["rows"]) == 20, sample
        assert sample["total_rows"] == 25 and sample["truncated"] is True, sample
        rejects(sources.sample_file, stream.getvalue(), "xlsx", {"sheet": "Missing"})
    else:
        print("  skipped Excel checks: openpyxl is not installed")

    if importlib.util.find_spec("pyarrow"):
        import pyarrow
        import pyarrow.parquet

        stream = io.BytesIO()
        pyarrow.parquet.write_table(pyarrow.table({"id": list(range(30)), "name": ["n%d" % i for i in range(30)]}), stream)
        sample = sources.sample_file(stream.getvalue(), "parquet")
        assert sample["columns"] == ["id", "name"] and len(sample["rows"]) == 20, sample
        assert sample["total_rows"] == 30 and "row group" in sample["detail"], sample
    else:
        print("  skipped Parquet checks: pyarrow is not installed")


def test_public_only_guard():
    with JsonServer([{"id": 1}]) as server:
        rejects(sources.http_get, server.url, {}, 1024, True)
        body, truncated, _ = sources.http_get(server.url, {}, 1024, False)
        assert json.loads(body) == [{"id": 1}] and truncated is False
    rejects(sources.http_get, "http://user:secret@example.com/data.json")
    rejects(sources.http_get, "file:///etc/passwd")


def test_preview_sources():
    with tempfile.TemporaryDirectory(prefix="jobseeker-preview-") as repository:
        os.makedirs(os.path.join(repository, "data-assets", "dev", "fixture"))
        with open(os.path.join(repository, "data-assets", "dev", "fixture", "fixture.csv"), "w") as stream:
            stream.write("id,name\n1,Ada\n")
        result = sources.preview(data_asset_from_item(asset_item(), repository))
        assert result["ok"] and result["rows"] == [["1", "Ada"]] and result["size"] == 14, result
        missing = sources.preview(data_asset_from_item(asset_item(relative_path="data-assets/dev/none.csv"), repository))
        assert missing["ok"] is False and "Upload a file" in missing["message"], missing

        with JsonServer({"data": {"items": [{"id": 7}]}}) as server:
            item = asset_item(format="json", source_type="url", source={"url": server.url, "response_path": "data.items"})
            result = sources.preview(data_asset_from_item(item, repository), public_only=False)
            assert result["ok"] and result["rows"] == [["7"]], result
            blocked = sources.preview(data_asset_from_item(item, repository), public_only=True)
            assert blocked["ok"] is False and "private" in blocked["message"], blocked

        table = data_asset_from_item(asset_item(format="table", source_type="database_table", connector_key="db",
                                                source={"table": "orders"}), repository)
        result = sources.preview(table)
        assert result["ok"] is False and "requires a Connection" in result["message"], result
        result = sources.preview(table, connector("mongodb"))
        assert result["ok"] is False and "cannot serve" in result["message"], result


def test_preview_service_contract():
    if not importlib.util.find_spec("fastapi"):
        print("  skipped service checks: fastapi is not installed")
        return
    from fastapi import HTTPException

    from jobseeker import preview_server

    os.environ["JOBSEEKER_DATA_PREVIEW_TOKEN"] = "unit-token"
    request = preview_server.PreviewRequest(asset=asset_item(source_type="database_table", format="table",
                                                             connector_key="db", source={"table": "orders"}))
    for header in ("", "Bearer wrong", "unit-token"):
        try:
            preview_server.preview(request, authorization=header)
            raise AssertionError("an unauthenticated preview must be refused")
        except HTTPException as error:
            assert error.status_code == 401
    result = preview_server.preview(request, authorization="Bearer unit-token")
    assert result["ok"] is False and "was not supplied" in result["message"], result
    request.connector = {"key": "db", "type": "pgsql", "config": {"host": "127.0.0.1", "port": 1}, "secret": {"backend": "local", "values": {}}}
    result = preview_server.preview(request, authorization="Bearer unit-token")
    assert result["ok"] is False and "orders" in result["message"], result


def main():
    for test in (test_sql_references, test_storage_locations, test_file_formats, test_public_only_guard,
                 test_preview_sources, test_preview_service_contract):
        test()
    print("Data Asset preview engine tests passed.")


if __name__ == "__main__":
    main()
