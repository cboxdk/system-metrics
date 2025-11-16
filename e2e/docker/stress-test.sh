#!/bin/sh

# Stress test script for E2E testing

set -e

echo "==> Starting stress test"

# Default values
CPU_WORKERS=${CPU_WORKERS:-2}
MEMORY_MB=${MEMORY_MB:-100}
DURATION=${DURATION:-5}

echo "CPU workers: $CPU_WORKERS"
echo "Memory: ${MEMORY_MB}MB"
echo "Duration: ${DURATION}s"

# Install stress-ng if not available
if ! command -v stress-ng >/dev/null 2>&1; then
    echo "Installing stress-ng..."
    apk add --no-cache stress-ng
fi

# Run stress test
stress-ng --cpu "$CPU_WORKERS" \
          --vm 1 \
          --vm-bytes "${MEMORY_MB}M" \
          --timeout "${DURATION}s" \
          --verbose

echo "==> Stress test complete"
