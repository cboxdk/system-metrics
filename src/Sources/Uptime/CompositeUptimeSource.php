<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Uptime;

use PHPeek\SystemMetrics\Contracts\UptimeSource;
use PHPeek\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;
use PHPeek\SystemMetrics\Support\OsDetector;

/**
 * Composite uptime source with automatic OS detection.
 */
final class CompositeUptimeSource implements UptimeSource
{
    public function __construct(
        private readonly ?UptimeSource $source = null,
    ) {}

    public function read(): Result
    {
        if ($this->source !== null) {
            return $this->source->read();
        }

        if (OsDetector::isLinux()) {
            $source = new LinuxProcUptimeSource;

            return $source->read();
        }

        if (OsDetector::isMacOs()) {
            $source = new MacOsFFIUptimeSource;

            return $source->read();
        }

        if (OsDetector::isWindows()) {
            $source = new WindowsFFIUptimeSource;

            return $source->read();
        }

        if (OsDetector::isFreeBSD()) {
            $source = new FreeBSDSysctlUptimeSource;

            return $source->read();
        }

        /** @var Result<UptimeSnapshot> */
        return Result::failure(
            new SystemMetricsException('Uptime metrics not supported on this platform')
        );
    }
}
