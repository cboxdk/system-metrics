<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Tracking;

use PHPeek\SystemMetrics\Contracts\ProcessMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessDelta;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessResourceUsage;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessStats;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;
use PHPeek\SystemMetrics\Sources\Process\CompositeProcessMetricsSource;

/**
 * Object-oriented process tracker for monitoring resource usage over time.
 *
 * Tracks a single process or process group and collects samples for
 * statistical aggregation (current, peak, average).
 */
final class ProcessTracker
{
    private ?ProcessSnapshot $startSnapshot = null;

    /**
     * @var ProcessSnapshot[]
     */
    private array $samples = [];

    private bool $includeChildren;

    private ProcessMetricsSource $source;

    public function __construct(
        private readonly int $pid,
        bool $includeChildren = false,
        ?ProcessMetricsSource $source = null,
    ) {
        $this->includeChildren = $includeChildren;
        $this->source = $source ?? new CompositeProcessMetricsSource;
    }

    /**
     * Start tracking by capturing initial snapshot.
     *
     * @return Result<ProcessSnapshot>
     */
    public function start(): Result
    {
        if ($this->isTracking()) {
            /** @var Result<ProcessSnapshot> */
            return Result::failure(
                new SystemMetricsException('Tracker is already started')
            );
        }

        $result = $this->captureSnapshot();
        if ($result->isSuccess()) {
            $this->startSnapshot = $result->getValue();
        }

        return $result;
    }

    /**
     * Capture a manual sample for statistical aggregation.
     *
     * @return Result<ProcessSnapshot>
     */
    public function sample(): Result
    {
        if (! $this->isTracking()) {
            /** @var Result<ProcessSnapshot> */
            return Result::failure(
                new SystemMetricsException('Tracker has not been started')
            );
        }

        $result = $this->captureSnapshot();
        if ($result->isSuccess()) {
            $this->samples[] = $result->getValue();
        }

        return $result;
    }

    /**
     * Stop tracking and calculate final statistics.
     *
     * @return Result<ProcessStats>
     */
    public function stop(): Result
    {
        if (! $this->isTracking()) {
            /** @var Result<ProcessStats> */
            return Result::failure(
                new SystemMetricsException('Tracker has not been started')
            );
        }

        // Capture final snapshot
        $endResult = $this->captureSnapshot();
        if ($endResult->isFailure()) {
            $error = $endResult->getError();
            assert($error !== null);

            /** @var Result<ProcessStats> */
            return Result::failure($error);
        }

        $endSnapshot = $endResult->getValue();
        $startSnapshot = $this->startSnapshot;
        assert($startSnapshot !== null);

        // Build all data points: start + samples + end
        $allSnapshots = array_merge(
            [$startSnapshot],
            $this->samples,
            [$endSnapshot]
        );

        // Calculate statistics
        $stats = $this->calculateStats($allSnapshots, $endSnapshot, $startSnapshot);

        // Reset state
        $this->startSnapshot = null;
        $this->samples = [];

        return Result::success($stats);
    }

    /**
     * Get delta between start and current state.
     *
     * @return Result<ProcessDelta>
     */
    public function getDelta(): Result
    {
        if (! $this->isTracking()) {
            /** @var Result<ProcessDelta> */
            return Result::failure(
                new SystemMetricsException('Tracker has not been started')
            );
        }

        $currentResult = $this->captureSnapshot();
        if ($currentResult->isFailure()) {
            $error = $currentResult->getError();
            assert($error !== null);

            /** @var Result<ProcessDelta> */
            return Result::failure($error);
        }

        $current = $currentResult->getValue();
        $start = $this->startSnapshot;
        assert($start !== null);

        $delta = $this->calculateDelta($start, $current);

        return Result::success($delta);
    }

    /**
     * Check if tracker has been started.
     */
    public function isTracking(): bool
    {
        return $this->startSnapshot !== null;
    }

    /**
     * Capture a snapshot (single process or process group).
     *
     * @return Result<ProcessSnapshot>
     */
    private function captureSnapshot(): Result
    {
        if ($this->includeChildren) {
            $groupResult = $this->source->readProcessGroup($this->pid);
            if ($groupResult->isFailure()) {
                $error = $groupResult->getError();
                assert($error !== null);

                /** @var Result<ProcessSnapshot> */
                return Result::failure($error);
            }

            $group = $groupResult->getValue();

            // Aggregate resources from root + children
            $aggregated = $this->aggregateProcessGroup($group);

            return Result::success($aggregated);
        }

        return $this->source->read($this->pid);
    }

    /**
     * Aggregate process group into single snapshot.
     */
    private function aggregateProcessGroup(ProcessGroupSnapshot $group): ProcessSnapshot
    {
        $totalRss = $group->aggregateMemoryRss();
        $totalVms = $group->aggregateMemoryVms();

        // Sum CPU times, threads, and file descriptors from all processes
        $totalUserTime = $group->root->resources->cpuTimes->user;
        $totalSystemTime = $group->root->resources->cpuTimes->system;
        $totalThreads = $group->root->resources->threadCount;
        $totalFds = $group->root->resources->openFileDescriptors;
        $processCount = 1; // Root process

        foreach ($group->children as $child) {
            $totalUserTime += $child->resources->cpuTimes->user;
            $totalSystemTime += $child->resources->cpuTimes->system;
            $totalThreads += $child->resources->threadCount;
            $totalFds += $child->resources->openFileDescriptors;
            $processCount++;
        }

        $aggregatedCpu = new CpuTimes(
            user: $totalUserTime,
            nice: 0,
            system: $totalSystemTime,
            idle: 0,
            iowait: 0,
            irq: 0,
            softirq: 0,
            steal: 0
        );

        $aggregatedResources = new ProcessResourceUsage(
            cpuTimes: $aggregatedCpu,
            memoryRssBytes: $totalRss,
            memoryVmsBytes: $totalVms,
            threadCount: $totalThreads,
            openFileDescriptors: $totalFds,
            processCount: $processCount
        );

        return new ProcessSnapshot(
            pid: $group->rootPid,
            parentPid: $group->root->parentPid,
            resources: $aggregatedResources,
            timestamp: $group->timestamp
        );
    }

    /**
     * Calculate delta between two snapshots.
     */
    private function calculateDelta(ProcessSnapshot $start, ProcessSnapshot $end): ProcessDelta
    {
        $cpuDelta = new CpuTimes(
            user: $end->resources->cpuTimes->user - $start->resources->cpuTimes->user,
            nice: 0,
            system: $end->resources->cpuTimes->system - $start->resources->cpuTimes->system,
            idle: 0,
            iowait: 0,
            irq: 0,
            softirq: 0,
            steal: 0
        );

        $memoryDelta = $end->resources->memoryRssBytes - $start->resources->memoryRssBytes;
        $duration = $end->timestamp->getTimestamp() - $start->timestamp->getTimestamp();

        return new ProcessDelta(
            pid: $this->pid,
            cpuDelta: $cpuDelta,
            memoryDeltaBytes: $memoryDelta,
            durationSeconds: (float) $duration,
            startTime: $start->timestamp,
            endTime: $end->timestamp
        );
    }

    /**
     * Calculate statistics from all snapshots.
     *
     * @param  ProcessSnapshot[]  $snapshots
     */
    private function calculateStats(array $snapshots, ProcessSnapshot $current, ProcessSnapshot $start): ProcessStats
    {
        $count = count($snapshots);

        // Find peak values
        $peakRss = 0;
        $peakVms = 0;
        $peakCpuUser = 0;
        $peakCpuSystem = 0;
        $peakThreads = 0;

        foreach ($snapshots as $snapshot) {
            $peakRss = max($peakRss, $snapshot->resources->memoryRssBytes);
            $peakVms = max($peakVms, $snapshot->resources->memoryVmsBytes);
            $peakCpuUser = max($peakCpuUser, $snapshot->resources->cpuTimes->user);
            $peakCpuSystem = max($peakCpuSystem, $snapshot->resources->cpuTimes->system);
            $peakThreads = max($peakThreads, $snapshot->resources->threadCount);
        }

        // Calculate averages
        $avgRss = 0;
        $avgVms = 0;
        $avgCpuUser = 0;
        $avgCpuSystem = 0;
        $avgThreads = 0;

        foreach ($snapshots as $snapshot) {
            $avgRss += $snapshot->resources->memoryRssBytes;
            $avgVms += $snapshot->resources->memoryVmsBytes;
            $avgCpuUser += $snapshot->resources->cpuTimes->user;
            $avgCpuSystem += $snapshot->resources->cpuTimes->system;
            $avgThreads += $snapshot->resources->threadCount;
        }

        if ($count > 0) {
            $avgRss = (int) ($avgRss / $count);
            $avgVms = (int) ($avgVms / $count);
            $avgCpuUser = (int) ($avgCpuUser / $count);
            $avgCpuSystem = (int) ($avgCpuSystem / $count);
            $avgThreads = (int) ($avgThreads / $count);
        }

        // Calculate total duration
        $first = $snapshots[0];
        $last = $snapshots[$count - 1];
        $duration = $last->timestamp->getTimestamp() - $first->timestamp->getTimestamp();

        // Calculate delta between start and end
        $delta = $this->calculateDelta($start, $current);

        $peakCpu = new CpuTimes(
            user: $peakCpuUser,
            nice: 0,
            system: $peakCpuSystem,
            idle: 0,
            iowait: 0,
            irq: 0,
            softirq: 0,
            steal: 0
        );

        $avgCpu = new CpuTimes(
            user: $avgCpuUser,
            nice: 0,
            system: $avgCpuSystem,
            idle: 0,
            iowait: 0,
            irq: 0,
            softirq: 0,
            steal: 0
        );

        $peakResources = new ProcessResourceUsage(
            cpuTimes: $peakCpu,
            memoryRssBytes: $peakRss,
            memoryVmsBytes: $peakVms,
            threadCount: $peakThreads,
            openFileDescriptors: 0
        );

        $avgResources = new ProcessResourceUsage(
            cpuTimes: $avgCpu,
            memoryRssBytes: $avgRss,
            memoryVmsBytes: $avgVms,
            threadCount: $avgThreads,
            openFileDescriptors: 0
        );

        return new ProcessStats(
            pid: $this->pid,
            current: $current->resources,
            peak: $peakResources,
            average: $avgResources,
            delta: $delta,
            sampleCount: $count,
            totalDurationSeconds: (float) $duration,
            processCount: 1  // For now, always 1 (even if tracking group)
        );
    }
}
