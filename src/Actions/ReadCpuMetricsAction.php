<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Actions;

use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;

/**
 * Action to read CPU metrics.
 */
final class ReadCpuMetricsAction
{
    public function __construct(
        private readonly CpuMetricsSource $source = new CompositeCpuMetricsSource,
    ) {}

    /**
     * Execute the CPU metrics reading.
     *
     * @return Result<CpuSnapshot>
     */
    public function execute(): Result
    {
        return $this->source->read();
    }
}
