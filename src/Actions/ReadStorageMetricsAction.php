<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\StorageMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Storage\CompositeStorageMetricsSource;

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
