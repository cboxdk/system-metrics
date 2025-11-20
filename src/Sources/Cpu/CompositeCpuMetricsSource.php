<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Cpu;

use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use PHPeek\SystemMetrics\Support\OsDetector;

/**
 * Routes CPU metrics reading to the appropriate OS-specific source.
 */
final class CompositeCpuMetricsSource implements CpuMetricsSource
{
    private readonly CpuMetricsSource $source;

    public function __construct(?CpuMetricsSource $source = null)
    {
        $this->source = $source ?? $this->createSource();
    }

    public function read(): Result
    {
        return $this->source->read();
    }

    private function createSource(): CpuMetricsSource
    {
        if (OsDetector::isLinux()) {
            return new LinuxProcCpuMetricsSource;
        }

        if (OsDetector::isMacOs()) {
            // Priority order for macOS:
            // 1. host_processor_info() via FFI (fast, accurate, modern)
            // 2. sysctl kern.cp_time (fallback for older systems or FFI unavailable)
            // 3. MinimalCpuMetricsSource (last resort - returns zeros)
            return new FallbackCpuMetricsSource([
                new MacOsHostProcessorInfoSource,
                new MacOsSysctlCpuMetricsSource,
                new MinimalCpuMetricsSource,
            ]);
        }

        throw UnsupportedOperatingSystemException::forOs(OsDetector::getFamily());
    }
}
