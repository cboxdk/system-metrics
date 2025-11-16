<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Actions;

use PHPeek\SystemMetrics\Contracts\StorageMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Sources\Storage\CompositeStorageMetricsSource;

/**
 * Action to read storage metrics.
 */
final class ReadStorageMetricsAction
{
    public function __construct(
        private readonly StorageMetricsSource $source = new CompositeStorageMetricsSource,
    ) {}

    /**
     * Execute the action to read storage metrics.
     *
     * @return Result<StorageSnapshot>
     */
    public function execute(): Result
    {
        return $this->source->read();
    }
}
