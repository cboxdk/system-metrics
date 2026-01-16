<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use Cbox\SystemMetrics\DTO\Result;

/**
 * Contract for detecting system environment information.
 */
interface EnvironmentDetector
{
    /**
     * Detect the current system environment.
     *
     * @return Result<EnvironmentSnapshot>
     */
    public function detect(): Result;
}
