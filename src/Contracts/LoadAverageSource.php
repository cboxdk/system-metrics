<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Result;

/**
 * Contract for reading system load average metrics.
 */
interface LoadAverageSource
{
    /**
     * Read current system load average.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot>
     */
    public function read(): Result;
}
