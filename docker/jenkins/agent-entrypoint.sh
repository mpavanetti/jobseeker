#!/bin/sh
set -eu

JENKINS_URL="${JENKINS_URL:-http://jenkins:8080/}"
JENKINS_URL="${JENKINS_URL%/}/"
JENKINS_USER="${JENKINS_USER:-jobseeker}"
JENKINS_TOKEN="${JENKINS_TOKEN:-jobseeker}"
JENKINS_AGENT_WORKDIR="${JENKINS_AGENT_WORKDIR:-/home/jenkins/agent}"
JENKINS_AGENT_LABELS="${JENKINS_AGENT_LABELS:-jobseeker}"
JENKINS_AGENT_DESCRIPTION="${JENKINS_AGENT_DESCRIPTION:-JobSeeker environment worker}"
JENKINS_AGENT_MODE="${JENKINS_AGENT_MODE:-EXCLUSIVE}"
JENKINS_AGENT_WEB_SOCKET="${JENKINS_AGENT_WEB_SOCKET:-true}"
JENKINS_AGENT_TUNNEL="${JENKINS_AGENT_TUNNEL:-jenkins:50000}"
JENKINS_AGENT_EXECUTORS="${JENKINS_AGENT_EXECUTORS:-1}"

case "$JENKINS_AGENT_EXECUTORS" in
  ''|*[!0-9]*) JENKINS_AGENT_EXECUTORS=1 ;;
esac

if [ -z "${JENKINS_AGENT_NAME:-}" ]; then
  suffix="$(hostname | tr -cd 'A-Za-z0-9-' | cut -c1-12)"
  JENKINS_AGENT_NAME="${JENKINS_AGENT_NAME_PREFIX:-jobseeker-agent}-${suffix:-1}"
fi

xml_escape() {
  printf '%s' "$1" | sed -e 's/&/\&amp;/g' -e 's/</\&lt;/g' -e 's/>/\&gt;/g' -e 's/"/\&quot;/g' -e "s/'/\&apos;/g"
}

url_encode_agent_name() {
  JENKINS_AGENT_NAME="$JENKINS_AGENT_NAME" python3 - <<'PY'
import os
import urllib.parse
print(urllib.parse.quote(os.environ['JENKINS_AGENT_NAME'], safe=''))
PY
}

auth="$JENKINS_USER:$JENKINS_TOKEN"
mkdir -p "$JENKINS_AGENT_WORKDIR"

echo "Waiting for Jenkins at ${JENKINS_URL}..."
until curl -fsS -u "$auth" "${JENKINS_URL}login" >/dev/null 2>&1; do
  sleep 5
done

cli_jar="${TMPDIR:-/tmp}/jenkins-cli.jar"
curl -fsS -u "$auth" -o "$cli_jar" "${JENKINS_URL}jnlpJars/jenkins-cli.jar"

jenkins_cli() {
  java -jar "$cli_jar" -s "$JENKINS_URL" -auth "$auth" -http "$@"
}

node_xml="${TMPDIR:-/tmp}/jobseeker-agent-${JENKINS_AGENT_NAME}.xml"
cat > "$node_xml" <<EOF
<slave>
  <name>$(xml_escape "$JENKINS_AGENT_NAME")</name>
  <description>$(xml_escape "$JENKINS_AGENT_DESCRIPTION")</description>
  <remoteFS>$(xml_escape "$JENKINS_AGENT_WORKDIR")</remoteFS>
  <numExecutors>${JENKINS_AGENT_EXECUTORS}</numExecutors>
  <mode>$(xml_escape "$JENKINS_AGENT_MODE")</mode>
  <retentionStrategy class="hudson.slaves.RetentionStrategy\$Always"/>
  <launcher class="hudson.slaves.JNLPLauncher">
    <workDirSettings>
      <disabled>false</disabled>
      <workDirPath></workDirPath>
      <internalDir>remoting</internalDir>
      <failIfWorkDirIsMissing>false</failIfWorkDirIsMissing>
    </workDirSettings>
    <webSocket>${JENKINS_AGENT_WEB_SOCKET}</webSocket>
  </launcher>
  <label>$(xml_escape "$JENKINS_AGENT_LABELS")</label>
  <nodeProperties/>
</slave>
EOF

if jenkins_cli get-node "$JENKINS_AGENT_NAME" >/dev/null 2>&1; then
  jenkins_cli update-node "$JENKINS_AGENT_NAME" < "$node_xml"
else
  jenkins_cli create-node "$JENKINS_AGENT_NAME" < "$node_xml"
fi

agent_path="$(url_encode_agent_name)"
agent_secret=""
for endpoint in "computer/${agent_path}/jenkins-agent.jnlp" "computer/${agent_path}/slave-agent.jnlp"; do
  jnlp="$(curl -fsS -u "$auth" "${JENKINS_URL}${endpoint}" 2>/dev/null || true)"
  agent_secret="$(printf '%s' "$jnlp" | tr '<' '\n<' | sed -n 's/^argument>\([^<][^<]*\).*/\1/p' | head -n1)"
  if [ -n "$agent_secret" ]; then
    break
  fi
done

if [ -z "$agent_secret" ]; then
  echo "Unable to discover inbound agent secret for ${JENKINS_AGENT_NAME}." >&2
  exit 1
fi

export JENKINS_URL JENKINS_AGENT_NAME JENKINS_AGENT_WORKDIR
export JENKINS_SECRET="$agent_secret"

if [ "$JENKINS_AGENT_WEB_SOCKET" = "true" ]; then
  export JENKINS_WEB_SOCKET=true
  unset JENKINS_TUNNEL
else
  unset JENKINS_WEB_SOCKET
  export JENKINS_TUNNEL="$JENKINS_AGENT_TUNNEL"
fi

exec jenkins-agent