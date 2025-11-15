<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Actions;

use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\DTO\SystemOverview;

/**
 * Action to get a complete system overview.
 */
final class SystemOverviewAction
{
    public function __construct(
        private readonly DetectEnvironmentAction $environmentAction,
        private readonly ReadCpuMetricsAction $cpuAction,
        private readonly ReadMemoryMetricsAction $memoryAction,
    ) {}

    /**
     * Execute the system overview collection.
     *
     * @return Result<SystemOverview>
     */
    public function execute(): Result
    {
        $environmentResult = $this->environmentAction->execute();
        if ($environmentResult->isFailure()) {
            return Result::failure($environmentResult->getError());
        }

        $cpuResult = $this->cpuAction->execute();
        if ($cpuResult->isFailure()) {
            return Result::failure($cpuResult->getError());
        }

        $memoryResult = $this->memoryAction->execute();
        if ($memoryResult->isFailure()) {
            return Result::failure($memoryResult->getError());
        }

        return Result::success(new SystemOverview(
            environment: $environmentResult->getValue(),
            cpu: $cpuResult->getValue(),
            memory: $memoryResult->getValue(),
        ));
    }
}
