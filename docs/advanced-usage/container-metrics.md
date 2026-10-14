---
title: "Container Metrics (Cgroups)"
description: "Get container resource limits and usage when running in Docker/Kubernetes"
weight: 31
---

# Container Metrics (Cgroups)

Get container resource limits and usage when running in Docker/Kubernetes.

## Overview

The Container Metrics API provides access to cgroup (control group) limits and usage, allowing your application to be aware of container resource constraints.

```php
use Cbox\SystemMetrics\SystemMetrics;

$container = SystemMetrics::container()->getValue();
```

## Checking Container Environment

```php
if ($container->cgroupVersion !== CgroupVersion::NONE) {
    echo "Running in containerized environment\n";
    echo "Cgroup version: {$container->cgroupVersion->value}\n";
}
```

## CPU Limits and Usage

```php
if ($container->hasCpuLimit()) {
    echo "CPU quota: {$container->cpuQuota} cores\n";
    echo "CPU usage: {$container->cpuUsageCores} cores\n";
    echo "CPU utilization: " . round($container->cpuUtilizationPercentage(), 1) . "%\n";
    echo "Available CPU: {$container->availableCpuCores()} cores\n";
}

// Check for CPU throttling
if ($container->isCpuThrottled()) {
    echo "⚠️ CPU is being throttled (count: {$container->cpuThrottledCount})\n";
}
```

## Memory Limits and Usage

```php
if ($container->hasMemoryLimit()) {
    $limitGB = round($container->memoryLimitBytes / 1024**3, 2);
    $usageGB = round($container->memoryUsageBytes / 1024**3, 2);

    echo "Memory limit: {$limitGB} GB\n";
    echo "Memory usage: {$usageGB} GB\n";
    echo "Memory utilization: " . round($container->memoryUtilizationPercentage(), 1) . "%\n";
    echo "Available memory: " . round($container->availableMemoryBytes() / 1024**3, 2) . " GB\n";
}

// Check for OOM kills
if ($container->hasOomKills()) {
    echo "🚨 OOM kills detected: {$container->oomKillCount}\n";
}
```

`oomKillCount` is the cgroup's cumulative OOM kill counter: `oom_kill` in `memory.events` on cgroup v2, and in `memory.oom_control` on cgroup v1 (Linux 4.13+). It is `null` where the kernel does not report it.

## Working Set (Page-Cache-Adjusted Usage)

`memoryUsageBytes` is the cgroup's raw usage counter (`memory.current` on cgroup v2, `memory.usage_in_bytes` on cgroup v1). It includes page cache. A container that writes files will see this number climb toward its limit even though the kernel reclaims that cache under pressure, long before it OOM-kills anything.

`memoryWorkingSetBytes` is the working set as cAdvisor and Kubernetes define it: usage minus the inactive file cache from `memory.stat` (`inactive_file` on cgroup v2, `total_inactive_file` on cgroup v1), floored at zero. It is the figure `kubectl top` and kubelet eviction use, and it is the better input for "how close am I to the limit?".

```php
if ($container->hasMemoryLimit() && $container->memoryWorkingSetBytes !== null) {
    echo "Working set: " . round($container->memoryWorkingSetBytes / 1024**3, 2) . " GB\n";
    echo "Utilization: " . round($container->memoryWorkingSetUtilizationPercentage(), 1) . "%\n";
    echo "Available: " . round($container->availableMemoryBytesFromWorkingSet() / 1024**3, 2) . " GB\n";
}
```

`memoryWorkingSetBytes` is `null` when the usage or `memory.stat` cannot be read, or `memory.stat` has no inactive file line; the two helpers return `null` in that case too, and when there is no memory limit. The raw `memoryUsageBytes`, `memoryUtilizationPercentage()` and `availableMemoryBytes()` keep their meaning.

## Complete Example

```php
use Cbox\SystemMetrics\SystemMetrics;

$container = SystemMetrics::container()->getValue();

echo "=== CONTAINER INFO ===\n";
echo "Cgroup Version: {$container->cgroupVersion->value}\n\n";

if ($container->hasCpuLimit()) {
    echo "=== CPU ===\n";
    echo "Quota: {$container->cpuQuota} cores\n";
    echo "Usage: {$container->cpuUsageCores} cores\n";
    echo "Utilization: " . round($container->cpuUtilizationPercentage() ?? 0, 1) . "%\n";
    echo "Throttled: " . ($container->isCpuThrottled() ? 'YES' : 'no') . "\n\n";
}

if ($container->hasMemoryLimit()) {
    echo "=== MEMORY ===\n";
    echo "Limit: " . round($container->memoryLimitBytes / 1024**3, 2) . " GB\n";
    echo "Usage: " . round($container->memoryUsageBytes / 1024**3, 2) . " GB\n";
    echo "Utilization: " . round($container->memoryUtilizationPercentage() ?? 0, 1) . "%\n";
    echo "OOM Kills: " . ($container->hasOomKills() ? "YES ({$container->oomKillCount})" : 'no') . "\n";
}
```

## Use Cases

### Auto-Scaling Based on Container Limits

```php
$container = SystemMetrics::container()->getValue();

if ($container->hasMemoryLimit()) {
    $memUtil = $container->memoryUtilizationPercentage();

    if ($memUtil > 80) {
        echo "⚠️ High memory utilization: " . round($memUtil, 1) . "%\n";
        // Scale up or reduce workload
    }
}
```

### Preventing OOM Kills

```php
$container = SystemMetrics::container()->getValue();

if ($container->hasMemoryLimit()) {
    $availableGB = $container->availableMemoryBytes() / 1024**3;

    if ($availableGB < 1.0) {
        echo "⚠️ Low memory: " . round($availableGB, 2) . " GB available\n";
        // Reduce cache size, trigger garbage collection, etc.
    }
}
```

### CPU Throttling Detection

```php
$container = SystemMetrics::container()->getValue();

if ($container->isCpuThrottled()) {
    echo "⚠️ CPU throttling detected\n";
    echo "Throttle count: {$container->cpuThrottledCount}\n";
    // Consider scaling up CPU quota
}
```

## Platform Support

- **Linux**: Full support for cgroup v1 and v2
- **macOS**: Returns `CgroupVersion::NONE` (no cgroup support)
- **Containers**: Works in Docker, Podman, Kubernetes, LXC

## Related Documentation

- [Unified Limits API](unified-limits.md) - Environment-aware resource limits
- [Environment Detection](../basic-usage/environment-detection.md) - Container detection
