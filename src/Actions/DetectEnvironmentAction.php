<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Actions;

use PHPeek\SystemMetrics\Contracts\EnvironmentDetector;
use PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Sources\Environment\CompositeEnvironmentDetector;

/**
 * Action to detect the current system environment.
 */
final class DetectEnvironmentAction
{
    public function __construct(
        private readonly EnvironmentDetector $detector = new CompositeEnvironmentDetector,
    ) {}

    /**
     * Execute the environment detection.
     *
     * @return Result<EnvironmentSnapshot>
     */
    public function execute(): Result
    {
        return $this->detector->detect();
    }
}
