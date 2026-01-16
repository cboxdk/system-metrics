<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics;

use Cbox\SystemMetrics\Contracts\ProcessMetricsSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\Sources\Process\CompositeProcessMetricsSource;
use Cbox\SystemMetrics\Tracking\ProcessTracker;

/**
 * Facade for process-level metrics operations.
 *
 * Provides both snapshot-based operations and stateful tracking
 * with automatic ID management for tracking multiple processes.
 */
final class ProcessMetrics
{
    /**
     * @var array<string, ProcessTracker>
     */
    private static array $trackers = [];

    private static ?ProcessMetricsSource $source = null;

    /**
     * Start tracking a process with an optional custom ID.
     *
     * @param  int  $pid  Process ID to track
     * @param  string|null  $trackerId  Custom tracker ID (auto-generated if null)
     * @param  bool  $includeChildren  Track process group (parent + children)
     * @return Result<string> Returns the tracker ID on success
     */
    public static function start(
        int $pid,
        ?string $trackerId = null,
        bool $includeChildren = false
    ): Result {
        $trackerId = $trackerId ?? self::generateTrackerId($pid);

        if (isset(self::$trackers[$trackerId])) {
            /** @var Result<string> */
            return Result::failure(
                new SystemMetricsException("Tracker ID '{$trackerId}' is already in use")
            );
        }

        $tracker = new ProcessTracker($pid, $includeChildren, self::$source);
        $startResult = $tracker->start();

        if ($startResult->isFailure()) {
            $error = $startResult->getError();
            assert($error !== null);

            /** @var Result<string> */
            return Result::failure($error);
        }

        self::$trackers[$trackerId] = $tracker;

        return Result::success($trackerId);
    }

    /**
     * Stop tracking and calculate final statistics.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessStats>
     */
    public static function stop(string $trackerId): Result
    {
        if (! isset(self::$trackers[$trackerId])) {
            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessStats> */
            return Result::failure(
                new SystemMetricsException("Tracker ID '{$trackerId}' not found")
            );
        }

        $tracker = self::$trackers[$trackerId];
        $result = $tracker->stop();

        // Remove tracker after stopping
        unset(self::$trackers[$trackerId]);

        return $result;
    }

    /**
     * Capture a manual sample for a tracked process.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot>
     */
    public static function sample(string $trackerId): Result
    {
        if (! isset(self::$trackers[$trackerId])) {
            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot> */
            return Result::failure(
                new SystemMetricsException("Tracker ID '{$trackerId}' not found")
            );
        }

        return self::$trackers[$trackerId]->sample();
    }

    /**
     * Get delta between start and current state for a tracked process.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessDelta>
     */
    public static function delta(string $trackerId): Result
    {
        if (! isset(self::$trackers[$trackerId])) {
            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessDelta> */
            return Result::failure(
                new SystemMetricsException("Tracker ID '{$trackerId}' not found")
            );
        }

        return self::$trackers[$trackerId]->getDelta();
    }

    /**
     * Get a one-time snapshot of a process (no tracking).
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot>
     */
    public static function snapshot(int $pid): Result
    {
        $source = self::$source ?? new CompositeProcessMetricsSource;

        return $source->read($pid);
    }

    /**
     * Get a snapshot of a process group (parent + children).
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot>
     */
    public static function group(int $rootPid): Result
    {
        $source = self::$source ?? new CompositeProcessMetricsSource;

        return $source->readProcessGroup($rootPid);
    }

    /**
     * Get a snapshot of the current PHP process.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot>
     */
    public static function current(): Result
    {
        $pid = getmypid();
        if ($pid === false) {
            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot> */
            return Result::failure(
                new SystemMetricsException('Unable to get current process ID')
            );
        }

        return self::snapshot($pid);
    }

    /**
     * Set a custom process metrics source for all operations.
     */
    public static function setSource(?ProcessMetricsSource $source): void
    {
        self::$source = $source;
    }

    /**
     * Generate a unique tracker ID.
     */
    private static function generateTrackerId(int $pid): string
    {
        return "process_{$pid}_".uniqid();
    }

    /**
     * Get all active tracker IDs (for testing/debugging).
     *
     * @return string[]
     */
    public static function activeTrackers(): array
    {
        return array_keys(self::$trackers);
    }

    /**
     * Clear all trackers (for testing).
     *
     * @internal
     */
    public static function clearTrackers(): void
    {
        self::$trackers = [];
    }
}
