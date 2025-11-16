<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Contracts;

use PHPeek\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use PHPeek\SystemMetrics\DTO\Result;

/**
 * Interface for reading storage metrics.
 */
interface StorageMetricsSource
{
    /**
     * Read storage metrics from the system.
     *
     * @return Result<StorageSnapshot>
     */
    public function read(): Result;
}
