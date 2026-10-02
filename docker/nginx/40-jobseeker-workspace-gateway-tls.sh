#!/bin/sh
# How the workspace gateway listens (nginx/workspace-gateway.conf): HTTPS when
# nginx/workspace-gateway-tls holds tls.crt and tls.key (see
# scripts/workspace-gateway-certificate.sh), otherwise plain HTTP.
set -eu

tls=/etc/nginx/workspace-gateway-tls
target=/etc/nginx/conf.d/workspace-gateway-listen.inc

if [ -r "$tls/tls.crt" ] && [ -r "$tls/tls.key" ]; then
  cat > "$target" <<'CONF'
listen 0.0.0.0:8081 ssl;
ssl_certificate /etc/nginx/workspace-gateway-tls/tls.crt;
ssl_certificate_key /etc/nginx/workspace-gateway-tls/tls.key;
ssl_protocols TLSv1.2 TLSv1.3;
ssl_session_cache shared:jobseeker_ide:1m;
# An http:// link to this port moves to https:// on the same host and port.
error_page 497 =307 https://$http_host$request_uri;
CONF
  echo "$0: the workspace gateway serves HTTPS with $tls/tls.crt"
else
  if [ -e "$tls/tls.crt" ] || [ -e "$tls/tls.key" ]; then
    echo "$0: $tls/tls.crt or tls.key is not readable by $(id -un); the workspace gateway serves HTTP" >&2
  fi
  echo 'listen 0.0.0.0:8081;' > "$target"
fi
