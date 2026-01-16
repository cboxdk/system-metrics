<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;

/**
 * Action to read memory metrics.
 */
final class ReadMemoryMetricsAction
{
    public function __construct(
        private readonly MemoryMetricsSource $source = new CompositeMemoryMetricsSource,
    ) {}

    /**
     * Execute the memory metrics reading.
     *
     * @return Result<MemorySnapshot>
     */
    public function execute(): Result
    {
        return $this->source->read();
    }
}
