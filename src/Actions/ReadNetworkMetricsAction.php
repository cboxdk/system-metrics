<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Actions;

use PHPeek\SystemMetrics\Contracts\NetworkMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Sources\Network\CompositeNetworkMetricsSource;

/**
 * Action to read network metrics.
 */
final class ReadNetworkMetricsAction
{
    public function __construct(
        private readonly NetworkMetricsSource $source = new CompositeNetworkMetricsSource,
    ) {}

    /**
     * Execute the action to read network metrics.
     *
     * @return Result<NetworkSnapshot>
     */
    public function execute(): Result
    {
        return $this->source->read();
    }
}
