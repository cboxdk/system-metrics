<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Cpu;

use FFI;
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Read CPU metrics from FreeBSD using sysctl via FFI.
 *
 * FreeBSD provides CPU time counters via sysctl:
 * - kern.cp_time: System-wide CPU time (user, nice, system, interrupt, idle)
 * - kern.cp_times: Per-core CPU times
 *
 * Uses sysctlbyname() C function via FFI for native performance.
 */
final class FreeBSDSysctlCpuMetricsSource implements CpuMetricsSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    public function read(): Result
    {
        try {
            // Get system-wide CPU times
            $totalTimes = $this->readCpTimes();

            if ($totalTimes === null) {
                /** @var Result<CpuSnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to read kern.cp_time')
                );
            }

            // Get per-core CPU times
            $perCore = $this->readPerCoreCpTimes();

            return Result::success(new CpuSnapshot(
                total: $totalTimes,
                perCore: $perCore,
            ));

        } catch (\Throwable $e) {
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                new SystemMetricsException(
                    'Failed to read CPU metrics via sysctl: '.$e->getMessage(),
                    previous: $e
                )
            );
        }
    }

    /**
     * Read system-wide CPU times from kern.cp_time.
     *
     * Returns array of 5 longs: [user, nice, system, interrupt, idle]
     */
    private function readCpTimes(): ?CpuTimes
    {
        $ffi = $this->getFFI();

        // FreeBSD kern.cp_time returns array of 5 longs
        $cpTime = $ffi->new('long[5]');
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($cpTime === null) {
            return null;
        }

        $sizePtr = $ffi->new('unsigned long');
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($sizePtr === null) {
            return null;
        }

        $size = FFI::sizeof($cpTime);
        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $sizePtr->cdata = $size;

        // @phpstan-ignore method.notFound (FFI methods defined via cdef)
        $result = $ffi->sysctlbyname(
            'kern.cp_time',
            $cpTime,
            FFI::addr($sizePtr),
            null,
            0
        );

        if ($result !== 0) {
            return null;
        }

        // FreeBSD cp_time indices: 0=user, 1=nice, 2=system, 3=interrupt, 4=idle
        return new CpuTimes(
            user: (int) $cpTime[0],
            nice: (int) $cpTime[1],
            system: (int) $cpTime[2],
            idle: (int) $cpTime[4],
            iowait: 0, // FreeBSD doesn't track iowait separately
            irq: (int) $cpTime[3], // interrupt time
            softirq: 0, // Not available on FreeBSD
            steal: 0, // Not applicable on bare metal
        );
    }

    /**
     * Read per-core CPU times from kern.cp_times.
     *
     * Returns array of (ncpu * 5) longs
     *
     * @return array<CpuCoreTimes>
     */
    private function readPerCoreCpTimes(): array
    {
        $ffi = $this->getFFI();

        // First, get number of CPUs
        $ncpu = $this->getCpuCount();

        if ($ncpu === 0) {
            return []; // Fallback if we can't determine CPU count
        }

        // Allocate buffer for all cores (ncpu * 5 longs)
        $bufferSize = $ncpu * 5;
        $cpTimes = $ffi->new("long[{$bufferSize}]");
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($cpTimes === null) {
            return [];
        }

        $sizePtr = $ffi->new('unsigned long');
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($sizePtr === null) {
            return [];
        }

        $size = FFI::sizeof($cpTimes);
        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $sizePtr->cdata = $size;

        // @phpstan-ignore method.notFound (FFI methods defined via cdef)
        $result = $ffi->sysctlbyname(
            'kern.cp_times',
            $cpTimes,
            FFI::addr($sizePtr),
            null,
            0
        );

        if ($result !== 0) {
            return []; // Fallback on failure
        }

        // Parse per-core data
        $perCore = [];

        for ($i = 0; $i < $ncpu; $i++) {
            $offset = $i * 5;

            $times = new CpuTimes(
                user: (int) $cpTimes[$offset + 0],
                nice: (int) $cpTimes[$offset + 1],
                system: (int) $cpTimes[$offset + 2],
                idle: (int) $cpTimes[$offset + 4],
                iowait: 0,
                irq: (int) $cpTimes[$offset + 3],
                softirq: 0,
                steal: 0,
            );

            $perCore[] = new CpuCoreTimes(
                coreIndex: $i,
                times: $times,
            );
        }

        return $perCore;
    }

    /**
     * Get CPU count from hw.ncpu sysctl.
     */
    private function getCpuCount(): int
    {
        $ffi = $this->getFFI();

        $ncpu = $ffi->new('int');
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($ncpu === null) {
            return 0;
        }

        $sizePtr = $ffi->new('unsigned long');
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($sizePtr === null) {
            return 0;
        }

        $size = FFI::sizeof($ncpu);
        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $sizePtr->cdata = $size;

        // @phpstan-ignore method.notFound (FFI methods defined via cdef)
        $result = $ffi->sysctlbyname(
            'hw.ncpu',
            FFI::addr($ncpu),
            FFI::addr($sizePtr),
            null,
            0
        );

        if ($result !== 0) {
            return 0;
        }

        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        return (int) $ncpu->cdata;
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
