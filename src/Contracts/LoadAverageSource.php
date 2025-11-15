<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Contracts;

use PHPeek\SystemMetrics\DTO\Result;

/**
 * Contract for reading system load average metrics.
 */
interface LoadAverageSource
{
    /**
     * Read current system load average.
     *
     * @return Result<\PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot>
     */
    public function read(): Result;
}
