<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics;

use PHPeek\SystemMetrics\Actions\DetectEnvironmentAction;
use PHPeek\SystemMetrics\Actions\ReadCpuMetricsAction;
use PHPeek\SystemMetrics\Actions\ReadMemoryMetricsAction;
use PHPeek\SystemMetrics\Actions\SystemOverviewAction;
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;
use PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
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
        );

        return $action->execute();
    }
}
