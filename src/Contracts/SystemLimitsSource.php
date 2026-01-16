<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Metrics\SystemLimits;
use Cbox\SystemMetrics\DTO\Result;

/**
 * Contract for retrieving unified system resource limits.
 */
interface SystemLimitsSource
{
    /**
     * Read system resource limits and current usage.
     *
     * Returns limits from cgroups if running in container,
     * otherwise returns host limits.
     *
     * @return Result<SystemLimits>
     */
    public function read(): Result;
}
