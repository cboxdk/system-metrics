# Load Average

Get system load average without needing delta calculations.

## Overview

Load average represents the number of processes in the run queue (runnable + waiting for CPU). On Linux, it also includes processes in uninterruptible I/O wait.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$load = SystemMetrics::loadAverage()->getValue();
```

## Basic Usage

```php
// Raw load average values
echo "Load (1 min): {$load->oneMinute}\n";
echo "Load (5 min): {$load->fiveMinutes}\n";
echo "Load (15 min): {$load->fifteenMinutes}\n";
```

## Normalized Load (Per-Core Capacity)

```php
$cpu = SystemMetrics::cpu()->getValue();
$normalized = $load->normalized($cpu);

// Normalized values (0.0 to 1.0+ per core)
echo "Normalized (1 min): " . round($normalized->oneMinute, 3) . "\n";

// Percentage helpers (0% to 100%+)
echo "CPU Capacity (1 min): " . round($normalized->oneMinutePercentage(), 1) . "%\n";
echo "CPU Capacity (5 min): " . round($normalized->fiveMinutesPercentage(), 1) . "%\n";
echo "CPU Capacity (15 min): " . round($normalized->fifteenMinutesPercentage(), 1) . "%\n";
echo "Core count: {$normalized->coreCount}\n";
```

## Interpretation

- **< 1.0 (< 100%)**: System has spare capacity
- **= 1.0 (= 100%)**: System is at full capacity
- **> 1.0 (> 100%)**: System is overloaded, processes are queuing

**Note:** Load average ≠ CPU usage percentage. A system with high I/O wait can have high load but low CPU usage.

## Use Cases

### Load Monitoring

```php
$load = SystemMetrics::loadAverage()->getValue();
$cpu = SystemMetrics::cpu()->getValue();
$normalized = $load->normalized($cpu);

if ($normalized->oneMinutePercentage() > 80) {
    echo "⚠️ High load: " . round($normalized->oneMinutePercentage(), 1) . "%\n";
}
```

## Related Documentation

- [CPU Metrics](cpu-metrics.md)
- [System Overview](system-overview.md)
