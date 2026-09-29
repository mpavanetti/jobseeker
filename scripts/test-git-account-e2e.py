#!/usr/bin/env python3
"""Exercise repository-scoped personal Git accounts against the live stack."""

from __future__ import annotations

import base64
import hashlib
import hmac
import importlib.util
import json
import os
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
os.environ.setdefault("JOBSEEKER_E2E_URL", "http://127.0.0.1:8088")
_spec = importlib.util.spec_from_file_location("sample_matrix", ROOT / "scripts" / "test-job-samples-e2e.py")
matrix = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(matrix)

HOST = "git-account-e2e-%s.invalid" % uuid.uuid4().hex[:8]
passed = 0


def check(name: str, condition: bool, detail: str = "") -> None:
    global passed
    if not condition:
        print("FAIL: " + name + (("\n  " + detail) if detail else ""))
        raise SystemExit(1)
    passed += 1
    print("ok - " + name)


def compose(*arguments: str) -> subprocess.CompletedProcess:
    return subprocess.run(("docker", "compose", *arguments), cwd=ROOT, capture_output=True, text=True)


def base64url(value: bytes) -> str:
    return base64.urlsafe_b64encode(value).decode().rstrip("=")


def workspace_session(user_id: int, path: str) -> str:
    """A session as JobSeeker issues it: scoped to one repository (host and path)."""
    worker = compose("exec", "-T", "php", "printenv", "JOBSEEKER_CONNECTOR_API_TOKEN").stdout.strip()
    if len(worker) < 24:
        check("the platform has a private Git session signing key", False)
    repository = path.lower().strip("/")
    repository = repository[:-4] if repository.endswith(".git") else repository
    claims = {"uid": user_id, "job": "git-account-e2e", "host": HOST, "path": repository}
    payload = base64url(json.dumps(claims, separators=(",", ":")).encode())
    key = hmac.new(worker.encode(), b"git-credential-session", hashlib.sha256).digest()
    signature = base64url(hmac.new(key, payload.encode(), hashlib.sha256).digest())
    return "jsgit1.%s.%s" % (payload, signature)


def credential(user_id: int, path: str, protocol: str = "https", session: str = "") -> tuple[int, str]:
    session = session or workspace_session(user_id, path)
    request = urllib.request.Request(
        matrix.BASE_URL + "/git-credential",
        data=urllib.parse.urlencode({"host": HOST, "path": path, "protocol": protocol}).encode(),
        headers={"Authorization": "Bearer " + session},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()


def main() -> None:
    browser = matrix.Browser()
    browser.request("/")
    _, page = browser.request("/loginMe", method="POST", csrf=True,
                              fields={"email": matrix.ADMIN_EMAIL, "password": matrix.ADMIN_PASSWORD})
    check("admin login", "logout" in page.lower())

    ids: list[str] = []
    try:
        for label, scope, username, secret in (
            ("Broad", "organization", "broad-user", "broad-token-e2e"),
            ("Specific", "organization/repository", "specific-user", "specific-token-e2e"),
        ):
            browser.request("/gitAccountSave", method="POST", csrf=True, fields={
                "provider": "generic", "label": label, "host": HOST, "path_prefix": scope,
                "auth_type": "token", "username": username, "secret": secret, "known_hosts": "",
            })

        query = ("SELECT CONCAT(id, ':', secret_encrypted) FROM user_git_accounts "
                 "WHERE host='%s' ORDER BY id" % HOST)
        rows = compose("exec", "-T", "mariadb", "mariadb", "-umysql", "-pmysql", "-D", "jobseeker",
                       "-N", "-e", query).stdout.strip().splitlines()
        ids = [row.split(":", 1)[0] for row in rows if ":" in row]
        check("two scoped accounts were saved", len(ids) == 2, "rows=%r" % rows)
        check("account tokens are encrypted at rest",
              all("broad-token-e2e" not in row and "specific-token-e2e" not in row for row in rows))

        user_query = "SELECT userId FROM tbl_users WHERE email='%s' AND isDeleted=0 LIMIT 1" % matrix.ADMIN_EMAIL
        user_id_text = compose("exec", "-T", "mariadb", "mariadb", "-umysql", "-pmysql", "-D", "jobseeker",
                               "-N", "-e", user_query).stdout.strip()
        user_id = int(user_id_text)
        check("the platform has a private Git session signing key", bool(workspace_session(user_id, "organization/repository")))

        status, output = credential(user_id, "organization/repository.git")
        check("the most specific repository account wins",
              status == 200 and "username=specific-user" in output and "password=specific-token-e2e" in output,
              output)
        status, output = credential(user_id, "organization/other.git")
        check("the owner-scoped account is the fallback",
              status == 200 and "username=broad-user" in output and "password=broad-token-e2e" in output,
              output)
        status, _ = credential(user_id, "another/repository.git")
        check("an unrelated repository receives no account", status == 404)
        status, _ = credential(user_id, "organization/repository.git", "ssh")
        check("HTTP tokens are not returned to SSH", status == 404)
        status, _ = credential(user_id, "organization/other.git", session=workspace_session(user_id, "organization/repository"))
        check("a workspace session cannot fetch another repository's account", status == 403)
    finally:
        if not ids:
            query = "SELECT id FROM user_git_accounts WHERE host='%s'" % HOST
            ids = compose("exec", "-T", "mariadb", "mariadb", "-umysql", "-pmysql", "-D", "jobseeker",
                          "-N", "-e", query).stdout.strip().splitlines()
        for account_id in ids:
            browser.request("/gitAccountDelete", method="POST", csrf=True, fields={"id": account_id})

    remaining = compose("exec", "-T", "mariadb", "mariadb", "-umysql", "-pmysql", "-D", "jobseeker", "-N", "-e",
                        "SELECT COUNT(*) FROM user_git_accounts WHERE host='%s'" % HOST).stdout.strip()
    check("temporary accounts were removed", remaining == "0", remaining)
    print("\nGit account e2e: %d checks passed." % passed)


if __name__ == "__main__":
    sys.exit(main())
