# Millicores CPU Capacity + Container Limits Bug Fixes

**Issue:** [cboxdk/system-metrics#6](https://github.com/cboxdk/system-metrics/issues/6)
**Date:** 2026-04-29
**Status:** Approved
**Breaking:** Yes — requires major version bump

## Problem

Four related bugs in the container/limits path make `SystemMetrics::limits()` unreliable in containerized environments:

1. **Type precision loss** — `SystemLimits::$cpuCores` and `$currentCpuCores` are `int`. Cgroup quotas like 200m (0.2 cores) or 1500m (1.5 cores) get `(int) ceil()`'d, silently rounding up (0.2 → 1, 1.5 → 2). This breaks downstream scaling formulas in `laravel-queue-autoscale`.

2. **Headroom used as limit** — `CompositeSystemLimitsSource:75-76` populates `cpuCores` (the total limit) with `$container->availableCpuCores()` which computes `quota - usage` (headroom remaining). Same bug for memory: uses `availableMemoryBytes()` (limit - usage) instead of `memoryLimitBytes`. Result: the "limit" shrinks as usage increases.

3. **Delta sampling permanently broken** — `CompositeContainerMetricsSource:29-32` creates a new `LinuxCgroupMetricsSource` instance on every `read()` call. The delta-based CPU usage calculation (`computeUsageRate()`) requires two samples from the same parser instance to compute a delta. Since the parser (and its cache) is recreated each time, `cpuUsageCores` is always `null` — not just on first call, but on every call. The delta sampling feature is completely non-functional.

4. **Utilization capping inconsistency** — `ContainerLimits::cpuUtilizationPercentage()` and `memoryUtilizationPercentage()` cap at `min(100.0, ...)`, hiding over-quota burst scenarios. `SystemLimits::cpuUtilization()` allows >100%. Over-quota detection is operationally important for scaling decisions.

## Design

### Change 1: SystemLimits DTO — int to float for CPU fields

**File:** `src/DTO/Metrics/SystemLimits.php`

Change CPU-related fields and methods from `int` to `float`:

```php
final readonly class SystemLimits
{
    public function __construct(
        public LimitSource $source,
        public float $cpuCores,           // was: int
        public int $memoryBytes,
        public float $currentCpuCores,    // was: int
        public float $currentMemoryBytes,
        public ?int $swapBytes = null,
        public ?float $currentSwapBytes = null,
    ) {}

    public function availableCpuCores(): float    // was: int
    {
        $available = $this->cpuCores - $this->currentCpuCores;
        return max(0.0, $available);               // was: max(0, ...)
    }

    public function cpuUtilization(): float
    {
        if ($this->cpuCores <= 0.0) {              // was: === 0
            return 0.0;
        }
        return ($this->currentCpuCores / $this->cpuCores) * 100;
    }

    public function canScaleCpu(float $additionalCores): bool  // was: int
    {
        return ($this->currentCpuCores + $additionalCores) <= $this->cpuCores;
    }
}
```

Methods that stay unchanged: `availableMemoryBytes()`, `memoryUtilization()`, `canScaleMemory()`, `swapUtilization()`, `cpuHeadroom()`, `memoryHeadroom()`, `isMemoryPressure()`, `isCpuPressure()`, `isContainerized()`.

### Change 2: CompositeSystemLimitsSource — use quota, not headroom

**File:** `src/Sources/SystemLimits/CompositeSystemLimitsSource.php`

In `readFromCgroup()`, use the raw limit/quota properties instead of headroom methods:

```php
// Before (WRONG):
$cpuCores = $container->availableCpuCores() ?? $this->getHostCpuCores();
$memoryBytes = $container->availableMemoryBytes() ?? $memory->totalBytes;
// ...
cpuCores: (int) ceil($cpuCores),
currentCpuCores: (int) ceil($currentCpuUsage),

// After (CORRECT):
$cpuCores = $container->cpuQuota ?? (float) $this->getHostCpuCores();
$memoryBytes = $container->memoryLimitBytes ?? $memory->totalBytes;
// ...
cpuCores: $cpuCores,
currentCpuCores: $currentCpuUsage,
```

Also change `getHostCpuCores()` return type from `int` to `float`:

```php
private function getHostCpuCores(): float    // was: int
{
    $cpuResult = $this->cpuSource->read();
    if ($cpuResult->isFailure()) {
        return 1.0;                           // was: 1
    }
    return (float) $cpuResult->getValue()->coreCount();
}
```

In `readFromHost()`, cast values to float:

```php
$cpuCores = (float) $cpu->coreCount();       // was: $cpu->coreCount() (int)
$currentCpuCores = 0.0;                      // was: 0 (int)
```

### Change 3: CompositeContainerMetricsSource — persist source instance

**File:** `src/Sources/Container/CompositeContainerMetricsSource.php`

Store the Linux source as a lazy-initialized instance variable so the parser (and its delta cache) survives across calls:

```php
final class CompositeContainerMetricsSource implements ContainerMetricsSource
{
    private ?ContainerMetricsSource $linuxSource = null;

    public function __construct(
        private readonly ?ContainerMetricsSource $source = null,
    ) {}

    public function read(): Result
    {
        if ($this->source !== null) {
            return $this->source->read();
        }

        if (OsDetector::isLinux()) {
            if ($this->linuxSource === null) {
                $this->linuxSource = new LinuxCgroupMetricsSource;
            }
            return $this->linuxSource->read();
        }

        // Non-Linux: return NONE
        return Result::success(new ContainerLimits(
            cgroupVersion: CgroupVersion::NONE,
            cpuQuota: null,
            memoryLimitBytes: null,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: null,
        ));
    }
}
```

Note: this class cannot be `readonly` because `$linuxSource` is mutable. If the class is currently `final class` (not `final readonly class`), no issue. If it is `final readonly class`, that modifier must be removed.

### Change 4: ContainerLimits — remove utilization caps

**File:** `src/DTO/Metrics/Container/ContainerLimits.php`

Remove `min(100.0, ...)` from utilization methods to allow detecting over-quota usage, matching `SystemLimits` behavior:

```php
public function cpuUtilizationPercentage(): ?float
{
    if ($this->cpuQuota === null || $this->cpuUsageCores === null || $this->cpuQuota <= 0) {
        return null;
    }
    return ($this->cpuUsageCores / $this->cpuQuota) * 100;  // was: min(100.0, ...)
}

public function memoryUtilizationPercentage(): ?float
{
    if ($this->memoryLimitBytes === null || $this->memoryUsageBytes === null || $this->memoryLimitBytes <= 0) {
        return null;
    }
    return ($this->memoryUsageBytes / $this->memoryLimitBytes) * 100;  // was: min(100.0, ...)
}
```

## What stays unchanged

- `CpuSnapshot::coreCount()` — stays `int`, counts physical cores from `/proc/stat`
- `ContainerLimits` fields and `availableCpuCores()` — already uses `?float`
- `CgroupV1CpuParser` / `CgroupV2CpuParser` — parsing logic is correct
- `LinuxCgroupMetricsSource` — no changes needed
- All other DTOs, actions, facades, contracts

## Test changes

- `tests/Unit/DTO/Metrics/SystemLimitsTest.php` — update expectations for `float` types, add fractional CPU test cases (0.2, 0.5, 1.5 cores)
- `tests/Unit/DTO/Container/ContainerLimitsTest.php` — update utilization tests to expect >100% values
- `tests/Unit/Sources/CompositeSystemLimitsSourceTest.php` — if exists, update for quota vs headroom fix
- Add test: `CompositeContainerMetricsSource` reuses parser instance across calls (verify delta sampling works on second call)

## Breaking changes

This is a breaking change requiring a major version bump:

| Change | Before | After |
|--------|--------|-------|
| `SystemLimits::$cpuCores` | `int` | `float` |
| `SystemLimits::$currentCpuCores` | `int` | `float` |
| `SystemLimits::availableCpuCores()` | returns `int` | returns `float` |
| `SystemLimits::canScaleCpu()` | `int` param | `float` param |
| `ContainerLimits::cpuUtilizationPercentage()` | capped at 100.0 | unbounded |
| `ContainerLimits::memoryUtilizationPercentage()` | capped at 100.0 | unbounded |

Downstream consumers (e.g., `laravel-queue-autoscale` CapacityCalculator) will need to handle `float` instead of `int` for CPU fields.

## Out of scope (separate issues)

- **ProcessDelta hardcoded USER_HZ=100** (`ProcessDelta.php:35`) — different subsystem
- **CpuDelta naming/doc confusion** — cosmetic, separate fix
