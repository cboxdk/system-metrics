<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Container;

/**
 * Container resource limits and usage from cgroups.
 *
 * All values represent the container's perspective, not the host.
 */
final readonly class ContainerLimits
{
    public function __construct(
        public CgroupVersion $cgroupVersion,
        public ?float $cpuQuota,
        public ?int $memoryLimitBytes,
        public ?float $cpuUsageCores,
        public ?int $memoryUsageBytes,
        public ?int $cpuThrottledCount,
        public ?int $oomKillCount,
    ) {}

    /**
     * Check if container has CPU limits.
     */
    public function hasCpuLimit(): bool
    {
        return $this->cpuQuota !== null && $this->cpuQuota > 0;
    }

    /**
     * Check if container has memory limits.
     */
    public function hasMemoryLimit(): bool
    {
        return $this->memoryLimitBytes !== null && $this->memoryLimitBytes > 0;
    }

    /**
     * Get CPU utilization as percentage (0-100).
     */
    public function cpuUtilizationPercentage(): ?float
    {
        if ($this->cpuQuota === null || $this->cpuUsageCores === null || $this->cpuQuota <= 0) {
            return null;
        }

        return min(100.0, ($this->cpuUsageCores / $this->cpuQuota) * 100);
    }

    /**
     * Get memory utilization as percentage (0-100).
     */
    public function memoryUtilizationPercentage(): ?float
    {
        if ($this->memoryLimitBytes === null || $this->memoryUsageBytes === null || $this->memoryLimitBytes <= 0) {
            return null;
        }

        return min(100.0, ($this->memoryUsageBytes / $this->memoryLimitBytes) * 100);
    }

    /**
     * Get available CPU cores (quota - usage).
     */
    public function availableCpuCores(): ?float
    {
        if ($this->cpuQuota === null || $this->cpuUsageCores === null) {
            return null;
        }

        return max(0.0, $this->cpuQuota - $this->cpuUsageCores);
    }

    /**
     * Get available memory in bytes (limit - usage).
     */
    public function availableMemoryBytes(): ?int
    {
        if ($this->memoryLimitBytes === null || $this->memoryUsageBytes === null) {
            return null;
        }

        return max(0, $this->memoryLimitBytes - $this->memoryUsageBytes);
    }

    /**
     * Check if CPU is being throttled.
     */
    public function isCpuThrottled(): bool
    {
        return $this->cpuThrottledCount !== null && $this->cpuThrottledCount > 0;
    }

    /**
     * Check if OOM kills have occurred.
     */
    public function hasOomKills(): bool
    {
        return $this->oomKillCount !== null && $this->oomKillCount > 0;
    }
}
