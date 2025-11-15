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
    echo $overview->environment->os->name;        // "Ubuntu"
    echo $overview->environment->architecture->kind->value; // "x86_64"

    // CPU (raw time counters in ticks)
    echo $overview->cpu->coreCount();             // 8
    echo $overview->cpu->total->user;             // 123456 ticks

    // Memory (all values in bytes)
    $usedGB = $overview->memory->usedBytes / 1024**3;
    echo round($usedGB, 2) . " GB";               // "8.42 GB"
    echo $overview->memory->usedPercentage();     // 52.3
}
```

## What You Can Do

### ✅ Environment Detection

Detect OS, architecture, virtualization, containers, and cgroups:

```php
$env = SystemMetrics::environment()->getValue();

// OS Info
$env->os->family->value;      // OsFamily::Linux
$env->os->name;               // "Ubuntu"
$env->os->version;            // "22.04"

// Architecture
$env->architecture->kind->value;    // "x86_64" or "arm64"
$env->architecture->rosettaTranslation; // true on M1 Macs running x86_64

// Virtualization
$env->virtualization->type->value;  // "kvm", "vmware", "none"
$env->virtualization->isVirtual;    // true/false

// Container Detection
$env->containerization->insideContainer; // true/false
$env->containerization->type->value;     // "docker", "kubernetes", "none"

// Cgroups
$env->cgroup->version->value;  // "v1", "v2", "none"
$env->cgroup->detected;        // true/false
```

### ✅ CPU Metrics

Get raw CPU time counters (in ticks):

```php
$cpu = SystemMetrics::cpu()->getValue();

// Total CPU time across all cores
$cpu->total->user;      // Time in user mode
$cpu->total->system;    // Time in kernel mode
$cpu->total->idle;      // Idle time
$cpu->total->iowait;    // Waiting for I/O (Linux only)
$cpu->total->total();   // Sum of all time
$cpu->total->busy();    // Total - idle

// Per-core metrics
$cpu->coreCount();      // Number of CPU cores
foreach ($cpu->perCore as $core) {
    echo "Core {$core->coreIndex}: {$core->times->user} ticks\n";
}
```

**Note:** These are raw counters that increase monotonically. To calculate CPU usage %, you need to take two snapshots and calculate the delta.

### ✅ Memory Metrics

Get memory usage (all values in bytes):

```php
$mem = SystemMetrics::memory()->getValue();

// Physical Memory
$mem->totalBytes;           // Total physical RAM
$mem->freeBytes;            // Completely unused memory
$mem->availableBytes;       // Free + reclaimable (best indicator)
$mem->usedBytes;            // Actually in use
$mem->buffersBytes;         // Buffer cache (Linux)
$mem->cachedBytes;          // Page cache (Linux)

// Calculated Percentages
$mem->usedPercentage();     // Used / total * 100
$mem->availablePercentage(); // Available / total * 100

// Swap Memory
$mem->swapTotalBytes;       // Total swap space
$mem->swapFreeBytes;        // Free swap
$mem->swapUsedBytes;        // Used swap
$mem->swapUsedPercentage(); // Swap usage %
```

### ✅ Load Average

Get system load average without needing delta calculations:

```php
$load = SystemMetrics::loadAverage()->getValue();

// Raw load average values (number of processes in run queue)
$load->oneMinute;       // 2.45
$load->fiveMinutes;     // 1.80
$load->fifteenMinutes;  // 1.20

// Normalize by core count for capacity percentage
$cpu = SystemMetrics::cpu()->getValue();
$normalized = $load->normalized($cpu);

$normalized->oneMinute;              // 0.306 (on 8-core system)
$normalized->oneMinutePercentage();  // 30.6% capacity
$normalized->coreCount;              // 8
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
