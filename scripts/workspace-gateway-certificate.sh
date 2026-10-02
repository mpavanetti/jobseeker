#!/bin/sh
# Makes the certificate the workspace gateway serves HTTPS with
# (doc/jobseeker/Architecture/workspace-runtimes.md, "HTTPS").
#
#   sh scripts/workspace-gateway-certificate.sh [NAME|IP ...]
#
# Without arguments it covers this machine: <hostname>.local, <hostname>,
# localhost, 127.0.0.1 and the first LAN address. Files land in
# nginx/workspace-gateway-tls/:
#
#   ca.crt   the local certificate authority each browser machine trusts once
#   ca.key   its key; it only signs certificates for the names above
#   tls.crt  the gateway's certificate, renewed by running this again
#   tls.key  its key, readable by nginx
#
# The authority is limited to those names with X.509 name constraints, so even
# its key cannot make a certificate browsers accept for any other site. Adding
# a name later needs a new authority: remove ca.* and trust the new ca.crt.
set -eu

cd "$(dirname "$0")/.."
dir=nginx/workspace-gateway-tls
mkdir -p "$dir"

if [ "$#" -eq 0 ]; then
  host="$(hostname -s 2>/dev/null || hostname)"
  set -- "$host.local" "$host" localhost 127.0.0.1
  lan="$(hostname -I 2>/dev/null | awk '{print $1}')"
  [ -z "$lan" ] || set -- "$@" "$lan"
fi

san=
constraints=
for name in "$@"; do
  if printf '%s' "$name" | grep -Eq '^[0-9]{1,3}(\.[0-9]{1,3}){3}$'; then
    san="$san,IP:$name"
    constraints="$constraints,permitted;IP:$name/255.255.255.255"
  elif printf '%s' "$name" | grep -Eq '^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$'; then
    san="$san,DNS:$name"
    constraints="$constraints,permitted;DNS:$name"
  else
    echo "Not a host name or IPv4 address: $name" >&2
    exit 2
  fi
done
san="${san#,}"
constraints="${constraints#,}"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

if [ ! -f "$dir/ca.key" ] || [ ! -f "$dir/ca.crt" ]; then
  # A config of its own, so the system's default CA extensions stay out.
  cat > "$work/ca.cnf" <<EOF
[req]
prompt = no
distinguished_name = dn
x509_extensions = ca
[dn]
CN = JobSeeker workspace gateway CA ($1)
[ca]
basicConstraints = critical,CA:TRUE,pathlen:0
keyUsage = critical,keyCertSign,cRLSign
subjectKeyIdentifier = hash
nameConstraints = critical,$constraints
EOF
  (umask 077 && openssl req -x509 -config "$work/ca.cnf" -newkey rsa:3072 -sha256 -nodes -days 3650 \
    -keyout "$dir/ca.key" -out "$dir/ca.crt" 2>/dev/null)
  echo "Created the certificate authority $dir/ca.crt for: $*"
  echo "Trust it once on every machine whose browser opens the editors (see below)."
else
  echo "Reusing the certificate authority $dir/ca.crt."
fi

cat > "$work/tls.ext" <<EOF
basicConstraints=critical,CA:FALSE
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid
subjectAltName=$san
EOF
openssl req -newkey rsa:2048 -sha256 -nodes -keyout "$work/tls.key" -out "$work/tls.csr" -subj "/CN=$1" 2>/dev/null
# 397 days: the longest browsers and macOS accept for a server certificate.
openssl x509 -req -sha256 -days 397 -in "$work/tls.csr" -CA "$dir/ca.crt" -CAkey "$dir/ca.key" \
  -set_serial "0x$(openssl rand -hex 16)" -extfile "$work/tls.ext" -out "$work/tls.crt" 2>/dev/null
if ! openssl verify -CAfile "$dir/ca.crt" "$work/tls.crt" >/dev/null; then
  echo "The new certificate does not verify against $dir/ca.crt; it covers names the authority does not. Remove $dir/ca.* and run this again." >&2
  exit 1
fi
mv "$work/tls.crt" "$dir/tls.crt"
mv "$work/tls.key" "$dir/tls.key"
# nginx runs as its own user, so its key is readable; the authority's is not.
chmod 0644 "$dir/tls.crt" "$dir/tls.key" "$dir/ca.crt"
chmod 0600 "$dir/ca.key"

echo "Created $dir/tls.crt for: $*"
cat <<EOF

Next:
  1. Set JOBSEEKER_WORKSPACE_GATEWAY_TLS=true in .env, then:
       docker compose up -d --build nginx php
  2. Trust $dir/ca.crt on each machine that opens editors, then restart the browser:
       macOS:    sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ca.crt
                 (or open it in Keychain Access and set "When using this certificate" to "Always Trust")
       Windows:  certutil -addstore -f Root ca.crt
       Linux:    copy it to /usr/local/share/ca-certificates/jobseeker-gateway.crt; sudo update-ca-certificates
                 (Chrome on Linux: Settings > Privacy and security > Security > Manage certificates > Authorities)
       Firefox:  Settings > Privacy & Security > Certificates > View Certificates > Authorities > Import
  3. Editors now open on https://$1:${JOBSEEKER_WORKSPACE_GATEWAY_PORT:-3001}/ide/...
EOF
