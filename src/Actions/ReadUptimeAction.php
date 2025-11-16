<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Actions;

use PHPeek\SystemMetrics\Contracts\UptimeSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Sources\Uptime\CompositeUptimeSource;

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
     * @return Result<\PHPeek\SystemMetrics\DTO\Metrics\UptimeSnapshot>
     */
    public function execute(): Result
    {
        $source = $this->source ?? new CompositeUptimeSource;

        return $source->read();
    }
}
