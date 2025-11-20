<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Cpu;

use FFI;
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Read CPU metrics from Windows using GetSystemTimes() via FFI.
 *
 * This is the preferred method for Windows systems as it provides:
 * - ⚡ Fast performance (direct syscall vs WMI queries)
 * - 📊 System-wide CPU times (idle, kernel, user)
 * - 🔒 Native Win32 API access
 *
 * Note: GetSystemTimes() only provides system-wide totals, not per-core breakdown.
 * Per-core metrics would require PDH (Performance Data Helper) API.
 *
 * Requires PHP FFI extension (enabled by default in PHP 7.4+).
 */
final class WindowsFFICpuMetricsSource implements CpuMetricsSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    public function read(): Result
    {
        if (! extension_loaded('ffi')) {
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                new SystemMetricsException('FFI extension not available')
            );
        }

        try {
            $ffi = $this->getFFI();

            // Initialize FILETIME structures
            $idleTime = $ffi->new('FILETIME');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($idleTime === null) {
                /** @var Result<CpuSnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to allocate FFI memory')
                );
            }

            $kernelTime = $ffi->new('FILETIME');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($kernelTime === null) {
                /** @var Result<CpuSnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to allocate FFI memory')
                );
            }

            $userTime = $ffi->new('FILETIME');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($userTime === null) {
                /** @var Result<CpuSnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to allocate FFI memory')
                );
            }

            // Call GetSystemTimes
            $result = $ffi->GetSystemTimes( // @phpstan-ignore method.notFound (FFI methods defined via cdef)
                FFI::addr($idleTime),
                FFI::addr($kernelTime),
                FFI::addr($userTime)
            );

            if ($result === 0) {
                /** @var Result<CpuSnapshot> */
                return Result::failure(
                    new SystemMetricsException('GetSystemTimes failed')
                );
            }

            // Convert FILETIME to ticks
            // FILETIME represents 100-nanosecond intervals since January 1, 1601
            $idle = $this->fileTimeToTicks($idleTime);
            $kernel = $this->fileTimeToTicks($kernelTime);
            $user = $this->fileTimeToTicks($userTime);

            // Windows kernel time includes idle time, so system time = kernel - idle
            $system = $kernel - $idle;

            // Create CpuTimes (Windows doesn't track nice, iowait, irq, softirq, steal separately)
            $cpuTimes = new CpuTimes(
                user: $user,
                nice: 0,        // Not tracked on Windows
                system: $system,
                idle: $idle,
                iowait: 0,      // Not tracked separately on Windows
                irq: 0,         // Not tracked separately on Windows
                softirq: 0,     // Not tracked separately on Windows
                steal: 0        // Not applicable on Windows (virtualization concept)
            );

            // GetSystemTimes only provides system-wide totals, not per-core
            // Would need PDH API or GetProcessorSystemInformation for per-core data
            return Result::success(new CpuSnapshot(
                total: $cpuTimes,
                perCore: [] // System-wide only
            ));

        } catch (\Throwable $e) {
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                new SystemMetricsException(
                    'Failed to read CPU metrics via FFI: '.$e->getMessage(),
                    previous: $e
                )
            );
        }
    }

    /**
     * Convert FILETIME structure to tick count.
     *
     * FILETIME represents 100-nanosecond intervals since January 1, 1601.
     * We convert to simple tick count for consistency with Linux/macOS.
     *
     * @param  \FFI\CData  $fileTime  FILETIME structure
     */
    private function fileTimeToTicks(\FFI\CData $fileTime): int
    {
        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $high = (int) $fileTime->dwHighDateTime;
        // @phpstan-ignore property.notFound
        $low = (int) $fileTime->dwLowDateTime;

        // Combine high and low 32-bit values into 64-bit value
        // Convert to ticks (divide by 10000 to get milliseconds, then to reasonable tick unit)
        $ticks = (($high << 32) | $low) / 10000; // Convert 100ns intervals to ~ticks

        return (int) $ticks;
    }

    /**
     * Get or create FFI instance (cached for performance).
     */
    private function getFFI(): FFI
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef('
                // FILETIME structure - represents 100-nanosecond intervals since Jan 1, 1601
                typedef struct {
                    unsigned int dwLowDateTime;
                    unsigned int dwHighDateTime;
                } FILETIME;

                // Get system-wide CPU times
                // lpIdleTime: idle time
                // lpKernelTime: kernel time (includes idle time)
                // lpUserTime: user time
                int GetSystemTimes(FILETIME* lpIdleTime, FILETIME* lpKernelTime, FILETIME* lpUserTime);
            ', 'kernel32.dll');
        }

        return self::$ffi;
    }
}
