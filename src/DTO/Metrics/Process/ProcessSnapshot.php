<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Process;

use DateTimeImmutable;

/**
 * Process metrics snapshot at a specific point in time.
 */
final readonly class ProcessSnapshot
{
    public function __construct(
        public int $pid,
        public int $parentPid,
        public ProcessResourceUsage $resources,
        public DateTimeImmutable $timestamp,
    ) {}
}
