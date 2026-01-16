<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\LoadAverageSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\LoadAverage\CompositeLoadAverageSource;

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
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot>
     */
    public function execute(): Result
    {
        return $this->source->read();
    }
}
