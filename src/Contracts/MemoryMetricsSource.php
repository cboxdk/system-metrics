<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Result;

/**
 * Contract for reading memory metrics.
 */
interface MemoryMetricsSource
{
    /**
     * Read current memory metrics.
     *
     * @return Result<MemorySnapshot>
     */
    public function read(): Result;
}
