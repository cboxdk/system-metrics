<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\DTO\SystemOverview;

/**
 * Action to get a complete system overview.
 */
final class SystemOverviewAction
{
    public function __construct(
        private readonly DetectEnvironmentAction $environmentAction,
        private readonly ReadCpuMetricsAction $cpuAction,
        private readonly ReadMemoryMetricsAction $memoryAction,
        private readonly ReadStorageMetricsAction $storageAction,
        private readonly ReadNetworkMetricsAction $networkAction,
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

            /** @var Result<SystemOverview> */
            return Result::failure($error);
        }

        $cpuResult = $this->cpuAction->execute();
        if ($cpuResult->isFailure()) {
            $error = $cpuResult->getError();
            assert($error !== null);

            /** @var Result<SystemOverview> */
            return Result::failure($error);
        }

        $memoryResult = $this->memoryAction->execute();
        if ($memoryResult->isFailure()) {
            $error = $memoryResult->getError();
            assert($error !== null);

            /** @var Result<SystemOverview> */
            return Result::failure($error);
        }

        $storageResult = $this->storageAction->execute();
        if ($storageResult->isFailure()) {
            $error = $storageResult->getError();
            assert($error !== null);

            /** @var Result<SystemOverview> */
            return Result::failure($error);
        }

        $networkResult = $this->networkAction->execute();
        if ($networkResult->isFailure()) {
            $error = $networkResult->getError();
            assert($error !== null);

            /** @var Result<SystemOverview> */
            return Result::failure($error);
        }

        return Result::success(new SystemOverview(
            environment: $environmentResult->getValue(),
            cpu: $cpuResult->getValue(),
            memory: $memoryResult->getValue(),
            storage: $storageResult->getValue(),
            network: $networkResult->getValue(),
        ));
    }
}
