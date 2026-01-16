<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics;

/**
 * Unified system resource limits and current usage.
 *
 * Provides consistent API for resource limits regardless of environment
 * (bare metal, VPS, or container with cgroups).
 *
 * Critical for vertical scaling decisions - never exceed these limits!
 */
final readonly class SystemLimits
{
    public function __construct(
        public LimitSource $source,
        public int $cpuCores,
        public int $memoryBytes,
        public int $currentCpuCores,
        public float $currentMemoryBytes,
        public ?int $swapBytes = null,
        public ?float $currentSwapBytes = null,
    ) {}

    /**
     * Available CPU cores for scaling up.
     */
    public function availableCpuCores(): int
    {
        $available = $this->cpuCores - $this->currentCpuCores;

        return max(0, $available);
    }

    /**
     * Available memory bytes for scaling up.
     */
    public function availableMemoryBytes(): int
    {
        $available = (int) ($this->memoryBytes - $this->currentMemoryBytes);

        return max(0, $available);
    }

    /**
     * CPU utilization percentage (0-100+).
     * Can exceed 100% if over-provisioned.
     */
    public function cpuUtilization(): float
    {
        if ($this->cpuCores === 0) {
            return 0.0;
        }

        return ($this->currentCpuCores / $this->cpuCores) * 100;
    }

    /**
     * Memory utilization percentage (0-100).
     */
    public function memoryUtilization(): float
    {
        if ($this->memoryBytes === 0) {
            return 0.0;
        }

        return ($this->currentMemoryBytes / $this->memoryBytes) * 100;
    }

    /**
     * Swap utilization percentage (0-100).
     * Returns null if swap not available.
     */
    public function swapUtilization(): ?float
    {
        if ($this->swapBytes === null || $this->currentSwapBytes === null) {
            return null;
        }

        if ($this->swapBytes === 0) {
            return 0.0;
        }

        return ($this->currentSwapBytes / $this->swapBytes) * 100;
    }

    /**
     * Can scale up by specified CPU cores without exceeding limit?
     */
    public function canScaleCpu(int $additionalCores): bool
    {
        return ($this->currentCpuCores + $additionalCores) <= $this->cpuCores;
    }

    /**
     * Can scale up by specified memory bytes without exceeding limit?
     */
    public function canScaleMemory(int $additionalBytes): bool
    {
        return ($this->currentMemoryBytes + $additionalBytes) <= $this->memoryBytes;
    }

    /**
     * Headroom percentage for CPU (how much capacity left).
     * Returns 0-100 where 100 = completely unused, 0 = at capacity.
     */
    public function cpuHeadroom(): float
    {
        return max(0.0, 100.0 - $this->cpuUtilization());
    }

    /**
     * Headroom percentage for memory (how much capacity left).
     * Returns 0-100 where 100 = completely unused, 0 = at capacity.
     */
    public function memoryHeadroom(): float
    {
        return max(0.0, 100.0 - $this->memoryUtilization());
    }

    /**
     * Is system running in container with cgroup limits?
     */
    public function isContainerized(): bool
    {
        return $this->source === LimitSource::CGROUP_V1 || $this->source === LimitSource::CGROUP_V2;
    }

    /**
     * Is memory usage approaching limit? (above threshold percentage)
     */
    public function isMemoryPressure(float $thresholdPercentage = 80.0): bool
    {
        return $this->memoryUtilization() >= $thresholdPercentage;
    }

    /**
     * Is CPU usage approaching limit? (above threshold percentage)
     */
    public function isCpuPressure(float $thresholdPercentage = 80.0): bool
    {
        return $this->cpuUtilization() >= $thresholdPercentage;
    }
}
