<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Memory;

use FFI;
use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Read memory metrics from FreeBSD using sysctl via FFI.
 *
 * FreeBSD provides memory statistics via sysctl:
 * - hw.physmem: Total physical memory
 * - vm.stats.vm.v_page_count: Total pages
 * - vm.stats.vm.v_free_count: Free pages
 * - vm.stats.vm.v_inactive_count: Inactive pages
 * - vm.stats.vm.v_cache_count: Cached pages
 * - vm.stats.vm.v_wire_count: Wired (kernel) pages
 * - hw.pagesize: Page size in bytes
 *
 * Uses sysctlbyname() C function via FFI for native performance.
 */
final class FreeBSDSysctlMemoryMetricsSource implements MemoryMetricsSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    public function read(): Result
    {
        try {
            $pageSize = $this->readSysctlUint('hw.pagesize');
            $totalPages = $this->readSysctlUint('vm.stats.vm.v_page_count');
            $freePages = $this->readSysctlUint('vm.stats.vm.v_free_count');
            $inactivePages = $this->readSysctlUint('vm.stats.vm.v_inactive_count');
            $cachePages = $this->readSysctlUint('vm.stats.vm.v_cache_count');
            $wiredPages = $this->readSysctlUint('vm.stats.vm.v_wire_count');

            if ($pageSize === null || $totalPages === null) {
                /** @var Result<MemorySnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to read required memory metrics')
                );
            }

            // Calculate bytes
            $totalBytes = $totalPages * $pageSize;
            $freeBytes = ($freePages ?? 0) * $pageSize;
            $inactiveBytes = ($inactivePages ?? 0) * $pageSize;
            $cacheBytes = ($cachePages ?? 0) * $pageSize;

            // Available = free + inactive + cache (similar to Linux)
            $availableBytes = $freeBytes + $inactiveBytes + $cacheBytes;

            // Used = total - available
            $usedBytes = $totalBytes - $availableBytes;

            // Get swap information
            [$swapTotal, $swapUsed] = $this->readSwapInfo();

            return Result::success(new MemorySnapshot(
                totalBytes: $totalBytes,
                availableBytes: $availableBytes,
                usedBytes: $usedBytes,
                freeBytes: $freeBytes,
                buffersBytes: 0, // FreeBSD doesn't separate buffers
                cachedBytes: $cacheBytes,
                swapTotalBytes: $swapTotal,
                swapFreeBytes: $swapTotal - $swapUsed,
                swapUsedBytes: $swapUsed,
            ));

        } catch (\Throwable $e) {
            /** @var Result<MemorySnapshot> */
            return Result::failure(
                new SystemMetricsException(
                    'Failed to read memory metrics via sysctl: '.$e->getMessage(),
                    previous: $e
                )
            );
        }
    }

    /**
     * Read swap information.
     *
     * @return array{int, int} [total, used]
     */
    private function readSwapInfo(): array
    {
        // Try to read swap total and used via sysctl
        $swapTotal = $this->readSysctlUint('vm.swap_total');
        $swapUsed = $this->readSysctlUint('vm.swap_reserved');

        return [
            $swapTotal ?? 0,
            $swapUsed ?? 0,
        ];
    }

    /**
     * Read an unsigned int value from sysctl.
     */
    private function readSysctlUint(string $name): ?int
    {
        $ffi = $this->getFFI();

        $value = $ffi->new('unsigned int');
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($value === null) {
            return null;
        }

        $size = FFI::sizeof($value);
        $sizePtr = $ffi->new('unsigned long');
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($sizePtr === null) {
            return null;
        }

        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $sizePtr->cdata = $size;

        // @phpstan-ignore method.notFound (FFI methods defined via cdef)
        $result = $ffi->sysctlbyname(
            $name,
            FFI::addr($value),
            FFI::addr($sizePtr),
            null,
            0
        );

        if ($result !== 0) {
            return null;
        }

        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        return (int) $value->cdata;
    }

    /**
     * Get or create FFI instance (cached for performance).
     */
    private function getFFI(): FFI
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef('
                // sysctl function for reading kernel state
                int sysctlbyname(
                    const char *name,
                    void *oldp,
                    unsigned long *oldlenp,
                    const void *newp,
                    unsigned long newlen
                );
            ', null); // FreeBSD: libc functions available without explicit library
        }

        return self::$ffi;
    }
}
