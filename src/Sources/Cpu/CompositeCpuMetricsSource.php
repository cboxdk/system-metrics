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
            return new MacOsSysctlCpuMetricsSource;
        }

        throw UnsupportedOperatingSystemException::forOs(OsDetector::getFamily());
    }
}
