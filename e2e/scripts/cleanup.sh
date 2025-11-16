#!/usr/bin/env bash

# cleanup.sh - Cleanup E2E test resources
#
# Usage:
#   ./e2e/scripts/cleanup.sh [options]
#
# Options:
#   --docker-only    Cleanup only Docker resources
#   --k8s-only       Cleanup only Kubernetes resources
#   --all            Cleanup everything (default)

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DOCKER_CLEANUP=true
K8S_CLEANUP=true

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --docker-only)
            K8S_CLEANUP=false
            shift
            ;;
        --k8s-only)
            DOCKER_CLEANUP=false
            shift
            ;;
        --all)
            # Default, both enabled
            shift
            ;;
        *)
            echo -e "${RED}Error: Unknown option $1${NC}"
            exit 1
            ;;
    esac
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
E2E_DIR="$PROJECT_ROOT/e2e"
CLUSTER_NAME="system-metrics-test"

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}E2E Test Cleanup${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

# Cleanup Docker resources
if [ "$DOCKER_CLEANUP" = true ]; then
    echo -e "${YELLOW}→ Cleaning up Docker resources...${NC}"

    cd "$E2E_DIR/compose"

    if docker-compose ps -q | grep -q .; then
        echo -e "${YELLOW}  Stopping containers...${NC}"
        docker-compose down -v --remove-orphans

        echo -e "${GREEN}✓ Docker containers stopped and removed${NC}"
    else
        echo -e "${YELLOW}  No Docker containers to cleanup${NC}"
    fi

    cd "$PROJECT_ROOT"
    echo ""
fi

# Cleanup Kubernetes resources
if [ "$K8S_CLEANUP" = true ]; then
    echo -e "${YELLOW}→ Cleaning up Kubernetes resources...${NC}"

    if ! command -v kind &> /dev/null; then
        echo -e "${YELLOW}  kind not found, skipping Kubernetes cleanup${NC}"
    elif kind get clusters | grep -q "$CLUSTER_NAME"; then
        echo -e "${YELLOW}  Deleting namespace metrics-test...${NC}"
        kubectl delete namespace metrics-test \
            --ignore-not-found=true \
            --context "kind-$CLUSTER_NAME" \
            --timeout=60s 2>/dev/null || true

        echo -e "${YELLOW}  Deleting Kind cluster...${NC}"
        kind delete cluster --name "$CLUSTER_NAME"

        echo -e "${GREEN}✓ Kind cluster deleted${NC}"
    else
        echo -e "${YELLOW}  No Kind cluster to cleanup${NC}"
    fi

    echo ""
fi

echo -e "${GREEN}✓ Cleanup completed${NC}"
echo ""

# Show remaining resources (if any)
if [ "$DOCKER_CLEANUP" = true ]; then
    echo -e "${YELLOW}Docker containers (system-metrics):${NC}"
    docker ps -a --filter "name=system-metrics" --format "table {{.Names}}\t{{.Status}}" || echo "  None"
    echo ""
fi

if [ "$K8S_CLEANUP" = true ] && command -v kind &> /dev/null; then
    echo -e "${YELLOW}Kind clusters:${NC}"
    kind get clusters | grep system-metrics || echo "  None"
    echo ""
fi

echo -e "${BLUE}======================================${NC}"
echo -e "${GREEN}All E2E resources cleaned up!${NC}"
echo -e "${BLUE}======================================${NC}"
