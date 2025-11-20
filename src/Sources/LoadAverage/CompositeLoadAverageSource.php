<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\LoadAverage;

use PHPeek\SystemMetrics\Contracts\LoadAverageSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use PHPeek\SystemMetrics\Support\OsDetector;

/**
 * Composite load average source with automatic OS detection.
 *
 * Delegates to the appropriate platform-specific implementation
 * based on the detected operating system.
 */
final class CompositeLoadAverageSource implements LoadAverageSource
{
    private ?LoadAverageSource $source;

    public function __construct(?LoadAverageSource $source = null)
    {
        $this->source = $source ?? $this->createOsSpecificSource();
    }

    /**
     * Read load average using the OS-specific implementation.
     *
     * @return Result<\PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot>
     */
    public function read(): Result
    {
        if ($this->source === null) {
            /** @var Result<\PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot> */
            return Result::failure(
                UnsupportedOperatingSystemException::forOs(PHP_OS_FAMILY)
            );
        }

        return $this->source->read();
    }

    /**
     * Create OS-specific load average source.
     */
    private function createOsSpecificSource(): ?LoadAverageSource
    {
        if (OsDetector::isLinux()) {
            return new LinuxProcLoadAverageSource;
        }

        if (OsDetector::isMacOs()) {
            return new MacOsFFILoadAverageSource;
        }

        return null;
    }
}
