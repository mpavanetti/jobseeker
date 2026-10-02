# Workspace gateway certificate

When this folder holds `tls.crt` and `tls.key`, the workspace gateway serves
HTTPS. Browsers run VS Code's notebooks and webviews only on HTTPS or
`localhost`, so a gateway reached by name, such as `http://minipc.local:3001`,
needs one.

`scripts/workspace-gateway-certificate.sh` creates them, with a local
certificate authority (`ca.crt`) that each browser machine trusts once. See
"HTTPS" in `doc/jobseeker/Architecture/workspace-runtimes.md`.

Nothing here is committed.
