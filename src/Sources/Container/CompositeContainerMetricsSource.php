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
 */
final class CompositeContainerMetricsSource implements ContainerMetricsSource
{
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
            $source = new LinuxCgroupMetricsSource;

            return $source->read();
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
