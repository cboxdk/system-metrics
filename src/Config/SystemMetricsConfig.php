<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Config;

use Cbox\SystemMetrics\Contracts\ContainerMetricsSource;
use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
use Cbox\SystemMetrics\Contracts\LoadAverageSource;
use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\Contracts\NetworkMetricsSource;
use Cbox\SystemMetrics\Contracts\StorageMetricsSource;
use Cbox\SystemMetrics\Contracts\SystemLimitsSource;
use Cbox\SystemMetrics\Contracts\UptimeSource;
use Cbox\SystemMetrics\Sources\Container\CompositeContainerMetricsSource;
use Cbox\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;
use Cbox\SystemMetrics\Sources\Environment\CompositeEnvironmentDetector;
use Cbox\SystemMetrics\Sources\LoadAverage\CompositeLoadAverageSource;
use Cbox\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;
use Cbox\SystemMetrics\Sources\Network\CompositeNetworkMetricsSource;
use Cbox\SystemMetrics\Sources\Storage\CompositeStorageMetricsSource;
use Cbox\SystemMetrics\Sources\SystemLimits\CompositeSystemLimitsSource;
use Cbox\SystemMetrics\Sources\Uptime\CompositeUptimeSource;

/**
 * Configuration for SystemMetrics with default bindings.
 */
final class SystemMetricsConfig
{
    private static ?EnvironmentDetector $environmentDetector = null;

    private static ?CpuMetricsSource $cpuMetricsSource = null;

    private static ?MemoryMetricsSource $memoryMetricsSource = null;

    private static ?LoadAverageSource $loadAverageSource = null;

    private static ?StorageMetricsSource $storageMetricsSource = null;

    private static ?NetworkMetricsSource $networkMetricsSource = null;

    private static ?UptimeSource $uptimeSource = null;

    private static ?ContainerMetricsSource $containerMetricsSource = null;

    private static ?SystemLimitsSource $systemLimitsSource = null;

    /**
     * Get the configured EnvironmentDetector.
     */
    public static function getEnvironmentDetector(): EnvironmentDetector
    {
        return self::$environmentDetector ?? new CompositeEnvironmentDetector;
    }

    /**
     * Set a custom EnvironmentDetector implementation.
     */
    public static function setEnvironmentDetector(EnvironmentDetector $detector): void
    {
        self::$environmentDetector = $detector;
    }

    /**
     * Get the configured CpuMetricsSource.
     */
    public static function getCpuMetricsSource(): CpuMetricsSource
    {
        return self::$cpuMetricsSource ?? new CompositeCpuMetricsSource;
    }

    /**
     * Set a custom CpuMetricsSource implementation.
     */
    public static function setCpuMetricsSource(CpuMetricsSource $source): void
    {
        self::$cpuMetricsSource = $source;
    }

    /**
     * Get the configured MemoryMetricsSource.
     */
    public static function getMemoryMetricsSource(): MemoryMetricsSource
    {
        return self::$memoryMetricsSource ?? new CompositeMemoryMetricsSource;
    }

    /**
     * Set a custom MemoryMetricsSource implementation.
     */
    public static function setMemoryMetricsSource(MemoryMetricsSource $source): void
    {
        self::$memoryMetricsSource = $source;
    }

    /**
     * Get the configured LoadAverageSource.
     */
    public static function getLoadAverageSource(): LoadAverageSource
    {
        return self::$loadAverageSource ?? new CompositeLoadAverageSource;
    }

    /**
     * Set a custom LoadAverageSource implementation.
     */
    public static function setLoadAverageSource(LoadAverageSource $source): void
    {
        self::$loadAverageSource = $source;
    }

    /**
     * Get the configured StorageMetricsSource.
     */
    public static function getStorageMetricsSource(): StorageMetricsSource
    {
        return self::$storageMetricsSource ?? new CompositeStorageMetricsSource;
    }

    /**
     * Set a custom StorageMetricsSource implementation.
     */
    public static function setStorageMetricsSource(StorageMetricsSource $source): void
    {
        self::$storageMetricsSource = $source;
    }

    /**
     * Get the configured NetworkMetricsSource.
     */
    public static function getNetworkMetricsSource(): NetworkMetricsSource
    {
        return self::$networkMetricsSource ?? new CompositeNetworkMetricsSource;
    }

    /**
     * Set a custom NetworkMetricsSource implementation.
     */
    public static function setNetworkMetricsSource(NetworkMetricsSource $source): void
    {
        self::$networkMetricsSource = $source;
    }

    /**
     * Get the configured UptimeSource.
     */
    public static function getUptimeSource(): UptimeSource
    {
        return self::$uptimeSource ?? new CompositeUptimeSource;
    }

    /**
     * Set a custom UptimeSource implementation.
     */
    public static function setUptimeSource(UptimeSource $source): void
    {
        self::$uptimeSource = $source;
    }

    /**
     * Get the configured ContainerMetricsSource.
     */
    public static function getContainerMetricsSource(): ContainerMetricsSource
    {
        return self::$containerMetricsSource ?? new CompositeContainerMetricsSource;
    }

    /**
     * Set a custom ContainerMetricsSource implementation.
     */
    public static function setContainerMetricsSource(ContainerMetricsSource $source): void
    {
        self::$containerMetricsSource = $source;
    }

    /**
     * Get the configured SystemLimitsSource.
     */
    public static function getSystemLimitsSource(): SystemLimitsSource
    {
        return self::$systemLimitsSource ?? new CompositeSystemLimitsSource;
    }

    /**
     * Set a custom SystemLimitsSource implementation.
     */
    public static function setSystemLimitsSource(SystemLimitsSource $source): void
    {
        self::$systemLimitsSource = $source;
    }

    /**
     * Reset all configuration to defaults.
     */
    public static function reset(): void
    {
        self::$environmentDetector = null;
        self::$cpuMetricsSource = null;
        self::$memoryMetricsSource = null;
        self::$loadAverageSource = null;
        self::$storageMetricsSource = null;
        self::$networkMetricsSource = null;
        self::$uptimeSource = null;
        self::$containerMetricsSource = null;
        self::$systemLimitsSource = null;
    }
}
