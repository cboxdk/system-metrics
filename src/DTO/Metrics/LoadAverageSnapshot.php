<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics;

use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;

/**
 * System load average snapshot.
 *
 * Load average represents the number of processes in the run queue
 * (runnable + waiting for CPU). On Linux, it also includes processes
 * in uninterruptible state (waiting for disk I/O).
 *
 * This is NOT the same as CPU usage percentage. Load average is a
 * measure of system capacity saturation, not resource utilization.
 *
 * To interpret load average values, divide by the number of CPU cores:
 * - Load = cores: System at full capacity
 * - Load < cores: System underutilized
 * - Load > cores: System overloaded (processes waiting)
 */
final readonly class LoadAverageSnapshot
{
    public function __construct(
        public float $oneMinute,
        public float $fiveMinutes,
        public float $fifteenMinutes,
    ) {}

    /**
     * Get normalized load average (divided by core count).
     *
     * Returns load average as a percentage of system capacity.
     * Requires a CpuSnapshot to determine core count.
     */
    public function normalized(CpuSnapshot $cpu): NormalizedLoadAverage
    {
        $coreCount = $cpu->coreCount();

        if ($coreCount === 0) {
            // Prevent division by zero - return zeros
            return new NormalizedLoadAverage(
                oneMinute: 0.0,
                fiveMinutes: 0.0,
                fifteenMinutes: 0.0,
                coreCount: 0
            );
        }

        return new NormalizedLoadAverage(
            oneMinute: $this->oneMinute / $coreCount,
            fiveMinutes: $this->fiveMinutes / $coreCount,
            fifteenMinutes: $this->fifteenMinutes / $coreCount,
            coreCount: $coreCount
        );
    }
}
