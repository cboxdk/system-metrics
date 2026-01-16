<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics;

/**
 * Load average normalized by CPU core count.
 *
 * Represents load average as a ratio of system capacity:
 * - 1.0 = System at 100% capacity (load equals core count)
 * - < 1.0 = System underutilized
 * - > 1.0 = System overloaded (processes waiting for CPU)
 *
 * Example: On an 8-core system
 * - Load average 4.0 → normalized 0.5 (50% capacity)
 * - Load average 8.0 → normalized 1.0 (100% capacity)
 * - Load average 16.0 → normalized 2.0 (200% capacity, overloaded)
 */
final readonly class NormalizedLoadAverage
{
    public function __construct(
        public float $oneMinute,
        public float $fiveMinutes,
        public float $fifteenMinutes,
        public int $coreCount,
    ) {}

    /**
     * Get one-minute load as percentage (0-100+).
     *
     * Values over 100 indicate system overload.
     */
    public function oneMinutePercentage(): float
    {
        return $this->oneMinute * 100.0;
    }

    /**
     * Get five-minute load as percentage (0-100+).
     *
     * Values over 100 indicate system overload.
     */
    public function fiveMinutesPercentage(): float
    {
        return $this->fiveMinutes * 100.0;
    }

    /**
     * Get fifteen-minute load as percentage (0-100+).
     *
     * Values over 100 indicate system overload.
     */
    public function fifteenMinutesPercentage(): float
    {
        return $this->fifteenMinutes * 100.0;
    }
}
