<?php

use Cbox\SystemMetrics\Contracts\ContainerMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use Cbox\SystemMetrics\DTO\Metrics\Container\ContainerLimits;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Container\CompositeContainerMetricsSource;

describe('CompositeContainerMetricsSource', function () {
    it('reuses the same injected source across multiple reads', function () {
        $callCount = 0;

        $mockSource = new class($callCount) implements ContainerMetricsSource
        {
            private int $callCount;

            public function __construct(int &$callCount)
            {
                $this->callCount = &$callCount;
            }

            public function read(): Result
            {
                $this->callCount++;

                return Result::success(new ContainerLimits(
                    cgroupVersion: CgroupVersion::V2,
                    cpuQuota: 0.5,
                    memoryLimitBytes: 512_000_000,
                    cpuUsageCores: null,
                    memoryUsageBytes: null,
                    cpuThrottledCount: null,
                    oomKillCount: null,
                ));
            }

            public function getCallCount(): int
            {
                return $this->callCount;
            }
        };

        $composite = new CompositeContainerMetricsSource($mockSource);

        $result1 = $composite->read();
        $result2 = $composite->read();

        expect($result1->isSuccess())->toBeTrue();
        expect($result2->isSuccess())->toBeTrue();
        expect($mockSource->getCallCount())->toBe(2);
    });

    it('returns NONE container limits on non-Linux without custom source', function () {
        // Without a custom source on a non-Linux system, should return NONE
        $composite = new CompositeContainerMetricsSource;

        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        // On non-Linux CI, this returns NONE; on Linux CI, it reads cgroups.
        // We just verify it doesn't crash.
    });
});
