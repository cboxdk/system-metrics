# System Uptime

Track how long the system has been running since last boot.

## Overview

```php
use PHPeek\SystemMetrics\SystemMetrics;

$uptime = SystemMetrics::uptime()->getValue();
```

## Basic Usage

```php
// Boot time
echo "Boot time: {$uptime->bootTime->format('Y-m-d H:i:s')}\n";
echo "Current time: {$uptime->timestamp->format('Y-m-d H:i:s')}\n";

// Total uptime in seconds
echo "Total seconds: {$uptime->totalSeconds}\n";

// Human-readable format
echo "Uptime: {$uptime->humanReadable()}\n";
// Output: "5 days, 3 hours, 42 minutes"
```

## Component Breakdown

```php
// Individual components
echo "Days: {$uptime->days()}\n";
echo "Hours: {$uptime->hours()}\n";           // Remaining hours after days
echo "Minutes: {$uptime->minutes()}\n";       // Remaining minutes after hours

// Decimal representations
echo "Total hours: " . round($uptime->totalHours(), 2) . "\n";
echo "Total minutes: " . round($uptime->totalMinutes(), 2) . "\n";
```

## Use Cases

### Uptime Monitoring

```php
$uptime = SystemMetrics::uptime()->getValue();

if ($uptime->totalHours() < 1) {
    echo "⚠️ System recently restarted\n";
}
```

### SLA Calculations

```php
$uptime = SystemMetrics::uptime()->getValue();
$uptimePercent = ($uptime->totalHours() / 720) * 100; // 30 days
echo "Uptime: " . round($uptimePercent, 2) . "%\n";
```

## Related Documentation

- [System Overview](system-overview.md)
