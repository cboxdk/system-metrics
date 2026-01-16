<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\NetworkMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Network\CompositeNetworkMetricsSource;

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
