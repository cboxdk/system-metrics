# Quick Start

Get up and running with PHPeek System Metrics in 30 seconds.

## Installation

```bash
composer require gophpeek/system-metrics
```

## Basic Example

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use PHPeek\SystemMetrics\SystemMetrics;

// Get complete system overview
$result = SystemMetrics::overview();

if ($result->isSuccess()) {
    $overview = $result->getValue();

    // Environment Information
    echo "=== SYSTEM INFO ===\n";
    echo "OS: {$overview->environment->os->name} {$overview->environment->os->version}\n";
    echo "Architecture: {$overview->environment->architecture->kind->value}\n";
    echo "Kernel: {$overview->environment->kernel->release}\n";

    // CPU Metrics
    echo "\n=== CPU ===\n";
    echo "Cores: {$overview->cpu->coreCount()}\n";
    echo "Total CPU time: {$overview->cpu->total->total()} ticks\n";
    echo "Busy time: {$overview->cpu->total->busy()} ticks\n";

    // Memory Metrics
    echo "\n=== MEMORY ===\n";
    $usedGB = round($overview->memory->usedBytes / 1024**3, 2);
    $totalGB = round($overview->memory->totalBytes / 1024**3, 2);
    echo "Used: {$usedGB} GB / {$totalGB} GB\n";
    echo "Usage: " . round($overview->memory->usedPercentage(), 1) . "%\n";
    echo "Available: " . round($overview->memory->availablePercentage(), 1) . "%\n";

    // Load Average
    echo "\n=== LOAD AVERAGE ===\n";
    echo "1 min: {$overview->loadAverage->oneMinute}\n";
    echo "5 min: {$overview->loadAverage->fiveMinutes}\n";
    echo "15 min: {$overview->loadAverage->fifteenMinutes}\n";

} else {
    echo "Error: " . $result->getError()->getMessage() . "\n";
}
```

**Example output:**
```
=== SYSTEM INFO ===
OS: Ubuntu 22.04
Architecture: x86_64
Kernel: 5.15.0-91-generic

=== CPU ===
Cores: 8
Total CPU time: 1234567890 ticks
Busy time: 456789012 ticks

=== MEMORY ===
Used: 12.45 GB / 32.00 GB
Usage: 38.9%
Available: 61.1%

=== LOAD AVERAGE ===
1 min: 1.25
5 min: 1.45
15 min: 1.67
```

## Individual Metrics

Instead of getting everything at once, you can fetch specific metrics:

### Environment Detection

```php
use PHPeek\SystemMetrics\SystemMetrics;

$env = SystemMetrics::environment()->getValue();

echo "OS: {$env->os->family->value}\n";  // "linux" or "macos"
echo "Virtualization: {$env->virtualization->type->value}\n";
echo "In Container: " . ($env->containerization->insideContainer ? 'yes' : 'no') . "\n";
```

### CPU Metrics

```php
$cpu = SystemMetrics::cpu()->getValue();

echo "CPU Cores: {$cpu->coreCount()}\n";
echo "User time: {$cpu->total->user} ticks\n";
echo "System time: {$cpu->total->system} ticks\n";
```

### Memory Metrics

```php
$mem = SystemMetrics::memory()->getValue();

echo "Total: " . round($mem->totalBytes / 1024**3, 2) . " GB\n";
echo "Used: " . round($mem->usedPercentage(), 1) . "%\n";
echo "Available: " . round($mem->availableBytes / 1024**3, 2) . " GB\n";
```

### Load Average

```php
$load = SystemMetrics::loadAverage()->getValue();

echo "Load (1 min): {$load->oneMinute}\n";
echo "Load (5 min): {$load->fiveMinutes}\n";
echo "Load (15 min): {$load->fifteenMinutes}\n";
```

### Storage Metrics

```php
$storage = SystemMetrics::storage()->getValue();

foreach ($storage->mountPoints as $mount) {
    echo "Mount: {$mount->mountPoint}\n";
    echo "Size: " . round($mount->totalBytes / 1024**3, 2) . " GB\n";
    echo "Used: " . round($mount->usedPercentage(), 1) . "%\n\n";
}
```

### Network Metrics

```php
$network = SystemMetrics::network()->getValue();

foreach ($network->interfaces as $iface) {
    echo "Interface: {$iface->name}\n";
    echo "Sent: " . round($iface->stats->bytesSent / 1024**2, 2) . " MB\n";
    echo "Received: " . round($iface->stats->bytesReceived / 1024**2, 2) . " MB\n\n";
}
```

## CPU Usage Percentage

CPU metrics return raw time counters. To calculate usage percentage, you need two snapshots:

```php
use PHPeek\SystemMetrics\SystemMetrics;

// Convenience method (blocks for the specified interval)
$cpuDelta = SystemMetrics::cpuUsage(1.0)->getValue(); // Wait 1 second

echo "CPU Usage: " . round($cpuDelta->usagePercentage(), 1) . "%\n";
echo "User: " . round($cpuDelta->userPercentage(), 1) . "%\n";
echo "System: " . round($cpuDelta->systemPercentage(), 1) . "%\n";
echo "Idle: " . round($cpuDelta->idlePercentage(), 1) . "%\n";
```

**Alternative non-blocking approach:**

```php
use PHPeek\SystemMetrics\SystemMetrics;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;

// Take first snapshot
$snap1 = SystemMetrics::cpu()->getValue();

// Your code does work here...
sleep(2);

// Take second snapshot
$snap2 = SystemMetrics::cpu()->getValue();

// Calculate delta
$delta = CpuSnapshot::calculateDelta($snap1, $snap2);
echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";
```

## Error Handling

All methods return `Result<T>` objects. Always check for success:

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::memory();

if ($result->isSuccess()) {
    $memory = $result->getValue();
    echo "Memory: " . round($memory->usedPercentage(), 1) . "%\n";
} else {
    $error = $result->getError();
    echo "Error: {$error->getMessage()}\n";
    // Handle the error appropriately
}
```

**Using default values:**

```php
$memory = SystemMetrics::memory()->getValueOr(null);

if ($memory === null) {
    echo "Could not read memory metrics\n";
} else {
    echo "Memory: " . round($memory->usedPercentage(), 1) . "%\n";
}
```

**Functional style:**

```php
SystemMetrics::cpu()
    ->onSuccess(function($cpu) {
        echo "CPU cores: {$cpu->coreCount()}\n";
    })
    ->onFailure(function($error) {
        error_log("Failed to read CPU: " . $error->getMessage());
    });
```

## Container Awareness

The library automatically detects container environments:

```php
use PHPeek\SystemMetrics\SystemMetrics;

$container = SystemMetrics::container()->getValue();

if ($container->hasCpuLimit()) {
    echo "Container CPU limit: {$container->cpuQuota} cores\n";
    echo "CPU usage: " . round($container->cpuUtilizationPercentage(), 1) . "%\n";
}

if ($container->hasMemoryLimit()) {
    $limitGB = round($container->memoryLimitBytes / 1024**3, 2);
    echo "Container memory limit: {$limitGB} GB\n";
    echo "Memory usage: " . round($container->memoryUtilizationPercentage(), 1) . "%\n";
}
```

## Process Monitoring

Track individual processes:

```php
use PHPeek\SystemMetrics\ProcessMetrics;

// Get current process snapshot
$process = ProcessMetrics::snapshot(getmypid())->getValue();

echo "PID: {$process->pid}\n";
echo "Memory: " . round($process->resources->memoryRssBytes / 1024**2, 2) . " MB\n";
echo "CPU time: {$process->resources->cpuTimes->total()} ticks\n";
echo "Threads: {$process->resources->threadCount}\n";
```

## What's Next?

Now that you've seen the basics:

- **[Explore all metrics](basic-usage/system-overview.md)** - Detailed examples for each metric type
- **[Learn error handling](advanced-usage/error-handling.md)** - Master the Result<T> pattern
- **[Container metrics](advanced-usage/container-metrics.md)** - Docker and Kubernetes integration
- **[Process tracking](advanced-usage/process-metrics.md)** - Monitor spawned processes
- **[API reference](api-reference.md)** - Complete method documentation

## Common Patterns

### Health Check Endpoint

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::overview();

if ($result->isFailure()) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Cannot read system metrics']);
    exit;
}

$overview = $result->getValue();
$memoryUsage = $overview->memory->usedPercentage();
$cpuCores = $overview->cpu->coreCount();

$status = $memoryUsage < 90 ? 'healthy' : 'degraded';

header('Content-Type: application/json');
echo json_encode([
    'status' => $status,
    'memory_usage_percent' => round($memoryUsage, 2),
    'cpu_cores' => $cpuCores,
    'uptime_seconds' => $overview->uptime->totalSeconds,
]);
```

### Auto-Scaling Decision

```php
use PHPeek\SystemMetrics\SystemMetrics;

$limits = SystemMetrics::limits()->getValue();

if ($limits->memoryUtilization() > 80) {
    // Scale up - memory pressure detected
    echo "⚠️  Memory usage: " . round($limits->memoryUtilization(), 1) . "%\n";
    echo "Consider scaling up memory resources\n";
}

if ($limits->canScaleMemory(4 * 1024**3)) { // Can we add 4 GB?
    echo "✅ Safe to add 4 GB more memory\n";
} else {
    echo "❌ Cannot add more memory without exceeding limits\n";
}
```

### Queue Worker Concurrency

```php
use PHPeek\SystemMetrics\SystemMetrics;

$cpu = SystemMetrics::cpu()->getValue();
$memory = SystemMetrics::memory()->getValue();

// Calculate safe worker count
$workerCount = min(
    $cpu->coreCount(),  // Don't exceed CPU cores
    (int) floor($memory->availableBytes / (256 * 1024 * 1024))  // 256 MB per worker
);

echo "Safe worker count: {$workerCount}\n";
```
