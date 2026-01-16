<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Memory;

use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use FFI;

/**
 * Read memory metrics from Windows using GlobalMemoryStatusEx() via FFI.
 *
 * This is the preferred method for Windows systems as it provides:
 * - ⚡ Fast performance (direct syscalls vs WMI queries)
 * - 📊 Complete memory information in single call
 * - 🔒 Native Win32 API access
 *
 * Requires PHP FFI extension (enabled by default in PHP 7.4+).
 */
final class WindowsFFIMemoryMetricsSource implements MemoryMetricsSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    public function read(): Result
    {
        if (! extension_loaded('ffi')) {
            /** @var Result<MemorySnapshot> */
            return Result::failure(
                new SystemMetricsException('FFI extension not available')
            );
        }

        try {
            $ffi = $this->getFFI();

            // Initialize MEMORYSTATUSEX structure
            $memStatus = $ffi->new('MEMORYSTATUSEX');
            // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
            $memStatus->dwLength = FFI::sizeof($memStatus);

            // Call GlobalMemoryStatusEx
            $result = $ffi->GlobalMemoryStatusEx(FFI::addr($memStatus)); // @phpstan-ignore method.notFound (FFI methods defined via cdef)

            if ($result === 0) {
                /** @var Result<MemorySnapshot> */
                return Result::failure(
                    new SystemMetricsException('GlobalMemoryStatusEx failed')
                );
            }

            // Extract memory values (all in bytes)
            // @phpstan-ignore property.notFound
            $totalBytes = (int) $memStatus->ullTotalPhys;
            // @phpstan-ignore property.notFound
            $availableBytes = (int) $memStatus->ullAvailPhys;
            $usedBytes = $totalBytes - $availableBytes;

            // Virtual memory (page file)
            // @phpstan-ignore property.notFound
            $swapTotalBytes = (int) $memStatus->ullTotalPageFile - $totalBytes;
            // @phpstan-ignore property.notFound
            $swapFreeBytes = (int) $memStatus->ullAvailPageFile - $availableBytes;
            $swapUsedBytes = $swapTotalBytes - $swapFreeBytes;

            // Windows doesn't expose buffer/cache separately like Linux
            // Available memory already accounts for cache that can be freed
            $freeBytes = $availableBytes; // Conservative estimate
            $buffersBytes = 0;
            $cachedBytes = 0;

            return Result::success(new MemorySnapshot(
                totalBytes: $totalBytes,
                freeBytes: $freeBytes,
                availableBytes: $availableBytes,
                usedBytes: $usedBytes,
                buffersBytes: $buffersBytes,
                cachedBytes: $cachedBytes,
                swapTotalBytes: $swapTotalBytes,
                swapUsedBytes: $swapUsedBytes,
                swapFreeBytes: $swapFreeBytes
            ));

        } catch (\Throwable $e) {
            /** @var Result<MemorySnapshot> */
            return Result::failure(
                new SystemMetricsException(
                    'Failed to read memory metrics via FFI: '.$e->getMessage(),
                    previous: $e
                )
            );
        }
    }

    /**
     * Get or create FFI instance (cached for performance).
     */
    private function getFFI(): FFI
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef('
                // MEMORYSTATUSEX structure
                typedef struct {
                    unsigned int dwLength;
                    unsigned int dwMemoryLoad;
                    unsigned long long ullTotalPhys;
                    unsigned long long ullAvailPhys;
                    unsigned long long ullTotalPageFile;
                    unsigned long long ullAvailPageFile;
                    unsigned long long ullTotalVirtual;
                    unsigned long long ullAvailVirtual;
                    unsigned long long ullAvailExtendedVirtual;
                } MEMORYSTATUSEX;

                int GlobalMemoryStatusEx(MEMORYSTATUSEX* lpBuffer);
            ', 'kernel32.dll');
        }

        return self::$ffi;
    }
}
