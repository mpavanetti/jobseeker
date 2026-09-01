#!/usr/bin/env python3
"""End-to-end check that saving an email template preserves the HTML exactly.

Regression guard for the email editor: a stored template that is a complete
<html> document with inline styles, a <style> block, an HTML comment and Jenkins
Email-ext tokens must round-trip through create and update without being
rewritten, re-encoded or truncated.
"""
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

    def csrf_token(self):
        for cookie in self.cookies:
            if cookie.name == "csrf_cookie_name":
                return cookie.value
        raise AssertionError("CSRF cookie is missing")

    def request(self, path, method="GET", fields=None, expected=(200,)):
        values = dict(fields or {})
        if fields is not None:
            values["csrf_test_name"] = self.csrf_token()
        data = urllib.parse.urlencode(values).encode("utf-8") if fields is not None else None
        request = urllib.request.Request(BASE_URL + path, data=data, method=method)
        try:
            with self.opener.open(request, timeout=20) as response:
                status, body = response.status, response.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as error:
            status, body = error.code, error.read().decode("utf-8", "replace")
        if status not in expected:
            raise AssertionError("%s %s returned %s: %s" % (method, path, status, body[:500]))
        return status, body


def build_template_html(marker):
    return "\n".join([
        "<!DOCTYPE html>",
        "<html>",
        "<head>",
        '<meta charset="utf-8">',
        "<style>",
        "  .badge { color: #b91c1c; font-weight: 700; }",
        "  a:hover { text-decoration: underline; }",
        "</style>",
        "</head>",
        '<body style="margin:0; padding:0; background:#f3f4f6; color:#17202a; font-family:Arial, Helvetica, sans-serif;">',
        "  <!-- %s -->" % marker,
        '  <table role="presentation" width="100%" style="border-collapse:collapse;">',
        "    <tr>",
        '      <td style="padding:16px 24px; background:#047857; color:#ffffff;">',
        '        Build <span class="badge">${BUILD_STATUS}</span> - ${PROJECT_NAME} #${BUILD_NUMBER}',
        "      </td>",
        "    </tr>",
        "    <tr>",
        '      <td style="padding:16px 24px;">',
        '        Environment: ${ENV,var="ENVIRONMENT"}<br>',
        '        Dataset: ${PROPFILE,file="jobseeker-email-metrics.properties",property="dataset"}<br>',
        '        <a href="${BUILD_URL}console" style="color:#047857;">Open console output</a>',
        "      </td>",
        "    </tr>",
        "  </table>",
        "</body>",
        "</html>",
    ])


def main():
    run_id = uuid.uuid4().hex[:10]
    name = "e2e-email-%s" % run_id
    template_html = build_template_html("preview marker %s" % run_id)
    browser = Browser()
    created_id = None

    browser.request("/")
    _, login_body = browser.request(
        "/loginMe", method="POST",
        fields={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD},
    )
    if "logout" not in login_body.lower():
        raise AssertionError("Administrator login failed")

    smtp_provider = ""
    _, smtp_body = browser.request("/EmailSettings/fetchSMTP")
    try:
        smtp_rows = json.loads(smtp_body)
        if smtp_rows:
            smtp_provider = smtp_rows[0].get("name", "")
    except json.JSONDecodeError:
        pass
    if smtp_provider == "":
        smtp_provider = "default"

    base_fields = {
        "name": name,
        "smtp": smtp_provider,
        "to": "ops@example.com",
        "from": "jenkins@example.com",
        "cc": "",
        "subject": "Build ${BUILD_STATUS}: ${PROJECT_NAME}",
        "msg": template_html,
        "description": "Disposable email template round-trip fixture " + run_id,
        "enabled": "1",
    }

    try:
        browser.request("/EmailSettings/InsertDbSettings", method="POST", fields=base_fields, expected=(200, 302))

        _, listing = browser.request("/EmailSettings/fetchAll/all")
        rows = json.loads(listing).get("data", [])
        mine = [row for row in rows if row.get("name") == name]
        assert len(mine) == 1, "Expected exactly one created template, found %d" % len(mine)
        created_id = int(mine[0]["id"])

        assert mine[0]["msg"] == template_html, (
            "Stored email HTML differs from what was submitted.\n--- sent ---\n%s\n--- stored ---\n%s"
            % (template_html, mine[0]["msg"])
        )

        # Update: change only the subject, resend the same body, confirm it is untouched.
        update_fields = dict(base_fields)
        update_fields["id"] = str(created_id)
        update_fields["subject"] = "Updated - Build ${BUILD_STATUS}"
        browser.request("/EmailSettings/UpdateDbSettings", method="POST", fields=update_fields, expected=(200, 302))

        _, fetched = browser.request("/EmailSettings/fetch/%d" % created_id)
        row = json.loads(fetched)["data"][0]
        assert row["msg"] == template_html, (
            "Email HTML was rewritten on update.\n--- expected ---\n%s\n--- stored ---\n%s"
            % (template_html, row["msg"])
        )
        assert row["subject"] == "Updated - Build ${BUILD_STATUS}", row["subject"]
        # The preview must still be able to substitute the untouched tokens.
        assert "${BUILD_STATUS}" not in row["preview_msg"], "Preview did not resolve Jenkins tokens"

        print("Email template round-trip E2E test passed.")
    finally:
        if created_id is not None:
            browser.request(
                "/EmailSettings/deleteSetting", method="POST",
                fields={"userId": str(created_id)}, expected=(200,),
            )


if __name__ == "__main__":
    main()
