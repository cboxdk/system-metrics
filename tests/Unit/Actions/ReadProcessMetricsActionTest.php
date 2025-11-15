<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Actions\ReadProcessMetricsAction;
use PHPeek\SystemMetrics\Contracts\ProcessMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessResourceUsage;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

// Test double for ProcessMetricsSource
class FakeProcessMetricsSourceForAction implements ProcessMetricsSource
{
    public function __construct(
        private bool $shouldSucceed = true,
        private ?int $expectedPid = null
    ) {}

    public function read(int $pid): Result
    {
        if (! $this->shouldSucceed) {
            return Result::failure(new SystemMetricsException('Read failed'));
        }

        if ($this->expectedPid !== null && $pid !== $this->expectedPid) {
            return Result::failure(new SystemMetricsException('Unexpected PID'));
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
        return Result::failure(new SystemMetricsException('Not implemented'));
    }
}

describe('ReadProcessMetricsAction', function () {
    it('uses default composite source when none provided', function () {
        $action = new ReadProcessMetricsAction;

        $result = $action->execute(getmypid());

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('uses injected source when provided', function () {
        $mockSource = new FakeProcessMetricsSourceForAction(shouldSucceed: true, expectedPid: 1234);
        $action = new ReadProcessMetricsAction($mockSource);

        $result = $action->execute(1234);

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(ProcessSnapshot::class);
        expect($snapshot->pid)->toBe(1234);
    });

    it('delegates read to underlying source', function () {
        $mockSource = new FakeProcessMetricsSourceForAction(shouldSucceed: true);
        $action = new ReadProcessMetricsAction($mockSource);

        $result = $action->execute(5678);

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->pid)->toBe(5678);
        expect($snapshot->resources->memoryRssBytes)->toBe(1024000);
    });

    it('propagates errors from underlying source', function () {
        $mockSource = new FakeProcessMetricsSourceForAction(shouldSucceed: false);
        $action = new ReadProcessMetricsAction($mockSource);

        $result = $action->execute(1234);

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('correctly passes PID to source', function () {
        $mockSource = new FakeProcessMetricsSourceForAction(shouldSucceed: true, expectedPid: 9999);
        $action = new ReadProcessMetricsAction($mockSource);

        $result = $action->execute(9999);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue()->pid)->toBe(9999);
    });
});
