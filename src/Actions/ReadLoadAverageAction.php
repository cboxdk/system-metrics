<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Actions;

use PHPeek\SystemMetrics\Contracts\LoadAverageSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Sources\LoadAverage\CompositeLoadAverageSource;

/**
 * Action for reading system load average.
 */
final readonly class ReadLoadAverageAction
{
    public function __construct(
        private LoadAverageSource $source = new CompositeLoadAverageSource,
    ) {}

    /**
     * Execute the action to read load average.
     *
     * @return Result<\PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot>
     */
    public function execute(): Result
    {
        return $this->source->read();
    }
}
