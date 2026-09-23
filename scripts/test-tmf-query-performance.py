#!/usr/bin/env python3
"""Query-plan guards for the TMF landing page.

/tmf builds four filter dropdowns, and each one is a SELECT DISTINCT over the
whole tmf table. The environment predicate is UPPER(TRIM(environment)), which is
not sargable, so no index can narrow those scans - the only lever left is to
make sure each one is answered from a covering index instead of reading every
row, and that none of them falls back to a full table scan into a temporary
table.

On a 250k-row table that distinction was worth 1.36s -> 0.27s of query time, and
1025ms -> 290ms on the page. This test asserts the plan rather than the clock,
because a wall-clock budget on a shared CI box is noise. A generous ceiling is
checked afterwards as a second signal.

Run against the live compose stack:  python3 scripts/test-tmf-query-performance.py
"""

from __future__ import annotations

import os
import subprocess
import sys

DB_SERVICE = os.environ.get("JOBSEEKER_DB_SERVICE", "mariadb")
DB_USER = os.environ.get("JOBSEEKER_DB_USER", "mysql")
DB_PASSWORD = os.environ.get("JOBSEEKER_DB_PASSWORD", "mysql")
DB_NAME = os.environ.get("JOBSEEKER_DB_NAME", "jobseeker")

# The minimum table size at which a bad plan is actually measurable. Below this
# the optimiser may legitimately prefer a scan, so the plan assertions are
# skipped rather than reported as failures.
MIN_ROWS_FOR_PLAN_CHECKS = 5000

# Wall-clock ceiling for all four dropdown queries combined, in seconds. Set far
# above the measured 0.27s so only a real regression trips it.
TIME_BUDGET_SECONDS = 2.0

INTERNAL = '__jobseeker_'
ENV_FILTER = 'UPPER(TRIM(environment))="DEV"'
NOT_INTERNAL = f'(tmf.job_name IS NULL OR LEFT(tmf.job_name,12) <> "{INTERNAL}")'

QUERIES = {
    "listStatus": f'SELECT DISTINCT status FROM tmf WHERE {NOT_INTERNAL} AND {ENV_FILTER} AND status IS NOT NULL',
    "listJobName": f'SELECT DISTINCT job_name FROM tmf WHERE {NOT_INTERNAL} AND {ENV_FILTER}',
    "listDimension": f'SELECT DISTINCT dimension FROM tmf WHERE {NOT_INTERNAL} AND {ENV_FILTER}',
    "listReprocess": f'SELECT DISTINCT job_name,reprocess FROM tmf WHERE reprocess=1 AND {NOT_INTERNAL} AND {ENV_FILTER}',
}

# Every covering index the page depends on, as Tmf_model::ensureResultIndexes()
# declares it.
REQUIRED_INDEXES = {
    "tmf_filter_status": ["status", "environment", "job_name"],
    "tmf_filter_job": ["job_name", "environment"],
    "tmf_filter_dimension": ["dimension", "environment", "job_name"],
    "tmf_filter_reprocess": ["reprocess", "job_name", "environment"],
}

failures: list[str] = []
checks = 0


def check(label: str, condition: bool, detail: str = "") -> None:
    global checks
    checks += 1
    if not condition:
        failures.append(f"{label}{(' -> ' + detail) if detail else ''}")


def run_sql(sql: str) -> str:
    environment = os.environ.copy()
    environment["MYSQL_PWD"] = DB_PASSWORD
    command = [
        "docker", "compose", "exec", "-T", "-e", "MYSQL_PWD",
        DB_SERVICE, "mariadb", "-N", "-B", "-u", DB_USER, DB_NAME,
    ]
    result = subprocess.run(command, input=sql, text=True, capture_output=True,
                            env=environment, check=False)
    if result.returncode:
        raise RuntimeError((result.stderr or result.stdout).strip() or "MariaDB command failed")
    return result.stdout.strip()


def main() -> int:
    try:
        row_count = int(run_sql("SELECT COUNT(*) FROM tmf;") or 0)
    except Exception as error:  # noqa: BLE001 - the stack simply may not be up
        print(f"SKIP: the compose stack is not reachable ({error})")
        return 0

    print(f"tmf rows: {row_count}")

    # --- 1. The covering indexes must exist, with the right columns in order --
    index_rows = run_sql(
        'SELECT INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS '
        f'WHERE TABLE_SCHEMA="{DB_NAME}" AND TABLE_NAME="tmf" ORDER BY INDEX_NAME, SEQ_IN_INDEX;'
    )
    present: dict[str, list[str]] = {}
    for line in index_rows.splitlines():
        parts = line.split("\t")
        if len(parts) == 3:
            present.setdefault(parts[0], []).append(parts[2])

    for name, columns in REQUIRED_INDEXES.items():
        check(f"index {name} exists", name in present, f"found {sorted(present)}")
        if name in present:
            check(f"index {name} has the expected column order",
                  present[name] == columns, f"{present[name]} != {columns}")

    # --- 2. No dropdown query may scan the table or build a temporary table ---
    if row_count < MIN_ROWS_FOR_PLAN_CHECKS:
        print(f"note: fewer than {MIN_ROWS_FOR_PLAN_CHECKS} rows, skipping plan assertions")
    else:
        for label, sql in QUERIES.items():
            plan = run_sql(f"EXPLAIN {sql};")
            # EXPLAIN columns: id, select_type, table, type, possible_keys, key, ...
            fields = plan.split("\t")
            access_type = fields[3] if len(fields) > 3 else "?"
            key_used = fields[5] if len(fields) > 5 else "NULL"
            extra = fields[-1] if fields else ""
            print(f"  {label:<14} type={access_type:<6} key={key_used:<22} extra={extra}")

            check(f"{label} must not do a full table scan",
                  access_type != "ALL", f"type={access_type}")
            check(f"{label} must use an index", key_used not in ("NULL", ""), f"key={key_used}")
            check(f"{label} must not build a temporary table",
                  "Using temporary" not in extra, extra)

    # --- 3. A generous wall-clock ceiling as a second signal ------------------
    import time
    started = time.perf_counter()
    for sql in QUERIES.values():
        run_sql(f"{sql};")
    elapsed = time.perf_counter() - started
    # Each run_sql pays a docker-exec round trip, so only flag a large overrun.
    print(f"four dropdown queries (including docker exec overhead): {elapsed:.2f}s")
    if row_count >= MIN_ROWS_FOR_PLAN_CHECKS:
        check("the four dropdown queries stay within budget",
              elapsed < TIME_BUDGET_SECONDS + 2.0,
              f"{elapsed:.2f}s")

    if failures:
        print("\nFAILURES:")
        for failure in failures:
            print(f"  - {failure}")
        return 1

    print(f"\nTMF query-plan performance tests passed ({checks} assertions).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
