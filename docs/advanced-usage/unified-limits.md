# Unified Limits API

Get actual resource limits and current usage regardless of environment (bare metal, VM, or container).

## Overview

The Unified Limits API provides a single interface for checking resource limits that works correctly whether you're running on bare metal, in a VM, or inside a container. It automatically detects and uses the appropriate limits for your environment.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$limits = SystemMetrics::limits()->getValue();
```

## Understanding the Source

```php
echo "Source: {$limits->source->value}\n";
// Values: "host", "cgroup_v1", "cgroup_v2"

echo "Containerized: " . ($limits->isContainerized() ? 'yes' : 'no') . "\n";
```

## CPU Limits

```php
echo "Total CPU cores: {$limits->cpuCores}\n";
echo "Current usage: {$limits->currentCpuCores} cores\n";
echo "Available: {$limits->availableCpuCores()} cores\n";
echo "Utilization: " . round($limits->cpuUtilization(), 1) . "%\n";
echo "Headroom: " . round($limits->cpuHeadroom(), 1) . "%\n";
```

## Memory Limits

```php
$totalGB = round($limits->memoryBytes / 1024**3, 2);
$currentGB = round($limits->currentMemoryBytes / 1024**3, 2);
$availableGB = round($limits->availableMemoryBytes() / 1024**3, 2);

echo "Total memory: {$totalGB} GB\n";
echo "Current usage: {$currentGB} GB\n";
echo "Available: {$availableGB} GB\n";
echo "Utilization: " . round($limits->memoryUtilization(), 1) . "%\n";
echo "Headroom: " . round($limits->memoryHeadroom(), 1) . "%\n";
```

## Vertical Scaling Decisions

Check if you can safely scale resources before attempting:

```php
// Can we add 2 more CPU cores?
if ($limits->canScaleCpu(2)) {
    echo "✅ Safe to add 2 more CPU cores\n";
} else {
    echo "⚠️ Cannot add 2 more CPU cores (would exceed limit)\n";
}

// Can we allocate 4 GB more memory?
if ($limits->canScaleMemory(4 * 1024**3)) {
    echo "✅ Safe to allocate 4 GB more memory\n";
} else {
    echo "⚠️ Cannot allocate 4 GB more memory (would exceed limit)\n";
}
```

## Pressure Detection

Detect when approaching resource limits:

```php
if ($limits->isMemoryPressure()) {
    echo "🚨 Memory pressure detected (>80% utilization)\n";
}

if ($limits->isCpuPressure()) {
    echo "🚨 CPU pressure detected (>80% utilization)\n";
}

// Custom thresholds
if ($limits->isMemoryPressure(0.90)) {  // 90% threshold
    echo "🚨 Critical memory pressure\n";
}
```

## Complete Example

```php
use PHPeek\SystemMetrics\SystemMetrics;

$limits = SystemMetrics::limits()->getValue();

echo "=== RESOURCE LIMITS ===\n";
echo "Source: {$limits->source->value}\n";
echo "Containerized: " . ($limits->isContainerized() ? 'yes' : 'no') . "\n\n";

echo "=== CPU ===\n";
echo "Total cores: {$limits->cpuCores}\n";
echo "Current usage: {$limits->currentCpuCores} cores\n";
echo "Available: {$limits->availableCpuCores()} cores\n";
echo "Utilization: " . round($limits->cpuUtilization(), 1) . "%\n";
echo "Headroom: " . round($limits->cpuHeadroom(), 1) . "%\n\n";

echo "=== MEMORY ===\n";
$totalGB = round($limits->memoryBytes / 1024**3, 2);
$currentGB = round($limits->currentMemoryBytes / 1024**3, 2);
$availableGB = round($limits->availableMemoryBytes() / 1024**3, 2);
echo "Total: {$totalGB} GB\n";
echo "Current: {$currentGB} GB\n";
echo "Available: {$availableGB} GB\n";
echo "Utilization: " . round($limits->memoryUtilization(), 1) . "%\n";
echo "Headroom: " . round($limits->memoryHeadroom(), 1) . "%\n";
```

## Use Cases

### Auto-Scaling Logic

```php
$limits = SystemMetrics::limits()->getValue();

if ($limits->memoryUtilization() > 75) {
    if ($limits->isContainerized()) {
        echo "Scale container memory limit\n";
    } else {
        echo "Add more physical RAM or scale horizontally\n";
    }
}
```

### Worker Concurrency

```php
$limits = SystemMetrics::limits()->getValue();

// Calculate safe worker count based on available resources
$memoryPerWorker = 256 * 1024 * 1024; // 256 MB
$maxWorkersByMemory = (int) floor($limits->availableMemoryBytes() / $memoryPerWorker);
$maxWorkersByCpu = (int) $limits->availableCpuCores();

$workerCount = min($maxWorkersByMemory, $maxWorkersByCpu);
echo "Safe worker count: {$workerCount}\n";
```

### Resource Planning

```php
$limits = SystemMetrics::limits()->getValue();

// Check if we can handle peak load
$peakCpuNeeded = 4.0;
$peakMemoryNeeded = 8 * 1024**3; // 8 GB

if ($limits->canScaleCpu($peakCpuNeeded) && $limits->canScaleMemory($peakMemoryNeeded)) {
    echo "✅ System can handle peak load\n";
} else {
    echo "⚠️ Insufficient resources for peak load\n";
}
```

## Decision Logic

The API automatically selects the appropriate source:

1. Checks if running in container with cgroup limits
2. If cgroup limits found, uses those (container-aware)
3. Otherwise falls back to host limits (bare metal/VM)

## Related Documentation

- [Container Metrics](container-metrics.md) - Detailed cgroup metrics
- [CPU Metrics](../basic-usage/cpu-metrics.md)
- [Memory Metrics](../basic-usage/memory-metrics.md)
