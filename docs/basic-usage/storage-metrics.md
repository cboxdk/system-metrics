# Storage Metrics

Get filesystem usage and disk I/O statistics.

## Overview

```php
use PHPeek\SystemMetrics\SystemMetrics;

$storage = SystemMetrics::storage()->getValue();
```

## Mount Points (Filesystem Usage)

```php
foreach ($storage->mountPoints as $mount) {
    echo "Device: {$mount->device}\n";
    echo "Mount Point: {$mount->mountPoint}\n";
    echo "Filesystem: {$mount->fsType->value}\n";
    echo "Total: " . round($mount->totalBytes / 1024**3, 2) . " GB\n";
    echo "Used: " . round($mount->usedBytes / 1024**3, 2) . " GB\n";
    echo "Available: " . round($mount->availableBytes / 1024**3, 2) . " GB\n";
    echo "Usage: " . round($mount->usedPercentage(), 1) . "%\n";
    echo "Inodes: {$mount->usedInodes} / {$mount->totalInodes}\n\n";
}
```

## Disk I/O Statistics

```php
foreach ($storage->diskIO as $disk) {
    echo "Device: {$disk->device}\n";
    echo "Reads: {$disk->readsCompleted} ops, " . round($disk->readBytes / 1024**2, 2) . " MB\n";
    echo "Writes: {$disk->writesCompleted} ops, " . round($disk->writeBytes / 1024**2, 2) . " MB\n";
    echo "I/O Time: {$disk->ioTimeMs} ms\n";
    echo "Total Operations: {$disk->totalOperations()}\n";
    echo "Total Bytes: " . round($disk->totalBytes() / 1024**3, 2) . " GB\n\n";
}
```

**Note:** Disk I/O counters are cumulative since boot. To get I/O rates (MB/s, IOPS), take two snapshots and calculate the delta.

## Aggregate Statistics

```php
echo "Total Storage: " . round($storage->totalBytes() / 1024**3, 2) . " GB\n";
echo "Total Used: " . round($storage->usedBytes() / 1024**3, 2) . " GB\n";
echo "Overall Usage: " . round($storage->usedPercentage(), 1) . "%\n";
```

## Use Cases

### Disk Space Monitoring

```php
$storage = SystemMetrics::storage()->getValue();

foreach ($storage->mountPoints as $mount) {
    if ($mount->usedPercentage() > 90) {
        echo "⚠️ Low disk space on {$mount->mountPoint}: " . round($mount->usedPercentage(), 1) . "%\n";
    }
}
```

## Related Documentation

- [System Overview](system-overview.md)
