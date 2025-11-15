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
            $error = $environmentResult->getError();
            assert($error !== null);

            return Result::failure($error);
        }

        $cpuResult = $this->cpuAction->execute();
        if ($cpuResult->isFailure()) {
            $error = $cpuResult->getError();
            assert($error !== null);

            return Result::failure($error);
        }

        $memoryResult = $this->memoryAction->execute();
        if ($memoryResult->isFailure()) {
            $error = $memoryResult->getError();
            assert($error !== null);

            return Result::failure($error);
        }

        return Result::success(new SystemOverview(
            environment: $environmentResult->getValue(),
            cpu: $cpuResult->getValue(),
            memory: $memoryResult->getValue(),
        ));
    }
}
