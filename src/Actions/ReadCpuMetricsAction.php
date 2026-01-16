<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;

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
