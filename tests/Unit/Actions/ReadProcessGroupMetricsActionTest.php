<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Actions\ReadProcessGroupMetricsAction;
use PHPeek\SystemMetrics\Contracts\ProcessMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessResourceUsage;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

// Test double for ProcessMetricsSource
class FakeGroupProcessMetricsSource implements ProcessMetricsSource
{
    public function __construct(
        private bool $shouldSucceed = true,
        private ?int $expectedRootPid = null
    ) {}

    public function read(int $pid): Result
    {
        if (! $this->shouldSucceed) {
            return Result::failure(new SystemMetricsException('Read failed'));
        }

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
        if (! $this->shouldSucceed) {
            return Result::failure(new SystemMetricsException('Group read failed'));
        }

        if ($this->expectedRootPid !== null && $rootPid !== $this->expectedRootPid) {
            return Result::failure(new SystemMetricsException('Unexpected root PID'));
        }

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
}

describe('ReadProcessGroupMetricsAction', function () {
    it('uses default composite source when none provided', function () {
        $action = new ReadProcessGroupMetricsAction;

        $result = $action->execute(getmypid());

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('uses injected source when provided', function () {
        $mockSource = new FakeGroupProcessMetricsSource(shouldSucceed: true, expectedRootPid: 1234);
        $action = new ReadProcessGroupMetricsAction($mockSource);

        $result = $action->execute(1234);

        expect($result->isSuccess())->toBeTrue();
        $group = $result->getValue();
        expect($group)->toBeInstanceOf(ProcessGroupSnapshot::class);
        expect($group->rootPid)->toBe(1234);
    });

    it('delegates readProcessGroup to underlying source', function () {
        $mockSource = new FakeGroupProcessMetricsSource(shouldSucceed: true);
        $action = new ReadProcessGroupMetricsAction($mockSource);

        $result = $action->execute(5678);

        expect($result->isSuccess())->toBeTrue();
        $group = $result->getValue();
        expect($group->rootPid)->toBe(5678);
        expect($group->root)->toBeInstanceOf(ProcessSnapshot::class);
        expect($group->children)->toBeArray();
    });

    it('propagates errors from underlying source', function () {
        $mockSource = new FakeGroupProcessMetricsSource(shouldSucceed: false);
        $action = new ReadProcessGroupMetricsAction($mockSource);

        $result = $action->execute(1234);

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('correctly passes root PID to source', function () {
        $mockSource = new FakeGroupProcessMetricsSource(shouldSucceed: true, expectedRootPid: 9999);
        $action = new ReadProcessGroupMetricsAction($mockSource);

        $result = $action->execute(9999);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue()->rootPid)->toBe(9999);
    });
});
