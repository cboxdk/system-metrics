# Millicores CPU Capacity + Container Limits Bug Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix four related bugs in the container/limits path so `SystemMetrics::limits()` correctly reports fractional CPU quotas, uses raw limits instead of headroom, persists parser instances for delta sampling, and allows >100% utilization for over-quota detection.

**Architecture:** Four targeted changes across 4 source files + their tests. Each fix is independent at the code level but all relate to the same data flow: cgroup parser → ContainerLimits → CompositeSystemLimitsSource → SystemLimits DTO. The changes are ordered bottom-up: DTOs first (they have no dependencies), then sources (which depend on DTOs).

**Tech Stack:** PHP 8.3+, Pest v4 testing framework, readonly DTOs, Result<T> pattern.

**Spec:** `docs/superpowers/specs/2026-04-29-millicores-cpu-capacity-design.md`

---

### Task 1: ContainerLimits — remove utilization caps

**Files:**
- Modify: `src/DTO/Metrics/Container/ContainerLimits.php:43-62`
- Test: `tests/Unit/DTO/Container/ContainerLimitsTest.php`

This is the simplest change — just remove `min(100.0, ...)` from two methods. Do it first to warm up and verify the test workflow.

- [ ] **Step 1: Update the over-utilization test to expect >100%**

In `tests/Unit/DTO/Container/ContainerLimitsTest.php`, find the test `handles CPU over-utilization correctly` (around line 133) and update it:

```php
it('handles CPU over-utilization correctly', function () {
    $limits = new ContainerLimits(
        cgroupVersion: CgroupVersion::V2,
        cpuQuota: 1.0,
        memoryLimitBytes: null,
        cpuUsageCores: 1.5,
        memoryUsageBytes: null,
        cpuThrottledCount: null,
        oomKillCount: null,
    );

    expect($limits->cpuUtilizationPercentage())->toBe(150.0);
    expect($limits->availableCpuCores())->toBe(0.0);
});
```

Also update `handles memory over-utilization correctly` (around line 148):

```php
it('handles memory over-utilization correctly', function () {
    $limits = new ContainerLimits(
        cgroupVersion: CgroupVersion::V2,
        cpuQuota: null,
        memoryLimitBytes: 2_147_483_648,
        cpuUsageCores: null,
        memoryUsageBytes: 4_294_967_296,
        cpuThrottledCount: null,
        oomKillCount: null,
    );

    expect($limits->memoryUtilizationPercentage())->toBe(200.0);
    expect($limits->availableMemoryBytes())->toBe(0);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/DTO/Container/ContainerLimitsTest.php`

Expected: Two failures — the over-utilization tests expect 150.0/200.0 but get 100.0.

- [ ] **Step 3: Remove the min(100.0, ...) caps**

In `src/DTO/Metrics/Container/ContainerLimits.php`, update both utilization methods.

Change `cpuUtilizationPercentage()` (line 43-50):

```php
/**
 * Get CPU utilization as percentage (0-100+).
 * Can exceed 100% during quota burst scenarios.
 */
public function cpuUtilizationPercentage(): ?float
{
    if ($this->cpuQuota === null || $this->cpuUsageCores === null || $this->cpuQuota <= 0) {
        return null;
    }

    return ($this->cpuUsageCores / $this->cpuQuota) * 100;
}
```

Change `memoryUtilizationPercentage()` (line 55-62):

```php
/**
 * Get memory utilization as percentage (0-100+).
 * Can exceed 100% if memory usage exceeds limit before OOM kill.
 */
public function memoryUtilizationPercentage(): ?float
{
    if ($this->memoryLimitBytes === null || $this->memoryUsageBytes === null || $this->memoryLimitBytes <= 0) {
        return null;
    }

    return ($this->memoryUsageBytes / $this->memoryLimitBytes) * 100;
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/DTO/Container/ContainerLimitsTest.php`

Expected: All 11 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/DTO/Metrics/Container/ContainerLimits.php tests/Unit/DTO/Container/ContainerLimitsTest.php
git commit -m "fix: remove utilization percentage caps from ContainerLimits

Allow cpuUtilizationPercentage() and memoryUtilizationPercentage()
to exceed 100% for over-quota detection. Matches SystemLimits
behavior which already allows >100%."
```

---

### Task 2: SystemLimits DTO — int to float for CPU fields

**Files:**
- Modify: `src/DTO/Metrics/SystemLimits.php:17-95`
- Test: `tests/Unit/DTO/Metrics/SystemLimitsTest.php`

- [ ] **Step 1: Add a fractional CPU test case**

Add this test to the end of the `describe('SystemLimits', ...)` block in `tests/Unit/DTO/Metrics/SystemLimitsTest.php`:

```php
it('supports fractional CPU cores for container environments', function () {
    $limits = new SystemLimits(
        source: LimitSource::CGROUP_V2,
        cpuCores: 0.5,
        memoryBytes: 512_000_000,
        currentCpuCores: 0.2,
        currentMemoryBytes: 256_000_000.0,
    );

    expect($limits->cpuCores)->toBe(0.5);
    expect($limits->currentCpuCores)->toBe(0.2);
    expect($limits->availableCpuCores())->toBe(0.3);
    expect($limits->cpuUtilization())->toBe(40.0);
    expect($limits->canScaleCpu(0.2))->toBeTrue();
    expect($limits->canScaleCpu(0.4))->toBeFalse();
});

it('supports millicores-scale CPU values', function () {
    $limits = new SystemLimits(
        source: LimitSource::CGROUP_V1,
        cpuCores: 1.5,
        memoryBytes: 1_000_000_000,
        currentCpuCores: 1.2,
        currentMemoryBytes: 500_000_000.0,
    );

    expect($limits->cpuCores)->toBe(1.5);
    expect($limits->currentCpuCores)->toBe(1.2);
    expect($limits->availableCpuCores())->toBe(0.3);
    expect($limits->cpuUtilization())->toBe(80.0);
    expect($limits->isCpuPressure())->toBeTrue();
    expect($limits->cpuHeadroom())->toBe(20.0);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/DTO/Metrics/SystemLimitsTest.php`

Expected: Two failures — PHP strict_types prevents passing float (0.5) to int parameter.

- [ ] **Step 3: Change CPU fields from int to float in SystemLimits**

In `src/DTO/Metrics/SystemLimits.php`, make these changes:

Change the constructor (lines 17-25):

```php
public function __construct(
    public LimitSource $source,
    public float $cpuCores,
    public int $memoryBytes,
    public float $currentCpuCores,
    public float $currentMemoryBytes,
    public ?int $swapBytes = null,
    public ?float $currentSwapBytes = null,
) {}
```

Change `availableCpuCores()` (lines 30-35):

```php
/**
 * Available CPU cores for scaling up.
 */
public function availableCpuCores(): float
{
    $available = $this->cpuCores - $this->currentCpuCores;

    return max(0.0, $available);
}
```

Change `cpuUtilization()` (lines 51-58):

```php
/**
 * CPU utilization percentage (0-100+).
 * Can exceed 100% if over-provisioned.
 */
public function cpuUtilization(): float
{
    if ($this->cpuCores <= 0.0) {
        return 0.0;
    }

    return ($this->currentCpuCores / $this->cpuCores) * 100;
}
```

Change `canScaleCpu()` (lines 92-95):

```php
/**
 * Can scale up by specified CPU cores without exceeding limit?
 */
public function canScaleCpu(float $additionalCores): bool
{
    return ($this->currentCpuCores + $additionalCores) <= $this->cpuCores;
}
```

- [ ] **Step 4: Update existing test expectations from int to float**

In `tests/Unit/DTO/Metrics/SystemLimitsTest.php`, update these assertions:

In `can be instantiated with all values` (line 19):
```php
expect($limits->cpuCores)->toBe(8.0);
```
and (line 21):
```php
expect($limits->currentCpuCores)->toBe(4.0);
```

In `calculates available CPU cores correctly` (line 49):
```php
expect($limits->availableCpuCores())->toBe(5.0);
```

In `returns zero available when at capacity` (line 73):
```php
expect($limits->availableCpuCores())->toBe(0.0);
```

In `returns zero available when over capacity` (line 86):
```php
expect($limits->availableCpuCores())->toBe(0.0);
```

All constructor calls throughout the file that pass integer CPU values will continue to work because PHP auto-casts int to float. The `toBe()` assertions need updating only where the test checks the exact value of a float-returning method (since `toBe(5)` fails against `5.0` with strict comparison).

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/DTO/Metrics/SystemLimitsTest.php`

Expected: All tests pass (including the two new fractional CPU tests).

- [ ] **Step 6: Run the full test suite to check for regressions**

Run: `vendor/bin/pest tests/Unit/`

Expected: All unit tests pass. If any other test creates a `SystemLimits` with int values, they still work because int auto-casts to float in PHP.

- [ ] **Step 7: Commit**

```bash
git add src/DTO/Metrics/SystemLimits.php tests/Unit/DTO/Metrics/SystemLimitsTest.php
git commit -m "feat: change SystemLimits CPU fields from int to float

Support fractional CPU cores (e.g. 0.2, 1.5) for container
environments where cgroup quotas are sub-core. Changes cpuCores,
currentCpuCores, availableCpuCores(), and canScaleCpu() to float.

BREAKING CHANGE: SystemLimits CPU fields and methods now use float
instead of int. Ref: cboxdk/system-metrics#6"
```

---

### Task 3: CompositeContainerMetricsSource — persist source instance

**Files:**
- Modify: `src/Sources/Container/CompositeContainerMetricsSource.php`
- Test: `tests/Unit/Sources/CompositeContainerMetricsSourceTest.php` (create)

- [ ] **Step 1: Write a test proving the source is reused across calls**

Create `tests/Unit/Sources/CompositeContainerMetricsSourceTest.php`:

```php
<?php

use Cbox\SystemMetrics\Contracts\ContainerMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use Cbox\SystemMetrics\DTO\Metrics\Container\ContainerLimits;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Container\CompositeContainerMetricsSource;

describe('CompositeContainerMetricsSource', function () {
    it('reuses the same injected source across multiple reads', function () {
        $callCount = 0;

        $mockSource = new class($callCount) implements ContainerMetricsSource {
            private int $callCount;

            public function __construct(int &$callCount)
            {
                $this->callCount = &$callCount;
            }

            public function read(): Result
            {
                $this->callCount++;

                return Result::success(new ContainerLimits(
                    cgroupVersion: CgroupVersion::V2,
                    cpuQuota: 0.5,
                    memoryLimitBytes: 512_000_000,
                    cpuUsageCores: null,
                    memoryUsageBytes: null,
                    cpuThrottledCount: null,
                    oomKillCount: null,
                ));
            }

            public function getCallCount(): int
            {
                return $this->callCount;
            }
        };

        $composite = new CompositeContainerMetricsSource($mockSource);

        $result1 = $composite->read();
        $result2 = $composite->read();

        expect($result1->isSuccess())->toBeTrue();
        expect($result2->isSuccess())->toBeTrue();
        expect($mockSource->getCallCount())->toBe(2);
    });

    it('returns NONE container limits on non-Linux without custom source', function () {
        // Without a custom source on a non-Linux system, should return NONE
        $composite = new CompositeContainerMetricsSource;

        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        // On non-Linux CI, this returns NONE; on Linux CI, it reads cgroups.
        // We just verify it doesn't crash.
    });
});
```

- [ ] **Step 2: Run the test to verify it passes (baseline)**

Run: `vendor/bin/pest tests/Unit/Sources/CompositeContainerMetricsSourceTest.php`

Expected: Both tests pass (the injected source path already works correctly since `$this->source` is reused).

- [ ] **Step 3: Change CompositeContainerMetricsSource to persist the Linux source**

Replace the full file `src/Sources/Container/CompositeContainerMetricsSource.php`:

```php
<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Container;

use Cbox\SystemMetrics\Contracts\ContainerMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use Cbox\SystemMetrics\DTO\Metrics\Container\ContainerLimits;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\OsDetector;

/**
 * Composite container metrics source with automatic OS detection.
 *
 * Persists the Linux source instance so that the cgroup parser's
 * delta-based CPU usage sampling cache survives across calls.
 */
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

        // Only Linux supports cgroups
        if (OsDetector::isLinux()) {
            if ($this->linuxSource === null) {
                $this->linuxSource = new LinuxCgroupMetricsSource;
            }

            return $this->linuxSource->read();
        }

        // Non-Linux systems: return NONE
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

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Sources/CompositeContainerMetricsSourceTest.php`

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Sources/Container/CompositeContainerMetricsSource.php tests/Unit/Sources/CompositeContainerMetricsSourceTest.php
git commit -m "fix: persist LinuxCgroupMetricsSource instance for delta sampling

CompositeContainerMetricsSource was creating a new
LinuxCgroupMetricsSource on every read() call, which meant the
cgroup parser's CPU usage delta cache was always empty.
cpuUsageCores was permanently null.

Lazy-initialize and reuse the Linux source so the parser's cache
survives across calls, enabling actual delta-based CPU usage
calculation."
```

---

### Task 4: CompositeSystemLimitsSource — use quota instead of headroom

**Files:**
- Modify: `src/Sources/SystemLimits/CompositeSystemLimitsSource.php:57-153`
- Test: `tests/Unit/Sources/CompositeSystemLimitsSourceTest.php` (create)

- [ ] **Step 1: Write a test proving quota is used, not headroom**

Create `tests/Unit/Sources/CompositeSystemLimitsSourceTest.php`:

```php
<?php

use Cbox\SystemMetrics\Contracts\ContainerMetricsSource;
use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use Cbox\SystemMetrics\DTO\Metrics\Container\ContainerLimits;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Metrics\LimitSource;
use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\SystemLimits\CompositeSystemLimitsSource;

function makeContainerSource(
    CgroupVersion $version,
    ?float $cpuQuota,
    ?int $memoryLimitBytes,
    ?float $cpuUsageCores = null,
    ?int $memoryUsageBytes = null,
): ContainerMetricsSource {
    return new class($version, $cpuQuota, $memoryLimitBytes, $cpuUsageCores, $memoryUsageBytes) implements ContainerMetricsSource {
        public function __construct(
            private readonly CgroupVersion $version,
            private readonly ?float $cpuQuota,
            private readonly ?int $memoryLimitBytes,
            private readonly ?float $cpuUsageCores,
            private readonly ?int $memoryUsageBytes,
        ) {}

        public function read(): Result
        {
            return Result::success(new ContainerLimits(
                cgroupVersion: $this->version,
                cpuQuota: $this->cpuQuota,
                memoryLimitBytes: $this->memoryLimitBytes,
                cpuUsageCores: $this->cpuUsageCores,
                memoryUsageBytes: $this->memoryUsageBytes,
                cpuThrottledCount: null,
                oomKillCount: null,
            ));
        }
    };
}

function makeCpuSource(int $coreCount): CpuMetricsSource
{
    return new class($coreCount) implements CpuMetricsSource {
        public function __construct(private readonly int $coreCount) {}

        public function read(): Result
        {
            $zeroCpuTimes = new CpuTimes(
                user: 0, nice: 0, system: 0, idle: 0,
                iowait: 0, irq: 0, softirq: 0, steal: 0,
            );
            $perCore = [];
            for ($i = 0; $i < $this->coreCount; $i++) {
                $perCore[] = new CpuCoreTimes(coreIndex: $i, times: $zeroCpuTimes);
            }

            return Result::success(new CpuSnapshot(
                total: $zeroCpuTimes,
                perCore: $perCore,
            ));
        }
    };
}

function makeMemorySource(int $totalBytes, int $usedBytes): MemoryMetricsSource
{
    return new class($totalBytes, $usedBytes) implements MemoryMetricsSource {
        public function __construct(
            private readonly int $totalBytes,
            private readonly int $usedBytes,
        ) {}

        public function read(): Result
        {
            return Result::success(new MemorySnapshot(
                totalBytes: $this->totalBytes,
                freeBytes: $this->totalBytes - $this->usedBytes,
                availableBytes: $this->totalBytes - $this->usedBytes,
                usedBytes: $this->usedBytes,
                cachedBytes: 0,
                buffersBytes: 0,
                swapTotalBytes: 0,
                swapFreeBytes: 0,
                swapUsedBytes: 0,
            ));
        }
    };
}

describe('CompositeSystemLimitsSource', function () {
    it('uses cpuQuota as the CPU limit, not headroom', function () {
        $source = new CompositeSystemLimitsSource(
            containerSource: makeContainerSource(
                version: CgroupVersion::V2,
                cpuQuota: 0.5,
                memoryLimitBytes: 512_000_000,
                cpuUsageCores: 0.3,
                memoryUsageBytes: 256_000_000,
            ),
            cpuSource: makeCpuSource(16),
            memorySource: makeMemorySource(16_000_000_000, 8_000_000_000),
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        // cpuCores should be the quota (0.5), not available (0.5 - 0.3 = 0.2)
        expect($limits->cpuCores)->toBe(0.5);
        expect($limits->currentCpuCores)->toBe(0.3);
        expect($limits->source)->toBe(LimitSource::CGROUP_V2);
    });

    it('uses memoryLimitBytes as the memory limit, not headroom', function () {
        $source = new CompositeSystemLimitsSource(
            containerSource: makeContainerSource(
                version: CgroupVersion::V2,
                cpuQuota: 2.0,
                memoryLimitBytes: 1_000_000_000,
                cpuUsageCores: 0.5,
                memoryUsageBytes: 400_000_000,
            ),
            cpuSource: makeCpuSource(8),
            memorySource: makeMemorySource(16_000_000_000, 8_000_000_000),
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        // memoryBytes should be the limit (1GB), not available (1GB - 400MB = 600MB)
        expect($limits->memoryBytes)->toBe(1_000_000_000);
    });

    it('falls back to host CPU cores when cgroup has no quota', function () {
        $source = new CompositeSystemLimitsSource(
            containerSource: makeContainerSource(
                version: CgroupVersion::V2,
                cpuQuota: null,
                memoryLimitBytes: 512_000_000,
                cpuUsageCores: null,
                memoryUsageBytes: 256_000_000,
            ),
            cpuSource: makeCpuSource(16),
            memorySource: makeMemorySource(16_000_000_000, 8_000_000_000),
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        // Falls back to host: 16.0 cores
        expect($limits->cpuCores)->toBe(16.0);
    });

    it('falls back to host memory when cgroup has no limit', function () {
        $source = new CompositeSystemLimitsSource(
            containerSource: makeContainerSource(
                version: CgroupVersion::V2,
                cpuQuota: 2.0,
                memoryLimitBytes: null,
                cpuUsageCores: 0.5,
                memoryUsageBytes: null,
            ),
            cpuSource: makeCpuSource(8),
            memorySource: makeMemorySource(16_000_000_000, 8_000_000_000),
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        // Falls back to host memory
        expect($limits->memoryBytes)->toBe(16_000_000_000);
    });

    it('reads from host when no cgroups detected', function () {
        $source = new CompositeSystemLimitsSource(
            containerSource: makeContainerSource(
                version: CgroupVersion::NONE,
                cpuQuota: null,
                memoryLimitBytes: null,
            ),
            cpuSource: makeCpuSource(8),
            memorySource: makeMemorySource(16_000_000_000, 8_000_000_000),
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        expect($limits->source)->toBe(LimitSource::HOST);
        expect($limits->cpuCores)->toBe(8.0);
        expect($limits->memoryBytes)->toBe(16_000_000_000);
        expect($limits->currentCpuCores)->toBe(0.0);
    });

    it('preserves fractional CPU values without rounding', function () {
        $source = new CompositeSystemLimitsSource(
            containerSource: makeContainerSource(
                version: CgroupVersion::V1,
                cpuQuota: 0.2,
                memoryLimitBytes: 256_000_000,
                cpuUsageCores: 0.15,
                memoryUsageBytes: 128_000_000,
            ),
            cpuSource: makeCpuSource(16),
            memorySource: makeMemorySource(16_000_000_000, 8_000_000_000),
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        // 200m = 0.2 cores — must NOT be ceil()'d to 1
        expect($limits->cpuCores)->toBe(0.2);
        expect($limits->currentCpuCores)->toBe(0.15);
        expect($limits->source)->toBe(LimitSource::CGROUP_V1);
    });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Sources/CompositeSystemLimitsSourceTest.php`

Expected: Multiple failures — `cpuCores` returns headroom values (or host fallback due to null), not quota.

- [ ] **Step 3: Fix readFromCgroup() to use quota instead of headroom**

In `src/Sources/SystemLimits/CompositeSystemLimitsSource.php`, replace the `readFromCgroup()` method (lines 57-94):

```php
/**
 * Read limits from cgroup (container environment).
 *
 * @return Result<SystemLimits>
 */
private function readFromCgroup(CgroupVersion $version): Result
{
    $containerResult = $this->containerSource->read();
    if ($containerResult->isFailure()) {
        /** @var Result<SystemLimits> */
        return $containerResult;
    }

    $memoryResult = $this->memorySource->read();
    if ($memoryResult->isFailure()) {
        /** @var Result<SystemLimits> */
        return $memoryResult;
    }

    $container = $containerResult->getValue();
    $memory = $memoryResult->getValue();

    // Use cgroup quota/limit directly, not headroom (quota - usage)
    $cpuCores = $container->cpuQuota ?? (float) $this->getHostCpuCores();
    $memoryBytes = $container->memoryLimitBytes ?? $memory->totalBytes;

    // Current usage from cgroup
    $currentCpuUsage = $container->cpuUsageCores ?? 0.0;
    $currentMemoryUsage = (float) $container->memoryUsageBytes;

    $source = $version === CgroupVersion::V2 ? LimitSource::CGROUP_V2 : LimitSource::CGROUP_V1;

    /** @var Result<SystemLimits> */
    return Result::success(new SystemLimits(
        source: $source,
        cpuCores: $cpuCores,
        memoryBytes: $memoryBytes,
        currentCpuCores: $currentCpuUsage,
        currentMemoryBytes: $currentMemoryUsage,
        swapBytes: $memory->swapTotalBytes,
        currentSwapBytes: (float) $memory->swapUsedBytes,
    ));
}
```

- [ ] **Step 4: Fix readFromHost() to use float for CPU values**

In the same file, replace `readFromHost()` (lines 101-139):

```php
/**
 * Read limits from host system (bare metal or VM).
 *
 * @return Result<SystemLimits>
 */
private function readFromHost(): Result
{
    $cpuResult = $this->cpuSource->read();
    if ($cpuResult->isFailure()) {
        /** @var Result<SystemLimits> */
        return $cpuResult;
    }

    $memoryResult = $this->memorySource->read();
    if ($memoryResult->isFailure()) {
        /** @var Result<SystemLimits> */
        return $memoryResult;
    }

    $cpu = $cpuResult->getValue();
    $memory = $memoryResult->getValue();

    // For host limits, we use total system resources
    $cpuCores = (float) $cpu->coreCount();

    // Current usage: calculate from most recent snapshot
    // Note: CPU usage requires delta, so we use 0 for "not yet measured"
    // Users should call SystemMetrics::cpu() twice to get actual usage
    $currentCpuCores = 0.0;

    // Memory current usage
    $currentMemoryUsage = (float) $memory->usedBytes;

    /** @var Result<SystemLimits> */
    return Result::success(new SystemLimits(
        source: LimitSource::HOST,
        cpuCores: $cpuCores,
        memoryBytes: $memory->totalBytes,
        currentCpuCores: $currentCpuCores,
        currentMemoryBytes: $currentMemoryUsage,
        swapBytes: $memory->swapTotalBytes,
        currentSwapBytes: (float) $memory->swapUsedBytes,
    ));
}
```

- [ ] **Step 5: Fix getHostCpuCores() to return float**

In the same file, replace `getHostCpuCores()` (lines 144-152):

```php
/**
 * Get host CPU cores count.
 */
private function getHostCpuCores(): float
{
    $cpuResult = $this->cpuSource->read();
    if ($cpuResult->isFailure()) {
        return 1.0; // Safe fallback
    }

    return (float) $cpuResult->getValue()->coreCount();
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Sources/CompositeSystemLimitsSourceTest.php`

Expected: All 6 tests pass.

- [ ] **Step 7: Run the full test suite**

Run: `vendor/bin/pest tests/Unit/`

Expected: All unit tests pass.

- [ ] **Step 8: Commit**

```bash
git add src/Sources/SystemLimits/CompositeSystemLimitsSource.php tests/Unit/Sources/CompositeSystemLimitsSourceTest.php
git commit -m "fix: use cgroup quota as CPU limit, not headroom

CompositeSystemLimitsSource was using availableCpuCores() (quota -
usage) to populate cpuCores (the total limit). This made the limit
shrink as usage increased. Same bug for memory with
availableMemoryBytes().

Now uses cpuQuota and memoryLimitBytes directly. Also removes
(int) ceil() casts that dropped fractional precision, and changes
getHostCpuCores() to return float.

Ref: cboxdk/system-metrics#6"
```

---

### Task 5: Final verification

- [ ] **Step 1: Run the full test suite**

Run: `composer test`

Expected: All tests pass.

- [ ] **Step 2: Run code formatting**

Run: `composer format`

Expected: Code is formatted (Pint may make minor whitespace changes).

- [ ] **Step 3: Run tests again after formatting**

Run: `composer test`

Expected: Still all green.

- [ ] **Step 4: Commit any formatting changes**

Only if Pint changed something:

```bash
git add -A
git commit -m "style: apply Pint formatting"
```
