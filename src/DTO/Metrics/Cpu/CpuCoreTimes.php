<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Cpu;

/**
 * Represents CPU time counters for a specific core.
 */
final readonly class CpuCoreTimes
{
    public function __construct(
        public int $coreIndex,
        public CpuTimes $times,
    ) {}
}
