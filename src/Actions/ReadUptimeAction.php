<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\UptimeSource;
use Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Uptime\CompositeUptimeSource;

/**
 * Read system uptime.
 */
final readonly class ReadUptimeAction
{
    public function __construct(
        private ?UptimeSource $source = null,
    ) {}

    /**
     * Execute the action.
     *
     * @return Result<UptimeSnapshot>
     */
    public function execute(): Result
    {
        $source = $this->source ?? new CompositeUptimeSource;

        return $source->read();
    }
}
