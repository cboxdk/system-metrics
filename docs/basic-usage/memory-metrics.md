# Memory Metrics

Get physical memory, swap, and buffer/cache information from the system.

## Overview

Memory metrics provide instant snapshots of RAM and swap usage. All values are in bytes.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$mem = SystemMetrics::memory()->getValue();
```

## Physical Memory

```php
$mem = SystemMetrics::memory()->getValue();

// Raw bytes
echo "Total RAM: {$mem->totalBytes} bytes\n";
echo "Free: {$mem->freeBytes} bytes\n";
echo "Available: {$mem->availableBytes} bytes\n";
echo "Used: {$mem->usedBytes} bytes\n";

// Convert to GB
$totalGB = round($mem->totalBytes / 1024**3, 2);
$usedGB = round($mem->usedBytes / 1024**3, 2);
$availableGB = round($mem->availableBytes / 1024**3, 2);

echo "Total: {$totalGB} GB\n";
echo "Used: {$usedGB} GB\n";
echo "Available: {$availableGB} GB\n";

// Percentage helpers
echo "Usage: " . round($mem->usedPercentage(), 1) . "%\n";
echo "Available: " . round($mem->availablePercentage(), 1) . "%\n";
```

### Free vs Available

- **Free**: Completely unused memory (not allocated to anything)
- **Available**: Memory that can be used immediately (includes reclaimable cache)

**Use `availableBytes` for resource decisions**, not `freeBytes`. Available memory includes:
- Free memory
- Reclaimable page cache
- Reclaimable buffers

## Buffers and Cache (Linux Only)

Linux uses "free" memory for caching to improve performance. This cache is automatically reclaimed when applications need memory.

```php
$mem = SystemMetrics::memory()->getValue();

// Buffers (filesystem metadata)
echo "Buffers: " . round($mem->buffersBytes / 1024**2, 2) . " MB\n";

// Cache (file content)
echo "Cache: " . round($mem->cachedBytes / 1024**3, 2) . " GB\n";
```

**On macOS:** `buffersBytes` and `cachedBytes` are always 0.

## Swap Memory

```php
$mem = SystemMetrics::memory()->getValue();

// Swap totals
echo "Swap Total: " . round($mem->swapTotalBytes / 1024**3, 2) . " GB\n";
echo "Swap Free: " . round($mem->swapFreeBytes / 1024**3, 2) . " GB\n";
echo "Swap Used: " . round($mem->swapUsedBytes / 1024**3, 2) . " GB\n";

// Swap usage percentage
echo "Swap Usage: " . round($mem->swapUsedPercentage(), 1) . "%\n";
```

**Swap behavior:**
- **Linux**: Fixed swap partition or file
- **macOS**: Dynamic swap files (created/removed on demand)

## Complete Example

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::memory();

if ($result->isFailure()) {
    echo "Error: " . $result->getError()->getMessage() . "\n";
    exit(1);
}

$mem = $result->getValue();

echo "=== PHYSICAL MEMORY ===\n";
echo "Total: " . round($mem->totalBytes / 1024**3, 2) . " GB\n";
echo "Used: " . round($mem->usedBytes / 1024**3, 2) . " GB (" . round($mem->usedPercentage(), 1) . "%)\n";
echo "Free: " . round($mem->freeBytes / 1024**3, 2) . " GB\n";
echo "Available: " . round($mem->availableBytes / 1024**3, 2) . " GB (" . round($mem->availablePercentage(), 1) . "%)\n";

if ($mem->buffersBytes > 0 || $mem->cachedBytes > 0) {
    echo "\n=== BUFFERS & CACHE (Linux) ===\n";
    echo "Buffers: " . round($mem->buffersBytes / 1024**2, 2) . " MB\n";
    echo "Cached: " . round($mem->cachedBytes / 1024**3, 2) . " GB\n";
}

echo "\n=== SWAP MEMORY ===\n";
echo "Total: " . round($mem->swapTotalBytes / 1024**3, 2) . " GB\n";
echo "Used: " . round($mem->swapUsedBytes / 1024**3, 2) . " GB (" . round($mem->swapUsedPercentage(), 1) . "%)\n";
echo "Free: " . round($mem->swapFreeBytes / 1024**3, 2) . " GB\n";

echo "\n=== TIMESTAMP ===\n";
echo "Measured at: {$mem->timestamp->format('Y-m-d H:i:s')}\n";
```

**Output example:**
```
=== PHYSICAL MEMORY ===
Total: 32.00 GB
Used: 12.45 GB (38.9%)
Free: 2.30 GB
Available: 19.55 GB (61.1%)

=== BUFFERS & CACHE (Linux) ===
Buffers: 345.67 MB
Cached: 17.25 GB

=== SWAP MEMORY ===
Total: 8.00 GB
Used: 0.50 GB (6.3%)
Free: 7.50 GB

=== TIMESTAMP ===
Measured at: 2024-01-15 14:30:45
```

## Platform Differences

### Linux
- Full memory metrics from `/proc/meminfo`
- Buffers and cache reported separately
- Fixed swap partition or swap file

### macOS
- Memory from `vm_stat` and `sysctl hw.memsize`
- No buffers/cache breakdown (always 0)
- Dynamic swap (values are estimates)

See [Platform Support](../platform-support/comparison.md) for details.

## Use Cases

### Memory Pressure Detection

```php
$mem = SystemMetrics::memory()->getValue();

if ($mem->availablePercentage() < 10) {
    echo "⚠️ Low memory: Only " . round($mem->availablePercentage(), 1) . "% available\n";
    // Trigger cleanup, scale up, or alert
}
```

### Swap Monitoring

```php
$mem = SystemMetrics::memory()->getValue();

if ($mem->swapUsedPercentage() > 50) {
    echo "⚠️ Heavy swap usage: " . round($mem->swapUsedPercentage(), 1) . "%\n";
    // Performance may be degraded
}
```

### Cache Size Decisions

```php
$mem = SystemMetrics->memory()->getValue();

// Use 10% of available memory for cache
$cacheSize = (int) ($mem->availableBytes * 0.10);
echo "Setting cache to " . round($cacheSize / 1024**2, 2) . " MB\n";
```

### Container vs Host Memory

```php
$env = SystemMetrics::environment()->getValue();

if ($env->containerization->insideContainer) {
    // Use container limits, not host memory
    $container = SystemMetrics::container()->getValue();
    if ($container->hasMemoryLimit()) {
        $maxMemory = $container->memoryLimitBytes;
    }
} else {
    // Use host memory
    $mem = SystemMetrics::memory()->getValue();
    $maxMemory = $mem->totalBytes;
}
```

## Related Documentation

- [Container Metrics](../advanced-usage/container-metrics.md) - Container memory limits
- [Unified Limits API](../advanced-usage/unified-limits.md) - Environment-aware limits
- [Process Metrics](../advanced-usage/process-metrics.md) - Per-process memory usage
