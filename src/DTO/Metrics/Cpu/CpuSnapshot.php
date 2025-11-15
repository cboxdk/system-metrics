<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Cpu;

/**
 * Complete snapshot of CPU metrics.
 */
final readonly class CpuSnapshot
{
    /**
     * @param  CpuCoreTimes[]  $perCore
     */
    public function __construct(
        public CpuTimes $total,
        public array $perCore,
    ) {}

    /**
     * Get the number of CPU cores.
     */
    public function coreCount(): int
    {
        return count($this->perCore);
    }
}
