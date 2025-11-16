<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Contracts;

use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use PHPeek\SystemMetrics\DTO\Result;

/**
 * Interface for reading network metrics.
 */
interface NetworkMetricsSource
{
    /**
     * Read network metrics from the system.
     *
     * @return Result<NetworkSnapshot>
     */
    public function read(): Result;
}
