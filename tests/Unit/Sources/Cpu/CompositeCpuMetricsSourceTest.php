<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use PHPeek\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;

describe('CompositeCpuMetricsSource', function () {
    it('creates OS-specific source when none provided', function () {
        $source = new CompositeCpuMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('uses injected source when provided', function () {
        $mockSource = new class implements CpuMetricsSource {
            public function read(): Result
            {
                return Result::success(
                    new CpuSnapshot(
                        total: new CpuTimes(
                            user: 1000,
                            nice: 100,
                            system: 500,
                            idle: 8000,
                            iowait: 200,
                            irq: 50,
                            softirq: 150,
                            steal: 0
                        ),
                        perCore: []
                    )
                );
            }
        };

        $composite = new CompositeCpuMetricsSource($mockSource);
        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(CpuSnapshot::class);
        expect($snapshot->total->user)->toBe(1000);
    });

    it('delegates read to underlying source', function () {
        $mockSource = new class implements CpuMetricsSource {
            public function read(): Result
            {
                return Result::success(
                    new CpuSnapshot(
                        total: new CpuTimes(2000, 200, 1000, 6000, 400, 100, 300, 0),
                        perCore: [
                            new CpuCoreTimes(0, new CpuTimes(500, 50, 250, 1500, 100, 25, 75, 0)),
                            new CpuCoreTimes(1, new CpuTimes(500, 50, 250, 1500, 100, 25, 75, 0)),
                        ]
                    )
                );
            }
        };

        $composite = new CompositeCpuMetricsSource($mockSource);
        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(CpuSnapshot::class);
        expect($result->getValue()->perCore)->toHaveCount(2);
    });

    it('propagates errors from underlying source', function () {
        $mockSource = new class implements CpuMetricsSource {
            public function read(): Result
            {
                return Result::failure(
                    UnsupportedOperatingSystemException::forOs('Windows')
                );
            }
        };

        $composite = new CompositeCpuMetricsSource($mockSource);
        $result = $composite->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(UnsupportedOperatingSystemException::class);
    });
});
