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
    return new class($version, $cpuQuota, $memoryLimitBytes, $cpuUsageCores, $memoryUsageBytes) implements ContainerMetricsSource
    {
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
    return new class($coreCount) implements CpuMetricsSource
    {
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
    return new class($totalBytes, $usedBytes) implements MemoryMetricsSource
    {
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

        // Falls back to host: 16.0 cores, but source is still CGROUP_V2
        expect($limits->cpuCores)->toBe(16.0);
        expect($limits->source)->toBe(LimitSource::CGROUP_V2);
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
