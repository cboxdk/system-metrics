<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Memory;

use FFI;
use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Reads memory metrics from macOS using host_statistics64() via FFI.
 *
 * This is the preferred method for macOS systems as it provides:
 * - ⚡ Fast performance (~0.01ms vs ~30ms for vm_stat + sysctl)
 * - 📊 Accurate memory statistics directly from kernel
 * - 🎯 Single native call instead of 3 shell commands
 * - 🔒 Stable API (native Mach kernel calls)
 *
 * Requires PHP FFI extension (enabled by default in PHP 7.4+).
 */
final class MacOsHostStatisticsMemorySource implements MemoryMetricsSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    /** @var int HOST_VM_INFO64 flavor constant */
    private const HOST_VM_INFO64 = 4;

    /** @var int VM_STATISTICS64_COUNT (struct size in natural_t units) */
    private const VM_STATISTICS64_COUNT = 38;

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

            $host = $ffi->mach_host_self(); // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $vm_info = $ffi->new('vm_statistics64_data_t');
            $count = $ffi->new('mach_msg_type_number_t');
            $count->cdata = self::VM_STATISTICS64_COUNT; // @phpstan-ignore property.notFound

            $kr = $ffi->host_statistics64( // @phpstan-ignore method.notFound
                $host,
                self::HOST_VM_INFO64,
                FFI::addr($vm_info),
                FFI::addr($count)
            );

            if ($kr !== 0) {
                /** @var Result<MemorySnapshot> */
                return Result::failure(
                    new SystemMetricsException("host_statistics64 failed with kern_return_t: {$kr}")
                );
            }

            // Get total physical memory and page size
            $totalBytes = $this->getPhysicalMemory($ffi);
            $pageSize = $this->getPageSize($ffi);

            // Parse memory statistics
            $snapshot = $this->parseSnapshot($vm_info, $totalBytes, $pageSize);

            return Result::success($snapshot);

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
     * Parse memory snapshot from vm_statistics64 structure.
     */
    private function parseSnapshot(\FFI\CData $vm_info, int $totalBytes, int $pageSize): MemorySnapshot
    {
        // Extract memory statistics from vm_statistics64_data_t
        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $freePages = $vm_info->free_count;
        // @phpstan-ignore property.notFound
        $activePages = $vm_info->active_count;
        // @phpstan-ignore property.notFound
        $inactivePages = $vm_info->inactive_count;
        // @phpstan-ignore property.notFound
        $wiredPages = $vm_info->wire_count;
        // @phpstan-ignore property.notFound
        $speculativePages = $vm_info->speculative_count;
        // @phpstan-ignore property.notFound
        $compressedPages = $vm_info->compressor_page_count;
        // @phpstan-ignore property.notFound
        $purgeable = $vm_info->purgeable_count;

        // Calculate memory values in bytes
        $freeBytes = $freePages * $pageSize;
        $activeBytes = $activePages * $pageSize;
        $inactiveBytes = $inactivePages * $pageSize;
        $wiredBytes = $wiredPages * $pageSize;
        $speculativeBytes = $speculativePages * $pageSize;
        $compressedBytes = $compressedPages * $pageSize;
        $purgeableBytes = $purgeable * $pageSize;

        // Calculate available memory (free + inactive + speculative + purgeable)
        // This matches macOS Activity Monitor's "available" calculation
        $availableBytes = $freeBytes + $inactiveBytes + $speculativeBytes + $purgeableBytes;

        // Calculate used memory (active + wired + compressed)
        $usedBytes = $activeBytes + $wiredBytes + $compressedBytes;

        // macOS swap metrics (best effort - may not be fully accurate)
        // Note: macOS manages swap dynamically, these are approximate
        $swapTotalBytes = 0;  // macOS doesn't expose max swap limit
        $swapUsedBytes = 0;   // Would need additional syscalls
        $swapFreeBytes = 0;

        return new MemorySnapshot(
            totalBytes: $totalBytes,
            freeBytes: $freeBytes,
            availableBytes: $availableBytes,
            usedBytes: $usedBytes,
            buffersBytes: 0,  // macOS doesn't have buffer cache like Linux
            cachedBytes: $inactiveBytes + $speculativeBytes,  // Approximate
            swapTotalBytes: $swapTotalBytes,
            swapUsedBytes: $swapUsedBytes,
            swapFreeBytes: $swapFreeBytes
        );
    }

    /**
     * Get total physical memory using sysctl.
     */
    private function getPhysicalMemory(FFI $ffi): int
    {
        $size = $ffi->new('size_t');
        // @phpstan-ignore property.notFound (uint64_t size)
        $size->cdata = 8;

        $memsize = $ffi->new('uint64_t');

        $result = $ffi->sysctlbyname( // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            'hw.memsize',
            FFI::addr($memsize),
            FFI::addr($size),
            null,
            0
        );

        if ($result !== 0) {
            throw new SystemMetricsException('Failed to get hw.memsize via sysctlbyname');
        }

        return (int) $memsize->cdata; // @phpstan-ignore property.notFound
    }

    /**
     * Get page size using sysctl.
     */
    private function getPageSize(FFI $ffi): int
    {
        $size = $ffi->new('size_t');
        // @phpstan-ignore property.notFound (uint64_t size)
        $size->cdata = 8;

        $pagesize = $ffi->new('uint64_t');

        $result = $ffi->sysctlbyname( // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            'vm.pagesize',
            FFI::addr($pagesize),
            FFI::addr($size),
            null,
            0
        );

        if ($result !== 0) {
            // Fallback to default macOS page size
            return 4096;
        }

        return (int) $pagesize->cdata; // @phpstan-ignore property.notFound
    }

    /**
     * Get or create FFI instance (cached for performance).
     */
    private function getFFI(): FFI
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef('
                typedef unsigned int mach_port_t;
                typedef int kern_return_t;
                typedef unsigned int natural_t;
                typedef int host_flavor_t;
                typedef unsigned int mach_msg_type_number_t;
                typedef unsigned long long uint64_t;
                typedef unsigned long size_t;

                // vm_statistics64 structure
                typedef struct {
                    natural_t free_count;
                    natural_t active_count;
                    natural_t inactive_count;
                    natural_t wire_count;
                    uint64_t zero_fill_count;
                    uint64_t reactivations;
                    uint64_t pageins;
                    uint64_t pageouts;
                    uint64_t faults;
                    uint64_t cow_faults;
                    uint64_t lookups;
                    uint64_t hits;
                    uint64_t purges;
                    natural_t purgeable_count;
                    natural_t speculative_count;
                    uint64_t decompressions;
                    uint64_t compressions;
                    uint64_t swapins;
                    uint64_t swapouts;
                    natural_t compressor_page_count;
                    natural_t throttled_count;
                    natural_t external_page_count;
                    natural_t internal_page_count;
                    uint64_t total_uncompressed_pages_in_compressor;
                } vm_statistics64_data_t;

                extern mach_port_t mach_host_self(void);

                kern_return_t host_statistics64(
                    mach_port_t host,
                    host_flavor_t flavor,
                    vm_statistics64_data_t *host_info,
                    mach_msg_type_number_t *host_info_count
                );

                int sysctlbyname(
                    const char *name,
                    void *oldp,
                    size_t *oldlenp,
                    void *newp,
                    size_t newlen
                );
            ', 'libSystem.dylib');
        }

        return self::$ffi;
    }
}
