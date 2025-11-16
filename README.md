# PHPeek System Metrics

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gophpeek/system-metrics.svg?style=flat-square)](https://packagist.org/packages/gophpeek/system-metrics)
[![Tests](https://img.shields.io/github/actions/workflow/status/gophpeek/system-metrics/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/gophpeek/system-metrics/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/gophpeek/system-metrics.svg?style=flat-square)](https://packagist.org/packages/gophpeek/system-metrics)

**Get real-time system metrics from Linux and macOS in pure PHP.** No extensions, no dependencies, just clean type-safe access to CPU, memory, and environment data.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::overview();
$overview = $result->getValue();

echo "OS: {$overview->environment->os->name}\n";
echo "CPU Cores: {$overview->cpu->coreCount()}\n";
echo "Memory: " . round($overview->memory->usedPercentage(), 1) . "%\n";
```

## Quick Start

### Installation

```bash
composer require gophpeek/system-metrics
```

### Requirements

- **PHP 8.3+** (uses modern readonly classes)
- **Linux or macOS** (Windows not supported)
- **System commands** available:
  - Linux: Read access to `/proc`, `/sys` filesystems
  - macOS: `sysctl`, `vm_stat`, `sw_vers` commands (pre-installed)

### 30-Second Example

```php
use PHPeek\SystemMetrics\SystemMetrics;

// Get everything at once
$result = SystemMetrics::overview();

if ($result->isSuccess()) {
    $overview = $result->getValue();

    // Environment
    echo "OS: " . $overview->environment->os->name . "\n";
    echo "Architecture: " . $overview->environment->architecture->kind->value . "\n";

    // CPU (raw time counters in ticks)
    echo "CPU Cores: " . $overview->cpu->coreCount() . "\n";
    echo "CPU User Time: " . $overview->cpu->total->user . " ticks\n";

    // Memory (all values in bytes)
    $usedGB = $overview->memory->usedBytes / 1024**3;
    echo "Memory Used: " . round($usedGB, 2) . " GB\n";
    echo "Memory Usage: " . round($overview->memory->usedPercentage(), 1) . "%\n";
}
```

## Complete Example - All Metrics

Get a complete snapshot of all available system metrics:

```php
use PHPeek\SystemMetrics\SystemMetrics;
use PHPeek\SystemMetrics\ProcessMetrics;

// ============================================
// ENVIRONMENT DETECTION
// ============================================
$env = SystemMetrics::environment()->getValue();

echo "=== ENVIRONMENT ===\n";
echo "OS: {$env->os->name} {$env->os->version}\n";
echo "Architecture: {$env->architecture->kind->value}\n";
echo "Virtualization: {$env->virtualization->type->value}\n";
echo "Container: " . ($env->containerization->insideContainer ? 'yes' : 'no') . "\n";
echo "Cgroup: {$env->cgroup->version->value}\n\n";

// ============================================
// CPU METRICS (Raw Counters)
// ============================================
$cpu = SystemMetrics::cpu()->getValue();

echo "=== CPU ===\n";
echo "Cores: {$cpu->coreCount()}\n";
echo "User Time: {$cpu->total->user} ticks\n";
echo "System Time: {$cpu->total->system} ticks\n";
echo "Idle Time: {$cpu->total->idle} ticks\n";
echo "Total Time: {$cpu->total->total()} ticks\n";
echo "Busy Time: {$cpu->total->busy()} ticks\n\n";

// ============================================
// MEMORY METRICS
// ============================================
$mem = SystemMetrics::memory()->getValue();

echo "=== MEMORY ===\n";
echo "Total: " . round($mem->totalBytes / 1024**3, 2) . " GB\n";
echo "Used: " . round($mem->usedBytes / 1024**3, 2) . " GB (" . round($mem->usedPercentage(), 1) . "%)\n";
echo "Available: " . round($mem->availableBytes / 1024**3, 2) . " GB (" . round($mem->availablePercentage(), 1) . "%)\n";
echo "Swap Used: " . round($mem->swapUsedBytes / 1024**3, 2) . " GB (" . round($mem->swapUsedPercentage(), 1) . "%)\n\n";

// ============================================
// LOAD AVERAGE
// ============================================
$load = SystemMetrics::loadAverage()->getValue();
$normalized = $load->normalized($cpu);

echo "=== LOAD AVERAGE ===\n";
echo "1 min: {$load->oneMinute} (raw)\n";
echo "5 min: {$load->fiveMinutes} (raw)\n";
echo "15 min: {$load->fifteenMinutes} (raw)\n";
echo "Capacity (1 min): " . round($normalized->oneMinutePercentage(), 1) . "%\n";
echo "Capacity (5 min): " . round($normalized->fiveMinutesPercentage(), 1) . "%\n";
echo "Capacity (15 min): " . round($normalized->fifteenMinutesPercentage(), 1) . "%\n\n";

// ============================================
// STORAGE METRICS
// ============================================
$storage = SystemMetrics::storage()->getValue();

echo "=== STORAGE ===\n";
foreach ($storage->mountPoints as $mount) {
    if ($mount->totalBytes > 0) {
        echo "Mount: {$mount->mountPoint} ({$mount->device})\n";
        echo "  Type: {$mount->fsType->value}\n";
        echo "  Size: " . round($mount->totalBytes / 1024**3, 2) . " GB\n";
        echo "  Used: " . round($mount->usedPercentage(), 1) . "%\n";
    }
}
echo "\n";

// ============================================
// NETWORK METRICS
// ============================================
$network = SystemMetrics::network()->getValue();

echo "=== NETWORK ===\n";
foreach ($network->interfaces as $iface) {
    if ($iface->stats->totalBytes() > 0) {
        echo "Interface: {$iface->name} ({$iface->type->value})\n";
        echo "  Sent: " . round($iface->stats->bytesSent / 1024**2, 2) . " MB\n";
        echo "  Received: " . round($iface->stats->bytesReceived / 1024**2, 2) . " MB\n";
        echo "  Errors: {$iface->stats->totalErrors()}\n";
    }
}
echo "\n";

// ============================================
// CONTAINER METRICS (Cgroups)
// ============================================
$container = SystemMetrics::container()->getValue();

echo "=== CONTAINER ===\n";
echo "Cgroup Version: {$container->cgroupVersion->value}\n";
if ($container->hasCpuLimit()) {
    echo "CPU Quota: {$container->cpuQuota} cores\n";
    echo "CPU Usage: " . round($container->cpuUtilizationPercentage() ?? 0, 1) . "%\n";
    echo "CPU Throttled: " . ($container->isCpuThrottled() ? 'YES' : 'no') . "\n";
}
if ($container->hasMemoryLimit()) {
    echo "Memory Limit: " . round($container->memoryLimitBytes / 1024**3, 2) . " GB\n";
    echo "Memory Usage: " . round($container->memoryUtilizationPercentage() ?? 0, 1) . "%\n";
    echo "OOM Kills: " . ($container->hasOomKills() ? "YES ({$container->oomKillCount})" : 'no') . "\n";
}
echo "\n";

// ============================================
// PROCESS METRICS (Current Process)
// ============================================
$process = ProcessMetrics::snapshot(getmypid())->getValue();

echo "=== CURRENT PROCESS (PID: {$process->pid}) ===\n";
echo "Parent PID: {$process->parentPid}\n";
echo "CPU User: {$process->resources->cpuTimes->user} ticks\n";
echo "CPU System: {$process->resources->cpuTimes->system} ticks\n";
echo "Memory RSS: " . round($process->resources->memoryRssBytes / 1024**2, 2) . " MB\n";
echo "Memory VMS: " . round($process->resources->memoryVmsBytes / 1024**2, 2) . " MB\n";
echo "Threads: {$process->resources->threadCount}\n";
echo "Open Files: {$process->resources->openFileDescriptors}\n";
```

**Output example:**
```
=== ENVIRONMENT ===
OS: macOS 26.0.1
Architecture: arm64
Virtualization: bare_metal
Container: no
Cgroup: none

=== CPU ===
Cores: 10
User Time: 1234567 ticks
System Time: 567890 ticks
Idle Time: 8901234 ticks
Total Time: 10703691 ticks
Busy Time: 1802457 ticks

=== MEMORY ===
Total: 64.00 GB
Used: 32.50 GB (50.8%)
Available: 31.50 GB (49.2%)
Swap Used: 2.00 GB (12.5%)

=== LOAD AVERAGE ===
1 min: 2.45 (raw)
5 min: 1.80 (raw)
15 min: 1.20 (raw)
Capacity (1 min): 24.5%
Capacity (5 min): 18.0%
Capacity (15 min): 12.0%

=== STORAGE ===
Mount: / (disk1s1)
  Type: apfs
  Size: 500.00 GB
  Used: 67.5%
Mount: /System/Volumes/Data (disk1s2)
  Type: apfs
  Size: 500.00 GB
  Used: 32.8%

=== NETWORK ===
Interface: en0 (ethernet)
  Sent: 1234.56 MB
  Received: 5678.90 MB
  Errors: 0
Interface: lo0 (loopback)
  Sent: 12.34 MB
  Received: 12.34 MB
  Errors: 0

=== CONTAINER ===
Cgroup Version: v2
CPU Quota: 2.0 cores
CPU Usage: 45.3%
CPU Throttled: no
Memory Limit: 4.00 GB
Memory Usage: 62.5%
OOM Kills: no

=== CURRENT PROCESS (PID: 12345) ===
Parent PID: 1234
CPU User: 450 ticks
CPU System: 123 ticks
Memory RSS: 45.32 MB
Memory VMS: 128.50 MB
Threads: 8
Open Files: 25
```

## What You Can Do

### ✅ Environment Detection

Detect OS, architecture, virtualization, containers, and cgroups:

```php
use PHPeek\SystemMetrics\SystemMetrics;

$env = SystemMetrics::environment()->getValue();

// OS Info
echo "OS Family: " . $env->os->family->value . "\n";      // OsFamily::Linux
echo "OS Name: " . $env->os->name . "\n";                  // "Ubuntu"
echo "OS Version: " . $env->os->version . "\n";            // "22.04"

// Architecture
echo "Architecture: " . $env->architecture->kind->value . "\n";  // "x86_64" or "arm64"
echo "Raw Architecture: " . $env->architecture->raw . "\n";      // Raw architecture string

// Virtualization
echo "Virtualization Type: " . $env->virtualization->type->value . "\n";  // "bare_metal", "virtual_machine"
echo "Virtualization Vendor: " . ($env->virtualization->vendor ?? 'none') . "\n";  // "KVM", "VMware", null

// Container Detection
echo "Inside Container: " . ($env->containerization->insideContainer ? 'yes' : 'no') . "\n";
echo "Container Type: " . $env->containerization->type->value . "\n";  // "docker", "kubernetes", "none"

// Cgroups
echo "Cgroup Version: " . $env->cgroup->version->value . "\n";  // "v1", "v2", "none", "unknown"
echo "Cgroup CPU Path: " . ($env->cgroup->cpuPath ?? 'none') . "\n";
echo "Cgroup Memory Path: " . ($env->cgroup->memoryPath ?? 'none') . "\n";
```

### ✅ CPU Metrics

Get raw CPU time counters (in ticks):

```php
use PHPeek\SystemMetrics\SystemMetrics;

$cpu = SystemMetrics::cpu()->getValue();

// Total CPU time across all cores
echo "User Time: " . $cpu->total->user . " ticks\n";
echo "System Time: " . $cpu->total->system . " ticks\n";
echo "Idle Time: " . $cpu->total->idle . " ticks\n";
echo "IO Wait: " . $cpu->total->iowait . " ticks\n";  // Linux only
echo "Total Time: " . $cpu->total->total() . " ticks\n";
echo "Busy Time: " . $cpu->total->busy() . " ticks\n";

// Per-core metrics
echo "CPU Cores: " . $cpu->coreCount() . "\n";
foreach ($cpu->perCore as $core) {
    echo "Core {$core->coreIndex}: {$core->times->user} ticks\n";
}
```

**Note:** These are raw counters that increase monotonically. To calculate CPU usage %, you need to take two snapshots and calculate the delta.

### ✅ Memory Metrics

Get memory usage (all values in bytes):

```php
use PHPeek\SystemMetrics\SystemMetrics;

$mem = SystemMetrics::memory()->getValue();

// Physical Memory (bytes)
echo "Total RAM: " . round($mem->totalBytes / 1024**3, 2) . " GB\n";
echo "Free: " . round($mem->freeBytes / 1024**3, 2) . " GB\n";
echo "Available: " . round($mem->availableBytes / 1024**3, 2) . " GB\n";
echo "Used: " . round($mem->usedBytes / 1024**3, 2) . " GB\n";
echo "Buffers: " . round($mem->buffersBytes / 1024**2, 2) . " MB\n";  // Linux
echo "Cached: " . round($mem->cachedBytes / 1024**3, 2) . " GB\n";   // Linux

// Calculated Percentages
echo "Memory Usage: " . round($mem->usedPercentage(), 1) . "%\n";
echo "Memory Available: " . round($mem->availablePercentage(), 1) . "%\n";

// Swap Memory
echo "Swap Total: " . round($mem->swapTotalBytes / 1024**3, 2) . " GB\n";
echo "Swap Free: " . round($mem->swapFreeBytes / 1024**3, 2) . " GB\n";
echo "Swap Used: " . round($mem->swapUsedBytes / 1024**3, 2) . " GB\n";
echo "Swap Usage: " . round($mem->swapUsedPercentage(), 1) . "%\n";
```

### ✅ Load Average

Get system load average without needing delta calculations:

```php
use PHPeek\SystemMetrics\SystemMetrics;

$load = SystemMetrics::loadAverage()->getValue();

// Raw load average values (number of processes in run queue)
echo "Load Average (1 min): " . $load->oneMinute . "\n";
echo "Load Average (5 min): " . $load->fiveMinutes . "\n";
echo "Load Average (15 min): " . $load->fifteenMinutes . "\n";

// Normalize by core count for capacity percentage
$cpu = SystemMetrics::cpu()->getValue();
$normalized = $load->normalized($cpu);

echo "\nNormalized Load (1 min): " . round($normalized->oneMinute, 3) . "\n";
echo "CPU Capacity (1 min): " . round($normalized->oneMinutePercentage(), 1) . "%\n";
echo "CPU Cores: " . $normalized->coreCount . "\n";
```

**What is Load Average?**

Load average represents the number of processes in the run queue (runnable + waiting for CPU). On Linux, it also includes processes in uninterruptible I/O wait.

- **Raw values** are absolute numbers (e.g., 4.0 means 4 processes waiting)
- **Normalized values** divide by core count to show capacity (e.g., 4.0 / 8 cores = 0.5 = 50%)
- **Percentage helpers** multiply by 100 for easier interpretation (50% capacity)

**Interpretation:**
- `< 1.0` (< 100%): System has spare capacity
- `= 1.0` (= 100%): System is at full capacity
- `> 1.0` (> 100%): System is overloaded, processes are queuing

**Note:** Load average ≠ CPU usage percentage. A system with high I/O wait can have high load but low CPU usage.

### ✅ Storage Metrics

Get filesystem and disk I/O statistics:

```php
use PHPeek\SystemMetrics\SystemMetrics;

$storage = SystemMetrics::storage()->getValue();

// Mount Points (filesystem usage)
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

// Disk I/O Statistics (cumulative counters)
foreach ($storage->diskIO as $disk) {
    echo "Device: {$disk->device}\n";
    echo "Reads: {$disk->readsCompleted} operations, " . round($disk->readBytes / 1024**2, 2) . " MB\n";
    echo "Writes: {$disk->writesCompleted} operations, " . round($disk->writeBytes / 1024**2, 2) . " MB\n";
    echo "I/O Time: {$disk->ioTimeMs} ms\n";
    echo "Total Operations: {$disk->totalOperations()}\n";
    echo "Total Bytes: " . round($disk->totalBytes() / 1024**3, 2) . " GB\n\n";
}

// Aggregate statistics
echo "Total Storage: " . round($storage->totalBytes() / 1024**3, 2) . " GB\n";
echo "Total Used: " . round($storage->usedBytes() / 1024**3, 2) . " GB\n";
echo "Overall Usage: " . round($storage->usedPercentage(), 1) . "%\n";
```

**Note:** Disk I/O counters are cumulative since boot. To get I/O rates (MB/s, IOPS), take two snapshots and calculate the delta over time.

**Filesystem Types:**
- Linux: ext2, ext3, ext4, xfs, btrfs, zfs, tmpfs, nfs, etc.
- macOS: apfs, hfs, hfs+, ntfs, fat32, exfat

### ✅ Network Metrics

Get network interface statistics and connection information:

```php
use PHPeek\SystemMetrics\SystemMetrics;

$network = SystemMetrics::network()->getValue();

// Network Interfaces
foreach ($network->interfaces as $interface) {
    echo "Interface: {$interface->name}\n";
    echo "Type: {$interface->type->value}\n";
    echo "MAC Address: {$interface->macAddress}\n";
    echo "Status: " . ($interface->isUp ? 'UP' : 'DOWN') . "\n";
    echo "MTU: {$interface->mtu}\n";

    // Traffic statistics (cumulative counters)
    $stats = $interface->stats;
    echo "Received: " . round($stats->bytesReceived / 1024**2, 2) . " MB ({$stats->packetsReceived} packets)\n";
    echo "Sent: " . round($stats->bytesSent / 1024**2, 2) . " MB ({$stats->packetsSent} packets)\n";
    echo "Errors: RX {$stats->receiveErrors}, TX {$stats->transmitErrors}\n";
    echo "Drops: RX {$stats->receiveDrops}, TX {$stats->transmitDrops}\n";
    echo "Total: " . round($stats->totalBytes() / 1024**2, 2) . " MB ({$stats->totalPackets()} packets)\n\n";
}

// Connection Statistics (if available)
if ($network->connections !== null) {
    $conn = $network->connections;
    echo "=== CONNECTIONS ===\n";
    echo "TCP Established: {$conn->tcpEstablished}\n";
    echo "TCP Listening: {$conn->tcpListening}\n";
    echo "TCP Time Wait: {$conn->tcpTimeWait}\n";
    echo "UDP Listening: {$conn->udpListening}\n";
    echo "Total Connections: {$conn->totalConnections}\n";
}

// Aggregate statistics
echo "\n=== TOTALS ===\n";
echo "Total Received: " . round($network->totalBytesReceived() / 1024**3, 2) . " GB\n";
echo "Total Sent: " . round($network->totalBytesSent() / 1024**3, 2) . " GB\n";
echo "Total Packets: " . $network->totalPacketsReceived() + $network->totalPacketsSent() . "\n";
```

**Note:** Network counters are cumulative since boot. To get bandwidth (MB/s), take two snapshots and calculate the delta.

**Interface Types:**
- `ethernet`: Wired Ethernet interfaces (eth*, en*)
- `wifi`: Wireless interfaces (wlan*, wl*, wi*)
- `loopback`: Loopback interface (lo, lo0)
- `bridge`: Bridge interfaces (br*)
- `vlan`: VLAN interfaces (vlan*)
- `vpn`: VPN interfaces (vpn*, tun*, tap*)
- `cellular`: Mobile data (ppp*, wwan*)
- `bluetooth`: Bluetooth interfaces (bt*)
- `other`: Unknown or virtual interfaces

### ✅ Container Metrics (Cgroups)

Get container resource limits and usage when running in Docker/Kubernetes:

```php
$container = SystemMetrics::container()->getValue();

// Check if running in container
if ($container->cgroupVersion !== CgroupVersion::NONE) {
    echo "Cgroup version: {$container->cgroupVersion->value}\n";

    // CPU limits and usage
    if ($container->hasCpuLimit()) {
        echo "CPU quota: {$container->cpuQuota} cores\n";
        echo "CPU usage: {$container->cpuUsageCores} cores\n";
        echo "CPU utilization: " . round($container->cpuUtilizationPercentage(), 1) . "%\n";
        echo "Available CPU: {$container->availableCpuCores()} cores\n";
    }

    // Memory limits and usage
    if ($container->hasMemoryLimit()) {
        $limitGB = round($container->memoryLimitBytes / 1024**3, 2);
        $usageGB = round($container->memoryUsageBytes / 1024**3, 2);
        echo "Memory limit: {$limitGB} GB\n";
        echo "Memory usage: {$usageGB} GB\n";
        echo "Memory utilization: " . round($container->memoryUtilizationPercentage(), 1) . "%\n";
    }

    // Health indicators
    if ($container->isCpuThrottled()) {
        echo "⚠️  CPU is being throttled (count: {$container->cpuThrottledCount})\n";
    }

    if ($container->hasOomKills()) {
        echo "🚨 OOM kills detected: {$container->oomKillCount}\n";
    }
}
```

**Features:**
- **Cgroup v1 and v2 support**: Works with both legacy and unified cgroup hierarchies
- **CPU quota tracking**: Detects CPU limits (e.g., Docker `--cpus=2`)
- **Memory limits**: Detects memory limits (e.g., Docker `-m 4g`)
- **Usage monitoring**: Track actual CPU and memory usage within container
- **Throttling detection**: Identify when container is hitting CPU limits
- **OOM kill tracking**: Detect out-of-memory kills
- **Helper methods**: Easy percentage calculations and availability checks

**Use Cases:**
- Auto-scaling decisions based on container limits (not host resources)
- Preventing OOM kills by monitoring memory utilization
- Detecting CPU throttling before it impacts performance
- Resource-aware worker scaling in queues
- Container health monitoring and alerting

**Note:** Returns `CgroupVersion::NONE` on non-Linux systems or when not running in a container.

### ✅ Process-Level Monitoring

Monitor resource usage for individual processes or process groups:

```php
use PHPeek\SystemMetrics\ProcessMetrics;

// Start tracking a process
$trackerId = ProcessMetrics::start(1234)->getValue(); // Returns tracker ID

// Optionally take manual samples for better statistics
ProcessMetrics::sample($trackerId);
sleep(1);
ProcessMetrics::sample($trackerId);

// Stop and get statistics
$stats = ProcessMetrics::stop($trackerId)->getValue();

// Current, Peak, and Average values
echo "Current Memory: " . $stats->current->memoryRssBytes / 1024**2 . " MB\n";
echo "Peak Memory: " . $stats->peak->memoryRssBytes / 1024**2 . " MB\n";
echo "Average Memory: " . $stats->average->memoryRssBytes / 1024**2 . " MB\n";
echo "Samples collected: {$stats->sampleCount}\n";

// Get a one-time snapshot without tracking
$snapshot = ProcessMetrics::snapshot(1234)->getValue();
echo "Memory RSS: {$snapshot->resources->memoryRssBytes} bytes\n";

// Monitor process group (parent + all children)
$group = ProcessMetrics::group(1234)->getValue();
echo "Total processes: {$group->totalProcessCount()}\n";
echo "Total memory: {$group->aggregateMemoryRss()} bytes\n";

// Track process group with children
$trackerId = ProcessMetrics::start(1234, includeChildren: true)->getValue();
// ... work happens ...
$stats = ProcessMetrics::stop($trackerId)->getValue();
```

**Use cases:**
- Queue workers: Monitor resource usage from job start to completion
- Spawned processes: Track ffmpeg, node.js, or other binaries launched by your application
- Memory leak detection: Track peak and average memory usage over time
- Process groups: Monitor parent + all child processes together

## What You Cannot Do

### ❌ Windows Support

Windows is **not supported**. Attempting to use this library on Windows will return error results:

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::cpu();
if ($result->isFailure()) {
    // On Windows: "Unsupported operating system: Windows"
}
```

### ❌ CPU Usage Percentage (Direct)

This library provides **raw counters only**, not calculated percentages. You need to:

1. Take a snapshot
2. Wait (e.g., 1 second)
3. Take another snapshot
4. Calculate delta: `(busy2 - busy1) / (total2 - total1) * 100`

```php
use PHPeek\SystemMetrics\SystemMetrics;

// ❌ Wrong - no instant percentage
$cpu = SystemMetrics::cpu()->getValue();
// There's no $cpu->usagePercentage() method

// ✅ Correct - calculate from two snapshots
$snap1 = SystemMetrics::cpu()->getValue();
sleep(1);
$snap2 = SystemMetrics::cpu()->getValue();

$deltaTotal = $snap2->total->total() - $snap1->total->total();
$deltaBusy = $snap2->total->busy() - $snap1->total->busy();
$cpuUsage = ($deltaBusy / $deltaTotal) * 100;
```

### ❌ Real-Time Streaming

Each method call reads from the system **at that moment**. There's no built-in streaming or continuous monitoring.

### ❌ Historical Data

The library only returns **current values**. No history, trends, or time series data.

## Permission Requirements

### Linux

The library reads from `/proc` and `/sys` filesystems:

```bash
# Required read access (usually world-readable)
/proc/meminfo           # Memory metrics
/proc/stat              # CPU metrics
/proc/loadavg           # Load average
/proc/cpuinfo          # CPU architecture
/proc/self/cgroup      # Container detection
/sys/hypervisor/type   # Virtualization detection
/etc/os-release        # OS information
```

**Permissions:** Usually **no special permissions** needed. Standard user access works.

**Containers:** Inside Docker/Kubernetes, `/proc` is typically mounted, but some metrics may be restricted or show container-specific values.

### macOS

The library executes these commands:

```bash
sysctl -n kern.cp_time      # CPU metrics (may fail on Apple Silicon)
sysctl -n kern.cp_times     # Per-core CPU (may fail on Apple Silicon)
sysctl -n vm.loadavg        # Load average
sysctl -n hw.memsize        # Total RAM
sysctl -n hw.ncpu           # CPU count
vm_stat                     # Memory statistics
sw_vers -productVersion     # OS version
```

**Permissions:** Usually **no special permissions** needed. Standard user access works.

**Restrictions:** On modern macOS (especially Apple Silicon), CPU time sysctls may be unavailable. The library gracefully returns zero values instead of failing.

## Known Limitations

### macOS CPU Metrics (Apple Silicon)

Modern macOS versions (especially Apple Silicon) have **deprecated** `kern.cp_time` and `kern.cp_times` sysctls:

```php
use PHPeek\SystemMetrics\SystemMetrics;

$cpu = SystemMetrics::cpu()->getValue();
// On Apple Silicon: $cpu->total->user may be 0
// The library won't fail, but CPU counters will be zero
```

**Workaround:** Use `top`, `ps`, or Activity Monitor for CPU usage on modern Macs. This library focuses on Linux production environments.

### Container Environments

Inside containers (Docker, Kubernetes):

- **CPU metrics** reflect the container's assigned CPUs, not host
- **Memory metrics** reflect container limits, not host RAM
- **Environment detection** correctly identifies the container type
- Some `/proc` paths may be read-only or restricted

### macOS Swap

macOS uses **dynamic swap**, creating/removing swap files on-demand. Swap metrics are best-effort estimates.

### File Permissions

If your user lacks permission to read `/proc` or execute `sysctl`:

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::cpu();
// Result will be failure with InsufficientPermissionsException
```

No special capabilities or root access is required in standard environments.

## Error Handling (Result Pattern)

**All methods return `Result<T>`**, not raw values. This forces explicit error handling:

```php
use PHPeek\SystemMetrics\SystemMetrics;

// ✅ Check before using
$result = SystemMetrics::cpu();
if ($result->isSuccess()) {
    $cpu = $result->getValue();
    // Use $cpu safely
} else {
    $error = $result->getError();
    echo "Failed: {$error->getMessage()}\n";
}

// ✅ Provide default value
$cpu = SystemMetrics::cpu()->getValueOr(null);
if ($cpu === null) {
    echo "Could not read CPU metrics\n";
}

// ✅ Functional style with callbacks
SystemMetrics::memory()
    ->onSuccess(fn($mem) => $this->storageMetrics($mem))
    ->onFailure(fn($err) => $this->logError($err));

// ❌ Wrong - will throw if result is failure
$cpu = SystemMetrics::cpu()->getValue(); // Can throw!
```

### Possible Errors

- **`FileNotFoundException`** - `/proc/stat` or similar file doesn't exist
- **`InsufficientPermissionsException`** - Can't read file or execute command
- **`ParseException`** - File format is unexpected/corrupted
- **`UnsupportedOperatingSystemException`** - Not Linux or macOS
- **`SystemMetricsException`** - Generic error (command failed, etc.)

## Advanced Usage

### Custom Implementations

Swap out any metric source with your own implementation:

```php
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;

class RedisCachedCpuSource implements CpuMetricsSource {
    public function read(): Result {
        // Read from Redis cache, fallback to /proc
    }
}

// Set globally
SystemMetricsConfig::setCpuMetricsSource(new RedisCachedCpuSource());

// All subsequent calls use your implementation
$cpu = SystemMetrics::cpu();
```

### Dependency Injection

Actions are independent and can be dependency-injected:

```php
use PHPeek\SystemMetrics\Actions\ReadCpuMetricsAction;
use PHPeek\SystemMetrics\Sources\Cpu\LinuxProcCpuMetricsSource;

$action = new ReadCpuMetricsAction(
    new LinuxProcCpuMetricsSource()
);

$result = $action->execute();
```

### Testing with Stubs

All contracts have interfaces for easy mocking:

```php
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;

$stub = new class implements CpuMetricsSource {
    public function read(): Result {
        return Result::success($this->fakeSnapshot());
    }

    private function fakeSnapshot() { /* ... */ }
};

SystemMetricsConfig::setCpuMetricsSource($stub);
```

## Architecture Overview

### Result<T> Pattern

Instead of exceptions, all operations return `Result<T>`:

- **`isSuccess() / isFailure()`** - Check status
- **`getValue()`** - Get value (throws if failure)
- **`getValueOr($default)`** - Get value or default
- **`getError()`** - Get error (null if success)
- **`map(callable)`** - Transform success value
- **`onSuccess(callable)`** - Execute on success
- **`onFailure(callable)`** - Execute on failure

### Composite Pattern with Fallbacks

Each metric uses a composite source that tries multiple implementations:

```
CompositeCpuMetricsSource
├── LinuxProcCpuMetricsSource (if Linux)
├── MacOsSysctlCpuMetricsSource (if macOS)
└── Returns Result::failure if all fail
```

This enables graceful degradation when APIs are unavailable.

### Immutable DTOs

All data transfer objects use PHP 8.3 readonly classes:

```php
readonly class CpuSnapshot {
    public function __construct(
        public CpuTimes $total,
        public array $perCore,
        public DateTimeImmutable $timestamp,
    ) {}
}
```

Once created, values cannot be modified.

## Design Principles

1. **Pure PHP** - No PHP extensions or Composer packages required
2. **Strict Types** - `declare(strict_types=1)` everywhere
3. **Immutable DTOs** - Readonly classes prevent mutations
4. **Result Pattern** - No uncaught exceptions, explicit error handling
5. **Interface-Driven** - Easy to swap implementations
6. **Action Pattern** - Small, focused, testable use cases

## Quality Standards

- **PHPStan Level 9** - Strictest static analysis
- **89.9% Test Coverage** - Comprehensive test suite
- **PSR-12** - Laravel Pint code style
- **PHP 8.3+** - Modern language features
- **Zero Composer Dependencies** - No external PHP packages

## Testing

```bash
# Run tests
composer test

# With coverage
composer test-coverage

# Static analysis
composer analyse

# Code style
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Sylvester Damgaard](https://github.com/sylvesterdamgaard)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
