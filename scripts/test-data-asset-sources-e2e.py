#!/usr/bin/env python3
"""End-to-end preview checks for connected and public Data Asset sources."""

import html
import http.cookiejar
import json
import os
import re
import urllib.error
import urllib.parse
import urllib.request
import uuid


BASE_URL = os.environ.get("JOBSEEKER_E2E_URL", "http://localhost").rstrip("/")
ADMIN_EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
ADMIN_PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")


class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def csrf(self):
        for cookie in self.cookies:
            if cookie.name == "csrf_cookie_name":
                return cookie.value
        raise AssertionError("CSRF cookie is missing")

    def request(self, path, method="GET", fields=None, csrf=False, expected=(200,)):
        data = None
        if fields is not None:
            values = list(fields.items())
            if csrf:
                values.append(("csrf_test_name", self.csrf()))
            data = urllib.parse.urlencode(values).encode("utf-8")
        request = urllib.request.Request(BASE_URL + path, data=data, method=method)
        try:
            with self.opener.open(request, timeout=60) as response:
                status, body = response.status, response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as error:
            status, body = error.code, error.read().decode("utf-8", "replace")
        if status not in expected:
            raise AssertionError("%s %s -> %s: %s" % (method, path, status, body[:600]))
        return status, body


def assets_from_page(page):
    match = re.search(r'<script id="dataAssetsPayload" type="application/json">(.*?)</script>', page, re.S)
    if not match:
        raise AssertionError("Data Assets payload is missing")
    return json.loads(html.unescape(match.group(1)))


def create_asset(browser, fields):
    _, page = browser.request("/data-assets/save?environment=DEV", method="POST", fields=fields, csrf=True)
    asset = next((item for item in assets_from_page(page) if item["key"] == fields["asset_key"]), None)
    if not asset:
        message = re.search(r'<div class="alert alert-danger[^>]*>.*?<i[^>]*></i>\s*(.*?)</div>', page, re.S)
        raise AssertionError("asset was not created: %s" % (html.unescape(re.sub('<[^>]+>', '', message.group(1))).strip() if message else page[:500]))
    return asset


def base_fields(key, name, source_type, format_name, file_name):
    return {
        "asset_id": "0",
        "name": name,
        "asset_key": key,
        "direction": "input",
        "environment": "DEV",
        "job_name": "*",
        "source_type": source_type,
        "format": format_name,
        "file_name": file_name,
        "delimiter": ",",
        "encoding": "UTF-8",
        "has_header": "1",
        "sheet": "",
        "description": "Disposable connected-source E2E fixture.",
        "is_required": "1",
        "is_active": "1",
    }


def main():
    suffix = uuid.uuid4().hex[:8]
    table_key = "e2e-mariadb-table-" + suffix
    web_key = "e2e-public-json-" + suffix
    sheet_key = "e2e-public-sheet-" + suffix
    browser = Browser()
    created = []

    browser.request("/")
    _, login = browser.request("/loginMe", method="POST", fields={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD}, csrf=True)
    assert "logout" in login.lower(), "admin login failed"

    try:
        fields = base_fields(table_key, "MariaDB environment table", "database_table", "table", "environment.csv")
        fields.update({"connector_key": "jobseeker-mariadb", "table_schema": "", "table_name": "environment"})
        table_asset = create_asset(browser, fields)
        created.append(int(table_asset["id"]))
        assert table_asset["source_type"] == "database_table"
        assert table_asset["connector_key"] == "jobseeker-mariadb"
        assert table_asset["source"] == {"table": "environment", "schema": ""}

        _, body = browser.request("/data-assets/preview/%d?environment=DEV" % table_asset["id"])
        preview = json.loads(body)
        assert preview["ok"] is True and preview["kind"] == "table", preview
        assert "Environment" in preview["columns"] and preview["rows"], preview

        fields = base_fields(web_key, "Public JSON users", "url", "json", "users.json")
        fields.update({
            "connector_key": "",
            "source_url": "https://jsonplaceholder.typicode.com/users",
            "response_path": "",
        })
        web_asset = create_asset(browser, fields)
        created.append(int(web_asset["id"]))
        _, body = browser.request("/data-assets/preview/%d?environment=DEV" % web_asset["id"])
        preview = json.loads(body)
        assert preview["ok"] is True and preview["kind"] == "table", preview
        assert "email" in preview["columns"] and len(preview["rows"]) >= 1, preview

        fields = base_fields(sheet_key, "Public Google Sheet", "google_sheet", "csv", "class-data.csv")
        fields.update({
            "connector_key": "",
            "spreadsheet_id": "1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms",
            "sheet_range": "Class Data!A1:C4",
            "google_public_url": "",
        })
        sheet_asset = create_asset(browser, fields)
        created.append(int(sheet_asset["id"]))
        _, body = browser.request("/data-assets/preview/%d?environment=DEV" % sheet_asset["id"])
        preview = json.loads(body)
        assert preview["ok"] is True and preview["kind"] == "table", preview
        assert preview["columns"][:3] == ["Student Name", "Gender", "Class Level"], preview
        assert preview["rows"][0][0] == "Alexandra", preview

        _, body = browser.request("/data-assets/catalog?environment=DEV")
        catalog = {item["key"]: item for item in json.loads(body)["assets"]}
        assert catalog[table_key]["source_type"] == "database_table"
        assert catalog[table_key]["connector_key"] == "jobseeker-mariadb"
        assert "secret" not in json.dumps(catalog[table_key]).lower(), catalog[table_key]
        assert catalog[web_key]["source"]["url"].startswith("https://")
        assert catalog[sheet_key]["source_type"] == "google_sheet"

        print("Connected and public Data Asset source E2E test passed.")
    finally:
        for asset_id in created:
            browser.request(
                "/data-assets/delete?environment=DEV", method="POST",
                fields={"asset_id": str(asset_id), "delete_file": "0"}, csrf=True, expected=(200,),
            )


if __name__ == "__main__":
    main()
