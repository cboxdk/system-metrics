<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Result;

/**
 * Contract for reading CPU metrics.
 */
interface CpuMetricsSource
{
    /**
     * Read current CPU metrics.
     *
     * @return Result<CpuSnapshot>
     */
    public function read(): Result;
}
