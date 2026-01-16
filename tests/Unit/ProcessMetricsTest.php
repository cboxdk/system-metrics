<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Contracts\ProcessMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessResourceUsage;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\ProcessMetrics;

// Test double for ProcessMetricsSource
class FakeProcessMetricsSource implements ProcessMetricsSource
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
        if (! $this->shouldSucceed) {
            return Result::failure(new SystemMetricsException('Group read failed'));
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

describe('ProcessMetrics', function () {
    beforeEach(function () {
        ProcessMetrics::clearTrackers();
        ProcessMetrics::setSource(null);
    });

    afterEach(function () {
        ProcessMetrics::clearTrackers();
        ProcessMetrics::setSource(null);
    });

    it('can get snapshot of a process', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: true, expectedPid: 1234);
        ProcessMetrics::setSource($source);

        $result = ProcessMetrics::snapshot(1234);

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(ProcessSnapshot::class);
        expect($snapshot->pid)->toBe(1234);
    });

    it('can get snapshot of current process', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: true);
        ProcessMetrics::setSource($source);

        $result = ProcessMetrics::current();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(ProcessSnapshot::class);
    });

    it('can get process group snapshot', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: true);
        ProcessMetrics::setSource($source);

        $result = ProcessMetrics::group(1234);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(ProcessGroupSnapshot::class);
    });

    it('propagates errors from source on snapshot', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: false);
        ProcessMetrics::setSource($source);

        $result = ProcessMetrics::snapshot(1234);

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('propagates errors from source on group', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: false);
        ProcessMetrics::setSource($source);

        $result = ProcessMetrics::group(1234);

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('can start tracking a process', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: true);
        ProcessMetrics::setSource($source);

        $result = ProcessMetrics::start(1234);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeString();
        expect(ProcessMetrics::activeTrackers())->toHaveCount(1);
    });

    it('can start tracking with custom tracker ID', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: true);
        ProcessMetrics::setSource($source);

        $result = ProcessMetrics::start(1234, 'custom-id');

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBe('custom-id');
        expect(ProcessMetrics::activeTrackers())->toContain('custom-id');
    });

    it('fails to start with duplicate tracker ID', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: true);
        ProcessMetrics::setSource($source);

        ProcessMetrics::start(1234, 'duplicate-id');
        $result = ProcessMetrics::start(1234, 'duplicate-id');

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('fails to sample non-existent tracker', function () {
        $result = ProcessMetrics::sample('non-existent');

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('fails to get delta for non-existent tracker', function () {
        $result = ProcessMetrics::delta('non-existent');

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('fails to stop non-existent tracker', function () {
        $result = ProcessMetrics::stop('non-existent');

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('removes tracker after stopping', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: true);
        ProcessMetrics::setSource($source);

        $startResult = ProcessMetrics::start(1234);
        $trackerId = $startResult->getValue();

        expect(ProcessMetrics::activeTrackers())->toHaveCount(1);

        ProcessMetrics::stop($trackerId);

        expect(ProcessMetrics::activeTrackers())->toHaveCount(0);
    });

    it('tracks multiple processes independently', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: true);
        ProcessMetrics::setSource($source);

        ProcessMetrics::start(1234, 'tracker-1');
        ProcessMetrics::start(5678, 'tracker-2');

        expect(ProcessMetrics::activeTrackers())->toHaveCount(2);
        expect(ProcessMetrics::activeTrackers())->toContain('tracker-1', 'tracker-2');
    });

    it('propagates tracker start failure', function () {
        $source = new FakeProcessMetricsSource(shouldSucceed: false);
        ProcessMetrics::setSource($source);

        $result = ProcessMetrics::start(1234);

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('propagates tracker sample failure', function () {
        // Use a failing source that succeeds on start but fails on subsequent reads
        $source = new class implements ProcessMetricsSource
        {
            private int $callCount = 0;

            public function read(int $pid): Result
            {
                $this->callCount++;
                // First call succeeds (for start), subsequent calls fail
                if ($this->callCount === 1) {
                    return Result::success(
                        new ProcessSnapshot(
                            pid: $pid,
                            parentPid: 1,
                            resources: new ProcessResourceUsage(
                                cpuTimes: new CpuTimes(1000, 0, 500, 0, 0, 0, 0, 0),
                                memoryRssBytes: 1024000,
                                memoryVmsBytes: 2048000,
                                threadCount: 1,
                                openFileDescriptors: 10
                            ),
                            timestamp: new DateTimeImmutable
                        )
                    );
                }

                return Result::failure(new SystemMetricsException('Sample failed'));
            }

            public function readProcessGroup(int $rootPid): Result
            {
                return Result::failure(new SystemMetricsException('Not implemented'));
            }
        };

        ProcessMetrics::setSource($source);
        $result = ProcessMetrics::start(1234);
        $trackerId = $result->getValue();

        $sampleResult = ProcessMetrics::sample($trackerId);

        expect($sampleResult->isFailure())->toBeTrue();
        expect($sampleResult->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('propagates tracker delta failure', function () {
        // Use a failing source that succeeds on start but fails on subsequent reads
        $source = new class implements ProcessMetricsSource
        {
            private int $callCount = 0;

            public function read(int $pid): Result
            {
                $this->callCount++;
                // First call succeeds (for start), subsequent calls fail
                if ($this->callCount === 1) {
                    return Result::success(
                        new ProcessSnapshot(
                            pid: $pid,
                            parentPid: 1,
                            resources: new ProcessResourceUsage(
                                cpuTimes: new CpuTimes(1000, 0, 500, 0, 0, 0, 0, 0),
                                memoryRssBytes: 1024000,
                                memoryVmsBytes: 2048000,
                                threadCount: 1,
                                openFileDescriptors: 10
                            ),
                            timestamp: new DateTimeImmutable
                        )
                    );
                }

                return Result::failure(new SystemMetricsException('Delta failed'));
            }

            public function readProcessGroup(int $rootPid): Result
            {
                return Result::failure(new SystemMetricsException('Not implemented'));
            }
        };

        ProcessMetrics::setSource($source);
        $result = ProcessMetrics::start(1234);
        $trackerId = $result->getValue();

        $deltaResult = ProcessMetrics::delta($trackerId);

        expect($deltaResult->isFailure())->toBeTrue();
        expect($deltaResult->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('propagates tracker stop failure', function () {
        // Use a failing source that succeeds on start but fails on subsequent reads
        $source = new class implements ProcessMetricsSource
        {
            private int $callCount = 0;

            public function read(int $pid): Result
            {
                $this->callCount++;
                // First call succeeds (for start), subsequent calls fail
                if ($this->callCount === 1) {
                    return Result::success(
                        new ProcessSnapshot(
                            pid: $pid,
                            parentPid: 1,
                            resources: new ProcessResourceUsage(
                                cpuTimes: new CpuTimes(1000, 0, 500, 0, 0, 0, 0, 0),
                                memoryRssBytes: 1024000,
                                memoryVmsBytes: 2048000,
                                threadCount: 1,
                                openFileDescriptors: 10
                            ),
                            timestamp: new DateTimeImmutable
                        )
                    );
                }

                return Result::failure(new SystemMetricsException('Stop failed'));
            }

            public function readProcessGroup(int $rootPid): Result
            {
                return Result::failure(new SystemMetricsException('Not implemented'));
            }
        };

        ProcessMetrics::setSource($source);
        $result = ProcessMetrics::start(1234);
        $trackerId = $result->getValue();

        $stopResult = ProcessMetrics::stop($trackerId);

        expect($stopResult->isFailure())->toBeTrue();
        expect($stopResult->getError())->toBeInstanceOf(SystemMetricsException::class);
    });
});
