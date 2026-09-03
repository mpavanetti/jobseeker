# Kubernetes deployment

This deployment runs the JobSeeker web tier with horizontal autoscaling and
uses the Jenkins Kubernetes plugin to create a disposable agent pod for each
build. Existing freestyle jobs keep their Jenkins history, schedules, grouped
console output, environment routing, connectors, and Data Asset paths.

## Prerequisites

- A Kubernetes cluster with a default StorageClass and Metrics Server.
- A `ReadWriteMany` StorageClass for `jobseeker-repository` and
  `jobseeker-app-state`. The PHP replicas, OpenVSCode, Jenkins controller, and
  disposable agents must see the same files.
- An OCI registry reachable by every cluster node.
- `kubectl` with Kustomize support.

The base uses namespace-scoped RBAC. Only the Jenkins controller service
account can create and inspect agent pods. Agent pods use a service account
with no RBAC permissions and do not receive an API token.

The default `JOBSEEKER_KUBERNETES_SERVER_URL` uses the in-cluster API Service.
An externally hosted Jenkins controller can instead set that value to the
cluster API URL and provide an appropriate Kubernetes credential.

## Deployment modes

`deploy/kubernetes/base` is the default `multi` topology. It runs one control
plane for all environments and configures separate DEV, QA, UAT, and PROD
dynamic-agent templates.

`deploy/kubernetes/overlays/standalone` is the one-environment topology. Deploy
one copy to each environment's infrastructure. Before applying it, copy the
overlay into your own configuration repository and change these values
together in `config.yaml`:

```yaml
JOBSEEKER_STANDALONE_ENVIRONMENT: PROD
JOBSEEKER_JENKINS_ENVIRONMENT_SLOTS: PROD=10
JOBSEEKER_JENKINS_ENVIRONMENT_AGENT_LABELS: PROD=jobseeker-env-standalone
JOBSEEKER_KUBERNETES_CONTAINER_CAP: "10"
JOBSEEKER_KUBERNETES_STANDALONE_AGENT_CAP: "10"
```

The standalone JCasC file exposes only one generic pod template. JobSeeker
fixes every application and worker request to the configured environment and
disables in-process cross-environment promotion. Use your release pipeline to
apply the same image/artifact version to DEV, QA, UAT, and PROD clusters.

## Build and publish images

Choose a registry and immutable tag, then build the six project images:

```bash
export REGISTRY=registry.example.com/jobseeker
export TAG=0.2.0

docker build -f docker/php_image -t "$REGISTRY/php:$TAG" .
docker build -f docker/nginx_image -t "$REGISTRY/nginx:$TAG" .
docker build -f docker/jenkins_image -t "$REGISTRY/jenkins:$TAG" .
docker build -f docker/jenkins_kubernetes_agent_image -t "$REGISTRY/jenkins-agent:$TAG" .
docker build -f docker/mariadb_image -t "$REGISTRY/mariadb:$TAG" .
docker build -f docker/openvscode_image -t "$REGISTRY/openvscode:$TAG" .

docker push "$REGISTRY/php:$TAG"
docker push "$REGISTRY/nginx:$TAG"
docker push "$REGISTRY/jenkins:$TAG"
docker push "$REGISTRY/jenkins-agent:$TAG"
docker push "$REGISTRY/mariadb:$TAG"
docker push "$REGISTRY/openvscode:$TAG"
```

Create an overlay rather than editing the base. At minimum, replace every
placeholder Secret value, select RWX storage, set public URLs, and pin the
published images:

```yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization
namespace: jobseeker
resources:
  - ../../base
patches:
  - path: secret.yaml
  - path: storage.yaml
  - path: public-urls.yaml
images:
  - name: jobseeker-php
    newName: registry.example.com/jobseeker/php
    newTag: 0.2.0
  - name: jobseeker-nginx
    newName: registry.example.com/jobseeker/nginx
    newTag: 0.2.0
  - name: jobseeker-jenkins
    newName: registry.example.com/jobseeker/jenkins
    newTag: 0.2.0
  - name: jobseeker-jenkins-kubernetes-agent
    newName: registry.example.com/jobseeker/jenkins-agent
    newTag: 0.2.0
  - name: jobseeker-mariadb
    newName: registry.example.com/jobseeker/mariadb
    newTag: 0.2.0
  - name: jobseeker-openvscode
    newName: registry.example.com/jobseeker/openvscode
    newTag: 0.2.0
```

Also update `JOBSEEKER_KUBERNETES_AGENT_IMAGE` in `jobseeker-config`; the
Jenkins pod templates reference that value directly.

Review the rendered resources, apply them, and wait for the stateful services:

```bash
kubectl kustomize deploy/kubernetes/base > /tmp/jobseeker-kubernetes.yaml
# Server-side validation needs the target namespace to exist first.
kubectl apply -f deploy/kubernetes/base/namespace.yaml
kubectl apply --dry-run=server -k deploy/kubernetes/base
kubectl apply -k deploy/kubernetes/base
kubectl -n jobseeker rollout status statefulset/mariadb
kubectl -n jobseeker rollout status statefulset/jenkins
kubectl -n jobseeker rollout status deployment/php
kubectl -n jobseeker rollout status deployment/nginx
```

For a standalone installation, replace `deploy/kubernetes/base` in those
commands with your customized standalone overlay (or use
`deploy/kubernetes/overlays/standalone` to evaluate the DEV example).

Use `ingress.example.yaml` as a starting point for an ingress controller and
TLS certificate used by your cluster. The file is intentionally not applied by
the base because ingress classes and certificate issuers are cluster-specific.

## Scaling model

- Nginx and PHP start with two replicas and scale from CPU metrics up to ten.
  Database-backed CodeIgniter sessions allow requests to move between replicas.
- MariaDB, Jenkins, and OpenVSCode are single-writer workloads. Scale their
  backing services vertically or replace MariaDB with a managed compatible
  database; do not increase their replica counts blindly.
- Each environment has a Jenkins pod template and instance cap. A queued job
  with `jobseeker-env-dev`, `jobseeker-env-qa`, `jobseeker-env-uat`, or
  `jobseeker-env-prod` starts a fresh agent. The agent is removed after the
  build, while Jenkins keeps its build record and console.
- JobSeeker environment slots and Kubernetes pod caps should normally match.
  The Executor Monitor's Kubernetes setup helper calculates a starting point.
- Resource requests are the scheduler's placement signal; limits are the hard
  ceiling. Tune both from observed job and application metrics.

## Runtime compatibility

Jenkins-agent Python, shell, and Talend jobs run directly on the disposable
agent image. Scoped connector materialization and the shared Data Asset
repository work without changing a job definition.

The generic deployment does not mount a host Docker socket and does not create
a privileged Docker-in-Docker sidecar. Existing **Docker Container** job mode
therefore needs an explicitly secured remote Docker/BuildKit service or a
cluster-specific unprivileged builder. Keeping that capability out of the base
avoids granting every agent root-equivalent node access.

OpenVSCode is managed as a Kubernetes Deployment, so JobSeeker checks its
Service health instead of trying to start or stop a Docker container. Keep it
at one replica unless editor workspaces are assigned per user.

## Operations

Useful checks:

```bash
kubectl -n jobseeker get deploy,statefulset,pods,hpa,pdb
kubectl -n jobseeker describe hpa php
kubectl -n jobseeker logs statefulset/jenkins -f
kubectl -n jobseeker get pods -l app.kubernetes.io/component=jenkins-agent -w
kubectl auth can-i create pods --as=system:serviceaccount:jobseeker:jobseeker-jenkins -n jobseeker
kubectl auth can-i get pods --as=system:serviceaccount:jobseeker:jobseeker-agent -n jobseeker
```

The last command should answer `no`. Back up the MariaDB, Jenkins home,
repository, application-state, and OpenVSCode claims according to the storage
provider's snapshot mechanism before upgrades.
