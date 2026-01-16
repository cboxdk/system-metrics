<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Config;

use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;
use Cbox\SystemMetrics\Sources\Environment\CompositeEnvironmentDetector;
use Cbox\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;

/**
 * Configuration for SystemMetrics with default bindings.
 */
final class SystemMetricsConfig
{
    private static ?EnvironmentDetector $environmentDetector = null;

    private static ?CpuMetricsSource $cpuMetricsSource = null;

    private static ?MemoryMetricsSource $memoryMetricsSource = null;

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
     * Reset all configuration to defaults.
     */
    public static function reset(): void
    {
        self::$environmentDetector = null;
        self::$cpuMetricsSource = null;
        self::$memoryMetricsSource = null;
    }
}
