<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Contracts;

use PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use PHPeek\SystemMetrics\DTO\Result;

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
