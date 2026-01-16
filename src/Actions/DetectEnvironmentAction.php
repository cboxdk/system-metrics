<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
use Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Environment\CompositeEnvironmentDetector;

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
