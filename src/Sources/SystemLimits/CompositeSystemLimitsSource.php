<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\SystemLimits;

use Cbox\SystemMetrics\Contracts\ContainerMetricsSource;
use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\Contracts\SystemLimitsSource;
use Cbox\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use Cbox\SystemMetrics\DTO\Metrics\LimitSource;
use Cbox\SystemMetrics\DTO\Metrics\SystemLimits;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Container\CompositeContainerMetricsSource;
use Cbox\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;
use Cbox\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;

/**
 * Intelligent source that provides unified limits regardless of environment.
 *
 * Decision logic:
 * 1. Check if running in container with cgroup limits
 * 2. If cgroup limits found, use those (container-aware)
 * 3. Otherwise fall back to host limits (bare metal/VM)
 */
final class CompositeSystemLimitsSource implements SystemLimitsSource
{
    public function __construct(
        private readonly ContainerMetricsSource $containerSource = new CompositeContainerMetricsSource,
        private readonly CpuMetricsSource $cpuSource = new CompositeCpuMetricsSource,
        private readonly MemoryMetricsSource $memorySource = new CompositeMemoryMetricsSource,
    ) {}

    public function read(): Result
    {
        // First check if we're running in a container with cgroup limits
        $containerResult = $this->containerSource->read();
        if ($containerResult->isSuccess()) {
            $container = $containerResult->getValue();

            // If we have cgroup limits, use those
            if ($container->cgroupVersion !== CgroupVersion::NONE) {
                return $this->readFromCgroup($container->cgroupVersion);
            }
        }

        // Fall back to host limits
        return $this->readFromHost();
    }

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

        // Use cgroup limits if available, otherwise fall back to host
        $cpuCores = $container->availableCpuCores() ?? $this->getHostCpuCores();
        $memoryBytes = $container->availableMemoryBytes() ?? $memory->totalBytes;

        // Current usage from cgroup
        $currentCpuUsage = $container->cpuUsageCores ?? 0.0;
        $currentMemoryUsage = (float) $container->memoryUsageBytes;

        $source = $version === CgroupVersion::V2 ? LimitSource::CGROUP_V2 : LimitSource::CGROUP_V1;

        /** @var Result<SystemLimits> */
        return Result::success(new SystemLimits(
            source: $source,
            cpuCores: (int) ceil($cpuCores),
            memoryBytes: $memoryBytes,
            currentCpuCores: (int) ceil($currentCpuUsage),
            currentMemoryBytes: $currentMemoryUsage,
            swapBytes: $memory->swapTotalBytes,
            currentSwapBytes: (float) $memory->swapUsedBytes,
        ));
    }

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
        $cpuCores = $cpu->coreCount();

        // Current usage: calculate from most recent snapshot
        // Note: CPU usage requires delta, so we use 0 for "not yet measured"
        // Users should call SystemMetrics::cpu() twice to get actual usage
        $currentCpuCores = 0;

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

    /**
     * Get host CPU cores count.
     */
    private function getHostCpuCores(): int
    {
        $cpuResult = $this->cpuSource->read();
        if ($cpuResult->isFailure()) {
            return 1; // Safe fallback
        }

        return $cpuResult->getValue()->coreCount();
    }
}
