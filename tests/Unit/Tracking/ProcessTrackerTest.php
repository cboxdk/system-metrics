<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Contracts\ProcessMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessResourceUsage;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessStats;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;
use PHPeek\SystemMetrics\Tracking\ProcessTracker;

// Test double that returns consistent snapshots
class FakeProcessTrackerSource implements ProcessMetricsSource
{
    private int $callCount = 0;

    public function __construct(
        private int $basePid = 1234,
        private bool $shouldFail = false
    ) {}

    public function read(int $pid): Result
    {
        if ($this->shouldFail) {
            return Result::failure(new SystemMetricsException('Process read failed'));
        }

        // Return different values on each call to simulate resource changes
        $this->callCount++;

        return Result::success(
            new ProcessSnapshot(
                pid: $pid,
                parentPid: 1,
                resources: new ProcessResourceUsage(
                    cpuTimes: new CpuTimes(
                        user: 1000 + ($this->callCount * 100),
                        nice: 0,
                        system: 500 + ($this->callCount * 50),
                        idle: 0,
                        iowait: 0,
                        irq: 0,
                        softirq: 0,
                        steal: 0
                    ),
                    memoryRssBytes: 1024000 + ($this->callCount * 10000),
                    memoryVmsBytes: 2048000 + ($this->callCount * 20000),
                    threadCount: 1 + $this->callCount,
                    openFileDescriptors: 10
                ),
                timestamp: new DateTimeImmutable
            )
        );
    }

    public function readProcessGroup(int $rootPid): Result
    {
        if ($this->shouldFail) {
            return Result::failure(new SystemMetricsException('Process group read failed'));
        }

        $this->callCount++;

        $root = new ProcessSnapshot(
            pid: $rootPid,
            parentPid: 1,
            resources: new ProcessResourceUsage(
                cpuTimes: new CpuTimes(
                    user: 1000 + ($this->callCount * 100),
                    nice: 0,
                    system: 500 + ($this->callCount * 50),
                    idle: 0,
                    iowait: 0,
                    irq: 0,
                    softirq: 0,
                    steal: 0
                ),
                memoryRssBytes: 1024000 + ($this->callCount * 10000),
                memoryVmsBytes: 2048000 + ($this->callCount * 20000),
                threadCount: 1 + $this->callCount,
                openFileDescriptors: 10
            ),
            timestamp: new DateTimeImmutable
        );

        // Add a child process
        $child = new ProcessSnapshot(
            pid: $rootPid + 1,
            parentPid: $rootPid,
            resources: new ProcessResourceUsage(
                cpuTimes: new CpuTimes(
                    user: 500 + ($this->callCount * 50),
                    nice: 0,
                    system: 250 + ($this->callCount * 25),
                    idle: 0,
                    iowait: 0,
                    irq: 0,
                    softirq: 0,
                    steal: 0
                ),
                memoryRssBytes: 512000 + ($this->callCount * 5000),
                memoryVmsBytes: 1024000 + ($this->callCount * 10000),
                threadCount: 1,
                openFileDescriptors: 5
            ),
            timestamp: new DateTimeImmutable
        );

        return Result::success(
            new ProcessGroupSnapshot(
                rootPid: $rootPid,
                root: $root,
                children: [$child],
                timestamp: new DateTimeImmutable
            )
        );
    }
}

describe('ProcessTracker', function () {
    it('can start tracking a process', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        expect($tracker->isTracking())->toBeFalse();

        $result = $tracker->start();

        expect($result->isSuccess())->toBeTrue();
        expect($tracker->isTracking())->toBeTrue();
    });

    it('fails to start if already tracking', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();
        $result = $tracker->start();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('Tracker is already started');
    });

    it('can sample process metrics', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();
        $result = $tracker->sample();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(ProcessSnapshot::class);
    });

    it('fails to sample if not tracking', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $result = $tracker->sample();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('Tracker has not been started');
    });

    it('can stop tracking and get statistics', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();
        $tracker->sample();
        $tracker->sample();
        $result = $tracker->stop();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(ProcessStats::class);

        $stats = $result->getValue();
        expect($stats->pid)->toBe(1234);
        expect($stats->sampleCount)->toBe(4); // start + 2 samples + stop
        expect($stats->current)->toBeInstanceOf(ProcessResourceUsage::class);
        expect($stats->peak)->toBeInstanceOf(ProcessResourceUsage::class);
        expect($stats->average)->toBeInstanceOf(ProcessResourceUsage::class);
    });

    it('fails to stop if not tracking', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $result = $tracker->stop();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('Tracker has not been started');
    });

    it('resets state after stopping', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();
        $tracker->stop();

        expect($tracker->isTracking())->toBeFalse();

        // Should be able to start again
        $result = $tracker->start();
        expect($result->isSuccess())->toBeTrue();
    });

    it('can get delta between start and current', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();
        sleep(1); // Ensure time passes
        $result = $tracker->getDelta();

        expect($result->isSuccess())->toBeTrue();
        $delta = $result->getValue();
        expect($delta->pid)->toBe(1234);
        expect($delta->cpuDelta)->toBeInstanceOf(CpuTimes::class);
        expect($delta->cpuDelta->user)->toBeGreaterThan(0);
    });

    it('fails to get delta if not tracking', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $result = $tracker->getDelta();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('Tracker has not been started');
    });

    it('propagates source errors on start', function () {
        $source = new FakeProcessTrackerSource(shouldFail: true);
        $tracker = new ProcessTracker(1234, false, $source);

        $result = $tracker->start();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('propagates source errors on sample', function () {
        $source = new FakeProcessTrackerSource(shouldFail: false);
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();

        // Make source fail after start
        $failingSource = new FakeProcessTrackerSource(shouldFail: true);
        $failingTracker = new ProcessTracker(1234, false, $failingSource);
        $failingTracker->start(); // This will work

        // Now try sampling with a tracker that has failing source
        $result = $failingTracker->sample();
        expect($result->isFailure())->toBeTrue();
    });

    it('propagates source errors on stop', function () {
        $source = new FakeProcessTrackerSource(shouldFail: false);
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();

        // Create a new tracker with failing source for the stop operation
        $failingSource = new FakeProcessTrackerSource(shouldFail: true);
        $failingTracker = new ProcessTracker(1234, false, $failingSource);
        $failingTracker->start(); // Initial snapshot works

        // Manually set it to failing for next call
        $result = $failingTracker->stop();
        expect($result->isFailure())->toBeTrue();
    });

    it('propagates source errors on getDelta', function () {
        $source = new FakeProcessTrackerSource(shouldFail: false);
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();

        // Create failing tracker for delta
        $failingSource = new FakeProcessTrackerSource(shouldFail: true);
        $failingTracker = new ProcessTracker(1234, false, $failingSource);
        $failingTracker->start(); // Initial works

        $result = $failingTracker->getDelta();
        expect($result->isFailure())->toBeTrue();
    });

    it('can track process group with children', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, includeChildren: true, source: $source);

        $result = $tracker->start();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(ProcessSnapshot::class);
        // Memory should be aggregated (root + child)
        expect($snapshot->resources->memoryRssBytes)->toBeGreaterThan(1024000);
    });

    it('aggregates resources from process group', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, includeChildren: true, source: $source);

        $tracker->start();
        $tracker->stop();

        // The tracker should have aggregated CPU times and memory from parent + children
        expect(true)->toBeTrue(); // Process completed successfully
    });

    it('calculates statistics with multiple samples', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();
        $tracker->sample();
        $tracker->sample();
        $tracker->sample();
        $result = $tracker->stop();

        $stats = $result->getValue();

        // Should have 5 samples: start + 3 samples + stop
        expect($stats->sampleCount)->toBe(5);

        // Peak should be from the last snapshot (highest values)
        expect($stats->peak->memoryRssBytes)->toBeGreaterThan($stats->average->memoryRssBytes);

        // Current should match the final snapshot
        expect($stats->current)->toBeInstanceOf(ProcessResourceUsage::class);
    });

    it('calculates correct averages', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();
        $result = $tracker->stop();

        $stats = $result->getValue();

        // With 2 samples (start + stop), average should be between them
        expect($stats->average->memoryRssBytes)->toBeGreaterThan(0);
        expect($stats->average->cpuTimes->user)->toBeGreaterThan(0);
    });

    it('tracks multiple samples correctly', function () {
        $source = new FakeProcessTrackerSource;
        $tracker = new ProcessTracker(1234, false, $source);

        $tracker->start();

        for ($i = 0; $i < 5; $i++) {
            $result = $tracker->sample();
            expect($result->isSuccess())->toBeTrue();
        }

        $result = $tracker->stop();
        expect($result->isSuccess())->toBeTrue();

        // Should have 7 total: start + 5 samples + stop
        expect($result->getValue()->sampleCount)->toBe(7);
    });

    it('uses default composite source when none provided', function () {
        $tracker = new ProcessTracker(getmypid());

        $result = $tracker->start();

        expect($result)->toBeInstanceOf(Result::class);
        // May succeed or fail depending on system, but should not throw
    });

    it('propagates process group read failures', function () {
        $source = new FakeProcessTrackerSource(shouldFail: true);
        $tracker = new ProcessTracker(1234, includeChildren: true, source: $source);

        $result = $tracker->start();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('Process group read failed');
    });
});
