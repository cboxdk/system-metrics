<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Actions;

use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;

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
