#!/usr/bin/env python3
"""Verify the one-time migration of the retired Insight Studio connection store
(``visualization_connections``) into the unified connector catalog.

A legacy connection + published dataset is seeded directly in the database, the
DbSettings model is exercised (which runs the idempotent migration), and we then
assert the dataset was repointed at a real ``database_settings`` row that Insight
Studio can still use to browse tables. Running it twice must not duplicate rows.
"""
import http.cookiejar
import json
import os
import subprocess
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

BASE_URL = os.environ.get("JOBSEEKER_E2E_URL", "http://localhost").rstrip("/")
ADMIN_EMAIL = os.environ.get("JOBSEEKER_E2E_EMAIL", "admin@example.com")
ADMIN_PASSWORD = os.environ.get("JOBSEEKER_E2E_PASSWORD", "123456")
REPOSITORY_ROOT = Path(__file__).resolve().parents[1]


def sql(statement):
    result = subprocess.run(
        ["docker", "compose", "exec", "-T", "mariadb", "sh", "-c",
         'exec mysql -N -B -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "$0"', statement],
        cwd=REPOSITORY_ROOT, capture_output=True, text=True,
    )
    if result.returncode != 0:
        raise AssertionError("SQL failed: %s\n%s" % (statement, result.stderr))
    return result.stdout.strip()


class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def csrf(self):
        for cookie in self.cookies:
            if cookie.name == "csrf_cookie_name":
                return cookie.value
        raise AssertionError("no CSRF cookie")

    def get(self, path):
        with self.opener.open(urllib.request.Request(BASE_URL + path), timeout=60) as response:
            return response.status, response.read().decode("utf-8", "replace")

    def post(self, path, fields):
        fields = dict(fields, csrf_test_name=self.csrf())
        data = urllib.parse.urlencode(fields).encode("utf-8")
        try:
            with self.opener.open(urllib.request.Request(BASE_URL + path, data=data, method="POST"), timeout=60) as response:
                return response.status, response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as error:  # noqa: F821 - urllib.error imported lazily below
            return error.code, error.read().decode("utf-8", "replace")


import urllib.error  # noqa: E402


def main():
    run_id = uuid.uuid4().hex[:8]
    name = "e2e-migrate-%s" % run_id
    browser = Browser()
    browser.get("/")
    browser.post("/loginMe", {"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD})

    # Make sure the Insight Studio schema exists before seeding into it.
    browser.get("/Visualization/dataSources")

    created_connection_id = None
    try:
        sql(
            "INSERT INTO visualization_connections "
            "(name, driver, host, port, database_name, username, password_encrypted, ssl_mode, is_active, owner_id, owner, created_at, updated_at) "
            "VALUES ('%s', 'mysql', 'mariadb', 3306, 'jobseeker', 'mysql', '', 'preferred', 1, 0, 'e2e', NOW(), NOW())" % name
        )
        created_connection_id = int(sql("SELECT id FROM visualization_connections WHERE name = '%s'" % name))
        # A real, non-empty encrypted secret is required; reuse the built-in connector's.
        sql(
            "UPDATE visualization_connections SET password_encrypted = "
            "(SELECT secret_encrypted FROM database_settings WHERE connector_key = 'jobseeker-mariadb' LIMIT 1) "
            "WHERE id = %d" % created_connection_id
        )
        sql(
            "INSERT INTO visualization_datasets "
            "(connection_id, name, dataset_key, description, table_schema, table_name, dimensions_json, measures_json, time_column, environment_column, is_active, owner_id, owner, created_at, updated_at) "
            "VALUES (%d, '%s ds', 'source_%s', '', 'jobseeker', 'environment', '[]', '[]', '', '', 1, 0, 'e2e', NOW(), NOW())"
            % (created_connection_id, name, uuid.uuid4().hex[:16])
        )

        # Undo any marker so this run performs a fresh migration.
        sql("UPDATE visualization_connections SET migrated_connector_id = NULL WHERE id = %d" % created_connection_id)
        sql("UPDATE visualization_datasets SET connection_id = %d, connector_migrated = 0 WHERE connection_id != %d AND name = '%s ds'"
            % (created_connection_id, created_connection_id, name))

        before = int(sql("SELECT COUNT(*) FROM database_settings"))
        # Loading the DbSettings model runs the idempotent migration.
        browser.get("/dbSettings?environment=ALL")

        mapped = sql("SELECT migrated_connector_id FROM visualization_connections WHERE id = %d" % created_connection_id)
        assert mapped and mapped != "NULL", "migration did not record a mapping"
        new_connector_id = int(mapped)
        assert new_connector_id != created_connection_id

        row = sql("SELECT db_type, secret_backend, environment, job_name FROM database_settings WHERE id = %d" % new_connector_id)
        assert row.split("\t") == ["mysql", "local", "ALL", "*"], row

        dataset_conn = int(sql("SELECT connection_id FROM visualization_datasets WHERE name = '%s ds'" % name))
        assert dataset_conn == new_connector_id, "dataset was not repointed (got %d)" % dataset_conn

        after = int(sql("SELECT COUNT(*) FROM database_settings"))
        assert after == before + 1, "expected exactly one migrated connector (%d -> %d)" % (before, after)

        # Idempotency: a second pass creates nothing new and keeps the dataset stable.
        browser.get("/dbSettings?environment=ALL")
        assert int(sql("SELECT COUNT(*) FROM database_settings")) == after, "second migration pass duplicated a connector"
        assert int(sql("SELECT connection_id FROM visualization_datasets WHERE name = '%s ds'" % name)) == new_connector_id

        # The migrated connector is a usable Insight Studio data source.
        status, body = browser.get("/Visualization/connectionTables/%d" % new_connector_id)
        payload = json.loads(body)
        assert payload.get("status") is True and any(
            r["table_name"] == "environment" for r in payload.get("data", [])
        ), body

        print("Insight Studio connection migration test passed.")
    finally:
        sql("DELETE FROM visualization_datasets WHERE name = '%s ds'" % name)
        if created_connection_id is not None:
            mapped = sql("SELECT migrated_connector_id FROM visualization_connections WHERE id = %d" % created_connection_id)
            if mapped and mapped not in ("NULL", ""):
                sql("DELETE FROM database_settings WHERE id = %d" % int(mapped))
            sql("DELETE FROM visualization_connections WHERE id = %d" % created_connection_id)


if __name__ == "__main__":
    main()
