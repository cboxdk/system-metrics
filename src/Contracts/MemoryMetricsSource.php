<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Contracts;

use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use PHPeek\SystemMetrics\DTO\Result;

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
