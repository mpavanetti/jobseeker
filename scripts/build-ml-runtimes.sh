#!/usr/bin/env bash
# Build the JobSeeker ML runtime images onto the compute engine.
#
# The docker-runtime (DinD) daemon runs ML job containers, so build there:
#   DOCKER_HOST=tcp://docker-runtime:2375 bash scripts/build-ml-runtimes.sh
# From the host you can also point at the local daemon; the images just need to
# be visible to whatever JOBSEEKER_ML_COMPUTE_DRIVER talks to.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTEXT="$(mktemp -d)"
trap 'rm -rf "$CONTEXT"' EXIT

# The Dockerfiles COPY ./sdk, so assemble a build context that has it.
cp -r "$ROOT/repository/ml/sdk" "$CONTEXT/sdk"

build() {
  local dockerfile="$1" tag="$2"
  echo ">> building $tag from $dockerfile"
  docker build -f "$ROOT/docker/ml/$dockerfile" -t "$tag" "$CONTEXT"
}

build Dockerfile.ml-cpu    jobseeker/ml-runtime:cpu
build Dockerfile.ml-dl-cpu jobseeker/ml-runtime:dl-cpu

echo ">> done. images:"
docker image ls 'jobseeker/ml-runtime'
