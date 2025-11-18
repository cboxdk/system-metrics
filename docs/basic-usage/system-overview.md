# System Overview

Get a complete snapshot of all system metrics at once.

## Overview

The `SystemMetrics::overview()` method returns a single snapshot containing all available system metrics. This is more efficient than calling each metric individually when you need multiple values.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$overview = SystemMetrics::overview()->getValue();
```

## Available Metrics

```php
// Environment information
$overview->environment  // EnvironmentSnapshot

// CPU metrics
$overview->cpu          // CpuSnapshot

// Memory metrics
$overview->memory       // MemorySnapshot

// Load average
$overview->loadAverage  // LoadAverageSnapshot

// System uptime
$overview->uptime       // UptimeSnapshot

// Storage metrics (may be null if unavailable)
$overview->storage      // StorageSnapshot|null

// Network metrics (may be null if unavailable)
$overview->network      // NetworkSnapshot|null
```

## Complete Example

```php
use PHPeek\SystemMetrics\SystemMetrics;

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
        'cpu_cores' => $overview->cpu->coreCount(),
        'memory_usage_percent' => round($overview->memory->usedPercentage(), 2),
        'load_average_1min' => $overview->loadAverage->oneMinute,
        'uptime_seconds' => $overview->uptime->totalSeconds,
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

## Related Documentation

- [Environment Detection](environment-detection.md)
- [CPU Metrics](cpu-metrics.md)
- [Memory Metrics](memory-metrics.md)
- [Load Average](load-average.md)
- [System Uptime](uptime.md)
- [Storage Metrics](storage-metrics.md)
- [Network Metrics](network-metrics.md)
