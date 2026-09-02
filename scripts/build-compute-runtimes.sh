#!/usr/bin/env bash
#
# Build the JobSeeker Spark and ML runtime images.
#
# By default the images are built on the in-stack Docker-in-Docker engine
# (the same engine that runs the ephemeral job clusters), so set DOCKER_HOST
# to point at it when running from the host:
#
#   DOCKER_HOST=tcp://localhost:2375 scripts/build-compute-runtimes.sh
#
# or run it through docker compose:
#
#   docker compose --profile runtimes up --build
#
# Build a single image:  scripts/build-compute-runtimes.sh spark-4.0-python
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# name|dockerfile|context|tag
RUNTIMES=(
  "spark-4.0-python|docker/spark/Dockerfile.spark-4.0-python|docker/spark|jobseeker/spark-runtime:4.0.0-python"
  "spark-4.0-python-notebook|docker/spark/Dockerfile.spark-4.0-python-notebook|docker/spark|jobseeker/spark-runtime:4.0.0-python-notebook"
  "spark-4.0-scala|docker/spark/Dockerfile.spark-4.0-scala|docker/spark|jobseeker/spark-runtime:4.0.0-scala"
  "ml-cpu|docker/ml/Dockerfile.ml-cpu|docker/ml|jobseeker/ml-runtime:cpu"
  "ml-dl-cpu|docker/ml/Dockerfile.ml-dl-cpu|docker/ml|jobseeker/ml-runtime:dl-cpu"
)

WANT="${1:-all}"
BUILT=0

for entry in "${RUNTIMES[@]}"; do
  IFS='|' read -r name dockerfile context tag <<<"$entry"
  if [ "$WANT" != "all" ] && [ "$WANT" != "$name" ]; then
    continue
  fi
  echo "==> building ${tag}  (${dockerfile})"
  docker build -t "$tag" -f "$dockerfile" "$context"
  BUILT=$((BUILT + 1))
done

if [ "$BUILT" -eq 0 ]; then
  echo "no runtime matched '${WANT}'. known: all spark-4.0-python spark-4.0-python-notebook spark-4.0-scala ml-cpu ml-dl-cpu" >&2
  exit 2
fi

echo "==> done (${BUILT} image(s)). Target engine: ${DOCKER_HOST:-local docker}"
docker image ls --filter 'reference=jobseeker/spark-runtime' --filter 'reference=jobseeker/ml-runtime'
