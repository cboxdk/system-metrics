<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Uptime;

use Cbox\SystemMetrics\Contracts\UptimeSource;
use Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use DateTimeImmutable;
use FFI;

/**
 * Read uptime from Windows using GetTickCount64() via FFI.
 *
 * This is the preferred method for Windows systems as it provides:
 * - ⚡ Fast performance (direct syscall)
 * - 📊 Millisecond precision since boot
 * - 🔒 Native Win32 API access
 *
 * Requires PHP FFI extension (enabled by default in PHP 7.4+).
 */
final class WindowsFFIUptimeSource implements UptimeSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    public function read(): Result
    {
        if (! extension_loaded('ffi')) {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(
                new SystemMetricsException('FFI extension not available')
            );
        }

        try {
            $ffi = $this->getFFI();

            // Get milliseconds since system boot
            $uptimeMs = $ffi->GetTickCount64(); // @phpstan-ignore method.notFound (FFI methods defined via cdef)

            // Convert to seconds
            $uptimeSeconds = (int) ($uptimeMs / 1000);

            // Calculate boot time
            $currentTime = new DateTimeImmutable;
            $bootTime = $currentTime->modify("-{$uptimeSeconds} seconds");

            return Result::success(new UptimeSnapshot(
                totalSeconds: $uptimeSeconds,
                bootTime: $bootTime,
                timestamp: $currentTime
            ));

        } catch (\Throwable $e) {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(
                new SystemMetricsException(
                    'Failed to read uptime via FFI: '.$e->getMessage(),
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
                // Returns milliseconds since system boot
                // Uses unsigned long long (64-bit) to avoid 49-day overflow
                unsigned long long GetTickCount64(void);
            ', 'kernel32.dll');
        }

        return self::$ffi;
    }
}
