<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Process;

use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use DateTimeImmutable;

/**
 * Delta between two process snapshots (typically start and stop).
 */
final readonly class ProcessDelta
{
    public function __construct(
        public int $pid,
        public CpuTimes $cpuDelta,
        public int $memoryDeltaBytes,
        public float $durationSeconds,
        public DateTimeImmutable $startTime,
        public DateTimeImmutable $endTime,
    ) {}

    /**
     * Calculate CPU usage percentage based on elapsed time.
     */
    public function cpuUsagePercentage(): float
    {
        if ($this->durationSeconds === 0.0) {
            return 0.0;
        }

        // CPU ticks are in USER_HZ (typically 100 per second)
        // Convert to seconds and calculate percentage
        $cpuSecondsUsed = $this->cpuDelta->total() / 100.0;

        return ($cpuSecondsUsed / $this->durationSeconds) * 100.0;
    }
}
