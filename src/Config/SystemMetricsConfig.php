<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Config;

use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\Contracts\EnvironmentDetector;
use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;
use PHPeek\SystemMetrics\Sources\Environment\CompositeEnvironmentDetector;
use PHPeek\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;

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
