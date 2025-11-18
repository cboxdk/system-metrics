# CPU Metrics

Get raw CPU time counters and per-core metrics from the system.

## Overview

CPU metrics provide raw time counters (measured in ticks/jiffies) that increase monotonically since boot. These counters track how much time the CPU spent in different states.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$cpu = SystemMetrics::cpu()->getValue();
```

## Understanding CPU Time Counters

**Important:** This library provides RAW COUNTERS, not percentages. To calculate CPU usage percentage, you need to take TWO snapshots and calculate the delta.

### Time Units (Ticks)

- Measured in "ticks" or "jiffies" (typically 100 Hz = 100 ticks per second)
- On Linux, you can check with: `getconf CLK_TCK` (usually returns 100)
- Counters are cumulative since system boot
- Values are always increasing (monotonic)

### CPU States

- **user**: Time spent running user-space processes
- **system**: Time spent running kernel code
- **idle**: Time CPU was idle (not doing work)
- **iowait**: Time waiting for I/O operations (Linux only)
- **irq**: Time servicing hardware interrupts (Linux only)
- **softirq**: Time servicing software interrupts (Linux only)
- **steal**: Time stolen by hypervisor for other VMs (Linux only)
- **guest**: Time spent running virtual CPUs (Linux only)

## System-Wide CPU Times

```php
$cpu = SystemMetrics::cpu()->getValue();

// Individual time counters
echo "User: {$cpu->total->user} ticks\n";
echo "System: {$cpu->total->system} ticks\n";
echo "Idle: {$cpu->total->idle} ticks\n";
echo "I/O Wait: {$cpu->total->iowait} ticks\n";  // Linux only, 0 on macOS
echo "IRQ: {$cpu->total->irq} ticks\n";          // Linux only
echo "Soft IRQ: {$cpu->total->softirq} ticks\n"; // Linux only
echo "Steal: {$cpu->total->steal} ticks\n";      // Linux only
echo "Guest: {$cpu->total->guest} ticks\n";      // Linux only

// Calculated totals
echo "Total Time: {$cpu->total->total()} ticks\n";     // Sum of all fields
echo "Busy Time: {$cpu->total->busy()} ticks\n";       // total - idle - iowait
```

## Per-Core Metrics

```php
$cpu = SystemMetrics::cpu()->getValue();

echo "CPU Cores: {$cpu->coreCount()}\n\n";

foreach ($cpu->perCore as $core) {
    echo "Core {$core->coreIndex}:\n";
    echo "  User: {$core->times->user} ticks\n";
    echo "  System: {$core->times->system} ticks\n";
    echo "  Idle: {$core->times->idle} ticks\n";
    echo "  Total: {$core->times->total()} ticks\n";
    echo "  Busy: {$core->times->busy()} ticks\n\n";
}
```

## Calculating CPU Usage Percentage

Since raw counters are cumulative, you need TWO snapshots to calculate usage:

### Method 1: Convenience Method (Blocking)

```php
use PHPeek\SystemMetrics\SystemMetrics;

// Blocks for the specified interval
$delta = SystemMetrics::cpuUsage(1.0)->getValue(); // Wait 1 second

// Overall CPU usage
echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";
echo "Normalized (per-core): " . round($delta->normalizedUsagePercentage(), 1) . "%\n";

// By CPU state
echo "User: " . round($delta->userPercentage(), 1) . "%\n";
echo "System: " . round($delta->systemPercentage(), 1) . "%\n";
echo "Idle: " . round($delta->idlePercentage(), 1) . "%\n";
echo "I/O Wait: " . round($delta->iowaitPercentage(), 1) . "%\n";

// Per-core analysis
if ($busiest = $delta->busiestCore()) {
    echo "Busiest Core: #{$busiest->coreIndex} at " . round($busiest->usagePercentage(), 1) . "%\n";
}
if ($idlest = $delta->idlestCore()) {
    echo "Idlest Core: #{$idlest->coreIndex} at " . round($idlest->usagePercentage(), 1) . "%\n";
}
```

### Method 2: Manual (Non-Blocking)

```php
use PHPeek\SystemMetrics\SystemMetrics;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;

// Take first snapshot
$snap1 = SystemMetrics::cpu()->getValue();

// Your application does work here...
sleep(2);

// Take second snapshot
$snap2 = SystemMetrics::cpu()->getValue();

// Calculate delta
$delta = CpuSnapshot::calculateDelta($snap1, $snap2);
echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";
```

### Understanding CPU Percentage

**Overall Usage:**
- `usagePercentage()`: Total CPU usage across all cores (can exceed 100%)
- Formula: `(busy_delta / total_delta) * 100`
- Example: On 4-core system, 200% means 2 cores fully utilized

**Normalized Usage:**
- `normalizedUsagePercentage()`: Average usage per core (0-100%)
- Formula: `usagePercentage() / coreCount`
- Example: 200% on 4 cores = 50% normalized

## Complete Example

```php
use PHPeek\SystemMetrics\SystemMetrics;

// Get raw CPU counters
$cpu = SystemMetrics::cpu()->getValue();

echo "=== CPU INFORMATION ===\n";
echo "Cores: {$cpu->coreCount()}\n";
echo "Timestamp: {$cpu->timestamp->format('Y-m-d H:i:s')}\n\n";

// System-wide counters
echo "=== SYSTEM-WIDE TIMES (ticks) ===\n";
echo "User: {$cpu->total->user}\n";
echo "System: {$cpu->total->system}\n";
echo "Idle: {$cpu->total->idle}\n";
echo "I/O Wait: {$cpu->total->iowait}\n";
echo "Total: {$cpu->total->total()}\n";
echo "Busy: {$cpu->total->busy()}\n\n";

// Per-core breakdown
echo "=== PER-CORE TIMES ===\n";
foreach ($cpu->perCore as $core) {
    $busyPct = ($core->times->busy() / $core->times->total()) * 100;
    echo "Core {$core->coreIndex}: {$core->times->user}u {$core->times->system}s " .
         "{$core->times->idle}i (~" . round($busyPct, 1) . "% busy since boot)\n";
}
echo "\n";

// Calculate CPU usage over 1 second
echo "=== CPU USAGE (1 second sample) ===\n";
$delta = SystemMetrics::cpuUsage(1.0)->getValue();
echo "Overall Usage: " . round($delta->usagePercentage(), 1) . "%\n";
echo "Normalized: " . round($delta->normalizedUsagePercentage(), 1) . "%\n";
echo "User: " . round($delta->userPercentage(), 1) . "%\n";
echo "System: " . round($delta->systemPercentage(), 1) . "%\n";
```

## Platform Differences

### Linux
- Full 8-field CPU times (user, system, idle, iowait, irq, softirq, steal, guest)
- Per-core metrics available
- Reads from `/proc/stat`

### macOS
- Limited fields (user, system, idle only on older systems)
- Modern macOS (Apple Silicon) may return zero values
- Uses `sysctl kern.cp_time` (deprecated on new systems)

See [Platform Support](../platform-support/comparison.md) for details.

## Use Cases

### CPU Usage Monitoring

```php
while (true) {
    $delta = SystemMetrics::cpuUsage(1.0)->getValue();
    $usage = $delta->normalizedUsagePercentage();
    
    if ($usage > 80) {
        echo "⚠️ High CPU usage: " . round($usage, 1) . "%\n";
    }
    
    sleep(5);
}
```

### Load Balancing

```php
$delta = SystemMetrics::cpuUsage(0.5)->getValue();

// Find least busy core
if ($idlest = $delta->idlestCore()) {
    echo "Assign task to core {$idlest->coreIndex}\n";
}
```

### Performance Profiling

```php
$snap1 = SystemMetrics::cpu()->getValue();

// Run expensive operation
expensiveOperation();

$snap2 = SystemMetrics::cpu()->getValue();
$delta = CpuSnapshot::calculateDelta($snap1, $snap2);

echo "Operation used " . round($delta->usagePercentage(), 1) . "% CPU\n";
```

## Related Documentation

- [Load Average](load-average.md) - System load metrics
- [CPU Usage Calculation](../advanced-usage/cpu-usage-calculation.md) - Deep dive into delta calculations
- [Process Metrics](../advanced-usage/process-metrics.md) - Per-process CPU tracking
- [Container Metrics](../advanced-usage/container-metrics.md) - Container CPU limits
