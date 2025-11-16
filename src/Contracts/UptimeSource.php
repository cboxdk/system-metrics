<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Contracts;

use PHPeek\SystemMetrics\DTO\Result;

/**
 * Contract for reading system uptime.
 */
interface UptimeSource
{
    /**
     * Read system uptime.
     *
     * @return Result<\PHPeek\SystemMetrics\DTO\Metrics\UptimeSnapshot>
     */
    public function read(): Result;
}
