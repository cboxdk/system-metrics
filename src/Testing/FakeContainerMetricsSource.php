<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Contracts\ContainerMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use Cbox\SystemMetrics\DTO\Metrics\Container\ContainerLimits;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Fake ContainerMetricsSource for testing.
 */
final class FakeContainerMetricsSource implements ContainerMetricsSource
{
    private ?ContainerLimits $snapshot = null;

    private ?SystemMetricsException $exception = null;

    /**
     * By default, returns a failure (simulates non-containerized environment).
     *
     * @return Result<ContainerLimits>
     */
    public function read(): Result
    {
        if ($this->exception !== null) {
            /** @var Result<ContainerLimits> */
            return Result::failure($this->exception);
        }

        if ($this->snapshot !== null) {
            return Result::success($this->snapshot);
        }

        // Default: not in a container
        /** @var Result<ContainerLimits> */
        return Result::failure(
            new SystemMetricsException('Not running in a container')
        );
    }

    public function set(ContainerLimits $snapshot): self
    {
        $this->snapshot = $snapshot;
        $this->exception = null;

        return $this;
    }

    public function failWith(SystemMetricsException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    /**
     * Configure as a containerized environment with cgroup v2.
     */
    public function asContainer(
        float $cpuQuota = 2.0,
        int $memoryLimitBytes = 4_294_967_296,
    ): self {
        $this->snapshot = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: $cpuQuota,
            memoryLimitBytes: $memoryLimitBytes,
            cpuUsageCores: $cpuQuota * 0.5,
            memoryUsageBytes: (int) ($memoryLimitBytes * 0.6),
            cpuThrottledCount: 0,
            oomKillCount: 0,
        );

        return $this;
    }
}
