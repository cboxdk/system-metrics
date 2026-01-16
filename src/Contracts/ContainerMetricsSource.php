<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Result;

/**
 * Contract for reading container resource limits and usage.
 */
interface ContainerMetricsSource
{
    /**
     * Read container limits and usage from cgroups.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Container\ContainerLimits>
     */
    public function read(): Result;
}
