<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Contracts\ProcessMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessResourceUsage;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use PHPeek\SystemMetrics\Sources\Process\CompositeProcessMetricsSource;

describe('CompositeProcessMetricsSource', function () {
    it('creates OS-specific source when none provided', function () {
        $source = new CompositeProcessMetricsSource;

        $result = $source->read(getmypid());

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('uses injected source when provided', function () {
        $mockSource = new class implements ProcessMetricsSource {
            public function read(int $pid): Result
            {
                return Result::success(
                    new ProcessSnapshot(
                        pid: $pid,
                        parentPid: 1,
                        resources: new ProcessResourceUsage(
                            cpuTimes: new CpuTimes(
                                user: 100,
                                nice: 0,
                                system: 50,
                                idle: 0,
                                iowait: 0,
                                irq: 0,
                                softirq: 0,
                                steal: 0
                            ),
                            memoryRssBytes: 1024000,
                            memoryVmsBytes: 2048000,
                            threadCount: 1,
                            openFileDescriptors: 10
                        ),
                        timestamp: new DateTimeImmutable
                    )
                );
            }

            public function readProcessGroup(int $rootPid): Result
            {
                $root = $this->read($rootPid)->getValue();

                return Result::success(
                    new ProcessGroupSnapshot(
                        rootPid: $rootPid,
                        root: $root,
                        children: [],
                        timestamp: new DateTimeImmutable
                    )
                );
            }
        };

        $composite = new CompositeProcessMetricsSource($mockSource);
        $result = $composite->read(1234);

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(ProcessSnapshot::class);
        expect($snapshot->pid)->toBe(1234);
    });

    it('delegates read to underlying source', function () {
        $mockSource = new class implements ProcessMetricsSource {
            public function read(int $pid): Result
            {
                return Result::success(
                    new ProcessSnapshot(
                        pid: $pid,
                        parentPid: 1,
                        resources: new ProcessResourceUsage(
                            cpuTimes: new CpuTimes(100, 0, 50, 0, 0, 0, 0, 0),
                            memoryRssBytes: 1024000,
                            memoryVmsBytes: 2048000,
                            threadCount: 1,
                            openFileDescriptors: 10
                        ),
                        timestamp: new DateTimeImmutable
                    )
                );
            }

            public function readProcessGroup(int $rootPid): Result
            {
                $root = $this->read($rootPid)->getValue();

                return Result::success(
                    new ProcessGroupSnapshot(
                        rootPid: $rootPid,
                        root: $root,
                        children: [],
                        timestamp: new DateTimeImmutable
                    )
                );
            }
        };

        $composite = new CompositeProcessMetricsSource($mockSource);
        $result = $composite->readProcessGroup(1234);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(ProcessGroupSnapshot::class);
    });

    it('propagates errors from underlying source', function () {
        $mockSource = new class implements ProcessMetricsSource {
            public function read(int $pid): Result
            {
                return Result::failure(
                    UnsupportedOperatingSystemException::forOs('Windows')
                );
            }

            public function readProcessGroup(int $rootPid): Result
            {
                return Result::failure(
                    UnsupportedOperatingSystemException::forOs('Windows')
                );
            }
        };

        $composite = new CompositeProcessMetricsSource($mockSource);
        $result = $composite->read(1234);

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(UnsupportedOperatingSystemException::class);
    });
});
