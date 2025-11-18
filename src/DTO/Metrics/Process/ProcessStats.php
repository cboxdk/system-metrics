<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Process;

/**
 * Statistical aggregation of process metrics over time.
 *
 * Includes current, peak, and average values calculated from
 * start snapshot + manual samples + stop snapshot.
 *
 * Also includes delta between start and stop for calculating
 * CPU usage percentage and memory growth.
 */
final readonly class ProcessStats
{
    public function __construct(
        public int $pid,
        public ProcessResourceUsage $current,
        public ProcessResourceUsage $peak,
        public ProcessResourceUsage $average,
        public ProcessDelta $delta,
        public int $sampleCount,
        public float $totalDurationSeconds,
        public int $processCount = 1,
    ) {}
}
