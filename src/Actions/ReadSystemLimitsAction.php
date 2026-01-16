<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\SystemLimitsSource;
use Cbox\SystemMetrics\DTO\Metrics\SystemLimits;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\SystemLimits\CompositeSystemLimitsSource;

/**
 * Read unified system resource limits and current usage.
 *
 * Provides consistent API for resource limits regardless of environment:
 * - Container with cgroups: Returns cgroup limits
 * - Bare metal / VM: Returns host limits
 *
 * Use this for vertical scaling decisions to avoid exceeding limits.
 */
final class ReadSystemLimitsAction
{
    public function __construct(
        private readonly SystemLimitsSource $source = new CompositeSystemLimitsSource,
    ) {}

    /**
     * Execute the action to read system limits.
     *
     * @return Result<SystemLimits>
     */
    public function execute(): Result
    {
        return $this->source->read();
    }
}
