<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Contracts;

use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Result;

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
