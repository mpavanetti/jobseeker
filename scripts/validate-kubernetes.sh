#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
base_dir="$root/deploy/kubernetes/base"
standalone_dir="$root/deploy/kubernetes/overlays/standalone"

command -v kubectl >/dev/null 2>&1 || {
  echo "kubectl is required to validate Kubernetes resources." >&2
  exit 127
}

multi_render=$(mktemp)
standalone_render=$(mktemp)
trap 'rm -f "$multi_render" "$standalone_render"' EXIT HUP INT TERM

kubectl kustomize "$base_dir" > "$multi_render"
kubectl kustomize "$standalone_dir" > "$standalone_render"

for rendered in "$multi_render" "$standalone_render"; do
  grep -q 'kind: HorizontalPodAutoscaler' "$rendered"
  grep -q 'kind: StatefulSet' "$rendered"
  grep -q 'name: jobseeker-jenkins-agent-manager' "$rendered"
done
grep -q 'JOBSEEKER_DEPLOYMENT_MODE: multi' "$multi_render"
grep -q 'JOBSEEKER_DEPLOYMENT_MODE: standalone' "$standalone_render"
grep -q 'casc-kubernetes-standalone.yaml' "$standalone_render"

echo "Multi-environment and standalone Kubernetes resources render successfully with the required workload, scaling, and RBAC objects."
