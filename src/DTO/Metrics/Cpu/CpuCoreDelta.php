<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Cpu;

/**
 * Delta for a single CPU core between two snapshots.
 *
 * Represents the change in CPU time counters for one core over a time interval.
 */
final readonly class CpuCoreDelta
{
    /**
     * @param  int  $coreIndex  Zero-based core index (0, 1, 2, ...)
     * @param  CpuTimes  $delta  Delta of CPU times for this core
     */
    public function __construct(
        public int $coreIndex,
        public CpuTimes $delta,
    ) {}

    /**
     * Calculate usage percentage for this core (0-100).
     *
     * Formula: (busy_ticks_delta / total_ticks_delta) * 100
     */
    public function usagePercentage(): float
    {
        $total = $this->delta->total();
        if ($total === 0) {
            return 0.0;
        }

        return ($this->delta->busy() / $total) * 100.0;
    }

    /**
     * Calculate idle percentage for this core (0-100).
     *
     * Formula: (idle_ticks_delta / total_ticks_delta) * 100
     */
    public function idlePercentage(): float
    {
        $total = $this->delta->total();
        if ($total === 0) {
            return 0.0;
        }

        return ($this->delta->idle / $total) * 100.0;
    }

    /**
     * Calculate user-mode percentage for this core (0-100).
     */
    public function userPercentage(): float
    {
        $total = $this->delta->total();
        if ($total === 0) {
            return 0.0;
        }

        return ($this->delta->user / $total) * 100.0;
    }

    /**
     * Calculate system-mode percentage for this core (0-100).
     */
    public function systemPercentage(): float
    {
        $total = $this->delta->total();
        if ($total === 0) {
            return 0.0;
        }

        return ($this->delta->system / $total) * 100.0;
    }
}
