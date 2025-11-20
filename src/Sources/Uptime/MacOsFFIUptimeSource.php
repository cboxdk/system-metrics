<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Uptime;

use DateTimeImmutable;
use FFI;
use PHPeek\SystemMetrics\Contracts\UptimeSource;
use PHPeek\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Reads system uptime from macOS using sysctlbyname() via FFI.
 *
 * This is the preferred method for macOS systems as it provides:
 * - ⚡ Fast performance (~0.005ms vs ~10ms for sysctl command)
 * - 📊 Direct access to kernel boottime structure
 * - 🔒 Stable API (standard sysctl)
 *
 * Requires PHP FFI extension (enabled by default in PHP 7.4+).
 */
final class MacOsFFIUptimeSource implements UptimeSource
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

            // timeval structure: { long tv_sec; int tv_usec; }
            $boottime = $ffi->new('struct timeval');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($boottime === null) {
                /** @var Result<UptimeSnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to allocate memory for boottime structure')
                );
            }

            $size = $ffi->new('size_t');
            $size->cdata = // @phpstan-ignore property.notFound
                FFI::sizeof($boottime);

            $result = $ffi->sysctlbyname( // @phpstan-ignore method.notFound (FFI methods defined via cdef)

                'kern.boottime',
                FFI::addr($boottime),
                FFI::addr($size),
                null,
                0
            );

            if ($result !== 0) {
                /** @var Result<UptimeSnapshot> */
                return Result::failure(
                    new SystemMetricsException('sysctlbyname(kern.boottime) failed')
                );
            }

            // Extract boot timestamp
            $bootTimestamp = (int) $boottime->tv_sec; // @phpstan-ignore property.notFound
            $currentTime = new DateTimeImmutable;
            $bootTime = new DateTimeImmutable('@'.$bootTimestamp);
            $uptimeSeconds = $currentTime->getTimestamp() - $bootTimestamp;

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
                typedef unsigned long size_t;
                typedef long time_t;
                typedef int suseconds_t;

                // timeval structure from sys/time.h
                struct timeval {
                    time_t tv_sec;
                    suseconds_t tv_usec;
                };

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
