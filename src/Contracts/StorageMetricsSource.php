<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Result;

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
