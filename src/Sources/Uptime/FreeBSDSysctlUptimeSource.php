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
 * Read uptime from FreeBSD using sysctl kern.boottime via FFI.
 *
 * Uses sysctlbyname() to read kern.boottime timeval structure.
 */
final class FreeBSDSysctlUptimeSource implements UptimeSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    public function read(): Result
    {
        try {
            $ffi = $this->getFFI();

            // kern.boottime returns a timeval structure: {tv_sec, tv_usec}
            $bootTime = $ffi->new('struct timeval');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($bootTime === null) {
                /** @var Result<UptimeSnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to allocate memory for boottime structure')
                );
            }

            $sizePtr = $ffi->new('unsigned long');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($sizePtr === null) {
                /** @var Result<UptimeSnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to allocate memory for size pointer')
                );
            }

            $size = FFI::sizeof($bootTime);

            // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
            $sizePtr->cdata = $size;

            // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $result = $ffi->sysctlbyname(
                'kern.boottime',
                FFI::addr($bootTime),
                FFI::addr($sizePtr),
                null,
                0
            );

            if ($result !== 0) {
                /** @var Result<UptimeSnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to read kern.boottime')
                );
            }

            // Extract seconds from timeval
            // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
            $bootTimestamp = (int) $bootTime->tv_sec;

            $bootTimeObj = (new DateTimeImmutable)->setTimestamp($bootTimestamp);
            $currentTime = new DateTimeImmutable;

            $uptimeSeconds = $currentTime->getTimestamp() - $bootTimestamp;

            return Result::success(new UptimeSnapshot(
                totalSeconds: $uptimeSeconds,
                bootTime: $bootTimeObj,
                timestamp: $currentTime,
            ));

        } catch (\Throwable $e) {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(
                new SystemMetricsException(
                    'Failed to read uptime via sysctl: '.$e->getMessage(),
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
                // timeval structure
                struct timeval {
                    long tv_sec;
                    long tv_usec;
                };

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
