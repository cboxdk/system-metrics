---
title: "System Overview"
description: "Get a complete snapshot of all system metrics at once in a single efficient call"
weight: 10
---

# System Overview

Get a complete snapshot of all system metrics at once.

## Overview

The `SystemMetrics::overview()` method returns a single snapshot containing all available system metrics. This is more efficient than calling each metric individually when you need multiple values.

```php
use Cbox\SystemMetrics\SystemMetrics;

$overview = SystemMetrics::overview()->getValue();
```

## Available Metrics

```php
// Core metrics (always present)
$overview->environment  // EnvironmentSnapshot
$overview->cpu          // CpuSnapshot
$overview->memory       // MemorySnapshot

// Optional metrics (null if unavailable on the current platform)
$overview->storage      // StorageSnapshot|null
$overview->network      // NetworkSnapshot|null
$overview->loadAverage  // LoadAverageSnapshot|null
$overview->uptime       // UptimeSnapshot|null
$overview->limits       // SystemLimits|null      — cgroup-aware resource limits
$overview->container    // ContainerLimits|null   — container-specific cgroup data
```

## Complete Example

```php
use Cbox\SystemMetrics\SystemMetrics;

$overview = SystemMetrics::overview()->getValue();

echo "=== SYSTEM INFO ===\n";
echo "OS: {$overview->environment->os->name} {$overview->environment->os->version}\n";
echo "Architecture: {$overview->environment->architecture->kind->value}\n";
echo "Kernel: {$overview->environment->kernel->release}\n\n";

echo "=== CPU ===\n";
echo "Cores: {$overview->cpu->coreCount()}\n";
echo "Total time: {$overview->cpu->total->total()} ticks\n\n";

echo "=== MEMORY ===\n";
$usedGB = round($overview->memory->usedBytes / 1024**3, 2);
$totalGB = round($overview->memory->totalBytes / 1024**3, 2);
echo "Used: {$usedGB} GB / {$totalGB} GB\n";
echo "Usage: " . round($overview->memory->usedPercentage(), 1) . "%\n\n";

echo "=== LOAD AVERAGE ===\n";
echo "1 min: {$overview->loadAverage->oneMinute}\n";
echo "5 min: {$overview->loadAverage->fiveMinutes}\n";
echo "15 min: {$overview->loadAverage->fifteenMinutes}\n\n";

echo "=== UPTIME ===\n";
echo "Boot time: {$overview->uptime->bootTime->format('Y-m-d H:i:s')}\n";
echo "Uptime: {$overview->uptime->humanReadable()}\n\n";

if ($overview->storage !== null) {
    echo "=== STORAGE ===\n";
    echo "Total: " . round($overview->storage->totalBytes() / 1024**3, 2) . " GB\n";
    echo "Used: " . round($overview->storage->usedPercentage(), 1) . "%\n\n";
}

if ($overview->network !== null) {
    echo "=== NETWORK ===\n";
    echo "Total received: " . round($overview->network->totalBytesReceived() / 1024**3, 2) . " GB\n";
    echo "Total sent: " . round($overview->network->totalBytesSent() / 1024**3, 2) . " GB\n";
}

if ($overview->limits !== null) {
    echo "\n=== RESOURCE LIMITS ===\n";
    echo "Source: {$overview->limits->source->value}\n";
    echo "CPU cores: {$overview->limits->cpuCores}\n";
    echo "Memory: " . round($overview->limits->memoryBytes / 1024**3, 2) . " GB\n";
    echo "Memory utilization: " . round($overview->limits->memoryUtilization(), 1) . "%\n";

    if ($overview->limits->isContainerized()) {
        echo "Running inside container (cgroup limits active)\n";
    }
}

if ($overview->container !== null) {
    echo "\n=== CONTAINER ===\n";
    echo "Cgroup version: {$overview->container->cgroupVersion->value}\n";

    if ($overview->container->hasMemoryLimit()) {
        echo "Memory limit: " . round($overview->container->memoryLimitBytes / 1024**2) . " MB\n";
        echo "Memory utilization: " . round($overview->container->memoryUtilizationPercentage(), 1) . "%\n";
    }

    if ($overview->container->hasCpuLimit()) {
        echo "CPU quota: {$overview->container->cpuQuota} cores\n";
    }
}
```

## Use Cases

### Health Check Endpoint

```php
$overview = SystemMetrics::overview()->getValue();

header('Content-Type: application/json');
echo json_encode([
    'status' => 'healthy',
    'system' => [
        'os' => $overview->environment->os->name,
        'cpu_cores' => $overview->limits?->cpuCores ?? $overview->cpu->coreCount(),
        'memory_total' => $overview->limits?->memoryBytes ?? $overview->memory->totalBytes,
        'memory_usage_percent' => $overview->limits !== null
            ? round($overview->limits->memoryUtilization(), 2)
            : round($overview->memory->usedPercentage(), 2),
        'load_average_1min' => $overview->loadAverage?->oneMinute,
        'uptime_seconds' => $overview->uptime?->totalSeconds,
        'containerized' => $overview->limits?->isContainerized() ?? false,
    ],
]);
```

### Dashboard Data

```php
$overview = SystemMetrics::overview()->getValue();

return [
    'cpu' => [
        'cores' => $overview->cpu->coreCount(),
        'busy_ticks' => $overview->cpu->total->busy(),
    ],
    'memory' => [
        'total_gb' => round($overview->memory->totalBytes / 1024**3, 2),
        'used_percent' => round($overview->memory->usedPercentage(), 1),
    ],
    'load' => [
        'one_minute' => $overview->loadAverage->oneMinute,
        'five_minutes' => $overview->loadAverage->fiveMinutes,
    ],
];
```

## Container-Aware Metrics

When running inside Docker or Kubernetes, `overview()` automatically detects cgroup limits. The `limits` property reflects the **effective** resource limits (cgroup when containerized, host when bare metal), and the `container` property provides cgroup-specific details.

```php
$overview = SystemMetrics::overview()->getValue();

if ($overview->limits?->isContainerized()) {
    // Memory limit is the container limit, not the host
    echo "Container memory: " . round($overview->limits->memoryBytes / 1024**2) . " MB\n";
    echo "Container CPU cores: {$overview->limits->cpuCores}\n";

    // Detailed container info
    if ($overview->container !== null) {
        echo "Throttled: " . ($overview->container->isCpuThrottled() ? 'yes' : 'no') . "\n";
        echo "OOM kills: " . ($overview->container->oomKillCount ?? 0) . "\n";
    }
}
```

## Related Documentation

- [Environment Detection](environment-detection.md)
- [CPU Metrics](cpu-metrics.md)
- [Memory Metrics](memory-metrics.md)
- [Load Average](load-average.md)
- [System Uptime](uptime.md)
- [Storage Metrics](storage-metrics.md)
- [Network Metrics](network-metrics.md)
- [Container Metrics](../advanced-usage/container-metrics.md)
- [Unified Limits](../advanced-usage/unified-limits.md)
