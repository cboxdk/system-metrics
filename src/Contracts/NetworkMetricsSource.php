<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Result;

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
