#!/usr/bin/env bash

# setup-kind.sh - Setup Kind cluster for E2E testing
#
# Usage:
#   ./e2e/scripts/setup-kind.sh

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

CLUSTER_NAME="system-metrics-test"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
KIND_DIR="$PROJECT_ROOT/e2e/kind"

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Kind Cluster Setup${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

# Check if kind is installed
if ! command -v kind &> /dev/null; then
    echo -e "${RED}Error: kind not found${NC}"
    echo -e "${YELLOW}Install from: https://kind.sigs.k8s.io/docs/user/quick-start/#installation${NC}"
    exit 1
fi

# Check if kubectl is installed
if ! command -v kubectl &> /dev/null; then
    echo -e "${RED}Error: kubectl not found${NC}"
    exit 1
fi

# Delete existing cluster if it exists
if kind get clusters | grep -q "$CLUSTER_NAME"; then
    echo -e "${YELLOW}→ Deleting existing cluster...${NC}"
    kind delete cluster --name "$CLUSTER_NAME"
fi

# Create cluster
echo -e "${YELLOW}→ Creating Kind cluster...${NC}"
kind create cluster \
    --name "$CLUSTER_NAME" \
    --config "$KIND_DIR/cluster-config.yaml" \
    --wait 120s

if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Failed to create Kind cluster${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Cluster created${NC}"
echo ""

# Wait for nodes to be ready
echo -e "${YELLOW}→ Waiting for nodes to be ready...${NC}"
kubectl wait --for=condition=Ready nodes --all --timeout=120s --context "kind-$CLUSTER_NAME"

if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Nodes did not become ready${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Nodes ready${NC}"
echo ""

# Create namespace
echo -e "${YELLOW}→ Creating metrics-test namespace...${NC}"
kubectl create namespace metrics-test \
    --dry-run=client -o yaml \
    --context "kind-$CLUSTER_NAME" | \
    kubectl apply -f - --context "kind-$CLUSTER_NAME"

echo -e "${GREEN}✓ Namespace created${NC}"
echo ""

# Apply resource quota
echo -e "${YELLOW}→ Applying resource quota...${NC}"
kubectl apply -f "$KIND_DIR/resource-quota.yaml" --context "kind-$CLUSTER_NAME"

echo -e "${GREEN}✓ Resource quota applied${NC}"
echo ""

# Load PHP image into Kind
echo -e "${YELLOW}→ Loading PHP image into Kind...${NC}"
docker pull php:8.3-cli --quiet
kind load docker-image php:8.3-cli --name "$CLUSTER_NAME"

echo -e "${GREEN}✓ PHP image loaded${NC}"
echo ""

# Deploy test pods
echo -e "${YELLOW}→ Deploying test pods...${NC}"

kubectl apply -f "$KIND_DIR/pod-cpu-limit.yaml" --context "kind-$CLUSTER_NAME"
kubectl apply -f "$KIND_DIR/pod-memory-limit.yaml" --context "kind-$CLUSTER_NAME"

echo -e "${YELLOW}  Waiting for pods to be ready...${NC}"

# Wait for CPU test pod
kubectl wait --for=condition=Ready \
    pod/php-metrics-cpu-test \
    -n metrics-test \
    --timeout=120s \
    --context "kind-$CLUSTER_NAME" 2>/dev/null || true

# Wait for memory test pod (might OOM, so don't fail)
kubectl wait --for=condition=Ready \
    pod/php-metrics-memory-test \
    -n metrics-test \
    --timeout=120s \
    --context "kind-$CLUSTER_NAME" 2>/dev/null || true

echo -e "${GREEN}✓ Test pods deployed${NC}"
echo ""

# Display cluster info
echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}Cluster Information${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

echo -e "${YELLOW}Cluster name:${NC} $CLUSTER_NAME"
echo -e "${YELLOW}Context:${NC} kind-$CLUSTER_NAME"
echo ""

echo -e "${YELLOW}Nodes:${NC}"
kubectl get nodes --context "kind-$CLUSTER_NAME"
echo ""

echo -e "${YELLOW}Namespace resources:${NC}"
kubectl get all -n metrics-test --context "kind-$CLUSTER_NAME"
echo ""

echo -e "${YELLOW}Resource quota:${NC}"
kubectl describe resourcequota metrics-quota -n metrics-test --context "kind-$CLUSTER_NAME"
echo ""

echo -e "${GREEN}✓ Kind cluster ready for E2E tests${NC}"
echo ""

echo -e "${YELLOW}Usage:${NC}"
echo -e "  Run tests:  vendor/bin/pest tests/E2E/Kubernetes/"
echo -e "  View pods:  kubectl get pods -n metrics-test"
echo -e "  View logs:  kubectl logs -n metrics-test php-metrics-cpu-test"
echo -e "  Cleanup:    bash e2e/scripts/cleanup.sh"
