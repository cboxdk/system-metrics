<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot;
use Cbox\SystemMetrics\DTO\Result;

/**
 * Contract for reading process-level metrics.
 */
interface ProcessMetricsSource
{
    /**
     * Read metrics for a single process.
     *
     * @return Result<ProcessSnapshot>
     */
    public function read(int $pid): Result;

    /**
     * Read metrics for a process group (parent + all children).
     *
     * @return Result<ProcessGroupSnapshot>
     */
    public function readProcessGroup(int $rootPid): Result;
}
