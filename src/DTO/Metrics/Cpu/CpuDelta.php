<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Cpu;

use DateTimeImmutable;

/**
 * Delta between two CPU snapshots for calculating usage percentage.
 *
 * IMPORTANT: This represents the change in CPU counters between two points in time.
 * You MUST take two snapshots with time elapsed between them to get accurate results.
 *
 * CPU counters are cumulative since boot (like an odometer). To calculate speed (usage %),
 * you need to measure distance traveled over time.
 *
 * @see \PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessDelta for similar pattern
 */
final readonly class CpuDelta
{
    /**
     * @param  CpuTimes  $totalDelta  Delta of total CPU times between snapshots
     * @param  CpuCoreDelta[]  $perCoreDelta  Delta per core
     * @param  float  $durationSeconds  Time elapsed between snapshots
     * @param  DateTimeImmutable  $startTime  Timestamp of first snapshot
     * @param  DateTimeImmutable  $endTime  Timestamp of second snapshot
     */
    public function __construct(
        public CpuTimes $totalDelta,
        public array $perCoreDelta,
        public float $durationSeconds,
        public DateTimeImmutable $startTime,
        public DateTimeImmutable $endTime,
    ) {}

    /**
     * Calculate overall CPU usage percentage (0-100+).
     *
     * Can exceed 100% on multi-core systems when multiple cores are utilized.
     * For example, 200% means 2 cores fully utilized.
     *
     * Formula: (busy_ticks_delta / total_ticks_delta) * 100
     */
    public function usagePercentage(): float
    {
        if ($this->durationSeconds === 0.0) {
            return 0.0;
        }

        $deltaTotal = $this->totalDelta->total();
        $deltaBusy = $this->totalDelta->busy();

        if ($deltaTotal === 0) {
            return 0.0;
        }

        return ($deltaBusy / $deltaTotal) * 100.0;
    }

    /**
     * Calculate CPU usage percentage normalized by core count (0-100).
     *
     * This divides the total usage by number of cores to show average
     * per-core utilization. Always returns 0-100%.
     *
     * Formula: usagePercentage() / core_count
     */
    public function normalizedUsagePercentage(): float
    {
        if (empty($this->perCoreDelta)) {
            return 0.0;
        }

        return $this->usagePercentage() / count($this->perCoreDelta);
    }

    /**
     * Calculate user-mode CPU usage percentage (0-100+).
     *
     * Shows percentage of time spent executing user-space code.
     */
    public function userPercentage(): float
    {
        if ($this->durationSeconds === 0.0) {
            return 0.0;
        }

        $deltaTotal = $this->totalDelta->total();
        if ($deltaTotal === 0) {
            return 0.0;
        }

        return ($this->totalDelta->user / $deltaTotal) * 100.0;
    }

    /**
     * Calculate system-mode CPU usage percentage (0-100+).
     *
     * Shows percentage of time spent executing kernel code.
     */
    public function systemPercentage(): float
    {
        if ($this->durationSeconds === 0.0) {
            return 0.0;
        }

        $deltaTotal = $this->totalDelta->total();
        if ($deltaTotal === 0) {
            return 0.0;
        }

        return ($this->totalDelta->system / $deltaTotal) * 100.0;
    }

    /**
     * Calculate idle CPU percentage (0-100).
     *
     * Shows percentage of time CPU was idle.
     */
    public function idlePercentage(): float
    {
        if ($this->durationSeconds === 0.0) {
            return 0.0;
        }

        $deltaTotal = $this->totalDelta->total();
        if ($deltaTotal === 0) {
            return 0.0;
        }

        return ($this->totalDelta->idle / $deltaTotal) * 100.0;
    }

    /**
     * Calculate I/O wait percentage (0-100).
     *
     * Shows percentage of time CPU was idle while waiting for I/O.
     */
    public function iowaitPercentage(): float
    {
        if ($this->durationSeconds === 0.0) {
            return 0.0;
        }

        $deltaTotal = $this->totalDelta->total();
        if ($deltaTotal === 0) {
            return 0.0;
        }

        return ($this->totalDelta->iowait / $deltaTotal) * 100.0;
    }

    /**
     * Get usage percentage for a specific core (0-100).
     *
     * Returns null if the core index doesn't exist in the delta.
     */
    public function coreUsagePercentage(int $coreIndex): ?float
    {
        foreach ($this->perCoreDelta as $coreDelta) {
            if ($coreDelta->coreIndex === $coreIndex) {
                return $coreDelta->usagePercentage();
            }
        }

        return null;
    }

    /**
     * Get the busiest core during this interval.
     *
     * Returns null if there are no cores in the delta.
     */
    public function busiestCore(): ?CpuCoreDelta
    {
        if (empty($this->perCoreDelta)) {
            return null;
        }

        $busiest = $this->perCoreDelta[0];
        $busiestPercentage = $busiest->usagePercentage();

        foreach ($this->perCoreDelta as $coreDelta) {
            $percentage = $coreDelta->usagePercentage();
            if ($percentage > $busiestPercentage) {
                $busiest = $coreDelta;
                $busiestPercentage = $percentage;
            }
        }

        return $busiest;
    }

    /**
     * Get the least busy (most idle) core during this interval.
     *
     * Returns null if there are no cores in the delta.
     */
    public function idlestCore(): ?CpuCoreDelta
    {
        if (empty($this->perCoreDelta)) {
            return null;
        }

        $idlest = $this->perCoreDelta[0];
        $idlestPercentage = $idlest->usagePercentage();

        foreach ($this->perCoreDelta as $coreDelta) {
            $percentage = $coreDelta->usagePercentage();
            if ($percentage < $idlestPercentage) {
                $idlest = $coreDelta;
                $idlestPercentage = $percentage;
            }
        }

        return $idlest;
    }
}
