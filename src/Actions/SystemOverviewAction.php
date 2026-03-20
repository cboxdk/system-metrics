<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Actions;

use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\DTO\SystemOverview;

/**
 * Action to get a complete system overview.
 *
 * Core metrics (environment, CPU, memory) are required and will fail the
 * entire action if unavailable. Optional metrics (storage, network,
 * load average, uptime, limits, container) gracefully degrade to null.
 */
final class SystemOverviewAction
{
    public function __construct(
        private readonly DetectEnvironmentAction $environmentAction,
        private readonly ReadCpuMetricsAction $cpuAction,
        private readonly ReadMemoryMetricsAction $memoryAction,
        private readonly ReadStorageMetricsAction $storageAction,
        private readonly ReadNetworkMetricsAction $networkAction,
        private readonly ReadLoadAverageAction $loadAverageAction = new ReadLoadAverageAction,
        private readonly ReadUptimeAction $uptimeAction = new ReadUptimeAction,
        private readonly ReadSystemLimitsAction $limitsAction = new ReadSystemLimitsAction,
        private readonly ReadContainerMetricsAction $containerAction = new ReadContainerMetricsAction,
    ) {}

    /**
     * Execute the system overview collection.
     *
     * @return Result<SystemOverview>
     */
    public function execute(): Result
    {
        // Core metrics — required
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

        // Optional metrics — null on failure
        $storageResult = $this->storageAction->execute();
        $networkResult = $this->networkAction->execute();
        $loadAverageResult = $this->loadAverageAction->execute();
        $uptimeResult = $this->uptimeAction->execute();
        $limitsResult = $this->limitsAction->execute();

        // Only collect container metrics when actually running inside a container
        $environment = $environmentResult->getValue();
        $isContainerized = $environment->containerization->insideContainer;

        $container = null;
        if ($isContainerized) {
            $containerResult = $this->containerAction->execute();
            $container = $containerResult->isSuccess() ? $containerResult->getValue() : null;
        }

        return Result::success(new SystemOverview(
            environment: $environment,
            cpu: $cpuResult->getValue(),
            memory: $memoryResult->getValue(),
            storage: $storageResult->isSuccess() ? $storageResult->getValue() : null,
            network: $networkResult->isSuccess() ? $networkResult->getValue() : null,
            loadAverage: $loadAverageResult->isSuccess() ? $loadAverageResult->getValue() : null,
            uptime: $uptimeResult->isSuccess() ? $uptimeResult->getValue() : null,
            limits: $limitsResult->isSuccess() ? $limitsResult->getValue() : null,
            container: $container,
        ));
    }
}
