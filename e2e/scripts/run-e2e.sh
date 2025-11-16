#!/usr/bin/env bash

# run-e2e.sh - Execute all E2E tests for PHPeek/SystemMetrics
#
# Usage:
#   ./e2e/scripts/run-e2e.sh [options]
#
# Options:
#   --docker-only     Run only Docker tests (skip Kubernetes)
#   --k8s-only       Run only Kubernetes tests (skip Docker)
#   --skip-setup     Skip Docker Compose and Kind setup
#   --cleanup        Cleanup resources after tests
#   --help           Show this help message

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default options
DOCKER_TESTS=true
K8S_TESTS=true
SKIP_SETUP=false
CLEANUP=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --docker-only)
            K8S_TESTS=false
            shift
            ;;
        --k8s-only)
            DOCKER_TESTS=false
            shift
            ;;
        --skip-setup)
            SKIP_SETUP=true
            shift
            ;;
        --cleanup)
            CLEANUP=true
            shift
            ;;
        --help)
            head -n 14 "$0" | tail -n +2
            exit 0
            ;;
        *)
            echo -e "${RED}Error: Unknown option $1${NC}"
            exit 1
            ;;
    esac
done

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
E2E_DIR="$PROJECT_ROOT/e2e"

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}PHPeek/SystemMetrics - E2E Test Suite${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

# Check prerequisites
echo -e "${YELLOW}→ Checking prerequisites...${NC}"

if [ "$DOCKER_TESTS" = true ]; then
    if ! command -v docker &> /dev/null; then
        echo -e "${RED}Error: docker command not found${NC}"
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        echo -e "${RED}Error: docker-compose not found${NC}"
        exit 1
    fi
fi

if [ "$K8S_TESTS" = true ]; then
    if ! command -v kind &> /dev/null; then
        echo -e "${RED}Error: kind command not found${NC}"
        echo -e "${YELLOW}Install: https://kind.sigs.k8s.io/docs/user/quick-start/#installation${NC}"
        exit 1
    fi

    if ! command -v kubectl &> /dev/null; then
        echo -e "${RED}Error: kubectl command not found${NC}"
        exit 1
    fi
fi

echo -e "${GREEN}✓ Prerequisites met${NC}"
echo ""

# Setup Docker Compose environment
if [ "$DOCKER_TESTS" = true ] && [ "$SKIP_SETUP" = false ]; then
    echo -e "${YELLOW}→ Setting up Docker Compose environment...${NC}"
    cd "$E2E_DIR/compose"

    # Build and start containers
    docker-compose down -v 2>/dev/null || true
    docker-compose build --quiet
    docker-compose up -d

    echo -e "${YELLOW}  Waiting for containers to be ready...${NC}"
    sleep 5

    # Verify containers are running
    if docker-compose ps | grep -q "Up"; then
        echo -e "${GREEN}✓ Docker containers ready${NC}"
    else
        echo -e "${RED}Error: Docker containers failed to start${NC}"
        docker-compose logs
        exit 1
    fi

    cd "$PROJECT_ROOT"
    echo ""
fi

# Setup Kind cluster
if [ "$K8S_TESTS" = true ] && [ "$SKIP_SETUP" = false ]; then
    echo -e "${YELLOW}→ Setting up Kind cluster...${NC}"

    if ! kind get clusters | grep -q "system-metrics-test"; then
        bash "$SCRIPT_DIR/setup-kind.sh"
    else
        echo -e "${GREEN}✓ Kind cluster already exists${NC}"
    fi

    echo ""
fi

# Run Docker tests
if [ "$DOCKER_TESTS" = true ]; then
    echo -e "${BLUE}======================================${NC}"
    echo -e "${BLUE}Docker E2E Tests${NC}"
    echo -e "${BLUE}======================================${NC}"
    echo ""

    echo -e "${YELLOW}→ Running Docker CgroupV1 tests...${NC}"
    vendor/bin/pest tests/E2E/Docker/CgroupV1/ --colors=always || true
    echo ""

    echo -e "${YELLOW}→ Running Docker CgroupV2 tests...${NC}"
    vendor/bin/pest tests/E2E/Docker/CgroupV2/ --colors=always || true
    echo ""
fi

# Run Kubernetes tests
if [ "$K8S_TESTS" = true ]; then
    echo -e "${BLUE}======================================${NC}"
    echo -e "${BLUE}Kubernetes E2E Tests${NC}"
    echo -e "${BLUE}======================================${NC}"
    echo ""

    echo -e "${YELLOW}→ Running Kubernetes tests...${NC}"
    vendor/bin/pest tests/E2E/Kubernetes/ --colors=always || true
    echo ""
fi

# Cleanup
if [ "$CLEANUP" = true ]; then
    echo -e "${BLUE}======================================${NC}"
    echo -e "${BLUE}Cleanup${NC}"
    echo -e "${BLUE}======================================${NC}"
    echo ""

    bash "$SCRIPT_DIR/cleanup.sh"
fi

echo -e "${BLUE}======================================${NC}"
echo -e "${GREEN}E2E tests completed!${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

echo -e "${YELLOW}Manual cleanup (if needed):${NC}"
echo -e "  Docker:     cd e2e/compose && docker-compose down -v"
echo -e "  Kubernetes: bash e2e/scripts/cleanup.sh"
