#!/bin/sh
#
# Make the JobSeeker Apache Hop image available to Jenkins jobs.
#
# Jenkins builds talk to the docker-runtime (Docker-in-Docker) daemon, which has
# its own image store and can only pull from a registry. `docker compose build`
# writes to the host daemon instead, so a locally built image is invisible to
# jobs until it is copied across. This script does that copy.
#
# Without it, container-engine Hop jobs still work: they fall back to the public
# apache/hop image and install the JDBC drivers they need on container start,
# which costs about a minute per build. Running this once removes that cost.
#
# Usage:
#   docker compose --profile hop build hop-server   # build jobseeker-hop:local
#   ./scripts/load-hop-image.sh
#   # then set JOBSEEKER_HOP_IMAGE=jobseeker-hop:local in .env and restart
#
set -eu

IMAGE="${JOBSEEKER_HOP_IMAGE:-jobseeker-hop:local}"
RUNTIME_SERVICE="${JOBSEEKER_DOCKER_RUNTIME_SERVICE:-docker-runtime}"

if ! docker image inspect "${IMAGE}" >/dev/null 2>&1; then
  echo "Image ${IMAGE} was not found on this host." >&2
  echo "Build it first:  docker compose --profile hop build hop-server" >&2
  exit 1
fi

echo "Copying ${IMAGE} into the ${RUNTIME_SERVICE} daemon that Jenkins jobs use..."
docker save "${IMAGE}" | docker compose exec -T "${RUNTIME_SERVICE}" docker load

echo
echo "Done. Set this in .env so Hop jobs use it, then recreate Jenkins and the agents:"
echo "  JOBSEEKER_HOP_IMAGE=${IMAGE}"
