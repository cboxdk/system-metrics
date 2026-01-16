<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\ProcessMetricsSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Process\CompositeProcessMetricsSource;

/**
 * Action for reading process group metrics (parent + children).
 */
final readonly class ReadProcessGroupMetricsAction
{
    public function __construct(
        private ProcessMetricsSource $source = new CompositeProcessMetricsSource,
    ) {}

    /**
     * Execute the action to read process group metrics.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot>
     */
    public function execute(int $rootPid): Result
    {
        return $this->source->readProcessGroup($rootPid);
    }
}
