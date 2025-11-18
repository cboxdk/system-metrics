# API Reference

Complete API documentation for all public methods.

## SystemMetrics Facade

Main entry point for system metrics.

### `SystemMetrics::environment(): Result<EnvironmentSnapshot>`

Get environment detection (OS, kernel, architecture, virtualization, containers, cgroups).

**Returns:** `Result<EnvironmentSnapshot>`
**Caching:** Results are automatically cached after first call
**Example:**
```php
$env = SystemMetrics::environment()->getValue();
echo "OS: {$env->os->name}\n";
```

### `SystemMetrics::cpu(): Result<CpuSnapshot>`

Get CPU time counters (raw ticks, not percentages).

**Returns:** `Result<CpuSnapshot>`
**Example:**
```php
$cpu = SystemMetrics::cpu()->getValue();
echo "Cores: {$cpu->coreCount()}\n";
```

### `SystemMetrics::cpuUsage(float $interval): Result<CpuDelta>`

Calculate CPU usage percentage over the specified interval (blocking).

**Parameters:**
- `$interval` - Time to wait in seconds (e.g., 1.0 for 1 second)

**Returns:** `Result<CpuDelta>`
**Example:**
```php
$delta = SystemMetrics::cpuUsage(1.0)->getValue();
echo "CPU: " . round($delta->usagePercentage(), 1) . "%\n";
```

### `SystemMetrics::memory(): Result<MemorySnapshot>`

Get memory metrics (physical RAM and swap).

**Returns:** `Result<MemorySnapshot>`
**Example:**
```php
$mem = SystemMetrics::memory()->getValue();
echo "Usage: " . round($mem->usedPercentage(), 1) . "%\n";
```

### `SystemMetrics::loadAverage(): Result<LoadAverageSnapshot>`

Get system load average (1, 5, 15 minutes).

**Returns:** `Result<LoadAverageSnapshot>`
**Example:**
```php
$load = SystemMetrics::loadAverage()->getValue();
echo "Load (1 min): {$load->oneMinute}\n";
```

### `SystemMetrics::uptime(): Result<UptimeSnapshot>`

Get system uptime since last boot.

**Returns:** `Result<UptimeSnapshot>`
**Example:**
```php
$uptime = SystemMetrics::uptime()->getValue();
echo "Uptime: {$uptime->humanReadable()}\n";
```

### `SystemMetrics::storage(): Result<StorageSnapshot>`

Get filesystem usage and disk I/O statistics.

**Returns:** `Result<StorageSnapshot>`
**Example:**
```php
$storage = SystemMetrics::storage()->getValue();
foreach ($storage->mountPoints as $mount) {
    echo "{$mount->mountPoint}: {$mount->usedPercentage()}%\n";
}
```

### `SystemMetrics::network(): Result<NetworkSnapshot>`

Get network interface statistics and connections.

**Returns:** `Result<NetworkSnapshot>`
**Example:**
```php
$network = SystemMetrics::network()->getValue();
foreach ($network->interfaces as $iface) {
    echo "{$iface->name}: {$iface->stats->totalBytes()} bytes\n";
}
```

### `SystemMetrics::container(): Result<ContainerLimits>`

Get container resource limits and usage (cgroups).

**Returns:** `Result<ContainerLimits>`
**Example:**
```php
$container = SystemMetrics::container()->getValue();
if ($container->hasCpuLimit()) {
    echo "CPU quota: {$container->cpuQuota} cores\n";
}
```

### `SystemMetrics::limits(): Result<SystemLimits>`

Get actual resource limits (environment-aware: host vs container).

**Returns:** `Result<SystemLimits>`
**Example:**
```php
$limits = SystemMetrics::limits()->getValue();
echo "CPU: {$limits->cpuCores} cores\n";
echo "Memory: " . round($limits->memoryBytes / 1024**3, 2) . " GB\n";
```

### `SystemMetrics::overview(): Result<SystemOverview>`

Get complete snapshot of all available metrics.

**Returns:** `Result<SystemOverview>`
**Example:**
```php
$overview = SystemMetrics::overview()->getValue();
echo "OS: {$overview->environment->os->name}\n";
echo "CPU Cores: {$overview->cpu->coreCount()}\n";
echo "Memory: " . round($overview->memory->usedPercentage(), 1) . "%\n";
```

### `SystemMetrics::clearEnvironmentCache(): void`

Clear cached environment detection results.

**Returns:** `void`
**Example:**
```php
SystemMetrics::clearEnvironmentCache();
$env = SystemMetrics::environment()->getValue();  // Fresh read
```

## ProcessMetrics Facade

Process-level monitoring.

### `ProcessMetrics::snapshot(int $pid): Result<ProcessSnapshot>`

Get one-time snapshot of a process.

**Parameters:**
- `$pid` - Process ID

**Returns:** `Result<ProcessSnapshot>`
**Example:**
```php
$process = ProcessMetrics::snapshot(getmypid())->getValue();
echo "Memory: {$process->resources->memoryRssBytes} bytes\n";
```

### `ProcessMetrics::group(int $pid): Result<ProcessGroupSnapshot>`

Get snapshot of process group (parent + all children).

**Parameters:**
- `$pid` - Parent process ID

**Returns:** `Result<ProcessGroupSnapshot>`
**Example:**
```php
$group = ProcessMetrics::group($pid)->getValue();
echo "Total processes: {$group->totalProcessCount()}\n";
```

### `ProcessMetrics::start(int $pid, bool $includeChildren = false): Result<string>`

Start tracking a process or process group.

**Parameters:**
- `$pid` - Process ID
- `$includeChildren` - Track children too

**Returns:** `Result<string>` - Tracker ID
**Example:**
```php
$trackerId = ProcessMetrics::start($pid)->getValue();
```

### `ProcessMetrics::sample(string $trackerId): Result<void>`

Take manual sample of tracked process (optional).

**Parameters:**
- `$trackerId` - Tracker ID from `start()`

**Returns:** `Result<void>`
**Example:**
```php
ProcessMetrics::sample($trackerId);
```

### `ProcessMetrics::stop(string $trackerId): Result<ProcessStats>`

Stop tracking and get statistics.

**Parameters:**
- `$trackerId` - Tracker ID from `start()`

**Returns:** `Result<ProcessStats>`
**Example:**
```php
$stats = ProcessMetrics::stop($trackerId)->getValue();
echo "Peak memory: {$stats->peak->memoryRssBytes} bytes\n";
```

## Result<T> Methods

All metrics methods return `Result<T>` objects with these methods:

### `isSuccess(): bool`
Check if operation succeeded.

### `isFailure(): bool`
Check if operation failed.

### `getValue(): T`
Get the value (throws if failure).

### `getValueOr(T $default): T`
Get value or default if failure.

### `getError(): ?Throwable`
Get error (null if success).

### `map(callable $fn): Result`
Transform success value.

### `onSuccess(callable $fn): Result`
Execute callback on success.

### `onFailure(callable $fn): Result`
Execute callback on failure.

## Configuration

### `SystemMetricsConfig::setCpuMetricsSource(CpuMetricsSource $source): void`
Set custom CPU metrics source.

### `SystemMetricsConfig::setMemoryMetricsSource(MemoryMetricsSource $source): void`
Set custom memory metrics source.

### `SystemMetricsConfig::setEnvironmentDetector(EnvironmentDetector $detector): void`
Set custom environment detector.

See [Custom Implementations](advanced-usage/custom-implementations.md) for details.

## Related Documentation

- [Quick Start](quickstart.md) - Get started quickly
- [Basic Usage](basic-usage/system-overview.md) - Common usage patterns
- [Error Handling](advanced-usage/error-handling.md) - Result<T> pattern
- [Custom Implementations](advanced-usage/custom-implementations.md) - Extend the library
