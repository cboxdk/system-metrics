# CPU Usage Calculation

Deep dive into calculating CPU usage percentages from raw time counters.

## Understanding Raw Counters

CPU metrics provide monotonically increasing time counters (ticks/jiffies) that represent cumulative time since boot. To calculate usage percentage, you need TWO snapshots and calculate the delta.

## The Delta Formula

```
usage_percent = (busy_delta / total_delta) * 100

where:
  busy_delta = (busy₂ - busy₁)
  total_delta = (total₂ - total₁)
```

## Method 1: Convenience Method

The blocking convenience method handles everything for you:

```php
use PHPeek\SystemMetrics\SystemMetrics;

// Blocks for 1 second, takes two snapshots, calculates delta
$delta = SystemMetrics::cpuUsage(1.0)->getValue();

echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";
echo "User: " . round($delta->userPercentage(), 1) . "%\n";
echo "System: " . round($delta->systemPercentage(), 1) . "%\n";
echo "Idle: " . round($delta->idlePercentage(), 1) . "%\n";
```

## Method 2: Manual Calculation

For non-blocking scenarios, manually take snapshots and calculate delta:

```php
use PHPeek\SystemMetrics\SystemMetrics;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;

// Take first snapshot
$snap1 = SystemMetrics::cpu()->getValue();

// Your code does work here (non-blocking)
doWork();
sleep(2);

// Take second snapshot
$snap2 = SystemMetrics::cpu()->getValue();

// Calculate delta
$delta = CpuSnapshot::calculateDelta($snap1, $snap2);
echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";
```

## Understanding CPU Percentages

### Overall Usage (Can Exceed 100%)

```php
$usage = $delta->usagePercentage();
// On 4-core system: 200% means 2 cores fully utilized
```

### Normalized Usage (0-100%)

```php
$normalized = $delta->normalizedUsagePercentage();
// Average usage per core: 200% / 4 cores = 50%
```

### Per-State Breakdown

```php
echo "User mode: " . round($delta->userPercentage(), 1) . "%\n";
echo "System mode: " . round($delta->systemPercentage(), 1) . "%\n";
echo "I/O wait: " . round($delta->iowaitPercentage(), 1) . "%\n";
echo "Idle: " . round($delta->idlePercentage(), 1) . "%\n";
```

## Per-Core Analysis

```php
// Find busiest core
if ($busiest = $delta->busiestCore()) {
    echo "Busiest: Core #{$busiest->coreIndex} at " .
         round($busiest->usagePercentage(), 1) . "%\n";
}

// Find idlest core
if ($idlest = $delta->idlestCore()) {
    echo "Idlest: Core #{$idlest->coreIndex} at " .
         round($idlest->usagePercentage(), 1) . "%\n";
}

// Analyze all cores
foreach ($delta->perCore as $core) {
    echo "Core {$core->coreIndex}: " .
         round($core->usagePercentage(), 1) . "%\n";
}
```

## Complete Example

```php
use PHPeek\SystemMetrics\SystemMetrics;

// Get CPU usage over 1 second
$delta = SystemMetrics::cpuUsage(1.0)->getValue();

echo "=== CPU USAGE ANALYSIS ===\n";
echo "Sample interval: {$delta->intervalSeconds} seconds\n";
echo "Cores: {$delta->coreCount()}\n\n";

echo "=== OVERALL USAGE ===\n";
echo "Total: " . round($delta->usagePercentage(), 1) . "% (all cores)\n";
echo "Normalized: " . round($delta->normalizedUsagePercentage(), 1) . "% (per core avg)\n\n";

echo "=== BREAKDOWN ===\n";
echo "User: " . round($delta->userPercentage(), 1) . "%\n";
echo "System: " . round($delta->systemPercentage(), 1) . "%\n";
echo "Idle: " . round($delta->idlePercentage(), 1) . "%\n";
echo "I/O Wait: " . round($delta->iowaitPercentage(), 1) . "%\n\n";

echo "=== PER-CORE ===\n";
foreach ($delta->perCore as $core) {
    $usage = round($core->usagePercentage(), 1);
    echo "Core {$core->coreIndex}: {$usage}%\n";
}

if ($busiest = $delta->busiestCore()) {
    echo "\nBusiest: Core #{$busiest->coreIndex} at " .
         round($busiest->usagePercentage(), 1) . "%\n";
}
```

## Best Practices

### Sampling Interval

- **Short intervals (< 0.5s)**: More noise, less accurate
- **Medium intervals (1-5s)**: Good balance for most use cases
- **Long intervals (> 10s)**: Smooth out spikes, good for trends

```php
// Quick check (may be noisy)
$delta = SystemMetrics::cpuUsage(0.5)->getValue();

// Recommended for monitoring
$delta = SystemMetrics::cpuUsage(1.0)->getValue();

// Smooth long-term trend
$delta = SystemMetrics::cpuUsage(10.0)->getValue();
```

### Continuous Monitoring

```php
$snap1 = SystemMetrics::cpu()->getValue();

while (true) {
    sleep(5);
    $snap2 = SystemMetrics::cpu()->getValue();
    $delta = CpuSnapshot::calculateDelta($snap1, $snap2);

    echo "CPU: " . round($delta->usagePercentage(), 1) . "%\n";

    // Move forward for next iteration
    $snap1 = $snap2;
}
```

## Related Documentation

- [CPU Metrics](../basic-usage/cpu-metrics.md) - Raw CPU counters
- [Load Average](../basic-usage/load-average.md) - System load metrics
- [Process Metrics](process-metrics.md) - Per-process CPU tracking
