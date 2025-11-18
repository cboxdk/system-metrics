# Process Metrics

Monitor resource usage for individual processes or process groups.

## Overview

Process metrics allow you to track CPU, memory, threads, and file descriptors for specific processes, including spawned child processes.

```php
use PHPeek\SystemMetrics\ProcessMetrics;
```

## One-Time Snapshot

Get a single snapshot of a process:

```php
$snapshot = ProcessMetrics::snapshot($pid)->getValue();

echo "PID: {$snapshot->pid}\n";
echo "Parent PID: {$snapshot->parentPid}\n";
echo "Memory RSS: " . round($snapshot->resources->memoryRssBytes / 1024**2, 2) . " MB\n";
echo "Memory VMS: " . round($snapshot->resources->memoryVmsBytes / 1024**2, 2) . " MB\n";
echo "CPU User: {$snapshot->resources->cpuTimes->user} ticks\n";
echo "CPU System: {$snapshot->resources->cpuTimes->system} ticks\n";
echo "Threads: {$snapshot->resources->threadCount}\n";
echo "Open Files: {$snapshot->resources->openFileDescriptors}\n";
```

## Process Tracking

Track a process over time to collect statistics:

```php
// Start tracking
$trackerId = ProcessMetrics::start($pid)->getValue();

// Optionally take manual samples
ProcessMetrics::sample($trackerId);
sleep(1);
ProcessMetrics::sample($trackerId);

// Stop and get statistics
$stats = ProcessMetrics::stop($trackerId)->getValue();

// Current, Peak, and Average values - Memory
echo "Current Memory: " . round($stats->current->memoryRssBytes / 1024**2, 2) . " MB\n";
echo "Peak Memory: " . round($stats->peak->memoryRssBytes / 1024**2, 2) . " MB\n";
echo "Average Memory: " . round($stats->average->memoryRssBytes / 1024**2, 2) . " MB\n";

// CPU usage percentage from delta
echo "CPU Usage: " . round($stats->delta->cpuUsagePercentage(), 2) . "%\n";

echo "Samples: {$stats->sampleCount}\n";
```

## Process Group Monitoring

Monitor a process and all its children:

```php
// Get process group snapshot
$group = ProcessMetrics::group($pid)->getValue();

echo "Total processes: {$group->totalProcessCount()}\n";
echo "Total memory: " . round($group->aggregateMemoryRss() / 1024**2, 2) . " MB\n";
echo "Total CPU user: {$group->aggregateCpuUser()} ticks\n";
echo "Total CPU system: {$group->aggregateCpuSystem()} ticks\n";
echo "Total threads: {$group->aggregateThreads()}\n";

// Track group over time
$trackerId = ProcessMetrics::start($pid, includeChildren: true)->getValue();
// ... work happens ...
$stats = ProcessMetrics::stop($trackerId)->getValue();
```

## Use Cases

### Queue Worker Monitoring

```php
// Start tracking when job starts
$trackerId = ProcessMetrics::start(getmypid())->getValue();

// Do work
processJob();

// Get statistics when job completes
$stats = ProcessMetrics::stop($trackerId)->getValue();

echo "Job completed:\n";
echo "Peak memory: " . round($stats->peak->memoryRssBytes / 1024**2, 2) . " MB\n";
echo "Average memory: " . round($stats->average->memoryRssBytes / 1024**2, 2) . " MB\n";
echo "CPU usage: " . round($stats->delta->cpuUsagePercentage(), 2) . "%\n";
```

### Spawned Process Monitoring

```php
// Launch external process
$process = proc_open('ffmpeg -i input.mp4 output.mp4', $descriptors, $pipes);
$status = proc_get_status($process);
$pid = $status['pid'];

// Track the spawned process
$trackerId = ProcessMetrics::start($pid)->getValue();

// Wait for completion
proc_close($process);

// Get statistics
$stats = ProcessMetrics::stop($trackerId)->getValue();
echo "FFmpeg peak memory: " . round($stats->peak->memoryRssBytes / 1024**2, 2) . " MB\n";
echo "FFmpeg CPU usage: " . round($stats->delta->cpuUsagePercentage(), 2) . "%\n";
```

### Memory Leak Detection

```php
$trackerId = ProcessMetrics::start(getmypid())->getValue();

for ($i = 0; $i < 1000; $i++) {
    doWork();
    if ($i % 100 === 0) {
        ProcessMetrics::sample($trackerId);
    }
}

$stats = ProcessMetrics::stop($trackerId)->getValue();

if ($stats->peak->memoryRssBytes > $stats->average->memoryRssBytes * 1.5) {
    echo "⚠️ Possible memory leak detected\n";
    echo "Peak memory: " . round($stats->peak->memoryRssBytes / 1024**2, 2) . " MB\n";
    echo "Average memory: " . round($stats->average->memoryRssBytes / 1024**2, 2) . " MB\n";
}

// Check CPU usage
echo "CPU usage: " . round($stats->delta->cpuUsagePercentage(), 2) . "%\n";
echo "Memory delta: " . round($stats->delta->memoryDeltaBytes / 1024**2, 2) . " MB\n";
```

## Related Documentation

- [System Overview](../basic-usage/system-overview.md)
- [CPU Metrics](../basic-usage/cpu-metrics.md)
- [Memory Metrics](../basic-usage/memory-metrics.md)
