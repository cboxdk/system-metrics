<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use Cbox\SystemMetrics\DTO\Result;

/**
 * Contract for reading system uptime.
 */
interface UptimeSource
{
    /**
     * Read system uptime.
     *
     * @return Result<UptimeSnapshot>
     */
    public function read(): Result;
}
