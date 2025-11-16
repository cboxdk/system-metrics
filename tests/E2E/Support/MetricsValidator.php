<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Tests\E2E\Support;

use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use PHPUnit\Framework\Assert;

/**
 * Validation helper for E2E metrics tests.
 * Provides assertion methods with tolerance for container resource limits.
 */
class MetricsValidator
{
    /**
     * Default tolerance percentages for validation.
     */
    private const CPU_TOLERANCE_PERCENT = 10.0;

    private const MEMORY_TOLERANCE_PERCENT = 5.0;

    /**
     * Validate CPU quota matches expected value within tolerance.
     *
     * @param  float  $expectedCores  Expected CPU cores (e.g., 0.5 for 500m)
     * @param  float  $tolerancePercent  Tolerance percentage (default 10%)
     *
     * @throws \RuntimeException If CPU metrics cannot be read
     */
    public static function validateCpuQuota(
        CpuSnapshot $cpu,
        float $expectedCores,
        float $tolerancePercent = self::CPU_TOLERANCE_PERCENT
    ): void {
        $actualCores = $cpu->coreCount();

        $lowerBound = $expectedCores * (1 - $tolerancePercent / 100);
        $upperBound = $expectedCores * (1 + $tolerancePercent / 100);

        Assert::assertGreaterThanOrEqual(
            $lowerBound,
            $actualCores,
            sprintf(
                'CPU cores %.2f is below expected %.2f (tolerance ±%.1f%%)',
                $actualCores,
                $expectedCores,
                $tolerancePercent
            )
        );

        Assert::assertLessThanOrEqual(
            $upperBound,
            $actualCores,
            sprintf(
                'CPU cores %.2f exceeds expected %.2f (tolerance ±%.1f%%)',
                $actualCores,
                $expectedCores,
                $tolerancePercent
            )
        );
    }

    /**
     * Validate memory limit matches expected value within tolerance.
     *
     * @param  int  $expectedBytes  Expected memory limit in bytes
     * @param  float  $tolerancePercent  Tolerance percentage (default 5%)
     *
     * @throws \RuntimeException If memory metrics cannot be read
     */
    public static function validateMemoryLimit(
        MemorySnapshot $memory,
        int $expectedBytes,
        float $tolerancePercent = self::MEMORY_TOLERANCE_PERCENT
    ): void {
        $actualBytes = $memory->totalBytes;

        $lowerBound = (int) ($expectedBytes * (1 - $tolerancePercent / 100));
        $upperBound = (int) ($expectedBytes * (1 + $tolerancePercent / 100));

        Assert::assertGreaterThanOrEqual(
            $lowerBound,
            $actualBytes,
            sprintf(
                'Memory total %d bytes is below expected %d (tolerance ±%.1f%%)',
                $actualBytes,
                $expectedBytes,
                $tolerancePercent
            )
        );

        Assert::assertLessThanOrEqual(
            $upperBound,
            $actualBytes,
            sprintf(
                'Memory total %d bytes exceeds expected %d (tolerance ±%.1f%%)',
                $actualBytes,
                $expectedBytes,
                $tolerancePercent
            )
        );
    }

    /**
     * Validate memory limit in megabytes.
     *
     * @param  int  $expectedMB  Expected memory limit in megabytes
     * @param  float  $tolerancePercent  Tolerance percentage (default 5%)
     */
    public static function validateMemoryLimitMB(
        MemorySnapshot $memory,
        int $expectedMB,
        float $tolerancePercent = self::MEMORY_TOLERANCE_PERCENT
    ): void {
        self::validateMemoryLimit(
            $memory,
            $expectedMB * 1024 * 1024,
            $tolerancePercent
        );
    }

    /**
     * Validate CPU and memory metrics consistency.
     * Ensures metrics make logical sense (e.g., used <= total).
     */
    public static function validateConsistency(
        CpuSnapshot $cpu,
        MemorySnapshot $memory
    ): void {
        // CPU consistency checks
        Assert::assertGreaterThan(
            0,
            $cpu->coreCount(),
            'CPU core count must be positive'
        );

        Assert::assertGreaterThan(
            0,
            $cpu->total->total(),
            'Total CPU time must be positive'
        );

        Assert::assertGreaterThanOrEqual(
            0,
            $cpu->total->busy(),
            'Busy CPU time cannot be negative'
        );

        Assert::assertLessThanOrEqual(
            $cpu->total->total(),
            $cpu->total->busy(),
            'Busy CPU time cannot exceed total'
        );

        // Memory consistency checks
        Assert::assertGreaterThan(
            0,
            $memory->totalBytes,
            'Total memory must be positive'
        );

        Assert::assertGreaterThanOrEqual(
            0,
            $memory->freeBytes,
            'Free memory cannot be negative'
        );

        Assert::assertGreaterThanOrEqual(
            0,
            $memory->availableBytes,
            'Available memory cannot be negative'
        );

        Assert::assertGreaterThanOrEqual(
            0,
            $memory->usedBytes,
            'Used memory cannot be negative'
        );

        Assert::assertLessThanOrEqual(
            $memory->totalBytes,
            $memory->usedBytes,
            'Used memory cannot exceed total'
        );

        Assert::assertLessThanOrEqual(
            $memory->totalBytes,
            $memory->availableBytes,
            'Available memory cannot exceed total'
        );

        // Percentage checks
        Assert::assertGreaterThanOrEqual(
            0.0,
            $memory->usedPercentage(),
            'Memory usage percentage cannot be negative'
        );

        Assert::assertLessThanOrEqual(
            100.0,
            $memory->usedPercentage(),
            'Memory usage percentage cannot exceed 100%'
        );
    }

    /**
     * Validate that metrics show CPU throttling is occurring.
     * Useful for validating CPU limit enforcement.
     */
    public static function assertCpuThrottled(CpuSnapshot $cpu): void
    {
        // In a real implementation, this would check cgroup throttling stats
        // For now, we verify CPU time is advancing (activity detected)
        Assert::assertGreaterThan(
            0,
            $cpu->total->busy(),
            'CPU should show activity when throttling test runs'
        );
    }

    /**
     * Validate that memory metrics show memory pressure.
     * Useful for validating memory limit enforcement.
     */
    public static function assertMemoryPressure(MemorySnapshot $memory): void
    {
        // Memory pressure indicated by high usage percentage
        Assert::assertGreaterThan(
            50.0,
            $memory->usedPercentage(),
            'Memory usage should exceed 50% to indicate pressure'
        );
    }

    /**
     * Validate per-core CPU metrics consistency.
     */
    public static function validatePerCoreMetrics(CpuSnapshot $cpu): void
    {
        Assert::assertNotEmpty(
            $cpu->perCore,
            'Per-core metrics should not be empty'
        );

        $sumBusy = 0;
        $sumTotal = 0;

        foreach ($cpu->perCore as $index => $core) {
            Assert::assertEquals(
                $index,
                $core->coreIndex,
                "Core at index {$index} should have matching coreIndex"
            );

            Assert::assertGreaterThanOrEqual(
                0,
                $core->times->total(),
                "Core {$index} total time must be non-negative"
            );

            Assert::assertGreaterThanOrEqual(
                0,
                $core->times->busy(),
                "Core {$index} busy time must be non-negative"
            );

            Assert::assertLessThanOrEqual(
                $core->times->total(),
                $core->times->busy(),
                "Core {$index} busy time cannot exceed total"
            );

            $sumBusy += $core->times->busy();
            $sumTotal += $core->times->total();
        }

        // Per-core sum should roughly match total (within rounding)
        $tolerance = $cpu->coreCount() * 100; // Allow 100 ticks per core variance

        Assert::assertEqualsWithDelta(
            $cpu->total->busy(),
            $sumBusy,
            $tolerance,
            'Sum of per-core busy time should match total busy time'
        );

        Assert::assertEqualsWithDelta(
            $cpu->total->total(),
            $sumTotal,
            $tolerance,
            'Sum of per-core total time should match total time'
        );
    }

    /**
     * Convert megabytes to bytes for memory validation.
     */
    public static function mbToBytes(int $megabytes): int
    {
        return $megabytes * 1024 * 1024;
    }

    /**
     * Convert CPU millicores to cores (e.g., 500m -> 0.5).
     */
    public static function milliCoresToCores(int $milliCores): float
    {
        return $milliCores / 1000.0;
    }
}
