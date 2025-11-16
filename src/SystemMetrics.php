<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics;

use PHPeek\SystemMetrics\Actions\DetectEnvironmentAction;
use PHPeek\SystemMetrics\Actions\ReadContainerMetricsAction;
use PHPeek\SystemMetrics\Actions\ReadCpuMetricsAction;
use PHPeek\SystemMetrics\Actions\ReadLoadAverageAction;
use PHPeek\SystemMetrics\Actions\ReadMemoryMetricsAction;
use PHPeek\SystemMetrics\Actions\ReadNetworkMetricsAction;
use PHPeek\SystemMetrics\Actions\ReadStorageMetricsAction;
use PHPeek\SystemMetrics\Actions\ReadSystemLimitsAction;
use PHPeek\SystemMetrics\Actions\ReadUptimeAction;
use PHPeek\SystemMetrics\Actions\SystemOverviewAction;
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;
use PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Container\ContainerLimits;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuDelta;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\SystemLimits;
use PHPeek\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\DTO\SystemOverview;

/**
 * Main facade for accessing system metrics.
 */
final class SystemMetrics
{
    /**
     * Detect the current system environment.
     *
     * @return Result<EnvironmentSnapshot>
     */
    public static function environment(): Result
    {
        $action = new DetectEnvironmentAction(
            SystemMetricsConfig::getEnvironmentDetector()
        );

        return $action->execute();
    }

    /**
     * Read CPU metrics.
     *
     * @return Result<CpuSnapshot>
     */
    public static function cpu(): Result
    {
        $action = new ReadCpuMetricsAction(
            SystemMetricsConfig::getCpuMetricsSource()
        );

        return $action->execute();
    }

    /**
     * Read memory metrics.
     *
     * @return Result<MemorySnapshot>
     */
    public static function memory(): Result
    {
        $action = new ReadMemoryMetricsAction(
            SystemMetricsConfig::getMemoryMetricsSource()
        );

        return $action->execute();
    }

    /**
     * Read system load average.
     *
     * @return Result<LoadAverageSnapshot>
     */
    public static function loadAverage(): Result
    {
        $action = new ReadLoadAverageAction;

        return $action->execute();
    }

    /**
     * Read system uptime.
     *
     * @return Result<UptimeSnapshot>
     */
    public static function uptime(): Result
    {
        $action = new ReadUptimeAction;

        return $action->execute();
    }

    /**
     * Read unified system resource limits and current usage.
     *
     * Returns actual limits based on environment:
     * - Container: cgroup limits (respects resource constraints)
     * - Bare metal/VM: host limits (total system resources)
     *
     * Critical for vertical scaling decisions to avoid exceeding limits.
     *
     * @return Result<SystemLimits>
     */
    public static function limits(): Result
    {
        $action = new ReadSystemLimitsAction;

        return $action->execute();
    }

    /**
     * Read storage metrics.
     *
     * @return Result<StorageSnapshot>
     */
    public static function storage(): Result
    {
        $action = new ReadStorageMetricsAction;

        return $action->execute();
    }

    /**
     * Read network metrics.
     *
     * @return Result<NetworkSnapshot>
     */
    public static function network(): Result
    {
        $action = new ReadNetworkMetricsAction;

        return $action->execute();
    }

    /**
     * Read container resource limits and usage (cgroups).
     *
     * @return Result<ContainerLimits>
     */
    public static function container(): Result
    {
        $action = new ReadContainerMetricsAction;

        return $action->execute();
    }

    /**
     * Measure CPU usage percentage over a specific time interval.
     *
     * This is a convenience method that handles the two-snapshot requirement
     * automatically by waiting between measurements.
     *
     * ⚠️  This method blocks execution during the interval. For non-blocking usage,
     * use CpuSnapshot::calculateDelta() with manual snapshots.
     *
     * @param  float  $intervalSeconds  Time to wait between snapshots (default: 1.0, min: 0.1)
     * @return Result<CpuDelta>
     *
     * @example Quick measurement (1 second)
     * ```php
     * $result = SystemMetrics::cpuUsage();
     * $delta = $result->getValue();
     * echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";
     * ```
     *
     * @example Longer measurement (5 seconds, more accurate)
     * ```php
     * $result = SystemMetrics::cpuUsage(5.0);
     * $delta = $result->getValue();
     * echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";
     * echo "Normalized: " . round($delta->normalizedUsagePercentage(), 1) . "%\n";
     * ```
     */
    public static function cpuUsage(float $intervalSeconds = 1.0): Result
    {
        // Ensure minimum interval
        $intervalSeconds = max(0.1, $intervalSeconds);

        // Take first snapshot
        $result1 = self::cpu();
        if ($result1->isFailure()) {
            /** @var Result<CpuDelta> */
            return $result1;
        }

        // Wait for interval
        $microSeconds = (int) ($intervalSeconds * 1_000_000);
        usleep($microSeconds);

        // Take second snapshot
        $result2 = self::cpu();
        if ($result2->isFailure()) {
            /** @var Result<CpuDelta> */
            return $result2;
        }

        // Calculate delta
        $delta = CpuSnapshot::calculateDelta(
            $result1->getValue(),
            $result2->getValue()
        );

        return Result::success($delta);
    }

    /**
     * Get a complete system overview.
     *
     * @return Result<SystemOverview>
     */
    public static function overview(): Result
    {
        $action = new SystemOverviewAction(
            new DetectEnvironmentAction(SystemMetricsConfig::getEnvironmentDetector()),
            new ReadCpuMetricsAction(SystemMetricsConfig::getCpuMetricsSource()),
            new ReadMemoryMetricsAction(SystemMetricsConfig::getMemoryMetricsSource()),
            new ReadStorageMetricsAction,
            new ReadNetworkMetricsAction,
        );

        return $action->execute();
    }
}
