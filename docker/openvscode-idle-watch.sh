#!/bin/sh

set -eu

server="${OPENVSCODE_SERVER_ROOT}/bin/openvscode-server"
idle_minutes="${JOBSEEKER_OPENVSCODE_IDLE_TIMEOUT_MINUTES:-30}"
check_seconds="${JOBSEEKER_OPENVSCODE_IDLE_CHECK_SECONDS:-10}"

case "$idle_minutes" in
  ''|*[!0-9]*)
    echo "[JobSeeker OpenVSCode] Invalid idle timeout '$idle_minutes'; using 30 minutes." >&2
    idle_minutes=30
    ;;
esac

case "$check_seconds" in
  ''|*[!0-9]*) check_seconds=10 ;;
esac

if [ "$idle_minutes" -gt 1440 ]; then
  idle_minutes=1440
fi
if [ "$check_seconds" -lt 2 ]; then
  check_seconds=2
fi

if [ "$idle_minutes" -eq 0 ]; then
  echo "[JobSeeker OpenVSCode] Automatic idle shutdown is disabled."
  exec "$server" --host 0.0.0.0 --connection-token "$JOBSEEKER_OPENVSCODE_TOKEN" "$@"
fi

idle_seconds=$((idle_minutes * 60))

# A browser keeps at least one HTTP/WebSocket connection open while the editor
# is connected. Loopback peers are Docker/PHP health probes and must not keep
# an otherwise unused editor alive.
has_connected_editor()
{
  awk '
    $2 ~ /:0BB8$/ && $4 == "01" {
      split($3, remote, ":")
      address = remote[1]
      if (address != "0100007F" &&
          address != "00000000000000000000000001000000" &&
          address != "00000000000000000000000000000000") {
        connected = 1
      }
    }
    END { exit connected ? 0 : 1 }
  ' /proc/net/tcp /proc/net/tcp6 2>/dev/null
}

"$server" --host 0.0.0.0 --connection-token "$JOBSEEKER_OPENVSCODE_TOKEN" "$@" &
server_pid=$!
idle_stop=0
last_connected_at=$(date +%s)

forward_signal()
{
  kill -TERM "$server_pid" 2>/dev/null || true
}

trap forward_signal TERM INT HUP

echo "[JobSeeker OpenVSCode] Idle shutdown enabled after $idle_minutes minute(s) without a connected editor."

while kill -0 "$server_pid" 2>/dev/null; do
  now=$(date +%s)
  if has_connected_editor; then
    last_connected_at=$now
  elif [ $((now - last_connected_at)) -ge "$idle_seconds" ]; then
    idle_stop=1
    echo "[JobSeeker OpenVSCode] No editor has been connected for $idle_minutes minute(s); stopping the container."
    kill -TERM "$server_pid" 2>/dev/null || true
    break
  fi
  sleep "$check_seconds"
done

set +e
wait "$server_pid"
server_status=$?
set -e

if [ "$idle_stop" -eq 1 ]; then
  exit 0
fi

exit "$server_status"
