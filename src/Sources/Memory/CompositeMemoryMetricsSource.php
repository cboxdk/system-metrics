<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Memory;

use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use PHPeek\SystemMetrics\Support\OsDetector;

/**
 * Routes memory metrics reading to the appropriate OS-specific source.
 */
final class CompositeMemoryMetricsSource implements MemoryMetricsSource
{
    private readonly MemoryMetricsSource $source;

    public function __construct(?MemoryMetricsSource $source = null)
    {
        $this->source = $source ?? $this->createSource();
    }

    public function read(): Result
    {
        return $this->source->read();
    }

    private function createSource(): MemoryMetricsSource
    {
        if (OsDetector::isLinux()) {
            return new LinuxProcMeminfoMemoryMetricsSource;
        }

        if (OsDetector::isMacOs()) {
            return new FallbackMemoryMetricsSource([
                new MacOsHostStatisticsMemorySource,  // 1. FFI (fast, accurate)
                new MacOsVmStatMemoryMetricsSource,   // 2. vm_stat (older systems)
            ]);
        }

        if (OsDetector::isWindows()) {
            return new WindowsFFIMemoryMetricsSource;
        }

        if (OsDetector::isFreeBSD()) {
            return new FreeBSDSysctlMemoryMetricsSource;
        }

        throw UnsupportedOperatingSystemException::forOs(OsDetector::getFamily());
    }
}
