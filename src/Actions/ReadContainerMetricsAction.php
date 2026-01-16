<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\ContainerMetricsSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Container\CompositeContainerMetricsSource;

/**
 * Read container resource limits and usage.
 */
final readonly class ReadContainerMetricsAction
{
    public function __construct(
        private ?ContainerMetricsSource $source = null,
    ) {}

    /**
     * Execute the action to read container metrics.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Container\ContainerLimits>
     */
    public function execute(): Result
    {
        $source = $this->source ?? new CompositeContainerMetricsSource;

        return $source->read();
    }
}
