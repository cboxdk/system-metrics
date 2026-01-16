<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\ProcessMetricsSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Process\CompositeProcessMetricsSource;

/**
 * Action for reading process metrics.
 */
final readonly class ReadProcessMetricsAction
{
    public function __construct(
        private ProcessMetricsSource $source = new CompositeProcessMetricsSource,
    ) {}

    /**
     * Execute the action to read process metrics.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot>
     */
    public function execute(int $pid): Result
    {
        return $this->source->read($pid);
    }
}
