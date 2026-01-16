<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Cpu;

/**
 * Represents raw CPU time counters in clock ticks.
 */
final readonly class CpuTimes
{
    public function __construct(
        public int $user,
        public int $nice,
        public int $system,
        public int $idle,
        public int $iowait,
        public int $irq,
        public int $softirq,
        public int $steal,
    ) {}

    /**
     * Calculate total CPU time across all categories.
     */
    public function total(): int
    {
        return $this->user +
            $this->nice +
            $this->system +
            $this->idle +
            $this->iowait +
            $this->irq +
            $this->softirq +
            $this->steal;
    }

    /**
     * Calculate busy time (total - idle - iowait).
     */
    public function busy(): int
    {
        return $this->user +
            $this->nice +
            $this->system +
            $this->irq +
            $this->softirq +
            $this->steal;
    }
}
